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

/**
 * class Database
 *
 * Database utility class. Handles the connection to the database, transaction handling, some database reflection,
 * some data conversions to specific dbtypes, execution of passed in queries, database error logging
 *
 * @author Kim D Geiger
 */
class Database {

	static private $iTableColumns = array();
	static private $iTablesFound = false;
	static private $iViewsFound = false;
	static private $iChangeLogForeignKeys = array();
	static private $iIgnoreErrorList = false;
    static private $iIgnoreErrorCounts = array();
	static private $iFieldForeignTableNames = array();
	static private $iForeignTableFields = array();
	static private $iAddNewInfo = array();
	static private $iCachedFieldValues = false;

    private $statementPoolSize = 50;  // Adjust this value based on your needs.

	/**
	 *    The name of the database
	 *
	 * @access private
	 * @var string
	 */
	var $iDatabaseName = "";
	/**
	 *    This is the actual DB connection object.
	 *
	 * @access private
	 * @var string
	 */
	var $iDBConnection = null;
	/**
	 *    Store the last error logged so that if the code has to rollback transactions, it can rewrite the error to the error log.
	 *    If this is not done, the error logged will also get rolled back and there will be no record of the error.
	 *
	 * @access private
	 * @var string
	 */
	var $iLastErrorLogged = "";
	var $iLastEcommerceError = false;
	/**
	 *    Store the previous errors logged so that duplicate error messages being written to the error log and emails
	 *    being send about those errors can be minimized.
	 *
	 * @access private
	 * @var array
	 */
	var $iLoggedErrorQueryText = array();
	var $iLoggedErrorMessages = array();
	/**
	 *    Occasionally, the code anticipates an error and does not want to log it. This allows the code to set the database
	 *    to ignore errors for a time. This should be used sparingly and cautiously.
	 *
	 * @access private
	 * @var boolean
	 */
	var $iIgnoreError = false;
	/**
	 *    The last error that the database returned.
	 *
	 * @access private
	 * @var string
	 */
	var $iErrorMessage = "";
	var $iReadonlyConnection = false;
	var $iQueryStatements = array();
	protected $iLastQueryLogEntryTime;
	private $iTablePrimaryKeys = array();

