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

$GLOBALS['gPageCode'] = "ADDIMAGE";
require_once "shared/startup.inc";

$dataSource = new DataSource("images");
$_POST['date_uploaded'] = date("m/d/Y");
if (!array_key_exists("description", $_POST)) {
	$_POST['description'] = $_POST['image_picker_new_image_description'];
}
if (!array_key_exists("image_code", $_POST)) {
	$imageCode = makeCode($_POST['description']);
	$imageCodeNumber = 0;
	do {
		$imageId = getFieldFromId("image_id", "images", "image_code", $imageCode . (empty($imageCodeNumber) ? "" : "_" . $imageCodeNumber));
		$imageCodeNumber++;
	} while (!empty($imageId));
	$imageCode .= (empty($imageCodeNumber) ? "" : "_" . $imageCodeNumber);
	$_POST['image_code'] = $imageCode;
}
if (!array_key_exists("file_content_file", $_POST)) {
	$_POST['file_content_file'] = $_POST['image_picker_file_content_file'];
	$_FILES['file_content_file'] = $_FILES['image_picker_file_content_file'];
	$_POST['image_size'] = $_FILES['image_picker_file_content_file']['size'];
}
$imageId = $dataSource->saveRecord(array("name_values" => $_POST, "primary_id" => ""));
if (!$imageId) {
	$returnArray['error_message'] = $dataSource->getErrorMessage();
} else {
	$returnArray['image'] = array("description" => $_POST['description'],
		"image_id" => $imageId, "url" => getImageFilename($imageId));
}
echo jsonEncode($returnArray);
