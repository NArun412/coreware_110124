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

$GLOBALS['gPageCode'] = "DEVELOPERMAINT";
require_once "shared/startup.inc";

class DeveloperMaintenancePage extends Page {

	function setup() {
		if (array_key_exists("connection_key", $_POST)) {
			$this->iDataSource->addColumnControl("connection_key", "readonly", false);
		}
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("start_date", "first_name", "last_name", "business_name", "city", "state", "postal_code", "email_address"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->setJoinTable("contacts", "contact_id", "contact_id");
		$this->iDataSource->setSaveOnlyPresent(true);

		$this->iDataSource->addColumnControl("first_name", "data-conditional-required", "empty($(\"#business_name\").val())");
		$this->iDataSource->addColumnControl("first_name", "not_null", true);
		$this->iDataSource->addColumnControl("last_name", "data-conditional-required", "empty($(\"#business_name\").val())");
		$this->iDataSource->addColumnControl("last_name", "not_null", true);

		$this->iDataSource->addColumnControl("developer_api_method_groups", "data_type", "custom");
		$this->iDataSource->addColumnControl("developer_api_method_groups", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("developer_api_method_groups", "form_label", "API Method Groups");
		$this->iDataSource->addColumnControl("developer_api_method_groups", "links_table", "developer_api_method_groups");
		$this->iDataSource->addColumnControl("developer_api_method_groups", "control_table", "api_method_groups");

		$this->iDataSource->addColumnControl("users_user_id", "select_value", "select user_id from users where contact_id = developers.contact_id");
		$this->iDataSource->addColumnControl("users_user_id", "data_type", "hidden");
		$this->iDataSource->addColumnControl("user_id", "help_label", "Select [None] to login as the developer's user");
		$this->iDataSource->addColumnControl("user_id", "data_type", "user_picker");
		$this->iDataSource->addColumnLikeColumn("contact_un", "users", "user_name");
		$this->iDataSource->addColumnControl("contact_un", "form_label", "User Name");
		$this->iDataSource->addColumnControl("contact_un", "not_null", "false");
		$this->iDataSource->addColumnControl("contact_un", "classes", "allow-dash");
		if (canAccessPageCode("USERMAINT")) {
			$this->iDataSource->addColumnControl("contact_un", "help_label", "Click to open User record");
		}
		$this->iDataSource->addColumnLikeColumn("contact_pw", "users", "password");
		$this->iDataSource->addColumnControl("contact_pw", "not_null", "false");
	}

	function executePageUrlActions() {
		switch ($_GET['url_action']) {
			case "get_connection_key";
				$returnArray = array("connection_key" => strtoupper(getRandomString()));
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
			<?php if (canAccessPageCode("USERMAINT")) { ?>
            $(document).on("click", "#contact_un", function () {
                const userId = $("#users_user_id").val();
                if (!empty(userId)) {
                    window.open("/usermaintenance.php?clear_filter=true&url_page=show&primary_id=" + userId);
                }
            });
			<?php } ?>
			<?php if (canAccessPageCode("USERMAINT")) { ?>
            $("#contact_un").change(function () {
                const $userNameMessage = $("#_user_name_message");
                if (!empty($(this).val())) {
                    loadAjaxRequest("/checkusername.php?ajax=true&user_name=" + $(this).val(), function(returnArray) {
                        $userNameMessage.removeClass("info-message").removeClass("error-message");
                        if ("info_user_name_message" in returnArray) {
                            $("#_user_name_message").html(returnArray['info_user_name_message']).addClass("info-message");
                        }
                        if ("error_user_name_message" in returnArray) {
                            $userNameMessage.html(returnArray['error_user_name_message']).addClass("error-message");
                            $("#contact_un").val("").focus();
                            setTimeout(function () {
                                $("#_edit_form").validationEngine("hideAll");
                            }, 10);
                        }
                    });
                } else {
                    $userNameMessage.val("");
                }
            });
			<?php } ?>
            $("#_get_new_connection_key").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_connection_key", function(returnArray) {
                    $("#connection_key").val(returnArray['connection_key']);
                });
                return false;
            });
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
            function afterGetRecord() {
				<?php if (canAccessPageCode("USERMAINT")) { ?>
                const $contactUn = $("#contact_un");
                $contactUn.prop("readonly", !empty($contactUn.val()));
				<?php } ?>
                $("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() === "1000" ? "9999" : "10"));
                $("#_city_select_row").hide();
                $("#_city_row").show();
                if (empty($("#primary_id").val())) {
                    $("#_get_new_connection_key").trigger("click");
                }
            }
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #contact_un:read-only {
                cursor: pointer;
            }
        </style>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		if (canAccessPageCode("USERMAINT")) {
			$userId = Contact::getContactUserId($returnArray['contact_id']['data_value']);
			$returnArray['users_user_id'] = array("data_value" => $userId);
			$userName = getFieldFromId("user_name", "users", "contact_id", $returnArray['contact_id']['data_value']);
			$returnArray['contact_un'] = array("data_value" => $userName, "crc_value" => getCrcValue($userName));
			$returnArray['contact_pw'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		}
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if (!empty($nameValues['users_user_id']) && !empty($nameValues['contact_pw'])) {
			$passwordSalt = getRandomString(64);
			$password = hash("sha256", $nameValues['users_user_id'] . $passwordSalt . $nameValues['contact_pw']);
			executeQuery("update users set password = ?,password_salt = ?,last_password_change = now()," .
				"force_password_change = 1 where user_id = ? and contact_id = ?", $password, $passwordSalt,
				$nameValues['users_user_id'], $nameValues['contact_id']);
		}
		if (empty($nameValues['users_user_id']) && !empty($nameValues['contact_un']) && !empty($nameValues['contact_pw'])) {
			$passwordSalt = getRandomString(64);
			$password = hash("sha256", $passwordSalt . $nameValues['contact_pw']);

			$nameValues['contact_un'] = makeCode($nameValues['contact_un'], array("lowercase" => true));
			$checkUserId = getFieldFromId("user_id", "users", "user_name", $nameValues['contact_un'], "client_id = ? or superuser_flag = 1", $GLOBALS['gClientId']);
			if (!empty($checkUserId)) {
				return "User name is unavailable. Choose another";
			}

			$usersTable = new DataTable("users");
			if ($userId = $usersTable->saveRecord(array("name_values"=>array("client_id"=>$GLOBALS['gClientId'],"contact_id"=>$nameValues['contact_id'],"user_name"=>strtolower($nameValues['contact_un']),
				"password_salt"=>$passwordSalt,"password"=>$password,"date_created"=>date("Y-m-d H:i:s"))))) {
				$password = hash("sha256", $userId . $passwordSalt . $nameValues['contact_pw']);
				executeQuery("update users set password = ?,force_password_change = 1 where user_id = ?", $password, $userId);
			}
		}
		return true;
	}
}

$pageObject = new DeveloperMaintenancePage("developers");
$pageObject->displayPage();
