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

$GLOBALS['gPageCode'] = "DISPLAYFORM";
$GLOBALS['gProxyPageCode'] = "FORMMAINT";
require_once "shared/startup.inc";

$formId = getFieldFromId("form_id", "forms", "form_id", $_GET['form_id'], ($GLOBALS['gUserRow']['superuser_flag'] ? "" : "form_definition_id in (select form_definition_id from " .
	"form_definitions where user_group_id is null or user_group_id in (select user_group_id from user_group_members where user_id = " . $GLOBALS['gUserId'] . "))"));
if (empty($formId)) {
	echo "Form Not Found";
	exit;
}

class DisplayFormPage extends Page {
	private $iCustomFields = array();
	private $iFormDefinitionId = "";

	function headerIncludes() {
		$formId = getFieldFromId("form_id", "forms", "form_id", $_GET['form_id']);
		$this->iFormDefinitionId = getFieldFromId("form_definition_id", "forms", "form_id", $formId);
		$cssFileCode = getFieldFromId("css_file_code", "css_files", "css_file_id", getFieldFromId("css_file_id", "form_definitions", "form_definition_id", $this->iFormDefinitionId));
		if (!empty($cssFileCode)) {
			?>
            <link type="text/css" rel="stylesheet" href="<?= autoVersion(getCSSFilename($cssFileCode)) ?>"/>
			<?php
		}
	}

