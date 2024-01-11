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

$GLOBALS['gPageCode'] = "USERGROUPMEMBERMAINT";
require_once "shared/startup.inc";

class UserGroupMemberMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$filters = array();
			$resultSet = executeQuery("select * from user_groups where client_id = ? order by sort_order,description",$GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$filters['user_group_' . $row['user_group_id']] = array("form_label"=>$row['description'],"where"=>"user_group_id = " . $row['user_group_id'],"data_type"=>"tinyint");
			}
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("user_display_name","user_group_id"));
			$this->iTemplateObject->getTableEditorObject()->addIncludeFormColumn(array("user_id","user_group_id"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("user_display_name","select_value","select concat_ws(' - ',concat_ws(' ',first_name,last_name),user_name) from contacts join users using (contact_id) where user_id = user_group_members.user_id");
		$this->iDataSource->addColumnControl("user_display_name","form_label","User");
		$this->iDataSource->setFilterWhere("user_group_id in (select user_group_id from user_groups where client_id = " . $GLOBALS['gClientId'] . ") and user_id in (select user_id from users where client_id = " . $GLOBALS['gClientId'] . ")");
	}
}

$pageObject = new UserGroupMemberMaintenancePage("user_group_members");
$pageObject->displayPage();
