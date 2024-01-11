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
			echo $this->iPageObject->getPageData("content");
		}
	}

	function headerIncludes() {
		if (!$this->iPageObject->headerIncludes()) {
			$resultSet = executeQuery("select * from page_meta_tags where page_id = ?",$GLOBALS['gPageRow']['page_id']);
			$propertyArray = array();
			while ($row = getNextRow($resultSet)) {
				if (!empty($row['content'])) {
					$propertyArray[] = $row['meta_value'];
?>
<meta <?= $row['meta_name'] ?>="<?= $row['meta_value'] ?>" content="<?= str_replace("\"","'",str_replace("\n"," ",$row['content'])) ?>" />
<?php
				}
			}
		}
	}

	function displayPage() {
?>
<!DOCTYPE html>
<html>
<head>

<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="Pragma" content="no-cache" />

<?= $this->iPageObject->getPageData("header_includes") ?>

<title><?= $this->getPageTitle() ?></title>

<link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/reset.css') ?>"/>
<link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/perrow.css') ?>"/>
<link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/jquery-ui.css') ?>"/>
<link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/table_editor.css') ?>"/>
<link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/validationEngine.jquery.css') ?>"/>
<link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/prettyPhoto.css') ?>" media="screen" />
<link type="text/css" rel="stylesheet" href="<?= autoVersion('/fontawesome-core/css/all.min.css') ?>" media="screen" />

<script src="<?= autoVersion("/js/jquery-3.4.0.min.js") ?>"></script>
<script src="<?= autoVersion("/js/jquery-migrate-3.0.1.min.js") ?>"></script>

<script src="<?= autoVersion('/js/json3.js') ?>"></script>
<script src="<?= autoVersion('/js/jquery-ui.js') ?>"></script>
<script src="<?= autoVersion('/js/jquery.validationEngine-en.js') ?>"></script>
<script src="<?= autoVersion('/js/jquery.validationEngine.js') ?>"></script>
<script src="<?= autoVersion('/js/jquery.prettyPhoto.js') ?>"></script>
<script src="<?= autoVersion('/js/general.js') ?>"></script>
<script src="<?= autoVersion('/js/admin.js') ?>"></script>
<script src="<?= autoVersion('/js/editablelist.js') ?>"></script>
<script src="<?= autoVersion('/js/multipleselect.js') ?>"></script>
<script src="<?= autoVersion('/js/multipledropdown.js') ?>"></script>
<script src="<?= autoVersion('/js/formlist.js') ?>"></script>
<?php $this->headerIncludes() ?>

<style type='text/css'>
#_outer { width: 1000px; margin: 40px auto; }
</style>

<?php $this->onLoadJavascript() ?>
<?php $this->javascript() ?>
<?php $this->internalCSS() ?>

<?= $this->getAnalyticsCode() ?>
</head>
<body>
<div id="_outer">
<?php
	$this->mainContent();
?>
</div>
<?php $this->hiddenElements() ?>
<div id="_templates">
<?php $this->jqueryTemplates() ?>
</div>

</body>
</html>
<?php
	}
}