	function mainContent() {
		?>
        <div id="_form_div">
			<?php
			$formId = getFieldFromId("form_id", "forms", "form_id", $_GET['form_id']);
			$resultSet = executeQuery("select * from forms where form_id = ?", $formId);
			$formRow = getNextRow($resultSet);
			$formRow['data'] = array();
			$resultSet = executeQuery("select * from contacts where contact_id = ?", $formRow['contact_id']);
			if (!$contactRow = getNextRow($resultSet)) {
				$contactRow = array();
			}
			$resultSet = executeQuery("select * from form_data join form_fields using (form_field_id) where form_id = ?", $formRow['form_id']);
			while ($row = getNextRow($resultSet)) {
				if (!empty($row['integer_data'])) {
					$fieldValue = $row['integer_data'];
				} else if (!empty($row['number_data'])) {
					$fieldValue = $row['number_data'];
				} else if (!empty($row['date_data'])) {
					$fieldValue = (empty($row['date_data']) ? "" : date("m/d/Y", strtotime($row['date_data'])));
				} else if (!empty($row['image_id'])) {
					$fieldValue = $row['image_id'];
				} else if (!empty($row['file_id'])) {
					$fieldValue = $row['file_id'];
				} else {
					$fieldValue = $row['text_data'];
				}
				$formRow['data'][$row['form_field_code']] = $fieldValue;
			}
			$resultSet = executeQuery("select * from form_definition_contact_fields where form_definition_id = ?", $formRow['form_definition_id']);
			while ($row = getNextRow($resultSet)) {
				$contactColumnName = getFieldFromId("column_name", "column_definitions", "column_definition_id", $row['column_definition_id']);
				$formFieldCode = getFieldFromId("form_field_code", "form_fields", "form_field_id", $row['form_field_id']);
				if (empty($formRow['data'][$formFieldCode])) {
					$formRow['data'][$formFieldCode] = $contactRow[$contactColumnName];
				}
			}
			$resultSet = executeQuery("select * from form_definition_phone_number_fields where form_definition_id = ?", $formRow['form_definition_id']);
			while ($row = getNextRow($resultSet)) {
				$formFieldCode = getFieldFromId("form_field_code", "form_fields", "form_field_id", $row['form_field_id']);
				if (empty($formRow['data'][$formFieldCode])) {
					$formRow['data'][$formFieldCode] = Contact::getContactPhoneNumber($formRow['contact_id'],$row['description'],false);
				}
			}

			$resultSet = executeQuery("select * from form_definitions where form_definition_id = ?", $formRow['form_definition_id']);
			$formDefinitionRow = getNextRow($resultSet);
			echo $formDefinitionRow['introduction_content'] . "\n";
			$parentFormRow = array();
			if (!empty($formRow['parent_form_id'])) {
				$resultSet = executeQuery("select * from forms where form_id = ?", $formRow['parent_form_id']);
				if ($parentFormRow = getNextRow($resultSet)) {
					$parentFormRow['data'] = array();
					$resultSet = executeQuery("select * from contacts where contact_id = ?", $parentFormRow['contact_id']);
					if ($row = getNextRow($resultSet)) {
						$parentFormRow['data'] = $row;
					}
					$resultSet = executeQuery("select * from form_fields left outer join form_data on (form_fields.form_field_id = form_data.form_field_id and form_data.form_id = ?) where client_id = ?", $parentFormRow['form_id'], $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						if (!empty($row['integer_data'])) {
							$fieldValue = $row['integer_data'];
						} else if (!empty($row['number_data'])) {
							$fieldValue = $row['number_data'];
						} else if (!empty($row['date_data'])) {
							$fieldValue = (empty($row['date_data']) ? "" : date("m/d/Y", strtotime($row['date_data'])));
						} else if (!empty($row['image_id'])) {
							$fieldValue = $row['image_id'];
						} else if (!empty($row['file_id'])) {
							$fieldValue = $row['file_id'];
						} else {
							$fieldValue = $row['text_data'];
						}
						$parentFormRow['data'][$row['form_field_code']] = $fieldValue;
					}
				} else {
					$parentFormRow = array();
				}
			}

			$complexForm = getFieldFromId("control_value", "form_definition_controls", "form_definition_id", $formDefinitionRow['form_definition_id'], "column_name = 'form_definition' and control_name = 'complex_form'");

			$formContentArray = array();
			$userSubmittedForm = false;
			if (!empty($formDefinitionRow['form_filename'])) {
				$filename = $GLOBALS['gDocumentRoot'] . "/forms/" . $formDefinitionRow['form_filename'];
				$handle = fopen($filename, 'r');
				while ($thisLine = fgets($handle)) {
					$formContentArray[] = $thisLine;
				}
				fclose($handle);
			} else {
				$formContentArray = getContentLines($formDefinitionRow['form_content']);
				$userSubmittedForm = true;
				if (!empty($complexForm)) {
					$newFormContentArray = array();
					foreach ($formContentArray as $thisLine) {
						if (startsWith($thisLine,"%field:")) {
							$fieldName = trim(str_replace("%", "", substr($thisLine, strlen("%field:"))));
							if (!empty($formRow['data'][$fieldName])) {
								$newFormContentArray[] = $thisLine;
								$newFormContentArray[] = '<div class="form-line" id="_%column_name%_row">';
								$newFormContentArray[] = '<label for="%column_name%" class="%label_class%">%form_label%</label>';
								$newFormContentArray[] = '%input_control%';
								$newFormContentArray[] = '<div class="clear-div"></div>';
								$newFormContentArray[] = '</div>';
							}
						} else if (strpos($thisLine, "%parent_form:") !== false || startsWith($thisLine, "<h")) {
							$newFormContentArray[] = $thisLine;
						}
					}
					$formContentArray = $newFormContentArray;
				}
			}
			$useLine = true;
			$thisColumn = new DataColumn("missing_field");
			foreach ($formContentArray as $line) {
				$line = trim($line);
				if ($line == "%endif%") {
					$useLine = true;
					continue;
				}
				if (!$useLine) {
					continue;
				}
				if (startsWith($line,"%if:")) {
					if ($userSubmittedForm) {
						$useLine = false;
					} else {
						$evalStatement = substr($line, strlen("%if:"));
						if (substr($evalStatement, -1) == "%") {
							$evalStatement = substr($evalStatement, 0, -1);
						}
						if (!startsWith($evalStatement,"return ")) {
							$evalStatement = "return " . $evalStatement;
						}
						if (substr($evalStatement, -1) != ";") {
							$evalStatement .= ";";
						}
						$useLine = eval($evalStatement);
					}
					continue;
				} else if (startsWith($line,"%if_has_value:")) {
					$parentFormField = substr($line, strlen("%if_has_value:"));
					if (substr($parentFormField, -1) == "%") {
						$parentFormField = substr($parentFormField, 0, -1);
					}
					$useLine = !empty($parentFormRow['data'][$parentFormField]);
					continue;
				} else if (startsWith($line,"%field:")) {
					$fieldName = trim(str_replace("%", "", substr($line, strlen("%field:"))));
					$thisColumn = $this->createColumn($fieldName, $formDefinitionRow['form_definition_id']);
					continue;
				} else if (startsWith($line,"%method:")) {
					$functionName = str_replace("%", "", substr($line, strlen("%method:")));
					if (!$userSubmittedForm && method_exists($this, $functionName)) {
						$this->$functionName();
					}
					continue;
				}
				if (array_key_exists("data", $parentFormRow)) {
					foreach ($parentFormRow['data'] as $fieldName => $fieldValue) {
						$line = str_replace("%parent_form:" . $fieldName . "%", htmlText($fieldValue), $line);
					}
				}
				if (!empty($formRow['form_description'])) {
					$line = str_replace("%formDescription%", htmlText($formRow['description']), $line);
				}
				$line = str_replace("%formDescription%", htmlText($formDefinitionRow['description']), $line);
				$line = str_replace("%dateSubmitted%", htmlText(date("m/d/Y", strtotime($formRow['date_created']))), $line);
				if ($thisColumn->getControlValue('data_type') == "tinyint") {
					$line = str_replace("%form_label%", "&nbsp;", $line);
				}
				$labelClass = "";
				if ($thisColumn->getControlValue('not_null') && $thisColumn->getControlValue('data_type') != "tinyint" && !$thisColumn->getControlValue('readonly')) {
					$labelClass = "required-label";
				}
				$thisColumn->setControlValue("label_class", $labelClass);
				foreach ($thisColumn->getAllControlValues() as $infoName => $infoData) {
                    $infoData = is_scalar($infoData) ? $infoData : "";
                    $line = str_ireplace("%" . $infoName . "%", $infoData, $line);
				}
				if (strpos($line, "%input_control%") !== false) {
					$thisColumn->setControlValue('readonly', true);
					switch ($thisColumn->getControlValue('data_type')) {
						case "custom":
							if ($thisColumn->getControlValue('control_class') == "EditableList" || $thisColumn->getControlValue('control_class') == "FormList") {
								$submittedData = json_decode($formRow['data'][$thisColumn->getControlValue('column_name')], true);
								$listTableControls = $thisColumn->getControlValue("list_table_controls");
								foreach ($submittedData as $thisRow) {
									?>
                                    <div class='custom-field-row'>
										<?php
										foreach ($listTableControls as $fieldName => $fieldControls) {
										    $thisData = $thisRow[$fieldName];
										    if ($fieldControls['data_type'] == "tinyint") {
										        $thisData = (empty($thisRow[$fieldName]) ? "no" : "YES");
										    }
											?>
                                            <div class='form-line'>
                                                <label><?= $fieldControls['form_label'] ?></label>
                                                <span><?= $thisData ?></span>
                                            </div>
											<?php
										}
										?>
                                    </div>
									<?php
								}
							}
							break;
						case "signature":
							$thisControl = $formRow['data'][$thisColumn->getControlValue('column_name')];
							break;
						case "image_input":
						case "image":
							$thisControl = "<img class='form-image' src='/getimage.php?image_id=" . $formRow['data'][$thisColumn->getControlValue('column_name')] . "' />";
							break;
						case "file":
							$thisControl = (empty($formRow['data'][$thisColumn->getControlValue('column_name')]) ? "" : "<a href='/download.php?file_id=" . $formRow['data'][$thisColumn->getControlValue('column_name')] . "'>Download</a>");
							break;
						case "radio":
						case "select":
							$choices = $thisColumn->getChoices($this, true);
							$choiceSet = executeQuery("select * from form_field_choices where form_field_id = (select form_field_id from form_fields where form_field_code = ? and client_id = ?)", $thisColumn->getControlValue('column_name'), $GLOBALS['gClientId']);
							while ($choiceRow = getNextRow($choiceSet)) {
								$choices[$choiceRow['key_value']] = array("key_value" => $choiceRow['key_value'], "description" => $choiceRow['description']);
							}
							$thisColumn->setControlValue("choices", $choices);
						default:
							$thisControl = $thisColumn->getControl($this);
							$thisControl = str_replace("</textarea>", "</div>", $thisControl);
							$thisControl = str_replace("<textarea", "<div class='textarea-div'", $thisControl);
							break;
					}
					$line = str_replace("%input_control%", $thisControl, $line);
				}

				$userSubstitutions = array("first_name" => $contactRow['first_name'],
					"last_name" => $contactRow['last_name'],
					"display_name" => getDisplayName($contactRow['contact_id']),
					"address_1" => $contactRow['address_1'],
					"address_2" => $contactRow['address_2'],
					"city" => $contactRow['city'],
					"state" => $contactRow['state'],
					"city_state" => $contactRow['city'] . (!empty($contactRow['city']) && !empty($contactRow['state']) ? ", " : "") . $contactRow['state'],
					"postal_code" => $contactRow['postal_code'],
					"country_name" => getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id']),
					"email_address" => $contactRow['email_address'],
					"business_name" => $contactRow['business_name']);
				if (function_exists("_localPageSubstitutions")) {
					$additionalSubstitutions = _localPageSubstitutions();
					if (is_array($additionalSubstitutions)) {
						$userSubstitutions = array_merge($userSubstitutions, $additionalSubstitutions);
					}
				}
				foreach ($userSubstitutions as $fieldName => $fieldData) {
                    $fieldData = is_scalar($fieldData) ? $fieldData : "";
                    $line = str_ireplace("%" . $fieldName . "%", $fieldData, $line);
				}
				if (strpos($line, "%date:") !== false) {
					$offset = strpos($line, "%date:");
					while ($offset !== false) {
						$endOffset = strpos($line, "%", $offset + 5);
						if ($endOffset === false) {
							break;
						}
						$dateFormat = substr($line, $offset, ($endOffset - $offset + 1));
						$line = str_replace($dateFormat, date(substr($dateFormat, 6, -1), strtotime($formRow['date_created'])), $line);
						$offset = strpos($line, "%date:");
					}
				}
				if (strpos($line, "%custom_field:") !== false) {
					$customSet = executeQuery("select * from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS') and inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
					while ($customRow = getNextRow($customSet)) {
						$customDataRow = getRowFromId("custom_field_data", "custom_field_id", $customRow['custom_field_id'], "primary_identifier = ?", $contactRow['contact_id']);
						if (empty($customDataRow)) {
							$fieldValue = "";
						} else {
							$dataType = getFieldFromId("control_value", "custom_field_controls", "custom_field_id", $customRow['custom_field_id'], "control_name = 'data_type'");
							$fieldValue = "";
							switch ($dataType) {
								case "date":
									$fieldValue = (empty($customDataRow['date_data']) ? "" : date("m/d/Y", strtotime($customDataRow['date_data'])));
									break;
                                case "bigint":
                                case "int":
									$fieldValue = $customDataRow['integer_data'];
									break;
								case "decimal":
									$fieldValue = number_format($customDataRow['number_data'], 2);
									break;
								case "tinyint":
									$fieldValue = ($customDataRow['text_data'] ? "Yes" : "No");
									break;
								default:
									$fieldValue = $customDataRow['text_data'];
									break;
							}
						}
						if (empty($fieldValue)) {
							$fieldValue = getFieldFromId("control_value", "custom_field_controls", "custom_field_id", $customRow['custom_field_id'], "control_name = 'default_value'");
						}
						$line = str_ireplace("%custom_field:" . $customRow['custom_field_code'] . "%", $fieldValue, $line);
					}
				}

				$paymentInformation = "";
				if (!empty($formRow['donation_id'])) {
					$donationRow = getRowFromId("donations", "donation_id", $formRow['donation_id']);
					$paymentInformation = "Payment of $" . $donationRow['amount'] . " for " . getFieldFromId("description", "designations", "designation_id", $donationRow['designation_id']);
				}
				$line = str_replace("%payment_block%", $paymentInformation, $line);

				echo $line . "\n";
			}
			?>
        </div>
		<?php
	}

	function createColumn($formFieldCode, $formDefinitionId) {
		$resultSet = executeQuery("select * from form_fields where form_field_code = ? and client_id = ?", $formFieldCode, $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$columnControls = array("form_label" => $row['form_label'], "column_name" => $row['form_field_code']);
			$resultSet = executeQuery("select * from form_field_controls where form_field_id = ?", $row['form_field_id']);
			while ($controlRow = getNextRow($resultSet)) {
				$columnControls[$controlRow['control_name']] = DataSource::massageControlValue($controlRow['control_name'], $controlRow['control_value']);
			}
		} else {
			$columnControls = array("column_name" => $formFieldCode, "form_label" => "");
		}
		$resultSet = executeQuery("select * from form_definition_controls where form_definition_id = ? and column_name = ?",
			$formDefinitionId, $columnControls['column_name']);
		while ($controlRow = getNextRow($resultSet)) {
			$columnControls[$controlRow['control_name']] = DataSource::massageControlValue($controlRow['control_name'], $controlRow['control_value']);
		}
		$thisColumn = new DataColumn($row['form_field_code'], $columnControls);
		$dataType = $thisColumn->getControlValue("data_type");
		if ($dataType == "custom") {
			$this->iCustomFields[] = $row['form_field_id'];
			$thisColumn->setControlValue("primary_table", "forms");
		}
		return $thisColumn;
	}

	function internalCSS() {
		?>
        <style>
            body {
                width: 1000px;
                margin: 20px auto;
            }
            .textarea-div {
                border: 1px solid rgb(200, 200, 200);
                padding: 10px;
                font-size: 14px;
                margin-left: 215px;
                border-radius: 5px;
            }
            .form-image {
                max-width: 400px;
            }
            .field-label {
                white-space: normal;
            }
            .generate-form {
                display: none;
            }
            table {
                margin-left: auto;
                margin-right: auto;
            }
            #_form_div {
                margin-bottom: 40px;
            }
            .custom-field-row {
                padding: 10px;
                border: 1px solid rgb(200,200,200);
                background-color: rgb(240,240,240);
                margin: 10px;
            }
        </style>
		<?php
	}

