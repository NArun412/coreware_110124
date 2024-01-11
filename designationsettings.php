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

$GLOBALS['gPageCode'] = "DESIGNATIONSETTINGS";
require_once "shared/startup.inc";

class DesignationSettingsPage extends Page {
	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$editAlias = getPreference("EDIT_ALIAS");
			if ($editAlias) {
				$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn("alias");
			}
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "delete"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("designation_code", "readonly", "true");
		$this->iDataSource->addColumnControl("description", "readonly", "true");
		$this->iDataSource->addColumnControl("link_name", "classes", "url-link");
		$this->iDataSource->addColumnControl("alias", "help_label", "leave blank to use description");
		$this->iDataSource->addColumnControl("designation_email_addresses", "data_type", "custom");
		$this->iDataSource->addColumnControl("designation_email_addresses", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("designation_email_addresses", "list_table", "designation_email_addresses");
		$this->iDataSource->addColumnControl("designation_email_addresses", "form_label", "Email Addresses");
		$this->iDataSource->addColumnControl("designation_email_addresses", "help_label", "for giving reports and notifications");

		$this->iDataSource->addColumnControl("designation_giving_goals", "form_label", "Giving Goals");
		$this->iDataSource->addColumnControl("designation_giving_goals", "data_type", "custom");
		$this->iDataSource->addColumnControl("designation_giving_goals", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("designation_giving_goals", "list_table", "designation_giving_goals");
		$this->iDataSource->addColumnControl("designation_giving_goals", "help_label", "If more than one is active, only the first will be used");

		$this->iDataSource->addColumnControl("image_id", "data_type", "image_input");
		$this->iDataSource->setFilterWhere(($GLOBALS['gUserRow']['full_client_access'] ? "" : "inactive = 0 and designation_id in (select designation_id from designation_users where user_id = " . $GLOBALS['gUserId'] . ")"));
		$this->iDataSource->setSaveOnlyPresent(true);
	}

	function massageUrlParameters() {
		if (!$GLOBALS['gUserRow']['full_client_access']) {
			$resultSet = executeQuery("select * from designations where client_id = ? and inactive = 0 and " .
				"designation_id in (select designation_id from designation_users where user_id = ?)", $GLOBALS['gClientId'], $GLOBALS['gUserId']);
			if ($resultSet['row_count'] == 1) {
				if ($row = getNextRow($resultSet)) {
					$_GET['url_subpage'] = $_GET['url_page'];
					$_GET['url_page'] = "show";
					$_GET['primary_id'] = $row['designation_id'];
				}
			}
		}
	}

	function afterGetRecord(&$returnArray) {
		ob_start();
		$resultSet = executeQuery("select * from designation_files where designation_id = ?", $returnArray['primary_id']['data_value']);
		if ($resultSet['row_count'] > 0) {
			?>
            <h2>Files</h2>
			<?php
			while ($row = getNextRow($resultSet)) {
				?>
                <p><a target="_blank" href="/download.php?id=<?= $row['file_id'] ?>"><?= htmlText($row['description']) ?></a></p>
				<?php
			}
		}
		$returnArray['designation_files'] = array("data_value" => ob_get_clean());
	}
}

$pageObject = new DesignationSettingsPage("designations");
$pageObject->displayPage();
