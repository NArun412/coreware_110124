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

$GLOBALS['gPageCode'] = "GETPRODUCTPICKERLIST";
require_once "shared/startup.inc";

$returnArray = array();
if (!empty($_GET['product_id'])) {
	$resultSet = executeQuery("select product_id,description from products where inactive = 0 and client_id = ? " .
		"and product_id = ?",$GLOBALS['gClientId'],$_GET['product_id']);
	$productInfo = array();
	while ($row = getNextRow($resultSet)) {
		$productInfo = array("description"=>$row['description'],"product_id"=>$row['product_id']);
	}
	$returnArray['product_info'] = $productInfo;
	ajaxResponse($returnArray);
}
$fields = array("description","detailed_description");
$whereParameters = array($GLOBALS['gClientId']);
$resultSet = executeQuery("select product_id,description from products where inactive = 0 and client_id = ? " .
	"(description like '% order by date_created desc limit 50",$whereParameters);
$contactList = array();
while ($row = getNextRow($resultSet)) {
	$description = getDisplayName($row['contact_id'],array("include_company"=>true));
	if (!empty($row['address_1'])) {
		if (!empty($description)) {
			$description .= " • ";
		}
		$description .= $row['address_1'];
	}
	if (!empty($row['state'])) {
		if (!empty($row['city'])) {
			$row['city'] .= ", ";
		}
		$row['city'] .= $row['state'];
	}
	if (!empty($row['city'])) {
		if (!empty($description)) {
			$description .= " • ";
		}
		$description .= $row['city'];
	}
	if (!empty($row['email_address'])) {
		if (!empty($description)) {
			$description .= " • ";
		}
		$description .= $row['email_address'];
	}
	$contactList[] = array("description"=>$description,"contact_id"=>$row['contact_id']);
}
$returnArray['contacts'] = $contactList;
echo jsonEncode($returnArray);
