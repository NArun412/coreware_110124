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

$GLOBALS['gPageCode'] = "COMPANYMAINT";
require_once "shared/startup.inc";

class CompanyMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("company_type_id", "first_name", "last_name", "business_name", "city", "state", "postal_code", "email_address", "inactive"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->setJoinTable("contacts", "contact_id", "contact_id");
		$this->iDataSource->setSaveOnlyPresent(true);
		$this->iDataSource->addColumnControl("business_name", "not_null", true);
	}

	function showClientUsers() {
		?>
        <div id="client_users">
        </div>
		<?php
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
		$resultSet = executeQuery("select * from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS') and inactive = 0 and client_id = ?" .
			($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and (user_group_id is null or user_group_id in " .
				"(select user_group_id from user_group_members where user_id = " . $GLOBALS['gUserId'] . ")) "), $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$controlSet = executeQuery("select * from custom_field_controls where custom_field_id = ? and control_name = 'data_type'", $row['custom_field_id']);
			$dataType = "";
			if ($controlRow = getNextRow($controlSet)) {
				$dataType = $controlRow['control_value'];
			}
			$fieldValue = "";
			$fieldName = "custom_field_id_" . $row['custom_field_id'];
			$dataSet = executeQuery("select * from custom_field_data where custom_field_id = ? and primary_identifier = ?",
				$row['custom_field_id'], $returnArray['contact_id']['data_value']);
			if (!$dataRow = getNextRow($dataSet)) {
				$dataRow = array();
			}
			switch ($dataType) {
				case "date":
					$fieldValue = (empty($dataRow['date_data']) ? "" : date("m/d/Y", strtotime($dataRow['date_data'])));
					break;
                case "bigint":
                case "int":
					$fieldValue = $dataRow['integer_data'];
					break;
				case "decimal":
					$fieldValue = $dataRow['number_data'];
					break;
				case "image":
					$returnArray[$fieldName . "_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
					$returnArray[$fieldName . "_view"] = array("data_value" => getImageFilename($dataRow['image_id']));
					$returnArray[$fieldName . "_filename"] = array("data_value" => getFieldFromId("filename", "images", "image_id", $dataRow['image_id'], "client_id is not null"));
					$returnArray["remove_" . $fieldName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
					$fieldValue = $dataRow['image_id'];
					break;
				case "file":
					$returnArray[$fieldName . "_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
					$returnArray[$fieldName . "_download"] = array("data_value" => (empty($dataRow['file_id']) ? "" : "download.php?id=" . $dataRow['file_id']));
					$returnArray["remove_" . $fieldName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
					$fieldValue = $dataRow['file_id'];
					break;
				case "tinyint":
					$fieldValue = ($dataRow['text_data'] ? "1" : "0");
					break;
				default:
					$fieldValue = $dataRow['text_data'];
					break;
			}
			$returnArray[$fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
		}
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$resultSet = executeQuery("select * from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS') and inactive = 0 and client_id = ?" .
			($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and (user_group_id is null or user_group_id in " .
				"(select user_group_id from user_group_members where user_id = " . $GLOBALS['gUserId'] . ")) "), $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$dataType = "";
			$controlSet = executeQuery("select * from custom_field_controls where custom_field_id = ? and control_name = 'data_type'", $row['custom_field_id']);
			if ($controlRow = getNextRow($controlSet)) {
				$dataType = $controlRow['control_value'];
			}
			$customFieldDataId = "";
			$dataSet = executeQuery("select * from custom_field_data where custom_field_id = ? and primary_identifier = ?",
				$row['custom_field_id'], $nameValues['contact_id']);
			if ($dataRow = getNextRow($dataSet)) {
				$customFieldDataId = $dataRow['custom_field_data_id'];
			}
			$fieldName = "custom_field_id_" . $row['custom_field_id'];
			$deleteRows = array();
			switch ($dataType) {
				case "date":
					$nameValues[$fieldName] = $this->iDatabase->makeDateParameter($nameValues[$fieldName]);
					$updateField = "date_data";
					break;
                case "bigint":
                case "int":
					$updateField = "integer_data";
					break;
				case "decimal":
					$updateField = "number_data";
					break;
				case "file":
					if (!empty($nameValues['remove_' . $fieldName]) || (array_key_exists($fieldName . "_file", $_FILES) &&
							!empty($_FILES[$fieldName . '_file']['name']))) {
						$oldFileId = $nameValues[$fieldName];
						if (!empty($oldFileId)) {
							$nameValues[$fieldName] = "";
							$deleteRows[] = array("table_name" => "files", "key_name" => "file_id", "key_value" => $oldFileId);
						}
					}
					if (array_key_exists($fieldName . "_file", $_FILES) && !empty($_FILES[$fieldName . '_file']['name']) && empty($nameValues['remove_' . $fieldName])) {
						$originalFilename = $_FILES[$fieldName . '_file']['name'];
						if (array_key_exists($_FILES[$fieldName . '_file']['type'], $GLOBALS['gMimeTypes'])) {
							$extension = $GLOBALS['gMimeTypes'][$_FILES[$fieldName . '_file']['type']];
						} else {
							$fileNameParts = explode(".", $_FILES[$fieldName . '_file']['name']);
							$extension = $fileNameParts[count($fileNameParts) - 1];
						}
						$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
						if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
							$maxDBSize = 1000000;
						}
						if ($_FILES[$fieldName . '_file']['size'] < $maxDBSize) {
							$fileContent = file_get_contents($_FILES[$fieldName . '_file']['tmp_name']);
							$osFilename = "";
						} else {
							$fileContent = "";
							$osFilename = "/documents/tmp." . $extension;
						}
						$fileSet = $this->iDatabase->executeQuery("insert into files (file_id,client_id,description,date_uploaded," .
							"filename,extension,file_content,os_filename,public_access,all_user_access,administrator_access," .
							"sort_order,version) values (null,?,?,now(),?,?,?,?,0,0,1,0,1)", $GLOBALS['gClientId'], "Custom Data File",
							$originalFilename, $extension, $fileContent, $osFilename);
						if (!empty($fileSet['sql_error'])) {
							return getSystemMessage("basic", $fileSet['sql_error']);
						}
						$nameValues[$fieldName] = $fileSet['insert_id'];
						if (!empty($osFilename)) {
							putExternalFileContents($nameValues[$fieldName], $extension, file_get_contents($_FILES[$fieldName . '_file']['tmp_name']));
						}
					}
					$updateField = "file_id";
					break;
				case "image":
					if (!empty($nameValues['remove_' . $fieldName]) || (array_key_exists($fieldName . "_file", $_FILES) &&
							!empty($_FILES[$fieldName . '_file']['name']))) {
						$oldImageId = $nameValues[$fieldName];
						if (!empty($oldImageId)) {
							$nameValues[$fieldName] = "";
							$deleteRows[] = array("table_name" => "images", "key_name" => "image_id", "key_value" => $oldImageId);
						}
					}
					if (array_key_exists($fieldName . "_file", $_FILES) && !empty($_FILES[$fieldName . '_file']['name']) && empty($nameValues['remove_' . $fieldName])) {
						$imageId = createImage($fieldName . "_file", array("description" => "Custom Data Image"));
						if ($imageId == false) {
							return getSystemMessage("Unable to create Image");
						}
						$nameValues[$fieldName] = $imageId;
					}
					$updateField = "image_id";
					break;
				default:
					$updateField = "text_data";
					break;
			}
			if (empty($customFieldDataId)) {
				if (!empty($nameValues[$fieldName])) {
					$updateSet = executeQuery("insert into custom_field_data (primary_identifier,custom_field_id,$updateField) values (?,?,?)",
						$nameValues['contact_id'], $row['custom_field_id'], $nameValues[$fieldName]);
				}
			} else {
				if (!empty($nameValues[$fieldName])) {
					$updateSet = executeQuery("update custom_field_data set $updateField = ? where custom_field_data_id = ?",
						$nameValues[$fieldName], $customFieldDataId);
				} else {
					$updateSet = executeQuery("delete from custom_field_data where custom_field_data_id = ?", $customFieldDataId);
				}
			}
			foreach ($deleteRows as $deleteInfo) {
				$this->iDatabase->executeQuery("delete from " . $deleteInfo['table_name'] . " where " . $deleteInfo['key_name'] . " = " . $deleteInfo['key_value']);
			}
			if (!empty($updateSet['sql_error'])) {
				return getSystemMessage("basic", $updateSet['sql_error']);
			}
		}
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#postal_code").blur(function () {
                if ($("#country_id").val() === "1000") {
                    validatePostalCode();
                }
            });
            $("#country_id").change(function () {
                $("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() === "1000" ? "9999" : "10"));
                $("#_city_row").show();
                $("#_city_select_row").hide();
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
            function afterGetRecord() {
                $("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
                $("#city").add("#state").attr("tabindex", ($("#country_id").val() === "1000" ? "9999" : "10"));
                $("#_city_select_row").hide();
                $("#_city_row").show();
            }
        </script>
		<?php
	}
}

$pageObject = new CompanyMaintenancePage("companies");
$pageObject->displayPage();
