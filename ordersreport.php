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

$GLOBALS['gPageCode'] = "ORDERSREPORT";
require_once "shared/startup.inc";
require_once "jpgraph/jpgraph.php";
require_once "jpgraph/jpgraph_line.php";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;
ini_set("memory_limit", "4096M");

function dateCallBack($time) {
	return date("m/d/Y", $time);
}

class OrdersReportPage extends Page implements BackgroundReport {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_statistics":
				$hideHeader = userHasAttribute("HIDE_ORDERS_REVENUE", $GLOBALS['gUserId'], false);
				if ($hideHeader) {
					ajaxResponse($returnArray);
					break;
				}
				$dateFrom = date("Y-m-d", strtotime($_GET['start_date']));
				$dateTo = date("Y-m-d", strtotime($_GET['end_date']));

				//get new customers
				$resultSet = executeQuery("select count(*) from contacts where client_id = ? and contact_id in (select contact_id from orders where deleted = 0 and client_id = ?) and " .
					"date_created between ? and ?", $GLOBALS['gClientId'], $GLOBALS['gClientId'], $dateFrom, $dateTo);
				if ($row = getNextRow($resultSet)) {
					$returnArray['customer_count_new'] = $row['count(*)'];
				}

				//get total customers
				$resultSet = executeQuery("select count(*) from contacts where client_id = ? and contact_id in (select contact_id from orders where deleted = 0 and " .
					"date(order_time) between ? and ? and client_id = ?)", $GLOBALS['gClientId'], $dateFrom, $dateTo, $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['customer_count_total'] = $row['count(*)'];
				}

				//get new orders
				$resultSet = executeQuery("select count(*) from orders where deleted = 0 and client_id = ? and " .
					"date(order_time) between ? and ?", $GLOBALS['gClientId'], $dateFrom, $dateTo);
				if ($row = getNextRow($resultSet)) {
					$returnArray['order_count_total'] = $row['count(*)'];
				}

				//get total revenue
				$totalRevenue = 0;
				$resultSet = executeQuery("select shipping_charge,tax_charge,handling_charge,(select sum(order_items.sale_price * order_items.quantity) from order_items where " .
					"deleted = 0 and order_id = orders.order_id) as item_total from orders where " .
					"orders.client_id = ? and date(order_time) between ? and ? and orders.deleted = 0", $GLOBALS['gClientId'], $dateFrom, $dateTo);
				while ($row = getNextRow($resultSet)) {
					$totalRevenue += $row['shipping_charge'] + $row['tax_charge'] + $row['handling_charge'] + $row['item_total'];
				}

