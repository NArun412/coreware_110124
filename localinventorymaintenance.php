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

$GLOBALS['gPageCode'] = "LOCALINVENTORYMAINT";
require_once "shared/startup.inc";

class LocalInventoryMaintenancePage extends Page {

	var $iValidDefaultFields = array("upc_code",  "location_code", "quantity", "cost");
	var $iLocationsArray = array();
	var $iDepartmentsInventories = array();

	var $iRequiredFields = array("upc_code", "quantity");
	var $iNumericFields = array("quantity", "cost");

	function setup() {
		sort($this->iValidDefaultFields);
		setUserPreference("MAINTENANCE_HIDE_IDS", "true", $GLOBALS['gPageRow']['page_code']);

		$filters = array();
		$filters['hide_inactive'] = array("form_label" => "Hide Inactive Products", "where" => "product_id not in (select product_id from products where inactive = 1)", "data_type" => "tinyint", "set_default" => true, "conjunction" => "and");
		$filters['in_stock_only'] = array("form_label" => "Hide Products with zero quantity", "where" => "quantity > 0", "data_type" => "tinyint", "set_default" => false, "conjunction" => "and");

		$resultSet = executeQuery("select * from locations where product_distributor_id is null and client_id = ?"
			. (empty($GLOBALS['gUserRow']['administrator_flag']) ? " and location_id in (select location_id from ffl_locations where federal_firearms_licensee_id in (select user_ffls.federal_firearms_licensee_id from user_ffls where user_id = " . $GLOBALS['gUserId'] . "))" : "")
			. " order by sort_order, description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$filters['location_id_' . $row['location_id']] = array("form_label" => $row['description'], "where" => "location_id = " . $row['location_id'], "data_type" => "tinyint");
			$this->iLocationsArray[$row['location_code']] = $row['location_id'];
		}

		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));

			$columns = array("product_id", "upc_code", "location_id", "quantity", "last_updated_time");
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn($columns);
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder($columns);
			$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn($_GET['url_page'] == "new" ?
				array("upc_code", "isbn_13", "manufacturer_sku", "product_code", "last_updated_time") : "last_updated_time");

			$additionalButtons = array("import_inventory" => array("label" => getLanguageText("Import CSV")));
			$this->iTemplateObject->getTableEditorObject()->addAdditionalListButtons($additionalButtons);
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons($additionalButtons);
		}

		// Statistics
		if ((empty($_GET['url_page']) || $_GET['url_page'] == "list") && empty($this->iPageData['pre_management_header'])) {
			ob_start();
			?>
			<section id="_stats_section" class="mb-3 mt-4">
				<?php

				$this->iDepartmentsInventories = array();
				$resultSet = executeQuery("select * from product_departments where client_id = ? and inactive = 0 and internal_use_only = 0 order by sort_order, description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$this->iDepartmentsInventories[$row['product_department_id']] = array("description" => $row['description'], "count" => 0, "locations" => array());
				}
				$resultSet = executeQuery("select location_id, description, quantity, product_id from locations left join product_inventories using (location_id) where product_distributor_id is null and client_id = ?"
					. " order by sort_order, description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					foreach ($this->iDepartmentsInventories as $departmentId => $departmentInventories) {
						if (ProductCatalog::productIsInDepartment($row['product_id'], $departmentId)) {
							$this->iDepartmentsInventories[$departmentId]['count'] += $row['quantity'];

							if (empty($this->iDepartmentsInventories[$departmentId]['locations'][$row['location_id']])) {
								$this->iDepartmentsInventories[$departmentId]['locations'][$row['location_id']] = array("description" => $row['description'], "count" => $row['quantity']);
							} else {
								$this->iDepartmentsInventories[$departmentId]['locations'][$row['location_id']]['count'] += $row['quantity'];
							}
						}
					}
				}

				foreach ($this->iDepartmentsInventories as $departmentId => $departmentInventories) {
					$tooltip = "";
					$locationIndex = 0;
					foreach ($departmentInventories['locations'] as $locationInventories) {
						$tooltip .= "<b>" . htmlText($locationInventories['description']) . "</b>: " . $locationInventories['count'] . "<br/>";
						$locationIndex++;
						if ($locationIndex >= 5) {
							$tooltip .= "Click card to view complete list.";
							break;
						}
					}

					?>
					<div class="card border d-inline-block me-2 mb-3" <?= count($departmentInventories['locations']) >= 5 ? "data-department_id='" . $departmentId . "'" : "" ?>
					     data-mdb-toggle="tooltip"
					     data-mdb-html="true"
					     data-mdb-placement="bottom"
					     title="<?= $tooltip ?>">
						<div class="card-body">
							<h5 class="card-title"><?= $departmentInventories['count'] ?></h5>
							<p class="card-text text-truncate"><?= $departmentInventories['description'] ?></p>
						</div>
					</div>
					<?php
				}
				?>
			</section>
			<?php
			$this->iPageData['pre_management_header'] = ob_get_clean();
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("upc_code", "readonly", "true");
		$this->iDataSource->addColumnControl("upc_code", "form_label", "UPC Code");
		$this->iDataSource->addColumnControl("upc_code", "data_type", "varchar");
		$this->iDataSource->addColumnControl("upc_code", "select_value", "select upc_code from product_data where product_id = product_inventories.product_id");

		$this->iDataSource->addColumnControl("isbn_13", "readonly", "true");
		$this->iDataSource->addColumnControl("isbn_13", "form_label", "ISBN 13");
		$this->iDataSource->addColumnControl("isbn_13", "data_type", "varchar");
		$this->iDataSource->addColumnControl("isbn_13", "select_value", "select isbn_13 from product_data where product_id = product_inventories.product_id");

		$this->iDataSource->addColumnControl("manufacturer_sku", "readonly", "true");
		$this->iDataSource->addColumnControl("manufacturer_sku", "form_label", "SKU");
		$this->iDataSource->addColumnControl("manufacturer_sku", "data_type", "varchar");
		$this->iDataSource->addColumnControl("manufacturer_sku", "select_value", "select manufacturer_sku from product_data where product_id = product_inventories.product_id");

		$this->iDataSource->addColumnControl("product_code", "readonly", "true");
		$this->iDataSource->addColumnControl("product_code", "form_label", "Product Code");
		$this->iDataSource->addColumnControl("product_code", "data_type", "varchar");
		$this->iDataSource->addColumnControl("product_code", "select_value", "select product_code from products where product_id = product_inventories.product_id");

		$this->iDataSource->addColumnControl("last_updated_time", "form_label", "Last Updated Time");
		$this->iDataSource->addColumnControl("last_updated_time", "select_value", "select max(log_time) from product_inventory_log where product_inventory_id = product_inventories.product_inventory_id");

		$this->iDataSource->addFilterWhere("location_id in (select location_id from locations where product_distributor_id is null) and product_id in (select product_id from products where client_id = " . $GLOBALS['gClientId'] . ")");
		if (!$GLOBALS['gUserRow']['administrator_flag']) {
			$this->iDataSource->addFilterWhere("location_id in (select location_id from ffl_locations where federal_firearms_licensee_id in (select user_ffls.federal_firearms_licensee_id from user_ffls where user_id = " . $GLOBALS['gUserId'] . "))");
		}

		$this->iDataSource->addColumnControl("quantity", "readonly", "false");
		$this->iDataSource->addColumnControl("quantity", "form_label", "Current Inventory Quantity");

		$this->iDataSource->addColumnControl("product_id", "not_editable", "true");
		$this->iDataSource->addColumnControl("location_id", "not_editable", "true");
		$this->iDataSource->addColumnControl("location_id", "get_choices", "locationChoices");

		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "product_data",
			"referenced_column_name" => "product_id", "foreign_key" => "product_id", "description" => "upc_code"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "products",
			"referenced_column_name" => "product_id", "foreign_key" => "product_id", "description" => "product_code"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "products",
			"referenced_column_name" => "product_id", "foreign_key" => "product_id", "description" => "description"));
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "import_csv":
				if (!array_key_exists("csv_file", $_FILES)) {
					$returnArray['error_message'] = "No File uploaded";
					ajaxResponse($returnArray);
					break;
				}

				$fieldValue = file_get_contents($_FILES['csv_file']['tmp_name']);
				$hashCode = md5($fieldValue);
				$csvImportId = getFieldFromId("csv_import_id", "csv_imports", "hash_code", $hashCode);
				if (!empty($csvImportId)) {
					$returnArray['error_message'] = "This file has already been imported.";
					ajaxResponse($returnArray);
					break;
				}
				$openFile = fopen($_FILES['csv_file']['tmp_name'], "r");

				$allValidFields = $this->iValidDefaultFields;
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
								$invalidFields .= (empty($invalidFields) ? "" : ", ") . $fieldName;
							}
						}
						if (!empty($invalidFields)) {
							$errorMessage .= "<p>Invalid fields in CSV: " . $invalidFields . "</p>";
						}
					} else {
						$fieldData = array();
						foreach ($csvData as $index => $thisData) {
							$thisFieldName = $fieldNames[$index];
							if (in_array($thisFieldName, $numericFields)) {
								$thisData = str_replace(",", "", $thisData);
							}
							$fieldData[$thisFieldName] = trim($thisData);
						}
						$importRecords[] = $fieldData;
					}
					$count++;
				}
				fclose($openFile);
				$locations = $this->iLocationsArray;
				if (count($locations) > 1) {
					$this->iRequiredFields[] = "location_code";
				}
				$productsFound = 0;
				$processCount = 0;
				foreach ($importRecords as $index => $thisRecord) {
					$processCount++;
					$missingFields = "";
					$upcCode = ProductCatalog::makeValidUPC(trim($thisRecord['upc_code'], " \t'"));
					if (empty($upcCode)) {
						if (empty($_POST['skip_unknown'])) {
							$errorMessage .= "<p>Line " . ($index + 2) . ": UPC is required</p>";
						}
						continue;
					}
					$productId = getFieldFromId("product_id", "product_data", "upc_code", $upcCode);
					if (!empty($productId)) {
						$productsFound++;
					}
					if (!empty($_POST['skip_not_found']) && empty($productId)) {
						continue;
					}
					if (empty($productId)) {
						$errorMessage .= "<p>Line " . ($index + 2) . ": Product with UPC " . $upcCode . " not found.</p>";
					}

					foreach ($requiredFields as $thisField) {

						if (!isset($thisRecord[$thisField])) {
							addDebugLog("this record: " . $thisRecord[$thisField]);
							$missingFields .= (empty($missingFields) ? "" : ", ") . $thisField;
						}
					}
					if (!empty($missingFields)) {
						$errorMessage .= "<p>Line " . ($index + 2) . " has missing fields: " . $missingFields . "</p>";
					}

					if (!empty($upcCode)) {
						$checkProductId = getFieldFromId("product_id", "product_data", "upc_code", $upcCode);
						if (!empty($checkProductId) && $checkProductId != $productId) {
							$errorMessage .= "<p>Line " . ($index + 2) . ": Product ID " . $productId . ", Duplicate Product: " . $checkProductId . "</p>";
						}
					}

					foreach ($numericFields as $fieldName) {
						if (!empty($thisRecord[$fieldName]) && !is_float($thisRecord[$fieldName]) && !is_numeric($thisRecord[$fieldName])) {
							$errorMessage .= "<p>Line " . ($index + 2) . ": " . $fieldName . " needs to be numeric: " . $thisRecord[$fieldName] . "</p>";
						}
					}
					if (empty($thisRecord['location_code'])) {
						$thisRecord['location_code'] = $_POST['location_code'];
					}
					if (!empty($thisRecord['location_code'])) {
						$thisRecord['location_code'] = makeCode($thisRecord['location_code']);
						if (!array_key_exists($thisRecord['location_code'], $locations)) {
							$errorMessage .= "<p>Line " . ($index + 2) . " location code is invalid: " . $thisRecord['location_code'] . "</p>";
						}
					}
				}

				if (!empty($errorMessage)) {
					$returnArray['import_error'] = $errorMessage;
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id,content) values (?,?,'products',?,now(),?,?)",
					$GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId'], file_get_contents($_FILES['csv_file']['tmp_name']));
				$this->checkSqlError($resultSet, $returnArray);
				$csvImportId = $resultSet['insert_id'];

				ProductCatalog::getInventoryAdjustmentTypes();

				$productIdArray = array();
				$lineNumber = 0;
				$notFound = 0;
				$updateCount = 0;
				foreach ($importRecords as $thisRecord) {
					$lineNumber++;
					$upcCode = ProductCatalog::makeValidUPC($thisRecord['upc_code']);

					if (empty(ltrim($upcCode, "0"))) {
						$upcCode = "";
					}
					$thisRecord['upc_code'] = $upcCode;
					if (empty($thisRecord['upc_code']) && !empty($_POST['skip_unknown'])) {
						continue;
					}
					$productId = getFieldFromId("product_id", "product_data", "upc_code", $upcCode);
					if (empty($productId)) {
						if (!empty($_POST['skip_not_found'])) {
							$notFound++;
							continue;
						} else {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = "Product with UPC " . $upcCode . " not found.";
							$returnArray['console'] = jsonEncode($thisRecord);
							ajaxResponse($returnArray);
							break;
						}
					}

					$updateCount++;
					$productIdArray[] = $productId;
					if (empty($thisRecord['location_code'])) {
						$thisRecord['location_code'] = $_POST['location_code'];
					}

					$locationId = (count($locations) == 1 ? array_values($locations)[0] : $locations[$thisRecord['location_code']]);

					if (strlen($thisRecord['quantity']) > 0) {
						$productInventoryRow = getRowFromId("product_inventories", "product_id", $productId, "location_id = ?", $locationId);
						$productInventoryId = $productInventoryRow['product_inventory_id'];
						if (empty($productInventoryId)) {
							$insertSet = executeQuery("insert into product_inventories (product_id,location_id,quantity) values (?,?,?)",
								$productId, $locationId, $thisRecord['quantity']);
							$this->checkSqlError($insertSet, $returnArray, $insertSet['sql_error'] . " at line: " . $lineNumber . ", " . $productId);
							$productInventoryId = $insertSet['insert_id'];
							$affectedRows = 1;
						} else {
							if ($productInventoryRow['quantity'] != $thisRecord['quantity']) {
								$resultSet = executeQuery("update product_inventories set quantity = ? where product_inventory_id = ?", $thisRecord['quantity'], $productInventoryId);
								$affectedRows = $resultSet['affected_rows'];
							} else {
								$affectedRows = 0;
							}
						}
						if ($affectedRows > 0) {
							if (empty($thisRecord['total_cost']) && !empty($thisRecord['cost'])) {
								if ($thisRecord['quantity'] > 0) {
									$thisRecord['total_cost'] = $thisRecord['cost'] * $thisRecord['quantity'];
								} else {
									$thisRecord['total_cost'] = $thisRecord['cost'];
								}
							}
							if (empty($thisRecord['total_cost']) || $thisRecord['quantity'] < 0) {
								$thisRecord['total_cost'] = "";
							}
							$baseCost = getFieldFromId("base_cost", "products", "product_id", $productId);
							if ($thisRecord['total_cost'] == $baseCost && $thisRecord['quantity'] > 1) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $returnArray['import_error'] = "Total_cost for UPC " . $upcCode . " is incorrect. Total_cost must be quantity (" . $thisRecord['quantity'] . ")  * cost (" . $thisRecord['cost'] . ").";
								$returnArray['console'] = jsonEncode($thisRecord);
								ajaxResponse($returnArray);
								break;
							}
							if (empty($_POST['ignore_cost_warning'])) {
								$newCost = $thisRecord['cost'];
								$costDeviation = abs($baseCost - $newCost);
								if ($costDeviation > ($baseCost * (20 / 100)) && $costDeviation > 20) {
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									$returnArray['error_message'] = $returnArray['import_error'] = sprintf("Cost for UPC %s (%s) is more than 20%% different " .
										" from existing base cost (%s).  To import anyway, check 'Ignore warnings for large changes in cost'.", $upcCode, $newCost, $baseCost);
									$returnArray['console'] = jsonEncode($thisRecord);
									ajaxResponse($returnArray);
									break;
								}
							}
							$insertSet = executeQuery("insert into product_inventory_log (product_inventory_id,inventory_adjustment_type_id,user_id,log_time,quantity,total_cost,notes) values " .
								"(?,?,?,now(),?,?,?)", $productInventoryId, $GLOBALS['gInventoryAdjustmentTypeId'], $GLOBALS['gUserId'], $thisRecord['quantity'], $thisRecord['total_cost'], $thisRecord['inventory_notes']);
							$this->checkSqlError($insertSet, $returnArray, $insertSet['sql_error'] . " at line: " . $lineNumber . ", " . $productId);
							ProductCatalog::updateLocationBaseCost($productId, $locationId);
							ProductCatalog::calculateProductCost($productId);
						}
					}

					$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $productId);
					$this->checkSqlError($insertSet, $returnArray);
				}
				if (!empty($productIdArray)) {
					ProductCatalog::reindexProducts($productIdArray);
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$returnArray['response'] .= $updateCount . " product(s) updated.";
				if ($notFound > 0) {
					$returnArray['response'] .= "<br>" . $notFound . " product(s) not found.";
				}
				ajaxResponse($returnArray);
				break;
			case "get_locations_inventories":
				if (empty($_GET['department_id']) || !array_key_exists($_GET['department_id'], $this->iDepartmentsInventories)) {
					$returnArray['error_message'] = "Department is required.";
					ajaxResponse($returnArray);
					break;
				}
				$returnArray['locations'] = array();
				foreach ($this->iDepartmentsInventories[$_GET['department_id']]['locations'] as $locationId => $locationInventories) {
					$returnArray['locations'][] = array_merge(array("location_id" => $locationId), $locationInventories);
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function checkSqlError($resultSet, &$returnArray, $errorMessage = "") {
		if (!empty($resultSet['sql_error'])) {
			$returnArray['error_message'] = $returnArray['import_error'] = $errorMessage ?: getSystemMessage("basic", $resultSet['sql_error']);
			$returnArray['console'] = $resultSet['sql_error'];
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			ajaxResponse($returnArray);
		}
	}

	function locationChoices($showInactive = false) {
		$locationChoices = array();
		$resultSet = executeQuery("select * from locations where product_distributor_id is null and client_id = ?"
			. (empty($GLOBALS['gUserRow']['administrator_flag']) ? " and location_id in (select location_id from ffl_locations where federal_firearms_licensee_id in (select user_ffls.federal_firearms_licensee_id from user_ffls where user_id = " . $GLOBALS['gUserId'] . "))" : "")
			. " order by sort_order, description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$locationChoices[$row['location_id']] = array("key_value" => $row['location_id'], "description" => $row['description'], "inactive" => false);
		}
		freeResult($resultSet);
		return $locationChoices;
	}

	function filterTextProcessing($filterText) {
		if (is_numeric($filterText) && strlen($filterText) >= 8) {
			$whereStatement = "(product_id in (select product_id from products where client_id = " . $GLOBALS['gClientId'] . " and product_code = " . makeParameter($filterText) .
				") or product_id = '" . $filterText . "' or product_id in (select product_id from product_data where upc_code = '" . ProductCatalog::makeValidUPC($filterText) . "'" .
				") or product_id in (select product_id from product_data where isbn = '" . ProductCatalog::makeValidISBN($filterText) . "'" .
				") or product_id in (select product_id from product_data where isbn_13 = '" . ProductCatalog::makeValidISBN13($filterText) . "'))";
			$this->iDataSource->addFilterWhere($whereStatement);
		} else if (!empty($filterText)) {
			$productId = getFieldFromId("product_id","products","description",$filterText);
			if (empty($productId)) {
				$searchWordInfo = ProductCatalog::getSearchWords($filterText);
				$searchWords = $searchWordInfo['search_words'];
				$whereStatement = "";
				foreach ($searchWords as $thisWord) {
					$productSearchWordId = getFieldFromId("product_search_word_id","product_search_words","search_term",$thisWord);
					if (empty($productSearchWordId)) {
						continue;
					}
					$whereStatement .= (empty($whereStatement) ? "" : " and ") .
						"product_id in (select product_id from product_search_word_values where product_search_word_id = " . $productSearchWordId . ")";
				}
				$whereStatement = "(product_id in (select product_id from products where product_code = " . makeParameter($filterText) . " or description like " . makeParameter($filterText . "%") . ")" . (empty($whereStatement) ? ")" : " or (" . $whereStatement . "))");
				$whereStatement = "(" . (is_numeric($filterText) ? "product_id = " . makeParameter($filterText) . " or " : "") . "(" . $whereStatement . "))";
				$this->iDataSource->addFilterWhere($whereStatement);
			} else {
				$this->iDataSource->addFilterWhere("product_id in (select product_id from products where description like " . makeParameter($filterText . "%") . ")");
			}
		}
	}

	function headerIncludes() {
		?>
		<link href="/css/mdb.min.css" rel="stylesheet" />
		<link href="/css/modules/select.min.css" rel="stylesheet" />
		<script type="text/javascript" src="/js/mdb.min.js" defer></script>
		<script type="text/javascript" src="/js/modules/toast.min.js" defer></script>
		<?php
	}

	function onLoadJavascript() {
		?>
		<script>
            const importModal = new mdb.Modal(document.getElementById('import_modal'));
            const inventoryLocationsModal = new mdb.Modal(document.getElementById('inventory_locations_modal'));

            $(document).on("tap click", "#_import_inventory_button", function() {
                importModal.show();
                return false;
            });

            $(document).on("tap click", "#_stats_section [data-department_id]", function() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_locations_inventories&department_id=" + $(this).data("department_id"), function(returnArray) {
                    if (returnArray.locations) {
                        const locationsTable = $("#inventory_locations_modal tbody");
                        locationsTable.empty();

                        Object.values(returnArray.locations).forEach(location => {
                            locationsTable.append(`<tr>
                                <td>${location.description}</td>
                                <td>${location.count}</td>
                            </tr>`);
                        });
                        inventoryLocationsModal.show();
                    }
                });
                return false;
            })

            $(document).on("tap click", "#_submit_form", function() {
                const submitForm = $("#_submit_form");
                const importForm = $("#_import_form")[0];

                if (submitForm.data("disabled") === "true") {
                    return false;
                }
                if (!importForm.checkValidity()) {
                    importForm.classList.add('was-validated');
                    return false;
                }

                const formData = new FormData(importForm);
                const errorToast = mdb.Toast.getInstance(document.getElementById('import_error'));
                const successToast = mdb.Toast.getInstance(document.getElementById('import_success'));

                disableButtons(submitForm);
                $("body").addClass("waiting-for-ajax");

                $.ajax({
                    type: "POST",
                    url: "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_csv",
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'JSON',
                    success: function (data) {
                        $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                        const returnArray = data;
                        if (returnArray === false) {
                            enableButtons($("#_submit_form"));
                            return;
                        }

                        if (returnArray.hasOwnProperty('import_error') || returnArray.hasOwnProperty('error_message') ) {
                            let errorMessage = "";
                            if (!empty(returnArray['import_error'])) {
                                errorMessage = returnArray['import_error'];
                            }
                            if (!empty(returnArray['error_message'])) {
                                errorMessage = returnArray['error_message'];
                            }
                            $("#import_error .toast-body").html(errorMessage);
                            errorToast.show();
                        }
                        if (returnArray.hasOwnProperty('response')) {
                            returnArray['response'] += '<br>Page will now refresh to reflect updated quantities.';
                            $("#import_success .toast-body").html(returnArray['response']);

                            importModal.hide();
                            successToast.show();

                            setTimeout(function() {
                                location.reload();
                            }, 6000);
                        }
                        enableButtons(submitForm);
                    },
                    error: function (data) {
                        $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                        console.error('An error occurred.', data);
                    },
                });
                return false;
            });
		</script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['upc_code'] = array("data_value" => getFieldFromId("upc_code", "product_data", "product_id", $returnArray['product_id']['data_value']));
		$returnArray['isbn_13'] = array("data_value" => getFieldFromId("isbn_13", "product_data", "product_id", $returnArray['product_id']['data_value']));
		$returnArray['manufacturer_sku'] = array("data_value" => getFieldFromId("manufacturer_sku", "product_data", "product_id", $returnArray['product_id']['data_value']));
		$returnArray['product_code'] = array("data_value" => getFieldFromId("product_code", "products", "product_id", $returnArray['product_id']['data_value']));

		if (!empty($returnArray['primary_id']['data_value'])) {
			$resultSet = executeQuery("select * from product_inventory_log where product_inventory_id = ? order by log_time desc, product_inventory_log_id", $returnArray['primary_id']['data_value']);
			ob_start();
			?>
			<h2>Inventory Log</h2>
			<p class="text-danger"><strong>Rows with an Order ID do not change cost, so unit cost will be blank.</strong></p>
			<table class="table table-striped table-hover table-bordered align-middle">
				<tr>
					<th>User</th>
					<th>Order ID</th>
					<th>Date</th>
					<th>Quantity</th>
					<th>Unit Cost</th>
				</tr>
				<?php
				while ($row = getNextRow($resultSet)) {
					?>
					<tr>
						<td><?= (empty($row['user_id']) ? "" : getUserDisplayName($row['user_id'])) ?></td>
						<td><?= $row['order_id'] ?></td>
						<td><?= date("m/d/Y g:ia", strtotime($row['log_time'])) ?></td>
						<td class="align-right"><?= $row['quantity'] ?></td>
						<td class="align-right"><?= ((empty($row['quantity']) || (empty($row['total_cost']))) ? "" : number_format(round($row['total_cost'] / $row['quantity'], 2), 2)) ?></td>
					</tr>
					<?php
				}
				?>
			</table>
			<?php
			$returnArray['product_inventory_log'] = array("data_value" => ob_get_clean());
		}
	}

	function beforeSaveChanges($nameValues) {
		$existingRecord = getRowFromId("product_inventories", "product_inventory_id", $_POST['primary_id']);
		if (!empty($existingRecord)) {
			$unitCost = "";
			$resultSet = executeQuery("select total_cost, quantity from product_inventory_log where product_inventory_id = ? order by log_time desc limit 1", $_POST['primary_id']);
			if ($row = getNextRow($resultSet)) {
				$unitCost = empty($row['quantity']) ? "" : number_format(round($row['total_cost'] / $row['quantity'], 2), 2);
			}
			if (empty($unitCost)) {
				$unitCost = getFieldFromId("base_cost", "products", "product_id", $existingRecord['product_id']);
			}

			$quantityChanged = $nameValues['quantity'] != $existingRecord['quantity'] && $nameValues['quantity'] > 0;
			if ($quantityChanged || $nameValues['unit_cost'] != $unitCost) {
				$this->updateProductInventoryQuantity($nameValues);
			}
		}
		return true;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if ($actionPerformed == "insert") {
			$this->updateProductInventoryQuantity($nameValues);
		}
		return true;
	}

	function updateProductInventoryQuantity($nameValues) {
		$totalCost = "";
		if (strlen($nameValues['unit_cost']) > 0 && $nameValues['quantity'] > 0) {
			$totalCost = $nameValues['unit_cost'] * $nameValues['quantity'];
		}
		$inventoryAdjustmentTypeId = getFieldFromId("inventory_adjustment_type_id", "inventory_adjustment_types", "inventory_adjustment_type_code", "INVENTORY");
		executeQuery("insert into product_inventory_log (product_inventory_id, inventory_adjustment_type_id, user_id, log_time, quantity, total_cost, notes) values " .
			"(?, ?, ?, now(), ?, ?, ?)", $nameValues['primary_id'], $inventoryAdjustmentTypeId, $GLOBALS['gUserId'], $nameValues['quantity'], $totalCost, "Manual update in local inventory maintenance");

		$nameValues['quantity'] = max($nameValues['quantity'], 0);
		executeQuery("update product_inventories set quantity = ? where product_inventory_id = ?", $nameValues['quantity'], $nameValues['primary_id']);

		removeCachedData("base_cost", $nameValues['product_id']);
		removeCachedData("*", $nameValues['product_id']);
		ProductCatalog::updateLocationBaseCost($nameValues['product_id'], $nameValues['location_id']);
		ProductCatalog::calculateProductCost($nameValues['product_id']);

		if ($nameValues['quantity'] > 0) {
			executeQuery("delete from product_category_links where product_id = ? and product_category_id in (select product_category_id from product_categories where product_category_code = 'DISCONTINUED')", $nameValues['product_id']);
		}
	}

	function hiddenElements() {
		?>
		<div id="import_modal" class="modal fade" aria-label="Import modal" aria-hidden="true">
			<div class="modal-dialog modal-lg modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Inventory Import</h5>
						<button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<p class="fs-6 fw-light">
							<?= getFragment("inventory_import_heading") ?: "Use this page to import your local inventory via CSV file." ?>
						</p>

						<form id="_import_form" class="row g-3 needs-validation" enctype='multipart/form-data' novalidate>
							<?php
							$fflChoices = array();
							$resultSet = executeQuery("select * from federal_firearms_licensees join contacts using (contact_id) where federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id = ?)", $GLOBALS['gUserId']);
							while ($row = getNextRow($resultSet)) {
								$fflName = (empty($row['business_name']) ? $row['licensee_name'] : $row['business_name']);
								$fflChoices[$row['federal_firearms_licensee_id']] = array("key_value" => $row['federal_firearms_licensee_id'], "description" => $fflName, "ffl_number" => $row['license_number'], "ffl_code" => makeCode($row['license_lookup']), "inactive" => ($row['inactive'] == 1));
							}
							if (!empty($fflChoices)) {
								$singleLocation = count($fflChoices) <= 1;
								?>
								<div class="form-line col-md-12">
									<select id="location_code" class="select" name="location_code" data-mdb-visible-options="5" aria-label="Location">
										<option value="0" <?= $singleLocation ? "" : "selected" ?> disabled>Select Store</option>
										<?php foreach ($fflChoices as $fflRow) { ?>
											<option id="option_<?= $fflRow['key_value'] ?>" value="<?= $fflRow['ffl_code'] ?>"
												<?= $singleLocation ? "disabled selected" : "" ?>><?= $fflRow['description'] ?></option>
										<?php } ?>
									</select>
									<?php if (count($fflChoices) > 1) { ?>
										<label class="form-label select-label">Store Selection</label>
									<?php } ?>
								</div>
							<?php } ?>

							<div class="form-line col-md-12">
								<div id="_description_wrapper" class="form-outline">
									<input type="text" id="description" name="description" class="form-control" aria-describedby="description_details" required />
									<label class="form-label" for="description">Description</label>
									<div class="invalid-feedback">This field is required.</div>
								</div>
								<div id="description_details" class="form-text">
									This description will show up in the Inventory Log for reference.
								</div>
							</div>

							<div id="_csv_file_row" class="form-line col-md-12">
								<label class="form-label" for="csv_file" >Select CSV File</label>
								<input id="csv_file" name="csv_file" type="file" class="form-control" accept=".csv" aria-describedby="_valid_fields" required />
								<div class="invalid-feedback">No file chosen.</div>
							</div>

							<div id="_valid_fields" class="form-text">
								Valid Fields: <?= (count($this->iLocationsArray) > 1 ? "upc_code, location_code, quantity, and cost." : "upc_code, quantity and cost.") ?>
							</div>

							<div class="form-line hidden" id="_skip_unknown_row">
								<input type="checkbox" tabindex="10" id="skip_unknown" name="skip_unknown" value="1" checked>
								<label class="checkbox-label" for="skip_unknown">Skip Rows without UPC (instead of failing)</label>
								<div class="clear-div"></div>
							</div>

							<div class="form-line hidden" id="_skip_not_found_row">
								<input type="checkbox" tabindex="10" id="skip_not_found" name="skip_not_found" value="1" checked>
								<label class="checkbox-label" for="skip_not_found">Skip Rows not found (instead of failing)</label>
								<div class="clear-div"></div>
							</div>

							<div class="form-line hidden" id="_ignore_cost_warning">
								<input type="checkbox" tabindex="10" id="ignore_cost_warning" name="ignore_cost_warning" value="1" checked>
								<label class="checkbox-label" for="ignore_cost_warning">Ignore warnings for large changes in cost</label>
								<div class="clear-div"></div>
							</div>

							<div id="import_message"></div>
						</form>
					</div>

					<div class="modal-footer">
						<button type="button" class="btn btn-link" data-mdb-dismiss="modal">Close</button>
						<button id="_submit_form" type="button" class="btn btn-primary">Import</button>
					</div>
				</div>
			</div>
		</div>

		<div id="inventory_locations_modal" class="modal fade" aria-label="Inventory locations" aria-hidden="true">
			<div class="modal-dialog modal-dialog-centered">
				<div class="modal-content">
					<div class="modal-header">
						<h5 class="modal-title">Inventory locations quantity</h5>
						<button type="button" class="btn-close" data-mdb-dismiss="modal" aria-label="Close"></button>
					</div>
					<div class="modal-body">
						<table class="table table-bordered">
							<tbody></tbody>
						</table>
					</div>
				</div>
			</div>
		</div>

		<div id="import_success"
		     class="toast fade mx-auto"
		     role="alert"
		     aria-live="assertive"
		     aria-atomic="true"
		     data-mdb-delay="6000"
		     data-mdb-position="top-center"
		     data-mdb-append-to-body="true"
		     data-mdb-stacking="true"
		     data-mdb-width="350px"
		     data-mdb-color="success">
			<div class="toast-header text-white">
				<strong class="me-auto">Success</strong>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="toast" aria-label="Close"></button>
			</div>
			<div class="toast-body text-white"></div>
		</div>

		<div id="import_error"
		     class="toast fade mx-auto"
		     role="alert"
		     aria-live="assertive"
		     aria-atomic="true"
		     data-mdb-autohide="false"
		     data-mdb-position="top-center"
		     data-mdb-append-to-body="true"
		     data-mdb-stacking="true"
		     data-mdb-width="350px"
		     data-mdb-color="danger">
			<div class="toast-header text-white">
				<strong class="me-auto">Error</strong>
				<button type="button" class="btn-close btn-close-white" data-mdb-dismiss="toast" aria-label="Close"></button>
			</div>
			<div class="toast-body text-white"></div>
		</div>
		<?php
	}

}

$pageObject = new LocalInventoryMaintenancePage("product_inventories");
$pageObject->displayPage();
