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

$GLOBALS['gPageCode'] = "GUNSALEFINDERFEED";
$GLOBALS['gAllowLongRun'] = true;
require_once "shared/startup.inc";

$allowedIpAddresses = array("104.207.138.143","173.199.30.117","137.220.56.199","52.0.58.53");
$resultSet = executeReadQuery("select * from feed_whitelist_ip_addresses where client_id = ?", $GLOBALS['gClientId']);
while ($row = getNextRow($resultSet)) {
	$allowedIpAddresses[] = $row['ip_address'];
}
if (!$GLOBALS['gInternalConnection'] && (isWebCrawler() || !in_array($_SERVER['REMOTE_ADDR'], $allowedIpAddresses))) {
	addProgramLog("Gun Sale Finder IP address rejection: " . $_SERVER['REMOTE_ADDR'] . "\n\nUser Agent: " . $_SERVER['HTTP_USER_AGENT'] );
	exit;
}
$logContent = "Gun Sale Finder feed accessed by " . $_SERVER['REMOTE_ADDR'] . " User Agent: " . $_SERVER['HTTP_USER_AGENT'] ."\n\n";

$systemName = getPreference("system_name");
$apcuKey = "gun_sale_finder_feed_" . strtolower($systemName) . "_" . $GLOBALS['gClientId'] . ".xml";
if (empty($_GET['no_cache'])) {
	$exportData = getCachedData("gun_sale_finder_feed", $apcuKey);
	if (!empty($exportData) && file_exists($GLOBALS['gDocumentRoot'] . "/cache/" . $apcuKey)) {
		echo file_get_contents($GLOBALS['gDocumentRoot'] . "/cache/" . $apcuKey);
		addProgramLog($logContent . "Cached data sent.");
		exit;
	}
}

