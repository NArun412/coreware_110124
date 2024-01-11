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

$GLOBALS['gPageCode'] = "DOMAINNAMEMAINT";
require_once "shared/startup.inc";

class DomainNameMaintenancePage extends Page {

	function setup() {
		$this->iDataSource->addColumnControl("client_name", "select_value", "select coalesce(business_name,concat_ws(' ',first_name,last_name)) from contacts where contact_id = (select contact_id from clients where client_id = domain_names.domain_client_id)");
		$this->iDataSource->addColumnControl("client_name", "form_label", "Client");
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn("client_id");
			$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn("client_name");
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("domain_name", "client_name", "forward_domain_name", "page_id", "link_url", "inactive"));
		}
	}

	function massageDataSource() {
		if ($GLOBALS['gUserRow']['superuser_flag'] || ($GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId'] && $GLOBALS['gUserRow']['full_client_access'])) {
			$this->iDataSource->setFilterWhere("domain_client_id is null or domain_client_id = " . $GLOBALS['gClientId'] . " or (page_id is null and admin_page_id is null and user_page_id is null)");
		} else {
			$this->iDataSource->setFilterWhere("domain_client_id = " . $GLOBALS['gClientId']);
			$this->iDataSource->addColumnControl("domain_client_id", "default_value", $GLOBALS['gClientId']);
			$this->iDataSource->addColumnControl("domain_client_id", "readonly", true);
		}
        $this->iDataSource->addColumnControl("language_id", "empty_text", "[Default]");
        $this->iDataSource->addColumnControl("page_id", "form_label", "Public Page");

		$this->iDataSource->addColumnControl("domain_name_alternate_pages", "data_type", "custom");
		$this->iDataSource->addColumnControl("domain_name_alternate_pages", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("domain_name_alternate_pages", "list_table", "domain_name_alternate_pages");
		$this->iDataSource->addColumnControl("domain_name_alternate_pages", "list_table_controls", array("page_id"=>array("get_choices"=>"pageChoices")));
		$this->iDataSource->addColumnControl("domain_name_alternate_pages", "form_label", "Alternate pages for Public Page");
		$this->iDataSource->addColumnControl("domain_name_alternate_pages", "help_label", "System will make a random choice from the public page above and these when a user is not logged in");

		$this->iDataSource->addColumnControl("admin_page_id", "get_choices", "pageChoices");
		$this->iDataSource->addColumnControl("page_id", "get_choices", "pageChoices");
		$this->iDataSource->addColumnControl("user_page_id", "get_choices", "pageChoices");
		$this->iDataSource->addColumnControl("domain_client_id", "form_label", "Client");
		$this->iDataSource->addColumnControl("domain_client_id", "get_choices", "clientChoices");
		$this->iDataSource->addColumnControl("domain_name", "help_label", "don't include www");
		$this->iDataSource->addColumnControl("link_url", "css_width", "500px");
		$this->iDataSource->addColumnControl("link_url", "data_type", "varchar");
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		removeCachedData("domain_name_row", "*",true);
		return true;
	}
}

$pageObject = new DomainNameMaintenancePage("domain_names");
$pageObject->displayPage();
