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

$GLOBALS['gPageCode'] = "PRODUCTGROUPMATRIXMAINT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "add"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("description", "readonly", true);
		$this->iDataSource->setSaveOnlyPresent(true);
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".existing-product", function () {
                const $thisElement = $(this).closest("tr").find(".autocomplete-field");
                $(this).closest("tr").find(".product-selector-wrapper").remove();
                $thisElement.removeClass("hidden").focus();
            });
            $(document).on("click", ".new-product", function () {
                $(this).closest("tr").find(".new-product-details").removeClass("hidden");
                $(this).closest("tr").find(".product-code").focus();
                $(this).closest("tr").find(".product-selector-wrapper").remove();
            });
            $(document).on("click", ".add-variant", function () {
                var rowNumber = parseInt($("#row_number").val()) + 1;
                $("#row_number").val(rowNumber);
                var variantRow = $("#_variant_row").find("tbody").html();
                var variantRow = variantRow.replace(/%row_number%/g, rowNumber);
                $("#matrix").find(".add-row").before(variantRow);
                $("#product_id_" + rowNumber + "_autocomplete_text").focus();
                return false;
            });
            $(document).on("click", ".delete-variant", function () {
                var thisPrimaryId = $(this).closest("tr").data("product_group_variant_id");
                if (!empty(thisPrimaryId)) {
                    var deleteIds = $("#_delete_ids").val();
                    if (deleteIds != "") {
                        deleteIds += ",";
                    }
                    deleteIds += thisPrimaryId;
                    $("#_delete_ids").val(deleteIds);
                }
                $(this).closest("tr").remove();
                return false;
            });
        </script>
		<?php
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$productOptions = array();
		$resultSet = executeQuery("select * from product_group_options join product_options using (product_option_id) where product_group_id = ? order by sequence_number", $nameValues['primary_id']);
		while ($row = getNextRow($resultSet)) {
			$productOptions[] = $row;
		}
		foreach ($nameValues as $fieldName => $fieldData) {
			if (substr($fieldName, 0, strlen("product_group_variant_id_")) != "product_group_variant_id_") {
				continue;
			}
			$rowNumber = substr($fieldName, strlen("product_group_variant_id_"));
			$productId = $nameValues['product_id_' . $rowNumber];
			if (empty($productId)) {
				if (empty($nameValues['product_code_' . $rowNumber]) || empty($nameValues['description_' . $rowNumber])) {
					continue;
				}
				$dataTable = new DataTable("products");
				$dataTable->setSaveOnlyPresent(true);
				$baseLinkName = makeCode($nameValues['product_code_' . $rowNumber], array("use_dash" => true, "lowercase" => true));
				$sequenceNumber = 0;
				do {
					$linkName = $baseLinkName . (empty($sequenceNumber) ? "" : "-" . $sequenceNumber);
					$foundProductId = getFieldFromId("product_id", "products", "link_name", $linkName);
					$sequenceNumber++;
				} while (!empty($foundProductId));
				$imageId = "";
				if (array_key_exists("image_id_file_" . $rowNumber, $_FILES) && !empty($_FILES["image_id_file_" . $rowNumber]['name'])) {
					$imageId = createImage("image_id_file_" . $rowNumber, array("client_id" => $nameValues['primary_id']));
					if ($imageId === false) {
						return getSystemMessage("basic", $resultSet['sql_error']);
					}
				}
				$productId = $dataTable->saveRecord(array("name_values" => array("product_code" => $nameValues['product_code_' . $rowNumber], "description" => $nameValues['description_' . $rowNumber], "image_id" => $imageId,
					"list_price" => $nameValues['list_price_' . $rowNumber], "base_cost" => $nameValues['base_cost_' . $rowNumber], "reindex" => "1", "link_name" => $linkName, "date_created" => date("Y-m-d"), "time_changed" => date("Y-m-d H:i:s"))));
				if (!$productId) {
					return $dataTable->getErrorMessage();
				}
			}
			$dataTable = new DataTable("product_group_variants");
			$productGroupVariantId = $dataTable->saveRecord(array("name_values" => array("product_group_id" => $nameValues['primary_id'], "product_id" => $productId),
				"primary_id" => $fieldData));
			if (!$productGroupVariantId) {
				return $dataTable->getErrorMessage();
			}
			foreach ($productOptions as $thisOption) {
				$productOptionChoiceId = getFieldFromId("product_option_choice_id", "product_group_variant_choices", "product_group_variant_id", $productGroupVariantId, "product_option_id = ?", $thisOption['product_option_id']);
				if (empty($productOptionChoiceId) || $productOptionChoiceId != $nameValues["product_option_id_" . $thisOption['product_option_id'] . "_" . $rowNumber]) {
					$productGroupVariantChoiceId = getFieldFromId("product_group_variant_choice_id", "product_group_variant_choices", "product_group_variant_id", $productGroupVariantId, "product_option_id = ?", $thisOption['product_option_id']);
					$dataTable = new DataTable("product_group_variant_choices");
					$productGroupVariantChoiceId = $dataTable->saveRecord(array("name_values" => array("product_group_variant_id" => $productGroupVariantId, "product_option_id" => $thisOption['product_option_id'],
						"product_option_choice_id" => $nameValues["product_option_id_" . $thisOption['product_option_id'] . "_" . $rowNumber]),
						"primary_id" => $productGroupVariantChoiceId));
					if (!$productGroupVariantChoiceId) {
						return $dataTable->getErrorMessage();
					}
				}
			}
		}
		if (!empty($nameValues['_delete_ids'])) {
			$deleteIds = explode(",", $nameValues['_delete_ids']);
			$resultSet = executeQuery("delete from product_group_variant_choices where product_group_variant_id in (" . implode(",", array_fill(0, count($deleteIds), "?")) . ") and product_group_variant_id in (select product_group_variant_id from product_group_variants where product_group_id = ?)",
				array_merge($deleteIds, array($nameValues['primary_id'])));
			$resultSet = executeQuery("delete from product_group_variants where product_group_variant_id in (" . implode(",", array_fill(0, count($deleteIds), "?")) . ") and product_group_id = ?",
				array_merge($deleteIds, array($nameValues['primary_id'])));
		}
		return true;
	}

	function filterTextProcessing($filterText) {
		if (is_numeric($filterText)) {
			$whereStatement = "product_group_id in (select product_group_id from product_group_variants where product_id = " . makeParameter($filterText) .
				" or product_id in (select product_id from product_data where upc_code = '" . ProductCatalog::makeValidUPC($filterText) . "'" .
				") or product_id in (select product_id from product_data where isbn = '" . ProductCatalog::makeValidISBN($filterText) . "'" .
				") or product_id in (select product_id from product_data where isbn_13 = '" . ProductCatalog::makeValidISBN13($filterText) . "'))";
			$this->iDataSource->addFilterWhere($whereStatement);
		} else {
			$this->iDataSource->setFilterText($filterText);
		}
	}

	function afterGetRecord(&$returnArray) {
		$primaryId = $returnArray['primary_id']['data_value'];
		ob_start();
		$productOptions = array();
		$resultSet = executeQuery("select * from product_group_options join product_options using (product_option_id) where product_group_id = ? order by sequence_number", $primaryId);
		while ($row = getNextRow($resultSet)) {
			$productOptions[] = $row;
		}
		?>
        <table id="matrix" class="grid-table">
            <tr>
                <th>Product</th>
				<?php
				foreach ($productOptions as $thisOption) {
					?>
                    <th><?= htmlText($thisOption['description']) ?></th>
					<?php
				}
				?>
                <th></th>
            </tr>
			<?php
			$rowNumber = 0;
			$resultSet = executeQuery("select * from product_group_variants where product_group_id = ?", $primaryId);
			while ($row = getNextRow($resultSet)) {
				$rowNumber++;
				?>
                <tr data-product_group_variant_id="<?= $row['product_group_variant_id'] ?>">
                    <td><input type="hidden" name="product_group_variant_id_<?= $rowNumber ?>" value="<?= $row['product_group_variant_id'] ?>"><input class="" type="hidden" id="product_id_<?= $rowNumber ?>" name="product_id_<?= $rowNumber ?>" value="<?= $row['product_id'] ?>" data-crc_value="<?= getCrcValue($row['product_id']) ?>"><input autocomplete="chrome-off" autocomplete="off" tabindex="10" class="autocomplete-field validate[required]" type="text" size="60" name="product_id_<?= $rowNumber ?>_autocomplete_text" id="product_id_<?= $rowNumber ?>_autocomplete_text" data-autocomplete_tag="products" value="<?= htmlText(getFieldFromId("description", "products", "product_id", $row['product_id'])) ?>"></td>
					<?php
					foreach ($productOptions as $thisOption) {
						$productOptionChoiceId = getFieldFromId("product_option_choice_id", "product_group_variant_choices", "product_group_variant_id", $row['product_group_variant_id'], "product_option_id = ?", $thisOption['product_option_id']);
						?>
                        <td><select tabindex="10" class="validate[required]" id="product_option_id_<?= $thisOption['product_option_id'] ?>_<?= $rowNumber ?>" name="product_option_id_<?= $thisOption['product_option_id'] ?>_<?= $rowNumber ?>" data-crc_value="<?= getCrcValue($productOptionChoiceId) ?>">
                                <option value="">[Select]</option>
								<?php
								$optionSet = executeQuery("select * from product_option_choices where product_option_id = ? order by sort_order,description", $thisOption['product_option_id']);
								while ($optionRow = getNextRow($optionSet)) {
									?>
                                    <option value="<?= $optionRow['product_option_choice_id'] ?>"<?= ($optionRow['product_option_choice_id'] == $productOptionChoiceId ? " selected" : "") ?>><?= htmlText($optionRow['description']) ?></option>
									<?php
								}
								?>
                            </select></td>
						<?php
					}
					?>
                    <td><span class='delete-variant fas fa-trash'></span></td>
                </tr>
				<?php
			}
			?>
            <tr class="add-row">
                <th colspan="<?= count($productOptions) + 1 ?>"></th>
                <th class="add-variant"><input type="hidden" id="row_number" value="<?= $rowNumber ?>"><input type="hidden" name="_delete_ids" id="_delete_ids" data-crc_value="#00000000" value="">
                    <button class='add-variant' tabindex="10"><span class='fad fa-plus-octagon'></span></button>
                </th>
        </table>
		<?php
		$returnArray['product_matrix'] = array("data_value" => ob_get_clean());
	}

	function jqueryTemplates() {
		?>
        <table id="_variant_row">
            <tbody>
            </tbody>
        </table>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
                $("#_variant_row").find("tr").remove();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_variant_template&primary_id=" + $("#primary_id").val(), function (returnArray) {
                    if ("variant_template" in returnArray) {
                        $("#_variant_row").find("tbody").append(returnArray['variant_template']);
                    }
                });
            }
        </script>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_variant_template":
				$productOptions = array();
				$resultSet = executeQuery("select * from product_group_options join product_options using (product_option_id) where product_group_id = ? order by sequence_number", $_GET['primary_id']);
				while ($row = getNextRow($resultSet)) {
					$productOptions[] = $row;
				}
				ob_start();
				?>
                <tr data-product_group_variant_id="">
                    <td><input type="hidden" name="product_group_variant_id_%row_number%" value="">
                        <input class="" type="hidden" id="product_id_%row_number%" name="product_id_%row_number%" value="" data-crc_value="<?= getCrcValue("") ?>">
                        <div class="product-selector-wrapper">
                            <button class="existing-product" tabindex='10'>Use Existing Product</button>
                            <button class="new-product" tabindex='10'>Create New Product</button>
                        </div>
                        <input autocomplete="chrome-off" autocomplete="off" tabindex="10" class="hidden autocomplete-field validate[required]" type="text" size="60" name="product_id_%row_number%_autocomplete_text" id="product_id_%row_number%_autocomplete_text" data-autocomplete_tag="products" value="">
                        <div class="new-product-details hidden">
                            <div class='form-line'>
                                <label class="required-label">Product Code</label>
                                <input type="text" tabindex="10" class="product-code code-value uppercase validate[required]" value="" size="40" maxlength="100" name="product_code_%row_number%" id="product_code_%row_number%">
                            </div>
                            <div class='form-line'>
                                <label class="required-label">Title</label>
                                <input type="text" tabindex="10" class="validate[required]" value="" size="40" maxlength="255" name="description_%row_number%" id="description_%row_number%">
                            </div>
                            <div class='form-line inline-block'>
                                <label>List Price</label>
                                <input tabindex="10" data-decimal-places="2" class="align-right validate[min[0],custom[number]]" type="text" value="" size="12" name="list_price_%row_number%" id="list_price_%row_number%">
                            </div>
                            <div class='form-line inline-block'>
                                <label>Base Cost</label>
                                <input tabindex="10" data-decimal-places="2" class="align-right validate[min[0],custom[number]]" type="text" value="" size="12" name="base_cost_%row_number%" id="base_cost_%row_number%">
                            </div>
                            <div class='form-line'>
                                <label>Product Image</label>
                                <input tabindex="10" type="file" name="image_id_file_%row_number%" id="image_id_file_%row_number%">
                            </div>
                        </div>
                    </td>
					<?php
					foreach ($productOptions as $thisOption) {
						?>
                        <td><select tabindex="10" class="validate[required]" id="product_option_id_<?= $thisOption['product_option_id'] ?>_%row_number%" name="product_option_id_<?= $thisOption['product_option_id'] ?>_%row_number%">
                                <option value="">[Select]</option>
								<?php
								$optionSet = executeQuery("select * from product_option_choices where product_option_id = ? order by sort_order,description", $thisOption['product_option_id']);
								while ($optionRow = getNextRow($optionSet)) {
									?>
                                    <option value="<?= $optionRow['product_option_choice_id'] ?>"><?= htmlText($optionRow['description']) ?></option>
									<?php
								}
								?>
                            </select></td>
						<?php
					}
					?>
                    <td><span class='delete-variant fas fa-trash'></span></td>
                </tr>
				<?php
				$returnArray['variant_template'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
		}
	}

	function internalCSS() {
		?>
        <style>
            table#matrix td {
                position: relative;
            }
            th.add-variant button {
                border: none;
                background-color: transparent;
            }
        </style>
		<?php
	}
}

$pageObject = new ThisPage("product_groups");
$pageObject->displayPage();
