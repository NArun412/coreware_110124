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

$GLOBALS['gPageCode'] = "CUSTOMFIELDMAINT";
require_once "shared/startup.inc";

class CustomFieldMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			if ($GLOBALS['gPermissionLevel'] > _READONLY) {
				$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("icon" => "fad fa-copy", "label" => getLanguageText("Duplicate"),
					"disabled" => false)));
			}
			$filters = array();
			$resultSet = executeQuery("select * from custom_field_types order by sort_order,description");
			if ($resultSet['row_count'] > 0) {
				$customFieldTypes = array();
				while ($row = getNextRow($resultSet)) {
					$customFieldTypes[$row['custom_field_type_id']] = $row['description'];
				}
				$filters['custom_field_types'] = array("form_label" => "Custom Field Type", "where" => "custom_field_type_id = %key_value%", "data_type" => "select", "choices" => $customFieldTypes, "conjunction"=>"and");
			}
			$resultSet = executeQuery("select * from custom_field_groups where client_id = ? order by sort_order,description",$GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['custom_field_group_' . $row['custom_field_group_id']] = array("form_label" => $row['description'], "where" => "custom_field_id in (select custom_field_id from custom_field_group_links where custom_field_group_id = " . $row['custom_field_group_id'] . ")", "data_type" => "tinyint");
			}
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("custom_field_controls", "custom_field_data", "custom_field_group_links", "custom_field_choices", "product_distributor_custom_fields"));
		if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
			$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_id", $_GET['primary_id']);
			if (empty($customFieldId)) {
				return;
			}
			$resultSet = executeQuery("select * from custom_fields where custom_field_id = ?", $customFieldId);
			$customFieldRow = getNextRow($resultSet);
			$originalCustomFieldCode = $customFieldRow['custom_field_code'];
			$subNumber = 1;
			$queryString = "";
			foreach ($customFieldRow as $fieldName => $fieldData) {
				if (empty($queryString)) {
					$customFieldRow[$fieldName] = "";
				}
				if ($fieldName == "client_id") {
					$customFieldRow[$fieldName] = $GLOBALS['gClientId'];
				}
				$queryString .= (empty($queryString) ? "" : ",") . "?";
			}
			$newCustomFieldId = "";
			$customFieldRow['description'] .= " Copy";
			while (empty($newCustomFieldId)) {
				$customFieldRow['custom_field_code'] = $originalCustomFieldCode . "_" . $subNumber;
				$resultSet = executeQuery("select * from custom_fields where custom_field_code = ?", $customFieldRow['custom_field_code']);
				if ($row = getNextRow($resultSet)) {
					$subNumber++;
					continue;
				}
				$resultSet = executeQuery("insert into custom_fields values (" . $queryString . ")", $customFieldRow);
				if ($resultSet['sql_error_number'] == 1062) {
					$subNumber++;
					continue;
				}
				$newCustomFieldId = $resultSet['insert_id'];
			}
			$_GET['primary_id'] = $newCustomFieldId;
			$subTables = array("custom_field_choices", "custom_field_controls", "custom_field_group_links");
			foreach ($subTables as $tableName) {
				$resultSet = executeQuery("select * from " . $tableName . " where custom_field_id = ?", $customFieldId);
				while ($row = getNextRow($resultSet)) {
					$queryString = "";
					foreach ($row as $fieldName => $fieldData) {
						if (empty($queryString)) {
							$row[$fieldName] = "";
						}
						$queryString .= (empty($queryString) ? "" : ",") . "?";
					}
					$row['custom_field_id'] = $newCustomFieldId;
					$insertSet = executeQuery("insert into " . $tableName . " values (" . $queryString . ")", $row);
				}
			}
		}
	}

	function onLoadJavascript() {
		?>
        <script>
			<?php
			if ($GLOBALS['gPermissionLevel'] > _READONLY) {
			?>
            $(document).on("tap click", "#_duplicate_button", function () {
                if (!empty($("#primary_id").val())) {
                    if (changesMade()) {
                        askAboutChanges(function () {
                            $('body').data('just_saved', 'true');
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $("#primary_id").val();
                        });
                    } else {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $("#primary_id").val();
                    }
                }
                return false;
            });
			<?php
			}
			?>
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
				<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                if (empty($("#primary_id").val())) {
                    disableButtons($("#_duplicate_button"));
                } else {
                    enableButtons($("#_duplicate_button"));
                }
				<?php } ?>
            }
        </script>
		<?php
	}
}

$pageObject = new CustomFieldMaintenancePage("custom_fields");
$pageObject->displayPage();
