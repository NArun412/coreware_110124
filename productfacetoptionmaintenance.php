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

$GLOBALS['gPageCode'] = "PRODUCTFACETOPTIONMAINT";
require_once "shared/startup.inc";

class ProductFacetOptionMaintenancePage extends Page {

	function setup() {
		$filters = array();

		$resultSet = executeQuery("select * from product_facets where client_id = ? order by description", $GLOBALS['gClientId']);
		$facets = array();
		while ($row = getNextRow($resultSet)) {
			$facets[$row['product_facet_id']] = $row['description'];
		}
		$filters['product_facet'] = array("form_label" => "Product Facet", "where" => "product_facet_id = %key_value%",
			"data_type" => "select", "choices" => $facets, "conjunction" => "and");
		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("remove_unused", "Remove Unused, Selected Values");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "remove_unused":
				$resultSet = executeQuery("delete from product_facet_options where product_facet_option_id in (select primary_identifier from selected_rows where page_id = ? and user_id = ?) and " .
					"product_facet_option_id not in (select product_facet_option_id from product_facet_values)", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				$returnArray['info_message'] = $resultSet['affected_rows'] . " product facet options deleted";
				ajaxResponse($returnArray);
				break;
		}
	}

	function supplementaryContent() {
		?>
		<div id='product_list'></div>
		<?php
	}

	function javascript() {
		?>
		<script>
            function customActions(actionName) {
                if (actionName === "remove_unused") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=" + actionName, function (returnArray) {
                        setTimeout(function() {
                            getDataList();
                        },2000);
                    });
                    return true;
                }
                return false;
            }
		</script>
		<?php
	}

	function massageDataSource() {
		if ($GLOBALS['gClientRow']['client_code'] == "COREWARE_SHOOTING_SPORTS") {
			$this->iDataSource->getPrimaryTable()->setSubtables(array("product_facet_values"));
		}
		$this->iDataSource->setFilterWhere("product_facet_id in (select product_facet_id from product_facets where client_id = " . $GLOBALS['gClientId'] . ")");
	}

	function afterGetRecord(&$returnArray) {
		$resultSet = executeQuery("select * from products join product_facet_values using (product_id) where product_facet_option_id = ?",
			$returnArray['primary_id']['data_value']);
		if ($resultSet['row_count'] > 0 && $resultSet['row_count'] < 50 && canAccessPageCode("PRODUCTMAINT")) {
			ob_start();
			?>
			<h2>Products</h2>
			<?php
			while ($row = getNextRow($resultSet)) {
				?>
				<p><a target='_blank' href='/productmaintenance.php?clear_filter=true&url_page=show&primary_id=<?= $row['product_id'] ?>'><?= htmlText($row['description']) ?></a></p>
				<?php
			}
			$returnArray['product_list'] = array("data_value" => ob_get_clean());
		} else {
			$returnArray['product_list'] = array("data_value" => $resultSet['row_count'] . " products use this value");
		}
	}
}

$pageObject = new ProductFacetOptionMaintenancePage("product_facet_options");
$pageObject->displayPage();
