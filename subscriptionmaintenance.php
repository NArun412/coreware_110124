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

$GLOBALS['gPageCode'] = "SUBSCRIPTIONMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_recurring_payment_info":
				$resultSet = executeQuery("select units_between,interval_unit from recurring_payment_types where recurring_payment_type_id = ?", $_GET['recurring_payment_type_id']);
				if ($row = getNextRow($resultSet)) {
					$returnArray = $row;
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("subscription_products"));
		$this->iDataSource->addColumnControl("notify_days", "help_label", "Days before renewal to send notification. Leave blank to never send.");
		$this->iDataSource->addColumnControl("notify_days", "minimum_value", 1);
		$this->iDataSource->addColumnControl("product_id", "help_label", "Product used when the subscription is paused");
		$this->iDataSource->addColumnControl("product_id", "help_label", "Minimum days between pauses initiated by customer");

		$this->iDataSource->addColumnControl("maximum_retries", "minimum_value", 1);
		$this->iDataSource->addColumnControl("maximum_retries", "maximum_value", 5);
		$this->iDataSource->addColumnControl("maximum_retries", "form_label", "Maximum Retries for Payment");
		$this->iDataSource->addColumnControl("maximum_retries", "help_label", "Leave empty to flag the payment as requires attention on failure");
	}

	function supplementaryContent() {
		?>
        <p>Products can start and extend the subscription, as well as start a recurring payment to continue the subscription. Deleting an entry will make the setup product inactive.</p>
        <p><input type="hidden" id="row_number" value="0">
            <button tabindex="10" id="add_product">Add Product</button>
        </p>
        <div id="subscription_products">
        </div>
        <input type="hidden" id="deleted_subscription_products" name="deleted_subscription_products" value="" data-crc_value="<?= getCrcValue("") ?>">
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".delete-subscription-product", function () {
                var subscriptionProductId = $(this).closest(".subscription-product").find(".subscription-product-id").val();
                if (!empty(subscriptionProductId)) {
                    var deleteIds = $("#deleted_subscription_products").val();
                    if (deleteIds != "") {
                        deleteIds += ",";
                    }
                    deleteIds += subscriptionProductId;
                    $("#deleted_subscription_products").val(deleteIds);
                }
                $(this).closest(".subscription-product").remove();
            });
            $(document).on("click", "#add_product", function () {
                var rowNumber = addProductRow();
                $("#setup_product_description_" + rowNumber).focus();
                return false;
            });
            $(document).on("change", ".recurring-payment-type", function () {
                if (empty($(this).val())) {
                    $(this).closest(".subscription-product").find(".recurring-payment-type-field").addClass("hidden");
                } else {
                    $(this).closest(".subscription-product").find(".recurring-payment-type-field").removeClass("hidden");
                }
                var thisElement = $(this);
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_recurring_payment_info&recurring_payment_type_id=" + $(this).val(), function(returnArray) {
                        if ("units_between" in returnArray) {
                            thisElement.closest(".subscription-product").find(".units-between").val(returnArray['units_between']);
                            thisElement.closest(".subscription-product").find(".interval-unit").val(returnArray['interval_unit']);
                        }
                    });
                }
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
                $("#row_number").val("0");
                $("#subscription_products").html("");
                if ("subscription_products" in returnArray) {
                    for (var i in returnArray['subscription_products']) {
                        var rowNumber = addProductRow();
                        $("#subscription_product_id_" + rowNumber).val(returnArray['subscription_products'][i]['subscription_product_id']);
                        $("#setup_product_id_" + rowNumber).val(returnArray['subscription_products'][i]['setup_product_id']);
                        $("#product_id_" + rowNumber).val(returnArray['subscription_products'][i]['product_id']);

                        $("#setup_product_description_" + rowNumber).val(returnArray['subscription_products'][i]['setup_product_row']['description']).data("crc_value", getCrcValue(returnArray['subscription_products'][i]['setup_product_row']['description']));
                        $("#setup_product_category_id_" + rowNumber).val(returnArray['subscription_products'][i]['setup_product_row']['product_category_id']).data("crc_value", getCrcValue(returnArray['subscription_products'][i]['setup_product_row']['product_category_id']));
                        $("#setup_product_detailed_description_" + rowNumber).val(returnArray['subscription_products'][i]['setup_product_row']['detailed_description']).data("crc_value", getCrcValue(returnArray['subscription_products'][i]['setup_product_row']['detailed_description']));
                        if (!empty(returnArray['subscription_products'][i]['setup_product_row']['image_id'])) {
                            $("#setup_image_link").html("<a href='getimage.php?id=" + returnArray['subscription_products'][i]['setup_product_row']['image_id'] + "' class='pretty-photo'>View</a>");
                        }
                        $("#setup_product_price_" + rowNumber).val(returnArray['subscription_products'][i]['setup_product_row']['sale_price']).data("crc_value", getCrcValue(returnArray['subscription_products'][i]['setup_product_row']['sale_price']));
                        $("#setup_units_between_" + rowNumber).val(returnArray['subscription_products'][i]['setup_units_between']).data("crc_value", getCrcValue(returnArray['subscription_products'][i]['setup_units_between']));
                        $("#setup_interval_unit_" + rowNumber).val(returnArray['subscription_products'][i]['setup_interval_unit']).data("crc_value", getCrcValue(returnArray['subscription_products'][i]['setup_interval_unit']));
                        $("#recurring_payment_type_id_" + rowNumber).val(returnArray['subscription_products'][i]['recurring_payment_type_id']).data("crc_value", getCrcValue(returnArray['subscription_products'][i]['recurring_payment_type_id']));

                        $("#product_description_" + rowNumber).val(returnArray['subscription_products'][i]['product_row']['description']).data("crc_value", getCrcValue(returnArray['subscription_products'][i]['product_row']['description']));
                        $("#product_category_id_" + rowNumber).val(returnArray['subscription_products'][i]['product_row']['product_category_id']).data("crc_value", getCrcValue(returnArray['subscription_products'][i]['product_row']['product_category_id']));
                        $("#product_detailed_description_" + rowNumber).val(returnArray['subscription_products'][i]['product_row']['detailed_description']).data("crc_value", getCrcValue(returnArray['subscription_products'][i]['product_row']['detailed_description']));
                        if (!empty(returnArray['subscription_products'][i]['product_row']['image_id'])) {
                            $("#image_link").html("<a href='getimage.php?id=" + returnArray['subscription_products'][i]['product_row']['image_id'] + "' class='pretty-photo'>View</a>");
                        }
                        $("#product_price_" + rowNumber).val(returnArray['subscription_products'][i]['product_row']['sale_price']).data("crc_value", getCrcValue(returnArray['subscription_products'][i]['product_row']['sale_price']));
                        $("#units_between_" + rowNumber).val(returnArray['subscription_products'][i]['units_between']).data("crc_value", getCrcValue(returnArray['subscription_products'][i]['units_between']));
                        $("#interval_unit_" + rowNumber).val(returnArray['subscription_products'][i]['interval_unit']).data("crc_value", getCrcValue(returnArray['subscription_products'][i]['interval_unit']));
                        if (empty($("#recurring_payment_type_id_" + rowNumber).val())) {
                            $($("#recurring_payment_type_id_" + rowNumber)).closest(".subscription-product").find(".recurring-payment-type-field").addClass("hidden");
                        } else {
                            $($("#recurring_payment_type_id_" + rowNumber)).closest(".subscription-product").find(".recurring-payment-type-field").removeClass("hidden");
                        }
                    }
                }
            }

            function addProductRow() {
                var rowNumber = parseInt($("#row_number").val()) + 1;
                $("#row_number").val(rowNumber);
                var productRow = $("#product_wrapper").html();
                var productRow = productRow.replace(/%row_number%/g, rowNumber);
                $("#subscription_products").append(productRow);
                return rowNumber;
            }
			$(document).on("click",".product-id-display", function (event) {
				if(!empty($(this).val())) {
					window.open("/productmaintenance.php?clear_filter=true&url_page=show&primary_id=" + $(this).val());
				}
			});

		</script>
		<?php
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$salePriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_code", "SALE_PRICE");
		if (empty($salePriceTypeId)) {
			$resultSet = executeQuery("insert into product_price_types (client_id,product_price_type_code,description) values (?,'SALE_PRICE','Sale Price')", $GLOBALS['gClientId']);
			$salePriceTypeId = $resultSet['insert_id'];
		}
		$startupProductType = getFieldFromId("product_type_id", "product_types", "product_type_code", "SUBSCRIPTION_STARTUP");
		if (empty($startupProductType)) {
			$resultSet = executeQuery("insert into product_types (client_id,product_type_code,description,internal_use_only) values (?,'SUBSCRIPTION_STARTUP','Subscription Startup',1)", $GLOBALS['gClientId']);
			$startupProductType = $resultSet['insert_id'];
		}
		$renewalProductType = getFieldFromId("product_type_id", "product_types", "product_type_code", "SUBSCRIPTION_RENEWAL");
		if (empty($renewalProductType)) {
			$resultSet = executeQuery("insert into product_types (client_id,product_type_code,description,internal_use_only) values (?,'SUBSCRIPTION_RENEWAL','Subscription Renewal',1)", $GLOBALS['gClientId']);
			$renewalProductType = $resultSet['insert_id'];
		}
		foreach ($nameValues as $fieldName => $fieldData) {
			if (substr($fieldName, 0, strlen("subscription_product_id_")) != "subscription_product_id_") {
				continue;
			}
			$rowNumber = substr($fieldName, strlen("subscription_product_id_"));

# Get Setup Product

			$setupProductId = $nameValues['setup_product_id_' . $rowNumber];

			$dataTable = new DataTable("products");
			$dataTable->setSaveOnlyPresent(true);
			$productValues = array("description" => $nameValues['setup_product_description_' . $rowNumber], "detailed_description" => $nameValues['setup_product_detailed_description_' . $rowNumber], "non_inventory_item" => "1", "time_changed" => date("Y-m-d H:i:s"), "reindex" => 1, "virtual_product" => 1, "cart_maximum" => 1);
			if (empty($setupProductId)) {
				$baseProductCode = makeCode($nameValues['subscription_code'] . "_" . $nameValues['setup_product_description_' . $rowNumber]);
				$sequenceNumber = 0;
				do {
					$productCode = $baseProductCode . (empty($sequenceNumber) ? "" : "-" . $sequenceNumber);
					$foundProductId = getFieldFromId("product_id", "products", "product_code", $productCode);
					$sequenceNumber++;
				} while (!empty($foundProductId));
				$baseLinkName = makeCode($nameValues['setup_product_description_' . $rowNumber], array("use_dash" => true, "lowercase" => true));
				$sequenceNumber = 0;
				do {
					$linkName = $baseLinkName . (empty($sequenceNumber) ? "" : "-" . $sequenceNumber);
					$foundProductId = getFieldFromId("product_id", "products", "link_name", $linkName);
					$sequenceNumber++;
				} while (!empty($foundProductId));
				$productValues['date_created'] = date("Y-m-d");
				$productValues['link_name'] = $linkName;
				$productValues['product_code'] = $productCode;
				$productValues['product_type_id'] = $startupProductType;

				if (array_key_exists("setup_image_id_file_" . $rowNumber, $_FILES) && !empty($_FILES["setup_image_id_file_" . $rowNumber]['name'])) {
					$imageId = createImage("setup_image_id_file_" . $rowNumber);
					if ($imageId === false) {
						return "Unable to create file";
					}
					$productValues['image_id'] = $imageId;
				}
			}
			$setupProductId = $dataTable->saveRecord(array("name_values" => $productValues, "primary_id" => $setupProductId));
			if (!$setupProductId) {
				return $dataTable->getErrorMessage();
			}
			if (!empty($nameValues['setup_product_category_id_' . $rowNumber])) {
				$productCategoryLinksDataTable = new DataTable("product_category_links");
				$productCategoryLinksDataTable->saveRecord(array("name_values"=>array("product_category_id"=>$nameValues['setup_product_category_id_' . $rowNumber],"product_id"=>$setupProductId)));
			}
			$productSalePriceId = getFieldFromId("product_price_id", "product_prices", "product_id", $setupProductId, "product_price_type_id = ?", $salePriceTypeId);
			$dataTable = new DataTable("product_prices");
			$productSalePriceId = $dataTable->saveRecord(array("name_values" => array("product_id" => $setupProductId, "product_price_type_id" => $salePriceTypeId, "price" => $nameValues['setup_product_price_' . $rowNumber]),
				"primary_id" => $productSalePriceId));

# Get Renewal Product

			if (!empty($nameValues['recurring_payment_type_id_' . $rowNumber])) {
				$productId = $nameValues['product_id_' . $rowNumber];
				$dataTable = new DataTable("products");
				$dataTable->setSaveOnlyPresent(true);
				$productValues = array("description" => $nameValues['product_description_' . $rowNumber], "detailed_description" => $nameValues['setup_product_detailed_description_' . $rowNumber], "non_inventory_item" => "1", "internal_use_only" => "1", "time_changed" => date("Y-m-d H:i:s"), "reindex" => 1, "virtual_product" => 1, "cart_maximum" => 1);
				if (empty($productId)) {
					$productCode = $nameValues['subscription_code'] . "_SUBSCRIPTION_RENEWAL";
					$productId = getFieldFromId("product_id", "products", "product_code", $productCode);
				}
				if (empty($productId)) {
					$baseProductCode = makeCode($nameValues['subscription_code'] . "_" . $nameValues['product_description_' . $rowNumber]);
					$sequenceNumber = 0;
					do {
						$productCode = $baseProductCode . (empty($sequenceNumber) ? "" : "-" . $sequenceNumber);
						$foundProductId = getFieldFromId("product_id", "products", "product_code", $productCode);
						$sequenceNumber++;
					} while (!empty($foundProductId));
					$baseLinkName = makeCode($nameValues['product_description_' . $rowNumber], array("use_dash" => true, "lowercase" => true));
					$sequenceNumber = 0;
					do {
						$linkName = $baseLinkName . (empty($sequenceNumber) ? "" : "-" . $sequenceNumber);
						$foundProductId = getFieldFromId("product_id", "products", "link_name", $linkName);
						$sequenceNumber++;
					} while (!empty($foundProductId));
					$productValues['date_created'] = date("Y-m-d");
					$productValues['link_name'] = $linkName;
					$productValues['product_code'] = $productCode;
					$productValues['product_type_id'] = $renewalProductType;

					if (array_key_exists("image_id_file_" . $rowNumber, $_FILES) && !empty($_FILES["image_id_file_" . $rowNumber]['name'])) {
						$imageId = createImage("image_id_file_" . $rowNumber);
						if ($imageId === false) {
							return "Unable to create file";
						}
						$productValues['image_id'] = $imageId;
					}
				}
				$productId = $dataTable->saveRecord(array("name_values" => $productValues, "primary_id" => $productId));
				if (!$productId) {
					return $dataTable->getErrorMessage();
				}
				if (!empty($nameValues['product_category_id_' . $rowNumber])) {
					$productCategoryLinksDataTable = new DataTable("product_category_links");
					$productCategoryLinksDataTable->saveRecord(array("name_values"=>array("product_category_id"=>$nameValues['product_category_id_' . $rowNumber],"product_id"=>$productId)));
				}
				$productSalePriceId = getFieldFromId("product_price_id", "product_prices", "product_id", $productId, "product_price_type_id = ?", $salePriceTypeId);
				$dataTable = new DataTable("product_prices");
				$productSalePriceId = $dataTable->saveRecord(array("name_values" => array("product_id" => $productId, "product_price_type_id" => $salePriceTypeId, "price" => $nameValues['product_price_' . $rowNumber]),
					"primary_id" => $productSalePriceId));
			}

# Create Subscription Product

			$dataTable = new DataTable("subscription_products");
			$dataArray = array("subscription_id" => $nameValues['primary_id'], "setup_product_id" => $setupProductId, "setup_units_between" => $nameValues['setup_units_between_' . $rowNumber],
				"setup_interval_unit" => $nameValues['setup_interval_unit_' . $rowNumber], "recurring_payment_type_id" => $nameValues['recurring_payment_type_id_' . $rowNumber]);
			if (!empty($nameValues['recurring_payment_type_id_' . $rowNumber])) {
				$dataArray['product_id'] = $productId;
				$dataArray['units_between'] = $nameValues['units_between_' . $rowNumber];
				$dataArray['interval_unit'] = $nameValues['interval_unit_' . $rowNumber];
			}
			$subscriptionProductId = $dataTable->saveRecord(array("name_values" => $dataArray, "primary_id" => $fieldData));
			if (!$subscriptionProductId) {
				return $dataTable->getErrorMessage();
			}
			$deleteIds = explode(",", $nameValues['deleted_subscription_products']);
			foreach ($deleteIds as $thisId) {
				$subscriptionProductRow = getRowFromId("subscription_products", "subscription_product_id", $thisId, "subscription_id = ?", $nameValues['primary_id']);
				if (empty($subscriptionProductRow)) {
					continue;
				}
				executeQuery("delete from subscription_products where subscription_product_id = ?", $subscriptionProductRow['subscription_product_id']);
				executeQuery("update products set inactive = 1 where product_id = ?", $subscriptionProductRow['setup_product_id']);
			}
		}
		$resultSet = executeQuery("select * from subscription_products where subscription_id = ?", $nameValues['primary_id']);
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['setup_product_id'])) {
				removeCachedData("product_waiting_quantity", $row['setup_product_id']);
				removeCachedData("base_cost", $row['setup_product_id']);
				removeCachedData("*", $row['setup_product_id']);
				removeCachedData("*", $row['setup_product_id']);
			}
			if (!empty($row['product_id'])) {
				removeCachedData("product_waiting_quantity", $row['product_id']);
				removeCachedData("base_cost", $row['product_id']);
				removeCachedData("*", $row['product_id']);
				removeCachedData("*", $row['product_id']);
			}
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
		$salePriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_code", "SALE_PRICE");
		$resultSet = executeQuery("select * from subscription_products where subscription_id = ?", $returnArray['primary_id']['data_value']);
		$subscriptionProducts = array();
		while ($row = getNextRow($resultSet)) {
			$productRow = ProductCatalog::getCachedProductRow($row['setup_product_id']);
			$productRow['sale_price'] = getFieldFromId("price", "product_prices", "product_id", $row['setup_product_id'], "product_price_type_id = ?", $salePriceTypeId);
			$productRow['product_category_id'] = getFieldFromId("product_category_id", "product_category_links", "product_id", $row['setup_product_id']);
			$row['setup_product_row'] = $productRow;

			$productRow = ProductCatalog::getCachedProductRow($row['product_id']);
			if (empty($productRow)) {
				$productRow['product_id'] = "";
				$productRow['description'] = "";
			}
			$productRow['sale_price'] = getFieldFromId("price", "product_prices", "product_id", $row['product_id'], "product_price_type_id = ?", $salePriceTypeId);
			$productRow['product_category_id'] = getFieldFromId("product_category_id", "product_category_links", "product_id", $row['product_id']);
			$row['product_row'] = $productRow;
			$subscriptionProducts[] = $row;
		}
		$returnArray['subscription_products'] = $subscriptionProducts;
	}

	function jqueryTemplates() {
		?>
        <div id="product_wrapper">
            <div class="subscription-product">
                <div class="delete-subscription-product"><span class="fas fa-times"></span></div>
                <div>
                    <input type="hidden" class="subscription-product-id" id="subscription_product_id_%row_number%" name="subscription_product_id_%row_number%" value="">
                    <div class="form-line">
                        <h2>Startup</h2>
						<label>Startup Product ID</label>
						<input type="text" readonly="true" id="setup_product_id_%row_number%" name="setup_product_id_%row_number%" value="" class="product-id-display">
                        <label>Startup product description</label>
                        <input tabindex="10" type="text" size="40" class="validate[required]" id="setup_product_description_%row_number%" name="setup_product_description_%row_number%">
                    </div>

                    <div class="form-line">
                        <label>Detailed Description</label>
                        <textarea tabindex="10" id="setup_product_detailed_description_%row_number%" name="setup_product_detailed_description_%row_number%"></textarea>
                    </div>

                    <div class="form-line" id="_setup_product_category_id_%row_number%_row">
                        <label for="setup_product_category_id_%row_number%">Product Category</label>
                        <select tabindex="10" id="setup_product_category_id_%row_number%" name="setup_product_category_id_%row_number%" class="validate[required]">
                            <option value="">[Select]</option>
							<?php
							$resultSet = executeQuery("select * from product_categories where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								?>
                                <option value="<?= $row['product_category_id'] ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_setup_image_id_%row_number%_row">
                        <label>Product Image</label>
                        <input tabindex="10" type="file" id="setup_image_id_file_%row_number%" name="setup_image_id_file_%row_number%">
                        <div class="inline-block" id="setup_image_link"></div>
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line">
                        <label>Startup price</label>
                        <input tabindex="10" type="text" size="12" class="validate[required,custom[number]]" data-decimal-places="2" id="setup_product_price_%row_number%" name="setup_product_price_%row_number%">
                    </div>

                    <div class="form-line">
                        <label>Startup includes in subscription</label>
                        <input tabindex="10" type="text" size="6" class="validate[required,custom[integer]]" id="setup_units_between_%row_number%" name="setup_units_between_%row_number%">
                        <select tabindex="10" class="validate[required]" id="setup_interval_unit_%row_number%" name="setup_interval_unit_%row_number%">
                            <option value="units">Units (for unit based subscriptions)</option>
                            <option value="day">Days</option>
                            <option value="week">Weeks</option>
                            <option value="month" selected>Months</option>
                        </select>
                    </div>

                    <div class="form-line">
                        <label>Recurring Payment Type</label>
                        <select tabindex="10" class="recurring-payment-type" id="recurring_payment_type_id_%row_number%" name="recurring_payment_type_id_%row_number%">
                            <option value="">[None]</option>
							<?php
							$resultSet = executeQuery("select * from recurring_payment_types where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								?>
                                <option value="<?= $row['recurring_payment_type_id'] ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
                        </select>
                    </div>

                </div>

                <div>

                    <div class="form-line recurring-payment-type-field hidden">
                        <h2>Renewal</h2>
						<label>Renewal Product ID</label>
						<input type="text" readonly="true" id="product_id_%row_number%" name="product_id_%row_number%" value="" class="product-id-display">
						<label>Renewal product description</label>
                        <input tabindex="10" type="text" size="40" class="validate[required]" id="product_description_%row_number%" name="product_description_%row_number%">
                    </div>

                    <div class="form-line recurring-payment-type-field hidden">
                        <label>Detailed Description</label>
                        <textarea tabindex="10" id="product_detailed_description_%row_number%" name="product_detailed_description_%row_number%"></textarea>
                    </div>

                    <div class="form-line recurring-payment-type-field hidden" id="_product_category_id_%row_number%_row">
                        <label for="product_category_id_%row_number%">Product Category</label>
                        <select tabindex="10" id="product_category_id_%row_number%" name="product_category_id_%row_number%" class="validate[required]">
                            <option value="">[Select]</option>
							<?php
							$resultSet = executeQuery("select * from product_categories where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								?>
                                <option value="<?= $row['product_category_id'] ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line recurring-payment-type-field hidden" id="_image_id_%row_number%_row">
                        <label>Product Image</label>
                        <input tabindex="10" type="file" id="image_id_file_%row_number%" name="image_id_file_%row_number%">
                        <div class="inline-block" id="image_link"></div>
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line recurring-payment-type-field hidden">
                        <label>Renewal price</label>
                        <input tabindex="10" type="text" size="12" class="validate[required,custom[number]]" data-decimal-places="2" id="product_price_%row_number%" name="product_price_%row_number%">
                    </div>

                    <div class="form-line recurring-payment-type-field hidden">
                        <label>Renewal extends subscription</label>
                        <input tabindex="10" type="text" size="6" class="units-between validate[required,custom[integer]]" id="units_between_%row_number%" name="units_between_%row_number%">
                        <select tabindex="10" class="interval-unit validate[required]" id="interval_unit_%row_number%" name="interval_unit_%row_number%">
                            <option value="units">Units (for unit based subscriptions)</option>
                            <option value="day">Days</option>
                            <option value="week">Weeks</option>
                            <option value="month" selected>Months</option>
                        </select>
                    </div>

                </div>

            </div>
        </div>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            .subscription-product {
                width: 80%;
                padding: 20px;
                border: 1px solid rgb(180, 180, 180);
                display: flex;
                position: relative;
                margin-bottom: 10px;
            }

            .subscription-product div {
                flex: 0 0 50%;
            }

            .delete-subscription-product {
                position: absolute;
                top: 10px;
                right: 10px;
                cursor: pointer;
            }

            .delete-subscription-product span {
                color: rgb(180, 180, 180);
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage("subscriptions");
$pageObject->displayPage();
