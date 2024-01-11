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

$GLOBALS['gPageCode'] = "CONTACTSUBSCRIPTIONMAINT";
require_once "shared/startup.inc";

class ContactSubscriptionMaintenancePage extends Page {

	private $iSubscriptionWasPaused = false;

	function setup() {
		$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("contact_id", "first_name", "last_name", "business_name", "email_address", "subscription_id", "start_date", "expiration_date", "inactive"));
		$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("contact_id", "first_name", "last_name", "business_name", "email_address", "subscription_id", "start_date", "expiration_date", "inactive"));
		$this->iTemplateObject->getTableEditorObject()->addExcludeFormColumn(array("first_name", "last_name", "business_name", "email_address"));
		$filters = array();
		$filters['active'] = array("form_label" => "Only Active Subscriptions", "where" => "inactive = 0 and (expiration_date is null or expiration_date >= current_date)", "data_type" => "tinyint");
		$filters['paused'] = array("form_label" => "Customer Paused", "where" => "customer_paused = 1", "data_type" => "tinyint");
		$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		if (canAccessPageCode("UPGRADESUBSCRIPTION")) {
			$resultSet = executeQuery("select count(*) from products where inactive = 0 and product_type_id in (select product_type_id from product_types where product_type_code = 'UPGRADE_SUBSCRIPTION') and client_id = ?", $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				if ($row['count(*)'] > 0) {
					$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("upgrade" => array("label" => getLanguageText("Upgrade"), "disabled" => false)));
				}
			}
		}
	}

	function filterTextProcessing($filterText) {
		if (!empty($filterText)) {
			$parts = explode(" ", $filterText);
			if (count($parts) == 2) {
				$whereStatement = "(contact_id in (select contact_id from contacts where (first_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[0] . "%") .
					" and last_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[1] . "%") . ")))";
				$this->iDataSource->addSearchWhereStatement($whereStatement);
			}
			$this->iDataSource->setFilterText($filterText);
		}
	}

	function massageDataSource() {
		$this->iDataSource->setFilterWhere("contact_id in (select contact_id from contacts where client_id = " . $GLOBALS['gClientId'] . ") and subscription_id in (select subscription_id from subscriptions where client_id = " . $GLOBALS['gClientId'] . ")");
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "contact_id",
			"description" => "first_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "contact_id",
			"description" => "last_name"));
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => "contacts",
			"referenced_column_name" => "contact_id", "foreign_key" => "contact_id",
			"description" => "business_name"));
		$this->iDataSource->addColumnControl("first_name", "select_value", "select first_name from contacts where contact_id = contact_subscriptions.contact_id");
		$this->iDataSource->addColumnControl("first_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("first_name", "form_label", "First");
		$this->iDataSource->addColumnControl("last_name", "select_value", "select last_name from contacts where contact_id = contact_subscriptions.contact_id");
		$this->iDataSource->addColumnControl("last_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("last_name", "form_label", "Last");
		$this->iDataSource->addColumnControl("email_address", "select_value", "select email_address from contacts where contact_id = contact_subscriptions.contact_id");
		$this->iDataSource->addColumnControl("email_address", "data_type", "varchar");
		$this->iDataSource->addColumnControl("email_address", "form_label", "Email Address");
		$this->iDataSource->addColumnControl("business_name", "select_value", "select business_name from contacts where contact_id = contact_subscriptions.contact_id");
		$this->iDataSource->addColumnControl("business_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("business_name", "form_label", "Company");
	}

	function beforeSaveChanges($nameValues) {
		$this->iSubscriptionWasPaused = getFieldFromId("customer_paused", "contact_subscriptions",
			"contact_subscription_id", $nameValues['primary_id']);
		return true;
	}

	function afterSaveChanges($nameValues) {
		$GLOBALS['gChangeLogNotes'] = "Updating User Subscriptions from contact subscription maintenance";
        $contactId = getFieldFromId("contact_id","contact_subscriptions","contact_subscription_id",$nameValues['primary_id']);
		updateUserSubscriptions($contactId);
		$GLOBALS['gChangeLogNotes'] = "";
		$isPaused = getFieldFromId("customer_paused", "contact_subscriptions",
			"contact_subscription_id", $nameValues['primary_id']);
		if ($this->iSubscriptionWasPaused && !$isPaused) {
			// update next billing date so that missed payments are skipped
			$nextBillingDate = getFieldFromId('next_billing_date', 'recurring_payments', 'contact_subscription_id', $nameValues['primary_id']);
			$nextBillingDate = date_create($nextBillingDate);
			if (empty($nextBillingDate)) {
				$nextBillingDate = date_create();
			}
			$subscriptionProductSet = executeQuery("select units_between, interval_unit from recurring_payments"
				. " join recurring_payment_order_items using (recurring_payment_id) join subscription_products using (product_id) where contact_subscription_id = ?",
				$nameValues['primary_id']);
			$subscriptionProductRow = getNextRow($subscriptionProductSet);
			freeResult($subscriptionProductSet);
			$today = date_create();
			while ($nextBillingDate < $today) {
				$nextBillingDate->add(date_interval_create_from_date_string($subscriptionProductRow['units_between'] . " " . $subscriptionProductRow['interval_unit']));
			}
			executeQuery("Update recurring_payments set next_billing_date = ? where contact_subscription_id = ?",
				date_format($nextBillingDate, "Y-m-d"), $nameValues['primary_id']);
		}

		return true;
	}

	function onLoadJavascript() {
		?>
		<script>
            $(document).on("tap click", "#_duplicate_button", function () {
                const contactId = $("#contact_id").val();
                window.open("/upgrade-subscription?contact_id=" + contactId);
                return false;
            });
		</script>
		<?php
	}
}

$pageObject = new ContactSubscriptionMaintenancePage("contact_subscriptions");
$pageObject->displayPage();
