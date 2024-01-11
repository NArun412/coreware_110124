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
	    ob_start();
		$this->iPageObject->internalPageCSS();
		$fullPageCSS = ob_get_clean();
		$fullPageCSS = $this->iPageObject->replaceImageReferences($fullPageCSS);
		echo $fullPageCSS;
	}

	function jqueryTemplates() {
		$this->iPageObject->jqueryTemplates();
	}

	function hiddenElements() {
		$this->iPageObject->hiddenElements();
	}

    function headerIncludes() {
        if (!$this->iPageObject->headerIncludes()) {
            $resultSet = executeQuery("select * from page_meta_tags where page_id = ?", $GLOBALS['gPageRow']['page_id']);
            $propertyArray = array();
            while ($row = getNextRow($resultSet)) {
                if (!empty($row['content'])) {
                    $propertyArray[] = $row['meta_value'];
                    ?>
                    <meta <?= $row['meta_name'] ?>="<?= $row['meta_value'] ?>" content="<?= str_replace("\"", "'", str_replace("\n", " ", $row['content'])) ?>"/>
                    <?php
                }
            }
        }
        echo $GLOBALS['gPageRow']['header_includes'];
    }

    function mainContent() {
		if (Page::pageIsUnderMaintenance()) {
			return;
		}
		if (!$this->iPageObject->mainContent()) {
			echo $this->iPageObject->getPageData("content");
			echo $this->iPageObject->getPageData("after_form_content");
		}
	}

	function displayPage() {
?>
<!DOCTYPE html>
<html lang="en">

<head>
<meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=0, maximum-scale=1">

<?php if (!empty($GLOBALS['gPageRow']['meta_keywords'])) { ?>
<meta name="Keywords" content="<?= str_replace("\"","'",$GLOBALS['gPageRow']['meta_keywords']) ?>" />
<?php } ?>
<?php if (!empty($GLOBALS['gPageRow']['meta_description'])) { ?>
<meta name="Description" content="<?= str_replace("\"","'",$GLOBALS['gPageRow']['meta_description']) ?>" />
<?php } ?>

<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />

<title><?= $this->getPageTitle() ?></title>

<link type="text/css" rel="stylesheet" property="stylesheet" href="<?= autoVersion('/css/reset.css') ?>" />
<link type="text/css" rel="stylesheet" property="stylesheet" href="<?= autoVersion('/css/perrow.css') ?>" />
<link type="text/css" rel="stylesheet" href="<?= autoVersion('/fontawesome-core/css/all.min.css') ?>" media="screen" />
<link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/jquery-ui.css') ?>"/>
<link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/validationEngine.jquery.css') ?>"/>

<script src="<?= autoVersion("/js/jquery-3.4.0.min.js") ?>"></script>
<script src="<?= autoVersion("/js/jquery-migrate-3.0.1.min.js") ?>"></script>

    <script src="<?= autoVersion('/js/jquery-ui.js') ?>"></script>
    <script src="<?= autoVersion('/js/jquery.validationEngine.js') ?>"></script>
    <script src="<?= autoVersion('/js/jquery.validationEngine-en.js') ?>"></script>
    <script src="<?= autoVersion('/js/general.js') ?>"></script>
    <script src="<?= autoVersion('/js/flowtype.js') ?>"></script>

<?php $this->headerIncludes() ?>

<?php $this->onLoadJavascript() ?>
<?php $this->javascript() ?>

<?= $this->getAnalyticsCode() ?>

<?php $this->internalCSS() ?>
</head>

<body>

<?php $this->mainContent() ?>

<div id="_templates">
<?php $this->jqueryTemplates() ?>
</div> <!-- templates -->

<?php $this->hiddenElements() ?>
</body>
</html><?php
	}
}
