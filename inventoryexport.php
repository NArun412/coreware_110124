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

$GLOBALS['gPageCode'] = "INVENTORYEXPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class InventoryExportPage extends Page implements BackgroundReport {

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
		$distributorWhereStatement = "(product_distributor_id is null or primary_location = 1)";
		if (strlen($_POST['distributors']) > 0) {
			$distributorWhereStatement = ($_POST['distributors'] == 1 ? "(product_distributor_id is not null and primary_location = 1)" : "(product_distributor_id is null)");
		}
		$locationsWhereStatement = (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_location = 1 and user_id = " . $GLOBALS['gUserId'] . " and " : "user_location = 0 and ") .
			"inactive = 0 and internal_use_only = 0 and ignore_inventory = 0 and " . $distributorWhereStatement . " and client_id = ?";
		$parameters = array($GLOBALS['gClientId']);
		$locationsParameters = array($GLOBALS['gClientId']);

		if (!empty($_POST['location_id'])) {
			if (!empty($locationsWhereStatement)) {
				$locationsWhereStatement .= " and ";
			}
			$locationsWhereStatement .= "location_id = ?";
			$locationsParameters[] = $_POST['location_id'];
		}

		if (!empty($_POST['product_id'])) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "product_id = ?";
			$parameters[] = $_POST['product_id'];
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

		$locationArray = array();
		$locationSet = executeQuery("select * from locations"
			. (empty($locationsWhereStatement) ? "" : " where " . $locationsWhereStatement), $locationsParameters);
		while ($locationRow = getNextRow($locationSet)) {
			$locationArray[$locationRow['location_id']] = array('description' => $locationRow['description'],
				'is_distributor' => !empty($locationRow['product_distributor_id']));
		}
		freeResult($locationSet);

        $productsArray = array();
        $productIds = array();
        $tempTableName = "temp_product_ids_" . getRandomString(10);
        executeQuery("create table " . $tempTableName . "(product_id int not null,primary key (product_id))");
        $resultSet = executeQuery("select * from products left outer join product_data using (product_id) where inactive = 0 and products.client_id = ?" .
            (!empty($whereStatement) ? " and " . $whereStatement : ""), $parameters);
        while($row = getNextRow($resultSet)) {
            $productsArray[$row['product_id']] = $row;
            $productIds[] = $row['product_id'];
        }
        freeResult($resultSet);
        foreach(array_chunk($productIds, 1000) as $idArray) {
            $resultSet = executeQuery("insert into " . $tempTableName . " (product_id) values (" . implode("),(", $idArray) . ")");
        }

        $inventoryArray = array();
		$inventorySet = executeQuery("select * from product_inventories where product_id in (select product_id from " . $tempTableName .") and location_id in (" . implode(",", array_keys($locationArray)) . ")");
		while ($inventoryRow = getNextRow($inventorySet)) {
			$inventoryArray[$inventoryRow['product_id']][$inventoryRow['location_id']] = $inventoryRow;
		}
		freeResult($inventorySet);
        executeQuery("drop table " . $tempTableName);

		$fieldNames = array('UPC', 'Product Code', 'Description', 'Manufacturer', 'SKU', 'MAP');
		foreach ($locationArray as $thisLocation) {
			$fieldNames[] = $thisLocation['description'] . " Qty";
			if (!$thisLocation['is_distributor']) {
				$fieldNames[] = $thisLocation['description'] . " Bin";
			}
			$fieldNames[] = $thisLocation['description'] . " Cost";
		}

		$returnArray['report_title'] = "Product Inventory Export";
		if ($exportReport) {
			$returnArray['export_headers'] = array();
			$returnArray['export_headers'][] = "Content-Type: text/csv";
			$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"inventory_export.csv\"";
			$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
			$returnArray['export_headers'][] = 'Pragma: public';
			$returnArray['filename'] = "inventory_export.csv";

			$isFirst = true;
			foreach ($fieldNames as $thisField) {
				echo ($isFirst ? "" : ",") . '"' . $thisField . '"';
				$isFirst = false;
			}
			echo "\n";
		} else {
			?>
            <table class="grid-table">
            <tr>
				<?php
				foreach ($fieldNames as $thisField) {
					echo "<th>" . $thisField . "</th>";
				}
				?>
            </tr>
			<?php
		}
        foreach($productsArray as $row) {
			$productId = $row['product_id'];
			// Skip out of stock products
			$inStock = false;
			foreach (array_keys($locationArray) as $locationId) {
				if ($inventoryArray[$productId][$locationId]['quantity'] > 0) {
					$inStock = true;
					break;
				}
			}
			if (!$inStock) {
				continue;
			}
			$upcCode = $row['upc_code'];
			$productCode = $row['product_code'];
			$description = $row['description'];
			$manufacturer = getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $row['product_manufacturer_id']);
			$sku = $row['manufacturer_sku'];
			$map = $row['manufacturer_advertised_price'];

			if ($exportReport) {
				echo '"' . str_replace('"', '""', $upcCode) . '",';
				echo '"' . str_replace('"', '""', $productCode) . '",';
				echo '"' . str_replace('"', '""', $description) . '",';
				echo '"' . str_replace('"', '""', $manufacturer) . '",';
				echo '"' . str_replace('"', '""', $sku) . '",';
				echo '"' . (empty($map) || $map == 0 ? "" : number_format($map, 2, ".", ",")) . '",';
				$isFirst = true;
				foreach (array_keys($locationArray) as $locationId) {
					if (!$isFirst) {
						echo ",";
					}
					echo '"' . str_replace('"', '""', $inventoryArray[$productId][$locationId]['quantity']) . '",';
					if (!$locationArray[$locationId]['is_distributor']) {
						echo '"' . str_replace('"', '""', $inventoryArray[$productId][$locationId]['bin_number']) . '",';
					}
					echo '"' . (empty($inventoryArray[$productId][$locationId]['location_cost']) ? "" : number_format($inventoryArray[$productId][$locationId]['location_cost'], 2, ".", ",")) . '"';
					$isFirst = false;
				}
				echo "\r\n";
			} else {
				?>
                <tr>
                    <td><?= $upcCode ?></td>
                    <td><?= htmlText($productCode) ?></td>
                    <td><?= htmlText($description) ?></td>
                    <td><?= htmlText($manufacturer) ?></td>
                    <td><?= htmlText($sku) ?></td>
                    <td class="align-right"><?= (empty($map) || $map == 0 ? "" : number_format($map, 2, ".", ",")) ?></td>
					<?php
					foreach (array_keys($locationArray) as $locationId) {
						echo '<td class="align-right">' . str_replace('"', '""', $inventoryArray[$productId][$locationId]['quantity']) . '</td>';
						if (!$locationArray[$locationId]['is_distributor']) {
							echo '<td class="align-right">' . str_replace('"', '""', $inventoryArray[$productId][$locationId]['bin_number']) . '</td>';
						}
						echo '<td class="align-right">' . (empty($inventoryArray[$productId][$locationId]['location_cost']) ? "" : number_format($inventoryArray[$productId][$locationId]['location_cost'], 2, ".", ",")) . '</td>';
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
                    <label for="report_type">Output Type</label>
                    <select tabindex="10" id="report_type" name="report_type">
                        <option value="web">Web</option>
                        <option value="csv">CSV</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_location_id_row">
                    <label for="location_id">Location</label>
                    <select tabindex="10" id="location_id" name="location_id">
                        <option value="">[All]</option>
						<?php
						$resultSet = executeReadQuery("select * from locations where " . (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_location = 1 and user_id = " . $GLOBALS['gUserId'] . " and " : "user_location = 0 and ") . "client_id = ? and ignore_inventory = 0 and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['location_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_distributors_row">
                    <span class="checkbox-label">Product Distributor Locations:</span>
                    <input type="radio" checked tabindex="10" id="distributors_include" name="distributors" value=""><label class="checkbox-label" for="distributors_include">Include</label>
                    <input type="radio" tabindex="10" id="distributors_hide" name="distributors" value="0"><label class="checkbox-label" for="distributors_hide">Hide</label>
                    <input type="radio" tabindex="10" id="distributors_only" name="distributors" value="1"><label class="checkbox-label" for="distributors_only">Only Distributor Locations</label>
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

                <div class="basic-form-line" id="_product_id_row">
                    <label for="product_id">Product</label>
                    <input class="" type="hidden" id="product_id" name="product_id" value="">
                    <input tabindex="10" class="autocomplete-field" type="text" size="50" name="product_id_autocomplete_text" id="product_id_autocomplete_text" data-autocomplete_tag="products">
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
                input = $("<input>").attr("type", "hidden").attr("name", "filename").val("inventory.pdf");
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

$pageObject = new InventoryExportPage();
$pageObject->displayPage();
