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

$GLOBALS['gPageCode'] = "PHOTOALBUM";
require_once "shared/startup.inc";

if ($_GET['url_action'] == "get_gallery") {
	$albumId = getFieldFromId("album_id", "albums", "album_id", $_GET['album_id'], "inactive = 0 and internal_use_only = 0 and client_id = " . $GLOBALS['gClientId']);
	$downloadLink = ($_GET['download_link'] == "true");
	if ($_GET['parent'] == "true") {
		$parentAlbumId = getFieldFromId("parent_album_id", "albums", "album_id", $albumId, "inactive = 0 and internal_use_only = 0 and client_id = " . $GLOBALS['gClientId']);
	}
	ob_start();
	if ($parentAlbumId) {
		$returnLink = "<p><a href='#' class='photo-gallery-album' data-album_id='" . $parentAlbumId . "'>Return to " . getFieldFromId("description", "albums", "album_id", $parentAlbumId) . "</a></p>";
	}
	getGalleryContent($albumId, $returnLink, $downloadLink);
	$returnArray = array();
	$returnArray['gallery_content'] = ob_get_clean();
	ajaxResponse($returnArray);
}

/*
Parameters

album_id or id, default=there is none
div_width, div width, default=650
columns, number of thumbnails wide, default=6
title, display title, default=true
description, display detailed description, default=true
image_title, display image titles, default=true
*/

$albumId = $_GET['album_id'];
if (empty($albumId)) {
	$albumId = $_GET['id'];
}
$albumId = getFieldFromId("album_id", "albums", "album_id", $albumId, "inactive = 0 and internal_use_only = 0 and client_id = " . $GLOBALS['gClientId']);
if (empty($albumId)) {
	$albumCode = $_GET['code'];
	if (empty($albumCode)) {
		$albumCode = $_GET['album_code'];
	}
	$albumId = getFieldFromId("album_id", "albums", "album_code", $albumCode, "inactive = 0 and internal_use_only = 0 and client_id = " . $GLOBALS['gClientId']);
}
$defaults = array("div_width" => "650", "columns" => "6", "title" => true, "description" => true, "image_title" => true, "download_link" => false);
foreach ($defaults as $name => $value) {
	if (array_key_exists($name, $_GET)) {
		$defaults[$name] = $_GET[$name];
	}
}
$defaults['width'] = round($defaults['div_width'] / $defaults['columns']) - 20;
if (empty($_GET['no_style'])) {
	?>

    <style type="text/css">
        div#_gallery_<?= $albumId ?> {
            width: <?= $defaults['div_width'] ?>px;
            position: relative;
        }
        div#_gallery_<?= $albumId ?> p.gallery-title {
            padding-top: 6px;
            padding-bottom: 5px;
            font-size: 20px;
        }
        div#_gallery_<?= $albumId ?> div.image-cell {
            width: <?= $defaults['width'] ?>px;
            height: <?= $defaults['width'] + 40 ?>px;
            overflow: hidden;
            float: left;
            margin-right: 20px;
            margin-bottom: 20px;
            -moz-box-sizing: border-box;
            -webkit-box-sizing: border-box;
            box-sizing: border-box;
        }
        div#_gallery_<?= $albumId ?> div.image-cell p {
            text-align: center;
            font-size: 10px;
            line-height: 1.0;
            padding-top: 10px;
        }
        div#_gallery_<?= $albumId ?> div.gallery-image-div {
            background-repeat: no-repeat;
            border: 1px solid rgb(200, 200, 200);
            -moz-box-shadow: 3px 3px 5px 0 rgb(100, 100, 100);
            -webkit-box-shadow: 3px 3px 5px 0 rgb(100, 100, 100);
            box-shadow: 3px 3px 5px 0 rgb(100, 100, 100);
            margin-left: auto;
            margin-right: auto;
            overflow: hidden;
            background-size: cover;
            background-position: center;
            width: <?= $defaults['width'] ?>px;
            height: <?= $defaults['width'] ?>px;
        }
        .pp_details a img[src*=download] {
            float: right;
        }
        <?php if (!$defaults['title']) { ?>
        div#_gallery_<?= $albumId ?> .gallery-title {
            display: none;
        }
        <?php } ?>
        <?php if (!$defaults['description']) { ?>
        div#_gallery_<?= $albumId ?> .gallery-description {
            display: none;
        }
        <?php } ?>
        <?php if (!$defaults['image_title']) { ?>
        div#_gallery_<?= $albumId ?> .image-caption {
            display: none;
        }
        <?php } ?>
    </style>
