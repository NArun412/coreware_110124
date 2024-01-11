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

/*
Create a form for doing a quick filter and search of products. The form will include Categories, Manufacturers, Product Tags,
an in stock checkbox, price ranges, state compliance, and a text search field. If no department code is specified, all departments
will be included. Departments will be displayed as buttons across the top of the control.

For each department, the fields included can be defined. If nothing is defined, then all fields will be included. The order of the fields
defined will be honored. Possible fields are:

product_manufacturers
product_categories
product_facet_code-XXXXX - XXXXX is the product facet code that will be used for options
product_tags
in_stock_only
price
state_compliance
search_text

%module:product_search[:product_department_codes=DDDDD,EEEEE,FFFFF][:not_product_department_codes=GGGGG][:element_id=_product_search_wrapper][product_department_code-DDDD=product_categories,product_manufacturers,product_tags,in_stock_only,available_in_store,price,state_compliance,search_text,product_facet_code-XXXXX,product_facet_code-YYYYY]%

*/

class ProductSearchPageModule extends PageModule {

	var $iPriceRanges = array(array("minimum_cost" => 0, "maximum_cost" => 99.99, "label" => "Under $100"),
		array("minimum_cost" => 100, "maximum_cost" => 199.99, "label" => "$100-200"),
		array("minimum_cost" => 200, "maximum_cost" => 499.99, "label" => "$200-500"),
		array("minimum_cost" => 500, "maximum_cost" => 999.99, "label" => "$500-1000"),
		array("minimum_cost" => 1000, "maximum_cost" => 99999999.99, "label" => "Over $1000")
	);

