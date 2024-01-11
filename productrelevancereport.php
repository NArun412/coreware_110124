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

$GLOBALS['gPageCode'] = "PRODUCTRELEVANCEREPORT";
require_once "shared/startup.inc";

class ProductRelevanceReportPage extends Page implements BackgroundReport {

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

	public static function sortProducts($a, $b) {
		if ($a['relevance'] == $b['relevance']) {
			return 0;
		}
		return ($a['relevance'] > $b['relevance']) ? -1 : 1;
	}

	public static function getReportContent() {
		$returnArray = array();
		saveStoredReport(static::class);

		$fullName = getUserDisplayName($GLOBALS['gUserId']);

		$productCatalog = new ProductCatalog();
		$productCatalog->showOutOfStock(empty($_POST['exclude_out_of_stock']));
		$productCatalog->setSearchText($_POST['search_text']);
		$productCatalog->setSelectLimit(50000);
		$productCatalog->setIgnoreManufacturerLogo(true);
		$productCatalog->setBaseImageFilenameOnly(true);

        $productLimit = (empty($_POST['row_limit']) ? 100 : $_POST['row_limit']);

		if (!empty($_POST['product_department_id'])) {
			$productCatalog->setDepartments($_POST['product_department_id']);
		}

		if (!empty($_POST['product_category_id'])) {
			$productCatalog->setCategories($_POST['product_category_id']);
		}

		if (!empty($_POST['product_category_group_id'])) {
			$productCatalog->setCategoryGroups($_POST['product_category_group_id']);
		}

		if (!empty($_POST['product_tag_id'])) {
			$productCatalog->setTags($_POST['product_tag_id']);
		}

		$productResults = $productCatalog->getProducts();
		usort($productResults, array(static::class, "sortProducts"));

		$productIds = array();
		foreach ($productResults as $thisResult) {
			$productIds[] = $thisResult['product_id'];
		}

		$relevanceData = array();
		if (!empty($productIds)) {
			$originalSearchTerm = $_POST['search_text'];
			if ($GLOBALS['gSearchTermSynonyms'] === false) {
				ProductCatalog::getSearchTermSynonyms();
			}
			$searchTermSynonymRow = $GLOBALS['gSearchTermSynonyms'][strtoupper($_POST['search_text'])];
			if (!empty($searchTermSynonymRow)) {
				$_POST['search_text'] = $searchTermSynonymRow['search_term'];
			}

			$searchWordInfo = ProductCatalog::getSearchWords($_POST['search_text']);
			$searchWords = $searchWordInfo['search_words'];
			$parameters = array();
			$pushInStockToTop = getPreference("PUSH_IN_STOCK_TO_TOP");

			$query = "select product_id,search_multiplier," .
					"(select coalesce(sum(search_multiplier),0) from product_categories where product_category_id in (select product_category_id from product_category_links where product_id = products.product_id)) as category_search_multiplier," .
					"(select coalesce(sum(search_multiplier),0) from product_departments where product_department_id in (select product_department_id from product_category_departments where product_category_id in (select product_category_id from product_category_links where product_id = products.product_id))) as department_search_multiplier," .
					"(select coalesce(max(search_multiplier),0) from locations where location_id in (select location_id from product_inventories where product_id = products.product_id and quantity > 0)) as location_search_multiplier," .
					"(select coalesce(max(search_multiplier),0) from product_tags where product_tag_id in (select product_tag_id from product_tag_links where product_id = products.product_id and (start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date > current_date))) as product_tag_search_multiplier," .
					"(select coalesce(sum(search_multiplier),0) from product_facets where product_facet_id in (select product_facet_id from product_facet_values where product_id = products.product_id)) as facets_search_multiplier, " .
					"(select coalesce(sum(search_multiplier),0) from product_manufacturers where product_manufacturer_id = products.product_manufacturer_id) as manufacturer_search_multiplier," .
					(empty($pushInStockToTop) ? "0 as in_stock_multiplier," : "if((select count(*) from product_inventories where product_id = products.product_id and quantity > 0 and location_id in (select location_id from locations where internal_use_only = 0 and inactive = 0)) > 0,1000000,0) as in_stock_multiplier,");
			if (!empty($originalSearchTerm)) {
				$query .= "(if (products.description = ?,100,0)) as description_relevance";
				$parameters[] = $originalSearchTerm;
			} else {
				$query .= "0 as description_relevance";
			}
			if (empty($searchWords)) {
				$query .= ",(100 / if(products.sort_order = 0,.5,products.sort_order)) as relevance";
			} else {
				$query .= ",(select sum(search_value) from product_search_word_values where product_id = products.product_id and " .
						"product_search_word_id in (select product_search_word_id from product_search_words where client_id = ? and search_term in (" . implode(",", array_fill(0, count($searchWords), "?")) . "))) as relevance";
				$parameters[] = $GLOBALS['gClientId'];
				$parameters = array_merge($parameters, $searchWords);
			}
			$query .= " from products where product_id in (" . implode(",", $productIds) . ")";
			$resultSet = executeReadQuery($query, $parameters);
			while ($row = getNextRow($resultSet)) {
				$relevanceData[$row['product_id']] = $row;
			}
		}

		ob_start();
		?>
		<table class="grid-table" id="product_table">
			<tr>
				<th rowspan="2">UPC</th>
				<th rowspan="2">Description</th>
				<th colspan="3">Relevance</th>
				<th colspan="9">Multipliers</th>
				<th rowspan="2">Relevance<br>Number</th>
			</tr>
			<tr>
				<th>Search</th>
				<th>Description</th>
				<th>Total</th>
				<th>Product</th>
				<th>Category</th>
				<th>Department</th>
				<th>Location</th>
				<th>Product Tag</th>
				<th>Facets</th>
				<th>Manufacturer</th>
				<th>In Stock</th>
				<th>Total</th>
			</tr>
			<?php
			$totalRelevance = 0;
			foreach ($productResults as $row) {
				$totalRelevance++;
                if ($totalRelevance > $productLimit) {
	                break;
                }
				$productRelevance = $relevanceData[$row['product_id']];
				?>
				<tr>
					<td class='upc-code'><?= $row['upc_code'] ?></td>
					<td class='product-description'><a href='/productmaintenance.php?url_page=show&clear_filter=true&primary_id=<?= $row['product_id'] ?>' target='_blank'><?= htmlText($row['description']) ?></a></td>
					<td class='align-right'><?= showSignificant($row['relevance'], 2) ?></td>
					<td class='align-right'><?= showSignificant($row['description_relevance'], 2) ?></td>
					<td class='align-right'><?= showSignificant($row['relevance'] + $row['description_relevance'], 2) ?></td>
					<td class='align-right'><?= showSignificant($productRelevance['search_multiplier'], 2) ?></td>
					<td class='align-right'><?= showSignificant($productRelevance['category_search_multiplier'], 2) ?></td>
					<td class='align-right'><?= showSignificant($productRelevance['department_search_multiplier'], 2) ?></td>
					<td class='align-right'><?= showSignificant($productRelevance['location_search_multiplier'], 2) ?></td>
					<td class='align-right'><?= showSignificant($productRelevance['product_tag_search_multiplier'], 2) ?></td>
					<td class='align-right'><?= showSignificant($productRelevance['facets_search_multiplier'], 2) ?></td>
					<td class='align-right'><?= showSignificant($productRelevance['manufacturer_search_multiplier'], 2) ?></td>
					<td class='align-right'><?= showSignificant($productRelevance['in_stock_multiplier'], 2) ?></td>
					<td class='align-right'><?= showSignificant($row['relevance_multiplier'], 2) ?></td>
					<td class='align-right'><?= $totalRelevance ?></td>
				</tr>
				<?php
			}
			?>
		</table>
		<?php
		$reportContent = ob_get_clean();
		$returnArray['report_content'] = $reportContent;
		$returnArray['report_title'] = "Product Relevance Report";
		return $returnArray;
	}

