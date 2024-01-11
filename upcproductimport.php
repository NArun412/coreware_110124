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

$GLOBALS['gPageCode'] = "UPCPRODUCTIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDontWrapperManagementContent'] = true;

class UPCProductImportPage extends Page {

	var $iProductFields = array("description", "detailed_description", "product_type_id", "list_price");
	var $iProductDataFields = array("model", "upc_code", "manufacturer_sku", "manufacturer_advertised_price", "width", "length", "height", "weight");

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_facet_options":
				$productFacetId = $_GET['product_facet_id'];
				$returnArray['product_facet_options'] = array();
				$resultSet = executeQuery("select * from product_facet_options where product_facet_id = ? order by facet_value", $productFacetId);
				while ($row = getNextRow($resultSet)) {
					$returnArray['product_facet_options'][] = array("key_value" => $row['product_facet_option_id'], "description" => $row['facet_value']);
				}
				ajaxResponse($returnArray);
				break;
			case "save_product":
				$createProduct = true;
				$returnArray['post_fields'] = $_POST;
				if (empty($_POST['product_id'])) {
					$productId = getFieldFromId("product_id", "product_data", "upc_code", $_POST['upc_code']);
					if (!empty($productId)) {
						$returnArray['error_message'] = "Product already exists";
						ajaxResponse($returnArray);
						break;
					}
				} else {
					$productId = getFieldFromId("product_id", "products", "product_id", $_POST['product_id']);
					if (empty($productId)) {
						$returnArray['error_message'] = "Product not found";
						ajaxResponse($returnArray);
						break;
					}
					$createProduct = false;
				}
				$productInformation = json_decode($_POST['cssc_product_data'], true);
				$fieldNames = array("description", "detailed_description", "product_type_id", "list_price", "product_manufacturer_id", "model", "manufacturer_sku",
					"manufacturer_advertised_price", "width", "length", "height", "weight", "upc_code");
				foreach ($fieldNames as $fieldName) {
					$productInformation[$fieldName] = $_POST[$fieldName];
				}

				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$copyFields = array("description", "detailed_description", "base_cost", "product_type_id", "list_price", "product_manufacturer_id", "low_inventory_quantity", "low_inventory_surcharge_amount", "virtual_product", "cart_minimum", "cart_maximum", "order_maximum", "serializable");
				$productData = array("client_id" => $GLOBALS['client_id'], "date_created" => date("Y-m-d"), "reindex" => 1);
				foreach ($copyFields as $fieldName) {
					$productData[$fieldName] = $productInformation[$fieldName];
				}
				$useProductNumber = 0;
				do {
					$useProductCode = substr($productInformation['product_code'], 0, 95) . (empty($useProductNumber) ? "" : "_" . $useProductNumber);
					$dupProductId = getFieldFromId("product_id", "products", "product_code", $useProductCode);
					$useProductNumber++;
				} while (!empty($dupProductId));
				$productData['product_code'] = $useProductCode;

				$useProductNumber = 0;
				do {
					$useLinkName = makeCode($productInformation['description'], array("use_dash" => true, "lowercase" => true)) . (empty($useProductNumber) ? "" : "-" . $useProductNumber);
					$dupProductId = getFieldFromId("product_id", "products", "link_name", $useLinkName);
					$useProductNumber++;
				} while (!empty($dupProductId));
				$productData['link_name'] = $useLinkName;
				$productData['remote_identifier'] = $productInformation['product_id'];

				$productDataTable = new DataTable("products");
				if ($createProduct) {
					if (!$productId = $productDataTable->saveRecord(array("name_values" => $productData, "primary_id" => ""))) {
						$returnArray['error_message'] = $productDataTable->getErrorMessage();
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						return $returnArray;
					}
				} else {
					$productDataTable->setSaveOnlyPresent(true);
					if (!$productDataTable->saveRecord(array("name_values" => $_POST, "primary_id" => $productId))) {
						$returnArray['error_message'] = $productDataTable->getErrorMessage();
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						return $returnArray;
					}
				}

				$productData = array("product_id" => $productId, "client_id" => $GLOBALS['gClientId']);
				$copyFields = array("model", "upc_code", "isbn", "isbn_13", "manufacturer_sku", "minimum_price", "manufacturer_advertised_price", "width", "length", "height", "weight");
				foreach ($copyFields as $fieldName) {
					$productData[$fieldName] = $productInformation[$fieldName];
				}
				$productDataTable = new DataTable("product_data");
				if ($createProduct) {
					if (!$productDataId = $productDataTable->saveRecord(array("name_values" => $productData, "primary_id" => ""))) {
						$returnArray['error_message'] = $productDataTable->getErrorMessage();
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						return $returnArray;
					}
				} else {
					$productDataTable->setSaveOnlyPresent(true);
					$productData = array("product_id" => $productId, "client_id" => $GLOBALS['gClientId']);
					$copyFields = array("model", "upc_code", "isbn", "isbn_13", "manufacturer_sku", "minimum_price", "manufacturer_advertised_price", "width", "length", "height", "weight");
					foreach ($copyFields as $fieldName) {
						$productData[$fieldName] = $productInformation[$fieldName];
					}
					$productDataId = getFieldFromId("product_data_id", "product_data", "product_id", $productId);
					if (!$productDataId = $productDataTable->saveRecord(array("name_values" => $productData, "primary_id" => $productDataId))) {
						$returnArray['error_message'] = $productDataTable->getErrorMessage();
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						return $returnArray;
					}
				}

