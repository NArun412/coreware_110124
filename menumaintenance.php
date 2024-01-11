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

$GLOBALS['gPageCode'] = "MENUMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			if ($GLOBALS['gUserRow']['superuser_flag']) {
				$filters = array();
				$filters['hide_core'] = array("form_label"=>"Hide Core","where"=>"core_menu = 0","data_type"=>"tinyint");
				$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			} else {
				$this->iTemplateObject->getTableEditorObject()->addExcludeColumn(array("core_menu"));
			}
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("menu_contents","menu_items"));
		if (!$GLOBALS['gUserRow']['superuser_flag']) {
			$this->iDataSource->setFilterWhere("core_menu = 0");
		}
	}

	function menuItemChoices($showInactive = false, $lite = false) {
		$menuItemChoices = [];

		$queryParts = [];
		$queryParts[] = "SELECT menu_item_id, link_title, description FROM menu_items WHERE client_id = ?";

		if ($lite) {
			$queryParts[] = "AND (page_id IS NULL OR page_id NOT IN (SELECT page_id FROM pages WHERE client_id = 1))";
			$queryParts[] = "AND (menu_id IS NULL OR menu_id NOT IN (SELECT menu_id FROM menus WHERE core_menu = 1))";
		}

		$queryParts[] = "ORDER BY sort_order, link_title, description";

		$query = implode(" ", $queryParts);

		$resultSet = executeQuery($query, $GLOBALS['gClientId']);

		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$description = empty($row['link_title']) || isHtml($row['link_title']) ?
					$row['description'] :
					"{$row['link_title']} - {$row['description']}";

				$menuItemChoices[$row['menu_item_id']] = [
					"key_value"    => $row['menu_item_id'],
					"description"  => $description,
					"inactive"     => $row['inactive'] == 1
				];
			}
		}
		return $menuItemChoices;
	}

	function afterSaveChanges($nameValues,$actionPerformed) {
		removeCachedData("menu_contents","*");
		removeCachedData("admin_menu","*");
		return true;
	}
}

$pageObject = new ThisPage("menus");
$pageObject->displayPage();
