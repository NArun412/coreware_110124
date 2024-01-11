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

$GLOBALS['gPageCode'] = "RETAILSTOREWISHLIST";
$GLOBALS['gCacheProhibited'] = true;
require_once "shared/startup.inc";

class ThisPage extends Page {

	private $iWishListId = false;
	private $iMyWishListId = false;

	function setup() {
		if (!empty($_GET['id'])) {
			$this->iWishListId = getFieldFromId("wish_list_id", "wish_lists", "wish_list_id", $_GET['id'], "user_id in (select user_id from users where client_id = ?)", $GLOBALS['gClientId']);
		}
		if ($GLOBALS['gLoggedIn'] && empty($this->iWishListId)) {
			executeQuery("delete from wish_list_items where wish_list_id in (select wish_list_id from wish_lists where user_id = ?) and product_id in (select product_id from products where inactive = 1 or internal_use_only = 1)", $GLOBALS['gUserId']);
			$this->iMyWishListId = getFieldFromId("wish_list_id", "wish_lists", "user_id", $GLOBALS['gUserId']);
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(function () {
				<?php if ($GLOBALS['gLoggedIn'] && empty($this->iWishListId)) { ?>
                $(document).on("click", ".wish-list-item .clickable", function () {
                    var productId = $(this).closest(".wish-list-item").data("product_id");
                    if (!empty(productId)) {
                        document.location = "/product-details?id=" + productId;
                    }
                    return false;
                });
                $(document).on("click", ".notify-when-in-stock", function () {
                    var productId = $(this).closest("tr").data("product_id");
                    setWishListItemNotify(productId, $(this).prop("checked"));
                });
                $(document).on("click", ".remove-item", function () {
                    var productId = $(this).data("product_id");
                    removeProductFromWishList(productId);
                    $(this).closest("tr").remove();
                });
				<?php } ?>
                getWishListItems(<?= $this->iWishListId ?>);
            });
        </script>
		<?php
	}

	function wishList() {
		$wishlistWrapper = $this->getFragment("WISH_LIST_ITEM_WRAPPER");
		$displayName = false;
        if (!empty($this->iWishListId)) {
            $userId = getFieldFromId("user_id","wish_lists","wish_list_id",$this->iWishListId);
			$contactId = getFieldFromId("contact_id", "users", "user_id", $userId);
			if (!empty($contactId)) {
				$displayName = getFieldFromId("first_name", "contacts", "contact_id", $contactId);
				if (empty($displayName)) {
					$displayName = "Your friend";
				}
			}
        }
		if (empty($wishlistWrapper)) {
			ob_start();
			?>
			<?php if (!empty($this->iMyWishListId)) { ?>
                <p>To share your wishlist, copy and use this link: <a href='<?= getDomainName() ?>/<?= $GLOBALS['gLinkUrl'] ?>?id=<?= $this->iMyWishListId ?>'><?= getDomainName() ?>/<?= $GLOBALS['gLinkUrl'] ?>?id=<?= $this->iMyWishListId ?></a></p>
			<?php } elseif ($displayName !== false) { ?>
                <h3><? $displayName ?>'s Wish List</h3>
			<?php } ?>
            <table id="_wishlist_items">
                <thead>
                <tr>
                    <th></th>
                    <th>Description</th>
					<?php if ($GLOBALS['gLoggedIn'] && empty($this->iWishListId)) { ?>
                        <th>Notify When<br>in Stock</th>
					<?php } ?>
                    <th>Price</th>
                    <th>&nbsp;</th>
                </tr>
                </thead>
                <tbody id="wish_list_items_wrapper">
                </tbody>
            </table>
			<?php
			$wishlistWrapper = ob_get_clean();
		}
		echo $wishlistWrapper;
	}

	function jqueryTemplates() {
		$wishListItemFragment = $this->getFragment("WISH_LIST_ITEM");
		if (empty($wishListItemFragment)) {
			ob_start();
			?>
            <table class="hidden" id="wishlist_item_template">
                <tbody id="_wish_list_item_block">
                <tr class="wish-list-item %other_classes%" id="wish_list_item_id_%wish_list_item_id%" data-product_id="%product_id%">
                    <td class="clickable align-center"><img %image_src%="%small_image_url%"></td>
                    <td class="clickable">%description%<span class="out-of-stock-notice">Out of Stock</span><span class="in-stock-notice">In Stock</span><span class="no-online-order-notice">In-store purchase only</span></td>
					<?php if ($GLOBALS['gLoggedIn'] && empty($this->iWishListId)) { ?>
                        <td class="align-center"><input type="checkbox" class='notify-when-in-stock' name="notify_when_in_stock_%wish_list_item_id%" id="notify_when_in_stock_%wish_list_item_id%" value="1"></td>
					<?php } ?>
                    <td class="align-right">%sale_price%</td>
                    <td class="controls align-center"><?php if ($GLOBALS['gLoggedIn'] && empty($this->iWishListId)) { ?><span class="fa fa-times remove-item" data-product_id="%product_id%"></span><?php } ?><span class="fas fa-shopping-cart add-to-cart" data-product_id="%product_id%"></span></td>
                </tr>
                </tbody>
            </table>
			<?php
			$wishListItemFragment = ob_get_clean();
		}
		echo $wishListItemFragment;
	}

	function internalCSS() {
		?>
        <style>
            table#_wishlist_items {
                width: 100%;
                background-color: rgb(255, 255, 255);
                margin: 20px auto;
            }

            table#_wishlist_items thead th {
                background: rgb(180, 180, 180);
                font-size: .9rem;
                font-weight: 400;
                line-height: 1.2;
                color: rgb(0, 0, 0);
                white-space: nowrap;
                vertical-align: middle;
            }

