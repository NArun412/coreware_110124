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

$GLOBALS['gPageCode'] = "EXPENSESREPORT";
require_once "shared/startup.inc";

class ExpensesReportPage extends Page implements BackgroundReport {

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

		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId']);
		$displayCriteria = "";
		$fileOutput = $_POST['report_type'] == "file";
		$detailReport = $_POST['report_type'] == "detail" && !empty($_POST['designation_id']);

		if (!empty($_POST['designation_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "designation_id = ?";
			$parameters[] = $_POST['designation_id'];
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Designation code is " . getFieldFromId("designation_code", "designations", "designation_id", $_POST['designation_id']);
		}

		if (!empty($_POST['as_of_date']) && !$detailReport) {
			if (!empty($displayCriteria)) {
				$displayCriteria .= " and ";
			}
			$displayCriteria .= "Expenses as of " . $_POST['as_of_date'];
			$asOfDate = $_POST['as_of_date'];
		} else {
			$asOfDate = "";
		}

		ob_start();
		if (!$fileOutput) {
			?>
            <h1>Reimbursable Expenses Report</h1>
            <p><?= $displayCriteria ?></p>
            <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
            <table class="grid-table expense-table">
            <tr>
                <th>Designation Code</th>
                <th>Description</th>
                <th>Reimbursable Expenses</th>
            </tr>
			<?php
		}
		$totalExpenses = 0;
		$resultSet = executeReadQuery("select * from designations where inactive = 0 and client_id = ?" .
			(!empty($whereStatement) ? " and " . $whereStatement : "") . " order by designation_code", $parameters);
		while ($row = getNextRow($resultSet)) {
			$row['email_address'] = getFieldFromId("email_address", "designation_email_addresses", "designation_id", $row['designation_id']);
			$reimbursableExpenses = self::getReimbursableExpenses($row['designation_id'], $asOfDate);
			if ($reimbursableExpenses <= 0 && !$detailReport) {
				continue;
			}
			$totalExpenses += $reimbursableExpenses;
			if ($fileOutput) {
				echo $row['designation_code'] . "\t" . $row['description'] . "\t" . $row['email_address'] . "\t" .
					number_format($reimbursableExpenses, 2) . "\n";
			} else {
				?>
                <tr>
                    <td><?= $row['designation_code'] ?></td>
                    <td><?= $row['description'] ?></td>
                    <td><?= number_format($reimbursableExpenses, 2) ?></td>
                </tr>
				<?php
			}
		}
		if ($detailReport) {
			?>
            </table>
            <table class="detail-table grid-table">
                <tr>
                    <th>Log Date</th>
                    <th>Expiration Date</th>
                    <th>Amount</th>
                    <th>Used</th>
                    <th>Remaining</th>
                </tr>
				<?php
				$resultSet1 = executeReadQuery("select expense_id,log_date,expiration_date,amount,(select coalesce(sum(amount),0) from expense_uses " .
					"where expense_id = expenses.expense_id) amount_used from expenses where designation_id in (select designation_id from designations where client_id = ?) " . (!empty($whereStatement) ? " and " . $whereStatement : "") .
					(empty($_POST['log_date_from']) ? "" : " and log_date >= '" . makeDateParameter($_POST['from_date']) . "'") .
					(empty($_POST['log_date_to']) ? "" : " and log_date <= '" . makeDateParameter($_POST['to_date']) . "'") .
					" order by log_date desc", $parameters);
				while ($row1 = getNextRow($resultSet1)) {
					?>
                    <tr>
                        <td><?= date("m/d/Y", strtotime($row1['log_date'])) ?></td>
                        <td><?= (empty($row1['expiration_date']) ? "" : date("m/d/Y", strtotime($row1['expiration_date']))) ?></td>
                        <td class="align-right"><?= number_format($row1['amount'], 2) ?></td>
                        <td class="align-right"><?= number_format($row1['amount_used'], 2) ?></td>
                        <td class="align-right"><?= number_format($row1['amount'] - $row1['amount_used'], 2) ?></td>
                    </tr>
					<?php
					$resultSet2 = executeReadQuery("select * from expense_uses where expense_id = " . $row1['expense_id'] . " order by date_used");
					while ($row2 = getNextRow($resultSet2)) {
						?>
                        <tr>
                            <td colspan="2">&nbsp;</td>
                            <td colspan="3">$<?= number_format($row2['amount'], 2) ?> used on <?= date("m/d/Y", strtotime($row2['date_used'])) ?></td>
                        </tr>
						<?php
					}
				}
				?>
            </table>
			<?php
		}
		if (!$detailReport && !$fileOutput) {
			?>
            <table class="expense-table total-table">
                <tr>
                    <td>Report Total</td>
                    <td class="align-right"><?= number_format($totalExpenses, 2) ?></td>
                </tr>
            </table>
			<?php
		}
		$reportContent = ob_get_clean();
		if ($fileOutput) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/plain";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"expenses.txt\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "expenses.txt";
			$returnArray['report_export'] = $reportContent;
		} else {
			$returnArray['report_content'] = $reportContent;
		}
		return $returnArray;
	}

