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

$GLOBALS['gPageCode'] = "DISTRIBUTORAVAILABILITYREPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 1200000;

class DistributorAvailabilityReportPage extends Page implements BackgroundReport {

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

		$savedProductDistributorIds = array();
		$resultSet = executeReadQuery("select * from locations where client_id = ? and inactive = 0 and product_distributor_id is not null order by product_distributor_id,primary_location desc,location_id", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (in_array($row['product_distributor_id'], $savedProductDistributorIds)) {
				continue;
			}
			$savedProductDistributorIds[] = $row['product_distributor_id'];
			if (!$row['primary_location']) {
				executeQuery("update locations set primary_location = 1 where location_id = ?", $row['location_id']);
				executeQuery("update locations set primary_location = 0 where product_distributor_id = ? and client_id = ? and location_id <> ?", $row['product_distributor_id'], $GLOBALS['gClientId'], $row['location_id']);
			}
		}

		$locationIds = array();
		$resultSet = executeQuery("select * from locations where inactive = 0 and client_id = ? and product_distributor_id is not null and primary_location = 1",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$locationIds[$row['location_id']] = $row;
		}
		if (empty($locationIds)) {
			$locationIds[0] = array();
		}

		ob_start();
		$productData = array();
		$countSet = executeQuery("select product_id,location_id,quantity,location_cost from product_inventories " .
				"where location_id in (" . implode(",",array_keys($locationIds)) . ")");
		while ($countRow = getNextRow($countSet)) {
			if (!array_key_exists($countRow['product_id'],$productData)) {
				$productData[$countRow['product_id']] = array();
			}
			$productDistributorId = $locationIds[$countRow['location_id']]['product_distributor_id'];
			$productData[$countRow['product_id']][$productDistributorId] = $countRow;
		}

		$doneProductDistributorIds = array();
		$resultSet = executeQuery("select * from locations join product_distributors using (product_distributor_id) where locations.product_distributor_id is not null and primary_location = 1 and " .
				"locations.inactive = 0 and product_distributors.inactive = 0 and locations.client_id = ? order by product_distributors.description", $GLOBALS['gClientId']);
		?>

		<table class='grid-table header-sortable' id="distributor_table">
			<tr class='header-row'>
				<th>Distributor</th>
				<th>Total Products</th>
				<th>Unique Products</th>
				<th>Products in Stock</th>
				<th>Lowest Price</th>
			</tr>
			<?php
			while ($row = getNextRow($resultSet)) {
				if (in_array($row['product_distributor_id'], $doneProductDistributorIds)) {
					continue;
				}
				$doneProductDistributorIds[] = $row['product_distributor_id'];
				$totalProducts = 0;
				$uniqueProducts = 0;
				$inStockProducts = 0;
				$lowestPriceProducts = 0;
				foreach ($productData as $productId => $productDistributorData) {
					if (array_key_exists($row['product_distributor_id'],$productDistributorData)) {
						$totalProducts++;
					} else {
						continue;
					}
					if ($productDistributorData[$row['product_distributor_id']]['quantity'] > 0) {
						$inStockProducts++;
					}
					if (count($productDistributorData) == 1) {
						$uniqueProducts++;
						$lowestPriceProducts++;
					} else {
						$lowestPrice = true;
						$locationCost = $productDistributorData[$row['product_distributor_id']]['location_cost'];
						foreach ($productDistributorData as $productDistributorId => $productDistributorInfo) {
							if ($productDistributorId == $row['product_distributor_id']) {
								continue;
							}
							if ($productDistributorInfo['location_cost'] < $locationCost) {
								$lowestPrice = false;
								break;
							}
						}
						if ($lowestPrice) {
							$lowestPriceProducts++;
						}
					}
				}
				?>
				<tr>
					<td><?= htmlText($row['description']) ?></td>
					<td class='align-right'><?= $totalProducts ?></td>
					<td class='align-right'><?= $uniqueProducts ?></td>
					<td class='align-right'><?= $inStockProducts ?></td>
					<td class='align-right'><?= $lowestPriceProducts ?></td>
				</tr>
				<?php
			}
			?>
		</table>
		<?php
		$reportContent = ob_get_clean();
		$returnArray['report_title'] = "Distributor Availability";
		$returnArray['report_content'] = $reportContent;
		return $returnArray;
	}

	function mainContent() {

# The report form is where the user can set parameters for how the report would be run.

		?>
		<div id="report_parameters">
			<form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>
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
            $(document).on("click", "#select_all", function() {
                $(".product-distributor-id").prop("checked",true);
                return false;
            });
            $(document).on("click", "#unselect_all", function() {
                $(".product-distributor-id").prop("checked",false);
                return false;
            });
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
				input = $("<input>").attr("type", "hidden").attr("name", "filename").val("designationtotals.pdf");
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
						loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $reportForm.serialize(), function (returnArray) {
							if ("report_content" in returnArray) {
								$("#report_parameters").hide();
								$("#_report_title").html(returnArray['report_title']).show();
								$("#_report_content").html(returnArray['report_content']).show();
								$("#_button_row").show();
								$("html, body").animate({scrollTop: 0}, 600);
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

$pageObject = new DistributorAvailabilityReportPage();
$pageObject->displayPage();
