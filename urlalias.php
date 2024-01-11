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

if (strpos($_SERVER['REQUEST_URI'],"cache/image") !== false) {
	$imageData = file_get_contents("images/empty.jpg", true);
	header("Content-Type: image/jpeg");
	echo $imageData;
	exit;
}

$GLOBALS['gPageCode'] = "URLALIAS";
$GLOBALS['gEmbeddablePage'] = true;
require_once "shared/startup.inc";

$requestUri = $_SERVER['REQUEST_URI'];
if (substr($requestUri, 0, strlen("/urlalias.php/")) == "/urlalias.php/") {
	$requestUri = substr($requestUri, strlen("/urlalias.php/"));
}
$originalAlias = $requestUri;
$alias = (strpos($requestUri, "?") == false ? $requestUri : substr($requestUri,0,strpos($requestUri,"?")));
$aliasParts = explode("/", trim($alias, "/"));
$alias = "";
$aliasType = "";
if (count($aliasParts) == 1) {
	$alias = $aliasParts[0];
} else if (count($aliasParts) == 2) {
	$aliasType = $aliasParts[0];
	$aliasParts = explode("?", $aliasParts[1]);
	$alias = $aliasParts[0];
} else {
	if (count($aliasParts) == 3 && $aliasParts[0] == "images" && $aliasParts[1] == "products" && $GLOBALS['gClientRow']['client_code'] == "COREWARE_SHOOTING_SPORTS") {
		$filename = $aliasParts[2];
		$parts = explode(".", $filename);
		$filenameParts = explode("-", $parts[0]);
		$smallImage = false;
		if ($filenameParts[0] == "small") {
			array_shift($filenameParts);
			$smallImage = true;
		}
		if (count($filenameParts) == 2 && is_numeric($filenameParts[0]) && is_numeric($filenameParts[1])) {
			ProductCatalog::createProductImageFiles($filenameParts[0]);
			if (file_exists($GLOBALS['gDocumentRoot'] . "/images/products/" . ($smallImage ? "small-" : "") . $filenameParts[0] . "-" . $filenameParts[1] . ".jpg")) {
				$imageContents = file_get_contents($GLOBALS['gDocumentRoot'] . "/images/products/" . ($smallImage ? "small-" : "") . $filenameParts[0] . "-" . $filenameParts[1] . ".jpg");
				if (empty($imageContents)) {
					header("Content-Type: application/octet-stream");
					header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
					header("Cache-Control: post-check=0, pre-check=0", false);
					header("Pragma: no-cache");
					$imageContents = file_get_contents($GLOBALS['gDocumentRoot'] . "/images/no_product_image.jpg");
					echo $imageContents;
				} else {
					echo $imageContents;
				}
			} else {
				header("Content-Type: application/octet-stream");
				header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
				header("Cache-Control: post-check=0, pre-check=0", false);
				header("Pragma: no-cache");
				$imageContents = file_get_contents($GLOBALS['gDocumentRoot'] . "/images/no_product_image.jpg");
				echo $imageContents;
			}
			exit;
		}
	}
	$alias = $requestUri;
}
if (empty($alias)) {
	header("Location: /");
	exit;
}
$linkUrl = "";
if ($_SERVER['REDIRECT_QUERY_STRING']) {
	$_GET = array();
	$_SERVER['QUERY_STRING'] = $_SERVER['REDIRECT_QUERY_STRING'];
	parse_str(preg_replace('/&(\w+)(&|$)/', '&$1=$2', strtr($_SERVER['QUERY_STRING'], ';', '&')), $_GET);
}
$alias = str_replace("?" . $_SERVER['REDIRECT_QUERY_STRING'], "", $alias);
$permanentRedirect = false;

