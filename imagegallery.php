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

$GLOBALS['gPageCode'] = "IMAGEGALLERY";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function mainContent() {
		$albumId = getFieldFromId("album_id","albums","album_id",$_GET['id'],
			"client_id = ?",$GLOBALS['gClientId']);
		if (empty($albumId)) {
			$resultSet = executeQuery("select * from albums where client_id = ? and " .
				"inactive = 0 and internal_use_only = 0 order by sort_order,description",
				$GLOBALS['gClientId']);
			$columnCount = 0;
?>
<table id="album_table">
<tr>
<?php
			while ($row = getNextRow($resultSet)) {
				if ($columnCount >= 5) {
					$columnCount = 0;
					echo "</tr><tr>";
				}
				$imageSet = executeQuery("select image_id from images where client_id = ? and image_id in " .
					"(select image_id from album_images where album_id = ?) order by description limit 1",
					$GLOBALS['gClientId'],$row['album_id']);
				if ($imageRow = getNextRow($imageSet)) {
					$columnCount++;
?>
<td class="align-center">
	<a href="<?= $GLOBALS['gLinkUrl'] ?>?id=<?= $row['album_id'] ?>" class="album-gallery">
		<div class="gallery-image-div" style="background-size: contain; background-repeat: no-repeat; background-image: url('<?= getImageFilename($imageRow['image_id'],array("use_cdn"=>true,"image_type"=>"thumbnail")) ?>');">
		</div>
	</a>
	<p><?= $row['description'] ?></p>
</td>
<?php
				}
			}
?>
</tr>
</table>
<?php
		} else {
			$description = getFieldFromId("description","albums","album_id",$albumId);
?>
<p><a href="<?= $GLOBALS['gLinkUrl'] ?>">Photo Albums</a>&nbsp;->&nbsp;<?= htmlText($description) ?></p>
<div src='/photoalbum.php?id=<?= $albumId ?>'></div>
<?php
		}
	}

	function setPageTitle() {
		$thisPageTitle = "";
		$albumId = getFieldFromId("album_id","albums","album_id",$_GET['id'],
			"client_id = ?",$GLOBALS['gClientId']);
		if (!empty($albumId)) {
			$GLOBALS['gPageRow']['window_title'] = getFieldFromId("description","albums","album_id",$albumId) . " | " . $GLOBALS['gClientName'];
		}
		return $thisPageTitle;
	}

	function internalCSS() {
?>
.gallery-image-div { border: 1px solid rgb(200,200,200); -moz-box-shadow: 3px 3px 5px 0 rgb(100,100,100); -webkit-box-shadow: 3px 3px 5px 0 rgb(100,100,100); box-shadow: 3px 3px 5px 0 rgb(100,100,100); margin-left: auto; margin-right: auto; overflow: hidden; background-size: cover; background-position: center; width: 100px; height: 100px; }
#album_table td { padding-right: 40px; }
#album_table td p { font-size: 9px; padding-top: 10px; }
<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
