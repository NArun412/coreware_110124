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
    private $iDeveloperSubsystemAccess = false;

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "merge_selected":
				$selectedCount = 0;
				$resultSet = executeQuery("select count(*) from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $GLOBALS['gPageId']);
				if ($row = getNextRow($resultSet)) {
					$selectedCount = $row['count(*)'];
				}
				if ($selectedCount != 2) {
					$returnArray['error_message'] = "Exactly and only two contacts must be selected";
				}
				ajaxResponse($returnArray);
				break;
			case "reset_superusers":
				if (!$GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gClientId'] != $GLOBALS['gDefaultClientId']) {
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("update users set client_id = 1 where superuser_flag = 1");
				executeQuery("update contacts set client_id = 1 where contact_id in (select contact_id from users where superuser_flag = 1)");
				ajaxResponse($returnArray);
				break;
			case "enable_sso":
				$customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "CONTACTS");
				$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "SSO_LINK_USER", "custom_field_type_id = ?", $customFieldTypeId);
				if (empty($customFieldId)) {
					$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
						$GLOBALS['gClientId'], "SSO_LINK_USER", "Link User to SSO on next login", $customFieldTypeId, "Link User to SSO on next login");
					$customFieldId = $insertSet['insert_id'];
				}
                executeQuery("insert ignore into custom_field_controls (custom_field_id,control_name,control_value) values (?,?,?)", $customFieldId, "data_type", "tinyint");
                executeQuery("insert ignore into custom_field_data (primary_identifier,custom_field_id,text_data) select contact_id,?,'1' from contacts where email_address like '%coreware.com' and " .
                    "deleted = 0 and contact_id in (select contact_id from users where superuser_flag = 1 and inactive = 0)",$customFieldId);
				ajaxResponse($returnArray);
				break;
			case "add_user_group":
				$userGroupId = getFieldFromId("user_group_id", "user_groups", "user_group_id", $_GET['user_group_id']);
				$count = 0;
				if (!empty($userGroupId)) {
					$resultSet = executeQuery("select user_id from users where inactive = 0 and user_id in " .
						"(select primary_identifier from selected_rows where page_id = ? and user_id = ?) and " .
						"user_id not in (select user_id from user_group_members where user_group_id = ?)", $GLOBALS['gPageId'], $GLOBALS['gUserId'], $userGroupId);
					while ($row = getNextRow($resultSet)) {
						executeQuery("insert into user_group_members (user_id,user_group_id) values (?,?)", $row['user_id'], $userGroupId);
						$count++;
					}
				}
				$returnArray['info_message'] = $count . " user" . ($count == 1 ? "" : "s") . " added to user group '" . htmlText(getFieldFromId("description", "user_groups", "user_group_id", $userGroupId)) . "'";
				ajaxResponse($returnArray);
				break;
            case "create_developer":
                do { // check conditions in a loop to break out easily
                    if($this->iDeveloperSubsystemAccess < _READWRITE) {
                        $returnArray['error_message'] = "You do not have permission to create Developer records.";
                        break;
                    }
                    $contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id'], "deleted = 0 and contact_id in (select contact_id from users where inactive = 0)");
                    if (empty($contactId)) {
                        $returnArray['error_message'] = "Invalid contact for Developer record";
                        break;
                    }
                    $developerRow = getRowFromId("developers", "contact_id", $contactId);
                    if(!empty($developerRow)) {
                        $returnArray['error_message'] = "Developer record already exists";
                        break;
                    }
                    $GLOBALS['gPrimaryDatabase']->startTransaction();
                    $connectionKey = strtoupper(getRandomString());
                    $insertSet = executeQuery("insert into developers (contact_id, connection_key) values (?,?)", $contactId, $connectionKey);
                    if (!empty($insertSet['sql_error'])) {
                        $returnArray['error_message'] = $insertSet['sql_error'];
                        $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                        break;
                    }
                    $returnArray['connection_key'] = $connectionKey;
                    $returnArray['developer_id'] = $insertSet['insert_id'];
                    $apiMethodGroupId = getFieldFromId("api_method_group_id", "api_method_groups", "api_method_group_code", getPreference("DEVELOPER_DEFAULT_API_GROUP"));

                    if(!empty($apiMethodGroupId)) {
                        $insertSet = executeQuery("insert into developer_api_method_groups (developer_id, api_method_group_id) values (?,?)", $returnArray['developer_id'], $apiMethodGroupId);
                        if (!empty($insertSet['sql_error'])) {
                            $returnArray['error_message'] = $insertSet['sql_error'];
                            $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                            break;
                        }
                    }
                    $GLOBALS['gPrimaryDatabase']->commitTransaction();
                    $returnArray['info_message'] = "Developer record created successfully.";
                } while(false);
                ajaxResponse($returnArray);
                break;
		}
	}

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
			} elseif (count($parts) == 3) {
				$whereStatement = "(first_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[0] . "%") .
					" and middle_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[1] . "%") .
					" and last_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[2] . "%") . ")";
				foreach ($this->iSearchFields as $fieldName) {
					$whereStatement .= " or " . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText . "%");
				}
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
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("user_name", "administrator_flag", "users.date_created", "first_name", "last_name", "business_name", "email_address", "inactive", "last_login", "locked", "user_type_id"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeSearchColumn(array("password", "password_salt"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn(array("contacts.date_created"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));
			$this->iTemplateObject->getTableEditorObject()->addCustomAction("add_user_group", "Add Selected to Group");
			if ($GLOBALS['gUserRow']['superuser_flag'] && $GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId']) {
				$this->iTemplateObject->getTableEditorObject()->addCustomAction("reset_superusers", "Reset Superusers");
                if (strpos($GLOBALS['gUserRow']['email_address'],"coreware.com") !== false) {
	                $this->iTemplateObject->getTableEditorObject()->addCustomAction("enable_sso", "Enable SSO for Coreware");
                }
			}
			if (canAccessPageCode("DUPLICATEPROCESSING")) {
				$this->iTemplateObject->getTableEditorObject()->addCustomAction("merge_selected", "Merge Selected Contacts");
			}
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("activity" => array("icon" => "fad fa-file-medical-alt", "label" => getLanguageText("Activity"), "disabled" => false)));

			$filters = array();
			$filters['administrators'] = array("form_label" => "Show Only Administrators", "where" => "administrator_flag = 1", "data_type" => "tinyint", "conjunction" => "and", "set_default" => true);

			$userGroups = array();
			$resultSet = executeQuery("select * from user_groups where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$userGroups[$row['user_group_id']] = $row['description'];
			}
			if (!empty($userGroups)) {
				$filters['user_groups'] = array("form_label" => "User Group", "where" => "user_id in (select user_id from user_group_members where user_group_id = %key_value%)", "data_type" => "select", "choices" => $userGroups);
			}

			$userTypes = array();
			$resultSet = executeQuery("select * from user_types where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$userTypes[$row['user_type_id']] = $row['description'];
			}

			if (!empty($userTypes)) {
				$filters['user_types'] = array("form_label" => "User Type", "where" => "user_type_id = %key_value%", "data_type" => "select", "choices" => $userTypes);
			}

			if (!empty($GLOBALS['gUserRow']['full_client_access'])) {
				$filters['full_client_access'] = array("form_label" => "Full Client Access", "where" => "full_client_access = 1", "data_type" => "tinyint");
			}
			if (!empty($GLOBALS['gUserRow']['superuser_flag'])) {
				$filters['superuser_flag'] = array("form_label" => "Superuser", "where" => "superuser_flag = 1", "data_type" => "tinyint");
			}
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
        $this->iDeveloperSubsystemAccess = (in_array("CORE_DEVELOPER", $GLOBALS['gClientSubsystemCodes']) ? canAccessPageCode("DEVELOPERMAINT") : _NOACCESS);
	}

	function userGroupChoices($showInactive = false) {
		$userGroupChoices = array();
		$resultSet = executeQuery("select * from user_groups where client_id = ?" .
			(empty($GLOBALS['gUserRow']['superuser_flag']) && empty($GLOBALS['gUserRow']['full_client_access']) ?
				" and (restricted_access = 0 or (user_id is not null and user_id = " . $GLOBALS['gUserId'] . "))" : "") . " order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$userGroupChoices[$row['user_group_id']] = array("key_value" => $row['user_group_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1);
			}
		}
		freeResult($resultSet);
		return $userGroupChoices;
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("user_group_members", "get_choices", "userGroupChoices");

		$this->iDataSource->addColumnControl("user_access", "form_label", "Page Access");
		$this->iDataSource->addColumnControl("user_function_uses", "form_label", "User Functions");

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

		$this->iDataSource->addColumnControl("user_group_members", "data_type", "custom");
		$this->iDataSource->addColumnControl("user_group_members", "form_label", "Groups");
		$this->iDataSource->addColumnControl("user_group_members", "control_class", "MultipleDropdown");
		$this->iDataSource->addColumnControl("user_group_members", "links_table", "user_group_members");
		$this->iDataSource->addColumnControl("user_group_members", "control_table", "user_groups");

		$this->iDataSource->addColumnControl("user_subsystem_access", "data_type", "custom");
		$this->iDataSource->addColumnControl("user_subsystem_access", "form_label", "Subsystem Access");
		$this->iDataSource->addColumnControl("user_subsystem_access", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("user_subsystem_access", "list_table", "user_subsystem_access");
		$this->iDataSource->addColumnControl("user_subsystem_access", "list_table_controls",
			array("permission_level" => array("data_type" => "select", "choices" => array("" => "[None]", "0" => "No Access", "1" => "Read Only", "2" => "Write", "3" => "All"))));

        $this->iDataSource->addColumnControl("connection_key_display", "data_type", "varchar");
        $this->iDataSource->addColumnControl("connection_key_display", "readonly", true);
        $this->iDataSource->addColumnControl("connection_key_display", "form_label", "Connection Key");
        $this->iDataSource->addColumnControl("developer_id", "data_type", "hidden");
        if ($this->iDeveloperSubsystemAccess) {
            $this->iDataSource->addColumnControl("connection_key_display", "help_label", "Click to open Developer record");
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
		if (!$GLOBALS['gUserRow']['superuser_flag']) {
			$this->iDataSource->setFilterWhere("superuser_flag = 0");
		}
		if (!$GLOBALS['gUserRow']['full_client_access']) {
			$this->iDataSource->setFilterWhere("full_client_access = 0");
		}
	}

	function membershipFields() {
		$resultSet = executeQuery("select * from mailing_lists where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		if ($resultSet['row_count'] > 0) {
			?>
            <h3>Mailing Lists</h3>
            <table>
				<?php
				while ($row = getNextRow($resultSet)) {
					?>
                    <div class="basic-form-line" id="_mailing_list_id_<?= $row['mailing_list_id'] ?>_row">
                        <label for="mailing_list_id_<?= $row['mailing_list_id'] ?>"></label>
                        <input tabindex="10" class="" type="checkbox" value="1" name="mailing_list_id_<?= $row['mailing_list_id'] ?>" id="mailing_list_id_<?= $row['mailing_list_id'] ?>"><label class="checkbox-label" for="mailing_list_id_<?= $row['mailing_list_id'] ?>"><?= htmlText($row['description']) ?></label>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        <div class='clear-div'></div>
                    </div>
					<?php
				}
				?>
            </table>
			<?php
		}
		$resultSet = executeQuery("select count(*) from categories where inactive = 0 and (category_group_id is null or " .
			"category_group_id in (select category_group_id from category_groups where inactive = 0 and client_id = ?)) and " .
			"client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$rowCount = $row['count(*)'];
		} else {
			$rowCount = 0;
		}
		if ($rowCount > 0) {
			?>
            <h3>Categories</h3>
			<?php
		}
		$resultSet = executeQuery("select * from category_groups where inactive = 0 and choose_only_one = 1 and client_id = ? " .
			"order by sort_order,description", $GLOBALS['gClientId']);
		if ($resultSet['row_count'] > 0) {
			while ($row = getNextRow($resultSet)) {
				$thisColumn = new DataColumn("category_group_id_" . $row['category_group_id']);
				$thisColumn->setControlValue("data_type", "select");
				$thisColumn->setControlValue("form_label", $row['description']);
				$choices = array();
				$resultSet1 = executeQuery("select * from categories where inactive = 0 and category_group_id = ? order by sort_order,description", $row['category_group_id']);
				while ($row1 = getNextRow($resultSet1)) {
					$choices[$row1['category_id']] = array("key_value" => $row1['category_id'], "description" => $row1['description'], "inactive" => $row1['inactive'] == 1);
				}
				if (empty($choices)) {
					continue;
				}
				$thisColumn->setControlValue("choices", $choices);
				?>
                <div class="basic-form-line" id="_category_group_id_<?= $row['category_group_id'] ?>_row">
                    <label for="category_group_id_<?= $row['category_group_id'] ?>"><?= htmlText($row['description']) ?></label>
					<?= $thisColumn->getControl($this) ?>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    <div class='clear-div'></div>
                </div>
				<?php
			}
		}
		$resultSet = executeQuery("select * from category_groups where inactive = 0 and choose_only_one = 0 and client_id = ? " .
			"and category_group_id in (select category_group_id from categories where inactive = 0 and client_id = ?) " .
			"order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			?>
            <h3><?= htmlText($row['description']) ?></h3>
            <div class="category-table">
				<?php
				$resultSet1 = executeQuery("select * from categories where category_group_id = ? and inactive = 0 and " .
					"client_id = ?", $row['category_group_id'], $GLOBALS['gClientId']);
				while ($row1 = getNextRow($resultSet1)) {
					?>
                    <div class="basic-form-line">
                        <label></label>
                        <input tabindex="10" class="" type="checkbox" value="1" name="category_id_<?= $row1['category_id'] ?>" id="category_id_<?= $row1['category_id'] ?>"><label class="checkbox-label" for="category_id_<?= $row1['category_id'] ?>"><?= htmlText($row1['description']) ?></label>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        <div class='clear-div'></div>
                    </div>
					<?php
				}
				?>
            </div>
			<?php
		}
		$resultSet = executeQuery("select * from categories where category_group_id is null and inactive = 0 and client_id = ? " .
			"order by sort_order,description", $GLOBALS['gClientId']);
		if ($resultSet['row_count'] > 0) {
			?>
            <h3>General Categories</h3>
            <div class="category-table">
				<?php
				while ($row = getNextRow($resultSet)) {
					?>
                    <div class="basic-form-line">
                        <label></label>
                        <input tabindex="10" class="" type="checkbox" value="1" name="category_id_<?= $row['category_id'] ?>" id="category_id_<?= $row['category_id'] ?>"><label class="checkbox-label" for="category_id_<?= $row['category_id'] ?>"><?= htmlText($row['description']) ?></label>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        <div class='clear-div'></div>
                    </div>
					<?php
				}
				?>
            </div>
			<?php
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
			echo $customField->getControl(array("basic_form_line"=>true));
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

	function beforeSaveChanges(&$nameValues) {
		if (!empty($nameValues['superuser_flag'])) {
			$checkUserId = getFieldFromId("user_id", "users", "user_name", strtolower($nameValues['user_name']), "user_id <> ? and client_id is not null", $nameValues['primary_id']);
			if (!empty($checkUserId)) {
				return "This user cannot be a superuser because this username exists in other clients";
			}
			if (empty($nameValues['primary_id'])) {
				$contactId = getFieldFromId("contact_id", "contacts", "email_address", strtolower($nameValues['email_address']), "contact_id in (select contact_id from users where superuser_flag = 1 and client_id is not null and user_id <> ?)", $nameValues['primary_id']);
				if (!empty($contactId)) {
					return "This user already exists as a superuser";
				}
			}
		}
		if (!empty($nameValues['superuser_flag']) && empty($nameValues['primary_id'])) {
			$sendToAddresses = getNotificationEmails("ERROR_LOG", $GLOBALS['gDefaultClientId']);
			sendEmail(array("subject" => "SUPERUSER CREATED", "body" => "<p>Superuser account created by " . getUserDisplayName() . ", username " . $nameValues['user_name'] . ".</p>"));
		}
		if (empty($nameValues['primary_id'])) {
			$checkUserId = getFieldFromId("user_id", "users", "user_name", strtolower($nameValues['user_name']), "(client_id = ? or superuser_flag = 1)", $GLOBALS['gClientId']);
		} else {
			$checkUserId = getFieldFromId("user_id", "users", "user_name", strtolower($nameValues['user_name']), "user_id <> ? and (client_id = ? or superuser_flag = 1)", $nameValues['primary_id'], $GLOBALS['gClientId']);
		}
		if (!empty($checkUserId)) {
			return "User name is unavailable. Choose another";
		}
		$contactTypeId = getFieldFromId("contact_type_id", "contacts", "contact_id", $nameValues['contact_id']);
		if (empty($contactTypeId) && !empty($nameValues['user_type_id'])) {
			$nameValues['contact_type_id'] = getFieldFromId("contact_type_id", "user_types", "user_type_id", $nameValues['user_type_id']);
		}
		if (empty($nameValues['password'])) {
			unset($nameValues['password']);
		}
		$nameValues['time_locked_out'] = "";
		return true;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		removeCachedData("admin_menu", "*");
		removeCachedData("user_group_permission", "*");
		$contactId = getFieldFromId("contact_id","users","user_id",$nameValues['primary_id']);
		$customFields = CustomField::getCustomFields("contacts");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldValues = $nameValues;
			$customFieldValues['primary_id'] = $contactId;
			if (!$customField->saveData($customFieldValues)) {
				return $customField->getErrorMessage();
			}
		}
		$mailingLists = array();
		$categories = array();
		$resultSet = executeQuery("select * from mailing_lists where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$updateNecessary = false;
			$contactMailingListValues = array("contact_id" => $contactId, "mailing_list_id" => $row['mailing_list_id']);
			$contactSet = executeQuery("select * from contact_mailing_lists where contact_id = ? and mailing_list_id = ?",
				$contactId, $row['mailing_list_id']);
			if ($contactRow = getNextRow($contactSet)) {
				if (empty($nameValues['mailing_list_id_' . $row['mailing_list_id']])) {
					$contactMailingListValues['date_opted_out'] = date("m/d/Y");
				} else {
					$contactMailingListValues['date_opted_out'] = "";
				}
				$updateNecessary = true;
			} else {
				if (!empty($nameValues['mailing_list_id_' . $row['mailing_list_id']])) {
					$contactMailingListValues['date_opted_in'] = date("m/d/Y");
					$mailingLists[] = $row['mailing_list_id'];
					$updateNecessary = true;
				}
			}
			if ($updateNecessary) {
				$thisDataSource = new DataSource("contact_mailing_lists");
				$thisDataSource->setSaveOnlyPresent(true);
				$thisDataSource->saveRecord(array("name_values" => $contactMailingListValues, "primary_id" => $contactRow['contact_mailing_list_id']));
			}
		}
		$resultSet = executeQuery("select * from category_groups where inactive = 0 and choose_only_one = 1 and client_id = ? " .
			"order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			executeQuery("delete from contact_categories where category_id in (select category_id from categories where category_group_id = ?) and " .
				"contact_id = ?", $row['category_group_id'], $contactId);
			if (!empty($nameValues['category_group_id_' . $row['category_group_id']])) {
				$thisDataSource = new DataSource("contact_categories");
				$thisDataSource->saveRecord(array("name_values" => array("contact_id" => $contactId, "category_id" => $nameValues['category_group_id_' . $row['category_group_id']]), "primary_id" => ""));
				$categories[] = $nameValues['category_group_id_' . $row['category_group_id']];
			}
		}
		$resultSet = executeQuery("select * from categories where (category_group_id is null or category_group_id not in (select category_group_id from " .
			"category_groups where inactive = 0 and choose_only_one = 1 and client_id = ?)) and inactive = 0 and client_id = ?",
			$GLOBALS['gClientId'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$resultSet1 = executeQuery("select * from contact_categories where category_id = ? and contact_id = ?",
				$row['category_id'], $contactId);
			if ($row1 = getNextRow($resultSet1)) {
				if (empty($nameValues['category_id_' . $row['category_id']])) {
					$thisDataSource = new DataSource("contact_categories");
					$thisDataSource->deleteRecord(array("primary_id" => $row1['contact_category_id']));
				}
			} else {
				if (!empty($nameValues['category_id_' . $row['category_id']])) {
					$thisDataSource = new DataSource("contact_categories");
					$thisDataSource->saveRecord(array("name_values" => array("contact_id" => $contactId, "category_id" => $row['category_id']), "primary_id" => ""));
					$categories[] = $row['category_id'];
				}
			}
		}
		if (!empty($nameValues['password'])) {
			$passwordSalt = getRandomString(64);
			$password = hash("sha256", $nameValues['primary_id'] . $passwordSalt . $nameValues['password']);
			$resultSet = executeQuery("update users set password = ?,password_salt = ?,last_password_change = now() where user_id = ?", $password, $passwordSalt, $nameValues['primary_id']);
		}
		$resultSet = executeQuery("delete from security_log where user_name = ? and security_log_type like '%LOGIN-FAILED' and entry_time > (now() - interval 30 minute)", $nameValues['user_name']);
		return true;
	}

	function afterGetRecord(&$returnArray) {
		if (!empty($returnArray['superuser_flag']['data_value'])) {
			$resultSet = executeQuery("select * from users where user_name = ? and user_id <> ?", $returnArray['user_name']['data_value'], $returnArray['primary_id']['data_value']);
			while ($row = getNextRow($resultSet)) {
				executeQuery("update users set user_name = ? where user_id = ?", $row['user_name'] . "_" . strtolower(getRandomString(4)), $row['user_id']);
			}
		}
		$returnArray['simulate_user_wrapper'] = array('data_value' => "");
		if (canAccessPageCode("SIMULATEUSER")) {
			$allowAdministratorLogin = getPreference("allow_administrator_login");
			if ($GLOBALS['gUserRow']['superuser_flag'] || $allowAdministratorLogin) {
				$query = ($GLOBALS['gUserRow']['superuser_flag'] ? "" : "superuser_flag = 0" .
					(empty($GLOBALS['gUserRow']['full_client_access']) ? " and full_client_access = 0 and administrator_flag = 0" : ""));
				$resultSet = executeQuery("select * from users where user_id = ? and inactive = 0 and client_id = ?" .
					(empty($query) ? "" : " and " . $query), $returnArray['primary_id']['data_value'], $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['simulate_user_wrapper']['data_value'] = "<button id='simulate_user'>Simulate User</button>";
				}
			}
		}
		$returnArray['user_id_display'] = array("data_value" => $returnArray['primary_id']['data_value']);
		$returnArray['contact_id_display'] = array("data_value" => $returnArray['contact_id']['data_value']);
		$customFields = CustomField::getCustomFields("contacts");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldData = $customField->getRecord($returnArray['contact_id']['data_value']);
			if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldData)) {
				$returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'], $customFieldData['select_values']);
			}
			$returnArray = array_merge($returnArray, $customFieldData);
		}
		$resultSet = executeQuery("select * from mailing_lists where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$contactSet = executeQuery("select * from contact_mailing_lists where contact_id = ? and mailing_list_id = ? and date_opted_out is null",
				$returnArray['contact_id']['data_value'], $row['mailing_list_id']);
			if ($contactRow = getNextRow($contactSet)) {
				$returnArray['mailing_list_id_' . $row['mailing_list_id']] = array("data_value" => "1", "crc_value" => getCrcValue("1"));
			} else {
				$returnArray['mailing_list_id_' . $row['mailing_list_id']] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
			}
		}
		$resultSet = executeQuery("select * from category_groups where inactive = 0 and choose_only_one = 1 and client_id = ? " .
			"order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$resultSet1 = executeQuery("select * from contact_categories where category_id in (select category_id from categories where " .
				"category_group_id = ?) and contact_id = ?", $row['category_group_id'], $returnArray['contact_id']['data_value']);
			if ($row1 = getNextRow($resultSet1)) {
				$returnArray['category_group_id_' . $row['category_group_id']] = array("data_value" => $row1['category_id'], "crc_value" => getCrcValue($row1['category_id']));
			} else {
				$returnArray['category_group_id_' . $row['category_group_id']] = array("data_value" => "", "crc_value" => getCrcValue(""));
			}
		}
		$resultSet = executeQuery("select * from categories where (category_group_id is null or category_group_id not in (select category_group_id from " .
			"category_groups where inactive = 0 and choose_only_one = 1 and client_id = ?)) and inactive = 0 and client_id = ?",
			$GLOBALS['gClientId'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$resultSet1 = executeQuery("select * from contact_categories where category_id = ? and contact_id = ?",
				$row['category_id'], $returnArray['contact_id']['data_value']);
			if ($row1 = getNextRow($resultSet1)) {
				$returnArray['category_id_' . $row['category_id']] = array("data_value" => "1", "crc_value" => getCrcValue("1"));
			} else {
				$returnArray['category_id_' . $row['category_id']] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
			}
		}
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
        if ($this->iDeveloperSubsystemAccess) {
            $developerRow = getRowFromId("developers", "contact_id", $returnArray['contact_id']['data_value']);
            $returnArray['developer_id'] = array("data_value" => $developerRow['developer_id']);
            $returnArray['connection_key_display'] = array("data_value" => $developerRow['connection_key']);
        }
	}

	function internalCSS() {
		?>
        <style>
            #simulate_user_wrapper {
                margin-top: 10px;
            }

            #simulate_user_wrapper button {
                margin: 0;
            }

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

            #connection_key_display {
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
            <?php
            if ($this->iDeveloperSubsystemAccess) {
            ?>
            $("#connection_key_display").click(function () {
                window.open("/developermaintenance.php?clear_filter=true&url_page=show&primary_id=" + $("#developer_id").val());
            });
            $(document).on("click", "#create_developer", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_developer&contact_id=" + $("#contact_id").val(), function(returnArray) {
                    if("connection_key" in returnArray) {
                        $("#connection_key_display").val(returnArray['connection_key']);
                        $("#developer_id").val(returnArray['developer_id']);
                        $("#_security_developer").removeClass("hidden");
                        $("#_security_create_developer").addClass("hidden");
                    }
                });
                return false;
            });
            <?php } ?>
            $(document).on("click", "#simulate_user", function () {
                goToLink("/simulateuser.php?url_action=simulate_user&user_id=" + $("#primary_id").val());
                return false;
            });
            $(document).on("change", "#password", function () {
                $("#force_password_change").prop("checked", true);
            });
            $(document).on("tap click", "#_activity_button", function () {
                loadAjaxRequest("/useractivitylog.php?ajax=true&user_id=" + $("#primary_id").val(), function(returnArray) {
                    if ("activity_log" in returnArray) {
                        $("#_activity_log").html(returnArray['activity_log']);
                    } else {
                        $("#_activity_log").html("<p>No Activity Found</p>");
                    }
                    $('#_activity_log').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'User Activity',
                        buttons: {
                            Close: function (event) {
                                $("#_activity_log").dialog('close');
                            }
                        }
                    });
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
            function customActions(actionName) {
				<?php if (canAccessPageCode("DUPLICATEPROCESSING")) { ?>
                if (actionName === "merge_selected") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=merge_selected", function(returnArray) {
                        if (!("error_message" in returnArray)) {
                            goToLink("/duplicateprocessing.php?selected=true&return=users");
                        }
                    });
                    return true;
                }
				<?php } ?>
				<?php if ($GLOBALS['gUserRow']['superuser_flag'] && $GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId']) { ?>
                if (actionName === "reset_superusers") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=reset_superusers", function(returnArray) {
                        getDataList();
                    });
                }
                if (actionName === "enable_sso") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=enable_sso", function(returnArray) {
                        getDataList();
                    });
                }
				<?php } ?>
                if (actionName === "add_user_group") {
                    $('#user_group_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        width: 600,
                        title: 'Choose User Group',
                        buttons: {
                            Save: function (event) {
                                if (!empty($("#user_group_id").val())) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=add_user_group&user_group_id=" + $("#user_group_id").val());
                                }
                                $("#user_group_dialog").dialog('close');
                            },
                            Cancel: function (event) {
                                $("#user_group_dialog").dialog('close');
                            }
                        }
                    });
                    return true;
                }
                return false;
            }

            function afterGetRecord(returnArray) {
                $("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() === "1000" ? "9999" : "10"));
                $("#city_select").hide();
                $("#city").show();
                if(returnArray['password']['data_value'] == 'sso') {
                    $("#_security_non_sso").addClass("hidden").find("input").prop("disabled", true);
                    $("#_security_sso").removeClass("hidden");
                    <?php if(getPreference("SSO_MAKE_USER_CONTACT_INFO_READONLY")) {?>
                    $("#user_name").prop("readonly", true);
                    $("#email_address").prop("readonly", true);
                    $("#first_name").prop("readonly", true);
                    $("#last_name").prop("readonly", true);
                    <?php } ?>
                } else {
                    $("#_security_non_sso").removeClass("hidden").find("input").prop("disabled", false);
                    $("#_security_sso").addClass("hidden");
                    <?php if(getPreference("SSO_MAKE_USER_CONTACT_INFO_READONLY")) {?>
                    $("#user_name").prop("readonly", false);
                    $("#email_address").prop("readonly", false);
                    $("#first_name").prop("readonly", false);
                    $("#last_name").prop("readonly", false);
                    <?php } ?>
                }
                if(empty(returnArray['connection_key_display']['data_value'])) {
                    $("#_security_developer").addClass("hidden");
                    $("#_security_create_developer").removeClass("hidden");
                } else {
                    $("#_security_developer").removeClass("hidden");
                    $("#_security_create_developer").addClass("hidden");
                }
            }
        </script>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="user_group_dialog" class="dialog-box">
            <div class="basic-form-line" id="_user_group_id_row">
                <label>User Group</label>
                <select id="user_group_id" name="user_group_id">
                    <option value="">[Select]</option>
					<?php
					$resultSet = executeQuery("select * from user_groups where client_id = ? and inactive = 0" .
						(empty($GLOBALS['gUserRow']['superuser_flag']) && empty($GLOBALS['gUserRow']['full_client_access']) ?
							" and (restricted_access = 0 or (user_id is not null and user_id = " . $GLOBALS['gUserId'] . "))" : ""), $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['user_group_id'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
        </div>

        <div id="_activity_log" class="dialog-box">
        </div>
		<?php
	}
}

$pageObject = new UserMaintenancePage("users");
$pageObject->displayPage();
