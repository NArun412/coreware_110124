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

$GLOBALS['gPageCode'] = "SALARYREPORT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_report":
				$fullName = getUserDisplayName($GLOBALS['gUserId']);

				$thisYear = date("Y");
				$lastYear = $thisYear - 1;
				$resultSet = executeReadQuery("select * from designations where client_id = ? and designation_id in (select primary_identifier from custom_field_data where custom_field_id = " .
					"(select custom_field_id from custom_fields where client_id = ? and custom_field_code = 'REQUESTED_SALARY' and custom_field_type_id = (select custom_field_type_id from custom_field_types where " .
					"custom_field_type_code = 'DESIGNATIONS'))) and number_data is not null and number_data > 0 order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
				$designationArray = array();
				while ($row = getNextRow($resultSet)) {
					$designationArray[] = $row;
				}
				?>
                <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
                <table class="grid-table">
                    <tr>
                        <th rowspan="2">Designation Code</th>
                        <th rowspan="2">Description</th>
                        <th class="align-center">Last Year</th>
                        <th class="align-center">Last Year</th>
                        <th class="align-center">Current Year</th>
                        <th class="align-center">Current Year</th>
                        <th class="align-center">Requested</th>
                        <th class="align-center">Salary</th>
                    </tr>
                    <tr>
                        <th class="align-center">Donations</th>
                        <th class="align-center">Salary</th>
                        <th class="align-center">Donations</th>
                        <th class="align-center">Salary</th>
                        <th class="align-center">Salary</th>
                        <th class="align-center">Cap</th>
                    </tr>
					<?php
					foreach ($designationArray as $thisDesignation) {
						$lastYearDonations = 0;
						$resultSet = executeReadQuery("select sum(amount) from donations where designation_id = ? and donation_date between ? and ?", $thisDesignation['designation_id'], $lastYear . "-01-01", $lastYear . "-12-31");
						if ($row = getNextRow($resultSet)) {
							$lastYearDonations = $row['sum(amount)'];
							if (empty($lastYearDonations)) {
								$lastYearDonations = 0;
							}
						}
						$lastYearSalary = 0;
						$resultSet = executeReadQuery("select sum(amount) from paycheck_records where designation_id = ? and " .
							"pay_period_id in (select pay_period_id from pay_periods where date_paid_out between ? and ?)", $thisDesignation['designation_id'], $lastYear . "-01-01", $lastYear . "-12-31");
						if ($row = getNextRow($resultSet)) {
							$lastYearSalary = $row['sum(amount)'];
							if (empty($lastYearSalary)) {
								$lastYearSalary = 0;
							}
						}
						$thisYearDonations = 0;
						$resultSet = executeReadQuery("select sum(amount) from donations where designation_id = ? and donation_date between ? and ?", $thisDesignation['designation_id'], $thisYear . "-01-01", $thisYear . "-12-31");
						if ($row = getNextRow($resultSet)) {
							$thisYearDonations = $row['sum(amount)'];
							if (empty($thisYearDonations)) {
								$thisYearDonations = 0;
							}
						}
						$thisYearSalary = 0;
						$resultSet = executeReadQuery("select sum(amount) from paycheck_records where designation_id = ? and " .
							"pay_period_id in (select pay_period_id from pay_periods where date_paid_out between ? and ?)", $thisDesignation['designation_id'], $thisYear . "-01-01", $thisYear . "-12-31");
						if ($row = getNextRow($resultSet)) {
							$thisYearSalary = $row['sum(amount)'];
							if (empty($thisYearSalary)) {
								$thisYearSalary = 0;
							}
						}
						$requestedSalary = getFieldFromId("number_data", "custom_field_data", "primary_identifier", $thisDesignation['designation_id'],
							"custom_field_id = (select custom_field_id from custom_fields where client_id = ? and custom_field_code = 'REQUESTED_SALARY' and " .
							"custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'DESIGNATIONS'))", $GLOBALS['gClientId']);
						$salaryCap = getFieldFromId("number_data", "custom_field_data", "primary_identifier", $thisDesignation['designation_id'],
							"custom_field_id = (select custom_field_id from custom_fields where client_id = ? and custom_field_code = 'REQUESTED_SALARY' and " .
							"custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'DESIGNATIONS'))", $GLOBALS['gClientId']);
						if (empty($salaryCap)) {
							$salaryCap = "n/a";
						} else {
							$salaryCap = number_format($salaryCap, 2, ".", ",");
						}
						?>
                        <tr>
                            <td><?= $thisDesignation['designation_code'] ?></td>
                            <td><?= htmlText($thisDesignation['description']) ?></td>
                            <td class="align-right"><?= number_format($lastYearDonations, 2, ".", ",") ?></td>
                            <td class="align-right"><?= number_format($lastYearSalary, 2, ".", ",") ?></td>
                            <td class="align-right"><?= number_format($thisYearDonations, 2, ".", ",") ?></td>
                            <td class="align-right"><?= number_format($thisYearSalary, 2, ".", ",") ?></td>
                            <td class="align-right"><?= number_format($requestedSalary, 2, ".", ",") ?></td>
                            <td class="align-right"><?= $salaryCap ?></td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
				$reportContent = ob_get_clean();
				$returnArray['report_content'] = $reportContent;
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("designationtotals.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $("#_report_form").serialize(), function(returnArray) {
                        if ("report_content" in returnArray) {
                            $("#report_parameters").hide();
                            $("#_report_title").html(returnArray['report_title']).show();
                            $("#_report_content").html(returnArray['report_content']).show();
                            $("#_button_row").show();
                            $("html, body").animate({ scrollTop: 0 }, "slow");
                        }
                    });
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
            .total-line {
                font-weight: bold;
                font-size: 15px;
            }
            .grid-table td.border-bottom {
                border-bottom: 2px solid rgb(0, 0, 0);
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
