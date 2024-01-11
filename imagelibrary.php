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

$GLOBALS['gPageCode'] = "IMAGELIBRARY";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;
ini_set("memory_limit", "4096M");

class ThisPage extends Page {

	var $iExemptionTables = array("album_images", "image_usage_log", "image_data");

	function setup() {
		$imageDataTypeId = getFieldFromId("image_data_type_id", "image_data_types", "image_data_type_code", "DO_NOT_AUTO_RESIZE");
		if (empty($imageDataTypeId)) {
			$resultSet = executeQuery("insert into image_data_types (client_id,image_data_type_code,description,data_type) values (?,'DO_NOT_AUTO_RESIZE','Do Not Auto Resize','tinyint')", $GLOBALS['gClientId']);
			$imageDataTypeId = $resultSet['insert_id'];
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "mass_delete":
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					$imageIdArray = array();
					foreach (explode(",", $_POST['mass_delete_image_ids']) as $imageId) {
						$imageId = getFieldFromId("image_id", "images", "image_id", $imageId, "remote_storage = 0");
						if (!empty($imageId)) {
							$imageIdArray[] = $imageId;
						}
					}
					$returnArray['info_message'] = "";
					foreach ($imageIdArray as $imageId) {
						$imageTableColumnId = "";
						$resultSet = executeQuery("select table_column_id from table_columns where table_id = " .
							"(select table_id from tables where table_name = 'images' and database_definition_id = " .
							"(select database_definition_id from database_definitions where database_name = ?)) and column_definition_id = " .
							"(select column_definition_id from column_definitions where column_name = 'image_id')", $GLOBALS['gPrimaryDatabase']->getName());
						if ($row = getNextRow($resultSet)) {
							$imageTableColumnId = $row['table_column_id'];
						}
						$resultSet = executeQuery("select table_id,column_definition_id from table_columns where table_column_id in " .
							"(select table_column_id from foreign_keys where referenced_table_column_id = ?)", $imageTableColumnId);
						while ($row = getNextRow($resultSet)) {
							$tableName = getFieldFromId("table_name", "tables", "table_id", $row['table_id']);
							if (!in_array($tableName, $this->iExemptionTables)) {
								$this->iDependentTables[] = array("table_name" => $tableName, "column_name" => getFieldFromId("column_name", "column_definitions", "column_definition_id", $row['column_definition_id']));
							}
						}
						foreach ($this->iDependentTables as $tableInfo) {
							$resultSet = executeQuery("select count(*) from " . $tableInfo['table_name'] . " where " . $tableInfo['column_name'] . " = ?", $imageId);
							if ($row = getNextRow($resultSet)) {
								if ($row['count(*)'] > 0) {
									$returnArray['info_message'] = "One or more images cannot be deleted because they are in use.";
								}
							}
						}
						$resultSet = executeQuery("select image_id from images where image_id = ? and client_id = ? and " .
							"((select security_level from security_levels where security_level_id = images.security_level_id) <= ? or security_level_id is null) and remote_storage = 0",
							$imageId, $GLOBALS['gClientId'], $GLOBALS['gUserRow']['security_level']);
						if ($row = getNextRow($resultSet)) {
							foreach ($this->iExemptionTables as $tableName) {
								if ($GLOBALS['gPrimaryDatabase']->tableExists($tableName)) {
									$resultSet = executeQuery("delete from " . $tableName . " where image_id = ?", $imageId);
								}
							}
							$resultSet = executeQuery("delete ignore from images where image_id = ?", $imageId);
						}
					}
				}
				ajaxResponse($returnArray);
				break;
			case "mass_edit":
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					$imageIdArray = array();
					foreach (explode(",", $_POST['mass_image_ids']) as $imageId) {
						$imageId = getFieldFromId("image_id", "images", "image_id", $imageId, "remote_storage = 0");
						if (!empty($imageId)) {
							$imageIdArray[] = $imageId;
						}
					}
					if (empty($imageIdArray)) {
						ajaxResponse($returnArray);
						break;
					}
					$changedValues = array();
					foreach ($_POST as $fieldName => $fieldValue) {
						if (!empty($fieldValue)) {
							$changedValues[str_replace("mass_", "", $fieldName)] = $fieldValue;
						}
					}
					$this->iDatabase->startTransaction();
					foreach ($imageIdArray as $imageId) {
						if (array_key_exists("album_id", $changedValues)) {
							$albumImageId = getFieldFromId("album_image_id", "album_images", "image_id", $imageId, "album_id = ?", $changedValues['album_id']);
							if (empty($albumImageId)) {
								executeQuery("insert ignore into album_images (image_id,album_id,sequence_number) values (?,?,0)", $imageId, $changedValues['album_id']);
							}
						}
						if (!empty($_POST['mass_maximum_dimension'])) {
							$imageFilename = getImageFilename($imageId);
							$extension = getFieldFromId("extension", "images", "image_id", $imageId);
							$filename = $GLOBALS['gDocumentRoot'] . $imageFilename;
							$image = new SimpleImage();
							$image->loadImage($filename);
							$image->resizeMax($_POST['mass_maximum_dimension'], $_POST['mass_maximum_dimension']);
							$image->saveImage($filename, (empty($extension) || !empty($_POST['mass_convert_jpg']) ? "jpg" : $extension));
							$fileContent = file_get_contents($filename);
							$changedValues['image_size'] = strlen($fileContent);
							$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
							if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
								$maxDBSize = 1000000;
							}
							if ($changedValues['image_size'] < $maxDBSize) {
								$changedValues['os_filename'] = "";
								$changedValues['file_content'] = $fileContent;
							} else {
								$changedValues['file_content'] = "";
								$changedValues['os_filename'] = putExternalImageContents($imageId, $extension, $fileContent);
							}
						}
						$table = new DataTable("images");
						$table->setSaveOnlyPresent(true);
						$table->setPrimaryId($imageId);
						$primaryId = $table->saveRecord(array("name_values" => $changedValues));
						if (!$primaryId) {
							$returnArray['error_message'] = $table->getErrorMessage();
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						} else {
							$imageUsageLogControl = new DataColumn("mass_image_usage_log");
							$imageUsageLogControl->setControlValue("data_type", "custom");
							$imageUsageLogControl->setControlValue("control_class", "EditableList");
							$imageUsageLogControl->setControlValue("primary_table", "images");
							$imageUsageLogControl->setControlValue("list_table", "image_usage_log");
							$imageUsageLogControl->setControlValue("column_list", "log_date,content");
							$imageUsageLogControl->setControlValue("list_table_controls", "return array(\"user_id\"=>array(\"default_value\"=>\"" . $GLOBALS['gUserId'] . "\"))");
							$customControl = new EditableList($imageUsageLogControl, $this);
							$customControl->setPrimaryId($primaryId);
							if ($customControl->saveData($_POST) !== true) {
								$returnArray['error_message'] = $customControl->getErrorMessage();
								$this->iDatabase->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
						}
						$resultSet = executeQuery("select * from image_data_types where client_id = ?", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							$fieldName = "image_data_type_id_" . $row['image_data_type_id'];
							if (empty($changedValues[$fieldName])) {
								continue;
							}
							$imageDataId = "";
							$dataSet = executeQuery("select * from image_data where image_data_type_id = ? and image_id = ?",
								$row['image_data_type_id'], $primaryId);
							if ($dataRow = getNextRow($dataSet)) {
								$imageDataId = $dataRow['image_data_id'];
							}
							switch ($row['data_type']) {
								case "date":
									$changedValues[$fieldName] = $this->iDatabase->makeDateParameter($changedValues[$fieldName]);
									break;
							}
							if (empty($imageDataId)) {
								if (!empty($changedValues[$fieldName])) {
									$updateSet = executeQuery("insert into image_data (image_id,image_data_type_id,text_data) values (?,?,?)",
										$primaryId, $row['image_data_type_id'], $changedValues[$fieldName]);
								}
							} else {
								if (!empty($changedValues[$fieldName])) {
									$updateSet = executeQuery("update image_data set text_data = ? where image_data_id = ?",
										$changedValues[$fieldName], $imageDataId);
								} else {
									$updateSet = executeQuery("delete from image_data where image_data_id = ?", $imageDataId);
								}
							}
							if (!empty($updateSet['sql_error'])) {
								$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
								$this->iDatabase->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
						}
					}
					$this->iDatabase->commitTransaction();
				}
				ajaxResponse($returnArray);
				break;
			case "save_image_changes":
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					$imageId = getFieldFromId("image_code", "images", "image_code", $_POST['image_code'],
						"client_id = ? and image_id <> ? and remote_storage = 0", $GLOBALS['gClientId'], $_POST['image_id']);
					if (!empty($imageId)) {
						$returnArray['error_message'] = "Duplicate Image Code found. Please use another.";
						ajaxResponse($returnArray);
						break;
					}
					$this->iDatabase->startTransaction();
					$table = new DataTable("images");
					$table->setSaveOnlyPresent(true);
					if (array_key_exists("file_content_file", $_FILES) && !empty($_FILES['file_content_file']['name'])) {
						$imageId = createImage("file_content_file", array("maximum_width" => $_POST['maximum_dimension'], "maximum_height" => $_POST['maximum_dimension'], "image_id" => $_POST['image_id']));
						if ($imageId === false) {
							$returnArray['error_message'] = "Error creating image";
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						$image = new SimpleImage();
						$image->loadImage($_FILES['file_content_file']['tmp_name']);
						$gpsCoordinates = $image->getGPS();
						$_POST['latitude'] = $gpsCoordinates['latitude'];
						$_POST['longitude'] = $gpsCoordinates['longitude'];
						$dateTaken = $image->getDateTaken();
						$_POST['date_created'] = (empty($dateTaken) ? "" : date("Y-m-d", strtotime($dateTaken)));
						removeCachedData("img_filenames", $imageId);
					} else if (!empty($_POST['maximum_dimension'])) {
						$imageFilename = getImageFilename($_POST['image_id']);
						$extension = getFieldFromId("extension", "images", "image_id", $_POST['image_id']);
						$filename = $GLOBALS['gDocumentRoot'] . $imageFilename;
						$image = new SimpleImage();
						$image->loadImage($filename);
						$image->resizeMax($_POST['maximum_dimension'], $_POST['maximum_dimension']);
						$image->saveImage($filename, (empty($extension) || !empty($_POST['convert_jpg']) ? "jpg" : $extension));
						$fileContent = file_get_contents($filename);
						$_POST['image_size'] = strlen($fileContent);
						$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
						if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
							$maxDBSize = 1000000;
						}
						if ($_POST['image_size'] < $maxDBSize) {
							$_POST['os_filename'] = "";
							$_POST['file_content'] = $fileContent;
						} else {
							$_POST['file_content'] = "";
							$_POST['os_filename'] = putExternalImageContents($imageId, $extension, $fileContent);
						}
					}
					$_POST['hash_code'] = "";
					removeCachedData("img_filenames", $imageId);
					$filename = $GLOBALS['gDocumentRoot'] . "/cache" . getImageFilename($imageId);
					if (file_exists($filename)) {
						unlink($filename);
					}
					$table->setPrimaryId($_POST['image_id']);
					$primaryId = $table->saveRecord(array("name_values" => $_POST));
					getImageFilename($imageId, array("overwrite" => true));
					if (!$primaryId) {
						$returnArray['error_message'] = $table->getErrorMessage();
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					} else {
						$imageUsageLogControl = new DataColumn("image_usage_log");
						$imageUsageLogControl->setControlValue("data_type", "custom");
						$imageUsageLogControl->setControlValue("control_class", "EditableList");
						$imageUsageLogControl->setControlValue("primary_table", "images");
						$imageUsageLogControl->setControlValue("list_table", "image_usage_log");
						$imageUsageLogControl->setControlValue("column_list", "log_date,content");
						$imageUsageLogControl->setControlValue("list_table_controls", "return array(\"user_id\"=>array(\"default_value\"=>\"" . $GLOBALS['gUserId'] . "\"))");
						$customControl = new EditableList($imageUsageLogControl, $this);
						$customControl->setPrimaryId($primaryId);
						if ($customControl->saveData($_POST) !== true) {
							$returnArray['error_message'] = $customControl->getErrorMessage();
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
					$resultSet = executeQuery("select * from image_data_types where client_id = ?", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$imageDataId = "";
						$dataSet = executeQuery("select * from image_data where image_data_type_id = ? and image_id = ?",
							$row['image_data_type_id'], $primaryId);
						if ($dataRow = getNextRow($dataSet)) {
							$imageDataId = $dataRow['image_data_id'];
						}
						$fieldName = "image_data_type_id_" . $row['image_data_type_id'];
						switch ($row['data_type']) {
							case "date":
								$_POST[$fieldName] = $this->iDatabase->makeDateParameter($_POST[$fieldName]);
								break;
						}
						if (empty($imageDataId)) {
							if (!empty($_POST[$fieldName])) {
								$updateSet = executeQuery("insert into image_data (image_id,image_data_type_id,text_data) values (?,?,?)",
									$primaryId, $row['image_data_type_id'], $_POST[$fieldName]);
							}
						} else {
							if (!empty($_POST[$fieldName])) {
								$updateSet = executeQuery("update image_data set text_data = ? where image_data_id = ?",
									$_POST[$fieldName], $imageDataId);
							} else {
								$updateSet = executeQuery("delete from image_data where image_data_id = ?", $imageDataId);
							}
						}
						if (!empty($updateSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
					if (!empty($_POST['image_album_id'])) {
						$albumImageId = getFieldFromId("album_image_id", "album_images", "image_id", $primaryId, "album_id = ?", $_POST['image_album_id']);
						if (empty($albumImageId)) {
							executeQuery("insert ignore into album_images (image_id,album_id,sequence_number) values (?,?,0)", $primaryId, $_POST['image_album_id']);
						}
					}
					$this->iDatabase->commitTransaction();
				}
				if (!empty($_POST['clear_cache'])) {
					$imageRow = getRowFromId("images", "image_id", $_POST['image_id']);
					if (!empty($imageRow)) {
						removeCachedData(getDomainName(true) . ":image_code_filename", $imageRow['image_code'] . "::::", true);
						removeCachedData(getDomainName(true) . ":image_code_filename", $imageRow['image_code'] . ":1:::", true);
						removeCachedData(getDomainName(true) . ":image_code_filename", $imageRow['image_code'] . "::1::", true);
						removeCachedData(getDomainName(true) . ":image_code_filename", $imageRow['image_code'] . ":::1:", true);
						removeCachedData(getDomainName(true) . ":image_id_filename", $imageRow['image_id'] . "::::", true);
						removeCachedData(getDomainName(true) . ":image_id_filename", $imageRow['image_id'] . ":1:::", true);
						removeCachedData(getDomainName(true) . ":image_id_filename", $imageRow['image_id'] . "::1::", true);
						removeCachedData(getDomainName(true) . ":image_id_filename", $imageRow['image_id'] . ":::1:", true);
						executeQuery("update images set hash_code = null where image_id = ?", $imageRow['image_id']);
						$filename = $GLOBALS['gDocumentRoot'] . "/cache/image-full-" . $imageRow['image_id'] . "-" . $imageRow['hash_code'] . "." . (empty($imageRow['extension']) ? "jpg" : $imageRow['extension']);
						unlink($filename);
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_details":
				$imageId = getFieldFromId("image_id", "images", "image_id", $_GET['image_id'], "remote_storage = 0");
				$resultSet = executeQuery("select image_id,image_code,description,detailed_description,link_url,country_id,date_created," .
					"security_level_id,user_group_id,latitude,longitude,notes from images where image_id = ?", $imageId);
				if ($row = getNextRow($resultSet)) {
					$returnArray = $row;
					$returnArray['date_created'] = (empty($row['date_created']) ? "" : date("m/d/Y", strtotime($row['date_created'])));

					$imageUsageLogControl = new DataColumn("image_usage_log");
					$imageUsageLogControl->setControlValue("data_type", "custom");
					$imageUsageLogControl->setControlValue("control_class", "EditableList");
					$imageUsageLogControl->setControlValue("primary_table", "images");
					$imageUsageLogControl->setControlValue("list_table", "image_usage_log");
					$imageUsageLogControl->setControlValue("column_list", "log_date,content");
					$imageUsageLogControl->setControlValue("list_table_controls", "return array(\"user_id\"=>array(\"default_value\"=>\"" . $GLOBALS['gUserId'] . "\"))");
					$customControl = new EditableList($imageUsageLogControl, $this);
					$returnArray = array_merge($returnArray, $customControl->getRecord($imageId));
				}
				$resultSet = executeQuery("select * from image_data_types where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$fieldValue = "";
					$fieldName = "image_data_type_id_" . $row['image_data_type_id'];
					$dataSet = executeQuery("select * from image_data where image_data_type_id = ? and image_id = ?",
						$row['image_data_type_id'], $imageId);
					if (!$dataRow = getNextRow($dataSet)) {
						$dataRow = array();
					}
					switch ($row['data_type']) {
						case "date":
							$fieldValue = (empty($dataRow['text_data']) ? "" : date("m/d/Y", strtotime($dataRow['text_data'])));
							break;
						case "tinyint":
							$fieldValue = (empty($dataRow['text_data']) ? "0" : "1");
							break;
						default:
							$fieldValue = $dataRow['text_data'];
							break;
					}
					$returnArray[$fieldName] = array("data_value" => $fieldValue, "crc_value" => getCrcValue($fieldValue));
				}
				ajaxResponse($returnArray);
				break;
			case "sort_album":
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					$albumId = getFieldFromId("album_id", "albums", "album_id", $_POST['album_id']);
					$sequenceNumber = 100;
					$imageIds = explode(",", $_POST['image_id_list']);
					$resultSet = executeQuery("select image_id from album_images where album_id = ? order by sequence_number", $albumId);
					while ($row = getNextRow($resultSet)) {
						if (!in_array($row['image_id'], $imageIds)) {
							$imageIds[] = $row['image_id'];
						}
					}
					foreach ($imageIds as $imageId) {
						executeQuery("update album_images set sequence_number = ? where album_id = ? and image_id = ?", $sequenceNumber, $albumId, $imageId);
						$sequenceNumber += 100;
					}
				}
				ajaxResponse($returnArray);
				break;
			case "create_album":
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					$albumImageIds = array();
					foreach (explode(",", $_POST['album_image_ids']) as $imageId) {
						$imageId = getFieldFromId("image_id", "images", "image_id", $imageId, "remote_storage = 0");
						if (!empty($imageId)) {
							$albumImageIds[] = $imageId;
						}
					}
					$resultSet = executeQuery("insert into albums (client_id,album_code,description,detailed_description) values (?,?,?,?)",
						$GLOBALS['gClientId'], strtoupper(str_replace(" ", "_", preg_replace("/[^A-Za-z0-9 ]/", '', $_POST['album_description']))),
						$_POST['album_description'], $_POST['album_detailed_description']);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					} else {
						$albumId = $resultSet['insert_id'];
						$returnArray['album_id'] = $resultSet['insert_id'];
						$sequenceNumber = 10;
						foreach ($albumImageIds as $imageId) {
							executeQuery("insert ignore into album_images (album_id,image_id,sequence_number) values (?,?,?)", $albumId, $imageId, $sequenceNumber);
							$sequenceNumber += 10;
						}
						$albumArray = array();
						$resultSet = executeQuery("select * from albums where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							$albumArray[$row['album_id']] = htmlText($row['description']);
						}
						$returnArray['albums'] = $albumArray;
					}
				}
				ajaxResponse($returnArray);
				break;
			case "delete_image":
				if ($GLOBALS['gPermissionLevel'] > _READWRITE) {
					$imageId = $_GET['image_id'];
					$imageTableColumnId = "";
					$resultSet = executeQuery("select table_column_id from table_columns where table_id = " .
						"(select table_id from tables where table_name = 'images' and database_definition_id = " .
						"(select database_definition_id from database_definitions where database_name = ?)) and column_definition_id = " .
						"(select column_definition_id from column_definitions where column_name = 'image_id')", $GLOBALS['gPrimaryDatabase']->getName());
					if ($row = getNextRow($resultSet)) {
						$imageTableColumnId = $row['table_column_id'];
					}
					$resultSet = executeQuery("select table_id,column_definition_id from table_columns where table_column_id in " .
						"(select table_column_id from foreign_keys where referenced_table_column_id = ?)", $imageTableColumnId);
					while ($row = getNextRow($resultSet)) {
						$tableName = getFieldFromId("table_name", "tables", "table_id", $row['table_id']);
						if (!in_array($tableName, $this->iExemptionTables)) {
							$this->iDependentTables[] = array("table_name" => $tableName, "column_name" => getFieldFromId("column_name", "column_definitions", "column_definition_id", $row['column_definition_id']));
						}
					}
					foreach ($this->iDependentTables as $tableInfo) {
						$resultSet = executeQuery("select count(*) from " . $tableInfo['table_name'] . " where " . $tableInfo['column_name'] . " = ?", $imageId);
						if ($row = getNextRow($resultSet)) {
							if ($row['count(*)'] > 0) {
								$returnArray['error_message'] = "The image is used in " . getFieldFromId("description", "tables", "table_name", $tableInfo['table_name']) . " and cannot be deleted.";
								ajaxResponse($returnArray);
								break;
							}
						}
					}
					$resultSet = executeQuery("select image_id from images where image_id = ? and client_id = ? and " .
						"((select security_level from security_levels where security_level_id = images.security_level_id) <= ? or security_level_id is null) and remote_storage = 0",
						$imageId, $GLOBALS['gClientId'], $GLOBALS['gUserRow']['security_level']);
					if ($row = getNextRow($resultSet)) {
						foreach ($this->iExemptionTables as $tableName) {
							if ($GLOBALS['gPrimaryDatabase']->tableExists($tableName)) {
								$resultSet = executeQuery("delete from " . $tableName . " where image_id = ?", $imageId);
							}
						}
						$resultSet = executeQuery("delete ignore from images where image_id = ?", $imageId);
					}
				}
				ajaxResponse($returnArray);
				break;
			case "remove_from_album":
				if ($GLOBALS['gPermissionLevel'] > _READWRITE) {
					executeQuery("delete from album_images where image_id = ? and album_id = ?", $_GET['image_id'], $_GET['album_id']);
				}
				ajaxResponse($returnArray);
				break;
			case "upload_images":
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					if (!is_array($_FILES['new_image']['name'])) {
						$returnArray['error_message'] = "Too many files uploaded. Try with fewer images.";
						ajaxResponse($returnArray);
						break;
					}
					foreach ($_FILES['new_image']['name'] as $fileIndex => $fileName) {
						$tempFile = $_FILES['new_image']['tmp_name'][$fileIndex];

						$fileParts = pathinfo($_FILES['new_image']['name'][$fileIndex]);
						if (empty($fileParts['extension'])) {
							$fileParts['extension'] = "jpg";
						}

						if (in_array(strtolower($fileParts['extension']), $GLOBALS['gValidImageFileTypes'])) {
							$extension = $fileParts['extension'];
							$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
							if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
								$maxDBSize = 1000000;
							}
							if ($_FILES['new_image']['size'][$fileIndex] < $maxDBSize) {
								$fileContent = file_get_contents($_FILES['new_image']['tmp_name'][$fileIndex]);
								$osFilename = "";
							} else {
								$fileContent = "";
								$osFilename = "/documents/tmp." . $extension;
							}
							$description = str_replace("  ", " ", str_replace("-", " ", str_replace("_", " ", $fileParts['filename'])));
							$image = new SimpleImage();
							$image->loadImage($_FILES['new_image']['tmp_name'][$fileIndex]);
							$gpsCoordinates = $image->getGPS();
							$latitude = $gpsCoordinates['latitude'];
							$longitude = $gpsCoordinates['longitude'];
							$dateTaken = $image->getDateTaken();
							$dateCreated = (empty($dateTaken) ? "" : date("Y-m-d", strtotime($dateTaken)));
							$imageCode = makeCode(str_replace("-", "_", $fileParts['filename']));
							if (!empty($imageCode)) {
								$imageId = getFieldFromId("image_id", "images", "image_code", $imageCode);
								$imageNumber = 0;
								while (!empty($imageId)) {
									$imageNumber++;
									$imageId = getFieldFromId("image_id", "images", "image_code", $imageCode . "_" . $imageNumber);
								}
								$imageCode .= (empty($imageNumber) ? "" : "_" . $imageNumber);
							}
                            if (empty($osFilename) && empty($fileContent)) {
                                continue;
                            }
							$resultSet = executeQuery("insert into images (client_id,image_code,user_id,extension,filename,description,os_filename,file_content," .
								"image_size,latitude,longitude,date_created,date_uploaded) values (?,?,?,?,?,?,?,?,?,?,?,?,now())",
								$GLOBALS['gClientId'], $imageCode, $GLOBALS['gUserId'], $extension, $_FILES['new_image']['name'][$fileIndex], $description,
								$osFilename, $fileContent,
								$_FILES['new_image']['size'][$fileIndex], $latitude, $longitude, $dateCreated);
							$primaryId = $resultSet['insert_id'];
							if (!empty($osFilename)) {
								putExternalImageContents($primaryId, $extension, file_get_contents($_FILES['new_image']['tmp_name'][$fileIndex]));
							}
							if (!empty($_POST['upload_album_id'])) {
								$resultSet = executeQuery("select max(sequence_number) from album_images where album_id = ?",
									$_POST['upload_album_id']);
								if ($row = getNextRow($resultSet)) {
									$sequenceNumber = $row['max(sequence_number)'] + 10;
								} else {
									$sequenceNumber = 0;
								}
								$resultSet = executeQuery("insert ignore into album_images (image_id,album_id,sequence_number) values (?,?,?)", $primaryId, $_POST['upload_album_id'], $sequenceNumber);
							}
						} else {
							$returnArray['error_message'] .= $_FILES['new_image']['name'][$fileIndex] . ": Invalid image type. ";
						}
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_image":
				$resultSet = executeQuery("select image_id,description,image_code,date_uploaded,image_size,os_filename " .
					"from images where image_id = ? and client_id = ? and remote_storage = 0", $_GET['image_id'], $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['image_id'] = $row['image_id'];
					$returnArray['description'] = $row['description'];
					$returnArray['image_code'] = $row['image_code'];
					$returnArray['image_size'] = bytesToSize($row['image_size']);
					$returnArray['small_image_url'] = getImageFilename($row['image_id'], array("image_type" => "small"));
					$returnArray['image_url'] = getImageFilename($row['image_id']);
				}
				ajaxResponse($returnArray);
				break;
			case "save_preferences":
				$valuesArray = Page::getPagePreferences();
				foreach ($_POST as $fieldName => $fieldValue) {
					$valuesArray[$fieldName] = $fieldValue;
				}
				Page::setPagePreferences($valuesArray);
				ajaxResponse($returnArray);
				break;
			case "get_images":
				$albumId = getFieldFromId("album_id", "albums", "album_id", $_POST['album_id']);
				$imageArray = array();
				$validSortOrders = array("description", "image_code", "image_size_desc", "image_size", "date_uploaded_desc", "date_uploaded", "sequence");
				switch ($_POST['sort_order']) {
					case "image_size_desc":
						$orderBy = "image_size desc";
						break;
					case "date_uploaded_desc":
						$orderBy = "date_uploaded desc";
						break;
					case "sequence":
						if (empty($albumId)) {
							$orderBy = "description";
						} else {
							$orderBy = "sequence_number";
						}
						break;
					default:
						if (!empty($_POST['sort_order']) && in_array($_POST['sort_order'], $validSortOrders)) {
							$orderBy = $_POST['sort_order'];
						} else {
							$orderBy = "description";
							$_POST['sort_order'] = $orderBy;
						}
						break;
				}
				if (empty($albumId)) {
					setUserPreference("MAINTENANCE_SORT_ORDER_COLUMN", $_POST['sort_order'], $GLOBALS['gPageRow']['page_code']);
				}
				$countQuery = "select count(*) from images" . (empty($albumId) ? "" : " join album_images using (image_id)") . " where client_id = ? and remote_storage = 0 " .
					(empty($_POST['search_text']) || !is_numeric($_POST['search_text']) ? "and not exists (select image_id from fragments where image_id = images.image_id) and not exists (select image_id from products where image_id = images.image_id) " .
					"and not exists (select image_id from contacts where image_id = images.image_id) and not exists (select image_id from product_images where image_id = images.image_id)" : "");
				$query = "select image_id,description,image_code,date_uploaded,image_size,os_filename,remote_storage " .
					"from images" . (empty($albumId) ? "" : " join album_images using (image_id)") . " where client_id = ? " .
					(empty($_POST['search_text']) || !is_numeric($_POST['search_text']) ? "and not exists (select image_id from fragments where image_id = images.image_id) and not exists (select image_id from products where image_id = images.image_id) " .
					"and not exists (select image_id from contacts where image_id = images.image_id) and not exists (select image_id from product_images where image_id = images.image_id)" : "");
				$parameters = array($GLOBALS['gClientId']);
				if (!empty($albumId)) {
					$query .= " and album_id = ?";
					$countQuery .= " and album_id = ?";
					$parameters[] = $albumId;
				}
				$searchFullText = "";
				$searchText = "";
				$searchParts = explode(" ", $_POST['search_text']);
				foreach ($searchParts as $thisPart) {
					if (!empty($thisPart)) {
						$searchFullText .= (empty($searchFullText) ? "" : " ") . "+" . $thisPart;
					}
				}
				if (!empty($_POST['search_text'])) {
					$searchText = $_POST['search_text'] . "%";
				}
				if (!empty($searchText)) {
					$tableId = getFieldFromId("table_id", "tables", "table_name", "images");
					$searchFields = array("image_code", "description", "detailed_description");
					if (is_numeric($_POST['search_text'])) {
						$thisQuery = "image_id = ?";
						$parameters[] = $_POST['search_text'];
					} else {
						$thisQuery = "";
					}
					foreach ($searchFields as $searchFieldName) {
						$fullText = getFieldFromId("full_text", "table_columns", "table_id", $tableId,
							"column_definition_id = (select column_definition_id from column_definitions where column_name = ?)", $searchFieldName);
						if ($fullText) {
							$thisQuery .= (empty($thisQuery) ? "" : " or ") . "match(" . $searchFieldName . ") against (? in boolean mode) or " . $searchFieldName . " like ?";
							$parameters[] = $searchFullText;
							$parameters[] = $searchText;
						} else {
							$thisQuery .= (empty($thisQuery) ? "" : " or ") . $searchFieldName . " like ?";
							$parameters[] = $searchText;
						}
					}
					$query .= " and (" . $thisQuery . ")";
					$countQuery .= " and (" . $thisQuery . ")";
				}
				foreach ($_POST as $fieldName => $fieldValue) {
					if (!startsWith($fieldName, "image_data_type_id_") || empty($fieldValue)) {
						continue;
					}
					$imageDataTypeId = getFieldFromId("image_data_type_id","image_data_types","image_data_type_id",substr($fieldName,strlen("image_data_type_id_")));
					if (empty($imageDataTypeId)) {
						continue;
					}
					$query .= " and image_id " . ($fieldValue == "n" ? "not " : "") . "in (select image_id from image_data where text_data is not null and image_data_type_id = " . $imageDataTypeId . ")";
				}
				if (!is_numeric($_POST['start_image'])) {
					$_POST['start_image'] = 0;
				}
				$limit = $_POST['images_per_page'];
				if (empty($limit)) {
					$limit = 10;
				}
				if ($limit > 100) {
					$limit = 100;
				}
				$query .= " order by " . $orderBy . ",image_id" . (strpos($orderBy, " desc") !== false ? " desc" : "") . " limit " . $limit . " offset " . $_POST['start_image'];
				$returnArray['image_count'] = 0;
				$countSet = executeQuery($countQuery, $parameters);
				if ($countRow = getNextRow($countSet)) {
					$returnArray['image_count'] = $countRow['count(*)'];
				}
				$resultSet = executeQuery($query, $parameters);
				while ($row = getNextRow($resultSet)) {
					if (!empty($row['remote_storage'])) {
						continue;
					}
					$thisImage = array();
					$thisImage['image_id'] = $row['image_id'];
					$thisImage['description'] = $row['description'];
					$thisImage['image_code'] = $row['image_code'];
					if (empty($row['image_size'])) {
						if (!empty($row['os_filename'])) {
							$fileContent = getExternalImageContents($row['os_filename']);
							if (strlen($fileContent) > 0) {
								$imageSize = strlen($fileContent);
								if (!empty($imageSize)) {
									executeQuery("update images set image_size = ? where image_id = ?", filesize($row['os_filename']), $row['image_id']);
								}
								$row['image_size'] = $imageSize;
							}
						} else {
							$sizeSet = executeQuery("select length(file_content) as content_length from images where image_id = ?", $row['image_id']);
							if ($sizeRow = getNextRow($sizeSet)) {
								if ($sizeRow['content_length'] > 0) {
									executeQuery("update images set image_size = ? where image_id = ?", $sizeRow['content_length'], $row['image_id']);
									$row['image_size'] = $sizeRow['content_length'];
								}
							}
						}
					}
					$thisImage['image_size'] = bytesToSize($row['image_size']);
					$thisImage['date_uploaded'] = date("m/d/Y", strtotime($row['date_uploaded']));
					$thisImage['base_filename'] = getImageFilename($row['image_id'], array("base_filename_only" => true));
					$imageArray[] = $thisImage;
				}
				$returnArray['images'] = $imageArray;
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).keydown(function (event) {
                if (event.which == 34) {
                    $("#next_page").trigger("click");
                    return false;
                } else if (event.which == 33) {
                    $("#previous_page").trigger("click");
                    return false;
                }
            });
            $(document).on("click", "#previous_page", function () {
                if ($(this).hasClass("invisible")) {
                    return false;
                }
                var pageNumber = $("#page_number").val();
                pageNumber--;
                if (isNaN(pageNumber) || pageNumber <= 0) {
                    pageNumber = 1;
                }
                $("#page_number").val(pageNumber);
                getContents();
            });
            $(document).on("click", "#next_page", function () {
                if ($(this).hasClass("invisible")) {
                    return false;
                }
                var pageNumber = $("#page_number").val();
                pageNumber++;
                var totalPages = parseInt($("#total_pages").html());
                if (isNaN(pageNumber) || pageNumber > totalPages) {
                    pageNumber = totalPages;
                }
                $("#page_number").val(pageNumber);
                getContents();
            });
            $("#_preference_button").click(function () {
                $('#_preference_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    width: 600,
                    title: 'Preferences',
                    buttons: {
                        Save: function (event) {
                            if ($("#_preference_form").validationEngine('validate')) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_preferences", $("#_preference_form").serialize(), function(returnArray) {
                                    getContents();
                                });
                                $("#_preference_dialog").dialog('close');
                            }
                        },
                        Cancel: function (event) {
                            $("#_preference_dialog").dialog('close');
                        }
                    }
                });
            })
            $(".modal").attr("id", "modal_div");
            $(document).on("click", ".fa-edit", function () {
                var imageId = $(this).closest(".image-section").data("image_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_details&image_id=" + imageId, function(returnArray) {
                    $("#_edit_image").find("input,textarea,select").each(function () {
                        $(this).removeData("crc_value");
                        if ($(this).attr("id") in returnArray) {
                            var fieldId = $(this).attr("id");
                            if (returnArray[fieldId] != null) {
                                if ($(this).is("input[type=checkbox]")) {
                                    $(this).prop("checked", returnArray[fieldId].data_value == 1);
                                } else {
                                    if (typeof returnArray[fieldId] == "object" && "data_value" in returnArray[fieldId]) {
                                        $(this).val(returnArray[fieldId]['data_value']);
                                    } else {
                                        $(this).val(returnArray[fieldId]);
                                    }
                                }
                            } else {
                                if ($(this).is("input[type=checkbox]")) {
                                    $(this).prop("checked", false);
                                } else {
                                    $(this).val("");
                                }
                            }
                        } else {
                            if ($(this).is("input[type=checkbox]")) {
                                $(this).prop("checked", false);
                            } else {
                                $(this).val("");
                            }
                        }
                    });
                    $("#image_id_display").val(returnArray['image_id']);
                    $("#_image_usage_log_table tr").not(":first").not(":last").remove();
                    if ("image_usage_log" in returnArray) {
                        for (var j in returnArray['image_usage_log']) {
                            addEditableListRow('image_usage_log', returnArray['image_usage_log'][j]);
                        }
                    }
                    $('#_edit_image_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                        width: 1000,
                        title: '<?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "Edit" : "View") ?> Image Details',
                        buttons: {
							<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                            Save: function (event) {
                                if ($("#_edit_image").validationEngine('validate')) {
                                    $("body").addClass("waiting-for-ajax");
                                    $("#_edit_image").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_image_changes").attr("method", "POST").attr("target", "post_iframe").submit();
                                    $("#_post_iframe").off("load");
                                    $("#_post_iframe").on("load", function () {
                                        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                                        var returnText = $(this).contents().find("body").html();
                                        const returnArray = processReturn(returnText);
                                        if (returnArray === false) {
                                            return;
                                        }
                                        if (!("error_message" in returnArray)) {
                                            $("#_edit_image").find("input,textarea,select").each(function () {
                                                if ($(this).is("input[type=checkbox]")) {
                                                    $(this).prop("checked", false).removeData("crc_value");
                                                } else {
                                                    $(this).val("").removeData("crc_value");
                                                }
                                            });
                                            displayImageBlock(imageId);
                                            $("#_edit_image_dialog").dialog('close');
                                            $("#_edit_image_error_message").html("");
                                        } else {
                                            $("#_edit_image_error_message").html(returnArray['error_message']);
                                            $("#image_code").focus();
                                        }
                                    });
                                }
                            },
							<?php } ?>
                            Cancel: function (event) {
                                $("#_edit_image_dialog").dialog('close');
                            }
                        }
                    });
                });
                return false;
            });
			<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
            $(document).on("click", "#create_album", function () {
                var imageIds = "";
                if ($(".image-section.selected").length > 0) {
                    $("#album_selected_images").html($(".image-section.selected").length + " images will be added to this album.");
                    $(".image-section.selected").each(function () {
                        imageIds += (imageIds == "" ? "" : ",") + $(this).data("image_id");
                    });
                } else {
                    $("#album_selected_images").html("");
                }
                $("#album_image_ids").val(imageIds);
                $('#_new_album_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    width: 800,
                    title: 'New Album',
                    buttons: {
                        Save: function (event) {
                            if ($("#_new_album").validationEngine('validate')) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_album", $("#_new_album").serialize(), function(returnArray) {
                                    if ("albums" in returnArray) {
                                        $("#album_id option[value!='']").remove();
                                        $("#image_album_id option[value!='']").remove();
                                        $("#mass_album_id option[value!='']").remove();
                                        for (var i in returnArray['albums']) {
                                            $("#album_id").append($("<option></option>").attr("value", i).text(returnArray['albums'][i]));
                                            $("#image_album_id").append($("<option></option>").attr("value", i).text(returnArray['albums'][i]));
                                            $("#mass_album_id").append($("<option></option>").attr("value", i).text(returnArray['albums'][i]));
                                        }
                                    }
                                    if ("album_id" in returnArray) {
                                        $("#album_id").val(returnArray['album_id']);
                                    }
                                    $("#album_id").trigger("change");
                                });
                                $("#_new_album_dialog").dialog('close');
                            }
                        },
                        Cancel: function (event) {
                            $("#_new_album_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", "#mass_delete", function () {
                var imageIds = "";
                if ($(".image-section.selected").length > 1) {
                    $("#mass_delete_selected_images").html($(".image-section.selected").length + " images will be deleted.");
                    $(".image-section.selected").each(function () {
                        imageIds += (imageIds == "" ? "" : ",") + $(this).data("image_id");
                    });
                } else {
                    disableButtons($("#mass_delete"));
                    return;
                }
                $("#mass_delete_image_ids").val(imageIds);
                $('#_delete_all_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    width: 600,
                    title: 'Delete Multiple Images',
                    buttons: {
                        Save: function (event) {
                            if ($("#_mass_delete").validationEngine('validate')) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mass_delete", $("#_mass_delete").serialize(), function(returnArray) {
                                    getContents();
                                });
                                $("#_delete_all_dialog").dialog('close');
                            }
                        },
                        Cancel: function (event) {
                            $("#_delete_all_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", "#mass_edit", function () {
                var imageIds = "";
                if ($(".image-section.selected").length > 1) {
                    $("#mass_selected_images").html($(".image-section.selected").length + " images will be edited when saved.");
                    $(".image-section.selected").each(function () {
                        imageIds += (imageIds == "" ? "" : ",") + $(this).data("image_id");
                    });
                } else {
                    disableButtons($("#mass_edit"));
                    return;
                }
                $("#_mass_edit").find("input,textarea,select").each(function () {
                    $(this).removeData("crc_value");
                    if ($(this).is("input[type=checkbox]")) {
                        $(this).prop("checked", false);
                    } else {
                        $(this).val("");
                    }
                });
                $("#_mass_image_usage_log_table tr").not(":first").not(":last").remove();
                $("#mass_image_ids").val(imageIds);
                $('#_mass_edit_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    width: 800,
                    title: 'Edit Multiple Images',
                    buttons: {
                        Save: function (event) {
                            if ($("#_mass_edit").validationEngine('validate')) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mass_edit", $("#_mass_edit").serialize(), function(returnArray) {
                                    $("#_mass_image").find("input,textarea,select").each(function () {
                                        if ($(this).is("input[type=checkbox]")) {
                                            $(this).prop("checked", false).removeData("crc_value");
                                        } else {
                                            $(this).val("").removeData("crc_value");
                                        }
                                    });
                                });
                                $("#_mass_edit_dialog").dialog('close');
                            }
                        },
                        Cancel: function (event) {
                            $("#_mass_edit_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
			<?php } ?>
			<?php if ($GLOBALS['gPermissionLevel'] > _READWRITE) { ?>
            $(document).on("click", ".fa-trash", function (event) {
                var imageId = $(this).closest(".image-section").data("image_id");
                var btns = {};
                if ($("#album_id").val() != "") {
                    btns["Remove from Album"] = function () {
                        var albumId = $("#album_id").val();
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_from_album&image_id=" + imageId + "&album_id=" + albumId, function(returnArray) {
                            if (!("error_message" in returnArray)) {
                                $("#image_section_" + imageId).remove();
                            }
                        });
                        $("#_delete_image_dialog").dialog("close");
                    };
                    $("#delete_image_text").html("Do you want to permanently delete this image or just remove it from this album?");
                } else {
                    $("#delete_image_text").html("Are you sure you want to permanently delete this image?");
                }
                $("#dialog_image").attr("src", $(this).closest(".image-section").find(".image").find("img").attr("src"));
                btns["Delete Image"] = function () {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_image&image_id=" + imageId, function(returnArray) {
                        if (!("error_message" in returnArray)) {
                            $("#image_section_" + imageId).remove();
                            if ($("#image_library").find(".image-section").length == 0) {
                                getContents();
                            }
                        }
                    });
                    $("#_delete_image_dialog").dialog("close");
                };
                btns["Cancel"] = function () {
                    $("#_delete_image_dialog").dialog("close");
                };
                $('#_delete_image_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    width: 500,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    title: 'Delete Image',
                    buttons: btns
                });
                event.stopPropagation();
                return false;
            });
			<?php } ?>
			<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
            $(document).on("click", ".fa-download", function (event) {
                document.location = "/getimage.php?id=" + $(this).closest(".image-section").data("image_id") + "&force_download=true";
                event.preventDefault();
                event.stopPropagation();
            });
			<?php } ?>
            $("#display_search_text").keyup(function (event) {
                if (event.which == 13 || event.which == 3) {
                    searchImages();
                    return false;
                }
            }).keydown(function () {
                $(".fa-search").data("show_all", "");
            });
            $(".fa-search").click(function () {
                if ($(this).data("show_all") == "true") {
                    $(this).data("show_all", "");
                    $("#display_search_text").val("");
                }
                searchImages();
            });
            $(document).on("blur", ".link-text", function () {
                $(this).hide();
            });
            $(document).on("click", ".fa-link", function (event) {
                var imageCode = $(this).closest(".image-section").find(".image-code").text();
                var imageId = $(this).closest(".image-section").data("image_id");
                var linkUrl = "/getimage.php?" + (imageCode == "" ? "id=" + imageId : "code=" + imageCode);
                $(this).closest(".image-section").find(".link-text").val(linkUrl).show().select().focus();
                event.preventDefault();
                event.stopPropagation();
            });
            $(document).on("click", ".image-section", function () {
                $(this).toggleClass("selected");
                var count = $("#_page_number_controls").data("image_count");
                var selected = $(".image-section.selected").length;
                $("#_page_number_controls").find("#count_display").html(count + " image" + (count == 1 ? "" : "s") + (selected > 0 ? ", " + selected + " selected" : ""));
                if (selected > 1) {
                    enableButtons($("#mass_edit"));
                    enableButtons($("#mass_delete"));
                } else {
                    disableButtons($("#mass_edit"));
                    disableButtons($("#mass_delete"));
                }
            });
            $("#_page_number_controls").html("<div id='count_display'></div><div id='page_wrapper'>\n" +
                "<input type='hidden' value='1' id='page_number'>\n" +
                "<span class='fas fa-chevron-left' id='previous_page'></span>\n" +
                "<span id='page_number_display_wrapper'>Page <span id='page_number_display'>1</span> of <span id='total_pages'></span></span>\n" +
                "<span class='fas fa-chevron-right' id='next_page'></span>\n" +
                "</div>");
			<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
            $(document).on("click", "#upload_images", function () {
                $("#new_image").trigger("click");
            });
            $(document).on("change", "#new_image", function () {
                $("#modal_div").addClass("modal");
                $("body").addClass("waiting-for-ajax");
                $("#upload_album_id").val($("#album_id").val());
                $("#upload_images").html("Uploading...");
                $("#_upload_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_page=show&url_action=upload_images").attr("method", "POST").attr("target", "post_iframe").submit();
                $("#_post_iframe").off("load");
                $("#_post_iframe").on("load", function () {
                    $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                    var returnText = $(this).contents().find("body").html();
                    const returnArray = processReturn(returnText);
                    if (returnArray === false) {
                        return;
                    }
                    $("#upload_images").html("Upload Images");
                    $("#new_image").prop("disabled", false).val("");
                    if ("error_message" in returnArray) {
                        if (typeof regardlessFunction == "function") {
                            regardlessFunction();
                        }
                        regardlessFunction = "";
                    } else {
                        getContents();
                    }
                    $("body").removeClass("waiting-for-ajax");
                    $("#modal_div").removeClass("modal");
                });
                $(this).prop("disabled", true);
            });
			<?php } ?>
            $("#album_id").change(function () {
                if (empty($(this).val())) {
                    $("#image_library").sortable("destroy");
                    $("#album_sequence").remove();
                    $("#sort_order").val($("#save_sort_order").val());
                    $("#save_sort_order").val("");
                } else {
                    if ($("#save_sort_order").val() == "") {
                        $("#save_sort_order").val($("#sort_order").val());
                    }
                    if ($("#album_sequence").length == 0) {
                        $("#sort_order").append($("<option></option>").attr("value", "sequence").attr("id", "album_sequence").text("Album Sequence"));
                        $("#sort_order").val("sequence");
                    }
                    $("#image_library").sortable({
                        tolerance: "pointer",
                        placeholder: 'placeholder',
                        distance: 5,
                        update: function (event, ui) {
                            var imageIdList = "";
                            $("#image_library").find(".image-section").each(function () {
                                imageIdList += (imageIdList == "" ? "" : ",") + $(this).data("image_id");
                            });
                            var albumId = $("#album_id").val();
                            if (albumId != "") {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=sort_album", {image_id_list: imageIdList, album_id: albumId});
                            }
                        }
                    });
                }
                getContents();
            });
            getContents();
            $("#_page_number_controls").show();
            $(".page-buttons,.page-list-buttons").html($("#button_pane").html());
            $(".page-form-buttons").remove();
            $(".page-action-selector").remove();
            $(".page-list-control").not(".page-list-buttons").not(".page-buttons").remove();
            $(".page-controls").show();
            $("#button_pane").html("");
            disableButtons($("#mass_edit"));
            disableButtons($("#mass_delete"));
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>

            function displayImageBlock(imageId) {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_image&image_id=" + imageId, function(returnArray) {
                    if ("image_id" in returnArray) {
                        $("#image_section_" + returnArray['image_id']).find(".description").html(returnArray['description']);
                        $("#image_section_" + returnArray['image_id']).find(".image-code").html(returnArray['image_code']);
                        $("#image_section_" + returnArray['image_id']).find(".image-size").html(returnArray['image_size']);
                        $("#image_section_" + returnArray['image_id']).find(".image").find("img").attr("src", returnArray['small_image_url']);
                    }
                });
            }

            var loading = false;

            function searchImages() {
                $(".fa-search").data("show_all", "true");
                $("#search_text").val($("#display_search_text").val());
                getContents();
            }

            function getContents() {
                if (loading) {
                    return;
                }
                loading = true;
                $("#image_library").html("");
                var postData = new Object;
                postData['start_image'] = $("#images_per_page").val() * ($("#page_number").val() - 1);
                postData['sort_order'] = $("#sort_order").val();
                postData['album_id'] = $("#album_id").val();
                postData['search_text'] = $("#search_text").val();
                postData['page_number'] = $("#page_number").val();
                postData['images_per_page'] = $("#images_per_page").val();
                $(".image-data-type").each(function() {
                    if (!empty($(this).val())) {
                        postData[$(this).attr("id")] = $(this).val();
                    }
                });
                $("#page_number_display").html($("#page_number").val());
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_images", postData, function(returnArray) {
                    if ("image_count" in returnArray) {
                        $("#_page_number_controls").data("image_count", returnArray['image_count']).find("#count_display").html(returnArray['image_count'] + " image" + (returnArray['image_count'] == 1 ? "" : "s"));
                        var imagesPerPage = $("#images_per_page").val();
                        var totalPages = Math.ceil(returnArray['image_count'] / imagesPerPage);
                        var pageNumber = $("#page_number").val();
                        if (pageNumber <= 1) {
                            $("#previous_page").addClass("invisible");
                        } else {
                            $("#previous_page").removeClass("invisible");
                        }
                        if (pageNumber >= totalPages) {
                            $("#next_page").addClass("invisible");
                        } else {
                            $("#next_page").removeClass("invisible");
                        }
                        $("#total_pages").html(empty(totalPages) ? "1" : totalPages);
                    }
                    var displayType = $("#display_type").val();
                    if ("images" in returnArray) {
                        for (var i in returnArray['images']) {
                            returnArray['images'][i]['small_image_url'] = "/cache/image-small-" + returnArray['images'][i]['base_filename'];
                            returnArray['images'][i]['thumbnail_image_url'] = "/cache/image-thumbnail-" + returnArray['images'][i]['base_filename'];
                            returnArray['images'][i]['image_url'] = "/cache/image-full-" + returnArray['images'][i]['base_filename'];
                            var imageSection = $("#_image_" + displayType + "_template").html();
                            for (var fieldName in returnArray['images'][i]) {
                                var re = new RegExp("%" + fieldName + "%", 'g');
                                imageSection = imageSection.replace(re, returnArray['images'][i][fieldName]);
                            }
                            imageSection = imageSection.replace(/%src%/g, "src");
                            $("#image_library").append(imageSection);
                        }
                        $("a[href*='download.php']").attr("target", "_blank");
                        if ($().prettyPhoto) {
                            $("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({social_tools: false, default_height: 480, default_width: 854, deeplinking: false});
                        }
                        $("#image_library").append("<div class='clear-div'></div>");
                    }
                    if ($("#album_id").val() != "") {
                        $("#image_library .image-section").addClass("album-contents");
                    }
                    loading = false;
                    $("#modal_div").addClass("modal");
                });
            }
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #next_page, #previous_page {
                cursor: pointer;
            }

            #next_page:hover, #previous_page:hover {
                color: rgb(200, 200, 200);
            }

            #count_display {
                display: inline-block;
            }

            #page_wrapper {
                margin-left: 60px;
                display: inline-block;
            }

            #page_wrapper span {
                margin: 0 5px;
            }

            #_page_header_wrapper {
                display: none;
            }

            #_page_header {
                display: none;
            }

            #_header_wrapper {
                height: 80px;
            }

            #_page_header h3 {
                margin: 0;
                padding: 0;
                margin-bottom: 2px;
            }

            #_control_bar {
                position: relative;
                padding-bottom: 20px;
                max-width: 100%;
            }

            #_control_bar div {
                display: inline-block;
            }

            #_album_selector {
                margin-right: 30px;
                text-align: left;
            }

            #_album_selector select {
                width: 150px;
                font-size: 1.0rem;
            }

            #_search_control {
                margin-right: 30px;
                text-align: left;
                position: relative;
            }

            #_search_control input {
                width: 250px;
                font-size: .7rem;
                padding-right: 20px;
            }

            #_search_control .fa {
                position: absolute;
                top: 5px;
                right: 5px;
                z-index: 500;
                font-size: 1rem;
            }

            #upload_div {
                display: block;
                height: 1px;
                width: 1px;
            }

            #upload_div input {
                display: block;
                position: absolute;
                top: -40px;
                left: 0;
                font-size: 20px;
                opacity: 0;
                filter: alpha(opacity:0);
                position: relative;
                cursor: pointer;
                top: -40px;
            }

            .placeholder {
                width: 200px;
                height: 260px;
                border: 4px dashed rgb(180, 180, 180);
                float: left;
                margin-left: -4px;
                background: rgb(240, 240, 200);
                margin-top: 10px;
            }

            .image-section.album-contents {
                cursor: move;
            }

            .image-section.selected {
                background-color: rgb(170, 200, 240);
            }

            .image-block {
                width: 200px;
                height: 260px;
                border: 4px solid rgb(180, 180, 180);
                float: left;
                margin-right: 10px;
                margin-top: 10px;
                position: relative;
                overflow: hidden;
            }

            .image-block .description {
                text-align: center;
                height: 20px;
                white-space: nowrap;
                font-size: 12px;
                font-weight: bold;
                position: relative;
                top: 5px;
            }

            .image-block .image-code {
                text-align: center;
                height: 10px;
                white-space: nowrap;
                font-size: 8px;
            }

            .image-block .image-size {
                text-align: center;
                height: 10px;
                white-space: nowrap;
                font-size: 8px;
            }

            .image-block .date-uploaded {
                text-align: center;
                height: 10px;
                white-space: nowrap;
                font-size: 8px;
                margin-bottom: 5px;
            }

            .image-block .image {
                text-align: center;
            }

            .image-block .image img {
                max-width: 180px;
                max-height: 160px;
            }

            .image-block .image-button-block {
                position: absolute;
                left: 0;
                bottom: 5px;
                text-align: center;
                width: 100%;
            }

            input[type=text].link-text {
                z-index: 500;
                display: block;
                display: none;
                width: 100%;
                position: absolute;
                left: 0;
                bottom: 4px;
                font-size: 10px;
                background-color: rgb(255, 255, 255);
            }

            .image-line {
                width: 100%;
                height: 40px;
                border-bottom: 1px solid rgb(220, 220, 220);
                position: relative;
                overflow: hidden;
                display: flex;
                margin-bottom: 5px;
            }

            .image-line div {
                flex: 0 0 16.6666%;
                padding: 0 10px;
                overflow: hidden;
            }

            .image-line .image-button-block {
                position: relative;
                left: 0;
                bottom: 0;
                text-align: left;
                width: auto;
            }

            .image-line .image img {
                max-width: 90%;
                max-height: 34px;
            }

            .image-line .image {
                width: 100px;
            }

            .image-line div {
                line-height: 40px;
                font-size: 1rem;
            }

            .image-line input[type=text].link-text {
                z-index: 500;
                display: block;
                display: none;
                max-width: 100%;
                border: 1px solid rgb(0, 0, 0);
                padding: 5px;
                width: 300px;
                position: absolute;
                left: auto;
                right: 20px;
                bottom: 4px;
                height: 32px;
                font-size: 16px;
                background-color: rgb(255, 255, 255);
            }

            .image-button-block span {
                font-size: 1rem;
                margin: 0 5px;
                cursor: pointer;
                color: rgb(50, 50, 50);
            }

            .fa-search {
                cursor: pointer;
            }
        </style>
		<?php
	}

	function mainContent() {
		?>
        <div id="_control_bar">

            <div id="_album_selector">
                <select id="album_id" name="album_id">
                    <option value="">[Select Album]</option>
					<?php
					$resultSet = executeQuery("select * from albums where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['album_id'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
            </div>

            <div id="_search_control">
                <input type="text" id="display_search_text" name="display_search_text">
                <input type="hidden" id="search_text" name="search_text">
                <span class="fa fa-search"></span>
            </div>

            <div id="_preference_button_wrapper">
                <button id="_preference_button">Preferences</button>
            </div>

            <div class='clear-div'></div>
        </div>

        <div id="image_library">
        </div>
		<?php
		return true;
	}

	function hiddenElements() {
		$valuesArray = Page::getPagePreferences();
		if (empty($valuesArray['images_per_page'])) {
			$valuesArray['images_per_page'] = 10;
		}
		if ($valuesArray['images_per_page'] > 100) {
			$valuesArray['images_per_page'] = 100;
		}
		if (empty($valuesArray['display_type'])) {
			$valuesArray['display_type'] = 'block';
		}
		?>
        <div id="_preference_dialog" class="dialog-box">
            <form id="_preference_form">
                <div class="basic-form-line" id="_sort_control_row">
                    <input type="hidden" id="save_sort_order" name="save_sort_order">
                    <label>Sort By</label>
                    <select id="sort_order" name="sort_order">
                        <option value="description" <?= ($valuesArray['sort_order'] == "description" ? " selected" : "") ?>>Description</option>
                        <option value="image_code" <?= ($valuesArray['sort_order'] == "image_code" ? " selected" : "") ?>>Image Code</option>
                        <option value="image_size_desc" <?= ($valuesArray['sort_order'] == "image_size_desc" ? " selected" : "") ?>>Size - Largest First</option>
                        <option value="image_size" <?= ($valuesArray['sort_order'] == "image_size" ? " selected" : "") ?>>Size - Smallest First</option>
                        <option value="date_uploaded_desc" <?= ($valuesArray['sort_order'] == "date_uploaded_desc" ? " selected" : "") ?>>Date - Newest First</option>
                        <option value="date_uploaded" <?= ($valuesArray['sort_order'] == "date_uploaded" ? " selected" : "") ?>>Date - Oldest First</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class='basic-form-line' id="_images_per_page_row">
                    <label>Images per page</label>
                    <input id='images_per_page' name='images_per_page' type="text" size="10" class="validate[required,custom[integer],min[10],max[100]" ] value="<?= $valuesArray['images_per_page'] ?>">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class='basic-form-line' id="_display_type_row">
                    <label>Display Type</label>
                    <select id='display_type' name='display_type'>
                        <option value='block' <?= ($valuesArray['display_type'] == 'block' ? "selected" : "") ?>>Block</option>
                        <option value='line' <?= ($valuesArray['display_type'] == 'line' ? "selected" : "") ?>>Line</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

	            <?php
	            $resultSet = executeQuery("select * from image_data_types where inactive = 0 and internal_use_only = 0 and data_type = 'tinyint' and client_id = ?", $GLOBALS['gClientId']);
	            while ($row = getNextRow($resultSet)) {
		            ?>
                    <div class='basic-form-line' id="_image_data_type_<?= strtolower($row['image_data_type_id']) ?>_row">
                        <label for='<?= strtolower($row['image_data_type_id']) ?>'><?= htmlText($row['description']) ?></label>
                        <select class='image-data-type' id='image_data_type_id_<?= strtolower($row['image_data_type_id']) ?>' name='image_data_type_id_<?= strtolower($row['image_data_type_id']) ?>'>
                            <option value=''>[All]</option>
                            <option <?= ($valuesArray['image_data_type_id_' . strtolower($row['image_data_type_id'])] == 'y' ? "selected " : "") ?>value='y'>Yes</option>
                            <option <?= ($valuesArray['image_data_type_id_' . strtolower($row['image_data_type_id'])] == 'n' ? "selected " : "") ?>value='n'>No</option>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

		            <?php
	            }
	            ?>
            </form>
        </div>

        <div id="button_pane">
			<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                <button id="upload_images">Upload Images</button>
                <button id="create_album">Create Album</button>
                <button id="mass_edit">Mass Edit</button>
                <button id="mass_delete">Delete All</button>
                <div id="upload_div">
                    <form id="_upload_form" name="_upload_form" method="post" enctype='multipart/form-data'>
                        <input type="file" id="new_image" name="new_image[]" multiple="multiple">
                        <input type="hidden" id="upload_album_id" name="upload_album_id">
                    </form>
                </div>
			<?php } ?>
        </div>
        <div id="_edit_image_dialog" class="dialog-box">
            <form id="_edit_image" enctype='multipart/form-data'>
                <input type="hidden" id="image_id" name="image_id"/>
                <p id="_edit_image_error_message" class="error-message"></p>
                <div class="basic-form-line" id="_image_id_display_row">
                    <label>Image ID</label>
                    <input type="text" readonly='readonly' class="field-text" size="10" id="image_id_display" name="image_id_display"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
				<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                    <div class="basic-form-line" id="_image_album_id_row">
                        <label for="image_album_id">Move To Album</label>
                        <select id="image_album_id" name="image_album_id" class="field-text">
                            <option value="">[Select]</option>
							<?php
							$resultSet = executeQuery("select * from albums where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								?>
                                <option value="<?= $row['album_id'] ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
				<?php } ?>
                <div class="basic-form-line" id="_description_row">
                    <label for="description" class="required-label">Title (title tag)</label>
                    <input type="text" <?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "" : "readonly='readonly'") ?> class="field-text validate[required]" size="60" maxlength="255" id="description" name="description"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_image_code_row">
                    <label for="image_code">Code</label>
                    <input type="text" <?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "" : "readonly='readonly'") ?> class="field-text code-value uppercase validate[]" size="40" maxlength="100" id="image_code" name="image_code"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_link_url_row">
                    <label for="link_url">Link URL</label>
                    <input type="text" class="field-text" <?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "" : "readonly='readonly'") ?> size="60" id="link_url" name="link_url"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_detailed_description_row">
                    <label for="detailed_description">Description (alt tag)</label>
                    <textarea id="detailed_description" <?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "" : "readonly='readonly'") ?> name="detailed_description" class="field-text"></textarea>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_country_id_row">
                    <label for="country_id">Country</label>
                    <select id="country_id" name="country_id" <?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "" : "disabled='disabled'") ?> class="field-text">
                        <option value="">[Select]</option>
						<?php
						foreach (getCountryArray() as $countryId => $countryName) {
							?>
                            <option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_date_created_row">
                    <label for="date_created">Date Taken</label>
                    <input type="text" size="12" class="field-text validate[custom[date]]" <?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "" : "readonly='readonly'") ?> id="date_created" name="date_created"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_security_level_id_row">
                    <label for="security_level_id">Security Level</label>
                    <select id="security_level_id" name="security_level_id" <?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "" : "disabled='disabled'") ?> class="field-text">
                        <option value="">[None]</option>
						<?php
						$resultSet = executeQuery("select * from security_levels where inactive = 0 order by sort_order,description");
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['security_level_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_user_group_id_row">
                    <label for="user_group_id">User Group</label>
                    <select id="user_group_id" name="user_group_id" <?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "" : "disabled='disabled'") ?> class="field-text">
                        <option value="">[None]</option>
						<?php
						$resultSet = executeQuery("select * from user_groups where inactive = 0 order by sort_order,description");
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['user_group_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_latitude_row">
                    <label for="latitude">Latitude</label>
                    <input type="text" class="field-text validate[custom[number]],min[-90],max[90]" <?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "" : "readonly='readonly'") ?> data-decimal-places="8" id="latitude" name="latitude"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_longitude_row">
                    <label for="longitude">Longitude</label>
                    <input type="text" class="field-text validate[custom[number]],min[-180],max[180]" <?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "" : "readonly='readonly'") ?> data-decimal-places="8" id="longitude" name="longitude"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_notes_row">
                    <label for="notes">Notes</label>
                    <textarea id="notes" <?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "" : "readonly='readonly'") ?> name="notes" class="field-text"></textarea>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_usage_log_row">
                    <label for="usage_log">Usage Log</label>
					<?php
					$imageUsageLogControl = new DataColumn("image_usage_log");
					$imageUsageLogControl->setControlValue("data_type", "custom");
					$imageUsageLogControl->setControlValue("control_class", "EditableList");
					$imageUsageLogControl->setControlValue("primary_table", "images");
					$imageUsageLogControl->setControlValue("list_table", "image_usage_log");
					$imageUsageLogControl->setControlValue("column_list", "log_date,content");
					$imageUsageLogControl->setControlValue("tabindex", "");
					$imageUsageLogControl->setControlValue("list_table_controls", "return array(\"user_id\"=>array(\"default_value\"=>\"" . $GLOBALS['gUserId'] . "\"))");
					if ($GLOBALS['gPermissionLevel'] <= _READONLY) {
						$imageUsageLogControl->setControlValue("readonly", "true");
					}
					?>
					<?= $imageUsageLogControl->getControl($this) ?>
                </div>
                <div class="basic-form-line" id="_maximum_dimension_row">
                    <label for="maximum_dimension">Maximum Dimension</label>
                    <input type="text" class="field-text validate[custom[integer]],min[50]] align-right" size="10" <?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "" : "readonly='readonly'") ?> id="maximum_dimension" name="maximum_dimension"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_convert_jpg_row">
                    <input type="checkbox" <?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "" : "readonly='readonly'") ?> id="convert_jpg" name="convert_jpg" value="1"/><label class="checkbox-label" for="convert_jpg">Convert to JPG (only when resizing, results in smaller image for same quality)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
				<?php
				$resultSet = executeQuery("select * from image_data_types where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$thisColumn = new DataColumn("image_data_type_id_" . $row['image_data_type_id']);
					$thisColumn->setControlValue("form_label", $row['description']);
					$thisColumn->setControlValue("tabindex", "");
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
                    <div class="basic-form-line" id="_<?= "image_data_type_id_" . $row['image_data_type_id'] ?>_row">
                        <label for="<?= "image_data_type_id_" . $row['image_data_type_id'] ?>"><?= ($row['data_type'] == "tinyint" ? "" : $thisColumn->getControlValue("form_label")) ?></label>
						<?= $thisColumn->getControl($this) ?>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
					<?php
				}
				?>
                <div class="basic-form-line" id="_clear_cache_row">
                    <input type="checkbox" id="clear_cache" name="clear_cache" checked='checked' value="1"/><label class="checkbox-label" for="clear_cache">Clear cache for this image</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

				<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                    <div class="basic-form-line" id="_file_content_file_row">
                        <label for="file_content_file">New Image</label>
                        <input type="file" id="file_content_file" name="file_content_file"/>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
				<?php } ?>

            </form>
        </div> <!-- edit_image_dialog -->

		<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
            <div id="_mass_edit_dialog" class="dialog-box">
            <form id="_mass_edit">
            <input type="hidden" id="mass_image_ids" name="mass_image_ids"/>
            <p id="mass_selected_images"></p>
            <div class="basic-form-line" id="_mass_album_id_row">
                <label for="mass_album_id">Move To Album</label>
                <select id="mass_album_id" name="mass_album_id" class="field-text">
                    <option value="">[Select]</option>
					<?php
					$resultSet = executeQuery("select * from albums where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['album_id'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

            <div class="basic-form-line" id="_mass_country_id_row">
                <label for="mass_country_id">Country</label>
                <select id="mass_country_id" name="mass_country_id" class="field-text">
                    <option value="">[Select]</option>
					<?php
					foreach (getCountryArray() as $countryId => $countryName) {
						?>
                        <option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
						<?php
					}
					?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_mass_date_created_row">
                <label for="mass_date_created">Date Taken</label>
                <input type="text" size="12" class="field-text validate[custom[date]]" id="mass_date_created" name="mass_date_created"/>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_mass_security_level_id_row">
                <label for="mass_security_level_id">Security Level</label>
                <select id="mass_security_level_id" name="mass_security_level_id" class="field-text">
                    <option value="">[None]</option>
					<?php
					$resultSet = executeQuery("select * from security_levels where inactive = 0 order by sort_order,description");
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['security_level_id'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_mass_user_group_id_row">
                <label for="mass_user_group_id">User Group</label>
                <select id="mass_user_group_id" name="mass_user_group_id" class="field-text">
                    <option value="">[None]</option>
					<?php
					$resultSet = executeQuery("select * from user_groups where inactive = 0 order by sort_order,description");
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['user_group_id'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_mass_maximum_dimension_row">
                <label for="mass_maximum_dimension">Maximum Dimension</label>
                <input type="text" size="10" class="field-text validate[custom[integer],min[100]] align-right" id="mass_maximum_dimension" name="mass_maximum_dimension"/>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line" id="_mass_convert_jpg_row">
                <input type="checkbox" <?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "" : "readonly='readonly'") ?> id="mass_convert_jpg" name="mass_convert_jpg" value="1"/><label class="checkbox-label" for="mass_convert_jpg">Convert to JPG (only when resizing, results in smaller image for same quality)</label>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
            <div class="basic-form-line custom-control-no-help custom-control-form-line" id="_mass_image_usage_log_row">
                <label for="mass_image_usage_log">Usage Log</label>
				<?php
				$imageUsageLogControl = new DataColumn("mass_image_usage_log");
				$imageUsageLogControl->setControlValue("data_type", "custom");
				$imageUsageLogControl->setControlValue("control_class", "EditableList");
				$imageUsageLogControl->setControlValue("primary_table", "images");
				$imageUsageLogControl->setControlValue("list_table", "image_usage_log");
				$imageUsageLogControl->setControlValue("column_list", "log_date,content");
				$imageUsageLogControl->setControlValue("tabindex", "");
				$imageUsageLogControl->setControlValue("list_table_controls", "return array(\"user_id\"=>array(\"default_value\"=>\"" . $GLOBALS['gUserId'] . "\"))");
				if ($GLOBALS['gPermissionLevel'] <= _READONLY) {
					$imageUsageLogControl->setControlValue("readonly", "true");
				}
				?>
				<?= $imageUsageLogControl->getControl($this) ?>
            </div>
			<?php
			$resultSet = executeQuery("select * from image_data_types where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$thisColumn = new DataColumn("mass_image_data_type_id_" . $row['image_data_type_id']);
				$thisColumn->setControlValue("form_label", $row['description']);
				$thisColumn->setControlValue("tabindex", "");
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
                <div class="basic-form-line" id="_<?= "mass_image_data_type_id_" . $row['image_data_type_id'] ?>_row">
                    <label for="<?= "mass_image_data_type_id_" . $row['image_data_type_id'] ?>"><?= ($row['data_type'] == "tinyint" ? "" : $thisColumn->getControlValue("form_label")) ?></label>
					<?= $thisColumn->getControl($this) ?>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
			<?php } ?>
			<?php
		}
		?>

        </form>
        </div> <!-- mass_edit_dialog -->

        <iframe id="_post_iframe" name="post_iframe"></iframe>

		<?php if ($GLOBALS['gPermissionLevel'] > _READWRITE) { ?>
            <div id="_delete_image_dialog" class="dialog-box">
                <div class="align-center">
                    <img id="dialog_image"/>
                </div>
                <p class="align-center" id="delete_image_text"></p>
            </div>

            <div id="_delete_all_dialog" class="dialog-box">
                <form id="_mass_delete">
                    <input type="hidden" id="mass_delete_image_ids" name="mass_delete_image_ids"/>
                    <p id="mass_delete_selected_images"></p>
                    <p class="align-center" id="delete_image_text">Are you sure you want to delete all selected images? This cannot be undone.</p>
                </form>
            </div>
		<?php } ?>

		<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
            <div id="_new_album_dialog" class="dialog-box">
                <form id="_new_album" enctype='multipart/form-data'>
                    <input type="hidden" id="album_image_ids" name="album_image_ids"/>
                    <p id="album_selected_images"></p>

                    <div class="basic-form-line" id="_album_description_row">
                        <label for="album_description" class="required-label">Album Name</label>
                        <input type="text" class="validate[required]" size="40" maxlength="255" id="album_description" name="album_description"/>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line" id="_album_detailed_description_row">
                        <label for="album_detailed_description">Description</label>
                        <textarea id="album_detailed_description" name="album_detailed_description"></textarea>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                </form>
            </div>
		<?php } ?>
		<?php
	}

	function jqueryTemplates() {
		$imageUsageLogControl = new DataColumn("image_usage_log");
		$imageUsageLogControl->setControlValue("data_type", "custom");
		$imageUsageLogControl->setControlValue("control_class", "EditableList");
		$imageUsageLogControl->setControlValue("primary_table", "images");
		$imageUsageLogControl->setControlValue("list_table", "image_usage_log");
		$imageUsageLogControl->setControlValue("column_list", "log_date,content");
		$imageUsageLogControl->setControlValue("tabindex", "");
		$imageUsageLogControl->setControlValue("list_table_controls", "return array(\"user_id\"=>array(\"default_value\"=>\"" . $GLOBALS['gUserId'] . "\"))");
		if ($GLOBALS['gPermissionLevel'] <= _READONLY) {
			$imageUsageLogControl->setControlValue("readonly", "true");
		}
		$customControl = new EditableList($imageUsageLogControl, $this);
		echo $customControl->getTemplate();

		$imageUsageLogControl = new DataColumn("mass_image_usage_log");
		$imageUsageLogControl->setControlValue("data_type", "custom");
		$imageUsageLogControl->setControlValue("control_class", "EditableList");
		$imageUsageLogControl->setControlValue("primary_table", "images");
		$imageUsageLogControl->setControlValue("list_table", "image_usage_log");
		$imageUsageLogControl->setControlValue("column_list", "log_date,content");
		$imageUsageLogControl->setControlValue("tabindex", "");
		$imageUsageLogControl->setControlValue("list_table_controls", "return array(\"user_id\"=>array(\"default_value\"=>\"" . $GLOBALS['gUserId'] . "\"))");
		if ($GLOBALS['gPermissionLevel'] <= _READONLY) {
			$imageUsageLogControl->setControlValue("readonly", "true");
		}
		$customControl = new EditableList($imageUsageLogControl, $this);
		echo $customControl->getTemplate();
		?>
        <div id="_image_block_template">

            <div class="image-section image-block" id="image_section_%image_id%" data-image_id="%image_id%">
                <div class="description">%description%</div>
                <div class="image-code">%image_code%</div>
                <div class="image-size">%image_size%</div>
                <div class="date-uploaded">%date_uploaded%</div>
                <div class="image"><a href="%image_url%" class="pretty-photo"><img %src%="%small_image_url%"></a></div>
                <input type="text" class="link-text">
                <div class="image-button-block">
					<?php if ($GLOBALS['gPermissionLevel'] > _READWRITE) { ?>
                        <span title="Delete Image" class="fa fa-trash"></span>
					<?php } ?>
                    <span title="<?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "Edit" : "View") ?> Image Details" class="fa fa-edit"></span>
                    <span title="Show Image Link" class="fa fa-link"></span>
                    <span title="Download Image" class="fa fa-download"></span>
                </div>
            </div>

        </div>

        <div id="_image_line_template">

            <div class="image-section image-line" id="image_section_%image_id%" data-image_id="%image_id%">
                <div class="image"><a href="%image_url%" class="pretty-photo"><img %src%="%thumbnail_image_url%"></a></div>
                <div class="image-code">%image_code%</div>
                <div class="description">%description%</div>
                <div class="image-size">%image_size%</div>
                <div class="date-uploaded">%date_uploaded%</div>
                <input type="text" class="link-text">
                <div class="image-button-block">
					<?php if ($GLOBALS['gPermissionLevel'] > _READWRITE) { ?>
                        <span title="Delete Image" class="fa fa-trash"></span>
					<?php } ?>
                    <span title="<?= ($GLOBALS['gPermissionLevel'] > _READONLY ? "Edit" : "View") ?> Image Details" class="fa fa-edit"></span>
                    <span title="Show Image Link" class="fa fa-link"></span>
                    <span title="Download Image" class="fa fa-download"></span>
                </div>
            </div>

        </div>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
