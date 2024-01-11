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
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn(array("list_item_identifier","list_item_classes","query_string","subsystem_id","image_id","content","display_color","separate_window"));
			if (empty($GLOBALS['gUserRow']['company_id'])) {
				$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete", "add"));
			} else {
				$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));
			}
		}
	}

	function massageDataSource() {
		$this->iDataSource->setSaveOnlyPresent(true);
		$this->iDataSource->addColumnControl("page_id","get_choices","pageChoices");
		$this->iDataSource->addColumnControl("page_id","not_null",true);
		$this->iDataSource->addColumnControl("link_title","data_type","varchar");
		$this->iDataSource->addColumnControl("link_title","css-width","500px");
		$this->iDataSource->addColumnControl("link_url","data_type","varchar");
		$this->iDataSource->addColumnControl("link_url","css-width","500px");
		$this->iDataSource->addColumnControl("menu_id","get_choices","menuChoices");
		$this->iDataSource->getPrimaryTable()->setSubtables(array("menu_contents"));
		$this->iDataSource->setFilterWhere("page_id in (select page_id from pages where client_id = " . $GLOBALS['gClientId'] .
			") and (menu_id is null or menu_id in (select menu_id from menus where client_id = " . $GLOBALS['gClientId'] . " and core_menu = 0))");
	}

	function menuChoices($showInactive = false) {
		$menuChoices = array();
		$resultSet = executeQuery("select * from menus where client_id = ? and core_menu = 0 order by description",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$menuChoices[$row['menu_id']] = array("key_value"=>$row['menu_id'],"description"=>$row['description'],"inactive"=>$row['inactive'] == 1);
			}
		}
		return $menuChoices;
	}

	function pageChoices($showInactive = false) {
		$pageChoices = array();
		$resultSet = executeQuery("select * from pages where client_id = ? order by description",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$pageChoices[$row['page_id']] = array("key_value"=>$row['page_id'],"description"=>$row['description'],"inactive"=>$row['inactive'] == 1);
			}
		}
		return $pageChoices;
	}

	function afterSaveChanges($nameValues,$actionPerformed) {
		removeCachedData("menu_contents","*");
		removeCachedData("admin_menu","*");
		return true;
	}
}

$pageObject = new ThisPage("menu_items");
$pageObject->displayPage();
