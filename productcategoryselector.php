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

$GLOBALS['gPageCode'] = "PRODUCTCATEGORYSELECTOR";
require_once "shared/startup.inc";

class ProductCategorySelectorPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_products":
				$parameters = array($GLOBALS['gClientId']);
				$otherCategoriesClause = "";
				if (!empty($_POST['product_category_id'])) {
					$parameters[] = $_POST['product_category_id'];
					switch ($_POST['other_categories']) {
						case "none_other":
							$otherCategoriesClause = "and product_id not in (select product_id from product_category_links where product_category_id <> ?)";
							$parameters[] = $_POST['product_category_id'];
							break;
						case "more_than_one":
							$otherCategoriesClause = "and product_id in (select product_id from product_category_links where product_category_id <> ?)";
							$parameters[] = $_POST['product_category_id'];
							break;
						default:
							break;
					}
				}
				$resultSet = executeQuery("select product_id,description from products where inactive = 0 and client_id = ? " .
					(empty($_POST['product_category_id']) ? "and product_id not in (select product_id from product_category_links) " :
						"and product_id in (select product_id from product_category_links where product_category_id = ?) " . $otherCategoriesClause) .
					"order by description", $parameters);
				ob_start();
				?>
                <p><input id="product_filter" placeholder="Product Filter"></p>
                <p><span id="row_count"><?= $resultSet['row_count'] ?></span> Products found, <span id="selected_count">0</span> Products selected</p>
                <div id="product_list_wrapper">
                    <ul id="product_list" class="product-category-connector">
						<?php
						while ($row = getNextRow($resultSet)) {
							?>
                            <li data-product_id="<?= $row['product_id'] ?>"><input class="product-selector" type="checkbox" name="product_id_<?= $row['product_id'] ?>" id="product_id_<?= $row['product_id'] ?>" value="<?= $row['product_id'] ?>"><label for="product_id_<?= $row['product_id'] ?>"><?= htmlText($row['description']) ?></label></li>
							<?php
						}
						?>
                    </ul>
                </div>
				<?php
				$returnArray['product_section'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "save_products":
				$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $_POST['add_product_category_id']);
				$removeProductCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $_POST['remove_product_category_id']);
				$productIds = array();
				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("product_id_")) == "product_id_") {
						$productId = getFieldFromId("product_id", "products", "product_id", $fieldData);
						if (empty($productId)) {
							continue;
						}
						$productIds[] = $productId;
					}
				}
				if (!empty($productIds) && !empty($productCategoryId)) {
					foreach ($productIds as $productId) {
						$productCategoryLinkId = getFieldFromId("product_category_link_id", "product_category_links", "product_id", $productId,
							"product_category_id = ?", $productCategoryId);
						if (empty($productCategoryLinkId)) {
                            $productCategoryLinksDataTable = new DataTable("product_category_links");
                            $productCategoryLinksDataTable->saveRecord(array("name_values"=>array("product_category_id"=>$productCategoryId,"product_id"=>$productId)));
						}
					}
				}
				if (!empty($productIds) && !empty($removeProductCategoryId)) {
					foreach ($productIds as $productId) {
						executeQuery("delete from product_category_links where product_category_id = ? and product_id = ?", $removeProductCategoryId, $productId);
					}
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#reselect", function () {
                $("#selectors").removeClass("hidden");
                $("#product_category_selector_wrapper").addClass("hidden");
                return false;
            });
            $(document).on("click", "#save_products", function () {
                if ($("#_product_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_products", $("#_product_form").serialize(), function(returnArray) {
                        $("#add_product_category_id").val("");
                        $("#remove_product_category_id").val("");
                        $("#selectors").removeClass("hidden");
                        $("#product_category_selector_wrapper").addClass("hidden");
                    });
                }
                return false;
            });
            $(document).on("keyup", "#product_filter", function (event) {
                var textFilter = $(this).val().toLowerCase();
                if (textFilter == "") {
                    $("ul#product_list li").removeClass("hidden");
                } else {
                    $("ul#product_list li").each(function () {
                        var description = $(this).html().toLowerCase();
                        if (description.indexOf(textFilter) >= 0) {
                            $(this).removeClass("hidden");
                        } else {
                            $(this).addClass("hidden");
                        }
                    });
                }
                if (event.which == 13 || event.which == 3) {
                    if ($("ul#product_list li").not(".hidden").length == 1) {
                        $("ul#product_list li").not(".hidden").trigger("click");
                    }
                }
                $("#row_count").text($("ul#product_list > li").not(".hidden").length);
                $("#selected_count").text($(".product-selector:checked").length);
            })
            $("#select_all").click(function (event) {
                $("ul#product_list > li").not(".hidden").find('input[type="checkbox"]').prop("checked", true);
                $("#selected_count").text($(".product-selector:checked").length);
                event.preventDefault();
            });
            $("#select_none").click(function (event) {
                $("ul#product_list > li").not(".hidden").find('input[type="checkbox"]').prop("checked", false);
                $("#selected_count").text($(".product-selector:checked").length);
                event.preventDefault();
            });
            $(document).click(function (event) {
                $("#selected_count").text($(".product-selector:checked").length);
            });
            $("#get_products").click(function () {
                if ($("#_edit_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_products", $("#_edit_form").serialize(), function(returnArray) {
                        $("#product_section").html("");
                        if ("product_section" in returnArray) {
                            $("#product_section").html(returnArray['product_section']);
                        }
                        $("#selectors").addClass("hidden");
                        $("#product_category_selector_wrapper").removeClass("hidden");
                        $("#product_filter").focus();
                    });
                }
                return false;
            });
        </script>
		<?php
	}

	function mainContent() {
		echo $this->getPageData("content");
		?>
        <div id="selectors">
            <h2>Select Products</h2>
            <form id="_edit_form">

				<?= createFormControl("product_category_links", "product_category_id", array("form_label" => "Category", "not_null" => false, "empty_text" => "[None]")) ?>
                <div class="basic-form-line">
                    <input tabindex="10" type="radio" id="other_categories_none" name="other_categories" value="none_other" checked><label for="other_categories_none" class="checkbox-label">In no other categories</label>
                    <br>
                    <input tabindex="10" type="radio" id="other_categories_more" name="other_categories" value="more_than_one"><label for="other_categories_more" class="checkbox-label">In more than one category</label>
                    <br>
                    <input tabindex="10" type="radio" id="other_categories_all" name="other_categories" value="all"><label for="other_categories_all" class="checkbox-label">All products in category</label>
                </div>

                <p>
                    <button id="get_products">Get Products</button>
                </p>
            </form>
        </div>

        <div id="product_category_selector_wrapper" class="hidden">
            <form id="_product_form">
                <p>
                    <button id="reselect">Reselect</button>
                    <button id="save_products">Save Changes</button>
                    <button id="select_all">Select Visible</button>
                    <button id="select_none">Unselect Visible</button>
                </p>
                <p>Put the checked products in the selected category</p>
                <div id="product_category_selector">
                    <div id="product_section">
                    </div>
                    <div id="category_section">

                        <div class="basic-form-line">
                            <label>Put into Category</label>
                            <select id="add_product_category_id" name="add_product_category_id">
                                <option value="">[Select]</option>
								<?php
								$resultSet = executeQuery("select * from product_categories where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
								while ($row = getNextRow($resultSet)) {
									?>
                                    <option value="<?= $row['product_category_id'] ?>"><?= htmlText($row['description']) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>

                        <div class="basic-form-line">
                            <label>Remove From Category</label>
                            <select id="remove_product_category_id" name="remove_product_category_id">
                                <option value="">[Select]</option>
								<?php
								$resultSet = executeQuery("select * from product_categories where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
								while ($row = getNextRow($resultSet)) {
									?>
                                    <option value="<?= $row['product_category_id'] ?>"><?= htmlText($row['description']) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>

                    </div>
                </div>
            </form>
        </div>
		<?php
		echo $this->getPageData("after_form_content");
		return true;
	}

	function massageUrlParameters() {
		$_GET['url_subpage'] = $_GET['url_page'];
		$_GET['url_page'] = "show";
		$_GET['primary_id'] = "";
	}

	function internalCSS() {
		?>
        <style>
            #product_category_selector {
                display: flex;
                width: 96%;
            }

            #product_category_selector > div {
                flex: 0 0 50%;
                padding-right: 40px;
                overflow: hidden;
            }

            #product_list_wrapper {
                height: 500px;
                overflow: scroll;
            }

            #product_section ul {
                background-color: rgb(245, 250, 255);
                border: 1px solid rgb(200, 200, 200);
                border-radius: 4px;
                width: 100%;
                overflow: hidden;
            }

            #product_section ul li {
                border: 1px solid rgb(15, 160, 80);
                color: rgb(0, 50, 15);
                font-size: .8rem;
                background-color: rgb(225, 250, 240);
                font-weight: 500;
                overflow: hidden;
                margin: 0 0 2px 0;
                padding: 8px 20px 8px 6px;
                width: 100%;
                cursor: pointer;
                border-radius: 5px;
                position: relative;
                width: 100%;
                overflow: hidden;
                white-space: nowrap;
                cursor: pointer;
            }

            #product_section ul li label {
                color: rgb(40, 40, 40);
            }

            .product-selector {
                margin-right: 10px;
            }

            #category_section {
                flex: 0 0 50%;
            }

            .category-chosen-div {
                height: 200px;
                border: 1px solid rgb(200, 200, 200);
                margin-bottom: 20px;
                border-radius: 4px;
            }

            #product_filter {
                width: 300px;
                font-size: 1.2rem;
            }

            .product-category-connector {
                background-color: rgb(255, 250, 245);
            }

            .product-category-connector li {
                border: 1px solid rgb(15, 160, 80);
                color: rgb(0, 50, 15);
                font-size: .8rem;
                background-color: rgb(225, 250, 240);
                font-weight: 500;
                overflow: hidden;
                margin: 0 0 2px 0;
                padding: 8px 20px 8px 6px;
                width: 100%;
                cursor: pointer;
                border-radius: 5px;
                position: relative;
            }
        </style>
		<?php
	}
}

$pageObject = new ProductCategorySelectorPage();
$pageObject->displayPage();
