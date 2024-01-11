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

$GLOBALS['gPageCode'] = "AVANTLINKSETUP";
require_once "shared/startup.inc";

class AvantLinkSetupPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "delete", "list", "changes"));
		}
	}

	function massageUrlParameters() {
		$_GET['url_subpage'] = $_GET['url_page'];
		$_GET['url_page'] = "show";
		$_GET['primary_id'] = $GLOBALS['gClientId'];
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("domain_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("domain_name", "form_label", "Domain Name");
		$this->iDataSource->addColumnControl("domain_name", "help_label", "Domain name used for Avant Link listings. Leave blank to use your default domain name.");
		$this->iDataSource->addColumnControl("domain_name", "not_null", false);

		$this->iDataSource->addColumnControl("base_commission", "data_type", "decimal");
		$this->iDataSource->addColumnControl("base_commission", "decimal_places", "2");
		$this->iDataSource->addColumnControl("base_commission", "data_size", "4");
		$this->iDataSource->addColumnControl("base_commission", "minimum_value", "0");
		$this->iDataSource->addColumnControl("base_commission", "maximum_value", "99.99");
		$this->iDataSource->addColumnControl("base_commission", "form_label", "Base Commission");
		$this->iDataSource->addColumnControl("base_commission", "not_null", true);

		$this->iDataSource->addColumnControl("department_commissions", "data_type", "custom");
		$this->iDataSource->addColumnControl("department_commissions", "form_label", "Department Commissions");
		$this->iDataSource->addColumnControl("department_commissions", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("department_commissions", "column_list", "product_department_id,percentage");
		$this->iDataSource->addColumnControl("department_commissions", "list_table_controls",
			array("product_department_id" => array("data_type" => "select", "get_choices" => "departmentChoices", "form_label" => "Department", "not_null" => true),
				"percentage" => array("data_type" => "decimal", "decimal_places" => "2", "data_size" => "4", "minimum_value" => "0", "maximum_value" => "99.99", "not_null" => true, "form_label" => "% Commission")));

		$this->iDataSource->addColumnControl("category_commissions", "data_type", "custom");
		$this->iDataSource->addColumnControl("category_commissions", "form_label", "Category Commissions");
		$this->iDataSource->addColumnControl("category_commissions", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("category_commissions", "column_list", "product_category_id,percentage");
		$this->iDataSource->addColumnControl("category_commissions", "list_table_controls",
			array("product_category_id" => array("data_type" => "select", "get_choices" => "categoryChoices", "form_label" => "Category", "not_null" => true),
				"percentage" => array("data_type" => "decimal", "decimal_places" => "2", "data_size" => "4", "minimum_value" => "0", "maximum_value" => "99.99", "not_null" => true, "form_label" => "% Commission")));

		$this->iDataSource->addColumnControl("manufacturer_commissions", "data_type", "custom");
		$this->iDataSource->addColumnControl("manufacturer_commissions", "form_label", "Manufacturer Commissions");
		$this->iDataSource->addColumnControl("manufacturer_commissions", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("manufacturer_commissions", "column_list", "product_manufacturer_id,percentage");
		$this->iDataSource->addColumnControl("manufacturer_commissions", "list_table_controls",
			array("product_manufacturer_id" => array("data_type" => "autocomplete", "data-autocomplete_tag" => "product_manufacturers", "form_label" => "Manufacturer", "not_null" => true),
				"percentage" => array("data_type" => "decimal", "decimal_places" => "2", "data_size" => "4", "minimum_value" => "0", "maximum_value" => "99.99", "not_null" => true, "form_label" => "% Commission")));

		$this->iDataSource->addColumnControl("manufacturer_category_commissions", "data_type", "custom");
		$this->iDataSource->addColumnControl("manufacturer_category_commissions", "form_label", "Manufacturer/Category Commissions");
		$this->iDataSource->addColumnControl("manufacturer_category_commissions", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("manufacturer_category_commissions", "column_list", "product_manufacturer_id,product_category_id,percentage");
		$this->iDataSource->addColumnControl("manufacturer_category_commissions", "list_table_controls",
			array("product_manufacturer_id" => array("data_type" => "autocomplete", "data-autocomplete_tag" => "product_manufacturers", "form_label" => "Manufacturer", "not_null" => true),
				"product_category_id" => array("data_type" => "select", "get_choices" => "categoryChoices", "form_label" => "Category", "not_null" => true),
				"percentage" => array("data_type" => "decimal", "decimal_places" => "2", "data_size" => "4", "minimum_value" => "0", "maximum_value" => "99.99", "not_null" => true, "form_label" => "% Commission")));

		$this->iDataSource->addColumnControl("product_commissions", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_commissions", "form_label", "Specific Product Commissions");
		$this->iDataSource->addColumnControl("product_commissions", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("product_commissions", "column_list", "product_id,percentage");
		$this->iDataSource->addColumnControl("product_commissions", "list_table_controls",
			array("product_id" => array("data_type" => "autocomplete", "data-autocomplete_tag" => "products", "form_label" => "Product", "not_null" => true),
				"percentage" => array("data_type" => "decimal", "decimal_places" => "2", "data_size" => "4", "minimum_value" => "0", "maximum_value" => "99.99", "not_null" => true, "form_label" => "% Commission")));

	}

	function departmentChoices() {
		$departmentChoices = array();
		$resultSet = executeQuery("select product_department_id,description,inactive from product_departments where client_id = ? and " .
			"inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$departmentChoices[$row['product_department_id']] = array("key_value" => $row['product_department_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1);
		}
		return $departmentChoices;
	}

	function categoryChoices() {
		$categoryChoices = array();
		$resultSet = executeQuery("select product_category_id,description,inactive from product_categories where client_id = ? and " .
			"inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$categoryChoices[$row['product_category_id']] = array("key_value" => $row['product_category_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1);
		}
		return $categoryChoices;
	}

    function departmentSettings() {
		$resultSet = executeQuery("select * from product_departments where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			?>
            <h3><?= htmlText($row['description']) ?></h3>
            <div class='basic-form-line inline-block'>
                <label for='department_products_<?= $row['product_department_id'] ?>'>What is listed?</label>
                <select id='department_products_<?= $row['product_department_id'] ?>' name='department_products_<?= $row['product_department_id'] ?>'>
                    <option value='all'>All Products</option>
                    <option value=''>Only In-Stock Products</option>
                    <option value='local'>All Products at Local Location(s)</option>
                    <option value='localstock'>In Stock at Local Location(s)</option>
                    <option value='nothing'>Nothing</option>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
			<?php
		}
    }

	function afterGetRecord(&$returnArray) {
		$fieldArray = array("department_commissions", "category_commissions", "manufacturer_commissions", "manufacturer_category_commissions", "product_commissions");
		$preferences = self::getClientPagePreferences();
		$returnArray['base_commission'] = array("data_value" => $preferences['base_commission'], "crc_value" => getCrcValue($preferences['base_commission']));
		$returnArray['domain_name'] = array("data_value" => $preferences['domain_name'], "crc_value" => getCrcValue($preferences['domain_name']));
		$resultSet = executeQuery("select * from product_departments where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$returnArray['department_products_' . $row['product_department_id']] = array("data_value" => $preferences['department_products_' . $row['product_department_id']], "crc_value" => getCrcValue($preferences['department_products_' . $row['product_department_id']]));
		}

		$fieldColumns = array();
		foreach ($fieldArray as $editableListField) {
			if (!array_key_exists($editableListField, $fieldColumns)) {
				$fieldColumns[$editableListField] = array();
			}
			foreach ($preferences as $fieldName => $fieldValue) {
				if (startsWith($fieldName, $editableListField . "_")) {
					$parts = explode("-", str_replace($editableListField . "_", "", $fieldName));
					if (!in_array($parts[0], $fieldColumns[$editableListField])) {
						$fieldColumns[$editableListField][] = $parts[0];
					}
				}
			}
		}
		foreach ($fieldColumns as $editableListField => $columnNames) {
			$returnArray[$editableListField] = array();
			$firstColumn = $columnNames[0];
			if (empty($firstColumn)) {
				continue;
			}
			foreach ($preferences as $fieldName => $fieldValue) {
				if (startsWith($fieldName, $editableListField . "_" . $firstColumn)) {
					$recordNumber = str_replace($editableListField . "_" . $firstColumn . "-", "", $fieldName);
                    if (!is_numeric($recordNumber)) {
                        continue;
                    }
                    $newRecord = array();
                    foreach ($columnNames as $thisColumnName) {
                        $newRecord[$thisColumnName] = array("data_value"=>$preferences[$editableListField . "_" . $thisColumnName . "-" . $recordNumber], "crc_value"=>getCrcValue($preferences[$editableListField . "_" . $thisColumnName . "-" . $recordNumber]));
                    }
                    $returnArray[$editableListField][$recordNumber] = $newRecord;
				}
			}
		}
	}

	function saveChanges() {
		self::setClientPagePreferences($_POST);
		$returnArray['info_message'] = "Information Saved";
		ajaxResponse($returnArray);
        return true;
	}
}

$pageObject = new AvantLinkSetupPage("clients");
$pageObject->displayPage();
