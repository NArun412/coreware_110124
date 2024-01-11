<?php

# Product API Methods
$getFullProductInformation = false;
switch ($_POST['action']) {
	case "remove_product_sale_prices":
		$salePriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_code", "sale_price");
		if ($_POST['product_ids'] == "all_any_location") {
			$resultSet = executeQuery("delete from product_prices where product_price_type_id = ? and product_id in (select product_id from products where client_id = ?) and " .
				"location_id is null", $salePriceTypeId, $GLOBALS['gClientId']);
			break;
		}
		$productIds = array_filter((is_array($_POST['product_ids']) ? $_POST['product_ids'] : explode(",", $_POST['product_ids'])));
		if (empty($productIds)) {
			break;
		}
		$whereProducts = "";
		foreach ($productIds as $productId) {
			if (!empty($productId) && is_numeric($productId)) {
				$whereProducts .= (empty($whereProducts) ? "" : ",") . $productId;
				removeCachedData("product_prices", $productId);
			}
		}
		$resultSet = executeQuery("delete from product_prices where product_price_type_id = ? and product_id in (select product_id from products where client_id = ?) and " .
			"product_id in (" . $whereProducts . ")", $salePriceTypeId, $GLOBALS['gClientId']);
		break;
	case "get_product_tags":
		$productTags = array();
		$resultSet = executeReadQuery("select * from product_tags where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$row['image_url'] = getDomainName() . getImageFilename($row['image_id']);
			$productTags[] = $row;
		}
		$returnArray['product_tags'] = $productTags;
		break;
	case "add_product_tag":
		if (empty($_POST['product_tag_code'])) {
			$productTagCode = makeCode($_POST['description']);
		} else {
			$productTagCode = makeCode($_POST['product_tag_code']);
		}
		$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", $productTagCode);
		if (empty($productTagId)) {
			$productTagId = getFieldFromId("product_tag_id", "product_tags", "description", $_POST['description']);
		}
		if (!empty($productTagId)) {
			$returnArray['product_tag_id'] = $productTagId;
			$returnArray['error_message'] = "Tag already exists" . "-" . __LINE__;
		}
		$productTagTable = new DataTable("product_tags");
		$nameValues = array("description" => $_POST['description'], "product_tag_code" => $productTagCode);
		if (!$productTagId = $productTagTable->saveRecord(array("name_values" => $nameValues))) {
			$returnArray['error_message'] = "Unable to create tag" . "-" . __LINE__;
		} else {
			$returnArray['product_tag_id'] = $productTagId;
			$returnArray['product_tag_code'] = $productTagCode;
			$returnArray['description'] = $_POST['description'];
		}
		break;
	case "add_product_category":
		if (empty($_POST['product_category_code'])) {
			$productCategoryCode = makeCode($_POST['description']);
		} else {
			$productCategoryCode = makeCode($_POST['product_category_code']);
		}
		$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_code", $productCategoryCode);
		if (empty($productCategoryId)) {
			$productCategoryId = getFieldFromId("product_category_id", "product_categories", "description", $_POST['description']);
		}
		if (!empty($productCategoryId)) {
			$returnArray['product_category_id'] = $productCategoryId;
			$returnArray['error_message'] = "Category already exists" . "-" . __LINE__;
			break;
		}
		if (!empty($_POST['product_category_group_id'])) {
			$productCategoryGroupId = getFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_id", $_POST['product_category_group_id']);
			if (empty($productCategoryGroupId)) {
				$returnArray['error_message'] = "Product Category Group ID not found: " . $_POST['product_category_group_id'] . "-" . __LINE__;
				break;
			}
		}
		if (empty($productCategoryGroupId) && !empty($_POST['product_category_group_code'])) {
			$productCategoryGroupId = getFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_code", makeCode($_POST['product_category_group_code']));
			if (empty($productCategoryGroupId)) {
				$returnArray['error_message'] = "Product Category Group Code not found: " . $_POST['product_category_group_code'] . "-" . __LINE__;
				break;
			}
		}
		if (!empty($_POST['product_department_id'])) {
			$productDepartmentId = getFieldFromId("product_department_id", "product_departments", "product_department_id", $_POST['product_department_id']);
			if (empty($productDepartmentId)) {
				$returnArray['error_message'] = "Product Department ID not found: " . $_POST['product_department_id'] . "-" . __LINE__;
				break;
			}
		}
		if (empty($productDepartmentId) && !empty($_POST['product_department_code'])) {
			$productDepartmentId = getFieldFromId("product_department_id", "product_departments", "product_department_code", makeCode($_POST['product_department_code']));
			if (empty($productDepartmentId)) {
				$returnArray['error_message'] = "Product Department Code not found: " . $_POST['product_department_code'] . "-" . __LINE__;
				break;
			}
		}
		$productCategoryTable = new DataTable("product_categories");
		$nameValues = array("description" => $_POST['description'], "product_category_code" => $productCategoryCode);
		if (!$productCategoryId = $productCategoryTable->saveRecord(array("name_values" => $nameValues))) {
			$returnArray['error_message'] = "Unable to create category" . "-" . __LINE__;
		} else {
			$returnArray['product_category_id'] = $productCategoryId;
			$returnArray['product_category_code'] = $productCategoryCode;
			$returnArray['description'] = $_POST['description'];
		}
		if (!empty($productCategoryGroupId)) {
			$productCategoryGroupLinksTable = new DataTable("product_category_group_links");
			$nameValues = array("product_category_id" => $productCategoryId, "product_category_group_id" => $productCategoryGroupId);
			if (!$productCategoryGroupLinksTable->saveRecord(array("name_values" => $nameValues))) {
				$returnArray['error_message'] = "Category was created but error occurred adding category to category group: " . $productCategoryGroupLinksTable->getErrorMessage() . "-" . __LINE__;
			}
		}
		if (!empty($productDepartmentId)) {
			$productCategoryDepartmentsTable = new DataTable("product_category_departments");
			$nameValues = array("product_category_id" => $productCategoryId, "product_department_id" => $productDepartmentId);
			if (!$productCategoryDepartmentsTable->saveRecord(array("name_values" => $nameValues))) {
				$returnArray['error_message'] = "Category was created but error occurred adding category to department: " . $productCategoryDepartmentsTable->getErrorMessage() . "-" . __LINE__;
			}
		}
		break;
	case "get_product_categories":
		$productCategories = array();
		if (!empty($_POST['product_category_group_code'])) {
			$resultSet = executeReadQuery("select * from product_categories where inactive = 0 and client_id = ? and product_category_id in (select product_category_id from product_category_group_links " .
				"where product_category_group_id in (select product_category_group_id from product_category_groups where product_category_group_code = ?))", $GLOBALS['gClientId'], $_POST['product_category_group_code']);
		} elseif (!empty($_POST['product_category_code'])) {
			$resultSet = executeReadQuery("select * from product_categories where inactive = 0 and client_id = ? and product_category_code = ?", $GLOBALS['gClientId'], $_POST['product_category_code']);
		} elseif (!empty($_POST['product_category_id'])) {
			$resultSet = executeReadQuery("select * from product_categories where inactive = 0 and client_id = ? and product_category_id = ?", $GLOBALS['gClientId'], $_POST['product_category_id']);
		} else {
			$resultSet = executeReadQuery("select * from product_categories where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		}
		while ($row = getNextRow($resultSet)) {
			$atfFirearmType = getReadFieldFromId("description", "atf_firearm_types", "atf_firearm_type_id", $row['atf_firearm_type_id']);
			$productCategories[] = array("product_category_id" => $row['product_category_id'], "product_category_code" => $row['product_category_code'], "atf_firearm_type" => $atfFirearmType, "description" => $row['description'], "image_id" => $row['image_id'], "image_url" => "https://" . $_SERVER['HTTP_HOST'] . getImageFilename($row['image_id']));
		}
		$returnArray['product_categories'] = $productCategories;
		break;

	case "get_product_category_groups":
		$productCategoryGroups = array();
		$resultSet = executeReadQuery("select * from product_category_groups where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$categorySet = executeReadQuery("select product_category_id from product_category_group_links where product_category_group_id = ?", $row['product_category_group_id']);
			$productCategories = array();
			while ($categoryRow = getNextRow($categorySet)) {
				$productCategories[] = $categoryRow['product_category_id'];
			}
			$productCategoryGroups[] = array("product_category_group_id" => $row['product_category_group_id'], "product_category_group_code" => $row['product_category_group_code'], "description" => $row['description'], "product_categories" => $productCategories);
		}
		$returnArray['product_category_groups'] = $productCategoryGroups;
		break;

	case "get_product_departments":
		$productDepartments = array();
		$resultSet = executeReadQuery("select * from product_departments where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$categorySet = executeReadQuery("select product_category_id from product_category_departments where product_department_id = ?", $row['product_department_id']);
			$productCategories = array();
			while ($categoryRow = getNextRow($categorySet)) {
				$productCategories[] = $categoryRow['product_category_id'];
			}
			$productDepartments[] = array("product_department_id" => $row['product_department_id'], "product_department_code" => $row['product_department_code'], "description" => $row['description'], "product_categories" => $productCategories);
		}
		$returnArray['product_departments'] = $productDepartments;
		break;

	case "get_product_facets":
		$productFacets = array();
		$resultSet = executeReadQuery("select * from product_facets where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$categorySet = executeReadQuery("select product_category_id from product_facet_categories where product_facet_id = ?", $row['product_facet_id']);
			$productCategories = array();
			while ($categoryRow = getNextRow($categorySet)) {
				$productCategories[] = $categoryRow['product_category_id'];
			}
			$productFacets[] = array("product_facet_id" => $row['product_facet_id'], "product_facet_code" => $row['product_facet_code'], "description" => $row['description'], "product_categories" => $productCategories);
		}
		$returnArray['product_facets'] = $productFacets;
		break;

	case "get_taxonomy_structure":
		$productCategories = array();
		$resultSet = executeReadQuery("select * from product_categories where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$groupSet = executeReadQuery("select product_category_group_id,product_category_group_code from product_category_groups join product_category_group_links using (product_category_group_id) where product_category_id = ?", $row['product_category_id']);
			$productCategoryGroups = array();
			while ($groupRow = getNextRow($groupSet)) {
				$productCategoryGroups[$groupRow['product_category_group_id']] = $groupRow['product_category_group_code'];
			}
			$departmentSet = executeReadQuery("select product_department_id,product_department_code from product_departments join product_category_departments using (product_department_id) where product_category_id = ?", $row['product_category_id']);
			$productDepartments = array();
			while ($departmentRow = getNextRow($departmentSet)) {
				$productDepartments[$departmentRow['product_department_id']] = $departmentRow['product_department_code'];
			}
			$productCategories[] = array("product_category_id" => $row['product_category_id'], "product_category_code" => $row['product_category_code'], "link_name" => $row['link_name'], "description" => $row['description'], "atf_firearm_type_id" => $row['atf_firearm_type_id'], "product_category_groups" => $productCategoryGroups, "product_departments" => $productDepartments);
		}
		$returnArray['product_categories'] = $productCategories;

		$productCategoryGroups = array();
		$resultSet = executeReadQuery("select * from product_category_groups where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$categorySet = executeReadQuery("select product_category_id,product_category_code from product_category_group_links join product_categories using (product_category_id) where product_category_group_id = ?", $row['product_category_group_id']);
			$productCategories = array();
			while ($categoryRow = getNextRow($categorySet)) {
				$productCategories[$categoryRow['product_category_id']] = $categoryRow['product_category_code'];
			}
			$productCategoryGroups[] = array("product_category_group_id" => $row['product_category_group_id'], "product_category_group_code" => $row['product_category_group_code'], "link_name" => $row['link_name'], "description" => $row['description'], "product_categories" => $productCategories);
		}
		$returnArray['product_category_groups'] = $productCategoryGroups;

		$productDepartments = array();
		$resultSet = executeReadQuery("select * from product_departments where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$categorySet = executeReadQuery("select product_category_id,product_category_code from product_category_departments join product_categories using (product_category_id) where product_department_id = ?", $row['product_department_id']);
			$productCategories = array();
			while ($categoryRow = getNextRow($categorySet)) {
				$productCategories[$categoryRow['product_category_id']] = $categoryRow['product_category_code'];
			}
			$categoryGroupSet = executeReadQuery("select product_category_group_id,product_category_group_code from product_category_group_departments join product_category_groups using (product_category_group_id) where product_department_id = ?", $row['product_department_id']);
			$productCategoryGroups = array();
			while ($categoryGroupRow = getNextRow($categoryGroupSet)) {
				$productCategoryGroups[$categoryGroupRow['product_category_group_id']] = $categoryGroupRow['product_category_group_code'];
			}
			$productDepartments[] = array("product_department_id" => $row['product_department_id'], "product_department_code" => $row['product_department_code'], "link_name" => $row['link_name'], "description" => $row['description'], "product_categories" => $productCategories, "product_category_groups" => $productCategoryGroups);
		}
		$returnArray['product_departments'] = $productDepartments;

		$productFacets = array();
		$resultSet = executeReadQuery("select * from product_facets where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$categorySet = executeReadQuery("select product_categories.product_category_id,product_category_code from product_facet_categories join product_categories using (product_category_id) where product_facet_id = ?", $row['product_facet_id']);
			$productCategories = array();
			while ($categoryRow = getNextRow($categorySet)) {
				$productCategories[$categoryRow['product_category_id']] = $categoryRow['product_category_code'];
			}
			$productFacets[] = array("product_facet_id" => $row['product_facet_id'], "product_facet_code" => $row['product_facet_code'], "description" => $row['description'], "product_categories" => $productCategories);
		}
		$returnArray['product_facets'] = $productFacets;
		break;

	case "get_product_taxonomy":
		$productIds = array_filter((is_array($_POST['product_ids']) ? $_POST['product_ids'] : explode(",", $_POST['product_ids'])));
		$productCodes = array_filter(explode(",", $_POST['product_codes']));

		$whereProducts = "";
		foreach ($productIds as $productId) {
			if (!empty($productId) && is_numeric($productId)) {
				$whereProducts .= (empty($whereProducts) ? "" : ",") . $productId;
			}
		}
		$whereProductCodes = "";
		foreach ($productCodes as $productCode) {
			if (!empty($productCode)) {
				$whereProductCodes .= (empty($whereProductCodes) ? "" : ",") . makeParameter($productCode);
			}
		}
		$productCategories = array();
		$resultSet = executeReadQuery("select products.product_id,product_categories.product_category_id,product_categories.product_category_code,product_code from " .
			"product_categories join product_category_links using (product_category_id) join products using (product_id) where " .
			(empty($whereProductCodes) ? "" : "product_code in (" . $whereProductCodes . ") and ") .
			(empty($whereProducts) ? "" : "product_id in (" . $whereProducts . ") and ") . "products.client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists($row['product_id'], $productCategories)) {
				$productCategories[$row['product_id']] = array("product_id" => $row['product_id'], "product_code" => $row['product_code'], "product_categories" => array());
			}
			$productCategories[$row['product_id']]['product_categories'][$row['product_category_id']] = $row['product_category_code'];
		}
		$returnArray['product_categories'] = array_values($productCategories);
		break;

	case "get_product_manufacturers":
		$productManufacturers = array();
		if (!empty($_POST['product_manufacturer_id'])) {
			$resultSet = executeReadQuery("select *,(select filename from images where image_id = contacts.image_id) filename," .
				"(select extension from images where image_id = contacts.image_id) extension,(select map_policy_code from map_policies where map_policy_id = product_manufacturers.map_policy_id) map_policy_code " .
				"from product_manufacturers join contacts using (contact_id) where product_manufacturers.client_id = ? and product_manufacturer_id = ?", $GLOBALS['gClientId'], $_POST['product_manufacturer_id']);
		} elseif (!empty($_POST['product_manufacturer_code'])) {
			$resultSet = executeReadQuery("select *,(select filename from images where image_id = contacts.image_id) filename," .
				"(select extension from images where image_id = contacts.image_id) extension,(select map_policy_code from map_policies where map_policy_id = product_manufacturers.map_policy_id) map_policy_code " .
				"from product_manufacturers join contacts using (contact_id) where product_manufacturers.client_id = ? and product_manufacturer_code = ?", $GLOBALS['gClientId'], $_POST['product_manufacturer_code']);
		} else {
			$resultSet = executeReadQuery("select *,(select filename from images where image_id = contacts.image_id) filename," .
				"(select extension from images where image_id = contacts.image_id) extension,(select map_policy_code from map_policies where map_policy_id = product_manufacturers.map_policy_id) map_policy_code " .
				"from product_manufacturers join contacts using (contact_id) where product_manufacturers.client_id = ?", $GLOBALS['gClientId']);
		}
		while ($row = getNextRow($resultSet)) {
			$distributors = array();
			$subSet = executeReadQuery("select * from product_manufacturer_distributor_dropships join product_distributors using (product_distributor_id) where product_manufacturer_id = ?", $row['product_manufacturer_id']);
			while ($subRow = getNextRow($subSet)) {
				$distributors[] = $subRow['product_distributor_code'];
			}
			$row['distributors'] = $distributors;
			$departments = array();
			$subSet = executeReadQuery("select * from product_manufacturer_dropship_exclusions join product_departments using (product_department_id) where product_manufacturer_id = ?", $row['product_manufacturer_id']);
			while ($subRow = getNextRow($subSet)) {
				$departments[] = $subRow['product_department_code'];
			}
			$row['departments'] = $departments;

			$productManufacturers[] = $row;
		}
		$returnArray['product_manufacturers'] = $productManufacturers;
		break;

	case "get_product_facet_values":
		if (empty($_POST['product_facet_code'])) {
			$productIds = array_filter((is_array($_POST['product_ids']) ? $_POST['product_ids'] : explode(",", $_POST['product_ids'])));
			$productCodes = array_filter(explode(",", $_POST['product_codes']));

			$whereProducts = "";
			foreach ($productIds as $productId) {
				if (!empty($productId) && is_numeric($productId)) {
					$whereProducts .= (empty($whereProducts) ? "" : ",") . $productId;
				}
			}
			$whereProductCodes = "";
			foreach ($productCodes as $productCode) {
				if (!empty($productCode)) {
					$whereProductCodes .= (empty($whereProductCodes) ? "" : ",") . makeParameter($productCode);
				}
			}
			$productFacetValues = array();
			$resultSet = executeReadQuery("select products.product_id,products.product_code,(select product_facet_code from product_facets where " .
				"product_facet_id = product_facet_values.product_facet_id) product_facet_code,facet_value from products join product_facet_values using (product_id) join " .
				"product_facet_options using (product_facet_option_id) where " .
				(empty($whereProductCodes) ? "" : "product_code in (" . $whereProductCodes . ") and ") .
				(empty($whereProducts) ? "" : "products.product_id in (" . $whereProducts . ") and ") . "products.client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (!array_key_exists($row['product_id'], $productFacetValues)) {
					$productFacetValues[$row['product_id']] = array("product_id" => $row['product_id'], "product_code" => $row['product_code'], "product_facet_values" => array());
				}
				$productFacetValues[$row['product_id']]['product_facet_values'][] = array("product_facet_code" => $row['product_facet_code'], "facet_value" => $row['facet_value']);
			}
			$returnArray['product_facet_values'] = array_values($productFacetValues);
		} else {
			$productFacetOptions = array();
			$resultSet = executeReadQuery("select * from product_facet_options where product_facet_id = (select product_facet_id from product_facets where product_facet_code = ? and client_id = ?)", $_POST['product_facet_code'], $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$productFacetOptions[$row['product_facet_option_id']] = $row['facet_value'];
			}
			$returnArray['product_facet_options'] = $productFacetOptions;
		}
		break;

	case "get_syncable_facets":
		$returnArray['product_facets'] = array();
		$clientId = getFieldFromId("client_id", "clients", "client_code", "COREWARE_SHOOTING_SPORTS");
		if (empty($clientId) || $clientId != $GLOBALS['gClientId']) {
			break;
		}
		$resultSet = executeQuery("select * from product_facets where client_id = ? and catalog_lock = 1 and inactive = 0 and internal_use_only = 0", $clientId);
		while ($row = getNextRow($resultSet)) {
			$returnArray['product_facets'][] = $row;
		}
		break;

	case "get_normalized_facet_values":
		$clientId = getFieldFromId("client_id", "clients", "client_code", "COREWARE_SHOOTING_SPORTS");
		$productFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_code", $_POST['product_facet_code'], "inactive = 0 and internal_use_only = 0 and client_id = ? and catalog_lock = 1", $clientId);
		if (empty($productFacetId) || empty($clientId) || $clientId != $GLOBALS['gClientId']) {
			$returnArray['error_message'] = "Invalid Product Facet";
			break;
		}

		executeQuery("delete from product_distributor_conversions where client_id = ? and table_name = 'product_facet_options' and primary_identifier not in (select product_facet_option_id from product_facet_options)", $clientId);
		executeQuery("DELETE t1 FROM product_distributor_conversions t1 INNER JOIN product_distributor_conversions t2 WHERE t1.product_distributor_conversion_id < t2.product_distributor_conversion_id AND " .
			"t1.client_id = t2.client_id and t1.product_distributor_id is null and t2.product_distributor_id is null and t1.table_name = t2.table_name and t1.original_value = t2.original_value and " .
			"t1.original_value_qualifier = t2.original_value_qualifier");
		executeQuery("delete from product_distributor_conversions where client_id = ? and table_name = 'product_facet_options' and original_value_qualifier = ? and " .
			"original_value = (select facet_value from product_facet_options where product_facet_option_id = product_distributor_conversions.primary_identifier)", $clientId, $productFacetRow['product_facet_code']);

		$productFacetCode = $_POST['product_facet_code'];
		$returnArray['product_facet_options'] = array();
		$resultSet = executeQuery("select * from product_facet_options where product_facet_id = ?",$productFacetId);
		while ($row = getNextRow($resultSet)) {
			$returnArray['product_facet_options'][$row['product_facet_option_id']] = $row['facet_value'];
		}
		$returnArray['product_facet_value_conversions'] = array();
		$resultSet = executeQuery("select original_value, primary_identifier from product_distributor_conversions where client_id = ? and table_name = 'product_facet_options' and original_value_qualifier = ?", $clientId, $productFacetCode);
		while ($row = getNextRow($resultSet)) {
			if (array_key_exists($row['primary_identifier'], $returnArray['product_facet_options'])) {
				$returnArray['product_facet_value_conversions'][$row['original_value']] = $row['primary_identifier'];
			}
		}
		$returnArray['product_facet_values'] = array();
		$resultSet = executeQuery("select upc_code,product_facet_option_id from product_data join product_facet_values using (product_id) where product_facet_id = ? and upc_code is not null", $productFacetId);
		while ($row = getNextRow($resultSet)) {
			if (array_key_exists($row['product_facet_option_id'], $returnArray['product_facet_options'])) {
				$returnArray['product_facet_values'][$row['upc_code']] = $row['product_facet_option_id'];
			}
		}
		break;

	case "get_subscriptions":
		$returnArray['subscriptions'] = array();
		$resultSet = executeQuery("select * from subscriptions where client_id = ?", $GLOBALS['gClientId']);
		$productList = "";
		while ($row = getNextRow($resultSet)) {
			$row['subscription_products'] = array();
			$productSet = executeQuery("select * from subscription_products where subscription_id = ?", $row['subscription_id']);
			while ($productRow = getNextRow($productSet)) {
				$row['subscription_products'][] = $productRow;
				if (!empty($productRow['setup_product_id'])) {
					$productList .= (empty($productList) ? "" : ",") . $productRow['setup_product_id'];
				}
			}
			$returnArray['subscriptions'][] = $row;
		}
		if (empty($productList)) {
			break;
		} else {
			$_POST['product_id'] = $productList;
		}
		$ignoreProductGroupDescription = true;

	case "search_products":
		if (!isset($ignoreProductGroupDescription)) {
			$ignoreProductGroupDescription = false;
		}
		$productCatalog = new ProductCatalog();
		$productCatalog->setIncludeNoInventoryProducts(true);
		$productCatalog->setTruncateDescriptions(false);
		$productCatalog->setIgnoreProductGroupDescription($ignoreProductGroupDescription);
		$productCatalog->setSendAllFields(true);
		$productCatalog->setSelectLimit(1000000);
		$productCatalog->setAllowInternalUseOnly(true);
		if (array_key_exists("search_text", $_POST)) {
			$productCatalog->setSearchText($_POST['search_text']);
		}
		if (array_key_exists("product_id", $_POST)) {
			$productCatalog->setSpecificProductIds($_POST['product_id']);
		}
		if (array_key_exists("show_out_of_stock", $_POST)) {
			$productCatalog->showOutOfStock($_POST['show_out_of_stock']);
		} else {
			$productCatalog->showOutOfStock(true);
		}
		if (array_key_exists("need_sidebar_info", $_POST)) {
			$productCatalog->needSidebarInfo($_POST['need_sidebar_info']);
		}
		if (array_key_exists("offset", $_POST) && is_numeric($_POST['offset'])) {
			$productCatalog->setOffset($_POST['offset']);
		}
		if (array_key_exists("limit", $_POST) && is_numeric($_POST['limit'])) {
			if ($_POST['limit'] <= $productCatalog->getOffset()) {
				$_POST['limit'] += $productCatalog->getOffset();
			}
			$productCatalog->setLimitQuery(true);
			$productCatalog->setSelectLimit($_POST['limit']);
		}
		if (array_key_exists("sort_by", $_POST)) {
			$productCatalog->setSortBy($_POST['sort_by']);
		}
		if (array_key_exists("ignore_products_without_image", $_POST)) {
			$productCatalog->ignoreProductsWithoutImages($_POST['ignore_products_without_image']);
		} else {
			$productCatalog->ignoreProductsWithoutImages(false);
		}
		$productDepartmentIds = array();
		if (array_key_exists("product_department_ids", $_POST) && !empty($_POST['product_department_ids'])) {
			if (!is_array($_POST['product_department_ids'])) {
				$_POST['product_department_ids'] = array($_POST['product_department_ids']);
			}
			foreach ($_POST['product_department_ids'] as $productDepartmentId) {
				$productDepartmentId = getReadFieldFromId("product_department_id", "product_departments", "product_department_id", $productDepartmentId);
				if (!empty($productDepartmentId) && !in_array($productDepartmentId, $productDepartmentIds)) {
					$productDepartmentIds[] = $productDepartmentId;
				}
			}
		}
		if (array_key_exists("product_department_codes", $_POST) && !empty($_POST['product_department_codes'])) {
			if (!is_array($_POST['product_department_codes'])) {
				$_POST['product_department_codes'] = array($_POST['product_department_codes']);
			}
			foreach ($_POST['product_department_codes'] as $productDepartmentCode) {
				$productDepartmentId = getReadFieldFromId("product_department_id", "product_departments", "product_department_code", $productDepartmentCode);
				if (!empty($productDepartmentId) && !in_array($productDepartmentId, $productDepartmentIds)) {
					$productDepartmentIds[] = $productDepartmentId;
				}
			}
		}
		if (array_key_exists("product_department_id", $_POST) && !empty($_POST['product_department_id'])) {
			if (!is_array($_POST['product_department_id'])) {
				$_POST['product_department_id'] = array($_POST['product_department_id']);
			}
			foreach ($_POST['product_department_id'] as $productDepartmentId) {
				$productDepartmentId = getReadFieldFromId("product_department_id", "product_departments", "product_department_id", $productDepartmentId);
				if (!empty($productDepartmentId) && !in_array($productDepartmentId, $productDepartmentIds)) {
					$productDepartmentIds[] = $productDepartmentId;
				}
			}
		}
		if (array_key_exists("product_department_code", $_POST) && !empty($_POST['product_department_code'])) {
			if (!is_array($_POST['product_department_code'])) {
				$_POST['product_department_code'] = array($_POST['product_department_code']);
			}
			foreach ($_POST['product_department_code'] as $productDepartmentCode) {
				$productDepartmentId = getReadFieldFromId("product_department_id", "product_departments", "product_department_code", $productDepartmentCode);
				if (!empty($productDepartmentId) && !in_array($productDepartmentId, $productDepartmentIds)) {
					$productDepartmentIds[] = $productDepartmentId;
				}
			}
		}
		if (!empty($productDepartmentIds)) {
			$productCatalog->setDepartments($productDepartmentIds);
		}
		$productCategoryIds = array();
		if (array_key_exists("product_category_ids", $_POST) && !empty($_POST['product_category_ids'])) {
			if (!is_array($_POST['product_category_ids'])) {
				$_POST['product_category_ids'] = array($_POST['product_category_ids']);
			}
			foreach ($_POST['product_category_ids'] as $productCategoryId) {
				$productCategoryId = getReadFieldFromId("product_category_id", "product_categories", "product_category_id", $productCategoryId);
				if (!empty($productCategoryId) && !in_array($productCategoryId, $productCategoryIds)) {
					$productCategoryIds[] = $productCategoryId;
				}
			}
		}
		if (array_key_exists("product_category_codes", $_POST) && !empty($_POST['product_category_codes'])) {
			if (!is_array($_POST['product_category_codes'])) {
				$_POST['product_category_codes'] = array($_POST['product_category_codes']);
			}
			foreach ($_POST['product_category_codes'] as $productCategoryCode) {
				$productCategoryId = getReadFieldFromId("product_category_id", "product_categories", "product_category_code", $productCategoryCode);
				if (!empty($productCategoryId) && !in_array($productCategoryId, $productCategoryIds)) {
					$productCategoryIds[] = $productCategoryId;
				}
			}
		}
		if (array_key_exists("product_category_id", $_POST) && !empty($_POST['product_category_id'])) {
			if (!is_array($_POST['product_category_id'])) {
				$_POST['product_category_id'] = array($_POST['product_category_id']);
			}
			foreach ($_POST['product_category_id'] as $productCategoryId) {
				$productCategoryId = getReadFieldFromId("product_category_id", "product_categories", "product_category_id", $productCategoryId);
				if (!empty($productCategoryId) && !in_array($productCategoryId, $productCategoryIds)) {
					$productCategoryIds[] = $productCategoryId;
				}
			}
		}
		if (array_key_exists("product_category_code", $_POST) && !empty($_POST['product_category_code'])) {
			if (!is_array($_POST['product_category_code'])) {
				$_POST['product_category_code'] = array($_POST['product_category_code']);
			}
			foreach ($_POST['product_category_code'] as $productCategoryCode) {
				$productCategoryId = getReadFieldFromId("product_category_id", "product_categories", "product_category_code", $productCategoryCode);
				if (!empty($productCategoryId) && !in_array($productCategoryId, $productCategoryIds)) {
					$productCategoryIds[] = $productCategoryId;
				}
			}
		}
		if (!empty($productCategoryIds)) {
			$productCatalog->setCategories($productCategoryIds);
		}
		$productManufacturerIds = array();
		if (array_key_exists("product_manufacturer_ids", $_POST) && !empty($_POST['product_manufacturer_ids'])) {
			if (!is_array($_POST['product_manufacturer_ids'])) {
				$_POST['product_manufacturer_ids'] = array($_POST['product_manufacturer_ids']);
			}
			foreach ($_POST['product_manufacturer_ids'] as $productManufacturerId) {
				$productManufacturerId = getReadFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $productManufacturerId);
				if (!empty($productManufacturerId) && !in_array($productManufacturerId, $productManufacturerIds)) {
					$productManufacturerIds[] = $productManufacturerId;
				}
			}
		}
		if (array_key_exists("product_manufacturer_id", $_POST) && !empty($_POST['product_manufacturer_id'])) {
			if (!is_array($_POST['product_manufacturer_id'])) {
				$_POST['product_manufacturer_id'] = array($_POST['product_manufacturer_id']);
			}
			foreach ($_POST['product_manufacturer_id'] as $productManufacturerId) {
				$productManufacturerId = getReadFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $productManufacturerId);
				if (!empty($productManufacturerId) && !in_array($productManufacturerId, $productManufacturerIds)) {
					$productManufacturerIds[] = $productManufacturerId;
				}
			}
		}
		if (!empty($productManufacturerIds)) {
			$productCatalog->setManufacturers($productManufacturerIds);
		}
		$locationIds = array();
		if (array_key_exists("location_ids", $_POST) && !empty($_POST['location_ids'])) {
			if (!is_array($_POST['location_ids'])) {
				$_POST['location_ids'] = array($_POST['location_ids']);
			}
			foreach ($_POST['location_ids'] as $locationId) {
				$locationId = getReadFieldFromId("location_id", "locations", "location_id", $locationId);
				if (!empty($locationId) && !in_array($locationId, $locationIds)) {
					$locationIds[] = $locationId;
				}
			}
		}
		if (array_key_exists("location_codes", $_POST) && !empty($_POST['location_codes'])) {
			if (!is_array($_POST['location_codes'])) {
				$_POST['location_codes'] = array($_POST['location_codes']);
			}
			foreach ($_POST['location_codes'] as $locationCode) {
				$locationId = getReadFieldFromId("location_id", "locations", "location_code", makeCode($locationCode));
				if (!empty($locationId) && !in_array($locationId, $locationIds)) {
					$locationIds[] = $locationId;
				}
			}
		}
		if (array_key_exists("location_id", $_POST) && !empty($_POST['location_id'])) {
			if (!is_array($_POST['location_id'])) {
				$_POST['location_id'] = array($_POST['location_id']);
			}
			foreach ($_POST['location_id'] as $locationId) {
				$locationId = getReadFieldFromId("location_id", "locations", "location_id", $locationId);
				if (!empty($locationId) && !in_array($locationId, $locationIds)) {
					$locationIds[] = $locationId;
				}
			}
		}
		if (array_key_exists("location_code", $_POST) && !empty($_POST['location_code'])) {
			if (!is_array($_POST['location_code'])) {
				$_POST['location_code'] = array($_POST['location_code']);
			}
			foreach ($_POST['location_code'] as $locationCode) {
				$locationId = getReadFieldFromId("location_id", "locations", "location_code", makeCode($locationCode));
				if (!empty($locationId) && !in_array($locationId, $locationIds)) {
					$locationIds[] = $locationId;
				}
			}
		}
		if (!empty($locationIds)) {
			$productCatalog->setLocations($locationIds);
		}
		$productTagIds = array();
		if (array_key_exists("product_tag_ids", $_POST) && !empty($_POST['product_tag_ids'])) {
			if (!is_array($_POST['product_tag_ids'])) {
				$_POST['product_tag_ids'] = array($_POST['product_tag_ids']);
			}
			foreach ($_POST['product_tag_ids'] as $productTagId) {
				$productTagId = getReadFieldFromId("product_tag_id", "product_tags", "product_tag_id", $productTagId);
				if (!empty($productTagId) && !in_array($productTagId, $productTagIds)) {
					$productTagIds[] = $productTagId;
				}
			}
		}
		if (array_key_exists("product_tag_codes", $_POST) && !empty($_POST['product_tag_codes'])) {
			if (!is_array($_POST['product_tag_codes'])) {
				$_POST['product_tag_codes'] = array($_POST['product_tag_codes']);
			}
			foreach ($_POST['product_tag_codes'] as $productTagCode) {
				$productTagId = getReadFieldFromId("product_tag_id", "product_tags", "product_tag_code", $productTagCode);
				if (!empty($productTagId) && !in_array($productTagId, $productTagIds)) {
					$productTagIds[] = $productTagId;
				}
			}
		}
		if (array_key_exists("product_tag_id", $_POST) && !empty($_POST['product_tag_id'])) {
			if (!is_array($_POST['product_tag_id'])) {
				$_POST['product_tag_id'] = array($_POST['product_tag_id']);
			}
			foreach ($_POST['product_tag_id'] as $productTagId) {
				$productTagId = getReadFieldFromId("product_tag_id", "product_tags", "product_tag_id", $productTagId);
				if (!empty($productTagId) && !in_array($productTagId, $productTagIds)) {
					$productTagIds[] = $productTagId;
				}
			}
		}
		if (array_key_exists("product_tag_code", $_POST) && !empty($_POST['product_tag_code'])) {
			if (!is_array($_POST['product_tag_code'])) {
				$_POST['product_tag_code'] = array($_POST['product_tag_code']);
			}
			foreach ($_POST['product_tag_code'] as $productTagCode) {
				$productTagId = getReadFieldFromId("product_tag_id", "product_tags", "product_tag_code", $productTagCode);
				if (!empty($productTagId) && !in_array($productTagId, $productTagIds)) {
					$productTagIds[] = $productTagId;
				}
			}
		}
		if (!empty($productTagIds)) {
			$productCatalog->setTags($productTagIds);
		}
		if (array_key_exists("include_product_tags_without_start_date", $_POST)) {
			$productCatalog->includeProductTagsWithNoStartDate($_POST['include_product_tags_without_start_date']);
		}

		$productTypeIds = array();
		if (array_key_exists("product_type_ids", $_POST) && !empty($_POST['product_type_ids'])) {
			if (!is_array($_POST['product_type_ids'])) {
				$_POST['product_type_ids'] = explode("|", $_POST['product_type_ids']);
			}
			foreach ($_POST['product_type_ids'] as $productTypeId) {
				$productTypeId = getReadFieldFromId("product_type_id", "product_types", "product_type_id", $productTypeId);
				if (!empty($productTypeId) && !in_array($productTypeId, $productTypeIds)) {
					$productTypeIds[] = $productTypeId;
				}
			}
		}
		if (array_key_exists("product_type_codes", $_POST) && !empty($_POST['product_type_codes'])) {
			if (!is_array($_POST['product_type_codes'])) {
				$_POST['product_type_codes'] = explode("|", $_POST['product_type_codes']);
			}
			foreach ($_POST['product_type_codes'] as $productTypeCode) {
				$productTypeId = getReadFieldFromId("product_type_id", "product_types", "product_type_code", $productTypeCode);
				if (!empty($productTypeId) && !in_array($productTypeId, $productTypeIds)) {
					$productTypeIds[] = $productTypeId;
				}
			}
		}
		if (array_key_exists("product_type_id", $_POST) && !empty($_POST['product_type_id'])) {
			if (!is_array($_POST['product_type_id'])) {
				$_POST['product_type_id'] = explode("|", $_POST['product_type_id']);
			}
			foreach ($_POST['product_type_id'] as $productTypeId) {
				$productTypeId = getReadFieldFromId("product_type_id", "product_types", "product_type_id", $productTypeId);
				if (!empty($productTypeId) && !in_array($productTypeId, $productTypeIds)) {
					$productTypeIds[] = $productTypeId;
				}
			}
		}
		if (array_key_exists("product_type_code", $_POST) && !empty($_POST['product_type_code'])) {
			if (!is_array($_POST['product_type_code'])) {
				$_POST['product_type_code'] = explode("|", $_POST['product_type_code']);
			}
			foreach ($_POST['product_type_code'] as $productTypeCode) {
				$productTypeId = getReadFieldFromId("product_type_id", "product_types", "product_type_code", $productTypeCode);
				if (!empty($productTypeId) && !in_array($productTypeId, $productTypeIds)) {
					$productTypeIds[] = $productTypeId;
				}
			}
		}
		if (!empty($productTypeIds)) {
			$productCatalog->setProductTypes($productTypeIds);
		}

		$productFacetOptionIds = array();
		if (array_key_exists("product_facet_option_ids", $_POST) && !empty($_POST['product_facet_option_ids'])) {
			if (!is_array($_POST['product_facet_option_ids'])) {
				$_POST['product_facet_option_ids'] = array($_POST['product_facet_option_ids']);
			}
			foreach ($_POST['product_facet_option_ids'] as $productFacetOptionId) {
				$productFacetOptionId = getReadFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_option_id", $productFacetOptionId);
				if (!empty($productFacetOptionId) && !in_array($productFacetOptionId, $productFacetOptionIds)) {
					$productFacetOptionIds[] = $productFacetOptionId;
				}
			}
		}
		if (array_key_exists("product_facet_option_id", $_POST) && !empty($_POST['product_facet_option_id'])) {
			if (!is_array($_POST['product_facet_option_id'])) {
				$_POST['product_facet_option_id'] = array($_POST['product_facet_option_id']);
			}
			foreach ($_POST['product_facet_option_id'] as $productFacetOptionId) {
				$productFacetOptionId = getReadFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_option_id", $productFacetOptionId);
				if (!empty($productFacetOptionId) && !in_array($productFacetOptionId, $productFacetOptionIds)) {
					$productFacetOptionIds[] = $productFacetOptionId;
				}
			}
		}
		if (!empty($productFacetOptionIds)) {
			$productCatalog->setFacetOptions($productFacetOptionIds);
		}
		$excludeIds = array();
		if (array_key_exists("exclude_product_category_ids", $_POST) && !empty($_POST['exclude_product_category_ids'])) {
			if (!is_array($_POST['exclude_product_category_ids'])) {
				$_POST['exclude_product_category_ids'] = array($_POST['exclude_product_category_ids']);
			}
			foreach ($_POST['exclude_product_category_ids'] as $excludeId) {
				$excludeId = getReadFieldFromId("product_category_id", "product_categories", "product_category_id", $excludeId);
				if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
					$excludeIds[] = $excludeId;
				}
			}
		}
		if (array_key_exists("exclude_product_category_id", $_POST) && !empty($_POST['exclude_product_category_id'])) {
			if (!is_array($_POST['exclude_product_category_id'])) {
				$_POST['exclude_product_category_id'] = array($_POST['exclude_product_category_id']);
			}
			foreach ($_POST['exclude_product_category_id'] as $excludeId) {
				$excludeId = getReadFieldFromId("product_category_id", "product_categories", "product_category_id", $excludeId);
				if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
					$excludeIds[] = $excludeId;
				}
			}
		}
		if (array_key_exists("exclude_internal_product_categories", $_POST) && $_POST['exclude_internal_product_categories']) {
			$resultSet = executeReadQuery("select * from product_categories where client_id = ? and internal_use_only = 1", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (!in_array($row['product_category_id'], $excludeIds)) {
					$excludeIds[] = $row['product_category_id'];
				}
			}
		}
		if (!empty($excludeIds)) {
			$productCatalog->setExcludeCategories($excludeIds);
		}
		$excludeIds = array();
		if (array_key_exists("exclude_product_department_ids", $_POST) && !empty($_POST['exclude_product_department_ids'])) {
			if (!is_array($_POST['exclude_product_department_ids'])) {
				$_POST['exclude_product_department_ids'] = array($_POST['exclude_product_department_ids']);
			}
			foreach ($_POST['exclude_product_department_ids'] as $excludeId) {
				$excludeId = getReadFieldFromId("product_department_id", "product_departments", "product_department_id", $excludeId);
				if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
					$excludeIds[] = $excludeId;
				}
			}
		}
		if (array_key_exists("exclude_product_department_id", $_POST) && !empty($_POST['exclude_product_department_id'])) {
			if (!is_array($_POST['exclude_product_department_id'])) {
				$_POST['exclude_product_department_id'] = array($_POST['exclude_product_department_id']);
			}
			foreach ($_POST['exclude_product_department_id'] as $excludeId) {
				$excludeId = getReadFieldFromId("product_department_id", "product_departments", "product_department_id", $excludeId);
				if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
					$excludeIds[] = $excludeId;
				}
			}
		}
		if (array_key_exists("exclude_internal_product_departments", $_POST) && $_POST['exclude_internal_product_departments']) {
			$resultSet = executeReadQuery("select * from product_departments where client_id = ? and internal_use_only = 1", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (!in_array($row['product_department_id'], $excludeIds)) {
					$excludeIds[] = $row['product_department_id'];
				}
			}
		}
		if (!empty($excludeIds)) {
			$productCatalog->setExcludeDepartments($excludeIds);
		}
		$excludeIds = array();
		if (array_key_exists("exclude_product_manufacturer_ids", $_POST) && !empty($_POST['exclude_product_manufacturer_ids'])) {
			if (!is_array($_POST['exclude_product_manufacturer_ids'])) {
				$_POST['exclude_product_manufacturer_ids'] = array($_POST['exclude_product_manufacturer_ids']);
			}
			foreach ($_POST['exclude_product_manufacturer_ids'] as $excludeId) {
				$excludeId = getReadFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $excludeId);
				if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
					$excludeIds[] = $excludeId;
				}
			}
		}
		if (array_key_exists("exclude_product_manufacturer_id", $_POST) && !empty($_POST['exclude_product_manufacturer_id'])) {
			if (!is_array($_POST['exclude_product_manufacturer_id'])) {
				$_POST['exclude_product_manufacturer_id'] = array($_POST['exclude_product_manufacturer_id']);
			}
			foreach ($_POST['exclude_product_manufacturer_id'] as $excludeId) {
				$excludeId = getReadFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $excludeId);
				if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
					$excludeIds[] = $excludeId;
				}
			}
		}
		if (array_key_exists("exclude_internal_product_manufacturers", $_POST) && $_POST['exclude_internal_product_manufacturers']) {
			$resultSet = executeReadQuery("select * from product_manufacturers where client_id = ? and internal_use_only = 1", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (!in_array($row['product_manufacturer_id'], $excludeIds)) {
					$excludeIds[] = $row['product_manufacturer_id'];
				}
			}
		}
		if (!empty($excludeIds)) {
			$productCatalog->setExcludeManufacturers($excludeIds);
		}
		$productCatalog->setAddDomainName(true);

		$productCatalog->setGetProductSalePrice(true);
		$returnArray['results'] = $productCatalog->getProducts();

		$showLocationAvailability = getPreference("RETAIL_STORE_SHOW_LOCATION_AVAILABILITY");
		if (!empty($showLocationAvailability)) {
			$productLocationAvailability = $productCatalog->getLocationAvailability();
			foreach ($returnArray['results'] as $index => $thisProduct) {
				$returnArray['results'][$index]['location_availability'] = $productCatalog::getProductAvailabilityText($thisProduct, $productLocationAvailability);
			}
		}

		$atfFirearmTypes = array();
		$resultSet = executeReadQuery("select product_category_id,atf_firearm_types.description from product_categories join atf_firearm_types using (atf_firearm_type_id) where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$atfFirearmTypes[$row['product_category_id']] = $row['description'];
		}

		$taxLocationId = "";
		$locationContactRow = array();
		if (!empty($_POST['tax_location_id'])) {
			$taxLocationId = getFieldFromId("location_id", "locations", "location_id", $_POST['tax_location_id'], "inactive = 0");
			$locationContactRow = Contact::getContact(getFieldFromId("contact_id", "locations", "location_id", $taxLocationId));
			if (empty($locationContactRow['city']) || empty($locationContactRow['state'])) {
				$taxLocationId = "";
			}
		}
		foreach ($returnArray['results'] as $index => $thisProduct) {
			$facets = array();
			$resultSet = executeReadQuery("select * from product_facet_values join product_facets using (product_facet_id) join product_facet_options using (product_facet_option_id) where product_id = ?", $thisProduct['product_id']);
			while ($row = getNextRow($resultSet)) {
				$facets[] = array("product_facet_id" => $row['product_facet_id'], "description" => $row['description'], "facet_value" => $row['facet_value']);
			}
			$thisProduct['product_facets'] = $facets;
			$tags = array();
			$resultSet = executeReadQuery("select * from product_tags join product_tag_links using (product_tag_id) where product_id = ?", $thisProduct['product_id']);
			while ($row = getNextRow($resultSet)) {
				$tags[] = array("product_tag_id" => $row['product_tag_id'], "description" => $row['description']);
			}
			$thisProduct['product_tags'] = $tags;
			$atfTypes = array();
			foreach (explode(",", $thisProduct['product_category_ids']) as $productCategoryId) {
				$thisType = $atfFirearmTypes[$productCategoryId];
				if (!empty($thisType) && !in_array($thisType, $atfTypes)) {
					$atfTypes[] = $thisType;
				}
			}
			$thisProduct['atf_firearm_types_array'] = $atfTypes;
			$thisProduct['atf_firearm_types'] = implode(",", $atfTypes);
			$thisProduct['alternate_image_urls'] = ProductCatalog::getProductAlternateImages($thisProduct['product_id'], array("product_row" => $thisProduct));

			if (!empty($taxLocationId)) {
				$orderObject = new Order();
				$thisItem = array("product_id" => $thisProduct['product_id'], "description" => $thisProduct['description'], "sale_price" => $thisProduct['sale_price'], "quantity" => 1);
				$orderObject->addOrderItem($thisItem);
				$taxCharge = $orderObject->getTax($locationContactRow['contact_id']);
				if (empty($taxCharge)) {
					$taxCharge = 0;
				}
				$thisProduct['tax_charge'] = $taxCharge;
			}

			$returnArray['results'][$index] = $thisProduct;
		}
		$returnArray['constraints'] = $productCatalog->getConstraints();
		$returnArray['result_count'] = $productCatalog->getResultCount();
		$returnArray['inventory_counts'] = $productCatalog->getInventoryCounts();
		break;

	case "get_all_product_ids":
		$productIds = array();
		$resultSet = executeReadQuery("select product_id from products where client_id = ?", $GLOBALS['gClientId']);
		$returnArray['product_count'] = $resultSet['row_count'];
		while ($row = getNextRow($resultSet)) {
			$productIds[$row['product_id']] = $row['product_id'];
		}
		$returnArray['product_ids'] = $productIds;
		break;

	case "get_product_metadata":
        unset($_POST['connection_key']);
		$cachedMetadata = getCachedData("shooting_sports_metadata_zip", jsonEncode($_POST));
		if (!empty($cachedMetadata)) {
			echo $cachedMetadata;
			exit;
		}
        $timeChangedQuery = "";
        if(!empty($_POST['changed_since'])) {
            $timeChangedQuery = " and products.time_changed > '" . date("c", strtotime($_POST['changed_since'])) . "'";
        }
		if (empty($_POST['map_prices_only'])) {
			$resultSet = executeReadQuery("select products.product_id,serializable,upc_code,description,detailed_description,(select product_manufacturer_code from product_manufacturers where product_manufacturer_id = products.product_manufacturer_id) product_manufacturer_code," .
				"image_id,(select group_concat(product_category_code) from product_category_links join product_categories using(product_category_id) where product_id = products.product_id) product_category_codes," .
				"(select group_concat(concat_ws('||', product_facet_code, facet_value) SEPARATOR '||||') from product_facet_values join product_facets using(product_facet_id) join product_facet_options using(product_facet_option_id) where " .
				"product_id = products.product_id) product_facets,manufacturer_advertised_price," .
				"(select group_concat(concat_ws('||', upc_code, related_product_type_code)) from related_products left outer join related_product_types using (related_product_type_id) join product_data using (product_id) where related_products.product_id = products.product_id) related_products," .
				"(select group_concat(state) from product_restrictions where country_id = 1000 and postal_code is null and state is not null and product_id = products.product_id) restricted_states," .
				"(select group_concat(concat_ws(',',product_distributor_code,product_code) SEPARATOR '||') from distributor_product_codes join product_distributors using (product_distributor_id) where product_id = products.product_id) distributor_product_codes," .
				"(select group_concat(image_id) from product_images where product_id = products.product_id) alternate_images from products " .
				"join product_data using(product_id) where products.client_id = ? and inactive = 0" . $timeChangedQuery, $GLOBALS['gClientId']);
		} else {
			$resultSet = executeReadQuery("select products.product_id,upc_code,manufacturer_advertised_price from products " .
				"join product_data using(product_id) where products.client_id = ? and inactive = 0", $GLOBALS['gClientId']);
		}
		$productList = array();
		$fieldArray = array();
		while ($row = getNextRow($resultSet)) {
			if (!empty($_POST['test_data']) && count($productList) >= 100) {
				break;
			}
			$upcCode = ProductCatalog::makeValidUPC($row['upc_code']);
			if (empty($upcCode) || (!empty($_POST['map_prices_only']) && !is_numeric($upcCode))) {
				continue;
			}
			if (!empty($_POST['map_prices_only'])) {
				$row = array_intersect_key($row, array("upc_code" => true, "manufacturer_advertised_price" => true));
			}
			if (empty($fieldArray)) {
				$fieldArray = array_keys($row);
			}
			if (!empty($_POST['map_prices_only']) && empty($row['manufacturer_advertised_price'])) {
				continue;
			}
			$productList[$upcCode] = array_values($row);
		}
		if (empty($_POST['uncompressed'])) {
			$metadataArray = gzencode(jsonEncode(array("values" => $productList, "keys" => $fieldArray)), 9);
			if (empty($_POST['map_prices_only'])) {
				setCachedData("shooting_sports_metadata_zip", "", $metadataArray, 4);
			}
			echo $metadataArray;
			exit;
		} else {
			$returnArray['product_list'] = $productList;
			$returnArray['field_keys'] = $fieldArray;
		}
		break;
	case "get_full_product_information":
		$getFullProductInformation = true;
	case "get_product_information":
		if (empty($_POST['product_id']) && !empty($_POST['upc_code'])) {
			$upcsToCheck = array($_POST['upc_code']);
			$upcsToCheck[] = $trimmedUpc = ltrim($_POST['upc_code'], "0");
			$upcsToCheck[] = ProductCatalog::makeValidUPC($trimmedUpc);
			$upcsToCheck[] = ProductCatalog::makeValidUPC($trimmedUpc, 13);
			$upcsToCheck[] = ProductCatalog::makeValidUPC($trimmedUpc, 14);
			foreach ($upcsToCheck as $upcCode) {
				if ($_POST['product_id'] = getFieldFromId("product_id", "product_data", "upc_code", $upcCode)) {
					break;
				}
			}
		}
		$resultSet = executeReadQuery("select *,(select product_manufacturer_code from product_manufacturers where product_manufacturer_id = products.product_manufacturer_id) product_manufacturer_code," .
			"(select description from product_manufacturers where product_manufacturer_id = products.product_manufacturer_id) product_manufacturer_description," .
			"image_id,(select group_concat(product_category_code) from product_category_links join product_categories using(product_category_id) where product_id = products.product_id) product_category_codes," .
			"(select group_concat(product_tag_code) from product_tag_links join product_tags using (product_tag_id) where product_tag_code in ('FFL_REQUIRED','CLASS_3') and product_id = products.product_id) product_tag_codes," .
			"(select group_concat(concat_ws('||', product_facet_code, facet_value) SEPARATOR '||||') from product_facet_values join product_facets using(product_facet_id) join product_facet_options using(product_facet_option_id) where " .
			"product_id = products.product_id) product_facets," .
			"(select group_concat(concat_ws('||', upc_code, related_product_type_code)) from related_products left outer join related_product_types using (related_product_type_id) join product_data using (product_id) where related_products.product_id = products.product_id) related_products," .
			"(select group_concat(state) from product_restrictions where country_id = 1000 and postal_code is null and state is not null and product_id = products.product_id) restricted_states," .
			"(select group_concat(image_id) from product_images where product_id = products.product_id) alternate_images from products " .
			"join product_data using (product_id) where products.client_id = ? and inactive = 0 and products.product_id = ?", $GLOBALS['gClientId'], $_POST['product_id']);
		while ($row = getNextRow($resultSet)) {
			if (!empty($_POST['distributor_import'])) {
				$imageIds = array();
				if (!empty($row['image_id'])) {
					$imageIds[] = $row['image_id'];
				}
				if (!empty($row['alternate_images'])) {
					$imageIds = array_merge($imageIds, array_filter(explode(",", $row['alternate_images'])));
				}
				if (!empty($imageIds)) {
					$row['image_id'] = "";
					$row['alternate_images'] = "";
					foreach ($imageIds as $imageId) {
						$imageId = getFieldFromId("image_id", "images", "image_id", $imageId, "source_id is null");
						if (!empty($imageId)) {
							if (empty($row['image_id'])) {
								$row['image_id'] = $imageId;
							} else {
								$row['alternate_images'] .= (empty($row['alternate_images']) ? "" : ",") . $imageId;
							}
						}
					}
				}
			}
			$returnArray['product_information'] = $row;
		}
		if (!$getFullProductInformation) {
			break;
		}
		# full product information:
		# 1. product prices
		# 2. Media
		# 3. Custom fields
		# 4. add-ons
		$productId = $_POST['product_id'];
		$pricesSet = executeQuery("select product_prices.*, product_price_types.product_price_type_code from product_prices join product_price_types using (product_price_type_id) " .
			"where location_id is null and user_type_id is null and product_id = ?", $productId);
		$productPrices = array();
		while ($pricesRow = getNextRow($pricesSet)) {
			$productPrices[] = $pricesRow;
		}
		$returnArray['product_information']['product_prices'] = $productPrices;

		$productVideos = array();
		$mediaSet = executeQuery("select product_videos.*, media.*, media_services.media_service_code, product_videos.description as product_video_description from product_videos join media using (media_id) join media_services using (media_service_id) where product_id = ?", $productId);
		while ($mediaRow = getNextRow($mediaSet)) {
			$productVideos[] = $mediaRow;
		}
		$returnArray['product_information']['product_videos'] = $productVideos;

		$productCustomFields = array();
		$customFieldsSet = executeQuery("select custom_fields.*, custom_field_types.custom_field_type_code from product_custom_fields join custom_fields using (custom_field_id) " .
			"join custom_field_types using (custom_field_type_id) where product_id = ?", $productId);
		while ($customFieldRow = getNextRow($customFieldsSet)) {
			$customFieldControlsSet = executeQuery("select * from custom_field_controls where custom_field_id = ?", $customFieldRow['custom_field_id']);
			while ($customFieldControlsRow = getNextRow($customFieldControlsSet)) {
				$customFieldRow['custom_field_controls'][] = $customFieldControlsRow;
			}
			$customFieldChoicesSet = executeQuery("select * from custom_field_choices where custom_field_id = ?", $customFieldRow['custom_field_id']);
			while ($customFieldChoicesRow = getNextRow($customFieldChoicesSet)) {
				$customFieldRow['custom_field_choices'][] = $customFieldChoicesRow;
			}
			$productCustomFields[] = $customFieldRow;
		}
		$returnArray['product_information']['product_custom_fields'] = $productCustomFields;

		$customFields = array();
		$customFieldsSet = executeQuery("select custom_fields.*, custom_field_data.*, custom_field_types.custom_field_type_code from custom_fields join custom_field_data using (custom_field_id) " .
			"join custom_field_types using (custom_field_type_id) where custom_field_type_code = 'PRODUCTS' and primary_identifier = ?", $productId);
		while ($customFieldRow = getNextRow($customFieldsSet)) {
			$customFieldControlsSet = executeQuery("select * from custom_field_controls where custom_field_id = ?", $customFieldRow['custom_field_id']);
			while ($customFieldControlsRow = getNextRow($customFieldControlsSet)) {
				$customFieldRow['custom_field_controls'][] = $customFieldControlsRow;
			}
			$customFieldChoicesSet = executeQuery("select * from custom_field_choices where custom_field_id = ?", $customFieldRow['custom_field_id']);
			while ($customFieldChoicesRow = getNextRow($customFieldChoicesSet)) {
				$customFieldRow['custom_field_choices'][] = $customFieldChoicesRow;
			}
			$customFields[] = $customFieldRow;
		}
		$returnArray['product_information']['custom_fields'] = $customFields;

		$productAddons = array();
		$addonsSet = executeQuery("select * from product_addons where product_id = ?", $productId);
		while ($addonsRow = getNextRow($addonsSet)) {
			$productAddons[] = $addonsRow;
		}
		$returnArray['product_information']['product_addons'] = $productAddons;
		break;

	case "update_product_inventory":
		if (is_array($_POST['product_inventory_updates'])) {
			$productInventoryUpdates = $_POST['product_inventory_updates'];
		} else {
			$productInventoryUpdates = array();
		}
		if (array_key_exists("quantity", $_POST)) {
			$productInventoryUpdates[] = $_POST;
		}
		$salePriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_code", "sale_price");
		$GLOBALS['gPrimaryDatabase']->startTransaction();
		$errorProducts = array();
		foreach ($productInventoryUpdates as $thisInventoryUpdate) {
			if (empty($thisInventoryUpdate['product_id']) && !empty($thisInventoryUpdate['upc_code'])) {
				$upcCode = ProductCatalog::makeValidUPC($thisInventoryUpdate['upc_code']);
				if (!empty($upcCode)) {
					$thisInventoryUpdate['product_id'] = getFieldFromId("product_id", "product_data", "upc_code", $upcCode);
				}
			}
			if (empty($thisInventoryUpdate['product_id']) && !empty($thisInventoryUpdate['product_code'])) {
				$thisInventoryUpdate['product_id'] = getFieldFromId("product_id", "products", "product_code", makeCode($thisInventoryUpdate['product_code']));
			}
			if (empty($thisInventoryUpdate['product_id'])) {
				$thisInventoryUpdate['error_message'] = "Invalid product" . "-" . __LINE__;
				$errorProducts[] = $thisInventoryUpdate;
				continue;
			}

			if (empty($thisInventoryUpdate['location_id']) && !empty($thisInventoryUpdate['location_code'])) {
				$thisInventoryUpdate['location_id'] = getFieldFromId("location_id", "locations", "location_code", makeCode($thisInventoryUpdate['location_code']));
				if (empty($thisInventoryUpdate['location_id'])) {
					$thisInventoryUpdate['location_id'] = getFieldFromId("location_id", "locations", "description", $thisInventoryUpdate['location_code']);
				}
			}
			$thisInventoryUpdate['location_id'] = getFieldFromId("location_id", "locations", "location_id", $thisInventoryUpdate['location_id']);

			if (empty($thisInventoryUpdate['location_id'])) {
				$thisInventoryUpdate['error_message'] = "Invalid location" . "-" . __LINE__;
				$errorProducts[] = $thisInventoryUpdate;
				continue;
			}

			if ($thisInventoryUpdate['quantity'] < 0) {
				$thisInventoryUpdate['quantity'] = 0;
			}

			if (empty($thisInventoryUpdate['inventory_adjustment_type_id']) && !empty($thisInventoryUpdate['inventory_adjustment_type_code'])) {
				$thisInventoryUpdate['inventory_adjustment_type_id'] = getFieldFromId("inventory_adjustment_type_id", "inventory_adjustment_types", "inventory_adjustment_type_code", $thisInventoryUpdate['inventory_adjustment_type_code']);
			}
			if (empty($thisInventoryUpdate['inventory_adjustment_type_id'])) {
				$thisInventoryUpdate['error_message'] = "Invalid inventory adjustment type" . "-" . __LINE__;
				$errorProducts[] = $thisInventoryUpdate;
				continue;
			}

# UPC code or Product Code, the location code, Adjustment Type, Quantity, Cost and Notes.

			$productInventoryRow = getRowFromId("product_inventories", "product_id", $thisInventoryUpdate['product_id'], "location_id = ?", $thisInventoryUpdate['location_id']);
			$adjustmentType = getFieldFromId("adjustment_type", "inventory_adjustment_types", "inventory_adjustment_type_id", $thisInventoryUpdate['inventory_adjustment_type_id']);
			$quantity = $productInventoryRow['quantity'];
			if (empty($quantity)) {
				$quantity = 0;
			}
			switch ($adjustmentType) {
				case "A":
					$quantity += $thisInventoryUpdate['quantity'];
					break;
				case "R":
					$quantity = $thisInventoryUpdate['quantity'];
					break;
				case "S":
					$quantity -= $thisInventoryUpdate['quantity'];
					break;
			}
			$quantity = max($quantity, 0);
			$totalCost = "";
			if (strlen($thisInventoryUpdate['total_cost']) > 0 && $adjustmentType != "S") {
				$totalCost = $thisInventoryUpdate['total_cost'];
			} elseif (strlen($thisInventoryUpdate['cost']) > 0 && $adjustmentType != "S") {
				$totalCost = $thisInventoryUpdate['cost'] * $thisInventoryUpdate['quantity'];
			}
			if ($totalCost <= 0) {
				$totalCost = "";
			}
			$productInventoryId = $productInventoryRow['product_inventory_id'];

			if (empty($productInventoryId)) {
				$insertSet = executeQuery("insert into product_inventories (product_id, location_id, bin_number, quantity, reorder_level, replenishment_level) values(?,?,?,?,?, ?)",
					$thisInventoryUpdate['product_id'], $thisInventoryUpdate['location_id'], $thisInventoryUpdate['bin_number'], $quantity, $thisInventoryUpdate['reorder_level'], $thisInventoryUpdate['replenishment_level']);
				if (!empty($insertSet['sql_error'])) {
					$thisInventoryUpdate['error_message'] = getSystemMessage("basic", $insertSet['sql_error']) . "-" . __LINE__;
					$errorProducts[] = $thisInventoryUpdate;
					continue;
				}
				$productInventoryId = $insertSet['insert_id'];
				$affectedRows = 1;
			} else {
				if (strlen($_POST['reorder_level']) > 0 || strlen($_POST['replenishment_level']) > 0) {
					$parameters = array();
					$setWhere = "";
					if (strlen($_POST['reorder_level']) > 0) {
						$parameters[] = $_POST['reorder_level'];
						$setWhere = "reorder_level = ?";
					}
					if (strlen($_POST['replenishment_level']) > 0) {
						$parameters[] = $_POST['replenishment_level'];
						$setWhere = (empty($setWhere) ? "" : ",") . "replenishment_level = ?";
					}
					if (strlen($_POST['bin_number']) > 0) {
						$parameters[] = $_POST['bin_number'];
						$setWhere = (empty($setWhere) ? "" : ",") . "bin_number = ?";
					}
					$parameters[] = $productInventoryId;
					if (!empty($setWhere)) {
						$updateSet = executeQuery("update product_inventories set " . $setWhere . " where product_inventory_id = ?", $parameters);
					}
				}
				if ($productInventoryRow['quantity'] != $quantity) {
					$updateSet = executeQuery("update product_inventories set quantity = ? where product_inventory_id = ?", $quantity, $productInventoryId);
					if (!empty($updateSet['sql_error'])) {
						$thisInventoryUpdate['error_message'] = getSystemMessage("basic", $updateSet['sql_error']) . "-" . __LINE__;
						$errorProducts[] = $thisInventoryUpdate;
						continue;
					}
					$affectedRows = $updateSet['affected_rows'];
				} else {
					$affectedRows = 0;
				}
			}
			removeCachedData("product_prices", $thisInventoryUpdate['product_id']);
			if ($quantity > 0) {
				executeQuery("delete from product_category_links where product_id = ? and product_category_id in (select product_category_id from product_categories where product_category_code = 'DISCONTINUED')", $thisInventoryUpdate['product_id']);
			}
			if ($affectedRows > 0 || !empty($thisInventoryUpdate['notes'])) {
				if (empty($thisInventoryUpdate['notes'])) {
					$thisInventoryUpdate['notes'] = "API update";
				}
				$insertSet = executeQuery("insert into product_inventory_log (product_inventory_id, inventory_adjustment_type_id, user_id, log_time, quantity, total_cost, notes) values " .
					"(?,?,?,now(),?,?,?)", $productInventoryId, $thisInventoryUpdate['inventory_adjustment_type_id'], $GLOBALS['gUserId'], $thisInventoryUpdate['quantity'], $totalCost, $thisInventoryUpdate['notes']);
				if (!empty($insertSet['sql_error'])) {
					$thisInventoryUpdate['error_message'] = getSystemMessage("basic", $insertSet['sql_error']) . "-" . __LINE__;
					$errorProducts[] = $thisInventoryUpdate;
					continue;
				}
			} elseif (is_numeric($totalCost) && $totalCost > 0 && is_numeric($quantity) && $quantity > 0) {
				$updateCost = round($totalCost / $quantity, 2);
				$logSet = executeQuery("select * from product_inventory_log where product_inventory_id = ? order by product_inventory_log_id desc limit 1", $productInventoryId);
				if ($logRow = getNextRow($logSet)) {
					$logCost = ($logRow['quantity'] > 0 ? round($logRow['total_cost'] / $logRow['quantity'], 2) : 0);
					if ($updateCost != $logCost) {
						$thisInventoryUpdate['notes'] = "API update";
						$insertSet = executeQuery("insert into product_inventory_log (product_inventory_id, inventory_adjustment_type_id, user_id, log_time, quantity, total_cost, notes) values " .
							"(?,?,?,now(),?,?,?)", $productInventoryId, $thisInventoryUpdate['inventory_adjustment_type_id'], $GLOBALS['gUserId'], $thisInventoryUpdate['quantity'], $totalCost, $thisInventoryUpdate['notes']);
						if (!empty($insertSet['sql_error'])) {
							$thisInventoryUpdate['error_message'] = getSystemMessage("basic", $insertSet['sql_error']) . "-" . __LINE__;
							$errorProducts[] = $thisInventoryUpdate;
							continue;
						}

					}
				}
			}
			ProductCatalog::calculateProductCost($thisInventoryUpdate['product_id'], "Product inventory updated in API");

			if (!empty($thisInventoryUpdate['remove_product_sale_prices'])) {
				executeQuery("delete from product_prices where product_id = ? and product_price_type_id = ?", $thisInventoryUpdate['product_id'], $salePriceTypeId);
				removeCachedData("product_prices", $thisInventoryUpdate['product_id']);
			}
		}
		if (!empty($errorProducts)) {
			$returnArray['errors'] = $errorProducts;
			if (!empty($_POST['cancel_on_error'])) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = "Errors found; update cancelled. See Errors for details." . "-" . __LINE__;
				break;
			}
			$returnArray['error_message'] = "Some inventory updates failed. See Errors for details." . "-" . __LINE__;
		}
		$GLOBALS['gPrimaryDatabase']->commitTransaction();
		break;

	case "get_locations":
		$locationArray = array();
		$resultSet = executeReadQuery("select * from locations where client_id = ?" . (empty($_POST['location_code']) ? "" : " and location_code = " . makeParameter($_POST['location_code'])), $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$locationArray[] = $row;
		}
		$returnArray['locations'] = $locationArray;
		break;

	case "get_product_waiting":
		if (empty($_POST['product_id'])) {
			$upcCode = ProductCatalog::makeValidUPC($_POST['upc_code']);
			$productId = getReadFieldFromId("product_id", "product_data", "upc_code", $upcCode);
		} else {
			$productId = getReadFieldFromId("product_id", "products", "product_id", $_POST['product_id']);
		}
		if (empty($productId)) {
			$returnArray['error_message'] = "Invalid product" . "-" . __LINE__;
			break;
		}
		$returnArray['waiting_quantity'] = ProductCatalog::getWaitingToShipQuantity($productId);
		break;

	case "get_product_inventory":
		$productId = "";
		$upcCodes = array();
		$whereStatement = "";
		if (!empty($_POST['product_id'])) {
			$productId = getReadFieldFromId("product_id", "products", "product_id", $_POST['product_id']);
		} elseif (!empty($_POST['upc_code'])) {
			$upcCode = ProductCatalog::makeValidUPC($_POST['upc_code']);
			$productId = getReadFieldFromId("product_id", "product_data", "upc_code", $upcCode);
		} elseif (!empty($_POST['upc_codes']) && is_array($_POST['upc_codes'])) {
			foreach ($_POST['upc_codes'] as $upcCode) {
				$whereStatement .= (empty($whereStatement) ? "" : ",") . "?";
				$upcCodes[] = $upcCode;
			}
		}
		if (empty($productId) && empty($upcCodes)) {
			$returnArray['error_message'] = "Invalid product" . "-" . __LINE__;
			break;
		}
		$returnArray['product_inventories'] = array();
		$resultSet = executeReadQuery("select *,(select upc_code from product_data where product_id = product_inventories.product_id) upc_code,(select description from product_distributors where product_distributor_id = locations.product_distributor_id) product_distributor_description from product_inventories join locations using(location_id) where inactive = 0 and (product_distributor_id is null or primary_location = 1) and " .
			(empty($upcCodes) ? "product_id = ?" : "product_id in (select product_id from product_data where client_id = ? and upc_code in (" . $whereStatement . "))"),
			(empty($upcCodes) ? $productId : array_merge(array($GLOBALS['gClientId']), $upcCodes)));
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['product_distributor_id'])) {
				$row['description'] = $row['product_distributor_description'];
			}
			unset($row['product_distributor_description']);
			$row['cost'] = ProductCatalog::getLocationBaseCost($row['product_id'], $row['location_id'], $row);
			$returnArray['product_inventories'][] = $row;
		}
		break;
	case "get_product_inventory_log":
		$locationId = getReadFieldFromId("location_id", "locations", "location_code", makeCode($_POST['location_code']));
		$logTime = date("Y-m-d H:i:s", strtotime($_POST['log_time']));
		if (empty($_POST['product_id'])) {
			$upcCode = ProductCatalog::makeValidUPC($_POST['upc_code']);
			$productId = getReadFieldFromId("product_id", "product_data", "upc_code", $upcCode);
		} else {
			$productId = getReadFieldFromId("product_id", "products", "product_id", $_POST['product_id']);
		}
		if (empty($productId)) {
			$returnArray['error_message'] = "Invalid product" . "-" . __LINE__;
			break;
		}
		$returnArray['product_inventory_log'] = array();
		$resultSet = executeReadQuery("select * from product_inventories join product_inventory_log using(product_inventory_id) where product_id = ?" .
			(empty($locationId) ? "" : " and location_id = " . $locationId) . (empty($_POST['log_time']) ? "" : " and log_time > '" . $logTime . "'"), $productId);
		while ($row = getNextRow($resultSet)) {
			$returnArray['product_inventory_log'][] = $row;
		}
		break;

	case "get_inventory_adjustment_types":
		$inventoryAdjustmentTypeArray = array();
		$resultSet = executeReadQuery("select * from inventory_adjustment_types where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$inventoryAdjustmentTypeArray[] = $row;
		}
		$returnArray['inventory_adjustment_types'] = $inventoryAdjustmentTypeArray;
		break;

	case "create_gift_card":
		$giftCard = false;
		$giftCardId = false;

		// Check if updating of existing gift cards with a balance of zero is allowed
		$preference = getPreference("ALLOW_EXISTING_GIFT_CARD_UPDATE_BALANCE");

		if (!empty($preference)) {

			if (!empty($_POST['gift_card_number'])) {
				$giftCardNumber = $_POST['gift_card_number'];

				if ($_POST['amount'] <= 0 || !is_numeric($_POST['amount'])) {
					$returnArray['error_message'] = "Invalid Amount" . " - " . __LINE__;
					break;
				}

                $giftCardId = GiftCard::lookupGiftCard($giftCardNumber);

				if (!empty($giftCardId)) {

					$giftCardArray = array(
						'gift_card_number' => $giftCardNumber,
						'gift_card_pin'    => $_POST['gift_card_pin'] ?? null // Use null if PIN is not provided
					);
					$giftCard = new GiftCard($giftCardArray);

					$giftCardBalance = $giftCard->getBalance();

					if (!empty($giftCardBalance) && $giftCardBalance == 0) {

						// If PIN is provided, update it
						if (!empty($_POST['gift_card_pin'])) {
							$resultSet = executeQuery("UPDATE gift_cards SET balance = ?, gift_card_pin = ? WHERE gift_card_id = ?", $_POST['amount'], $_POST['gift_card_pin'], $giftCardId);
						} else {
							$resultSet = executeQuery("UPDATE gift_cards SET balance = ? WHERE gift_card_id = ?", $_POST['amount'], $giftCardId);
						}

						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = "Problem Updating Gift Card" . " - " . __LINE__;
							break;
						}

					} else {
						// Log error or set an error message saying gift card balance is not zero
						$returnArray['error_message'] = "Gift Card found, but balance is not zero" . " - " . __LINE__;
						break;
					}
				}
			}
		}

		if (!$giftCardId) {
			$giftCard = new GiftCard();
			$giftCardId = $giftCard->createGiftCard($_POST['gift_card_number'], "API created gift card");
			if (empty($giftCardId)) {
				$returnArray['error_message'] = $giftCard->getErrorMessage() . "-" . __LINE__;
				break;
			}
			if ($_POST['amount'] <= 0 || !is_numeric($_POST['amount'])) {
				$returnArray['error_message'] = "Invalid Amount" . "-" . __LINE__;
				break;
			}
			if (!$giftCard->adjustBalance(true, $_POST['amount'])) {
				$returnArray['error_message'] = "Unable to set balance" . "-" . __LINE__;
				break;
			}
		}

		$returnArray['gift_card_number'] = $giftCard->getGiftCardNumber();
		$returnArray['gift_card_id'] = $giftCardId;
		break;

	case "get_gift_card_balance":
	case "adjust_gift_card_balance":
		$giftCard = false;
		if (!empty($_POST['gift_card_id'])) {
			$giftCard = new GiftCard(array("gift_card_id" => $_POST['gift_card_id']));
            if (!$giftCard->isValid()) {
                $returnArray['error_message'] = "Invalid Gift Card ID " . $_POST['gift_card_id'];
                break;
            }
		}
		if (empty($giftCard) && !empty($_POST['gift_card_number'])) {
			$giftCard = new GiftCard(array("gift_card_number" => $_POST['gift_card_number'], "gift_card_pin"=>$_POST['gift_card_pin']));
            if (!$giftCard->isValid()) {
                $returnArray['error_message'] = "Invalid Gift Card Number " . $_POST['gift_card_number'];
                break;
            }
		}
        if (empty($giftCard) && !empty($_POST['user_id']) || !empty($_POST['user_name'])) {
            $userId = $_POST['user_id'] ?: getFieldFromId("user_id", "users", "user_name", $_POST['user_name']);
            $giftCard = new GiftCard(array("user_id" => $userId));
            if (!$giftCard->isValid()) {
                $returnArray['error_message'] = "Invalid Gift Card User " . $_POST['user_id'] ?: $_POST['user_name'];
                break;
            }
        }
        if(empty($giftCard)){
            $returnArray['error_message'] = "Invalid Gift Card - gift_card_id, gift_card_number, or user is required";
            break;
        }
		$balance = $giftCard->getBalance();
		if ($_POST['action'] == "get_gift_card_balance") {
			$returnArray['balance'] = $balance;
			break;
		}
		if(!$giftCard->adjustBalance($_POST['set_balance'], $_POST['amount'], ($_POST['set_balance'] ? "API Set Balance" : ($_POST['amount'] < 0 ? "API Reduction" : "API Increase")))){
            $returnArray['error_message'] = $giftCard->getErrorMessage();
            break;
        }
		$returnArray['balance'] = $giftCard->getBalance();
		break;

	case "import_product":
		if (array_key_exists("multiple_products", $_POST)) {
			$productImportArray = $_POST['multiple_products'];
			if (!is_array($productImportArray)) {
				if (substr($productImportArray, 0, 1) == "[") {
					$productImportArray = json_decode($productImportArray, true);
				} else {
					$productImportArray = array();
				}
			}
		} else {
			$productImportArray = array($_POST);
		}

		$singleProduct = (count($productImportArray) == 1);
		$returnArray['product_index_errors'] = array();
		$returnArray['product_ids'] = array();
		foreach ($productImportArray as $postIndex => $thisPostVariables) {
			if (!empty(getPreference("DISABLE_API_PRODUCT_CATEGORY_UPDATES"))) {
				$thisPostVariables['product_categories'] = $thisPostVariables['remove_product_categories'] = $thisPostVariables['product_category_ids'] = $thisPostVariables['remove_product_category_ids'] = "";
			}
			$productCategories = array();
			$removeProductCategories = array();
			$productTags = array();
			$removeProductTags = array();

			$upcCode = ProductCatalog::makeValidUPC($thisPostVariables['upc_code']);
			$isbn = ProductCatalog::makeValidISBN($thisPostVariables['isbn']);
			$isbn13 = ProductCatalog::makeValidISBN13($thisPostVariables['isbn_13']);
			$productCode = makeCode($thisPostVariables['product_code'], array("allow_dash" => true));
			if (empty($upcCode) && empty($productCode)) {
				$returnArray['error_message'] = "Product Code or UPC is required" . "-" . __LINE__;
				$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
				continue;
			}
			if (!empty($thisPostVariables['start_date']) && date("Y-m-d", strtotime($thisPostVariables['start_date'])) < '1961-07-23') {
				$returnArray['error_message'] = "Price start date is out of range" . "-" . __LINE__;
				$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
				continue;
			}
			if (!empty($thisPostVariables['end_date']) && date("Y-m-d", strtotime($thisPostVariables['end_date'])) < '1961-07-23') {
				$returnArray['error_message'] = "Price end date is out of range" . "-" . __LINE__;
				$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
				continue;
			}

			$productId = "";
			if (!empty($thisPostVariables['product_id'])) {
				$productId = getFieldFromId("product_id", "products", "product_id", $thisPostVariables['product_id']);
				if (empty($productId)) {
					$returnArray['error_message'] = "Invalid Product ID" . "-" . __LINE__;
					break;
				}
			}
			if (empty($productId)) {
				if (empty($upcCode)) {
					$productId = getFieldFromId("product_id", "products", "product_code", $productCode);
				} else {
					$resultSet = executeQuery("select product_id from product_data where upc_code = ? and client_id = ?", $upcCode, $GLOBALS['gClientId']);
					$row = getNextRow($resultSet);
					$productId = $row['product_id'];
				}
			}

			if (empty($productId) && empty($thisPostVariables['description'])) {
				$returnArray['error_message'] = "Description is required" . "-" . __LINE__;
				$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
				continue;
			}

			if (!empty($productCode)) {
				$checkProductId = getFieldFromId("product_id", "products", "product_code", $productCode);
				if (!empty($checkProductId) && $productId != $checkProductId) {
					$returnArray['error_message'] = "Product Code already exists" . "-" . __LINE__;
					$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
					continue;
				}
			}

			if (!empty($thisPostVariables['product_categories'])) {
				$categories = explode("|", $thisPostVariables['product_categories']);
				foreach ($categories as $thisCategory) {
					$thisCategory = trim($thisCategory);
					if (!array_key_exists($thisCategory, $productCategories) && !empty($thisCategory)) {
						$productCategories[$thisCategory] = "";
					}
				}
			}
			if (!empty($thisPostVariables['remove_product_categories'])) {
				$categories = explode("|", $thisPostVariables['remove_product_categories']);
				foreach ($categories as $thisCategory) {
					$thisCategory = trim($thisCategory);
					if (!array_key_exists($thisCategory, $removeProductCategories) && !empty($thisCategory)) {
						$removeProductCategories[$thisCategory] = "";
					}
				}
			}
			if (!empty($thisPostVariables['product_tags'])) {
				$tags = explode("|", $thisPostVariables['product_tags']);
				foreach ($tags as $thisTag) {
					$thisTag = trim($thisTag);
					if (!array_key_exists($thisTag, $productTags) && !empty($thisTag)) {
						$productTags[$thisTag] = "";
					}
				}
			}
			if (!empty($thisPostVariables['remove_product_tags'])) {
				$tags = explode("|", $thisPostVariables['remove_product_tags']);
				foreach ($tags as $thisTag) {
					$thisTag = trim($thisTag);
					if (!array_key_exists($thisTag, $removeProductTags) && !empty($thisTag)) {
						$removeProductTags[$thisTag] = "";
					}
				}
			}
			if (!empty($thisPostVariables['image_urls'])) {
				$imageUrls = explode("|", str_replace(",", "|", $thisPostVariables['image_urls']));
				foreach ($imageUrls as $thisUrl) {
					$thisUrl = trim($thisUrl);
					if (substr($thisUrl, 0, 4) != "http") {
						$returnArray['error_message'] = "Invalid image URL(s)" . "-" . __LINE__;
						$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
						continue 2;
					}
				}
			}

			$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $thisPostVariables['product_manufacturer_id']);
			if (empty($productManufacturerId)) {
				$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_code", $thisPostVariables['product_manufacturer_code']);
			}
			if (empty($productManufacturerId)) {
				$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "description", $thisPostVariables['product_manufacturer_code']);
			}
			if (empty($productManufacturerId) && (!empty($thisPostVariables['product_manufacturer_code']) || !empty($thisPostVariables['product_manufacturer_id']))) {
				$returnArray['error_message'] = "Invalid Product Manufacturer" . "-" . __LINE__;
				$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
				continue;
			}

			$productTypeId = getFieldFromId("product_type_id", "product_types", "product_type_id", $thisPostVariables['product_type_id']);
			if (empty($productTypeId)) {
				$productTypeId = getFieldFromId("product_type_id", "product_types", "product_type_code", $thisPostVariables['product_type_code']);
			}
			if (empty($productTypeId)) {
				$productTypeId = getFieldFromId("product_type_id", "product_types", "description", $thisPostVariables['product_type_code']);
			}
			if (empty($productTypeId) && (!empty($thisPostVariables['product_type_code']) || !empty($thisPostVariables['product_type_id']))) {
				$returnArray['error_message'] = "Invalid Product Type" . "-" . __LINE__;
				$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
				continue;
			}

			$productPriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_id", $thisPostVariables['product_price_type_id']);
			if (empty($productPriceTypeId)) {
				$productPriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_code", $thisPostVariables['product_price_type_code']);
			}
			if (empty($productPriceTypeId)) {
				$productPriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "description", $thisPostVariables['product_price_type_code']);
				if (strtolower($thisPostVariables['product_price_type_code']) == "sale_price") {
					$insertSet = executeQuery("insert into product_price_types (client_id,product_price_type_code,description) values (?,'SALE_PRICE','Sale Price')", $GLOBALS['gClientId']);
					$salePriceTypeId = $insertSet['insert_id'];
				}
			}
			if (empty($productPriceTypeId) && (!empty($thisPostVariables['product_price_type_code']) || !empty($thisPostVariables['product_price_type_id']))) {
				$returnArray['error_message'] = "Invalid Product Price Type" . "-" . __LINE__;
				$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
				continue;
			}

			$locationId = getFieldFromId("location_id", "locations", "location_id", $thisPostVariables['location_id']);
			if (empty($locationId)) {
				$locationId = getFieldFromId("location_id", "locations", "location_code", makeCode($thisPostVariables['location_code']));
			}
			if (empty($locationId)) {
				$locationId = getFieldFromId("location_id", "locations", "description", $thisPostVariables['location_code']);
			}
			if (empty($locationId) && (!empty($thisPostVariables['location_code']) || !empty($thisPostVariables['location_id']))) {
				$returnArray['error_message'] = "Invalid Location" . "-" . __LINE__;
				$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
				continue;
			}

			foreach ($productCategories as $thisCategory => $productCategoryId) {
				$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_code", makeCode($thisCategory));
				if (empty($productCategoryId)) {
					$productCategoryId = getFieldFromId("product_category_id", "product_categories", "description", $thisCategory);
				}
				if (empty($productCategoryId)) {
					$returnArray['error_message'] = "Invalid Category" . "-" . __LINE__;
					$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
					continue 2;
				} else {
					$productCategories[$thisCategory] = $productCategoryId;
				}
			}

			if (!empty($thisPostVariables['product_category_ids'])) {
				if (!is_array($thisPostVariables['product_category_ids'])) {
					$categoryIds = explode("|", $thisPostVariables['product_category_ids']);
				} else {
					$categoryIds = $thisPostVariables['product_category_ids'];
				}
				foreach ($categoryIds as $thisCategoryId) {
					if (!empty($thisCategoryId) && !in_array($thisCategoryId, $productCategories)) {
						$productCategories[] = $thisCategoryId;
					}
				}
			}

			foreach ($removeProductCategories as $thisCategory => $productCategoryId) {
				$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_code", makeCode($thisCategory));
				if (empty($productCategoryId)) {
					$productCategoryId = getFieldFromId("product_category_id", "product_categories", "description", $thisCategory);
				}
				if (empty($productCategoryId)) {
					$returnArray['error_message'] = "Invalid Category" . "-" . __LINE__;
					$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
					continue 2;
				} else {
					$removeProductCategories[$thisCategory] = $productCategoryId;
				}
			}

			if (!empty($thisPostVariables['remove_product_category_ids'])) {
				if (!is_array($thisPostVariables['product_category_ids'])) {
					$categoryIds = explode("|", $thisPostVariables['remove_product_category_ids']);
				} else {
					$categoryIds = $thisPostVariables['remove_product_category_ids'];
				}
				foreach ($categoryIds as $thisCategoryId) {
					if (!empty($thisCategoryId) && !in_array($thisCategoryId, $removeProductCategories)) {
						$removeProductCategories[] = $thisCategoryId;
					}
				}
			}

			foreach ($productTags as $thisTag => $productTagId) {
				$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", makeCode($thisTag));
				if (empty($productTagId)) {
					$productTagId = getFieldFromId("product_tag_id", "product_tags", "description", $thisTag);
				}
				if (empty($productTagId)) {
					$returnArray['error_message'] = "Invalid Tags" . "-" . __LINE__;
					$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
					continue 2;
				} else {
					$productTags[$thisTag] = $productTagId;
				}
			}

			foreach ($removeProductTags as $thisTag => $productTagId) {
				$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", makeCode($thisTag));
				if (empty($productTagId)) {
					$productTagId = getFieldFromId("product_tag_id", "product_tags", "description", $thisTag);
				}
				if (empty($productTagId)) {
					$returnArray['error_message'] = "Invalid Tags" . "-" . __LINE__;
					$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
					continue 2;
				} else {
					$removeProductTags[$thisTag] = $productTagId;
				}
			}

			$GLOBALS['gPrimaryDatabase']->startTransaction();

			$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED");
			$class3ProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "CLASS_3");
			ProductCatalog::getInventoryAdjustmentTypes();

			if (empty($thisPostVariables['link_name']) && empty($productId)) {
				$useProductNumber = 0;
				do {
					$useLinkName = makeCode($thisPostVariables['description'], array("use_dash" => true, "lowercase" => true)) . (empty($useProductNumber) ? "" : "-" . $useProductNumber);
					$dupProductId = getFieldFromId("product_id", "products", "link_name", $useLinkName);
					$useProductNumber++;
				} while (!empty($dupProductId));
				$thisPostVariables['link_name'] = $useLinkName;
			} else {
				if (!empty($thisPostVariables['link_name'])) {
					$thisPostVariables['link_name'] = makeCode($thisPostVariables['link_name'], array("use_dash" => true, "lowercase" => true));
				}
			}

			$imageResults = ProductCatalog::processProductImages($productId, $thisPostVariables);
			$imageId = $imageResults['image_id'];
			$imageIds = $imageResults['product_images'];

			if (empty($productId)) {
				if (empty($productCode)) {
					$returnArray['error_message'] = "Product code is required for new products" . "-" . __LINE__;
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
					continue;
				}
				if (empty($thisPostVariables['sort_order']) || !is_numeric($thisPostVariables['sort_order'])) {
					$thisPostVariables['sort_order'] = 100;
				}
				$insertSet = executeQuery("insert into products (client_id, product_code, description, detailed_description, link_name, product_type_id, product_manufacturer_id, base_cost, list_price, image_id, " .
					"no_online_order,date_created,time_changed,reindex,cannot_dropship,virtual_product,non_inventory_item,notes,sort_order) values(?,?,?,?,?, ?,?,?,?,?, ?,current_date,now(),1,?,?,?,'Created by API',?)", $GLOBALS['gClientId'], $productCode, $thisPostVariables['description'],
					$thisPostVariables['detailed_description'], $thisPostVariables['link_name'], $productTypeId, $productManufacturerId, $thisPostVariables['base_cost'], $thisPostVariables['list_price'], $imageId, (empty($thisPostVariables['no_online_order']) ? 0 : 1),
					(empty($thisPostVariables['cannot_dropship']) ? 0 : 1), (empty($thisPostVariables['virtual_product']) ? 0 : 1), (empty($thisPostVariables['non_inventory_item']) ? 0 : 1), $thisPostVariables['sort_order']);
				if (!empty($insertSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']) . "-" . __LINE__;
					$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
					continue;
				}
				$productId = $insertSet['insert_id'];
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
				if (!empty($productManufacturerId)) {
					$nameValues['product_manufacturer_id'] = $productManufacturerId;
				}
				unset($thisPostVariables['base_cost']);
				if (empty($thisPostVariables['list_price']) || $thisPostVariables['list_price'] < 0) {
					unset($thisPostVariables['list_price']);
				}
				if (empty(getPreference("API_DOES_NOT_UPDATE_PRODUCT_TEXT"))) {
					$fieldArray = array("description", "detailed_description", "link_name", "base_cost", "list_price");
				} else {
					$fieldArray = array("base_cost", "list_price");
				}
				foreach ($fieldArray as $fieldName) {
					if (!empty($thisPostVariables[$fieldName])) {
						$nameValues[$fieldName] = $thisPostVariables[$fieldName];
					}
				}
				foreach (array("cannot_dropship", "no_online_order", "virtual_product", "non_inventory_item") as $fieldName) {
					if (!empty($thisPostVariables[$fieldName])) {
						$nameValues[$fieldName] = 1;
					}
				}
				$GLOBALS['gChangeLogNotes'] = "Change by API";
				$dataTable = new DataTable("products");
				$dataTable->setPrimaryId($productId);
				$dataTable->setSaveOnlyPresent(true);
				if (!$dataTable->saveRecord(array("name_values" => $nameValues))) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $dataTable->getErrorMessage() . "-" . __LINE__;
					$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
					continue;
				}
			}
			$productRow = ProductCatalog::getCachedProductRow($productId);

			if (!empty($thisPostVariables['product_pack_content_ids'])) {
				executeQuery("delete from product_pack_contents where product_id = ?", $productId);
				foreach ($thisPostVariables['product_pack_content_ids'] as $packProductId) {
					$quantity = 0;
					if (is_array($packProductId)) {
						$quantity = $packProductId['quantity'];
						$packProductId = $packProductId['product_id'];
					}
					if (empty($quantity)) {
						$quantity = 1;
					}
					$packProductId = getFieldFromId("product_id", "products", "product_id", $packProductId);
					if (empty($packProductId) || $packProductId == $productId) {
						continue;
					}
					executeQuery("insert into product_pack_contents (product_id,contains_product_id,quantity) values (?,?,?)",
						$productId, $packProductId, $quantity);
				}
			}

			if (!empty($thisPostVariables['product_pack_content_upcs'])) {
				executeQuery("delete from product_pack_contents where product_id = ?", $productId);
				foreach ($thisPostVariables['product_pack_content_upcs'] as $packProductUpc) {
					$quantity = 0;
					if (is_array($packProductUpc)) {
						$quantity = $packProductUpc['quantity'];
						$packProductUpc = $packProductUpc['upc_code'];
					}
					if (empty($quantity)) {
						$quantity = 1;
					}
					$packProductId = getFieldFromId("product_id", "product_data", "upc_code", $packProductUpc);
					if (empty($packProductId) || $packProductId == $productId) {
						continue;
					}
					executeQuery("insert into product_pack_contents (product_id,contains_product_id,quantity) values (?,?,?)",
						$productId, $packProductId, $quantity);
				}
			}

			foreach ($thisPostVariables as $fieldName => $fieldValue) {
				if (startsWith($fieldName, "custom_field-")) {
					$customFieldCode = substr($fieldName, strlen("custom_field-"));
					$customFieldId = CustomField::getCustomFieldIdFromCode($customFieldCode, "PRODUCTS");
					if (empty($customFieldId)) {
						continue;
					}
					CustomField::setCustomFieldData($productId, $customFieldCode, $fieldValue, "PRODUCTS");
				}
			}

			$productDataId = getFieldFromId("product_data_id", "product_data", "product_id", $productId);
			if (empty($productDataId)) {
				$insertSet = executeQuery("insert into product_data(client_id, product_id, model, upc_code, isbn, isbn_13, manufacturer_sku, manufacturer_advertised_price, width, length, height, weight) values(?,?,?,?,?, ?,?,?,?,?, ?,?)",
					$GLOBALS['gClientId'], $productId, $thisPostVariables['model'], $upcCode, $isbn, $isbn13, $thisPostVariables['manufacturer_sku'], $thisPostVariables['manufacturer_advertised_price'], $thisPostVariables['width'],
					$thisPostVariables['length'], $thisPostVariables['height'], $thisPostVariables['weight']);
				if (!empty($insertSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $insertSet['sql_error'] . "-" . __LINE__;
					$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
					continue;
				}
			} else {
				$checkFields = array("upc_code", "model", "isbn", "isbn_13", "manufacturer_sku", "width", "length", "height", "weight");
				$nameValues = array();
				foreach ($checkFields as $fieldName) {
					if (array_key_exists($fieldName, $thisPostVariables) && !empty($thisPostVariables[$fieldName])) {
						$nameValues[$fieldName] = $thisPostVariables[$fieldName];
					}
				}
				if (!empty($nameValues)) {
					$dataTable = new DataTable("product_data");
					$dataTable->setPrimaryId($productDataId);
					$dataTable->setSaveOnlyPresent(true);
					if (!$dataTable->saveRecord(array("name_values" => $nameValues))) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $dataTable->getErrorMessage() . "-" . __LINE__;
						$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
						continue;
					}
				}
			}

			$salePriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_code", "sale_price");
			if (empty($salePriceTypeId)) {
				$insertSet = executeQuery("insert into product_price_types (client_id, product_price_type_code, description) values (?,'SALE_PRICE', 'Sale Price')", $GLOBALS['gClientId']);
				$salePriceTypeId = $insertSet['insert_id'];
			}
			if (($productRow['base_cost'] > 500 || $productRow['list_price'] > 500) && $thisPostVariables['product_price'] < 50 && $productPriceTypeId == $salePriceTypeId) {
				$thisPostVariables['product_price'] = "";
			}

			$userTypeId = getFieldFromId("user_type_id", "user_types", "user_type_code", $thisPostVariables['user_type_code']);

			# make sure product prices are not greated less than MAP price when the MAP policy for the product manufacturer is MAP_MINIMUM
			if (strlen($thisPostVariables['product_price']) > 0 && $thisPostVariables['product_price'] > 0 && !empty($productRow['manufacturer_advertised_price']) &&
				$thisPostVariables['product_price'] < $productRow['manufacturer_advertised_price'] && empty($userTypeId) && $productPriceTypeId == $salePriceTypeId) {
				$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productId, "product_tag_id in (select product_tag_id from product_tags where product_tag_code = 'IGNORE_MAP' and inactive = 0)");
				if (empty($productTagLinkId)) {
					$ignoreMap = false;
					$customFieldId = getReadFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "IGNORE_MAP", "inactive = 0 and " .
						"custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'PRODUCTS')");
					$ignoreMapSet = executeReadQuery("select primary_identifier from custom_field_data where custom_field_id = ? and text_data = '1' and primary_identifier = ?", $customFieldId, $productId);
					if ($ignoreMapRow = getNextRow($ignoreMapSet)) {
						$ignoreMap = true;
					}
					if (!$ignoreMap) {
						$resultSet = executeQuery("select map_policy_code from product_manufacturers join map_policies using (map_policy_id) where product_manufacturer_id = (select product_manufacturer_id from products where product_id = ?)", $productId);
						if ($row = getNextRow($resultSet)) {
							if ($row['map_policy_code'] == "MAP_MINIMUM") {
								$thisPostVariables['product_price'] = "";
							}
						}
					}
				}
			}
			if (!empty($thisPostVariables['remove_product_sale_prices'])) {
				executeQuery("delete from product_prices where product_id = ? and product_price_type_id = ?", $productId, $salePriceTypeId);
			}
			if (!empty($productPriceTypeId) && strlen($thisPostVariables['product_price']) > 0 && $thisPostVariables['product_price'] > 0) {
				$productPriceId = getFieldFromId("product_price_id", "product_prices", "product_id", $productId, "product_price_type_id = ? and " .
					"start_date <=> ? and end_date <=> ? and location_id <=> ? and sale_count is null" .
					(empty($userTypeId) ? " and user_type_id is null" : " and user_type_id = " . $userTypeId), $productPriceTypeId, makeDateParameter($thisPostVariables['start_date']),
					makeDateParameter($thisPostVariables['end_date']), $locationId);
				$dataTable = new DataTable("product_prices");
				$dataTable->setSaveOnlyPresent(true);
				if (empty($productPriceId)) {
					if (!$dataTable->saveRecord(array("name_values" => array("product_id" => $productId, "product_price_type_id" => $productPriceTypeId, "user_type_id" => $userTypeId, "price" => $thisPostVariables['product_price'],
						"start_date" => $thisPostVariables['start_date'], "end_date" => $thisPostVariables['end_date'], "location_id" => $locationId)))) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $dataTable->getErrorMessage() . "-" . __LINE__;
						$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
						continue;
					}
				} else {
					if (!$dataTable->saveRecord(array("name_values" => array("price" => $thisPostVariables['product_price']), "primary_id" => $productPriceId))) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $dataTable->getErrorMessage() . "-" . __LINE__;
						$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
						continue;
					}
				}
				removeCachedData("product_prices", $productId);
			}

			foreach ($imageIds as $imageId) {
				$insertSet = executeQuery("insert into product_images (product_id, description, image_id) values(?,'Alternate Image',?)", $productId, $imageId);
				if (!empty($insertSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $insertSet['sql_error'] . "-" . __LINE__;
					$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
					continue;
				}
			}

			if (!empty($thisPostVariables['ffl_required'])) {
				if (empty($fflRequiredProductTagId)) {
					$insertSet = executeQuery("insert into product_tags (client_id, product_tag_code, description, internal_use_only) values (?,'FFL_REQUIRED','FFL Required',1)", $GLOBALS['gClientId']);
					$fflRequiredProductTagId = $insertSet['insert_id'];
				}
				$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productId, "product_tag_id = ?", $fflRequiredProductTagId);
				if (empty($productTagLinkId)) {
					$dataTable = new DataTable("product_tag_links");
					if (!$dataTable->saveRecord(array("name_values" => array("product_id" => $productId, "product_tag_id" => $fflRequiredProductTagId)))) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $dataTable->getErrorMessage() . "-" . __LINE__;
						$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
						continue;
					}
				}
				$insertSet = executeQuery("update products set time_changed = now(), serializable = 1 where product_id = ?", $productId);
				if (!empty($insertSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $insertSet['sql_error'] . "-" . __LINE__;
					$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
					continue;
				}
			}
			if (!empty($thisPostVariables['class_3'])) {
				if (empty($class3ProductTagId)) {
					$insertSet = executeQuery("insert into product_tags(client_id, product_tag_code, description, internal_use_only) values(?,'CLASS_3','Class 3 Products',1)", $GLOBALS['gClientId']);
					$class3ProductTagId = $insertSet['insert_id'];
				}
				$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productId, "product_tag_id = ?", $class3ProductTagId);
				if (empty($productTagLinkId)) {
					$dataTable = new DataTable("product_tag_links");
					if (!$dataTable->saveRecord(array("name_values" => array("product_id" => $productId, "product_tag_id" => $class3ProductTagId)))) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $dataTable->getErrorMessage() . "-" . __LINE__;
						$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
						continue;
					}
				}
				$insertSet = executeQuery("update products set time_changed = now(), serializable = 1 where product_id = ?", $productId);
				if (!empty($insertSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $insertSet['sql_error'] . "-" . __LINE__;
					$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
					continue;
				}
			}
			foreach ($productCategories as $productCategoryId) {
				$productCategoryLinkId = getFieldFromId("product_category_link_id", "product_category_links", "product_id", $productId, "product_category_id = ?", $productCategoryId);
				if (empty($productCategoryLinkId)) {
					$productCategoryLinksDataTable = new DataTable("product_category_links");
					$productCategoryLinksDataTable->saveRecord(array("name_values" => array("product_category_id" => $productCategoryId, "product_id" => $productId)));
				}
			}
			if (!empty($thisPostVariables['product_categories_remove_others'])) {
				executeQuery("delete from product_category_links where product_id = ?" . (empty($productCategories) ? "" : " and product_category_id not in (" . implode(", ", $productCategories) . ")"), $productId);
			}
			foreach ($removeProductCategories as $productCategoryId) {
				executeQuery("delete from product_category_links where product_id = ? and product_category_id = ?", $productId, $productCategoryId);
			}
			foreach ($productTags as $productTagId) {
				$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productId, "product_tag_id = ?", $productTagId);
				if (empty($productTagLinkId)) {
					$dataTable = new DataTable("product_tag_links");
					if (!$dataTable->saveRecord(array("name_values" => array("product_id" => $productId, "product_tag_id" => $productTagId)))) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $dataTable->getErrorMessage() . "-" . __LINE__;
						$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
						continue 2;
					}
				}
			}
			if (!empty($thisPostVariables['product_tags_remove_others'])) {
				executeQuery("delete from product_tag_links where product_id = ?" . (empty($productTags) ? "" : " and product_tag_id not in (" . implode(", ", $productTags) . ")"), $productId);
			}
			foreach ($removeProductTags as $productTagId) {
				executeQuery("delete from product_tag_links where product_id = ? and product_tag_id = ?", $productId, $productTagId);
			}
			if (is_array($thisPostVariables['location_quantities'])) {
				$locationQuantities = $thisPostVariables['location_quantities'];
			} else {
				$locationQuantities = array();
			}
			if (strlen($thisPostVariables['quantity']) > 0 && !empty($locationId)) {
				$locationQuantities[] = array("quantity" => $thisPostVariables['quantity'], "location_id" => $locationId, "reorder_level" => $thisPostVariables['reorder_level'], "replenishment_level" => $thisPostVariables['replenishment_level'],
					"bin_number" => $thisPostVariables['bin_number'], "total_cost" => $thisPostVariables['total_cost'], "inventory_notes" => $thisPostVariables['inventory_notes']);
			}
			foreach ($locationQuantities as $thisLocationQuantity) {
				$locationId = getFieldFromId("location_id", "locations", "location_id", $thisLocationQuantity['location_id']);
				if (empty($locationId)) {
					$locationId = getFieldFromId("location_id", "locations", "location_code", makeCode($thisLocationQuantity['location_code']));
				}
				if (empty($locationId)) {
					$locationId = getFieldFromId("location_id", "locations", "description", $thisLocationQuantity['location_code']);
				}
				if (empty($locationId)) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Invalid Location: " . $thisLocationQuantity['location_code'] . "-" . __LINE__;
					$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
					continue 2;
				}
				if (!empty($thisLocationQuantity['remove_product_sale_prices'])) {
					executeQuery("delete from product_prices where location_id = ? and product_id = ? and product_price_type_id = ?", $locationId, $productId, $salePriceTypeId);
					removeCachedData("product_prices", $productId);
				}
				if (empty($thisLocationQuantity['sale_prices'])) {
					$thisLocationQuantity['sale_prices'] = array($thisLocationQuantity);
				}
				foreach ($thisLocationQuantity['sale_prices'] as $thisSalePrice) {
					if (!empty($thisSalePrice['sale_price']) && is_numeric($thisSalePrice['sale_price']) && $thisSalePrice['sale_price'] > 0) {
						$userTypeId = getFieldFromId("user_type_id", "user_types", "user_type_id", $thisSalePrice['user_type_id']);
						if (empty($userTypeId)) {
							$userTypeId = getFieldFromId("user_type_id", "user_types", "user_type_code", $thisSalePrice['user_type_code']);
						}
						$productPriceId = getFieldFromId("product_price_id", "product_prices", "product_id", $productId, "product_price_type_id = ? and " .
							"start_date <=> ? and end_date <=> ? and location_id <=> ? and sale_count is null" .
							(empty($userTypeId) ? " and user_type_id is null" : " and user_type_id = " . $userTypeId), $salePriceTypeId,
							makeDateParameter($thisSalePrice['start_date']), makeDateParameter($thisSalePrice['end_date']), $locationId);
						$dataTable = new DataTable("product_prices");
						$dataTable->setSaveOnlyPresent(true);
						if (empty($productPriceId)) {
							if (!$dataTable->saveRecord(array("name_values" => array("product_id" => $productId, "product_price_type_id" => $salePriceTypeId,
								"price" => $thisSalePrice['sale_price'], "user_type_id" => $userTypeId, "start_date" => $thisSalePrice['start_date'],
								"end_date" => $thisSalePrice['end_date'], "location_id" => $locationId)))) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $dataTable->getErrorMessage() . "-" . __LINE__;
								$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
								continue 3;
							}
						} else {
							if (!$dataTable->saveRecord(array("name_values" => array("price" => $thisSalePrice['sale_price']), "primary_id" => $productPriceId))) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $dataTable->getErrorMessage() . "-" . __LINE__;
								$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
								continue 3;
							}
						}
						removeCachedData("product_prices", $productId);
					}
				}
				$quantity = $thisLocationQuantity['quantity'];
				if (strlen($quantity) == 0 || !is_numeric($quantity)) {
					continue;
				}
				$productInventoryRow = getRowFromId("product_inventories", "product_id", $productId, "location_id = ?", $locationId);
				$productInventoryId = $productInventoryRow['product_inventory_id'];
				if (empty($productInventoryId)) {
					$insertSet = executeQuery("insert into product_inventories (product_id, location_id, bin_number, quantity, reorder_level, replenishment_level) values(?,?,?,?,?, ?)",
						$productId, $locationId, $thisLocationQuantity['bin_number'], $quantity, $thisLocationQuantity['reorder_level'], $thisLocationQuantity['replenishment_level']);
					$productInventoryId = $insertSet['insert_id'];
					$affectedRows = 1;
				} else {
					if (strlen($thisLocationQuantity['reorder_level']) > 0 || strlen($thisLocationQuantity['replenishment_level']) > 0 || strlen($thisLocationQuantity['bin_number']) > 0) {
						$parameters = array();
						$setWhere = "";
						if (strlen($thisLocationQuantity['reorder_level']) > 0) {
							$parameters[] = $thisLocationQuantity['reorder_level'];
							$setWhere .= "reorder_level = ?";
						}
						if (strlen($thisLocationQuantity['replenishment_level']) > 0) {
							$parameters[] = $thisLocationQuantity['replenishment_level'];
							$setWhere .= (empty($setWhere) ? "" : ",") . "replenishment_level = ?";
						}
						if (strlen($thisLocationQuantity['bin_number']) > 0) {
							$parameters[] = $thisLocationQuantity['bin_number'];
							$setWhere .= (empty($setWhere) ? "" : ",") . "bin_number = ?";
						}
						$parameters[] = $productInventoryId;
						if (!empty($setWhere)) {
							$updateSet = executeQuery("update product_inventories set " . $setWhere . " where product_inventory_id = ?", $parameters);
						}
					}
					if ($productInventoryRow['quantity'] != $quantity) {
						$updateSet = executeQuery("update product_inventories set quantity = ? where product_inventory_id = ?", $quantity, $productInventoryId);
						if (!empty($updateSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']) . "-" . __LINE__;
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
							continue 2;
						}
						$affectedRows = $updateSet['affected_rows'];
					} else {
						$affectedRows = 0;
					}
				}
				removeCachedData("product_prices", $thisLocationQuantity['product_id']);
				if ($quantity > 0) {
					executeQuery("delete from product_category_links where product_id = ? and product_category_id in (select product_category_id from product_categories where product_category_code = 'DISCONTINUED')", $productId);
				}
				if ($affectedRows > 0) {
					if (empty($thisLocationQuantity['total_cost']) || $thisLocationQuantity['total_cost'] <= 0) {
						$thisLocationQuantity['total_cost'] = "";
					}
					if (empty($thisInventoryUpdate['notes'])) {
						$thisInventoryUpdate['notes'] = "API update";
					}
					executeQuery("insert into product_inventory_log (product_inventory_id, inventory_adjustment_type_id, user_id, log_time, quantity, total_cost,notes) values " .
						"(?,?,?,now(),?,?,?)", $productInventoryId, $GLOBALS['gInventoryAdjustmentTypeId'], $GLOBALS['gUserId'], $quantity, $thisLocationQuantity['total_cost'], $thisLocationQuantity['inventory_notes']);
				} elseif (is_numeric($thisLocationQuantity['total_cost']) && $thisLocationQuantity['total_cost'] > 0 && is_numeric($quantity) && $quantity > 0) {
					$updateCost = round($thisLocationQuantity['total_cost'] / $quantity, 2);
					if ($updateCost != $productInventoryRow['location_cost']) {
						if (empty($thisInventoryUpdate['notes'])) {
							$thisInventoryUpdate['notes'] = "API update";
						}
						executeQuery("insert into product_inventory_log(product_inventory_id, inventory_adjustment_type_id, user_id, log_time, quantity, total_cost,notes) values " .
							"(?,?,?,now(),?,?,?)", $productInventoryId, $GLOBALS['gInventoryAdjustmentTypeId'], $GLOBALS['gUserId'], $quantity, $thisLocationQuantity['total_cost'], $thisLocationQuantity['inventory_notes']);
						ProductCatalog::updateLocationBaseCost($productId, $locationId);
					}
				}
			}
			foreach ($thisPostVariables as $thisFieldName => $fieldValue) {
				if (empty($fieldValue)) {
					continue;
				}
				if (startsWith($thisFieldName, "facet-")) {
					$productFacetCode = strtoupper(substr($thisFieldName, strlen("facet-")));
					$productFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_code", strtoupper(substr($thisFieldName, strlen("facet-"))));
					if (empty($productFacetId)) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = "Invalid Product Facet" . "-" . __LINE__;
						$returnArray['product_index_errors'][$postIndex] = $returnArray['error_message'];
						continue 2;
					}
					$productFacetRow = getRowFromId("product_facets", "product_facet_id", $productFacetId);
					$productFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_id", $productFacetId, "facet_value = ?", $fieldValue);
					if (empty($productFacetOptionId)) {
						if ($GLOBALS['gClientRow']['client_code'] != "COREWARE_SHOOTING_SPORTS" || empty($productFacetRow['catalog_lock'])) {
							$insertSet = executeQuery("insert into product_facet_options (product_facet_id, facet_value) values(?,?)", $productFacetId, $fieldValue);
							$productFacetOptionId = $insertSet['insert_id'];
						}
					}
					if (!empty($productFacetOptionId)) {
						$productFacetValueId = getFieldFromId("product_facet_value_id", "product_facet_values", "product_id", $productId, "product_facet_id = ?", $productFacetId);
						if (empty($productFacetValueId)) {
							executeQuery("insert into product_facet_values (product_id, product_facet_id, product_facet_option_id) values(?,?,?)", $productId, $productFacetId, $productFacetOptionId);
						} else {
							executeQuery("update product_facet_values set product_facet_option_id = ? where product_facet_value_id = ?", $productFacetOptionId, $productFacetValueId);
						}
					}
				}
			}

			if (!empty($thisPostVariables['reindex'])) {
				ProductCatalog::reindexProducts($productId);
			}
			ProductCatalog::calculateProductCost($productId, "Product imported in API");
			if ($singleProduct) {
				$returnArray['product_id'] = $productId;
			} else {
				$returnArray['product_ids'][$postIndex] = $productId;
			}
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
		}
		break;

	case "get_map_policies":
		$returnArray['map_policies'] = array();
		$resultSet = executeQuery("select * from map_policies order by sort_order");
		while ($row = getNextRow($resultSet)) {
			$returnArray['map_policies'][] = $row;
		}
		break;

	case "get_manufacturers":
		$parameters = array($GLOBALS['gClientId']);
		$whereStatement = "product_manufacturers.client_id = ?";
		if (!empty($_POST['product_manufacturer_id'])) {
			$whereStatement .= " and product_manufacturer_id = ?";
			$parameters[] = $_POST['product_manufacturer_id'];
		}
		if (!empty($_POST['product_manufacturer_code'])) {
			$whereStatement .= " and product_manufacturer_code = ?";
			$parameters[] = $_POST['product_manufacturer_code'];
		}
		$resultSet = executeReadQuery("select product_manufacturer_code,description,web_page from product_manufacturers join contacts using (contact_id) where " .
			$whereStatement, $parameters);
		$manufacturers = array();
		while ($row = getNextRow($resultSet)) {
			if (empty($row['image_id'])) {
				$row['image_url'] = "";
			} else {
				$imageFilename = getImageFilename($row['image_id']);
				if (strpos($imageFilename, "empty.jpg") !== false) {
					$row['image_url'] = "";
				} else {
					$row['image_url'] = "https://" . $_SERVER['HTTP_HOST'] . $imageFilename;
				}
			}
			$manufacturers[] = $row;
		}
		$returnArray['manufacturers'] = $manufacturers;
		break;

	case "get_catalog":
		$productFacets = array();
		$resultSet = executeReadQuery("select product_id,product_facet_code,facet_value from product_facet_values join product_facet_options using (product_facet_option_id) join product_facets on (product_facet_options.product_facet_id = product_facets.product_facet_id) where product_facets.client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists($row['product_id'], $productFacets)) {
				$productFacets[$row['product_id']] = array();
			}
			$productFacets[$row['product_id']][$row['product_facet_code']] = $row['facet_value'];
		}
		$productIdList = array();
		$postProductIds = array_filter((is_array($_POST['product_ids']) ? $_POST['product_ids'] : explode(",", $_POST['product_ids'])));
		foreach ($postProductIds as $productId) {
			$productIdList[$productId] = $productId;
		}
		$productArray = array();
		$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "DEALER_CATALOG", "inactive = 0");
		$resultSet = executeReadQuery("select products.product_id,product_code,description,detailed_description,remote_identifier,(select product_type_code from product_types where inactive = 0 and " .
			"product_type_id = products.product_type_id) product_type,(select product_format_code from product_formats where inactive = 0 and product_format_id = products.product_format_id) product_format," .
			"(select product_manufacturer_code from product_manufacturers where inactive = 0 and product_manufacturer_id = products.product_manufacturer_id) product_manufacturer,image_id,list_price," .
			"cannot_dropship,serializable,(select unit_code from units where unit_id = product_data.unit_id) unit,model,upc_code,isbn,isbn_13,manufacturer_sku,manufacturer_advertised_price,width,length,height,weight," .
			"(select group_concat(image_id) from product_images where product_id = products.product_id) product_images," .
			"(select group_concat(image_identifier) from product_remote_images where product_id = products.product_id order by primary_image desc,product_remote_image_id) image_identifiers," .
			"(select group_concat(product_category_code) from product_categories where inactive = 0 and product_category_id in (select product_category_id from product_category_links where " .
			"product_id = products.product_id)) product_categories,(select group_concat(product_tag_code) from product_tags where inactive = 0 and product_tag_id in " .
			"(select product_tag_id from product_tag_links where (start_date is null or start_date >= current_date) and (expiration_date is null or expiration_date > current_date) and " .
			"product_id = products.product_id)) product_tags,(select group_concat(state) from product_restrictions where product_id = products.product_id) restricted_states from products " .
			"left outer join product_data using (product_id) where user_group_id is null and inactive = 0 and internal_use_only = 0 and products.client_id = ? and (expiration_date is null or expiration_date > current_date)" .
			(empty($productTagId) ? "" : " and products.product_id in (select product_id from product_tag_links where product_tag_id = " . $productTagId . " and (start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date > current_date))"), $GLOBALS['gClientId']);
		$productIds = array();
		while ($row = getNextRow($resultSet)) {
			if (!empty($productIdList)) {
				if (!array_key_exists($row['product_id'], $productIdList)) {
					continue;
				}
			}
			if (array_key_exists($row['product_id'], $productFacets)) {
				$row['facets'] = $productFacets[$row['product_id']];
			} else {
				$row['facets'] = array();
			}
			$imageIdentifiers = array_filter(explode(",", $row['image_identifiers']));
			$row['image_url'] = ProductCatalog::getProductImage($row['product_id'], array("product_row" => $row, "image_identifier" => $imageIdentifiers[0]));
			if (strpos($row['image_url'], "empty.jpg") !== false || strpos($row['image_url'], "no_product_image.jpg") !== false) {
				$row['image_url'] = "";
			}
			$productImages = array_filter(explode(",", $row['product_images']));
			$row['image_urls'] = ProductCatalog::getProductAlternateImages($row['product_id'], array("product_row" => $row, "product_images" => $productImages, "image_identifiers" => $imageIdentifiers));
			if (!empty($row['image_url'])) {
				array_unshift($row['image_urls'], array("url"=>$row['image_url']));
			}
			$productIds[] = $row['product_id'];
			$productArray[$row['product_id']] = $row;
		}

		$productCatalog = new ProductCatalog();
		if (count($productIds) > 500) {
			$resultSet = executeQuery("select * from products left outer join product_data using (product_id) where products.client_id = ?", $GLOBALS['gClientId']);
		} else {
			$resultSet = executeQuery("select * from products left outer join product_data using (product_id) where products.client_id = ? and products.product_id in (" . implode(",", $productIds) . ")", $GLOBALS['gClientId']);
		}
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists($row['product_id'], $productArray)) {
				continue;
			}
			$salePriceInfo = $productCatalog->getProductSalePrice($row['product_id'], array("product_information" => $row));
			$productArray[$row['product_id']]['base_cost'] = $salePriceInfo['sale_price'];
		}

		$returnArray['products'] = array_values($productArray);
		break;

	case "get_allocated_inventory":
		$inventoryLocationCode = "ALLOCATED_" . $GLOBALS['gUserId'];
	case "get_catalog_inventory":
		$onlyPresentProducts = ($_POST['action'] == "get_allocated_inventory");
		if (empty($inventoryLocationCode)) {
			$inventoryLocationCode = $_POST['inventory_location_code'];
		}
		if (empty($inventoryLocationCode)) {
			$inventoryLocationCode = "DEALER_INVENTORY";
		}
		$productInventory = array();
		$locationId = getReadFieldFromId("location_id", "locations", "location_code", makeCode($inventoryLocationCode));
		if (empty($locationId)) {
			$returnArray['product_inventory'] = $productInventory;
			break;
		}
		$productIdList = array();
		$postProductIds = array_filter((is_array($_POST['product_ids']) ? $_POST['product_ids'] : explode(",", $_POST['product_ids'])));
		foreach ($postProductIds as $productId) {
			$productIdList[$productId] = $productId;
		}
		$productIdArray = array();
		$resultSet = executeReadQuery("select * from product_inventories join products using (product_id) where location_id = ?", $locationId);
		while ($row = getNextRow($resultSet)) {
			if (!empty($productIdList)) {
				if (!array_key_exists($row['product_id'], $productIdList)) {
					continue;
				}
			}
			$productInventory[$row['product_id']] = array("product_id" => $row['product_id'], "product_code" => $row['product_code'], "quantity" => $row['quantity'], "sale_price" => false);
			$productIdArray[$row['product_id']] = $row['product_id'];
		}
		$resultSet = executeQuery("select product_id, sum(quantity) as quantity_ordered," .
			"(select sum(quantity) from order_shipment_items where exists (select order_shipment_id from order_shipments where order_shipment_id = order_shipment_items.order_shipment_id and " .
			"secondary_shipment = 0) and order_item_id in (select order_item_id from order_items oi where oi.product_id = order_items.product_id and deleted = 0 and " .
			"order_id in (select order_id from orders where deleted = 0 and date_completed is null))) as quantity_shipped " .
			"from order_items where deleted = 0 and exists (select order_id from orders " .
			"where order_id = order_items.order_id and deleted = 0 and date_completed is null) group by product_id");
		while ($row = getNextRow($resultSet)) {
			if (array_key_exists($row['product_id'], $productInventory)) {
				$productInventory[$row['product_id']]['quantity'] -= ($row['quantity_ordered'] - $row['quantity_shipped']);
				$productInventory[$row['product_id']]['quantity'] = max($productInventory[$row['product_id']]['quantity'], 0);
			}
		}
		if (!$onlyPresentProducts) {
			$resultSet = executeReadQuery("select *,(select product_category_link_id from product_category_links where " .
				"product_id = products.product_id and product_category_id in (select product_category_id from product_categories where " .
				"product_category_code in ('INACTIVE','INTERNAL_USE_ONLY','DISCONTINUED')) limit 1) inactive_category from products join product_data using (product_id) where products.client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (!empty($productIdList)) {
					if (!array_key_exists($row['product_id'], $productIdList)) {
						continue;
					}
				}
				if (!array_key_exists($row['product_id'], $productInventory) || !empty($row['inactive']) || !empty($row['internal_use_only']) || !empty($row['inactive_category'])) {
					$productInventory[$row['product_id']] = array("product_id" => $row['product_id'], "product_code" => $row['product_code'], "quantity" => 0, "sale_price" => false);
				} else {
					$productIdArray[$row['product_id']] = $row;
				}
			}
		}
		$productCatalog = new ProductCatalog();
		foreach ($productIdArray as $productId => $productInformation) {
			$salePriceInfo = $productCatalog->getProductSalePrice($productId, array("product_information" => $productInformation, "ignore_map" => true));
			$productInventory[$productId]['sale_price'] = number_format($salePriceInfo['sale_price'], 2, ".", "");
		}
		$returnArray['product_inventory'] = $productInventory;
		break;

	case "get_discontinued_products":
		$resultSet = executeReadQuery("select products.product_id,product_code,upc_code,isbn,isbn_13 from products join product_data using (product_id) where " .
			"(inactive = 1 or internal_use_only = 1 or (expiration_date is not null and expiration_date <= current_date)) and products.client_id = ?", $GLOBALS['gClientId']);
		$productArray = array();
		while ($row = getNextRow($resultSet)) {
			$productArray[] = $row;
		}
		$returnArray['discontinued_products'] = $productArray;
		break;

	case "get_top_products":
		$productArray = array();
		$resultSet = executeReadQuery("select upc_code,sum(quantity) from order_items join product_data using (product_id) where deleted = 0 and " .
			"order_id in (select order_id from orders where order_time > date_sub(current_time,interval 30 day) and deleted = 0 and order_id = order_items.order_id) and " .
			"upc_code is not null group by upc_code having sum(quantity) > 1 order by sum(quantity) desc limit 100");
		while ($row = getNextRow($resultSet)) {
			$productArray['sales'][] = array("upc_code" => $row['upc_code'], "quantity" => $row['sum(quantity)']);
		}
		$resultSet = executeReadQuery("select upc_code,sum(quantity) from order_items join product_data using (product_id) where deleted = 0 and " .
			"order_id in (select order_id from orders where order_time > date_sub(current_time,interval 7 day) and deleted = 0 and order_id = order_items.order_id) and " .
			"upc_code is not null group by upc_code having sum(quantity) > 1 order by sum(quantity) desc limit 100");
		while ($row = getNextRow($resultSet)) {
			$productArray['trending'][] = array("upc_code" => $row['upc_code'], "quantity" => $row['sum(quantity)']);
		}
		$resultSet = executeReadQuery("select upc_code,count(*) from product_view_log join product_data using (product_id) where upc_code is not null and " .
			"log_time > date_sub(current_date,interval 30 day) and product_id in (select product_id from distributor_product_codes) group by upc_code having count(*) > 1 order by count(*) desc limit 100");
		while ($row = getNextRow($resultSet)) {
			$productArray['views'][] = array("upc_code" => $row['upc_code'], "quantity" => $row['count(*)']);
		}
		$resultSet = executeReadQuery("select search_term,use_count from search_terms order by use_count desc limit 100");
		while ($row = getNextRow($resultSet)) {
			$productArray['search'][] = array("search_term" => $row['search_term'], "quantity" => $row['use_count']);
		}
		$returnArray['product_array'] = $productArray;
		break;

	case "get_top_product_data":
		$resultSet = executeReadQuery("select content from fragments where fragment_code = 'TOP_PRODUCTS' and client_id = ?", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$returnArray['top_products'] = (empty($row['content']) ? array() : json_decode($row['content'], true));
		}
		break;

	case "get_product_availability":
		$productId = getFieldFromId("product_id", "products", "product_id", $_POST['product_id']);
		if (empty($productId)) {
			$productId = getFieldFromId("product_id", "product_data", "upc_code", $_POST['upc_code']);
		}
		if (empty($productId)) {
			$returnArray['error_message'] = "Invalid Product" . "-" . __LINE__;
			break;
		}

		# get number of products in open orders but NOT in shipment. Label as "ordered"
		# get number of products in open orders, in a shipment without tracking. Label as "shipment_pending"
		# get number of products in open orders, in a shipment WITH tracking. Label as "shipped"

		$resultSet = executeQuery("select sum(quantity) as ordered_quantity,(select sum(quantity) from order_shipment_items " .
			"where exists (select order_shipment_id from order_shipments where order_shipment_id = order_shipment_items.order_shipment_id and " .
			"secondary_shipment = 0) and order_item_id in (select order_item_id from order_items where product_id = ? and deleted = 0 and " .
			"order_id in (select order_id from orders where deleted = 0 and date_completed is null))) as shipped_quantity " .
			"from order_items where product_id = ? and deleted = 0 and exists (select order_id from orders " .
			"where order_id = order_items.order_id and deleted = 0 and date_completed is null)", $productId, $productId);

		if ($row = getNextRow($resultSet)) {
			if (empty($row['ordered_quantity'])) {
				$row['ordered_quantity'] = 0;
			}
			if (empty($row['shipped_quantity'])) {
				$row['shipped_quantity'] = 0;
			}
			$returnArray['ordered'] = max($row['ordered_quantity'] - $row['shipped_quantity'], 0);
		}
		if (empty($returnArray['ordered'])) {
			$returnArray['ordered'] = 0;
		}
		$resultSet = executeQuery("select sum(quantity) as ordered_quantity,(select sum(quantity) from order_shipment_items where order_item_id in (select order_item_id from order_items where " .
			"product_id = ? and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) and order_shipment_id in (select order_shipment_id from order_shipments where " .
			"shipping_carrier_id is null and carrier_description is null and tracking_identifier is null)) as shipped_quantity from order_items where product_id = ? and order_id in (select order_id from orders where " .
			"deleted = 0 and date_completed is null)", $productId, $productId);
		if ($row = getNextRow($resultSet)) {
			if (empty($row['ordered_quantity'])) {
				$row['ordered_quantity'] = 0;
			}
			if (empty($row['shipped_quantity'])) {
				$row['shipped_quantity'] = 0;
			}
			$returnArray['shipment_pending'] = $row['shipped_quantity'];
		}
		if (empty($returnArray['shipment_pending'])) {
			$returnArray['shipment_pending'] = 0;
		}

		$resultSet = executeQuery("select location_id,(select location_code from locations where location_id = order_shipments.location_id) as location_code,sum(quantity) as shipped_quantity from " .
			"order_shipment_items join order_shipments using (order_shipment_id) where order_item_id in (select order_item_id from order_items where product_id = ? and deleted = 0) and " .
			"order_id in (select order_id from orders where deleted = 0 and date_completed is null) and location_id is not null and shipping_carrier_id is null and " .
			"carrier_description is null and tracking_identifier is null group by location_id,location_code", $productId);
		$returnArray['shipment_pending_by_location'] = array();
		while ($row = getNextRow($resultSet)) {
			if (empty($row['shipped_quantity'])) {
				$row['shipped_quantity'] = 0;
			}
			$returnArray['shipment_pending_by_location'][] = array("location_id" => $row['location_id'], "location_code" => $row['location_code'], "quantity" => $row['shipped_quantity']);
		}

		$resultSet = executeQuery("select sum(quantity) as ordered_quantity,(select sum(quantity) from order_shipment_items where order_item_id in (select order_item_id from order_items where " .
			"product_id = ? and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) and order_shipment_id in (select order_shipment_id from order_shipments where " .
			"shipping_carrier_id is not null or carrier_description is not null or tracking_identifier is not null)) as shipped_quantity from order_items where product_id = ? and order_id in (select order_id from orders where " .
			"deleted = 0 and date_completed is null)", $productId, $productId);
		if ($row = getNextRow($resultSet)) {
			if (empty($row['ordered_quantity'])) {
				$row['ordered_quantity'] = 0;
			}
			if (empty($row['shipped_quantity'])) {
				$row['shipped_quantity'] = 0;
			}
			$returnArray['shipped'] = $row['shipped_quantity'];
		}
		if (empty($returnArray['shipped'])) {
			$returnArray['shipped'] = 0;
		}

		$resultSet = executeQuery("select location_id,(select location_code from locations where location_id = order_shipments.location_id) as location_code,sum(quantity) as shipped_quantity from " .
			"order_shipment_items join order_shipments using (order_shipment_id) where order_item_id in (select order_item_id from order_items where product_id = ? and deleted = 0) and " .
			"order_id in (select order_id from orders where deleted = 0 and date_completed is null) and location_id is not null and (shipping_carrier_id is not null or " .
			"carrier_description is not null or tracking_identifier is not null) group by location_id,location_code", $productId);
		$returnArray['shipped_by_location'] = array();
		while ($row = getNextRow($resultSet)) {
			if (empty($row['shipped_quantity'])) {
				$row['shipped_quantity'] = 0;
			}
			$returnArray['shipped_by_location'][] = array("location_id" => $row['location_id'], "location_code" => $row['location_code'], "quantity" => $row['shipped_quantity']);
		}

		$resultSet = executeQuery("select * from product_inventories where product_id = ? and quantity > 0 " .
			"and location_id in (select location_id from locations where product_distributor_id is not null and primary_location = 1)", $productId);
		$returnArray['in_stock_at_distributor'] = (empty(getNextRow($resultSet)) ? 0 : 1);

		break;
	case "update_product_category":
		$productCategoryCode = makeCode($_POST['product_category_code']);
		$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_code", $productCategoryCode);
		if (empty($productCategoryId)) {
			$returnArray['error_message'] = "Product Category not found: " . $_POST['product_category_code'] . "-" . __LINE__;
			break;
		}
		$existingProductCategoryId = getFieldFromId("product_category_id", "product_categories", "description", $_POST['description']);
		if (!empty($existingProductCategoryId) && $existingProductCategoryId != $productCategoryId) {
			$returnArray['error_message'] = "Product Category already exists: " . $_POST['description'] . "-" . __LINE__;
			break;
		}
		$productCategoryTable = new DataTable("product_categories");
		$nameValues = array("description" => $_POST['description'],
			'link_name' => makeCode($_POST['link_name'] ?: $_POST['description'], array("use_dash" => true, "lowercase" => true)));
		if (!$productCategoryTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $productCategoryId))) {
			$returnArray['error_message'] = "Unable to update product category: " . $productCategoryTable->getErrorMessage() . "-" . __LINE__;
		} else {
			$returnArray['product_category_id'] = $productCategoryId;
			$returnArray['product_category_code'] = $productCategoryCode;
			$returnArray['description'] = $_POST['description'];
		}
		break;
	case "update_product_category_group":
		$productCategoryGroupCode = makeCode($_POST['product_category_group_code']);
		$productCategoryGroupRow = getRowFromId("product_category_groups", "product_category_group_code", $productCategoryGroupCode);
		if (empty($productCategoryGroupRow)) {
			$returnArray['error_message'] = "Product Category Group not found: " . $_POST['product_category_group_code'] . "-" . __LINE__;
			break;
		}
		$existingProductCategoryGroupId = getFieldFromId("product_category_group_id", "product_category_groups", "description", $_POST['description']);
		if (!empty($existingProductCategoryGroupId) && $existingProductCategoryGroupId != $productCategoryGroupRow['product_category_group_id']) {
			$returnArray['error_message'] = "Product Category Group already exists: " . $_POST['description'] . "-" . __LINE__;
			break;
		}
		$productCategoryGroupsTable = new DataTable("product_category_groups");
		$nameValues = array("description" => $_POST['description'],
			'link_name' => makeCode($_POST['link_name'] ?: $_POST['description'], array("use_dash" => true, "lowercase" => true)));
		if (!$productCategoryGroupsTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $productCategoryGroupRow['product_category_group_id']))) {
			$returnArray['error_message'] = "Unable to update product category group: " . $productCategoryGroupsTable->getErrorMessage() . "-" . __LINE__;
		} else {
			$returnArray['product_category_group_id'] = $productCategoryGroupRow['product_category_group_id'];
			$returnArray['product_category_group_code'] = $productCategoryGroupCode;
			$returnArray['description'] = $_POST['description'];
		}
		break;
	case "update_product_department":
		$productDepartmentCode = makeCode($_POST['product_department_code']);
		$productDepartmentId = getFieldFromId("product_department_id", "product_departments", "product_department_code", $productDepartmentCode);
		if (empty($productDepartmentId)) {
			$returnArray['error_message'] = "Product Department not found: " . $_POST['product_department_code'] . "-" . __LINE__;
			break;
		}
		$existingProductDepartmentId = getFieldFromId("product_department_id", "product_departments", "description", $_POST['description']);
		if (!empty($existingProductDepartmentId) && $existingProductDepartmentId != $productDepartmentId) {
			$returnArray['error_message'] = "Product Department already exists: " . $_POST['description'] . "-" . __LINE__;
			break;
		}
		$productDepartmentTable = new DataTable("product_departments");
		$nameValues = array("description" => $_POST['description'],
			'link_name' => makeCode($_POST['link_name'] ?: $_POST['description'], array("use_dash" => true, "lowercase" => true)));
		if (!$productDepartmentTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $productDepartmentId))) {
			$returnArray['error_message'] = "Unable to update product category: " . $productDepartmentTable->getErrorMessage() . "-" . __LINE__;
		} else {
			$returnArray['product_department_id'] = $productDepartmentId;
			$returnArray['product_department_code'] = $productDepartmentCode;
			$returnArray['description'] = $_POST['description'];
		}
		break;
	case "add_product_option":
	case "update_product_option":
		$productOptionId = false;
		if (!empty($_POST['product_option_id'])) {
			$productOptionId = getFieldFromId("product_option_id", "product_options", "product_option_id", $_GET['product_option_id']);
			if (empty($productOptionId)) {
				$returnArray['error_message'] = "Invalid product option ID" . "-" . __LINE__;
				break;
			}
		}
		if (empty($productOptionId) && !empty($_POST['product_option_code'])) {
			$_POST['product_option_code'] = makeCode($_POST['product_option_code']);
			$productOptionId = getFieldFromId("product_option_id", "product_options", "product_option_code", $_POST['product_option_code']);
		}
		$dataTable = new DataTable("product_options");
		$fieldNames = array("description", "product_option_code", "sort_order", "show_one", "internal_use_only", "inactive");
		$nameValues = array();
		foreach ($fieldNames as $thisFieldName) {
			if (array_key_exists($thisFieldName, $_POST)) {
				$nameValues[$thisFieldName] = $_POST[$thisFieldName];
			}
		}
		if (!$productOptionId = $dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $productOptionId))) {
			$returnArray['error_message'] = $dataTable->getErrorMessage() . "-" . __LINE__;
			break;
		}
		$returnArray['error_message'] = "";
		if (array_key_exists("product_option_choices", $_POST)) {
			if (!is_array($_POST['product_option_choices'])) {
				$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ",") . "Unable to update product choices" . "-" . __LINE__;
				break;
			}
			$dataTable = new DataTable("product_option_choices");
			$foundProductOptionChoiceIds = array();
			foreach ($_POST['product_option_choices'] as $thisProductOptionChoice) {
				$productOptionChoiceId = false;
				if (!empty($thisProductOptionChoice['product_option_choice_id'])) {
					$productOptionChoiceId = getFieldFromId("product_option_choice_id", "product_option_choices", "product_option_choice_id", $thisProductOptionChoice['product_option_choice_id'], "product_option_id = ?", $productOptionId);
					if (empty($productOptionChoiceId)) {
						$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ",") . "Unable to update product choice ID " . $thisProductOptionChoice['product_option_choice_id'] . "-" . __LINE__;
						continue;
					}
				}
				if (empty($productOptionChoiceId) && !empty($thisProductOptionChoice['description'])) {
					$productOptionChoiceId = getFieldFromId("product_option_choice_id", "product_option_choices", "description", $thisProductOptionChoice['description'], "product_option_id = ?", $productOptionId);
				}
				$fieldNames = array("description", "sort_order");
				$nameValues = array("product_option_id" => $productOptionId);
				foreach ($fieldNames as $thisFieldName) {
					if (array_key_exists($thisFieldName, $thisProductOptionChoice)) {
						$nameValues[$thisFieldName] = $thisProductOptionChoice[$thisFieldName];
					}
				}
				if (!$productOptionChoiceId = $dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $productOptionChoiceId))) {
					$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ",") . "Unable to save product option choice " . (empty($thisProductOptionChoice['product_option_choice_id']) ? "'" . $thisProductOptionChoice['description'] . "'" : "ID " . $thisProductOptionChoice['product_option_choice_id']) . ": " . $dataTable->getErrorMessage() . "-" . __LINE__;
					continue;
				}
				$foundProductOptionChoiceIds[] = $productOptionChoiceId;
			}
			$resultSet = executeQuery("select * from product_option_choices where product_option_id = ?" .
				(empty($foundProductOptionChoiceIds) ? "" : " and product_option_choice_id not in (" . implode(",", $foundProductOptionChoiceIds) . ")"), $productOptionId);
			while ($row = getNextRow($resultSet)) {
				$productGroupVariantChoiceId = getFieldFromId("product_group_variant_choice_id", "product_group_variant_choices", "product_option_choice_id", $row['product_option_choice_id']);
				if (!empty($productGroupVariantChoiceId)) {
					$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ",") . "Choice '" . $row['description'] . "' is in use and cannot be removed" . "-" . __LINE__;
					continue;
				}
				if (!$dataTable->deleteRecord(array("primary_id" => $row['product_option_choice_id']))) {
					$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ",") . "Unable to delete choice: " . $dataTable->getErrorMessage() . "-" . __LINE__;
				}
			}
		}
		break;
	case "get_product_options":
		$productOptionId = intval($_POST['product_option_id']);
		if (empty($productOptionId) && !empty($_POST['product_option_code'])) {
			$_POST['product_option_code'] = makeCode($_POST['product_option_code']);
			$productOptionId = getFieldFromId("product_option_id", "product_options", "product_option_code", $_POST['product_option_code']);
			if (empty($productOptionId)) {
				$returnArray['error_message'] = "Product Option does not exist: " . $_POST['product_option_code'] . "-" . __LINE__;
				break;
			}
		}
		$returnArray['product_options'] = array();
		if (empty($productOptionId)) {
			$resultSet = executeQuery("select * from product_options where client_id = ?", $GLOBALS['gClientId']);
		} else {
			$resultSet = executeQuery("select * from product_options where client_id = ? and product_option_id = ?", $GLOBALS['gClientId'], $productOptionId);
		}
		while ($row = getNextRow($resultSet)) {
			$row['product_option_choices'] = array();
			$choicesSet = executeQuery("select * from product_option_choices where product_option_id = ?", $row['product_option_id']);
			while ($choicesRow = getNextRow($choicesSet)) {
				$row['product_option_choices'][] = $choicesRow;
			}
			$returnArray['product_options'][] = $row;
		}
		break;
	case "update_product_option_choice":
		$dataTable = new DataTable("product_option_choices");
		$productOptionChoiceId = getFieldFromId("product_option_choice_id", "product_option_choices", "product_option_choice_id", $_POST['product_option_choice_id'], "product_option_id = ?", $_POST['product_option_id']);
		if (empty($productOptionChoiceId)) {
			$returnArray['error_message'] = "Product Option Choice does not exist" . "-" . __LINE__;
			break;
		}
		$fieldNames = array("description", "sort_order");
		$nameValues = array();
		foreach ($fieldNames as $thisFieldName) {
			if (array_key_exists($thisFieldName, $_POST)) {
				$nameValues[$thisFieldName] = $_POST[$thisFieldName];
			}
		}
		if (!$productOptionChoiceId = $dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $productOptionChoiceId))) {
			$returnArray['error_message'] = "Unable to update product option choice: " . $dataTable->getErrorMessage() . "-" . __LINE__;
			break;
		}
		break;
	case "add_product_group":
	case "update_product_group":
		$productGroupId = false;
		if (!empty($_POST['product_group_id'])) {
			$productGroupId = getFieldFromId("product_group_id", "product_groups", "product_group_id", $_GET['product_group_id']);
			if (empty($productGroupId)) {
				$returnArray['error_message'] = "Invalid product group ID" . "-" . __LINE__;
				break;
			}
		}
		if (empty($productGroupId) && !empty($_POST['product_group_code'])) {
			$_POST['product_group_code'] = makeCode($_POST['product_group_code']);
			$productGroupId = getFieldFromId("product_group_id", "product_groups", "product_group_code", $_POST['product_group_code']);
		}
		$dataTable = new DataTable("product_groups");
		$fieldNames = array("description", "product_group_code", "detailed_description", "sort_order", "use_group_description", "internal_use_only", "inactive");
		$nameValues = array();
		foreach ($fieldNames as $thisFieldName) {
			if (array_key_exists($thisFieldName, $_POST)) {
				$nameValues[$thisFieldName] = $_POST[$thisFieldName];
			}
		}
		if (!$productGroupId = $dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $productGroupId))) {
			$returnArray['error_message'] = $dataTable->getErrorMessage() . "-" . __LINE__;
			break;
		}
		$returnArray['error_message'] = "";
		if (array_key_exists("product_group_options", $_POST)) {
			if (!is_array($_POST['product_group_options'])) {
				$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ",") . "Unable to update product group options" . "-" . __LINE__;
				break;
			}
			$dataTable = new DataTable("product_group_options");
			$foundProductGroupOptionIds = array();
			foreach ($_POST['product_group_options'] as $thisProductGroupOption) {
				if (!is_array($thisProductGroupOption)) {
					$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ",") . "Invalid product option: " . $thisProductGroupOption . "-" . __LINE__;
					continue;
				}
				$productOptionId = getFieldFromId("product_option_id", "product_options", "product_option_id", $thisProductGroupOption['product_option_id']);
				if (empty($productOptionId) && !empty($thisProductGroupOption['product_option_code'])) {
					$thisProductGroupOption['product_option_code'] = makeCode($thisProductGroupOption['product_option_code']);
					$productOptionId = getFieldFromId("product_option_id", "product_options", "product_option_code", $thisProductGroupOption['product_option_code']);
				}
				if (empty($productOptionId)) {
					$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ",") . "Invalid product option: " . jsonEncode($thisProductGroupOption) . "-" . __LINE__;
					continue;
				}
				$productGroupOptionId = getFieldFromId("product_group_option_id", "product_group_options", "product_group_id", $productGroupId, "product_option_id = ?", $productOptionId);
				$fieldNames = array("sequence_number");
				$nameValues = array();
				foreach ($fieldNames as $thisFieldName) {
					if (array_key_exists($thisFieldName, $thisProductGroupOption)) {
						$nameValues[$thisFieldName] = $thisProductGroupOption[$thisFieldName];
					}
				}
				if (empty($nameValues) && !empty($productGroupOptionId)) {
					$foundProductGroupOptionIds[] = $productOptionId;
					continue;
				}
				$nameValues['product_group_id'] = $productGroupId;
				$nameValues['product_option_id'] = $productOptionId;
				if (!$productGroupOptionId = $dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $productGroupOptionId))) {
					$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ",") . "Unable to save product group option: " . $dataTable->getErrorMessage() . "-" . __LINE__;
					continue;
				}
				$foundProductGroupOptionIds[] = $productOptionId;
			}
			$resultSet = executeQuery("select * from product_group_options where product_group_id = ?" .
				(empty($foundProductGroupOptionIds) ? "" : " and product_option_id not in (" . implode(",", $foundProductGroupOptionIds) . ")"), $productGroupId);
			while ($row = getNextRow($resultSet)) {
				$productGroupVariantChoiceId = getFieldFromId("product_group_variant_choice_id", "product_group_variant_choices", "product_option_id", $row['product_option_id'],
					"product_group_variant_id in (select product_group_variant_id from product_group_variants where product_group_id = ?)", $productGroupId);
				if (!empty($productGroupVariantChoiceId)) {
					$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ",") . "Option '" . getFieldFromId("description", "product_options", "product_option_id", $row['product_option_id']) . "' is in use and cannot be removed" . "-" . __LINE__;
					continue;
				}
				if (!$dataTable->deleteRecord(array("primary_id" => $row['product_group_option_id']))) {
					$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ",") . "Unable to delete option from group: " . $dataTable->getErrorMessage() . "-" . __LINE__;
				}
			}
		}
		break;
	case "get_product_groups":
		$returnArray['product_groups'] = array();
		$resultSet = executeQuery("select * from product_groups where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$row['product_group_options'] = array();
			$optionsSet = executeQuery("select * from product_group_options join product_options using (product_option_id) where product_group_id = ?", $row['product_group_id']);
			while ($optionsRow = getNextRow($optionsSet)) {
				$row['product_group_options'][] = $optionsRow;
			}
			$returnArray['product_groups'][] = $row;
		}
		break;
	case "update_product_group_variant":
	case "add_product_group_variant":
		$productGroupId = false;
		if (!empty($_POST['product_group_id'])) {
			$productGroupId = getFieldFromId("product_group_id", "product_groups", "product_group_id", $_POST['product_group_id']);
			if (empty($productGroupId)) {
				$returnArray['error_message'] = "Invalid Product Group" . "-" . __LINE__;
				break;
			}
		}
		if (empty($productGroupId) && !empty($_POST['product_group_code'])) {
			$_POST['product_group_code'] = makeCode($_POST['product_group_code']);
			$productGroupId = getFieldFromId("product_group_id", "product_groups", "product_group_code", $_POST['product_group_code']);
		}
		if (empty($productGroupId)) {
			$returnArray['error_message'] = "Invalid Product Group" . "-" . __LINE__;
			break;
		}
		$productId = false;
		if (!empty($_POST['product_id'])) {
			$productId = getFieldFromId("product_id", "products", "product_id", $_POST['product_id']);
			if (empty($productId)) {
				$returnArray['error_message'] = "Invalid Product" . "-" . __LINE__;
				break;
			}
		}
		if (empty($productId) && !empty($_POST['product_code'])) {
			$productId = getFieldFromId("product_id", "products", "product_code", makeCode($_POST['product_code']));
			if (empty($productId)) {
				$returnArray['error_message'] = "Invalid Product" . "-" . __LINE__;
				break;
			}
		}
		if (empty($productId)) {
			$productId = getFieldFromId("product_id", "product_data", "upc_code", $_POST['upc_code']);
			if (empty($productId)) {
				$returnArray['error_message'] = "Invalid Product" . "-" . __LINE__;
				break;
			}
		}
		$productGroupVariantId = false;
		if (!empty($_POST['product_group_variant_id'])) {
			$productGroupVariantId = getFieldFromId("product_group_variant_id", "product_group_variants", "product_group_variant_id", $_POST['product_group_variant_id'],
				"product_group_id = ? and product_id = ?", $productGroupId, $productId);
			if (empty($productGroupVariantId)) {
				$returnArray['error_message'] = "Invalid Product Group Variant ID" . "-" . __LINE__;
				break;
			}
		}
		$productGroupVariantId = getFieldFromId("product_group_variant_id", "product_group_variants", "product_group_id", $productGroupId,
			"product_id = ?", $productId);
		# expect array of product group option choices for the variant
		# each element of array must have product_option_id and product_option_choice_id and they must go together
		if (!is_array($_POST['product_group_variant_choices'])) {
			$returnArray['error_message'] = "No variant choices included" . "-" . __LINE__;
			break;
		}
		$variantChoices = array();
		foreach ($_POST['product_group_variant_choices'] as $thisVariantChoice) {
			if (!is_array($thisVariantChoice) || (empty($thisVariantChoice['product_option_id']) && empty($thisVariantChoice['product_option_code']))
				|| (empty($thisVariantChoice['product_option_choice_id']) && empty($thisVariantChoice['product_option_choice']))) {
				$returnArray['error_message'] = "Each variant choices must be an array with both 'product_option_id' or 'product_option_code' and 'product_option_choice_id' or 'product_option_choice'" . "-" . __LINE__;
				break 2;
			}
			$productOptionId = getFieldFromId("product_option_id", "product_options", "product_option_id", $thisVariantChoice['product_option_id'],
				"product_option_id in (select product_option_id from product_group_options where product_group_id = ?)", $productGroupId);
			if (empty($productOptionId) && !empty($thisVariantChoice['product_option_code'])) {
				$thisVariantChoice['product_option_code'] = makeCode($thisVariantChoice['product_option_code']);
				$productOptionId = getFieldFromId("product_option_id", "product_options", "product_option_code", $thisVariantChoice['product_option_code'],
					"product_option_id in (select product_option_id from product_group_options where product_group_id = ?)", $productGroupId);
			}
			if (empty($productOptionId)) {
				$returnArray['error_message'] = "Invalid product option in variant choice: " . jsonEncode($thisVariantChoice) . "-" . __LINE__;
				break 2;
			}
			$productOptionChoiceId = getFieldFromId("product_option_choice_id", "product_option_choices", "product_option_choice_id", $thisVariantChoice['product_option_choice_id'],
				"product_option_id = ?", $productOptionId);
			if (empty($productOptionChoiceId) && !empty($thisVariantChoice['product_option_choice'])) {
				$productOptionChoiceId = getFieldFromId("product_option_choice_id", "product_option_choices", "description", $thisVariantChoice['product_option_choice'],
					"product_option_id = ?", $productOptionId);
			}
			if (empty($productOptionChoiceId)) {
				$returnArray['error_message'] = "Invalid product option choice in variant choice: " . jsonEncode($thisVariantChoice) . "-" . __LINE__;
				break 2;
			}
			$variantChoices[$productOptionId] = $productOptionChoiceId;
		}
		if (empty($variantChoices)) {
			$returnArray['error_message'] = "No Variant choices included" . "-" . __LINE__;
			break;
		}
		$allOptionsFound = true;
		$resultSet = executeQuery("select * from product_group_options where product_group_id = ?", $productGroupId);
		while ($row = getNextRow($resultSet)) {
			if (array_key_exists($row['product_option_id'], $variantChoices)) {
				continue;
			}
			if (empty($productGroupVariantId)) {
				$allOptionsFound = false;
				break;
			}
			$productGroupVariantChoiceId = getFieldFromId("product_group_variant_choice_id", "product_group_variant_choices", "product_group_variant_id", $productGroupVariantId,
				"product_option_id = ?", $row['product_option_id']);
			if (empty($productGroupVariantChoiceId)) {
				$allOptionsFound = false;
				break;
			}
		}
		if (!$allOptionsFound) {
			$returnArray['error_message'] = "All options must have a value" . "-" . __LINE__;
			break;
		}
		if (empty($productGroupVariantId)) {
			$insertSet = executeQuery("insert into product_group_variants (product_group_id,product_id) values (?,?)", $productGroupId, $productId);
			if (!empty($insertSet['sql_error'])) {
				$returnArray['error_message'] = "Unable to create product group variant: " . $insertSet['sql_error'] . "-" . __LINE__;
				break;
			}
			$productGroupVariantId = $insertSet['insert_id'];
		}
		foreach ($variantChoices as $productOptionId => $productOptionChoiceId) {
			$productGroupVariantChoiceId = getFieldFromId("product_group_variant_choice_id", "product_group_variant_choices", "product_group_variant_id", $productGroupVariantId,
				"product_option_id = ?", $productOptionId);
			if (empty($productGroupVariantChoiceId)) {
				executeQuery("insert into product_group_variant_choices (product_group_variant_id,product_option_id,product_option_choice_id) values (?,?,?)",
					$productGroupVariantId, $productOptionId, $productOptionChoiceId);
			} else {
				executeQuery("update product_group_variant_choices set product_option_choice_id = ? where product_group_variant_choice_id = ?", $productOptionChoiceId, $productGroupVariantChoiceId);
			}
		}
		break;
	case "get_product_group_variants":
		$productGroupId = getFieldFromId("product_group_id", "product_groups", "product_group_id", $_POST['product_group_id']);
		if (empty($productGroupId)) {
			$returnArray['error_message'] = "Invalid Product Group" . "-" . __LINE__;
			break;
		}
		$returnArray['product_group_variants'] = array();
		$resultSet = executeQuery("select * from product_group_variants join product_group_variant_choices using (product_group_variant_id) where product_group_id = ?", $productGroupId);
		while ($row = getNextRow($resultSet)) {
			$returnArray['product_group_variants'][] = $row;
		}
		break;
}
