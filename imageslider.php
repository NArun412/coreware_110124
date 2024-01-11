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

$GLOBALS['gPageCode'] = "IMAGESLIDER";
require_once "shared/startup.inc";

if (empty($_GET['album_id']) && !empty($_GET['code'])) {
	$_GET['album_id'] = getFieldFromId("album_id","albums","album_code",strtoupper($_GET['code']));
}

$imageArray = array();
$resultSet = executeQuery("select * from images,album_images where images.image_id = album_images.image_id and " .
	"album_id = ? and client_id = ? order by sequence_number",$_GET['album_id'],$GLOBALS['gClientId']);
while ($row = getNextRow($resultSet)) {
	$imageArray[] = array("image_id"=>$row['image_id'],"title"=>$row['description'],"description"=>(empty($row['detailed_description']) ? "" : $row['detailed_description']),"url"=>getImageFilename($row['image_id'],array("use_cdn"=>true)),"link_url"=>$row['link_url']);
}

$returnArray['image_array'] = $imageArray;
$returnArray['album_id'] = $_GET['album_id'];
echo jsonEncode($returnArray);
