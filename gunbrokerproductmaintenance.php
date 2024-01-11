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

$GLOBALS['gPageCode'] = "GUNBROKERPRODUCTMAINT";
require_once "shared/startup.inc";

class GunbrokerProductMaintenancePage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_listing_template_data":
				$resultSet = executeQuery("select * from gunbroker_listing_templates where gunbroker_listing_template_id = ? and client_id = ?", $_GET['gunbroker_listing_template_id'], $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$unsetFields = array("gunbroker_listing_template_id", "client_id", "description", "sort_order", "internal_use_only", "inactive", "version");
					foreach ($unsetFields as $fieldName) {
						unset($row[$fieldName]);
					}
					$returnArray = $row;
				}
				ajaxResponse($returnArray);
				break;
			case "get_product_data":
				$row = ProductCatalog::getCachedProductRow($_GET['product_id']);
				$returnArray['description'] = substr($row['description'], 0, 75);
				$returnArray['detailed_description'] = $row['detailed_description'];
				$productCatalog = new ProductCatalog();
				$salePriceInfo = $productCatalog->getProductSalePrice($row['product_id'], array("product_information" => $row, "no_stored_prices" => true, "contact_type_id" => "", "user_type_id" => ""));
				$returnArray['sale_price'] = $salePriceInfo['sale_price'];
				$returnArray['upc_code'] = $row['upc_code'];
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("description", "data_size", "75");
		$this->iDataSource->addColumnControl("detailed_description", "classes", "ck-editor");
		$this->iDataSource->addColumnControl("upc_code", "readonly", true);
		$this->iDataSource->addColumnControl("upc_code", "data_type", "varchar");
		$this->iDataSource->addColumnControl("upc_code", "data_size", "40");
		$this->iDataSource->addColumnControl("upc_code", "form_label", "UPC");
        $this->iDataSource->addColumnControl("upc_code", "select_value", "select upc_code from product_data where product_id = gunbroker_products.product_id");

		$this->iDataSource->addColumnControl("header_content", "help_label", "Content added to the beginning of the Detailed Description");
		$this->iDataSource->addColumnControl("footer_content", "help_label", "Content added to the end of the Detailed Description");
		$this->iDataSource->addColumnControl("header_content", "classes", "ck-editor");
		$this->iDataSource->addColumnControl("footer_content", "classes", "ck-editor");

		$this->iDataSource->addColumnControl("gunbroker_listing_template_id", "data_type", "select");
		$this->iDataSource->addColumnControl("gunbroker_listing_template_id", "form_label", "Use Listing Template");
		$this->iDataSource->addColumnControl("gunbroker_listing_template_id", "not_null", false);
		$this->iDataSource->addColumnControl("gunbroker_listing_template_id", "choices", $GLOBALS['gPrimaryDatabase']->getControlRecords(array("table_name" => "gunbroker_listing_templates")));

		$this->iDataSource->addColumnControl("title_color", "data_type", "select");
		$this->iDataSource->addColumnControl("title_color", "choices", array("Red" => "Red", "Green" => "Green", "Blue" => "Blue"));
		$this->iDataSource->addColumnControl("scheduled_start_date_part", "data_type", "date");
		$this->iDataSource->addColumnControl("scheduled_start_date_part", "form_label", "Scheduled Starting Date");
		$this->iDataSource->addColumnControl("scheduled_start_time_part", "data_type", "time");
		$this->iDataSource->addColumnControl("scheduled_start_time_part", "form_label", "Scheduled Starting Time");

		$this->iDataSource->addColumnControl("listing_type", "data_type", "select");
		$this->iDataSource->addColumnControl("listing_type", "form_label", "Listing Type");
		$this->iDataSource->addColumnControl("listing_type", "no_empty_option", true);
		$this->iDataSource->addColumnControl("listing_type", "choices", array("fixed" => "Fixed Price", "auction" => "Auction"));
		$this->iDataSource->addColumnControl("listing_type", "default_value", "fixed");

		$this->iDataSource->addColumnControl("auto_relist", "data_type", "select");
		$this->iDataSource->addColumnControl("auto_relist", "choices", array("1" => "Do Not Relist", "2" => "Relist Until Sold", "3" => "Relist Fixed Count", "4" => "Relist Fixed Price"));
		$this->iDataSource->addColumnControl("auto_relist", "no_empty_option", true);
		$this->iDataSource->addColumnControl("auto_relist", "default_value", "2");
		$this->iDataSource->addColumnControl("can_offer", "form_label", "Customer can make an offer on this product");

		$gunBrokerCategories = getCachedData("gunbroker_category_choices", "all", true);
		if (empty($gunBrokerCategories)) {
			try {
				$gunBroker = new GunBroker();
				$rawCategories = $gunBroker->getCategories();
				if ($rawCategories === false) {
					$gunBrokerCategories = array();
				} else {
					$gunBrokerCategories = array();
					foreach ($rawCategories as $thisData) {
						$gunBrokerCategories[] = array("key_value" => $thisData['category_id'], "raw_description" => $thisData['description'], "description" => $thisData['description'] . " (ID " . $thisData['category_id'] . ")");
					}
					setCachedData("gunbroker_category_choices", "all", $gunBrokerCategories, 24, true);
				}
			} catch (Exception $exception) {
				$gunBrokerCategories = array();
			}
		}
		$this->iDataSource->addColumnControl("category_identifier", "data_type", "select");
		$this->iDataSource->addColumnControl("category_identifier", "choices", $gunBrokerCategories);
		$this->iDataSource->addColumnControl("category_identifier", "form_label", "GunBroker Category");
		$this->iDataSource->addColumnControl("category_identifier", "help_label", "If no categories are available, your GunBroker credentials are wrong");
		$this->iDataSource->addColumnControl("category_identifier", "not_null", true);

		$this->iDataSource->addColumnControl("ground_shipping_cost", "not_null", true);
		$this->iDataSource->addColumnControl("ground_shipping_cost", "minimum_value", "0");

		$this->iDataSource->addColumnControl("item_condition", "data_type", "select");
		$this->iDataSource->addColumnControl("item_condition", "choices", array("1" => "Factory New", "2" => "New Old Stock", "3" => "Used"));
		$this->iDataSource->addColumnControl("item_condition", "default_value", "1");
		$this->iDataSource->addColumnControl("item_condition", "no_empty_option", true);

		$this->iDataSource->addColumnControl("inspection_period", "data_type", "select");
		$this->iDataSource->addColumnControl("inspection_period", "choices", array("1" => "AS IS - No refund or exchange",
			"2" => "No refund but item can be returned for exchange or store credit within fourteen days",
			"3" => "No refund but item can be returned for exchange or store credit within thirty days",
			"4" => "Three Days from the date the item is received",
			"5" => "Three Days from the date the item is received, including the cost of shipping",
			"6" => "Five Days from the date the item is received",
			"7" => "Five Days from the date the item is received, including the cost of shipping",
			"8" => "Seven Days from the date the item is received",
			"9" => "Seven Days from the date the item is received, including the cost of shipping",
			"10" => "Fourteen Days from the date the item is received",
			"11" => "Fourteen Days from the date the item is received, including the cost of shipping",
			"12" => "30 day money back guarantee",
			"13" => "30 day money back guarantee including the cost of shipping",
			"14" => "Factory Warranty"));
		$this->iDataSource->addColumnControl("inspection_period", "default_value", "1");
		$this->iDataSource->addColumnControl("inspection_period", "no_empty_option", true);

		$this->iDataSource->addColumnControl("listing_duration", "data_type", "select");
		$this->iDataSource->addColumnControl("listing_duration", "choices", array("1" => "One day", "3" => "Three days", "5" => "Five days",
			"7" => "Seven days", "9" => "Nine days", "10" => "Ten days", "11" => "Eleven days", "12" => "Twelve days", "13" => "Thirteen days", "14" => "Fourteen days",
			"30" => "Thirty days. (Fixed price items only)", "60" => "Sixty days. (Fixed price items only)", "90" => "Ninety days. (Fixed price items only)"));
		$this->iDataSource->addColumnControl("listing_duration", "default_value", "1");
		$this->iDataSource->addColumnControl("listing_duration", "no_empty_option", true);
		$this->iDataSource->addColumnControl("listing_duration", "help_label", "Please note for 1 and 3 day listings, a Reserve Price is not allowed");

		$this->iDataSource->addColumnControl("weight_unit", "data_type", "select");
		$this->iDataSource->addColumnControl("weight_unit", "choices", array("1" => "Pounds", "2" => "Kilograms"));
		$this->iDataSource->addColumnControl("weight_unit", "default_value", "1");
		$this->iDataSource->addColumnControl("weight_unit", "no_empty_option", true);

		$this->iDataSource->addColumnControl("who_pays_for_shipping", "data_type", "select");
		$this->iDataSource->addColumnControl("who_pays_for_shipping", "choices", array("1" => "See item description", "2" => "Seller pays for shipping", "4" => "Buyer pays actual shipping cost", "8" => "Buyer pays fixed amount", "16" => "Use shipping profile"));
		$this->iDataSource->addColumnControl("who_pays_for_shipping", "default_value", "8");
		$this->iDataSource->addColumnControl("who_pays_for_shipping", "no_empty_option", true);

		$this->iDataSource->addColumnControl("standard_text_identifier", "data_type", "int");
		$this->iDataSource->addColumnControl("standard_text_identifier", "minimum_value", 1);
		$this->iDataSource->addColumnControl("standard_text_identifier", "form_label", "Standard Text ID");

		$this->iDataSource->addColumnControl("shipping_profile_identifier", "data_type", "int");
		$this->iDataSource->addColumnControl("shipping_profile_identifier", "minimum_value", 1);
		$this->iDataSource->addColumnControl("shipping_profile_identifier", "form_label", "Shipping Profile ID");

		$this->iDataSource->addColumnControl("date_sent", "readonly", true);
		$this->iDataSource->addColumnControl("gunbroker_identifier", "readonly", true);
		$this->iDataSource->addColumnControl("gunbroker_identifier", "form_label", "Gunbroker Listing ID");

		$this->iDataSource->addColumnControl("auto_accept_price", "minimum_value", "0");
		$this->iDataSource->addColumnControl("auto_reject_price", "minimum_value", "0");
		$this->iDataSource->addColumnControl("auto_accept_price", "data-conditional-required", '$("#can_offer").prop("checked")');
		$this->iDataSource->addColumnControl("auto_reject_price", "data-conditional-required", '$("#can_offer").prop("checked")');
		$this->iDataSource->addColumnControl("auto_relist_fixed_count", "minimum_value", "1");
		$this->iDataSource->addColumnControl("buy_now_price", "minimum_value", "0");
		$this->iDataSource->addColumnControl("fixed_price", "data-conditional-required", '$("#listing_type").val() == "fixed"');
		$this->iDataSource->addColumnControl("starting_bid", "data-conditional-required", '$("#listing_type").val() == "auction"');

		$this->iDataSource->addColumnControl("buy_now_price", "classes", "auction-field");
		$this->iDataSource->addColumnControl("reserve_price", "classes", "auction-field");
		$this->iDataSource->addColumnControl("starting_bid", "classes", "auction-field");

		$this->iDataSource->addColumnControl("auto_accept_price", "classes", "fixed-field");
		$this->iDataSource->addColumnControl("auto_reject_price", "classes", "auction-field");
		$this->iDataSource->addColumnControl("can_offer", "classes", "fixed-field");
		$this->iDataSource->addColumnControl("fixed_price", "classes", "fixed-field");
		$this->iDataSource->addColumnControl("quantity", "classes", "fixed-field");

		$this->iDataSource->addColumnControl("buy_now_price", "form_line_classes", "form-line-auction-field");
		$this->iDataSource->addColumnControl("reserve_price", "form_line_classes", "form-line-auction-field");
		$this->iDataSource->addColumnControl("starting_bid", "form_line_classes", "form-line-auction-field");

		$this->iDataSource->addColumnControl("auto_accept_price", "form_line_classes", "form-line-fixed-field");
		$this->iDataSource->addColumnControl("auto_reject_price", "form_line_classes", "form-line-fixed-field");
		$this->iDataSource->addColumnControl("can_offer", "form_line_classes", "form-line-fixed-field");
		$this->iDataSource->addColumnControl("fixed_price", "form_line_classes", "form-line-fixed-field");
		$this->iDataSource->addColumnControl("quantity", "form_line_classes", "form-line-fixed-field");

		$this->iDataSource->addFilterWhere("product_id in (select product_id from products where client_id = " . $GLOBALS['gClientId'] . ")");

		$valuesArray = Page::getPagePreferences();
		foreach ($valuesArray as $thisField => $thisValue) {
			if (!empty($thisValue)) {
				$this->iDataSource->addColumnControl($thisField, "default_value", $thisValue);
			}
		}
	}

    function filterTextProcessing($filterText) {
        if (is_numeric($filterText) && strlen($filterText) >= 8) {
            $whereStatement = "product_id = '" . $filterText . "'" .
                " or product_id in (select product_id from products where product_code = '" . ProductCatalog::makeValidUPC($filterText) . "')" .
                " or product_id in (select product_id from product_data where upc_code = '" . ProductCatalog::makeValidUPC($filterText) . "')";
            $this->iDataSource->addFilterWhere($whereStatement);
        } else {
            if (!empty($filterText)) {
                $productId = getFieldFromId("product_id", "products", "description", $filterText);
                if (empty($productId)) {
                    $searchWordInfo = ProductCatalog::getSearchWords($filterText);
                    $searchWords = $searchWordInfo['search_words'];
                    $whereStatement = "";
                    foreach ($searchWords as $thisWord) {
                        $whereStatement .= (empty($whereStatement) ? "" : " and ") .
                            "product_id in (select product_id from product_search_word_values where product_search_word_id in " .
                            "(select product_search_word_id from product_search_words where client_id = " . $GLOBALS['gClientId'] . " and search_term = " . makeParameter($thisWord) . "))";
                    }
                    $whereStatement = "(product_id in (select product_id from products where product_code = " . makeParameter($filterText) . ")" .
                        " or description like " . makeParameter($filterText . "%") .
                        " or product_id in (select product_id from distributor_product_codes where product_code = " . makeParameter($filterText) . ")" .
                        (empty($whereStatement) ? ")" : " or (" . $whereStatement . "))");
                    $whereStatement = "(" . (is_numeric($filterText) ? "product_id = " . makeParameter($filterText) . " or " : "") . "(" . $whereStatement . "))";
                    $this->iDataSource->addFilterWhere($whereStatement);
                } else {
                    $this->iDataSource->addFilterWhere("description like " . makeParameter($filterText . "%"));
                }
            }
        }
    }

    function onLoadJavascript() {
		?>
        <script>
            $("#gunbroker_listing_template_id").change(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_listing_template_data&gunbroker_listing_template_id=" + $(this).val(), function (returnArray) {
                    for (var i in returnArray) {
                        if ($("#" + i).is("input[type=checkbox]")) {
                            $("#" + i).prop("checked", !empty(returnArray[i]));
                        } else {
                            $("#" + i).val(returnArray[i]);
                            if ($("#" + i).hasClass("ck-editor")) {
                                CKEDITOR.instances[i].setData(returnArray[i]);
                            }
                        }
                    }
                    for (var i in returnArray) {
                        if ($("#" + i).length > 0) {
                            $("#" + i).trigger("change");
                        }
                    }
                });
            })
            $("#listing_type,#listing_duration").change(function () {
                if ($("#listing_type").val() == "auction") {
                    if ($("#listing_duration").val() >= 30) {
                        displayErrorMessage("Auction Listings can't be over 14 days");
                        $("#listing_duration").val("14");
                    }
                } else {
                    if ($("#listing_duration").val() < 30) {
                        displayErrorMessage("Fixed Priced Listings must be 30, 60 or 90 days");
                        $("#listing_duration").val("30");
                    }
                }
            });
            $("#listing_type,#auto_relist").change(function () {
                if ($("#listing_type").val() == "fixed") {
                    if ($("#auto_relist").val() == "2") {
                        displayErrorMessage("Fixed Priced Listings cannot Relist until sold");
                        $("#auto_relist").val("1");
                    }
                }
            });
            $("#buy_now_price").change(function () {
                if ($(this).val().length === 0) {
                    return;
                }
                const thisValue = parseFloat($(this).val().replace(",", "").replace(",", ""));
                if (!empty($("#reserve_price").val())) {
                    const reservePrice = parseFloat($("#reserve_price").val().replace(",", "").replace(",", ""));
                    if (thisValue < reservePrice) {
                        $(this).val("");
                        displayErrorMessage("Buy Now price must be greater than or equal reserve price");
                    }
                }
                if (!empty($("#starting_bid").val())) {
                    const startingBid = parseFloat($("#reserve_price").val().replace(",", "").replace(",", ""));
                    if (thisValue < startingBid) {
                        $(this).val("");
                        displayErrorMessage("Buy Now price must be greater than or equal starting bid");
                    }
                }
            });
            $("#reserve_price").change(function () {
                if ($(this).val().length === 0) {
                    return;
                }
                if ($("#listing_duration").val() === "1" || $("#listing_duration").val() === "3") {
                    displayErrorMessage("Reserve price not allowed with listing duration of 1 or 3 days");
                    $(this).val("");
                    return;
                }
                const thisValue = parseFloat($(this).val().replace(",", "").replace(",", ""));
                if (!empty($("#buy_now_price").val())) {
                    const buyNowPrice = parseFloat($("#buy_now_price").val().replace(",", "").replace(",", ""));
                    if (thisValue > buyNowPrice) {
                        $(this).val("");
                        displayErrorMessage("Reserve price must be less than or equal to the Buy Now price");
                    }
                }
            });
            $("#starting_bid").change(function () {
                if ($(this).val().length === 0) {
                    return;
                }
                const thisValue = parseFloat($(this).val().replace(",", "").replace(",", ""));
                if (!empty($("#buy_now_price").val())) {
                    const buyNowPrice = parseFloat($("#buy_now_price").val().replace(",", "").replace(",", ""));
                    if (thisValue > buyNowPrice) {
                        $(this).val("");
                        displayErrorMessage("Starting Bid must be less than or equal to the Buy Now price");
                    }
                }
            });
            $("#listing_type,#auto_relist").change(function () {
                filterFields();
            });
            $("#product_id").change(function () {
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_product_data&product_id=" + $(this).val(), function (returnArray) {
                        if ("description" in returnArray) {
                            $("#description").val(returnArray['description']);
                            CKEDITOR.instances["detailed_description"].setData(returnArray['detailed_description']);
                            $("#upc_code").val(returnArray['upc_code']);
                            if ($("#listing_type").val() === "fixed") {
                                $("#fixed_price").val(returnArray['sale_price']);
                            }
                        }
                    });
                }
            });
            $("#upc_code").click(function () {
               window.open("/products?clear_filter=true&url_page=show&primary_id=" + $("#product_id").val());
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
                if (!empty($("#primary_id").val())) {
                    $("#_gunbroker_listing_template_id_row").remove();
                }
                filterFields();
            }

            function filterFields() {
                const listingType = $("#listing_type").val();
                if (listingType === "fixed") {
                    $(".auction-field").val("");
                    $(".form-line-auction-field").addClass("hidden");
                    $(".form-line-fixed-field").removeClass("hidden");
                } else {
                    $(".fixed-field").val("");
                    $(".form-line-auction-field").removeClass("hidden");
                    $(".form-line-fixed-field").addClass("hidden");
                    $("#quantity").val("1");
                }
                if ($("#auto_relist").val() === "3") {
                    $("#_auto_relist_fixed_count_row").removeClass("hidden");
                } else {
                    $("#_auto_relist_fixed_count_row").addClass("hidden");
                }
            }
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['listing_type'] = array("data_value" => (empty($returnArray['fixed_price']['data_value']) ? "auction" : "fixed"));
		$returnArray['scheduled_start_time_part'] = array("data_value" => (empty($returnArray['scheduled_starting_date']['data_value']) ? "" : date("g:i a", strtotime($returnArray['scheduled_starting_date']['data_value']))), "crc_value" => getCrcValue((empty($returnArray['scheduled_starting_date']['data_value']) ? "" : date("g:i a", strtotime($returnArray['scheduled_starting_date']['data_value'])))));
		$returnArray['scheduled_start_date_part'] = array("data_value" => (empty($returnArray['scheduled_starting_date']['data_value']) ? "" : date("m/d/Y", strtotime($returnArray['scheduled_starting_date']['data_value']))), "crc_value" => getCrcValue((empty($returnArray['scheduled_starting_date']['data_value']) ? "" : date("m/d/Y", strtotime($returnArray['scheduled_starting_date']['data_value'])))));
		$returnArray['upc_code'] = array("data_value" => getFieldFromId("upc_code", "product_data", "product_id", $returnArray['product_id']['data_value']));
	}

	function beforeSaveChanges(&$nameValues) {
		$listingType = (empty($nameValues['fixed_price']) ? "auction" : "fixed");
		if ($listingType == "auction") {
			$nameValues['quantity'] = 1;
		}
		if (empty($nameValues['scheduled_start_date_part'])) {
			$nameValues['scheduled_starting_date'] = "";
		} else {
			if (empty($nameValues['scheduled_start_time_part'])) {
				$nameValues['scheduled_start_time_part'] = "00:00";
			}
			$nameValues['scheduled_starting_date'] = $nameValues['scheduled_start_date_part'] . " " . $nameValues['scheduled_start_time_part'];
		}
		return true;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$fields = array("listing_type", "auto_relist", "auto_relist_fixed_count", "can_offer", "item_condition", "inspection_period", "listing_duration", "prop_65_warning", "starting_bid", "who_pays_for_shipping", "will_ship_international");
		$valuesArray = Page::getPagePreferences();
		foreach ($fields as $thisField) {
			$valuesArray[$thisField] = $nameValues[$thisField];
		}
		Page::setPagePreferences($valuesArray);
		return true;
	}
}

$pageObject = new GunbrokerProductMaintenancePage("gunbroker_products");
$pageObject->displayPage();
