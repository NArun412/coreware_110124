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

$GLOBALS['gPageCode'] = "CLIENTMAINT";
require_once "shared/startup.inc";

class ClientMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$columnList = array("client_code", "client_type_id", "start_date", "inactive", "first_name", "last_name", "business_name", "city", "state", "postal_code", "email_address");
			$customFields = CustomField::getCustomFields("contacts");
			foreach ($customFields as $thisCustomField) {
				$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);

				$dataType = $customField->getColumn()->getControlValue("data_type");
				switch ($dataType) {
					case "date":
						$fieldName = "date_data";
						break;
					case "bigint":
					case "int":
						$fieldName = "integer_data";
						break;
					case "decimal":
						$fieldName = "number_data";
						break;
					case "image":
					case "image_input":
					case "file":
					case "custom":
					case "custom_control":
						$fieldName = "";
						break;
					default:
						$fieldName = "text_data";
						break;
				}
				if (empty($fieldName)) {
					continue;
				}
				$columnList[] = $customField->getColumn()->getControlValue("column_name");
			}
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn($columnList);
			$disabledFunctions = array("delete");
			$resultSet = executeQuery("select count(*) from clients where inactive = 0");
			$count = 0;
			if ($row = getNextRow($resultSet)) {
				$count = $row['count(*)'];
			}
			if ($GLOBALS['gDefaultClientId'] != $GLOBALS['gClientId'] || $count >= 75) {
				$disabledFunctions[] = "add";
			}
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions($disabledFunctions);
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
		}
	}

	function internalCSS() {
		?>
        <style>
            #_contact_left_column {
                float: left;
                width: 50%;
            }

            #_contact_left_column input {
                max-width: 350px;
            }

            #_contact_right_column {
                float: right;
                width: 50%;
            }

            #_contact_right_column input {
                max-width: 350px;
            }

            @media only screen and (max-width: 850px) {
                #_contact_left_column {
                    float: none;
                    width: 100%;
                }

                #_contact_left_column input {
                    max-width: 100%;
                }

                #_contact_right_column {
                    float: none;
                    width: 100%;
                }

                #_contact_right_column input {
                    max-width: 100%;
                }
            }
        </style>
		<?php
	}

	function massageDataSource() {
		$this->iDataSource->setJoinTable("contacts", "contact_id", "contact_id");
		$this->iDataSource->getPrimaryTable()->setLimitByClient(false);
		$this->iDataSource->getJoinTable()->setLimitByClient(false);
		$this->iDataSource->setSaveOnlyPresent(true);
		$this->iDataSource->addColumnControl("client_code","help_label", "Client code is used in various situations");
		$this->iDataSource->addColumnControl("email_address","not_null", true);
		$this->iDataSource->addColumnLikeColumn("new_user_user_name", "users", "user_name");
		$this->iDataSource->addColumnControl("business_name", "not_null", true);
		$this->iDataSource->addColumnControl("new_user_user_name", "not_null", "false");
		$this->iDataSource->addColumnControl("new_user_user_name", "classes", "allow-dash");
		$this->iDataSource->addColumnLikeColumn("new_user_password", "users", "password");
		$this->iDataSource->addColumnControl("new_user_password", "data-conditional-required", "\$(\"#new_user_user_name\").val() != \"\"");
		$this->iDataSource->addColumnLikeColumn("new_user_administrator_flag", "users", "administrator_flag");
		$this->iDataSource->addColumnLikeColumn("new_user_force_password_change", "users", "force_password_change");
		$this->iDataSource->addColumnLikeColumn("new_user_first_name", "contacts", "first_name");
		$this->iDataSource->addColumnControl("new_user_first_name", "data-conditional-required", "\$(\"#new_user_user_name\").val() != \"\"");
		$this->iDataSource->addColumnLikeColumn("new_user_last_name", "contacts", "last_name");
		$this->iDataSource->addColumnControl("new_user_last_name", "data-conditional-required", "\$(\"#new_user_user_name\").val() != \"\"");
		$this->iDataSource->addColumnLikeColumn("new_user_email_address", "contacts", "email_address");
		$this->iDataSource->addColumnControl("new_user_email_address", "data-conditional-required", "\$(\"#new_user_user_name\").val() != \"\"");

		$this->iDataSource->addColumnControl("contact_emails", "data_type", "custom");
		$this->iDataSource->addColumnControl("contact_emails", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("contact_emails", "form_label", "Alternate Email Addresses");
		$this->iDataSource->addColumnControl("contact_emails", "foreign_key_field", "contact_id");
		$this->iDataSource->addColumnControl("contact_emails", "list_table", "contact_emails");
		$this->iDataSource->addColumnControl("contact_emails", "list_table_controls", array("description" => array("size" => "10")));

		$this->iDataSource->addColumnControl("phone_numbers", "no_limit_by_client", true);
		$this->iDataSource->addColumnControl("client_subsystems", "data_type", "custom");
		$this->iDataSource->addColumnControl("client_subsystems", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("client_subsystems", "form_label", "Subsystems");
		$this->iDataSource->addColumnControl("client_subsystems", "links_table", "client_subsystems");
		$this->iDataSource->addColumnControl("client_subsystems", "control_table", "subsystems");
		$customFields = CustomField::getCustomFields("contacts");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);

			$dataType = $customField->getColumn()->getControlValue("data_type");
			switch ($dataType) {
				case "date":
					$fieldName = "date_data";
					break;
				case "bigint":
				case "int":
					$fieldName = "integer_data";
					break;
				case "decimal":
					$fieldName = "number_data";
					break;
				case "image":
				case "image_input":
				case "file":
				case "custom":
				case "custom_control":
					$fieldName = "";
					break;
				default:
					$fieldName = "text_data";
					break;
			}
			if (empty($fieldName)) {
				continue;
			}

			$this->iDataSource->addColumnControl($customField->getColumn()->getControlValue("column_name"), "select_value",
				"select " . $fieldName . " from custom_field_data where primary_identifier = contacts.contact_id and custom_field_id = " . $thisCustomField['custom_field_id']);
			$this->iDataSource->addColumnControl($customField->getColumn()->getControlValue("column_name"), "data_type", $dataType);
			$this->iDataSource->addColumnControl($customField->getColumn()->getControlValue("column_name"), "form_label", $customField->getColumn()->getControlValue("form_label"));
		}
	}

	function addCustomFieldsBeforeAddress() {
		$this->addCustomFields("contacts_before_address");
	}

	function addCustomFields($customFieldGroupCode = "") {
		$customFieldGroupCode = strtoupper($customFieldGroupCode);
		$customFields = CustomField::getCustomFields("contacts", $customFieldGroupCode);
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl();
		}
		$usedCustomFieldGroupCodes = array("contacts_before_address", "contacts_after_address");
		if (empty($customFieldGroupCode)) {
			$resultSet = executeQuery("select * from custom_field_groups where client_id = ? and inactive = 0 and custom_field_group_id in (select custom_field_group_id from custom_field_group_links " .
				"where custom_field_id in (select custom_field_id from custom_fields where custom_field_type_id = " .
				"(select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS'))) order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (!in_array(strtolower($row['custom_field_group_code']), $usedCustomFieldGroupCodes)) {
					echo "<h3>" . htmlText($row['description']) . "</h3>";
					$this->addCustomFields($row['custom_field_group_code']);
				}
			}
		}
	}

	function addCustomFieldsAfterAddress() {
		$this->addCustomFields("contacts_after_address");
	}

	function afterGetRecord(&$returnArray) {
		$returnValue = "<ul>";
		$resultSet = executeQuery("select * from users where client_id = ?" .
			($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and superuser_flag = 0") . " order by date_created desc",
			$returnArray['client_id']['data_value']);
		$count = 0;
		while ($row = getNextRow($resultSet)) {
			$returnValue .= "<li>" . getUserDisplayName($row['user_id']) . ", created " . date("m/d/Y", strtotime($row['date_created'])) . ($row['inactive'] ? " (inactive)" : "") . "</li>";
			$count++;
			if ($count > 100) {
				$returnValue .= "<li>Too many to display</li>";
				break;
			}
		}
		$returnValue .= "</ul>";
		$returnArray['client_users'] = array("data_value" => $returnValue);
		if (empty($returnArray['primary_id']['data_value'])) {
			$logoImageId = "";
		} else {
			$logoImageId = getFieldFromId("image_id", "images", "image_code", "HEADER_LOGO", "client_id = ?", $returnArray['primary_id']['data_value']);
		}
		$description = getFieldFromId("description", "images", "image_id", $logoImageId, "client_id is not null");
		$returnArray['select_values']["logo_image_id"] = array(array("key_value" => $logoImageId, "description" => $description));
		$returnArray['logo_image_id'] = array("data_value" => $logoImageId, "crc_value" => getCrcValue($logoImageId));
		$returnArray["logo_image_id_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray["logo_image_id_view"] = array("data_value" => (empty($logoImageId) ? "" : getImageFilename($logoImageId)));
		$returnArray["logo_image_id_filename"] = array("data_value" => getFieldFromId("filename", "images", "image_id", $logoImageId, "client_id is not null"));
		$returnArray["remove_logo_image_id"] = array("data_value" => "0", "crc_value" => getCrcValue("0"));

		$customFields = CustomField::getCustomFields("contacts");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldData = $customField->getRecord($returnArray['contact_id']['data_value']);
			if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldData)) {
				$returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'], $customFieldData['select_values']);
			}
			$returnArray = array_merge($returnArray, $customFieldData);
		}
		$returnArray["new_user_user_name"] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray["new_user_password"] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray["new_user_first_name"] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray["new_user_last_name"] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray["new_user_email_address"] = array("data_value" => "", "crc_value" => getCrcValue(""));
		$returnArray["new_user_administrator_flag"] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
		$returnArray["new_user_force_password_change"] = array("data_value" => "1", "crc_value" => getCrcValue("1"));
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		removeCachedData("client_subsystems", "client_subsystems");
		removeCachedData("client_subsystem_codes", "client_subsystem_codes");
        removeCachedData("client_row", $nameValues['primary_id'], true);
        removeCachedData("client_name", $nameValues['primary_id'], true);
		if (array_key_exists("logo_image_id_file", $_FILES) && !empty($_FILES['logo_image_id_file']['name'])) {
			$logoImageId = createImage("logo_image_id_file", array("maximum_width" => 500, "maximum_height" => 500, "image_code" => "HEADER_LOGO", "client_id" => $nameValues['primary_id']));
			if ($logoImageId === false) {
				return "Error processing image";
			}
		}
		$customFields = CustomField::getCustomFields("contacts");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldData = $nameValues;
			$customFieldData['primary_id'] = $nameValues['contact_id'];
			if (!$customField->saveData($customFieldData)) {
				return $customField->getErrorMessage();
			}
		}
		if (!empty($nameValues['new_user_user_name']) && !empty($nameValues['new_user_password']) &&
			!empty($nameValues['new_user_first_name']) && !empty($nameValues['new_user_last_name']) &&
			!empty($nameValues['new_user_email_address'])) {
			$resultSet = executeQuery("insert into contacts (client_id,first_name,last_name,email_address,country_id,date_created) values (?,?,?,?,1000,now())",
				$nameValues['primary_id'], $nameValues['new_user_first_name'], $nameValues['new_user_last_name'], $nameValues['new_user_email_address']);
			if (!empty($resultSet['sql_error'])) {
				return getSystemMessage("basic", $resultSet['sql_error']);
			}
			$contactId = $resultSet['insert_id'];
			$passwordSalt = getRandomString(64);
			$password = hash("sha256", $passwordSalt . $_POST['password']);
			$checkUserId = getFieldFromId("user_id", "users", "user_name", strtolower($nameValues['new_user_user_name']), "client_id = ? or superuser_flag = 1", $GLOBALS['gClientId']);
			if (!empty($checkUserId)) {
				return "User name is unavailable. Choose another";
			}
			$resultSet = executeQuery("insert into users (client_id,contact_id,user_name,password_salt,password,date_created,administrator_flag,force_password_change) values (?,?,?,?,?,now(),?,?)",
				$nameValues['primary_id'], $contactId, strtolower($nameValues['new_user_user_name']), $passwordSalt, $password, $nameValues['new_user_administrator_flag'], $nameValues['new_user_force_password_change']);
			if (!empty($resultSet['sql_error'])) {
				return getSystemMessage("basic", $resultSet['sql_error']);
			}
			$userId = $resultSet['insert_id'];
			$password = hash("sha256", $userId . $passwordSalt . $nameValues['new_user_password']);
			executeQuery("insert into user_passwords (user_id,password_salt,password) values (?,?,?)", $userId, $passwordSalt, $password);
			$resultSet = executeQuery("update users set password = ? where user_id = ?", $password, $userId);
			if (!empty($resultSet['sql_error'])) {
				return getSystemMessage("basic", $resultSet['sql_error']);
			}
		}
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#new_user_user_name").change(function () {
                const $userNameMessage = $("#_user_name_message");
                if (!empty($(this).val())) {
                    loadAjaxRequest("/checkusername.php?ajax=true&user_name=" + $(this).val(), function(returnArray) {
                        $userNameMessage.removeClass("info-message").removeClass("error-message");
                        if ("info_user_name_message" in returnArray) {
                            $userNameMessage.html(returnArray['info_user_name_message']).addClass("info-message");
                        }
                        if ("error_user_name_message" in returnArray) {
                            $userNameMessage.html(returnArray['error_user_name_message']).addClass("error-message");
                            $("#new_user_user_name").val("").focus();
                            setTimeout(function () {
                                $("#_edit_form").validationEngine("hideAll");
                            }, 10);
                        }
                    });
                } else {
                    $userNameMessage.val("");
                }
            });
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

$pageObject = new ClientMaintenancePage("clients");
$pageObject->displayPage();
