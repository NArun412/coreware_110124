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

$GLOBALS['gPageCode'] = "ORDERSERIALNUMBERREPORT";
require_once "shared/startup.inc";

class OrderSerialNumbersReportPage extends Page implements BackgroundReport {

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

		processPresetDates($_POST['preset_dates'], "report_date_from", "report_date_to");

		$fullName = getUserDisplayName($GLOBALS['gUserId']);

		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId']);
		$displayCriteria = "";

		if (!empty($_POST['report_date_from'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.order_time >= ?";
			$parameters[] = date("Y-m-d", strtotime($_POST['report_date_from']));
		}
		if (!empty($_POST['report_date_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.order_time <= ?";
			$parameters[] = date("Y-m-d", strtotime($_POST['report_date_to'])) . " 23:59:59";
		}
		if (!empty($_POST['report_date_from']) && !empty($_POST['report_date_to'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Report date is between " . date("m/d/Y", strtotime($_POST['report_date_from'])) . " and " . date("m/d/Y", strtotime($_POST['report_date_to']));
		} else {
			if (!empty($_POST['report_date_from'])) {
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Report date is on or after " . date("m/d/Y", strtotime($_POST['report_date_from']));
			} else {
				if (!empty($_POST['report_date_to'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Report date is on or before " . date("m/d/Y", strtotime($_POST['donation_date_to']));
				}
			}
		}
		if (!empty($_POST['only_completed'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.date_completed is not null";
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Showing completed orders only";
		}
		if (empty($_POST['include_deleted'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.deleted = 0 and order_items.deleted = 0";
		} else {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Including deleted orders and items";
		}

		ob_start();

		$resultSet = executeReadQuery("select orders.order_id, orders.order_time, orders.full_name, orders.date_completed, order_items.*,order_item_serial_numbers.serial_number,"
			. " (select product_code from products where products.product_id = order_items.product_id) product_code,"
			. " (select upc_code from product_data where product_data.product_id = order_items.product_id) upc_code"
			. " from orders join order_items using (order_id) left join order_item_serial_numbers using (order_item_id)"
			. " where orders.client_id = ? and order_items.product_id in (select product_id from products where serializable = 1)" . (!empty($whereStatement) ? " and " . $whereStatement : "") . " order by order_time,orders.order_id", $parameters);
		$fieldNames = array("Order ID", "Order Date", "Time", "Full Name", "Date Completed", "UPC",
			"Product Code", "Product Description", "Base Cost", "Quantity", "Serial Number");

		$returnArray['report_title'] = "Order Serial Numbers Report";
		if ($_POST['report_type'] == "csv") {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"orderserialnumbers.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "orderserialnumbers.csv";
			echo implode(",", $fieldNames) . "\n";
		} else {
			?>
            <p><?= $displayCriteria ?></p>
            <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
            <table class="grid-table">
            <tr>
				<?php
				foreach ($fieldNames as $thisFieldName) {
					?>
                    <th><?= $thisFieldName ?></th>
					<?php
				}
				?>
            </tr>
			<?php
		}
		$saveOrderId = "";
		$saveOrderItemId = '';
		while ($row = getNextRow($resultSet)) {
			$dateCompleted = (empty($row['date_completed']) ? "" : date("m/d/Y", strtotime($row['date_completed'])));
			if ($_POST['report_type'] == "csv") {
				echo createCsvRow(array($row['order_id'],
					date("m/d/Y", strtotime($row['order_time'])),
					date("g:i a", strtotime($row['order_time'])),
					htmlText($row['full_name']),
					$dateCompleted,
					htmlText($row['upc_code']),
					htmlText($row['product_code']),
					htmlText($row['description']),
					htmlText(number_format($row['base_cost'], 2)),
					htmlText($row['quantity']),
					htmlText($row['serial_number'])));
			} else {
				?>
                <tr>
                    <td><?= htmlText(($saveOrderId == $row['order_id'] ? "" : $row['order_id'])) ?></td>
                    <td><?= ($saveOrderId == $row['order_id'] ? "" : date("m/d/Y", strtotime($row['order_time']))) ?></td>
                    <td><?= ($saveOrderId == $row['order_id'] ? "" : date("g:i a", strtotime($row['order_time']))) ?></td>
                    <td><?= ($saveOrderId == $row['order_id'] ? "" : htmlText($row['full_name'])) ?></td>
                    <td><?= ($saveOrderId == $row['order_id'] ? "" : $dateCompleted) ?></td>
                    <td><?= ($saveOrderItemId == $row['order_item_id'] ? "" : htmlText($row['upc_code'])) ?></td>
                    <td><?= ($saveOrderItemId == $row['order_item_id'] ? "" : htmlText($row['product_code'])) ?></td>
                    <td><?= ($saveOrderItemId == $row['order_item_id'] ? "" : htmlText($row['description'])) ?></td>
                    <td><?= ($saveOrderItemId == $row['order_item_id'] ? "" : htmlText(number_format($row['base_cost'], 2))) ?></td>
                    <td><?= ($saveOrderItemId == $row['order_item_id'] ? "" : htmlText($row['quantity'])) ?></td>
                    <td><?= htmlText($row['serial_number']) ?></td>
                </tr>
				<?php
			}
			$saveOrderId = $row['order_id'];
			$saveOrderItemId = $row['order_item_id'];
		}
		if ($_POST['report_type'] == "csv") {
			$returnArray['report_export'] = ob_get_clean();
		} else {
			?>
            </table>
			<?php
			$returnArray['report_content'] = ob_get_clean();
		}
		return $returnArray;
	}

	function mainContent() {

# The report form is where the user can set parameters for how the report would be run.

		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="web">Web</option>
                        <option value="csv">CSV Export</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php getPresetDateOptions() ?>

                <div class="basic-form-line preset-date-custom" id="_report_date_row">
                    <label for="report_date_from">Report Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_from" name="report_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_to" name="report_date_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_only_completed_row">
                    <input type="checkbox" name="only_completed" id="only_completed" value="1"><label class="checkbox-label" for="only_completed">Include only completed orders</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_deleted_row">
                    <input type="checkbox" name="include_deleted" id="include_deleted" value="1"><label class="checkbox-label" for="include_deleted">Include deleted orders and items (excluded by default)</label>
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
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("orderspayment.pdf");
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
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $reportForm.serialize(), function(returnArray) {
                            if ("report_content" in returnArray) {
                                $("#report_parameters").hide();
                                $("#_report_title").html(returnArray['report_title']).show();
                                $("#_report_content").html(returnArray['report_content']).show();
                                $("#_button_row").show();
                                $("html, body").animate({ scrollTop: 0 }, 600);
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

$pageObject = new OrderSerialNumbersReportPage();
$pageObject->displayPage();
