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

$GLOBALS['gPageCode'] = "CREATECSSFILES";
require_once "shared/startup.inc";

if ($GLOBALS['gCommandLine']) {
	createAllCssFiles();
	exit;
}

function createAllCssFiles() {
	$resultSet = executeQuery("select * from css_files");
	while ($row = getNextRow($resultSet)) {
		echo "Creating '" . $row['description'] . "'<br>\n";
		createCSSFile($row['css_file_id'], true);
	}
	echo "Done\n";
}

class CreateCssFilesPage extends Page {

	function mainContent() {
		createAllCssFiles();
		return true;
	}

}

$pageObject = new CreateCssFilesPage();
$pageObject->displayPage();
