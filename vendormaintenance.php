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

$GLOBALS['gPageCode'] = "VENDORMAINT";
require_once "shared/startup.inc";

class VendorMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("description","first_name","last_name","business_name","city","state","postal_code","email_address"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->setJoinTable("contacts","contact_id","contact_id");
		$this->iDataSource->setSaveOnlyPresent(true);
		$this->iDataSource->addColumnControl("city_select","data_type","select");
		$this->iDataSource->addColumnControl("city_select","form_label","City");
		$this->iDataSource->addColumnControl("country_id","default_value","1000");
		$this->iDataSource->addColumnControl("date_created","default_value","return date(\"m/d/Y\")");
		$this->iDataSource->addColumnControl("state","css-width","60px");
		$this->iDataSource->addColumnControl("phone_numbers","data_type","custom");
		$this->iDataSource->addColumnControl("phone_numbers","form_label","Phone Numbers");
		$this->iDataSource->addColumnControl("phone_numbers","control_class","EditableList");
		$this->iDataSource->addColumnControl("phone_numbers","list_table","phone_numbers");
		$this->iDataSource->addColumnControl("phone_numbers","foreign_key_field","contact_id");
		$this->iDataSource->addColumnControl("phone_numbers","primary_key_field","contact_id");
	}

	function onLoadJavascript() {
?>
$("#postal_code").blur(function() {
	if ($("#country_id").val() == "1000") {
		validatePostalCode();
	}
});
$("#country_id").change(function() {
	$("#city").add("#state").prop("readonly",$("#country_id").val() == "1000");
	$("#city").add("#state").attr("tabindex",($("#country_id").val() == "1000" ? "9999" : "10"));
	$("#_city_row").show();
	$("#_city_select_row").hide();
	if ($("#country_id").val() == "1000") {
		validatePostalCode();
	}
});
$("#city_select").change(function() {
	$("#city").val($(this).val());
	$("#state").val($(this).find("option:selected").data("state"));
});
<?php
	}

	function javascript() {
?>
function afterGetRecord() {
	$("#city").add("#state").prop("readonly",$("#country_id").val() == "1000");
	$("#city").add("#state").attr("tabindex",($("#country_id").val() == "1000" ? "9999" : "10"));
	$("#_city_select_row").hide();
	$("#_city_row").show();
}
<?php
	}
}

$pageObject = new VendorMaintenancePage("vendors");
$pageObject->displayPage();
