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

$GLOBALS['gPageCode'] = "SUBSCRIPTIONREPORT";
require_once "shared/startup.inc";

class SubscriptionReportPage extends Page implements BackgroundReport {

	public static function getReportContent() {
		$returnArray = array();
		saveStoredReport(static::class);
		processPresetDates($_POST['preset_dates'], "report_date_from", "report_date_to");

		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId']);

		if (!empty($_POST['subscription_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "subscription_id = ?";
			$parameters[] = $_POST['subscription_id'];
		}

		$exportReport = $_POST['report_type'] == "export";
		ob_start();

		$reportArray = array();
		$resultSet = executeQuery("select *,(select group_concat(retired_contact_identifier separator ', ') from contact_redirect where contact_id = contacts.contact_id) retired_contact_ids, " .
			"contact_subscriptions.inactive as customer_inactive,(select group_concat(phone_number) from phone_numbers where contact_id = contacts.contact_id) as phone_numbers from contacts " .
			"join contact_subscriptions using (contact_id) join subscriptions using (subscription_id) where start_date <= current_date and " .
			(empty($_POST['include_expired']) ? "((units_subscription = 0 and (expiration_date is null or expiration_date >= current_date)) or (units_subscription = 1 and units_remaining > 0)) and " : "") .
			(empty($_POST['include_only_expired']) ? "" : "((units_subscription = 0 and (expiration_date is not null and expiration_date <= current_date)) or (units_subscription = 1 and units_remaining = 0)) and ") .
			"contacts.client_id = ? and " . (empty($_POST['show_inactive']) ? "contact_subscriptions.inactive = 0 and " : "") . "contact_subscriptions.customer_paused = 0 and subscriptions.inactive = 0" .
			(!empty($whereStatement) ? " and " . $whereStatement : "") . " order by subscriptions.sort_order,subscriptions.description,last_name,first_name", $parameters);
		$returnArray['report_title'] = "Subscription Report";
		$subscriptionArray = array();
		$contactArray = array();
		$retiredContactIdsFound = false;
		$_POST['report_date_from'] = (empty($_POST['report_date_from']) ? "" : date("Y-m-d", strtotime($_POST['report_date_from'])));
		$_POST['report_date_to'] = (empty($_POST['report_date_to']) ? "" : date("Y-m-d", strtotime($_POST['report_date_to'])));
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['retired_contact_ids'])) {
				$retiredContactIdsFound = true;
			}
			$recurringPaymentSet = executeQuery("select *,(select sum(quantity * sale_price) from recurring_payment_order_items where recurring_payment_id = recurring_payments.recurring_payment_id) as total_payment, " .
				"(select description from recurring_payment_types where recurring_payment_type_id = recurring_payments.recurring_payment_type_id) recurring_payment_type, " .
				"(select description from payment_methods where payment_method_id = recurring_payments.payment_method_id) payment_method " .
				"from recurring_payments where contact_subscription_id = ? and (end_date is null or end_date > current_date)", $row['contact_subscription_id']);
			if (!$recurringPaymentRow = getNextRow($recurringPaymentSet)) {
				$recurringPaymentRow = array();
			}
			$row['recurring_payment'] = $recurringPaymentRow;
			if (!empty($_POST['report_date_from']) && (empty($recurringPaymentRow['next_billing_date']) || $recurringPaymentRow['next_billing_date'] < $_POST['report_date_from'])) {
				continue;
			}
			if (!empty($_POST['report_date_to']) && (empty($recurringPaymentRow['next_billing_date']) || $recurringPaymentRow['next_billing_date'] > $_POST['report_date_to'])) {
				continue;
			}
			$orderSet = executeQuery("select order_id,order_time from orders where recurring_payment_id = ? order by order_id desc", $row['recurring_payment']['recurring_payment_id']);
			if (!$orderRow = getNextRow($orderSet)) {
				$orderRow = array();
			}
			$row['order_id'] = $orderRow['order_id'];
			$row['order_time'] = $orderRow['order_time'];
			if (function_exists("_localSubscriptionReportRowProcessing")) {
				_localSubscriptionReportRowProcessing($row);
			}

			$subscriptionArray[] = $row;
			if (!array_key_exists($row['contact_id'], $contactArray)) {
				$contactArray[$row['contact_id']] = 0;
			}
			$contactArray[$row['contact_id']]++;
			$unitsReduced = false;
			if (!empty($_POST['reduce_units']) && !empty($row['units_subscription'])) {
				if ($row['units_remaining'] > 0) {
					$dataTable = new DataTable("contact_subscriptions");
					$dataTable->setSaveOnlyPresent(true);
					if ($dataTable->saveRecord(array("name_values" => array("units_remaining" => ($row['units_remaining'] - 1)), "primary_id" => $row['contact_subscription_id']))) {
						$unitsReduced = true;
					}
				}
			}
			if (!empty($_POST['create_usage_log']) || $unitsReduced) {
				executeQuery("insert into contact_subscription_usage (contact_subscription_id,log_date) values (?,current_date)", $row['contact_subscription_id']);
			}
		}
		$headerLabels = array("Subscription", "Contact ID");
		if ($retiredContactIdsFound) {
			$headerLabels[] = "Retired Contact IDs";
		}
		$headerLabels = array_merge($headerLabels, array("First Name", "Last Name", "Address 1", "City", "State", "Postal Code", "Email Address", "Phone", "Start Date", "Expiration Date", "Next Billing Date", "Paused"));
		if (!empty($_POST['show_inactive'])) {
			$headerLabels[] = "Inactive";
		}
		$headerLabels[] = "Notes";
		$headerLabels[] = "Last Order ID";
		$headerLabels[] = "Last Order Time";
		$headerLabels[] = "Recurring Payment Amount";
		$headerLabels[] = "Recurring Payment Type";
		$headerLabels[] = "Requires Attention";
		$headerLabels[] = "Payment Method";
		if (function_exists("_localSubscriptionReportHeaderProcessing")) {
			_localSubscriptionReportHeaderProcessing($headerLabels);
		}