				# add categories

				$productCategoryIds = explode(",", $_POST['product_categories']);
				foreach ($productCategoryIds as $productCategoryId) {
					$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $productCategoryId);
					if (!empty($productCategoryId)) {
						$productCategoryLinksDataTable = new DataTable("product_category_links");
						$productCategoryLinksDataTable->saveRecord(array("name_values"=>array("product_category_id"=>$productCategoryId,"product_id"=>$productId)));
					}
				}
				if (!empty($_POST['_delete_product_categories'])) {
					$deleteProductCategoryIds = "";
					foreach (explode(",", $_POST['_delete_product_categories']) as $productCategoryId) {
						if (!is_numeric($productCategoryId)) {
							continue;
						}
						$deleteProductCategoryIds .= (empty($deleteProductCategoryIds) ? "" : ",") . $productCategoryId;
					}
					if (!empty($deleteProductCategoryIds)) {
						executeQuery("delete from product_category_links where product_id = ? and product_category_id in (" . $deleteProductCategoryIds . ")", $productId);
					}
				}

				# add product tags

				$productTagIds = explode(",", $_POST['product_tags']);
				foreach ($productTagIds as $productTagId) {
					$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_id", $productTagId);
					if (!empty($productTagId)) {
						executeQuery("insert ignore into product_tag_links (product_id,product_tag_id) values (?,?)", $productId, $productTagId);
					}
				}
				if (!empty($_POST['_delete_product_tags'])) {
					$deleteProductTagIds = "";
					foreach (explode(",", $_POST['_delete_product_tags']) as $productTagId) {
						if (!is_numeric($productTagId)) {
							continue;
						}
						$deleteProductTagIds .= (empty($deleteProductTagIds) ? "" : ",") . $productTagId;
					}
					if (!empty($deleteProductTagIds)) {
						executeQuery("delete from product_tag_links where product_id = ? and product_tag_id in (" . $deleteProductTagIds . ")", $productId);
					}
				}

				# add facets
				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("product_facet_option_id-")) != "product_facet_option_id-") {
						continue;
					}
					$productFacetCode = substr($fieldName, strlen("product_facet_option_id-"));
					$productFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_code", $productFacetCode, "inactive = 0");
					$productFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_option_id", $fieldData);
					$facetValue = $_POST['product_facet_value-' . $productFacetCode];
					if (empty($productFacetId) || (empty($productFacetOptionId) && empty($productFacetValue))) {
						continue;
					}
					$productFacetRow = getRowFromId("product_facets", "product_facet_id", $productFacetId);
					if (empty($productFacetOptionId) && ($GLOBALS['gClientRow']['client_code'] != "COREWARE_SHOOTING_SPORTS" || empty($productFacetRow['catalog_lock']))) {
						$insertSet = executeQuery("insert into product_facet_options (product_facet_id,facet_value) values (?,?)", $productFacetId, $facetValue);
						$productFacetOptionId = $insertSet['insert_id'];
						freeResult($insertSet);
					}
                    if (empty($productFacetOptionId)) {
	                    continue;
                    }
					$productFacetValues = getRowFromId("product_facet_values", "product_facet_id", $productFacetId, "product_id = ?", $productId);
					if (empty($productFacetValues)) {
						$insertSet = executeQuery("insert into product_facet_values (product_id,product_facet_id,product_facet_option_id) values (?,?,?)", $productId, $productFacetId, $productFacetOptionId);
					} else if ($productFacetValues['product_facet_option_id'] != $productFacetOptionId) {
						executeQuery("update product_facet_values set product_facet_option_id = ? where product_facet_value_id = ?", $productFacetOptionId, $productFacetValues['product_facet_value_id']);
					}

					$originalProductFacetValue = $_POST['original_product_facet_value-' . $productFacetCode];
					$newProductFacetValue = getFieldFromId("facet_value", "product_facet_options", "product_facet_option_id", $productFacetOptionId);
					if (!empty($originalProductFacetValue) && !empty($newProductFacetValue) && $originalProductFacetValue != $newProductFacetValue) {
						$productDistributorConversionRow = getRowFromId("product_distributor_conversions", "table_name", "product_facet_options",
							"original_value = ? and product_distributor_id is null and original_value_qualifier = ?", $originalProductFacetValue, $productFacetCode);
						if (empty($productDistributorConversionRow)) {
							executeQuery("insert into product_distributor_conversions (client_id,table_name,original_value,original_value_qualifier,primary_identifier) values (?,'product_facet_options',?,?,?)",
								$GLOBALS['gClientId'], $originalProductFacetValue, $productFacetCode, $productFacetOptionId);
						} else if ($productFacetOptionId != $productDistributorConversionRow['primary_identifier']) {
							executeQuery("update product_distributor_conversions set primary_identifier = ? where product_distributor_conversion_id = ?", $productFacetOptionId, $productDistributorConversionRow['product_distributor_conversion_id']);
						}
					}
					freeResult($insertSet);
				}

				# add new facets

				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("missing_facet_option-")) != "missing_facet_option-") {
						continue;
					}
					$productFacetCode = substr($fieldName, strlen("missing_facet_option-"));
					switch ($fieldData) {
						case "add":
							$facetValue = $_POST['add_facet_value_' . $productFacetCode];
							if (empty($facetValue)) {
								continue;
							}
							$description = ucwords(strtolower(str_replace("_", " ", $productFacetCode)));
							$insertSet = executeQuery("insert into product_facets (client_id,product_facet_code,description) values (?,?,?)", $GLOBALS['gClientId'], $productFacetCode, $description);
							$productFacetId = $insertSet['insert_id'];
							freeResult($insertSet);
							$insertSet = executeQuery("insert into product_facet_options (product_facet_id,facet_value) values (?,?)", $productFacetId, $facetValue);
							$productFacetOptionId = $insertSet['insert_id'];
							freeResult($insertSet);
							$insertSet = executeQuery("insert into product_facet_values (product_id,product_facet_id,product_facet_option_id) values (?,?,?)", $productId, $productFacetId, $productFacetOptionId);
							break;
						case "exist":
							$productFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_id", $_POST['existing_product_facet_id-' . $productFacetCode]);
							$productFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_option_id", $_POST['existing_facet_option_id-' . $productFacetCode]);
							$facetValue = $_POST['existing_product_facet_value-' . $productFacetCode];
							if (empty($productFacetId) || (empty($productFacetOptionId) && empty($productFacetValue))) {
								continue;
							}
							$productFacetRow = getRowFromId("product_facets", "product_facet_id", $productFacetId);
							if (empty($productFacetOptionId)) {
                                if ($GLOBALS['gClientRow']['client_code'] != "COREWARE_SHOOTING_SPORTS" || empty($productFacetRow['catalog_lock'])) {
	                                $insertSet = executeQuery("insert into product_facet_options (product_facet_id,facet_value) values (?,?)", $productFacetId, $facetValue);
	                                $productFacetOptionId = $insertSet['insert_id'];
	                                freeResult($insertSet);
                                }
							} else {
								$facetValue = getFieldFromId("facet_value", "product_facet_options", "product_facet_option_id", $productFacetOptionId);
							}
                            if (empty($productFacetOptionId)) {
                                continue;
                            }
							$insertSet = executeQuery("insert into product_facet_values (product_id,product_facet_id,product_facet_option_id) values (?,?,?)", $productId, $productFacetId, $productFacetOptionId);

							$productDistributorConversionRow = getRowFromId("product_distributor_conversion", "table_name", "product_facets",
								"original_value = ? and product_distributor_id is null", $productFacetCode);
							if (empty($productDistributorConversionRow)) {
								executeQuery("insert into product_distributor_conversions (client_id,table_name,original_value,primary_identifier) values (?,'product_facets',?,?)",
									$GLOBALS['gClientId'], $productFacetCode, $productFacetId);
							}
							if ($_POST['add_facet_value_' . $productFacetCode] != $facetValue) {
								$productDistributorConversionRow = getRowFromId("product_distributor_conversion", "table_name", "product_facet_options",
									"original_value = ? and product_distributor_id is null", $productFacetCode);
								if (empty($productDistributorConversionRow)) {
									executeQuery("insert into product_distributor_conversions (client_id,table_name,original_value,primary_identifier) values (?,'product_facet_options',?,?)",
										$GLOBALS['gClientId'], $_POST['add_facet_value_' . $productFacetCode], $productFacetOptionId);
								}
							}
							break;
					}
				}

				# add images
				if ($createProduct) {
					if (!empty($_POST['use_coreware_images'])) {
						$imageIds = array();
						if (!empty($productInformation['image_id'])) {
							$imageIds[] = $productInformation['image_id'];
						}
						if (!empty($productInformation['alternate_images'])) {
							$alternateImages = explode(",", $productInformation['alternate_images']);
							foreach ($alternateImages as $imageId) {
								if (!empty($imageId) && !in_array($imageId, $imageIds)) {
									$imageIds[] = $imageId;
								}
							}
						}

						if (!empty($imageIds)) {
							$primaryImage = true;
							foreach ($imageIds as $imageId) {
								$resultSet = executeQuery("insert into product_remote_images (product_id,image_identifier,primary_image) values (?,?,?)", $productId, $imageId, ($primaryImage ? 1 : 0));
								freeResult($resultSet);
								$primaryImage = false;
							}
						}
					} else {
						if (array_key_exists("image_id_file", $_FILES) && empty($_POST['remove_image_id'])) {
							$imageId = createImage("image_id_file");
							executeQuery("update products set image_id = ? where product_id = ?", $imageId, $productId);
						}
					}
				}

				# add restricted states
				if (!empty($productInformation['restricted_states'])) {
					$restrictedStates = explode(",", $productInformation['restricted_states']);
					foreach ($restrictedStates as $thisState) {
						if (!empty($thisState)) {
							executeQuery("insert into product_restrictions (product_id,state,country_id) values (?,?,1000)", $productId, $thisState);
						}
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$returnArray['info_message'] = "Product Created";
				ajaxResponse($returnArray);
				break;
			case "import_upc_code":
				$productId = getFieldFromId("product_id", "product_data", "upc_code", $_GET['upc_code']);
				if (!empty($productId)) {
					$existingProductData = ProductCatalog::getCachedProductRow($productId);
				} else {
					$existingProductData = array();
				}
				$productData = ProductCatalog::importProductFromUPC($_GET['upc_code'], array("distributor_import" => true, "return_data_only" => true));
				$returnArray['product_data'] = array();
				$returnArray['product_data']['product_id'] = $productId;
				foreach ($productData as $fieldName => $fieldData) {
					if (in_array($fieldName, $this->iProductFields) || in_array($fieldName, $this->iProductDataFields)) {
						$returnArray['product_data'][$fieldName . "_cssc"] = $fieldData;
						$returnArray['product_data'][$fieldName] = (empty($productId) ? $fieldData : $existingProductData[$fieldName]);
					}
				}
				$returnArray['product_data']['product_manufacturer_description'] = $productData['product_manufacturer_description'];
				$returnArray['product_data']['product_manufacturer_id'] = (empty($productId) ? getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_code", $productData['product_manufacturer_code']) : $existingProductData['product_manufacturer_id']);
				$returnArray['product_data']['image_id_cssc'] = $returnArray['product_data']['image_id_cssc_link'] = "http://shootingsports.coreware.com/getimage.php?id=" . $productData['image_id'];
				$returnArray['product_data']['cssc_product_data'] = $productData;

				$returnArray['product_data']['product_categories_cssc'] = "";
				$returnArray['product_data']['product_categories'] = "";
				if (!empty($productData['product_category_codes'])) {
					$productCategoryCodes = explode(",", $productData['product_category_codes']);
					foreach ($productCategoryCodes as $productCategoryCode) {
						$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_code", $productCategoryCode, "inactive = 0");
						if (empty($productId) && !empty($productCategoryId)) {
							$returnArray['product_data']['product_categories'] .= (empty($returnArray['product_data']['product_categories']) ? "" : ",") . $productCategoryId;
						}
						$returnArray['product_data']['product_categories_cssc'] .= (empty($returnArray['product_data']['product_categories_cssc']) ? "" : "<br>") . (getFieldFromId("description", "product_categories", "product_category_code", $productCategoryCode) ?: $productCategoryCode);
					}
				}
				if (!empty($productId)) {
					$resultSet = executeQuery("select * from product_category_links where product_id = ?", $productId);
					while ($row = getNextRow($resultSet)) {
						$returnArray['product_data']['product_categories'] .= (empty($returnArray['product_data']['product_categories']) ? "" : ",") . $row['product_category_id'];
					}
				}
				$returnArray['product_data']['product_categories_cssc'] = $returnArray['product_data']['product_categories_cssc'] ?: "NONE";

				$returnArray['product_data']['product_tags_cssc'] = "";
				$returnArray['product_data']['product_tags'] = "";
				if (!empty($productData['product_tag_codes'])) {
					$productTagCodes = explode(",", $productData['product_tag_codes']);
					foreach ($productTagCodes as $productTagCode) {
						$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", $productTagCode, "inactive = 0");
						if (empty($productId) && !empty($productTagId)) {
							$returnArray['product_data']['product_tags'] .= (empty($returnArray['product_data']['product_tags']) ? "" : ",") . $productTagId;
						}
						$returnArray['product_data']['product_tags_cssc'] .= (empty($returnArray['product_data']['product_tags_cssc']) ? "" : "<br>") . (getFieldFromId("description", "product_tags", "product_tag_code", $productTagCode) ?: $productTagCode);
					}
				}
				if (!empty($productId)) {
					$resultSet = executeQuery("select * from product_tag_links where product_id = ?", $productId);
					while ($row = getNextRow($resultSet)) {
						$returnArray['product_data']['product_tags'] .= (empty($returnArray['product_data']['product_tags']) ? "" : ",") . $row['product_tag_id'];
					}
				}
				$returnArray['product_data']['product_tags_cssc'] = $returnArray['product_data']['product_tags_cssc'] ?: "NONE";

				$orphanedProductFacetCodes = array();
				$usedFacetIds = array();
				if (empty($productData['product_facets'])) {
					$returnArray['product_data']['product_facets'] = "";
				} else {
					ob_start();
					$productFacets = explode("||||", $productData['product_facets']);
					foreach ($productFacets as $thisFacetData) {
						$parts = explode("||", $thisFacetData);
						$productFacetCode = $parts[0];
						$productFacetValue = $parts[1];
						$productFacetRow = getRowFromId("product_facets", "product_facet_code", $productFacetCode);
						if (empty($productFacetRow)) {
							$convertedProductFacetId = getFieldFromId("primary_identifier", "product_distributor_conversions", "table_name", "product_facets",
								"product_distributor_id is null and original_value = ?", $productFacetCode);
							if (!empty($convertedProductFacetId)) {
								$productFacetRow = getRowFromId("product_facets", "product_facet_id", $convertedProductFacetId);
							}
						}
						if (empty($productFacetRow)) {
							$orphanedProductFacetCodes[$productFacetCode] = $productFacetValue;
							continue;
						}
						$usedFacetIds[] = $productFacetRow['product_facet_id'];
						if ($productFacetRow['inactive']) {
							continue;
						}
						$initialProductFacetOptionId = "";
						$productFacetValues = array();
						$resultSet = executeQuery("select * from product_facet_options where product_facet_id = ? order by facet_value", $productFacetRow['product_facet_id']);
						while ($row = getNextRow($resultSet)) {
							$productFacetValues[] = array("key_value" => $row['product_facet_option_id'], "description" => $row['facet_value']);
							if ($row['facet_value'] == $productFacetValue) {
								$initialProductFacetOptionId = $row['product_facet_option_id'];
							}
						}
						if (empty($initialProductFacetOptionId)) {
							$initialProductFacetOptionId = getFieldFromId("primary_identifier", "product_distributor_conversions", "table_name", "product_facet_options",
								"original_value = ? and product_distributor_id is null", $productFacetValue);
							$initialProductFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_option_id", $initialProductFacetOptionId);
						}
						$existingFacetOptionId = "";
						if (!empty($productId)) {
							$existingFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_values", "product_id", $productId, "product_facet_id = ?", $productFacetRow['product_facet_id']);
						}

						?>
                        <div class="merge-data-row product-facet-row">
                            <div class="merge-data-label"><label><?= htmlText($productFacetRow['description']) ?></label></div>
                            <div class="merge-data-cell"><p id="product_facet_code-<?= $productFacetCode ?>-cssc"><?= htmlText($productFacetValue) ?></p></div>
                            <input type='hidden' id='original_product_facet_value-<?= $productFacetCode ?>' name='original_product_facet_value-<?= $productFacetCode ?>' value='<?= htmlText($productFacetValue) ?>'>
                            <div class="merge-data-cell">
								<?= createFormControl("product_facet_values", "product_facet_option_id", array("not_null" => false, "column_name" => "product_facet_option_id-" . $productFacetCode, "control_only" => true, "classes" => "merge-field,product-facet-field", "data_type" => "select", "choices" => $productFacetValues, "empty_text" => "[Ignore or Create New Value]", "initial_value" => (empty($existingFacetOptionId) ? $initialProductFacetOptionId : $existingFacetOptionId))) ?>
                                <div class='form-line<?= (empty($initialProductFacetOptionId) ? "" : " hidden") ?> facet-value-wrapper'>
                                    <label>Create New Facet Value</label>
                                    <span class='help-label'>Leave blank to ignore this facet</span>
                                    <input tabindex='10' type='text' size="40" class='facet-value' id='product_facet_value-<?= $productFacetCode ?>' name='product_facet_value-<?= $productFacetCode ?>' value="<?= (empty($initialProductFacetOptionId) ? htmlText($productFacetValue) : "") ?>">
                                </div>
                            </div>
                        </div>
						<?php
					}
					foreach ($orphanedProductFacetCodes as $productFacetCode => $productFacetValue) {
						?>
                        <div class="merge-data-row product-facet-row">
                            <input type='hidden' class='add-facet-value' id='add_facet_value-<?= $productFacetCode ?>' name='add_facet_value-<?= $productFacetCode ?>' value='<?= htmlText($productFacetValue) ?>'>
                            <div class="merge-data-label"><label><?= htmlText($productFacetCode) ?></label></div>
                            <div class="merge-data-cell"><p><?= htmlText($productFacetValue) ?></p></div>
                            <div class="merge-data-cell">
                                <label>Facet does not exist. Action to take:</label>
                                <p>
                                    <select tabindex='10' class='missing-facet-option' id="missing_facet_option-<?= $productFacetCode ?>" name="missing_facet_option-<?= $productFacetCode ?>">
                                        <option value="">[Ignore]</option>
                                        <option value="add">Add Facet and Value</option>
                                        <option value="exist">Use Existing Facet</option>
                                    </select><br>
                                    <select tabindex='10' class='hidden existing-product-facet-id validate[required]' id="existing_product_facet_id-<?= $productFacetCode ?>" name="existing_product_facet_id-<?= $productFacetCode ?>">
                                        <option value="">[Choose Existing Facet]</option>
										<?php
										$resultSet = executeQuery("select * from product_facets where client_id = ?" . (empty($usedFacetIds) ? "" : " and product_facet_id not in (" . implode(",", $usedFacetIds) . ")") . " order by sort_order,description", $GLOBALS['gClientId']);
										while ($row = getNextRow($resultSet)) {
											?>
                                            <option value='<?= $row['product_facet_id'] ?>'><?= htmlText($row['description']) ?></option>
											<?php
										}
										?>
                                    </select><br>
                                    <select tabindex='10' class='hidden existing-facet-option' id="existing_facet_option_id-<?= $productFacetCode ?>" name="existing_facet_option_id-<?= $productFacetCode ?>">
                                        <option value="">[New Facet Value]</option>
                                    </select><br>
                                    <input tabindex='10' type='text' size="40" class='hidden existing-facet-value validate[required]' data-conditional-required="empty($(this).closest('div.product-facet-row').find('.existing-facet-option').val())" id='existing_product_facet_value-<?= $productFacetCode ?>' name='existing_product_facet_value-<?= $productFacetCode ?>' value="<?= htmlText($productFacetValue) ?>">
                                </p>
                            </div>
                        </div>
						<?php
					}
					$returnArray['product_data']['product_facets'] = ob_get_clean();
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		$productTableId = getFieldFromId("table_id", "tables", "table_name", "products");
		$productDataTableId = getFieldFromId("table_id", "tables", "table_name", "product_data");
		?>
		<?= createFormControl("product_data", "upc_code", array("column_name" => "import_upc_code", "not_null" => false)) ?>
        <p class='green-text' id='exists_message'></p>
        <form id="_edit_form" enctype='multipart/form-data'>
            <input type='hidden' id='cssc_product_data' name='cssc_product_data'>
            <input type='hidden' id='product_id' name='product_id'>
            <div id="merge_data_form">
                <div class="merge-data-row">
                    <div class="merge-data-label"></div>
                    <div class="merge-data-cell"><h2>CSSC Data</h2></div>
                    <div class="merge-data-cell"><h2>Final Data</h2></div>
                </div>
				<?php
				foreach ($this->iProductFields as $productField) {
					?>
                    <div class="merge-data-row">
                        <div class="merge-data-label"><label><?= getFieldFromId("description", "table_columns", "table_id", $productTableId, "column_definition_id = (select column_definition_id from column_definitions where column_name = ?)", $productField) ?></label></div>
                        <div class="merge-data-cell"><?= createFormControl("products", $productField, array("control_only" => true, "data-field_name" => $productField, "column_name" => $productField . "_cssc", "readonly" => true, "tabindex" => "0")) ?></div>
                        <div class="merge-data-cell"><?= createFormControl("products", $productField, array("control_only" => true, "classes" => "merge-field")) ?></div>
                    </div>
				<?php } ?>
                <div class="merge-data-row">
                    <div class="merge-data-label"><label>Manufacturer</label></div>
                    <div class="merge-data-cell"><p id="product_manufacturer_description" class="merge-field"></p></div>
                    <div class="merge-data-cell"><?= createFormControl("products", "product_manufacturer_id", array("control_only" => true, "classes" => "merge-field", "not_null" => true)) ?></div>
                </div>
                <div class="merge-data-row" id="_image_id_row">
                    <div class="merge-data-label"><label>Image</label></div>
                    <div class="merge-data-cell"><a href='#' id='image_id_cssc_link' class='pretty-photo merge-field'><img src='/images/empty.jpg' class='product-image merge-field' id='image_id_cssc'></a></div>
                    <div class="merge-data-cell"><?= createFormControl("products", "image_id", array("control_only" => true, "classes" => "merge-field", "data_type" => "image_input", "not_null" => false)) ?><br>
                        <input type='checkbox' class="merge-field" id="use_coreware_images" name="use_coreware_images" value="1" checked><label class='checkbox-label' for='use_coreware_images'>Use Coreware Images</label>
                    </div>
                </div>
				<?php foreach ($this->iProductDataFields as $productField) { ?>
                    <div class="merge-data-row">
                        <div class="merge-data-label"><label><?= getFieldFromId("description", "table_columns", "table_id", $productDataTableId, "column_definition_id = (select column_definition_id from column_definitions where column_name = ?)", $productField) ?></label></div>
                        <div class="merge-data-cell"><?= createFormControl("product_data", $productField, array("control_only" => true, "data-field_name" => $productField, "column_name" => $productField . "_cssc", "readonly" => true, "tabindex" => "0")) ?></div>
                        <div class="merge-data-cell"><?= createFormControl("product_data", $productField, array("control_only" => true, "classes" => "merge-field")) ?></div>
                    </div>
				<?php } ?>
                <div class="merge-data-row">
                    <div class="merge-data-label"><label>Categories</label></div>
                    <div class="merge-data-cell">
                        <p id='product_categories_cssc' class='merge-field'></p>
                    </div>
					<?php
					$categoryControl = new DataColumn("product_categories");
					$categoryControl->setControlValue("data_type", "custom");
					$categoryControl->setControlValue("include_inactive", false);
					$categoryControl->setControlValue("control_class", "MultiSelect");
					$categoryControl->setControlValue("control_table", "product_categories");
					$categoryControl->setControlValue("links_table", "product_category_links");
					$categoryControl->setControlValue("primary_table", "products");
					$customControl = new MultipleSelect($categoryControl, $this);
					?>
                    <div class="merge-data-cell">
                        <div class="form-line" id="_product_category_row">
							<?= $customControl->getControl() ?>
                            <div class='clear-div'></div>
                        </div>
                    </div>
                </div>
                <div class="merge-data-row">
                    <div class="merge-data-label"><label>Tags</label></div>
                    <div class="merge-data-cell">
                        <p id='product_tags_cssc' class='merge-field'></p>
                    </div>
					<?php
					$tagControl = new DataColumn("product_tags");
					$tagControl->setControlValue("data_type", "custom");
					$tagControl->setControlValue("include_inactive", false);
					$tagControl->setControlValue("control_class", "MultiSelect");
					$tagControl->setControlValue("control_table", "product_tags");
					$tagControl->setControlValue("links_table", "product_tag_links");
					$tagControl->setControlValue("primary_table", "products");
					$customControl = new MultipleSelect($tagControl, $this);
					?>
                    <div class="merge-data-cell">
                        <div class="form-line" id="_product_tag_row">
							<?= $customControl->getControl() ?>
                            <div class='clear-div'></div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <p class='error-message'></p>
        <p>
            <button id="save_product" tabindex="10">Create Product</button>
        </p>

		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("change", ".missing-facet-option", function () {
                if ($(this).val() == "exist") {
                    $(this).closest("div.product-facet-row").find(".existing-product-facet-id, .existing-facet-option, .existing-facet-value").removeClass("hidden");
                } else {
                    $(this).closest("div.product-facet-row").find(".existing-product-facet-id, .existing-facet-option, .existing-facet-value").addClass("hidden");
                }
                $(this).closest("div.product-facet-row").find(".existing-facet-option").trigger("change");
            });
            $(document).on("change", ".existing-facet-option", function () {
                if (empty($(this).val()) && $(this).closest("div.product-facet-row").find(".missing-facet-option").val() == "exist") {
                    $(this).closest("div.product-facet-row").find(".existing-facet-value").removeClass("hidden");
                } else {
                    $(this).closest("div.product-facet-row").find(".existing-facet-value").addClass("hidden");
                }
            });
            $(document).on("change", ".existing-product-facet-id", function () {
                $(this).closest("div.product-facet-row").find(".existing-facet-option").find("option[value!='']").remove();
                const $existingFacetOption = $(this).closest("div.product-facet-row").find(".existing-facet-option");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_facet_options&product_facet_id=" + $(this).val(), function(returnArray) {
                        if ("product_facet_options" in returnArray) {
                            const thisFacetValue = $existingFacetOption.closest("div.product-facet-row").find(".add-facet-value").val();
                            for (var i in returnArray['product_facet_options']) {
                                $existingFacetOption.append($("<option></option>").attr("value", returnArray['product_facet_options'][i]['key_value']).text(returnArray['product_facet_options'][i]['description']).prop("selected", (thisFacetValue == returnArray['product_facet_options'][i]['description'])));
                            }
                        }
                    });
                }
            });
            $(document).on("change", "#image_id_file", function () {
                $("#use_coreware_images").prop("checked", false);
            });
            $("#save_product").click(function () {
                if ($("#_edit_form").validationEngine("validate")) {
                    $("#_post_iframe").html("");
                    $("body").addClass("waiting-for-ajax");
                    $("#_post_iframe").off("load");
                    $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_product").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").on("load", function () {
                        if (postTimeout != null) {
                            clearTimeout(postTimeout);
                        }
                        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                        var returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            return;
                        }
                        if (!("error_message" in returnArray)) {
                            for (instance in CKEDITOR.instances) {
                                CKEDITOR.instances[instance].destroy();
                                $("#" + instance).data("checked", "false");
                            }
                            $("#_edit_form").clearForm();
                            $(".product-facet-row").remove();
                            $("p.merge-field").html("");
                            $(".selector-value-delete-list").val("");
                            $(".selector-value-list").val("").trigger("change");
                            $("img.merge-field").attr("src", "/images/empty.jpg");
                            $("a.merge-field").attr("href", "#");
                            $("#use_coreware_images").prop("checked", true);
                            $('html,body').animate({ scrollTop: 0 }, 250, 'swing');
                            $("#import_upc_code").val("").focus();
                        }
                    });
                    postTimeout = setTimeout(function () {
                        postTimeout = null;
                        $("#_post_iframe").off("load");
                        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                        displayErrorMessage("Server not responding");
                    }, 60000);
                }
                return false;
            });
            $(document).on("change", ".product-facet-field", function () {
                if (empty($(this).val())) {
                    $(this).closest(".merge-data-cell").find(".facet-value-wrapper").removeClass("hidden");
                    $(this).closest(".merge-data-cell").find(".facet-value").focus();
                } else {
                    $(this).closest(".merge-data-cell").find(".facet-value-wrapper").addClass("hidden");
                }
            });
            $(document).on("keyup", "#import_upc_code", function (event) {
                if (event.which === 13 || event.which === 3) {
                    $(this).trigger("change");
                }
            });
            $(document).on("change", "#import_upc_code", function () {
                const upcCode = $(this).val();
                if (empty(upcCode)) {
                    return false;
                }
                if (upcCode == ($("#upc_code").val())) {
                    return false;
                }
                $(".product-facet-row").remove();
                for (instance in CKEDITOR.instances) {
                    CKEDITOR.instances[instance].destroy();
                    $("#" + instance).data("checked", "false");
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_upc_code&upc_code=" + encodeURIComponent(upcCode), function(returnArray) {
                    for (var i in returnArray['product_data']) {
                        if ($("#" + i).is("p")) {
                            $("#" + i).html(returnArray['product_data'][i]);
                        } else if ($("#" + i).is("img")) {
                            $("#" + i).attr("src", returnArray['product_data'][i]);
                        } else if ($("#" + i).is("a")) {
                            $("#" + i).attr("href", returnArray['product_data'][i]);
                        } else {
                            $("#" + i).val(returnArray['product_data'][i]);
                        }
                    }
                    if (empty(returnArray['product_data']['product_id'])) {
                        $("#_image_id_row").removeClass("hidden");
                        $("#exists_message").html("");
                        $("#save_product").html("Create Product");
                    } else {
                        $("#_image_id_row").addClass("hidden");
                        $("#exists_message").html("This product already exists. Changes will update existing product.");
                        $("#save_product").html("Update Product");
                    }
                    $("#merge_data_form").append(returnArray['product_data']['product_facets']);
                    $(".selector-value-list").trigger("change");
                    if ($().prettyPhoto) {
                        $("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({ social_tools: false, default_height: 480, default_width: 854, deeplinking: false });
                    }
                    $("#_edit_form").find(".autocomplete-field").trigger("get_autocomplete_text");
                    addCKEditor();
                });
            });
            setTimeout(function () {
                $("#upc_code").prop("readonly", true);
                $("#import_upc_code").focus();
                $("#detailed_description_cssc").addClass("ck-editor");
                $("#detailed_description").addClass("ck-editor");
            }, 200);
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #exists_message {
                font-size: 1.2rem;
                font-weight: bold;
            }
            #merge_data_form {
                display: table;
                width: 100%;
                margin-bottom: 20px;
            }

            #_main_content .merge-data-row {
                display: table-row;
            }

            #_main_content .merge-data-cell {
                display: table-cell;
                padding: 5px 20px 5px 10px;
                background-color: rgb(240, 240, 240);
                border-bottom: 1px solid rgb(220, 220, 220);
                vertical-align: top;
            }

            #_main_content .merge-data-label {
                display: table-cell;
                padding: 5px 10px 5px 20px;
                background-color: rgb(240, 240, 240);
                border-bottom: 1px solid rgb(220, 220, 220);
                text-align: right;
                vertical-align: top;
            }

            #_main_content .merge-data-cell p {
                line-height: 2;
            }

            #merge_data_form label {
                font-size: .9rem;
            }

            #merge_data_form textarea {
                width: 95%;
            }

            #merge_data_form input[type=text] {
                width: 95%;
            }

            #_main_content input[readonly=readonly]:hover {
                cursor: pointer;
            }

            .highlighted-field {
                background-color: rgb(255, 200, 200);
            }

            #image_id_cssc {
                max-width: 100%;
                max-height: 100px;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>
		<?php
	}
}

$pageObject = new UPCProductImportPage();
$pageObject->displayPage();
