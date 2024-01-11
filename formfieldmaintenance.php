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

$GLOBALS['gPageCode'] = "FORMFIELDMAINT";
require_once "shared/startup.inc";

class FormFieldMaintenancePage extends Page {

	function setup() {
		if ($GLOBALS['gPermissionLevel'] > _READONLY) {
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("icon" => "fad fa-copy", "label" => getLanguageText("Duplicate"),
				"disabled" => false)));
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("form_field_controls", "form_field_choices"));
		$this->iDataSource->addColumnControl("custom_field_id", "get_choices", "customFieldChoices");

		// Duplicate function
		if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
			$fieldId = getFieldFromId("form_field_id", "form_fields", "form_field_id", $_GET['primary_id'], "client_id is not null");
			if (empty($fieldId)) {
				return;
			}
			$resultSet = executeQuery("select * from form_fields where form_field_id = ?", $fieldId);
			$fieldRow = getNextRow($resultSet);
			$originalfieldCode = $fieldRow['form_field_code'];
			$subNumber = 1;
			$queryString = "";
			foreach ($fieldRow as $fieldName => $fieldData) {
				if (empty($queryString)) {
					$fieldRow[$fieldName] = "";
				}
				if ($fieldName == "client_id") {
					$fieldRow[$fieldName] = $GLOBALS['gClientId'];
				}
				$queryString .= (empty($queryString) ? "" : ",") . "?";
			}
			$newFieldId = "";
			$fieldRow['description'] .= " Copy";
			while (empty($newFieldId)) {
				$fieldRow['form_field_code'] = $originalfieldCode . "_" . $subNumber;
				$resultSet = executeQuery("insert into form_fields values (" . $queryString . ")", $fieldRow);
				if ($resultSet['sql_error_number'] == 1062) {
					$subNumber++;
					continue;
				}
				$newFieldId = $resultSet['insert_id'];
			}
			$_GET['primary_id'] = $newFieldId;
			$subTables = array("form_field_controls", "form_field_choices");
			foreach ($subTables as $tableName) {
				$resultSet = executeQuery("select * from " . $tableName . " where form_field_id = ?", $fieldId);
				while ($row = getNextRow($resultSet)) {
					$queryString = "";
					foreach ($row as $fieldName => $fieldData) {
						if (empty($queryString)) {
							$row[$fieldName] = "";
						}
						$queryString .= (empty($queryString) ? "" : ",") . "?";
					}
					$row['form_field_id'] = $newFieldId;
					executeQuery("insert into " . $tableName . " values (" . $queryString . ")", $row);
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
                const $primaryId = $("#primary_id");
                if (!empty($primaryId.val())) {
                    if (changesMade()) {
                        askAboutChanges(function () {
                            $('body').data('just_saved', 'true');
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $primaryId.val();
                        });
                    } else {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $primaryId.val();
                    }
                }
                return false;
            });
        </script>
	<?php }
	}

	function customFieldChoices($showInactive = false) {
		$customFieldChoices = array();
		$resultSet = executeQuery("select * from custom_fields where client_id = ? and custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code in ('CONTACTS','HELP_DESK')) order by sort_order,description",
			$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$customFieldChoices[$row['custom_field_id']] = array("key_value" => $row['custom_field_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1);
			}
		}
		freeResult($resultSet);
		return $customFieldChoices;
	}

}

$pageObject = new FormFieldMaintenancePage("form_fields");
$pageObject->displayPage();
