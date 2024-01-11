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

$GLOBALS['gPageCode'] = "MERGEFACETS";
require_once "shared/startup.inc";

$GLOBALS['gDefaultAjaxTimeout'] = 600000;

class MergeFacetsPage extends Page {

	private $iErrorMessage = "";

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "merge_facets":
				$startTime = getMilliseconds();
				$oldProductFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_id", $_POST['old_product_facet_id'], "internal_use_only = 1");
				$newProductFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_id", $_POST['new_product_facet_id']);
				if (empty($oldProductFacetId) || empty($newProductFacetId)) {
					$returnArray['error_message'] = "Invalid Product Facet... Remember, the duplicate facet has to be marked Internal Use Only";
					ajaxResponse($returnArray);
					break;
				}
				if ($oldProductFacetId == $newProductFacetId) {
					$returnArray['error_message'] = "Old & New Product Facets must not be the same";
					ajaxResponse($returnArray);
					break;
				}

				$this->iDatabase->startTransaction();
				if ($this->executeMergeFacets($oldProductFacetId, $newProductFacetId)) {
					$endTime = getMilliseconds();
					$returnArray['info_message'] = sprintf("Facet %s (ID %s) successfully merged into %s (ID %s), Took %s seconds", $_POST['old_product_facet_id_autocomplete_text'],
						$_POST['old_product_facet_id'], $_POST['new_product_facet_id_autocomplete_text'], $_POST['new_product_facet_id'], round(($endTime - $startTime) / 1000, 2));
					$this->iDatabase->commitTransaction();
					addProgramLog($returnArray['info_message']);
				} else {
					$returnArray['error_message'] = $this->iErrorMessage;
					$this->iDatabase->rollbackTransaction();
				}
				ajaxResponse($returnArray);
				break;
			case "merge_facet_options":
				/*
				Merge Facet options
				Update product_facet_values and change old product_facet_option_id to new one
				delete old value from product_value_options
				 */
				$productFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_id", $_POST['filter_product_facet_id']);
				$oldProductFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_option_id", $_POST['old_product_facet_option_id'],
					"product_facet_id = ?", $productFacetId);
				$oldFacetValue = getFieldFromId("facet_value", "product_facet_options", "product_facet_option_id", $oldProductFacetOptionId,
					"product_facet_id = ?", $productFacetId);
				$newProductFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_option_id", $_POST['new_product_facet_option_id'],
					"product_facet_id = ?", $productFacetId);
				if (empty($oldProductFacetOptionId) || empty($newProductFacetOptionId)) {
					$returnArray['error_message'] = "Invalid Product Facet Option";
					ajaxResponse($returnArray);
					break;
				}

				# make sure there isn't already a facet value for this facet. If so, just delete (using data table to record the deletion)

				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$dataTable = new DataTable("product_facet_values");
				$resultSet = executeQuery("select * from product_facet_values where product_facet_option_id = ?", $oldProductFacetOptionId);
				while ($row = getNextRow($resultSet)) {
					$productFacetValueId = getFieldFromId("product_facet_value_id", "product_facet_values", "product_id", $row['product_id'],
						"product_facet_id = ? and product_facet_option_id = ?", $row['product_facet_id'], $newProductFacetOptionId);
					if (empty($productFacetValueId)) {
						executeQuery("update product_facet_values set product_facet_option_id = ? where product_facet_value_id = ?", $newProductFacetOptionId, $row['product_facet_value_id']);
					} else {
						$dataTable->deleteRecord(array("primary_id" => $row['product_facet_value_id']));
					}
				}
				$resultSet = executeQuery("delete from product_facet_options where product_facet_option_id = ?", $oldProductFacetOptionId);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					$this->iDatabase->rollbackTransaction();
				} else {
					$this->iDatabase->commitTransaction();
					$returnArray['info_message'] = "Facet Options Successfully Merged";
				}
				$productFacetCode = getFieldFromId("product_facet_code", "product_facets", "product_facet_id", $productFacetId);
				if (!empty($productFacetCode)) {
					$productDistributorConversionRow = getRowFromId("product_distributor_conversions", "table_name", "product_facet_options",
						"original_value = ? and product_distributor_id is null and original_value_qualifier = ?", $oldFacetValue, $productFacetCode);
					if (empty($productDistributorConversionRow)) {
						executeQuery("insert into product_distributor_conversions (client_id,table_name,original_value,original_value_qualifier,primary_identifier) values (?,'product_facet_options',?,?,?)",
							$GLOBALS['gClientId'], $oldFacetValue, $productFacetCode, $newProductFacetOptionId);
					} else if ($newProductFacetOptionId != $productDistributorConversionRow['primary_identifier']) {
						executeQuery("update product_distributor_conversions set primary_identifier = ? where product_distributor_conversion_id = ?", $newProductFacetOptionId, $productDistributorConversionRow['product_distributor_conversion_id']);
					}
				}

				ajaxResponse($returnArray);
				break;
		}
	}

	private function executeMergeFacets($oldProductFacetId, $newProductFacetId) {
		// remove old facet from categories
		$deleteSet = executeQuery("delete from product_facet_categories where product_facet_id = ?", $oldProductFacetId);
		if (!empty($deleteSet['sql_error'])) {
			$this->iErrorMessage = ($GLOBALS['gUserRow']['superuser_flag'] ? $deleteSet['sql_error'] : getSystemMessage("basic", $deleteSet['sql_error']));
			return false;
		}
		// replace facet options with new facet
		$resultSet = executeQuery("select * from product_facet_options where product_facet_id = ?", $oldProductFacetId);
		while ($row = getNextRow($resultSet)) {
			$productFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_id", $newProductFacetId,
				"facet_value = ?", $row['facet_value']);
			if (empty($productFacetOptionId)) {
				$updateSet = executeQuery("update product_facet_options set product_facet_id = ? where product_facet_option_id = ?", $newProductFacetId, $row['product_facet_option_id']);
				if (!empty($updateSet['sql_error'])) {
					$this->iErrorMessage = ($GLOBALS['gUserRow']['superuser_flag'] ? $updateSet['sql_error'] : getSystemMessage("basic", $updateSet['sql_error']));
					return false;
				}
			} else {
				$updateSet = executeQuery("update product_facet_values set product_facet_option_id = ? where product_facet_option_id = ?", $productFacetOptionId, $row['product_facet_option_id']);
				if (!empty($updateSet['sql_error'])) {
					$this->iErrorMessage = ($GLOBALS['gUserRow']['superuser_flag'] ? $updateSet['sql_error'] : getSystemMessage("basic", $updateSet['sql_error']));
					return false;
				}
				$deleteSet = executeQuery("delete from product_facet_options where product_facet_option_id = ?", $row['product_facet_option_id']);
				if (!empty($deleteSet['sql_error'])) {
					$this->iErrorMessage = ($GLOBALS['gUserRow']['superuser_flag'] ? $deleteSet['sql_error'] : getSystemMessage("basic", $deleteSet['sql_error']));
					return false;
				}
			}
		}

		# make sure there isn't already a facet value for this facet. If so, just delete (using data table to record the deletion)

		$dataTable = new DataTable("product_facet_values");
		$resultSet = executeQuery("select * from product_facet_values where product_facet_id = ?", $oldProductFacetId);
		while ($row = getNextRow($resultSet)) {
			// Each product can only have each facet once. (unique constraint on product_id and product_facet_id)
			// If both the old and new facets exist on a product with different values, just delete the old one.
			$productFacetValueId = getFieldFromId("product_facet_value_id", "product_facet_values", "product_id", $row['product_id'],
				"product_facet_id = ?", $newProductFacetId);
			if (empty($productFacetValueId)) {
				$updateSet = executeQuery("update product_facet_values set product_facet_id = ? where product_facet_value_id = ?", $newProductFacetId, $row['product_facet_value_id']);
				if (!empty($updateSet['sql_error'])) {
					$this->iErrorMessage = ($GLOBALS['gUserRow']['superuser_flag'] ? $updateSet['sql_error'] : getSystemMessage("basic", $updateSet['sql_error']));
					return false;
				}
			} else {
				$dataTable->deleteRecord(array("primary_id" => $row['product_facet_value_id']));
			}
		}

		$updateSet = executeQuery("update product_distributor_conversions set primary_identifier = ? where table_name = 'product_facets' and primary_identifier = ?", $newProductFacetId, $oldProductFacetId);
		if (!empty($updateSet['sql_error'])) {
			$this->iErrorMessage = ($GLOBALS['gUserRow']['superuser_flag'] ? $updateSet['sql_error'] : getSystemMessage("basic", $updateSet['sql_error']));
			return false;
		}
		$deleteSet = executeQuery("delete from product_facets where product_facet_id = ?", $oldProductFacetId);
		if (!empty($deleteSet['sql_error'])) {
			$this->iErrorMessage = ($GLOBALS['gUserRow']['superuser_flag'] ? $deleteSet['sql_error'] : getSystemMessage("basic", $deleteSet['sql_error']));
			return false;
		}
		return true;
	}

	function mainContent() {
		?>
        <div id="_buttons_wrapper">
            <p>
                <button id="_merge_facets_button">Merge Facets</button>
                <button id="_merge_facet_options_button">Merge Facet Options</button>
            </p>
        </div>
        <div id="_merge_facets_wrapper" class="hidden">
            <p>
                <button class='back-button'><span class='fas fa-chevron-left'></span> Back</button>
            </p>
            <form id="merge_facets_form">
                <p class='highlighted-text color-red'>Merging Facets CANNOT be undone. Please be certain the two facets you choose are genuine duplicates. As a precaution, the duplicate facet must first be marked Internal Use Only.</p>
				<?php echo createFormControl("product_facet_options", "product_facet_id", array("form_label" => "Duplicate Facet", "help_label" => "This facet will be removed and merged into the real one", "not_null" => true, "column_name" => "old_product_facet_id")) ?>
				<?php echo createFormControl("product_facet_options", "product_facet_id", array("not_null" => true, "column_name" => "new_product_facet_id")) ?>
                <div class='form-line'>
                    <input tabindex="10" class="validate[required]" type='checkbox' id="confirm_merge_facets" name="confirm_merge_facets" value="1"><label for="confirm_merge_facets" class='checkbox-label'>I confirm these are duplicates and should be merged</label>
                </div>
                <p>
                    <button tabindex="10" id="_execute_merge_facets_button">Merge Facets</button>
                </p>
            </form>
        </div>
        <div id="_merge_facet_options_wrapper" class="hidden">
            <p>
                <button class='back-button'><span class='fas fa-chevron-left'></span> Back</button>
            </p>
            <form id="merge_facet_options_form">
                <p class='highlighted-text color-red'>Merging Facet Options CANNOT be undone. Please be certain the two facet options you choose are geniune duplicates.</p>
				<?php echo createFormControl("product_facet_options", "product_facet_id", array("form_label" => "Filter Facet", "help_label" => "Options are for this facet", "not_null" => true, "column_name" => "filter_product_facet_id")) ?>
				<?php echo createFormControl("product_facet_values", "product_facet_option_id", array("form_label" => "Duplication Option", "help_label" => "This option will be removed and merged into the real one", "not_null" => true, "column_name" => "old_product_facet_option_id", "choices" => array(), "data-additional_filter" => "xxx")) ?>
				<?php echo createFormControl("product_facet_values", "product_facet_option_id", array("not_null" => true, "column_name" => "new_product_facet_option_id", "choices" => array(), "data-additional_filter" => "xxx")) ?>
                <div class='form-line'>
                    <input tabindex="10" class="validate[required]" type='checkbox' id="confirm_merge_facet_options" name="confirm_merge_facet_options" value="1"><label for="confirm_merge_facet_options" class='checkbox-label'>I confirm these are duplicates and should be merged</label>
                </div>
                <p>
                    <button tabindex="10" id="_execute_merge_facet_options_button">Merge Facet Options</button>
                </p>
            </form>
        </div>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#_merge_facets_button", function () {
                $("#_buttons_wrapper").add("#_merge_facet_options_wrapper").addClass("hidden");
                $("#_merge_facets_wrapper").removeClass("hidden");
                $("#old_product_facet_id_autocomplete_text").focus();
            });
            $(document).on("click", "#_merge_facet_options_button", function () {
                $("#_buttons_wrapper").add("#_merge_facets_wrapper").addClass("hidden");
                $("#_merge_facet_options_wrapper").removeClass("hidden");
                $("#filter_product_facet_id_autocomplete_text").focus();
            });
            $(document).on("click", ".back-button", function () {
                $("#_merge_facet_options_wrapper").add("#_merge_facets_wrapper").addClass("hidden");
                $("#_buttons_wrapper").removeClass("hidden");
            });
            $(document).on("change", "#filter_product_facet_id", function () {
                const filterValue = (empty($(this).val()) ? "xxx" : $(this).val());
                $("#old_product_facet_option_id").val("");
                $("#new_product_facet_option_id").val("");
                $("#old_product_facet_option_id_autocomplete_text").data("additional_filter", filterValue).val("");
                $("#new_product_facet_option_id_autocomplete_text").data("additional_filter", filterValue).val("");
            });
            $(document).on("click", "#_execute_merge_facets_button", function () {
                if ($("#merge_facets_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=merge_facets", $("#merge_facets_form").serialize(), function(returnArray) {
                        if (!("error_message" in returnArray)) {
                            $("#old_product_facet_id").val("");
                            $("#new_product_facet_id").val("");
                            $("#old_product_facet_id_autocomplete_text").val("");
                            $("#new_product_facet_id_autocomplete_text").val("");
                            $("#confirm_merge_facets").prop("checked", false);
                            $(".back-button").trigger("click");
                        }
                    });
                }
                return false;
            });
            $(document).on("click", "#_execute_merge_facet_options_button", function () {
                if ($("#merge_facet_options_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=merge_facet_options", $("#merge_facet_options_form").serialize(), function(returnArray) {
                        if (!("error_message" in returnArray)) {
                            $("#filter_product_facet_id").val("");
                            $("#old_product_facet_option_id").val("");
                            $("#new_product_facet_option_id").val("");
                            $("#filter_product_facet_id_autocomplete_text").val("");
                            $("#old_product_facet_option_id_autocomplete_text").data("additional_filter", "xxx").val("");
                            $("#new_product_facet_option_id_autocomplete_text").data("additional_filter", "xxx").val("");
                            $("#confirm_merge_facet_options").prop("checked", false);
                            $(".back-button").trigger("click");
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
            .back-button {
                cursor: pointer;
            }
            #_merge_facets_wrapper, #_merge_facet_options_wrapper {
            p {
                margin-bottom: 20px;
                max-width: 650px;
            }
            }
        </style>
		<?php
	}
}

$pageObject = new MergeFacetsPage();
$pageObject->displayPage();
