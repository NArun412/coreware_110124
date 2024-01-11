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

$GLOBALS['gPageCode'] = "PRODUCTCATALOGCSVIMPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;
$GLOBALS['gSkipCorestoreContactUpdate'] = true;
ini_set("memory_limit", "4096M");

class ProductCatalogCsvImportPage extends Page {

    private $iValidFields = array("product_code", "upc_code");
    private $iDistributorProductCodeFields = array();
    private $iErrorMessages;

    function setup() {
        $productDistributorCodeResult = executeQuery("select product_distributor_code from product_distributors where inactive = 0");
        while($productDistributorCodeRow = getNextRow($productDistributorCodeResult)) {
            $this->iDistributorProductCodeFields[] = strtolower($productDistributorCodeRow['product_distributor_code']) . "_product_code";
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
                $GLOBALS['gPrimaryDatabase']->startTransaction();

                $imageIds = array();
                $resultSet = executeQuery("select image_id from products where image_id is not null and product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                while ($row = getNextRow($resultSet)) {
                    $imageIds[] = $row['image_id'];
                }
                $resultSet = executeQuery("select image_id from product_images where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                while ($row = getNextRow($resultSet)) {
                    $imageIds[] = $row['image_id'];
                }

                $deleteSet = executeQuery("delete from product_images where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: product images");

                $deleteSet = executeQuery("delete from product_remote_images where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: product remote images");

                $deleteSet = executeQuery("delete from product_group_variant_choices where product_group_variant_id in (select product_group_variant_id from product_group_variants where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?))", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: product_group_variant_choices");

                $deleteSet = executeQuery("delete from product_group_variants where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: product_group_variants");

                $deleteSet = executeQuery("delete from product_addons where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: product_addons");

                $deleteSet = executeQuery("delete from product_category_links where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: product category links");

                $deleteSet = executeQuery("delete from product_tag_links where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: product tag links");

                $deleteSet = executeQuery("delete from product_facet_values where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: product category links");

                $deleteSet = executeQuery("delete from product_search_word_values where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: product_search_word_values");

                $deleteSet = executeQuery("delete from distributor_product_codes where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: distributor_product_codes");

                $deleteSet = executeQuery("delete from product_prices where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: product_prices");

                $deleteSet = executeQuery("delete from product_sale_prices where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: product_sale_prices");

                $deleteSet = executeQuery("delete from product_data where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: product data");

                $deleteSet = executeQuery("delete from product_restrictions where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: product restrictions");

                $deleteSet = executeQuery("delete from product_inventories where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: product inventories");

                $deleteSet = executeQuery("delete from products where product_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
                $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: products");

                if (!empty($imageIds)) {
                    $deleteSet = executeQuery("delete from images where image_id in (" . implode(",", $imageIds) . ") and client_id = ?", $GLOBALS['gClientId']);
                    $this->checkSqlError($deleteSet,$returnArray,"Unable to remove import due to use of or changes to products: images");
                }

                $deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
                $this->checkSqlError($deleteSet, $returnArray, "Unable to remove import due to use of or changes to products");

                $deleteSet = executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
                $this->checkSqlError($deleteSet, $returnArray, "Unable to remove import");

                $returnArray['info_message'] = "Import successfully removed";
                $returnArray['csv_import_id'] = $csvImportId;
                $GLOBALS['gPrimaryDatabase']->commitTransaction();

                ajaxResponse($returnArray);

                break;

            case "import_file":
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
                $startTime = getMilliseconds();

                // Build lookup arrays
                $upcProductIdsResult = executeQuery("select products.product_id, product_code,upc_code from products join product_data using (product_id) where products.client_id = ?", $GLOBALS['gClientId']);
                $upcProducts = array();
                while ($row = getNextRow($upcProductIdsResult)) {
                    if (!empty($row['upc_code'])) {
                        $upcProducts[$row['upc_code']] = $row;
                    }
                }

                $catalogUpcs = array();
                ProductDistributor::downloadProductMetaData();
                if (is_array($GLOBALS['coreware_product_metadata'])) {
                    foreach ($GLOBALS['coreware_product_metadata'] as $thisProduct) {
                        $catalogUpcs[] = $thisProduct['upc_code'];
                    }
                }

                // Load and validate data
                $openFile = fopen($_FILES['csv_file']['tmp_name'], "r");
                $lineNumber = 0;
                $errorMessage = "";
                $importArray = array();
                $allValidFields = array_merge($this->iValidFields, $this->iDistributorProductCodeFields);
                $requiredFields = array("upc_code");
                $numericFields = array();
                $dateTimeFields = array();

                $fieldNames = array();
                $importRecords = array();
                $count = 0;
                $this->iErrorMessages = array();
                # parse file and check for invalid fields
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
                            $this->addErrorMessage("Invalid fields in CSV: " . $invalidFields);
                            $this->addErrorMessage("Valid fields are: " . implode(", ", $allValidFields));
                        }
                    } else {
                        $fieldData = array();
                        foreach ($csvData as $index => $thisData) {
                            $thisFieldName = $fieldNames[$index];
                            // strip non-printable characters - preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $thisData)
                            $fieldData[$thisFieldName] = trim(convertSmartQuotes($thisData));
                        }
                        if (empty(array_filter($fieldData))) {
                            continue;
                        }
                        $importRecords[] = $fieldData;
                    }
                    $count++;
                }
                fclose($openFile);

                # check for required fields and invalid data
                foreach ($importRecords as $index => $thisRecord) {
                    $missingFields = "";
                    foreach ($requiredFields as $thisField) {
                        if (strpos($thisField, "|") !== false) {
                            $alternateRequiredFields = explode("|", $thisField);
                            $found = false;
                            foreach ($alternateRequiredFields as $thisAlternate) {
                                $found = $found ?: !empty($thisRecord[$thisAlternate]);
                            }
                            if (!$found) {
                                $missingFields .= (empty($missingFields) ? "" : ", ") . str_replace("|", " or ", $thisField);
                            }
                        } else {
                            if (empty($thisRecord[$thisField])) {
                                $missingFields .= (empty($missingFields) ? "" : ", ") . $thisField;
                            }
                        }
                    }
                    if (!empty($missingFields)) {
                        $this->addErrorMessage("Line " . ($index + 2) . " has missing fields: " . $missingFields);
                    }

                    foreach ($numericFields as $fieldName) {
                        if (!empty($thisRecord[$fieldName]) && !is_float($thisRecord[$fieldName]) && !is_numeric($thisRecord[$fieldName])) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be numeric: " . $thisRecord[$fieldName]);
                        }
                    }
                    foreach ($dateTimeFields as $fieldName) {
                        if (!empty($thisRecord[$fieldName]) && strtotime($thisRecord[$fieldName]) == false) {
                            $this->addErrorMessage("Line " . ($index + 2) . ": " . $fieldName . " needs to be a valid date or time: " . $thisRecord[$fieldName]);
                        }
                    }
                }

