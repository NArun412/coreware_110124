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

$GLOBALS['gPageCode'] = "COMPANYUSERMAINT";
require_once "shared/startup.inc";

class CompanyUserMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("user_name", "administrator_flag", "users.date_created", "first_name", "last_name", "business_name", "email_address", "inactive", "last_login", "locked"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeSearchColumn(array("password", "password_salt"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn(array("contacts.date_created"));
			if (empty($GLOBALS['gUserRow']['company_id'])) {
				$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "add"));
			} else {
				$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));
			}
		}
	}

	function massageDataSource() {
		if (empty($GLOBALS['gUserRow']['company_id'])) {
			$this->iDataSource->setFilterWhere("user_id is null");
		} else {
			$this->iDataSource->setFilterWhere("company_id = " . $GLOBALS['gUserRow']['company_id']);
		}
		$this->iDataSource->setJoinTable("contacts", "contact_id", "contact_id");
		$this->iDataSource->setSaveOnlyPresent(true);
		$minimumPasswordLength = getPreference("minimum_password_length");
		if (empty($minimumPasswordLength)) {
			$minimumPasswordLength = 10;
		}
		$this->iDataSource->addColumnControl("password", "validation_classes", "custom[pciPassword],minSize[" . $minimumPasswordLength . "]");
		if (getPreference("PCI_COMPLIANCE")) {
			$noPasswordRequirements = false;
		} else {
			$noPasswordRequirements = getPreference("no_password_requirements");
		}
		if ($noPasswordRequirements) {
			$this->iDataSource->addColumnControl("password", "classes", "no-password-requirements");
		}
		if (getPreference("PCI_COMPLIANCE")) {
			$this->iDataSource->addColumnControl("answer_text", "validation_classes", "minSize[3]");
			$this->iDataSource->addColumnControl("secondary_answer_text", "validation_classes", "minSize[3]");
		}
		$this->iDataSource->addColumnControl("password", "help_label", "will be reset by user");
		$this->iDataSource->addColumnControl("password", "no_required_label", "true");
		if (!$GLOBALS['gUserRow']['superuser_flag']) {
			$this->iDataSource->addFilterWhere("superuser_flag = 0");
		}
		$this->iDataSource->addColumnControl("user_group_members", "data_type", "custom");
		$this->iDataSource->addColumnControl("user_group_members", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("user_group_members", "form_label", "Groups");
		$this->iDataSource->addColumnControl("user_group_members", "links_table", "user_group_members");
		$this->iDataSource->addColumnControl("user_group_members", "control_table", "user_groups");
		$this->iDataSource->addColumnControl("company_id", "data_type", "hidden");
		$this->iDataSource->addColumnControl("company_id", "default_value", $GLOBALS['gUserRow']['company_id']);
	}

	function userGroupChoices($showInactive = false) {
		$userGroupChoices = array();
		$resultSet = executeQuery("select * from user_groups where internal_use_only = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$userGroupChoices[$row['user_group_id']] = array("key_value" => $row['user_group_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1);
		}
		freeResult($resultSet);
		return $userGroupChoices;
	}

	function beforeSaveChanges(&$nameValues) {
		if (empty($nameValues['password'])) {
			unset($nameValues['password']);
		}
		return true;
	}

	function pageChoices($showInactive = false) {
		$pageChoices = array();
		$resultSet = executeQuery("select page_id,description,inactive from pages where (client_id = ? or client_id = ?) order by description",
			$GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
		while ($row = getNextRow($resultSet)) {
			$pageAccessId = getFieldFromId("page_access_id", "page_access", "page_id", $row['page_id'], "public_access = 1 and permission_level > 2");
			if (empty($pageAccessId) && canAccessPage($row['page_id']) && (empty($row['inactive']) || $showInactive)) {
				$pageChoices[$row['page_id']] = array("key_value" => $row['page_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1);
			}
		}
		freeResult($resultSet);
		return $pageChoices;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if (!empty($nameValues['password'])) {
			$passwordSalt = getRandomString(64);
			$password = hash("sha256", $nameValues['primary_id'] . $passwordSalt . $nameValues['password']);
			$resultSet = executeQuery("update users set password = ?,password_salt = ?,last_password_change = now()," .
				"force_password_change = 1 where user_id = ?", $password, $passwordSalt, $nameValues['primary_id']);
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
        if(startsWith($returnArray['password']['data_value'], "SSO_")) {
            $returnArray['password'] = array("data_value" => "sso", "crc_value" => getCrcValue(""));
        } else {
            $returnArray['password'] = array("data_value" => "", "crc_value" => getCrcValue(""));
        }
    }

	function internalCSS() {
		?>
        <style>
			#_contact_left_column {
				width: 65%;
				float: left;
				margin-top: 10px;
			}
			#_contact_right_column {
				width: 34%;
				margin-left: 65%;
				margin-top: 10px;
			}
        </style>
		<?php
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
                $("#city").show();
                $("#city_select").hide();
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
            function afterGetRecord() {
                $("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() === "1000" ? "9999" : "10"));
                $("#city_select").hide();
                $("#city").show();
                if(returnArray['password']['data_value'] == 'sso') {
                    $("#tab_3").html("This user's login is handled by Single Sign-On (SSO).")
                }
            }
        </script>
		<?php
	}
}

$pageObject = new CompanyUserMaintenancePage("users");
$pageObject->displayPage();
