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

$GLOBALS['gPageCode'] = "URLALIASTYPEMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {
	
	function massageDataSource() {
		$this->iDataSource->addColumnControl("table_id","get_choices","tableChoices");
	}
	
	function tableChoices($showInactive = false) {
		$tableChoices = array();
		$resultSet = executeQuery("select table_id,description from tables where table_id in (select table_id from table_columns where " .
			"column_definition_id = (select column_definition_id from column_definitions where column_name = 'link_name')) and table_name not in ('pages','url_alias','page_aliases') order by description");
		while ($row = getNextRow($resultSet)) {
			$tableChoices[$row['table_id']] = array("key_value"=>$row['table_id'],"description"=>$row['description'],"inactive"=>false);
		}
		freeResult($resultSet);
		return $tableChoices;
	}
	
}

$pageObject = new ThisPage("url_alias_types");
$pageObject->displayPage();
