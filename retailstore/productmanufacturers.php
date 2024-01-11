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

$GLOBALS['gPageCode'] = "RETAILSTOREPRODUCTMANUFACTURERS";
$GLOBALS['gCacheProhibited'] = true;
require_once "shared/startup.inc";

class ThisPage extends Page {

	function onLoadJavascript() {
		?>
		<script>
			$("#filter_text").keyup(function (event) {
				if (event.which == 13 || event.which == 3) {
					filterList();
				} else {
					if (!empty(timer)) {
						clearTimeout(timer);
					}
					timer = setTimeout(function () {
						filterList();
					}, 500);
				}
				return false;
			});
			$("#filter_text").change(function (event) {
				filterList();
			}).focus();
		</script>
		<?php
	}

	function javascript() {
		?>
		<script>
			var timer = null;

			function filterList() {
				if (empty($("#filter_text").val())) {
					$(".product-manufacturer").removeClass("hidden");
				} else {
					var textFilter = $("#filter_text").val().toLowerCase();
					$(".product-manufacturer").addClass("hidden");
					$(".product-manufacturer").each(function () {
						var description = $(this).find(".description").html().toLowerCase();
						if (description.indexOf(textFilter) >= 0) {
							$(this).removeClass("hidden");
						}
					});
				}
			}
		</script>
		<?php
	}

	function internalCSS() {
		?>
		<style>
			#filter_text {
				font-size: 1.2rem;
				border-radius: 4px;
				padding: 4px 8px;
			}

			#product_manufacturer_wrapper {
				max-width: 600px;
				margin: 0 auto;
			}

			.product-manufacturer {
				position: relative;
				margin-bottom: 20px;
				height: 60px;
				border-bottom: 1px solid rgb(200, 200, 200);
			}

			.product-manufacturer img {
				max-height: 50px;
				max-width: 120px;
				position: absolute;
				top: 0;
				left: 0;
			}

			.product-manufacturer .description {
				position: absolute;
				top: 50%;
				left: 140px;
				transform: translate(0px, -50%);
			}
		</style>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];
		?>
		<div id="product_manufacturer_wrapper">
			<p><input type="text" id="filter_text" name="filter_text" placeholder="Filter Manufacturers"></p>
			<?php
			$inStockOnly = (!empty($this->getPageTextChunk("IN_STOCK_ONLY")));
			$resultSet = executeQuery("select * from product_manufacturers join contacts using (contact_id) where inactive = 0 and internal_use_only = 0 and cannot_sell = 0 and " .
					"product_manufacturers.client_id = ? and product_manufacturer_id in (select product_manufacturer_id from products where inactive = 0 and internal_use_only = 0)" .
					($inStockOnly ? " and product_manufacturer_id in (select product_manufacturer_id from products where product_id in (select product_id from product_inventories where quantity > 0 and " .
                    "location_id in (select location_id from locations where client_id = " . $GLOBALS['gClientId'] . " and inactive = 0 and internal_use_only = 0 and (product_distributor_id is null or primary_location = 1))))" : "") .
					" order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
                if ($_GET['no_image']) {
                    $row['image_id'] = "";
                }
				$linkUrl = (empty($row['link_name']) ? "/product-search-results?product_manufacturer_id=" . $row['product_manufacturer_id'] : "/product-manufacturer/" . $row['link_name']);
				$validUrlParameters = array("product_category_id","product_category_code","product_tag_id","product_tag_code","product_department_id","product_department_code");
				foreach ($validUrlParameters as $validUrlParameter) {
				    if (!empty($_GET[$validUrlParameter])) {
					    $linkUrl = (strpos($linkUrl, "?") === false ? "?" : "&") . $validUrlParameter . "=" . $_GET[$validUrlParameter];
				    }
				}
				?>
				<div class="product-manufacturer">
					<a href="<?= $linkUrl ?>">
						<?php if (!empty($row['image_id'])) { ?>
							<img src="<?= getImageFilename($row['image_id'],array("use_cdn"=>true)) ?>">
						<?php } ?>
						<div class="description"><?= htmlText($row['description']) ?></div>
					</a>
					<div class='clear-div'></div>
				</div>
				<?php
			}
			?>
		</div>
		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
