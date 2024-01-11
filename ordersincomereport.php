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

$GLOBALS['gPageCode'] = "ORDERSINCOMEREPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 120000;

class OrdersIncomeReportPage extends Page implements BackgroundReport {

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

		processPresetDates($_POST['preset_dates'], "report_date_from", "report_date_to");

		$fullName = getUserDisplayName($GLOBALS['gUserId']);

		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId']);
		$displayCriteria = "";

		if (!empty($_POST['report_date_from'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.order_time >= ?";
			$parameters[] = date("Y-m-d", strtotime($_POST['report_date_from']));
		}
		if (!empty($_POST['report_date_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.order_time <= ?";
			$parameters[] = date("Y-m-d", strtotime($_POST['report_date_to'])) . " 23:59:59";
		}
		if (!empty($_POST['report_date_from']) && !empty($_POST['report_date_to'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Report date is between " . date("m/d/Y", strtotime($_POST['report_date_from'])) . " and " . date("m/d/Y", strtotime($_POST['report_date_to']));
		} else {
			if (!empty($_POST['report_date_from'])) {
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Report date is on or after " . date("m/d/Y", strtotime($_POST['report_date_from']));
			} else {
				if (!empty($_POST['report_date_to'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Report date is on or before " . date("m/d/Y", strtotime($_POST['donation_date_to']));
				}
			}
		}

		if (!empty($_POST['order_status'])) {
			$tempOrderStatusArray = explode(",", $_POST['order_status']);
		} else {
			$tempOrderStatusArray = array();
		}
		$orderStatusArray = array();
		foreach ($tempOrderStatusArray as $thisOrderStatusId) {
			$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_id", $thisOrderStatusId);
			if (!empty($orderStatusId)) {
				$orderStatusArray[] = $orderStatusId;
			}
		}
		if (count($orderStatusArray) > 0) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			if (empty($_POST['exclude_order_status'])) {
				$whereStatement .= "order_status_id in (" . implode(",", $orderStatusArray) . ")";
			} else {
				$whereStatement .= "(order_status_id not in (" . implode(",", $orderStatusArray) . ") or order_status_id is null)";
			}
		}

		if (!empty($_POST['product_categories'])) {
			$tempProductCategoryArray = explode(",", $_POST['product_categories']);
		} else {
			$tempProductCategoryArray = array();
		}
		$productCategoryArray = array();
		foreach ($tempProductCategoryArray as $thisProductCategoryId) {
			$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $thisProductCategoryId);
			if (!empty($productCategoryId)) {
				$productCategoryArray[] = $productCategoryId;
			}
		}
		if (count($productCategoryArray) > 0) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_id in (select product_id from product_category_links where product_category_id in (" . implode(",", $productCategoryArray) . "))";
		}

		if (!empty($_POST['payment_method_type_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "order_id in (select order_id from order_payments where payment_method_id in (select payment_method_id from payment_methods where payment_method_type_id = ?))";
			$parameters[] = $_POST['payment_method_type_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Payment Method Type is " . getFieldFromId("description", "payment_method_types", "payment_method_type_id", $_POST['payment_method_type_id']);
		}
		if (!empty($_POST['location_id'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Location is " . getFieldFromId("description", "locations", "location_id", $_POST['location_id']);
		}

		if (!empty($_POST['source_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "source_id = ?";
			$parameters[] = $_POST['source_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Source is " . getFieldFromId("description", "sources", "source_id", $_POST['source_id']);
		}

		ob_start();

		$departments = array();
		$resultSet = executeReadQuery("select * from product_departments where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$departments[$row['product_department_code']] = $row;
		}

		$departmentGroups = array();
		$resultSet = executeReadQuery("select * from product_department_groups where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$departmentGroups[$row['product_department_group_code']] = $row;
		}
		$resultSet = executeReadQuery("select product_department_group_code, product_department_code from product_department_groups 
            join product_department_group_links using (product_department_group_id) 
            join product_departments using (product_department_id) where product_department_groups.client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$departmentGroups[$row['product_department_group_code']][$row['product_department_code']] = true;
		}

		$paymentMethodTypes = array();
		$resultSet = executeReadQuery("select * from payment_method_types where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$paymentMethodTypes[] = $row;
		}

		$shippingMethodLocations = array();
		$resultSet = executeQuery("select * from shipping_methods where client_id = ? and location_id is not null", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$shippingMethodLocations[$row['shipping_method_id']] = $row['location_id'];
		}
		$eventLocations = array();
		$resultSet = executeQuery("select * from events where product_id is not null and client_id = ? and location_id is not null", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$eventLocations[$row['product_id']] = $row['location_id'];
		}
		$locations = array();
		$resultSet = executeQuery("select * from locations where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$locations[$row['location_id']] = $row['description'];
		}
		$sources = array();
		$resultSet = executeQuery("select * from sources where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$sources[$row['source_id']] = $row['description'];
		}
		$productCategories = array();
		$resultSet = executeQuery("select * from product_categories where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$productCategories[$row['product_category_id']] = $row['description'];
		}

		$resultSet = executeReadQuery("select *,(select sum(sale_price * quantity) from order_items where order_id = orders.order_id and deleted = 0) as cart_total, " .
            "(select product_category_id from product_category_links where product_id = products.product_id order by sequence_number limit 1) as product_category_id from orders"
			. " join order_items using (order_id) join products using (product_id) left outer join product_data using (product_id) where orders.client_id = ? and orders.deleted = 0 and order_items.deleted = 0"
			. (!empty($whereStatement) ? " and " . $whereStatement : "") . " order by order_time,order_id,order_item_id", $parameters);
		$returnArray['report_title'] = "Orders Income Report";
		if ($_POST['report_type'] == "csv") {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"ordersincome.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "ordersincome.csv";

			$fieldNames = "Order ID,Order Date,Time,Date Completed,Status,Location,Source,Product Code,UPC Code,Description,Department,Product Category,Quantity,Sale Price,Subtotal";
			foreach ($paymentMethodTypes as $thisPaymentMethodType) {
				$fieldNames .= "," . $thisPaymentMethodType['description'];
			}
			$fieldNames .= ",Cart Total,Shipping,Handling,Tax,Discount,Total";
			echo $fieldNames . "\n";
		} else {
			?>
            <p><?= $displayCriteria ?></p>
            <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
            <table class="grid-table">
			<?php
			if ($_POST['report_type'] == 'detail') {
				?>
                <tr>
                    <th>Order ID</th>
                    <th>Order Date</th>
                    <th>Time</th>
                    <th>Date Completed</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Source</th>
                    <th>Product Code</th>
                    <th>UPC Code</th>
                    <th>Description</th>
                    <th>Department</th>
                    <th>Product Category</th>
                    <th>Quantity</th>
                    <th>Sale Price</th>
                    <th>Subtotal</th>
					<?php
					foreach ($paymentMethodTypes as $thisPaymentMethodType) {
						?>
                        <th><?= htmlText($thisPaymentMethodType['description']) ?></th>
						<?php
					}
					?>
                    <th>Cart Total</th>
                    <th>Shipping</th>
                    <th>Handling</th>
                    <th>Tax</th>
                    <th>Discount</th>
                    <th>Total</th>
                </tr>
				<?php
			} else {
				?>
                <tr>
                    <th><?= ($_POST['report_type'] == 'summary_department') ? 'Department' : 'Department Group' ?></th>
                    <th>Product Total</th>
                    <th>Shipping</th>
                    <th>Handling</th>
                    <th>Tax</th>
                    <th>Discount</th>
                    <th>Total</th>
                </tr>
				<?php
			}
		}
		$saveOrderId = "";
		$summaryTotals = array();
		while ($row = getNextRow($resultSet)) {
			$locationId = $shippingMethodLocations[$row['shipping_method_id']];
			if (empty($locationId)) {
				$locationId = $eventLocations[$row['product_id']];
			}
			if (!empty($_POST['location_id']) && $locationId != $_POST['location_id']) {
				continue;
			}

			$departmentDescription = "";
			$summaryCode = "";
			foreach ($departments as $thisDepartment) {
				if (ProductCatalog::productIsInDepartment($row['product_id'], $thisDepartment['product_department_id'])) {
					$departmentDescription = $thisDepartment['description'];
					if ($_POST['report_type'] == 'summary_department') {
						$summaryCode = $thisDepartment['product_department_code'];
					} else {
						foreach ($departmentGroups as $thisDepartmentGroup) {
							if ($thisDepartmentGroup[$thisDepartment['product_department_code']]) {
								$summaryCode = $thisDepartmentGroup['product_department_group_code'];
							}
						}
					}
					break;
				}
			}
			if (empty($departmentDescription)) {
				$departmentDescription = "(No Department)";
				$summaryCode = '_NONE';
			}
			if ($_POST['report_type'] == "csv") {
				echo '"' . str_replace('"', '', $row['order_id']) . '",' .
					'"' . str_replace('"', '', date("m/d/Y", strtotime($row['order_time']))) . '",' .
					'"' . str_replace('"', '', date("g:i a", strtotime($row['order_time']))) . '",' .
					'"' . str_replace('"', '', empty($row['date_completed']) ? "" : date("m/d/Y", strtotime($row['date_completed']))) . '",' .
					'"' . str_replace('"', '', getFieldFromId("description", "order_status", "order_status_id", $row['order_status_id'])) . '",' .
					'"' . str_replace('"', '', htmlText($locations[$locationId])) . '",' .
					'"' . str_replace('"', '', htmlText($sources[$row['source_id']])) . '",' .
					'"' . str_replace('"', '', htmlText($row['product_code'])) . '",' .
					'"' . str_replace('"', '', htmlText($row['upc_code'])) . '",' .
					'"' . str_replace('"', '', htmlText($row['description'])) . '",' .
					'"' . str_replace('"', '', htmlText($departmentDescription)) . '",' .
					'"' . str_replace('"', '', htmlText($productCategories[$row['product_category_id']])) . '",' .
					'"' . str_replace('"', '', htmlText($row['quantity'])) . '",' .
					'"' . str_replace('"', '', number_format($row['sale_price'], 2)) . '",' .
					'"' . str_replace('"', '', number_format($row['quantity'] * $row['sale_price'], 2)) . '",';
				foreach ($paymentMethodTypes as $thisPaymentMethodType) {
					$paymentAmount = "";
					$paymentSet = executeReadQuery("select sum(amount + shipping_charge + handling_charge + tax_charge) as payment_amount from order_payments where payment_method_id in (select payment_method_id from payment_methods where payment_method_type_id = ?) and order_id = ?", $thisPaymentMethodType['payment_method_type_id'], $row['order_id']);
					if ($paymentRow = getNextRow($paymentSet)) {
						$paymentAmount = $paymentRow['payment_amount'];
					}
					echo '"' . number_format($paymentAmount, 2) . '",';
				}
				echo '"' . str_replace('"', '', number_format($row['cart_total'], 2)) . '",' .
					'"' . str_replace('"', '', number_format($row['shipping_charge'], 2)) . '",' .
					'"' . str_replace('"', '', number_format($row['handling_charge'], 2)) . '",' .
					'"' . str_replace('"', '', number_format($row['tax_charge'], 2)) . '",' .
					'"' . str_replace('"', '', number_format($row['order_discount'], 2)) . '",' .
					'"' . number_format($row['cart_total'] + $row['shipping_charge'] + $row['handling_charge'] + $row['tax_charge'] - $row['order_discount'], 2) . '"' . "\n";
			} elseif ($_POST['report_type'] == 'detail') {
				?>
                <tr>
                    <td><?= htmlText(($saveOrderId == $row['order_id'] ? "" : $row['order_id'])) ?></td>
                    <td><?= ($saveOrderId == $row['order_id'] ? "" : date("m/d/Y", strtotime($row['order_time']))) ?></td>
                    <td><?= ($saveOrderId == $row['order_id'] ? "" : date("g:i a", strtotime($row['order_time']))) ?></td>
                    <td><?= ($saveOrderId == $row['order_id'] ? "" : (empty($row['date_completed']) ? "" : date("m/d/Y", strtotime($row['date_completed'])))) ?></td>
                    <td><?= htmlText(getFieldFromId("description", "order_status", "order_status_id", $row['order_status_id'])) ?></td>
                    <td><?= htmlText($locations[$locationId]) ?></td>
                    <td><?= htmlText($sources[$row['source_id']]) ?></td>
                    <td><?= htmlText($row['product_code']) ?></td>
                    <td><?= htmlText($row['upc_code']) ?></td>
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= htmlText($departmentDescription) ?></td>
                    <td><?= htmlText($productCategories[$row['product_category_id']]) ?></td>
                    <td class='align-right'><?= $row['quantity'] ?></td>
                    <td class='align-right'><?= number_format($row['sale_price'], 2) ?></td>
                    <td class='align-right'><?= number_format($row['quantity'] * $row['sale_price'], 2) ?></td>
					<?php
					foreach ($paymentMethodTypes as $thisPaymentMethodType) {
						$paymentAmount = "";
						$paymentSet = executeReadQuery("select sum(amount + shipping_charge + handling_charge + tax_charge) as payment_amount from order_payments where payment_method_id in (select payment_method_id from payment_methods where payment_method_type_id = ?) and order_id = ?", $thisPaymentMethodType['payment_method_type_id'], $row['order_id']);
						if ($paymentRow = getNextRow($paymentSet)) {
							$paymentAmount = $paymentRow['payment_amount'];
						}
						?>
                        <td class='align-right'><?= ($saveOrderId == $row['order_id'] ? "" : number_format($paymentAmount, 2)) ?></td>
						<?php
					}
					?>
                    <td class='align-right'><?= ($saveOrderId == $row['order_id'] ? "" : number_format($row['cart_total'], 2)) ?></td>
                    <td class='align-right'><?= ($saveOrderId == $row['order_id'] ? "" : number_format($row['shipping_charge'], 2)) ?></td>
                    <td class='align-right'><?= ($saveOrderId == $row['order_id'] ? "" : number_format($row['handling_charge'], 2)) ?></td>
                    <td class='align-right'><?= ($saveOrderId == $row['order_id'] ? "" : number_format($row['tax_charge'], 2)) ?></td>
                    <td class='align-right'><?= ($saveOrderId == $row['order_id'] ? "" : number_format($row['order_discount'], 2)) ?></td>
                    <td class='align-right'><?= ($saveOrderId == $row['order_id'] ? "" : number_format($row['cart_total'] + $row['shipping_charge'] + $row['handling_charge'] + $row['tax_charge'] - $row['order_discount'], 2)) ?></td>
                </tr>
				<?php
			} else {
				$summaryTotals[$summaryCode]['product_total'] += $row['quantity'] * $row['sale_price'];
				$summaryTotals[$summaryCode]['shipping_charge'] += $row['shipping_charge'];
				$summaryTotals[$summaryCode]['handling_charge'] += $row['handling_charge'];
				$summaryTotals[$summaryCode]['tax_charge'] += $row['tax_charge'];
				$summaryTotals[$summaryCode]['order_discount'] += $row['order_discount'];
				$summaryTotals[$summaryCode]['total'] += (($row['quantity'] * $row['sale_price']) + $row['shipping_charge'] + $row['handling_charge'] + $row['tax_charge'] - $row['order_discount']);
			}
			$saveOrderId = $row['order_id'];
		}
		if ($_POST['report_type'] == "csv") {
			$returnArray['report_export'] = ob_get_clean();
		} elseif ($_POST['report_type'] == 'detail') {
			echo '</table>';
			$returnArray['report_content'] = ob_get_clean();
		} else {
			ksort($summaryTotals);
			foreach ($summaryTotals as $thisSummaryCode => $thisSummaryTotal) {
				$description = $_POST['report_type'] == 'summary_department' ? $departments[$thisSummaryCode]['description'] : $departmentGroups[$thisSummaryCode]['description'];
				?>
                <tr>
                    <td><?= htmlText($thisSummaryCode == '_NONE' ? "(No Department)" : $description) ?></td>
                    <td class='align-right'><?= number_format($thisSummaryTotal['product_total'], 2) ?></td>
                    <td class='align-right'><?= number_format($thisSummaryTotal['shipping_charge'], 2) ?></td>
                    <td class='align-right'><?= number_format($thisSummaryTotal['handling_charge'], 2) ?></td>
                    <td class='align-right'><?= number_format($thisSummaryTotal['tax_charge'], 2) ?></td>
                    <td class='align-right'><?= number_format($thisSummaryTotal['order_discount'], 2) ?></td>
                    <td class='align-right'><?= number_format($thisSummaryTotal['total'], 2) ?></td>
                </tr>
				<?php
			}
			?>
            </table>
			<?php
			$returnArray['report_content'] = ob_get_clean();
		}
		return $returnArray;
	}

	function mainContent() {

# The report form is where the user can set parameters for how the report would be run.

		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="summary_department">Summary by Department</option>
                        <option value="summary_department_group">Summary by Department Group</option>
                        <option value="detail">Details</option>
                        <option value="csv">CSV Export</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php getPresetDateOptions() ?>

                <div class="basic-form-line preset-date-custom" id="_report_date_row">
                    <label for="report_date_from">Report Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_from" name="report_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_to" name="report_date_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_payment_method_type_id_row">
                    <label for="payment_method_type_id">Payment Method Type</label>
                    <select tabindex="10" id="payment_method_type_id" name="payment_method_type_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from payment_method_types where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['payment_method_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_location_id_row">
                    <label for="location_id">Location</label>
                    <select tabindex="10" id="location_id" name="location_id">
                        <option value="">[All]</option>
			            <?php
			            $resultSet = executeReadQuery("select * from locations where client_id = ? and product_distributor_id is null and user_location = 0 order by sort_order,description", $GLOBALS['gClientId']);
			            while ($row = getNextRow($resultSet)) {
				            ?>
                            <option value="<?= $row['location_id'] ?>"><?= htmlText($row['description']) ?></option>
				            <?php
			            }
			            ?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_source_id_row">
                    <label for="source_id">Source</label>
                    <select tabindex="10" id="source_id" name="source_id">
                        <option value="">[All]</option>
			            <?php
			            $resultSet = executeReadQuery("select * from sources where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			            while ($row = getNextRow($resultSet)) {
				            ?>
                            <option value="<?= $row['source_id'] ?>"><?= htmlText($row['description']) ?></option>
				            <?php
			            }
			            ?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

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
                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_order_status_row">
                    <label for="order_status">Include only these order statuses</label>
		            <?= $customControl->getControl() ?>
                </div>

	            <?php
	            $productCategoryControl = new DataColumn("product_categories");
	            $productCategoryControl->setControlValue("data_type", "custom");
	            $productCategoryControl->setControlValue("include_inactive", "true");
	            $productCategoryControl->setControlValue("control_class", "MultiSelect");
	            $productCategoryControl->setControlValue("control_table", "product_categories");
	            $productCategoryControl->setControlValue("links_table", "product_category_links");
	            $productCategoryControl->setControlValue("primary_table", "product_category_links");
	            $customControl = new MultipleSelect($productCategoryControl, $this);
	            ?>
                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_product_categories_row">
                    <label for="order_status">Include only products in these categories</label>
		            <?= $customControl->getControl() ?>
                </div>

                <div class="basic-form-line" id="_exclude_order_status_row">
                    <input type="checkbox" name="exclude_order_status" id="exclude_order_status" value="1"><label class="checkbox-label" for="exclude_order_status">Exclude (instead of include) the previous statuses</label>
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
                const $pdfForm = $("#_pdf_form");
                $pdfForm.html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $pdfForm.append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $pdfForm.append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $pdfForm.append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("ordersincome.pdf");
                $pdfForm.append($(input));
                $pdfForm.attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                const $reportForm = $("#_report_form");
                if ($reportForm.validationEngine("validate")) {
                    const reportType = $("#report_type").val();
                    if (reportType === "csv") {
                        $reportForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $reportForm.serialize(), function(returnArray) {
                            if ("report_content" in returnArray) {
                                $("#report_parameters").hide();
                                $("#_report_title").html(returnArray['report_title']).show();
                                $("#_report_content").html(returnArray['report_content']).show();
                                $("#_button_row").show();
                                $("html, body").animate({ scrollTop: 0 }, 600);
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

$pageObject = new OrdersIncomeReportPage();
$pageObject->displayPage();
