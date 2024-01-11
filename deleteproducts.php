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

$GLOBALS['gPageCode'] = "DELETEPRODUCTS";
$GLOBALS['gDefaultAjaxTimeout'] = 6000000;
require_once "shared/startup.inc";
exit;

class ThisPage extends Page {

	function mainContent() {
		if (!$GLOBALS['gUserRow']['superuser_flag']) {
			echo "<p>Unable to delete products</p>";
			return true;
		}
		$resultSet = executeQuery("select count(*) from orders where deleted = 0 and client_id = ?", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			if ($row['count(*)'] > 0) {
				echo "<p>" . $row['count(*)'] . " undeleted order" . ($row['count(*)'] == 1 ? "" : "s") . " exist in this client. Products cannot be deleted.</p>";
				return true;
			}
		}
		?>
        <p class='red-text'>This will DELETE all products on this client. This process CANNOT be undone. Proceed with caution. DO NOT CLOSE THIS WINDOW. This process can take up to 2 hours.</p>
        <p><input type='checkbox' id='confirm_delete' name='confirm_delete' value='1'><label class='checkbox-label' for='confirm_delete'>Confirm here that you understand the implications and want to delete all products from this client.</label></p>
        <button id='delete products'>Delete All Products</button>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#confirm_delete", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_products");
            });
        </script>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "delete_products":
				if (!$GLOBALS['gUserRow']['superuser_flag']) {
					$returnArray['error_message'] = "Unable to delete products";
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("select count(*) from orders where deleted = 0 and client_id = ?", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					if ($row['count(*)'] > 0) {
						$returnArray['error_message'] = $row['count(*)'] . " undeleted order" . ($row['count(*)'] == 1 ? "" : "s") . " exist in this client. Products cannot be deleted.";
						ajaxResponse($returnArray);
						break;
					}
				}
				$resultSet = executeQuery("select count(*) from distributor_orders where client_id = ?", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					if ($row['count(*)'] > 0) {
						$returnArray['error_message'] = "Products can't be deleted while there are distributor orders";
						ajaxResponse($returnArray);
						break;
					}
				}

				$productsTableId = getFieldFromId("table_id", "tables", "table_name", "products");
				$productIdColumnDefinitionId = getFieldFromId("column_definition_id", "column_definitions", "column_name", "product_id");
				$productIdTableColumnId = getFieldFromId("table_column_id", "table_columns", "table_id", $productsTableId, "column_definition_id = ?", $productIdColumnDefinitionId);
				$productSubTables = array();
				$resultSet = executeQuery("select table_column_id from foreign_keys where referenced_table_column_id = ?", $productIdTableColumnId);
				while ($row = getNextRow($resultSet)) {
					if ($tableInfo = $this->getTableInfo($row['table_column_id'])) {
						$tableObject = new DataTable($tableInfo['table_name']);
						if ($tableObject->columnExists("promotion_id")) {
							$checkSet = executeQuery("select count(*) from " . $tableInfo['table_name'] . " where promotion_id in (select promotion_id from promotions where client_id = ?)", $GLOBALS['gClientId']);
							if ($checkRow = getNextRow($checkSet)) {
								if ($checkRow['count(*)'] > 0) {
									$returnArray['error_message'] = "Promotions contain products, so products cannot be deleted. Delete the promotions first.";
									ajaxResponse($returnArray);
									break;
								}
							}
						}
						$productSubTables[] = $tableInfo;
					}
				}

				# mark images for later deletion
				$resultSet = executeQuery("update images set version = 1 where client_id = ?", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("update images set version = 94281 where client_id = ? and image_id in (select image_id from products)", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("update images set version = 94281 where client_id = ? and image_id in (select image_id from product_images)", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}

				# delete custom field data for products
				$resultSet = executeQuery("delete from custom_field_data where primary_identifier in (select product_id from products where client_id = ?) and custom_field_id in (select custom_field_id from custom_fields where custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'PRODUCTS'))", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}

				# delete product inventory log
				$resultSet = executeQuery("delete from product_inventory_log where product_inventory_id in (select product_inventory_id from product_inventories where product_id in (select product_id from products where client_id = ?))", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}

				# delete gift cards related to orders
				$resultSet = executeQuery("delete from gift_card_log where gift_card_id in (select gift_card_id from gift_cards where client_id = ?) and " .
					"(order_id is not null or gift_card_id in (select gift_card_id from gift_cards where client_id = ? and order_item_id is not null))", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from gift_cards where client_id = ? and order_item_id is not null", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}

				# delete orders
				$resultSet = executeQuery("delete from order_shipment_items where order_shipment_id in (select order_shipment_id from order_shipments where order_id in (select order_id from orders where client_id = ?))", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from order_shipments where order_id in (select order_id from orders where client_id = ?)", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from distributor_order_products where order_item_id in (select order_item_id from order_items where order_id in (select order_id from orders where client_id = ?))", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from order_item_serial_numbers where order_item_id in (select order_item_id from order_items where order_id in (select order_id from orders where client_id = ?))", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from order_items where order_id in (select order_id from orders where client_id = ?)", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from order_payments where order_id in (select order_id from orders where client_id = ?)", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from order_files where order_id in (select order_id from orders where client_id = ?)", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from order_notes where order_id in (select order_id from orders where client_id = ?)", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from product_inventory_log where order_id in (select order_id from orders where client_id = ?)", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from orders where client_id = ?", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}

				# delete shopping cart items and wish list items
				$resultSet = executeQuery("delete from shopping_cart_items where shopping_cart_id in (select shopping_cart_id from shopping_carts where client_id = ?)", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from wish_list_items where product_id in (select product_id from products where client_id = ?) or wish_list_id in (select wish_list_id from wish_lists where user_id in (select user_id from users where client_id = ?))", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}

				# delete product group variant subtables
				$resultSet = executeQuery("delete from product_group_variant_choices where product_group_variant_id in (select product_group_variant_id from product_group_variants where product_id in (select product_id from products where client_id = ?))", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}

				# delete product subtables
				foreach ($productSubTables as $tableInfo) {
					$resultSet = executeQuery("delete from " . $tableInfo . " where " . $tableInfo['column_name'] . " in (select product_id from products where client_id = ?)", $GLOBALS['gClientId']);
					if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						ajaxResponse($returnArray);
						break;
					}
				}

				# delete products
				$resultSet = executeQuery("delete from products where client_id = ?", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from image_data where image_id in (select image_id from images where version = 94281 and client_id = ?)", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from images where version = 94281 and client_id = ?", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}

				ajaxResponse($returnArray);
				break;
		}
	}

	private function getTableInfo($tableColumnId) {
		$resultSet = executeQuery("select *,(select table_name from tables where table_id = table_columns.table_id) as table_name, " .
			"(select column_name from column_definitions where column_definition_id = table_columns.column_definition_id) as column_name from table_columns where table_column_id = ?", $tableColumnId);
		if ($row = getNextRow($resultSet)) {
			return array("table_name" => $row['table_name'], "column_name" => $row['column_name']);
		}
		return false;
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
