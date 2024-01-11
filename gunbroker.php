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

$GLOBALS['gPageCode'] = "GUNBROKER";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 600000;

class GunBrokerPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_orders":
				$productRow = getRowFromId("products", "product_id", $_GET['product_id']);
				$productDataRow = getRowFromId("product_data", "product_id", $_GET['product_id']);
				$resultSet = executeQuery("select * from orders join order_items using (order_id) where client_id = ? and source_id in (select source_id from sources where source_code = 'GUNBROKER') and product_id = ? order by order_id desc", $GLOBALS['gClientId'], $productRow['product_id']);
				if ($resultSet['row_count'] == 0) {
					$returnArray['error_message'] = "No orders found";
				} else {
					ob_start();
					?>
                    <p>Orders for <?= $productDataRow['upc_code'] ?>, <?= htmlText($productRow['description']) ?> with source "GUNBROKER".</p>
                    <table class='grid-table' id='order_list_table'>
                        <tr>
                            <th>Order ID</th>
                            <th>Order Date</th>
                            <th>Customer Name</th>
                            <th>Quantity Ordered</th>
                            <th>Sale Price</th>
                            <th>Date Completed</th>
                        </tr>
						<?php
						while ($row = getNextRow($resultSet)) {
							?>
                            <tr class='order-row' data-order_id="<?= $row['order_id'] ?>">
                                <td><?= $row['order_id'] ?></td>
                                <td><?= date("m/d/Y", strtotime($row['order_time'])) ?></td>
                                <td><?= htmlText($row['full_name']) ?></td>
                                <td><?= $row['quantity'] ?></td>
                                <td><?= number_format($row['sale_price'], 2) ?></td>
                                <td><?= (empty($row['date_completed']) ? "" : date("m/d/Y",strtotime($row['date_completed']))) ?></td>
                            </tr>
							<?php
						}
						?>
                    </table>
					<?php
                    $returnArray['order_list'] = ob_get_clean();
				}
				ajaxResponse($returnArray);
				break;
			case "delete_products":
				$productIdList = array();
				$productIds = explode(",", $_GET['product_list']);
				foreach ($productIds as $productId) {
					$productId = getFieldFromId("product_id", "products", "product_id", $productId, "inactive = 0 and product_id in (select product_id from gunbroker_products)");
					if (!empty($productId)) {
						$productIdList[] = $productId;
					}
				}
				if (empty($productIdList)) {
					$returnArray['error_message'] = "No products to delete";
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from gunbroker_products where product_id in (" . implode(",", $productIdList) . ") and product_id in (select product_id from products where client_id = ?)", $GLOBALS['gClientId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
				}
				ajaxResponse($returnArray);
				break;
			case "send_products":
				$productIdList = array();
				$productIds = explode(",", $_GET['product_list']);
				foreach ($productIds as $productId) {
					$productId = getFieldFromId("product_id", "products", "product_id", $productId, "inactive = 0 and product_id in (select product_id from gunbroker_products)");
					if (!empty($productId)) {
						$productIdList[] = $productId;
					}
				}
				if (empty($productIdList)) {
					$returnArray['error_message'] = "No products to send";
					ajaxResponse($returnArray);
					break;
				}
				try {
					$gunBroker = new GunBroker();
					$results = $gunBroker->sendProducts($productIdList);
					if ($results === false) {
						$returnArray['error_message'] = $gunBroker->getErrorMessage();
						ajaxResponse($returnArray);
						break;
					}
					$returnArray['send_results'] = str_replace("\n", "<br>", $results['log']);
					foreach ($results['errors'] as $productId => $error) {
						$returnArray['send_results'] .= "<p>Product ID " . $productId . " failed: " . $error . "</p>";
					}
					if (!empty($returnArray['send_results'])) {
						$returnArray['send_results'] .= "<p><button id='clear_results'>Hide Results</button></p>";
					}
					$returnArray['success'] = $results['success'];
					$returnArray['gunbroker_identifiers'] = array();
					foreach ($results['success'] as $productId) {
						$returnArray['gunbroker_identifiers'][$productId] = getFieldFromId("gunbroker_identifier", "gunbroker_products", "product_id", $productId);
					}
				} catch (Exception $exception) {
					$returnArray['error_message'] = "Unable to connect successfully with GunBroker";
					ajaxResponse($returnArray);
					break;
				}
				ajaxResponse($returnArray);
				break;
			case "save_gunbroker_category":
				$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id']);
				CustomField::setCustomFieldData($productId, "GUNBROKER_CATEGORY", $_GET['category_id'], "PRODUCTS");
				ajaxResponse($returnArray);
				break;
			case "save_preference":
				$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", $_GET['preference_code'], "preference_code like 'GUNBROKER%'");
				if (empty($preferenceId)) {
					$returnArray['error_message'] = "Invalid Preference";
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $GLOBALS['gClientId'], $preferenceId);
				executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,?)", $GLOBALS['gClientId'], $preferenceId, $_GET['preference_value']);
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            <?php if (canAccessPageCode("ORDERDASHBOARD")) { ?>
            $(document).on("click", ".order-row", function() {
                const orderId = $(this).data("order_id");
                window.open("/orderdashboard.php?url_page=show&primary_id=" + orderId + "&clear_filter=true");
                return false;
            });
            <?php } ?>
            $(document).on("click", ".order-list", function () {
                const productId = $(this).closest("tr").data("product_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_orders&product_id=" + productId, function (returnArray) {
                    if ("order_list" in returnArray) {
                        $("#_order_list_dialog").html(returnArray['order_list']);
                        $("#_order_list_dialog").dialog({
                            closeOnEscape: true,
                            draggable: false,
                            modal: true,
                            resizable: false,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 600,
                            title: 'Product Order Listings',
                            buttons: {
                                Close: function (event) {
                                    $("#_order_list_dialog").dialog('close');
                                }
                            }
                        });
                    }
                });
                return false;
            });
            $(document).on("click", "#clear_results", function () {
                $("#send_results").html("");
            })
            $(document).on("click", "#button_panel button", function () {
                if (empty($("#preference_value_GUNBROKER_USERNAME").val()) || empty($("#preference_value_GUNBROKER_PASSWORD").val())) {
                    displayErrorMessage("All preferences must have a value.");
                    return;
                }
                const panelId = $(this).attr("id").replace("_button", "");
                $(".panel-section").addClass("hidden");
                $("#" + panelId).removeClass("hidden");
            });
            $("#text_filter").keyup(function (event) {
                const textFilter = $(this).val().toLowerCase();
                if (empty(textFilter)) {
                    $("#product_table tr.data-row").removeClass("hidden");
                } else {
                    $("#product_table tr.data-row").each(function () {
                        let hideRow = true;
                        const productId = $(this).find(".product-id").html().toLowerCase();
                        if (productId.indexOf(textFilter) >= 0) {
                            hideRow = false;
                        }
                        const description = $(this).find(".description").html().toLowerCase();
                        if (description.indexOf(textFilter) >= 0) {
                            hideRow = false;
                        }
                        const productCode = $(this).find(".product-code").html().toLowerCase();
                        if (productCode.indexOf(textFilter) >= 0) {
                            hideRow = false;
                        }
                        const upcCode = $(this).find(".upc-code").html().toLowerCase();
                        if (upcCode.indexOf(textFilter) >= 0) {
                            hideRow = false;
                        }
                        if (hideRow) {
                            $(this).addClass("hidden");
                        } else {
                            $(this).removeClass("hidden");
                        }
                    });
                }
            });
			<?php
			$resultSet = executeQuery("select * from preferences where preference_code like 'GUNBROKER%' and inactive = 0 and internal_use_only = 0" .
				" and client_setable = 1 order by sort_order,description");
			while ($row = getNextRow($resultSet)) {
			$thisValue = trim(getPreference($row['preference_code']));
			?>
            $("#preference_value_<?= $row['preference_code'] ?>").val("<?= $thisValue ?>");
			<?php
			}
			?>
            $(document).on("change", ".gunbroker-category", function () {
                const productId = $(this).closest("tr").data("product_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_gunbroker_category&product_id=" + productId + "&category_id=" + encodeURIComponent($(this).val()));
            });
            $(document).on("change", ".gunbroker-preference", function () {
                const preferenceCode = $(this).attr("id").replace("preference_value_", "");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_preference&preference_code=" + preferenceCode + "&preference_value=" + encodeURIComponent($(this).val()));
            });
            $(document).on("click", "#select_all", function () {
                $(".select-product").prop("checked", true);
            });
            $(document).on("click", "#unselect_all", function () {
                $(".select-product").prop("checked", false);
            });
            $(document).on("click", "#never_sent", function () {
                $(".select-product").prop("checked", false);
                $("#product_table tr.data-row").each(function () {
                    const dateSent = $(this).find(".date-sent").html();
                    if (empty(dateSent)) {
                        $(this).find(".select-product").prop("checked", true);
                    }
                });
            });
            $(document).on("click", "#hide_unselected", function () {
                $("#product_table tr.data-row").each(function () {
                    if (!$(this).find(".select-product").prop("checked")) {
                        $(this).addClass("hidden");
                    } else {
                        $(this).removeClass("hidden");
                    }
                });
            });
            $(document).on("click", "#show_all", function () {
                $("tr.data-row").removeClass("hidden");
            });
            $(document).on("click", ".checkbox-cell", function () {
                $(this).find("input[type=checkbox]").trigger("click");
            });
            $(document).on("click", ".checkbox-cell input", function (event) {
                event.stopPropagation();
            });
            $(document).on("click", "#send_products", function () {
                let productIdList = "";
                $("#product_table tr.data-row").each(function () {
                    if ($(this).find(".select-product").prop("checked")) {
                        productIdList += (empty(productIdList) ? "" : ",") + $(this).data("product_id");
                    }
                });
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=send_products&product_list=" + encodeURIComponent(productIdList), function (returnArray) {
                    if ("send_results" in returnArray) {
                        $("#send_results").html(returnArray['send_results']);
                    }
                    if ("success" in returnArray) {
                        for (var i in returnArray['success']) {
                            $("#data_row_" + returnArray['success'][i]).find(".date-sent").html("<?= date('m/d/Y') ?>");
                        }
                    }
                    if ("gunbroker_identifiers" in returnArray) {
                        for (var productId in returnArray['gunbroker_identifiers']) {
                            $("#gunbroker_identifier_" + productId).html(returnArray['gunbroker_identifiers'][productId]);
                        }
                    }
                });
            });
            $(document).on("click", "#delete_products", function () {
                let productIdList = "";
                $("#product_table tr.data-row").each(function () {
                    if ($(this).find(".select-product").prop("checked")) {
                        productIdList += (empty(productIdList) ? "" : ",") + $(this).data("product_id");
                    }
                });
                if (empty(productIdList)) {
                    displayErrorMessage("No products selected");
                } else {
                    $("#_confirm_delete_dialog").dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Delete Listings',
                        buttons: {
                            Delete: function (event) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_products&product_list=" + encodeURIComponent(productIdList), function (returnArray) {
                                    if (!("error_message" in returnArray)) {
                                        location.reload();
                                    }
                                });
                                $("#_confirm_delete_dialog").dialog('close');
                            },
                            Cancel: function (event) {
                                $("#_confirm_delete_dialog").dialog('close');
                            }
                        }
                    });
                }
            });
        </script>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];

		try {
			$gunBroker = new GunBroker();
			$itemUrl = $gunBroker->getItemUrl();
			$gunBrokerCategories = getCachedData("gunbroker_categories", "", true);
			if (empty($gunBrokerCategories)) {
				$gunBrokerCategories = $gunBroker->getCategories();
				if ($gunBrokerCategories === false) {
					echo "<p class='error-message'>Error: <?= $gunBroker->getErrorMessage() ?></p>";
					$gunBrokerCategories = array();
				} else {
					setCachedData("gunbroker_categories", "", $gunBrokerCategories, 24, true);
				}
			}
		} catch (Exception $exception) {
			echo "<p class='error-message'>Username and password are required to use GunBroker integration. </p>";
			return true;
		}

		?>
        <p class='error-message'></p>
        <div class="panel-section" id="products_panel">
            <h2>GunBroker Products</h2>
            <p>
                <button id='select_all'>Select All</button>
                <button id="unselect_all">UnSelect All</button>
                <button id="never_sent">Select Never Sent</button>
                <button id="hide_unselected">Hide Unselected</button>
                <button id="show_all">Show All</button>
                <button id="delete_products">Delete Selected Products</button>
                <button id="send_products">Send Selected Products</button>
            </p>
            <p><input tabindex="10" type="text" size="40" id="text_filter" name="text_filter" placeholder="Filter"></p>
            <div id="send_results"></div>
            <table id='product_table' class='grid-table'>
                <tr>
                    <th></th>
                    <th>ID</th>
                    <th>Product Code</th>
                    <th>UPC</th>
                    <th>Description</th>
                    <th>Last Changed</th>
                    <th>Last Sent</th>
                    <th>GunBroker ID</th>
                    <th>Orders</th>
                </tr>
				<?php

				$itemLink = '<a target="_blank" href="' . $itemUrl . '">%gunbroker_identifier%</a>';
				$resultSet = executeQuery("select * from products left outer join product_data using (product_id) join gunbroker_products using (product_id) where products.inactive = 0 and products.client_id = ? order by gunbroker_products.time_changed desc,gunbroker_products.description", $GLOBALS['gClientId']);
				if (canAccessPageCode("PRODUCTMAINT")) {
					$productLink = "/productmaintenance.php?url_page=show&primary_id=%s&clear_filter=true";
				} elseif (canAccessPageCode("PRODUCTMAINT_LITE")) {
					$productLink = "/product-maintenance?url_page=show&primary_id=%s&clear_filter=true";
				} else {
					$productLink = "#";
				}
				while ($row = getNextRow($resultSet)) {
					$thisItemLink = (empty($row['gunbroker_identifier']) ? "" : str_replace('%gunbroker_identifier%', $row['gunbroker_identifier'], $itemLink));
					?>
                    <tr id="data_row_<?= $row['product_id'] ?>" class='data-row' data-product_id="<?= $row['product_id'] ?>">
                        <td class='align-center checkbox-cell'><input id="checkbox_<?= $row['product_id'] ?>" type="checkbox" class="select-product"></td>
                        <td class='product-id'><a target="_blank" href="<?= sprintf($productLink, $row['product_id']) ?>"><?= $row['product_id'] ?></a></td>
                        <td class='product-code'><?= $row['product_code'] ?></td>
                        <td class='upc-code'><?= $row['upc_code'] ?></td>
                        <td class='description'><a href='/gunbrokerproductmaintenance.php?url_page=show&primary_id=<?= $row['gunbroker_product_id'] ?>' target="_blank"><?= htmlText($row['description']) ?></a></td>
                        <td class='time-changed'><?= (empty($row['time_changed']) ? "" : date("m/d/Y g:ia", strtotime($row['time_changed']))) ?></td>
                        <td class='date-sent'><?= (empty($row['date_sent']) ? "" : date("m/d/Y", strtotime($row['date_sent']))) ?></td>
                        <td class='gunbroker-identifier' id="gunbroker_identifier_<?= $row['product_id'] ?>"><?= $thisItemLink ?></td>
                        <td><span class='fad fa-list order-list'></span></td>
                    </tr>
					<?php
				}
				?>
            </table>
        </div>
		<?php
		return true;
	}

	function internalCSS() {
		?>
        <style>
            .order-row {
                cursor: pointer;
            }
            .order-list {
                cursor: pointer;
            }
            #product_table td {
                font-size: .7rem;
            }

            #product_table th {
                font-size: .7rem;
            }

            .panel-section {
                margin-top: 40px;
            }

            .panel-section button {
                font-size: .7rem;
            }

            #send_results p {
                color: rgb(192, 0, 0);
            }
        </style>
		<?php
	}

	function hiddenElement() {
		?>
        <div class='dialog-box' id="_order_list_dialog">
        </div>

        <div class='dialog-box' id="_confirm_delete_dialog">
            <p>Deleting products will NOT remove the products from GunBroker. It will only remove them from your listing of the products you have available to push to GunBroker. This cannot be undone. Are you sure?</p>
        </div>
		<?php
	}

}

$pageObject = new GunBrokerPage();
$pageObject->displayPage();
