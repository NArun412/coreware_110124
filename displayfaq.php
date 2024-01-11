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

$GLOBALS['gPageCode'] = "DISPLAYFAQ";
require_once "shared/startup.inc";

class DisplayFaqPage extends Page {

	function mainContent() {
		echo $this->iPageData['content'];
		$resultSet = executeQuery("select * from knowledge_base where client_id = ? and knowledge_base_id in (select knowledge_base_id from " .
			"knowledge_base_category_links where knowledge_base_category_id in (select knowledge_base_category_id from knowledge_base_categories " .
			"where knowledge_base_category_code = 'FAQ')) order by sort_order,title_text", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			?>
            <h2><?= $row['title_text'] ?></h2>
			<?= makeHtml($row['content']) ?>
			<?php
		}
		echo $this->iPageData['after_form_content'];
		return true;
	}
}

$pageObject = new DisplayFaqPage();
$pageObject->displayPage();
