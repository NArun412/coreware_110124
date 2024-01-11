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

$GLOBALS['gPageCode'] = "PRODUCTFACETMAINT";
require_once "shared/startup.inc";

class ProductFacetMaintenancePage extends Page {

	function setup() {
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_exclude_details", "Exclude from Details for Selected Facets");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("remove_exclude_details", "Clear Exclude from Details for Selected Facets");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_exclude_sidebar", "Exclude from Sidebar for Selected Facets");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("remove_exclude_sidebar", "Clear Exclude from Sidebar for Selected Facets");
		$filters = array();
		$resultSet = executeQuery("select * from product_categories where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
		$categories = array();
		while ($row = getNextRow($resultSet)) {
			$categories[$row['product_category_id']] = $row['description'];
		}
		$filters['product_category'] = array("form_label" => "Product Category", "where" => "product_facets.product_facet_id in (select product_facet_id from product_facet_categories where product_category_id = %key_value%)", "data_type" => "select", "choices" => $categories);
		$resultSet = executeQuery("select * from product_category_groups where client_id = ? and inactive = 0 order by description", $GLOBALS['gClientId']);
		$categories = array();
		while ($row = getNextRow($resultSet)) {
			$categories[$row['product_category_group_id']] = $row['description'];
		}
		$filters['product_category_group'] = array("form_label" => "Product Category Group", "where" => "product_facets.product_facet_id in (select product_facet_id from product_facet_categories where " .
			"product_category_id in (select product_category_id from product_category_group_links where product_category_group_id = %key_value%))", "data_type" => "select", "choices" => $categories);
		$filters['not_excluded'] = array("form_label" => "Not Excluded from Sidebar", "where" => "exclude_reductive = 0", "data_type" => "tinyint");
		$filters['not_details'] = array("form_label" => "Not Excluded from Details", "where" => "exclude_details = 0", "data_type" => "tinyint");
		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("product_facet_values", "product_facet_options", "product_facet_categories"));
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "set_exclude_sidebar":
				$productFacetIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productFacetIds[] = $row['primary_identifier'];
				}
				$count = 0;
				if (!empty($productFacetIds)) {
					$resultSet = executeQuery("update product_facets set exclude_reductive = 1 where client_id = ? and product_facet_id in (" . implode(",", $productFacetIds) . ")", $GLOBALS['gClientId']);
					$count = $resultSet['affected_rows'];
				}
				$returnArray['info_message'] = $count . " product facets set to exclude from sidebar";
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,notes) values (?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'product_facets', 'exclude_reductive', $count . " product facets set to exclude reductive",(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
			case "remove_exclude_sidebar":
				$productFacetIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productFacetIds[] = $row['primary_identifier'];
				}
				$count = 0;
				if (!empty($productFacetIds)) {
					$resultSet = executeQuery("update product_facets set exclude_reductive = 0 where client_id = ? and product_facet_id in (" . implode(",", $productFacetIds) . ")", $GLOBALS['gClientId']);
					$count = $resultSet['affected_rows'];
				}
				$returnArray['info_message'] = $count . " product facets cleared from exclude from sidebar";
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,notes) values (?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'product_facets', 'exclude_reductive', $count . " product facets cleared exclude reductive",(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
			case "set_exclude_details":
				$productFacetIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productFacetIds[] = $row['primary_identifier'];
				}
				$count = 0;
				if (!empty($productFacetIds)) {
					$resultSet = executeQuery("update product_facets set exclude_details = 1 where client_id = ? and product_facet_id in (" . implode(",", $productFacetIds) . ")", $GLOBALS['gClientId']);
					$count = $resultSet['affected_rows'];
				}
				$returnArray['info_message'] = $count . " product facets set to exclude from details";
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,notes) values (?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'product_facets', 'exclude_details', $count . " product facets set to exclude details",(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
			case "remove_exclude_details":
				$productFacetIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productFacetIds[] = $row['primary_identifier'];
				}
				$count = 0;
				if (!empty($productFacetIds)) {
					$resultSet = executeQuery("update product_facets set exclude_details = 0 where client_id = ? and product_facet_id in (" . implode(",", $productFacetIds) . ")", $GLOBALS['gClientId']);
					$count = $resultSet['affected_rows'];
				}
				$returnArray['info_message'] = $count . " product facets cleared from exclude from details";
				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,notes) values (?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'product_facets', 'exclude_details', $count . " product facets cleared exclude details",(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
		}
	}

	function beforeDeleteRecord($primaryId) {
		$productFacetCode = getFieldFromId("product_facet_code", "product_facets", "product_facet_id", $primaryId);
		executeQuery("delete from product_distributor_conversions where table_name = 'product_facet_options' and original_value_qualifier = ?", $productFacetCode);
		executeQuery("delete from product_distributor_conversions where table_name = 'product_facets' and primary_identifier = ?", $primaryId);
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#_confirm_delete_dialog").find("dialog-text").html("Deleting this facet will remove ALL facet values for all products for this facet and cannot be undone. Are you sure?");
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function customActions(actionName) {
                if (actionName == "set_exclude_details" || actionName == "remove_exclude_details" || actionName == "set_exclude_sidebar" || actionName == "remove_exclude_sidebar") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=" + actionName, $("#_set_exclude_details_form").serialize(), function(returnArray) {
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

$pageObject = new ProductFacetMaintenancePage("product_facets");
$pageObject->displayPage();
