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

$GLOBALS['gPageCode'] = "DISTRIBUTORORDERPRODUCTREPORT";
require_once "shared/startup.inc";

class DistributorOrderProductReportPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_report":
				$export = ($_POST['report_type'] == "csv");
				if ($export) {
					header("Content-Type: text/csv");
					header("Content-Disposition: attachment; filename=\"order_items.csv\"");
					header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
					header('Pragma: public');
					echo '"UPC","Description","Qty on Distributor Orders","Distributor Orders","Qty on Customer Orders","Customer Orders"' . "\n";
				} else {
					ob_start();
					?>
                    <table class='grid-table'>
                    <tr>
                        <th>UPC</th>
                        <th>Description</th>
                        <th>Qty on Distributor Orders</th>
                        <th>Distributor Orders</th>
                        <th>Qty on Customer Orders</th>
                        <th>Customer Orders</th>
                    </tr>
					<?php
				}
				$resultSet = executeReadQuery("select * from products join product_data using (product_id) join distributor_order_items using (product_id) where distributor_order_id in " .
					"(select distributor_order_id from distributor_orders where client_id = ? and date_completed is null) order by product_data.upc_code,products.description", $GLOBALS['gClientId']);
				$productArray = array();
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['product_id'], $productArray)) {
						$row['order_ids'] = array();
						$row['distributor_order_ids'] = array();
						$productArray[$row['product_id']] = $row;
					}
					if (substr($row['notes'], 0, strlen("For order ID ")) == "For order ID ") {
						$orderId = substr($row['notes'], strlen("For order ID "));
						if (!empty($orderId) && is_numeric($orderId)) {
							$productArray[$row['product_id']]['order_ids'][] = $orderId;
						}
					}
					$productArray[$row['product_id']]['distributor_order_ids'][] = $row['distributor_order_id'];
				}
				foreach ($productArray as $row) {
					$distributorOrders = "";
					sort($row['distributor_order_ids']);
					$distributorOrderIds = array_unique($row['distributor_order_ids']);
					foreach ($distributorOrderIds as $thisId) {
						$distributorOrders .= (empty($distributorOrders) ? "" : "; ") . $thisId;
					}
					$orders = "";
					sort($row['order_ids']);
					$orderIds = array_unique($row['order_ids']);
					foreach ($orderIds as $thisId) {
						$orders .= (empty($orders) ? "" : "; ") . $thisId;
					}
					if ($export) {
						echo '"' . $row['upc_code'] . '",';
						echo '"' . $row['description'] . '",';
						echo '"' . count($distributorOrderIds) . '",';
						echo '"' . $distributorOrders . '",';
						echo '"' . count($orderIds) . '",';
						echo '"' . $orders . '"' . "\r\n";
					} else {
						?>
                        <tr>
                            <td><?= $row['upc_code'] ?></td>
                            <td><?= htmlText($row['description']) ?></td>
                            <td class='align-right'><?= count($distributorOrderIds) ?></td>
                            <td><?= $distributorOrders ?></td>
                            <td class='align-right'><?= count($orderIds) ?></td>
                            <td><?= $orders ?></td>
                        </tr>
						<?php
					}
				}

				if (!$export) {
					?>
                    </table>
					<?php
					$returnArray['report_title'] = "Orders Report";
					$returnArray['report_content'] = ob_get_clean();
					echo jsonEncode($returnArray);
				}
				exit;
		}
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="web">Print</option>
                        <option value="csv">CSV</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line">
                    <label></label>
                    <button tabindex="10" id="create_report">Create Report</button>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
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
                    if (reportType === "csv") {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function(returnArray) {
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

$pageObject = new DistributorOrderProductReportPage();
$pageObject->displayPage();