	/**
	 *    Database Class Constructor
	 *
	 *    Instantiates the Database class and initilizes a connection object
	 *    to the server by passing the server name, user and password, and database name. Typically, the server name
	 *    will be localhost, but the constructor allows the code to specify a different database server
	 *
	 * @param string $databaseName
	 * @param string $user
	 * @param string $password
	 * @param string $server
	 */
	function __construct($databaseName, $user, $password, $server = "localhost", $readOnly = false) {
		$this->iDatabaseName = $databaseName;
		self::$iTableColumns = getCachedData("table_columns", $this->iDatabaseName, true);
		if (!is_array(self::$iTableColumns)) {
			self::$iTableColumns = array();
		}
		mysqli_report(MYSQLI_REPORT_OFF);
		$this->iDBConnection = new mysqli($server, $user, $password, $databaseName);
		$GLOBALS['gDatabaseConnectionCount']++;
		if ($GLOBALS['gDatabaseConnectionCount'] > 2) {
			$GLOBALS['gPrimaryDatabase']->logError("TOO MANY CONNECTIONS: " . jsonEncode($_SERVER));
		}
		if ($this->iDBConnection->connect_errno) {
			if ($GLOBALS['gDevelopmentServer']) {
				echo $this->iDBConnection->connect_errno . "<br>";
				echo $this->iDBConnection->connect_error . "<br>";
			}
			return false;
		}
		$this->iDBConnection->set_charset("utf8mb4");
		if ($readOnly) {
			$this->iReadonlyConnection = true;
		} else {
			self::$iChangeLogForeignKeys["accounts"] = array("table_name" => "accounts", "foreign_key" => "contact_id", "foreign_table" => "contacts");
			self::$iChangeLogForeignKeys["addresses"] = array("table_name" => "addresses", "foreign_key" => "contact_id", "foreign_table" => "contacts");
			self::$iChangeLogForeignKeys["contact_categories"] = array("table_name" => "contact_categories", "foreign_key" => "contact_id", "foreign_table" => "contacts");
			self::$iChangeLogForeignKeys["contact_subscriptions"] = array("table_name" => "contact_subscriptions", "foreign_key" => "contact_id", "foreign_table" => "contacts");
			self::$iChangeLogForeignKeys["contact_emails"] = array("table_name" => "contact_emails", "foreign_key" => "contact_id", "foreign_table" => "contacts");
			self::$iChangeLogForeignKeys["contact_identifiers"] = array("table_name" => "contact_identifiers", "foreign_key" => "contact_id", "foreign_table" => "contacts");
			self::$iChangeLogForeignKeys["contact_mailing_lists"] = array("table_name" => "contact_mailing_lists", "foreign_key" => "contact_id", "foreign_table" => "contacts");
			self::$iChangeLogForeignKeys["custom_field_data"] = array("table_name" => "custom_field_data", "foreign_key" => "primary_identifier", "foreign_table" => "contacts");
			self::$iChangeLogForeignKeys["recurring_payments"] = array("table_name" => "recurring_payments", "foreign_key" => "contact_id", "foreign_table" => "contacts");
			self::$iChangeLogForeignKeys["custom_field_choices"] = array("table_name" => "custom_field_choices", "foreign_key" => "custom_field_id", "foreign_table" => "custom_fields");
			self::$iChangeLogForeignKeys["custom_field_controls"] = array("table_name" => "custom_field_controls", "foreign_key" => "custom_field_id", "foreign_table" => "custom_fields");
			self::$iChangeLogForeignKeys["designation_group_links"] = array("table_name" => "designation_group_links", "foreign_key" => "designation_id", "foreign_table" => "designations");
			self::$iChangeLogForeignKeys["event_registrants"] = array("table_name" => "event_registrants", "foreign_key" => "event_id", "foreign_table" => "events");
			self::$iChangeLogForeignKeys["help_desk_entry_list_items"] = array("table_name" => "help_desk_entry_list_items", "foreign_key" => "help_desk_entry_id", "foreign_table" => "help_desk_entries");
			self::$iChangeLogForeignKeys['invoice_payments'] = array("table_name" => 'invoice_payments', "foreign_key" => 'invoice_id', "foreign_table" => 'invoices');
			self::$iChangeLogForeignKeys['invoice_details'] = array("table_name" => 'invoice_details', "foreign_key" => 'invoice_id', "foreign_table" => 'invoices');
			self::$iChangeLogForeignKeys["phone_numbers"] = array("table_name" => "phone_numbers", "foreign_key" => "contact_id", "foreign_table" => "contacts");
			self::$iChangeLogForeignKeys["order_files"] = array("table_name" => "order_files", "foreign_key" => "order_id", "foreign_table" => "orders");
			self::$iChangeLogForeignKeys["order_items"] = array("table_name" => "order_items", "foreign_key" => "order_id", "foreign_table" => "orders");
			self::$iChangeLogForeignKeys["order_notes"] = array("table_name" => "order_notes", "foreign_key" => "order_id", "foreign_table" => "orders");
			self::$iChangeLogForeignKeys["order_payments"] = array("table_name" => "order_payments", "foreign_key" => "order_id", "foreign_table" => "orders");
			self::$iChangeLogForeignKeys["order_shipments"] = array("table_name" => "order_shipments", "foreign_key" => "order_id", "foreign_table" => "orders");
			self::$iChangeLogForeignKeys["order_directive_actions"] = array("table_name" => "order_directive_actions", "foreign_key" => "order_directive_id", "foreign_table" => "order_directives");
			self::$iChangeLogForeignKeys["order_directive_conditions"] = array("table_name" => "order_directive_conditions", "foreign_key" => "order_directive_id", "foreign_table" => "order_directives");
			self::$iChangeLogForeignKeys["page_access"] = array("table_name" => "page_access", "foreign_key" => "page_id", "foreign_table" => "pages");
			self::$iChangeLogForeignKeys["page_controls"] = array("table_name" => "page_controls", "foreign_key" => "page_id", "foreign_table" => "pages");
			self::$iChangeLogForeignKeys["page_data"] = array("table_name" => "page_data", "foreign_key" => "page_id", "foreign_table" => "pages");
			self::$iChangeLogForeignKeys["page_functions"] = array("table_name" => "page_functions", "foreign_key" => "page_id", "foreign_table" => "pages");
			self::$iChangeLogForeignKeys["page_text_chunks"] = array("table_name" => "page_text_chunks", "foreign_key" => "page_id", "foreign_table" => "pages");
			self::$iChangeLogForeignKeys["page_meta_tags"] = array("table_name" => "page_meta_tags", "foreign_key" => "page_id", "foreign_table" => "pages");
			self::$iChangeLogForeignKeys["page_notifications"] = array("table_name" => "page_notifications", "foreign_key" => "page_id", "foreign_table" => "pages");
			self::$iChangeLogForeignKeys['shipping_charge_product_categories'] = array("table_name" => 'shipping_charge_product_categories', "foreign_key" => 'shipping_charge_id', "foreign_table" => 'shipping_charges');
			self::$iChangeLogForeignKeys['shipping_charge_product_departments'] = array("table_name" => 'shipping_charge_product_departments', "foreign_key" => 'shipping_charge_id', "foreign_table" => 'shipping_charges');
			self::$iChangeLogForeignKeys['shipping_charge_product_types'] = array("table_name" => 'shipping_charge_product_types', "foreign_key" => 'shipping_charge_id', "foreign_table" => 'shipping_charges');
			self::$iChangeLogForeignKeys['shipping_locations'] = array("table_name" => 'shipping_locations', "foreign_key" => 'shipping_charge_id', "foreign_table" => 'shipping_charges');
			self::$iChangeLogForeignKeys['shipping_rates'] = array("table_name" => 'shipping_rates', "foreign_key" => 'shipping_charge_id', "foreign_table" => 'shipping_charges');
			self::$iChangeLogForeignKeys['product_distributor_shipping_charges'] = array("table_name" => 'product_distributor_shipping_charges', "foreign_key" => 'shipping_charge_id', "foreign_table" => 'shipping_charges');
			self::$iChangeLogForeignKeys["product_bulk_packs"] = array("table_name" => "product_bulk_packs", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_category_links"] = array("table_name" => "product_category_links", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_change_details"] = array("table_name" => "product_change_details", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_contributors"] = array("table_name" => "product_contributors", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_data"] = array("table_name" => "product_data", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_addons"] = array("table_name" => "product_addons", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_facet_values"] = array("table_name" => "product_facet_values", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_images"] = array("table_name" => "product_images", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_inventories"] = array("table_name" => "product_inventories", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_manufacturer_cannot_sell_distributors"] = array("table_name" => "product_manufacturer_cannot_sell_distributors", "foreign_key" => "product_manufacturer_id", "foreign_table" => "product_manufacturers");
			self::$iChangeLogForeignKeys["product_manufacturer_distributor_dropships"] = array("table_name" => "product_manufacturer_distributor_dropships", "foreign_key" => "product_manufacturer_id", "foreign_table" => "product_manufacturers");
			self::$iChangeLogForeignKeys["product_manufacturer_dropship_exclusions"] = array("table_name" => "product_manufacturer_dropship_exclusions", "foreign_key" => "product_manufacturer_id", "foreign_table" => "product_manufacturers");
			self::$iChangeLogForeignKeys["product_manufacturer_images"] = array("table_name" => "product_manufacturer_images", "foreign_key" => "product_manufacturer_id", "foreign_table" => "product_manufacturers");
			self::$iChangeLogForeignKeys["product_manufacturer_map_holidays"] = array("table_name" => "product_manufacturer_map_holidays", "foreign_key" => "product_manufacturer_id", "foreign_table" => "product_manufacturers");
			self::$iChangeLogForeignKeys["product_prices"] = array("table_name" => "product_prices", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_restrictions"] = array("table_name" => "product_restrictions", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_reviews"] = array("table_name" => "product_reviews", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_serial_numbers"] = array("table_name" => "product_serial_numbers", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_shipping_carriers"] = array("table_name" => "product_shipping_carriers", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_shipping_methods"] = array("table_name" => "product_shipping_methods", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["related_products"] = array("table_name" => "related_products", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_tag_links"] = array("table_name" => "product_tag_links", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_vendors"] = array("table_name" => "product_vendors", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["product_distributor_dropship_prohibitions"] = array("table_name" => "product_distributor_dropship_prohibitions", "foreign_key" => "product_id", "foreign_table" => "products");
			self::$iChangeLogForeignKeys["recurring_payment_order_items"] = array("table_name" => "recurring_payment_order_items", "foreign_key" => "recurring_payment_id", "foreign_table" => "recurring_payments");
			self::$iChangeLogForeignKeys["tasks"] = array("table_name" => "tasks", "foreign_key" => "contact_id", "foreign_table" => "contacts");
			self::$iChangeLogForeignKeys["user_access"] = array("table_name" => "user_access", "foreign_key" => "user_id", "foreign_table" => "users");
			self::$iChangeLogForeignKeys["user_attributions"] = array("table_name" => "user_attributions", "foreign_key" => "user_id", "foreign_table" => "users");
			self::$iChangeLogForeignKeys["user_function_uses"] = array("table_name" => "user_function_uses", "foreign_key" => "user_id", "foreign_table" => "users");
			self::$iChangeLogForeignKeys["user_group_members"] = array("table_name" => "user_group_members", "foreign_key" => "user_id", "foreign_table" => "users");
			self::$iChangeLogForeignKeys["user_checklist_items"] = array("table_name" => "user_checklist_items", "foreign_key" => "user_checklist_id", "foreign_table" => "user_checklists");
			self::$iChangeLogForeignKeys["email_copies"] = array("table_name" => "email_copies", "foreign_key" => "email_id", "foreign_table" => "emails");
			self::$iChangeLogForeignKeys["template_banners"] = array("table_name" => "template_banners", "foreign_key" => "template_id", "foreign_table" => "templates");
			self::$iChangeLogForeignKeys["template_images"] = array("table_name" => "template_images", "foreign_key" => "template_id", "foreign_table" => "templates");
			self::$iChangeLogForeignKeys["template_fragments"] = array("table_name" => "template_fragments", "foreign_key" => "template_id", "foreign_table" => "templates");
			self::$iChangeLogForeignKeys["template_menus"] = array("table_name" => "template_menus", "foreign_key" => "template_id", "foreign_table" => "templates");
			self::$iChangeLogForeignKeys["template_text_chunks"] = array("table_name" => "template_text_chunks", "foreign_key" => "template_id", "foreign_table" => "templates");
		}
		if (self::$iIgnoreErrorList === false) {
			self::$iIgnoreErrorList = array();
			self::$iIgnoreErrorList[] = "Unknown or incorrect time zone";
			self::$iIgnoreErrorList[] = "JPEG library reports unrecoverable error";
			self::$iIgnoreErrorList[] = "ftp_login";
			self::$iIgnoreErrorList[] = "Entering passive mode";
			self::$iIgnoreErrorList[] = "ftp_get";
			self::$iIgnoreErrorList[] = "seconds (measured here)";
			self::$iIgnoreErrorList[] = "ftp_chdir";
			self::$iIgnoreErrorList[] = "SSL: Connection reset by peer";
			self::$iIgnoreErrorList[] = "ftp_close";
			self::$iIgnoreErrorList[] = "doRequest";
			self::$iIgnoreErrorList[] = "SHIPMENT.INVALID_PARAMS";
			self::$iIgnoreErrorList[] = "imap_open";
			self::$iIgnoreErrorList[] = "curl_getinfo";
			self::$iIgnoreErrorList[] = "slick is not a function";
			self::$iIgnoreErrorList[] = "r.shift is not a function";
			self::$iIgnoreErrorList[] = "failed to open stream";
			self::$iIgnoreErrorList[] = "Filename cannot be empty";
			self::$iIgnoreErrorList[] = "fk_fe7c781598365e0fe32fd5a0a7111bfd";
			self::$iIgnoreErrorList[] = "fk_f8a5b2d4692d92a6119d196b47a23a2d";
			self::$iIgnoreErrorList[] = "is not a valid JPEG file";
			self::$iIgnoreErrorList[] = "Error Fetching http headers";
			self::$iIgnoreErrorList[] = "php_connect_nonb";
			self::$iIgnoreErrorList[] = "filemtime(): stat failed";
			self::$iIgnoreErrorList[] = "object(EasyPost\Shipment";
			self::$iIgnoreErrorList[] = "for key 'uk_7ca6bd623d0eec2c45a62d392ee3c13a'";
			self::$iIgnoreErrorList[] = "Deadlock found when trying to get lock";
			self::$iIgnoreErrorList[] = "Could not connect to host";
			self::$iIgnoreErrorList[] = "unlink(/var/www/html/cache/";
			self::$iIgnoreErrorList[] = "temporary_products_";
            self::$iIgnoreErrorList[] = "Can't create more than max_prepared_stmt_count statements";
		}
		if (self::$iCachedFieldValues === false) {
			self::$iCachedFieldValues = array();
			$this->addCachedField("product_tag_id", "product_tags", "product_tag_code", true);
			$this->addCachedField("product_distributor_id", "locations", "location_id");
			$this->addCachedField("description", "locations", "location_id");
			$this->addCachedField("product_type_code", "product_types", "product_type_id");
			$this->addCachedField("subsystem_id", "subsystems", "subsystem_code");
			$this->addCachedField("preference_id", "preferences", "preference_code");
			$this->addCachedField("client_code", "clients", "client_id");
			$this->addCachedField("product_id", "events", "event_id");
			$this->addCachedField("event_type_id", "events", "event_id");
			$this->addCachedField("country_name", "countries", "country_id");
			$this->addCachedField("display_color", "event_types", "event_type_id");
			$this->addCachedField("email_credential_id", "emails", "email_id");
			$this->addCachedField("description", "user_groups", "user_group_id");
			$this->addCachedField("description", "help_desk_types", "help_desk_type_id");
			$this->addCachedField("description", "help_desk_categories", "help_desk_category_id");
			$this->addCachedField("description", "help_desk_statuses", "help_desk_status_id");
			$this->addCachedField("description", "user_types", "user_type_id");
			$this->addCachedField("contact_id", "product_manufacturers", "product_manufacturer_id");
			$this->addCachedField("description", "product_distributors", "product_distributor_id");
			$this->addCachedField("description", "locations", "location_id");
			$this->addCachedField("language_id", "languages", "iso_code", true);
			$this->addCachedField("country_id", "countries", "country_code", true);
			$this->addCachedField("email_id", "emails", "email_code", true);
			$this->addCachedField("table_name", "tables", "table_id");
		}
	}

	public function addCachedField($fieldName, $tableName, $idName, $includeNotFound = false) {
		self::$iCachedFieldValues[md5($fieldName . ":" . $tableName . ":" . $idName)] = array("field_name" => $fieldName, "table_name" => $tableName, "id_name" => $idName, "include_not_found" => $includeNotFound);
	}

	public static function updateDatabase($parameters) {
		$returnOutput = array();
		$errorOutput = array();
		$databaseDefinitionId = getFieldFromId("database_definition_id", "database_definitions", "database_name", $GLOBALS['gPrimaryDatabase']->getName());
		if (empty($parameters)) {
			return true;
		}
		foreach ($parameters as $changeInfo) {
			switch ($changeInfo['operation']) {
				case "create_view":
					$resultSet = executeQuery("show tables like '" . $changeInfo['view_name'] . "'");
					if ($row = getNextRow($resultSet)) {
						break;
					}
					if (empty($changeInfo['description'])) {
						$changeInfo['description'] = ucwords(strtolower(str_replace("_", " ", $changeInfo['view_name'])));
					}
					if (substr($changeInfo['description'], -3) == " Id") {
						$changeInfo['description'] = substr($changeInfo['description'], 0, -3);
					}
					$subsystemId = getFieldFromId("subsystem_id", "subsystems", "subsystem_code", strtoupper((substr($changeInfo['subsystem'], 0, 5) == "CORE_" ? "" : "CORE_") . makeCode($changeInfo['subsystem'])));
					if (empty($subsystemId)) {
						$errorOutput[] = "Invalid subsystem: " . $changeInfo['subsystem'];
						break;
					}
					if (empty($changeInfo['table_list']) && empty($changeInfo['full_query_text'])) {
						$errorOutput[] = "Table list or full query text required for view";
						break;
					}
					$tableList = explode(",", $changeInfo['table_list']);
					foreach ($tableList as $tableName) {
						if (!empty($tableName)) {
							$referencedTableId = getFieldFromId("table_id", "tables", "table_name", $tableName);
							if (empty($referencedTableId)) {
								$errorOutput[] = "Table " . $tableName . " does not exist.";
								break;
							}
						}
					}
					$resultSet = executeQuery("insert into tables (database_definition_id,table_name,description,detailed_description,subsystem_id,table_view,query_string,query_text,full_query_text) values (?,?,?,?,?,1,?,?,?)",
						$databaseDefinitionId, $changeInfo['view_name'], $changeInfo['description'], $changeInfo['detailed_description'], $subsystemId, $changeInfo['query_string'], $changeInfo['query_text'], $changeInfo['full_query_text']);
					$tableId = $resultSet['insert_id'];

					$sequenceNumber = 0;
					foreach ($tableList as $tableName) {
						$referencedTableId = getFieldFromId("table_id", "tables", "table_name", $tableName);
						if (!empty($referencedTableId)) {
							$sequenceNumber++;
							executeQuery("insert into view_tables (table_id,referenced_table_id,sequence_number) values (?,?,?)", $tableId, $referencedTableId, $sequenceNumber);
						}
					}
					$returnOutput[] = "View " . $changeInfo['view_name'] . " created.";
					break;
				case "create_table":
					$resultSet = executeQuery("show tables like '" . $changeInfo['table_name'] . "'");
					if ($row = getNextRow($resultSet)) {
						break;
					}
					if (empty($changeInfo['description'])) {
						$changeInfo['description'] = ucwords(strtolower(str_replace("_", " ", $changeInfo['table_name'])));
					}
					if (substr($changeInfo['description'], -3) == " Id") {
						$changeInfo['description'] = substr($changeInfo['description'], 0, -3);
					}
					$subsystemId = getFieldFromId("subsystem_id", "subsystems", "subsystem_code", strtoupper((substr($changeInfo['subsystem'], 0, 5) == "CORE_" ? "" : "CORE_") . makeCode($changeInfo['subsystem'])));
					if (empty($subsystemId)) {
						$errorOutput[] = "Invalid subsystem: " . $changeInfo['subsystem'] . "";
						break;
					}
					$resultSet = executeQuery("insert into tables (database_definition_id,table_name,description,detailed_description,subsystem_id) values (?,?,?,?,?)",
						$databaseDefinitionId, $changeInfo['table_name'], $changeInfo['description'], $changeInfo['detailed_description'], $subsystemId);
					$tableId = $resultSet['insert_id'];
					if (empty($changeInfo['primary_key'])) {
						$changeInfo['primary_key'] = $changeInfo['table_name'];
						if (substr($changeInfo['primary_key'], -3) == "ies") {
							$changeInfo['primary_key'] = substr($changeInfo['primary_key'], 0, -3) . "y_id";
						} else {
							if (substr($changeInfo['primary_key'], -4) == "sses") {
								$changeInfo['primary_key'] = substr($changeInfo['primary_key'], 0, -2) . "_id";
							} else {
								if (substr($changeInfo['primary_key'], -8) == "statuses") {
									$changeInfo['primary_key'] = substr($changeInfo['primary_key'], 0, -2) . "_id";
								} else {
									if (substr($changeInfo['primary_key'], -2) == "ss" || substr($changeInfo['primary_key'], -6) == "status" || substr($changeInfo['primary_key'], -2) == "as") {
										$changeInfo['primary_key'] = $changeInfo['primary_key'] . "_id";
									} else {
										if (substr($changeInfo['primary_key'], -1) == "s") {
											$changeInfo['primary_key'] = substr($changeInfo['primary_key'], 0, -1) . "_id";
										} else {
											$changeInfo['primary_key'] = $changeInfo['primary_key'] . "_id";
										}
									}
								}
							}
						}
					}
					$columnDefinitionId = getFieldFromId("column_definition_id", "column_definitions", "column_name", $changeInfo['primary_key']);
					if (empty($columnDefinitionId)) {
						$resultSet = executeQuery("insert into column_definitions (column_name,column_type,not_null) values " .
							"(?,'int',1)", $changeInfo['primary_key']);
						$columnDefinitionId = $resultSet['insert_id'];
					}
					$description = "ID";
					executeQuery("insert into table_columns (table_id,column_definition_id,description,sequence_number," .
						"primary_table_key,indexed,not_null) values (?,?,?,10,1,1,1)", $tableId, $columnDefinitionId, $description);
					$returnOutput[] = "Table " . $changeInfo['table_name'] . " created.";
					break;
				case "add_column":
					$resultSet = executeQuery("show tables like '" . $changeInfo['table_name'] . "'");
					if ($row = getNextRow($resultSet)) {
						$resultSet = executeQuery("show columns from " . $changeInfo['table_name'] . " where Field = '" . $changeInfo['column_name'] . "'");
						if ($row = getNextRow($resultSet)) {
							break;
						}
					}
					$hasVersionColumn = false;
					$resultSet = executeQuery("show columns from " . $changeInfo['table_name'] . " where Field = 'version'");
					if ($row = getNextRow($resultSet)) {
						$hasVersionColumn = true;
					}
					if ($hasVersionColumn && empty($changeInfo['after'])) {
						$errorOutput[] = "Column '" . $changeInfo['column_name'] . " of " . $changeInfo['table_name'] . " must be inserted after something because version field exists.";
						break;
					}
					$tableId = getFieldFromId("table_id", "tables", "table_name", $changeInfo['table_name'], "database_definition_id = ?", $databaseDefinitionId);
					if (empty($tableId)) {
						$errorOutput[] = "Table " . $changeInfo['table_name'] . " can't have column added to it because it doesn't exist.";
						break;
					}
					$columnDefinitionId = getFieldFromId("column_definition_id", "column_definitions", "column_name", $changeInfo['column_name']);
					if (!empty($columnDefinitionId)) {
						$tableColumnId = getFieldFromId("table_column_id", "table_columns", "table_id", $tableId, "column_definition_id = ?", $columnDefinitionId);
						if (!empty($tableColumnId)) {
							break;
						}
					}
					if (empty($columnDefinitionId)) {
						if (empty($changeInfo['column_type'])) {
							$errorOutput[] = "Column Type for " . $changeInfo['column_name'] . " not defined.";
							break;
						}
						$resultSet = executeQuery("insert into column_definitions (column_name,column_type,data_size,decimal_places,minimum_value," .
							"maximum_value,data_format,not_null,code_value,letter_case,default_value) values " .
							"(?,?,?,?,?, ?,?,?,?,?, ?)", $changeInfo['column_name'], $changeInfo['column_type'], $changeInfo['data_size'],
							$changeInfo['decimal_places'], $changeInfo['minimum_value'], $changeInfo['maximum_value'], $changeInfo['data_format'],
							(empty($changeInfo['not_null']) ? 0 : 1), (empty($changeInfo['code_value']) ? 0 : 1), $changeInfo['letter_case'], $changeInfo['default_value']);
						if (!empty($resultSet['sql_error'])) {
							$errorOutput[] = "Unable to create column " . $changeInfo['column_name'] . ".";
						}
						$columnDefinitionId = $resultSet['insert_id'];
					}
					$sequenceNumber = 0;
					$resultSet = executeQuery("select max(sequence_number) from table_columns where table_id = ?", $tableId);
					if ($row = getNextRow($resultSet)) {
						$sequenceNumber = $row['max(sequence_number)'];
					}
					if (!empty($changeInfo['after'])) {
						$sequenceNumber = getFieldFromId("sequence_number", "table_columns", "table_id", $tableId,
							"column_definition_id = (select column_definition_id from column_definitions where column_name = ?)",
							$changeInfo['after']);
					}
					if (empty($sequenceNumber) || !is_numeric($sequenceNumber)) {
						$sequenceNumber = 0;
					}
					$sequenceNumber += 1;
					if (empty($changeInfo['description'])) {
						$changeInfo['description'] = ucwords(str_replace("_", " ", $changeInfo['column_name']));
					}
					if (substr($changeInfo['description'], -3) == " Id") {
						$changeInfo['description'] = substr($changeInfo['description'], 0, -3);
					}
					$columnDefinitionRow = getRowFromId("column_definitions", "column_definition_id", $columnDefinitionId);
					if (!array_key_exists("not_null", $changeInfo)) {
						$changeInfo['not_null'] = $columnDefinitionRow['not_null'];
					}
					if (!array_key_exists("default_value", $changeInfo)) {
						$changeInfo['default_value'] = $columnDefinitionRow['default_value'];
					}
					$resultSet = executeQuery("insert into table_columns (table_id,column_definition_id,description,detailed_description,sequence_number," .
						"indexed,full_text,not_null,default_value) values (?,?,?,?,?,?,?,?,?)", $tableId, $columnDefinitionId,
						$changeInfo['description'], $changeInfo['detailed_description'], $sequenceNumber, (empty($changeInfo['indexed']) ? 0 : 1), (empty($changeInfo['full_text']) ? 0 : 1),
						(empty($changeInfo['not_null']) ? 0 : 1), $changeInfo['default_value']);
					$tableColumnId = $resultSet['insert_id'];
					if (substr($changeInfo['column_name'], -3) == "_id") {
						if (empty($changeInfo['referenced_table_name'])) {
							$columnDefinitionId = getFieldFromId("column_definition_id", "column_definitions", "column_name", $changeInfo['column_name']);
							$referencedTableId = getFieldFromId("table_id", "table_columns", "column_definition_id", $columnDefinitionId, "primary_table_key = 1");
							if (empty($referencedTableId)) {
								$referencedTableId = getFieldFromId("table_id", "tables", "table_name", substr($changeInfo['column_name'], 0, -3) . "s");
							}
							if (empty($referencedTableId)) {
								$referencedTableId = getFieldFromId("table_id", "tables", "table_name", substr($changeInfo['column_name'], 0, -3) . "es");
							}
							if (empty($referencedTableId)) {
								$referencedTableId = getFieldFromId("table_id", "tables", "table_name", substr($changeInfo['column_name'], 0, -4) . "ies");
							}
							if (empty($referencedTableId)) {
								$referencedTableId = getFieldFromId("table_id", "tables", "table_name", substr($changeInfo['column_name'], 0, -3));
							}
						} else {
							$referencedTableId = getFieldFromId("table_id", "tables", "table_name", $changeInfo['referenced_table_name']);
						}
						$referencedTableColumnId = getFieldFromId("table_column_id", "table_columns", "table_id", $referencedTableId, "primary_table_key = 1");
						if (empty($referencedTableColumnId)) {
							$errorOutput[] = "Foreign key for " . $changeInfo['column_name'] . " in " . $changeInfo['table_name'] . " cannot be created.";
							break;
						}
						executeQuery("insert ignore into foreign_keys (table_column_id,referenced_table_column_id) values (?,?)",
							$tableColumnId, $referencedTableColumnId);
					}
					$returnOutput[] = "Column " . $changeInfo['column_name'] . " added to table " . $changeInfo['table_name'] . ".";
					break;
				case "modify_table_column":
					$tableId = getFieldFromId("table_id", "tables", "table_name", $changeInfo['table_name'], "database_definition_id = ?", $databaseDefinitionId);
					if (empty($tableId)) {
						$errorOutput[] = "Table " . $changeInfo['table_name'] . " does not exist.</p>";
						break;
					}
					$columnDefinitionId = getFieldFromId("column_definition_id", "column_definitions", "column_name", $changeInfo['column_name']);
					if (empty($columnDefinitionId)) {
						$errorOutput[] = "Column " . $changeInfo['column_name'] . " does not exist.</p>";
						break;
					}
					$tableColumnId = getFieldFromId("table_column_id", "table_columns", "table_id", $tableId, "column_definition_id = ?", $columnDefinitionId);
					if (empty($tableColumnId)) {
						$errorOutput[] = "Column " . $changeInfo['column_name'] . " does not exist in table " . $changeInfo['table_name'] . ".</p>";
						break;
					}
					$whereStatement = "";
					$thisParameters = array();
					$updateColumns = array("description", "detailed_description", "sequence_number", "indexed", "full_text", "not_null", "default_value");
					foreach ($updateColumns as $updateColumn) {
						if (array_key_exists($updateColumn, $changeInfo)) {
							$whereStatement .= (empty($whereStatement) ? "" : " and ") . $updateColumn . " = ?";
							$thisParameters[] = $changeInfo[$updateColumn];
						}
					}
					if (!empty($whereStatement)) {
						$thisParameters[] = $tableColumnId;
						$resultSet = executeQuery("update table_columns set " . $whereStatement . " where table_column_id = ?", $thisParameters);
					}
					$returnOutput[] = "Column " . $changeInfo['column_name'] . " in table " . $changeInfo['table_name'] . " modified.</p>";
					break;
				case "modify_column":
					$columnDefinitionId = getFieldFromId("column_definition_id", "column_definitions", "column_name", $changeInfo['column_name']);
					if (empty($columnDefinitionId)) {
						$errorOutput[] = "Column " . $changeInfo['column_name'] . " does not exist.</p>";
						break;
					}
					$tableId = getFieldFromId("table_id", "tables", "table_name", $changeInfo['table_name'], "database_definition_id = ?", $databaseDefinitionId);
					if (empty($tableId)) {
						$errorOutput[] = "Table " . $changeInfo['table_name'] . " not found for modifying column.</p>";
						break;
					}
					$tableColumnId = getFieldFromId("table_column_id", "table_columns", "table_id", $tableId, "column_definition_id = ?", $columnDefinitionId);
					if (empty($tableColumnId)) {
						$errorOutput[] = "Table " . $changeInfo['table_name'] . " does not contain column " . $changeInfo['column_name'] . ".</p>";
						break;
					}
					if (!empty($changeInfo['after'])) {
						$sequenceNumber = getFieldFromId("sequence_number", "table_columns", "table_id", $tableId,
							"column_definition_id = (select column_definition_id from column_definitions where column_name = ?)",
							$changeInfo['after']);
						$sequenceNumber++;
						executeQuery("update table_columns set sequence_number = ? where table_column_id = ?", $sequenceNumber, $tableColumnId);
					}
					$whereStatement = "";
					$thisParameters = array();
					$booleanColumns = array("code_value", "not_null", "indexed");
					foreach ($booleanColumns as $thisColumn) {
						if (array_key_exists($thisColumn, $changeInfo)) {
							$changeInfo[$thisColumn] = (empty($changeInfo[$thisColumn]) ? 0 : 1);
						}
					}
					$updateColumns = array("column_type", "data_size", "decimal_places", "minimum_value", "maximum_value", "data_format", "code_value", "letter_case");
					foreach ($updateColumns as $updateColumn) {
						if (array_key_exists($updateColumn, $changeInfo)) {
							$whereStatement .= (empty($whereStatement) ? "" : ", ") . $updateColumn . " = ?";
							$thisParameters[] = $changeInfo[$updateColumn];
						}
					}
					if (!empty($whereStatement)) {
						$thisParameters[] = $columnDefinitionId;
						$resultSet = executeQuery("update column_definitions set " . $whereStatement . " where column_definition_id = ?", $thisParameters);
					}
					$whereStatement = "";
					$thisParameters = array();
					$updateColumns = array("description", "not_null", "default_value", "indexed");
					foreach ($updateColumns as $updateColumn) {
						if (array_key_exists($updateColumn, $changeInfo)) {
							$whereStatement .= (empty($whereStatement) ? "" : ", ") . $updateColumn . " = ?";
							$thisParameters[] = $changeInfo[$updateColumn];
						}
					}
					if (!empty($whereStatement)) {
						$thisParameters[] = $tableColumnId;
						$resultSet = executeQuery("update table_columns set " . $whereStatement . " where table_column_id = ?", $thisParameters);
					}
					$returnOutput[] = "Column " . $changeInfo['column_name'] . " modified.</p>";
					break;
				case "add_unique_key":
					$tableId = getFieldFromId("table_id", "tables", "table_name", $changeInfo['table_name'], "database_definition_id = ?", $databaseDefinitionId);
					if (empty($tableId)) {
						$errorOutput[] = "Table " . $changeInfo['table_name'] . " not found for unique key.</p>";
						break;
					}
					if (!is_array($changeInfo['column_names'])) {
						$changeInfo['column_names'] = array();
					}
					if (!empty($changeInfo['column_name'])) {
						$changeInfo['column_names'][] = $changeInfo['column_name'];
					}
					$tableColumnIds = array();
					foreach ($changeInfo['column_names'] as $columnName) {
						$columnDefinitionId = getFieldFromId("column_definition_id", "column_definitions", "column_name", $columnName);
						$tableColumnId = getFieldFromId("table_column_id", "table_columns", "table_id", $tableId, "column_definition_id = ?", $columnDefinitionId);
						if (empty($tableColumnId)) {
							$errorOutput[] = "Column " . $columnName . " not found for unique key for table " . $changeInfo['table_name'] . ".</p>";
							$returnValue = false;
							break;
						}
						$tableColumnIds[] = $tableColumnId;
					}
					if (empty($tableColumnIds)) {
						$errorOutput[] = "No columns defined for unique key for table " . $changeInfo['table_name'] . ".</p>";
						break;
					}
					$foundUniqueKey = false;
					$resultSet = executeQuery("select unique_key_id,table_id,(select count(*) from unique_key_columns where " .
						"unique_key_id = unique_keys.unique_key_id) column_count from unique_keys where table_id = ?", $tableId);
					while ($row = getNextRow($resultSet)) {
						if (count($tableColumnIds) != $row['column_count']) {
							continue;
						}
						foreach ($tableColumnIds as $tableColumnId) {
							$uniqueKeyColumnId = getFieldFromId("unique_key_column_id", "unique_key_columns", "unique_key_id", $row['unique_key_id'], "table_column_id = ?", $tableColumnId);
							if (empty($uniqueKeyColumnId)) {
								continue 2;
							}
						}
						$foundUniqueKey = true;
						break;
					}
					if (!$foundUniqueKey) {
						$resultSet = executeQuery("insert into unique_keys (table_id) values (?)", $tableId);
						$uniqueKeyId = $resultSet['insert_id'];
						foreach ($tableColumnIds as $tableColumnId) {
							executeQuery("insert ignore into unique_key_columns (unique_key_id,table_column_id) values (?,?)", $uniqueKeyId, $tableColumnId);
						}
						$returnOutput[] = "Unique key created for " . implode(",", $changeInfo['column_names']) . " in " . $changeInfo['table_name'] . ".</p>";
					}
					break;
				case "drop_column":
					$tableId = getFieldFromId("table_id", "tables", "table_name", $changeInfo['table_name'], "database_definition_id = ?", $databaseDefinitionId);
					if (empty($tableId)) {
						$errorOutput[] = "Table " . $changeInfo['table_name'] . " not found for dropping column.</p>";
						break;
					}
					$columnDefinitionId = getFieldFromId("column_definition_id", "column_definitions", "column_name", $changeInfo['column_name']);
					if (empty($columnDefinitionId)) {
						break;
					}
					$tableColumnId = getFieldFromId("table_column_id", "table_columns", "table_id", $tableId, "column_definition_id = ?", $columnDefinitionId);
					if (empty($tableColumnId)) {
						break;
					}
					do {
						$uniqueKeyId = getFieldFromId("unique_key_id", "unique_key_columns", "table_column_id", $tableColumnId);
						if (!empty($uniqueKeyId)) {
							executeQuery("delete from unique_key_columns where unique_key_id = ?", $uniqueKeyId);
							executeQuery("delete from unique_keys where unique_key_id = ?", $uniqueKeyId);
						} else {
							break;
						}
					} while (!empty($uniqueKeyId));
					executeQuery("delete from foreign_keys where table_column_id = ?", $tableColumnId);
					executeQuery("delete from table_columns where table_column_id = ?", $tableColumnId);
					$returnOutput[] = "Column " . $changeInfo['column_name'] . " in table " . $changeInfo['table_name'] . " deleted.</p>";
					break;
				case "drop_table":
					$tableId = getFieldFromId("table_id", "tables", "table_name", $changeInfo['table_name'], "database_definition_id = ?", $databaseDefinitionId);
					if (empty($tableId)) {
						break;
					}
					executeQuery("delete from language_text where language_column_id in (select language_column_id from language_columns where table_id = ?)", $tableId);
					executeQuery("delete from language_columns where table_id = ?", $tableId);
					executeQuery("delete from foreign_keys where table_column_id in (select table_column_id from table_columns " .
						"where table_id = ?) or referenced_table_column_id in (select table_column_id from table_columns " .
						"where table_id = ?)", $tableId, $tableId);
					executeQuery("delete from unique_key_columns where unique_key_id in (select unique_key_id from unique_keys where table_id = ?)", $tableId);
					executeQuery("delete from unique_keys where table_id = ?", $tableId);
					executeQuery("delete from table_columns where table_id = ?", $tableId);
					$resultSet = executeQuery("delete from tables where table_id = ?", $tableId);
					if (empty($resultSet['sql_error'])) {
						$returnOutput[] = "Table " . $changeInfo['table_name'] . " deleted.</p>";
					} else {
						$errorOutput[] = "Table " . $changeInfo['table_name'] . " unable to be deleted. " . $resultSet['sql_error'] . "</p>";
					}
					break;
			}
		}
		executeQuery("update database_definitions set checked = 0 where database_definition_id = ?", $databaseDefinitionId);
		removeCachedData("all_column_metadata", "*", true);
		removeCachedData("all_column_definitions", "*", true);
		removeCachedData("data_source_count", "*", true);
		removeCachedData("table_unique_keys", "*", true);
		removeCachedData("existing_tables", "*", true);
		removeCachedData("table_columns", "*", true);
		removeCachedData("all_key_column_usage", "*", true);

		$integrityArray = self::checkDatabaseIntegrity();
		if (is_array($integrityArray['errors'])) {
			$errorOutput = array_merge($errorOutput, $integrityArray['errors']);;
		}
		if (is_array($integrityArray['output'])) {
			$returnOutput = array_merge($returnOutput, $integrityArray['output']);;
		}
		if (!empty($errorOutput)) {
			return array("output" => $returnOutput, "errors" => $errorOutput);
		}
		$alterResults = self::generateAlterScript(true);
		if (array_key_exists("errors", $alterResults)) {
			$errorOutput = array_merge($errorOutput, $alterResults['errors']);
		}
		if (array_key_exists("output", $alterResults)) {
			$returnOutput = array_merge($returnOutput, $alterResults['output']);
		}
		if (empty($errorOutput)) {
			$returnOutput = array();
		}
		return array("output" => $returnOutput, "errors" => $errorOutput);
	}

	public static function checkDatabaseIntegrity($databaseStatistics = false) {
		$returnOutput = array();
		$errorOutput = array();
		$databaseDefinitionId = getFieldFromId("database_definition_id", "database_definitions", "database_name", $GLOBALS['gPrimaryDatabase']->getName());

		$resultSet = executeQuery("select TABLE_NAME from information_schema.tables where table_type = 'BASE TABLE' and table_schema = ? and " .
			"table_name like 'temporary_products_%' and table_name not like 'temporary_products_" . date("Ymd") . "%'", $GLOBALS['gPrimaryDatabase']->getName());
		while ($row = getNextRow($resultSet)) {
			executeQuery("drop table " . $row['TABLE_NAME']);
		}

		executeQuery("update table_columns set sequence_number = 1 where primary_table_key = 1");
		$resultSet = executeQuery("select * from tables where table_view = 0 and table_id in (select table_id from table_columns where sequence_number > 1 and sequence_number % 100 > 0)");
		while ($row = getNextRow($resultSet)) {
			executeQuery("set @sequenceNumber := 0");
			executeQuery("update table_columns set sequence_number = @sequenceNumber := @sequenceNumber + 100 where table_id = ? and primary_table_key = 0 ORDER BY sequence_number,table_column_id", $row['table_id']);
		}
		$returnOutput[] = "Table Columns resequenced";

# Get DB statistics

		$statCount = 0;
		$resultSet = executeQuery("select count(*) from tables where table_view = 0 and database_definition_id = ?", $databaseDefinitionId);
		if ($row = getNextRow($resultSet)) {
			$statCount = $row['count(*)'];
		}
		$returnOutput[] = "Table Count: " . $statCount;

		$statCount = 0;
		$resultSet = executeQuery("select count(*) from tables where table_view = 1 and database_definition_id = ?", $databaseDefinitionId);
		if ($row = getNextRow($resultSet)) {
			$statCount = $row['count(*)'];
		}
		$returnOutput[] = "View Count: " . $statCount;

		$statCount = 0;
		$resultSet = executeQuery("select count(*) from table_columns where table_id in (select table_id from tables where table_view = 0 and database_definition_id = ?)", $databaseDefinitionId);
		if ($row = getNextRow($resultSet)) {
			$statCount = $row['count(*)'];
		}
		$returnOutput[] = "Column Count: " . $statCount;

		$statCount = 0;
		$resultSet = executeQuery("select count(*) from column_definitions where column_definition_id in (select column_definition_id from table_columns where table_id in (select table_id from tables where table_view = 0 and database_definition_id = ?))", $databaseDefinitionId);
		if ($row = getNextRow($resultSet)) {
			$statCount = $row['count(*)'];
		}
		$returnOutput[] = "Unique Column Count: " . $statCount;

		$statCount = 0;
		$resultSet = executeQuery("select count(*) from unique_keys where table_id in (select table_id from tables where table_view = 0 and database_definition_id = ?)", $databaseDefinitionId);
		if ($row = getNextRow($resultSet)) {
			$statCount = $row['count(*)'];
		}
		$returnOutput[] = "Unique Key Count: " . $statCount;

		$statCount = 0;
		$resultSet = executeQuery("select count(*) from foreign_keys where table_column_id in (select table_column_id from table_columns where table_id in (select table_id from tables where table_view = 0 and database_definition_id = ?))", $databaseDefinitionId);
		if ($row = getNextRow($resultSet)) {
			$statCount = $row['count(*)'];
		}
		$returnOutput[] = "Foreign Key Count: " . $statCount;

# check for unused columns

		$versionColumnDefinitionId = getFieldFromId("column_definition_id", "column_definitions", "column_name", "version");
		$resultSet = executeQuery("select *,(select table_name from tables where table_id = check_tables.table_id) table_name," .
			"(select column_name from column_definitions where column_definition_id = check_tables.column_definition_id) column_name from " .
			"(select a.table_id,column_definition_id from table_columns a inner join (select table_id, max(sequence_number) sequence_number from table_columns group by table_id) b on " .
			"a.table_id = b.table_id and a.sequence_number = b.sequence_number) check_tables where check_tables.column_definition_id <> ? order by check_tables.table_id", $versionColumnDefinitionId);
		if ($resultSet['row_count'] > 0) {
			$returnOutput[] = "";
			$returnOutput[] = "Tables where version is not last column:";
			$returnOutput[] = "";
		}
		while ($row = getNextRow($resultSet)) {
			$returnOutput[] = "    " . $row['table_name'];
		}
		if ($resultSet['row_count'] > 0) {
			$returnOutput[] = "";
		}

		$resultSet = executeQuery("select * from column_definitions where column_definition_id not in (select column_definition_id from table_columns) order by column_name");
		if ($resultSet['row_count'] > 0) {
			$returnOutput[] = "Columns defined, but not used:";
			$returnOutput[] = "";
		}
		while ($row = getNextRow($resultSet)) {
			$returnOutput[] = "    " . $row['column_name'];
		}
		if ($resultSet['row_count'] > 0) {
			$resultSet = executeQuery("delete from column_definitions where column_definition_id not in (select column_definition_id from table_columns)");
			$returnOutput[] = $resultSet['affected_rows'] . " Columns Deleted";
		}

# check for empty tables

		$resultSet = executeQuery("select * from tables where table_view = 0 and (select count(*) from table_columns where table_id = tables.table_id) <= 1 order by table_name");
		if ($resultSet['row_count'] > 0) {
			$errorOutput[] = "Tables defined but without data columns:";
			$errorOutput[] = "";
		}
		while ($row = getNextRow($resultSet)) {
			$errorOutput[] = "    " . $row['table_name'];
		}

# check for tables without version

		$resultSet = executeQuery("select table_id,table_name from tables where table_view = 0 and table_id not in (select table_id from table_columns where column_definition_id = ?)", $versionColumnDefinitionId);
		while ($row = getNextRow($resultSet)) {
			executeQuery("insert into table_columns(table_id,column_definition_id,description,sequence_number,not_null,default_value) values (?,?,?,?,?,?)", $row['table_id'], $versionColumnDefinitionId, 'Version', 10000, 1, "1");
			$returnOutput[] = "Column 'version' added to " . $row['table_name'];
		}

# check for linking table without unique key

		$someFound = false;
		$resultSet = executeQuery("select table_name from tables where (select count(*) from table_columns where table_id = tables.table_id) = 5 and " .
			"table_id not in (select table_id from unique_keys) and (select count(*) from foreign_keys where table_column_id in (select table_column_id from table_columns where " .
			"table_id = tables.table_id)) = 2 and table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'sequence_number'))");
		if ($resultSet['row_count'] > 0) {
			$someFound = true;
			$errorOutput[] = "Linking tables need unique key:";
			$errorOutput[] = "";
		}
		while ($row = getNextRow($resultSet)) {
			$errorOutput[] = "    " . $row['table_name'];
		}
		$resultSet = executeQuery("select table_name from tables where (select count(*) from table_columns where table_id = tables.table_id) = 4 and table_id not in (select table_id from unique_keys) and (select count(*) from foreign_keys where table_column_id in (select table_column_id from table_columns where table_id = tables.table_id)) = 2");
		if ($resultSet['row_count'] > 0 && !$someFound) {
			$errorOutput[] = "Linking tables need unique key:";
			$errorOutput[] = "";
		}
		while ($row = getNextRow($resultSet)) {
			$errorOutput[] = "    " . $row['table_name'];
		}

# remove columns from view where table is not included
		$resultSet = executeQuery("delete from view_columns where table_column_id not in (select table_column_id from table_columns where table_id in (select referenced_table_id from view_tables where table_id = view_columns.table_id))");
		if ($resultSet['affected_rows'] > 0) {
			$returnOutput[] = $resultSet['affected_rows'] . " orphan columns deleted from views";
		}

# check for empty views

		$resultSet = executeQuery("select * from tables where table_view = 1 and full_query_text is null and custom_definition = 0 and (select count(*) from view_tables where table_id = tables.table_id) < 1 order by table_name");
		if ($resultSet['row_count'] > 0) {
			$errorOutput[] = "Views defined but without tables:";
			$errorOutput[] = "";
		}
		while ($row = getNextRow($resultSet)) {
			$errorOutput[] = "    " . $row['table_name'];
		}

# check for duplicate unique keys

		$tableColumnNames = array();
		$resultSet = executeQuery("select table_column_id,column_name from table_columns join column_definitions using (column_definition_id)");
		while ($row = getNextRow($resultSet)) {
			$tableColumnNames[$row['table_column_id']] = $row['column_name'];
		}

		$tableInfoByName = array();
		$tableInfoById = array();
		$resultSet = executeQuery("select tables.table_id,table_name,column_name from tables join table_columns using (table_id) join column_definitions using (column_definition_id) where primary_table_key = 1");
		while ($row = getNextRow($resultSet)) {
			$tableInfoByName[$row['table_name']] = $row;
			$tableInfoById[$row['table_id']] = $row;
		}

		$keysDone = array();
		$resultSet = executeQuery("select *,(select group_concat(table_column_id) from unique_key_columns where unique_key_id = unique_keys.unique_key_id order by unique_key_column_id) as unique_key_columns from unique_keys order by unique_key_id");
		while ($row = getNextRow($resultSet)) {
			$tableName = $uniqueKeyName = $tableInfoById[$row['table_id']]['table_name'];
			$uniqueKeyColumns = explode(",", $row['unique_key_columns']);
			foreach ($uniqueKeyColumns as $tableColumnId) {
				$columnName = $tableColumnNames[$tableColumnId];
				$uniqueKeyName .= "_" . $columnName;
			}
			$uniqueKeyMD5Name = md5($uniqueKeyName);
			if (in_array($uniqueKeyMD5Name, $keysDone)) {
				$errorOutput[] = "Table '" . $tableName . "' has duplicate unique keys";
			}
			$keysDone[] = $uniqueKeyMD5Name;
		}

# check for code fields that don't have unique keys

		$resultSet = executeQuery("select * from table_columns join tables using (table_id) join column_definitions using (column_definition_id) where column_name like '%_code' and " .
			"table_column_id not in (select table_column_id from unique_key_columns) and code_value = 1");
		if ($resultSet['row_count'] > 0) {
			$errorOutput[] = "Code columns without unique keys:";
			$errorOutput[] = "";
		}
		while ($row = getNextRow($resultSet)) {
			$errorOutput[] = "    " . $row['table_name'] . " - " . $row['column_name'];
		}

# make sure an index exists for each foreign key
		$resultSet = executeQuery("update table_columns set indexed = 1 where table_column_id in (select table_column_id from foreign_keys)");

# check for tables not having just one primary key

		$resultSet = executeQuery("select (select table_name from tables where table_view = 0 and table_id = table_columns.table_id) table_name,sum(primary_table_key) from table_columns where table_id in (select table_id from tables where table_view = 0 and database_definition_id = ?) group by table_name having sum(primary_table_key) <> 1", $databaseDefinitionId);
		if ($resultSet['row_count'] > 0) {
			$errorOutput[] = "";
			$errorOutput[] = "Tables not having exactly one primary key:";
		} else {
			executeQuery("update table_columns set not_null = 1, indexed = 1 where primary_table_key = 1");
		}
		while ($row = getNextRow($resultSet)) {
			$errorOutput[] = "    " . $row['table_name'];
		}

# check for invalid column name

		$resultSet = executeQuery("select column_name from column_definitions where length(column_name) < 4");
		if ($resultSet['row_count'] > 0) {
			$errorOutput[] = "";
			$errorOutput[] = "Column names that are too short (be more descriptive):";
		}
		while ($row = getNextRow($resultSet)) {
			$errorOutput[] = "    " . $row['column_name'];
		}

# check for columns needing size, but having none

		$resultSet = executeQuery("select * from column_definitions where data_size is null and column_type in ('decimal','varchar') order by column_name");
		if ($resultSet['row_count'] > 0) {
			$errorOutput[] = "";
			$errorOutput[] = "Columns needing size, but having none:";
		}
		while ($row = getNextRow($resultSet)) {
			$errorOutput[] = "    " . $row['column_name'];
		}

# check for columns needing scale, but having none

		$resultSet = executeQuery("select * from column_definitions where decimal_places is null and column_type = 'decimal' order by column_name");
		if ($resultSet['row_count'] > 0) {
			$errorOutput[] = "";
			$errorOutput[] = "Columns needing scale, but having none:";
		}
		while ($row = getNextRow($resultSet)) {
			$errorOutput[] = "    " . $row['column_name'];
		}

# fix tinyint columns without not_null and default

		$resultSet = executeQuery("update table_columns set not_null = 1 where column_definition_id in (select column_definition_id from column_definitions where column_type = 'tinyint')");
		$resultSet = executeQuery("update column_definitions set not_null = 1 where column_type = 'tinyint'");
		if ($resultSet['affected_rows'] > 0) {
			$returnOutput[] = "";
			$returnOutput[] = $resultSet['affected_rows'] . " tinyint columns set to not_null";
		}
		$resultSet = executeQuery("update table_columns set default_value = '0' where default_value is null and column_definition_id in (select column_definition_id from column_definitions where column_type = 'tinyint')");
		$resultSet = executeQuery("update column_definitions set default_value = '0' where default_value is null and column_type = 'tinyint'");
		if ($resultSet['affected_rows'] > 0) {
			$returnOutput[] = "";
			$returnOutput[] = $resultSet['affected_rows'] . " tinyint columns set to default value '0'";
		}

# check for foreign keys that reference tables in a different database

		$resultSet = executeQuery("select * from foreign_keys where table_column_id in (select table_column_id from table_columns " .
			"where table_id in (select table_id from tables where table_view = 0 and database_definition_id = ?)) and " .
			"referenced_table_column_id not in (select table_column_id from table_columns " .
			"where table_id in (select table_id from tables where table_view = 0 and database_definition_id = ?))", array($databaseDefinitionId, $databaseDefinitionId));
		if ($resultSet['row_count'] > 0) {
			$errorOutput[] = "";
			$errorOutput[] = "Foreign keys referencing tables in a different database:";
		}
		while ($row = getNextRow($resultSet)) {
			$columnName = getFieldFromId("column_name", "column_definitions", "column_definition_id", getFieldFromId("column_definition_id", "table_columns", "table_column_id", $row['table_column_id']));
			$tableName = getFieldFromId("table_name", "tables", "table_id", getFieldFromId("table_id", "table_columns", "table_column_id", $row['table_column_id']));
			$referencedColumnName = getFieldFromId("column_name", "column_definitions", "column_definition_id", getFieldFromId("column_definition_id", "table_columns", "table_column_id", $row['referenced_table_column_id']));
			$referencedTableName = getFieldFromId("table_name", "tables", "table_id", getFieldFromId("table_id", "table_columns", "table_column_id", $row['referenced_table_column_id']));
			$errorOutput[] = "    " . $columnName . " of " . $tableName . " references " . $referencedColumnName . " of " . $referencedTableName;
		}

# check for columns that are indexes but not used in foreign keys

		$resultSet = executeQuery("select *,(select column_name from column_definitions where column_definition_id = table_columns.column_definition_id) column_name," .
			"(select table_name from tables where table_view = 0 and table_id = table_columns.table_id) table_name from table_columns where " .
			"table_id in (select table_id from tables where table_view = 0 and database_definition_id = ?) and primary_table_key = 0 and " .
			"column_definition_id in (select column_definition_id from column_definitions where column_name like '%\_id') and table_column_id not in " .
			"(select table_column_id from foreign_keys) order by table_name", $databaseDefinitionId);
		if ($resultSet['row_count'] > 0) {
			$errorOutput[] = "";
			$errorOutput[] = "Columns needing foreign keys, but having none:";
		}
		while ($row = getNextRow($resultSet)) {
			$errorOutput[] = "    " . $row['column_name'] . " of " . $row['table_name'];
		}

# check for duplicates columns in a table

		$resultSet = executeQuery("select (select table_name from tables where table_view = 0 and table_id = table_columns.table_id) table_name," .
			"(select column_name from column_definitions where column_definition_id = table_columns.column_definition_id) column_name," .
			"count(*) from table_columns where table_id in (select table_id from tables where table_view = 0 and database_definition_id = ?) " .
			"group by table_name,column_name having count(*) > 1", $databaseDefinitionId);
		if ($resultSet['row_count'] > 0) {
			$errorOutput[] = "";
			$errorOutput[] = "The following columns are duplicated within the table:";
		}
		while ($row = getNextRow($resultSet)) {
			$errorOutput[] = "    " . $row['column_name'] . " of " . $row['table_name'];
		}

# check for columns defined as full text, but not varchar or text

		$resultSet = executeQuery("select * from table_columns where full_text = 1 and column_definition_id not in " .
			"(select column_definition_id from column_definitions where column_type in ('mediumtext','text','varchar'))");
		if ($resultSet['row_count'] > 0) {
			$returnOutput[] = "";
			$returnOutput[] = "Columns defined as full text index, but not varchar, text or mediumtext:";
		}
		while ($row = getNextRow($resultSet)) {
			$returnOutput[] = "    " . getFieldFromId("column_name", "column_definitions", "column_definition_id", $row['column_definition_id']);
			executeQuery("update table_columns set full_text = 0 where table_column_id = ?", $row['table_column_id']);
		}

# check for columns that are not indexes used in foreign key

		$resultSet = executeQuery("select *,(select column_name from column_definitions where column_definition_id = table_columns.column_definition_id) column_name," .
			"(select table_name from tables where table_view = 0 and table_id = table_columns.table_id) table_name from table_columns where " .
			"table_id in (select table_id from tables where table_view = 0 and database_definition_id = ?) and primary_table_key = 0 and " .
			"column_definition_id in (select column_definition_id from column_definitions where column_name not like '%\_id') and (table_column_id in " .
			"(select table_column_id from foreign_keys) or table_column_id in (select referenced_table_column_id from foreign_keys)) order by table_name,column_name", $databaseDefinitionId);
		if ($resultSet['row_count'] > 0) {
			$errorOutput[] = "";
			$errorOutput[] = "Non-index Columns used in foreign keys:";
		}
		while ($row = getNextRow($resultSet)) {
			$errorOutput[] = "    " . $row['column_name'] . " of " . $row['table_name'];
		}

# Verify that views can be created

		$resultSet = executeQuery("select * from tables where table_view = 1 and database_definition_id = ? and custom_definition = 0", $databaseDefinitionId);
		while ($row = getNextRow($resultSet)) {
			$tableIdList = array();
			$tableSet = executeQuery("select *,(select table_name from tables where table_id = view_tables.referenced_table_id) table_name from view_tables where table_id = ? order by sequence_number", $row['table_id']);
			while ($tableRow = getNextRow($tableSet)) {
				$tableIdList[] = $tableRow;
			}
			$lastTableId = "";
			$lastTableName = "";
			$lastPrimaryKey = "";
			if (empty($row['full_query_text'])) {
				$query = "select * from";
				foreach ($tableIdList as $tableInfoByName) {
					if (empty($lastTableId)) {
						$query .= " " . $tableInfoByName['table_name'];
						$lastTableId = $tableInfoByName['referenced_table_id'];
						$lastTableName = $tableInfoByName['table_name'];
						$columnSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where table_id = ? and primary_table_key = 1", $tableInfoByName['referenced_table_id']);
						if ($columnRow = getNextRow($columnSet)) {
							$lastPrimaryKey = $columnRow['column_name'];
						}
					} else {
						$lastTableForeignKey = "";
						$thisTableForeignKey = "";
						$columnSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where table_id = ? and primary_table_key = 1", $tableInfoByName['referenced_table_id']);
						if ($columnRow = getNextRow($columnSet)) {
							$thisPrimaryKey = $columnRow['column_name'];
						}
						# check for foreign key from this table to previous primary key
						# check for foreign key from previous table to this primary key

						$columnSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where table_id = ? and table_column_id in (select referenced_table_column_id from foreign_keys where " .
							"table_column_id in (select table_column_id from table_columns where table_id = ?)) order by sequence_number", $lastTableId, $tableInfoByName['referenced_table_id']);
						if ($columnRow = getNextRow($columnSet)) {
							$lastTableForeignKey = $lastPrimaryKey;
							$thisTableForeignKey = $columnRow['column_name'];
						}
						if (empty($lastTableForeignKey) || empty($thisTableForeignKey)) {
							$columnSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where table_id = ? and table_column_id in (select referenced_table_column_id from foreign_keys where " .
								"table_column_id in (select table_column_id from table_columns where table_id = ?)) order by sequence_number", $tableInfoByName['referenced_table_id'], $lastTableId);
							if ($columnRow = getNextRow($columnSet)) {
								$lastTableForeignKey = $columnRow['column_name'];
								$thisTableForeignKey = $thisPrimaryKey;
							}
						}
						if (empty($lastTableForeignKey) || empty($thisTableForeignKey)) {
							$errorOutput[] = "For view '" . $row['table_name'] . "', no foreign key between " . $lastTableName . " and " . $tableInfoByName['table_name'];
							$lastTableId = $tableInfoByName['referenced_table_id'];
							$lastTableName = $tableInfoByName['table_name'];
							continue;
						}
						$query .= " join " . $tableInfoByName['table_name'] . ($lastTableForeignKey == $thisTableForeignKey ? " using (" . $thisTableForeignKey . ")" : " on (" . $lastTableName . "." . $lastTableForeignKey . " = " . $tableInfoByName['table_name'] . "." . $thisTableForeignKey . ")");
					}
				}
			}
		}

		if (count($errorOutput) > 0) {
			$checked = 0;
		} else {
			$checked = 1;
		}
		executeQuery("update database_definitions set checked = ? where database_definition_id = ?", $checked, $databaseDefinitionId);

		if ($GLOBALS['gPrimaryDatabase']->tableExists("email_queue")) {
			$resultSet = executeQuery("delete from email_queue where deleted = 1");
			if ($resultSet['affected_rows'] > 0) {
				$returnOutput[] = $resultSet['affected_rows'] . " emails deleted from queue";
			}
			$resultSet = executeQuery("select count(*) from email_queue where deleted = 0");
			if ($row = getNextRow($resultSet)) {
				$returnOutput[] = $row['count(*)'] . " emails waiting to be sent";
			}
		}

		if ($databaseStatistics) {
			$returnOutput[] = "";
			$returnOutput[] = "Superusers:";
			$returnOutput[] = "";
			$resultSet = executeQuery("select user_id,users.client_id,user_name,first_name,last_name,email_address,inactive from users join contacts using (contact_id) where superuser_flag = 1 order by user_name");
			while ($row = getNextRow($resultSet)) {
				$returnOutput[] = $row['user_name'] . (empty($row['inactive']) ? "" : " (INACTIVE) ") . " - " . getFieldFromId("client_code", "clients", "client_id", $row['client_id']) . " - " . $row['first_name'] . " " . $row['last_name'] . " - " . $row['email_address'];
				if (empty($row['inactive'])) {
					$dupSet = executeQuery("select user_id,(select client_code from clients where client_id = users.client_id) client_code from users where user_name = ? and user_id <> ?",
						$row['user_name'], $row['user_id']);
					while ($dupRow = getNextRow($dupSet)) {
						$returnOutput[] = "   ---> DUPLICATE FOUND in client '" . $dupRow['client_code'] . "'";
					}
				}
			}

			$returnOutput[] = "";
			$returnOutput[] = "Full Access Developers:";
			$returnOutput[] = "";
			$resultSet = executeQuery("select client_id,first_name,last_name,business_name,inactive from developers join contacts using (contact_id) where full_access = 1 order by client_id,first_name");
			while ($row = getNextRow($resultSet)) {
				$returnOutput[] = $row['first_name'] . " " . $row['last_name'] . (empty($row['inactive']) ? "" : " (INACTIVE) ") . " - " . getFieldFromId("client_code", "clients", "client_id", $row['client_id']);
			}

			$resultSet = executeQuery("SELECT TABLE_NAME FROM information_schema.TABLES where table_name not like 'temporary_products_%' and table_schema = ? and table_type = 'BASE TABLE' order by table_name", $GLOBALS['gPrimaryDatabase']->getName());
			while ($row = getNextRow($resultSet)) {
				$primaryKey = $tableInfoByName[$row['TABLE_NAME']]['column_name'];
				if (empty($primaryKey)) {
					continue;
				}
				$maxSet = executeQuery("select max(" . $primaryKey . ") as max_id from " . $row['TABLE_NAME']);
				if ($maxRow = getNextRow($maxSet)) {
					if ($maxRow['max_id'] > 1000000000) {
						$returnOutput[] = $row['TABLE_NAME'] . " has primary key values over a billion.";
					}
				}
			}
			$GLOBALS['gEndTime'] = getMilliseconds();
			$returnOutput[] = "";
			$returnOutput[] = "Total time to run: " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 4);
			$GLOBALS['gStartTime'] = getMilliseconds();

			$resultSet = executeQuery("SELECT CONCAT(table_schema, '.', table_name) as full_table_name, table_rows as table_row_count," .
				"CONCAT(ROUND(data_length / ( 1024 * 1024 ), 2), 'MB') data_size, CONCAT(ROUND(index_length / ( 1024 * 1024 ), 2), 'MB') index_size," .
				"ROUND(( data_length + index_length ) / ( 1024 * 1024 ), 2) total_size FROM information_schema.TABLES where table_schema = ? and ((data_length + index_length) > 50000000 or table_rows > 500000) " .
				"ORDER BY data_length + index_length DESC", $GLOBALS['gPrimaryDatabase']->getName());
			if ($resultSet['row_count'] == 0) {
				$resultSet = executeQuery("SELECT CONCAT(table_schema, '.', table_name) full_table_name, table_rows as table_row_count," .
					"CONCAT(ROUND(data_length / ( 1024 * 1024 ), 2), 'MB') data_size, CONCAT(ROUND(index_length / ( 1024 * 1024 ), 2), 'MB') index_size," .
					"ROUND(( data_length + index_length ) / ( 1024 * 1024 ), 2) total_size FROM information_schema.TABLES where table_schema = ? ORDER BY table_rows DESC limit 5", $GLOBALS['gPrimaryDatabase']->getName());
			}
			$returnOutput[] = "";
			$returnOutput[] = "Largest Tables:";
			$returnOutput[] = "";
			while ($row = getNextRow($resultSet)) {
				$returnOutput[] = $row['full_table_name'] . " - " . number_format($row['table_row_count'], 0, "", ",") . " rows requiring " . $row['total_size'] . "MB";
			}
			$GLOBALS['gEndTime'] = getMilliseconds();
			$returnOutput[] = "";
			$returnOutput[] = "Total time to run: " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 4);
		}
		$returnArray['output'] = $returnOutput;
		$returnArray['errors'] = $errorOutput;
		return $returnArray;
	}

