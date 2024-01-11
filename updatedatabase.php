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

$GLOBALS['gPreemptivePage'] = true;
$GLOBALS['gPageCode'] = "UPDATEDATABASE";
$GLOBALS['gDefaultAjaxTimeout'] = 600000;
ini_set("memory_limit", "8192M");
require_once "shared/startup.inc";
require_once "managementtemplate.inc";
require_once "databaseupdates.inc";

class UpdateDatabasePage extends Page {

	function mainContent() {
		echo $this->getPageData("content");


		if (!empty($_GET['special_action'])) {
			switch ($_GET['special_action']) {
				case "update_product_costs":
					$resultSet = executeQuery("select product_id from products where client_id = ? and inactive = 0 and product_id not in (select primary_identifier from change_log where table_name = 'products' and column_name = 'base_cost' and time_changed > date_sub(now(),interval 7 day))",$GLOBALS['gClientId']);
					$count = 0;
					while ($row = getNextRow($resultSet)) {
						ProductCatalog::calculateProductCost($row['product_id'],"Manual Update");
						$count++;
					}
					echo $count . " product costs updated";
					break;
				case "fix_duplicates":
					$resultSet = executeQuery("select product_id,group_concat(image_id) from product_images where product_id in (select product_id from " .
						"(select product_id,count(*) from product_images where product_id in (select product_id from products where client_id = ? and version < 1000 having count(*) > 2) group by product_id having count(*) > 1 order by count(*) desc) as group_table) group by product_id",$GLOBALS['gClientId']);
					$productIds = array();
					while ($row = getNextRow($resultSet)) {
						$productIds[$row['product_id']] = explode(",", $row['group_concat(image_id)']);
					}
					$count = 0;
					foreach ($productIds as $productId => $imageIds) {
						$goodImageIds = array();
						$duplicateImageIds = array();
						foreach ($imageIds as $imageId) {
							if (empty($goodImageIds)) {
								$goodImageIds[] = $imageId;
								continue;
							}
							$resultSet = executeQuery("select image_id from images where image_id in (" . implode(",",$goodImageIds) . ") and file_content = (select file_content from images where image_id = ?)",$imageId);
							if ($row = getNextRow($resultSet)) {
								$duplicateImageIds[] = $imageId;
							} else {
								$goodImageIds[] = $imageId;
							}
						}
						if (!empty($duplicateImageIds)) {
							$deleteSet = executeQuery("delete from product_images where image_id in (" . implode(",",$duplicateImageIds) . ")");
							if (!empty($deleteSet['sql_error'])) {
								echo $deleteSet['sql_error'] . "<br>";
								break;
							}
							$deleteSet = executeQuery("delete ignore from images where image_id in (" . implode(",",$duplicateImageIds) . ")");
							if (!empty($deleteSet['sql_error'])) {
								echo $deleteSet['sql_error'] . "<br>";
								break;
							}
							$count += count($duplicateImageIds);
						}
						executeQuery("update products set version = 51823 where product_id = ?",$productId);
					}
					echo $count . " duplicate images deleted";
					break;
				case "test_inventory":
					$clientSet = executeQuery("select * from clients where inactive = 0");
					while ($clientRow = getNextRow($clientSet)) {
						changeClient($clientRow['client_id']);
						$productCatalog = new ProductCatalog();
						echo "Processing for " . $clientRow['client_code'] . "<br>";
						$productIdArray = array();
						$resultSet = executeQuery("select distinct product_id from product_inventories where location_id in (select location_id from locations where inactive = 0 and internal_use_only = 0) and " .
							"location_id not in (select location_id from location_credentials where inactive = 1) and quantity > 5 and product_id in (select product_id from products where " .
							"inactive = 0 and client_id = ?) order by rand() limit 1000", $clientRow['client_id']);
						while ($row = getNextRow($resultSet)) {
							$productIdArray[] = $row['product_id'];
						}
						$inventoryCounts = $productCatalog->getInventoryCounts(false, $productIdArray);
						foreach ($inventoryCounts as $productId => $inventoryNumbers) {
							if ($inventoryNumbers['total'] <= 0) {
								echo $productId . ": " . jsonEncode($inventoryNumbers) . "<br>";
							}
						}
					}
					break;
				case "cache_prices":
					$startTime = getMilliseconds();
					$resultSet = executeReadQuery("select product_id,parameters from product_sale_prices where expiration_time > now() and product_id in (select product_id from products where client_id = ?)", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$cachedPrice = json_decode($row['parameters'], true);
						if (is_array($cachedPrice)) {
							$cachedSalePrice = $cachedPrice['sale_price'];
						} else {
							$cachedSalePrice = $cachedPrice;
							$cachedPrice = array("sale_price" => $cachedSalePrice);
						}
						setCachedData("price::", $row['product_id'], $cachedPrice, ($cachedSalePrice > 0 ? 2 : .25));
					}
					setCachedData("stored_prices_were_cached", "stored_prices_were_cached", true, 2);
					$endTime = getMilliseconds();
					echo round(($endTime - $startTime) / 1000, 4) . " to load<br>";
					break;
				case "remove_noupc":
					$exclusionTables = array("courses", "distributor_order_items", "event_registration_products", "event_types", "events", "form_definitions", "gunbroker_products", "product_reviews",
						"order_items", "promotion_purchased_products", "promotion_rewards_excluded_products", "promotion_rewards_products", "promotion_set_products", "product_prices", "product_offers",
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

					$resultSet = executeQuery("select product_id from products where (product_id not in (select product_id from product_data) or product_id in (select product_id from product_data where upc_code like 'NOUPC%')) and " .
						"product_id not in (select product_id from distributor_product_codes where product_distributor_id in (select product_distributor_id from product_distributors where product_distributor_code = 'PRINTFUL'))" .
						($GLOBALS['gDefaultClientId'] == $GLOBALS['gClientId'] ? "" : " and products.client_id = " . $GLOBALS['gClientId']));
					$productIdArray = array();
					while ($row = getNextRow($resultSet)) {
						$deleteProduct = true;
						foreach ($exclusionTables as $thisTable) {
							$productId = getFieldFromId("product_id", $thisTable, "product_id", $row['product_id']);
							if (!empty($productId)) {
								$deleteProduct = false;
							}
						}
						if (!$deleteProduct) {
							continue;
						}
						foreach ($exclusionOtherTables as $thisTable => $thisField) {
							$productId = getFieldFromId($thisField, $thisTable, $thisField, $row['product_id']);
							if (!empty($productId)) {
								$deleteProduct = false;
							}
						}
						if ($deleteProduct) {
							$productIdArray[] = $row['product_id'];
						}
					}

					echo "Count: " . count($productIdArray) . "<br>";
					executeQuery("drop table if exists remove_noupc");
					executeQuery("create table remove_noupc (product_id int not null,primary key (product_id))");
					$count = 0;
					foreach ($productIdArray as $productId) {
						executeQuery("insert into remove_noupc values (?)", $productId);
						if ($count > 5000) {
							break;
						}
					}

					$resultSet = executeQuery("delete from product_inventory_log where product_inventory_id in (select product_inventory_id from product_inventories where product_id in (select product_id from remove_noupc))");
					if (!empty($resultSet['sql_error'])) {
						echo $resultSet['sql_error'];
						break;
					}
					foreach ($subTables as $thisTable) {
						$resultSet = executeQuery("delete from " . $thisTable . " where product_id in (select product_id from remove_noupc)");
						if (!empty($resultSet['sql_error'])) {
							echo $resultSet['sql_error'];
							break;
						}
					}
					foreach ($otherTables as $thisTable => $thisField) {
						$resultSet = executeQuery("delete from " . $thisTable . " where " . $thisField . " in (select product_id from remove_noupc)");
						if (!empty($resultSet['sql_error'])) {
							echo $resultSet['sql_error'];
							break;
						}
					}
					$resultSet = executeQuery("delete from product_data where product_id in (select product_id from remove_noupc)");
					if (!empty($resultSet['sql_error'])) {
						echo $resultSet['sql_error'];
						break;
					}
					$resultSet = executeQuery("delete from products where product_id in (select product_id from remove_noupc)");
					if (!empty($resultSet['sql_error'])) {
						echo $resultSet['sql_error'];
						break;
					}
					echo $resultSet['affected_rows'] . " products deleted<br>";
					$resultSet = executeQuery("select count(*) from products where (product_id not in (select product_id from product_data) or product_id in (select product_id from product_data where upc_code like 'NOUPC%')) and " .
						"product_id not in (select product_id from distributor_product_codes where product_distributor_id in (select product_distributor_id from product_distributors where product_distributor_code = 'PRINTFUL'))");
					if ($row = getNextRow($resultSet)) {
						echo $row['count(*)'] . " products left<br>";
					}
					break;
				case "test_large_query":
					$imageIds = array();
					if (empty($_GET['limit']) || !is_numeric($_GET['limit'])) {
						$_GET['limit'] = 2000;
					}
					$resultSet = executeQuery("select image_id from images order by rand() limit " . $_GET['limit']);
					while ($row = getNextRow($resultSet)) {
						$imageIds[] = $row['image_id'];
					}
					echo "<p>Selecting " . count($imageIds) . " images in various ways</p>";
					$GLOBALS['gStartTime'] = getMilliseconds();
					$resultSet = executeQuery("select client_id,image_id,hash_code,extension,os_filename,remote_storage from images where image_id in (" . implode(",", $imageIds) . ")");
					while ($row = getNextRow($resultSet)) {
						$row['alternate_image_id'] = $row['image_id'];
					}
					$GLOBALS['gEndTime'] = getMilliseconds();
					echo "<p>All images in " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 2) . " seconds</p>";
					addDebugLog("All images in " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 2) . " seconds", true);

					$GLOBALS['gStartTime'] = getMilliseconds();
					$resultSet = executeQuery("select client_id,image_id,hash_code,extension,os_filename,remote_storage from images where image_id in (" . implode(",", array_fill(0, count($imageIds), "?")) . ")", $imageIds);
					while ($row = getNextRow($resultSet)) {
						$row['alternate_image_id'] = $row['image_id'];
					}
					$GLOBALS['gEndTime'] = getMilliseconds();
					echo "<p>All images in parameters in " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 2) . " seconds</p>";
					addDebugLog("All images in parameters in " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 2) . " seconds", true);

