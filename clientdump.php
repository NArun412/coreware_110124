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

$GLOBALS['gPageCode'] = "CLIENTDUMP";
require_once "shared/startup.inc";

class ClientDumpPage extends Page {

	var $iTableNames = array();
	var $iTablesDone = array();
	var $iTableIds = array();
	var $iPrimaryKeys = array();

	function setup() {
		if (empty($GLOBALS['gUserRow']['superuser_flag'])) {
			header("Location: /");
			exit;
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "dump_client":
				$clientId = $_POST['client_id'];

				header("Content-Type: text/plain");
				header("Content-Disposition: attachment; filename=clientdump.txt");
				header("Cache-Control: no-store, no-cache, must-revalidate");
				header('Pragma: public');

				$resultSet = executeQuery("select * from tables where table_view = 0 order by table_name");
				while ($row = getNextRow($resultSet)) {
					$this->iTableNames[] = $row;
					$this->iTableIds[$row['table_name']] = $row['table_id'];
				}

				$clientRow = getRowFromId("clients", "client_id", $clientId,"contact_id in (select contact_id from contacts where client_id = ?)",$GLOBALS['gDefaultClientId']);
				if (empty($clientRow)) {
				    echo "Client Not Found or contact in the wrong client";
				    exit;
                }

				executeQuery("delete from shopping_carts where shopping_cart_id not in (select shopping_cart_id from shopping_cart_items) and shopping_cart_id not in (select shopping_cart_id from product_map_overrides)");
				executeQuery("delete from wish_lists where wish_list_id not in (select wish_list_id from wish_list_items)");

				$resultSet = executeQuery("select *,(select table_name from tables where table_id = table_columns.table_id) as table_name," .
                    "(select column_name from column_definitions where column_definition_id = table_columns.column_definition_id) as column_name from table_columns where primary_table_key = 1");
				while ($row = getNextRow($resultSet)) {
					$this->iPrimaryKeys[$row['table_name']] = $row['column_name'];
                }
				$this->dumpTable("contacts", "client_id = " . $GLOBALS['gDefaultClientId'] . " and contact_id = " . $clientRow['contact_id'],"client_contact");
				$this->dumpTable("phone_numbers", "contact_id = " . $clientRow['contact_id'],"client_phone_numbers");
				$this->dumpTable("clients", "client_id = " . $clientId,"client");
				$this->dumpTable("templates", "client_id = 1", "core_templates");
				$this->dumpTable("pages", "client_id = 1", "core_pages");
                $this->dumpTable("tables", "", "table_conversion");

				$translationTables = array("templates"=>"","preferences"=>"preference_code","subsystems"=>"subsystem_code","countries"=>"country_code","template_data"=>"data_name","form_fields"=>"","fragments"=>"",
					"menus"=>"","merchant_accounts"=>"","order_methods"=>"","order_status"=>"","pages"=>"","related_product_types"=>"","menu_items"=>"",
					"search_term_parameters"=>"","security_levels"=>"security_level","security_questions"=>"security_question","shipping_carriers"=>"");
				foreach ($translationTables as $tableName => $codeField) {
					$this->dumpTable($tableName,(empty($codeField) ? "client_id = " . $clientRow['client_id'] : ""));
				}

				executeQuery("update contacts set client_id = 1, contact_type_id = null, source_id = null where contact_id in (select contact_id from users where superuser_flag = 1)");
				executeQuery("update users set client_id = 1 where superuser_flag = 1");
				$this->dumpTable("contacts", "contact_id in (select contact_id from users where superuser_flag = 1)", "superuser_contacts");
				$this->dumpTable("users", "superuser_flag = 1", "superusers");
				$this->dumpTable("addresses", "contact_id in (select contact_id from users where superuser_flag = 1)", "superuser_addresses");
				$this->dumpTable("phone_numbers", "contact_id in (select contact_id from users where superuser_flag = 1)", "superuser_phone_numbers");
				$this->dumpTable("accounts", "contact_id in (select contact_id from users where superuser_flag = 1) and " .
                    "merchant_account_id in (select merchant_account_id from merchant_accounts where client_id = " . $clientId . ") and " .
                    "payment_method_id in (select payment_method_id from payment_methods where client_id = " . $clientId . ")", "superuser_accounts");

				$additionalWhereStatements = array();
				$additionalWhereStatements['product_inventory_log'] = "product_inventory_id in (select product_inventory_id from product_inventories where " .
					"location_id in (select location_id from locations where product_distributor_id is not null))";
				$filterWhereStatements = array();

				$logPurgeTables = array('action_log','api_log','background_process_log','change_log','click_log','download_log','ecommerce_log','email_log','error_log','image_usage_log',
					'not_found_log','product_distributor_log','product_inventory_log','program_log','query_log','search_term_log','security_log','server_monitor_log','user_activity_log','web_user_pages','product_view_log');
				foreach ($logPurgeTables as $logTableName) {
					$tableId = getFieldFromId("table_id", "tables", "table_name", $row['table_name']);
					if (empty($tableId)) {
						continue;
					}
					$columnName = getFieldFromId("column_name", "column_definitions", "column_type", "datetime", "column_definition_id in (select column_definition_id from table_columns where table_id = ?)", $tableId);
					if (empty($columnName)) {
						$columnName = getFieldFromId("column_name", "column_definitions", "column_type", "date", "column_definition_id in (select column_definition_id from table_columns where table_id = ?)", $tableId);
					}
					if (empty($columnName)) {
						continue;
					}
					$filterWhereStatements[$logTableName] = $columnName . " > date_sub(current_date,interval 3 month)";
					if (array_key_exists($logTableName,$additionalWhereStatements)) {
						$filterWhereStatements[$logTableName] .= " and " . $additionalWhereStatements[$logTableName];
                    }
				}

				$ignoreTables = array("action_results","action_types","add_hashes","api_app_methods","api_apps","api_method_group_links","api_method_groups","api_method_parameters","api_methods","api_parameters",
					"api_sessions","atf_firearm_types","background_process_log","background_process_notifications","background_processes","blocked_referers","clients","column_definitions","countries","country_data",
                    "country_data_types","database_alter_log","database_definitions","debug_log", "documentation_entries","documentation_entry_tables","documentation_parameters",
                    "documentation_types","domain_name_tracking_log","email_queue","error_log","form_fields","foreign_keys","hacking_terms","image_types","ip_address_blacklist","ip_address_countries","ip_address_errors",
                    "ip_address_hit_counts","ip_address_metrics","ip_address_whitelist","ip_quality_score_data","languages","log_purge_parameters","map_policies","media_services","merchant_services","page_modules",
                    "payroll_parameters","postal_codes","preference_group_links","preference_groups","preferences","preset_record_values","preset_records","price_calculation_types","product_distributors","product_inventory_log",
                    "product_search_fields","product_search_word_values","program_text","query_log","queryable_field_controls","queryable_fields","queryable_tables","random_data_chunks","selected_rows","stop_words","subsystems",
                    "table_columns","tables","task_attributes","template_data","timezones","tips","unique_key_columns","unique_keys","user_functions","view_columns","view_tables");

				foreach ($this->iTableNames as $thisTable) {
					if (in_array($thisTable['table_name'],$ignoreTables) || array_key_exists($thisTable['table_name'],$translationTables)) {
						continue;
					}

					$getWhereStatement = $this->getWhereStatement($thisTable['table_name'], $clientId);
					if (array_key_exists($thisTable['table_name'],$filterWhereStatements)) {
					    $getWhereStatement .= (empty($getWhereStatement) ? "" : " and ") . $filterWhereStatements[$thisTable['table_name']];
                    }
					$this->dumpTable($thisTable['table_name'], $getWhereStatement);
				}
				sendEmail(array("subject"=>"Client Dump Completed","body"=>"Client Dump for " . $clientRow['client_code'] . " has completed.","email_address"=>$GLOBALS['gUserRow']['email_address'],"contact_id"=>$GLOBALS['gUserRow']['contact_id']));

				exit;
		}
	}

