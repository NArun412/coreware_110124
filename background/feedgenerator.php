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
if ($runEnvironment == "cli") {
	require_once "shared/startup.inc";
} else {
	require_once "../shared/startup.inc";
}

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "feed_generator";
	}

	function process() {
		shell_exec("chmod 777 /var/www/html/feeds");
		$feedNames = array("wikiarmsfeed" => 2, "gundealsfeed" => 2, "ammoseekfeed" => 1, "highcapdealsfeed" => 2, "avantlinkfeed" => 2);
		$clientSet = executeReadQuery("select * from clients where inactive = 0");
		$systemName = strtolower(getPreference("system_name"));
		$feedGenerationCount = 0;
		while ($clientRow = getNextRow($clientSet)) {
			changeClient($clientRow['client_id']);
			$GLOBALS['gFeedProductPrices'] = array();
			foreach ($feedNames as $feedType => $regenerateHours) {
				$extension = (in_array($feedType, ["highcapdealsfeed", "avantlinkfeed"]) ? ".csv" : ".xml");
				$filename = $GLOBALS['gDocumentRoot'] . "/feeds/" . $feedType . "_" . strtolower(str_replace("_", "", $systemName)) . "_" . $clientRow['client_id'] . $extension;
				if (!file_exists($filename)) {
					continue;
				}
				$feedDetailRow = getReadRowFromId("feed_details", "feed_detail_code", $feedType);
				if (empty($feedDetailRow)) {
					$insertSet = executeQuery("insert into feed_details (client_id,feed_detail_code,time_created,last_activity) values (?,?,now(),now())", $GLOBALS['gClientId'], $feedType);
					$feedDetailRow = array("feed_detail_id" => $insertSet['feed_detail_id']);
				} else {
					if ($feedDetailRow['last_activity'] < date("Y-m-d", strtotime("-7 days"))) {
						unlink($filename);
						$this->addResult("Found file, but with no activity, so file was removed: " . $filename);
						continue;
					}
				}
				$cutoffTime = date("Y-m-d H:i:s", strtotime("-" . $regenerateHours . " hours"));
				if ($feedDetailRow['time_created'] < $cutoffTime || filesize($filename) < 1000) {
					$this->addResult("Found file to regenerate: " . $feedType . "_" . strtolower(str_replace("_", "", $systemName)) . "_" . $clientRow['client_id'] . $extension . ": cutoff: " . $cutoffTime . ", created: " . $feedDetailRow['time_created'] . ", size: " . filesize($filename));
					$startTime = getMilliseconds();
					$this->regenerateFeed($feedType, $filename);
					executeQuery("update feed_details set time_created = now() where feed_detail_id = ?", $feedDetailRow['feed_detail_id']);
					$endTime = getMilliseconds();
					$seconds = round(($endTime - $startTime) / 1000);
					$minutes = floor($seconds / 60);
					$seconds = $seconds - ($minutes * 60);
					$this->addResult($feedType . " generated for " . $clientRow['client_code'] . " taking " . $minutes . ":" . ($seconds < 10 ? "0" : "") . $seconds . ", filesize: " . filesize($filename));
					$feedGenerationCount++;
				}
			}
		}
		$this->addResult($feedGenerationCount . " feeds generated");
	}

	private function regenerateFeed($feedType, $filename) {
		switch ($feedType) {
			case "wikiarmsfeed":
				$GLOBALS['gProductIdArray'] = array();
				$GLOBALS['gProductCatalog'] = new ProductCatalog();
				$resultSet = executeReadQuery("select *,(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
					"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'ROUNDS_PER_BOX') and product_id = products.product_id limit 1) as number_of_rounds," .
					"(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
					"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'CALIBER') and product_id = products.product_id limit 1) as caliber," .
					"(select group_concat(product_category_id) from product_category_links where product_id = products.product_id) as product_category_ids," .
					"(select sum(quantity) from product_inventories where product_id = products.product_id and location_id in (select location_id from locations where " .
					"(product_distributor_id is null or primary_location = 1) and inactive = 0 and internal_use_only = 0 and ignore_inventory = 0)) as inventory_quantity," .
					"(select sum(quantity) from order_items where product_id = products.product_id and deleted = 0 and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) as ordered_quantity," .
					"(select sum(quantity) from order_shipment_items where order_item_id in (select order_item_id from order_items where product_id = products.product_id and deleted = 0 and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) and " .
					"exists (select order_shipment_id from order_shipments where order_shipment_id = order_shipment_items.order_shipment_id and secondary_shipment = 0)) as shipped_quantity " .
					"FROM products join product_data using (product_id) where product_id in (select product_id from product_category_links where " .
					"(product_category_id in (select product_category_id from product_category_departments where product_department_id in (select product_department_id from " .
					"product_departments where product_department_code = 'FIREARMS')) or product_category_id in (select product_category_id from product_category_group_links where " .
					"product_category_group_id in (select product_category_group_id from product_category_group_departments  where product_department_id in " .
					"(select product_department_id from product_departments where product_department_code = 'FIREARMS'))))) " .
					"and (product_manufacturer_id is null or product_manufacturer_id not in " .
					"(select product_manufacturer_id from product_manufacturers where cannot_sell = 1)) and inactive = 0 and internal_use_only = 0 and products.client_id = ? and " .
					"product_id not in (select product_id from product_category_links where product_category_id in " .
					"(select product_category_id from product_categories where cannot_sell = 1 or product_category_code in ('INACTIVE','INTERNAL_USE_ONLY','WIKIARMS_EXCLUDE'))) and product_id not in " .
					"(select product_id from product_tag_links where product_tag_id in (select product_tag_id from product_tags where cannot_sell = 1))", $GLOBALS['gClientId']);

				while ($row = getNextRow($resultSet)) {
					if (empty($includeOutOfStock)) {
						if (empty($row['inventory_quantity'])) {
							$row['inventory_quantity'] = 0;
						}
						if (empty($row['ordered_quantity'])) {
							$row['ordered_quantity'] = 0;
						}
						if (empty($row['shipped_quantity'])) {
							$row['shipped_quantity'] = 0;
						}
						$availableQuantity = $row['inventory_quantity'] - max(0, $row['ordered_quantity'] - $row['shipped_quantity']);
						if ($availableQuantity <= 0) {
							continue;
						}
					}
					$row['product_type'] = "firearm";
					$GLOBALS['gProductIdArray'][$row['product_id']] = $row;
				}

				$resultSet = executeReadQuery("select *,(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
					"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'ROUNDS_PER_BOX') and product_id = products.product_id limit 1) as number_of_rounds," .
					"(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
					"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'CALIBER') and product_id = products.product_id limit 1) as caliber," .
					"(select sum(quantity) from product_inventories where product_id = products.product_id and location_id in (select location_id from locations where " .
					"(product_distributor_id is null or primary_location = 1) and inactive = 0 and internal_use_only = 0 and ignore_inventory = 0)) as inventory_quantity," .
					"(select sum(quantity) from order_items where product_id = products.product_id and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) as ordered_quantity," .
					"(select sum(quantity) from order_shipment_items where order_item_id in (select order_item_id from order_items where product_id = products.product_id and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) and " .
					"exists (select order_shipment_id from order_shipments where order_shipment_id = order_shipment_items.order_shipment_id and secondary_shipment = 0)) as shipped_quantity " .
					"from products join product_data using (product_id) where product_id in (select product_id from product_category_links where " .
					"(product_category_id in (select product_category_id from product_category_departments where product_department_id in (select product_department_id from " .
					"product_departments where product_department_code = 'AMMUNITION')) or product_category_id in (select product_category_id from product_category_group_links where " .
					"product_category_group_id in (select product_category_group_id from product_category_group_departments  where product_department_id in " .
					"(select product_department_id from product_departments where product_department_code = 'AMMUNITION'))))) " .
					"and (product_manufacturer_id is null or product_manufacturer_id not in (select product_manufacturer_id from " .
					"product_manufacturers where cannot_sell = 1)) and inactive = 0 and internal_use_only = 0 and products.client_id = ? and product_id not in (select product_id from product_category_links where product_category_id in " .
					"(select product_category_id from product_categories where cannot_sell = 1 or product_category_code in ('INACTIVE','INTERNAL_USE_ONLY'))) and product_id not in " .
					"(select product_id from product_tag_links where product_tag_id in (select product_tag_id from product_tags where cannot_sell = 1))", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (empty($includeOutOfStock)) {
						if (empty($row['inventory_quantity'])) {
							$row['inventory_quantity'] = 0;
						}
						if (empty($row['ordered_quantity'])) {
							$row['ordered_quantity'] = 0;
						}
						if (empty($row['shipped_quantity'])) {
							$row['shipped_quantity'] = 0;
						}
						$availableQuantity = $row['inventory_quantity'] - ($row['ordered_quantity'] - $row['shipped_quantity']);
						if ($availableQuantity <= 0) {
							continue;
						}
					}
					$row['product_type'] = "ammunition";
					$GLOBALS['gProductIdArray'][$row['product_id']] = $row;
				}

				$excludeProductManufacturerIds = array();
				$resultSet = executeReadQuery("select product_manufacturer_id from product_manufacturers where exclude_from_feeds = 1 and client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$excludeProductManufacturerIds[] = $row['product_manufacturer_id'];
				}
				$wikiArmsArray = array();
				$domainName = getPreference("wikiarms_domain_name");
				if (empty($domainName)) {
					$domainName = getDomainName(true);
				}

				foreach ($GLOBALS['gProductIdArray'] as $productRow) {
					if (in_array($productRow['product_manufacturer_id'], $excludeProductManufacturerIds)) {
						continue;
					}
					if (array_key_exists($productRow['product_id'], $GLOBALS['gFeedProductPrices'])) {
						$salePriceInfo = $GLOBALS['gFeedProductPrices'][$productRow['product_id']];
					} else {
						$salePriceInfo = $GLOBALS['gProductCatalog']->getProductSalePrice($productRow['product_id'], array("product_information" => $productRow, "no_cache" => true));
						$GLOBALS['gFeedProductPrices'][$productRow['product_id']] = $salePriceInfo;
					}
					$productRow['sale_price'] = $salePriceInfo['sale_price'];
					$ignoreMap = $salePriceInfo['ignore_map'];
					$mapEnforced = (($salePriceInfo['map_enforced'] || $salePriceInfo['map_policy_code'] == "STRICT" || $salePriceInfo['map_policy_code'] == "STRICT_CODE") && !empty($productRow['manufacturer_advertised_price']));

					if (empty($productRow['sale_price'])) {
						continue;
					}
					if (substr($domainName, 0, 4) != "http") {
						$domainName = "https://" . $domainName;
					}
					$map = "";
					if (!$ignoreMap && !empty($productRow['manufacturer_advertised_price']) && !$mapEnforced && $productRow['manufacturer_advertised_price'] > $productRow['sale_price']) {
						$map = "Add to Cart for best price";
					} elseif ($mapEnforced) {
						$saveMapPolicyCode = $salePriceInfo['map_policy_code'];
						if (array_key_exists($productRow['product_id'] . "-ignore_map", $GLOBALS['gFeedProductPrices'])) {
							$salePriceInfo = $GLOBALS['gFeedProductPrices'][$productRow['product_id'] . "-ignore_map"];
						} else {
							$salePriceInfo = $GLOBALS['gProductCatalog']->getProductSalePrice($productRow['product_id'], array("product_information" => $productRow, "ignore_map" => true, "no_cache" => true));
							$GLOBALS['gFeedProductPrices'][$productRow['product_id'] . "-ignore_map"] = $salePriceInfo;
						}
						$productRow['sale_price'] = $salePriceInfo['sale_price'];
						if ($saveMapPolicyCode == "STRICT" || $saveMapPolicyCode == "STRICT_CODE") {
							$map = "map";
						} elseif ($saveMapPolicyCode == "CALL_PRICE") {
							$map = getFragment("CALL_FOR_PRICE");
							if (empty($callForPriceText)) {
								$map = getLanguageText("Call for Price");
							}
						} else {
							$map = "Add to Cart for best price";
						}
					}
					if (!empty($productRow['upc_code']) && !is_numeric($productRow['upc_code'])) {
						continue;
					}

					$linkUrl = $domainName . "/" . (empty($productRow['link_name']) ? "product-details?id=" . $productRow['product_id'] : "product/" . $productRow['link_name']);
					$wikiArmsArray[] = array(
						"type" => $productRow['product_type'],
						"description" => $productRow['description'],
						"url" => $linkUrl,
						"price" => number_format($productRow['sale_price'], 2, ".", ""),
						"map" => $map,
						"numrounds" => $productRow['number_of_rounds'],
						"caliber" => $productRow['caliber'],
						"UPC" => $productRow['upc_code'],
						"MPN" => $productRow['manufacturer_sku']);
				}

				$content = jsonEncode($wikiArmsArray);
				$fileHandle = fopen($filename, "c");
				if (!empty($fileHandle)) {
					if (flock($fileHandle, LOCK_EX)) {
						ftruncate($fileHandle, 0);
						fwrite($fileHandle, $content);
					}
					fclose($fileHandle);
				}
				break;
			case "gundealsfeed":
				$GLOBALS['gExcludeProductManufacturerIds'] = array();
				$resultSet = executeReadQuery("select product_manufacturer_id from product_manufacturers where exclude_from_feeds = 1 and client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$GLOBALS['gExcludeProductManufacturerIds'][] = $row['product_manufacturer_id'];
				}
				$gunDealsPreferences = Page::getClientPagePreferences("GUNDEALSFEED");

				ob_start();
				echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";

				?>
				<productlist>
					<?php
					$GLOBALS['gProductManufacturerShippingCharges'] = array();
					$resultSet = executeReadQuery("select * from product_manufacturers where shipping_charge is not null and client_id = ?", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$GLOBALS['gProductManufacturerShippingCharges'][$row['product_manufacturer_id']] = $row['shipping_charge'];
					}

					$GLOBALS['gProductShippingCharges'] = array();
					$resultSet = executeReadQuery("select * from product_prices where product_id in (select product_id from products where client_id = ?) and product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'SHIPPING_CHARGE')", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$GLOBALS['gProductShippingCharges'][$row['product_id']] = $row['price'];
					}

					$resultSet = executeReadQuery("select * from product_prices where product_id in (select product_id from products where client_id = ?) and product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'GUN_DEALS_SHIPPING_CHARGE')", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$GLOBALS['gProductShippingCharges'][$row['product_id']] = $row['price'];
					}

					$GLOBALS['gDomainName'] = $gunDealsPreferences['domain_name'];
					if (empty($GLOBALS['gDomainName'])) {
						$GLOBALS['gDomainName'] = getDomainName(true);
					}
					$GLOBALS['gFreeShippingText'] = $gunDealsPreferences['free_shipping_text'] ?: "FREE shipping";

					$optionValues = array("all" => "All Products", "" => "Only In-Stock Products", "local" => "All Products at Local Location(s)", "localstock" => "In Stock at Local Location(s)", "nothing" => "Nothing");
					$GLOBALS['gProductIdArray'] = array();
					$GLOBALS['gProductCatalog'] = new ProductCatalog();

					$departmentSet = executeReadQuery("select * from product_departments where client_id = ? and inactive = 0 and internal_use_only = 0 order by sort_order", $GLOBALS['gClientId']);
					$productCount = 0;
					while ($departmentRow = getNextRow($departmentSet)) {
						$productSet = $gunDealsPreferences['department_products_' . $departmentRow['product_department_id']];
						if (!array_key_exists($productSet, $optionValues)) {
							$productSet = "";
						}
						$shippingCharge = $gunDealsPreferences['department_shipping_' . $departmentRow['product_department_id']];
						if (strlen($shippingCharge) == 0 || !is_numeric($shippingCharge)) {
							if ($departmentRow['product_department_code'] == "FIREARMS") {
								$shippingCharge = 14.95;
							} elseif ($departmentRow['product_department_code'] == "AMMUNITION") {
								$shippingCharge = 10.95;
							} else {
								$shippingCharge = 8.95;
							}
						}
						if ($productSet == "nothing") {
							continue;
						}
						$productType = "accessory";
						switch ($departmentRow['product_department_code']) {
							case "FIREARMS":
								$productType = "firearm";
								break;
							case "AMMUNITION":
								$productType = "ammunition";
								break;
						}

						$query = "select *,(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
							"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'ROUNDS_PER_BOX') and product_id = products.product_id limit 1) as number_of_rounds," .
							"(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
							"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'CALIBER') and product_id = products.product_id limit 1) as caliber," .
							"(select group_concat(product_category_id) from product_category_links where product_id = products.product_id) as product_category_ids ";

						$query .= ",(select sum(quantity) from product_inventories where product_id = products.product_id and location_id in (select location_id from locations where " .
							"(product_distributor_id is null" . (empty($productSet) || $productSet == "all" ? " or primary_location = 1" : "") . ") and inactive = 0 and internal_use_only = 0 and ignore_inventory = 0)) as inventory_quantity," .
							"(select sum(quantity) from order_items where product_id = products.product_id and deleted = 0 and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) as ordered_quantity," .
							"(select sum(quantity) from order_shipment_items where order_item_id in (select order_item_id from order_items where product_id = products.product_id and deleted = 0 and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) and " .
							"exists (select order_shipment_id from order_shipments where order_shipment_id = order_shipment_items.order_shipment_id and secondary_shipment = 0)) as shipped_quantity ";

						$query .= "FROM products join product_data using (product_id) where product_id in (select product_id from product_category_links where " .
							"(product_category_id in (select product_category_id from product_category_departments where product_department_id in (select product_department_id from " .
							"product_departments where product_department_code = '" . $departmentRow['product_department_code'] . "')) or product_category_id in (select product_category_id from product_category_group_links where " .
							"product_category_group_id in (select product_category_group_id from product_category_group_departments  where product_department_id in " .
							"(select product_department_id from product_departments where product_department_code = '" . $departmentRow['product_department_code'] . "'))))) " .
							"and (product_manufacturer_id is null or product_manufacturer_id not in " .
							"(select product_manufacturer_id from product_manufacturers where cannot_sell = 1)) and inactive = 0 and internal_use_only = 0 and products.client_id = ? and " .
							"product_id not in (select product_id from product_category_links where product_category_id in " .
							"(select product_category_id from product_categories where cannot_sell = 1 or product_category_code in ('INACTIVE','INTERNAL_USE_ONLY','GUN_DEALS_EXCLUDE'))) and product_id not in " .
							"(select product_id from product_tag_links where product_tag_id in (select product_tag_id from product_tags where cannot_sell = 1))";

						$resultSet = executeReadQuery($query, $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							if (empty($row['inventory_quantity'])) {
								$row['inventory_quantity'] = 0;
							}
							if (empty($row['ordered_quantity'])) {
								$row['ordered_quantity'] = 0;
							}
							if (empty($row['shipped_quantity'])) {
								$row['shipped_quantity'] = 0;
							}
							$availableQuantity = $row['inventory_quantity'] - max(0, $row['ordered_quantity'] - $row['shipped_quantity']);
							if (empty($productSet) || $productSet == "localstock") {
								if ($availableQuantity <= 0) {
									continue;
								}
							}
							$row['available_quantity'] = $availableQuantity;
							$row['shipping_price'] = $shippingCharge;
							$this->exportGundealsfeedProduct($row, $productType);
							$productCount++;
						}
					}
					?>
				</productlist>
				<?php
				$exportData = ob_get_clean();
				$contentLines = getContentLines($exportData);
				$exportData = implode("\n", $contentLines);
				$fileHandle = fopen($filename, "c");
				if (!empty($fileHandle)) {
					if (flock($fileHandle, LOCK_EX)) {
						ftruncate($fileHandle, 0);
						fwrite($fileHandle, $exportData);
					}
					fclose($fileHandle);
				}
				break;

			case "ammoseekfeed":
				$GLOBALS['gExcludeProductManufacturerIds'] = array();
				$GLOBALS['gProductManufacturers'] = array();
				$resultSet = executeReadQuery("select product_manufacturer_id,description from product_manufacturers where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (!empty($row['exclude_from_feeds'])) {
						$GLOBALS['gExcludeProductManufacturerIds'][] = $row['product_manufacturer_id'];
					}
					$GLOBALS['gProductManufacturers'][$row['product_manufacturer_id']] = $row['description'];
				}

				$ammoSeekPreferences = Page::getClientPagePreferences("AMMOSEEKFEED");

				$validCategories = array("black_powder_balls" => "bullets", "black_powder_bullets" => "bullets", "powders" => "powder", "powder_measures_charges" => "powder",
					"magazines" => "magazines", "reloading_brass" => "brass", "reloading_bullets" => "bullets", "reloading_wads" => "reloading_misc", "reloading_primers" => "primers");
				$excludeCategories = $ammoSeekPreferences['exclude_category_codes'];
				if (!empty($excludeCategories)) {
					foreach (explode(",", $excludeCategories) as $excludeCategory) {
						$validCategories[trim($excludeCategory)] = "exclude";
					}
				}
				$categoryResult = executeReadQuery("select * from product_categories join product_category_group_links using (product_category_id) join product_category_groups using (product_category_group_id)"
					. " where product_category_group_code in ('GUN_PARTS', 'UPPERS_LOWERS') and product_categories.client_id = ? and product_category_groups.client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
				while ($categoryRow = getNextRow($categoryResult)) {
					if (!array_key_exists(strtolower($categoryRow['product_category_code']), $validCategories)) {
						$validCategories[strtolower($categoryRow['product_category_code'])] = "exclude";
					}
				}

				$categoryString = "";
				foreach ($validCategories as $categoryCode => $productType) {
					$categoryString .= (empty($categoryString) ? "" : ",") . "'" . strtoupper($categoryCode) . "'";
				}

				$GLOBALS['gProductCategories'] = array();
				$resultSet = executeReadQuery("select * from product_categories join product_category_links using (product_category_id) where product_category_code in (" . $categoryString . ") and client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['product_category_code'], $GLOBALS['gProductCategories'])) {
						$GLOBALS['gProductCategories'][$row['product_category_code']] = array();
					}
					$GLOBALS['gProductCategories'][$row['product_category_code']][$row['product_id']] = $row['product_id'];
				}

				ob_start();

				$GLOBALS['gDomainName'] = $ammoSeekPreferences['domain_name'];
				if (empty($GLOBALS['gDomainName'])) {
					$GLOBALS['gDomainName'] = getDomainName(true);
				}
				$GLOBALS['gDomainName'] = str_replace("/ammoseekfeed.php", "", $GLOBALS['gDomainName']);
				$GLOBALS['gProductTypeCounts'] = array();
				$GLOBALS['gLogAmmoseekPriceIssues'] = !empty(getPreference("AMMOSEEK_LOG_PRICE_ISSUES"));
				$GLOBALS['gAmmoseekAllowBelowCost'] = !empty(getPreference("AMMOSEEK_ALLOW_BELOW_COST"));
				$GLOBALS['gAmmoseekAllowNonNumericUPC'] = !empty($ammoSeekPreferences["allow_non_numeric_upc"]);

				?>
				<productlist retailer="<?= $GLOBALS['gDomainName'] ?>">
					<?php
					$GLOBALS['gProductManufacturerShippingCharges'] = array();
					$resultSet = executeReadQuery("select * from product_manufacturers where shipping_charge is not null and client_id = ?", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$GLOBALS['gProductManufacturerShippingCharges'][$row['product_manufacturer_id']] = $row['shipping_charge'];
					}

					$GLOBALS['gProductShippingCharges'] = array();
					$resultSet = executeReadQuery("select * from product_prices where product_id in (select product_id from products where client_id = ?) and product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'SHIPPING_CHARGE')", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$GLOBALS['gProductShippingCharges'][$row['product_id']] = $row['price'];
					}

					$resultSet = executeReadQuery("select * from product_prices where product_id in (select product_id from products where client_id = ?) and product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'AMMO_SEEK_SHIPPING_CHARGE')", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$GLOBALS['gProductShippingCharges'][$row['product_id']] = $row['price'];
					}

					$optionValues = array("all" => "All Products", "" => "Only In-Stock Products", "local" => "All Products at Local Location(s)", "localstock" => "In Stock at Local Location(s)", "nothing" => "Nothing");
					$GLOBALS['gProductIdArray'] = array();
					$GLOBALS['gProductCatalog'] = new ProductCatalog();

					$totalProductCount = 0;
					$departmentSet = executeReadQuery("select * from product_departments where client_id = ? and inactive = 0 and internal_use_only = 0", $GLOBALS['gClientId']);
					while ($departmentRow = getNextRow($departmentSet)) {
						$productCount = 0;
						$productSet = $ammoSeekPreferences['department_products_' . $departmentRow['product_department_id']];
						if (!array_key_exists($productSet, $optionValues)) {
							$productSet = "";
						}
						if ($productSet == "nothing") {
							continue;
						}
						$shippingCharge = $ammoSeekPreferences['department_shipping_' . $departmentRow['product_department_id']];
						if (strlen($shippingCharge) == 0 || !is_numeric($shippingCharge)) {
							if ($departmentRow['product_department_code'] == "FIREARMS") {
								$shippingCharge = 14.95;
							} elseif ($departmentRow['product_department_code'] == "AMMUNITION") {
								$shippingCharge = 10.95;
							} else {
								$shippingCharge = 8.95;
							}
						}
						$locationIds = array();
						$resultSet = executeQuery("select location_id from locations where client_id = ? and (product_distributor_id is null" . (empty($productSet) || $productSet == "all" ? " or primary_location = 1" : "") . ") and inactive = 0 and internal_use_only = 0 and ignore_inventory = 0", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							$locationIds[] = $row['location_id'];
						}
						if (empty($locationIds)) {
							continue;
						}
						$productCategories = array();
						$resultSet = executeQuery("select product_category_id from product_categories where client_id = ? and inactive = 0 and internal_use_only = 0 and cannot_sell = 0 and product_category_code not in ('INACTIVE','INTERNAL_USE_ONLY') and " .
							"(product_category_id in (select product_category_id from product_category_departments where product_department_id = " . $departmentRow['product_department_id'] .
							") or product_category_id in (select product_category_id from product_category_group_links where " .
							"product_category_group_id in (select product_category_group_id from product_category_group_departments where product_department_id = " . $departmentRow['product_department_id'] . ")))", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							$productCategories[] = $row['product_category_id'];
						}
						if (empty($productCategories)) {
							continue;
						}

						$query = "select *,(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
							"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'ROUNDS_PER_BOX') and product_id = products.product_id limit 1) as rounds_per_box," .
							"(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
							"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'CARTRIDGES_PER_BOX') and product_id = products.product_id limit 1) as cartridges_per_box," .
							"(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
							"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'TOTAL_ROUNDS') and product_id = products.product_id limit 1) as total_rounds," .
							"(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
							"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'QUANTITY') and product_id = products.product_id limit 1) as quantity_facet," .
							"(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
							"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'CALIBER') and product_id = products.product_id limit 1) as caliber," .
							"(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
							"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'CASING_MATERIAL') and product_id = products.product_id limit 1) as casing ";

						$query .= ",(select sum(quantity) from product_inventories where product_id = products.product_id and location_id in (" . implode(",", $locationIds) . ")) as inventory_quantity," .
							"(select sum(quantity) from order_items where product_id = products.product_id and deleted = 0 and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) as ordered_quantity," .
							"(select sum(quantity) from order_shipment_items where order_item_id in (select order_item_id from order_items where product_id = products.product_id and deleted = 0 and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) and " .
							"exists (select order_shipment_id from order_shipments where order_shipment_id = order_shipment_items.order_shipment_id and secondary_shipment = 0)) as shipped_quantity," .
							"(select group_concat(product_category_id) from product_category_links where product_id = products.product_id) as product_category_ids ";

						$query .= "FROM products join product_data using (product_id) where product_id in (select product_id from product_category_links where " .
							"(product_category_id in (" . implode(",", $productCategories) . "))) " .
							"and (product_manufacturer_id is null or product_manufacturer_id not in " .
							"(select product_manufacturer_id from product_manufacturers where cannot_sell = 1)) and inactive = 0 and internal_use_only = 0 and products.client_id = ? and " .
							"product_id not in (select product_id from product_tag_links where product_tag_id in (select product_tag_id from product_tags where cannot_sell = 1))";
						if (!empty($_GET['test_product_id']) && is_numeric($_GET['test_product_id'])) {
							$query .= " and products.product_id = " . makeNumberParameter($_GET['test_product_id']);
						}

						$resultSet = executeReadQuery($query, $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {

							if (empty($row['inventory_quantity'])) {
								$row['inventory_quantity'] = 0;
							}
							if (empty($row['ordered_quantity'])) {
								$row['ordered_quantity'] = 0;
							}
							if (empty($row['shipped_quantity'])) {
								$row['shipped_quantity'] = 0;
							}
							$availableQuantity = $row['inventory_quantity'] - ($row['ordered_quantity'] - $row['shipped_quantity']);
							if (empty($productSet) || $productSet == "localstock") {
								if ($availableQuantity <= 0) {
									continue;
								}
							}
							$row['available_quantity'] = $availableQuantity;
							$row['shipping_price'] = $shippingCharge;
							$productType = "";
							foreach ($validCategories as $categoryCode => $thisProductType) {
								if (is_array($GLOBALS['gProductCategories']) && array_key_exists(strtoupper($categoryCode), $GLOBALS['gProductCategories']) && array_key_exists($row['product_id'], $GLOBALS['gProductCategories'][strtoupper($categoryCode)])) {
									$productType = $thisProductType;
									break;
								}
							}
							if (empty($productType)) {
								switch ($departmentRow['product_department_code']) {
									case "FIREARMS":
										$productType = "guns";
										break;
									case "AMMUNITION":
										$productType = "ammunition";
										break;
								}
							}
							if (empty($productType) || $productType == "exclude") {
								continue;
							}
							$this->exportAmmoseekProduct($row, $productType);
						}
						$totalProductCount += $productCount;
					}
					?>
				</productlist>
				<?php
				$exportData = ob_get_clean();
				$contentLines = getContentLines($exportData);
				$exportData = implode("\n", $contentLines);
				$fileHandle = fopen($filename, "c");
				if (!empty($fileHandle)) {
					if (flock($fileHandle, LOCK_EX)) {
						ftruncate($fileHandle, 0);
						fwrite($fileHandle, $exportData);
					}
					fclose($fileHandle);
				}
				break;

			case "highcapdealsfeed":
				$excludeProductManufacturerIds = array();
				$resultSet = executeReadQuery("select product_manufacturer_id from product_manufacturers where exclude_from_feeds = 1 and client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$excludeProductManufacturerIds[] = $row['product_manufacturer_id'];
				}
				$highCapDealsPreferences = Page::getClientPagePreferences("HIGHCAPDEALSFEED");

				$headers = array('product_type',
					'description',
					'url',
					'numrounds',
					'caliber',
					'price',
					'availability',
					'shipping_price',
					'upc',
					'mpn',
					'map');

				ob_start();
				echo createCsvRow($headers);

				$productManufacturerShippingCharges = array();
				$resultSet = executeReadQuery("select * from product_manufacturers where shipping_charge is not null and client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$productManufacturerShippingCharges[$row['product_manufacturer_id']] = $row['shipping_charge'];
				}

				$productShippingCharges = array();
				$resultSet = executeReadQuery("select * from product_prices where product_id in (select product_id from products where client_id = ?) and product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'SHIPPING_CHARGE')", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$productShippingCharges[$row['product_id']] = $row['price'];
				}

				$resultSet = executeReadQuery("select * from product_prices where product_id in (select product_id from products where client_id = ?) and product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'HIGHCAP_DEALS_SHIPPING_CHARGE')", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$productShippingCharges[$row['product_id']] = $row['price'];
				}

				$domainName = $highCapDealsPreferences['domain_name'];
				if (empty($domainName)) {
					$domainName = getDomainName();
				} else {
					if (substr($domainName, 0, 4) != "http") {
						$domainName = "https://" . $domainName;
					}
				}
				$freeShippingText = $highCapDealsPreferences['free_shipping_text'] ?: "FREE shipping";

				$optionValues = array("all" => "All Products", "" => "Only In-Stock Products", "local" => "All Products at Local Location(s)", "localstock" => "In Stock at Local Location(s)", "nothing" => "Nothing");
				$productIdArray = array();
				$productCatalog = new ProductCatalog();

				$departmentSet = executeReadQuery("select * from product_departments where client_id = ? and inactive = 0 and internal_use_only = 0 order by sort_order", $GLOBALS['gClientId']);
				while ($departmentRow = getNextRow($departmentSet)) {
					$productSet = $highCapDealsPreferences['department_products_' . $departmentRow['product_department_id']];
					if (!array_key_exists($productSet, $optionValues)) {
						$productSet = "";
					}
					$shippingCharge = $highCapDealsPreferences['department_shipping_' . $departmentRow['product_department_id']];
					if (strlen($shippingCharge) == 0 || !is_numeric($shippingCharge)) {
						if ($departmentRow['product_department_code'] == "FIREARMS") {
							$shippingCharge = 14.95;
						} elseif ($departmentRow['product_department_code'] == "AMMUNITION") {
							$shippingCharge = 10.95;
						} else {
							$shippingCharge = 8.95;
						}
					}
					if ($productSet == "nothing") {
						continue;
					}
					$productType = "accessory";
					switch ($departmentRow['product_department_code']) {
						case "FIREARMS":
							$productType = "firearm";
							break;
						case "AMMUNITION":
							$productType = "ammunition";
							break;
					}

					$query = "select *,(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
						"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'ROUNDS_PER_BOX') and product_id = products.product_id limit 1) as number_of_rounds," .
						"(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
						"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'CALIBER') and product_id = products.product_id limit 1) as caliber," .
						"(select group_concat(product_category_id) from product_category_links where product_id = products.product_id) as product_category_ids ";

					$query .= ",(select sum(quantity) from product_inventories where product_id = products.product_id and location_id in (select location_id from locations where " .
						"(product_distributor_id is null" . (empty($productSet) || $productSet == "all" ? " or primary_location = 1" : "") . ") and inactive = 0 and internal_use_only = 0 and ignore_inventory = 0)) as inventory_quantity," .
						"(select sum(quantity) from order_items where product_id = products.product_id and deleted = 0 and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) as ordered_quantity," .
						"(select sum(quantity) from order_shipment_items where order_item_id in (select order_item_id from order_items where product_id = products.product_id and deleted = 0 and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) and " .
						"exists (select order_shipment_id from order_shipments where order_shipment_id = order_shipment_items.order_shipment_id and secondary_shipment = 0)) as shipped_quantity ";

					$query .= "FROM products join product_data using (product_id) where product_id in (select product_id from product_category_links where " .
						"(product_category_id in (select product_category_id from product_category_departments where product_department_id in (select product_department_id from " .
						"product_departments where product_department_code = '" . $departmentRow['product_department_code'] . "')) or product_category_id in (select product_category_id from product_category_group_links where " .
						"product_category_group_id in (select product_category_group_id from product_category_group_departments  where product_department_id in " .
						"(select product_department_id from product_departments where product_department_code = '" . $departmentRow['product_department_code'] . "'))))) " .
						"and (product_manufacturer_id is null or product_manufacturer_id not in " .
						"(select product_manufacturer_id from product_manufacturers where cannot_sell = 1)) and inactive = 0 and internal_use_only = 0 and products.client_id = ? and " .
						"product_id not in (select product_id from product_category_links where product_category_id in " .
						"(select product_category_id from product_categories where cannot_sell = 1 or product_category_code in ('INACTIVE','INTERNAL_USE_ONLY','HIGHCAP_DEALS_EXCLUDE'))) and product_id not in " .
						"(select product_id from product_tag_links where product_tag_id in (select product_tag_id from product_tags where cannot_sell = 1))";

					$resultSet = executeReadQuery($query, $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						if (empty($row['inventory_quantity'])) {
							$row['inventory_quantity'] = 0;
						}
						if (empty($row['ordered_quantity'])) {
							$row['ordered_quantity'] = 0;
						}
						if (empty($row['shipped_quantity'])) {
							$row['shipped_quantity'] = 0;
						}
						$availableQuantity = $row['inventory_quantity'] - max(0, $row['ordered_quantity'] - $row['shipped_quantity']);
						if (empty($productSet) || $productSet == "localstock") {
							if ($availableQuantity <= 0) {
								continue;
							}
						}
						$row['available_quantity'] = $availableQuantity;
						$thisShippingCharge = $shippingCharge;

						if (array_key_exists($row['product_id'], $productIdArray)) {
							continue;
						}
						if (in_array($row['product_manufacturer_id'], $excludeProductManufacturerIds)) {
							continue;
						}
						if (!is_numeric($row['upc_code'])) {
							continue;
						}
						$productIdArray[$row['product_id']] = $row['product_id'];
						if (array_key_exists($row['product_manufacturer_id'], $productManufacturerShippingCharges)) {
							$thisShippingCharge = $productManufacturerShippingCharges[$row['product_manufacturer_id']];
						}
						if (array_key_exists($row['product_id'], $productShippingCharges)) {
							$thisShippingCharge = $productShippingCharges[$row['product_id']];
						}

						$salePriceInfo = $productCatalog->getProductSalePrice($row['product_id'], array("product_information" => $row));
						$row['sale_price'] = $salePriceInfo['sale_price'];
						$ignoreMap = $salePriceInfo['ignore_map'];
						$mapEnforced = (($salePriceInfo['map_enforced'] || $salePriceInfo['map_policy_code'] == "STRICT" || $salePriceInfo['map_policy_code'] == "STRICT_CODE") && !empty($row['manufacturer_advertised_price']));

						if (empty($row['sale_price'])) {
							continue;
						}
						$linkUrl = (empty($row['link_name']) ? "product-details?id=" . $row['product_id'] : "product/" . $row['link_name']);

						$map = "";
						if (!$ignoreMap && !empty($row['manufacturer_advertised_price']) && !$mapEnforced && $row['manufacturer_advertised_price'] > $row['sale_price']) {
							$map = "Add to Cart for best price";
						} elseif ($mapEnforced) {
							$saveMapPolicyCode = $salePriceInfo['map_policy_code'];
							$salePriceInfo = $productCatalog->getProductSalePrice($row['product_id'], array("product_information" => $row, "ignore_map" => true));
							$row['sale_price'] = $salePriceInfo['sale_price'];
							if ($saveMapPolicyCode == "STRICT" || $saveMapPolicyCode == "STRICT_CODE") {
								$map = "map";
							} elseif ($saveMapPolicyCode == "CALL_PRICE") {
								$map = getFragment("CALL_FOR_PRICE");
								if (empty($callForPriceText)) {
									$map = getLanguageText("Call for Price");
								}
							} else {
								$map = "Add to Cart for best price";
							}
						}

						$exportRow = array($productType,
							$row['description'],
							$domainName . "/" . $linkUrl,
							$row['number_of_rounds'],
							$row['caliber'],
							number_format($row['sale_price'], 2, ".", ""),
							($row['available_quantity'] <= 0 ? "out of stock" : "in stock"),
							($thisShippingCharge <= 0 ? $freeShippingText : $thisShippingCharge),
							$row['upc_code'],
							$row['manufacturer_sku'],
							$map);

						echo createCsvRow($exportRow);
					}
				}

				$exportData = ob_get_clean();
				$fileHandle = fopen($filename, "c");
				if (!empty($fileHandle)) {
					if (flock($fileHandle, LOCK_EX)) {
						ftruncate($fileHandle, 0);
						fwrite($fileHandle, $exportData);
					}
					fclose($fileHandle);
				}
				break;

			case "avantlinkfeed":
				$excludeProductManufacturerIds = array();
				$resultSet = executeReadQuery("select product_manufacturer_id from product_manufacturers where exclude_from_feeds = 1 and client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$excludeProductManufacturerIds[] = $row['product_manufacturer_id'];
				}
				$avantLinkFeedPreferences = Page::getClientPagePreferences("AVANTLINKSETUP");
				if (empty($avantLinkFeedPreferences)) {
					break;
				}

				$headers = array('SKU',
					'Product Name',
					'Long Description',
					'Department',
					'Image URL',
					'Buy Link',
					'Retail Price',
					'Manufacturer ID',
					'Brand Name',
					'Sale Price',
					'Item Based Commission',
					'UPC/GTIN');

				ob_start();
				echo createCsvRow($headers);

				$domainName = $avantLinkFeedPreferences['domain_name'];
				if (empty($domainName)) {
					$domainName = getDomainName();
				} else {
					if (substr($domainName, 0, 4) != "http") {
						$domainName = "https://" . $domainName;
					}
				}
				$optionValues = array("all" => "All Products", "" => "Only In-Stock Products", "local" => "All Products at Local Location(s)", "localstock" => "In Stock at Local Location(s)", "nothing" => "Nothing");
				$productIdArray = array();
				$productCatalog = new ProductCatalog();

				$departmentSet = executeReadQuery("select * from product_departments where client_id = ? and inactive = 0 and internal_use_only = 0 order by sort_order", $GLOBALS['gClientId']);
				while ($departmentRow = getNextRow($departmentSet)) {
					$productSet = $avantLinkFeedPreferences['department_products_' . $departmentRow['product_department_id']];
					if (!array_key_exists($productSet, $optionValues)) {
						$productSet = "";
					}
					if ($productSet == "nothing") {
						continue;
					}

					$query = "select *,(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
						"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'ROUNDS_PER_BOX') and product_id = products.product_id limit 1) as number_of_rounds," .
						"(select facet_value from product_facet_values join product_facet_options using (product_facet_option_id) where " .
						"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'CALIBER') and product_id = products.product_id limit 1) as caliber," .
						"(select group_concat(product_category_id) from product_category_links where product_id = products.product_id) as product_category_ids," .
						"(select description from product_manufacturers where product_manufacturer_id = products.product_manufacturer_id) as product_manufacturer";

					$query .= ",(select sum(quantity) from product_inventories where product_id = products.product_id and location_id in (select location_id from locations where " .
						"(product_distributor_id is null" . (empty($productSet) || $productSet == "all" ? " or primary_location = 1" : "") . ") and inactive = 0 and internal_use_only = 0 and ignore_inventory = 0)) as inventory_quantity," .
						"(select sum(quantity) from order_items where product_id = products.product_id and deleted = 0 and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) as ordered_quantity," .
						"(select sum(quantity) from order_shipment_items where order_item_id in (select order_item_id from order_items where product_id = products.product_id and deleted = 0 and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) and " .
						"exists (select order_shipment_id from order_shipments where order_shipment_id = order_shipment_items.order_shipment_id and secondary_shipment = 0)) as shipped_quantity ";

					$query .= "FROM products join product_data using (product_id) where product_id in (select product_id from product_category_links where " .
						"(product_category_id in (select product_category_id from product_category_departments where product_department_id in (select product_department_id from " .
						"product_departments where product_department_code = '" . $departmentRow['product_department_code'] . "')) or product_category_id in (select product_category_id from product_category_group_links where " .
						"product_category_group_id in (select product_category_group_id from product_category_group_departments  where product_department_id in " .
						"(select product_department_id from product_departments where product_department_code = '" . $departmentRow['product_department_code'] . "'))))) " .
						"and (product_manufacturer_id is null or product_manufacturer_id not in " .
						"(select product_manufacturer_id from product_manufacturers where cannot_sell = 1)) and inactive = 0 and internal_use_only = 0 and products.client_id = ? and " .
						"product_id not in (select product_id from product_category_links where product_category_id in " .
						"(select product_category_id from product_categories where cannot_sell = 1 or product_category_code in ('INACTIVE','INTERNAL_USE_ONLY','AVANT_LINK_FEED_EXCLUDE'))) and product_id not in " .
						"(select product_id from product_tag_links where product_tag_id in (select product_tag_id from product_tags where cannot_sell = 1))";

					$resultSet = executeReadQuery($query, $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {

						$commission = false;
						$productCategoryIds = explode(",", $row['product_category_ids']);
						foreach ($avantLinkFeedPreferences as $fieldName => $fieldData) {
							if (startsWith($fieldName, "product_commissions_product_id-") && $row['product_id'] == $fieldData) {
								$rowNumber = str_replace("product_commissions_product_id-", "", $fieldName);
								$commission = $avantLinkFeedPreferences["product_commissions_percentage-" . $rowNumber];
								break;
							}
							if (startsWith($fieldName, "manufacturer_category_commissions_product_manufacturer_id-") && $row['product_manufacturer_id'] == $fieldData) {
								$rowNumber = str_replace("manufacturer_category_commissions_product_manufacturer_id-", "", $fieldName);
								if (in_array($avantLinkFeedPreferences['manufacturer_category_commissions_product_category_id-' . $rowNumber], $productCategoryIds)) {
									$commission = $avantLinkFeedPreferences["manufacturer_category_commissions_percentage-" . $rowNumber];
								}
								break;
							}
							if (startsWith($fieldName, "manufacturer_commissions_product_manufacturer_id-") && $row['product_manufacturer_id'] == $fieldData) {
								$rowNumber = str_replace("manufacturer_commissions_product_manufacturer_id-", "", $fieldName);
								$commission = $avantLinkFeedPreferences["manufacturer_commissions_percentage-" . $rowNumber];
								break;
							}
							if (startsWith($fieldName, "category_commissions_product_category_id-") && in_array($fieldData, $productCategoryIds)) {
								$rowNumber = str_replace("category_commissions_product_category_id-", "", $fieldName);
								$commission = $avantLinkFeedPreferences["category_commissions_percentage-" . $rowNumber];
								break;
							}
							if (startsWith($fieldName, "department_commissions_product_department_id-") && $fieldData == $departmentRow['product_department_id']) {
								$rowNumber = str_replace("department_commissions_product_department_id-", "", $fieldName);
								$commission = $avantLinkFeedPreferences["department_commissions_percentage-" . $rowNumber];
								break;
							}
						}
						if ($commission === false) {
							$commission = $avantLinkFeedPreferences['base_commission'];
						}
						if (!is_numeric($commission)) {
							$commission = "";
						}

						if (empty($row['inventory_quantity'])) {
							$row['inventory_quantity'] = 0;
						}
						if (empty($row['ordered_quantity'])) {
							$row['ordered_quantity'] = 0;
						}
						if (empty($row['shipped_quantity'])) {
							$row['shipped_quantity'] = 0;
						}
						$availableQuantity = $row['inventory_quantity'] - max(0, $row['ordered_quantity'] - $row['shipped_quantity']);
						if (empty($productSet) || $productSet == "localstock") {
							if ($availableQuantity <= 0) {
								continue;
							}
						}
						$row['available_quantity'] = $availableQuantity;

						if (array_key_exists($row['product_id'], $productIdArray)) {
							continue;
						}
						if (in_array($row['product_manufacturer_id'], $excludeProductManufacturerIds)) {
							continue;
						}
						if (!is_numeric($row['upc_code'])) {
							continue;
						}
						$productIdArray[$row['product_id']] = $row['product_id'];
						$salePriceInfo = $productCatalog->getProductSalePrice($row['product_id'], array("product_information" => $row));
						$row['sale_price'] = $salePriceInfo['sale_price'];
						if (empty($row['sale_price'])) {
							continue;
						}
						$linkUrl = (empty($row['link_name']) ? "product-details?id=" . $row['product_id'] : "product/" . $row['link_name']);
						if (empty($row['image_id'])) {
							$imageUrl = "";
						} else {
							$imageUrl = $domainName . getImageFilename($row['image_id']);
						}

						$exportRow = array($row['product_id'],
							$row['description'],
							$row['detailed_description'],
							$departmentRow['description'],
							$imageUrl,
							$domainName . "/" . $linkUrl,
							$row['list_price'],
							$row['manufacturer_sku'],
							$row['product_manufacturer'],
							number_format($row['sale_price'], 2, ".", ""),
							number_format(($commission / 100), 4, ".", ""),
							$row['upc_code']);

						echo createCsvRow($exportRow);
					}
				}

				$exportData = ob_get_clean();
				$fileHandle = fopen($filename, "c");
				if (!empty($fileHandle)) {
					if (flock($fileHandle, LOCK_EX)) {
						ftruncate($fileHandle, 0);
						fwrite($fileHandle, $exportData);
					}
					fclose($fileHandle);
				}
				break;
		}
	}

	function exportAmmoseekProduct($productRow, $productType) {
		$productId = $productRow['product_id'];
		if (!array_key_exists($productType, $GLOBALS['gProductTypeCounts'])) {
			$GLOBALS['gProductTypeCounts'][$productType] = 0;
		}
		$GLOBALS['gProductTypeCounts'][$productType]++;
		if (array_key_exists($productRow['product_id'], $GLOBALS['gProductIdArray'])) {
			return;
		}
		if (in_array($productRow['product_manufacturer_id'], $GLOBALS['gExcludeProductManufacturerIds'])) {
			return;
		}
		if (!$GLOBALS['gAmmoseekAllowNonNumericUPC'] && !is_numeric($productRow['upc_code'])) {
			return;
		}
		$GLOBALS['gProductIdArray'][$productRow['product_id']] = $productRow['product_id'];

		$resultSet = executeQuery("select * from product_inventories where product_id = ? and quantity > 0 and location_id in " .
			"(select location_id from locations where inactive = 0 and internal_use_only = 0 and ignore_inventory = 0 and (product_distributor_id is null or primary_location = 1)) and " .
			"location_id not in (select location_id from product_prices where location_id is not null and product_id = ? and (start_date is null or start_date <= current_date) and " .
			"(end_date is null or end_date >= current_date))", $productId, $productId);
		$inventoryCosts = array();
		while ($row = getNextRow($resultSet)) {
			$cost = ProductCatalog::getLocationBaseCost($row['product_id'], $row['location_id'], $row, false);
			if (!empty($cost)) {
				$inventoryCosts[] = $cost;
			}
		}
		$lastLogCost = 0;
		foreach ($inventoryCosts as $thisCost) {
			if (!empty($thisCost) && (empty($lastLogCost) || $thisCost < $lastLogCost)) {
				$lastLogCost = $thisCost;
			}
		}
		$lastLogCost = $lastLogCost ?: $productRow['base_cost'];
		if (round($productRow['base_cost'], 2) != $lastLogCost) {
			ProductCatalog::calculateProductCost($productRow['product_id'], "Ammoseek Feed");
			$productRow['base_cost'] = getFieldFromId("base_cost", "products", "product_id", $productRow['product_id']);
		}

		if (array_key_exists($productId, $GLOBALS['gFeedProductPrices'])) {
			$salePriceInfo = $GLOBALS['gFeedProductPrices'][$productRow['product_id']];
		} else {
			$salePriceInfo = $GLOBALS['gProductCatalog']->getProductSalePrice($productRow['product_id'], array("product_information" => $productRow, "no_cache" => true));
			$GLOBALS['gFeedProductPrices'][$productRow['product_id']] = $salePriceInfo;
		}
		$productRow['sale_price'] = $salePriceInfo['sale_price'];
		$ignoreMap = $salePriceInfo['ignore_map'];
		$mapEnforced = (($salePriceInfo['map_enforced'] || $salePriceInfo['map_policy_code'] == "STRICT" || $salePriceInfo['map_policy_code'] == "STRICT_CODE") && !empty($productRow['manufacturer_advertised_price']));

		if (empty($productRow['sale_price'])) {
			return;
		}
		$domainName = $GLOBALS['gDomainName'];
		if (substr($domainName, 0, 4) != "http") {
			$domainName = "https://" . $domainName;
		}
		$domainName = trim($domainName, "/");
		$linkUrl = (empty($productRow['link_name']) ? "product-details?id=" . $productRow['product_id'] : "product/" . $productRow['link_name']);
		$map = "";
		if (!$ignoreMap && !empty($productRow['manufacturer_advertised_price']) && !$mapEnforced && $productRow['manufacturer_advertised_price'] > $productRow['sale_price']) {
			$map = "Add to Cart for best price";
		} elseif ($mapEnforced) {
			$saveMapPolicyCode = $salePriceInfo['map_policy_code'];
			if (array_key_exists($productId . "-ignore_map", $GLOBALS['gFeedProductPrices'])) {
				$salePriceInfo = $GLOBALS['gFeedProductPrices'][$productRow['product_id'] . "-ignore_map"];
			} else {
				$salePriceInfo = $GLOBALS['gProductCatalog']->getProductSalePrice($productRow['product_id'], array("product_information" => $productRow, "ignore_map" => true, "no_cache" => true));
				$GLOBALS['gFeedProductPrices'][$productRow['product_id'] . "-ignore_map"] = $salePriceInfo;
			}
			$productRow['sale_price'] = $salePriceInfo['sale_price'];
			if ($saveMapPolicyCode == "STRICT" || $saveMapPolicyCode == "STRICT_CODE") {
				$map = "Email for Price";
			} elseif ($saveMapPolicyCode == "CALL_PRICE") {
				$map = getFragment("CALL_FOR_PRICE");
				if (empty($callForPriceText)) {
					$map = getLanguageText("Call for Price");
				}
			} else {
				$map = "Add to Cart for best price";
			}
		}
		if ($salePriceInfo['sale_price'] < $productRow['base_cost']) {
			$dontSkip = CustomField::getCustomFieldData($productRow['product_id'], "ALLOW_BELOW_COST", "PRODUCTS");
			$result = ($GLOBALS['gAmmoseekAllowBelowCost'] ? "product listed because of preference: " : "skipping product: ");
			if (empty($dontSkip) && !$GLOBALS['gAmmoseekAllowBelowCost']) {
				return;
			}
		}
		if (empty($productRow['rounds_per_box'])) {
			$productRow['rounds_per_box'] = $productRow['cartridges_per_box'];
		}
		$numberOfRounds = $productRow['total_rounds'] ?: $productRow['rounds_per_box'];
		$itemCount = $productRow['quantity_facet'];
		$condition = CustomField::getCustomFieldData($productRow['product_id'], "AMMOSEEK_CONDITION", "PRODUCTS", true) ?: "new";
		?>
		<product>
			<type><?= $productType ?></type>
			<manufacturer><![CDATA[<?= $GLOBALS['gProductManufacturers'][$productRow['product_manufacturer_id']] ?>]]></manufacturer>
			<caliber><![CDATA[<?= $productRow['caliber'] ?>]]></caliber>
			<?php if (!empty($productRow['casing'])) { ?>
				<casing><![CDATA[<?= $productRow['casing'] ?>]]></casing>
			<?php } ?>
			<title><![CDATA[<?= $productRow['description'] ?>]]></title>
			<url><![CDATA[<?= rtrim($domainName, "/") . "/" . $linkUrl ?>]]></url>
			<upc><?= $productRow['upc_code'] ?></upc>
			<?= (empty($itemCount) ? "" : "<count>" . $itemCount . "</count>") ?>
			<numrounds><![CDATA[<?= htmlText($numberOfRounds) ?>]]></numrounds>
			<price><?= number_format($productRow['sale_price'], 2, ".", "") ?></price>
			<availability><?= ($productRow['available_quantity'] <= 0 ? "out of stock" : "in stock") ?></availability>
			<purchaselimit><?= $productRow['cart_maximum'] ?></purchaselimit>
			<condition><?= $condition ?></condition>
			<map><?= $map ?></map>
		</product>
		<?php
	}

	function exportGundealsfeedProduct($productRow, $productType) {
		if (array_key_exists($productRow['product_id'], $GLOBALS['gProductIdArray'])) {
			return;
		}
		if (in_array($productRow['product_manufacturer_id'], $GLOBALS['gExcludeProductManufacturerIds'])) {
			return;
		}
		if (!is_numeric($productRow['upc_code'])) {
			return;
		}
		$GLOBALS['gProductIdArray'][$productRow['product_id']] = $productRow['product_id'];
		$shippingCharge = $productRow['shipping_price'];
		if (array_key_exists($productRow['product_manufacturer_id'], $GLOBALS['gProductManufacturerShippingCharges'])) {
			$shippingCharge = $GLOBALS['gProductManufacturerShippingCharges'][$productRow['product_manufacturer_id']];
		}
		if (array_key_exists($productRow['product_id'], $GLOBALS['gProductShippingCharges'])) {
			$shippingCharge = $GLOBALS['gProductShippingCharges'][$productRow['product_id']];
		}

		if (array_key_exists($productRow['product_id'], $GLOBALS['gFeedProductPrices'])) {
			$salePriceInfo = $GLOBALS['gFeedProductPrices'][$productRow['product_id']];
		} else {
			$salePriceInfo = $GLOBALS['gProductCatalog']->getProductSalePrice($productRow['product_id'], array("product_information" => $productRow, "no_cache" => true));
			$GLOBALS['gFeedProductPrices'][$productRow['product_id']] = $salePriceInfo;
		}
		$productRow['sale_price'] = $salePriceInfo['sale_price'];
		$ignoreMap = $salePriceInfo['ignore_map'];
		$mapEnforced = (($salePriceInfo['map_enforced'] || $salePriceInfo['map_policy_code'] == "STRICT" || $salePriceInfo['map_policy_code'] == "STRICT_CODE") && !empty($productRow['manufacturer_advertised_price']));

		if (empty($productRow['sale_price'])) {
			return;
		}
		$domainName = $GLOBALS['gDomainName'];
		if (substr($domainName, 0, 4) != "http") {
			$domainName = "https://" . $domainName;
		}
		$linkUrl = (empty($productRow['link_name']) ? "product-details?id=" . $productRow['product_id'] : "product/" . $productRow['link_name']);

		$map = "";
		if (!$ignoreMap && !empty($productRow['manufacturer_advertised_price']) && !$mapEnforced && $productRow['manufacturer_advertised_price'] > $productRow['sale_price']) {
			$map = "Add to Cart for best price";
		} elseif ($mapEnforced) {
			$saveMapPolicyCode = $salePriceInfo['map_policy_code'];
			if (array_key_exists($productRow['product_id'] . "-ignore_map", $GLOBALS['gFeedProductPrices'])) {
				$salePriceInfo = $GLOBALS['gFeedProductPrices'][$productRow['product_id'] . "-ignore_map"];
			} else {
				$salePriceInfo = $GLOBALS['gProductCatalog']->getProductSalePrice($productRow['product_id'], array("product_information" => $productRow, "ignore_map" => true, "no_cache" => true));
				$GLOBALS['gFeedProductPrices'][$productRow['product_id'] . "-ignore_map"] = $salePriceInfo;
			}
			$productRow['sale_price'] = $salePriceInfo['sale_price'];
			if ($saveMapPolicyCode == "STRICT" || $saveMapPolicyCode == "STRICT_CODE") {
				$map = "map";
			} elseif ($saveMapPolicyCode == "CALL_PRICE") {
				$map = getFragment("CALL_FOR_PRICE");
				if (empty($callForPriceText)) {
					$map = getLanguageText("Call for Price");
				}
			} else {
				$map = "Add to Cart for best price";
			}
		}
		?>
		<product Type="<?= $productType ?>">
			<description><?= htmlText($productRow['description']) ?></description>
			<url><?= $domainName . "/" . $linkUrl ?></url>
			<numrounds><?= htmlText($productRow['number_of_rounds']) ?></numrounds>
			<caliber><?= htmlText($productRow['caliber']) ?></caliber>
			<price><?= number_format($productRow['sale_price'], 2, ".", "") ?></price>
			<availability><?= ($productRow['available_quantity'] <= 0 ? "out of stock" : "in stock") ?></availability>
			<shipping_price><?= ($shippingCharge == 0 ? $GLOBALS['gFreeShippingText'] : $shippingCharge) ?></shipping_price>
			<UPC><?= $productRow['upc_code'] ?></UPC>
			<MPN><?= htmlText($productRow['manufacturer_sku']) ?></MPN>
			<map><?= $map ?></map>
		</product>
		<?php
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