            table#_wishlist_items tbody tr {
                border: .5px solid #ccc;
            }

            table#_wishlist_items tbody td {
                padding: 20px;
                vertical-align: middle;
                line-height: 1.2;
            }

            table#_wishlist_items td img {
                max-width: 250px;
                max-height: 80px;
            }

            table#_wishlist_items td.controls {
                white-space: nowrap;
            }

            table#_wishlist_items td.controls span {
                font-size: 1.4rem;
                color: rgb(0, 0, 0);
                display: inline-block;
                margin: 0 10px;
                cursor: pointer;
            }

            table#_wishlist_items td.controls span:hover {
                color: rgb(180, 190, 200);
            }

            .in-stock-notice {
                display: block;
                color: rgb(0, 192, 0);
                font-weight: 900;
                margin-top: 5px;
            }

            .out-of-stock-notice {
                display: none;
            }

            .out-of-stock .out-of-stock-notice {
                display: block;
                color: rgb(192, 0, 0);
                font-weight: 900;
                margin-top: 5px;
            }

            .out-of-stock .in-stock-notice {
                display: none;
            }

            .no-online-order-notice {
                display: none;
            }

            .no-online-order .no-online-order-notice {
                display: block;
                color: rgb(192, 0, 0);
                font-weight: 900;
                margin-top: 5px;
            }

            @media (max-width: 800px) {
                table#_wishlist_items td img {
                    max-width: 150px;
                }

                table#_wishlist_items thead th {
                    font-size: 1.0em;
                    line-height: 20px;
                }
            }

            @media (max-width: 625px) {
                table#_wishlist_items tbody td {
                    padding: 5px;
                }

                table#_wishlist_items td img {
                    max-width: 40px;
                    padding: 2px;
                }

                table#_wishlist_items td {
                    padding: 1.5px;
                    font-size: .9em;
                    line-height: 1.3;
                }

                table#_wishlist_items thead th {
                    font-size: .9em;
                }

                table#_wishlist_items thead th {
                    line-height: 20px;
                }

                table#_wishlist_items td.product-quantity-wrapper span {
                    margin: 0 3px;
                }

                span.product-quantity {
                    padding: 5px 10px;
                }

                table#_wishlist_items td.product-description {
                    max-width: 80px;
                    overflow-x: scroll;
                    font-size: .8em;
                }
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
