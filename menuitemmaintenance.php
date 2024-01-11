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

$GLOBALS['gPageCode'] = "MENUITEMMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function setup() {
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$filters = array();
			$filters['hide_core'] = array("form_label"=>"Hide Core Menu Items","where"=>"(page_id is null or page_id not in (select page_id from pages where client_id = 1)) and (menu_id is null or menu_id not in (select menu_id from menus where core_menu = 1))","data_type"=>"tinyint");
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
		$this->iDataSource->addColumnControl("page_id","include_default_client","true");
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("menu_contents"));
		$this->iDataSource->addColumnControl("link_title","data_type","varchar");
		$this->iDataSource->addColumnControl("link_title","css-width","500px");
		$this->iDataSource->addColumnControl("link_url","data_type","varchar");
		$this->iDataSource->addColumnControl("link_url","css-width","500px");
	}

	function menuChoices($showInactive = false) {
		$menuChoices = array();
		$resultSet = executeQuery("select * from menus where client_id = ? or (client_id = ? and menu_code not in (select menu_code from menus where client_id = ?)) order by description",
			$GLOBALS['gClientId'],$GLOBALS['gDefaultClientId'],$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$menuChoices[$row['menu_id']] = array("key_value"=>$row['menu_id'],"description"=>$row['description'],"inactive"=>$row['inactive'] == 1);
			}
		}
		return $menuChoices;
	}

	function afterSaveChanges($nameValues,$actionPerformed) {
		removeCachedData("menu_contents","*");
		removeCachedData("admin_menu","*");
		return true;
	}
}

$pageObject = new ThisPage("menu_items");
$pageObject->displayPage();
