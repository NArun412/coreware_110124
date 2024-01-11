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

$GLOBALS['gPageCode'] = "IMPORTDISTRIBUTORPRODUCTINFORMATION";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 600000;

if ($GLOBALS['gClientRow']['client_code'] != "COREWARE_SHOOTING_SPORTS" && ($GLOBALS['gClientRow']['client_code'] != "CORE" || !$GLOBALS['gDevelopmentServer'])) {
    if(!getPreference("AUTHORITATIVE_SITE")) {
        header("Location: /");
        exit;
    }
}

class ThisPage extends Page {

    function executePageUrlActions() {
        $returnArray = array();
        switch ($_GET['url_action']) {
            case "save_information":
                $locationId = getFieldFromId("location_id", "locations", "location_id", $_POST['location_id']);
                if (empty($locationId)) {
                    $returnArray['error_message'] = "Invalid Location";
                    ajaxResponse($returnArray);
                    break;
                }
                $productDistributorId = getFieldFromId("product_distributor_id", "locations", "location_id", $locationId);
                $productDistributor = ProductDistributor::getProductDistributorInstance($locationId);
                if (!$productDistributor) {
                    $returnArray['error_message'] = "Can't get product distributor";
                    ajaxResponse($returnArray);
                    break;
                }
                switch ($_POST['table_name']) {
                    case "product_categories":
                        $distributorCategories = $productDistributor->getCategories();
                        if ($distributorCategories === false) {
                            $returnArray['error_message'] = $productDistributor->getErrorMessage();
                            break;
                        }
                        $count = 0;
                        foreach ($_POST as $fieldName => $fieldValue) {
                            if (substr($fieldName, 0, strlen("distributor_id_")) == "distributor_id_") {
                                $rowNumber = substr($fieldName, strlen("distributor_id_"));
                                $distributorId = $fieldValue;
                                $productCategoryId = $_POST['product_category_id_' . $rowNumber];
                                if (strlen($productCategoryId) == 0) {
                                    continue;
                                }
                                if ($productCategoryId == -1) {
                                    $productCategoryCode = makeCode($distributorCategories[$distributorId]['description']);
                                    $productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_code", $productCategoryCode);
                                    if (!empty($productCategoryId)) {
                                        $productCategoryCode = strtoupper(getRandomString(20));
                                    }
                                    $description = ucwords(strtolower($distributorCategories[$distributorId]['description']));
                                    $insertSet = executeQuery("insert into product_categories (client_id,product_category_code,description,link_name) values (?,?,?,?)",
                                        $GLOBALS['gClientId'], $productCategoryCode, $description, makeCode($description, array("use_dash" => true, "lowercase" => true)));
                                    if (!empty($insertSet['sql_error'])) {
                                        $returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
                                        ajaxResponse($returnArray);
                                        break;
                                    }
                                    $productCategoryId = $insertSet['insert_id'];
                                    $count++;
                                }
                                executeQuery("insert into product_distributor_conversions (client_id,product_distributor_id,table_name,original_value,description,primary_identifier) values (?,?,'product_categories',?,?,?)",
                                    $GLOBALS['gClientId'], $productDistributorId, $distributorId, ucwords(strtolower($distributorCategories[$distributorId]['description'])), (empty($productCategoryId) ? -1 : $productCategoryId));
                                if (!empty($productCategoryId) && !empty($distributorCategories[$distributorId]['product_category_groups'])) {
                                    foreach ($distributorCategories[$distributorId]['product_category_groups'] as $thisCategoryGroupCode) {
                                        $productCategoryGroupId = getFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_code", $thisCategoryGroupCode);
                                        if (!empty($productCategoryGroupId)) {
                                            $productCategoryGroupLinkId = getFieldFromId("product_category_group_link_id", "product_category_group_links", "product_category_group_id", $productCategoryGroupId,
                                                "product_category_id = ?", $productCategoryId);
                                            if (empty($productCategoryGroupLinkId)) {
                                                executeQuery("insert ignore into product_category_group_links (product_category_group_id,product_category_id) values (?,?)", $productCategoryGroupId, $productCategoryId);
                                            }
                                        }
                                    }
                                }
                                if (!empty($productCategoryId) && !empty($distributorCategories[$distributorId]['product_departments'])) {
                                    foreach ($distributorCategories[$distributorId]['product_departments'] as $thisDepartmentCode) {
                                        $productDepartmentId = getFieldFromId("product_department_id", "product_departments", "product_department_code", $thisDepartmentCode);
                                        if (!empty($productDepartmentId)) {
                                            $productCategoryDepartmentId = getFieldFromId("product_category_department_id", "product_category_departments", "product_department_id", $productDepartmentId,
                                                "product_category_id = ?", $productCategoryId);
                                            if (empty($productCategoryDepartmentId)) {
                                                executeQuery("insert ignore into product_category_departments (product_department_id,product_category_id) values (?,?)", $productDepartmentId, $productCategoryId);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        if ($count > 0) {
                            $returnArray['info_message'] = $count . " product categor" . ($count == 1 ? "y" : "ies") . " created";
                        }
                        break;
                    case "product_manufacturers":
                        $distributorManufacturers = $productDistributor->getManufacturers();
                        if ($distributorManufacturers === false) {
                            $returnArray['error_message'] = $productDistributor->getErrorMessage();
                            break;
                        }
                        $count = 0;
                        foreach ($_POST as $fieldName => $fieldValue) {
                            if (substr($fieldName, 0, strlen("distributor_id_")) == "distributor_id_") {
                                $rowNumber = substr($fieldName, strlen("distributor_id_"));
                                $distributorId = $fieldValue;
                                $productManufacturerId = $_POST['product_manufacturer_id_' . $rowNumber];
                                if (strlen($productManufacturerId) == 0) {
                                    continue;
                                }
                                if ($productManufacturerId == -1) {
                                    $businessName = ucwords(strtolower($distributorManufacturers[$distributorId]['business_name']));
                                    if (empty($businessName)) {
                                        continue;
                                    }
                                    $imageId = "";
                                    if (!empty($distributorManufacturers[$distributorId]['image_url'])) {
                                        if (urlExists($distributorManufacturers[$distributorId]['image_url'])) {
                                            $imageContents = file_get_contents($distributorManufacturers[$distributorId]['image_url']);
                                        }
                                        if (!empty($imageContents)) {
                                            $filenameParts = explode(".", $distributorManufacturers[$distributorId]['image_url']);
                                            $extension = $filenameParts[count($filenameParts) - 1];
											$imageId = createImage(array("image_code" => "MANUFACTURER_LOGO_" . $distributorId, "extension" => $extension, "file_content" => $imageContents, "name" => $distributorId . "." . $extension,
												"description" => "Manufacturer Logo", "detailed_description" => ""));
                                        }
                                    }
                                    $contactDataTable = new DataTable("contacts");
                                    if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("business_name" => $businessName,
                                        "web_page" => $distributorManufacturers[$distributorId]['web_page'], "image_id" => $imageId)))) {
                                        $returnArray['error_message'] = $contactDataTable->getErrorMessage();
                                        ajaxResponse($returnArray);
                                        break;
                                    }
                                    $insertSet = executeQuery("insert into product_manufacturers (client_id,product_manufacturer_code,description,contact_id,link_name) values (?,?,?,?,?)",
                                        $GLOBALS['gClientId'], makeCode($businessName), $businessName, $contactId, makeCode($businessName, array("use_dash" => true, "lowercase" => true)));
                                    if (!empty($insertSet['sql_error'])) {
                                        $returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
                                        ajaxResponse($returnArray);
                                        break;
                                    }
                                    $productManufacturerId = $insertSet['insert_id'];
                                    $count++;
                                }
                                executeQuery("insert into product_distributor_conversions (client_id,product_distributor_id,table_name,original_value,description,primary_identifier) values (?,?,'product_manufacturers',?,?,?)",
                                    $GLOBALS['gClientId'], $productDistributorId, $distributorId, ucwords(strtolower($distributorManufacturers[$distributorId]['business_name'])), (empty($productManufacturerId) ? -1 : $productManufacturerId));
                            }
                        }
                        if ($count > 0) {
                            $returnArray['info_message'] = $count . " product manufacturer" . ($count == 1 ? "" : "s") . " created";
                        }
                        break;
                    case "product_facets":
                        $distributorFacets = $productDistributor->getFacets();
                        if ($distributorFacets === false) {
                            $returnArray['error_message'] = $productDistributor->getErrorMessage();
                            break;
                        }
                        $count = 0;
                        $newProductFacets = array();
                        foreach ($_POST as $fieldName => $fieldValue) {
                            if (substr($fieldName, 0, strlen("distributor_id_")) == "distributor_id_") {
                                $rowNumber = substr($fieldName, strlen("distributor_id_"));
                                $distributorId = $fieldValue;
                                $productCategoryId = $_POST['product_category_id_' . $rowNumber];
                                $productFacetId = $_POST['product_facet_id_' . $rowNumber];
                                $distributorCategoryId = $_POST['distributor_category_id_' . $rowNumber];
                                if (strlen($productFacetId) == 0) {
                                    continue;
                                }
                                if ($productFacetId == -1) {
                                    $description = ucwords(strtolower($distributorFacets[$distributorCategoryId][$distributorId]));
                                    $productFacetId = $newProductFacets[$description];
                                    if (empty($productFacetId)) {
                                        $productFacetCode = makeCode($description);
                                        $productFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_code", $productFacetCode);
                                        if (!empty($productFacetId)) {
                                            $productFacetCode = strtoupper(getRandomString(20));
                                        }
                                        $insertSet = executeQuery("insert into product_facets (client_id,product_facet_code,description) values (?,?,?)", $GLOBALS['gClientId'], $productFacetCode, $description);
                                        if (!empty($insertSet['sql_error'])) {
                                            $returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
                                            ajaxResponse($returnArray);
                                            break;
                                        }
                                        $productFacetId = $insertSet['insert_id'];
                                        $newProductFacets[$description] = $productFacetId;
                                        $count++;
                                    }
                                }
                                if (!empty($productFacetId)) {
                                    $productFacetCategoryId = getFieldFromId("product_facet_category_id", "product_facet_categories", "product_category_id", $productCategoryId, "product_facet_id = ?", $productFacetId);
                                    if (empty($productFacetCategoryId)) {
                                        executeQuery("insert ignore into product_facet_categories (product_category_id,product_facet_id) values (?,?)", $productCategoryId, $productFacetId);
                                    }
                                }
                                $originalValue = $distributorId;
                                executeQuery("delete from product_distributor_conversions where client_id = ? and product_distributor_id = ? and table_name = 'product_facets' and original_value = ?", $GLOBALS['gClientId'], $productDistributorId, $originalValue);
                                executeQuery("insert into product_distributor_conversions (client_id,product_distributor_id,table_name,original_value,description,primary_identifier) values (?,?,'product_facets',?,?,?)",
                                    $GLOBALS['gClientId'], $productDistributorId, $originalValue, ucwords(strtolower($distributorFacets[$distributorCategoryId][$distributorId])), (empty($productFacetId) ? -1 : $productFacetId));
                            }
                        }
                        if ($count > 0) {
                            $returnArray['info_message'] = $count . " product facet" . ($count == 1 ? "" : "s") . " created";
                        }
                        break;
                }
                ajaxResponse($returnArray);
                break;
            case "get_information":
                $locationId = getFieldFromId("location_id", "locations", "location_id", $_GET['location_id']);
                if (empty($locationId)) {
                    $returnArray['error_message'] = "Invalid Location";
                    ajaxResponse($returnArray);
                    break;
                }
                $locationRow = getRowFromId("locations", "location_id", $locationId);
                $productDistributorId = $locationRow['product_distributor_id'];
                $productDistributorCode = getFieldFromId("product_distributor_code", "product_distributors", "product_distributor_id", $locationRow['product_distributor_id']);
                $productDistributor = ProductDistributor::getProductDistributorInstance($locationId);
                if (!$productDistributor) {
                    $returnArray['error_message'] = "Can't get product distributor";
                    ajaxResponse($returnArray);
                    break;
                }
                $_GET['product_distributor_id'] = $productDistributor->getProductDistributorId();
                $batchSize = $_GET['batch_size'] ?: 200;
                ob_start();
                $tableName = $_GET['table_name'];
                ?>
                <input type="hidden" name="location_id" value="<?= $locationId ?>">
                <input type="hidden" name="table_name" value="<?= htmlText($tableName) ?>">
                <p>The left column is the information coming from the Distributor. The list is limited to <?= $batchSize ?> at a time. The right column is a dropdown of options. There are 4 options:</p>
                <ul id="instructions">
                    <li>Create New - The information from the distributor (category/manufacturer/facet) is NOT yet in the system, so create it.</li>
                    <li>Skip For Now - Not sure what to do with the distributor information, so we'll just skip it for now and come back to it later.</li>
                    <li>Don't Use - This information from the distributor will be ignored and disregarded. This is permanent removal and cannot be undo.</li>
                    <li>Existing Data - The remainder of the options are existing choices in the system. If the exact choice exists, it will be selected, but also look for slight differences in spelling.</li>
                </ul>
                <p>
                    <button tabindex="10" id="_save_product_information">Save Changes</button>
                    <button tabindex="10" id="_create_all">Create All</button>
                    <button tabindex="10" id="_cancel">Cancel</button>
                </p>
                <?php
                switch ($_GET['table_name']) {
                    case "product_categories":
                        $distributorCategories = $productDistributor->getCategories();
                        if ($distributorCategories === false) {
                            echo $productDistributor->getErrorMessage();
                            break;
                        }
                        $productCategories = array();
                        $resultSet = executeQuery("select * from product_categories where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
                        while ($row = getNextRow($resultSet)) {
                            $productCategories[$row['product_category_id']] = $row['description'];
                        }
                        ?>
                        <table id="information_table" class="grid-table">
                            <tr>
                                <th>Distributor Category</th>
                                <th>Coreware Product Category</th>
                                <th>Sample Products (Maximum of 5 displayed)</th>
                            </tr>
                            <?php
                            $count = 0;
                            foreach ($distributorCategories as $distributorId => $categoryInfo) {
                                $categoryDescription = $categoryInfo['description'];
                                $conversionId = getFieldFromId("product_distributor_conversion_id", "product_distributor_conversions", "product_distributor_id", $_GET['product_distributor_id'], "table_name = 'product_categories' and original_value = ?", $distributorId);
                                if (!empty($conversionId)) {
                                    executeQuery("update product_distributor_conversions set description = ? where product_distributor_conversion_id = ?",
                                        $categoryDescription, $conversionId);
                                    continue;
                                }
                                $count++;
                                ?>
                                <tr class="data-row">
                                    <td><input type="hidden" id="distributor_id_<?= $count ?>" name="distributor_id_<?= $count ?>" value="<?= $distributorId ?>"><?= htmlText(ucwords(strtolower($categoryDescription))) ?></td>
                                    <td><select tabindex="10" id="product_category_id_<?= $count ?>" name="product_category_id_<?= $count ?>">
                                            <option value="">[Skip For Now]</option>
                                            <option value="0">[Don't Use]</option>
                                            <option value="-1">[Create New]</option>
                                            <?php
                                            foreach ($productCategories as $productCategoryId => $description) {
                                                $thisValue = "";
                                                if (strtolower($description) == strtolower($categoryDescription)) {
                                                    $thisValue = $productCategoryId;
                                                }
                                                ?>
                                                <option value="<?= $productCategoryId ?>"<?= ($thisValue == $productCategoryId ? " selected" : "") ?>><?= htmlText($description) ?></option>
                                                <?php
                                            }
                                            ?>
                                        </select></td><td><?php
                                        if(!empty($categoryInfo['products'])) {
                                            echo "<table><tr><th>Product Code</th><th>Description</th><th>Cost</th></tr>";
                                            foreach ($categoryInfo['products'] as $thisProduct) {
                                                echo sprintf("<tr><td>%s</td><td>%s</td><td>%s</td></tr>",
                                                    htmlText($thisProduct['product_code']), htmlText($thisProduct['description']), htmlText($thisProduct['base_cost']));
                                            }
                                            echo "</table><p>Total in category: ". $categoryInfo['product_count'] . "</p>";
                                        }
                                        ?></td>
                                </tr>
                                <?php
                                if ($count > $batchSize) {
                                    break;
                                }
                            }
                            ?>
                        </table>
                        <?php
                        break;
                    case "product_manufacturers":
                        $distributorManufacturers = $productDistributor->getManufacturers();
                        if ($distributorManufacturers === false) {
                            $returnArray['error_message'] = $productDistributor->getErrorMessage();
                            break;
                        }
                        $productManufacturers = array();
                        $resultSet = executeQuery("select * from product_manufacturers where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
                        while ($row = getNextRow($resultSet)) {
                            $productManufacturers[$row['product_manufacturer_id']] = $row['description'];
                        }
                        ?>
                        <table id="information_table" class="grid-table">
                            <tr>
                                <th>Distributor Manufacturer</th>
                                <th>Coreware Manufacturer</th>
                            </tr>
                            <?php
                            $count = 0;
                            foreach ($distributorManufacturers as $distributorId => $manufacturerInfo) {
                                $manufacturerDescription = $manufacturerInfo['business_name'];
	                            if (empty($manufacturerDescription)) {
		                            continue;
	                            }
                                $conversionId = getFieldFromId("product_distributor_conversion_id", "product_distributor_conversions", "product_distributor_id", $_GET['product_distributor_id'], "table_name = 'product_manufacturers' and original_value = ?", $distributorId);
                                if (!empty($conversionId)) {
                                    executeQuery("update product_distributor_conversions set description = ? where product_distributor_conversion_id = ?",
                                        $manufacturerDescription, $conversionId);
                                    continue;
                                }
                                $count++;
                                ?>
                                <tr class="data-row">
                                    <td><input type="hidden" id="distributor_id_<?= $count ?>" name="distributor_id_<?= $count ?>" value="<?= $distributorId ?>"><?= htmlText(ucwords(strtolower($manufacturerDescription))) ?></td>
                                    <td><select tabindex="10" id="product_manufacturer_id_<?= $count ?>" name="product_manufacturer_id_<?= $count ?>">
                                            <option value="">[Skip For Now]</option>
                                            <option value="-1"<?= count($productManufacturers) == 0 || $productDistributorCode == "SPORTSSOUTH" ? " selected" : "" ?>>[Create New]</option>
                                            <option value="0">[Don't Use]</option>
                                            <?php
                                            foreach ($productManufacturers as $productManufacturerId => $description) {
                                                $thisValue = "";
                                                if (strtolower($description) == strtolower($manufacturerDescription)) {
                                                    $thisValue = $productManufacturerId;
                                                }
                                                ?>
                                                <option value="<?= $productManufacturerId ?>"<?= ($thisValue == $productManufacturerId ? " selected" : "") ?>><?= htmlText($description) ?></option>
                                                <?php
                                            }
                                            ?>
                                        </select></td>
                                </tr>
                                <?php
                                if ($count > $batchSize) {
                                    break;
                                }
                            }
                            ?>
                        </table>
                        <?php
                        break;
                    case "product_facets":
                        $distributorFacets = $productDistributor->getFacets();
                        if ($distributorFacets === false) {
                            $returnArray['error_message'] = $productDistributor->getErrorMessage();
                            break;
                        }
                        $productFacets = array();
                        $resultSet = executeQuery("select * from product_facets where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
                        while ($row = getNextRow($resultSet)) {
                            $productFacets[$row['product_facet_id']] = $row['description'];
                        }
                        ?>
                        <table id="information_table" class="grid-table">
                            <tr>
                                <th>Product Category</th>
                                <th>Distributor Facet</th>
                                <th>Coreware Facet</th>
                            </tr>
                            <?php
                            $count = 0;
                            foreach ($distributorFacets as $distributorCategoryId => $categoryFacets) {
                                $primaryIdentifier = getFieldFromId("primary_identifier", "product_distributor_conversions", "product_distributor_id", $_GET['product_distributor_id'], "table_name = 'product_categories' and original_value = ?", $distributorCategoryId);
                                if (empty($primaryIdentifier) || $primaryIdentifier < 0) {
                                    continue;
                                }
                                $productCategoryRow = getRowFromId("product_categories", "product_category_id", $primaryIdentifier);
                                if (empty($productCategoryRow)) {
                                    continue;
                                }
                                foreach ($categoryFacets as $distributorId => $facetDescription) {
                                    $conversionId = getFieldFromId("primary_identifier", "product_distributor_conversions", "product_distributor_id", $_GET['product_distributor_id'], "table_name = 'product_facets' and original_value = ?", $distributorId);
                                    if (!empty($conversionId)) {
                                        if ($conversionId < 0) {
                                            continue;
                                        }
                                        executeQuery("update product_distributor_conversions set description = ? where product_distributor_id = ? and table_name = 'product_facets' and original_value = ?",
                                            $facetDescription, $_GET['product_distributor_id'], $distributorId);
                                        $productFacetCategoryId = getFieldFromId("product_facet_category_id", "product_facet_categories", "product_category_id", $productCategoryRow['product_category_id'], "product_facet_id = ?", $conversionId);
                                        if (!empty($productFacetCategoryId)) {
                                            continue;
                                        }
                                    }
                                    $count++;
                                    ?>
                                    <tr class="data-row">
                                        <td><input type="hidden" id="product_category_id_<?= $count ?>" name="product_category_id_<?= $count ?>" value="<?= $productCategoryRow['product_category_id'] ?>"><input type="hidden" id="distributor_category_id_<?= $count ?>" name="distributor_category_id_<?= $count ?>" value="<?= $distributorCategoryId ?>"><input type="hidden" id="distributor_id_<?= $count ?>" name="distributor_id_<?= $count ?>" value="<?= $distributorId ?>"><?= htmlText($productCategoryRow['description']) ?></td>
                                        <td><?= htmlText(ucwords(strtolower($facetDescription))) ?></td>
                                        <td><select tabindex="10" id="product_facet_id_<?= $count ?>" name="product_facet_id_<?= $count ?>">
                                                <option value="">[Skip For Now]</option>
                                                <option value="-1"<?= count($productFacets) == 0 || $productDistributorCode == "SPORTSSOUTH" ? " selected" : "" ?>>[Create New]</option>
                                                <option value="0">[Don't Use]</option>
                                                <?php
                                                foreach ($productFacets as $productFacetId => $description) {
                                                    $thisValue = "";
                                                    if (strtolower($description) == strtolower($facetDescription)) {
                                                        $thisValue = $productFacetId;
                                                    }
                                                    ?>
                                                    <option value="<?= $productFacetId ?>"<?= ($thisValue == $productFacetId ? " selected" : "") ?>><?= htmlText($description) ?></option>
                                                    <?php
                                                }
                                                ?>
                                            </select></td>
                                    </tr>
                                    <?php
                                }
                                if ($count > $batchSize) {
                                    break;
                                }
                            }
                            ?>
                        </table>
                        <?php
                        break;
                }
                $returnArray['information_chart'] = ob_get_clean();
                ajaxResponse($returnArray);
                break;
        }
    }

    function onLoadJavascript() {
        ?>
        <script>
            $(document).on("click", "#_cancel", function () {
                $(".selector").show();
                $("#information_chart").html("");
                $("#location_id").prop("disabled", false).focus();
                $("#table_name").prop("disabled", false);
                $("html, body").animate({scrollTop: 0}, "slow");
            });
            $(document).on("click", "#_create_all", function () {
                $("#information_chart").find("select").each(function () {
                    var thisValue = $(this).val();
                    if (thisValue == "") {
                        $(this).val("-1");
                    }
                });
                return false;
            });
            $(document).on("click", "#_save_product_information", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_information", $("#_edit_form").serialize(), function(returnArray) {
                    if (!("error_message" in returnArray)) {
                        $(".selector").show();
                        $("#information_chart").html("");
                        $("#location_id").prop("disabled", false).focus();
                        $("#table_name").prop("disabled", false);
                        $("html, body").animate({scrollTop: 0}, "slow");
                        <?php if (!empty($_GET['auto'])) { ?>
                        setTimeout(function () {
                            $("#get_information").trigger("click");
                        }, 1000);
                        <?php } ?>
                    }
                });
                return false;
            });
            $("#get_information").click(function () {
                if (empty($("#location_id").val()) || empty($("#table_name").val())) {
                    displayErrorMessage("Location and Information Type are required");
                } else {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_information&location_id=" + $("#location_id").val() + "&table_name=" + $("#table_name").val() + "&batch_size=" + $("#batch_size").val(), function(returnArray) {
                        if ("information_chart" in returnArray) {
                            $(".selector").hide();
                            $("#information_chart").html(returnArray['information_chart']);
                            $("#location_id").prop("disabled", true);
                            $("#table_name").prop("disabled", true);
                        } else {
                            $(".selector").show();
                            $("#information_chart").html("");
                            $("#location_id").prop("disabled", false);
                            $("#table_name").prop("disabled", false);
                        }
                        <?php if (!empty($_GET['auto'])) { ?>
                        if ($("#information_table").find("tr.data-row").length > 0) {
                            setTimeout(function () {
                                $("#_save_product_information").trigger("click");
                            }, 1000);
                        }
                        <?php } ?>
                    });
                }
                return false;
            });
        </script>
        <?php
    }

    function mainContent() {
        echo $this->iPageData['content'];
        ?>
        <form id="_edit_form">
            <div class="basic-form-line selector">
                <label for="location_id">Location</label>
                <select tabindex="10" id="location_id">
                    <option value="">[Select]</option>
                    <?php
                    $resultSet = executeQuery("select * from locations join location_credentials using (location_id) where locations.client_id = ? and locations.inactive = 0 and location_credentials.inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
                    while ($row = getNextRow($resultSet)) {
                        ?>
                        <option value="<?= $row['location_id'] ?>"><?= htmlText($row['description']) ?></option>
                        <?php
                    }
                    ?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line selector">
                <label for="table_name">Information Type</label>
                <select tabindex="10" id="table_name">
                    <option value="">[Select]</option>
                    <option value="product_categories">Product Categories</option>
                    <option value="product_manufacturers">Manufacturers</option>
                    <option value="product_facets">Facets</option>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line selector">
                <label for="batch_size">Batch Size</label>
                <select tabindex="10" id="batch_size">
                    <option value="200">200</option>
                    <option value="100">100</option>
                    <option value="50">50</option>
                    <option value="20">20</option>
                </select>
                <div class='basic-form-line-messages'><span class="help-label">Number of records to retrieve at one time</span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line selector">
                <button tabindex="10" id="get_information">Get Information</button>
            </div>
            <div id="information_chart"></div>
        </form>
        <?php
        echo $this->iPageData['after_form_content'];
        return true;
    }

    function internalCSS() {
        ?>
        <style>
            ul#instructions {
                margin-bottom: 20px;
                list-style: disc;
                margin-left: 20px;
            }

            ul#instructions li {
                margin-bottom: 5px;
                list-style: disc;
            }

            #information_chart table {
                margin-bottom: 20px;
            }
        </style>
        <?php
    }
}

$pageObject = new ThisPage();
$pageObject->displayPage();