                # product specific validation

                $foundCount = 0;
                $updateRecords = array();
                $catalogRecords = array();
                $notFoundRecords = array();
                $productCodesToImport = array();

                foreach ($importRecords as $index => $thisRecord) {
                    // make sure leading zeros are not changing the length of the UPC
                    $upcCode = ProductCatalog::makeValidUPC(str_pad(ltrim($thisRecord['upc_code'],"0"), 12,"0", STR_PAD_LEFT));
                    $productCode = makeCode($thisRecord['product_code']);
                    if(array_key_exists($productCode,$productCodesToImport)) {
                        $this->addErrorMessage("Product Code " . $productCode . " is already in use by UPC " . $productCodesToImport[$productCode]);
                        continue;
                    }

                    if(!empty($upcCode) && is_numeric($upcCode) && intval($upcCode) > 0) {
                        # identify products
                        if (array_key_exists($upcCode, $upcProducts)) {
                            $existingProduct = getRowFromId("products", "product_code", $productCode);
                            if ($upcProducts[$upcCode]['product_code'] != $productCode && !empty($existingProduct)) {
                                $this->addErrorMessage("Product code " . $productCode . " is being used by a different product (product ID" . $existingProduct['product_id'] . ")");
                                continue;
                            }
                            $updateRecords[$upcProducts[$upcCode]['product_id']] = $thisRecord;
                            $productCodesToImport[$productCode] = $upcCode;
                            continue;
                        }

                        if (in_array($upcCode, $catalogUpcs)) {
                            $catalogRecords[$upcCode] = $thisRecord;
                            $productCodesToImport[$productCode] = $upcCode;
                            continue;
                        }
                    }

                    $notFoundRecords[] = $thisRecord;
                }

