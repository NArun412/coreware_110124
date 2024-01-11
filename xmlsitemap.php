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

$GLOBALS['gPageCode'] = "SITEMAP";
require_once "shared/startup.inc";

header("Content-Type: application/xml; charset=utf-8");

$bareDomainName = getDomainName(true, true, true);
$domainName = getDomainName(true, false, true);
$originalUrlNumber = str_replace("/xmlsitemap", "", str_replace(".xml", "", str_replace(".php", "", $_SERVER['REDIRECT_URL'])));
if (empty($originalUrlNumber) || !is_numeric($originalUrlNumber)) {
	$originalUrlNumber = 0;
}
$productCount = getCachedData("site_product_count", $_SERVER['HTTP_HOST'], true);
if (empty($productCount)) {
	$productCount = 100;
}

$apcuKey = "xmlsitemap" . "." . $originalUrlNumber . "." . $GLOBALS['gClientId'] . "." . getCrcValue($bareDomainName, true) . ".inc";
$xmlSitemap = getCachedData("xml_sitemap_content", $apcuKey);
if (!empty($xmlSitemap) && file_exists($_SERVER['DOCUMENT_ROOT'] . "/cache/" . $apcuKey)) {
	echo file_get_contents($GLOBALS['gDocumentRoot'] . "/cache/" . $apcuKey);
	exit;
}

ob_start();
echo '<?xml version="1.0" encoding="UTF-8" ?>';
echo '<urlset xmlns="https://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xsi="https://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="https://www.sitemaps.org/schemas/sitemap/0.9 https://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">';

$noSiteMap = getPreference("NO_SITEMAP");

