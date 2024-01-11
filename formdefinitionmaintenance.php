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

$GLOBALS['gPageCode'] = "FORMDEFINITIONMAINT";
require_once "shared/startup.inc";

class FormDefinitionMaintenancePage extends Page {

	private $iDefaultFields = array();

	function setup() {

		if (empty($_GET['ajax'])) {
			$this->iDefaultFields[] = array("form_field_code" => "first_name", "description" => "First Name", "form_label" => "First Name", "controls" => array("data_type" => "varchar", "maximum_length" => "60"));
			$this->iDefaultFields[] = array("form_field_code" => "middle_name", "description" => "Middle Name", "form_label" => "Middle Name", "controls" => array("data_type" => "varchar", "maximum_length" => "25"));
			$this->iDefaultFields[] = array("form_field_code" => "last_name", "description" => "Last Name", "form_label" => "Last Name", "controls" => array("data_type" => "varchar", "maximum_length" => "60"));
			$this->iDefaultFields[] = array("form_field_code" => "address_1", "description" => "Address Line 1", "form_label" => "Address", "controls" => array("data_type" => "varchar", "maximum_length" => "60"));
			$this->iDefaultFields[] = array("form_field_code" => "city", "description" => "City", "form_label" => "City", "controls" => array("data_type" => "varchar", "maximum_length" => "60"));
			$this->iDefaultFields[] = array("form_field_code" => "state_select", "description" => "State Select", "form_label" => "State", "controls" => array("data_type" => "select", "get_choices" => "getStateArray"));
			$this->iDefaultFields[] = array("form_field_code" => "postal_code", "description" => "Postal Code", "form_label" => "Postal Code", "controls" => array("data_type" => "varchar", "maximum_length" => "10"));
			$this->iDefaultFields[] = array("form_field_code" => "email_address", "description" => "Email Address", "form_label" => "Email Address", "controls" => array("data_type" => "varchar", "maximum_length" => "60", "data_format" => "email"));
			$this->iDefaultFields[] = array("form_field_code" => "home_phone_number", "description" => "Home Phone", "form_label" => "Home Phone", "controls" => array("data_type" => "varchar", "maximum_length" => "25", "data_format" => "phone"));
			$this->iDefaultFields[] = array("form_field_code" => "cell_phone_number", "description" => "Cell Phone", "form_label" => "Cell Phone", "controls" => array("data_type" => "varchar", "maximum_length" => "25", "data_format" => "phone"));
			$this->iDefaultFields[] = array("form_field_code" => "office_phone_number", "description" => "Office Phone", "form_label" => "Office Phone", "controls" => array("data_type" => "varchar", "maximum_length" => "25", "data_format" => "phone"));
			$this->iDefaultFields[] = array("form_field_code" => "phone_number", "description" => "Phone", "form_label" => "Phone", "controls" => array("data_type" => "varchar", "maximum_length" => "25", "data_format" => "phone"));
			$this->iDefaultFields[] = array("form_field_code" => "state", "description" => "State", "form_label" => "State", "controls" => array("data_type" => "varchar", "maximum_length" => "30", "css-width" => "80px"));
			$this->iDefaultFields[] = array("form_field_code" => "country_id", "description" => "Country", "form_label" => "Country", "controls" => array("data_type" => "select", "initial_value" => "1000", "get_choices" => "getCountryArray"));
			$this->iDefaultFields[] = array("form_field_code" => "content", "description" => "Content", "form_label" => "Content", "controls" => array("data_type" => "text"));
			$this->iDefaultFields[] = array("form_field_code" => "help_desk_type_code", "description" => "Help Desk Type Code", "form_label" => "Help Desk Type Code", "controls" => array("data_type" => "hidden"));
			$this->iDefaultFields[] = array("form_field_code" => "help_desk_category_id", "description" => "Help Desk Inquiry Type", "form_label" => "Help Desk Inquiry Type", "controls" => array("data_type" => "select", "choices" => "return \$GLOBALS['gPrimaryDatabase']->getControlRecords(array(\"table_name\"=>\"help_desk_categories\",\"description_field\"=>\"description\"))"));
			$this->iDefaultFields[] = array("form_field_code" => "description", "description" => "Brief Description", "form_label" => "Brief Description", "controls" => array("data_type" => "varchar"));
			$this->iDefaultFields[] = array("form_field_code" => "file_id", "description" => "Attach File", "form_label" => "Attach File", "controls" => array("data_type" => "file"));
			$this->iDefaultFields[] = array("form_field_code" => "image_id", "description" => "Attach Image", "form_label" => "Attach Image", "controls" => array("data_type" => "image_input"));

		}

		foreach ($this->iDefaultFields as $thisField) {
			$formFieldId = getFieldFromId("form_field_id", "form_fields", "form_field_code", $thisField['form_field_code']);
			if (empty($formFieldId)) {
				$insertSet = executeQuery("insert into form_fields (client_id,form_field_code,description,form_label) values (?,?,?,?)", $GLOBALS['gClientId'], $thisField['form_field_code'], $thisField['description'], $thisField['form_label']);
				$formFieldId = $insertSet['insert_id'];
				if (empty($thisField['controls'])) {
					continue;
				}
				foreach ($thisField['controls'] as $controlName => $controlValue) {
					executeQuery("insert into form_field_controls (form_field_id,control_name,control_value) values (?,?,?)", $formFieldId, $controlName, $controlValue);
				}
			}
		}

		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			if (!$GLOBALS['gUserRow']['superuser_flag']) {
				$this->iTemplateObject->getTableEditorObject()->addExcludeColumn(array("action_filename"));
			}
			if ($GLOBALS['gPermissionLevel'] > _READONLY) {
				$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("icon" => "fad fa-copy", "label" => getLanguageText("Duplicate"),
					"disabled" => false)));
			}
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_form_field_value_control":
				$formFieldCode = getFieldFromId("form_field_code", "form_fields", "form_field_id", $_POST['form_field_id']);
				if (empty($formFieldCode)) {
					$returnArray['error_message'] = "Invalid Form Field";
					ajaxResponse($returnArray);
					break;
				}
				$formFieldId = "";
				$resultSet = executeQuery("select * from form_fields where form_field_code = ? and client_id = ?", $formFieldCode, $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$formFieldId = $row['form_field_id'];
					$columnControls = array("form_label" => $row['form_label'], "column_name" => $row['form_field_code']);
					$resultSet = executeQuery("select * from form_field_controls where form_field_id = ?", $row['form_field_id']);
					while ($controlRow = getNextRow($resultSet)) {
						$columnControls[$controlRow['control_name']] = DataSource::massageControlValue($controlRow['control_name'], $controlRow['control_value']);
					}
				} else {
					$columnControls = array("column_name" => $formFieldCode, "form_label" => "Form Field Not Found: " . $formFieldCode);
				}
				if (empty($columnControls['data_type'])) {
					$columnControls['data_type'] = "varchar";
				}
				$thisColumn = new DataColumn($row['form_field_code'], $columnControls);
				$dataType = $thisColumn->getControlValue("data_type");
				switch ($dataType) {
					case "varchar":
						$returnArray['form_field_value_control'] = "<input tabindex='10' class='form-field-value-control' type='text'>";
						break;
					case "bigint":
					case "int":
						$returnArray['form_field_value_control'] = "<input tabindex='10' class='align-right form-field-value-control validate[custom[integer]]' size='8' type='text'>";
						break;
					case "decimal":
						$returnArray['form_field_value_control'] = "<input tabindex='10' class='align-right form-field-value-control validate[custom[number]]' size='10' data-decimal-places='2' type='text'>";
						break;
					case "date":
						$returnArray['form_field_value_control'] = "<input tabindex='10' class='form-field-value-control validate[custom[date]]' size='10' type='text'>";
						break;
					case "tinyint":
						$returnArray['form_field_value_control'] = "<select tabindex='10' class='form-field-value-control'><option value='1'>Selected</option><option value='0'>Not selected</option></select>";
						break;
					case "select":
					case "radio":
						$choices = $thisColumn->getControlValue("choices");
						if (!is_array($choices)) {
							$choices = array();
						}
						$choiceSet = executeQuery("select * from form_field_choices where form_field_id = ?", $row['form_field_id']);
						while ($choiceRow = getNextRow($choiceSet)) {
							$choices[$choiceRow['key_value']] = $choiceRow['description'];
						}
						$returnArray['form_field_value_control'] = "<select tabindex='10' class='form-field-value-control'>";
						foreach ($choices as $keyValue => $description) {
							$returnArray['form_field_value_control'] .= "<option value='" . $keyValue . "'>" . htmlText($description) . "</option>";
						}
						$returnArray['form_field_value_control'] .= "</select>";
						break;
					default:
						$returnArray['error_message'] = "Invalid Form Field Type";
						ajaxResponse($returnArray);
						break;
				}
				$returnArray['row_id'] = $_POST['row_id'];
				ajaxResponse($returnArray);
				break;
		}
	}

	function getBasicFormFields($showInactive = false) {
		$fieldChoices = array();
		$resultSet = executeQuery("select * from form_fields where client_id = ? and form_field_id in (select form_field_id from form_field_controls " .
			"where control_name = 'data_type' and control_value in ('int','decimal','varchar','select','tinyint','date','radio')) order by description",
			$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$fieldChoices[$row['form_field_id']] = array("key_value" => $row['form_field_id'], "description" => $row['description'], "inactive" => false);
		}
		freeResult($resultSet);
		return $fieldChoices;
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("create_contact_pdf","form_label","Create a PDF copy to save with contact when form is submitted");
		$this->iDataSource->addColumnControl("form_definition_contact_identifiers", "data_type", "custom");
		$this->iDataSource->addColumnControl("form_definition_contact_identifiers", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("form_definition_contact_identifiers", "list_table", "form_definition_contact_identifiers");
		$this->iDataSource->addColumnControl("form_definition_contact_identifiers", "form_label", "Contact Identifiers");
		$this->iDataSource->addColumnControl("form_definition_contact_identifiers", "list_table_controls", array("form_field_id" => array("get_choices" => "formFieldChoices")));

		$this->iDataSource->addColumnControl("form_definition_group_links", "data_type", "custom");
		$this->iDataSource->addColumnControl("form_definition_group_links", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("form_definition_group_links", "control_table", "form_definition_groups");
		$this->iDataSource->addColumnControl("form_definition_group_links", "links_table", "form_definition_group_links");
		$this->iDataSource->addColumnControl("form_definition_group_links", "form_label", "Form Definition Groups");

		$this->iDataSource->addColumnControl("expiration_days", "help_label", "Number of days after form is completed that it is expires and has to be completed again. Leave blank for no expiration.");
		$this->iDataSource->addColumnControl("expiration_days", "minimum_value", 30);
		$this->iDataSource->addColumnControl("expiration_email_id", "help_label", "Email that is sent 7 days before expiration of previously completed form.");

		$this->iDataSource->addColumnControl("submitted_count", "form_label", "Number of forms submitted");
		$this->iDataSource->addColumnControl("submitted_count", "data_type", "int");
		$this->iDataSource->addColumnControl("submitted_count", "readonly", "true");
		$this->iDataSource->getPrimaryTable()->setSubtables(array("form_definition_files", "form_definition_controls", "form_definition_emails", "form_definition_status", "form_definition_contact_fields", "form_definition_phone_number_fields", "form_field_tags", "form_definition_discounts", "form_definition_mailing_lists", "form_field_payments"));
		if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
			$formDefinitionId = getFieldFromId("form_definition_id", "form_definitions", "form_definition_id", $_GET['primary_id']);
			if (empty($formDefinitionId)) {
				return;
			}
			$resultSet = executeQuery("select * from form_definitions where form_definition_id = ?", $formDefinitionId);
			$formDefinitionRow = getNextRow($resultSet);
			$originalFormDefinitionCode = $formDefinitionRow['form_definition_code'];
			$subNumber = 1;
			$queryString = "";
			foreach ($formDefinitionRow as $fieldName => $fieldData) {
				if (empty($queryString)) {
					$formDefinitionRow[$fieldName] = "";
				}
				if ($fieldName == "client_id") {
					$pageRow[$fieldName] = $GLOBALS['gClientId'];
				}
				$queryString .= (empty($queryString) ? "" : ",") . "?";
			}
			$newFormDefinitionId = "";
			$formDefinitionRow['description'] .= " Copy";
			while (empty($newFormDefinitionId)) {
				$formDefinitionRow['form_definition_code'] = $originalFormDefinitionCode . "_" . $subNumber;
				$formDefinitionRow['date_created'] = date("Y-m-d");
				$formDefinitionRow['creator_user_id'] = $GLOBALS['gUserId'];
				$resultSet = executeQuery("select * from form_definitions where form_definition_code = ?", $formDefinitionRow['form_definition_code']);
				if ($row = getNextRow($resultSet)) {
					$subNumber++;
					continue;
				}
				$resultSet = executeQuery("insert into form_definitions values (" . $queryString . ")", $formDefinitionRow);
				if ($resultSet['sql_error_number'] == 1062) {
					$subNumber++;
					continue;
				}
				$newFormDefinitionId = $resultSet['insert_id'];
			}
			$_GET['primary_id'] = $newFormDefinitionId;
			$subTables = array("form_definition_files", "form_definition_controls", "form_definition_emails", "form_definition_status", "form_definition_contact_fields", "form_definition_phone_number_fields", "form_field_tags", "form_definition_discounts", "form_definition_mailing_lists", "form_field_payments");
			foreach ($subTables as $tableName) {
				$resultSet = executeQuery("select * from " . $tableName . " where form_definition_id = ?", $formDefinitionId);
				while ($row = getNextRow($resultSet)) {
					$queryString = "";
					foreach ($row as $fieldName => $fieldData) {
						if (empty($queryString)) {
							$row[$fieldName] = "";
						}
						$queryString .= (empty($queryString) ? "" : ",") . "?";
					}
					$row['form_definition_id'] = $newFormDefinitionId;
					$insertSet = executeQuery("insert into " . $tableName . " values (" . $queryString . ")", $row);
				}
			}
		}
	}

	function contactColumnChoices($showInactive = false) {
		$contactColumnChoices = array();
		$resultSet = executeQuery("select column_definition_id,description from table_columns where table_id = (select table_id from tables where table_name = 'contacts') and column_definition_id in " .
			"(select column_definition_id from column_definitions where column_name in ('title','first_name','middle_name','last_name','suffix','alternate_name','preferred_first_name','business_name'," .
			"'job_title','department','salutation','address_1','address_2','city','state','postal_code','country_id','attention_line','email_address','web_page','source_id','image_id','birthdate','contact_type_id'))");
		while ($row = getNextRow($resultSet)) {
			$contactColumnChoices[$row['column_definition_id']] = array("key_value" => $row['column_definition_id'], "description" => $row['description'], "inactive" => false);
		}
		freeResult($resultSet);
		return $contactColumnChoices;
	}

	function formFieldChoices($showInactive = false) {
		$formFieldChoices = array();
		$resultSet = executeQuery("select * from form_fields where client_id = ? order by description,form_field_code", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$formFieldChoices[$row['form_field_id']] = array("key_value" => $row['form_field_id'], "description" => $row['description'] . " (" . $row['form_field_code'] . ")", "inactive" => false);
		}
		freeResult($resultSet);
		return $formFieldChoices;
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
			<?php } ?>
            $(document).on("blur", ".form-field-value-control", function () {
                $(this).closest("td").find(".form-field-tag-form-field-value").val($(this).val());
            });
            $(document).on("change", ".form-field-tag-form-field-id", function () {
                const rowId = $(this).closest("tr").prop("id");
                const formFieldId = $(this).val();
                $(this).closest("tr").find('.form-field-value-control').remove();
                if (!empty(formFieldId)) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_form_field_value_control", {row_id: rowId, form_field_id: formFieldId}, function(returnArray) {
                        if ("row_id" in returnArray && "form_field_value_control" in returnArray) {
                            if ($("#" + returnArray['row_id']).length > 0) {
                                $("#" + returnArray['row_id']).find(".form-field-tag-form-field-value").closest("td").append(returnArray['form_field_value_control']);
                            }
                            const fieldValue = $("#" + returnArray['row_id']).find(".form-field-tag-form-field-value").val();
                            $("#" + returnArray['row_id']).find(".form-field-value-control").val(fieldValue);
                        }
                    });
                }
            });
            $("#export_submissions").click(function () {
                if (!empty($("#primary_id").val())) {
                    document.location = "exportform.php?id=" + $("#primary_id").val();
                }
                return false;
            });
            $("#convert_content").click(function () {
                $("#form_content").val($("#form_content").val().replace("<!-- Coreware Form Builder -->", "").replace("%payment_block%", ""));
                $(".form-builder-content").hide();
                $("#_form_content_row").show();
                return false;
            });
            $("#open_form_builder").click(function () {
                const primaryId = $("#primary_id").val();
                if (!empty(primaryId)) {
                    goToLink($(this), "/formbuilder.php?url_page=show&primary_id=" + primaryId);
                }
                return false;
            });
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		if (substr($returnArray['form_content']['data_value'], 0, strlen("<!-- Coreware Form Builder -->")) == "<!-- Coreware Form Builder -->") {
			$returnArray['form_builder_content'] = "1";
		} else {
			$returnArray['form_builder_content'] = "";
		}
		$count = 0;
		$resultSet = executeQuery("select count(*) from forms where form_definition_id = ?", $returnArray['primary_id']['data_value']);
		if ($row = getNextRow($resultSet)) {
			$count = $row['count(*)'];
		}
		$returnArray['submitted_count'] = array("data_value" => $count);
	}

	function internalCSS() {
		?>
        <style>
            .form-builder-content {
                display: none;
            }

            #form_content {
                height: 500px;
            }
        </style>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord(returnArray) {
                if (returnArray['form_builder_content'] === "1") {
                    $(".form-builder-content").show();
                    $("#_form_content_row").hide();
                    $(".open-form-builder").show();
                } else {
                    if (empty($("#form_content").val())) {
                        $(".open-form-builder").show();
                    } else {
                        $(".open-form-builder").hide();
                    }
                    $(".form-builder-content").hide();
                    $("#_form_content_row").show();
                }
                if (empty($("#primary_id").val())) {
                    $(".open-form-builder").hide();
                    $(".form-builder-instructions").show();
                } else {
                    $(".open-form-builder").show();
                    $(".form-builder-instructions").hide();
                }
                $(".form-field-tag-form-field-id").each(function () {
                    $(this).trigger("change");
                });
            }
        </script>
		<?php
	}
}

$pageObject = new FormDefinitionMaintenancePage("form_definitions");
$pageObject->displayPage();
