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

$GLOBALS['gPageCode'] = "INVENTORYREPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;
ini_set("memory_limit", "4096M");

class InventoryReportPage extends Page implements BackgroundReport {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_report":
				$returnArray = self::getReportContent();
				if (array_key_exists("report_export", $returnArray)) {
					if (is_array($returnArray['export_headers'])) {
						foreach ($returnArray['export_headers'] as $thisHeader) {
							header($thisHeader);
						}
					}
					echo $returnArray['report_export'];
				} else {
					echo jsonEncode($returnArray);
				}
				exit;
		}
	}

	public static function getReportContent() {
		$returnArray = array();
		saveStoredReport(static::class);

		$fullName = getUserDisplayName($GLOBALS['gUserId']);

		$whereStatement = "location_id in (select location_id from locations where " . (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_location = 1 and user_id = " . $GLOBALS['gUserId'] . " and " : "user_location = 0 and ") .
			"inactive = 0 and internal_use_only = 0 and ignore_inventory = 0 and (product_distributor_id is null or primary_location = 1))";
		$parameters = array($GLOBALS['gClientId']);
		$displayCriteria = "";

		if (!empty($_POST['location_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "location_id = ?";
			$parameters[] = $_POST['location_id'];
		}

		if (!empty($_POST['product_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_id = ?";
			$parameters[] = $_POST['product_id'];
		}

		if (!empty($_POST['product_category_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_id in (select product_id from product_category_links where product_category_id = ?)";
			$parameters[] = $_POST['product_category_id'];
		}

		if (!empty($_POST['product_manufacturer_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_manufacturer_id = ?";
			$parameters[] = $_POST['product_manufacturer_id'];
		}

		if (empty($_POST['include_inactive'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_id in (select product_id from products where inactive = 0)";
		}

		if (!empty($_POST['product_category_group_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from product_category_group_links where product_category_group_id = ?))";
			$parameters[] = $_POST['product_category_group_id'];
		}

		if (!empty($_POST['product_department_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "(product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from product_category_group_links " .
				"where product_category_group_id in (select product_category_group_id from product_category_group_departments where product_department_id = ?))) or " .
				"product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from product_category_departments where " .
				"product_department_id = ?)))";
			$parameters[] = $_POST['product_department_id'];
			$parameters[] = $_POST['product_department_id'];
		}

		$exportReport = $_POST['report_type'] == "csv";
		$includeZeroQuantity = (!empty($_POST['include_zero_quantity']));

		$productDistributor = false;
		if (!empty($_POST['location_id']) && !empty($_POST['product_id'])) {
			$productDistributor = ProductDistributor::getProductDistributorInstance($_POST['location_id']);
		}
		$restrictionsText = "";
		if ($productDistributor) {
			$productRow = getRowFromId("products", "product_id", $_POST['product_id']);
			$productCategoryId = getFieldFromId("product_category_id","product_category_cannot_sell_distributors","product_distributor_id",$productDistributor->getProductDistributorId(),
				"product_category_id in (select product_category_id from product_category_links where product_id = ?)",$productRow['product_id']);
			if (!empty($productCategoryId)) {
				$restrictionsText .= (empty($restrictionsText) ? "" : "<br>") . "This product is in a category that cannot be sold from this distributor.";
			}
			$resultSet = executeQuery("select product_department_id from product_department_cannot_sell_distributors where product_department_id in (select product_department_id from product_departments where client_id = ?)",$GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (ProductCatalog::productIsInDepartment($productRow['product_Id'], $row['product_department_id'])) {
					$restrictionsText .= (empty($restrictionsText) ? "" : "<br>") . "This product is in a department that cannot be sold from this distributor.";
				}
			}
			$productManufacturerId = getFieldFromId("product_manufacturer_id","product_manufacturer_cannot_sell_distributors","product_distributor_id",$productDistributor->getProductDistributorId(),
				"product_manufacturer_id = ?",$productRow['product_manufacturer_id']);
			if (!empty($productManufacturerId)) {
				$restrictionsText .= (empty($restrictionsText) ? "" : "<br>") . "This product has a manufacturer that cannot be sold from this distributor.";
			}
		}

		ob_start();

		$resultSet = executeReadQuery("select *,(select description from locations where location_id = product_inventories.location_id) location_description," .
			"(select sum(quantity) from order_items where product_id = products.product_id and deleted = 0 and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) as ordered_quantity," .
			"(select sum(quantity) from order_shipment_items where order_item_id in (select order_item_id from order_items where product_id = products.product_id and deleted = 0 and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) and " .
			"exists (select order_shipment_id from order_shipments where order_shipment_id = order_shipment_items.order_shipment_id and secondary_shipment = 0)) as shipped_quantity from products " .
			"left outer join product_data using (product_id) join product_inventories using (product_id) where products.client_id = ?" .
			(!empty($whereStatement) ? " and " . $whereStatement : "") . " order by " . ($_POST['group_by'] == "location" ? "location_description,upc_code" : "upc_code,location_description"), $parameters);
		$returnArray['report_title'] = "Product Inventory Report";
		if ($exportReport) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"inventory.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "inventory.csv";

			$headers = array("UPC", "Product Code", "Description", "Manufacturer", "SKU", "MAP");
			if ($_POST['group_by'] != "totals") {
				$headers[] = "Location";
				$headers[] = "Bin";
			}
			$headers[] = "Quantity";
			if ($_POST['group_by'] == "totals") {
				$headers[] = "Waiting";
				$headers[] = "Available";
			} else {
				$headers[] = "Cost";
			}
			echo createCsvRow($headers);
		} else {
			if (!empty($productDistributor)) {
				?>
                <p class='live-value'>Green values are from the live feed of the distributor.</p>
				<?php
			}
			if (!empty($restrictionsText)) {
				?>
                <p class='red-text'><?= $restrictionsText ?></p>
				<?php
			}
			?>
            <table class="grid-table">
            <tr>
                <th>UPC</th>
                <th>Product Code</th>
                <th>Description</th>
                <th>Manufacturer</th>
                <th>SKU</th>
                <th class="align-right">MAP</th>
				<?php if ($_POST['group_by'] != "totals") { ?>
                    <th>Location</th>
                    <th>Bin</th>
				<?php } ?>
                <th class="align-right">Quantity</th>
				<?php if ($_POST['group_by'] == "totals") { ?>
                    <th class="align-right">Waiting</th>
                    <th class="align-right">Available</th>
				<?php } else { ?>
                    <th class="align-right">Cost</th>
				<?php } ?>
            </tr>
			<?php
		}
		$saveProductRow = false;
		$saveQuantity = 0;
		$saveWaiting = 0;
		while ($row = getNextRow($resultSet)) {
			$liveDistributorQuantity = false;
			$liveDistributorCost = false;
			$baseCost = false;
			if (!empty($productDistributor)) {
				$distributorQuantities = $productDistributor->getProductInventoryQuantity($row['product_id']);
				if (!empty($distributorQuantities)) {
					$row['quantity'] = $distributorQuantities['quantity'];
					$liveDistributorQuantity = true;
					if (!empty($distributorQuantities['cost'])) {
						$baseCost = $distributorQuantities['cost'];
						$liveDistributorCost = true;
					}
				}
			}
			if (empty($row['ordered_quantity'])) {
				$row['ordered_quantity'] = 0;
			}
			if (empty($row['shipped_quantity'])) {
				$row['shipped_quantity'] = 0;
			}
			$availableQuantity = $row['quantity'] - max(0, $row['ordered_quantity'] - $row['shipped_quantity']);
			$row['available_quantity'] = $availableQuantity;
			if ($row['quantity'] == 0 && !$includeZeroQuantity) {
				continue;
			}
			if ($_POST['group_by'] == "totals") {
				if ($row['product_id'] == $saveProductRow['product_id']) {
					$saveQuantity += $row['quantity'];
				} else {
					if (!empty($saveProductRow)) {
						$availableQuantity = $saveQuantity - $saveWaiting;
						if (($availableQuantity == 0 && !$includeZeroQuantity) || ($availableQuantity >= 0 && !empty($_POST['only_negative']))) {
							$saveQuantity = $row['quantity'];
							$saveWaiting = max(0, $row['ordered_quantity'] - $row['shipped_quantity']);
							continue;
						}
						if ($exportReport) {
							$dataRow = array($saveProductRow['upc_code'], $saveProductRow['product_code'], $saveProductRow['description'], getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $saveProductRow['product_manufacturer_id']),
								$saveProductRow['manufacturer_sku'], (empty($saveProductRow['manufacturer_advertised_price']) || $saveProductRow['manufacturer_advertised_price'] == 0 ? "" : number_format($saveProductRow['manufacturer_advertised_price'], 2, ".", ",")),
								$saveQuantity, $saveWaiting, $saveQuantity - $saveWaiting);
							echo createCsvRow($dataRow);
						} else {
							?>
                            <tr>
                                <td><?= $saveProductRow['upc_code'] ?></td>
                                <td><?= htmlText($saveProductRow['product_code']) ?></td>
                                <td><?= htmlText($saveProductRow['description']) ?></td>
                                <td><?= htmlText(getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $saveProductRow['product_manufacturer_id'])) ?></td>
                                <td><?= htmlText($row['manufacturer_sku']) ?></td>
                                <td class="align-right"><?= (empty($saveProductRow['manufacturer_advertised_price']) || $saveProductRow['manufacturer_advertised_price'] == 0 ? "" : number_format($saveProductRow['manufacturer_advertised_price'], 2, ".", ",")) ?></td>
                                <td class="align-right"><?= $saveQuantity ?></td>
                                <td class="align-right"><?= $saveWaiting ?></td>
                                <td class="align-right"><?= $saveQuantity - $saveWaiting ?></td>
                            </tr>
							<?php
						}
					}
					$saveProductRow = $row;
					$saveQuantity = $row['quantity'];
					$saveWaiting = max(0, $row['ordered_quantity'] - $row['shipped_quantity']);
				}
			} else {
				if (!$liveDistributorCost) {
					$baseCost = ProductCatalog::getLocationBaseCost($row['product_id'], $row['location_id'], $row);
				}
				if ($exportReport) {
                    $dataRow = array($row['upc_code'],
                        $row['product_code'],
                        $row['description'],
                        getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $row['product_manufacturer_id']),
                        $row['manufacturer_sku'],
                        (empty($row['manufacturer_advertised_price']) || $row['manufacturer_advertised_price'] == 0 ? "" : number_format($row['manufacturer_advertised_price'], 2, ".", ",")),
                        getFieldFromId("description", "locations", "location_id", $row['location_id']),
                        $row['bin_number'],
                        $row['quantity'],
                        (empty($baseCost) ? "" : number_format($baseCost, 2, ".", ",")));
                    echo createCsvRow($dataRow);
				} else {
					?>
                    <tr>
                        <td><?= $row['upc_code'] ?></td>
                        <td><?= htmlText($row['product_code']) ?></td>
                        <td><?= htmlText($row['description']) ?></td>
                        <td><?= htmlText(getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $row['product_manufacturer_id'])) ?></td>
                        <td><?= htmlText($row['manufacturer_sku']) ?></td>
                        <td class="align-right"><?= (empty($row['manufacturer_advertised_price']) || $row['manufacturer_advertised_price'] == 0 ? "" : number_format($row['manufacturer_advertised_price'], 2, ".", ",")) ?></td>
                        <td><?= htmlText(getFieldFromId("description", "locations", "location_id", $row['location_id'])) ?></td>
                        <td><?= htmlText($row['bin_number']) ?></td>
                        <td class="align-right <?= ($liveDistributorQuantity ? "live-value" : "") ?>"><?= $row['quantity'] ?></td>
                        <td class="align-right <?= ($liveDistributorCost ? "live-value" : "") ?>"><?= (empty($baseCost) ? "" : number_format($baseCost, 2, ".", ",")) ?></td>
                    </tr>
					<?php
				}
			}
		}
		if (!empty($saveProductRow)) {
			$availableQuantity = $saveQuantity - $saveWaiting;
			if (($availableQuantity == 0 && !$includeZeroQuantity) || ($availableQuantity >= 0 && !empty($_POST['only_negative']))) {
				$saveQuantity = 0;
			} else if ($exportReport) {
				$dataRow = array($saveProductRow['upc_code'], $saveProductRow['product_code'], $saveProductRow['description'], getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $saveProductRow['product_manufacturer_id']),
					$saveProductRow['manufacturer_sku'], (empty($saveProductRow['manufacturer_advertised_price']) || $saveProductRow['manufacturer_advertised_price'] == 0 ? "" : number_format($saveProductRow['manufacturer_advertised_price'], 2, ".", ",")),
					$saveQuantity, $saveWaiting, $saveQuantity - $saveWaiting);
				createCsvRow($dataRow);
			} else {
				?>
                <tr>
                    <td><?= $saveProductRow['upc_code'] ?></td>
                    <td><?= htmlText($saveProductRow['product_code']) ?></td>
                    <td><?= htmlText($saveProductRow['description']) ?></td>
                    <td><?= htmlText(getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $saveProductRow['product_manufacturer_id'])) ?></td>
                    <td><?= htmlText($row['manufacturer_sku']) ?></td>
                    <td class="align-right"><?= (empty($saveProductRow['manufacturer_advertised_price']) || $saveProductRow['manufacturer_advertised_price'] == 0 ? "" : number_format($saveProductRow['manufacturer_advertised_price'], 2, ".", ",")) ?></td>
                    <td class="align-right"><?= $saveQuantity ?></td>
                    <td class="align-right"><?= $saveWaiting ?></td>
                    <td class="align-right"><?= $saveQuantity - $saveWaiting ?></td>
                </tr>
				<?php
			}
		}
		if ($exportReport) {
			$returnArray['report_export'] = ob_get_clean();
		} else {
			?>
            </table>
			<?php
			$returnArray['report_content'] = ob_get_clean();
		}
		return $returnArray;
	}

	function mainContent() {

		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <p>If a location and a specific product is selected, the inventory quantity and cost will be retrieved from the distributor in real time.</p>

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Output Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="web">Web</option>
                        <option value="csv">CSV</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_group_by_row">
                    <label for="group_by">Group By</label>
                    <select tabindex="10" id="group_by" name="group_by">
                        <option value="location">Location</option>
                        <option value="product">Product</option>
                        <option value="totals">Totals By Product</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_location_id_row">
                    <label for="location_id">Location</label>
                    <select tabindex="10" id="location_id" name="location_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select location_id,description from locations where " . (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_location = 1 and user_id = " . $GLOBALS['gUserId'] . " and " : "user_location = 0 and ") . "client_id = ? and ignore_inventory = 0 and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['location_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_product_department_id_row">
                    <label for="product_department_id">Department</label>
                    <select tabindex="10" id="product_department_id" name="product_department_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select product_department_id,description from product_departments where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_department_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>


                <div class="basic-form-line" id="_product_category_group_id_row">
                    <label for="product_category_group_id">Category Group</label>
                    <select tabindex="10" id="product_category_group_id" name="product_category_group_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select product_category_group_id,description from product_category_groups where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_category_group_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_product_category_id_row">
                    <label for="product_category_id">Category</label>
                    <select tabindex="10" id="product_category_id" name="product_category_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select product_category_id,description from product_categories where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_category_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_product_manufacturer_id_row">
                    <label for="product_manufacturer_id">Manufacturer</label>
                    <select tabindex="10" id="product_manufacturer_id" name="product_manufacturer_id">
                        <option value="">[All]</option>
			            <?php
			            $resultSet = executeReadQuery("select product_manufacturer_id,description from product_manufacturers where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			            while ($row = getNextRow($resultSet)) {
				            ?>
                            <option value="<?= $row['product_manufacturer_id'] ?>"><?= htmlText($row['description']) ?></option>
				            <?php
			            }
			            ?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_zero_quantity_row">
                    <input type="checkbox" name="include_zero_quantity" id="include_zero_quantity" value="1"><label class="checkbox-label" for="include_zero_quantity">Include products with zero quantity</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_only_negative_row">
                    <input type="checkbox" name="only_negative" id="only_negative" value="1"><label class="checkbox-label" for="only_negative">Only show products with negative availability (Total by Products only)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_product_id_row">
                    <label for="product_id">Product</label>
                    <input class="" type="hidden" id="product_id" name="product_id" value="">
                    <input tabindex="10" class="autocomplete-field" type="text" size="50" name="product_id_autocomplete_text" id="product_id_autocomplete_text" data-autocomplete_tag="products">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_inactive_row">
                    <input type="checkbox" name="include_inactive" id="include_inactive" value="1"><label class="checkbox-label" for="include_inactive">Include Inactive Products</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php storedReportDescription() ?>

                <div class="basic-form-line">
                    <button tabindex="10" id="create_report">Create Report</button>
                </div>

            </form>
        </div>
        <div id="_button_row">
            <button id="refresh_button">Refresh</button>
            <button id="new_parameters_button">Search Again</button>
            <button id="printable_button">Printable Report</button>
            <button id="pdf_button">Download PDF</button>
        </div>
        <h1 id="_report_title"></h1>
        <div id="_report_content">
        </div>
        <div id="_pdf_data" class="hidden">
            <form id="_pdf_form">
            </form>
        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#printable_button", function () {
                window.open("/printable.html");
                return false;
            });
            $(document).on("tap click", "#pdf_button", function () {
                $("#_pdf_form").html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("inventory.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    var reportType = $("#report_type").val();
                    if (reportType == "export" || reportType == "file" || reportType == "csv") {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function (returnArray) {
                            if ("report_content" in returnArray) {
                                $("#report_parameters").hide();
                                $("#_report_title").html(returnArray['report_title']).show();
                                $("#_report_content").html(returnArray['report_content']).show();
                                $("#_button_row").show();
                                $("html, body").animate({scrollTop: 0}, "slow");
                            }
                        });
                    }
                }
                return false;
            });
            $(document).on("tap click", "#new_parameters_button", function () {
                $("#report_parameters").show();
                $("#_report_title").hide();
                $("#_report_content").hide();
                $("#_button_row").hide();
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #report_parameters {
                width: 100%;
                margin-left: auto;
                margin-right: auto;
            }

            #_report_content {
                display: none;
            }

            #_report_content table td {
                font-size: .9rem;
            }

            #_button_row {
                display: none;
                margin-bottom: 20px;
            }

            .live-value {
                color: rgb(0, 200, 0);
                font-weight: bold;
            }
        </style>
        <style id="_printable_style">
            /*this style section will be used in the printable page and PDF document*/
            #_report_content {
                width: auto;
                display: block;
            }

            #_report_title {
                width: auto;
                display: block;
            }
        </style>
		<?php
	}
}

$pageObject = new InventoryReportPage();
$pageObject->displayPage();
