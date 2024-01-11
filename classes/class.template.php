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

class Template extends AbstractTemplate {
	private $iErrorMessage = "";

	function setup() {
		$this->iPageObject->setDatabase($GLOBALS['gPrimaryDatabase']);
		$this->iPageObject->setup();
	}

	function executeUrlActions() {
	}

	function onLoadJavascript() {
		$this->iPageObject->onLoadPageJavascript();
	}

	function javascript() {
		$this->iPageObject->pageJavascript();
	}

	function internalCSS() {
		$this->iPageObject->internalPageCSS();
	}

	function jqueryTemplates() {
		$this->iPageObject->jqueryTemplates();
	}

	function hiddenElements() {
		$this->iPageObject->hiddenElements();
	}

	function mainContent() {
		if (Page::pageIsUnderMaintenance()) {
			return;
		}
		if (!$this->iPageObject->mainContent()) {
			echo $this->iPageObject->getPageData("full_content");
		}
	}

	function displayPage() {
?>
<!DOCTYPE html>
<html>
<body>
<h1>Default Template</h1>
<?php
		$this->mainContent();
?>
</body>
</html>
<?php
	}

}
