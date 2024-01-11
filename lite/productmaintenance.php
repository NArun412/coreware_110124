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

$GLOBALS['gPageCode'] = "PRODUCTMAINT_LITE";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;
ini_set("memory_limit", "4096M");

class ProductMaintenanceLitePage extends Page {

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

		$filters = array();
		$filters['tag_header'] = array("form_label" => "Tags", "data_type" => "header");
		$resultSet = executeQuery("select * from product_tags where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$filters['product_tag_' . $row['product_tag_id']] = array("form_label" => $row['description'],
				"where" => "products.product_id in (select product_id from product_tag_links where (expiration_date is null or expiration_date > current_date) and product_tag_id = " . $row['product_tag_id'] . ")",
				"data_type" => "tinyint");
		}

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
		$resultSet = executeQuery("select location_id from locations where client_id = ? and inactive = 0 and internal_use_only = 0 and ignore_inventory = 0", $GLOBALS['gClientId']);
		$locationIds = array();
		while ($row = getNextRow($resultSet)) {
			$locationIds[] = $row['location_id'];
		}
		if (!empty($locationIds)) {
			$filters['in_stock'] = array("form_label" => "In Stock for Customer", "where" => "products.product_id in (select product_id from product_inventories where quantity > 0 and location_id in (" . implode(",", $locationIds) . "))", "data_type" => "tinyint");
		}

		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("add_to_category", "Add Selected Products to Category");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("remove_from_category", "Remove Selected Products from Category");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_manufacturer", "Set Manufacturer for Selected Products");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_tag", "Tag Selected Products");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("remove_tag", "Remove Tag from Selected Products");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_custom_field", "Set Custom Field on Selected Products");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("remove_custom_field", "Remove Custom Field from Selected Products");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_inactive", "Mark selected Products inactive");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("clear_inactive", "Clear inactive flag for selected Products");

		$this->iTemplateObject->getTableEditorObject()->setIgnoreGuiSorting(true);
		$this->iTemplateObject->getTableEditorObject()->addIncludeSearchColumn(array("product_id", "product_code", "upc_code"));
		$pageId = getFieldFromId("page_id", "pages", "script_filename", "retailstore/productdetails.php", "link_name = 'product-details'");
		if (!empty($pageId)) {
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("view_site" => array("icon" => "fad fa-eye", "label" => getLanguageText("View"), "disabled" => false)));
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
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
			case "set_inactive":
				$inactiveValue = true;
			case "clear_inactive":
				$returnArray = DataTable::setInactive("products", $inactiveValue);
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
				$salePriceInfo = $productCatalog->getProductSalePrice($row['product_id'], array("product_information" => $row, "no_stored_prices" => true, "contact_type_id" => "", "user_type_id" => ""));
				$salePrice = $salePriceInfo['sale_price'];

				$dataList[$index]['sale_price'] = $salePrice;
				if (empty($row['manufacturer_advertised_price'])) {
					$dataList[$index]['displayed_sale_price'] = $salePrice;
				} else {
					$dataList[$index]['displayed_sale_price'] = $row['manufacturer_advertised_price'];
				}
			}
		}
	}

	function massageDataSource() {
		$this->iDataSource->setSaveOnlyPresent(true);

		$this->iDataSource->addColumnControl("product_restrictions", "list_table_controls", array("state" => array("data_type" => "select", "choices" => getStateArray())));

		$this->iDataSource->addColumnControl("map_expiration_date", "data_type", "hidden");
		$this->iDataSource->addColumnControl("product_tag_links", "column_list", "product_tag_id,start_date,expiration_date");
		$this->iDataSource->addColumnControl("product_tag_links", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("product_tag_links", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_tag_links", "form_label", "Tags");
		$this->iDataSource->addColumnControl("product_tag_links", "list_table", "product_tag_links");

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

		$this->iDataSource->addColumnControl("pricing_structure_id", "empty_text", "[Use Default]");

		$this->iDataSource->addColumnControl("date_created", "form_line_classes", "inline-block");
		$this->iDataSource->addColumnControl("expiration_date", "form_line_classes", "inline-block");
		$this->iDataSource->addColumnControl("time_changed", "form_line_classes", "inline-block");

		$this->iDataSource->addColumnControl("distributor_product_codes", "data_type", "custom");
		$this->iDataSource->addColumnControl("distributor_product_codes", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("distributor_product_codes", "list_table", "distributor_product_codes");
		$this->iDataSource->addColumnControl("distributor_product_codes", "form_label", "Distributor Product Codes");

		$this->iDataSource->addColumnControl("product_prices", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_prices", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("product_prices", "filter_where", "product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'SALE_PRICE')");
		$this->iDataSource->addColumnControl("product_prices", "column_list", "location_id,price");
		$this->iDataSource->addColumnControl("product_prices", "list_table", "product_prices");
		$this->iDataSource->addColumnControl("product_prices", "form_label", "coreSTORE Prices");
		$this->iDataSource->addColumnControl("product_prices", "help_label", "If a location is set, the sale price will only be valid for inventory from that location.");
		$this->iDataSource->addColumnControl("product_prices", "no_delete", true);
		$this->iDataSource->addColumnControl("product_prices", "no_add", true);
		$this->iDataSource->addColumnControl("product_prices", "list_table_controls", array(
			"location_id" => array("not_editable" => true, "empty_text" => "[Any]"), "price" => array("not_editable" => true)));


		$this->iDataSource->addColumnControl("detailed_description", "classes", "ck-editor");
		$this->iDataSource->addColumnControl("detailed_description", "no_editor", true);

		$this->iDataSource->addColumnControl("image_id", "data_type", "image_input");
		$this->iDataSource->setJoinTable("product_data", "product_id", "product_id", true);
		$this->iDataSource->getPrimaryTable()->setSubtables(array("product_data", "product_sale_prices", "product_category_links", "product_change_details", "product_restrictions", "product_sale_notifications",
			"product_contributors", "product_inventories", "product_reviews", "product_vendors", "product_search_word_values", "product_payment_methods", "product_prices",
			"product_shipping_methods", "product_tag_links", "product_facet_values", "distributor_product_codes", "product_serial_numbers",
			"product_images", "product_videos", "product_addons", "quotations", "related_products", "shopping_cart_items", "wish_list_items", "product_remote_images", "product_group_variants", "product_view_log", "product_inventory_notifications"));
		$this->iDataSource->addColumnControl("product_id", "exact_search", true);
		$this->iDataSource->addColumnControl("product_id", "form_label", "Product ID / Ecommerce ID");
		$this->iDataSource->addColumnControl("product_id", "list_header", "ID");

		$this->iDataSource->addColumnControl("product_facet_values", "list_table_controls",
			array("product_facet_id" => array("classes" => "product-facet-id"),
				"product_facet_option_id" => array("classes" => "product-facet-option-id", "data-additional_filter_function" => "productFacetOptionFilter")));

		$this->iDataSource->addColumnControl("product_videos", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_videos", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("product_videos", "form_label", "Videos");
		$this->iDataSource->addColumnControl("product_videos", "list_table", "product_videos");

		$this->iDataSource->addColumnControl("upc_code", "exact_search", true);
		$this->iDataSource->addColumnControl("product_images", "list_table_controls", array("description" => array("classes" => "image-description"), "image_id" => array("data_type" => "image_input")));

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

	function onLoadJavascript() {
		?>
		<script>
            $(document).on("click", "#_view_site_button", function () {
                if (!empty($("#primary_id").val())) {
                    window.open("/product-details?id=" + $("#primary_id").val());
                }
                return false;
            });
            $(document).on("click", ".product-inventory-update", function (event) {
                event.stopPropagation();
                return true;
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
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_facets&product_category_ids=" + encodeURIComponent($(this).val()), function (returnArray) {
                    if ("product_facet_ids" in returnArray) {
                        for (var i in returnArray['product_facet_ids']) {
                            let foundFacet = false;
                            $("#_product_facet_values_row").find(".product-facet-id").each(function () {
                                if ($(this).val() == i) {
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
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_taxonomy&product_category_ids=" + encodeURIComponent($(this).val()), function (returnArray) {
                    if ("taxonomy" in returnArray) {
                        $("#_product_taxonomy").html("<table id='_product_taxonomy_table' class='grid-table'><tr><th>Category</th><th>Category Groups</th><th>Departments</th></tr></table>");
                        returnArray['taxonomy'].forEach(function (row) {
                            $("#_product_taxonomy_table > tbody").append("<tr><td>" + row.product_category + "</td><td>" + row.product_category_groups + "</td><td>" + row.product_departments + "</td></tr>");
                        });
                    }
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
                            width: 600,
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

                if (actionName === "set_inactive" || actionName === "clear_inactive") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=" + actionName, function (returnArray) {
                        getDataList();
                    });
                    return true;
                }
                return false;
            }
		</script>
		<?php
	}

	function beforeSaveChanges(&$nameValues) {
		$nameValues['upc_code'] = ProductCatalog::makeValidUPC(trim($nameValues['upc_code']));
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

	function afterSaveChanges($nameValues, $actionPerformed) {
		foreach ($nameValues as $fieldName => $fieldValue) {
			if (strlen($fieldValue) > 0 && substr($fieldName, 0, strlen("set_inventory_")) == "set_inventory_") {
				$productDistributorId = getFieldFromId("product_distributor_id", "product_distributors", "product_distributor_id", substr($fieldName, strlen("set_inventory_")));
				if (empty($productDistributorId)) {
					continue;
				}
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
						executeQuery("insert into product_inventory_log (product_inventory_id, inventory_adjustment_type_id, user_id, log_time, quantity, notes) values " .
							"(?,?,?,now(),?,'Manual update in product maintenance')", $row['product_inventory_id'], $GLOBALS['gInventoryAdjustmentTypeId'], $GLOBALS['gUserId'], $fieldValue);
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
		removeCachedData("product_category_ids", $nameValues['primary_id']);
		removeCachedData("product_waiting_quantity", $nameValues['primary_id']);
		removeCachedData("base_cost", $nameValues['primary_id']);
		removeCachedData("*", $nameValues['primary_id']);
		removeCachedData("*", $nameValues['primary_id']);
		if (empty($nameValues['base_cost'])) {
			ProductCatalog::calculateProductCost($nameValues['primary_id'], "Saved in Product Maintenance List");
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
		ProductCatalog::calculateAllProductSalePrices($returnArray['primary_id']['data_value']);
		removeCachedData("price::", $returnArray['primary_id']['data_value']);
        // 2023-10-29 This count is significantly hurting performance and doesn't seem to be used anywhere
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

		if (empty($returnArray['image_id']['data_value']) && !empty($returnArray['remote_identifier']['data_value'])) {
			$returnArray['image_message'] = array("data_value" => "Primary image comes from Coreware resource library. Add alternate images for different views. Adding a primary image will override the Coreware image.");
		} else {
			$returnArray['image_message'] = array("data_value" => "");
		}
		removeCachedData("full_product_content", $returnArray['primary_id']['data_value']);
		removeCachedData("base_cost", $returnArray['primary_id']['data_value']);
		removeCachedData("price::", $returnArray['primary_id']['data_value']);
		$productCatalog = new ProductCatalog();
		$salePriceInfo = $productCatalog->getProductSalePrice($returnArray['primary_id']['data_value'], array("no_cache" => true, "no_stored_prices" => true, "contact_type_id" => "", "user_type_id" => ""));
		$salePrice = $salePriceInfo['sale_price'];
		if ($salePrice === false) {
			$salePrice = "";
		} else {
			$salePrice = number_format($salePrice, 2, ".", ",");
		}
		$returnArray['displayed_sale_price'] = array("data_value" => $salePrice);

		$salePriceInfo = $productCatalog->getProductSalePrice($returnArray['primary_id']['data_value'], array("no_cache" => true, "no_stored_prices" => true, "contact_type_id" => "", "user_type_id" => "", "ignore_map" => true));
		$salePrice = $salePriceInfo['sale_price'];
		if ($salePrice === false) {
			$salePrice = "";
		} else {
			$salePrice = number_format($salePrice, 2, ".", ",");
		}
		$returnArray['sale_price'] = array("data_value" => $salePrice);

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
		if (!empty($returnArray['non_inventory_item']['data_value'])) {
			$returnArray['product_inventory'] = array("data_value" => "");
		} else {
			$productInventory .= "<table class='grid-table'><tr><th>Location</th><th>Quantity</th><th>Total Cost</th><th>Each</th><th>Set Inventory</th></tr>";
			$resultSet = executeQuery("select * from locations join product_inventories using (location_id) where inactive = 0 and " .
				"product_id = ? order by quantity desc", $returnArray['primary_id']['data_value']);
			$totalInventory = 0;
			$productDistributorIds = array();
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
				$totalInventory += $row['quantity'];
				$logSet = executeQuery("select * from product_inventory_log where product_inventory_id = ? and " .
					"inventory_adjustment_type_id in (select inventory_adjustment_type_id from inventory_adjustment_types where " .
					"inventory_adjustment_type_code in ('INVENTORY','RESTOCK') and client_id = ?) and quantity is not null and quantity > 0 and total_cost is not null order by product_inventory_log_id desc limit 1", $row['product_inventory_id'], $GLOBALS['gClientId']);
				if (!$logRow = getNextRow($logSet)) {
					$logRow = array();
				}
				$productInventory .= "<tr data-product_inventory_id='" . $row['product_inventory_id'] . "' class='product-inventory'><td class='product-inventory-description'>" . $locationDescription . "</td><td class='align-right'>" . $row['quantity'] . "</td>" .
					"<td class='align-right'>" . (empty($logRow['total_cost']) ? "" : number_format($logRow['total_cost'], 2, ".", ",")) . "</td><td>" . (empty($logRow['quantity']) ? "" : number_format(round($logRow['total_cost'] / $logRow['quantity'], 2), 2, ".", ",")) . "</td>";
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
			$productInventory .= "</table>";
			$productInventory .= "<p>Total inventory available: " . ($totalInventory - $waitingQuantity) . "</p>";
			if ($waitingQuantity > $totalInventory) {
				$productInventory .= "<p class='highlighted-text'>Out of stock</p>";
			} elseif (count($productDistributorIds) > 0) {
				$productInventory .= "<p class='highlighted-text'>Setting distributor inventory is temporary and will be overwritten when the inventory update process runs.</p>";
			}
		}
		$returnArray['product_inventory'] = array("data_value" => $productInventory);
	}

	function productDataFields($columnName) {
		$productDataSource = new DataSource("product_data");
		$productDataSource->getPageControls();
		$resultSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where " .
			"table_id = (select table_id from tables where table_name = 'product_data') and column_name = ?", $columnName);
		while ($row = getNextRow($resultSet)) {
			$column = $productDataSource->getColumns($row['column_name']);
			$dataType = $column->getControlValue('data_type');
			$helpLabel = $column->getControlValue('help_label');
			?>
			<div class="basic-form-line" id="_<?= $column->getControlValue('column_name') ?>_row">
				<label for="<?= $column->getControlValue('column_name') ?>"><?= ($dataType == "tinyint" ? "" : $column->getControlValue("form_label")) ?></label>
				<?= $column->getControl($this) ?>
				<div class='basic-form-line-messages'><span class="help-label"><?= htmlText($helpLabel) ?></span><span class='field-error-text'></span></div>

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
		return true;
	}

	function internalCSS() {
		?>
		<style>
            #product_inventory_log {
                max-height: 800px;
                overflow: scroll;
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

		</style>
		<?php
	}

	function hiddenElements() {
		?>

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
						$resultSet = executeReadQuery("select * from product_categories where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
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
						$resultSet = executeReadQuery("select * from product_categories where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
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

        <div id="_set_manufacturer_dialog" class="dialog-box">
            <p>Set Manufacturer for selected products. Only applies to products without a manufacturer set.</p>
            <form id="_set_manufacturer_form">
                <div class="basic-form-line" id="_product_manufacturer_id_row">
                    <label for="product_manufacturer_id">Product Manufacturer</label>
                    <select id="product_manufacturer_id" name="product_manufacturer_id" class="validate[required]">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeReadQuery("select * from product_manufacturers where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
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
						$resultSet = executeReadQuery("select * from product_tags where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
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
		<?php
	}
}

$pageObject = new ProductMaintenanceLitePage("products");
$pageObject->displayPage();
