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

$GLOBALS['gPageCode'] = "TEMPLATEMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			if ($GLOBALS['gPermissionLevel'] > _READONLY) {
				if (empty($_GET['simplified'])) {
					$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("label" => getLanguageText("Duplicate"),
						"disabled" => false)));
				} else if ($GLOBALS['gClientId'] != $GLOBALS['gDefaultClientId']) {
					if ($GLOBALS['gUserRow']['superuser_flag']) {
						$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("update_template" => array("label" => getLanguageText("Update"), "disabled" => false)));
					}
				}
			}
			if (!empty($_GET['simplified'])) {
				$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add"));
			}
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("template_banner_groups", "data_type", "custom");
		$this->iDataSource->addColumnControl("template_banner_groups", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("template_banner_groups", "form_label", "Banner Groups");
		$this->iDataSource->addColumnControl("template_banner_groups", "control_table", "banner_groups");
		$this->iDataSource->addColumnControl("template_banner_groups", "links_table", "template_banner_groups");

		$this->iDataSource->addColumnControl("template_images", "data_type", "custom");
		$this->iDataSource->addColumnControl("template_images", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("template_images", "form_label", "Images");
		$this->iDataSource->addColumnControl("template_images", "list_table", "template_images");

		$this->iDataSource->addColumnControl("template_menus", "data_type", "custom");
		$this->iDataSource->addColumnControl("template_menus", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("template_menus", "form_label", "Menus");
		$this->iDataSource->addColumnControl("template_menus", "control_table", "menus");
		$this->iDataSource->addColumnControl("template_menus", "links_table", "template_menus");

		$this->iDataSource->addColumnControl("template_fragments", "data_type", "custom");
		$this->iDataSource->addColumnControl("template_fragments", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("template_fragments", "form_label", "Fragments");
		$this->iDataSource->addColumnControl("template_fragments", "control_table", "fragments");
		$this->iDataSource->addColumnControl("template_fragments", "links_table", "template_fragments");

		$this->iDataSource->addColumnControl("sass_headers", "data_type", "text");
		$this->iDataSource->addColumnControl("sass_headers", "readonly", true);
		$this->iDataSource->addColumnControl("sass_headers", "form_label", "SASS Header Content");
		$this->iDataSource->addColumnControl("sass_headers", "help_label", "Click on a Sass Header above to show content");

		$this->iDataSource->addColumnControl("template_custom_fields", "choice_where", "custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'TEMPLATES')");
		$this->iDataSource->addColumnControl("template_custom_fields", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("template_custom_fields", "control_table", "custom_fields");
		$this->iDataSource->addColumnControl("template_custom_fields", "data_type", "custom");
		$this->iDataSource->addColumnControl("template_custom_fields", "form_label", "Custom Fields");
		$this->iDataSource->addColumnControl("template_custom_fields", "help_label", "Fields presented to customer when onboarding");
		$this->iDataSource->addColumnControl("template_custom_fields", "links_table", "template_custom_fields");

		if (empty($_GET['simplified'])) {
			$this->iDataSource->getPrimaryTable()->setLimitByClient(false);
			$this->iDataSource->setFilterWhere("client_id in (1," . $GLOBALS['gClientId'] . ")");
		} else {
			$this->iDataSource->setFilterWhere("client_id = " . $GLOBALS['gClientId']);
		}
		$this->iDataSource->getPrimaryTable()->setSubtables(array("template_data_uses"));
		if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
			$templateId = getFieldFromId("template_id", "templates", "template_id", $_GET['primary_id'], "client_id is not null");
			if (empty($templateId)) {
				return;
			}
			$resultSet = executeQuery("select * from templates where template_id = ?", $templateId);
			$templateRow = getNextRow($resultSet);
			$originalTemplateCode = $templateRow['template_code'];
			$subNumber = 1;
			$queryString = "";
			foreach ($templateRow as $fieldName => $fieldData) {
				if (empty($queryString)) {
					$templateRow[$fieldName] = "";
				}
				if ($fieldName == "client_id") {
					$templateRow[$fieldName] = $GLOBALS['gClientId'];
				}
				$queryString .= (empty($queryString) ? "" : ",") . "?";
			}
			$newTemplateId = "";
			$templateRow['description'] .= " Copy";
			while (empty($newTemplateId)) {
				$templateRow['template_code'] = $originalTemplateCode . "_" . $subNumber;
				$resultSet = executeQuery("select * from templates where template_code = ?", $templateRow['template_code']);
				if ($row = getNextRow($resultSet)) {
					$subNumber++;
					continue;
				}
				$resultSet = executeQuery("insert into templates values (" . $queryString . ")", $templateRow);
				if ($resultSet['sql_error_number'] == 1062) {
					$subNumber++;
					continue;
				}
				$newTemplateId = $resultSet['insert_id'];
			}
			$_GET['primary_id'] = $newTemplateId;
			$subTables = array("template_data_uses");
			foreach ($subTables as $tableName) {
				$resultSet = executeQuery("select * from " . $tableName . " where template_id = ?", $templateId);
				while ($row = getNextRow($resultSet)) {
					$queryString = "";
					foreach ($row as $fieldName => $fieldData) {
						if (empty($queryString)) {
							$row[$fieldName] = "";
						}
						$queryString .= (empty($queryString) ? "" : ",") . "?";
					}
					$row['template_id'] = $newTemplateId;
					$insertSet = executeQuery("insert into " . $tableName . " values (" . $queryString . ")", $row);
				}
			}
		}
		$this->iDataSource->addColumnControl("template_text_chunks", "data_type", "custom");
		$this->iDataSource->addColumnControl("template_text_chunks", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("template_text_chunks", "list_table", "template_text_chunks");

		$this->iDataSource->addColumnControl("template_sass_headers", "data_type", "custom");
		$this->iDataSource->addColumnControl("template_sass_headers", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("template_sass_headers", "links_table", "template_sass_headers");
		$this->iDataSource->addColumnControl("template_sass_headers", "control_table", "sass_headers");
		$this->iDataSource->addColumnControl("template_sass_headers", "form_label", "SASS Headers");
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
            case "update_template":
                $templateId = getFieldFromId("template_id","templates","template_id",$_GET['template_id']);
                if (empty($templateId)) {
                    $returnArray['error_message'] = "Unable to update template";
	                ajaxResponse($returnArray);
	                break;
                }
                $primaryTemplateCode = getFieldFromId("content","template_text_chunks","template_id",$templateId,"template_text_chunk_code = 'PRIMARY_TEMPLATE_CODE'");
                if (empty($primaryTemplateCode)) {
	                $returnArray['error_message'] = "No original template found";
	                ajaxResponse($returnArray);
	                break;
                }
                $primaryTemplateId = getFieldFromId("template_id","templates","template_code",$primaryTemplateCode,"client_id = " . $GLOBALS['gDefaultClientId']);
	            if (empty($primaryTemplateId)) {
		            $returnArray['error_message'] = "No original template found";
		            ajaxResponse($returnArray);
		            break;
	            }
                $resultSet = executeQuery("select css_content, javascript_code, content from templates where template_id = ?",$primaryTemplateId);
                if ($row = getNextRow($resultSet)) {
                    $returnArray = $row;
                }
	            ajaxResponse($returnArray);
                break;
			case "get_sass_headers":
				$returnArray['sass_headers'] = getFieldFromId("content", "sass_headers", "sass_header_id", $_GET['sass_header_id']);
				ajaxResponse($returnArray);
				break;
			case "check_requirements":
				$templateContent = str_replace('"', "'", $_POST['content']);
				$requiredElements = array(
					array("line" => "%crudIncludes%", "before" => "%headerIncludes%"),
					array("line" => "%crudJavascript%", "before" => array("%onLoadJavascript%", "%javascript%")),
					array("contains" => "page-form-buttons", "supercedes" => "page-buttons"),
					array("contains" => "page-list-buttons", "supercedes" => "page-buttons"),
					array("contains" => "page-buttons", "supercedes" => array("page-form-buttons", "page-list-buttons")),
					array("contains" => "id='_filter_text'"),
					array("contains" => "page-search-button"),
					array("contains" => "id='_error_message'"),
					array("contains" => "page-previous-button"),
					array("contains" => "page-next-button"),
					array("contains" => "<div id='_management_content'"));
				$templateLines = getContentLines($templateContent);
				$previousLines = array();
				foreach ($templateLines as $templateLine) {
					foreach ($requiredElements as $index => $elementInfo) {
						$beforeRequirementsMet = true;
						if (array_key_exists("before", $elementInfo)) {
							$beforeElements = $elementInfo['before'];
							if (!is_array($beforeElements)) {
								$beforeElements = array($beforeElements);
							}
							foreach ($beforeElements as $checkElement) {
								if (in_array($checkElement, $previousLines)) {
									$beforeRequirementsMet = false;
									break;
								}
							}
						}
						if (array_key_exists("line", $elementInfo)) {
							if ($templateLine == $elementInfo['line']) {
								if (!array_key_exists("requirement_met", $elementInfo)) {
									$requiredElements[$index]['requirement_met'] = $beforeRequirementsMet;
								}
							}
						} else if (array_key_exists("contains", $elementInfo)) {
							if (strpos($templateLine, $elementInfo['contains']) !== false) {
								if (!array_key_exists("requirement_met", $elementInfo)) {
									$requiredElements[$index]['requirement_met'] = $beforeRequirementsMet;
								}
							}
						}
						if ($requiredElements[$index]['requirement_met'] && array_key_exists("supercedes", $elementInfo)) {
							$supercedes = $elementInfo['supercedes'];
							if (!is_array($supercedes)) {
								$supercedes = array($supercedes);
							}
							foreach ($supercedes as $containPart) {
								foreach ($requiredElements as $thisIndex => $thisElement) {
									if ($thisElement['contains'] == $containPart) {
										$requiredElements[$thisIndex]['requirement_met'] = true;
									}
								}
							}
						}
					}
					$previousLines[] = $templateLine;
				}
				$results = "";
				foreach ($requiredElements as $elements) {
					if (!$elements['requirement_met']) {
						$elementString = $elements['line'] . $elements['contains'];
						$results .= (empty($results) ? "Missing or misplaced elements: " : ", ") . $elementString;
					}
				}
				$returnArray['requirements_results'] = (empty($results) ? "The template appears to contain all requirements." : $results);
				$returnArray['results'] = (empty($results) ? "OK" : "ERROR");
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
			<?php
			if ($GLOBALS['gPermissionLevel'] > _READONLY) {
			?>
            $(document).on("tap click", "#_duplicate_button", function () {
                if ($("#primary_id").val() != "") {
                    if (changesMade()) {
                        askAboutChanges(function () {
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $("#primary_id").val();
                        });
                    } else {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $("#primary_id").val();
                    }
                }
                return false;
            });
            $(document).on("tap click", "#_update_template_button", function () {
                $('#update_template_dialog').dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: false,
                    resizable: true,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 1000,
                    title: 'Update Template',
                    buttons: {
                        Update: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_template&template_id=" + $("#primary_id").val(), function (returnArray) {
                                for (var i in returnArray) {
                                    $("#" + i).val(returnArray[i]);
                                }
                                $("#javascript_code.ace-javascript-editor,#css_content.ace-css-editor,#content.ace-html-editor").each(function () {
                                    var elementId = $(this).data("element_id");
                                    if (empty(elementId)) {
                                        return true;
                                    }
                                    var javascriptEditor = ace.edit(elementId + "-ace_editor");
                                    if ($("#" + elementId).length > 0 && !empty(javascriptEditor)) {
                                        javascriptEditor.setValue($("#" + elementId).val(), 1);
                                    }
                                });
                            });
                            $("#update_template_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#update_template_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
			<?php } ?>
            $(document).on("click", "#_template_sass_headers_row li", function () {
                const sassHeaderId = $(this).data("id");
                $("#sass_headers").val("");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_sass_headers&sass_header_id=" + sassHeaderId, function (returnArray) {
                    if ("sass_headers" in returnArray) {
                        $("#sass_headers").val(returnArray['sass_headers']);
                    }
                });
            });
            $("#placeholder_list").click(function () {
                $('#placeholder_list_dialog').dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: false,
                    resizable: true,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 1000,
                    title: 'Placeholders',
                    buttons: {
                        Cancel: function (event) {
                            $("#placeholder_list_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $("#crud_documentation").click(function () {
                $('#crud_documentation_dialog').dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: false,
                    resizable: true,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 1000,
                    title: 'CRUD Requirements',
                    buttons: {
                        Cancel: function (event) {
                            $("#crud_documentation_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $("#check_requirements").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_requirements", $("#_edit_form").serialize(), function (returnArray) {
                    if ("requirements_results" in returnArray) {
                        $("#_requirements_results").html(returnArray['requirements_results']);
                        if (returnArray['results'] == "OK") {
                            $("#_requirements_results").addClass("info-message").removeClass("error-message");
                        } else {
                            $("#_requirements_results").removeClass("info-message").addClass("error-message");
                        }
                    }
                });
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function afterGetRecord() {
				<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                if ($("#primary_id").val() == "") {
                    disableButtons($("#_duplicate_button"));
                } else {
                    enableButtons($("#_duplicate_button"));
                }
				<?php } ?>
                $("#sass_headers").val("");
                $("#_template_sass_headers_row li:first-child").trigger("click");
            }
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #javascript_code {
                height: 500px;
                max-width: 100%;
                width: 100%;
            }

            #css_content {
                height: 500px;
                max-width: 100%;
                width: 100%;
            }

            #content {
                height: 600px;
                max-width: 100%;
                width: 100%;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="update_template_dialog" class="dialog-box">
            <p>This will update the template from the original template. Any custom changes made in this template will be lost. Are you sure you want to proceed.</p>
        </div>
        <div id="placeholder_list_dialog" class="dialog-box">
            <h2>Placeholders</h2>
            <p>These placeholders should be the only thing on the line in the template content. The system will substitute appropriate code for the placeholder. For HTML validation, the placeholders can be wrapped by an HTML comment tags. So, &lt;!-- %crudJavascript% --&gt; is the same as %crudJavascript%.</p>
            <ul>
                <li>%crudIncludes% - Only used in a template set to use CRUD capabilities</li>
                <li>%crudJavascript% - Only used in a template set to use CRUD capabilities</li>
                <li>%cssFile:/css/xxxxxx.css% - create an import for the coreware CSS file</li>
                <li>%cssFileCode:css_file_code% - import CSS from database with code "css_file_code"</li>
                <li>%currentDate% - Is simply replaced by the current date in m/d/Y format.</li>
                <li>%currentYear% - Is simply replaced by the current year. Typically used in copyright.</li>
                <li>%getAnalyticsCode% - Analytics code for the page</li>
                <li>%getMenu:menu_id[,name=value]% - Display the menu code content, can include name value pairs after menu code</li>
                <li>%getMenuByCode:menu_code[,name=value]% - Display the menu code content, can include name value pairs after menu code</li>
                <li>%getPageTitle% - set the page title</li>
                <li>%headerIncludes% - header include statements for the page</li>
                <li>%hiddenElements% - Hidden elements, such as dialog boxes</li>
                <li>%ifAdmin% - Code following, until %endif% is only displayed if a user is logged in and is an administrator.</li>
                <li>%ifLoggedIn% - Code following, until %endif% is only displayed if a user is logged in.</li>
                <li>%ifNotLoggedIn% - Code following, until %endif% is only displayed if no user is logged in.</li>
                <li>%ifPageData:page_data_code% - Code following, until %endif% is only displayed if the page data with code 'page_data_code' has a value.</li>
                <li>%internalCSS% - Page CSS</li>
                <li>%javascript% - javascript functions</li>
                <li>%javascriptFile:/js/xxxxxx.js% - create an import for the coreware JS file</li>
                <li>%jqueryTemplates% - JQuery templates for duplicating elements</li>
                <li>%mainContent% - The main content of the page</li>
                <li>%metaDescription% - meta description section from the page</li>
                <li>%metaKeywords% - meta keywords section from the page</li>
                <li>%method:templateAddendumMethod% - Run a method from the template addendum</li>
                <li>%onLoadJavascript% - Javascript after the page loads</li>
                <li>%pageData:pageDataCode% - Display page data</li>
                <li>%pageDescription% - Replaced by the page meta description or, if there is none, just the page description</li>
                <li>%pageLinkUrl% = The link url for the current page.</li>
                <li>%pageTitle% - Replaced by the page description</li>
                <li>%postIframe% - hidden iFrame for post submissions</li>
                <li>%userDisplayName% - The full name of the currently logged in user.</li>
                <li>%userHomeLink% - The home link of the logged in user. If no one is logged in, it is set to "/".</li>
                <li>%userImageFilename% - Filename of the user's stored image</li>
            </ul>
        </div>
        <div id="crud_documentation_dialog" class="dialog-box">
            <h2>Coreware CRUD elements</h2>
            <p>In order to successfully used the CRUD capabilities, these elements need to be in the template:</p>
            <ul>
                <li>%crudIncludes% - This will include all the requirements necessary for these capabilities to function. This placeholder needs to appear before %headerIncludes%.</li>
                <li>%crudJavascript% - Javascript code needed to implement capabilities. Needs to appear before %onLoadJavascript% and before %javascript%.</li>
                <li>page-form-buttons - At least one element, most likely a div, should have this class so that the buttons for the form can be displayed.</li>
                <li>page-list-buttons - At least one element, most likely a div, should have this class so that the buttons for the list can be displayed.</li>
                <li>page-buttons - If an element exists with this class, it will substitute for the previous two and contain buttons for both the list and form.</li>
                <li>page-action-selector - An element with this class will be filled with the Action selector used in the list page.</li>
                <li>_filter_text - An input text field with this ID needs to be on the page so the user can search.</li>
                <li>page-search-button - This element is used as a search button after text is typed into the search text field.</li>
                <li>_error_message - Some type of text element (span, paragraph, etc) needs to have this ID so that error messages can be displayed for the user.</li>
                <li>page-previous-button - One or more elements need this class so the user can go to the previous record or page.</li>
                <li>page-next-button - One or more elements need this class so the user can go to the next record or page.</li>
                <li>_management_content - A div with this ID needs to wrap around all the input elements of the page <span class="highlighted-text">except</span> that it should not include the %hiddenElements% placeholder. Typically, this div would wrap around the header and main contents of the page.</li>
            </ul>
            <p>In addition to these required elements, the following elements are strongly recommended:</p>
            <ul>
                <li>%getMenuByCode:admin_menu% - This will display the admin menu. Other menus can be used as an alternative, but if the template is intended for administrators, "admin_menu" is recommended.</li>
                <li>page-login - Some link or button with this class to let the user log in. These elements also need the class "user-not-logged-in" so they don't appear when a user is logged in.</li>
                <li>page-logout - Some link or button with this class to let the user log out. These elements also need the class "user-logged-in" so they don't appear when a user is not logged in.</li>
                <li>page-first-record-display - Part of the display for number of records, this is the number of the first record in the list.</li>
                <li>page-last-record-display - Part of the display for number of records, this is the number of the last record in the list.</li>
                <li>page-row-count - Part of the display for number of records, this is the total number of records in the list.</li>
                <li>page-select-count - Part of the display for number of records, this is the number of selected records.</li>
                <li>page-record-number - Part of the display for number of records, this is the number of the current record.</li>
                <li>page-record-display - this class should be on any element that has to do with the display of the record number, including the previous 5 in this list and the next & previous buttons.</li>
                <li>page-heading - any element with this class will be filled in by the core software with the title of the page</li>
                <li>%favicon% - This will insert into the head code to add a favicon to the URL. It will either be a standard Coreware favicon or use code in a fragment with code 'MANAGEMENT_FAVICON'.</li>
            </ul>
        </div>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$ignoreCSSErrors = getFieldFromId("content", "template_text_chunks", "template_text_chunk_code", "IGNORE_CSS_ERRORS", "template_id = ?", $returnArray['primary_id']['data_value']);
		if (empty($ignoreCSSErrors) && !empty($returnArray['css_content']['data_value'])) {
			$rawCssContent = "";
			$resultSet = executeQuery("select * from sass_headers join template_sass_headers using (sass_header_id) where template_id = ?", $returnArray['primary_id']['data_value']);
			while ($row = getNextRow($resultSet)) {
				$rawCssContent .= $row['content'] . "\n";
			}
			$rawCssContent .= $returnArray['css_content']['data_value'];
			$scss = new ScssPhp\ScssPhp\Compiler();
			try {
				$cssContentLines = getContentLines($rawCssContent);
				$thisChunk = "";
				foreach ($cssContentLines as $thisLine) {
					if (substr($thisLine, 0, 1) == "%" && substr($thisLine, -1) == "%") {
						continue;
					}
					$thisChunk .= $thisLine . "\n";
				}
				$cssContent = $scss->compile($thisChunk);
			} catch (Exception $e) {
				$returnArray['error_message'] = "Style processing error: " . $e->GetMessage();
			}
		}
		$returnArray['_permission'] = array("data_value" => ($returnArray['client_id']['data_value'] == $GLOBALS['gClientId'] || $GLOBALS['gUserRow']['superuser_flag'] || empty($returnArray['primary_id']['data_value']) ? $GLOBALS['gPermissionLevel'] : _READONLY));
		$returnArray['_requirements_results'] = array("data_value" => "");
	}

	function beforeSaveChanges(&$nameValues) {
		$importantChunk = $this->getPageTextChunk("allow_important");
		if (strpos($nameValues['css_content'], "!important") !== false && empty($importantChunk)) {
			return "Do not use '!important' in a template";
		}
		if (strpos($nameValues['css_content'], "download.php") !== false) {
			return "Do not use download.php in a template";
		}
		if (strpos($nameValues['content'], "download.php") !== false) {
			return "Do not use download.php in a template";
		}
		if (strpos($nameValues['javascript_code'], "download.php") !== false) {
			return "Do not use download.php in a template";
		}
		while (strpos($nameValues['css_content'], "!  ") !== false) {
			$nameValues['css_content'] = str_replace("!  ", "! ", $nameValues['css_content']);
		}
		if (strpos($nameValues['css_content'], "! important") !== false && empty($importantChunk)) {
			return "Do not use '!important' in a template";
		}
		$ignoreCSSErrors = getFieldFromId("content", "template_text_chunks", "template_text_chunk_code", "IGNORE_CSS_ERRORS", "template_id = ?", $nameValues['primary_id']);
		if (empty($ignoreCSSErrors)) {
			$rawCssContent = "";
			$rawSassHeaderIds = explode(",", $nameValues['template_sass_headers']);
            $sassHeaderIds = array();
            foreach ($rawSassHeaderIds as $thisSassHeaderId) {
	            if (!empty($thisSassHeaderId) && is_numeric($thisSassHeaderId)) {
		            $sassHeaderIds[] = $thisSassHeaderId;
	            }
            }
            if (!empty($sassHeaderIds)) {
	            $resultSet = executeQuery("select * from sass_headers where client_id = ? and sass_header_id in (" . implode(",", $sassHeaderIds) . ")", $GLOBALS['gClientId']);
	            while ($row = getNextRow($resultSet)) {
		            $rawCssContent .= $row['content'] . "\n";
	            }
            }
			$rawCssContent .= $nameValues['css_content'];
			if (!empty($rawCssContent)) {
				$scss = new ScssPhp\ScssPhp\Compiler();
				try {
					$cssContentLines = getContentLines($rawCssContent);
					$thisChunk = "";
					foreach ($cssContentLines as $thisLine) {
						if (substr($thisLine, 0, 1) == "%" && substr($thisLine, -1) == "%") {
							continue;
						}
						$thisChunk .= $thisLine . "\n";
					}
					$cssContent = $scss->compile($thisChunk);
				} catch (Exception $e) {
					return ("Style processing error: " . $e->GetMessage());
				}
			}
		}
		$clientId = getFieldFromId("client_id", "templates", "template_id", $nameValues['primary_id']);
		return (empty($nameValues['primary_id']) || $GLOBALS['gClientId'] == $clientId || $GLOBALS['gUserRow']['superuser_flag'] ? true : "Permission Denied");
	}

	function afterSaveDone($nameValues) {
		removeCachedData("page_contents", "*");
		removeCachedData("template_row", $nameValues['primary_id'],true);
	}
}

$pageObject = new ThisPage("templates");
$pageObject->displayPage();
