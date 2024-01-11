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

$GLOBALS['gPageCode'] = "GUNBROKERLISTINGTEMPLATEMAINT";
require_once "shared/startup.inc";

class GunbrokerListingTemplateMaintenancePage extends Page {

    function massageDataSource() {

        $this->iDataSource->addColumnControl("title_color", "data_type", "select");
        $this->iDataSource->addColumnControl("title_color", "choices", array("Red"=>"Red","Green"=>"Green","Blue"=>"Blue"));

        $this->iDataSource->addColumnControl("header_content", "classes", "ck-editor");
        $this->iDataSource->addColumnControl("header_content", "help_label", "Content added to the beginning of the description");
        $this->iDataSource->addColumnControl("footer_content", "classes", "ck-editor");
        $this->iDataSource->addColumnControl("footer_content", "help_label", "Content added to the end of the description");

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

	    $this->iDataSource->addColumnControl("auto_relist_fixed_count", "minimum_value", "1");
        $this->iDataSource->addColumnControl("starting_bid", "classes", "auction-field");

        $this->iDataSource->addColumnControl("can_offer", "classes", "fixed-field");
        $this->iDataSource->addColumnControl("quantity", "classes", "fixed-field");

        $this->iDataSource->addColumnControl("starting_bid", "form_line_classes", "form-line-auction-field");

        $this->iDataSource->addColumnControl("can_offer", "form_line_classes", "form-line-fixed-field");
        $this->iDataSource->addColumnControl("quantity", "form_line_classes", "form-line-fixed-field");

        $valuesArray = Page::getPagePreferences();
        foreach ($valuesArray as $thisField => $thisValue) {
            if (!empty($thisValue)) {
                $this->iDataSource->addColumnControl($thisField, "default_value", $thisValue);
            }
        }
    }

    function onLoadJavascript() {
        ?>
        <script>
            $("#listing_type,#listing_duration").change(function() {
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
            $("#listing_type,#auto_relist").change(function() {
                if ($("#listing_type").val() == "fixed") {
                    if ($("#auto_relist").val() == "2") {
                        displayErrorMessage("Fixed Priced Listings cannot Relist until sold");
                        $("#auto_relist").val("1");
                    }
                }
            });
            $("#listing_type,#auto_relist").change(function () {
                filterFields();
            });
        </script>
        <?php
    }

    function javascript() {
        ?>
        <script>
            function afterGetRecord() {
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
}

$pageObject = new GunbrokerListingTemplateMaintenancePage("gunbroker_listing_templates");
$pageObject->displayPage();
