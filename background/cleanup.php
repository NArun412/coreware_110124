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

$GLOBALS['gPageCode'] = "BACKGROUNDPROCESS";
$runEnvironment = php_sapi_name();
require_once __DIR__ . "/../shared/startup.inc";

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "cleanup";
	}

	function process() {

        $this->addResult("Starting cleanup process on " . $GLOBALS['gPrimaryDatabase']->getName());

		ProductDistributor::downloadProductMetaData();

		if (getPreference("FORCE_COST_RECALCULATION")) {
			$clientSet = executeQuery("select * from clients where inactive = 0");
			while ($clientRow = getNextRow($clientSet)) {
				changeClient($clientRow['client_id']);
				$resultSet = executeQuery("select * from products where client_id = ?", $clientRow['client_id']);
				while ($row = getNextRow($resultSet)) {
					executeQuery("delete from product_sale_prices where product_id = ?",$row['product_id']);
					ProductCatalog::calculateProductCost($row['product_id']);
				}
			}
			executeQuery("update preferences set system_value = null where preference_code = 'FORCE_COST_RECALCULATION'");
			triggerServerClearCache();
		}
        if(!empty(getPreference("DELETE_PRODUCT_INVENTORY_LOG_DATES"))) {
            [$startDate,$endDate] = explode(",",getPreference("DELETE_PRODUCT_INVENTORY_LOG_DATES"));
            if(!empty(strtotime($startDate)) && !empty(strtotime($endDate))) {
                $affectedRows = 1;
                $totalDeleted = 0;
                $deleteStartTime = time();
                while($affectedRows > 0) {
                    $affectedRows = executeQuery("delete from product_inventory_log where log_time between ? and ? and notes = 'Update product inventory from distributor' limit 10000",
                        date("Y-m-d", strtotime($startDate)),date("Y-m-d", strtotime($endDate)))['affected_rows'];
                    sleep(1);
                    $totalDeleted += $affectedRows;
                    // run for 30 minutes max
                    if(time() - $deleteStartTime > 1800) {
                        break;
                    }
                }
                $this->addResult($totalDeleted . " product inventory log records between " . $startDate . " and " . $endDate . " deleted");
            }
        }

		# Processing for Shooting Sports Catalog

		$shootingSportsClientId = getFieldFromId("client_id", "clients", "client_code", "COREWARE_SHOOTING_SPORTS");
		if (!empty($shootingSportsClientId)) {
			changeClient($shootingSportsClientId);

			$resultSet = executeQuery("select * from contacts join federal_firearms_licensees using (contact_id) where contacts.client_id = ? and latitude = null and longitude is null and validation_status = 0 order by rand() limit 1000", $GLOBALS['gClientId']);
			$count = 0;
			while ($row = getNextRow($resultSet)) {
				$geoCode = getAddressGeocode($row);
				if (!empty($geoCode) && !empty($geoCode['latitude']) && !empty($geoCode['longitude'])) {
					executeQuery("update contacts set latitude = ?,longitude = ?,validation_status = 1 where contact_id = ?", $geoCode['latitude'], $geoCode['longitude'], $row['contact_id']);
					$count++;
				}
			}
			$this->addResult($count . " geo locations set for FFLs");

			$usedImageIds = array();
			$resultSet = executeQuery("select image_id from products where client_id = ? and image_id is not null", $shootingSportsClientId);
			while ($row = getNextRow($resultSet)) {
				$usedImageIds[$row['image_id']] = true;
			}
			$resultSet = executeQuery("select image_id from product_images where image_id is not null and product_id in (select product_id from products where client_id = ?)", $shootingSportsClientId);
			while ($row = getNextRow($resultSet)) {
				$usedImageIds[$row['image_id']] = true;
			}
			$resultSet = executeQuery("select image_id from contacts where client_id = ? and image_id is not null", $shootingSportsClientId);
			while ($row = getNextRow($resultSet)) {
				$usedImageIds[$row['image_id']] = true;
			}
			$resultSet = executeQuery("select image_id from images where client_id = ? and image_code is null", $shootingSportsClientId);
			$count = 0;
			while ($row = getNextRow($resultSet)) {
				if (array_key_exists($row['image_id'], $usedImageIds)) {
					continue;
				}
				executeQuery("delete from image_data where image_id = ?", $row['image_id']);
				executeQuery("delete from images where image_id = ?", $row['image_id']);
				$count++;
			}
			unset($usedImageIds);
			$this->addResult($count . " unused images deleted from Shooting Sports catalog");

			if (date("w") == 6 && date("j") < 5) {
				$resultSet = executeReadQuery("select *,(select product_manufacturer_code from product_manufacturers where product_manufacturer_id = products.product_manufacturer_id) manufacturer from " .
					"products join product_data using (product_id) where products.client_id = ? and product_id in (select product_id from distributor_product_codes) order by products.product_id", $shootingSportsClientId);
				$duplicateProducts = array();
				while ($row = getNextRow($resultSet)) {
					if (strlen($row['upc_code']) < 8 || strlen($row['upc_code'] > 15) || !is_numeric($row['upc_code'])) {
						continue;
					}
					$duplicateKey = trim(ltrim($row['upc_code'], "0") . ":" . $row['manufacturer_sku'] . ":" . $row['manufacturer']);
					if (!array_key_exists($duplicateKey, $duplicateProducts)) {
						$duplicateProducts[$duplicateKey] = array();
					}
					$duplicateProducts[$duplicateKey][] = $row['product_id'];
				}
				foreach ($duplicateProducts as $duplicateKey => $productList) {
					if (count($productList) < 2) {
						unset($duplicateProducts[$duplicateKey]);
					}
				}
				$mergeCount = 0;
				foreach ($duplicateProducts as $productList) {
					$primaryProductId = array_shift($productList);
					foreach ($productList as $duplicateProductId) {
						if (ProductCatalog::mergeProducts($primaryProductId, $duplicateProductId) === true) {
							$mergeCount++;
						}
					}
				}
				$this->addResult($mergeCount . " duplicate products merged");
				unset($duplicateProducts);
			}
		}

		# clean up erroneous products

		$exclusionTables = array("courses", "distributor_order_items", "event_registration_products", "event_types", "events", "form_definitions", "gunbroker_products", "product_reviews",
			"order_items", "promotion_purchased_products", "promotion_rewards_excluded_products", "promotion_rewards_products", "promotion_set_products", "product_prices", "product_offers",
			"promotion_terms_excluded_products", "promotion_terms_products", "subscription_products", "product_pack_contents", "distributor_order_products", "product_questions");
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

		$resultSet = executeReadQuery("select products.product_id, products.client_id from products left join product_data using (product_id) where custom_product = 0 and (product_data_id is null or upc_code like 'NOUPC%') order by products.client_id");
		$productIdArray = array();
        $saveClientId = 0;
		while ($row = getNextRow($resultSet)) {
            if($row['client_id'] != $saveClientId) {
                changeClient($row['client_id']);
                $saveClientId = $row['client_id'];
            }
			$deleteProduct = true;
			foreach ($exclusionTables as $thisTable) {
				$productId = getFieldFromId("product_id", $thisTable, "product_id", $row['product_id']);
				if (!empty($productId)) {
					$deleteProduct = false;
                    break;
				}
			}
			if (!$deleteProduct) {
				continue;
			}
			foreach ($exclusionOtherTables as $thisTable => $thisField) {
				$productId = getFieldFromId($thisField, $thisTable, $thisField, $row['product_id']);
				if (!empty($productId)) {
					$deleteProduct = false;
                    break;
				}
			}
			if ($deleteProduct) {
				$productIdArray[$row['product_id']] = $row['product_id'];
			}
		}

        if(count($productIdArray) > 0) {
            $this->addResult("Erroneous Product Count: " . count($productIdArray));
            $totalDeleted = 0;
            while (count($productIdArray) > 0) {
                $thisProductArray = array_splice($productIdArray, 0, 100);
                $resultSet = executeQuery("delete from product_inventory_log where product_inventory_id in (select product_inventory_id from product_inventories where product_id in (" . implode(",", $thisProductArray) . "))");
                if (!empty($resultSet['sql_error'])) {
                    $this->addResult($resultSet['sql_error']);
                    break;
                }
                $resultSet = executeQuery("delete from product_group_variant_choices where product_group_variant_id in (select product_group_variant_id from product_group_variants where product_id in (" . implode(",", $thisProductArray) . "))");
                if (!empty($resultSet['sql_error'])) {
                    $this->addResult($resultSet['sql_error']);
                    break;
                }
                foreach ($subTables as $thisTable) {
                    $resultSet = executeQuery("delete from " . $thisTable . " where product_id in (" . implode(",", $thisProductArray) . ")");
                    if (!empty($resultSet['sql_error'])) {
                        $this->addResult($resultSet['sql_error']);
                        break 2;
                    }
                }
                foreach ($otherTables as $thisTable => $thisField) {
                    $resultSet = executeQuery("delete from " . $thisTable . " where " . $thisField . " in (" . implode(",", $thisProductArray) . ")");
                    if (!empty($resultSet['sql_error'])) {
                        $this->addResult($resultSet['sql_error']);
                        break 2;
                    }
                }
                $resultSet = executeQuery("delete from product_data where product_id in (" . implode(",", $thisProductArray) . ")");
                if (!empty($resultSet['sql_error'])) {
                    $this->addResult($resultSet['sql_error']);
                    break;
                }
                $resultSet = executeQuery("delete from products where product_id in (" . implode(",", $thisProductArray) . ")");
                if (!empty($resultSet['sql_error'])) {
                    $this->addResult($resultSet['sql_error']);
                    break;
                }
                $totalDeleted += $resultSet['affected_rows'];
                if (date("H") < 14 && date("H") > 5) {
                    break;
                }
            }
            $this->addResult($totalDeleted . " erroneous products deleted");
            $this->addResult(count($productIdArray) . " erroneous products remaining");
        }
		unset($productIdArray);

		# clean up duplicates in various table. Unique key can't be created duplicates of just product ID and associated Product ID are valid with different related product types.

		executeQuery("DELETE t1 FROM related_products t1 INNER JOIN related_products t2 WHERE t1.related_product_id < t2.related_product_id AND t1.product_id = t2.product_id and t1.associated_product_id = t2.associated_product_id and t1.related_product_type_id is null and t2.related_product_type_id is null");
		executeQuery("DELETE t1 FROM product_distributor_conversions t1 INNER JOIN product_distributor_conversions t2 WHERE t1.product_distributor_conversion_id < t2.product_distributor_conversion_id AND " .
			"t1.client_id = t2.client_id and t1.product_distributor_id is null and t2.product_distributor_id is null and t1.table_name = t2.table_name and t1.original_value = t2.original_value and " .
			"t1.original_value_qualifier = t2.original_value_qualifier");

		# fill contact identifier types that should be auto generated

		$contactIdentifierTypes = array();
		$resultSet = executeQuery("select * from contact_identifier_types where inactive = 0 and autogenerate = 1");
		while ($row = getNextRow($resultSet)) {
			$contactIdentifierTypes[] = $row;
		}
		foreach ($contactIdentifierTypes as $contactIdentifierType) {
			$resultSet = executeQuery("select * from contacts where client_id = ? and contact_id not in " .
				"(select contact_id from contact_identifiers where contact_identifier_type_id = ?)", $contactIdentifierType['client_id'], $contactIdentifierType['contact_identifier_type_id']);
			$insertCount = 0;
			while ($row = getNextRow($resultSet)) {
				$idString = generateContactIdentifier($contactIdentifierType['contact_identifier_type_id']);
				executeQuery("insert into contact_identifiers (contact_id,contact_identifier_type_id,identifier_value) values (?,?,?)",
					$row['contact_id'], $contactIdentifierType['contact_identifier_type_id'], $idString);
				$insertCount++;
			}
			$this->addResult(sprintf("Contact Identifier '%s' for client %s: identifiers created for %s contacts", $contactIdentifierType['description'],
				getFieldFromId("client_code", "clients", "client_id", $contactIdentifierType['client_id']), $insertCount));
		}

		# delete old shopping carts

		$totalDeleted = 0;
		for ($x = 0; $x < 5; $x++) {
			executeQuery("update shopping_carts set last_activity = '1776-07-04' where last_activity < date_sub(current_date,interval 1 year) limit 1000");
			executeQuery("delete from shopping_cart_item_addons where shopping_cart_item_id in (select shopping_cart_item_id from shopping_cart_items where shopping_cart_id in (select shopping_cart_id from shopping_carts where last_activity = '1776-07-04'))");
			executeQuery("delete from shopping_cart_items where shopping_cart_id in (select shopping_cart_id from shopping_carts where last_activity = '1776-07-04')");
			executeQuery("delete from product_map_overrides where shopping_cart_id in (select shopping_cart_id from shopping_carts where last_activity = '1776-07-04')");
			$deleteSet = executeQuery("delete from shopping_carts where last_activity = '1776-07-04'");
			$totalDeleted += $deleteSet['affected_rows'];
		}
		$this->addResult($totalDeleted . " old shopping carts deleted");

		$deleteSet = executeQuery("delete from shopping_carts where last_activity < date_sub(current_date,interval 3 day) and shopping_cart_id not in (select shopping_cart_id from shopping_cart_items) and shopping_cart_id not in (select shopping_cart_id from product_map_overrides)");
		$this->addResult($deleteSet['affected_rows'] . " empty shopping carts deleted");

		# delete old user notifications

		executeQuery("delete from user_notifications where time_submitted < date_sub(current_date,interval 3 month)");

		# delete old captcha codes

		executeQuery("delete from captcha_codes where time_submitted < date_sub(current_date,interval 1 day)");

		# delete temporary tables

		$dropTables = array();
		$count = 0;
		$resultSet = executeQuery("select TABLE_NAME from information_schema.tables where table_type = 'BASE TABLE' and table_schema = ? and " .
			"table_name like 'temporary_products_%'" . (empty($_GET['remove_all']) ? " and table_name not like 'temporary_products_" . date("Ymd") . "%'" : ""), $GLOBALS['gPrimaryDatabase']->getName());
		while ($row = getNextRow($resultSet)) {
			executeQuery("drop table " . $row['TABLE_NAME']);
			$count++;
		}
		$this->addResult($count . " temporary tables dropped");

		# delete IP address errors

		executeQuery("delete from ip_address_errors where log_time < (now() - interval 5 minute)");
		executeQuery("delete from query_log");

		# make expired products inactive

		executeQuery("update products set inactive = 1 where expiration_date is not null and expiration_date < current_date");
		$resultSet = executeQuery("delete from related_products where product_id = associated_product_id");
		$this->addResult($resultSet['affected_rows'] . " related products deleted because products are the same");

		# make products marked HIDE_WHEN_SOLD_OUT internal use only

		$resultSet = executeQuery("select product_id, internal_use_only from products where product_id in " .
			"(select product_id from product_category_links where product_category_id in " .
			"(select product_category_id from product_categories where product_category_code = 'HIDE_WHEN_SOLD_OUT'))");
		$hiddenCount = 0;
		while ($row = getNextRow($resultSet)) {
			$productInventoryRow = getRowFromId("product_inventories", "product_id", $row['product_id'], "quantity > 0");
			if (empty($productInventoryRow) && empty($row['internal_use_only'])) {
				executeQuery("update products set internal_use_only = 1 where product_id = ?", $row['product_id']);
				$hiddenCount++;
			}
		}
		$this->addResult($hiddenCount . " products in HIDE_WHEN_SOLD_OUT category marked internal use only.");

		if (date("j") == 1) {
			executeQuery("delete from search_term_log where search_term_id in (select search_term_id from search_terms where use_count = 1)");
			executeQuery("delete from search_terms where use_count = 1");
		}

		# Prevent dropshipping optics for clients that don't have NoFraud

		if (getPreference("REQUIRE_NOFRAUD_FOR_OPTICS_SHIPPING")) { // system preference
			$departmentResults = executeQuery("select client_id,product_department_id from product_departments where product_department_code = 'OPTICS' and client_id in (select client_id from clients where inactive = 0)");
			while ($departmentRow = getNextRow($departmentResults)) {
				changeClient($departmentRow['client_id']);
                $categoryCount = 0;
				if (getPreference("REQUIRE_NOFRAUD_FOR_OPTICS_SHIPPING")) { // check for client-set override
					$noFraudToken = getPreference("NOFRAUD_TOKEN");
					if (empty($noFraudToken)) {
						$categoryResult = executeQuery("select * from product_categories where product_category_id in (select product_category_id from product_category_departments where product_department_id = ?) or " .
							"product_category_id in (select product_category_id from product_category_group_links where product_category_group_id in (select product_category_group_id from product_category_group_departments " .
							" where product_department_id = ?))", $departmentRow['product_department_id'], $departmentRow['product_department_id']);
						while ($categoryRow = getNextRow($categoryResult)) {
							$updateResult = executeQuery("update product_categories set cannot_dropship = 1 where product_category_id = ?", $categoryRow['product_category_id']);
							$categoryCount += $updateResult['affected_rows'];
						}
                        if($categoryCount > 0) {
                            $this->addResult($categoryCount . " categories in OPTICS departments marked cannot dropship because client " . $GLOBALS['gClientRow']['client_code'] . " is not using NoFraud");
                        }
					} else {
                        $this->addResult( $GLOBALS['gClientRow']['client_code'] . " is using NoFraud; no categories marked cannot dropship.");
                    }
                } else {
                    $this->addResult( $GLOBALS['gClientRow']['client_code'] . " has client preference set to allow optics shipping; no categories marked cannot dropship.");
                }
			}
			changeClient($GLOBALS['gDefaultClientId']);
		}

# clear distributor inventory cache weekly

		if (date("w") == 6) {

			# find and merge duplicate products

			executeQuery("delete from ip_address_blacklist where time_submitted < date_sub(current_date,interval 30 day)");

			$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", "AUTOCOMPLETE_TABLE_NAMES");
			if (empty($preferenceId)) {
				$resultSet = executeQuery("insert into preferences (preference_code, description, client_setable, data_type) values ('AUTOCOMPLETE_TABLE_NAMES', " .
					"'autocomplete tables',1,'text')");
				$preferenceId = $resultSet['insert_id'];
			}
			$tableNames = "";
			$resultSet = executeReadQuery("select * from information_schema.tables where table_schema = ? and table_rows >= ? and table_name not like 'temporary_product%'", $GLOBALS['gPrimaryDatabase']->getName(), $GLOBALS['gClientCount'] * 250);
			while ($row = getNextRow($resultSet)) {
				$tableNames .= (empty($tableNames) ? "" : ",") . $row['TABLE_NAME'];
			}
			executeQuery("update preferences set system_value = ? where preference_id = ?", $tableNames, $preferenceId);

			$this->addResult("Cached Cleared");
			$pageId = $GLOBALS['gAllPageCodes']["CLEARCACHE"];
			if (!empty($pageId)) {
				executeQuery("delete from page_text_chunks where page_id = ?", $pageId);
				$randomString = getRandomString(8);
				executeQuery("insert into page_text_chunks (page_text_chunk_code,page_id,description,content) values ('ACCESS_CODE',?,'Access Code',?)", $pageId, $randomString);

				$webUrl = getDomainName();
				$linkUrl = $webUrl . "/clearcache.php?access_code=" . $randomString;
				$startTime = getMilliseconds();
				$curlHandle = curl_init($linkUrl);
				curl_setopt($curlHandle, CURLOPT_HEADER, 0);
				curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 180);
				curl_setopt($curlHandle, CURLOPT_TIMEOUT, 600);
				$siteContent = curl_exec($curlHandle);
				curl_close($curlHandle);
				$endTime = getMilliseconds();
				if ($siteContent === false) {
					$this->addResult("Clear Cache URL ERROR: " . $linkUrl);
					$this->iErrorsFound = true;
				} else {
					$this->addResult("Clear Cache URL loaded: " . $linkUrl . ", Took " . round(($endTime - $startTime) / 1000, 2) . " seconds");
				}
				executeQuery("delete from page_text_chunks where page_id = ?", $pageId);
			}

			# turn off logging
			$preferenceSet = executeQuery("select * from preferences where temporary_setting = 1");
			$this->addResult($preferenceSet['row_count'] . " preferences reset");
			while ($preferenceRow = getNextRow($preferenceSet)) {
				executeQuery("update preferences set system_value = null where preference_id = ?", $preferenceRow['preference_id']);
				executeQuery("delete from client_preferences where preference_id = ?", $preferenceRow['preference_id']);
				executeQuery("delete from user_preferences where preference_id = ?", $preferenceRow['preference_id']);
			}
		}

		$additionalWhereStatements = array();
		$additionalWhereStatements['product_inventory_log'] = "product_inventory_id in (select product_inventory_id from product_inventories where " .
			"location_id in (select location_id from locations where product_distributor_id is not null))";

		$logPurgeTables = array('action_log', 'api_log', 'background_process_log', 'change_log', 'click_log', 'download_log', 'ecommerce_log', 'email_log', 'error_log', 'image_usage_log',
			'not_found_log', 'product_distributor_log', 'product_inventory_log', 'product_view_log', 'program_log', 'query_log', 'search_term_log', 'security_log', 'server_monitor_log',
			'user_activity_log', 'web_user_pages', 'debug_log', 'merchant_log');

		$resultSet = executeQuery("select * from log_purge_parameters where inactive = 0");
		while ($row = getNextRow($resultSet)) {
			if (!in_array($row['table_name'], $logPurgeTables)) {
				continue;
			}
			$tableId = getFieldFromId("table_id", "tables", "table_name", $row['table_name']);
			if (empty($tableId)) {
				$this->addResult("Table '" . $row['table_name'] . "' does not exist.");
				$this->iErrorsFound = true;
				continue;
			}
			$columnName = getFieldFromId("column_name", "column_definitions", "column_type", "datetime", "column_definition_id in (select column_definition_id from table_columns where table_id = ?)", $tableId);
			if (empty($columnName)) {
				$columnName = getFieldFromId("column_name", "column_definitions", "column_type", "date", "column_definition_id in (select column_definition_id from table_columns where table_id = ?)", $tableId);
			}
			if (empty($columnName)) {
				$this->addResult("Table '" . $row['table_name'] . "' does not have a date or datetime column.");
				$this->iErrorsFound = true;
				continue;
			}
            $limit = 10000;
            $totalDeleted = 0;
            $deleted = 1;
            while($deleted > 0) {
                $purgeSet = executeQuery(sprintf("delete from %s where %s < date_sub(current_date, interval %s day) %s limit %s", $row['table_name'], $columnName,$row['maximum_days'],
                    (array_key_exists($row['table_name'], $additionalWhereStatements) ? " and " . $additionalWhereStatements[$row['table_name']] : ""), $limit));
                $deleted = $purgeSet['affected_rows'];
                $totalDeleted += $deleted;
            }
			$this->addResult($totalDeleted . " rows deleted from table '" . $row['table_name'] . "'");
		}

		executeQuery("delete from user_notifications where time_deleted < date_sub(now(), interval 30 day)");

		$resultSet = executeQuery("select user_id from users where inactive = 0 and locked = 1 and time_locked_out is not null and time_locked_out < date_sub(now(), interval 1 hour)");
		while ($row = getNextRow($resultSet)) {
			executeQuery("update users set locked = 0 where user_id = ?", $row['user_id']);
		}

		$resultSet = executeQuery("delete from add_hashes where date_used < date_sub(now(), interval 30 day)");
		$this->addResult($resultSet['affected_rows'] . " old rows deleted from add_hashes");

		$resultSet = executeQuery("delete from background_process_log where run_time < date_sub(current_date,interval 30 day)");
		$this->addResult($resultSet['affected_rows'] . " old rows deleted from background_process_log");

