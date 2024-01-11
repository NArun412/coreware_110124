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

$GLOBALS['gPageCode'] = "SAFEVISITORSETUP";
$GLOBALS['gDefaultAjaxTimeout'] = 3600000;
require_once "shared/startup.inc";

class SafeVisitorSetupPage extends Page {
	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "test_credentials":
				$safeVisitor = new SafeVisitor($_POST['username'], $_POST['password']);
				$result = $safeVisitor->getGroups();
				if (!empty($result)) {
					$returnArray['info_message'] = "Credentials work";
				} else {
					$returnArray['error_message'] = "Credentials do NOT work " . $safeVisitor->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "setup_fields":
				$contactsCustomFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "CONTACTS");
				$backgroundCheckGroupField = CustomField::getCustomFieldByCode("BACKGROUND_CHECK_GROUP");
				if (empty($backgroundCheckGroupField)) {
					$resultSet = executeQuery("insert into custom_fields (client_id, custom_field_code, description, custom_field_type_id, form_label) " .
						"values (?,'BACKGROUND_CHECK_GROUP', 'Background Check Group',?, 'Background Check Group')", $GLOBALS['gClientId'], $contactsCustomFieldTypeId);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : "<br>") . $resultSet['sql_error'];
					} else {
						$backgroundCheckGroupFieldId = $resultSet['insert_id'];
						$myAccountGroupId = getFieldFromId("custom_field_group_id", "custom_field_groups", "custom_field_group_code", "MY_ACCOUNT");
						executeQuery("insert into custom_field_controls (custom_field_id, control_name, control_value) " .
							"values (?,'not_null','true'), (?,'data_type','select')", $backgroundCheckGroupFieldId, $backgroundCheckGroupFieldId);
						executeQuery("insert into custom_field_group_links (custom_field_group_id, custom_field_id) values (?,?)", $myAccountGroupId, $backgroundCheckGroupField);
					}
				}
				$backgroundCheckStatusField = CustomField::getCustomFieldByCode("BACKGROUND_CHECK_STATUS");
				if (empty($backgroundCheckStatusField)) {
					$resultSet = executeQuery("insert into custom_fields (client_id, custom_field_code, description, custom_field_type_id, form_label, internal_use_only) " .
						"values (?,'BACKGROUND_CHECK_STATUS', 'Background Check Status', ?, 'Background Check Status', 1)", $GLOBALS['gClientId'], $contactsCustomFieldTypeId);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : "<br>") . $resultSet['sql_error'];
					} else {
						$backgroundCheckStatusFieldId = $resultSet['insert_id'];
						executeQuery("insert into custom_field_controls (custom_field_id, control_name, control_value) " .
							"values (?,'data_type','varchar')", $backgroundCheckStatusFieldId);
					}
				}
				$backgroundCheckStatusField = CustomField::getCustomFieldByCode("BACKGROUND_CHECK_EXPIRATION");
				if (empty($backgroundCheckStatusField)) {
					$resultSet = executeQuery("insert into custom_fields (client_id, custom_field_code, description, custom_field_type_id, form_label, internal_use_only) " .
						"values (?,'BACKGROUND_CHECK_EXPIRATION', 'Background Check Expiration', ?, 'Background Check Expiration', 1)", $GLOBALS['gClientId'], $contactsCustomFieldTypeId);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : "<br>") . $resultSet['sql_error'];
					} else {
						$backgroundCheckStatusFieldId = $resultSet['insert_id'];
						executeQuery("insert into custom_field_controls (custom_field_id, control_name, control_value) " .
							"values (?,'data_type','varchar')", $backgroundCheckStatusFieldId);
					}
				}
				$customerIdIdentifierTypeId = getFieldFromId("contact_identifier_type_id", "contact_identifier_types", "contact_identifier_type_code",
					"CUSTOMER_ID");
				if (empty($customerIdIdentifierTypeId)) {
					$resultSet = executeQuery("insert into contact_identifier_types (client_id, contact_identifier_type_code, description, autogenerate) values (?,?,?,?)",
						$GLOBALS['gClientId'], "CUSTOMER_ID", "Customer ID", 1);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : "<br>") . $resultSet['sql_error'];
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_groups":
				$groups = array();
				$safeVisitorConfig = Page::getClientPagePreferences("SAFEVISITOR");
				foreach ($safeVisitorConfig['groups'] as $thisGroup) {
					$rowValues = array();
					$rowValues['description'] = array("data_value" => $thisGroup['description'], "crc_value" => getCrcValue($thisGroup['description']));
					$rowValues['product_id'] = array("data_value" => $thisGroup['product_id'], "crc_value" => getCrcValue($thisGroup['product_id']));
					$rowValues['is_default'] = array("data_value" => $thisGroup['is_default'], "crc_value" => getCrcValue($thisGroup['is_default']));
                    $rowValues['public_access'] = array("data_value" => $thisGroup['public_access'], "crc_value" => getCrcValue($thisGroup['public_access']));
					$rowValues['group_name'] = array("data_value" => $thisGroup['group_name'], "crc_value" => getCrcValue($thisGroup['group_name']));
					$rowValues['application_url'] = array("data_value" => $thisGroup['application_url'], "crc_value" => getCrcValue($thisGroup['application_url']));
					$groups[] = $rowValues;
				}
				$safeVisitor = new SafeVisitor($safeVisitorConfig['username'], $safeVisitorConfig['password']);
				$safeVisitorGroups = $safeVisitor->getGroups();
				foreach ($safeVisitorGroups as $thisSafeVisitorGroup) {
					$found = false;
					foreach ($groups as $thisGroup) {
						if (strtolower($thisGroup['group_name']['data_value']) == strtolower($thisSafeVisitorGroup['name'])) {
							$found = true;
							break;
						}
					}
					if (!$found) {
						$rowValues = array();
						$rowValues['description'] = array("data_value" => $thisSafeVisitorGroup['name'], "crc_value" => getCrcValue($thisSafeVisitorGroup['name']));
						$rowValues['product_id'] = array("data_value" => 0, "crc_value" => getCrcValue(0));
						$rowValues['is_default'] = array("data_value" => 0, "crc_value" => getCrcValue(0));
                        $rowValues['public_access'] = array("data_value" => 0, "crc_value" => getCrcValue(0));
						$rowValues['group_name'] = array("data_value" => $thisSafeVisitorGroup['name'], "crc_value" => getCrcValue($thisSafeVisitorGroup['name']));
						$rowValues['application_url'] = array("data_value" => $thisSafeVisitorGroup['applicationUrl'], "crc_value" => getCrcValue($thisSafeVisitorGroup['applicationUrl']));
						$groups[] = $rowValues;
					}
				}

				$returnArray['groups'] = $groups;
				ajaxResponse($returnArray);
				break;
			case "save_config":
                $safeVisitorConfig = Page::getClientPagePreferences("SAFEVISITOR");
                if(!empty($_POST['password'])) {
                    $safeVisitor = new SafeVisitor($_POST['username'], $_POST['password']);
                    $result = $safeVisitor->getGroups();
                    if (empty($result)) {
                        $returnArray['error_message'] = "Credentials do NOT work " . $safeVisitor->getErrorMessage();
                        ajaxResponse($returnArray);
                        break;
                    }
                    $safeVisitorConfig["username"] = $_POST['username'];
                    $safeVisitorConfig["password"] = $_POST['password'];
                } else {
                    $safeVisitor = new SafeVisitor($safeVisitorConfig['username'], $safeVisitorConfig['password']);
                    $result = $safeVisitor->getGroups();
                }
				$safeVisitorGroups = array();
				foreach ($result as $thisResult) {
					$safeVisitorGroups[$thisResult['name']] = $thisResult['applicationUrl'];
				}
				$groups = array();
				$index = 1;
				while (array_key_exists("background_check_groups_description-" . $index, $_POST)) {
					$groups[] = array(
						"description" => $_POST['background_check_groups_description-' . $index],
						"product_id" => $_POST['background_check_groups_product_id-' . $index],
						"is_default" => $_POST['background_check_groups_is_default-' . $index],
                        "public_access" => $_POST['background_check_groups_public_access-' . $index],
						"group_name" => $_POST['background_check_groups_group_name-' . $index],
						"application_url" => $safeVisitorGroups[$_POST['background_check_groups_group_name-' . $index]]
					);
					$index++;
				}
				$safeVisitorConfig['groups'] = $groups;
				$safeVisitorConfig['test_mode'] = !empty($_POST['test_mode']);
				Page::setClientPagePreferences($safeVisitorConfig, "SAFEVISITOR");
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		echo $this->iPageData['content'];

		$safeVisitorConfig = Page::getClientPagePreferences("SAFEVISITOR");
		?>
        <h2>SafeVisitor Configuration</h2>
        <div id="setup_contents">
            <form id="_setup_form">

                <div class="form-line">
                    <label>SafeVisitor Username</label>
                    <input tabindex="10" type="text" size="12" id="username" name="username" value="<?= $safeVisitorConfig['username'] ?>">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line">
                    <label>SafeVisitor Password</label>
                    <input tabindex="10" type="<?= $GLOBALS['gUserRow']['superuser_flag'] ? "text" : "password" ?>" autocomplete="off" autocomplete="chrome-off" size="12" id="password" name="password" value="">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line">
                    <button id="test_credentials">Test Credentials</button>
                    <div class='clear-div'></div>
                </div>

                <div id="_test_credentials"></div>

                <div class="form-line">
					<?php
					$backgroundCheckGroupField = CustomField::getCustomFieldByCode("BACKGROUND_CHECK_GROUP");
					$backgroundCheckStatusField = CustomField::getCustomFieldByCode("BACKGROUND_CHECK_STATUS");
					$backgroundCheckExpirationField = CustomField::getCustomFieldByCode("BACKGROUND_CHECK_EXPIRATION");
					$customerIdIdentifierTypeId = getFieldFromId("contact_identifier_type_id", "contact_identifier_types", "contact_identifier_type_code",
						"CUSTOMER_ID");
					if (empty($backgroundCheckGroupField) || empty($backgroundCheckStatusField) || empty($backgroundCheckExpirationField) || empty($customerIdIdentifierTypeId)) {
						?>
                        <button id="setup_fields">Set Up Fields</button>
                        <div id="_setup_fields"></div>
					<?php } else { ?>
                        <p>Background check fields are properly set up</p>
					<?php } ?>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line">
					<?php
					$groupsControl = $this->getBackgroundCheckGroupsControl();
					echo $groupsControl->getControl();
					?>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_test_mode_row">
                    <input type="checkbox" tabindex="10" id="test_mode" name="test_mode" <?= $safeVisitorConfig['test_mode'] ? "checked" : "" ?> value="1">
                    <label class="checkbox-label" for="test_mode">Test Mode - if this is checked, no emails will be sent and no registrations will be cancelled. Results will be logged only.</label>
                    <div class='clear-div'></div>
                </div>


                <div class="form-line">
                    <button id="save_config">Save Configuration</button>
                    <div class='clear-div'></div>
                </div>
            </form>
        </div>

		<?php
		return true;
	}

	function getBackgroundCheckGroupsControl() {
		$groupsColumn = new DataColumn("background_check_groups");
		$groupsColumn->setControlValue("data_type", "custom");
		$groupsColumn->setControlValue("control_class", "EditableList");
		$groupsControl = new EditableList($groupsColumn, $this);
		$columns = array("description" => array("data_type" => "varchar", "form_label" => "Public Group Name"),
			"product_id" => array("data_type" => "autocomplete", "data-autocomplete_tag" => "products", "form_label" => "Product"),
			"is_default" => array("data_type" => "tinyint", "form_label" => "Default?", "classes"=>"is-default-checkbox"),
            "public_access" => array("data_type" => "tinyint", "form_label" => "Public"),
			"group_name" => array("data_type" => "varchar", "form_label" => "SafeVisitor Group Name"),
			"application_url" => array("data_type" => "varchar", "form_label" => "URL for Survey", "readonly" => true));
		$columnList = array();
		foreach ($columns as $columnName => $thisColumn) {
			$dataColumn = new DataColumn($columnName);
			foreach ($thisColumn as $controlName => $controlValue) {
				$dataColumn->setControlValue($controlName, $controlValue);
			}
			$columnList[$columnName] = $dataColumn;
		}
		$groupsControl->setColumnList($columnList);
		return $groupsControl;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#test_credentials").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=test_credentials", $("#_setup_form").serialize(), function (returnArray) {
                    if ("error_message" in returnArray) {
                        $("#_test_credentials").html(returnArray['error_message']).addClass("red-text");
                    } else {
                        $("#_test_credentials").html("Credentials work").addClass("green-text");
                        loadGroups();
                    }
                });
                return false;
            });
            $("#setup_fields").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=setup_fields", function (returnArray) {
                    if ("error_message" in returnArray) {
                        $("#_setup_fields").html(returnArray['error_message']).addClass("red-text");
                    } else {
                        $("#_setup_fields").html("Fields set up successfully").addClass("green-text");
                    }
                });
                return false;
            });
            function loadGroups() {
                if ($("#_background_check_groups_table .editable-list-data-row").length == 0) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_groups", function (returnArray) {
                        if ("groups" in returnArray) {
                            returnArray["groups"].forEach(function (row) {
                                addEditableListRow("background_check_groups", row);
                            });
                        }
                    })
                }
            }
            $("#save_config").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_config", $("#_setup_form").serialize(), function (returnArray) {
                    if ("error_message" in returnArray) {
                        $("#_test_credentials").html(returnArray['error_message']).addClass("red-text");
                    } else {
						$('body').data('just_saved', 'true');
                        $("#_test_credentials").html("Configuration Saved Successfully").addClass("green-text");
                    }
                });
                return false;
            });
            loadGroups();
			$("#_background_check_groups_table").on("change", ".is-default-checkbox", function(){
				$("#_background_check_groups_table").find(".is-default-checkbox").prop("checked", false);
				$(this).prop("checked", true);
			});
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #sync_categories {
                margin: 40px 0;
            }
        </style>
		<?php
	}

	function jqueryTemplates() {
		$groupsControl = $this->getBackgroundCheckGroupsControl();
		echo $groupsControl->getTemplate();
	}
}

$pageObject = new SafeVisitorSetupPage();
$pageObject->displayPage();
