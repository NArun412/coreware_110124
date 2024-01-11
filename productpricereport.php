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

$GLOBALS['gPageCode'] = "PRODUCTPRICEREPORT";
require_once "shared/startup.inc";

class ProductPriceReportPage extends Page implements BackgroundReport {

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

		if (!empty($_POST['product_price_type_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_price_type_id = ?";
			$parameters[] = $_POST['product_price_type_id'];
		}

		if (!empty($_POST['location_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "location_id = ?";
			$parameters[] = $_POST['location_id'];
		}

		if (!empty($_POST['product_category_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_id in (select product_id from product_category_links where product_category_id = ?)";
			$parameters[] = $_POST['product_category_id'];
		}

		if (!empty($_POST['product_category_group_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from product_category_group_links where product_category_group_id = ?))";
			$parameters[] = $_POST['product_category_group_id'];
		}

		if (!empty($_POST['product_department_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "(product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from product_category_group_links " .
				"where product_category_group_id in (select product_category_group_id from product_category_group_departments where product_department_id = ?))) or " .
				"product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from product_category_departments where " .
				"product_department_id = ?)))";
			$parameters[] = $_POST['product_department_id'];
			$parameters[] = $_POST['product_department_id'];
		}

		$exportReport = $_POST['report_type'] == "csv";
		ob_start();

		$reportArray = array();
		$resultSet = executeReadQuery("select *,(select description from product_price_types where product_price_type_id = product_prices.product_price_type_id) price_type_description from products " .
			"left outer join product_data using (product_id) join product_prices using (product_id) where products.client_id = ?" .
			(!empty($whereStatement) ? " and " . $whereStatement : "") . " order by " . ($_POST['group_by'] == "price_type" ? "price_type_description,upc_code" : "upc_code,price_type_description"), $parameters);
		$returnArray['report_title'] = "Product Price Report";
		if ($exportReport) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"productprices.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "productprices.csv";

			echo '"UPC","Product Code","Description","Manufacturer","Price Type","Location","Start Date","End Date","End after X Sales","Price"' . "\n";
		} else {
			?>
            <table class="grid-table">
            <tr>
                <th>UPC</th>
                <th>Product Code</th>
                <th>Description</th>
                <th>Manufacturer</th>
                <th>Price Type</th>
                <th>Location</th>
                <th>Start Date</th>
                <th>End Date</th>
                <th>End after X Sales</th>
                <th class="align-right">Price</th>
            </tr>
			<?php
		}
		while ($row = getNextRow($resultSet)) {
			if ($exportReport) {
				echo '"' . str_replace('"', '""', $row['upc_code']) . '",';
				echo '"' . str_replace('"', '""', $row['product_code']) . '",';
				echo '"' . str_replace('"', '""', $row['description']) . '",';
				echo '"' . str_replace('"', '""', getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $row['product_manufacturer_id'])) . '",';
				echo '"' . str_replace('"', '""', $row['price_type_description']) . '",';
				echo '"' . str_replace('"', '""', getFieldFromId("description", "locations", "location_id", $row['location_id'])) . '",';
				echo '"' . (empty($row['start_date']) ? "" : date("m/d/Y", strtotime($row['start_date']))) . '",';
				echo '"' . (empty($row['end_date']) ? "" : date("m/d/Y", strtotime($row['end_date']))) . '",';
				echo '"' . $row['sale_count'] . '",';
				echo '"' . number_format($row['price'], 2, ".", ",") . '"' . "\r\n";
			} else {
				?>
                <tr>
                    <td><?= $row['upc_code'] ?></td>
                    <td><?= htmlText($row['product_code']) ?></td>
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= htmlText(getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $row['product_manufacturer_id'])) ?></td>
                    <td><?= htmlText($row['price_type_description']) ?></td>
                    <td><?= htmlText(getFieldFromId("description", "locations", "location_id", $row['location_id'])) ?></td>
                    <td><?= (empty($row['start_date']) ? "" : date("m/d/Y", strtotime($row['start_date']))) ?></td>
                    <td><?= (empty($row['end_date']) ? "" : date("m/d/Y", strtotime($row['end_date']))) ?></td>
                    <td><?= $row['sale_count'] ?></td>
                    <td class="align-right"><?= number_format($row['price'], 2, ".", ",") ?></td>
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
                    <label for="report_type">Output Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="web">Web</option>
                        <option value="csv">CSV</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_group_by_row">
                    <label for="group_by">Group By</label>
                    <select tabindex="10" id="group_by" name="group_by">
                        <option value="price_type">Price Type</option>
                        <option value="product">Product</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_product_price_type_id_row">
                    <label for="product_price_type_id">Price Type</label>
                    <select tabindex="10" id="product_price_type_id" name="product_price_type_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from product_price_types where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_price_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_location_id_row">
                    <label for="location_id">Location</label>
                    <select tabindex="10" id="location_id" name="location_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from locations where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['location_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_product_department_id_row">
                    <label for="product_department_id">Department</label>
                    <select tabindex="10" id="product_department_id" name="product_department_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from product_departments where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_department_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>


                <div class="basic-form-line" id="_product_category_group_id_row">
                    <label for="product_category_group_id">Category Group</label>
                    <select tabindex="10" id="product_category_group_id" name="product_category_group_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from product_category_groups where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_category_group_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_product_category_id_row">
                    <label for="product_category_id">Category</label>
                    <select tabindex="10" id="product_category_id" name="product_category_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from product_categories where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_category_id'] ?>"><?= htmlText($row['description']) ?></option>
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("designationtotals.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    var reportType = $("#report_type").val();
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

$pageObject = new ProductPriceReportPage();
$pageObject->displayPage();
