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

$GLOBALS['gPageCode'] = "RECURRINGPAYMENTREPORT";
require_once "shared/startup.inc";

class RecurringPaymentReportPage extends Page implements BackgroundReport {

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

		$fullName = getUserDisplayName($GLOBALS['gUserId']);
		$totalCount = 0;
		$totalFees = 0;
		$totalPayments = 0;

		$detailReport = $_POST['report_type'] == "detail";
		$exportReport = $_POST['report_type'] == "csv";

		$whereStatement = "";
		$displayCriteria = "";
		if (!empty($_POST['only_active'])) {
			$whereStatement = "(end_date is null or end_date > current_date)";
			$displayCriteria = "Only Active Recurring Payments";
		}

		$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(contact_subscription_id is null or contact_subscription_id in (select contact_subscription_id from contact_subscriptions where inactive = 0))";

		$resultSet = executeReadQuery("select * from recurring_payments join contacts using (contact_id) where client_id = ?" .
			(!empty($whereStatement) ? " and " . $whereStatement : "") . " order by last_name,first_name,business_name", $GLOBALS['gClientId']);

		$reportTotal = 0;
		ob_start();
		if ($exportReport) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"recurringpayments.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "recurringpayments.csv";
			$csvHeaders = array("Contact ID", "Name", "Address 1", "Address 2", "City", "State", "Postal Code", "Email Address",
				"Items", "Next Billing", "End Date", "Payment Method", "Requires Attention", "Last Error Message", "Amount");
			if (function_exists("_localRecurringPaymentReportHeaderProcessing")) {
				_localRecurringPaymentReportHeaderProcessing($csvHeaders);
			}