<?php } ?>

    <div id="_gallery_<?= $albumId ?>" class="photo-gallery" data-top_album_id="<?= $albumId ?>" data-album_id="<?= $albumId ?>" data-download_link="<?= ($defaults['download_link'] ? "true" : "false") ?>">

		<?php getGalleryContent($albumId, "", $defaults['download_link']) ?>

    </div>

<?php
function getGalleryContent($albumId, $returnLink = "", $showDownloadLink = true) {
	?>
    <p class="gallery-title"><?= getFieldFromId("description", "albums", "album_id", $albumId) ?></p>
    <p class="gallery-description"><?= makeHtml(getFieldFromId("detailed_description", "albums", "album_id", $albumId)) ?></p>
	<?php
	echo $returnLink;
	$resultSet = executeQuery("select * from albums where parent_album_id = ? order by sort_order,description", $albumId);
	while ($row = getNextRow($resultSet)) {
		?>
        <div class="image-cell">
			<?php if ($row['image_id']) { ?>
                <a href="#" class="photo-gallery-album" data-album_id="<?= $row['album_id'] ?>">
                    <div class="gallery-image-div" style="background-repeat: no-repeat; background-size: contain; background-image: url('<?= getImageFilename($row['image_id'], array("use_cdn" => true, "image_type" => "thumbnail")) ?>');"></div>
                </a>
			<?php } else { ?>
                <a href="#" class="photo-gallery-album" data-album_id="<?= $row['album_id'] ?>">
                    <div class="gallery-image-div" style="background-color: rgb(200,200,200);"></div>
                </a>
			<?php } ?>
            <p class='image-caption'><?= $row['description'] ?></p>
        </div>
		<?php
	}

	$resultSet = executeQuery("select images.image_id,images.description,images.detailed_description from images,album_images where client_id = ? and images.image_id = album_images.image_id and album_images.album_id = ? order by sequence_number,description", $GLOBALS['gClientId'], $albumId);
	while ($row = getNextRow($resultSet)) {
		$imageDataTypeId = getFieldFromId("image_data_type_id", "image_data_types", "image_data_type_code", "REQUIRES_APPROVAL");
		if (!empty($imageDataTypeId)) {
			$requiresApproval = getFieldFromId("text_data", "image_data", "image_id", $row['image_id'], "image_data_type_id = ?", $imageDataTypeId);
			if (!empty($requiresApproval)) {
				continue;
			}
		}
		$imageDataTypeId = getFieldFromId("image_data_type_id", "image_data_types", "image_data_type_code", "APPROVED");
		if (!empty($imageDataTypeId)) {
			$approved = getFieldFromId("text_data", "image_data", "image_id", $row['image_id'], "image_data_type_id = ?", $imageDataTypeId);
			if (empty($approved)) {
				continue;
			}
		}
		$otherClasses = "";
		$imageDataTypeId = getFieldFromId("image_data_type_id", "image_data_types", "image_data_type_code", "CATEGORY");
		if (!empty($imageDataTypeId)) {
			$category = getFieldFromId("text_data", "image_data", "image_id", $row['image_id'], "image_data_type_id = ?", $imageDataTypeId);
            if (!empty($category)) {
                $otherClasses .= (empty($otherClasses) ? "" : " ") . makeCode($category, array("use_dash" => true, "lowercase" => true));
            }
		}
        $dataSet = executeQuery("select * from image_data_types where client_id = ? and image_data_type_code like 'CATEGORY\_%' and inactive = 0",$GLOBALS['gClientId']);
        while ($dataRow = getNextRow($dataSet)) {
	        $category = getFieldFromId("text_data", "image_data", "image_id", $row['image_id'], "image_data_type_id = ?", $dataRow['image_data_type_id']);
	        if (!empty($category)) {
		        $otherClasses .= (empty($otherClasses) ? "" : " ") . makeCode($dataRow['image_data_type_code'], array("use_dash" => true, "lowercase" => true));
	        }
        }
		?>
        <div class="image-cell <?= $otherClasses ?>">
            <a href="<?= getImageFilename($row['image_id'], array("use_cdn" => true)) ?>" rel="prettyPhoto[album_<?= $albumId ?>]" title="<?= (empty($row['detailed_description']) ? $row['description'] : $row['detailed_description']) ?>">
				<?php if ($showDownloadLink) { ?><a href='/getimage.php?id=<?= $row['image_id'] ?>&force_download=true'><img src='/images/download.png'></a><?php } ?>
                <div class="gallery-image-div" style="background-repeat: no-repeat; background-size: contain; background-image: url('<?= getImageFilename($row['image_id'], array("use_cdn" => true, "image_type" => "small")) ?>');"></div>
            </a>
            <p class='image-caption'><?= $row['description'] ?></p>
        </div>
		<?php
	}
	?>
    <div class='clear-div'></div>
	<?php
}
