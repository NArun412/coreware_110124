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
%module:recommended_products:wrapper_element_id=element_id%

Options:
select_limit=8 - limit to this number of products
exclude_out_of_stock=true - Exclude products that are out of stock
prefer_personal=true - use personal recommendations instead of contextual (related products)
random=true
no_style=true
*/

class RecommendedProductsPageModule extends PageModule {
	function createContent() {
		$zaiusApiKey = getPreference("ZAIUS_API_KEY");
		$zaiusUseUpc = !empty(getPreference("ZAIUS_USE_UPC"));
		$zaiusProductId = false;
		if (!empty($_GET['product_id'])) {
			$zaiusProductId = $zaiusUseUpc ? getFieldFromId("upc_code", "product_data", "product_id", $_GET['product_id'])
				: getFieldFromId("product_id", "products", "product_id", $_GET['product_id']);
		}
		if (!empty($zaiusApiKey)) {
			if ($GLOBALS['gLoggedIn']) {
				$email = $GLOBALS['gUserRow']['email_address'];
				$data = getCachedData("zaius_recommended_products", $email . ":" . $zaiusProductId);
				if (empty($data)) {
					$zaiusObject = new Zaius($zaiusApiKey);
					$queryParams = array("email" => $email);
					if (!empty($zaiusProductId)) {
						$queryParams["product_ids"] = $zaiusProductId;
						if ($this->iParameters['prefer_personal'] !== "true") {
							$queryParams['type'] = 'contextual';
						}
					}
					$result = $zaiusObject->getApi("recommendations/products", $queryParams);
					if (!empty($result)) {
						$data = json_decode($result, true, 512, JSON_BIGINT_AS_STRING);
					}
					setCachedData("zaius_recommended_products", $email . ":" . $zaiusProductId, $data);
				}
			} else { // if not logged in, get Zaius bestsellers
				$data = getCachedData("zaius_recommended_products", ($zaiusProductId ?: "best_sellers"));
				if (empty($data)) {
					$zaiusObject = new Zaius($zaiusApiKey);
					$queryParams = array();
					if (!empty($zaiusProductId)) {
						$queryParams["product_ids"] = $zaiusProductId;
						$queryParams['type'] = 'contextual';
					}
					$result = $zaiusObject->getApi("recommendations/products", $queryParams);
					if (!empty($result)) {
						$data = json_decode($result, true, 512, JSON_BIGINT_AS_STRING);
					}
					setCachedData("zaius_recommended_products", "best_sellers", ($zaiusProductId ?: "best_sellers"));
				}
			}
			$productIds = array();
			foreach ($data as $datum) {
				if ($zaiusUseUpc) {
					$productId = getFieldFromId('product_id', 'product_data', 'upc_code', $datum['product_id']);
				} else {
					$productId = getFieldFromId('product_id', 'products', 'product_id', $datum['product_id']);;
				}
				$productIds[] = $productId;
			}
		}
		$wrapperElementId = $this->iParameters['wrapper_element_id'];
		$selectLimit = $this->iParameters['select_limit'];
		$excludeOutOfStock = ($this->iParameters['exclude_out_of_stock'] == "true" ? "1" : "");
		$randomOrder = ($this->iParameters['random'] == "true" ? "1" : "");
		if (empty($productIds)) {
			?>
            No recommended products found.<span class='hidden'><?= jsonEncode($this->iParameters) ?></span>
			<?php
		} else if (empty($wrapperElementId)) {
			?>
            Invalid wrapper Element ID
			<?php
		} else {
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
                taggedProductsFunctions.push("<?= $functionName ?>");
                function <?= $functionName ?>() {
                    $("body").addClass("no-waiting-for-ajax");
                    loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_products&url_source=recommended_products", { specific_product_ids: "<?= implode(",", $productIds) ?>", select_limit: "<?= $selectLimit ?>", exclude_out_of_stock: "<?= $excludeOutOfStock ?>" <?= (empty($randomOrder) ? ', sort_by: "random"' : '') ?> }, function(returnArray) {
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
}