                if (!empty($this->iErrorMessages)) {
                    $returnArray['import_error'] = "<p>" . count($this->iErrorMessages) . " errors found</p>";
                    foreach ($this->iErrorMessages as $thisMessage => $count) {
                        $returnArray['import_error'] .= "<p>" . ($count > 1 ? $count . ": " : "") . $thisMessage . "</p>";
                    }
                    ajaxResponse($returnArray);
                    break;
                }

                if (!empty($_POST['validate_only'])) {
                    $endTime = getMilliseconds();
                    $returnArray['response'] = sprintf("<p>File validated successfully in %s. %s matching products to update; %s UPCs available to import; %s UPCs not found in catalog.</p>",
                        getTimeElapsed($startTime,$endTime),count($updateRecords),count($catalogRecords),count($notFoundRecords));
                    if(!empty($notFoundRecords)) {
                        $returnArray['response'] .= "<p>Products not found:</p><ul>";
                        foreach ($notFoundRecords as $thisRecord) {
                            $returnArray['response'] .= sprintf("<li>UPC %s (product code %s)</li>", $thisRecord['upc_code'], $thisRecord['product_code']);
                        }
                    }
                    ajaxResponse($returnArray);
                    break;
                }

                // Import data
                $GLOBALS['gPrimaryDatabase']->startTransaction();
                $resultSet = executeQuery("insert into csv_imports (client_id,description,table_name,hash_code,time_submitted,user_id,content) values (?,?,'products_catalog',?,now(),?,?)",
                    $GLOBALS['gClientId'], $_POST['description'], $hashCode, $GLOBALS['gUserId'], file_get_contents($_FILES['csv_file']['tmp_name']));
                if (!empty($resultSet['sql_error'])) {
                    $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                    $returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("basic", $resultSet['sql_error']) . ($GLOBALS['gUserRow']['superuser_flag'] ? ": " . $resultSet['sql_error'] : "");
                    ajaxResponse($returnArray);
                    break;
                }
                $csvImportId = $resultSet['insert_id'];

                $insertCount = 0;
                $updateCount = 0;
                $productsTable = new DataTable('products');
                $productsTable->setSaveOnlyPresent(true);
                foreach($updateRecords as $productId=>$thisRecord) {
                    if($productsTable->saveRecord(["primary_id"=>$productId,"name_values"=>["product_code"=>makeCode($thisRecord['product_code'])]])) {
                        $this->createDistributorProductCodes($productId,$thisRecord, $returnArray);
                        $updateCount++;
                    } else {
                        $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                        $returnArray['error_message'] = $returnArray['import_error'] = $productsTable->getErrorMessage();
                        ajaxResponse($returnArray);
                        break 2;
                    }
                }

