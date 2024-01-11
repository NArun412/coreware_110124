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

$GLOBALS['gPageCode'] = "DUPLICATEPRODUCTPROCESSING";
require_once "shared/startup.inc";

class DuplicateProductProcessingPage extends Page {

	function setup() {
		$this->iDataSource->addColumnControl("product_1", "select_value", "select concat_ws(', ',description,upc_code,manufacturer_sku) from products join product_data using (product_id) where products.product_id = potential_product_duplicates.product_id");
		$this->iDataSource->addColumnControl("product_1", "form_label", "Primary Product");
		$this->iDataSource->addColumnControl("product_2", "select_value", "select concat_ws(', ',description,upc_code,manufacturer_sku) from products join product_data using (product_id) where products.product_id = potential_product_duplicates.duplicate_product_id");
		$this->iDataSource->addColumnControl("product_2", "form_label", "Possible Duplicate");
		$this->iDataSource->addColumnControl("product_id", "foreign_key", false);
		$this->iDataSource->addColumnControl("duplicate_product_id", "foreign_key", false);
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "save"));
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("product_id", "product_1", "duplicate_product_id", "product_2"));
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("product_id", "duplicate_product_id", "product_1", "product_2"));
			$this->iTemplateObject->getTableEditorObject()->setListDataLength(60);
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("merge" => array("label" => getLanguageText("Merge"),
				"disabled" => false)));
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "merge_products":
				$potentialProductDuplicateId = getFieldFromId("potential_product_duplicate_id", "potential_product_duplicates", "potential_product_duplicate_id", $_POST['primary_id'],
					"product_id = ? and duplicate_product_id = ?", $_POST['product_id_1'], $_POST['product_id_2']);
				if (empty($potentialProductDuplicateId)) {
					$returnArray['error_message'] = "Invalid Duplicate Product";
					ajaxResponse($returnArray);
					break;
				}
				$mergeResult = ProductCatalog::mergeProducts($_POST['product_id_1'], $_POST['product_id_2']);
				if ($mergeResult !== true) {
					$returnArray = $mergeResult;
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "product_data", "referenced_column_name" => "product_id", "foreign_key" => "product_id", "description" => "upc_code"));
	}

	function filterTextProcessing($filterText) {
		if (!empty($filterText)) {
			if (is_numeric($filterText) && strlen($filterText) >= 8) {
				$whereStatement = "product_code = " . makeParameter($filterText) . " or products.product_id = '" . $filterText . "'" .
					" or products.product_id in (select product_id from product_data where upc_code = '" . ProductCatalog::makeValidUPC($filterText) . "')" .
					" or products.product_id in (select product_id from product_data where isbn = '" . ProductCatalog::makeValidISBN($filterText) . "')" .
					" or products.product_id in (select product_id from product_data where isbn_13 = '" . ProductCatalog::makeValidISBN13($filterText) . "')";
			} else {
				$productId = getFieldFromId("product_id", "products", "description", $filterText);
				if (empty($productId)) {
					$searchWordInfo = ProductCatalog::getSearchWords($filterText);
					$searchWords = $searchWordInfo['search_words'];
					$whereStatement = "";
					foreach ($searchWords as $thisWord) {
						$whereStatement .= (empty($whereStatement) ? "" : " and ") .
							"products.product_id in (select product_id from product_search_word_values where product_search_word_id in " .
							"(select product_search_word_id from product_search_words where client_id = " . $GLOBALS['gClientId'] . " and search_term = " . makeParameter($thisWord) . "))";
					}
					$whereStatement = "(products.product_code = " . makeParameter($filterText) . " or products.description like " . makeParameter($filterText . "%") . (empty($whereStatement) ? ")" : " or (" . $whereStatement . "))");
					$whereStatement = "(" . (is_numeric($filterText) ? "products.product_id = " . makeParameter($filterText) . " or " : "") . "(" . $whereStatement . "))";
				} else {
					$whereStatement = "products.description like " . makeParameter($filterText . "%");
				}
			}
			$whereStatement = "product_id in (select product_id from products where " . $whereStatement
				. ") or duplicate_product_id in (select product_id from products where " . $whereStatement . ")";
			$this->iDataSource->addFilterWhere($whereStatement);
		}
	}

	function mergeForm() {
		?>
        <input type='hidden' id='product_id_1' name='product_id_1'>
        <input type='hidden' id='product_id_2' name='product_id_2'>
        <div id="merge_data_form">
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Product ID</label></p></div>
                <div class="merge-data-cell"><p id="display_product_id_1"></p></div>
                <div class="merge-data-cell"><p id="display_product_id_2"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Description</label></p></div>
                <div class="merge-data-cell"><p id="description_1"></p></div>
                <div class="merge-data-cell"><p id="description_2"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Manufacturer</label></p></div>
                <div class="merge-data-cell"><p id="product_manufacturer_1"></p></div>
                <div class="merge-data-cell"><p id="product_manufacturer_2"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Image</label></p></div>
                <div class="merge-data-cell"><p id='product_image_1'></p></div>
                <div class="merge-data-cell"><p id='product_image_2'></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>UPC</label></p></div>
                <div class="merge-data-cell"><p id="upc_code_1"></p></div>
                <div class="merge-data-cell"><p id="upc_code_2"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Model</label></p></div>
                <div class="merge-data-cell"><p id="model_1"></p></div>
                <div class="merge-data-cell"><p id="model_2"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Manufacturer SKU</label></p></div>
                <div class="merge-data-cell"><p id="manufacturer_sku_1"></p></div>
                <div class="merge-data-cell"><p id="manufacturer_sku_2"></p></div>
            </div>
            <div class="merge-data-row">
                <div class="merge-data-cell"><p><label>Cost</label></p></div>
                <div class="merge-data-cell"><p id="base_cost_1"></p></div>
                <div class="merge-data-cell"><p id="base_cost_2"></p></div>
            </div>
        </div>

		<?php
	}

	function afterGetRecord(&$returnArray) {
		$fields = array("product_id", "description", "upc_code", "model", "manufacturer_sku");
		$productRow = ProductCatalog::getCachedProductRow($returnArray['product_id']['data_value']);
		foreach ($fields as $fieldName) {
			$returnArray[$fieldName . "_1"] = array("data_value" => $productRow[$fieldName]);
		}
		$returnArray['product_manufacturer_1'] = array("data_value" => getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $productRow['product_manufacturer_id']));
		$imageUrl = ProductCatalog::getProductImage($productRow['product_id']);
		$returnArray['product_image_1'] = array("data_value" => "<img src='" . $imageUrl . "'>");
		$returnArray['base_cost_1'] = array("data_value" => number_format($productRow['base_cost'], 2));
		$returnArray['display_product_id_1'] = array("data_value" => $productRow['product_id']);

		$productRow = ProductCatalog::getCachedProductRow($returnArray['duplicate_product_id']['data_value']);
		foreach ($fields as $fieldName) {
			$returnArray[$fieldName . "_2"] = array("data_value" => $productRow[$fieldName]);
		}
		$returnArray['product_manufacturer_2'] = array("data_value" => getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $productRow['product_manufacturer_id']));
		$imageUrl = ProductCatalog::getProductImage($productRow['product_id']);
		$returnArray['product_image_2'] = array("data_value" => "<img src='" . $imageUrl . "'>");
		$returnArray['base_cost_2'] = array("data_value" => number_format($productRow['base_cost'], 2));
		$returnArray['display_product_id_2'] = array("data_value" => $productRow['product_id']);

		$differenceFields = array("description", "model", "manufacturer_sku", "product_manufacturer", "upc_code");
		foreach ($differenceFields as $fieldName) {
			$fieldValue = $returnArray[$fieldName . "_1"]['data_value'];
			$duplicateFieldValue = $returnArray[$fieldName . "_2"]['data_value'];
			$highlightedFieldValue = "";
			for ($x = 0; $x < strlen($duplicateFieldValue); $x++) {
				$thisChar = substr($duplicateFieldValue, $x, 1);
				if (strtolower($thisChar) == strtolower(substr($fieldValue, $x, 1))) {
					$highlightedFieldValue .= $thisChar;
				} else {
					$highlightedFieldValue .= "<span class='different-text'>" . $thisChar . "</span>";
				}
			}
			$returnArray[$fieldName . "_2"]['data_value'] = $highlightedFieldValue;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#_merge_button", function () {
                disableButtons($(this));
                mergeContacts();
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function dontAskAboutChanges() {
                return true;
            }

            function mergeContacts() {
                $("#_next_button").add("#_previous_button").data("ignore_click", "true");
                disableButtons($("#_merge_button"));
                let message = "<p>Merging product ID " + $("#product_id_1").val() + " and product ID " + $("#product_id_2").val() +
                    ". This is irreversible!</p>";
                message += "<p>Are you sure you want to continue?</p>";
                $("#_confirm_dialog").html(message);
                $('#_confirm_dialog').dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Confirm Merge',
                    close: function (event, ui) {
                        $("#_next_button").add("#_previous_button").removeData("ignore_click");
                        enableButtons($("#_merge_button"));
                    },
                    buttons: {
                        Merge: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=merge_products", $("#_edit_form").serialize(), function(returnArray) {
                                if (!("error_message" in returnArray)) {
                                    displayInfoMessage("Products Merged");
                                    $('body').data('just_saved', 'true');
                                    if ("next_primary_id" in returnArray) {
                                        $("#_next_primary_id").val(returnArray['next_primary_id']);
                                    }
                                    if (!empty($("#_next_primary_id").val())) {
                                        getRecord($("#_next_primary_id").val());
                                    } else if (!empty($("#_previous_primary_id").val())) {
                                        getRecord($("#_previous_primary_id").val());
                                    } else {
                                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=list";
                                    }
                                }
                            });
                            $("#_confirm_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_dialog").dialog('close');
                        }
                    }
                });
            }
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #_changes_button {
                display: none;
            }

            #merge_data_form {
                display: table;
                max-width: 90%;
            }

            #_main_content .merge-data-row {
                display: table-row;
            }

            #_main_content .merge-data-cell {
                display: table-cell;
                padding: 5px 20px 5px 10px;
                background-color: rgb(240, 240, 240);
            }

            #_main_content .merge-data-cell p {
                margin: 0;
                padding: 0;
                font-size: 1rem;
            }

            #_main_content .merge-data-cell img {
                max-width: 90%;
                max-height: 300px;
            }

            #_main_content .merge-data-cell label {
                display: block;
                text-align: right;
            }

            #_confirm_dialog p {
                margin-bottom: 20px;
                font-size: 14px;
                font-weight: bold;
            }

            .different-text {
                background-color: rgb(255, 160, 160);
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="_confirm_dialog" class="dialog-box">
        </div>
		<?php
	}
}

$pageObject = new DuplicateProductProcessingPage("potential_product_duplicates");
$pageObject->displayPage();
