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

$GLOBALS['gPageCode'] = "PRODUCTCATEGORYGROUPMAINT";
require_once "shared/startup.inc";

class ProductCategoryGroupMaintenancePage extends Page {

	function setup() {
		$filters['no_link_name'] = array("form_label" => "No Link Name Set", "where" => "link_name is null", "data_type" => "tinyint");
		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_link_name", "Set Link Name to Description for selected rows");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "set_link_name":
				$returnArray = DataTable::setLinkNames("product_category_groups");
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("detailed_description", "wysiwyg", true);
		$this->iDataSource->addColumnControl("link_name", "classes", "url-link");
		$this->iDataSource->addColumnControl("fragment_id", "get_choices", "fragmentChoices");
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
        removeCachedData("product_menu_page_module", "*");
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
}

$pageObject = new ProductCategoryGroupMaintenancePage("product_category_groups");
$pageObject->displayPage();
