<?php

/*		This software is the unpublished, confidential, proprietary, intellectual
		property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
		or used in any manner without expressed written consent from Kim David Software, LLC.
		Kim David Software, LLC owns all rights to this work and intends to keep this
		software confidential so as to maintain its value as a trade secret.

		Copyright 2004-Present, Kim David Software, LLC.

		WARNING! This code is part of the Kim David Software's Coreware system.
		Changes made to this source file will be lost when new versions of the
		system are installed.
*/

$GLOBALS['gPageCode'] = "DEALERORDERDASHBOARD";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;

class DealerOrderDashboardPage extends Page {

	var $iSearchContactFields = array("contact_id", "first_name", "last_name", "business_name", "address_1", "city", "state", "postal_code", "email_address");
	var $iSearchFields = array("full_name", "phone_number");

	function setup() {
        ProductCatalog::getInventoryAdjustmentTypes();
		$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", "PAYMENTS_NOT_COMPLETE");
		if (empty($orderStatusId)) {
			$insertSet = executeQuery("insert into order_status (client_id,order_status_code,description,display_color,internal_use_only) values (?,?,?,?,1)", $GLOBALS['gClientId'], "PAYMENTS_NOT_COMPLETE", "Payments may not have gotten completed", "#CC0000");
			$orderStatusId = $insertSet['insert_id'];
		}
		$resultSet = executeQuery("select * from orders where order_status_id = ? and deleted = 0 and date_completed is null and client_id = ?", $orderStatusId, $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$cartTotal = 0;
			$itemSet = executeQuery("select sum(quantity * sale_price) as cart_total from order_items where order_id = ?", $row['order_id']);
			if ($itemRow = getNextRow($itemSet)) {
				$cartTotal = $itemRow['cart_total'];
			}
			$paymentTotal = 0;
			$paymentSet = executeQuery("select sum(amount + shipping_charge + tax_charge + handling_charge) as payment_total from order_payments where order_id = ?", $row['order_id']);
			if ($paymentRow = getNextRow($paymentSet)) {
				$paymentTotal = $paymentRow['payment_total'];
			}
			$donationAmount = 0;
			if (!empty($row['donation_id'])) {
				$donationAmount = getFieldFromId("amount", "donations", "donation_id", $row['donation_id']);
			}
			$orderTotal = $cartTotal + $row['tax_charge'] + $row['shipping_charge'] + $row['handling_charge'] - $row['order_discount'] + $donationAmount;
			if (round($orderTotal - $paymentTotal, 2) != 0) {
				executeQuery("update orders set order_status_id = null where order_id = ?", $row['order_id']);
			}
		}

		$this->iDataSource->addColumnControl("ship_to_address", "data_type", "varchar");
		$this->iDataSource->addColumnControl("ship_to_address", "form_label", "Ship To Address");
		$this->iDataSource->addColumnControl("ship_to_address", "select_value", "if(address_id is null,(select concat_ws(',',address_1,address_2,city,state,postal_code) from contacts where contact_id = orders.contact_id),(select concat_ws(',',address_1,address_2,city,state,postal_code) from addresses where address_id = orders.address_id))");
		$this->iDataSource->addColumnControl("user_type_id", "form_label", "User Type");
		$this->iDataSource->addColumnControl("user_type_id", "data_type", "varchar");
		$this->iDataSource->addColumnControl("user_type_id", "select_value", "(select description from user_types where user_type_id = (select user_type_id from users where contact_id = orders.contact_id))");

		$this->iDataSource->addColumnControl("shipment_charges", "data_type", "decimal");
		$this->iDataSource->addColumnControl("shipment_charges", "decimal_places", "2");
		$this->iDataSource->addColumnControl("shipment_charges", "form_label", "Distributor Order Charges");
		$this->iDataSource->addColumnControl("shipment_charges", "select_value", "coalesce((select sum(shipping_charge) from order_shipments where order_id = orders.order_id and shipping_charge is not null),0)");

		$this->iDataSource->addColumnControl("order_maximum", "help_label", "Maximum a user can ever order");

		$this->iDataSource->addColumnControl("deleted", "data_type", "hidden");

		$this->iDataSource->addColumnLikeColumn("content", "order_notes", "content");
		$this->iDataSource->addColumnLikeColumn("public_access", "order_notes", "public_access");
		$this->iDataSource->addColumnControl("content", "not_null", false);
		$this->iDataSource->addColumnControl("content", "classes", "keep-visible");
		$this->iDataSource->addColumnControl("add_note", "data_type", "button");
		$this->iDataSource->addColumnControl("add_note", "button_label", "Add Note");
		$this->iDataSource->addColumnControl("add_note", "classes", "keep-visible");

		$this->iDataSource->addColumnLikeColumn("location_id", "product_inventories", "location_id");
		$this->iDataSource->addColumnControl("location_id", "not_null", false);
		$this->iDataSource->addColumnControl("location_id", "empty_text", "[None]");
		$this->iDataSource->addColumnControl("location_id", "get_choices", "locationChoices");
		$this->iDataSource->addColumnControl("location_id", "form_label", "Location/Distributor");

		$this->iDataSource->addColumnControl("create_shipment", "data_type", "button");
		$this->iDataSource->addColumnControl("create_shipment", "button_label", "Create Distributor Order");

		$this->iDataSource->addColumnControl("order_number", "form_label", "Order #");
		$this->iDataSource->addColumnControl("order_amount", "form_label", "Amount");
		$this->iDataSource->addColumnControl("order_amount", "data_type", "decimal");
		$this->iDataSource->addColumnControl("order_amount", "decimal_places", "2");
		$this->iDataSource->addColumnControl("order_amount", "select_value", "(select sum(order_items.sale_price * order_items.quantity) " .
			"from order_items where orders.order_id = order_items.order_id and deleted = 0) + shipping_charge + tax_charge + handling_charge - order_discount");
		$this->iDataSource->addColumnControl("order_subtotal", "form_label", "Subtotal");
		$this->iDataSource->addColumnControl("order_subtotal", "data_type", "decimal");
		$this->iDataSource->addColumnControl("order_subtotal", "decimal_places", "2");
		$this->iDataSource->addColumnControl("order_subtotal", "select_value", "(select sum(order_items.sale_price * order_items.quantity) " .
			"from order_items where orders.order_id = order_items.order_id and deleted = 0) - order_discount");

		$this->iDataSource->addColumnControl("date_completed", "form_label", "Completed");

		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("order_number", "full_name", "order_amount", "order_time", "order_status_id", "date_completed"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn(array("create_shipment", "add_note"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("save", "add"));
			$this->iTemplateObject->getTableEditorObject()->setMaximumListColumns(6);

			$filters = array();
			$filters['hide_deleted'] = array("form_label" => "Hide Deleted", "where" => "deleted = 0", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);
			$filters['hide_completed'] = array("form_label" => "Hide Completed", "where" => "date_completed is null", "data_type" => "tinyint", "conjunction" => "and");

			$filters['start_date'] = array("form_label" => "Start Order Date", "where" => "order_time >= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$filters['end_date'] = array("form_label" => "End Order Date", "where" => "order_time <= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$filters['start_date_completed'] = array("form_label" => "Start Date Completed", "where" => "date_completed is not null and date_completed >= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$filters['end_date_completed'] = array("form_label" => "End Date Completed", "where" => "date_completed is not null and date_completed <= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$filters['over_amount'] = array("form_label" => "Cart Total Over", "where" => "(select sum(sale_price) from order_items where order_id = orders.order_id) > %filter_value%", "data_type" => "decimal", "conjunction" => "and");

			$orderTags = array();
			$resultSet = executeQuery("select * from order_tags where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$orderTags[$row['order_tag_id']] = $row['description'];
			}
			if (count($orderTags) > 0) {
				$filters['order_tags'] = array("form_label" => "Order Tag", "where" => "order_id in (select order_id from order_tag_links where order_tag_id = %key_value%)", "data_type" => "select", "choices" => $orderTags, "conjunction" => "and");
			}

			$resultSet = executeQuery("select * from order_status where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			$orderStatus = array();
			while ($row = getNextRow($resultSet)) {
				$orderStatus[$row['order_status_id']] = $row['description'];
			}
			$filters['order_status_id'] = array("form_label" => "Order Status", "where" => "order_status_id = %key_value%", "data_type" => "select", "choices" => $orderStatus, "conjunction" => "and");
			$filters['no_order_status'] = array("form_label" => "No Status Set", "where" => "order_status_id is null", "data_type" => "tinyint");
			$paymentMethods = array();
			$resultSet = executeQuery("select * from payment_methods where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$paymentMethods[$row['payment_method_id']] = $row['description'];
			}
			$filters['payment_method'] = array("form_label" => "Payment Method", "where" => "payment_method_id = %key_value% or order_id in (select order_id from order_payments where payment_method_id = %key_value%)", "data_type" => "select", "choices" => $paymentMethods, "conjunction" => "and");
			$shippingMethods = array();
			$resultSet = executeQuery("select * from shipping_methods where client_id = ? and location_id is not null and location_id in (select location_id from user_locations where user_id = ?) order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gUserId']);
			while ($row = getNextRow($resultSet)) {
				$shippingMethods[$row['shipping_method_id']] = $row['description'];
			}
			$filters['shipping_method'] = array("form_label" => "Shipping Method", "where" => "shipping_method_id = %key_value%", "data_type" => "select", "choices" => $shippingMethods, "conjunction" => "and");
			$filters['notes'] = array("form_label" => "Notes Contain", "where" => "order_id in (select order_id from order_notes where content like %like_value%)", "data_type" => "varchar", "conjunction" => "and");
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			if (!empty(getPreference("ORDER_DASHBOARD_PICKUP_OPTIONS"))) {
				$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("pickup_ready" => array("icon" => "fad fa-truck-pickup", "label" => getLanguageText("Ready For Pickup"), "disabled" => false)));
				$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("picked_up" => array("icon" => "fad fa-check-square", "label" => getLanguageText("Picked Up"), "disabled" => false)));
			}
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("export_customers", "CSV Export Customers");
		}

		if ((empty($_GET['url_page']) || $_GET['url_page'] == "list") && empty($this->iPageData['pre_management_header'])) {
			$hideHeader = userHasAttribute("HIDE_ORDERS_REVENUE", $GLOBALS['gUserId'], false);
			if (!$hideHeader) {
				ob_start();
				?>
                <div id="_order_header_section" class="advanced-feature header-section">
                    <div id="order_filters">
                        <button accesskey="t" data-start_date='<?= date("Y-m-d") ?>' data-end_date='<?= date("Y-m-d") ?>' class="statistics-filter-button">Today</button>
                        <button data-start_date='1776-07-04' data-end_date='<?= date("Y-m-d") ?>' class="statistics-filter-button active">All Time</button>
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
				<?php
				$this->iPageData['pre_management_header'] = ob_get_clean();
			}
		}
	}

	function locationChoices($showInactive = false) {
		$locationChoices = array();
		$resultSet = executeQuery("select * from locations where inactive = 0 and client_id = ? and (location_id in (select location_id from ffl_locations where " .
			"federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id = " . $GLOBALS['gUserId'] . ")) or location_id in (select location_id from user_locations where user_id = " .
			$GLOBALS['gUserId'] . ")) order by sort_order,description",
			$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$locationChoices[$row['location_id']] = array("key_value" => $row['location_id'], "description" => $row['description'], "inactive" => !empty($row['inactive']));
			}
		}
		freeResult($resultSet);
		return $locationChoices;
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("address_id", "choices", array());
		$this->iDataSource->addColumnControl("account_id", "choices", array());
		$this->iDataSource->addColumnControl("user_id", "choices", array());

		if (!$GLOBALS['gUserRow']['full_client_access']) {
			$this->iDataSource->addFilterWhere("(shipping_method_id in (select shipping_method_id from shipping_methods where pickup = 1 and location_id is not null and location_id in (select location_id from user_locations where user_id = " . $GLOBALS['gUserId'] . ")) or federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id = " . $GLOBALS['gUserId'] . "))");
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "export_customers":
				$resultSet = executeQuery("select *,(select max(order_time) from orders where contact_id = contacts.contact_id and federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id = " . $GLOBALS['gUserId'] . ")) as order_time " .
					"from contacts where contact_id in (select contact_id from orders where federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id = " . $GLOBALS['gUserId'] . "))");
				header("Content-Type: text/csv");
				header("Content-Disposition: attachment; filename=\"customers.csv\"");
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');
				echo createCsvRow(array("First Name", "Last Name", "Email Address", "Last Order Date"));
				while ($row = getNextRow($resultSet)) {
					echo createCsvRow(array($row['first_name'], $row['last_name'], $row['email_address'], date("m/d/Y", strtotime($row['order_time']))));
				}
				exit;
			case "resend_gift_card_email":
				$orderItemRow = getRowFromId("order_items", "order_item_id", $_GET['order_item_id']);
				if (empty($orderItemRow)) {
					$returnArray['error_message'] = "Invalid Order Item";
					ajaxResponse($returnArray);
					break;
				}
				$orderId = $orderItemRow['order_id'];
				$contactRow = Contact::getContact(getFieldFromId("contact_id", "orders", "order_id", $orderId));
				$resultSet = executeQuery("select * from gift_cards where order_item_id = ?", $orderItemRow['order_item_id']);
				$emailSent = false;
				while ($row = getNextRow($resultSet)) {
					$substitutions = array();
					$substitutions['amount'] = $row['balance'];
					$substitutions['description'] = $row['description'];
					$substitutions['gift_card_number'] = $row['gift_card_number'];

					$customFieldSet = executeQuery("select * from custom_fields where client_id = ? and custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'ORDER_ITEMS')", $GLOBALS['gClientId']);
					while ($customFieldRow = getNextRow($customFieldSet)) {
						$substitutions[strtolower($customFieldRow['custom_field_code'])] = CustomField::getCustomFieldData($orderItemRow['order_item_id'], $customFieldRow['custom_field_code'], "ORDER_ITEMS");
					}

					$emailId = "";
					if (!empty($substitutions['recipient_email_address'])) {
						$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_GIFT_CARD_GIVEN",  "inactive = 0");
						$emailAddress = $substitutions['recipient_email_address'];
					}
					if (empty($emailId)) {
						$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_GIFT_CARD",  "inactive = 0");
					}
					$subject = "Gift Card";
					$body = "Your gift card number is %gift_card_number%, for the amount of %amount%.";
					$emailSent = sendEmail(array("email_id" => $emailId, "subject" => $subject, "body" => $body, "substitutions" => $substitutions, "email_addresses" => $emailAddress, "contact_id" => $contactRow['contact_id']));
				}
				$returnArray['info_message'] = ($emailSent ? "" : "NO ") . "Email Resent";
				ajaxResponse($returnArray);
				break;
			case "print_order_items":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$orderIds[] = $row['primary_identifier'];
				}
				$returnArray['order_ids'] = implode("|", $orderIds);
				ajaxResponse($returnArray);
				break;
			case "set_serial_number":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id']);
				$orderItemId = getFieldFromId("order_item_id", "order_items", "order_item_id", $_POST['order_item_id'], "order_id = ?", $orderId);
				$orderItemSerialNumberId = getFieldFromId("order_item_serial_number_id", "order_item_serial_numbers", "order_item_serial_number_id", $_POST['primary_id'],
					"order_item_id = ?", $orderItemId);
				$dataTable = new DataTable("order_item_serial_numbers");
				$returnArray['primary_id'] = $dataTable->saveRecord(array("name_values" => array("order_item_id" => $orderItemId, "serial_number" => $_POST['serial_number']), "primary_id" => $orderItemSerialNumberId));
				ajaxResponse($returnArray);
				break;
			case "remove_order_item_serial_number":
				$dataTable = new DataTable("order_item_serial_numbers");
				$orderItemSerialNumberId = getFieldFromId("order_item_serial_number_id", "order_item_serial_numbers", "order_item_serial_number_id", $_GET['delete_id'],
					"order_item_id = ?", $_GET['order_item_id']);
				$dataTable->deleteRecord(array("primary_id" => $orderItemSerialNumberId));
				ajaxResponse($returnArray);
				break;
			case "save_order_tag":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order ID";
					ajaxResponse($returnArray);
					break;
				}
				$orderTagId = getFieldFromId("order_tag_id", "order_tags", "order_tag_id", $_GET['order_tag_id']);
				if (empty($orderTagId)) {
					$returnArray['error_message'] = "Invalid Order Tag";
					ajaxResponse($returnArray);
					break;
				}
				if (empty($_GET['checked'])) {
					executeQuery("delete from order_tag_links where order_id = ? and order_tag_id = ?", $orderId, $orderTagId);
				} else {
					executeQuery("insert ignore into order_tag_links (order_id,order_tag_id) values (?,?)", $orderId, $orderTagId);
				}
				ajaxResponse($returnArray);
				break;
			case "report_taxes":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$orderIds[] = $row['primary_identifier'];
				}
				$count = 0;
				foreach ($orderIds as $orderId) {
					if (Order::reportOrderToTaxjar($orderId)) {
						$count++;
					}
				}
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				$returnArray['info_message'] = $count . " order" . ($count == 1 ? "" : "s") . " reported to Taxjar";
				ajaxResponse($returnArray);
				break;
			case "set_status":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$orderIds[] = $row['primary_identifier'];
				}
				$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_id", $_POST['order_status_id']);
				$count = 0;
				if (!empty($orderIds) && !empty($orderStatusId)) {
					$resultSet = executeQuery("update orders set order_status_id = ? where client_id = ? and order_id in (" . implode(",", $orderIds) . ")",
						$orderStatusId, $GLOBALS['gClientId']);
					$count = $resultSet['affected_rows'];
				}
				$returnArray['info_message'] = $count . " orders changed";
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,old_value,notes) values (?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'orders', 'order_id', $count . " order statuses set to " . getFieldFromId("description", "order_status", "order_status_id", $orderStatusId), jsonEncode($orderIds),
					(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
			case "order_picked_up":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order ID";
					ajaxResponse($returnArray);
					break;
				}
				$returnArray = Order::markOrderPickedUp($orderId);
				ajaxResponse($returnArray);

				break;
			case "mark_ready_for_pickup":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order ID";
					ajaxResponse($returnArray);
					break;
				}
				$returnArray = Order::markOrderReadyForPickup($orderId);
				ajaxResponse($returnArray);
				break;
			case "mark_selected_completed":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$orderIds[] = $row['primary_identifier'];
				}
				$count = 0;
				$orderIdList = "";
				foreach ($orderIds as $orderId) {
					if (Order::markOrderCompleted($orderId, date("Y-m-d"), false)) {
						$orderIdList .= (empty($orderIdList) ? "" : ",") . $orderId;
						$count++;
					}
				}
				coreSTORE::orderNotification($orderIdList, "mark_completed");
				$returnArray['info_message'] = $count . " orders marked completed";
				ajaxResponse($returnArray);
				break;
			case "mark_selected_not_completed":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$orderIds[] = $row['primary_identifier'];
				}
				$count = 0;
				$orderIdList = "";
				foreach ($orderIds as $orderId) {
					$orderId = getFieldFromId("order_id", "orders", "order_id", $orderId, "date_completed is not null");
					if (empty($orderId)) {
						ajaxResponse($returnArray);
						break;
					}
					$ordersTable = new DataTable("orders");
					$ordersTable->setSaveOnlyPresent(true);
					if ($ordersTable->saveRecord(array("name_values" => array("date_completed" => ""), "primary_id" => $orderId))) {
						$orderIdList .= (empty($orderIdList) ? "" : ",") . $orderId;
						$count++;
					}
				}
				coreSTORE::orderNotification($orderIdList, "mark_not_completed");
				$returnArray['info_message'] = $count . " orders marked NOT completed";
				ajaxResponse($returnArray);
				break;
			case "capture_payment":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				$orderPaymentId = getFieldFromId("order_payment_id", "order_payments", "order_payment_id", $_GET['order_payment_id'], "order_id = ?", $orderId);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order Payment";
					ajaxResponse($returnArray);
					break;
				}
				$orderPaymentRow = getRowFromId("order_payments", "order_payment_id", $orderPaymentId);
				if (empty($orderPaymentRow['not_captured'])) {
					$returnArray['info_message'] = "Payment already captured";
					$returnArray['transaction_identifier'] = $orderPaymentRow['transaction_identifier'];
					ajaxResponse($returnArray);
					break;
				}
				$accountRow = getRowFromId("accounts", "account_id", $orderPaymentRow['account_id']);
				$merchantAccountId = $accountRow['merchant_account_id'] ?: $GLOBALS['gMerchantAccountId'];
				$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
				if (!$eCommerce) {
					$returnArray['error_message'] = "Unable to connect to Merchant Gateway";
					ajaxResponse($returnArray);
					break;
				}
				$success = $eCommerce->captureCharge(array("transaction_identifier" => $orderPaymentRow['transaction_identifier'], "authorization_code" => $orderPaymentRow['authorization_code']));
				$response = $eCommerce->getResponse();
				if ($success) {
					executeQuery("update order_payments set payment_time = now(),transaction_identifier = ?,not_captured = 0 where order_payment_id = ?", $response['transaction_id'], $orderPaymentId);
					$returnArray['transaction_identifier'] = $response['transaction_id'];
					$returnArray['info_message'] = "Transaction successfully captured.";
				} else {
					$paymentAmount = $orderPaymentRow['amount'] + $orderPaymentRow['shipping_charge'] + $orderPaymentRow['tax_charge'] + $orderPaymentRow['handling_charge'];
					$success = $eCommerce->createCustomerProfileTransactionRequest(array("amount" => $paymentAmount, "order_number" => $orderId,
						"merchant_identifier" => $accountRow['merchant_identifier'], "account_token" => $accountRow['account_token'], "address_id" => $accountRow['address_id']));
					$response = $eCommerce->getResponse();
					if ($success) {
						executeQuery("update order_payments set payment_time = now(),transaction_identifier = ?,not_captured = 0 where order_payment_id = ?", $response['transaction_id'], $orderPaymentId);
						$returnArray['transaction_identifier'] = $response['transaction_id'];
						$returnArray['info_message'] = "Transaction capture failed, but payment authorized and captured.";
					} else {
						$returnArray['error_message'] = "Payment transaction unable to be captured";
					}
				}

				$notCaptured = false;
				$resultSet = executeQuery("select * from order_payments where order_id = ? and not_captured = 1 and deleted = 0", $orderId);
				if ($row = getNextRow($resultSet)) {
					$notCaptured = true;
				}
				if ($notCaptured) {
					$returnArray['_capture_message'] = "Distributor Orders cannot be created until all payments are captured";
				} else {
					$returnArray['_capture_message'] = "";
				}

				Order::updateGunbrokerOrder($orderId);
				ajaxResponse($returnArray);

				break;
			case "undelete_payment":
			case "delete_payment":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				$orderPaymentId = getFieldFromId("order_payment_id", "order_payments", "order_payment_id", $_GET['order_payment_id'], "order_id = ?", $orderId);
				if (empty($orderPaymentId)) {
					$returnArray['error_message'] = "Invalid Order Payment";
					ajaxResponse($returnArray);
					break;
				}
				$dataTable = new DataTable("order_payments");
				$dataTable->setSaveOnlyPresent(true);
				if (!$dataTable->saveRecord(array("name_values" => array("deleted" => ($_GET['url_action'] == "delete_payment" ? 1 : 0)), "primary_id" => $orderPaymentId))) {
					$returnArray['error_message'] = $dataTable->getErrorMessage();
				}
				$orderPaymentRow = getRowFromId("order_payments", "order_payment_id", $orderPaymentId);
				$returnArray['not_captured'] = (empty($orderPaymentRow['deleted']) ? (empty($orderPaymentRow['not_captured']) ? "Done" : "<button class='capture-payment'>Capture Now</button>") : "");

				$notCaptured = false;
				$resultSet = executeQuery("select * from order_payments where order_id = ? and not_captured = 1 and deleted = 0", $orderId);
				if ($row = getNextRow($resultSet)) {
					$notCaptured = true;
				}
				if ($notCaptured) {
					$returnArray['_capture_message'] = "Distributor Orders cannot be created until all payments are captured";
				} else {
					$returnArray['_capture_message'] = "";
				}
				ajaxResponse($returnArray);
				break;
			case "save_payment":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				if (empty($_POST['payment_time'])) {
					$_POST['payment_time'] = date("Y-m-d H:i:s");
				} else {
					$paymentDate = date("Y-m-d", strtotime($_POST['payment_time']));
					if ($paymentDate == date("Y-m-d")) {
						$_POST['payment_time'] = $paymentDate . " " . date("H:i:s");
					}
				}
				$dataTable = new DataTable("order_payments");
				$_POST['order_id'] = $orderId;
				if (!$dataTable->saveRecord(array("name_values" => $_POST, "primary_id" => ""))) {
					$returnArray['error_message'] = $dataTable->getErrorMessage();
				} else {
					$orderPayments = array();
					$resultSet = executeQuery("select * from order_payments where order_id = ? order by payment_time", $orderId);
					while ($row = getNextRow($resultSet)) {
						if (!empty($row['deleted'])) {
							continue;
						}
						$row['payment_time'] = date("m/d/Y g:i a", strtotime($row['payment_time']));
						$row['not_captured'] = (empty($row['deleted']) ? (empty($row['not_captured']) ? "Done" : "<button class='capture-payment'>Capture Now</button>") : "");
						$row['payment_method'] = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']);
						$row['account_number'] = (empty($row['account_id']) ? $row['reference_number'] : substr(getFieldFromId("account_number", "accounts", "account_id", $row['account_id']), -8));
						$row['total_amount'] = number_format($row['amount'] + $row['shipping_charge'] + $row['tax_charge'] + $row['handling_charge'], 2, ".", ",");
						$row['amount'] = number_format($row['amount'], 2, ".", ",");
						$row['shipping_charge'] = number_format($row['shipping_charge'], 2, ".", ",");
						$row['tax_charge'] = number_format($row['tax_charge'], 2, ".", ",");
						$row['handling_charge'] = number_format($row['handling_charge'], 2, ".", ",");
						$orderPayments[] = $row;
					}
					$returnArray['order_payments'] = $orderPayments;
				}
				Order::updateGunbrokerOrder($orderId);
				ajaxResponse($returnArray);
				break;
			case "distributor_order_product":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id']);
				$productId = getFieldFromId("product_id", "order_items", "product_id", $_POST['product_id'], "order_id = ?", $orderId);
				if (empty($orderId) || empty($productId)) {
					$returnArray['error_message'] = "Invalid Product";
					ajaxResponse($returnArray);
					break;
				}
				$quantity = $_POST['quantity'];
				if (empty($quantity) || !is_numeric($quantity)) {
					$returnArray['error_message'] = "Invalid Quantity";
					ajaxResponse($returnArray);
					break;
				}
				if (!empty($_POST['order_item_id'])) {
					executeQuery("delete from distributor_order_products where order_item_id = ?", $_POST['order_item_id']);
				}
				executeQuery("insert into distributor_order_products (client_id,product_id,quantity,location_id,order_item_id,user_id,notes) values (?,?,?,?,?, ?,?)",
					$GLOBALS['gClientId'], $productId, $quantity, $_POST['location_id'], $_POST['order_item_id'], $GLOBALS['gUserId'], "For order ID " . $orderId);
				$returnArray['info_message'] = $quantity . " added to distributor order products";
				ajaxResponse($returnArray);
				break;
			case "resend_receipt":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Order not found";
					ajaxResponse($returnArray);
					break;
				}
				if (Order::sendReceipt($orderId)) {
					$returnArray['info_message'] = "Receipt successfully resent";
				} else {
					$returnArray['error_message'] = "Error sending email";
				}
				ajaxResponse($returnArray);
				break;

			case "send_tracking_email":
				$orderShipmentId = getFieldFromId("order_shipment_id", "order_shipments", "order_shipment_id", $_POST['order_shipment_id'], "order_id in (select order_id from orders where client_id = ?)", $GLOBALS['gClientId']);
				if (empty($orderShipmentId)) {
					$returnArray['error_message'] = "Invalid Distributor Order";
					ajaxResponse($returnArray);
					break;
				}
				Order::sendTrackingEmail($orderShipmentId);
				ajaxResponse($returnArray);
				break;
			case "get_order_shipments":
				$orderRow = getRowFromId("orders", "order_id", $_GET['order_id']);
				if (empty($orderRow)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				$orderItemTotal = 0;
				$resultSet = executeQuery("select * from order_items where order_id = ?", $_GET['order_id']);
				while ($row = getNextRow($resultSet)) {
					$orderItemTotal += $row['sale_price'] * $row['quantity'];
				}

				$orderShipments = array();
				$orderShipmentItemsQuantity = 0;
				$resultSet = executeQuery("select *,(select order_number from remote_orders where remote_order_id = order_shipments.remote_order_id) order_number from order_shipments left join shipping_carriers sc using (shipping_carrier_id) " .
					"where order_id = ? order by date_shipped", $_GET['order_id']);
				while ($row = getNextRow($resultSet)) {
					$row['location'] = getFieldFromId("description", "locations", "location_id", $row['location_id']);
					$row['product_distributor_id'] = getFieldFromId("product_distributor_id", "locations", "location_id", $row['location_id']);
					$row['date_shipped'] = date("m/d/Y", strtotime($row['date_shipped']));
					$row['order_shipment_items'] = array();
					$itemSet = executeQuery("select *,(select sale_price from order_items where order_item_id = order_shipment_items.order_item_id) as sale_price," .
						"(select product_id from order_items where order_item_id = order_shipment_items.order_item_id) as product_id from order_shipment_items where " .
						"order_shipment_id = ?", $row['order_shipment_id']);
					$shipmentItemTotal = 0;
					while ($itemRow = getNextRow($itemSet)) {
						$itemRow['product_description'] = getFieldFromId("description", "products", "product_id", $itemRow['product_id']);
						$row['order_shipment_items'][] = $itemRow;
						$shipmentItemTotal += $itemRow['sale_price'] * $itemRow['quantity'];
						$orderShipmentItemsQuantity += $itemRow['quantity'];
					}
					if ($orderItemTotal == 0) {
						$shippingCharge = 0;
						$taxCharge = 0;
						$handlingCharge = 0;
					} else {
						$shippingCharge = ($shipmentItemTotal / $orderItemTotal) * $orderRow['shipping_charge'];
						$taxCharge = ($shipmentItemTotal / $orderItemTotal) * $orderRow['tax_charge'];
						$handlingCharge = ($shipmentItemTotal / $orderItemTotal) * $orderRow['handling_charge'];
					}
					$row['shipment_amount'] = number_format($shipmentItemTotal + $shippingCharge + $taxCharge + $handlingCharge, 2, ".", ",");
					$row['shipping_charge'] = (empty($row['shipping_charge']) ? "" : number_format($row['shipping_charge'], 2, ".", ","));
					if (!empty($row['tracking_identifier'])) {
						$row['link_url'] = str_replace("%tracking_identifier%", $row['tracking_identifier'], $row['link_url']);
					} else {
						$row['link_url'] = "";
					}
					$orderShipments[] = $row;
				}
				$returnArray['order_shipments'] = $orderShipments;
				$returnArray['order_shipment_items_quantity'] = $orderShipmentItemsQuantity;
				ajaxResponse($returnArray);
				break;

			case "send_email":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				$subject = $_POST['email_subject'];
				$body = $_POST['email_body'];
				if (empty($subject) || empty($body)) {
					$returnArray['error_message'] = "Required information is missing";
					ajaxResponse($returnArray);
					break;
				}
				$contactId = getFieldFromId("contact_id", "orders", "order_id", $orderId);
				$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $contactId);
				if (empty($emailAddress)) {
					$returnArray['error_message'] = "Customer has no email address";
					ajaxResponse($returnArray);
					break;
				}
				$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", "EMAIL_SENT");
				if (empty($taskTypeId)) {
					$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", "TOUCHPOINT");
				}
				if (empty($taskTypeId)) {
					$taskAttributeId = getFieldFromId("task_attribute_id", "task_attributes", "task_attribute_code", "CONTACT_TASK");
					if (empty($taskAttributeId)) {
						$returnArray['error_message'] = "No Touchpoint Task Type";
						ajaxResponse($returnArray);
						break;
					}
					$resultSet = executeQuery("insert into task_types (client_id,task_type_code,description) values (?,'EMAIL_SENT','Email Sent')", $GLOBALS['gClientId']);
					$taskTypeId = $resultSet['insert_id'];
					executeQuery("insert into task_type_attributes (task_type_id,task_attribute_id) values (?,?)", $taskTypeId, $taskAttributeId);
				}
				if (empty($taskTypeId)) {
					$returnArray['error_message'] = "No Touchpoint Task Type";
					ajaxResponse($returnArray);
					break;
				}
				$result = sendEmail(array("subject" => $subject, "body" => $body, "email_address" => $emailAddress));
				if ($result) {
					executeQuery("insert into tasks (client_id,contact_id,description,detailed_description,date_completed,task_type_id,simple_contact_task,creator_user_id,order_id) values " .
						"(?,?,?,?,now(),?,1,?,?)", $GLOBALS['gClientId'], $contactId, $subject, $body, $taskTypeId, $GLOBALS['gUserId'], $orderId);
				} else {
					$returnArray['error_message'] = "Unable to send email";
				}
				ajaxResponse($returnArray);
				break;
			case "send_text_message":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order";
					ajaxResponse($returnArray);
					break;
				}
				$body = $_POST['text_message'];
				if (empty($body)) {
					$returnArray['error_message'] = "Required information is missing";
					ajaxResponse($returnArray);
					break;
				}
				$contactId = getFieldFromId("contact_id", "orders", "order_id", $orderId);
				if ($GLOBALS['gPHPVersion'] < 70200) {
					$returnArray['error_message'] = "Unable to send text, use email";
					ajaxResponse($returnArray);
					break;
				}
				$result = TextMessage::sendMessage($contactId, $body);
				if (!$result) {
					$returnArray['error_message'] = "Unable to send Text Message";
					ajaxResponse($returnArray);
					break;
				}
				$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", "TEXT_SENT");
				if (empty($taskTypeId)) {
					$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", "TOUCHPOINT");
				}
				if (empty($taskTypeId)) {
					$taskAttributeId = getFieldFromId("task_attribute_id", "task_attributes", "task_attribute_code", "CONTACT_TASK");
					if (empty($taskAttributeId)) {
						$returnArray['info_message'] = "Message sent, but unable to create Touchpoint";
						ajaxResponse($returnArray);
						break;
					}
					$resultSet = executeQuery("insert into task_types (client_id,task_type_code,description) values (?,'TEXT_SENT','Text Sent')", $GLOBALS['gClientId']);
					$taskTypeId = $resultSet['insert_id'];
					executeQuery("insert into task_type_attributes (task_type_id,task_attribute_id) values (?,?)", $taskTypeId, $taskAttributeId);
				}
				executeQuery("insert into tasks (client_id,contact_id,description,detailed_description,date_completed,task_type_id,simple_contact_task,creator_user_id,order_id) values " .
					"(?,?,?,?,now(),?,1,?,?)", $GLOBALS['gClientId'], $contactId, "Text Sent", $body, $taskTypeId, $GLOBALS['gUserId'], $orderId);
				ajaxResponse($returnArray);
				break;
			case "reopen_order":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id'], "date_completed is not null");
				if (empty($orderId)) {
					ajaxResponse($returnArray);
					break;
				}
				$ordersTable = new DataTable("orders");
				$ordersTable->setSaveOnlyPresent(true);
				$ordersTable->saveRecord(array("name_values" => array("date_completed" => ""), "primary_id" => $orderId));
				coreSTORE::orderNotification($orderId, "mark_not_completed");
				ajaxResponse($returnArray);
				break;
			case "mark_completed":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id'], "date_completed is null");
				if (empty($orderId)) {
					ajaxResponse($returnArray);
					break;
				}
				Order::markOrderCompleted($orderId);
				ajaxResponse($returnArray);
				break;
			case "get_order_items":
				$orderItems = array();
				$totalQuantity = 0;
				$shippedOrderItemStatusId = getFieldFromId("order_item_status_id", "order_item_statuses", "order_item_status_code", "SHIPPED");
				$orderDataRow = getRowFromId("orders", "order_id", $_GET['order_id']);
				$resultSet = executeQuery("select * from order_items where order_id = ?", $_GET['order_id']);
				$orangeInventoryThreshold = intval(getPageTextChunk("INVENTORY_ORANGE_LIMIT"));
				$redInventoryThreshold = intval(getPageTextChunk("INVENTORY_RED_LIMIT"));
				$orangeInventoryThreshold = $orangeInventoryThreshold ?: 10;
				$redInventoryThreshold = $redInventoryThreshold ?: 5;

				while ($row = getNextRow($resultSet)) {
					$productRow = ProductCatalog::getCachedProductRow($row['product_id']);
					$productTypeCode = getFieldFromId("product_type_code", "product_types", "product_type_id", $productRow['product_type_id']);
					$row['distributor_order_message'] = "";
					$orderSet = executeQuery("select * from distributor_order_item_links where order_item_id = ?", $row['order_item_id']);
					while ($orderRow = getNextRow($orderSet)) {
						if (empty($row['distributor_order_message'])) {
							$row['distributor_order_message'] = "Ordered in distributor order #" . $orderRow['distributor_order_id'];
						} else {
							$row['distributor_order_message'] .= "," . $orderRow['distributor_order_id'];
						}
					}
					$row['product_description'] = (empty($row['description']) ? $productRow['description'] : $row['description']);
					$row['additional_product_description'] = "";

					$giftCardEmailAddress = false;
					$customDataSet = executeQuery("select * from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'ORDER_ITEMS' and client_id = ?)", $GLOBALS['gClientId']);
					while ($customDataRow = getNextRow($customDataSet)) {
						$customFieldData = CustomField::getCustomFieldData($row['order_item_id'], $customDataRow['custom_field_code'], "ORDER_ITEMS");
						if (empty($customFieldData)) {
							continue;
						}
						$row['additional_product_description'] .= "<br><span class='highlighted-text'>" . $customDataRow['description'] . "</span>: " . $customFieldData;
						if ($customDataRow['custom_field_code'] == "RECIPIENT_EMAIL_ADDRESS" && $productTypeCode == "GIFT_CARD") {
							$giftCardEmailAddress = $customFieldData;
						}
					}
					if (!empty($giftCardEmailAddress)) {
						$row['additional_product_description'] .= "<br><button class='resend-gift-card-email' data_order_item_id='" . $row['order_item_id'] . "'>Resend Gift Card Email</button>";
					}
					if ($productTypeCode == "PROMOTION_SALE") {
						$promotionSet = executeQuery("select * from promotions where order_item_id = ?", $row['order_item_id']);
						while ($promotionRow = getNextRow($promotionSet)) {
							$row['additional_product_description'] .= "<br>Promotion Code: <strong>" . $promotionRow['promotion_code'] . "</strong>";
						}
					}

					$addonSet = executeQuery("select * from product_addons join order_item_addons using (product_addon_id) where order_item_id = ? order by sort_order", $row['order_item_id']);
					while ($addonRow = getNextRow($addonSet)) {
						$salePrice = ($addonRow['quantity'] <= 1 ? $addonRow['sale_price'] : $addonRow['sale_price'] * $addonRow['quantity']);
						$row['additional_product_description'] .= "<br>" . $addonRow['description'] . ($addonRow['quantity'] <= 1 ? "" : " (Qty: " . $addonRow['quantity'] . ")") . " - $" . number_format($salePrice, 2, ".", "");
					}
					$row['virtual_product'] = getFieldFromId("virtual_product", "products", "product_id", $row['product_id']);
					$row['serializable'] = $productRow['serializable'];
					$upcCode = getFieldFromId("upc_code", "product_data", "product_id", $row['product_id']);
					if (!empty($upcCode)) {
						$row['additional_product_description'] .= "<br><span class='upc-code copy-to-clipboard'>UPC: <span class='copy-text'>" . $upcCode . "</span></span>";
					}
					$row['additional_product_description'] .= "<br><span class='product-code copy-to-clipboard'>Product Code: <span class='copy-text'>" . getFirstPart($productRow['product_code'], 40) . "</span></span>";
					$row['total_price'] = $row['sale_price'] * $row['quantity'];
					$row['sale_price'] = number_format($row['sale_price'], 2, ".", ",");
					$row['total_price'] = number_format($row['total_price'], 2, ".", ",");
					if ($row['virtual_product']) {
						$row['shipped_quantity'] = $row['quantity'];
						if (!empty($shippedOrderItemStatusId)) {
							$row['order_item_status_id'] = $shippedOrderItemStatusId;
						}
					} else {
						$shippedSet = executeQuery("select sum(quantity) from order_shipment_items where order_item_id = ? and order_shipment_id in (select order_shipment_id from order_shipments where secondary_shipment = 0 and internal_use_only = 0)", $row['order_item_id']);
						while ($shippedRow = getNextRow($shippedSet)) {
							$row['shipped_quantity'] = $shippedRow['sum(quantity)'];
						}
						if (empty($row['shipped_quantity'])) {
							$row['shipped_quantity'] = 0;
						}
						if ($row['shipped_quantity'] >= $row['quantity'] && !empty($shippedOrderItemStatusId)) {
							$row['order_item_status_id'] = $shippedOrderItemStatusId;
						}
					}

					ProductDistributor::setPrimaryDistributorLocation();

					$locationSet = executeQuery("select * from locations where client_id = ? and inactive = 0 and location_id in (select location_id from product_inventories where product_id = ? and quantity > 0) order by location_id", $GLOBALS['gClientId'], $row['product_id']);
					$productInventories = array();
					$productDistributorIds = array();
					while ($locationRow = getNextRow($locationSet)) {
						if (!empty($locationRow['product_distributor_id']) && (in_array($locationRow['product_distributor_id'], $productDistributorIds) || empty($locationRow['primary_location']))) {
							continue;
						}
						$productDistributorIds[] = $locationRow['product_distributor_id'];
						$productInventoryRow = getRowFromId("product_inventories", "product_id", $row['product_id'], "location_id = ?", $locationRow['location_id']);
						if (empty($productInventoryRow)) {
							$productInventoryRow['quantity'] = 0;
						}
						$cost = ProductCatalog::getLocationBaseCost($row['product_id'], $locationRow['location_id'], $productInventoryRow,false);
						if ($productInventoryRow['quantity'] > 0 && (strlen($cost) > 0 || empty($locationRow['product_distributor_id']))) {
							$description = $locationRow['description'];
							$sortOrder = "";
							if (!empty($locationRow['product_distributor_id'])) {
								$description = getReadFieldFromId("description", "product_distributors", "product_distributor_id", $locationRow['product_distributor_id']);
								$sortOrder = getReadFieldFromId("sort_order", "product_distributors", "product_distributor_id", $locationRow['product_distributor_id']);
							}
							$productInventories[] = array("description" => $description, "quantity" => $productInventoryRow['quantity'], "cost" => $cost, "sort_order" => $sortOrder);
						}
					}

					if (!empty($row['deleted'])) {
						$row['order_item_status_id'] = -1;
					} else {
						$totalQuantity += $row['quantity'];
					}
					$orderItems[] = $row;
				}
				$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $orderDataRow['shipping_method_id']);
				$returnArray['order_items'] = $orderItems;
				$returnArray['order_items_quantity'] = $totalQuantity;

				$resultSet = executeQuery("select * from order_items join products using (product_id) where order_id = ?", $_GET['order_id']);
				ob_start();
				while ($row = getNextRow($resultSet)) {
					if (!$row['serializable']) {
						continue;
					}
					$serialNumberControl = new DataColumn("order_item_serial_numbers_" . $row['order_item_id']);
					$serialNumberControl->setControlValue("data_type", "custom_control");
					$serialNumberControl->setControlValue("control_class", "EditableList");
					$serialNumberControl->setControlValue("primary_table", "order_items");
					$serialNumberControl->setControlValue("list_table", "order_item_serial_numbers");
					$serialNumberControl->setControlValue("list_table_controls", array("serial_number" => array("classes" => "serial-number")));
					$customControl = new EditableList($serialNumberControl, $this);
					echo $customControl->getTemplate();

					$returnArray = array_merge($returnArray, $customControl->getRecord($row['order_item_id']));
				}
				$returnArray['jquery_templates'] = ob_get_clean();

				ajaxResponse($returnArray);

				break;
			case "delete_location_shipments":
				$orderShipmentItemRows = array();
				$resultSet = executeQuery("select * from order_shipment_items where order_shipment_id in "
					. "(select order_shipment_id from order_shipments where order_id = ? and location_id = ?)", $_POST['order_id'], $_POST['location_id']);
				while ($row = getNextRow($resultSet)) {
					$orderShipmentItemRows[] = $row;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				foreach ($orderShipmentItemRows as $orderShipmentItemRow) {
					$orderItemRow = getRowFromId("order_items", "order_item_id", $orderShipmentItemRow['order_item_id']);
					$resultSet = executeQuery("delete from order_shipment_items where order_shipment_item_id = ?", $orderShipmentItemRow['order_shipment_item_id']);
					if (!empty($resultSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						ajaxResponse($returnArray);
						break;
					}
					if (empty($resultSet['affected_rows'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = "Distributor Order item not found";
						ajaxResponse($returnArray);
						break;
					}
					# remove inventory adjustments
					if (empty($orderShipmentRow['secondary_shipment'])) {
						$locationId = getFieldFromId("location_id", "order_shipments", "order_shipment_id", $orderShipmentItemRow['order_shipment_id']);
						$productDistributorId = getFieldFromId("product_distributor_id", "locations", "location_id", $locationId);
						$locationSet = executeQuery("select * from locations where inactive = 0 and (location_id = ? or product_distributor_id = ?) order by primary_location desc", $locationId, $productDistributorId);
						while ($locationRow = getNextRow($locationSet)) {
							if (!empty($locationRow['product_distributor_id']) && empty($locationRow['primary_location'])) {
								continue;
							}
							$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $orderItemRow['product_id'], "location_id = ?", $locationRow['location_id']);
							if (!empty($productInventoryId)) {
								$deleteQuantity = 0;
								$remainingQuantity = $orderShipmentItemRow['quantity'];
								$resultSet = executeQuery("select * from product_inventory_log where product_inventory_id = ? and inventory_adjustment_type_id = ? and order_id = ?",
									$productInventoryId, $GLOBALS['gSalesAdjustmentTypeId'], $orderItemRow['order_id']);
								while ($row = getNextRow($resultSet)) {
									if ($row['quantity'] <= $remainingQuantity) {
										executeQuery("delete from product_inventory_log where product_inventory_log_id = ?", $row['product_inventory_log_id']);
										$deleteQuantity += $row['quantity'];
										$remainingQuantity -= $row['quantity'];
									} else {
										if ($remainingQuantity > 0) {
											executeQuery("update product_inventory_log set quantity = quantity - " . $remainingQuantity . " where product_inventory_log_id = ?", $row['product_inventory_log_id']);
											$deleteQuantity += $remainingQuantity;
											$remainingQuantity = 0;
										}
									}
									if ($remainingQuantity <= 0) {
										break;
									}
								}
								executeQuery("update product_inventories set quantity = quantity + " . $deleteQuantity . " where product_inventory_id = ?", $productInventoryId);
							}
						}
						executeQuery("update order_items set order_item_status_id = null where order_item_id = ? and order_item_status_id in " .
							"(select order_item_status_id from order_item_statuses where order_item_status_code = 'SHIPPED')", $orderShipmentItemRow['order_item_id']);
					}
				}
				$resultSet = executeQuery("delete from order_shipments where order_id = ? and location_id = ?", $_POST['order_id'], $_POST['location_id']);
				if (!empty($resultSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				if (empty($resultSet['affected_rows'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Distributor Order(s) not found";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				ajaxResponse($returnArray);
				break;
			case "remove_shipment_item":
				$orderShipmentItemRow = getRowFromId("order_shipment_items", "order_shipment_item_id", $_POST['order_shipment_item_id'],
					"order_shipment_id = ?", $_POST['order_shipment_id']);
				$orderShipmentRow = getRowFromId("order_shipments", "order_shipment_id", $_POST['order_shipment_id']);
				$orderItemRow = getRowFromId("order_items", "order_item_id", $orderShipmentItemRow['order_item_id']);
				if (empty($orderShipmentItemRow)) {
					$returnArray['error_message'] = "Distributor Order item not found";
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from order_shipment_items where order_shipment_id = ? and order_shipment_item_id = ?", $_POST['order_shipment_id'], $_POST['order_shipment_item_id']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				if (empty($resultSet['affected_rows'])) {
					$returnArray['error_message'] = "Distributor Order item not found";
					ajaxResponse($returnArray);
					break;
				}

# remove from product inventory log

				if (empty($orderShipmentRow['secondary_shipment'])) {
					$locationId = getFieldFromId("location_id", "order_shipments", "order_shipment_id", $_POST['order_shipment_id']);
					$productDistributorId = getFieldFromId("product_distributor_id", "locations", "location_id", $locationId);
					$locationSet = executeQuery("select * from locations where inactive = 0 and (location_id = ? or product_distributor_id = ?) order by primary_location desc", $locationId, $productDistributorId);
					while ($locationRow = getNextRow($locationSet)) {
						if (!empty($locationRow['product_distributor_id']) && empty($locationRow['primary_location'])) {
							continue;
						}
						$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $orderItemRow['product_id'], "location_id = ?", $locationRow['location_id']);
						if (!empty($productInventoryId)) {
							$deleteQuantity = 0;
							$remainingQuantity = $orderShipmentItemRow['quantity'];
							$resultSet = executeQuery("select * from product_inventory_log where product_inventory_id = ? and inventory_adjustment_type_id = ? and order_id = ?",
								$productInventoryId, $GLOBALS['gSalesAdjustmentTypeId'], $orderItemRow['order_id']);
							while ($row = getNextRow($resultSet)) {
								if ($row['quantity'] <= $remainingQuantity) {
									executeQuery("delete from product_inventory_log where product_inventory_log_id = ?", $row['product_inventory_log_id']);
									$deleteQuantity += $row['quantity'];
									$remainingQuantity -= $row['quantity'];
								} else {
									if ($remainingQuantity > 0) {
										executeQuery("update product_inventory_log set quantity = quantity - " . $remainingQuantity . " where product_inventory_log_id = ?", $row['product_inventory_log_id']);
										$deleteQuantity += $remainingQuantity;
										$remainingQuantity = 0;
									}
								}
								if ($remainingQuantity <= 0) {
									break;
								}
							}
							executeQuery("update product_inventories set quantity = quantity + " . $deleteQuantity . " where product_inventory_id = ?", $productInventoryId);
						}
					}
					executeQuery("update order_items set order_item_status_id = null where order_item_id = ? and order_item_status_id in " .
						"(select order_item_status_id from order_item_statuses where order_item_status_code = 'SHIPPED')", $orderShipmentItemRow['order_item_id']);
				}
				$orderShipmentItemId = getFieldFromId("order_shipment_item_id", "order_shipment_items", "order_shipment_id", $_POST['order_shipment_id']);
				if (empty($orderShipmentItemId)) {
					$remoteOrderId = getFieldFromId("remote_order_id", "order_shipments", "order_shipment_id", $_POST['order_shipment_id']);
					executeQuery("delete from order_shipments where order_shipment_id = ?", $_POST['order_shipment_id']);
					if (!empty($remoteOrderId)) {
						executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
						executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
					}
				}
				$orderId = getFieldFromId("order_id", "order_shipments", "order_shipment_id", $_POST['order_shipment_id']);
				$orderRow = getRowFromId("orders", "order_id", $orderId);

				$orderItemTotal = 0;
				$resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
				while ($row = getNextRow($resultSet)) {
					$orderItemTotal += $row['sale_price'] * $row['quantity'];
				}

				$itemSet = executeQuery("select *,(select sale_price from order_items where order_item_id = order_shipment_items.order_item_id) as sale_price," .
					"(select product_id from order_items where order_item_id = order_shipment_items.order_item_id) as product_id from order_shipment_items where " .
					"order_shipment_id = ?", $_POST['order_shipment_id']);
				$shipmentItemTotal = 0;
				while ($itemRow = getNextRow($itemSet)) {
					$shipmentItemTotal += $itemRow['sale_price'] * $itemRow['quantity'];
				}
				$shippingCharge = (empty($orderItemTotal) ? 0 : ($shipmentItemTotal / $orderItemTotal) * $orderRow['shipping_charge']);
				$taxCharge = (empty($orderItemTotal) ? 0 : ($shipmentItemTotal / $orderItemTotal) * $orderRow['tax_charge']);
				$handlingCharge = (empty($orderItemTotal) ? 0 : ($shipmentItemTotal / $orderItemTotal) * $orderRow['handling_charge']);
				$returnArray['shipment_amount'] = number_format($shipmentItemTotal + $shippingCharge + $taxCharge + $handlingCharge, 2, ".", ",");

				ajaxResponse($returnArray);

				break;
			case "add_note":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id']);
				if (empty($_POST['content']) || empty($orderId)) {
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("insert into order_notes (order_id,user_id,time_submitted,content,public_access) values (?,?,current_time,?,?)",
					$orderId, $GLOBALS['gUserId'], $_POST['content'], (empty($_POST['public_access']) ? 0 : 1));
				$returnArray['order_note'] = array("user_id" => getUserDisplayName(), "time_submitted" => date("m/d/Y g:ia"), "content" => $_POST['content'], "public_access" => (empty($_POST['public_access']) ? "" : "YES"));
				ajaxResponse($returnArray);
				break;
			case "save_shipment_details":
				$fieldName = $_POST['field_name'];
				$fieldData = $_POST['field_data'];
				$orderShipmentId = $_POST['order_shipment_id'];
				$orderId = $_POST['order_id'];
				$validFields = array("no_notifications", "tracking_identifier", "shipping_carrier_id", "carrier_description", "notes");
				if (!in_array($fieldName, $validFields)) {
					$returnArray['error_message'] = "Invalid Field";
				} else {
					executeQuery("update order_shipments set " . $fieldName . " = ? where order_id = ? and order_shipment_id = ?", $fieldData, $orderId, $orderShipmentId);
				}
				ajaxResponse($returnArray);
				break;
			case "create_shipment":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Invalid Order ID";
					ajaxResponse($returnArray);
					break;
				}
				$lastOrderShipmentId = "";
				$resultSet = executeQuery("select max(order_shipment_id) from order_shipments where order_id = ?", $orderId);
				if ($row = getNextRow($resultSet)) {
					$lastOrderShipmentId = $row['max(order_shipment_id)'];
				}
				if (!empty($lastOrderShipmentId) && $lastOrderShipmentId != $_POST['latest_order_shipment_id']) {
					$returnArray['error_message'] = "Someone created a shipment since you opened the order. Close and reopen the order.";
					ajaxResponse($returnArray);
					break;
				}
				$orderPaymentId = getFieldFromId("order_payment_id", "order_payments", "order_id", $orderId, "not_captured = 1 and deleted = 0");
				if (!empty($orderPaymentId)) {
					$returnArray['error_message'] = "Distributor Orders cannot be created until all payments are captured";
					ajaxResponse($returnArray);
					break;
				}
				$orderRow = getRowFromId("orders", "order_id", $orderId);
				$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
				$sendPickupTracking = empty(getPreference("NO_TRACKING_FOR_PICKUP_ORDERS")) ? $shippingMethodRow['pickup'] : 0;
				$locationId = getFieldFromId("location_id", "locations", "location_id", $_POST['location_id']);
				if (empty($locationId) && !empty($_POST['location_id'])) {
					$returnArray['error_message'] = "Invalid Location";
					ajaxResponse($returnArray);
					break;
				}
				$orderItems = $_POST['order_items'];
				$selectedLocationRow = getRowFromId("locations", "location_id", $locationId);
				$inventoryLocationRow = array();
				if (!empty($selectedLocationRow['product_distributor_id']) && empty($selectedLocationRow['primary_location'])) {
					$inventoryLocationRow = getRowFromId("locations", "product_distributor_id", $selectedLocationRow['product_distributor_id'], "primary_location = 1");
				}
				if (empty($inventoryLocationRow)) {
					$inventoryLocationRow = $selectedLocationRow;
				}
				if (empty($selectedLocationRow['ignore_inventory'])) {
					foreach ($orderItems as $index => $thisOrderItem) {
						$quantity = getFieldFromId("quantity", "order_items", "order_item_id", $thisOrderItem['order_item_id']);
						$resultSet = executeQuery("select sum(quantity) from order_shipment_items where order_item_id = ? and " .
							"order_shipment_id in (select order_shipment_id from order_shipments where order_id = ? and " .
							"secondary_shipment = 0 and internal_use_only = 0)", $thisOrderItem['order_item_id'], $thisOrderItem['order_id']);
						$shippedQuantity = 0;
						if ($row = getNextRow($resultSet)) {
							$shippedQuantity = $row['sum(quantity)'];
						}
						$maximumQuantity = min($thisOrderItem['quantity'], $quantity - $shippedQuantity);
						if ($thisOrderItem['quantity'] > $maximumQuantity) {
							$returnArray['error_message'] = "Invalid Quantities";
							ajaxResponse($returnArray);
							break;
						}
						$thisOrderItem['product_id'] = getFieldFromId("product_id", "order_items", "order_item_id", $thisOrderItem['order_item_id']);
						$inventoryQuantity = getFieldFromId("quantity", "product_inventories", "location_id", $inventoryLocationRow['location_id'], "product_id = ?", $thisOrderItem['product_id']);
						if (empty($inventoryQuantity) || $thisOrderItem['quantity'] > $inventoryQuantity) {
							$returnArray['error_message'] = "This location doesn't have inventory to ship these items";
							ajaxResponse($returnArray);
							break;
						}
					}
				}
				$productDistributorId = getFieldFromId("product_distributor_id", "locations", "location_id", $inventoryLocationRow['location_id']);
				if (empty($productDistributorId)) {
					$orderShipmentsDataTable = new DataTable("order_shipments");
					$orderShipmentId = $orderShipmentsDataTable->saveRecord(array("name_values" => array("order_id" => $orderId, "location_id" => $locationId, "date_shipped" => date("m/d/Y"), "secondary_shipment" => 0)));

					$orderItemCount = 0;
					foreach ($orderItems as $thisOrderItem) {
						$thisOrderItem['product_id'] = getFieldFromId("product_id", "order_items", "order_item_id", $thisOrderItem['order_item_id']);
						$cost = ProductCatalog::getLocationBaseCost($thisOrderItem['product_id'], $locationId);
						executeQuery("insert into order_shipment_items (order_shipment_id,order_item_id,quantity,cost) values (?,?,?,?)",
							$orderShipmentId, $thisOrderItem['order_item_id'], $thisOrderItem['quantity'], $cost);
						$orderItemCount++;

						# add to product inventory log

						if (empty($selectedLocationRow['ignore_inventory'])) {
							if (empty($GLOBALS['gSalesAdjustmentTypeId'])) {
								$GLOBALS['gPrimaryDatabase']->logError("Sales Adjustment type not found");
							} else {
								$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $thisOrderItem['product_id'], "location_id = ?", $locationId);
								if (!empty($productInventoryId)) {
									$productInventoryLogId = getFieldFromId("product_inventory_log_id", "product_inventory_log", "product_inventory_id", $productInventoryId,
										"inventory_adjustment_type_id = ? and order_id = ?", $GLOBALS['gSalesAdjustmentTypeId'], $orderId);
									if (empty($productInventoryLogId)) {
										executeQuery("insert into product_inventory_log (product_inventory_id,inventory_adjustment_type_id,order_id,user_id,log_time,quantity) values " .
											"(?,?,?,?,now(),?)", $productInventoryId, $GLOBALS['gSalesAdjustmentTypeId'], $orderId, $GLOBALS['gUserId'], $thisOrderItem['quantity']);
									} else {
										executeQuery("update product_inventory_log set quantity = quantity + " . $thisOrderItem['quantity'] . " where product_inventory_log_id = ?", $productInventoryLogId);
									}
								}
								executeQuery("update product_inventories set quantity = greatest(0,quantity - " . $thisOrderItem['quantity'] . ") where product_inventory_id = ?", $productInventoryId);
							}
						}
					}
					if ($orderItemCount == 0) {
						executeQuery("delete from remote_order_items where remote_order_id = (select remote_order_id from order_shipments where order_shipment_id = ?)", $orderShipmentId);
						executeQuery("delete from remote_orders where remote_order_id = (select remote_order_id from order_shipments where order_shipment_id = ?)", $orderShipmentId);
						executeQuery("delete from order_shipments where order_shipment_id = ?", $orderShipmentId);
					}
				} else {
					$productDistributor = ProductDistributor::getProductDistributorInstance($locationId);
					$response = array();
					if ($GLOBALS['gDevelopmentServer'] && empty(getPreference('DEVELOPMENT_TEST_DISTRIBUTORS'))) {
						$orderSet = executeQuery("insert into remote_orders (order_id,order_number) values (?,?)",
							$orderRow['order_id'], $orderRow['order_id']);
						$remoteOrderId = $orderSet['insert_id'];
						foreach ($orderItems as $thisOrderItem) {
							executeQuery("insert into remote_order_items (remote_order_id,order_item_id,quantity) values (?,?,?)",
								$remoteOrderId, $thisOrderItem['order_item_id'], $thisOrderItem['quantity']);
						}
						$response = array('dealer' => array("order_type" => "dealer", "remote_order_id" => $remoteOrderId, "order_number" => $orderRow['order_id'], "ship_to" => $GLOBALS['gClientName']));
					} else {
						if ($productDistributor) {
							$response = $productDistributor->placeOrder($orderId, $orderItems);
							if ($response === false || array_key_exists("error_message", $response) || array_key_exists("failed_items", $response)) {
								$returnArray['error_message'] = "Error: " . ($productDistributor->getErrorMessage() == $response['error_message'] ? "" : $productDistributor->getErrorMessage() . ":") .
									$response['error_message'] . ($GLOBALS['gUserRow']['superuser_flag'] ? ":" . jsonEncode($response) : "");
								ajaxResponse($returnArray);
								break;
							}
							if (array_key_exists("info_message", $response)) {
								$returnArray['info_message'] = $response['info_message'];
							}
						} else {
							$returnArray['error_message'] = "Unable to get distributor for this location";
						}
					}
					foreach ($response as $shipmentInformation) {
						if (!empty($shipmentInformation['error_message'])) {
							$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ", ") . $shipmentInformation['error_message'];
						}
						$checkFields = array("order_type", "remote_order_id", "order_number", "ship_to");
						foreach ($checkFields as $thisField) {
							if (!array_key_exists($thisField, $shipmentInformation)) {
								$shipmentInformation[$thisField] = "";
							}
						}
						if (empty($shipmentInformation['ship_to']) && empty($shipmentInformation['remote_order_id'])) {
							continue;
						}
						$orderShipmentsDataTable = new DataTable("order_shipments");
						$orderShipmentId = $orderShipmentsDataTable->saveRecord(array("name_values" => array("order_id" => $orderId, "location_id" => $locationId, "full_name" => $shipmentInformation['ship_to'],
							"remote_order_id" => $shipmentInformation['remote_order_id'], "no_notifications" => ($shipmentInformation['order_type'] == "dealer" && !$sendPickupTracking ? 1 : 0), "internal_use_only" => ($shipmentInformation['order_type'] == "dealer" ? 1 : 0),
							"date_shipped" => date("m/d/Y"), "secondary_shipment" => 0)));
						$orderItemCount = 0;
						$resultSet = executeQuery("select * from remote_order_items where remote_order_id = ?", $shipmentInformation['remote_order_id']);
						while ($thisOrderItem = getNextRow($resultSet)) {
							$thisOrderItem['product_id'] = getFieldFromId("product_id", "order_items", "order_item_id", $thisOrderItem['order_item_id']);
							$cost = ProductCatalog::getLocationBaseCost($thisOrderItem['product_id'], $inventoryLocationRow['location_id']);
							executeQuery("insert into order_shipment_items (order_shipment_id,order_item_id,quantity,cost) values (?,?,?,?)",
								$orderShipmentId, $thisOrderItem['order_item_id'], $thisOrderItem['quantity'], $cost);
							$orderItemCount++;

# add to product inventory log

							if (empty($GLOBALS['gSalesAdjustmentTypeId'])) {
								$GLOBALS['gPrimaryDatabase']->logError("Sales Adjustment type not found");
							} else {
								$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $thisOrderItem['product_id'], "location_id = ?", $inventoryLocationRow['location_id']);
								if (!empty($productInventoryId)) {
									$productInventoryLogId = getFieldFromId("product_inventory_log_id", "product_inventory_log", "product_inventory_id", $productInventoryId,
										"inventory_adjustment_type_id = ? and order_id = ?", $GLOBALS['gSalesAdjustmentTypeId'], $orderId);
									if (empty($productInventoryLogId)) {
										executeQuery("insert into product_inventory_log (product_inventory_id,inventory_adjustment_type_id,order_id,user_id,log_time,quantity) values " .
											"(?,?,?,?,now(),?)", $productInventoryId, $GLOBALS['gSalesAdjustmentTypeId'], $orderId, $GLOBALS['gUserId'], $thisOrderItem['quantity']);
									} else {
										executeQuery("update product_inventory_log set quantity = quantity + " . $thisOrderItem['quantity'] . " where product_inventory_log_id = ?", $productInventoryLogId);
									}
								}
								executeQuery("update product_inventories set quantity = greatest(0,quantity - " . $thisOrderItem['quantity'] . ") where product_inventory_id = ?", $productInventoryId);
							}
						}
						if ($orderItemCount == 0) {
							$remoteOrderId = getFieldFromId("remote_order_id", "order_shipments", "order_shipment_id", $orderShipmentId);
							executeQuery("delete from order_shipments where order_shipment_id = ?", $orderShipmentId);
							executeQuery("delete from remote_order_items where remote_order_id = ?", $remoteOrderId);
							executeQuery("delete from remote_orders where remote_order_id = ?", $remoteOrderId);
						}
					}
				}
				$shippedQuantities = array();
				foreach ($orderItems as $index => $thisOrderItem) {
					$shippedSet = executeQuery("select sum(quantity) from order_shipment_items where order_item_id = ? and order_shipment_id in (select order_shipment_id from order_shipments where secondary_shipment = 0 and internal_use_only = 0)", $thisOrderItem['order_item_id']);
					while ($shippedRow = getNextRow($shippedSet)) {
						$shippedQuantities[$thisOrderItem['order_item_id']] = $shippedRow['sum(quantity)'];
					}
					if (empty($shippedQuantities[$thisOrderItem['order_item_id']])) {
						$shippedQuantities[$thisOrderItem['order_item_id']] = 0;
					}
				}
				$returnArray['shipped_quantities'] = $shippedQuantities;

				ajaxResponse($returnArray);

				break;
			case "get_inventory":
				$locationRow = getRowFromId("locations", "location_id", $_GET['location_id']);
				if (empty($locationRow)) {
					$returnArray['error_message'] = "Invalid Location";
					ajaxResponse($returnArray);
					break;
				}
				$productDistributorId = $locationRow['product_distributor_id'];
				if (!empty($productDistributorId) && empty($locationRow['primary_location'])) {
					$inventoryLocationRow = getRowFromId("locations", "product_distributor_id", $productDistributorId, "primary_location = 1");
					if (empty($inventoryLocationRow)) {
						$inventoryLocationRow = $locationRow;
					}
				} else {
					$inventoryLocationRow = $locationRow;
				}
				$resultSet = executeQuery("select * from order_items where order_id = ?", $_GET['order_id']);
				$productInventories = array();
				while ($row = getNextRow($resultSet)) {
					$virtualProduct = getFieldFromId("virtual_product", "products", "product_id", $row['product_id']);
					if ($virtualProduct) {
						$productInventories[$row['order_item_id']] = array("unavailable" => true, "quantity" => 0);
						continue;
					}
					$productInventoryRow = getRowFromId("product_inventories", "product_id", $row['product_id'], "location_id = ?", $inventoryLocationRow['location_id']);
					if (empty($productInventoryRow['quantity'])) {
						$productInventoryRow['quantity'] = 0;
					}
					$cost = ProductCatalog::getLocationBaseCost($row['product_id'], $inventoryLocationRow['location_id'], $productInventoryRow,false);
					$productInventories[$row['order_item_id']] = array("quantity" => $productInventoryRow['quantity'], "cost" => (empty($cost) ? "" : number_format($cost, 2, ".", ",")));
				}
				$returnArray['product_inventories'] = $productInventories;
				$returnArray['product_distributor_id'] = $productDistributorId;
				ajaxResponse($returnArray);
				break;
			case "save_order_status":
				Order::updateOrderStatus($_GET['order_id'], $_GET['order_status_id']);
				ajaxResponse($returnArray);
				break;
			case "save_order_item_status":
				$orderItemDataTable = new DataTable("order_items");
				$orderItemDataTable->setSaveOnlyPresent(true);
				$orderItemId = getFieldFromId("order_item_id", "order_items", "order_item_id", $_GET['order_item_id'], "order_id = ?", $_GET['order_id']);
				if (empty($orderItemId)) {
					$returnArray['error_message'] = "Invalid Order Item";
					ajaxResponse($returnArray);
					break;
				}
				if ($_GET['order_item_status_id'] == -1) {
					$orderItemDataTable->saveRecord(array("name_values" => array("deleted" => 1), "primary_id" => $orderItemId));
				} else {
					$orderItemDataTable->saveRecord(array("name_values" => array("deleted" => 0, "order_item_status_id" => $_GET['order_item_status_id']), "primary_id" => $orderItemId));
				}
				$orderItemStatusCode = getFieldFromId("order_item_status_code", "order_item_statuses", "order_item_status_id", $_GET['order_item_status_id']);
				switch ($orderItemStatusCode) {
					case "BACKORDER":
						$orderItemRow = getRowFromId("order_items", "order_item_id", $orderItemId);
						$productId = $orderItemRow['product_id'];
						$productCatalog = new ProductCatalog();
						$totalInventory = $productCatalog->getInventoryCounts(true, $productId, false, array("ignore_backorder" => true));
                        $emailAddress = getPreference("BACKORDERED_ITEM_AVAILABLE_NOTIFICATION");
                        if (empty($emailAddress)) {
                            $emailAddress = $GLOBALS['gUserRow']['email_address'];
                        }
						if ($totalInventory <= 0) {
							$productInventoryNoticationId = getFieldFromId("product_inventory_notification_id", "product_inventory_notifications", "product_id", $productId);
							if (empty($productInventoryNoticationId)) {
								executeQuery("insert into product_inventory_notifications (product_id,user_id,email_address,comparator,quantity,order_quantity,place_order,use_lowest_price,allow_multiple) values " .
									"(?,?,?,?,?, ?,?,?,?)", $productId, $GLOBALS['gUserId'], $emailAddress, ">", 0, $orderItemRow['quantity'], 1, 1, 1);
							}
						}
						break;
				}
				ajaxResponse($returnArray);
				break;
			case "get_statistics":
				$hideHeader = userHasAttribute("HIDE_ORDERS_REVENUE", $GLOBALS['gUserId'], false);
				if ($hideHeader) {
					ajaxResponse($returnArray);
					break;
				}
				$dateFrom = date("Y-m-d", strtotime($_GET['start_date']));
				$dateTo = date("Y-m-d", strtotime($_GET['end_date']));

				//get FFLs associated with logged in user
				$resultSet = executeQuery("select federal_firearms_licensee_id from user_ffls where user_id = " . $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$fflArray[] = $row['federal_firearms_licensee_id'];
				}

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
					"date(order_time) between ? and ?" . ($GLOBALS['gUserRow']['administrator_flag'] ? "" : (empty($fflArray) ? "" : " and federal_firearms_licensee_id in (" . implode(",", $fflArray) . ")")), $GLOBALS['gClientId'], $dateFrom, $dateTo);
				if ($row = getNextRow($resultSet)) {
					$returnArray['order_count_total'] = $row['count(*)'];
				}

				//get total revenue
				$totalRevenue = 0;
				$resultSet = executeQuery("select shipping_charge,tax_charge,handling_charge,(select sum(order_items.sale_price * order_items.quantity) from order_items where " .
					"deleted = 0 and order_id = orders.order_id) as item_total from orders where " .
					"orders.client_id = ? and date(order_time) between ? and ? and orders.deleted = 0" . ($GLOBALS['gUserRow']['administrator_flag'] ? "" : (empty($fflArray) ? "" : " and federal_firearms_licensee_id in (" . implode(",", $fflArray) . ")")), $GLOBALS['gClientId'], $dateFrom, $dateTo);
				while ($row = getNextRow($resultSet)) {
					$totalRevenue += $row['shipping_charge'] + $row['tax_charge'] + $row['handling_charge'] + $row['item_total'];
				}

				$returnArray['revenue_total'] = "$" . number_format($totalRevenue, 2, ".", ",");
				ajaxResponse($returnArray);
				break;
		}
	}

	function filterTextProcessing($filterText) {
		if (!empty($filterText)) {
			if (is_numeric($filterText) && strlen($filterText) >= 8) {
				$this->iDataSource->addFilterWhere("order_id = " . makeNumberParameter($filterText) . " or order_number = " . makeNumberParameter($filterText) . " or order_id in (select order_id from order_items where product_id in " .
					"(select product_id from product_data where upc_code = '" . ProductCatalog::makeValidUPC($filterText) . "'" .
					") or product_id in (select product_id from product_data where isbn = '" . ProductCatalog::makeValidISBN($filterText) . "'" .
					") or product_id in (select product_id from product_data where isbn_13 = '" . ProductCatalog::makeValidISBN13($filterText) . "')" .
					") or order_id in (select order_id from order_items where order_item_id in (select order_item_id from order_item_serial_numbers where serial_number = '" . $GLOBALS['gPrimaryDatabase']->makeNumberParameter($filterText) . "'))");
			} else {
				$parts = explode(" ", $filterText);
				$whereStatement = "order_id = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText);
				if (count($parts) == 2) {
					$whereStatement .= (empty($whereStatement) ? "" : " or ") . "(contact_id in (select contact_id from contacts where first_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[0] . "%") .
						" and last_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[1] . "%") . "))";
				}
				foreach ($this->iSearchContactFields as $fieldName) {
					$whereStatement .= (empty($whereStatement) ? "" : " or ") . "contact_id in (select contact_id from contacts where " . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%") . ")";
				}
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . "contact_id in (select contact_id from contact_identifiers where identifier_value = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . ")";
				foreach ($this->iSearchFields as $fieldName) {
					$whereStatement .= (empty($whereStatement) ? "" : " or ") . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%");
				}
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . "federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensees where license_number = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . ")";
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . "order_id in (select order_id from order_shipments where tracking_identifier = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . ")";
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . " order_id in (select order_id from order_items" .
					" where description like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%") .
					" or product_id in (select product_id from product_data where upc_code = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . " or manufacturer_sku = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) .
					") or product_id in (select product_id from products where product_code = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) .
					") or order_item_id in (select order_item_id from order_item_serial_numbers where serial_number like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%") . "))";
				$this->iDataSource->addFilterWhere($whereStatement);
			}
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".resend-gift-card-email", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=resend_gift_card_email&order_item_id=" + $("#primary_id").val(), $("#_send_text_message_form").serialize(), function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        $("#_send_text_message_dialog").dialog('close');
                    }
                });
            });
            $(document).on("click change", "#show_order_touchpoints", function () {
                $(".touchpoint").addClass("hidden");
                $(".touchpoint.order-" + $("#primary_id").val()).removeClass("hidden");
                if (!$(this).prop("checked")) {
                    $(".touchpoint").removeClass("hidden");
                }
            });
            $(document).on("click", "#send_text_message", function () {
                $("#_send_text_message_form").clearForm();
                $('#_send_text_message_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 800,
                    title: 'Send Text Message to Customer',
                    open: function () {
                        addCKEditor();
                    },
                    buttons: {
                        Send: function (event) {
                            if ($("#_send_text_message_form").validationEngine("validate")) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_text_message&order_id=" + $("#primary_id").val(), $("#_send_text_message_form").serialize(), function (returnArray) {
                                    if (!("error_message" in returnArray)) {
                                        $("#_send_text_message_dialog").dialog('close');
                                    }
                                });
                            }
                        },
                        Cancel: function (event) {
                            $("#_send_text_message_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $("#_confirm_delete_dialog").find(".dialog-text").html("Are you sure you want to delete this order?");
            $(document).on("click", ".order-tag", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_order_tag&order_id=" + $("#primary_id").val() + "&order_tag_id=" + $(this).data("order_tag_id") + "&checked=" + ($(this).prop("checked") ? "true" : ""));
            });
            $(document).on("click", ".distributor-order-product", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=distributor_order_product", {
                    product_id: $(this).data("product_id"),
                    quantity: $(this).data("quantity"),
                    order_id: $("#primary_id").val(),
                    order_item_id: $(this).closest(".order-item").data("order_item_id"),
                    location_id: $("#location_id").val()
                });
            });
            if ($("#location_id").find("option[value!='']").length === 1) {
                $("#location_id").find("option[value='']").remove();
            }
            $(document).on("click", "#resend_receipt", function () {
                $('#_confirm_receipt_email_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Resend Receipt Email',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=resend_receipt&order_id=" + $("#primary_id").val());
                            $("#_confirm_receipt_email_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_receipt_email_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", "#letter_package", function () {
                if ($(this).prop("checked")) {
                    $(".letter-package").addClass("hidden");
                    $("#weight").val("3");
                    $("#weight_unit").val("ounce");
                    $("#signature_required").prop("checked", false);
                    $("#adult_signature_required").prop("checked", false);
                    $("#no_email").prop("checked", true);
                } else {
                    $(".letter-package").removeClass("hidden");
                    $("#no_email").prop("checked", false);
                }
            });
            $(document).on("tap click", "#_pickup_ready_button", function () {
                const primaryId = $("#primary_id").val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mark_ready_for_pickup&order_id=" + primaryId, function (returnArray) {
                    if ("order_status_id" in returnArray) {
                        $("#order_status_id").val(returnArray['order_status_id']);
                    }
                });
                return false;
            });
            $(document).on("tap click", "#_picked_up_button", function () {
                const primaryId = $("#primary_id").val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=order_picked_up&order_id=" + primaryId, function (returnArray) {
                    $("#_list_button").trigger("click");
                });
                return false;
            });
            $(document).on("tap click", "#_refunds_button", function () {
                const primaryId = $("#primary_id").val();
                document.location = "/refunddashboard.php?url_page=show&primary_id=" + primaryId;
                return false;
            });
            $(document).on("click", "#print_receipt", function () {
                const orderId = $("#primary_id").val();
                window.open("/admin-order-receipt?order_id=" + orderId + "&printable=true");
                return false;
            });
            $(document).on("click", ".print-packing-slip", function () {
                const orderShipmentId = $(this).closest("tr").data("order_shipment_id");
                window.open("/packing-slip?order_shipment_id=" + orderShipmentId);
                return false;
            });

            $(document).on("click", "#send_email", function () {
                $("#_send_email_form").clearForm();
                $('#_send_email_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 800,
                    title: 'Send Email to Customer',
                    open: function () {
                        addCKEditor();
                    },
                    buttons: {
                        Send: function (event) {
                            for (instance in CKEDITOR.instances) {
                                CKEDITOR.instances[instance].updateElement();
                            }
                            if ($("#_send_email_form").validationEngine("validate")) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_email&order_id=" + $("#primary_id").val(), $("#_send_email_form").serialize(), function (returnArray) {
                                    if (!("error_message" in returnArray)) {
                                        $("#_send_email_dialog").dialog('close');
                                    }
                                });
                            }
                        },
                        Cancel: function (event) {
                            $("#_send_email_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("blur", ".serial-number", function () {
                const rowNumber = $(this).closest("tr").data("row_number");
                const primaryId = $(this).closest("tr").find(".editable-list-primary-id").val();
                const primaryIdElement = $(this).closest("tr").find(".editable-list-primary-id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_serial_number", { order_id: $("#primary_id").val(), order_item_id: $(this).closest(".order-item").data("order_item_id"), serial_number: $(this).val(), primary_id: primaryId }, function (returnArray) {
                    if ("primary_id" in returnArray) {
                        primaryIdElement.val(returnArray['primary_id']);
                    }
                });
            });
            $(document).on("click", "#reopen_order", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=reopen_order", { order_id: $("#primary_id").val() }, function (returnArray) {
                    document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&primary_id=" + $("#primary_id").val();
                });
                return false;
            });
            $(document).on("click", "#mark_completed", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mark_completed", { order_id: $("#primary_id").val() }, function (returnArray) {
                    document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                });
                return false;
            });
            $(document).on("click", ".print-1508", function () {
                const orderId = $("#primary_id").val();
                window.open("/print-1508?order_id=" + orderId + "&printable=true");
            });
            $(document).on("click", ".track-package", function () {
                if (!empty($(this).data("link_url"))) {
                    window.open($(this).data("link_url"));
                }
            });
            $(document).on("click", ".send-tracking-email", function () {
                const orderShipmentId = $(this).closest(".order-shipment").data("order_shipment_id");
                $('#_confirm_email_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Send Tracking Email',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_tracking_email", { order_shipment_id: orderShipmentId });
                            $("#_confirm_email_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_email_dialog").dialog('close');
                        }
                    }
                });
            });
            $(document).on("click", "#add_note", function () {
                if (!empty($("#content").val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=add_note", { content: $("#content").val(), order_id: $("#primary_id").val(), public_access: $("#public_access").prop("checked") ? 1 : 0 }, function (returnArray) {
                        if ("order_note" in returnArray) {
                            $("#order_notes").find(".no-order-notes").remove();
                            let orderNoteBlock = $("#_order_note_template").html();
                            for (const j in returnArray['order_note']) {
                                const re = new RegExp("%" + j + "%", 'g');
                                orderNoteBlock = orderNoteBlock.replace(re, returnArray['order_note'][j]);
                            }
                            $("#order_notes").append(orderNoteBlock);
                            $("#content").val("");
                            $("#public_access").prop("checked", false);
                        }
                    });
                }
                return false;
            });
            $(document).on("change", ".editable-shipping-field", function () {
                const orderShipmentId = $(this).closest(".order-shipment").data("order_shipment_id");
                const postVariables = {};
                postVariables['order_id'] = $("#primary_id").val();
                postVariables['order_shipment_id'] = orderShipmentId;
                postVariables['field_name'] = $(this).data("field_name");
                postVariables['field_data'] = $(this).val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_shipment_details", postVariables);
            });
            $(document).on("click", ".no-notification", function () {
                const orderShipmentId = $(this).closest(".order-shipment").data("order_shipment_id");
                const postVariables = {};
                postVariables['order_id'] = $("#primary_id").val();
                postVariables['order_shipment_id'] = orderShipmentId;
                postVariables['field_name'] = "no_notifications";
                postVariables['field_data'] = ($(this).prop("checked") ? "1" : "0");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_shipment_details", postVariables);
            });
            $(document).on("click", ".capture-payment", function () {
                const $orderPaymentRow = $(this).closest("tr");
                const orderPaymentId = $orderPaymentRow.data("order_payment_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=capture_payment&order_payment_id=" + orderPaymentId + "&order_id=" + $("#primary_id").val(), function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        $orderPaymentRow.find(".not-captured").html("Done");
                        $orderPaymentRow.find(".transaction-identifier").html(returnArray['transaction_identifier']);
                        $("#_capture_message").html(returnArray['_capture_message']);
                        if (empty(returnArray['_capture_message'])) {
                            enableButtons($("#create_shipment"));
                        } else {
                            disableButtons($("#create_shipment"));
                        }
                    }
                });
                return false;
            });
            $(document).on("click", "#create_shipment", function () {
                let latestOrderShipmentId = "";
                $("#order_shipments tr.order-shipment").each(function () {
                    const orderShipmentId = $(this).data("order_shipment_id");
                    if (empty(orderShipmentId) || isNaN(orderShipmentId)) {
                        return true;
                    }
                    if (empty(latestOrderShipmentId) || parseInt(orderShipmentId) > parseInt(latestOrderShipmentId)) {
                        latestOrderShipmentId = orderShipmentId;
                    }
                });
                if ($(".capture-payment").length > 0) {
                    displayErrorMessage("All payments must be captured first.");
                    return false;
                }
                if (empty($("#location_id").val())) {
                    displayErrorMessage("Select a shipping location");
                    return false;
                }
                let totalQuantity = 0;
                const postVariables = {};
                postVariables['order_id'] = $("#primary_id").val();
                postVariables['location_id'] = $("#location_id").val();
                postVariables['latest_order_shipment_id'] = latestOrderShipmentId;
                const orderItems = {};
                $("#order_items").find(".shipment-quantity").each(function () {
                    totalQuantity += $(this).val();
                    if (!empty($(this).val())) {
                        const orderItemId = $(this).closest("tr").data("order_item_id");
                        orderItems[orderItemId] = { order_item_id: orderItemId, quantity: $(this).val() };
                    }
                });
                postVariables['order_items'] = orderItems;
                if (totalQuantity <= 0) {
                    displayErrorMessage("No item quantities set");
                    return false;
                }
                const productDistributorId = $("#location_id").data("product_distributor_id");
                $('#_confirm_shipment_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Create Distributor Order',
                    open: function (event, ui) {
                        if (empty(productDistributorId)) {
                            $(".confirm-create-shipment").trigger("click");
                        }
                    },
                    buttons:
                        [
                            {
                                text: "Yes",
                                click: function () {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_shipment", postVariables, function (returnArray) {
                                        if ("shipped_quantities" in returnArray) {
                                            for (const i in returnArray['shipped_quantities']) {
                                                $("#shipped_quantity_" + i).val(returnArray['shipped_quantities'][i]);
                                                if (parseInt($("#quantity_" + i).val()) <= parseInt($("#shipped_quantity_" + i).val()) && !empty(shippedOrderItemStatusId)) {
                                                    $("#order_item_status_id_" + i).val(shippedOrderItemStatusId).trigger("change");
                                                }
                                            }
                                        }
                                        $("#location_id").trigger("change");
                                        getOrderShipments();
                                    });
                                    $("#_confirm_shipment_dialog").dialog('close');
                                },
                                'class': 'confirm-create-shipment'
                            },
                            {
                                text: "Cancel",
                                click: function () {
                                    $("#_confirm_shipment_dialog").dialog('close');
                                }
                            }
                        ]
                });
                return false;
            });
            $(document).on("click", ".ship-all-items", function () {
                $("#order_items").find(".ship-all").not(".hidden").trigger("click");
            });
            $(document).on("click", ".ship-all", function () {
                let orderQuantity = $(this).closest("tr").find(".shipment-quantity").data("order_quantity");
                const orderItemId = $(this).closest("tr").data("order_item_id");
                let shippedQuantity = 0;
                shippedQuantity = $("#shipped_quantity_" + orderItemId).val();
                orderQuantity -= shippedQuantity;
                $(this).closest("td").find(".shipment-quantity").val(orderQuantity).trigger("change");
            });
            $(document).on("change", ".shipment-quantity", function () {
                const quantity = $(this).val();
                let maximumQuantity = $(this).data("maximum_quantity");
                let orderQuantity = $(this).data("order_quantity");
                const orderItemId = $(this).closest("tr").data("order_item_id");
                let shippedQuantity = 0;
                shippedQuantity = $("#shipped_quantity_" + orderItemId).val();
                orderQuantity -= shippedQuantity;
                if (isNaN(quantity) || quantity < 0 || quantity > orderQuantity) {
                    $(this).val("");
                    displayErrorMessage("Invalid quantity");
                    return;
                }
                if (quantity > maximumQuantity) {
                    displayErrorMessage("Maximum quantity is " + maximumQuantity);
                    $(this).val("");
                }
            });
            $("#location_id").change(function () {
                $(this).data("product_distributor_id", "");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_inventory&order_id=" + $("#primary_id").val() + "&location_id=" + $(this).val(), function (returnArray) {
                        $(".shipment-quantity").addClass("hidden").val("0");
                        $(".ship-all").addClass("hidden");
                        if ("product_inventories" in returnArray) {
                            for (const i in returnArray['product_inventories']) {
                                const quantity = $("#order_item_" + i).find("#quantity_" + i).val();
                                const shippedQuantity = $("#order_item_" + i).find("#shipped_quantity_" + i).val();
                                if (!("unavailable" in returnArray['product_inventories'][i])) {
                                    $("#shipment_quantity_" + i).removeClass("hidden").data("maximum_quantity", Math.min(quantity, returnArray['product_inventories'][i]['quantity'])).data("order_quantity", quantity);
                                    $("#ship_all_" + i).removeClass("hidden");
                                }
                            }
                        }
                        $("#location_id").data("product_distributor_id", returnArray['product_distributor_id']);
                    });
                }
                return false;
            });
            $("#_list_button").text("Return To List");
			<?php
			$hideHeader = userHasAttribute("HIDE_ORDERS_REVENUE", $GLOBALS['gUserId'], false);
			if (!$hideHeader) {
			?>
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
			<?php } ?>
            $("#order_status_id").change(function () {
                $("#order_status_wrapper").attr("class", "");
                $("#order_status_display").html("");
                $("#order_information_block").attr("class", "");
                if (!empty($(this).val())) {
                    const displayText = $(this).find("option:selected").text();
                    $("#order_status_wrapper").addClass("order-status-" + $(this).val());
                    $("#order_status_display").html(displayText);
                    $("#order_information_block").addClass("order-status-" + $(this).val() + "-light");
                }
                if (!empty($("#deleted").val())) {
                    $("#order_status_display").html("Deleted");
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_order_status&order_id=" + $("#primary_id").val() + "&order_status_id=" + $(this).val());
            });
            $(document).on("change", ".order-item-status-id", function () {
                $(this).closest("tr").attr("class", "").addClass("order-item");
                if (!empty($(this).val())) {
                    $(this).closest("tr").addClass("order-item-status-" + $(this).val());
                }
                const orderItemId = $(this).closest("tr").data("order_item_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_order_item_status&order_id=" + $("#primary_id").val() + "&order_item_id=" + orderItemId + "&order_item_status_id=" + $(this).val());
            });
            $(document).on("click", ".postage-rate", function () {
                $("#rate_shipment_id").val($(this).closest("p").find(".rate-shipment-id").val());
            });
        </script>
		<?php
	}

	function javascript() {
		$shippedOrderItemStatusId = getFieldFromId("order_item_status_id", "order_item_statuses", "order_item_status_code", "SHIPPED");
		?>
        <script>
            const shippedOrderItemStatusId = "<?= $shippedOrderItemStatusId ?>";
            let shippingAddresses = [];

            function customActions(actionName) {
                if (actionName === "export_customers") {
                    document.location = "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=export_customers";
                }
            }
            function afterGetRecord(returnArray) {
                if (returnArray['can_send_text']) {
                    $("#_send_text_message_wrapper").removeClass("hidden");
                } else {
                    $("#_send_text_message_wrapper").addClass("hidden");
                }
                if (empty(returnArray['pickup']['data_value'])) {
                    $("#_pickup_ready_button").addClass("hidden");
                    $("#_picked_up_button").addClass("hidden");
                    $(".create-shipping-label").removeClass("hidden");
                } else {
                    $("#_pickup_ready_button").removeClass("hidden");
                    $("#_picked_up_button").removeClass("hidden");
                    $(".create-shipping-label").addClass("hidden");
                }
                if ($("#location_id").find("option").length === 1) {
                    $("#location_id").val($("#location_id").find("option").attr("value"));
                }
                if ($("#primary_id").length > 0) {
                    document.title = "Order Number " + $("#primary_id").val();
                }
                if ($("#deleted").val() === "1") {
                    $("#_delete_button").find(".button-text").html("Undelete");
                } else {
                    $("#_delete_button").find(".button-text").html("Delete");
                }
                if (empty(returnArray['date_completed']['data_value'])) {
                    $("#order_status_id").trigger("change");
                }
                getOrderItems();
                getOrderShipments();

                $("#order_shipments").find(".order-shipment").remove();
                $("#order_shipments").find(".order-shipment-item").remove();
                for (let i in returnArray['order_shipments']) {
                    let orderShipmentBlock = $("#_order_shipment_template").html();
                    for (let j in returnArray['order_shipments'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        orderShipmentBlock = orderShipmentBlock.replace(re, returnArray['order_shipments'][i][j]);
                    }
                    $("#order_shipments").append(orderShipmentBlock);
                    if (returnArray['order_shipments'][i]['no_notifications'] === "1") {
                        $("#no_notifications_" + returnArray['order_shipments'][i]['order_shipment_id']).prop("checked", true);
                    }
                    if (!empty(returnArray['order_shipments'][i]['product_distributor_id'])) {
                        $("#create_shipping_label_" + returnArray['order_shipments'][i]['order_shipment_id']).remove();
                    }
                    if ("order_shipment_items" in returnArray['order_shipments'][i]) {
                        for (let j in returnArray['order_shipments'][i]['order_shipment_items']) {
                            let orderShipmentItemBlock = $("#_order_shipment_item_template").html();
                            for (let k in returnArray['order_shipments'][i]['order_shipment_items'][j]) {
                                const re = new RegExp("%" + k + "%", 'g');
                                orderShipmentItemBlock = orderShipmentItemBlock.replace(re, returnArray['order_shipments'][i]['order_shipment_items'][j][k]);
                            }
                            $("#order_shipments").append(orderShipmentItemBlock);
                        }
                    }
                }
                if ($("#order_shipments").find(".order-shipment").length === 0) {
                    $("#order_shipments").append("<tr class='order-shipment no-order-shipments'><td colspan='100'>No Distributor Orders yet</td></tr>");
                }

                $("#order_payments").find(".order-payment").remove();
                for (let i in returnArray['order_payments']) {
                    let orderPaymentBlock = $("#_order_payment_template").html();
                    for (let j in returnArray['order_payments'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        orderPaymentBlock = orderPaymentBlock.replace(re, returnArray['order_payments'][i][j]);
                    }
                    let additionalClasses = "";
                    if (!empty(returnArray['order_payments'][i]['deleted'])) {
                        additionalClasses = "deleted-payment hidden";
                    }
                    orderPaymentBlock = orderPaymentBlock.replace(new RegExp("%additional_classes%", 'g'), additionalClasses);
                    $("#order_payments").append(orderPaymentBlock);
                }
                $(".order-payment-invoice-id-").remove();
                if ($("#order_payments").find(".order-payment").length === 0) {
                    $("#order_payments").append("<tr class='order-payment'><td colspan='100'>No Payments yet</td></tr>");
                }
                if (empty(returnArray['not_captured'])) {
                    enableButtons($("#create_shipment"));
                } else {
                    disableButtons($("#create_shipment"));
                }

                $("#order_notes").find(".order-note").remove();
                for (let i in returnArray['order_notes']) {
                    let orderNoteBlock = $("#_order_note_template").html();
                    for (let j in returnArray['order_notes'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        orderNoteBlock = orderNoteBlock.replace(re, returnArray['order_notes'][i][j]);
                    }
                    $("#order_notes").append(orderNoteBlock);
                }
                if ($("#order_notes").find(".order-note").length === 0) {
                    $("#order_notes").append("<tr class='order-note no-order-notes'><td colspan='100'>No Notes yet</td></tr>");
                }

                $("#touchpoints").find(".touchpoint").remove();
                for (let i in returnArray['touchpoints']) {
                    let touchpointBlock = $("#_touchpoint_template").html();
                    for (let j in returnArray['touchpoints'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        touchpointBlock = touchpointBlock.replace(re, returnArray['touchpoints'][i][j]);
                    }
                    $("#touchpoints").append(touchpointBlock);
                }
                if ($("#touchpoints").find(".touchpoint").length === 0) {
                    $("#touchpoints").append("<tr class='touchpoint no-touchpoints'><td colspan='100'>No Touchpoints</td></tr>");
                }
                $("#show_order_touchpoints").prop("checked", ($(".touchpoint.order-" + $("#primary_id").val()).length > 0)).trigger("change");

                if (!empty(returnArray['date_completed']['data_value']) || !empty(returnArray['deleted']['data_value'])) {
                    $("#_maintenance_form").find("input[type=text]").prop("readonly", true);
                    $("#_maintenance_form").find("select").prop("disabled", true);
                    $("#_maintenance_form").find("textarea").not(".keep-visible").prop("readonly", true);
                    $("#_maintenance_form").find("button").not(".keep-visible").addClass("hidden");
                }
            }

            function afterEditableListRemove(listIdentifier) {
                if (listIdentifier.indexOf("order_item_serial_numbers_") === 0) {
                    const orderItemId = listIdentifier.substring("order_item_serial_numbers_".length);
                    const deleteId = $("#_" + listIdentifier + "_delete_ids").val();
                    $("#_" + listIdentifier + "_delete_ids").val("");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_order_item_serial_number&order_item_id=" + orderItemId + "&delete_id=" + deleteId);
                }
            }

            function getOrderItems() {
                $("#order_items").find("tr.order-item").remove();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_order_items&order_id=" + $("#primary_id").val(), function (returnArray) {
                    if ("jquery_templates" in returnArray) {
                        $("#jquery_templates").html(returnArray['jquery_templates']);
                    }
                    if ("order_items" in returnArray) {
                        for (const i in returnArray['order_items']) {
                            let orderItemBlock = $("#_order_item_template").html();
                            for (const j in returnArray['order_items'][i]) {
                                const re = new RegExp("%" + j + "%", 'g');
                                orderItemBlock = orderItemBlock.replace(re, returnArray['order_items'][i][j]);
                            }
                            $("#order_items").append(orderItemBlock);
                            if (!empty(returnArray['order_items'][i]['serializable'])) {
                                $("#order_item_" + returnArray['order_items'][i]['order_item_id']).find(".serial-number-wrapper").removeClass("hidden");
                            }
                            $("#order_item_status_id_" + returnArray['order_items'][i]['order_item_id']).val(returnArray['order_items'][i]['order_item_status_id']);
                            if (!empty(returnArray['order_items'][i]['virtual_product'])) {
                                $("#order_item_" + returnArray['order_items'][i]['order_item_id']).addClass("virtual-product");
                                $("#order_item_status_id_" + returnArray['order_items'][i]['order_item_id']).addClass("hidden");
                            }
                        }
                        $("#order_items").find(".order-item-status-id").each(function () {
                            $(this).closest("tr").attr("class", "").addClass("order-item");
                            if (!empty($(this).val())) {
                                $(this).closest("tr").addClass("order-item-status-" + $(this).val());
                            }
                        });
                        if (!empty($("#deleted").val()) || !empty($("#date_completed").val())) {
                            $("#order_items").find(".order-item-status-id").prop("disabled", true);
                        }
                        $("#location_id").trigger("change");
                        for (const i in returnArray) {
                            if (i.indexOf("order_item_serial_numbers_") === 0) {
                                for (const j in returnArray[i]) {
                                    addEditableListRow(i, returnArray[i][j]);
                                }
                            }
                        }
                        if ("order_items_quantity" in returnArray) {
                            $("#order_items_quantity").html("Ordered items total quantity: " + returnArray['order_items_quantity'])
                        }
                    }
                });
            }

            function getOrderShipments() {
                $("#order_shipments").find(".order-shipment").remove();
                $("#order_shipments").find(".order-shipment-item").remove();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_order_shipments&order_id=" + $("#primary_id").val(), function (returnArray) {
                    if ("order_shipments" in returnArray) {
                        $("#order_shipments").find(".order-shipment").remove();
                        $("#order_shipments").find(".order-shipment-item").remove();
                        for (let i in returnArray['order_shipments']) {
                            let orderShipmentBlock = $("#_order_shipment_template").html();
                            for (let j in returnArray['order_shipments'][i]) {
                                const re = new RegExp("%" + j + "%", 'g');
                                orderShipmentBlock = orderShipmentBlock.replace(re, returnArray['order_shipments'][i][j]);
                            }
                            $("#order_shipments").append(orderShipmentBlock);
                            if (!empty(returnArray['order_shipments'][i]['no_notifications'])) {
                                $("#no_notifications_" + returnArray['order_shipments'][i]['order_shipment_id']).prop("checked", true);
                            }
                            if (!empty(returnArray['order_shipments'][i]['product_distributor_id'])) {
                                $("#create_shipping_label_" + returnArray['order_shipments'][i]['order_shipment_id']).remove();
                            }
                            if ("order_shipment_items" in returnArray['order_shipments'][i]) {
                                for (const j in returnArray['order_shipments'][i]['order_shipment_items']) {
                                    let orderShipmentItemBlock = $("#_order_shipment_item_template").html();
                                    for (const k in returnArray['order_shipments'][i]['order_shipment_items'][j]) {
                                        const re = new RegExp("%" + k + "%", 'g');
                                        orderShipmentItemBlock = orderShipmentItemBlock.replace(re, returnArray['order_shipments'][i]['order_shipment_items'][j][k]);
                                    }
                                    $("#order_shipments").append(orderShipmentItemBlock);
                                }
                                $("#shipping_carrier_id_" + returnArray['order_shipments'][i]['order_shipment_id']).val(returnArray['order_shipments'][i]['shipping_carrier_id']);
                            }
                        }
                        if ($("#order_shipments").find(".order-shipment").length === 0) {
                            $("#order_shipments").append("<tr class='order-shipment no-order-shipments'><td colspan='100'>No Distributor Orders yet</td></tr>");
                        }
                        if ("order_shipment_items_quantity" in returnArray) {
                            $("#order_shipment_items_quantity").html("Distributor Order items total quantity: " + returnArray['order_shipment_items_quantity'])
                        }

                    }
                });
            }

            function afterDeleteRecord(returnArray) {
                document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                return false;
            }

            function changesMade() {
                return false;
            }
        </script>
		<?php
	}

	function deleteRecord() {
		$returnArray = array();
		$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['primary_id']);
		if (!empty($orderId)) {
            updateFieldById("deleted", (!empty($_POST['deleted']) ? "0" : "1"), "orders", "order_id", $orderId, "client_id = ?", $GLOBALS['gClientId']);
			if ($_POST['deleted'] == "1") {
				$returnArray['deleted'] = "0";
				$returnArray['info_message'] = getLanguageText("Order successfully undeleted");
                $webhookReason = "mark_undeleted";
			} else {
				$returnArray['deleted'] = "1";
                $webhookReason = "mark_deleted";
			}
            Corestore::orderNotification($_POST['primary_id'], $webhookReason);
			$resultSet = executeQuery("select product_id from order_items where order_id = ?", $_POST['primary_id']);
			while ($row = getNextRow($resultSet)) {
				removeCachedData("*", $row['product_id']);
				removeCachedData("base_cost", $row['product_id']);
				removeCachedData("*", $row['product_id']);
			}
			if (empty($_POST['deleted'])) {
				$resultSet = executeQuery("select * from loyalty_program_point_log where order_id = ? and (notes is null or notes not like '%order deleted%')", $orderId);
				while ($row = getNextRow($resultSet)) {
					executeQuery("update loyalty_program_points set point_value = greatest(0,point_value - ?) where loyalty_program_point_id = ?",
						$row['point_value'], $row['loyalty_program_point_id']);
					executeQuery("update loyalty_program_point_log set notes = ? where loyalty_program_point_log_id = ?",
						date("m/d/Y") . ": points reversed because order deleted", $row['loyalty_program_point_log_id']);
				}
				$orderRow = getRowFromId("orders", "order_id", $orderId);
				$pickup = getReadFieldFromId("pickup", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
				if (!$pickup || Order::hasPhysicalProducts($orderId)) {
					$emailAddresses = array();
					$resultSet = executeQuery("select email_address from shipping_method_notifications where shipping_method_id = ?", $orderRow['shipping_method_id']);
					while ($row = getNextRow($resultSet)) {
						$emailAddresses[] = $row['email_address'];
					}
					if (!empty($emailAddresses)) {
						$substitutions = $orderRow;
						$emailResult = sendEmail(array("subject" => "Order Deleted", "body" => "<p>Order ID %order_id% from %full_name% has been deleted.</p>", "substitutions" => $substitutions, "email_addresses" => $emailAddresses));
					}
				}
			}
		}
		ajaxResponse($returnArray);
	}

	function orderTags() {
		$resultSet = executeQuery("select * from order_tags where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
		if ($resultSet['row_count'] > 0) {
			?>
            <h2>Order Tags</h2>
			<?php
			while ($row = getNextRow($resultSet)) {
				?>
                <div class="basic-form-line" id="_order_tag_id_<?= $row['order_tag_id'] ?>_row">
                    <input class="order-tag" type="checkbox" data-order_tag_id="<?= $row['order_tag_id'] ?>" id="order_tag_id_<?= $row['order_tag_id'] ?>" name="order_tag_id_<?= $row['order_tag_id'] ?>" value="1"><label class="checkbox-label" for="order_tag_id_<?= $row['order_tag_id'] ?>"><?= htmlText($row['description']) ?></label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
				<?php
			}
		}
	}

	function afterGetRecord(&$returnArray) {
		if (canAccessPageCode("ORDEREDIT")) {
			$returnArray['order_id_display'] = array("data_value" => "<a href='/orderedit.php?url_page=show&primary_id=" . $returnArray['primary_id']['data_value'] . "'>" . $returnArray['primary_id']['data_value'] . "</a>");
		} else {
			$returnArray['order_id_display'] = array("data_value" => $returnArray['primary_id']['data_value']);
		}
		if ($GLOBALS['gPHPVersion'] < 70200) {
			$returnArray['can_send_text'] = false;
		} else {
			$returnArray['can_send_text'] = TextMessage::canSendTextMessage($returnArray['contact_id']['data_value']);
		}
		$resultSet = executeQuery("select * from order_tags where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$orderTagLinkId = getFieldFromId("order_tag_link_id", "order_tag_links", "order_tag_id", $row['order_tag_id'], "order_id = ?", $returnArray['primary_id']['data_value']);
			$returnArray['order_tag_id_' . $row['order_tag_id']] = array("data_value" => (empty($orderTagLinkId) ? "" : "1"), "crc_value" => getCrcValue((empty($orderTagLinkId) ? "" : "1")));
		}

		$contactIdentifiers = "";
		$resultSet = executeQuery("select * from contact_identifiers join contact_identifier_types using (contact_identifier_type_id) where contact_id = ?" . ($GLOBALS['gUserRow']['administrator_flag'] ? "" : " and internal_use_only = 0"), $returnArray['contact_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$contactIdentifiers .= "<p>" . htmlText($row['description']) . ": " . $row['identifier_value'] . "</p>";
		}
		$returnArray['contact_identifiers'] = array("data_value" => $contactIdentifiers);

		$fullName = $returnArray['full_name']['data_value'];
		$returnArray['contact_name'] = array("data_value" => "<a target='_blank' href='/contactmaintenance.php?url_page=show&primary_id=" . $returnArray['contact_id']['data_value'] . "&clear_filter=true'>" . getDisplayName($returnArray['contact_id']['data_value']) . "</a>");
		$countSet = executeQuery("select count(*) from orders where contact_id = ? and order_id < ?", $returnArray['contact_id']['data_value'],
			$returnArray['primary_id']['data_value']);
		$orderCount = 0;
		if ($countRow = getNextRow($countSet)) {
			$orderCount = $countRow['count(*)'];
		}
		$returnArray['order_count'] = array("data_value" => ($orderCount == 0 ? "No previous orders" : $orderCount . " Previous Order" . ($orderCount == 1 ? "" : "s")));

		$returnArray['location_id'] = array("data_value" => "");
		$promotionId = getFieldFromId("promotion_id", "order_promotions", "order_id", $returnArray['primary_id']['data_value']);
		$returnArray['order_promotion'] = array("data_value" => (empty($promotionId) ? "" : "Used promotion '" . getFieldFromId("description", "promotions", "promotion_id", $promotionId) . "'"));

		if (!empty($returnArray['date_completed']['data_value'])) {
			$returnArray['order_status_display'] = array("data_value" => "Completed on " . date("m/d/Y", strtotime($returnArray['date_completed']['data_value'])));
			$returnArray['date_completed_wrapper'] = array("data_value" => "<button class='keep-visible' id='reopen_order'>Reopen Order</button>");
		} else {
			$returnArray['order_status_display'] = array("data_value" => getFieldFromId("description", "order_status", "order_status_id", $returnArray['order_status_id']['data_value']));
			$returnArray['date_completed_wrapper'] = array("data_value" => "<button id='mark_completed'>Mark Order Completed</button>");
		}
		$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $returnArray['contact_id']['data_value']);
		$returnArray['email_address'] = array("data_value" => (empty($emailAddress) ? "" : "<a href='mailto:" . $emailAddress . "'>" . $emailAddress . "</a>"));

		if (empty($returnArray['address_id']['data_value'])) {
			$addressRow = Contact::getContact($returnArray['contact_id']['data_value']);
		} else {
			$addressRow = getRowFromId("addresses", "address_id", $returnArray['address_id']['data_value']);
			if (empty($addressRow['address_1']) && empty($addressRow['city'])) {
				$addressRow = Contact::getContact($returnArray['contact_id']['data_value']);
			}
		}
		if (strlen($fullName) > 20) {
			$fullName = str_replace(", ", "<br>", $fullName);
		}
		$shippingAddress = $fullName . "<br>" . $addressRow['address_1'] . "<br>" . (empty($addressRow['address_2']) ? "" : $addressRow['address_2'] . "<br>") . $addressRow['city'] . ", " . $addressRow['state'] . "," . $addressRow['postal_code'];
		$shippingPostalCode = $addressRow['postal_code'];
		if ($addressRow['country_id'] != 1000) {
			$shippingAddress .= "<br>" . getFieldFromId("country_name", "countries", "country_id", $addressRow['country_id']);
		}
		$shippingAddress .= "</p><p><a target='_blank' href='https://www.google.com/maps?q=" . urlencode($addressRow['address_1'] . ", " . (empty($addressRow['address_2']) ? "" : $addressRow['address_2'] . ", ") . $addressRow['city'] . ", " . $addressRow['state'] . " " . $addressRow['postal_code']) . "'>Show on map</a>";
		$returnArray['shipping_address'] = array("data_value" => $shippingAddress);
		$returnArray['shipping_method_display'] = array("data_value" => getFieldFromId("description", "shipping_methods", "shipping_method_id", $returnArray['shipping_method_id']['data_value']));
		$returnArray['source_display'] = array("data_value" => getFieldFromId("description", "sources", "source_id", $returnArray['source_id']['data_value']));
		$returnArray['pickup'] = array("data_value" => getFieldFromId("pickup", "shipping_methods", "shipping_method_id", $returnArray['shipping_method_id']['data_value']));
		$returnArray['order_method_display'] = array("data_value" => getFieldFromId("description", "order_methods", "order_method_id", $returnArray['order_method_id']['data_value']));
		$returnArray['white_pages'] = array("data_value" => "<a target='_blank' href='https://www.whitepages.com/name/" . str_replace(" ", "-", $returnArray['full_name']['data_value']) . "/" . str_replace(" ", "-", $addressRow['city']) . "-" . $addressRow['state'] . "?fs=1&searchedLocation=" . urlencode($addressRow['city'] . ", " . $addressRow['state']) . "&searchedName=" . urlencode($returnArray['full_name']['data_value']) . "'>White Pages Listing</a>");
		$returnArray['reverse_white_pages'] = array("data_value" => "<a target='_blank' href='https://www.whitepages.com/address/" . str_replace(" ", "-", str_replace(",", "", $addressRow['address_1'])) . "/" . str_replace(" ", "-", $addressRow['city']) . "-" . $addressRow['state'] . "'>Reverse Address Lookup</a>");

		$returnArray['billing_address'] = array("data_value" => "");
		$accountId = getFieldFromId("account_id", "order_payments", "order_id", $returnArray['primary_id']['data_value'], "account_id is not null");
		if (empty($accountId)) {
			$accountId = $returnArray['account_id']['data_value'];
		}
		if (!empty($accountId)) {
			$accountRow = getRowFromId("accounts", "account_id", $accountId);
			$billingAddressId = $accountRow['address_id'];
			if (empty($billingAddressId)) {
				$addressRow = Contact::getContact($returnArray['contact_id']['data_value']);
			} else {
				$addressRow = getRowFromId("addresses", "address_id", $billingAddressId);
			}
			$billingPostalCode = $addressRow['postal_code'];
			$distance = calculateDistance(getPointForZipCode($billingPostalCode), getPointForZipCode($shippingPostalCode));
			if ($distance > 100) {
				if ($distance < 200) {
					$class = "orange-text";
				} else {
					$class = "red-text";
				}
			}
			$billingAddress = '<div class="' . $class . '">' . $addressRow['address_1'] . "<br>" . (empty($addressRow['address_2']) ? "" : $addressRow['address_2'] . "<br>") . $addressRow['city'] . ", " . $addressRow['state'] . " " . $addressRow['postal_code'];
			if ($addressRow['country_id'] != 1000) {
				$billingAddress .= "<br>" . getFieldFromId("country_name", "countries", "country_id", $addressRow['country_id']);
			}
			$billingAddress .= ($distance > 100 ? "<br>" . number_format($distance) . " miles from shipping address" : "");
			$billingAddress .= "</div></p><p>" . getFieldFromId("description", "payment_methods", "payment_method_id", $accountRow['payment_method_id']) . " - " . $accountRow['account_number'];
			$returnArray['billing_address'] = array("data_value" => $billingAddress);
		}
		$orderItemTotal = 0;
		$orderItemCount = 0;
		$resultSet = executeQuery("select * from order_items where order_id = ? and deleted = 0", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$orderItemTotal += $row['sale_price'] * $row['quantity'];
			$orderItemCount += $row['quantity'];
		}
		if ($orderItemCount > 0) {
			$orderTotal = $orderItemTotal - $returnArray['order_discount']['data_value'] + $returnArray['shipping_charge']['data_value'] + $returnArray['handling_charge']['data_value'] + $returnArray['tax_charge']['data_value'];
		}
		$donationAmount = getFieldFromId("amount", "donations", "donation_id", $returnArray['donation_id']['data_value']);
		if (empty($donationAmount)) {
			$donationAmount = 0;
		}
		$orderTotal += $donationAmount;
		if ($donationAmount <= 0) {
			$returnArray['donation_amount_wrapper'] = array("data_value" => "");
		} else {
			$returnArray['donation_amount_wrapper'] = array("data_value" => "<label>Donation</label> <span id='donation_amount'>" . number_format($donationAmount, 2, ".", ",") . "</span>");
		}

		$openInvoices = false;
		$notCaptured = false;
		$orderPayments = array();
		$paymentTotal = 0;
		$resultSet = executeQuery("select *,(select contact_id from orders where order_id = order_payments.order_id) as contact_id from order_payments where order_id = ? order by payment_time", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['not_captured'])) {
				$notCaptured = true;
			}
			$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id", $row['payment_method_id']));
			$invoiceId = "";
			if (!empty($row['invoice_id'])) {
				$invoiceId = getFieldFromId("invoice_id", "invoices", "invoice_id", $row['invoice_id'], "inactive = 0 and contact_id = ?", $row['contact_id']);
			}
			if (empty($invoiceId)) {
				$invoiceId = getFieldFromId("invoice_id", "invoices", "invoice_number", $row['order_id'], "inactive = 0 and contact_id = ?", $row['contact_id']);
			}
			if (!empty($invoiceId) && $paymentMethodTypeCode == "INVOICE") {
				executeQuery("update order_payments set invoice_id = ? where order_payment_id = ?", $invoiceId, $row['order_payment_id']);
				$row['invoice_id'] = $invoiceId;
			}
			if (!empty($invoiceId)) {
				$dateCompleted = getFieldFromId("date_completed", "invoices", "invoice_id", $invoiceId);
				if (empty($dateCompleted)) {
					$openInvoices = true;
				}
				$invoiceTotal = 0;
				$invoiceSet = executeQuery("select sum(amount * unit_price) as invoice_total from invoice_details where invoice_id = ?", $row['invoice_id']);
				if ($invoiceRow = getNextRow($invoiceSet)) {
					$invoiceTotal = $invoiceRow['invoice_total'];
				}
				$totalPayments = 0;
				$invoiceSet = executeQuery("select sum(amount) as total_payments from invoice_payments where invoice_id = ?", $row['invoice_id']);
				if ($invoiceRow = getNextRow($invoiceSet)) {
					$totalPayments = $invoiceRow['total_payments'];
				}
				$balanceDue = $invoiceTotal - $totalPayments;
				if ($balanceDue >= 0) {
					$row['invoice_info'] = number_format($balanceDue, 2, ".", ",") . " due";
				} else {
					$row['invoice_info'] = "Invoice Paid";
				}
			}
			$row['payment_time'] = date("m/d/Y g:i a", strtotime($row['payment_time']));
			$row['not_captured'] = (empty($row['deleted']) ? (empty($row['not_captured']) ? "Done" : "<button class='capture-payment'>Capture Now</button>") : "");
			$row['payment_method'] = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']);
			$fullName = getFieldFromId("full_name", "accounts", "account_id", $row['account_id']);
			$row['account_number'] = (empty($row['account_id']) ? $row['reference_number'] : (empty($fullName) ? "" : $fullName . ", ") . substr(getFieldFromId("account_number", "accounts", "account_id", $row['account_id']), -8));
			$row['total_amount'] = number_format($row['amount'] + $row['shipping_charge'] + $row['tax_charge'] + $row['handling_charge'], 2, ".", ",");
			if (empty($row['deleted'])) {
				$paymentTotal += $row['amount'] + $row['shipping_charge'] + $row['tax_charge'] + $row['handling_charge'];
			}
			$row['amount'] = number_format($row['amount'], 2, ".", ",");
			$row['shipping_charge'] = number_format($row['shipping_charge'], 2, ".", ",");
			$row['tax_charge'] = number_format($row['tax_charge'], 2, ".", ",");
			$row['handling_charge'] = number_format($row['handling_charge'], 2, ".", ",");
			$orderPayments[] = $row;
		}
		if ($openInvoices) {
			$returnArray['_invoice_message'] = array("data_value" => "This order has open invoices.");
		} else {
			$returnArray['_invoice_message'] = array("data_value" => "");
		}
		if (count($orderPayments) == 0) {
			$returnArray['payment_warning'] = array("data_value" => "No Payments have been made for this order");
		} else if (round($orderTotal - $paymentTotal, 2) != 0) {
			$returnArray['payment_warning'] = array("data_value" => "Payments do not equal the order total. Payments: " . number_format($paymentTotal, 2, ".", ",") . ", Order: " . number_format($orderTotal, 2, ".", ","));
		} else if ($openInvoices) {
			$returnArray['payment_warning'] = array("data_value" => "This order has open invoices.");
		} else {
			$returnArray['payment_warning'] = array("data_value" => "");
		}
		$returnArray['order_payments'] = $orderPayments;
		$returnArray['not_captured'] = $notCaptured;
		if ($notCaptured) {
			$returnArray['_capture_message'] = array("data_value" => "Distributor Orders cannot be created until all payments are captured");
		} else {
			$returnArray['_capture_message'] = array("data_value" => "");
		}

		$touchpoints = array();
		$resultSet = executeQuery("select task_id, task_type_id, description, detailed_description, date_completed, order_id"
			. " from tasks where contact_id = ? order by task_id desc", $returnArray['contact_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$row['task_type'] = getFieldFromId("description", "task_types", "task_type_id", $row['task_type_id']);
			$row['date_completed'] = (empty($row['date_completed']) ? "" : date("m/d/Y", strtotime($row['date_completed'])));
			$touchpoints[] = $row;
		}
		$returnArray['touchpoints'] = $touchpoints;

		$orderNotes = array();
		$resultSet = executeQuery("select * from order_notes where order_id = ? order by time_submitted desc", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$row['time_submitted'] = date("m/d/Y g:ia", strtotime($row['time_submitted']));
			$row['user_id'] = getUserDisplayName($row['user_id']);
			$row['public_access'] = (empty($row['public_access']) ? "" : "YES");
			$orderNotes[] = $row;
		}
		$returnArray['order_notes'] = $orderNotes;
		if (count($orderNotes) > 0) {
			$returnArray['error_message'] = "Check notes below";
		}

		$orderProductKey = "";
		$resultSet = executeQuery("select group_concat(concat_ws('|',product_id,quantity,sale_price) order by product_id) order_product_key from order_items where order_id = ?", $returnArray['primary_id']['data_value']);
		if ($row = getNextRow($resultSet)) {
			$orderProductKey = $row['order_product_key'];
		}

		$returnArray['ip_address_message'] = array("data_value" => $returnArray['ip_address']['data_value']);
		if (!empty($returnArray['ip_address']['data_value'])) {
			$ipAddress = $returnArray['ip_address']['data_value'];
			$ipAddressData = getIpAddressMetrics($ipAddress);

			if (!empty($ipAddressData)) {
				if (!empty($ipAddressData['latitude']) && !empty($ipAddressData['longitude'])) {
					$orderPoint = array("latitude" => $ipAddressData['latitude'], "longitude" => $ipAddressData['longitude']);
				} else {
					if (!empty($ipAddressData['postal_code'])) {
						$orderPoint = getPointForZipCode($ipAddressData['postal_code']);
					} else {
						$orderPoint = array();
					}
				}
				if (!empty($orderPoint)) {
					$shipPoint = getPointForZipCode($addressRow['postal_code']);
				}
				if (!empty($orderPoint) && !empty($shipPoint)) {
					$distance = calculateDistance($orderPoint, $shipPoint);
					if ($distance < 100) {
						$class = "green-text";
					} else {
						if ($distance < 200) {
							$class = "orange-text";
						} else {
							$class = "red-text";
						}
					}
					$returnArray['ip_address_message']['data_value'] = "<a href='http://ip-api.com/#" . $ipAddress . "' target='_blank' class='" . $class . "'>" . $ipAddress . "</a>";
				}
			}
		}
	}

	function internalCSS() {
		?>
        <style>
            <?php if (!empty($_GET['simplified'])) { ?>
            .advanced-feature {
                display: none !important;
            }

            <?php } ?>
            .help-desk-entry {
                cursor: pointer;
            }

            .help-desk-entry:hover td {
                background-color: rgb(240, 240, 180);
            }

            #jquery_templates {
                display: none;
            }

            #_order_header_section {
                margin-top: 20px;
            }

            #_capture_message, #_invoice_message {
                color: rgb(192, 0, 0);
            }

            <?php
			if ($_GET['url_page'] == "show") {
				?>
            #_page_number_controls {
                display: none !important;
            }

            #_form_header_buttons {
                margin-bottom: 0;
            }

            #_management_header {
                padding-bottom: 0;
            }

            #_main_content {
                margin-top: 0;
            }

            p#_error_message {
                margin-bottom: 0;
            }

            <?php
		}
		?>
            .distance--miles {
                display: none;
            }

            #payment_warning {
                color: rgb(192, 0, 0);
                font-weight: 900;
                font-size: 1.2rem;
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

            #_list_actions, #_list_search_control, #_list_header_buttons {
                margin-bottom: 0;
            }

            #_add_button {
                display: none;
            }

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

            #order_information_block {
                background-color: rgb(240, 240, 240);
                border: 1px solid rgb(180, 180, 180);
                padding: 20px;
                display: flex;
                margin-bottom: 10px;
            }

            #order_information_block > div {
                flex: 1 1 auto;
            }

            #_main_content p#contact_name {
                font-size: 1.6rem;
                font-weight: 300;
            }

            p label {
                font-size: 1rem;
                margin-right: 20px;
            }

            table.order-information {
                width: 100%;
                margin-bottom: 10px;
                border: 1px solid rgb(150, 150, 150);
            }

            table.order-information tr {
                border: 1px solid rgb(150, 150, 150);
            }

            table.order-information th {
                vertical-align: middle;
                padding: 5px;
            }

            table.order-information td {
                background-color: rgb(240, 240, 240);
                padding: 5px;
            }

            .product-description {
                font-size: .8rem;
                line-height: 1.2;
            }

            .upc-code, .product-code {
                color: rgb(150, 150, 150);
                font-weight: 300;
                font-size: .8rem;
            }

            #order_status_id {
                min-width: 200px;
                width: 200px;
                max-width: 100%;
            }

            .order-shipment input {
                width: 150px;
                font-size: .8rem;
            }

            .order-shipment .notes {
                height: 26px;
                width: 150px;
            }

            .order-shipment .notes:focus {
                height: 100px;
            }

            .inventory-quantities {
                white-space: nowrap;
                font-size: .7rem;
                line-height: 1.2;
                text-align: left;
            }

            .quantity-input {
                overflow: visible;
                white-space: nowrap;
            }

            .ship-all {
                margin-right: 5px;
                cursor: pointer;
            }

            .ship-all-items {
                cursor: pointer;
            }

            #predefined_message {
                font-size: .7rem;
            }

            #order_status_wrapper {
                display: flex;
                background-color: rgb(180, 180, 180);
                padding: 20px;
                color: #FFF;
                text-shadow: 0 1px 2px #000000;
                text-transform: uppercase;
                font-size: 1.4rem;
            }

            #order_status_display {
                flex: 1 1 auto;
            }

            #order_id_wrapper {
                text-align: right;
            }

            #order_id_wrapper a {
                color: rgb(255, 255, 255);
                font-weight: normal;
                text-decoration: none;
            }

            #order_id_wrapper a:hover {
                text-decoration: underline;
            }

            #postage_rates {
                border-top: 1px solid rgb(180, 180, 180);
                padding-top: 20px;
                margin-top: 20px;
            }

            .create-label {
                white-space: nowrap;
            }

            .create-shipping-label {
                cursor: pointer;
                margin-right: 10px;
            }

            .print-packing-slip {
                cursor: pointer;
                margin-right: 10px;
            }

            .send-tracking-email {
                cursor: pointer;
                margin-right: 10px;
            }

            .print-1508 {
                cursor: pointer;
            }

            .track-package {
                margin-left: 10px;
                cursor: pointer;
            }

            #order_filters button {
                padding: 5px 15px;
            }

            .basic-form-line.serial-number-wrapper {
                margin-bottom: 0;
                margin-top: 10px;
            }

            .dialog-box .basic-form-line {
                white-space: nowrap;
            }

            .distributor-order-product {
                cursor: pointer;
            }

            .distributor-order-product:hover {
                color: rgb(200, 200, 200);
            }

            .no-license {
                font-weight: bold;
                color: rgb(192, 0, 0);
            }

            .distributor-order-message {
                font-weight: bold;
                color: rgb(0, 150, 0);
                margin-top: 10px;
            }

            .order-item-status-id {
                width: 200px;
            }

            #order_payments td {
                font-size: .7rem;
            }

            #_save_button {
                display: none;
            }

            <?php
			$resultSet = executeQuery("select * from order_status where display_color is not null and client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$rgb = hex2rgb($row['display_color']);
				$lightRgb = $rgb;
				foreach ($lightRgb as $index => $thisColor) {
					$lightRgb[$index] = $thisColor + round((255 - $thisColor) * .8);
				}
				?>
            .order-status-<?= $row['order_status_id'] ?> {
                background-color: rgb(<?= $rgb[0] ?>,<?= $rgb[1] ?>,<?= $rgb[2] ?>) !important;
            }

            .order-status-<?= $row['order_status_id'] ?>-light {
                background-color: rgb(<?= $lightRgb[0] ?>,<?= $lightRgb[1] ?>,<?= $lightRgb[2] ?>) !important;
                border: 1px solid rgb(<?= $rgb[0] ?>,<?= $rgb[1] ?>,<?= $rgb[2] ?>) !important;
            }

            <?php
		}
		$resultSet = executeQuery("select * from order_item_statuses where display_color is not null and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$rgb = hex2rgb($row['display_color']);
			$lightRgb = $rgb;
			foreach ($lightRgb as $index => $thisColor) {
				$lightRgb[$index] = $thisColor + round((255 - $thisColor) * .8);
			}
			?>
            .order-item-status-<?= $row['order_item_status_id'] ?> td {
                background-color: rgb(<?= $rgb[0] ?>,<?= $rgb[1] ?>,<?= $rgb[2] ?>) !important;
            }

            <?php
		}
		?>
        </style>
		<?php
	}

	function getListRowClasses($columnRow) {
		if (!empty($columnRow['order_status_id'])) {
			return "order-status-" . $columnRow['order_status_id'] . "-light";
		}
		return "";
	}

	function hiddenElements() {
		?>
        <div id="_set_status_dialog" class="dialog-box">
            <p>Set status for selected orders. This cannot be undone, so be sure.</p>
            <form id="_set_status_form">
                <div class="basic-form-line" id="_order_status_id_row">
                    <label for="order_status_id">Order Status</label>
                    <select id="order_status_id" name="order_status_id">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from order_status where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['order_status_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
            </form>
        </div>

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


        <div id="_payment_dialog" class="dialog-box">
            <form id="_payment_form">
                <p class="error-message"></p>

                <p>Creating a payment here will NOT issue a refund or charge the payment method. That should be done manually. This is simply a record of the payment or refund.</p>

                <div class="basic-form-line" id="_payment_time_row">
                    <label class="">Payment Date</label>
                    <input tabindex="10" type="text" class="validate[custom[date]] datepicker" size="12" id="payment_time" name="payment_time">
                    <div class='basic-form-line-messages'><span class="help-label">Leave blank to use current date/time</span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_payment_method_id_row">
                    <label class="required-label">Payment Method</label>
                    <select tabindex="10" class="validate[required]" id="payment_method_id" name="payment_method_id">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from payment_methods where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['payment_method_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_amount_row">
                    <label class="required-label">Amount</label>
                    <input tabindex="10" type="text" class="validate[required,custom[number]]" data-decimal-places="2" size="12" id="amount" name="amount">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_authorization_code_row">
                    <label>CC Authorization Code</label>
                    <input tabindex="10" type="text" size="12" id="authorization_code" name="authorization_code">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_transaction_identifier_row">
                    <label>CC Transaction ID</label>
                    <input tabindex="10" type="text" size="20" id="transaction_identifier" name="transaction_identifier">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
            </form>
        </div>

        <div id="_confirm_shipment_dialog" class="dialog-box">
            This will result in an order being placed with the distributor. Are you sure?
        </div> <!-- confirm_shipment_dialog -->

        <div id="_confirm_delete_shipments_dialog" class="dialog-box">
            All shipments from this location will be deleted. Are you sure?
        </div> <!-- _confirm_delete_shipments_dialog -->

        <div id="_confirm_email_dialog" class="dialog-box">
            Are you sure you want to send/resend this tracking email?
        </div> <!-- confirm_shipment_dialog -->

        <div id="_confirm_receipt_email_dialog" class="dialog-box">
            Are you sure you want to resend this receipt?
        </div> <!-- confirm_shipment_dialog -->

        <div id="_confirm_delete_item_dialog" class="dialog-box">
            This will NOT remove the item from an order to a distributor. You will need to contact the distributor directly to do that. This cannot be undone. Are you sure?
        </div> <!-- confirm_shipment_dialog -->

        <div id="_send_email_dialog" class="dialog-box">
            <form id="_send_email_form">

                <p class="error-message"></p>
                <div class="basic-form-line" id="_email_subject_row">
                    <label for="email_subject" class="required-label">Subject</label>
                    <input type="text" class="validate[required]" maxlength="255" id="email_subject" name="email_subject">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_email_body_row">
                    <label for="email_body" class="required-label">Content</label>
                    <textarea class="validate[required] ck-editor" id="email_body" name="email_body"></textarea>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

            </form>
        </div> <!-- send_email_dialog -->

        <div id="_send_text_message_dialog" class="dialog-box">
            <form id="_send_text_message_form">

                <p class="error-message"></p>
                <div class="basic-form-line" id="_text_message_row">
                    <label for="text_message" class="required-label">Content</label>
                    <textarea class="validate[required]" id="text_message" name="text_message"></textarea>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

            </form>
        </div> <!-- send_text_message_dialog -->
		<?php
	}

	function jqueryTemplates() {
		?>
        <table>
            <tbody id="_order_item_template">
            <tr class="order-item" id="order_item_%order_item_id%" data-order_item_id="%order_item_id%">
                <td class='product-description'>
					<?php
					$pageLink = false;
					if ($_GET['simplified']) {
						if (canAccessPageCode("PRODUCTMAINT_LITE")) {
							$pageLink = "product-maintenance";
						}
					} else if (canAccessPageCode("PRODUCTMAINT")) {
						$pageLink = "productmaintenance.php";
					}
					?>
					<?php if (empty($pageLink)) { ?>
                        %product_description% %additional_product_description%
					<?php } else { ?>
                        <a href="/<?= $pageLink ?>?url_page=show&primary_id=%product_id%&clear_filter=true" target="_blank">%product_description%</a>%additional_product_description%
					<?php } ?>
                    <div class="basic-form-line custom-control-form-line custom-control-no-help hidden serial-number-wrapper">
						<?php
						$serialNumberControl = new DataColumn("order_item_serial_numbers_%order_item_id%");
						$serialNumberControl->setControlValue("data_type", "custom_control");
						$serialNumberControl->setControlValue("control_class", "EditableList");
						$serialNumberControl->setControlValue("primary_table", "order_items");
						$serialNumberControl->setControlValue("list_table", "order_item_serial_numbers");
						echo $serialNumberControl->getControl($this);
						?>
                    </div>
                    <div class='distributor-order-message'>%distributor_order_message%</div>
                </td>
                <td class='order-item-status'><select class="order-item-status-id" id="order_item_status_id_%order_item_id%" name="order_item_status_id_%order_item_id%">
                        <option value="">[None]</option>
                        <option value="-1">[DELETED]</option>
						<?php
						$resultSet = executeQuery("select * from order_item_statuses where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['order_item_status_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select></td>
                <td class="align-center quantity-input"><span id="ship_all_%order_item_id%" class="ship-all hidden fas fa-check"></span><input type="text" size="4" class="shipment-quantity hidden" id="shipment_quantity_%order_item_id%"
                                                                                                                                               name="shipment_quantity_%order_item_id%"></td>
                <td class="align-right quantity"><input type="hidden" id="shipped_quantity_%order_item_id%" value="%shipped_quantity%"><input type="hidden" id="quantity_%order_item_id%" value="%quantity%">%quantity%</td>
                <td class="align-right sale-price">%sale_price%</td>
                <td class="align-right total-price">%total_price%</td>
                <td class="align-center distributor-order-product" data-quantity="%quantity%" data-product_id="%product_id%" title="Add to Distributor Order"><span class='fas fa-plus-square'></span></td>
            </tr>
            </tbody>
        </table>

        <table>
            <tbody id="_order_shipment_template">
            <tr class="order-shipment" id="order_shipment_%order_shipment_id%" data-order_shipment_id="%order_shipment_id%">
                <td class='date-shipped'><input type="hidden" class="label-url" value="%label_url%">%date_shipped%</td>
                <td class='location'>%location%</td>
                <td class='order-number'>%order_number%</td>
                <td class='full-name'>%full_name%</td>
                <td class='create-label'>
                    <span title="Print Packing Slip" class="print-packing-slip far fa-print"></span>
                    <span title="Send Email" class="send-tracking-email far fa-envelope"></span>
                </td>
                <td class='align-right shipping-charge'>%shipping_charge%</td>
                <td><input data-field_name="tracking_identifier" class='editable-shipping-field tracking-identifier' type="text" id="tracking_identifier_%order_shipment_id%" value="%tracking_identifier%"> <span title="Track Package" class="track-package far fa-map-marked-alt" data-link_url="%link_url%"></span>
                </td>
                <td><select data-field_name="shipping_carrier_id" class='editable-shipping-field shipping-carrier-id' id="shipping_carrier_id_%order_shipment_id%">
                        <option value="">[Other]</option>
						<?php
						$resultSet = executeQuery("select * from shipping_carriers where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['shipping_carrier_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                </td>
                <td><input data-field_name="carrier_description" class='editable-shipping-field carrier-description' type="text" id="carrier_description_%order_shipment_id%" value="%carrier_description%"></td>
                <td colspan="2"><textarea data-field_name="notes" class='editable-shipping-field notes' id="notes_%order_shipment_id%">%notes%</textarea></td>
            </tr>
            </tbody>
        </table>

        <table>
            <tbody id="_order_shipment_item_template">
            <tr class="order-shipment-item" id="order_shipment_item_%order_shipment_item_id%" data-order_shipment_id="%order_shipment_id%" data-order_shipment_item_id="%order_shipment_item_id%">
                <td></td>
                <td colspan="8">%product_description%</td>
                <td class='align-right'>%quantity%</td>
            </tr>
            </tbody>
        </table>

        <table>
            <tbody id="_order_payment_template">
            <tr class="order-payment %additional_classes%" id="order_payment_%order_payment_id%" data-order_payment_id="%order_payment_id%">
                <td class='payment_time'>%payment_time%</td>
                <td class='payment-method'>%payment_method%</td>
                <td class='account-number'>%account_number%</td>
                <td class='not-captured'>%not_captured%</td>
                <td class='transaction-identifier'>%transaction_identifier%</td>
                <td class='align-right amount'>%amount%</td>
                <td class='align-right shipping-charge'>%shipping_charge%</td>
                <td class='align-right tax-charge'>%tax_charge%</td>
                <td class='align-right handling-charge'>%handling_charge%</td>
                <td class='align-right total-amount'>%total_amount%</td>
            </tr>
            </tbody>
        </table>

        <table>
            <tbody id="_order_note_template">
            <tr class="order-note" id="order_note_%order_note_id%" data-order_note_id="%order_note_id%">
                <td class='time-submitted'>%time_submitted%</td>
                <td class='user-id'>%user_id%</td>
                <td class='public-access'>%public_access%</td>
                <td class='content'>%content%</td>
            </tr>
            </tbody>
        </table>

        <table>
            <tbody id="_touchpoint_template">
            <tr class="touchpoint order-%order_id%" id="touchpoint_%task_id%">
                <td>%date_completed%</td>
                <td>%task_type%</td>
                <td>%description%</td>
                <td>%detailed_description%</td>
            </tr>
            </tbody>
        </table>

		<?php
	}
}

$pageObject = new DealerOrderDashboardPage("orders");
$pageObject->displayPage();
