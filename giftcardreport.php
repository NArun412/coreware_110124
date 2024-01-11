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

$GLOBALS['gPageCode'] = "GIFTCARDREPORT";
require_once "shared/startup.inc";

class GiftCardReportPage extends Page implements BackgroundReport {

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

		ob_start();

		$csvExport = ($_POST['report_type'] == "csv");
		$resultSet = executeReadQuery("select *,"
			. " (select log_time from gift_card_log where gift_card_log.gift_card_id = gift_cards.gift_card_id order by log_time asc limit 1) created,"
			. " (select log_time from gift_card_log where gift_card_log.gift_card_id = gift_cards.gift_card_id order by log_time desc limit 1) last_activity"
			. " from gift_cards where inactive = 0 and client_id = ?" . (!empty($whereStatement) ? " and " . $whereStatement : ""), $parameters);
		$returnArray['report_title'] = "Gift Cards Report";
		if ($csvExport) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"giftcards.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "giftcards.csv";

			echo '"Gift Card Number","Description","Balance","Created","User","Last Activity"' . "\n";
		} else {
			?>
            <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
            <table class='grid-table'>
            <tr>
                <th>Gift Card Number</th>
                <th>Description</th>
                <th>Balance</th>
                <th>Created</th>
                <th>User</th>
                <th>Last Activity</th>
            </tr>
			<?php
		}
		while ($row = getNextRow($resultSet)) {
			if ($csvExport) {
				echo '"' . $row['gift_card_number'] . '",' .
					'"' . str_replace('"', '', $row['description']) . '",' .
					'"' . number_format($row['balance'], 2) . '",' .
					'"' . (empty($row['created']) ? "" : date("m/d/Y h:i A", strtotime($row['created']))) . '",' .
					'"' . (empty($row['user_id']) ? "" : getUserDisplayName($row['user_id'])) . '",' .
					'"' . (empty($row['last_activity']) ? "" : date("m/d/Y h:i A", strtotime($row['last_activity']))) . '"' . "\n";

			} else {
				?>
                <tr>
                    <td><?= htmlText($row['gift_card_number']) ?></td>
                    <td><?= htmlText($row['description']) ?></td>
                    <td class="align-right"><?= number_format($row['balance'], 2, ".", ",") ?></td>
                    <td><?= (empty($row['created']) ? "" : date("m/d/Y h:i A", strtotime($row['created']))) ?></td>
                    <td><?= (empty($row['user_id']) ? "" : getUserDisplayName($row['user_id'])) ?></td>
                    <td><?= (empty($row['last_activity']) ? "" : date("m/d/Y h:i A", strtotime($row['last_activity']))) ?></td>
                </tr>
				<?php
			}
		}
		if ($csvExport) {
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
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

                <div class="basic-form-line" id="_report_type_row">
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="web">Web</option>
                        <option value="csv">Export</option>
                    </select>
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("giftcards.pdf");
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

$pageObject = new GiftCardReportPage();
$pageObject->displayPage();
