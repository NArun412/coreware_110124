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

$GLOBALS['gPageCode'] = "ORDERSPAYMENTREPORT";
require_once "shared/startup.inc";

class OrdersPaymentReportPage extends Page implements BackgroundReport {

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
			$whereStatement .= "order_payments.payment_time >= ?";
			$parameters[] = date("Y-m-d", strtotime($_POST['report_date_from']));
		}
		if (!empty($_POST['report_date_to'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "order_payments.payment_time <= ?";
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
        $paymentMethodTypes = array();
        foreach (explode(",", $_POST['payment_method_types']) as $paymentMethodTypeId) {
            $paymentMethodTypeRow = getRowFromId("payment_method_types", "payment_method_type_id", $paymentMethodTypeId);
            if (!empty($paymentMethodTypeRow)) {
                $paymentMethodTypes[$paymentMethodTypeRow['payment_method_type_id']] = $paymentMethodTypeRow['description'];
            }
        }
        if (!empty($paymentMethodTypes)) {
            if (!empty($whereStatement)) {
                $whereStatement .= " and ";
            }
            $whereStatement .= "order_payments.payment_method_id in (select payment_method_id from payment_methods where payment_method_type_id in (" . implode(",", array_keys($paymentMethodTypes)).  "))";
            if (!empty($displayCriteria)) {
                $displayCriteria .= " and ";
            }
            $displayCriteria .= "Payment Method Type is " . implode(",", array_values($paymentMethodTypes));
        }

		if (strlen($_POST['not_captured']) > 0) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "not_captured = ?";
			$parameters[] = $_POST['not_captured'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Payment is " . ($_POST['not_captured'] == 1 ? "not " : "") . "captured";
		}

		if (empty($_POST['include_completed'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.date_completed is null";
		}
		if (!empty($displayCriteria)) {
			$displayCriteria .= " and ";
		}
		$displayCriteria .= (empty($_POST['include_completed']) ? "Excluding" : "Including") . " completed orders";

		if (!empty($_POST['external_identifier'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.external_identifier is " . ($_POST['external_identifier'] == "set" ? "not" : "") . " null";
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= ($_POST['include_completed'] == "not" ? "Excluding" : "Including") . " where POS ID is set";
		}

		if (empty($_POST['include_deleted'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "orders.deleted = 0";
		} else {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Including deleted orders";
		}

		if (empty($_POST['include_deleted_payments'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "order_payments.deleted = 0";
		} else {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Including deleted payments";
		}

		ob_start();

		$paymentMethods = array();
		$resultSet = executeReadQuery("select * from payment_methods where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$paymentMethods[$row['payment_method_id']] = $row['description'];
		}

		$resultSet = executeReadQuery("select orders.order_id, orders.order_time, orders.full_name, order_payments.*,"
			. " (select sum(sale_price * quantity) from order_items where order_id = orders.order_id) as cart_total"
			. " from orders join order_payments using (order_id)"
			. " where orders.client_id = ?" . (!empty($whereStatement) ? " and " . $whereStatement : "") . " order by order_time,orders.order_id", $parameters);
		$fieldNames = array("Order ID", "Order Date", "Time", "Full Name", "Cart Total", "Payment Method", "Payment Date", "Account", "Transaction ID", "Authorization Code",
			"Invoice Number", "Payment Status", "Amount", "Shipping", "Handling", "Tax", "Total", "Notes");
		$fieldFormats = array("cart_total" => "align-right",
			"invoice_number" => "align-right",
			"amount" => "align-right",
			"shipping_charge" => "align-right",
			"handling_charge" => "align-right",
			"tax_charge" => "align-right",
			"payment_total" => "align-right");
        $formatForAccounting = !empty($_POST['format_for_accounting_import']);

		$returnArray['report_title'] = "Orders Payment Report";
		if ($_POST['report_type'] == "csv") {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"orderspayment.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "orderspayment.csv";
			echo implode(",", $fieldNames) . "\n";
		} else {
			?>
            <p><?= $displayCriteria ?></p>
            <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
            <table class="header-sortable grid-table">
            <tr class='header-row'>
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
		$reportTotalSales = 0;
		$reportTotalShipping = 0;
		$reportTotalHandling = 0;
		$reportTotalTax = 0;
		$reportTotalAmount = 0;
		while ($row = getNextRow($resultSet)) {
            $accountRow = getRowFromId("accounts", "account_id", $row['account_id']);
			$paymentTotal = floatval($row['amount']) + floatval($row['shipping_charge']) + floatval($row['handling_charge']) + floatval($row['tax_charge']);
			$reportTotalSales += $row['amount'];
			$reportTotalShipping += $row['shipping_charge'];
			$reportTotalTax += $row['tax_charge'];
			$reportTotalHandling += $row['handling_charge'];
			$reportTotalAmount += $paymentTotal;
			$paymentStatus = empty($row['not_captured']) ? "" : "NOT CAPTURED";
			if (!empty($row['deleted'])) {
				$paymentStatus .= (empty($paymentStatus) ? "" : ", ") . "DELETED";
			}
			$fieldValues = array("order_id" => $row['order_id'],
				"order_date" => date("m/d/Y", strtotime($row['order_time'])),
				"order_time" => date("g:i a", strtotime($row['order_time'])),
				"full_name" => htmlText($formatForAccounting ? substr($row['full_name'], 0, 31) : $row['full_name']),
				"cart_total" => number_format($row['cart_total'], 2),
				"payment_method" => htmlText($paymentMethods[($row['payment_method_id'] ?: $accountRow['payment_method_id'])]),
				"payment_time" => date(($formatForAccounting ? "m/d/Y" : "m/d/Y g:i a"), strtotime($row['payment_time'])),
				"account_label" => htmlText($accountRow['account_label']),
				"transaction_id" => $row['transaction_identifier'],
				"authorization_code" => $row['authorization_code'],
				"invoice_number" => htmlText(getFieldFromId('invoice_number', 'invoices', 'invoice_id', $row['invoice_id'], "inactive = 0")),
				"payment_status" => htmlText($paymentStatus),
				"amount" => number_format($row['amount'], 2),
				"shipping_charge" => number_format($row['shipping_charge'], 2),
				"handling_charge" => number_format($row['handling_charge'], 2),
				"tax_charge" => number_format($row['tax_charge'], 2),
				"payment_total" => number_format($paymentTotal, 2),
				"notes" => htmlText($row['notes']));


			if ($_POST['report_type'] == "csv") {
				echo createCsvRow($fieldValues);
			} else {
				echo "<tr>";
				foreach ($fieldValues as $thisName => $thisValue) {
					echo (array_key_exists($thisName, $fieldFormats) ? "<td class='" . $fieldFormats[$thisName] . "'>" : "<td>") . $thisValue . "</td>";
				}
				echo "</tr>";
			}
		}
		if ($_POST['report_type'] == "csv") {
			$returnArray['report_export'] = ob_get_clean();
		} else {
			?>
            <tr class='footer-row'>
                <td class="highlighted-text" colspan="10">Totals</td>
                <td class='highlighted-text align-right'><?= number_format($reportTotalSales, 2) ?></td>
                <td class='highlighted-text align-right'><?= number_format($reportTotalShipping, 2) ?></td>
                <td class='highlighted-text align-right'><?= number_format($reportTotalHandling, 2) ?></td>
                <td class='highlighted-text align-right'><?= number_format($reportTotalTax, 2) ?></td>
                <td class='highlighted-text align-right'><?= number_format($reportTotalAmount, 2) ?></td>
                <td>&nbsp;</td>
            </tr>
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
                    <label for="report_date_from">Payment Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_from" name="report_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_to" name="report_date_to">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <?php
                $paymentMethodTypesControl = new DataColumn("payment_method_types");
                $paymentMethodTypesControl->setControlValue("data_type", "custom");
                $paymentMethodTypesControl->setControlValue("include_inactive", "true");
                $paymentMethodTypesControl->setControlValue("control_class", "MultiSelect");
                $paymentMethodTypesControl->setControlValue("control_table", "payment_method_types");
                $paymentMethodTypesControl->setControlValue("primary_table", "payment_method_types");
                $customControl = new MultipleSelect($paymentMethodTypesControl, $this);
                ?>
                <div class="basic-form-line custom-control-no-help custom-control-form-line" id="_payment_method_types_row">
                    <label for="payment_method_types">Payment Method Types</label>
                    <?= $customControl->getControl() ?>
                    <div class='clear-div'></div>
                </div>

                <div class="basic-form-line" id="_not_captured_row">
                    <label for="not_captured">Include payments not captured</label>
                    <select tabindex="10" id="not_captured" name="not_captured">
                        <option value="">[All]</option>
                        <option value="0">Only Captured</option>
                        <option value="1">Only Not Captured</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_external_identifier_row">
                    <label for="external_identifier">POS ID Set</label>
                    <select tabindex="10" id="external_identifier" name="external_identifier">
                        <option value="">[All]</option>
                        <option value="set">Only has POS ID Set</option>
                        <option value="not">Only does Not have POS ID Set</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_completed_row">
                    <input type="checkbox" name="include_completed" id="include_completed" value="1" checked><label class="checkbox-label" for="include_completed">Include completed orders</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_deleted_row">
                    <input type="checkbox" name="include_deleted" id="include_deleted" value="1"><label class="checkbox-label" for="include_deleted">Include deleted orders</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_include_deleted_payments_row">
                    <input type="checkbox" name="include_deleted_payments" id="include_deleted_payments" value="1"><label class="checkbox-label" for="include_deleted_payments">Include deleted payments</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_format_for_accounting_import_row">
                    <input type="checkbox" name="format_for_accounting_import" id="format_for_accounting_import" value="1"><label class="checkbox-label" for="format_for_accounting_import">Format for accounting import (truncates some fields)</label>
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

$pageObject = new OrdersPaymentReportPage();
$pageObject->displayPage();