                foreach($catalogRecords as $upcCode=>$thisRecord) {
                    $result = ProductCatalog::importProductFromUPC($upcCode,["no_transaction"=>true]);
                    if(!empty($result['error_message'])) {
                        $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                        $returnArray['error_message'] = $returnArray['import_error'] = "UPC " . $upcCode . ": " . $result['error_message'];
                        ajaxResponse($returnArray);
                        break 2;
                    } else {
                        $productsTable->saveRecord(["primary_id"=>$result['product_id'],"name_values"=>["product_code"=>makeCode($thisRecord['product_code'])]]);
                    }
                    $this->createDistributorProductCodes($result['product_id'],$thisRecord, $returnArray);
                    $insertCount++;
                    $insertSet = executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $result['product_id']);
                    if (!empty($insertSet['sql_error'])) {
                        $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                        $returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
                        ajaxResponse($returnArray);
                        break 2;
                    }
                }

                $GLOBALS['gPrimaryDatabase']->commitTransaction();

                $endTime = getMilliseconds();
                $returnArray['response'] = "<p>Import completed in " . getTimeElapsed($startTime,$endTime) . ".</p>\n";
                $returnArray['response'] .= "<p>" . $insertCount . " Products imported.</p>\n";
                $returnArray['response'] .= "<p>" . $updateCount . " existing products updated.</p>\n";
                $returnArray['response'] .= "<p>" . count($notFoundRecords) . " products not found in catalog.</p>\n";
                if(!empty($notFoundRecords)) {
                    $returnArray['response'] .= "<p>Products not found:</p><ul>\n";
                    foreach ($notFoundRecords as $thisRecord) {
                        $returnArray['response'] .= sprintf("<li>UPC %s (product code %s)</li>\n", $thisRecord['upc_code'], $thisRecord['product_code']);
                    }
                }
                addProgramLog(strip_tags($returnArray['response']));
                ajaxResponse($returnArray);
                break;

        }

    }

    function createDistributorProductCodes($productId,$thisRecord,&$returnArray ) {
        foreach($this->iDistributorProductCodeFields as $distributorProductCodeField) {
            if(!empty($thisRecord[$distributorProductCodeField])) {
                $newProductCode = $thisRecord[$distributorProductCodeField];
                $distributorId = getFieldFromId("product_distributor_id", "product_distributors", "product_distributor_code",
                    str_replace("_product_code","",$distributorProductCodeField));
                $existingDistributorProductCode = getFieldFromId("product_code", "distributor_product_codes", "product_id", $productId,
                    "product_distributor_id = ?", $distributorId);
                if(empty($existingDistributorProductCode)) {
                    $resultSet = executeQuery("insert into distributor_product_codes (client_id, product_distributor_id, product_id, product_code) values (?,?,?,?)",
                        $GLOBALS['gClientId'], $distributorId,$productId,$newProductCode);
                    $this->checkSqlError($resultSet, $returnArray);
                } elseif($existingDistributorProductCode != $newProductCode) {
                    $resultSet = executeQuery("update distributor_product_codes set product_code = ? where product_id = ? and product_distributor_id = ?",
                        $newProductCode,$productId,$distributorId);
                    $this->checkSqlError($resultSet, $returnArray);
                }
            }
        }
    }

    function addErrorMessage($errorMessage) {
        if (array_key_exists($errorMessage, $this->iErrorMessages)) {
            $this->iErrorMessages[$errorMessage]++;
        } else {
            $this->iErrorMessages[$errorMessage] = 1;
        }
    }

    function checkSqlError($resultSet, &$returnArray, $errorMessage = "") {
        if (!empty($resultSet['sql_error'])) {
            if ($GLOBALS['gUserRow']['superuser_flag']) {
                $returnArray['error_message'] = $returnArray['import_error'] = $resultSet['sql_error'];
            } else {
                $returnArray['error_message'] = $returnArray['import_error'] = $errorMessage ?: getSystemMessage("basic", $resultSet['sql_error']);
            }
            $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
            ajaxResponse($returnArray);
        }
    }

    function mainContent() {
        echo $this->iPageData['content'];
        ?>
        <div id="_form_div">
            <form id="_edit_form" enctype='multipart/form-data'>

                <div class="basic-form-line" id="_description_row">
                    <label for="description" class="required-label">Description</label>
                    <input tabindex="10" class="validate[required]" size="40" type="text" id="description"
                           name="description">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="csv_file" class="required-label">CSV File</label>
                    <input tabindex="10" class="validate[required]" type="file" id="csv_file" name="csv_file">
                    <a class="valid-fields-trigger" href="#"><span class="help-label">Click here to check Valid Fields</span></a>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_validate_only">
                    <input type="checkbox" tabindex="10" id="validate_only" name="validate_only" value="1"><label
                            class="checkbox-label" for="validate_only">Validate Data only (do not import)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

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
                <th></th>
            </tr>
            <?php
            $resultSet = executeQuery("select * from csv_imports where table_name = 'products_catalog' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
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
                    <td><?= $importCount ?></td>
                    <td><?= ($canUndo ? "<span class='far fa-undo remove-import'></span>" : "") ?></td>
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
                    $editForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_file").attr("method", "POST").attr("target", "post_iframe").submit();
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
            #import_error {
                color: rgb(192, 0, 0);
            }

            .remove-import {
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

        <div id="_valid_fields_dialog" title="Valid Fields" class="dialog-box">
            <ul>
                <li><?= implode("</li><li>", array_merge($this->iValidFields)) ?></li>
            </ul>
            <div class="accordion">
                <?php if (!empty($this->iDistributorProductCodeFields)) { ?>
                    <h3>Distributor Product Code Fields</h3>
                    <!-- Has an extra wrapper div since columns CSS property doesn't work properly with accordion content's max height -->
                    <div>
                        <ul>
                            <li><?= implode("</li><li>", $this->iDistributorProductCodeFields) ?></li>
                        </ul>
                    </div>
                <?php } ?>
            </div>
        </div>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these products being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
        <?php
    }
}

$pageObject = new ProductCatalogCsvImportPage();
$pageObject->displayPage();
