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

let detailIntervalTimer = false;
let savedAuctionItemDetails = {};
let neverOutOfStock = false;
secondsToClearMessage = 5;

let displaySearchText = "";
let postVariables = false;
let auctionItemFieldNames = false;
let auctionItemKeyLookup = false;
let auctionItemResults = false;
let auctionItemDetailData = false;
let constraints = false;
let resultCount = false;
let queryTime = false;
let pageGroupingData = [];
let productCategoryGroupIds = false;
let emptyImageFilename = "/getimage.php?code=no_product_image";
let productTagCodes = false;
let taggedProductsFunctions = [];
let lastSortOrder = "";
let sortedIndexes = {};
let sidebarReductionNeeded = true;
let cdnDomain = "";
let orderedItems = false;
let filterTextMinimumCount = 6;
let clickedFilterId = "";
let windowScroll = 0;
let siteSearchPageLink = false;
let specificationDescriptions = false;
let watchListAuctionItemIds = false;

let priceRanges = [ { minimum_cost: 0, maximum_cost: 99.99, label: "Under $100", count: 0 },
    { minimum_cost: 100, maximum_cost: 199.99, label: "$100-200", count: 0 },
    { minimum_cost: 200, maximum_cost: 499.99, label: "$200-500", count: 0 },
    { minimum_cost: 500, maximum_cost: 999.99, label: "$500-1000", count: 0 },
    { minimum_cost: 1000, maximum_cost: 99999999.99, label: "Over $1000", count: 0 },
];