	function mainContent() {
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
					<label for="product_tag_id">Product Tag</label>
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

				<div class="basic-form-line" id="_search_text_row">
					<label for="search_text">Search Text</label>
					<input size="60" tabindex="10" type="text" id="search_text" name="search_text">
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<div class="basic-form-line" id="_row_limit_row">
					<label for="row_limit">Maximum Products</label>
					<span class='help-label'>Default is 100</span>
					<input tabindex="10" type="text" class='validate[custom[integer]] align-right' size='8' id="row_limit" name="row_limit">
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<div class="basic-form-line" id="_exclude_out_of_stock_row">
					<input tabindex="10" type="checkbox" id="exclude_out_of_stock" name="exclude_out_of_stock" value="1"><label for='exclude_out_of_stock' class='checkbox-label'>Hide Out of Stock</label>
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
				const $pdfForm = $("#_pdf_form");
				$pdfForm.html("");
				let input = $("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html());
				$pdfForm.append($(input));
				input = $("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html());
				$pdfForm.append($(input));
				input = $("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html());
				$pdfForm.append($(input));
				input = $("<input>").attr("type", "hidden").attr("name", "filename").val("productrelevance.pdf");
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
						loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_report", $reportForm.serialize(), function (returnArray) {
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

			.product-description {
				width: 500px;
			}

			.upc-code {
				max-width: 250px;
				white-space: nowrap;
				overflow: hidden;
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

$pageObject = new ProductRelevanceReportPage();
$pageObject->displayPage();