if (empty($noSiteMap)) {
	$domainNameRow = array();
	$resultSet = executeReadQuery("select * from domain_names where domain_name = ? and inactive = 0", $domainName);
	if (!$domainNameRow = getNextRow($resultSet)) {
		$domainNameRow = array();
		if (substr($domainName, 0, 4) == "www.") {
			$resultSet = executeReadQuery("select * from domain_names where domain_name = ? and include_www = 1 and inactive = 0", substr($domainName, 4));
			if (!$domainNameRow = getNextRow($resultSet)) {
				$domainNameRow = array();
			}
		}
	}

	$domainNameId = "";
	$alternateDomainName = $domainName;
	if (substr($domainName, 0, 4) == "www.") {
		$alternateDomainName = substr($domainName, 4);
	} else {
		$alternateDomainName = "www." . $domainName;
	}
	$resultSet = executeReadQuery("select * from domain_names where domain_name = ? and forward_domain_name is null and inactive = 0", $domainName);
	if (!$row = getNextRow($resultSet)) {
		$resultSet = executeReadQuery("select * from domain_names where domain_name = ? and forward_domain_name is null and inactive = 0", $alternateDomainName);
		if (!$row = getNextRow($resultSet)) {
			$row = array();
		}
	}

	$domainNameId = $row['domain_name_id'];
	$pageCount = 0;
	if (!empty($domainNameId)) {
		$resultSet = executeReadQuery("select count(*) from domain_name_pages where domain_name_id = ?", $domainNameId);
		if ($row = getNextRow($resultSet)) {
			$pageCount = $row['count(*)'];
		}
	}
	$foundProductPage = false;

# Pages

	$domainPageIds = array();
	$parameterRequiredPages = array("product-details", "reset-password", "product-review");
	if (empty($pageCount)) {
		$resultSet = executeReadQuery("select page_id,link_name,script_filename,date_created as last_modified from pages where client_id = ? and exclude_sitemap = 0 and " .
			"inactive = 0 and (publish_start_date is null or (publish_start_date is not null and current_date >= publish_start_date)) and (publish_end_date is null or (publish_end_date is not null and current_date <= publish_end_date)) and " .
			"internal_use_only = 0 and link_name is not null and page_id in (select page_id from page_access " .
			"where public_access = 1)", $GLOBALS['gClientId']);
	} else {
		$resultSet = executeReadQuery("select page_id,link_name,script_filename,date_created as last_modified from pages where page_id in (select page_id from domain_name_pages where domain_name_id = ?)", $domainNameId);
	}
	while ($row = getNextRow($resultSet)) {
		if (empty($row['script_filename']) && empty($row['link_name'])) {
			continue;
		}
		$lastChanged = getReadFieldFromId("time_changed", "change_log", "table_name", "pages", "primary_identifier = ?", $row['page_id']);
		if (!empty($lastChanged)) {
			$row['last_modified'] = $lastChanged;
		}
		if ($row['script_filename'] == "retailstore/productdetails.php") {
			$foundProductPage = true;
		}
		$domainPageIds[$row['page_id']] = $row;
	}

	if (empty($originalUrlNumber)) {
		foreach ($domainPageIds as $domainPageId => $row) {
			if (in_array($row['link_name'], $parameterRequiredPages)) {
				continue;
			}
			?>
            <url>
                <loc>https://<?= $domainName ?>/<?= $row['link_name'] ?></loc>
            </url>
			<?php
		}

		$productDetailAliasFound = false;

		$pageAliases = array();
		$resultSet = executeReadQuery("select * from url_alias_types join tables using (table_id) where exclude_sitemap = 0 and table_name <> 'products' and table_name <> 'product_departments' and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (array_key_exists($row['page_id'], $domainPageIds)) {
				$pageAliases[] = $row;
			}
		}
		foreach ($pageAliases as $urlAliasType) {
			try {
				$dataTable = new DataTable($urlAliasType['table_name']);
				if (!$dataTable->columnExists("client_id")) {
					continue;
				}
			} catch (Exception $e) {
				continue;
			}
			$resultSet = executeReadQuery("select * from " . $urlAliasType['table_name'] . " where link_name is not null and client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (array_key_exists("public_access", $row) && empty($row['public_access'])) {
					continue;
				}
				if (array_key_exists("inactive", $row) && !empty($row['inactive'])) {
					continue;
				}
				if (array_key_exists("internal_use_only", $row) && !empty($row['internal_use_only'])) {
					continue;
				}
				if (array_key_exists("cannot_sell", $row) && !empty($row['cannot_sell'])) {
					continue;
				}
				$skipRecord = false;
				switch ($urlAliasType) {
					case "product_categories":
						$resultSet = executeQuery("select count(*) from product_category_links where product_category_id = ? and product_id in (select product_id from product_inventories where quantity > 0 and " .
							"location_id in (select location_id from locations where inactive = 0 and internal_use_only = 0 and (product_distributor_id is null or primary_location = 1)))",
							$row['product_category_id']);
						if ($row = getNextRow($resultSet)) {
							if ($row['count(*)'] == 0) {
								$skipRecord = true;
							}
						}
						break;
					case "product_manufacturers":
						$resultSet = executeQuery("select count(*) from products where product_manufacturer_id = ? and product_id in (select product_id from product_inventories where quantity > 0 and " .
							"location_id in (select location_id from locations where inactive = 0 and internal_use_only = 0 and (product_distributor_id is null or primary_location = 1)))",
							$row['product_manufacturer_id']);
						if ($row = getNextRow($resultSet)) {
							if ($row['count(*)'] == 0) {
								$skipRecord = true;
							}
						}
						break;
				}
				if ($skipRecord) {
					continue;
				}
				?>
                <url>
                    <loc>https://<?= $domainName ?>/<?= $urlAliasType['url_alias_type_code'] ?>/<?= $row['link_name'] ?></loc>
                </url>
				<?php
			}
		}
	}

	if (empty($pageCount)) {
		$pageAlias = false;
		$resultSet = executeReadQuery("select * from url_alias_types join tables using (table_id) where exclude_sitemap = 0 and table_name = 'products' and client_id = ?", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			if (array_key_exists($row['page_id'], $domainPageIds)) {
				$pageAlias = $row;
			}
		}
		if ($pageAlias) {
			if (empty($originalUrlNumber) || !is_numeric($originalUrlNumber)) {
				$offset = 0;
				$limit = 10000;
			} else {
				$offset = 10000 + (($originalUrlNumber - 1) * 20000);
				$limit = 20000;
			}
			$productCatalog = new ProductCatalog();
			$productCatalog->setSelectLimit($limit);
			$productCatalog->setOffset($offset);
			$productCatalog->showOutOfStock(true);
			$productArray = $productCatalog->getProducts();

			foreach ($productArray as $row) {
				if (empty($row['link_name'])) {
					continue;
				}
				?>
                <url>
                    <loc>https://<?= $domainName ?>/<?= $pageAlias['url_alias_type_code'] ?>/<?= $row['link_name'] ?></loc>
                </url>
				<?php
			}
		}
	}

	if (empty($originalUrlNumber)) {
		if (file_exists($GLOBALS['gDocumentRoot'] . "/xmlsitemap.inc")) {
			include_once "xmlsitemap.inc";
		}
	}
}
?>
    </urlset>
<?php
$xmlSitemap = ob_get_clean();
file_put_contents($GLOBALS['gDocumentRoot'] . "/cache/" . $apcuKey, $xmlSitemap);
setCachedData("xml_sitemap_content", $apcuKey, "created", 48);
echo $xmlSitemap;
