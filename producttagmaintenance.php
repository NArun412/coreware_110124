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

$GLOBALS['gPageCode'] = "PRODUCTTAGMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		$filters['no_link_name'] = array("form_label" => "No Link Name Set", "where" => "link_name is null", "data_type" => "tinyint");
		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		$this->iTemplateObject->getTableEditorObject()->addCustomAction("set_link_name", "Set Link Name to Description for selected rows");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "set_link_name":
				$returnArray = DataTable::setLinkNames("product_tags");
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("points_multiplier", "minimum_value", "1");
	}

    function afterSaveDone() {
        removeCachedData("product_menu_page_module", "*");
    }

    function supplementaryContent() {
		?>
        <div id="sort_header">
            <h3>Sort Products</h3>
            <ul id="product_sort">
            </ul>
            <button id="_sort_added_desc" class="sort-button">Sort by Recently Added</button>
            <button id="_sort_added_asc" class="sort-button">Sort by First Added</button>
            <button id="_sort_alpha_asc" class="sort-button">Sort Alphabetical</button>
            <button id="_sort_alpha_desc" class="sort-button">Sort Reverse Alphabetical</button>
        </div>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#product_sort").sortable({
                update: function () {
                    changeSortOrder();
                }
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
                $("#product_sort").html("");
                if ("product_tag_links" in returnArray) {
                    $("#sort_header").removeClass("hidden");
                    for (var i in returnArray['product_tag_links']) {
                        var rowNumber = $("#product_sort").find(".product-row").length + 1;
                        var productRow = $("#product_row").html().replace(/%rowNumber%/g, rowNumber);
                        $("#product_sort").append(productRow);
                        $("#product_row_" + rowNumber).find(".product-tag-link-id").val(returnArray['product_tag_links'][i]['product_tag_link_id']);
                        $("#product_row_" + rowNumber).find(".sequence-number").val(returnArray['product_tag_links'][i]['sequence_number']).data("crc_value", returnArray['product_tag_links'][i]['crc_value']);
                        $("#product_row_" + rowNumber).find(".product-description").html("<span class='fa fa-bars'></span>" + returnArray['product_tag_links'][i]['description']);
                    }
                } else {
                    $("#sort_header").addClass("hidden");
                }
                $("#product_sort").sortable({
                    update: function () {
                        changeSortOrder();
                    }
                });
            }
            function changeSortOrder() {
                var sequenceNumber = 10;
                $("#product_sort").find(".product-row").each(function () {
                    $(this).find(".sequence-number").val(sequenceNumber);
                    sequenceNumber += 10;
                });
            }
            $(document).on("click", ".sort-button", function () {
                let sortType = $(this).attr("id");
                let productSort = $('#product_sort');
                let listItems = $('li', productSort);

                switch (sortType) {
                    case "_sort_added_desc":
                        listItems.sort(function (a, b) {
                            return (parseInt($(a).find(".product-tag-link-id").val()) < parseInt($(b).find(".product-tag-link-id").val())) ? 1 : -1;
                        });
                        break;
                    case "_sort_added_asc":
                        listItems.sort(function (a, b) {
                            return (parseInt($(a).find(".product-tag-link-id").val()) > parseInt($(b).find(".product-tag-link-id").val())) ? 1 : -1;
                        });
                        break;
                    case "_sort_alpha_asc":
                        listItems.sort(function (a, b) {
                            return ($(a).find(".product-description").text() > $(b).find(".product-description").text()) ? 1 : -1;
                        });
                        break;
                    case "_sort_alpha_desc":
                        listItems.sort(function (a, b) {
                            return ($(a).find(".product-description").text() < $(b).find(".product-description").text()) ? 1 : -1;
                        });
                        break;
                }
                productSort.append(listItems);
                changeSortOrder();
                return false;
            });
            function customActions(actionName) {
                if (actionName === "set_link_name") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=" + actionName, function(returnArray) {
                        getDataList();
                    });
                    return true;
                }
                return false;
            }

        </script>
		<?php
	}

	function jqueryTemplates() {
		?>
        <ul id="product_row">
            <li class="ui-state-default product-row" id="product_row_%rowNumber%">
                <input type="hidden" class="product-tag-link-id" id="product_tag_link_id_%rowNumber%" name="product_tag_link_id_%rowNumber%">
                <input type="hidden" class="sequence-number" id="sequence_number_%rowNumber%" name="sequence_number_%rowNumber%">
                <p class="product-description"></p>
            </li>
        </ul>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            .product-row {
                height: 30px;
                cursor: pointer;
            }

            #product_sort {
                width: 90%;
                margin: 10px 0 0 20px;
            }

            #product_sort li {
                margin: 0 13px 3px 3px;
                padding: 4px;
                height: auto;
                cursor: pointer;
            }

            .product-description {
                margin: 0;
            }

            .product-description .fa-bars {
                color: rgb(200, 200, 200);
                margin-right: 20px;
            }
        </style>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$resultSet = executeQuery("select * from product_tag_links where product_id in (select product_id from products where inactive = 0) and (expiration_date >= current_date or expiration_date is null) and product_tag_id = ? order by sequence_number",
			$returnArray['primary_id']['data_value']);
        $maxTaggedProducts = 500;
		if ($resultSet['row_count'] < $maxTaggedProducts) {
			$returnArray['product_tag_links'] = array();
			while ($row = getNextRow($resultSet)) {
				$returnArray['product_tag_links'][] = array("product_tag_link_id" => $row['product_tag_link_id'],
					"description" => getFieldFromId("product_code", "products", "product_id", $row['product_id']) . " - " . getFieldFromId("description", "products", "product_id", $row['product_id']),
					"sequence_number" => $row['sequence_number'], "crc_value" => getCrcValue($row['sequence_number']));
			}
		} else {
            $returnArray['error_message'] = sprintf("Too many tagged products to display (must be %s or fewer)", $maxTaggedProducts);
        }
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		removeCachedData("page_module-tagged_products", "*");
		removeCachedData("request_search_result", "*", true);
		removeCachedData("get_products_response", "*");

		foreach ($nameValues as $fieldName => $fieldValue) {
			if (substr($fieldName, 0, strlen("product_tag_link_id_")) == "product_tag_link_id_") {
				$rowNumber = substr($fieldName, strlen("product_tag_link_id_"));
				if (!is_numeric($rowNumber) || !is_numeric($fieldValue) || !is_numeric($nameValues['sequence_number_' . $rowNumber])) {
					continue;
				}
				executeQuery("update product_tag_links set sequence_number = ? where product_tag_link_id = ? and " .
					"product_tag_id = ?", $nameValues['sequence_number_' . $rowNumber], $fieldValue, $nameValues['primary_id']);
			}
		}
		return true;
	}
}

$pageObject = new ThisPage("product_tags");
$pageObject->displayPage();
