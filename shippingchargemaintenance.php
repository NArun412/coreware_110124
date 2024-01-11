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

$GLOBALS['gPageCode'] = "SHIPPINGCHARGEMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function setup() {
		$this->iTemplateObject->getTableEditorObject()->setFormSortOrder(array("shipping_method_id", "description", "minimum_amount", "maximum_order_amount", "minimum_charge",
			"percentage", "flat_rate", "additional_item_charge", "maximum_amount", "per_location", "shipping_locations", "product_department_id", "shipping_rates"));
	}

	function massageDataSource() {
        $this->iDataSource->getPrimaryTable()->setSubtables(array("shipping_locations", "shipping_rates", "product_distributor_shipping_charges",
            "shipping_charge_product_categories","shipping_charge_product_departments","shipping_charge_product_types"));
        $this->iDataSource->setFilterWhere("shipping_method_id in (select shipping_method_id from shipping_methods where client_id = " . $GLOBALS['gClientId'] . ")");
		$this->iDataSource->addColumnControl("minimum_amount", "form_label", "Minimum Order Amount");
		$this->iDataSource->addColumnControl("minimum_charge", "form_label", "Minimum Shipping Charge");
		$this->iDataSource->addColumnControl("product_department_id", "help_label", "Limit weight based shipping rate charges to items in this department");
		$this->iDataSource->addColumnControl("product_department_id", "empty_text", "[All]");

		$this->iDataSource->addColumnControl("shipping_service_calculation", "form_label", "Use EasyPost to calculate shipping <span class='shipping-service-info fad fa-info-circle'></span>");
		$this->iDataSource->addColumnControl("shipping_service_flat_rate", "help_label", "Flat rate to add to each box for shipping service calculations");
		$this->iDataSource->addColumnControl("shipping_service_flat_rate", "minimum_value", 0);
		$this->iDataSource->addColumnControl("shipping_service_percentage", "help_label", "Percentage to add to each box for shipping service calculations");
		$this->iDataSource->addColumnControl("shipping_service_percentage", "minimum_value", 0);
		$this->iDataSource->addColumnControl("shipping_service_maximum_weight", "help_label", "Maximum weight per box for shipping service");
		$this->iDataSource->addColumnControl("shipping_service_maximum_weight", "minimum_value", 0);
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".shipping-service-info", function () {
                $('#_shipping_service_info_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Shipping Service',
                    buttons: {
                        Cancel: function (event) {
                            $("#_shipping_service_info_dialog").dialog('close');
                        }
                    }
                });
            });
            $("#shipping_method_id").blur(function () {
                if (!empty($(this).val()) && empty($("#description").val())) {
                    $("#description").val($(this).find("option:selected").text());
                }
            });
            $(document).on("click", "#shipping_service_calculation", function () {
                showHideShippingServiceFields();
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
                showHideShippingServiceFields();
            }

            function showHideShippingServiceFields() {
                if ($("#shipping_service_calculation").prop("checked")) {
                    $("#_shipping_service_flat_rate_row").removeClass("hidden");
                    $("#_shipping_service_percentage_row").removeClass("hidden");
                    $("#_shipping_service_maximum_weight_row").removeClass("hidden");
                } else {
                    $("#_shipping_service_flat_rate_row").addClass("hidden");
                    $("#_shipping_service_percentage_row").addClass("hidden");
                    $("#_shipping_service_maximum_weight_row").addClass("hidden");
                }
            }
        </script>
		<?php
	}

	function hiddenElements() {
		?>
        <div class='dialog-box' id='_shipping_service_info_dialog'>
            <p>If EasyPost integration is enabled, the shipping charge will be calculated as the lowest charge available from EasyPost for the weight of the items, from the store location to the customer's address. This calculation is ONLY used if the following conditions are met. If any condition is not met, normal calculations will be used.</p>
            <ul>
                <li>Of course, EasyPost integration is working and active.</li>
                <li>The shipping method is NOT pickup.</li>
                <li>All products in the order have a weight greater than zero.</li>
                <li>All products in the order are available at a local location. If any product is only available from a distributor, the normal shipping calculations are used.</li>
                <li>At least one rate is returned by EasyPost.</li>
            </ul>
            <p>Minimum shipping charge will still be applied if this Shipping Service charge is used.</p>
        </div>
		<?php
	}
}

$pageObject = new ThisPage("shipping_charges");
$pageObject->displayPage();
