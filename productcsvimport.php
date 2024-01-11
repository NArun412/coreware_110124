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

$GLOBALS['gPageCode'] = "PRODUCTCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class ProductCsvImportPage extends Page {

	var $iValidDefaultFields = array("product_id", "product_code", "description", "detailed_description", "link_name", "product_manufacturer", "base_cost", "list_price", "image_urls", "product_type",
		"model", "upc_code", "isbn", "isbn_13", "manufacturer_sku", "manufacturer_advertised_price", "width", "length", "height", "weight", "ffl_required", "class_3", "product_categories", "product_tags", "minimum_price",
		"quantity", "total_cost", "product_price_type_code", "location_code", "bin_number", "start_date", "end_date", "product_price", "reorder_level", "replenishment_level", "internal_use_only",
		"remove_product_categories", "state_restrictions", "unit_code", "inventory_notes", "user_type_code", "sort_order", "cart_minimum", "cart_maximum", "order_maximum", "cannot_dropship", "pricing_structure_id",
		"replace_primary_image", "replace_existing_images", "non_inventory_item", "virtual_product", "not_taxable", "serializable", "no_online_order", "inactive");
	var $iValidFacetFields = array();
	var $iValidProductTagFields = array();
	var $iValidCustomFields = array();
	var $iValidLocationFields = array();
	var $iProgramLogId = "";

	var $iRequiredFields = array("description");
	var $iNumericFields = array("base_cost", "list_price", "manufacturer_advertised_price", "width", "length", "height", "weight", "quantity", "total_cost", "product_price", "sort_order");
	private $iShowDetailedErrors = false;

	function setup() {
		if ($_GET['simplified']) {
			$this->iValidDefaultFields = array("product_id", "product_code", "description", "detailed_description", "link_name", "product_manufacturer", "base_cost", "list_price", "minimum_price",
				"image_urls", "model", "upc_code", "manufacturer_sku", "manufacturer_advertised_price", "width", "length", "height", "weight", "ffl_required", "class_3",
				"product_categories", "product_tags", "quantity", "total_cost", "location_code", "remove_product_categories", "state_restrictions", "inactive");
		}

		if (empty($_GET['simplified'])) {
			$resultSet = executeQuery("select * from product_facets where client_id = ? and inactive = 0 order by product_facet_code", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$this->iValidFacetFields[] = "facet-" . strtolower($row['product_facet_code']);
			}
			$resultSet = executeQuery("select * from product_tags where client_id = ? and inactive = 0 order by product_tag_code", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$this->iValidProductTagFields[] = "product_tag-" . strtolower($row['product_tag_code']);
				$this->iValidProductTagFields[] = "product_tag-" . strtolower($row['product_tag_code']) . "-start_date";
				$this->iValidProductTagFields[] = "product_tag-" . strtolower($row['product_tag_code']) . "-expiration_date";
			}
			$resultSet = executeQuery("select * from locations where client_id = ? and inactive = 0 order by location_code", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$locationQuantityFieldName = "location_" . strtolower($row['location_code']) . "_quantity";
				$locationSalePriceFieldName = "location_" . strtolower($row['location_code']) . "_sale_price";
				$this->iNumericFields[] = $locationQuantityFieldName;
				$this->iValidLocationFields[] = $locationQuantityFieldName;
				$this->iNumericFields[] = $locationSalePriceFieldName;
				$this->iValidLocationFields[] = $locationSalePriceFieldName;
			}
		}
		$resultSet = executeQuery("select * from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'PRODUCTS') and inactive = 0 and client_id = ? order by custom_field_code", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$this->iValidCustomFields[] = "custom_field-" . strtolower($row['custom_field_code']);
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "remove_import":
				$csvImportId = getFieldFromId("csv_import_id", "csv_imports", "csv_import_id", $_GET['csv_import_id']);
				if (empty($csvImportId)) {
					$returnArray['error_message'] = "Invalid CSV Import";
					ajaxResponse($returnArray);
					break;
				}
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "products", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: change log";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$deleteSet = executeQuery("delete from product_data where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: product_data";
					ajaxResponse($returnArray);
					break;
				}

				$imageIds = array();
				$resultSet = executeQuery("select image_id from products where image_id is not null and product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				while ($row = getNextRow($resultSet)) {
					$imageIds[] = $row['image_id'];
				}
				$resultSet = executeQuery("select image_id from product_images where image_id is not null and product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				while ($row = getNextRow($resultSet)) {
					$imageIds[] = $row['image_id'];
				}

				$deleteSet = executeQuery("delete from product_images where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: product images";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from product_tag_links where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: product tag links";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from product_category_links where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: product category links";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from product_facet_values where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: product facet values";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from product_inventory_log where product_inventory_id in (select product_inventory_id from product_inventories where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?))", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: product inventory log";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from product_search_word_values where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: product_search_word_values";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from products where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: products";
					ajaxResponse($returnArray);
					break;
				}

				if (!empty($imageIds)) {
					$deleteSet = executeQuery("delete from images where image_id in (" . implode(",", $imageIds) . ") and client_id = ?", $GLOBALS['gClientId']);
					if (!empty($deleteSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = "Unable to remove import due to use of or changes to products: images";
						ajaxResponse($returnArray);
						break;
					}
				}

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to products";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $deleteSet['sql_error'];
					ajaxResponse($returnArray);
					break;
				}

				$returnArray['info_message'] = "Import successfully removed";
				$returnArray['csv_import_id'] = $csvImportId;
				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				ajaxResponse($returnArray);

				break;
			case "select_products":
				$pageId = $GLOBALS['gAllPageCodes']['PRODUCTMAINT'];
				executeQuery("delete from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $pageId);
				executeQuery("insert into selected_rows (user_id,page_id,primary_identifier) select " . $GLOBALS['gUserId'] . "," . $pageId .
					",primary_identifier from csv_import_details where csv_import_id = ?", $_GET['csv_import_id']);
				$returnArray['info_message'] = "Product selected in Products Maintenance program";
				ajaxResponse($returnArray);
				break;
			case "download_csv":
				header("Pragma: public");
				header("Expires: 0");
				header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header("Content-Type: application/force-download");
				header("Content-Type: application/octet-stream");
				header("Content-Type: application/download");
				header("Content-Disposition: attachment;filename=productimport.csv");
				$content = getFieldFromId("content", "csv_imports", "csv_import_id", $_GET['csv_import_id']);
				echo $content;
				exit;
			case "import_csv":
				if (!array_key_exists("csv_file", $_FILES)) {
					$returnArray['error_message'] = "No File uploaded";
					ajaxResponse($returnArray);
					break;
				}

				$fieldValue = file_get_contents($_FILES['csv_file']['tmp_name']);
				$hashCode = md5($fieldValue);
                $retryImport = false;
                $csvImportRow = getMultipleFieldsFromId(array("csv_import_id", "successful"), "csv_imports", "hash_code", $hashCode);
				if (!empty($csvImportRow)) {
                    if($csvImportRow['successful']) {
                        $returnArray['error_message'] = "This file has already been imported.";
                        ajaxResponse($returnArray);
                        break;
                    } else {
                        $retryImport = true;
                    }
				}
				$openFile = fopen($_FILES['csv_file']['tmp_name'], "r");
				$GLOBALS['gStartTime'] = getMilliseconds();
				$this->addResult("Start Product CSV Import");

				$allValidFields = array_merge($this->iValidDefaultFields, $this->iValidFacetFields,
					$this->iValidProductTagFields, $this->iValidCustomFields, $this->iValidLocationFields);
				$requiredFields = $this->iRequiredFields;
				$numericFields = $this->iNumericFields;

				$fieldNames = array();
				$importRecords = array();
				$count = 0;
				$errorMessage = "";
				while ($csvData = fgetcsv($openFile)) {
					if ($count == 0) {
						foreach ($csvData as $thisName) {
							$fieldNames[] = makeCode(trim($thisName), array("lowercase" => true, "allow_dash" => true));
						}
						$invalidFields = "";
						foreach ($fieldNames as $index => $fieldName) {
							if (!in_array($fieldName, $allValidFields)) {
								$fieldCreated = false;
								if ($GLOBALS['gUserRow']['superuser_flag'] && !empty($_POST['create_control_data'])) {
									if (substr($fieldName, 0, strlen("facet-")) == "facet-") {
										$productFacetCode = makeCode(str_replace("facet-", "", $fieldName));
										$description = ucwords(strtolower(str_replace("_", " ", $productFacetCode)));
										$insertSet = executeQuery("insert into product_facets (client_id,product_facet_code,description) values (?,?,?)", $GLOBALS['gClientId'], $productFacetCode, $description);
										$allValidFields[] = $fieldName;
										$fieldCreated = true;
									} else if (substr($fieldName, 0, strlen("product_tag-")) == "product_tag-") {
										$productTagCode = makeCode(str_replace("product_tag-", "", $fieldName));
										$description = ucwords(strtolower(str_replace("_", " ", $productTagCode)));
										$insertSet = executeQuery("insert into product_tags (client_id,product_tag_code,description) values (?,?,?)", $GLOBALS['gClientId'], $productTagCode, $description);
										$allValidFields[] = $fieldName;
										$fieldCreated = true;
									} else if (substr($fieldName, 0, strlen("custom_field-")) == "custom_field-") {
										$customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "PRODUCTS");
										$parts = explode("-", $fieldName);
										$customFieldCode = makeCode($parts[1]);
										$description = ucwords(strtolower(str_replace("_", " ", $customFieldCode)));
										$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
											$GLOBALS['gClientId'], $customFieldCode, $description, $customFieldTypeId, $description);
										$customFieldId = $insertSet['insert_id'];
										executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,'data_type',?)", $customFieldId, (empty($parts[2]) ? "varchar" : $parts[2]));
										$allValidFields[] = "custom_field-" . $parts[1];
										$fieldNames[$index] = "custom_field-" . $parts[1];
										$fieldCreated = true;
									}
								}
								if (!$fieldCreated) {
									$invalidFields .= (empty($invalidFields) ? "" : ", ") . $fieldName;
								}
							}
						}
						if (!empty($invalidFields)) {
							$errorMessage .= "<p>Invalid fields in CSV: " . $invalidFields . " <a class='valid-fields-trigger'>View valid fields</a></p>";
						}
						$this->addResult("Fields: " . jsonEncode($fieldNames));
					} else {
						$fieldData = array();
						foreach ($csvData as $index => $thisData) {
							$thisFieldName = $fieldNames[$index];
							if (in_array($thisFieldName, $numericFields)) {
                                $thisData = str_replace(["$",","],"",trim($thisData));
							}
                            $fieldData[$thisFieldName] = trim(convertSmartQuotes($thisData));
						}
						$importRecords[] = $fieldData;
					}
					$count++;
				}
				fclose($openFile);
				$this->addResult("File loaded into array, count: " . count($importRecords));

				$unitCodes = array();
				$productTypes = array();
				$userTypes = array();
				$productCategories = array();
				$productTags = array();
				$productManufacturers = array();
				$productCodeArray = array();
				$productPriceTypes = array();
				$restrictedStates = array();
				$pricingStructures = array();
				$locations = array();
				$productsFound = 0;
				$processCount = 0;
				foreach ($importRecords as $index => $thisRecord) {
					$processCount++;
					$missingFields = "";
					$upcCode = ProductCatalog::makeValidUPC(trim($thisRecord['upc_code'], " \t'"));
					$productCode = makeCode($thisRecord['product_code'], array("allow_dash" => true));
					$productId = getFieldFromId("product_id", "products", "product_id", $thisRecord['product_id']);
					if (empty($productId)) {
						if (empty($upcCode) && empty($productCode)) {
							if (empty($_POST['skip_unknown'])) {
								$errorMessage .= "<p>Line " . ($index + 2) . ": Product Code or UPC is required</p>";
							}
							continue;
						}
					}
					if (!empty($thisRecord['start_date']) && date("Y-m-d", strtotime($thisRecord['start_date'])) < '1961-07-23') {
						$errorMessage .= "<p>Line " . ($index + 2) . ": price start date is out of range</p>";
					}
					if (!empty($thisRecord['end_date']) && date("Y-m-d", strtotime($thisRecord['end_date'])) < '1961-07-23') {
						$errorMessage .= "<p>Line " . ($index + 2) . ": price end date is out of range</p>";
					}

					if (empty($productId) && !empty($productCode)) {
						$productId = getFieldFromId("product_id", "products", "product_code", $productCode);
					}
					if (empty($productId) && !empty($upcCode)) {
						$productId = getFieldFromId("product_id", "product_data", "upc_code", $upcCode);
					}
					if (!empty($productId)) {
						$productsFound++;
					}
					if (!empty($_POST['skip_not_found']) && empty($productId)) {
						continue;
					}

					if (empty($productId)) {
						foreach ($requiredFields as $thisField) {
							if (empty($thisRecord[$thisField])) {
								$missingFields .= (empty($missingFields) ? "" : ", ") . $thisField;
							}
						}
					}
					if (!empty($missingFields)) {
						$errorMessage .= "<p>Line " . ($index + 2) . " has missing fields: " . $missingFields . "</p>";
					}

					if (!empty($productCode)) {
						$checkProductId = getFieldFromId("product_id", "products", "product_code", $productCode);
						if (!empty($checkProductId) && $checkProductId != $productId) {
							$errorMessage .= "<p>Line " . ($index + 2) . ": Product ID " . $productId . ", Product Code " . $productCode . " already exists for product ID - " . $checkProductId . "</p>";
						}
					}
					if (!empty($upcCode)) {
						$checkProductId = getFieldFromId("product_id", "product_data", "upc_code", $upcCode);
						if (!empty($checkProductId) && $checkProductId != $productId) {
							$errorMessage .= "<p>Line " . ($index + 2) . ": Product ID " . $productId . ", Duplicate Product: " . $checkProductId . "</p>";
						}
					}

					if (!empty($productCode) && array_key_exists($productCode, $productCodeArray)) {
						$errorMessage .= "<p>Duplicate product codes (" . $productCode . ") in the file at line " . $productCodeArray[$productCode] . " and " . ($index + 2) . "</p>";
					}
					$productCodeArray[$productCode] = ($index + 2);

					foreach ($numericFields as $fieldName) {
						if (!empty($thisRecord[$fieldName]) && !is_float($thisRecord[$fieldName]) && !is_numeric($thisRecord[$fieldName])) {
							$errorMessage .= "<p>Line " . ($index + 2) . ": " . $fieldName . " needs to be numeric: " . $thisRecord[$fieldName] . "</p>";
						}
					}
					if (!empty($thisRecord['product_manufacturer']) && !array_key_exists($thisRecord['product_manufacturer'], $productManufacturers)) {
						$productManufacturers[$thisRecord['product_manufacturer']] = "";
					}
					if (!empty($thisRecord['product_type'])) {
						if (!array_key_exists($thisRecord['product_type'], $productTypes)) {
							$productTypes[$thisRecord['product_type']] = "";
						}
					}
					if (!empty($thisRecord['user_type_code'])) {
						if (!array_key_exists($thisRecord['user_type_code'], $userTypes)) {
							$userTypes[$thisRecord['user_type_code']] = "";
						}
					}
					if (!empty($thisRecord['unit_code'])) {
						if (!array_key_exists($thisRecord['unit_code'], $unitCodes)) {
							$unitCodes[$thisRecord['unit_code']] = "";
						}
					}
					if (!empty($thisRecord['product_price_type_code'])) {
						if (!array_key_exists($thisRecord['product_price_type_code'], $productPriceTypes)) {
							$productPriceTypes[$thisRecord['product_price_type_code']] = "";
						}
					}
					if (!empty($thisRecord['location_code'])) {
						if (!array_key_exists($thisRecord['location_code'], $locations)) {
							$locations[$thisRecord['location_code']] = "";
						}
					}
					if (!empty($thisRecord['product_categories'])) {
						$categories = explode("|", $thisRecord['product_categories']);
						foreach ($categories as $thisCategory) {
							if (!array_key_exists($thisCategory, $productCategories) && !empty($thisCategory)) {
								$productCategories[$thisCategory] = "";
							}
						}
					}
					if (!empty($thisRecord['remove_product_categories'])) {
						$categories = explode("|", $thisRecord['remove_product_categories']);
						foreach ($categories as $thisCategory) {
							if (!array_key_exists($thisCategory, $productCategories) && !empty($thisCategory)) {
								$productCategories[$thisCategory] = "";
							}
						}
					}
					if (!empty($thisRecord['state_restrictions'])) {
						$states = explode("|", $thisRecord['state_restrictions']);
						foreach ($states as $thisState) {
							if (!array_key_exists($thisState, $restrictedStates) && !empty($thisState)) {
								$restrictedStates[$thisState] = $thisState;
							}
						}
					}
					if (!empty($thisRecord['product_tags'])) {
						$tags = explode("|", $thisRecord['product_tags']);
						foreach ($tags as $thisTag) {
							if (!array_key_exists($thisTag, $productTags) && !empty($thisTag)) {
								$productTags[$thisTag] = "";
							}
						}
					}
					if (!empty($thisRecord['image_urls'])) {
						$imageUrls = explode("|", str_replace(",", "|", $thisRecord['image_urls']));
						foreach ($imageUrls as $thisUrl) {
							$thisUrl = trim($thisUrl);
							if (substr($thisUrl, 0, 4) != "http") {
								$errorMessage .= "<p>Line " . ($index + 2) . ": Invalid URL: " . $thisUrl . "</p>";
							}
						}
					}
					if (array_key_exists("pricing_structure_id", $thisRecord) || array_key_exists("pricing_structure_code", $thisRecord)) {
						if (empty($pricingStructures)) {
							$resultSet = executeQuery("select pricing_structure_id,pricing_structure_code from pricing_structures where client_id = ?", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								$pricingStructures[$row['pricing_structure_code']] = $row['pricing_structure_id'];
							}
						}
						if (!array_key_exists("pricing_structure_id", $thisRecord)) {
							if (empty($thisRecord['pricing_structure_code'])) {
								$importRecords[$index]['pricing_structure_id'] = "";
							} else if (!array_key_exists($thisRecord['pricing_structure_code'], $pricingStructures)) {
								$errorMessage .= "<p>Line " . ($index + 2) . ": Invalid pricing structure code: " . $thisRecord['pricing_structure_code'] . "</p>";
							} else {
								$importRecords[$index]['pricing_structure_id'] = $pricingStructures[$thisRecord['pricing_structure_code']];
							}
						} else {
							if (!empty($thisRecord['pricing_structure_id']) && !in_array($thisRecord['pricing_structure_id'], $pricingStructures)) {
								$errorMessage .= "<p>Line " . ($index + 2) . ": Invalid pricing structure ID: " . $thisRecord['pricing_structure_id'] . "</p>";
							}
						}
					}
				}
				$this->addResult("Data validated");

				foreach ($productManufacturers as $thisManufacturer => $productManufacturerId) {
					$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_code", makeCode($thisManufacturer));
					if (empty($productManufacturerId)) {
						$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "description", $thisManufacturer);
					}
					if (empty($productManufacturerId)) {
						$errorMessage .= "<p>Invalid Product Manufacturer: " . $thisManufacturer . "</p>";
					} else {
						$productManufacturers[$thisManufacturer] = $productManufacturerId;
					}
				}
				foreach ($productTypes as $thisType => $productTypeId) {
					$productTypeId = getFieldFromId("product_type_id", "product_types", "product_type_code", makeCode($thisType));
					if (empty($productTypeId)) {
						$productTypeId = getFieldFromId("product_type_id", "product_types", "description", $thisType);
					}
					if (empty($productTypeId)) {
						$errorMessage .= "<p>Invalid Product Type: " . $thisType . "</p>";
					} else {
						$productTypes[$thisType] = $productTypeId;
					}
				}
				foreach ($userTypes as $thisType => $userTypeId) {
					$userTypeId = getFieldFromId("user_type_id", "user_types", "user_type_code", makeCode($thisType));
					if (empty($userTypeId)) {
						$userTypeId = getFieldFromId("user_type_id", "user_types", "description", $thisType);
					}
					if (empty($userTypeId)) {
						$errorMessage .= "<p>Invalid User Type: " . $thisType . "</p>";
					} else {
						$userTypes[$thisType] = $userTypeId;
					}
				}
				foreach ($unitCodes as $thisCode => $unitId) {
					$unitId = getFieldFromId("unit_id", "units", "unit_code", makeCode($thisCode));
					if (empty($unitId)) {
						$unitId = getFieldFromId("unit_id", "units", "description", $thisCode);
					}
					if (empty($unitId)) {
						$errorMessage .= "<p>Invalid Unit: " . $thisCode . "</p>";
					} else {
						$unitCodes[$thisCode] = $unitId;
					}
				}
				foreach ($productPriceTypes as $thisType => $productPriceTypeId) {
					$productPriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_code", makeCode($thisType));
					if (empty($productPriceTypeId)) {
						$productPriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "description", $thisType);
					}
					if (empty($productPriceTypeId)) {
						$errorMessage .= "<p>Invalid Product Price Type: " . $thisType . "</p>";
					} else {
						$productPriceTypes[$thisType] = $productPriceTypeId;
					}
				}
				foreach ($locations as $thisType => $locationId) {
					$locationId = getFieldFromId("location_id", "locations", "location_code", makeCode($thisType));
					if (empty($locationId)) {
						$locationId = getFieldFromId("location_id", "locations", "description", $thisType);
					}
					if (empty($locationId)) {
						$errorMessage .= "<p>Invalid Location: " . $thisType . "</p>";
					} else {
						$locations[$thisType] = $locationId;
					}
				}
				foreach ($productCategories as $thisCategory => $productCategoryId) {
					$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_code", makeCode($thisCategory));
					if (empty($productCategoryId)) {
						$productCategoryId = getFieldFromId("product_category_id", "product_categories", "description", $thisCategory);
					}
					if (empty($productCategoryId)) {
						if ($GLOBALS['gUserRow']['superuser_flag'] && !empty($_POST['create_control_data'])) {
							$description = ucwords(strtolower($thisCategory));
							$insertSet = executeQuery("insert into product_categories (client_id,product_category_code,description) values (?,?,?)",
								$GLOBALS['gClientId'], makeCode($thisCategory), $description);
							$productCategoryId = $insertSet['insert_id'];
							$productCategories[$thisCategory] = $productCategoryId;
						} else {
							$errorMessage .= "<p>Invalid Product Category: " . $thisCategory . "</p>";
						}
					} else {
						$productCategories[$thisCategory] = $productCategoryId;
					}
				}
				$validStates = getStateArray();
				foreach ($restrictedStates as $thisState) {
					if (!array_key_exists($thisState, $validStates)) {
						$errorMessage .= "<p>Invalid State Code: " . $thisState . "</p>";
					}
				}
				foreach ($productTags as $thisTag => $productTagId) {
					$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", makeCode($thisTag));
					if (empty($productTagId)) {
						$productTagId = getFieldFromId("product_tag_id", "product_tags", "description", $thisTag);
					}
					if (empty($productTagId)) {
						$errorMessage .= "<p>Invalid Product Tag: " . $thisTag . "</p>";
					} else {
						$productTags[$thisTag] = $productTagId;
					}
				}
				$productTagIds = array();
				$resultSet = executeQuery("select * from product_tags where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$productTagIds[$row['product_tag_code']] = $row['product_tag_id'];
				}
                $customFields = array();
                $resultSet = executeQuery("select custom_fields.*,(select control_value from custom_field_controls where custom_field_id = custom_fields.custom_field_id and control_name = 'data_type' limit 1) data_type " .
                    " from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'PRODUCTS') and inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
                while ($row = getNextRow($resultSet)) {
                    $customFields[$row['custom_field_code']] = $row;
                }

				if (!empty($errorMessage)) {
					$returnArray['import_error'] = $errorMessage;
                    $this->addResult("Import failed: ". $returnArray['error_message']);
					ajaxResponse($returnArray);
					break;
				}
				$this->addResult("Controls validated");

				executeQuery("delete from query_log");

                if($retryImport) {
                    $resultSet = executeQuery("update csv_imports set description = ?,time_submitted = now(),user_id = ? where csv_import_id = ?",
                        $_POST['description'],  $GLOBALS['gUserId'], $csvImportRow['csv_import_id']);
                    if (!empty($resultSet['sql_error'])) {
                        $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                        $returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']) . ($this->iShowDetailedErrors ? ": " . $resultSet['sql_error'] : "");
                        $this->addResult("Import failed: " . $returnArray['error_message']);
                        ajaxResponse($returnArray);
                        break;
                    }
                    $csvImportId = $csvImportRow['csv_import_id'];
                } else {
                    $resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id,content) values (?,?,'products',?,now(),?,?)",
                        $GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId'], file_get_contents($_FILES['csv_file']['tmp_name']));
                    if (!empty($resultSet['sql_error'])) {
                        $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                        $returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']) . ($this->iShowDetailedErrors ? ": " . $resultSet['sql_error'] : "");
                        $this->addResult("Import failed: " . $returnArray['error_message']);
                        ajaxResponse($returnArray);
                        break;
                    }
                    $csvImportId = $resultSet['insert_id'];
                }

				$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED");
				$class3ProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "CLASS_3");
				ProductCatalog::getInventoryAdjustmentTypes();

				$lineNumber = 0;
				$insertCount = 0;
				$updateCount = 0;
				$totalCount = 0;
				$this->addResult("Begin processing " . count($importRecords) . " records");
				$this->iShowDetailedErrors = $GLOBALS['gUserRow']['superuser_flag'] ?: !empty(getPreference("CSV_IMPORT_DETAILED_ERRORS"));

				foreach ($importRecords as $index => $thisRecord) {
					$GLOBALS['gPrimaryDatabase']->startTransaction();
					$lineNumber++;
					$upcCode = ProductCatalog::makeValidUPC(trim($thisRecord['upc_code'], " \t'"));

					if (empty(ltrim($upcCode, "0"))) {
						$upcCode = "";
					}
					$thisRecord['upc_code'] = $upcCode;
					$thisRecord['isbn'] = $isbn = ProductCatalog::makeValidISBN($thisRecord['isbn']);
					$thisRecord['isbn_13'] = $isbn13 = ProductCatalog::makeValidISBN13($thisRecord['isbn_13']);
					$productCode = makeCode($thisRecord['product_code'], array("allow_dash" => true));
					if (empty($productCode) && empty($thisRecord['upc_code']) && !empty($_POST['skip_unknown'])) {
						continue;
					}
					$productId = getFieldFromId("product_id", "products", "product_id", $thisRecord['product_id']);
					if (empty($productId)) {
						$productId = getFieldFromId("product_id", "products", "product_code", $productCode);
					}
					if (empty($productId)) {
						$productId = getFieldFromId("product_id", "product_data", "upc_code", $upcCode);
					}
					if (!empty($_POST['skip_not_found']) && empty($productId)) {
						continue;
					}
					$totalCount++;
					if ($totalCount % 200 == 0) {
						$this->addResult($totalCount . " Records processed");
					}


					if (empty($thisRecord['link_name']) && empty($productId)) {
						$originalLinkName = makeCode($thisRecord['description'], array("use_dash" => true, "lowercase" => true));
						$linkNumber = -1;
						do {
							$linkNumber++;
							$duplicateProductId = getFieldFromId("product_id", "products", "link_name", $originalLinkName . (empty($linkNumber) ? "" : "_" . $linkNumber));
						} while (!empty($duplicateProductId));
						$thisRecord['link_name'] = $originalLinkName . (empty($linkNumber) ? "" : "_" . $linkNumber);
					} else {
						if (!empty($thisRecord['link_name'])) {
							$thisRecord['link_name'] = makeCode($thisRecord['link_name'], array("use_dash" => true, "lowercase" => true));
						}
					}

					$imageResults = ProductCatalog::processProductImages($productId,$thisRecord);
					$imageId = $imageResults['image_id'];
					$imageIds = $imageResults['product_images'];

					$productTypeId = $productTypes[$thisRecord['product_type']];
					$userTypeId = $userTypes[$thisRecord['user_type_code']];
					$productManufacturerId = $productManufacturers[$thisRecord['product_manufacturer']];
					$descriptionWords = explode(" ", $thisRecord['description']);
					$oneWord = $descriptionWords[0];
					$twoWords = $descriptionWords[0] . " " . $descriptionWords[1];
					if (empty($productManufacturerId)) {
						$productManufacturerId = $productManufacturers[makeCode($oneWord)];
					}
					if (empty($productManufacturerId)) {
						$productManufacturerId = $productManufacturers[$oneWord];
					}
					if (empty($productManufacturerId)) {
						$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "client_id", $GLOBALS['gClientId'],
							"product_manufacturer_code = ? or description = ?", makeCode($oneWord), $oneWord);
					}
					if (empty($productManufacturerId)) {
						$productManufacturerId = $productManufacturers[makeCode($twoWords)];
					}
					if (empty($productManufacturerId)) {
						$productManufacturerId = $productManufacturers[$twoWords];
					}
					if (empty($productManufacturerId)) {
						$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "client_id", $GLOBALS['gClientId'],
							"product_manufacturer_code = ? or description = ?", makeCode($twoWords), $twoWords);
					}

					if (empty($productId)) {
						$insertSet = executeQuery("insert into products (client_id,product_code,description,detailed_description,link_name, product_type_id,product_manufacturer_id,base_cost,list_price,image_id," .
							"no_online_order,date_created,time_changed,reindex,sort_order,internal_use_only,cart_minimum,cart_maximum,order_maximum,cannot_dropship,non_inventory_item,virtual_product,not_taxable,serializable, inactive) values " .
							"(?,?,?,?,?, ?,?,?,?,?, ?,current_date,now(),1,?,?, ?,?,?,?,?, ?,?,?,?)", $GLOBALS['gClientId'], $productCode, $thisRecord['description'],
							$thisRecord['detailed_description'], $thisRecord['link_name'], $productTypeId, $productManufacturerId, $thisRecord['base_cost'], $thisRecord['list_price'], $imageId,
							(empty($thisRecord['no_online_order']) ? 0 : 1), (strlen($thisRecord['sort_order']) == 0 ? 100 : $thisRecord['sort_order']), (empty($thisRecord['internal_use_only']) ? 0 : 1),
							$thisRecord['cart_minimum'], $thisRecord['cart_maximum'], $thisRecord['order_maximum'], (empty($thisRecord['cannot_dropship']) ? 0 : 1),
							(empty($thisRecord['non_inventory_item']) ? 0 : 1), (empty($thisRecord['virtual_product']) ? 0 : 1), (empty($thisRecord['not_taxable']) ? 0 : 1),
							(empty($thisRecord['serializable']) ? 0 : 1), (empty($thisRecord['inactive']) ? 0 : 1));
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                            $this->addResult("Import failed: ". $returnArray['error_message']);
                            ajaxResponse($returnArray);
							break;
						}
						$productId = $insertSet['insert_id'];
						$insertCount++;
					} else {
						$nameValues = array();
						if (!empty($productCode)) {
							$nameValues['product_code'] = $productCode;
						}
						if (!empty($productTypeId)) {
							$nameValues['product_type_id'] = $productTypeId;
						}
						if (!empty($imageId)) {
							$nameValues['image_id'] = $imageId;
						}
						if (!empty($thisRecord['product_manufacturer'])) {
							$nameValues['product_manufacturer_id'] = $productManufacturers[$thisRecord['product_manufacturer']];
						}
						foreach (array("description", "detailed_description", "link_name", "base_cost", "list_price") as $fieldName) {
							if (!empty($thisRecord[$fieldName])) {
								$nameValues[$fieldName] = $thisRecord[$fieldName];
							}
						}
						foreach (array("cart_minimum", "cart_maximum", "order_maximum") as $numericField) {
							if (array_key_exists($numericField, $thisRecord)) {
								$nameValues[$numericField] = (is_numeric($thisRecord[$numericField]) ? $thisRecord[$numericField] : 0);
							}
						}
						if (array_key_exists("sort_order", $thisRecord)) {
							$nameValues['sort_order'] = $thisRecord['sort_order'];
						}
						foreach (array("cannot_dropship", "non_inventory_item", "virtual_product", "not_taxable", "serializable", "no_online_order", "internal_use_only", "inactive") as $tinyintField) {
							if (array_key_exists($tinyintField, $thisRecord)) {
								$nameValues[$tinyintField] = (empty($thisRecord[$tinyintField]) ? 0 : 1);
							}
						}
						$nameValues['time_changed'] = date("Y-m-d H:i:s");
						$dataTable = new DataTable("products");
						$dataTable->setPrimaryId($productId);
						$dataTable->setSaveOnlyPresent(true);
						if (!$dataTable->saveRecord(array("name_values" => $nameValues))) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $dataTable->getErrorMessage();
                            $this->addResult("Import failed: ". $returnArray['error_message']);
                            ajaxResponse($returnArray);
							break;
						}
						$updateCount++;
					}
					$productDataId = getFieldFromId("product_data_id", "product_data", "product_id", $productId);
					if (empty($productDataId)) {
						$insertSet = executeQuery("insert into product_data (client_id,product_id,model,upc_code,isbn,isbn_13,manufacturer_sku,minimum_price," .
							"manufacturer_advertised_price,width,length,height,weight,unit_id) values (?,?,?,?,?, ?,?,?,?,?, ?,?,?,?)",
							$GLOBALS['gClientId'], $productId, $thisRecord['model'], $upcCode, $isbn, $isbn13, $thisRecord['manufacturer_sku'], $thisRecord['minimum_price'],
							$thisRecord['manufacturer_advertised_price'], $thisRecord['width'], $thisRecord['length'], $thisRecord['height'], $thisRecord['weight'], $unitCodes[$thisRecord['unit_code']]);
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'] . ": " . $lineNumber;
                            $this->addResult("Import failed: ". $returnArray['error_message']);
                            ajaxResponse($returnArray);
							break;
						}
						$productDataId = $insertSet['insert_id'];
					} else {
						$checkFields = array("model", "isbn", "isbn_13", "manufacturer_sku", "minimum_price", "manufacturer_advertised_price", "width", "length", "height", "weight");
						$nameValues = array();
						if (!empty($upcCode)) {
							$nameValues['upc_code'] = $upcCode;
						}
						foreach ($checkFields as $fieldName) {
							if (array_key_exists($fieldName, $thisRecord) && strlen($thisRecord[$fieldName]) > 0) {
								$nameValues[$fieldName] = $thisRecord[$fieldName];
							}
						}
						if (!empty($nameValues['unit_code'])) {
							$nameValues['unit_id'] = $unitCodes[$thisRecord['unit_code']];
						}
						if (!empty($nameValues)) {
							$dataTable = new DataTable("product_data");
							$dataTable->setPrimaryId($productDataId);
							if (!$dataTable->saveRecord(array("name_values" => $nameValues))) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = $dataTable->getErrorMessage() . ": (Update) " . $lineNumber;
                                $this->addResult("Import failed: ". $returnArray['error_message']);
                                ajaxResponse($returnArray);
								break;
							}
							removeCachedData("base_cost", $productId);
							removeCachedData("*", $productId);
							removeCachedData("*", $productId);
						}
					}
					if ($_POST['manufacturer_map'] && array_key_exists("manufacturer_advertised_price",$thisRecord)) {
						executeQuery("update product_data set map_expiration_date = ? where product_data_id = ?",date("Y-m-d",strtotime("+ 6 months")),$productDataId);
					}

					$salePriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_code", "SALE_PRICE");
					if (empty($salePriceTypeId)) {
						$resultSet = executeQuery("insert into product_price_types (client_id,product_price_type_code,description) values (?,'SALE_PRICE','Sale Price')", $GLOBALS['gClientId']);
						$salePriceTypeId = $resultSet['insert_id'];
					}
					$resultSet = executeQuery("select * from locations where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$salePriceCode = "location_" . strtolower($row['location_code']) . "_sale_price";
						if (empty($thisRecord[$salePriceCode])) {
							continue;
						}
						$productPriceId = getFieldFromId("product_price_id", "product_prices", "product_id", $productId, "product_price_type_id = ? and " .
							"location_id = ? and sale_count is null and user_type_id is null and start_date is null and end_date is null", $salePriceTypeId, $row['location_id']);
						if (empty($productPriceId)) {
							$insertSet = executeQuery("insert into product_prices (product_id,product_price_type_id,price,location_id) values (?,?,?,?)",
								$productId, $salePriceTypeId, $thisRecord[$salePriceCode], $row['location_id']);
						} else {
							$insertSet = executeQuery("update product_prices set price = ? where product_price_id = ?", $thisRecord[$salePriceCode], $productPriceId);
						}
						removeCachedData("product_prices", $productId);

						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'] . " at line: " . $lineNumber . ", " . $productId;
                            $this->addResult("Import failed: ". $returnArray['error_message']);
                            ajaxResponse($returnArray);
							break;
						}
					}

					# "product_price_type_code","location_code","start_date","end_date","product_price"
					if (array_key_exists("product_price_type_code", $thisRecord) && !empty($thisRecord['product_price_type_code']) && strlen($thisRecord['product_price']) > 0) {
						$productPriceId = getFieldFromId("product_price_id", "product_prices", "product_id", $productId, "product_price_type_id = ? and " .
							"start_date <=> ? and end_date <=> ? and location_id <=> ? and sale_count is null and user_type_id <=> ?", $productPriceTypes[$thisRecord['product_price_type_code']],
							(empty($thisRecord['start_date']) ? "" : makeDateParameter($thisRecord['start_date'])),
							(empty($thisRecord['end_date']) ? "" : makeDateParameter($thisRecord['end_date'])), $locations[$thisRecord['location_code']], $userTypeId);
						if (empty($productPriceId)) {
							$insertSet = executeQuery("insert into product_prices (product_id,product_price_type_id,user_type_id,price,start_date,end_date,location_id) values (?,?,?,?,?,?,?)",
								$productId, $productPriceTypes[$thisRecord['product_price_type_code']], $userTypeId, $thisRecord['product_price'], makeDateParameter($thisRecord['start_date']), makeDateParameter($thisRecord['end_date']),
								$locations[$thisRecord['location_code']]);
						} else {
							$insertSet = executeQuery("update product_prices set price = ? where product_price_id = ?", $thisRecord['product_price'], $productPriceId);
						}
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'] . " at line: " . $lineNumber . ", " . $thisRecord['product_price_type_code'] . ", " . jsonEncode($productPriceTypes) . ", " . $productId;
                            $this->addResult("Import failed: ". $returnArray['error_message']);
                            ajaxResponse($returnArray);
							break;
						}
					}

					foreach ($imageIds as $imageId) {
						$productImageId = getFieldFromId("product_image_id", "product_images", "product_id", $productId, "image_id = ?", $imageId);
						if (empty($productImageId)) {
							$insertSet = executeQuery("insert ignore into product_images (product_id,description,image_id) values (?,'Alternate Image',?)", $productId, $imageId);
							if (!empty($insertSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                                $this->addResult("Import failed: ". $returnArray['error_message']);
                                ajaxResponse($returnArray);
								break;
							}
						}
					}

					if (!empty($thisRecord['ffl_required'])) {
						if (empty($fflRequiredProductTagId)) {
							$insertSet = executeQuery("insert into product_tags (client_id,product_tag_code,description,internal_use_only) values (?,'FFL_REQUIRED','FFL Required',1)", $GLOBALS['gClientId']);
							$fflRequiredProductTagId = $insertSet['insert_id'];
						}
						$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productId, "product_tag_id = ?", $fflRequiredProductTagId);
						if (empty($productTagLinkId)) {
							$insertSet = executeQuery("insert into product_tag_links (product_id,product_tag_id) values (?,?)", $productId, $fflRequiredProductTagId);
							if (!empty($insertSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                                $this->addResult("Import failed: ". $returnArray['error_message']);
                                ajaxResponse($returnArray);
								break;
							}
						}
						$insertSet = executeQuery("update products set time_changed = now(), serializable = 1 where product_id = ?", $productId);
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                            $this->addResult("Import failed: ". $returnArray['error_message']);
                            ajaxResponse($returnArray);
							break;
						}
					}
					if (!empty($thisRecord['class_3'])) {
						if (empty($class3ProductTagId)) {
							$insertSet = executeQuery("insert into product_tags (client_id,product_tag_code,description,internal_use_only) values (?,'CLASS_3','Class 3 Products',1)", $GLOBALS['gClientId']);
							$class3ProductTagId = $insertSet['insert_id'];
						}
						$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productId, "product_tag_id = ?", $class3ProductTagId);
						if (empty($productTagLinkId)) {
							$insertSet = executeQuery("insert into product_tag_links (product_id,product_tag_id) values (?,?)", $productId, $class3ProductTagId);
							if (!empty($insertSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                                $this->addResult("Import failed: ". $returnArray['error_message']);
                                ajaxResponse($returnArray);
								break;
							}
						}
						$insertSet = executeQuery("update products set time_changed = now(), serializable = 1 where product_id = ?", $productId);
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                            $this->addResult("Import failed: ". $returnArray['error_message']);
                            ajaxResponse($returnArray);
							break;
						}
					}
					$categories = explode("|", $thisRecord['product_categories']);
					foreach ($categories as $thisCategory) {
						if (!empty($thisCategory)) {
							$productCategoryId = $productCategories[$thisCategory];
							$productCategoryLinkId = getFieldFromId("product_category_link_id", "product_category_links", "product_id", $productId, "product_category_id = ?", $productCategoryId);
							if (empty($productCategoryLinkId)) {
								$productCategoryLinksDataTable = new DataTable("product_category_links");
								$productCategoryLinksDataTable->saveRecord(array("name_values"=>array("product_category_id"=>$productCategoryId,"product_id"=>$productId)));
							}
						}
					}
					$categories = explode("|", $thisRecord['remove_product_categories']);
					foreach ($categories as $thisCategory) {
						if (!empty($thisCategory)) {
							$productCategoryId = $productCategories[$thisCategory];
							executeQuery("delete from product_category_links where product_id = ? and product_category_id = ?", $productId, $productCategoryId);
						}
					}
					$states = explode("|", $thisRecord['state_restrictions']);
					foreach ($states as $thisState) {
						if (!empty($thisState)) {
							$productRestrictionId = getFieldFromId("product_restriction_id", "product_restrictions", "product_id", $productId, "state = ? and country_id = 1000 and postal_code is null", $thisState);
							if (empty($productRestrictionId)) {
								$insertSet = executeQuery("insert into product_restrictions (product_id,state,country_id) values (?,?,1000)", $productId, $thisState);
								if (!empty($insertSet['sql_error'])) {
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                                    $this->addResult("Import failed: ". $returnArray['error_message']);
                                    ajaxResponse($returnArray);
									break;
								}
							}
						}
					}
                    foreach ($customFields as $customFieldCode => $customFieldRow) {
                        if(array_key_exists('custom_field-' . strtolower($customFieldCode), $thisRecord)) {
                            $value = $thisRecord['custom_field-' . strtolower($customFieldCode)];
                            if($customFieldRow['data_type'] == "tinyint") {
                                $value = !empty($value) && !in_array(strtolower($value),['false','no']);
                            }
                            CustomField::setCustomFieldData($productId, $customFieldCode, $value, "PRODUCTS");
                        }
                    }
					foreach ($productTagIds as $productTagCode => $productTagId) {
						if (!empty($thisRecord['product_tag-' . strtolower($productTagCode)]) || !empty($thisRecord['product_tag-' . strtolower($productTagCode) . "-start_date"]) || !empty($thisRecord['product_tag-' . strtolower($productTagCode) . "-expiration_date"])) {
							if ($thisRecord['product_tag-' . strtolower($productTagCode)] == "N" && $thisRecord['product_tag-' . strtolower($productTagCode)] == "n") {
								executeQuery("delete from product_tag_links where product_id = ? and product_tag_id = ?", $productId, $productTagId);
							} else {
								if (!array_key_exists("product_tag-" . strtolower($productTagCode) . "-start_date", $thisRecord) && !array_key_exists("product_tag-" . strtolower($productTagCode) . "-expiration_date", $thisRecord)) {
									$insertSet = executeQuery("insert ignore into product_tag_links (product_id,product_tag_id) values (?,?)", $productId, $productTagId);
									if (!empty($insertSet['sql_error'])) {
										$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
										$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                                        $this->addResult("Import failed: ". $returnArray['error_message']);
                                        ajaxResponse($returnArray);
										break;
									}
								} else {
									$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productId, "product_tag_id = ?", $productTagId);
									if (empty($productTagLinkId)) {
										$insertSet = executeQuery("insert ignore into product_tag_links (product_id,product_tag_id,start_date,expiration_date) values (?,?,?,?)", $productId, $productTagId,
											(empty($thisRecord['product_tag-' . strtolower($productTagCode) . '-start_date']) ? "" : date("Y-m-d", strtotime($thisRecord['product_tag-' . strtolower($productTagCode) . '-start_date']))),
											(empty($thisRecord['product_tag-' . strtolower($productTagCode) . '-expiration_date']) ? "" : date("Y-m-d", strtotime($thisRecord['product_tag-' . strtolower($productTagCode) . '-expiration_date']))));
										if (!empty($insertSet['sql_error'])) {
											$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
											$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                                            $this->addResult("Import failed: ". $returnArray['error_message']);
                                            ajaxResponse($returnArray);
											break;
										}
									} else {
										$insertSet = executeQuery("update product_tag_links set start_date = ?,expiration_date = ? where product_tag_link_id = ?",
											(empty($thisRecord['product_tag-' . strtolower($productTagCode) . '-start_date']) ? "" : date("Y-m-d", strtotime($thisRecord['product_tag-' . strtolower($productTagCode) . '-start_date']))),
											(empty($thisRecord['product_tag-' . strtolower($productTagCode) . '-expiration_date']) ? "" : date("Y-m-d", strtotime($thisRecord['product_tag-' . strtolower($productTagCode) . '-expiration_date']))),
											$productTagLinkId);
										if (!empty($insertSet['sql_error'])) {
											$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
											$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                                            $this->addResult("Import failed: ". $returnArray['error_message']);
                                            ajaxResponse($returnArray);
											break;
										}
									}
								}
							}
						}
					}
					$tags = explode("|", $thisRecord['product_tags']);
					foreach ($tags as $thisTag) {
						if (!empty($thisTag)) {
							$productTagId = $productTags[$thisTag];
							$insertSet = executeQuery("insert ignore into product_tag_links (product_id,product_tag_id) values (?,?)", $productId, $productTagId);
							if (!empty($insertSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                                $this->addResult("Import failed: ". $returnArray['error_message']);
								ajaxResponse($returnArray);
								break;
							}
						}
					}

					$resultSet = executeQuery("select * from locations where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$inventoryCode = "location_" . strtolower($row['location_code']) . "_quantity";
						if (strlen($thisRecord[$inventoryCode]) > 0) {
							$productInventoryRow = getRowFromId("product_inventories", "product_id", $productId, "location_id = ?", $row['location_id']);
							$productInventoryId = $productInventoryRow['product_inventory_id'];
							if (empty($productInventoryId)) {
								$insertSet = executeQuery("insert into product_inventories (product_id,location_id,quantity) values (?,?,?)",
									$productId, $row['location_id'], $thisRecord[$inventoryCode]);
								if (!empty($insertSet['sql_error'])) {
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                                    $this->addResult("Import failed: ". $returnArray['error_message']);
									ajaxResponse($returnArray);
									break;
								}
								$productInventoryId = $insertSet['insert_id'];
								$affectedRows = 1;
							} else {
								if ($productInventoryRow['quantity'] != $thisRecord[$inventoryCode]) {
									$resultSet = executeQuery("update product_inventories set quantity = ? where product_inventory_id = ?", $thisRecord[$inventoryCode], $productInventoryId);
									$affectedRows = $resultSet['affected_rows'];
								} else {
									$affectedRows = 0;
								}
							}
							removeCachedData("product_prices", $productId);
							if ($affectedRows > 0) {
								if (empty($thisRecord['cost']) || $thisRecord[$inventoryCode] <= 0) {
									$thisRecord['cost'] = "";
								}
								if (empty($thisRecord['inventory_notes'])) {
									$thisRecord['inventory_notes'] = "CSV Import";
								}
								$insertSet = executeQuery("insert into product_inventory_log (product_inventory_id,inventory_adjustment_type_id,user_id,log_time,quantity,total_cost,notes) values " .
									"(?,?,?,now(),?,?,?)", $productInventoryId, $GLOBALS['gInventoryAdjustmentTypeId'], $GLOBALS['gUserId'], $thisRecord[$inventoryCode],
									(empty($thisRecord['cost']) ? "" : $thisRecord[$inventoryCode] * $thisRecord['cost']), $thisRecord['inventory_notes']);
								if (!empty($insertSet['sql_error'])) {
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                                    $this->addResult("Import failed: ". $returnArray['error_message']);
									ajaxResponse($returnArray);
									break;
								}
								$insertSet = executeQuery("delete from product_category_links where product_id = ? and product_category_id in (select product_category_id from product_categories where product_category_code = 'DISCONTINUED')", $productId);
								if (!empty($insertSet['sql_error'])) {
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                                    $this->addResult("Import failed: ". $returnArray['error_message']);
									ajaxResponse($returnArray);
									break;
								}
							}
						}
					}

					if (strlen($thisRecord['quantity']) > 0) {
						$productInventoryRow = getRowFromId("product_inventories", "product_id", $productId, "location_id = ?", $locations[$thisRecord['location_code']]);
						$productInventoryId = $productInventoryRow['product_inventory_id'];
						if (empty($productInventoryId)) {
							$insertSet = executeQuery("insert into product_inventories (product_id,location_id,bin_number,quantity,reorder_level,replenishment_level) values (?,?,?,?,?, ?)",
								$productId, $locations[$thisRecord['location_code']], $thisRecord['bin_number'], $thisRecord['quantity'], $thisRecord['reorder_level'], $thisRecord['replenishment_level']);
							if (!empty($insertSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                                $this->addResult("Import failed: ". $returnArray['error_message']);
								ajaxResponse($returnArray);
								break;
							}
							$productInventoryId = $insertSet['insert_id'];
							$affectedRows = 1;
						} else {
							if (strlen($thisRecord['reorder_level']) > 0 || strlen($thisRecord['replenishment_level']) > 0) {
								$parameters = array();
								$setWhere = "";
								if (strlen($thisRecord['reorder_level']) > 0) {
									$parameters[] = $thisRecord['reorder_level'];
									$setWhere = "reorder_level = ?";
								}
								if (strlen($thisRecord['replenishment_level']) > 0) {
									$parameters[] = $thisRecord['replenishment_level'];
									$setWhere = (empty($setWhere) ? "" : ",") . "replenishment_level = ?";
								}
								if (strlen($thisRecord['bin_number']) > 0) {
									$parameters[] = $thisRecord['bin_number'];
									$setWhere = (empty($setWhere) ? "" : ",") . "bin_number = ?";
								}
								$parameters[] = $productInventoryId;
								if (!empty($setWhere)) {
									executeQuery("update product_inventories set " . $setWhere . " where product_inventory_id = ?", $parameters);
								}
							}
							if ($productInventoryRow['quantity'] != $thisRecord['quantity']) {
								$resultSet = executeQuery("update product_inventories set quantity = ? where product_inventory_id = ?", $thisRecord['quantity'], $productInventoryId);
								$affectedRows = $resultSet['affected_rows'];
							} else {
								$affectedRows = 0;
							}
						}
						removeCachedData("product_prices", $productId);
						if ($affectedRows > 0) {
							if (empty($thisRecord['total_cost']) || $thisRecord['quantity'] <= 0) {
								$thisRecord['total_cost'] = "";
							}
							if (empty($thisRecord['inventory_notes'])) {
								$thisRecord['inventory_notes'] = "CSV Import";
							}
							$insertSet = executeQuery("insert into product_inventory_log (product_inventory_id,inventory_adjustment_type_id,user_id,log_time,quantity,total_cost,notes) values " .
								"(?,?,?,now(),?,?,?)", $productInventoryId, $GLOBALS['gInventoryAdjustmentTypeId'], $GLOBALS['gUserId'], $thisRecord['quantity'], $thisRecord['total_cost'], $thisRecord['inventory_notes']);
							if (!empty($insertSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                                $this->addResult("Import failed: ". $returnArray['error_message']);
								ajaxResponse($returnArray);
								break;
							}
							$insertSet = executeQuery("delete from product_category_links where product_id = ? and product_category_id in (select product_category_id from product_categories where product_category_code = 'DISCONTINUED')", $productId);
							if (!empty($insertSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                                $this->addResult("Import failed: ". $returnArray['error_message']);
                                ajaxResponse($returnArray);
								break;
							}
						}
					}
					foreach ($fieldNames as $thisFieldName) {
						if (empty($thisRecord[$thisFieldName])) {
							continue;
						}
						if (substr($thisFieldName, 0, strlen("facet-")) == "facet-") {
							$productFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_code", strtoupper(substr($thisFieldName, strlen("facet-"))));
							if (empty($productFacetId)) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = "Invalid Product Facet: " . $thisFieldName;
                                $this->addResult("Import failed: ". $returnArray['error_message']);
                                ajaxResponse($returnArray);
								break;
							}
							$productFacetRow = getRowFromId("product_facets", "product_facet_id", $productFacetId);
							$productFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_id", $productFacetId, "facet_value = ?", $thisRecord[$thisFieldName]);
							if (empty($productFacetOptionId) && ($GLOBALS['gClientRow']['client_code'] != "COREWARE_SHOOTING_SPORTS" || empty($productFacetRow['catalog_lock']))) {
								$insertSet = executeQuery("insert into product_facet_options (product_facet_id,facet_value) values (?,?)", $productFacetId, $thisRecord[$thisFieldName]);
								$productFacetOptionId = $insertSet['insert_id'];
							}
                            if (!empty($productFacetOptionId)) {
	                            $productFacetValueId = getFieldFromId("product_facet_value_id", "product_facet_values", "product_id", $productId, "product_facet_id = ?", $productFacetId);
	                            if (empty($productFacetValueId)) {
		                            executeQuery("insert into product_facet_values (product_id,product_facet_id,product_facet_option_id) values (?,?,?)", $productId, $productFacetId, $productFacetOptionId);
	                            } else {
		                            executeQuery("update product_facet_values set product_facet_option_id = ? where product_facet_value_id = ?", $productFacetOptionId, $productFacetValueId);
	                            }
                            }
						}
					}

					$GLOBALS['gPrimaryDatabase']->commitTransaction();

					$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $productId);
					if (!empty($insertSet['sql_error'])) {
						$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                        $this->addResult("Import failed: ". $returnArray['error_message']);
						ajaxResponse($returnArray);
						break;
					}
				}
				$productIdArray = array();
				$resultSet = executeQuery("select * from change_log where client_id = ? and table_name = 'products' and column_name in ('description','detailed_description') and user_id = ? and " .
					"time_changed > date_sub(now(),interval 30 minute) and old_value <> '[NEW RECORD]'",$GLOBALS['gClientId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productIdArray[] = $row['primary_identifier'];
				}
				$productIdArray = array_unique($productIdArray);
                if (count($productIdArray) > 0) {
	                $this->iProgramLogId = addProgramLog(count($productIdArray) . " products set to be reindexed", $this->iProgramLogId);
	                executeQuery("update products set reindex = 1 where product_id in (" . implode(",", $productIdArray) . ")");
                }

				$this->addResult("Finished processing");
				executeQuery("update csv_imports set successful = 1 where csv_import_id = ?", $csvImportId);

				$returnArray['response'] = "<p>" . $insertCount . " Products imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " Products updated.</p>";
				ajaxResponse($returnArray);
				break;
		}

	}

    private function addResult($message) {
        $this->iProgramLogId = addProgramLog(numberFormat( (getMilliseconds() - $GLOBALS['gStartTime']) / 1000, 2) . ": " . $message, $this->iProgramLogId);
    }


    function mainContent() {
		echo $this->iPageData['content'];
		?>
        <div id="_form_div">
            <form id="_edit_form" enctype='multipart/form-data'>
                <div class="basic-form-line" id="_csv_file_row">
                    <label for="description" class="required-label">Description</label>
                    <input tabindex="10" class="validate[required]" size="40" type="text" id="description"
                           name="description">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="csv_file" class="required-label">CSV File</label>
                    <span class="help-label">Required Fields: Product Code/UPC and Description.</span>
                    <a class="valid-fields-trigger" href="#"><span class="help-label">Click here to check Valid Fields</span></a>
                    <input tabindex="10" class="validate[required]" type="file" id="csv_file" name="csv_file">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_skip_unknown_row">
                    <input type="checkbox" tabindex="10" id="skip_unknown" name="skip_unknown" value="1"><label
                            class="checkbox-label" for="skip_unknown" value="1">Skip Rows without UPC or Product Code
                        (instead of failing)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_skip_not_found_row">
                    <input type="checkbox" tabindex="10" id="skip_not_found" name="skip_not_found" value="1"><label
                            class="checkbox-label" for="skip_not_found" value="1">Skip Rows not found (instead of
                        adding)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
                    <div class="basic-form-line" id="_create_control_data_row">
                        <input type="checkbox" tabindex="10" id="create_control_data" name="create_control_data" value="1" checked><label class="checkbox-label" for="create_control_data">Create Missing Control Data</label>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
				<?php } ?>

				<?php if ($GLOBALS['gUserRow']['superuser_flag'] && $GLOBALS['gClientRow']['client_code'] == "COREWARE_SHOOTING_SPORTS") { ?>
                    <div class="basic-form-line" id="_manufacturer_map_row">
                        <input type="checkbox" tabindex="10" id="manufacturer_map" name="manufacturer_map" value="1"><label class="checkbox-label" for="manufacturer_map">These are Manufacturer's MAP prices and need to be tagged as such</label>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
				<?php } ?>

                <div class="basic-form-line">
                    <button tabindex="10" id="_submit_form">Import</button>
                    <div id="import_message"></div>
                </div>

                <div id="import_error"></div>

            </form>
        </div> <!-- form_div -->

        <table class="grid-table">
            <tr>
                <th>Description</th>
                <th>Imported On</th>
                <th>By</th>
                <th>Count</th>
                <th>Successful</th>
                <th>Undo</th>
				<?php if (canAccessPage("PRODUCTMAINT")) { ?>
                    <th></th>
				<?php } ?>
                <th></th>
            </tr>
			<?php
			$resultSet = executeQuery("select * from csv_imports where table_name = 'products' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$importCount = 0;
				$countSet = executeQuery("select count(*) from csv_import_details where csv_import_id = ?", $row['csv_import_id']);
				if ($countRow = getNextRow($countSet)) {
					$importCount = $countRow['count(*)'];
				}
				$minutesSince = (time() - strtotime($row['time_submitted'])) / 60;
				$canUndo = ($minutesSince < 120 || $GLOBALS['gDevelopmentServer']);
				?>
                <tr id="csv_import_id_<?= $row['csv_import_id'] ?>" class="import-row"
                    data-csv_import_id="<?= $row['csv_import_id'] ?>">
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= date("m/d/Y g:i a", strtotime($row['time_submitted'])) ?></td>
                    <td><?= getUserDisplayName($row['user_id']) ?></td>
                    <td class='align-right'><?= $importCount ?></td>
                    <td><?= ($row['successful'] ? "Yes" : "") ?></td>
                    <td><?= ($canUndo ? "<span class='far fa-undo remove-import'></span>" : "") ?></td>
					<?php if (canAccessPage("PRODUCTMAINT")) { ?>
                        <td class='align-center'><span class='far fa-check-square select-products'></span></td>
					<?php } ?>
                    <td class='align-center'><span class='fad fa-download csv-download'></span></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".csv-download", function () {
                document.location = "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=download_csv&csv_import_id=" + $(this).closest("tr").data("csv_import_id");
                return false;
            });
            $(document).on("click", ".select-products", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_products&csv_import_id=" + $(this).closest("tr").data("csv_import_id"));
            });
            $(document).on("click", ".remove-import", function () {
                const csvImportId = $(this).closest("tr").data("csv_import_id");
                $('#_confirm_undo_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Remove Import',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_import&csv_import_id=" + csvImportId, function (returnArray) {
                                if ("csv_import_id" in returnArray) {
                                    $("#csv_import_id_" + returnArray['csv_import_id']).remove();
                                }
                            });
                            $("#_confirm_undo_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_undo_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("tap click", "#_submit_form", function () {
                const $submitForm = $("#_submit_form");
                const $editForm = $("#_edit_form");
                const $postIframe = $("#_post_iframe");
                if ($submitForm.data("disabled") === "true") {
                    return false;
                }
                if ($editForm.validationEngine("validate")) {
                    $("#import_error").html("");
                    disableButtons($submitForm);
                    $("body").addClass("waiting-for-ajax");
                    $editForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_csv").attr("method", "POST").attr("target", "post_iframe").submit();
                    $postIframe.off("load");
                    $postIframe.on("load", function () {
                        $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                        const returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            enableButtons($("#_submit_form"));
                            return;
                        }
                        if ("import_error" in returnArray) {
                            $("#import_error").html(returnArray['import_error']);
                        }
                        if ("response" in returnArray) {
                            $("#_form_div").html(returnArray['response']);
                        }
                        enableButtons($submitForm);
                    });
                }
                return false;
            });
            $(document).on("tap click", ".valid-fields-trigger", function () {
                $("#_valid_fields_dialog").dialog({
                    modal: true,
                    resizable: true,
                    width: 1000,
                    title: 'Valid Fields',
                    buttons: {
                        Close: function (event) {
                            $("#_valid_fields_dialog").dialog('close');
                        }
                    }
                });
            });
            $("#_valid_fields_dialog .accordion").accordion({
                active: false,
                heightStyle: "content",
                collapsible: true
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            .csv-download {
                cursor: pointer;
            }
            #import_error {
                color: rgb(192, 0, 0);
            }

            .remove-import {
                cursor: pointer;
            }

            .select-products {
                cursor: pointer;
            }

            #_valid_fields_dialog .ui-accordion-content {
                max-height: 200px;
            }

            #_valid_fields_dialog > ul {
                columns: 3;
                padding-bottom: 1rem;
            }

            #_valid_fields_dialog .ui-accordion ul {
                columns: 2;
            }

            #_valid_fields_dialog ul li {
                padding-right: 20px;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these products being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->

        <div id="_valid_fields_dialog" title="Valid Fields" class="dialog-box">
            <ul>
                <li><?= implode("</li><li>", $this->iValidDefaultFields) ?></li>
            </ul>

            <div class="accordion">
				<?php if (!empty($this->iValidFacetFields)) { ?>
                    <h3>Valid Facet Fields</h3>
                    <!-- Has an extra wrapper div since columns CSS property doesn't work properly with accordion content's max height -->
                    <div>
                        <ul>
                            <li><?= implode("</li><li>", $this->iValidFacetFields) ?></li>
                        </ul>
                    </div>
				<?php } ?>

				<?php if (!empty($this->iValidProductTagFields)) { ?>
                    <h3>Valid Product Tag Fields</h3>
                    <div>
                        <ul>
                            <li><?= implode("</li><li>", $this->iValidProductTagFields) ?></li>
                        </ul>
                    </div>
				<?php } ?>

				<?php if (!empty($this->iValidCustomFields)) { ?>
                    <h3>Valid Custom Fields</h3>
                    <div>
                        <ul>
                            <li><?= implode("</li><li>", $this->iValidCustomFields) ?></li>
                        </ul>
                    </div>
				<?php } ?>

				<?php if (!empty($this->iValidLocationFields)) { ?>
                    <h3>Valid Location Fields</h3>
                    <div>
                        <ul>
                            <li><?= implode("</li><li>", $this->iValidLocationFields) ?></li>
                        </ul>
                    </div>
				<?php } ?>
            </div>
            <br>
            <p>To create a new product, <strong>description</strong> is required.<br>
                To update an existing product, one of <strong>product_id</strong>, <strong>product_code</strong>, or <strong>upc_code</strong> is required.</p>
        </div>
		<?php
	}
}

$pageObject = new ProductCsvImportPage();
$pageObject->displayPage();