	function createContent() {
		$elementWrapperId = $this->iParameters['element_id'];
		if (empty($elementWrapperId)) {
			$elementWrapperId = "_product_search_wrapper";
		}
		$departmentCodes = array_map("strtoupper", array_filter(explode(",", $this->iParameters['product_department_codes'])));
		$resultSet = executeQuery("select * from product_departments where client_id = ? and inactive = 0 and internal_use_only = 0 order by sort_order,description", $GLOBALS['gClientId']);
		$productDepartments = array();
		while ($row = getNextRow($resultSet)) {
			if (empty($departmentCodes) || in_array($row['product_department_code'], $departmentCodes)) {
				$productDepartments[] = $row;
			}
		}
		$notDepartmentCodes = array_map("strtoupper", array_filter(explode(",", $this->iParameters['not_product_department_codes'])));
		$useDepartments = array();
		foreach ($productDepartments as $thisDepartment) {
			if (in_array($thisDepartment['product_department_code'], $notDepartmentCodes)) {
				continue;
			}
			$useDepartments[] = $thisDepartment;
		}
		if (empty($useDepartments)) {
			return;
		}
		?>
        <div id="<?= $elementWrapperId ?>" class='product-search-page-module-wrapper'>
            <div class='product-search-departments<?= count($useDepartments) == 1 ? " hidden" : "" ?>'>
				<?php
				$firstOne = true;
				foreach ($useDepartments as $thisDepartment) {
					?>
                    <button id="_<?= $elementWrapperId ?>_department_id_<?= $thisDepartment['product_department_id'] ?>" class="product-search-page-module-department-button<?= ($firstOne ? " selected" : "") ?>"><?= htmlText($thisDepartment['description']) ?></button>
					<?php
					$firstOne = false;
				}
				?>
            </div>
            <div class='product-search-page-module-forms'>
				<?php
				$firstOne = true;
				foreach ($useDepartments as $thisDepartment) {
					$fieldList = array_filter(explode(",", $this->iParameters['product_department_code-' . strtolower($thisDepartment['product_department_code'])]));
					if (empty($fieldList)) {
						$fieldList = array("product_manufacturers", "product_categories", "product_tags", "in_stock_only", "available_in_store", "price", "state_compliance", "search_text");
					}
					?>
                    <div class='product-search-page-module-form-wrapper<?= ($firstOne ? "" : " hidden") ?>' id="_<?= $elementWrapperId ?>_department_id_<?= $thisDepartment['product_department_id'] ?>_form_wrapper">
                        <h2 class='product-search-page-module-title'>Search <?= htmlText($thisDepartment['description']) ?></h2>
                        <form id="_<?= $elementWrapperId ?>_department_id_<?= $thisDepartment['product_department_id'] ?>_form_wrapper" class='product-search-page-module-form'>
                            <input class="product-search-page-module product-department-id" type='hidden' id="<?= $elementWrapperId . "_product_department_id" ?>" name="<?= $elementWrapperId . "_product_department_id" ?>" value="<?= $thisDepartment['product_department_id'] ?>">
                            <input class="product-search-page-module field-list no-clear" type='hidden' id="<?= $elementWrapperId . "_field_list" ?>" name="<?= $elementWrapperId . "_field_list" ?>" value="<?= implode(",", $fieldList) ?>">
							<?php
							$firstOne = false;
							foreach ($fieldList as $thisFieldName) {
								switch ($thisFieldName) {
									case "product_manufacturers":
										echo createFormLineControl("products", "product_manufacturer_id", array("data_type" => "select", "empty_text" => "[All]", "classes" => "product-search-page-module product-manufacturer-id",
											"not_null" => "false", "column_name" => $elementWrapperId . "_product_manufacturer_id", "filter_where" => "product_manufacturer_id in (select product_manufacturer_id from products where " .
												"product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from " .
												"product_category_departments where product_department_id = " . $thisDepartment['product_department_id'] . ") or " .
												"product_category_id in (select product_category_id from product_category_group_links where product_category_group_id in (select product_category_group_id from " .
												"product_category_group_departments where product_department_id = " . $thisDepartment['product_department_id'] . "))))"));
										break;
									case "product_categories":
										echo createFormLineControl("product_category_links", "product_category_id", array("classes" => "product-search-page-module product-category-id", "empty_text" => "[All]",
											"not_null" => "false", "column_name" => $elementWrapperId . "_product_category_id", "filter_where" => "(product_category_id in (select product_category_id from " .
												"product_category_departments where product_department_id = " . $thisDepartment['product_department_id'] . ") or " .
												"product_category_id in (select product_category_id from product_category_group_links where product_category_group_id in (select product_category_group_id from " .
												"product_category_group_departments where product_department_id = " . $thisDepartment['product_department_id'] . ")))"));
										break;
									case "product_tags":
										echo createFormLineControl("product_tag_links", "product_tag_id", array("classes" => "product-search-page-module product-tag-id", "empty_text" => "[All]", "not_null" => "false", "column_name" => $elementWrapperId . "_product_tag_id"));
										break;
									case "in_stock_only":
										?>
                                        <div class='form-line' id='_<?= $elementWrapperId ?>_in_stock_only_row'>
                                            <input tabindex="10" class="product-search-page-module in-stock-only" type='checkbox' id='<?= $elementWrapperId ?>_in_stock_only' name='<?= $elementWrapperId ?>_in_stock_only' value='1'><label class='checkbox-label' for='<?= $elementWrapperId ?>_in_stock_only'>Only show products in-stock</label>
                                        </div>
										<?php
										break;
									case "available_in_store":
										$resultSet = executeQuery("select location_id from locations where inactive = 0 and internal_use_only = 0 and client_id = ? and product_distributor_id is null and not_searchable = 0 and warehouse_location = 0 and ignore_inventory = 0", $GLOBALS['gClientId']);
										if ($resultSet['row_count'] == 1) {
											if ($row = getNextRow($resultSet)) {
												$locationId = $row['location_id'];
											}
											?>
                                            <div class='form-line' id='_<?= $elementWrapperId ?>_row'>
                                                <input tabindex="10" class="product-search-page-module available-in-store" type='checkbox' id='<?= $elementWrapperId ?>_location_id' name='<?= $elementWrapperId ?>_location_id' value='<?= $locationId ?>'><label class='checkbox-label' for='<?= $elementWrapperId ?>_location_id'>Available In-store</label>
                                            </div>
											<?php
										}
										break;
									case "price":
										?>
                                        <div class='form-line' id='_<?= $elementWrapperId ?>_price_row'>
                                            <label>Price</label>
                                            <select tabindex="10" class="product-search-page-module sale-price" id='<?= $elementWrapperId ?>_price' name='<?= $elementWrapperId ?>_price'>
                                                <option value=''>[All]</option>
												<?php
												foreach ($this->iPriceRanges as $thisPriceRange) {
													?>
                                                    <option value='<?= $thisPriceRange['minimum_cost'] . "-" . $thisPriceRange['maximum_cost'] ?>'><?= $thisPriceRange['label'] ?></option>
													<?php
												}
												?>
                                            </select>
                                        </div>
										<?php
										break;
									case "state_compliance":
										$states = array();
										$resultSet = executeQuery("select distinct state from product_category_restrictions where state is not null and length(state) = 2 and product_category_id in (select product_category_id from product_categories where client_id = ?)", $GLOBALS['gClientId']);
										while ($row = getNextRow($resultSet)) {
											if (!in_array($row['state'], $states)) {
												$states[] = $row['state'];
											}
										}
										$resultSet = executeQuery("select distinct state from product_department_restrictions where state is not null and length(state) = 2 and product_department_id in (select product_department_id from product_departments where client_id = ?)", $GLOBALS['gClientId']);
										while ($row = getNextRow($resultSet)) {
											if (!in_array($row['state'], $states)) {
												$states[] = $row['state'];
											}
										}
										$resultSet = executeQuery("select distinct state from product_restrictions where state is not null and length(state) = 2 and product_id in (select product_id from products where client_id = ?)", $GLOBALS['gClientId']);
										while ($row = getNextRow($resultSet)) {
											if (!in_array($row['state'], $states)) {
												$states[] = $row['state'];
											}
										}
										if (!empty($states)) {
											sort($states);
											?>
                                            <div class='form-line' id='_<?= $elementWrapperId ?>_state_compliance_row'>
                                                <label>Compliant in State</label>
                                                <select tabindex="10" class="product-search-page-module state-compliance" id='<?= $elementWrapperId ?>_state_compliance' name='<?= $elementWrapperId ?>_state_compliance'>
                                                    <option value=''>[Any]</option>
													<?php
													foreach (getStateArray() as $stateCode => $stateName) {
														if (!in_array($stateCode, $states)) {
															continue;
														}
														?>
                                                        <option value='<?= $stateCode ?>'><?= $stateName ?></option>
														<?php
													}
													?>
                                                </select>
                                            </div>
											<?php
										}
										break;
									case "search_text":
										echo createFormLineControl("product_search_words", "search_term", array("classes" => "product-search-page-module search-text", "not_null" => "false", "column_name" => $elementWrapperId . "_search_text"));
										break;
									default:
										if (!startsWith($thisFieldName, "product_facet_code-")) {
											break;
										}
										$productFacetCode = strtoupper(substr($thisFieldName, strlen("product_facet_code-")));
										$productFacet = getMultipleFieldsFromId(array("description", "product_facet_id"), "product_facets", "product_facet_code",
											$productFacetCode, "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
										if (empty($productFacet)) {
											break;
										}
										echo createFormLineControl("product_facet_values", "product_facet_option_id",
                                            array("data-product_facet_id" => $productFacet['product_facet_id'], "data-product_facet_code" => $productFacetCode, "data_type" => "select", "empty_text" => "[All]", "form_label" => $productFacet['description'], "classes" => "product-search-page-module product-facet-option-id", "not_null" => "false",
                                                "filter_where" => "product_facet_id = " . $productFacet['product_facet_id'] . " and product_facet_option_id in (select product_facet_option_id from product_facet_values where product_facet_id = " . $productFacet['product_facet_id'] . ")",
                                                "column_name" => $elementWrapperId . "_product_facet_option_id-" . $productFacet['product_facet_id']));
										break;
								}
							}
							?>
                            <p class='product-search-result-count-wrapper hidden'><span class='product-search-result-count'></span> results found.</p>
                            <p class='product-search-result-searching-wrapper hidden'>Searching...</p>
                            <p class='product-search-button-wrapper'>
                                <button id="<?= $elementWrapperId . "_product_search_view_results" ?>" class='product-search-view-results'>View Results</button>
                                <button id="<?= $elementWrapperId . "_product_search_clear_form" ?>" class='product-search-clear-form'>Clear Form</button>
                            </p>
                        </form>
                    </div>
				<?php } ?>
            </div>
        </div>
		<?php
	}
}
