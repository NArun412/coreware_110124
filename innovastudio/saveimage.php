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

$GLOBALS['gPageCode'] = "SAVEBASE64IMAGE";
$GLOBALS['gProxyPageCode'] = "BUILDCONTENT";
require_once "../shared/startup.inc";

header('Cache-Control: no-cache, must-revalidate');

//Read image
$count = $_REQUEST['count'];
$base64Content = $_REQUEST['hidimg-' . $count];
$imageName = $_REQUEST['hidname-' . $count];
$imageType = $_REQUEST['hidtype-' . $count];

if (empty($base64Content)) {
	echo "<html><body>No Content</body></html>";
} else {
	$imageContent = base64_decode($base64Content);
	$imageId = createImage(array("extension"=>$imageType,"file_content"=>$imageContent,"name"=>$imageName,"description"=>"Converted Base 64 Image"));
	echo "<html><body onload=\"parent.document.getElementById('img-" . $count . "').setAttribute('src','/getimage.php?id=" . $imageId . "');  parent.document.getElementById('img-" . $count . "').removeAttribute('id') \"></body></html>";
}
