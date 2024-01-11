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

$GLOBALS['gPageCode'] = "RETAILSTOREPURCHASECUSTOMPRODUCT";
require_once "shared/startup.inc";

class PurchaseCustomProductPage extends Page {

	var $iProductRow = array();

	function setup() {
		$productCode = $this->getPageTextChunk("product_code");
		$this->iProductRow = getRowFromId("products", "product_code", $productCode, "custom_product = 1 and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
		if (empty($this->iProductRow)) {
			header("Location: /");
			exit;
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_product":
				$shoppingCart = ShoppingCart::getShoppingCart("RETAIL");
				$amount = $_POST['sale_price'];
				if ($amount <= 0) {
					$returnArray['error_message'] = "Invalid Amount";
					ajaxResponse($returnArray);
					break;
				}
				$quantity = 1;
				$productId = $this->iProductRow['product_id'];
				$shoppingCart->removeProduct($productId);
				$shoppingCart->addItem(array("product_id" => $productId, "quantity" => $quantity, "set_quantity" => true, "sale_price" => $amount));
				$returnArray['shopping_cart_item_count'] = $shoppingCart->getShoppingCartItemsCount();
				$returnArray['product_id'] = $productId;
				$returnArray['response'] = $this->getPageTextChunk("response");
				if (empty($returnArray['response'])) {
					$returnArray['response'] = $this->iProductRow['description'] . " has been added to your shopping cart for the amount of $" . number_format($amount, 2, ".", ",") . ".";
				} else {
					$returnArray['response'] = str_replace("%amount%", number_format($amount, 2, ".", ","), $returnArray['response']);
				}

				ajaxResponse($returnArray);

				break;
		}
	}

	function mainContent() {
		$clientContent = $this->iPageData['content'];
		$minimumAmount = $this->getPageTextChunk("minimum_amount");
		if (empty($minimumAmount) || !is_numeric($minimumAmount) || $minimumAmount < 0) {
			$minimumAmount = 0;
		}
		$maximumAmount = $this->getPageTextChunk("maximum_amount");
		if (empty($maximumAmount) || !is_numeric($maximumAmount) || $maximumAmount < 0) {
			$maximumAmount = 0;
		}
		if (empty($clientContent)) {
			ob_start();
			?>
            <div class="margin-div">
                <div id="form_wrapper">
                    <h2>Purchase <?= htmlText($this->iProductRow['description']) ?></h2>
					<?= makeHtml($this->iProductRow['detailed_description']) ?>
                    <p class="error-message" id="error_message"></p>

                    <form id="_edit_form">
                        <div class="form-line">
                            <label>Amount</label>
                            <input type="text" id="sale_price" name="sale_price" class="validate[required,custom[number],min[<?= $minimumAmount ?>]<?= (!empty($maximumAmount) ? ",max[]" : "") ?>]" data-decimal-places="2">
                            <div class='clear-div'></div>
                        </div>

                        <p>
                            <button id="create_product">Add to Cart</button>
                        </p>
                    </form>
                </div>

            </div>
			<?php
			$clientContent = ob_get_clean();
		}
		echo $clientContent;
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#create_product").click(function () {
                if (!$(this).validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_product", $("#_edit_form").serialize(), function(returnArray) {
                        if ("response" in returnArray) {
                            $("#form_wrapper").html(returnArray['response']);
                        }
                        if ("shopping_cart_item_count" in returnArray) {
                            $(".shopping-cart-item-count").html(returnArray['shopping_cart_item_count']);
                            if (typeof afterAddToCart == "function") {
                                setTimeout(function () {
                                    afterAddToCart(returnArray['product_id'], 1);
                                }, 100);
                            }
                        }
                    });
                }
                return false;
            });
        </script>
		<?php
	}
}

$pageObject = new PurchaseCustomProductPage();
$pageObject->displayPage();
