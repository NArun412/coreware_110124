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

$GLOBALS['gPageCode'] = "PRODUCTINVENTORYMAINT";
require_once "shared/startup.inc";

class ProductInventoryMaintenancePage extends Page {
	function setup() {
		$filters = array();
		$filters['hide_inactive'] = array("form_label" => "Hide Inactive Products", "where" => "product_id not in (select product_id from products where inactive = 1)", "data_type" => "tinyint", "set_default" => true, "conjunction" => "and");
        $filters['in_stock_only'] = array("form_label" => "Hide Products with zero quantity", "where" => "quantity > 0", "data_type" => "tinyint", "set_default" => false, "conjunction" => "and");
		$resultSet = executeQuery("select * from locations where product_distributor_id is null and " . (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_id = " . $GLOBALS['gUserId'] . " and " : "") . "client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$filters['location_id_' . $row['location_id']] = array("form_label" => $row['description'], "where" => "location_id = " . $row['location_id'], "data_type" => "tinyint");
		}
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));
			$columns = array("product_id", "product_code", "upc_code", "manufacturer_sku", "location_id", "quantity", "reorder_level", "replenishment_level", "bin_number", "location_cost");
			$resultSet = executeQuery("select * from contributor_types where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$columns[] = "contributor_type_id_" . $row['contributor_type_id'];
			}
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn($columns);
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("product_id", "product_code", "upc_code", "manufacturer_sku", "location_id", "quantity", "reorder_level", "replenishment_level"));
		}
	}

	function massageDataSource() {
        $this->iDataSource->addColumnControl("quantity", "readonly", "true");

        $this->iDataSource->addColumnControl("maximum_price", "help_label", "Auto ordering will not happen for availability above this price. Leave blank to ignore.");
        $this->iDataSource->addColumnControl("manual_order", "form_label", "Do not auto order");

        $this->iDataSource->addColumnControl("upc_code", "readonly", "true");
		$this->iDataSource->addColumnControl("upc_code", "form_label", "UPC Code");
		$this->iDataSource->addColumnControl("upc_code", "data_type", "varchar");
        $this->iDataSource->addColumnControl("upc_code", "select_value", "select upc_code from product_data where product_id = product_inventories.product_id");

        $this->iDataSource->addColumnControl("isbn_13", "readonly", "true");
        $this->iDataSource->addColumnControl("isbn_13", "form_label", "ISBN 13");
        $this->iDataSource->addColumnControl("isbn_13", "data_type", "varchar");
        $this->iDataSource->addColumnControl("isbn_13", "select_value", "select isbn_13 from product_data where product_id = product_inventories.product_id");

        $this->iDataSource->addColumnControl("manufacturer_sku", "readonly", "true");
        $this->iDataSource->addColumnControl("manufacturer_sku", "form_label", "SKU");
        $this->iDataSource->addColumnControl("manufacturer_sku", "data_type", "varchar");
        $this->iDataSource->addColumnControl("manufacturer_sku", "select_value", "select manufacturer_sku from product_data where product_id = product_inventories.product_id");

        if (empty($GLOBALS['gUserRow']['administrator_flag'])) {
			$this->iDataSource->setFilterWhere("location_id in (select location_id from locations where product_distributor_id is null and user_id = " . $GLOBALS['gUserId'] . ") and product_id in (select product_id from products where client_id = " . $GLOBALS['gClientId'] . ")");
		} else {
			$this->iDataSource->setFilterWhere("location_id in (select location_id from locations where product_distributor_id is null) and product_id in (select product_id from products where client_id = " . $GLOBALS['gClientId'] . ")");
		}
		$this->iDataSource->addColumnControl("quantity", "readonly", "true");
		$this->iDataSource->addColumnControl("quantity", "form_label", "Current Inventory Quantity");
		$this->iDataSource->addColumnControl("product_id", "not_editable", "true");
		$this->iDataSource->addColumnControl("location_id", "not_editable", "true");
		$this->iDataSource->addColumnControl("location_id", "get_choices", "locationChoices");
		$this->iDataSource->addColumnControl("product_code", "select_value", "select product_code from products where product_id = product_inventories.product_id");
		$this->iDataSource->addColumnControl("product_code", "data_type", "varchar");
		$this->iDataSource->addColumnControl("product_code", "form_label", "Product Code");
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "product_data",
			"referenced_column_name" => "product_id", "foreign_key" => "product_id", "description" => "upc_code"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "products",
			"referenced_column_name" => "product_id", "foreign_key" => "product_id", "description" => "product_code"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "products",
			"referenced_column_name" => "product_id", "foreign_key" => "product_id", "description" => "description"));
		$resultSet = executeQuery("select * from contributor_types where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$this->iDataSource->addColumnControl("contributor_type_id_" . $row['contributor_type_id'], "data_type", "varchar");
			$this->iDataSource->addColumnControl("contributor_type_id_" . $row['contributor_type_id'], "form_label", $row['description']);
			$this->iDataSource->addColumnControl("contributor_type_id_" . $row['contributor_type_id'], "select_value", "select group_concat(full_name) from contributors join product_contributors using (contributor_id) where product_id = product_inventories.product_id and contributor_type_id = " . $row['contributor_type_id'] . " group by product_id");
		}
	}

	function locationChoices($showInactive = false) {
		$locationChoices = array();
		$resultSet = executeQuery("select * from locations where product_distributor_id is null" . (empty($GLOBALS['gUserRow']['administrator_flag']) ? " and user_location = 1 and user_id = " . $GLOBALS['gUserId'] : " and user_location = 0") .
			" and inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$locationChoices[$row['location_id']] = array("key_value" => $row['location_id'], "description" => $row['description'], "inactive" => false);
		}
		freeResult($resultSet);
		return $locationChoices;
	}

	function filterTextProcessing($filterText) {
		if (is_numeric($filterText) && strlen($filterText) >= 8) {
			$whereStatement = "(product_id in (select product_id from products where client_id = " . $GLOBALS['gClientId'] . " and product_code = " . makeParameter($filterText) .
				") or product_id = " . makeNumberParameter($filterText) . " or product_id in (select product_id from product_data where upc_code = '" . ProductCatalog::makeValidUPC($filterText) . "'" .
				") or product_id in (select product_id from product_data where isbn = '" . ProductCatalog::makeValidISBN($filterText) . "'" .
				") or product_id in (select product_id from product_data where isbn_13 = '" . ProductCatalog::makeValidISBN13($filterText) . "'))";
			$this->iDataSource->addFilterWhere($whereStatement);
		} else if (!empty($filterText)) {
		    $productId = getFieldFromId("product_id","products","description",$filterText);
		    if (empty($productId)) {
				$searchWordInfo = ProductCatalog::getSearchWords($filterText);
				$searchWords = $searchWordInfo['search_words'];
				$whereStatement = "";
				foreach ($searchWords as $thisWord) {
                    $productSearchWordId = getFieldFromId("product_search_word_id","product_search_words","search_term",$thisWord);
                    if (empty($productSearchWordId)) {
                        continue;
                    }
					$whereStatement .= (empty($whereStatement) ? "" : " and ") .
						"product_id in (select product_id from product_search_word_values where product_search_word_id = " . $productSearchWordId . ")";
				}
			    $whereStatement = "(product_id in (select product_id from products where product_code like " . makeParameter($filterText) . " or description like " . makeParameter($filterText . "%") . ")" . (empty($whereStatement) ? ")" : " or (" . $whereStatement . "))");
                $whereStatement = "(bin_number = " . makeParameter($filterText) . " or (" . $whereStatement . "))";
			    $whereStatement = "(" . (is_numeric($filterText) ? "product_id = " . makeParameter($filterText) . " or " : "") . "(" . $whereStatement . "))";
				$this->iDataSource->addFilterWhere($whereStatement);
			} else {
		        $this->iDataSource->addFilterWhere("product_id in (select product_id from products where description like " . makeParameter($filterText . "%") . ")");
            }
		}
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
                if (empty(returnArray['product_distributor_id']['data_value']) && !empty($("#primary_id").val())) {
                    $("#_reorder_level_row").removeClass("hidden");
                    $("#_replenishment_level_row").removeClass("hidden");
                } else {
                    $("#_reorder_level_row").addClass("hidden");
                    $("#_replenishment_level_row").addClass("hidden");
                }
            }
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['upc_code'] = array("data_value"=>getFieldFromId("upc_code","product_data","product_id",$returnArray['product_id']['data_value']));
		$returnArray['isbn_13'] = array("data_value"=>getFieldFromId("isbn_13","product_data","product_id",$returnArray['product_id']['data_value']));
		$returnArray['product_distributor_id'] = getFieldFromId("product_distributor_id", "locations", "location_id", $returnArray['location_id']['data_value']);
		$returnArray['inventory_adjustment_type_id'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray['log_quantity'] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
		$returnArray['log_notes'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$adjustmentTypes = array("S" => "Reduces Inventory", "A" => "Increases Inventory", "R" => "Sets Inventory");
		$resultSet = executeQuery("select * from product_inventory_log where product_inventory_id = ? order by log_time desc,product_inventory_log_id", $returnArray['primary_id']['data_value']);
		ob_start();
		?>
        <table class="grid-table">
            <tr>
                <th>Adjustment Type</th>
                <th>User</th>
                <th>Order ID</th>
                <th>Date</th>
                <th>Qty</th>
                <th>Total Cost</th>
                <th>Unit Cost</th>
                <th>Notes</th>
            </tr>
			<?php
			while ($row = getNextRow($resultSet)) {
				?>
                <tr>
                    <td><?= getFieldFromId("description", "inventory_adjustment_types", "inventory_adjustment_type_id", $row['inventory_adjustment_type_id']) . " (" .
						$adjustmentTypes[getFieldFromId("adjustment_type", "inventory_adjustment_types", "inventory_adjustment_type_id", $row['inventory_adjustment_type_id'])] . ")" ?></td>
                    <td><?= (empty($row['user_id']) ? "" : getUserDisplayName($row['user_id'])) ?></td>
                    <td><?= $row['order_id'] ?></td>
                    <td><?= date("m/d/Y g:ia", strtotime($row['log_time'])) ?></td>
                    <td class="align-right"><?= $row['quantity'] ?></td>
                    <td class="align-right"><?= (empty($row['quantity']) || empty($row['total_cost']) ? "" : number_format($row['total_cost'], 2, ".", ",")) ?></td>
                    <td class="align-right"><?= (empty($row['quantity']) ? "" : number_format(round($row['total_cost'] / $row['quantity'], 2), 2, ".", ",")) ?></td>
                    <td><?= $row['notes'] ?></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		$returnArray['product_inventory_log'] = array("data_value" => ob_get_clean());
	}

	function addInventoryLogEntry() {
		?>
        <div class="basic-form-line">
            <label for="inventory_adjustment_type_id">Inventory Adjustment Type</label>
            <select id="inventory_adjustment_type_id" name="inventory_adjustment_type_id" tabindex="10">
                <option value="">[Select]</option>
				<?php
				$resultSet = executeQuery("select * from inventory_adjustment_types where client_id = ? and inactive = 0",
					$GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
                    <option value="<?= $row['inventory_adjustment_type_id'] ?>"><?= htmlText($row['description']) ?></option>
					<?php
				}
				?>
            </select>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line">
            <label for="log_quantity">Quantity</label>
            <input type="text" class="validate[custom[integer]] align-right" size="6" id="log_quantity"
                   name="log_quantity" tabindex="10">
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line">
            <label for="log_cost">Cost Per Item</label>
            <span class="help-label">Only used for Restock and Inventory. Will be used for calculating product cost.</span>
            <input type="text" class="validate[custom[number]] align-right" data-decimal-places="2" size="10"
                   id="log_cost" name="log_cost" tabindex="10">
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line">
            <label for="log_notes">Notes</label>
            <textarea id="log_notes" name="log_notes" tabindex="10"></textarea>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

		<?php
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if (!empty($_POST['inventory_adjustment_type_id']) && strlen($_POST['log_quantity']) > 0) {
			$adjustmentType = getFieldFromId("adjustment_type", "inventory_adjustment_types", "inventory_adjustment_type_id", $_POST['inventory_adjustment_type_id']);
			$totalCost = "";
			if (strlen($_POST['log_cost']) > 0 && $_POST['log_quantity'] > 0 && $adjustmentType != "S") {
				$totalCost = $_POST['log_cost'] * $_POST['log_quantity'];
			}
            if (empty($_POST['log_notes'])) {
	            $_POST['log_notes'] = "Manual update in product inventory maintenance";
            }
			executeQuery("insert into product_inventory_log (product_inventory_id,inventory_adjustment_type_id,user_id,log_time,quantity,total_cost,notes) values " .
				"(?,?,?,now(),?,?,?)", $nameValues['primary_id'], $_POST['inventory_adjustment_type_id'], $GLOBALS['gUserId'], $_POST['log_quantity'], $totalCost, $_POST['log_notes']);
			$quantity = getFieldFromId("quantity", "product_inventories", "product_inventory_id", $nameValues['primary_id']);
			switch ($adjustmentType) {
				case "A":
					$quantity += $_POST['log_quantity'];
					break;
				case "R":
					$quantity = $_POST['log_quantity'];
					break;
				case "S":
					$quantity -= $_POST['log_quantity'];
					break;
			}
			$quantity = max($quantity, 0);
			executeQuery("update product_inventories set quantity = ? where product_inventory_id = ?", $quantity, $nameValues['primary_id']);
			executeQuery("update product_inventories set on_order_quantity = null where quantity > replenishment_level and product_inventory_id = ?", $nameValues['primary_id']);

			removeCachedData("product_prices", $nameValues['product_id']);
			removeCachedData("base_cost", $nameValues['product_id']);
			removeCachedData("*", $nameValues['product_id']);
			removeCachedData("*", $nameValues['product_id']);
			ProductCatalog::updateAllProductLocationCosts($nameValues['product_id']);
			ProductCatalog::calculateProductCost($nameValues['product_id'],"Saved in Product Inventory Maintenance");

			if ($quantity > 0) {
                executeQuery("delete from product_category_links where product_id = ? and product_category_id in (select product_category_id from product_categories where product_category_code = 'DISCONTINUED')", $nameValues['product_id']);
            }
		}
		return true;
	}
}

$pageObject = new ProductInventoryMaintenancePage("product_inventories");
$pageObject->displayPage();
