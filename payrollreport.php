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

$GLOBALS['gPageCode'] = "PAYROLLREPORT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setReadonly(true);
		}
	}

	function afterGetRecord(&$returnArray) {
		ob_start();
?>
<p>Created <?= date("m/d/Y",strtotime($returnArray['date_created']['data_value'])) ?>, Paid out <?= date("m/d/Y",strtotime($returnArray['date_paid_out']['data_value'])) ?></p>
<div id="_report_content">
<table class="grid-table">
<tr>
	<th>Designation Code</th>
	<th>Description</th>
	<th>Count</th>
	<th>Amount</th>
</tr>
<?php
		$designationCount = 0;
		$reportTotal = 0;
		$donationFeeTotal = 0;
		$fundTotals = array();
		$giftCount = 0;
		$resultSet = executeReadQuery("select donations.designation_id,count(*),sum(amount),sum(donation_fee) from donations,designations " .
			"where donations.designation_id = designations.designation_id and pay_period_id = ? group by designation_id " .
			"order by designations.sort_order,designations.description",$returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$designationCount++;
			$reportTotal += $row['sum(amount)'];
			$donationFeeTotal += $row['sum(donation_fee)'];
			$netDonations = $row['sum(amount)'] - $row['sum(donation_fee)'];
			$giftCount += $row['count(*)'];
?>
<tr<?= ($designationCount == 1 ? "" : " class='thick-top'") ?>>
	<td class="highlighted-text"><?= getFieldFromId("designation_code","designations","designation_id",$row['designation_id']) ?></td>
	<td class="highlighted-text"><?= getFieldFromId("description","designations","designation_id",$row['designation_id']) ?></td>
	<td class="align-right"><?= $row['count(*)'] ?></td>
	<td class="align-right"><?= number_format($row['sum(amount)'],2,".",",") ?></td>
</tr>
<tr>
	<td class="spacer"></td>
	<td colspan="2">Donation Fees</td>
	<td class="align-right"><?= number_format($row['sum(donation_fee)'],2,".",",") ?></td>
</tr>
<?php
			$fundSet = executeReadQuery("select fund_account_details.amount,fund_accounts.fund_account_id,fund_accounts.description from fund_accounts,fund_account_details where " .
				"fund_accounts.fund_account_id = fund_account_details.fund_account_id and designation_id = ? and pay_period_id = ?",
				$row['designation_id'],$returnArray['primary_id']['data_value']);
			while ($fundRow = getNextRow($fundSet)) {
				$netDonations -= $fundRow['amount'];
				if (!array_key_exists($fundRow['fund_account_id'],$fundTotals)) {
					$fundTotals[$fundRow['fund_account_id']] = array("description"=>$fundRow['description'],"total"=>0);
				}
				$fundTotals[$fundRow['fund_account_id']]['total'] += $fundRow['amount'];
?>
<tr>
	<td class="spacer"></td>
	<td colspan="2"><?= $fundRow['description'] ?></td>
	<td class="align-right"><?= number_format($fundRow['amount'],2,".",",") ?></td>
</tr>
<?php
			}
?>
<tr>
	<td class="spacer"></td>
	<td colspan="2" class="highlighted-text align-right">Net Donations for <?= getFieldFromId("designation_code","designations","designation_id",$row['designation_id']) . " - " . getFieldFromId("description","designations","designation_id",$row['designation_id']) ?></td>
	<td class="align-right highlighted-text"><?= number_format($netDonations,2,".",",") ?></td>
</tr>
<?php
		}
?>
<tr class="thick-top-black">
	<td class="highlighted-text">Report Totals</td>
	<td colspan="2" class="highlighted-text align-right">Total Gifts</td>
	<td class="align-right"><?= $giftCount ?></td>
</tr>
<tr>
	<td class="spacer"></td>
	<td colspan="2" class="highlighted-text align-right">Total Designations</td>
	<td class="align-right"><?= $designationCount ?></td>
</tr>
<tr>
	<td class="spacer"></td>
	<td colspan="2" class="highlighted-text align-right">Total Donations</td>
	<td class="align-right"><?= number_format($reportTotal,2,".",",") ?></td>
</tr>
<tr>
	<td class="spacer"></td>
	<td colspan="2" class="highlighted-text align-right">Total Donation Fees</td>
	<td class="align-right"><?= number_format($donationFeeTotal,2,".",",") ?></td>
</tr>
<?php
		$reportTotal -= $donationFeeTotal;
		foreach ($fundTotals as $fundAccountId => $fundInfo) {
			$reportTotal -= $fundInfo['total'];
?>
<tr>
	<td class="spacer"></td>
	<td colspan="2" class="highlighted-text align-right">Total for fund - <?= $fundInfo['description'] ?></td>
	<td class="align-right"><?= number_format($fundInfo['total'],2,".",",") ?></td>
</tr>
<?php
		}
?>
<tr>
	<td class="spacer"></td>
	<td colspan="2" class="highlighted-text align-right">Total Net Donations</td>
	<td class="align-right highlighted-text"><?= number_format($reportTotal,2,".",",") ?></td>
</tr>
</table>
</div>
<?php
		$returnArray['_report_content'] = array("data_value"=>ob_get_clean());
	}

	function onLoadJavascript() {
?>
<script>
$(document).on("tap click","#printable_button",function() {
	window.open("/printable.html");
	return false;
});
$(document).on("tap click","#pdf_button",function() {
	$("#_pdf_form").html("");
	let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
	$('#_pdf_form').append($(input));
	input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
	$('#_pdf_form').append($(input));
	input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
	$('#_pdf_form').append($(input));
	input = $("<input>").attr("type", "hidden").attr("name", "filename").val("payroll.pdf");
	$('#_pdf_form').append($(input));
	$("#_pdf_form").attr("action","/reportpdf.php").attr("method","POST").submit();
	return false;
});
</script>
<?php
	}

	function hiddenElements() {
?>
<div id="_pdf_data" class="hidden">
<form id="_pdf_form">
</form>
</div>
<?php
	}

	function internalCSS() {
?>
<style>
.grid-table { border-left: none; border-bottom: none; }
.grid-table tr.thick-top td { border-top-width: 4px; }
.grid-table tr.thick-top-black td { border-top: 4px solid rgb(0,0,0); }
.grid-table tr:nth-child(even) td { background-color: rgb(230,230,230); }
.grid-table tr:nth-child(even) td.spacer { background-color: rgb(255,255,255); }
.grid-table td.spacer { border: none; }
</style>
<?php
	}
}

$pageObject = new ThisPage("pay_periods");
$pageObject->displayPage();
