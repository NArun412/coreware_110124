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

$GLOBALS['gPageCode'] = "DISTRIBUTORORDERMAINT";
require_once "shared/startup.inc";

class DistributorOrderMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$filters = array();
			$filters['hide_completed'] = array("form_label" => "Hide Completed", "where" => "date_completed is null", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);
			$filters['show_requires_attention'] = array("form_label" => "Show Requires Attention", "where" => "requires_attention = 1", "data_type" => "tinyint", "conjunction" => "and");
			$filters['show_not_ordered'] = array("form_label" => "Show Not Ordered", "where" => "order_time is null", "data_type" => "tinyint", "conjunction" => "and");

			$resultSet = executeQuery("select * from distributor_order_statuses where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['distributor_order_status_id_' . $row['distributor_order_status_id']] = array("form_label" => $row['description'], "where" => "distributor_order_status_id = " . $row['distributor_order_status_id'], "data_type" => "tinyint");
			}

			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "add"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("location_id", "readonly", true);
		$this->iDataSource->addColumnControl("order_number", "readonly", true);
		$this->iDataSource->addColumnControl("user_id", "readonly", true);
		$this->iDataSource->addColumnLikeColumn("add_location_id", "product_inventories", "location_id");
		$this->iDataSource->addColumnControl("add_location_id", "get_choices", "locationChoices");
		$this->iDataSource->addColumnControl("add_location_id", "data-conditional-required", "$(\".add-to-inventory:checked\").length > 0");
		if (empty($GLOBALS['gUserRow']['administrator_flag'])) {
			$this->iDataSource->setFilterWhere("user_id = " . $GLOBALS['gUserId']);
		}
	}

	function locationChoices($showInactive = false) {
		$locationChoices = array();
		$resultSet = executeQuery("select * from locations where " . (empty($GLOBALS['gUserRow']['administrator_flag']) ? "user_location = 1 and user_id = " . $GLOBALS['gUserId'] : "user_location = 0") .
			" and inactive = 0 and product_distributor_id is null and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$locationChoices[$row['location_id']] = array("key_value" => $row['location_id'], "description" => $row['description'], "inactive" => false);
		}
		freeResult($resultSet);
		return $locationChoices;
	}

	function onloadJavascript() {
		?>
        <script>
            $(document).on("click", ".add-to-inventory", function () {
                if (empty($(this).closest("tr").find(".date-received").val()) && $(this).prop("checked")) {
                    $(this).closest("tr").find(".date-received").val($.formatDate(new Date(), "MM/dd/yyyy"));
                }
            });
            $(document).on("click","#_received_header", function () {
                $(".date-received").each(function () {
                    if (empty($(this).val())) {
                        $(this).val("<?= date("m/d/Y") ?>");
                    }
                });
            });
            $(document).on("click","#_add_inventory_header", function () {
                $(".add-to-inventory").prop("checked", true);
            });
            $("#split_charges").on("click", function () {
                if (empty($("#misc_charges").val()) || isNaN($("#misc_charges").val())) {
                    return false;
                }
                let totalCost = 0;
                $(".cost").each(function() {
                    const thisCost = $(this).val();
                    if (!empty(thisCost) && !isNaN(thisCost)) {
                        const quantity = $(this).closest(".order-item-row").find(".order-quantity").html();
                        totalCost += parseFloat(quantity * thisCost);
                    }
                });
                const otherCharges = parseFloat($("#misc_charges").val());
                let availableCharges = otherCharges;
                $(".cost").each(function() {
                    const thisCost = $(this).val();
                    if (!empty(thisCost) && !isNaN(thisCost)) {
                        let thisCharge = (thisCost / totalCost) * otherCharges;
                        availableCharges = availableCharges - thisCharge;
                        if (availableCharges < .05) {
                            thisCharge = parseFloat(thisCharge) + parseFloat(availableCharges);
                            availableCharges = 0;
                        }
                        $(this).val(RoundFixed(parseFloat(thisCost) + parseFloat(thisCharge),2,true));
                    }
                });
                $(".cost").first().trigger("change");
                $("#misc_changes").val("");
                return false;
            });
            $(document).on("change", ".cost", function () {
                calculateTotal();
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function calculateTotal() {
                let totalCost = 0;
                $(".cost").each(function() {
                    const thisCost = $(this).val();
                    if (!empty(thisCost) && !isNaN(thisCost)) {
                        const quantity = $(this).closest(".order-item-row").find(".order-quantity").html();
                        totalCost += parseFloat(quantity * thisCost);
                    }
                });
                $("#total_charges").val(RoundFixed(totalCost,2));
            }

            function afterGetRecord() {
                calculateTotal();
            }
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		ob_start();
		?>
        <table class="grid-table">
            <tr>
                <th>Product</th>
                <th>Notes</th>
                <th>Qty</th>
                <th id="_received_header">Received</th>
                <th id="_add_inventory_header" class="align-center">Add To<br>Inventory</th>
                <th>Cost Each</th>
                <th>Delete</th>
            </tr>
			<?php
			$resultSet = executeQuery("select * from distributor_order_items where deleted = 0 and distributor_order_id = ?", $returnArray['primary_id']['data_value']);
			while ($row = getNextRow($resultSet)) {
				$cost = ProductCatalog::getLocationBaseCost($row['product_id'], $returnArray['location_id']['data_value']);
				?>
                <tr class='order-item-row' data-product_id="<?= $row['product_id'] ?>" id="order_item_row_<?= $row['product_id'] ?>">
                    <td><input type='hidden' name='distributor_order_item_id_<?= $row['distributor_order_item_id'] ?>' value="<?= $row['distributor_order_item_id'] ?>"><?= htmlText(getFieldFromId("upc_code", "product_data", "product_id", $row['product_id']) . " - " . getFieldFromId("product_code", "products", "product_id", $row['product_id']) . " - " . getFieldFromId("description", "products", "product_id", $row['product_id'])) ?></td>
                    <td><?= $row['notes'] ?></td>
                    <td class="align-right order-quantity"><?= $row['quantity'] ?></td>
                    <td><input type="text" size="10" class="validate[custom[date]] date-received"<?= (empty($row['date_received']) ? "" : " readonly='readonly'") ?> id="date_received_<?= $row['distributor_order_item_id'] ?>" name="date_received_<?= $row['distributor_order_item_id'] ?>" value="<?= (empty($row['date_received']) ? "" : date("m/d/Y", strtotime($row['date_received']))) ?>" data-crc_value="<?= getCrcValue($row['date_received']) ?>"></td>
                    <td class="align-center"><?php if (empty($row['date_received'])) { ?><input type="checkbox" value="1" class="add-to-inventory" id="add_to_inventory_<?= $row['distributor_order_item_id'] ?>" name="add_to_inventory_<?= $row['distributor_order_item_id'] ?>" data-crc_value="<?= getCrcValue("0") ?>"><?php } ?></td>
                    <td><?php if (empty($row['date_received'])) { ?><input type="text" size="10" class="align-right validate[custom[number]] cost" id="cost_<?= $row['distributor_order_item_id'] ?>" name="cost_<?= $row['distributor_order_item_id'] ?>" value="<?= $cost ?>" data-crc_value="<?= getCrcValue($cost) ?>" data-decimal-places="2" data-conditional-required="$('#add_to_inventory_<?= $row['distributor_order_item_id'] ?>').prop('checked')"><?php } ?></td>
                    <td class="align-center"><?php if (empty($row['date_received'])) { ?><input type="checkbox" value="1" class="deleted" id="deleted_<?= $row['distributor_order_item_id'] ?>" name="deleted_<?= $row['distributor_order_item_id'] ?>" data-crc_value="<?= getCrcValue("0") ?>"><?php } ?></td>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		$returnArray['distributor_order_items'] = array("data_value" => ob_get_clean());
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		foreach ($nameValues as $fieldName => $fieldData) {
			if (!startsWith($fieldName,"distributor_order_item_id_")) {
				continue;
			}
			$distributorOrderItemId = substr($fieldName, strlen("distributor_order_item_id_"));
			$dataTable = new DataTable("distributor_order_items");
			$dataTable->setSaveOnlyPresent(true);
			if (!empty($nameValues['deleted_' . $distributorOrderItemId])) {
				if (!$dataTable->saveRecord(array("name_values" => array("deleted" => "1"), "primary_id" => $distributorOrderItemId))) {
					return $dataTable->getErrorMessage();
				}
				continue;
			}
			$distributorOrderItemRow = getRowFromId("distributor_order_items", "distributor_order_item_id", $distributorOrderItemId);
			if (!empty($nameValues['add_to_inventory_' . $distributorOrderItemId])) {
				if (empty($nameValues['date_created_' . $distributorOrderItemId])) {
					$nameValues['date_created_' . $distributorOrderItemId] = date("m/d/Y");
				}
				ProductCatalog::getInventoryAdjustmentTypes();

				if (empty($nameValues['cost_' . $distributorOrderItemId]) || empty($distributorOrderItemRow['quantity'])) {
					$totalCost = "";
				} else {
					$totalCost = $distributorOrderItemRow['quantity'] * $nameValues['cost_' . $distributorOrderItemId];
				}
				$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $distributorOrderItemRow['product_id'], "location_id = ?", $nameValues['add_location_id']);
				if (empty($productInventoryId)) {
					$insertSet = executeQuery("insert into product_inventories (product_id,location_id,quantity) values (?,?,?)", $distributorOrderItemRow['product_id'], $nameValues['add_location_id'], $distributorOrderItemRow['quantity']);
					$productInventoryId = $insertSet['insert_id'];
					executeQuery("insert into product_inventory_log (product_inventory_id,inventory_adjustment_type_id,user_id,log_time,quantity,total_cost,notes) values " .
						"(?,?,?,now(),?,?,'Distributor order')", $productInventoryId, $GLOBALS['gInventoryAdjustmentTypeId'], $GLOBALS['gUserId'], $distributorOrderItemRow['quantity'], $totalCost);
				} else {
					executeQuery("insert into product_inventory_log (product_inventory_id,inventory_adjustment_type_id,user_id,log_time,quantity,total_cost,notes) values " .
						"(?,?,?,now(),?,?,'Distributor order')", $productInventoryId, $GLOBALS['gRestockAdjustmentTypeId'], $GLOBALS['gUserId'], $distributorOrderItemRow['quantity'], $totalCost);
					executeQuery("update product_inventories set quantity = quantity + " . $distributorOrderItemRow['quantity'] . " where product_inventory_id = ?", $productInventoryId);
				}
			}
			if (!empty($nameValues['date_received_' . $distributorOrderItemId])) {
				if (!$dataTable->saveRecord(array("name_values" => array("date_received" => $nameValues['date_received_' . $distributorOrderItemId]), "primary_id" => $distributorOrderItemId))) {
					return $dataTable->getErrorMessage();
				}
			}
		}
		return true;
	}

	function internalCSS() {
		?>
        <style>
            <?php
			$resultSet = executeQuery("select * from distributor_order_statuses where display_color is not null and client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$rgb = hex2rgb($row['display_color']);
				?>
            .order-status-<?= $row['distributor_order_status_id'] ?> {
                background-color: rgb(<?= $rgb[0] ?>,<?= $rgb[1] ?>,<?= $rgb[2] ?>) !important;
            }
            <?php
		}
		?>
            #_received_header, #_add_inventory_header {
                cursor: pointer;
            }
            #distributor_order_items {
                margin-bottom: 20px;
            }
        </style>
		<?php
	}

	function getListRowClasses($columnRow) {
		if (!empty($columnRow['distributor_order_status_id'])) {
			return "order-status-" . $columnRow['distributor_order_status_id'];
		}
		return "";
	}

}

$pageObject = new DistributorOrderMaintenancePage("distributor_orders");
$pageObject->displayPage();
