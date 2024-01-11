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

$GLOBALS['gPageCode'] = "LOSSLEADERREPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class ThisPage extends Page {

	function sortLosses($a, $b) {
		if ($a['net_loss'] == $b['net_loss']) {
			return 0;
		}
		return ($a['net_loss'] > $b['net_loss']) ? -1 : 1;
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_report":

				$fullName = getUserDisplayName($GLOBALS['gUserId']);

				$whereStatement = "";
				$parameters = array($GLOBALS['gClientId']);
				$displayCriteria = "";

				$returnArray['report_title'] = "Loss Leaders";
				ob_start();

				?>
                <table class="grid-table">
                    <tr>
                        <th>Product Code</th>
                        <th>Description</th>
                        <th>UPC</th>
                        <th>Cost</th>
                        <th>Sale Price</th>
                        <th>Net Loss</th>
                    </tr>
					<?php
					$productCatalog = new ProductCatalog();
					$count = 0;
					$resultsArray = array();
					$resultSet = executeReadQuery("select * from products join product_data using (product_id) where base_cost is not null and base_cost > 0 and products.client_id = ? order by description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$salePriceInfo = $productCatalog->getProductSalePrice($row['product_id'], array("product_information" => $row));
						$salePrice = $salePriceInfo['sale_price'];
						if ($salePrice > $row['base_cost']) {
							continue;
						}
						$GLOBALS['gMultipleProductsForWaitingQuantities'] = true;
						ProductCatalog::calculateProductCost($row['product_id'],"Loss Leader Report");
						$row['base_cost'] = getFieldFromId("base_cost", "products", "product_id", $row['product_id']);
						$salePriceInfo = $productCatalog->getProductSalePrice($row['product_id'], array("product_information" => $row));
						$salePrice = $salePriceInfo['sale_price'];
						$count++;
						$resultsArray[] = array("product_id" => $row['product_id'], "product_code" => $row['product_code'], "description" => $row['description'], "upc_code" => $row['upc_code'],
							"base_cost" => $row['base_cost'], "sale_price" => $salePrice, "net_loss" => $row['base_cost'] - $salePrice);
					}
					usort($resultsArray, array($this, "sortLosses"));
					foreach ($resultsArray as $row) {
						?>
                        <tr class='product-row'>
                            <td><a href='/productmaintenance.php?url_page=show&primary_id=<?= $row['product_id'] ?>&clear_filter=true' target="_blank"><?= htmlText($row['product_code']) ?></a></td>
                            <td><a href='/productmaintenance.php?url_page=show&primary_id=<?= $row['product_id'] ?>&clear_filter=true' target="_blank"><?= htmlText($row['description']) ?></a></td>
                            <td><a href='/productmaintenance.php?url_page=show&primary_id=<?= $row['product_id'] ?>&clear_filter=true' target="_blank"><?= htmlText($row['upc_code']) ?></a></td>
                            <td class="align-right"><?= number_format($row['base_cost'], 2, ".", ",") ?></td>
                            <td class="align-right"><?= number_format($row['sale_price'], 2, ".", ",") ?></td>
                            <td class="align-right red-text"><?= number_format($row['net_loss'], 2, ".", ",") ?></td>
                        </tr>
						<?php
					}
					?>
                    <tr>
                        <td colspan="5" class="highlighted-text">Total Product Count</td>
                        <td class="align-right highlighted-text"><?= $count ?></td>
                    </tr>
                </table>
				<?php
				$returnArray['report_content'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("lossleaders.pdf");
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

$pageObject = new ThisPage();
$pageObject->displayPage();
