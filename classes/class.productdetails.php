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

/**
 * class ProductDetails
 *
 * Generate code for displaying product details
 * HTML for product details and all information
 * inline Javascript - related products information
 * onload Javascript - various handlers
 * javascript - functions
 * CSS - for the div section
 * hidden elements - create user dialog, make offer dialog, write review dialog
 *
 * @author Kim D Geiger
 */
class ProductDetails {

	var $iProductId = "";
	var $iProductRow = array();
	var $iProductDistributorId = "";
	var $iApi360UserName = false;
	var $iApi360Key = false;
	var $iCredovaUsername = false;
	var $iDefaultLocationRow = false;
	var $iEnableProductQuestions = false;

	function __construct($productId) {
		$this->iProductId = $productId;
		$this->getOutfitter360Image();
		$credovaCredentials = getCredovaCredentials();
		$this->iCredovaUsername = $credovaCredentials['username'];
		if (!empty(getPreference("ENABLE_PRODUCT_QUESTIONS"))) {
			$this->iEnableProductQuestions = true;
		}

		if ($GLOBALS['gLoggedIn']) {
			$defaultLocationId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID");
		} else {
			$defaultLocationId = $_COOKIE['default_location_id'];
		}
		if (!empty($defaultLocationId)) {
			$this->iDefaultLocationRow = getReadRowFromId("locations", "location_id", $defaultLocationId, "inactive = 0 and internal_use_only = 0 and warehouse_location = 0 and location_id in (select location_id from shipping_methods where inactive = 0 and internal_use_only = 0 and pickup = 1)");
			$defaultLocationId = $this->iDefaultLocationRow['location_id'];
		}
		if (empty($defaultLocationId)) {
			$this->iDefaultLocationRow = getCachedData("client_default_location", "");
			if ($this->iDefaultLocationRow === false) {
				$resultSet = executeReadQuery("select * from locations where client_id = ? and internal_use_only = 0 and inactive = 0 and product_distributor_id is null and warehouse_location = 0 order by sort_order", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$this->iDefaultLocationRow = $row;
				} else {
					$this->iDefaultLocationRow = array();
				}
				setCachedData("client_default_location", "", $this->iDefaultLocationRow);
			}
		}
	}

	private function getOutfitter360Image() {
		if (!isWebCrawler()) {
			$outfitterDone = getCachedData("sports_south_outfitter_done", $this->iProductId);
			if ($outfitterDone) {
				$resultSet = executeReadQuery("select * from locations join location_credentials using (location_id) where locations.inactive = 0 and client_id = ? and product_distributor_id is not null and " .
					"product_distributor_id = (select product_distributor_id from product_distributors where product_distributor_code = 'SPORTSSOUTH') order by locations.sort_order", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$this->iProductDistributorId = $row['product_distributor_id'];
					$this->iApi360UserName = CustomField::getCustomFieldData($row['location_credential_id'], "360_IMAGE_USER_ID", "PRODUCT_DISTRIBUTORS");
					$this->iApi360Key = CustomField::getCustomFieldData($row['location_credential_id'], "360_IMAGE_KEY", "PRODUCT_DISTRIBUTORS");
				}
				if ($GLOBALS['gSystemName'] == "NEO") {
					$row = array("user_name" => "17748");
					$this->iApi360UserName = "11984870680";
					$this->iApi360Key = "I45pr8xu4GqDBhx8gDoCPtvcxA8Yrbva";
				}
			} else {
				setCachedData("sports_south_outfitter_done", $this->iProductId, true, 24);
				if ($GLOBALS['gSystemName'] == "NEO") {
					$row = array("user_name" => "17748");
					$this->iApi360UserName = "11984870680";
					$this->iApi360Key = "I45pr8xu4GqDBhx8gDoCPtvcxA8Yrbva";
				} else {
					if ($GLOBALS['gClientRow']['client_code'] == "COREWARE_SHOOTING_SPORTS") {
						$resultSet = executeReadQuery("select * from locations join location_credentials using (location_id) where locations.inactive = 0 and location_credentials.inactive = 0 and client_id = ? and product_distributor_id is not null and " .
							"product_distributor_id = (select product_distributor_id from product_distributors where product_distributor_code = 'SPORTSSOUTH') order by locations.sort_order", $GLOBALS['gClientId']);
						$row = getNextRow($resultSet);
					} else {
						$row = false;
					}
				}
				if ($row) {
					$this->iProductDistributorId = $row['product_distributor_id'];
					if (empty($this->iApi360UserName)) {
						$this->iApi360UserName = CustomField::getCustomFieldData($row['location_credential_id'], "360_IMAGE_USER_ID", "PRODUCT_DISTRIBUTORS");
						$this->iApi360Key = CustomField::getCustomFieldData($row['location_credential_id'], "360_IMAGE_KEY", "PRODUCT_DISTRIBUTORS");
					}

					$distributorProductCode = getReadFieldFromId("product_code", "distributor_product_codes", "product_distributor_id", $row['product_distributor_id'], "product_id = ?", $this->iProductId);
					if (empty($distributorProductCode) && $GLOBALS['gSystemName'] == "NEO") {
						$distributorProductCode = getReadFieldFromId("product_code", "products", "product_id", $this->iProductId);
						if (!is_numeric($distributorProductCode)) {
							$distributorProductCode = "";
						}
					}
					if (!empty($distributorProductCode)) {
						$productDistributorId = "";
						if (empty($outfitterArray)) {

							$outfitterUrl = "https://api.sportssouth.dev/request/data/outfitter/get_outfitter_items.php?account_number=" . $row['user_name'] . "&item_number=" . $distributorProductCode;

							$siteContent = getCurlReturn($outfitterUrl);
							if (!empty($siteContent)) {
								$productDistributorId = $row['product_distributor_id'];
								$outfitterArray = json_decode($siteContent, true);
							}

						}
						if (is_array($outfitterArray) && array_key_exists("OUTFITTER_ITEMS", $outfitterArray)) {
							$outfitterRelatedProductTypeGroupId = getReadFieldFromId("related_product_type_group_id", "related_product_type_groups", "related_product_type_group_code", "OUTFITTER");
							if (empty($outfitterRelatedProductTypeGroupId)) {
								$insertSet = executeQuery("insert into related_product_type_groups (client_id,related_product_type_group_code,description) values (?,?,?)",
									$GLOBALS['gClientId'], 'OUTFITTER', 'Outfitter');
								$outfitterRelatedProductTypeGroupId = $insertSet['insert_id'];
							}
							$relatedProductTypes = array();
							$typeSet = executeQuery("select * from related_product_types left outer join related_product_type_links using (related_product_type_id) where client_id = ? and " .
								"(related_product_type_group_id = ? or related_product_type_group_id is null)", $GLOBALS['gClientId'], $outfitterRelatedProductTypeGroupId);
							while ($typeRow = getNextRow($typeSet)) {
								$relatedProductTypes[$typeRow['related_product_type_code']] = $typeRow;
							}
							foreach ($outfitterArray['OUTFITTER_ITEMS'] as $description => $information) {
								$relatedProductTypeCode = makeCode($description);
								if (!array_key_exists($relatedProductTypeCode, $relatedProductTypes)) {
									$insertSet = executeQuery("insert into related_product_types (client_id,related_product_type_code,description) values (?,?,?)",
										$GLOBALS['gClientId'], $relatedProductTypeCode, $description);
									$relatedProductTypes[$relatedProductTypeCode] = array("related_product_type_id" => $insertSet['insert_id']);
								}
								if (empty($relatedProductTypes[$relatedProductTypeCode]['related_product_type_link_id'])) {
									executeQuery("insert into related_product_type_links (related_product_type_id,related_product_type_group_id) values (?,?)",
										$relatedProductTypes[$relatedProductTypeCode]['related_product_type_id'], $outfitterRelatedProductTypeGroupId);
								}
							}
							$outfitterItems = $outfitterArray['OUTFITTER_ITEMS'];
							foreach ($outfitterItems as $relatedProductTypeDescription => $itemList) {
								$relatedProductTypeCode = makeCode($relatedProductTypeDescription);
								$relatedProductTypeId = $relatedProductTypes[$relatedProductTypeCode]['related_product_type_id'];
								if (empty($relatedProductTypeId)) {
									continue;
								}
								if (is_array($itemList)) {
									foreach ($itemList as $itemNumber => $itemInfo) {
										$productId = getReadFieldFromId("product_id", "distributor_product_codes", "product_distributor_id", $productDistributorId, "product_code = ?", $itemNumber);
										if (empty($productId) && $GLOBALS['gSystemName'] == "NEO") {
											$productId = getReadFieldFromId("product_id", "products", "product_code", $itemNumber);
										}
										if (empty($productId)) {
											continue;
										}
										$relatedProductId = getReadFieldFromId("related_product_id", "related_products", "product_id", $this->iProductId,
											"associated_product_id = ? and related_product_type_id = ?", $productId, $relatedProductTypeId);
										if (empty($relatedProductId)) {
											if ($this->iProductId != $productId) {
												executeQuery("insert ignore into related_products (product_id,associated_product_id,related_product_type_id,version) values (?,?,?,3)",
													$this->iProductId, $productId, $relatedProductTypeId);
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}
		$this->check360Image();
	}

	private function check360Image() {
		if (!empty($this->iApi360UserName) && !empty($this->iApi360Key)) {
			$productCode = getReadFieldFromId("product_code", "distributor_product_codes", "product_id", $this->iProductId, "product_distributor_id = ?", $this->iProductDistributorId);
			if (empty($productCode) && $GLOBALS['gSystemName'] == "NEO") {
				$productCode = getReadFieldFromId("product_code", "products", "product_id", $this->iProductId);
			}
			$curlHandle = curl_init("https://tsw-api.com/images/rotator/check.php?u=" . $this->iApi360UserName . "&k=" . $this->iApi360Key . "&i=" . $productCode);
			curl_setopt($curlHandle, CURLOPT_HEADER, 0);
			curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 15);
			curl_setopt($curlHandle, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
			$siteContent = curl_exec($curlHandle);
			curl_close($curlHandle);
			if (!empty($siteContent)) {
				$product360Array = json_decode($siteContent, true);
				if ($product360Array[$productCode] != "true") {
					$this->iApi360UserName = "";
					$this->iApi360Key = "";
				}
			}
		}
	}

	function getFullPage() {
		$fullProductContent = $this->internalCSS();
		$fullProductContent .= $this->getPageCSS();
		$fullProductContent .= $this->mainContent();
		$fullProductContent .= $this->hiddenElements();
		$fullProductContent .= $this->javascript();
		$fullProductContent .= $this->inlineJavascript();
		$fullProductContent .= $this->onLoadJavascript();
		return $fullProductContent;
	}

	function getPageTitle() {
		if (!empty($this->iProductId)) {
			$metaDescription = CustomField::getCustomFieldData($this->iProductId, "PAGE_TITLE", "PRODUCTS");
			if (empty($metaDescription)) {
				$metaDescription = getReadFieldFromId("description", "products", "product_id", $this->iProductId,
						"(product_manufacturer_id is null or product_manufacturer_id not in (select product_manufacturer_id from product_manufacturers where cannot_sell = 1)) and " .
						"inactive = 0 and internal_use_only = 0 and product_id not in (select product_id from product_category_links where product_category_id in " .
						"(select product_category_id from product_categories where cannot_sell = 1 or product_category_code in ('INACTIVE','INTERNAL_USE_ONLY'))) and product_id not in " .
						"(select product_id from product_tag_links where product_tag_id in (select product_tag_id from product_tags where cannot_sell = 1))") . " | " . $GLOBALS['gClientName'];
			}
			return $metaDescription;
		}
		return false;
	}

	function internalCSS() {
		ob_start();
		?>
		<style>
            .answer-wrapper {
                background-color: rgb(240, 240, 240);
                margin: 0 0 5px 40px;
                padding: 5px 10px;
                border-radius: 3px;
            }
            #_sample_questions_wrapper p {
                margin: 0;
            }
            .like-wrapper {
                display: inline-block;
                padding: 5px 20px;
                background-color: rgb(220, 220, 220);
                font-weight: 700;
                border-radius: 5px;
                margin: 5px 5px 5px 40px;
                cursor: pointer;
            }
            .review-response-content {
                background-color: rgb(240, 240, 240);
                margin-left: 40px;
                padding: 5px 10px;
            }
            #_shipping_options {
                margin: 20px 0;
                padding: 20px 0;
                border-top: 1px solid rgb(100, 100, 100);
                border-bottom: 1px solid rgb(100, 100, 100);
            }

            #_shipping_options div {
                margin-bottom: 20px;
            }

            #_product_details #_shipping_options p {
                margin: 0px;
                padding: 0px;
                text-indent: 40px;
            }

            #_product_details #_shipping_options p:first-child {
                text-indent: 0px;
                font-weight: 900;
            }

            #_shipping_options span.fad {
                width: 40px
            }

            #change_location_panel {
                width: 400px;
                max-width: 80%;
                position: fixed;
                height: 100vh;
                top: 0;
                right: 0;
                transition: all .5s linear;
                transform: translatex(100%);
                padding: 20px;
                background-color: rgb(255, 255, 255);
                z-index: 999999;
                border: 1px solid rgb(100, 100, 100);
                overflow: scroll;
            }

            #change_location_panel p {
                position: relative;
                margin: 0;
                padding: 0;
            }

            #change_location_panel.shown {
                transform: translatex(0);
            }

            #change_location_panel h2 {
                border-bottom: 1px solid rgb(100, 100, 100);
                font-size: 1.5rem;
                margin-bottom: 10px;
            }

