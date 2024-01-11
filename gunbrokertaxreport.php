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

$GLOBALS['gPageCode'] = "GUNBROKERTAXREPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class GunBrokerTaxReportPage extends Page implements BackgroundReport {
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

	public static function ordersSort($a, $b) {
		if ($a['shipToState'] == $b['shipToState']) {
			if ($a['orderDate'] == $b['orderDate']) {
				return 0;
			}
			return ($a['orderDate'] < $b['orderDate'] ? -1 : 1);
		}
		return ($a['shipToState'] < $b['shipToState'] ? -1 : 1);
	}

	public static function getReportContent() {
		$returnArray = array();
		saveStoredReport(static::class);

		processPresetDates($_POST['preset_dates'], "report_date_from", "report_date_to");

		$fullName = getUserDisplayName($GLOBALS['gUserId']);

		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId']);
		$displayCriteria = "";

		try {
			$gunBroker = new GunBroker();
		} catch (Exception $e) {
			$returnArray['error_message'] = "Unable to get GunBroker data";
			return $returnArray;
		}

		$fromDate = date("Y-m-d", strtotime($_POST['report_date_from']));
		$toDate = (empty($_POST['report_date_to']) ? date("Y-m-d") : date("Y-m-d", strtotime($_POST['report_date_to'])));
		$compareDate = date("Y-m-d", strtotime("-90 days"));
		if ($fromDate < $compareDate) {
			$timeFrame = 8;
		} else {
			$timeFrame = 7;
		}
		$allOrders = array();

		for ($pageNumber = 1; $pageNumber <= 100; $pageNumber++) {

			$filterArray = array("PageSize" => 300, "PageIndex" => $pageNumber, "OrderStatus" => "0", "TimeFrame" => $timeFrame);

			$orders = $gunBroker->getOrders($filterArray);
			$errorMessage = $gunBroker->getErrorMessage();
			if (!empty($errorMessage)) {
				$returnArray['error_message'] = $errorMessage;
				return;
			}
			if (count($orders) == 0) {
				break;
			}
			foreach ($orders as $thisOrder) {
				if ($thisOrder['orderCancelled']) {
					continue;
				}
				$compareDate = date("Y-m-d", strtotime($thisOrder['orderDate']));
				if ($compareDate < $fromDate || $compareDate > $toDate) {
					continue;
				}
				if ($thisOrder['salesTaxTotal'] <= 0) {
					continue;
				}
				$allOrders[] = $thisOrder;
			}
		}
		if (count($allOrders) == 0) {
			$returnArray['error_message'] = "No orders found";
			return;
		}

		usort($allOrders, array(static::class, "ordersSort"));

		$detailReport = $_POST['report_type'] == "detail";
		ob_start();

		$returnArray['report_title'] = "GunBroker Tax " . ($detailReport ? "Details" : "Summary") . " Report";
		?>
        <p><?= $displayCriteria ?></p>
        <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
        <h2>Tax Report for <?= date("m/d/Y", strtotime($fromDate)) ?> through <?= date("m/d/Y", strtotime($toDate)) ?></h2>
        <table class='grid-table'>
			<?php
			if ($detailReport) {
				?>
                <tr>
                    <th>Customer Name</th>
                    <th>GunBroker Order ID</th>
                    <th>State</th>
                    <th>Tax Collected</th>
                </tr>
			<?php } else { ?>
                <tr>
                    <th>State</th>
                    <th>Orders Count</th>
                    <th>Tax Collected</th>
                </tr>
				<?php
			}
			$saveState = "";
			$saveTax = 0;
			$saveCount = 0;
			$totalTax = 0;
			$totalCount = 0;
			foreach ($allOrders as $thisOrder) {
				if ($saveState != $thisOrder['shipToState']) {
					if (!empty($saveState)) {
						if ($detailReport) {
							?>
                            <tr>
                                <td class='highlighted-text align-right' colspan='3'>Total for <?= $saveState ?></td>
                                <td class='highlighted-text align-right'><?= number_format($saveTax, 2, ".", ",") ?></td>
                            </tr>
							<?php
						} else {
							?>
                            <tr>
                                <td class='highlighted-text align-right'>Total for <?= $saveState ?></td>
                                <td class='highlighted-text align-right'><?= $saveCount ?></td>
                                <td class='highlighted-text align-right'><?= number_format($saveTax, 2, ".", ",") ?></td>
                            </tr>
							<?php
						}
					}
					$saveState = $thisOrder['shipToState'];
					$saveTax = 0;
					$saveCount = 0;
				}
				$saveTax += $thisOrder['salesTaxTotal'];
				$saveCount++;
				$totalTax += $thisOrder['salesTaxTotal'];
				$totalCount++;
				if ($detailReport) {
					?>
                    <tr>
                        <td><?= $thisOrder['shipToName'] ?></td>
                        <td><?= $thisOrder['orderID'] ?></td>
                        <td><?= $thisOrder['shipToState'] ?></td>
                        <td class='align-right'><?= number_format($thisOrder['salesTaxTotal'], 2, ".", ",") ?></td>
                    </tr>
					<?php
				}
			}
			if (!empty($saveState)) {
				if ($detailReport) {
					?>
                    <tr>
                        <td class='highlighted-text align-right' colspan='3'>Total for <?= $saveState ?></td>
                        <td class='highlighted-text align-right'><?= number_format($saveTax, 2, ".", ",") ?></td>
                    </tr>
					<?php
				} else {
					?>
                    <tr>
                        <td class='highlighted-text align-right'>Total for <?= $saveState ?></td>
                        <td class='highlighted-text align-right'><?= $saveCount ?></td>
                        <td class='highlighted-text align-right'><?= number_format($saveTax, 2, ".", ",") ?></td>
                    </tr>
					<?php
				}
			}
			?>
        </table>
		<?php
		$reportContent = ob_get_clean();
		$returnArray['report_content'] = $reportContent;
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
                        <option value="summary">Summary</option>
                        <option value="detail">Details</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php getPresetDateOptions() ?>

                <div class="basic-form-line preset-date-custom" id="_report_date_row">
                    <label for="report_date_from">Order Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[required,custom[date]] datepicker" id="report_date_from" name="report_date_from">
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

$pageObject = new GunBrokerTaxReportPage();
$pageObject->displayPage();
