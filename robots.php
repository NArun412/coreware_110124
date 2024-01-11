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

$GLOBALS['gPageCode'] = "ROBOTS";
$GLOBALS['gDocumentRoot'] = $_SERVER['DOCUMENT_ROOT'];
if (empty($GLOBALS['gDocumentRoot'])) {
	$GLOBALS['gDocumentRoot'] = str_replace("/robots.php", "", __FILE__);
}
if (substr($GLOBALS['gDocumentRoot'], -1) == "/" || substr($GLOBALS['gDocumentRoot'], -1) == "\\") {
	$GLOBALS['gDocumentRoot'] = substr($GLOBALS['gDocumentRoot'], 0, -1);
}
$GLOBALS['gCommandLine'] = (php_sapi_name() == "cli");
$GLOBALS['gApcuEnabled'] = !$GLOBALS['gCommandLine'] && ((extension_loaded('apc') && ini_get('apc.enabled')) || (extension_loaded('apcu') && ini_get('apc.enabled')));

header("Content-type: text/plain; charset=utf-8");
$serverName = gethostname();
if ($serverName != "manage.coreware.com") {
	$domainNameParts = explode(".", $_SERVER['HTTP_HOST']);
	$noRobotsDomainNames = array();
	if (file_exists($GLOBALS['gDocumentRoot'] . "/norobotsdomains.txt")) {
		$openFile = fopen($GLOBALS['gDocumentRoot'] . "/norobotsdomains.txt", "r");
		while ($line = fgets($openFile)) {
			$noRobotsDomainNames[] = trim($line);
		}
		fclose($openFile);
	}
	if (in_array($_SERVER['HTTP_HOST'], $noRobotsDomainNames) || ($domainNameParts[0] != "images" && strpos($domainNameParts[0],"cdn") === false
					&& count($domainNameParts) == 3 && $domainNameParts[1] == "coreware" && $domainNameParts[2] == "com")) {
		?>
		User-agent: *
		Disallow: /
		<?php
		exit;
	}
}

$noSitemap = false;
$apcuKey = "ALL|no_sitemap|" . str_replace("www.", "", $_SERVER['HTTP_HOST']);
if ($GLOBALS['gApcuEnabled'] && apcu_exists($apcuKey)) {
	$noSitemap = apcu_fetch($apcuKey);
}

if (!$noSitemap) {
	$productCount = 0;
	$domainName = $_SERVER['HTTP_HOST'];
	$apcuKey = "ALL|redirect_domain_name|" . $domainName;
	if ($GLOBALS['gApcuEnabled'] && apcu_exists($apcuKey)) {
		$useDomainName = apcu_fetch($apcuKey);
		if (!empty($useDomainName)) {
			$domainName = $useDomainName;
		}
	}

	$apcuKey = "ALL|site_product_count|" . str_replace("www.", "", $_SERVER['HTTP_HOST']);
	if ($GLOBALS['gApcuEnabled'] && !empty($apcuKey)) {
		if (apcu_exists($apcuKey)) {
			$cachedProductCount = apcu_fetch($apcuKey);
			if (is_numeric($cachedProductCount)) {
				$productCount = $cachedProductCount;
			}
		}
	}
	if (empty($productCount)) {
		$apcuKey = "ALL|site_product_count|" . $_SERVER['HTTP_HOST'];
		if ($GLOBALS['gApcuEnabled'] && !empty($apcuKey)) {
			if (apcu_exists($apcuKey)) {
				$cachedProductCount = apcu_fetch($apcuKey);
				if (is_numeric($cachedProductCount)) {
					$productCount = $cachedProductCount;
				}
			}
		}
	}

	if ($productCount > 10000) {
		$fileCount = floor(($productCount - 10000) / 20000) + 1;
	} else {
		$fileCount = 0;
	}

	?>
	<?php for ($x = 0; $x <= $fileCount; $x++) { ?>
		Sitemap: https://<?= $domainName ?>/xmlsitemap<?= ($x == 0 ? "" : $x) ?>.xml
	<?php } ?>

	<?php
}

if (file_exists($GLOBALS['gDocumentRoot'] . "/robots.txt")) {
	echo file_get_contents($GLOBALS['gDocumentRoot'] . "/robots.txt");
} else {
	?>
	User-agent: *
	Disallow: /retail-store-controller

	User-agent: Googlebot
	Allow: /

	User-agent: bingbot
	Allow: /
	crawl-delay: 5

    User-agent: GPTBot
    Disallow: /

    User-agent: AhrefsBot
    Allow: /
    crawl-delay: 5

    <?php
}
