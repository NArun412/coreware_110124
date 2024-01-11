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

$GLOBALS['gPageCode'] = "USERGROUPMAINT";
require_once "shared/startup.inc";

class UserGroupMaintenancePage extends Page {

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("user_group_members","user_group_access"));
		if (empty($GLOBALS['gUserRow']['superuser_flag']) && empty($GLOBALS['gUserRow']['full_client_access'])) {
			$this->iDataSource->setFilterWhere("restricted_access = 0 or (user_id is not null and user_id = " . $GLOBALS['gUserId'] . ")");
		}
		$count = 0;
		$resultSet = executeQuery("select count(*) from users where client_id = ?",$GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$count = $row['count(*)'];
		}
		if ($count < 200) {
			$this->iDataSource->addColumnControl("user_group_members","data_type","custom");
			$this->iDataSource->addColumnControl("user_group_members","form_label","Members");
			$this->iDataSource->addColumnControl("user_group_members","control_class","EditableList");
			$this->iDataSource->addColumnControl("user_group_members","list_table","user_group_members");
		}
		$this->iDataSource->addColumnControl("user_id","data_type","user_picker");

		$this->iDataSource->addColumnControl("user_group_subsystem_access","data_type","custom");
		$this->iDataSource->addColumnControl("user_group_subsystem_access","control_class","EditableList");
		$this->iDataSource->addColumnControl("user_group_subsystem_access","list_table","user_group_subsystem_access");
		$this->iDataSource->addColumnControl("user_group_subsystem_access","form_label","Subsystem Access");
		$this->iDataSource->addColumnControl("user_group_subsystem_access","list_table_controls",array("page_id"=>array("get_choices"=>"pageChoices"),"permission_level"=>array("data_type"=>"select","choices"=>array(""=>"[None]","0"=>"No Access","1"=>"Read Only","2"=>"Write","3"=>"All"))));

		$this->iDataSource->addColumnControl("user_group_access","data_type","custom");
		$this->iDataSource->addColumnControl("user_group_access","control_class","EditableList");
		$this->iDataSource->addColumnControl("user_group_access","list_table","user_group_access");
		$this->iDataSource->addColumnControl("user_group_access","form_label","Access");
		$this->iDataSource->addColumnControl("user_group_access","sort_order","page_id");
		$this->iDataSource->addColumnControl("user_group_access","list_table_controls",array("page_id"=>array("get_choices"=>"pageChoices"),"permission_level"=>array("data_type"=>"select","choices"=>array(""=>"[None]","0"=>"No Access","1"=>"Read Only","2"=>"Write","3"=>"All"))));

		$this->iDataSource->addColumnControl("user_group_page_functions","data_type","custom");
		$this->iDataSource->addColumnControl("user_group_page_functions","control_class","MultipleSelect");
		$this->iDataSource->addColumnControl("user_group_page_functions","control_table","page_functions");
		$this->iDataSource->addColumnControl("user_group_page_functions","links_table","user_group_page_functions");
		$this->iDataSource->addColumnControl("user_group_page_functions","form_label","Page Functions");
		$this->iDataSource->addColumnControl("user_group_page_functions","get_choices","pageFunctionChoices");

		$this->iDataSource->addColumnControl("user_group_function_uses","data_type","custom");
		$this->iDataSource->addColumnControl("user_group_function_uses","control_class","MultipleSelect");
		$this->iDataSource->addColumnControl("user_group_function_uses","control_table","user_functions");
		$this->iDataSource->addColumnControl("user_group_function_uses","links_table","user_group_function_uses");
		$this->iDataSource->addColumnControl("user_group_function_uses","form_label","User Functions");
	}

	function afterSaveDone($nameValues) {
		removeCachedData("user_group_permission", "*");
	}
}

$pageObject = new UserGroupMaintenancePage("user_groups");
$pageObject->displayPage();
