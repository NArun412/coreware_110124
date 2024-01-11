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

$GLOBALS['gPageCode'] = "DESIGNATIONFUNDREPORT";
require_once "shared/startup.inc";

class DesignationFundReportPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_report":
				ob_start();
				$resultSet = executeReadQuery("select *,(select description from fund_accounts where fund_account_id = designation_fund_accounts.fund_account_id) fund_description " .
					"from designation_fund_accounts join designations using (designation_id) where client_id = ? and fund_account_id in (select fund_account_id from " .
					"fund_accounts where client_id = ? and inactive = 0) order by designation_code,fund_description",
					$GLOBALS['gClientId'], $GLOBALS['gClientId']);
				if ($resultSet['row_count'] == 0) {
					?>
                    <p>No Designations Found</p>
					<?php
					$returnArray['report_content'] = ob_get_clean();
					ajaxResponse($returnArray);
				}

				$saveDesignationId = "";
				while ($row = getNextRow($resultSet)) {
					if ($saveDesignationId != $row['designation_id']) {
						if (!empty($saveDesignationId)) {
							echo "<table>";
						}
						?>
                        <p class="highlighted-text designation-description"><?= $row['designation_code'] ?> - <?= $row['description'] ?></p>
                        <table class="grid-table">
                        <tr>
                            <th>Fund</th>
                            <th>Amount</th>
                            <th>Percentage</th>
                        </tr>
						<?php
						$saveDesignationId = $row['designation_id'];
					}
					?>
                    <tr>
                        <td class="fund-description"><?= $row['fund_description'] ?></td>
                        <td class="align-right amount"><?= number_format($row['amount'], 2) ?></td>
                        <td class="align-right amount"><?= number_format($row['percentage'], 2) ?></td>
                    </tr>
					<?php
				}
				if (!empty($saveDesignationId)) {
					?>
                    </table>
					<?php
				}
				$returnArray['report_content'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		?>
        <div id="_button_row">
            <button id="printable_button">Printable Report</button>
            <button id="pdf_button">Download PDF</button>
        </div>
        <h1 id="_report_title">Designation Funds</h1>
        <p>Designations not listed use fund defaults</p>
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("designationfunds.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function(returnArray) {
                if ("report_content" in returnArray) {
                    $("#_report_content").html(returnArray['report_content']).show();
                    $("#_button_row").show();
                    $("html, body").animate({scrollTop: 0}, "slow");
                }
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
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
            .total-line {
                font-weight: bold;
                font-size: 15px;
            }
            .grid-table td.border-bottom {
                border-bottom: 2px solid rgb(0, 0, 0);
            }
            .fund-description {
                min-width: 400px;
            }
            .designation-description {
                margin-top: 20px;
            }
            .amount {
                min-width: 100px;
            }
        </style>
		<?php
	}
}

$pageObject = new DesignationFundReportPage();
$pageObject->displayPage();
