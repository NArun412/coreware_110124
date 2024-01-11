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
		$filters['not_in_groups'] = array("form_label" => "Not in a category group", "where" => "product_category_id not in (select product_category_id from product_category_group_links)", "data_type" => "tinyint");
		$filters['no_link_name'] = array("form_label" => "No Link Name Set", "where" => "link_name is null", "data_type" => "tinyint");
		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("cannot_sell", "Set selected categories as Cannot Sell");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("remove_cannot_sell", "Remove Cannot Sell from selected categories");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_link_name", "Set Link Name to Description for selected categories");
		if ($GLOBALS['gPermissionLevel'] > _READONLY) {
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("icon" => "fad fa-copy", "label" => getLanguageText("Duplicate"), "disabled" => false)));
		}

	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_taxjar_categories":
				$taxjarCategories = getCachedData("taxjar_categories", "", true);
				if (!empty($taxjarCategories)) {
					$returnArray['taxjar_categories'] = $taxjarCategories;
					ajaxResponse($returnArray);
					break;
				}

				$taxjarApiToken = getPreference("taxjar_api_token");
				if (empty($taxjarApiToken)) {
					$returnArray['error_message'] = "TaxJar not configured";
					ajaxResponse($returnArray);
					break;
				}

				$client = false;
				require_once __DIR__ . '/taxjar/vendor/autoload.php';
				try {
					$client = TaxJar\Client::withApiKey($taxjarApiToken);
					$client->setApiConfig('headers', ['x-api-version' => '2022-01-24']);
				} catch (Exception $e) {
					$returnArray['error_message'] = "TaxJar not configured";
					ajaxResponse($returnArray);
					break;
				}
				if (!$client) {
					$returnArray['error_message'] = "TaxJar not configured";
					ajaxResponse($returnArray);
					break;
				}

				ob_start();
				$categories = array();
				try {
					$categories = $client->categories();
				} catch (Exception $e) {
				}

				?>
                <table id='taxjar_category_table' class='grid-table'>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Tax Code</th>
                    </tr>
					<?php
					foreach ((array)$categories as $thisCategory) {
						?>
                        <tr class='taxjar-category' data-taxjar_product_tax_code="<?= $thisCategory->product_tax_code ?>">
                            <td><?= $thisCategory->name ?></td>
                            <td><?= $thisCategory->description ?></td>
                            <td><?= $thisCategory->product_tax_code ?></td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
				$returnArray['taxjar_category_list'] = ob_get_clean();
				setCachedData("taxjar_categories", "", $returnArray['taxjar_categories'], 240, true);

				ajaxResponse($returnArray);

				break;
			case "cannot_sell":
				$productCategoryIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productCategoryIds[] = $row['primary_identifier'];
				}
				$count = 0;
				if (!empty($productCategoryIds)) {
					$resultSet = executeQuery("update product_categories set cannot_sell = 1 where product_category_id in (" . implode(",", $productCategoryIds) . ")");
					$count = $resultSet['affected_rows'];
				}
				$returnArray['info_message'] = $count . " categories set as Cannot Sell";
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,notes) values (?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'product_categories', 'cannot_sell', $count . " categories set",(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
			case "remove_cannot_sell":
				$productCategoryIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productCategoryIds[] = $row['primary_identifier'];
				}
				$count = 0;
				if (!empty($productCategoryIds)) {
					$resultSet = executeQuery("update product_categories set cannot_sell = 0 where product_category_id in (" . implode(",", $productCategoryIds) . ")");
					$count = $resultSet['affected_rows'];
				}
				$returnArray['info_message'] = $count . " categories set as Cannot Sell";
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,notes) values (?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'product_categories', 'cannot_sell', $count . " categories removed",(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
			case "set_link_name":
				$returnArray = DataTable::setLinkNames("product_categories");
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array('auction_category_fees',
			'auction_item_product_category_links',
			'auction_specification_product_categories',
			'ffl_category_restrictions',
			'gunbroker_product_categories',
			'pricing_structure_category_quantity_discounts',
			'product_category_addons',
			'product_category_cannot_sell_distributors',
			'product_category_departments',
			'product_category_group_links',
			'product_category_restrictions',
			'product_category_shipping_carriers',
			'product_category_shipping_methods',
			'product_facet_categories',
			'promotion_purchased_product_categories',
			'promotion_rewards_excluded_product_categories',
			'promotion_rewards_product_categories',
			'promotion_terms_excluded_product_categories',
			'promotion_terms_product_categories',
			'search_term_synonym_product_categories',
			'shipping_charge_product_categories',
			'source_product_categories'));

		$this->iDataSource->addColumnControl("product_category_restrictions", "list_table_controls", array("state"=>array("data_type"=>"select","choices"=>getStateArray())));
		$this->iDataSource->addColumnControl("remove_products", "data_type", "tinyint");
		$this->iDataSource->addColumnControl("remove_products", "form_label", "Remove all products from this category - CANNOT be undone");
		$this->iDataSource->addColumnControl("product_count", "data_type", "int");
		$this->iDataSource->addColumnControl("product_count", "form_label", "Count of products in this category");
		$this->iDataSource->addColumnControl("product_count", "select_value", "select count(*) from product_category_links where product_category_id = product_categories.product_category_id");

		$this->iDataSource->addColumnControl("product_facet_categories", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_facet_categories", "control_table", "product_facets");
		$this->iDataSource->addColumnControl("product_facet_categories", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("product_facet_categories", "links_table", "product_facet_categories");

		$this->iDataSource->addColumnControl("product_category_shipping_methods", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_category_shipping_methods", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("product_category_shipping_methods", "links_table", "product_category_shipping_methods");
		$this->iDataSource->addColumnControl("product_category_shipping_methods", "form_label", "Prohibited Shipping Methods");
		$this->iDataSource->addColumnControl("product_category_shipping_methods", "control_table", "shipping_methods");

		$taxjarApiToken = getPreference("taxjar_api_token");
		if (!empty($taxjarApiToken)) {
			$this->iDataSource->addColumnControl("product_tax_code", "help_label", "Click <a href='#' id='taxjar_categories'>here</a> for a list of categories.");
		}

		$this->iDataSource->addColumnControl("user_group_id", "help_label", "Only users in this group can purchase these products");

		$this->iDataSource->addColumnControl("product_category_shipping_carriers", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_category_shipping_carriers", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("product_category_shipping_carriers", "links_table", "product_category_shipping_carriers");
		$this->iDataSource->addColumnControl("product_category_shipping_carriers", "form_label", "Prohibited Shipping Carriers");
		$this->iDataSource->addColumnControl("product_category_shipping_carriers", "control_table", "shipping_carriers");

		$this->iDataSource->addColumnControl("product_category_addons", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_category_addons", "control_class", "FormList");
		$this->iDataSource->addColumnControl("product_category_addons", "list_table", "product_category_addons");
		$this->iDataSource->addColumnControl("product_category_addons", "no_delete", true);
		$this->iDataSource->addColumnControl("product_category_addons", "list_table_controls",
			array("description" => array("not_editable" => true), "group_description" => array("not_editable" => true),"sale_price" => array("inline-width" => "100px"), "sort_order" => array("inline-width" => "60px"),
                "maximum_quantity" => array("form_label" => "Maximum Quantity", "minimum_value" => "1"), "image_id" => array("data_type" => "image_input", "no_remove" => true),
                "form_definition_id"=>array("help_label"=>"Form used to generate the addon"), "inventory_product_id" => array("data_type"=>"autocomplete", "data-autocomplete_tag" => "products")));

		$this->iDataSource->addColumnControl("product_category_cannot_sell_distributors", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_category_cannot_sell_distributors", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("product_category_cannot_sell_distributors", "links_table", "product_category_cannot_sell_distributors");
		$this->iDataSource->addColumnControl("product_category_cannot_sell_distributors", "form_label", "Cannot Sell from these Distributors");
		$this->iDataSource->addColumnControl("product_category_cannot_sell_distributors", "control_table", "product_distributors");

		$this->iDataSource->addColumnControl("points_multiplier", "minimum_value", "0");
		if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
			$originalId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $_GET['primary_id'], "client_id is not null");
			if (empty($originalId)) {
				return;
			}
			$resultSet = executeQuery("select * from product_categories where product_category_id = ?", $originalId);
			$newRow = getNextRow($resultSet);
			$originalCode = $newRow['product_category_code'];
			$originalLinkName = $newRow['link_name'];
			$subNumber = 1;
			$queryString = "";
			foreach ($newRow as $fieldName => $fieldData) {
				if (empty($queryString)) {
					$newRow[$fieldName] = "";
				}
				if ($fieldName == "client_id") {
					$newRow[$fieldName] = $GLOBALS['gClientId'];
				}
				$queryString .= (empty($queryString) ? "" : ",") . "?";
			}
			$newId = "";
			$newRow['description'] .= " Copy";
			while (empty($newId)) {
				$newRow['product_category_code'] = $originalCode . "_" . $subNumber;
				$newRow['link_name'] = $originalLinkName . "_" . $subNumber;
				$resultSet = executeQuery("select * from product_categories where product_category_code = ? and client_id = ?",
					$newRow['product_category_code'], $GLOBALS['gClientId']);
				if (getNextRow($resultSet)) {
					$subNumber++;
					continue;
				}
				$resultSet = executeQuery("insert into product_categories values (" . $queryString . ")", $newRow);
				if ($resultSet['sql_error_number'] == 1062) {
					$subNumber++;
					continue;
				}
				$newId = $resultSet['insert_id'];
			}
			$_GET['primary_id'] = $newId;
			$subTables = array('auction_category_fees',
				'auction_specification_product_categories',
				'ffl_category_restrictions',
				'postal_code_tax_rates',
				'pricing_structure_category_quantity_discounts',
				'product_category_addons',
				'product_category_cannot_sell_distributors',
				'product_category_departments',
				'product_category_group_links',
				'product_category_restrictions',
				'product_category_shipping_carriers',
				'product_category_shipping_methods',
				'product_facet_categories',
				'shipping_charge_product_categories',
				'state_tax_rates');
			foreach ($subTables as $tableName) {
				$resultSet = executeQuery("select * from " . $tableName . " where product_category_id = ?", $originalId);
				while ($row = getNextRow($resultSet)) {
					$queryString = "";
					foreach ($row as $fieldName => $fieldData) {
						if (empty($queryString)) {
							$row[$fieldName] = "";
						}
						$queryString .= (empty($queryString) ? "" : ",") . "?";
					}
					$row['product_category_id'] = $newId;
					executeQuery("insert into " . $tableName . " values (" . $queryString . ")", $row);
				}
			}
		}
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['remove_products'] = array("data_value" => "0");
		$returnArray['product_count'] = array("data_value" => "0");
		$resultSet = executeQuery("select count(distinct product_id) from product_category_links where product_category_id = ?", $returnArray['primary_id']['data_value']);
		if ($row = getNextRow($resultSet)) {
			$returnArray['product_count']['data_value'] = $row['count(distinct product_id)'];
		}
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if (!empty($nameValues['remove_products'])) {
			executeQuery("delete from product_category_links where product_category_id = ?", $nameValues['primary_id']);
		}
        removeCachedData("product_menu_page_module", "*");
        return true;
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
				<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                if (empty($("#primary_id").val())) {
                    disableButtons($("#_duplicate_button"));
                } else {
                    enableButtons($("#_duplicate_button"));
                }
				<?php } ?>
            }
            function customActions(actionName) {
                if (actionName === "cannot_sell" || actionName === "remove_cannot_sell" || actionName === "set_link_name") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=" + actionName, function(returnArray) {
                        getDataList();
                    });
                    return true;
                }
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".taxjar-category", function () {
                const taxCode = $(this).data("taxjar_product_tax_code");
                $("#_taxjar_categories_dialog").dialog('close');
                $("#product_tax_code").val(taxCode).focus();
            })
            $("#taxjar_category_filter").keyup(function (event) {
                const textFilter = $(this).val().toLowerCase();
                if (empty(textFilter)) {
                    $("td.taxjar-category").removeClass("hidden");
                } else {
                    $("tr.taxjar-category").each(function () {
                        const description = $(this).text().toLowerCase();
                        if (description.indexOf(textFilter) >= 0) {
                            $(this).removeClass("hidden");
                        } else {
                            $(this).addClass("hidden");
                        }
                    });
                }
            });
            $(document).on("click", "#taxjar_categories", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_taxjar_categories", function(returnArray) {
                    if ("taxjar_category_list" in returnArray) {
                        $("#taxjar_category_list").html(returnArray['taxjar_category_list']);
                        $('#_taxjar_categories_dialog').dialog({
                            closeOnEscape: true,
                            draggable: false,
                            modal: true,
                            resizable: false,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 1000,
                            title: 'TaxJar Categories',
                            buttons: {
                                Close: function (event) {
                                    $("#_taxjar_categories_dialog").dialog('close');
                                }
                            }
                        });
                    }
                });
            });
			<?php
			if ($GLOBALS['gPermissionLevel'] > _READONLY) {
			?>
            $(document).on("tap click", "#_duplicate_button", function () {
                const $primaryId = $("#primary_id");
                if (!empty($primaryId.val())) {
                    if (changesMade()) {
                        askAboutChanges(function () {
                            $('body').data('just_saved', 'true');
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $primaryId.val();
                        });
                    } else {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $primaryId.val();
                    }
                }
                return false;
            });
			<?php } ?>
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #taxjar_category_filter {
                width: 400px;
            }

            #taxjar_category_list {
                height: 600px;
            }

            .taxjar-category {
                cursor: pointer;

            &
            :hover {
                background-color: rgb(240, 240, 160)
            }

            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="_taxjar_categories_dialog" class="dialog-box">
            <p>Filter and click a category to select it.</p>
            <p><input type='text' id='taxjar_category_filter' placeholder='Filter Categories'></p>
            <div id="taxjar_category_list"></div>
        </div>
		<?php
	}
}

$pageObject = new ThisPage("product_categories");
$pageObject->displayPage();