	private static function getReimbursableExpenses($designationId, $asOfDate) {
		$reimbursableExpenses = 0;
		if (!empty($designationId)) {
			$resultSet = executeReadQuery("select * from expenses where designation_id = ? and " .
				(empty($asOfDate) ? "(expiration_date is null or expiration_date >= now())" : "log_date <= '" . makeDateParameter($asOfDate) . "'" .
					" and (expiration_date is null or expiration_date >= '" . makeDateParameter($asOfDate) . "')"),
				$designationId);
			while ($row = getNextRow($resultSet)) {
				$reimbursableExpenses = $reimbursableExpenses + $row['amount'];
				$resultSet1 = executeReadQuery("select * from expense_uses where expense_id = ?" . (empty($asOfDate) ? "" : " and date_used <= " .
						"'" . makeDateParameter($asOfDate) . "'"), $row['expense_id']);
				while ($row1 = getNextRow($resultSet1)) {
					$reimbursableExpenses = $reimbursableExpenses - $row1['amount'];
				}
			}
		}
		return $reimbursableExpenses;
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
                        <option value="detail">Detail</option>
                        <option value="file">Delimited File</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_as_of_date_row">
                    <label for="as_of_date">On Date</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="as_of_date" name="as_of_date">
                    <span class="extra-info">leave blank for current, only used on summary report</span>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_log_date_row">
                    <label for="log_date_from">Log Date: From</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="log_date_from" name="log_date_from">
                    <label class="second-label">Through</label>
                    <input tabindex="10" type="text" size="12" maxlength="12" class="align-right validate[custom[date]] datepicker" id="log_date_to" name="log_date_to">
                    <span class="extra-info">Only used on detail report</span>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_designation_id_row">
                    <label for="designation_id" class="required-label">Designation</label>
                    <select tabindex="10" id="designation_id" name="designation_id" class="validate[required]" data-conditional-required="$('#report_type').val() == 'detail'">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeReadQuery("select * from designations where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['designation_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
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
                $("#_pdf_form").html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $('#_pdf_form').append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("expenses.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    const reportType = $("#report_type").val();
                    if (reportType === "file") {
                        $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
                    } else {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function(returnArray) {
                            if ("report_content" in returnArray) {
                                $("#report_parameters").hide();
                                $("#_report_title").html(returnArray['report_title']).show();
                                $("#_report_content").html(returnArray['report_content']).show();
                                $("#_button_row").show();
                                $("html, body").animate({scrollTop: 0}, "slow");
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

            #_button_row {
                display: none;
                margin-bottom: 20px;
            }

            .expense-table {
                width: 600px;
            }

            .detail-table {
                margin: 20px 0 0 40px;
            }

            .total-table {
                font-size: 14px;
                font-weight: bold;
                margin-top: 10px;
            }
        </style>
		<?php
	}
}

$pageObject = new ExpensesReportPage();
$pageObject->displayPage();
