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

$GLOBALS['gPageCode'] = "GUNDATAEXPORT";
require_once "shared/startup.inc";
require_once "classes/xlsxwriter.class.php";

class ThisPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_report":

# Orders Sheet

				$writer = new XLSXWriter();

				$headerRow = array("Order ID" => "integer",
					"Contact Id" => "integer",
					"Order Date" => "string",
					"Status" => "string",
					"First Name" => "string",
					"Last Name" => "string",
					"Full Name" => "string",
					"Email" => "string",
					"Phone" => "string",
					"Shipping Address" => "string",
					"Shipping Address 2" => "string",
					"Shipping City" => "string",
					"Shipping State" => "string",
					"Shipping Zip" => "string",
					"Shipping Method" => "string",
					"Shipping Charge" => "money",
					"Tax" => "money",
					"FFL Dealer" => "string",
					"FFL Number" => "string",
					"FFL Address" => "string",
					"FFL City" => "string",
					"FFL State" => "string",
					"FFL Zip" => "string",
					"FFL Phone" => "string");
				$dataArray = array();
				$shippingMethods = array();
				$resultSet = executeQuery("select * from shipping_methods");
				while ($row = getNextRow($resultSet)) {
					$shippingMethods[$row['shipping_method_id']] = $row['description'];
				}
				$orderStatuses = array();
				$resultSet = executeQuery("select * from order_status");
				while ($row = getNextRow($resultSet)) {
					$orderStatuses[$row['order_status_id']] = $row['description'];
				}
				$resultSet = executeQuery("select * from orders join contacts using (contact_id)");
				while ($row = getNextRow($resultSet)) {
					$thisArray = array();
					$thisArray[] = $row['order_id'];
					$thisArray[] = $row['contact_id'];
					$thisArray[] = date("m/d/Y", strtotime($row['order_time']));
					$thisArray[] = $orderStatuses[$row['order_status_id']];
					$thisArray[] = $row['first_name'];
					$thisArray[] = $row['last_name'];
					$thisArray[] = $row['full_name'];
					$thisArray[] = $row['email_address'];
					$thisArray[] = $row['phone_number'];
					if (empty($row['address_id'])) {
						$addressRow = $row;
					} else {
						$addressRow = getRowFromId("addresses", "address_id", $row['address_id']);
					}
					$thisArray[] = $addressRow['address_1'];
					$thisArray[] = $addressRow['address_2'];
					$thisArray[] = $addressRow['city'];
					$thisArray[] = $addressRow['state'];
					$thisArray[] = $addressRow['postal_code'];
					$thisArray[] = $shippingMethods[$row['shipping_method_id']];
					$thisArray[] = $addressRow['shipping_charge'];
					$thisArray[] = $addressRow['tax_charge'];
					if (empty($row['federal_firearms_licensee_id'])) {
						$fflRow = array();
					} else {
						$fflRow = (new FFL($row['federal_firearms_licensee_id']))->getFFLRow();
					}
					$thisArray[] = $fflRow['business_name'];
					$thisArray[] = $fflRow['license_number'];
					$thisArray[] = $fflRow['address_1'];
					$thisArray[] = $fflRow['city'];
					$thisArray[] = $fflRow['state'];
					$thisArray[] = $fflRow['postal_code'];
					$thisArray[] = $fflRow['phone_number'];
					$dataArray[] = $thisArray;
				}

				$writer->writeSheet($dataArray, 'Orders', $headerRow);

				$headerRow = array("Order ID" => "integer",
					"UPC" => "string",
					"Description" => "string",
					"Serial Number" => "string",
					"Quantity" => "integer",
					"Sale Price" => "money");
				$dataArray = array();
				$resultSet = executeQuery("select *,(select group_concat(serial_number) from order_item_serial_numbers where " .
					"order_item_id = order_items.order_item_id) as serial_numbers from order_items join products using (product_id) join product_data using (product_id)");
				while ($row = getNextRow($resultSet)) {
					$thisArray = array();
					$thisArray[] = $row['order_id'];
					$thisArray[] = $row['upc_code'];
					$thisArray[] = $row['description'];
					$thisArray[] = $row['serial_numbers'];
					$thisArray[] = $row['quantity'];
					$thisArray[] = $row['sale_price'];
					$dataArray[] = $thisArray;
				}

				$writer->writeSheet($dataArray, 'Order Items', $headerRow);

				$paymentMethods = array();
				$resultSet = executeQuery("select * from payment_methods");
				while ($row = getNextRow($resultSet)) {
					$paymentMethods[$row['payment_method_id']] = $row['description'];
				}
				$headerRow = array("Order ID" => "integer",
					"Payment Date" => "string",
					"Payment Method" => "string",
					"Account Number" => "string",
					"Amount" => "money",
					"Tax" => "money",
					"Shipping" => "money",
					"Total" => "money",
					"Billing Address 1" => "string",
					"Billing Address 2" => "string",
					"Billing City" => "string",
					"Billing State" => "string",
					"Billing Zip" => "string");
				$dataArray = array();
				$resultSet = executeQuery("select * from order_payments left outer join accounts using (account_id)");
				while ($row = getNextRow($resultSet)) {
					$thisArray = array();
					$thisArray[] = $row['order_id'];
					$thisArray[] = date("m/d/Y g:i a", strtotime($row['payment_time']));
					$thisArray[] = $paymentMethods[$row['payment_method_id']];
					$thisArray[] = $row['account_number'];
					$thisArray[] = $row['amount'];
					$thisArray[] = $row['tax_charge'];
					$thisArray[] = $row['shipping_charge'];
					$thisArray[] = $row['amount'] + $row['tax_charge'] + $row['shipping_charge'];
					if (empty($row['address_id'])) {
						$addressRow = array();
					} else {
						$addressRow = getRowFromId("addresses", "address_id", $row['address_id']);
					}
					$thisArray[] = $addressRow['address_1'];
					$thisArray[] = $addressRow['address_2'];
					$thisArray[] = $addressRow['city'];
					$thisArray[] = $addressRow['state'];
					$thisArray[] = $addressRow['postal_code'];
					$dataArray[] = $thisArray;
				}

				$writer->writeSheet($dataArray, 'Payments', $headerRow);

				$headerRow = array("Order ID" => "integer",
					"User" => "string",
					"Time Created" => "string",
					"Content" => "string");
				$dataArray = array();
				$resultSet = executeQuery("select * from order_notes");
				while ($row = getNextRow($resultSet)) {
					$thisArray = array();
					$thisArray[] = $row['order_id'];
					$thisArray[] = getUserDisplayName($row['user_id']);
					$thisArray[] = date("m/d/Y g:ia", strtotime($row['time_submitted']));
					$thisArray[] = $row['content'];
					$dataArray[] = $thisArray;
				}

				$writer->writeSheet($dataArray, 'Notes', $headerRow);

				$headerRow = array("Order ID" => "integer",
					"Date Shipped" => "string",
					"From" => "string",
					"Order Number" => "integer",
					"To" => "string",
					"Tracking ID" => "string",
					"Notes" => "string");
				$dataArray = array();
				$resultSet = executeQuery("select * from order_shipments left outer join remote_orders using (remote_order_id)");
				while ($row = getNextRow($resultSet)) {
					$thisArray = array();
					$thisArray[] = $row['order_id'];
					$thisArray[] = date("m/d/Y", strtotime($row['date_shipped']));
					$thisArray[] = getFieldFromId("description", "locations", "location_id", $row['location_id']);
					$thisArray[] = $row['order_number'];
					$thisArray[] = $row['full_name'];
					$thisArray[] = $row['tracking_identifier'];
					$thisArray[] = $row['notes'];
					$dataArray[] = $thisArray;
				}

				$writer->writeSheet($dataArray, 'Shipments', $headerRow);

				$headerRow = array("Contact ID" => "integer",
					"Touchpoint Date" => "string",
					"Description" => "string",
					"Content" => "string");
				$dataArray = array();
				$resultSet = executeQuery("select * from tasks");
				while ($row = getNextRow($resultSet)) {
					$thisArray = array();
					$thisArray[] = $row['contact_id'];
					$thisArray[] = date("m/d/Y", strtotime($row['date_completed']));
					$thisArray[] = $row['description'];
					$thisArray[] = $row['detailed_description'];
					$dataArray[] = $thisArray;
				}

				$writer->writeSheet($dataArray, 'Touchpoints', $headerRow);

				$headerRow = array("Contact ID" => "integer",
					"Entry Date" => "string",
					"Description" => "string",
					"Content" => "string",
					"Notes" => "string");
				$dataArray = array();
				$resultSet = executeQuery("select * from help_desk_entries where length(content) < 2000");
				while ($row = getNextRow($resultSet)) {
					$thisArray = array();
					$thisArray[] = $row['contact_id'];
					$thisArray[] = date("m/d/Y", strtotime($row['time_submitted']));
					$thisArray[] = $row['description'];
					$thisArray[] = $row['content'];
					$notes = "";
					$noteSet = executeQuery("select * from help_desk_public_notes where content not in ('Attached Image','Attached File') and length(content) < 2000 and help_desk_entry_id = ?", $row['help_desk_entry_id']);
					while ($noteRow = getNextRow($noteSet)) {
						$notes .= (empty($notes) ? "" : "\r\r") . $noteRow['content'];
					}
					$thisArray[] = $notes;
					$dataArray[] = $thisArray;
				}

				$writer->writeSheet($dataArray, 'Help Desk', $headerRow);

				$filename = "export.xlsx";
				header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
				header('Content-Disposition: attachment;filename="' . $filename . '"');
				header('Content-Transfer-Encoding: binary');
				header('Cache-Control: must-revalidate');
				header('Pragma: public');

				$writer->writeToStdOut();
				exit;
		}
	}

	function mainContent() {
		echo $this->iPageData['content'];
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

                <div class="basic-form-line">
                    <button tabindex="10" id="create_report">Download Export</button>
                </div>

            </form>
        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        $(document).on("tap click","#create_report",function() {
        disableButtons($(this));
        $("#_report_form").attr("action","<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report").attr("method","POST").attr("target","post_iframe").submit();
        return false;
        });
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
