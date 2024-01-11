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

$GLOBALS['gPageCode'] = "TAXONOMYREPORT";
require_once "shared/startup.inc";

class TaxonomyReportPage extends Page implements BackgroundReport {

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
		$resultSet = executeReadQuery("select description as product_category,"
			. "	(select group_concat(description separator '|') from product_category_groups"
			. " where inactive = 0 and product_category_group_id in (select product_category_group_id from product_category_group_links"
			. " where product_category_group_links.product_category_id = product_categories.product_category_id)) as product_category_groups,"
			. " (select group_concat(description separator '|') from product_departments"
			. " where inactive = 0 and product_department_id in (select product_department_id from product_category_departments"
			. " where product_category_departments.product_category_id = product_categories.product_category_id)"
			. " or product_department_id in (select product_department_id from product_category_group_departments join product_category_groups using (product_category_group_id)"
			. " where product_category_group_id in (select product_category_group_id from product_category_group_links"
			. " where product_category_group_links.product_category_id = product_categories.product_category_id))) as product_departments,"
			. " (select count(*) from products where inactive = 0 and product_id in (select product_id from product_category_links"
			. "	where product_category_links.product_category_id = product_categories.product_category_id)) as products_in_category"
			. " from product_categories where inactive = 0 and client_id = ?"
			. (!empty($whereStatement) ? " and " . $whereStatement : ""), $parameters);

		$returnArray['report_title'] = "Taxonomy Report";
		if ($csvExport) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"taxonomy.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "taxonomy.csv";

			echo '"Category","Category Groups","Departments","Products in Category"' . "\n";
		} else {
			?>
            <p>Run on <?= date("m-d-Y") ?> by <?= $fullName ?></p>
            <table class='grid-table'>
            <tr>
                <th>Category</th>
                <th>Category Groups</th>
                <th>Departments</th>
                <th>Products in Category</th>
            </tr>
			<?php
		}
		while ($row = getNextRow($resultSet)) {
			if ($csvExport) {
				echo '"' . $row['product_category'] . '",' .
					'"' . $row['product_category_groups'] . '",' .
					'"' . $row['product_departments'] . '",' .
					'"' . number_format($row['products_in_category'], 0) . '"' . "\n";

			} else {
				?>
                <tr>
                    <td><?= htmlText($row['product_category']) ?></td>
                    <td><?= htmlText($row['product_category_groups']) ?></td>
                    <td><?= htmlText($row['product_departments']) ?></td>
                    <td class="align-right"><?= number_format($row['products_in_category'], 0, ".", ",") ?></td>
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
                    <label for="report_type">Report Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="web">Web</option>
                        <option value="csv">Export</option>
                    </select>
                    <div class='clear-div'></div>
                </div>

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
                const $pdfForm = $("#_pdf_form");
                $pdfForm.html("");
                let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
                $pdfForm.append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
                $pdfForm.append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
                $pdfForm.append($(input));
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("taxonomy.pdf");
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

$pageObject = new TaxonomyReportPage();
$pageObject->displayPage();