function checkFFLRequirements() {
    const $fflSelectWrapper = $("#ffl_selection_wrapper");
    if ($fflSelectWrapper.length > 0) {
        const productTagId = $fflSelectWrapper.data("product_tag_id");
        let foundFFLProduct = false;
        $("#shopping_cart_items_wrapper").find(".shopping-cart-item").each(function () {
            const productTagIdsString = $(this).data("product_tag_ids");
            if (empty(productTagIdsString)) {
                return true;
            }
            const productTagIdArray = String(productTagIdsString).split(",");
            if (isInArray(productTagId, productTagIdArray)) {
                foundFFLProduct = true;
                return false;
            }
        });
        const shippingState = $("#state").val();
        if (!empty(shippingState)) {
            if ($(".product-tag-ffl_required_" + shippingState.toLowerCase()).length > 0) {
                foundFFLProduct = true;
            }
        }

        const pickup = $("#shipping_method_id option:selected").data("pickup");
        const userFFLNumber = $("#user_ffl_number").val();

        const $fflSection = $("#ffl_section");
        let fflRequired = false;
        if (foundFFLProduct && empty(pickup) && empty(userFFLNumber)) {
            fflRequired = true;
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

function getWatchListItems() {
    loadAjaxRequest("/auction-controller?ajax=true&url_action=get_user_watchlists",function(returnArray) {

    });
}

function getWatchListItemCount() {
    loadAjaxRequest("/auction-controller?ajax=true&url_action=get_auction_watchlist_item_count");
}

function addItemToWatchList(auctionItemId) {
    let postFields = {};
    postFields['auction_item_id'] = auctionItemId;
    loadAjaxRequest("/auction-controller?ajax=true&url_action=add_to_user_watchlist", postFields, function(returnArray) {
        if (!empty(returnArray['message'])) {
            displayInfoMessage(returnArray['message'],true);
            $('#_auction_item_details_wrapper').toggleClass('in-watchlist');
            $('#auction_item_' + auctionItemId).toggleClass('in-watchlist');
        }
    });
}

function removeItemFromWatchList(auctionItemId) {
    loadAjaxRequest("/auction-controller?ajax=true&url_action=remove_from_auction_watchlist&auction_item_id=" + auctionItemId);
}

function sortSearchResults(sortField) {
    const sortObjects = [];
    for (let i in auctionItemResults) {
        sortObjects.push({ "data_value": auctionItemResults[i][auctionItemKeyLookup[sortField]], "index": i });
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
}

function sortSearchResultsByPrice(sortField, sortDirection) {
    const sortObjects = [];
    for (let i in auctionItemResults) {
        sortObjects.push({ "data_value": auctionItemResults[i][auctionItemKeyLookup[sortField]], "index": i });
    }
    sortObjects.sort(function (a, b) {
        if (a.data_value === b.data_value) {
            return 0;
        } else {
            const aPrice = !empty(a.data_value) ? parseFloat(a.data_value.replace(new RegExp("$", 'ig'), "").replace(new RegExp(",", 'ig'), "")) : 0;
            const bPrice = !empty(b.data_value) ? parseFloat(b.data_value.replace(new RegExp("$", 'ig'), "").replace(new RegExp(",", 'ig'), "")) : 0;
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
        catalogResultElementId = "_auction_result";
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
    if (auctionItemKeyLookup === false) {
        auctionItemKeyLookup = {};
        for (let i in auctionItemFieldNames) {
            auctionItemKeyLookup[auctionItemFieldNames[i]] = i;
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
        $("#" + catalogItemContainer).html("<p>No Results Found</p>");
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
            $selectedFilters.append(`<div class='selected-filter sidebar-selected-filter' data-id='${ thisId }'>
                ${ filterName }: <span class='filter-text-value'>${ $(this).closest("div.filter-option").find("label").html() }</span>
                <span class='far fa-times-circle'></span>
            </div>`);
        });
        $selectedFilters.find("span.reductive-count").remove();
        filterIndex++;
    });

    let sortOrder = $("#auction_item_sort_order").val();
    if (sortOrder !== lastSortOrder || empty(lastSortOrder) || sortedIndexes.length === 0) {
        sortedIndexes = {};
        lastSortOrder = sortOrder;

        switch (sortOrder) {
            case "ending_soon":
                sortSearchResults("end_time");
                break;
            case "just_listed":
                sortSearchResults("start_time");
                break;
            case "highest_bid":
                sortSearchResultsByPrice("starting_bid", -1);
                break;
            case "lowest_bid":
                sortSearchResultsByPrice("starting_bid", 1);
                break;
            case "highest_price":
                sortSearchResultsByPrice("buy_now_price", -1);
                break;
            case "lowest_price":
                sortSearchResultsByPrice("buy_now_price", 1);
                break;
            default:
                for (let i in auctionItemResults) {
                    sortedIndexes[i] = i;
                }
                break;
        }
    }
    for (let i in sortedIndexes) {
        let resultIndex = sortedIndexes[i];
        let thisAuctionItem = auctionItemResults[resultIndex];
        auctionItemResults[resultIndex]['hide_auction_item'] = true;

// check to see if product is in filtered items

        let displayThisAuctionItem = true;
        for (filterIndex in filterValues) {
            let fieldName = filterValues[filterIndex]['field_name'];
            if (fieldName === "price_range") {
                let foundValue = false;
                if ("sale_price" in auctionItemKeyLookup && auctionItemKeyLookup['sale_price'] in thisAuctionItem) {
                    if (thisAuctionItem[auctionItemKeyLookup['sale_price']].length === 0) {
                        continue;
                    }
                    const productCost = parseFloat(thisAuctionItem[auctionItemKeyLookup['sale_price']].replace(new RegExp(",", 'ig'), ""));
                    for (let i in filterValues[filterIndex]['filter_values']) {
                        const priceRangeIndex = filterValues[filterIndex]['filter_values'][i];
                        if (priceRangeIndex in priceRanges && productCost >= priceRanges[priceRangeIndex]['minimum_cost'] && productCost <= priceRanges[priceRangeIndex]['maximum_cost']) {
                            foundValue = true;
                            break;
                        }
                    }
                }
                if (!foundValue) {
                    displayThisAuctionItem = false;
                    break;
                }
                continue;
            }
            if (fieldName.startsWith("auction_specification_")) {
                if ("auction_item_specifications" in auctionItemKeyLookup && auctionItemKeyLookup['auction_item_specifications'] in thisAuctionItem) {
                    const auctionSpecificationId = fieldName.substr("auction_specification_".length);
                    const auctionItemSpecification = thisAuctionItem[auctionItemKeyLookup['auction_item_specifications']]
                        .find(item => item.auction_specification_id === auctionSpecificationId);
                    if (auctionItemSpecification && filterValues[filterIndex]['filter_values'].includes(auctionItemSpecification.hash)) {
                        continue;
                    } else {
                        displayThisAuctionItem = false;
                        break;
                    }
                }
            }
            if (!(fieldName in auctionItemKeyLookup) || !(auctionItemKeyLookup[fieldName] in thisAuctionItem)) {
                displayThisAuctionItem = false;
                break;
            }
            const auctionItemValues = (thisAuctionItem[auctionItemKeyLookup[fieldName]] + "").split(",");
            let foundValue = false;
            for (let i in auctionItemValues) {
                if (!empty(auctionItemValues[i]) && isInArray(auctionItemValues[i], filterValues[filterIndex]['filter_values'], true)) {
                    foundValue = true;
                    break;
                }
            }
            if (!foundValue) {
                displayThisAuctionItem = false;
                break;
            }
        }

        const auctionType = $('#_filter_tabs button.selected').data("auction_type");
        if (auctionType === "auction") {
            if (!("starting_bid" in auctionItemKeyLookup) || !(auctionItemKeyLookup["starting_bid"] in thisAuctionItem)
                || isNaN(thisAuctionItem[auctionItemKeyLookup["starting_bid"]]) || thisAuctionItem[auctionItemKeyLookup["starting_bid"]] <= 0) {
                displayThisAuctionItem = false;
            }
        } else if (auctionType === "buy_now") {
            if (!("buy_now_price" in auctionItemKeyLookup) || !(auctionItemKeyLookup["buy_now_price"] in thisAuctionItem)
                || isNaN(thisAuctionItem[auctionItemKeyLookup["buy_now_price"]]) || thisAuctionItem[auctionItemKeyLookup["buy_now_price"]] <= 0) {
                displayThisAuctionItem = false;
            }
        } else if (auctionType === "make_offer") {
            if (!("can_offer" in auctionItemKeyLookup) || !(auctionItemKeyLookup["can_offer"] in thisAuctionItem) || thisAuctionItem[auctionItemKeyLookup["can_offer"]] !== 1) {
                displayThisAuctionItem = false;
            }
        }

        if (!displayThisAuctionItem) {
            continue;
        }
        localResultCount++;
        auctionItemResults[resultIndex]['hide_auction_item'] = false;
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
        if (auctionItemResults[resultIndex]['hide_auction_item']) {
            continue;
        }
        if (skipCount > 0 && skippedCount < skipCount) {
            skippedCount++;
            continue;
        }
        let catalogResult = originalCatalogResult;
        let otherClasses = "";
        catalogResult = catalogResult.replace(new RegExp("%image_src%", 'ig'), "src");
        const imageBaseFilenameKey = auctionItemKeyLookup['image_base_filename'];
        const imageBaseFilename = auctionItemResults[resultIndex][auctionItemKeyLookup];
        const auctionItemId = auctionItemResults[resultIndex][auctionItemKeyLookup['auction_item_id']];
        for (let fieldNumber in auctionItemFieldNames) {
            let thisValue = auctionItemResults[resultIndex][fieldNumber];

            if (auctionItemKeyLookup['bid_count'] === fieldNumber) {
                thisValue = thisValue + (thisValue === 1 ? " Bid" : " Bids");
            }
            catalogResult = catalogResult.replace(new RegExp("%" + auctionItemFieldNames[fieldNumber] + "%", 'ig'), thisValue);
        }

        catalogResult = catalogResult.replace(new RegExp("%auction_item_image%", 'ig'),
            (empty(imageBaseFilename) || imageBaseFilename === emptyImageFilename ? emptyImageFilename : (imageBaseFilename.substring(0, 4) === "http" || imageBaseFilename.substring(0, 2) === "//" ? "" : cdnDomain + "/cache/image-small-") + imageBaseFilename));
        for (let fieldNumber in auctionItemFieldNames) {
            catalogResult = catalogResult.replace(new RegExp("%hidden_if_empty:" + auctionItemFieldNames[fieldNumber] + "%", 'ig'), (empty(auctionItemResults[resultIndex][fieldNumber]) ? "hidden" : ""));
        }
        if ("product_tag_ids" in auctionItemKeyLookup && !empty(auctionItemResults[resultIndex][auctionItemKeyLookup['product_tag_ids']])) {
            const productTagIds = auctionItemResults[resultIndex][auctionItemKeyLookup['product_tag_ids']].split(",");
            for (let i in productTagIds) {
                const productTagCode = productTagCodes[productTagIds[i]];
                if (!empty(productTagCode)) {
                    otherClasses += (empty(otherClasses) ? "" : " ") + "product-tag-code-" + productTagCode.replace(new RegExp("_", "ig"), "-");
                }
            }
        }

        catalogResult = catalogResult.replace(new RegExp("%other_classes%", 'ig'), otherClasses);
        insertedCatalogItems[catalogItemIndex++] = catalogResult;
        productCatalogItems[auctionItemId] = {};
        count++;
        if ($showCount.length > 0 && count >= parseInt($showCount.val())) {
            break;
        }
    }

    let $catalogItemWrapper = $("#" + catalogItemWrapper);
    $catalogItemWrapper.html(insertedCatalogItems.join(""));
    $catalogItemWrapper.append("<div class='clear-div'></div>");
    $catalogItemWrapper.find("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({
        social_tools: false,
        default_height: 480,
        default_width: 854,
        deeplinking: false
    });

    for (let i in watchListAuctionItemIds) {
        $('#auction_item_' + watchListAuctionItemIds[i]).addClass("in-watchlist");
    }

    setTimeout(function () {
        reduceSidebar();
    }, 100);
    if (scrollToTop) {
        window.scrollTo(0, 0);
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
    let thisFilter = $sidebarFilter.html();
    let allFilters;
    let oneExpanded = false;
    if (empty(constraints)) {
        return;
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

    if ("specifications" in constraints) {

        for (let specificationIndex in specificationDescriptions) {
            if (!(specificationDescriptions[specificationIndex]['id'] in constraints['specifications'])) {
                continue;
            }
            thisFilter = $sidebarFilter.html();
            thisFilter = thisFilter.replace(new RegExp("%filter_id%", 'ig'), "filter_id_" + filterIndex++);
            thisFilter = thisFilter.replace(new RegExp("%filter_title%", 'ig'), specificationDescriptions[specificationIndex]['description']);
            thisFilter = thisFilter.replace(new RegExp("%search_text%", 'ig'), "Search " + specificationDescriptions[specificationIndex]['description']);
            thisFilter = thisFilter.replace(new RegExp("%field_name%", 'ig'), "auction_specification_" + specificationDescriptions[specificationIndex]['id']);
            if (Object.keys(constraints['specifications'][specificationDescriptions[specificationIndex]['id']]).length < filterTextMinimumCount) {
                thisFilter = thisFilter.replace(new RegExp("%other_classes%", 'ig'), "no-filter-text");
            } else {
                thisFilter = thisFilter.replace(new RegExp("%other_classes%", 'ig'), "");
            }
            allFilters = "";
            const fieldValues = constraints['specifications'][specificationDescriptions[specificationIndex]['id']]['field_values'];
            for (let hash in fieldValues) {
                allFilters += `<div class='filter-option'>
                    <div class='filter-option-checkbox'>
                        <input type='checkbox' id='specification_value_${ hash }' value='${ hash }' data-specification-count="${ fieldValues[hash]['count'] }">
                    </div>
                    <div class='filter-option-label'>
                        <label class='checkbox-label' for='specification_value_${ hash }'>${ fieldValues[hash]['field_value'] }</label>
                    </div>
                </div>`;
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

    for (let i in auctionItemResults) {
        if ("hide_auction_item" in auctionItemResults[i] && auctionItemResults[i]['hide_auction_item']) {
            continue;
        }
        const thisAuctionItem = auctionItemResults[i];

        if ("product_category_ids" in auctionItemKeyLookup && auctionItemKeyLookup['product_category_ids'] in thisAuctionItem) {
            const productCategoryIds = thisAuctionItem[auctionItemKeyLookup['product_category_ids']].split(",");
            for (let i in productCategoryIds) {
                if ("product_category_id_" + productCategoryIds[i] in displayCountArray) {
                    displayCountArray["product_category_id_" + productCategoryIds[i]]++;
                } else {
                    displayCountArray["product_category_id_" + productCategoryIds[i]] = 1;
                }
            }
        }
        if ("product_tag_ids" in auctionItemKeyLookup && auctionItemKeyLookup['product_tag_ids'] in thisAuctionItem) {
            const productTagIds = thisAuctionItem[auctionItemKeyLookup['product_tag_ids']].split(",");
            for (let i in productTagIds) {
                if ("product_tag_id_" + productTagIds[i] in displayCountArray) {
                    displayCountArray["product_tag_id_" + productTagIds[i]]++;
                } else {
                    displayCountArray["product_tag_id_" + productTagIds[i]] = 1;
                }
            }
        }
        if ("auction_item_specifications" in auctionItemKeyLookup && auctionItemKeyLookup['auction_item_specifications'] in thisAuctionItem) {
            thisAuctionItem[auctionItemKeyLookup['auction_item_specifications']].forEach(auctionItemSpecification => {
                if ("specification_value_" + auctionItemSpecification.hash in displayCountArray) {
                    displayCountArray["specification_value_" + auctionItemSpecification.hash]++;
                } else {
                    displayCountArray["specification_value_" + auctionItemSpecification.hash] = 1;
                }
            });
        }
        // if ("sale_price" in auctionItemKeyLookup && auctionItemKeyLookup['sale_price'] in thisAuctionItem) {
        //     if (thisAuctionItem[auctionItemKeyLookup['sale_price']].length === 0) {
        //         continue;
        //     }
        //     const productCost = parseFloat(thisAuctionItem[auctionItemKeyLookup['sale_price']].replace(new RegExp(",", 'ig'), ""));
        //
        //     for (let i in priceRanges) {
        //         if (productCost >= priceRanges[i]['minimum_cost'] && productCost <= priceRanges[i]['maximum_cost']) {
        //             priceRanges[i]['count']++;
        //         }
        //     }
        // }
        const locationsExist = ($("input[type=radio][name=location_availability]").length > 0);
        let locationIds = [];
        if (locationsExist) {
            for (let i in locationIds) {
                if ("inventory_quantity_" + locationIds[i] in auctionItemKeyLookup && auctionItemKeyLookup["inventory_quantity_" + locationIds[i]] in thisAuctionItem && thisAuctionItem[auctionItemKeyLookup["inventory_quantity_" + locationIds[i]]] > 0) {
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
    const $selectedFilters = $("#selected_filters").find(".primary-selected-filter");
    if ($selectedFilters.length <= 1) {
        $selectedFilters.addClass("not-removable");
    } else {
        $selectedFilters.removeClass("not-removable");
    }
}

function getFFLDealers(preferredOnly) {
    if (empty(preferredOnly)) {
        preferredOnly = "";
        if ($("#show_preferred_only").length > 0 && $("#show_preferred_only").prop("checked")) {
            preferredOnly = true;
        }
    } else {
        preferredOnly = true;
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
    const requestUrl = "/auction-controller?ajax=true&url_action=get_ffl_dealers&postal_code=" +
        encodeURIComponent((empty($postalCode.val()) ? "" : $postalCode.val())) + "&preferred_only=" + preferredOnly + "&have_license_only=" + haveLicenseOnly +
        (empty($("#ffl_dealer_filter").val()) ? "" : "&search_text=" + encodeURIComponent($("#ffl_dealer_filter").val())) +
        "&billing_postal_code=" + encodeURIComponent(empty($billingPostalCode.val()) ? "" : $billingPostalCode.val()) +
        "&radius=" + (empty($fflRadius.val()) ? "25" : $fflRadius.val());
    loadAjaxRequest(requestUrl, function(returnArray) {
        $fflDealers.find("li").remove();
        let someRestricted = false;
        $restrictedDealers.addClass("hidden");
        if ("ffl_dealers" in returnArray) {
            fflDealers = [];
            for (let i in returnArray['ffl_dealers']) {
                if (!empty(returnArray['ffl_dealers'][i]['restricted'])) {
                    someRestricted = true;
                }
                fflDealers[returnArray['ffl_dealers'][i]['federal_firearms_licensee_id']] = returnArray['ffl_dealers'][i]['display_name'];
                $fflDealers.append("<li title='Click to select' class='ffl-dealer" + (empty(returnArray['ffl_dealers'][i]['preferred']) ? "" : " preferred") + (empty(returnArray['ffl_dealers'][i]['restricted']) ? "" : " restricted") +
                    (empty(returnArray['ffl_dealers'][i]['have_license']) ? "" : " have-license") + "' data-federal_firearms_licensee_id='" + returnArray['ffl_dealers'][i]['federal_firearms_licensee_id'] + "'>" +
                    returnArray['ffl_dealers'][i]['display_name'] + "</li>");
            }
            if (someRestricted) {
                $restrictedDealers.removeClass("hidden");
            }
            $("#ffl_dealer_count").html(returnArray['ffl_dealers'].length);
        }
        if ($fflDealers.find("li").length === 0 && $fflRadius.length > 0 && parseInt($fflRadius.val()) < 100) {
            $fflRadius.find('option:selected').next().prop('selected', true);
            setTimeout(function () {
                getFFLDealers();
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
    $("#selected_filters").append(`<div class='selected-filter primary-selected-filter' data-field_name='${ fieldName }'>
        ${ filterLabel }: <span class='filter-text-value'>${ filterText }</span>
        <span class='far fa-times-circle'></span>
    </div>`);
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

function searchAuctionCatalog() {
    let valuesFound = false;
    const $searchAuctionForm = $("#_search_auction_form");
    $searchAuctionForm.find("input").each(function () {
        if (empty($(this).val())) {
            $(this).addClass("input-remove");
        } else {
            valuesFound = true;
        }
    });
    if (valuesFound) {
        $searchAuctionForm.find(".input-remove").remove();
        $searchAuctionForm.attr("method", "GET").attr("action", "/auction-search-results").submit();
    } else {
        $searchAuctionForm.find(".input-remove").removeClass("input-remove");
    }
    return false;
}

var filterProductSearchTimer = null;

function getFilterProductSearchParameters($productSearchForm) {
    let postFields = {};
    if (!empty($productSearchForm.find(".product-category-id").val())) {
        postFields['product_category_id'] = $productSearchForm.find(".product-category-id").val();
    }
    let specificationValues = "";
    $productSearchForm.find(".specification-value").each(function () {
        if (!empty($(this).val())) {
            specificationValues += (empty(specificationValues) ? "" : "|") + $(this).val();
        }
    });
    if (!empty(specificationValues)) {
        postFields['specifications'] = specificationValues;
    }
    if (!empty($productSearchForm.find(".product-tag-id").val())) {
        postFields['product_tag_id'] = $productSearchForm.find(".product-tag-id").val();
    }
    if (!empty($productSearchForm.find(".sale-price").val())) {
        const salePriceParts = $productSearchForm.find(".sale-price").val().split("-");
        postFields['minimum_price'] = salePriceParts[0];
        postFields['maximum_price'] = salePriceParts[1];
    }
    if (!empty($productSearchForm.find(".search-text").val())) {
        postFields['search_text'] = $productSearchForm.find(".search-text").val();
    }
    if (empty(postFields)) {
        return postFields;
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
    loadAjaxRequest("/auction-controller?ajax=true&url_action=get_auction_items", postFields, function(returnArray) {
        if ("result_count" in returnArray) {
            $productSearchForm.find(".product-search-result-searching-wrapper").addClass("hidden");
            $productSearchForm.find(".product-search-result-count-wrapper").removeClass("hidden");
            $productSearchForm.find(".product-search-result-count").html(returnArray['result_count']);
        }
        if ("reductive_data" in returnArray) {
            let thisValue = "";
            for (var i in returnArray['reductive_data']) {
                switch (i) {
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
                        if (i.substr(0, "specification-".length) != "specification-") {
                            break;
                        }
                        const specification = i.replace("specification-", "").toUpperCase();
                        if ($fieldChanged.hasClass("specification-value") && $fieldChanged.data("specification") == specification) {
                            break;
                        }
                        let $productFacetField = false;
                        $productSearchForm.find(".specification-value").each(function () {
                            if ($(this).data("specification") === specification) {
                                $specificationField = $(this);
                                return false;
                            }
                        });
                        if ($specificationField === false) {
                            break;
                        }
                        thisValue = $specificationField.val();
                        $specificationField.find("option").unwrap("span");
                        $specificationField.find("option[value!='']").each(function () {
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
    $(document).on("click tap", ".add-to-watchlist", function(event) {
        event.stopPropagation()
        let auctionItemId = $(this).closest('.auction-item').data('auction_item_id');
        if (empty(auctionItemId)) {
            auctionItemId = $('#auction_item_id').val();
        }
        addItemToWatchList(auctionItemId);
        return false;
    });

    $(document).on("click", ".product-search-page-module-department-button", function () {
        const formWrapperId = $(this).attr("id") + "_form_wrapper";
        $(this).closest(".product-search-page-module-wrapper").find(".product-search-page-module-form-wrapper").addClass("hidden");
        $(this).closest(".product-search-page-module-wrapper").find(".product-search-page-module-department-button").removeClass("selected");
        $("#" + formWrapperId).removeClass("hidden");
        $(this).addClass("selected");
    });
    $(document).on("click", ".product-search-clear-form", function () {
        $(this).closest("form").clearForm();
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
        $productSearchForm = $(this).closest("form");
        filterProductSearchTimer = setTimeout(function () {
            filterProductSearchPageModule($productSearchForm);
        }, 500);
    });
    $(document).on("change", ".product-search-page-module", function () {
        if (!empty(filterProductSearchTimer)) {
            clearTimeout(filterProductSearchTimer);
        }
        $productSearchForm = $(this).closest("form");
        const $fieldChanged = $(this);
        filterProductSearchTimer = setTimeout(function () {
            filterProductSearchPageModule($productSearchForm, $fieldChanged);
        }, 500);
    });
    $(document).on("click", ".product-search-view-results", function () {
        $productSearchForm = $(this).closest("form");
        let postFields = getFilterProductSearchParameters($productSearchForm);
        let parameters = "no_location=true";
        for (var i in postFields) {
            parameters += (empty(parameters) ? "" : "&") + i + "=" + encodeURIComponent(postFields[i]);
        }
        window.open("/product-search-results?" + parameters);
        return false;
    });

    $(document).on("click", ".result-display-type", function () {
        if ($(this).hasClass("selected")) {
            return;
        }
        const resultDisplayType = $(this).data("result_display_type");
        $.cookie("result_display_type", resultDisplayType, { expires: 365, path: '/' });
        $(".result-display-type").removeClass("selected");
        $(this).addClass("selected");
        $("#_auction_result").remove();
        displaySearchResults()
    });

    $(window).resize(function () {
        if ($(window).width() < 1050 && $("#result_display_type_list").hasClass("selected")) {
            $("#result_display_type_tile").trigger("click");
        }
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

    $(document).on("change", "#auction_item_sort_order", function () {
        let sortOrder = $("#auction_item_sort_order").val();
        $.cookie("auction_item_sort_order", sortOrder, { expires: 365, path: '/' });
        $("#_search_results_wrapper").html("<h3 class='align-center'>Loading results</h3>");
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

    $(document).on("click", ".auction-item-thumbnail", function (event) {
        $(this).parent("div").find("a.auction-detail-link").trigger("click");
    });

    $(document).on("click", "a.auction-detail-link", function (event) {
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

    $(document).on("click", ".click-auction-detail", function (event) {
        const auctionItemId = $(this).closest(".auction-item").data("auction_item_id");
        if (empty(auctionItemId)) {
            return;
        }
        if (event.metaKey || event.ctrlKey || !empty($(this).data("separate_window"))) {
            let detailLink = $(this).parent(".auction-item").find(".auction-detail-link").val();
            if (empty(detailLink)) {
                detailLink = "/auction-item-details?id=" + auctionItemId;
            }
            window.open(detailLink, "_blank");
            return;
        }
        if ($("#_search_result_details_wrapper").length == 0) {
            let detailLink = $(this).parent(".auction-item").find(".auction-detail-link").val();
            if (empty(detailLink)) {
                detailLink = "/auction-item-details?id=" + auctionItemId;
            }
            document.location = detailLink;
            return;
        }
        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
        if (auctionItemId in savedAuctionItemDetails) {
            displayAuctionDetails(savedAuctionItemDetails[auctionItemId]);
        } else {
            loadAjaxRequest("/auction-controller?ajax=true&url_action=get_full_auction_details&auction_item_id=" + auctionItemId, function(returnArray) {
                if ("content" in returnArray) {
                    savedAuctionItemDetails[auctionItemId] = returnArray;
                    displayAuctionDetails(returnArray);
                }
            });
        }
        return false;
    });

    // $(document).on("contextmenu",".auction-item", function (event) {
    //     event.preventDefault();
    //     $(".right-click-menu").data("target_element",$(this).attr("id"));
    //     $(".right-click-menu").finish().toggle(100).css({
    //         top: event.pageY + "px",
    //         left: event.pageX + "px"
    //     });
    // });

    // $(document).bind("mousedown", function (event) {
    //     if (!$(event.target).parents(".right-click-menu").length > 0) {
    //         $(".right-click-menu").hide(100);
    //     }
    // });
    //
    // $(document).on("click",".right-click-menu li",function(){
    //     switch($(this).attr("data-action")) {
    //         case "open":
    //             const targetElement = $(".right-click-menu").data("target_element");
    //             if (empty(targetElement) || $("#" + targetElement).length == 0 || $("#" + targetElement).find(".auction-detail-link").length == 0) {
    //                 break;
    //             }
    //             const auctionDetailLink = $("#" + targetElement).find(".auction-detail-link").val();
    //             if (empty(auctionDetailLink)) {
    //                 break;
    //             }
    //             window.open(auctionDetailLink);
    //             break;
    //     }
    //
    //     $(".right-click-menu").hide(100);
    // });
    //
    // if ($(".right-click-menu").length == 0) {
    //     $("body").append("<ul class='right-click-menu'><li data-action = \"open\">Open details in new tab</li></ul>");
    // }
});

function getCatalogResultTemplate(afterFunction) {
    if ($("#_auction_result").length > 0) {
        return;
    }
    if ($(window).width() < 1050) {
        $.cookie("result_display_type", "tile", { expires: 365, path: '/' });
        $(".result-display-type").removeClass("selected");
        $("#result_display_type_tile").addClass("selected");
    }
    $("body").addClass("no-waiting-for-ajax");
    loadAjaxRequest("/auction-controller?ajax=true&url_action=get_auction_result_html&force_tile=" + ($(window).width() < 1050 ? "true" : ""), function(returnArray) {
        if ("auction_result_html" in returnArray) {
            $("#_templates").append(returnArray['auction_result_html']);
            if (!empty(afterFunction)) {
                setTimeout(function () {
                    afterFunction();
                }, 100);
            }
        }
    });
}

function displayAuctionDetails(returnArray) {
    windowScroll = $(window).scrollTop();
    $("#_search_result_details_wrapper").html("<div id='_search_result_close_details_wrapper'><span class='fas fa-times'></span></div>" + returnArray['content']).removeClass("hidden").siblings().addClass("hidden");
    $("#_search_result_details_wrapper").find("a[href^='http']").not("a[rel^='prettyPhoto']").not(".same-page").attr("target", "_blank");
    if (!("adminLoggedIn" in window) || !adminLoggedIn) {
        $("#_search_result_details_wrapper").find(".admin-logged-in").remove();
    }
    if (!("userLoggedIn" in window) || !userLoggedIn) {
        $("#_search_result_details_wrapper").find(".user-logged-in").remove();
    }
    $(window).scrollTop(0);
    history.pushState("", document.title, returnArray['link_url']);
    if (detailIntervalTimer !== false) {
        clearInterval(detailIntervalTimer);
    }
    detailIntervalTimer = setInterval(function () {
        if (window.location.hash != "#auction_item_detail") {
            $("#_search_result_details_wrapper").html("").addClass("hidden").siblings().removeClass("hidden");
            setTimeout(function () {
                $(window).scrollTop(windowScroll);
            }, 100);
            clearInterval(detailIntervalTimer);
        }
    }, 100);
    if (typeof afterDisplayAuctionDetails == "function") {
        setTimeout(function () {
            afterDisplayAuctionDetails()
        }, 100);
    }
}
