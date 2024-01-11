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

$GLOBALS['gPageCode'] = "CLASSINSTRUCTORMAINT";
require_once "shared/startup.inc";

class ClassInstructorMaintenance extends Page {

    function setup() {
        if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
            $this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("first_name","last_name", "address_1", "city", "state", "postal_code", "email_address"));
            $this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
        }
    }

    function massageDataSource() {
        $this->iDataSource->getPrimaryTable()->setSubtables(array("class_instructor_qualifications"));
        $this->iDataSource->setJoinTable("contacts", "contact_id", "contact_id");

        $this->iDataSource->setSaveOnlyPresent(true);
        $this->iDataSource->addColumnControl("class_instructor_qualifications", "data_type", "custom");
        $this->iDataSource->addColumnControl("class_instructor_qualifications", "control_class", "EditableList");
        $this->iDataSource->addColumnControl("class_instructor_qualifications", "list_table", "class_instructor_qualifications");

        $this->iDataSource->addColumnControl("image_id", "form_label", "Photo");
        $this->iDataSource->addColumnControl("image_id", "data_type", "image_input");

        $this->iDataSource->addColumnControl("phone_numbers", "data_type", "custom");
        $this->iDataSource->addColumnControl("phone_numbers", "control_class", "EditableList");
        $this->iDataSource->addColumnControl("phone_numbers", "form_label", "Phone Numbers");
        $this->iDataSource->addColumnControl("phone_numbers", "foreign_key_field", "contact_id");
        $this->iDataSource->addColumnControl("phone_numbers", "primary_key_field", "contact_id");
        $this->iDataSource->addColumnControl("phone_numbers", "list_table", "phone_numbers");

        $this->iDataSource->addColumnControl("city_select", "data_type", "select");
        $this->iDataSource->addColumnControl("city_select", "form_label", "City");
        $this->iDataSource->addColumnControl("country_id", "default_value", "1000");
        $this->iDataSource->addColumnControl("country_id", "data_type", "hidden");
        $this->iDataSource->addColumnControl("date_created", "default_value", date("m/d/Y"));
        $this->iDataSource->addColumnControl("date_created", "readonly", true);
        $this->iDataSource->addColumnControl("state", "css-width", "60px");

    }

    function onLoadJavascript() {
        ?>
        <script>
            $("#postal_code").blur(function () {
                if ($("#country_id").val() === "1000") {
                    validatePostalCode();
                }
            });
            $("#country_id").change(function () {
                $("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() === "1000" ? "9999" : "10"));
                $("#_city_row").show();
                $("#_city_select_row").hide();
                if ($("#country_id").val() === "1000") {
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
            function afterGetRecord(returnArray) {
                $("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() === "1000" ? "9999" : "10"));
                $("#_city_select_row").hide();
                $("#_city_row").show();
            }
        </script>
        <?php
    }

}

$pageObject = new ClassInstructorMaintenance("class_instructors");
$pageObject->displayPage();
