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

$GLOBALS['gPageCode'] = "PAGEPATTERNMAINTENANCE";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function massageDataSource() {
		$this->iDataSource->addColumnControl("page_id","get_choices","pageChoices");
	}

	function pageChoices($showInactive = false) {
		$pageChoices = array();
		$resultSet = executeQuery("select * from pages where client_id = ? and page_pattern_id is null order by description",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$pageChoices[$row['page_id']] = array("key_value"=>$row['page_id'],"description"=>$row['description'],"inactive"=>$row['inactive'] == 1);
			}
		}
		return $pageChoices;
	}

}

$pageObject = new ThisPage("page_patterns");
$pageObject->displayPage();
