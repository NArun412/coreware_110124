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

if (gethostname() != "repository.officemadeeasy.com") {
	exit;
}

$validRepositories = array();
$validRepositories['tools'] = array("ip_addresses" => array("3.93.41.12"));
$validRepositories['spb'] = array("ip_addresses" => array("18.232.189.125", "34.194.192.182", "18.220.110.76"));
$validRepositories['beta'] = array("ip_addresses" => array("52.203.138.126"));
$validRepositories['coreware'] = array("ip_addresses" => array("52.0.58.53", "52.206.88.145", "34.239.218.243", "34.204.208.129", "52.22.194.152",
	"100.26.117.142", "34.230.213.210", "52.86.208.134", "18.232.189.125", "34.197.110.64", "52.204.23.184", "35.173.99.217", "18.209.247.213", "34.194.202.90",
	"3.219.209.66", "34.198.56.123", "34.194.192.182", "174.209.21.217", "107.20.16.249", "3.94.140.79", "174.198.131.127", "54.167.200.96", "107.23.157.195", "34.202.38.86",
	"184.73.89.9", "3.225.235.135", "52.203.138.126", "3.80.199.149", "3.91.224.48", "23.22.248.27", "18.224.120.98", "54.159.79.188"));
$validRepositories['sig'] = array("ip_addresses" => array("107.23.157.195", "34.206.125.220", "52.4.88.175", "3.226.94.122", "52.205.232.37", "54.147.18.147", "44.207.28.172"));
$validRepositories['datastats'] = array("ip_addresses" => array("3.86.131.181"));
$validRepositories['dbs'] = array("ip_addresses" => array("71.39.53.202", "71.39.53.206", "50.194.155.131"));
$validRepositories['ehc'] = array("ip_addresses" => array("34.199.208.0", "3.223.117.23"));
$validRepositories['idb'] = array("ip_addresses" => array("209.210.214.26", "209.210.175.30", "209.210.175.26", "34.197.191.136", "34.195.245.40","34.199.208.0","3.223.117.23"));
$validRepositories['dunhams'] = array("ip_addresses" => array("157.56.177.82"));
$validRepositories['davidsons'] = array("ip_addresses" => array("54.162.60.43"));

$_GET['repository'] = strtolower($_GET['repository']);
if (empty($_GET['repository']) || !array_key_exists($_GET['repository'], $validRepositories) || (!in_array($_SERVER['REMOTE_ADDR'], $validRepositories[$_GET['repository']]['ip_addresses']))) {
	echo htmlspecialchars($_GET['repository']) . " NOT FOUND, " . $_SERVER['REMOTE_ADDR'];
	http_response_code(404);
	exit;
}

shell_exec("find /var/www/html/cache -mmin +9 -type f -exec rm -rf {} \;");
sleep(2);
$repository = (array_key_exists("repository", $validRepositories[$_GET['repository']]) ? $validRepositories[$_GET['repository']]['repository'] : $_GET['repository']);
if (file_exists("/var/www/html/cache/" . $repository . ".tgz")) {
	$filename = $repository . ".tgz";
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: ' . filesize("/var/www/html/cache/" . $filename));
	readfile("/var/www/html/cache/" . $filename);
	exit;
}
$filename = trim(shell_exec("/usr/local/bin/exportgit " . $repository));
error_log(date("m/d/Y H:i:s") . " : " . $filename . "\n", 3, "/var/log/exportgit.log");
if ($filename == "ERROR") {
	echo "ERROR";
} else {
	header('Content-Description: File Transfer');
	header('Content-Type: application/octet-stream');
	header('Content-Disposition: attachment; filename="' . $filename . '"');
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: ' . filesize("/var/www/html/cache/" . $filename));
	readfile("/var/www/html/cache/" . $filename);
}
