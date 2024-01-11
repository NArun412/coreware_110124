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

$GLOBALS['gPageCode'] = "PRODUCTMANUFACTURERMAINT_LITE";
require_once "shared/startup.inc";

class ProductManufacturerMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("product_manufacturer_code", "description", "first_name", "last_name", "business_name", "city", "state", "postal_code", "email_address", "web_page", "link_name"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
		}
	}

	function massageDataSource() {
		$this->iDataSource->setJoinTable("contacts", "contact_id", "contact_id");
		$this->iDataSource->setSaveOnlyPresent(true);
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

		$this->iDataSource->addColumnControl("map_policy_id", "empty_text", "Sale price shows in cart");
        $this->iDataSource->addColumnControl("product_manufacturer_map_holidays", "data_type", "custom");
        $this->iDataSource->addColumnControl("product_manufacturer_map_holidays", "control_class", "EditableList");
        $this->iDataSource->addColumnControl("product_manufacturer_map_holidays", "form_label", "MAP Holidays");
        $this->iDataSource->addColumnControl("product_manufacturer_map_holidays", "list_table", "product_manufacturer_map_holidays");

		$this->iDataSource->addColumnControl("image_id", "data_type", "image_input");
		$this->iDataSource->addColumnControl("image_id", "form_label", "Logo");

		$this->iDataSource->addColumnControl("detailed_description", "wysiwyg", "true");
		$this->iDataSource->addColumnControl("link_name", "classes", "url-link");

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

		</script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['upper_image'] = array("data_value" => "<img src='" . getImageFilename($returnArray['image_id']['data_value'],array("use_cdn"=>true,"image_type" => "small")) . "'>");
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

}

$pageObject = new ProductManufacturerMaintenancePage("product_manufacturers");
$pageObject->displayPage();