					$GLOBALS['gStartTime'] = getMilliseconds();
					foreach ($imageIds as $thisImageId) {
						$resultSet = executeQuery("select client_id,image_id,hash_code,extension,os_filename,remote_storage from images where image_id = ?", $thisImageId);
						while ($row = getNextRow($resultSet)) {
							$row['alternate_image_id'] = $row['image_id'];
						}
					}
					$GLOBALS['gEndTime'] = getMilliseconds();
					echo "<p>One at a time images in " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 2) . " seconds</p>";
					addDebugLog("One at a time images in " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 2) . " seconds", true);

					$GLOBALS['gStartTime'] = getMilliseconds();
					$imageSection = array();
					foreach ($imageIds as $thisImageId) {
						$imageSection[] = $thisImageId;
						if (count($imageSection) == 100) {
							$resultSet = executeQuery("select client_id,image_id,hash_code,extension,os_filename,remote_storage from images where image_id in (" . implode(",", $imageSection) . ")");
							while ($row = getNextRow($resultSet)) {
								$row['alternate_image_id'] = $row['image_id'];
							}
							$imageSection = array();
						}
					}
					$GLOBALS['gEndTime'] = getMilliseconds();
					echo "<p>100 at a time images in " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 2) . " seconds</p>";
					addDebugLog("100 at a time images in " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 2) . " seconds", true);

					$GLOBALS['gStartTime'] = getMilliseconds();
					$imageSection = array();
					foreach ($imageIds as $thisImageId) {
						$imageSection[] = $thisImageId;
						if (count($imageSection) == 1000) {
							$resultSet = executeQuery("select client_id,image_id,hash_code,extension,os_filename,remote_storage from images where image_id in (" . implode(",", $imageSection) . ")");
							while ($row = getNextRow($resultSet)) {
								$row['alternate_image_id'] = $row['image_id'];
							}
							$imageSection = array();
						}
					}
					$GLOBALS['gEndTime'] = getMilliseconds();
					echo "<p>1000 at a time images in " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 2) . " seconds</p>";
					addDebugLog("1000 at a time images in " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 2) . " seconds", true);

					$GLOBALS['gStartTime'] = getMilliseconds();
					$imageSection = array();
					foreach ($imageIds as $thisImageId) {
						$imageSection[] = $thisImageId;
						if (count($imageSection) == 1000) {
							$resultSet = executeQuery("select client_id,image_id,hash_code,extension,os_filename,remote_storage from images where image_id in (" . implode(",", array_fill(0, count($imageSection), "?")) . ")", $imageSection);
							while ($row = getNextRow($resultSet)) {
								$row['alternate_image_id'] = $row['image_id'];
							}
							$imageSection = array();
						}
					}
					$GLOBALS['gEndTime'] = getMilliseconds();
					echo "<p>1000 at a time with parameters images in " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 2) . " seconds</p>";
					addDebugLog("1000 at a time with parameters images in " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 2) . " seconds", true);

					break;
				case "process_images":
					$GLOBALS['gStartTime'] = getMilliseconds();
					$resultSet = executeQuery("select image_id from images where client_id = ? and hash_code is null", $GLOBALS['gClientId']);
					echo "<p>" . $resultSet['row_count'] . " images found</p>";
					$count = 0;
					while ($row = getNextRow($resultSet)) {
						getImageFilename($row['image_id']);
						$count++;
						if ($count >= 1000) {
							break;
						}
					}
					$GLOBALS['gEndTime'] = getMilliseconds();
					echo "<p>" . $count . " images processed in " . round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 2) . " seconds</p>";
					break;
				case "check_max_id":
					$resultSet = executeQuery("select table_name,(select column_name from column_definitions where column_definition_id = (select column_definition_id from table_columns where table_id = tables.table_id and primary_table_key = 1)) primary_key from tables");
					$tables = array();
					while ($row = getNextRow($resultSet)) {
						$tables[] = $row;
					}
					$maximumId = 0;
					$maximumTableName = false;
					$tableValues = array();
					foreach ($tables as $tableInfo) {
						$resultSet = executeQuery("select min(" . $tableInfo['primary_key'] . ") as min_id,max(" . $tableInfo['primary_key'] . ") as max_id from " . $tableInfo['table_name']);
						if ($row = getNextRow($resultSet)) {
							if (!empty($row['max_id'])) {
								$tableValues[] = array("table_name" => $tableInfo['table_name'], "min" => $row['min_id'], "max" => $row['max_id']);
							}
						}
					}
					echo count($tableValues) . " tables found<br>";
					foreach ($tableValues as $tableInfo) {
						if ($tableInfo['max'] > 10000000 || ($tableInfo['max'] - $tableInfo['min']) > 10000000) {
							echo $tableInfo['table_name'] . ": " . $tableInfo['min'] . ":" . $tableInfo['max'] . ":" . number_format($tableInfo['max'] - $tableInfo['min']) . "<br>";
						}
					}
					break;
				case "fix_s3_files":
					$awsAccessKey = getPreference("AWS_S3_ACCESS_KEY");
					$awsSecretKey = getPreference("AWS_S3_SECRET_KEY");
					$awsRegion = getPreference("AWS_REGION");
					$s3 = new S3($awsAccessKey, $awsSecretKey, false, 's3' . (empty($awsRegion) ? "" : "." . $awsRegion) . '.amazonaws.com', $awsRegion);
					$s3->setSignatureVersion('v4');

					$resultSet = executeQuery("select * from files where os_filename like 'S3%' and client_id = ?", $GLOBALS['gClientId']);
					$fixCount = 0;
					while ($row = getNextRow($resultSet)) {
						$osFilename = $row['os_filename'];
						$fileParts = explode("/", str_replace("S3://", "", $osFilename), 2);
						$bucketName = $fileParts[0];
						$objectName = $fileParts[1];
						$objectInfo = $s3->getObjectInfo($bucketName, $objectName);
						if (empty($objectInfo) || empty($objectInfo['size'])) {
							$fileContents = file_get_contents("https://manage7.coreware.com/download.php?id=" . $row['file_id'] . "&source=client_load&connection_key=96D265101F55BC434E509754994772C2");
							$return = putExternalFileContents($row['file_id'], $row['extension'], $fileContents);
							$fixCount++;
						}
					}
					echo $fixCount . " files fixed<br>";
					break;
				case "fix_s3_images":
					$awsAccessKey = getPreference("AWS_S3_ACCESS_KEY");
					$awsSecretKey = getPreference("AWS_S3_SECRET_KEY");
					$awsRegion = getPreference("AWS_REGION");
					$s3 = new S3($awsAccessKey, $awsSecretKey, false, 's3' . (empty($awsRegion) ? "" : "." . $awsRegion) . '.amazonaws.com', $awsRegion);
					$s3->setSignatureVersion('v4');

					$resultSet = executeQuery("select * from images where os_filename like 'S3%' and client_id = ?", $GLOBALS['gClientId']);
					$fixCount = 0;
					while ($row = getNextRow($resultSet)) {
						$osFilename = $row['os_filename'];
						$fileParts = explode("/", str_replace("S3://", "", $osFilename), 2);
						$bucketName = $fileParts[0];
						$objectName = $fileParts[1];
						$objectInfo = $s3->getObjectInfo($bucketName, $objectName);
						if (empty($objectInfo) || empty($objectInfo['size'])) {
							$fileContents = file_get_contents("https://manage7.coreware.com/getimage.php?id=" . $row['image_id'] . "&connection_key=96D265101F55BC434E509754994772C2");
							$return = putExternalImageContents($row['image_id'], $row['extension'], $fileContents);
							$fixCount++;
						}
					}
					echo $fixCount . " images fixed<br>";
					break;
				case "email_for_quote":
					$minimumSeconds = 300;
					$openingHour = $_GET['open'];
					if (empty($openingHour)) {
						$openingHour = 7.0;
					}
					$closingHour = $_GET['close'];
					if (empty($closingHour)) {
						$closingHour = 19.0;
					}
					$currentHour = rand(50, 2350) / 100;
					if ($currentHour < $openingHour || $currentHour > $closingHour) {
						$minimumSeconds += ($currentHour < $openingHour ? $openingHour - $currentHour : 24 - $currentHour + $openingHour) * 3600;
					}
					echo "Current time is " . Events::getDisplayTime($currentHour) . " - Email will go out between " . $minimumSeconds . " and " . ($minimumSeconds + 300) . " seconds.";
					break;
				case "calculate_prices":
					$GLOBALS['gStartTime'] = getMilliseconds();
					if (empty($_GET['price_count']) || !is_numeric($_GET['price_count'])) {
						$priceCount = 1000;
					} else {
						$priceCount = $_GET['price_count'];
					}
					$resultSet = executeQuery("select count(*) from products where client_id = ? and product_id not in (select product_id from product_sale_prices where expiration_time < now())", $GLOBALS['gClientId']);
					$GLOBALS['gStartTime'] = getMilliseconds();
					if ($row = getNextRow($resultSet)) {
						echo $row['count(*)'] . " products found to be recalculated<br>";
					}
					$GLOBALS['gPrimaryDatabase']->startTransaction();
					$resultSet = executeQuery("select product_id from products where client_id = ? and product_id not in (select product_id from product_sale_prices where expiration_time < now()) limit " . $priceCount, $GLOBALS['gClientId']);
					$count = 0;
					while ($row = getNextRow($resultSet)) {
						$count++;
						ProductCatalog::calculateAllProductSalePrices($row['product_id']);
						if ($count % 200 == 0) {
							$GLOBALS['gPrimaryDatabase']->commitTransaction();
						}
					}
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
					$GLOBALS['gEndTime'] = getMilliseconds();
					$average = ($count == 0 ? 0 : round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000 / $count, 4));
					echo round(($GLOBALS['gEndTime'] - $GLOBALS['gStartTime']) / 1000, 2) . " seconds to do " . $count . " products, averaging " . $average . " sec/product<br>";
					break;
			}
			if (function_exists("_localSpecialActions")) {
				_localSpecialActions();
			}
			return true;
		}

		$databaseVersion = getPreference("DATABASE_VERSION");
		if ($GLOBALS['gClientId'] != $GLOBALS['gDefaultClientId']) {
			echo "<p class='color-red size-16-point'>Must be logged in to primary Client</p>";
			return true;
		}

		echo "<p>System Name: " . getPreference("SYSTEM_NAME") . "</p>";