# make inactive administrators who have not logged in for 90 days

		if ((date("w") == 0 && date("g") == 1) || $_GET['force'] == "true") {
			if (getPreference("PCI_COMPLIANCE")) {
				$userCount = 0;
				$resultSet = executeQuery("select * from users where administrator_flag = 1 and inactive = 0 and " .
					"last_login < date_sub(now(), interval 90 day) and user_id not in (select primary_identifier from change_log where " .
					"table_name = 'users' and time_changed > date_sub(now(),interval 90 day))");
				$emailContent = array();
				while ($row = getNextRow($resultSet)) {
					$GLOBALS['gChangeLogNotes'] = "Inactive because of non-use for 90 days";
					$userDataTable = new DataTable("users");
					$userDataTable->setSaveOnlyPresent(true);
					$userDataTable->saveRecord(array("name_values" => array("inactive" => "1"), "primary_id" => $row['user_id']));
					$GLOBALS['gChangeLogNotes'] = "";
					$userCount++;
					if (!array_key_exists($row['client_id'], $emailContent)) {
						$emailContent[$row['client_id']] = "";
					}
					$emailContent[$row['client_id']] .= (empty($emailContent) ? "" : "<br>") . "User account '" . $row['user_name'] . "' for " . getDisplayName($row['contact_id']) . " was made inactive due to inactivity.";
				}
				if (!empty($emailContent)) {
					foreach ($emailContent as $clientId => $emailBody) {
						$GLOBALS['gClientId'] = $clientId;
						sendEmail(array("subject" => "User Account Inactivated", "body" => $emailBody, "notification_code" => "USER_MANAGEMENT"));
					}
				}
				$this->addResult($userCount . " stale administrator users made inactive");
			}

# remove images from file system that are no longer used

			$imageIds = array();
			$resultSet = executeQuery("select image_id from images where os_filename is not null order by image_id");
			while ($row = getNextRow($resultSet)) {
				$imageIds[$row['image_id']] = $row['image_id'];
			}
			$fileIds = array();
			$resultSet = executeQuery("select file_id from files where os_filename is not null order by file_id");
			while ($row = getNextRow($resultSet)) {
				$fileIds[$row['file_id']] = $row['file_id'];
			}

			$fileDirectory = getPreference("EXTERNAL_FILE_DIRECTORY");
			if (empty($fileDirectory)) {
				$fileDirectory = "/documents";
			}

			#TODO: remove files from S3 that are no longer used

			$deleteCount = 0;
			if (substr($fileDirectory, 0, 3) != "S3:") {
				$files = scandir($fileDirectory);
				foreach ($files as $filename) {
					$fileParts = explode(".", $filename);
					if (substr($fileParts[0], 0, strlen("image")) == "image") {
						$fileNumber = substr($fileParts[0], strlen("image"));
						if (is_numeric($fileNumber)) {
							if (!array_key_exists($fileNumber, $imageIds)) {
								$osFilename = $fileDirectory . $filename;
								unlink($osFilename);
								$deleteCount++;
							}
						}
					}
					if (substr($fileParts[0], 0, strlen("file")) == "file") {
						$fileNumber = substr($fileParts[0], strlen("file"));
						if (is_numeric($fileNumber)) {
							if (!array_key_exists($fileNumber, $fileIds)) {
								$osFilename = $fileDirectory . $filename;
								unlink($osFilename);
								$deleteCount++;
							}
						}
					}
				}
			}
			$this->addResult($deleteCount . " unused images & files deleted");
		}

