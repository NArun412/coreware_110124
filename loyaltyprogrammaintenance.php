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

$GLOBALS['gPageCode'] = "LOYALTYPROGRAMMAINT";
require_once "shared/startup.inc";

class LoyaltyProgramMaintenancePage extends Page {

	function massageDataSource() {
		$this->iDataSource->addColumnControl("user_type_id", "empty_text", "[Any]");
		$this->iDataSource->addColumnControl("user_type_id", "maximum_amount", "Maximum dollar amount that can be redeemed with points on a single order. Leave blank for no limit.");

		$this->iDataSource->addColumnControl("loyalty_program_awards", "data_type", "custom");
		$this->iDataSource->addColumnControl("loyalty_program_awards", "form_label", "Award Levels");
		$this->iDataSource->addColumnControl("loyalty_program_awards", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("loyalty_program_awards", "list_table", "loyalty_program_awards");

		$this->iDataSource->addColumnControl("loyalty_program_values", "data_type", "custom");
		$this->iDataSource->addColumnControl("loyalty_program_values", "form_label", "Point Values");
		$this->iDataSource->addColumnControl("loyalty_program_values", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("loyalty_program_values", "list_table", "loyalty_program_values");

		$this->iDataSource->addColumnControl("minimum_amount", "help_label", "Minimum number of points a user must have before they can redeem any");
		$this->iDataSource->getPrimaryTable()->setSubtables(array("loyalty_program_awards","loyalty_program_values"));
	}

}

$pageObject = new LoyaltyProgramMaintenancePage("loyalty_programs");
$pageObject->displayPage();
