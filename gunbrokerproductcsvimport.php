<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "GUNBROKERPRODUCTCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;

class GunbrokerCsvImportPage extends Page {

    // todo: way to import existing listings from GB
	var $iValidFields = array("product_code", "upc_code", "gunbroker_listing_template_code", "product_category_code", "category_identifier", "scheduled_starting_date", "fixed_price", "quantity", "starting_bid", "buy_now_price");
    var $iRequiredFields = array("gunbroker_listing_template_code", "category_identifier", "quantity");

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
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "gunbroker_products", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to gunbroker products";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$deleteSet = executeQuery("delete from gunbroker_products where gunbroker_product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to gunbroker products";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to gunbroker products";
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
			case "select_gunbroker_products":
				$pageId = $GLOBALS['gAllPageCodes']["GUNBROKERPRODUCTMAINT"];
				$actionSet = executeQuery("delete from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $pageId);
				executeQuery("insert into selected_rows (user_id,page_id,primary_identifier) select " . $GLOBALS['gUserId'] . "," . $pageId .
					",primary_identifier from csv_import_details where csv_import_id = ?", $_GET['csv_import_id']);
				$returnArray['info_message'] = "Gunbroker Products selected in Gunbroker Product Maintenance program";
				ajaxResponse($returnArray);
				break;
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
				$missingFields = "";
				$openFile = fopen($_FILES['csv_file']['tmp_name'], "r");

				$allValidFields = $this->iValidFields;
				$requiredFields = $this->iRequiredFields;
				$numericFields = array("fixed_price", "category_identifier", "quantity", "starting_bid", "buy_now_price");

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
						foreach ($fieldNames as $fieldName) {
							if (!in_array($fieldName, $allValidFields)) {
								$invalidFields .= (empty($invalidFields) ? "" : ", ") . $fieldName;
							}
						}
						if (!empty($invalidFields)) {
							$errorMessage .= "<p>Invalid fields in CSV: " . $invalidFields . "</p>";
							$errorMessage .= "<p>Valid fields are: " . implode(", ", $allValidFields) . "</p>";
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

				$gunBrokerCategories = getCachedData("gunbroker_category_choices", "all", true);
				if (empty($gunBrokerCategories)) {
					try {
						$gunBroker = new GunBroker();
						$rawCategories = $gunBroker->getCategories();
						if ($rawCategories === false) {
							$gunBrokerCategories = array();
						} else {
							$gunBrokerCategories = array();
							foreach ($rawCategories as $thisData) {
								$gunBrokerCategories[] = array("key_value" => $thisData['category_id'], "raw_description" => $thisData['description'], "description" => $thisData['description'] . " (ID " . $thisData['category_id'] . ")");
							}
							setCachedData("gunbroker_category_choices", "all", $gunBrokerCategories, 24, true);
						}
					} catch (Exception $exception) {
						$gunBrokerCategories = array();
					}
				}
				$gunBrokerCategoryIds = array();
				foreach ($gunBrokerCategories as $thisCategory) {
					$gunBrokerCategoryIds[$thisCategory['key_value']] = $thisCategory['key_value'];
				}

				foreach ($importRecords as $index => $thisRecord) {
					$productId = getFieldFromId("product_id", "product_data", "upc_code", $thisRecord['upc_code']);
					if (empty($productId) && !empty($thisRecord['upc_code'])) {
						$errorMessage .= "<p>Invalid Product: " . $thisRecord['upc_code'] . "</p>";
						continue;
					}
					if (empty($productId)) {
						$productId = getFieldFromId("product_id", "products", "product_code", $thisRecord['product_code']);
						if (empty($productId) && !empty($thisRecord['product_code'])) {
							$errorMessage .= "<p>Invalid Product: " . $thisRecord['product_code'] . "</p>";
							continue;
						}
					}
					if (empty($productId)) {
						$errorMessage .= "<p>No product identified on line " . ($index + 1) . "</p>";
						continue;
                    }
					$importRecords[$index]['product_id'] = $productId;
					foreach ($requiredFields as $thisField) {
						if (empty($thisRecord[$thisField])) {
							$missingFields .= (empty($missingFields) ? "" : ", ") . $thisField;
						}
					}
					$gunbrokerListingTemplateId = getFieldFromId("gunbroker_listing_template_id", "gunbroker_listing_templates", "gunbroker_listing_template_code", $thisRecord['gunbroker_listing_template_code']);
					if (empty($gunbrokerListingTemplateId)) {
						$errorMessage .= "<p>Invalid Listing Template ID: " . $thisRecord['gunbroker_listing_template_code'] . "</p>";
					}
					$importRecords[$index]['gunbroker_listing_template_id'] = $gunbrokerListingTemplateId;

					if (empty($thisRecord['category_identifier']) && !empty($thisRecord['product_category_code'])) {
						$thisRecord['category_identifier'] = getFieldFromId("category_identifier", "gunbroker_product_categories", "product_category_id", getFieldFromId("product_category_id", "product_categories", "product_category_code", $thisRecord['product_category_code']));
						if (empty($thisRecord['category_identifier'])) {
							$categoryDescription = getFieldFromId("description", "product_categories", "product_category_code", $thisRecord['product_category_code']);
							if (!empty($categoryDescription)) {
								foreach ($gunBrokerCategories as $thisCategory) {
									if ($thisCategory['raw_description'] == $categoryDescription) {
										$thisRecord['category_identifier'] = $thisCategory['key_value'];
									}
								}
							}
						}
					}
					if (empty($thisRecord['category_identifier'])) {
						$errorMessage .= "<p>No category set for product on line " . ($index + 1) . "</p>";
					} else if (!array_key_exists($thisRecord['category_identifier'], $gunBrokerCategoryIds)) {
						$errorMessage .= "<p>Invalid category ID for product on line " . ($index + 1) . "</p>";
					} else {
						$importRecords[$index]['category_identifier'] = $thisRecord['category_identifier'];
					}
				}
				if (!empty($errorMessage)) {
					$returnArray['import_error'] = $errorMessage;
					ajaxResponse($returnArray);
					break;
				}

				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id,content) values (?,?,'gunbroker_products',?,now(),?,?)",
					$GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId'], file_get_contents($_FILES['csv_file']['tmp_name']));
				if (!empty($resultSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$csvImportId = $resultSet['insert_id'];

				$insertCount = 0;
				$updateCount = 0;
				$dataTable = new DataTable("gunbroker_products");
                $productCatalog = new ProductCatalog();

				foreach ($importRecords as $index => $thisRecord) {
					$productRow = getRowFromId("products", "product_id", $thisRecord['product_id']);
					$gunbrokerListingTemplateRow = getRowFromId("gunbroker_listing_templates", "gunbroker_listing_template_id", $thisRecord['gunbroker_listing_template_id']);
                    if($gunbrokerListingTemplateRow['listing_type'] != "auction") {
                        $salePriceInfo = $productCatalog->getProductSalePrice($thisRecord['product_id']);
                        $thisRecord['fixed_price'] = $thisRecord['fixed_price'] ?: $salePriceInfo['sale_price'];
                    }
					$gunbrokerProductId = getFieldFromId("gunbroker_product_id", "gunbroker_products", "product_id", $thisRecord['product_id']);
					$gunbrokerProductRow = getRowFromId("gunbroker_products", "gunbroker_product_id", $gunbrokerProductId);
					if (empty($gunbrokerProductRow)) {
						$gunbrokerProductRow = array();
					}
					$gunbrokerProductRow['product_id'] = $productRow['product_id'];
					$gunbrokerProductRow['description'] = substr($productRow['description'], 0, 75);
					$gunbrokerProductRow['detailed_description'] = $productRow['detailed_description'];
					$gunbrokerProductRow['header_content'] = $gunbrokerListingTemplateRow['header_content'];
					$gunbrokerProductRow['footer_content'] = $gunbrokerListingTemplateRow['footer_content'];
					$gunbrokerProductRow['auto_relist'] = $gunbrokerListingTemplateRow['auto_relist'];
					$gunbrokerProductRow['auto_relist_fixed_count'] = $gunbrokerListingTemplateRow['auto_relist_fixed_count'];
					$gunbrokerProductRow['can_offer'] = $gunbrokerListingTemplateRow['can_offer'];
					$gunbrokerProductRow['category_identifier'] = $thisRecord['category_identifier'];
					$gunbrokerProductRow['item_condition'] = $gunbrokerListingTemplateRow['item_condition'];
					$gunbrokerProductRow['fixed_price'] = ($gunbrokerListingTemplateRow['listing_type'] == "auction" ? "" : $thisRecord['fixed_price']);
					$gunbrokerProductRow['ground_shipping_cost'] = $gunbrokerListingTemplateRow['ground_shipping_cost'];
					$gunbrokerProductRow['inspection_period'] = $gunbrokerListingTemplateRow['inspection_period'];
					$gunbrokerProductRow['listing_duration'] = $gunbrokerListingTemplateRow['listing_duration'];
					$gunbrokerProductRow['prop_65_warning'] = $gunbrokerListingTemplateRow['prop_65_warning'];
					$gunbrokerProductRow['quantity'] = ($gunbrokerListingTemplateRow['listing_type'] == "auction" ? 1 : $thisRecord['quantity']);
					$gunbrokerProductRow['starting_bid'] = $gunbrokerListingTemplateRow['starting_bid'];
					$gunbrokerProductRow['who_pays_for_shipping'] = $gunbrokerListingTemplateRow['who_pays_for_shipping'];
					$gunbrokerProductRow['standard_text_identifier'] = $gunbrokerListingTemplateRow['standard_text_identifier'];
					$gunbrokerProductRow['shipping_profile_identifier'] = $gunbrokerListingTemplateRow['shipping_profile_identifier'];
					$gunbrokerProductRow['will_ship_international'] = $gunbrokerListingTemplateRow['will_ship_international'];
					$gunbrokerProductRow['has_view_counter'] = $gunbrokerListingTemplateRow['has_view_counter'];
					$gunbrokerProductRow['is_featured_item'] = $gunbrokerListingTemplateRow['is_featured_item'];
					$gunbrokerProductRow['is_highlighted'] = $gunbrokerListingTemplateRow['is_highlighted'];
					$gunbrokerProductRow['is_show_case_item'] = $gunbrokerListingTemplateRow['is_show_case_item'];
					$gunbrokerProductRow['is_title_boldface'] = $gunbrokerListingTemplateRow['is_title_boldface'];
					$gunbrokerProductRow['is_sponsored_onsite'] = $gunbrokerListingTemplateRow['is_sponsored_onsite'];
					$gunbrokerProductRow['scheduled_starting_date'] = (empty($thisRecord['scheduled_starting_date']) ? "" : date("Y-m-d H:i:s", strtotime($thisRecord['scheduled_starting_date'])));
					$gunbrokerProductRow['title_color'] = $gunbrokerListingTemplateRow['title_color'];

					if (!empty($thisRecord['starting_bid'])) {
						$gunbrokerProductRow['starting_bid'] = $thisRecord['starting_bid'];
					}
					if (!empty($thisRecord['buy_now_price'])) {
						$gunbrokerProductRow['buy_now_price'] = $thisRecord['buy_now_price'];
					}

					if (!$gunbrokerProductId = $dataTable->saveRecord(array("name_values" => $gunbrokerProductRow, "primary_id" => $gunbrokerProductId, "no_change_log" => true))) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $dataTable->getErrorMessage();
						ajaxResponse($returnArray);
						break;
					}
					if (empty($gunbrokerProductRow['gunbroker_product_id'])) {
						$insertCount++;
					} else {
						$updateCount++;
					}

					$insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $gunbrokerProductId);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$returnArray['response'] = "<p>" . $insertCount . " gun broker products imported.</p>";
				$returnArray['response'] .= "<p>" . $updateCount . " gun broker products updated.</p>";
				ajaxResponse($returnArray);
				break;
		}

	}

