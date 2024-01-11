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

$GLOBALS['gPageCode'] = "CATEGORYMAINT";
require_once "shared/startup.inc";

class CategoryMaintenancePage extends Page {

	function supplementaryContent() {
		?>
        <div class="basic-form-line" id="_contact_count_row">
            <label for="contact_count" class="">Contacts in Category</label>
            <input type="text" size="8" class="align-right" readonly="readonly" id="contact_count"
                   name="contact_count"/>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>
        <div class="basic-form-line" id="_remove_contacts_row">
            <label for="remove_contacts" class=""></label>
            <input type="checkbox" id="remove_contacts" name="remove_contacts"><label for="remove_contacts" class="color-red highlighted-text checkbox-label">Remove ALL contacts from this category. This CANNOT be undone.</label>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['remove_contacts'] = array("data_value" => "0");
		$returnArray['contact_count'] = array("data_value" => "0");
		$resultSet = executeQuery("select count(*) from contact_categories where category_id = ?", $returnArray['primary_id']['data_value']);
		if ($row = getNextRow($resultSet)) {
			$returnArray['contact_count']['data_value'] = $row['count(*)'];
		}
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		if (!empty($nameValues['remove_contacts'])) {
			executeQuery("delete from contact_categories where category_id = ?", $nameValues['primary_id']);
		}
		return true;
	}
}

$pageObject = new CategoryMaintenancePage("categories");
$pageObject->displayPage();
