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

$GLOBALS['gPageCode'] = "CLIENTSELFMAINT";
require_once "shared/startup.inc";

class ClientSelfMaintenancePage extends Page {

	function setup() {
		setUserPreference("MAINTENANCE_SAVE_NO_LIST", "true", $GLOBALS['gPageRow']['page_code']);
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "delete", "list"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->setFilterWhere("client_id = " . $GLOBALS['gDefaultClientId'] . " and contact_id = " . $GLOBALS['gClientRow']['contact_id']);
		$this->iDataSource->setSaveOnlyPresent(true);
		$this->iDataSource->getPrimaryTable()->setLimitByClient(false);

		$this->iDataSource->addColumnControl("address_1", "not_null", true);
		$this->iDataSource->addColumnControl("city", "not_null", true);
		$this->iDataSource->addColumnControl("city_select", "data_type", "select");
		$this->iDataSource->addColumnControl("city_select", "form_label", "City");
		$this->iDataSource->addColumnControl("business_name", "not_null", true);
		$this->iDataSource->addColumnControl("email_address", "not_null", true);
		$this->iDataSource->addColumnControl("phone_numbers", "column_list", "phone_numbers.phone_number,phone_numbers.description");
		$this->iDataSource->addColumnControl("phone_numbers", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("phone_numbers", "data_type", "custom");
		$this->iDataSource->addColumnControl("phone_numbers", "foreign_key_field", "contact_id");
		$this->iDataSource->addColumnControl("phone_numbers", "no_limit_by_client", true);
		$this->iDataSource->addColumnControl("phone_numbers", "list_table", "phone_numbers");
		$this->iDataSource->addColumnControl("phone_numbers", "list_table_controls", array("contact_id" => array("default_value" => $GLOBALS['gClientRow']['contact_id']), "description" => array("size" => "10")));
		$this->iDataSource->addColumnControl("phone_numbers", "primary_key_field", "contact_id");
		$this->iDataSource->addColumnControl("postal_code", "not_null", true);
		$this->iDataSource->addColumnControl("state", "css-width", "60px");
	}

	function massageUrlParameters() {
		$_GET['url_subpage'] = $_GET['url_page'];
		$_GET['url_page'] = "show";
		$_GET['primary_id'] = $GLOBALS['gClientRow']['contact_id'];
	}

	function beforeSaveChanges(&$nameValues) {
		$nameValues['contact_id'] = $nameValues['primary_id'];
		return true;
	}

    function afterSaveChanges($nameValues,$actionPerformed) {
	    removeCachedData("client_row", $GLOBALS['gClientId'], true);
	    removeCachedData("client_name", $GLOBALS['gClientId'], true);
        return true;
    }

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("blur", "#postal_code", function () {
                if ($("#country_id").val() === "1000") {
                    validatePostalCode();
                }
            });
            $("#country_id").change(function () {
                const $city = $("#city");
                const $countryId = $("#country_id");
                $city.add("#state").prop("readonly", $countryId.val() === "1000");
                $city.add("#state").attr("tabindex", ($countryId.val() === "1000" ? "9999" : "10"));
                $("#_city_row").show();
                $("#_city_select_row").hide();
                if ($countryId.val() === "1000") {
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
                const $city = $("#city");
                const $countryId = $("#country_id");
                $city.add("#state").prop("readonly", $countryId.val() === "1000");
                $city.add("#state").attr("tabindex", ($countryId.val() === "1000" ? "9999" : "10"));
                $("#_city_select_row").hide();
                $("#_city_row").show();
            }
        </script>
		<?php
	}

}

$pageObject = new ClientSelfMaintenancePage("contacts");
$pageObject->displayPage();