if (!empty($aliasType)) {
	$domainName = $_SERVER['HTTP_HOST'];
	$resultSet = executeQuery("select * from url_alias_types where url_alias_type_code = ? and client_id = ? and domain_name = ?", $aliasType, $GLOBALS['gClientId'], $domainName);
	if (empty($resultSet['row_count'])) {
		$resultSet = executeQuery("select * from url_alias_types where url_alias_type_code = ? and client_id = ? and domain_name is null", $aliasType, $GLOBALS['gClientId']);
	}
	if ($row = getNextRow($resultSet)) {
		$pageId = $row['page_id'];
		$tableName = getFieldFromId("table_name", "tables", "table_id", $row['table_id']);
		if (!empty($tableName)) {
			$primaryTable = new DataTable($tableName);
			$primaryKey = $primaryTable->getPrimaryKey();
			$hasClient = $primaryTable->columnExists("client_id");
			$hasLinkName = $primaryTable->columnExists("link_name");
            $hasInactive = $primaryTable->columnExists("inactive");
			$parameterName = $row['parameter_name'];
			if ($hasLinkName) {
				$query = "select " . $primaryKey . " as primary_id from " . $primaryTable->getName() . " where link_name = ?" . ($hasInactive ? " and inactive = 0" : "") .
					($hasClient ? " and client_id = " . $GLOBALS['gClientId'] . " order by client_id desc" : "");
				$resultSet = executeQuery($query, $alias);
				if ($row = getNextRow($resultSet)) {
					$_GET[$parameterName] = $row['primary_id'];
					loadPage($pageId);
				}
			}
		}
	} else {
		$resultSet = executeQuery("select * from url_alias_types where url_alias_type_code = ? and client_id = ? and domain_name is null", $aliasType, $GLOBALS['gDefaultClientId']);
		if ($row = getNextRow($resultSet)) {
			$pageId = $row['page_id'];
			$tableName = getFieldFromId("table_name", "tables", "table_id", $row['table_id']);
			if (!empty($tableName)) {
				$primaryTable = new DataTable($tableName);
				$primaryKey = $primaryTable->getPrimaryKey();
				$hasClient = $primaryTable->columnExists("client_id");
				$hasLinkName = $primaryTable->columnExists("link_name");
                $hasInactive = $primaryTable->columnExists("inactive");
				$parameterName = $row['parameter_name'];
				if ($hasLinkName) {
					$query = "select " . $primaryKey . " as primary_id from " . $primaryTable->getName() . " where link_name = ?" . ($hasInactive ? " and inactive = 0" : "") .
						($hasClient ? " and client_id = " . $GLOBALS['gClientId'] . " order by client_id desc" : "");
					$resultSet = executeQuery($query, $alias);
					if ($row = getNextRow($resultSet)) {
						$_GET[$parameterName] = $row['primary_id'];
						loadPage($pageId);
					}
				}
			}
		}
	}
}

