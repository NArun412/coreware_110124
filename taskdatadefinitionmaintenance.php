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

$GLOBALS['gPageCode'] = "TASKDATADEFINITIONMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function onLoadJavascript() {
?>
$(document).on("tap click","#allow_multiple",function() {
	if (!$(this).prop("checked")) {
		$("#group_identifier").val("");
	}
});
$("#group_identifier").change(function() {
	if (!empty($(this).val())) {
		$("#allow_multiple").prop("checked",true);
	}
});
<?php
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("task_type_data"));
		if (!$GLOBALS['gUserRow']['superuser_flag']) {
			$this->iDataSource->addColumnControl("table_name","readonly",true);
			$this->iDataSource->addColumnControl("column_name","readonly",true);
		}
	}
}

$pageObject = new ThisPage("task_data_definitions");
$pageObject->displayPage();
