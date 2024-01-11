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

$GLOBALS['gPageCode'] = "WEBUSERLOG";
require_once "shared/startup.inc";

class WebUserLogPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setReadonly(true);
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("web_user_id","first_name","last_name","email_address","start_date"));
			$filters = array();
			$filters['with_contacts'] = array("form_label"=>"Has Contact","where"=>"web_users.contact_id is not null","data_type"=>"tinyint");
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("web_user_pages","control_class","EditableList");
		$this->iDataSource->addColumnControl("web_user_pages","data_type","custom");
		$this->iDataSource->addColumnControl("web_user_pages","list_table","web_user_pages");
		$this->iDataSource->addColumnControl("web_user_pages","form_label","Page Access");
		$this->iDataSource->setJoinTable("contacts","contact_id","contact_id",$outerJoin=true);
	}
}

$pageObject = new WebUserLogPage("web_users");
$pageObject->displayPage();
