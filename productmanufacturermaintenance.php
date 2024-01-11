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

$GLOBALS['gPageCode'] = "PRODUCTMANUFACTURERMAINT";
require_once "shared/startup.inc";

class ProductManufacturerMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("product_manufacturer_code", "description", "first_name", "last_name", "business_name", "city", "state", "postal_code", "email_address", "web_page", "link_name", "map_policy_id", "pricing_structure_id", "search_multiplier"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
		}
		$filters = array();

		$filters['tag_header'] = array("form_label" => "Tags", "data_type" => "header");
		$resultSet = executeQuery("select * from product_manufacturer_tags where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$filters['product_manufacturer_tag_' . $row['product_manufacturer_tag_id']] = array("form_label" => $row['description'],
				"where" => "product_manufacturers.product_manufacturer_id in (select product_manufacturer_id from product_manufacturer_tag_links where product_manufacturer_tag_id = " . $row['product_manufacturer_tag_id'] . ")",
				"data_type" => "tinyint");
		}

		$filters['no_logo'] = array("form_label" => "No Logo", "where" => "contacts.image_id is null", "data_type" => "tinyint");
		$filters['no_website'] = array("form_label" => "No Website", "where" => "web_page is null", "data_type" => "tinyint");
		$filters['has_map'] = array("form_label" => "Has MAP product", "where" => "product_manufacturer_id in (select product_manufacturer_id from products where product_manufacturer_id is not null and product_id in (select product_id from product_data where manufacturer_advertised_price is not null and manufacturer_advertised_price > 0))", "data_type" => "tinyint");
		$filters['no_link_name'] = array("form_label" => "No Link Name Set", "where" => "link_name is null", "data_type" => "tinyint");
		$discontinuedCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_code", "DISCONTINUED");
		$filters['no_products'] = array("form_label" => "No active products", "where" => "product_manufacturer_id not in "
			. "(select distinct product_manufacturer_id from products where product_manufacturer_id is not null and client_id = " . $GLOBALS['gClientId'] . " and inactive = 0 and custom_product = 0 and not_searchable = 0 "
			. "and not exists (select product_id from product_category_links where product_id = products.product_id and product_category_id = " . $discontinuedCategoryId . "))", "data_type" => "tinyint");
		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_map_policy", "Set MAP policy for selected Manufacturers");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_tag", "Set Tag for selected Manufacturers");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_link_name", "Set Link Name to Description for selected Manufacturers");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_inactive", "Mark selected Manufacturers inactive");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("clear_inactive", "Clear inactive flag for selected Manufacturers");
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("merge_selected", "Merge Selected Manufacturers");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "merge_selected":
				$selectedCount = 0;
				$primaryIdentifiers = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where user_id = ? and page_id = ? order by primary_identifier", $GLOBALS['gUserId'], $GLOBALS['gPageId']);
				while ($row = getNextRow($resultSet)) {
					$primaryIdentifiers[] = $row['primary_identifier'];
				}
				if (count($primaryIdentifiers) != 2) {
					$returnArray['error_message'] = "Exactly and only two manufacturers must be selected";
					ajaxResponse($returnArray);
					break;
				}

				$productManufacturerCode = getFieldFromId("product_manufacturer_code", "product_manufacturers", "product_manufacturer_id", $primaryIdentifiers[0]);
				$duplicateProductManufacturerCode = getFieldFromId("product_manufacturer_code", "product_manufacturers", "product_manufacturer_id", $primaryIdentifiers[1]);
				if (empty($productManufacturerCode) || empty($duplicateProductManufacturerCode)) {
					$returnArray['error_message'] = "Invalid Manufacturer";
					ajaxResponse($returnArray);
					break;
				}
				ProductCatalog::mergeManufacturers($duplicateProductManufacturerCode, $productManufacturerCode, false);
				executeQuery("delete from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $GLOBALS['gPageId']);
				$returnArray['info_message'] = "Manufacturers successfully merged";
				ajaxResponse($returnArray);
				break;
			case "set_map_policy":
				$productManufacturerIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productManufacturerIds[] = $row['primary_identifier'];
				}
				$mapPolicyId = getFieldFromId("map_policy_id", "map_policies", "map_policy_id", $_POST['map_policy_id']);
				$count = 0;
				$updatedManufacturerIds = array();
				if (!empty($productManufacturerIds) && !empty($mapPolicyId)) {
					foreach ($productManufacturerIds as $thisManufacturerId) {
						$resultSet = executeQuery("update product_manufacturers set map_policy_id = ? where product_manufacturer_id = ? and client_id = ?",
							$mapPolicyId, $thisManufacturerId, $GLOBALS['gClientId']);
						if ($resultSet['affected_rows'] > 0) {
							$updatedManufacturerIds[] = $thisManufacturerId;
							$count++;
						}
					}
				}
				$returnArray['info_message'] = $count . " product manufacturers MAP policy set";
				if ($count > 0) {
					executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,notes) values (?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
						'product_manufacturers', 'map_policy_id', $count . " manufacturers set MAP policy to " . $mapPolicyId,(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				}
				$count = 0;
				if (!empty($productManufacturerIds) && strlen($_POST['percentage']) > 0) {
					foreach ($productManufacturerIds as $thisManufacturerId) {
						$resultSet = executeQuery("update product_manufacturers set percentage = ? where product_manufacturer_id = ? and client_id = ?",
							$_POST['percentage'], $thisManufacturerId, $GLOBALS['gClientId']);
						if ($resultSet['affected_rows'] > 0) {
							if (!in_array($thisManufacturerId, $updatedManufacturerIds)) {
								$updatedManufacturerIds[] = $thisManufacturerId;
							}
							$count++;
						}
					}
				}
				$returnArray['info_message'] .= (empty($returnArray['info_message']) ? "" : ", ") . $count . " product manufacturers percentage above MAP set";
				if ($count > 0) {
					executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,notes) values (?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
						'product_manufacturers', 'percentage', $count . " manufacturers set percentage to " . $_POST['percentage'],(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				}
				if (!empty($updatedManufacturerIds)) {
					executeQuery("delete from product_sale_prices where product_id in (select product_id from products where product_manufacturer_id in ("
						. implode(",", $updatedManufacturerIds) . "))");
				}

				executeQuery("delete from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				ajaxResponse($returnArray);
				break;
			case "set_tag":
				$productManufacturerIds = array();
				$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $row['primary_identifier']);
					if (!empty($productManufacturerId)) {
						$productManufacturerIds[] = $productManufacturerId;
					}
				}
				$productManufacturerTagId = getFieldFromId("product_manufacturer_tag_id", "product_manufacturer_tags", "product_manufacturer_tag_id", $_POST['product_manufacturer_tag_id']);
				$count = 0;
				if (!empty($productManufacturerIds) && !empty($productManufacturerTagId)) {
					foreach ($productManufacturerIds as $productManufacturerId) {
						$resultSet = executeQuery("insert ignore into product_manufacturer_tag_links (product_manufacturer_id,product_manufacturer_tag_id) values (?,?)", $productManufacturerId, $productManufacturerTagId);
						$count += $resultSet['affected_rows'];
					}
				}
				$returnArray['info_message'] = $count . " product manufacturers added to tag";
				executeQuery("insert into change_log (client_id,user_id,table_name,column_name,new_value,notes) values (?,?,?,?,?,?)", $GLOBALS['gClientId'], $GLOBALS['gUserId'],
					'product_manufacturer_tag_links', 'product_manufacturer_tag_id', $count . " manufacturers set tag to " . $productManufacturerTagId,(empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id'])));
				ajaxResponse($returnArray);
				break;
			case "set_link_name":
				$returnArray = DataTable::setLinkNames("product_manufacturers");
				ajaxResponse($returnArray);
				break;
			case "set_inactive":
				$inactiveValue = true;
			case "clear_inactive":
				$returnArray = DataTable::setInactive("product_manufacturers", $inactiveValue);
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->setJoinTable("contacts", "contact_id", "contact_id");
		$this->iDataSource->setSaveOnlyPresent(true);
		$this->iDataSource->addColumnControl("product_distributor_id", "help_label", "Auto ordering is ONLY from this distributor");
		$this->iDataSource->addColumnControl("city_select", "data_type", "select");
		$this->iDataSource->addColumnControl("city_select", "form_label", "City");
		$this->iDataSource->addColumnControl("country_id", "default_value", "1000");
		$this->iDataSource->addColumnControl("date_created", "default_value", "return date(\"m/d/Y\")");
		$this->iDataSource->addColumnControl("state", "css-width", "60px");
		$this->iDataSource->addColumnControl("phone_numbers", "data_type", "custom");
		$this->iDataSource->addColumnControl("phone_numbers", "form_label", "Phone Numbers");
		$this->iDataSource->addColumnControl("phone_numbers", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("phone_numbers", "list_table", "phone_numbers");
		$this->iDataSource->addColumnControl("phone_numbers", "foreign_key_field", "contact_id");
		$this->iDataSource->addColumnControl("phone_numbers", "primary_key_field", "contact_id");

		$this->iDataSource->addColumnControl("product_manufacturer_map_holidays", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_manufacturer_map_holidays", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("product_manufacturer_map_holidays", "form_label", "MAP Holidays");
		$this->iDataSource->addColumnControl("product_manufacturer_map_holidays", "list_table", "product_manufacturer_map_holidays");

		$this->iDataSource->addColumnControl("image_id", "form_label", "Primary Image/Logo");

		$this->iDataSource->addColumnControl("product_manufacturer_images", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_manufacturer_images", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("product_manufacturer_images", "form_label", "Alternate Images");
		$this->iDataSource->addColumnControl("product_manufacturer_images", "list_table", "product_manufacturer_images");

		$this->iDataSource->addColumnControl("product_manufacturer_cannot_sell_distributors", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("product_manufacturer_cannot_sell_distributors", "control_table", "product_distributors");
		$this->iDataSource->addColumnControl("product_manufacturer_cannot_sell_distributors", "data_type", "custom_control");
		$this->iDataSource->addColumnControl("product_manufacturer_cannot_sell_distributors", "form_label", "Cannot sell from these distributors");
		$this->iDataSource->addColumnControl("product_manufacturer_cannot_sell_distributors", "links_table", "product_manufacturer_cannot_sell_distributors");

		$this->iDataSource->addColumnControl("product_manufacturer_tag_links", "data_type", "custom");
		$this->iDataSource->addColumnControl("product_manufacturer_tag_links", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("product_manufacturer_tag_links", "links_table", "product_manufacturer_tag_links");
		$this->iDataSource->addColumnControl("product_manufacturer_tag_links", "form_label", "Tags");
		$this->iDataSource->addColumnControl("product_manufacturer_tag_links", "control_table", "product_manufacturer_tags");
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#postal_code").blur(function () {
                if ($("#country_id").val() == "1000") {
                    validatePostalCode();
                }
            });
            $("#country_id").change(function () {
                $("#city").add("#state").prop("readonly", $("#country_id").val() == "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() == "1000" ? "9999" : "10"));
                $("#_city_row").show();
                $("#_city_select_row").hide();
                if ($("#country_id").val() == "1000") {
                    validatePostalCode();
                }
            });
            $("#city_select").change(function () {
                $("#city").val($(this).val());
                $("#state").val($(this).find("option:selected").data("state"));
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
                $("#city").add("#state").prop("readonly", $("#country_id").val() == "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() == "1000" ? "9999" : "10"));
                $("#_city_select_row").hide();
                $("#_city_row").show();
            }

            function customActions(actionName) {
                if (actionName === "set_link_name" || actionName === "set_inactive" || actionName === 'clear_inactive') {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=" + actionName, function (returnArray) {
                        getDataList();
                    });
                    return true;
                }
                if (actionName === "merge_selected") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=merge_selected", function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            getDataList();
                        }
                    });
                    return true;
                }
                if (actionName === "set_map_policy") {
                    $('#_set_map_policy_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Set MAP Policy',
                        buttons: {
                            Save: function (event) {
                                if ($("#_set_map_policy_form").validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_map_policy", $("#_set_map_policy_form").serialize(), function (returnArray) {
                                        getDataList();
                                    });
                                    $("#_set_map_policy_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_set_map_policy_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                if (actionName === "set_tag") {
                    $('#_set_tag_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Set MAP Policy',
                        buttons: {
                            Save: function (event) {
                                if ($("#_set_tag_form").validationEngine('validate')) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_tag", $("#_set_tag_form").serialize(), function (returnArray) {
                                        getDataList();
                                    });
                                    $("#_set_tag_dialog").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_set_tag_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                return false;
            }
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['upper_image'] = array("data_value" => "<img src='" . getImageFilename($returnArray['image_id']['data_value'], array("use_cdn" => true, "image_type" => "small")) . "'>");
	}

	function beforeSaveChanges($nameValues) {
		$originalMapPolicyId = getFieldFromId("map_policy_id", "product_manufacturers", "product_manufacturer_id", $nameValues['primary_id']);
		if ($originalMapPolicyId != $nameValues['map_policy_id']) {
			executeQuery("delete from product_sale_prices where product_id in (select product_id from products where product_manufacturer_id = ?)", $nameValues['primary_id']);
		}
		return true;
	}
    function afterSaveDone() {
        removeCachedData("product_menu_page_module", "*");
    }
    function internalCSS() {
		?>
        #_maintenance_form { position: relative; }
        #upper_image { position: absolute; top: 0; right: 0; z-index: 1000; }
        #upper_image img { max-height: 100px; max-width: 500px; }
		<?php
	}

	function hiddenElements() {
		?>
        <div id="_set_map_policy_dialog" class="dialog-box">
            <p>Set selected Manufacturers to this MAP Policy</p>
            <p class="red-text"><span class="highlighted-text page-select-count"></span> manufacturers are selected and will be set to this MAP Policy. MAKE SURE this is correct.</p>
            <form id="_set_map_policy_form">
                <div class="basic-form-line" id="_map_policy_id_row">
                    <label for="map_policy_id">MAP Policy</label>
                    <select id="map_policy_id" name="map_policy_id" class="validate[required]">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from map_policies order by description");
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['map_policy_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_percentage_row">
                    <label for="percentage">Percent above MAP</label>
                    <span class='help-label'>Only applied to strict MAP. Leave blank to not change.</span>
                    <input id="percentage" name="percentage" type='text' size='8' class='validate[custom[number],min[0]]' data-decimal-places='4'>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
            </form>
        </div>
        <div id="_set_tag_dialog" class="dialog-box">
            <p>Set selected Manufacturers to this Tag</p>
            <p class="red-text"><span class="highlighted-text page-select-count"></span> manufacturers are selected and will be set to this Tag.</p>
            <form id="_set_tag_form">
                <div class="basic-form-line" id="_product_manufacturer_tag_id_row">
                    <label for="product_manufacturer_tag_id">Manufacturer Tag</label>
                    <select id="product_manufacturer_tag_id" name="product_manufacturer_tag_id" class="validate[required]">
                        <option value="">[Select]</option>
						<?php
						$resultSet = executeQuery("select * from product_manufacturer_tags order by sort_order,description");
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['product_manufacturer_tag_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
            </form>
        </div>
		<?php
	}
}

$pageObject = new ProductManufacturerMaintenancePage("product_manufacturers");
$pageObject->displayPage();
