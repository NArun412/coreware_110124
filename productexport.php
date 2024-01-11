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

$GLOBALS['gPageCode'] = "PRODUCTEXPORT";
require_once "shared/startup.inc";

class ProductExportPage extends Page implements BackgroundReport {

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

		ob_start();

		$productFacetCodes = array();
		$products = array();
		$resultSet = executeReadQuery("select *,(select group_concat(product_category_code) from product_categories where product_category_id in (select product_category_id from product_category_links where product_id = products.product_id)) as product_categories," .
			"(select group_concat(product_tag_code) from product_tags where product_tag_id in (select product_tag_id from product_tag_links where (expiration_date is null or expiration_date > current_date) and product_id = products.product_id)) as product_tags," .
			"(select group_concat(concat_ws('||', product_facet_code, facet_value) SEPARATOR '||||') from product_facet_values join product_facets using(product_facet_id) join product_facet_options using(product_facet_option_id) where " .
			"product_id = products.product_id) product_facets from products join product_data using (product_id) where products.client_id = ? and inactive = 0" .
			(!empty($whereStatement) ? " and " . $whereStatement : ""), $parameters);
		while ($row = getNextRow($resultSet)) {
		    $products[] = $row;
		    if (!empty($row['product_facets'])) {
			    $facets = explode("||||", $row['product_facets']);
			    foreach ($facets as $thisFacet) {
				    if (empty($thisFacet)) {
					    continue;
				    }
				    $facetParts = explode("||", $thisFacet);
				    if (empty($facetParts[0]) || empty($facetParts[1])) {
					    continue;
				    }
				    $productFacetCodes[$facetParts[0]] = true;
			    }
		    }
        }

		$skipFields = array("file_id", "image_id", "remote_identifier", "error_message", "client_id", "product_distributor_id", "product_facets","version");
		$headers = array();
		$productCatalog = new ProductCatalog();
		$productFacets = array();
		foreach ($products as $row) {
			if (empty($headers)) {
				foreach ($row as $fieldName => $fieldValue) {
					if (!in_array($fieldName, $skipFields)) {
						$headers[] = $fieldName;
					}
				}
				$facetSet = executeQuery("select * from product_facets where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
				while ($facetRow = getNextRow($facetSet)) {
				    if (!$productFacetCodes[$facetRow['product_facet_code']]) {
				        continue;
                    }
					$productFacets[] = $facetRow;
					$headers[] = $facetRow['description'];
				}
				echo createCsvRow($headers);
			}
			$thisRow = array();
			foreach ($row as $fieldName => $fieldValue) {
			    if (!in_array($fieldName,$skipFields)) {
			        $thisRow[] = $fieldValue;
                }
            }
			$facetValues = array();
			$facets = explode("||||",$row['product_facets']);
			foreach ($facets as $thisFacet) {
			    if (empty($thisFacet)) {
			        continue;
                }
			    $facetParts = explode("||",$thisFacet);
			    if (empty($facetParts[0]) || empty($facetParts[1])) {
			        continue;
                }
			    $facetValues[$facetParts[0]] = $facetParts[1];
            }
			foreach ($productFacets as $thisFacetRow) {
			    $thisRow[] = $facetValues[$thisFacetRow['product_facet_code']];
            }
			echo createCsvRow($thisRow);
		}
		$returnArray['export_headers'] = array();
		$returnArray['export_headers'][] = "Content-Type: text/csv";
		$returnArray['export_headers'][] = "Content-Disposition: attachment; filename=\"products.csv\"";
		$returnArray['export_headers'][] = 'Cache-Control: must-revalidate, post-check=0, pre-check=0';
		$returnArray['export_headers'][] = 'Pragma: public';
		$returnArray['filename'] = "products.csv";
		$returnArray['report_export'] = ob_get_clean();
		return $returnArray;
	}

	function mainContent() {

# The report form is where the user can set parameters for how the report would be run.

		?>
        <div id="report_parameters">
            <form id="_report_form" name="_report_form">

				<?php getStoredReports() ?>

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

				<?php storedReportDescription() ?>

                <div class="basic-form-line">
                    <button tabindex="10" id="create_report">Create Export</button>
                </div>

            </form>
        </div>
        <div id="_button_row">
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
            $(document).on("tap click", "#create_report", function () {
                if ($("#_report_form").validationEngine("validate")) {
                    $("#_report_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_report").attr("method", "POST").submit();
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

$pageObject = new ProductExportPage();
$pageObject->displayPage();
