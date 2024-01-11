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

$GLOBALS['gPageCode'] = "CLIENTPAGETEMPLATEMAINT";
require_once "shared/startup.inc";

class ClientPageTemplateMaintenancePage extends Page {

	function setup() {
		setUserPreference("MAINTENANCE_SAVE_NO_LIST", "true", $GLOBALS['gPageRow']['page_code']);
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$columnList = array("template_id", "client_page_templates");
			$this->iTemplateObject->getTableEditorObject()->addIncludeFormColumn($columnList);
			$this->iTemplateObject->getTableEditorObject()->setFormSortOrder($columnList);
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "delete", "list"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->setFilterWhere("client_id = " . $GLOBALS['gUserRow']['client_id']);
		$this->iDataSource->setSaveOnlyPresent(true);
		$this->iDataSource->addColumnControl("template_id", "get_choices", "templateChoices");
		$this->iDataSource->addColumnControl("template_id", "form_label", "Template for all Core Pages");
		$this->iDataSource->addColumnControl("template_id", "empty_text", "[Use Default]");
		$this->iDataSource->addColumnControl("client_page_templates", "data_type", "custom");
		$this->iDataSource->addColumnControl("client_page_templates", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("client_page_templates", "list_table", "client_page_templates");
		$this->iDataSource->addColumnControl("client_page_templates", "form_label", "Individual Pages");
		$this->iDataSource->addColumnControl("client_page_templates", "list_table_controls", "return array('template_id'=>array('get_choices'=>'templateChoices'),'page_id'=>array('get_choices'=>'pageChoices'))");
	}

	function pageChoices($showInactive = false) {
		$pageChoices = array();
		$resultSet = executeQuery("select *,(select description from subsystems where subsystem_id = pages.subsystem_id) subsystem from pages where client_id = ? and " .
			"template_id = (select template_id from templates where client_id = ? and template_code = 'MANAGEMENT') order by subsystem,description", $GLOBALS['gDefaultClientId'], $GLOBALS['gDefaultClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$pageChoices[$row['page_id']] = array("key_value" => $row['page_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1, "optgroup" => $row['subsystem']);
			}
		}
		return $pageChoices;
	}

	function templateChoices($showInactive = false) {
		$templateChoices = array();
		$resultSet = executeQuery("select * from templates where (client_id = ? or client_id = ?) and include_crud = 1",
			$GLOBALS['gDefaultClientId'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$templateChoices[$row['template_id']] = array("key_value" => $row['template_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1);
			}
		}
		return $templateChoices;
	}

	function massageUrlParameters() {
		$_GET['url_subpage'] = $_GET['url_page'];
		$_GET['url_page'] = "show";
		$_GET['primary_id'] = $GLOBALS['gUserRow']['client_id'];
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		removeCachedData("client_row", $GLOBALS['gClientId'], true);
		removeCachedData("client_name", $GLOBALS['gClientId'], true);
		removeCachedData("client_page_templates","client_page_templates");
		return true;
	}

}

$pageObject = new ClientPageTemplateMaintenancePage("clients");
$pageObject->displayPage();
