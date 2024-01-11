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

$GLOBALS['gPageCode'] = "PRICINGSTRUCTUREMAINT";
require_once "shared/startup.inc";

class PricingStructureMaintenancePage extends Page {

	function setup() {
		$hideAdvanced = $this->getPageTextChunk("HIDE_ADVANCED");
		if (!empty($hideAdvanced)) {
			if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
				$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn("pricing_structure_code");
			}
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables("pricing_structure_quantity_discounts,pricing_structure_user_discounts,pricing_structure_contact_discounts,pricing_structure_distributor_surcharges,pricing_structure_order_method_discounts,pricing_structure_payment_method_discounts,pricing_structure_distributor_surcharges");
		$this->iDataSource->addColumnControl("pricing_structure_quantity_discounts", "list_table_controls", array("user_type_id" => array("empty_text" => "[Any]"), "contact_type_id" => array("empty_text" => "[Any]")));
		$this->iDataSource->addColumnControl("pricing_structure_user_discounts", "list_table_controls", array("user_type_id" => array("empty_text" => "[Any]"), "contact_type_id" => array("empty_text" => "[Any]")));
		$this->iDataSource->addColumnControl("pricing_structure_category_quantity_discounts", "data_type", "custom");
		$this->iDataSource->addColumnControl("pricing_structure_category_quantity_discounts", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("pricing_structure_category_quantity_discounts", "list_table", "pricing_structure_category_quantity_discounts");
		$this->iDataSource->addColumnControl("pricing_structure_category_quantity_discounts", "form_label", "Category Quantity Discounts");
		$this->iDataSource->addColumnControl("pricing_structure_category_quantity_discounts", "help_label", "These are typically lower prices for quantity purchases. Only applied in the Shopping Cart unless the quantity is one.");

		$this->iDataSource->addColumnControl("pricing_structure_price_discounts", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("pricing_structure_price_discounts", "data_type", "custom");
		$this->iDataSource->addColumnControl("pricing_structure_price_discounts", "form_label", "Price Discounts");
		$this->iDataSource->addColumnControl("pricing_structure_price_discounts", "list_table", "pricing_structure_price_discounts");
		$this->iDataSource->addColumnControl("pricing_structure_quantity_discounts", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("pricing_structure_quantity_discounts", "data_type", "custom_control");
		$this->iDataSource->addColumnControl("pricing_structure_quantity_discounts", "form_label", "Quantity Discounts");
		$this->iDataSource->addColumnControl("pricing_structure_quantity_discounts", "list_table", "pricing_structure_quantity_discounts");
		$this->iDataSource->addColumnControl("pricing_structure_quantity_discounts", "list_table_controls", array("amount"=>array("form_label"=>"Discount Amount")));
		$this->iDataSource->addColumnControl("pricing_structure_quantity_discounts", "help_label", "Both amount and percentage will be applied. To use only amount, set percentage same as base percentage above.");

		$this->iDataSource->addColumnControl("pricing_structure_user_discounts", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("pricing_structure_user_discounts", "data_type", "custom_control");
		$this->iDataSource->addColumnControl("pricing_structure_user_discounts", "form_label", "User Discounts");
		$this->iDataSource->addColumnControl("pricing_structure_user_discounts", "list_table", "pricing_structure_user_discounts");

		$this->iDataSource->addColumnControl("percentage", "form_label", "Base Percentage");
		$this->iDataSource->addColumnControl("minimum_markup", "form_label", "Minimum Percentage");
		$this->iDataSource->addColumnControl("minimum_markup", "form_line_classes", "price-calculation-type price-calculation-type-margin price-calculation-type-markup");
		$this->iDataSource->addColumnControl("minimum_amount", "form_label", "Minimum Profit");
		$this->iDataSource->addColumnControl("maximum_discount", "form_label", "Maximum Discount Percentage");
		$this->iDataSource->addColumnControl("maximum_discount", "form_line_classes", "price-calculation-type price-calculation-type-discount");
		$this->iDataSource->addColumnControl("price_calculation_type_id", "get_choices", "priceCalculationTypeChoices");
		$this->iDataSource->addColumnControl("use_list_price", "form_line_classes", "price-calculation-type price-calculation-type-margin price-calculation-type-markup");

		$this->iDataSource->addColumnControl("user_type_id", "help_label", "This pricing structure only applies to users with this user type");
		$this->iDataSource->addColumnControl("user_type_id", "form_line_classes", "advanced-feature");
		$this->iDataSource->addColumnControl("internal_use_only", "form_line_classes", "advanced-feature");
		$this->iDataSource->addColumnControl("inactive", "form_label", "Make this pricing structure inactive");
		$this->iDataSource->addColumnControl("pricing_structure_code", "form_line_classes", "advanced-feature");

		$this->iDataSource->addColumnControl("pricing_structure_distributor_surcharges", "data_type", "custom");
		$this->iDataSource->addColumnControl("pricing_structure_distributor_surcharges", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("pricing_structure_distributor_surcharges", "list_table", "pricing_structure_distributor_surcharges");
		$this->iDataSource->addColumnControl("pricing_structure_distributor_surcharges", "form_label", "Distributor Surcharges");
		$this->iDataSource->addColumnControl("pricing_structure_distributor_surcharges", "help_label", "Percentage will be added to markup (or reduce discount) for selected distributor when product is only available from that distributor");

		$this->iDataSource->addColumnControl("low_inventory_percentage", "form_label", "Additional percentage when total inventory is at or below quantity");

		$this->iDataSource->addColumnControl("price_structure_departments", "data_type", "custom");
		$this->iDataSource->addColumnControl("price_structure_departments", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("price_structure_departments", "form_label", "");
		$this->iDataSource->addColumnControl("price_structure_departments", "control_table", "product_departments");
		$this->iDataSource->addColumnControl("price_structure_departments", "button_selectors", true);
		$this->iDataSource->addColumnControl("price_structure_departments", "get_links", "getDepartmentLinks");
		$this->iDataSource->addColumnControl("price_structure_departments", "save_data", "saveDepartmentLinks");

		$this->iDataSource->addColumnControl("price_structure_categories", "data_type", "custom");
		$this->iDataSource->addColumnControl("price_structure_categories", "control_class", "MultipleDropdown");
		$this->iDataSource->addColumnControl("price_structure_categories", "form_label", "");
		$this->iDataSource->addColumnControl("price_structure_categories", "control_table", "product_categories");
		$this->iDataSource->addColumnControl("price_structure_categories", "get_links", "getCategoryLinks");
		$this->iDataSource->addColumnControl("price_structure_categories", "save_data", "saveCategoryLinks");

		$this->iDataSource->addColumnControl("price_structure_manufacturers", "data_type", "custom");
		$this->iDataSource->addColumnControl("price_structure_manufacturers", "control_class", "MultipleDropdown");
		$this->iDataSource->addColumnControl("price_structure_manufacturers", "form_label", "");
		$this->iDataSource->addColumnControl("price_structure_manufacturers", "control_table", "product_manufacturers");
		$this->iDataSource->addColumnControl("price_structure_manufacturers", "get_links", "getManufacturerLinks");
		$this->iDataSource->addColumnControl("price_structure_manufacturers", "save_data", "saveManufacturerLinks");

		$this->iDataSource->addColumnControl("price_structure_product_types", "data_type", "custom");
		$this->iDataSource->addColumnControl("price_structure_product_types", "control_class", "MultipleDropdown");
		$this->iDataSource->addColumnControl("price_structure_product_types", "form_label", "");
		$this->iDataSource->addColumnControl("price_structure_product_types", "control_table", "product_types");
		$this->iDataSource->addColumnControl("price_structure_product_types", "get_links", "getProductTypeLinks");
		$this->iDataSource->addColumnControl("price_structure_product_types", "save_data", "saveProductTypeLinks");

		$this->iDataSource->addColumnControl("price_structure_products", "data_type", "custom");
		$this->iDataSource->addColumnControl("price_structure_products", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("price_structure_products", "form_label", "");
		$this->iDataSource->addColumnControl("price_structure_products", "column_list", "product_id");
		$this->iDataSource->addColumnControl("price_structure_products", "list_table_controls", array("product_id" => array("form_label" => "Product", "data-autocomplete_tag" => "products")));
		$this->iDataSource->addColumnControl("price_structure_products", "save_data", "saveProductLinks");
		$this->iDataSource->addColumnControl("price_structure_products", "get_record", "getProducts");
	}

	function getProducts($controlName, $priceStructureId) {
		$records = array();
		$resultSet = executeQuery("select product_id from products where pricing_structure_id = ?", $priceStructureId);
		while ($row = getNextRow($resultSet)) {
			$records[] = array("product_id" => array("data_value" => $row['product_id'], "crc_value" => getCrcValue($row['product_id'])));
		}
		return array("has_product_filters" => array("data_value" => (count($records) > 0 ? "1" : "0"), "crc_value" => getCrcValue((count($records) > 0 ? "1" : "0"))), $controlName => $records);
	}

	function saveProductLinks($controlName, $nameValues) {
		$productIds = array();
		if ($nameValues['has_product_filters']) {
			foreach ($nameValues as $fieldName => $fieldValue) {
				if (startsWith($fieldName, "price_structure_products_product_id-")) {
					$productId = getFieldFromId("product_id", "products", "product_id", $fieldValue);
					if (!empty($productId)) {
						$productIds[] = $productId;
					}
				}
			}
		}
		if (empty($productIds)) {
			$resultSet = executeQuery("update products set pricing_structure_id = null where pricing_structure_id = ?", $nameValues['primary_id']);
		} else {
			$resultSet = executeQuery("update products set pricing_structure_id = ? where product_id in (" . implode(",", $productIds) . ") and client_id = ?", $nameValues['primary_id'], $GLOBALS['gClientId']);
			$resultSet = executeQuery("update products set pricing_structure_id = null where pricing_structure_id = ? and product_id not in (" . implode(",", $productIds) . ") and client_id = ?", $nameValues['primary_id'], $GLOBALS['gClientId']);
		}
		return true;
	}

	function getDepartmentLinks($priceStructureId) {
		$returnIds = "";
		$resultSet = executeQuery("select * from product_departments where pricing_structure_id = ? and client_id = ?", $priceStructureId, $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$returnIds .= (empty($returnIds) ? "" : ",") . $row['product_department_id'];
		}
		return $returnIds;
	}

	function saveDepartmentLinks($nameValues) {
		if ($nameValues['has_department_filters']) {
			$priceStructureDepartments = explode(",", $nameValues['price_structure_departments']);
			foreach ($priceStructureDepartments as $index => $priceStructureDepartment) {
				$productDepartmentId = getFieldFromId("product_department_id", "product_departments", "product_department_id", $priceStructureDepartment);
				if (empty($productDepartmentId)) {
					unset($priceStructureDepartments[$index]);
				}
			}
		} else {
			$priceStructureDepartments = array();
		}
		if (empty($priceStructureDepartments)) {
			$resultSet = executeQuery("update product_departments set pricing_structure_id = null where pricing_structure_id = ?", $nameValues['primary_id']);
		} else {
			$resultSet = executeQuery("update product_departments set pricing_structure_id = ? where product_department_id in (" . implode(",", $priceStructureDepartments) . ") and client_id = ?", $nameValues['primary_id'], $GLOBALS['gClientId']);
			$resultSet = executeQuery("update product_departments set pricing_structure_id = null where pricing_structure_id = ? and product_department_id not in (" . implode(",", $priceStructureDepartments) . ") and client_id = ?", $nameValues['primary_id'], $GLOBALS['gClientId']);
		}
		return true;
	}

	function getCategoryLinks($priceStructureId) {
		$returnIds = "";
		$resultSet = executeQuery("select * from product_categories where pricing_structure_id = ? and client_id = ?", $priceStructureId, $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$returnIds .= (empty($returnIds) ? "" : ",") . $row['product_category_id'];
		}
		return $returnIds;
	}

	function saveCategoryLinks($nameValues) {
		if ($nameValues['has_category_filters']) {
			$priceStructureCategories = explode(",", $nameValues['price_structure_categories']);
			foreach ($priceStructureCategories as $index => $priceStructureCategory) {
				$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $priceStructureCategory);
				if (empty($productCategoryId)) {
					unset($priceStructureCategories[$index]);
				}
			}
		} else {
			$priceStructureCategories = array();
		}
		if (empty($priceStructureCategories)) {
			$resultSet = executeQuery("update product_categories set pricing_structure_id = null where pricing_structure_id = ?", $nameValues['primary_id']);
		} else {
			$resultSet = executeQuery("update product_categories set pricing_structure_id = ? where product_category_id in (" . implode(",", $priceStructureCategories) . ") and client_id = ?", $nameValues['primary_id'], $GLOBALS['gClientId']);
			$resultSet = executeQuery("update product_categories set pricing_structure_id = null where pricing_structure_id = ? and product_category_id not in (" . implode(",", $priceStructureCategories) . ") and client_id = ?", $nameValues['primary_id'], $GLOBALS['gClientId']);
		}
		return true;
	}

	function getManufacturerLinks($priceStructureId) {
		$returnIds = "";
		$resultSet = executeQuery("select * from product_manufacturers where pricing_structure_id = ? and client_id = ?", $priceStructureId, $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$returnIds .= (empty($returnIds) ? "" : ",") . $row['product_manufacturer_id'];
		}
		return $returnIds;
	}

	function saveManufacturerLinks($nameValues) {
		if ($nameValues['has_manufacturer_filters']) {
			$priceStructureManufacturers = explode(",", $nameValues['price_structure_manufacturers']);
			foreach ($priceStructureManufacturers as $index => $priceStructureManufacturer) {
				$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $priceStructureManufacturer);
				if (empty($productManufacturerId)) {
					unset($priceStructureManufacturers[$index]);
				}
			}
		} else {
			$priceStructureManufacturers = array();
		}
		if (empty($priceStructureManufacturers)) {
			$resultSet = executeQuery("update product_manufacturers set pricing_structure_id = null where pricing_structure_id = ?", $nameValues['primary_id']);
		} else {
			$resultSet = executeQuery("update product_manufacturers set pricing_structure_id = ? where product_manufacturer_id in (" . implode(",", $priceStructureManufacturers) . ") and client_id = ?", $nameValues['primary_id'], $GLOBALS['gClientId']);
			$resultSet = executeQuery("update product_manufacturers set pricing_structure_id = null where pricing_structure_id = ? and product_manufacturer_id not in (" . implode(",", $priceStructureManufacturers) . ") and client_id = ?", $nameValues['primary_id'], $GLOBALS['gClientId']);
		}
		return true;
	}

	function getProductTypeLinks($priceStructureId) {
		$returnIds = "";
		$resultSet = executeQuery("select * from product_types where pricing_structure_id = ? and client_id = ?", $priceStructureId, $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$returnIds .= (empty($returnIds) ? "" : ",") . $row['product_type_id'];
		}
		return $returnIds;
	}

	function saveProductTypeLinks($nameValues) {
		if ($nameValues['has_product_type_filters']) {
			$priceStructureProductTypes = explode(",", $nameValues['price_structure_product_types']);
			foreach ($priceStructureProductTypes as $index => $priceStructureProductType) {
				$productProductTypeId = getFieldFromId("product_type_id", "product_types", "product_type_id", $priceStructureProductType);
				if (empty($productProductTypeId)) {
					unset($priceStructureProductTypes[$index]);
				}
			}
		} else {
			$priceStructureProductTypes = array();
		}
		if (empty($priceStructureProductTypes)) {
			$resultSet = executeQuery("update product_types set pricing_structure_id = null where pricing_structure_id = ?", $nameValues['primary_id']);
		} else {
			$resultSet = executeQuery("update product_types set pricing_structure_id = ? where product_type_id in (" . implode(",", $priceStructureProductTypes) . ") and client_id = ?", $nameValues['primary_id'], $GLOBALS['gClientId']);
			$resultSet = executeQuery("update product_types set pricing_structure_id = null where pricing_structure_id = ? and product_type_id not in (" . implode(",", $priceStructureProductTypes) . ") and client_id = ?", $nameValues['primary_id'], $GLOBALS['gClientId']);
		}
		return true;
	}

	function priceCalculationTypeChoices($showInactive = false) {
		$priceCalculationTypeChoices = array();
		$resultSet = executeReadQuery("select * from price_calculation_types");
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$priceCalculationTypeChoices[$row['price_calculation_type_id']] = array("key_value" => $row['price_calculation_type_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1, "data-price_calculation_type_code" => $row['price_calculation_type_code']);
			}
		}
		freeResult($resultSet);
		return $priceCalculationTypeChoices;
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['debug'] = true;
		$departmentCount = (empty($returnArray['primary_id']['data_value']) ? 0 : getFieldFromId("count(*)", "product_departments", "pricing_structure_id", $returnArray['primary_id']['data_value']));
		$returnArray['has_department_filters'] = array("data_value" => ($departmentCount > 0 ? "1" : "0"), "crc_value" => getCrcValue(($departmentCount > 0 ? "1" : "0")));
		$categoryCount = (empty($returnArray['primary_id']['data_value']) ? 0 : getFieldFromId("count(*)", "product_categories", "pricing_structure_id", $returnArray['primary_id']['data_value']));
		$returnArray['has_category_filters'] = array("data_value" => ($categoryCount > 0 ? "1" : "0"), "crc_value" => getCrcValue(($categoryCount > 0 ? "1" : "0")));
		$manufacturerCount = (empty($returnArray['primary_id']['data_value']) ? 0 : getFieldFromId("count(*)", "product_manufacturers", "pricing_structure_id", $returnArray['primary_id']['data_value']));
		$returnArray['has_manufacturer_filters'] = array("data_value" => ($manufacturerCount > 0 ? "1" : "0"), "crc_value" => getCrcValue(($manufacturerCount > 0 ? "1" : "0")));
		$productTypeCount = (empty($returnArray['primary_id']['data_value']) ? 0 : getFieldFromId("count(*)", "product_types", "pricing_structure_id", $returnArray['primary_id']['data_value']));
		$returnArray['has_product_type_filters'] = array("data_value" => ($productTypeCount > 0 ? "1" : "0"), "crc_value" => getCrcValue(($productTypeCount > 0 ? "1" : "0")));

		if ($returnArray['pricing_structure_code']['data_value'] == "DEFAULT") {
			$returnArray['structure_application_type'] = "all";
		} else {
			$returnArray['structure_application_type'] = "specific";
		}
		$returnArray['setup_type'] = "basic";
		$fieldsArray = array("internal_use_only", "user_type_id", "low_inventory_percentage", "low_inventory_quantity");
		foreach ($fieldsArray as $thisField) {
			if (!empty($returnArray[$thisField]['data_value'])) {
				$returnArray['setup_type'] = "advanced";
			}
		}
		if ($returnArray['setup_type'] == "basic") {
			$subtables = array("pricing_structure_quantity_discounts", "pricing_structure_category_quantity_discounts", "pricing_structure_distributor_surcharges", "pricing_structure_user_discounts");
			foreach ($subtables as $subtable) {
				$count = getFieldFromId("count(*)", $subtable, "pricing_structure_id", $returnArray['primary_id']['data_value']);
				if ($count > 0) {
					$returnArray['setup_type'] = "advanced";
					break;
				}
			}
		}
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
                $("#price_calculation_type_id").trigger("change");
                $("#structure_application").removeClass("hidden");
                $("#" + returnArray['setup_type'] + "_setup_button").trigger("click");
                $("#" + returnArray['structure_application_type'] + "_application").trigger("click");
                if (empty(returnArray['primary_id']['data_value'])) {
                    $("#structure_application_types").removeClass("hidden");
                } else {
                    if (returnArray['pricing_structure_code']['data_value'] == "DEFAULT") {
                        $("#structure_application").addClass("hidden");
                    } else {
                        $("#structure_application_types").addClass("hidden");
                    }
                }
                if (empty($("#has_product_filters").val())) {
                    $("#structure_application_product").removeClass("selected");
                    $("#" + $("#structure_application_product").data("filter_type") + "_filters").removeClass("active");
                } else {
                    $("#structure_application_product").addClass("selected");
                    $("#" + $("#structure_application_product").data("filter_type") + "_filters").addClass("active");
                }
                if (empty($("#has_department_filters").val())) {
                    $("#structure_application_department").removeClass("selected");
                    $("#" + $("#structure_application_department").data("filter_type") + "_filters").removeClass("active");
                } else {
                    $("#structure_application_department").addClass("selected");
                    $("#" + $("#structure_application_department").data("filter_type") + "_filters").addClass("active");
                }
                if (empty($("#has_category_filters").val())) {
                    $("#structure_application_category").removeClass("selected");
                    $("#" + $("#structure_application_category").data("filter_type") + "_filters").removeClass("active");
                } else {
                    $("#structure_application_category").addClass("selected");
                    $("#" + $("#structure_application_category").data("filter_type") + "_filters").addClass("active");
                }
                if (empty($("#has_manufacturer_filters").val())) {
                    $("#structure_application_manufacturer").removeClass("selected");
                    $("#" + $("#structure_application_manufacturer").data("filter_type") + "_filters").removeClass("active");
                } else {
                    $("#structure_application_manufacturer").addClass("selected");
                    $("#" + $("#structure_application_manufacturer").data("filter_type") + "_filters").addClass("active");
                }
                if (empty($("#has_product_type_filters").val())) {
                    $("#structure_application_product_type").removeClass("selected");
                    $("#" + $("#structure_application_product_type").data("filter_type") + "_filters").removeClass("active");
                } else {
                    $("#structure_application_product_type").addClass("selected");
                    $("#" + $("#structure_application_product_type").data("filter_type") + "_filters").addClass("active");
                }
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		$hideAdvanced = $this->getPageTextChunk("HIDE_ADVANCED");
		?>
        <script>
			<?php if (empty($hideAdvanced)) { ?>
            $("#setup_types").removeClass("hidden");
			<?php } ?>
            $(document).on("click", "#application_filters .setup-button", function () {
                if ($(this).hasClass("selected")) {
                    $(this).removeClass("selected");
                    $("#" + $(this).data("filter_type") + "_filters").removeClass("active");
                    $("#has_" + $(this).data("filter_type") + "_filters").val("0");
                } else {
                    $(this).addClass("selected");
                    $("#" + $(this).data("filter_type") + "_filters").addClass("active");
                    $("#has_" + $(this).data("filter_type") + "_filters").val("1");
                }
            });
            $(document).on("change", "#price_calculation_type_id", function () {
                if (!empty($(this).val())) {
                    $("#_main_content").attr("class", "").addClass($(this).find("option:selected").data("price_calculation_type_code").toLowerCase());
                }
            });
            $(document).on("click", "#setup_types .setup-button", function () {
                $("#_maintenance_form").attr("class", "").addClass($(this).data('setup_type'));
                $("#setup_types").find(".setup-button").removeClass("selected");
                $(this).addClass("selected");
            });
            $(document).on("click", "#structure_application_types .setup-button", function () {
                $("#structure_application_types").find(".setup-button").removeClass("selected");
                $(this).addClass("selected");
                if ($(this).is("#all_application")) {
                    $("#application_filters").addClass("hidden");
                    $("#pricing_structure_code").val("DEFAULT");
                } else {
                    $("#application_filters").removeClass("hidden");
                    if ($("#pricing_structure_code").val() == "DEFAULT") {
                        $("#pricing_structure_code").val("");
                    }
                }
            });
        </script>
		<?php
	}

	function beforeSaveChanges(&$nameValues) {
		if (empty($nameValues['pricing_structure_code'])) {
			$nameValues['pricing_structure_code'] = strtoupper(getRandomString(32));
		}
		$errorMessage = "";
		if (!empty($nameValues['price_structure_departments'])) {
			$subtableIds = explode(",", $nameValues['price_structure_departments']);
			foreach ($subtableIds as $thisId) {
				$checkId = getFieldFromId("pricing_structure_id", "product_departments", "product_department_id", $thisId,
					"pricing_structure_id is not null and pricing_structure_id <> ?", $nameValues['primary_id']);
				if (!empty($checkId)) {
					$errorMessage .= (empty($errorMessage) ? "" : ", ") . "Department '" . getFieldFromId("description", "product_departments", "product_department_id", $thisId) .
						"' already uses pricing structure '" . getFieldFromId("description", "pricing_structures", "pricing_structure_id", $checkId) . "'";
				}
			}
		}
		if (!empty($nameValues['price_structure_categories'])) {
			$subtableIds = explode(",", $nameValues['price_structure_categories']);
			foreach ($subtableIds as $thisId) {
				$checkId = getFieldFromId("pricing_structure_id", "product_categories", "product_category_id", $thisId,
					"pricing_structure_id is not null and pricing_structure_id <> ?", $nameValues['primary_id']);
				if (!empty($checkId)) {
					$errorMessage .= (empty($errorMessage) ? "" : ", ") . "Category '" . getFieldFromId("description", "product_categories", "product_category_id", $thisId) .
						"' already uses pricing structure '" . getFieldFromId("description", "pricing_structures", "pricing_structure_id", $checkId) . "'";
				}
			}
		}
		if (!empty($nameValues['price_structure_manufacturers'])) {
			$subtableIds = explode(",", $nameValues['price_structure_manufacturers']);
			foreach ($subtableIds as $thisId) {
				$checkId = getFieldFromId("pricing_structure_id", "product_manufacturers", "product_manufacturer_id", $thisId,
					"pricing_structure_id is not null and pricing_structure_id <> ?", $nameValues['primary_id']);
				if (!empty($checkId)) {
					$errorMessage .= (empty($errorMessage) ? "" : ", ") . "Manufacturer '" . getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $thisId) .
						"' already uses pricing structure '" . getFieldFromId("description", "pricing_structures", "pricing_structure_id", $checkId) . "'";
				}
			}
		}
		if (!empty($nameValues['price_structure_product_types'])) {
			$subtableIds = explode(",", $nameValues['price_structure_product_types']);
			foreach ($subtableIds as $thisId) {
				$checkId = getFieldFromId("pricing_structure_id", "product_types", "product_type_id", $thisId,
					"pricing_structure_id is not null and pricing_structure_id <> ?", $nameValues['primary_id']);
				if (!empty($checkId)) {
					$errorMessage .= (empty($errorMessage) ? "" : ", ") . "Product Type '" . getFieldFromId("description", "product_types", "product_type_id", $thisId) .
						"' already uses pricing structure '" . getFieldFromId("description", "pricing_structures", "pricing_structure_id", $checkId) . "'";
				}
			}
		}
		foreach ($nameValues as $fieldName => $fieldValue) {
			if (!startsWith($fieldName, "price_structure_products_product_id-")) {
				continue;
			}
			$checkId = getFieldFromId("pricing_structure_id", "products", "product_id", $fieldValue,
				"pricing_structure_id is not null and pricing_structure_id <> ?", $nameValues['primary_id']);
			if (!empty($checkId)) {
				$errorMessage .= (empty($errorMessage) ? "" : ", ") . "Product '" . getFieldFromId("description", "products", "product_id", $fieldValue) .
					"' already uses pricing structure '" . getFieldFromId("description", "pricing_structures", "pricing_structure_id", $checkId) . "'";
			}
		}
        if (!empty($errorMessage)) {
	        return $errorMessage;
        }
		return true;
	}

	function internalCSS() {
		?>
        <style>
            #_main_content .price-calculation-type {
                display: none;
            }
            #_main_content.margin .price-calculation-type-margin {
                display: block;
            }
            #_main_content.markup .price-calculation-type-markup {
                display: block;
            }
            #_main_content.discount .price-calculation-type-discount {
                display: block;
            }
            .setup-button {
                padding: 10px 20px;
                box-shadow: 0 2px 2px rgba(0, 0, 0, 0.05);
                border-radius: .25rem;
                display: inline-block;
                cursor: pointer;
                margin: 0 1rem 1rem 0;
                border: 1px solid rgb(180, 180, 180);
            }
            #_main_content .setup-button h5 {
                padding: 0;
                margin: 0;
                font-size: .9rem;
                color: rgb(100, 100, 100);
            }
            #_main_content .setup-button p {
                padding: 0;
                margin: 5px 0 0 0;
                font-size: .7rem;
                color: rgb(100, 100, 100);
                max-width: 250px;
            }
            .setup-button.selected {
                background-color: rgb(240, 240, 240);
            }
            #_maintenance_form.basic .advanced-feature {
                display: none;
            }
            #structure_application {
                padding: 20px;
                margin: 10px 0 20px;
                border: 1px solid rgb(200, 200, 200);
            }
            .filter-control {
                display: none;
            }
            .filter-control.active {
                display: block;
            }
        </style>
		<?php
	}

	function afterSaveChanges($nameValues,$actionPerformed) {
		removeCachedData("pricing_structure_data","contact_types");
		removeCachedData("pricing_structure_data","user_types");
		return true;
	}
}

$pageObject = new PricingStructureMaintenancePage("pricing_structures");
$pageObject->displayPage();