	public static function generateAlterScript($runScript = false) {
		$databaseName = $GLOBALS['gPrimaryDatabase']->getName();
		$databaseDefinitionId = getFieldFromId("database_definition_id", "database_definitions", "database_name", $databaseName);
		$alterScript = array();

		# get tables and columns

		$allTableArray = array();
		$viewArray = array();
		$tableArray = array();
		$allTableColumns = array();
		$allTableColumnIds = array();
		$resultSet = executeQuery("select * from table_columns join tables using (table_id) right join column_definitions using (column_definition_id) where table_view = 0 and database_definition_id = ? order by table_name,sequence_number", $databaseDefinitionId);
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists($row['table_id'], $tableArray)) {
				$tableArray[$row['table_id']] = $row['table_name'];
				$allTableArray[$row['table_id']] = $row['table_name'];
			}
			$allTableColumns[$row['table_column_id']] = $row;
			$allTableColumnIds[$row['table_name'] . "." . $row['column_name']] = $row['table_column_id'];
		}
		$resultSet = executeQuery("select * from tables where table_view = 1 and database_definition_id = ? order by table_name", $databaseDefinitionId);
		while ($row = getNextRow($resultSet)) {
			$viewArray[$row['table_id']] = $row['table_name'];
		}

		# Drop foreign keys

		$foreignKeyArray = array();
		$resultSet = executeQuery("select * from foreign_keys where table_column_id in " .
			"(select table_column_id from table_columns where table_id in (select table_id from tables where table_view = 0 and database_definition_id = ?))", $databaseDefinitionId);
		while ($row = getNextRow($resultSet)) {
			$tableName = $allTableColumns[$row['table_column_id']]['table_name'];
			$columnName = $allTableColumns[$row['table_column_id']]['column_name'];

			$referencedTableName = $allTableColumns[$row['referenced_table_column_id']]['table_name'];
			$referencedColumnName = $allTableColumns[$row['referenced_table_column_id']]['column_name'];

			$foreignKeyName = "fk_" . md5($tableName . "_" . $columnName);
			$foreignKeyArray[$foreignKeyName] = "alter table " . $tableName . " add constraint " . $foreignKeyName . " foreign key (" .
				$columnName . ") references " . $referencedTableName . "(" . $referencedColumnName . ");";
		}
		$resultSet = executeQuery("select * from information_schema.key_column_usage where table_name not like 'temporary_products_%' and constraint_schema = ? and referenced_table_name is not null", $databaseName);
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists($row['CONSTRAINT_NAME'], $foreignKeyArray)) {
				$alterScript[] = "alter table " . $row['TABLE_NAME'] . " drop foreign key " . $row['CONSTRAINT_NAME'] . ";";
			}
		}

		# drop tables

		$dropTables = array();
		$resultSet = executeQuery("select TABLE_NAME from information_schema.tables where table_name not like 'temporary_products_%' and table_type = 'BASE TABLE' and table_schema = ?", $databaseName);
		while ($row = getNextRow($resultSet)) {
			if (!in_array($row['TABLE_NAME'], $tableArray)) {
				$alterScript[] = "drop table " . $row['TABLE_NAME'] . ";";
				$dropTables[] = $row['TABLE_NAME'];
			} else {
				unset($tableArray[array_search($row['TABLE_NAME'], $tableArray)]);
			}
		}

		# drop views

		$viewNames = array();
		$resultSet = executeQuery("select TABLE_NAME from information_schema.views where table_name not like 'temporary_products_%' and table_schema = ?", $databaseName);
		while ($row = getNextRow($resultSet)) {
			$viewNames[] = $row['TABLE_NAME'];
			if (!in_array($row['TABLE_NAME'], $viewArray)) {
				$alterScript[] = "drop view " . $row['TABLE_NAME'] . ";";
			} else {
				unset($viewArray[array_search($row['TABLE_NAME'], $viewArray)]);
			}
		}

		# drop unique keys

		$uniqueKeyArray = array();
		$resultSet = executeQuery("select *,(select group_concat(table_column_id) from unique_key_columns where unique_key_id = unique_keys.unique_key_id order by unique_key_column_id) as unique_key_columns from unique_keys where table_id in (select table_id from tables where table_view = 0 and database_definition_id = ?)", $databaseDefinitionId);
		while ($row = getNextRow($resultSet)) {
			$uniqueKeyName = $allTableArray[$row['table_id']];
			$uniqueKeyColumns = explode(",", $row['unique_key_columns']);
			foreach ($uniqueKeyColumns as $tableColumnId) {
				$uniqueKeyName .= "_" . $allTableColumns[$tableColumnId]['column_name'];
			}
			$uniqueKeyArray[] = "uk_" . md5($uniqueKeyName);
		}

		$resultSet = executeQuery("select * from information_schema.table_constraints where table_name not like 'temporary_products_%' and constraint_type = 'UNIQUE' and constraint_schema = ?", $databaseName);
		while ($row = getNextRow($resultSet)) {
			if (!in_array($row['CONSTRAINT_NAME'], $uniqueKeyArray) && !in_array($row['TABLE_NAME'], $dropTables)) {
				$alterScript[] = "alter table " . $row['TABLE_NAME'] . " drop index " . $row['CONSTRAINT_NAME'] . ";";
			}
		}

		# drop indexes

		$indexArray = array();
		foreach ($allTableColumns as $tableColumnId => $row) {
			if ($row['indexed'] != 1) {
				continue;
			}
			$indexArray[] = "i_" . md5($row['table_name'] . "_" . $row['column_name']);
		}
		$resultSet = executeQuery("select * from information_schema.statistics where table_name not like 'temporary_products_%' and index_schema = ? and non_unique = 1 and index_type <> 'FULLTEXT';", $databaseName);
		while ($row = getNextRow($resultSet)) {
			if (!in_array($row['INDEX_NAME'], $indexArray) && !in_array($row['TABLE_NAME'], $dropTables)) {
				$alterScript[] = "ALTER TABLE " . $row['TABLE_NAME'] . " drop index " . $row['INDEX_NAME'] . ";";
			}
		}

		# drop full text indexes

		$fullTextArray = array();
		foreach ($allTableColumns as $tableColumnId => $row) {
			if ($row['full_text'] != 1) {
				continue;
			}
			$fullTextArray[] = "ft_" . md5($row['table_name'] . "_" . $row['column_name']);
		}
		$resultSet = executeQuery("select * from information_schema.statistics where table_name not like 'temporary_products_%' and index_schema = ? and index_type = 'FULLTEXT';", $databaseName);
		while ($row = getNextRow($resultSet)) {
			if (!in_array($row['INDEX_NAME'], $fullTextArray) && !in_array($row['TABLE_NAME'], $dropTables)) {
				$alterScript[] = "alter table " . $row['TABLE_NAME'] . " drop index " . $row['INDEX_NAME'] . ";";
			}
		}

		# create new tables

		foreach ($tableArray as $tableId => $tableName) {
			$alterScript[] = "CREATE TABLE " . $tableName . " (";
			$primaryKey = "";
			foreach ($allTableColumns as $tableColumnId => $row) {
				if ($row['table_id'] != $tableId) {
					continue;
				}
				if (!empty($row['primary_table_key'])) {
					$primaryKey = $row['column_name'];
				}
				$alterScript[] = "\t" . $row['column_name'] . " " . $row['column_type'] .
					(empty($row['data_size']) ? "" : "(" . $row['data_size'] . (empty($row['decimal_places']) ? "" : "," . $row['decimal_places']) . ")") . (empty($row['not_null']) ? "" : " NOT NULL") .
					(strlen($row['default_value']) == 0 || ($row['default_value'] == "now" && $row['column_type'] == "date") ? "" : " default " . ($row['default_value'] == "now" ? "current_timestamp" : (is_numeric($row['default_value']) ? $row['default_value'] : "'" . $row['default_value'] . "'"))) .
					($row['column_name'] == $primaryKey ? " auto_increment" : "") . ",";
			}
			$alterScript[] = "\tPRIMARY KEY(" . $primaryKey . ")";
			$alterScript[] = ") engine=innoDB;";
		}

		# add new columns

		$existingColumnArray = array();
		$existingColumnOrder = array();
		$checkColumnArray = array();
		$resultSet = executeQuery("select * from information_schema.columns where table_name not like 'temporary_products_%' and table_schema = ? and " .
			"table_name in (select table_name from information_schema.tables where table_type = 'BASE TABLE') order by TABLE_NAME,ORDINAL_POSITION", $databaseName);
		while ($row = getNextRow($resultSet)) {
			$existingColumnArray[$row['TABLE_NAME'] . "." . $row['COLUMN_NAME']] = $row;
			if (!array_key_exists($row['TABLE_NAME'], $existingColumnOrder)) {
				$existingColumnOrder[$row['TABLE_NAME']] = array();
			}
			$existingColumnOrder[$row['TABLE_NAME']][] = $row['COLUMN_NAME'];
			$checkColumnArray[$row['TABLE_NAME'] . "." . $row['COLUMN_NAME']] = $row['TABLE_NAME'] . "." . $row['COLUMN_NAME'];
		}
		$lastColumnName = "";
		foreach ($allTableColumns as $tableColumnId => $row) {
			if (array_key_exists($row['table_id'], $tableArray)) {
				continue;
			}
			$fullColumnName = $row['table_name'] . "." . $row['column_name'];
			if (!in_array($fullColumnName, $checkColumnArray)) {
				$alterScript[] = "alter table " . $row['table_name'] . " add " . $row['column_name'] . " " . $row['column_type'] .
					(empty($row['data_size']) ? "" : "(" . $row['data_size'] . (empty($row['decimal_places']) ? "" : "," . $row['decimal_places']) . ")") . (empty($row['not_null']) ? "" : " NOT NULL") .
					(strlen($row['default_value']) == 0 || ($row['default_value'] == "now" && $row['column_type'] == "date") ? "" : " default " . ($row['default_value'] == "now" ? "current_timestamp" : (is_numeric($row['default_value']) ? $row['default_value'] : "'" . $row['default_value'] . "'"))) .
					" after " . $lastColumnName . ";";
			} else {
				unset($checkColumnArray[$fullColumnName]);
			}
			$lastColumnName = $row['column_name'];
		}

		# drop columns

		$tableDroppedColumns = array();
		foreach ($checkColumnArray as $fullColumnName) {
			$parts = explode(".", $fullColumnName);
			$tableName = $parts[0];
			$columnName = $parts[1];
			if (in_array($tableName, $viewNames)) {
				continue;
			}
			if (!in_array($tableName, $dropTables)) {
				$alterScript[] = "alter table " . $tableName . " drop " . $columnName . ";";
				if (!array_key_exists($tableName, $tableDroppedColumns)) {
					$tableDroppedColumns[$tableName] = array();
				}
				$tableDroppedColumns[$tableName][] = $columnName;
			}
		}

		# alter changed columns

		$columnArray = array();
		$columnTypeStripSize = array("tinyint", "int", "bigint");
		foreach ($allTableColumns as $tableColumnId => $row) {
			$columnType = $row['column_type'] . (empty($row['data_size']) ? "" : "(" . $row['data_size'] . (empty($row['decimal_places']) ? "" : "," . $row['decimal_places']) . ")");
			$nullable = (empty($row['not_null']) ? "YES" : "NO");
			$defaultValue = ($row['default_value'] == "now" ? ($row['column_type'] == "date" ? "" : "CURRENT_TIMESTAMP") : $row['default_value']);
			if (array_key_exists($row['table_name'] . "." . $row['column_name'], $existingColumnArray)) {
				$existingColumnInfo = $existingColumnArray[$row['table_name'] . "." . $row['column_name']];
				foreach ($columnTypeStripSize as $thisColumnType) {
					if (startsWith($columnType, $thisColumnType)) {
						$columnType = explode("(", $columnType)[0];
						$existingColumnInfo['COLUMN_TYPE'] = explode("(", $existingColumnInfo['COLUMN_TYPE'])[0];
					}
				}
				if ((!empty($row['primary_table_key']) && empty($existingColumnInfo['EXTRA'])) || $columnType != $existingColumnInfo['COLUMN_TYPE'] || $nullable != $existingColumnInfo['IS_NULLABLE'] || $defaultValue != $existingColumnInfo['COLUMN_DEFAULT']) {
					if (empty($row['primary_table_key']) && substr($row['column_name'], -3) == "_id") {
						$foreignKeyName = "fk_" . md5($row['table_name'] . "_" . $row['column_name']);
						$alterScript[] = "alter table " . $row['table_name'] . " drop foreign key " . $foreignKeyName . ";";
					}
					$alterScript[] = "alter table " . $row['table_name'] . " modify " . $row['column_name'] . " " . $row['column_type'] .
						(empty($row['data_size']) ? "" : "(" . $row['data_size'] . (empty($row['decimal_places']) ? "" : "," . $row['decimal_places']) . ")") . (empty($row['not_null']) ? "" : " NOT NULL") .
						(empty($row['primary_table_key']) ? "" : " AUTO_INCREMENT") . (strlen($row['default_value']) == 0 || ($row['default_value'] == "now" && $row['column_type'] == "date") ? "" : " default " .
							($row['default_value'] == "now" ? "current_timestamp" : (is_numeric($row['default_value']) ? $row['default_value'] : "'" . $row['default_value'] . "'"))) . ";";
					if (substr($row['column_name'], -3) == "_id") {
						$referSet = executeQuery("select * from foreign_keys,table_columns where referenced_table_column_id = table_columns.table_column_id and foreign_keys.table_column_id = ?", $tableColumnId);
						if ($referRow = getNextRow($referSet)) {
							$referencedTableName = $allTableArray[$referRow['table_id']];
							$referencedColumnName = getFieldFromId("column_name", "column_definitions", "column_definition_id", $referRow['column_definition_id']);
							$foreignKeyName = "fk_" . md5($row['table_name'] . "_" . $row['column_name']);
							$alterScript[] = "alter table " . $row['table_name'] . " add constraint " . $foreignKeyName . " foreign key (" . $row['column_name'] .
								") references " . $referencedTableName . "(" . $referencedColumnName . ");";
						}
					}
				}
			}
		}

		# change order of columns

		$columnArray = array();
		foreach ($allTableColumns as $tableColumnId => $row) {
			if (!array_key_exists($row['table_name'], $columnArray)) {
				$columnArray[$row['table_name']] = array();
			}
			$fullColumnName = $row['table_name'] . "." . $row['column_name'];
			if (!array_key_exists($fullColumnName, $existingColumnArray)) {
				continue;
			}
			$columnArray[$row['table_name']][] = $row['column_name'];
		}
		foreach ($columnArray as $tableName => $columnNameArray) {
			if (!array_key_exists($tableName, $existingColumnOrder)) {
				continue;
			}
			$compareColumnOrder = array();
			foreach ($existingColumnOrder[$tableName] as $thisColumnName) {
				if (!array_key_exists($tableName, $tableDroppedColumns) || !in_array($thisColumnName, $tableDroppedColumns[$tableName])) {
					$compareColumnOrder[] = $thisColumnName;
				}
			}
			if ($columnNameArray === $compareColumnOrder) {
				continue;
			}
			$moveHappened = false;
			$movedColumns = array();
			while (count($columnNameArray) > 0) {
				if (!$moveHappened) {
					while (true) {
						$existingColumnName = array_shift($compareColumnOrder);
						if (empty($existingColumnName) || !in_array($existingColumnName, $movedColumns)) {
							break;
						}
					}
				}
				$columnName = array_shift($columnNameArray);
				if (empty($existingColumnName) || empty($columnName)) {
					break;
				}
				$moveHappened = false;
				if ($existingColumnName != $columnName) {
					$tableColumnId = $allTableColumnIds[$tableName . "." . $columnName];
					if (!empty($tableColumnId)) {
						$row = $allTableColumns[$tableColumnId];
						$alterScript[] = "alter table " . $tableName . " modify " . $columnName . " " . $row['column_type'] .
							(empty($row['data_size']) ? "" : "(" . $row['data_size'] . (empty($row['decimal_places']) ? "" : "," . $row['decimal_places']) . ")") . (empty($row['not_null']) ? "" : " NOT NULL") .
							(strlen($row['default_value']) == 0 || ($row['default_value'] == "now" && $row['column_type'] == "date") ? "" : " default " . ($row['default_value'] == "now" ? "current_timestamp" : (is_numeric($row['default_value']) ? $row['default_value'] : "'" . $row['default_value'] . "'"))) . " after " . $lastColumnName . ";";
						$moveHappened = true;
						$movedColumns[] = $columnName;
					}
				}
				$lastColumnName = $columnName;
			}
		}

		# add unique keys

		$uniqueKeyArray = array();
		$resultSet = executeQuery("select *,(select group_concat(table_column_id) from unique_key_columns where unique_key_id = unique_keys.unique_key_id order by unique_key_column_id) as unique_key_columns from unique_keys where table_id in (select table_id from tables where table_view = 0 and database_definition_id = ?)", $databaseDefinitionId);
		while ($row = getNextRow($resultSet)) {
			$uniqueKeyName = $allTableArray[$row['table_id']];
			$uniqueKey = "";
			$uniqueKeyColumns = explode(",", $row['unique_key_columns']);
			foreach ($uniqueKeyColumns as $tableColumnId) {
				$uniqueKeyName .= "_" . $allTableColumns[$tableColumnId]['column_name'];
				if (!empty($uniqueKey)) {
					$uniqueKey .= ",";
				}
				$uniqueKey .= $allTableColumns[$tableColumnId]['column_name'];
			}
			$uniqueKeyArray["uk_" . md5($uniqueKeyName)] = "create unique index uk_" . md5($uniqueKeyName) . " on " .
				$allTableArray[$row['table_id']] . "(" . $uniqueKey . ");";
		}
		$resultSet = executeQuery("select * from information_schema.table_constraints where constraint_type = 'UNIQUE' and constraint_schema = ?", $databaseName);
		while ($row = getNextRow($resultSet)) {
			if (array_key_exists($row['CONSTRAINT_NAME'], $uniqueKeyArray)) {
				unset($uniqueKeyArray[$row['CONSTRAINT_NAME']]);
			}
		}
		foreach ($uniqueKeyArray as $uniqueKey) {
			$alterScript[] = $uniqueKey;
		}

		# Add indexes

		$columnTypes = array();
		$resultSet = executeQuery("select column_definition_id,column_type from column_definitions");
		while ($row = getNextRow($resultSet)) {
			$columnTypes[$row['column_definition_id']] = $row['column_type'];
		}

		$indexArray = array();
		$indexColumns = array();
		$resultSet = executeQuery("select * from column_definitions where always_index = 1");
		while ($row = getNextRow($resultSet)) {
			$indexColumns[$row['column_definition_id']] = $row['column_definition_id'];
		}
		foreach ($allTableColumns as $tableColumnId => $row) {
			if ($row['indexed'] != 1 || $row['primary_table_key'] == 1 || array_key_exists($row['column_definition_id'], $indexColumns)) {
				continue;
			}
			$columnType = $columnTypes[$row['column_definition_id']];
			$indexName = "i_" . md5($row['table_name'] . "_" . $row['column_name']);
			$indexArray[$indexName] = "create" . ($columnType == "point" ? " spatial" : "") . " index " . $indexName . " on " . $row['table_name'] . "(" . $row['column_name'] . ($columnType == "text" || $columnType == "mediumtext" ? "(20)" : "") . ");";
		}
		$resultSet = executeQuery("select * from information_schema.statistics where index_schema = ? and non_unique = 1 and index_type <> 'FULLTEXT';", $databaseName);
		while ($row = getNextRow($resultSet)) {
			if (array_key_exists($row['INDEX_NAME'], $indexArray)) {
				unset($indexArray[$row['INDEX_NAME']]);
			}
		}
		foreach ($indexArray as $index) {
			$alterScript[] = $index;
		}

		# Add full text indexes

		$fullTextArray = array();
		foreach ($allTableColumns as $tableColumnId => $row) {
			if ($row['full_text'] != 1 || $row['primary_table_key'] == 1) {
				continue;
			}
			$indexName = "ft_" . md5($row['table_name'] . "_" . $row['column_name']);
			$fullTextArray[$indexName] = "create fulltext index " . $indexName . " on " . $row['table_name'] . "(" . $row['column_name'] . ");";
		}
		$resultSet = executeQuery("select * from information_schema.statistics where index_schema = ? and index_type = 'FULLTEXT'", $databaseName);
		while ($row = getNextRow($resultSet)) {
			if (array_key_exists($row['INDEX_NAME'], $fullTextArray)) {
				unset($fullTextArray[$row['INDEX_NAME']]);
			}
		}
		foreach ($fullTextArray as $index) {
			$alterScript[] = $index;
		}

		# add foreign keys

		$resultSet = executeQuery("select * from information_schema.key_column_usage where constraint_schema = ? and referenced_table_name is not null", $databaseName);
		while ($row = getNextRow($resultSet)) {
			if (array_key_exists($row['CONSTRAINT_NAME'], $foreignKeyArray)) {
				unset($foreignKeyArray[$row['CONSTRAINT_NAME']]);
			}
		}
		foreach ($foreignKeyArray as $foreignKey) {
			$alterScript[] = $foreignKey;
		}

		# create and update views

		$resultSet = executeQuery("select * from tables where table_view = 1 and database_definition_id = ? and custom_definition = 0", $databaseDefinitionId);
		while ($row = getNextRow($resultSet)) {
			$tableIdList = array();
			$tableSet = executeQuery("select *,(select table_name from tables where table_id = view_tables.referenced_table_id) table_name from view_tables where table_id = ? order by sequence_number", $row['table_id']);
			while ($tableRow = getNextRow($tableSet)) {
				$tableIdList[] = $tableRow;
			}
			$lastTableId = "";
			$lastTableName = "";
			$columnList = "";
			$lastPrimaryKey = "";
			$columnSet = executeQuery("select *,(select column_name from column_definitions where column_definition_id = (select column_definition_id from table_columns where table_column_id = view_columns.table_column_id)) column_name," .
				"(select table_name from tables where table_id = (select table_id from table_columns where table_column_id = view_columns.table_column_id)) table_name from view_columns where table_id = ? order by sequence_number", $row['table_id']);
			while ($columnRow = getNextRow($columnSet)) {
				$columnList .= (empty($columnList) ? "" : ",") . $columnRow['table_name'] . "." . $columnRow['column_name'];
			}
			if (empty($columnList)) {
				$columnList = "*";
			}
			if (empty($row['full_query_text'])) {
				$query = "create view " . $row['table_name'] . " as select " . $columnList . " from";
				foreach ($tableIdList as $tableInfoByName) {
					if (empty($lastTableId)) {
						$query .= " " . $tableInfoByName['table_name'];
						$lastTableId = $tableInfoByName['referenced_table_id'];
						$lastTableName = $tableInfoByName['table_name'];
						$columnSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where table_id = ? and primary_table_key = 1", $tableInfoByName['referenced_table_id']);
						if ($columnRow = getNextRow($columnSet)) {
							$lastPrimaryKey = $columnRow['column_name'];
						}
					} else {
						$lastTableForeignKey = "";
						$thisTableForeignKey = "";
						$columnSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where table_id = ? and primary_table_key = 1", $tableInfoByName['referenced_table_id']);
						if ($columnRow = getNextRow($columnSet)) {
							$thisPrimaryKey = $columnRow['column_name'];
						}
						# check for foreign key from this table to previous primary key
						# check for foreign key from previous table to this primary key

						$columnSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where table_id = ? and table_column_id in (select referenced_table_column_id from foreign_keys where " .
							"table_column_id in (select table_column_id from table_columns where table_id = ?)) order by sequence_number", $lastTableId, $tableInfoByName['referenced_table_id']);
						if ($columnRow = getNextRow($columnSet)) {
							$lastTableForeignKey = $lastPrimaryKey;
							$thisTableForeignKey = $columnRow['column_name'];
						}
						if (empty($lastTableForeignKey) || empty($thisTableForeignKey)) {
							$columnSet = executeQuery("select * from table_columns join column_definitions using (column_definition_id) where table_id = ? and table_column_id in (select referenced_table_column_id from foreign_keys where " .
								"table_column_id in (select table_column_id from table_columns where table_id = ?)) order by sequence_number", $tableInfoByName['referenced_table_id'], $lastTableId);
							if ($columnRow = getNextRow($columnSet)) {
								$lastTableForeignKey = $columnRow['column_name'];
								$thisTableForeignKey = $thisPrimaryKey;
							}
						}
						if (empty($lastTableForeignKey) || empty($thisTableForeignKey)) {
							$errorOutput[] = "For view '" . $row['table_name'] . "', no foreign key between " . $lastTableName . " and " . $tableInfoByName['table_name'];
							$lastTableId = $tableInfoByName['referenced_table_id'];
							$lastTableName = $tableInfoByName['table_name'];
							continue;
						}
						$query .= " join " . $tableInfoByName['table_name'] . ($lastTableForeignKey == $thisTableForeignKey ? " using (" . $thisTableForeignKey . ")" : " on (" . $lastTableName . "." . $lastTableForeignKey . " = " . $tableInfoByName['table_name'] . "." . $thisTableForeignKey . ")");
					}
				}
				if (!empty($row['query_text'])) {
					$query .= " where " . $row['query_text'];
				}
			} else {
				$query = "create view " . $row['table_name'] . " as " . $row['full_query_text'];
			}
			if (in_array($row['table_name'], $viewArray) || $query != $row['query_string']) {
				$alterScript[] = "drop view if exists " . $row['table_name'] . ";";
				$alterScript[] = $query . ";";
				$alterScript[] = "update tables set query_string = " . makeParameter($query) . " where table_id = " . $row['table_id'] . ";";
			}
		}

