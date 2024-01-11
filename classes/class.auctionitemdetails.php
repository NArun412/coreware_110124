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
 * class AuctionItemDetails
 *
 * Generate code for displaying auction item details
 * HTML for auction item details and all information
 * inline Javascript - related auction item information
 * onload Javascript - various handlers
 * javascript - functions
 * CSS - for the div section
 * hidden elements - create user dialog, make offer dialog, write review dialog
 *
 * @author Kim D Geiger
 */
class AuctionItemDetails {

	var $iAuctionItemId = "";
	var $iAuctionItemRow = array();

	function __construct($auctionItemId) {
		$this->iAuctionItemId = $auctionItemId;
	}

	function getFullPage() {
		$fullAuctionItemContent = $this->internalCSS();
		$fullAuctionItemContent .= $this->getPageCSS();
		$fullAuctionItemContent .= $this->mainContent();
		$fullAuctionItemContent .= $this->hiddenElements();
		$fullAuctionItemContent .= $this->javascript();
		$fullAuctionItemContent .= $this->inlineJavascript();
		$fullAuctionItemContent .= $this->onLoadJavascript();
		return $fullAuctionItemContent;
	}

	function internalCSS() {
		ob_start();
		?>
        <style>
            .addon-image {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
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

            .auction-item-video-icon {
                font-size: 3rem;
            }

            #_free_shipping {
                display: none;
            }

            #_auction_item_details div#_free_shipping p {
                font-size: 1.2rem;
                color: rgb(0, 128, 0);
            }

