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

$GLOBALS['gPageCode'] = "COSTDIFFERENCELOG";
require_once "shared/startup.inc";

class CostDifferenceLogPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setReadonly(true);
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("product_id", "product_distributor_id", "log_time", "original_base_cost", "base_cost", "percentage"));
			$this->iTemplateObject->getTableEditorObject()->setMaximumListColumns(6);
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("percentage", "select_value", "round(((original_base_cost - base_cost) * 100 / original_base_cost),1)");
		$this->iDataSource->addColumnControl("percentage", "form_label", "Percent Change");
		$this->iDataSource->addColumnControl("percentage", "data_type", "decimal");
		$this->iDataSource->addColumnControl("percentage", "decimal_places", "1");
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['percentage'] = array("data_value" => round((($returnArray['original_base_cost']['data_value'] - $returnArray['base_cost']['data_value']) * 100 / $returnArray['original_base_cost']['data_value']), 1));
	}

	function onLoadJavascript() {
		?>
        <script>
			<?php if (canAccessPageCode("PRODUCTMAINT")) { ?>
            $(document).on("click", "#product_id_autocomplete_text", function () {
                window.open("/productmaintenance.php?clear_filter=true&url_page=show&primary_id=" + $("#product_id").val());
            });
			<?php } ?>
        </script>
		<?php
	}

}

$pageObject = new CostDifferenceLogPage("cost_difference_log");
$pageObject->displayPage();