	function javascript() {
		$formId = getFieldFromId("form_id", "forms", "form_id", $_GET['form_id']);
		$resultSet = executeQuery("select * from forms where form_id = ?", $formId);
		$formRow = getNextRow($resultSet);
		$formRow['data'] = array();
		$resultSet = executeQuery("select * from contacts where contact_id = ?", $formRow['contact_id']);
		if (!$contactRow = getNextRow($resultSet)) {
			$contactRow = array();
		}
		$resultSet = executeQuery("select * from form_data join form_fields using (form_field_id) where form_id = ?", $formRow['form_id']);
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['integer_data'])) {
				$fieldValue = $row['integer_data'];
			} else if (!empty($row['number_data'])) {
				$fieldValue = $row['number_data'];
			} else if (!empty($row['date_data'])) {
				$fieldValue = (empty($row['date_data']) ? "" : date("m/d/Y", strtotime($row['date_data'])));
			} else if (!empty($row['image_id'])) {
				$fieldValue = $row['image_id'];
			} else if (!empty($row['file_id'])) {
				$fieldValue = $row['file_id'];
			} else {
				$fieldValue = $row['text_data'];
			}
			$formRow['data'][$row['form_field_code']] = $fieldValue;
		}
		$resultSet = executeQuery("select * from form_definition_contact_fields where form_definition_id = ?", $formRow['form_definition_id']);
		while ($row = getNextRow($resultSet)) {
			$contactColumnName = getFieldFromId("column_name", "column_definitions", "column_definition_id", $row['column_definition_id']);
			$contactDataType = getFieldFromId("column_type", "column_definitions", "column_definition_id", $row['column_definition_id']);
			$formFieldCode = getFieldFromId("form_field_code", "form_fields", "form_field_id", $row['form_field_id']);
			switch ($contactDataType) {
				case "date":
                    $dateValue = $formRow['data'][$formFieldCode] ?: $contactRow[$contactColumnName];
					$formRow['data'][$formFieldCode] = (empty($dateValue) ? "" : date("m/d/Y", strtotime($dateValue)));
					break;
				default:
					if (empty($formRow['data'][$formFieldCode])) {
						$formRow['data'][$formFieldCode] = $contactRow[$contactColumnName];
					}
			}
		}
		$resultSet = executeQuery("select * from form_definition_phone_number_fields where form_definition_id = ?", $formRow['form_definition_id']);
		while ($row = getNextRow($resultSet)) {
			$formFieldCode = getFieldFromId("form_field_code", "form_fields", "form_field_id", $row['form_field_id']);
			if (empty($formRow['data'][$formFieldCode])) {
				$formRow['data'][$formFieldCode] = Contact::getContactPhoneNumber($formRow['contact_id'],$row['description'],false);
			}
		}
		?>
        var returnArray = <?= jsonEncode($formRow['data']) ?>;
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(".generate-form").remove();
            for (const i in returnArray) {
                if (empty(i)) {
                    continue;
                }
                if ($("input[type=radio][name='" + i + "']").length > 0) {
                    $("input[type=radio][name='" + i + "']").prop("checked", false);
                    $("input[type=radio][name='" + i + "'][value='" + returnArray[i] + "']").prop("checked", true);
                } else if ($("#" + i).is("input[type=checkbox]")) {
                    $("#" + i).prop("checked", returnArray[i] === 1);
                } else if ($("input[name=" + i + "]").is("input[type=radio]")) {
                    $("input[name=" + i + "][value=" + returnArray[i] + "]").prop("checked", true);
                } else if ($("#" + i).is("a")) {
                    $("#" + i).attr("href", returnArray[i]).css("display", (empty(returnArray[i]) ? "none" : "inline"));
                } else if ($("#" + i).is("div") || $("#" + i).is("span") || $("#" + i).is("td") || $("#" + i).is("tr")) {
                    $("#" + i).html(returnArray[i]);
                } else if ($("#_" + i + "_table").is(".editable-list")) {
                    $("#_" + i + "_table tr").not(":first").not(":last").remove();
                    const editableListRows = JSON.parse(returnArray[i]);
                    for (const j in editableListRows) {
                        const editableListValues = {};
                        for (const k in editableListRows[j]) {
                            editableListValues[k] = {};
                            editableListValues[k]['data_value'] = editableListRows[j][k];
                        }
                        addEditableListRow(i, editableListValues);
                    }
                } else {
                    $("#" + i).val(returnArray[i]);
                }
            }
            $(".view-image-link").each(function () {
                const imageId = $(this).closest("td").find("input[type=hidden]").val();
                if (!empty(imageId)) {
                    $(this).attr("href", "/getimage.php?id=" + imageId).show();
                }
            });
            $(".download-file-link").each(function () {
                const fileId = $(this).closest("td").find("input[type=hidden]").val();
                if (!empty(fileId)) {
                    $(this).attr("href", "/download.php?id=" + fileId).show();
                }
            });
        </script>
		<?php
	}

	function jqueryTemplates() {
		if (!empty($this->iCustomFields)) {
			$resultSet = executeQuery("select * from form_fields where form_field_id in (" . implode(",", $this->iCustomFields) . ")");
			while ($row = getNextRow($resultSet)) {
				$thisColumn = $this->createColumn($row['form_field_code'], $this->iFormDefinitionId);
				$thisColumn->setControlValue('readonly', true);
				$controlSet = executeQuery("select * from form_field_controls where form_field_id = ?", $row['form_field_id']);
				while ($controlRow = getNextRow($controlSet)) {
					$thisColumn->setControlValue($controlRow['control_name'], $controlRow['control_value']);
				}
				if ($thisColumn->getControlValue("data_type") == "custom") {
					$controlClass = $thisColumn->getControlValue('control_class');
					$customControl = new $controlClass($thisColumn, $this);
					echo $customControl->getTemplate();
				}
			}
		}
	}
}

$pageObject = new DisplayFormPage();
$pageObject->displayPage();
