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

$GLOBALS['gPageCode'] = "RETAILSTOREPURCHASEGIFTCARD";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function mainContent() {
		$clientContent = $this->iPageData['content'];
		if (empty($clientContent)) {
			ob_start();
			?>
            <div class="margin-div">
                <div id="form_wrapper">
                    <h2>Purchase Gift Card</h2>
                    <p>Add a gift card with the specified amount to your shopping cart. At checkout time, you will be able to personalize it.</p>
                    <p class="error-message" id="error_message"></p>

                    <form id="_edit_form">
                        <div class="form-line">
                            <label>Gift Card Amount</label>
                            <input type="text" id="gift_card_amount" name="gift_card_amount" class="validate[required,custom[number]]" data-decimal-places="2">
                            <div class='clear-div'></div>
                        </div>

                        <p>
                            <button id="create_gift_card">Add to Cart</button>
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
            $(function () {
                $("#create_gift_card").click(function () {
                    if (!$(this).validationEngine("validate")) {
                        loadAjaxRequest("/retail-store-controller?ajax=true&url_action=create_gift_card", $("#_edit_form").serialize(), function(returnArray) {
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
            });
        </script>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
