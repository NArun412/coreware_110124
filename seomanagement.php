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

$GLOBALS['gPageCode'] = "SEOMANAGEMENT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete","add"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addSearchableSubfield(array("referenced_table_name"=>"page_data",
			"referenced_column_name"=>"page_id","foreign_key"=>"page_id",
			"description"=>"text_data"));
		$this->iDataSource->setFilterWhere("page_id in (select page_id from page_access where public_access = 1 and " .
			"all_client_access = 0 and administrator_access = 0) and inactive = 0 and template_id is not null and client_id = " . $GLOBALS['gClientId']);
		$this->iDataSource->setSaveOnlyPresent(true);
	}

	function onLoadJavascript() {
?>
$("#meta_description").keydown(function() {
	limitText($(this),$("#_meta_description_character_count"),255);
}).keyup(function () {
	limitText($(this),$("#_meta_description_character_count"),255);
});
<?php
	}

	function javascript() {
?>
function limitText(limitField, limitCount, limitNum) {
	if (limitField.val().length > limitNum) {
		limitField.val(limitField.val().substring(0, limitNum));
	}
	limitCount.html("<?= getLanguageText("Character Count") ?>: " + (limitField.val().length));
}

function afterGetRecord() {
	limitText($("#meta_description"),$("#_meta_description_character_count"),255);
	if ($(".page-image").length > 0) {
		$("#page_images").prev("h2").show();
	} else {
		$("#page_images").prev("h2").hide();
	}
	if ($(".page-data").length > 0) {
		$("#page_data").prev("h2").show();
	} else {
		$("#page_data").prev("h2").hide();
	}
}

<?php
	}

	function afterGetRecord(&$returnArray) {
		$imageArray = array();
		$resultSet = executeQuery("select * from page_data where (image_id is not null or text_data like '%getimage.php%') and page_id = ?",$returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['image_id'])) {
				if (!in_array($row['image_id'],$imageArray)) {
					$imageArray[] = $row['image_id'];
				}
			}
			$content = str_replace("getimage.php","/getimage.php",$row['text_data']);
			$content = str_replace("getimage.php?id=","getimage.php?image_id=",$content);
			$startPosition = 0;
			while (true) {
				$stringPosition = strpos($content,"/getimage.php?code=",$startPosition);
				if ($stringPosition === false || $stringPosition > 10000) {
					break;
				}
				$endPosition = $stringPosition + strlen("/getimage.php?code=");
				while (substr($content,$endPosition,1) != "'" && substr($content,$endPosition,1) != '"' && $endPosition < strlen($content)) {
					$endPosition++;
				}
				$imageCode = substr($content,$stringPosition + strlen("/getimage.php?code="),$endPosition - $stringPosition - strlen("/getimage.php?code="));
				$imageId = getFieldFromId("image_id","images","image_code",$imageCode);
				if (!empty($imageId) && !in_array($row['image_id'],$imageArray)) {
					$imageArray[] = $imageId;
				}
				$startPosition = $stringPosition + 1;
			}
			$startPosition = 0;
			while (true) {
				$stringPosition = strpos($content,"/getimage.php?image_id=",$startPosition);
				if ($stringPosition === false || $stringPosition > 10000) {
					break;
				}
				$endPosition = $stringPosition + strlen("/getimage.php?image_id=");
				while (substr($content,$endPosition,1) != "'" && substr($content,$endPosition,1) != '"' && $endPosition < strlen($content)) {
					$endPosition++;
				}
				$imageId = substr($content,$stringPosition + strlen("/getimage.php?image_id="),$endPosition - $stringPosition - strlen("/getimage.php?image_id="));
				$imageId = getFieldFromId("image_id","images","image_id",$imageId);
				if (!empty($imageId) && !in_array($row['image_id'],$imageArray)) {
					$imageArray[] = $imageId;
				}
				$startPosition = $stringPosition + 1;
			}
		}
		ob_start();
		foreach ($imageArray as $imageId) {
			$description = getFieldFromId("description","images","image_id",$imageId);
			$detailedDescription = getFieldFromId("detailed_description","images","image_id",$imageId);
?>
<div class="page-image">
	<div class="page-image-img">
		<span class="helper"></span>
		<a href="<?= getImageFilename($imageId,array("use_cdn"=>true)) ?>" class="pretty-photo"><img class='image-thumbnail' src="<?= getImageFilename($imageId,array("use_cdn"=>true)) ?>"></a>
	</div>
	<p><label for="image_title_<?= $imageId ?>">Title</label><input tabindex="10" type="text" class="field-text image-description" id="image_title_<?= $imageId ?>" name="image_title_<?= $imageId ?>" value="<?= htmlText($description) ?>"></p>
	<p><label for="image_alt_<?= $imageId ?>">Alt</label><textarea tabindex="10" class="image-detailed-description field-text" id="image_alt_<?= $imageId ?>" name="image_alt_<?= $imageId ?>" data-crc_value="<?= getCrcValue($detailedDescription) ?>"><?= htmlText($detailedDescription) ?></textarea></p>
	<div class='clear-div'></div>
</div>
<?php
		}
		$returnArray['page_images']['data_value'] = ob_get_clean();
		ob_start();
		$imageArray = array();
		$resultSet = executeQuery("select * from page_data where text_data is not null and template_data_id in (select template_data_id from template_data where data_type = 'html') and page_id = ?",$returnArray['primary_id']['data_value']);
		while ($row = getNextRow($resultSet)) {
			$templateId = getFieldFromId("template_id","pages","page_id",$returnArray['primary_id']['data_value'],"client_id is not null");
			$templateDirectory = getFieldFromId("directory_name","templates","template_id",$templateId,"client_id = ? or client_id = ?",$GLOBALS['gClientId'],$GLOBALS['gDefaultClientId']);
			$cssFile = $ckEditorFile = "";
			if (!empty($templateDirectory)) {
				if (file_exists($GLOBALS['gDocumentRoot'] . "/templates/" . $templateDirectory . "/style.css")) {
					$cssFile = "/templates/" . $templateDirectory . "/style.css";
				}
				if (file_exists($GLOBALS['gDocumentRoot'] . "/templates/" . $templateDirectory . "/ckeditor.js")) {
					$ckEditorFile = "/templates/" . $templateDirectory . "/ckeditor.js";
				}
			}
?>
<div class="form-line" id="_%column_name%_row">
	<label for="page_data_<?= $row['page_data_id'] ?>"><?= getFieldFromId("description","template_data","template_data_id",$row['template_data_id']) ?></label>
	<div class='textarea-wrapper'><textarea tabindex="10" class="page-data field-text" data-styles_set="<?= $ckEditorFile ?>" data-contents_css="<?= $cssFile ?>" name="page_data_<?= $row['page_data_id'] ?>" id="page_data_<?= $row['page_data_id'] ?>" data-crc_value="<?= getCrcValue($row['text_data']) ?>"><?= htmlText($row['text_data']) ?></textarea></div>
	<div class="toggle-wysiwyg" data-id="page_data_<?= $row['page_data_id'] ?>" data-checked="false"></div>
	<div class='clear-div'></div>
</div>
<?php
		}
		$returnArray['page_data']['data_value'] = ob_get_clean();
		$resultSet = executeQuery("select * from required_meta_tags where client_id = ?",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$dataSet = executeQuery("select * from page_meta_tags where page_id = ? and meta_name = ? and meta_value = ?",
				$returnArray['primary_id']['data_value'],$row['meta_name'],$row['meta_value']);
			if ($dataRow = getNextRow($dataSet)) {
				$returnArray['required_meta_tag_id_' . $row['required_meta_tag_id']] = array("data_value"=>$dataRow['content'],"crc_value"=>getCrcValue($dataRow['content']));
			} else {
				$returnArray['required_meta_tag_id_' . $row['required_meta_tag_id']] = array("data_value"=>"","crc_value"=>getCrcValue(""));
			}
		}
	}

	function afterSaveChanges($nameValues,$actionPerformed) {
		foreach ($nameValues as $fieldName => $fieldData) {
			if (substr($fieldName,0,strlen("required_meta_tag_id_")) == "required_meta_tag_id_") {
				$requiredMetaTagId = substr($fieldName,strlen("required_meta_tag_id_"));
				if (is_numeric($requiredMetaTagId)) {
					$resultSet = executeQuery("select * from required_meta_tags where required_meta_tag_id = ? and client_id = ?",$requiredMetaTagId,$GLOBALS['gClientId']);
					if ($row = getNextRow($resultSet)) {
						$dataSet = executeQuery("select * from page_meta_tags where page_id = ? and meta_name = ? and meta_value = ?",
							$nameValues['primary_id'],$row['meta_name'],$row['meta_value']);
						if ($dataRow = getNextRow($dataSet)) {
							if (empty($fieldData)) {
								executeQuery("delete from page_meta_tags where page_meta_tag_id = ?",$dataRow['page_meta_tag_id']);
							} else {
								executeQuery("update page_meta_tags set content = ? where page_meta_tag_id = ?",$fieldData,$dataRow['page_meta_tag_id']);
							}
						} else {
							executeQuery("insert into page_meta_tags (page_id,meta_name,meta_value,content) values (?,?,?,?)",
								$nameValues['primary_id'],$row['meta_name'],$row['meta_value'],$fieldData);
						}
					}
				}
			}
			if (substr($fieldName,0,strlen("image_alt_")) == "image_alt_") {
				$imageId = substr($fieldName,strlen("image_alt_"));
				if (is_numeric($imageId)) {
					executeQuery("update images set detailed_description = ? where image_id = ? and client_id = ?",
						$fieldData,$imageId,$GLOBALS['gClientId']);
				}
			}
			if (substr($fieldName,0,strlen("image_title_")) == "image_title_") {
				$imageId = substr($fieldName,strlen("image_title_"));
				if (is_numeric($imageId)) {
					executeQuery("update images set description = ? where image_id = ? and client_id = ?",
						$fieldData,$imageId,$GLOBALS['gClientId']);
				}
			}
			if (substr($fieldName,0,strlen("page_data_")) == "page_data_") {
				$pageDataId = substr($fieldName,strlen("page_data_"));
				if (is_numeric($pageDataId)) {
					executeQuery("update page_data set text_data = ? where page_data_id = ? and page_id = ?",
						$fieldData,$pageDataId,$nameValues['primary_id']);
				}
			}
		}
		return true;
	}

	function requiredMetaTags() {
		$resultSet = executeQuery("select * from required_meta_tags where client_id = ? order by sort_order,required_meta_tag_id",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
?>
<div class="form-line" id="_required_meta_tag_id_<?= $row['required_meta_tag_id'] ?>">
	<label for="required_meta_tag_id_<?= $row['required_meta_tag_id'] ?>" class="required-label"><?= $row['meta_name'] ?>='<?= $row['meta_value'] ?>'</label>
	<textarea tabindex="10" class="meta-tag field-text" id="required_meta_tag_id_<?= $row['required_meta_tag_id'] ?>" name="required_meta_tag_id_<?= $row['required_meta_tag_id'] ?>"></textarea>
	<div class='clear-div'></div>
</div>
<?php
		}
	}

	function internalCSS() {
?>
.meta-tag { height: 50px; }
.image-description { width: 500px; }
.image-detailed-description { height: 60px; width: 500px; }
.image-thumbnail { max-height: 80px; max-width: 80px; }
.page-image { position: relative; height: 80px; margin-bottom: 20px; }
.page-image-img { position: relative; height: 80px; float: left; }
.page-image img { vertical-align: middle; }
.page-image p { margin: 0; padding: 0; margin-left: 90px; position: relative; }
.page-image p input { position: relative; left: 50px; }
.page-image p textarea { position: relative; left: 50px; }
.page-image p label { width: 40px; display: inline-block; position: absolute; top: 5px; text-align: right; }
.helper { display: inline-block; height: 100%; vertical-align: middle; }
<?php
	}
}

$pageObject = new ThisPage("pages");
$pageObject->displayPage();
