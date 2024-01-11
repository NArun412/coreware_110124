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

$GLOBALS['gPageCode'] = "TEMPLATEDATAMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function onLoadJavascript() {
?>
	$(document).on("tap click","#allow_multiple",function() {
		if ($("#data_type").val() == "image") {
			displayErrorMessage("Images cannot allow multiple");
			$(this).prop("checked",false);
		}
		if (!$(this).prop("checked")) {
			$("#group_identifier").val("");
		}
	});
	$("#group_identifier").change(function() {
		if ($("#data_type").val() == "image") {
			displayErrorMessage("Images cannot allow multiple");
			$(this).val("");
		}
		if (!empty($(this).val())) {
			$("#allow_multiple").prop("checked",true);
		}
	});
<?php
	}
}

$pageObject = new ThisPage("template_data");
$pageObject->displayPage();
