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

$GLOBALS['gPageCode'] = "CLEARIMAGECACHE";
require_once "shared/startup.inc";

class ClearImageCachePage extends Page {

	function mainContent() {
		return true;
		$fileList = scandir($GLOBALS['gDocumentRoot'] . "/cache");
		foreach ($fileList as $filename) {
			if (substr($filename,0,strlen("image-")) != "image-") {
				continue;
			}
			if (file_exists($GLOBALS['gDocumentRoot'] . "/cache/" . $filename)) {
                unlink($GLOBALS['gDocumentRoot'] . "/cache/" . $filename);
            }
		}
		executeQuery("update images set hash_code = null");
		removeCachedData("img_filenames","*");
		echo "Image cache cleared";
		return true;
	}
}

$pageObject = new ClearImageCachePage();
$pageObject->displayPage();