$GLOBALS['gExcludeProductManufacturerIds'] = array();
$resultSet = executeReadQuery("select product_manufacturer_id from product_manufacturers where exclude_from_feeds = 1 and client_id = ?", $GLOBALS['gClientId']);
while ($row = getNextRow($resultSet)) {
	$GLOBALS['gExcludeProductManufacturerIds'][] = $row['product_manufacturer_id'];
}
$gunSaleFinderPreferences = Page::getClientPagePreferences("GUNSALEFINDERFEED");

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

		$GLOBALS['gProductShippingCharges'] = array();
		$resultSet = executeReadQuery("select * from product_prices where product_id in (select product_id from products where client_id = ?) and product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'GUN_SALE_FINDER_SHIPPING_CHARGE')", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$GLOBALS['gProductShippingCharges'][$row['product_id']] = $row['price'];
		}

		$GLOBALS['gDomainName'] = $gunSaleFinderPreferences['domain_name'];
		$GLOBALS['gFreeShippingText'] = $gunSaleFinderPreferences['free_shipping_text'] ?: "FREE shipping";

		$optionValues = array("all" => "All Products", "" => "Only In-Stock Products", "local" => "All Products at Local Location(s)", "localstock"=>"In Stock at Local Location(s)", "nothing" => "Nothing");
		$GLOBALS['gProductIdArray'] = array();
		$GLOBALS['gProductCatalog'] = new ProductCatalog();

		$departmentSet = executeReadQuery("select * from product_departments where client_id = ? and inactive = 0 and internal_use_only = 0 order by sort_order", $GLOBALS['gClientId']);
		$productCount = 0;
        $skipCount = 0;
		while ($departmentRow = getNextRow($departmentSet)) {
			$productSet = $gunSaleFinderPreferences['department_products_' . $departmentRow['product_department_id']];
			if (!array_key_exists($productSet, $optionValues)) {
				$productSet = "";
			}
			$shippingCharge = $gunSaleFinderPreferences['department_shipping_' . $departmentRow['product_department_id']];
			if (strlen($shippingCharge) == 0 || !is_numeric($shippingCharge)) {
				if ($departmentRow['product_department_code'] == "FIREARMS") {
					$shippingCharge = 14.95;
				} else if ($departmentRow['product_department_code'] == "AMMUNITION") {
					$shippingCharge = 10.95;
				} else {
					$shippingCharge = 8.95;
				}
			}
			if ($productSet == "nothing") {
				$logContent .= "Send NOTHING for Department '" . $departmentRow['description'] . "'\n";
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
				"product_facet_values.product_facet_id in (select product_facet_id from product_facets where product_facet_code = 'CALIBER') and product_id = products.product_id limit 1) as caliber ";

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
				"(select product_category_id from product_categories where cannot_sell = 1 or product_category_code in ('INACTIVE','INTERNAL_USE_ONLY','GUN_SALE_FINDER_EXCLUDE'))) and product_id not in " .
				"(select product_id from product_tag_links where product_tag_id in (select product_tag_id from product_tags where cannot_sell = 1))";

			$resultSet = executeReadQuery($query, $GLOBALS['gClientId']);
			if ($_SERVER['REMOTE_ADDR'] == "52.0.58.53") {
				addDebugLog("Gun Sale Finder: " . $resultSet['query'] . ":" . $resultSet['row_count'],true);
			}
			while ($row = getNextRow($resultSet)) {
                $departmentCount = 0;
				if (empty($row['inventory_quantity'])) {
					$row['inventory_quantity'] = 0;
				}
				if (empty($row['ordered_quantity'])) {
					$row['ordered_quantity'] = 0;
				}
				if (empty($row['shipped_quantity'])) {
					$row['shipped_quantity'] = 0;
				}
				$availableQuantity = $row['inventory_quantity'] - max(0,$row['ordered_quantity'] - $row['shipped_quantity']);
				if (empty($productSet) || $productSet == "localstock") {
					if ($availableQuantity <= 0) {
						continue;
					}
				}
				$row['available_quantity'] = $availableQuantity;
				$row['shipping_price'] = $shippingCharge;
				if (exportProduct($row, $productType)) {
					$productCount++;
					$departmentCount++;
				} else {
                    $skipCount++;
                }
			}
			$logContent .= $departmentCount . " products sent for department '" . $departmentRow['description'] . "', shipping " . $shippingCharge . ", which products: " . $optionValues[$productSet] . "\n";
		}
		?>
	</productlist>
<?php
$exportData = ob_get_clean();
$logContent .= $productCount . " products sent\n";
$logContent .= $skipCount . " products skipped\n";
addProgramLog($logContent);

file_put_contents($GLOBALS['gDocumentRoot'] . "/cache/" . $apcuKey, $exportData);
setCachedData("gun_sale_finder_feed", $apcuKey, "created", 4);
echo $exportData;

function exportProduct($productRow, $productType) {
	if (array_key_exists($productRow['product_id'], $GLOBALS['gProductIdArray'])) {
		return false;
	}
	if (in_array($productRow['product_manufacturer_id'], $GLOBALS['gExcludeProductManufacturerIds'])) {
		return false;
	}
	if (!is_numeric($productRow['upc_code'])) {
		return false;
	}
	$GLOBALS['gProductIdArray'][$productRow['product_id']] = $productRow['product_id'];
	$shippingCharge = $productRow['shipping_price'];
	if (array_key_exists($productRow['product_manufacturer_id'], $GLOBALS['gProductManufacturerShippingCharges'])) {
		$shippingCharge = $GLOBALS['gProductManufacturerShippingCharges'][$productRow['product_manufacturer_id']];
	}
	if (array_key_exists($productRow['product_id'], $GLOBALS['gProductShippingCharges'])) {
		$shippingCharge = $GLOBALS['gProductShippingCharges'][$productRow['product_id']];
	}

	$mapEnforced = false;
	$ignoreMap = false;
	$salePriceInfo = $GLOBALS['gProductCatalog']->getProductSalePrice($productRow['product_id'], array("product_information"=>$productRow));
	$productRow['sale_price'] = $salePriceInfo['sale_price'];
	$ignoreMap = $salePriceInfo['ignore_map'];
	$mapEnforced = (($salePriceInfo['map_enforced'] || $salePriceInfo['map_policy_code'] == "STRICT" || $salePriceInfo['map_policy_code'] == "STRICT_CODE") && !empty($productRow['manufacturer_advertised_price']));

	if (empty($productRow['sale_price'])) {
		return false;
	}
	$domainName = $GLOBALS['gDomainName'];
	if (empty($domainName)) {
		$domainName = "https://" . $_SERVER['HTTP_HOST'];
	} else {
		if (substr($domainName, 0, 4) != "http") {
			$domainName = "https://" . $domainName;
		}
	}
	$linkUrl = (empty($productRow['link_name']) ? "product-details?id=" . $productRow['product_id'] : "product/" . $productRow['link_name']);

	$map = "";
	if (!$ignoreMap && !empty($productRow['manufacturer_advertised_price']) && !$mapEnforced && $productRow['manufacturer_advertised_price'] > $productRow['sale_price']) {
		$map = "Add to Cart for best price";
	} else if ($mapEnforced) {
		$saveMapPolicyCode = $salePriceInfo['map_policy_code'];
		$salePriceInfo = $GLOBALS['gProductCatalog']->getProductSalePrice($productRow['product_id'], array("product_information"=>$productRow, "ignore_map"=>true));
		$productRow['sale_price'] = $salePriceInfo['sale_price'];
		if ($saveMapPolicyCode == "STRICT" || $saveMapPolicyCode == "STRICT_CODE") {
			$map = "map";
		} else if ($saveMapPolicyCode == "CALL_PRICE") {
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
    return true;
}