		if ($exportReport) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"subscriptions.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "subscriptions.csv";

			$firstLabel = true;
			foreach ($headerLabels as $thisLabel) {
				echo ($firstLabel ? "" : ",") . '"' . $thisLabel . '"';
				$firstLabel = false;
			}
			echo "\r\n";
		} else {
			?>
            <table class="grid-table header-sortable">
            <tr class='header-row'>
				<?php
				foreach ($headerLabels as $thisLabel) {
					echo "<th>" . $thisLabel . "</th>";
				}
				?>
            </tr>
			<?php
		}
		foreach ($subscriptionArray as $row) {
			$dataRows = array();
			$dataRows[] = $row['description'];
			if (!$exportReport && canAccessPageCode("CONTACTMAINT")) {
				$dataRows[] = array("cell_data" => "<a target='_blank' href='/contactmaintenance.php?clear_filter=true&url_page=show&primary_id=" . $row['contact_id'] . "'>" . $row['contact_id'] . "</a>");
			} else {
				$dataRows[] = $row['contact_id'];
			}
			if ($retiredContactIdsFound) {
				$dataRows[] = $row['retired_contact_ids'];
			}
			$dataRows[] = $row['first_name'];
			$dataRows[] = $row['last_name'];
			$dataRows[] = $row['address_1'];
			$dataRows[] = $row['city'];
			$dataRows[] = $row['state'];
			$dataRows[] = $row['postal_code'];
			$dataRows[] = $row['email_address'];
			$dataRows[] = $row['phone_numbers'];
			$dataRows[] = date("m/d/Y", strtotime($row['start_date']));
			$dataRows[] = (empty($row['expiration_date']) ? "" : date("m/d/Y", strtotime($row['expiration_date'])));
			$dataRows[] = (empty($row['recurring_payment']['next_billing_date']) ? "" : date("m/d/Y", strtotime($row['recurring_payment']['next_billing_date'])));
			$dataRows[] = (empty($row['paused_by_customer']) ? "" : "YES");
			if (!empty($_POST['show_inactive'])) {
				$dataRows[] = (empty($row['customer_inactive']) ? "" : "YES");
			}
			$dataRows[] = $row['notes'];
			$dataRows[] = $row['order_id'];
			$dataRows[] = (empty($row['order_time']) ? "" : date("m/d/Y g:ia", strtotime($row['order_time'])));
			$dataRows[] = number_format($row['recurring_payment']['total_payment'], 2, ".", ",");
			$dataRows[] = $row['recurring_payment']['recurring_payment_type'];
			$dataRows[] = ($row['recurring_payment']['requires_attention'] ? "YES" : "");
			$dataRows[] = $row['recurring_payment']['payment_method'];
			if (function_exists("_localSubscriptionReportDataProcessing")) {
				_localSubscriptionReportDataProcessing($row, $dataRows, $contactArray);
			}
			if ($exportReport) {
				$firstData = true;
				foreach ($dataRows as $thisData) {
					echo ($firstData ? "" : ",") . '"' . $thisData . '"';
					$firstData = false;
				}
				echo "\r\n";
			} else {
				?>
                <tr class="subscription-<?= $row['subscription_id'] ?>">
					<?php
					foreach ($dataRows as $thisData) {
						$class = "";
						if (is_numeric(str_replace(",", "", $thisData))) {
							$class = "align-right";
							$cellData = $thisData;
						} else if (is_array($thisData)) {
							$cellData = $thisData['cell_data'];
						} else {
							$cellData = htmlText($thisData);
						}
						?>
                        <td class='<?= $class ?>'><?= $cellData ?></td>
						<?php
					}
					?>
                </tr>
				<?php
			}
		}
		if ($exportReport) {
			$returnArray['report_export'] = ob_get_clean();
		} else {
			?>
            </table>
			<?php
			$reportContent = ob_get_clean();
			$returnArray['report_content'] = $reportContent;
		}
		return $returnArray;

	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="form-line" id="_report_type_row">
                    <label for="report_type">Output Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="report">Report</option>
                        <option value="export">CSV</option>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_subscription_id_row">
                    <label for="subscription_id">Subscription</label>
                    <select tabindex="10" id="subscription_id" name="subscription_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeQuery("select * from subscriptions where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['subscription_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='clear-div'></div>
                </div>

				<?php getPresetDateOptions() ?>

                <div class="form-line preset-date-custom" id="_report_date_row">
                    <label for="report_date_from">Next Billing Date from</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_from" name="report_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="report_date_to" name="report_date_to">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_include_expired_row">
                    <input type="checkbox" tabindex="10" id="include_expired" name="include_expired" value="1"><label class="checkbox-label" for="include_expired">Include Expired Subscriptions</label>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_include_only_expired_row">
                    <input type="checkbox" tabindex="10" id="include_only_expired" name="include_only_expired" value="1"><label class="checkbox-label" for="include_only_expired">Include ONLY Expired Subscriptions</label>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_show_inactive_row">
                    <input type="checkbox" tabindex="10" id="show_inactive" name="show_inactive" value="1"><label class="checkbox-label" for="show_inactive">Show Inactive Customer Subscriptions</label>
                    <div class='clear-div'></div>
                </div>

				<?php
				$subscriptionId = getFieldFromId("subscription_id", "subscriptions", "units_subscription", "1");
				if (!empty($subscriptionId)) {
					?>
                    <div class="form-line" id="_reduce_units_row">
                        <input type="checkbox" tabindex="10" id="reduce_units" name="reduce_units" value="1"><label class="checkbox-label" for="reduce_units">Reduce subscription units (only applies to unit-based subscriptions and CANNOT be undone)</label>
                        <div class='clear-div'></div>
                    </div>

                    <p>Reducing the subscription units is a permanent change. It will apply to ALL subscriptions included in the report, but only subscriptions that are unit-based. Time-based subscriptions will no be affected. Since this is a permanent change, Coreware recommends the report be run first WITHOUT reducing the units, the report is looked at and verified, and the report is run a final time with reducing units set. A log of usage will be created.</p>
				<?php } else { ?>
                    <div class="form-line" id="_create_usage_log_row">
                        <input type="checkbox" tabindex="10" id="create_usage_log" name="create_usage_log" value="1"><label class="checkbox-label" for="create_usage_log">Create log of subscription usage.</label>
                        <div class='clear-div'></div>
                    </div>
				<?php } ?>

				<?php storedReportDescription() ?>

                <div class="form-line">
                    <label></label>
                    <button tabindex="10" id="create_report">Create Report</button>
                    <div class='clear-div'></div>
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("subscriptionreport.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    var reportType = $("#report_type").val();
                    if (reportType == "export" || reportType == "file" || reportType == "csv") {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function (returnArray) {
                            if ("report_content" in returnArray) {
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

            #_report_content table {
            td {
                font-size: .7rem;
            }
            th {
                font-size: .6rem;
            }
            }

            #_button_row {
                display: none;
                margin-bottom: 20px;
            }

        </style>
        <style id="_printable_style">
            <?php
				$resultSet = executeReadQuery("select * from subscriptions where display_color is not null and client_id = ?",$GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
			?>
            .subscription-<?= $row['subscription_id'] ?> {
                background-color: <?= $row['display_color'] ?>;
            }

            <?php
				}
			?>
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
}

$pageObject = new SubscriptionReportPage();
$pageObject->displayPage();
