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

$GLOBALS['gPageCode'] = "GETLINKLIST";
require_once "shared/startup.inc";

$linkListArray = array();
if ($GLOBALS['gLoggedIn']) {
	$fileTableColumnId = "";
	$resultSet = executeQuery("select table_column_id from table_columns where table_id = " .
		"(select table_id from tables where table_name = 'files' and database_definition_id = " .
		"(select database_definition_id from database_definitions where database_name = ?)) and column_definition_id = " .
		"(select column_definition_id from column_definitions where column_name = 'file_id')",$GLOBALS['gPrimaryDatabase']->getName());
	if ($row = getNextRow($resultSet)) {
		$fileTableColumnId = $row['table_column_id'];
	}
	$query = "";
	$resultSet = executeQuery("select table_id,column_definition_id from table_columns where table_column_id in " .
		"(select table_column_id from foreign_keys where referenced_table_column_id = ?)",$fileTableColumnId);
	while ($row = getNextRow($resultSet)) {
		if (!empty($query)) {
			$query .= " and ";
		}
		$query .= "file_id not in (select " . getFieldFromId("column_name","column_definitions","column_definition_id",$row['column_definition_id']) .
			" from " . getFieldFromId("table_name","tables","table_id",$row['table_id']) . " where " .
			getFieldFromId("column_name","column_definitions","column_definition_id",$row['column_definition_id']) .
			" is not null)";
	}
	$resultSet = executeQuery("select file_id,description from files where internal_use_only = 0 and client_id = ?" . (empty($query) ? "" : " and " . $query),$GLOBALS['gClientId']);
	while ($row = getNextRow($resultSet)) {
		$linkListArray["File: " . $row['description']] = "/download.php?file_id=" . $row['file_id'];
	}
	$resultSet = executeQuery("select page_id,description,link_name,script_filename,script_arguments from pages where client_id = ? and " .
		"(link_name is not null or script_filename is not null)",$GLOBALS['gClientId']);
	while ($row = getNextRow($resultSet)) {
        if (empty($row['script_filename']) && empty($row['link_name'])) {
            continue;
        }
		$linkListArray["Page: " . $row['description']] = "/" . (empty($row['link_name']) ? $row['script_filename'] : $row['link_name']) . (empty($row['script_arguments']) ? "" : "?" . $row['script_arguments']);
	}
}
ksort($linkListArray);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Link List</title>
<script src="<?= autoVersion("/js/jquery-3.4.0.min.js") ?>"></script>
<script src="<?= autoVersion("/js/jquery-migrate-3.0.1.min.js") ?>"></script>
<script type="text/javascript">
var ckFunctionNum = "";
$(function() {
	var regex = new RegExp('[?&]CKEditorFuncNum=([^&]*)'),
		result = window.location.search.match(regex);

	ckFunctionNum = (result && result.length > 1 ? decodeURIComponent(result[1]) : null);
	$(document).on('click', '.link-choice', function () {
		window.opener.CKEDITOR.tools.callFunction(ckFunctionNum, $(this).data('link_url'));
		window.close();
		return false;
	});
});
</script>
<style type='text/css'>
a { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 16px; font-weight: bold; text-decoration: none; color: rgb(100,100,100); }
a:hover { color: rgb(100,100,240); }
</style>
</head>

<body>
<?php
foreach ($linkListArray as $description => $linkUrl) {
?>
<a href="#" class="link-choice" data-link_url='<?= $linkUrl ?>'><?= $description ?></a><br>
<?php
}
?>
</body>
</html>
