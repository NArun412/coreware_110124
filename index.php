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

$GLOBALS['gPageCode'] = "INDEX";
require_once "shared/startup.inc";

if (empty($GLOBALS['gPageRow']['template_id'])) {
	$templateId = getFieldFromId("template_id", "templates", "template_code", "MANAGEMENT", "client_id = " . $GLOBALS['gDefaultClientId']);
	if (empty($templateId)) {
		include_once "templates/class.admintemplate.php";
	} else {
		$GLOBALS['gPageTemplateId'] = $templateId;
		$GLOBALS['gPageRow']['template_id'] = $templateId;
		$GLOBALS['gTemplateRow'] = getCachedData("template_row", $templateId, true);
		if ($GLOBALS['gTemplateRow'] === false) {
			$GLOBALS['gTemplateRow'] = getRowFromId("templates", "template_id", $templateId, "client_id = ? or client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
			setCachedData("template_row", $templateId, $GLOBALS['gTemplateRow'], 24, true);
		}
		if (is_array($GLOBALS['gPageRow']['page_text_chunks'])) {
			foreach ($GLOBALS['gPageRow']['page_text_chunks'] as $pageTextChunkCode => $pageTextChunkContent) {
				foreach ($GLOBALS['gTemplateRow'] as $fieldName => $fieldData) {
					$GLOBALS['gTemplateRow'][$fieldName] = str_replace("%" . strtolower($pageTextChunkCode) . "%", $pageTextChunkContent, $fieldData);
				}
			}
		}
		include_once "templates/class.administration.php";
	}
}

class ThisPage extends Page {

	function setup() {
		$GLOBALS['gPageRow']['description'] = "";
	}

	function mainContent() {
		$displayTip = false;
		$userIndexText = "";
		if ($GLOBALS['gLoggedIn']) {
			$userIndexText = getPreference("USER_INDEX_TEXT");
			if (empty($userIndexText)) {
				$userIndexText = "<h5>Administrator dashboard for</h5>";
				$userIndexText .= "<h1>" . $GLOBALS['gClientName'] . "</h1>";
				$displayTip = true;
			}
		} else {
			$userIndexText = getPreference("INDEX_TEXT");
			if (empty($userIndexText)) {
				if ($GLOBALS['gClientId'] != $GLOBALS['gDefaultClientId']) {
					$userIndexText = "<h1>" . $GLOBALS['gClientName'] . "</h1>";
				}
				$userIndexText .= "<p><a id='_login_link' href='/loginform.php'>Click here to login</a></p><script>document.location = '/loginform.php';</script>";
			}
		}
		if ($GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId'] && $GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gLocalExecution']) {
			$domainNameId = getFieldFromId("domain_name_id", "domain_names", "domain_name", $_SERVER['HTTP_HOST']);
			if (empty($domainNameId)) {
				$domainNameId = getFieldFromId("domain_name_id", "domain_names", "domain_name", str_replace("www.", "", $_SERVER['HTTP_HOST']), "include_www = 1");
			}
			if (empty($domainNameId)) {
				$userIndexText = "<span class='error-message'><strong style='font-size: 24px'>WARNING: You are a SUPERUSER. You've accessed this site with a domain name that is not pointed to a specific client. " . $_SERVER['HTTP_HOST'] . " does not exist on this server. You are on the Management Client.</strong></span>" . $userIndexText;
			}
		}
		echo $userIndexText;

		if (canAccessPageCode("SYSTEMNOTICES")) {
			$systemNotices = new SystemNotices();
			$count = $systemNotices->getCount();
			if ($count > 0) {
				?>
                <p id="system_notices" class="highlighted-text red-text">There are important System Notices. Click <a href="/systemnotices.php">here</a> to read them.</p>
				<?php
			}
		}

		if ($displayTip) {
			if ($GLOBALS['gPrimaryDatabase']->tableExists("announcements")) {
				$resultSet = executeQuery("select * from announcements where inactive = 0 and (end_date is null or end_date > current_date) order by sort_order,announcement_id");
				while ($row = getNextRow($resultSet)) {
					?>
                    <h2><?= $row['description'] ?></h2>
                    <p><?= makeHtml($row['detailed_description']) ?></p>
					<?php
				}
			}
			if (canAccessPageCode("COREWARECHANGELOG")) {
				$resultSet = executeQuery("select * from knowledge_base where inactive = 0 and date_entered >= date_sub(current_date,interval 7 day) and knowledge_base_id in (" .
					"select knowledge_base_id from knowledge_base_category_links where knowledge_base_category_id = (select knowledge_base_category_id from knowledge_base_categories " .
					"where client_id = ? and knowledge_base_category_code = 'COREWARE_CHANGE_LOG')) order by date_entered desc,knowledge_base_id desc", $GLOBALS['gDefaultClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
                    <div class="knowledge-base-entry">
                        <h2>Coreware Change: <?= $row['title_text'] ?></h2>
                        <p><?= makeHtml($row['content']) ?></p>
                    </div>
					<?php
				}
			}
			$resultSet = executeQuery("select * from tips where internal_use_only = 0 and inactive = 0 order by rand() limit 1");
			if ($row = getNextRow($resultSet)) {
				?>
                <div id="_random_tip">
                    <h3>Did you know?</h3>
					<?php
					echo makeHtml($row['content']);
					?>
                </div>
				<?php
			}
		}
	}

	function internalCSS() {
		?>
        #_main_content h1 { margin-bottom: 60px; }
        #_main_content ul { list-style: disc; margin: 20px 40px; }
        #_main_content ol { list-style: decimal; margin: 20px 40px; }
        #_main_content ul ol,#_main_content ul ul,#_main_content ol ul,#_main_content ol ol { margin-bottom: 0; }
        #_main_content li { padding-bottom: 10px; }
        #_main_content ul li a { font-size: 1.2rem; }
        #_random_tip { margin-top: 40px; }
        #_random_tip p { font-size: 1.2rem; width: 100%; max-width: 600px; }
        div.knowledge-base-entry { margin-bottom: 40px; }
        #system_notices { margin-bottom: 40px; }
		<?php
		echo processCssContent(getSassHeaders() . $GLOBALS['gPageRow']['css_content']);
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
