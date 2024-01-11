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

$GLOBALS['gPageCode'] = "ORDERITEMREPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;
ini_set("memory_limit", "4096M");

class OrderItemReportPage extends Page implements BackgroundReport {

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
		if (!empty($_POST['product_manufacturer_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_id in (select product_id from products where product_manufacturer_id = ?)";
			$parameters[] = $_POST['product_manufacturer_id'];
		}
		if (!empty($_POST['not_shipped'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_id not in (select product_id from products where virtual_product = 1) and (not exists (select order_item_id from order_shipment_items where order_item_id = order_items.order_item_id) or order_items.quantity > (select sum(quantity) from order_shipment_items where order_item_id = order_items.order_item_id))";
		}
		$productIds = array();
		foreach (explode(",", $_POST['products']) as $productId) {
			$productId = getFieldFromId("product_id", "products", "product_id", $productId);
			if (!empty($productId)) {
				$productIds[] = $productId;
			}
		}
		if (!empty($productIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "product_id in (" . implode(",", $productIds) . ")";
		}
		$productCategoryIds = array();
		foreach (explode(",", $_POST['product_categories']) as $productCategoryId) {
			$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $productCategoryId);
			if (!empty($productCategoryId)) {
				$productCategoryIds[] = $productCategoryId;
			}
		}
		if (!empty($productCategoryIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "product_id in (select product_id from product_category_links where product_category_id in (" . implode(",", $productCategoryIds) . "))";
		}
		$productTagIds = array();
		foreach (explode(",", $_POST['product_tags']) as $productTagId) {
			$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_id", $productTagId);
			if (!empty($productTagId)) {
				$productTagIds[] = $productTagId;
			}
		}
		if (!empty($productTagIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "product_id in (select product_id from product_tag_links where (start_date is null or start_date >= current_date) and (expiration_date is null or expiration_date > current_date) and product_tag_id in (" . implode(",", $productTagIds) . "))";
		}

		$productCategoryGroupIds = array();
		foreach (explode(",", $_POST['product_category_groups']) as $productCategoryGroupId) {
			$productCategoryGroupId = getFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_id", $productCategoryGroupId);
			if (!empty($productCategoryGroupId)) {
				$productCategoryGroupIds[] = $productCategoryGroupId;
			}
		}
		if (!empty($productCategoryGroupIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from product_category_group_links where product_category_group_id in (" . implode(",", $productCategoryGroupIds) . ")))";
		}

		$userTypeIds = array();
		foreach (explode(",", $_POST['user_types']) as $userTypeId) {
			$userTypeId = getFieldFromId("user_type_id", "user_types", "user_type_id", $userTypeId);
			if (!empty($userTypeId)) {
				$userTypeIds[] = $userTypeId;
			}
		}
		if (!empty($userTypeIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "contact_id " . (empty($_POST['exclude_user_types']) ? "" : "not ") .
				"in (select contact_id from users where user_type_id in (" . implode(",", $userTypeIds) . "))";
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

		if (strlen($_POST['deleted_items']) > 0) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "order_items.deleted = " . ($_POST['deleted_items'] == 1 ? 1 : 0);
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

		$departments = array();
		$resultSet = executeReadQuery("select * from product_departments where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$departments[$row['product_department_code']] = $row;
		}

		$reportTotalQuantity = 0;
		$reportTotalAmount = 0;
		$reportTotalAddonsAmount = 0;

		$zaiusExport = false;
		$includeAddons = false;
		ob_start();
		switch ($_POST['report_type']) {
			case "zaius_csv":
				$zaiusExport = true;
				$zaiusUseUpc = getPreference("ZAIUS_USE_UPC");
				$filename = "zaius_orders.csv";
			case "summary_csv":
			case "csv":
				if ($_POST['report_type'] == "summary_csv") {
					$summaryReport = true;
				}
				$filename = empty($filename) ? "order_items.csv" : $filename;
				$returnArray['export_headers'] = array();
				$returnArray['export_headers'][] = "Content-Type: text/csv";
				$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"" . $filename . "\"";
				$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
				$returnArray['export_headers'][] = 'Pragma: public';
				$returnArray['filename'] = "order_items.csv";

				if ($zaiusExport) {
					$exportOrderHeaders = array('email', 'first_name', 'last_name', 'phone', 'bill_address', 'ship_address', 'action', 'order_id', 'total', 'discount', 'subtotal', 'tax', 'shipping', 'coupon_code', 'item_product_id', 'item_sku', 'item_price', 'item_quantity', 'item_discount', 'item_subtotal', 'ts');
				} else if ($summaryReport) {
					$exportOrderHeaders = array("ProductCode", "Description", "UPC", "Department", "Manufacturer", "Quantity", "TotalIncome", "AveragePrice");
				} else {
					$exportOrderHeaders = array("OrderDate", "OrderNumber", "ProductCode", "Description", "UPC", "Department", "Manufacturer", "OrderStatus", "ItemStatus", "Source", "Location", "Quantity", "SalePrice", "ContactName", "OrderName", "Address1", "Address2", "City", "State", "PostalCode", "EmailAddress", "PhoneNumber", "Country", "ReferredBy", "DateCompleted");
				}

				echo createCsvRow($exportOrderHeaders);

				$productTotals = array();
				$resultSet = executeReadQuery("select *, orders.deleted as order_deleted, order_items.deleted as item_deleted,"
					. "orders.tax_charge as order_tax_charge, order_items.tax_charge as order_item_tax_charge, referral_contact_id,"
					. "(select sum(quantity * sale_price) from order_items where order_id = orders.order_id group by order_id) as sales_total, "
					. "(select description from locations where location_id in "
					. "(select location_id from order_shipments where order_id = orders.order_id and order_shipment_id in "
					. "(select order_shipment_id from order_shipment_items where order_item_id = order_items.order_item_id)) limit 1) as location_description "
					. "from orders join order_items using (order_id) where client_id = ?"
					. (empty($whereStatement) ? "" : " and " . $whereStatement) . ($zaiusExport ? " order by orders.order_id" : " order by order_time"), $parameters);
				while ($row = getNextRow($resultSet)) {
					$contactRow = Contact::getContact($row['contact_id']);
					$accountId = getFieldFromId("account_id", "order_payments", "order_id", $row['order_id']);
					if (empty($accountId)) {
						$accountId = $row['account_id'];
					}
					if (empty($accountId)) {
						$billingRow = Contact::getContact($row['contact_id']);
					} else {
						$billingRow = getRowFromId("addresses", "address_id", getFieldFromId("address_id", "accounts", "account_id", $accountId));
					}
					if (empty($row['address_id'])) {
						$addressRow = Contact::getContact($row['contact_id']);
					} else {
						$addressRow = getRowFromId("addresses", "address_id", $row['address_id']);
					}
					$productRow = ProductCatalog::getCachedProductRow($row['product_id']);

					if (empty($row['location_description'])) {
						$defaultLocationId = CustomField::getCustomFieldData($row['contact_id'], "DEFAULT_LOCATION_ID");
						if (!empty($defaultLocationId)) {
							$row['location_description'] = getFieldFromId("description", "locations", "location_id", $defaultLocationId);
						}
					}
					$departmentDescription = "";
					foreach ($departments as $thisDepartment) {
						if (ProductCatalog::productIsInDepartment($row['product_id'], $thisDepartment['product_department_id'])) {
							$departmentDescription = $thisDepartment['description'];
							break;
						}
					}
					if (empty($departmentDescription)) {
						$departmentDescription = "(No Department)";
					}

					if ($zaiusExport) {
						if ($row['order_deleted'] == 1 || $row['item_deleted'] == 1) {  // return; include order record twice; 1x purchase, 1x return
							$refundResult = executeReadQuery("select sum(amount) sales_total, sum(shipping_charge) shipping_charge,
                                sum(tax_charge) tax_charge, sum(handling_charge) handling_charge, 0 order_discount from order_payments where order_id = ? and amount < 0;", $row['order_id']);
							$paymentRow = getNextRow($refundResult);
							if (!empty($paymentRow['sales_total'])) {
								$exportRow = array(
									$contactRow['email_address'],
									$contactRow['first_name'],
									$contactRow['last_name'],
									getContactPhoneNumber($row['contact_id']),
									static::formatZaiusAddress($billingRow),
									static::formatZaiusAddress($addressRow),
									"return",
									$row['order_id'],
									$paymentRow['sales_total'] + $paymentRow['shipping_charge'] + $paymentRow['handling_charge'] + $paymentRow['tax_charge'],
									$paymentRow['order_discount'],
									$paymentRow['sales_total'],
									$paymentRow['tax_charge'],
									$paymentRow['shipping_charge'],
									getFieldFromId("promtion_code", "promotions", "promotion_id", $row['order_promotion_id']),
									($zaiusUseUpc && !empty($productRow['upc_code'])) ? $productRow['upc_code'] : $row['product_id'],
									$productRow['product_code'],
									$row['sale_price'],
									$row['quantity'],
									0, // order item discount is not saved after order is placed.
									($row['item_deleted'] == 1 ? -1 : 1) * ($row['sale_price'] * $row['quantity']),
									strtotime($row['order_time'])
								);
								echo createCsvRow($exportRow);
							}
							freeResult($refundResult);
						}
						$exportRow = array(
							$contactRow['email_address'],
							$contactRow['first_name'],
							$contactRow['last_name'],
							(empty($row['phone_number']) ? getContactPhoneNumber($row['contact_id']) : $row['phone_number']),
							static::formatZaiusAddress($billingRow),
							static::formatZaiusAddress($addressRow),
							"purchase",
							$row['order_id'],
							($row['sales_total'] + $row['shipping_charge'] + $row['handling_charge'] + $row['order_tax_charge']),
							$row['order_discount'],
							$row['sales_total'],
							$row['order_tax_charge'],
							$row['shipping_charge'],
							getFieldFromId("promtion_code", "promotions", "promotion_id", $row['order_promotion_id']),
							($zaiusUseUpc && !empty($productRow['upc_code'])) ? $productRow['upc_code'] : $row['product_id'],
							$productRow['product_code'],
							$row['sale_price'],
							$row['quantity'],
							0, // order item discount is not saved after order is placed.
							$row['sale_price'] * $row['quantity'],
							strtotime($row['order_time'])
						);
						echo createCsvRow($exportRow);
					} else if ($summaryReport) {
						if (!array_key_exists($productRow['product_id'], $productTotals)) {
							$productRow['department_description'] = $departmentDescription;
							$productTotals[$productRow['product_id']] = array("product_row" => $productRow, "quantity" => "0", "total_income" => 0);
						}
						$productTotals[$productRow['product_id']]['quantity'] += $row['quantity'];
						$productTotals[$productRow['product_id']]['total_income'] += ($row['quantity'] * $row['sale_price']);
					} else {
						$exportRow = array(
							date("m/d/Y", strtotime($row['order_time'])),
							$row['order_id'],
							$productRow['product_code'],
							$productRow['description'],
							$productRow['upc_code'],
							$departmentDescription,
							getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $productRow['product_manufacturer_id']),
							getFieldFromId("description", "order_status", "order_status_id", $row['order_status_id']),
							getFieldFromId("description", "order_item_statuses", "order_item_status_id", $row['order_item_status_id']),
							getFieldFromId("description", "sources", "source_id", $row['source_id']),
							$row['location_description'],
							$row['quantity'],
							$row['sale_price'],
							getDisplayName($row['contact_id'], array("include_company" => true)),
							$row['full_name'],
							$addressRow['address_1'],
							$addressRow['address_2'],
							$addressRow['city'],
							$addressRow['state'],
							$addressRow['postal_code'],
							getFieldFromId("email_address", "contacts", "contact_id", $row['contact_id']),
							(empty($row['phone_number']) ? getContactPhoneNumber($row['contact_id']) : $row['phone_number']),
							getFieldFromId("country_name", "countries", "country_id", $addressRow['country_id']),
							(empty($row['referral_contact_id']) ? "" : getDisplayName($row['referral_contact_id'])),
							(empty($row['date_completed']) ? "" : date("m/d/Y", strtotime($row['date_completed'])))
						);
						echo createCsvRow($exportRow);
					}
				}
				if ($summaryReport) {
					usort($productTotals, array(static::class, "sortQuantity"));
					foreach ($productTotals as $productInfo) {
						$quantity = $productInfo['quantity'];
						$totalIncome = $productInfo['total_income'];
						$productRow = $productInfo['product_row'];
						$exportRow = array(
							$productRow['product_code'],
							$productRow['description'],
							$productRow['upc_code'],
							$productRow['department_description'],
							getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $productRow['product_manufacturer_id']),
							$quantity,
							$totalIncome,
							round($totalIncome / $quantity, 2)
						);
						echo createCsvRow($exportRow);
					}
				}
				$returnArray['report_export'] = ob_get_clean();
				return $returnArray;
			case "detail_addons":
				$includeAddons = true;
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "order_item_id in (select order_item_id from order_item_addons)";
			case "detail":
				?>
                <table class="grid-table">
                    <tr>
                        <th>Order Date</th>
                        <th>Order Number</th>
                        <th>Product Code</th>
                        <th>Description</th>
                        <th>UPC</th>
                        <th>Manufacturer</th>
                        <th>Order Status</th>
                        <th>Item Status</th>
                        <th>Source</th>
                        <th>Location</th>
                        <th>Contact</th>
						<?= ($includeAddons ? "<th>Add-ons</th>" : "") ?>
                        <th>Quantity</th>
                        <th>Sale Price</th>
						<?= ($includeAddons ? "<th>Total Add-ons Price</th>" : "") ?>
                        <th>Total</th>
                    </tr>
					<?php
					$orderIdArray = array();
					$productArray = array();
					$resultSet = executeReadQuery("select *, (select description from locations where location_id in "
						. "(select location_id from order_shipments where order_id = orders.order_id and order_shipment_id in "
						. "(select order_shipment_id from order_shipment_items where order_item_id = order_items.order_item_id)) limit 1) as location_description "
						. "from orders join order_items using (order_id) where client_id = ?"
						. (empty($whereStatement) ? "" : " and " . $whereStatement) . " order by order_time", $parameters);
					while ($row = getNextRow($resultSet)) {
						if (!array_key_exists($row['product_id'], $productArray)) {
							$productArray[$row['product_id']] = array();
						}
						$productArray[$row['product_id']][] = $row;
					}
					foreach ($productArray as $productId => $productRows) {
						$firstLine = true;
						$totalQuantity = 0;
						$totalAmount = 0;
						$totalAddonsAmount = 0;
						$productRow = ProductCatalog::getCachedProductRow($productId);
						$productManufacturerId = $productRow['product_manufacturer_id'];
						foreach ($productRows as $row) {
							$orderIdArray[] = $row['order_id'];
							if (empty($row['address_id'])) {
								$resultSet = executeReadQuery("select * from contacts where contact_id = ?", $row['contact_id']);
							} else {
								$resultSet = executeReadQuery("select * from addresses where address_id = ?", $row['address_id']);
							}
							$contactRow = getNextRow($resultSet);
							$addressBlock = (empty($row['full_name']) ? getDisplayName($row['contact_id']) : $row['full_name']);
							if (!empty($contactRow['address_1'])) {
								$addressBlock .= "<br>" . $contactRow['address_1'];
							}
							$city = $contactRow['city'];
							if (!empty($contactRow['state'])) {
								$city .= (empty($city) ? "" : ", ") . $contactRow['state'];
							}
							if (!empty($contactRow['postal_code'])) {
								$city .= (empty($city) ? "" : " ") . $contactRow['postal_code'];
							}
							if (!empty($city)) {
								$addressBlock .= "<br>" . $city;
							}
							if ($contactRow['country_id'] != 1000) {
								$addressBlock .= "<br>" . getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id']);
							}
							$addressBlock .= "<br>" . getFieldFromId("email_address", "contacts", "contact_id", $row['contact_id']);
							if (empty($row['phone_number'])) {
								$phoneSet = executeReadQuery("select * from phone_numbers where contact_id = ?", $row['contact_id']);
								while ($phoneRow = getNextRow($phoneSet)) {
									$addressBlock .= "<br>" . $phoneRow['phone_number'] . (empty($phoneRow['description']) ? "" : " " . $phoneRow['description']);
								}
							} else {
								$addressBlock .= "<br>" . $row['phone_number'];
							}
							if (empty($row['location_description']) && !empty($productRow['virtual_product'])) {
								$defaultLocationId = CustomField::getCustomFieldData($row['contact_id'], "DEFAULT_LOCATION_ID");
								if (!empty($defaultLocationId)) {
									$row['location_description'] = getFieldFromId("description", "locations", "location_id", $defaultLocationId);
								}
							}
							$productAddons = "";
							$productAddonsPrice = 0;
							if ($includeAddons) {
								$addonSet = executeQuery("select * from product_addons join order_item_addons using (product_addon_id) where order_item_id = ?", $row['order_item_id']);
								while ($addonRow = getNextRow($addonSet)) {
									$productAddons .= htmlText((empty($addonRow['group_description']) ? "" : $addonRow['group_description'] . ": ") . $addonRow['description']) . ($addonRow['quantity'] <= 1 ? "" : " (Qty: " . $addonRow['quantity'] . ")") . "<br>";
									$productAddonsPrice += ($row['quantity'] * $addonRow['quantity'] * $addonRow['sale_price']);
								}
							}
							?>
                            <tr>
                                <td><?= date("m/d/Y", strtotime($row['order_time'])) ?></td>
								<?php if (canAccessPageCode("ORDERDASHBOARD")) { ?>
                                    <td><a href='/orderdashboard.php?clear_filter=true&url_page=show&primary_id=<?= $row['order_id'] ?>'><?= $row['order_id'] ?></a></td>
								<?php } else { ?>
                                    <td><?= $row['order_id'] ?></td>
								<?php } ?>
                                <td><?= ($firstLine ? htmlText($productRow['product_code']) : "") ?></td>
                                <td><?= ($firstLine ? htmlText($productRow['description']) : "") ?></td>
                                <td><?= ($firstLine ? htmlText($productRow['upc_code']) : "") ?></td>
                                <td><?= ($firstLine ? htmlText(getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $productManufacturerId)) : "") ?></td>
                                <td><?= getFieldFromId("description", "order_status", "order_status_id", $row['order_status_id']) ?></td>
                                <td><?= getFieldFromId("description", "order_item_statuses", "order_item_status_id", $row['order_item_status_id']) ?></td>
                                <td><?= getFieldFromId("description", "sources", "source_id", $row['source_id']) ?></td>
                                <td><?= $row['location_description'] ?></td>
                                <td><?= $addressBlock ?></td>
								<?= ($includeAddons ? "<td>" . $productAddons . "</td>" : "") ?>
                                <td class="align-right"><?= $row['quantity'] ?></td>
                                <td class="align-right"><?= showSignificant($row['sale_price'], 2) ?></td>
								<?= ($includeAddons ? "<td>" . showSignificant($productAddonsPrice, 2) . "</td>" : "") ?>
                                <td class="align-right"><?= showSignificant(($row['quantity'] * $row['sale_price']), 2) ?></td>
                            </tr>
							<?php
							$totalQuantity += $row['quantity'];
							$totalAmount += ($row['quantity'] * $row['sale_price']);
							$totalAddonsAmount += $productAddonsPrice;
							$firstLine = false;
						}
						$reportTotalQuantity += $totalQuantity;
						$reportTotalAmount += $totalAmount;
						$reportTotalAddonsAmount += $totalAddonsAmount;
						?>
                        <tr>
                            <td colspan="2"></td>
                            <td class="highlighted-text"><?= htmlText($productRow['product_code']) ?></td>
                            <td class="highlighted-text"><?= htmlText($productRow['description']) ?></td>
                            <td class="highlighted-text"><?= htmlText($productRow['upc_code']) ?></td>
                            <td colspan="<?= $includeAddons ? 7 : 6 ?>"></td>
                            <td class="highlighted-text align-right"><?= $totalQuantity ?></td>
                            <td class="align-right"></td>
							<?= ($includeAddons ? "<td>" . showSignificant($totalAddonsAmount, 2) . "</td>" : "") ?>
                            <td class="highlighted-text align-right"><?= showSignificant($totalAmount, 2) ?></td>
                        </tr>
						<?php
					}
					?>
                    <tr>
                        <td colspan="5" class="highlighted-text">Report Total</td>
                        <td colspan="<?= $includeAddons ? 7 : 6 ?>"></td>
                        <td class="highlighted-text align-right"><?= $reportTotalQuantity ?></td>
                        <td class="align-right"></td>
						<?= ($includeAddons ? "<td>" . showSignificant($reportTotalAddonsAmount, 2) . "</td>" : "") ?>
                        <td class="highlighted-text align-right"><?= showSignificant($reportTotalAmount, 2) ?></td>
                    </tr>
                </table>
				<?php
				break;
			case "summary":
				$productArray = array();
				$resultSet = executeReadQuery("select * from order_items where deleted = 0 and order_id in (select order_id from orders where client_id = ? and deleted = 0" .
					(empty($whereStatement) ? "" : " and " . $whereStatement) . ")", $parameters);
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['product_id'], $productArray)) {
						$productArray[$row['product_id']] = array("product_id" => $row['product_id'], "quantity" => "0", "total_amount" => "0");
					}
					$productArray[$row['product_id']]['quantity'] += $row['quantity'];
					$productArray[$row['product_id']]['total_amount'] += round($row['quantity'] * $row['sale_price'], 2);
				}
				if (!empty($_POST['sort_quantity'])) {
					usort($productArray, array(static::class, "sortQuantity"));
				}
				?>
                <table class="grid-table">
                    <tr>
                        <th>Description</th>
                        <th>UPC</th>
                        <th>Quantity</th>
                        <th>Total Income</th>
                        <th>Average Price</th>
                    </tr>
					<?php
					foreach ($productArray as $productInfo) {
						$productId = $productInfo['product_id'];
						?>
                        <tr>
                            <td><?= htmlText(getFieldFromId("product_code", "products", "product_id", $productId) . " - " . getFieldFromId("description", "products", "product_id", $productId)) ?></td>
                            <td><?= htmlText(getFieldFromId("upc_code", "product_data", "product_id", $productId)) ?></td>
                            <td class="align-right"><?= $productInfo['quantity'] ?></td>
                            <td class="align-right"><?= showSignificant($productInfo['total_amount'], 2) ?></td>
                            <td class="align-right"><?= showSignificant($productInfo['total_amount'] / $productInfo['quantity'], 2) ?></td>
                        </tr>
						<?php
						$reportTotalQuantity += $productInfo['quantity'];
						$reportTotalAmount += $productInfo['total_amount'];
					}
					?>
                    <tr>
                        <td colspan="2" class="highlighted-text">Report Total</td>
                        <td class="highlighted-text align-right"><?= $reportTotalQuantity ?></td>
                        <td class="highlighted-text align-right"><?= showSignificant($reportTotalAmount, 2) ?></td>
                        <td class="align-right"></td>
                    </tr>
                </table>
				<?php
				break;
			case "source":
				$sourceArray = array();
				$resultSet = executeReadQuery("select *,(select description from sources where source_id = (select source_id from orders where order_id = order_items.order_id)) source_description," .
					"(select source_code from sources where source_id = (select source_id from orders where order_id = order_items.order_id)) source_code," .
					"(select source_id from orders where order_id = order_items.order_id) source_id, " .
					"(select sort_order from products where product_id = order_items.product_id) product_sort_order from order_items where deleted = 0 and order_id in (select order_id from orders where client_id = ? and deleted = 0" .
					(empty($whereStatement) ? "" : " and " . $whereStatement) . ") order by source_description,product_sort_order,product_id", $parameters);
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['source_id'], $sourceArray)) {
						$sourceArray[$row['source_id']] = array("description" => $row['source_description'], "source_code" => $row['source_description'], "products" => array());
					}
					if (!array_key_exists($row['product_id'], $sourceArray[$row['source_id']]['products'])) {
						$sourceArray[$row['source_id']]['products'][$row['product_id']] = array("quantity" => 0, "total_amount" => 0);
					}
					$sourceArray[$row['source_id']]['products'][$row['product_id']]['quantity'] += $row['quantity'];
					$sourceArray[$row['source_id']]['products'][$row['product_id']]['total_amount'] += round($row['quantity'] * $row['sale_price'], 2);
				}
				?>
                <table class="grid-table">
                    <tr>
                        <th>Source</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Total Income</th>
                    </tr>
					<?php
					foreach ($sourceArray as $sourceId => $sourceInfo) {
						$firstOne = true;
						$totalQuantity = 0;
						$totalAmount = 0;
						foreach ($sourceInfo['products'] as $productId => $productInfo) {
							$totalQuantity += $productInfo['quantity'];
							$totalAmount += $productInfo['total_amount'];
							?>
                            <tr>
                                <td><?= htmlText(($firstOne ? (empty($sourceInfo['source_code']) ? "[None]" : $sourceInfo['source_code']) : "")) ?></td>
                                <td><?= htmlText(getFieldFromId("product_code", "products", "product_id", $productId) . " - " . getFieldFromId("description", "products", "product_id", $productId)) ?></td>
                                <td class="align-right"><?= $productInfo['quantity'] ?></td>
                                <td class="align-right"><?= showSignificant($productInfo['total_amount'], 2) ?></td>
                            </tr>
							<?php
							$firstOne = false;
						}
						?>
                        <tr>
                            <td class="highlighted-text"><?= htmlText((empty($sourceInfo['description']) ? "[None]" : $sourceInfo['description'])) ?></td>
                            <td class="align-right highlighted-text">Total</td>
                            <td class="align-right"><?= $totalQuantity ?></td>
                            <td class="align-right"><?= showSignificant($totalAmount, 2) ?></td>
                        </tr>
						<?php
						$reportTotalQuantity += $totalQuantity;
						$reportTotalAmount += $totalAmount;
					}
					?>
                    <tr>
                        <td class="highlighted-text">Report Total</td>
                        <td class="align-right"></td>
                        <td class="highlighted-text align-right"><?= $reportTotalQuantity ?></td>
                        <td class="highlighted-text align-right"><?= showSignificant($reportTotalAmount, 2) ?></td>
                    </tr>
                </table>
				<?php
				break;
			case "referral":
				$referralArray = array();
				$resultSet = executeReadQuery("select *,(select last_name from contacts where contact_id = (select referral_contact_id from orders where order_id = order_items.order_id)) last_name," .
					"(select first_name from contacts where contact_id = (select referral_contact_id from orders where order_id = order_items.order_id)) first_name, " .
					"(select referral_contact_id from orders where order_id = order_items.order_id) referral_contact_id, " .
					"(select sort_order from products where product_id = order_items.product_id) product_sort_order from order_items where deleted = 0 and order_id in (select order_id from orders where client_id = ? and deleted = 0" .
					(empty($whereStatement) ? "" : " and " . $whereStatement) . ") order by last_name,first_name,product_sort_order,product_id", $parameters);
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['referral_contact_id'], $referralArray)) {
						$referralArray[$row['referral_contact_id']] = array("products" => array());
					}
					if (!array_key_exists($row['product_id'], $referralArray[$row['referral_contact_id']]['products'])) {
						$referralArray[$row['referral_contact_id']]['products'][$row['product_id']] = array("quantity" => 0, "total_amount" => 0);
					}
					$referralArray[$row['referral_contact_id']]['products'][$row['product_id']]['quantity'] += $row['quantity'];
					$referralArray[$row['referral_contact_id']]['products'][$row['product_id']]['total_amount'] += round($row['quantity'] * $row['sale_price'], 2);
				}
				?>
                <table class="grid-table">
                    <tr>
                        <th>Referred By</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Total Income</th>
                    </tr>
					<?php
					foreach ($referralArray as $referralContactId => $referralInfo) {
						$firstOne = true;
						$totalQuantity = 0;
						$totalAmount = 0;
						foreach ($referralInfo['products'] as $productId => $productInfo) {
							$totalQuantity += $productInfo['quantity'];
							$totalAmount += $productInfo['total_amount'];
							?>
                            <tr>
                                <td><?= htmlText($firstOne ? empty($referralContactId) ? "[None]" : getDisplayName($referralContactId) : "") ?></td>
                                <td><?= htmlText(getFieldFromId("product_code", "products", "product_id", $productId) . " - " . getFieldFromId("description", "products", "product_id", $productId)) ?></td>
                                <td class="align-right"><?= $productInfo['quantity'] ?></td>
                                <td class="align-right"><?= showSignificant($productInfo['total_amount'], 2) ?></td>
                            </tr>
							<?php
							$firstOne = false;
						}
						?>
                        <tr>
                            <td class="highlighted-text"><?= htmlText(empty($referralContactId) ? "[None]" : getDisplayName($referralContactId)) ?></td>
                            <td class="align-right highlighted-text">Total</td>
                            <td class="align-right"><?= $totalQuantity ?></td>
                            <td class="align-right"><?= showSignificant($totalAmount, 2) ?></td>
                        </tr>
						<?php
						$reportTotalQuantity += $totalQuantity;
						$reportTotalAmount += $totalAmount;
					}
					?>
                    <tr>
                        <td class="highlighted-text">Report Total</td>
                        <td class="align-right"></td>
                        <td class="highlighted-text align-right"><?= $reportTotalQuantity ?></td>
                        <td class="highlighted-text align-right"><?= showSignificant($reportTotalAmount, 2) ?></td>
                    </tr>
                </table>
				<?php
				break;
			case "gifts":
				$resultSet = executeReadQuery("select * from order_items,orders where order_items.order_id = orders.order_id and gift_order = 1 and " .
					"order_items.deleted = 0 and client_id = ? and orders.deleted = 0" .
					(empty($whereStatement) ? "" : " and " . $whereStatement) . " order by order_time", $parameters);
				?>
                <table class="grid-table">
                    <tr>
                        <th>Date</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Gift Text</th>
                        <th>Product</th>
                        <th>Quantity</th>
                    </tr>
					<?php
					$saveOrderId = "";
					while ($row = getNextRow($resultSet)) {
						if ($row['order_id'] == $saveOrderId) {
							$fromName = "";
							$toName = "";
						} else {
							$fromName = getDisplayName($row['contact_id']);
							$toName = htmlText($row['full_name']);
							$addressSet = executeReadQuery("select * from addresses where address_id = ?", $row['address_id']);
							if ($addressRow = getNextRow($addressSet)) {
								$toName .= (empty($addressRow['address_1']) ? "" : "<br/>" . htmlText($addressRow['address_1'])) .
									(empty($addressRow['address_2']) ? "" : "<br/>" . htmlText($addressRow['address_2'])) .
									(empty($addressRow['city']) ? "" : "<br/>" . htmlText($addressRow['city'])) .
									(empty($addressRow['state']) ? "" : ($addressRow['country_id'] < 1002 ? ", " : "<br/>") . htmlText($addressRow['state'])) .
									(empty($addressRow['postal_code']) ? "" : ($addressRow['country_id'] < 1002 ? " " : "<br/>") . htmlText($addressRow['postal_code'])) .
									($addressRow['country_id'] == 1000 ? "" : "<br/>" . htmlText(getFieldFromId("country_name", "countries", "country_id", $addressRow['country_id'])));
							}
						}
						?>
                        <tr>
                            <td><?= date("m/d/Y", strtotime($row['order_time'])) ?></td>
                            <td><?= htmlText($fromName) ?></td>
                            <td><?= $toName ?></td>
                            <td><?= htmlText($row['gift_text']) ?></td>
                            <td><?= htmlText(getFieldFromId("product_code", "products", "product_id", $row['product_id']) . " - " . getFieldFromId("description", "products", "product_id", $row['product_id'])) ?></td>
                            <td class="align-right"><?= $row['quantity'] ?></td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
				break;
			case "gift_csv":
				$resultSet = executeReadQuery("select * from order_items,orders where order_items.order_id = orders.order_id and gift_order = 1 and " .
					"order_items.deleted = 0 and client_id = ? and orders.deleted = 0" .
					(empty($whereStatement) ? "" : " and " . $whereStatement) . " order by order_time", $parameters);
				$returnArray['export_headers'] = array();
				$returnArray['export_headers'][] = "Content-Type: text/csv";
				$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"gifts.csv\"";
				$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
				$returnArray['export_headers'][] = 'Pragma: public';
				$returnArray['filename'] = "gifts.csv";

				echo '"OrderDate","From","FromEmail","Name","Address1","Address2","City","State","PostalCode","Country","GiftText","Product","Quantity"' . "\n";
				while ($row = getNextRow($resultSet)) {
					$fromName = getDisplayName($row['contact_id']);
					$fromEmail = getFieldFromId("email_address", "contacts", "contact_id", $row['contact_id']);
					$toName = htmlText($row['full_name']);
					$addressSet = executeReadQuery("select * from addresses where address_id = ?", $row['address_id']);
					if (!$addressRow = getNextRow($addressSet)) {
						$addressRow = array();
						$toName .= (empty($addressRow['address_1']) ? "" : "<br/>" . htmlText($addressRow['address_1'])) .
							(empty($addressRow['address_2']) ? "" : "<br/>" . htmlText($addressRow['address_2'])) .
							(empty($addressRow['city']) ? "" : "<br/>" . htmlText($addressRow['city'])) .
							(empty($addressRow['state']) ? "" : ($addressRow['country_id'] < 1002 ? ", " : "<br/>") . htmlText($addressRow['state'])) .
							(empty($addressRow['postal_code']) ? "" : ($addressRow['country_id'] < 1002 ? " " : "<br/>") . htmlText($addressRow['postal_code'])) .
							($addressRow['country_id'] == 1000 ? "" : "<br/>" . htmlText(getFieldFromId("country_name", "countries", "country_id", $addressRow['country_id'])));
					}
					echo '"' . date("m/d/Y", strtotime($row['order_time'])) . '",';
					echo '"' . str_replace('"', '""', $fromName) . '",';
					echo '"' . str_replace('"', '""', $fromEmail) . '",';
					echo '"' . str_replace('"', '""', $toName) . '",';
					echo '"' . str_replace('"', '""', $addressRow['address_1']) . '",';
					echo '"' . str_replace('"', '""', $addressRow['address_2']) . '",';
					echo '"' . str_replace('"', '""', $addressRow['city']) . '",';
					echo '"' . str_replace('"', '""', $addressRow['state']) . '",';
					echo '"' . str_replace('"', '""', $addressRow['postal_code']) . '",';
					echo '"' . str_replace('"', '""', getFieldFromId("country_name", "countries", "country_id", $addressRow['country_id'])) . '",';
					echo '"' . str_replace('"', '""', $row['gift_text']) . '",';
					echo '"' . str_replace('"', '""', getFieldFromId("description", "products", "product_id", $row['product_id'])) . '",';
					echo '"' . str_replace('"', '""', $row['quantity']) . '"' . "\r\n";
				}
				$returnArray['report_export'] = ob_get_clean();
				return $returnArray;
		}

		$returnArray['report_title'] = "Order Items Report";
		$returnArray['report_content'] = ob_get_clean();
		return $returnArray;
	}

	private static function formatZaiusAddress($addressRow) {
		$returnValue = "";
		$addressFields = array("address_1", "address_2", "city", "state", "postal_code");
		foreach ($addressFields as $thisField) {
			$returnValue .= (empty($returnValue) ? "" : ",") . str_replace(",", "", $addressRow[$thisField]);
		}
		$returnValue .= "," . getFieldFromId('country_code', 'countries', 'country_id', $addressRow['country_id']);
		return $returnValue;
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="summary">Summary</option>
                        <option selected value="detail">Detail</option>
                        <option value="summary_csv">Summary Export CSV</option>
                        <option value="csv">Export CSV</option>
                        <option value="zaius_csv">Export CSV for Zaius</option>
                        <option value="detail_addons">Addons</option>
                        <option value="source">Source</option>
                        <option value="referral">Referral</option>
                        <option value="gifts">Gifts</option>
                        <option value="gift_csv" data-report_type="download">Gifts CSV</option>
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

				<?php
				$resultSet = executeReadQuery("select * from sources where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
				if ($resultSet['row_count'] > 0) {
					?>
                    <div class="basic-form-line" id="_source_id_row">
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
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
				<?php } ?>

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
                    <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_products_row">
                        <label for="products">Products</label>
						<?= $customControl->getControl() ?>
                    </div>
				<?php } else { ?>
					<?= createFormControl("order_items", "product_id", array("not_null" => false, "help_label" => "leave blank to include all")) ?>
				<?php } ?>

				<?php
				$productsControl = new DataColumn("product_category_groups");
				$productsControl->setControlValue("data_type", "custom");
				$productsControl->setControlValue("include_inactive", "true");
				$productsControl->setControlValue("control_class", "MultiSelect");
				$productsControl->setControlValue("control_table", "product_category_groups");
				$productsControl->setControlValue("links_table", "product_category_group_links");
				$productsControl->setControlValue("primary_table", "product_categories");
				$customControl = new MultipleSelect($productsControl, $this);
				?>
                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_product_category_groups_row">
                    <label for="product_category_groups">Product Category Groups</label>
					<?= $customControl->getControl() ?>
                </div>

				<?php
				$productsControl = new DataColumn("product_categories");
				$productsControl->setControlValue("data_type", "custom");
				$productsControl->setControlValue("include_inactive", "true");
				$productsControl->setControlValue("control_class", "MultiSelect");
				$productsControl->setControlValue("control_table", "product_categories");
				$productsControl->setControlValue("links_table", "product_category_links");
				$productsControl->setControlValue("primary_table", "products");
				$customControl = new MultipleSelect($productsControl, $this);
				?>
                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_product_categories_row">
                    <label for="product_categories">Product Categories</label>
					<?= $customControl->getControl() ?>
                </div>

				<?php
				$productsControl = new DataColumn("product_tags");
				$productsControl->setControlValue("data_type", "custom");
				$productsControl->setControlValue("include_inactive", "true");
				$productsControl->setControlValue("control_class", "MultiSelect");
				$productsControl->setControlValue("control_table", "product_tags");
				$productsControl->setControlValue("links_table", "product_tag_links");
				$productsControl->setControlValue("primary_table", "products");
				$customControl = new MultipleSelect($productsControl, $this);
				?>
                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_product_tags_row">
                    <label for="product_tags">Product Tags</label>
					<?= $customControl->getControl() ?>
                </div>

                <div class="basic-form-line" id="_product_manufacturer_id_row">
                    <label for="product_manufacturer_id">Manufacturer</label>
                    <select tabindex="10" id="product_manufacturer_id" name="product_manufacturer_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from product_manufacturers where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_manufacturer_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php
				$userTypeControl = new DataColumn("user_types");
				$userTypeControl->setControlValue("data_type", "custom");
				$userTypeControl->setControlValue("include_inactive", "true");
				$userTypeControl->setControlValue("control_class", "MultiSelect");
				$userTypeControl->setControlValue("control_table", "user_types");
				$userTypeControl->setControlValue("primary_table", "users");
				$customControl = new MultipleSelect($userTypeControl, $this);
				?>
                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_user_types_row">
                    <label for="user_types">User Types</label>
					<?= $customControl->getControl() ?>
                </div>

                <div class="basic-form-line" id="_exclude_user_types_row">
                    <input type="checkbox" name="exclude_user_types" id="exclude_user_types" value="1"><label class="checkbox-label" for="exclude_user_types">Exclude (instead of include) these user types</label>
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

                <div class="basic-form-line" id="_exclude_order_status_row">
                    <input type="checkbox" name="exclude_order_status" id="exclude_order_status" value="1"><label class="checkbox-label" for="exclude_order_status">Exclude (instead of include) the previous statuses</label>
                </div>

                <div class="basic-form-line" id="_not_shipped_row">
                    <input tabindex="10" type="checkbox" id="not_shipped" name="not_shipped"><label class="checkbox-label" for="not_shipped">Only Not Shipped</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_sort_quantity_row">
                    <input tabindex="10" type="checkbox" id="sort_quantity" name="sort_quantity"><label class="checkbox-label" for="sort_quantity">Sort by Quantity (Summary Report Only)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_completed_row">
                    <span class="checkbox-label">Completed Orders:</span>
                    <input type="radio" checked tabindex="10" id="completed-include" name="completed" value=""><label class="checkbox-label" for="completed-include">Include</label>
                    <input type="radio" tabindex="10" id="completed-hide" name="completed" value="0"><label class="checkbox-label" for="completed-hide">Hide</label>
                    <input type="radio" tabindex="10" id="completed-only" name="completed" value="1"><label class="checkbox-label" for="completed-only">Only Completed</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_deleted_row">
                    <span class="checkbox-label">Deleted Orders:</span>
                    <input type="radio" tabindex="10" id="deleted-include" name="deleted" value=""><label class="checkbox-label" for="deleted-include">Include</label>
                    <input type="radio" checked tabindex="10" id="deleted-hide" name="deleted" value="0"><label class="checkbox-label" for="deleted-hide">Hide</label>
                    <input type="radio" tabindex="10" id="deleted-only" name="deleted" value="1"><label class="checkbox-label" for="deleted-only">Only Deleted</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_deleted_items_row">
                    <span class="checkbox-label">Deleted Items:</span>
                    <input type="radio" tabindex="10" id="deleted_items-include" name="deleted_items" value=""><label class="checkbox-label" for="deleted_items-include">Include</label>
                    <input type="radio" checked tabindex="10" id="deleted_items-hide" name="deleted_items" value="0"><label class="checkbox-label" for="deleted_items-hide">Hide</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_virtual_products_row">
                    <label for="virtual_products">Virtual Products</label>
                    <select tabindex="10" id="virtual_products" name="virtual_products">
                        <option value="">Include</option>
                        <option value="exclude">Exclude</option>
                        <option value="only">Only</option>
                    </select>
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
                    const reportType = $("#report_type").val();
                    if (reportType == "gift_csv" || reportType == "csv" || reportType == "summary_csv" || reportType == "zaius_csv") {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
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
        </style>
		<?php
	}
}

$pageObject = new OrderItemReportPage();
$pageObject->displayPage();