	function dumpTable($tableName, $whereStatement = "", $dumpTag = "") {
		$this->iTablesDone[] = $tableName;
		$thisDump = array();
		$thisDump['table_name'] = $tableName;
		$thisDump['where'] = $whereStatement;
		$thisDump['tag'] = $dumpTag;
		$thisDump['keys'] = array();
		$thisDump['rows'] = array();
		$primaryKey = $this->iPrimaryKeys[$tableName];
		$lastPrimaryId = "";
		$totalRows = 0;
		while (true) {
			$useWhereStatement = $whereStatement . (empty($whereStatement) ? "" : " and ") . $primaryKey . " > " . (empty($lastPrimaryId) ? "0" : $lastPrimaryId);
			$resultSet = executeQuery("select * from " . $tableName . " where " . $useWhereStatement . " order by " . $primaryKey . " limit 1000");
			addDebugLog($tableName . ": " . $totalRows);
			if ($resultSet['row_count'] == 0) {
			    break;
            }
			$dumpRows = array();
			while ($row = getNextRow($resultSet)) {
				if (!empty($primaryKey)) {
					$lastPrimaryId = $row[$primaryKey];
				}
				switch ($tableName) {
					case "images":
					    if (empty($row['os_filename'])) {
					        $row['content_hash'] = md5($row['file_content']);
                        } else {
					        $row['content_hash'] = md5(getExternalFileContents($row['os_filename']));
                        }
						unset($row['file_content']);
						break;
					case "files":
						if (empty($row['os_filename'])) {
							$row['content_hash'] = md5($row['file_content']);
						} else {
							$row['content_hash'] = md5(getExternalFileContents($row['os_filename']));
						}
						unset($row['file_content']);
						break;
				}
				$dumpRows[] = $row;
				$totalRows++;
			}
			freeResult($resultSet);
			if (!empty($dumpRows)) {
			    $thisDump['keys'] = array_keys($dumpRows[0]);
				$thisDump['rows'] = array();
				foreach ($dumpRows as $thisRow) {
				    $thisDump['rows'][] = array_values($thisRow);
                }
				echo jsonEncode($thisDump) . "\n";
				unset($thisDump);
				$thisDump = array();
				$thisDump['table_name'] = $tableName;
				$thisDump['where'] = $whereStatement;
				$thisDump['tag'] = $dumpTag;
				$thisDump['keys'] = array();
				$thisDump['rows'] = array();
			}
		}
	}

