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

$GLOBALS['gPageCode'] = "RESPONSIBLEUSERMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("user_name","first_name","last_name","email_address","inactive"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add","delete"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->setSaveOnlyPresent(true);
		$this->iDataSource->setJoinTable("contacts","contact_id","contact_id");
		if (!$GLOBALS['gUserRow']['superuser_flag']) {
			$this->iDataSource->setFilterWhere("superuser_flag = 0");
		}
		$this->iDataSource->addColumnControl("user_name","readonly","true");
		$this->iDataSource->addColumnControl("first_name","readonly","true");
		$this->iDataSource->addColumnControl("last_name","readonly","true");
		$this->iDataSource->addColumnControl("email_address","readonly","true");

		$this->iDataSource->addColumnControl("designation_group_users","data_type","custom");
		$this->iDataSource->addColumnControl("designation_group_users","control_class","MultipleSelect");
		$this->iDataSource->addColumnControl("designation_group_users","links_table","designation_group_users");
		$this->iDataSource->addColumnControl("designation_group_users","control_table","designation_groups");
		$this->iDataSource->addColumnControl("designation_group_users","form_label","Designation Groups");

		$this->iDataSource->addColumnControl("designation_users","data_type","custom");
		$this->iDataSource->addColumnControl("designation_users","control_class","MultipleSelect");
		$this->iDataSource->addColumnControl("designation_users","links_table","designation_users");
		$this->iDataSource->addColumnControl("designation_users","control_table","designations");
		$this->iDataSource->addColumnControl("designation_users","form_label","Designations");
	}
}

$pageObject = new ThisPage("users");
$pageObject->displayPage();