				$returnArray['revenue_total'] = "$" . number_format($totalRevenue, 2, ".", ",");
				ajaxResponse($returnArray);
				break;
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
			$tempPaymentMethodArray = explode(",", $_POST['payment_method_id']);
		} else {
			$tempPaymentMethodArray = array();
		}
		$paymentMethodArray = array();
		foreach ($tempPaymentMethodArray as $thisPaymentMethodId) {
			$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_id", $thisPaymentMethodId);
			if (!empty($paymentMethodId)) {
				$paymentMethodArray[] = $paymentMethodId;
			}
		}
		if (count($paymentMethodArray) > 0) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "order_id in (select order_id from order_payments where payment_method_id in (" . implode(",", $paymentMethodArray) . "))";
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
		if (!empty($_POST['manually_created'])) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "order_id in (select order_id from order_notes where content = 'Order was manually created')";
		}

		if (!empty($_POST['only_physical_products']) > 0) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "order_id in (select order_id from order_items where product_id in (select product_id from products where virtual_product = 0))";
		}

		ob_start();
		switch ($_POST['group_by']) {
			case "state":
				$groupByField = "address_state";
				$groupByDisplayField = "address_state";
				$groupByLabel = "State";
				$orderBy = "coalesce(address_state,state)";
				break;
			case "source";
				$groupByField = "source_id";
				$groupByDisplayField = "source_description";
				$groupByLabel = "Source";
				$orderBy = "source_description,orders.source_id";
				break;
			case "status";
				$groupByField = "order_status_id";
				$groupByDisplayField = "status_description";
				$groupByLabel = "Status";
				$orderBy = "status_description,orders.order_status_id";
				break;
			case "payment_method";
				$groupByField = "order_payment_method_id";
				$groupByDisplayField = "order_payment_method_description";
				$groupByLabel = "Payment Method";
				$orderBy = "order_payment_method_description,order_payment_method_id";
				break;
			case "shipping_method";
				$groupByField = "shipping_method_id";
				$groupByDisplayField = "shipping_method_description";
				$groupByLabel = "Shipping Method";
				$orderBy = "shipping_method_description,orders.shipping_method_id";
				break;
			case "referrer";
				$groupByField = "referral_contact_id";
				$groupByDisplayField = "referrer_name";
				$groupByLabel = "Referrer";
				$orderBy = "referrer_name,referral_contact_id";
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "referral_contact_id is not null";
				break;
			case "month":
				$groupByField = "order_month";
				$groupByDisplayField = "order_month";
				$groupByLabel = "Month";
				$orderBy = "order_time";
				break;
			default:
				$groupByField = "order_date";
				$groupByDisplayField = "order_date";
				$groupByLabel = "Date";
				$orderBy = "order_time";
				break;
		}
		$resultSet = executeReadQuery("select *,(select description from sources where source_id = orders.source_id) source_description, " .
			"(select order_id from orders as previous_orders where contact_id = orders.contact_id and order_id < orders.order_id limit 1) previous_order_id," .
			"(select description from order_status where order_status_id = orders.order_status_id) status_description," .
			"(select concat_ws(' ',first_name,last_name) from contacts where contact_id = orders.referral_contact_id) referrer_name," .
			"(select description from shipping_methods where shipping_method_id = orders.shipping_method_id) shipping_method_description," .
			"(select state from addresses where address_id = orders.address_id) address_state, " .
			"(select payment_method_id from order_payments where order_id = orders.order_id order by amount desc limit 1) order_payment_method_id, " .
			"(select account_id from order_payments where order_id = orders.order_id order by amount desc limit 1) payment_account_id, " .
			"(select sum(shipping_charge) from order_shipments where order_id = orders.order_id) shipping_cost, " .
			"(select description from payment_methods where payment_method_id = (select payment_method_id from order_payments where order_id = orders.order_id order by amount desc limit 1)) order_payment_method_description " .
			"from contacts join orders using (contact_id) where orders.client_id = ?" .
			(empty($whereStatement) ? "" : " and " . $whereStatement) . " order by " . $orderBy, $parameters);

        $paymentArray = array();
        if ($resultSet['row_count'] > 100) {
	        $paymentSet = executeQuery("select order_id,sum(amount),sum(tax_charge),sum(shipping_charge),sum(handling_charge) from order_payments where order_id in (select order_id from orders where client_id = ?) and deleted = 0 group by order_id", $GLOBALS['gClientId']);
	        while ($paymentRow = getNextRow($paymentSet)) {
                $paymentArray[$paymentRow['order_id']] = $paymentRow;
	        }
        }
		$detailReport = ($_POST['report_type'] == "detail" || $_POST['report_type'] == "csv");
		$csvExport = ($_POST['report_type'] == "csv");
		$orderArray = array();
		while ($row = getNextRow($resultSet)) {
			if (empty($row['address_state'])) {
				$row['address_state'] = $row['state'];
			}
			$row['order_date'] = date("m/d/Y", strtotime($row['order_time']));
			$row['order_month'] = date("m/Y", strtotime($row['order_time']));
			$orderArray[] = $row;
		}
		$dayCount = 0;
		$orderAmounts = array();
		$orderDates = array();
		if ($csvExport) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"ordersreport.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "ordersreport.csv";
			echo createCsvRow(array("Order Date", "Order Number", "Contact", "Email", "Billing Address", "Source", "Status", "Referrer Name", "Payment Method", "Shipping Method", "Sales", "Tax", "Shipping", "Handling", "Total", "Shipping Costs"));
		} else {
			?>
            <p>Run at <?= date("m/d/Y g:i a") . " by " . getUserDisplayName() ?></p>
            <table class="grid-table">
			<?php if ($detailReport) { ?>
                <tr>
                    <th><?= $groupByLabel ?></th>
                    <th>Order Date</th>
                    <th>Order Number</th>
                    <th>Contact</th>
                    <th>Billing Address</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Payment Method</th>
                    <th>Shipping Method</th>
                    <th>Qty</th>
                    <th>Sales</th>
                    <th>Tax</th>
                    <th>Shipping</th>
                    <th>Handling</th>
                    <th>Total</th>
                    <th>Shipping Costs</th>
                </tr>
			<?php } else { ?>
                <tr>
                    <th></th>
                    <th>Orders</th>
                    <th>From Repeat<br>Customers</th>
                    <th>Sales</th>
                    <th>Tax</th>
                    <th>Shipping</th>
                    <th>Handling</th>
                    <th>Total</th>
                </tr>
				<?php
			}
		}
		$reportTotalQuantity = 0;
        $reportTotalRepeat = 0;
		$reportTotalSales = 0;
		$reportTotalShipping = 0;
		$reportTotalTax = 0;
		$reportTotalHandling = 0;
		$reportTotalAmount = 0;
		$reportTotalShippingCharges = 0;
		$saveGroupBy = "";
		$saveGroupByDisplayField = "";
		$saveQuantity = 0;
        $saveRepeat = 0;
		$saveSales = 0;
		$saveTax = 0;
		$saveShipping = 0;
		$saveHandling = 0;
		$saveTotal = 0;
		$saveShippingCharges = 0;
		$firstLine = false;
		foreach ($orderArray as $row) {
			if (empty($row['shipping_cost'])) {
				$row['shipping_cost'] = 0;
			}
			if ($saveGroupBy != $row[$groupByField]) {
				if ($saveQuantity > 0) {
					$dayCount++;
					if (!$csvExport) {
						?>
                        <tr>
                            <td colspan="<?= ($detailReport ? "9" : "1") ?>" class="highlighted-text"><?= ($detailReport ? "Total for " : "") ?><?= htmlText($saveGroupByDisplayField) ?></td>
                            <td class="align-right"><?= $saveQuantity ?></td>
                            <td class="align-right"><?= $saveRepeat ?></td>
                            <td class="align-right"><?= number_format($saveSales, 2, ".", ",") ?></td>
                            <td class="align-right"><?= number_format($saveTax, 2, ".", ",") ?></td>
                            <td class="align-right"><?= number_format($saveShipping, 2, ".", ",") ?></td>
                            <td class="align-right"><?= number_format($saveHandling, 2, ".", ",") ?></td>
                            <td class="align-right"><?= number_format($saveTotal, 2, ".", ",") ?></td>
							<?php if ($detailReport) { ?>
                                <td class="align-right"><?= number_format($saveShippingCharges, 2, ".", ",") ?></td>
							<?php } ?>
                        </tr>
						<?php
					}
					if (!$detailReport && $_POST['group_by'] == "date") {
						$orderAmounts[] = $saveTotal;
						$orderDates[] = strtotime($saveGroupBy);
					}
				}
				$saveGroupBy = $row[$groupByField];
				$saveGroupByDisplayField = $row[$groupByDisplayField];
				$saveQuantity = 0;
				$saveRepeat = 0;
				$saveSales = 0;
				$saveTax = 0;
				$saveShipping = 0;
				$saveHandling = 0;
				$saveTotal = 0;
				$saveShippingCharges = 0;
				$firstLine = true;
			}
			$saveQuantity++;
            if (!empty($row['previous_order_id'])) {
	            $saveRepeat++;
            }

			$row['tax_charge'] = $row['shipping_charge'] = $row['handling_charge'] = 0;
            if (array_key_exists($row['order_id'],$paymentArray)) {
                $paymentRow = $paymentArray[$row['order_id']];
	            $salesTotal = $paymentRow['sum(amount)'];
	            $row['tax_charge'] += $paymentRow['sum(tax_charge)'];
	            $row['shipping_charge'] += $paymentRow['sum(shipping_charge)'];
	            $row['handling_charge'] += $paymentRow['sum(handling_charge)'];
            } else {
	            $paymentSet = executeQuery("select sum(amount),sum(tax_charge),sum(shipping_charge),sum(handling_charge) from order_payments where order_id = ? and deleted = 0", $row['order_id']);
	            if ($paymentRow = getNextRow($paymentSet)) {
		            $salesTotal = $paymentRow['sum(amount)'];
		            $row['tax_charge'] += $paymentRow['sum(tax_charge)'];
		            $row['shipping_charge'] += $paymentRow['sum(shipping_charge)'];
		            $row['handling_charge'] += $paymentRow['sum(handling_charge)'];
	            }
            }
			$saveTax += $row['tax_charge'];
			$saveShipping += $row['shipping_charge'];
			$saveHandling += $row['handling_charge'];
			$orderTotal = $salesTotal + $row['tax_charge'] + $row['shipping_charge'] + $row['handling_charge'];
			$saveSales += $salesTotal;
			$saveTotal += $orderTotal;
			$saveShippingCharges += $row['shipping_cost'];
			$reportTotalQuantity++;
            if (!empty($row['previous_order_id'])) {
	            $reportTotalRepeat++;
            }
			$reportTotalSales += $salesTotal;
			$reportTotalShipping += $row['shipping_charge'];
			$reportTotalTax += $row['tax_charge'];
			$reportTotalHandling += $row['handling_charge'];
			$reportTotalAmount += $orderTotal;
			$reportTotalShippingCharges += $row['shipping_cost'];
			if ($detailReport) {
				if (empty($row['address_id'])) {
					$contactSet = executeReadQuery("select * from contacts where contact_id = ?", $row['contact_id']);
				} else {
					$contactSet = executeReadQuery("select * from addresses where address_id = ?", $row['address_id']);
				}
				$contactRow = getNextRow($contactSet);
				$addressBlock = (empty($row['full_name']) ? getDisplayName($row['contact_id']) : $row['full_name']);
				if (!empty($contactRow['address_1'])) {
					$addressBlock .= ($csvExport ? ", " : "<br>") . $contactRow['address_1'];
				}
				$city = $contactRow['city'];
				if (!empty($contactRow['state'])) {
					$city .= (empty($city) ? "" : ", ") . $contactRow['state'];
				}
				if (!empty($contactRow['postal_code'])) {
					$city .= (empty($city) ? "" : " ") . $contactRow['postal_code'];
				}
				if (!empty($city)) {
					$addressBlock .= ($csvExport ? ", " : "<br>") . $city;
				}
				if ($contactRow['country_id'] != 1000) {
					$addressBlock .= ($csvExport ? ", " : "<br>") . getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id']);
				}
				$addressBlock .= ($csvExport ? "" : "<br>" . $row['email_address']);
				if (empty($row['phone_number'])) {
					$phoneSet = executeReadQuery("select * from phone_numbers where contact_id = ?", $row['contact_id']);
					while ($phoneRow = getNextRow($phoneSet)) {
						$addressBlock .= ($csvExport ? ", " : "<br>") . $phoneRow['phone_number'] . (empty($phoneRow['description']) ? "" : " " . $phoneRow['description']);
					}
				} else {
					$addressBlock .= ($csvExport ? ", " : "<br>") . $row['phone_number'];
				}
				$accountId = getFieldFromId("account_id", "order_payments", "order_id", $row['order_id'], "account_id is not null");
				$accountRow = getRowFromId("accounts", "account_id", $accountId);
				$billingInfo = (empty($accountRow['full_name']) ? getDisplayName($row['contact_id']) : $accountRow['full_name']);
				if (empty($accountRow['address_id'])) {
					$addressRow = $contactRow;
				} else {
					$addressRow = getRowFromId("addresses", "address_id", $accountRow['address_id']);
				}
				if (!empty($addressRow['address_1'])) {
					$billingInfo .= ($csvExport ? ", " : "<br>") . $addressRow['address_1'];
				}
				$city = $addressRow['city'];
				if (!empty($addressRow['state'])) {
					$city .= (empty($city) ? "" : ", ") . $addressRow['state'];
				}
				if (!empty($addressRow['postal_code'])) {
					$city .= (empty($city) ? "" : " ") . $addressRow['postal_code'];
				}
				if (!empty($city)) {
					$billingInfo .= ($csvExport ? ", " : "<br>") . $city;
				}
				if ($addressRow['country_id'] != 1000) {
					$billingInfo .= ($csvExport ? ", " : "<br>") . getFieldFromId("country_name", "countries", "country_id", $addressRow['country_id']);
				}
				if ($csvExport) {
					echo '"' . date("m/d/Y", strtotime($row['order_time'])) . '",' .
						'"' . $row['order_number'] . '",' .
						'"' . str_replace('"', '', $addressBlock) . '",' .
						'"' . str_replace('"', '', $row['email_address']) . '",' .
						'"' . str_replace('"', '', $billingInfo) . '",' .
						'"' . str_replace('"', '', getFieldFromId("description", "sources", "source_id", $row['source_id'])) . '",' .
						'"' . str_replace('"', '', getFieldFromId("description", "order_status", "order_status_id", $row['order_status_id'])) . '",' .
						'"' . str_replace('"', '', $row['referrer_name']) . '",' .
						'"' . str_replace('"', '', $row['order_payment_method_description'] . (empty($row['payment_account_id']) ? "" : " - " . substr(getFieldFromId("account_number", "accounts", "account_id", $row['payment_account_id']), -4))) . '",' .
						'"' . str_replace('"', '', $row['shipping_method_description']) . '",' .
						'"' . str_replace('"', '', number_format($salesTotal, 2, ".", ",")) . '",' .
						'"' . str_replace('"', '', number_format($row['tax_charge'], 2, ".", ",")) . '",' .
						'"' . str_replace('"', '', number_format($row['shipping_charge'], 2, ".", ",")) . '",' .
						'"' . str_replace('"', '', number_format($row['handling_charge'], 2, ".", ",")) . '",' .
						'"' . str_replace('"', '', number_format($orderTotal, 2, ".", ",")) . '",' .
						'"' . str_replace('"', '', number_format($row['shipping_cost'], 2, ".", ",")) . '"' . "\n";
				} else {
					?>
                    <tr>
                        <td><?= ($firstLine ? htmlText($row[$groupByDisplayField]) : "") ?></td>
                        <td><?= date("m/d/Y", strtotime($row['order_time'])) ?></td>
                        <td><?= $row['order_number'] ?></td>
                        <td><?= $addressBlock ?></td>
                        <td><?= $billingInfo ?></td>
                        <td><?= getFieldFromId("description", "sources", "source_id", $row['source_id']) ?></td>
                        <td><?= getFieldFromId("description", "order_status", "order_status_id", $row['order_status_id']) ?></td>
                        <td><?= $row['order_payment_method_description'] . (empty($row['payment_account_id']) ? "" : " - " . substr(getFieldFromId("account_number", "accounts", "account_id", $row['payment_account_id']), -4)) ?></td>
                        <td><?= $row['shipping_method_description'] ?></td>
                        <td></td>
                        <td class="align-right"><?= number_format($salesTotal, 2, ".", ",") ?></td>
                        <td class="align-right"><?= number_format($row['tax_charge'], 2, ".", ",") ?></td>
                        <td class="align-right"><?= number_format($row['shipping_charge'], 2, ".", ",") ?></td>
                        <td class="align-right"><?= number_format($row['handling_charge'], 2, ".", ",") ?></td>
                        <td class="align-right"><?= number_format($orderTotal, 2, ".", ",") ?></td>
                        <td class="align-right"><?= number_format($row['shipping_cost'], 2, ".", ",") ?></td>
                    </tr>
					<?php
				}
			}
		}
		if ($saveQuantity > 0) {
			$dayCount++;
			if (!$csvExport) {
				?>
                <tr>
                    <td colspan="<?= ($detailReport ? "9" : "1") ?>" class="highlighted-text"><?= ($detailReport ? "Total for " : "") ?><?= htmlText($row[$groupByDisplayField]) ?></td>
                    <td class="align-right"><?= $saveQuantity ?></td>
                    <td class="align-right"><?= $saveRepeat ?></td>
                    <td class="align-right"><?= number_format($saveSales, 2, ".", ",") ?></td>
                    <td class="align-right"><?= number_format($saveTax, 2, ".", ",") ?></td>
                    <td class="align-right"><?= number_format($saveShipping, 2, ".", ",") ?></td>
                    <td class="align-right"><?= number_format($saveHandling, 2, ".", ",") ?></td>
                    <td class="align-right"><?= number_format($saveTotal, 2, ".", ",") ?></td>
					<?php if ($detailReport) { ?>
                        <td class="align-right"><?= number_format($saveShippingCharges, 2, ".", ",") ?></td>
					<?php } ?>
                </tr>
				<?php
			}
			if (!$detailReport && $_POST['group_by'] == "date") {
				$orderAmounts[] = $saveTotal;
				$orderDates[] = strtotime($row[$groupByDisplayField]);
			}
		}
		if (!$csvExport) {
			?>
            <tr>
                <td colspan="<?= ($detailReport ? "9" : "1") ?>" class="highlighted-text">Report Total</td>
                <td class="highlighted-text align-right"><?= $reportTotalQuantity ?></td>
                <td class="highlighted-text align-right"><?= $reportTotalRepeat ?></td>
                <td class="highlighted-text align-right"><?= number_format($reportTotalSales, 2, ".", ",") ?></td>
                <td class="highlighted-text align-right"><?= number_format($reportTotalTax, 2, ".", ",") ?></td>
                <td class="highlighted-text align-right"><?= number_format($reportTotalShipping, 2, ".", ",") ?></td>
                <td class="highlighted-text align-right"><?= number_format($reportTotalHandling, 2, ".", ",") ?></td>
                <td class="highlighted-text align-right"><?= number_format($reportTotalAmount, 2, ".", ",") ?></td>
				<?php if ($detailReport) { ?>
                    <td class="align-right"><?= number_format($reportTotalShippingCharges, 2, ".", ",") ?></td>
				<?php } ?>
            </tr>
			<?php
			if ($groupByField == "order_date" && $dayCount > 0 && !$detailReport) {
				?>
                <tr>
                    <td class="highlighted-text">Daily Averages</td>
                    <td class="highlighted-text align-right"><?= round($reportTotalQuantity / $dayCount) ?></td>
                    <td class="highlighted-text align-right"><?= round($reportTotalRepeat / $dayCount) ?></td>
                    <td class="highlighted-text align-right"><?= number_format(round($reportTotalSales / $dayCount, 2), 2, ".", ",") ?></td>
                    <td class="highlighted-text align-right"><?= number_format(round($reportTotalTax / $dayCount, 2), 2, ".", ",") ?></td>
                    <td class="highlighted-text align-right"><?= number_format(round($reportTotalShipping / $dayCount, 2), 2, ".", ",") ?></td>
                    <td class="highlighted-text align-right"><?= number_format(round($reportTotalHandling / $dayCount, 2), 2, ".", ",") ?></td>
                    <td class="highlighted-text align-right"><?= number_format(round($reportTotalAmount / $dayCount, 2), 2, ".", ",") ?></td>
                </tr>
				<?php
			}
			?>
            </table>
			<?php
		}
		if ($csvExport) {
			$returnArray['report_export'] = ob_get_clean();
			return $returnArray;
		}
		if (!empty($orderAmounts)) {
			$returnArray['report_content'] = ob_get_clean();

			try {
				$width = 1200;
				$height = 600;
				$graph = new Graph($width, $height);
				$graph->SetScale('intlin');
				$graph->xaxis->SetLabelFormatCallback('dateCallBack');
				$graph->xaxis->SetLabelAngle(90);
				$graph->SetMargin(100, 20, 20, 100);
				$lineplot = new LinePlot($orderAmounts, $orderDates);
				$graph->Add($lineplot);
				$image = $graph->Stroke(_IMG_HANDLER);
				ob_start();
				imagepng($image);
				$imageData = ob_get_contents();
				ob_end_clean();
				$returnArray['report_content'] .= "<p><img alt='Report Image' width='" . $width . "' height='" . $height . "' src='data:image/png;base64," . base64_encode($imageData) . "'/>";
			} catch (Exception $e) {
				$returnArray['report_content'] .= $e->getMessage();
			}
		} else {
			$returnArray['report_content'] = ob_get_clean();
		}
		$returnArray['report_title'] = "Orders Report";
		return $returnArray;
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <div id="_order_header_section" class="advanced-feature header-section">
                <div id="order_filters">
                    <button accesskey="t" data-start_date='<?= date("Y-m-d") ?>' data-end_date='<?= date("Y-m-d") ?>' class="statistics-filter-button active">Today</button>
                    <button data-start_date='1776-07-04' data-end_date='<?= date("Y-m-d") ?>' class="statistics-filter-button">All Time</button>
                    <button accesskey="y" data-start_date='<?= date("Y-01-01") ?>' data-end_date='<?= date("Y-m-d") ?>' class="statistics-filter-button">YTD</button>
                    <button accesskey="m" data-start_date='<?= date("Y-m-d", strtotime("first day of previous month")) ?>' data-end_date='<?= date("Y-m-d", strtotime("last day of previous month")) ?>'
                            class="statistics-filter-button"><?= date("F", strtotime("last day of previous month")) ?></button>
                    <button data-start_date='<?= date("Y-m-d", strtotime("first day of this month")) ?>' data-end_date='<?= date("Y-m-d") ?>' class="statistics-filter-button"><?= date("F") ?></button>
                    <button accesskey="w" data-start_date='<?= date("Y-m-d", strtotime("last week monday")) ?>' data-end_date='<?= date("Y-m-d", strtotime("last week sunday")) ?>' class="statistics-filter-button">Last Week</button>
                    <button accesskey="n" data-start_date='<?= date("Y-m-d", strtotime("this week monday")) ?>' data-end_date='<?= date("Y-m-d") ?>' class="statistics-filter-button">This Week</button>
                    <button class="statistics-filter-button">Custom Dates</button>
                </div>
            </div>

            <div id="statistics_block" class='advanced-feature'>
                <div id="customer_statistics">
                    <h3>Customers</h3>
                    <div class="count-wrapper">
                        <div class="col-2"><h2 id="customer_count_new">0</h2>
                            <p>New</p></div>
                        <div class="col-2"><h2 id="customer_count_total">0</h2>
                            <p>Total</p></div>
                    </div>
                </div>

                <div id="order_statistics">
                    <h3>Orders</h3>
                    <div class="count-wrapper">
                        <div><h2 id="order_count_total">0</h2>
                            <p>Total</p></div>
                    </div>
                </div>

                <div id="revenue_statistics">
                    <h3>Revenue</h3>
                    <div class="count-wrapper">
                        <div><h2 id="revenue_total">$0.00</h2>
                            <p>Total</p></div>
                    </div>
                </div>

            </div>

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

                <div class="basic-form-line" id="_group_by_row">
                    <label for="group_by">Group By</label>
                    <select tabindex="10" id="group_by" name="group_by">
                        <option selected value="date">Date</option>
                        <option value="month">Month</option>
                        <option value="state">State</option>
                        <option value="source">Source</option>
                        <option value="status">Status</option>
                        <option value="payment_method">Payment Method</option>
                        <option value="shipping_method">Shipping Method</option>
                        <option value="referrer">Referrer</option>
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
                    <label for="order_status">Include only these order statuses (leave blank to include all)</label>
					<?= $customControl->getControl() ?>
                    <div class='clear-div'></div>
                </div>

                <div class="basic-form-line" id="_exclude_order_status_row">
                    <input type="checkbox" name="exclude_order_status" id="exclude_order_status" value="1"><label class="checkbox-label" for="exclude_order_status">Exclude (instead of include) the previous statuses</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_not_free_row">
                    <input tabindex="10" type="checkbox" id="not_free" name="not_free"><label class="checkbox-label" for="not_free">Exclude free orders</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_manually_created_row">
                    <input tabindex="10" type="checkbox" id="manually_created" name="manually_created"><label class="checkbox-label" for="manually_created">Include only manually created orders</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_only_physical_products_row">
                    <input tabindex="10" type="checkbox" id="only_physical_products" name="only_physical_products"><label class="checkbox-label" for="only_physical_products">Exclude orders with only virtual products (classes, memberships, etc)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <?php
				$paymentMethodControl = new DataColumn("payment_method_id");
				$paymentMethodControl->setControlValue("data_type", "custom");
				$paymentMethodControl->setControlValue("include_inactive", "true");
				$paymentMethodControl->setControlValue("control_class", "MultiSelect");
				$paymentMethodControl->setControlValue("control_table", "payment_methods");
				$paymentMethodControl->setControlValue("links_table", "orders");
				$paymentMethodControl->setControlValue("primary_table", "orders");
				$customControl = new MultipleSelect($paymentMethodControl, $this);
				?>
                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_payment_method_id_row">
                    <label for="payment_method_id">Include only these payment methods (leave blank to include all)</label>
					<?= $customControl->getControl() ?>
                    <div class='clear-div'></div>
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
            $(document).on("click", ".statistics-filter-button", function () {
                const startDate = $(this).data("start_date");
                const endDate = $(this).data("end_date");
                const $statisticsFilterButton = $(this);
                if (empty(startDate) || empty(endDate)) {
                    $('#_custom_dates_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: true,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 500,
                        title: 'Custom Dates',
                        buttons: {
                            Select: function (event) {
                                if ($("#_custom_dates_form").validationEngine("validate")) {
                                    $(".statistics-filter-button").removeClass("active");
                                    $statisticsFilterButton.addClass("active");
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_statistics&start_date=" + $.formatDate($("#start_date").val(), "yyyy-MM-dd") + "&end_date=" + $.formatDate($("#end_date").val(), "yyyy-MM-dd"), function (returnArray) {
                                        for (const i in returnArray) {
                                            $("#" + i).html(returnArray[i]);
                                        }
                                    });
                                    $("#_custom_dates_dialog").dialog('close');
                                }
                            }
                        }
                    });
                } else {
                    $(".statistics-filter-button").removeClass("active");
                    $(this).addClass("active");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_statistics&start_date=" + $.formatDate(startDate, "yyyy-MM-dd") + "&end_date=" + $.formatDate(endDate, "yyyy-MM-dd"), function (returnArray) {
                        for (const i in returnArray) {
                            $("#" + i).html(returnArray[i]);
                        }
                    });
                }
                return false;
            });
            $(".statistics-filter-button.active").trigger("click");
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
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function (returnArray) {
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
            #customer_statistics {
                background-color: #333c43;
            }

            #customer_statistics h3 {
                background-color: #252a2e;
            }

            #order_statistics {
                background-color: #53646b;
            }

            #order_statistics h3 {
                background-color: #3b464a;
            }

            #revenue_statistics {
                background-color: #7a8e95;
            }

            #revenue_statistics h3 {
                background-color: #556469;
            }

            #statistics_block {
                display: flex;
                margin-bottom: 20px;
            }

            #statistics_block > div {
                flex: 0 0 33%;
                margin-top: 20px;
                height: 160px;
            }

            #statistics_block h3, #statistics_block p {
                color: #d8d8d8;
                font-weight: 700;
            }

            #statistics_block h3 {
                padding: 13px;
                margin: 0 0 15px 0;
                text-transform: uppercase;
                font-size: 16px;
            }

            #statistics_block h2 {
                color: #FFF;
                font-size: 32px;
                font-weight: 300;
            }

            #_maintenance_form h2 {
                margin-top: 16px;
            }

            .count-wrapper {
                display: flex;
            }

            .count-wrapper > div {
                flex: 1 1 auto;
                text-align: center;
            }

            .count-wrapper > div:nth-child(2) {
                border-left: 1px solid #d8d8d8;
            }

            #order_filters {
                text-align: center;
            }

            #order_filters button:hover {
                background-color: #000;
                border-color: #000;
                color: #FFF;
            }

            #order_filters button.active {
                background-color: #00807f;
                border-color: #00807f;
                color: #FFF;
            }

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

	function hiddenElements() {
		?>
        <div id="_custom_dates_dialog" class="dialog-box">
            <form id="_custom_dates_form">
                <div class="basic-form-line" id="_start_date_row">
                    <label for="start_date" class="required-label">Start Date</label>
                    <input type="text" tabindex="10" class="validate[required,custom[date]] datepicker" size="12" id="start_date" name="start_date">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_end_date_row">
                    <label for="end_date" class="required-label">End Date</label>
                    <input type="text" tabindex="10" class="validate[required,custom[date]] datepicker" size="12" id="end_date" name="end_date">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
            </form>
        </div>
		<?php
	}

}

$pageObject = new OrdersReportPage();
$pageObject->displayPage();