			echo createCsvRow($csvHeaders);
			while ($row = getNextRow($resultSet)) {
				$totalAmount = 0;
				$orderDetails = "";
				$itemSet = executeReadQuery("select * from recurring_payment_order_items where recurring_payment_id = ?", $row['recurring_payment_id']);
				while ($itemRow = getNextRow($itemSet)) {
					$orderDetails .= (empty($orderDetails) ? "" : "|") . $itemRow['quantity'] . 'x '
						. getFieldFromId("description", "products", "product_id", $itemRow['product_id']) . " $" . $itemRow['sale_price'];
					$totalAmount += ($itemRow['quantity'] * $itemRow['sale_price']);
				}
				$reportTotal += $totalAmount;

                $accountRow = getRowFromId("accounts", "account_id", $row['account_id']);
                if($accountRow['inactive']) {
                    $expirationMessage = "INACTIVE ACCOUNT";
                } else {
                    $expirationMessage = $GLOBALS['gPageObject']->getExpirationMessage($accountRow['expiration_date']);
                }
				$requiresAttentionMessage = (empty($row['requires_attention']) ? (empty($paused) ? "" : "Paused by Customer") : "REQUIRES ATTENTION");
				if (!empty($expirationMessage)) {
					$requiresAttentionMessage .= (empty($requiresAttentionMessage) ? "" : " / ") . $expirationMessage;
				}

				$paused = false;
				if (!empty($row['contact_subscription_id'])) {
					$paused = getFieldFromId("customer_paused", "contact_subscriptions", "contact_subscription_id", $row['contact_subscription_id']);
				}
				if (empty($paused)) {
					$paused = $row['customer_paused'];
				}
				if (function_exists("_localRecurringPaymentReportRowProcessing")) {
					_localRecurringPaymentReportRowProcessing($row);
				}

                $paymentMethodText = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id'])
                    . (empty($accountRow['account_number']) ? "" : " - " . substr($accountRow['account_number'],-4));

				$csvData = array($row['contact_id'],
					getDisplayName($row['contact_id']),
					$row['address_1'],
					$row['address_2'],
					$row['city'],
					$row['state'],
					$row['postal_code'],
					$row['email_address'],
					$orderDetails,
					(empty($row['next_billing_date']) ? "" : date("m/d/Y", strtotime($row['next_billing_date']))),
					(empty($row['end_date']) ? "" : date("m/d/Y", strtotime($row['end_date']))),
					$paymentMethodText,
					$requiresAttentionMessage,
					$row['error_message'],
					number_format($totalAmount, 2));
				if (function_exists("_localRecurringPaymentReportDataProcessing")) {
					_localRecurringPaymentReportDataProcessing($row, $csvData);
				}

				echo createCsvRow($csvData);
			}
			$returnArray['report_export'] = ob_get_clean();
			return $returnArray;
		}

		$webHeaders = array("Start Date", "From", "Next Billing", "End Date", "Payment Method", "Errors", "Amount");
		if (function_exists("_localRecurringPaymentReportHeaderProcessing")) {
			_localRecurringPaymentReportHeaderProcessing($webHeaders);
		}
		?>
        <h1>Recurring Payments Report</h1>
        <p><?= $displayCriteria ?></p>
        <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
        <table id="report_table" class="grid-table">
            <tr>
				<?php foreach ($webHeaders as $thisHeader) {
					echo '<th>' . $thisHeader . '</th>';
				} ?>
            </tr>
			<?php
			while ($row = getNextRow($resultSet)) {
				$fromDisplay = htmlText(getDisplayName($row['contact_id']));
				if (!empty($row['address_1'])) {
					$fromDisplay .= "<br>" . htmlText($row['address_1']);
				}
				if (!empty($row['city'])) {
					$fromDisplay .= "<br>" . htmlText($row['city']);
				}
				if (!empty($row['state'])) {
					$fromDisplay .= ", " . htmlText($row['state']);
				}
				if (!empty($row['postal_code'])) {
					$fromDisplay .= " " . htmlText($row['postal_code']);
				}
				if (!empty($row['email_address'])) {
					$fromDisplay .= "<br>" . htmlText($row['email_address']);
				}
				$paused = false;
				if (!empty($row['contact_subscription_id'])) {
					$paused = getFieldFromId("customer_paused", "contact_subscriptions", "contact_subscription_id", $row['contact_subscription_id']);
				}
				if (empty($paused)) {
					$paused = $row['customer_paused'];
				}
				if (function_exists("_localRecurringPaymentReportRowProcessing")) {
					_localRecurringPaymentReportRowProcessing($row);
				}

				$detailArray = array();
				$totalAmount = 0;
				$itemSet = executeReadQuery("select * from recurring_payment_order_items where recurring_payment_id = ?", $row['recurring_payment_id']);
				while ($itemRow = getNextRow($itemSet)) {
					$detailArray[] = $itemRow;
					$totalAmount += ($itemRow['quantity'] * $itemRow['sale_price']);
				}
				$reportTotal += $totalAmount;
                $accountRow = getRowFromId("accounts", "account_id", $row['account_id']);
                if($accountRow['inactive']) {
                    $expirationMessage = "INACTIVE ACCOUNT";
                } else {
                    $expirationMessage = $GLOBALS['gPageObject']->getExpirationMessage($accountRow['expiration_date']);
                }
				$requiresAttentionMessage = (empty($row['requires_attention']) ? (empty($paused) ? "" : "Paused by Customer") : "REQUIRES ATTENTION");
				if (!empty($expirationMessage)) {
					$requiresAttentionMessage .= (empty($requiresAttentionMessage) ? "" : " <br> ") . $expirationMessage;
				}

                $paymentMethodText = getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id'])
                    . (empty($accountRow['account_number']) ? "" : " - " . substr($accountRow['account_number'],-4));

                $webData = array(date("m/d/Y", strtotime($row['start_date'])),
					$fromDisplay,
					(empty($row['next_billing_date']) ? "" : date("m/d/Y", strtotime($row['next_billing_date']))),
					(empty($row['end_date']) ? "" : date("m/d/Y", strtotime($row['end_date']))),
					htmlText($paymentMethodText),
					(empty($requiresAttentionMessage) ? "" : $requiresAttentionMessage . ($row['requires_attention'] == 0 ? "" : "<br>" . $row['error_message'])),
					number_format($totalAmount, 2));
				$webFormat = array("",
					"",
					"",
					"",
					"",
					"error-message",
					"align-right");
				if (function_exists("_localRecurringPaymentReportDataProcessing")) {
					_localRecurringPaymentReportDataProcessing($row, $webData);
				}
				$columnCount = count($webData);
				echo '<tr>';
				foreach ($webData as $index => $thisData) {
					echo (empty($webFormat[$index]) ? '<td>' : '<td class="' . $webFormat[$index] . '">') . $thisData . '</td>';
				}
				echo '</tr>';
				if ($detailReport) {
					foreach ($detailArray as $itemRow) {
						?>
                        <tr>
                            <td></td>
                            <td colspan="3"><?= htmlText(getFieldFromId("description", "products", "product_id", $itemRow['product_id'])) ?></td>
                            <td class="align-right"><?= $itemRow['quantity'] ?></td>
                            <td class="align-right"><?= number_format($itemRow['sale_price'], 2, ".", ",") ?></td>
                            <td colspan="<?= $columnCount - 6 ?>"></td>
                        </tr>
						<?php
					}
				}
			}
			?>
            <tr>
                <td colspan="6" class="highlighted-text">Report Total</td>
                <td class="align-right"><?= number_format($reportTotal, 2, ".", ",") ?></td>
				<?php echo($columnCount > 7 ? '<td colspan="' . ($columnCount - 7) . '"></td>' : ""); ?>
            </tr>
        </table>
		<?php
		$reportContent = ob_get_clean();
		$returnArray['report_content'] = $reportContent;
		return $returnArray;
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="summary">Summary</option>
                        <option value="detail">Details</option>
                        <option value="csv">Export</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_only_active_row">
                    <input tabindex="10" type="checkbox" id="only_active" name="only_active" checked="checked"><label class="checkbox-label" for="only_active">Show only active recurring payments</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php storedReportDescription() ?>

                <div class="basic-form-line">
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
                $("#_pdf_form").html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("recurringpayments.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                const $reportForm = $("#_report_form");
                if ($reportForm.validationEngine("validate")) {
                    const reportType = $("#report_type").val();
                    if (reportType == "csv") {
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
        #report_parameters { width: 100%; margin-left: auto; margin-right: auto; }
        #_report_content { display: none; }
        #_button_row { display: none; margin-bottom: 20px; }
        #report_table { margin-bottom: 20px; }
		<?php
	}

	private function getExpirationMessage($expirationDateString) {
		$expirationMessage = "";
		if (!empty($expirationDateString)) {
			try {
				$expirationDate = new DateTime($expirationDateString);
				if ($expirationDate->format("j") == "1") { // If expiration date is first of the month, adjust to end of month
					$expirationDate->add(new DateInterval("P1M"));
					$expirationDate->sub(new DateInterval("P1D"));
				}
				$daysToExpiration = ($expirationDate->getTimestamp() - time()) / 86400;
				if ($daysToExpiration < 0) {
					$expirationMessage = "Card is expired";
				} elseif ($daysToExpiration < 60) {
					$expirationMessage = "Card expires within 60 days";
				}
			} catch (Exception $e) {
				$expirationMessage = "";
			}
		}
		return $expirationMessage;
	}
}

$pageObject = new RecurringPaymentReportPage();
$pageObject->displayPage();
