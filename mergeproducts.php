<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "MERGEPRODUCTS";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 120000;

class MergeProductsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "merge_products";
				$duplicateProductId = getFieldFromId("product_id", "product_data", "product_id", $_GET['duplicate_product_id'],
                    "(upc_code is null or length(upc_code) < 12 or length(upc_code) > 13)");
                if(empty($duplicateProductId) && !empty($_GET['duplicate_product_id'])) {
                    $returnArray['results'] = "Duplicate product can not have a valid UPC. Remove the UPC first.";
                    ajaxResponse($returnArray);
                }
				$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id']);
				$mergeResult = ProductCatalog::mergeProducts($productId, $duplicateProductId);
				if ($mergeResult === true) {
					$returnArray['results'] = "<p class='info-message'>Products successfully merged</p>";
				} else {
					$returnArray = $mergeResult;
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#merge_products", function () {
                $("#results").html("");
                const productId = $("#product_id").val();
                const duplicateProductId = $("#duplicate_product_id").val();
                if (empty(productId) || empty(duplicateProductId)) {
                    return;
                }
                $("#results").html("<p>Merging '" + $("#duplicate_product_id_autocomplete_text").val() + "' and '" + $("#product_id_autocomplete_text").val() + "'</p>");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=merge_products&product_id=" + productId + "&duplicate_product_id=" + duplicateProductId, function(returnArray) {
                    if ("results" in returnArray) {
                        $("#results").html(returnArray['results']);
                        $("#duplicate_product_id").val("");
                        $("#product_id").val("");
                        $("#duplicate_product_id_autocomplete_text").val("");
                        $("#product_id_autocomplete_text").val("");
                    }
                });
            });
        </script>
		<?php
	}

	function mainContent() {
		?>
        <h2>Merge product with no UPC into existing product</h2>
        <p class='red-text highlighted-text'>Merging products is PERMANENT and CANNOT be reversed. Be sure that the products are identical.</p>
        <p>Merge will be performed as follows:</p>
        <ul>
            <li>Any orders for the duplicate product will be changed to the real product.</li>
            <li>Any wish list items or shopping cart items for the duplicate product will be changed to the real product.</li>
            <li>All inventory history will be removed from the duplicate product.</li>
            <li>The duplicate product will be deleted.</li>
        </ul>

        <p class='error-message'></p>
        <p id="results"></p>

        <div class="basic-form-line " id="_duplicate_product_id_row">
            <label for="duplicate_product_id" class="required-label">Duplicate Product (cannot have a valid UPC)</label>
            <input type="hidden" id="duplicate_product_id" name="duplicate_product_id" value="">
            <input autocomplete="chrome-off" autocomplete="off" tabindex="10" class="autocomplete-field validate[required]" type="text" size="50" name="duplicate_product_id_autocomplete_text" id="duplicate_product_id_autocomplete_text" data-additional_filter="BADUPC" data-autocomplete_tag="products">
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line " id="_product_id_row">
            <label for="product_id" class="required-label">Real Product</label>
            <input type="hidden" id="product_id" name="product_id" value="">
            <input autocomplete="chrome-off" autocomplete="off" tabindex="10" class="autocomplete-field validate[required]" type="text" size="50" name="product_id_autocomplete_text" id="product_id_autocomplete_text" data-autocomplete_tag="products">
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <p>
            <button id="merge_products">Merge Products</button>
        </p>
		<?php

		return true;
	}

	function internalCSS() {
		?>
        <style>
            input.autocomplete-field {
                width: 800px;
            }
            #_main_content ul {
                list-style: disc;
                margin: 20px 0 40px 30px;
            li {
                margin: 5px;
            }
            }
        </style>
		<?php
	}
}

$pageObject = new MergeProductsPage();
$pageObject->displayPage();
