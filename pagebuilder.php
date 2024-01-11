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

$GLOBALS['gPageCode'] = "PAGEBUILDER";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("delete"));
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("description", "page_pattern_id", "link_name"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->setFilterWhere("page_pattern_id is not null");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "preview_not_available":
				?>
                <body>
                <h1 class="align-center">Preview not available until page is created.</h1>
                </body>
				<?php
				exit;
			case "save_page":
				$pageId = $_POST['primary_id'];
				if (empty($pageId)) {
					$originalPageId = getFieldFromId("page_id", "page_patterns", "page_pattern_id", $_POST['page_pattern_id']);
					if (empty($originalPageId)) {
						$returnArray['error_message'] = "Invalid Page Pattern";
						ajaxResponse($returnArray);
						break;
					}
					$resultSet = executeQuery("select * from pages where page_id = ?", $originalPageId);
					$pageRow = getNextRow($resultSet);
					$subNumber = 1;
					$queryString = "";
					foreach ($pageRow as $fieldName => $fieldData) {
						if (empty($queryString)) {
							$pageRow[$fieldName] = "";
						}
						if ($fieldName == "client_id") {
							$pageRow[$fieldName] = $GLOBALS['gClientId'];
						}
						$queryString .= (empty($queryString) ? "" : ",") . "?";
					}
					$pageId = "";
					$pageRow['description'] = $_POST['description'];
					$pageRow['link_name'] = $_POST['link_name'];
					$pageRow['date_created'] = date("Y-m-d");
					$pageRow['creator_user_id'] = $GLOBALS['gUserId'];
					$pageRow['inactive'] = (empty($_POST['inactive']) ? 0 : 1);
					$pageRow['internal_use_only'] = (empty($_POST['internal_use_only']) ? 0 : 1);
					$pageRow['page_pattern_id'] = $_POST['page_pattern_id'];
					$pageRow['page_code'] = getRandomString(32);
					$pageId = $this->iDataSource->saveRecord(array("name_values" => $pageRow, "primary_id" => ""));

					$subTables = array("page_controls", "page_data", "page_functions", "page_meta_tags", "page_notifications", "page_text_chunks", "page_access", "page_sections");
					foreach ($subTables as $tableName) {
						$resultSet = executeQuery("select * from " . $tableName . " where page_id = ?", $originalPageId);
						while ($row = getNextRow($resultSet)) {
							$queryString = "";
							foreach ($row as $fieldName => $fieldData) {
								if (empty($queryString)) {
									$row[$fieldName] = "";
								}
								$queryString .= (empty($queryString) ? "" : ",") . "?";
							}
							$row['page_id'] = $pageId;
							$insertSet = executeQuery("insert into " . $tableName . " values (" . $queryString . ")", $row);
						}
					}
				} else {
					$this->iDataSource->saveRecord(array("name_values" => $_POST, "primary_id" => $pageId));
				}
				foreach ($_POST as $fieldName => $fieldValue) {
					if (substr($fieldName, 0, strlen("_placeholder_")) == "_placeholder_") {
						$pageTextChunkCode = strtoupper(substr($fieldName, strlen("_placeholder_")));
						$pagePlaceholderRow = getRowFromId("page_placeholders", "page_placeholder_code", $pageTextChunkCode);
						if (empty($pagePlaceholderRow)) {
							continue;
						}
						if ($pagePlaceholderRow['data_type'] == "image_input") {
							if (!empty($_FILES[$fieldName . "_file"])) {
								$imageId = createImage($fieldName . "_file");
								if (!empty($imageId)) {
									$fieldValue = $imageId;
								}
							}
						}
						$pageTextChunkRow = getRowFromId("page_text_chunks", "page_id", $pageId, "page_text_chunk_code = ?", $pageTextChunkCode);
						$pageTextChunkRow['page_id'] = $pageId;
						$pageTextChunkRow['page_text_chunk_code'] = $pageTextChunkCode;
						$pageTextChunkRow['description'] = $pagePlaceholderRow['description'];
						$pageTextChunkRow['content'] = $fieldValue;
						$pageTextChunkDataTable = new DataTable("page_text_chunks");
						$pageTextChunkDataTable->saveRecord(array("name_values" => $pageTextChunkRow, "primary_id" => $pageTextChunkRow['page_text_chunk_id']));
					}
				}
				$returnArray['page_id'] = $pageId;
				if (empty($_POST['primary_id'])) {
					$returnArray['info_message'] = "Page Created";
				} else {
					$returnArray['info_message'] = "Page Updated";
				}
				$returnArray['link_name'] = $pageRow['link_name'];
				ajaxResponse($returnArray);
				break;
			case "get_placeholders":
				$pageId = getFieldFromId("page_id", "pages", "page_id", $_GET['page_id'], "page_pattern_id is not null");
				if (!empty($pageId)) {
					$pageRow = getRowFromId("pages", "page_id", $pageId);
					$pagePatternId = getFieldFromId("page_pattern_id", "pages", "page_id", $pageId);
				} else {
					$pageRow = array();
					$pagePatternId = getFieldFromId("page_pattern_id", "page_patterns", "page_pattern_id", $_GET['page_pattern_id']);
				}
				if (empty($pagePatternId)) {
					$returnArray['error_message'] = "Invalid Page Pattern";
					ajaxResponse($returnArray);
					break;
				}
				ob_start();
				?>
                <div id="page_placeholder_wrapper">
                    <input type="hidden" id="page_pattern_id" name="page_pattern_id" value="<?= $pagePatternId ?>">

                    <div class="basic-form-line" id="_description_row">
                        <label for="description" class="required-label">Page Description</label>
                        <input tabindex="10" type="text" size="60" class="validate[required]" id="description" name="description" value="<?= $pageRow['description'] ?>">
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line" id="_link_name_row">
                        <label for="link_name" class="required-label">URL Link</label>
                        <input tabindex="10" type="text" size="60" class="validate[required] url-link lowercase" id="link_name" name="link_name" value="<?= $pageRow['link_name'] ?>">
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
					<?php
					$resultSet = executeQuery("select * from page_placeholders join page_pattern_placeholders using (page_placeholder_id) where page_pattern_id = ? order by sequence_number", $pagePatternId);
					while ($row = getNextRow($resultSet)) {
						$column = $this->createColumn($row['page_placeholder_id']);
						if (!empty($pageRow)) {
							$initialValue = getFieldFromId("content", "page_text_chunks", "page_id", $pageRow['page_id'], "page_text_chunk_code = ?", $row['page_placeholder_code']);
							$column->setControlValue("initial_value", $initialValue);
						}
						?>
                        <div class='basic-form-line' id="_<?= $column->getControlValue("column_name") ?>_row">
                            <label class="<?= (empty($column->getControlValue("not_null")) ? "" : "required-label") ?>" for="<?= $column->getControlValue("column_name") ?>"><?= $column->getControlValue("form_label") ?></label>
							<?= $column->getControl() ?>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>
						<?php
					}
					?>

                    <input type='hidden' id='inactive' name='inactive' value=''>

                    <div class="basic-form-line" id="_internal_use_only_row">
                        <select tabindex="10" id="internal_use_only" name="internal_use_only">
                            <option value="0"<?= (!$pageRow['internal_use_only'] ? " selected" : "") ?>>Published</option>
                            <option value="1"<?= (!$pageRow['internal_use_only'] ? " selected" : "") ?>>NOT Published</option>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <p>
                        <button id="save_page">Create Page</button>
                        <button id="cancel_build">Cancel</button>
                    </p>
                </div>

                <div id="_page_preview_outer_wrapper" class="preview-outer-wrapper">
                    <div id="_page_preview_controls" class="preview-controls"><span class="fas fa-desktop selected" data-screen="desktop"></span><span class="fas fa-tablet-alt" data-screen="tablet"></span><span class="fas fa-mobile-alt" data-screen="mobile"></span></div>
                    <div id="_page_preview_wrapper" class="preview-wrapper">
                        <iframe id="_page_preview" class="page-preview-iframe desktop" src="<?= (empty($pageRow['link_name']) ? $GLOBALS['gLinkUrl'] . "?url_action=preview_not_available" : "/" . $pageRow['link_name']) ?>"></iframe>
                    </div> <!-- page_preview_wrapper -->
                </div> <!-- page_preview_outer_wrapper -->

                <p><a href="/<?= $pageRow['link_name'] ?>" target="_blank">Open Page</a><a class='float-right' href='#' id='make_inactive'>Delete Page</a></p>
				<?php
				$returnArray['builder_section'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
		}
	}

	function createColumn($pagePlaceholderId) {
		$resultSet = executeQuery("select * from page_placeholders where page_placeholder_id = ? and client_id = ?", $pagePlaceholderId, $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$columnControls = array("form_label" => $row['description'], "column_name" => "_placeholder_" . $row['page_placeholder_code'], "data_type" => $row['data_type'], "not_null" => !empty($row['required']));
			$resultSet = executeQuery("select * from page_placeholder_controls where page_placeholder_id = ?", $pagePlaceholderId);
			while ($controlRow = getNextRow($resultSet)) {
				$columnControls[$controlRow['control_name']] = DataSource::massageControlValue($controlRow['control_name'], $controlRow['control_value']);
			}
		} else {
			$columnControls = array("column_name" => "page_placeholder_id_" . $pagePlaceholderId, "form_label" => "Page Placeholder Not Found");
		}
		if (empty($columnControls['data_type'])) {
			$columnControls['data_type'] = "varchar";
		}
		$thisColumn = new DataColumn($row['page_placeholder_code'], $columnControls);
		$dataType = $thisColumn->getControlValue("data_type");
		switch ($dataType) {
			case "image_input":
			case "image":
			case "file":
				if (!empty($columnControls['not_null'])) {
					$thisColumn->setControlValue("no_remove", true);
				}
				break;
		}
		if ($dataType == "select" || $dataType == "radio") {
			$choices = $thisColumn->getControlValue("choices");
			if (!is_array($choices)) {
				$choices = array();
			}
			$choiceSet = executeQuery("select * from page_placeholder_choices where page_placeholder_id = ?", $row['page_placeholder_id']);
			while ($choiceRow = getNextRow($choiceSet)) {
				$choices[$choiceRow['key_value']] = $choiceRow['description'];
			}
			$thisColumn->setControlValue("choices", $choices);
		}
		return $thisColumn;
	}

	function mainContent() {
		if ($_GET['url_page'] == "new" || $_GET['url_page'] == "show") {
			?>
            <div id="_chooser_section">
                <div id="_choose_pattern">
                    <h2>Choose A Page</h2>
                    <div id="_page_patterns">
						<?php
						$resultSet = executeQuery("select * from pages join page_patterns using (page_id) where page_patterns.client_id = ? and inactive = 0 order by page_patterns.description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							if (empty($row['script_filename']) && empty($row['link_name'])) {
								continue;
							}
							if (canAccessPage($row['page_id'])) {
								$linkUrl = (empty($row['link_name']) ? $row['script_filename'] : $row['link_name']);
								?>
                                <div class="page-pattern equal-height-blocks">
                                    <button class="page-pattern-button" data-page_pattern_id="<?= $row['page_pattern_id'] ?>" data-link_url="<?= $linkUrl ?>" data-page_id="<?= $row['page_id'] ?>"><?= htmlText($row['description']) ?></button>
                                </div>
								<?php
							}
						}
						?>
                    </div> <!-- page_patterns -->
                </div>

                <div id="_pattern_page_preview_outer_wrapper" class="hidden preview-outer-wrapper">
                    <div id="_pattern_page_preview_controls" class="preview-controls"><span class="fas fa-desktop selected" data-screen="desktop"></span><span class="fas fa-tablet-alt" data-screen="tablet"></span><span class="fas fa-mobile-alt" data-screen="mobile"></span></div>
                    <div id="_pattern_page_preview_wrapper" class="preview-wrapper">
                        <iframe id="_pattern_page_preview" class="page-preview-iframe desktop" src=""></iframe>
                    </div> <!-- pattern_page_preview_wrapper -->

                    <div id="_build_page_wrapper">
                        <p>
                            <button id="build_page">Build This Page</button>
                        </p>
                    </div> <!-- build_page_wrapper -->
                </div> <!-- pattern_page_outer_wrapper -->
            </div> <!-- chooser_section -->

            <div id="_builder_section" class="hidden">
            </div>

			<?php
			return true;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#make_inactive", function () {
                $("#inactive").val("1");
                $(this).attr("id", "make_active").html("Undo Delete");
                return false;
            });
            $(document).on("click", "#make_active", function () {
                $("#inactive").val("");
                $(this).attr("id", "make_inactive").html("Delete Page");
                return false;
            });
            $("#_add_button").html("Create New Page");
            $(document).on("click", "#save_page", function () {
                saveChanges();
                return false;
            });

            $(document).on("click", "#cancel_build", function () {
                if (changesMade()) {
                    askAboutChanges(function () {
                        $("#_chooser_section").removeClass("hidden");
                        $("#_builder_section").html("").addClass("hidden");
                        clearMessage();
                    });
                } else {
                    $("#_chooser_section").removeClass("hidden");
                    $("#_builder_section").html("").addClass("hidden");
                    clearMessage();
                }
            });
            $(".page-pattern-button").click(function () {
                $(".page-pattern-button").removeClass("selected");
                $(this).addClass("selected");
                getPagePatternPreview();
                return false;
            });
            $(document).on("click", ".preview-controls span", function () {
                var screen = $(this).data("screen");
                $(this).closest(".preview-outer-wrapper").find(".page-preview-iframe").removeClass("desktop").removeClass("tablet").removeClass("mobile").addClass(screen);
                $(this).closest(".preview-outer-wrapper").find(".preview-controls span").removeClass("selected");
                $(this).addClass("selected");
            });
            $("#build_page").click(function () {
                var pagePatternId = $(".page-pattern-button.selected").data("page_pattern_id");
                if (empty(pagePatternId)) {
                    return false;
                }
                displayPageData(pagePatternId);
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            var postTimeout = null;

            function displayPageData(pagePatternId) {
                $("#_chooser_section").addClass("hidden");
                $("#_builder_section").html("").removeClass("hidden");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_placeholders&page_pattern_id=" + pagePatternId + "&page_id=" + $("#primary_id").val(), function(returnArray) {
                    if ("builder_section" in returnArray) {
                        $("#_builder_section").html(returnArray['builder_section']);
                        $("#_edit_form .required-label").append("<span class='required-tag fa fa-asterisk'></span>");
                        $("#_edit_form").validationEngine();
                        $("#_edit_form").find("input,select").each(function () {
                            if ($(this).is("input[type=checkbox]")) {
                                $(this).data("crc_value", getCrcValue($(this).prop("checked") ? "1" : "0"));
                            } else {
                                $(this).data("crc_value", getCrcValue($(this).val()));
                            }
                        });
                        $(".view-image-link").each(function () {
                            $(this).show();
                            if ($(this).closest(".basic-form-line").find("input[type=hidden]").length == 1 && $(this).closest(".basic-form-line").find("input[type=hidden]").val() == "") {
                                $(this).hide();
                            }
                            if ($(this).closest(".basic-form-line").find("select").length == 1 && empty($(this).closest(".basic-form-line").find("select").val())) {
                                $(this).hide();
                            }
                        });
                        $("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({ social_tools: false, default_height: 480, default_width: 854, deeplinking: false });
                        if (empty($("#primary_id").val())) {
                            $("#cancel_build").removeClass("hidden");
                            $("#save_page").html("Create Page");
                        } else {
                            $("#cancel_build").addClass("hidden");
                            $("#save_page").html("Save Changes");
                        }
                        $("#description").focus();
                    }
                });
            }

            function afterGetRecord(returnArray) {
                if (!empty(returnArray['primary_id']['data_value'])) {
                    displayPageData();
                }
            }

            function beforeSaveChanges() {
                for (instance in CKEDITOR.instances) {
                    CKEDITOR.instances[instance].updateElement();
                }
                if ($("#_edit_form").validationEngine('validate')) {
                    $("body").addClass("waiting-for-ajax");
                    $("#_build_iframe").off("load");
                    $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_page").attr("method", "POST").attr("target", "build_iframe").submit();
                    $("#_build_iframe").on("load", function () {
                        if (postTimeout != null) {
                            clearTimeout(postTimeout);
                        }
                        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                        var returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            return;
                        }
                        if ("page_id" in returnArray) {
                            $("#cancel_build").html("Create Another");
                            $("#save_page").html("Save Changes");
                            $("#_page_preview").attr("src", "/" + returnArray['link_name']);
                            $("#_edit_form").find("input,select").each(function () {
                                if ($(this).is("input[type=checkbox]")) {
                                    $(this).data("crc_value", getCrcValue($(this).prop("checked") ? "1" : "0"));
                                } else {
                                    $(this).data("crc_value", getCrcValue($(this).val()));
                                }
                            });
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&primary_id=" + returnArray['page_id'];
                        }
                    });
                    postTimeout = setTimeout(function () {
                        postTimeout = null;
                        $("#_build_iframe").off("load");
                        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                        displayErrorMessage("<?= getSystemMessage("not_responding") ?>");
                    }, <?= (empty($GLOBALS['gDefaultAjaxTimeout']) || !is_numeric($GLOBALS['gDefaultAjaxTimeout']) ? "5000" : $GLOBALS['gDefaultAjaxTimeout']) ?>);
                }
                return false;
            }

            function getPagePatternPreview() {
                var pageId = $(".page-pattern-button.selected").data("page_id");
                if (empty(pageId)) {
                    $("#_pattern_page_preview_outer_wrapper").addClass("hidden");
                    return;
                }
                $("#_pattern_page_preview_outer_wrapper").removeClass("hidden");
                var linkUrl = $(".page-pattern-button.selected").data("link_url");
                $("#_pattern_page_preview").attr("src", linkUrl);
            }
        </script>
		<?php
	}

	function beforeSaveChanges(&$nameValues) {
		foreach ($nameValues as $fieldName => $fieldContent) {
			$nameValues[$fieldName] = processBase64Images($fieldContent);
		}
		return true;
	}

	function internalCSS() {
		?>
        <style>
            <?php
	if ($_GET['url_page'] == "new" || $_GET['url_page'] == "show") {
		?>
            #_add_button {
                display: none;
            }
            <?php } ?>
            <?php if ($_GET['url_page'] == "new") { ?>
            #_list_button {
                display: none;
            }
            <?php } ?>
            #_list_actions {
                display: none;
            }
            #_list_search_control {
                display: none;
            }
            #_delete_button {
                display: none;
            }
            #_save_button {
                display: none;
            }

            #_choose_pattern {
                padding: 20px 40px;
                background-color: rgb(240, 240, 240);
                margin-bottom: 20px;
            }
            #_page_patterns {
                display: flex;
                width: 100%;
                flex-wrap: wrap;
            }
            #_page_patterns div {
                flex: 0 0 33%;
                padding: 20px;
                display: inline-block;
            }
            .page-pattern-button {
                width: 100%;
                height: 100%;
                padding-top: 20px;
                padding-bottom: 20px;
            }
            .page-pattern-button.selected {
                background-color: rgb(100, 200, 100);
            }

            .preview-wrapper {
                height: 700px;
                background-color: rgb(220, 220, 220);
                padding: 20px;
                margin: 0 0 20px 0;
            }
            .preview-controls span {
                padding: 10px 20px;
                text-align: center;
                cursor: pointer;
            }
            .preview-controls span.selected {
                background-color: rgb(220, 220, 220);
            }
            .page-preview-iframe {
                background-color: rgb(255, 255, 255);
                height: 100%;
            }
            .page-preview-iframe.desktop {
                width: 100%;
            }
            .page-preview-iframe.tablet {
                width: 750px;
            }
            .page-preview-iframe.mobile {
                width: 400px;
            }

            #_page_preview_outer_wrapper {
                margin-top: 40px;
            }

            div#_builder_section {
                margin-left: 1%;
                background: rgb(240, 240, 240);
                padding: 20px;
                margin-right: 1%;
            }

            #_build_iframe {
                display: none;
                border: 1px solid rgb(100, 100, 100);
                height: 400px;
                width: 100%;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_build_iframe" name="build_iframe"></iframe>
		<?php
	}
}

$pageObject = new ThisPage("pages");
$pageObject->displayPage();
