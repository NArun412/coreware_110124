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

$GLOBALS['gPageCode'] = "MERGEPRODUCTCATEGORIES";
require_once "shared/startup.inc";

class MergeProductCategoriesPage extends Page {
	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "merge_categories":
				$oldProductCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $_POST['old_product_category_id'], "internal_use_only = 1");
				$newProductCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $_POST['new_product_category_id']);
				if (empty($oldProductCategoryId) || empty($newProductCategoryId)) {
					$returnArray['error_message'] = "Invalid Product Category... Remember, the duplicate category has to be marked Internal Use Only";
					ajaxResponse($returnArray);
					break;
				}
				if ($oldProductCategoryId == $newProductCategoryId) {
					$returnArray['error_message'] = "Old & New Product Categories must not be the same";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$updateTables = array(
					array("table_name" => "product_category_links", "column_name" => "product_id"),
					array("table_name" => "auction_item_product_category_links", "column_name" => "auction_item_id"),
					array("table_name" => "ffl_category_restrictions", "column_name" => "federal_firearms_licensee_id"),
					array("table_name" => "gunbroker_product_categories", "column_name" => "category_identifier"),
					array("table_name" => "postal_code_tax_rates", "column_name" => "country_id", "secondary_column_name" => "postal_code"),
					array("table_name" => "product_facet_categories", "column_name" => "product_facet_id"),
					array("table_name" => "pricing_structure_category_quantity_discounts", "column_name" => "pricing_structure_id"),
					array("table_name" => "product_category_cannot_sell_distributors", "column_name" => "product_distributor_id"),
					array("table_name" => "product_category_departments", "column_name" => "product_department_id"),
					array("table_name" => "product_category_group_links", "column_name" => "product_category_group_id"),
					array("table_name" => "product_category_restrictions", "column_name" => "country_id", "secondary_column_name" => "state", "tertiary_column_name" => "postal_code"),
					array("table_name" => "product_category_shipping_carriers", "column_name" => "shipping_carrier_id"),
					array("table_name" => "product_category_shipping_methods", "column_name" => "shipping_method_id"),
					array("table_name" => "promotion_rewards_excluded_product_categories", "column_name" => "promotion_id"),
					array("table_name" => "promotion_rewards_product_categories", "column_name" => "promotion_id"),
					array("table_name" => "promotion_terms_excluded_product_categories", "column_name" => "promotion_id"),
					array("table_name" => "promotion_terms_product_categories", "column_name" => "promotion_id"),
					array("table_name" => "search_term_synonym_product_categories", "column_name" => "search_term_synonym_id"),
					array("table_name" => "shipping_charge_product_categories", "column_name" => "shipping_charge_id"),
					array("table_name" => "state_tax_rates", "column_name" => "country_id", "secondary_column_name" => "state")
				);

				$updateCount = $deleteCount = 0;
				foreach ($updateTables as $thisTable) {
					if ($updateCount + $deleteCount > 0) {
						$updateCount = $deleteCount = 0;
					}
					$dataTable = new DataTable($thisTable['table_name']);
					$resultSet = executeQuery("select * from " . $thisTable['table_name'] . " where product_category_id = ?", $oldProductCategoryId);
					while ($row = getNextRow($resultSet)) {
						$primaryKey = $dataTable->getPrimaryKey();
						$primaryId = getFieldFromId($primaryKey, $thisTable['table_name'], $thisTable['column_name'], $row[$thisTable['column_name']],
							"product_category_id = ?" . (empty($thisTable['secondary_column_name']) ? "" : " and " . $thisTable['secondary_column_name'] . " <=> " . makeParameter($row[$thisTable['secondary_column_name']])) .
							(empty($thisTable['tertiary_column_name']) ? "" : " and " . $thisTable['tertiary_column_name'] . " <=> " . makeParameter($row[$thisTable['tertiary_column_name']])), $newProductCategoryId);
						if (empty($primaryId)) {
							$updateSet = executeQuery("update " . $thisTable['table_name'] . " set product_category_id = ? where " . $primaryKey . " = ?", $newProductCategoryId, $row[$primaryKey]);
							if (!empty($updateSet['sql_error'])) {
								$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
								$this->iDatabase->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							} else {
								$updateCount += $updateSet['affected_rows'];
							}
						} else {
							$deleteSet = executeQuery("delete from " . $thisTable['table_name'] . " where " . $primaryKey . " = ?", $row[$primaryKey]);
							if (!empty($deleteSet['sql_error'])) {
								$returnArray['error_message'] = getSystemMessage("basic", $deleteSet['sql_error']);
								$this->iDatabase->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							} else {
								$deleteCount += $deleteSet['affected_rows'];
							}
						}
					}
				}

				$resultSet = executeQuery("update product_category_addons set product_category_id = ? where product_category_id = ?", $newProductCategoryId, $oldProductCategoryId);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("update product_distributor_conversions set primary_identifier = ? where table_name = 'product_categories' and primary_identifier = ?", $newProductCategoryId, $oldProductCategoryId);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from product_categories where product_category_id = ?", $oldProductCategoryId);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					$this->iDatabase->rollbackTransaction();
				} else {
					$this->iDatabase->commitTransaction();
					$returnArray['info_message'] = "Categories Successfully Merged";
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		?>
        <div id="_merge_categories_wrapper">
            <form id="merge_categories_form">
                <p class='highlighted-text color-red'>Merging Categories CANNOT be undone. Please be certain the two categories you choose are genuine duplicates. As a precaution, the duplicate category must first be marked Internal Use Only.</p>
				<?php echo createFormControl("product_category_links", "product_category_id", array("form_label" => "Duplicate Category", "help_label" => "This category will be removed and merged into the real one", "not_null" => true, "column_name" => "old_product_category_id")) ?>
				<?php echo createFormControl("product_category_links", "product_category_id", array("not_null" => true, "column_name" => "new_product_category_id")) ?>
                <div class='form-line'>
                    <input tabindex="10" class="validate[required]" type='checkbox' id="confirm_merge_categories" name="confirm_merge_categories" value="1"><label for="confirm_merge_categories" class='checkbox-label'>I confirm these are duplicates and should be merged</label>
                </div>
                <p>
                    <button tabindex="10" id="_execute_merge_categories_button">Merge Categories</button>
                </p>
            </form>
        </div>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#_execute_merge_categories_button", function () {
                if ($("#merge_categories_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=merge_categories", $("#merge_categories_form").serialize(), function(returnArray) {
                        if (!("error_message" in returnArray)) {
                            $("#old_product_category_id").val("");
                            $("#new_product_category_id").val("");
                            $("#confirm_merge_categories").prop("checked", false);
                        }
                    });
                }
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #_merge_categories_wrapper p {
                margin-bottom: 20px;
                max-width: 650px;
            }
        </style>
		<?php
	}
}

$pageObject = new MergeProductCategoriesPage();
$pageObject->displayPage();
