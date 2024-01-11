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
		$this->iDataSource->addColumnControl("link_name", "classes", "url-link");

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
        $this->iDataSource->addColumnControl("product_department_cannot_sell_distributors", "get_choices", "distributorChoices");
	}

    function distributorChoices($showInactive = false) {
        $distributorChoices = array();
        $resultSet = executeQuery("select product_distributors.* from locations join product_distributors using (product_distributor_id) where locations.inactive = 0 and client_id = ?" .
            (empty($GLOBALS['gUserRow']['administrator_flag']) ? " and ((user_location = 1 and user_id = " . $GLOBALS['gUserId'] . ") or (location_id in (select location_id from ffl_locations where federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id = " . $GLOBALS['gUserId'] . "))))" : " and user_location = 0") . " order by sort_order,description",
            $GLOBALS['gClientId']);
        while ($row = getNextRow($resultSet)) {
            $distributorChoices[$row['product_distributor_id']] = array("key_value" => $row['product_distributor_id'], "description" => $row['description'], "inactive" => false);
        }
        freeResult($resultSet);
        return $distributorChoices;
    }

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName === "set_link_name") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=" + actionName, function(returnArray) {
                        getDataList();
                    });
                    return true;
                }
                return false;
            }
        </script>
		<?php
	}

    function afterSaveDone() {
        removeCachedData("product_menu_page_module", "*");
    }
}

$pageObject = new ProductDepartmentMaintenancePage("product_departments");
$pageObject->displayPage();
