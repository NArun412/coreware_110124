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

$GLOBALS['gPageCode'] = "COUNTRYMAINT";
require_once "shared/startup.inc";

class CountryMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
		}
		$countryDataTypeId = getFieldFromId("country_data_type_id", "country_data_types", "country_data_type_code", "creative_access");
		if (!empty($countryDataTypeId)) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("country_code","iso_code","country_name","sort_order","creative_access"));
			$this->iDataSource->addColumnControl("creative_access", "select_value", "select integer_data from country_data where country_id = countries.country_id and country_data_type_id = " . $countryDataTypeId);
			$this->iDataSource->addColumnControl("creative_access", "data_type", "tinyint");
			$this->iDataSource->addColumnControl("creative_access", "form_label", "Creative Access");
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("country_data"));
		$this->iDataSource->addColumnControl("sort_order", "default_value", "100");
	}

	function customFields() {
		$resultSet = executeQuery("select * from country_data_types order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
			$thisColumn = new DataColumn("country_data_type_id_" . $row['country_data_type_id']);
			$thisColumn->setControlValue("form_label", $row['description']);
			switch ($row['data_type']) {
				case "bigint":
				case "int":
					$thisColumn->setControlValue("data_type", "int");
					break;
				case "decimal":
					$thisColumn->setControlValue("data_type", "decimal");
					$thisColumn->setControlValue("decimal_places", "2");
					break;
				case "tinyint":
					$thisColumn->setControlValue("data_type", "tinyint");
					break;
				case "date":
					$thisColumn->setControlValue("data_type", "date");
					break;
				case "image":
				case "image_input":
					$thisColumn->setControlValue("data_type", "image_input");
					$thisColumn->setControlValue("subtype", "image");
					break;
				case "file":
					$thisColumn->setControlValue("data_type", "file");
					$thisColumn->setControlValue("subtype", "file");
					break;
				case "varchar":
					$thisColumn->setControlValue("data_type", "varchar");
					$thisColumn->setControlValue("css-width", "500px");
					break;
				case "select":
					$thisColumn->setControlValue("data_type", "select");
					$thisChoices = array();
					if (!empty($row['choices'])) {
						$choiceArray = getContentLines($row['choices']);
						foreach ($choiceArray as $choice) {
							$thisChoices[$choice] = $choice;
						}
					} else if (!empty($row['table_name'])) {
						if (empty($row['column_name'])) {
							$row['column_name'] = "description";
						}
						$choicesDataSource = new DataSource($row['table_name']);
						if ($choicesDataSource->getPrimaryTable()->columnExists("client_id")) {
							if (!empty($row['query_text'])) {
								$row['query_text'] .= " and ";
							}
							$row['query_text'] .= "client_id = " . $GLOBALS['gClientId'];
						}
						$row['query_text'] = str_replace("%client_id%", $GLOBALS['gClientId'], $row['query_text']);
						$choiceSet = executeQuery("select * from " . $row['table_name'] . (empty($row['query_text']) ? "" : " where " . $row['query_text']) . " order by " . $row['column_name']);
						while ($choiceRow = getNextRow($choiceSet)) {
							$thisChoices[$choiceRow[$choicesDataSource->getPrimaryTable()->getPrimaryKey()]] = $choiceRow[$row['column_name']];
						}
					}
					$thisColumn->setControlValue("choices", $thisChoices);
					break;
				default:
					$thisColumn->setControlValue("data_type", "text");
					break;
			}

			?>
			<div class="basic-form-line" id="_<?= "country_data_type_id_" . $row['country_data_type_id'] ?>_row">
				<label for="<?= "country_data_type_id_" . $row['country_data_type_id'] ?>"><?= ($row['data_type'] == "tinyint" ? "" : $thisColumn->getControlValue("form_label")) ?></label>
				<?= $thisColumn->getControl($this) ?>
				<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
			</div>
			<?php
		}
	}

	function afterGetRecord(&$returnArray) {
		$resultSet = executeQuery("select * from country_data_types");
		while ($row = getNextRow($resultSet)) {
			$fieldValue = "";
			$fieldName = "country_data_type_id_" . $row['country_data_type_id'];
			$dataSet = executeQuery("select * from country_data where country_data_type_id = ? and country_id = ?",
					$row['country_data_type_id'], $returnArray['primary_id']['data_value']);
			if (!$dataRow = getNextRow($dataSet)) {
				$dataRow = array();
			}
			switch ($row['data_type']) {
				case "date":
					$fieldValue = (empty($dataRow['date_data']) ? "" : date("m/d/Y", strtotime($dataRow['date_data'])));
					break;
				case "tinyint":
					$fieldValue = (empty($dataRow['integer_data']) ? "0" : "1");
					break;
				case "bigint":
				case "int":
					$fieldValue = $dataRow['integer_data'];
					break;
				case "decimal":
					$fieldValue = $dataRow['number_data'];
					break;
				case "image":
				case "image_input":
					$returnArray[$fieldName . "_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
					$returnArray[$fieldName . "_view"] = array("data_value" => getImageFilename($dataRow['image_id']));
					$returnArray[$fieldName . "_filename"] = array("data_value" => getFieldFromId("filename", "images", "image_id", $dataRow['image_id']));
					$returnArray["remove_" . $fieldName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
					if (!empty($dataRow['image_id'])) {
						$returnArray['select_values'][$fieldName] = array(array("key_value" => $dataRow['image_id'], "description" => getFieldFromId("description", "images", "image_id", $dataRow['image_id'])));
					}
					$fieldValue = $dataRow['image_id'];
					break;
				case "file":
					$returnArray[$fieldName . "_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
					$returnArray[$fieldName . "_download"] = array("data_value" => (empty($dataRow['file_id']) ? "" : "download.php?id=" . $dataRow['file_id']));
					$returnArray["remove_" . $fieldName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
					$fieldValue = $dataRow['file_id'];
					break;
				default:
					$fieldValue = $dataRow['text_data'];
					break;
			}
			$returnArray[$fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
		}
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$resultSet = executeQuery("select * from country_data_types");
		while ($row = getNextRow($resultSet)) {
			$countryDataId = "";
			$dataSet = executeQuery("select * from country_data where country_data_type_id = ? and country_id = ?",
					$row['country_data_type_id'], $nameValues['primary_id']);
			if ($dataRow = getNextRow($dataSet)) {
				$countryDataId = $dataRow['country_data_id'];
			}
			$fieldName = "country_data_type_id_" . $row['country_data_type_id'];
			$deleteRows = array();
			switch ($row['data_type']) {
				case "date":
					$nameValues[$fieldName] = $this->iDatabase->makeDateParameter($nameValues[$fieldName]);
					$updateField = "date_data";
					break;
				case "bigint":
				case "int":
				case "tinyint":
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
								"sort_order,version) values (null,?,?,now(),?,?,?,?,0,0,1,0,1)", $GLOBALS['gClientId'], "Country Data File",
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
				case "image_input":
					if (!empty($nameValues['remove_' . $fieldName]) || (array_key_exists($fieldName . "_file", $_FILES) &&
									!empty($_FILES[$fieldName . '_file']['name']))) {
						$oldImageId = $nameValues[$fieldName];
						if (!empty($oldImageId)) {
							$nameValues[$fieldName] = "";
							$deleteRows[] = array("table_name" => "images", "key_name" => "image_id", "key_value" => $oldImageId);
						}
					}
					if (array_key_exists($fieldName . "_file", $_FILES) && !empty($_FILES[$fieldName . '_file']['name']) && empty($nameValues['remove_' . $fieldName])) {
						$imageId = createImage($fieldName . "_file", array("description" => "Country Data Image"));
						if ($imageId == false) {
							return "Error creating Image";
						}
						$nameValues[$fieldName] = $imageId;
					}
					$updateField = "image_id";
					break;
				default:
					$updateField = "text_data";
					break;
			}
			if (empty($countryDataId)) {
				if (!empty($nameValues[$fieldName])) {
					$updateSet = executeQuery("insert into country_data (country_id,country_data_type_id,$updateField) values (?,?,?)",
							$nameValues['primary_id'], $row['country_data_type_id'], $nameValues[$fieldName]);
				}
			} else {
				if (!empty($nameValues[$fieldName])) {
					$updateSet = executeQuery("update country_data set $updateField = ? where country_data_id = ?",
							$nameValues[$fieldName], $countryDataId);
				} else {
					$updateSet = executeQuery("delete from country_data where country_data_id = ?", $countryDataId);
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
}

$pageObject = new CountryMaintenancePage("countries");
$pageObject->displayPage();
