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

$GLOBALS['gPageCode'] = "SELECTIMAGES";
$GLOBALS['gProxyPageCode'] = "BUILDCONTENT";
require_once "../shared/startup.inc";

?>

<!DOCTYPE HTML>
<html>
<head>
    <meta charset="utf-8">
    <title>Images</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <style>
        #files img { cursor: pointer; margin: 20px; max-height: 150px; max-width: 150px; border: 1px solid rgb(200,200,200); }
    </style>
</head>
<body>

<div id="files">
<?php
	$tables = array("album_images","image_usage_log","page_data","page_data_changes","remote_image_type_data");
	$imageIdTableColumnId = "";
	$resultSet = executeQuery("select table_column_id from table_columns where table_id = (select table_id from tables where table_name = 'images' and " .
		"database_definition_id = (select database_definition_id from database_definitions where database_name = ?)) and column_definition_id = " .
		"(select column_definition_id from column_definitions where column_name = 'image_id')",$GLOBALS['gPrimaryDatabase']->getName());
	if ($row = getNextRow($resultSet)) {
		$imageIdTableColumnId = $row['table_column_id'];
	}
	$tableWhere = "";
	$tableSet = executeQuery("select *,(select table_name from tables where table_id = table_columns.table_id) table_name," .
		"(select column_name from column_definitions where column_definition_id = table_columns.column_definition_id) column_name " .
		"from table_columns where table_column_id in (select table_column_id from foreign_keys where referenced_table_column_id = ?) order by table_name",$imageIdTableColumnId);
	while ($tableRow = getNextRow($tableSet)) {
		if (!in_array($tableRow['table_name'],$tables)) {
			$tableWhere .= " and image_id not in (select " . $tableRow['column_name'] . " from " . $tableRow['table_name'] . " where " . $tableRow['column_name'] . " is not null)";
		}
	}
	$resultSet = executeQuery("select * from images where client_id = ?" . $tableWhere,$GLOBALS['gClientId']);
	while ($row = getNextRow($resultSet)) {
?>
	<img src="/getimage.php?id=<?= $row['image_id'] ?>&type=.jpg" />
<?php
	}
?>
</div>

<script src="<?= autoVersion("/js/jquery-3.4.0.min.js") ?>"></script>
<script src="<?= autoVersion("/js/jquery-migrate-3.0.1.min.js") ?>"></script>
<script>
    $(function () {

        window.frameElement.style.height = '600px';

        $("img").click(function () {

            selectAsset($(this).attr('src'));

        });

    });

    /*
    USE THIS FUNCTION TO SELECT CUSTOM ASSET WITH CUSTOM VALUE TO RETURN
    An asset can be a file, an image or a page in your own CMS
    */
    function selectAsset(assetValue) {
        if (parent.selectImage) {
            parent.selectImage(assetValue);
        } else {
            //Backward compatible

            //Get selected URL
            var inp = parent.top.$('#active-input').val();
            parent.top.$('#' + inp).val(assetValue);

            //Close dialog
            if (window.frameElement.id == 'ifrFileBrowse') parent.top.$("#md-fileselect").data('simplemodal').hide();
            if (window.frameElement.id == 'ifrImageBrowse') parent.top.$("#md-imageselect").data('simplemodal').hide();
        }
    }
</script>
</body>
</html>
