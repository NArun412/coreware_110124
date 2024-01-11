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

$GLOBALS['gPageCode'] = "LOGPURGEPARAMETERMAINT";
require_once "shared/startup.inc";

class LogPurgeParameterMaintenancePage extends Page {

	function massageDataSource() {
		$this->iDataSource->addColumnControl("maximum_days", "help_label", "Days of log entries to keep");

		$tables = array("action_log"=>"","api_log"=>"","debug_log"=>"","merchant_log"=>"",
			"background_process_log"=>"","change_log"=>"","click_log"=>"",
			"download_log"=>"","ecommerce_log"=>"","email_log"=>"","error_log"=>"","image_usage_log"=>"",
			"not_found_log"=>"","product_distributor_log"=>"","product_inventory_log"=>"","product_view_log"=>"",
			"program_log"=>"","query_log"=>"","search_term_log"=>"","security_log"=>"","server_monitor_log"=>"",
			"user_activity_log"=>"","web_user_pages"=>"");
		foreach ($tables as $tableName => $description) {
			$tables[$tableName] = getFieldFromId("description","tables","table_name",$tableName);
		}

		$this->iDataSource->addColumnControl("table_name", "choices", $tables);
		$this->iDataSource->addColumnControl("table_name", "data_type", "select");
		$this->iDataSource->addColumnControl("maximum_days", "maximum_value", 90);
	}

}

$pageObject = new LogPurgeParameterMaintenancePage("log_purge_parameters");
$pageObject->displayPage();
