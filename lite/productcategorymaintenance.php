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

$GLOBALS['gPageCode'] = "PRODUCTCATEGORYMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		$filters = array();
		$filters['no_link_name'] = array("form_label" => "No Link Name Set", "where" => "link_name is null", "data_type" => "tinyint");
		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_link_name", "Set Link Name to Description for selected categories");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "set_link_name":
				$returnArray = DataTable::setLinkNames("product_categories");
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("product_category_links","product_category_restrictions","product_category_cannot_sell_distributors"));
		$this->iDataSource->addColumnControl("link_name", "classes", "url-link");

        $this->iDataSource->addColumnControl("product_category_restrictions", "control_class", "EditableList");
        $this->iDataSource->addColumnControl("product_category_restrictions", "data_type", "custom");
        $this->iDataSource->addColumnControl("product_category_restrictions", "form_label", "Restricted Locations");
        $this->iDataSource->addColumnControl("product_category_restrictions", "list_table", "product_category_restrictions");
        $this->iDataSource->addColumnControl("product_category_restrictions", "list_table_controls", array("state"=>array("data_type"=>"select","choices"=>getStateArray())));

		$this->iDataSource->addColumnControl("product_facet_categories", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_facet_categories", "control_table", "product_facets");
		$this->iDataSource->addColumnControl("product_facet_categories", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("product_facet_categories", "links_table", "product_facet_categories");

		$this->iDataSource->addColumnControl("product_category_group_links", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_category_group_links", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("product_category_group_links", "links_table", "product_category_group_links");
		$this->iDataSource->addColumnControl("product_category_group_links", "form_label", "Category Groups");
		$this->iDataSource->addColumnControl("product_category_group_links", "control_table", "product_category_groups");

        $this->iDataSource->addColumnControl("product_category_cannot_sell_distributors", "data_type", "custom");
        $this->iDataSource->addColumnControl("product_category_cannot_sell_distributors", "control_class", "MultipleSelect");
        $this->iDataSource->addColumnControl("product_category_cannot_sell_distributors", "links_table", "product_category_cannot_sell_distributors");
        $this->iDataSource->addColumnControl("product_category_cannot_sell_distributors", "form_label", "Cannot Sell from these Distributors");
        $this->iDataSource->addColumnControl("product_category_cannot_sell_distributors", "control_table", "product_distributors");
        $this->iDataSource->addColumnControl("product_category_cannot_sell_distributors", "get_choices", "locationChoices");
	}

    function locationChoices($showInactive = false) {
        $locationChoices = array();
        $resultSet = executeQuery("select * from locations where inactive = 0 and product_distributor_id is not null and client_id = ?" .
            (empty($GLOBALS['gUserRow']['administrator_flag']) ? " and ((user_location = 1 and user_id = " . $GLOBALS['gUserId'] . ") or (location_id in (select location_id from ffl_locations where federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id = " . $GLOBALS['gUserId'] . "))))" : " and user_location = 0") . " order by sort_order,description",
            $GLOBALS['gClientId']);
        while ($row = getNextRow($resultSet)) {
            $locationChoices[$row['location_id']] = array("key_value" => $row['location_id'], "description" => $row['description'], "inactive" => false);
        }
        freeResult($resultSet);
        return $locationChoices;
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
            }
        </script>
		<?php
	}

    function afterSaveDone() {
        removeCachedData("product_menu_page_module", "*");
    }
}

$pageObject = new ThisPage("product_categories");
$pageObject->displayPage();
