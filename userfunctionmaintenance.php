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

$GLOBALS['gPageCode'] = "USERFUNCTIONMAINT";
require_once "shared/startup.inc";

class UserFunctionMaintenancePage extends Page {

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables("user_function_uses");
		$count = 0;
		$resultSet = executeQuery("select count(*) from users where client_id = ?",$GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$count = $row['count(*)'];
		}
		if ($count < 200) {
			$this->iDataSource->addColumnControl("user_function_uses","data_type","custom");
			$this->iDataSource->addColumnControl("user_function_uses","form_label","Users");
			$this->iDataSource->addColumnControl("user_function_uses","control_class","MultipleSelect");
			$this->iDataSource->addColumnControl("user_function_uses","links_table","user_function_uses");
			$this->iDataSource->addColumnControl("user_function_uses","get_choices","allUserChoices");
		} else {
			$this->iDataSource->addColumnControl("user_function_uses", "data_type", "custom");
			$this->iDataSource->addColumnControl("user_function_uses", "form_label", "Users");
			$this->iDataSource->addColumnControl("user_function_uses", "control_class", "EditableList");
			$this->iDataSource->addColumnControl("user_function_uses", "list_table", "user_function_uses");
			$this->iDataSource->addColumnControl("user_function_uses", "list_table_controls", array("data_type"=>"user_picker"));
		}
	}
}

$pageObject = new UserFunctionMaintenancePage("user_functions");
$pageObject->displayPage();
