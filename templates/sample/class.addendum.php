<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

class TemplateAddendum {
	function addendumMethod($parameters = array()) {
		$resultSet = executeQuery("select count(*) from users");
		if ($row = getNextRow($resultSet)) {
			$userCount = $row['count(*)'];
			echo "<p class='align-center'>" . $userCount . " users on the system.</p>";
		}
	}

/*	If this method exists, the template will call it in addition to the page url actions

	function executeUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
		case "signup":
			# Do something
			ajaxResponse($returnArray);
			break;
		}
	}
*/
}