	function getWhereStatement($tableName, $clientId, $foreignKeys = array()) {
		$dataTable = new DataTable($tableName);
		$foreignKeyList = $dataTable->getForeignKeyList();
		foreach ($foreignKeyList as $foreignKeyInfo) {
			if ($foreignKeyInfo['referenced_table_name'] == "clients" && $foreignKeyInfo['referenced_column_name'] == "client_id") {
				if (empty($foreignKeys)) {
					return $foreignKeyInfo['column_name'] . " = " . $clientId;
				} else {
					$whereStatement = "";
					$endWhereStatement = "";
					foreach ($foreignKeys as $subtableInfo) {
						$whereStatement .= $subtableInfo['column_name'] . " in (select " . $subtableInfo['referenced_column_name'] . " from " . $subtableInfo['referenced_table_name'] . " where ";
						$endWhereStatement .= ")";
					}
					$whereStatement .= $foreignKeyInfo['column_name'] . " = " . $clientId . $endWhereStatement;
					return $whereStatement;
				}
			}
			$tableColumnRow = getRowFromId("table_columns", "table_id", $this->iTableIds[$tableName], "column_definition_id = (select column_definition_id from column_definitions where column_name = ?)", $foreignKeyInfo['column_name']);
			if (empty($tableColumnRow['not_null'])) {
				continue;
			}
			$foreignKeys[] = $foreignKeyInfo;
			$thisWhereStatement = $this->getWhereStatement($foreignKeyInfo['referenced_table_name'], $clientId, $foreignKeys);
			if (!empty($thisWhereStatement)) {
			    return $thisWhereStatement;
            }
			$foreignKeys = array();
		}
		return false;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#dump_client").click(function () {
                $(this).closest("p").html("Processing and downloading file");
                $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=dump_client").attr("method", "POST").attr("target", "post_iframe").submit();
            });
        </script>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];
		?>
        <form id="_edit_form">
            <div class="basic-form-line" id="_client_id_row">
                <label for="client_id">Client</label>
                <select tabindex="10" class="validate[required]" id="client_id" name="client_id">
                    <option value="">[Select]</option>
					<?php
					$resultSet = executeQuery("select * from contacts join clients using (contact_id) order by business_name");
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['client_id'] ?>"><?= htmlText($row['business_name']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>
        </form>
        <p>
            <button tabindex='10' id="dump_client">Dump this Client</button>
        </p>
		<?php
		return true;
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>
		<?php
	}
}

$pageObject = new ClientDumpPage();
$pageObject->displayPage();
