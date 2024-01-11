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

$GLOBALS['gPageCode'] = "PICKUPDASHBOARD";
require_once "shared/startup.inc";

class PickupDashboardPage extends Page {

	var $iSearchContactFields = array("contact_id", "first_name", "last_name", "business_name", "address_1", "city", "state", "postal_code", "email_address");
	var $iSearchFields = array("full_name", "phone_number");

	function massageDataSource() {
		$this->iDataSource->addColumnControl("address_id", "choices", array());
		$this->iDataSource->addColumnControl("ship_to_address", "data_type", "varchar");
		$this->iDataSource->addColumnControl("ship_to_address", "form_label", "Ship To Address");
		$this->iDataSource->addColumnControl("ship_to_address", "select_value", "if(address_id is null,(select concat_ws(',',address_1,address_2,city,state,postal_code) from contacts where contact_id = orders.contact_id),(select concat_ws(',',address_1,address_2,city,state,postal_code) from addresses where address_id = orders.address_id))");

		$this->iDataSource->addColumnControl("deleted", "data_type", "hidden");
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "order_items",
			"referenced_column_name" => "order_id", "foreign_key" => "order_id", "description" => "description"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "order_items",
			"referenced_column_name" => "order_id", "foreign_key" => "order_id", "description" => "product_id"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "contact_id", "description" => "first_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "contact_id", "description" => "last_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "contact_id", "description" => "email_address"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "phone_numbers",
			"referenced_column_name" => "contact_id", "foreign_key" => "contact_id", "description" => "phone_number"));

		$this->iDataSource->addColumnLikeColumn("order_files_description", "order_files", "description");
		$this->iDataSource->addColumnLikeColumn("order_files_file_id", "order_files", "file_id");
		$this->iDataSource->addColumnControl("order_files_file_id", "no_remove", true);
		$this->iDataSource->addColumnControl("order_files_description", "not_null", false);
		$this->iDataSource->addColumnControl("order_files_file_id", "not_null", false);
		$this->iDataSource->addColumnControl("add_file", "data_type", "button");
		$this->iDataSource->addColumnControl("add_file", "button_label", "Add File");

		$this->iDataSource->addColumnLikeColumn("content", "order_notes", "content");
		$this->iDataSource->addColumnLikeColumn("public_access", "order_notes", "public_access");
		$this->iDataSource->addColumnControl("content", "not_null", false);
		$this->iDataSource->addColumnControl("add_note", "data_type", "button");
		$this->iDataSource->addColumnControl("add_note", "button_label", "Add Note");

		$this->iDataSource->addColumnControl("order_number", "form_label", "Order #");
		$this->iDataSource->addColumnControl("order_amount", "form_label", "Amount");
		$this->iDataSource->addColumnControl("order_amount", "data_type", "decimal");
		$this->iDataSource->addColumnControl("order_amount", "decimal_places", "2");
		$this->iDataSource->addColumnControl("order_amount", "select_value", "(select sum(order_items.sale_price * order_items.quantity) " .
			"from order_items where orders.order_id = order_items.order_id) + shipping_charge + tax_charge + handling_charge");
		$this->iDataSource->addColumnControl("date_completed", "form_label", "Completed");
		$this->iDataSource->addFilterWhere("order_id in (select order_id from order_items where exists (select product_id from products where product_id = order_items.product_id and " .
			"virtual_product = 0)) and shipping_method_id in (select shipping_method_id from shipping_methods where pickup = 1 and location_id is not null" .
			($GLOBALS['gUserRow']['full_client_access'] ? "" : " and location_id in (select location_id from user_locations where user_id = " . $GLOBALS['gUserId'] . ")") . ")");
	}

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("order_number", "full_name", "order_amount", "order_time", "order_status_id"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn("signature");
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("save", "add"));
			$this->iTemplateObject->getTableEditorObject()->setMaximumListColumns(5);
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("pick_list" => array("icon" => "fad fa-print", "label" => getLanguageText("Pick List"), "disabled" => false)));
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("pickup_ready" => array("icon" => "fad fa-truck-pickup", "label" => getLanguageText("Ready For Pickup"), "disabled" => false)));
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("picked_up" => array("icon" => "fad fa-check-square", "label" => getLanguageText("Picked Up"), "disabled" => false)));

			$filters = array();
			$filters['hide_deleted'] = array("form_label" => "Hide Deleted", "where" => "deleted = 0", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);
			$filters['hide_completed'] = array("form_label" => "Hide Completed", "where" => "date_completed is null", "data_type" => "tinyint", "conjunction" => "and");
			$filters['start_date'] = array("form_label" => "Start Date", "where" => "order_time >= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
			$filters['end_date'] = array("form_label" => "End Date", "where" => "order_time <= '%filter_value%'", "data_type" => "date", "conjunction" => "and");

			$orderTags = array();
			$resultSet = executeQuery("select * from order_tags where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$orderTags[$row['order_tag_id']] = $row['description'];
			}
			$filters['order_tags'] = array("form_label" => "Order Tag", "where" => "order_id in (select order_id from order_tag_links where order_tag_id = %key_value%)", "data_type" => "select", "choices" => $orderTags);

			$resultSet = executeQuery("select * from order_status where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['order_status_id_' . $row['order_status_id']] = array("form_label" => $row['description'], "where" => "order_status_id = " . $row['order_status_id'], "data_type" => "tinyint");
			}
			$filters['no_order_status'] = array("form_label" => "No Status Set", "where" => "order_status_id is null", "data_type" => "tinyint");
			$paymentMethods = array();
			$resultSet = executeQuery("select * from payment_methods where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$paymentMethods[$row['payment_method_id']] = $row['description'];
			}
			$filters['payment_method'] = array("form_label" => "Payment Method", "where" => "payment_method_id = %key_value% or order_id in (select order_id from order_payments where payment_method_id = %key_value%)", "data_type" => "select", "choices" => $paymentMethods);
			$shippingMethods = array();
			$resultSet = executeQuery("select * from shipping_methods where pickup = 1 and location_id is not null " .
				($GLOBALS['gUserRow']['full_client_access'] ? "" : "and location_id in (select location_id from user_locations where user_id = " . $GLOBALS['gUserId'] . ")") .
				"and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$shippingMethods[$row['shipping_method_id']] = $row['description'];
			}
			$filters['shipping_method'] = array("form_label" => "Shipping Method", "where" => "shipping_method_id = %key_value%", "data_type" => "select", "choices" => $shippingMethods);
			$filters['notes'] = array("form_label" => "Notes Contain", "where" => "order_id in (select order_id from order_notes where content like %like_value%)", "data_type" => "varchar", "conjunction" => "and");
			$filters['physical_products'] = array("form_label" => "Only orders with physical products", "where" => "order_id in (select order_id from order_items where product_id in (select product_id from products where virtual_product = 0))", "data_type" => "tinyint", "conjunction" => "and");
			$filters['inventory_products'] = array("form_label" => "Only orders with inventoried products", "where" => "order_id in (select order_id from order_items where product_id in (select product_id from products where non_inventory_item = 0))", "data_type" => "tinyint", "conjunction" => "and");
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);

			$extraColumns = array();
			$extraColumns['status'] = array("column_name" => "status",
				"description" => "Actions",
				"data_value" => "<span title='Print Pick List' class='print-pick-list data-list-icon fad fa-print'></span> " .
					"<span title='Ready for Pickup' class='ready-pickup data-list-icon fad fa-truck-pickup'></span> " .
					"<span title='Completed' class='order-picked-up data-list-icon fad fa-check-square'></span>"
			);
			$this->iTemplateObject->getTableEditorObject()->setExtraColumns($extraColumns);
		}

		if ((empty($_GET['url_page']) || $_GET['url_page'] == "list") && empty($this->iPageData['pre_management_header'])) {
			ob_start();
			?>
            <div id="_order_header_section" class="header-section">
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

            <div id="statistics_block">
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
                        <div><h2 id="order_count_new">0</h2>
                            <p>New</p></div>
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
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_status", "Set Status of Selected Orders");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("mark_selected_completed", "Mark Selected Orders Completed");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("mark_selected_not_completed", "Mark Selected Orders NOT Completed");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("print_picklists", "Print Picklist for Selected Orders");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
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
			case "change_contact":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Order not found";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$dataTable = new DataTable("orders");
				$dataTable->setSaveOnlyPresent(true);
				if (!$dataTable->saveRecord(array("name_values" => array("full_name" => $_POST['change_full_name']), "primary_id" => $orderId))) {
					$returnArray['error_message'] = $dataTable->getErrorMessage();
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$addressId = getFieldFromId("address_id", "orders", "order_id", $orderId);
				if (empty($addressId)) {
					$contactId = getFieldFromId("contact_id", "orders", "order_id", $orderId);
					$dataTable = new DataTable("contacts");
					$dataTable->setSaveOnlyPresent(true);
					if (!$dataTable->saveRecord(array("name_values" => array("address_1" => $_POST['change_address_1'], "address_2" => $_POST['change_address_2'], "city" => $_POST['change_city'], "state" => $_POST['change_state'], "postal_code" => $_POST['change_postal_code']), "primary_id" => $contactId))) {
						$returnArray['error_message'] = $dataTable->getErrorMessage();
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				} else {
					$dataTable = new DataTable("addresses");
					$dataTable->setSaveOnlyPresent(true);
					if (!$dataTable->saveRecord(array("name_values" => array("address_1" => $_POST['change_address_1'], "address_2" => $_POST['change_address_2'], "city" => $_POST['change_city'], "state" => $_POST['change_state'], "postal_code" => $_POST['change_postal_code']), "primary_id" => $addressId))) {
						$returnArray['error_message'] = $dataTable->getErrorMessage();
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				$returnArray['full_name'] = $_POST['change_full_name'];
				$returnArray['address_1'] = $_POST['change_address_1'];
				$returnArray['address_2'] = $_POST['change_address_2'];
				$returnArray['city'] = $_POST['change_city'];
				$returnArray['state'] = $_POST['change_state'];
				$returnArray['postal_code'] = $_POST['change_postal_code'];

				$shippingAddress = $_POST['change_full_name'] . "<br>" . $_POST['change_address_1'] . "<br>" . (empty($_POST['change_address_2']) ? "" : $_POST['change_address_2'] . "<br>") . $_POST['change_city'] . ", " . $_POST['change_state'] . "," . $_POST['change_postal_code'];
				$shippingAddress .= "<br><a target='_blank' href='https://www.google.com/maps?q=" . urlencode($_POST['change_address_1'] . ", " . (empty($_POST['change_address_2']) ? "" : $_POST['change_address_2'] . ", ") . $_POST['change_city'] . ", " . $_POST['change_state'] . " " . $_POST['change_postal_code']) . "'>Show on map</a>";
				$returnArray['shipping_address'] = $shippingAddress;

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
			case "set_order_status":
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
			case "print_picklists":
				$orderIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$orderIds[] = $row['primary_identifier'];
				}
				$returnArray['order_ids'] = implode("|", $orderIds);
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
					$orderId = getFieldFromId("order_id", "orders", "order_id", $orderId, "date_completed is null");
					if (empty($orderId)) {
						ajaxResponse($returnArray);
						break;
					}
                    if(Order::markOrderCompleted($orderId, date("Y-m-d"), false)) {
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
					$returnArray['_capture_message'] = "Shipments cannot be created until all payments are captured";
				} else {
					$returnArray['_capture_message'] = "";
				}

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
					$returnArray['_capture_message'] = "Shipments cannot be created until all payments are captured";
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
				ajaxResponse($returnArray);
				break;
			case "resend_receipt":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_GET['order_id']);
				if (empty($orderId)) {
					$returnArray['error_message'] = "Order not found";
					ajaxResponse($returnArray);
					break;
				}
				$orderRow = getRowFromId("orders", "order_id", $orderId);
				$contactRow = Contact::getContact($orderRow['contact_id']);

				$shippingAddress = (empty($orderRow['address_id']) ? $contactRow : array_merge($contactRow, getRowFromId("addresses", "address_id", $orderRow['address_id'])));
				$substitutions = $shippingAddress;
				$substitutions['shipping_address_block'] = $shippingAddress['address_1'];
				if (!empty($shippingAddress['address_2'])) {
					$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . $shippingAddress['address_2'];
				}
				$shippingCityLine = $shippingAddress['city'] . (empty($shippingAddress['city']) || empty($shippingAddress['state']) ? "" : ", ") . $shippingAddress['state'];
				if (!empty($shippingAddress['postal_code'])) {
					$shippingCityLine .= (empty($shippingCityLine) ? "" : " ") . $shippingAddress['postal_code'];
				}
				if (!empty($shippingCityLine)) {
					$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . $shippingCityLine;
				}
				if (!empty($shippingAddress['country_id']) && $shippingAddress['country_id'] != 1000) {
					$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . getFieldFromId("country_name", "countries", "country_id", $shippingAddress['country_id']);
				}
				$substitutions = array_merge($substitutions, $orderRow);

				$cartTotal = 0;
				$cartTotalQuantity = 0;
				$substitutions['domain_name'] = getDomainName();
				$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
				$fflRequired = false;
				$resultSet = executeQuery("select * from order_items where order_id = ?", $orderId);
				while ($thisItem = getNextRow($resultSet)) {
					$cartTotal += ($thisItem['quantity'] * $thisItem['sale_price']);
					$cartTotalQuantity++;
					if ($fflRequiredProductTagId) {
						$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $thisItem['product_id'], "product_tag_id = ?", $fflRequiredProductTagId);
						if (!empty($productTagLinkId)) {
							$fflRequired = true;
						}
					}
				}
				$substitutions = array_merge(Order::getOrderItemsSubstitutions($orderId), $substitutions);

				$orderPayments = "";
				$orderPaymentsTable = "<table id='order_payments_table'><tr>" .
					"<th class='payment_method-header'>Payment Method</th>" .
					"<th class='payment-amount-header'>Amount</th></tr>";
				$resultSet = executeQuery("select * from order_payments where order_id = ?", $orderId);
				while ($thisPayment = getNextRow($resultSet)) {
					$orderPayments .= "<div class='order-payment-line'><span class='payment-method'>" . getFieldFromId("description", "payment_methods", "payment_method_id", $thisPayment['payment_method_id']) . "</span>" .
						"<span class='payment-amount'>" . number_format($thisPayment['amount'] + $thisPayment['shipping_charge'] + $thisPayment['tax_charge'] + $thisPayment['handling_charge'], 2, ".", ",") . "</span>" .
						"</div>";
					$orderPaymentsTable .= "<tr class='order-payment-row'><td class='payment-method'>" . getFieldFromId("description", "payment_methods", "payment_method_id", $thisPayment['payment_method_id']) . "</td>" .
						"<td class='payment-amount'>" . number_format($thisPayment['amount'] + $thisPayment['shipping_charge'] + $thisPayment['tax_charge'] + $thisPayment['handling_charge'], 2, ".", ",") . "</td></tr>";
				}
				$orderPaymentsTable .= "</table>";
				$substitutions['order_payments'] = $orderPayments;
				$substitutions['order_payments_table'] = $orderPaymentsTable;

				$orderTotal = $cartTotal + $orderRow['tax_charge'] + $orderRow['shipping_charge'] + $orderRow['handling_charge'] - $orderRow['order_discount'];

				$substitutions['order_id'] = $orderId;
				$substitutions['order_total'] = number_format($orderTotal, 2, ".", ",");
				$substitutions['tax_charge'] = number_format($orderRow['tax_charge'], 2, ".", ",");
				$substitutions['shipping_charge'] = number_format($orderRow['shipping_charge'], 2, ".", ",");
				$substitutions['handling_charge'] = number_format($orderRow['handling_charge'], 2, ".", ",");
				$substitutions['order_discount'] = $orderRow['order_discount'];
				$substitutions['cart_total'] = number_format($cartTotal, 2, ".", ",");
				$substitutions['cart_total_quantity'] = $cartTotalQuantity;
				if (empty($orderRow['donation_id'])) {
					$substitutions['donation_amount'] = "0.00";
					$substitutions['designation_code'] = "";
					$substitutions['designation_description'] = "";
				} else {
					$substitutions['donation_amount'] = number_format(getFieldFromId("amount", "donations", "donation_id", $orderRow['donation_id']), 2, ".", ",");
					$substitutions['designation_code'] = getFieldFromId("designation_code", "designations", "designation_id", getFieldFromId("designation_id", "donations", "donation_id", $orderRow['donation_id']));
					$substitutions['designation_description'] = getFieldFromId("description", "designations", "designation_id", getFieldFromId("designation_id", "donations", "donation_id", $orderRow['donation_id']));
				}
				$substitutions['order_total'] += $substitutions['donation_amount'];
				$substitutions['order_date'] = date("m/d/Y", strtotime($orderRow['order_time']));
				$fflRow = (new FFL($orderRow['federal_firearms_licensee_id']))->getFFLRow();
                $substitutions['ffl_name'] = (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']);
				$substitutions['ffl_phone_number'] = $fflRow['phone_number'];
				$substitutions['ffl_license_number'] = $fflRow['license_number'];
				$substitutions['ffl_license_number_masked'] = maskString($fflRow['license_number'], "#-##-XXX-XX-XX-#####");
				$substitutions['ffl_address'] = $fflRow['address_1'] . ", " . (empty($fflRow['address_2']) ? "" : $fflRow['address_2'] . ", ") . $fflRow['city'] . ", " . $fflRow['state'] . " " . $fflRow['postal_code'];

				$billingAddressRow = $contactRow;
				$resultSet = executeQuery("select * from order_payments left outer join accounts using (account_id) where order_id = ? and address_id is not null", $orderRow['order_id']);
				if ($row = getNextRow($resultSet)) {
					$billingAddressRow = getRowFromId("addresses", "address_id", $row['address_id']);
				}
				$substitutions['billing_address_block'] = $billingAddressRow['address_1'];
				if (!empty($billingAddressRow['address_2'])) {
					$substitutions['billing_address_block'] .= (empty($substitutions['billing_address_block']) ? "" : "<br>") . $billingAddressRow['address_2'];
				}
				$billingCityLine = $billingAddressRow['city'] . (empty($billingAddressRow['city']) || empty($billingAddressRow['state']) ? "" : ", ") . $billingAddressRow['state'];
				if (!empty($billingAddressRow['postal_code'])) {
					$billingCityLine .= (empty($billingCityLine) ? "" : " ") . $billingAddressRow['postal_code'];
				}
				if (!empty($billingCityLine)) {
					$substitutions['billing_address_block'] .= (empty($substitutions['billing_address_block']) ? "" : "<br>") . $billingCityLine;
				}
				if (!empty($billingAddressRow['country_id']) && $billingAddressRow['country_id'] != 1000) {
					$substitutions['billing_address_block'] .= (empty($substitutions['billing_address_block']) ? "" : "<br>") . getFieldFromId("country_name", "countries", "country_id", $billingAddressRow['country_id']);
				}

				$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_ORDER_CONFIRMATION",  "inactive = 0");
				if ($fflRequired && empty($orderRow['federal_firearms_licensee_id'])) {
					$substitutions['need_ffl_dealer'] = getFragment("RETAIL_STORE_NEED_FFL_DEALER");
				} else {
					$substitutions['need_ffl_dealer'] = "";
				}
				if (empty($shippingAddress['email_address'])) {
					$shippingAddress['email_address'] = $contactRow['email_address'];
				}
				$emailResult = sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_addresses" => $shippingAddress['email_address']));
				if ($emailResult) {
					$returnArray['info_message'] = "Receipt successfully resent";
				} else {
					$returnArray['error_message'] = "Error sending email";
				}
				ajaxResponse($returnArray);
				break;

			case "get_answer":
				$returnArray['content'] = getFieldFromId("content", "help_desk_answers", "help_desk_answer_id", $_GET['help_desk_answer_id'], ($GLOBALS['gUserRow']['superuser_flag'] ? "client_id is not null" : ""));
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
					executeQuery("insert into tasks (client_id,contact_id,description,detailed_description,date_completed,task_type_id,simple_contact_task) values " .
						"(?,?,?,?,now(),?,1)", $GLOBALS['gClientId'], $contactId, $subject, $body, $taskTypeId);
				} else {
					$returnArray['error_message'] = "Unable to send email";
				}
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
				$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
				$fflRequired = false;
				$orderItems = array();
				$shippedOrderItemStatusId = getFieldFromId("order_item_status_id", "order_item_statuses", "order_item_status_code", "SHIPPED");
				$resultSet = executeQuery("select * from order_items where order_id = ?", $_GET['order_id']);
				while ($row = getNextRow($resultSet)) {
					$productRow = ProductCatalog::getCachedProductRow($row['product_id']);
					if ($fflRequiredProductTagId) {
						$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $row['product_id'], "product_tag_id = ?", $fflRequiredProductTagId);
						if (!empty($productTagLinkId)) {
							$fflRequired = true;
						}
					}
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
					$addonSet = executeQuery("select * from product_addons join order_item_addons using (product_addon_id) where order_item_id = ?", $row['order_item_id']);
					while ($addonRow = getNextRow($addonSet)) {
						$salePrice = ($addonRow['quantity'] <= 1 ? $addonRow['sale_price'] : $addonRow['sale_price'] * $addonRow['quantity']);
						$row['product_description'] .= "<br>" . $addonRow['description'] . ($addonRow['quantity'] <= 1 ? "" : " (Qty: " . $addonRow['quantity'] . ")") . " - $" . number_format($salePrice, 2, ".", "");
					}
					$row['virtual_product'] = getFieldFromId("virtual_product", "products", "product_id", $row['product_id']);
					$row['serializable'] = $productRow['serializable'];
					$upcCode = getFieldFromId("upc_code", "product_data", "product_id", $row['product_id']);
					if (!empty($upcCode)) {
						$row['product_description'] .= "<br><span class='upc-code'>UPC: " . $upcCode . "</span>";
					}
					$row['total_price'] = $row['sale_price'] * $row['quantity'];
					$row['sale_price'] = number_format($row['sale_price'], 2, ".", ",");
					$row['total_price'] = number_format($row['total_price'], 2, ".", ",");
					if ($row['virtual_product']) {
						$row['shipped_quantity'] = $row['quantity'];
						if (!empty($shippedOrderItemStatusId)) {
							$row['order_item_status_id'] = $shippedOrderItemStatusId;
						}
					} else {
						$shippedSet = executeQuery("select sum(quantity) from order_shipment_items where order_item_id = ? and " .
							"order_shipment_id in (select order_shipment_id from order_shipments where location_id is not null and " .
							"location_id not in (select location_id from locations where inactive = 0 and product_distributor_id is not null)) and " .
							"order_shipment_id in (select order_shipment_id from order_shipments where secondary_shipment = 0)", $row['order_item_id']);
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

					$locationSet = executeQuery("select * from locations where client_id = ? and inactive = 0 and location_id in (select location_id from user_locations where user_id = " . $GLOBALS['gUserId'] . ") and location_id in " .
						"(select location_id from product_inventories where product_distributor_id is null and product_id = ? and quantity > 0)", $GLOBALS['gClientId'], $row['product_id']);
					$productInventories = array();
					while ($locationRow = getNextRow($locationSet)) {
						$productInventoryRow = getRowFromId("product_inventories", "product_id", $row['product_id'], "location_id = ?", $locationRow['location_id']);
						if (empty($productInventoryRow)) {
							$productInventoryRow['quantity'] = 0;
						}
						$cost = ProductCatalog::getLocationBaseCost($row['product_id'], $locationRow['location_id'], $productInventoryRow,false);
						if ($productInventoryRow['quantity'] > 0) {
							$description = $locationRow['description'];
							$sortOrder = "";
							if (!empty($locationRow['product_distributor_id'])) {
								$description = getReadFieldFromId("description", "product_distributors", "product_distributor_id", $locationRow['product_distributor_id']);
								$sortOrder = getReadFieldFromId("sort_order", "product_distributors", "product_distributor_id", $locationRow['product_distributor_id']);
							}
							$productInventories[] = array("description" => $description, "quantity" => $productInventoryRow['quantity'], "cost" => $cost, "sort_order" => $sortOrder);
						}
					}

					$row['product_inventories'] = "";
					if (!empty($productInventories)) {
						usort($productInventories, array($this, "sortPrices"));
						foreach ($productInventories as $thisPrice) {
							$row['product_inventories'] .= (empty($row['product_inventories']) ? "" : "<br>") . $thisPrice['description'] . " - " . $thisPrice['quantity'] . (empty($thisPrice['cost']) ? "" : "/$" . number_format($thisPrice['cost'], 2, ".", ","));
						}
						$row['product_inventories'] = "<div class='product-inventory'>" . $row['product_inventories'] . "</div>";
					}
					if (!empty($row['deleted'])) {
						$row['order_item_status_id'] = -1;
					}
					$orderItems[] = $row;
				}
				$returnArray['ffl_required'] = $fflRequired;
				$returnArray['order_items'] = $orderItems;

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
			case "add_file":
				$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['primary_id']);
				if (empty($_POST['order_files_description']) || empty($_FILES['order_files_file_id_file']) || empty($orderId)) {
					$returnArray['error_message'] = "Missing information";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$fileId = createFile("order_files_file_id_file");
				$resultSet = executeQuery("insert into order_files (order_id,description,file_id) values (?,?,?)",
					$orderId, $_POST['order_files_description'], $fileId);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = "Unable to create file: " . $resultSet['sql_error'];
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				} else {
					$returnArray['order_file'] = array("file_id" => $fileId, "description" => $_POST['order_files_description'], "order_file_id" => $resultSet['insert_id']);
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
				}
				ajaxResponse($returnArray);
				break;
			case "save_order_status":
				$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_id", $_GET['order_status_id']);
				Order::updateOrderStatus($_GET['order_id'], $orderStatusId);
				$returnArray['order_status_id'] = $orderStatusId;
				ajaxResponse($returnArray);
				break;
			case "save_order_item_status":
				$orderItemDataTable = new DataTable("order_items");
				$orderItemDataTable->setSaveOnlyPresent(true);
				$orderItemId = getFieldFromId("order_item_id","order_items","order_item_id",$_GET['order_item_id'],"order_id = ?",$_GET['order_id']);
				if (empty($orderItemId)) {
					$returnArray['error_message'] = "Invalid Order Item";
					ajaxResponse($returnArray);
					break;
				}
				if ($_GET['order_item_status_id'] == -1) {
					$orderItemDataTable->saveRecord(array("name_values" => array("deleted" => 1), "primary_id" => $orderItemId));
				} else {
					$orderItemDataTable->saveRecord(array("name_values" => array("deleted" => 0, "order_item_status_id"=>$_GET['order_item_status_id']), "primary_id" => $orderItemId));
				}
				$orderItemStatusCode = getFieldFromId("order_item_status_code", "order_item_statuses", "order_item_status_id", $_GET['order_item_status_id']);
				switch ($orderItemStatusCode) {
					case "BACKORDER":
						$orderItemRow = getRowFromId("order_items", "order_item_id", $orderItemId);
						$productId = $orderItemRow['product_id'];
						$productCatalog = new ProductCatalog();
						$emailAddress = getPreference("BACKORDERED_ITEM_AVAILABLE_NOTIFICATION");
						if (empty($emailAddress)) {
							$emailAddress = $GLOBALS['gUserRow']['email_address'];
						}
						$totalInventory = $productCatalog->getInventoryCounts(true, $productId, false, array("ignore_backorder" => true));
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
				$dateFrom = date("Y-m-d", strtotime($_GET['start_date']));
				$dateTo = date("Y-m-d", strtotime($_GET['end_date']));
				$shippingMethodIds = array();
				$resultSet = executeQuery("select * from shipping_methods where client_id = ? and pickup = 1 and location_id is not null" .
					($GLOBALS['gUserRow']['full_client_access'] ? "" : " and location_id in (select location_id from user_locations where user_id = " . $GLOBALS['gUserId'] . ")"), $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$shippingMethodIds[] = $row['shipping_method_id'];
				}
				if (empty($shippingMethodIds)) {
					$shippingMethodIds[] = 0;
				}

				//get new customers
				$resultSet = executeQuery("select count(*) from contacts where client_id = ? and contact_id in (select contact_id from orders where deleted = 0 and client_id = ? and shipping_method_id in (" . implode(",", $shippingMethodIds) . ") and " .
					"date_created between ? and ?)", $GLOBALS['gClientId'], $GLOBALS['gClientId'], $dateFrom, $dateTo);
				if ($row = getNextRow($resultSet)) {
					$returnArray['customer_count_new'] = $row['count(*)'];
				}

				//get total customers
				$resultSet = executeQuery("select count(*) from contacts where client_id = ? and contact_id in (select contact_id from orders where deleted = 0 and shipping_method_id in (" . implode(",", $shippingMethodIds) . ") and " .
					"date(order_time) between ? and ? and client_id = ?)", $GLOBALS['gClientId'], $dateFrom, $dateTo, $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['customer_count_total'] = $row['count(*)'];
				}

				//get new orders
				$resultSet = executeQuery("select count(*) from orders where deleted = 0 and client_id = ? and shipping_method_id in (" . implode(",", $shippingMethodIds) . ") and " .
					"date(order_time) between ? and ?", $GLOBALS['gClientId'], $dateFrom, $dateTo);
				if ($row = getNextRow($resultSet)) {
					$returnArray['order_count_new'] = $row['count(*)'];
				}

				//get total orders
				$resultSet = executeQuery("select count(*) from orders where deleted = 0 and client_id = ? and shipping_method_id in (" . implode(",", $shippingMethodIds) . ")", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['order_count_total'] = $row['count(*)'];
				}

				//get total revenue
				$totalRevenue = 0;
				$resultSet = executeQuery("select shipping_charge,tax_charge,handling_charge,(select sum(order_items.sale_price * order_items.quantity) from order_items where " .
					"deleted = 0 and order_id = orders.order_id) as item_total from orders where " .
					"orders.client_id = ? and date(order_time) between ? and ? and orders.deleted = 0 and shipping_method_id in (" . implode(",", $shippingMethodIds) . ")", $GLOBALS['gClientId'], $dateFrom, $dateTo);
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
					" or order_item_id in (select order_item_id from order_item_serial_numbers where serial_number like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%") . "))";
				$this->iDataSource->addFilterWhere($whereStatement);
			}
		}
	}

	function sortPrices($a, $b) {
		if ($a['cost'] == $b['cost']) {
			if ($a['sort_order'] == $b['sort_order']) {
				return 0;
			}
			return ($a['sort_order'] > $b['sort_order']) ? 1 : -1;
		}
		return ($a['cost'] > $b['cost']) ? 1 : -1;
	}

	function onLoadJavascript() {
		$pickListPrintedOrderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", "PICK_LIST_PRINTED");
		?>
        <script>
            $(document).on("click", "#change_contact", function () {
                $("#change_full_name").val($("#full_name").find("a").text());
                $("#change_address_1").val($("#address_1").val());
                $("#change_address_2").val($("#address_2").val());
                $("#change_city").val($("#city").val());
                $("#change_state").val($("#state").val());
                $("#change_postal_code").val($("#postal_code").val());
                $('#_change_contact_dialog').dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 800,
                    title: 'Change Contact Information',
                    buttons: {
                        Save: function (event) {
                            if ($("#_change_contact_form").validationEngine("validate")) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=change_contact&order_id=" + $("#primary_id").val(), $("#_change_contact_form").serialize(), function(returnArray) {
                                    if (!("error_message" in returnArray)) {
                                        $("#full_name").find("a").text(returnArray['full_name']);
                                        $("#address_1").val(returnArray['address_1']);
                                        $("#address_2").val(returnArray['address_2']);
                                        $("#city").val(returnArray['city']);
                                        $("#state").val(returnArray['state']);
                                        $("#postal_code").val(returnArray['postal_code']);
                                        $("#shipping_address").html(returnArray['shipping_address']);
                                        $("#_change_contact_dialog").dialog('close');
                                    }
                                });
                            }
                        },
                        Cancel: function (event) {
                            $("#_change_contact_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("blur", ".serial-number", function () {
                const rowNumber = $(this).closest("tr").data("row_number");
                const primaryId = $(this).closest("tr").find(".editable-list-primary-id").val();
                const primaryIdElement = $(this).closest("tr").find(".editable-list-primary-id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_serial_number", { order_id: $("#primary_id").val(), order_item_id: $(this).closest(".order-item").data("order_item_id"), serial_number: $(this).val(), primary_id: primaryId }, function(returnArray) {
                    if ("primary_id" in returnArray) {
                        primaryIdElement.val(returnArray['primary_id']);
                    }
                });
            });
            $(document).on("click", ".order-tag", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_order_tag&order_id=" + $("#primary_id").val() + "&order_tag_id=" + $(this).data("order_tag_id") + "&checked=" + ($(this).prop("checked") ? "true" : ""));
            });
            $(document).on("tap click", "#_pick_list_button", function () {
                const primaryId = $("#primary_id").val();
                window.open("/print-pick-list?order_id=" + primaryId + "&printable=true");
				<?php if (!empty($pickListPrintedOrderStatusId)) { ?>
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_order_status&order_id=" + primaryId + "&order_status_id=<?= $pickListPrintedOrderStatusId ?>");
				<?php } ?>
                return false;
            });
            $(document).on("tap click", "#_pickup_ready_button", function () {
                const primaryId = $("#primary_id").val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mark_ready_for_pickup&order_id=" + primaryId, function(returnArray) {
                    if ("order_status_id" in returnArray) {
                        $("#order_status_id").val(returnArray['order_status_id']);
                    }
                });
                return false;
            });
            $(document).on("tap click", "#_picked_up_button", function () {
                const primaryId = $("#primary_id").val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=order_picked_up&order_id=" + primaryId, function(returnArray) {
                    $("#_list_button").trigger("click");
                });
                return false;
            });
            $(document).on("click", ".print-pick-list", function (event) {
                const primaryId = $(this).closest(".data-row").data("primary_id");
                window.open("/print-pick-list?order_id=" + primaryId + "&printable=true");
				<?php if (!empty($pickListPrintedOrderStatusId)) { ?>
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_order_status&order_id=" + primaryId + "&order_status_id=<?= $pickListPrintedOrderStatusId ?>");
				<?php } ?>
                event.stopPropagation();
                event.preventDefault();
                return false;
            });
            $(document).on("click", ".ready-pickup", function (event) {
                const primaryId = $(this).closest(".data-row").data("primary_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mark_ready_for_pickup&order_id=" + primaryId);
                event.stopPropagation();
                event.preventDefault();
                return false;
            });
            $(document).on("click", ".order-picked-up", function (event) {
                const primaryId = $(this).closest(".data-row").data("primary_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=order_picked_up&order_id=" + primaryId);
                event.stopPropagation();
                event.preventDefault();
                return false;
            });
            $(document).on("click", ".delete-payment", function () {
                const $thisPayment = $(this).closest("tr");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_payment&order_id=" + $("#primary_id").val() + "&order_payment_id=" + $thisPayment.data("order_payment_id"), function(returnArray) {
                    if (!("error_message" in returnArray)) {
                        $thisPayment.addClass("deleted-payment");
                        if (!$("#show_deleted_payments").prop("checked")) {
                            $thisPayment.addClass("hidden");
                        }
                        $thisPayment.find(".not-captured").html(returnArray['not_captured']);
                        $("#_capture_message").html(returnArray['_capture_message']);
                    }
                });
            });
            $(document).on("click", ".undelete-payment", function () {
                const $thisPayment = $(this).closest("tr");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=undelete_payment&order_id=" + $("#primary_id").val() + "&order_payment_id=" + $thisPayment.data("order_payment_id"), function(returnArray) {
                    if (!("error_message" in returnArray)) {
                        $thisPayment.removeClass("deleted-payment");
                        $thisPayment.removeClass("hidden");
                        $("#_capture_message").html(returnArray['_capture_message']);
                        $thisPayment.find(".not-captured").html(returnArray['not_captured']);
                    }
                });
            });
            $(document).on("click", "#show_deleted_payments", function () {
                if ($(this).prop('checked')) {
                    $("#order_payments").find("tr.deleted-payment").removeClass("hidden");
                } else {
                    $("#order_payments").find("tr.deleted-payment").addClass("hidden");
                }
            });
            $(document).on("click", "#add_payment", function () {
                $('#_payment_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 800,
                    title: 'Add Payment',
                    buttons: {
                        Save: function (event) {
                            if ($("#_payment_form").validationEngine("validate")) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_payment&order_id=" + $("#primary_id").val(), $("#_payment_form").serialize(), function(returnArray) {
                                    if ("order_payments" in returnArray) {
                                        $("#_payment_dialog").dialog('close');
                                        $("#_payment_form").clearForm();
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
                                    }
                                });
                            }
                        },
                        Cancel: function (event) {
                            $("#_payment_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
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
            $(document).on("change", "#help_desk_answer_id", function () {
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_answer&help_desk_answer_id=" + $(this).val(), function(returnArray) {
                        if ("content" in returnArray) {
                            $("#email_body").val(returnArray['content']);
                        }
                    });
                }
            });
            $(document).on("click", "#print_receipt", function () {
                const orderId = $("#primary_id").val();
                window.open("/admin-order-receipt?order_id=" + orderId + "&printable=true");
                return false;
            });
            $(document).on("click", "#add_file", function () {
                if (empty($("#order_files_description").val()) || empty($("#order_files_file_id_file").val())) {
                    displayErrorMessage("Description and file are both required");
                    return false;
                }
                $("body").addClass("waiting-for-ajax");
                $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=add_file").attr("method", "POST").attr("target", "post_iframe").submit();
                $("#_post_iframe").off("load");
                $("#_post_iframe").on("load", function () {
                    $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                    const returnText = $(this).contents().find("body").html();
                    const returnArray = processReturn(returnText);
                    if (returnArray === false) {
                        enableButtons($("#_submit_form"));
                        return;
                    }
                    if (!("error_message" in returnArray)) {
                        $("#order_files_description").val("");
                        $("#order_files_file_id_file").val("");
                        let orderFileBlock = $("#_order_file_template").html();
                        for (const j in returnArray['order_file']) {
                            const re = new RegExp("%" + j + "%", 'g');
                            orderFileBlock = orderFileBlock.replace(re, returnArray['order_file'][j]);
                        }
                        $("#order_files").append(orderFileBlock);
                        $(".no-order-files").remove();
                    }
                });
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
                    width: 600,
                    title: 'Send Email to Customer',
                    buttons: {
                        Send: function (event) {
                            if ($("#_send_email_form").validationEngine("validate")) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_email&order_id=" + $("#primary_id").val(), $("#_send_email_form").serialize(), function(returnArray) {
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
            $(document).on("click", "#reopen_order", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=reopen_order", { order_id: $("#primary_id").val() }, function(returnArray) {
                    document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&primary_id=" + $("#primary_id").val();
                });
                return false;
            });
            $(document).on("click", "#mark_completed", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mark_completed", { order_id: $("#primary_id").val() }, function(returnArray) {
                    document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                });
                return false;
            });
            $(document).on("click", "#add_note", function () {
                if (!empty($("#content").val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=add_note", { content: $("#content").val(), order_id: $("#primary_id").val(), public_access: $("#public_access").prop("checked") ? 1 : 0 }, function(returnArray) {
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
            $(document).on("click", ".capture-payment", function () {
                const $orderPaymentRow = $(this).closest("tr");
                const orderPaymentId = $orderPaymentRow.data("order_payment_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=capture_payment&order_payment_id=" + orderPaymentId + "&order_id=" + $("#primary_id").val(), function(returnArray) {
                    if (!("error_message" in returnArray)) {
                        $orderPaymentRow.find(".not-captured").html("Done");
                        $orderPaymentRow.find(".transaction-identifier").html(returnArray['transaction_identifier']);
                        $("#_capture_message").html(returnArray['_capture_message']);
                    }
                });
                return false;
            });
            $("#_list_button").text("Return To List");
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
                                    loadAjaxRequest("<?php echo $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_statistics&start_date=" + $("#start_date").val() + "&end_date=" + $("#end_date").val(), function(returnArray) {
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
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_statistics&start_date=" + startDate + "&end_date=" + endDate, function(returnArray) {
                        for (const i in returnArray) {
                            $("#" + i).html(returnArray[i]);
                        }
                    });
                }
                return false;
            });
            $(".statistics-filter-button.active").trigger("click");
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
                if (actionName === "set_status") {
                    $('#_set_status_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Set Status',
                        buttons: {
                            Save: function (event) {
                                if (!empty($("#order_status_id"))) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_order_status", $("#_set_status_form").serialize(), function(returnArray) {
                                        if (!("error_message" in returnArray)) {
                                            getDataList();
                                        }
                                    });
                                    $("#_set_status_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_set_status_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                if (actionName === "print_picklists") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=print_picklists", function(returnArray) {
                        if ("order_ids" in returnArray) {
                            window.open("/print-pick-list?order_id=" + returnArray['order_ids'] + "&printable=true");
                        }
                    });
                }
                if (actionName === "mark_selected_completed") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mark_selected_completed", function(returnArray) {
                        if (!("error_message" in returnArray)) {
                            getDataList();
                        }
                    });
                }
                if (actionName === "mark_selected_not_completed") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mark_selected_not_completed", function(returnArray) {
                        if (!("error_message" in returnArray)) {
                            getDataList();
                        }
                    });
                }
            }

            function afterGetRecord(returnArray) {
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

                $("#help_desk_entries").find(".help-desk-entry").remove();
                for (let i in returnArray['help_desk_entries']) {
                    let helpDeskEntryBlock = $("#_help_desk_entry_template").html();
                    for (let j in returnArray['help_desk_entries'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        helpDeskEntryBlock = helpDeskEntryBlock.replace(re, returnArray['help_desk_entries'][i][j]);
                    }
                    $("#help_desk_entries").append(helpDeskEntryBlock);
                }
                if ($("#help_desk_entries").find(".help-desk-entry").length === 0) {
                    $("#help_desk_entries").append("<tr class='help-desk-entry no-help-desk-entries'><td colspan='100'>No Help Desk Tickets</td></tr>");
                }

                $("#order_files").find(".order-file").remove();
                for (const i in returnArray['order_files']) {
                    let orderFileBlock = $("#_order_file_template").html();
                    for (const j in returnArray['order_files'][i]) {
                        const re = new RegExp("%" + j + "%", 'g');
                        orderFileBlock = orderFileBlock.replace(re, returnArray['order_files'][i][j]);
                    }
                    $("#order_files").append(orderFileBlock);
                }
                if ($("#order_files").find(".order-file").length === 0) {
                    $("#order_files").append("<tr class='order-file no-order-files'><td colspan='100'>No Files yet</td></tr>");
                }

                if (!empty(returnArray['date_completed']['data_value']) || !empty(returnArray['deleted']['data_value'])) {
                    $("#_maintenance_form").find("input[type=text]").prop("readonly", true);
                    $("#_maintenance_form").find("select").prop("disabled", true);
                    $("#_maintenance_form").find("textarea").prop("readonly", true);
                    $("#_maintenance_form").find("button").not(".keep-visible").addClass("hidden");
                    $("#_content_row").addClass("hidden");
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
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_order_items&order_id=" + $("#primary_id").val(), function(returnArray) {
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
                        for (const i in returnArray) {
                            if (i.indexOf("order_item_serial_numbers_") === 0) {
                                for (const j in returnArray[i]) {
                                    addEditableListRow(i, returnArray[i][j]);
                                }
                            }
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
        updateFieldById("deleted", (!empty($_POST['deleted']) ? "0" : "1"), "orders", "order_id", $_POST['primary_id'], "client_id = ?", $GLOBALS['gClientId']);
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
		$resultSet = executeQuery("select * from order_tags where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$orderTagLinkId = getFieldFromId("order_tag_link_id", "order_tag_links", "order_tag_id", $row['order_tag_id'], "order_id = ?", $returnArray['primary_id']['data_value']);
			$returnArray['order_tag_id_' . $row['order_tag_id']] = array("data_value" => (empty($orderTagLinkId) ? "" : "1"), "crc_value" => getCrcValue((empty($orderTagLinkId) ? "" : "1")));
		}

		$contactIdentifiers = "";
		$resultSet = executeQuery("select * from contact_identifiers join contact_identifier_types using (contact_identifier_type_id) where contact_id = ?", $returnArray['contact_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$contactIdentifiers .= "<p>" . htmlText($row['description']) . ": " . $row['identifier_value'] . "</p>";
		}
		$returnArray['contact_identifiers'] = array("data_value" => $contactIdentifiers);

		$fullName = $returnArray['full_name']['data_value'];
		$returnArray['full_name'] = array("data_value" => "<a target='_blank' href='/contactmaintenance.php?url_page=show&primary_id=" . $returnArray['contact_id']['data_value'] . "&clear_filter=true'>" . $returnArray['full_name']['data_value'] . "</a>");
		$promotionId = getFieldFromId("promotion_id", "order_promotions", "order_id", $returnArray['primary_id']['data_value']);
		$returnArray['order_promotion'] = array("data_value" => (empty($promotionId) ? "" : "Used promotion '" . getFieldFromId("description", "promotions", "promotion_id", $promotionId) . "'"));

		if (!empty($returnArray['date_completed']['data_value'])) {
			$returnArray['order_status_display'] = array("data_value" => "Completed on " . date("m/d/Y", strtotime($returnArray['date_completed']['data_value'])));
			$returnArray['date_completed_wrapper'] = array("data_value" => "<button class='keep-visible' id='reopen_order'>Reopen Order</button>");
		} else {
			$returnArray['order_status_display'] = array("data_value" => getFieldFromId("description", "order_status", "order_status_id", $returnArray['order_status_id']['data_value']));
			$returnArray['date_completed_wrapper'] = array("data_value" => "<button id='mark_completed'>Mark Order Completed</button>");
		}
		$returnArray['email_address'] = array("data_value" => getFieldFromId("email_address", "contacts", "contact_id", $returnArray['contact_id']['data_value']));
		$returnArray['billing_address'] = array("data_value" => "");
		$accountId = getFieldFromId("account_id", "order_payments", "order_id", $returnArray['primary_id']['data_value']);
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
			$billingAddress = $addressRow['address_1'] . "<br>" . (empty($addressRow['address_2']) ? "" : $addressRow['address_2'] . "<br>") . $addressRow['city'] . ", " . $addressRow['state'] . " " . $addressRow['postal_code'];
			if ($addressRow['country_id'] != 1000) {
				$billingAddress .= "<br>" . getFieldFromId("country_name", "countries", "country_id", $addressRow['country_id']);
			}
			$billingAddress .= "</p><p>" . getFieldFromId("description", "payment_methods", "payment_method_id", $accountRow['payment_method_id']) . " - " . $accountRow['account_number'];
			$returnArray['billing_address'] = array("data_value" => $billingAddress);
		}
		if (empty($returnArray['address_id']['data_value'])) {
			$addressRow = Contact::getContact($returnArray['contact_id']['data_value']);
		} else {
			$addressRow = getRowFromId("addresses", "address_id", $returnArray['address_id']['data_value']);
		}
		if (strlen($fullName) > 20) {
			$fullName = str_replace(", ", "<br>", $fullName);
		}
		$returnArray['address_1'] = array("data_value" => $addressRow['address_1']);
		$returnArray['address_2'] = array("data_value" => $addressRow['address_2']);
		$returnArray['city'] = array("data_value" => $addressRow['city']);
		$returnArray['state'] = array("data_value" => $addressRow['state']);
		$returnArray['postal_code'] = array("data_value" => $addressRow['postal_code']);
		$shippingAddress = $fullName . "<br>" . $addressRow['address_1'] . "<br>" . (empty($addressRow['address_2']) ? "" : $addressRow['address_2'] . "<br>") . $addressRow['city'] . ", " . $addressRow['state'] . "," . $addressRow['postal_code'];
		if ($addressRow['country_id'] != 1000) {
			$shippingAddress .= "<br>" . getFieldFromId("country_name", "countries", "country_id", $addressRow['country_id']);
		}
		$shippingAddress .= "<br><a target='_blank' href='https://www.google.com/maps?q=" . urlencode($addressRow['address_1'] . ", " . (empty($addressRow['address_2']) ? "" : $addressRow['address_2'] . ", ") . $addressRow['city'] . ", " . $addressRow['state'] . " " . $addressRow['postal_code']) . "'>Show on map</a>";
		$returnArray['shipping_address'] = array("data_value" => $shippingAddress);
		$returnArray['shipping_method_display'] = array("data_value" => getFieldFromId("description", "shipping_methods", "shipping_method_id", $returnArray['shipping_method_id']['data_value']));
		$returnArray['order_method_display'] = array("data_value" => getFieldFromId("description", "order_methods", "order_method_id", $returnArray['order_method_id']['data_value']));

		$orderItemTotal = 0;
		$resultSet = executeQuery("select * from order_items where order_id = ?", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$orderItemTotal += $row['sale_price'] * $row['quantity'];
		}

		$openInvoices = false;
		$notCaptured = false;
		$orderPayments = array();
		$resultSet = executeQuery("select * from order_payments where order_id = ? order by payment_time", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['not_captured'])) {
				$notCaptured = true;
			}
			if (!empty($row['invoice_id'])) {
				$invoiceId = getFieldFromId("invoice_id", "invoices", "invoice_id", $row['invoice_id'], "inactive = 0");
				if (!empty($invoiceId)) {
					$dateCompleted = getFieldFromId("date_completed", "invoices", "invoice_id", $row['invoice_id']);
					if (empty($dateCompleted)) {
						$openInvoices = true;
					}
				}
			}
			$row['payment_time'] = date("m/d/Y g:i a", strtotime($row['payment_time']));
			$row['not_captured'] = (empty($row['deleted']) ? (empty($row['not_captured']) ? "Done" : "<button class='capture-payment'>Capture Now</button>") : "");
			$row['payment_method'] = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']);
			$fullName = getFieldFromId("full_name", "accounts", "account_id", $row['account_id']);
			$row['account_number'] = (empty($row['account_id']) ? $row['reference_number'] : (empty($fullName) ? "" : $fullName . ", ") . substr(getFieldFromId("account_number", "accounts", "account_id", $row['account_id']), -8));
			$row['total_amount'] = number_format($row['amount'] + $row['shipping_charge'] + $row['tax_charge'] + $row['handling_charge'], 2, ".", ",");
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
			$returnArray['payment_warning'] = "No Payments have been made for this order";
		}
		$returnArray['order_payments'] = $orderPayments;
		$returnArray['not_captured'] = $notCaptured;

		$touchpoints = array();
		$resultSet = executeQuery("select task_id,task_type_id,description,detailed_description,date_completed from tasks where contact_id = ? order by task_id desc", $returnArray['contact_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$row['task_type'] = getFieldFromId("description", "task_types", "task_type_id", $row['task_type_id']);
			$row['date_completed'] = (empty($row['date_completed']) ? "" : date("m/d/Y", strtotime($row['date_completed'])));
			$touchpoints[] = $row;
		}
		$returnArray['touchpoints'] = $touchpoints;

		$helpDeskEntries = array();
		$resultSet = executeQuery("select description,time_submitted,time_closed from help_desk_entries where contact_id = ? order by time_submitted desc", $returnArray['contact_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$row['date_submitted'] = date("m/d/Y", strtotime($row['time_submitted']));
			$row['date_closed'] = (empty($row['time_closed']) ? "" : date("m/d/Y", strtotime($row['time_closed'])));
			$helpDeskEntries[] = $row;
		}
		$returnArray['help_desk_entries'] = $helpDeskEntries;

		$orderNotes = array();
		$resultSet = executeQuery("select * from order_notes where order_id = ? order by time_submitted desc", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$row['time_submitted'] = date("m/d/Y g:ia", strtotime($row['time_submitted']));
			$row['user_id'] = getUserDisplayName($row['user_id']);
			$row['public_access'] = (empty($row['public_access']) ? "" : "YES");
			$orderNotes[] = $row;
		}
		$returnArray['order_notes'] = $orderNotes;

		$orderFiles = array();
		$resultSet = executeQuery("select * from order_files where order_id = ?", $returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$orderFiles[] = $row;
		}
		$returnArray['order_files'] = $orderFiles;

		$returnArray['ip_address_message']['data_value'] = $returnArray['ip_address']['data_value'];
		if (!empty($returnArray['ip_address']['data_value'])) {
			$ipAddress = $returnArray['ip_address']['data_value'];
			$ipAddressData = getRowFromId("ip_address_metrics", "ip_address", $ipAddress);
			if (empty($ipAddressData)) {
				$ipAddressRaw = getCurlReturn("http://ip-api.com/json/" . $ipAddress);
				if (empty($ipAddressRaw)) {
					$ipAddressData = array();
				} else {
					$ipAddressData = json_decode($ipAddressRaw, true);
				}
				if (!empty($ipAddressData)) {
					executeQuery("insert ignore into ip_address_metrics (ip_address,country_id,city,state,postal_code,latitude,longitude) values (?,?,?,?,?, ?,?)",
						$ipAddress, getFieldFromId("country_id", "countries", "country_code", $ipAddressData['countryCode']), $ipAddressData['city'], $ipAddressData['region'],
						$ipAddressData['zip'], $ipAddressData['lat'], $ipAddressData['lon']);
					$ipAddressData = getRowFromId("ip_address_metrics", "ip_address", $ipAddress);
				}
			}
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
            #jquery_templates {
                display: none;
            }

            .data-list-icon {
                font-size: 1.2rem;
                margin-right: 10px;
            }

            .data-list-icon:hover {
                color: rgb(150, 150, 150);
            }

            #_order_header_section {
                margin-top: 20px;
            }

            #_capture_message, #_invoice_message {
                color: rgb(192, 0, 0);
            }

            .delete-payment {
                cursor: pointer;
                font-size: 1rem;
            }

            .undelete-payment {
                display: none;
                cursor: pointer;
                font-size: 1rem;
            }

            .delete-payment:hover {
                color: rgb(200, 200, 200);
            }

            .undelete-payment:hover {
                color: rgb(200, 200, 200);
            }

            tr.order-payment.deleted-payment .undelete-payment {
                display: inline-block;
            }

            tr.order-payment.deleted-payment .delete-payment {
                display: none;
            }

            tr.order-payment.deleted-payment td {
                text-decoration: line-through;
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

            #_main_content p#full_name {
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
                padding: 10px;
            }

            table.order-information td {
                background-color: rgb(240, 240, 240);
                padding: 10px;
            }

            .product-description {
                font-size: .8rem;
                line-height: 1.2;
            }

            .upc-code {
                color: rgb(150, 150, 150);
                font-weight: 300;
                font-size: .8rem;
            }

            #order_status_id {
                min-width: 200px;
                width: 200px;
                max-width: 100%;
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

            #order_filters button {
                padding: 5px 15px;
            }

            .basic-form-line.serial-number-wrapper {
                margin-bottom: 0;
                margin-top: 10px;
            }

            #help_desk_answer_id {
                width: 500px;
            }

            .dialog-box .basic-form-line {
                white-space: nowrap;
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
            @media only screen and (max-width: 1000px) {
                #_order_header_section {
                    display: none;
                }

                #statistics_block {
                    display: none;
                }
            }
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

        <div id="_change_contact_dialog" class="dialog-box">
            <p>Change contact name & address for this order. This cannot be undone, so be sure.</p>
            <p class='error-message'></p>
            <form id="_change_contact_form">
				<?php
				echo createFormControl("orders", "full_name", array("column_name" => "change_full_name", "not_null" => true, "form_label" => "Full Name"));
				echo createFormControl("contacts", "address_1", array("column_name" => "change_address_1", "not_null" => true, "form_label" => "Address 1"));
				echo createFormControl("contacts", "address_2", array("column_name" => "change_address_2", "not_null" => false, "form_label" => "Address 2"));
				echo createFormControl("contacts", "city", array("column_name" => "change_city", "not_null" => true, "form_label" => "City"));
				echo createFormControl("contacts", "state", array("column_name" => "change_state", "not_null" => true, "form_label" => "State"));
				echo createFormControl("contacts", "postal_code", array("column_name" => "change_postal_code", "not_null" => true, "form_label" => "Postal_code"));
				?>
            </form>
        </div>

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
                    <label class="required-label">Payment Date</label>
                    <input tabindex="10" type="text" class="validate[required,custom[date]] datepicker" size="12" id="payment_time" name="payment_time">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
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

        <div id="_confirm_email_dialog" class="dialog-box">
            Are you sure you want to resend this tracking email?
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
				<?php
				$resultSet = executeQuery("select * from help_desk_answers where help_desk_type_id is null and client_id = ?" . ($GLOBALS['gUserRow']['superuser_flag'] ? " or client_id = " . $GLOBALS['gDefaultClientId'] : "") . " order by description", $GLOBALS['gClientId']);
				if ($resultSet['row_count'] > 0) {
					?>
                    <div class="basic-form-line">
                        <label>Standard Answers</label>
                        <select id="help_desk_answer_id">
                            <option value="">[Select]</option>
							<?php
							while ($row = getNextRow($resultSet)) {
								?>
                                <option value="<?= $row['help_desk_answer_id'] ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
					<?php
				}
				?>
                <div class="basic-form-line" id="_email_subject_row">
                    <label for="email_subject" class="required-label">Subject</label>
                    <input type="text" class="validate[required]" maxlength="255" id="email_subject" name="email_subject">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_email_body_row">
                    <label for="email_body" class="required-label">Content</label>
                    <textarea class="validate[required]" id="email_body" name="email_body"></textarea>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

            </form>
        </div> <!-- confirm_shipment_dialog -->
		<?php
	}

	function jqueryTemplates() {
		?>
        <table>
            <tbody id="_order_item_template">
            <tr class="order-item" id="order_item_%order_item_id%" data-order_item_id="%order_item_id%">
                <td class='product-description'>
                    <a href="/productmaintenance.php?url_page=show&primary_id=%product_id%&clear_filter=true" target="_blank">%product_description%</a>
                    <div class="basic-form-line custom-control-no-help custom-control-form-line hidden serial-number-wrapper">
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
                <td class="inventory-quantities">%product_inventories%</td>
                <td class="align-right quantity">%quantity%</td>
                <td class="align-right sale-price">%sale_price%</td>
                <td class="align-right total-price">%total_price%</td>
            </tr>
            </tbody>
        </table>

        <table>
            <tbody id="_order_payment_template">
            <tr class="order-payment %additional_classes%" id="order_payment_%order_payment_id%" data-order_payment_id="%order_payment_id%">
                <td class='payment-time'>%payment_time%</td>
                <td class='payment-method'>%payment_method%</td>
                <td class='account-number'>%account_number%</td>
                <td class='not-captured'>%not_captured%</td>
                <td class='transaction-identifier'>%transaction_identifier%</td>
                <td class='align-right amount'>%amount%</td>
                <td class='align-right shipping-charge'>%shipping_charge%</td>
                <td class='align-right tax-charge'>%tax_charge%</td>
                <td class='align-right handling-charge'>%handling_charge%</td>
                <td class='align-right total-amount'>%total_amount%</td>
                <td><?php if (canAccessPageCode("INVOICEMAINT")) { ?><a class='order-payment-invoice-id-%invoice_id%' href='/invoicemaintenance.php?url_page=show&primary_id=%invoice_id%'>View Invoice</a> <?php } ?><span class="fad fa-trash-alt delete-payment" title="Delete Payment"></span><span class="fad fa-trash-undo undelete-payment" title="Undelete Payment"></span></td>
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
            <tr class="touchpoint" id="touchpoint_%task_id%">
                <td>%date_completed%</td>
                <td>%task_type%</td>
                <td>%description%</td>
                <td>%detailed_description%</td>
            </tr>
            </tbody>
        </table>

        <table>
            <tbody id="_help_desk_entry_template">
            <tr class="help-desk-entry" id="help_desk_entry_%help_desk_entry_id%">
                <td>%date_submitted%</td>
                <td>%description%</td>
                <td>%date_closed%</td>
            </tr>
            </tbody>
        </table>

        <table>
            <tbody id="_order_file_template">
            <tr class="order-file" id="order_file_%order_file_id%" data-order_file_id="%order_file_id%">
                <td class='order-file-download'><a href="/download.php?id=%file_id%">%description%</a></td>
            </tr>
            </tbody>
        </table>
		<?php
	}
}

$pageObject = new PickupDashboardPage("orders");
$pageObject->displayPage();
