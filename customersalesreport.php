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

$GLOBALS['gPageCode'] = "CUSTOMERSALESREPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class CustomerSalesReportPage extends Page implements BackgroundReport {

	private static function sortOrders($a, $b) {
		if ($a['total_sales'] == $b['total_sales']) {
			return 0;
		}
		return ($a['total_sales'] > $b['total_sales'] ? -1 : 1);
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
		if (!empty($_POST['order_method_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.order_method_id = ?";
			$parameters[] = $_POST['order_method_id'];
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
		if (!empty($_POST['payment_method_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "order_id in (select order_id from order_payments where payment_method_id = ?)";
			$parameters[] = $_POST['payment_method_id'];
		}
		if (!empty($_POST['state'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "((address_id is not null and address_id in (select address_id from addresses where contact_id = orders.contact_id and state = ?)) or " .
				"(address_id is null and contacts.state = ?))";
			$parameters[] = $_POST['state'];
			$parameters[] = $_POST['state'];
		}
		if (strlen($_POST['order_total']) > 0) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "order_id in (select order_id from (select order_id,sum(quantity * sale_price) from order_items group by order_id having sum(quantity * sale_price) > ?) as sum_table)";
			$parameters[] = $_POST['order_total'];
		}
		if (strlen($_POST['tax_exempt']) > 0) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.tax_exempt = " . ($_POST['tax_exempt'] == 1 ? 1 : 0);;
		}

		if (strlen($_POST['completed']) > 0) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.date_completed is " . ($_POST['completed'] == 1 ? "not " : "") . "null";
		}

		if (strlen($_POST['deleted']) > 0) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.deleted = " . ($_POST['deleted'] == 1 ? 1 : 0);
		}

		if (!empty($_POST['postal_code_start'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "((address_id is not null and address_id in (select address_id from addresses where contact_id = orders.contact_id and postal_code >= ?)) or " .
				"(address_id is null and contacts.postal_code >= ?))";
			$parameters[] = $_POST['postal_code_start'];
			$parameters[] = $_POST['postal_code_start'];
		}
		if (!empty($_POST['postal_code_end'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "((address_id is not null and address_id in (select address_id from addresses where contact_id = orders.contact_id and postal_code <= ?)) or " .
				"(address_id is null and contacts.postal_code <= ?))";
			$parameters[] = $_POST['postal_code_end'];
			$parameters[] = $_POST['postal_code_end'];
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
		if (!empty($_POST['not_free'])) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "order_id in (select order_id from order_items where sale_price > 0)";
		}
		if (!empty($_POST['virtual_products'])) {
			switch ($_POST['virtual_products']) {
				case "exclude":
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "order_id in (select order_id from order_items where product_id in (select product_id from products where virtual_product = 0))";
					break;
				case "only":
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "order_id in (select order_id from order_items where product_id in (select product_id from products where virtual_product = 1))";
					break;
			}
		}
		$pageId = getFieldFromId("page_id","pages","page_code","CONTACTMAINT");

		ob_start();
		$resultSet = executeReadQuery("select *,(select description from sources where source_id = orders.source_id) source_description, " .
			"(select sum(quantity * sale_price) from order_items where order_id = orders.order_id) cart_total," .
			"(select sum(quantity * base_cost) from order_items where order_id = orders.order_id) cart_cost," .
			"(select description from order_status where order_status_id = orders.order_status_id) status_description," .
			"(select concat_ws(' ',first_name,last_name) from contacts where contact_id = orders.referral_contact_id) referrer_name," .
			"(select description from shipping_methods where shipping_method_id = orders.shipping_method_id) shipping_method_description," .
			"(select state from addresses where address_id = orders.address_id) address_state, " .
			"(select payment_method_id from order_payments where order_id = orders.order_id order by amount desc limit 1) order_payment_method_id, " .
			"(select account_id from order_payments where order_id = orders.order_id order by amount desc limit 1) payment_account_id, " .
			"(select description from payment_methods where payment_method_id = (select payment_method_id from order_payments where order_id = orders.order_id order by amount desc limit 1)) order_payment_method_description " .
			"from contacts join orders using (contact_id) where orders.client_id = ?" .
			(empty($whereStatement) ? "" : " and " . $whereStatement) . " order by contact_id", $parameters);
		$detailReport = ($_POST['report_type'] == "detail");
		$exportReport = ($_POST['report_type'] == "csv");
		$orderArray = array();
		while ($row = getNextRow($resultSet)) {
		    if (!empty($_POST['select_contacts'])) {
                executeQuery("insert ignore into selected_rows (user_id,page_id,primary_identifier) values (?,?,?)",$GLOBALS['gUserId'],$pageId,$row['contact_id']);
            }
			if (!array_key_exists($row['contact_id'], $orderArray)) {
				$orderArray[$row['contact_id']] = array("orders" => array(), "total_sales" => 0);
			}
			if (empty($row['address_state'])) {
				$row['address_state'] = $row['state'];
			}
			$row['order_date'] = date("m/d/Y", strtotime($row['order_time']));
			$thisOrderTotal = $row['cart_total'] + $row['shipping_charge'] + $row['tax_charge'] + $row['handling_charge'];
			$orderArray[$row['contact_id']]['total_sales'] += $thisOrderTotal;
			$orderArray[$row['contact_id']]['orders'][] = $row;
		}
		if (!empty($_POST['order_count'])) {
		    foreach ($orderArray as $contactId => $orders) {
		        if (count($orders['orders']) < $_POST['order_count']) {
		            unset($orderArray[$contactId]);
                }
            }
        }
		usort($orderArray, array(static::class, "sortOrders"));
		if ($exportReport) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"customersales.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "customersales.csv";

			echo createCsvRow(array("Contact ID", "Name", "Address", "City", "State", "Postal Code", "Email", "Order ID", "Order Date", "Sales", "Tax", "Shipping", "Handling", "Total", "Sales Cost", "Sales Profit"));

			foreach ($orderArray as $contactId => $orderInfo) {
                $salesTotal = 0;
                $taxCharge = 0;
                $shippingCharge = 0;
                $handlingCharge = 0;
                $orderTotal = 0;
                foreach ($orderInfo['orders'] as $thisOrder) {
					$salesTotal += $thisOrder['cart_total'];
					$taxCharge += $thisOrder['tax_charge'];
					$shippingCharge += $thisOrder['shipping_charge'];
					$handlingCharge += $thisOrder['handling_charge'];
					$orderTotal += $thisOrder['cart_total'] + $thisOrder['tax_charge'] + $thisOrder['shipping_charge'] + $thisOrder['handling_charge'];
					echo createCsvRow(array($thisOrder['contact_id'], getDisplayName($thisOrder['contact_id']), $thisOrder['address_1'], $thisOrder['city'], $thisOrder['state'], $thisOrder['postal_code'],
						$thisOrder['email_address'], $thisOrder['order_id'], $thisOrder['order_date'], number_format($thisOrder['cart_total'], 2, ".", ""),
						number_format($thisOrder['tax_charge'], 2, ".", ""),
						number_format($thisOrder['shipping_charge'], 2, ".", ""),
						number_format($thisOrder['handling_charge'], 2, ".", ""),
						number_format($thisOrder['cart_total'] + $thisOrder['tax_charge'] + $thisOrder['shipping_charge'] + $thisOrder['handling_charge'], 2, ".", ""),
						number_format($thisOrder['cart_cost'], 2, ".", ""),
						number_format($thisOrder['cart_total'] - $thisOrder['cart_cost'], 2, ".", "")));
				}
			}
		} else {
			?>
            <p>Run at <?= date("m/d/Y g:i a") . " by " . getUserDisplayName() ?></p>
            <table class="grid-table header-sortable">
                <tr class='header-row'>
                    <th>Customer</th>
                    <th class='align-right'>Orders</th>
                    <th class='align-right'>Sales</th>
                    <th class='align-right'>Tax</th>
                    <th class='align-right'>Shipping</th>
                    <th class='align-right'>Handling</th>
                    <th class='align-right'>Total</th>
                    <th class='align-right'>Sales Cost</th>
                    <th class='align-right'>Sales Profit</th>
                </tr>
				<?php
				$count = 0;
				foreach ($orderArray as $contactId => $orderInfo) {
					$count++;
					if (!empty($_POST['top_number']) && $count > $_POST['top_number']) {
						break;
					}
					$customerOrders = $orderInfo['orders'];
					$row = $customerOrders[0];
					$customerInfo = $row['contact_id'] . "<br>" . getDisplayName($row['contact_id']);
					if (!empty($row['address_1'])) {
						$customerInfo .= "<br>" . $row['address_1'];
					}
					if (!empty($row['address_2'])) {
						$customerInfo .= "<br>" . $row['address_2'];
					}
					$city = $row['city'];
					if (!empty($row['state'])) {
						$city .= (empty($city) ? "" : ", ") . $row['state'];
					}
					if (!empty($row['postal_code'])) {
						$city .= (empty($city) ? "" : " ") . $row['postal_code'];
					}
					if (!empty($city)) {
						$customerInfo .= "<br>" . $city;
					}
					if (!empty($row['email_address'])) {
						$customerInfo .= "<br>" . $row['email_address'];
					}
					$salesTotal = 0;
                    $salesCostTotal = 0;
					$orderTotal = 0;
					$taxCharge = 0;
					$shippingCharge = 0;
					$handlingCharge = 0;
					foreach ($customerOrders as $thisOrder) {
						$salesTotal += $thisOrder['cart_total'];
						$salesCostTotal += $thisOrder['cart_cost'];
						$taxCharge += $thisOrder['tax_charge'];
						$shippingCharge += $thisOrder['shipping_charge'];
						$handlingCharge += $thisOrder['handling_charge'];
						$orderTotal += $thisOrder['cart_total'] + $thisOrder['tax_charge'] + $thisOrder['shipping_charge'] + $thisOrder['handling_charge'];
					}
					?>
                    <tr>
                        <td><?= $customerInfo ?></td>
                        <td class='align-right'><?= count($customerOrders) ?></td>
                        <td class='align-right'><?= number_format($salesTotal, 2, ".", ",") ?></td>
                        <td class='align-right'><?= number_format($taxCharge, 2, ".", ",") ?></td>
                        <td class='align-right'><?= number_format($shippingCharge, 2, ".", ",") ?></td>
                        <td class='align-right'><?= number_format($handlingCharge, 2, ".", ",") ?></td>
                        <td class='align-right'><?= number_format($orderTotal, 2, ".", ",") ?></td>
                        <td class='align-right'><?= number_format($salesCostTotal, 2, ".", ",") ?></td>
                        <td class='align-right'><?= number_format($salesTotal - $salesCostTotal, 2, ".", ",") ?></td>
                    </tr>
					<?php
					if ($detailReport) {
						foreach ($customerOrders as $thisOrder) {
							$salesTotal = $thisOrder['cart_total'];
							$orderTotal = $salesTotal + $thisOrder['tax_charge'] + $thisOrder['shipping_charge'] + $thisOrder['handling_charge'];
							?>
                            <tr>
                                <td></td>
                                <td colspan="8">Order ID <?= (canAccessPage("ORDERDASHBOARD") ? "<a target='_blank' href='/order-dashboard?clear_filter=true&url_page=show&primary_id=" . $thisOrder['order_id'] . "'>" : "") . $thisOrder['order_id'] . (canAccessPage("ORDERDASHBOARD") ? "</a>" : "") ?> on <?= date("m/d/Y", strtotime($thisOrder['order_time'])) ?> for <?= number_format($orderTotal, 2, ".", ",") ?></td>
                            </tr>
							<?php
						}
					}
				}
				?>
            </table>
			<?php
		}
		if ($exportReport) {
			$returnArray['report_export'] = ob_get_clean();
		} else {
			$returnArray['report_content'] = ob_get_clean();
			$returnArray['report_title'] = "Customer Orders Report";
		}
		return $returnArray;
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option selected value="summary">Summary</option>
                        <option value="detail">Detail</option>
                        <option value="csv">CSV Export</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php getPresetDateOptions() ?>

                <div class="basic-form-line preset-date-custom" id="_order_time_row">
                    <label for="order_time_from">Order Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="order_time_from" name="order_time_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="order_time_to" name="order_time_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_order_total_row">
                    <label for="order_total">Order Total Over</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[number]]" data-decimal-places="2" id="order_total" name="order_total">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_order_count_row">
                    <label for="order_count">Number of orders</label>
                    <span class='help-label'>Only include customers where the number of orders is at or above this number.</span>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[int]]" data-decimal-places="2" id="order_count" name="order_count">
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

                <div class="basic-form-line" id="_order_method_id_row">
                    <label for="order_method_id">Order Method</label>
                    <select tabindex="10" id="order_method_id" name="order_method_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from order_methods where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['order_method_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_shipping_method_id_row">
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
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_only_pickup_row">
                    <input type="checkbox" name="only_pickup" id="only_pickup" value="1"><label class="checkbox-label" for="only_pickup">Include only pickup shipping methods</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_only_fulfillment_row">
                    <input type="checkbox" name="only_fulfillment" id="only_fulfillment" value="1"><label class="checkbox-label" for="only_fulfillment">Include only fulfillment shipping methods</label>
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
                <div class="basic-form-line custom-control-no-help custom-control-form-line" id="_order_status_row">
                    <label for="order_status">Include only these order statuses</label>
					<?= $customControl->getControl() ?>
                </div>

                <div class="basic-form-line" id="_exclude_order_status_row">
                    <input type="checkbox" name="exclude_order_status" id="exclude_order_status" value="1"><label class="checkbox-label" for="exclude_order_status">Exclude (instead of include) the previous statuses</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_not_free_row">
                    <input tabindex="10" type="checkbox" id="not_free" name="not_free"><label class="checkbox-label" for="not_free">Exclude free orders</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_payment_method_id_row">
                    <label for="payment_method_id">Payment Method</label>
                    <select tabindex="10" id="payment_method_id" name="payment_method_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from payment_methods where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['payment_method_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_state_row">
                    <label for="state">State of purchase</label>
                    <select tabindex="10" id="state" name="state">
                        <option value="">[All]</option>
						<?php
						foreach (getStateArray() as $stateCode => $state) {
							?>
                            <option value="<?= $stateCode ?>"><?= $state ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_postal_code_row">
                    <label>Postal Code Range</label>
                    <input type="text" tabindex="10" size="12" id="postal_code_start" name="postal_code_start">
                    <input type="text" tabindex="10" size="12" id="postal_code_end" name="postal_code_end">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_tax_exempt_row">
                    <span class="checkbox-label">Tax Exempt Orders:</span>
                    <input type="radio" checked tabindex="10" id="tax_exempt_include" name="tax_exempt" value=""><label class="checkbox-label" for="tax_exempt_include">Include</label>
                    <input type="radio" tabindex="10" id="tax_exempt_hide" name="tax_exempt" value="0"><label class="checkbox-label" for="tax_exempt_hide">Hide</label>
                    <input type="radio" tabindex="10" id="tax_exempt_only" name="tax_exempt" value="1"><label class="checkbox-label" for="tax_exempt_only">Only Tax Exempt</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_completed_row">
                    <span class="checkbox-label">Completed Orders:</span>
                    <input type="radio" checked tabindex="10" id="completed_include" name="completed" value=""><label class="checkbox-label" for="completed_include">Include</label>
                    <input type="radio" tabindex="10" id="completed_hide" name="completed" value="0"><label class="checkbox-label" for="completed_hide">Hide</label>
                    <input type="radio" tabindex="10" id="completed_only" name="completed" value="1"><label class="checkbox-label" for="completed_only">Only Completed</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_deleted_row">
                    <span class="checkbox-label">Deleted Orders:</span>
                    <input type="radio" tabindex="10" id="deleted_include" name="deleted" value=""><label class="checkbox-label" for="deleted_include">Include</label>
                    <input type="radio" checked tabindex="10" id="deleted_hide" name="deleted" value="0"><label class="checkbox-label" for="deleted_hide">Hide</label>
                    <input type="radio" tabindex="10" id="deleted_only" name="deleted" value="1"><label class="checkbox-label" for="deleted_only">Only Deleted</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_top_number_row">
                    <label>Only include top</label>
                    <input type="text" tabindex="10" size="10" id="top_number" name="top_number" class='validation[custom[integer]] align-right' value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_select_contacts_row">
                    <input tabindex="10" type="checkbox" id="select_contacts" name="select_contacts"><label class="checkbox-label" for="select_contacts">Select Contact included in report</label>
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
                    if ($("#report_type").val() === "csv") {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                        return;
                    }
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function(returnArray) {
                        if ("error_message" in returnArray) {
                            $("#_error_message").html(returnArray['error_message']);
                        } else {
                            $("#report_parameters").hide();
                            $("#_report_title").html(returnArray['report_title']).show();
                            $("#_report_content").html(returnArray['report_content']).show();
                            $("#_button_row").show();
                            $("html, body").animate({scrollTop: 0}, "slow");
                        }
                    });
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
                font-size: 13px;
            }

            #_button_row {
                display: none;
                margin-bottom: 20px;
            }
        </style>
		<?php
	}

}

$pageObject = new CustomerSalesReportPage();
$pageObject->displayPage();
