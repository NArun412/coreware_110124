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

/*
%module:recently_viewed:wrapper_element_id=element_id%

Options:
select_limit=8 - limit to this number of products. Default is 8
exclude_out_of_stock=true - Exclude products that are out of stock
no_style=true - don't include css styling
*/

class RecentlyViewedPageModule extends PageModule {
	function createContent() {
		$shoppingCart = ShoppingCart::getShoppingCart("RETAIL");
		$contactId = $shoppingCart->getContact();
		if (empty($contactId)) {
			return;
		}
		$wrapperElementId = $this->iParameters['wrapper_element_id'];
		if (empty($wrapperElementId)) {
			$wrapperElementId = "recently_viewed_products";
		}
		$selectLimit = $this->iParameters['select_limit'];
		if (empty($selectLimit)) {
			$selectLimit = 8;
		}
		$excludeOutOfStock = ($this->iParameters['exclude_out_of_stock'] == "true" ? "1" : "");
		$productIds = array();
		$resultSet = executeQuery("select * from product_view_log where contact_id = ? and product_id in (select product_id from products where inactive = 0 and internal_use_only = 0 and (expiration_date is null or expiration_date >= current_date))" .
			($excludeOutOfStock ? " and (product_id in (select product_id from products where non_inventory_item = 1) or " .
				"product_id in (select product_id from product_inventories where quantity > 0 and location_id not in (select location_id from locations where inactive = 1 or internal_use_only = 1)))" : "") .
			" order by log_time desc", $contactId);
		while ($row = getNextRow($resultSet)) {
			$productIds[] = $row['product_id'];
			if (count($productIds) >= $selectLimit) {
				break;
			}
		}
		if (empty($productIds)) {
			return;
		}
		$functionName = "tag" . getRandomString(12);
		if (empty($this->iParameters['no_style'])) {
			?>
            <style>
                <?= "#" . $wrapperElementId ?>
                .catalog-item .info-label {
                    font-size: 90%;
                    margin-right: 10px;
                }
                <?= "#" . $wrapperElementId ?>
                .catalog-item img {
                    max-width: 100%;
                }

                <?= "#" . $wrapperElementId ?>
                .click-product-detail {
                    cursor: pointer;
                }
                <?= "#" . $wrapperElementId ?>
                .click-product-detail a:hover {
                    color: rgb(140, 140, 140);
                }
                <?= "#" . $wrapperElementId ?>
                .catalog-item-description {
                    font-size: 1.1rem;
                    text-align: center;
                    font-weight: 700;
                    height: 110px;
                    overflow: hidden;
                    position: relative;
                }
                <?= "#" . $wrapperElementId ?>
                .catalog-item-description:after {
                    content: "";
                    position: absolute;
                    top: 90px;
                    left: 0;
                    height: 20px;
                    width: 100%;
                    background: linear-gradient(rgba(255, 255, 255, 0), rgb(255, 255, 255));
                }

                <?= "#" . $wrapperElementId ?>
                .catalog-item-detailed-description {
                    font-size: .8rem;
                    margin-bottom: 10px;
                    height: 100px;
                    overflow: hidden;
                    position: relative;
                }
                <?= "#" . $wrapperElementId ?>
                .catalog-item-detailed-description:after {
                    content: "";
                    position: absolute;
                    top: 60px;
                    left: 0;
                    height: 40px;
                    width: 100%;
                    background: linear-gradient(rgba(255, 255, 255, 0), rgb(255, 255, 255));
                }
                <?= "#" . $wrapperElementId ?>
                .catalog-item-price-wrapper {
                    font-size: 1.5rem;
                    font-weight: 700;
                    margin-bottom: 20px;
                    margin-top: 10px;
                    text-align: center;
                }
                <?= "#" . $wrapperElementId ?>
                .catalog-item-thumbnail {
                    text-align: center;
                    margin-bottom: 10px;
                    height: 120px;
                    position: relative;
                }
                <?= "#" . $wrapperElementId ?>
                .catalog-item-thumbnail img {
                    max-height: 100%;
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    cursor: zoom-in;
                }
                <?= "#" . $wrapperElementId ?>
                .catalog-item-out-of-stock {
                    padding: 5px;
                    text-align: center;
                }
                <?= "#" . $wrapperElementId ?>
                .catalog-item-add-to-cart {
                    padding: 5px;
                    text-align: center;
                }
                <?= "#" . $wrapperElementId ?>
                .catalog-item-add-to-wishlist {
                    padding: 5px;
                    text-align: center;
                }
                <?= "#" . $wrapperElementId ?>
                .button-subtext {
                    display: none;
                }
                <?= "#" . $wrapperElementId ?>
                .map-priced-product .button-subtext {
                    display: inline;
                }
                <?= "#" . $wrapperElementId ?>
                .out-of-stock-product .button-subtext {
                    display: inline;
                    white-space: pre-line;
                }
                <?= "#" . $wrapperElementId ?>
                .catalog-item-out-of-stock {
                    display: none;
                }
                <?= "#" . $wrapperElementId ?>
                .out-of-stock-product .catalog-item-out-of-stock {
                    display: block;
                }
                <?= "#" . $wrapperElementId ?>
                .out-of-stock-product .catalog-item-add-to-cart {
                    display: none;
                }
            </style>
			<?php
		}
		?>
        <script>
            taggedProductsFunctions.push("<?= $functionName ?>");
            function <?= $functionName ?>() {
                $("body").addClass("no-waiting-for-ajax");
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_products&url_source=recently_viewed", { specific_product_ids: "<?= implode(",", $productIds) ?>", exclude_out_of_stock: "<?= $excludeOutOfStock ?>" }, function(returnArray) {
                    if ("product_results" in returnArray) {
                        if (returnArray['result_count'] > 0) {
                            manufacturerNames = returnArray['manufacturer_names'];
                            productFieldNames = returnArray['product_field_names'];
                            productResults = returnArray['product_results'];
                            resultCount = returnArray['result_count'];
                            shoppingCartProductIds = returnArray['shopping_cart_product_ids'];
                            wishListProductIds = returnArray['wishlist_product_ids'];
                            emptyImageFilename = returnArray['empty_image_filename'];
                            inStorePurchaseOnlyText = "<?= (getFragment("IN_STORE_PURCHASE_ONLY_TEXT") ?: "In-store purchase only") ?>";

                            displaySearchResults("<?= $wrapperElementId ?>", null, function () {
                                checkForTaggedProductFunctions();
                            });
                        } else {
                            checkForTaggedProductFunctions();
                        }
                    }
                });
            }
        </script>
		<?php
	}
}
