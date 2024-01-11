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

$GLOBALS['gPageCode'] = "ORDERVIEWER";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("order_number","order_time","order_method_id","full_name","first_name","last_name","business_name","city","state","postal_code","email_address"));
			$this->iTemplateObject->getTableEditorObject()->setReadonly(true);
		}
	}

	function massageDataSource() {
		$this->iDataSource->setJoinTable("contacts","contact_id","contact_id");
		$this->iDataSource->addColumnControl("address_id","choices",array());
	}

	function afterGetRecord(&$returnArray) {
		$primaryId = $returnArray['primary_id']['data_value'];
		$contactInformation = "";
		$resultSet = executeQuery("select * from contacts where contact_id = ?",$returnArray['contact_id']['data_value']);
		if ($row = getNextRow($resultSet)) {
			$contactInformation .= $row['address_1'];
			$contactInformation .= (empty($row['address_2']) || empty($contactInformation) ? "" : "<br>") . $row['address_2'];
			$cityLine = $row['city'];
			$cityLine .= (empty($row['state']) || empty($cityLine) ? "" : ", ") . $row['state'];
			$cityLine .= (empty($row['postal_code']) || empty($cityLine) ? "" : " ") . $row['postal_code'];
			$contactInformation .= (empty($cityLine) || empty($contactInformation) ? "" : "<br>") . $cityLine;
			$contactInformation .= "<br><a href='#' id='customer_email_address'>" . $row['email_address'] . "</a>";
		}
		$returnArray['contact_information'] = array("data_value"=>$contactInformation);
		$referralInformation = "";
		$resultSet = executeQuery("select * from contacts where contact_id = ?",$returnArray['referral_contact_id']['data_value']);
		if ($row = getNextRow($resultSet)) {
			$referralInformation = getDisplayName($row['contact_id']);
			$referralInformation .= (empty($row['address_1']) || empty($referralInformation) ? "" : "<br>") . $row['address_1'];
			$referralInformation .= (empty($row['address_2']) || empty($referralInformation) ? "" : "<br>") . $row['address_2'];
			$cityLine = $row['city'];
			$cityLine .= (empty($row['state']) || empty($cityLine) ? "" : ", ") . $row['state'];
			$cityLine .= (empty($row['postal_code']) || empty($cityLine) ? "" : " ") . $row['postal_code'];
			$referralInformation .= (empty($cityLine) || empty($referralInformation) ? "" : "<br>") . $cityLine;
		}
		$returnArray['referral_information'] = array("data_value"=>$referralInformation);
		$noListPrice = getFieldFromId("order_item_id","order_items","order_id",$primaryId,"list_price is null");
		ob_start();
?>
<table class='grid-table'>
<tr>
	<th>Product</th>
	<th>Image</th>
	<th>Quantity</th>
<?php if (!$noListPrice) { ?>
	<th>List Price</th>
	<th>Discount</th>
<?php } ?>
	<th>Each</th>
	<th>Extended</th>
</tr>
<?php
		$resultSet = executeQuery("select * from order_items where order_id = ?",$primaryId);
		$itemsTotal = 0;
		while ($row = getNextRow($resultSet)) {
			$productRow = ProductCatalog::getCachedProductRow($row['product_id']);
			$itemsTotal += ($row['quantity'] * $row['sale_price']);
?>
<tr>
	<td class='product-description'><?= htmlText(empty($row['description']) ? $productRow['description'] : $row['description']) ?></td>
	<td><img class='product-image' src='<?= ProductCatalog::getProductImage($productRow['image_id'],array("image_type"=>"small")) ?>'></td>
	<td class='align-right'><?= $row['quantity'] ?></td>
<?php if (!$noListPrice) { ?>
	<td class='align-right'><?= number_format($row['list_price'],2) ?></td>
	<td class='align-right'><?= number_format(($row['list_price'] == 0 ? 0 : (100 - ($row['sale_price'] * 100 / $row['list_price']))),2) ?>%</td>
<?php } ?>
	<td class='align-right'><?= number_format($row['sale_price'],2) ?></td>
	<td class='align-right'><?= number_format($row['quantity'] * $row['sale_price'],2) ?></td>
</tr>
<?php
		}
		$grandTotal = round($itemsTotal * ((100 - $returnArray['order_discount']['data_value']) / 100),2);
		$grandTotal += $returnArray['tax_charge']['data_value'];
		$grandTotal += $returnArray['shipping_charge']['data_value'];
		$grandTotal += $returnArray['handling_charge']['data_value'];
?>
<tr>
	<td colspan='<?= (!$noListPrice ? 6 : 4) ?>' class='align-right highlighted-text'>Product Total</td>
	<td class='align-right highlighted-text'><?= number_format($itemsTotal,2) ?></td>
</tr>
<tr>
	<td colspan='<?= (!$noListPrice ? 6 : 4) ?>' class='align-right highlighted-text'>Order Discount</td>
	<td class='align-right highlighted-text'><?= number_format($returnArray['order_discount']['data_value'],2) ?>%</td>
</tr>
<tr>
	<td colspan='<?= (!$noListPrice ? 6 : 4) ?>' class='align-right highlighted-text'>Tax</td>
	<td class='align-right highlighted-text'><?= number_format($returnArray['tax_charge']['data_value'],2) ?></td>
</tr>
<tr>
	<td colspan='<?= (!$noListPrice ? 6 : 4) ?>' class='align-right highlighted-text'>Shipping</td>
	<td class='align-right highlighted-text'><?= number_format($returnArray['shipping_charge']['data_value'],2) ?></td>
</tr>
<tr>
	<td colspan='<?= (!$noListPrice ? 6 : 4) ?>' class='align-right highlighted-text'>Handling</td>
	<td class='align-right highlighted-text'><?= number_format($returnArray['handling_charge']['data_value'],2) ?></td>
</tr>
<tr>
	<td colspan='<?= (!$noListPrice ? 6 : 4) ?>' class='align-right highlighted-text'>Order Total</td>
	<td class='align-right highlighted-text'><?= number_format($grandTotal,2) ?></td>
</tr>
</table>
<?php
		$returnArray['item_list'] = array('data_value'=>ob_get_clean());
		ob_start();
?>
<table class='grid-table'>
<tr>
	<td>Date</td>
	<td>Payment Method</td>
	<td>Account</td>
	<td>Amount</td>
</tr>
<?php
		$resultSet = executeQuery("select * from order_payments where order_id = ? order by payment_time desc",$primaryId);
		$paymentTotal = 0;
		while ($row = getNextRow($resultSet)) {
			$accountRow = getRowFromId("accounts","account_id",$row['account_id']);
			$paymentTotal += $row['amount'];
?>
<tr>
	<td><?= date("m/d/Y g:i a",strtotime($row['payment_time'])) ?></td>
	<td><?= htmlText(getFieldFromId("description","payment_methods","payment_method_id",$row['payment_method_id'])) ?></td>
	<td><?= htmlText(empty($accountRow['account_label']) ? $accountRow['account_number'] : $accountRow['account_label']) ?></td>
	<td class="align-right"><?= number_format($row['amount'],2) ?></td>
</tr>
<?php
		}
?>
<tr>
	<td colspan="3" class="highlighted-text align-right">Total Payments</td>
	<td class="highlighted-text align-right"><?= number_format($paymentTotal,2) ?></td>
</tr>
</table>
<?php
		$returnArray['payment_list'] = array("data_value"=>ob_get_clean());
		$shippingInformation = (empty($returnArray['full_name']['data_value']) ? getDisplayName($returnArray['contact_id']['data_value']) : $returnArray['full_name']['data_value']);
		if (empty($returnArray['address_id']['data_value'])) {
			$shippingInformation .= "<br>" . $contactInformation;
		} else {
			$resultSet = executeQuery("select * from addresses where address_id = ?",$returnArray['address_id']['data_value']);
			if ($row = getNextRow($resultSet)) {
				$shippingInformation .= (empty($row['address_1']) || empty($shippingInformation) ? "" : "<br>") . $row['address_1'];
				$shippingInformation .= (empty($row['address_2']) || empty($shippingInformation) ? "" : "<br>") . $row['address_2'];
				$cityLine = $row['city'];
				$cityLine .= (empty($row['state']) || empty($cityLine) ? "" : ", ") . $row['state'];
				$cityLine .= (empty($row['postal_code']) || empty($cityLine) ? "" : " ") . $row['postal_code'];
				$shippingInformation .= (empty($cityLine) || empty($shippingInformation) ? "" : "<br>") . $cityLine;
			}
		}
		$returnArray['shipping_information'] = array("data_value"=>$shippingInformation);
	}

	function internalCSS() {
?>
<style>
#contact_information,#referral_information,#shipping_information { font-size: 1rem; line-height: 1.5; color: rgb(70,70,120); font-weight: bold; }
#customer_email_address { cursor: pointer; color: rgb(0,100,0); }
.product-image { max-width: 300px; }
.product-description { max-width: 400px; }
</style>
<?php
	}

	function onLoadJavascript() {
?>
	$(document).on("tap click","#customer_email_address",function() {
		window.open("/sendemail.php?contact_id=" + $("#contact_id").val());
		return false;
	});
<?php
	}
}

$pageObject = new ThisPage("orders");
$pageObject->displayPage();
