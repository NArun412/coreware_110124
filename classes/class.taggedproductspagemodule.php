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
%module:tagged_products:product_tag_code=used_product_tag_code|multiple_tag_code:wrapper_element_id=element_id%

Options:
select_limit=8 - limit to this number of products
product_department_code=xxxxx - limit to products also in this department
product_category_group_code=xxxxx - limit to products also in this category group
product_category_code=xxxxx - limit to products also in this category
product_manufacturer_code=xxxxx - limit to products also in this manufacturer
include_out_of_stock=true - include products that are out of stock
random=true
no_style=true
fragment_code=XXXXXX - fragment to use for the product results. Default is just the normal catalog results HTML
*/

class TaggedProductsPageModule extends PageModule {
	function massageParameters() {
		if ($GLOBALS['gLoggedIn']) {
			$this->iParameters['no_cache'] = true;
		}
	}

	function createContent() {
		$productTagValid = true;
		if (strstr($this->iParameters['product_tag_code'], "|")) {
			$productTagCodes = explode("|", $this->iParameters['product_tag_code']);
			foreach ($productTagCodes as $productTagCode) {
				$productTagCode = getFieldFromId("product_tag_code", "product_tags", "product_tag_code", $productTagCode,
					"internal_use_only = 0 and inactive = 0 and client_id = " . $GLOBALS['gClientId']);
				if (empty($productTagCode)) {
					$productTagValid = false;
					break;
				}
			}
		} else {
			$productTagCode = getFieldFromId("product_tag_code", "product_tags", "product_tag_code", $this->iParameters['product_tag_code'],
				"internal_use_only = 0 and inactive = 0 and client_id = " . $GLOBALS['gClientId']);
			if (empty($productTagCode) && is_numeric($this->iParameters['product_tag_id'])) {
				$productTagCode = getFieldFromId("product_tag_code", "product_tags", "product_tag_id", $this->iParameters['product_tag_id'],
					"internal_use_only = 0 and inactive = 0 and client_id = " . $GLOBALS['gClientId']);
			}
			$productTagValid = !empty($productTagCode);
		}
		$wrapperElementId = $this->iParameters['wrapper_element_id'];
		$selectLimit = $this->iParameters['select_limit'];
		$productManufacturerCode = $this->iParameters['product_manufacturer_code'];
		$productCategoryCode = $this->iParameters['product_category_code'];
		$productDepartmentCode = $this->iParameters['product_department_code'];
		$productCategoryGroupCode = $this->iParameters['product_category_group_code'];
		$excludeOutOfStock = ($this->iParameters['include_out_of_stock'] == "true" ? "" : "1");
		$randomOrder = ($this->iParameters['random'] == "true" ? "1" : "");
		$fragmentId = getFieldFromId("fragment_id", "fragments", "fragment_code", $this->iParameters['fragment_code']);
		if (!$productTagValid) {
			?>
            Invalid product tag <span class='hidden'><?= jsonEncode($this->iParameters) ?></span>
			<?php
		} else if (empty($wrapperElementId)) {
			?>
            Invalid wrapper Element ID
			<?php
		} else {
			if (!empty($fragmentId)) {
				?>
                <div id='_tagged_products_result_fragment_<?= $fragmentId ?>' class='hidden'>
					<?= getFieldFromId("content", "fragments", "fragment_id", $fragmentId) ?>
                </div>
				<?php
			}
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
				<?php if (!$GLOBALS['gLoggedIn']) { ?>
                    <style id="not_logged_in_wish_list_styles">
                        <?= "#" . $wrapperElementId ?>
                        .add-to-wishlist {
                            display: none;
                        }
                    </style>
				<?php } ?>
			<?php } ?>
            <script>
				<?php
				$functionName = "tag" . getRandomString(12);
				?>
                if ($("#catalog_result_product_tags_template").length == 0) {
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_product_tag_html", function(returnArray) {
                        if ($("#catalog_result_product_tags_template").length == 0 && "catalog_result_product_tags_template" in returnArray) {
                            $("#_templates").append(returnArray['catalog_result_product_tags_template']);
                        }
                    });
                }
                taggedProductsFunctions.push("<?= $functionName ?>");
                function <?= $functionName ?>() {
                    $("body").addClass("no-waiting-for-ajax");
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_products&url_source=tagged_products", {
                        product_tag_code: "<?= $this->iParameters['product_tag_code'] ?>", select_limit: "<?= $selectLimit ?>", exclude_out_of_stock: "<?= $excludeOutOfStock ?>", sort_by: "<?= (empty($randomOrder) ? "tagged_order" : "random") ?>",
                        product_department_code: "<?= $productDepartmentCode ?>", product_category_group_code: "<?= $productCategoryGroupCode ?>", product_category_code: "<?= $productCategoryCode ?>", product_manufacturer_code: "<?= $productManufacturerCode ?>"<?= ($this->iParameters['no_cache'] ? ", no_cache: true" : "") ?>
                    }, function(returnArray) {
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
                                    if ($("#_create_user_dialog").length > 0 && $("#not_logged_in_wish_list").length > 0) {
                                        $("#not_logged_in_wish_list_styles").remove();
                                    }
                                }
								<?= (empty($fragmentId) ? "" : ",'_tagged_products_result_fragment_" . $fragmentId . "'") ?>
                            )
                                ;
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
}