if ($GLOBALS['gDevelopmentServer']) {
	$resultSet = executeQuery("select * from url_alias where (link_name = ? or link_name = ? or link_name = ?) and (client_id = ? or client_id = ?) " .
		"order by domain_name desc,client_id desc",
		$alias, $originalAlias, trim($originalAlias,"/"), $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
} else {
	$domainName = $_SERVER['HTTP_HOST'];
	if (substr($domainName, 0, 4) == "www.") {
		$domainName = substr($domainName, 4);
	}
	$parameters = array($alias, trim($alias, "/"), "/" . trim($alias, "/") . "/", "/" . trim($alias, "/"),
		$originalAlias, trim($originalAlias, "/"), "/" . trim($originalAlias, "/") . "/", "/" . trim($originalAlias, "/"),
		$GLOBALS['gClientId'], $GLOBALS['gDefaultClientId'], $domainName, "www." . $domainName);
	$resultSet = executeQuery("select * from url_alias where link_name in (?,?,?,?,?,?,?,?) and (client_id = ? or client_id = ?) and " .
		"(domain_name is null or domain_name = ? or domain_name = ?) order by domain_name desc,client_id desc",$parameters);
}
if ($row = getNextRow($resultSet)) {
	$linkName = trim($row['link_name'], "/");
	$linkUrl = trim($row['link_url'], "/");
	if ($linkUrl == $linkName) {
		$row = false;
	}
}
if ($row) {
	$permanentRedirect = ($row['permanent_redirect'] == 1);
	$linkUrls = array();
	$linkUrls[] = $row['link_url'];
	$urlSet = executeQuery("select * from url_alias_links where url_alias_id = ? order by url_alias_link_id", $row['url_alias_id']);
	while ($urlRow = getNextRow($urlSet)) {
		$linkUrls[] = $urlRow['link_url'];
	}
	$count = count($linkUrls);
	if ($count > 1) {
		$permanentRedirect = false;
	}
	$linkUrl = $linkUrls[($row['use_count'] % $count)];
	if (strpos($linkUrl, "?") === false && !empty($_SERVER['REDIRECT_QUERY_STRING'])) {
		$linkUrl .= "?" . $_SERVER['REDIRECT_QUERY_STRING'];
	}
	executeQuery("update url_alias set use_count = use_count + 1,date_last_used = now() where url_alias_id = ?", $row['url_alias_id']);
} else {
	$alias = ltrim($alias,"/");
	if ($GLOBALS['gDevelopmentServer']) {
		$resultSet = executeQuery("select page_id,template_id from pages where (client_id = ? or client_id = ?) and (link_name = ? or page_id in (select page_id from page_aliases where link_name = ?)) and " .
			"inactive = 0 and (publish_start_date is null or (publish_start_date is not null and current_date >= publish_start_date)) and (publish_end_date is null or (publish_end_date is not null and current_date <= publish_end_date)) " .
			"order by domain_name desc,client_id desc,page_id", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId'], $alias, $alias);
	} else {
		$domainName = $_SERVER['HTTP_HOST'];
		$resultSet = executeQuery("select page_id,template_id from pages where (client_id = ? or client_id = ?) and (link_name = ? or page_id in (select page_id from page_aliases where link_name = ?)) and " .
			"(domain_name is null or domain_name = ?) and inactive = 0 and (publish_start_date is null or (publish_start_date is not null and current_date >= publish_start_date)) and (publish_end_date is null or (publish_end_date is not null and current_date <= publish_end_date)) " .
			"order by domain_name desc,client_id desc,page_id", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId'], $alias, $alias, $domainName);
	}
	if ($row = getNextRow($resultSet)) {
		$GLOBALS['gUrlAlias'] = $alias;

		$templateCode = getFieldFromId("template_code", "templates", "template_id", $row['template_id'], "client_id is not null");
		if ($templateCode != "EMBED") {
			if (!array_key_exists("gXFrameOptions",$GLOBALS)) {
				$GLOBALS['gXFrameOptions'] = "SAMEORIGIN";
				header('X-Frame-Options: ' . $GLOBALS['gXFrameOptions']);
			}
		}
		loadPage($row['page_id']);
	} else {
		if ($GLOBALS['gDevelopmentServer']) {
			$resultSet = executeQuery("select * from url_alias where ? like link_name and " .
				"(client_id = ? or client_id = ?) order by client_id desc", $alias, $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
		} else {
			$domainName = $_SERVER['HTTP_HOST'];
			$resultSet = executeQuery("select * from url_alias where ? like link_name and (domain_name is null or domain_name = ?) and " .
				"(client_id = ? or client_id = ?) order by client_id desc", $alias, $domainName, $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
		}
		if ($row = getNextRow($resultSet)) {
			$permanentRedirect = ($row['permanent_redirect'] == 1);
			$linkUrls = array();
			$linkUrls[] = $row['link_url'];
			$urlSet = executeQuery("select * from url_alias_links where url_alias_id = ? order by url_alias_link_id", $row['url_alias_id']);
			while ($urlRow = getNextRow($urlSet)) {
				$linkUrls[] = $urlRow['link_url'];
			}
			$count = count($linkUrls);
			if ($count > 1) {
				$permanentRedirect = false;
			}
			$linkUrl = $linkUrls[($row['use_count'] % $count)];
			executeQuery("update url_alias set use_count = use_count + 1,date_last_used = now() where url_alias_id = ?", $row['url_alias_id']);
		}
	}
}
if (!empty($linkUrl)) {
	if (substr($linkUrl,0,1) != "/" && substr($linkUrl,0,4) != "http") {
		$linkUrl = "/" . $linkUrl;
	}
	if ($permanentRedirect) {
		header("HTTP/1.1 301 Moved Permanently");
	}
	header("Location: $linkUrl");
	exit;
}
if (!$GLOBALS['gLoggedIn'] && strpos($requestUri, "/cache/") === false && strpos($requestUri, "favicon") === false && !$GLOBALS['gWhiteListed']) {
	executeQuery("insert into ip_address_errors (ip_address,link_url,log_time) values (?,?,now())", $_SERVER['REMOTE_ADDR'], $requestUri);
	$resultSet = executeQuery("select count(*) from ip_address_errors where ip_address = ? and log_time > (now() - interval 5 minute)", $_SERVER['REMOTE_ADDR']);
	if ($row = getNextRow($resultSet)) {
		if ($row['count(*)'] > 200) {
			blacklistIpAddress($_SERVER['REMOTE_ADDR'], "Too many repeated 404 errors");
			sleep(2);
			header('HTTP/1.1 503 Service Temporarily Unavailable');
			header('Status: 503 Service Temporarily Unavailable');
			header('Retry-After: 120');
			exit;
		}
	}
}

if (getPreference("log_404_errors") && !empty($alias)) {
	$notFoundLogId = getFieldFromId("not_found_log_id", "not_found_log", "domain_name", $_SERVER['REMOTE_ADDR'], "link_url = ?", $alias);
	if (empty($notFoundLogId)) {
		executeQuery("insert into not_found_log (client_id,domain_name,link_url,time_submitted) values (?,?,?,current_time)", $GLOBALS['gClientId'], $_SERVER['REMOTE_ADDR'], $alias);
	}
}

$pageId = getFieldFromId("page_id", "pages", "link_name", "search", "inactive = 0 and internal_use_only = 0");
if (empty($pageId)) {
	$content = getFragment("PAGE_NOT_FOUND");
	if (!empty($content)) {
		header("HTTP/1.0 404 Not Found");
		echo $content;
		exit;
	}
	$pageId = false;
	if ($GLOBALS['gUserRow']['administrator_flag']) {
		$pageId = getFieldFromId("page_id", "pages", "page_code", "ADMIN_PAGE_NOT_FOUND");
	}
	if (empty($pageId)) {
		$pageId = getFieldFromId("page_id", "pages", "page_code", "PAGE_NOT_FOUND");
		if (empty($pageId)) {
		    $pageId = getFieldFromId("page_id","pages","link_name","page-not-found");
        }
	}
	if (!empty($pageId)) {
		header("HTTP/1.0 404 Not Found");
		loadPage($pageId);
		exit;
	}
	$page = "/";
} else if ($alias != "search" && !$GLOBALS['gUserRow']['administrator_flag']) {
	$page = "/search?search_text=" . $alias;
} else {
	$page = "/";
}
header("HTTP/1.0 404 Not Found");
header("Location: $page");