# create and update stored procedures

		$storedProcedureNames = array();
		$resultSet = executeQuery("select * from stored_procedures where inactive = 0 and database_definition_id = ? order by sort_order", $databaseDefinitionId);
		while ($row = getNextRow($resultSet)) {
			$storedProcedureNames[] = $row['stored_procedure_name'];
			$recreateProcedure = false;
			$procedureCodeLines = array();
			$sqlLines = getContentLines($row['content']);
			foreach ($sqlLines as $thisLine) {
				if (substr($thisLine, 0, 2) != "--") {
					$procedureCodeLines[] = $thisLine;
				}
			}
			$procedureCode = trim(implode("\n", $procedureCodeLines));
			$checkSet = executeQuery("show procedure status where db = ? and name = ?", $databaseName, $row['stored_procedure_name']);
			if ($checkSet['row_count'] == 0) {
				$recreateProcedure = true;
			} else {
				$checkSet = executeQuery("select ROUTINE_DEFINITION from INFORMATION_SCHEMA.ROUTINES where routine_schema = ? and specific_name = ?", $databaseName, $row['stored_procedure_name']);
				if ($checkRow = getNextRow($checkSet)) {
					if ($checkRow['ROUTINE_DEFINITION'] != $procedureCode) {
						$recreateProcedure = true;
					}
				} else {
					$recreateProcedure = true;
				}
				if (!$recreateProcedure) {
					$checkSet = executeQuery("show create procedure " . $row['stored_procedure_name']);
					if ($checkRow = getNextRow($checkSet)) {
						$parts = getContentLines($checkRow['Create Procedure'], array("limit" => 2));
						$createParts = explode("(", $parts[0], 2);
						$parameters = substr($createParts[1], 0, -1);
						if ($parameters != $row['parameters']) {
							$recreateProcedure = true;
						}
					} else {
						$recreateProcedure = true;
					}
				}
			}
			if ($recreateProcedure) {
				$alterScript[] = "DROP PROCEDURE IF EXISTS " . $row['stored_procedure_name'] . ";";
				$alterScript[] = "DELIMITER $$";
				$alterScript[] = "CREATE PROCEDURE " . $row['stored_procedure_name'] . "(" . $row['parameters'] . ")";
				$sqlLines = getContentLines($row['content']);
				foreach ($sqlLines as $thisLine) {
					if (substr($thisLine, 0, 2) != "--") {
						$alterScript[] = $thisLine;
					}
				}
				$alterScript[] = "$$";
				$alterScript[] = "DELIMITER ;";
			}
		}
		$resultSet = executeQuery("show procedure status where db = ?", $databaseName);
		while ($row = getNextRow($resultSet)) {
			if (!in_array($row['Name'], $storedProcedureNames)) {
				$alterScript[] = "DROP PROCEDURE IF EXISTS " . $row['Name'] . ";";
			}
		}
		if (!$runScript || empty($alterScript)) {
			return array("alter_script" => $alterScript);
		}

		$returnOutput = array();
		$returnOutput[] = "ALTER SCRIPT RUN:";
		$errorOutput = array();
		$alterCommand = "";
		$delimiter = ";";
		foreach ($alterScript as $alterLine) {
			$returnOutput[] = $alterLine;
			if (!startsWith($alterLine, "delimiter") && $alterLine != $delimiter) {
				$alterCommand .= "\n" . $alterLine;
			}
			if ($alterLine == $delimiter || substr($alterCommand, -1 * strlen($delimiter)) == $delimiter) {
				$resultSet = executeQuery($alterCommand);
				if (!empty($resultSet['sql_error'])) {
					$errorOutput[] = $resultSet['sql_error'];
					break;
				} else {
					$firstWord = strtolower(trim(explode(" ", $alterCommand)[0]));
					switch ($firstWord) {
						case "delete":
							$returnOutput[] = $resultSet['affected_rows'] . " row" . ($resultSet['affected_rows'] == 1 ? "" : "s") . " deleted";
							break;
						case "update":
							$returnOutput[] = $resultSet['affected_rows'] . " row" . ($resultSet['affected_rows'] == 1 ? "" : "s") . " updated";
							break;
						case "insert":
							$returnOutput[] = $resultSet['affected_rows'] . " row" . ($resultSet['affected_rows'] == 1 ? "" : "s") . " inserted";
							break;
						case "select";
							$fieldValues = array();
							$headers = array();
							while ($row = getNextRow($resultSet)) {
								$thisRow = array();
								$headerIndex = 0;
								foreach ($row as $fieldName => $fieldData) {
									if (is_numeric($fieldName)) {
										continue;
									}
									$thisRow[] = $fieldData;
									if (empty($fieldValues)) {
										$headers[] = array("label" => $fieldName, "length" => max(strlen($fieldName), strlen($fieldData)));
									} else {
										$headers[$headerIndex]['length'] = max($headers[$headerIndex]['length'], strlen($fieldData));
									}
									$headerIndex++;
								}
								$fieldValues[] = $thisRow;
								if (count($fieldValues) >= 1000 && strpos($alterCommand, "limit") === false) {
									break;
								}
							}
							if (!empty($headers)) {
								$rowlength = 1;
								foreach ($headers as $thisHeader) {
									$thisLine = " " . str_pad($thisHeader['label'], $thisHeader['length'], " ") . " |";
									$rowlength += strlen($thisLine);
								}
								$separatorLine = str_repeat("-", $rowlength);
								$returnOutput[] = $separatorLine;
								$thisResult = "|";
								foreach ($headers as $thisHeader) {
									$thisLine = " " . str_pad($thisHeader['label'], $thisHeader['length'], " ") . " |";
									$thisResult .= $thisLine;
								}
								$returnOutput[] = $thisResult;
								$returnOutput[] = $separatorLine;
								foreach ($fieldValues as $thisRow) {
									$thisResult = "|";
									foreach ($thisRow as $index => $fieldValue) {
										$thisResult .= " " . str_pad($fieldValue, $headers[$index]['length'], " ") . " |";
									}
									$returnOutput[] = $thisResult;
								}
								$returnOutput[] = $separatorLine;
							}
							if (count($fieldValues) < $resultSet['row_count']) {
								$returnOutput[] = count($fieldValues) . " rows of " . $resultSet['row_count'] . " displayed";
							} else {
								$returnOutput[] = count($fieldValues) . " row" . (count($fieldValues) == 1 ? "" : "s");
							}
							break;
						default:
							$returnOutput[] = "Successful!";
					}
				}
				$alterCommand = "";
			}
			if (startsWith($alterLine, "delimiter")) {
				$delimiter = substr($alterLine, strlen("delimiter "));
			}
		}
		executeQuery("insert into database_alter_log (database_definition_id,log_date,user_id,alter_script) " .
			"values (?,now(),?,?)", $databaseDefinitionId, $GLOBALS['gUserId'], implode("\n", $alterScript));
		return array("alter_script" => $alterScript, "output" => $returnOutput, "errors" => $errorOutput);
	}

	public static function updateCorePages($parameters = array()) {
		addDebugLog("Update core pages run at " . date("m/d/Y H:i:s") . " parameters: " . jsonEncode($parameters), true);
		$returnOutput = array();
		$errorOutput = array();
        $limited = !empty($parameters['limited']);
        $filename = $parameters['file_name'] ?: "{$GLOBALS['gDocumentRoot']}/shared/pagecodes.txt";

        if(empty($parameters['action'])) {
            if($GLOBALS['gLocalExecution'] && $GLOBALS['gDevelopmentServer']) {
                $parameters['action'] = "export";
            }
        }

        # Check Core pages

        if ($parameters['action'] == "export") {
            if (($GLOBALS['gSystemName'] == "CORE" && $GLOBALS['gDevelopmentServer']) || ($GLOBALS['gSystemName'] == "COREWARE" && $GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId'])) {
                $managementTemplateId = getFieldFromId("template_id", "templates", "template_code", "MANAGEMENT");

                # Dump Template Data

                $coreTemplateDataInfo = array();
                $resultSet = executeQuery("select * from template_data order by data_name");
                while ($row = getNextRow($resultSet)) {
                    $coreTemplateDataInfo[] = $row;
                }

                # Dump Templates

                $coreTemplateInfo = array();
                $resultSet = executeQuery("select * from templates order by template_code");
                while ($row = getNextRow($resultSet)) {
                    $templateData = array();
                    $subSet = executeQuery("select data_name,sequence_number from template_data join template_data_uses using (template_data_id) where template_id = ?", $row['template_id']);
                    while ($subRow = getNextRow($subSet)) {
                        $templateData[] = $subRow;
                    }
                    $row['page_controls'] = $templateData;
                    $coreTemplateInfo[] = $row;
                }

# Dump Pages

                $corePageInfo = array();
                executeQuery("update pages set core_page = 1 where client_id = ?", $GLOBALS['gDefaultClientId']);
                executeQuery("update pages set requires_ssl = 1 where template_id = ?", $managementTemplateId);
                $resultSet = executeQuery("select * from pages where core_page = 1 and internal_use_only = 0 order by page_code");
                while ($row = getNextRow($resultSet)) {
                    $pageInfo = array();
                    $pageInfo['template_code'] = getFieldFromId("template_code", "templates", "template_id", $row['template_id']);
                    $pageInfo['subsystem_code'] = getFieldFromId("subsystem_code", "subsystems", "subsystem_id", $row['subsystem_id']);
                    $ignoreFields = array("subsystem_id", "page_tag", "analytics_code_chunk_id", "page_id", "version", "date_created", "creator_user_id", "template_id", "client_id");
                    $pageInfo['pages'] = array();
                    foreach ($row as $fieldName => $fieldData) {
                        if (!in_array($fieldName, $ignoreFields)) {
                            $pageInfo['pages'][$fieldName] = $fieldData;
                        }
                    }
                    $pageControls = array();
                    $subSet = executeQuery("select column_name,control_name,control_value from page_controls where page_id = ?", $row['page_id']);
                    while ($subRow = getNextRow($subSet)) {
                        $pageControls[] = $subRow;
                    }
                    $pageInfo['page_controls'] = $pageControls;
                    $pageInfo['page_access'] = array();
                    $subSet = executeQuery("select all_client_access,administrator_access,all_user_access,public_access,permission_level from page_access where page_id = ?", $row['page_id']);
                    if ($subRow = getNextRow($subSet)) {
                        $pageInfo['page_access'] = $subRow;
                    }
                    $pageData = array();
                    $subSet = executeQuery("select template_data_id,sequence_number,integer_data,number_data,text_data,date_data from page_data where page_id = ?", $row['page_id']);
                    while ($subRow = getNextRow($subSet)) {
                        $subRow['data_name'] = getFieldFromId("data_name", "template_data", "template_data_id", $subRow['template_data_id']);
                        unset($subRow['template_data_id']);
                        $pageData[] = $subRow;
                    }
                    $pageInfo['page_data'] = $pageData;
                    $corePageInfo[] = $pageInfo;
                }

                # Dump Menus with submenus

                $coreMenuInfo = array();
                executeQuery("update menus set core_menu = 1 where menu_code like 'CORE_%' and client_id = ?", $GLOBALS['gDefaultClientId']);
                $resultSet = executeQuery("select * from menus where client_id = ? and (menu_code like 'CORE_%' or menu_code = 'ADMIN_MENU')", $GLOBALS['gDefaultClientId']);
                while ($row = getNextRow($resultSet)) {
                    $thisMenu = array("menu_code" => $row['menu_code'], "description" => $row['description']);
                    $itemArray = array();
                    $itemSet = executeQuery("select description,link_title,link_url,(select menu_code from menus where menu_id = menu_items.menu_id) menu_code," .
                        "(select page_code from pages where page_id = menu_items.page_id) page_code,(select subsystem_code from subsystems where subsystem_id = menu_items.subsystem_id) subsystem_code," .
                        "not_logged_in,logged_in,administrator_access,query_string,separate_window from menu_items " .
                        "where menu_item_id in (select menu_item_id from menu_contents where menu_id = ?)", $row['menu_id']);
                    while ($itemRow = getNextRow($itemSet)) {
                        $itemArray[] = $itemRow;
                    }
                    $thisMenu['items'] = $itemArray;
                    $coreMenuInfo[$row['menu_id']] = $thisMenu;
                    if ($row['menu_code'] != "ADMIN_MENU") {
                        sortMenu($row['menu_code']);
                    }
                }

                # Dump Subsystems

                $coreSubsystems = array();
                $resultSet = executeQuery("select * from subsystems where internal_use_only = 0");
                while ($row = getNextRow($resultSet)) {
                    unset($row['version']);
                    $coreSubsystems[] = $row;
                }

                # Dump Tips

                $tipsInfo = array();
                $resultSet = executeQuery("select * from tips where internal_use_only = 0");
                while ($row = getNextRow($resultSet)) {
                    unset($row['version']);
                    $tipsInfo[] = $row;
                }

                # Dump Coreware Change Log

                $knowledgeBaseCategoryInfo = array();
                $resultSet = executeQuery("select * from knowledge_base_categories where knowledge_base_category_code like 'COREWARE_%' and client_id = ?", $GLOBALS['gDefaultClientId']);
                while ($row = getNextRow($resultSet)) {
                    unset($row['version']);
                    $knowledgeBaseCategoryInfo[] = $row;
                }
                $knowledgeBaseInfo = array();
                $resultSet = executeQuery("select *,(select knowledge_base_category_code from knowledge_base_categories where " .
                    "knowledge_base_category_id = (select knowledge_base_category_id from knowledge_base_category_links where " .
                    "knowledge_base_id = knowledge_base.knowledge_base_id limit 1)) knowledge_base_category_code from knowledge_base where knowledge_base_id in " .
                    "(select knowledge_base_id from knowledge_base_category_links where knowledge_base_category_id in " .
                    "(select knowledge_base_category_id from knowledge_base_categories where knowledge_base_category_code like 'COREWARE_%')) and client_id = ?", $GLOBALS['gDefaultClientId']);
                while ($row = getNextRow($resultSet)) {
                    unset($row['version']);
                    $knowledgeBaseInfo[] = $row;
                }

                # Dump System Messages

                $systemMessageInfo = array();
                $resultSet = executeQuery("select * from system_messages where client_id = ?", $GLOBALS['gDefaultClientId']);
                while ($row = getNextRow($resultSet)) {
                    unset($row['version']);
                    $systemMessageInfo[] = $row;
                }

                # Dump Core API

                $coreApiInfo = array();
                $coreApiInfo['parameters'] = array();
                $resultSet = executeQuery("select column_name,description,data_type from api_parameters");
                while ($row = getNextRow($resultSet)) {
                    $coreApiInfo['parameters'][] = $row;
                }
                $coreApiInfo['method_groups'] = array();
                $resultSet = executeQuery("select api_method_group_code,description from api_method_groups where internal_use_only = 0");
                while ($row = getNextRow($resultSet)) {
                    $coreApiInfo['method_groups'][] = $row;
                }
                $coreApiInfo['methods'] = array();
                $resultSet = executeQuery("select * from api_methods where internal_use_only = 0");
                while ($row = getNextRow($resultSet)) {
                    unset($row['version']);
                    $parameterSet = executeQuery("select api_parameters.column_name,api_method_parameters.detailed_description,api_method_parameters.required,api_method_parameters.sort_order from " .
                        "api_method_parameters join api_parameters using (api_parameter_id) where api_method_id = ?", $row['api_method_id']);
                    $parameterArray = array();
                    while ($parameterRow = getNextRow($parameterSet)) {
                        $parameterArray[] = $parameterRow;
                    }
                    $row['parameters'] = $parameterArray;
                    $groupSet = executeQuery("select api_method_group_code from api_method_groups where api_method_group_id in (select api_method_group_id from api_method_group_links where api_method_id = ?)", $row['api_method_id']);
                    $groupArray = array();
                    while ($groupRow = getNextRow($groupSet)) {
                        $groupArray[] = $groupRow['api_method_group_code'];
                    }
                    $row['groups'] = $groupArray;
                    $coreApiInfo['methods'][] = $row;
                }
                $coreApiInfo['apps'] = array();
                $resultSet = executeQuery("select * from api_apps where client_id = ?", $GLOBALS['gDefaultClientId']);
                while ($row = getNextRow($resultSet)) {
                    unset($row['version']);
                    $methodSet = executeQuery("select api_method_code from api_methods where api_method_id in (select api_method_id from api_app_methods where api_app_id = ?)", $row['api_app_id']);
                    $methodArray = array();
                    while ($methodRow = getNextRow($methodSet)) {
                        $methodArray[] = $methodRow['api_method_code'];
                    }
                    $row['methods'] = $methodArray;
                    $coreApiInfo['apps'][] = $row;
                }

                # Dump Core Documentation

                executeQuery("update documentation_entries set core_data = 1");
                executeQuery("update documentation_entries set documentation_entry_code = concat('CORE_',documentation_entry_code) where documentation_entry_code not like 'CORE_%'");
                executeQuery("update documentation_types set documentation_type_code = concat('CORE_',documentation_type_code) where documentation_type_code not like 'CORE_%'");
                $coreDocumentation = array();
                $coreDocumentation['types'] = array();
                $resultSet = executeQuery("select * from documentation_types");
                while ($row = getNextRow($resultSet)) {
                    $coreDocumentation['types'][] = $row;
                }
                $coreDocumentation['entries'] = array();
                $resultSet = executeQuery("select *,(select documentation_type_code from documentation_types where documentation_type_id = documentation_entries.documentation_type_id) documentation_type_code from documentation_entries");
                while ($row = getNextRow($resultSet)) {
                    unset($row['version']);
                    $parameterSet = executeQuery("select * from documentation_parameters where documentation_entry_id = ?", $row['documentation_entry_id']);
                    $parameterArray = array();
                    while ($parameterRow = getNextRow($parameterSet)) {
                        $parameterArray[] = $parameterRow;
                    }
                    $row['parameters'] = $parameterArray;

                    $tableSet = executeQuery("select *,(select table_name from tables where table_id = documentation_entry_tables.table_id) table_name from documentation_entry_tables where documentation_entry_id = ?", $row['documentation_entry_id']);
                    $tableArray = array();
                    while ($tableRow = getNextRow($tableSet)) {
                        $tableArray[] = $tableRow;
                    }
                    $row['tables'] = $tableArray;

                    $coreDocumentation['entries'][] = $row;
                }

                # Dump Help Desk Categories and Types

                $coreHelpDeskInfo = array();
                $coreHelpDeskInfo['categories'] = array();
                $resultSet = executeQuery("select help_desk_category_code,description from help_desk_categories where help_desk_category_code like 'CORE_%' and client_id = ?", $GLOBALS['gDefaultClientId']);
                while ($row = getNextRow($resultSet)) {
                    $coreHelpDeskInfo['categories'][] = $row;
                }
                $coreHelpDeskInfo['types'] = array();
                $resultSet = executeQuery("select help_desk_type_id,help_desk_type_code,description,user_id from help_desk_types where help_desk_type_code like 'CORE_%' and client_id = ?", $GLOBALS['gDefaultClientId']);
                while ($row = getNextRow($resultSet)) {
                    $categorySet = executeQuery("select help_desk_category_code,response_within,no_activity_notification from " .
                        "help_desk_type_categories join help_desk_categories using (help_desk_category_id) where help_desk_type_id = ?", $row['help_desk_type_id']);
                    $categoryArray = array();
                    while ($categoryRow = getNextRow($categorySet)) {
                        $categoryArray[] = $categoryRow;
                    }
                    $row['categories'] = $categoryArray;
                    unset($row['help_desk_type_id']);
                    $coreHelpDeskInfo['types'][] = $row;
                }

                $coreHelpDeskInfo['statuses'] = array();
                $resultSet = executeQuery("select * from help_desk_statuses where help_desk_status_code like 'CORE_%' and client_id = ?", $GLOBALS['gDefaultClientId']);
                while ($row = getNextRow($resultSet)) {
                    unset($row['help_desk_status_id']);
                    $coreHelpDeskInfo['statuses'][] = $row;
                }

                # Dump Help Desk Categories and Types

                $coreMerchantServicesInfo = array();
                $resultSet = executeQuery("select description,class_name from merchant_services");
                while ($row = getNextRow($resultSet)) {
                    $coreMerchantServicesInfo[] = $row;
                }

                # Dump Preference Groups and preferences

                $corePreferenceInfo = array();
                $corePreferenceInfo['groups'] = array();
                $resultSet = executeQuery("select preference_group_code,description from preference_groups");
                while ($row = getNextRow($resultSet)) {
                    $corePreferenceInfo['groups'][] = $row;
                }
                $corePreferenceInfo['preferences'] = array();
                $resultSet = executeQuery("select preference_id,preference_code,description,detailed_description,user_setable,client_setable,data_type,minimum_value," .
                    "maximum_value,choices,sort_order,temporary_setting,hide_system_value,internal_use_only from preferences where internal_use_only = 0");
                while ($row = getNextRow($resultSet)) {
                    $groupSet = executeQuery("select preference_group_code,sequence_number from preference_group_links join preference_groups using (preference_group_id) where preference_id = ?", $row['preference_id']);
                    $groupArray = array();
                    while ($groupRow = getNextRow($groupSet)) {
                        $groupArray[] = $groupRow;
                    }
                    $row['groups'] = $groupArray;
                    $corePreferenceInfo['preferences'][] = $row;
                }

                # Dump Fragment Types and Fragments

                $coreFragmentInfo = array();
                $coreFragmentInfo['types'] = array();
                $resultSet = executeQuery("select fragment_type_code,description from fragment_types where client_id = ?", $GLOBALS['gDefaultClientId']);
                while ($row = getNextRow($resultSet)) {
                    $coreFragmentInfo['types'][] = $row;
                }
                $coreFragmentInfo['fragments'] = array();
                $resultSet = executeQuery("select fragment_code,description,(select fragment_type_code from fragment_types where fragment_type_id = fragments.fragment_type_id) as fragment_type_code," .
                    "content,(select file_content from images where image_id = fragments.image_id) as image_content from fragments where fragment_type_id is not null and client_id = ?", $GLOBALS['gDefaultClientId']);
                while ($row = getNextRow($resultSet)) {
                    if (!empty($row['image_content'])) {
                        $row['image_content'] = base64_encode($row['image_content']);
                    }
                    $coreFragmentInfo['fragments'][] = $row;
                }

                $coreInfo = array("fragments" => $coreFragmentInfo, "merchant_services" => $coreMerchantServicesInfo, "preferences" => $corePreferenceInfo,
                    "help_desk" => $coreHelpDeskInfo, "api" => $coreApiInfo, "documentation" => $coreDocumentation, "pages" => $corePageInfo, "menus" => $coreMenuInfo,
                    "subsystems" => $coreSubsystems, "tips" => $tipsInfo, "knowledge_base" => $knowledgeBaseInfo, "knowledge_base_categories" => $knowledgeBaseCategoryInfo,
                    "system_messages" => $systemMessageInfo, "templates" => $coreTemplateInfo, "template_data" => $coreTemplateDataInfo);
                file_put_contents($filename, gzencode(jsonEncode($coreInfo), 9));
                $returnOutput[] = "Core data exported";
            } else {
                $errorOutput[] = "Core data can not be exported on this server";
                return array("output" => $returnOutput, "errors" => $errorOutput);
            }
        } else {
			$coreInfo = json_decode(gzdecode(file_get_contents($filename)), true);
			$clientRows = array();
			$resultSet = executeQuery("select * from clients where inactive = 0");
			while ($row = getNextRow($resultSet)) {
				$clientRows[] = $row;
			}

			$allPageCodes = array();
			$resultSet = executeQuery("select * from pages where client_id = ?", $GLOBALS['gDefaultClientId']);
			while ($row = getNextRow($resultSet)) {
				$allPageCodes[$row['page_code']] = $row['page_id'];
			}

			# Load Template Data

			if (!is_array($coreInfo['template_data'])) {
				$errorOutput[] = "Invalid template data JSON";
			} else {
				foreach ($coreInfo['template_data'] as $index => $templateDataInfo) {
					$templateDataId = getFieldFromId("template_data_id", "template_data", "data_name", $templateDataInfo['data_name']);
					if (empty($templateDataId)) {
						executeQuery("insert into template_data (description,data_name,data_type,data_size,choices,table_name,column_name,query_text,minimum_value,maximum_value,required,sort_order,group_identifier,allow_multiple,css_content) values " .
							"(?,?,?,?,?, ?,?,?,?,?, ?,?,?,?,?)", $templateDataInfo['description'], $templateDataInfo['data_name'], $templateDataInfo['data_type'], $templateDataInfo['data_size'], $templateDataInfo['choices'], $templateDataInfo['table_name'],
							$templateDataInfo['column_name'], $templateDataInfo['query_text'], $templateDataInfo['minimum_value'], $templateDataInfo['maximum_value'], $templateDataInfo['required'], $templateDataInfo['sort_order'],
							$templateDataInfo['group_identifier'], $templateDataInfo['allow_multiple'], $templateDataInfo['css_content']);
					}
				}
				$returnOutput[] = "Template Data Loaded";
			}

			if (!is_array($coreInfo['templates'])) {
				$errorOutput[] = "Invalid templates JSON";
			} else {
				foreach ($coreInfo['templates'] as $index => $templateInfo) {
					$templateRow = getRowFromId("templates", "template_code", $templateInfo['template_code'], "client_id is not null");
					if ($templateRow['client_id'] != $GLOBALS['gDefaultClientId']) {
						continue;
					}
					if (empty($templateRow)) {
						executeQuery("insert into templates (client_id,template_code,description,detailed_description,filename,addendum_filename,directory_name,css_content,javascript_code,content,include_crud) values " .
							"(?,?,?,?,?, ?,?,?,?,?, ?)", $GLOBALS['gClientId'], $templateInfo['template_code'], $templateInfo['description'], $templateInfo['detailed_description'], $templateInfo['file_name'],
							$templateInfo['addendum_filename'], $templateInfo['directory_name'], $templateInfo['css_content'],
							$templateInfo['javascript_code'], $templateInfo['content'], (empty($templateInfo['include_crud']) ? 0 : 1));
					} else {
						executeQuery("update templates set description = ?,detailed_description = ?,filename = ?,addendum_filename = ?,directory_name = ?,css_content = ?,javascript_code = ?,content = ?,include_crud = ? where template_id = ?",
							$templateInfo['description'], $templateInfo['detailed_description'], $templateInfo['filename'], $templateInfo['addendum_filename'], $templateInfo['directory_name'], $templateInfo['css_content'],
							$templateInfo['javascript_code'], $templateInfo['content'], (empty($templateInfo['include_crud']) ? 0 : 1), $templateRow['template_id']);
					}
				}
				$returnOutput[] = "Template Loaded";
			}

			# Load Subsystems

			if (!is_array($coreInfo['subsystems'])) {
				$errorOutput[] = "Invalid subsystems JSON";
			} else {
				foreach ($coreInfo['subsystems'] as $index => $subsystemInfo) {
					$subsystemId = getFieldFromId("subsystem_id", "subsystems", "subsystem_code", $subsystemInfo['subsystem_code']);
					if (empty($subsystemId)) {
						executeQuery("insert into subsystems (subsystem_code,description) values (?,?)", $subsystemInfo['subsystem_code'], $subsystemInfo['description']);
					} else {
						executeQuery("update subsystems set description = ? where subsystem_id = ?", $subsystemInfo['description'], $subsystemId);
					}
				}
				$returnOutput[] = "Subsystems Loaded";
			}

			# Load Merchant Services

			if (!is_array($coreInfo['merchant_services'])) {
				$errorOutput[] = "Invalid merchant services JSON";
			} else {
				foreach ($coreInfo['merchant_services'] as $index => $merchantServiceInfo) {
					$merchantServiceId = getFieldFromId("merchant_service_id", "merchant_services", "class_name", $merchantServiceInfo['class_name']);
					if (empty($merchantServiceId)) {
						executeQuery("insert into merchant_services (description,class_name) values (?,?)", $merchantServiceInfo['description'], $merchantServiceInfo['class_name']);
					}
				}
				$returnOutput[] = "Merchant Services Loaded";
			}

			# Load Pages

			if (!is_array($coreInfo['pages'])) {
				$errorOutput[] = "Invalid page codes JSON";
			} else {
				$pageUpdateCount = 0;
				foreach ($coreInfo['pages'] as $pageInfo) {
					$subsystemId = getFieldFromId("subsystem_id", "subsystems", "subsystem_code", $pageInfo['subsystem_code']);
					$resultSet = executeQuery("select * from pages where page_code = ?", $pageInfo['pages']['page_code']);
					if ($row = getNextRow($resultSet)) {
						if ($row['client_id'] != $GLOBALS['gDefaultClientId']) {
							$errorOutput[] = "Core page code '" . $pageInfo['pages']['page_code'] . "' already exists in a different client.";
							continue;
						}
						if (empty($pageInfo['pages']['css_content']) && !empty($row['css_content'])) {
							$pageInfo['pages']['css_content'] = $row['css_content'];
						}
						if (empty($pageInfo['pages']['javascript_code']) && !empty($row['javascript_code'])) {
							$pageInfo['pages']['javascript_code'] = $row['javascript_code'];
						}
						$updateSet = executeQuery("update pages set description = ?,core_page = 1,script_filename = ?,subsystem_id = ?,script_arguments = ?,exclude_sitemap = ?," .
							"not_searchable = ?,css_content = ?,javascript_code = ?,internal_use_only = ? where page_id = ?", $pageInfo['pages']['description'],
							$pageInfo['pages']['script_filename'], $subsystemId, $pageInfo['pages']['script_arguments'], $pageInfo['pages']['exclude_sitemap'],
							$pageInfo['pages']['not_searchable'], $pageInfo['pages']['css_content'], $pageInfo['pages']['javascript_code'], $pageInfo['pages']['internal_use_only'], $row['page_id']);
						$pageUpdateCount += $updateSet['affected_rows'];
						if (is_array($pageInfo['page_controls'])) {
							foreach ($pageInfo['page_controls'] as $pageControlInfo) {
								$pageControlId = getFieldFromId("page_control_id", "page_controls", "page_id", $row['page_id'], "column_name = ? and control_name = ?", $pageControlInfo['column_name'], $pageControlInfo['control_name']);
								if (empty($pageControlId)) {
									$resultSet = executeQuery("insert into page_controls (page_id,column_name,control_name,control_value) values (?,?,?,?)", $row['page_id'], $pageControlInfo['column_name'], $pageControlInfo['control_name'], $pageControlInfo['control_value']);
								} else {
									$resultSet = executeQuery("update page_controls set control_value = ? where page_control_id = ?", $pageControlInfo['control_value'], $pageControlId);
								}
							}
						}
						if (is_array($pageInfo['page_access'])) {
							$pageAccessCount = getFieldFromId("count(*)", "page_access", "page_id", $row['page_id']);
                            if(!$limited && ($pageAccessCount > 1 || empty($pageInfo['page_access']))) {
                                    executeQuery("delete from page_access where page_id = ?", $row['page_id']);
                            }
							$pageAccessRow = getRowFromId("page_access", "page_id", $row['page_id']);
							if (empty($pageAccessRow)) {
								if (!empty($pageInfo['page_access'])) {
									executeQuery("insert into page_access (page_id,all_client_access,administrator_access,all_user_access,public_access,permission_level) values (?,?,?,?,?,?)",
										$row['page_id'], (empty($pageInfo['page_access']['all_client_access']) ? 0 : 1), (empty($pageInfo['page_access']['administrator_access']) ? 0 : 1),
										(empty($pageInfo['page_access']['all_user_access']) ? 0 : 1), (empty($pageInfo['page_access']['public_access']) ? 0 : 1),
										(empty($pageInfo['page_access']['permission_level']) ? 0 : $pageInfo['page_access']['permission_level']));
								}
							} else {
								$corePageAccessRow = $pageInfo['page_access'];
								if ($pageAccessRow['all_client_access'] != $corePageAccessRow['all_client_access'] ||
									$pageAccessRow['administrator_access'] != $corePageAccessRow['administrator_access'] ||
									$pageAccessRow['all_user_access'] != $corePageAccessRow['all_user_access'] ||
									$pageAccessRow['public_access'] != $corePageAccessRow['public_access'] ||
									$pageAccessRow['permission_level'] != $corePageAccessRow['permission_level']) {
									executeQuery("update page_access set all_client_access = ?, administrator_access = ?, all_user_access = ?, " .
										"public_access = ?, permission_level = ? where page_access_id = ?", (empty($corePageAccessRow['all_client_access']) ? 0 : 1),
										(empty($corePageAccessRow['administrator_access']) ? 0 : 1), (empty($corePageAccessRow['all_user_access']) ? 0 : 1), (empty($corePageAccessRow['public_access']) ? 0 : 1),
										(empty($corePageAccessRow['permission_level']) ? 0 : $corePageAccessRow['permission_level']), $pageAccessRow['page_access_id']);
								}
							}
						}
						if (is_array($pageInfo['page_data'])) {
							foreach ($pageInfo['page_data'] as $pageDataInfo) {
								$templateDataId = getFieldFromId("template_data_id", "template_data", "data_name", $pageDataInfo['data_name']);
								$pageDataRow = getRowFromId("page_data", "page_id", $row['page_id'], "template_data_id = ?", $templateDataId);
								if (empty($pageDataRow)) {
									$resultSet = executeQuery("insert into page_data (page_id,template_data_id,sequence_number,integer_data,number_data,text_data,date_data) values " .
										"(?,?,?,?,?, ?,?)", $row['page_id'], $templateDataId, $pageDataInfo['sequence_number'], $pageDataInfo['integer_data'], $pageDataInfo['number_data'],
										$pageDataInfo['text_data'], $pageDataInfo['date_data']);
								} elseif ($pageDataRow['sequence_number'] != $pageDataInfo['sequence_number'] || $pageDataRow['integer_data'] != $pageDataInfo['integer_data'] || $pageDataRow['number_data'] != $pageDataInfo['number_data'] || $pageDataRow['text_data'] != $pageDataInfo['text_data'] || $pageDataRow['date_data'] != $pageDataInfo['date_data']) {
									$resultSet = executeQuery("update page_data set sequence_number = ?,integer_data = ?,number_data = ?,text_data = ?,date_data = ? where page_data_id = ?",
										$pageDataInfo['sequence_number'], $pageDataInfo['integer_data'], $pageDataInfo['number_data'],
										$pageDataInfo['text_data'], $pageDataInfo['date_data'], $pageDataRow['page_data_id']);
								}
							}
						}
					} else {
						$returnOutput[] = "Core page code '" . $pageInfo['pages']['page_code'] . "' created.";
						$templateId = getFieldFromId("template_id", "templates", "template_code", $pageInfo['template_code']);
						$columnNamePart = "client_id,date_created,creator_user_id,template_id,subsystem_id";
						$valuePart = "?,now(),?,?,?";
						$creatorUserId = $GLOBALS['gUserId'];
						if (empty($creatorUserId)) {
							$creatorUserId = getFieldFromId("user_id", "users", "user_id", "10000", "superuser_flag = 1 and inactive = 0");
						}
						if (empty($creatorUserId)) {
							$creatorUserId = getFieldFromId("user_id", "users", "superuser_flag", "1", "inactive = 0");
						}
						$parameters = array($GLOBALS['gClientId'], $creatorUserId, $templateId, $subsystemId);
						foreach ($pageInfo['pages'] as $fieldName => $fieldData) {
							$columnNamePart .= "," . $fieldName;
							$valuePart .= "," . "?";
							$parameters[] = $fieldData;
						}
						$insertSet = executeQuery("insert into pages (" . $columnNamePart . ") values (" . $valuePart . ")", $parameters);
						$pageId = $insertSet['insert_id'];
						$allPageCodes[$pageInfo['pages']['page_code']] = $pageId;
						if (is_array($pageInfo['page_controls'])) {
							foreach ($pageInfo['page_controls'] as $pageControlInfo) {
								$resultSet = executeQuery("insert into page_controls (page_id,column_name,control_name,control_value) values (?,?,?,?)", $pageId, $pageControlInfo['column_name'], $pageControlInfo['control_name'], $pageControlInfo['control_value']);
							}
						}
						if (is_array($pageInfo['page_access'])) {
							$pageAccessInfo = $pageInfo['page_access'];
							$resultSet = executeQuery("insert into page_access (page_id,all_client_access,administrator_access,all_user_access,public_access,permission_level) values (?,?,?,?,?,?)",
								$pageId, (empty($pageAccessInfo['all_client_access']) ? 0 : 1), (empty($pageAccessInfo['administrator_access']) ? 0 : 1), (empty($pageAccessInfo['all_user_access']) ? 0 : 1),
								(empty($pageAccessInfo['public_access']) ? 0 : 1), $pageAccessInfo['permission_level']);
						}
						if (is_array($pageInfo['page_data'])) {
							foreach ($pageInfo['page_data'] as $pageDataInfo) {
								$templateDataId = getFieldFromId("template_data_id", "template_data", "data_name", $pageDataInfo['data_name']);
								$resultSet = executeQuery("insert into page_data (page_id,template_data_id,sequence_number,integer_data,number_data,text_data,date_data,image_id,file_id) values " .
									"(?,?,?,?,?, ?,?,?,?)", $pageId, $templateDataId, $pageDataInfo['sequence_number'], $pageDataInfo['integer_data'], $pageDataInfo['number_data'],
									$pageDataInfo['text_data'], $pageDataInfo['date_data'], $pageDataInfo['image_id'], $pageDataInfo['file_id']);
							}
						}
					}
				}
				if ($pageUpdateCount > 0) {
					$returnOutput[] = $pageUpdateCount . " page" . ($pageUpdateCount == 1 ? "" : "s") . " updated";
				}
				$returnOutput[] = "Pages Loaded";
			}

			# Load Menus

			if (!is_array($coreInfo['menus'])) {
				$errorOutput[] = "Invalid menus JSON";
			} else {
				$subsystemArray = array();
				$resultSet = executeQuery("select * from subsystems");
				while ($row = getNextRow($resultSet)) {
					$subsystemArray[$row['subsystem_code']] = $row['subsystem_id'];
				}
				foreach ($clientRows as $clientRow) {
					$clientId = $clientRow['client_id'];
					$clientMenus = array();
					$coreMenus = array();
					$resultSet = executeQuery("select menu_id,menu_code,core_menu from menus where client_id = ?", $clientId);
					while ($row = getNextRow($resultSet)) {
						$clientMenus[$row['menu_code']] = $row['menu_id'];
						if ($row['core_menu']) {
							$coreMenus[] = $row['menu_id'];
						}
					}
					foreach ($coreInfo['menus'] as $index => $menuInfo) {
						if ($menuInfo['menu_code'] == "ADMIN_MENU") {
							foreach ($menuInfo['items'] as $menuItemInfo) {
								$thisMenuId = $clientMenus[$menuItemInfo['menu_code']];
								$thisPageId = $allPageCodes[$menuItemInfo['page_code']];
								if (empty($thisMenuId) && empty($thisPageId)) {
									continue;
								}
								$menuItemRow = getRowFromId("menu_items", "client_id", $clientId, "client_id = ? and menu_id <=> ? and page_id <=> ? and query_string <=> ? and " .
									"link_url is null and menu_item_id in (select menu_item_id from menu_contents where menu_id = ?)", $clientId, $thisMenuId, $thisPageId, $menuItemInfo['query_string'], $thisMenuId);
								if (empty($menuItemRow)) {
									$menuItemRow = getRowFromId("menu_items", "client_id", $clientId, "client_id = ? and menu_id <=> ? and page_id <=> ? and query_string <=> ? and " .
										"link_url is null", $clientId, $thisMenuId, $thisPageId, $menuItemInfo['query_string']);
								}
								$menuItemId = $menuItemRow['menu_item_id'];
								if (empty($menuItemId)) {
									continue;
								} else {
									if (strpos($menuItemInfo['link_title'], "fa-") !== false) {
										executeQuery("update menu_items set link_title = ? where menu_item_id = ?", $menuItemInfo['link_title'], $menuItemId);
									}
								}
							}
							continue;
						}
						$menuId = $clientMenus[$menuInfo['menu_code']];
						if (empty($menuId)) {
							$resultSet = executeQuery("insert into menus (client_id,menu_code,description,core_menu) values (?,?,?,1)",
								$clientId, $menuInfo['menu_code'], $menuInfo['description']);
							$menuId = $resultSet['insert_id'];
							$clientMenus[$menuInfo['menu_code']] = $menuId;
						} else {
							if ($menuInfo['menu_code'] != "ADMIN_MENU" && !in_array($menuId, $coreMenus)) {
								executeQuery("update menus set core_menu = 1 where menu_id = ?", $menuId);
							}
						}
						$menuContents = array();
						$resultSet = executeQuery("select * from menu_contents where menu_id = ?", $menuId);
						while ($row = getNextRow($resultSet)) {
							$menuContents[] = $row['menu_item_id'];
						}
						$usedMenuContents = array();
						foreach ($menuInfo['items'] as $menuItemInfo) {
							$thisMenuId = $clientMenus[$menuItemInfo['menu_code']];
							$thisPageId = $allPageCodes[$menuItemInfo['page_code']];
							if (empty($thisMenuId) && empty($thisPageId)) {
								continue;
							}
							$menuItemRow = getRowFromId("menu_items", "client_id", $clientId, "client_id = ? and menu_id <=> ? and page_id <=> ? and query_string <=> ? and " .
								"link_url is null and menu_item_id in (select menu_item_id from menu_contents where menu_id = ?)", $clientId, $thisMenuId, $thisPageId, $menuItemInfo['query_string'], $menuId);
							if (empty($menuItemRow)) {
								$menuItemRow = getRowFromId("menu_items", "client_id", $clientId, "client_id = ? and menu_id <=> ? and page_id <=> ? and query_string <=> ? and " .
									"link_url is null", $clientId, $thisMenuId, $thisPageId, $menuItemInfo['query_string']);
							}
							$menuItemId = $menuItemRow['menu_item_id'];
							$subsystemId = $subsystemArray[$menuItemInfo['subsystem_code']];
							if (empty($menuItemId)) {
								$resultSet = executeQuery("insert into menu_items (client_id,description,link_title,menu_id,page_id,subsystem_id,administrator_access,query_string,separate_window) values " .
									"(?,?,?,?,?,?,1,?,?)", $clientId, $menuItemInfo['description'], $menuItemInfo['link_title'], $thisMenuId, $thisPageId, $subsystemId, $menuItemInfo['query_string'], $menuItemInfo['separate_window']);
								$menuItemId = $resultSet['insert_id'];
								$returnOutput[] = "Created Menu Item '" . $menuItemInfo['description'] . "' for client " . $clientId;
							} else {
								do {
									$duplicateMenuItemId = getFieldFromId("menu_item_id", "menu_items", "client_id", $clientId, "menu_item_id <> ? and client_id = ? and menu_id <=> ? and " .
										"page_id <=> ? and query_string <=> ? and link_url is null and link_title = ?", $menuItemId, $clientId, $thisMenuId, $thisPageId,
										$menuItemRow['query_string'], $menuItemRow['link_title']);
									if (!empty($duplicateMenuItemId)) {
										executeQuery("delete from menu_contents where menu_item_id = ?", $duplicateMenuItemId);
										executeQuery("delete from menu_items where menu_item_id = ?", $duplicateMenuItemId);
									}
								} while (!empty($duplicateMenuItemId));
								if ($menuItemRow['description'] != $menuItemInfo['description'] || $subsystemId != $menuItemInfo['subsystem_id']) {
									executeQuery("update menu_items set description = ?,subsystem_id = ? where menu_item_id = ?",
										$menuItemInfo['description'], $subsystemId, $menuItemId);
								}

								if (strpos($menuItemInfo['link_title'], "fa-") !== false) {
									executeQuery("update menu_items set link_title = ? where menu_item_id = ?", $menuItemInfo['link_title'], $menuItemId);
								}
							}
							if ($menuInfo['menu_code'] != "ADMIN_MENU") {
								if (!in_array($menuItemId, $menuContents)) {
									executeQuery("insert ignore into menu_contents (menu_id,menu_item_id,sequence_number) values (?,?,6)", $menuId, $menuItemId);
									$menuContents[] = $menuItemId;
								}
								$usedMenuContents[] = $menuItemId;
							}
						}
						$deleteMenuContents = array_diff($menuContents, $usedMenuContents);
						if ($menuInfo['menu_code'] != "ADMIN_MENU" && !empty($deleteMenuContents)) {
							executeQuery("delete from menu_contents where menu_id = ? and menu_item_id in (" . implode(",", $deleteMenuContents) . ")", $menuId);
						}
						if ($menuInfo['menu_code'] != "ADMIN_MENU") {
							sortMenu($menuInfo['menu_code'], $clientId);
						}
					}
					$adminMenuId = getFieldFromId("menu_id", "menus", "menu_code", "ADMIN_MENU", "client_id = ?", $clientId);
					$menuItemId = getFieldFromId("menu_item_id", "menu_items", "link_url", "%USERMENUS%", "client_id = ?", $clientId);
					if (empty($menuItemId)) {
						$resultSet = executeQuery("insert into menu_items (client_id,description,link_title,link_url,administrator_access) values " .
							"(?,?,?,?,1)", $clientId, 'User Menu Items', 'User Menu Items', '%USERMENUS%');
						$menuItemId = $resultSet['insert_id'];
						$returnOutput[] = "Created User Menu Item for client " . $clientId;
						if (!empty($adminMenuId)) {
							executeQuery("insert ignore into menu_contents (menu_id,menu_item_id,sequence_number) values (?,?,1)", $adminMenuId, $menuItemId);
						}
					}
					if (empty($adminMenuId)) {
						$resultSet = executeQuery("insert into menus (client_id,menu_code,description) values (?,?,?)",
							$clientId, "ADMIN_MENU", "Core Admin Menu");
						$menuId = $resultSet['insert_id'];
						$returnOutput[] = "Created Admin Menu for client " . $clientId;

						executeQuery("insert ignore into menu_contents (menu_id,menu_item_id,sequence_number) values (?,?,1)", $menuId, $menuItemId);

						$sequenceNumber = 1;
						$adminMenuItems = array("MY_ACCOUNT", "CMS", "CRM", "DONOR_MANAGEMENT", "EDUCATION", "DEVELOPER", "HELP_DESK", "ORDERS", "PAYMENTS", "PRODUCTS_TOP", "TASKS_TOP", "EVENTS_FACILITIES", "SYSTEM");
						foreach ($adminMenuItems as $thisMenuCode) {
							$submenuId = getFieldFromId("menu_id", "menus", "menu_code", "CORE_" . $thisMenuCode, "client_id = ?", $clientId);
							if (!empty($submenuId)) {
								$menuItemId = getFieldFromId("menu_item_id", "menu_items", "menu_id", $submenuId, "client_id = ?", $clientId);
								if (empty($menuItemId)) {
									$description = getFieldFromId("description", "menu_items", "client_id", $GLOBALS['gDefaultClientId'],
										"menu_id = (select menu_id from menus where menu_code = 'CORE_" . $thisMenuCode . "' and client_id = ?)", $GLOBALS['gDefaultClientId']);
									$linkTitle = getFieldFromId("link_title", "menu_items", "client_id", $GLOBALS['gDefaultClientId'],
										"menu_id = (select menu_id from menus where menu_code = 'CORE_" . $thisMenuCode . "' and client_id = ?)", $GLOBALS['gDefaultClientId']);
									$resultSet = executeQuery("insert into menu_items (client_id,description,link_title,menu_id,administrator_access) values " .
										"(?,?,?,?,1)", $clientId, $description, $linkTitle, $submenuId);
									$menuItemId = $resultSet['insert_id'];
								}
								$sequenceNumber++;
								executeQuery("insert ignore into menu_contents (menu_id,menu_item_id,sequence_number) values (?,?,?)", $menuId, $menuItemId, $sequenceNumber);
								$returnOutput[] = "Added menu CORE_" . $thisMenuCode . " for client " . $clientId;
							}
						}

						$pageId = $GLOBALS['gAllPageCodes']["COREWARECHANGELOG"];
						$menuItemId = getFieldFromId("menu_item_id", "menu_items", "page_id", $pageId, "client_id = ?", $clientId);
						if (empty($menuItemId)) {
							$resultSet = executeQuery("insert into menu_items (client_id,description,link_title,page_id,administrator_access) values " .
								"(?,?,?,?,1)", $clientId, 'Coreware Change Log', 'Coreware Change Log', $pageId);
							$menuItemId = $resultSet['insert_id'];
						}
						$sequenceNumber++;
						executeQuery("insert ignore into menu_contents (menu_id,menu_item_id,sequence_number) values (?,?,?)", $menuId, $menuItemId, $sequenceNumber);
					}
					executeQuery("set @sequenceNumber := 0");
					executeQuery("update menu_contents set sequence_number = @sequenceNumber := @sequenceNumber + 10 where menu_id = (select menu_id from menus where client_id = ? and menu_code = 'ADMIN_MENU') ORDER BY sequence_number", $clientId);
				}
				$resultSet = executeQuery("select * from clients");
				while ($row = getNextRow($resultSet)) {
					removeCachedData("admin_menu", "*", $row['client_id']);
					removeCachedData("menu_contents", "*", $row['client_id']);
				}
				$returnOutput[] = "Menus Loaded";
			}

			# Load System Messages

			if (!is_array($coreInfo['system_messages'])) {
				$errorOutput[] = "Invalid tips JSON";
			} else {
				foreach ($coreInfo['system_messages'] as $messageInfo) {
					$systemMessageId = getFieldFromId("system_message_id", "system_messages", "system_message_code", $messageInfo['system_message_code']);
					if (empty($systemMessageId)) {
						executeQuery("insert into system_messages (client_id,system_message_code,description,content) values (?,?,?,?)", $GLOBALS['gClientId'], $messageInfo['system_message_code'], $messageInfo['description'], $messageInfo['content']);
					}
				}
				$returnOutput[] = "System Messages Loaded";
			}

			# Load Core API

			if (!is_array($coreInfo['api'])) {
				$errorOutput[] = "Invalid API JSON";
			} else {
				foreach ($coreInfo['api']['parameters'] as $thisParameter) {
					$apiParameterId = getFieldFromId("api_parameter_id", "api_parameters", "column_name", $thisParameter['column_name']);
					if (empty($apiParameterId)) {
						executeQuery("insert into api_parameters (column_name,description,data_type) values (?,?,?)", $thisParameter['column_name'], $thisParameter['description'], $thisParameter['data_type']);
					}
				}
				foreach ($coreInfo['api']['method_groups'] as $thisParameter) {
					$apiMethodGroupId = getFieldFromId("api_method_group_id", "api_method_groups", "api_method_group_code", $thisParameter['api_method_group_code']);
					if (empty($apiMethodGroupId)) {
						executeQuery("insert into api_method_groups (api_method_group_code,description) values (?,?)", $thisParameter['api_method_group_code'], $thisParameter['description']);
					}
				}
				foreach ($coreInfo['api']['methods'] as $thisMethod) {
					$apiMethodId = getFieldFromId("api_method_id", "api_methods", "api_method_code", $thisMethod['api_method_code']);
					if (empty($apiMethodId)) {
						$insertSet = executeQuery("insert into api_methods (api_method_code,description,detailed_description,sample_return,sort_order,public_access,all_user_access,log_usage,always_log,internal_use_only,inactive) values " .
							"(?,?,?,?,?, ?,?,?,?,?, ?)", $thisMethod['api_method_code'], $thisMethod['description'], $thisMethod['detailed_description'], $thisMethod['sample_return'], $thisMethod['sort_order'],
							$thisMethod['public_access'], $thisMethod['all_user_access'], $thisMethod['log_usage'], $thisMethod['always_log'], $thisMethod['internal_use_only'], $thisMethod['inactive']);
						$apiMethodId = $insertSet['insert_id'];
						$returnOutput[] = "API Method '" . $thisMethod['api_method_code'] . "' created.";
					}
					foreach ($thisMethod['parameters'] as $thisParameter) {
						$apiParameterId = getFieldFromId("api_parameter_id", "api_parameters", "column_name", $thisParameter['column_name']);
						if (empty($apiParameterId)) {
							$errorOutput[] = "Invalid API Parameter: " . $thisParameter['column_name'];
							continue;
						}
						$apiMethodParameterId = getFieldFromId("api_method_parameter_id", "api_method_parameters", "api_method_id", $apiMethodId, "api_parameter_id = ?", $apiParameterId);
						if (empty($apiMethodParameterId)) {
							executeQuery("insert into api_method_parameters (api_method_id,api_parameter_id,detailed_description,required,sort_order) values (?,?,?,?,?)",
								$apiMethodId, $apiParameterId, $thisParameter['detailed_description'], $thisParameter['required'], $thisParameter['sort_order']);
						}
					}
					foreach ($thisMethod['groups'] as $thisGroupCode) {
						$apiMethodGroupId = getFieldFromId("api_method_group_id", "api_method_groups", "api_method_group_code", $thisGroupCode);
						if (!empty($apiMethodGroupId)) {
							executeQuery("insert ignore into api_method_group_links (api_method_group_id,api_method_id) values (?,?)", $apiMethodGroupId, $apiMethodId);
						}
					}
				}

				foreach ($coreInfo['api']['apps'] as $thisApp) {
					foreach ($clientRows as $clientRow) {
						$apiAppId = getFieldFromId("api_app_id", "api_apps", "api_app_code", $thisApp['api_app_code'], "client_id = ?", $clientRow['client_id']);
						if (empty($apiAppId)) {
							$insertSet = executeQuery("insert into api_apps (client_id,api_app_code,description,default_timeout,current_version,minimum_version,recommended_version,log_usage,always_log,requires_license,inactive) values " .
								"(?,?,?,?,?, ?,?,?,?,?, ?)", $clientRow['client_id'], $thisApp['api_app_code'], $thisApp['description'], $thisApp['default_timeout'], $thisApp['current_version'],
								$thisApp['minimum_version'], $thisApp['recommended_version'], $thisApp['log_usage'], $thisApp['always_log'], $thisApp['requires_license'], $thisApp['inactive']);
							$apiAppId = $insertSet['insert_id'];
						}
						foreach ($thisApp['methods'] as $thisMethodCode) {
							$apiMethodId = getFieldFromId("api_method_id", "api_methods", "api_method_code", $thisMethodCode);
							if (empty($apiMethodId)) {
								$errorOutput[] = "Invalid API Method: " . $thisMethodCode;
								continue;
							}
							$apiAppMethodId = getFieldFromId("api_app_method_id", "api_app_methods", "api_method_id", $apiMethodId, "api_app_id = ?", $apiAppId);
							if (empty($apiAppMethodId)) {
								executeQuery("insert ignore into api_app_methods (api_app_id,api_method_id) values (?,?)", $apiAppId, $apiMethodId);
							}
						}
					}
				}
				$returnOutput[] = "API Loaded";
			}

			# Load Core Documentation

			if (!is_array($coreInfo['documentation'])) {
				$errorOutput[] = "Invalid Documentation JSON";
			} else {
				$documentationTypeArray = array();
				foreach ($coreInfo['documentation']['types'] as $thisType) {
					$documentationTypeId = getFieldFromId("documentation_type_id", "documentation_types", "documentation_type_code", $thisType['documentation_type_code']);
					$dataTable = new DataTable("documentation_types");
					$documentationTypeId = $dataTable->saveRecord(array("name_values" => $thisType, "primary_id" => $documentationTypeId));
					$documentationTypeArray[$thisType['documentation_type_code']] = $documentationTypeId;
				}
				foreach ($coreInfo['documentation']['entries'] as $thisEntry) {
					$documentationEntryId = getFieldFromId("documentation_entry_id", "documentation_entries", "documentation_entry_code", $thisEntry['documentation_entry_code']);
					$dataTable = new DataTable("documentation_entries");
					$thisEntry['documentation_type_id'] = $documentationTypeArray[$thisEntry['documentation_type_code']];
					$documentationEntryId = $dataTable->saveRecord(array("name_values" => $thisEntry, "primary_id" => $documentationEntryId));
					foreach ($thisEntry['parameters'] as $thisParameter) {
						$thisParameter['documentation_entry_id'] = $documentationEntryId;
						$documentationParameterId = getFieldFromId("documentation_parameter_id", "documentation_parameters", "documentation_entry_id", $documentationEntryId,
							"parameter_name = ?", $thisParameter['parameter_name']);
						$dataTable = new DataTable("documentation_parameters");
						$dataTable->saveRecord(array("name_values" => $thisParameter, "primary_id" => $documentationParameterId));
					}
					foreach ($thisEntry['tables'] as $thisTable) {
						$thisTable['documentation_entry_id'] = $documentationEntryId;
						$thisTable['table_id'] = getFieldFromId("table_id", "tables", "table_name", $thisTable['table_name']);
						$documentationTableId = getFieldFromId("documentation_entry_table_id", "documentation_entry_tables", "documentation_entry_id", $documentationEntryId,
							"table_id = ?", $thisParameter['table_id']);
						$dataTable = new DataTable("documentation_entry_tables");
						$dataTable->saveRecord(array("name_values" => $thisTable, "primary_id" => $documentationTableId));
					}
				}
				$returnOutput[] = "Documentation Loaded";
			}

			# Load Core Preference Groups & Preferences

			if (!is_array($coreInfo['preferences'])) {
				$errorOutput[] = "Invalid preference JSON";
			} else {
				foreach ($coreInfo['preferences']['groups'] as $thisGroup) {
					$preferenceGroupId = getFieldFromId("preference_group_id", "preference_groups", "preference_group_code", $thisGroup['preference_group_code']);
					if (empty($preferenceGroupId)) {
						executeQuery("insert ignore into preference_groups (preference_group_code,description) values (?,?)", $thisGroup['preference_group_code'], $thisGroup['description']);
					}
				}
				foreach ($coreInfo['preferences']['preferences'] as $thisPreference) {
					$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", $thisPreference['preference_code']);
					if (empty($preferenceId)) {
						$resultSet = executeQuery("insert into preferences (preference_code,description,detailed_description,user_setable,client_setable, data_type,minimum_value," .
							"maximum_value,choices, sort_order,hide_system_value,temporary_setting) values (?,?,?,?,?, ?,?,?,?,?, ?,?)", $thisPreference['preference_code'],
							$thisPreference['description'], $thisPreference['detailed_description'], $thisPreference['user_setable'], $thisPreference['client_setable'],
							$thisPreference['data_type'], $thisPreference['minimum_value'], $thisPreference['maximum_value'], $thisPreference['choices'],
							$thisPreference['sort_order'], $thisPreference['hide_system_value'], (empty($thisPreference['temporary_setting']) ? 0 : 1));
						$preferenceId = $resultSet['insert_id'];
						$returnOutput[] = "Created preference '" . $thisPreference['preference_code'] . "'";
					} else {
						$resultSet = executeQuery("update preferences set preference_code = ?,description = ?,detailed_description = ?,user_setable = ?,client_setable = ?, data_type = ?,minimum_value = ?," .
							"maximum_value = ?,choices = ?,sort_order = ?,temporary_setting = ?,hide_system_value = ?,internal_use_only = ? where preference_id = ?", $thisPreference['preference_code'],
							$thisPreference['description'], $thisPreference['detailed_description'], $thisPreference['user_setable'], $thisPreference['client_setable'],
							$thisPreference['data_type'], $thisPreference['minimum_value'], $thisPreference['maximum_value'], $thisPreference['choices'],
							$thisPreference['sort_order'], $thisPreference['temporary_setting'], $thisPreference['hide_system_value'], $thisPreference['internal_use_only'], $preferenceId);
					}
					if (!empty($thisPreference['groups'])) {
						foreach ($thisPreference['groups'] as $thisGroup) {
							$preferenceGroupId = getFieldFromId("preference_group_id", "preference_groups", "preference_group_code", $thisGroup['preference_group_code']);
							$preferenceGroupLinkId = getFieldFromId("preference_group_link_id", "preference_group_links", "preference_group_id", $preferenceGroupId, "preference_id = ?", $preferenceId);
							if (empty($preferenceGroupLinkId) && !empty($preferenceGroupId)) {
								executeQuery("insert ignore into preference_group_links (preference_group_id,preference_id,sequence_number) values (?,?,?)", $preferenceGroupId, $preferenceId, $thisGroup['sequence_number']);
							}
						}
					}
				}
				$returnOutput[] = "Preferences Loaded";
			}

			# Load Core Fragments

			if (!is_array($coreInfo['fragments'])) {
				$errorOutput[] = "Invalid fragment JSON";
			} else {
				foreach ($coreInfo['fragments']['types'] as $thisType) {
					$fragmentTypeId = getFieldFromId("fragment_type_id", "fragment_types", "fragment_type_code", $thisType['fragment_type_code']);
					if (empty($fragmentTypeId)) {
						executeQuery("insert into fragment_types (client_id,fragment_type_code,description) values (?,?,?)", $GLOBALS['gClientId'], $thisType['fragment_type_code'], $thisType['description']);
						$returnOutput[] = "Created fragment type '" . $thisType['fragment_type_code'] . "'";
					}
				}
				foreach ($coreInfo['fragments']['fragments'] as $thisFragment) {
					$fragmentId = getFieldFromId("fragment_id", "fragments", "fragment_code", $thisFragment['fragment_code']);
					$imageId = "";
					if (!empty($thisFragment['image_content'])) {
						$imageId = createImage(array("file_content" => base64_decode($thisFragment['image_content']), "filename" => "Fragment Image", "extension" => "jpg"));
					}
					$fragmentTypeId = getFieldFromId("fragment_type_id", "fragment_types", "fragment_type_code", $thisFragment['fragment_type_code']);
					if (empty($fragmentId)) {
						$resultSet = executeQuery("insert into fragments (client_id,fragment_code,description,fragment_type_id,image_id,content) values (?,?,?,?,?,?)", $GLOBALS['gClientId'], $thisFragment['fragment_code'],
							$thisFragment['description'], $fragmentTypeId, $imageId, $thisFragment['content']);
						$fragmentId = $resultSet['insert_id'];
						$returnOutput[] = "Created fragment '" . $thisFragment['fragment_code'] . "'";
					} else {
						$ignoreUpdates = getPreference("IGNORE_FRAGMENT_UPDATES");
						if (empty($ignoreUpdates)) {
							executeQuery("update fragments set description = ?,fragment_type_id = ?,image_id = ?,content = ? where fragment_id = ?", $thisFragment['description'],
								$fragmentTypeId, $imageId, $thisFragment['content'], $fragmentId);
						}
					}
				}
				$returnOutput[] = "Fragments Loaded";
			}

            if(!$limited) {
                # Load Tips

                if (!is_array($coreInfo['tips'])) {
                    $errorOutput[] = "Invalid tips JSON";
                } else {
                    executeQuery("delete from tips");
                    foreach ($coreInfo['tips'] as $tipInfo) {
                        executeQuery("insert into tips (content) values (?)", $tipInfo['content']);
                    }
                    $returnOutput[] = "Tips Loaded";
                }

                # Load Coreware Change Log knowledge base

                $primaryClientKnowledgeBaseCategories = array();
                if (!is_array($coreInfo['knowledge_base_categories'])) {
                    $errorOutput[] = "Invalid knowledge base category JSON";
                } else {
                    foreach ($clientRows as $clientRow) {
                        foreach ($coreInfo['knowledge_base_categories'] as $knowledgeBaseCategoryInfo) {
                            $knowledgeBaseCategoryId = getFieldFromId("knowledge_base_category_id", "knowledge_base_categories", "knowledge_base_category_code", $knowledgeBaseCategoryInfo['knowledge_base_category_code'], "client_id = ?", $clientRow['client_id']);
                            if (empty($knowledgeBaseCategoryId)) {
                                $resultSet = executeQuery("insert into knowledge_base_categories (client_id,knowledge_base_category_code,description,content,parent_knowledge_base_category_id,sort_order,internal_use_only,inactive) values (?,?,?,?,?, ?,?,?)",
                                    $clientRow['client_id'], $knowledgeBaseCategoryInfo['knowledge_base_category_code'], $knowledgeBaseCategoryInfo['description'],
                                    $knowledgeBaseCategoryInfo['content'], $knowledgeBaseCategoryInfo['parent_knowledge_base_category_id'],
                                    $knowledgeBaseCategoryInfo['sort_order'], $knowledgeBaseCategoryInfo['internal_use_only'], $knowledgeBaseCategoryInfo['inactive']);
                                $knowledgeBaseCategoryId = $resultSet['insert_id'];
                            }
                            if ($clientRow['client_id'] == $GLOBALS['gDefaultClientId']) {
                                $primaryClientKnowledgeBaseCategories[$knowledgeBaseCategoryInfo['knowledge_base_category_code']] = $knowledgeBaseCategoryId;
                            }
                        }
                    }
                    $returnOutput[] = "Knowledge Base Categories Loaded";
                }

                if (!is_array($coreInfo['knowledge_base'])) {
                    $errorOutput[] = "Invalid knowledge base JSON";
                } else {
                    if (!empty($primaryClientKnowledgeBaseCategories)) {
                        $knowledgeBaseIds = array();
                        $resultSet = executeQuery("select knowledge_base_id from knowledge_base_category_links where " .
                            "knowledge_base_category_id in (" . implode(",", $primaryClientKnowledgeBaseCategories) . ")");
                        while ($row = getNextRow($resultSet)) {
                            $knowledgeBaseIds[] = $row['knowledge_base_id'];
                        }
                        if (!empty($knowledgeBaseIds)) {
                            executeQuery("delete from knowledge_base_category_links where knowledge_base_id in (" . implode(",", $knowledgeBaseIds) . ")");
                            executeQuery("delete from knowledge_base where knowledge_base_id in (" . implode(",", $knowledgeBaseIds) . ")");
                        }
                        foreach ($coreInfo['knowledge_base'] as $knowledgeBaseInfo) {
                            $resultSet = executeQuery("insert into knowledge_base (client_id,title_text,link_url,excerpt,content,notes,date_entered,sort_order,internal_use_only,inactive) values (?,?,?,?,?, ?,?,?,?,?)",
                                $GLOBALS['gClientId'], $knowledgeBaseInfo['title_text'], $knowledgeBaseInfo['link_url'], $knowledgeBaseInfo['excerpt'],
                                $knowledgeBaseInfo['content'], $knowledgeBaseInfo['notes'], $knowledgeBaseInfo['date_entered'],
                                $knowledgeBaseInfo['sort_order'], $knowledgeBaseInfo['internal_use_only'], $knowledgeBaseInfo['inactive']);
                            executeQuery("insert ignore into knowledge_base_category_links (knowledge_base_category_id,knowledge_base_id) values (?,?)",
                                $primaryClientKnowledgeBaseCategories[$knowledgeBaseInfo['knowledge_base_category_code']], $resultSet['insert_id']);
                        }
                        $returnOutput[] = "Knowledge Base Loaded";
                    }
                }

                # Load Core Help Desk Types & Categories

                if (!is_array($coreInfo['help_desk'])) {
                    $errorOutput[] = "Invalid help desk JSON";
                } else {
                    foreach ($coreInfo['help_desk']['categories'] as $thisCategory) {
                        foreach ($clientRows as $clientRow) {
                            $helpDeskCategoryId = getFieldFromId("help_desk_category_id", "help_desk_categories", "help_desk_category_code", $thisCategory['help_desk_category_code'], "client_id = ?", $clientRow['client_id']);
                            if (empty($helpDeskCategoryId)) {
                                executeQuery("insert into help_desk_categories (client_id,help_desk_category_code,description) values (?,?,?)", $clientRow['client_id'], $thisCategory['help_desk_category_code'], $thisCategory['description']);
                            }
                        }
                    }
                    foreach ($coreInfo['help_desk']['types'] as $thisType) {
                        foreach ($clientRows as $clientRow) {
                            $helpDeskTypeId = getFieldFromId("help_desk_type_id", "help_desk_types", "help_desk_type_code", $thisType['help_desk_type_code'], "client_id = ?", $clientRow['client_id']);
                            if (empty($helpDeskTypeId)) {
                                $insertSet = executeQuery("insert into help_desk_types (client_id,help_desk_type_code,description,user_id) values " .
                                    "(?,?,?,?)", $clientRow['client_id'], $thisType['help_desk_type_code'], $thisType['description'], $thisType['user_id']);
                                $helpDeskTypeId = $insertSet['insert_id'];
                            }
                            foreach ($thisType['categories'] as $thisCategory) {
                                $helpDeskCategoryId = getFieldFromId("help_desk_category_id", "help_desk_categories", "help_desk_category_code", $thisCategory['help_desk_category_code'], "client_id = ?", $clientRow['client_id']);
                                if (empty($helpDeskCategoryId)) {
                                    $errorOutput[] = "Invalid Help Desk Category: " . $thisCategory['help_desk_category_code'];
                                    continue;
                                }
                                $helpDeskTypeCategoryId = getFieldFromId("help_desk_type_category_id", "help_desk_type_categories", "help_desk_type_id", $helpDeskTypeId, "help_desk_category_id = ?", $helpDeskCategoryId);
                                if (empty($helpDeskTypeCategoryId)) {
                                    executeQuery("insert into help_desk_type_categories (help_desk_type_id,help_desk_category_id,response_within,no_activity_notification) values (?,?,?,?)",
                                        $helpDeskTypeId, $helpDeskCategoryId, $thisCategory['response_within'], $thisCategory['no_activity_notification']);
                                }
                            }
                        }
                    }
                    foreach ($coreInfo['help_desk']['statuses'] as $thisStatus) {
                        foreach ($clientRows as $clientRow) {
                            $helpDeskStatusId = getFieldFromId("help_desk_status_id", "help_desk_statuses", "help_desk_status_code", $thisStatus['help_desk_status_code'], "client_id = ?", $clientRow['client_id']);
                            if (empty($helpDeskStatusId)) {
                                $insertSet = executeQuery("insert into help_desk_statuses (client_id,help_desk_status_code,description,close_after_days,no_notifications) values " .
                                    "(?,?,?,?,?)", $clientRow['client_id'], $thisStatus['help_desk_status_code'], $thisStatus['description'], $thisStatus['close_after_days'], $thisStatus['no_notifications']);
                                $helpDeskStatusId = $insertSet['insert_id'];
                            }
                        }
                    }
                    $returnOutput[] = "Help Desk Loaded";
                }
            }
		}
		return array("output" => $returnOutput, "errors" => $errorOutput);
	}

	/**
	 * function isControlTable
	 *
	 * looking for a table that has an optional client ID, optional control code, description, sort order, internal use only, and inactive, but nothing else.
	 * @param $tableName
	 * @return bool
	 */

	public static function isControlTable($tableName, $allowForeignKeys = false) {
		if (empty($tableName)) {
			return false;
		}
		if (!$GLOBALS['gPrimaryDatabase']->tableExists($tableName)) {
			return false;
		}
		$dataSource = new DataSource($tableName);
		$primaryTable = $dataSource->getPrimaryTable();
		$columns = $primaryTable->getColumns();
		if (count($columns) > 10) {
			return false;
		}
		$columnList = array();
		$requiredFields = array($primaryTable->getPrimaryKey(), "description", "sort_order", "internal_use_only", "inactive", "version");
		foreach ($columns as $thisColumn) {
			$dataType = $thisColumn->getControlValue("mysql_type");
			if (!in_array($dataType, array("date", "int", "varchar", "decimal", "tinyint", "text", "mediumtext"))) {
				return false;
			}
			if (in_array($thisColumn->getName(), $requiredFields)) {
				$requiredFields = array_diff($requiredFields, array($thisColumn->getName()));
			} elseif ($thisColumn->getName() != "client_id") {
				$columnList[] = $thisColumn->getName();
			}
		}
		if (count($requiredFields) > 0) {
			return false;
		}
		if (!$allowForeignKeys) {
			foreach ($columnList as $columnName) {
				if (substr($columnName, -3) == "_id") {
					return false;
				}
			}
		}
		return true;
	}

	function closeConnection() {
		if ($this->iDBConnection) {
			mysqli_close($this->iDBConnection);
			unset($this->iDBConnection);
		}
	}

	function getAddNewInfo($tableName) {
		if (empty($tableName)) {
			return false;
		}
		if (!self::$iAddNewInfo) {
			self::$iAddNewInfo = getCachedData("add_new_control_table_info", $this->iDatabaseName, true);
		}
		if (empty(self::$iAddNewInfo)) {
			self::$iAddNewInfo = array();
			$resultSet = executeQuery("select *,(select link_name from pages where page_code = tables.page_code) link_name," .
				"(select script_filename from pages where page_code = tables.page_code) script_filename from tables where page_code is not null");
			while ($row = getNextRow($resultSet)) {
				$linkUrl = $row['link_name'];
				if (empty($linkUrl)) {
					$linkUrl = $row['script_filename'];
				}
				if (empty($linkUrl)) {
					continue;
				}
				$linkUrl .= (strpos($linkUrl, "?") === false ? "?" : "&") . "url_page=new";
				self::$iAddNewInfo[$row['table_name']] = array("table_name" => $row['table_name'], "page_code" => $row['page_code'], "link_url" => $linkUrl);
			}
			setCachedData("add_new_control_table_info", $this->iDatabaseName, self::$iAddNewInfo, 24, true);
		}
		if (array_key_exists($tableName, self::$iAddNewInfo)) {
			$addNewInfo = self::$iAddNewInfo[$tableName];
			if (canAccessPageCode($addNewInfo['page_code']) <= _READONLY) {
				return false;
			}
			return $addNewInfo;
		}
		return false;
	}

	function logLastEcommerceError($accountInformation, $responseMessage, $failure) {
		$this->iLastEcommerceError = array("account_information" => $accountInformation, "response_message" => $responseMessage, "failure" => $failure);
	}

	function getQueryStatements() {
		return $this->iQueryStatements;
	}

	function tableExists($tableName) {
		if (empty($tableName)) {
			return false;
		}
		if (!self::$iTablesFound || !is_array(self::$iTablesFound)) {
			self::$iTablesFound = getCachedData("existing_tables", $this->iDatabaseName, true);
		}
		if (!self::$iTablesFound || !is_array(self::$iTablesFound)) {
			self::$iTablesFound = array();
			$resultSet = executeQuery("select TABLE_NAME from information_schema.tables where table_schema = '" . $this->getName() . "'");
			while ($row = getNextRow($resultSet)) {
				self::$iTablesFound[$row['TABLE_NAME']] = $row['TABLE_NAME'];
			}
			$this->freeResult($resultSet);
			setCachedData("existing_tables", $this->iDatabaseName, self::$iTablesFound, 24, true);
		}
		if (!array_key_exists($tableName, self::$iTablesFound)) {
			$resultSet = executeQuery("select TABLE_NAME from information_schema.tables where table_name = ? and table_schema = '" . $this->getName() . "'", $tableName);
			if ($row = getNextRow($resultSet)) {
				self::$iTablesFound[$row['TABLE_NAME']] = $row['TABLE_NAME'];
			}
			$this->freeResult($resultSet);
			if (array_key_exists($tableName, self::$iTablesFound)) {
				setCachedData("existing_tables", $this->iDatabaseName, self::$iTablesFound, 24, true);
			}
		}
		return array_key_exists($tableName, self::$iTablesFound);
	}

	/**
	 *    function getName
	 *
	 *  Gets the name of the Database
	 *
	 * @return string $iDatabaseName
	 */
	function getName() {
		return $this->iDatabaseName;
	}

	function freeResult($resultSet) {
		if (empty($resultSet['result']) || !is_object($resultSet['result'])) {
			return;
		}
		mysqli_free_result($resultSet['result']);
		unset($resultSet);
	}

	function viewExists($tableName) {
		if (empty($tableName)) {
			return false;
		}
		if (!is_array(self::$iViewsFound)) {
			self::$iViewsFound = getCachedData("existing_views", $this->iDatabaseName, true);
		}
		if (!is_array(self::$iViewsFound)) {
			self::$iViewsFound = array();
			$resultSet = executeQuery("select TABLE_NAME from information_schema.views where table_schema = '" . $this->getName() . "'");
			while ($row = getNextRow($resultSet)) {
				self::$iViewsFound[$row['TABLE_NAME']] = $row['TABLE_NAME'];
			}
			$this->freeResult($resultSet);
			setCachedData("existing_views", $this->iDatabaseName, self::$iViewsFound, 24, true);
		}
		return array_key_exists($tableName, self::$iViewsFound);
	}

	/**
	 *    function ignoreError
	 *
	 *  Sets ignoreError Flag
	 */
	function ignoreError($logError) {
		$this->iIgnoreError = $logError;
	}

	/**
	 *    function makeDateParameter
	 *
	 *  if input data is not false or empty then format it as Year-Month-Day, the format required by mysql
	 *
	 * @param string $input
	 * @return date formatted for database
	 */
	function makeDateParameter($input) {
		$dateString = strtotime($input);
		if ($dateString === false || empty($input) || strlen($input) == 0) {
			return "";
		}
		return date("Y-m-d", $dateString);
	}

	/**
	 *    function makeDatetimeParameter
	 *
	 *  As long as input data is valid, format date as Y-m-d and Time as H:i:s
	 *
	 * @param string $dateValue
	 * @param string $timeValue
	 * @return string datetime formatted as database requires
	 */
	function makeDatetimeParameter($dateValue, $timeValue = "") {
		$dateString = strtotime($dateValue);
		if (!empty($timeValue)) {
			$timeString = strtotime($timeValue);
		} else {
			$timeString = true;
		}
		if ($dateString === false || $timeString === false || empty($dateValue) || strlen($dateValue) == 0) {
			return "";
		}
		$dateTimeValue = date("Y-m-d H:i:s", $dateString);
		if (!empty($timeValue)) {
			$timeValue = date("H:i:s", $timeString);
			$dateTimeValue = substr($dateTimeValue, 0, 10) . " " . $timeValue;
		}
		return $dateTimeValue;
	}

	/**
	 *    startTransaction
	 *
	 *    Sends Start Transaction command to the query statement.
	 *
	 * @return ResultSet generated by executeQuery command
	 */
	function startTransaction() {
		$this->iLastErrorLogged = "";
		$this->iLastEcommerceError = false;
		if ($this->iReadonlyConnection) {
			return $GLOBALS['gPrimaryDatabase']->startTransaction();
		}
		return $this->executeQuery("start transaction");
	}

	/**
	 *    function executeQuery
	 *
	 *    Execute a query or prepared statement with the given parameters. The function uses prepared statements and
	 *    parameterized queries. This is the best defense against SQL injection attacks. The function allows the query parameters
	 *    to be sent as the second argument or as a series of arguments after the query text or statement. If the second
	 *    argument is not an array, the function generates an array from the second and subsequent arguments of the function call.
	 *
	 * @return ResultSet array generated by the query
	 */
	function executeQuery($query, $parameters = array(), $prepareOnly = false, $statementQuery = "") {
		if (empty($GLOBALS['gLoggedHighMemory']) && !empty($GLOBALS['gMaximumMemory'])) {
			$memoryUsage = memory_get_usage();
			if (!empty($memoryUsage) && $memoryUsage > ($GLOBALS['gMaximumMemory'] * .75)) {
				$dbt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 4);
				$callers = array();
				for ($index = 1; $index < count($dbt); $index++) {
					$callers[] = $dbt[$index]['class'] . "::" . $dbt[$index]['function'] . "(" . $dbt[$index]['file'] . ", line " . $dbt[$index]['line'] . ")";
				}
				addDebugLog("High Memory: " . $_SERVER['HTTP_HOST'] . ":" . $_SERVER['REQUEST_URI'] . ":" . $memoryUsage . "\n" . implode("\n", $callers), true);
				$GLOBALS['gLoggedHighMemory'] = true;
			}
		}
		if (empty($GLOBALS['gLoggedLongRunning']) && !$GLOBALS['gCommandLine']) {
			$elapsedTime = getMilliseconds() - $GLOBALS['gOverallStartTime'];
			if ($elapsedTime > 600000) {
				addDebugLog("Long Running Process: " . $_SERVER['HTTP_HOST'] . ":" . $_SERVER['REQUEST_URI'] . ":" . $elapsedTime, true);
				$GLOBALS['gLoggedLongRunning'] = true;
			}
		}

		$startTime = getMilliseconds();
		$executeOnly = (is_object($query) && get_class($query) == "mysqli_stmt");
		if (is_array($query) && array_key_exists("statement", $query)) {
			$executeOnly = true;
			$statementQuery = $query['query'];
			$query = $query['statement'];
		}
		$resultSet = array();
		if (!is_array($parameters)) {
			$parameters = func_get_args();
			unset($parameters[0]);
			$prepareOnly = false;
			$statementQuery = "";
		}
		if (count($parameters) == 0 && !$prepareOnly && !$executeOnly) {
			$result = $this->iDBConnection->query($query);
		} else {
			$types = "";
			foreach ($parameters as $index => $parameter) {
				if (is_array($parameter)) {
					$parameter = jsonEncode($parameter);
				}
				if (strlen(trim($parameter, "\0 \t")) == 0) {
					$parameters[$index] = null;
					$types .= "s";
				} elseif (is_int($parameter)) {
					$types .= 'i';
				} elseif (is_float($parameter)) {
					$types .= 'd';
				} elseif (is_string(trim($parameter, "\0 \t"))) {
					if (ctype_print($parameter) && strlen($parameter) < 5000) {
						$parameters[$index] = trim($parameter, "\0 \t");
					}
					$types .= 's';
				} else {
					$types .= 'b';
				}
			}
			$parameterReferences = array();
			$parameterReferences[] = $types;
			foreach ($parameters as $index => $parameter) {
				$parameterReferences[] = &$parameters[$index];
			}
            if (!$executeOnly) {
                if (array_key_exists($query, $this->iQueryStatements)) {
                    $statement = $this->iQueryStatements[$query];
                    // move statement just used to the end of the array
                    unset($this->iQueryStatements[$query]);
                    $this->iQueryStatements[$query] = $statement;
                } else {
                    $statement = $this->iDBConnection->prepare($query);
                    if ($statement) {
                        $this->iQueryStatements[$query] = $statement;
                    }
                }
                while(count($this->iQueryStatements) >= $this->statementPoolSize) {
                    // remove and close the least recently used prepared statement
                    $oldestStatement = array_shift($this->iQueryStatements);
                    $oldestStatement->close();
                }
            } else {
                $statement = $query;
                $query = $statementQuery;
            }

			if ($statement && !$prepareOnly) {
				if (call_user_func_array(array($statement, 'bind_param'), $parameterReferences)) {
					$statement->execute();
					$result = $statement->get_result();
				}
			}
			$resultSet['parameters'] = $parameterReferences;
		}

		$resultSet['query'] = $query;
		if (!$GLOBALS['gNoTranslation'] && !empty($GLOBALS['gLanguageId']) && $GLOBALS['gLanguageId'] != $GLOBALS['gEnglishLanguageId']) {
			if (empty($GLOBALS['gLanguageTableName'])) {
				$queryParts = explode(" ", str_replace(" ,", ",", str_replace(", ", ",", str_replace(" join ", ",", $query))));
				if ($queryParts[0] == "select") {
					$foundFrom = false;
					$tableList = "";
					foreach ($queryParts as $thisPart) {
						if ($foundFrom) {
							$tableList = $thisPart;
							break;
						}
						if (strtolower($thisPart) == "from") {
							$foundFrom = true;
						}
					}
					$resultSet['table_list'] = explode(",", $tableList);
				}
			} else {
				$resultSet['table_list'] = $GLOBALS['gLanguageTableName'];
			}
		}
		$resultSet['sql_error'] = $this->iDBConnection->error;
		$resultSet['sql_error_number'] = $this->iDBConnection->errno;
		$resultSet['affected_rows'] = $this->iDBConnection->affected_rows;
		$variable = mysqli_info($this->iDBConnection);
		if (startsWith($variable, "Rows matched:")) {
			$parts = explode(" ", $variable);
			$resultSet['matched_rows'] = $parts[2];
		}
		$resultSet['insert_id'] = (empty($this->iDBConnection->insert_id) ? "" : $this->iDBConnection->insert_id);
		if ($prepareOnly) {
			$resultSet['statement'] = empty($statement) ? null : $statement;
		} else {
			$resultSet['result'] = empty($result) ? null : $result;
			if (!empty($result) && is_object($result)) {
				$resultSet['row_count'] = $result->num_rows;
			}
		}
		if (!empty($resultSet['sql_error'])) {
			$this->logError($resultSet['sql_error'] . " (" . $resultSet['sql_error_number'] . ")", $query, $parameters);
		}
		$endTime = getMilliseconds();
		$elapsedTime = round(($endTime - $startTime) / 1000, 2);
		if (empty($GLOBALS['gShuttingDown']) && !empty($query) && !$executeOnly && is_string($query) && strlen($query) < 300) {
			if (empty($GLOBALS['gQueryCounts']) || !is_array($GLOBALS['gQueryCounts'])) {
				$GLOBALS['gQueryCounts'] = array();
			}
			$hash = md5($query);
			if (!array_key_exists($hash, $GLOBALS['gQueryCounts'])) {
				$GLOBALS['gQueryCounts'][$hash] = array("query_text" => $query, "count" => 0);
			}
			$GLOBALS['gQueryCounts'][$hash]['count']++;
		}
		$logLimit = ($GLOBALS['gCommandLine'] ? 20 : 5);
		if (!$this->iReadonlyConnection && ($elapsedTime > $logLimit || (!$executeOnly && is_string($query) && !empty($query) && ($GLOBALS['gLogLiveQueries'] || ($GLOBALS['gDevelopmentServer'] && $GLOBALS['gLogDatabaseQueries']))))) {
			$logStatement = $this->iDBConnection->prepare("insert into query_log (query_text,content,elapsed_time,user_id) values (?,?,?,?)");
			if ($logStatement !== false) {
				$logParameters = array();
				$logParameters[] = "ssdi";
				$logParameters[] = &$query;
				$queryLogText = "";
				foreach ($parameters as $parameter) {
					$queryLogText .= $parameter . "\n";
				}
				$bt = debug_backtrace();
				foreach ($bt as $caller) {
                    $queryLogText .= " : " . $caller['file'] . "," . $caller['function'] .  "(), line " . $caller['line'] ;
				}
				$queryLogText .= " : " . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
				$logParameters[] = &$queryLogText;
				$logParameters[] = &$elapsedTime;
				$logParameters[] = &$GLOBALS['gUserId'];
				if (call_user_func_array(array($logStatement, 'bind_param'), $logParameters)) {
					$logStatement->execute();
				}
			}
		}
		return $resultSet;
	}

	/**
	 *    function logError
	 *
	 *    Log error/s to the database and send an email to developer/s about the error. The code checks to see if this particular
	 *    error has been logged in this page. If it has, the logging and emailing is skipped. This can be improved to store
	 *    all logged errors for a session (in the $_SESSION variable) or even checking the database to see if that error
	 *    has been logged recently. This should only be done later if the current scheme is not sufficient.
	 *
	 * @param string text of the error message
	 * @param string Query that produced the error
	 * @param array parameters that were used in the query
	 */
	function logError($errorMessage, $errorQuery = "", $parameters = array()) {
		foreach (self::$iIgnoreErrorList as $thisIgnoreError) {
			if (strpos($errorMessage, $thisIgnoreError) !== false) {
                self::$iIgnoreErrorCounts[$thisIgnoreError]++;
				return;
			}
		}
		if ($this->iReadonlyConnection) {
			return $GLOBALS['gPrimaryDatabase']->logError($errorMessage, $errorQuery, $parameters);
		}
		if ($this->iIgnoreError || !empty($GLOBALS['gDontLogDatabaseErrors'])) {
			return;
		}
		if (!is_array($this->iLoggedErrorQueryText)) {
			$this->iLoggedErrorQueryText = array();
		}
		if (!empty($errorQuery) && in_array($errorQuery, $this->iLoggedErrorQueryText)) {
			return;
		}
		if (!is_array($this->iLoggedErrorMessages)) {
			$this->iLoggedErrorMessages = array();
		}
		if (!empty($errorMessage) && in_array($errorMessage, $this->iLoggedErrorMessages)) {
			return;
		}
		if (strpos($errorQuery, "insert into error_log") !== false) {
			return;
		}
		if (!empty($errorQuery)) {
			$this->iLoggedErrorQueryText[] = $errorQuery;
			$logId = getFieldFromId("log_id", "error_log", "query_text", $errorQuery, "date(error_time) = current_date");
			if (!empty($logId)) {
				return;
			}
		}
		if (!empty($errorMessage)) {
			$this->iLoggedErrorMessages[] = $errorMessage;
			$logId = getFieldFromId("log_id", "error_log", "error_message", $errorMessage, "date(error_time) = current_date");
			if (!empty($logId)) {
				return;
			}
		}

		$sendToAddresses = getNotificationEmails("ERROR_LOG", $GLOBALS['gDefaultClientId']);
		$emailSet = executeQuery("select * from page_error_notifications where page_id = ?", $GLOBALS['gPageId']);
		while ($emailRow = getNextRow($emailSet)) {
			$sendToAddresses[] = $emailRow['email_address'];
		}
		if (!empty($GLOBALS['gPageRow']['subsystem_id'])) {
			$emailSet = executeQuery("select * from subsystem_error_notifications where subsystem_id = ?", $GLOBALS['gPageRow']['subsystem_id']);
			while ($emailRow = getNextRow($emailSet)) {
				$sendToAddresses[] = $emailRow['email_address'];
			}
		}
		$additionalContent = $GLOBALS['gPageCode'] . ", " . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] .
			($GLOBALS['gLoggedIn'] ? ", user: " . $GLOBALS['gUserRow']['user_name'] : "");

		$emailText = "<html>\n<body>\n<p>An error as occurred on " . date("m/d/Y g:ia") . " (Server: " . gethostname() . ", Client: " . $GLOBALS['gClientRow']['client_code'] . ")</p>\n" .
			"<p>The error message is as follows:</p>\n" .
			"<p>" . $errorMessage . (empty($errorQuery) ? "" : "</p>\n<p>The query that produced the error is: " .
				$errorQuery) . "</p>\n<p>Some Additional Information: " . $additionalContent . "</p>\n<p>Backtrace is:<\p>\n";

		$backtrace = debug_backtrace(false);
		foreach ($backtrace as $thisBacktrace) {
			if (in_array($thisBacktrace['function'], array("logError"))) {
				continue;
			}
			$args = "";
			if (isset($thisBacktrace['args']) && is_array($thisBacktrace['args'])) {
				foreach ($thisBacktrace['args'] as &$arg) {
					if (is_object($arg)) {
						$arg = 'CONVERTED OBJECT OF CLASS ' . get_class($arg);
					}
				}
			}
			$function = $thisBacktrace['function'] . '(' . (isset($thisBacktrace['args']) && is_array($thisBacktrace) ? implode(", ", $thisBacktrace['args']) : "") . ')';
			$emailText .= "<p>Function: " . $function . ", line: " . $thisBacktrace['line'] . ", file: " . $thisBacktrace['file'] . ", class: " . $thisBacktrace['class'] . ", type: " . $thisBacktrace['type'] . "</p>\n";
			$additionalContent .= "\n\nFunction: " . $function . ", line: " . $thisBacktrace['line'] . ", file: " . $thisBacktrace['file'] . ", class: " . $thisBacktrace['class'] . ", type: " . $thisBacktrace['type'];
		}

		if (count($parameters) > 0) {
			$emailText .= "<p>Parameters:</p>\n";
			$additionalContent .= "\n\nParameters:\n\n";
			foreach ($parameters as $fieldName => $fieldValue) {
				$emailText .= "<p>" . $fieldName . ": " . (strlen($fieldValue) > 200 ? "Large Value" : $fieldValue) . "</p>\n";
				$additionalContent .= $fieldName . ": " . $fieldValue . "\n";
			}
		}

		$query = "insert into error_log (user_id,script_filename,error_message,query_text,content) values " .
			"(" . (empty($GLOBALS['gUserId']) ? "null" : $GLOBALS['gUserId']) . "," . (empty($GLOBALS['gLinkUrl']) ? "NULL" : $this->makeParameter($GLOBALS['gLinkUrl'])) . "," .
			(empty($errorMessage) ? "NULL" : $this->makeParameter($errorMessage)) . "," . (empty($errorQuery) ? "NULL" : $this->makeParameter($errorQuery)) . "," .
			(empty($additionalContent) ? "NULL" : $this->makeParameter($additionalContent)) . ")";
		$this->iLastErrorLogged = $query;
		$this->iDBConnection->query($query);
		$thisErrorText = $this->iDBConnection->error;
		if (!empty($thisErrorText)) {
			syslog(LOG_ERR, $thisErrorText . ": " . $query);
		}

		// $emailText .= "</body></html>";
		// $emailAddresses = array();
		// $bccEmailAddresses = array();
		// $emailAdded = false;
		// foreach ($sendToAddresses as $emailAddress) {
		// 	if (empty($emailAddress)) {
		// 		continue;
		// 	}
		// 	if ($emailAdded) {
		// 		$bccEmailAddresses[] = $emailAddress;
		// 	} else {
		// 		$emailAddresses[] = $emailAddress;
		// 	}
		// 	$emailAdded = true;
		// }
		// if ($emailAdded) {
		// 	$errorMessage = sendEmail(array("subject" => "Error log entry created", "body" => $emailText, "email_addresses" => $emailAddresses, "bcc_addresses" => $bccEmailAddresses, "primary_client" => true, "no_notifications" => true, "no_copy" => true));
		// 	if ($errorMessage !== true) {
		// 		$userId = (empty($GLOBALS['gUserId']) ? "NULL" : $GLOBALS['gUserId']);
		// 		$query = "insert into error_log (user_id,script_filename,error_message,query_text,content) values " .
		// 			"(" . $userId . "," . $this->makeParameter($GLOBALS['gLinkUrl']) . "," . $this->makeParameter($errorMessage) . ",null," . (empty($additionalContent) ? "NULL" : $this->makeParameter($additionalContent)) . ")";
		// 		$this->iDBConnection->query($query);
		// 	}
		// }
	}

	/**
	 *    function makeParameter
	 *
	 *  As long as non-empty input has been passed, escape the character data and
	 *    and qualify within single quotes.
	 *
	 * @param string $input
	 * @return string the escaped string
	 */
	function makeParameter($input) {
		$input = trim($input, "\0 \t");
		if (empty($input) && strlen($input) == 0) {
			return "";
		}
		return ("'" . $this->iDBConnection->real_escape_string($input) . "'");
	}

	/**
	 *    rollbackTransaction
	 *
	 *    Sends rollback command to the query statement.
	 *    Logs error that caused the rollback to transpire
	 *
	 */
	function rollbackTransaction() {
		if ($this->iReadonlyConnection) {
			return $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
		}
		$query = "rollback";
		$this->iDBConnection->query($query);
		if (!empty($this->iLastErrorLogged)) {
			$this->iDBConnection->query($this->iLastErrorLogged);
		}
		$this->iLastErrorLogged = "";
		if (!empty($this->iLastEcommerceError) && is_array($this->iLastEcommerceError)) {
			eCommerce::doWriteLog($this->iLastEcommerceError['account_information'], $this->iLastEcommerceError['response_message'], $this->iLastEcommerceError['failure']);
		}
		$this->iLastEcommerceError = false;
	}

	/**
	 *    commitTransaction
	 *
	 *    Sends commit command to the query statement.
	 *
	 * @return ResultSet generated by executeQuery command
	 */
	function commitTransaction() {
		$this->iLastErrorLogged = "";
		$this->iLastEcommerceError = false;
		if ($this->iReadonlyConnection) {
			return $GLOBALS['gPrimaryDatabase']->commitTransaction();
		}
		return $this->executeQuery("commit");
	}

	/**
	 *    function prepareStatement
	 *
	 *    Prepare a query statement and return the prepared statement. This does nothing to the database, but simply
	 *    validates the statement and returns a mysql prepared statement. This is used when the same statement is being
	 *    executed over and over with different parameters. By preparing the statement, execution speed in improved.
	 *
	 * @return Prepared statement object generated by executeQuery command
	 */
	function prepareStatement($query) {
		return $this->executeQuery($query, array(), true);
	}

	/**
	 *    function resetResultSet
	 *
	 *    Given a result set, set the next row to be read
	 */
	function resetResultSet($resultSet, $rowOffset = 0) {
		if (!is_object($resultSet['result'])) {
			return false;
		}
		return $resultSet['result']->data_seek($rowOffset);
	}

	function updateFieldById($fieldName, $newValue, $tableName, $keyName, $keyValue, $extraWhere = "", $parameters = array()) {
		$dataTable = new DataTable($tableName);
		$dataTable->setSaveOnlyPresent(true);

		$filterByClient = ($keyName != "client_id" && $fieldName != "client_id" && $tableName != "clients" && strpos($extraWhere, "client_id") === false && $this->fieldExists($tableName, "client_id"));
		array_unshift($parameters, $keyValue);
		if ($filterByClient) {
			$parameters[] = $GLOBALS['gClientId'];
		}
		$primaryKey = $GLOBALS['gTableKeys'][$tableName];

		$queryText = "select " . $primaryKey . "," . $fieldName . " from " . $tableName . " where " . $keyName . " = ?" .
			(empty($extraWhere) ? "" : " and (" . $extraWhere . ")") . ($filterByClient ? " and (client_id = ?)" : "");
		$resultSet = $this->executeQuery($queryText, $parameters);
		$updateCount = 0;
		while ($row = $this->getNextRow($resultSet)) {
			$updateArray = array("name_values" => array($fieldName => $newValue), "primary_id" => $row[$primaryKey]);
			if ($dataTable->saveRecord($updateArray)) {
				$updateCount++;
			}
		}
		return $updateCount;
	}

	/**
	 *    fieldExists - check to see if a field exists in a table
	 * @param
	 *        field name
	 *        table name
	 * @return
	 *        true or false whether the field exists in table or not
	 */
	function fieldExists($tableName, $columnName) {
		if (empty($columnName) || empty($tableName)) {
			return false;
		}
		if (!$this->tableExists($tableName)) {
			return false;
		}
		if (empty(self::$iTableColumns) || !array_key_exists($tableName, self::$iTableColumns)) {
			$this->getTableColumns($tableName);
		}
		return array_key_exists($columnName, self::$iTableColumns[$tableName]);
	}

	function getTableColumns($tableName) {
		if (array_key_exists($tableName, self::$iTableColumns) && !empty(self::$iTableColumns[$tableName])) {
			return self::$iTableColumns[$tableName];
		}
		self::$iTableColumns[$tableName] = array();
		$resultSet = $this->executeQuery("select TABLE_NAME,COLUMN_NAME,COLUMN_DEFAULT,IS_NULLABLE,DATA_TYPE,COLUMN_TYPE,COLUMN_KEY,EXTRA from information_schema.columns where table_name = '" . $tableName . "' and table_schema = '" . $GLOBALS['gPrimaryDatabase']->getName() . "' order by ordinal_position");
		while ($row = getNextRow($resultSet)) {
			self::$iTableColumns[$tableName][$row['COLUMN_NAME']] = $row;
		}
		$this->freeResult($resultSet);
		setCachedData("table_columns", $this->iDatabaseName, self::$iTableColumns, 4, true);
		return self::$iTableColumns[$tableName];
	}

	/**
	 *    function getNextRow
	 *
	 *    Given a result set, set the next row and pass it back as an associative array
	 *
	 * @return associative array of the next row of the data from the query
	 */
	function getNextRow($resultSet) {
		if (!is_object($resultSet['result'])) {
			return array();
		}
		if ($row = $resultSet['result']->fetch_assoc()) {
			$GLOBALS['gNoTranslation'] = true;
			if (array_key_exists("table_list", $resultSet)) {
				foreach ($resultSet['table_list'] as $tableName) {
					if (array_key_exists($tableName, $GLOBALS['gLanguageColumns'])) {
						foreach ($row as $columnName => $columnData) {
							if (array_key_exists($columnName, $GLOBALS['gLanguageColumns'][$tableName])) {
								$primaryKey = $GLOBALS['gTableKeys'][$tableName];
								if (array_key_exists($primaryKey, $row)) {
									$content = $GLOBALS['gLanguageText'][$GLOBALS['gLanguageColumns'][$tableName][$columnName]][$row[$primaryKey]];
									if (!empty($content)) {
										$row[$columnName] = $content;
									} elseif (array_key_exists($tableName . "|" . $columnName, $GLOBALS['gTranslatableColumns']) || array_key_exists("|" . $columnName, $GLOBALS['gTranslatableColumns'])) {
										if (array_key_exists(strtolower($row[$columnName]), $GLOBALS['gTextTranslations'])) {
											$row[$columnName] = $GLOBALS['gTextTranslations'][strtolower($row[$columnName])];
										}
									}
								}
							}
						}
					}
				}
			}
			$GLOBALS['gNoTranslation'] = false;
			return $row;
		}
		return array();
	}

	/**
	 *    function getFieldFromId
	 *
	 *    A simple convenience function to get a field from a table. If the query generates more than one row, only the
	 *    first one is returned.
	 *
	 * @return mixed The value of the field requested
	 */

	function getFieldFromId($fieldName, $tableName, $idName = "", $primaryId = "", $extraWhere = "", $parameters = array()) {
		if (!$this->tableExists($tableName)) {
			$this->logError("Invalid table name in getFieldFromId");
			return false;
		}
		if (empty($parameters)) {
			$baseCacheKey = md5($fieldName . ":" . $tableName . ":" . $idName);
			$cachedFieldFromIdKey = md5($fieldName . ":" . $tableName . ":" . $idName . ":" . $extraWhere);
		} else {
			$baseCacheKey = false;
			$cachedFieldFromIdKey = false;
		}
		if (is_array($primaryId) || is_object($primaryId)) {
			$primaryId = "";
			$this->logError("Object used for key value");
		}
		$tableList = explode(",", getPreference("DEFAULT_CLIENT_CONTROL_TABLES"));
		$field = null;

		$filterByClient = ($idName != "client_id" && $fieldName != "client_id" && $tableName != "clients" && strpos($extraWhere, "client_id") === false && $this->fieldExists($tableName, "client_id"));
		if (!empty($idName)) {
			array_unshift($parameters, $primaryId);
		}
		if ($filterByClient) {
			$parameters[] = $GLOBALS['gClientId'];
			if (in_array($tableName, $tableList)) {
				$parameters[] = $GLOBALS['gDefaultClientId'];
			}
		}
		if ($cachedFieldFromIdKey && $baseCacheKey && array_key_exists($baseCacheKey, self::$iCachedFieldValues)) {
			if (!isset($GLOBALS['gFieldFromIdValues']) || !is_array($GLOBALS['gFieldFromIdValues'])) {
				$GLOBALS['gFieldFromIdValues'] = getCachedData("cached_id_values", "");
				if (!is_array($GLOBALS['gFieldFromIdValues'])) {
					$GLOBALS['gFieldFromIdValues'] = array();
				}
			}
			if (array_key_exists($cachedFieldFromIdKey, $GLOBALS['gFieldFromIdValues'])) {
				if (array_key_exists(strtoupper($primaryId), $GLOBALS['gFieldFromIdValues'][$cachedFieldFromIdKey]) ) {
					return $GLOBALS['gFieldFromIdValues'][$cachedFieldFromIdKey][strtoupper($primaryId)];
				} elseif(self::$iCachedFieldValues[$baseCacheKey]['include_not_found']) {
                    return false;
                }
			} else {
				$GLOBALS['gFieldFromIdValues'][$cachedFieldFromIdKey] = array();
				$whereStatement = ($filterByClient ? " client_id = " . $GLOBALS['gClientId'] : "");
				$whereStatement .= (empty($extraWhere) ? "" : (empty($whereStatement) ? "" : " and " . $extraWhere));
				$resultSet = executeReadQuery("select " . $fieldName . "," . $idName . " from " . $tableName . (empty($whereStatement) ? "" : " where " . $whereStatement));
				while ($row = getNextRow($resultSet)) {
					$GLOBALS['gFieldFromIdValues'][$cachedFieldFromIdKey][strtoupper($row[$idName])] = $row[$fieldName];
				}
				setCachedData("cached_id_values", "", $GLOBALS['gFieldFromIdValues'], 1);
				return (array_key_exists(strtoupper($primaryId), $GLOBALS['gFieldFromIdValues'][$cachedFieldFromIdKey]) ? $GLOBALS['gFieldFromIdValues'][$cachedFieldFromIdKey][strtoupper($primaryId)] : false);
			}
		}
		if (!is_array($parameters)) {
			$parameters = func_get_args();
			unset($parameters[0]);
			unset($parameters[1]);
			unset($parameters[2]);
			unset($parameters[3]);
			unset($parameters[4]);
		}

		$queryText = "select " . $fieldName . " from " . $tableName;
		$whereStatement = (empty($idName) ? "" : $idName . " = ?");
		if (!empty($extraWhere)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(" . $extraWhere . ")";
		}
		if ($filterByClient) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(client_id = ?" . (in_array($tableName, $tableList) ? " or client_id = ?)" : ")");
		}
		if (!empty($whereStatement)) {
			$queryText .= " where " . $whereStatement;
		}
		$resultSet = $this->executeQuery($queryText, $parameters);
		if ($row = $this->getNextRow($resultSet)) {
			$field = $row[$fieldName];
		}
		$this->freeResult($resultSet);
		if (!empty($GLOBALS['gTableKeys']) && $idName == $GLOBALS['gTableKeys'][$tableName] && array_key_exists($tableName, $GLOBALS['gLanguageColumns']) && array_key_exists($fieldName, $GLOBALS['gLanguageColumns'][$tableName])) {
			$newField = $GLOBALS['gLanguageText'][$GLOBALS['gLanguageColumns'][$tableName][$fieldName]][$primaryId];
			if (!empty($newField)) {
				$field = $newField;
			} elseif (array_key_exists($tableName . "|" . $fieldName, $GLOBALS['gTranslatableColumns']) || array_key_exists("|" . $fieldName, $GLOBALS['gTranslatableColumns'])) {
				if (array_key_exists(strtolower($field), $GLOBALS['gTextTranslations'])) {
					$field = $GLOBALS['gTextTranslations'][strtolower($field)];
				}
			}
		}

		return $field;
	}

	/**
	 *    function getMultipleFieldsFromId
	 *
	 *    A simple convenience function to get multiple fields from a table. If the query generates more than one row, only the
	 *    first one is returned.
	 *
	 * @return An array of the value of the fields requested
	 */
	function getMultipleFieldsFromId($fieldNames, $tableName, $idName, $primaryId, $extraWhere = "", $parameters = array()) {
		if (!is_array($fieldNames)) {
			return array();
		}
		if (!is_array($parameters)) {
			$parameters = func_get_args();
			unset($parameters[0]);
			unset($parameters[1]);
			unset($parameters[2]);
			unset($parameters[3]);
			unset($parameters[4]);
		}
		$tableList = explode(",", getPreference("DEFAULT_CLIENT_CONTROL_TABLES"));
		$fieldValues = array();
		if (!empty($primaryId)) {
			$filterByClient = $tableName != "clients" && strpos($extraWhere, "client_id") === false && $this->fieldExists($tableName, "client_id");
			array_unshift($parameters, $primaryId);
			if ($filterByClient) {
				$parameters[] = $GLOBALS['gClientId'];
				if (in_array($tableName, $tableList)) {
					$parameters[] = $GLOBALS['gDefaultClientId'];
				}
			}
			$queryText = "select " . implode(",", $fieldNames) . " from " . $tableName . " where " . $idName . " = ?" .
				(empty($extraWhere) ? "" : " and (" . $extraWhere . ")") . ($filterByClient ? " and (client_id = ?" . (in_array($tableName, $tableList) ? " or client_id = ?)" : ")") : "");
			$resultSet = $this->executeQuery($queryText, $parameters);
			if ($row = $this->getNextRow($resultSet)) {
				$fieldValues = $row;
			}
			$this->freeResult($resultSet);
			foreach ($fieldValues as $fieldName => $fieldValue) {
				if ($idName == $GLOBALS['gTableKeys'][$tableName] && array_key_exists($tableName, $GLOBALS['gLanguageColumns']) && array_key_exists($fieldName, $GLOBALS['gLanguageColumns'][$tableName])) {
					$newField = $GLOBALS['gLanguageText'][$GLOBALS['gLanguageColumns'][$tableName][$fieldName]][$primaryId];
					if (!empty($newField)) {
						$fieldValues[$fieldName] = $newField;
					} elseif (array_key_exists($tableName . "|" . $fieldName, $GLOBALS['gTranslatableColumns']) || array_key_exists("|" . $fieldName, $GLOBALS['gTranslatableColumns'])) {
						if (array_key_exists(strtolower($fieldValues[$fieldName]), $GLOBALS['gTextTranslations'])) {
							$fieldValues[$fieldName] = $GLOBALS['gTextTranslations'][strtolower($fieldValues[$fieldName])];
						}
					}
				}
			}
		}
		return $fieldValues;
	}

	/**
	 *    function getRowFromId
	 *
	 *    A simple convenience function to get a row from a table. If the query generates more than one row, only the
	 *    first one is returned.
	 *
	 * @return An array of the value of the fields requested
	 */
	function getRowFromId($tableName, $idName, $primaryId, $extraWhere = "", $parameters = array()) {
		if (!is_array($parameters)) {
			$parameters = func_get_args();
			unset($parameters[0]);
			unset($parameters[1]);
			unset($parameters[2]);
			unset($parameters[3]);
		}
		$tableList = explode(",", getPreference("DEFAULT_CLIENT_CONTROL_TABLES"));
		$returnRow = array();
		if (!empty($primaryId)) {
			$filterByClient = $tableName != "clients" && strpos($extraWhere, "client_id") === false && $this->fieldExists($tableName, "client_id");
			array_unshift($parameters, $primaryId);
			if ($filterByClient) {
				$parameters[] = $GLOBALS['gClientId'];
				if (in_array($tableName, $tableList)) {
					$parameters[] = $GLOBALS['gDefaultClientId'];
				}
			}
			$queryText = "select * from " . $tableName . " where " . $idName . " = ?" .
				(empty($extraWhere) ? "" : " and (" . $extraWhere . ")") . ($filterByClient ? " and (client_id = ?" . (in_array($tableName, $tableList) ? " or client_id = ?)" : ")") : "");
			$resultSet = $this->executeQuery($queryText, $parameters);
			if ($row = $this->getNextRow($resultSet)) {
				$returnRow = $row;
			}
			$this->freeResult($resultSet);
			foreach ($returnRow as $fieldName => $fieldValue) {
				if ($idName == $GLOBALS['gTableKeys'][$tableName] && array_key_exists($tableName, $GLOBALS['gLanguageColumns']) && array_key_exists($fieldName, $GLOBALS['gLanguageColumns'][$tableName])) {
					$newField = $GLOBALS['gLanguageText'][$GLOBALS['gLanguageColumns'][$tableName][$fieldName]][$primaryId];
					if (!empty($newField)) {
						$returnRow[$fieldName] = $newField;
					} elseif (array_key_exists($tableName . "|" . $fieldName, $GLOBALS['gTranslatableColumns']) || array_key_exists("|" . $fieldName, $GLOBALS['gTranslatableColumns'])) {
						if (array_key_exists(strtolower($returnRow[$fieldName]), $GLOBALS['gTextTranslations'])) {
							$returnRow[$fieldName] = $GLOBALS['gTextTranslations'][strtolower($returnRow[$fieldName])];
						}
					}
				}
			}
		}
		return $returnRow;
	}

	function getChangeLogForeignKey($tableName) {
		$foreignKey = "";
		if (array_key_exists($tableName, self::$iChangeLogForeignKeys)) {
			$foreignKey = self::$iChangeLogForeignKeys[$tableName]['foreign_key'];
		}
		return $foreignKey;
	}

	function getChangeLogForeignKeys($foreignTable = "") {
		if (empty($foreignTable)) {
			return self::$iChangeLogForeignKeys;
		}
		$returnArray = array();
		foreach (self::$iChangeLogForeignKeys as $index => $changeInfo) {
			if ($changeInfo['foreign_table'] == $foreignTable) {
				$returnArray[$index] = $changeInfo;
			}
		}
		return $returnArray;
	}

	function addChangeLogForeignKeys($tableName, $foreignKey, $foreignTable) {
		self::$iChangeLogForeignKeys[$tableName] = array("table_name" => $tableName, "foreign_key" => $foreignKey, "foreign_table" => $foreignTable);
	}

	/**
	 *    function createNumberChangeLog
	 *
	 *    Add an entry to the change log when a numeric field in a table changes.
	 *
	 * @return True or false as to whether the value changed or not
	 */
	function createNumberChangeLog($tableName, $fieldName, $keyValue, $oldValue, $newValue, $userId = "", $foreignKeyValue = "") {
		if ($oldValue != "[NEW RECORD]") {
			$oldValue = $this->makeNumberParameter($oldValue);
		}
		if ($newValue != "[DELETED]") {
			$newValue = $this->makeNumberParameter($newValue);
		}
		if ((strlen($oldValue) > 0 && strlen($newValue) > 0 && (floatval($oldValue) == floatval($newValue)) || ($oldValue == $newValue && strlen($oldValue) == strlen($newValue)))) {
			return false;
		} else {
			if (strval($oldValue) == "NULL") {
				$oldValue = "";
			}
			if (strval($newValue) == "NULL") {
				$newValue = "";
			}
			return $this->createChangeLog($tableName, $fieldName, $keyValue, $oldValue, $newValue, $userId, $foreignKeyValue);
		}
	}

	/**
	 *    function makeNumberParameter
	 *
	 *  Loop through the input characters of string passed in.
	 *    Going character by character, see if the value is a 0-9.
	 *    If it is, add that number to the value of $newNumber variable.
	 *    Look for - to make number negative, look for . to make it a decimal.
	 *  If newNumber is a negative number, concatenate a hyphen in front of it.
	 *  If newNumber is equal only to the characters of -, . or -., then set the number equal to zero.
	 *
	 * @param string $input
	 * @return int formatted and cleaned up number
	 */
	function makeNumberParameter($input) {
		$newNumber = "";
		$negativeValue = false;
		$decimalValue = false;
		for ($x = 0; $x < strlen($input); $x++) {
			if (substr($input, $x, 1) >= "0" && substr($input, $x, 1) <= "9") {
				$newNumber .= substr($input, $x, 1);
			}
			if (substr($input, $x, 1) == "-") {
				$negativeValue = true;
			}
			if (substr($input, $x, 1) == "." && !$decimalValue) {
				$decimalValue = true;
				$newNumber .= ".";
			}
		}
		if (strlen($newNumber) == 0) {
			return "";
		}
		if ($negativeValue) {
			$newNumber = "-" . $newNumber;
		}
		if ($newNumber == "-" || $newNumber == "." || $newNumber == "-.") {
			$newNumber = "0";
		}
		return $newNumber;
	}

	/**
	 *    function createChangeLog
	 *
	 *    Add an entry to the change log when a field in a table changes.
	 *
	 * @return True or false as to whether the value changed or not
	 */
	function createChangeLog($tableName, $fieldName, $keyValue, $oldValue, $newValue, $userId = "", $foreignKeyValue = "") {
		if ($this->iReadonlyConnection) {
			return $GLOBALS['gPrimaryDatabase']->createChangeLog($tableName, $fieldName, $keyValue, $oldValue, $newValue, $userId, $foreignKeyValue);
		}
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
		if ($tableName == "ip_address_blacklist" || $tableName == "ip_address_whitelist") {
			$GLOBALS['gCreateNewBlacklist'] = true;
		}
		$newValue = trim($newValue);
		if (strpos($fieldName, "password") !== false) {
			$oldValue = "Old Password";
			$newValue = "New Password";
		}
		if (strcmp($oldValue, $newValue) != 0) {
			if ($fieldName != "version" && $fieldName != "file_content") {
				if (substr($fieldName, -3) == "_id") {
					if (array_key_exists($fieldName, self::$iFieldForeignTableNames)) {
						$foreignTableName = self::$iFieldForeignTableNames[$fieldName];
					} else {
						$foreignTableName = "";
						$resultSet = $this->executeQuery("select table_name from tables where table_id in (select table_id from table_columns where " .
							"primary_table_key = 1 and column_definition_id = (select column_definition_id from column_definitions where column_name = ?) and " .
							"database_definition_id = (select database_definition_id from database_definitions where database_name = ?))", $fieldName, $this->iDatabaseName);
						if ($row = $this->getNextRow($resultSet)) {
							$foreignTableName = $row['table_name'];
						}
						self::$iFieldForeignTableNames[$fieldName] = $foreignTableName;
					}
					$this->freeResult($resultSet);
					if ($foreignTableName && $tableName != $foreignTableName) {
						$descriptionFieldName = "";
						$foreignTablePrimaryKey = "";
						$searchForeignTableName = $foreignTableName;
						$extraWhere = "";
						if (array_key_exists($foreignTableName, self::$iForeignTableFields)) {
							$foreignTablePrimaryKey = self::$iForeignTableFields[$foreignTableName]['primary_key'];
							$descriptionFieldName = self::$iForeignTableFields[$foreignTableName]['description'];
						} else {
							$resultSet = $this->executeQuery("select * from table_columns,column_definitions where " .
								"table_columns.column_definition_id = column_definitions.column_definition_id and table_id = " .
								"(select table_id from tables where table_name = ? and database_definition_id = " .
								"(select database_definition_id from database_definitions where database_name = ?)) and ((column_type = 'varchar' and code_value = 0 " .
								"and data_size > 10) or primary_table_key = 1) order by sequence_number", $foreignTableName, $this->iDatabaseName);
							while ($row = $this->getNextRow($resultSet)) {
								if (empty($foreignTablePrimaryKey)) {
									$foreignTablePrimaryKey = $row['column_name'];
								} else {
									$descriptionFieldName = $row['column_name'];
									break;
								}
							}
							$this->freeResult($resultSet);
							self::$iForeignTableFields[$foreignTableName] = array("primary_key" => $foreignTablePrimaryKey, "description" => $descriptionFieldName);
						}
						if ($descriptionFieldName && $foreignTablePrimaryKey) {
							$oldFieldValue = getFieldFromId($descriptionFieldName, $searchForeignTableName, $foreignTablePrimaryKey, $oldValue, $extraWhere);
							$newFieldValue = getFieldFromId($descriptionFieldName, $searchForeignTableName, $foreignTablePrimaryKey, $newValue, $extraWhere);
							if ($oldFieldValue) {
								$oldValue = $oldFieldValue;
							}
							if ($newFieldValue) {
								$newValue = $newFieldValue;
							}
						}
					}
				}
				$tablePrimaryKey = $this->getPrimaryKey($tableName);
				$foreignKeyIdentifier = "";
				if (array_key_exists($tableName, self::$iChangeLogForeignKeys)) {
					if (empty($foreignKeyValue)) {
						$foreignKeyIdentifier = getFieldFromId(self::$iChangeLogForeignKeys[$tableName]['foreign_key'], $tableName, $tablePrimaryKey, $keyValue);
					} else {
						$foreignKeyIdentifier = $foreignKeyValue;
					}
				}
				$changeLogNotes = $GLOBALS['gChangeLogNotes'];
				if (empty($userId) && empty($changeLogNotes)) {
					$changeLogNotes = "";
					$bt = debug_backtrace();
					foreach ($bt as $caller) {
						$changeLogNotes .= $caller['file'] . ", line " . $caller['line'] . "\n";
					}
				}
				$notes = $GLOBALS['gChangeLogNotes'];
				if (empty($notes) && !empty($_SESSION['original_user_id'])) {
					$notes = "Simulated by " . getUserDisplayName($_SESSION['original_user_id']);
				}
				$insertSet = $this->executeQuery("insert into change_log (client_id,user_id,table_name,column_name,primary_identifier,foreign_key_identifier," .
					"old_value,new_value,notes) values (?,?,?,?,?, ?,?,?,?)", array($GLOBALS['gClientId'], $userId, $tableName, $fieldName,
					$keyValue, $foreignKeyIdentifier, $oldValue, $newValue, $notes));
			}
			return true;
		}
		return false;
	}

	function getPrimaryKey($tableName) {
		if (empty($this->iTablePrimaryKeys)) {
			$resultSet = $this->executeQuery("select (select column_name from column_definitions where column_definition_id = table_columns.column_definition_id) column_name," .
				"(select table_name from tables where table_id = table_columns.table_id) table_name from table_columns where primary_table_key = 1 and " .
				"table_id in (select table_id from tables where database_definition_id = (select database_definition_id from database_definitions where " .
				"database_name = ?))", $this->iDatabaseName);
			while ($row = $this->getNextRow($resultSet)) {
				$this->iTablePrimaryKeys[$row['table_name']] = $row['column_name'];
			}
		}
		return $this->iTablePrimaryKeys[$tableName];
	}

	/**
	 *    function createBooleanChangeLog
	 *
	 *    Add an entry to the change log when a boolean field in a table changes.
	 *
	 * @return True or false as to whether the value changed or not
	 */
	function createBooleanChangeLog($tableName, $fieldName, $keyValue, $oldValue, $newValue, $userId = "", $foreignKeyValue = "") {
		$oldValue = $oldValue . "";
		$newValue = $newValue . "";
		if ($oldValue != "[NEW RECORD]") {
			$oldValue = (empty($oldValue) ? "no" : "YES");
		}
		if ($newValue != "[DELETED]") {
			$newValue = (empty($newValue) ? "no" : "YES");
		}
		return $this->createChangeLog($tableName, $fieldName, $keyValue, $oldValue, $newValue, $userId, $foreignKeyValue);
	}

	/**
	 *    function createDateChangeLog
	 *
	 *    Add an entry to the change log when a date field in a table changes.
	 *
	 * @return True or false as to whether the value changed or not
	 */
	function createDateChangeLog($tableName, $fieldName, $keyValue, $oldValue, $newValue, $userId = "", $foreignKeyValue = "") {
		if ($oldValue != "[NEW RECORD]" && !empty($oldValue)) {
			$oldValue = date("m/d/Y", strtotime($oldValue));
		}
		if ($newValue != "[DELETED]" && !empty($newValue)) {
			$newValue = date("m/d/Y", strtotime($newValue));
		}
		return $this->createChangeLog($tableName, $fieldName, $keyValue, $oldValue, $newValue, $userId, $foreignKeyValue);
	}

	/**
	 *    function createDatetimeChangeLog
	 *
	 *    Add an entry to the change log when a datetime field in a table changes.
	 *
	 * @return True or false as to whether the value changed or not
	 */
	function createDatetimeChangeLog($tableName, $fieldName, $keyValue, $oldValue, $newValue, $userId = "", $foreignKeyValue = "") {
		if ($oldValue != "[NEW RECORD]" && !empty($oldValue)) {
			$oldValue = date("m/d/Y g:ia", strtotime($oldValue));
		}
		if ($newValue != "[DELETED]" && !empty($newValue)) {
			$newValue = date("m/d/Y g:ia", strtotime($newValue));
		}
		return $this->createChangeLog($tableName, $fieldName, $keyValue, $oldValue, $newValue, $userId, $foreignKeyValue);
	}

	/**
	 *    getControlRecords - Based on the values set in the parameters, return a list of key, description pairs that can be used to create a
	 *    dropdown menu or some other list.
	 * @parameter
	 *    Parameter List:
	 *        table_name: table name of control table. required.
	 *        description_field: field to be used as the display value. Default: description
	 *        where_statement: any special selection criteria
	 *        show_inactive: If set to true, inactive values will be included in the list. Default: false
	 *        existing_value: if set, this value will be in the list even if it is inactive
	 *        sort_order: field or fields used as the primary sort order. Default: sort_order
	 * @return
	 *    false if there is a problem (and error message is loaded)
	 *    array of control records. Each entry in the array will have "key_value", "description", and "inactive".
	 */
	function getControlRecords($parameterList = array()) {
		if (!is_array($parameterList)) {
			$parameterList = array("table_name" => $parameterList);
		}
		$tableList = explode(",", getPreference("DEFAULT_CLIENT_CONTROL_TABLES"));
		$defaultParameters = array("table_name" => "",
			"description_field" => "description",
			"where_statement" => "",
			"show_inactive" => false,
			"existing_value" => "",
			"include_default_client" => (empty($parameterList['table_name']) || !in_array($parameterList, $tableList) ? false : true),
			"sort_order" => "sort_order");
		$parameterList = array_merge($defaultParameters, $parameterList);
		if (empty($parameterList['table_name'])) {
			$this->iErrorMessage = getSystemMessage("control_table_required");
			return false;
		}
		$columnInformation = $this->getTableColumns($parameterList['table_name']);
		foreach ($columnInformation as $row) {
			if ($row['COLUMN_KEY'] == "PRI") {
				$primaryKey = $row['COLUMN_NAME'];
			}
		}
		if (empty($primaryKey)) {
			$this->iErrorMessage = getSystemMessage("control_table_not_found", "", array("table_name" => $parameterList['table_name']));
			return false;
		}
		if (strpos($parameterList['description_field'], "(") === false && !$this->fieldExists($parameterList['table_name'], $parameterList['description_field'])) {
			$this->iErrorMessage = getSystemMessage("field_not_in_table", "", array("field_name" => $parameterList['description_field'], "table_name" => $parameterList['table_name']));
			return false;
		}

		if (strpos($parameterList['sort_order'], ",") === false && strpos($parameterList['sort_order'], " ") === false &&
			!$this->fieldExists($parameterList['table_name'], $parameterList['sort_order']) && !empty($parameterList['sort_order'])) {
			if ($parameterList['sort_order'] == $defaultParameters['sort_order']) {
				$parameterList['sort_order'] = "";
			} else {
				$this->iErrorMessage = getSystemMessage("field_not_in_table", "", array("field_name" => $parameterList['sort_order'], "table_name" => $parameterList['table_name']));
				return false;
			}
		}
		$sortOrder = $parameterList['sort_order'];
		if (!empty($sortOrder)) {
			$sortOrder .= ",";
		}
		$sortOrder .= $parameterList['description_field'];
		if (!empty($sortOrder)) {
			$sortOrder .= ",";
		}
		$sortOrder .= $primaryKey;
		$returnArray = array();

		if ($parameterList['table_name'] != "clients" && $this->fieldExists($parameterList['table_name'], "client_id")) {
			if (!empty($parameterList['where_statement'])) {
				$parameterList['where_statement'] .= " and ";
			}
			$parameterList['where_statement'] .= "(client_id = " . $GLOBALS['gClientId'] .
				($parameterList['include_default_client'] ? " or client_id = " . $GLOBALS['gDefaultClientId'] : "") . ")";
		}
		$resultSet = $this->executeQuery("select $primaryKey," . $parameterList['description_field'] .
			($this->fieldExists($parameterList['table_name'], "inactive") ? ",inactive" : "") .
			" from " . $parameterList['table_name'] .
			(empty($parameterList['where_statement']) ? "" : " where " . $parameterList['where_statement']) .
			" order by " . $sortOrder);
		if (!empty($resultSet['sql_error'])) {
			$this->iErrorMessage = $resultSet['sql_error'];
			return false;
		}
		while ($row = $this->getNextRow($resultSet)) {
			if (!array_key_exists("inactive", $row) || $row['inactive'] == 0 || $parameterList['show_inactive'] || $row[$primaryKey] == $parameterList['existing_value']) {
				$returnArray[$row[$primaryKey]] = array("key_value" => ($parameterList['use_description'] ? $row[$parameterList['description_field']] : $row[$primaryKey]), "description" => $row[$parameterList['description_field']], "inactive" => (array_key_exists("inactive", $row) && $row['inactive'] == 1));
			}
		}
		$this->freeResult($resultSet);
		return $returnArray;
	}

	function getColumnInformation($tableName, $columnName) {
		if (empty($columnName)) {
			return false;
		}
		if (empty(self::$iTableColumns) || !array_key_exists($tableName, self::$iTableColumns)) {
			$this->getTableColumns($tableName);
		}
		if (!array_key_exists($tableName, self::$iTableColumns)) {
			return false;
		}
		if (!array_key_exists($columnName, self::$iTableColumns[$tableName])) {
			return false;
		} else {
			return self::$iTableColumns[$tableName][$columnName];
		}
	}

	/**
	 *    getErrorMessage - Return the last error message
	 *
	 * @return string text of the last error message
	 */
	function getErrorMessage() {
		return $this->iErrorMessage;
	}

    function getIgnoredErrors() {
        return self::$iIgnoreErrorCounts;
    }

    // A new function to explicitly close a prepared statement
    // Always close statements after executing them unless they are stored in the pool
    function closePreparedStatement($query) {
        if (isset($this->iQueryStatements[$query])) {
            $this->iQueryStatements[$query]->close();
            unset($this->iQueryStatements[$query]);
        }
    }
}
