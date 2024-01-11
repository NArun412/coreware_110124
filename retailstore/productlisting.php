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

$GLOBALS['gPageCode'] = "RETAILSTOREPRODUCTLISTINGS";
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
                    $(".product-listing").removeClass("hidden");
                } else {
                    var textFilter = $("#filter_text").val().toLowerCase();
                    $(".product-listing").addClass("hidden");
                    $(".product-listing").each(function () {
                        var description = $(this).find(".description").html().toLowerCase();
                        var productManufacturer = $(this).find(".product-manufacturer").html().toLowerCase();
                        if (description.indexOf(textFilter) >= 0 || productManufacturer.indexOf(textFilter) >= 0) {
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

            #product_wrapper {
                width: 100%;
                margin: 0 auto;
            }

            #product_table {
                width: 100%;
            }

            #product_table.grid-table th {
                text-align: left;
                cursor: pointer;
                background-color: rgb(180, 180, 180);
                color: rgb(0, 0, 0);
                font-weight: 900;
            }

        </style>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];
		$urlAliasTypeCode = getUrlAliasTypeCode("products","product_id", "id");
		$productDepartmentId = getFieldFromId("product_department_id", "product_departments", "product_department_code", $_GET['product_department_code']);
		$productCategoryGroupId = getFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_code", $_GET['product_category_group_code']);
		$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_code", $_GET['product_category_code']);
		if (!empty($productCategoryId)) {
			?>
            <h2>Products</h2>
            <div id="product_wrapper">
                <input type='hidden' id='product_category_id' value='<?= $productCategoryId ?>'>
                <p><input type="text" id="filter_text" name="filter_text" placeholder="Filter Products"></p>
				<?php
				$resultSet = executeQuery("select *,(select description from product_manufacturers where product_manufacturer_id = products.product_manufacturer_id) product_manufacturer from products left outer join product_data using (product_id) where products.client_id = ? and inactive = 0" .
					($GLOBALS['gUserRow']['administrator_flag'] ? "" : " and internal_use_only = 0") .
					" and product_id in (select product_id from product_category_links where product_category_id = ?) order by sort_order,description", $GLOBALS['gClientId'], $productCategoryId);
				$products = array();
				$productListingTable = getCachedData("product_listing_table", $productCategoryId);
				if (empty($productListingTable)) {
					ob_start();
					?>
                    <table id="product_table" class='header-sortable grid-table'>
                        <tr class='header-row'>
                            <th>Title</th>
                            <th>Manufacturer</th>
                            <th>UPC</th>
                        </tr>
						<?php
						while ($row = getNextRow($resultSet)) {
							$linkUrl = "/" . (empty($urlAliasTypeCode) || empty($row['link_name']) ? "product-details?id=" . $row['product_id'] : $urlAliasTypeCode . "/" . $row['link_name']);
							?>
                            <tr class="product-listing">
                                <td><a target="_blank" href="<?= $linkUrl ?>">
                                        <div class="description"><?= htmlText($row['description']) ?></div>
                                    </a></td>
                                <td class='product-manufacturer'><?= htmlText($row['product_manufacturer']) ?></td>
                                <td><?= htmlText($row['upc_code']) ?></td>
                            </tr>
							<?php
						}
						?>
                    </table>
					<?php
					$productListingTable = ob_get_clean();
					setCachedData("product_listing_table", $productDepartmentId, $productListingTable, 24);
				}
				echo $productListingTable;
				?>
            </div>
			<?php
		} else {
			if (!empty($productDepartmentId)) {
				?>
                <h2>Product Category Groups</h2>
				<?php
				$resultSet = executeQuery("select * from product_category_group_departments join product_category_groups using (product_category_group_id) where inactive = 0 and internal_use_only = 0 and product_department_id = ? order by sort_order,description", $productDepartmentId);
				while ($row = getNextRow($resultSet)) {
					?>
                    <div class='selectable-hierarchy'><a href='<?= $GLOBALS['gLinkUrl'] ?>?product_category_group_code=<?= $row['product_category_group_code'] ?>'><?= htmlText($row['description']) ?></a></div>
					<?php
				}
			} else if (!empty($productCategoryGroupId)) {
				?>
                <h2>Product Categories</h2>
				<?php
				$resultSet = executeQuery("select * from product_category_group_links join product_categories using (product_category_id) where inactive = 0 and internal_use_only = 0 and product_category_group_id = ? order by sort_order,description", $productCategoryGroupId);
				while ($row = getNextRow($resultSet)) {
					?>
                    <div class='selectable-hierarchy'><a href='<?= $GLOBALS['gLinkUrl'] ?>?product_category_code=<?= $row['product_category_code'] ?>'><?= htmlText($row['description']) ?></a></div>
					<?php
				}
			} else {
				?>
                <h2>Product Departments</h2>
				<?php
				$resultSet = executeQuery("select * from product_departments where inactive = 0 and internal_use_only = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
                    <div class='selectable-hierarchy'><a href='<?= $GLOBALS['gLinkUrl'] ?>?product_department_code=<?= $row['product_department_code'] ?>'><?= htmlText($row['description']) ?></a></div>
					<?php
				}
			}
		}
		echo $this->iPageData['after_form_content'];
		return true;
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
