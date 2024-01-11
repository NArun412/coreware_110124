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

$GLOBALS['gPageCode'] = "SALESPROFITREPORT";
require_once "shared/startup.inc";

class SalesProfitReportPage extends Page implements BackgroundReport {

	private static function sortQuantity($a, $b) {
		if ($a['quantity'] == $b['quantity']) {
			return 0;
		}
		return ($a['quantity'] > $b['quantity'] ? -1 : 1);
	}

	function executePageUrlActions() {
		if ($_GET['url_action'] == "create_report") {
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

		processPresetDates($_POST['preset_dates'], "order_time_from", "order_time_to");

		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId']);
		if (!empty($_POST['order_time_from'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "date(order_time) >= ?";
			$parameters[] = $GLOBALS['gPrimaryDatabase']->makeDateParameter($_POST['order_time_from']);
		}
		if (!empty($_POST['order_time_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "date(order_time) <= ?";
			$parameters[] = $GLOBALS['gPrimaryDatabase']->makeDateParameter($_POST['order_time_to']);
		}
		if (!empty($_POST['shipping_method_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.shipping_method_id = ?";
			$parameters[] = $_POST['shipping_method_id'];
		}
		if (!empty($_POST['only_pickup'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.shipping_method_id in (select shipping_method_id from shipping_methods where pickup = 1)";
		}
		if (!empty($_POST['only_fulfillment'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.shipping_method_id in (select shipping_method_id from shipping_methods where pickup = 0)";
		}
		if (!empty($_POST['source_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.source_id = ?";
			$parameters[] = $_POST['source_id'];
		}
		if (!empty($_POST['product_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_id = ?";
			$parameters[] = $_POST['product_id'];
		}
		$productIds = array();
		foreach (explode(",", $_POST['products']) as $productId) {
			$productId = getReadFieldFromId("product_id", "products", "product_id", $productId);
			if (!empty($productId)) {
				$productIds[] = $productId;
			}
		}
		if (!empty($productIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "product_id in (" . implode(",", $productIds) . ")";
		}
		if (!empty($_POST['virtual_products'])) {
			switch ($_POST['virtual_products']) {
				case "exclude":
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "product_id in (select product_id from products where virtual_product = 0)";
					break;
				case "only":
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "product_id in (select product_id from products where virtual_product = 1)";
					break;
			}
		}

		if (!empty($_POST['order_status'])) {
			$tempOrderStatusArray = explode(",", $_POST['order_status']);
		} else {
			$tempOrderStatusArray = array();
		}
		$orderStatusArray = array();
		foreach ($tempOrderStatusArray as $thisOrderStatusId) {
			$orderStatusId = getReadFieldFromId("order_status_id", "order_status", "order_status_id", $thisOrderStatusId);
			if (!empty($orderStatusId)) {
				$orderStatusArray[] = $orderStatusId;
			}
		}
		if (count($orderStatusArray) > 0) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "order_status_id " . (empty($_POST['exclude_order_status']) ? "" : "not ") . "in (" . implode(",", $orderStatusArray) . ")";
		}

		$reportTotalQuantity = 0;
		$reportTotalAmount = 0;
		$reportTotalIncome = 0;
		$reportTotalCost = 0;
		$reportTotalProfit = 0;
		$resultSet = executeReadQuery("select *,(select location_id from order_shipments where order_shipment_id = order_shipment_items.order_shipment_id) location_id from " .
			"orders join order_items using (order_id) left outer join order_shipment_items using (order_item_id) where orders.client_id = ? and " .
			"orders.deleted = 0 and order_items.deleted = 0 and (order_shipment_id is null or order_shipment_id not in (select order_shipment_id from order_shipments where secondary_shipment = 1))" .
			(empty($whereStatement) ? "" : " and " . $whereStatement) . " order by order_time", $parameters);

		ob_start();
		switch ($_POST['report_type']) {
			case "csv":
				$returnArray['export_headers'] = array();
				$returnArray['export_headers'][] = "Content-Type: text/csv";
				$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"orders.csv\"";
				$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
				$returnArray['export_headers'][] = 'Pragma: public';
				$returnArray['filename'] = "orders.csv";

				echo '"OrderDate","OrderNumber","ProductCode","Description","UPC","Manufacturer","OrderStatus","ItemStatus","Source","Location","Quantity","Sale Price","Cost","Profit","% Profit"' . "\n";
				while ($row = getNextRow($resultSet)) {
					$productRow = getReadRowFromId("products", "product_id", $row['product_id']);
					if (strlen($row['cost']) == 0) {
						$row['cost'] = $productRow['base_cost'];
					}
					if (strlen($row['cost']) == 0) {
						continue;
					}
					$productDataRow = getReadRowFromId("product_data", "product_id", $row['product_id']);
					echo '"' . date("m/d/Y", strtotime($row['order_time'])) . '",';
					echo '"' . $row['order_number'] . '",';
					echo '"' . $productRow['product_code'] . '",';
					echo '"' . str_replace('"', '""', $productRow['description']) . '",';
					echo '"' . $productDataRow['upc_code'] . '",';
					echo '"' . getReadFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $productRow['product_manufacturer_id']) . '",';
					echo '"' . getReadFieldFromId("description", "order_status", "order_status_id", $row['order_status_id']) . '",';
					echo '"' . getReadFieldFromId("description", "order_item_statuses", "order_item_status_id", $row['order_item_status_id']) . '",';
					echo '"' . getReadFieldFromId("description", "sources", "source_id", $row['source_id']) . '",';
					echo '"' . getReadFieldFromId("description", "locations", "location_id", $row['location_id']) . '",';
					echo '"' . $row['quantity'] . '",';
					echo '"' . $row['sale_price'] . '",';
					echo '"' . $row['cost'] . '",';
					$profit = showSignificant(round(($row['sale_price'] - $row['cost']) * $row['quantity'], 2), 2);
					echo '"' . $profit . '",';
					$profitPercent = showSignificant(($row['sale_price'] == 0 || $row['quantity'] == 0 ? 0 : round(($profit * 100) / ($row['sale_price'] * $row['quantity']), 2)), 2);
					echo '"' . $profitPercent . '"' . "\r\n";
				}
				$returnArray['report_export'] = ob_get_clean();
				return $returnArray;
			case "detail":
				?>
                <table class="grid-table">
                    <tr>
                        <th>Product Code</th>
                        <th>Description</th>
                        <th>UPC</th>
                        <th>Manufacturer</th>
                        <th>Order Date</th>
                        <th>Order Number</th>
                        <th>Order Status</th>
                        <th>Item Status</th>
                        <th>Source</th>
                        <th>Location</th>
                        <th>Quantity</th>
                        <th>Sale Price</th>
                        <th>Cost</th>
                        <th>Profit</th>
                        <th>% Profit</th>
                    </tr>
					<?php
					$productArray = array();

					while ($row = getNextRow($resultSet)) {
						if (!array_key_exists($row['product_id'], $productArray)) {
							$productArray[$row['product_id']] = array();
						}
						$productArray[$row['product_id']][] = $row;
					}
					foreach ($productArray as $productId => $productRows) {
						$thisProductRow = getReadRowFromId("products", "product_id", $productId);
						$firstLine = true;
						$totalQuantity = 0;
						$totalAmount = 0;
						$totalCost = 0;
						$totalProfit = 0;
						foreach ($productRows as $row) {
							if (strlen($row['cost']) == 0) {
								$row['cost'] = $thisProductRow['base_cost'];
							}
							if (strlen($row['cost']) == 0) {
								continue;
							}
							$profit = showSignificant(round(($row['sale_price'] - $row['cost']) * $row['quantity'], 2), 2);
							$profitPercent = showSignificant(round(($row['sale_price'] == 0 || $row['quantity'] == 0 ? 0 : ($profit * 100) / ($row['sale_price'] * $row['quantity'])), 2), 2);
							?>
                            <tr>
                                <td><?= ($firstLine ? htmlText($thisProductRow['product_code']) : "") ?></td>
                                <td><?= ($firstLine ? htmlText($thisProductRow['description']) : "") ?></td>
                                <td><?= ($firstLine ? htmlText(getReadFieldFromId("upc_code", "product_data", "product_id", $productId)) : "") ?></td>
                                <td><?= ($firstLine ? htmlText(getReadFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $thisProductRow['product_manufacturer_id'])) : "") ?></td>
                                <td><?= date("m/d/Y", strtotime($row['order_time'])) ?></td>
                                <td><?= $row['order_number'] ?></td>
                                <td><?= getReadFieldFromId("description", "order_status", "order_status_id", $row['order_status_id']) ?></td>
                                <td><?= getReadFieldFromId("description", "order_item_statuses", "order_item_status_id", $row['order_item_status_id']) ?></td>
                                <td><?= getReadFieldFromId("description", "sources", "source_id", $row['source_id']) ?></td>
                                <td><?= getReadFieldFromId("description", "locations", "location_id", $row['location_id']) ?></td>
                                <td class="align-right"><?= $row['quantity'] ?></td>
                                <td class="align-right"><?= showSignificant($row['sale_price'], 2) ?></td>
                                <td class="align-right"><?= showSignificant($row['cost'], 2) ?></td>
                                <td class="align-right<?= ($row['cost'] > $row['sale_price'] ? " highlighted-text red-text" : "") ?>"><?= $profit ?></td>
                                <td class="align-right<?= ($row['cost'] > $row['sale_price'] ? " highlighted-text red-text" : "") ?>"><?= $profitPercent ?></td>
                            </tr>
							<?php
							$totalQuantity += $row['quantity'];
							$totalAmount += ($row['quantity'] * $row['sale_price']);
							$totalCost += ($row['quantity'] * $row['cost']);
							$totalProfit += ($row['sale_price'] - $row['cost']) * $row['quantity'];
							$firstLine = false;
						}
						$reportTotalQuantity += $totalQuantity;
						$reportTotalAmount += $totalAmount;
						$reportTotalCost += $totalCost;
						$reportTotalProfit += $totalProfit;
						$totalProfitPercent = showSignificant(($totalAmount == 0 ? 0 : $totalProfit * 100 / $totalAmount), 2);
						?>
                        <tr class='total-line'>
                            <td class="highlighted-text"><?= htmlText(getReadFieldFromId("product_code", "products", "product_id", $productId)) ?></td>
                            <td class="highlighted-text"><?= htmlText(getReadFieldFromId("description", "products", "product_id", $productId)) ?></td>
                            <td class="highlighted-text"><?= htmlText(getReadFieldFromId("upc_code", "product_data", "product_id", $productId)) ?></td>
                            <td colspan="7"></td>
                            <td class="highlighted-text align-right"><?= $totalQuantity ?></td>
                            <td class="highlighted-text align-right"><?= showSignificant($totalAmount, 2) ?></td>
                            <td class="highlighted-text align-right"><?= showSignificant($totalCost, 2) ?></td>
                            <td class="highlighted-text align-right"><?= showSignificant($totalProfit, 2) ?></td>
                            <td class="highlighted-text align-right"><?= showSignificant($totalProfitPercent, 2) ?></td>
                        </tr>
						<?php
					}
					$reportTotalProfitPercent = showSignificant(($reportTotalAmount == 0 ? 0 : round($reportTotalProfit * 100 / $reportTotalAmount, 2)), 2);
					?>
                    <tr class='total-line'>
                        <td class="highlighted-text">Report Total</td>
                        <td colspan="9"></td>
                        <td class="highlighted-text align-right"><?= $reportTotalQuantity ?></td>
                        <td class="highlighted-text align-right"><?= showSignificant($reportTotalAmount, 2) ?></td>
                        <td class="highlighted-text align-right"><?= showSignificant($reportTotalCost, 2) ?></td>
                        <td class="highlighted-text align-right"><?= showSignificant($reportTotalProfit, 2) ?></td>
                        <td class="highlighted-text align-right"><?= showSignificant($reportTotalProfitPercent, 2) ?></td>
                    </tr>
                </table>
				<?php
				break;
			case "summary":
				$productArray = array();
				$resultSet = executeReadQuery("select * from orders join order_items using (order_id) left outer join order_shipment_items using (order_item_id) where " .
					"orders.client_id = ? and orders.deleted = 0 and order_items.deleted = 0 and " .
					"(order_shipment_id is null or order_shipment_id not in (select order_shipment_id from order_shipments where secondary_shipment = 1))" .
					(empty($whereStatement) ? "" : " and " . $whereStatement) . " order by order_time", $parameters);
				while ($row = getNextRow($resultSet)) {
					if (strlen($row['cost']) == 0) {
						$row['cost'] = getFieldFromId("base_cost", "products", "product_id", $row['product_id']);
					}
					if (strlen($row['cost']) == 0) {
						continue;
					}
					if (!array_key_exists($row['product_id'], $productArray)) {
						$productArray[$row['product_id']] = array("product_id" => $row['product_id'], "quantity" => "0", "total_amount" => "0", "total_cost" => "0");
					}
					$productArray[$row['product_id']]['quantity'] += $row['quantity'];
					$productArray[$row['product_id']]['total_amount'] += round($row['quantity'] * $row['sale_price'], 2);
					$productArray[$row['product_id']]['total_cost'] += round($row['quantity'] * $row['cost'], 2);
				}
				if (!empty($_POST['sort_quantity'])) {
					usort($productArray, array(static::class, "sortQuantity"));
				}
				?>
                <table class="grid-table">
                    <tr>
                        <th>Product Code</th>
                        <th>Description</th>
                        <th>UPC</th>
                        <th>Quantity</th>
                        <th>Total Income</th>
                        <th>Average Price</th>
                        <th>Total Profit</th>
                        <th>Profit Percent</th>
                    </tr>
					<?php
					foreach ($productArray as $productInfo) {
						$productId = $productInfo['product_id'];
						$profitPercent = ($productInfo['total_amount'] == 0 ? 0 : (($productInfo['total_amount'] - $productInfo['total_cost']) * 100 / $productInfo['total_amount']));
						?>
                        <tr>
                            <td><?= htmlText(getReadFieldFromId("product_code", "products", "product_id", $productId)) ?></td>
                            <td><?= htmlText(getReadFieldFromId("description", "products", "product_id", $productId)) ?></td>
                            <td><?= htmlText(getReadFieldFromId("upc_code", "product_data", "product_id", $productId)) ?></td>
                            <td class="align-right"><?= $productInfo['quantity'] ?></td>
                            <td class="align-right"><?= showSignificant($productInfo['total_amount'], 2) ?></td>
                            <td class="align-right"><?= showSignificant(($productInfo['quantity'] == 0 ? 0 : round($productInfo['total_amount'] / $productInfo['quantity'], 2)), 2) ?></td>
                            <td class="align-right<?= ($productInfo['total_cost'] > $productInfo['total_amount'] ? " red-text" : "") ?>"><?= showSignificant($productInfo['total_amount'] - $productInfo['total_cost'], 2) ?></td>
                            <td class="align-right"><?= showSignificant($profitPercent, 2) ?></td>
                        </tr>
						<?php
						$reportTotalQuantity += $productInfo['quantity'];
						$reportTotalIncome += $productInfo['total_amount'];
						$reportTotalProfit += $productInfo['total_amount'] - $productInfo['total_cost'];
					}
					$reportTotalProfitPercent = ($reportTotalIncome == 0 ? 0 : $reportTotalProfit * 100 / $reportTotalIncome);
					?>
                    <tr class='total-line'>
                        <td class="highlighted-text " colspan="3">Report Total</td>
                        <td class="highlighted-text align-right"><?= $reportTotalQuantity ?></td>
                        <td class="highlighted-text align-right"><?= $reportTotalIncome ?></td>
                        <td></td>
                        <td class="highlighted-text align-right"><?= showSignificant($reportTotalProfit, 2) ?></td>
                        <td class="highlighted-text align-right"><?= showSignificant($reportTotalProfitPercent, 2) ?></td>
                    </tr>
                </table>
				<?php
				break;
		}
		$returnArray['report_title'] = "Orders Report";
		$returnArray['report_content'] = ob_get_clean();
		return $returnArray;
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="summary">Summary</option>
                        <option selected value="detail">Detail</option>
                        <option value="csv">Export CSV</option>
                    </select>
                    <div class='clear-div'></div>
                </div>

				<?php getPresetDateOptions() ?>

                <div class="form-line preset-date-custom" id="_order_time_row">
                    <label for="order_time_from">Order Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="order_time_from" name="order_time_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="order_time_to" name="order_time_to">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_shipping_method_id_row">
                    <label for="shipping_method_id">Shipping Method</label>
                    <select tabindex="10" id="shipping_method_id" name="shipping_method_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from shipping_methods where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['shipping_method_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_only_pickup_row">
                    <input type="checkbox" name="only_pickup" id="only_pickup" value="1"><label class="checkbox-label" for="only_pickup">Include only pickup shipping methods</label>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_only_fulfillment_row">
                    <input type="checkbox" name="only_fulfillment" id="only_fulfillment" value="1"><label class="checkbox-label" for="only_fulfillment">Include only fulfillment shipping methods</label>
                    <div class='clear-div'></div>
                </div>

				<?php
				$resultSet = executeReadQuery("select * from sources where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
				if ($resultSet['row_count'] > 0) {
					?>
                    <div class="form-line" id="_source_id_row">
                        <label for="source_id">Source</label>
                        <select tabindex="10" id="source_id" name="source_id">
                            <option value="">[All]</option>
							<?php
							while ($row = getNextRow($resultSet)) {
								?>
                                <option value="<?= $row['source_id'] ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='clear-div'></div>
                    </div>
				<?php } ?>

				<?php
				$orderStatusControl = new DataColumn("order_status");
				$orderStatusControl->setControlValue("data_type", "custom");
				$orderStatusControl->setControlValue("include_inactive", "true");
				$orderStatusControl->setControlValue("control_class", "MultiSelect");
				$orderStatusControl->setControlValue("control_table", "order_status");
				$orderStatusControl->setControlValue("links_table", "orders");
				$orderStatusControl->setControlValue("primary_table", "orders");
				$customControl = new MultipleSelect($orderStatusControl, $this);
				?>
                <div class="form-line" id="_order_status_row">
                    <label for="order_status">Include only these order statuses</label>
					<?= $customControl->getControl() ?>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_exclude_order_status_row">
                    <input type="checkbox" name="exclude_order_status" id="exclude_order_status" value="1"><label class="checkbox-label" for="exclude_order_status">Exclude (instead of include) the previous statuses</label>
                    <div class='clear-div'></div>
                </div>


                <div class="form-line" id="_virtual_products_row">
                    <label for="virtual_products">Virtual Products</label>
                    <select tabindex="10" id="virtual_products" name="virtual_products">
                        <option value="">Include</option>
                        <option value="exclude">Exclude</option>
                        <option value="only">Only</option>
                    </select>
                    <div class='clear-div'></div>
                </div>

				<?php
				$productCount = 0;
				$resultSet = executeReadQuery("select count(*) from products where client_id = ?", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$productCount = $row['count(*)'];
				}
				if ($productCount < 100) {
					$productsControl = new DataColumn("products");
					$productsControl->setControlValue("data_type", "custom");
					$productsControl->setControlValue("include_inactive", "true");
					$productsControl->setControlValue("control_class", "MultiSelect");
					$productsControl->setControlValue("control_table", "products");
					$productsControl->setControlValue("links_table", "product_category_links");
					$productsControl->setControlValue("primary_table", "product_categories");
					$customControl = new MultipleSelect($productsControl, $this);
					?>
                    <div class="form-line" id="_products_row">
                        <label for="products">Products</label>
						<?= $customControl->getControl() ?>
                        <div class='clear-div'></div>
                    </div>
				<?php } else { ?>
					<?= createFormControl("order_items", "product_id", array("not_null" => false, "help_label" => "leave blank to include all")) ?>
				<?php } ?>

                <div class="form-line" id="_sort_quantity_row">
                    <label></label>
                    <input tabindex="10" type="checkbox" id="sort_quantity" name="sort_quantity"><label class="checkbox-label" for="sort_quantity">Sort by Quantity (Summary Report Only)</label>
                    <div class='clear-div'></div>
                </div>

				<?php storedReportDescription() ?>

                <div class="form-line">
                    <label></label>
                    <button tabindex="10" id="create_report">Create Report</button>
                    <div class='clear-div'></div>
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("orders.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    let reportType = $("#report_type").val();
                    if (reportType === "csv") {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function(returnArray) {
                            if ("error_message" in returnArray) {
                                $("#_error_message").html(returnArray['error_message']);
                            } else {
                                $("#report_parameters").hide();
                                $("#_report_title").html(returnArray['report_title']).show();
                                $("#_report_content").html(returnArray['report_content']).show();
                                $("#_button_row").show();
                                $("html, body").animate({ scrollTop: 0 }, "slow");
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

	function hiddenElements() {
		?>
        <div id="autocomplete_options">
            <ul>
            </ul>
        </div>
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
                font-size: 13px;
            }

            #_button_row {
                display: none;
                margin-bottom: 20px;
            }

            tr.total-line td {
                background-color: rgb(240, 240, 240);
            }
        </style>
		<?php
	}
}

$pageObject = new SalesProfitReportPage();
$pageObject->displayPage();
