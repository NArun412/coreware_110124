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

$GLOBALS['gPageCode'] = "DONATIONEDIT";
require_once "shared/startup.inc";

class DonationEditPage extends Page {
	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("donation_date", "amount", "designation_id", "project_name", "first_name", "last_name", "business_name", "email_address"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add","delete"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->setFilterWhere("pay_period_id is null and client_id = " . $GLOBALS['gClientId']);
		$this->iDataSource->setSaveOnlyPresent(true);
		$this->iDataSource->addColumnControl("donation_date", "readonly", "true");
		$this->iDataSource->addColumnControl("donor_info", "readonly", "true");
		$this->iDataSource->addColumnControl("donor_info", "data_type", "varchar");
		$this->iDataSource->addColumnControl("donor_info", "css-width", "500px");
		$this->iDataSource->addColumnControl("donor_info", "form_label", "Donor");
		$this->iDataSource->addColumnControl("amount", "readonly", "true");

		$this->iDataSource->addColumnControl("first_name", "select_value", "select first_name from contacts where contact_id = donations.contact_id");
		$this->iDataSource->addColumnControl("first_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("first_name", "form_label", "First");
		$this->iDataSource->addColumnControl("last_name", "select_value", "select last_name from contacts where contact_id = donations.contact_id");
		$this->iDataSource->addColumnControl("last_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("last_name", "form_label", "Last");
		$this->iDataSource->addColumnControl("business_name", "select_value", "select business_name from contacts where contact_id = donations.contact_id");
		$this->iDataSource->addColumnControl("business_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("business_name", "form_label", "Company");

		$this->iDataSource->addColumnControl("email_address", "select_value", "select email_address from contacts where contact_id = donations.contact_id");
		$this->iDataSource->addColumnControl("email_address", "data_type", "varchar");
		$this->iDataSource->addColumnControl("email_address", "form_label", "Email");
	}

	function afterGetRecord(&$returnArray) {
		$countryId = getFieldFromId("country_id", "contacts", "contact_id", $returnArray['contact_id']['data_value']);
		$donorInfo = getDisplayName($returnArray['contact_id']['data_value']) . ", " .
			getFieldFromId("address_1", "contacts", "contact_id", $returnArray['contact_id']['data_value']) . ", " .
			getFieldFromId("city", "contacts", "contact_id", $returnArray['contact_id']['data_value']) . ", " .
			getFieldFromId("state", "contacts", "contact_id", $returnArray['contact_id']['data_value']) .
			($countryId == 1000 ? "" : ", " . getFieldFromId("country_name", "countries", "country_id", $countryId));
		$returnArray['donor_info'] = array("data_value" => $donorInfo);
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$donationFee = Donations::getDonationFee(getRowFromId("donations", "donation_id", $nameValues['primary_id']));
		executeQuery("update donations set donation_fee = ? where donation_id = ?", $donationFee, $nameValues['primary_id']);
		return true;
	}
}

$pageObject = new DonationEditPage("donations");
$pageObject->displayPage();
