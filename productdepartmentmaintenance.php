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

$GLOBALS['gPageCode'] = "PRODUCTDEPARTMENTMAINT";
require_once "shared/startup.inc";

class ProductDepartmentMaintenancePage extends Page {

	function setup() {
		$filters['no_link_name'] = array("form_label" => "No Link Name Set", "where" => "link_name is null", "data_type" => "tinyint");
		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_link_name", "Set Link Name to Description for selected rows");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "set_link_name":
				$returnArray = DataTable::setLinkNames("product_departments");
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$dataTable = new DataTable("product_departments");
		if (!$dataTable->columnExists("out_of_stock_threshold")) {
			$this->iDataSource->addColumnControl("out_of_stock_threshold", "data_type", "hidden");
		} else {
			$this->iDataSource->addColumnControl("out_of_stock_threshold", "form_label", "Out of stock threshold for local locations");
        }

		$this->iDataSource->addColumnControl("detailed_description", "wysiwyg", true);
		$this->iDataSource->addColumnControl("link_name", "classes", "url-link");
		$this->iDataSource->addColumnControl("fragment_id", "get_choices", "fragmentChoices");
		$this->iDataSource->addColumnControl("product_category_departments", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("product_category_departments", "control_table", "product_categories");
		$this->iDataSource->addColumnControl("product_category_departments", "data_type", "custom_control");
		$this->iDataSource->addColumnControl("product_category_departments", "form_label", "Categories");
		$this->iDataSource->addColumnControl("product_category_departments", "links_table", "product_category_departments");
		$this->iDataSource->addColumnControl("product_category_group_departments", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("product_category_group_departments", "control_table", "product_category_groups");
		$this->iDataSource->addColumnControl("product_category_group_departments", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_category_group_departments", "form_label", "Category Groups");
		$this->iDataSource->addColumnControl("product_category_group_departments", "links_table", "product_category_group_departments");
		$this->iDataSource->addColumnControl("product_department_group_links", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("product_department_group_links", "control_table", "product_department_groups");
		$this->iDataSource->addColumnControl("product_department_group_links", "data_type", "custom_control");
		$this->iDataSource->addColumnControl("product_department_group_links", "form_label", "Department Groups");
		$this->iDataSource->addColumnControl("product_department_group_links", "links_table", "product_department_group_links");
		$this->iDataSource->addColumnControl("product_department_restrictions", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("product_department_restrictions", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_department_restrictions", "form_label", "Restricted Locations");
		$this->iDataSource->addColumnControl("product_department_restrictions", "list_table", "product_department_restrictions");
		$this->iDataSource->addColumnControl("product_department_restrictions", "list_table_controls", array("state"=>array("data_type"=>"select","choices"=>getStateArray())));

		$this->iDataSource->addColumnControl("product_department_cannot_sell_distributors", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("product_department_cannot_sell_distributors", "control_table", "product_distributors");
		$this->iDataSource->addColumnControl("product_department_cannot_sell_distributors", "data_type", "custom_control");
		$this->iDataSource->addColumnControl("product_department_cannot_sell_distributors", "form_label", "Cannot sell from these distributors");
		$this->iDataSource->addColumnControl("product_department_cannot_sell_distributors", "links_table", "product_department_cannot_sell_distributors");
	}

	function fragmentChoices($showInactive = false) {
		$fragmentChoices = array();
		$resultSet = executeQuery("select * from fragments where (client_id = ? or client_id = ?) and fragment_type_id is not null and fragment_type_id in (select fragment_type_id from fragment_types where fragment_type_code = 'PRODUCT_DETAIL_HTML') order by sort_order,description",
			$GLOBALS['gDefaultClientId'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$fragmentChoices[$row['fragment_id']] = array("key_value" => $row['fragment_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1);
			}
		}
		freeResult($resultSet);
		return $fragmentChoices;
	}

    function afterSaveDone() {
	    removeCachedData("product_departments_with_out_of_stock_threshold","");
        removeCachedData("product_menu_page_module", "*");
    }

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName === "set_link_name") {
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
}

$pageObject = new ProductDepartmentMaintenancePage("product_departments");
$pageObject->displayPage();
