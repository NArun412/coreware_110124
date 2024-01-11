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

$GLOBALS['gPageCode'] = "USERMAINT";
require_once "shared/startup.inc";

class UserMaintenancePage extends Page {
	var $iSearchFields = array("user_name", "first_name", "last_name", "business_name", "email_address");
	var $iUserGroupId = false;

	function filterTextProcessing($filterText) {
		if (!empty($filterText)) {
			$parts = explode(" ", $filterText);
			if (count($parts) == 2) {
				$whereStatement = "(first_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[0] . "%") .
					" and last_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[1] . "%") . ")";
				foreach ($this->iSearchFields as $fieldName) {
					$whereStatement .= " or " . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText . "%");
				}
				$this->iDataSource->addFilterWhere($whereStatement);
			} else {
				if (is_numeric($filterText) && strlen($filterText) == 10) {
					$whereStatement = "users.contact_id in (select contact_id from phone_numbers where phone_number = " . $GLOBALS['gPrimaryDatabase']->makeParameter(formatPhoneNumber($filterText)) . ")";
					$this->iDataSource->addFilterWhere($whereStatement);
				} else if (is_numeric($filterText)) {
					$whereStatement = "exists (select contact_id from contact_redirect where client_id = " . $GLOBALS['gClientId'] .
						" and retired_contact_identifier = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . " and contact_id = users.contact_id) or users.contact_id = " .
						$GLOBALS['gPrimaryDatabase']->makeNumberParameter($filterText) . " or users.user_id = " . $GLOBALS['gPrimaryDatabase']->makeNumberParameter($filterText);
					foreach ($this->iSearchFields as $fieldName) {
						$whereStatement .= " or " . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText . "%");
					}
					$this->iDataSource->addFilterWhere($whereStatement);
				} else {
					$this->iDataSource->setFilterText($filterText);
				}
			}
		}
	}

	function setup() {
        $userGroupCode = $this->getPageTextChunk("user_group_code");
        if (empty($userGroupCode)) {
            $userGroupCode = "COREFIRE_ADMIN";
        }
		$this->iUserGroupId = getFieldFromId("user_group_id", "user_groups", "user_group_code", $userGroupCode);
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("user_name", "administrator_flag", "users.date_created", "first_name", "last_name", "business_name", "email_address", "inactive", "last_login", "locked"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeSearchColumn(array("password", "password_salt"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn(array("contacts.date_created"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));

			$filters = array();
			$filters['administrators'] = array("form_label" => "Show Only Administrators", "where" => "administrator_flag = 1", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);

			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("user_id_display", "data_type", "int");
		$this->iDataSource->addColumnControl("user_id_display", "readonly", true);
		$this->iDataSource->addColumnControl("user_id_display", "form_label", "User ID");
		$this->iDataSource->addColumnControl("user_id_display", "help_label", "&nbsp;");

		$this->iDataSource->addColumnControl("contact_id_display", "data_type", "int");
		$this->iDataSource->addColumnControl("contact_id_display", "readonly", true);
		$this->iDataSource->addColumnControl("contact_id_display", "form_label", "Contact ID");
		if (canAccessPageCode("CONTACTMAINT")) {
			$this->iDataSource->addColumnControl("contact_id_display", "help_label", "Click to open Contact record");
		}

		$this->iDataSource->addColumnControl("user_name", "classes", "allow-dash");
		$this->iDataSource->addColumnControl("last_login_location", "data_type", "varchar");
		$this->iDataSource->addColumnControl("last_login_location", "readonly", "true");
		$this->iDataSource->addColumnControl("last_login_location", "classes", "borderless");
		$this->iDataSource->addColumnControl("last_login_location", "size", "25");
		$this->iDataSource->addColumnControl("last_login_location", "form_label", "From");
		$this->iDataSource->getPrimaryTable()->setSubtables("user_menus,user_preferences");
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
		$this->iDataSource->setFilterWhere("superuser_flag = 0 and full_client_access = 0");

		$this->iDataSource->addColumnControl("city", "form_label", "City, St Zip");
		$this->iDataSource->addColumnControl("city", "size", "30");
		$this->iDataSource->addColumnControl("city_select", "data_type", "select");
		$this->iDataSource->addColumnControl("country_id", "default_value", "1000");
		$this->iDataSource->addColumnControl("email_address", "not_null", "true");
		$this->iDataSource->addColumnControl("last_login", "readonly", "true");
		$this->iDataSource->addColumnControl("last_password_change", "readonly", "true");
		$this->iDataSource->addColumnControl("password", "data-conditional-required", "$(\"#primary_id\").val() == \"\"");
		$this->iDataSource->addColumnControl("password_salt", "default_value", "93489jf988g7890w7eyqw089e7");
		$this->iDataSource->addColumnControl("phone_numbers", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("phone_numbers", "data_type", "custom");
		$this->iDataSource->addColumnControl("phone_numbers", "foreign_key_field", "contact_id");
		$this->iDataSource->addColumnControl("phone_numbers", "form_label", "Phone");
		$this->iDataSource->addColumnControl("phone_numbers", "list_table", "phone_numbers");
		$this->iDataSource->addColumnControl("phone_numbers", "list_table_controls", array("description" => array("size" => "15"), "phone_number" => array("size" => "20")));
		$this->iDataSource->addColumnControl("phone_numbers", "primary_key_field", "contact_id");
		$this->iDataSource->addColumnControl("postal_code", "data-city_hide", "city");
		$this->iDataSource->addColumnControl("postal_code", "data-city_select_hide", "city_select");
		$this->iDataSource->addColumnControl("state", "size", "10");
		$this->iDataSource->addColumnControl("users.date_created", "default_value", date("m/d/Y"));
		$this->iDataSource->addColumnControl("users.date_created", "readonly", "true");
	}

	function pageAccess() {
		if (!empty($this->iUserGroupId)) {
			$pages = userGroupPages($this->iUserGroupId);
			$sortedPages = array();
			foreach ($pages as $pageId => $permissionLevel) {
				if (empty($permissionLevel)) {
					continue;
				}
				$sortedPages[$pageId] = getFieldFromId("description", "pages", "page_id", $pageId,"client_id is not null");
			}
			asort($sortedPages);
			foreach ($sortedPages as $pageId => $description) {
				?>
                <div class='basic-form-line'>
                    <input type='checkbox' name='page_id_<?= $pageId ?>' id='page_id_<?= $pageId ?>' value='1'><label class='checkbox-label' for='page_id_<?= $pageId ?>'><?= htmlText($description) ?></label>
                </div>
				<?php
			}
		}
	}

	function beforeSaveChanges(&$nameValues) {
		if (empty($nameValues['primary_id'])) {
			$checkUserId = getFieldFromId("user_id", "users", "user_name", strtolower($nameValues['user_name']), "(client_id = ? or superuser_flag = 1)", $GLOBALS['gClientId']);
		} else {
			$checkUserId = getFieldFromId("user_id", "users", "user_name", strtolower($nameValues['user_name']), "user_id <> ? and (client_id = ? or superuser_flag = 1)", $nameValues['primary_id'], $GLOBALS['gClientId']);
		}
		if (!empty($checkUserId)) {
			return "User name is unavailable. Choose another";
		}
		if (empty($nameValues['password'])) {
			unset($nameValues['password']);
		}
		$nameValues['time_locked_out'] = "";
		return true;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		removeCachedData("admin_menu", "*");
		$contactId = Contact::getUserContactId($nameValues['primary_id']);
		if (!empty($nameValues['password'])) {
			$passwordSalt = getRandomString(64);
			$password = hash("sha256", $nameValues['primary_id'] . $passwordSalt . $nameValues['password']);
			$resultSet = executeQuery("update users set password = ?,password_salt = ?,last_password_change = now() where user_id = ?", $password, $passwordSalt, $nameValues['primary_id']);
		}
		$resultSet = executeQuery("delete from security_log where user_name = ? and security_log_type like '%LOGIN-FAILED' and entry_time > (now() - interval 30 minute)", $nameValues['user_name']);

		if (!empty($this->iUserGroupId)) {
			executeQuery("insert ignore into user_group_members (user_id,user_group_id) values (?,?)", $nameValues['primary_id'], $this->iUserGroupId);
		}
		$pages = userGroupPages($this->iUserGroupId);
		foreach ($pages as $pageId => $permissionLevel) {
			executeQuery("delete from user_access where user_id = ? and page_id = ?",$nameValues['primary_id'],$pageId);
		    if (empty($permissionLevel)) {
		        continue;
		    }
		    if (!empty($nameValues['page_id_' . $pageId]) && !empty($nameValues['administrator_flag'])) {
			    executeQuery("insert into user_access (user_id,page_id,permission_level) values (?,?,?)", $nameValues['primary_id'], $pageId, $permissionLevel);
		    }
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
		if (!empty($this->iUserGroupId)) {
			$pages = userGroupPages($this->iUserGroupId);
			foreach ($pages as $pageId => $permissionLevel) {
				if (empty($permissionLevel)) {
					continue;
				}
				$userAccessId = getFieldFromId("user_access_id", "user_access", "user_id", $returnArray['primary_id']['data_value'], "page_id = ? and permission_level > 0", $pageId);
				$returnArray['page_id_' . $pageId] = array("data_value" => (empty($userAccessId) ? "0" : "1"), "crc_value" => getCrcValue((empty($userAccessId) ? "0" : "1")));
			}
		}
		$returnArray['user_id_display'] = array("data_value" => $returnArray['primary_id']['data_value']);
		$returnArray['contact_id_display'] = array("data_value" => $returnArray['contact_id']['data_value']);
		$resultSet = executeQuery("select * from security_log where user_name = ? and user_name is not null and log_entry = 'Log In succeeded' order by entry_time desc limit 1", $returnArray['user_name']['data_value']);
		if ($row = getNextRow($resultSet)) {
			$lastLoginLocation = $row['ip_address'];
		} else {
			$lastLoginLocation = "Unknown";
		}
		$returnArray['last_login_location'] = array("data_value" => $lastLoginLocation);
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
                width: 55%;
                float: left;
                margin-top: 10px;
            }

            #_contact_right_column {
                width: 44%;
                margin-left: 55%;
                margin-top: 10px;
            }

            #id_wrapper {
                display: flex;
            }

            #id_wrapper div {
                flex: 0 0 auto;
                margin-right: 40px;
            }

            #contact_id_display {
                cursor: pointer;
            }

            @media only screen and (max-width: 850px) {
                #_contact_left_column {
                    width: 100%;
                    float: none;
                }

                #_contact_right_column {
                    width: 100%;
                    margin-left: 0;
                }
            }
        </style>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
			<?php
			if (canAccessPageCode("CONTACTMAINT")) {
			?>
            $("#contact_id_display").click(function () {
                window.open("/contactmaintenance.php?clear_filter=true&url_page=show&primary_id=" + $(this).val());
            });
			<?php } ?>
            $(document).on("change", "#password", function () {
                $("#force_password_change").prop("checked", true);
            });
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
            $(document).on("click", "#administrator_flag", function () {
                if ($("#administrator_flag").prop("checked")) {
                    $("#page_access_checkboxes").removeClass("hidden");
                    $("#non_admin_access").addClass("hidden");
                } else {
                    $("#page_access_checkboxes").addClass("hidden");
                    $("#non_admin_access").removeClass("hidden");
                }
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
                $("#city_select").hide();
                $("#city").show();
                if ($("#administrator_flag").prop("checked")) {
                    $("#page_access_checkboxes").removeClass("hidden");
                    $("#non_admin_access").addClass("hidden");
                } else {
                    $("#page_access_checkboxes").addClass("hidden");
                    $("#non_admin_access").removeClass("hidden");
                }
                if(returnArray['password']['data_value'] == 'sso') {
                    $("#_password_row").html("This user's login is handled by Single Sign-On (SSO).")
                }
            }
        </script>
		<?php
	}
}

$pageObject = new UserMaintenancePage("users");
$pageObject->displayPage();
