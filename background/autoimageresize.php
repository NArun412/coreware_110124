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

$GLOBALS['gPageCode'] = "BACKGROUNDPROCESS";
$runEnvironment = php_sapi_name();
if ($runEnvironment == "cli") {
	require_once "shared/startup.inc";
} else {
	require_once "../shared/startup.inc";
}

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "auto_image_resize";
	}

	function process() {

		$clientSet = executeQuery("select * from clients");
		while ($clientRow = getNextRow($clientSet)) {
			changeClient($clientRow['client_id']);

			$imageDataTypeId = getFieldFromId("image_data_type_id","image_data_types","image_data_type_code","DO_NOT_AUTO_RESIZE");
			if (empty($imageDataTypeId)) {
				$resultSet = executeQuery("insert into image_data_types (client_id,image_data_type_code,description,data_type) values (?,'DO_NOT_AUTO_RESIZE','Do Not Auto Resize','tinyint')",$GLOBALS['gClientId']);
				$imageDataTypeId = $resultSet['insert_id'];
			}

			$maxImageSize = getPreference("MAXIMUM_SIZE_IMAGE_RESIZE");
			$maxImageDimension = getPreference("MAXIMUM_DIMENSION_IMAGE_RESIZE");
			$convertToJPG = getPreference("AUTO_RESIZE_CONVERT_JPG");
			$convertAllProductImages = getPreference("RETAIL_STORE_CONVERT_PRODUCT_IMAGES");
			$compression = getPreference("AUTO_RESIZE_COMPRESSION");
			if (empty($maxImageSize) || empty($maxImageDimension)) {
				continue;
			}
			$this->addResult("Processing Client '" . $clientRow['client_code'] . "': Max image size: " . $maxImageSize . ", Max Dimension: " . $maxImageDimension);

			$resultSet = executeQuery("select * from images where (client_id = ? and image_size > ? and (extension is null or extension in ('jpg','png','jpeg')) and " .
				"image_id not in (select image_id from image_data where image_data_type_id = ? and text_data = '1')) " . (empty($convertAllProductImages) ? "" : "or " .
				"((image_id in (select image_id from products where image_id is not null) or image_id in (select image_id from product_images)) and client_id = " . $GLOBALS['gClientId'] .
				" and extension not in ('jpg','jpeg')) ") .
				"order by image_size desc limit 5000",$GLOBALS['gClientId'],$maxImageSize,$imageDataTypeId);
			$count = 0;
			$totalSavings = 0;
			while ($row = getNextRow($resultSet)) {
				$convertThisImage = (!empty($convertToJPG));
				if (!$convertThisImage && !empty($convertAllProductImages)) {
					$productImageId = getFieldFromId("image_id","products","image_id",$row['image_id']);
					if (empty($productImageId)) {
						$productImageId = getFieldFromId("image_id","product_images","image_id",$row['image_id']);
					}
					if (!empty($productImageId)) {
						$convertThisImage = true;
					}
				}

				$thisSavings = SimpleImage::reduceImageSize($row['image_id'],array("image_row"=>$row,"compression"=>$compression,"max_image_dimension"=>$maxImageDimension,"convert"=>$convertToJPG));
				if ($thisSavings) {
					$totalSavings += $thisSavings;
					$count++;
				}
			}
			$this->addResult($count . " images resized for a savings of " . number_format($totalSavings,0,"",","));
		}
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
