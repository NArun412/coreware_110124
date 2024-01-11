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

$GLOBALS['gPageCode'] = "PRODUCTLISTING";
require_once "shared/startup.inc";

class ProductListingPage extends Page implements BackgroundReport {

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

		if (!empty($_POST['product_department_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}

			$whereStatement .= "(product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from product_category_departments where " .
				"product_department_id = ?)) or product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from product_category_group_links where " .
				"product_category_group_id in (select product_category_group_id from product_category_group_departments where product_department_id = ?))))";
			$parameters[] = $_POST['product_department_id'];
			$parameters[] = $_POST['product_department_id'];
		}
		if (!empty($_POST['product_category_group_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}

			$whereStatement .= "product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from product_category_group_links where " .
				"product_category_group_id = ?))";
			$parameters[] = $_POST['product_category_group_id'];
		}

		if (!empty($_POST['product_category_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}

			$whereStatement .= "product_id in (select product_id from product_category_links where product_category_id = ?)";
			$parameters[] = $_POST['product_category_id'];
		}

		if (!empty($_POST['product_tag_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}

			$whereStatement .= "product_id in (select product_id from product_tag_links where product_tag_id = ?)";
			$parameters[] = $_POST['product_tag_id'];
		}

		if (!empty($_POST['product_manufacturer_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}

			$whereStatement .= "product_manufacturer_id = ?";
			$parameters[] = $_POST['product_manufacturer_id'];
		}

		if (!empty($_POST['internal_use_only'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}

			$whereStatement .= "internal_use_only = 0 and product_id not in (select product_id from product_category_links where product_category_id in (select product_category_id from product_categories where product_category_code = 'INTERNAL_USE_ONLY'))";
		}

		$productMetadata = array();
		if (!empty($_POST['large_map'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}

			$whereStatement .= "manufacturer_advertised_price is not null";
			$hostUrl = "https://shootingsports.coreware.com/api.php?action=get_product_metadata&map_prices_only=true";
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, "connection_key=760C0DCAB2BD193B585EB9734F34B3B6");
			curl_setopt($ch, CURLOPT_URL, $hostUrl);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
			curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
			$response = curl_exec($ch);
			$rawProductMetadata = $response;
			$uncompressedProductMetadata = json_decode(gzdecode($rawProductMetadata), true);
			$productMetadata = array();
			foreach ($uncompressedProductMetadata['values'] as $index => $row) {
				$thisArray = array();
				foreach ($uncompressedProductMetadata['keys'] as $keyIndex => $thisField) {
					$thisArray[$thisField] = $row[$keyIndex];
				}
				$productMetadata[$index] = $thisArray;
			}
		}

		$exportReport = $_POST['report_type'] == "export";
		ob_start();

		if (empty($_POST['location_id'])) {
			$locationDescription = '';
			$costHeader = 'Base Cost';
			$resultSet = executeReadQuery("select *,coalesce((select sum(quantity) from (select product_distributor_id,product_id,min(quantity) as quantity from product_inventories join locations using (location_id) where " .
				"inactive = 0 and internal_use_only = 0 and ignore_inventory = 0 and primary_location = 1 " .
				" and product_distributor_id is not null group by product_distributor_id,product_id) as distributor_inventory where product_id = products.product_id),0) as distributor_on_hand, " .
				"coalesce((select sum(quantity) from product_inventories join locations using (location_id) where " .
				"product_id = products.product_id and inactive = 0 and internal_use_only = 0 and ignore_inventory = 0 " .
				" and product_distributor_id is null),0) as local_on_hand from products join product_data using (product_id) where products.client_id = ? and inactive = 0" .
				(!empty($whereStatement) ? " and " . $whereStatement : "") . (empty($_POST['in_stock_only']) ? "" : " having distributor_on_hand > 0 or local_on_hand > 0") . " order by description", $parameters);
		} else {
			$locationDescription = getFieldFromId('description', 'locations', 'location_id', $_POST['location_id']);
			$costHeader = 'Location Cost';
			$locationId = makeParameter($_POST['location_id']);
			$resultSet = executeReadQuery("select *,coalesce((select sum(quantity) from (select product_distributor_id,product_inventories.product_id," .
				"min(product_inventories.quantity) as quantity from product_inventories join locations using (location_id) where " .
				"inactive = 0 and internal_use_only = 0 and ignore_inventory = 0  and locations.location_id = " . $locationId .
				" and product_distributor_id is not null group by product_distributor_id,product_inventories.product_id) as distributor_inventory where product_id = products.product_id),0) as distributor_on_hand, " .
				"coalesce((select sum(quantity) from product_inventories join locations using (location_id) where " .
				"product_id = products.product_id and inactive = 0 and internal_use_only = 0 and ignore_inventory = 0 and location_id = " . makeParameter($_POST['location_id']) .
				" and product_distributor_id is null),0) as local_on_hand from products join product_data using (product_id) where products.client_id = ? and inactive = 0" .
				(!empty($whereStatement) ? " and " . $whereStatement : "") .
				(empty($_POST['in_stock_only']) ? " and product_id in (select product_id from product_inventories where location_id = $locationId)" : " having distributor_on_hand > 0 or local_on_hand > 0") . " order by description", $parameters);
		}
		$returnArray['report_title'] = "Product Listing";
		$productPriceTypes = array();
		if ($exportReport) {
			if (empty($_POST['large_map'])) {
				$headers = array("Product Id", "Code", "UPC", "Description", "Location", "On Hand", $costHeader, "MAP Price", "List Price", "Sale Price");
			} else {
				$headers = array("Product Id", "Code", "UPC", "Description", "Location", "On Hand", $costHeader, "MAP Price", "Coreware MAP", "List Price", "Sale Price");
			}
			$priceSet = executeQuery("select * from product_price_types where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
			while ($priceRow = getNextRow($priceSet)) {
				$productPriceTypes[] = $priceRow;
				$headers[] = $priceRow['description'];
			}
			echo createCsvRow($headers);
		} else {
			?>
            <table class="grid-table">
            <tr>
                <th>Product ID</th>
                <th>Code</th>
                <th>UPC</th>
                <th>Description</th>
                <th>Location</th>
                <th>On Hand</th>
                <th><?= $costHeader ?></th>
                <th>MAP price</th>
                <?php if (!empty($_POST['large_map'])) { ?>
                    <th>Coreware MAP</th>
                <?php } ?>
                <th>List Price</th>
                <th>Sale Price</th>
            </tr>
			<?php
		}
		$productCatalog = new ProductCatalog();
		while ($row = getNextRow($resultSet)) {
            $corewareMap = 0;
			if (!empty($_POST['large_map'])) {
				if (array_key_exists($row['upc_code'], $productMetadata)) {
                    $corewareMap = $productMetadata[$row['upc_code']]['manufacturer_advertised_price'];
				}
                if ($row['manufacturer_advertised_price'] <= $corewareMap) {
	                continue;
                }
			}
			if (empty($row['distributor_on_hand'])) {
				$row['distributor_on_hand'] = 0;
			}
			if (empty($row['local_on_hand'])) {
				$row['local_on_hand'] = 0;
			}
			$row['on_hand'] = $row['distributor_on_hand'] + $row['local_on_hand'];
			if (empty($_POST['location_id'])) {
				$thisCost = $row['base_cost'];
			} else {
				$thisCost = ProductCatalog::getLocationBaseCost($row['product_id'], $_POST['location_id']);
			}
			$salePriceInfo = $productCatalog->getProductSalePrice($row['product_id'], array("product_information" => $row, "no_stored_prices" => true, "contact_type_id" => "", "user_type_id" => ""));
			$salePrice = $salePriceInfo['sale_price'];
			if ($row['on_hand'] < 0 || empty($row['on_hand'])) {
				$row['on_hand'] = 0;
			}
			if ($exportReport) {
				echo '"' . str_replace('"', '""', $row['product_id']) . '",';
				echo '"' . str_replace('"', '""', $row['product_code']) . '",';
				echo '"' . str_replace('"', '""', $row['upc_code']) . '",';
				echo '"' . str_replace('"', '""', $row['description']) . '",';
				echo '"' . $locationDescription . '",';
				echo '"' . $row['on_hand'] . '",';
				echo '"' . (empty($thisCost) ? "n/a" : $thisCost) . '",';
				echo '"' . (empty($row['manufacturer_advertised_price']) ? "n/a" : number_format($row['manufacturer_advertised_price'], 2, ".", ",")) . '",';
				if (!empty($_POST['large_map'])) {
					echo '"' . (empty($corewareMap) ? "n/a" : number_format($corewareMap, 2, ".", ",")) . '",';
				}
				echo '"' . (empty($row['list_price']) ? "n/a" : number_format($row['list_price'], 2, ".", ",")) . '",';
				echo '"' . ($salePrice === false ? "n/a" : number_format($salePrice, 2, ".", ",")) . '"';
				foreach ($productPriceTypes as $productPriceType) {
					$price = getFieldFromId("price", "product_prices", "product_id", $row['product_id'],
						"product_price_type_id = ? and start_date is null and end_date is null and user_type_id is null and location_id is null", $productPriceType['product_price_type_id']);
					if (empty($price)) {
						$price = 0;
					}
					echo ',"' . number_format($price, 2, ".", ",") . '"';
				}
				echo "\r\n";
			} else {
				?>
                <tr>
                    <td><?= $row['product_id'] ?></td>
                    <td><?= $row['product_code'] ?></td>
                    <td><?= htmlText($row['upc_code']) ?></td>
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= htmlText($locationDescription) ?></td>
                    <td class="align-right"><?= $row['on_hand'] ?></td>
                    <td class="align-right"><?= (empty($thisCost) ? "n/a" : $thisCost) ?></td>
                    <td class="align-right"><?= (empty($row['manufacturer_advertised_price']) ? "n/a" : number_format($row['manufacturer_advertised_price'], 2, ".", ",")) ?></td>
                    <?php if (!empty($_POST['large_map'])) { ?>
                        <td class="align-right"><?= (empty($corewareMap) ? "n/a" : number_format($corewareMap, 2, ".", ",")) ?></td>
                    <?php } ?>
                    <td class="align-right"><?= (empty($row['list_price']) ? "n/a" : number_format($row['list_price'], 2, ".", ",")) ?></td>
                    <td class="align-right"><?= number_format($salePrice, 2, ".", ",") ?></td>
                </tr>
				<?php
			}
		}
		if ($exportReport) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"productlisting.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "productlisting.csv";
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
                        <option value="report">Report</option>
                        <option value="export">Export</option>
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

                <div class="basic-form-line" id="_product_tag_id_row">
                    <label for="product_tag_id">Tag</label>
                    <select tabindex="10" id="product_tag_id" name="product_tag_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from product_tags where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_tag_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_product_manufacturer_id_row">
                    <label for="product_manufacturer_id">Manufacturer</label>
                    <select tabindex="10" id="product_manufacturer_id" name="product_manufacturer_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from product_manufacturers where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_manufacturer_id'] ?>"><?= htmlText($row['description']) ?></option>
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
                    <div class='basic-form-line-messages'><span class="help-label">Use this location for inventory counts. Leave blank for total of all locations.</span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_large_map_row">
                    <input type="checkbox" id="large_map" name="large_map" value="1"><label class="checkbox-label" for="large_map">MAP Price is larger than the MAP price in the Coreware catalog</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_in_stock_only_row">
                    <input checked type="checkbox" id="in_stock_only" name="in_stock_only" value="1"><label class="checkbox-label" for="in_stock_only">Exclude out-of-stock</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_internal_use_only_row">
                    <input checked type="checkbox" id="internal_use_only" name="internal_use_only" value="1"><label class="checkbox-label" for="internal_use_only">Exclude Internal Use Only</label>
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("productlisting.pdf");
                $('#_pdf_form').append($(input));
                $("#_pdf_form").attr("action", "/reportpdf.php").attr("method", "POST").submit();
                return false;
            });
            $(document).on("tap click", "#create_report,#refresh_button", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    var reportType = $("#report_type").val();
                    if (reportType == "export" || reportType == "file") {
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

$pageObject = new ProductListingPage();
$pageObject->displayPage();
