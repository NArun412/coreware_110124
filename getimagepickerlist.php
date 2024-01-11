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

$GLOBALS['gPageCode'] = "GETIMAGEPICKERLIST";
require_once "shared/startup.inc";

$query = "select image_id,description from images where client_id = ?";
$parameters = array($GLOBALS['gClientId']);
$searchFullText = "";
$searchText = "";
$_GET['search_text'] = is_scalar($_GET['search_text']) ? $_GET['search_text'] : "";
$searchParts = explode(" ",$_GET['search_text']);
ProductCatalog::getStopWords();
foreach ($searchParts as $thisPart) {
	if (!empty($thisPart) && !array_key_exists($thisPart,$GLOBALS['gStopWords'])) {
		$searchFullText .= (empty($searchFullText) ? "" : " ") . "+" . $thisPart;
	}
}
if (!empty($_GET['search_text'])) {
	$searchText = "%" . $_GET['search_text'] . "%";
}
if (!empty($searchText)) {
	$tableId = getFieldFromId("table_id","tables","table_name","images");
	$searchFields = array("image_code","description","detailed_description","notes");
	$thisQuery = "";
	foreach ($searchFields as $searchFieldName) {
		$fullText = getFieldFromId("full_text","table_columns","table_id",$tableId,
			"column_definition_id = (select column_definition_id from column_definitions where column_name = ?)",$searchFieldName);
		if ($fullText) {
			$thisQuery .= (empty($thisQuery) ? "" : " or ") . "match(" . $searchFieldName . ") against (? in boolean mode)";
			$parameters[] = $searchFullText;
		} else {
			$thisQuery .= (empty($thisQuery) ? "" : " or ") . $searchFieldName . " like ?";
			$parameters[] = $searchText;
		}
	}
	$query .= " and (" . $thisQuery . ")";
}
$query .= " order by description limit 50";

$resultSet = executeQuery($query,$parameters);
$imageList = array();
while ($row = getNextRow($resultSet)) {
	$imageList[] = array("description"=>$row['description'],"url"=>getImageFilename($row['image_id'],array("use_cdn"=>true)),"image_id"=>$row['image_id']);
}
freeResult($resultSet);

$returnArray = array("images"=>$imageList);
echo jsonEncode($returnArray);
exit;
