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

$GLOBALS['gPageCode'] = "CONTACTCUSTOMDATA";
require_once "shared/startup.inc";

class ContactCustomDataPage extends Page {
	var $iSearchFields = array("contact_id", "first_name", "last_name", "business_name", "address_1", "city", "state", "postal_code", "email_address");
	var $iCustomFieldGroupId = "";

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "set_custom_field_group":
				$valuesArray = Page::getPagePreferences();
				if (array_key_exists("custom_field_group_id", $_GET)) {
					$valuesArray['custom_field_group_id'] = getFieldFromId("custom_field_group_id", "custom_field_groups", "custom_field_group_id", $_GET['custom_field_group_id']);
					Page::setPagePreferences($valuesArray);
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function addCustomFields() {
		$description = getFieldFromId("description", "custom_field_groups", "custom_field_group_id", $this->iCustomFieldGroupId);
		?>
        <h2><?= htmlText($description) ?></h2>
		<?php
		$resultSet = executeQuery("select * from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS') and inactive = 0 and client_id = ? " .
			($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and (user_group_id is null or user_group_id in " .
				"(select user_group_id from user_group_members where user_id = " . $GLOBALS['gUserId'] . ")) ") .
			" and custom_field_id in (select custom_field_id from custom_field_group_links where custom_field_group_id = " .
			"(select custom_field_group_id from custom_field_groups where custom_field_group_id = ? and client_id = ?" .
			($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and (user_group_id is null or user_group_id in " .
				"(select user_group_id from user_group_members where user_id = " . $GLOBALS['gUserId'] . ")) ") . "))" .
			"order by sort_order,description", $GLOBALS['gClientId'], $this->iCustomFieldGroupId, $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$thisColumn = new DataColumn("custom_field_id_" . $row['custom_field_id']);
			$thisColumn->setControlValue("form_label", $row['form_label']);
			$controlSet = executeQuery("select * from custom_field_controls where custom_field_id = ?", $row['custom_field_id']);
			while ($controlRow = getNextRow($controlSet)) {
				$thisColumn->setControlValue($controlRow['control_name'], $controlRow['control_value']);
			}
			$choices = $thisColumn->getControlValue("choices");
			$choiceSet = executeQuery("select * from custom_field_choices where custom_field_id = ?", $row['custom_field_id']);
			while ($choiceRow = getNextRow($choiceSet)) {
				$choices[$choiceRow['key_value']] = $choiceRow['description'];
			}
			$thisColumn->setControlValue("choices", $choices);
			$checkbox = ($thisColumn->getControlValue("data_type") == "tinyint");
			?>
            <div class="basic-form-line" id="_<?= "custom_field_id_" . $row['custom_field_id'] ?>_row">
                <label for="<?= "custom_field_id_" . $row['custom_field_id'] ?>" class="<?= ($thisColumn->getControlValue("not_null") && !$checkbox ? "required-label" : "") ?>"><?= ($checkbox ? "" : $thisColumn->getControlValue("form_label")) ?></label>
				<?= $thisColumn->getControl($this) ?>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
			<?php
		}
	}

	function setup() {
		$valuesArray = Page::getPagePreferences();
		if (array_key_exists("custom_field_group_id", $_GET)) {
			$valuesArray['custom_field_group_id'] = getFieldFromId("custom_field_group_id", "custom_field_groups", "custom_field_group_id", $_GET['custom_field_group_id']);
			Page::setPagePreferences($valuesArray);
		}
		if (array_key_exists("custom_field_group_id", $valuesArray)) {
			$this->iCustomFieldGroupId = getFieldFromId("custom_field_group_id", "custom_field_groups", "custom_field_group_id", $valuesArray['custom_field_group_id']);
		}
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("first_name", "last_name", "business_name", "address_1", "city", "state", "postal_code", "country_id", "email_address", "date_created"));
			$this->iTemplateObject->getTableEditorObject()->addIncludeSearchColumn($this->iSearchFields);
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "add"));
			$filters = array();
			$resultSet = executeQuery("select * from categories where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
			if ($resultSet['row_count'] > 0) {
				$filters['category_header'] = array("form_label" => "Categories", "data_type" => "header");
				while ($row = getNextRow($resultSet)) {
					$filters['category_' . $row['category_id']] = array("form_label" => $row['description'], "where" => "contact_id in (select contact_id from contact_categories where category_id = " . $row['category_id'] . ")", "data_type" => "tinyint");
				}
			}
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("first_name", "readonly", true);
		$this->iDataSource->addColumnControl("last_name", "readonly", true);
		$this->iDataSource->addColumnControl("business_name", "readonly", true);
		$this->iDataSource->addColumnControl("address_1", "readonly", true);
		$this->iDataSource->addColumnControl("address_2", "readonly", true);
		$this->iDataSource->addColumnControl("city", "readonly", true);
		$this->iDataSource->addColumnControl("state", "readonly", true);
		$this->iDataSource->addColumnControl("postal_code", "readonly", true);
		$this->iDataSource->addColumnControl("country_id", "readonly", true);
		$this->iDataSource->addFilterWhere("deleted = 0");
	}

	function beforeList() {
		?>
        <div class="basic-form-line" id="_custom_field_group_id_row">
            <label for="custom_field_group_id">Custom Field Group</label>
            <select id="custom_field_group_id" name="custom_field_group_id">
                <option value="">[Select]</option>
				<?php
				$resultSet = executeQuery("select * from custom_field_groups where client_id = ? and inactive = 0" .
					($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and (user_group_id is null or user_group_id in " .
						"(select user_group_id from user_group_members where user_id = " . $GLOBALS['gUserId'] . "))") .
					" order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
                    <option value="<?= $row['custom_field_group_id'] ?>"<?= ($this->iCustomFieldGroupId == $row['custom_field_group_id'] ? " selected" : "") ?>><?= htmlText($row['description']) ?></option>
					<?php
				}
				?>
            </select>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#custom_field_group_id").change(function () {
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_custom_field_group&custom_field_group_id=" + $(this).val());
                }
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function dataRowClicked() {
                if (empty($("#custom_field_group_id").val())) {
                    displayErrorMessage("Choose a custom field group");
                    return false;
                } else {
                    return true;
                }
            }
        </script>
		<?php
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$customFields = CustomField::getCustomFields("contacts", getFieldFromId("custom_field_group_code", "custom_field_groups", "custom_field_group_id", $this->iCustomFieldGroupId));
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if (!$customField->saveData($nameValues)) {
				return $customField->getErrorMessage();
			}
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
		$customFields = CustomField::getCustomFields("contacts", getFieldFromId("custom_field_group_code", "custom_field_groups", "custom_field_group_id", $this->iCustomFieldGroupId));
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldData = $customField->getRecord($returnArray['primary_id']['data_value']);
			if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldData)) {
				$returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'], $customFieldData['select_values']);
			}
			$returnArray = array_merge($returnArray, $customFieldData);
		}
	}

	function filterTextProcessing($filterText) {
		if (!empty($filterText)) {
			$parts = explode(" ", $filterText);
			if (count($parts) == 2) {
				$whereStatement = "(first_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[0] . "%") .
					" and last_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[1] . "%") . ")";
				foreach ($this->iSearchFields as $fieldName) {
					$whereStatement .= " or " . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%");
				}
				$this->iDataSource->addFilterWhere($whereStatement);
			} else {
				if (is_numeric($filterText)) {
					$whereStatement = "contact_id in (select contact_id from contact_redirect where client_id = " . $GLOBALS['gClientId'] .
						" and retired_contact_identifier = " . $GLOBALS['gPrimaryDatabase']->makeParameter($filterText) . ") or contact_id = " .
						$GLOBALS['gPrimaryDatabase']->makeNumberParameter($filterText);
					foreach ($this->iSearchFields as $fieldName) {
						if ($fieldName != "contacts.contact_id") {
							$whereStatement .= " or " . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%");
						}
					}
					$this->iDataSource->addFilterWhere($whereStatement);
				} else {
					$this->iDataSource->setFilterText($filterText);
				}
			}
		}
	}
}

$pageObject = new ContactCustomDataPage("contacts");
$pageObject->displayPage();
