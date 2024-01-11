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

$GLOBALS['gPageCode'] = "GENERATEFORM";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gEmbeddablePage'] = true;
require_once "shared/startup.inc";

$formCode = $_GET['code'];
if (empty($formCode)) {
	$formCode = $_GET['form'];
}
if (empty($formCode)) {
	$formCode = $_POST['form_definition_code'];
}
if (empty($formCode)) {
	$GLOBALS['gFormDefinitionId'] = getFieldFromId("form_definition_id", "form_definitions", "form_definition_id", $_GET['form_id'],
		"client_id = " . $GLOBALS['gClientId'] . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and inactive = 0") .
		($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
} else {
	$GLOBALS['gFormDefinitionId'] = getFieldFromId("form_definition_id", "form_definitions", "form_definition_code", $formCode,
		"client_id = " . $GLOBALS['gClientId'] . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and inactive = 0") .
		($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
}
if (empty($GLOBALS['gFormDefinitionId'])) {
	header("Location: /");
	exit;
}

class GenerateFormPage extends Page {

	private $iContactRecords = array();
	private $iInProgressFormCode = "";
	private $iInProgressFormId = "";
	private $iCustomFields = array();
	private $iFormDefinitionId = "";

	function headerIncludes() {
		$resultSet = executeQuery("select control_value from form_definition_controls where form_definition_id = ? and control_value in ('signature','ck-editor') union " .
			"select control_value from form_field_controls where form_field_id in (select form_field_id from form_fields where client_id = ? and control_value in ('signature','ck-editor'))", $GLOBALS['gFormDefinitionId'], $GLOBALS['gClientId']);
		$requiresSignature = false;
		$requiresEditor = false;
		while ($row = getNextRow($resultSet)) {
			if ($row['control_value'] == "signature") {
				$requiresSignature = true;
			}
			if ($row['control_value'] == "ck-editor") {
				$requiresEditor = true;
			}
			if ($requiresSignature && $requiresEditor) {
				break;
			}
		}
		if ($requiresSignature) {
			?>
			<script src="<?= autoVersion('/js/jsignature/jSignature.js') ?>"></script>
			<script src="<?= autoVersion('/js/jsignature/jSignature.CompressorSVG.js') ?>"></script>
			<script src="<?= autoVersion('/js/jsignature/jSignature.UndoButton.js') ?>"></script>
			<script src="<?= autoVersion('/js/jsignature/signhere/jSignature.SignHere.js') ?>"></script>
		<?php } ?>
		<?php if ($requiresEditor) { ?>
			<script>
                var CKEDITOR_BASEPATH = "/ckeditor/";
			</script>
			<script src="<?= autoVersion("/ckeditor/ckeditor.js") ?>"></script>
			<?php
		}
	}

	function setup() {
		$this->iInProgressFormCode = $_GET['in_progress'];
		$this->iInProgressFormId = getFieldFromId("in_progress_form_id", "in_progress_forms", "in_progress_form_code", $this->iInProgressFormCode);
		if (empty($this->iInProgressFormId)) {
			$this->iInProgressFormCode = "";
		}
		$resultSet = executeQuery("select * from form_definitions where form_definition_id = ?", $GLOBALS['gFormDefinitionId']);
		$formDefinitionRow = getNextRow($resultSet);
		$this->iFormDefinitionId = $formDefinitionRow['form_definition_id'];
		if (!empty($formDefinitionRow['action_filename'])) {
			include_once "actions/" . $formDefinitionRow['action_filename'];
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_discount_amount":
				$formDefinitionDiscountRow = getRowFromId("form_definition_discounts", "discount_code", strtoupper($_POST['discount_code']), "form_definition_id = ?", $_POST['form_definition_id']);
				$returnArray['form_definition_discounts'] = $formDefinitionDiscountRow;
				ajaxResponse($returnArray);
				break;
			case "save_field":
				$fieldId = $_POST['field_id'];
				$fieldValue = $_POST['field_value'];
				$inProgressFormId = getFieldFromId("in_progress_form_id", "in_progress_forms", "in_progress_form_id", $_POST['in_progress_form_id']);
				$formDefinitionId = $_POST['form_definition_id'];
				if (empty($inProgressFormId) && empty($formDefinitionId)) {
					ajaxResponse($returnArray);
					break;
				}
				$saveProgress = getFieldFromId("save_progress", "form_definitions", "form_definition_id", $formDefinitionId);
				if (empty($saveProgress)) {
					ajaxResponse($returnArray);
					break;
				}
				if (empty($inProgressFormId)) {
					$inProgressFormCode = getRandomString(6);
					$resultSet = executeQuery("insert into in_progress_forms (in_progress_form_code,description,form_definition_id,date_created) values " .
						"(?,?,?,now())", $inProgressFormCode, getFieldFromId("description", "form_definitions", "form_definition_id", $formDefinitionId),
						$formDefinitionId);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						ajaxResponse($returnArray);
						break;
					}
					$inProgressFormId = $resultSet['insert_id'];
					$returnArray['in_progress_form_code'] = $inProgressFormCode;
					$returnArray['in_progress_form_id'] = $inProgressFormId;
				} else {
					$returnArray['in_progress_form_code'] = getFieldFromId("in_progress_form_code", "in_progress_forms", "in_progress_form_id", $inProgressFormId);
				}
				$resultSet = executeQuery("select * from form_fields where form_field_code = ? and client_id = ?", strtolower($fieldId), $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$thisColumn = $this->createColumn($row['form_field_code'], $formDefinitionId);
					$dataType = $thisColumn->getControlValue("data_type");
					switch ($dataType) {
						case "tinyint":
							$fieldValue = (empty($fieldValue) ? 0 : 1);
							break;
						default:
							break;
					}
					$resultSet = executeQuery("select * from in_progress_form_data where in_progress_form_id = ? and form_field_id = ?",
						$inProgressFormId, $row['form_field_id']);
					if ($formDataRow = getNextRow($resultSet)) {
						$updateSet = executeQuery("update in_progress_form_data set content = ? where in_progress_form_data_id = ?",
							$fieldValue, $formDataRow['in_progress_form_data_id']);
						if (!empty($updateSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
							ajaxResponse($returnArray);
							break;
						}
					} else {
						$insertSet = executeQuery("insert into in_progress_form_data (in_progress_form_id,form_field_id,content) values " .
							"(?,?,?)", $inProgressFormId, $row['form_field_id'], $fieldValue);
						if (!empty($insertSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
							ajaxResponse($returnArray);
							break;
						}
					}
				}
				ajaxResponse($returnArray);
				break;
			case "save_changes":
				if (!empty($_POST['_add_hash'])) {
					$resultSet = $this->iDatabase->executeQuery("select * from add_hashes where add_hash = ?", $_POST['_add_hash']);
					if ($row = $this->iDatabase->getNextRow($resultSet)) {
						$returnArray['error_message'] = "This form has already been saved";
						ajaxResponse($returnArray);
						break;
					}
				}
				if (empty($_POST['form_definition_id'])) {
					$_POST['form_definition_id'] = getFieldFromId("form_definition_id", "form_definitions", "form_definition_code", $_POST['form_definition_code']);
				}
				$formDefinitionId = getFieldFromId("form_definition_id", "form_definitions", "form_definition_id", $_POST['form_definition_id'],
					"client_id = " . $GLOBALS['gClientId'] . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and inactive = 0") .
					($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
				if (empty($formDefinitionId)) {
					$returnArray['error_message'] = "Invalid Form";
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("select * from form_definitions where form_definition_id = ?", $formDefinitionId);
				$formDefinitionRow = getNextRow($resultSet);

				if (!empty($formDefinitionRow['use_captcha'])) {
					$captchaCode = getFieldFromId("captcha_code", "captcha_codes", "captcha_code_id", $_POST['captcha_code_id']);
					if (empty($_POST['captcha_code']) || strtoupper($captchaCode) != strtoupper($_POST['captcha_code'])) {
						$returnArray['error_message'] = "Invalid captcha code";
						ajaxResponse($returnArray);
						break;
					}
				}

				if (!empty($formDefinitionRow['action_filename'])) {
					include_once "actions/" . $formDefinitionRow['action_filename'];
				}

				$this->iDatabase->startTransaction();
				if (!empty($_POST['_add_hash'])) {
					executeQuery("insert into add_hashes (add_hash,date_used) values (?,now())", $_POST['_add_hash']);
				}

				if (!empty($_POST['in_progress_form_id'])) {
					executeQuery("delete from in_progress_form_data where in_progress_form_id = ?", $_POST['in_progress_form_id']);
					executeQuery("delete from in_progress_forms where in_progress_form_id = ?", $_POST['in_progress_form_id']);
				}

				$contactFields = array();
				$resultSet = executeQuery("select * from form_definition_contact_fields where form_definition_id = ?", $formDefinitionId);
				while ($row = getNextRow($resultSet)) {
					$contactColumnName = getFieldFromId("column_name", "column_definitions", "column_definition_id", $row['column_definition_id']);
					$formFieldCode = getFieldFromId("form_field_code", "form_fields", "form_field_id", $row['form_field_id']);
					if (!empty($contactColumnName) && !empty($formFieldCode)) {
						$contactFields[$formFieldCode] = $contactColumnName;
					}
				}
				$createContact = (!empty($contactFields));
				$contactFieldValues = array();
				$formDescription = $formDefinitionRow['form_description'];
				if (empty($formDescription)) {
					$formDescription = $formDefinitionRow['description'];
				}
				if (function_exists("beforeSaveGenerateForm")) {
					$returnValue = beforeSaveGenerateForm($formDefinitionRow['form_definition_code']);
					if ($returnValue && $returnValue !== true) {
						$returnArray['error_message'] = $returnValue;
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}

				$saveForm = true;
				if (function_exists("actionSaveGenerateForm")) {
					if (actionSaveGenerateForm($formDefinitionRow)) {
						$saveForm = false;
					}
				}
				$mailingListIds = array();
				if ($saveForm) {
					$formDataSource = new DataSource("forms");
					$formDataSource->disableTransactions();
					$formId = $formDataSource->saveRecord(array("name_values" => array("form_definition_id" => $formDefinitionId, "description" => $formDescription, "content" => jsonEncode($_POST),
						"date_created" => date("Y-m-d"), "time_created" => date("Y-m-d H:i:s"), "parent_form_id" => $_POST['parent_form_id'], "user_id" => $GLOBALS['gUserId'], "ip_address" => $_SERVER['REMOTE_ADDR'], "referer" => $_SERVER['HTTP_REFERER'])));
					if (empty($formId)) {
						$returnArray['error_message'] = $formDataSource->getErrorMessage();
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					addActivityLog("Submitted form '" . $formDefinitionRow['description'] . "'");
					$postFields = array();
					$customPostFields = array();
					$alertFields = array();
					$resultSet = executeQuery("select * from form_fields where client_id = ?", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$thisColumn = $this->createColumn($row['form_field_code'], $formDefinitionRow['form_definition_id']);
						$dataType = $thisColumn->getControlValue("data_type");
						if ($dataType == "custom" || array_key_exists(strtolower($row['form_field_code']), $_POST)) {
							$fieldValue = $_POST[$row['form_field_code']];
							$alertFieldValues = $thisColumn->getControlValue("alert_values");
							if (!empty($alertFieldValues)) {
								if (!is_array($alertFieldValues)) {
									$alertFieldValues = array($alertFieldValues);
								}
								if (in_array(strtolower($fieldValue), array_map("strtolower", $alertFieldValues))) {
									$alertFields[$row['form_field_code']] = array("description" => $row['description'], "value" => $fieldValue);
								}
							}
							$integerData = $numberData = $textData = $dateData = $imageId = $fileId = "";
							$writeField = true;
							switch ($dataType) {
								case "bigint":
								case "int":
									$integerData = $fieldValue = $this->iDatabase->makeNumberParameter($fieldValue);
									break;
								case "decimal":
									$numberData = $fieldValue = $this->iDatabase->makeNumberParameter($fieldValue);
									break;
								case "date":
									$dateData = $fieldValue = $this->iDatabase->makeDateParameter($fieldValue);
									break;
								case "custom":
									$controlClass = $thisColumn->getControlValue('control_class');
									$customControl = new $controlClass($thisColumn, $this);
									$textData = $fieldValue = $customControl->saveData($_POST);
									break;
								case "image_input":
								case "image":
									if (array_key_exists($row['form_field_code'] . "_file", $_FILES) && !empty($_FILES[$row['form_field_code'] . '_file']['name'])) {
										$maxDimension = $thisColumn->getControlValue('maximum_dimension');
										$maxWidth = $thisColumn->getControlValue('maximum_width');
										if (empty($maxWidth)) {
											$maxWidth = $maxDimension;
										}
										$maxHeight = $thisColumn->getControlValue('maximum_height');
										if (empty($maxHeight)) {
											$maxHeight = $maxDimension;
										}
										$imageId = createImage($row['form_field_code'] . "_file", array("maximum_width" => $maxWidth, "maximum_height" => $maxHeight));
										if ($imageId == false) {
											$returnArray['error_message'] = "Error creating image";
											$this->iDatabase->rollbackTransaction();
											ajaxResponse($returnArray);
											break;
										}
										$fieldValue = $imageId;
									}
									break;
								case "file":
									if (array_key_exists($row['form_field_code'] . "_file", $_FILES) && !empty($_FILES[$row['form_field_code'] . '_file']['name'])) {
										$originalFilename = $_FILES[$row['form_field_code'] . '_file']['name'];
										if (array_key_exists($_FILES[$row['form_field_code'] . '_file']['type'], $GLOBALS['gMimeTypes'])) {
											$extension = $GLOBALS['gMimeTypes'][$_FILES[$row['form_field_code'] . '_file']['type']];
										} else {
											$fileNameParts = explode(".", $_FILES[$row['form_field_code'] . '_file']['name']);
											$extension = $fileNameParts[count($fileNameParts) - 1];
										}
										$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
										if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
											$maxDBSize = 1000000;
										}
										if ($_FILES[$row['form_field_code'] . '_file']['size'] < $maxDBSize) {
											$fileContent = file_get_contents($_FILES[$row['form_field_code'] . '_file']['tmp_name']);
											$osFilename = "";
										} else {
											$fileContent = "";
											$osFilename = "/documents/tmp." . $extension;
										}
										$insertSet = executeQuery("insert into files (file_id,client_id,description,date_uploaded," .
											"filename,extension,file_content,os_filename,public_access,all_user_access,administrator_access,user_group_id," .
											"sort_order,version) values (null,?,?,now(),?,?,?,?,0,0,1,?,0,1)", $GLOBALS['gClientId'], $row['form_field_code'] . " of forms",
											$originalFilename, $extension, $fileContent, $osFilename, $formDefinitionRow['user_group_id']);
										if (!empty($insertSet['sql_error'])) {
											$returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
											$this->iDatabase->rollbackTransaction();
											ajaxResponse($returnArray);
											break;
										}
										$fileId = $fieldValue = $insertSet['insert_id'];
										if (!empty($osFilename)) {
											putExternalFileContents($fileId, $extension, file_get_contents($_FILES[$row['form_field_code'] . '_file']['tmp_name']));
										}
									}
									break;
								case "tinyint":
									$integerData = $fieldValue = (empty($fieldValue) ? 0 : 1);
									break;
								default:
									$textData = $fieldValue;
									if (strlen($fieldValue) == 0) {
										$writeField = false;
									}
									break;
							}
							if (!$writeField) {
								continue;
							}
							$usedInContacts = false;
							if ($createContact) {
								foreach ($contactFields as $formFieldCode => $contactColumnName) {
									if ($formFieldCode == $row['form_field_code']) {
										if ($thisColumn->getControlValue("data_type") == "select" && $thisColumn->getControlValue("use_description")) {
											$choices = $thisColumn->getControlValue("choices");
											if (!is_array($choices)) {
												$choices = array();
											}
											$choiceSet = executeQuery("select * from form_field_choices where form_field_id = ?", $row['form_field_id']);
											while ($choiceRow = getNextRow($choiceSet)) {
												$choices[$choiceRow['key_value']] = $choiceRow['description'];
											}
											if (empty($choices) && !empty($thisColumn->getControlValue("get_choices"))) {
												$choiceFunction = $thisColumn->getControlValue('get_choices');
												if (function_exists($choiceFunction)) {
													$choices = $choiceFunction(false);
												}
											}
											$contactFieldValues[$contactColumnName] = (is_array($choices[$fieldValue]) ? $choices[$fieldValue]['description'] : $choices[$fieldValue]);
										} else {
											$contactFieldValues[$contactColumnName] = $fieldValue;
										}
										$usedInContacts = true;
									}
								}
								$phoneSet = executeQuery("select * from form_definition_phone_number_fields where form_definition_id = ?", $formDefinitionId);
								while ($phoneRow = getNextRow($phoneSet)) {
									$phoneFormFieldCode = getFieldFromId("form_field_code", "form_fields", "form_field_id", $phoneRow['form_field_id']);
									if ($phoneFormFieldCode == $row['form_field_code']) {
										$usedInContacts = true;
									}
								}
							}
							$postFields[$row['form_field_code']] = array("value" => $fieldValue, "description" => $row['description'], "data_type" => $dataType, "custom_field_id" => $row['custom_field_id']);
							if ($dataType == "custom") {
								$customPostFields[$row['form_field_code']] = $fieldValue;
							}
							if ($thisColumn->getControlValue("data_type") == "select" || $thisColumn->getControlValue("data_type") == "radio") {
								$choices = $thisColumn->getControlValue("choices");
								if (!is_array($choices)) {
									$choices = array();
								}
								$choiceSet = executeQuery("select * from form_field_choices where form_field_id = ?", $row['form_field_id']);
								while ($choiceRow = getNextRow($choiceSet)) {
									$choices[$choiceRow['key_value']] = $choiceRow['description'];
								}
								if (empty($choices) && !empty($thisColumn->getControlValue("get_choices"))) {
									$choiceFunction = $thisColumn->getControlValue('get_choices');
									if (function_exists($choiceFunction)) {
										$choices = $choiceFunction(false);
									}
								}
								$postFields[$row['form_field_code']]['text'] = (is_array($choices[$fieldValue]) ? $choices[$fieldValue]['description'] : $choices[$fieldValue]);
							}
							if (strlen($integerData) > 0 || strlen($numberData) > 0 || strlen($textData) > 0 || strlen($dateData) > 0 || !empty($imageId) || !empty($fileId)) {
								$insertSet = executeQuery("insert into form_data (form_id,form_field_id,integer_data,number_data,text_data,date_data,image_id,file_id) values " .
									"(?,?,?,?,?,?,?,?)", $formId, $row['form_field_id'], $integerData, $numberData, $textData, $dateData, $imageId, $fileId);
								if (!empty($insertSet['sql_error'])) {
									$returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
									$this->iDatabase->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
								if (!empty($fileId)) {
									executeQuery("insert into form_attachments (form_id,description,file_id) values (?,?,?)",
										$formId, getFieldFromId("description", "form_fields", "form_field_id", $row['form_field_id']), $fileId);
								}
							}
							if (startsWith($row['form_field_code'], "mailing_list_") && $dataType == "tinyint" && !empty($fieldValue)) {
								$mailingListId = getFieldFromId("mailing_list_id", "mailing_lists", "mailing_list_code", substr($row['form_field_code'], strlen("mailing_list_")));
								if (!empty($mailingListId)) {
									$mailingListIds[] = $mailingListId;
								}
							}
						}
					}
					if ($createContact) {
						if (empty($formDefinitionRow['use_user_contact']) || !$GLOBALS['gLoggedIn']) {
							$contactId = "";
							if (function_exists("_localFormMatchContact")) {
								$contactId = _localFormMatchContact($formDefinitionRow, $contactFieldValues);
							}
							if (empty($contactId) && !empty($contactFieldValues['email_address']) && empty($_POST['payment_amount'])) {
								if (!empty($_POST['user_name']) || !empty($_POST['password'])) {
									$whereStatement = "deleted = 0 and client_id = ? and contact_id not in (select contact_id from accounts) and " .
										"contact_id not in (select contact_id from donations) and contact_id not in (select contact_id from orders)";
								} else {
									$whereStatement = "deleted = 0 and client_id = ?";
								}
								$whereParameters = array($GLOBALS['gClientId']);
								foreach ($contactFieldValues as $fieldName => $fieldValue) {
									$whereParameters[] = $fieldValue;
									$whereStatement .= " and " . $fieldName . " = ?";
								}
								$resultSet = executeQuery("select contact_id from contacts where " . $whereStatement, $whereParameters);
								if ($row = getNextRow($resultSet)) {
									$contactId = $row['contact_id'];
								}
							}
							if (empty($contactId)) {
								$contactTable = new DataTable("contacts");
								$parameterArray = array("date_created" => date("Y-m-d"));
								foreach ($contactFieldValues as $fieldName => $fieldValue) {
									$parameterArray[$fieldName] = $fieldValue;
								}
								if (empty($parameterArray['country_id'])) {
									$parameterArray['country_id'] = "1000";
								}
								if (!empty($formDefinitionRow['contact_type_id']) && empty($parameterArray['contact_type_id'])) {
									$parameterArray['contact_type_id'] = $formDefinitionRow['contact_type_id'];
								}
								if (!empty($_POST['source_id'])) {
									$parameterArray['source_id'] = getFieldFromId("source_id", "sources", "source_id", $_POST['source_id']);
								} else if (!empty($_POST['source_code'])) {
									$parameterArray['source_id'] = getFieldFromId("source_id", "sources", "source_code", $_POST['source_code']);
								}
								if (empty($parameterArray['source_id'])) {
									$sourceId = getFieldFromId("source_id", "sources", "source_id", $_COOKIE['source_id'], "inactive = 0");
									if (empty($sourceId)) {
										$sourceId = getSourceFromReferer($_SERVER['HTTP_REFERER']);
									}
									$parameterArray['source_id'] = $sourceId;
								}
								unset($_FILES['image_id_file']);
								$contactId = $contactTable->saveRecord(array("name_values" => $parameterArray));
							}
							if (empty($contactId)) {
								$returnArray['error_message'] = getSystemMessage("basic", $contactTable->getErrorMessage());
								$this->iDatabase->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
							makeWebUserContact($contactId);
							$resultSet = executeQuery("select * from form_definition_phone_number_fields where form_definition_id = ?", $formDefinitionId);
							while ($row = getNextRow($resultSet)) {
								$formFieldCode = getFieldFromId("form_field_code", "form_fields", "form_field_id", $row['form_field_id']);
								if (!empty($postFields[$formFieldCode]['value'])) {
									executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,?)", $contactId, $postFields[$formFieldCode]['value'], $row['description']);
								}
							}
						}
					}
					if (empty($contactId) && $GLOBALS['gLoggedIn']) {
						$contactId = $GLOBALS['gUserRow']['contact_id'];
					}

					if (!empty($contactId)) {
						$resultSet = executeQuery("update forms set contact_id = ? where form_id = ?", $contactId, $formId);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						if (!empty($formDefinitionRow['category_id'])) {
							$contactCategoryId = getFieldFromId("contact_category_id", "contact_categories", "category_id", $formDefinitionRow['category_id'], "contact_id = ?", $contactId);
							if (empty($contactCategoryId)) {
								$contactCategoryDataTable = new DataTable("contact_categories");
								$contactCategoryDataTable->saveRecord(array("name_values" => array("contact_id" => $contactId, "category_id" => $formDefinitionRow['category_id'])));
							}
						}
						if (!empty($formDefinitionRow['remove_category_id'])) {
							$contactCategoryId = getFieldFromId("contact_category_id", "contact_categories", "category_id", $formDefinitionRow['remove_category_id'], "contact_id = ?", $contactId);
							if (empty($contactCategoryId)) {
								$contactCategoryDataTable = new DataTable("contact_categories");
								$contactCategoryDataTable->deleteRecord(array("primary_id" => $contactCategoryId));
							}
						}
						$resultSet = executeQuery("select * from form_definition_mailing_lists where form_definition_id = ? and mailing_list_id in (select mailing_list_id from mailing_lists where inactive = 0)",
							$formDefinitionRow['form_definition_id']);
						while ($row = getNextRow($resultSet)) {
							$mailingListId = $row['mailing_list_id'];
							$mailingListRow = getRowFromId("contact_mailing_lists", "mailing_list_id", $mailingListId, "contact_id = ?", $contactId);
							if (!empty($mailingListRow)) {
								if (!empty($mailingListRow['date_opted_out'])) {
									$contactMailingListTable = new DataTable("contact_mailing_lists");
									$contactMailingListTable->saveRecord(array("name_values" => array("date_opted_in" => date("Y-m-d"), "ip_address" => $_SERVER['REMOTE_ADDR'], "date_opted_out" => ""), "primary_id" => $mailingListRow['contact_mailing_list_id']));
								}
							} else {
								$contactMailingListTable = new DataTable("contact_mailing_lists");
								$contactMailingListTable->saveRecord(array("name_values" => array("date_opted_in" => date("Y-m-d"), "ip_address" => $_SERVER['REMOTE_ADDR'], "contact_id" => $contactId, "mailing_list_id" => $mailingListId)));
							}
						}
						if (!empty($mailingListIds)) {
							foreach ($mailingListIds as $mailingListId) {
								$mailingListRow = getRowFromId("contact_mailing_lists", "mailing_list_id", $mailingListId, "contact_id = ?", $contactId);
								if (!empty($mailingListRow)) {
									if (!empty($mailingListRow['date_opted_out'])) {
										$contactMailingListTable = new DataTable("contact_mailing_lists");
										$contactMailingListTable->saveRecord(array("name_values" => array("date_opted_in" => date("Y-m-d"), "ip_address" => $_SERVER['REMOTE_ADDR'], "date_opted_out" => ""), "primary_id" => $mailingListRow['contact_mailing_list_id']));
									}
								} else {
									$contactMailingListTable = new DataTable("contact_mailing_lists");
									$contactMailingListTable->saveRecord(array("name_values" => array("date_opted_in" => date("Y-m-d"), "ip_address" => $_SERVER['REMOTE_ADDR'], "contact_id" => $contactId, "mailing_list_id" => $mailingListId)));
								}
							}
						}
						foreach ($postFields as $thisField) {
							if (!empty($thisField['custom_field_id'])) {
								$customFieldCode = getFieldFromId("custom_field_code", "custom_fields", "custom_field_id", $thisField['custom_field_id'],
									"custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS')");
								CustomField::setCustomFieldData($contactId, $customFieldCode, $thisField['value']);
							}
						}
						$resultSet = executeQuery("select * from form_definition_contact_identifiers where form_definition_id = ?", $formDefinitionRow['form_definition_id']);
						$contactIdentifierDataTable = new DataTable("contact_identifiers");
						while ($row = getNextRow($resultSet)) {
							$formFieldCode = getFieldFromId("form_field_code", "form_fields", "form_field_id", $row['form_field_id']);
							if (!empty($formFieldCode) && array_key_exists(strtolower($formFieldCode), $_POST)) {
								$fieldValue = $_POST[$formFieldCode];
								if (!empty($fieldValue)) {
									$contactIdentifierId = getFieldFromId("contact_identifier_id", "contact_identifiers", "contact_id", $contactId,
										"contact_identifier_type_id = ?", $row['contact_identifier_type_id']);
									$contactIdentifierDataTable->saveRecord(array("name_values" => array("contact_id" => $contactId, "contact_identifier_type_id" => $row['contact_identifier_type_id'], "identifier_value" => $fieldValue), "primary_id" => $contactIdentifierId));
								}
							}
						}
					}
				}

				$notificationEmailAddresses = array();
				if (function_exists("afterSaveGenerateForm")) {
					$returnValue = afterSaveGenerateForm($formId, $contactId, $postFields);
					if (is_array($returnValue)) {
						$notificationEmailAddresses = $returnValue;
					} else if ($returnValue && $returnValue !== true) {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = $returnValue;
						ajaxResponse($returnArray);
						break;
					}
				}

				$pdfFileId = false;
				$domDocument = false;
				if (!empty($formDefinitionRow['create_contact_pdf']) && !empty($contactId) && !empty($_POST['_form_html'])) {
					$domDocument = new DOMDocument();
					$fragmentContent = $this->getFragment($formDefinitionRow['form_definition_code'] . "_PDF_CONTENT");
					if (empty($fragmentContent)) {
						$domDocument->loadHTML("<html><head></head><body><p>Submitted on " . date("m/d/Y g:i a") . "</p><div id='_main_content'>" . $_POST['_form_html'] . "</div></body></html>");
					} else {
						$fragmentContent = str_replace("%current_time%", date("m/d/Y g:i a"), $fragmentContent);
						$fragmentContent = str_replace("%current_date%", date("m/d/Y"), $fragmentContent);
						$domDocument->loadHTML($fragmentContent);
					}
					foreach ($_POST as $fieldName => $fieldValue) {
						if (empty($fieldValue) || $fieldName == "_form_html" || $fieldName == "_template_css_filename") {
							continue;
						}
						$domElement = $domDocument->getElementById($fieldName);
						$elementType = $domElement->nodeName;
						if (empty($elementType)) {
							$domElement = $domDocument->getElementById($fieldName . "_1");
							$elementType = $domElement->nodeName;
							if ($elementType == "input" && $domElement->getAttribute("type") == "radio") {
								$elementType = "radio";
							}
						}
						switch ($elementType) {
							case "radio":
								$elementNumber = 1;
								while (true) {
									$radioElement = $domDocument->getElementById($fieldName . "_" . $elementNumber++);
									$elementType = $radioElement->nodeName;
									if (empty($elementType)) {
										break;
									}
									if ($radioElement->getAttribute("value") == $fieldValue) {
										$radioElement->setAttribute("checked", "checked");
									}
								}
								break;
							case "input":
								$inputType = $domElement->getAttribute("type");
								switch ($inputType) {
									case "checkbox":
										if (!empty($fieldValue)) {
											$domElement->setAttribute("checked", "checked");
										}
										break;
									case "radio":
										$domElement->setAttribute("value", $fieldValue);
										break;
									case "hidden":
										$classes = $domElement->getAttribute("class");
										if (strpos($classes, "signature-field") !== false) {
											$formLineElement = $domDocument->getElementById("_" . $fieldName . "_row");
											$signatureDocument = new DOMDocument();
											$signatureDocument->loadHTML("<body>" . $fieldValue . "</body>");
											$signatureElement = $signatureDocument->getElementsByTagName('svg')->item(0);
											$formLineElement->replaceChild($domDocument->importNode($signatureElement, true), $domElement);
										}
										break;
									default:
										$domElement->setAttribute("value", $fieldValue);
										break;
								}
								break;
							case "select":
								foreach ($domElement->getElementsByTagName("option") as $option) {
									if ($option->getAttribute("value") == $fieldValue) {
										$option->setAttribute("selected", "selected");
										break;
									}
								}
								break;
							case "textarea":
							case "div":
							case "span":
							case "p":
							case "td":
								$domElement->nodeValue = $fieldValue;
								break;
						}
					}
					$removeElementIds = array("_submit_paragraph", "_return_url_div", "_signature_palette_parent");
					foreach ($removeElementIds as $removeElementId) {
						try {
							$domElement = $domDocument->getElementById($removeElementId);
							if (!empty($domElement)) {
								$domElement->parentNode->removeChild($domElement);
							}
						} catch (Exception $exception) {
						}
					}

					if (empty($fragmentContent)) {
						$includedCss = $GLOBALS['gDocumentRoot'] . "/css/reset.css";
						$cssContent = file_get_contents($GLOBALS['gDocumentRoot'] . "/css/reset.css");
						if (!empty($_POST['_template_css_filename'])) {
							$cssFilenames = explode(",", $_POST['_template_css_filename']);
							foreach ($cssFilenames as $cssFilename) {
								$cssFilename = preg_replace("/\.[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]\./", ".", $cssFilename);
								$cssFilenamePath = (substr($cssFilename, 0, 2) == "//" ? "https:" : $GLOBALS['gDocumentRoot']) . $cssFilename;
								$fileContent = file_get_contents($cssFilenamePath);
								$cssContent .= "\n" . $fileContent;
								$includedCss .= "\n" . $cssFilenamePath . " - " . strlen($fileContent) . " bytes";
							}
						}
						$cssContent = str_replace("./fonts/", "/fonts/", $cssContent);
						$cssContent = str_replace("/fonts/", $GLOBALS['gDocumentRoot'] . "/fonts/", $cssContent);
						$styleElement = $domDocument->createElement("style");
						$styleElement->nodeValue = $cssContent;
						$domDocument->getElementsByTagName("head")->item(0)->appendChild($styleElement);
					}

					$htmlContents = $domDocument->saveHTML();
					addProgramLog($htmlContents);
					$description = $formDefinitionRow['description'] . " - " . date("m/d/Y g:ia");
					$pdfFileId = outputPDF($htmlContents, array("create_file" => true, "filename" => "generatedform.pdf", "description" => $description));
					if (!empty($pdfFileId)) {
						$insertSet = executeQuery("insert into contact_files (contact_id,description,file_id) values (?,?,?)",
							$contactId, $description, $pdfFileId);
						executeQuery("insert into form_attachments (form_id,description,file_id) values (?,?,?)",
							$formId, $description, $pdfFileId);
					}
				}

				if (empty($_POST['email_address']) && $GLOBALS['gLoggedIn']) {
					$_POST['email_address'] = $GLOBALS['gUserRow']['email_address'];
				}
				$substitutions = $_POST;
				$substitutions['date_submitted'] = date("m/d/Y");
				$substitutions['form_id'] = $formId;
				if (!empty($_POST['email_address'])) {
					if (!empty($formDefinitionRow['email_id'])) {
						$emailParameters = array("email_credential_id" => $formDefinitionRow['email_credential_id'], "email_id" => $formDefinitionRow['email_id'], "substitutions" => $substitutions, "email_addresses" => $_POST['email_address'], "contact_id" => $contactId);
						if (!empty($pdfFileId)) {
							$emailParameters['attachment_file_id'] = $pdfFileId;
						}
						sendEmail($emailParameters);
					}
				}
				if (!empty($_POST['parent_form_id']) && !empty($formDefinitionRow['parent_form_email_id'])) {
					$parentFormRow = getRowFromId("forms", "form_id", $_POST['parent_form_id']);
					$emailAddress = Contact::getUserContactField($parentFormRow['user_id'], "email_address");
					if (empty($emailAddress)) {
						$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $parentFormRow['contact_id']);
					}
					if (empty($emailAddress)) {
						$emailAddress = getFieldFromId("text_data", "form_data", "form_id", $_POST['parent_form_id'], "form_field_id in (select form_field_id from form_fields where form_field_code = 'email_address')");
					}
					if (!empty($emailAddress)) {
						sendEmail(array("email_credential_id" => $formDefinitionRow['email_credential_id'], "email_id" => $formDefinitionRow['parent_form_email_id'], "substitutions" => $substitutions, "email_addresses" => $emailAddress));
					}
				}
				$resultSet = executeQuery("select * from form_definition_emails where form_definition_id = ?", $formDefinitionRow['form_definition_id']);
				while ($row = getNextRow($resultSet)) {
					$notificationEmailAddresses[] = $row['email_address'];
				}

				$formDescription = $formDefinitionRow['form_description'];
				foreach ($postFields as $fieldName => $fieldData) {
					$formDescription = str_replace("%" . $fieldName . "%", (empty($fieldData['text']) ? $fieldData['value'] : $fieldData['text']), $formDescription);
				}
				$formDescription = str_replace("%date_year%", date("Y"), $formDescription);
				$formDescription = str_replace("%date_month%", date("F"), $formDescription);
				$formDescription = str_replace("%date_day%", date("j"), $formDescription);
				if (strlen($formDescription) > 255) {
					$formDescription = mb_substr($formDescription, 0, 255);
				}
				if (empty($formDescription)) {
					$formDescription = $formDefinitionRow['description'];
				}
				executeQuery("update forms set description = ? where form_id = ?", trim($formDescription), $formId);

				if (!empty($notificationEmailAddresses)) {
					ob_start();
					?>
					<html lang="en">
					<head>
						<style>
                            table.field-values {
                                border-collapse: collapse;
                                border-spacing: 0;
                                border: 1px solid rgb(150, 150, 150);
                                margin: 20px 0;
                                width: 600px;
                            }

                            table.field-values td {
                                border: 1px solid rgb(150, 150, 150);
                                padding: 3px 10px;
                                vertical-align: top;
                            }

                            table.field-values td.label-line {
                                font-weight: bold;
                                width: 40%;
                            }

                            table.field-values td.highlight {
                                color: rgb(200, 0, 0);
                            }

                            table.field-values th {
                                text-align: left;
                                border: 1px solid rgb(150, 150, 150);
                                padding: 3px 10px;
                                font-weight: bold;
                                background-color: rgb(200, 200, 200);
                            }

                            p {
                                margin: 0;
                                padding: 0;
                                padding-bottom: 10px;
                            }
						</style>
					</head>
					<body>
					<?php
					$body = ob_get_clean();
					$body .= "<p>A form (" . $formDefinitionRow['description'] . ") was filled out and submitted.</p>";
					if (!empty($alertFields)) {
						$body .= "<p style='color: rgb(200,0,0);'>FORM CONTAINS HIGHLIGHTED ANSWERS:</p><ul>";
						foreach ($alertFields as $thisAlertField) {
							$body .= "<li>" . $thisAlertField['description'] . ": " . $thisAlertField['value'] . "</li>";
						}
						$body .= "</ul>";
					}
					$formInfo = "";
					foreach ($_POST as $fieldName => $formFieldData) {
						$foundCustom = false;
						if (!array_key_exists($fieldName, $postFields)) {
							foreach ($customPostFields as $formFieldCode => $customPostFieldValue) {
								if (substr($fieldName, 0, strlen($formFieldCode)) == $formFieldCode && strpos($fieldName, "-") !== false) {
									$foundCustom = true;
									break;
								}
							}
							if (!$foundCustom) {
								continue;
							}
						}
						$fieldData = ($foundCustom ? array("value" => $formFieldData) : $postFields[$fieldName]);
						if (empty($formInfo)) {
							$formInfo = "<table class='field-values'><tr><th class='label-line'>Field Name</th><th>Value Submitted</th></tr>";
						}
						$answerClass = (array_key_exists($fieldName, $alertFields) ? " class='highlight' " : "");
						if ($fieldData['data_type'] == "image" || $fieldData['data_type'] == "image_input") {
							$formInfo .= "<tr><td class='label-line'>" . (empty($fieldData['description']) ? str_replace("_", " ", $fieldName) : $fieldData['description']) . "</td>";
							$formInfo .= "<td" . $answerClass . "><a href='http://" . $_SERVER['HTTP_HOST'] . "/getimage.php?id=" . $fieldData['value'] . "'><img alt='Image' src='http://" . $_SERVER['HTTP_HOST'] . "/getimage.php?id=" . $fieldData['value'] . "' style='width: 200px'></a></td></tr>\n";
						} else if ($fieldData['data_type'] == "file") {
							$formInfo .= "<tr><td class='label-line'>" . (empty($fieldData['description']) ? str_replace("_", " ", $fieldName) : $fieldData['description']) . "</td>";
							$formInfo .= "<td" . $answerClass . "><a href='http://" . $_SERVER['HTTP_HOST'] . "/download.php?file_id=" . $fieldData['value'] . "'></a></td></tr>\n";
						} else if ($fieldData['data_type'] == "tinyint") {
							$formInfo .= "<tr><td class='label-line'>" . (empty($fieldData['description']) ? str_replace("_", " ", $fieldName) : $fieldData['description']) . "</td><td" . $answerClass . ">" . (empty($fieldData['value']) ? "NO" : "YES") . "</td></tr>\n";
						} else if ($fieldData['data_type'] == "signature") {
							$formInfo .= "<tr><td class='label-line'>" . (empty($fieldData['description']) ? str_replace("_", " ", $fieldName) : $fieldData['description']) . "</td><td" . $answerClass . ">" . (empty($fieldData['value']) ? "no" : "YES") . "</td></tr>\n";
						} else {
							$formInfo .= "<tr><td class='label-line'>" . (empty($fieldData['description']) ? str_replace("_", " ", $fieldName) : $fieldData['description']) . "</td><td" . $answerClass . ">" . (empty($fieldData['text']) ? makeHtml($fieldData['value']) : $fieldData['text']) . "</td></tr>\n";
						}
					}
					if (!empty($formInfo)) {
						$body .= "<p>Here is the form information:</p>\n" . $formInfo . "</table>\n";
					}
					if (!empty($_POST['parent_form_id'])) {
						$parentFormInformation = "";
						$contactId = getFieldFromId("contact_id", "forms", "form_id", $_POST['parent_form_id']);
						$resultSet = executeQuery("select * from contacts where contact_id = ?", $contactId);
						if ($contactRow = getNextRow($resultSet)) {
							foreach ($this->iContactRecords['contacts']['fields'] as $fieldName) {
								if (substr($fieldName, -3) != "_id" && !empty($contactRow[$fieldName])) {
									if (empty($parentFormInformation)) {
										$parentFormInformation = "<table class='field-values'><tr><th class='label-line'>Field Name</th><th>Value Submitted</th></tr>";
									}
									$parentFormInformation .= "<tr><td class='label-line'>" . ucwords(str_replace("_", " ", $fieldName)) . "</td><td>" . $contactRow[$fieldName] . "</td></tr>\n";
								}
							}
						}
						if (!empty($parentFormInformation)) {
							$body .= "<p>This form is connected with a previous form with the following information:</p>\n" . $parentFormInformation . "</table>\n";
						}
					}
					ob_start();
					?>
					</body>
					</html>
					<?php
					$body .= ob_get_clean();
					$emailParameters = array("subject" => $formDescription, "body" => $body, "email_addresses" => $notificationEmailAddresses, "html_already" => true);
					if (!empty($_POST['email_address'])) {
						$emailParameters['reply_email'] = $_POST['email_address'];
					}
					if (!empty($pdfFileId)) {
						$emailParameters['attachment_file_id'] = $pdfFileId;
					}
					$emailParameters["email_credential_id"] = $formDefinitionRow['email_credential_id'];
					sendEmail($emailParameters);
				}

				$donationId = "";
				$orderId = "";
				if (!empty($contactId) && !empty($_POST['payment_amount']) && !empty($formDefinitionRow['product_id']) && !empty($_POST['payment_method_id'])) {
					$contactRow = Contact::getContact($contactId);
					$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types",
						"payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id",
							$_POST['payment_method_id']));
					$isBankAccount = ($paymentMethodTypeCode == "BANK_ACCOUNT");

					$orderObject = new Order();
					$orderObject->setOrderField("contact_id", $contactId);
					$orderObject->setOrderField("user_id", $GLOBALS['gUserId']);
					$description = getFieldFromId("description", "products", "product_id", $formDefinitionRow['product_id']);
					$thisItem = array("product_id" => $formDefinitionRow['product_id'], "description" => $description, "sale_price" => $formDefinitionRow['amount'], "quantity" => 1);
					$orderObject->addOrderItem($thisItem);
					$orderObject->setOrderField("full_name", getDisplayName($contactId));
					$orderObject->setOrderField("payment_method_id", $_POST['payment_method_id']);

					$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']) . " - " . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4);
					$fullName = $_POST['first_name'] . " " . $_POST['last_name'] . (empty($_POST['business_name']) ? "" : ", " . $_POST['business_name']);
					$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name," .
						"account_number,expiration_date,inactive) values (?,?,?,?,?, ?,?)", $contactId, $accountLabel, $_POST['payment_method_id'],
						$fullName, "XXXX-" . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4),
						(empty($_POST['expiration_year']) ? "" : date("Y-m-d", strtotime($_POST['expiration_month'] . "/01/" . $_POST['expiration_year']))), 0);
					if (!empty($resultSet['sql_error'])) {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						ajaxResponse($returnArray);
						break;
					}
					$accountId = $resultSet['insert_id'];

					if (!$orderObject->generateOrder()) {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = $orderObject->getErrorMessage();
						ajaxResponse($returnArray);
						break;
					}
					$orderId = $orderObject->getOrderId();

					$merchantAccountId = $GLOBALS['gMerchantAccountId'];
					$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
					if (!$eCommerce) {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = "Unable to connect to Merchant Services. Please contact customer service.";
						ajaxResponse($returnArray);
						break;
					}
					$paymentArray = array("amount" => $formDefinitionRow['amount'], "order_number" => $orderId, "description" => "Order from form " . $formDefinitionRow['description'],
						"first_name" => (empty($contactRow['first_name']) ? $_POST['first_name'] : $contactRow['first_name']),
						"last_name" => (empty($contactRow['last_name']) ? $_POST['last_name'] : $contactRow['last_name']),
						"business_name" => (empty($contactRow['business_name']) ? $_POST['business_name'] : $contactRow['business_name']),
						"address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
						"postal_code" => (empty($_POST['billing_postal_code']) ? $_POST['postal_code'] : $_POST['billing_postal_code']),
						"country_id" => (empty($_POST['billing_country_id']) ? $_POST['country_id'] : $_POST['billing_country_id']),
						"email_address" => $_POST['email_address'], "contact_id" => $contactId);
					if ($isBankAccount) {
						$paymentArray['bank_routing_number'] = $_POST['routing_number'];
						$paymentArray['bank_account_number'] = $_POST['bank_account_number'];
						$paymentArray['bank_account_type'] = strtolower(str_replace("_", "", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id'])));
					} else {
						$paymentArray['card_number'] = $_POST['account_number'];
						$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
						$paymentArray['card_code'] = $_POST['cvv_code'];
					}
					$success = ($GLOBALS['gDevelopmentServer'] ? true : $eCommerce->authorizeCharge($paymentArray));
					$response = ($GLOBALS['gDevelopmentServer'] ? array("transaction_id" => "238559279234", "authorization_code" => "d92fwd") : $eCommerce->getResponse());
					if ($success) {
						$orderObject->createOrderPayment($formDefinitionRow['amount'], array("payment_method_id" => $_POST['payment_method_id'], "authorization_code" => $response['authorization_code'], "transaction_identifier" => $response['transaction_id']));
					} else {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
						$eCommerce->writeLog(($isBankAccount ? $paymentArray['bank_account_number'] : $paymentArray['card_number']), $response['response_reason_text'], true);
						ajaxResponse($returnArray);
						break;
					}
					executeQuery("update forms set order_id = ? where form_id = ?", $orderId, $formId);
				} else if (!empty($contactId) && !empty($_POST['payment_amount']) && !empty($formDefinitionRow['designation_id']) && !empty($_POST['designation_id']) && !empty($_POST['payment_method_id'])) {

					$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types",
						"payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id",
							$_POST['payment_method_id']));
					$isBankAccount = ($paymentMethodTypeCode == "BANK_ACCOUNT");

					$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']) . " - " . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4);
					$fullName = $_POST['first_name'] . " " . $_POST['last_name'] . (empty($_POST['business_name']) ? "" : ", " . $_POST['business_name']);
					$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name," .
						"account_number,expiration_date,inactive) values (?,?,?,?,?, ?,?)", $contactId, $accountLabel, $_POST['payment_method_id'],
						$fullName, "XXXX-" . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4),
						(empty($_POST['expiration_year']) ? "" : date("Y-m-d", strtotime($_POST['expiration_month'] . "/01/" . $_POST['expiration_year']))), 0);
					if (!empty($resultSet['sql_error'])) {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						ajaxResponse($returnArray);
						break;
					}
					$accountId = $resultSet['insert_id'];

					$donationFee = Donations::getDonationFee(array("designation_id" => $formDefinitionRow['designation_id'], "amount" => $_POST['payment_amount'], "payment_method_id" => $_POST['payment_method_id']));
					$donationCommitmentId = Donations::getContactDonationCommitment($contactId, $formDefinitionRow['designation_id']);
					$resultSet = executeQuery("insert into donations (client_id,contact_id,donation_date,payment_method_id," .
						"account_id,designation_id,amount,donation_fee,donation_commitment_id) values (?,?,now(),?,?, ?,?,?,?)",
						$GLOBALS['gClientId'], $contactId, $_POST['payment_method_id'], $accountId, $formDefinitionRow['designation_id'],
						$_POST['payment_amount'], $donationFee, $donationCommitmentId);
					if (!empty($resultSet['sql_error'])) {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						ajaxResponse($returnArray);
						break;
					}
					$donationId = $resultSet['insert_id'];
					Donations::processDonation($donationId);
					Donations::completeDonationCommitment($donationCommitmentId);
					addActivityLog("Made a donation for '" . getFieldFromId("description", "designations", "designation_id", $formDefinitionRow['designation_id']) . "' on form '" . $formDefinitionRow['description'] . "'");
					$requiresAttention = getFieldFromId("requires_attention", "designations", "designation_id", $formDefinitionRow['designation_id']);
					if ($requiresAttention) {
						sendEmail(array("subject" => "Designation Requires Attention", "body" => "Donation ID " . $donationId . " was created with a designation that requires attention.", "email_address" => getNotificationEmails("DONATIONS")));
					}
					$merchantAccountId = $GLOBALS['gMerchantAccountId'];

					$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
					if (!$eCommerce) {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = "Unable to connect to Merchant Services. Please contact customer service.";
						ajaxResponse($returnArray);
						break;
					}
					$paymentArray = array("amount" => $_POST['payment_amount'], "order_number" => $donationId, "description" => "Donation for " .
						getFieldFromId("description", "designations", "designation_id", $formDefinitionRow['designation_id']),
						"first_name" => (empty($GLOBALS['gUserRow']['first_name']) ? $_POST['first_name'] : $GLOBALS['gUserRow']['first_name']),
						"last_name" => (empty($GLOBALS['gUserRow']['last_name']) ? $_POST['last_name'] : $GLOBALS['gUserRow']['last_name']),
						"business_name" => (empty($GLOBALS['gUserRow']['business_name']) ? $_POST['business_name'] : $GLOBALS['gUserRow']['business_name']),
						"address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
						"postal_code" => (empty($_POST['billing_postal_code']) ? $_POST['postal_code'] : $_POST['billing_postal_code']),
						"country_id" => (empty($_POST['billing_country_id']) ? $_POST['country_id'] : $_POST['billing_country_id']),
						"email_address" => $_POST['email_address'], "contact_id" => $contactId);
					if ($isBankAccount) {
						$paymentArray['bank_routing_number'] = $_POST['routing_number'];
						$paymentArray['bank_account_number'] = $_POST['bank_account_number'];
						$paymentArray['bank_account_type'] = strtolower(str_replace("_", "", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id'])));
					} else {
						$paymentArray['card_number'] = $_POST['account_number'];
						$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
						$paymentArray['card_code'] = $_POST['cvv_code'];
					}
					$success = ($GLOBALS['gDevelopmentServer'] ? true : $eCommerce->authorizeCharge($paymentArray));
					$response = ($GLOBALS['gDevelopmentServer'] ? array("transaction_id" => "238559279234", "authorization_code" => "d92fwd") : $eCommerce->getResponse());
					if ($success) {
						executeQuery("update donations set transaction_identifier = ?,authorization_code = ?,bank_batch_number = ? where donation_id = ?",
							$response['transaction_id'], $response['authorization_code'], $response['bank_batch_number'], $donationId);
					} else {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
						$eCommerce->writeLog(($isBankAccount ? $paymentArray['bank_account_number'] : $paymentArray['card_number']), $response['response_reason_text'], true);
						ajaxResponse($returnArray);
						break;
					}
					executeQuery("update forms set donation_id = ? where form_id = ?", $donationId, $formId);
				}

				$this->iDatabase->commitTransaction();
				if (!empty($orderId)) {
					Order::processOrderItems($orderId);
					Order::processOrderAutomation($orderId);
					if (function_exists("_localServerProcessOrder")) {
						_localServerProcessOrder($orderId);
					}
					Order::notifyCRM($orderId);
					coreSTORE::orderNotification($orderId, "order_created");
					Order::reportOrderToTaxjar($orderId);
				}

				removeCachedData("last_form_date_" . $contactId, "*", true);
				$_POST['order_id'] = $orderId;
				$_POST['donation_id'] = $donationId;
				$responseContent = $formDefinitionRow['response_content'];
				if (empty($responseContent)) {
					if (function_exists("actionResponseContent")) {
						$responseContent = actionResponseContent();
					}
				}
				foreach ($_POST as $fieldName => $fieldValue) {
					$responseContent = str_replace("%" . $fieldName . "%", (is_scalar($fieldValue) ? $fieldValue : ""), $responseContent);
				}
				$returnArray['response'] = $responseContent;
				if (!empty($_POST['captcha_code_id'])) {
					executeQuery("delete from captcha_codes where captcha_code_id = ?", $_POST['captcha_code_id']);
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function createColumn($formFieldCode, $formDefinitionId) {
		$formFieldCode = strtolower($formFieldCode);
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
		if ($columnControls['data_type'] == "custom") {
			$this->iCustomFields[] = $row['form_field_id'];
		}
		$resultSet = executeQuery("select * from form_definition_controls where form_definition_id = ? and column_name = ?",
			$formDefinitionId, $columnControls['column_name']);
		while ($controlRow = getNextRow($resultSet)) {
			$columnControls[$controlRow['control_name']] = DataSource::massageControlValue($controlRow['control_name'], $controlRow['control_value']);
		}
		$thisColumn = new DataColumn($row['form_field_code'], $columnControls);
		$controlSet = executeQuery("select * from page_controls where page_id = ? and column_name = ?", $GLOBALS['gPageId'], $formFieldCode);
		while ($controlRow = getNextRow($controlSet)) {
			$thisColumn->setControlValue($controlRow['control_name'], $controlRow['control_value']);
		}
		$dataType = $thisColumn->getControlValue("data_type");
		switch ($dataType) {
			case "image":
			case "file":
				$thisColumn->setControlValue("no_download", true);
				$thisColumn->setControlValue("no_remove", true);
				break;
		}
		if (!empty($this->iInProgressFormId) && !empty($formFieldId)) {
			$resultSet = executeQuery("select * from in_progress_form_data where form_field_id = ? and in_progress_form_id = ?", $formFieldId, $this->iInProgressFormId);
			if ($inProgressFormDataRow = getNextRow($resultSet)) {
				switch ($dataType) {
					case "tinyint":
						$thisColumn->setControlValue("initial_value", ($inProgressFormDataRow['content'] ? 1 : 0));
						break;
					default:
						$thisColumn->setControlValue("initial_value", $inProgressFormDataRow['content']);
						break;
				}
			}
		}
		$formFieldContactRow = getRowFromId("form_definition_contact_fields", "form_field_id", $formFieldId, "form_definition_id = ?", $formDefinitionId);
		if (!empty($formFieldContactRow)) {
			$contactFieldColumnType = getFieldFromId("column_type", "column_definitions", "column_definition_id", $formFieldContactRow['column_definition_id']);
			if ($contactFieldColumnType == "varchar") {
				$dataSize = getFieldFromId("data_size", "column_definitions", "column_definition_id", $formFieldContactRow['column_definition_id']);
				$thisColumn->setControlValue("maximum_length", $dataSize);
			}
		}
		if (!empty($formFieldId) && $GLOBALS['gLoggedIn']) {
			$useUserContact = getFieldFromId("use_user_contact", "form_definitions", "form_definition_id", $formDefinitionId);
			if (!empty($useUserContact)) {
				if (!empty($formFieldContactRow)) {
					$columnName = getFieldFromId("column_name", "column_definitions", "column_definition_id", $formFieldContactRow['column_definition_id']);
					$thisColumn->setControlValue("initial_value", $GLOBALS['gUserRow'][$columnName]);
					$thisColumn->setControlValue("readonly", !empty($GLOBALS['gUserRow'][$columnName]));
				}
				$formFieldPhoneRow = getRowFromId("form_definition_phone_number_fields", "form_field_id", $formFieldId, "form_definition_id = ?", $formDefinitionId);
				if (!empty($formFieldPhoneRow)) {
					$phoneNumber = Contact::getContactPhoneNumber($GLOBALS['gUserRow']['contact_id'], $formFieldPhoneRow['description']);
					$thisColumn->setControlValue("initial_value", $phoneNumber);
					$thisColumn->setControlValue("readonly", !empty($phoneNumber));
				}
				$formFieldIdentifierRow = getRowFromId("form_definition_contact_identifiers", "form_field_id", $formFieldId, "form_definition_id = ?", $formDefinitionId);
				if (!empty($formFieldIdentifierRow)) {
					$contactIdentifier = getFieldFromId("identifier_value", "contact_identifiers", "contact_id", $GLOBALS['gUserRow']['contact_id'], "contact_identifier_type_id = ?", $formFieldIdentifierRow['contact_identifier_type_id']);
					$thisColumn->setControlValue("initial_value", $contactIdentifier);
					$thisColumn->setControlValue("readonly", !empty($contactIdentifier));
				}
				if (!empty($row['custom_field_id'])) {
					$customFieldData = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], getFieldFromId("custom_field_code", "custom_fields", "custom_field_id", $row['custom_field_id'],
						"custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS')"));
					$thisColumn->setControlValue("initial_value", $customFieldData);
				}
			}
		}
		if ($dataType == "select" || $dataType == "radio") {
			$choices = $thisColumn->getControlValue("choices");
			if (!is_array($choices)) {
				$choices = array();
			}
			$choiceSet = executeQuery("select * from form_field_choices where form_field_id = ?", $row['form_field_id']);
			while ($choiceRow = getNextRow($choiceSet)) {
				$choices[$choiceRow['key_value']] = $choiceRow['description'];
			}
			if (empty($choices) && !empty($thisColumn->getControlValue("get_choices"))) {
				$choiceFunction = $thisColumn->getControlValue('get_choices');
				if (function_exists($choiceFunction)) {
					$choices = $choiceFunction(false);
				}
			}
			$thisColumn->setControlValue("choices", $choices);
		} else if ($dataType == "custom") {
			$thisColumn->setControlValue("primary_table", "forms");
		}
		return $thisColumn;
	}

	function displayForm($parameters = array()) {
		if (empty($_GET['code'])) {
			$_GET['code'] = $parameters['code'];
		}
		if (empty($_GET['code'])) {
			$_GET['code'] = $parameters[0];
		}
		$formCode = $_GET['code'];
		if (empty($formCode)) {
			$formCode = $_GET['form'];
		}
		if (empty($formCode)) {
			$formCode = $_POST['form_definition_code'];
		}
		if (empty($formCode)) {
			$formDefinitionId = getFieldFromId("form_definition_id", "form_definitions", "form_definition_id", $_GET['form_id'],
				"client_id = " . $GLOBALS['gClientId'] . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and inactive = 0") .
				($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
		} else {
			$formDefinitionId = getFieldFromId("form_definition_id", "form_definitions", "form_definition_code", $formCode,
				"client_id = " . $GLOBALS['gClientId'] . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and inactive = 0") .
				($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
		}
		$resultSet = executeQuery("select * from form_definitions where form_definition_id = ? and client_id = ?", $formDefinitionId, $GLOBALS['gClientId']);
		$formDefinitionRow = getNextRow($resultSet);
		if (!$formDefinitionRow) {
			echo "<h1>Form not found</h1>";
			return true;
		}
		if (!empty($formDefinitionRow['action_filename'])) {
			include_once "actions/" . $formDefinitionRow['action_filename'];
		}
		if (function_exists("beforeDisplayForm")) {
			$returnValue = beforeDisplayForm($formDefinitionRow['form_definition_id']);
			if ($returnValue !== false) {
				echo $returnValue;
				return true;
			}
		}
		$resultSet = executeQuery("select * from forms where form_id = ?", $_GET['parent_form_id']);
		if ($parentFormRow = getNextRow($resultSet)) {
			$parentFormRow['data'] = array();
			$resultSet = executeQuery("select * from contacts where contact_id = ?", $parentFormRow['contact_id']);
			if ($row = getNextRow($resultSet)) {
				$parentFormRow['data'] = $row;
			}
			$parentFormRow['data']['date_created'] = date("m/d/Y", strtotime($parentFormRow['date_created']));
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
			if ($formDefinitionRow['parent_form_required']) {
				$formDefinitionRow = false;
			}
			$parentFormRow = array();
		}
		$formFieldTags = array();
		$resultSet = executeQuery("select * from form_field_tags where form_definition_id = ?", $formDefinitionId);
		while ($row = getNextRow($resultSet)) {
			$formFieldTags[$row['field_tag']] = $row['field_tag_action'];
		}
		if (method_exists($this->iTemplateObject, "beforeContent")) {
			$this->iTemplateObject->beforeContent();
		}
		$returnUrl = (empty($this->iInProgressFormCode) ? "" : "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] .
			(strpos($_SERVER['REQUEST_URI'], "in_progress") === false ? (strpos($_SERVER['REQUEST_URI'], "?") === false ? "?" : "&") . "in_progress=" . $this->iInProgressFormCode : ""));
		?>
		<div id="_form_div">
			<?= $formDefinitionRow['introduction_content'] ?>
			<div id="_return_url_div">
				<p id="_return_url">If you cannot complete this form now, you can return to it later. Copy the following link:</p>
				<p><a id="_return_url_link" href='<?= $returnUrl ?>'><?= $returnUrl ?></a></p>
			</div>
			<form id="_generated_form" name="_generated_form" enctype='multipart/form-data'>
				<input type="hidden" id="in_progress_form_id" name="in_progress_form_id" value="<?= $this->iInProgressFormId ?>"/>
				<input type="hidden" id="in_progress_form_code" name="in_progress_form_code" value="<?= $this->iInProgressFormCode ?>"/>
				<input type="hidden" id="parent_form_id" name="parent_form_id" value="<?= $parentFormRow['form_id'] ?>"/>
				<input type="hidden" id="form_definition_id" name="form_definition_id" value="<?= $formDefinitionRow['form_definition_id'] ?>"/>
				<input type="hidden" id="form_definition_code" name="form_definition_code" value="<?= $formDefinitionRow['form_definition_code'] ?>"/>
				<input type="hidden" name="_add_hash" id="_add_hash" value="<?= md5(uniqid(mt_rand(), true)) ?>"/>
				<input type="hidden" name="create_contact_pdf" id="create_contact_pdf" value="<?= $formDefinitionRow['create_contact_pdf'] ?>"/>
				<input type='hidden' name='_form_html' id='_form_html'>
				<input type='hidden' name='_template_css_filename' id='_template_css_filename'>
				<input type='hidden' name='shopping_cart_item_id' id='shopping_cart_item_id' value='<?= $_GET['shopping_cart_item_id'] ?>'/>
				<input type='hidden' name='product_addon_id' id='product_addon_id' value='<?= $_GET['product_addon_id'] ?>'/>
				<?php
				$formContentArray = array();
				$canUsePayment = true;
				$canUseReason = "";
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
					$userSubmittedForm = (strpos($formDefinitionRow['form_content'], "<!-- Coreware Form Builder -->") === false);
					if ($userSubmittedForm) {
						$canUsePayment = false;
					}
				}
				$designationId = getFieldFromId("designation_id", "designations", "designation_id", $formDefinitionRow['designation_id'], "inactive = 0");
				$productId = getFieldFromId("product_id", "products", "product_id", $formDefinitionRow['product_id'], "inactive = 0");
				if (empty($designationId) && empty($productId)) {
					$canUsePayment = false;
					$canUseReason = "No product or designation for payment";
				}
				if (!$GLOBALS['gLoggedIn']) {
					$contactFieldId = getFieldFromId("form_definition_contact_field_id", "form_definition_contact_fields", "form_definition_id", $formDefinitionRow['form_definition_id'], "column_definition_id = (select column_definition_id from column_definitions where column_name = 'first_name')");
					if (empty($contactFieldId)) {
						$canUsePayment = false;
						$canUseReason = "First Name field missing";
					}
					$contactFieldId = getFieldFromId("form_definition_contact_field_id", "form_definition_contact_fields", "form_definition_id", $formDefinitionRow['form_definition_id'], "column_definition_id = (select column_definition_id from column_definitions where column_name = 'last_name')");
					if (empty($contactFieldId)) {
						$canUsePayment = false;
						$canUseReason .= (empty($canUseReason) ? "" : ", ") . "Last Name field missing";
					}
					$contactFieldId = getFieldFromId("form_definition_contact_field_id", "form_definition_contact_fields", "form_definition_id", $formDefinitionRow['form_definition_id'], "column_definition_id = (select column_definition_id from column_definitions where column_name = 'email_address')");
					if (empty($contactFieldId)) {
						$canUsePayment = false;
						$canUseReason .= (empty($canUseReason) ? "" : ", ") . "Email Address field missing";
					}
				} else {
					if (empty($GLOBALS['gUserRow']['first_name']) || empty($GLOBALS['gUserRow']['last_name'])) {
						$canUsePayment = false;
						$canUseReason = "User's first or last name is empty. Use My Account to update.";
					}
				}

				$useLine = true;
				$thisColumn = new DataColumn("missing_field");
				/*
				Options within form:
				%field - define what form field will be used
				%if - evaluate a statement and include following lines (until %endif%) if the statement is true. For security reasons, not valid on user submitted form
				%if_has_value:fieldName% - If a parent form field has a value, then include the field until the %endif% statement
				%fragment - include a fragment text
				%method - execute a method. For security reasons, not valid on user submitted form
				%parent_form - display some data from the parent form
				%formDescription% - display the description of the form definition
				*/

				$formFieldValues = array();
				if (function_exists("_localCustomFormValues")) {
					$formFieldValues = _localCustomFormValues($formDefinitionRow);
				}
				if (empty($formFieldValues) && function_exists("actionCustomFormValues")) {
					$formFieldValues = actionCustomFormValues();
				}
				$formDefinitionDiscountId = getFieldFromId("form_definition_discount_id", "form_definition_discounts", "form_definition_id", $formDefinitionRow['form_definition_id']);

				$ifResults = array();
				foreach ($formContentArray as $line) {
					if ($line == "%endif%") {
						$useLine = count($ifResults) == 0 ? true : array_pop($ifResults);
						continue;
					}
					if (substr($line, 0, 4) == "<!--" || !$useLine) {
						if (substr($line, 0, strlen("%if:")) == "%if:") {
							$ifResults[] = false;
						}
						continue;
					}
					if (substr($line, 0, strlen("%fragment:")) == "%fragment:") {
						$fragmentCode = trim(substr($line, strlen("%fragment:")), "%");
						echo $this->getFragment($fragmentCode);
						continue;
					} else if (substr($line, 0, strlen("%payment_block%")) == "%payment_block%") {
						if ($canUsePayment) {
							?>
							<input type="hidden" value="<?= $designationId ?>" id="designation_id" name="designation_id">

							<div class="form-line" id="_payment_amount_row">
								<label for="payment_amount" class="<?= ($formDefinitionRow['payment_required'] ? "required-label" : "") ?>">Amount</label>
								<input tabindex="10" type="text" size="12" class="align-right validate[custom[number],<?= ($formDefinitionRow['payment_required'] ? "required" : "") ?>]"
								       data-decimal-places="2" <?= (empty($formDefinitionRow['amount']) ? "" : "readonly='readonly'") ?>
								       id="payment_amount" name="payment_amount" value="<?= $formDefinitionRow['amount'] ?>">
								<div class='clear-div'></div>
							</div>

							<?php if (!empty($formDefinitionDiscountId)) { ?>
								<div class="form-line" id="_discount_code_row">
									<label for="discount_code">Discount Code</label>
									<input tabindex="10" type="text" size="40" maxlength="100" class="code-value uppercase validate[]" id="discount_code" name="discount_code" value="">
									<div class='clear-div'></div>
								</div>
							<?php } ?>

							<div class="form-line" id="_payment_method_id_row">
								<label for="payment_method_id" class="">Payment Method</label>
								<select tabindex="10" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" id="payment_method_id" name="payment_method_id">
									<option value="">[Select]</option>
									<?php
									$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
										"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where " .
										($GLOBALS['gLoggedIn'] ? "" : "requires_user = 0 and ") .
										"(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
										(empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") . ") and " .
										"inactive = 0 and internal_use_only = 0 and client_id = ? and payment_method_type_id in " .
										"(select payment_method_type_id from payment_method_types where inactive = 0 and internal_use_only = 0 and " .
										"client_id = ?) order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
									while ($row = getNextRow($resultSet)) {
										?>
										<option value="<?= $row['payment_method_id'] ?>" data-payment_method_type_code="<?= strtolower($row['payment_method_type_code']) ?>"><?= htmlText($row['description']) ?></option>
										<?php
									}
									?>
								</select>
								<div class='clear-div'></div>
							</div>

							<div class="payment-method-fields" id="payment_method_credit_card">
								<div class="form-line" id="_account_number_row">
									<label for="account_number" class="">Card Number</label>
									<input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" size="20" maxlength="20" id="account_number" name="account_number" placeholder="Account Number" value="">
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_expiration_month_row">
									<label for="expiration_month" class="">Expiration Date</label>
									<select tabindex="10" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" id="expiration_month" name="expiration_month">
										<option value="">[Month]</option>
										<?php
										for ($x = 1; $x <= 12; $x++) {
											?>
											<option value="<?= $x ?>"><?= $x . " - " . date("F", strtotime($x . "/01/2000")) ?></option>
											<?php
										}
										?>
									</select>
									<select tabindex="10" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" id="expiration_year" name="expiration_year">
										<option value="">[Year]</option>
										<?php
										for ($x = 0; $x < 12; $x++) {
											$year = date("Y") + $x;
											?>
											<option value="<?= $year ?>"><?= $year ?></option>
											<?php
										}
										?>
									</select>
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_cvv_code_row">
									<label for="cvv_code" class="">Security Code</label>
									<input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" size="5" maxlength="4" id="cvv_code" name="cvv_code" placeholder="CVV Code" value="">
									<a href="https://www.cvvnumber.com/cvv.html" target="_blank"><img id="cvv_image" src="/images/cvv_code.gif" alt="CVV Code"></a>
									<div class='clear-div'></div>
								</div>
							</div> <!-- payment_method_credit_card -->

							<div class="payment-method-fields" id="payment_method_bank_account">
								<div class="form-line" id="_routing_number_row">
									<label for="routing_number" class="">Bank Routing Number</label>
									<input tabindex="10" type="text" class="validate[required,custom[routingNumber]]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" size="20" maxlength="20" id="routing_number" name="routing_number" placeholder="Routing Number" value="">
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_bank_account_number_row">
									<label for="bank_account_number" class="">Account Number</label>
									<input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" size="20" maxlength="20" id="bank_account_number" name="bank_account_number" placeholder="Bank Account Number" value="">
									<div class='clear-div'></div>
								</div>
							</div> <!-- payment_method_bank_account -->

							<div id="_billing_address">
								<div class="form-line" id="_billing_address_1_row">
									<label for="billing_address_1" class="">Billing Address</label>
									<input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" size="30" maxlength="60" id="billing_address_1" name="billing_address_1" placeholder="Billing Address" value="">
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_billing_city_row">
									<label for="billing_city" class="">City</label>
									<input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" size="30" maxlength="60" id="billing_city" name="billing_city" placeholder="City" value="">
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_billing_state_row">
									<label for="billing_state" class="">State</label>
									<input tabindex="10" type="text" class="validate[required]" data-conditional-required="($('#payment_amount').val() != '') && $('#billing_country_id').val() == 1000 && parseFloat($('#payment_amount').val()) > 0" size="10" maxlength="30" id="billing_state" name="billing_state" placeholder="State" value="">
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_billing_state_select_row">
									<label for="billing_state_select" class="">State</label>
									<select tabindex="10" id="billing_state_select" name="billing_state_select" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && $('#billing_country_id').val() == 1000 && parseFloat($('#payment_amount').val()) > 0">
										<option value="">[Select]</option>
										<?php
										foreach (getStateArray() as $stateCode => $state) {
											?>
											<option value="<?= $stateCode ?>"><?= htmlText($state) ?></option>
											<?php
										}
										?>
									</select>
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_billing_postal_code_row">
									<label for="billing_postal_code" class="">Postal Code</label>
									<input tabindex="10" type="text" class="validate[required]" size="10" maxlength="10" data-conditional-required="$('#payment_amount').val() != '' && $('#billing_country_id').val() == 1000 && parseFloat($('#payment_amount').val()) > 0" id="billing_postal_code" name="billing_postal_code" placeholder="Postal Code" value="">
									<div class='clear-div'></div>
								</div>

								<div class="form-line" id="_billing_country_id_row">
									<label for="billing_country_id" class="">Country</label>
									<select tabindex="10" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" id="billing_country_id" name="billing_country_id">
										<?php
										foreach (getCountryArray() as $countryId => $countryName) {
											?>
											<option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
											<?php
										}
										?>
									</select>
									<div class='clear-div'></div>
								</div>
							</div> <!-- billing_address -->

							<?php
						} else if (!empty($canUseReason)) {
							echo "<p>" . $canUseReason . "</p>";
						}
						continue;
					}
					if (substr($line, 0, strlen("%if:")) == "%if:") {
						$ifResults[] = $useLine;
						if ($userSubmittedForm) {
							$useLine = false;
						} else {
							$evalStatement = substr($line, strlen("%if:"));
							if (substr($evalStatement, -1) == "%") {
								$evalStatement = substr($evalStatement, 0, -1);
							}
							if (substr($evalStatement, 0, strlen("return ")) != "return ") {
								$evalStatement = "return " . $evalStatement;
							}
							if (substr($evalStatement, -1) != ";") {
								$evalStatement .= ";";
							}
							try {
								$useLine = eval($evalStatement);
							} catch (Exception $e) {
								addDebugLog("Error in eval: " . $evalStatement);
							}
						}
						continue;
					} else if (substr($line, 0, strlen("%if_has_value:")) == "%if_has_value:") {
						$parentFormField = substr($line, strlen("%if_has_value:"));
						if (substr($parentFormField, -1) == "%") {
							$parentFormField = substr($parentFormField, 0, -1);
						}
						$ifResults[] = $useLine;
						$useLine = !empty($parentFormRow['data'][$parentFormField]);
						continue;
					} else if (substr($line, 0, strlen("%execute:")) == "%execute:") {
						$functionName = str_replace("%", "", substr($line, strlen("%execute:")));
						if (!$userSubmittedForm) {
							eval($functionName);
						}
						continue;
					} else if (substr($line, 0, strlen("%field:")) == "%field:") {
						$fieldName = trim(str_replace("%", "", substr($line, strlen("%field:"))));
						$thisColumn = $this->createColumn($fieldName, $formDefinitionRow['form_definition_id']);
						$classes = explode(",", str_replace(" ", ",", $thisColumn->getControlValue("classes")));
						if (!in_array("generated-form-field", $classes)) {
							$classes[] = "generated-form-field";
						}
						$thisColumn->setControlValue("classes", implode(",", $classes));
						$classes = explode(",", str_replace(" ", ",", $thisColumn->getControlValue("classes")));
						$thisFormFieldTags = explode(",", str_replace(" ", ",", $thisColumn->getControlValue("form_field_tags")));
						foreach ($thisFormFieldTags as $thisFormFieldTag) {
							if (!empty($thisFormFieldTag) && !in_array($thisFormFieldTag, $classes)) {
								if (array_key_exists($thisFormFieldTag, $formFieldTags) && in_array($formFieldTags[$thisFormFieldTag], array("required", "not_required"))) {
									$thisColumn->setControlValue("not_null", true);
									$thisColumn->setControlValue("no_required_label", true);
								}
								$classes[] = "form-field-tag-" . $thisFormFieldTag;
							}
						}
						$thisColumn->setControlValue("classes", implode(",", $classes));
						if (empty($thisColumn->getControlValue("form_line_classes"))) {
							$thisColumn->setControlValue("form_line_classes", "");
						}
						continue;
					} else if (substr($line, 0, strlen("%method:")) == "%method:") {
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

					$labelClass = "";
					$line = str_replace("%formDescription%", htmlText($formDefinitionRow['description']), $line);
					if ($thisColumn->getControlValue('data_type') == "tinyint") {
						$line = str_replace("%form_label%", "&nbsp;", $line);
						$labelClass .= (empty($labelClass) ? "" : " ") . "checkbox-first-label";
					}
					if (!$thisColumn->getControlValue("no_required_label") && $thisColumn->getControlValue('not_null') && $thisColumn->getControlValue('data_type') != "tinyint" && !$thisColumn->getControlValue('readonly')) {
						$labelClass .= (empty($labelClass) ? "" : " ") . "required-label";
					}
					if ($thisColumn->getControlValue('data_type') == "text") {
						$labelClass .= (empty($labelClass) ? "" : " ") . "textarea-label";
					}
					if ($thisColumn->getControlValue('data_type') == "image" || $thisColumn->getControlValue('data_type') == "image_input") {
						$thisColumn->setControlValue("data_type", "image_input");
						$thisColumn->setControlValue("no_remove", "true");
						$thisColumn->setControlValue("no_view", "true");
					}
					if ($thisColumn->getControlValue('form_label') == "") {
						$labelClass .= (empty($labelClass) ? "" : " ") . "empty-label";
					}
					$thisColumn->setControlValue("label_class", $labelClass);
					if (empty($thisColumn->getControlValue("help_label"))) {
						$thisColumn->setControlValue("help_label", "");
					}
					foreach ($thisColumn->getAllControlValues() as $infoName => $infoData) {
						$line = str_replace("%" . $infoName . "%", (is_scalar($infoData) ? $infoData : ""), $line);
					}
					if (!empty($_GET[$thisColumn->getName()])) {
						$thisColumn->setControlValue("initial_value", $_GET[$thisColumn->getName()]);
					} else if (!empty($formFieldValues[$thisColumn->getName()])) {
						$thisColumn->setControlValue("initial_value", $formFieldValues[$thisColumn->getName()]);
					}
					if (strpos($line, "%input_control%") !== false) {
						$line = str_replace("%input_control%", $thisColumn->getControl($this), $line);
					}
					echo $line . "\n";
				}
				if (!empty($formDefinitionRow['use_captcha'])) {
					$captchaCodeId = createCaptchaCode();
					?>
					<input type='hidden' id='captcha_code_id' name='captcha_code_id' value='<?= $captchaCodeId ?>'>
					<div class='form-line' id=_captcha_image_row'>
						<label></label>
						<img src="/captchagenerator.php?id=<?= $captchaCodeId ?>">
					</div>

					<div class="form-line" id="_captcha_code_row">
						<label for="captcha_code" class="">Enter text from above</label>
						<input tabindex="10" type="text" size="10" maxlength="10" id="captcha_code" name="captcha_code" value="">
						<div class='clear-div'></div>
					</div>

				<?php } ?>

			</form>
			<?php
			$resultSet = executeQuery("select * from form_definition_files where form_definition_id = ?", $formDefinitionRow['form_definition_id']);
			while ($row = getNextRow($resultSet)) {
				?>
				<p><a data-description="<?= str_replace('"', "", $row['description']) ?>" href="/download.php?file_id=<?= $row['file_id'] ?>"><?= htmlText($row['description']) ?></a></p>
				<?php
			}
			?>
			<p id="_error_message" class="error-message"></p>
			<p id="_submit_paragraph">
				<button id="_submit_form">Submit</button>
			</p>
		</div> <!-- _form_div -->
		<?php
		return true;
	}

	function internalCSS() {
		?>
		<style>
            #_return_url_div {
                display: none;
                position: fixed;
                top: 20px;
                left: 50%;
                width: 90%;
                max-width: 800px;
                transform: translate(-50%, 0);
                border: 5px solid rgb(180, 180, 180);
                padding: 20px;
                padding-bottom: 0;
                border-radius: 10px;
                background-color: rgb(50, 50, 50);
                z-index: 99999;
                color: rgb(255, 255, 255);
            }

            #_return_url_div p {
                margin: 0;
                margin-bottom: 20px;
                text-align: center;
                color: rgb(255, 255, 255);
                font-weight: bold;
            }

            #_return_url_div p a {
                color: rgb(255, 255, 255);
            }

            #_return_url_div p a:hover {
                color: rgb(220, 220, 220);
            }

            #_submit_paragraph {
                text-align: center;
                padding-top: 20px;
            }

            .display-form {
                display: none;
            }

            .payment-method-fields {
                display: none;
            }

            .signature-palette-parent {
                color: rgb(10, 30, 150);
                background-color: rgb(180, 180, 180);
                padding: 10px;
                max-width: 600px;
            }

            .signature-palette {
                border: 2px dotted black;
                background-color: rgb(220, 220, 220);
                overflow: hidden;
            }

		</style>
		<?php
	}

	function javascript() {
		echo $GLOBALS['gPageRow']['javascript_code'];
		$formCode = $_GET['code'];
		if (empty($formCode)) {
			$formCode = $_GET['form'];
		}
		if (empty($formCode)) {
			$formCode = $_POST['form_definition_code'];
		}
		if (empty($formCode)) {
			$formDefinitionId = getFieldFromId("form_definition_id", "form_definitions", "form_definition_id", $_GET['form_id'],
				"client_id = " . $GLOBALS['gClientId'] . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and inactive = 0") .
				($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
		} else {
			$formDefinitionId = getFieldFromId("form_definition_id", "form_definitions", "form_definition_code", $formCode,
				"client_id = " . $GLOBALS['gClientId'] . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and inactive = 0") .
				($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
		}
		$fieldTagActions = array();
		$resultSet = executeQuery("select field_tag,field_tag_action,form_field_id,form_field_value from form_field_tags where form_definition_id = ?", $formDefinitionId);
		while ($row = getNextRow($resultSet)) {
			$row['form_field_name'] = getFieldFromId("form_field_code", "form_fields", "form_field_id", $row['form_field_id']);
			$fieldTagActions[] = $row;
		}
		$fieldPaymentAmounts = array();
		$resultSet = executeQuery("select form_field_id,form_field_value,amount from form_field_payments where form_definition_id = ?", $formDefinitionId);
		while ($row = getNextRow($resultSet)) {
			$row['form_field_name'] = getFieldFromId("form_field_code", "form_fields", "form_field_id", $row['form_field_id']);
			$fieldPaymentAmounts[] = $row;
		}
		?>
		<script>
            var fieldTagActions = <?= jsonEncode($fieldTagActions) ?>;
            var fieldPaymentAmounts = <?= jsonEncode($fieldPaymentAmounts) ?>;
            var formDefinitionDiscounts = {};
			<?php
			if (function_exists("actionJavascriptValues")) {
				actionJavascriptValues();
			}
			$javascriptCode = getFieldFromId("javascript_code", "form_definitions", "form_definition_id", $formDefinitionId);
			echo $javascriptCode;
			?>
            function fieldChanged(fieldId) {
                $("body").addClass("no-waiting-for-ajax");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_field", {
                    field_id: fieldId,
                    field_value: ($("#" + fieldId).is("input[type=checkbox]") ? $("#" + fieldId).prop("checked") : $("#" + fieldId).val()),
                    in_progress_form_id: $("#in_progress_form_id").val(),
                    form_definition_id: $("#form_definition_id").val()
                }, function (returnArray) {
                    if ("in_progress_form_id" in returnArray) {
                        $("#in_progress_form_id").val(returnArray['in_progress_form_id']);
                    }
                    if ("in_progress_form_code" in returnArray) {
                        $("#in_progress_form_code").val(returnArray['in_progress_form_code']);
						<?php
						$returnUrl = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] .
							(strpos($_SERVER['REQUEST_URI'], "in_progress=") === false ? (strpos($_SERVER['REQUEST_URI'], "?") === false ? "?" : "&") . "in_progress=" : "");
						if (strpos($_SERVER['REQUEST_URI'], "in_progress=") === false) {
						?>
                        var returnUrl = "<?= $returnUrl ?>" + returnArray['in_progress_form_code'];
						<?php } else { ?>
                        var returnUrl = "<?= $returnUrl ?>";
						<?php } ?>
                        $("#_return_url_link").attr("href", returnUrl).text(returnUrl);
                        $("#_return_url_div").show();
                    }
                });
            }
		</script>
		<?php
		return true;
	}

	function onLoadJavascript() {
		$urlQuery = http_build_query($_GET);
		?>
		<script>
			<?php if (!$GLOBALS['gUserRow']['superuser_flag']) { ?>
            $(document).bind("contextmenu", function (e) {
                return false;
            });
			<?php } ?>
            if ($("#_post_iframe").length == 0) {
                $("body").append("<iframe id='_post_iframe' name='post_iframe' class='hidden'></iframe>");
            }

            if ($(".signature-palette").length > 0) {
                $(".signature-palette").jSignature({ 'UndoButton': true, "height": 140 });
            }
            if (fieldTagActions.length > 0 || fieldPaymentAmounts.length > 0) {
                $(document).on("change click", ".generated-form-field", function () {
                    if (fieldTagActions.length > 0) {
                        for (var i in fieldTagActions) {
                            var matched = $("#" + fieldTagActions[i]['form_field_name']).val() == fieldTagActions[i]['form_field_value'];
                            switch (fieldTagActions[i]['field_tag_action']) {
                                case "hide":
                                    if (matched) {
                                        $(".form-field-tag-" + fieldTagActions[i]['field_tag']).closest(".form-line").hide();
                                    } else {
                                        $(".form-field-tag-" + fieldTagActions[i]['field_tag']).closest(".form-line").show();
                                    }
                                    break;
                                case "show":
                                    if (matched) {
                                        $(".form-field-tag-" + fieldTagActions[i]['field_tag']).closest(".form-line").show();
                                    } else {
                                        $(".form-field-tag-" + fieldTagActions[i]['field_tag']).closest(".form-line").hide();
                                    }
                                    break;
                                case "required":
                                    if (matched) {
                                        $(".form-field-tag-" + fieldTagActions[i]['field_tag']).data("conditional-required", "true");
                                    } else {
                                        $(".form-field-tag-" + fieldTagActions[i]['field_tag']).data("conditional-required", "false");
                                    }
                                    break;
                                case "not_required":
                                    if (matched) {
                                        $(".form-field-tag-" + fieldTagActions[i]['field_tag']).data("conditional-required", "false");
                                    } else {
                                        $(".form-field-tag-" + fieldTagActions[i]['field_tag']).data("conditional-required", "true");
                                    }
                                    break;
                            }
                        }
                    }
                    if (fieldPaymentAmounts.length > 0) {
                        for (var i in fieldPaymentAmounts) {
                            if ($("#" + fieldPaymentAmounts[i]['form_field_name']).is("input[type=checkbox]")) {
                                var matched = (!empty(fieldPaymentAmounts[i]['form_field_value']) && $("#" + fieldPaymentAmounts[i]['form_field_name']).prop("checked")) ||
                                    (empty(fieldPaymentAmounts[i]['form_field_value']) && !$("#" + fieldPaymentAmounts[i]['form_field_name']).prop("checked"));
                            } else {
                                var matched = $("#" + fieldPaymentAmounts[i]['form_field_name']).val() == fieldPaymentAmounts[i]['form_field_value'];
                            }
                            if (matched) {
                                $("#payment_amount").val(fieldPaymentAmounts[i]['amount']);
                                break;
                            }
                        }
                        if ($("#discount_code").length > 0) {
                            $("#discount_code").trigger("change");
                        }
                    }
                });
                for (var i in fieldTagActions) {
                    $("#" + fieldTagActions[i]['form_field_name'] + ".generated-form-field").trigger("change");
                }
            }
            $("#_form_div").find("a").each(function () {
                if (empty($(this).attr("target"))) {
                    $(this).attr("target", "_blank");
                }
            });
            $("#payment_amount").change(function () {
                var amount = parseFloat($(this).val());
                if (amount <= 0) {
                    $(this).val(0);
                    $("#_payment_method_id_row").addClass("hidden");
                    $("#_billing_address").addClass("hidden");
                    $(".payment-method-fields").addClass("hidden");
                } else {
                    $("#_payment_method_id_row").removeClass("hidden");
                    $("#_billing_address").removeClass("hidden");
                    $(".payment-method-fields").removeClass("hidden");
                }
            });
            $("#discount_code").change(function () {
                formDefinitionDiscounts = {};
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_discount_amount", { discount_code: $("#discount_code").val(), form_definition_id: $("#form_definition_id").val() }, function (returnArray) {
                    if ("form_definition_discounts" in returnArray) {
                        formDefinitionDiscounts = returnArray['form_definition_discounts'];
                        var originalPaymentAmount = $("#payment_amount").val();
                        var paymentAmount = $("#payment_amount").val();
                        if (!empty(originalPaymentAmount)) {
                            if ("discount_amount" in formDefinitionDiscounts) {
                                var discountAmount = parseFloat(formDefinitionDiscounts['discount_amount']);
                                if (discountAmount > 0) {
                                    var thisPaymentAmount = originalPaymentAmount - discountAmount;
                                    if (thisPaymentAmount < paymentAmount) {
                                        paymentAmount = thisPaymentAmount;
                                    }
                                }
                            }
                            if ("discount_percent" in formDefinitionDiscounts) {
                                var discountPercent = parseFloat(formDefinitionDiscounts['discount_percent']);
                                if (discountPercent > 0) {
                                    var thisPaymentAmount = Round(originalPaymentAmount * ((100 - discountPercent) / 100), 2);
                                    if (thisPaymentAmount < paymentAmount) {
                                        paymentAmount = thisPaymentAmount;
                                    }
                                }
                            }
                            if ("amount" in formDefinitionDiscounts && formDefinitionDiscounts['amount'] != "") {
                                var thisPaymentAmount = parseFloat(formDefinitionDiscounts['amount']);
                                if (thisPaymentAmount < paymentAmount) {
                                    paymentAmount = thisPaymentAmount;
                                }
                            }
                        }
                        $("#payment_amount").val(RoundFixed(paymentAmount, 2)).trigger("change");
                    }
                });
            });
            $("#payment_method_id").change(function () {
                $(".payment-method-fields").hide();
                if (!empty($(this).val())) {
                    var paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");
                    $("#payment_method_" + paymentMethodTypeCode.toLowerCase()).show();
                }
            });
            if ($("#country_id").length > 0 && $("#state").length > 0 && $("#state_select").length > 0) {
                $("#country_id").change(function () {
                    if ($(this).val() == "1000") {
                        $("#_state_row").hide();
                        $("#_state_select_row").show();
                    } else {
                        $("#_state_row").show();
                        $("#_state_select_row").hide();
                    }
                }).trigger("change");
                $("#state_select").change(function () {
                    $("#state").val($(this).val());
                });
            }
            $("#billing_country_id").change(function () {
                if ($(this).val() == "1000") {
                    $("#_billing_state_row").hide();
                    $("#_billing_state_select_row").show();
                } else {
                    $("#_billing_state_row").show();
                    $("#_billing_state_select_row").hide();
                }
            }).trigger("change");
            $("#billing_state_select").change(function () {
                $("#billing_state").val($(this).val());
            });
            $("#_form_div").find("input[type=text],select,textarea").change(function () {
                fieldChanged($(this).attr("id"));
            });
            $("#_form_div").find("input[type=checkbox]").click(function () {
                fieldChanged($(this).attr("id"));
            });
            $(document).on("tap click", "#_submit_form", function () {
                if ($("#_submit_form").data("disabled") == "true") {
                    return false;
                }
                var signatureRequired = false;
                $(".signature-palette").each(function () {
                    var columnName = $(this).closest(".form-line").find("input[type=hidden]").prop("id");
                    var required = $(this).closest(".form-line").find("input[type=hidden]").data("required");
                    if (!empty(required) && $(this).jSignature('getData', 'native').length == 0) {
                        $(this).validationEngine("showPrompt", "Required");
                        signatureRequired = true;
                    }
                    var data = $(this).jSignature('getData', 'svg');
                    $(this).closest(".form-line").find("input[type=hidden]").val(data[1]);
                });

                if ($("#_generated_form").validationEngine("validate") && !signatureRequired) {
                    displayErrorMessage("");
                    if (typeof beforeSubmit == "function") {
                        if (!beforeSubmit()) {
                            return false;
                        }
                    }
                    if (!empty($("#create_contact_pdf").val())) {
                        const htmlContent = $("#_main_content").html();
                        $("#_form_html").val(htmlContent);
                        let templateCssFilename = "";
                        $("head").find("link").each(function () {
                            const href = $(this).attr("href");
                            if (href.indexOf("fontawesome") < 0 && $(this).attr("rel") == "stylesheet") {
                                templateCssFilename += (empty(templateCssFilename) ? "" : ",") + href;
                            }
                        });
                        $("#_template_css_filename").val(templateCssFilename);
                    }
                    disableButtons($("#_submit_form"));
                    displayInfoMessage("Form being processed. Do not close window.");
                    $("body").addClass("waiting-for-ajax");
                    if (window.CKEDITOR && typeof CKEDITOR !== "undefined") {
                        for (instance in CKEDITOR.instances) {
                            CKEDITOR.instances[instance].updateElement();
                        }
                    }
                    $("#_generated_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_changes").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").off("load");
                    $("#_post_iframe").on("load", function () {
                        $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                        var returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            enableButtons($("#_submit_form"));
                            return;
                        }
                        if (!("error_message" in returnArray)) {
                            if ("response" in returnArray) {
                                $("#_form_div").html(returnArray['response']);
                                $("html,body").animate({ scrollTop: $("#_form_div").offset().top - 200 });
                            }
                            enableButtons($("#_submit_form"));
                            $("#_submit_form").show();
                            if (typeof afterSubmitForm == "function") {
                                afterSubmitForm(returnArray);
                            }
                        } else {
                            enableButtons($("#_submit_form"));
                        }
                    });
                } else {
                    displayErrorMessage("Some information is missing. Please check the form and try again.");
                }
                return false;
            });
            addCKEditor();
		</script>
		<?php
	}

	function jqueryTemplates() {
		if (!empty($this->iCustomFields)) {
			$resultSet = executeQuery("select * from form_fields where form_field_id in (" . implode(",", $this->iCustomFields) . ")");
			while ($row = getNextRow($resultSet)) {
				$thisColumn = $this->createColumn($row['form_field_code'], $this->iFormDefinitionId);
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

$pageObject = new GenerateFormPage();
$pageObject->displayPage();
