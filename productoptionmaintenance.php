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

$GLOBALS['gPageCode'] = "PRODUCTOPTIONMAINT";
require_once "shared/startup.inc";

class ProductOptionMaintenancePage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_facet_values":
				$valuesArray = array();
				$resultSet = executeQuery("select * from product_facet_options where product_facet_id = ? order by facet_value", $_GET['product_facet_id']);
				while ($row = getNextRow($resultSet)) {
					$thisArray = array();
					$thisArray['description'] = array("data_value" => $row['facet_value']);
					$thisArray['sort_order'] = array("data_value" => "100");
					$valuesArray[] = $thisArray;
				}
				$returnArray['facet_values'] = $valuesArray;
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("product_facet_id", "data_type", "select");
		$this->iDataSource->addColumnControl("product_facet_id", "form_label", "Facet");
		$this->iDataSource->addColumnControl("product_facet_id", "help_label", "Select a facet to import that facet's values");
		$this->iDataSource->addColumnControl("product_facet_id", "empty_text", "[Select to import]");
		$this->iDataSource->addColumnControl("product_facet_id", "choices", $GLOBALS['gPrimaryDatabase']->getControlRecords(array("table_name" => "product_facets")));

		$this->iDataSource->addColumnControl("product_option_choices", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_option_choices", "form_label", "Choices");
		$this->iDataSource->addColumnControl("product_option_choices", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("product_option_choices", "list_table", "product_option_choices");
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#product_facet_id").change(function () {
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_facet_values&product_facet_id=" + $(this).val(), function(returnArray) {
                        if ("facet_values" in returnArray) {
                            for (var i in returnArray['facet_values']) {
                                addEditableListRow("product_option_choices", returnArray['facet_values'][i]);
                            }
                        }
                    });
                }
            });
        </script>
		<?php
	}
}

$pageObject = new ProductOptionMaintenancePage("product_options");
$pageObject->displayPage();