            #_auction_item_details div#_free_shipping p span {
                color: rgb(192, 0, 0);
            }

            #_auction_item_details_wrapper.free-shipping #_free_shipping {
                display: block;
            }

            .auction_item-image {
                cursor: zoom-in;
            }

            .button-subtext {
                display: none;
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

            .click-auction-item-detail {
                cursor: pointer;
            }

            .click-auction-item-detail a:hover {
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

            #_auction_item_image_thumbnails {
                flex: 0 0 auto;
                width: 60px;
                margin-right: 10px;
            }

            #_auction_item_image_thumbnails .image-thumbnail {
                max-width: 100%;
                margin-top: 10px;
                border: 1px solid rgb(50, 50, 50);
                cursor: pointer;
                opacity: .5;
                text-align: center;
            }

            #_auction_item_image_thumbnails .image-thumbnail:hover {
                opacity: 1;
            }

            #_auction_item_primary_image {
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

            #_auction_item_details_wrapper {
                display: flex;
                padding: 60px 0;
                background: #fff;
                max-width: 1800px;
                margin: auto;
            }

            #_auction_item_details_wrapper > div {
                flex: 0 0 50%;
                padding: 0 50px;
            }

            #_auction_item_details_wrapper .out-of-stock-message {
                display: none;
            }

            #_auction_item_details p {
                color: #444444;
                margin: 0 0 10px 0;
                padding: 0;
            }

            #_auction_item_wrapper {
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

            /***************Auction Item Detail********************/
            #_auction_item_description {
                font-size: 1.2em;
                font-weight: 700;
            }

            #_sale_price_wrapper {
                margin: 10px 0;
                font-size: 1.8em;
                font-weight: 700;
                letter-spacing: 1px;
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

            #_auction_item_image_wrapper img {
                display: block;
                margin: auto;
                max-height: 500px;
                max-width: 100%;
            }

            .auction-item-features-list {
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

            /***************Catalog Item*********************/
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
                #_auction_item_details_wrapper {
                    flex-direction: column;
                }

                #_auction_item_image img {
                    transform: none;
                }

                button.add-to-cart, button.add-to-wishlist {
                    width: 100%;
                }
            }

            .auction-item-detail-product-tag {
                display: inline-block;
                padding: 2px;
                margin-right: 4px;
                margin-bottom: 4px;
                color: rgb(255, 255, 255);
            }

            <?php
				$resultSet = executeReadQuery("select * from product_tags where client_id = ? and display_color is not null and " .
					"internal_use_only = 0 and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
            .auction-item-detail-product-tag-<?= strtolower(str_replace("_","-",$row['product_tag_code'])) ?> {
                background-color: <?= $row['display_color'] ?>;
            }

            <?php
				}
			?>

        </style>
		<?php
		return ob_get_clean();
	}

	function getPageCSS() {
		$pageId = getReadFieldFromId("page_id", "pages", "script_filename", "auction/auctionitemdetails.php", "template_id = ?", $GLOBALS['gPageRow']['template_id']);
		if (empty($pageId)) {
			$pageId = getReadFieldFromId("page_id", "pages", "script_filename", "auction/auctionitemdetails.php");
		}
		$cssContent = getReadFieldFromId("css_content", "pages", "page_id", $pageId);
		if (!empty($cssContent)) {
			$sassContent = "";
			$resultSet = executeQuery("select content from sass_headers join template_sass_headers using (sass_header_id) where template_id = (select template_id from pages where page_id = ?)", $pageId);
			while ($row = getNextRow($resultSet)) {
				$sassContent .= "\r" . $row['content'];
			}
			$cssContent = $sassContent . $cssContent;
		}
		return "<style>" . processCssContent($cssContent) . "</style>";
	}

	function mainContent() {
		ob_start();
		$auctionItemDetails = getPageTextChunk("auction_item_details");
		if (empty($auctionItemDetails)) {
			$auctionItemDetails = getFragment("auction_item_details");
		}
		if (empty($auctionItemDetails)) {
			$fragmentId = getPreference("AUCTION_ITEM_DETAIL_HTML_FRAGMENT_ID");
			if (!empty($fragmentId)) {
				$auctionItemDetails = getReadFieldFromId("content", "fragments", "fragment_id", $fragmentId, "client_id = ?", $GLOBALS['gDefaultClientId']);
			}
		}
		if (empty($auctionItemDetails)) {
			$auctionItemDetails = getReadFieldFromId("content", "fragments", "fragment_code", "AUCTION_ITEM_DETAILS", "client_id = ?", $GLOBALS['gDefaultClientId']);
		}
		if (empty($auctionItemDetails)) {
			ob_start();
			?>
            <div id="_auction_item_wrapper">

                <div id="_auction_item_details_wrapper" class="%auction_item_classes%">
                    <input type="hidden" id="auction_item_id" value="%auction_item_id%">

                    <div id="_auction_item_image_wrapper">
                        <div id="_still_image_wrapper">
                            <div id="_auction_item_image_thumbnails">
                                %auction_item_image_thumbnails%
                            </div>
                            <div id="_auction_item_primary_image">
                                <a id="_auction_item_image" href="%image_url%" class="pretty-photo" rel='prettyPhoto[all_images]' data-image_code=''>
                                    <img class="auction-item-image" %image_src%="%image_url%" alt="%description%" title="%detailed_description_text%">
                                </a>
                            </div>
                        </div>
                    </div> <!-- _auction_item_image_wrapper -->

                    <div id="_auction_item_details">
                        <div id="_item_details">
                            <p id="_auction_item_description">%description%</p>
                        </div> <!-- _item_details -->

                        %product_tags%

                        <div id="_pricing_wrapper">
                            <p id="_sale_price_wrapper"><span id="sale_price">%sale_price%</span></p>
                            <p id="_make_offer" class='%make_offer_class%'>
                                <button id="_make_offer_button">Make Offer</button>
                            </p>
                            <p class='admin-logged-in' id='_quantity_on_hand'>Quantity on Hand: <span id="_inventory_count">%inventory_count%</span></p>
                        </div> <!-- _pricing_wrapper -->

                        <div id="_button_wrapper">
                            <div id="quantity_wrapper"><input type="text" id="quantity" name="quantity" value="1"
                                                              class="validate[custom[number]]"
                                                              data-cart_maximum="%cart_maximum%"
                                                              data-cart_minimum="%cart_minimum%"><label for='quantity' id="quantity_label">QTY%cart_maximum_label%</label>
                            </div>
                            <p>
                                <button class="buy-now buy-now-%auction_item_id%" id="buy_now_button" data-quantity_field="quantity" data-auction_item_id="%auction_item_id%">Buy Now</button>
                            </p>
                            <p>
                                <button class="place-bid place-bid-%auction_item_id%" id="place_bid_button" data-quantity_field="quantity" data-auction_item_id="%auction_item_id%">Place Bid</button>
                            </p>
                        </div> <!-- _button_wrapper -->

                    </div> <!-- auction_item_details -->

                </div> <!-- auction_item_details_wrapper -->

                <div id="_tab_container">

                    <ul id="_tab_nav">
                        <li class="active" id="_specifications_tab" data-tab_name="specifications_section">Specifications</li>
                        <li id="_reviews_tab" data-tab_name="reviews_section">Reviews</li>
                    </ul>

                    <div id="_tab_scroll_container">

                        <div id="specifications_section" class="tab-section">
                            %detailed_description%

                            <table id="specifications_table">
                                <tbody>
                                %auction_item_specifications%
                                </tbody>
                            </table> <!-- specifications_table -->

                        </div> <!-- specifications_section -->

                    </div> <!-- _tab_scroll_container -->

                </div> <!-- _tab_container -->

            </div> <!-- _auction_item_wrapper -->
			<?php
			$auctionItemDetails = ob_get_clean();
		}

		if (empty($this->iAuctionItemRow)) {
			$this->loadAuctionItemRow();
		}
		$auctionItemRow = $this->iAuctionItemRow;

		foreach ($auctionItemRow as $fieldName => $fieldData) {
			$fieldName = strtolower($fieldName);
			$fieldData = is_scalar($fieldData) ? $fieldData : "";
			$auctionItemDetails = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $auctionItemDetails);
			$auctionItemDetails = str_replace("%hidden_if_empty:" . $fieldName . "%", (empty($fieldData) ? "hidden" : ""), $auctionItemDetails);
			$auctionItemDetails = str_replace("%hidden_if_not_empty:" . $fieldName . "%", (empty($fieldData) ? "" : "hidden"), $auctionItemDetails);
		}
		$auctionItemDetails = str_replace("%image_src%", "src", $auctionItemDetails);
		echo $auctionItemDetails;
		return ob_get_clean();
	}

	public function loadAuctionItemRow() {
		if (!empty($this->iAuctionItemRow)) {
			return;
		}
		$auctionObject = new Auction();
		$auctionItemRow = Auction::getCachedAuctionItemRow($this->iAuctionItemId);
		$bidInformation = $auctionObject->getBidsInformation($this->iAuctionItemId);
		if (!empty($auctionItemRow)) {
			$auctionItemRow['auction_item_url'] = getDomainName() . "/auction/" . $auctionItemRow['link_name'];
			$auctionItemRow['description'] = str_replace("\n", "", $auctionItemRow['description']);
			$auctionItemRow['description_html'] = htmlspecialchars($auctionItemRow['description']);
			$auctionItemRow['seller_username'] = getFieldFromId("user_name", "users", "user_id", $auctionItemRow['user_id']);

			# Use Business name if exists for seller information
			if (!empty(getPreference("USE_FFL_INFORMATION_FOR_AUCTION"))) {
				$resultSet = executeQuery("select * from contacts where contact_id in (select contact_id from federal_firearms_licensees where federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id = ?))", $auctionItemRow['user_id']);
				$sellerRow = getNextRow($resultSet);
				$auctionItemRow['seller_business_name'] = $sellerRow['business_name'];
				$auctionItemRow['seller_address_block'] = getAddressBlock(Contact::getContact($sellerRow['contact_id']));
				$auctionItemRow['seller_phone_number'] = Contact::getContactPhoneNumber($sellerRow['contact_id'], 'Store', true);
				if (!empty($sellerRow['business_name'])) {
					$auctionItemRow['seller_username'] = "";
				}
			}

			$sellerTotalReviewCount = 0;
			$resultSet = executeQuery("select *,AVG(rating) average_rating,count(*) total_count from auction_user_reviews where user_id = ?", $auctionItemRow['user_id']);
			if ($row = getNextRow($resultSet)) {
				$sellerTotalReviewCount = $row['total_count'];
				$sellerAverageReviewCount = $row['average_rating'];
			}
			$auctionItemRow['seller_review_total'] = $sellerTotalReviewCount;
			$auctionItemRow['seller_review_average'] = $sellerAverageReviewCount;

			$auctionItemRow = array_merge($auctionItemRow,$bidInformation);

			$usersMaximumBidRow = $auctionObject->getUsersMaximumBid($auctionItemRow['auction_item_id']);
			$auctionItemRow['user_max_bid'] = $usersMaximumBidRow['maximum_amount'];

			$auctionItemRow['winning_bid_username'] = "";
			if ($bidInformation['winning_user_id'] != $GLOBALS['gUserId'] && !empty($bidInformation['winning_user_id'])) {
				$username = getFieldFromId("user_name", "users", "user_id", $bidInformation['winning_user_id']);
				$auctionItemRow['winning_bid_username'] = substr($username, 0, 4) . str_repeat("*", strlen($username) - 4);
			}

			$currentBidRow = $auctionObject->getCurrentBid($auctionItemRow['auction_item_id']);
			$auctionItemRow['minimum_bid'] = $auctionObject->getMinimumBid($auctionItemRow['auction_item_id']);

			$auctionItemRow['current_bid'] = $currentBidRow['amount'] ?: "0.00";

			$auctionItemRow['product_categories'] = "";
			$auctionItemRow['primary_category'] = "";
			$urlAliasTypeCode = getUrlAliasTypeCode("product_categories", "auction_product_category_id");
			$resultSet = executeReadQuery("select * from product_categories where inactive = 0 and internal_use_only = 0 and client_id = ? and " .
				"product_category_id in (select product_category_id from auction_item_product_category_links where auction_item_id = ?) order by sort_order,description", $GLOBALS['gClientId'], $auctionItemRow['auction_item_id']);
			$firstCategory = true;
			while ($row = getNextRow($resultSet)) {
				$linkUrl = (empty($urlAliasTypeCode) || empty($row['link_name']) ? "/auction-search-results?product_category_id=" . $row['product_category_id'] : "/" . $urlAliasTypeCode . "/" . $row['link_name']);
				$auctionItemRow['product_categories'] .= (empty($auctionItemRow['product_categories']) ? "" : ", ") . "<a href='" . $linkUrl . "'>" . htmlText($row['description']) . "</a>";
				if ($firstCategory) {
					$auctionItemRow['primary_category'] = str_replace("'", "", $row['description']);
					$firstCategory = false;
				}
			}

			$auctionItemRow['price_intro_message'] = getFragment("auction_item_price_intro_message");
			$auctionItemRow['auction_item_classes'] = "";
			$auctionClasses = array();
			if (!empty(getRowFromId("user_watchlists", "auction_item_id", $this->iAuctionItemId, "user_id = ?", $GLOBALS['gUserId']))) {
				$auctionClasses[] = "in-watchlist";
			}
			$auctionItemRow['auction_item_classes'] = implode(" ", $auctionClasses);

			$auctionItemRow['detailed_description'] = makeHtml($auctionItemRow['detailed_description']);
			$auctionItemRow['detailed_description_text'] = htmlText(removeHtml($auctionItemRow['detailed_description']));
			$parameters = array();
			$missingAuctionItemImage = getImageFilenameFromCode("NO_AUCTION_ITEM_IMAGE");
			if (empty($missingAuctionItemImage) || $missingAuctionItemImage == "/images/empty.jpg") {
				$missingAuctionItemImage = getPreference("DEFAULT_AUCTION_ITEM_IMAGE");
			}
			if (empty($missingAuctionItemImage)) {
				$missingAuctionItemImage = "/images/no_product_image.jpg";
			}
			if (!empty($missingAuctionItemImage)) {
				$parameters['default_image'] = $missingAuctionItemImage;
			}

			$auctionItemRow['image_url'] = getImageFilename($auctionItemRow['auction_item_images'][0]['image_id'], array("default_image" => $missingAuctionItemImage));
			$thumbnailExists = false;
			foreach ($GLOBALS['gImageTypes'] as $imageTypeRow) {
				$parameters['image_type'] = strtolower($imageTypeRow['image_type_code']);
				if ($parameters['image_type'] == "thumbnail") {
					$thumbnailExists = true;
				}
			}
			$auctionItemRow['auction_item_image_thumbnails'] = "";
			$auctionItemRow['auction_item_image_elements'] = "";
			$imageCount = 0;
			if (!empty($auctionItemRow['image_url']) && strpos($auctionItemRow['image_url'], $missingAuctionItemImage) === false) {
				$auctionItemRow['auction_item_image_elements'] = "<a class='auction-item-image pretty-photo' id='auction_item_image_" . $imageCount++ . "' href='" . $auctionItemRow["image_url"] . "'><img alt='Auction Item Image' src='" . $auctionItemRow["image_url"] . "'></a>";
			}
			$thumbnailAuctionItemVideos = array();
			$playlistAuctionItemVideos = array();
			$resultSet = executeQuery("select *, auction_item_videos.description auction_item_video_description from auction_item_videos join media using (media_id) where auction_item_id = ?", $auctionItemRow['auction_item_id']);
			while ($row = getNextRow($resultSet)) {
				if ($row['show_as_playlist']) {
					$playlistAuctionItemVideos[] = $row;
				} else {
					$thumbnailAuctionItemVideos[] = $row;
				}
			}

			if (count($auctionItemRow['auction_item_images']) > 0 || count($thumbnailAuctionItemVideos) > 0) {
				$thumbnailFragment = getFragment("AUCTION_ITEM_ALTERNATE_IMAGE");
				if (empty($thumbnailFragment)) {
					$thumbnailFragment = "<div class='image-thumbnail' data-image_code='%image_code%' data-image_url='%image_url%'>" .
						"<a data-ignore_click='true' rel='prettyPhoto[all_images]' href='%image_url%'><img alt='Auction Item Image' src='%thumbnail_image_url%'></a></div>";

					"<a rel='prettyPhoto[all_images]' href='" . $auctionItemRow['image_url'] . "'><img alt='Auction Item Image' src='" . $auctionItemRow[($thumbnailExists ? "thumbnail_" : "small_") . "image_url"] . "'></a></div>";

				}
				$auctionItemRow['auction_item_image_thumbnails'] = str_replace("%image_code%", "", str_replace("%image_url%", $auctionItemRow['image_url'], str_replace("%thumbnail_image_url%", $auctionItemRow['image_url'], $thumbnailFragment)));
				foreach ($auctionItemRow['auction_item_images'] as $index => $thisImageUrl) {
					if ($thisImageUrl['image_url'] == $auctionItemRow['image_url']) {
						continue;
					}
					$auctionItemRow['auction_item_image_thumbnails'] .= str_replace("%image_code%", "", str_replace("%image_url%", $thisImageUrl['image_url'], str_replace("%thumbnail_image_url%", $thisImageUrl['image_urls'][($thumbnailExists ? "thumbnail" : "small")], $thumbnailFragment)));
					$auctionItemRow['auction_item_image_elements'] .= "<a class='auction-item-image pretty-photo' rel='prettyPhoto[all_images]' id='auction_item_image_" . $imageCount++ . "' href='" . $thisImageUrl['url'] . "'><img title='Auction Item Image' src='" . $thisImageUrl['image_urls']['thumbnail'] . "'></a>";
				}
				foreach ($thumbnailAuctionItemVideos as $thisVideo) {
					$mediaServicesRow = getRowFromId("media_services", "media_service_id", $thisVideo['media_service_id']);
					$videoLink = "//" . $mediaServicesRow['link_url'] . $thisVideo['video_identifier'];
					$auctionItemRow['auction_item_image_thumbnails'] .= "<div class='image-thumbnail auction-item-video' data-image_code='' data-video_link='" . $videoLink . "'>" .
						(empty($thisVideo['image_id']) ? "<span alt='Auction Item Video' class='fab fa-youtube auction-item-video-icon'></span>" : "<img alt='Auction Item Video' src='" . getImageFilename($thisVideo['image_id'], array("image_type" => ($thumbnailExists ? "thumbnail" : "small"))) . "'>") . "</div>";
				}
			}

			if (count($playlistAuctionItemVideos) > 0) {
				$auctionItemRow['related_videos'] = "<div id='related_auction_item_videos_container'>
                    <div id='related_auction_item_video_iframe_container'>
                        <iframe allow='autoplay; encrypted-media' allowfullscreen></iframe>
                        <h2 class='auction-item-video-description'></h2>
                    </div>
                    <div id='related_auction_item_videos_list'>";

				foreach ($playlistAuctionItemVideos as $thisVideo) {
					$mediaServicesRow = getRowFromId("media_services", "media_service_id", $thisVideo['media_service_id']);
					$auctionItemRow['related_videos'] .= "<div class='related-auction-item-video'
                        data-video_identifier='" . $thisVideo['video_identifier'] . "'
                        data-media_services_link_url='" . $mediaServicesRow['link_url'] . "';
                        data-media_service_code='" . $mediaServicesRow['media_service_code'] . "'
                        data-description='" . $thisVideo['auction_item_video_description'] . "'>
                        <div class='auction-item-video-thumbnail'>
                            <img title='" . $thisVideo['auction_item_video_description'] . "' src='" . getImageFilename($thisVideo['image_id']) . "' alt='" . $thisVideo['auction_item_video_description'] . "' />
                            <i class='fa fa-play-circle center-div' aria-hidden='true'></i>
                        </div>
                        <div class='auction-item-video-content'>
                            <h3><a class='auction-item-video-link' href='" . $GLOBALS['gLinkUrl'] . "?type=media_id&amp;media_id=" . $thisVideo['media_id'] . "'>" . $thisVideo['auction_item_video_description'] . "</a></h3>"
						. (empty($thisVideo['subtitle']) ? "" : "<p class='content-subtitle'>" . htmlText($thisVideo['subtitle']) . "</p>")
						. (empty($thisVideo['full_name']) ? "" : "<p class='content-author'>" . htmlText($thisVideo['full_name']) . "</p>")
						. (empty($thisVideo['detailed_description']) ? "" : "<p class='content-detailed-description'>" . htmlText($thisVideo['detailed_description']) . "</p>")
						. (empty($thisVideo['date_created']) ? "" : "<p class='content-date-created'>" . date("m/d/Y", strtotime($row['date_created'])) . "</p>")
						. "</div>
                    </div>";
				}
				$auctionItemRow['related_videos'] .= "</div></div>";
			}

			$auctionItemRow['other_auction_specification_elements'] = "";
			$resultSet = executeQuery("select auction_specifications.auction_specification_code, auction_specifications.description auction_specification_description, auction_item_specifications.field_value"
				. " from auction_item_specifications join auction_specifications using (auction_specification_id)"
				. " where auction_item_specifications.auction_item_id = ?", $auctionItemRow['auction_item_id']);
			while ($specificationRow = getNextRow($resultSet)) {
				$auctionSpecificationCode = 'auction_specification_' . makeCode($specificationRow['auction_specification_code'], array("lowercase" => true));
				$auctionItemRow[$auctionSpecificationCode] = $specificationRow['field_value'];

				$primarySpecifications = array("upc_code", "manufacturer_sku", "model", "condition", "manufacturer");
				if (!in_array(strtolower($specificationRow['auction_specification_code']), $primarySpecifications)) {
					$auctionItemRow['other_auction_specification_elements'] .= "<p class='auction-specification'>" . $specificationRow['auction_specification_description'] . ": " . $specificationRow['field_value'] . "</p>";
				}
			}

			ob_start();
			?>
            <div id="auction_item_detail_product_tags">
				<?php
				$resultSet = executeReadQuery("select * from product_tags where client_id = ? and display_color is not null and internal_use_only = 0 and " .
					"inactive = 0 and product_tag_id in (select product_tag_id from auction_item_product_tag_links where auction_item_id = ?) order by sort_order,description", $GLOBALS['gClientId'], $this->iAuctionItemId);
				while ($row = getNextRow($resultSet)) {
					?>
                    <div class='auction-item-detail-product-tag auction-item-detail-product-tag-<?= strtolower(str_replace("_", "-", $row['product_tag_code'])) ?>'><?= htmlText($row['description']) ?></div>
					<?php
				}
				?>
            </div>
			<?php
			$auctionItemRow['product_tags'] = ob_get_clean();

			$this->iAuctionItemRow = $auctionItemRow;
		}
	}

	function hiddenElements() {
		ob_start();
		$makeOfferText = getFragment("make_offer_text");
		$minimumOffer = $this->iAuctionItemRow['auto_reject_price'];
		if (empty($minimumOffer)) {
			$minimumOffer = 1;
		}
		?>
        <div id="_make_offer_dialog" class="dialog-box">
			<?= makeHtml($makeOfferText) ?>
            <p class='error-message'></p>
            <form id="_make_offer_form">
                <input type="hidden" id="make_offer_auction_item_id" name="auction_item_id">
                <div class="form-line" id="_amount_row">
                    <label>Your Offer Amount</label>
                    <input tabindex="10" type="text" size="12" class="align-right validate[custom[number],min[<?= $minimumOffer ?>]]" data-decimal-places="2" id="amount" name="amount">
                    <div class='clear-div'></div>
                </div>
            </form>
        </div>

		<?php

		$buyNowText = getFragment("buy_now_text");
		?>

        <div id="_buy_now_dialog" class="dialog-box">
			<?= makeHtml($buyNowText) ?>
            <p class='error-message'></p>
            <form id="_buy_now_form">
                <div class="form-line" id="_amount_row">
                    <label>Are you sure you want to buy this item for $<?= $this->iAuctionItemRow['buy_now_price'] ?>?</label>
                </div>
            </form>
        </div>

		<?php
		return ob_get_clean();
	}

	function javascript() {
		ob_start();
		return ob_get_clean();
	}

	function inlineJavascript() {
		ob_start();
		return ob_get_clean();
	}

	function onLoadJavascript() {
		if (empty($this->iAuctionItemRow)) {
			$this->loadAuctionItemRow();
		}
		ob_start();

		?>
        <script>
            $(document).on("click", "#_buy_now_button", function() {
                let auctionItemId = $(this).data('auction_item_id');
                $("#_buy_now_dialog").dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: true,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Buy Now?',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("/auction-controller?ajax=true&url_action=get_buy_now_link&auction_item_id=" + auctionItemId, function (returnArray) {
                                $("#_buy_now_dialog").dialog('close');
                                if (!("error_message" in returnArray)) {
                                    goToLink(returnArray['buy_now_link']);
                                }
                            });
                        },
                        Cancel: function (event) {
                            $("#_buy_now_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", "#_make_offer_button", function () {
                $("#make_offer_auction_item_id").val($("#auction_item_id").val());
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
                                loadAjaxRequest("/auction-controller?url_action=make_offer", $("#_make_offer_form").serialize(), function(returnArray) {
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

            $(document).on("click", ".image-thumbnail", function () {
                $("#_auction_item_primary_image").find("img.addon-image").remove();
                $("#_auction_item_wrapper").removeClass("addon-builder");
                if ($(this).hasClass("auction-item-video")) {
                    if ($("#_auction_item_image_wrapper").find(".media-player").length == 0) {
                        const mediaPlayer = "<div class='media-player'><div class='embed-container'><iframe src='' allow='encrypted-media' frameborder='0' allowfullscreen></iframe></div></div>";
                        $("#_auction_item_primary_image").append(mediaPlayer);
                    }
                    $("#_auction_item_image").addClass("hidden");
                    $("#_auction_item_image_wrapper").find(".media-player").find("iframe").attr("src", $(this).data("video_link"));
                    $("#_auction_item_image_wrapper").find(".media-player").removeClass("hidden");
                } else {
                    const imageUrl = $(this).data("image_url");
                    const imageCode = $(this).data("image_code");
                    $("#_auction_item_image").removeClass("hidden");
                    $("#_auction_item_image").attr("href", imageUrl);
                    $("#_auction_item_image img").attr("src", imageUrl);
                    $("#_auction_item_image").data("image_code", imageCode);
                    $("#_auction_item_image_wrapper").find(".media-player").addClass("hidden");
                }
                return false;
            });
            $("#_auction_item_details_wrapper").find("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({
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
		$pageId = getReadFieldFromId("page_id", "pages", "script_filename", "auction/auctionitemdetails.php", "template_id = ?", $GLOBALS['gPageRow']['template_id']);
		if (empty($pageId)) {
			$pageId = getReadFieldFromId("page_id", "pages", "script_filename", "auction/auctionitemdetails.php");
		}
		return "<style>" . getReadFieldFromId("javascript_code", "pages", "page_id", $pageId) . "</style>";
	}
}
