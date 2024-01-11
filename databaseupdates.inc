<?php

/*	This software is the unpublished, confidential, proprietary, intellectual
	property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
	or used in any manner without expressed written consent from Kim David Software, LLC.
	Kim David Software, LLC owns all rights to this work and intends to keep this
	software confidential so as to maintain its value as a trade secret.

	Copyright 2004-Present, Kim David Software, LLC.

	WARNING! This code is part of the Kim David Software's Coreware system.
	Changes made to this source file will be lost when new versions of the system are installed.
*/

class DatabaseUpdate2253 extends AbstractDatabaseUpdate {
    public $iDescription = "Re-enable subsystems that are in use";

    function runUpdate() {
        $subsystemArray = array("CORE_DEVELOPER"=>"developers",
            "CORE_HELP_DESK"=>"help_desk_entries",
            "CORE_DMS"=>"donations",
            "CORE_EDUCATION"=>"courses",
            "CORE_EVENTS_FACILITIES"=>"facilities",
            "CORE_AUCTIONS"=>"auction_items");
        $clientSet = executeQuery("select * from clients where client_id <> ?", $GLOBALS['gDefaultClientId']);
        while($clientRow = getNextRow($clientSet)) {
            changeClient($clientRow['client_id']);
            foreach($subsystemArray as $subsystemCode => $tableName) {
                if(DataTable::isEmpty($tableName, true)) {
                    executeQuery("delete from client_subsystems where client_id = ? and subsystem_id = ?", $clientRow['client_id'],
                        getFieldFromId("subsystem_id", "subsystems", "subsystem_code", $subsystemCode));
                } else {
                    executeQuery("insert ignore into client_subsystems (client_id, subsystem_id) values (?,?)", $clientRow['client_id'],
                        getFieldFromId("subsystem_id", "subsystems", "subsystem_code", $subsystemCode));
                }
            }
        }

        return true;
    }
}

class DatabaseUpdate2251 extends AbstractDatabaseUpdate {
    public $iDescription = "Clean up duplicate inventory log records with truncate";