	function mainContent() {
		echo $this->iPageData['content'];

		?>
        <div id="_form_div">
            <p><strong>Valid Fields: </strong><?= implode(", ", $this->iValidFields) ?></p>
            <p><strong>Required Fields: </strong>upc_code or prouct_code, <?= implode(", ", $this->iRequiredFields) ?></p>
            <p>Category identifier is the numeric gunbroker category ID (e.g. 3026 for "Semi Auto Pistols")</p>

            <form id="_edit_form" enctype='multipart/form-data'>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="description" class="required-label">Description</label>
                    <input tabindex="10" class="validate[required]" size="40" type="text" id="description" name="description">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="csv_file" class="required-label">CSV File</label>
                    <input tabindex="10" class="validate[required]" type="file" id="csv_file" name="csv_file">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div id="import_error"></div>

                <div class="basic-form-line">
                    <button tabindex="10" id="_submit_form">Import</button>
                    <div id="import_message"></div>
                </div>

            </form>
        </div> <!-- form_div -->

        <table class="grid-table">
            <tr>
                <th>Description</th>
                <th>Imported On</th>
                <th>By</th>
                <th>Count</th>
                <th></th>
				<?php if (canAccessPage("GUNBROKERPRODUCTMAINT")) { ?>
                    <th></th>
				<?php } ?>
            </tr>
			<?php
			$resultSet = executeQuery("select * from csv_imports where table_name = 'gunbroker_products' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$importCount = 0;
				$countSet = executeQuery("select count(*) from csv_import_details where csv_import_id = ?", $row['csv_import_id']);
				if ($countRow = getNextRow($countSet)) {
					$importCount = $countRow['count(*)'];
				}
				$minutesSince = (time() - strtotime($row['time_submitted'])) / 60;
				$canUndo = $minutesSince < 48;
				?>
                <tr id="csv_import_id_<?= $row['csv_import_id'] ?>" class="import-row" data-csv_import_id="<?= $row['csv_import_id'] ?>">
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= date("m/d/Y g:i a", strtotime($row['time_submitted'])) ?></td>
                    <td><?= getUserDisplayName($row['user_id']) ?></td>
                    <td><?= $importCount ?></td>
                    <td><?= ($canUndo ? "<span class='far fa-undo remove-import'></span>" : "") ?></td>
					<?php if (canAccessPage("GUNBROKERPRODUCTMAINT")) { ?>
                        <td><span class='far fa-check-square select-gunbroker_products'></span></td>
					<?php } ?>
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
            $(document).on("click", ".select-gunbroker_products", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_contacts&csv_import_id=" + $(this).closest("tr").data("csv_import_id"));
            });
            $(document).on("click", ".remove-import", function () {
                const csvImportId = $(this).closest("tr").data("csv_import_id");
                $('#_confirm_undo_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    width: 400,
                    title: 'Remove Import',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_import&csv_import_id=" + csvImportId, function(returnArray) {
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
                if ($("#_submit_form").data("disabled") === "true") {
                    return false;
                }
                if ($("#_edit_form").validationEngine("validate")) {
                    disableButtons($("#_submit_form"));
                    $("body").addClass("waiting-for-ajax");
                    $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_csv").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").off("load");
                    $("#_post_iframe").on("load", function () {
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
                        enableButtons($("#_submit_form"));
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
            #import_error {
                color: rgb(192, 0, 0);
            }

            .remove-import {
                cursor: pointer;
            }

            .select-gunbroker_products {
                cursor: pointer;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these gunbroker products being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new GunbrokerCsvImportPage();
$pageObject->displayPage();
