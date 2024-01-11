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

$GLOBALS['gPageCode'] = "DISTRIBUTORSHIPMENTREPORT";
require_once "shared/startup.inc";

class DistributorShipmentReportPage extends Page implements BackgroundReport {

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

# Here, you would use the report parameters in $_POST to construct a where statement to get the data and construct the report

		$fullName = getUserDisplayName($GLOBALS['gUserId']);

		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId']);
		$displayCriteria = "";

		if (!empty($_POST['report_date_from'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "date_shipped >= ?";
			$parameters[] = makeDateParameter($_POST['report_date_from']);
		}
		if (!empty($_POST['report_date_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "date_shipped <= ?";
			$parameters[] = makeDateParameter($_POST['report_date_to']);
		}
		if (!empty($_POST['report_date_from']) && !empty($_POST['report_date_to'])) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Shipment Date is between " . date("m/d/Y", strtotime($_POST['report_date_from'])) . " and " . date("m/d/Y", strtotime($_POST['report_date_to']));
		} else {
			if (!empty($_POST['report_date_from'])) {
				if (!empty($displayCriteria)) {
					$displayCriteria .= " and ";
				}
				$displayCriteria .= "Shipment Date is on or after " . date("m/d/Y", strtotime($_POST['report_date_from']));
			} else {
				if (!empty($_POST['report_date_to'])) {
					if (!empty($displayCriteria)) {
						$displayCriteria .= " and ";
					}
					$displayCriteria .= "Shipment Date is on or before " . date("m/d/Y", strtotime($_POST['donation_date_to']));
				}
			}
		}

		ob_start();

		$locations = array();
		$resultSet = executeQuery("select * from locations join product_distributors using (product_distributor_id) where locations.client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$locations[$row['location_id']] = array("product_distributor_id" => $row['product_distributor_id'], "description" => $row['description']);
		}

		$resultSet = executeReadQuery("select *,(select date(order_time) from orders where order_id = order_shipments.order_id) order_date, order_shipment_items.quantity as ship_quantity, order_shipments.location_id as ship_location_id " .
			"from order_shipments join order_shipment_items using (order_shipment_id) join order_items using (order_item_id) where order_shipments.order_id in (select order_id from orders where client_id = ?) and order_shipments.location_id is not null and " .
			"order_shipments.location_id in (select location_id from locations where product_distributor_id is not null)" . (!empty($whereStatement) ? " and " . $whereStatement : "") . " order by date_shipped,order_shipment_id", $parameters);
		$returnArray['report_title'] = "Distributor Shipment Report";
		if ($_POST['report_type'] == "csv") {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"distributor_shipments.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "distributor_shipments.csv";
			echo createCsvRow(array("Order Id", "Order Date", "Product ID", "Distributor", "Distributor Item #", "Cost", "Quantity", "Ship Date", "Tracking Number"));
		} else {
			?>
            <p><?= $displayCriteria ?></p>
            <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
            <table class='grid-table header-sortable'>
            <tr class='header-row'>
                <th>Order ID</th>
                <th>Order Date</th>
                <th>Product ID</th>
                <th>Distributor</th>
                <th>Distributor Item #</th>
                <th class='align-right'>Cost</th>
                <th class='align-right'>Quantity</th>
                <th>Ship Date</th>
                <th>Tracking Number</th>
            </tr>
			<?php
		}
		while ($row = getNextRow($resultSet)) {
			if ($_POST['report_type'] == "csv") {
				echo createCsvRow(array($row['order_id'],
					date("m/d/Y", strtotime($row['order_date'])),
					$row['product_id'],
					$locations[$row['ship_location_id']]['description'],
					getFieldFromId("product_code", "distributor_product_codes", "product_distributor_id", $locations[$row['ship_location_id']]['product_distributor_id']),
					number_format($row['cost'], 2, ".", ","),
					$row['ship_quantity'],
					date("m/d/Y", strtotime($row['date_shipped'])),
					$row['tracking_identifier']));
			} else {
				?>
                <tr>
                    <td><?= $row['order_id'] ?></td>
                    <td><?= date("m/d/Y", strtotime($row['order_date'])) ?></td>
                    <td><?= $row['product_id'] ?></td>
                    <td><?= $locations[$row['ship_location_id']]['description'] ?></td>
                    <td><?= getFieldFromId("product_code", "distributor_product_codes", "product_distributor_id", $locations[$row['ship_location_id']]['product_distributor_id']) ?></td>
                    <td class='align-right'><?= number_format($row['cost'], 2, ".", ",") ?></td>
                    <td class='align-right'><?= $row['ship_quantity'] ?></td>
                    <td><?= date("m/d/Y", strtotime($row['date_shipped'])) ?></td>
                    <td><?= $row['tracking_identifier'] ?></td>
                </tr>
				<?php
			}
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
                        <option value="web">Web Report</option>
                        <option value="csv">CSV Export</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php getPresetDateOptions() ?>

                <div class="basic-form-line preset-date-custom" id="_report_date_row">
                    <label for="report_date_from">Shipment Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_from" name="report_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_to" name="report_date_to">
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

$pageObject = new DistributorShipmentReportPage();
$pageObject->displayPage();