            #close_change_location_panel {
                position: absolute;
                top: 10px;
                right: 10px;
                font-size: 1.2rem;
                cursor: pointer;
                color: rgb(200, 200, 200);
            }

            #change_location_postal_code {
                width: 100%;
                border: 1px solid rgb(100, 100, 100);
                padding: 5px 40px 5px 10px;
            }

            #search_change_location {
                position: absolute;
                top: 50%;
                right: 5px;
                font-size: 1.2rem;
                transform: translatey(-50%);
                cursor: pointer;
            }

            #search_change_location::placeholder {
                text-align: left;
            }

            #available_filter {
                position: relative;
                padding: 10px 0;
                border-bottom: 1px solid rgb(100, 100, 100);
            }

            #available_filter_line_1 {
                font-size: .9rem;
                font-weight: 400;
                color: rgb(100, 100, 100);
            }

            #available_filter_line_2 {
                font-size: .9rem;
                font-weight: 500;
                color: rgb(200, 200, 200);
            }

            #available_filter .fa-toggle-on {
                display: none;
                font-size: 2rem;
                position: absolute;
                top: 50%;
                transform: translateY(-50%);
                right: 0;
            }

            #available_filter .fa-toggle-off {
                display: block;
                font-size: 2rem;
                position: absolute;
                top: 50%;
                transform: translateY(-50%);
                right: 0;
            }

            #change_location_panel.only-available .fa-toggle-on {
                display: block;
            }

            #change_location_panel.only-available .fa-toggle-off {
                display: none;
            }

            #change_location_panel.only-available .pickup-not-available {
                display: none;
            }
            #no_stores_available_text {
                display: none;
            }

            .location-block {
                border-bottom: 1px solid rgb(100, 100, 100);
                padding: 15px 0;
                position: relative;
            }

            .location-block p {
                margin: 0;
                padding: 0;
                font-size: .8rem;
            }

            .location-block .location-block-distance {
                position: absolute;
                top: 15px;
                right: 0;
                text-align: right;
            }

            .location-block .location-block-inventory-count {
                color: rgb(60, 140, 45);
            }

            .location-block h3 {
                font-size: 1rem;
                font-weight: 900;
            }

            .location-block .store-information {
                font-size: .6rem;
                color: rgb(200, 200, 200);
            }

            .location-block .location-block-store-button {
                font-size: .7rem;
                padding: 3px 10px;
                border: 1px solid rgb(150, 150, 150);
                background-color: rgb(255, 255, 255);
                color: rgb(150, 150, 150);
                position: absolute;
                bottom: 10px;
                right: 0;
                font-weight: 400;
                text-transform: none;
            }

            .location-block .location-block-store-button:hover {
                background-color: rgb(150, 150, 150);
                color: rgb(255, 255, 255);
            }

            .location-block .location-block-store-button.default-store {
                background-color: rgb(150, 150, 150);
                color: rgb(255, 255, 255);
            }

            .location-block .location-block-store-button.default-store:hover {
                background-color: rgb(150, 150, 150);
                color: rgb(255, 255, 255);
            }

            .addon-image {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
            }

            .addon-image-inline {
                max-width: 200px;
                max-height: 200px;
                margin-bottom: 20px;
            }

            .embed-container {
                position: relative;
                padding-bottom: 56.25%;
                height: 0;
                overflow: hidden;
                height: auto;
                margin: 0 auto;
                margin-bottom: 20px;
            }

            .embed-container iframe, .embed-container object, .embed-container embed {
                position: absolute;
                width: 100%;
                height: 100%;
                top: 0;
                left: 0;
            }

            .product-video-icon {
                font-size: 3rem;
            }

            #_free_shipping {
                display: none;
            }

            #_product_details div#_free_shipping p {
                font-size: 1.2rem;
                color: rgb(0, 128, 0);
            }

            #_product_details div#_free_shipping p span {
                color: rgb(192, 0, 0);
            }

            #_product_details_wrapper.free-shipping #_free_shipping {
                display: block;
            }

            .product-image {
                cursor: zoom-in;
            }

            table#_related_product_types {
                width: 100%;
            }

            table#_related_product_types td {
                text-align: center;
                padding: 10px 20px;
                background-color: rgb(240, 240, 240);
                border: 1px solid rgb(180, 180, 180);
                color: rgb(0, 0, 0);
                font-size: 1.2rem;
                font-weight: 600;
            }

            td.related-products-selector {
                cursor: pointer;
            }

            table#_related_product_types td.selected {
                background-color: rgb(200, 200, 200);
            }

            div#related_products_filters {
                padding: 10px 20px;
                border: 1px solid rgb(180, 180, 180);
                position: relative;
                transform: translate(0px, -1px);
            }

            div.related-products-div {
                padding: 20px;
                border: 1px solid rgb(180, 180, 180);
                position: relative;
                transform: translate(0px, -2px);
                display: flex;
                flex-wrap: wrap;
            }

            #related_products_text_filter {
                font-size: 1rem;
                width: 300px;
                padding: 4px 10px;
                margin: 0 40px;
            }

            #sort_by_label {
                margin-left: 40px;
            }

            div#related_products_filters a {
                text-decoration: none;
                font-size: 1rem;
                margin-left: 20px;
            }

            div#related_products_filters a:hover {
                color: rgb(150, 150, 150);
            }

            .button-subtext {
                display: none;
            }

            .map-priced-product .button-subtext {
                display: inline;
                line-height: 8px;
            }

            .out-of-stock-product .button-subtext {
                display: inline;
            }

            .catalog-item {
                width: 280px;
                margin: 0 20px 20px 0;
                border: 1px solid rgb(200, 200, 200);
                padding: 20px;
                line-height: 1.2;
                background: #fff;
            }

            .catalog-item:hover {
                box-shadow: 0 1px 5px #aaa;
                border: 1px solid rgba(68, 68, 68, 0.62);
            }

            .catalog-item .info-label {
                font-size: 90%;
                margin-right: 10px;
            }

            .catalog-item img {
                max-width: 100%;
            }

            .click-product-detail {
                cursor: pointer;
            }

            .click-product-detail a:hover {
                color: rgb(140, 140, 140);
            }

            .catalog-item-description {
                font-size: 1.1rem;
                text-align: center;
                font-weight: 700;
                height: 110px;
                overflow: hidden;
                position: relative;
            }

            .catalog-item-description:after {
                content: "";
                position: absolute;
                top: 90px;
                left: 0;
                height: 20px;
                width: 100%;
                background: linear-gradient(rgba(255, 255, 255, 0), rgb(255, 255, 255));
            }

            .catalog-item-detailed-description {
                font-size: .8rem;
                margin-bottom: 10px;
                height: 100px;
                overflow: hidden;
                position: relative;
            }

            .catalog-item-detailed-description:after {
                content: "";
                position: absolute;
                top: 60px;
                left: 0;
                height: 40px;
                width: 100%;
                background: linear-gradient(rgba(255, 255, 255, 0), rgb(255, 255, 255));
            }

            .catalog-item-price-wrapper {
                font-size: 1.5rem;
                font-weight: 700;
                margin-bottom: 20px;
                margin-top: 10px;
                text-align: center;
            }

            .catalog-item-thumbnail {
                text-align: center;
                margin-bottom: 10px;
                height: 120px;
                position: relative;
            }

            .catalog-item-thumbnail img {
                max-height: 120px;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                cursor: zoom-in;
            }

            .catalog-item-out-of-stock {
                padding: 5px;
                text-align: center;
            }

            .catalog-item-add-to-cart {
                padding: 5px;
                text-align: center;
            }

            .catalog-item-add-to-wishlist {
                padding: 5px;
                text-align: center;
            }

            .catalog-item-out-of-stock {
                display: none;
            }

            .out-of-stock-product .catalog-item-out-of-stock {
                display: block;
            }

            .out-of-stock-product .catalog-item-add-to-cart {
                display: none;
            }

            span.fa-star {
                color: rgb(205, 160, 75);
                font-size: 1.8rem;
            }

            .review-stars {
                margin: 10px 0;
            }

            .review-title {
                font-weight: 900;
                margin-bottom: 10px;
            }

            #_still_image_wrapper {
                display: flex;
            }

            #_product_image_thumbnails {
                flex: 0 0 auto;
                width: 60px;
                margin-right: 10px;
            }

            #_product_image_thumbnails .image-thumbnail {
                max-width: 100%;
                margin-top: 10px;
                border: 1px solid rgb(50, 50, 50);
                cursor: pointer;
                opacity: .5;
                text-align: center;
            }

            #_product_image_thumbnails .image-thumbnail:hover {
                opacity: 1;
            }

            #_product_primary_image {
                flex: 1 1 auto;
                text-align: center;
                position: relative;
            }

            #_rotate_image_wrapper {
                padding: 10px;
                text-align: center;
            }

            #rotatorContainer {
                position: absolute;
                top: 50%;
                left: 50%;
                margin: -274px 0 0 -400px;
                background-color: #fff;
                z-index: 100001;
            }

            #rotatorExit {
                cursor: pointer;
                display: inline-block;
                opacity: 1;
                position: absolute;
                top: 3px;
                right: 3px;
            }

            #rotatorModal {
                background-color: rgba(0, 0, 0, .7);
                position: absolute;
                top: 0;
                width: 100%;
                height: 100%;
                z-index: 10000;
            }

            #_product_details_wrapper {
                display: flex;
                padding: 60px 0;
                background: #fff;
                max-width: 1800px;
                margin: auto;
            }

            #_product_details_wrapper > div {
                flex: 0 0 50%;
                padding: 0 50px;
            }

            #_product_details_wrapper .out-of-stock-message {
                display: none;
            }

            #_product_details_wrapper.out-of-stock-product .out-of-stock-message {
                display: block;
            }

            #_product_details p {
                color: #444444;
                margin: 0 0 10px 0;
                padding: 0;
            }

            #_product_review span {
                font-size: 1.4rem;
            }

            #_product_wrapper {
                max-width: 1800px;
                margin: auto;
            }

            #specifications_section p {
                margin: 0 0 10px 0;
                padding: 0;
                line-height: 1.2;
            }

            #specifications_table {
                border: 1px solid rgb(150, 150, 150);
            }

            #specifications_table td {
                border: 1px solid rgb(218, 216, 216);
                padding: 5px;
            }

            #specifications_table th {
                border: 1px solid rgb(218, 216, 216);
                padding: 5px;
                text-align: left;
                font-weight: 700;
            }

            #specifications_table tr:nth-child(even) {
                background-color: rgb(241, 241, 241);
            }

            #_restrictions p {
                margin: 0;
                padding: 0;
                font-size: .9rem;
                color: rgb(192, 0, 0);
            }

            #_tab_container {
                padding: 20px;
                background: #fff;
                margin: 30px 0;
                height: auto;
                width: 100%;
                background: none;
            }

            #_tab_container ul#_tab_nav {
                margin: 0;
                padding: 0;
                position: relative;
                width: 100%;
            }

            #_tab_container ul#_tab_nav li {
                list-style: none;
                display: inline-block;
                cursor: pointer;
                color: #000000;
                letter-spacing: 1px;
                font-size: 1.7em;
                padding: 5px 20px;
            }

            #_tab_container ul#_tab_nav li.active {
                background: #bfbfbf;
                color: #000000;
            }

            #_tab_container ul#_tab_nav li:hover {
                background: #a0a0a0;
                color: #000000;
            }

            #_tab_scroll_container {
                width: 100%;
                letter-spacing: 1px;
            }

            #_tab_scroll_container > div {
                border-top: 2px solid #bfbfbf;
                color: #444;
                font-size: 1.2em;
                line-height: 29px;
                padding: 18px 0;
                width: 100%;
            }

            .best-price-message {
                font-weight: 900;
                font-size: 1.0rem;
                font-family: 'Muli', sans-serif;
                color: #175128;
            }

            /***************Product Detail********************/
            #_product_manufacturer {
                font-size: 1.2em;
                font-weight: 800;
                text-transform: uppercase;
                color: #165225;
            }

            #_product_description {
                font-size: 1.2em;
                font-weight: 700;
            }

            #_original_sale_price_wrapper {
                margin: 10px 0;
                font-size: 1.4em;
                font-weight: 600;
                letter-spacing: 1px;
            }

            #_sale_price_wrapper {
                margin: 10px 0;
                font-size: 1.8em;
                font-weight: 700;
                letter-spacing: 1px;
            }

            #_list_price_wrapper {
                margin: 10px 0;
                font-size: 1.8em;
                font-weight: 700;
                letter-spacing: 1px;
            }

            #_available_stock span {
                margin-right: 10px;
            }

            #_available_stock {
                text-transform: uppercase;
                font-weight: 700;
                font-size: 1.0em;
            }

            #_secure_order span {
                margin-right: 10px;
                color: #175128;
            }

            #_secure_order {
                text-transform: uppercase;
                color: #175128;
                font-weight: 700;
                font-size: 1.0em;
            }

            #product_review {
                margin: 10px 0;
            }

            #quantity_wrapper input[type="text"] {
                border: 1px solid rgba(22, 81, 38, 0.56);
                border-radius: 0;
                margin: 20px 10px 20px 0;
                font-size: 1.2em;
                height: 40px;
                padding: 0;
                text-align: center;
                width: 100px;
            }

            button.add-to-cart, button.add-to-wishlist {
                min-width: 200px;
                width: 50%;
            }

            button.add-to-cart:hover, button.add-to-wishlist:hover {
                width: 50%;
            }

            button.add-to-cart.out-of-stock {
                display: none;
            }

            #_product_image_wrapper img {
                display: block;
                margin: auto;
                max-height: 500px;
                max-width: 100%;
            }

            .product-features-list {
                padding: 20px;
                list-style: disc;
            }

            /****************Reviews*************************/

            #reviews_section p {
                width: 90%;
            }

            #reviews_section a {
                margin: 5px 0;
                background: #00493C;
                color: #fff;
                border-radius: 0;
                text-transform: uppercase;
                font-family: 'Muli', sans-serif;
                font-weight: 700;
                border: 0;
                padding: 10px;
            }

            #reviews_section a:hover {
                color: #ffffff;
                background-color: #0d0d0d;
            }

            .review-wrapper {
                border-top: 1px solid #ccc;
                border-bottom: 1px solid #ccc;
                padding: 10px 0;
                margin: 20px 0;
            }

            /****************Questions*************************/

            #questions_section p {
                width: 90%;
            }

            #questions_section a {
                margin: 5px 0;
                background: #00493C;
                color: #fff;
                border-radius: 0;
                text-transform: uppercase;
                font-family: 'Muli', sans-serif;
                font-weight: 700;
                border: 0;
                padding: 10px;
            }

            #questions_section a:hover {
                color: #ffffff;
                background-color: #0d0d0d;
            }

            .question-wrapper {
                border-top: 1px solid #ccc;
                border-bottom: 1px solid #ccc;
                padding: 10px 0;
                margin: 20px 0;
            }

            /***************Catalog Item*********************/
            #_product_manufacturer_image {
                display: block;
            }

            #_product_manufacturer_image img {
                max-height: 100px;
                max-width: 100%;
            }

            p#quantity_discounts_title {
                font-weight: 700;
                font-size: 1rem;
            }

            #quantity_discount_table {
                border: 1px solid rgb(200, 200, 200);
            }

            #quantity_discount_table th {
                padding: 2px 10px;
                background-color: rgb(220, 220, 220);
                border: 1px solid rgb(200, 200, 200);
            }

            #quantity_discount_table td {
                padding: 2px 10px;
                border: 1px solid rgb(200, 200, 200);
            }

            span.star-rating {
                color: rgb(100, 100, 100);
                font-size: 2.5rem;
                margin-right: 5px;
            }

            span.star-rating.selected {
                color: rgb(205, 160, 75);
            }

            span.star-rating:hover {
                color: rgb(95, 140, 205);
            }

            #_star_rating_row {
                margin-bottom: 20px;
            }

            #star_label {
                height: 40px;
                font-weight: 900;
                color: rgb(150, 150, 150);
            }

            #content {
                width: 600px;
                max-width: 100%;
                height: 200px;
            }

            @media only screen and (max-width: 800px) {
                #_product_details_wrapper {
                    flex-direction: column;
                }

                #_product_image img {
                    transform: none;
                }

                button.add-to-cart, button.add-to-wishlist {
                    width: 100%;
                }
            }

            .product-detail-product-tag {
                display: inline-block;
                padding: 2px;
                margin-right: 4px;
                margin-bottom: 4px;
                color: rgb(255, 255, 255);
            }

            #_product_wrapper.addon-builder {

            #_still_image_wrapper {
                display: block;
            }

            #_product_details_wrapper {
                display: block;
            }

            #_product_image_wrapper #_product_primary_image img {
                width: 1000px;
                max-width: 100%;
                max-height: none;
                margin: 0;
            }

            #product_detail_product_tags {
                display: none;
            }

            #_restrictions {
                display: none;
            }

            #_location_availability {
                display: none;
            }

            }

			<?php
				$resultSet = executeReadQuery("select * from product_tags where client_id = ? and display_color is not null and " .
					"internal_use_only = 0 and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
            .product-detail-product-tag-<?= strtolower(str_replace("_","-",$row['product_tag_code'])) ?> {
                background-color: <?= $row['display_color'] ?>;
            }

			<?php
				}
			?>

            #related_product_videos_container {
                display: flex;
            }

            #related_product_videos_container iframe {
                width: 800px;
            }

            #related_product_videos_list {
                padding: 0 2rem;
                flex-shrink: 1;
            }

            #related_product_videos_list .related-product-video {
                display: flex;
                align-items: center;
                margin-bottom: 1rem;
                cursor: pointer;
            }

            #related_product_videos_list .related-product-video img {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }

            #related_product_videos_list .related-product-video:hover img {
                filter: brightness(50%);
            }

            #related_product_videos_list .related-product-video .fa {
                color: white;
                opacity: 0.8;
                font-size: 2.5rem;
                display: none;
            }

            #related_product_videos_list .related-product-video:hover .fa {
                display: block;
            }

            #related_product_videos_list .product-video-thumbnail {
                width: 25%;
                max-width: 25%;
                margin-right: 1rem;
                position: relative;
            }

            #related_product_video_iframe_container,
            #related_product_videos_container .product-video-content {
                flex-shrink: 1;
            }

            @media only screen and (max-width: 800px) {
                #related_product_videos_container {
                    display: block;
                }

                #related_product_videos_container iframe {
                    width: 100%;
                }

                #related_product_videos_list {
                    padding: 0;
                }
            }
		</style>
		<?php
		return ob_get_clean();
	}

	function getPageCSS() {
		$pageId = getReadFieldFromId("page_id", "pages", "script_filename", "retailstore/productdetails.php", "template_id = ?", $GLOBALS['gPageRow']['template_id']);
		if (empty($pageId)) {
			$pageId = getReadFieldFromId("page_id", "pages", "script_filename", "retailstore/productdetails.php", "domain_name = ?", $_SERVER['HTTP_HOST']);
		}
		if (empty($pageId)) {
			$pageId = getReadFieldFromId("page_id", "pages", "script_filename", "retailstore/productdetails.php");
		}
		$cssContent = getReadFieldFromId("css_content", "pages", "page_id", $pageId);
		if (!empty($cssContent)) {
			$sassContent = "";
			$resultSet = executeReadQuery("select content from sass_headers join template_sass_headers using (sass_header_id) where template_id = (select template_id from pages where page_id = ?)", $pageId);
			while ($row = getNextRow($resultSet)) {
				$sassContent .= "\r" . $row['content'];
			}
			$cssContent = $sassContent . $cssContent;
		}
		return "<style>" . processCssContent($cssContent) . "</style>";
	}

	function mainContent() {
		if (empty($this->iProductRow)) {
			$this->loadProductRow();
		}
		$productRow = $this->iProductRow;

		ob_start();
		$productDetails = "";
		$resultSet = executeReadQuery("select * from product_category_groups where fragment_id is not null and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (ProductCatalog::productIsInCategoryGroup($this->iProductId, $row['product_category_group_id'])) {
				$productDetails = getReadFieldFromId("content", "fragments", "fragment_id", $row['fragment_id'], "(client_id = ? or client_id = ?)", $GLOBALS['gDefaultClientId'], $GLOBALS['gClientId']);
				break;
			}
		}
		if (empty($productDetails)) {
			$resultSet = executeReadQuery("select * from product_departments where fragment_id is not null and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (ProductCatalog::productIsInDepartment($this->iProductId, $row['product_department_id'])) {
					$productDetails = getReadFieldFromId("content", "fragments", "fragment_id", $row['fragment_id'], "(client_id = ? or client_id = ?)", $GLOBALS['gDefaultClientId'], $GLOBALS['gClientId']);
					break;
				}
			}
		}
		if (empty($productDetails)) {
			$fragmentCode = getPageTextChunk("PRODUCT_DETAIL_HTML_FRAGMENT_CODE");
			if (!empty($fragmentCode)) {
				$productDetails = getFragment($fragmentCode, $productRow);
			}
		}
		if (empty($productDetails)) {
			$productDetails = getPageTextChunk("retail_store_product_details");
		}
		if (empty($productDetails)) {
			$productDetails = getFragment(makeCode(str_replace(".", "_", $_SERVER['HTTP_HOST']) . "_retail_store_product_details"), $productRow);
		}
		if (empty($productDetails) && $GLOBALS['gClientId'] != $GLOBALS['gDefaultClientId']) {
			$productDetails = getFragment("retail_store_product_details", $productRow);
		}
		if (empty($productDetails)) {
			$fragmentId = getPreference("PRODUCT_DETAIL_HTML_FRAGMENT_ID");
			if (!empty($fragmentId)) {
				$productDetails = getReadFieldFromId("content", "fragments", "fragment_id", $fragmentId, "client_id = ?", $GLOBALS['gDefaultClientId']);
			}
		}
		if (empty($productDetails)) {
			$productDetails = getFragment("retail_store_product_details", $productRow);
		}
		if (empty($productDetails)) {
			$productDetails = getReadFieldFromId("content", "fragments", "fragment_code", "RETAIL_STORE_PRODUCT_DETAILS", "client_id = ?", $GLOBALS['gDefaultClientId']);
		}
		if (empty($productDetails)) {
			ob_start();
			?>
			<div id="_product_wrapper">

				<div id="_product_details_wrapper" class="%product_classes%">
					<input type="hidden" id="product_id" value="%product_id%">
					<input type="hidden" id="product_code" value="%product_code%">

					<div id="_product_image_wrapper">
						<div id="_still_image_wrapper">
							<div id="_product_image_thumbnails">
								%product_image_thumbnails%
							</div>
							<div id="_product_primary_image">
								<a id="_product_image" href="%image_url%" class="pretty-photo" rel='prettyPhoto[all_images]' data-image_code=''>
									<img class="product-image" %image_src%="%image_url%" alt="%detailed_description_text%" title="%description%">
								</a>
							</div>
						</div>
						<div id="_rotate_image_wrapper">
							<?php
							$view360Content = getFragment("view_360_content");
							if (empty($view360Content)) {
								$view360Content = "View 360&deg; Image";
							}
							?>
							<a href="#" data-product_code="%product_code%" class="show-rotate-image"><?= $view360Content ?></a>
						</div>
					</div> <!-- _product_image_wrapper -->

					<div id="_product_details">
						<div id="_item_details">
							<a id="_product_manufacturer_image" href="%product_manufacturer_url%"><img alt='Logo Image' src="%logo_image_url%"></a>
							<p id="_product_manufacturer">%manufacturer_name%</p>
							<p id="_product_description">%description%</p>
							<p id="_product_code" class="product-data">Item : %product_code%</p>
							<p id="_manufacturer_sku" class="product-data">SKU : %manufacturer_sku%</p>
							<p id="_model" class="product-data">Model : %model%</p>
							<p id="_upc_code" class="product-data">UPC : %upc_code%</p>
							<div id="_product_category_codes" class="hidden">%product_category_codes%</div>
						</div> <!-- _item_details -->

						%product_tags%
						%product_variants%

						<div id="_pricing_wrapper">
							%price_intro_message%
							<p id="_original_sale_price_wrapper"><span id="original_sale_price" class='strikeout'>%original_sale_price%</span></p>
							<p id="_sale_price_wrapper"><span id="sale_price">%sale_price%</span></p>
							%best_price_message%
							<p id="_make_offer" class='%make_offer_class%'>
								<button id="_make_offer_button">Make Offer</button>
							</p>
							<div class="catalog-item-credova-financing">
								%credova_financing%
							</div>
							<p id="_available_stock" class="%availability_class%">%availability%</p>
							<p class='admin-logged-in' id='_quantity_on_hand'>Quantity on Hand: <span id="_inventory_count">%inventory_count%</span></p>
							<p id="_secure_order"><span class="fa fa-lock"></span>Secure Online Ordering</p>
							<p id="_product_review">%star_reviews% (%review_count% Review%review_count_plural%)</p>
						</div> <!-- _pricing_wrapper -->

						%additional_messages%

						%quantity_discount_table%

						<div id="_free_shipping">
							<p><span class='fad fa-shipping-fast'></span> Free Shipping!</p>
						</div>

						<div id="_restrictions">
							%product_restrictions%
						</div>

						<?php if (!empty(getPreference("product_detail_show_shipping_options"))) { ?>
							<div id="_shipping_options">
								<div id="_shipping_option_pickup_available">
									<p><span class="fad fa-store"></span>Available In-Store Now</p>
									<p>Free in-store pickup %pickup_available_when% at %default_location%</p>
									<p><a href="#" id="available_pickup_locations">See all available pickup locations</a></p>
								</div>
								<div id="_shipping_option_pickup_not_available">
									<p><span class="fad fa-ban"></span>NOT Available for pickup</p>
									<p>Unavailable at %default_location%</p>
									<p><a href="#" id="available_pickup_locations">See all available pickup locations</a></p>
								</div>
								<div id="_shipping_option_ship_to_store">
									<p><span class="fad fa-truck-fast"></span>Ship To My Store (%default_location%)</p>
									<p>Free in-store pickup (Est. 1-2 weeks)</p>
									<p><a href="#" id="change_location">Change My Location</a></p>
								</div>
								<div id="_shipping_option_ship_to_home">
									<p><span class="fad fa-house"></span>Ship To Home</p>
								</div>
								<div id="_shipping_option_ship_to_ffl">
									<p><span class="fad fa-truck-fast"></span>Ship To Another Dealer (FFL)</p>
									<p>Choose the dealer at checkout</p>
								</div>
							</div>
						<?php } else { ?>

							<div id="_location_availability">
								%location_availability%
							</div>

						<?php } ?>

						<div id="_product_addons">
							%product_addons%
						</div>

						<div id="_button_wrapper">
							<div id="quantity_wrapper"><input type="text" id="quantity" name="quantity" value="1"
							                                  class="validate[custom[number]]"
							                                  data-cart_maximum="%cart_maximum%"
							                                  data-cart_minimum="%cart_minimum%"><label for='quantity' id="quantity_label">QTY%cart_maximum_label%</label>
							</div>
							<p>
								<button class="add-to-cart add-to-cart-%product_id% %out_of_stock% %strict_map% %no_online_order%"
								        id="add_to_cart_button" data-text="%add_to_cart%" data-quantity_field="quantity" data-in_text="Item In Cart"
								        data-adding_text="Adding" data-product_id="%product_id%">%add_to_cart%
								</button>
							</p>
							<p>
								<button id="add_to_wishlist_button" class="add-to-wishlist add-to-wishlist-%product_id%"
								        data-product_id="%product_id%" data-text="Add to Wishlist"
								        data-in_text="Item In Wishlist" data-adding_text="Adding">%add_to_wishlist%
								</button>
							</p>
						</div> <!-- _button_wrapper -->

					</div> <!-- product_details -->

				</div> <!-- product_details_wrapper -->

				<div id="_tab_container">
					<ul id="_tab_nav">
						<li class="active" id="_specifications_tab" data-tab_name="specifications_section">Specifications</li>
						<li id="_reviews_tab" data-tab_name="reviews_section">Reviews</li>
						<?php if ($this->iEnableProductQuestions) { ?>
							<li id="_questions_tab" data-tab_name="questions_section">Q&A</li>
						<?php } ?>
					</ul>

					<div id="_tab_scroll_container">

						<div id="specifications_section" class="tab-section">
							%detailed_description%

							<table id="specifications_table">
								<tbody>
								%product_specifications%
								</tbody>
							</table> <!-- specifications_table -->

						</div> <!-- specifications_section -->

						<div id="reviews_section" class='hidden tab-section'>
							<p id="review_text">%review_count% review%review_count_plural% have been written for this product.</p>
							<p><a id="write_review" href="/product-review?product_id=%product_id%">Write a Review</a></p>
							<p>Sort by:
								<button class='sort-reviews selected' data-sort_order='rating' id='sort_reviews_by_rating'>Rating</button>
								<button class='sort-reviews' data-sort_order='like_count' id='sort_reviews_by_like_count'>Most Liked</button>
								<button class='sort-reviews selected' data-sort_order='date_created' id='sort_reviews_by_date_created'>Most Recent</button>
							</p>
							<div id="_sample_reviews_wrapper">
								%sample_reviews%
							</div>
						</div> <!-- reviews_section -->

						<?php if ($this->iEnableProductQuestions) { ?>
							<div id="questions_section" class='hidden tab-section'>
								<p id="question_text">%question_count% question%question_count_plural% have been answered for this product.</p>
								<p><a id="ask_question" href="#">Ask a question</a></p>
								<div id="_sample_questions_wrapper">
									%sample_questions%
								</div>
							</div> <!-- questions_section -->
						<?php } ?>

					</div> <!-- _tab_scroll_container -->

				</div> <!-- _tab_container -->

				<div id="related_videos_wrapper" class="hidden">
					<h2>Related videos</h2>
					%related_videos%
				</div> <!-- related_videos_wrapper -->

				<div id="related_products_wrapper" class="hidden">
					<h2>You might also like</h2>
					%related_products%
				</div> <!-- related_products_wrapper -->

			</div> <!-- _product_wrapper -->
			<?php
			$productDetails = ob_get_clean();
		}

		$defaultLocationId = $this->iDefaultLocationRow['location_id'];
		if (getPreference("USE_BUSINESS_NAME_AS_LOCATION_DESCRIPTION")) {
			$productRow['default_location'] = getFieldFromId("business_name", "contacts", "contact_id", getFieldFromId("contact_id", "locations", "location_id", $defaultLocationId));
		} else {
			$productRow['default_location'] = $this->iDefaultLocationRow['description'];
		}

		$defaultPickupCutoff = getPreference("DEFAULT_PICKUP_CUTOFF");
		if (empty($defaultPickupCutoff)) {
			$defaultPickupCutoff = 17;
		}
		if (date("H") > $defaultPickupCutoff) {
			$productRow['pickup_available_when'] = "tomorrow";
		} else {
			$productRow['pickup_available_when'] = "today";
		}

		$productDetails = str_replace("%image_src%", "src", $productDetails);
		$productDetails = massageFragmentContent($productDetails, $productRow);
		echo $productDetails;
		return ob_get_clean();
	}

	public function loadProductRow() {
		if (!empty($this->iProductRow)) {
			return;
		}

		$productRow = ProductCatalog::getCachedProductRow($this->iProductId);
		$relatedProductTypeIds = array();
		$relatedProductTypes = array();
		$resultSet = executeReadQuery("select * from related_product_types where client_id = ? and inactive = 0 and internal_use_only = 0 and (related_product_type_id not in " .
			"(select related_product_type_id from related_product_type_links) or related_product_type_id in (select related_product_type_id from related_product_type_links where " .
			"related_product_type_group_id in (select related_product_type_group_id from related_product_type_groups where inactive = 0 and internal_use_only = 0))) and " .
			"related_product_type_id in (select related_product_type_id from related_products where product_id = ? and exists (select product_id from products where " .
			"product_id = related_products.associated_product_id and inactive = 0)) order by sort_order,description", $GLOBALS['gClientId'], $this->iProductId);
		while ($row = getNextRow($resultSet)) {
			$relatedProductTypes[] = $row;
			$relatedProductTypeIds[] = $row['related_product_type_id'];
		}
		$relatedProductTypeGroups = array();
		$relatedProductTypeGroupIds = "";
		if (!empty($relatedProductTypeIds)) {
			$resultSet = executeReadQuery("select * from related_product_type_groups where client_id = ? and inactive = 0 and internal_use_only = 0 and " .
				"related_product_type_group_id in (select related_product_type_group_id from related_product_type_links where related_product_type_id in (" .
				implode(",", $relatedProductTypeIds) . ")) order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$types = array();
				$typeSet = executeReadQuery("select * from related_product_type_links where related_product_type_group_id = ?", $row['related_product_type_group_id']);
				while ($typeRow = getNextRow($typeSet)) {
					if (in_array($typeRow['related_product_type_id'], $relatedProductTypeIds) && !in_array($typeRow['related_product_type_id'], $types)) {
						$types[] = $typeRow['related_product_type_id'];
					}
				}
				$row['types'] = $types;
				$relatedProductTypeGroups[] = $row;
				$relatedProductTypeGroupIds .= (empty($relatedProductTypeGroupIds) ? "" : ",") . $row['related_product_type_group_id'];
			}
		}
		$relatedProductsTable = "";
		$relatedProductsDivs = "";
		$relatedProductsDivs .= "<div id='related_products_filters' class='hidden'><span id='related_products_count'></span><input type='text' id='related_products_text_filter' placeholder='Filter Results'><input type='checkbox' id='hide_related_out_of_stock'><label class='checkbox-label' for='hide_related_out_of_stock'>Hide Out of Stock</label><label id='sort_by_label'>Sort By:</label> <a href='#' id='sort_by_price_asc'>Price (Low-High)</a> <a href='#' id='sort_by_price_desc'>Price (High-Low)</a></div>";
		$relatedProductNumber = 0;
		ob_start();
		?>
		<table id="_related_product_types">
			<tr>
				<?php
				$relatedProductsTable .= ob_get_clean();
				if ($productRow['general_related_products_count'] > 0) {
					$relatedProductNumber++;
					$relatedProductsTable .= "<td rowspan='2' class='related-products-selector' data-related_div_number='" . $relatedProductNumber . "'>General Related Items</td>";
					$relatedProductsDivs .= "<div class='related-products-div hidden' id='related_products_" . $relatedProductNumber . "' data-related_product_type_code=''><h2>Loading</h2></div>";
				}

				foreach ($relatedProductTypes as $row) {
					if (!empty($relatedProductTypeGroupIds)) {
						$relatedProductTypeLinkId = getReadFieldFromId("related_product_type_link_id", "related_product_type_links", "related_product_type_id", $row['related_product_type_id'],
							"related_product_type_group_id in (" . $relatedProductTypeGroupIds . ")");
						if (!empty($relatedProductTypeLinkId)) {
							continue;
						}
					}
					$relatedProductNumber++;
					$relatedProductsTable .= "<td rowspan='2' class='related-products-selector' data-related_div_number='" . $relatedProductNumber . "'>" . htmlText($row['description']) . "</td>";
					$relatedProductsDivs .= "<div class='related-products-div hidden' id='related_products_" . $relatedProductNumber . "' data-related_product_type_code='" . $row['related_product_type_code'] . "'><h2>Loading</h2></div>";
				}

				foreach ($relatedProductTypeGroups as $row) {
					if (empty($row['types'])) {
						continue;
					}
					$relatedProductsTable .= "<td colspan='" . count($row['types']) . "'>" . htmlText($row['description']) . "</td>";
				}
				$relatedProductsTable .= "</tr><tr>";

				foreach ($relatedProductTypeGroups as $groupRow) {
					if (empty($groupRow['types'])) {
						continue;
					}
					foreach ($relatedProductTypes as $row) {
						if (!in_array($row['related_product_type_id'], $groupRow['types'])) {
							continue;
						}
						$relatedProductNumber++;
						$relatedProductsTable .= "<td class='related-products-selector' data-related_div_number='" . $relatedProductNumber . "'>" . htmlText($row['description']) . "</td>";
						$relatedProductsDivs .= "<div class='related-products-div hidden' id='related_products_" . $relatedProductNumber . "' data-related_product_type_code='" . $row['related_product_type_code'] . "'><h2>Loading</h2></div>";
					}
				}
				ob_start();
				?>
			</tr>
		</table>
		<?php
		$relatedProductsTable .= ob_get_clean();

		$neverOutOfStock = getPreference("RETAIL_STORE_NEVER_OUT_OF_STOCK");
		$productCatalog = new ProductCatalog();
		$removeFields = array("client_id", "base_cost", "version", "product_distributor_id");
		if (!empty($productRow)) {
			foreach ($removeFields as $fieldName) {
				unset($productRow[$fieldName]);
			}
			$productRow['product_url'] = getDomainName() . "/product/" . $productRow['link_name'];
			$productRow['description'] = str_replace("\n", "", $productRow['description']);
			$productRow['description_html'] = htmlspecialchars($productRow['description']);

			$productRow['location_availability'] = ProductCatalog::getProductAvailabilityText($productRow);

			$noRelatedProductsFound = getFragment("retail_store_no_related_products");
			if (empty($noRelatedProductsFound)) {
				$noRelatedProductsFound = "<p>No Related Products Found</p>";
			}
			$productRow['related_products'] = ($relatedProductNumber == 0 ? $noRelatedProductsFound : $relatedProductsTable . $relatedProductsDivs);
			$productRow['cart_maximum_label'] = "";
			if (empty($productRow['cart_minimum']) && !empty($productRow['cart_maximum']) && $productRow['cart_maximum'] > 0) {
				$productRow['cart_maximum_label'] = " (limit " . $productRow['cart_maximum'] . ")";
			} else {
				if (empty($productRow['cart_maximum']) && !empty($productRow['cart_minimum']) && $productRow['cart_minimum'] > 0) {
					$productRow['cart_maximum_label'] = " (min " . $productRow['cart_minimum'] . ")";
				} else {
					if (!empty($productRow['cart_maximum']) && !empty($productRow['cart_minimum'])) {
						$productRow['cart_maximum_label'] = " (min " . $productRow['cart_minimum'] . ", limit " . $productRow['cart_maximum'] . ")";
					}
				}
			}

			$inventoryCounts = $productCatalog->getInventoryCounts(false, array($productRow['product_id']));
			$productRow['inventory_count'] = $inventoryCounts[$productRow['product_id']]['total'];
			$productRow['inventory_details'] = $inventoryCounts[$productRow['product_id']];
			if (function_exists("_localMassageProductDetailsInventoryCounts")) {
				$massagedInventoryCounts = _localMassageProductDetailsInventoryCounts($productRow['product_id'], $inventoryCounts);
				if (is_array($massagedInventoryCounts)) {
					if (array_key_exists("totals", $massagedInventoryCounts)) {
						$productRow['inventory_count'] = $massagedInventoryCounts['totals'];
					}
					if (array_key_exists("details", $massagedInventoryCounts)) {
						$productRow['inventory_details'] = $massagedInventoryCounts['details'];
					}
				}
			}
			$localInventoryCount = 0;
			if (array_key_exists($productRow['product_id'], $inventoryCounts) && is_array($inventoryCounts[$productRow['product_id']])) {
				foreach ($inventoryCounts[$productRow['product_id']] as $thisLocationId => $thisInventoryCount) {
					if (!is_int($thisLocationId) || $thisInventoryCount <= 0) {
						continue;
					}
					$localInventoryCount++;
					$locationDescription = getReadFieldFromId("description", "locations", "location_id", $thisLocationId, "product_distributor_id is null and warehouse_location = 0");
					if (!empty($locationDescription)) {
						$productRow['local_inventory_description_' . $localInventoryCount] = $locationDescription;
						$productRow['local_inventory_count_' . $localInventoryCount] = $thisInventoryCount;
					}
				}
			}

			$productRow['product_contributors'] = "";
			$resultSet = executeReadQuery("select * from product_contributors join contributors using (contributor_id) join contributor_types using (contributor_type_id) where product_id = ? order by contributor_types.sort_order", $productRow['product_id']);
			while ($row = getNextRow($resultSet)) {
				$productRow['product_contributors'] .= (empty($productRow['product_contributors']) ? "" : "<br>") . "<span class='contributor-label'>" . $row['description'] . ":</span> <a href='/product-search-results?contributor_id=" .
					$row['contributor_id'] . "'>" . $row['full_name'] . "</a>";
			}
			$productRow['product_format'] = getReadFieldFromId("description", "product_formats", "product_format_id", $productRow['product_format_id']);
			$productRow['product_categories'] = "";
			$productRow['product_category_codes'] = "";
			$productRow['primary_category'] = "";
			$urlAliasTypeCode = getUrlAliasTypeCode("product_categories", "product_category_id");
			$resultSet = executeReadQuery("select * from product_categories where inactive = 0 and internal_use_only = 0 and client_id = ? and " .
				"product_category_id in (select product_category_id from product_category_links where product_id = ?) order by sort_order,description", $GLOBALS['gClientId'], $productRow['product_id']);
			$firstCategory = true;
			while ($row = getNextRow($resultSet)) {
				$linkUrl = (empty($urlAliasTypeCode) || empty($row['link_name']) ? "/product-search-results?product_category_id=" . $row['product_category_id'] : "/" . $urlAliasTypeCode . "/" . $row['link_name']);
				$productRow['product_categories'] .= (empty($productRow['product_categories']) ? "" : ", ") . "<a href='" . $linkUrl . "'>" . htmlText($row['description']) . "</a>";
				$productRow['product_category_codes'] .= (empty($productRow['product_category_codes']) ? "" : ",") . $row['product_category_code'];
				if ($firstCategory) {
					$productRow['primary_category'] = str_replace("'", "", $row['description']);
					$firstCategory = false;
				}
			}

			$mapEnforced = false;
			$salePriceInfo = $productCatalog->getProductSalePrice($productRow['product_id'], array("product_information" => $productRow, "no_cache" => true));
			$originalSalePrice = $salePriceInfo['original_sale_price'];
			$salePrice = $salePriceInfo['sale_price'];
			$mapEnforced = $salePriceInfo['map_enforced'];
			$callPrice = $salePriceInfo['call_price'];
            $calculationLog = $salePriceInfo['calculation_log'];
			if (empty($originalSalePrice)) {
				$originalSalePrice = $productRow['list_price'];
			}
			if (!empty($originalSalePrice) && ($originalSalePrice < $salePrice || (!empty($productRow['manufacturer_advertised_price']) && $productRow['manufacturer_advertised_price'] > $originalSalePrice))) {
				$originalSalePrice = "";
			}
			if (!empty($originalSalePrice) && $originalSalePrice <= $salePrice) {
				$originalSalePrice = "";
			}
			$productRow['quantity_discount_table'] = "";
			$pricingStructureId = $productCatalog->getProductSalePrice($productRow['product_id'], array("product_information" => $productRow, "return_pricing_structure_only" => true));
			if (!empty($pricingStructureId)) {
				$priceCalculationTypeCode = getReadFieldFromId("price_calculation_type_code", "price_calculation_types", "price_calculation_type_id",
					getReadFieldFromId("price_calculation_type_id", "pricing_structures", "pricing_structure_id", $pricingStructureId));
				if ($priceCalculationTypeCode == "DISCOUNT" && $productRow['list_price'] > 0) {
					$resultSet = executeReadQuery("select * from pricing_structure_quantity_discounts where user_type_id is null and contact_type_id is null and pricing_structure_id = ? and minimum_quantity > 1 order by minimum_quantity", $pricingStructureId);
					if ($resultSet['row_count'] > 0) {
						ob_start();
						?>
						<p id="quantity_discounts_title">Quantity Discounts</p>
						<table id="quantity_discount_table">
							<tr>
								<th>Min Qty</th>
								<th>Discount</th>
								<th>Price</th>
							</tr>
							<?php
							while ($row = getNextRow($resultSet)) {
								$quantityPriceInfo = $productCatalog->getProductSalePrice($productRow['product_id'], array("product_information" => $productRow, "quantity" => $row['minimum_quantity']));
								$quantityPrice = $quantityPriceInfo['sale_price'];
								?>
								<tr>
									<td class="align-right"><?= $row['minimum_quantity'] ?></td>
									<td class="align-right"><?= number_format($row['percentage'], 0, "", "") ?>%</td>
									<td class="align-right"><?= number_format($quantityPrice, 2, ".", ",") ?></td>
								</tr>
								<?php
							}
							?>
						</table>
						<?php
						$productRow['quantity_discount_table'] = ob_get_clean();
					}
				}
			}
			if (function_exists("_localGetManufacturerLogoUrl")) {
				$productRow['logo_image_url'] = _localGetManufacturerLogoUrl($productRow['product_manufacturer_id']);
			}
			if (empty($productRow['logo_image_url'])) {
				$logoImageId = getReadFieldFromId("image_id", "contacts", "contact_id", getReadFieldFromId("contact_id", "product_manufacturers", "product_manufacturer_id", $productRow['product_manufacturer_id']));
				$productRow['logo_image_url'] = getImageFilename($logoImageId, array("use_cdn" => true));
			}
			$productRow['product_manufacturer_url'] = getReadFieldFromId("web_page", "contacts", "contact_id", getReadFieldFromId("contact_id", "product_manufacturers", "product_manufacturer_id", $productRow['product_manufacturer_id']));
			$productRow['product_manufacturer_link_name'] = getReadFieldFromId("link_name", "product_manufacturers", "product_manufacturer_id", $productRow['product_manufacturer_id']);
			if (!empty($productRow['product_manufacturer_url']) && substr($productRow['product_manufacturer_url'], 0, 4) != "http") {
				$productRow['product_manufacturer_url'] = "http://" . $productRow['product_manufacturer_url'];
			}
			$mapPolicyId = getPreference("DEFAULT_MAP_POLICY_ID") ?: getReadFieldFromId("map_policy_id", "product_manufacturers", "product_manufacturer_id", $productRow['product_manufacturer_id']);
			$mapPolicyCode = getReadFieldFromId("map_policy_code", "map_policies", "map_policy_id", $mapPolicyId);
			$ignoreMap = ($mapPolicyCode == "IGNORE");
			if (!$ignoreMap) {
				$ignoreMap = CustomField::getCustomFieldData($productRow['product_id'], "IGNORE_MAP", "PRODUCTS");
			}
			$originalListPrice = $productRow['list_price'];
			if (!empty($productRow['list_price']) && $productRow['list_price'] > 0) {
				$productRow['list_price'] = "$" . number_format($productRow['list_price'], 2, ".", ",");
			}
			$productRow['original_sale_price'] = "";
			$productRow['list_price_discount'] = "";
			$callForPriceText = getFragment("CALL_FOR_PRICE");
			if (empty($callForPriceText)) {
				$callForPriceText = getLanguageText("Call for Price");
			}
			$inStorePurchaseOnlyText = getFragment("IN_STORE_PURCHASE_ONLY_TEXT");
			if (empty($inStorePurchaseOnlyText)) {
				$inStorePurchaseOnlyText = getLanguageText("In-store purchase only");
			}

			$showInStoreOnlyPrice = getPreference("SHOW_IN_STORE_ONLY_PRICE");
			if ($salePrice === false || ($productRow['no_online_order'] && empty($showInStoreOnlyPrice))) {
				$productRow['sale_price'] = $callForPriceText;
				$productRow['hide_dollar'] = $callForPriceText;
				$productRow['no_sale_price'] = true;
				$productRow['manufacturer_advertised_price'] = "";
                $productRow['calculation_log'] = $calculationLog . "\nNo price found; showing Call for Price text";
			} else {
				if ($salePrice > $productRow['manufacturer_advertised_price'] || $ignoreMap) {
					$productRow['manufacturer_advertised_price'] = "";
				}
				$productRow['sale_price'] = "$" . number_format($salePrice, 2, ".", ",");
				$productRow['original_sale_price'] = (empty($originalSalePrice) ? "" : "$" . number_format($originalSalePrice, 2, ".", ","));
				if (!empty($originalListPrice) && $originalListPrice > 0) {
					$listPriceDiscount = round(((($originalListPrice - $salePrice) / $originalListPrice) * 100), 0);
				} else {
					$listPriceDiscount = 0;
				}
				if ($listPriceDiscount > 0) {
					$productRow['list_price_discount'] = $listPriceDiscount;
				}
                $productRow['calculation_log'] = $calculationLog;
			}
			if (!empty($productRow['manufacturer_advertised_price'])) {
				$productRow['original_sale_price'] = "";
			}
			$productRow['strict_map'] = ($mapEnforced ? "strict-map" : "");
			$productRow['call_price'] = ($callPrice ? "call-price" : "");

			$productRow['price_intro_message'] = getFragment("retail_store_price_intro_message");
			$productRow['best_price_message'] = "";
			$productRow['product_classes'] = "";
			if (!empty($productRow['no_online_order'])) {
				$productRow['strict_map'] = "";
				$productRow['call_price'] = "";
				$productRow['add_to_cart'] = $inStorePurchaseOnlyText;
				$productRow['no_online_order'] = "no-online-order";
			} else {
				$productRow['no_online_order'] = "";
				$loginRequired = false;
				$noGuestCheckout = getPreference("RETAIL_STORE_NO_GUEST_CART");
				if (!empty($noGuestCheckout) && !$GLOBALS['gLoggedIn']) {
					$loginRequired = true;
				}
				if ($mapPolicyCode == "CART_PRICE") {
					$productRow['sale_price'] = "See price in cart";
					$productRow['add_to_cart'] = ($loginRequired ? "Login to purchase" : "Add to Cart");
				} else if (!$callPrice && !$mapEnforced && !empty($productRow['manufacturer_advertised_price']) && $productRow['manufacturer_advertised_price'] > $salePrice) {
					$productRow['sale_price'] = "$" . number_format($productRow['manufacturer_advertised_price'], 2, ".", ",");
					$productRow['best_price_message'] = ($loginRequired ? "Login to purchase" : "<p class='best-price-message'>Add to cart for best price</p>");
					$productRow['add_to_cart'] = ($loginRequired ? "Login to purchase" : "Add to Cart<span class='button-subtext'><br>for best price</span>");
					$productRow['product_classes'] .= " map-priced-product";
				} else {
					if ($mapEnforced) {
						$productRow['add_to_cart'] = getLanguageText($loginRequired ? "Login to purchase" : "Email for Price");
					} else {
						if ($callPrice) {
							$productRow['add_to_cart'] = getLanguageText($loginRequired ? "Login to purchase" : $callForPriceText);
						} else {
							$productRow['add_to_cart'] = getLanguageText($loginRequired ? "Login to purchase" : "Add to Cart");
						}
					}
				}
			}

			if (empty($_GET['shopping_cart_code'])) {
				$_GET['shopping_cart_code'] = "RETAIL";
			}
			$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
			$shoppingCartItemId = getReadFieldFromId("shopping_cart_item_id", "shopping_cart_items", "product_id", $productRow['product_id'], "shopping_cart_id = ?", $shoppingCart->getShoppingCartId());
			if (!empty($shoppingCartItemId)) {
				$productRow['add_to_cart'] = "Item In Cart";
			}

			if (!isWebCrawler()) {
				executeQuery("insert into product_view_log (product_id,contact_id,ip_address) values (?,?,?)", $productRow['product_id'], $shoppingCart->getContact(), $_SERVER['REMOTE_ADDR']);
			}

			$productRow['add_to_wishlist'] = "Add to Wishlist";
			$wishListProductIds = array();
			if ($GLOBALS['gLoggedIn']) {
				$wishList = new WishList();
				$wishListItems = $wishList->getWishListItems();
				foreach ($wishListItems as $thisItem) {
					$wishListProductIds[] = $thisItem['product_id'];
				}
			}
			if (in_array($productRow['product_id'], $wishListProductIds)) {
				$productRow['add_to_wishlist'] = "Item In Wishlist";
			}

			if ($productRow['list_price'] == $productRow['sale_price']) {
				$productRow['list_price'] = "";
			}
			$productRow['detailed_description'] = makeHtml($productRow['detailed_description']);
			$productManualFileId = CustomField::getCustomFieldData($productRow['product_id'], "PRODUCT_MANUAL", "PRODUCTS");
			if (empty($productManualFileId)) {
				$productRow['product_manual_link'] = "";
			} else {
				$productRow['product_manual_link'] = "/download.php?id=" . $productManualFileId;
			}
			if (empty(getPreference("RETAIL_STORE_DO_NOT_ADD_MANUFACTURER_DESCRIPTION_TO_PRODUCTS"))) {
				$manufacturerDetailedDescription = getReadFieldFromId("detailed_description", "product_manufacturers", "product_manufacturer_id", $productRow['product_manufacturer_id']);
				if (!empty($manufacturerDetailedDescription)) {
					$productRow['detailed_description'] .= makeHtml($manufacturerDetailedDescription);
				}
			}
			$productRow['detailed_description_text'] = htmlText(removeHtml($productRow['detailed_description']));
			$parameters = array();
			$missingProductImage = getImageFilenameFromCode("NO_PRODUCT_IMAGE");
			if (empty($missingProductImage) || $missingProductImage == "/images/empty.jpg") {
				$missingProductImage = getPreference("DEFAULT_PRODUCT_IMAGE");
			}
			if (empty($missingProductImage)) {
				$missingProductImage = "/images/no_product_image.jpg";
			}
			if (!empty($missingProductImage)) {
				$parameters['default_image'] = $missingProductImage;
			}

			$productRow['image_url'] = ProductCatalog::getProductImage($productRow['product_id'], $parameters);
			$thumbnailExists = false;
			foreach ($GLOBALS['gImageTypes'] as $imageTypeRow) {
				$parameters['image_type'] = strtolower($imageTypeRow['image_type_code']);
				if ($parameters['image_type'] == "thumbnail") {
					$thumbnailExists = true;
				}
				$productRow[strtolower($imageTypeRow['image_type_code']) . "_image_url"] = ProductCatalog::getProductImage($productRow['product_id'], $parameters);
			}
			$productRow['product_image_thumbnails'] = "";
			$productRow['product_images'] = "";
			$imageCount = 0;
			if (!empty($productRow['image_url']) && strpos($productRow['image_url'], $missingProductImage) === false) {
				$productRow['product_images'] = "<a class='product-image pretty-photo' id='product_image_" . $imageCount++ . "' href='" . $productRow["image_url"] . "'><img title='Product Image' src='" . $productRow["image_url"] . "'></a>";
			}
			$alternateImages = ProductCatalog::getProductAlternateImages($productRow['product_id'], array("include_code" => true));
			$alternateThumbnailImages = ProductCatalog::getProductAlternateImages($productRow['product_id'], array("image_type" => "thumbnail", "alternate_image_type" => "small", "include_code" => true));
			$thumbnailProductVideos = array();
			$playlistProductVideos = array();
			$resultSet = executeReadQuery("select *, product_videos.description product_video_description from product_videos join media using (media_id) where product_id = ?", $productRow['product_id']);
			while ($row = getNextRow($resultSet)) {
				if ($row['show_as_playlist']) {
					$playlistProductVideos[] = $row;
				} else {
					$thumbnailProductVideos[] = $row;
				}
			}

			if (count($alternateImages) > 0 || count($thumbnailProductVideos) > 0) {
				$thumbnailFragment = getFragment("RETAIL_STORE_ALTERNATE_IMAGE");
				if (empty($thumbnailFragment)) {
					$thumbnailFragment = "<div class='image-thumbnail' data-image_code='%image_code%' data-image_url='%image_url%'><a data-ignore_click='true' rel='prettyPhoto[all_images]' href='%image_url%'><img title='Product Image' src='%thumbnail_image_url%'></a></div>";
				}
				$productRow['product_image_thumbnails'] = str_replace("%image_code%", "", str_replace("%image_url%", $productRow['image_url'], str_replace("%thumbnail_image_url%", $productRow[($thumbnailExists ? "thumbnail_" : "small_") . "image_url"], $thumbnailFragment)));
				foreach ($alternateImages as $index => $thisImageUrl) {
					$productRow['product_image_thumbnails'] .= str_replace("%image_code%", $thisImageUrl['code'], str_replace("%image_url%", $thisImageUrl['url'], str_replace("%thumbnail_image_url%", $alternateThumbnailImages[$index]['url'], $thumbnailFragment)));
					$productRow['product_images'] .= "<a class='product-image pretty-photo' rel='prettyPhoto[all_images]' id='product_image_" . $imageCount++ . "' href='" . $thisImageUrl['url'] . "'><img title='Product Image' src='" . $thisImageUrl['url'] . "'></a>";
				}
				foreach ($thumbnailProductVideos as $thisVideo) {
					$mediaServicesRow = getReadRowFromId("media_services", "media_service_id", $thisVideo['media_service_id']);
					$videoLink = "//" . $mediaServicesRow['link_url'] . $thisVideo['video_identifier'];
					$productRow['product_image_thumbnails'] .= "<div class='image-thumbnail product-video' data-image_code='' data-video_link='" . $videoLink . "'>" .
						(empty($thisVideo['image_id']) ? "<span title='Product Video' class='fab fa-youtube product-video-icon'></span>" : "<img title='Product Video' src='" . getImageFilename($thisVideo['image_id'], array("use_cdn" => true, "image_type" => ($thumbnailExists ? "thumbnail" : "small"))) . "'>") . "</div>";
				}
			} else if (empty($productRow['image_url']) || strpos($productRow['image_url'], $missingProductImage) !== false) {
				$productRow['image_url'] = $missingProductImage;
				$productRow['product_images'] = "<a class='product-image pretty-photo' id='product_image_" . $imageCount++ . "' href='" . $missingProductImage . "'><img title='Product Image' src='" . $missingProductImage . "'></a>";
			}

			if (count($playlistProductVideos) > 0) {
				$productRow['related_videos'] = "<div id='related_product_videos_container'>
                    <div id='related_product_video_iframe_container'>
                        <iframe allow='autoplay; encrypted-media' allowfullscreen></iframe>
                        <h2 class='product-video-description'></h2>
                    </div>
                    <div id='related_product_videos_list'>";

				foreach ($playlistProductVideos as $thisVideo) {
					$mediaServicesRow = getReadRowFromId("media_services", "media_service_id", $thisVideo['media_service_id']);
					$productRow['related_videos'] .= "<div class='related-product-video'
                        data-video_identifier='" . $thisVideo['video_identifier'] . "'
                        data-media_services_link_url='" . $mediaServicesRow['link_url'] . "';
                        data-media_service_code='" . $mediaServicesRow['media_service_code'] . "'
                        data-description='" . $thisVideo['product_video_description'] . "'>
                        <div class='product-video-thumbnail'>
                            <img title='" . $thisVideo['product_video_description'] . "' src='" . getImageFilename($thisVideo['image_id'], array("use_cdn" => true)) . "' alt='" . $thisVideo['product_video_description'] . "' />
                            <i class='fa fa-play-circle center-div' aria-hidden='true'></i>
                        </div>
                        <div class='product-video-content'>
                            <h3><a class='product-video-link' href='" . $GLOBALS['gLinkUrl'] . "?type=media_id&amp;media_id=" . $thisVideo['media_id'] . "'>" . $thisVideo['product_video_description'] . "</a></h3>"
						. (empty($thisVideo['subtitle']) ? "" : "<p class='content-subtitle'>" . htmlText($thisVideo['subtitle']) . "</p>")
						. (empty($thisVideo['full_name']) ? "" : "<p class='content-author'>" . htmlText($thisVideo['full_name']) . "</p>")
						. (empty($thisVideo['detailed_description']) ? "" : "<p class='content-detailed-description'>" . htmlText($thisVideo['detailed_description']) . "</p>")
						. (empty($thisVideo['date_created']) ? "" : "<p class='content-date-created'>" . date("m/d/Y", strtotime($row['date_created'])) . "</p>")
						. "</div>
                    </div>";
				}
				$productRow['related_videos'] .= "</div></div>";
			}

			$specifications = array();
			if (!empty($productRow['product_manufacturer_id'])) {
				$specifications[] = array("field_name" => "specs_product_manufacturer", "field_description" => "Manufacturer", "field_value" => $productRow['manufacturer_name']);
			}
			if (!empty($productRow['product_type_id'])) {
				$specifications[] = array("field_name" => "specs_product_type", "field_description" => "Product Type", "field_value" => getReadFieldFromId("description", "product_types", "product_type_id", $productRow['product_type_id']));
			}
			$skipFields = array("product_data_id", "client_id", "product_id", "version", "product_distributor_id", "minimum_price", "manufacturer_advertised_price", "map_expiration_date");
			$tableId = getReadFieldFromId("table_id", "tables", "table_name", "product_data");
			$resultSet = executeQuery("select description, column_name from table_columns join column_definitions using (column_definition_id) where table_id = ?", $tableId);
			$fieldDescriptions = array();
			while ($row = getNextRow($resultSet)) {
				$fieldDescriptions[$row['column_name']] = $row['description'];
			}
			$resultSet = executeReadQuery("select * from product_data where product_id = ?", $this->iProductId);
			$dataTable = new DataTable("product_data");
			$foreignKeyList = $dataTable->getForeignKeyList();
			while ($row = getNextRow($resultSet)) {
				foreach ($row as $fieldName => $fieldValue) {
					if (in_array($fieldName, $skipFields) || empty($fieldValue) || $fieldValue == "N/A" || (is_numeric($fieldValue) && $fieldValue == 0)) {
						continue;
					}
					$fieldDescription = $fieldDescriptions[$fieldName];
					if (array_key_exists("product_data." . $fieldName, $foreignKeyList)) {
						$thisFieldValue = "";
						foreach ($foreignKeyList["product_data." . $fieldName]['description'] as $thisDescriptionField) {
							$descriptionFieldValue = getReadFieldFromId($thisDescriptionField, $foreignKeyList["product_data." . $fieldName]['referenced_table_name'],
								$foreignKeyList["product_data." . $fieldName]['referenced_column_name'], $fieldValue);
							$thisFieldValue .= $descriptionFieldValue;
						}
						$fieldValue = $thisFieldValue;
					}
					$specifications[] = array("field_name" => "product_data_" . $fieldName, "field_description" => $fieldDescription, "field_value" => $fieldValue);
				}
			}
			if (!isWebCrawler()) {
				$resultSet = executeReadQuery("select * from product_facet_values join product_facets using (product_facet_id) join product_facet_options using (product_facet_option_id) where product_id = ? and " .
					($GLOBALS['gInternalConnection'] ? "" : "internal_use_only = 0 and ") . "exclude_details = 0 and inactive = 0 order by sort_order,description", $this->iProductId);
				while ($row = getNextRow($resultSet)) {
					if (empty($row['facet_value']) || $row['facet_value'] == "N/A" || (is_numeric($row['facet_value']) && $row['facet_value'] == 0)) {
						continue;
					}
					$specifications[] = array("field_name" => "product_facets_" . strtolower($row['product_facet_code']), "field_description" => $row['description'], "field_value" => $row['facet_value']);
				}
			}
			$resultSet = executeReadQuery("select * from custom_fields where client_id = ? and custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'PRODUCTS') and " .
				"inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$customFieldData = CustomField::getCustomFieldData($productRow['product_id'], $row['custom_field_code'], "PRODUCTS");
				if (!empty($customFieldData)) {
					$specifications[] = array("field_name" => "custom_field_" . strtolower($row['custom_field_code']), "field_description" => $row['form_label'], "field_value" => $customFieldData);
				}
				$productRow['custom_field-' . strtolower($row['custom_field_code'])] = $customFieldData;
			}

			$productRow['product_addons'] = "";
			$productAddons = array();
			ProductCatalog::copyProductCategoryAddons($productRow['product_id']);
			$resultSet = executeReadQuery("select * from product_addons where product_id = ? and inactive = 0" . ($GLOBALS['gUserRow']['administrator_flag'] ? "" : " and internal_use_only = 0") . " order by sort_order", $productRow['product_id']);
			$addonIndex = -1;
			while ($row = getNextRow($resultSet)) {
				if (!empty($row['inventory_product_id'])) {
					$productCatalog = new ProductCatalog();
					$addonInventoryCounts = $productCatalog->getInventoryCounts(true, $row['inventory_product_id']);
					if (empty($addonInventoryCounts[$row['inventory_product_id']]) || $addonInventoryCounts[$row['inventory_product_id']] < 0) {
						continue;
					}
				}
				$row['maximum_quantity'] = (empty($row['maximum_quantity']) || $row['maximum_quantity'] <= 0 ? 1 : $row['maximum_quantity']);
				if (empty($row['group_description'])) {
					$addonIndex++;
					if ($row['maximum_quantity'] <= 1) {
						$row['data_type'] = "tinyint";
					} else {
						$row['data_type'] = "int";
						$row['minimum_value'] = "0";
					}
					$productAddons[$addonIndex] = $row;
				} else {
					$foundIndex = false;
					foreach ($productAddons as $addonIndex => $checkAddon) {
						if ($checkAddon['data_type'] == "select" && $checkAddon['group_description'] == $row['group_description']) {
							$foundIndex = $addonIndex;
							break;
						}
					}
					if ($foundIndex === false) {
						$addonIndex++;
						$productAddons[$addonIndex] = array("data_type" => "select", "group_description" => $row['group_description'], "maximum_quantity" => $row['maximum_quantity'], "options" => array());
						$foundIndex = $addonIndex;
					}
					$productAddons[$foundIndex]['maximum_quantity'] = max($productAddons[$foundIndex]['maximum_quantity'], $row['maximum_quantity']);
					$productAddons[$foundIndex]['options'][] = $row;
				}
			}
			if (!empty($productAddons)) {
				ob_start();
				$addonIntro = getFragment("RETAIL_STORE_ADDON_INTRO");
				if (empty($addonIntro)) {
					$addonIntro = "<p>Some add-ons add cost, which will be reflected in the cart. Product add-ons are only for the product being added to the cart. To make changes to the add-ons for products already in the cart, go to the shopping cart.</p>";
				}
				echo makeHtml($addonIntro);
				$showAddonsImagesInline = !empty(getPageTextChunk("product_addons_inline_image"));
				if ($showAddonsImagesInline) {
					$addonBaseImage = getFieldFromId("image_id", "product_images", "product_image_code", "addon_base", "product_id = ?", $productRow['product_id']);
				}
				foreach ($productAddons as $addonIndex => $thisAddon) {
					if (!empty($thisAddon['form_definition_id'])) {
						continue;
					}
					if ($thisAddon['data_type'] == "tinyint") {
						$columnName = "addon_" . $thisAddon['product_addon_id'] . "_0";
						$description = $thisAddon['description'] . ($thisAddon['sale_price'] == 0 ? "" : " ($" . number_format($thisAddon['sale_price'], 2, ".", ",") . ")");
						?>
						<div class="form-line" id="_<?= $columnName ?>_row">
							<input class='product-addon' data-sku='<?= str_replace("'", "", $thisAddon['manufacturer_sku']) ?>' data-image="<?= (empty($thisAddon['image_id']) ? "" : getImageFilename($thisAddon['image_id'], array("use_cdn" => true))) ?>" data-sale_price="<?= $thisAddon['sale_price'] ?>" type='checkbox' id='<?= $columnName ?>' name='<?= $columnName ?>' value="1">
							<label class='checkbox-label' for='<?= $columnName ?>'><?= htmlText($description) ?></label>
							<?= empty($thisAddon['image_id']) || !$showAddonsImagesInline ? "" : "<img class='addon-image-inline addon-image-checkbox' src='" . getImageFilename($thisAddon['image_id'], array("use_cdn" => true)) . "'>" ?>
							<div class='clear-div'></div>
						</div>
						<?php
					} else if ($thisAddon['data_type'] == "int") {
						$columnName = "addon_" . $thisAddon['product_addon_id'] . "_0";
						$description = $thisAddon['description'] . ($thisAddon['sale_price'] == 0 ? "" : " ($" . number_format($thisAddon['sale_price'], 2, ".", ",") . " each)");
						?>
						<div class="form-line" id="_<?= $columnName ?>_row">
							<label><?= htmlText($description) ?></label>
							<input tabindex='10' type='text' data-sku='<?= str_replace("'", "", $thisAddon['manufacturer_sku']) ?>' data-image="<?= (empty($thisAddon['image_id']) ? "" : getImageFilename($thisAddon['image_id'], array("use_cdn" => true))) ?>" data-maximum_quantity='<?= $thisAddon['maximum_quantity'] ?>' class='product-addon validate[required,custom[integer],min[0],max[<?= $thisAddon['maximum_quantity'] ?>]' data-sale_price="<?= $thisAddon['sale_price'] ?>" id='<?= $columnName ?>' name='<?= $columnName ?>' value="0">
							<?= empty($thisAddon['image_id']) || !$showAddonsImagesInline ? "" : "<img class='addon-image-inline addon-image-text' src='" . getImageFilename($thisAddon['image_id'], array("use_cdn" => true)) . "'>" ?>
							<div class='clear-div'></div>
						</div>
						<?php
					} else {
						$columnName = "addon_select_" . $addonIndex . "_0";
						?>
						<div class="form-line" id="_<?= $columnName ?>_row">
							<label><?= $thisAddon['group_description'] ?><span class='required-tag fa fa-asterisk'></span></label>
							<?php if ($thisAddon['maximum_quantity'] > 1) { ?>
								<input type='text' class='addon-select-quantity validate[custom[integer],min[1]]' value='1'>
							<?php } else { ?>
								<input type='hidden' class='addon-select-quantity' value='1'>
							<?php } ?>
							<select class='validate[required] product-addon' id='<?= $columnName ?>' name='<?= $columnName ?>'>
								<option value="">[Select]</option>
								<?php
								foreach ($thisAddon['options'] as $thisOption) {
									$optionId = "addon_" . $thisOption['product_addon_id'] . "_0";
									?>
									<option id="<?= $optionId ?>" data-sku='<?= str_replace("'", "", $thisOption['manufacturer_sku']) ?>' data-maximum_quantity='<?= $thisOption['maximum_quantity'] ?>' data-image="<?= (empty($thisOption['image_id']) ? "" : getImageFilename($thisOption['image_id'], array("use_cdn" => true))) ?>" value='<?= $thisOption['product_addon_id'] ?>' data-sale_price="<?= $thisOption['sale_price'] ?>"><?= $thisOption['description'] . ($thisOption['sale_price'] == 0 ? "" : " (" . "$" . number_format($thisOption['sale_price'], 2, ".", ",") . ")") ?></option>
									<?php
								}
								?>
							</select>
							<div class='clear-div'></div>
						</div>
						<?php
					}
				}
				$productRow['product_addons'] = ob_get_clean();
			}
		} else {
			$productRow = array();
			$specifications = array();
		}
		$productRow['specifications'] = $specifications;

		$productRow['additional_messages'] = "";
		$eventId = getReadFieldFromId("event_id", "events", "product_id", $productRow['product_id']);
		if (!empty($eventId)) {
			$attendeeCounts = Events::getAttendeeCounts($eventId);
			$spotsLeft = $attendeeCounts['attendees'] - $attendeeCounts['registrants'];
			if ($productRow['cart_maximum'] > $spotsLeft) {
				$productRow['cart_maximum'] = $spotsLeft;
			}
			$productRow['additional_messages'] .= "<p id='_class_spots'>" . ($spotsLeft <= 0 ? "No" : $spotsLeft) . " spot" . ($spotsLeft == 1 ? "" : "s") . " left</p>";

			$eventTypeId = getReadFieldFromId("event_type_id", "events", "event_id", $eventId);
			$resultSet = executeReadQuery("select * from certification_types join event_type_requirements using (certification_type_id) where event_type_id = ? order by sort_order,description", $eventTypeId);
			if ($resultSet['row_count'] > 0) {
				$userCertified = $GLOBALS['gLoggedIn'];
				$requirementsFound = 0;
				$messageAdded = false;
				while ($row = getNextRow($resultSet)) {
					if (!$messageAdded) {
						if ($row['any_requirement']) {
							$productRow['additional_messages'] .= "<p id='_event_requirements'>This class requires one of the following:</p><ul>";
						} else {
							$productRow['additional_messages'] .= "<p id='_event_requirements'>This class has the following requirements:</p><ul>";
						}
					}
					if ($GLOBALS['gLoggedIn']) {
						$statusSet = executeReadQuery("select * from contact_certifications where contact_id = ? and certification_type_id = ? and date_issued <= current_date", $GLOBALS['gUserRow']['contact_id'], $row['certification_type_id']);
						$contactCertificationRow = false;
						while ($statusRow = getNextRow($statusSet)) {
							if (empty($statusRow['expiration_date'])) {
								$contactCertificationRow = $statusRow;
								break;
							}
							if (empty($contactCertificationRow)) {
								$contactCertificationRow = $statusRow;
							} else if ($contactCertificationRow['expiration_date'] < $statusRow['expiration_date']) {
								$contactCertificationRow = $statusRow;
							}
						}
						$contactStatus = "";
						if (empty($contactCertificationRow)) {
							if (empty($row['any_requirement'])) {
								$userCertified = false;
							}
						} else {
							$requirementsFound++;
							if (empty($contactCertificationRow['expiration_date'])) {
								$contactStatus = "Issued on " . date("m/d/Y", strtotime($contactCertificationRow['date_issued']));
							} else {
								$contactStatus = "Expire" . ($contactCertificationRow['expiration_date'] < date("Y-m-d") ? "d" : "s") . " on " . date("m/d/Y", strtotime($contactCertificationRow['expiration_date']));
								if ($contactCertificationRow['expiration_date'] < date("Y-m-d")) {
									$userCertified = false;
								}
							}
						}
					}
					$productRow['additional_messages'] .= "<li>" . htmlText($row['description']) . (empty($contactStatus) ? "" : " - " . $contactStatus) . "</li>";
					if ($requirementsFound == 0) {
						$userCertified = false;
					}
				}
				$productRow['additional_messages'] .= "</ul>";
				if (!$userCertified) {
					$notQualifiedMessage = CustomField::getCustomFieldData($productRow['product_id'], "CLASS_NOT_QUALIFIED", "PRODUCTS");
					if (empty($notQualifiedMessage)) {
						$notQualifiedMessage = getFragment("CLASS_NOT_QUALIFIED");
					}
					if (empty($notQualifiedMessage)) {
						$productRow['additional_messages'] .= "<p id='_not_qualified'>You are not qualified for this class. You can schedule the class now, but must complete the prerequisites before attending</p>";
					}
				}
			}
		}

		if ($GLOBALS['gUserRow']['administrator_flag']) {
			if (canAccessPageCode("PRODUCTMAINT")) {
				$productRow['additional_messages'] .= "<p id='_edit_product'><a href='/productmaintenance.php?url_page=show&clear_filter=true&primary_id=" . $productRow['product_id'] . "' target='_blank'>Edit This Product</a></p>";
			} else if (canAccessPageCode("PRODUCTMAINT_LITE")) {
				$productRow['additional_messages'] .= "<p id='_edit_product'><a href='/product-maintenance?url_page=show&clear_filter=true&primary_id=" . $productRow['product_id'] . "' target='_blank'>Edit This Product</a></p>";
			}
		}
		$productDetailMessage = CustomField::getCustomFieldData($productRow['product_id'], "PRODUCT_DETAILS_MESSAGES", "PRODUCTS");
		if (!empty($productDetailMessage)) {
			$productRow['additional_messages'] = makeHtml($productDetailMessage);
		}

		$makeOfferProductTagId = getReadFieldFromId("product_tag_id", "product_tags", "product_tag_code", "MAKE_OFFER");
		$productTagLinkId = getReadFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $this->iProductId,
			"product_tag_id = ? and (start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date >= current_date)", $makeOfferProductTagId);
		if (empty($productTagLinkId)) {
			$productRow['make_offer_class'] = "hidden";
		} else {
			$productRow['make_offer_class'] = "";
		}
		if (empty($productRow['make_offer_class']) && $GLOBALS['gLoggedIn'] && isInUserGroupCode($GLOBALS['gUserId'], "NO_PRODUCT_OFFERS")) {
			$productRow['make_offer_class'] = "hidden";
		}
		$class3ProductTagId = getReadFieldFromId("product_tag_id", "product_tags", "product_tag_code", "CLASS_3");
		$productTagLinkId = "";
		if (empty($class3ProductTagId)) {
			$productTagLinkId = getReadFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $this->iProductId, "product_tag_id = ?", $class3ProductTagId);
		}
		if (empty($this->iCredovaUsername) || !empty($productTagLinkId)) {
			$productRow['credova_financing'] = "";
		} else {
			$productRow['credova_financing'] = "<p class='credova-button' data-amount='" . str_replace("$", "", str_replace(",", "", $productRow['sale_price'])) . "' data-type='popup'></p>";
		}

		$productRow['out_of_stock'] = "";
		$showInStoreOnlyStock = getPreference("SHOW_IN_STORE_ONLY_STOCK");
		if (!empty($productRow['no_online_order']) && empty($showInStoreOnlyStock)) {
			$productRow['availability'] = "";
			$productRow['availability_class'] = "hidden";
		} else {
			if (empty($productRow['non_inventory_item']) && empty($productRow['inventory_count']) && empty($neverOutOfStock)) {
				$productRow['availability'] = getFragment("retail_store_out_of_stock");
				if (empty($productRow['availability'])) {
					$productRow['availability'] = "<span class='fa fa-times-circle'></span>Out of Stock";
				}
				$productRow['availability_class'] = "red-text";
				$productRow['out_of_stock'] = "out-of-stock";
				$productRow['product_classes'] .= " out-of-stock-product";
				$productRow['location_availability'] = "";
			} else {
				$productRow['availability'] = getFragment("retail_store_in_stock");
				if (empty($productRow['availability'])) {
					$productRow['availability'] = "<span class='fa fa-check-circle'></span>In Stock";
				}
				$productRow['availability_class'] = "";
			}
		}

		$shippingPrice = getReadFieldFromId("price", "product_prices", "product_id", $this->iProductId, "product_price_type_id = " .
			"(select product_price_type_id from product_price_types where product_price_type_code = 'SHIPPING_CHARGE' and (start_date is null or start_date <= current_date) and " .
			"(end_date is null or end_date >= current_date) and client_id = ?)" .
			(!$GLOBALS['gLoggedIn'] || empty($GLOBALS['gUserRow']['user_type_id']) ? " and user_type_id is null" : " and (user_type_id is null or user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")"), $GLOBALS['gClientId']);
		if (strlen($shippingPrice) == 0) {
			$shippingPrice = getReadFieldFromId("price", "product_prices", "product_id", $this->iProductId, "product_price_type_id = " .
				"(select product_price_type_id from product_price_types where product_price_type_code = 'SHIPPING_CHARGE_" . $row['shipping_method_code'] .
				"' and (start_date is null or start_date <= current_date) and (end_date is null or end_date >= current_date) and client_id = ?)" .
				(!$GLOBALS['gLoggedIn'] || empty($GLOBALS['gUserRow']['user_type_id']) ? " and user_type_id is null" : " and (user_type_id is null or user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")"), $GLOBALS['gClientId']);
		}
		if (strlen($shippingPrice) > 0 && $shippingPrice == 0) {
			$productRow['product_classes'] .= " free-shipping";
		}

		$productRow['product_specifications_divs'] = "";
		$productRow['product_specifications'] = "";
		foreach ($productRow['specifications'] as $thisSpecification) {
			$specificationFieldName = makeCode($thisSpecification['field_name'], array("lowercase" => true));
			$productRow['product_specifications'] .= "<tr id='_" . $specificationFieldName . "_row'><td class='specification-name'>" . htmlText($thisSpecification['field_description']) . "</td>" .
				"<td class='specification-value' id='" . $specificationFieldName . "'>" . $thisSpecification['field_value'] . "</td></tr>";
			$productRow['product_specifications_divs'] .= "<div id='_" . $specificationFieldName . "_row' class='product-specification-row'><div class='specification-name'>" . htmlText($thisSpecification['field_description']) . "</div>" .
				"<div class='specification-value' id='" . $specificationFieldName . "'>" . $thisSpecification['field_value'] . "</div></div>";
		}

		# Get Reviews

		$reviewCount = 0;
		$starCount = 0;
		$totalStars = 0;
		$starReviewCounts = array();
		for ($x = 0; $x <= 5; $x++) {
			$starReviewCounts[$x] = 0;
		}

		$eventTypeId = getReadFieldFromId("event_type_id", "events", "product_id", $this->iProductId);
		if (empty($eventTypeId)) {
			$resultSet = executeReadQuery("select * from product_reviews where product_id = ? and inactive = 0 and requires_approval = 0 order by rating desc,date_created desc", $this->iProductId);
		} else {
			$resultSet = executeReadQuery("select * from product_reviews where product_id in (select product_id from events where product_id is not null and event_type_id = ?) and inactive = 0 and requires_approval = 0 order by rating desc,date_created desc", $eventTypeId);
		}
		$sampleReviewsArray = array();
		ob_start();
		while ($row = getNextRow($resultSet)) {
			$reviewCount++;
			if (strlen($row['rating']) > 0) {
				$starCount++;
				$totalStars += $row['rating'];
				$starReviewCounts[$row['rating']]++;
			}
			$reviewer = $row['reviewer'];
			if (empty($reviewer)) {
				$contactFields = Contact::getContactFromUserId($row['user_id']);
				if (!empty($contactFields['first_name'])) {
					$reviewer = $contactFields['first_name'] . " " . substr($contactFields['last_name'], 0, 1);
				} else {
					$reviewer = $contactFields['email_address'];
					if (strpos($reviewer, "@") !== false) {
						$parts = explode("@", $reviewer);
						$reviewer = $parts[0];
					}
				}
			}
			$starReviews = ProductCatalog::getReviewStars($row['rating']);
			$purchased = false;
			if (!empty($row['user_id'])) {
				$orderItemId = getReadFieldFromId("order_item_id", "order_items", "product_id", $row['product_id'], "order_id in (select order_id from orders where contact_id = (select contact_id from users where user_id = ?))", $row['user_id']);
				if (!empty($orderItemId)) {
					$purchased = true;
				}
			}
			if ($reviewCount <= 5) {
				$alreadyLiked = $_COOKIE[$row['product_id'] . "-" . $row['product_review_id'] . "-review"];
				?>
				<div class='review-wrapper'>
					<div class='review-date'><?= date("m/d/Y", strtotime($row['date_created'])) ?></div>
					<?php if ($purchased) { ?>
						<p class='reviewer-purchased'>Reviewer has purchased this product.</p>
					<?php } ?>
					<div class='reviewer-name'><?= htmlText($reviewer) ?></div>
					<?php if (!empty($starReviews)) { ?>
						<div class='review-stars'><?= $starReviews ?></div>
					<?php } ?>
					<div class='like-wrapper like-review<?= (empty($alreadyLiked) ? "" : " already-liked") ?>' data-product_review_id='<?= $row['product_review_id'] ?>'><span class='fad fa-thumbs-up'></span> <span class='like-count'><?= $row['like_count'] ?></span></div>
					<div class='review-title'><?= htmlText($row['title_text']) ?></div>
					<div class='review-content'><?= makeHtml($row['content']) ?></div>
					<?php if (!empty($row['response_content'])) { ?>
						<div class='review-response-content'><?= makeHtml($row['response_content']) ?></div>
					<?php } ?>
				</div>
				<?php
			}
			if (count($sampleReviewsArray) <= 5) {
				$sampleReviewsArray[] = array("reviewer" => $reviewer,
					"date_created" => date("Y-m-d", strtotime($row['date_created'])),
					"purchased" => $purchased,
					"title_text" => $row['title_text'],
					"content" => $row['content'],
					"star_rating" => $row['rating']);
			}
		}
		?>
		<p class='<?= ($reviewCount <= 5 ? "invisible" : "") ?>'><a id="view_all_reviews" href="#">View All Reviews</a></p>
		<?php
		?>
		<div class='hidden' id="star_rating_counts" data-star_1_count='<?= $starReviewCounts[1] ?>' data-star_2_count='<?= $starReviewCounts[2] ?>' data-star_3_count='<?= $starReviewCounts[3] ?>' data-star_4_count='<?= $starReviewCounts[4] ?>' data-star_5_count='<?= $starReviewCounts[5] ?>'></div>
		<?php
		$productRow['sample_reviews'] = ob_get_clean();
		$productRow['sample_reviews_data'] = $sampleReviewsArray;
		if ($totalStars > 0) {
			$starRating = (ceil($totalStars / $starCount / .5) * .5);
			$productRow['star_rating'] = $starRating;
			$productRow['star_reviews'] = ProductCatalog::getReviewStars($starRating);
		} else {
			$productRow['star_reviews'] = "";
		}
		if ($reviewCount == 0) {
			$productRow['review_count'] = "No";
			$productRow['review_count_plural'] = "s";
		} else {
			if ($reviewCount > 1) {
				$productRow['review_count'] = $reviewCount;
				$productRow['review_count_plural'] = "s";
			} else {
				$productRow['review_count'] = $reviewCount;
				$productRow['review_count_plural'] = "";
			}
		}
		$productRow['star_review_count'] = $starCount;
		foreach ($starReviewCounts as $index => $count) {
			$word = $GLOBALS['gNumberWords'][$index];
			if (empty($word)) {
				continue;
			}
			$productRow[$word . "_star_reviews"] = $count;
		}

		# Get Questions and answers

		if ($this->iEnableProductQuestions) {
			$sampleQuestionCount = getPageTextChunk("SAMPLE_QUESTION_COUNT");
			if (empty($sampleQuestionCount) || !is_numeric($sampleQuestionCount)) {
				$sampleQuestionCount = 5;
			}
			$questionCount = 0;
			$resultSet = executeReadQuery("select * from product_questions where product_question_id in (select product_question_id from product_answers where inactive = 0 and requires_approval = 0) and product_id = ? and inactive = 0 and requires_approval = 0 order by like_count desc,date_created desc", $this->iProductId);
			ob_start();
			while ($row = getNextRow($resultSet)) {
				$questionCount++;
				$questioner = $row['full_name'];
				if (empty($questioner)) {
					$contactFields = Contact::getContactFromUserId($row['user_id']);
					if (!empty($contactFields['first_name'])) {
						$questioner = $contactFields['first_name'] . " " . substr($contactFields['last_name'], 0, 1);
					} else {
						$questioner = $contactFields['email_address'];
						if (strpos($questioner, "@") !== false) {
							$parts = explode("@", $questioner);
							$questioner = $parts[0];
						}
					}
				}
				if ($questionCount <= $sampleQuestionCount) {
					$alreadyLiked = $_COOKIE[$this->iProductId . "-" . $row['product_question_id'] . "-question"];
					?>
					<div class='question-wrapper'>
						<div class='question-date'><?= date("m/d/Y", strtotime($row['date_created'])) ?> by <?= htmlText($questioner) ?>
							<div class='like-wrapper like-question<?= (empty($alreadyLiked) ? "" : " already-liked") ?>' data-product_question_id='<?= $row['product_question_id'] ?>'><span class='fad fa-thumbs-up'></span> <span class='like-count'><?= $row['like_count'] ?></span></div>
						</div>
						<div class='question-content'><?= (isHtml($row['content']) ? $row['content'] : makeHtml($row['content'])) ?></div>
						<div class='question-answer-wrapper'>
							<?php
							$answerSet = executeReadQuery("select * from product_answers where product_question_id = ? and inactive = 0 and requires_approval = 0 order by like_count desc,date_created desc", $row['product_question_id']);
							while ($answerRow = getNextRow($answerSet)) {
								$answerer = $answerRow['full_name'];
								if (empty($answerer)) {
									$contactFields = Contact::getContactFromUserId($answerRow['user_id']);
									if (!empty($contactFields['first_name'])) {
										$answerer = $contactFields['first_name'] . " " . substr($contactFields['last_name'], 0, 1);
									} else {
										$answerer = $contactFields['email_address'];
										if (strpos($answerer, "@") !== false) {
											$parts = explode("@", $questioner);
											$questioner = $parts[0];
										}
									}
								}
								$alreadyLiked = $_COOKIE[$this->iProductId . "-" . $row['product_answer_id'] . "-answer"];
								?>
								<div class='answer-wrapper'>
									<div class='answer-date'><?= date("m/d/Y", strtotime($answerRow['date_created'])) ?> by <?= htmlText($answerer) ?>
										<div class='like-wrapper like-answer<?= (empty($alreadyLiked) ? "" : " already-liked") ?>' data-product_answer_id='<?= $answerRow['product_answer_id'] ?>'><span class='fad fa-thumbs-up'></span> <span class='like-count'><?= $answerRow['like_count'] ?></span></div>
									</div>
									<div class='answer-content'><?= (isHtml($answerRow['content']) ? $answerRow['content'] : makeHtml($answerRow['content'])) ?></div>
								</div>
								<?php
							}
							?>
						</div>
					</div>
					<?php
				}
			}
			if ($questionCount > $sampleQuestionCount) {
				?>
				<p><a id="view_all_questions" href="#">View All Questions</a></p>
				<?php
			}
			$productRow['sample_questions'] = ob_get_clean();
			if ($questionCount == 0) {
				$productRow['question_count'] = "No";
				$productRow['question_count_plural'] = "s";
			} else {
				if ($questionCount > 1) {
					$productRow['question_count'] = $questionCount;
					$productRow['question_count_plural'] = "s";
				} else {
					$productRow['question_count'] = $questionCount;
					$productRow['question_count_plural'] = "";
				}
			}
		}

		$productRow['product_restrictions'] = "";
		if ($GLOBALS['gLoggedIn']) {
			$ignoreProductRestrictions = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "IGNORE_PRODUCT_RESTRICTIONS");
		} else {
			$ignoreProductRestrictions = false;
		}
		if (!$ignoreProductRestrictions) {
			$ignoreProductRestrictions = (!empty(getPreference("IGNORE_PRODUCT_RESTRICTIONS")));
		}
		if (empty($ignoreProductRestrictions)) {
			$resultSet = executeReadQuery("select state,postal_code,country_id from product_restrictions where product_id = ? union " .
				"select state,postal_code,country_id from product_category_restrictions where product_category_id in (select product_category_id from product_category_links where product_id = ?) union " .
				"select state,postal_code,country_id from product_department_restrictions where (product_department_id in (select product_department_id from product_category_departments where " .
				"product_category_id in (select product_category_id from product_category_links where product_id = ?)) or product_department_id in (select product_department_id from " .
				"product_category_group_departments where product_category_group_id in (select product_category_group_id from product_category_group_links where product_category_id in " .
				"(select product_category_id from product_category_links where product_id = ?))))", $productRow['product_id'], $productRow['product_id'], $productRow['product_id'], $productRow['product_id']);
			$usedRestrictions = array();
			$stateArray = getStateArray();
			while ($row = getNextRow($resultSet)) {
				if (in_array(jsonEncode($row), $usedRestrictions)) {
					continue;
				}
				if (empty($row['state']) && empty($row['postal_code']) && empty($row['country_id'])) {
					continue;
				}
				$ignoreStateRestriction = CustomField::getCustomFieldData($productRow['product_id'], "IGNORE_RESTRICTIONS_" . strtoupper($row['state']), "PRODUCTS");
				if (!empty($ignoreStateRestriction)) {
					continue;
				}
				if (!empty($GLOBALS['gProductRestrictionStateLimitations']) && is_array($GLOBALS['gProductRestrictionStateLimitations'])) {
					if (!in_array($row['state'], $GLOBALS['gProductRestrictionStateLimitations'])) {
						continue;
					}
				}
				$usedRestrictions[] = jsonEncode($row);
				$restrictions = "";
				if (!empty($row['state'])) {
					$state = $stateArray[$row['state']];
					if (empty($state)) {
						$state = $row['state'];
					}
					$restrictions .= (empty($restrictions) ? "" : ", ") . $state;
				}
				if (!empty($row['postal_code'])) {
					$restrictions .= (empty($restrictions) ? "" : ", ") . $row['postal_code'];
				}
				if (!empty($row['country_id']) && $row['country_id'] != 1000) {
					$restrictions .= (empty($restrictions) ? "" : ", ") . getReadFieldFromId("country_name", "countries", "country_id", $row['country_id']);
				}
				if (empty($restrictions)) {
					continue;
				}
				$productRow['product_restrictions'] .= (empty($productRow['product_restrictions']) ? "" : "; ") . $restrictions;
			}
		}
		if (!empty($productRow['product_restrictions'])) {
			$productRow['product_restrictions'] = "<p>Sale not allowed in " . $productRow['product_restrictions'] . "</p>";
		}
		$productGroups = array();
		$productGroupVariantRow = array();
		$resultSet = executeReadQuery("select * from product_group_variants where product_id = ?", $productRow['product_id']);
		if ($row = getNextRow($resultSet)) {
			$productGroupVariantRow = $row;
			if (!in_array($row['product_group_id'], $productGroups)) {
				$productGroups[] = $row['product_group_id'];
			}
		}
		if (!empty($productGroups)) {
			$productGroupSet = executeReadQuery("select *,products.client_id as product_client_id from products left outer join product_data using (product_id) where " .
				"products.product_id in (select product_id from product_group_variants where product_group_id in (" . implode(",", $productGroups) . "))");
			$count = 0;
			while ($productGroupRow = getNextRow($productGroupSet)) {
				$productGroupRow['client_id'] = $productGroupRow['product_client_id'];
				$GLOBALS['gProductRows'][$productGroupRow['product_id']] = $productGroupRow;
				$GLOBALS['gProductCodes'][$productGroupRow['product_code']] = $productGroupRow['product_id'];
				$count++;
			}
		}

		$productRow['product_tag_codes'] = array();
		ob_start();
		?>
		<div id="product_detail_product_tags">
			<?php
			$resultSet = executeReadQuery("select * from product_tags where client_id = ? and " .
				"inactive = 0 and product_tag_id in (select product_tag_id from product_tag_links where product_id = ? and (start_date is null or start_date <= current_date) and " .
				"(expiration_date is null or expiration_date >= current_date)) order by sort_order,description", $GLOBALS['gClientId'], $this->iProductId);
			while ($row = getNextRow($resultSet)) {
				$productRow['product_tag_codes'][] = $row['product_tag_code'];
				if (empty($row['display_color']) || !empty($row['internal_use_only'])) {
					continue;
				}
				?>
				<div class='product-detail-product-tag product-detail-product-tag-<?= strtolower(str_replace("_", "-", $row['product_tag_code'])) ?>'><?= htmlText($row['description']) ?></div>
				<?php
			}
			?>
		</div>
		<?php
		$productRow['product_tags'] = ob_get_clean();

		ob_start();
		$productRow['product_variants'] = "";
		if (empty($productRow['product_variants'])) {
			if (!empty($productGroupVariantRow)) {
				$productOptions = array();
				$productVariantQueryString = "product_id in (select product_id from products where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . ")";
				$primaryOptionId = "";
				$primaryOptionChoiceId = "";
				$secondaryOptionId = "";
				$secondaryOptionChoiceId = "product_id in (select product_id from products where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . ")";
				$lastOptionId = "";
				$resultSet = executeReadQuery("select * from product_group_options join product_options using (product_option_id) where product_group_id = ? and client_id = ? order by sequence_number", $productGroupVariantRow['product_group_id'], $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$row['product_option_choice_id'] = getReadFieldFromId("product_option_choice_id", "product_group_variant_choices", "product_group_variant_id", $productGroupVariantRow['product_group_variant_id'], "product_option_id = ?", $row['product_option_id']);
					$optionSet = executeReadQuery("select * from product_option_choices where product_option_id = ? and product_option_choice_id in (select product_option_choice_id from product_group_variant_choices where " .
						"product_group_variant_id in (select product_group_variant_id from product_group_variants where product_group_id in (" . implode(",", $productGroups) . "))) order by sort_order,description", $row['product_option_id']);
					$row['product_option_choices'] = array();
					while ($optionRow = getNextRow($optionSet)) {
						$row['product_option_choices'][$optionRow['product_option_choice_id']] = $optionRow['description'];
					}
					$productOptions[$row['product_option_id']] = $row;
					if (empty($primaryOptionId)) {
						$primaryOptionId = $row['product_option_id'];
						$primaryOptionChoiceId = $row['product_option_choice_id'];
					} else if (empty($secondaryOptionId)) {
						$secondaryOptionId = $row['product_option_id'];
						$secondaryOptionChoiceId = $row['product_option_choice_id'];
					}
					$lastOptionId = $row['product_option_id'];
					$productVariantQueryString .= (empty($productVariantQueryString) ? "" : " and ") .
						"product_group_variant_id in (select product_group_variant_id from product_group_variant_choices where product_option_id = " . $row['product_option_id'] . " and product_option_choice_id = ?)";
				}

				$urlAliasTypeCode = getUrlAliasTypeCode("products", "product_id", "id");

				$someAlternateOptions = false;
				$variantWhereStatement = "";
				foreach ($productOptions as $thisOptionId => $thisOption) {
					$selectedKey = "";
					?>
					<div class="form-line">
						<label for="product_option_id_<?= $thisOption['product_option_id'] ?>"><?= htmlText($thisOption['description']) ?></label>
						<select tabindex="10" class="product-option validate[required]"
						        id="product_option_id_<?= $thisOption['product_option_id'] ?>"
						        name="product_option_id_<?= $thisOption['product_option_id'] ?>">
							<?php
							foreach ($thisOption['product_option_choices'] as $thisKey => $thisDescription) {
								$optionParameter = "";
								$parameters = array();
								foreach ($productOptions as $checkOptionId => $checkOption) {
									if ($checkOptionId == $thisOptionId) {
										$parameters[] = $thisKey;
									} else {
										$parameters[] = $checkOption['product_option_choice_id'];
									}
								}
								$variantProductRow = array();
								$alternateOptions = false;
								if ($thisOptionId == $primaryOptionId && count($productOptions) > 2) {
									$variantProductId = getReadFieldFromId("product_id", "product_group_variants", "product_group_id", $productGroupVariantRow['product_group_id'],
										"product_id <> " . $productRow['product_id'] . " and product_id in (select product_id from products where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . ") and " .
										"product_group_variant_id in (select product_group_variant_id from product_group_variant_choices where product_option_id = " . $primaryOptionId . " and product_option_choice_id = " . $thisKey . ") and " .
										"product_group_variant_id in (select product_group_variant_id from product_group_variant_choices where product_option_id = " . $secondaryOptionId . " and product_option_choice_id = " . $secondaryOptionChoiceId . ")");
									$variantProductRow = ProductCatalog::getCachedProductRow($variantProductId, array("inactive" => "0", "internal_use_only" => ($GLOBALS['gInternalConnection'] ? "" : "0")));
								}
								if (empty($variantProductRow)) {
									$variantProductId = getReadFieldFromId("product_id", "product_group_variants", "product_group_id", $productGroupVariantRow['product_group_id'],
										$productVariantQueryString, $parameters);
									$variantProductRow = ProductCatalog::getCachedProductRow($variantProductId, array("inactive" => "0", "internal_use_only" => ($GLOBALS['gInternalConnection'] ? "" : "0")));
									if (empty($variantProductRow) && $thisOptionId != $lastOptionId && !empty($variantWhereStatement)) {
										$variantProductId = getReadFieldFromId("product_id", "product_group_variants", "product_group_id", $productGroupVariantRow['product_group_id'],
											"product_id <> " . $productRow['product_id'] . " and product_id in (select product_id from products where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . ") and " .
											"product_group_variant_id in (select product_group_variant_id from product_group_variant_choices where product_option_id = " . $thisOption['product_option_id'] . " and product_option_choice_id = ?)" .
											" and " . $variantWhereStatement, $thisKey);
										$variantProductRow = ProductCatalog::getCachedProductRow($variantProductId, array("inactive" => "0", "internal_use_only" => ($GLOBALS['gInternalConnection'] ? "" : "0")));
										if (!empty($variantProductRow)) {
											$alternateOptions = true;
											$someAlternateOptions = true;
										}
									}
								}
								if (empty($variantProductRow) && $thisOptionId != $lastOptionId) {
									$variantProductId = getReadFieldFromId("product_id", "product_group_variants", "product_group_id", $productGroupVariantRow['product_group_id'],
										"product_id <> " . $productRow['product_id'] . " and product_id in (select product_id from products where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . ") and " .
										"product_group_variant_id in (select product_group_variant_id from product_group_variant_choices where product_option_id = " . $thisOption['product_option_id'] . " and product_option_choice_id = ?)", $thisKey);
									$variantProductRow = ProductCatalog::getCachedProductRow($variantProductId, array("inactive" => "0", "internal_use_only" => ($GLOBALS['gInternalConnection'] ? "" : "0")));
									$alternateOptions = true;
									$someAlternateOptions = true;
								}
								if (empty($variantProductRow)) {
									continue;
								}
								if ($thisKey == $thisOption['product_option_choice_id'] || $variantProductId == $productRow['product_id']) {
									$optionParameter = "selected";
									$selectedKey = $thisKey;
								}
								$linkUrl = "/" . (empty($urlAliasTypeCode) || empty($variantProductRow['link_name']) ? "product-details?id=" . $variantProductId : $urlAliasTypeCode . "/" . $variantProductRow['link_name']);
								?>
								<option data-link_url="<?= $linkUrl ?>" value="<?= $thisKey ?>" <?= $optionParameter ?>><?= htmlText($thisDescription) . ($alternateOptions ? " *" : "") ?></option>
								<?php
							}
							?>
						</select>
						<div class='clear-div'></div>
					</div>
					<?php
					$variantWhereStatement = (empty($variantWhereStatement) ? "" : " and ") . "product_group_variant_id in (select product_group_variant_id from product_group_variant_choices where product_option_id = " . $thisOption['product_option_id'] . " and product_option_choice_id = " . $selectedKey . ")";
				}
				if ($someAlternateOptions) {
					?>
					<p><sup>*</sup> This option is not available with the other chosen options, so some other options will change.</p>
					<?php
				}
			}
			$productRow['product_variants'] = ob_get_clean();
		}

		$this->iProductRow = $productRow; // store product row for analytics
	}

	function hiddenElements() {
		ob_start();
		$makeOfferText = getFragment("make_offer_text");
		$locationBlock = getFragment("pickup_location_block");
		if (empty($locationBlock)) {
			ob_start();
			?>
			<div class='location-block %inventory_class%' data-location_id='%location_id%' data-location_code='%location_code%'>
				<p class='location-block-distance'>%distance%<br><span class='location-block-inventory-count'>%inventory_count%</span></p>
				<h3>%business_name%</h3>
				<p>%address_1%<br>%city%, %state% %postal_code%<br>%phone_number%</p>
				<div class='store-information'>%store_information%</div>
				<p>%directions_link%</p>
				<button class='location-block-store-button %store_button_type%'>%button_text%</button>
			</div>
			<?php
			$locationBlock = ob_get_clean();
		}
		?>
		<div id="default_pickup_location_block" class="hidden">
			<?= $locationBlock ?>
		</div>
		<div id="change_location_panel" class="only-available">
			<span class="fas fa-close" id="close_change_location_panel"></span>
			<h2>Check Other Stores</h2>
			<p><input type="text" id="change_location_postal_code" name="change_location_postal_code" placeholder="Zip Code"><span id="search_change_location" class="fad fa-search"></span></p>
			<div id="available_filter">
				<p id="available_filter_line_1">Show All Stores</p>
				<p id="available_filter_line_2">with available in-store pickup</p>
				<span class='fad fa-toggle-on available-filter-toggle'></span><span class='fad fa-toggle-off available-filter-toggle'></span>
			</div>
			<div id="change_locations"></div>
		</div>
		<div id="_make_offer_dialog" class="dialog-box">
			<?= makeHtml($makeOfferText) ?>
			<p class='error-message'></p>
			<form id="_make_offer_form">
				<input type="hidden" id="make_offer_product_id" name="product_id">
				<div class="form-line" id="_amount_row">
					<label>Your Offer Amount</label>
					<input tabindex="10" type="text" size="12" class="align-right validate[custom[number]]" data-decimal-places="2" id="amount" name="amount">
					<div class='clear-div'></div>
				</div>
			</form>
		</div>

		<div id="write_review_dialog" class="dialog-box">
			<form id="_review_form">
				<?php
				if (!$GLOBALS['gLoggedIn']) {
					echo createFormLineControl("product_reviews", "reviewer", array("not_null" => true, "form_label" => "Your Name"));
				} else {
					?>
					<p>Reviewing product as <?= getUserDisplayName() ?></p>
					<?php
				}
				?>
				<input type="hidden" id="rating" name="rating" value="">
				<input type="hidden" id="_add_hash" name="_add_hash" value="<?= md5(uniqid(mt_rand(), true)) ?>">
				<input type="hidden" id="write_review_product_id" name="product_id" value="<?= $this->iProductId ?>">
				<div class="form-line" id="_star_rating_row">
					<label id="star_label"></label>
					<span class="star-rating far fa-star" id="star_rating_1" data-star_number="1"
					      data-label="It's terrible"></span><span class="star-rating far fa-star" id="star_rating_2"
					                                              data-star_number="2"
					                                              data-label="It's not good"></span><span
						class="star-rating far fa-star" id="star_rating_3" data-star_number="3"
						data-label="It's Ok"></span><span class="star-rating far fa-star" id="star_rating_4"
					                                      data-star_number="4"
					                                      data-label="It's good"></span><span
						class="star-rating far fa-star" id="star_rating_5" data-star_number="5"
						data-label="It's Awesome"></span>
					<div class='clear-div'></div>
				</div>
				<?php
				echo createFormLineControl("product_reviews", "title_text", array("form_label" => "Review Title", "data_type" => "varchar", "inline-width" => "500px", "not_null" => true));
				echo createFormLineControl("product_reviews", "content", array("not_null" => true, "form_label" => "Review Details", "classes" => "ck-editor"));
				?>
				<p id="review_error_message" class="error-message"></p>
			</form>
		</div>

		<div id="ask_question_dialog" class="dialog-box">
			<form id="_question_form">
				<?php
				if (!$GLOBALS['gLoggedIn']) {
					echo createFormLineControl("product_questions", "full_name", array("not_null" => true, "form_label" => "Your Name"));
				} else {
					?>
					<p>Asking question as <?= getUserDisplayName() ?></p>
					<?php
				}
				?>
				<input type="hidden" id="_add_hash" name="_add_hash" value="<?= md5(uniqid(mt_rand(), true)) ?>">
				<input type="hidden" id="ask_question_product_id" name="product_id" value="<?= $this->iProductId ?>">
				<?php
				echo createFormLineControl("product_questions", "content", array("not_null" => true, "form_label" => "Your Question", "classes" => "ck-editor"));
				?>
				<p id="question_error_message" class="error-message"></p>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	function javascript() {
		ob_start();
		$credovaCredentials = getCredovaCredentials();
		$credovaTest = $credovaCredentials['test_environment'];
		?>
		<script>
            credovaTestEnvironment = <?= (empty($credovaTest) ? "false" : "true") ?>;
            credovaUserName = "<?= $this->iCredovaUsername ?>";
			<?php
			if (!empty($this->iApi360UserName) && !empty($this->iApi360Key)) {
			?>
            function goRotator(item) {
                const uID = '<?= $this->iApi360UserName ?>';
                const key = '<?= $this->iApi360Key ?>';
                $.ajax({
                    url: 'https://tsw-api.com/images/rotator/rotator.php?u=' + uID + '&k=' + key + '&i=' + item,
                    type: "GET",
                    dataType: "html",
                    success: function (data) {
                        const getHtml = data;
                        $("body").append('<div id="rotatorContainer" class="rotatorObject"><div></div><img alt="Rotation Image" id="rotatorExit" src="//tsw-api.com/images/rotator/graphics/close_out.png" /></div><div id="rotatorModal" class="rotatorObject">&nbsp;</div>');
                        $("#rotatorContainer > div").html(getHtml);
                    }
                }).done(function () {
                    $("#rotatorExit").css('opacity', '1');
                })
                return false;
            }
			<?php
			}
			$productTagLinkId = getReadFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $this->iProductId, "product_tag_id in (select product_tag_id from product_tags where product_tag_code = 'FINANCING_AVAILABLE')");
			if (!empty($productTagLinkId)) {
			$productCatalog = new ProductCatalog();
			$salePriceInfo = $productCatalog->getProductSalePrice($this->iProductId, array("ignore_map" => true));
			$salePrice = $salePriceInfo['sale_price'];
			if (empty($salePrice)) {
				$salePrice = 0;
			}
			?>
            var financeAmount = "<?= $salePrice ?>";
			<?php
			}
			?>
            function filterRelatedProducts() {
                const $relatedProductsCount = $("#related_products_count");
                const $relatedProductsDiv = $("div.related-products-div");
                $relatedProductsCount.html("");
                const textFilter = $("#related_products_text_filter").val().toLowerCase();
                if (empty(textFilter)) {
                    $relatedProductsDiv.find(".catalog-item").removeClass("hidden");
                } else {
                    $relatedProductsDiv.find(".catalog-item").each(function () {
                        const description = $(this).html().toLowerCase();
                        if (description.indexOf(textFilter) >= 0) {
                            $(this).removeClass("hidden");
                        } else {
                            $(this).addClass("hidden");
                        }
                    });
                }
                if ($("#hide_related_out_of_stock").prop("checked")) {
                    $(".catalog-item.out-of-stock-product").addClass("hidden");
                }
                const relatedProductsCount = $relatedProductsDiv.not(".hidden").find(".catalog-item").not(".hidden").length;
                $relatedProductsCount.html(relatedProductsCount + " result" + (relatedProductsCount === 1 ? "" : "s"));
            }

            function afterSetDefaultLocation() {
                location.reload();
            }
		</script>
		<?php
		return ob_get_clean();
	}

	function inlineJavascript() {
		if (empty($this->iProductRow)) {
			$this->loadProductRow();
		}
		ob_start();
		$relatedProductsCount = $this->iProductRow['related_products_count'];
		$pickupLocations = array();
		$resultSet = executeReadQuery("select * from locations where client_id = ? and inactive = 0 and internal_use_only = 0 and product_distributor_id is null and warehouse_location = 0 and location_id in (select location_id from shipping_methods where " .
			"inactive = 0 and internal_use_only = 0 and location_id is not null)", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$pickupLocations[] = $row['location_id'];
		}
		$defaultLocationId = $this->iDefaultLocationRow['location_id'];

		$removeElements = array();
		if (count($pickupLocations) < 2) {
			$removeElements[] = "available_pickup_locations";
			$removeElements[] = "change_location";
		}
		if (empty($defaultLocationId)) {
			$removeElements[] = "_shipping_option_pickup_available";
			$removeElements[] = "_shipping_option_pickup_not_available";
			$removeElements[] = "_shipping_option_ship_to_store";
		}
		if (empty($this->iProductRow['inventory_count']) || $this->iProductRow['virtual_product']) {
			$removeElements[] = "_shipping_options";
		}
		$locationInventory = $this->iProductRow['inventory_details'][$defaultLocationId];
		if (empty($locationInventory)) {
			$removeElements[] = "_shipping_option_pickup_available";
		} else {
			$removeElements[] = "_shipping_option_pickup_not_available";
			$removeElements[] = "_shipping_option_ship_to_store";
		}
		if (in_array("FFL_REQUIRED", $this->iProductRow['product_tag_codes'])) {
			$removeElements[] = "_shipping_option_ship_to_home";
		} else {
			$removeElements[] = "_shipping_option_ship_to_ffl";
		}
		if (empty($this->iProductRow['inventory_details']['distributor'])) {
			$removeElements[] = "_shipping_option_ship_to_store";
			$removeElements[] = "_shipping_option_ship_to_home";
			$removeElements[] = "_shipping_option_ship_to_ffl";
		}
		$removeElements = array_unique($removeElements);
		?>
		<script>
            $(function () {
				<?php
				foreach ($removeElements as $elementId) {
				?>
                $("#<?= $elementId ?>").remove();
				<?php
				}
				?>
            });
            relatedProductsCount = <?= $relatedProductsCount ?>;
            $(function () {
				<?php if (empty($this->iApi360Key) || empty($this->iApi360UserName)) { ?>
                $("#_rotate_image_wrapper").remove();
				<?php } ?>
                $(document).on("click", ".sort-reviews", function () {
                    $(".sort-reviews").removeClass("selected");
                    $(this).addClass("selected");
                    $("#view_all_reviews").trigger("click");
                    return false;
                });
                $(document).on("click", "#view_all_reviews", function () {
                    $(this).closest("p").addClass("invisible");
                    const sortOrder = $(".sort-reviews").data("sort_order");
                    loadAjaxRequest("/retail-store-controller?url_action=get_all_reviews&product_id=<?= $this->iProductId ?>&sort_order=" + sortOrder, function (returnArray) {
                        if ("all_reviews" in returnArray) {
                            $("#_sample_reviews_wrapper").html(returnArray['all_reviews']);
                        }
                    });
                    return false;
                });
                $(document).on("click", "#view_all_questions", function () {
                    $(this).closest("p").html("Loading...");
                    loadAjaxRequest("/retail-store-controller?url_action=get_all_questions&product_id=<?= $this->iProductId ?>", function (returnArray) {
                        if ("all_questions" in returnArray) {
                            $("#_sample_questions_wrapper").html(returnArray['all_questions']);
                        }
                    });
                    return false;
                });
                if ($("#catalog_result_product_tags_template").length == 0) {
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_product_tag_html", function (returnArray) {
                        if ($("#catalog_result_product_tags_template").length == 0 && "catalog_result_product_tags_template" in returnArray) {
                            $("#_templates").append(returnArray['catalog_result_product_tags_template']);
                        }
                    });
                }
                setTimeout(function () {
                    let firstOne = true;
                    $(".related-products-div").each(function () {
                        if (firstOne) {
                            loadRelatedProducts(<?= $this->iProductId ?>, $(this).data("related_product_type_code"), $(this).attr("id"), function () {
                                if ($(window).width() > 800) {
                                    $(".related-products-selector").first().trigger("click");
                                }
                            });
                        } else {
                            loadRelatedProducts(<?= $this->iProductId ?>, $(this).data("related_product_type_code"), $(this).attr("id"));
                        }
                    });
                }, 500);
            });
            <?php
            if(!empty($GLOBALS['gUserRow']['administrator_flag'])) {
                echo sprintf('console.log("Price Calculation Log:\n$%s")', str_replace(["'", "\n", '"'], ['', '\n', '\"'], $this->iProductRow['calculation_log']));
            }
            ?>
		</script>
		<?php
		return ob_get_clean();
	}

	function onLoadJavascript() {
		if (empty($this->iProductRow)) {
			$this->loadProductRow();
		}

		# process and deal with shipping options

		ob_start();
		$relatedProductsCount = $this->iProductRow['related_products_count'];
		$relatedVideosCount = 0;
		$resultSet = executeReadQuery("select count(*) from product_videos join media using (media_id) where product_id = ? and show_as_playlist = 1", $this->iProductId);
		if ($row = getNextRow($resultSet)) {
			$relatedVideosCount = $row['count(*)'];
		}
		$reviewRequiresUser = getPreference("RETAIL_STORE_REVIEW_REQUIRES_USER");
		$questionRequiresUser = getPreference("RETAIL_STORE_QUESTION_REQUIRES_USER");
        $productKey = getAnalyticsProductKey($this->iProductRow);
		$category = $this->iProductRow['primary_category'];

		?>
		<script>
			<?php if ($this->iEnableProductQuestions) { ?>
            $(document).on("click", ".like-wrapper", function () {
                if (!empty($(this).data("already_clicked"))) {
                    return;
                }
                $(this).data("already_clicked", true);
                var postData = new Object();
                postData['product_id'] = $("#product_id").val();
                postData['product_question_id'] = (empty($(this).data("product_question_id")) ? "" : $(this).data("product_question_id"));
                postData['product_answer_id'] = (empty($(this).data("product_answer_id")) ? "" : $(this).data("product_answer_id"));
                postData['product_review_id'] = (empty($(this).data("product_review_id")) ? "" : $(this).data("product_review_id"));
                $("body").addClass("no-waiting-for-ajax");
                const $savedElement = $(this);
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=like_product_data", postData, function (returnArray) {
                    if ('count_increased' in returnArray) {
                        currentCount = parseInt($savedElement.find(".like-count").html());
                        currentCount++;
                        $savedElement.find(".like-count").html(currentCount);
                        $savedElement.addClass("already-liked");
                    }
                });
                return false;
            });
			<?php } else { ?>
            $("#_questions_tab").remove();
            $("#_question_tab").remove();
			<?php } ?>
            // Save data in a variable for use with other events on this page (add to cart)
            productDetailData = {
                productKey: '<?= $productKey ?>',
                productId: '<?= $this->iProductId ?>',
                upcCode: '<?= $this->iProductRow['upc_code'] ?>',
                productName: '<?= str_replace("'", "\'", $this->iProductRow['description']) ?>',
                productPrice: '<?= $this->iProductRow['sale_price'] ?>',
                productManufacturer: '<?= str_replace("'", "\'", $this->iProductRow['manufacturer_name']) ?>',
                productCategory: '<?= $category ?>'
            }
            sendAnalyticsEvent("detail", productDetailData);
            $(document).on("click", "#available_pickup_locations,#change_location", function () {
                if ($(this).attr("id") == "change_location") {
                    $("#change_location_panel").removeClass("only-available");
                } else {
                    $("#change_location_panel").addClass("only-available");
                }
                $("#search_change_location").trigger("click");
                return false;
            });
            $(document).on("click", "#close_change_location_panel", function () {
                $("#change_location_panel").removeClass("shown");
                return false;
            });
            $("#change_location_postal_code").change(function () {
                $("#search_change_location").trigger("click");
            });
            $(document).on("keyup", "#change_location_postal_code", function (event) {
                if (event.which === 13 || event.which === 3) {
                    $(this).trigger("change");
                }
            });
            $(document).on("click", "#search_change_location", function () {
                if ($(this).hasClass("searching")) {
                    return;
                }
                $(this).addClass("searching");
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_pickup_locations&product_id=" + $("#product_id").val() + "&postal_code=" + encodeURIComponent($("#change_location_postal_code").val()), function (returnArray) {
                    $("#change_locations").html("");
                    $('#change_locations').html(returnArray['no_stores_available_text']);
                    if ("store_locations" in returnArray) {
                        for (let i in returnArray['store_locations']) {
                            let inventoryCount = returnArray['store_locations'][i]['inventory_count'];
                            if (inventoryCount > 5) {
                                returnArray['store_locations'][i]['inventory_count'] = "In-Stock";
                                returnArray['store_locations'][i]['inventory_class'] = "pickup-available";
                            } else if (inventoryCount > 0) {
                                returnArray['store_locations'][i]['inventory_count'] = "Low Stock";
                                returnArray['store_locations'][i]['inventory_class'] = "pickup-available";
                            } else {
                                returnArray['store_locations'][i]['inventory_count'] = "Out of Stock";
                                returnArray['store_locations'][i]['inventory_class'] = "pickup-not-available";
                            }
                            if (empty(returnArray['store_locations'][i]['distance'])) {
                                returnArray['store_locations'][i]['distance'] = "";
                            } else {
                                returnArray['store_locations'][i]['distance'] = RoundFixed(returnArray['store_locations'][i]['distance'], 1) + " mi";
                            }
                            if (empty(returnArray['store_locations'][i]['directions_url'])) {
                                returnArray['store_locations'][i]['directions_link'] = "";
                            } else {
                                returnArray['store_locations'][i]['directions_link'] = "<a target='_blank' href='" + returnArray['store_locations'][i]['directions_url'] + "'>Get Directions</a>";
                            }
                            if (returnArray['store_locations'][i]['default_location']) {
                                returnArray['store_locations'][i]['store_button_type'] = "default-store";
                                returnArray['store_locations'][i]['button_text'] = "My Store";
                            } else {
                                returnArray['store_locations'][i]['store_button_type'] = "";
                                returnArray['store_locations'][i]['button_text'] = "Make Default Store";
                            }
                            let blockHtml = ($("#pickup_location_block").length == 0 ? "" : $("#pickup_location_block").html());
                            if (empty(blockHtml)) {
                                blockHtml = $("#default_pickup_location_block").html();
                            }
                            for (let j in returnArray['store_locations'][i]) {
                                const re = new RegExp("%" + j + "%", 'g');
                                blockHtml = blockHtml.replace(re, returnArray['store_locations'][i][j]);
                            }
                            $("#change_locations").append(blockHtml);
                        }
                        if ($("#change_location_panel").hasClass("only-available")) {
                            if ($("#change_locations").find(".location-block").not(".pickup-not-available").length > 0) {
                                $('#no_stores_available_text').hide();
                            } else {
                                $('#no_stores_available_text').show();
                            }
                        } else {
                            $('#no_stores_available_text').hide();
                        }
                        $("#change_location_panel").addClass("shown");
                    }
                    $("#search_change_location").removeClass("searching");
                });
                return false;
            });

            $(document).on("click", ".location-block-store-button", function () {
                if ($(this).hasClass("default-store")) {
                    return false;
                }
                const locationCode = $(this).closest(".location-block").data("location_code");
                if (!empty(locationCode)) {
                    setDefaultLocation(locationCode);
                }
                return false;
            });
            $(document).on("click", ".available-filter-toggle", function (event) {
                event.stopImmediatePropagation();
                $("#change_location_panel").toggleClass('only-available');
                if ($("#change_locations").find(".location-block:visible").length > 0) {
                    $('#no_stores_available_text').hide();
                } else {
                    $('#no_stores_available_text').show();
                }
            });

            $(document).on("click", "input[type=checkbox].product-addon", function () {
                $(this).trigger("change");
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
                $(this).closest(".form-line").find(".product-addon").trigger("change");
            });
            $(document).on("change", ".product-addon", function () {
                if ($(this).is("select")) {
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
                    const manufacturerSku = $(this).find("option:selected").data("sku");
                    if (!empty(manufacturerSku)) {
                        if ($(".image-thumbnail[data-image_code='" + manufacturerSku + "']").length > 0) {
                            $(".image-thumbnail[data-image_code='" + manufacturerSku + "']").trigger("click");
                        }
                    }
                } else {
                    let maximumQuantity = $(this).data("maximum_quantity");
                    if (isNaN(maximumQuantity) || empty(maximumQuantity) || maximumQuantity <= 0) {
                        maximumQuantity = 1;
                    }
                    const $quantityField = $(this);
                    if (isNaN($quantityField.val() || empty($quantityField.val()) || $quantityField.val() <= 0)) {
                        $quantityField.val("0");
                    }
                    if (parseInt($quantityField.val()) > parseInt(maximumQuantity)) {
                        $quantityField.val(maximumQuantity);
                    }
                    const manufacturerSku = $(this).data("sku");
                    if (!empty(manufacturerSku)) {
                        if ($(".image-thumbnail[data-image_code='" + manufacturerSku + "']").length > 0) {
                            $(".image-thumbnail[data-image_code='" + manufacturerSku + "']").trigger("click");
                        }
                    }
                }

                if ($("#_starting_sale_price").length == 0) {
                    $("#_sale_price_wrapper").append("<input type='hidden' id='_starting_sale_price' value=''>");
                    let salePrice = $("#sale_price").html().replace('$', '').replace(',', '').replace(',', '');
                    $("#_starting_sale_price").val(salePrice);
                }
                let totalSalePrice = parseFloat($("#_starting_sale_price").val());
                $(".product-addon").each(function () {
                    const addOnSalePrice = ($(this).is("select") ? $(this).find("option:selected").data("sale_price") : $(this).data("sale_price"));
                    let quantity = ($(this).is("select") ? $(this).closest(".form-line").find(".addon-select-quantity").val() : $(this).val());
                    if ($(this).is("input[type=checkbox]") && !$(this).prop("checked")) {
                        quantity = 0;
                    }
                    if (!empty(addOnSalePrice)) {
                        totalSalePrice += parseFloat(addOnSalePrice) * parseInt(quantity);
                    }
                });
                $("#sale_price").html("$" + RoundFixed(totalSalePrice, 2));
				<?php if (empty(getPageTextChunk("product_addons_inline_image"))) { ?>
                const thisImageFilename = ($(this).is("select") ? $(this).find("option:selected").data("image") : (($(this).attr("type") == "checkbox" && $(this).prop("checked")) || ($(this).attr("type") != "checkbox" && !empty($(this).val())) ? $(this).data("image") : ""));
                if (empty(thisImageFilename)) {
                    return true;
                }

                $("#_product_primary_image").find("img.addon-image").remove();
                if ($("_product_image").data("image_code") != "addon_base") {
                    $(".image-thumbnail").each(function () {
                        const imageCode = $(this).data("image_code");
                        if (imageCode == "addon_base") {
                            const imageUrl = $(this).data("image_url");
                            $("#_product_image").attr("href", imageUrl);
                            $("#_product_image img").attr("src", imageUrl);
                            $("#_product_image").data("image_code", imageCode);
                            return false;
                        }
                    });
                }
                if ($("#_product_image").data("image_code") != "addon_base") {
                    $("#_product_wrapper").removeClass("addon-builder");
                    return false;
                }
                $("#_product_wrapper").addClass("addon-builder");
                $(".product-addon").each(function () {
                    const imageFilename = ($(this).is("select") ? $(this).find("option:selected").data("image") : (($(this).attr("type") == "checkbox" && $(this).prop("checked")) || ($(this).attr("type") != "checkbox" && !empty($(this).val())) ? $(this).data("image") : ""));
                    if (empty(imageFilename)) {
                        return true;
                    }
                    $("#_product_primary_image").append("<img class='addon-image' src='" + imageFilename + "'>");
                });
                $("html, body").animate({ scrollTop: $("#_product_primary_image").offset().top - 150 }, 800);
				<?php } else { ?>
                if ($(this).is("select")) {
                    $(this).siblings(".addon-image-inline").remove();
                    const imageFilename = $(this).find("option:selected").data("image");
                    if (!empty(imageFilename)) {
                        $(this).after("<img class='addon-image-inline addon-image-select' src='" + imageFilename + "'>");
                    }
                }
				<?php } ?>
            });
            $(document).on("click", "#_make_offer_button", function () {
                if (!userLoggedIn) {
                    if ($("#_create_user_dialog").length == 0) {
                        loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_create_user_dialog", function (returnArray) {
                            if ("dialog" in returnArray) {
                                $("body").append(returnArray['dialog']);
                            }
                            $("#_make_offer_button").trigger("click");
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
                    return false;
                }
                $("#make_offer_product_id").val($("#product_id").val());
                $('#_make_offer_dialog').dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: true,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Make Offer',
                    buttons: {
                        Submit: function (event) {
                            if ($("#_make_offer_form").validationEngine("validate")) {
                                loadAjaxRequest("/retail-store-controller?url_action=make_offer", $("#_make_offer_form").serialize(), function (returnArray) {
                                    if (!("error_message" in returnArray)) {
                                        $("#_make_offer_dialog").dialog('close');
                                    }
                                });
                            }
                        },
                        Cancel: function (event) {
                            $("#_make_offer_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
			<?php if (empty(getPageTextChunk("no_review_popup"))) { ?>
            $(document).on("click", "#write_review", function (event) {
                event.preventDefault();
				<?php if (!empty($reviewRequiresUser) && !$GLOBALS['gLoggedIn']) { ?>
                if ($("#_create_user_dialog").length == 0) {
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_create_user_dialog", function (returnArray) {
                        if ("dialog" in returnArray) {
                            $("body").append(returnArray['dialog']);
                        }
                        $("#write_review").trigger("click");
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
				<?php } else { ?>
                addCKEditor();
                $('#write_review_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 1000,
                    title: 'Review this product',
                    buttons: {
                        "save": {
                            text: 'Save',
                            classes: 'write-review-dialog-save-button',
                            click: function (event) {
                                if (empty($("#rating").val())) {
                                    displayErrorMessage("Please select a star rating");
                                    return;
                                }
                                const $reviewForm = $("#_review_form");
                                if (typeof CKEDITOR !== "undefined") {
                                    for (let instance in CKEDITOR.instances) {
                                        CKEDITOR.instances[instance].updateElement();
                                    }
                                }
                                if ($reviewForm.validationEngine("validate")) {
                                    loadAjaxRequest("/retail-store-controller?url_action=save_review", $reviewForm.serialize(), function (returnArray) {
                                        if (!("error_message" in returnArray)) {
                                            $("#write_review_dialog").dialog('close');
                                        }
                                    });
                                }
                            }
                        },
                        "cancel": {
                            text: 'Cancel',
                            classes: 'write-review-dialog-save-button',
                            click: function (event) {
                                $("#write_review_dialog").dialog('close');
                            }
                        }
                    }
                });
				<?php } ?>
                return false;
            });
            $(document).on("mouseover", ".star-rating", function () {
                const label = $(this).data("label");
                $("#star_label").html(label);
            }).on("mouseleave", ".star-rating", function () {
                $("#star_label").html("");
                if ($(".star-rating.set-rating").length > 0) {
                    $("#star_label").html($(".star-rating.set-rating").data("label"));
                }
            }).on("click", ".star-rating", function () {
                let starRating = $(this).data("star_number");
                $("#rating").val(starRating);
                $(".star-rating").removeClass("selected").removeClass("fas").addClass("far").removeClass("set-rating");
                $(this).addClass("set-rating");
                while (starRating > 0) {
                    $("#star_rating_" + starRating).addClass("selected").removeClass("far").addClass("fas");
                    starRating--;
                }
            });
			<?php } ?>

			<?php if ($this->iEnableProductQuestions) { ?>
            $(document).on("click", "#ask_question", function (event) {
                event.preventDefault();
				<?php if (!empty($questionRequiresUser) && !$GLOBALS['gLoggedIn']) { ?>
                displayErrorMessage("User account required to ask a question. Login or create an account.");
				<?php } else { ?>
                addCKEditor();
                $('#ask_question_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 650,
                    title: 'Ask a question about this product',
                    buttons: {
                        "save": {
                            text: 'Save',
                            classes: 'ask-question-dialog-save-button',
                            click: function (event) {
                                if (typeof CKEDITOR !== "undefined") {
                                    for (let instance in CKEDITOR.instances) {
                                        CKEDITOR.instances[instance].updateElement();
                                    }
                                }
                                if ($("#_question_form").validationEngine("validate")) {
                                    loadAjaxRequest("/retail-store-controller?url_action=save_product_question", $("#_question_form").serialize(), function (returnArray) {
                                        if (!("error_message" in returnArray)) {
                                            $("#ask_question_dialog").dialog('close');
                                        }
                                    });
                                }
                            }
                        },
                        "cancel": {
                            text: 'Cancel',
                            classes: 'ask-question-dialog-save-button',
                            click: function (event) {
                                $("#ask_question_dialog").dialog('close');
                            }
                        }
                    }
                });
				<?php } ?>
                return false;
            });
			<?php } ?>

            $(document).on("click", "#sort_by_price_asc", function () {
                const unorderedDivs = $(".related-products-div").not(".hidden").find(".catalog-item");
                const orderedDivs = unorderedDivs.sort(function (a, b) {
                    const priceA = parseFloat($(a).find(".catalog-item-price").text());
                    const priceB = parseFloat($(b).find(".catalog-item-price").text());
                    return priceA === priceB ? 0 : (priceA > priceB ? 1 : -1);
                });
                $(".related-products-div").not(".hidden").html(orderedDivs);
                $(".related-products-div").not(".hidden").append("<div class='clear-div'></div>");
                return false;
            });
            $(document).on("click", "#sort_by_price_desc", function () {
                const unorderedDivs = $(".related-products-div").not(".hidden").find(".catalog-item");
                const orderedDivs = unorderedDivs.sort(function (a, b) {
                    const priceA = parseFloat($(a).find(".catalog-item-price").text());
                    const priceB = parseFloat($(b).find(".catalog-item-price").text());
                    return priceA === priceB ? 0 : (priceA < priceB ? 1 : -1);
                });
                $(".related-products-div").not(".hidden").html(orderedDivs);
                $(".related-products-div").not(".hidden").append("<div class='clear-div'></div>");
                return false;
            });
            $(document).on("keyup", "#related_products_text_filter", function (event) {
                filterRelatedProducts();
            });
            $(document).on("click", "#hide_related_out_of_stock", function (event) {
                filterRelatedProducts();
            });
			<?php if ($relatedProductsCount > 0) { ?>
            $("#related_products_wrapper").removeClass("hidden");
			<?php } ?>

			<?php if ($relatedVideosCount > 0) { ?>
            $("#related_videos_wrapper").removeClass("hidden");

            $(document).ready(function () {
                const relatedVideosPlayer = $("#related_product_video_iframe_container iframe");
                relatedVideosPlayer.height(relatedVideosPlayer.width() / 16 * 9);
            });

            // Add onclick to playlist items
            const mediaElements = $("#related_product_videos_container .related-product-video");
            mediaElements.click(function (event) {
                if ($(event.target).hasClass("product-video-link")) {
                    event.preventDefault();
                }
                const selectedVideo = $(this);
                mediaElements.removeClass("selected");
                selectedVideo.addClass("selected");

                const embedContainer = $("#related_product_video_iframe_container");
                embedContainer.find("iframe").attr("src", `//${ selectedVideo.data("media_services_link_url") }${ selectedVideo.data("video_identifier") }?autoplay=1`);
                embedContainer.find(".product-video-description").html(selectedVideo.data("description"));
            });

            // Display first item in the list if available
            if (mediaElements.length > 0) {
                mediaElements.first().click();
            }

            // Load thumbnails based on media service provider
            mediaElements.each(function () {
                const videoElement = $(this);
                const videoId = videoElement.data("video_identifier");
                const thumbnailElement = videoElement.find("img");
                switch (videoElement.data("media_service_code")) {
                    case "YOUTUBE":
                        thumbnailElement.attr("src", `https://img.youtube.com/vi/${ videoId }/sddefault.jpg`);
                        break;
                    case "VIMEO":
                        $("body").addClass("no-waiting-for-ajax");
                        loadAjaxRequest(`https://vimeo.com/api/oembed.json?url=https://vimeo.com/${ videoId }`, function (returnArray) {
                            if (!("error_message" in returnArray)) {
                                thumbnailElement.attr("src", returnArray.thumbnail_url);
                            }
                        });
                        break;
                }
            });
			<?php } ?>

            $(document).on("click", ".related-products-selector", function () {
                const elementNumber = $(this).data("related_div_number");
                if (empty(elementNumber)) {
                    return;
                }
                $("#related_products_filters").removeClass("hidden");
                $(".related-products-selector").removeClass("selected");
                $(".related-products-div").addClass("hidden");
                $(this).addClass("selected");
                $("#related_products_" + elementNumber).removeClass("hidden");
                $("#hide_related_out_of_stock").prop("checked", false);
                $("#related_products_text_filter").val("");
                filterRelatedProducts();
            });

            $(document).on("change", ".product-option", function () {
                document.location = $(this).find("option:selected").data("link_url");
                $(".modal").css("display", "block");
            });
            $(document).on("click", "#_tab_container ul#_tab_nav li", function () {
                $(this).siblings("li").removeClass("active");
                $(this).addClass("active");
                $("#_tab_scroll_container > div").addClass("tab-section").show();
                $(".tab-section").addClass("hidden");
                const tabSection = $(this).data("tab_name");
                $("#" + tabSection).removeClass("hidden");
            });
            $("#_tab_scroll_container > div").addClass("tab-section").show().addClass("hidden").first().removeClass("hidden");

            $(document).on("click", ".image-thumbnail", function () {
                $("#_product_primary_image").find("img.addon-image").remove();
                $("#_product_wrapper").removeClass("addon-builder");
                if ($(this).hasClass("product-video")) {
                    if ($("#_product_image_wrapper").find(".media-player").length == 0) {
                        const mediaPlayer = "<div class='media-player'><div class='embed-container'><iframe src='' allow='encrypted-media' frameborder='0' allowfullscreen></iframe></div></div>";
                        $("#_product_primary_image").append(mediaPlayer);
                    }
                    $("#_product_image").addClass("hidden");
                    $("#_product_image_wrapper").find(".media-player").find("iframe").attr("src", $(this).data("video_link"));
                    $("#_product_image_wrapper").find(".media-player").removeClass("hidden");
                } else {
                    const imageUrl = $(this).data("image_url");
                    const imageCode = $(this).data("image_code");
                    $("#_product_image").removeClass("hidden");
                    $("#_product_image").attr("href", imageUrl);
                    $("#_product_image img").attr("src", imageUrl);
                    $("#_product_image").data("image_code", imageCode);
                    $("#_product_image_wrapper").find(".media-player").addClass("hidden");
                    if (imageCode == "addon_base") {
                        $(".product-addon").first().trigger("change");
                    }
                }
                return false;
            });
            $(document).on("click", ".show-rotate-image", function () {
                goRotator($(this).data("product_code"));
                return false;
            });
            $(document).on('click', '#rotatorExit', function () {
                $('.rotatorObject').remove();
            });
            $(document).on("click", 'html', function (event) {
                if (event.target.id === 'rotatorContainer') {
                } else {
                    $('.rotatorObject').remove();
                }
            });
            $("#_product_details_wrapper").find("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({
                social_tools: false,
                default_height: 480,
                default_width: 854,
                deeplinking: false
            });
		</script>
		<?php
		return ob_get_clean();
	}

	function getPageJavascript() {
		$pageId = getReadFieldFromId("page_id", "pages", "script_filename", "retailstore/productdetails.php", "template_id = ?", $GLOBALS['gPageRow']['template_id']);
		if (empty($pageId)) {
			$pageId = getReadFieldFromId("page_id", "pages", "script_filename", "retailstore/productdetails.php");
		}
		return "<style>" . getReadFieldFromId("javascript_code", "pages", "page_id", $pageId) . "</style>";
	}
}