# Find and remove empty images

		$imageIds = array();
		$resultSet = executeQuery("select image_id from images where os_filename is null and file_content is null");
		while ($row = getNextRow($resultSet)) {
			$imageIds[] = $row['image_id'];
		}
		if (!empty($imageIds)) {
			executeQuery("update contacts set image_id = null where image_id is not null and image_id in (" . implode(",", $imageIds) . ")");
			$GLOBALS['gPrimaryDatabase']->ignoreError(true);
			foreach ($imageIds as $imageId) {
				executeQuery("delete ignore from images where image_id = ?", $imageId);
			}
			$GLOBALS['gPrimaryDatabase']->ignoreError(false);
		}

# remove forms that are past the delete time

		$resultSet = executeQuery("select * from form_definitions where auto_delete_days is not null");
		$formsDeleted = 0;
		while ($row = getNextRow($resultSet)) {
			if (empty($row['auto_delete_days'])) {
				continue;
			}
			$imageIds = array();
			$fileIds = array();
			$formSet = executeQuery("select * from forms where form_definition_id = ? and date_created < date_sub(current_date,interval " . $row['auto_delete_days'] . " day)", $row['form_definition_id']);
			while ($formRow = getNextRow($formSet)) {
				$dataSet = executeQuery("select * from form_data where form_id = ?", $formRow['form_id']);
				while ($dataRow = getNextRow($dataSet)) {
					if (!empty($dataRow['image_id'])) {
						$imageIds[] = $dataRow['image_id'];
					}
					if (!empty($dataRow['file_id'])) {
						$fileIds[] = $dataRow['file_id'];
					}
				}
				$dataSet = executeQuery("select * from form_attachments where form_id = ?", $formRow['form_id']);
				while ($dataRow = getNextRow($dataSet)) {
					if (!empty($dataRow['file_id'])) {
						$fileIds[] = $dataRow['file_id'];
					}
				}
				executeQuery("delete from form_data where form_id = ?", $formRow['form_id']);
				executeQuery("delete from form_notes where form_id = ?", $formRow['form_id']);
				executeQuery("delete from form_attachments where form_id = ?", $formRow['form_id']);
				executeQuery("update forms set parent_form_id = null where parent_form_id = ?", $formRow['form_id']);
				executeQuery("delete from form_status where form_id = ?", $formRow['form_id']);
				executeQuery("delete from forms where form_id = ?", $formRow['form_id']);
			}
			foreach ($imageIds as $imageId) {
				executeQuery("delete ignore from images where image_id = ?", $imageId);
			}
			foreach ($fileIds as $fileId) {
				executeQuery("delete ignore from files where file_id = ?", $fileId);
			}
			$formsDeleted++;
		}
		$this->addResult($formsDeleted . " forms deleted");

		$resultSet = executeQuery("select * from contacts where hash_code is null limit 2000");
		while ($row = getNextRow($resultSet)) {
			$hashCode = md5(uniqid(mt_rand(), true) . $row['first_name'] . $row['last_name'] . $row['contact_id'] . $row['email_address'] . $row['date_created']);
			executeQuery("update contacts set hash_code = ? where contact_id = ?", $hashCode, $row['contact_id']);
		}

		$resultSet = executeQuery("select * from images where hash_code is null");
		$this->addResult($resultSet['row_count'] . " images found without hash code");
		while ($row = getNextRow($resultSet)) {
			getImageFilename($row['image_id']);
		}

		$GLOBALS['gChangeLogNotes'] = "Updating User Subscriptions from Cleanup process";
		$results = updateUserSubscriptions();
		$GLOBALS['gChangeLogNotes'] = "";
		if (is_array($results)) {
			foreach ($results as $result) {
				$this->addResult($result);
			}
		}

		if (file_exists($GLOBALS['gDocumentRoot'] . "/background/localcleanup.inc")) {
			include_once "localcleanup.inc";
		}
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
