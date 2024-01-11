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

$GLOBALS['gPageCode'] = "PRODUCTMANUFACTURERSELECTOR";
require_once "shared/startup.inc";

class ProductManufacturerSelectorPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_product_manufacturers":
				$resultSet = executeReadQuery("select count(*) from products where product_manufacturer_id is null and inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
				$row = getNextRow($resultSet);
				$manufacturerArray = array(array("product_manufacturer_id" => "none", "description" => "[None set]", "product_count" => $row['count(*)']));
				freeResult($resultSet);
				$resultSet = executeReadQuery("select product_manufacturer_id, description, (select count(*) from products where product_manufacturer_id = product_manufacturers.product_manufacturer_id) as product_count from product_manufacturers"
					. " where product_manufacturer_id in (select product_manufacturer_id from products) and inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$manufacturerArray[] = $row;
				}
				freeResult($resultSet);
				$returnArray['manufacturers'] = $manufacturerArray;
				ajaxResponse($returnArray);
				break;
			case "get_products":
				$parameters = array($GLOBALS['gClientId']);
				if (!empty($_POST['product_manufacturer_id'])) {
					if ($_POST['product_manufacturer_id'] == "none") {
						$productsWhereStatement = "and product_id in (select product_id from products where product_manufacturer_id is null) ";
					} else {
						$parameters[] = $_POST['product_manufacturer_id'];
						$productsWhereStatement = "and product_id in (select product_id from products where product_manufacturer_id = ?) ";
					}
				} else {
					$productsWhereStatement = "and product_id not in (select product_id from products where product_manufacturer_id is null)";
				}
				$resultSet = executeQuery("select product_id,description from products where inactive = 0 and client_id = ? "
					. $productsWhereStatement . "order by description", $parameters);
				ob_start();
				?>
                <p><input id="product_filter" placeholder="Product Filter"></p>
                <p><span id="row_count"><?= $resultSet['row_count'] ?></span> Products found, <span id="selected_count">0</span>
                    Products selected</p>
                <div id="product_list_wrapper">
                    <ul id="product_list" class="product-manufacturer-connector">
						<?php
						while ($row = getNextRow($resultSet)) {
							?>
                            <li data-product_id="<?= $row['product_id'] ?>"><input class="product-selector"
                                                                                   type="checkbox"
                                                                                   name="product_id_<?= $row['product_id'] ?>"
                                                                                   id="product_id_<?= $row['product_id'] ?>"
                                                                                   value="<?= $row['product_id'] ?>"><label
                                        for="product_id_<?= $row['product_id'] ?>"><?= htmlText($row['description']) ?></label>
                            </li>
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
				$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $_POST['new_product_manufacturer_id']);
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
				$count = 0;
				if (!empty($productIds) && !empty($productManufacturerId)) {
					$dataTable = new DataTable("products");
					$dataTable->setSaveOnlyPresent(true);
					foreach ($productIds as $productId) {
						if (!$dataTable->saveRecord(array("name_values" => array("product_manufacturer_id" => $productManufacturerId), "primary_id" => $productId))) {
							$returnArray['error_message'] .= "Unable to update manufacturer for product ID " . $productId . ": " . $dataTable->getErrorMessage() . "\n";
						} else {
							$count++;
						}
					}
				}
				if ($count > 0) {
					$returnArray['info_message'] = $count . " products updated successfully.";
				} else {
					$returnArray['error_message'] .= "No products updated.";
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function javascript() {
		?>
        <script>
            function populateManufacturers(manufacturers) {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_product_manufacturers", function(returnArray) {
                    if ("manufacturers" in returnArray) {
                        $("#product_manufacturer_id").find("option[value!='']").remove();
                        returnArray['manufacturers'].forEach(function (manufacturer) {
                            $("#product_manufacturer_id").append($("<option></option>").attr("value", manufacturer.product_manufacturer_id).text(manufacturer.description + " (" + manufacturer['product_count'] + ")"));
                        });
                    }
                });
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            populateManufacturers();
            $(document).on("click", "#reselect", function () {
                $("#selectors").removeClass("hidden");
                $("#product_manufacturer_selector_wrapper").addClass("hidden");
                return false;
            });
            $(document).on("click", "#save_products", function () {
                if ($("#_product_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_products", $("#_product_form").serialize(), function(returnArray) {
                        $("#add_product_manufacturer_id").val("");
                        $("#remove_product_manufacturer_id").val("");
                        $("#selectors").removeClass("hidden");
                        $("#product_manufacturer_selector_wrapper").addClass("hidden");
                        populateManufacturers();
                    });
                }
                return false;
            });
            $(document).on("keyup", "#product_filter", function (event) {
                const textFilter = $(this).val().toLowerCase();
                if (empty(textFilter)) {
                    $("ul#product_list li").removeClass("hidden");
                } else {
                    $("ul#product_list li").each(function () {
                        const description = $(this).html().toLowerCase();
                        if (description.indexOf(textFilter) >= 0) {
                            $(this).removeClass("hidden");
                        } else {
                            $(this).addClass("hidden");
                        }
                    });
                }
                if (event.which === 13 || event.which === 3) {
                    if ($("ul#product_list li").not(".hidden").length === 1) {
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
                        $("#product_manufacturer_selector_wrapper").removeClass("hidden");
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
            <h2>Select Products by Manufacturer</h2>
            <form id="_edit_form">
                <p>
                    <select id="product_manufacturer_id" name="product_manufacturer_id">
                        <option value="">[Show all]</option>
                    </select>
                </p>
                <p>
                    <button id="get_products">Get Products</button>
                </p>
            </form>
        </div>

        <div id="product_manufacturer_selector_wrapper" class="hidden">
            <form id="_product_form">
                <p>
                    <button id="reselect">Reselect</button>
                    <button id="save_products">Save Changes</button>
                    <button id="select_all">Select Visible</button>
                    <button id="select_none">Unselect Visible</button>
                </p>
                <p>Put the checked products in the selected manufacturer</p>
                <div id="product_manufacturer_selector">
                    <div id="product_section">
                    </div>
                    <div id="manufacturer_section">

                        <div class="basic-form-line">
                            <label>Change to Manufacturer</label>
                            <select id="new_product_manufacturer_id" name="new_product_manufacturer_id">
                                <option value="">[Select]</option>
								<?php
								$resultSet = executeQuery("select * from product_manufacturers where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
								while ($row = getNextRow($resultSet)) {
									?>
                                    <option value="<?= $row['product_manufacturer_id'] ?>"><?= htmlText($row['description']) ?></option>
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
            #product_manufacturer_selector {
                display: flex;
                width: 96%;
            }

            #product_manufacturer_selector > div {
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
                white-space: nowrap;
            }

            #product_section ul li label {
                color: rgb(40, 40, 40);
            }

            .product-selector {
                margin-right: 10px;
            }

            #manufacturer_section {
                flex: 0 0 50%;
            }

            .manufacturer-chosen-div {
                height: 200px;
                border: 1px solid rgb(200, 200, 200);
                margin-bottom: 20px;
                border-radius: 4px;
            }

            #product_filter {
                width: 300px;
                font-size: 1.2rem;
            }

            .product-manufacturer-connector {
                background-color: rgb(255, 250, 245);
            }

            .product-manufacturer-connector li {
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

$pageObject = new ProductManufacturerSelectorPage();
$pageObject->displayPage();
