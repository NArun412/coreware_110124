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

$GLOBALS['gPageCode'] = "GOOGLEPRODUCTFEED";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;
ini_set("memory_limit", "4096M");

$GLOBALS['gProductIdArray'] = array();
$GLOBALS['gProductCatalog'] = new ProductCatalog();
$resultSet = executeReadQuery("select *,(select description from product_manufacturers where product_manufacturer_id = products.product_manufacturer_id) brand," .

	"(select sum(quantity) from product_inventories where product_id = products.product_id and location_id in (select location_id from locations where inactive = 0 and internal_use_only = 0 and ignore_inventory = 0)) as inventory_quantity," .
	"(select sum(quantity) from order_items where product_id = products.product_id and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) as ordered_quantity," .
	"(select sum(quantity) from order_shipment_items where order_item_id in (select order_item_id from order_items where product_id = products.product_id and order_id in (select order_id from orders where deleted = 0 and date_completed is null)) and " .
	"exists (select order_shipment_id from order_shipments where order_shipment_id = order_shipment_items.order_shipment_id and secondary_shipment = 0)) as shipped_quantity " .

	"FROM products join product_data using (product_id) where product_id not in (select product_id from product_category_links where " .
	"product_category_id in (select product_category_id from product_category_departments where product_department_id in (select product_department_id from " .
	"product_departments where product_department_code in ('FIREARMS','AMMUNITION')))) and product_id not in (select product_id from product_tag_links where product_tag_id in " .
	"(select product_tag_id from product_tags where product_tag_code in ('FFL_REQUIRED','CLASS_3'))) and " .
	"(product_manufacturer_id is null or product_manufacturer_id not in " .
	"(select product_manufacturer_id from product_manufacturers where cannot_sell = 1)) and inactive = 0 and internal_use_only = 0 and products.client_id = ? and " .
	"product_id not in (select product_id from product_category_links where product_category_id in " .
	"(select product_category_id from product_categories where cannot_sell = 1 or product_category_code in ('INACTIVE','INTERNAL_USE_ONLY','EXCLUDE_GOOGLE'))) and product_id not in " .
	"(select product_id from product_tag_links where product_tag_id in (select product_tag_id from product_tags where cannot_sell = 1))",$GLOBALS['gClientId']);

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
	if ($availableQuantity <= 0) {
		$row['availability'] = "out of stock";
	} else {
		$row['availability'] = "in stock";
	}
	$GLOBALS['gProductIdArray'][] = $row;
}

echo "id" . "\t" .
	"title" . "\t" .
	"description" . "\t" .
	"link" . "\t" .
	"image_link" . "\t" .
	"availability" . "\t" .
	"price" . "\t" .
	"brand" . "\t" .
	"gtin" . "\t" .
	"MPN" . "\t" .
	"identifier_exists" . "\n";
$domainName = getPreference("google_product_data_feed_domain_name");
if (empty($domainName)) {
	$domainName = "https://" . $_SERVER['HTTP_HOST'];
} else {
	if (substr($domainName,0,4) != "http") {
		$domainName = "https://" . $domainName;
	}
}

foreach ($GLOBALS['gProductIdArray'] as $productRow) {
	$salePriceInfo = $GLOBALS['gProductCatalog']->getProductSalePrice($productRow['product_id'],array("product_information"=>$productRow,"no_stored_prices"=>true, "contact_type_id"=>"", "user_type_id"=>""));
	$salePrice = $salePriceInfo['sale_price'];

	if ($productRow['sale_price'] === false) {
		return;
	}
	$linkUrl = $domainName . "/" . (empty($productRow['link_name']) ? "product-details?id=" . $productRow['product_id'] : "product/" . $productRow['link_name']);
	$imageUrl = ProductCatalog::getProductImage($productRow['product_id'],array("product_row"=>$productRow));
	if (!$imageUrl || strpos($imageUrl,"empty.jpg") !== false) {
		$imageUrl = $domainName . "/images/no_image_available.jpg";
	} else if (substr($domainName,0,4) != "http") {
		$imageUrl = $domainName . $imageUrl;
	}
	$gtin = $productRow['upc_code'];
	if (empty($gtin)) {
		$gtin = $productRow['isbn_13'];
	}
	if (empty($gtin)) {
		$gtin = $productRow['isbn'];
	}
	echo $productRow['product_id'] . "\t" .
		str_replace("\t"," ",str_replace("\n"," ",str_replace("\r"," ",$productRow['description']))) . "\t" .
		str_replace("\t"," ",str_replace("\n"," ",str_replace("\r"," ",(empty($productRow['detailed_description']) ? $productRow['description'] : $productRow['detailed_description'])))) . "\t" .
		$linkUrl . "\t" .
		$imageUrl . "\t" .
		$productRow['availability'] . "\t" .
		number_format($salePrice,2,".","") . " USD\t" .
		substr($productRow['brand'],0,70) . "\t" .
		$gtin . "\t" .
		$productRow['manufacturer_sku'] . "\t" .
		(empty($productRow['brand']) && empty($productRow['manufacturer_sku']) ? "no" : "yes") . "\n";
}
