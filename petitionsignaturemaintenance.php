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

$GLOBALS['gPageCode'] = "PETITIONSIGNATUREMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function setup() {
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$filters = array();
			$resultSet = executeQuery("select * from petition_types where client_id = ? order by sort_order,description",$GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['petition_type_id_' . $row['petition_type_id']] = array("form_label"=>$row['description'],"where"=>"petition_type_id = " . $row['petition_type_id'],"data_type"=>"tinyint");
			}
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add"));
			$this->iTemplateObject->getTableEditorObject()->addIncludeColumn(array("full_name","petition_signatures.date_created","view_contact","first_name","last_name","city","state","postal_code","email_address","phone_number"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->setJoinTable("contacts","contact_id","contact_id",true);

		$this->iDataSource->addColumnControl("first_name","classes","contact-field");
		$this->iDataSource->addColumnControl("last_name","classes","contact-field");
		$this->iDataSource->addColumnControl("city","classes","contact-field");
		$this->iDataSource->addColumnControl("state","classes","contact-field");
		$this->iDataSource->addColumnControl("postal_code","classes","contact-field");
		$this->iDataSource->addColumnControl("email_address","classes","contact-field");
		$this->iDataSource->addColumnControl("phone_number","data_type","varchar");
		$this->iDataSource->addColumnControl("phone_number","form_label","Phone");

		$this->iDataSource->addColumnControl("first_name","readonly",true);
		$this->iDataSource->addColumnControl("last_name","readonly",true);
		$this->iDataSource->addColumnControl("city","readonly",true);
		$this->iDataSource->addColumnControl("state","readonly",true);
		$this->iDataSource->addColumnControl("postal_code","readonly",true);
		$this->iDataSource->addColumnControl("email_address","readonly",true);
		$this->iDataSource->addColumnControl("phone_number","readonly",true);
		$this->iDataSource->addColumnControl("date_created","readonly",true);

		$this->iDataSource->addColumnControl("view_contact","data_type","button");
		$this->iDataSource->addColumnControl("view_contact","button_label","Open Contact");
	}

	function afterGetRecord(&$returnArray) {
		ob_start();
		$customFields = CustomField::getCustomFields("petitions",$customFieldGroupCode);
		foreach ($customFields as $thisCustomField) {
			$customFieldId = getFieldFromId("custom_field_id","petition_type_custom_fields","custom_field_id",$thisCustomField['custom_field_id'],"petition_type_id = ?",$returnArray['petition_type_id']['data_value']);
			if (!empty($customFieldId)) {
				$customField = new CustomField($thisCustomField['custom_field_id']);
				if ($customField) {
					$customField->getColumn()->setControlValue("readonly", true);
					echo $customField->getControl();
				}
			}
		}
		$returnArray['_custom_data'] = array("data_value"=>ob_get_clean());
		$customFields = CustomField::getCustomFields("petitions");
		foreach ($customFields as $thisCustomField) {
			$customField = new CustomField($thisCustomField['custom_field_id']);
			if ($customField) {
				$customFieldData = $customField->getRecord($returnArray['primary_id']['data_value']);
				if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldData)) {
					$returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'], $customFieldData['select_values']);
				}
				$returnArray = array_merge($returnArray, $customFieldData);
			}
		}
		$returnArray['phone_number'] = array("data_value"=>getFieldFromId("phone_number","phone_numbers","contact_id",$returnArray['contact_id']['data_value']));
	}

	function onLoadJavascript() {
?>
<script>
$("#view_contact").click(function() {
	window.open("/contactmaintenance.php?primary_id=" + $("#contact_id").val() + "&url_page=show");
	return false;
});
</script>
<?php
	}

	function javascript() {
?>
<script>
function afterGetRecord(returnArray) {
	if (empty(returnArray['contact_id'])) {
		$(".contact-field").closest(".form-line").addClass("hidden");
	} else {
		$(".contact-field").closest(".form-line").removeClass("hidden");
	}
}
</script>
<?php
	}
}

$pageObject = new ThisPage("petition_signatures");
$pageObject->displayPage();
