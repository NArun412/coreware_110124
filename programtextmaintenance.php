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

$GLOBALS['gPageCode'] = "PROGRAMTEXTMAINT";
require_once "shared/startup.inc";

class ProgramTextMaintenancePage extends Page {

	function setup() {
		$resultSet = executeQuery("select * from program_text_groups where inactive = 0 order by description", $GLOBALS['gClientId']);
		$groups = array();
		while ($row = getNextRow($resultSet)) {
			$groups[$row['program_text_group_id']] = $row['description'];
		}
		$filters['product_department'] = array("form_label" => "Group", "where" => "program_text_group_id = %key_value%","data_type" => "select", "choices" => $groups);
		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
	}
}

$pageObject = new ProgramTextMaintenancePage("program_text");
$pageObject->displayPage();