    function runUpdate() {
        if (in_array(getPreference("SYSTEM_NAME"), ["GUNPRO", "COREWARE13", "COREWARE20", "COREWARE22"])) {
            return true;
        }
        executeQuery("CREATE TABLE `temp_product_inventory_log` (
  `product_inventory_log_id` int NOT NULL AUTO_INCREMENT,
  `product_inventory_id` int NOT NULL,
  `inventory_adjustment_type_id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `order_id` int DEFAULT NULL,
  `log_time` datetime NOT NULL,
  `quantity` int NOT NULL,
  `total_cost` decimal(12,2) DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `version` int NOT NULL DEFAULT '1',
  PRIMARY KEY (`product_inventory_log_id`)
  ) ENGINE=InnoDB AUTO_INCREMENT=442880019 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
        executeQuery("insert into temp_product_inventory_log select * from product_inventory_log where notes <> 'Update product inventory from distributor'");
        $resultSet = executeQuery("select count(*) from temp_product_inventory_log");
        $row = getNextRow($resultSet);
        if($row['count(*)'] == 0) {
            sendEmail(array("email_address"=>"servers@coreware.com", "send_immediately"=>true, "subject"=>"Truncate Product Inventory Log failed",
                "body"=>"No records found in temp_product_inventory_log on " . getPreference("SYSTEM_NAME") . ". table not truncated; check for errors."));
            return true;
        }
        executeQuery("truncate table product_inventory_log");
        executeQuery("insert ignore into product_inventory_log select * from temp_product_inventory_log");
        $resultSet = executeQuery("select count(*) from product_inventory_log");
        $row = getNextRow($resultSet);
        if($row['count(*)'] == 0) {
            sendEmail(array("email_address"=>"servers@coreware.com", "send_immediately"=>true, "subject"=>"Truncate Product Inventory Log failed",
                "body"=>"No records found in product_inventory_log on " . getPreference("SYSTEM_NAME") . ". Temp table not dropped; check for errors."));
            return true;
        } else {
            executeQuery("drop table temp_product_inventory_log");
        }
        executeQuery("update preferences set system_value = null where preference_code = 'DELETE_PRODUCT_INVENTORY_LOG_DATES'");
        return true;
    }
}

class DatabaseUpdate2250 extends AbstractDatabaseUpdate {
    public $iDescription = "Clean up duplicate inventory log records";

    function runUpdate() {
        $preferenceArray = array(['preference_code' => 'DELETE_PRODUCT_INVENTORY_LOG_DATES', 'description' => 'Dates to delete product inventory logs',
            'data_type' => 'varchar', 'client_setable' => 0, 'system_value' => "2023-10-03,2023-10-18"]);
        setupPreferences($preferenceArray);

        return true;
    }
}
class DatabaseUpdate2249 extends AbstractDatabaseUpdate {
    public $iDescription = "Add Orion as Distributor";

    function runUpdate() {
        executeQuery("insert into product_distributors (product_distributor_code, description, class_name) values ('ORION','Orion Wholesale','orion')");
        return true;
    }
}

class DatabaseUpdate2248 extends AbstractDatabaseUpdate {
    public $iDescription = "Create order actions for order item status";

    function runUpdate() {
        $actionTypeId = getFieldFromId("action_type_id", "action_types", "action_type_code", "ALL_ORDER_ITEM_STATUS_SET");
        if (empty($actionTypeId)) {
            $orderItemTableId = getFieldFromId("table_id", "tables", "table_name", "order_items");
            $orderItemStatusesTableId = getFieldFromId("table_id", "tables", "table_name", "order_item_statuses");
            executeQuery("insert into action_types (action_type_code,description,table_id,update_table_id) values ('ALL_ORDER_ITEM_STATUS_SET','All Items in Order set to Status',?,?)", $orderItemStatusesTableId, $orderItemTableId);
        }
        $actionResultId = getFieldFromId("action_result_id", "action_results", "action_result_code", "SET_ORDER_STATUS");
        if(empty($actionResultId)) {
            $orderStatusTableId = getFieldFromId("table_id", "tables", "table_name", "order_status");
            executeQuery("insert into action_results (action_result_code, description, table_id) values ('SET_ORDER_STATUS','Set Order Status',?)", $orderStatusTableId);
        }
        return true;
    }
}

class DatabaseUpdate2247 extends AbstractDatabaseUpdate {
	public $iDescription = "Data structure for One-time Use Promotion Codes";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "one_time_use_promotion_codes", "subsystem" => "Orders");

		$parameters[] = array("operation" => "add_column", "column_name" => "client_id", "table_name" => "one_time_use_promotion_codes", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "promotion_id", "table_name" => "one_time_use_promotion_codes", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "promotion_code", "table_name" => "one_time_use_promotion_codes", "not_null" => true, "indexed"=>true);
		$parameters[] = array("operation" => "add_column", "column_name" => "order_id", "table_name" => "one_time_use_promotion_codes", "not_null" => false, "column_type" => "int");
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "one_time_use_promotion_codes", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("client_id", "promotion_code"), "table_name" => "one_time_use_promotion_codes");

		$parameters[] = array("operation" => "add_column", "column_name" => "promotion_code", "table_name" => "shopping_carts", "not_null" => false, "after"=>"promotion_id");
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("date_created", "promotion_code", "last_activity"), "table_name" => "shopping_carts");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2246 extends AbstractDatabaseUpdate {
	public $iDescription = "Add fields for designation deductions";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "pay_period_id", "table_name" => "designation_deductions", "not_null" => false, "after" => "one_time");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2245 extends AbstractDatabaseUpdate {
	public $iDescription = "Index location cost";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "location_cost", "table_name" => "product_inventories", "indexed" => true);
		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2244 extends AbstractDatabaseUpdate {
	public $iDescription = "Add base cost to product_inventories";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "location_cost", "table_name" => "product_inventories", "not_null" => false, "after" => "quantity", "column_type" => "decimal", "data_size" => "12", "decimal_places" => "2");
		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2243 extends AbstractDatabaseUpdate {
	public $iDescription = "Add preference for force cost update";

	function runUpdate() {
		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "FORCE_COST_UPDATE");
		if (empty($preferenceId)) {
			$resultSet = executeQuery("insert into preferences (preference_code, description, data_type) values('FORCE_COST_UPDATE', 'Force Cost Update', 'tinyint')");
		}
		executeQuery("update preferences set system_value = 'true' where preference_code = 'FORCE_COST_UPDATE'");
		return true;
	}
}

class DatabaseUpdate2242 extends AbstractDatabaseUpdate {
	public $iDescription = "Move Coreware OIDC credentials to database";

	function runUpdate() {

		$preferenceArray = array(
			array('preference_code' => 'COREWARE_OIDC_CLIENT_ID', 'description' => 'Coreware OIDC Client ID', 'data_type' => 'varchar', 'client_setable' => 0,
				'hide_system_value' => 1, 'system_value' => "565203745863-35muqqu4auskjnfsu3bcpk4nabbecij3.apps.googleusercontent.com"),
			array('preference_code' => 'COREWARE_OIDC_CLIENT_SECRET', 'description' => 'Coreware OIDC Client Secret', 'data_type' => 'varchar', 'client_setable' => 0,
				'hide_system_value' => 1, 'system_value' => "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"),
		);
		setupPreferences($preferenceArray);

		return true;
	}
}

class DatabaseUpdate2241 extends AbstractDatabaseUpdate {
	public $iDescription = "Update temporary setting for preferences";

	function runUpdate() {
		executeQuery("update preferences set temporary_setting = 1 where preference_code like 'LOG\_%'");
		return true;
	}
}

class DatabaseUpdate2240 extends AbstractDatabaseUpdate {
	public $iDescription = "Add gift card pin as separate field";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "gift_card_pin", "table_name" => "gift_cards", "not_null" => false, "column_type" => "varchar", "data_size" => "8", "after" => "gift_card_number");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2239 extends AbstractDatabaseUpdate {
	public $iDescription = "Add Background process for Updating Gunbroker Listings";

	function runUpdate() {
		executeQuery("update recurring_payments set requires_attention = 0 where last_attempted > '2023-06-25 00:00:00' and requires_attention = 1");
		return true;
	}
}

class DatabaseUpdate2238 extends AbstractDatabaseUpdate {
	public $iDescription = "Add Background process for Updating Gunbroker Listings";

	function runUpdate() {
		$backgroundProcessId = getFieldFromId("background_process_id", "background_processes", "background_process_code", "UPDATE_GUNBROKER_LISTINGS");
		if (empty($backgroundProcessId)) {
			executeQuery("insert into background_processes (background_process_code, description, script_filename, repeat_rules) values " .
				"('UPDATE_GUNBROKER_LISTINGS', 'Update Gunbroker Listings', 'updategunbrokerlistings.php', 'MINUTES:60:::::')");
		}
		return true;
	}
}

class DatabaseUpdate2237 extends AbstractDatabaseUpdate {
	public $iDescription = "Add date paused to contact subscription";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "date_paused", "table_name" => "contact_subscriptions", "not_null" => false, "column_type" => "date", "after" => "customer_paused");
		$parameters[] = array("operation" => "add_column", "column_name" => "product_id", "table_name" => "subscriptions", "not_null" => false, "after" => "user_group_id", "description" => "Paused Product");
		$parameters[] = array("operation" => "add_column", "column_name" => "days_between", "table_name" => "subscriptions", "not_null" => false, "after" => "product_id", "description" => "Minimum Days Between Pauses", "column_type" => "int");
		$parameters[] = array("operation" => "add_column", "column_name" => "maximum_pause_days", "table_name" => "subscriptions", "not_null" => false, "after" => "product_id", "description" => "Maximum Days to Pause", "column_type" => "int");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2236 extends AbstractDatabaseUpdate {
	public $iDescription = "Add preferences for reCAPTCHA";

	function runUpdate() {
		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "ORDER_RECAPTCHA_V2_SITE_KEY");
		if (empty($preferenceId)) {
			$resultSet = executeQuery("insert into preferences (preference_code, description, user_setable, client_setable, data_type) values('ORDER_RECAPTCHA_V2_SITE_KEY', " .
				"'reCAPTCHA Site Key', 0, 1, 'varchar')");
		}
		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "ORDER_RECAPTCHA_V2_SECRET_KEY");
		if (empty($preferenceId)) {
			$resultSet = executeQuery("insert into preferences (preference_code, description, user_setable, client_setable, data_type) values('ORDER_RECAPTCHA_V2_SECRET_KEY', " .
				"'reCAPTCHA Secret Key', 0, 1, 'varchar')");
		}
		return true;
	}
}

class DatabaseUpdate2235 extends AbstractDatabaseUpdate {
	public $iDescription = "Create Address Blacklist table";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "one_time", "table_name" => "designation_deductions", "not_null" => true, "column_type" => "tinyint", "after" => "percentage");
		$parameters[] = array("operation" => "add_column", "column_name" => "inactive", "table_name" => "designation_deductions", "not_null" => true, "after" => "one_time");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		executeQuery("insert into address_blacklist (address_1,city,state,postal_code) values ('Danti Court','Hayward','CA','94545')");
		return true;
	}
}

class DatabaseUpdate2234 extends AbstractDatabaseUpdate {
	public $iDescription = "Create Address Blacklist table";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "address_blacklist", "subsystem" => "System");

		$parameters[] = array("operation" => "add_column", "column_name" => "address_1", "table_name" => "address_blacklist", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "city", "table_name" => "address_blacklist", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "state", "table_name" => "address_blacklist", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "postal_code", "table_name" => "address_blacklist", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "address_blacklist", "not_null" => true);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		executeQuery("insert into address_blacklist (address_1,city,state,postal_code) values ('Danti Court','Hayward','CA','94545')");
		return true;
	}
}

class DatabaseUpdate2233 extends AbstractDatabaseUpdate {
	public $iDescription = "Disable newcode background process";

	function runUpdate() {
		executeQuery("update background_processes set inactive = 1 where background_process_code = 'RUN_NEWCODE'");
		executeQuery("update preferences set system_value = 'true' where preference_code = 'USE_ORDER_CAPTCHA'");
		return true;
	}
}

class DatabaseUpdate2232 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove remote images when there are local images";

	function runUpdate() {
		executeQuery("delete from product_remote_images where product_id in (select product_id from products where image_id is not null)");
		return true;
	}
}

class DatabaseUpdate2231 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove unused manufacturer logos";

	function runUpdate() {
		executeQuery("update images set version = 1");
		executeQuery("update images set version = 60223 where image_id in (select image_id from contacts where image_id is not null)");
		executeQuery("delete from image_data where image_id in (select image_id from images where image_code like 'MANUFACTURER_LOGO%' and version < 1000)");
		executeQuery("delete from images where image_code like 'MANUFACTURER_LOGO%' and version < 1000");
		return true;
	}
}

class DatabaseUpdate2230 extends AbstractDatabaseUpdate {
	public $iDescription = "Add unique key for auction item offers";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("auction_item_id", "user_id"), "table_name" => "auction_item_offers");
		$parameters[] = array("operation" => "add_column", "column_name" => "inactive", "table_name" => "auction_item_offers", "not_null" => true, "after" => "from_seller");
		$parameters[] = array("operation" => "drop_table", "table_name" => "auction_item_offer_log");
		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2229 extends AbstractDatabaseUpdate {
	public $iDescription = "Add table for Auction Item Offer Statuses";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "auction_item_offer_statuses", "subsystem" => "Auctions");

		$parameters[] = array("operation" => "add_column", "column_name" => "client_id", "table_name" => "auction_item_offer_statuses", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "auction_item_offer_status_code", "table_name" => "auction_item_offer_statuses", "not_null" => true, "column_type" => "varchar", "data_size" => "100", "letter_case" => "U", "code_value" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "description", "table_name" => "auction_item_offer_statuses", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "email_id", "table_name" => "auction_item_offer_statuses", "not_null" => false, "after" => "description");
		$parameters[] = array("operation" => "add_column", "column_name" => "sort_order", "table_name" => "auction_item_offer_statuses", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "internal_use_only", "table_name" => "auction_item_offer_statuses", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "inactive", "table_name" => "auction_item_offer_statuses", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "auction_item_offer_statuses", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("client_id", "auction_item_offer_status_code"), "table_name" => "auction_item_offer_statuses");

		$parameters[] = array("operation" => "add_column", "column_name" => "auction_item_offer_status_id", "table_name" => "auction_item_offers", "not_null" => false, "after" => "amount");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2228 extends AbstractDatabaseUpdate {
	public $iDescription = "Add link URL for remote product images";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "image_identifier", "table_name" => "product_remote_images", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "link_url", "table_name" => "product_remote_images", "not_null" => false, "after" => "image_identifier");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2227 extends AbstractDatabaseUpdate {
	public $iDescription = "add preference for Show Unselected";

	function runUpdate() {
		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "MAINTENANCE_SHOW_UNSELECTED");
		if (empty($preferenceId)) {
			$resultSet = executeQuery("insert into preferences (preference_code, description, user_setable, client_setable, data_type) values('MAINTENANCE_SHOW_UNSELECTED', " .
				"'Show Unselected', 1, 1, 'tinyint')");
		}
		return true;
	}
}

class DatabaseUpdate2225 extends AbstractDatabaseUpdate {
	public $iDescription = "Add multiple products api parameter";

	function runUpdate() {
		$apiParameterId = getFieldFromId("api_parameter_id", "api_parameters", "column_name", "multiple_products");
		if (empty($apiParameterId)) {
			executeQuery("insert into api_parameters (column_name,description,data_type) values (?,?,?)", 'multiple_products', 'Multiple Products Array', 'array');
		}
		return true;
	}
}

class DatabaseUpdate2224 extends AbstractDatabaseUpdate {
	public $iDescription = "remove unused column";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "drop_column", "column_name" => "last_cached", "table_name" => "search_terms");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2223 extends AbstractDatabaseUpdate {
	public $iDescription = "Add source code for recurring payments";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "source_id", "table_name" => "recurring_payments", "not_null" => false, "after" => "promotion_id");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2222 extends AbstractDatabaseUpdate {
	public $iDescription = "location custom field types";

	function runUpdate() {
		$locationCustomFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "LOCATIONS");
		if (empty($orderCustomFieldTypeId)) {
			$resultSet = executeQuery("insert into custom_field_types (custom_field_type_code, description) values ('LOCATIONS','Locations')");
			$orderCustomFieldTypeId = $resultSet['insert_id'];
		}
		return true;
	}
}

class DatabaseUpdate2221 extends AbstractDatabaseUpdate {
	public $iDescription = "Add table for Auction Item Status Links";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "drop_table", "table_name" => "auction_item_purchase_statuses", "subsystem" => "Auctions");

		$parameters[] = array("operation" => "create_table", "table_name" => "auction_item_status_links", "subsystem" => "Auctions");
		$parameters[] = array("operation" => "add_column", "column_name" => "auction_item_id", "table_name" => "auction_item_status_links", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "auction_item_status_id", "table_name" => "auction_item_status_links", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "auction_item_status_links", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("auction_item_id", "auction_item_status_id"), "table_name" => "auction_item_status_links");

		$parameters[] = array("operation" => "add_column", "column_name" => "email_id", "table_name" => "auction_item_statuses", "not_null" => false, "after" => "description");
		$parameters[] = array("operation" => "add_column", "column_name" => "mark_completed", "table_name" => "auction_item_statuses", "not_null" => true, "after" => "sort_order");

		$parameters[] = array("operation" => "drop_column", "column_name" => "auction_item_status_id", "table_name" => "auction_item_purchases");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2220 extends AbstractDatabaseUpdate {
	public $iDescription = "Add unique key for auction items";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "date_completed", "table_name" => "auction_items", "not_null" => false, "after" => "end_time", "indexed" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("user_id", "link_name", "date_completed"), "table_name" => "auction_items");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2219 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove existing unique keys for auction items and add new one";

	function runUpdate() {
		$resultSet = executeQuery("delete from unique_key_columns where unique_key_id in(select unique_key_id from unique_keys where table_id = (select table_id from tables where table_name = 'auction_items'))");
		$resultSet = executeQuery("delete from unique_keys where table_id = (select table_id from tables where table_name = 'auction_items')");
		return true;
	}
}

class DatabaseUpdate2218 extends AbstractDatabaseUpdate {
	public $iDescription = "Add hacking terms";

	function runUpdate() {
		$terms = array("htmlcache", "html.gz");
		foreach ($terms as $thisTerm) {
			executeQuery("insert ignore into hacking_terms (search_term) values (?)", $thisTerm);
		}
		removeCachedData("hacking_terms", "", true);
		if ($GLOBALS['gApcuEnabled']) {
			apcu_clear_cache();
		}

		return true;
	}
}

class DatabaseUpdate2217 extends AbstractDatabaseUpdate {
	public $iDescription = "Disable cross client background process";

	function runUpdate() {
		executeQuery("update background_processes set inactive = 1 where background_process_code = 'CROSS_CLIENT_CHECK'");
		return true;
	}
}

class DatabaseUpdate2216 extends AbstractDatabaseUpdate {
	public $iDescription = "add detailed description for personalize message for crowd fund campaign participant";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "detailed_description", "table_name" => "crowd_fund_campaign_participants", "not_null" => false, "after" => "title_text");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2215 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove strict map with code policy";

	function runUpdate() {
		$strictMapPolicy = getFieldFromId("map_policy_id", "map_policies", "map_policy_code", "STRICT");
		$strictMapCodePolicy = getFieldFromId("map_policy_id", "map_policies", "map_policy_code", "STRICT_CODE");
		if (!empty($strictMapCodePolicy) && !empty($strictMapPolicy)) {
			executeQuery("update product_manufacturers set map_policy_id = ? where map_policy_id = ?", $strictMapPolicy, $strictMapCodePolicy);
			executeQuery("delete from map_policies where map_policy_id = ?", $strictMapCodePolicy);
		}
		$clientSet = executeQuery("select client_id from clients");
		while ($clientRow = getNextRow($clientSet)) {
			$codeEmailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_CUSTOM_QUOTE_CODE", "client_id = ?", $clientRow['client_id']);
			$noCodeEmailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_CUSTOM_QUOTE", "client_id = ?", $clientRow['client_id']);
			if (!empty($codeEmailId) && empty($noCodeEmailId)) {
				executeQuery("update emails set email_code = 'RETAIL_STORE_CUSTOM_QUOTE' where email_id = ?", $codeEmailId);
			}
		}
		return true;
	}
}

class DatabaseUpdate2214 extends AbstractDatabaseUpdate {
	public $iDescription = "Fix reset password email";

	function runUpdate() {
		executeQuery("update table_columns set description = 'Cannot ship from distributor to customer' where column_definition_id in (select column_definition_id from column_definitions where column_name = 'cannot_dropship')");
		return true;
	}
}

class DatabaseUpdate2213 extends AbstractDatabaseUpdate {
	public $iDescription = "Add table for Auction Item Status Links";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "auction_item_purchase_statuses", "subsystem" => "Auctions");

		$parameters[] = array("operation" => "add_column", "column_name" => "auction_item_purchase_id", "table_name" => "auction_item_purchase_statuses", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "auction_item_status_id", "table_name" => "auction_item_purchase_statuses", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "auction_item_purchase_statuses", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("auction_item_purchase_id", "auction_item_status_id"), "table_name" => "auction_item_purchase_statuses");

		$parameters[] = array("operation" => "drop_column", "column_name" => "auction_item_status_id", "table_name" => "auction_item_purchases");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2212 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove distributor inventory cache";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "drop_table", "table_name" => "distributor_inventory_cache");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2211 extends AbstractDatabaseUpdate {
	public $iDescription = "Fix reset password email";

	function runUpdate() {
		executeQuery("update emails set content = replace(content,'https://%domain_name%','%domain_name%') where email_code = 'RESET_PASSWORD'");
		return true;
	}
}

class DatabaseUpdate2210 extends AbstractDatabaseUpdate {
	public $iDescription = "Add out of stock threshold for departments";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "out_of_stock_threshold", "table_name" => "product_departments", "not_null" => true, "after" => "cart_maximum");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2209 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove access for laid off workers";

	function runUpdate() {
		executeQuery("update users set inactive = 1, superuser_flag = 0, password = '9GH2RG8743G98E7G98Q437GF8Q9FGA89EGHF807Q3' where user_name in ('matt.hardin','jason.parker','saltanat.zhumakalyi','rachael.weinstein','gabriel.stevens')");
		return true;
	}
}

class DatabaseUpdate2208 extends AbstractDatabaseUpdate {
	public $iDescription = "Add like count for product reviews";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "like_count", "table_name" => "product_reviews", "not_null" => true, "default_value" => "0", "after" => "response_content");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2207 extends AbstractDatabaseUpdate {
	public $iDescription = "update parameters for background processes";

	function runUpdate() {
		executeQuery("update background_processes set repeat_rules = 'DAILY:::::20:53' where background_process_code = 'IMPORT_DISTRIBUTOR_PRODUCTS'");
		executeQuery("update background_processes set repeat_rules = 'DAILY:::::19:53' where background_process_code = 'CALCULATE_PRODUCT_PRICES'");
		executeQuery("update background_processes set repeat_rules = 'DAILY:::::20:13' where background_process_code = 'CLEANUP'");
		return true;
	}
}

class DatabaseUpdate2206 extends AbstractDatabaseUpdate {
	public $iDescription = "Add sort order for related products";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "sort_order", "table_name" => "related_products", "not_null" => true, "default_value" => "100", "after" => "related_product_type_id");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2205 extends AbstractDatabaseUpdate {
	public $iDescription = "Index date completed";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "order_time", "table_name" => "orders", "indexed" => true);
		$parameters[] = array("operation" => "modify_column", "column_name" => "date_completed", "table_name" => "orders", "indexed" => true);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2204 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove erroneous conversion records";

	function runUpdate() {
		executeQuery("delete from product_distributor_conversions where table_name = 'product_facet_options' and original_value_qualifier is null");
		return true;
	}
}

class DatabaseUpdate2203 extends AbstractDatabaseUpdate {
	public $iDescription = "Data structure for hit limits";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "site_hit_limits", "subsystem" => "System");

		$parameters[] = array("operation" => "add_column", "column_name" => "description", "table_name" => "site_hit_limits", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "elapsed_seconds", "table_name" => "site_hit_limits", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "hits_per_minute", "table_name" => "site_hit_limits", "not_null" => true, "column_type" => "int");
		$parameters[] = array("operation" => "add_column", "column_name" => "log_only", "table_name" => "site_hit_limits", "not_null" => true, "column_type" => "tinyint", "default_value" => "0");
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "site_hit_limits", "not_null" => true);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		$siteHitLimitCount = getFieldFromId("count(*)", "site_hit_limits");
		if ($siteHitLimitCount == 0) {
			executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('Half Hour',1800,8,1)");
			executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('Hour',3600,6,1)");
		}

		return true;
	}
}

class DatabaseUpdate2202 extends AbstractDatabaseUpdate {
	public $iDescription = "Add hacking terms";

	function runUpdate() {
		$terms = array("python-requests");
		foreach ($terms as $thisTerm) {
			executeQuery("insert ignore into hacking_terms (search_term) values (?)", $thisTerm);
		}
		removeCachedData("hacking_terms", "", true);
		if ($GLOBALS['gApcuEnabled']) {
			apcu_clear_cache();
		}

		return true;
	}
}

class DatabaseUpdate2201 extends AbstractDatabaseUpdate {
	public $iDescription = "Add hacking terms";

	function runUpdate() {
		$terms = array("python-requests");
		foreach ($terms as $thisTerm) {
			executeQuery("insert ignore into hacking_terms (search_term) values (?)", $thisTerm);
		}
		removeCachedData("hacking_terms", "", true);
		if ($GLOBALS['gApcuEnabled']) {
			apcu_clear_cache();
		}

		return true;
	}
}

class DatabaseUpdate2200 extends AbstractDatabaseUpdate {
	public $iDescription = "Add column to connect location to an FFL";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "license_lookup", "table_name" => "locations", "not_null" => false, "after" => "store_information");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2181 extends AbstractDatabaseUpdate {
	public $iDescription = "Add hacking terms";

	function runUpdate() {
		executeQuery("update products set reindex = 1 where product_id in (select product_id from product_data where upc_code is not null and product_id not in " .
			"(select product_id from product_search_word_values where product_search_word_id = (select product_search_word_id from product_search_words where search_term = product_data.upc_code)))");
		executeQuery("update products set reindex = 1 where (select count(*) from product_search_word_values where product_id = products.product_id) < 5");
		return true;
	}
}

class DatabaseUpdate2178 extends AbstractDatabaseUpdate {
	public $iDescription = "Add Background process for Mass updating inventory";

	function runUpdate() {
		$backgroundProcessId = getFieldFromId("background_process_id", "background_processes", "background_process_code", "UPDATE_DISTRIBUTOR_INVENTORY_QUANTITIES");
		if (empty($backgroundProcessId)) {
			executeQuery("insert into background_processes (background_process_code, description, script_filename, repeat_rules) values " .
				"('UPDATE_DISTRIBUTOR_INVENTORY_QUANTITIES', 'Mass update of inventory quantities', 'updatedistributorinventoryquantities.php', 'MINUTES:30:::::')");
		}
		return true;
	}
}

class DatabaseUpdate2177 extends AbstractDatabaseUpdate {
	public $iDescription = "Add table for feeds";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "last_activity", "table_name" => "feed_details", "not_null" => true);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2176 extends AbstractDatabaseUpdate {
	public $iDescription = "Add table for feeds";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "feed_details", "subsystem" => "Products");

		$parameters[] = array("operation" => "add_column", "column_name" => "client_id", "table_name" => "feed_details", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "feed_detail_code", "table_name" => "feed_details", "not_null" => true, "column_type" => "varchar", "data_size" => "100", "letter_case" => "U", "code_value" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "time_created", "table_name" => "feed_details", "not_null" => true, "column_type" => "datetime");
		$parameters[] = array("operation" => "add_column", "column_name" => "last_activity", "table_name" => "feed_details", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "feed_details", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("client_id", "feed_detail_code"), "table_name" => "feed_details");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2174 extends AbstractDatabaseUpdate {
	public $iDescription = "Add Background process for Feed Generators";

	function runUpdate() {
		$backgroundProcessId = getFieldFromId("background_process_id", "background_processes", "background_process_code", "feed_generator");
		if (empty($backgroundProcessId)) {
			$insertSet = executeQuery("insert into background_processes (background_process_code, description, script_filename, repeat_rules) values " .
				"('FEED_GENERATOR', 'Feed Generator', 'feedgenerator.php', 'MINUTES:60:::::')");
		} else {
			$insertSet = executeQuery("update background_processes set repeat_rules = 'MINUTES:5:::::' where background_process_id = ?", $backgroundProcessId);
		}
		return true;
	}
}

class DatabaseUpdate2173 extends AbstractDatabaseUpdate {
	public $iDescription = "Add Background process for Feed Generators";

	function runUpdate() {
		$backgroundProcessId = getFieldFromId("background_process_id", "background_processes", "background_process_code", "feed_generator");
		if (empty($backgroundProcessId)) {
			$insertSet = executeQuery("insert into background_processes (background_process_code, description, script_filename, repeat_rules) values " .
				"('FEED_GENERATOR', 'Feed Generator', 'feedgenerator.php', 'MINUTES:60:::::')");
			$backgroundProcessId = $insertSet['insert_id'];
		}
		return true;
	}
}

class DatabaseUpdate2172 extends AbstractDatabaseUpdate {
	public $iDescription = "Make maximum amount not required";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "maximum_amount", "table_name" => "user_watchlists", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "log_time", "table_name" => "auction_item_purchases", "not_null" => true, "column_type" => "datetime", "after" => "user_id");

		$parameters[] = array("operation" => "create_table", "table_name" => "auction_item_payments", "subsystem" => "Auctions");

		$parameters[] = array("operation" => "add_column", "column_name" => "auction_item_id", "table_name" => "auction_item_payments", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "payment_date", "table_name" => "auction_item_payments", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "payment_method_id", "License File", "table_name" => "auction_item_payments", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "account_id", "table_name" => "auction_item_payments", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "reference_number", "table_name" => "auction_item_payments", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "amount", "table_name" => "auction_item_payments", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "authorization_code", "table_name" => "auction_item_payments", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "transaction_identifier", "table_name" => "auction_item_payments", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "notes", "table_name" => "auction_item_payments", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "auction_item_payments", "not_null" => true);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2170 extends AbstractDatabaseUpdate {
	public $iDescription = "Fix product tags";

	function runUpdate() {
		$productTags = array();
		$clientProductTags = array();
		$resultSet = executeQuery("select * from product_tags");
		while ($row = getNextRow($resultSet)) {
			$productTags[$row['product_tag_id']] = $row;
			if (!array_key_exists($row['client_id'], $clientProductTags)) {
				$clientProductTags[$row['client_id']] = array();
			}
			$clientProductTags[$row['client_id']][$row['product_tag_code']] = $row['product_tag_id'];
		}
		$fixCount = 0;
		$resultSet = executeQuery("select *,(select client_id from product_tags where product_tag_id = product_tag_links.product_tag_id) product_tag_client_id, " .
			"(select client_id from products where product_id = product_tag_links.product_id) product_client_id, (select product_tag_code from product_tags where product_tag_id = product_tag_links.product_tag_id) product_tag_code " .
			"from product_tag_links");
		while ($row = getNextRow($resultSet)) {
			if ($row['product_tag_client_id'] == $row['product_client_id']) {
				continue;
			}
			$correctProductTagId = $clientProductTags[$row['product_client_id']][$row['product_tag_code']];
			if (!empty($correctProductTagId)) {
				executeQuery("insert ignore into product_tag_links (product_id,product_tag_id,start_date,expiration_date,sequence_number) values (?,?,?,?,?)",
					$row['product_id'], $correctProductTagId, $row['start_date'], $row['expiration_date'], $row['sequence_number']);
			}
			executeQuery("delete from product_tag_links where product_tag_link_id = ?", $row['product_tag_link_id']);
			$fixCount++;
		}
		addDebugLog("Product tag links fixed: " . $fixCount);
		return true;
	}
}

class DatabaseUpdate2166 extends AbstractDatabaseUpdate {
	public $iDescription = "index for file code and link_name";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "file_code", "table_name" => "files", "not_null" => false, "indexed" => true);
		$parameters[] = array("operation" => "modify_column", "column_name" => "link_name", "table_name" => "files", "not_null" => false, "indexed" => true);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2165 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove discontinued category";

	function runUpdate() {
		$clientId = getFieldFromId("client_id", "clients", "client_code", "COREWARE8");
		if (empty($clientId)) {
			executeQuery("delete from product_category_links where product_category_id in (select product_category_id from product_categories where product_category_code = 'DISCONTINUED')");
			executeQuery("delete from product_categories where product_category_code = 'DISCONTINUED'");
		}
		return true;
	}
}

class DatabaseUpdate2164 extends AbstractDatabaseUpdate {
	public $iDescription = "index for hours between";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "hours_between", "table_name" => "product_sale_prices", "not_null" => true, "indexed" => true, "default_value" => "1");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2163 extends AbstractDatabaseUpdate {
	public $iDescription = "Update Sync CRM to only run daily";

	function runUpdate() {
		executeQuery("update background_processes set repeat_rules = 'DAILY:::::3:53' where background_process_code = 'SYNC_CRM'");
		return true;
	}
}

class DatabaseUpdate2162 extends AbstractDatabaseUpdate {
	public $iDescription = "Force clear cache on all servers";

	function runUpdate() {
		triggerServerClearCache();
		return true;
	}
}

class DatabaseUpdate2159 extends AbstractDatabaseUpdate {
	public $iDescription = "Update Order Status Maintenance Page";

	function runUpdate() {
		$pageId = $GLOBALS['gAllPageCodes']["ORDERSTATUSMAINT"];
		if (!empty($pageId)) {
			executeQuery("delete from page_data where page_id = ?", $pageId);
			executeQuery("update pages set javascript_code = null, css_content = null, script_filename = 'orderstatusmaintenance.php' where page_id = ?", $pageId);
			executeQuery("delete from page_controls where page_id = ?", $pageId);
		}
		return true;
	}
}

class DatabaseUpdate2156 extends AbstractDatabaseUpdate {
	public $iDescription = "add ach flag for ffl merchant accounts";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "ach_merchant_account", "table_name" => "ffl_merchant_accounts", "not_null" => true, "column_type" => "tinyint", "after" => "merchant_account_id");
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("federal_firearms_licensee_id", "ach_merchant_account"), "table_name" => "ffl_merchant_accounts");
		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2155 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove existing unique keys for ffl_merchant_accounts";

	function runUpdate() {
		$resultSet = executeQuery("delete from unique_key_columns where unique_key_id in(select unique_key_id from unique_keys where table_id = (select table_id from tables where table_name = 'ffl_merchant_accounts'))");
		$resultSet = executeQuery("delete from unique_keys where table_id = (select table_id from tables where table_name = 'ffl_merchant_accounts')");
		return true;
	}
}

class DatabaseUpdate2154 extends AbstractDatabaseUpdate {
	public $iDescription = "add code for location groups";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "location_group_code", "table_name" => "location_groups", "not_null" => false, "column_type" => "varchar", "data_size" => "100", "letter_case" => "U", "code_value" => true, "after" => "client_id");
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("client_id", "location_group_code"), "table_name" => "location_groups");
		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		executeQuery("update location_groups set location_group_code = concat('LOCATION_GROUP_',location_group_id) where location_group_code is null");
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "location_group_code", "table_name" => "location_groups", "not_null" => true);
		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2153 extends AbstractDatabaseUpdate {
	public $iDescription = "add preference for centralized FFL storage";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "federal_firearms_licensee_details", "subsystem" => "FFL");

		$parameters[] = array("operation" => "add_column", "column_name" => "client_id", "table_name" => "federal_firearms_licensee_details", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "federal_firearms_licensee_id", "table_name" => "federal_firearms_licensee_details", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "file_id", "description" => "License File", "table_name" => "federal_firearms_licensee_details", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "sot_file_id", "description" => "SOT License File", "table_name" => "federal_firearms_licensee_details", "not_null" => false, "referenced_table_name" => "files");
		$parameters[] = array("operation" => "add_column", "column_name" => "preferred", "table_name" => "federal_firearms_licensee_details", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "public_content", "table_name" => "federal_firearms_licensee_details", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "content", "table_name" => "federal_firearms_licensee_details", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "notes", "table_name" => "federal_firearms_licensee_details", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "inactive", "table_name" => "federal_firearms_licensee_details", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "federal_firearms_licensee_details", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("client_id", "federal_firearms_licensee_id"), "table_name" => "federal_firearms_licensee_details");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "ENABLE_PRODUCT_QUESTIONS");
		$preferenceGroupId = getFieldFromId("preference_group_id", "preference_groups", "preference_group_code", "RETAIL_STORE");
		if (empty($preferenceId)) {
			$resultSet = executeQuery("insert into preferences (preference_code, description, client_setable, data_type) values ('ENABLE_PRODUCT_QUESTIONS', " .
				"'Enable Product Questions',1,'tinyint')");
			$preferenceId = $resultSet['insert_id'];
		}
		executeQuery("insert ignore into preference_group_links(preference_id, preference_group_id) values(?,?)",
			$preferenceId, $preferenceGroupId);
		return true;
	}
}

class DatabaseUpdate2152 extends AbstractDatabaseUpdate {
	public $iDescription = "add index for product_codes";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "product_code", "table_name" => "distributor_product_codes", "not_null" => true, "indexed" => true);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2150 extends AbstractDatabaseUpdate {
	public $iDescription = "add field for allocated inventory";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "has_allocated_inventory", "table_name" => "locations", "not_null" => false, "after" => "sort_order", "column_type" => "tinyint");
		$parameters[] = array("operation" => "add_column", "column_name" => "last_inventory_update", "table_name" => "location_credentials", "not_null" => false, "after" => "date_last_run", "column_type" => "datetime");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2149 extends AbstractDatabaseUpdate {
	public $iDescription = "remove problematic view";

	function runUpdate() {
		executeQuery("delete from view_tables where table_id in (select table_id from tables where table_name = 'view_of_administrator_users')");
		executeQuery("delete from tables where table_name = 'view_of_administrator_users'");

		$parameters = array();
		$parameters[] = array("operation" => "drop_table", "table_name" => "view_of_administrator_users");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2148 extends AbstractDatabaseUpdate {
	public $iDescription = "Add inventory product for addons";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "inventory_product_id", "table_name" => "product_addons", "not_null" => false, "after" => "form_definition_id", "column_type" => "int", "referenced_table_name" => "products");
		$parameters[] = array("operation" => "add_column", "column_name" => "inventory_product_id", "table_name" => "product_addon_set_entries", "not_null" => false, "after" => "form_definition_id", "column_type" => "int", "referenced_table_name" => "products");
		$parameters[] = array("operation" => "add_column", "column_name" => "inventory_product_id", "table_name" => "product_category_addons", "not_null" => false, "after" => "form_definition_id", "column_type" => "int", "referenced_table_name" => "products");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2147 extends AbstractDatabaseUpdate {
	public $iDescription = "Add hacking terms";

	function runUpdate() {
		$terms = array("mdrv=");
		foreach ($terms as $thisTerm) {
			executeQuery("insert ignore into hacking_terms (search_term) values (?)", $thisTerm);
		}
		removeCachedData("all_hacking_terms", "hacking_terms");
		if ($GLOBALS['gApcuEnabled']) {
			apcu_clear_cache();
		}

		return true;
	}
}

class DatabaseUpdate2146 extends AbstractDatabaseUpdate {
	public $iDescription = "Add hacking terms";

	function runUpdate() {
		$terms = array("Monibot");
		foreach ($terms as $thisTerm) {
			executeQuery("insert ignore into hacking_terms (search_term) values (?)", $thisTerm);
		}
		removeCachedData("all_hacking_terms", "hacking_terms");
		if ($GLOBALS['gApcuEnabled']) {
			apcu_clear_cache();
		}

		return true;
	}
}

class DatabaseUpdate2145 extends AbstractDatabaseUpdate {
	public $iDescription = "Add cost threshold for locations";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "cost_threshold", "table_name" => "locations", "not_null" => false, "after" => "out_of_stock_threshold", "column_type" => "decimal", "data_size" => "12", "decimal_places" => "2");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2144 extends AbstractDatabaseUpdate {
	public $iDescription = "Add default shipping address to addresses";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "default_shipping_address", "table_name" => "addresses", "not_null" => true, "after" => "external_identifier", "column_type" => "tinyint", "default_value" => "0");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2143 extends AbstractDatabaseUpdate {
	public $iDescription = "Add default billing address flag";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "default_billing_address", "table_name" => "addresses", "not_null" => true, "after" => "external_identifier", "column_type" => "tinyint", "default_value" => "0");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2142 extends AbstractDatabaseUpdate {
	public $iDescription = "set Coreware users to use SSO";

	function runUpdate() {
		$contactsCustomFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "CONTACTS");
		$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "SSO_LINK_USER",
			"custom_field_type_id = ? and client_id = ?", $contactsCustomFieldTypeId, $GLOBALS['gDefaultClientId']);
		if (empty($customFieldId)) {
			$insertSet = executeQuery("insert into custom_fields (client_id, custom_field_code, description, custom_field_type_id, form_label) " .
				" values (?,'SSO_LINK_USER','Link User to SSO on next login', ?, 'Link User to SSO on next login')", $GLOBALS['gDefaultClientId'], $contactsCustomFieldTypeId);
			$customFieldId = $insertSet['insert_id'];
			executeQuery("insert into custom_field_controls (custom_field_id, control_name, control_value) values (?, 'data_type', 'tinyint')", $customFieldId);
			executeQuery("insert into custom_field_controls (custom_field_id, control_name, control_value) values (?, 'not_null', 'false')", $customFieldId);
		}
		$userSet = executeQuery("select * from users join contacts using (contact_id) where email_address like '%@coreware.com' and superuser_flag = 1");
		while ($userRow = getNextRow($userSet)) {
			executeQuery("update users set client_id = ? where user_id = ?", $GLOBALS['gDefaultClientId'], $userRow['user_id']);
			executeQuery("update contacts set client_id = ? where contact_id = ?", $GLOBALS['gDefaultClientId'], $userRow['contact_id']);
			executeQuery("insert into custom_field_data (primary_identifier, custom_field_id, text_data) values (?,?,'1')", $userRow['contact_id'], $customFieldId);
		}
		return true;
	}
}

class DatabaseUpdate2141 extends AbstractDatabaseUpdate {
	public $iDescription = "add login_providers table for SSO";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "login_providers", "subsystem" => "System");
		$parameters[] = array("operation" => "create_table", "table_name" => "login_provider_credentials", "subsystem" => "System");

		$parameters[] = array("operation" => "add_column", "column_name" => "description", "table_name" => "login_providers", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "class_name", "table_name" => "login_providers", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "credential_notes", "table_name" => "login_providers");
		$parameters[] = array("operation" => "add_column", "column_name" => "inactive", "table_name" => "login_providers", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "login_providers", "not_null" => true);

		$parameters[] = array("operation" => "add_column", "column_name" => "client_id", "table_name" => "login_provider_credentials", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "login_provider_id", "table_name" => "login_provider_credentials", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "account_login", "description" => "OAuth Client ID", "table_name" => "login_provider_credentials", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "account_key", "description" => "OAuth Client Secret", "table_name" => "login_provider_credentials", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "link_url", "description" => "Discovery URL", "table_name" => "login_provider_credentials", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "public_access", "table_name" => "login_provider_credentials");
		$parameters[] = array("operation" => "add_column", "column_name" => "allow_create_user", "table_name" => "login_provider_credentials", "not_null" => true, "column_type" => "tinyint", "default_value" => 0);
		$parameters[] = array("operation" => "add_column", "column_name" => "sort_order", "table_name" => "login_provider_credentials", "not_null" => true, "default_value" => 100);
		$parameters[] = array("operation" => "add_column", "column_name" => "inactive", "table_name" => "login_provider_credentials", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "login_provider_credentials", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("client_id", "login_provider_id"), "table_name" => "login_provider_credentials");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		executeQuery("insert into login_providers (description,class_name) values ('Google', 'GoogleOidc'), ('Azure', 'AzureOidc'), ('ForgeRock','ForgeRockOidc'),('Coreware Staff','CorewareOidc')");

		return true;
	}
}

class DatabaseUpdate2140 extends AbstractDatabaseUpdate {
	public $iDescription = "Add applyable as flag to payment methods";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "applyable", "table_name" => "payment_method_types", "not_null" => true, "after" => "sort_order", "column_type" => "tinyint", "default_value" => "0", "description" => "Applied at checkout");
		$parameters[] = array("operation" => "add_column", "column_name" => "default_payment_method", "table_name" => "accounts", "not_null" => true, "after" => "credit_terms", "column_type" => "tinyint", "default_value" => "0");
		$parameters[] = array("operation" => "add_column", "column_name" => "default_shipping_address", "table_name" => "addresses", "not_null" => true, "after" => "external_identifier", "column_type" => "tinyint", "default_value" => "0");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		executeQuery("update payment_method_types set applyable = 1 where payment_method_type_code in ('GIFT_CARD','LOYALTY_POINTS','CREDOVA')");
		executeQuery("update contacts set email_address = 'kim.geiger@coreware.com' where email_address = 'geigerk@gmail.com' and contact_id in (select contact_id from users where superuser_flag = 1)");
		return true;
	}
}

class DatabaseUpdate2138 extends AbstractDatabaseUpdate {
	public $iDescription = "Update api for loyalty points";

	function runUpdate() {
		executeQuery("DELETE c1 FROM api_method_parameters c1 INNER JOIN api_method_parameters c2 WHERE c1.api_method_parameter_id > c2.api_method_parameter_id AND c1.api_method_id = c2.api_method_id and c1.api_parameter_id = c2.api_parameter_id");

		$parameters = array();
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("api_method_id", "api_parameter_id"), "table_name" => "api_method_parameters");
		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		$userIdApiParameterId = getFieldFromId("api_parameter_id", "api_parameters", "column_name", "user_id");
		$contactIdApiParameterId = getFieldFromId("api_parameter_id", "api_parameters", "column_name", "contact_id");
		$apiMethodId = getFieldFromId("api_method_id", "api_methods", "api_method_code", "adjust_loyalty_point_balance");
		executeQuery("insert ignore into api_method_parameters (api_method_id,api_parameter_id) values (?,?)", $apiMethodId, $userIdApiParameterId);
		executeQuery("insert ignore into api_method_parameters (api_method_id,api_parameter_id) values (?,?)", $apiMethodId, $contactIdApiParameterId);
		executeQuery("update api_method_parameters set required = 0 where api_method_id = ? and api_parameter_id = ?", $apiMethodId, $userIdApiParameterId);
		$apiMethodId = getFieldFromId("api_method_id", "api_methods", "api_method_code", "get_loyalty_point_balance");
		executeQuery("insert ignore into api_method_parameters (api_method_id,api_parameter_id) values (?,?)", $apiMethodId, $userIdApiParameterId);
		executeQuery("insert ignore into api_method_parameters (api_method_id,api_parameter_id) values (?,?)", $apiMethodId, $contactIdApiParameterId);
		executeQuery("update api_method_parameters set required = 0 where api_method_id = ? and api_parameter_id = ?", $apiMethodId, $userIdApiParameterId);
		return true;
	}
}

class DatabaseUpdate2137 extends AbstractDatabaseUpdate {
	public $iDescription = "Update primary location for distributor locations";

	function runUpdate() {
		$resultSet = executeQuery("select * from clients");
		while ($clientRow = getNextRow($resultSet)) {
			$productDistributorIds = array();
			$locationSet = executeQuery("select * from locations where client_id = ? and inactive = 0 and product_distributor_id is not null order by product_distributor_id,primary_location desc,location_id", $clientRow['client_id']);
			while ($locationRow = getNextRow($locationSet)) {
				if (in_array($locationRow['product_distributor_id'], $productDistributorIds)) {
					continue;
				}
				$productDistributorIds[] = $locationRow['product_distributor_id'];
				if (empty($locationRow['primary_location'])) {
					executeQuery("update locations set primary_location = 1 where location_id = ?", $locationRow['location_id']);
				}
				executeQuery("update locations set primary_location = 0 where location_id <> ? and product_distributor_id = ? and client_id = ?",
					$locationRow['location_id'], $locationRow['product_distributor_id'], $clientRow['client_id']);
			}
		}
		return true;
	}
}

class DatabaseUpdate2136 extends AbstractDatabaseUpdate {
	public $iDescription = "Add Aaron's IP address";

	function runUpdate() {
		executeQuery("insert ignore into ip_address_whitelist (description,ip_address) values ('Aaron apartment','62.217.189.178')");
		return true;
	}
}

class DatabaseUpdate2135 extends AbstractDatabaseUpdate {
	public $iDescription = "update help label for product in event type";

	function runUpdate() {
		$pageId = $GLOBALS['gAllPageCodes']["EVENTTYPEMAINT"];
		executeQuery("update page_controls set control_value = 'Product for facility reservations for this event type or base product for classes' where control_name = 'help_label' and column_name = 'product_id' and page_id = ?", $pageId);
		return true;
	}
}

class DatabaseUpdate2134 extends AbstractDatabaseUpdate {
	public $iDescription = "add preference for product questions";

	function runUpdate() {
		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "ENABLE_PRODUCT_QUESTIONS");
		$preferenceGroupId = getFieldFromId("preference_group_id", "preference_groups", "preference_group_code", "RETAIL_STORE");
		if (empty($preferenceId)) {
			$resultSet = executeQuery("insert into preferences(preference_code, description, client_setable, data_type) values('ENABLE_PRODUCT_QUESTIONS', " .
				"'Enable Product Questions',1,'tinyint')");
			$preferenceId = $resultSet['insert_id'];
		}
		executeQuery("insert ignore into preference_group_links(preference_id, preference_group_id) values(?,?)",
			$preferenceId, $preferenceGroupId);
		return true;
	}
}

class DatabaseUpdate2133 extends AbstractDatabaseUpdate {
	public $iDescription = "Add tables for product Q & A";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "product_questions", "subsystem" => "Products");
		$parameters[] = array("operation" => "create_table", "table_name" => "product_answers", "subsystem" => "Products");

		$parameters[] = array("operation" => "add_column", "column_name" => "product_id", "table_name" => "product_questions", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "user_id", "table_name" => "product_questions", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "full_name", "table_name" => "product_questions", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "content", "table_name" => "product_questions", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "date_created", "table_name" => "product_questions", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "like_count", "table_name" => "product_questions", "not_null" => true, "column_type" => "int", "default_value" => "0");
		$parameters[] = array("operation" => "add_column", "column_name" => "requires_approval", "table_name" => "product_questions", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "inactive", "table_name" => "product_questions", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "product_questions", "not_null" => true);

		$parameters[] = array("operation" => "add_column", "column_name" => "product_question_id", "table_name" => "product_answers", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "user_id", "table_name" => "product_answers", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "full_name", "table_name" => "product_answers", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "content", "table_name" => "product_answers", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "date_created", "table_name" => "product_answers", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "like_count", "table_name" => "product_answers", "not_null" => true, "default_value" => "0");
		$parameters[] = array("operation" => "add_column", "column_name" => "requires_approval", "table_name" => "product_answers", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "inactive", "table_name" => "product_answers", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "product_answers", "not_null" => true);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2132 extends AbstractDatabaseUpdate {
	public $iDescription = "Add discount amount for quantity purchase";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "amount", "table_name" => "pricing_structure_quantity_discounts", "not_null" => true, "after" => "minimum_quantity", "default_value" => "0");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2131 extends AbstractDatabaseUpdate {
	public $iDescription = "drop distributor inventory only as it was never implemented";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "drop_column", "column_name" => "distributor_inventory_only", "table_name" => "pricing_structure_quantity_discounts");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2130 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove domain name page records";

	function runUpdate() {
		if ($GLOBALS['gClientRow']['client_code'] != "SHOOT_POINT_BLANK") {
			executeQuery("delete from domain_name_pages");
		}
		return true;
	}
}

class DatabaseUpdate2129 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove System Notices";

	function runUpdate() {
		executeQuery("delete from system_notice_users where system_notice_id in(select system_notice_id from system_notices where subject in('Outage issues', 'System Update'))");
		executeQuery("delete from system_notices where subject in('Outage issues', 'System Update')");
		return true;
	}
}

class DatabaseUpdate2128 extends AbstractDatabaseUpdate {
	public $iDescription = "Add tables for crowd funding campaigns";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "email_id", "table_name" => "crowd_fund_campaigns", "description" => "Response Email", "not_null" => false, "after" => "giving_goal");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2127 extends AbstractDatabaseUpdate {
	public $iDescription = "Add tables for crowd funding campaigns";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "giving_goal", "table_name" => "crowd_fund_campaigns", "not_null" => false, "after" => "donation_source_id");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2126 extends AbstractDatabaseUpdate {
	public $iDescription = "Add tables for crowd funding campaigns";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "crowd_fund_campaigns", "subsystem" => "DMS");
		$parameters[] = array("operation" => "create_table", "table_name" => "crowd_fund_campaign_designations", "subsystem" => "DMS");
		$parameters[] = array("operation" => "create_table", "table_name" => "crowd_fund_campaign_participants", "subsystem" => "DMS");
		$parameters[] = array("operation" => "create_table", "table_name" => "crowd_fund_campaign_participant_designations", "subsystem" => "DMS");

		$parameters[] = array("operation" => "add_column", "column_name" => "client_id", "table_name" => "crowd_fund_campaigns", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "crowd_fund_campaign_code", "table_name" => "crowd_fund_campaigns", "not_null" => true, "column_type" => "varchar", "data_size" => "100", "letter_case" => "U", "code_value" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "description", "table_name" => "crowd_fund_campaigns", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "detailed_description", "table_name" => "crowd_fund_campaigns", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "start_date", "table_name" => "crowd_fund_campaigns", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "end_date", "table_name" => "crowd_fund_campaigns", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "giving_goal", "table_name" => "crowd_fund_campaigns", "not_null" => false, "column_type" => "decimal", "data_size" => "12", "decimal_places" => "2");
		$parameters[] = array("operation" => "add_column", "column_name" => "sort_order", "table_name" => "crowd_fund_campaigns", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "internal_use_only", "table_name" => "crowd_fund_campaigns", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "inactive", "table_name" => "crowd_fund_campaigns", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "crowd_fund_campaigns", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("client_id", "crowd_fund_campaign_code"), "table_name" => "crowd_fund_campaigns");

		$parameters[] = array("operation" => "add_column", "column_name" => "crowd_fund_campaign_id", "table_name" => "crowd_fund_campaign_designations", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "designation_id", "table_name" => "crowd_fund_campaign_designations", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "crowd_fund_campaign_designations", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("crowd_fund_campaign_id", "designation_id"), "table_name" => "crowd_fund_campaign_designations");

		$parameters[] = array("operation" => "add_column", "column_name" => "client_id", "table_name" => "crowd_fund_campaign_participants", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "crowd_fund_campaign_id", "table_name" => "crowd_fund_campaign_participants", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "title_text", "table_name" => "crowd_fund_campaign_participants", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "contact_id", "table_name" => "crowd_fund_campaign_participants", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "donation_source_id", "table_name" => "crowd_fund_campaign_participants", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "giving_goal", "table_name" => "crowd_fund_campaigns", "not_null" => false, "column_type" => "decimal", "data_size" => "12", "decimal_places" => "2");
		$parameters[] = array("operation" => "add_column", "column_name" => "link_name", "table_name" => "crowd_fund_campaign_participants", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "inactive", "table_name" => "crowd_fund_campaign_participants", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "crowd_fund_campaign_participants", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("crowd_fund_campaign_id", "contact_id"), "table_name" => "crowd_fund_campaign_participants");
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("client_id", "link_name"), "table_name" => "crowd_fund_campaign_participants");

		$parameters[] = array("operation" => "add_column", "column_name" => "crowd_fund_campaign_participant_id", "table_name" => "crowd_fund_campaign_participant_designations", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "designation_id", "table_name" => "crowd_fund_campaign_participant_designations", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "crowd_fund_campaign_participant_designations", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("crowd_fund_campaign_participant_id", "designation_id"), "table_name" => "crowd_fund_campaign_participant_designations");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2125 extends AbstractDatabaseUpdate {
	public $iDescription = "Add link name to designation groups";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "detailed_description", "table_name" => "designation_groups", "not_null" => false, "after" => "description");
		$parameters[] = array("operation" => "add_column", "column_name" => "donation_source_id", "table_name" => "designation_groups", "not_null" => false, "after" => "detailed_description");
		$parameters[] = array("operation" => "add_column", "column_name" => "link_name", "table_name" => "designation_groups", "not_null" => false, "after" => "donation_source_id");
		$parameters[] = array("operation" => "add_column", "column_name" => "contact_id", "table_name" => "donation_sources", "not_null" => false, "after" => "description");
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("client_id", "link_name"), "table_name" => "designation_groups");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		$pageId = $GLOBALS['gAllPageCodes']["DONATIONSOURCEMAINT"];
		if (!empty($pageId)) {
			$pageControlId = getFieldFromId("page_control_id", "page_controls", "page_id", $pageId, "column_name = 'contact_id' and control_name = 'data_type'");
			if (empty($pageControlId)) {
				executeQuery("insert into page_controls(page_id, column_name, control_name, control_value) values(?,?,?,?)", $pageId, "contact_id", "data_type", "contact_picker");
			}
		}
		return true;
	}
}

class DatabaseUpdate2124 extends AbstractDatabaseUpdate {
	public $iDescription = "Clear cache";

	function runUpdate() {
		if ($GLOBALS['gApcuEnabled']) {
			apcu_clear_cache();
		}
		return true;
	}
}

class DatabaseUpdate2123 extends AbstractDatabaseUpdate {
	public $iDescription = "Add fields needed for complex addons form";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "form_definition_id", "table_name" => "product_category_addons", "not_null" => false, "after" => "manufacturer_sku");
		$parameters[] = array("operation" => "add_column", "column_name" => "form_definition_id", "table_name" => "product_addon_set_entries", "not_null" => false, "after" => "manufacturer_sku");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2122 extends AbstractDatabaseUpdate {
	public $iDescription = "Add fields needed for complex addons form";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "content", "table_name" => "order_item_addons", "not_null" => false, "after" => "sale_price");
		$parameters[] = array("operation" => "add_column", "column_name" => "content", "table_name" => "shopping_cart_item_addons", "not_null" => false, "after" => "sale_price");
		$parameters[] = array("operation" => "add_column", "column_name" => "form_definition_id", "table_name" => "product_addons", "not_null" => false, "after" => "manufacturer_sku");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2121 extends AbstractDatabaseUpdate {
	public $iDescription = "Add column for date status is set";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "date_completed", "table_name" => "form_status", "not_null" => false, "after" => "form_definition_status_id");
		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2120 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove erroneous phone numbers";

	function runUpdate() {
		executeQuery("update orders set phone_number = null where phone_number in('(719) 785-8309', '(719) 359-7162', '(719) 749-0563')");
		executeQuery("delete from phone_numbers where phone_number = '(719) 785-8309'");
		executeQuery("delete from phone_numbers where phone_number = '(719) 749-0563'");
		executeQuery("delete from phone_numbers where phone_number = '(719) 359-7162' and contact_id <> 10000");
		return true;
	}
}

class DatabaseUpdate2118 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove products from Coreware catalog that don't have UPC";

	function runUpdate() {
		if ($GLOBALS['gClientRow']['client_code'] != "CORE2") {
			return true;
		}
		$exclusionTables = array("courses", "distributor_order_items", "event_registration_products", "event_types", "events", "form_definitions", "gunbroker_products", "order_items", "promotion_purchased_products", "promotion_rewards_excluded_products", "promotion_rewards_products", "promotion_set_products",
			"promotion_terms_excluded_products", "promotion_terms_products", "subscription_products", "product_pack_contents", "distributor_order_products");
		$exclusionOtherTables = array("product_pack_contents" => "contains_product_id");
		$subTables = array("cost_difference_log", "distributor_product_codes", "ffl_product_restrictions", "potential_product_duplicates", "product_addons",
			"product_availability_notifications", "product_bulk_packs", "product_category_links", "product_change_details", "product_contributors", "product_custom_fields",
			"product_distributor_dropship_prohibitions", "product_facet_values", "product_group_variants", "product_images", "product_inventories",
			"product_inventory_notifications", "product_map_overrides", "product_offers", "product_payment_methods", "product_prices",
			"product_remote_images", "product_restrictions", "product_reviews", "product_sale_notifications", "product_sale_prices", "product_search_word_values",
			"product_serial_numbers", "product_shipping_carriers", "product_shipping_methods", "product_tag_links", "product_vendors", "product_videos", "product_view_log",
			"quotations", "recurring_payment_order_items", "related_products", "search_group_products",
			"shopping_cart_items", "source_products", "user_viewed_products", "vendor_products", "wish_list_items");
		$otherTables = array("potential_product_duplicates" => "duplicate_product_id", "related_products" => "associated_product_id");
		executeQuery("drop table if exists remove_noupc");
		executeQuery("create table remove_noupc (product_id int not null,primary key (product_id))");
		$query = "insert into remove_noupc select product_id from products where client_id = 2 and product_id not in (select product_id from product_data)";
		foreach ($exclusionTables as $thisTable) {
			$query .= " and product_id not in (select product_id from " . $thisTable . " where product_id is not null)";
		}
		foreach ($exclusionOtherTables as $thisTable => $thisField) {
			$query .= " and product_id not in (select " . $thisField . " from " . $thisTable . " where product_id is not null)";
		}
		$resultSet = executeQuery($query);
		executeQuery("delete from product_inventory_log where product_inventory_id in (select product_inventory_id from product_inventories where product_id in (select product_id from remove_noupc))");
		foreach ($subTables as $thisTable) {
			$resultSet = executeQuery("delete from " . $thisTable . " where product_id in (select product_id from remove_noupc)");
			if (!empty($resultSet['sql_error'])) {
				addDebugLog($resultSet['sql_error']);
				return false;
			}
		}
		foreach ($otherTables as $thisTable => $thisField) {
			$resultSet = executeQuery("delete from " . $thisTable . " where " . $thisField . " in (select product_id from remove_noupc)");
			if (!empty($resultSet['sql_error'])) {
				addDebugLog($resultSet['sql_error']);
				return false;
			}
		}
		$resultSet = executeQuery("delete from product_data where product_id in (select product_id from remove_noupc)");
		if (!empty($resultSet['sql_error'])) {
			addDebugLog($resultSet['sql_error']);
			return false;
		}
		$resultSet = executeQuery("delete from products where product_id in (select product_id from remove_noupc)");
		if (!empty($resultSet['sql_error'])) {
			addDebugLog($resultSet['sql_error']);
			return false;
		}
		addDebugLog($resultSet['affected_rows'] . " products deleted");
		return true;
	}
}

class DatabaseUpdate2115 extends AbstractDatabaseUpdate {
	public $iDescription = "Add column for run report immediately";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "run_immediately", "table_name" => "stored_reports", "not_null" => true, "after" => "email_results", "description" => "Run Once Immediately");
		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2114 extends AbstractDatabaseUpdate {
	public $iDescription = "Add table for Product Tag Groups";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "product_tag_groups", "subsystem" => "Products");

		$parameters[] = array("operation" => "add_column", "column_name" => "client_id", "table_name" => "product_tag_groups", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "product_tag_group_code", "table_name" => "product_tag_groups", "not_null" => true, "column_type" => "varchar", "data_size" => "100", "letter_case" => "U", "code_value" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "description", "table_name" => "product_tag_groups", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "sort_order", "table_name" => "product_tag_groups", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "internal_use_only", "table_name" => "product_tag_groups", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "inactive", "table_name" => "product_tag_groups", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "product_tag_groups", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("client_id", "product_tag_group_code"), "table_name" => "product_tag_groups");

		$parameters[] = array("operation" => "add_column", "column_name" => "product_tag_group_id", "table_name" => "product_tags", "not_null" => false, "after" => "detailed_description");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2113 extends AbstractDatabaseUpdate {
	public $iDescription = "Merge manufacturers";

	function runUpdate() {
		ProductCatalog::mergeManufacturers("MEPROLIGHT", "MEPRO_USA_LLC", true);
		return true;
	}
}

class DatabaseUpdate2112 extends AbstractDatabaseUpdate {
	public $iDescription = "Clear logs";

	function runUpdate() {
		executeQuery("delete from error_log");
		executeQuery("delete from debug_log");
		return true;
	}
}

class DatabaseUpdate2111 extends AbstractDatabaseUpdate {
	public $iDescription = "add index for primary identifier";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "primary_identifier", "table_name" => "custom_field_data", "not_null" => true, "indexed" => true);
		$parameters[] = array("operation" => "modify_column", "column_name" => "primary_identifier", "table_name" => "language_text", "not_null" => true, "indexed" => true);
		$parameters[] = array("operation" => "modify_column", "column_name" => "primary_identifier", "table_name" => "product_distributor_conversions", "not_null" => true, "indexed" => true);
		$parameters[] = array("operation" => "modify_column", "column_name" => "table_name", "table_name" => "product_distributor_conversions", "not_null" => true, "indexed" => true);
		$parameters[] = array("operation" => "modify_column", "column_name" => "original_value", "table_name" => "product_distributor_conversions", "not_null" => true, "indexed" => true);
		$parameters[] = array("operation" => "modify_column", "column_name" => "original_value_qualifier", "table_name" => "product_distributor_conversions", "not_null" => false, "indexed" => true);
		$parameters[] = array("operation" => "modify_column", "column_name" => "primary_identifier", "table_name" => "selected_rows", "not_null" => true, "indexed" => true);
		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2110 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove Generated UPC Product Tag";

	function runUpdate() {
		executeQuery("delete from product_tag_links where product_tag_id in (select product_tag_id from product_tags where product_tag_code = 'GENERATED_UPC_CODE')");
		executeQuery("update product_tags set inactive = 1 where product_tag_code = 'GENERATED_UPC_CODE'");
		executeQuery("delete ignore product_tags where product_tag_code = 'GENERATED_UPC_CODE'");
		return true;
	}
}

class DatabaseUpdate2109 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove System Notices";

	function runUpdate() {
		executeQuery("delete from system_notices where subject in ('Outage issues','System Update')");
		return true;
	}
}

class DatabaseUpdate2108 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove NOUPC products";

	function runUpdate() {
		$exclusionTables = array("courses", "distributor_order_items", "event_registration_products", "event_types", "events", "form_definitions", "gunbroker_products", "order_items", "promotion_purchased_products", "promotion_rewards_excluded_products", "promotion_rewards_products", "promotion_set_products",
			"promotion_terms_excluded_products", "promotion_terms_products", "subscription_products", "product_pack_contents", "distributor_order_products");
		$exclusionOtherTables = array("product_pack_contents" => "contains_product_id");
		$subTables = array("cost_difference_log", "distributor_product_codes", "ffl_product_restrictions", "potential_product_duplicates", "product_addons",
			"product_availability_notifications", "product_bulk_packs", "product_category_links", "product_change_details", "product_contributors", "product_custom_fields",
			"product_distributor_dropship_prohibitions", "product_facet_values", "product_group_variants", "product_images", "product_inventories",
			"product_inventory_notifications", "product_map_overrides", "product_offers", "product_payment_methods", "product_prices",
			"product_remote_images", "product_restrictions", "product_reviews", "product_sale_notifications", "product_sale_prices", "product_search_word_values",
			"product_serial_numbers", "product_shipping_carriers", "product_shipping_methods", "product_tag_links", "product_vendors", "product_videos", "product_view_log",
			"quotations", "recurring_payment_order_items", "related_products", "search_group_products",
			"shopping_cart_items", "source_products", "user_viewed_products", "vendor_products", "wish_list_items");
		$otherTables = array("potential_product_duplicates" => "duplicate_product_id", "related_products" => "associated_product_id");
		executeQuery("drop table if exists remove_noupc");
		executeQuery("create table remove_noupc (product_id int not null,primary key (product_id))");
		$query = "insert into remove_noupc select product_id from product_data where upc_code like 'NOUPC % '";
		foreach ($exclusionTables as $thisTable) {
			$query .= " and product_id not in (select product_id from " . $thisTable . " where product_id is not null)";
		}
		foreach ($exclusionOtherTables as $thisTable => $thisField) {
			$query .= " and product_id not in (select " . $thisField . " from " . $thisTable . " where product_id is not null)";
		}
		$resultSet = executeQuery($query);
		executeQuery("delete from product_inventory_log where product_inventory_id in (select product_inventory_id from product_inventories where product_id in (select product_id from remove_noupc))");
		foreach ($subTables as $thisTable) {
			$resultSet = executeQuery("delete from " . $thisTable . " where product_id in (select product_id from remove_noupc)");
			if (!empty($resultSet['sql_error'])) {
				addDebugLog($resultSet['sql_error']);
				return false;
			}
		}
		foreach ($otherTables as $thisTable => $thisField) {
			$resultSet = executeQuery("delete from " . $thisTable . " where " . $thisField . " in (select product_id from remove_noupc)");
			if (!empty($resultSet['sql_error'])) {
				addDebugLog($resultSet['sql_error']);
				return false;
			}
		}
		$resultSet = executeQuery("delete from product_data where product_id in (select product_id from remove_noupc)");
		if (!empty($resultSet['sql_error'])) {
			addDebugLog($resultSet['sql_error']);
			return false;
		}
		$resultSet = executeQuery("delete from products where product_id in (select product_id from remove_noupc)");
		if (!empty($resultSet['sql_error'])) {
			addDebugLog($resultSet['sql_error']);
			return false;
		}
		addDebugLog($resultSet['affected_rows'] . " products deleted");
		return true;
	}
}

class DatabaseUpdate2107 extends AbstractDatabaseUpdate {
	public $iDescription = "add sass header code";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "sass_header_code", "table_name" => "sass_headers", "not_null" => false, "column_type" => "varchar", "data_size" => "100", "letter_case" => "U", "code_value" => true, "after" => "client_id");
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("client_id", "sass_header_code"), "table_name" => "sass_headers");
		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		executeQuery("update sass_headers set sass_header_code = concat('CODE_',sass_header_id) where sass_header_code is null");
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "sass_header_code", "table_name" => "sass_headers", "not_null" => true, "indexed" => true);
		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2104 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove product pack contents where product_id = contains_product_id";

	function runUpdate() {
		$productIds = array();
		$resultSet = executeQuery("select product_id from product_pack_contents where product_id = contains_product_id");
		while ($row = getNextRow($resultSet)) {
			$productIds[] = $row['product_id'];
		}
		if (!empty($productIds)) {
			executeQuery("delete from product_pack_contents where product_id in (" . implode(",", $productIds) . ")");
		}
		return true;
	}
}

class DatabaseUpdate2103 extends AbstractDatabaseUpdate {
	public $iDescription = "Add not null for field labels";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "user_name", "table_name" => "location_credentials", "not_null" => false);
		$parameters[] = array("operation" => "modify_column", "column_name" => "password", "table_name" => "location_credentials", "not_null" => false);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2102 extends AbstractDatabaseUpdate {
	public $iDescription = "Add not null for field labels";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "not_null", "table_name" => "merchant_service_field_labels", "after" => "form_label", "not_null" => true, "default_value" => "0");
		$parameters[] = array("operation" => "add_column", "column_name" => "not_null", "table_name" => "product_distributor_field_labels", "after" => "form_label", "not_null" => true, "default_value" => "0");
		$parameters[] = array("operation" => "modify_column", "column_name" => "account_login", "table_name" => "merchant_accounts", "not_null" => false);
		$parameters[] = array("operation" => "modify_column", "column_name" => "account_key", "table_name" => "merchant_accounts", "not_null" => false);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2101 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove some stop words";

	function runUpdate() {
		executeQuery("delete from stop_words where search_term in ('above','across','amount','anywhere','behind','below','bottom','eight','eleven','empty','everyone','everything','everywhere','five','forty','four','front','hundred','nine','serious','six','sixty','something','system','ten','thick','thin','third','three','together','twelve','twenty','two','user','within','without')");
		return true;
	}
}

class DatabaseUpdate2100 extends AbstractDatabaseUpdate {
	public $iDescription = "Add search multiplier for product tags";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "search_multiplier", "table_name" => "product_tags", "after" => "points_multiplier", "not_null" => true, "default_value" => "1");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2099 extends AbstractDatabaseUpdate {
	public $iDescription = "Add meta description for categories, category groups and departments";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "meta_description", "table_name" => "product_categories", "after" => "meta_title", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "meta_description", "table_name" => "product_category_groups", "after" => "meta_title", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "meta_description", "table_name" => "product_departments", "after" => "meta_title", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "meta_description", "table_name" => "product_manufacturers", "after" => "meta_title", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "meta_description", "table_name" => "product_tags", "after" => "meta_title", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "meta_description", "table_name" => "product_types", "after" => "meta_title", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "meta_description", "table_name" => "contributors", "after" => "meta_title", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "meta_title", "table_name" => "search_groups", "after" => "detailed_description", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "meta_description", "table_name" => "search_groups", "after" => "meta_title", "not_null" => false);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2098 extends AbstractDatabaseUpdate {
	public $iDescription = "Add 'See Price in Cart' MAP policy";

	function runUpdate() {
		$useMapId = getFieldFromId("map_policy_id", "map_policies", "map_policy_code", "CART_PRICE");
		if (empty($useMapId)) {
			executeQuery("insert into map_policies (map_policy_code, description) values ('CART_PRICE', 'See price in cart')");
		}
		return true;
	}
}

class DatabaseUpdate2097 extends AbstractDatabaseUpdate {
	public $iDescription = "Add requirement for no previous orders";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "no_previous_orders", "table_name" => "promotions", "after" => "sort_order", "not_null" => true, "column_type" => "tinyint");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2096 extends AbstractDatabaseUpdate {
	public $iDescription = "Clear duplicated related product types";

	function runUpdate() {
		$relatedProductTypes = array();
		$relatedProductTypeIds = array();
		$resultSet = executeQuery("select * from related_product_types order by length(related_product_type_code) desc");
		while ($row = getNextRow($resultSet)) {
			$foundDup = false;
			foreach ($relatedProductTypes as $thisType) {
				if ($thisType != $row['related_product_type_code'] && startsWith($thisType, $row['related_product_type_code'])) {
					$relatedProductTypeIds[] = $row['related_product_type_id'];
					$foundDup = true;
					break;
				}
			}
			if (!$foundDup) {
				$relatedProductTypes[] = $row['related_product_type_code'];
			}
		}
		if (!empty($relatedProductTypeIds)) {
			executeQuery("delete from related_products where related_product_type_id in (" . implode(",", $relatedProductTypeIds) . ")");
			executeQuery("delete from related_product_types where related_product_type_id in (" . implode(",", $relatedProductTypeIds) . ")");
		}
		return true;
	}
}

class DatabaseUpdate2095 extends AbstractDatabaseUpdate {
	public $iDescription = "Clear out of stock threshold for local locations";

	function runUpdate() {
		executeQuery("update locations set out_of_stock_threshold = 0 where product_distributor_id is null");
		return true;
	}
}

class DatabaseUpdate2094 extends AbstractDatabaseUpdate {
	public $iDescription = "Add postal code to shipping charge locations";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "postal_code", "table_name" => "shipping_locations", "after" => "state", "not_null" => false);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2093 extends AbstractDatabaseUpdate {
	public $iDescription = "Add unique key for client page templates";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("client_id", "page_id"), "table_name" => "client_page_templates");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2092 extends AbstractDatabaseUpdate {
	public $iDescription = "Add field for using group description";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "use_group_description", "table_name" => "product_groups", "not_null" => true, "column_type" => "tinyint", "after" => "sort_order");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2091 extends AbstractDatabaseUpdate {
	public $iDescription = "Add field for map expiration date";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "map_expiration_date", "table_name" => "product_data", "not_null" => false, "column_type" => "date", "after" => "manufacturer_advertised_price");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2090 extends AbstractDatabaseUpdate {
	public $iDescription = "add fields for allowing image as contact identifier";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "allow_image", "table_name" => "contact_identifier_types", "not_null" => true, "after" => "sort_order");
		$parameters[] = array("operation" => "add_column", "column_name" => "image_id", "table_name" => "contact_identifiers", "not_null" => false, "after" => "identifier_value");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2089 extends AbstractDatabaseUpdate {
	public $iDescription = "Rename coreILLA v1 to ActiveCampaign";

	function runUpdate() {

		executeQuery("update contact_identifier_types set contact_identifier_type_code = 'ACTIVECAMPAIGN_ID', description = 'ActiveCampaign Contact Identifier' where contact_identifier_type_code = 'COREILLA_ID'");
		executeQuery("update contact_identifier_types set contact_identifier_type_code = 'ACTIVECAMPAIGN_CUSTOMER_ID', description = 'ActiveCampaign Customer Identifier' where contact_identifier_type_code = 'COREILLA_CUSTOMER_ID'");
		executeQuery("update preferences set preference_code = 'ACTIVECAMPAIGN_API_KEY', description = 'ActiveCampaign API Key' where preference_code = 'COREILLA_API_KEY'");
		executeQuery("update preferences set preference_code = 'ACTIVECAMPAIGN_TEST', description = 'ActiveCampaign Test Mode' where preference_code = 'COREILLA_TEST'");
		executeQuery("update preferences set preference_code = 'ACTIVECAMPAIGN_URL', description = 'ActiveCampaign URL' where preference_code = 'COREILLA_URL'");
		executeQuery("update preferences set preference_code = 'ACTIVECAMPAIGN_TEST_URL', description = 'ActiveCampaign Test URL' where preference_code = 'COREILLA_TEST_URL'");
		executeQuery("update preferences set preference_code = 'ACTIVECAMPAIGN_EVENT_KEY', description = 'ActiveCampaign Event Key' where preference_code = 'COREILLA_EVENT_KEY'");
		executeQuery("update preferences set preference_code = 'LOG_ACTIVECAMPAIGN', description = 'Log API calls to ActiveCampaign' where preference_code = 'LOG_COREILLA'");
		executeQuery("update pages set description = 'ActiveCampaign Sync', page_code = 'ACTIVECAMPAIGNSYNC', script_filename = 'activecampaignsync . php' where script_filename = 'coreillasync . php'");
		return true;
	}
}

class DatabaseUpdate2088 extends AbstractDatabaseUpdate {
	public $iDescription = "Add table for recurring donation amount changes";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "recurring_donation_changes", "subsystem" => "DMS");

		$parameters[] = array("operation" => "add_column", "column_name" => "recurring_donation_id", "table_name" => "recurring_donation_changes", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "amount", "table_name" => "recurring_donation_changes", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "next_billing_date", "table_name" => "recurring_donation_changes", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "change_date", "table_name" => "recurring_donation_changes", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "date_completed", "table_name" => "recurring_donation_changes", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "recurring_donation_changes", "not_null" => true);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2087 extends AbstractDatabaseUpdate {
	public $iDescription = "Add column for requires user";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "requires_user", "table_name" => "products", "not_null" => true, "after" => "sort_order", "description" => "Requires user for purchase");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2086 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove hacking term";

	function runUpdate() {
		executeQuery("delete from hacking_terms where search_term = 'phpinfo'");
		return true;
	}
}

class DatabaseUpdate2085 extends AbstractDatabaseUpdate {
	public $iDescription = "drop last activity";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "drop_column", "column_name" => "last_activity", "table_name" => "users");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2084 extends AbstractDatabaseUpdate {
	public $iDescription = "fix product tags";

	function runUpdate() {
		$resultSet = executeQuery("select * from product_tag_links where (select client_id from products where product_id = product_tag_links.product_id) != (select client_id from product_tags where product_tag_id = product_tag_links.product_tag_id)");
		while ($row = getNextRow($resultSet)) {
			$productClientId = getFieldFromId("client_id", "products", "product_id", $row['product_id']);
			$productTagClientId = getFieldFromId("client_id", "product_tags", "product_tag_id", $row['product_tag_id']);
			if ($productClientId == $productTagClientId) {
				continue;
			}
			$productTagCode = getFieldFromId("product_tag_code", "product_tags", "product_tag_id", $row['product_tag_id'], "client_id is not null");
			$newProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", $productTagCode, "client_id = ?", $productClientId);
			if (!empty($newProductTagId)) {
				executeQuery("insert ignore into product_tag_links (product_id,product_tag_id) values (?,?)", $row['product_id'], $newProductTagId);
			}
			executeQuery("delete from product_tag_links where product_tag_link_id = ?", $row['product_tag_link_id']);
		}
		return true;
	}
}

class DatabaseUpdate2082 extends AbstractDatabaseUpdate {
	public $iDescription = "Add table for random information";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "random_data_chunks", "subsystem" => "System");

		$parameters[] = array("operation" => "add_column", "column_name" => "random_data_chunk_code", "table_name" => "random_data_chunks", "not_null" => true, "column_type" => "varchar", "data_size" => "100", "letter_case" => "U", "code_value" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "text_data", "table_name" => "random_data_chunks", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "random_data_chunks", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("random_data_chunk_code"), "table_name" => "random_data_chunks");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2081 extends AbstractDatabaseUpdate {
	public $iDescription = "Add hacking terms";

	function runUpdate() {
		$terms = array("Baiduspider", "Yandex", "twenga", "majestic12", "thefind", "semrush", "dotbot", "AngleSharp", "Axios", "guncoyote", "Site24x7 Linux Agent", "pingdom", "pingbot", "PingdomPageSpeed");
		foreach ($terms as $thisTerm) {
			executeQuery("insert ignore into hacking_terms (search_term) values (?)", $thisTerm);
		}
		removeCachedData("all_hacking_terms", "hacking_terms");
		if ($GLOBALS['gApcuEnabled']) {
			apcu_clear_cache();
		}

		return true;
	}
}

class DatabaseUpdate2079 extends AbstractDatabaseUpdate {
	public $iDescription = "drop sudoku tables";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "drop_table", "table_name" => "sudoku_puzzle_cages");
		$parameters[] = array("operation" => "drop_table", "table_name" => "sudoku_puzzle_cell_feature_links");
		$parameters[] = array("operation" => "drop_table", "table_name" => "sudoku_puzzle_cell_features");
		$parameters[] = array("operation" => "drop_table", "table_name" => "sudoku_puzzle_cells");
		$parameters[] = array("operation" => "drop_table", "table_name" => "sudoku_puzzle_lines");
		$parameters[] = array("operation" => "drop_table", "table_name" => "sudoku_puzzles");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2078 extends AbstractDatabaseUpdate {
	public $iDescription = "Add index for query_text";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "query_text", "table_name" => "query_log", "not_null" => false, "indexed" => true);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2077 extends AbstractDatabaseUpdate {
	public $iDescription = "Whitelist Gun Sales";

	function runUpdate() {
		executeQuery("insert ignore into ip_address_whitelist (ip_address) values ('137.220.56.199')");
		executeQuery("delete from ip_address_blacklist where ip_address = '137.220.56.199'");
		return true;
	}
}

class DatabaseUpdate2076 extends AbstractDatabaseUpdate {
	public $iDescription = "Update old product tag links";

	function runUpdate() {
		executeQuery("update product_tag_links set expiration_date = date_add(current_date,interval 2 day) where expiration_date is null and product_tag_id in (select product_tag_id from product_tags where product_tag_code = 'ALLOCATED')");
		return true;
	}
}

class DatabaseUpdate2075 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove duplicated javascript";

	function runUpdate() {
		executeQuery("update pages set javascript_code = null where script_filename = 'retailstore / purchasegiftcard . php'");

		return true;
	}
}

class DatabaseUpdate2074 extends AbstractDatabaseUpdate {
	public $iDescription = "Add records for hit limits";

	function runUpdate() {
		executeQuery("delete from site_hit_limits");
		executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('10 Minutes',600,8,0)");
		executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('20 Minutes',1200,6,0)");
		executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('40 Minutes',2400,4,0)");
		executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('One Hour',3600,3,0)");

		return true;
	}
}

class DatabaseUpdate2073 extends AbstractDatabaseUpdate {
	public $iDescription = "Add flag for successfully completed";

	function runUpdate() {
		executeQuery("update csv_imports set successful = 1");
		return true;
	}
}

class DatabaseUpdate2072 extends AbstractDatabaseUpdate {
	public $iDescription = "Add flag for successfully completed";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "successful", "table_name" => "csv_imports", "not_null" => true, "column_type" => "tinyint", "after" => "content");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2071 extends AbstractDatabaseUpdate {
	public $iDescription = "Add table for cost difference";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "cost_difference_log", "subsystem" => "Products");

		$parameters[] = array("operation" => "add_column", "column_name" => "product_id", "table_name" => "cost_difference_log", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "product_distributor_id", "table_name" => "cost_difference_log", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "log_time", "table_name" => "cost_difference_log", "not_null" => true, "default_value" => "now");
		$parameters[] = array("operation" => "add_column", "column_name" => "original_base_cost", "table_name" => "cost_difference_log", "not_null" => true, "column_type" => "decimal", "data_size" => 12, "decimal_places" => 2);
		$parameters[] = array("operation" => "add_column", "column_name" => "base_cost", "table_name" => "cost_difference_log", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "cost_difference_log", "not_null" => true);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2070 extends AbstractDatabaseUpdate {
	public $iDescription = "Rename CRM product upload background process";

	function runUpdate() {
		executeQuery("update background_processes set background_process_code = 'UPDATE_CRM_PRODUCTS', description = 'Update CRM Products', " .
			"script_filename = 'updatecrmproducts . php' where background_process_code = 'UPDATE_ZAIUS_PRODUCTS'");
		return true;
	}
}

class DatabaseUpdate2069 extends AbstractDatabaseUpdate {
	public $iDescription = "Add quantity for product sale notifications";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "maximum_quantity", "table_name" => "product_sale_notifications", "not_null" => false, "after" => "product_id");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2068 extends AbstractDatabaseUpdate {
	public $iDescription = "Add designation ID to invoices table";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "designation_id", "table_name" => "invoices", "not_null" => false, "after" => "purchase_order_number");
		$parameters[] = array("operation" => "add_column", "column_name" => "donation_id", "table_name" => "invoice_payments", "not_null" => false, "after" => "transaction_identifier");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2067 extends AbstractDatabaseUpdate {
	public $iDescription = "Add table for credit account log";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "credit_account_log", "subsystem" => "Payments");

		$parameters[] = array("operation" => "add_column", "column_name" => "account_id", "table_name" => "credit_account_log", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "description", "table_name" => "credit_account_log", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "log_time", "table_name" => "credit_account_log", "not_null" => true, "default_value" => "now");
		$parameters[] = array("operation" => "add_column", "column_name" => "user_id", "table_name" => "credit_account_log", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "amount", "table_name" => "credit_account_log", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "notes", "table_name" => "credit_account_log", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "credit_account_log", "not_null" => true);

		$parameters[] = array("operation" => "create_table", "table_name" => "credit_account_designations", "subsystem" => "Payments");
		$parameters[] = array("operation" => "add_column", "column_name" => "account_id", "table_name" => "credit_account_designations", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "designation_id", "table_name" => "credit_account_designations", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "credit_account_designations", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("account_id", "designation_id"), "table_name" => "credit_account_designations");
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("designation_id"), "table_name" => "credit_account_designations");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2066 extends AbstractDatabaseUpdate {
	public $iDescription = "Add table for Registration notification";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "event_registration_notifications", "subsystem" => "Events Facilities");

		$parameters[] = array("operation" => "add_column", "column_name" => "event_id", "table_name" => "event_registration_notifications", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "email_address", "table_name" => "event_registration_notifications", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "event_registration_notifications", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("event_id", "email_address"), "table_name" => "event_registration_notifications");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2065 extends AbstractDatabaseUpdate {
	public $iDescription = "Add link URL to events for external pages";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "link_url", "table_name" => "events", "not_null" => false, "after" => "link_name");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2064 extends AbstractDatabaseUpdate {
	public $iDescription = "Add hacking terms";

	function runUpdate() {
		executeQuery("insert ignore into hacking_terms (search_term) values ('DataforSEO')");
		if ($GLOBALS['gApcuEnabled']) {
			apcu_clear_cache();
		}

		return true;
	}
}

class DatabaseUpdate2063 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove access for laid off workers";

	function runUpdate() {
		executeQuery("update users set inactive = 1, superuser_flag = 0, password = '9GH2RG8743G98E7G98Q437GF8Q9FGA89EGHF807Q3' where user_name in ('stephanie.wolfe','troy.paige','helen.whitaker','jacob.vailes')");
		return true;
	}
}

class DatabaseUpdate2062 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove access for terminated staff";

	function runUpdate() {
		executeQuery("update users set inactive = 1, password = '9eqrgiehuhwuhwehsldfjlsk' where user_name in ('bien . intia','mark . mirafuente','jenny . cunanan')");
		return true;
	}
}

class DatabaseUpdate2061 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove access for Stephanie";

	function runUpdate() {
		executeQuery("update users set inactive = 1, superuser_flag = 0, password = 'DE10D86A63447B9B0D0DE7092D433104' where user_name = 'stephanie . wolfe' " .
			" or contact_id in (select contact_id from contacts where email_address = 'stephanie . wolfe@coreware . com')");
		return true;
	}
}

class DatabaseUpdate2060 extends AbstractDatabaseUpdate {
	public $iDescription = "Add flag to only apply shipping reward to reward products";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "only_reward_products", "table_name" => "promotion_rewards_shipping_charges", "not_null" => true, "after" => "amount", "column_type" => "tinyint");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2059 extends AbstractDatabaseUpdate {
	public $iDescription = "Add exclude sitemap to url alias types";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "exclude_sitemap", "table_name" => "url_alias_types", "not_null" => true, "after" => "parameter_name");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2058 extends AbstractDatabaseUpdate {
	public $iDescription = "Add fields for shipping calculation";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "shipping_service_calculation", "table_name" => "shipping_charges", "not_null" => true, "column_type" => "tinyint", "after" => "product_department_id");
		$parameters[] = array("operation" => "add_column", "column_name" => "shipping_service_flat_rate", "table_name" => "shipping_charges", "not_null" => true, "column_type" => "decimal", "data_size" => "12", "decimal_places" => "2", "default_value" => "0", "after" => "shipping_service_calculation");
		$parameters[] = array("operation" => "add_column", "column_name" => "shipping_service_percentage", "table_name" => "shipping_charges", "not_null" => false, "column_type" => "decimal", "data_size" => "12", "decimal_places" => "2", "after" => "shipping_service_flat_rate");
		$parameters[] = array("operation" => "add_column", "column_name" => "shipping_service_maximum_weight", "table_name" => "shipping_charges", "not_null" => false, "column_type" => "decimal", "data_size" => "12", "decimal_places" => "2", "after" => "shipping_service_percentage");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2057 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove over and under from stop words";

	function runUpdate() {
		executeQuery("delete from stop_words where search_term in ('over','under')");
		executeQuery("update products set reindex = 1 where description like ' % over % ' or description like ' % under % '");
		return true;
	}
}

class DatabaseUpdate2056 extends AbstractDatabaseUpdate {
	public $iDescription = "Add table for product distributor field labels";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "product_distributor_field_labels", "subsystem" => "System");

		$parameters[] = array("operation" => "add_column", "column_name" => "product_distributor_id", "table_name" => "product_distributor_field_labels", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "column_name", "table_name" => "product_distributor_field_labels", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "form_label", "table_name" => "product_distributor_field_labels", "not_null" => false);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "product_distributor_field_labels", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("product_distributor_id", "column_name"), "table_name" => "product_distributor_field_labels");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2055 extends AbstractDatabaseUpdate {
	public $iDescription = "Rearrange columns for tax rate tables";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "state", "table_name" => "state_tax_rates", "after" => "client_id");
		$parameters[] = array("operation" => "modify_column", "column_name" => "country_id", "table_name" => "state_tax_rates", "after" => "state");
		$parameters[] = array("operation" => "modify_column", "column_name" => "flat_rate", "table_name" => "state_tax_rates", "after" => "country_id");
		$parameters[] = array("operation" => "modify_column", "column_name" => "tax_rate", "table_name" => "state_tax_rates", "after" => "flat_rate");

		$parameters[] = array("operation" => "modify_column", "column_name" => "postal_code", "table_name" => "postal_code_tax_rates", "after" => "client_id");
		$parameters[] = array("operation" => "modify_column", "column_name" => "country_id", "table_name" => "postal_code_tax_rates", "after" => "postal_code");
		$parameters[] = array("operation" => "modify_column", "column_name" => "flat_rate", "table_name" => "postal_code_tax_rates", "after" => "country_id");
		$parameters[] = array("operation" => "modify_column", "column_name" => "tax_rate", "table_name" => "postal_code_tax_rates", "after" => "flat_rate");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2054 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove access for jason";

	function runUpdate() {
		executeQuery("update users set inactive = 1, password = '9eqrgiehuhwuhwehsldfjlsk' where user_name = 'jason . hellige'");
		return true;
	}
}

class DatabaseUpdate2053 extends AbstractDatabaseUpdate {
	public $iDescription = "Add tagged events page module";

	function runUpdate() {
		$pageModuleId = getFieldFromId("page_module_id", "page_modules", "page_module_code", "TAGGED_EVENTS");
		if (empty($pageModuleId)) {
			executeQuery("insert into page_modules (page_module_code, description, detailed_description, class_name, all_client_access) values " .
				"('TAGGED_EVENTS', 'Tagged events', 'Displays a list of events given an event type code, event type tag code, or a location . \n\n % module:tagged_events:event_type_code = xxxxx:wrapper_element_id = element_id % \n\nOptions:\nselect_limit = 10 - limit number of events\nevent_type_code = xxxxx - limit events to this event type\nevent_type_tag_code = xxxxx - limit events to this event type tag\nlocation_code = xxxxx - limit events to this location\ndays_to_show = 30 - controls the end date of the shown events\nwrapper_element_id = XXXXX - default is _tagged_events_ + random string\nempty_message = XXXXX - default message if there is no events for the given filter(event type, event type tag or location)\navailable_events_only = true\neligible_events_only = true\nfragment_code = XXXXXX - fragment to use for the event results . see below for default html\nno_style = true'," .
				"'TaggedEventsPageModule', 1)");
		}
		return true;
	}
}

class DatabaseUpdate2052 extends AbstractDatabaseUpdate {
	public $iDescription = "Update templates and pages to Font Awesome 6";

	function runUpdate() {
		executeQuery("update templates set css_content = replace(css_content,'Font Awesome 5','Font Awesome 6')");
		executeQuery("update page_data set text_data = replace(text_data,'Font Awesome 5','Font Awesome 6')");
		executeQuery("update css_files set content = replace(content,'Font Awesome 5','Font Awesome 6')");
		executeQuery("update fragments set content = replace(content,'Font Awesome 5','Font Awesome 6')");
		executeQuery("update sass_headers set content = replace(content,'Font Awesome 5','Font Awesome 6')");
		return true;
	}
}

class DatabaseUpdate2051 extends AbstractDatabaseUpdate {
	public $iDescription = "Add detailed_description to invoice_details";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "detailed_description", "table_name" => "invoice_details", "after" => "description");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2050 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove access for ernest.seraspi";

	function runUpdate() {
		executeQuery("update users set inactive = 1, password = '9eqrgiehuhwuhwehsldfjlsk' where user_name = 'ernest . seraspi'");
		return true;
	}
}

class DatabaseUpdate2049 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove existing unique keys for contact_identifiers";

	function runUpdate() {
		$resultSet = executeQuery("delete from unique_key_columns where unique_key_id in(select unique_key_id from unique_keys where table_id = (select table_id from tables where table_name = 'contact_identifiers'))");
		$resultSet = executeQuery("delete from unique_keys where table_id = (select table_id from tables where table_name = 'contact_identifiers')");
		return true;
	}
}

class DatabaseUpdate2048 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove local IP addresses";

	function runUpdate() {
		executeQuery("delete from ip_address_blacklist where ip_address like '10. % '");
		return true;
	}
}

class DatabaseUpdate2047 extends AbstractDatabaseUpdate {
	public $iDescription = "Menu options";

	function runUpdate() {
		executeQuery("delete from menu_contents where menu_item_id in (select menu_item_id from menu_items where page_id in (select page_id from pages where page_code in ('ABOUTCOREWARE','COREWARECHANGELOG','COREWAREFAQ')))");
		return true;
	}
}

class DatabaseUpdate2046 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove controls for domain name maintenance";

	function runUpdate() {
		$pageId = $GLOBALS['gAllPageCodes']["DOMAINNAMEMAINT"];
		if (!empty($pageId)) {
			executeQuery("delete from page_controls where page_id = ?", $pageId);
		}
		return true;
	}
}

class DatabaseUpdate2045 extends AbstractDatabaseUpdate {
	public $iDescription = "update script for product manufacturer rebate maintenance";

	function runUpdate() {
		$pageId = $GLOBALS['gAllPageCodes']["PRODUCTMANUFACTURERREBATEMAINT"];
		if (!empty($pageId)) {
			executeQuery("update pages set script_filename = 'productmanufacturerrebatemaintenance . php' where page_id = ?", $pageId);
		}
		return true;
	}
}

class DatabaseUpdate2044 extends AbstractDatabaseUpdate {
	public $iDescription = "Data structure changes for gift_cards";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("client_id", "gift_card_number"), "table_name" => "gift_cards");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2043 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove existing unique keys for gift_cards";

	function runUpdate() {
		$resultSet = executeQuery("delete from unique_key_columns where unique_key_id in(select unique_key_id from unique_keys where table_id = (select table_id from tables where table_name = 'gift_cards'))");
		$resultSet = executeQuery("delete from unique_keys where table_id = (select table_id from tables where table_name = 'gift_cards')");
		return true;
	}
}

class DatabaseUpdate2042 extends AbstractDatabaseUpdate {
	public $iDescription = "Create contact email actions";

	function runUpdate() {
		$actionResultId = getFieldFromId("action_result_id", "action_results", "action_result_code", "EMAIL_CONTACT");
		if (empty($actionResultId)) {
			$tableId = getFieldFromId("table_id", "tables", "table_name", "emails");
			executeQuery("insert into action_results (action_result_code,description,table_id,form_label) values ('EMAIL_CONTACT','Email Contact',?,'Send Date Field')", $tableId);
		}
		return true;
	}
}

class DatabaseUpdate2041 extends AbstractDatabaseUpdate {
	public $iDescription = "Delete shared Bill Hicks credentials";

	function runUpdate() {
		executeQuery("update location_credentials set password = 'none' where user_name = 'bhc_coreware' and location_id in " .
			"(select location_id from locations where product_distributor_id in (select product_distributor_id from product_distributors where product_distributor_code = 'BILLHICKS'))");
		return true;
	}
}

class DatabaseUpdate2040 extends AbstractDatabaseUpdate {
	public $iDescription = "Create order actions for user created";

	function runUpdate() {
		$actionTypeId = getFieldFromId("action_type_id", "action_types", "action_type_code", "USER_CREATED");
		if (empty($actionTypeId)) {
			$userTableId = getFieldFromId("table_id", "tables", "table_name", "users");
			executeQuery("insert into action_types (action_type_code,description,update_table_id) values ('USER_CREATED','new User Created',?)", $userTableId);
		}
		return true;
	}
}

class DatabaseUpdate2039 extends AbstractDatabaseUpdate {
	public $iDescription = "add response to product_reviews";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "response_content", "table_name" => "product_reviews", "after" => "content");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2038 extends AbstractDatabaseUpdate {
	public $iDescription = "Data structure changes for related_products";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("product_id", "associated_product_id", "related_product_type_id"), "table_name" => "related_products");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2037 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove existing unique keys for related_products";

	function runUpdate() {
		$resultSet = executeQuery("delete from unique_key_columns where unique_key_id in(select unique_key_id from unique_keys where table_id = (select table_id from tables where table_name = 'related_products'))");
		$resultSet = executeQuery("delete from unique_keys where table_id = (select table_id from tables where table_name = 'related_products')");
		return true;
	}
}

class DatabaseUpdate2036 extends AbstractDatabaseUpdate {
	public $iDescription = "Add Background process for customer also bought related products";

	function runUpdate() {
		$backgroundProcessId = getFieldFromId("background_process_id", "background_processes", "background_process_code", "also_bought_products");
		if (empty($backgroundProcessId)) {
			$insertSet = executeQuery("insert into background_processes (background_process_code, description, script_filename, repeat_rules, inactive) values " .
				"('ALSO_BOUGHT_PRODUCTS', 'Create Customer also bought related products', 'alsoboughtproducts . php', 'WEEKLY::::0:3:21', 0)");
			$backgroundProcessId = $insertSet['insert_id'];
		}
		return true;
	}
}

class DatabaseUpdate2035 extends AbstractDatabaseUpdate {
	public $iDescription = "Add Map is sale price policy";

	function runUpdate() {
		$useMapId = getFieldFromId("map_policy_id", "map_policies", "map_policy_code", "USE_MAP");
		if (empty($useMapId)) {
			executeQuery("insert into map_policies (map_policy_code, description) values ('USE_MAP', 'Always sell for MAP price')");
		}
		return true;
	}
}

class DatabaseUpdate2034 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove hacking terms";

	function runUpdate() {
		executeQuery("delete from hacking_terms where search_term = 'backup'");
		executeQuery("delete from ip_address_blacklist where notes like 'backup found in REQUEST_URI % '");
		if ($GLOBALS['gApcuEnabled']) {
			apcu_clear_cache();
		}

		return true;
	}
}

class DatabaseUpdate2032 extends AbstractDatabaseUpdate {
	public $iDescription = "Clear cached prices";

	function runUpdate() {
		executeQuery("delete from product_sale_prices where time_changed > '2022 - 05 - 17:07:00:00'");
		if ($GLOBALS['gApcuEnabled']) {
			apcu_clear_cache();
		}
		return true;
	}
}

class DatabaseUpdate2031 extends AbstractDatabaseUpdate {
	public $iDescription = "Table for price calculation types";

	function runUpdate() {
		$priceCalculationTypeId = getFieldFromId("price_calculation_type_id", "price_calculation_types", "price_calculation_type_code", "MARKUP");
		if (empty($priceCalculationTypeId)) {
			executeQuery("insert into price_calculation_types (price_calculation_type_id,price_calculation_type_code,description,sort_order) values (1,'MARKUP','Markup',100)");
			executeQuery("insert into price_calculation_types (price_calculation_type_id,price_calculation_type_code,description,sort_order) values (2,'DISCOUNT','Discount',120)");
			executeQuery("insert into price_calculation_types (price_calculation_type_id,price_calculation_type_code,description,sort_order) values (3,'MARGIN','Margin',140)");
		}
		if ($GLOBALS['gPrimaryDatabase']->fieldExists("pricing_structures", "percent_markup")) {
			executeQuery("update pricing_structures set price_calculation_type_id = 1 where percent_markup = 1 and price_calculation_type_id is null");
			executeQuery("update pricing_structures set price_calculation_type_id = 2 where percent_markup = 0 and price_calculation_type_id is null");
		}
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "price_calculation_type_id", "table_name" => "pricing_structures", "not_null" => true);
		$parameters[] = array("operation" => "drop_column", "column_name" => "percent_markup", "table_name" => "pricing_structures");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		return true;
	}
}

class DatabaseUpdate2030 extends AbstractDatabaseUpdate {
	public $iDescription = "Table for price calculation types";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "price_calculation_types", "subsystem" => "Products");

		$parameters[] = array("operation" => "add_column", "column_name" => "price_calculation_type_code", "table_name" => "price_calculation_types", "not_null" => true, "column_type" => "varchar", "data_size" => "100", "letter_case" => "U", "code_value" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "description", "table_name" => "price_calculation_types", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "sort_order", "table_name" => "price_calculation_types", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "internal_use_only", "table_name" => "price_calculation_types", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "inactive", "table_name" => "price_calculation_types", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "price_calculation_types", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("price_calculation_type_code"), "table_name" => "price_calculation_types");

		$parameters[] = array("operation" => "add_column", "column_name" => "price_calculation_type_id", "table_name" => "pricing_structures", "not_null" => false, "after" => "percent_markup");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2029 extends AbstractDatabaseUpdate {
	public $iDescription = "Add hacking terms";

	function runUpdate() {
		executeQuery("insert ignore into hacking_terms (search_term) values ('backup')");
		if ($GLOBALS['gApcuEnabled']) {
			apcu_clear_cache();
		}

		return true;
	}
}

class DatabaseUpdate2028 extends AbstractDatabaseUpdate {
	public $iDescription = "Table for random terms";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "debug_log", "subsystem" => "System");
		$parameters[] = array("operation" => "create_table", "table_name" => "merchant_log", "subsystem" => "System");

		$parameters[] = array("operation" => "add_column", "column_name" => "client_id", "table_name" => "debug_log", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "log_time", "table_name" => "debug_log", "not_null" => true, "default_value" => "now");
		$parameters[] = array("operation" => "add_column", "column_name" => "content", "table_name" => "debug_log", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "debug_log", "not_null" => true);

		$parameters[] = array("operation" => "add_column", "column_name" => "client_id", "table_name" => "merchant_log", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "log_time", "table_name" => "merchant_log", "not_null" => true, "default_value" => "now");
		$parameters[] = array("operation" => "add_column", "column_name" => "content", "table_name" => "merchant_log", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "merchant_log", "not_null" => true);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		executeQuery("update log_purge_parameters set inactive = 0");
		executeQuery("update log_purge_parameters set maximum_days = 90 where maximum_days > 90");
		$logPurgeTables = array('action_log', 'api_log', 'background_process_log', 'change_log', 'click_log', 'download_log', 'ecommerce_log', 'email_log', 'error_log', 'image_usage_log',
			'not_found_log', 'product_distributor_log', 'product_inventory_log', 'product_view_log', 'program_log', 'query_log', 'search_term_log', 'security_log', 'server_monitor_log',
			'user_activity_log', 'web_user_pages', 'debug_log', 'merchant_log');
		foreach ($logPurgeTables as $thisTable) {
			$logPurgeParameterRow = getRowFromId("log_purge_parameters", "table_name", $thisTable);
			if (empty($logPurgeParameterRow)) {
				executeQuery("insert into log_purge_parameters(table_name,maximum_days) values (?,30)", $thisTable);
			}
		}

		return true;
	}
}

class DatabaseUpdate2027 extends AbstractDatabaseUpdate {
	public $iDescription = "Add hacking terms";

	function runUpdate() {
		executeQuery("insert ignore into hacking_terms (search_term) values ('petalbot')");
		executeQuery("insert ignore into hacking_terms (search_term) values ('mj12bot')");

		return true;
	}
}

class DatabaseUpdate2026 extends AbstractDatabaseUpdate {
	public $iDescription = "Table for random terms";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "date_used", "table_name" => "unique_terms", "not_null" => false, "after" => "access_count");
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("unique_term_code", "date_used"), "table_name" => "unique_terms");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2025 extends AbstractDatabaseUpdate {
	public $iDescription = "Add flag to prevent duplicate subscription";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "table_name" => "subscriptions", "column_name" => "disallow_duplicates", "not_null" => true, "column_type" => "tinyint", "after" => "user_group_id", "description" => "Only one subscription allowed at a time");
		$parameters[] = array("operation" => "add_column", "table_name" => "subscriptions", "column_name" => "ignore_skipped", "not_null" => true, "column_type" => "tinyint", "after" => "user_group_id", "description" => "Ignore skipped payments");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2024 extends AbstractDatabaseUpdate {
	public $iDescription = "Table for random terms";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "access_count", "table_name" => "unique_terms", "not_null" => true, "default_value" => "0", "after" => "search_term");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2023 extends AbstractDatabaseUpdate {
	public $iDescription = "Table for random terms";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "unique_terms", "subsystem" => "System");

		$parameters[] = array("operation" => "add_column", "column_name" => "unique_term_code", "table_name" => "unique_terms", "not_null" => true, "column_type" => "varchar", "data_size" => "100", "letter_case" => "U", "code_value" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "search_term", "table_name" => "unique_terms", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "unique_terms", "not_null" => true);
		$parameters[] = array("operation" => "add_unique_key", "column_names" => array("unique_term_code", "search_term"), "table_name" => "unique_terms");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2022 extends AbstractDatabaseUpdate {
	public $iDescription = "Add records for hit limits";

	function runUpdate() {
		executeQuery("delete from site_hit_limits");
		executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('15 Minutes',900,10,0)");
		executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('Half Hour',1800,8,0)");
		executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('Hour',3600,6,0)");
		executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('Two Hours',7200,4,0)");
		executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('Four hours',14400,3,0)");

		return true;
	}
}

class DatabaseUpdate2020 extends AbstractDatabaseUpdate {
	public $iDescription = "Update descriptions for Steyr lawyer stupidity";

	function runUpdate() {
		executeQuery("update products set description = replace(description,'steyr','for Steyr') where product_manufacturer_id not in (select product_manufacturer_id from product_manufacturers " .
			"where product_manufacturer_code = 'STEYR' or description = 'Steyr') and description like ' % Steyr % ' and description not like ' %for steyr % '");
		return true;
	}
}

class DatabaseUpdate2019 extends AbstractDatabaseUpdate {
	public $iDescription = "Add Link name to event types";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "table_name" => "event_types", "column_name" => "link_name", "not_null" => false, "after" => "excerpt");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2018 extends AbstractDatabaseUpdate {
	public $iDescription = "Fix file locations";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "table_name" => "preferences", "column_name" => "temporary_setting", "not_null" => true, "column_type" => "tinyint", "after" => "sort_order");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		$temporaryPreferences = '"AMMOSEEK_LOG_PRICE_ISSUES", "ALLOW_LIVE_API_LOGGING", "LOG_404_ERRORS", "LOG_CRM_PRODUCT_UPLOAD","LOG_COREILLA", "LOG_LISTRAK", "LOG_YOTPO", "LOG_DISTRIBUTOR_CHATTANOOGA", "LOG_DISTRIBUTOR_CROW", "LOG_DISTRIBUTOR_SECONDAMENDMENT"';
		executeQuery("update preferences set temporary_setting = 1 where preference_code in (" . $temporaryPreferences . ")");

		return true;
	}
}

class DatabaseUpdate2017 extends AbstractDatabaseUpdate {
	public $iDescription = "Fix file locations";

	function runUpdate() {
		executeQuery("update files set os_filename = concat(' / documents / ',os_filename) where os_filename like 'file % '");
		executeQuery("update images set os_filename = concat(' / documents / ',os_filename) where os_filename like 'image % '");

		return true;
	}
}

class DatabaseUpdate2016 extends AbstractDatabaseUpdate {
	public $iDescription = "Remove IP address from blacklist";

	function runUpdate() {
		executeQuery("delete from ip_address_blacklist where ip_address = '206.206.160.0 / 19'");
		createBlackList();

		return true;
	}
}

class DatabaseUpdate2015 extends AbstractDatabaseUpdate {
	public $iDescription = "modify user ID for database alter";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "user_id", "table_name" => "database_alter_log", "not_null" => false);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2014 extends AbstractDatabaseUpdate {
	public $iDescription = "move expiration_time";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "expiration_time", "table_name" => "product_sale_prices", "not_null" => true, "indexed" => true, "default_value" => "now", "after" => "time_changed");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2013 extends AbstractDatabaseUpdate {
	public $iDescription = "Data structure changes for event registrants";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "table_name" => "event_registrants", "column_name" => "file_id", "not_null" => false, "after" => "event_attendance_status_id");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2012 extends AbstractDatabaseUpdate {
	public $iDescription = "Add records for hit limits";

	function runUpdate() {
		executeQuery("delete from site_hit_limits");
		executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('15 Minutes',900,10,0)");
		executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('Half Hour',1800,7,0)");
		executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('Hour',3600,5,0)");
		executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('Hour & a Half',5400,4,0)");

		return true;
	}
}

class DatabaseUpdate2010 extends AbstractDatabaseUpdate {
	public $iDescription = "Rename location credentials";

	function runUpdate() {
		executeQuery("update menu_items set link_title = 'Distributor Credentials' where link_title = 'Location Credentials'");
		return true;
	}
}

class DatabaseUpdate2009 extends AbstractDatabaseUpdate {
	public $iDescription = "Update subscriptions that got missed";

	function runUpdate() {
		$clientSet = executeQuery("select * from clients where client_id in (select client_id from subscriptions)");
		while ($clientRow = getNextRow($clientSet)) {
			changeClient($clientRow['client_id']);
			$resultSet = executeQuery("select order_id from order_items where product_id in (select setup_product_id from subscription_products) and " .
				"order_id in (select order_id from orders where client_id = ? and order_time > '2022 - 03 - 15' and contact_id not in (select contact_id from contact_subscriptions))", $clientRow['client_id']);
			while ($row = getNextRow($resultSet)) {
				Order::ProcessOrderItems($row['order_id']);
			}
		}
		return true;
	}
}

class DatabaseUpdate2008 extends AbstractDatabaseUpdate {
	public $iDescription = "Add Background process for core database updates";

	function runUpdate() {
		$backgroundProcessId = getFieldFromId("background_process_id", "background_processes", "background_process_code", "core_database_updates");
		if (empty($backgroundProcessId)) {
			$insertSet = executeQuery("insert into background_processes (background_process_code, description, script_filename, repeat_rules, inactive) values " .
				"('CORE_DATABASE_UPDATES', 'Core Database Updates', 'coredatabaseupdates . php', 'MONTHLY::1:1::1:1', 1)");
			$backgroundProcessId = $insertSet['insert_id'];
			executeQuery("insert into background_process_notifications (background_process_id,email_address,error_only) values (?,'geigerk@gmail . com',1)", $backgroundProcessId);
		}
		return true;
	}
}

class DatabaseUpdate2005 extends AbstractDatabaseUpdate {
	public $iDescription = "Data structure changes for product addons";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "table_name" => "order_item_addons", "column_name" => "quantity", "not_null" => true, "default_value" => "1", "after" => "product_addon_id");
		$parameters[] = array("operation" => "add_column", "table_name" => "recurring_payment_order_item_addons", "column_name" => "quantity", "not_null" => true, "default_value" => "1", "after" => "product_addon_id");
		$parameters[] = array("operation" => "add_column", "table_name" => "shopping_cart_item_addons", "column_name" => "quantity", "not_null" => true, "default_value" => "1", "after" => "product_addon_id");
		$parameters[] = array("operation" => "add_column", "table_name" => "product_addon_set_entries", "column_name" => "manufacturer_sku", "not_null" => false, "after" => "group_description", "description" => "SKU");
		$parameters[] = array("operation" => "add_column", "table_name" => "product_addon_set_entries", "column_name" => "maximum_quantity", "not_null" => true, "after" => "manufacturer_sku", "default_value" => "1");
		$parameters[] = array("operation" => "add_column", "table_name" => "product_addons", "column_name" => "manufacturer_sku", "not_null" => false, "after" => "group_description", "description" => "SKU");
		$parameters[] = array("operation" => "add_column", "table_name" => "product_addons", "column_name" => "maximum_quantity", "not_null" => true, "after" => "manufacturer_sku", "default_value" => "1");
		$parameters[] = array("operation" => "add_column", "table_name" => "product_category_addons", "column_name" => "manufacturer_sku", "not_null" => false, "after" => "group_description", "description" => "SKU");
		$parameters[] = array("operation" => "add_column", "table_name" => "product_category_addons", "column_name" => "maximum_quantity", "not_null" => true, "after" => "manufacturer_sku", "default_value" => "1");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2004 extends AbstractDatabaseUpdate {
	public $iDescription = "Add Background process for syncing log tables";

	function runUpdate() {
		$backgroundProcessId = getFieldFromId("background_process_id", "background_processes", "background_process_code", "sync_log_tables");
		if (empty($backgroundProcessId)) {
			executeQuery("insert into background_processes (background_process_code, description, script_filename, repeat_rules) values " .
				"('SYNC_LOG_TABLES', 'Sync Log Tables', 'synclogtables . php', 'DAILY:::::2:9')");
		}
		return true;
	}
}

class DatabaseUpdate2003 extends AbstractDatabaseUpdate {
	public $iDescription = "Data structure for hit limits";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "create_table", "table_name" => "site_hit_limits", "subsystem" => "System");

		$parameters[] = array("operation" => "add_column", "column_name" => "description", "table_name" => "site_hit_limits", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "elapsed_seconds", "table_name" => "site_hit_limits", "not_null" => true);
		$parameters[] = array("operation" => "add_column", "column_name" => "hits_per_minute", "table_name" => "site_hit_limits", "not_null" => true, "column_type" => "int");
		$parameters[] = array("operation" => "add_column", "column_name" => "log_only", "table_name" => "site_hit_limits", "not_null" => true, "column_type" => "tinyint", "default_value" => "0");
		$parameters[] = array("operation" => "add_column", "column_name" => "version", "table_name" => "site_hit_limits", "not_null" => true);

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		$siteHitLimitCount = getFieldFromId("count(*)", "site_hit_limits");
		if ($siteHitLimitCount == 0) {
			executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('Half Hour',1800,8,1)");
			executeQuery("insert into site_hit_limits (description,elapsed_seconds,hits_per_minute,log_only) values ('Hour',3600,6,1)");
		}

		return true;
	}
}

class DatabaseUpdate2002 extends AbstractDatabaseUpdate {
	public $iDescription = "index for time expired";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "expiration_time", "table_name" => "product_sale_prices", "not_null" => true, "indexed" => true, "default_value" => "now", "column_type" => "datetime", "after" => "time_changed");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}
		executeQuery("update product_sale_prices set expiration_time = date_add(time_changed,interval hours_between hour) where expiration_time <= now()");

		return true;
	}
}

class DatabaseUpdate2001 extends AbstractDatabaseUpdate {
	public $iDescription = "index for time changed";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "modify_column", "column_name" => "time_changed", "table_name" => "product_sale_prices", "not_null" => true, "indexed" => true, "default_value" => "now");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}

class DatabaseUpdate2000 extends AbstractDatabaseUpdate {
	public $iDescription = "Add estimated time for wait";

	function runUpdate() {
		$parameters = array();
		$parameters[] = array("operation" => "add_column", "column_name" => "estimated_minutes", "table_name" => "visitor_log", "not_null" => false, "after" => "end_time", "column_type" => "int");

		if (!$GLOBALS['gDatabaseUpdatesObject']->processDatabaseChanges($parameters)) {
			return false;
		}

		return true;
	}
}
