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

$GLOBALS['gPageCode'] = "USERSELFMAINT";
$GLOBALS['gSetRequiredFields'] = true;
$GLOBALS['gPreemptivePage'] = true;
require_once "shared/startup.inc";

if (!$GLOBALS['gLoggedIn']) {
	header("Location: /");
	exit;
}

class UserSelfMaintenancePage extends Page {

	function setup() {
		setUserPreference("MAINTENANCE_SAVE_NO_LIST","true",$GLOBALS['gPageRow']['page_code']);
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			if (empty($GLOBALS['gDomainClientId']) && $GLOBALS['gUserRow']['superuser_flag']) {
				$columnList = array("users.client_id","title","first_name","middle_name",
					"last_name","suffix","business_name","address_1","address_2","city","city_select","state","postal_code","country_id",
					"email_address","birthdate","phone_numbers","language_id","image_id");
			} else {
				$columnList = array("title","first_name","middle_name",
					"last_name","suffix","business_name","address_1","address_2","city","city_select","state","postal_code","country_id",
					"email_address","birthdate","phone_numbers","language_id","image_id");
			}
			if (getPreference("PCI_COMPLIANCE")) {
				$columnList[] = "security_question_id";
				$columnList[] = "answer_text";
				$columnList[] = "secondary_security_question_id";
				$columnList[] = "secondary_answer_text";
			}
			$this->iTemplateObject->getTableEditorObject()->addIncludeFormColumn($columnList);
			$this->iTemplateObject->getTableEditorObject()->setFormSortOrder($columnList);
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add","delete","list"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("city_select","data_type","select");
		$this->iDataSource->addColumnControl("city_select","form_label","City");

		$this->iDataSource->setFilterWhere("contacts.contact_id = " . $GLOBALS['gUserRow']['contact_id']);
		$this->iDataSource->setJoinTable("users");
		$this->iDataSource->getPrimaryTable()->setLimitByClient(false);
		$this->iDataSource->getJoinTable()->setLimitByClient(false);
		$this->iDataSource->setSaveOnlyPresent(true);
		if (getPreference("PCI_COMPLIANCE")) {
			$this->iDataSource->addColumnControl("security_question_id","not_null","true");
			$this->iDataSource->addColumnControl("secondary_security_question_id","not_null","true");
			$this->iDataSource->addColumnControl("answer_text","not_null","true");
			$this->iDataSource->addColumnControl("answer_text","validation_classes","minSize[3]");
			$this->iDataSource->addColumnControl("secondary_answer_text","not_null","true");
			$this->iDataSource->addColumnControl("secondary_answer_text","validation_classes","minSize[3]");
		}
		$this->iDataSource->addColumnControl("users.client_id","get_choices","clientChoices");
		$this->iDataSource->addColumnControl("email_address","not_null","true");
		$this->iDataSource->addColumnControl("first_name","not_null","true");
		$this->iDataSource->addColumnControl("last_name","not_null","true");
		$this->iDataSource->addColumnControl("image_id","form_label","Image");
		$this->iDataSource->addColumnControl("image_id","data_type","image_input");
	}

	function mainContent() {
		if (!empty($_GET['required'])) {
?>
<p class="error-message highlighted-text">Your user account has some required information missing. Please fill in all the required fields below.</p>
<?php
		}
		return false;
	}

	function massageUrlParameters() {
		$_GET['url_subpage'] = $_GET['url_page'];
		$_GET['url_page'] = "show";
		$_GET['primary_id'] = $GLOBALS['gUserRow']['contact_id'];
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
function afterSaveChanges() {
	$("body").data("just_saved","true");
	setTimeout(function() {
		document.location = "/";
	},2000);
	return true;
}
<?php
	}

	function afterSaveChanges($nameValues,$actionPerformed) {
		executeQuery("update contacts set client_id = (select client_id from users where user_id = ?) where contact_id = ?",
			$GLOBALS['gUserId'],$GLOBALS['gUserRow']['contact_id']);
		return true;
	}
}

$pageObject = new UserSelfMaintenancePage("contacts");
$pageObject->displayPage();
