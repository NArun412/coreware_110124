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

$GLOBALS['gPageCode'] = "PRODUCTMAINT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;
ini_set("memory_limit", "4096M");

class ProductMaintenancePage extends Page {

	function setup() {
		$productTypeId = getFieldFromId("product_type_id", "product_types", "product_type_code", "ORDER_UPSELL_PRODUCT");
		if (empty($productTypeId)) {
			executeQuery("insert into product_types (client_id,product_type_code,description) values (?,'ORDER_UPSELL_PRODUCT','Order Upsell Product')", $GLOBALS['gClientId']);
		}
		$customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "PRODUCTS");
		$customFieldId = CustomField::getCustomFieldIdFromCode("ALLOW_MULTIPLE_UPGRADES", "PRODUCTS");
		if (empty($customFieldId)) {
			$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
				$GLOBALS['gClientId'], "ALLOW_MULTIPLE_UPGRADES", "Quantity is the number of products to which upgrade applies", $customFieldTypeId, "Quantity is the number of products to which upgrade applies");
			$customFieldId = $insertSet['insert_id'];
			executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,'data_type','tinyint')", $customFieldId);
		}
		$customFieldId = CustomField::getCustomFieldIdFromCode("PERCENTAGE_PRICE", "PRODUCTS");
		if (empty($customFieldId)) {
			$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
				$GLOBALS['gClientId'], "PERCENTAGE_PRICE", "Cost is this percentage of the products to which the upgrade applies", $customFieldTypeId, "Cost is this percentage of the products to which the upgrade applies");
			$customFieldId = $insertSet['insert_id'];
			executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,'data_type','decimal')", $customFieldId);
			executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,'decimal_places','2')", $customFieldId);
			executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,'minimum_value','0')", $customFieldId);
		}
		$customFieldId = CustomField::getCustomFieldIdFromCode("REQUIRE_AFTER_X_DAYS", "PRODUCTS");
		if (empty($customFieldId)) {
			$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
				$GLOBALS['gClientId'], "REQUIRE_AFTER_X_DAYS", "Number of days after which this upgrade must be purchased", $customFieldTypeId, "Number of days after which this upgrade must be purchased");
			$customFieldId = $insertSet['insert_id'];
			executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,'data_type','int')", $customFieldId);
			executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,'minimum_value','0')", $customFieldId);
		}
		$customFieldId = CustomField::getCustomFieldIdFromCode("ORDER_UPSELL_DESCRIPTION", "PRODUCTS");
		if (empty($customFieldId)) {
			$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
				$GLOBALS['gClientId'], "ORDER_UPSELL_DESCRIPTION", "Description to show as an upsell product instead of the Product Title", $customFieldTypeId, "Description to show as an upsell product instead of the Product Title");
			$customFieldId = $insertSet['insert_id'];
			executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,'data_type','varchar')", $customFieldId);
		}

		$filters = array();
		if ($GLOBALS['gClientRow']['client_code'] == "COREWARE_SHOOTING_SPORTS") {
			$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "MISSING_INFORMATION");
			if (empty($productTagId)) {
				$resultSet = executeQuery("insert into product_tags (client_id,product_tag_code,description,internal_use_only) values (?,'MISSING_INFORMATION','Missing Information',1)", $GLOBALS['gClientId']);
				$productTagId = $resultSet['insert_id'];
			}
			$ignoreProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "IGNORE_MISSING_INFORMATION");
			if (empty($ignoreProductTagId)) {
				$resultSet = executeQuery("insert into product_tags (client_id,product_tag_code,description,internal_use_only) values (?,'IGNORE_MISSING_INFORMATION','Ignore Missing Information',1)", $GLOBALS['gClientId']);
				$ignoreProductTagId = $resultSet['insert_id'];
			}
			$filters['missing_info'] = array("form_label" => "Missing Important Information", "where" => "products.product_id in (select product_id from product_tag_links where product_tag_id = " . $productTagId . ") and products.product_id not in (select product_id from product_tag_links where product_tag_id = " . $ignoreProductTagId . ")", "data_type" => "tinyint");
		}

		$filters['no_manufacturer'] = array("form_label" => "No Manufacturer Set", "where" => "product_manufacturer_id is null", "data_type" => "tinyint");

		$resultSet = executeQuery("select * from pricing_structures where client_id = ?", $GLOBALS['gClientId']);
		$pricingStructures = array();
		$defaultPricingStructureId = false;
		while ($row = getNextRow($resultSet)) {
			$pricingStructures[$row['pricing_structure_id']] = $row['description'];
			if ($row['pricing_structure_code'] == "DEFAULT") {
				$defaultPricingStructureId = $row['pricing_structure_id'];
			}
		}
		if (!empty($pricingStructures)) {
			$filters['pricing_structure_id'] = array("form_label" => "Uses Pricing Structure", "where" => "pricing_structure_id = %key_value%" .
				(empty($defaultPricingStructureId) ? "" : " or (%key_value% = " . $defaultPricingStructureId . " and pricing_structure_id is null)"), "data_type" => "select", "choices" => $pricingStructures);
		}

		$filters['no_sku'] = array("form_label" => "Product has No SKU", "where" => "manufacturer_sku is null", "data_type" => "tinyint");
		$filters['local_image'] = array("form_label" => "Has Local Image", "where" => "image_id is not null", "data_type" => "tinyint");
		$filters['no_image'] = array("form_label" => "No Image Set", "where" => "image_id is null" .
			($GLOBALS['gClientRow']['client_code'] == "COREWARE_SHOOTING_SPORTS" ? "" : " and products.product_id not in (select product_id from product_remote_images)"), "data_type" => "tinyint");
		if ($GLOBALS['gClientRow']['client_code'] == "COREWARE_SHOOTING_SPORTS") {
			$filters['duplicate_images'] = array("form_label" => "Has image used by multiple products", "where" =>
				"image_id in (select image_id from products where client_id = " . $GLOBALS['gClientId'] . " group by image_id having count(*) > 1)", "data_type" => "tinyint");
		}
		$filters['not_selected'] = array("form_label" => "Not selected", "where" => "products.product_id not in (" .
			"select primary_identifier from selected_rows where page_id = " . $GLOBALS['gPageId'] . " and user_id = " . $GLOBALS['gUserId'] . ")", "data_type" => "tinyint");
		$filters['no_link_name'] = array("form_label" => "No Link Name Set", "where" => "link_name is null", "data_type" => "tinyint");
		$filters['inventory_notifications'] = array("form_label" => "Inventory Notifications Setup", "where" => "products.product_id in (select product_id from product_inventory_notifications)", "data_type" => "tinyint");
		$resultSet = executeQuery("select location_id from locations where client_id = ? and inactive = 0 and internal_use_only = 0 and ignore_inventory = 0", $GLOBALS['gClientId']);
		$locationIds = array();
		while ($row = getNextRow($resultSet)) {
			$locationIds[] = $row['location_id'];
		}
		if (!empty($locationIds)) {
			$filters['in_stock'] = array("form_label" => "In Stock for Customer", "where" => "products.product_id in (select product_id from product_inventories where quantity > 0 and location_id in (" . implode(",", $locationIds) . "))", "data_type" => "tinyint");
		}
		$filters['tag_header'] = array("form_label" => "Tags", "data_type" => "header");
		$resultSet = executeQuery("select * from product_tags where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$filters['product_tag_' . $row['product_tag_id']] = array("form_label" => $row['description'],
				"where" => "products.product_id in (select product_id from product_tag_links where (expiration_date is null or expiration_date > current_date) and product_tag_id = " . $row['product_tag_id'] . ")",
				"data_type" => "tinyint");
		}
		$filters['sale_price_set'] = array("form_label" => "Sale Price Set", "where" => "products.product_id in (select product_id from product_prices where product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'SALE_PRICE'))", "conjunction" => "and");
		$filters['sale_price_no_location_set'] = array("form_label" => "Sale Price Without Location Set", "where" => "products.product_id in (select product_id from product_prices where location_id is null and product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'SALE_PRICE'))", "conjunction" => "and");
		$filters['has_minimum_price'] = array("form_label" => "Has Minimum Price", "where" => "product_data.minimum_price is not null", "conjunction" => "and");

		$resultSet = executeQuery("select * from product_departments where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
		$departments = array();
		while ($row = getNextRow($resultSet)) {
			$departments[$row['product_department_id']] = $row['description'];
		}
		$filters['product_department'] = array("form_label" => "Product Department", "where" => "products.product_id in (select product_id from product_category_links where product_category_id in " .
			"(select product_category_id from product_category_departments where product_department_id = %key_value%) or product_category_id in " .
			"(select product_category_id from product_category_group_links where product_category_group_id in (select product_category_group_id from product_category_group_departments where product_department_id = %key_value%)))",
			"data_type" => "select", "choices" => $departments, "conjunction" => "and");
		$resultSet = executeQuery("select * from product_categories where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
		if ($resultSet['row_count'] > 10) {
			$categories = array();
			while ($row = getNextRow($resultSet)) {
				$categories[$row['product_category_id']] = $row['description'];
			}
			$filters['product_category'] = array("form_label" => "Product Category", "where" => "products.product_id in (select product_id from product_category_links where product_category_id = %key_value%)", "data_type" => "select", "choices" => $categories, "conjunction" => "and");
		} else {
			$filters['category_header'] = array("form_label" => "Categories", "data_type" => "header");
			while ($row = getNextRow($resultSet)) {
				$filters['product_category_' . $row['product_category_id']] = array("form_label" => $row['description'],
					"where" => "products.product_id in (select product_id from product_category_links where product_category_id = " . $row['product_category_id'] . ")",
					"data_type" => "tinyint");
			}
		}
		$resultSet = executeQuery("select * from product_types where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
		if ($resultSet['row_count'] > 0) {
			$types = array();
			while ($row = getNextRow($resultSet)) {
				$types[$row['product_type_id']] = $row['description'];
			}
			$filters['product_types'] = array("form_label" => "Product Type", "where" => "products.product_type_id = %key_value%", "data_type" => "select", "choices" => $types);
		}
		$resultSet = executeQuery("select * from product_manufacturers where client_id = ? order by description", $GLOBALS['gClientId']);
		if ($resultSet['row_count'] > 0) {
			$manufacturers = array();
			while ($row = getNextRow($resultSet)) {
				$manufacturers[$row['product_manufacturer_id']] = $row['description'];
			}
			$filters['product_manufacturers'] = array("form_label" => "Manufacturer", "where" => "products.product_manufacturer_id = %key_value%", "data_type" => "select", "choices" => $manufacturers, "conjunction" => "and");
		}

		$locations = getCachedData("product_maintenance_locations", "product_maintenance_locations");
		if (empty($locations)) {
			$locations = array();
			$resultSet = executeQuery("select *,(select description from product_distributors where product_distributor_id = locations.product_distributor_id) product_distributor_description from locations " .
				"where client_id = ? and inactive = 0 and (product_distributor_id is null or primary_location = 1) order by primary_location,sort_order,description", $GLOBALS['gClientId']);
			if ($resultSet['row_count'] > 0) {
				while ($row = getNextRow($resultSet)) {
					if (empty($row['product_distributor_id'])) {
						$locations[$row['location_id']] = $row['description'];
					} else {
						$locations[$row['location_id']] = $row['product_distributor_description'];
					}
				}
			}
			setCachedData("product_maintenance_locations", "product_maintenance_locations", $locations);
		}
		if (!empty($locations)) {
			$filters['exist_locations'] = array("form_label" => "Exists at location", "where" => "products.product_id in (select product_id from product_inventories where location_id = %key_value%)", "data_type" => "select", "choices" => $locations, "conjunction" => "and");
			$filters['locations'] = array("form_label" => "In stock at location", "where" => "products.product_id in (select product_id from product_inventories where quantity > 0 and location_id = %key_value%)", "data_type" => "select", "choices" => $locations, "conjunction" => "and");
		}

		$filters['only_one'] = array("form_label" => "Only in one category", "where" => "(select count(*) from product_category_links where product_id = products.product_id) = 1", "conjunction" => "and");
		$filters['no_active_category'] = array("form_label" => "Not in any active category", "where" => "products.product_id not in (select product_id from product_category_links where product_category_id in (select product_category_id from product_categories where inactive = 0))", "conjunction" => "and");
		$filters['no_category'] = array("form_label" => "Not in any category", "where" => "products.product_id not in (select product_id from product_category_links)", "conjunction" => "and");
		$filters['more_than_one'] = array("form_label" => "In more than one category", "where" => "(select count(*) from product_category_links where product_id = products.product_id) > 1", "conjunction" => "and");
		$filters['no_manufacturer'] = array("form_label" => "No Manufacturer", "where" => "products.product_manufacturer_id is null", "data_type" => "tinyint");
		$filters['no_cost'] = array("form_label" => "No Base Cost Set", "where" => "(base_cost is null or base_cost = 0)", "conjunction" => "and");
		$filters['no_list_price'] = array("form_label" => "No List Price Set", "where" => "(list_price is null or list_price = 0)", "conjunction" => "and");
		$filters['no_upc_code'] = array("form_label" => "No UPC Set", "where" => "upc_code is null", "conjunction" => "and");
		$filters['no_upc_and_no_inventory'] = array("form_label" => "Missing UPC and no inventory anywhere", "where" => "upc_code is null and products.product_id not in (select product_id from distributor_product_codes) " .
			"and products.product_id not in (select product_id from product_inventories where quantity > 0 or location_id in (select location_id from locations where product_distributor_id is not null))", "conjunction" => "and");
		$filters['product_pack'] = array("form_label" => "Is Kit", "where" => "products.product_id in (select product_id from product_pack_contents)", "conjunction" => "and");
		$filters['product_shipping_methods'] = array("form_label" => "Has Shipping Restriction", "where" => "products.product_id in (select product_id from product_shipping_methods)", "conjunction" => "and");

		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("add_to_category", "Add Selected Products to Category");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("remove_from_category", "Remove Selected Products from Category");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_manufacturer", "Set Manufacturer for Selected Products");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_tag", "Tag Selected Products");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("remove_tag", "Remove Tag from Selected Products");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_custom_field", "Set Custom Field on Selected Products");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("remove_custom_field", "Remove Custom Field from Selected Products");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("add_inventory_notification", "Add Inventory Notification to Selected Products");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_link_name", "Set Link Name to Description for selected Products");
		if ($GLOBALS['gClientRow']['client_code'] == "COREWARE_SHOOTING_SPORTS") {
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("tag_missing_information", "Tag products with missing information");
		}
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_inactive", "Mark selected Products inactive");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("clear_inactive", "Clear inactive flag for selected Products");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("remove_sale_prices", "Remove set sale prices from select products");


		$this->iTemplateObject->getTableEditorObject()->setIgnoreGuiSorting(true);
		$this->iTemplateObject->getTableEditorObject()->addIncludeSearchColumn(array("product_id", "product_code", "upc_code"));
		if ($GLOBALS['gPermissionLevel'] > _READONLY) {
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("icon" => "fad fa-copy", "label" => getLanguageText("Duplicate"), "disabled" => false)));
			if ($GLOBALS['gClientRow']['client_code'] == "COREWARE_SHOOTING_SPORTS") {
				$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("research" => array("label" => getLanguageText("Research"), "disabled" => false)));
			}
		}
		$pageId = getFieldFromId("page_id", "pages", "script_filename", "retailstore/productdetails.php", "link_name = 'product-details'");
		if (!empty($pageId)) {
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("view_site" => array("icon" => "fad fa-eye", "label" => getLanguageText("View"), "disabled" => false)));
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		$inactiveValue = false;
		switch ($_GET['url_action']) {
			case "import_product_addons":
				$resultSet = executeQuery("select * from product_addon_set_entries where product_addon_set_id = ?", $_GET['product_addon_set_id']);
				$returnArray['product_addons'] = array();
				while ($row = getNextRow($resultSet)) {
					$rowValues = array();
					$rowValues['description'] = array("data_value" => $row['description'], "crc_value" => getCrcValue($row['description']));
					$rowValues['group_description'] = array("data_value" => $row['group_description'], "crc_value" => getCrcValue($row['group_description']));
					$rowValues['manufacturer_sku'] = array("data_value" => $row['manufacturer_sku'], "crc_value" => getCrcValue($row['manufacturer_sku']));
					$rowValues['form_definition_id'] = array("data_value" => $row['form_definition_id'], "crc_value" => getCrcValue($row['form_definition_id']));
					$rowValues['inventory_product_id'] = array("data_value" => $row['inventory_product_id'], "crc_value" => getCrcValue($row['inventory_product_id']));
					$rowValues['maximum_quantity'] = array("data_value" => $row['maximum_quantity'], "crc_value" => getCrcValue($row['maximum_quantity']));
					$rowValues['sale_price'] = array("data_value" => $row['sale_price'], "crc_value" => getCrcValue($row['sale_price']));
					$rowValues['sort_order'] = array("data_value" => $row['sort_order'], "crc_value" => getCrcValue($row['sort_order']));
					$returnArray['product_addons'][] = $rowValues;
				}
				ajaxResponse($returnArray);
				break;
			case "get_facets":
				$productCategoryIds = explode(",", $_GET['product_category_ids']);
				$facetIds = array();
				foreach ($productCategoryIds as $productCategoryId) {
					$resultSet = executeQuery("select * from product_facet_categories where product_category_id = ?", $productCategoryId);
					while ($row = getNextRow($resultSet)) {
						$facetIds[$row['product_facet_id']] = $row['product_facet_id'];
					}
				}
				$returnArray['product_facet_ids'] = $facetIds;
				ajaxResponse($returnArray);
				break;
			case "get_taxonomy":
				$productCategoryIds = explode(",", $_GET['product_category_ids']);
				$productCategoryIds = array_filter($productCategoryIds, 'is_numeric');

				$taxonomy = array();
				if (!empty($productCategoryIds)) {
					$resultSet = executeReadQuery("select description as product_category,"
						. "	(select group_concat(description separator '|') from product_category_groups"
						. " where inactive = 0 and product_category_group_id in (select product_category_group_id from product_category_group_links"
						. " where product_category_group_links.product_category_id = product_categories.product_category_id)) as product_category_groups,"
						. " (select group_concat(description separator '|') from product_departments"
						. " where inactive = 0 and product_department_id in (select product_department_id from product_category_departments"
						. " where product_category_departments.product_category_id = product_categories.product_category_id)"
						. " or product_department_id in (select product_department_id from product_category_group_departments join product_category_groups using (product_category_group_id)"
						. " where product_category_group_id in (select product_category_group_id from product_category_group_links"
						. " where product_category_group_links.product_category_id = product_categories.product_category_id))) as product_departments"
						. " from product_categories where product_category_id in (" . implode(",", $productCategoryIds) . ") and inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$taxonomy[] = $row;
					}
				}
				$returnArray['taxonomy'] = $taxonomy;
				ajaxResponse($returnArray);
				break;
			case "import_upc_code":
				$productId = getFieldFromId("product_id", "product_data", "upc_code", $_GET['upc_code']);
				if (!empty($productId)) {
					$returnArray['error_message'] = "Product already exists";
					ajaxResponse($returnArray);
					break;
				}
				$returnArray = ProductCatalog::importProductFromUPC($_GET['upc_code']);
				ajaxResponse($returnArray);
				break;
			case "get_taxjar_categories":
				$taxjarCategories = getCachedData("taxjar_categories", "", true);
				if (!empty($taxjarCategories)) {
					$returnArray['taxjar_categories'] = $taxjarCategories;
					ajaxResponse($returnArray);
					break;
				}

				$taxjarApiToken = getPreference("taxjar_api_token");
				if (empty($taxjarApiToken)) {
					$returnArray['error_message'] = "TaxJar not configured";
					ajaxResponse($returnArray);
					break;
				}

				$client = false;
				require_once __DIR__ . '/taxjar/vendor/autoload.php';
				try {
					$client = TaxJar\Client::withApiKey($taxjarApiToken);
					$client->setApiConfig('headers', ['x-api-version' => '2022-01-24']);
				} catch (Exception $e) {
					$returnArray['error_message'] = "TaxJar not configured";
					ajaxResponse($returnArray);
					break;
				}
				if (!$client) {
					$returnArray['error_message'] = "TaxJar not configured";
					ajaxResponse($returnArray);
					break;
				}

				ob_start();
				$categories = array();
				try {
					$categories = $client->categories();
				} catch (Exception $e) {
				}

				?>
                <table id='taxjar_category_table' class='grid-table'>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Tax Code</th>
                    </tr>
					<?php
					foreach ((array)$categories as $thisCategory) {
						?>
                        <tr class='taxjar-category' data-taxjar_product_tax_code="<?= $thisCategory->product_tax_code ?>">
                            <td><?= $thisCategory->name ?></td>
                            <td><?= $thisCategory->description ?></td>
                            <td><?= $thisCategory->product_tax_code ?></td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
				$returnArray['taxjar_category_list'] = ob_get_clean();
				setCachedData("taxjar_categories", "", $returnArray['taxjar_categories'], 240, true);

				ajaxResponse($returnArray);

				break;
			case "get_product_inventory_log":
				$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id']);
				$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_inventory_id", $_GET['product_inventory_id'], "product_id = ?", $productId);
				if (empty($productId) || empty($productInventoryId)) {
					ajaxResponse($returnArray);
					break;
				}
				$productInventoryRow = getRowFromId("product_inventories", "product_inventory_id", $productInventoryId);
				ob_start();
				?>
                <h2>Inventory log for <?= getFieldFromId("description", "locations", "location_id", $productInventoryRow['location_id']) ?></h2>
                <table class="grid-table">
                    <tr>
                        <td>Adjustment Type</td>
                        <td>Log Time</td>
                        <td>Quantity</td>
                        <td>Total Cost</td>
                        <td>Cost</td>
                        <td>Notes</td>
                    </tr>
					<?php
					$resultSet = executeQuery("select * from product_inventory_log where product_inventory_id = ? order by log_time desc limit 50", $productInventoryId);
					while ($row = getNextRow($resultSet)) {
						?>
                        <tr>
                            <td><?= getFieldFromId("description", "inventory_adjustment_types", "inventory_adjustment_type_id", $row['inventory_adjustment_type_id']) ?></td>
                            <td><?= date("m/d/Y g:ia", strtotime($row['log_time'])) ?></td>
                            <td class='align-right'><?= $row['quantity'] ?></td>
                            <td class='align-right'><?= (empty($row['total_cost']) ? "" : number_format($row['total_cost'], 2, ".", ",")) ?></td>
                            <td class='align-right'><?= (empty($row['total_cost']) || $row['quantity'] <= 0 ? "" : number_format(round($row['total_cost'] / $row['quantity'], 2), 2, ".", ",")) ?></td>
                            <td><?= htmlText($row['notes']) ?></td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
				$returnArray['product_inventory_log'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "set_manufacturer":
				$productIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productIds[] = $row['primary_identifier'];
				}
				$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $_POST['product_manufacturer_id']);
				$count = 0;
				if (!empty($productIds) && !empty($productManufacturerId)) {
					$resultSet = executeQuery("update products set time_changed = now(), product_manufacturer_id = ? where client_id = ? and products.product_id in (" . implode(",", $productIds) . ")",
						$productManufacturerId, $GLOBALS['gClientId']);
					$count = $resultSet['affected_rows'];
				}
				$returnArray['info_message'] = $count . " products changed";
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,old_value,notes) values (?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'products', 'product_id', $count . " products manufacturer set to " . getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $productManufacturerId), jsonEncode($productIds),
					(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
			case "add_inventory_notification":
				$productIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productIds[] = $row['primary_identifier'];
				}
				$count = 0;
				foreach ($productIds as $productId) {
					$productInventoryNotificationId = getFieldFromId("product_inventory_notification_id", "product_inventory_notifications", "product_id", $productId,
						"product_distributor_id <=> ? and comparator = ? and quantity = ?", $_POST['product_distributor_id'], $_POST['comparator'], $_POST['quantity']);
					if (empty($productInventoryNotificationId)) {
						executeQuery("insert into product_inventory_notifications (product_id,user_id,email_address,product_distributor_id,comparator, quantity,place_order,location_id,order_quantity," .
							"use_lowest_price,allow_multiple) values (?,?,?,?,?, ?,?,?,?,?, ?)", $productId, $GLOBALS['gUserId'], $_POST['email_address'], $_POST['product_distributor_id'], $_POST['comparator'],
							$_POST['quantity'], (empty($_POST['place_order']) ? 0 : 1), (empty($_POST['place_order']) ? "" : $_POST['location_id']), (empty($_POST['place_order']) ? "" : $_POST['order_quantity']),
							(empty($_POST['place_order']) ? 0 : (empty($_POST['use_lowest_price']) ? 0 : 1)), (empty($_POST['place_order']) ? 0 : (empty($_POST['allow_multiple']) ? 0 : 1)));
						$count++;
					}
				}
				$returnArray['info_message'] = $count . " product inventory notifications added";
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,old_value,notes) values (?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'products', 'product_id', $count . " product inventory notifications set up", jsonEncode($productIds),(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
			case "set_tag":
				$productIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productIds[] = $row['primary_identifier'];
				}
				$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_id", $_POST['product_tag_id']);
				$count = 0;
				if (!empty($productIds) && !empty($productTagId)) {
					executeQuery("update products set time_changed = now() where client_id = ? and product_id in (" . implode(",", $productIds) . ")"
						. " and product_id not in (select product_id from product_tag_links where product_tag_id = ?)", $GLOBALS['gClientId'], $productTagId);
					$resultSet = executeQuery(sprintf("insert into product_tag_links (product_id,product_tag_id,start_date,expiration_date) select product_id,?,?,?" .
						" from products where client_id = ? and product_id in (%s) and product_id not in (select product_id from product_tag_links where product_tag_id = ?)",
						implode(",", $productIds)), $productTagId, (empty($_POST['start_date']) ? null : date("Y-m-d", strtotime($_POST['start_date']))),
						(empty($_POST['expiration_date']) ? null : date("Y-m-d", strtotime($_POST['expiration_date']))),
						$GLOBALS['gClientId'], $productTagId);
					$count = $resultSet['affected_rows'];
				}
				$returnArray['info_message'] = $count . " products tagged";
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,old_value,notes) values (?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'products', 'product_id', $count . " products tagged as " . getFieldFromId("description", "product_tags", "product_tag_id", $productTagId), jsonEncode($productIds),
					(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
			case "remove_tag":
				$productIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productIds[] = $row['primary_identifier'];
				}
				$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_id", $_POST['product_tag_id']);
				$count = 0;
				if (!empty($productIds) && !empty($productTagId)) {
					executeQuery("update products set time_changed = now() where product_id in (select product_id from product_tag_links where product_tag_id = ?"
						. " and product_id in (" . implode(",", $productIds) . "))", $productTagId);
					$resultSet = executeQuery("delete from product_tag_links where product_tag_id = ? and product_id in (" . implode(",", $productIds) . ")", $productTagId);
					$count = $resultSet['affected_rows'];
				}
				$returnArray['info_message'] = $count . " products untagged";
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,notes) values (?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'product_tag_links', 'product_id', $count . " products removed from " . getFieldFromId("description", "product_tags", "product_tag_id", $_POST['product_tag_id']),
					(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
			case "add_to_category":
				$productIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productIds[] = $row['primary_identifier'];
				}
				$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $_POST['product_category_id']);
				$count = 0;
				if (!empty($productIds) && !empty($productCategoryId)) {
					$resultSet = executeQuery("insert ignore into product_category_links (product_id,product_category_id) select product_id," . $productCategoryId .
						" from products where client_id = ? and product_id in (" . implode(",", $productIds) . ") and product_id not in (select product_id from product_category_links where product_category_id = ?)",
						$GLOBALS['gClientId'], $productCategoryId);
					$count = $resultSet['affected_rows'];
				}
				$returnArray['info_message'] = $count . " products added to category";
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,notes) values (?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'product_category_links', 'product_id', $count . " products added to " . getFieldFromId("description", "product_categories", "product_category_id", $_POST['product_category_id']),
					(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
			case "remove_from_category":
				$productIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productIds[] = $row['primary_identifier'];
				}
				$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $_POST['product_category_id']);
				$count = 0;
				if (!empty($productIds) && !empty($productCategoryId)) {
					$resultSet = executeQuery("delete from product_category_links where product_category_id = ? and product_id in (" . implode(",", $productIds) . ")", $productCategoryId);
					$count = $resultSet['affected_rows'];
				}
				$returnArray['info_message'] = $count . " products removed from category";
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,notes) values (?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'product_category_links', 'product_id', $count . " products removed from " . getFieldFromId("description", "product_categories", "product_category_id", $_POST['product_category_id']),
					(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
			case "remove_custom_field":
				$remove = true;
			case "set_custom_field":
				$productIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productIds[] = $row['primary_identifier'];
				}
				$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_id", $_POST['custom_field_id']);
				$count = 0;
				if (!empty($productIds) && !empty($customFieldId)) {
					if (!$remove) {
						executeQuery("update products set time_changed = now() where client_id = ? and product_id in (" . implode(",", $productIds) . ")"
							. " and product_id not in (select primary_identifier from custom_field_data where custom_field_id = ?)", $GLOBALS['gClientId'], $customFieldId);
						$resultSet = executeQuery("insert ignore into custom_field_data (primary_identifier, custom_field_id, text_data) " .
							"select product_id, ?, 1 from products where product_id in (" . implode(",", $productIds) . ")", $customFieldId);
					} else {
						executeQuery("update products set time_changed = now() where client_id = ? and product_id in (" . implode(",", $productIds) . ")"
							. " and product_id in (select primary_identifier from custom_field_data where custom_field_id = ?)", $GLOBALS['gClientId'], $customFieldId);
						$resultSet = executeQuery("delete from custom_field_data where custom_field_id = ? and primary_identifier in (" . implode(",", $productIds) . ")",
							$customFieldId);
					}
					$count = $resultSet['affected_rows'];
				}
				$returnArray['info_message'] = "Custom field " . ($remove ? " cleared from " : " set on ") . $count . " products";
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,old_value,notes) values (?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'products', 'product_id', "Custom Field " . getFieldFromId("description", "custom_fields", "custom_field_id", $customFieldId) .
					($remove ? " cleared from " : " set on ") . $count . " products", jsonEncode($productIds),(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
			case "set_link_name":
				$returnArray = DataTable::setLinkNames("products");
				ajaxResponse($returnArray);
				break;
			case "tag_missing_information":
				$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "MISSING_INFORMATION");
				executeQuery("delete from product_tag_links where product_tag_id = ?", $productTagId);
				executeQuery("insert into product_tag_links (product_id,product_tag_id) select product_id,? from products where product_manufacturer_id is null and client_id = ?", $productTagId, $GLOBALS['gClientId']);
				executeQuery("insert into product_tag_links (product_id,product_tag_id) select product_id,? from products where image_id is null and client_id = ?", $productTagId, $GLOBALS['gClientId']);
				executeQuery("insert into product_tag_links (product_id,product_tag_id) select product_id,? from products where link_name is null and client_id = ?", $productTagId, $GLOBALS['gClientId']);
				executeQuery("insert into product_tag_links (product_id,product_tag_id) select product_id,? from products where (base_cost is null or base_cost < 1) and client_id = ?", $productTagId, $GLOBALS['gClientId']);
				executeQuery("insert into product_tag_links (product_id,product_tag_id) select product_id,? from product_data where (upc_code is null or length(upc_code) < 10 or upc_code > '9' or upc_code < '0') and client_id = ?", $productTagId, $GLOBALS['gClientId']);
				executeQuery("insert into product_tag_links (product_id,product_tag_id) select product_id,? from product_data where manufacturer_sku is null and client_id = ?", $productTagId, $GLOBALS['gClientId']);
				executeQuery("insert into product_tag_links (product_id,product_tag_id) select product_id,? from products join product_data using (product_id) where manufacturer_advertised_price is not null and list_price is not null and manufacturer_advertised_price > (list_price * 1.5) and products.client_id = ?", $productTagId, $GLOBALS['gClientId']);
				executeQuery("insert into product_tag_links (product_id,product_tag_id) select product_id,? from products where not exists (select product_id from product_category_links where product_id = products.product_id) and client_id = ?", $productTagId, $GLOBALS['gClientId']);
				ajaxResponse($returnArray);
				break;
			case "set_inactive":
				$inactiveValue = true;
			case "clear_inactive":
				$returnArray = DataTable::setInactive("products", $inactiveValue);
				ajaxResponse($returnArray);
				break;
			case "remove_sale_prices":
				$productIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productIds[] = $row['primary_identifier'];
				}
				$count = 0;
				$salePriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_code", "sale_price");
				if (!empty($productIds) && !empty($salePriceTypeId) && !empty($_POST['location_id'])) {
					$locationWhere = $_POST['location_id'] == "any" ? "location_id is null" : "location_id = " . $_POST['location_id'];
					$locationText = $_POST['location_id'] == "any" ? "[Any]" : getFieldFromId("description", "locations", "location_id", $_POST['location_id']);
					executeQuery("update products set time_changed = now() where client_id = ? and product_id in (" . implode(",", $productIds) . ")"
						. " and product_id in (select product_id from product_prices where product_price_type_id = ? and " . $locationWhere . ")", $GLOBALS['gClientId'], $salePriceTypeId);
					$resultSet = executeQuery("delete from product_prices where product_price_type_id = ? and " . $locationWhere .
						" and product_id in (" . implode(",", $productIds) . ")", $salePriceTypeId);
					$count = $resultSet['affected_rows'];
					executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
					executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,old_value,notes) values (?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
						'products', 'product_id', "Set Sale prices for location " . $locationText . " removed from " . $count . " products", jsonEncode($productIds),
						(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				}
				$returnArray['info_message'] = "Set sale prices removed from " . $count . " products";
				ajaxResponse($returnArray);
				break;
		}
	}

	function filterProduct($row) {
		return true;
	}

	function filterTextProcessing($filterText) {
		if (is_numeric($filterText)) {
			$whereStatement = "product_code = " . makeParameter($filterText) . " or products.product_id = '" . $filterText . "'" .
				" or products.product_id in (select product_id from product_data where upc_code = '" . ProductCatalog::makeValidUPC($filterText) . "')" .
				" or products.product_id in (select product_id from product_data where isbn = '" . ProductCatalog::makeValidISBN($filterText) . "')" .
				" or products.product_id in (select product_id from product_data where isbn_13 = '" . ProductCatalog::makeValidISBN13($filterText) . "')";
			$this->iDataSource->addFilterWhere($whereStatement);
			$this->iDataSource->setFilterFunction(array("object" => $this, "method" => "filterProduct"));
		} else {
			if (!empty($filterText)) {
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
					$this->iDataSource->addFilterWhere($whereStatement);
				} else {
					$this->iDataSource->addFilterWhere("products.description like " . makeParameter($filterText . "%"));
				}
			}
		}
	}

	function dataListProcessing(&$dataList) {
		$columnList = getPreference("MAINTENANCE_LIST_COLUMNS", $GLOBALS['gPageCode']);
		$columnList .= getPreference("MAINTENANCE_EXPORT_COLUMNS", $GLOBALS['gPageCode']);
		if (strpos($columnList, "displayed_sale_price") !== false || strpos($columnList, "sale_price") !== false) {
			$productCatalog = new ProductCatalog();
			foreach ($dataList as $index => $row) {
				$salePriceInfo = $productCatalog->getProductSalePrice($row['product_id'], array("product_information" => $row, "no_stored_prices" => true, "contact_type_id" => "", "user_type_id" => "", "ignore_map" => true, "single_product" => true));
				$salePrice = $salePriceInfo['sale_price'];

				$dataList[$index]['sale_price'] = $salePrice;
				if (empty($row['manufacturer_advertised_price'])) {
					$dataList[$index]['displayed_sale_price'] = $salePrice;
				} else {
					$dataList[$index]['displayed_sale_price'] = $row['manufacturer_advertised_price'];
				}
			}
		}
		if (strpos($columnList, "image_url") !== false || strpos($columnList, "image_url") !== false) {
			foreach ($dataList as $index => $row) {
				$imageUrl = ProductCatalog::getProductImage($row['product_id']);
				if (!empty($imageUrl) && $imageUrl != "/images/empty.jpg") {
					if (substr($imageUrl, 0, 4) != "http") {
						$imageUrl = getDomainName() . $imageUrl;
					}
					$dataList[$index]['image_url'] = $imageUrl;
				}
			}
		}
		if ($GLOBALS['gClientRow']['client_code'] == "COREWARE_SHOOTING_SPORTS") {
			foreach ($dataList as $index => $row) {
				$dataList[$index]['missing_information'] = $this->getMissingInformation($row['product_id'], $row);
			}
		}
	}

	function getMissingInformation($productId, $productRow) {
		if (array_key_exists("primary_id", $productRow) && array_key_exists("data_value", $productRow['primary_id'])) {
			$row = array();
			foreach ($productRow as $index => $dataValues) {
				if (is_array($dataValues)) {
					$row[$index] = $dataValues['data_value'];
				} else {
					$row[$index] = $dataValues;
				}
			}
			$productRow = $row;
		}
		$missingInformation = "";
		if (empty($productRow['product_manufacturer_id'])) {
			$missingInformation .= (empty($missingInformation) ? "" : ", ") . "Product Manufacturer";
		}
		if (empty($productRow['image_id'])) {
			$missingInformation .= (empty($missingInformation) ? "" : ", ") . "Image";
		}
		if (empty($productRow['link_name'])) {
			$missingInformation .= (empty($missingInformation) ? "" : ", ") . "Link Name";
		}
		if (empty($productRow['upc_code'])) {
			$missingInformation .= (empty($missingInformation) ? "" : ", ") . "UPC";
		} elseif (strlen($productRow['upc_code']) < 10 || substr($productRow['upc_code'], 0, 1) > "9" || substr($productRow['upc_code'], 0, 1) < "0") {
			$missingInformation .= (empty($missingInformation) ? "" : ", ") . "Improper UPC";
		}
		if (empty($productRow['manufacturer_sku'])) {
			$missingInformation .= (empty($missingInformation) ? "" : ", ") . "Manufacturer SKU";
		}
		if (empty($productRow['base_cost']) || $productRow['base_cost'] < 1) {
			$missingInformation .= (empty($missingInformation) ? "" : ", ") . "Low or missing cost";
		}
		if (empty($productRow['product_category_list']) && empty($productRow['product_categories'])) {
			$missingInformation .= (empty($missingInformation) ? "" : ", ") . "Category";
		}
		if (!empty($productRow['manufacturer_advertised_price']) && !empty($productRow['list_price']) && $productRow['manufacturer_advertised_price'] > ($productRow['list_price'] * 1.5)) {
			$missingInformation .= (empty($missingInformation) ? "" : ", ") . "Excessively High MAP";
		}
		if (!empty($productRow['image_id'])) {
			$imageCount = getFieldFromId("count(*)", "products", "image_id", $productRow['image_id'], "product_id <> ?", $productId);
			if ($imageCount > 0) {
				$missingInformation .= (empty($missingInformation) ? "" : ", ") . "Image used by " . $imageCount . " other products";
			}
		}

		return $missingInformation;
	}

	function showRelatedProducts() {
		$relatedProductsCount = getCachedData("max_related_products", "max_related_products");
		if (empty($relatedProductsCount)) {
			$resultSet = executeQuery("select product_id,count(*) from related_products where product_id in (select product_id from products where client_id = ?) group by product_id order by count(*) desc", $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				$relatedProductsCount = $row['count(*)'];
			}
			setCachedData("max_related_products", "max_related_products", $relatedProductsCount, 168);
		}
		return ($relatedProductsCount < 200);
	}

	function mainContent() {
		if (!empty(getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0")) && ($_GET['url_page'] == "list" || empty($_GET['url_page']))) {
			echo createFormControl("product_data", "upc_code", $additionalControls = array("column_name" => "import_upc_code", "form_label" => "Import UPC", "help_label" => "Enter a UPC. If it is in the Coreware catalog, it will be imported", "not_null" => false));
		}
		return false;
	}

	function productAddonSets($inactive = false) {
		$setChoices = array();
		$resultSet = executeQuery("select * from product_addon_sets where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$setChoices[$row['product_addon_set_id']] = array("key_value" => $row['product_addon_set_id'], "description" => $row['description'], "inactive" => false);
		}
		freeResult($resultSet);
		return $setChoices;
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("product_restrictions", "list_table_controls", array("state" => array("data_type" => "select", "choices" => getStateArray())));
		$this->iDataSource->addColumnControl("map_expiration_date", "data_type", "hidden");
		$this->iDataSource->addColumnControl("product_id_display", "data_type", "int");
		$this->iDataSource->addColumnControl("product_id_display", "readonly", true);
		$this->iDataSource->addColumnControl("product_id_display", "form_label", "Product ID");

		$this->iDataSource->addColumnControl("date_created", "default_value", date("m/d/Y"));
		$this->iDataSource->addColumnControl("date_created", "readonly", true);
		$this->iDataSource->addColumnControl("link_name", "classes", "url-link");
		$this->iDataSource->addColumnControl("list_price", "minimum_value", "0");
		$this->iDataSource->addColumnControl("product_categories", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("product_categories", "control_table", "product_categories");
		$this->iDataSource->addColumnControl("product_categories", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_categories", "form_label", "Categories");
		$this->iDataSource->addColumnControl("product_categories", "help_label", "Order determines pricing structure, so put primary category at top");
		$this->iDataSource->addColumnControl("product_categories", "links_table", "product_category_links");
		$this->iDataSource->addColumnControl("product_facet_values", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("product_facet_values", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_facet_values", "form_label", "Facets");
		$this->iDataSource->addColumnControl("product_facet_values", "list_table", "product_facet_values");
		$this->iDataSource->addColumnControl("product_images", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("product_images", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_images", "form_label", "Alternate Images");
		$this->iDataSource->addColumnControl("product_images", "list_table", "product_images");
		$this->iDataSource->addColumnControl("product_tag_links", "column_list", "product_tag_id,start_date,expiration_date");
		$this->iDataSource->addColumnControl("product_tag_links", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("product_tag_links", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_tag_links", "form_label", "Tags");
		$this->iDataSource->addColumnControl("product_tag_links", "list_table", "product_tag_links");
		$this->iDataSource->addColumnControl("time_changed", "default_value", date("m/d/Y g:i:sa"));
		$this->iDataSource->addColumnControl("time_changed", "readonly", true);
		$this->iDataSource->addColumnControl("time_changed", "size", "22");
		$this->iDataSource->addColumnControl("weight", "minimum_value", "0");
		$this->iDataSource->addColumnControl("length", "minimum_value", "0");
		$this->iDataSource->addColumnControl("width", "minimum_value", "0");
		$this->iDataSource->addColumnControl("height", "minimum_value", "0");

		$this->iDataSource->addColumnControl("custom_product", "data_type", "hidden");
		$this->iDataSource->addColumnControl("tax_rate_id", "empty_text", "[Use Default]");
		$this->iDataSource->addColumnControl("points_multiplier", "minimum_value", "0");

		$this->iDataSource->addColumnControl("pricing_structure_id", "empty_text", "[Use Default]");

		if ($GLOBALS['gClientRow']['client_code'] == "COREWARE_SHOOTING_SPORTS") {
			$this->iDataSource->addColumnControl("missing_information", "data_type", "varchar");
			$this->iDataSource->addColumnControl("missing_information", "form_label", "Missing Information");
			$this->iDataSource->addColumnControl("missing_information", "not_sortable", true);
			$this->iDataSource->addColumnControl("missing_information", "row_classes", "red-text");
		}

		$this->iDataSource->addColumnControl("product_addon_set_id", "data_type", "select");
		$this->iDataSource->addColumnControl("product_addon_set_id", "form_label", "Load Addon Set");
		$this->iDataSource->addColumnControl("product_addon_set_id", "empty_text", "[Select to Load]");
		$this->iDataSource->addColumnControl("product_addon_set_id", "get_choices", "productAddonSets");
		$this->iDataSource->addColumnControl("product_addon_set_id", "not_sortable", true);

		$this->iDataSource->addColumnControl("date_created", "form_line_classes", "inline-block");
		$this->iDataSource->addColumnControl("expiration_date", "form_line_classes", "inline-block");
		$this->iDataSource->addColumnControl("time_changed", "form_line_classes", "inline-block");
		$this->iDataSource->addColumnControl("full_name", "form_line_classes", "inline-block");
		$this->iDataSource->addColumnControl("contributor_type_id", "form_line_classes", "inline-block");

		$this->iDataSource->addColumnControl("product_addons", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_addons", "control_class", "FormList");
		$this->iDataSource->addColumnControl("product_addons", "list_table", "product_addons");
		$this->iDataSource->addColumnControl("product_addons", "form_label", "Product Addons");
		$this->iDataSource->addColumnControl("product_addons", "help_label", "Customer can choose or decline to add these to the product");
		$this->iDataSource->addColumnControl("product_addons", "list_table_controls",
			array("sale_price" => array("inline-width" => "100px"), "sort_order" => array("inline-width" => "60px"), "maximum_quantity" => array("form_label" => "Maximum Quantity", "minimum_value" => "1"),
				"image_id" => array("data_type" => "image_input", "no_remove" => true), "form_definition_id" => array("help_label" => "Form used to generate the addon"),
				"inventory_product_id" => array("data_type" => "autocomplete", "data-autocomplete_tag" => "products")));

		$this->iDataSource->addColumnControl("calculation_log", "data_type", "text");
		$this->iDataSource->addColumnControl("calculation_log", "form_label", "Calculation Log");
		$this->iDataSource->addColumnControl("calculation_log", "readonly", true);

		$this->iDataSource->addColumnControl("sale_price", "data_type", "decimal");
		$this->iDataSource->addColumnControl("sale_price", "decimal_places", "2");
		$this->iDataSource->addColumnControl("sale_price", "readonly", true);
		$this->iDataSource->addColumnControl("sale_price", "form_label", "Discounted Sale Price");
		$this->iDataSource->addColumnControl("sale_price", "help_label", "Different than displayed sale price if the displayed sale price is MAP");

		$this->iDataSource->addColumnControl("displayed_sale_price", "data_type", "decimal");
		$this->iDataSource->addColumnControl("displayed_sale_price", "decimal_places", "2");
		$this->iDataSource->addColumnControl("displayed_sale_price", "readonly", true);
		$this->iDataSource->addColumnControl("displayed_sale_price", "form_label", "Displayed Sale Price");
		$this->iDataSource->addColumnControl("displayed_sale_price", "not_sortable", true);

		$this->iDataSource->addColumnControl("image_url", "data_type", "varchar");
		$this->iDataSource->addColumnControl("image_url", "readonly", true);
		$this->iDataSource->addColumnControl("image_url", "form_label", "Image URL");
		$this->iDataSource->addColumnControl("image_url", "not_sortable", true);

		$this->iDataSource->addColumnControl("product_inventory_notifications", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_inventory_notifications", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("product_inventory_notifications", "list_table", "product_inventory_notifications");
		$this->iDataSource->addColumnControl("product_inventory_notifications", "form_label", "Inventory Notifications");
		$this->iDataSource->addColumnControl("product_inventory_notifications", "help_label", "Notify when inventory reaches these levels");
		$this->iDataSource->addColumnControl("product_inventory_notifications", "column_list", "email_address,product_distributor_id,comparator,quantity,place_order,location_id,order_quantity,use_lowest_price,allow_multiple,inactive");
		$this->iDataSource->addColumnControl("product_inventory_notifications", "list_table_controls", array("quantity" => array("inline-width" => "80px"),
			"order_quantity" => array("minimum_value" => "1", "not_null" => true, "data-conditional-required" => "$(this).closest(\"tr\").find(\".place-order\").prop(\"checked\")", "form_label" => "Order<br>Quantity", "inline-width" => "80px"),
			"user_id" => array("default_value" => $GLOBALS['gUserId']), "product_distributor_id" => array("inline-width" => "150px", "empty_text" => "[Total of all]"),
			"place_order" => array("classes" => "place-order", "form_label" => "Place<br>Order"), "allow_multiple" => array("form_label" => "Allow<br>Multiple"), "email_address" => array("inline-width" => "150px"),
			"location_id" => array("empty_text" => "[Any]", "form_label" => "Order<br>Location", "get_choices" => "locationChoices"), "use_lowest_price" => array("form_label" => "Order Location", "data_type" => "select", "default_value" => "1", "choices" => array("1" => "Lowest Price", "Location Order")),
			"comparator" => array("data_type" => "select", "inline-width" => "150px", "choices" => array("<" => "Less than", "<=" => "Less than equal", "=>" => "Greater than equal", ">" => "Greater than", "=" => "Equal"))));

		$this->iDataSource->addColumnControl("user_group_id", "help_label", "Can only be purchased by members of this user group");
		$this->iDataSource->addColumnControl("error_message", "help_label", "Displayed when a customer, not in the user group, tries to purchase a product");

		$this->iDataSource->addColumnControl("product_category_list", "form_label", "Categories");
		$this->iDataSource->addColumnControl("product_category_list", "data_type", "varchar");
		$this->iDataSource->addColumnControl("product_category_list", "select_value", "select group_concat(product_category_code) from product_categories where product_category_id in (select product_category_id from product_category_links where product_id = products.product_id)");

		$this->iDataSource->addColumnLikeColumn("full_name", "contributors", "full_name");
		$this->iDataSource->addColumnControl("full_name", "not_null", false);
		$this->iDataSource->addColumnControl("full_name", "help_label", "Add a new contributor");
		$this->iDataSource->addColumnLikeColumn("contributor_type_id", "product_contributors", "contributor_type_id");
		$this->iDataSource->addColumnControl("contributor_type_id", "data-conditional-required", "(!empty($(\"#full_name\").val()))");
		$this->iDataSource->addColumnControl("contributor_type_id", "help_label", "Required if adding a new contributor");
		$this->iDataSource->addColumnControl("contributor_type_id", "no_required_label", true);

		$this->iDataSource->addColumnControl("distributor_product_codes", "data_type", "custom");
		$this->iDataSource->addColumnControl("distributor_product_codes", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("distributor_product_codes", "list_table", "distributor_product_codes");
		$this->iDataSource->addColumnControl("distributor_product_codes", "form_label", "Distributor Product Codes");
		if ($GLOBALS['gClientRow']['client_code'] != "COREWARE_SHOOTING_SPORTS") {
			$this->iDataSource->addColumnControl("distributor_product_codes", "list_table_controls", array("product_distributor_id" => array("readonly" => true), "product_code" => array("readonly" => true)));
			$this->iDataSource->addColumnControl("distributor_product_codes", "no_add", true);
		}

		$this->iDataSource->addColumnControl("product_sale_notifications", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_sale_notifications", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("product_sale_notifications", "list_table", "product_sale_notifications");
		$this->iDataSource->addColumnControl("product_sale_notifications", "form_label", "Sale Notifications");
		$this->iDataSource->addColumnControl("product_sale_notifications", "help_label", "Notify when one is sold. If maximum quantity has a value greater than one, email will not be sent unless total inventory quantity is at or below that value.");

		$this->iDataSource->addColumnControl("product_prices", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_prices", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("product_prices", "list_table", "product_prices");
		$this->iDataSource->addColumnControl("product_prices", "list_table_controls", array("location_id" => array("empty_text" => "[Any]")));
		$this->iDataSource->addColumnControl("product_prices", "form_label", "Product Prices");

		$this->iDataSource->addColumnControl("product_distributor_dropship_prohibitions", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_distributor_dropship_prohibitions", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("product_distributor_dropship_prohibitions", "links_table", "product_distributor_dropship_prohibitions");
		$this->iDataSource->addColumnControl("product_distributor_dropship_prohibitions", "form_label", "Distributors Who Cannot Dropship");
		$this->iDataSource->addColumnControl("product_distributor_dropship_prohibitions", "control_table", "product_distributors");

		$shippingMethodCount = 0;
		$resultSet = executeQuery("select count(*) from shipping_methods where client_id = ?", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$shippingMethodCount = $row['count(*)'];
		}
		if ($shippingMethodCount < 200) {
			$this->iDataSource->addColumnControl("product_shipping_methods", "data_type", "custom");
			$this->iDataSource->addColumnControl("product_shipping_methods", "control_class", "MultipleSelect");
			$this->iDataSource->addColumnControl("product_shipping_methods", "links_table", "product_shipping_methods");
			$this->iDataSource->addColumnControl("product_shipping_methods", "form_label", "Prohibited Shipping Methods");
			$this->iDataSource->addColumnControl("product_shipping_methods", "control_table", "shipping_methods");
		} else {
			$this->iDataSource->addColumnControl("product_shipping_methods", "data_type", "varchar");
			$this->iDataSource->addColumnControl("product_shipping_methods", "form_line_classes", "hidden");
		}

		$this->iDataSource->addColumnControl("product_shipping_carriers", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_shipping_carriers", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("product_shipping_carriers", "links_table", "product_shipping_carriers");
		$this->iDataSource->addColumnControl("product_shipping_carriers", "form_label", "Prohibited Shipping Carriers");
		$this->iDataSource->addColumnControl("product_shipping_carriers", "control_table", "shipping_carriers");

		$this->iDataSource->addColumnControl("detailed_description", "wysiwyg", true);
		$this->iDataSource->addColumnControl("detailed_description", "classes", "use-ck-editor");
		$this->iDataSource->addColumnControl("detailed_description", "no_editor", true);

		$this->iDataSource->addColumnControl("image_id", "data_type", "image_input");
		$this->iDataSource->setJoinTable("product_data", "product_id", "product_id", true);
		$this->iDataSource->getPrimaryTable()->setSubtables(array("product_data", "product_sale_prices", "product_category_links", "product_change_details", "product_restrictions", "product_sale_notifications",
			"product_contributors", "product_inventories", "product_reviews", "product_vendors", "product_search_word_values", "product_payment_methods", "product_prices",
			"product_shipping_methods", "product_tag_links", "product_facet_values", "distributor_product_codes", "product_serial_numbers", "product_custom_fields",
			"product_images", "product_videos", "product_addons", "quotations", "related_products", "shopping_cart_items", "wish_list_items", "product_remote_images", "product_group_variants", "product_view_log", "product_inventory_notifications"));
		$this->iDataSource->addColumnControl("product_id", "exact_search", true);

		$this->iDataSource->addColumnControl("product_code", "exact_search", true);
		$this->iDataSource->addColumnControl("product_code", "classes", "allow-dash");

		$this->iDataSource->addColumnControl("product_facet_values", "list_table_controls",
			array("product_facet_id" => array("classes" => "product-facet-id"),
				"product_facet_option_id" => array("classes" => "product-facet-option-id", "data-additional_filter_function" => "productFacetOptionFilter")));

		$this->iDataSource->addColumnControl("product_videos", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_videos", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("product_videos", "form_label", "Videos");
		$this->iDataSource->addColumnControl("product_videos", "list_table", "product_videos");

		$this->iDataSource->addColumnControl("upc_code", "exact_search", true);
		$this->iDataSource->addColumnControl("product_images", "list_table_controls", array("description" => array("classes" => "image-description"), "image_id" => array("data_type" => "image_input")));
		if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
			executeQuery("delete from user_preferences where user_id = ? and preference_qualifier = ? and preference_id in " .
				"(select preference_id from preferences where preference_code in ('MAINTENANCE_FILTER_COLUMN','MAINTENANCE_FILTER_TEXT','MAINTENANCE_SET_FILTERS'))", $GLOBALS['gUserId'], $GLOBALS['gPageCode']);
			$productId = getFieldFromId("product_id", "products", "product_id", $_GET['primary_id'], "client_id is not null");
			if (empty($productId)) {
				return;
			}
			$resultSet = executeQuery("select * from products where product_id = ?", $productId);
			$productRow = getNextRow($resultSet);
			$originalProductCode = $productRow['product_code'];
			$originalLinkName = $productRow['link_name'];
			$subNumber = 1;
			$queryString = "";
			foreach ($productRow as $fieldName => $fieldData) {
				if (empty($queryString)) {
					$productRow[$fieldName] = "";
				}
				if ($fieldName == "client_id") {
					$productRow[$fieldName] = $GLOBALS['gClientId'];
				}
				$queryString .= (empty($queryString) ? "" : ",") . "?";
			}
			$newProductId = "";
			$productRow['description'] .= " Copy";
			while (empty($newProductId)) {
				$productRow['product_code'] = $originalProductCode . "_" . $subNumber;
				$productRow['link_name'] = $originalLinkName . (empty($originalLinkName) ? "" : "-" . $subNumber);
				$productRow['date_created'] = date("Y-m-d");
				$productRow['time_changed'] = date("Y-m-d H:i:s");
				$productRow['reindex'] = "1";
				$resultSet = executeQuery("select * from products where product_code = ? or (link_name is not null and link_name = ?)",
					$productRow['product_code'], $productRow['link_name']);
				if ($row = getNextRow($resultSet)) {
					$subNumber++;
					$productRow['link_name'] = $originalLinkName . (empty($originalLinkName) ? "" : "-" . $subNumber);
					continue;
				}
				$resultSet = executeQuery("insert into products values (" . $queryString . ")", $productRow);
				if ($resultSet['sql_error_number'] == 1062) {
					$subNumber++;
					continue;
				}
				$newProductId = $resultSet['insert_id'];
			}
			$_GET['primary_id'] = $newProductId;
			setUserPreference("MAINTENANCE_FILTER_TEXT", $newProductId, $GLOBALS['gPageCode']);

			$subTables = array("ffl_product_restrictions", "product_category_links", "product_contributors", "product_custom_fields", "product_data", "product_facet_values",
				"product_images", "product_pack_contents", "product_payment_methods", "product_prices", "product_restrictions", "product_sale_notifications", "product_shipping_methods",
				"product_tag_links", "product_vendors", "related_products", "vendor_products", "product_addons", "product_bulk_packs");
			foreach ($subTables as $tableName) {
				$resultSet = executeQuery("select * from " . $tableName . " where product_id = ?", $productId);
				while ($row = getNextRow($resultSet)) {
					$queryString = "";
					foreach ($row as $fieldName => $fieldData) {
						if (empty($queryString) || $fieldName == "upc_code") {
							$row[$fieldName] = "";
						}
						$queryString .= (empty($queryString) ? "" : ",") . "?";
					}
					$row['product_id'] = $newProductId;
					executeQuery("insert into " . $tableName . " values (" . $queryString . ")", $row);
				}
			}
		}
		$resultSet = executeQuery("select * from contributor_types where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$this->iDataSource->addColumnControl("contributor_type_id_" . $row['contributor_type_id'], "data_type", "varchar");
			$this->iDataSource->addColumnControl("contributor_type_id_" . $row['contributor_type_id'], "form_label", $row['description']);
			$this->iDataSource->addColumnControl("contributor_type_id_" . $row['contributor_type_id'], "select_value", "select group_concat(full_name) from contributors join product_contributors using (contributor_id) where product_id = products.product_id and contributor_type_id = " . $row['contributor_type_id'] . " group by product_id");
		}

		$customFields = CustomField::getCustomFields("products");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);

			$dataType = $customField->getColumn()->getControlValue("data_type");
			switch ($dataType) {
				case "date":
					$fieldName = "date_data";
					break;
				case "bigint":
				case "int":
					$fieldName = "integer_data";
					break;
				case "decimal":
					$fieldName = "number_data";
					break;
				case "image":
				case "image_input":
				case "file":
				case "custom":
				case "custom_control":
					$fieldName = "";
					break;
				default:
					$fieldName = "text_data";
					break;
			}
			if (empty($fieldName)) {
				continue;
			}

			$this->iDataSource->addColumnControl($customField->getColumn()->getControlValue("column_name"), "select_value",
				"select " . $fieldName . " from custom_field_data where primary_identifier = products.product_id and custom_field_id = " . $thisCustomField['custom_field_id']);
			$this->iDataSource->addColumnControl($customField->getColumn()->getControlValue("column_name"), "data_type", $dataType);
			$this->iDataSource->addColumnControl($customField->getColumn()->getControlValue("column_name"), "form_label", $customField->getColumn()->getControlValue("form_label"));
		}
	}

	function locationChoices($showInactive = false) {
		$locationChoices = array();
		$resultSet = executeQuery("select * from locations where " . (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_location = 1 and user_id = " . $GLOBALS['gUserId'] : "user_location = 0") .
			" and inactive = 0 and product_distributor_id is not null and primary_location = 1 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$locationChoices[$row['location_id']] = array("key_value" => $row['location_id'], "description" => $row['description'], "inactive" => false);
		}
		freeResult($resultSet);
		return $locationChoices;
	}

	function onLoadJavascript() {
		$taxjarCustomFieldId = CustomField::getCustomFieldIdFromCode("TAXJAR_PRODUCT_CATEGORY_CODE", "PRODUCTS");
		?>
        <script>
            $(document).on("click", "#non_inventory_item", function () {
                if ($("#non_inventory_item").prop("checked")) {
                    $("#product_inventory").addClass("hidden");
                } else {
                    $("#product_inventory").removeClass("hidden");
                }
                return true;
            });
            $(document).on("change", "#location_id", function () {
                if (empty($(this).val())) {
                    $(".any-order-location").removeClass("hidden");
                } else {
                    $(".any-order-location").addClass("hidden");
                }
            });
            $(document).on("click", "#place_order", function () {
                if ($("#place_order").prop("checked")) {
                    $(".place-order").removeClass("hidden");
                    $("#location_id").trigger("change");
                } else {
                    $(".place-order").addClass("hidden");
                }
            });
            $(document).on("click", ".product-inventory-update", function (event) {
                event.stopPropagation();
                return true;
            });
            $(document).on("change", "#product_addon_set_id", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_product_addons&product_addon_set_id=" + encodeURIComponent($(this).val()), function (returnArray) {
                    if ("product_addons" in returnArray) {
                        for (const i in returnArray['product_addons']) {
                            addFormListRow("product_addons", returnArray['product_addons'][i]);
                        }
                        $("#product_addon_set_id").val("");
                    }
                });
                return false;
            });
            $(document).on("click", "#load_all_facets", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_facets&product_category_ids=" + encodeURIComponent($("#product_categories").val()), function (returnArray) {
                    if ("product_facet_ids" in returnArray) {
                        for (var i in returnArray['product_facet_ids']) {
                            let foundFacet = false;
                            $("#_product_facet_values_row").find(".product-facet-id").each(function () {
                                if ($("#product_categories").val() == i) {
                                    foundFacet = true;
                                    return false;
                                }
                            });
                            if (!foundFacet) {
                                addEditableListRow("product_facet_values", { product_facet_id: { data_value: i } });
                            }
                        }
                    }
                });
                return false;
            });
            $("#product_categories").change(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_taxonomy&product_category_ids=" + encodeURIComponent($(this).val()), function (returnArray) {
                    if ("taxonomy" in returnArray) {
                        $("#_product_taxonomy").html("<table id='_product_taxonomy_table' class='grid-table'><tr><th>Category</th><th>Category Groups</th><th>Departments</th></tr></table>");
                        returnArray['taxonomy'].forEach(function (row) {
                            $("#_product_taxonomy_table > tbody").append("<tr><td>" + row.product_category + "</td><td>" + row.product_category_groups + "</td><td>" + row.product_departments + "</td></tr>");
                        });
                    }
                });
            });
			<?php
			if ($GLOBALS['gPermissionLevel'] > _READONLY) {
			?>
            $("#taxjar_category_filter").keyup(function (event) {
                const textFilter = $(this).val().toLowerCase();
                if (empty(textFilter)) {
                    $("td.taxjar-category").removeClass("hidden");
                } else {
                    $("tr.taxjar-category").each(function () {
                        const description = $(this).text().toLowerCase();
                        if (description.indexOf(textFilter) >= 0) {
                            $(this).removeClass("hidden");
                        } else {
                            $(this).addClass("hidden");
                        }
                    });
                }
            });
            $(document).on("click", "#taxjar_categories", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_taxjar_categories", function (returnArray) {
                    if ("taxjar_category_list" in returnArray) {
                        $("#taxjar_category_list").html(returnArray['taxjar_category_list']);
                        $('#_taxjar_categories_dialog').dialog({
                            closeOnEscape: true,
                            draggable: false,
                            modal: true,
                            resizable: false,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 1000,
                            title: 'TaxJar Categories',
                            buttons: {
                                Close: function (event) {
                                    $("#_taxjar_categories_dialog").dialog('close');
                                }
                            }
                        });
                    }
                });
            });
            $(document).on("click", ".taxjar-category", function () {
                const taxCode = $(this).data("taxjar_product_tax_code");
                $("#_taxjar_categories_dialog").dialog('close');
                $("#custom_field_id_<?= $taxjarCustomFieldId ?>").val(taxCode).focus();
            })
            $(document).on("click", "#_view_site_button", function () {
                if (!empty($("#primary_id").val())) {
                    window.open("/product-details?id=" + $("#primary_id").val());
                }
                return false;
            });
            $(document).on("tap click", "#_duplicate_button", function () {
                if (!empty($("#primary_id").val())) {
                    if (changesMade()) {
                        askAboutChanges(function () {
                            $('body').data('just_saved', 'true');
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $("#primary_id").val();
                        });
                    } else {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $("#primary_id").val();
                    }
                }
                return false;
            });
            $(document).on("tap click", "#_research_button", function () {
                let itemCode = $("#upc_code").val();
                if (empty(itemCode)) {
                    itemCode = $("#isbn").val();
                }
                if (empty(itemCode)) {
                    itemCode = $("#isbn_13").val();
                }
                if (empty(itemCode)) {
                    itemCode = $("#manufacturer_sku").val();
                    if (!empty($("#product_manufacturer_id").val())) {
                        itemCode += " " + $("#product_manufacturer_id option:selected").text();
                    }
                }
                const searchString = encodeURI(itemCode);
                window.open("https://www.google.com/search?q=" + searchString);
                return false;
            });
			<?php } ?>
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
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_upc_code&upc_code=" + encodeURIComponent(upcCode), function (returnArray) {
                    $("#_filter_text").val(upcCode);
                    $("#_search_button").data("show_all", "");
                    $("#_search_button").trigger("click");
                    $("#import_upc_code").val("");
                });
            });
            $(document).on("change", "#_product_images_row .image-picker-selector", function () {
                if (empty($(this).closest("tr").find(".image-description").val()) && !empty($(this).val())) {
                    $(this).closest("tr").find(".image-description").val($(this).find("option:selected").text());
                }
            });
            $(document).on("click", ".product-inventory", function () {
                const productInventoryId = $(this).data("product_inventory_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_product_inventory_log&product_inventory_id=" + productInventoryId + "&product_id=" + $("#primary_id").val(), function (returnArray) {
                    if ("product_inventory_log" in returnArray) {
                        $("#product_inventory_log").html(returnArray['product_inventory_log']);
                        $('#_product_inventory_log_dialog').dialog({
                            closeOnEscape: true,
                            draggable: false,
                            modal: true,
                            resizable: false,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 800,
                            title: 'Product Inventory Log',
                            buttons: {
                                Close: function (event) {
                                    $("#_product_inventory_log_dialog").dialog('close');
                                }
                            }
                        });
                    }
                });
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function productFacetOptionFilter(fieldName) {
                return $("#" + fieldName).closest("tr").not(".autocomplete-field").find(".product-facet-id").val();
            }

            function beforeSaveChanges() {
                $("#_product_facet_values_row").find(".editable-list-data-row").each(function () {
                    if (empty($(this).find(".product-facet-option-id").val()) || empty($(this).find(".product-facet-id").val())) {
                        $(this).remove();
                    }
                });
                return true;
            }

            function customActions(actionName) {
                if (actionName === "add_inventory_notification") {
                    $('#_add_inventory_notification_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Add Inventory Notification',
                        buttons: {
                            Save: function (event) {
                                if ($("#_add_inventory_notification_form").validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=add_inventory_notification", $("#_add_inventory_notification_form").serialize(), function (returnArray) {
                                        getDataList();
                                    });
                                    $("#_add_inventory_notification_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_add_inventory_notification_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                if (actionName === "set_manufacturer") {
                    $('#_set_manufacturer_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Set Manufacturer',
                        buttons: {
                            Save: function (event) {
                                if ($("#_set_manufacturer_form").validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_manufacturer", $("#_set_manufacturer_form").serialize(), function (returnArray) {
                                        getDataList();
                                    });
                                    $("#_set_manufacturer_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_set_manufacturer_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                if (actionName === "set_tag") {
                    $("#set_tag_paragraph").html("Tag Selected Products");
                    $('#_set_tag_dialog .date-row').removeClass("hidden");
                    $('#_set_tag_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Set Tag',
                        buttons: {
                            Save: function (event) {
                                if ($("#_set_tag_form").validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_tag", $("#_set_tag_form").serialize(), function (returnArray) {
                                        getDataList();
                                    });
                                    $("#_set_tag_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_set_tag_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                if (actionName === "remove_tag") {
                    $("#set_tag_paragraph").html("Remove Tag from Selected Products");
                    $('#_set_tag_dialog .date-row').addClass("hidden");
                    $('#_set_tag_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Remove Tag',
                        buttons: {
                            Save: function (event) {
                                if ($("#_set_tag_form").validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_tag", $("#_set_tag_form").serialize(), function (returnArray) {
                                        getDataList();
                                    });
                                    $("#_set_tag_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_set_tag_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                if (actionName === "add_to_category") {
                    $('#_add_to_category_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Add To Category',
                        buttons: {
                            Save: function (event) {
                                if ($("#_add_to_category_form").validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=add_to_category", $("#_add_to_category_form").serialize(), function (returnArray) {
                                        getDataList();
                                    });
                                    $("#_add_to_category_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_add_to_category_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                if (actionName === "remove_from_category") {
                    $('#_remove_from_category_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Remove From Category',
                        buttons: {
                            Save: function (event) {
                                if ($("#_remove_from_category_form").validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_from_category", $("#_remove_from_category_form").serialize());
                                    $("#_remove_from_category_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_remove_from_category_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                if (actionName === "set_custom_field") {
                    $("#set_tag_paragraph").html("Set Custom Field on Selected Products");
                    $('#_set_custom_field_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Set Custom Field',
                        buttons: {
                            Save: function (event) {
                                if ($("#_set_custom_field_form").validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_custom_field", $("#_set_custom_field_form").serialize(), function (returnArray) {
                                        getDataList();
                                    });
                                    $("#_set_custom_field_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_set_custom_field_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                if (actionName === "remove_custom_field") {
                    $("#set_tag_paragraph").html("Remove Custom Field from Selected Products");
                    $('#_set_custom_field_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Set Custom Field',
                        buttons: {
                            Save: function (event) {
                                if ($("#_set_custom_field_form").validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_custom_field", $("#_set_custom_field_form").serialize(), function (returnArray) {
                                        getDataList();
                                    });
                                    $("#_set_custom_field_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_set_custom_field_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                if (actionName === "remove_sale_prices") {
                    $('#_remove_sale_prices_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Remove Sale Prices',
                        buttons: {
                            Save: function (event) {
                                if ($("#_remove_sale_prices_form").validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_sale_prices", $("#_remove_sale_prices_form").serialize(), function (returnArray) {
                                        getDataList();
                                    });
                                    $("#_remove_sale_prices_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_remove_sale_prices_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                if (actionName === "set_link_name" || actionName === "tag_missing_information" || actionName === "set_inactive" || actionName === "clear_inactive") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=" + actionName, function (returnArray) {
                        getDataList();
                    });
                    return true;
                }
                return false;
            }

            function afterGetRecord(returnArray) {
				<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                if (empty($("#primary_id").val())) {
                    disableButtons($("#_duplicate_button"));
                } else {
                    enableButtons($("#_duplicate_button"));
                }
				<?php } ?>
                if (empty(returnArray['non_inventory_item']['data_value'])) {
                    $("#product_inventory").removeClass("hidden");
                } else {
                    $("#product_inventory").addClass("hidden");
                }
                if (returnArray['use_ck_editor']) {
                    $("#_detailed_description_row").find("textarea").addClass("ck-editor");
                    addCKEditor();
                } else {
                    $("#_detailed_description_row").find(".content-builder").data("checked", "false");
                    $("#detailed_description").removeClass("wysiwyg");
                    CKEDITOR.instances["detailed_description"].destroy();
                }
            }
        </script>
		<?php
	}

	function beforeSaveChanges(&$nameValues) {
		$nameValues['upc_code'] = ProductCatalog::makeValidUPC(trim($nameValues['upc_code']));
		$nameValues['isbn'] = ProductCatalog::makeValidISBN($nameValues['isbn']);
		$nameValues['isbn_13'] = ProductCatalog::makeValidISBN13($nameValues['isbn_13']);
		if (!empty($nameValues['_product_addons_delete_ids'])) {
			$result = executeQuery("delete from shopping_cart_item_addons where product_addon_id in (" . $nameValues['_product_addons_delete_ids'] . ")");
		}
		$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "MISSING_INFORMATION");
		executeQuery("delete from product_tag_links where product_id = ? and product_tag_id = ?", $nameValues['primary_id'], $productTagId);
		if (empty($nameValues['primary_id']) && !empty($nameValues['manufacturer_advertised_price'])) {
			$nameValues['map_expiration_date'] = date("Y-m-d", strtotime("+ 6 months"));
		} elseif (!empty($nameValues['primary_id'])) {
			$manufacturerAdvertisedPrice = getFieldFromId("manufacturer_advertised_price", "product_data", "product_id", $nameValues['primary_id']);
			if ($nameValues['manufacturer_advertised_price'] != $manufacturerAdvertisedPrice) {
				$nameValues['map_expiration_date'] = date("Y-m-d", strtotime("+ 6 months"));
			}
		}
		return true;
	}

	function afterSaveDone($nameValues) {
		executeQuery("delete from product_remote_images where product_id = ? and product_id in (select product_id from products where image_id is not null and product_id = ?)",$nameValues['primary_id'],$nameValues['primary_id']);
		return true;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		removeCachedData("product_prices", $nameValues['primary_id']);
		$productPackContentId = getFieldFromId("product_pack_content_id", "product_pack_contents", "product_id", $nameValues['primary_id'], "contains_product_id = ?", $nameValues['primary_id']);
		if (!empty($productPackContentId)) {
			return "Pack cannot contain itself.";
		}
		if (empty($nameValues['non_inventory_item'])) {
			foreach ($nameValues as $fieldName => $fieldValue) {
				if (strlen($fieldValue) == 0 || !is_numeric($fieldValue)) {
					continue;
				}
				if (startsWith($fieldName, "set_inventory_")) {
					$productDistributorId = getFieldFromId("product_distributor_id", "product_distributors", "product_distributor_id", substr($fieldName, strlen("set_inventory_")));
					if (!empty($productDistributorId)) {
						ProductCatalog::getInventoryAdjustmentTypes();
						ProductDistributor::setPrimaryDistributorLocation();
						$resultSet = executeQuery("select * from product_inventories where product_id = ? and location_id = (select location_id from locations where client_id = ? and product_distributor_id = ? and primary_location = 1 order by primary_location desc,location_id limit 1)", $nameValues['primary_id'], $GLOBALS['gClientId'], $productDistributorId);

						if ($resultSet['row_count'] == 0) {
							$GLOBALS['gPrimaryDatabase']->logError(sprintf("Unable to set inventory for product ID %s, distributor ID %s: %s", $nameValues['primary_id'], $productDistributorId,
								(empty($resultSet['sql_error']) ? "No product inventory record found." : $resultSet['sql_error'])));
						}

						if ($row = getNextRow($resultSet)) {
							if ($row['quantity'] != $fieldValue) {
								executeQuery("update product_inventories set quantity = ? where product_inventory_id = ?", $fieldValue, $row['product_inventory_id']);
								executeQuery("insert into product_inventory_log (product_inventory_id, inventory_adjustment_type_id, user_id, log_time, quantity,notes) values " .
									"(?,?,?,now(),?,'Manual update in product maintenance')", $row['product_inventory_id'], $GLOBALS['gInventoryAdjustmentTypeId'], $GLOBALS['gUserId'], $fieldValue);
							}
						}
					}
				}
				if (startsWith($fieldName, "set_location_inventory_")) {
					$locationId = getFieldFromId("location_id", "locations", "location_id", substr($fieldName, strlen("set_location_inventory_")));
					if (!empty($locationId)) {
						ProductCatalog::getInventoryAdjustmentTypes();
						$resultSet = executeQuery("select * from product_inventories where product_id = ? and location_id = ?", $nameValues['primary_id'], $locationId);
						if ($resultSet['row_count'] == 0) {
							$insertSet = executeQuery("insert into product_inventories (product_id,location_id,quantity) values (?,?,?)",
								$nameValues['primary_id'], $locationId, $fieldValue);
							executeQuery("insert into product_inventory_log (product_inventory_id, inventory_adjustment_type_id, user_id, log_time, quantity,notes) values " .
								"(?,?,?,now(),?,'Manual update in product maintenance')", $insertSet['insert_id'], $GLOBALS['gInventoryAdjustmentTypeId'], $GLOBALS['gUserId'], $fieldValue);
						}
					}
				}
			}
		}

		$customFields = CustomField::getCustomFields("products");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if (!$customField->saveData($nameValues)) {
				return $customField->getErrorMessage();
			}
		}
		if (!empty($nameValues['full_name']) && !empty($nameValues['contributor_type_id'])) {
			$contributorId = getFieldFromId("contributor_id", "contributors", "full_name", $nameValues['full_name']);
			if (empty($contributorId)) {
				$insertSet = executeQuery("insert into contributors (client_id,full_name) values (?,?)", $GLOBALS['gClientId'], $nameValues['full_name']);
				$contributorId = $insertSet['insert_id'];
			}
			$productContributorId = getFieldFromId("product_contributor_id", "product_contributors", "product_id", $nameValues['primary_id'],
				"contributor_id = ? and contributor_type_id = ?", $contributorId, $nameValues['contributor_type_id']);
			if (empty($productContributorId)) {
				executeQuery("insert into product_contributors (product_id,contributor_id,contributor_type_id) values (?,?,?)",
					$nameValues['primary_id'], $contributorId, $nameValues['contributor_type_id']);
			}
		}
		removeCachedData("product_category_ids", $nameValues['primary_id']);
		removeCachedData("product_waiting_quantity", $nameValues['primary_id']);
		removeCachedData("base_cost", $nameValues['primary_id']);
		removeCachedData("*", $nameValues['primary_id']);
		if (empty($nameValues['base_cost'])) {
			ProductCatalog::calculateProductCost($nameValues['primary_id'], "Saved in Product Maintenance");
		} else {
			ProductCatalog::updateAllProductLocationCosts($nameValues['primary_id']);
		}
		ProductCatalog::createProductImageFiles($nameValues['primary_id']);
		ProductCatalog::calculateAllProductSalePrices($nameValues['primary_id']);
		executeQuery("update products set time_changed = now(),reindex = 1 where product_id = ?", $nameValues['primary_id']);

		ProductCatalog::reindexProducts($nameValues['primary_id']);

		removeCachedData("page_module-tagged_products", "*");
		removeCachedData("request_search_result", "*", true);
		removeCachedData("get_products_response", "*");
		return true;
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['use_ck_editor'] = (isHtml($returnArray['detailed_description']['data_value']) || empty($returnArray['detailed_description']['data_value']));
		$returnArray['product_id_display'] = array("data_value" => $returnArray['primary_id']['data_value']);
		if (!empty($returnArray['primary_id']['data_value'])) {
			ProductCatalog::calculateAllProductSalePrices($returnArray['primary_id']['data_value']);
		}
		removeCachedData("price::", $returnArray['primary_id']['data_value']);
        // 2023-09-26 This count is significantly hurting performance and doesn't seem to be used anywhere
//		$countQuery = "select count(*) from " . $this->iDataSource->getPrimaryTable()->getName();
//		$count = getCachedData("data_source_count", $countQuery);
//		if (!$count || $count < 1000) {
//			$resultSet = executeQuery($countQuery);
//			if ($row = getNextRow($resultSet)) {
//				$count = $row['count(*)'];
//			} else {
//				$count = 0;
//			}
//			freeResult($resultSet);
//			setCachedData("data_source_count", $countQuery, $count, .1);
//		}
		$returnArray['missing_information'] = array("data_value" => "");
		if ($GLOBALS['gClientRow']['client_code'] == "COREWARE_SHOOTING_SPORTS") {
			$missingInformation = $this->getMissingInformation($returnArray['primary_id']['data_value'], $returnArray);
			if (!empty($missingInformation)) {
				$returnArray['missing_information'] = array("data_value" => "Missing Information: " . $missingInformation);
			}
		}

		if (empty($returnArray['image_id']['data_value']) && !empty($returnArray['remote_identifier']['data_value'])) {
			$returnArray['image_message'] = array("data_value" => "Primary image comes from Coreware resource library. Add alternate images for different views. Adding a primary image will override the Coreware image.");
		} else {
			$returnArray['image_message'] = array("data_value" => "");
		}
		removeCachedData("full_product_content", $returnArray['primary_id']['data_value']);
		removeCachedData("base_cost", $returnArray['primary_id']['data_value']);
		removeCachedData("price::", $returnArray['primary_id']['data_value']);
		$manufacturerWebsite = getFieldFromId("web_page", "contacts", "contact_id", getFieldFromId("contact_id", "product_manufacturers", "product_manufacturer_id", $returnArray['product_manufacturer_id']['data_value']));
		if (empty($manufacturerWebsite)) {
			$returnArray['product_manufacturer_website'] = array("data_value" => "");
		} else {
			$returnArray['product_manufacturer_website'] = array("data_value" => "<a href='" . $manufacturerWebsite . "' target='_blank'>Manufacturer Website</a>");
		}
		$productCatalog = new ProductCatalog();
		$salePriceInfo = $productCatalog->getProductSalePrice($returnArray['primary_id']['data_value'], array("no_cache" => true, "no_stored_prices" => true, "contact_type_id" => "", "user_type_id" => "", "single_product" => true));
		$salePrice = $salePriceInfo['sale_price'];
		if ($salePrice === false) {
			$salePrice = "";
		} else {
			$salePrice = number_format($salePrice, 2, ".", ",");
		}
		$returnArray['displayed_sale_price'] = array("data_value" => $salePrice);

		$salePriceInfo = $productCatalog->getProductSalePrice($returnArray['primary_id']['data_value'], array("no_cache" => true, "no_stored_prices" => true, "contact_type_id" => "", "user_type_id" => "", "ignore_map" => true, "single_product" => true));
		$salePrice = $salePriceInfo['sale_price'];
		if ($salePrice === false) {
			$salePrice = "";
		} else {
			$salePrice = number_format($salePrice, 2, ".", ",");
		}
		$returnArray['sale_price'] = array("data_value" => $salePrice);
		$calculationLog = $salePriceInfo['calculation_log'] . "\n\n";
		$salePriceInfo = $productCatalog->getProductSalePrice($returnArray['primary_id']['data_value'], array("no_cache" => true, "no_stored_prices" => true, "contact_type_id" => "", "user_type_id" => "", "ignore_map" => true, "single_product" => true, "quantity" => 5));
		$calculationLog .= "For Quantity 5:\n" . $salePriceInfo['calculation_log'];
		$returnArray['calculation_log'] = array("data_value" => $calculationLog);

		$returnArray['full_name'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray['contributor_type_id'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray['upper_image'] = array("data_value" => "<img src='" . ProductCatalog::getProductImage($returnArray['primary_id']['data_value'], array("image_type" => "thumbnail", "alternate_image_type" => "small")) . "'>");

		$customFields = CustomField::getCustomFields("products");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldData = $customField->getRecord($returnArray['primary_id']['data_value']);
			if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldData)) {
				$returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'], $customFieldData['select_values']);
			}
			$returnArray = array_merge($returnArray, $customFieldData);
		}

		$waitingQuantity = ProductCatalog::getWaitingToShipQuantity($returnArray['primary_id']['data_value']);

		# Only show distributor inventory once and use distributor description instead of location

		ProductDistributor::setPrimaryDistributorLocation();

		$productInventory = (empty($waitingQuantity) ? "" : "<p>" . $waitingQuantity . " on order but not yet shipped.</p>");
		$productInventory .= "<p>Product inventory for kits is determined by the inventory levels of the contents of the kit.</p>";
		$productInventory .= "<table class='grid-table'><tr><th>Location</th><th>Quantity</th><th>Total Cost</th><th>Each</th><th>Set Inventory</th></tr>";
		$resultSet = executeQuery("select * from locations join product_inventories using (location_id) where inactive = 0 and " .
			"product_id = ? order by quantity desc", $returnArray['primary_id']['data_value']);
		$totalInventory = 0;
		$productDistributorIds = array();
		$locationIds = array();
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['product_distributor_id'])) {
				if (empty($row['primary_location']) || in_array($row['product_distributor_id'], $productDistributorIds)) {
					continue;
				}
				$productDistributorIds[] = $row['product_distributor_id'];
				$locationDescription = getReadFieldFromId("description", "product_distributors", "product_distributor_id", $row['product_distributor_id']);
			} else {
				$locationDescription = $row['description'];
			}
			$locationIds[] = $row['location_id'];
			$totalInventory += $row['quantity'];
//			$logSet = executeQuery("select * from product_inventory_log where product_inventory_id = ? and " .
//				"inventory_adjustment_type_id in (select inventory_adjustment_type_id from inventory_adjustment_types where " .
//				"inventory_adjustment_type_code in ('INVENTORY','RESTOCK') and client_id = ?) and quantity is not null and quantity > 0 and total_cost is not null order by product_inventory_log_id desc limit 1", $row['product_inventory_id'], $GLOBALS['gClientId']);
//			if (!$logRow = getNextRow($logSet)) {
//				$logRow = array();
//			}
            $totalCost = $row['quantity'] * $row['location_cost'];
			$productInventory .= "<tr data-product_inventory_id='" . $row['product_inventory_id'] . "' class='product-inventory'><td class='product-inventory-description'>" . $locationDescription . "</td><td class='align-right'>" . $row['quantity'] . "</td>" .
				"<td class='align-right'>" . (empty($totalCost) ? "" : number_format($totalCost, 2, ".", ",")) . "</td><td>" .
                (empty($row['location_cost']) ? "" : number_format(round($row['location_cost'], 2), 2, ".", ",")) . "</td>";
			if (!empty($row['product_distributor_id'])) {
				$productInventory .= "<td><input data-crc_value='" . getCrcValue("") . "' class='align-right validate[min[0],custom[integer]]' type='text' size='6' id='set_inventory_" . $row['product_distributor_id'] . "' name='set_inventory_" . $row['product_distributor_id'] . "' value=''></td>";
			} else {
				if (canAccessPageCode("PRODUCTINVENTORYMAINT")) {
					$productInventory .= "<td><a target='_blank' class='product-inventory-update' href='/productinventorymaintenance.php?clear_filter=true&url_page=show&primary_id=" . $row['product_inventory_id'] . "'>Update</a></td>";
				} else {
					$productInventory .= "<td></td>";
				}
			}
			$productInventory .= "</tr>";
		}
		$resultSet = executeQuery("select * from locations where inactive = 0 and cannot_ship = 0 and ignore_inventory = 0 and product_distributor_id is null and client_id = ? order by description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (in_array($row['location_id'], $locationIds)) {
				continue;
			}
			$productInventory .= "<tr data-product_inventory_id='' class='product-inventory'><td class='product-inventory-description'>" . $row['description'] . "</td><td class='align-right'>0</td><td></td><td></td>";
			if (canAccessPageCode("PRODUCTINVENTORYMAINT")) {
				$productInventory .= "<td><input data-crc_value='" . getCrcValue("") . "' class='align-right validate[min[0],custom[integer]]' type='text' size='6' id='set_location_inventory_" . $row['location_id'] . "' name='set_location_inventory_" . $row['location_id'] . "' value=''></td>";
			} else {
				$productInventory .= "<td></td>";
			}
			$productInventory .= "</tr>";
		}
		$productInventory .= "</table>";
		$productInventory .= "<p>Total inventory available: " . ($totalInventory - $waitingQuantity) . "</p>";
		if ($waitingQuantity > $totalInventory) {
			$productInventory .= "<p class='highlighted-text'>Out of stock</p>";
		} elseif (count($productDistributorIds) > 0) {
			$productInventory .= "<p class='highlighted-text'>Setting distributor inventory is temporary and will be overwritten when the inventory update process runs.</p>";
		}
		$returnArray['product_inventory'] = array("data_value" => $productInventory);
	}

	function productDataFields() {
		$alreadyDisplayed = array("client_id", "product_id", "minimum_price", "manufacturer_advertised_price", "version", "product_distributor_id");
		$productDataSource = new DataSource("product_data");
		$productDataSource->getPageControls();
		$resultSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where " .
			"table_id = (select table_id from tables where table_name = 'product_data') and primary_table_key = 0 order by sequence_number");
		while ($row = getNextRow($resultSet)) {
			if (in_array($row['column_name'], $alreadyDisplayed)) {
				continue;
			}
			$column = $productDataSource->getColumns($row['column_name']);
			$dataType = $column->getControlValue('data_type');
			$helpLabel = $column->getControlValue('help_label');
			?>
            <div class="basic-form-line" id="_<?= $column->getControlValue('column_name') ?>_row">
                <label for="<?= $column->getControlValue('column_name') ?>"><?= ($dataType == "tinyint" ? "" : $column->getControlValue("form_label")) ?></label>
				<?= $column->getControl($this) ?>
                <div class='basic-form-line-messages'><span class="help-label"><?= htmlText($helpLabel) ?></span><span class='field-error-text'></span></div>
                <div class='clear-div'></div>
            </div>
			<?php
		}
	}

	function displayCustomFields() {
		$customFields = CustomField::getCustomFields("products");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl(array("basic_form_line" => true));
		}
	}

	function beforeDeleteRecord($primaryId) {
		executeQuery("delete from potential_product_duplicates where product_id = ? or duplicate_product_id = ?", $primaryId, $primaryId);
		executeQuery("delete from shopping_cart_item_addons where shopping_cart_item_id in (select shopping_cart_item_id from shopping_cart_items where product_id = ?)", $primaryId);
		$resultSet = executeQuery("select product_inventory_id from product_inventories where product_id = ?", $primaryId);
		$productInventoryIds = array();
		while ($row = getNextRow($resultSet)) {
			$productInventoryIds[] = $row['product_inventory_id'];
		}
		if (!empty($productInventoryIds)) {
			$resultSet = executeQuery("delete from product_inventory_log where product_inventory_id in (" . implode(",", $productInventoryIds) . ")");
			if (!empty($resultSet['sql_error'])) {
				return false;
			}
		}
		$resultSet = executeQuery("delete from product_group_variant_choices where product_group_variant_id in (select product_group_variant_id from product_group_variants where product_id = ?)", $primaryId);
		if (!empty($resultSet['sql_error'])) {
			return false;
		}
		$resultSet = executeQuery("delete from custom_field_data where primary_identifier = ? and custom_field_id in " .
			"(select custom_field_id from custom_fields where custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'PRODUCTS'))",
			$primaryId);
		if (!empty($resultSet['sql_error'])) {
			return false;
		}

		return true;
	}

	function internalCSS() {
		?>
        <style>
            #product_inventory_log {
                max-height: 800px;
                overflow: scroll;
            }

            #_main_content p#missing_information {
                color: rgb(200, 0, 0);
                font-size: 1.2rem;
            }

            #upper_image {
                position: absolute;
                top: 0;
                right: 0;
                z-index: 1000;
            }

            #upper_image img {
                max-height: 100px;
                max-width: 500px;
            }

            #_upper_section {
                position: relative;
                padding-bottom: 10px;
            }

            table.grid-table {
                margin-bottom: 10px;
            }

            tr.product-inventory {
                cursor: pointer;
            }

            tr.product-inventory:hover {
                background-color: rgb(240, 240, 160);
            }

            tr.product-inventory td.product-inventory-description {
                text-decoration: underline;
                font-weight: 900;
                font-color: rgb(0, 124, 124);
            }

            #image_message {
                color: rgb(0, 192, 0);
                font-weight: 700;
                font-size: .9rem;
            }

            #taxjar_category_filter {
                width: 400px;
            }

            #taxjar_category_list {
                height: 600px;
            }

            .taxjar-category {
                cursor: pointer;

            &
            :hover {
                background-color: rgb(240, 240, 160)
            }

            }
        </style>
		<?php
	}

	function jqueryTemplates() {
		$customFields = CustomField::getCustomFields("products");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getTemplate();
		}
	}

	function hiddenElements() {
		?>

        <div id="_taxjar_categories_dialog" class="dialog-box">
            <p>Filter and click a category to select it.</p>
            <p><input type='text' id='taxjar_category_filter' placeholder='Filter Categories'></p>
            <div id="taxjar_category_list"></div>
        </div>
        <div id="_product_inventory_log_dialog" class="dialog-box">
            <div id='product_inventory_log'></div>
        </div>

        <div id="_add_to_category_dialog" class="dialog-box">
            <p>Add Selected Products to this category</p>
            <p class="red-text"><span class="highlighted-text page-select-count"></span> products are selected and will be added to this category. MAKE SURE this is correct.</p>
            <form id="_add_to_category_form">
                <div class="basic-form-line" id="_product_category_id_row">
                    <label for="product_category_id">Product Category</label>
                    <select id="product_category_id" name="product_category_id" class="validate[required]">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from product_categories where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_category_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>
            </form>
        </div>

        <div id="_remove_from_category_dialog" class="dialog-box">
            <p>Remove Selected Products from this category</p>
            <form id="_remove_from_category_form">
                <div class="basic-form-line" id="_product_category_id_row">
                    <label for="product_category_id">Product Category</label>
                    <select id="product_category_id" name="product_category_id" class="validate[required]">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from product_categories where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_category_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>
            </form>
        </div>

        <div id="_add_inventory_notification_dialog" class="dialog-box">
            <p>Send a notification when any of the selected products reaches an inventory level.</p>
            <form id="_add_inventory_notification_form">
                <div class="basic-form-line" id="_product_distributor_id_row">
                    <label for="product_distributor_id">Product Distributor</label>
                    <select id="product_distributor_id" name="product_distributor_id" class="">
                        <option value="">[Total of all]</option>
						<?php
						$resultSet = executeQuery("select * from product_distributors where inactive = 0 order by description");
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_distributor_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>

                <div class="basic-form-line" id="_comparator_row">
                    <label for="comparator">Comparator</label>
                    <select id="comparator" name="comparator" class="validate[required]">
                        <option value="">[Select]</option>
						<?php
						foreach (array("<" => "Less than", "<=" => "Less than equal", "=>" => "Greater than equal", ">" => "Greater than", "=" => "Equal") as $value => $description) {
							?>
                            <option value="<?= $value ?>"><?= htmlText($description) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>

                <div class="basic-form-line" id="_quantity_row">
                    <label for="quantity">Quantity</label>
                    <input size="8" type='text' id="quantity" name="quantity" class="align-right validate[required,custom[integer]]">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>

                <div class="basic-form-line" id="_email_address_row">
                    <label for="email_address">Email Address</label>
                    <input type='text' id="email_address" name="email_address" class="validate[required,custom[email]]">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>

                <div class="basic-form-line" id="_place_order_row">
                    <input type='checkbox' id="place_order" name="place_order" value="1"><label class='checkbox-label' for='place_order'>Place Order</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>

                <div class="basic-form-line place-order hidden" id="_location_id_row">
                    <label for="location_id">Order From Location</label>
                    <select id="location_id" name="location_id">
                        <option value=''>[Any Location]</option>
						<?php
						$resultSet = executeQuery("select * from locations where " . (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_location = 1 and user_id = " . $GLOBALS['gUserId'] : "user_location = 0") .
							" and inactive = 0 and product_distributor_id is not null and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value='<?= $row['location_id'] ?>'><?= htmlText($row['description']) ?></option>
							<?php
						}
						freeResult($resultSet);
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>

                <div class="basic-form-line place-order hidden" id="_order_quantity_row">
                    <label for="order_quantity">Order Quantity</label>
                    <input size="8" type='text' id="order_quantity" name="order_quantity" class="align-right validate[required,custom[integer]]">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>

                <div class="basic-form-line place-order hidden any-order-location" id="_use_lowest_price_row">
                    <input type='checkbox' id="use_lowest_price" name="use_lowest_price" value="1"><label class='checkbox-label' for='use_lowest_price'>Order from location with lowest price</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>

                <div class="basic-form-line place-order hidden any-order-location" id="_allow_multiple_row">
                    <input type='checkbox' id="allow_multiple" name="allow_multiple" value="1"><label class='checkbox-label' for='allow_multiple'>Place multiple orders, if necessary</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>

            </form>
        </div>

        <div id="_set_manufacturer_dialog" class="dialog-box">
            <p>Set Manufacturer for selected products. Only applies to products without a manufacturer set.</p>
            <form id="_set_manufacturer_form">
                <div class="basic-form-line" id="_product_manufacturer_id_row">
                    <label for="product_manufacturer_id">Product Manufacturer</label>
                    <select id="product_manufacturer_id" name="product_manufacturer_id" class="validate[required]">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from product_manufacturers where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_manufacturer_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>
            </form>
        </div>

        <div id="_set_tag_dialog" class="dialog-box">
            <p id="set_tag_paragraph">Tag selected products.</p>
            <form id="_set_tag_form">
                <div class="basic-form-line" id="_product_tag_id_row">
                    <label for="product_tag_id">Product Tag</label>
                    <select id="product_tag_id" name="product_tag_id" class="validate[required]">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from product_tags where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_tag_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>
                <div class="basic-form-line date-row" id="_start_date_row">
                    <label for="start_date">Start Date (optional)</label>
                    <input class="datepicker" id="start_date" name="start_date">
                    <div class='clear-div'></div>
                </div>
                <div class="basic-form-line date-row" id="_expiration_date_row">
                    <label for="start_date">Expiration Date (leave blank to make permanent)</label>
                    <input class="datepicker" id="expiration_date" name="expiration_date">
                    <div class='clear-div'></div>
                </div>
            </form>
        </div>

        <div id="_set_custom_field_dialog" class="dialog-box">
            <p id="set_custom_field_paragraph">Set custom fields on selected products.</p>
            <form id="_set_custom_field_form">
                <div class="basic-form-line" id="_product_custom_field_id_row">
                    <label for="custom_field_id">Custom Field</label>
                    <select id="custom_field_id" name="custom_field_id" class="validate[required]">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from custom_fields where client_id = ? and inactive = 0 and " .
							"custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'PRODUCTS') and " .
							"custom_field_id in (select custom_field_id from custom_field_controls where control_name = 'data_type' and control_value = 'tinyint') order by description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['custom_field_id'] ?>"><?= htmlText($row['form_label']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>
            </form>
        </div>

        <div id="_remove_sale_prices_dialog" class="dialog-box">
            <p id="remove_sale_prices_paragraph">Remove set sale prices from selected products.</p>
            <form id="_remove_sale_prices_form">
                <div class="basic-form-line" id="_location_id_row">
                    <label for="location_id">Location</label>
                    <select id="location_id" name="location_id" class="validate[required]">
                        <option value="">[Select]</option>
                        <option value="any">[Any]</option>
						<?php
                        $resultSet = executeQuery("select * from locations where client_id = ? and (product_distributor_id is null or primary_location = 1) order by sort_order, description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['location_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>
            </form>
        </div>

		<?php
	}
}

$pageObject = new ProductMaintenancePage("products");
$pageObject->displayPage();