# Update Management Template

		echo "<p>PHP Version: " . phpversion() . "</p>";
		if ($GLOBALS['gApcuEnabled']) {
			echo "<p>APCU Caching is enabled. <a href='/apc.php' target='_blank'>Monitor</a></p>";
		} else {
			echo "<p>APCU Caching is NOT enabled.</p>";
		}
		$notEnabled = "";
		$extensions = array("Zend OPcache", "mysqlnd", "gd", "mbstring", "xml", "soap", "imap", "curl", "ftp", "json", "exif", "PDO", "zip");
		foreach ($extensions as $thisExtension) {
			if (!extension_loaded($thisExtension)) {
				$notEnabled .= (empty($notEnabled) ? "" : ", ") . $thisExtension;
			}
		}
		echo "<p>" . (empty($notEnabled) ? "" : "Not Enabled: ") . $notEnabled . "</p>";
		if ($GLOBALS['gUserRow']['superuser_flag'] && ($GLOBALS['gClientRow']['client_code'] == "COREWARE" || $GLOBALS['gClientRow']['client_code'] == "CORE")) {
			echo "<p><a href='https://www.site24x7.com/public/dashboard/kCHptonx_rk6B0aQ-jpCoRzvqvioGFfBhC0o6P0NhtlcwqeKGT37bJWRJh3Oza6Bct4y4AuPLidTUlrzA0sBF8WdUBcnbfuJbTLTtJEbln-XwXI1JUeUE1HO8EtLpZ2h' target='_blank'>Server Monitors</a></p>";
		}

		$resultSet = executeQuery("show status like 'Threads_connected'");
		if ($row = getNextRow($resultSet)) {
			echo "<p>" . snakeCaseToDescription($row['Variable_name']) . ": " . $row['Value'] . "</p>";
		}

		$count = 0;
		$clients = "";
		$resultSet = executeQuery("select business_name from clients join contacts using (contact_id) where inactive = 0");
		while ($row = getNextRow($resultSet)) {
			$clients .= (empty($clients) ? "" : ";&nbsp;&nbsp; ") . $row['business_name'];
			$count++;
		}
		echo "<div id='client_list'><p>" . $count . " active client" . ($count == 1 ? "" : "s") . ": " . $clients . "</p></div>";

		# Update Management Template, template data, and template text chunks

		$managementTemplateId = getFieldFromId("template_id", "templates", "template_code", "MANAGEMENT");
		$templateContents = getManagementTemplate();
		$javascriptCode = $templateContents['javascript_code'];
		$cssContent = $templateContents['css_content'];
		$content = $templateContents['html_content'];
		if (empty($managementTemplateId)) {
			$resultSet = executeQuery("insert into templates (client_id,template_code,description,css_content,javascript_code,content,include_crud) values (1,?,?,?,?,?,1)",
				"MANAGEMENT", "Management Template", $cssContent, $javascriptCode, $content);
			$managementTemplateId = $resultSet['insert_id'];
			echo "<p>Management Template Created</p>";
		} else {
			$resultSet = executeQuery("update templates set css_content = ?,javascript_code = ?,content = ? where template_id = ?", trim($cssContent), trim($javascriptCode), trim($content), $managementTemplateId);
			if ($resultSet['affected_rows'] > 0) {
				echo "<p>Management Template Updated</p>";
			}
		}
		$templateDataId = getFieldFromId("template_data_id", "template_data", "data_name", "primary_table_name");
		$templateDataUseId = getFieldFromId("template_data_use_id", "template_data_uses", "template_id", $managementTemplateId, "template_data_id = ?", $templateDataId);
		if (empty($templateDataUseId)) {
			$resultSet = executeQuery("insert ignore into template_data_uses (template_data_id,template_id,sequence_number) values (?,?,?)",
				$templateDataId, $managementTemplateId, 1);
		}
		$textChunk = getFieldFromId("content", "template_text_chunks", "template_id", $managementTemplateId, "template_text_chunk_code = 'COLOR_OVERRIDE'");
		if ($textChunk != $templateContents['color_override']) {
			executeQuery("delete from template_text_chunks where template_id = ? and template_text_chunk_code = 'COLOR_OVERRIDE'", $managementTemplateId);
			executeQuery("insert into template_text_chunks (template_text_chunk_code,template_id,description,content) values (?,?,?,?)", "COLOR_OVERRIDE", $managementTemplateId, "Color Override", $templateContents['color_override']);
			echo "<p>Management Template Text Chunks Updated</p>";
		}

		$executeUpdates = (!empty($_GET['execute_updates']) || !empty($_GET['auto']));

		# Get list of database updates

		$databaseUpdates = array();

		$updateClasses = array();
		foreach (get_declared_classes() as $class) {
			if (is_subclass_of($class, 'AbstractDatabaseUpdate')) {
				$updateClasses[] = $class;
			}
		}
		sort($updateClasses);
		foreach ($updateClasses as $class) {
			$updateNumber = str_replace("DatabaseUpdate", "", $class) - 0;
			if ($updateNumber > $databaseVersion) {
				if (class_exists($class)) {
					$thisUpdate = new $class($updateNumber);
					$databaseUpdates[] = $thisUpdate;
				}
			}
		}
		usort($databaseUpdates, array($this, "versionSort"));

		if (count($databaseUpdates) > 0) {
			echo "<h2>List of waiting updates</h2>";
			echo "<div id='waiting_updates'>";
			foreach ($databaseUpdates as $thisUpdate) {
				echo "<p class='waiting-update'>Database Update " . $thisUpdate->getVersion() . ": " . $thisUpdate->iDescription . "</p>";
			}
			echo "</div>";

			$updateErrors = false;
			if ($executeUpdates) {
				$GLOBALS['gDatabaseUpdatesObject'] = $this;
				foreach ($databaseUpdates as $index => $thisUpdate) {
					$success = $thisUpdate->execute();
					if ($success) {
						echo "<p>Finished Update " . $thisUpdate->getVersion() . "</p>";
						unset($databaseUpdates[$index]);
						if (empty($_GET['auto'])) {
							break;
						}
					} else {
						$updateErrors = true;
						break;
					}
				}
			}

			if (!empty($databaseUpdates)) {
				echo "<p><a href='/updatedatabase.php?execute_updates=true'><button>Run ONE update</button></a><a href='/updatedatabase.php?auto=true'><button>Run ALL updates</button></a></p>";
			}
			$databaseVersion = $GLOBALS['gAllPreferences']['DATABASE_VERSION']['system_value'];
			if ($updateErrors || !empty($databaseUpdates)) {
				echo "<h4 class='highlighted-text'>Database is currently at version " . $databaseVersion . "</h4>";
				return true;
			}
			echo "<h4 class='highlighted-text'>Database is fully up to date at version " . $databaseVersion . "</h4>";
			echo "<script>setTimeout(function() { document.location = '/update-database'; },3000);</script>";
		} else {
			echo "<h4 class='highlighted-text'>Database is fully up to date at version " . $databaseVersion . "</h4>";
		}

		$isCoreSystem = ($GLOBALS['gSystemName'] == "CORE" && $GLOBALS['gDevelopmentServer']);
		if (empty($_GET['core_pages'])) {
			if (count($databaseUpdates) == 0) {
				echo "<p><a href='/updatedatabase.php?core_pages=true'><button>" . ($isCoreSystem ? "Export Core Pages" : "Update Core Pages") . "</button></a></p>";
			}
		} else {
			executeQuery("delete from query_log");
			echo "<p class='loading-core-pages'>" . ($isCoreSystem ? "Core Pages being exported" : "Core Pages being updated") . ". Don't close window.</p>";
			?>
			<script>
                function adminMenusFullyLoaded() {
                    setTimeout(function () {
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_core_pages", function (returnArray) {
                            if ("response" in returnArray) {
                                $(".loading-core-pages").remove();
                                $("#_main_content").append(returnArray['response']);
                            }
                        });
                    }, 2000);
                }
			</script>
			<?php
		}
		return true;
	}

	function versionSort($a, $b) {
		return ($a->getVersion() < $b->getVersion()) ? -1 : 1;
	}

	function internalCSS() {
		?>
		<style>
            p {
                font-size: 14px;
            }

            #_main_content {
                margin-bottom: 40px;
            }

            h2 {
                margin-top: 20px;
            }

            h4 {
                margin-bottom: 40px;
            }

            #waiting_updates {
                margin-bottom: 40px;
            }

            #client_list {
                height: 400px;
                overflow: scroll;
            }
		</style>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "update_core_pages":
				$results = Database::updateCorePages();
				ob_start();
				if (is_array($results) && is_array($results['errors'])) {
					foreach ($results['errors'] as $thisError) {
						echo "<p class='red-text highlighted-text'>" . $thisError . "</p>";
					}
				}
				if (is_array($results) && is_array($results['output'])) {
					foreach ($results['output'] as $thisLine) {
						echo "<p class='green-text'>" . $thisLine . "</p>";
					}
				}
				$returnArray['response'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
		}
	}

	/*
	 * Return true if database changes are successfully made and no errors occurred
	 */
	function processDatabaseChanges($parameters) {
		$GLOBALS['gPrimaryDatabase']->startTransaction();
		$results = Database::updateDatabase($parameters);
		if (is_array($results) && array_key_exists("errors", $results) && !empty($results['errors'])) {
			foreach ($results['errors'] as $thisError) {
				echo "<p class='red-text highlighted-text'>" . $thisError . "</p>";
			}
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
		} else {
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
		}
		if (is_array($results) && array_key_exists("output", $results) && !empty($results['output'])) {
			foreach ($results['output'] as $thisLine) {
				echo "<p class='green-text'>" . $thisLine . "</p>";
			}
		}
		return (is_scalar($results) ? $results : empty($results['errors']));
	}

	function testPerformance($name, Closure $closure, $runs = 1000000) {
		$start = microtime(true);
		for (; $runs > 0; $runs--) {
			$closure();
		}
		$end = microtime(true);

		printf("Function call %s took %.5f seconds<br>", $name, $end - $start);
	}
}

$pageObject = new UpdateDatabasePage();
$pageObject->displayPage();
