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

$GLOBALS['gPageCode'] = "FILEMAINT";
require_once "shared/startup.inc";

class FileMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn(array("detailed_description"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn(array("file_size"));
			$this->iTemplateObject->getTableEditorObject()->addExcludeSearchColumn(array("file_content", "file_size"));
		}
		$resultSet = executeQuery("select file_id,os_filename,length(file_content) file_content_length from files where client_id = ? and file_size = 0", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['os_filename'])) {
				$fileSize = $row['file_content_length'];
			} else if (file_exists($row['os_filename'])) {
				$fileContents = getExternalFileContents($row['os_filename']);
				$fileSize = strlen($fileContents);
			} else {
				continue;
			}
			if (!empty($fileSize)) {
				executeQuery("update files set file_size = ? where file_id = ?", $fileSize, $row['file_id']);
			}
		}
	}

	function saveChanges() {
		$returnArray = array();
		$table = new DataTable("files");
		if (array_key_exists("file_content_file", $_FILES) && !empty($_FILES['file_content_file']['name'])) {
			$_POST['filename'] = $_FILES['file_content_file']['name'];
			if (array_key_exists($_FILES['file_content_file']['type'], $GLOBALS['gMimeTypes'])) {
				$_POST['extension'] = $GLOBALS['gMimeTypes'][$_FILES['file_content_file']['type']];
			} else {
				$fileNameParts = explode(".", $_FILES['file_content_file']['name']);
				$_POST['extension'] = $fileNameParts[count($fileNameParts) - 1];
			}
			$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
			if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
				$maxDBSize = 1000000;
			}
			if ($_FILES['file_content_file']['size'] < $maxDBSize) {
				$_POST['file_content'] = file_get_contents($_FILES['file_content_file']['tmp_name']);
				$_POST['os_filename'] = "";
			} else {
				$_POST['file_content'] = "";
				$_POST['os_filename'] = "/documents/tmp." . $_POST['extension'];
			}
		} else {
			$table->addExcludeUpdateColumns(array("os_filename", "file_content", "extension"));
		}
		$table->setPrimaryId($_POST['primary_id']);
		$primaryId = $table->saveRecord(array("name_values" => $_POST));
		if (empty($_POST['file_code'])) {
			executeQuery("update files set file_code = ? where file_id = ?", "FILE" . $primaryId, $primaryId);
		}
		if (!$primaryId) {
			$returnArray['error_message'] = $table->getErrorMessage();
		} else if (!empty($_POST['os_filename']) && array_key_exists("file_content_file", $_FILES) && !empty($_FILES['file_content_file']['name'])) {
			putExternalFileContents($primaryId, $_POST['extension'], file_get_contents($_FILES['file_content_file']['tmp_name']));
		}
		ajaxResponse($returnArray);
	}
}

$pageObject = new FileMaintenancePage("files");
$pageObject->displayPage();
