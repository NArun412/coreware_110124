<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "CHANGEORDERSTATUS";
require_once "shared/startup.inc";

class ChangeOrderStatusPage extends Page {

	function mainContent() {
		$orderRow = getRowFromId("orders", "order_id", $_GET['order_id'], "deleted = 0");
		if (empty($orderRow)) {
			echo "<h2 class='error-message'>Order does not exist</h2>";
			return true;
		}
		if (!empty($orderRow['date_completed'])) {
			echo "<h2 class='error-message'>This order is marked completed</h2>";
			return true;
		}
		$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_id", $_GET['order_status_id']);
		if (empty($orderStatusId)) {
			$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", $_GET['order_status_code']);
		}
		if (empty($orderStatusId)) {
			echo "<h2 class='error-message'>Invalid order status</h2>";
			return true;
		}
		$statusDescription = getFieldFromId("description", "order_status", "order_status_id", $orderStatusId);

		Order::updateOrderStatus($orderRow['order_id'], $orderStatusId);
		echo "<h2 class='info-message'>Order Status changed to '" . $statusDescription . "'</h2>";
		return true;
	}
}

$pageObject = new ChangeOrderStatusPage();
$pageObject->displayPage();
