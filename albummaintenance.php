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

$GLOBALS['gPageCode'] = "ALBUMMAINT";
require_once "shared/startup.inc";

class AlbumMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeListColumn("parent_album_id");
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("album_images"));
		$this->iDataSource->addColumnControl("images", "choice_query", "select image_id,description from images where client_id = " . $GLOBALS['gClientId'] . " order by description");
	}

	function supplementaryContent() {
		?>
        <h2>Generate Embed Code</h2>
        <div class="basic-form-line" id="_embed_div_width_row">
            <label for="embed_div_width">Width of gallery (default 650)</label>
            <input type="text" tabindex="10" size="6" id="embed_div_width"
                   class="embed-part validate[custom[integer],min[50]]"/>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>
        <div class="basic-form-line" id="_embed_columns_row">
            <label for="embed_columns">Number of columns (default 6)</label>
            <input type="text" tabindex="10" size="6" id="embed_columns"
                   class="embed-part validate[custom[integer],min[1]]"/>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>
        <div class="basic-form-line" id="_embed_title_row">
            <label for="embed_title"></label>
            <input type="checkbox" tabindex="10" id="embed_title" class="embed-part" value="0"/><label
                    class="checkbox-label" for="embed_title">Hide Album Title</label>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>
        <div class="basic-form-line" id="_embed_description_row">
            <label for="embed_description"></label>
            <input type="checkbox" tabindex="10" id="embed_description" class="embed-part" value="0"/><label
                    class="checkbox-label" for="embed_description">Hide Album Description</label>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>
        <div class="basic-form-line" id="_embed_download_link_row">
            <label for="embed_download_link"></label>
            <input type="checkbox" tabindex="10" id="embed_download_link" class="embed-part" value="true"/><label
                    class="checkbox-label" for="embed_download_link">Show Download Link</label>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>
        <div class="basic-form-line" id="_embed_code_row">
            <label for="embed_code">Embed Code</label>
            <input type="text" tabindex="10" size="100" id="embed_code" readonly="readonly"/>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#album_code,.embed-part").change(function () {
                let extraCode = "";
                $(".embed-part").each(function () {
                    if (($(this).is("[type=checkbox]") && $(this).is(":checked")) || ($(this).is("[type=text]") && !empty($(this).val()))) {
                        extraCode += "&" + $(this).attr("id").replace("embed_", "") + "=" + $(this).val();
                    }
                });
                $("#embed_code").val("<div src='/photoalbum.php?code=" + $("#album_code").val().toLowerCase() + extraCode + "' ></div>");
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
                $("#album_code").trigger("change");
            }
        </script>
		<?php
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		executeQuery("update albums set parent_album_id = null where parent_album_id = album_id");
		return true;
	}
}

$pageObject = new AlbumMaintenancePage("albums");
$pageObject->displayPage();
