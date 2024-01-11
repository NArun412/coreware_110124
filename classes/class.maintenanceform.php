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

/**
 * class MaintenanceForm
 *
 * This class generates a form for editing the contents of a record. Typically, this would be for a single table, but a
 *    joined table is also an option. The form generator uses controls from the page to create the input elements.
 *
 * @author Kim D Geiger
 */
class MaintenanceForm extends MaintenancePage {

	private $iNoGetChoicesTables = array("donations", "contacts", "images");

	function pageElements() {
	}

	/**
	 *    function pageHeader
	 *
	 *    Create the page header. For the form, this will include buttons for the actions that can be executed in the form.
	 *    Return true so that the template does not include its header.
	 *
	 * @return true
	 */
	function pageHeader($pageHeaderFile = "") {
		if ($pageHeaderFile && file_exists($GLOBALS['gDocumentRoot'] . "/classes/" . $pageHeaderFile)) {
			include_once($pageHeaderFile);
			return true;
		}
		?>
        <div id="_form_header_div">
            <div id="_form_button_section">
				<?php
				$buttonFunctions = array(
					"save" => array("accesskey" => "s", "label" => getLanguageText("Save"), "disabled" => ($GLOBALS['gPermissionLevel'] < _READWRITE || $this->iReadonly ? true : false)),
					"add" => array("accesskey" => "a", "label" => getLanguageText("Add"), "disabled" => ($GLOBALS['gPermissionLevel'] < _READWRITE || $this->iReadonly ? true : false)),
					"delete" => array("label" => getLanguageText("Delete"), "disabled" => ($GLOBALS['gPermissionLevel'] < _FULLACCESS || $this->iReadonly ? true : false)),
					"list" => array("accesskey" => "l", "label" => getLanguageText("List"), "disabled" => false));
				$enableButtons = array_diff(array("add", "save", "delete", "list"), $this->iDisabledFunctions);
				if ($this->iAdditionalButtons) {
					foreach ($this->iAdditionalButtons as $buttonCode => $buttonInfo) {
						$buttonFunctions[$buttonCode] = $buttonInfo;
						$enableButtons[] = $buttonCode;
					}
				}
				$this->displayButtons($enableButtons, $this->iReadonly, $buttonFunctions);
				?>
            </div>
            <div id="_previous_section">
                <span id="_previous_button"><span class='fa fa-chevron-left'></span>&nbsp;Prev</span>
            </div>
            <div id="_next_section">
                <span id="_next_button">Next&nbsp;<span class='fa fa-chevron-right'></span></span>
            </div>
            <div id="_record_number_section">Data Record <span id="_record_number">0</span> of <span id="_row_count">0</span></div>
        </div>
		<?php
		return true;
	}

	/**
	 *    function mainContent
	 *
	 *    Generate and display the HTML markup for the main content of the form. Start by determining what columns are to
	 *    be included in the form. Use the column objects to generate the form.
	 */
	function mainContent() {
		$primaryKeyColumnName = $this->iDataSource->getPrimaryTable()->getName() . "." . $this->iDataSource->getPrimaryTable()->getPrimaryKey();
		?>
        <form id="_edit_form" name="_edit_form"<?= ($this->iFileUpload ? " enctype='multipart/form-data'" : "") ?>>
			<?php
			$formColumns = array();

			# Get all columns from the data source
			$columns = $this->iDataSource->getColumns();
			foreach ($columns as $columnName => $thisColumn) {

# display the hidden columns
				if ($thisColumn->getControlValue('data_type') == "hidden") {
					?>
                    <input type="hidden" name="<?= $thisColumn->getControlValue('column_name') ?>" id="<?= $thisColumn->getControlValue('column_name') ?>" value=""/>
					<?php
					$this->addExcludeFormColumn($columnName);
					continue;
				}
				if (in_array($columnName, $this->iExcludeFormColumns) && $columnName != $primaryKeyColumnName) {
					continue;
				}

# If the column is a select dropdown, get the choices. If the field is not required and there are no choices, exclude it.
				if ($thisColumn->getControlValue("data_type") == "select") {
					$referencedTable = $thisColumn->getReferencedTable();
					if (!in_array($referencedTable, $this->iNoGetChoicesTables)) {
						$choices = $thisColumn->getChoices($this->iPageObject);
						if ((empty($choices) || count($choices) == 0) && !$thisColumn->getControlValue('not_null')) {
							$this->addExcludeFormColumn($columnName);
						}
					}
				}

# If the column is not excluded, add it to the list of columns to be included in the form
				$formColumns[] = $columnName;
			}
			?>
			<?php if ($GLOBALS['gDevelopmentServer']) { ?>
                <input type="hidden" name="development_server" id="development_server" value="true"/>
			<?php } ?>
			<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
                <input type="hidden" name="superuser_logged_in" id="superuser_logged_in" value="true"/>
			<?php } ?>
            <input type="hidden" name="primary_id" id="primary_id" value=""/>
            <input type="hidden" name="version" id="version" value=""/>
            <input type="hidden" name="_add_hash" id="_add_hash" value=""/>
            <input type="hidden" name="_previous_primary_id" id="_previous_primary_id" value=""/>
            <input type="hidden" name="_next_primary_id" id="_next_primary_id" value=""/>
            <input type="hidden" name="_permission" id="_permission" value="<?= $GLOBALS['gPermissionLevel'] ?>"/>
			<?php if ($this->iDataSource && $this->iDataSource->getJoinTable()) { ?>
                <input type="hidden" name="<?= $this->iDataSource->getJoinTable()->getPrimaryKey() ?>" id="<?= $this->iDataSource->getJoinTable()->getPrimaryKey() ?>" value=""/>
			<?php } ?>
			<?php

			# Default form line

			$defaultFormLineArray = array();
			$filename = $GLOBALS['gDocumentRoot'] . "/forms/defaultformline.frm";
			$handle = fopen($filename, 'r');
			while ($thisLine = fgets($handle)) {
				$defaultFormLineArray[] = $thisLine;
			}
			fclose($handle);

			# get the form used by this page. If no form exists exclusively for this page, use the generic maintenance.frm form
			$formContentArray = array();
			if (!empty($GLOBALS['gPageRow']['script_filename']) && file_exists($GLOBALS['gDocumentRoot'] . "/forms/" . str_replace(".php", ".frm", $GLOBALS['gPageRow']['script_filename']))) {
				$formTemplate = str_replace(".php", ".frm", $GLOBALS['gPageRow']['script_filename']);
			} else {
				$formTemplate = "maintenance.frm";
			}
			$filename = $GLOBALS['gDocumentRoot'] . "/forms/" . $formTemplate;
			$handle = fopen($filename, 'r');
			while ($thisLine = fgets($handle)) {
				$thisLine = trim($thisLine);
				if ($thisLine == "%basic_form_line%") {
					$formContentArray = array_merge($formContentArray, $defaultFormLineArray);
				} else {
					$formContentArray[] = $thisLine;
				}
			}
			fclose($handle);

			# Generally, the columns will appear in the natural order of the primary table columns, then joined table columns, then additional columns.
			# If the developer specifies a different sort order, this routine uses that sort order
			if (count($this->iFormSortOrder) > 0) {
				$newFormColumns = array();
				foreach ($this->iFormSortOrder as $columnName) {
					if (in_array($columnName, $formColumns)) {
						$newFormColumns[] = $columnName;
					}
				}
				foreach ($formColumns as $columnName) {
					if (!in_array($columnName, $newFormColumns)) {
						$newFormColumns[] = $columnName;
					}
				}
				$formColumns = $newFormColumns;
			}

			# Iterate through the lines of the form to generate the HTML markup of the form
			$repeatStartLine = 0;
			$contentIndex = 0;
			$usedFormColumns = array();
			if (count($formColumns) > 0) {
				$thisColumnName = array_shift($formColumns);
				$thisColumn = $columns[$thisColumnName];
				$usedFormColumns[] = $thisColumnName;
				if ($thisColumnName == $primaryKeyColumnName) {
					$thisColumnName = array_shift($formColumns);
					$thisColumn = $columns[$thisColumnName];
					$usedFormColumns[] = $thisColumnName;
				}
			} else {
				$thisColumn = false;
			}
			$firstField = true;
			$useLine = true;
			$skipField = false;
			$ifStatements = array(true);
			$fieldList = false;
			while (true) {
				if ($contentIndex > (count($formContentArray) + 1)) {
					break;
				}
				$line = trim($formContentArray[$contentIndex]);
				$contentIndex++;
				if ($line == "%endif%") {
					array_shift($ifStatements);
					if (!$ifStatements) {
						$ifStatements = array(true);
					}
					$useLine = true;
					foreach ($ifStatements as $ifResult) {
						$useLine = $useLine && $ifResult;
					}
					continue;
				}

# an if statement in the form allows conditional inclusion of parts of the form
				if (startsWith($line, "%if:")) {
					$evalStatement = substr($line, strlen("%if:"), -1);
					if (!startsWith($evalStatement, "return ")) {
						$evalStatement = "return " . $evalStatement;
					}
					if (substr($evalStatement, -1) == "%") {
						$evalStatement = substr($evalStatement, 0, -1);
					}
					if (substr($evalStatement, -1) != ";") {
						$evalStatement .= ";";
					}
					$thisResult = eval($evalStatement);
					array_unshift($ifStatements, $thisResult);
					$useLine = $useLine && $thisResult;
					continue;
				}
				if (!$useLine) {
					continue;
				}

# Go to the next field
				if ($line == "%next field%") {
					if (count($formColumns) > 0) {
						$thisColumnName = array_shift($formColumns);
						$thisColumn = $columns[$thisColumnName];
						$usedFormColumns[] = $thisColumnName;
						if ($thisColumnName == $primaryKeyColumnName) {
							if (count($formColumns) > 0) {
								$thisColumnName = array_shift($formColumns);
								$thisColumn = $columns[$thisColumnName];
								$usedFormColumns[] = $thisColumnName;
							} else {
								$thisColumn = false;
							}
						}
					} else {
						$thisColumn = false;
					}
					continue;

# Go to a specific field.
				} else if (startsWith($line, "%field:")) {
					if ($thisColumn) {
						if ($firstField) {
							array_unshift($formColumns, $thisColumn->getControlValue("full_column_name"));
							$usedFormColumns[] = $thisColumn->getControlValue("full_column_name");
							$firstField = false;
						}
					}
					$fieldName = trim(str_replace("%", "", substr($line, strlen("%field:"))));
					if (strpos($fieldName, ",") !== false) {
						$fieldList = explode(",", $fieldName);
						$fieldName = array_shift($fieldList);
						$repeatStartLine = $contentIndex;
						$repeatCount = 9999;
					}
					if (strpos($fieldName, ".") === false) {
						if ($this->iDataSource->getPrimaryTable()->columnExists($fieldName)) {
							$fieldName = $this->iDataSource->getPrimaryTable()->getName() . "." . $fieldName;
						} else if ($this->iDataSource->getJoinTable() && $this->iDataSource->getJoinTable()->columnExists($fieldName)) {
							$fieldName = $this->iDataSource->getJoinTable()->getName() . "." . $fieldName;
						}
					}
					if (array_key_exists($fieldName, $columns)) {
						if (in_array($fieldName, $formColumns) || in_array($fieldName, $usedFormColumns)) {
							$thisColumn = $columns[$fieldName];
							$formColumns = array_diff($formColumns, array($fieldName));
							if (!in_array($fieldName, $usedFormColumns)) {
								$usedFormColumns[] = $fieldName;
							}
						} else {
							$thisColumn = false;
							$skipField = true;
						}
					} else {
						$thisColumn = false;
					}
					continue;

# Repeat this section, possibly a fixed number of times
				} else if (preg_match('/^%repeat*%/i', $line)) {
					$repeatCount = str_replace("repeat", "", str_replace("%", "", str_replace(" ", "", trim($line))));
					if (empty($repeatCount) || !is_numeric($repeatCount)) {
						$repeatCount = 9999;
					}
					$repeatStartLine = $contentIndex;
					continue;

# End repeat
				} else if ($line == "%end repeat%") {
					if (is_array($fieldList)) {
						if (count($fieldList) == 0) {
							$fieldList = false;
							$thisColumn = false;
						} else {
							$fieldName = array_shift($fieldList);
							if (strpos($fieldName, ".") === false) {
								if ($this->iDataSource->getPrimaryTable()->columnExists($fieldName)) {
									$fieldName = $this->iDataSource->getPrimaryTable()->getName() . "." . $fieldName;
								} else if ($this->iDataSource->getJoinTable() && $this->iDataSource->getJoinTable()->columnExists($fieldName)) {
									$fieldName = $this->iDataSource->getJoinTable()->getName() . "." . $fieldName;
								}
							}
							if (array_key_exists($fieldName, $columns)) {
								if (in_array($fieldName, $formColumns) || in_array($fieldName, $usedFormColumns)) {
									$thisColumn = $columns[$fieldName];
									$formColumns = array_diff($formColumns, array($fieldName));
									if (!in_array($fieldName, $usedFormColumns)) {
										$usedFormColumns[] = $fieldName;
									}
								} else {
									$thisColumn = false;
									$skipField = true;
								}
							} else {
								$thisColumn = false;
							}
						}
					}
					$repeatCount--;
					if ($repeatStartLine > 0 && $repeatCount > 0 && $thisColumn !== false) {
						$contentIndex = $repeatStartLine;
					} else {
						$repeatStartLine = 0;
					}
					continue;
# Execute a method in the page object
				} else if (startsWith($line, "%method:")) {
					$functionName = str_replace("%", "", substr($line, strlen("%method:")));
					if (strpos($functionName, ":") !== false) {
						$parameterString = substr($functionName, strpos($functionName, ":") + 1);
						$functionName = substr($functionName, 0, strpos($functionName, ":"));
					}
					if (method_exists($this->iPageObject, $functionName)) {
						if ($parameterString) {
							$this->iPageObject->$functionName($parameterString);
						} else {
							$this->iPageObject->$functionName();
						}
					}
					continue;
				}

				if ($skipField) {
					if (!$thisColumn) {
						continue;
					} else {
						$skipField = false;
					}
				}
				if ($thisColumn) {
					if ($this->iReadonly || $thisColumn->getControlValue("full_column_name") == $primaryKeyColumnName) {
						$thisColumn->setControlValue("readonly", true);
						$thisColumn->setControlValue("not_null", false);
					}
					if (!$thisColumn->getControlValue("form_label")) {
						$thisColumn->setControlValue("form_label", "");
					}
					if ($thisColumn->getControlValue('data_type') == "tinyint") {
						$line = str_replace("%form_label%", "&nbsp;", $line);
					}
					if (!$thisColumn->controlValueExists("label_class")) {
						if ($thisColumn->getControlValue('not_null') && $thisColumn->getControlValue('data_type') != "tinyint" &&
							!$thisColumn->getControlValue('readonly') && !$thisColumn->getControlValue("no_required_label")) {
							$thisColumn->setControlValue("label_class", "required-label");
						} else {
							$thisColumn->setControlValue("label_class", "");
						}
					}
					foreach ($thisColumn->getAllControlValues() as $infoName => $infoData) {
						if (is_scalar($infoData)) {
							if ($infoName != "form_label") {
								$infoData = htmlText($infoData);
							}
							$line = str_replace("%" . $infoName . "%", $infoData, $line);
						}
					}
					if (strpos($line, "%input_control%") !== false) {
						if ($thisColumn->getControlValue('data_type') == "method") {
							if (method_exists($this->iPageObject, $thisColumn->getControlValue("method_name"))) {
								$methodName = $thisColumn->getControlValue("method_name");
								$line = str_replace("%input_control%", $this->iPageObject->$methodName(), $line);
							}
						} else {
							$line = str_replace("%input_control%", $thisColumn->getControl($this->iPageObject), $line);
						}
					}
				}

# Substitute a String from the database for multi-lingual support
				if (strpos($line, "%programText:") !== false) {
					$startPosition = strpos($line, "%programText:");
					$programTextCode = substr($line, $startPosition + strlen("%programText:"), strpos($line, "%", $startPosition + 1) - ($startPosition + strlen("%programText:")));
					$programText = getLanguageText($programTextCode);
					$line = str_replace("%programText:" . $programTextCode . "%", $programText, $line);
				}

				echo $line . "\n";
			}
			?>
        </form>
		<?php
	}

	/**
	 *    function onLoadPageJavascript
	 *
	 *    Add javascript code that should be executed after the page loads
	 */
	function onLoadPageJavascript() {
		if ($this->iPageObject->onLoadPageJavascript()) {
			return;
		}
		if (empty($this->iSaveAction)) {
			$saveAction = (empty($this->iSaveUrl) ? (getPreference("MAINTENANCE_SAVE_NO_LIST", $GLOBALS['gPageRow']['page_code']) == "true" ? "getRecord($('#primary_id').val())" : "$('body').data('just_saved','true');document.location = '" . $GLOBALS['gLinkUrl'] . "?url_page=list'") : "$('body').data('just_saved','true');document.location = '" . $this->iSaveUrl . "'");
		} else {
			$saveAction = $this->iSaveAction;
		}
		$activeTab = getPreference("MAINTENANCE_ACTIVE_TAB", $GLOBALS['gPageRow']['page_code']);
		?>
        <script>
            $(function () {
                $(document).on("click",".contact-picker-open-contact",function () {
                    var fieldName = $(this).data("field_name");
                    if (fieldName != "" && fieldName != undefined) {
                        var contactId = $("#" + fieldName).val();
                        if (contactId != "" && contactId != undefined) {
                            window.open("/contactmaintenance.php?url_page=show&clear_filter=true&primary_id=" + contactId);
                        }
                    }
                    return false;
                });
                $("input,textarea,select").keypress(function (event) {
                    if (!event.metaKey && $("#_permission").val() <= "1") {
                        return false;
                    }
                });
                $(document).on("tap click", "input,textarea,select", function (event) {
                    if ($("#_permission").val() <= "1") {
                        return false;
                    }
                });
                $(document).keydown(function (event) {
                    if (event.which == 34 && $("#_next_button").is(":visible")) {
                        $("#_next_button").trigger("click");
                        return false;
                    } else if (event.which == 33 && $("#_previous_button").is(":visible")) {
                        $("#_previous_button").trigger("click");
                        return false;
                    }
                });
                $(document).on("tap click", "#_add_button", function () {
                    if ($(this).data("ignore") == "true") {
                        return false;
                    }
                    if (changesMade()) {
                        askAboutChanges(function () {
                            $("#_edit_form").clearForm();
							<?php if (empty($this->iAddUrl)) { ?>
                            getRecord("");
							<?php } else { ?>
                            $('body').data('just_saved', 'true');
                            document.location = "<?= $this->iAddUrl ?>";
							<?php } ?>
                        });
                    } else {
                        $("#_edit_form").clearForm();
						<?php if (empty($this->iAddUrl)) { ?>
                        getRecord("");
						<?php } else { ?>
                        $('body').data('just_saved', 'true');
                        document.location = "<?= $this->iAddUrl ?>";
						<?php } ?>
                    }
                    return false;
                });
                $(document).on("tap click", "#_save_button", function () {
                    if ($(this).data("ignore") == "true") {
                        return false;
                    }
                    if ($("#_permission").val() <= "1") {
                        displayErrorMessage("<?= getSystemMessage("readonly") ?>");
                        return false;
                    }
                    $(this).button("disable");
                    saveChanges(function () {
						<?= $saveAction ?>;
                        $("#_save_button").button("enable");
                    }, function () {
                        $("#_save_button").button("enable");
                    });
                    return false;
                });
                $(document).on("tap click", "#_delete_button", function () {
                    if ($(this).data("ignore_click") == "true") {
                        return false;
                    }
                    if ($("#_permission").val() <= "2") {
                        displayErrorMessage("<?= getSystemMessage("no_delete") ?>");
                        return false;
                    }
                    $('#_confirm_delete_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        title: 'Delete Record?',
                        buttons: {
                            Yes: function (event) {
                                deleteRecord();
                                $("#_confirm_delete_dialog").dialog('close');
                            },
                            Cancel: function (event) {
                                $("#_confirm_delete_dialog").dialog('close');
                            }
                        }
                    });
                    return false;
                });
                $(document).on("tap click", "#_next_button", function () {
                    if ($(this).data("ignore_click") == "true") {
                        return false;
                    }
                    if ($("#_next_primary_id").val() != "") {
                        if (changesMade()) {
                            askAboutChanges(function () {
                                getRecord($("#_next_primary_id").val());
                            });
                        } else {
                            getRecord($("#_next_primary_id").val());
                        }
                    }
                    return false;
                });
                $(document).on("tap click", "#_previous_button", function () {
                    if ($(this).data("ignore_click") == "true") {
                        return false;
                    }
                    if ($("#_previous_primary_id").val() != "") {
                        if (changesMade()) {
                            askAboutChanges(function () {
                                getRecord($("#_previous_primary_id").val());
                            });
                        } else {
                            getRecord($("#_previous_primary_id").val());
                        }
                    }
                    return false;
                });
                $(document).on("tap click", "#_list_button", function () {
                    if ($(this).data("ignore_click") == "true") {
                        return false;
                    }
                    if (changesMade()) {
                        askAboutChanges(function () {
                            $('body').data('just_saved', 'true');
                            document.location = "<?= (empty($this->iListUrl) ? $GLOBALS['gLinkUrl'] . "?url_page=list" : $this->iListUrl) ?>";
                        });
                    } else {
                        document.location = "<?= (empty($this->iListUrl) ? $GLOBALS['gLinkUrl'] . "?url_page=list" : $this->iListUrl) ?>";
                    }
                    return false;
                });
				<?php if (!empty($this->iErrorMessage)) { ?>
                displayErrorMessage("<?= htmlText($this->iErrorMessage) ?>");
				<?php } ?>
                getRecord("<?= $this->iDataSource->getPrimaryId() ?>");
				<?php if (!empty($activeTab)) { ?>
                $(".tabbed-form").tabs("option", "active", <?= $activeTab ?>);
				<?php } ?>
                $("button.disabled-button").remove();
            });
        </script>
		<?php
	}

	/**
	 *    function pageJavascript
	 *
	 *    Add javascript code to the page. These will most likely be just functions. Any code that should execute outside a function
	 *    should go in onLoadJavascript, so that it executes after the page successfully loads.
	 */
	function pageJavascript() {
		if ($this->iPageObject->pageJavascript()) {
			return;
		}
		?>
        <script>
            function deleteRecord() {
                if ($("#primary_id").val() == "") {
                    $('body').data('just_saved', 'true');
                    document.location = "<?= (empty($this->iListUrl) ? $GLOBALS['gLinkUrl'] . "?url_page=list" : $this->iListUrl) ?>";
                    return false;
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_page=show&url_action=delete_record", $("#_edit_form").serialize(), function(returnArray) {
                    if (!("error_message" in returnArray)) {
                        if ("info_message" in returnArray) {
                            displayInfoMessage(returnArray['info_message']);
                        } else {
                            displayInfoMessage("<?= getSystemMessage("RECORD_DELETED") ?>");
                        }
                        if (typeof afterDeleteRecord == "function") {
                            if (afterDeleteRecord(returnArray)) {
                                return;
                            }
                        }
                        if ($("#_next_primary_id").val() != "") {
                            getRecord($("#_next_primary_id").val());
                        } else if ($("#_previous_primary_id").val() != "") {
                            getRecord($("#_previous_primary_id").val());
                        } else {
                            $('body').data('just_saved', 'true');
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=list";
                        }
                    }
                });
            }

            function saveChanges(afterFunction, regardlessFunction) {
                if (regardlessFunction == null || regardlessFunction == undefined) {
                    regardlessFunction = function () {
                    };
                }
                if (afterFunction == null || afterFunction == undefined) {
                    afterFunction = function () {
                    };
                }
                if (typeof beforeSaveChanges == "function") {
                    if (!beforeSaveChanges()) {
                        regardlessFunction();
                        return false;
                    }
                }

                for (instance in CKEDITOR.instances) {
                    CKEDITOR.instances[instance].updateElement();
                }
                $(".editable-list").each(function () {
                    $(this).find(".editable-list-data-row").each(function () {
                        var allEmpty = true;
                        $(this).find("input,select,textarea").each(function () {
                            if (!empty($(this).val())) {
                                allEmpty = false;
                                return true;
                            }
                        });
                        if (allEmpty) {
                            $(this).remove();
                        }
                    });
                });
                if ($("#_edit_form").validationEngine('validate')) {
					<?php if ($this->iFileUpload) { ?>
                    $("body").addClass("waiting-for-ajax");
                    $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_page=show&url_action=save_changes").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").off("load");
                    $("#_post_iframe").on("load", function () {
                        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                        var returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            return;
                        }
                        if ("error_message" in returnArray) {
                            if (typeof regardlessFunction == "function") {
                                regardlessFunction();
                            }
                            regardlessFunction = "";
                        } else {
                            if ($("#primary_id").val() == "") {
                                if (!("info_message" in returnArray)) {
                                    displayInfoMessage("<?= getSystemMessage("RECORD_ADDED") ?>");
                                }
                                $("#primary_id").val(returnArray['primary_id']);
                            } else {
                                if (!("info_message" in returnArray)) {
                                    displayInfoMessage("<?= getSystemMessage("RECORD_UPDATED") ?>");
                                }
                            }
                            if (typeof afterSaveChanges == "function") {
                                if (!afterSaveChanges()) {
                                    if (typeof afterFunction == "function") {
                                        afterFunction();
                                    }
                                }
                            } else if (typeof afterFunction == "function") {
                                afterFunction();
                            }
                            afterFunction = "";
                        }
                    });
					<?php } else { ?>
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_page=show&url_action=save_changes", $("#_edit_form").serialize(), function(returnArray) {
                        if ("error_message" in returnArray) {
                            regardlessFunction();
                        } else {
                            if ($("#primary_id").val() == "") {
                                if (!("info_message" in returnArray)) {
                                    displayInfoMessage("<?= getSystemMessage("RECORD_ADDED") ?>");
                                }
                                $("#primary_id").val(returnArray['primary_id']);
                            } else {
                                if (!("info_message" in returnArray)) {
                                    displayInfoMessage("<?= getSystemMessage("RECORD_UPDATED") ?>");
                                }
                            }
                            if (typeof afterSaveChanges == "function") {
                                if (!afterSaveChanges()) {
                                    if (typeof afterFunction == "function") {
                                        afterFunction();
                                    }
                                }
                            } else if (typeof afterFunction == "function") {
                                afterFunction();
                            }
                        }
                    }, function(returnArray) {
                        regardlessFunction();
                    });
					<?php } ?>
                } else {
                    regardlessFunction();
                }
            }

            function ignoreChanges(afterFunction) {
                afterFunction();
            }

            function getRecord(primaryId) {
                disableButtons();
				<?php
				$clearFilter = (!empty($_GET['clear_filter']));
				?>
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_page=show&url_action=get_record<?= ($clearFilter ? "&clear_filter=true" : "") ?>&primary_id=" + primaryId, function(returnArray) {
                    if ("return_to_list" in returnArray) {
                        setTimeout(function () {
                            $('body').data('just_saved', 'true');
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=list";
                        }, 500);
                        return false;
                    }
                    if (typeof beforeGetRecord == "function") {
                        if (!beforeGetRecord(returnArray)) {
                            setTimeout(function () {
                                $('body').data('just_saved', 'true');
                                document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=list";
                            }, 100);
                            return false;
                        }
                    }
                    $("#_edit_form").clearForm();
                    if ("select_values" in returnArray) {
                        for (var i in returnArray['select_values']) {
                            if (!$("#" + i).is("select")) {
                                continue;
                            }
                            $("#" + i + " option").each(function () {
                                if ($(this).data("inactive") == "1") {
                                    $(this).remove();
                                }
                            });
                            for (var j in returnArray['select_values'][i]) {
                                var valueFound = false;
                                $("#" + i + " option").each(function () {
                                    if ($(this).val() == returnArray['select_values'][i][j]['key_value']) {
                                        valueFound = true;
                                        return false;
                                    }
                                });
                                if (!valueFound) {
                                    var inactive = ("inactive" in returnArray['select_values'][i][j] ? returnArray['select_values'][i][j]['inactive'] : "0");
                                    var thisOption = $("<option></option>").attr("value", returnArray['select_values'][i][j]['key_value']).text(returnArray['select_values'][i][j]['description']).data("inactive", inactive);
                                    for (var k in returnArray['select_values'][i][j]) {
                                        if (k.substring(0, 5) == "data-") {
                                            thisOption.data(k.substring(5), returnArray['select_values'][i][j][k]);
                                        }
                                    }
                                    $("#" + i).append(thisOption);
                                }
                            }
                        }
                    }
                    $(".selection-control-filter").val("");
                    $(".selection-choices-div ul li").show();
                    for (instance in CKEDITOR.instances) {
                        CKEDITOR.instances[instance].destroy();
                        $("#" + instance).removeClass("wysiwyg");
                    }
                    $("toggle-wysiwyg").data("checked", false);
                    for (var i in returnArray) {
                        if (i == "") {
                            continue;
                        }
                        if (typeof returnArray[i] == "object" && "data_value" in returnArray[i]) {
                            if ($("input[type=radio][name='" + i + "']").length > 0) {
                                $("input[type=radio][name='" + i + "']").prop("checked", false);
                                $("input[type=radio][name='" + i + "'][value='" + returnArray[i]['data_value'] + "']").prop("checked", true);
                            } else if ($("#" + i).is("input[type=checkbox]")) {
                                $("#" + i).prop("checked", returnArray[i].data_value == 1);
                            } else if ($("#" + i).is("a")) {
                                $("#" + i).attr("href", returnArray[i].data_value).css("display", (returnArray[i].data_value == "" ? "none" : "inline"));
                            } else if ($("#" + i).is("div") || $("#" + i).is("span") || $("#" + i).is("td") || $("#" + i).is("tr") || $("#" + i).is("p") || $("#" + i).is("h2")) {
                                $("#" + i).html(returnArray[i].data_value);
                            } else {
                                $("#" + i).val(returnArray[i].data_value);
                            }
                            if ($("input[type=radio][name='" + i + "']").length > 0) {
                                if ("crc_value" in returnArray[i]) {
                                    $("input[type=radio][name='" + i + "']").data("crc_value", returnArray[i].crc_value);
                                } else {
                                    $("input[type=radio][name='" + i + "']").removeData("crc_value");
                                }
                            } else {
                                if ("crc_value" in returnArray[i]) {
                                    $("#" + i).data("crc_value", returnArray[i].crc_value);
                                } else {
                                    $("#" + i).removeData("crc_value");
                                }
                            }
                        } else if ($("#_" + i + "_table").is(".editable-list")) {
                            $("#_" + i + "_table tr").not(":first").not(":last").remove();
                            for (var j in returnArray[i]) {
                                addEditableListRow(i, returnArray[i][j]);
                            }
                        } else if ($("#_" + i + "_form_list").is(".form-list")) {
                            $("#_" + i + "_form_list").find(".form-list-item").remove();
                            for (var j in returnArray[i]) {
                                addFormListRow(i, returnArray[i][j]);
                            }
                        }
                        if (typeof afterDisplayField == "function") {
                            afterDisplayField(i);
                        }
                    }
                    if ("database_query_count" in returnArray && $("#database_query_count").length > 0) {
                        $("#database_query_count").html($("#database_query_count").html() + "," + returnArray['database_query_count']);
                    }
                    $(".selector-value-list").trigger("change");
                    enableButtons();
                    $("#_next_button").css("visibility", ($("#_next_primary_id").val().length == 0 ? "hidden" : "visible"));
                    $("#_previous_button").css("visibility", ($("#_previous_primary_id").val().length == 0 ? "hidden" : "visible"));
                    if ($("#primary_id").val() == "") {
                        $("#_edit_form").find("select,input[type=checkbox],input[type=radio]").filter(".not-editable").prop("disabled", false);
                        $("#_edit_form").find("input[type=password],input[type=text],textarea").filter(".not-editable").prop("readonly", false);
                        $("#_edit_form").find("button.not-editable").show();
                        $("#_edit_form").find("button.contact-picker-open-contact").hide();
                    } else {
                        $("#_edit_form").find("select,input[type=checkbox],input[type=radio]").filter(".not-editable").prop("disabled", true);
                        $("#_edit_form").find("input[type=password],input[type=text],textarea").filter(".not-editable").prop("readonly", true);
                        $("#_edit_form").find("button.not-editable").hide();
                        $("#_edit_form").find("button.contact-picker-open-contact").show();
                    }
                    if ($("#primary_id").val() == "") {
                        try {
							<?php if (!$this->iReadonly) { ?>
                            if ($("#_edit_form").find("input[type!=hidden]:not([readonly='readonly']),select:not([disabled='disabled']),textarea:not([readonly='readonly'])").length > 0) {
                                $("#_edit_form").find("input[type!=hidden]:not([readonly='readonly']):not([disabled='disabled']),select:not([disabled='disabled']),textarea:not([readonly='readonly'])").not(".no-first-focus").first().focus();
                            }
							<?php } ?>
                        } catch (e) {
                        }
                    }
                    $("#_edit_form").find(".autocomplete-field").trigger("get_autocomplete_text");
                    $(".minicolors").trigger("keyup");
                    if (typeof afterGetRecord == "function") {
                        afterGetRecord(returnArray);
                    }

                    $("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({ social_tools: false, default_height: 480, default_width: 854, deeplinking: false });
                    $(document).scrollTop(0);
                    if ($("#primary_id").val() == "" && $(".tabbed-form").length > 0) {
                        if ($(".tabbed-form a.new-record-tab").length > 0) {
                            var index = $(".tabbed-form a.new-record-tab").parent().index();
                        } else {
                            var index = 0;
                        }
                        $(".tabbed-form").tabs("option", "active", index);
                        lastFocusFieldId = "";
						<?php if (!$this->iReadonly) { ?>
                        if ($("#_edit_form").length > 0) {
                            $("#_edit_form :input:visible:enabled:not([readonly]):not([disabled])").not(".no-first-focus").first().focus();
                        }
						<?php } ?>
                    }
                    manualChangesMade = false;
					<?php if (!$this->iReadonly) { ?>
                    if (lastFocusFieldId != "") {
                        if ($("#" + lastFocusFieldId).length > 0) {
                            $("#" + lastFocusFieldId).focus();
                        }
                    } else if ($("#primary_id").val() != "") {
                        if ($("#_edit_form").find("input[type!=hidden]:not([readonly='readonly']):not([disabled='disabled']),select:not([disabled='disabled']),textarea:not([readonly='readonly'])").length > 0) {
                            $("#_edit_form").find("input[type!=hidden]:not([readonly='readonly']):not([disabled='disabled']),select:not([disabled='disabled']),textarea:not([readonly='readonly'])").not(".no-first-focus").first().focus();
                        }
                    }
					<?php } ?>
                    $("#_record_number_section").css("visibility", ($("#primary_id").val().length == 0 ? "hidden" : "visible"));
                    if (!$("#_save_button").is(".disabled-button")) {
                        if ($("#_permission").val() <= "1") {
                            $("#_locked_image").show();
                            $("#_save_button").button("disable");
                            $("#_delete_button").button("disable");
                        } else {
                            $("#_save_button").button("enable");
                            $("#_locked_image").hide();
                            if (!$("#_delete_button").is(".disabled-button")) {
                                if ($("#_permission").val() <= "2") {
                                    $("#_delete_button").button("disable");
                                } else {
                                    $("#_delete_button").button("enable");
                                }
                            }
                        }
                    }
                });
            }
        </script>
		<?php
	}

	/**
	 *    function internalPageCSS
	 *
	 *    Add css styles for columns that have them. If a column has a control name that starts with css-, that will
	 *    generate a css style.
	 */
	function internalPageCSS() {
		?>
        <style>
            <?php
					$columns = $this->iDataSource->getColumns();
					foreach ($columns as $columnName => $thisColumn) {
						$cssFields = "";
						foreach ($thisColumn->getAllControlValues() as $controlName => $controlData) {
							if (substr($controlName,0,4) == "css-" || substr($controlName,0,4) == "css_") {
								$cssFields .= substr($controlName,4) . ": " . $controlData . ";";
							}
						}
						if (!empty($cssFields)) {
			?>
            #
            <?= $thisColumn->getControlValue('column_name') ?>
            {
            <?= $cssFields ?>
            }
            <?php
						}
					}
			?>
        </style>
		<?php
		$this->iPageObject->internalPageCSS();
	}

	/**
	 *    function getRecord
	 *
	 *    Function to get the data for a specific record to be displayed in the form. An array is generated and then
	 *    the json form of the array is echoed for the browser. Each entry in the return array is an array in itself. The
	 *    "data_value" object in the array is the value of the field and the "crc_value" is the crc representation of the
	 *    data. This is used to determine if the value has changed or not. If the method afterGetRecord is in the current
	 *    page, the return array is passed to it and it is executed. This allows the developer to make changes to the
	 *    data or add data before the data is passed to the browser.
	 */
	function getRecord() {
		$returnArray = array();
		$filterText = getPreference("MAINTENANCE_FILTER_TEXT", $GLOBALS['gPageRow']['page_code']);
		if (method_exists($this->iPageObject, "filterTextProcessing")) {
			$this->iPageObject->filterTextProcessing($filterText);
		} else {
			$this->iDataSource->setFilterText($filterText);
		}
		if ($GLOBALS['gUserRow']['superuser_flag']) {
			$customWhereValue = getPreference("MAINTENANCE_CUSTOM_WHERE_VALUE", $GLOBALS['gPageRow']['page_code']);
			if (!empty($customWhereValue)) {
				$this->iDataSource->setFilterWhere($customWhereValue);
			}
		}

		$searchColumn = getPreference("MAINTENANCE_FILTER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$foreignKeys = $this->iDataSource->getForeignKeyList();
		foreach ($foreignKeys as $columnName => $foreignKeyInfo) {
			if (!empty($searchColumn) && $searchColumn != $columnName) {
				continue;
			}
			if (!in_array($columnName, $this->iExcludeListColumns)) {
				if (!empty($foreignKeyInfo['description'])) {
					$this->iDataSource->addColumnControl($foreignKeyInfo['column_name'] . "_display", "select_value",
						"select concat_ws(' '," . implode(",", $foreignKeyInfo['description']) . ") from " .
						$foreignKeyInfo['referenced_table_name'] . " where " . $foreignKeyInfo['referenced_column_name'] . " = " . $columnName);
					$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => $foreignKeyInfo['referenced_table_name'],
						"referenced_column_name" => $foreignKeyInfo['referenced_column_name'], "foreign_key" => $foreignKeyInfo['column_name'],
						"description" => $foreignKeyInfo['description']));
				}
			}
		}

		$columns = $this->iDataSource->getColumns();
		$allSearchColumns = array();
		foreach ($columns as $columnName => $thisColumn) {
			switch ($thisColumn->getControlValue('data_type')) {
				case "date":
				case "time":
				case "bigint":
				case "int":
				case "select":
				case "text":
				case "mediumtext":
				case "varchar":
				case "decimal":
					if (!in_array($columnName, $this->iExcludeSearchColumns)) {
						$allSearchColumns[] = $columnName;
					}
					break;
				default;
					continue 2;
			}
		}

		if (count($this->iFilters) > 0 || count($this->iVisibleFilters) > 0 && empty($_GET['clear_filter'])) {
			$setFilters = array();
			$setFilterText = getPreference("MAINTENANCE_SET_FILTERS", $GLOBALS['gPageRow']['page_code']);
			if (strlen($setFilterText) > 0) {
				$setFilters = json_decode($setFilterText, true);
			} else {
				foreach (array_merge($this->iFilters, $this->iVisibleFilters) as $filterCode => $filterInfo) {
					if ($filterInfo['set_default']) {
						$setFilters[$filterCode] = 1;
					}
				}
				setUserPreference("MAINTENANCE_SET_FILTERS", jsonEncode($setFilters), $GLOBALS['gPageRow']['page_code']);
			}
			if (method_exists($this->iPageObject, "filtersLoaded")) {
				$this->iPageObject->filtersLoaded($setFilters);
			}
			$filterAndWhere = "";
			$filterWhere = "";
			foreach (array_merge($this->iFilters, $this->iVisibleFilters) as $filterCode => $filterInfo) {
				if (empty($filterInfo['where'])) {
					continue;
				}
				if (strlen($setFilters[$filterCode]) == 0 && !empty($filterInfo['default_value'])) {
					$setFilters[$filterCode] = $filterInfo['default_value'];
				}
				if (!empty($setFilters[$filterCode])) {
					$filterValueParameter = ($filterInfo['data_type'] == "date" ? makeDateParameter($setFilters[$filterCode]) : makeParameter($setFilters[$filterCode]));
					if ($filterInfo['conjunction'] == "and") {
						$filterAndWhere .= (empty($filterAndWhere) ? "" : " and ") . "(" . str_replace("%key_value%", $setFilters[$filterCode], str_replace("%filter_value%", $filterValueParameter, $filterInfo['where'])) . ")";
					} else {
						$filterWhere .= (empty($filterWhere) ? "" : " or ") . "(" . str_replace("%key_value%", $setFilters[$filterCode], str_replace("%filter_value%", $filterValueParameter, $filterInfo['where'])) . ")";
					}
				} else if (!empty($filterInfo['not_where'])) {
					if ($filterInfo['conjunction'] == "and") {
						$filterAndWhere .= (empty($filterAndWhere) ? "" : " and ") . "(" . $filterInfo['not_where'] . ")";
					} else {
						$filterWhere .= (empty($filterWhere) ? "" : " or ") . "(" . $filterInfo['not_where'] . ")";
					}
				}
				$returnArray['_set_filter_' . $filterCode] = array("data_value" => $setFilters[$filterCode]);
			}
			$returnArray['_set_filters'] = "0";
			if (!empty($filterAndWhere)) {
				$filterWhere = (empty($filterWhere) ? "" : "(" . $filterWhere . ") and ") . $filterAndWhere;
			}
			if (empty($filterWhere)) {
				foreach (array_merge($this->iFilters, $this->iVisibleFilters) as $filterCode => $filterInfo) {
					if (empty($filterInfo['no_filter_default'])) {
						continue;
					}
					$setFilters[$filterCode] = $filterInfo['no_filter_default'];
					if (!empty($filterInfo['where'])) {
						if (!empty($setFilters[$filterCode])) {
							$filterValueParameter = ($filterInfo['data_type'] == "date" ? makeDateParameter($setFilters[$filterCode]) : makeParameter($setFilters[$filterCode]));
							$filterLikeParameter = makeParameter("%" . $setFilters[$filterCode] . "%");
							$filterWhere .= (empty($filterWhere) ? "" : " or ") . "(" . str_replace("%key_value%", $setFilters[$filterCode], str_replace("%filter_value%", $filterValueParameter, str_replace("%like_value%", $filterLikeParameter, $filterInfo['where']))) . ")";
						}
					}
					$returnArray['_set_filter_' . $filterCode] = array("data_value" => $setFilters[$filterCode]);
					break;
				}
			}
			if (!empty($filterWhere)) {
				$returnArray['filter_set'] = true;
				$this->iDataSource->addFilterWhere($filterWhere);
			}
		}
		if (!empty($_GET['clear_filter']) && !empty($_GET['primary_id'])) {
			$this->iDataSource->addFilterWhere($this->iDataSource->getPrimaryTable()->getPrimaryKey() . " = " . $this->iDataSource->getDatabase()->makeNumberParameter($_GET['primary_id']));
		}
		if ($this->iDataSource->getPrimaryTable()->columnExists("inactive") && getPreference("MAINTENANCE_SHOW_INACTIVE", $GLOBALS['gPageRow']['page_code']) != "true") {
			$this->iDataSource->addFilterWhere($this->iDataSource->getPrimaryTable()->getName() . ".inactive = 0");
		}

		$sortOrderColumn = getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$secondarySortOrderColumn = getPreference("MAINTENANCE_SECONDARY_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$listColumns = explode(",", getPreference("MAINTENANCE_LIST_COLUMNS", $GLOBALS['gPageRow']['page_code']));
		if (count($listColumns) == 0 || (count($listColumns) == 1 && empty($listColumns[0]))) {
			$listColumns = array();
			foreach ($columns as $columnName => $thisColumn) {
				$subType = $thisColumn->getControlValue('subtype');
				if (!in_array($columnName, $this->iExcludeListColumns) && $thisColumn->getControlValue('data_type') != "longblob" && empty($subtype)) {
					$listColumns[] = $columnName;
					if (count($listColumns) >= $this->iMaximumListColumns && $this->iMaximumListColumns > 0) {
						break;
					}
				}
			}
		}
		foreach ($listColumns as $thisIndex => $columnName) {
			if (!array_key_exists($columnName, $columns) || (!$columns[$columnName]->getControlValue("primary_key") && in_array($columnName, $this->iExcludeListColumns))) {
				unset($listColumns[$thisIndex]);
				continue;
			}
		}
		if (!in_array($sortOrderColumn, $listColumns)) {
			$sortOrderColumn = $this->iDefaultSortOrderColumn;
			$reverseSortOrder = $this->iDefaultReverseSortOrder;
			$secondarySortOrderColumn = "";
			$secondaryReverseSortOrder = false;
			setUserPreference("MAINTENANCE_SORT_ORDER_COLUMN", $sortOrderColumn, $GLOBALS['gPageRow']['page_code']);
			setUserPreference("MAINTENANCE_REVERSE_SORT_ORDER", ($reverseSortOrder ? "true" : "false"), $GLOBALS['gPageRow']['page_code']);
			setUserPreference("MAINTENANCE_SECONDARY_SORT_ORDER_COLUMN", $secondarySortOrderColumn, $GLOBALS['gPageRow']['page_code']);
			setUserPreference("MAINTENANCE_SECONDARY_REVERSE_SORT_ORDER", "false", $GLOBALS['gPageRow']['page_code']);
		}
		if (array_key_exists($sortOrderColumn, $columns)) {
			$sortOrderColumns = array($sortOrderColumn);
			$reverseSortOrder = (getPreference("MAINTENANCE_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']) == "true");
			$reverseSortOrders = array($reverseSortOrder ? "desc" : "asc");
			if (!empty($secondarySortOrderColumn)) {
				$sortOrderColumns[] = $secondarySortOrderColumn;
				$reverseSortOrders[] = (getPreference("MAINTENANCE_SECONDARY_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']) == "true" ? "desc" : "asc");
			}
			$sortOrderColumns[] = $this->iDataSource->getPrimaryTable()->getPrimaryKey();
			$reverseSortOrders[] = "asc";
			foreach ($sortOrderColumns as $index => $thisSortOrderColumn) {
				if (array_key_exists($thisSortOrderColumn, $foreignKeys)) {
					$sortOrderColumns[$index] = $foreignKeys[$thisSortOrderColumn]['column_name'] . "_display";
				}
			}
			$this->iDataSource->setSortOrder($sortOrderColumns, $reverseSortOrders);
		} else {
			$this->iDataSource->setSortOrder($this->iDataSource->getPrimaryTable()->getPrimaryKey());
		}
		if (empty($searchColumn) || !in_array($searchColumn, $allSearchColumns)) {
			$this->iDataSource->setSearchFields($allSearchColumns);
		} else {
			$this->iDataSource->setSearchFields($searchColumn);
		}

		$primaryId = $_GET['primary_id'];

# Check to see if the user or user type is set to be readonly or no delete based on data limitations

		if (!empty($primaryId)) {
			$permissionArray = array("1", "2");
			foreach ($permissionArray as $permissionLevel) {
				if (!empty($returnArray['_permission']['data_value'])) {
					break;
				}
				$checkQuery = "";
				$resultSet = executeQuery("select * from user_type_data_limitations where user_type_id = ? and page_id = ? and permission_level = ?",
					$GLOBALS['gUserRow']['user_type_id'], $GLOBALS['gPageId'], $permissionLevel);
				while ($row = getNextRow($resultSet)) {
					if (!empty($checkQuery)) {
						$checkQuery .= " or ";
					}
					$checkQuery .= "(" . PlaceHolders::massageContent($row['query_text']) . ")";
				}
				freeResult($resultSet);
				$resultSet = executeQuery("select * from user_data_limitations where user_id = ? and page_id = ? and permission_level = ?",
					$GLOBALS['gUserId'], $GLOBALS['gPageId'], $permissionLevel);
				while ($row = getNextRow($resultSet)) {
					if (!empty($checkQuery)) {
						$checkQuery .= " or ";
					}
					$checkQuery .= "(" . PlaceHolders::massageContent($row['query_text']) . ")";
				}
				freeResult($resultSet);
				foreach ($GLOBALS['gUserRow'] as $fieldName => $fieldData) {
					$checkQuery = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $checkQuery);
				}
				if (!empty($checkQuery)) {
                    if(!DataTable::isEmpty($this->iDataSource->getPrimaryTable()->getName())) {
						$returnArray['_permission'] = array("data_value" => $permissionLevel);
					}
				}
			}
		}
		if (empty($returnArray['_permission']['data_value'])) {
			$returnArray['_permission'] = array("data_value" => $GLOBALS['gPermissionLevel']);
		}

		$thisDataRow = $this->iDataSource->getRow($primaryId);
		if ($thisDataRow === false) {
			$returnArray['error_message'] = $this->iDataSource->getErrorMessage();
			$returnArray['return_to_list'] = true;
			ajaxResponse($returnArray);
		}
		if (empty($primaryId)) {
			$thisDataRow['_add_hash'] = md5(uniqid(mt_rand(), true));
			foreach ($this->iDataSource->getColumns() as $columnName => $thisColumn) {
				if ($thisColumn->getControlValue("data_type") == "select" && $thisColumn->getControlValue("not_null")) {
					$choices = $thisColumn->getChoices($this->iPageObject);
					if (is_array($choices) && count($choices) == 1) {
						$thisColumn->setControlValue("default_value", key($choices));
					}
				}
				if (!in_array($columnName, $this->iExcludeFormColumns)) {
					if (strlen($thisColumn->getControlValue('default_value')) > 0) {
						$thisDataRow[$thisColumn->getControlValue('column_name')] = $thisColumn->getControlValue('default_value');
					} else {
						$thisDataRow[$thisColumn->getControlValue('column_name')] = "";
					}
				}
			}
		}
		$returnArray['select_values'] = array();
		$thisDataRow['primary_id'] = $thisDataRow[$this->iDataSource->getPrimaryTable()->getPrimaryKey()];
		$dontCheckCrc = array("primary_id", "version", "_add_hash");
		foreach ($thisDataRow as $fieldName => $fieldData) {
			$thisColumn = false;
			if (array_key_exists($this->iDataSource->getPrimaryTable()->getName() . "." . $fieldName, $columns)) {
				$thisColumn = $columns[$this->iDataSource->getPrimaryTable()->getName() . "." . $fieldName];
			} else {
				$joinTable = $this->iDataSource->getJoinTable();
				if ($joinTable && array_key_exists($joinTable->getName() . "." . $fieldName, $columns)) {
					$thisColumn = $columns[$joinTable->getName() . "." . $fieldName];
				}
			}
			if ($thisColumn) {
				switch ($thisColumn->getControlValue('data_type')) {
					case "datetime":
					case "date":
						$dateFormat = $thisColumn->getControlValue("date_format");
						if (empty($dateFormat)) {
							$dateFormat = ($thisColumn->getControlValue('data_type') == "datetime" ? "m/d/Y g:i:sa" : "m/d/Y");
						}
						$fieldData = (empty($fieldData) ? "" : date($dateFormat, strtotime($fieldData)));
						break;
					case "time":
						$dateFormat = $thisColumn->getControlValue("date_format");
						if (empty($dateFormat)) {
							$dateFormat = "g:i a";
						}
						$fieldData = (empty($fieldData) ? "" : date($dateFormat, strtotime($fieldData)));
						break;
					case "longblob":
						$fieldData = (empty($fieldData) ? "" : "1");
						$dontCheckCrc[] = $fieldName;
						$crcValue = getCrcValue("");
						break;
					case "password":
						$fieldData = "";
						break;
					case "autocomplete":
						if (array_key_exists($fieldName, $GLOBALS['gAutocompleteFields'])) {
							$descriptionFields = explode(",", $GLOBALS['gAutocompleteFields'][$fieldName]['description_field']);
							$descriptionValue = "";
							foreach ($descriptionFields as $thisFieldName) {
								$descriptionValue .= (empty($descriptionValue) ? "" : " - ") .
									($thisFieldName == "contact_id" ? (empty($fieldData) ? "" : getDisplayName($fieldData)) : getFieldFromId($thisFieldName, $thisColumn->getReferencedTable(),
										$GLOBALS['gAutocompleteFields'][$fieldName]['key_field'], $fieldData));
							}
							$returnArray[$fieldName . "_autocomplete_text"] = array("data_value" => $descriptionValue);
						}
						break;
					case "contact_picker":
						$description = getDisplayName($fieldData, array("include_company" => true));
						$address1 = getFieldFromId("address_1", "contacts", "contact_id", $fieldData);
						if (!empty($address1)) {
							if (!empty($description)) {
								$description .= " • ";
							}
							$description .= $address1;
						}
						$city = getFieldFromId("city", "contacts", "contact_id", $fieldData);
						$state = getFieldFromId("state", "contacts", "contact_id", $fieldData);
						if (!empty($state)) {
							if (!empty($city)) {
								$city .= ", ";
							}
							$city .= $state;
						}
						if (!empty($city)) {
							if (!empty($description)) {
								$description .= " • ";
							}
							$description .= $city;
						}
						$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $fieldData);
						if (!empty($emailAddress)) {
							if (!empty($description)) {
								$description .= " • ";
							}
							$description .= $emailAddress;
						}
						if ($fieldData) {
							$returnArray['select_values'][$fieldName . "_selector"] = array(array("key_value" => $fieldData, "description" => $description));
						}
						$returnArray[$fieldName . "_selector"] = array("data_value" => $fieldData);
						break;
					case "user_picker":
						$description = getUserDisplayName($fieldData);
						if ($fieldData) {
							$returnArray['select_values'][$fieldName . "_selector"] = array(array("key_value" => $fieldData, "description" => $description));
						}
						$returnArray[$fieldName . "_selector"] = array("data_value" => $fieldData);
						break;
				}
				switch ($thisColumn->getControlValue('subtype')) {
					case "image":
						$returnArray[$fieldName . "_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
						$returnArray[$fieldName . "_view"] = array("data_value" => getImageFilename($thisColumn->getControlValue('data_type') == "longblob" ? $primaryId : $fieldData));
						$returnArray[$fieldName . "_filename"] = array("data_value" => getFieldFromId("filename", "images", "image_id", ($thisColumn->getControlValue('data_type') == "longblob" ? $primaryId : $fieldData)));
						$returnArray["remove_" . $fieldName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
						if (!empty($fieldData)) {
							$returnArray['select_values'][$fieldName] = array(array("key_value" => $fieldData, "description" => getFieldFromId("description", "images", "image_id", $fieldData)));
						}
						break;
					case "file":
						$returnArray[$fieldName . "_file"] = array("data_value" => "", "crc_value" => getCrcValue(""));
						$returnArray[$fieldName . "_download"] = array("data_value" => (empty($fieldData) && !array_key_exists("os_filename", $thisDataRow) ? "" : "download.php?id=" . ($thisColumn->getControlValue('data_type') == "longblob" ? $primaryId : $fieldData)));
						$returnArray["remove_" . $fieldName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
						break;
				}
			}
			if (in_array($fieldName, $dontCheckCrc)) {
				$crcValue = "";
			} else {
				$crcValue = getCrcValue($fieldData);
			}
			if ($thisColumn && $thisColumn->getControlValue("data_type") == "select" &&
				array_key_exists($this->iDataSource->getPrimaryTable()->getName() . "." . $fieldName, $foreignKeys) &&
				!in_array($fieldName, $this->iExcludeFormColumns)) {
				$referencedTable = $thisColumn->getReferencedTable();
				if (!in_array($referencedTable, $this->iNoGetChoicesTables)) {
					$selectList = $thisColumn->getChoices($this->iPageObject, true);
					if (strlen($fieldData) != 0 && $selectList[$fieldData]['inactive']) {
						$noInactiveTag = $thisColumn->getControlValue("no_inactive_tag");
						$returnArray['select_values'][$fieldName] = array(array("key_value" => $fieldData, "description" => $selectList[$fieldData]['description'] . (!empty($noInactiveTag) ? "" : " (Inactive)"), "inactive" => "1"));
					}
				}
			}
			$returnArray[$fieldName] = array("data_value" => $fieldData);
			if (!empty($crcValue)) {
				$returnArray[$fieldName]["crc_value"] = $crcValue;
			}
		}
		$previousPrimaryId = "";
		$nextPrimaryId = "";
		$recordNumber = 0;
		$rowCount = 0;
		if (!empty($primaryId)) {
			$startRow = 0;
			$retrieveLimit = 200;
			$primaryIdFound = false;
			while (true) {
				$dataList = $this->iDataSource->getDataList(array("start_row" => $startRow, "row_count" => $retrieveLimit));
				if (method_exists($this->iPageObject, "dataListProcessing")) {
					$this->iPageObject->dataListProcessing($dataList);
				}
				if (count($dataList) == 0) {
					break;
				}
				$rowCount = $this->iDataSource->getDataListCount();
				reset($dataList);
				if (!$primaryIdFound) {
					$thisArray = current($dataList);
					$thisPrimaryId = $thisArray[$this->iDataSource->getPrimaryTable()->getPrimaryKey()];
					while ($thisPrimaryId != $primaryId) {
						$recordNumber++;
						$previousPrimaryId = $thisPrimaryId;
						if (!next($dataList)) {
							break;
						}
						$thisArray = current($dataList);
						$thisPrimaryId = $thisArray[$this->iDataSource->getPrimaryTable()->getPrimaryKey()];
					}
					if ($thisPrimaryId == $primaryId) {
						$recordNumber++;
						$primaryIdFound = true;
					}
				}
				if ($primaryIdFound && next($dataList)) {
					$thisArray = current($dataList);
					$nextPrimaryId = $thisArray[$this->iDataSource->getPrimaryTable()->getPrimaryKey()];
				}
				if (!empty($nextPrimaryId)) {
					break;
				}
				$startRow += $retrieveLimit;
			}
		}
		$returnArray['_previous_primary_id'] = array("data_value" => $previousPrimaryId);
		$returnArray['_next_primary_id'] = array("data_value" => $nextPrimaryId);
		$returnArray['_record_number'] = array("data_value" => $recordNumber);
		$returnArray['_row_count'] = array("data_value" => $rowCount);

		foreach ($columns as $columnName => $thisColumn) {
			if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
				$controlClass = $thisColumn->getControlValue("control_class");
				$customControl = new $controlClass($thisColumn, $this->iPageObject);
				$customControlId = $primaryId;
				foreach ($customControl->getRecord($customControlId) as $keyValue => $dataValue) {
					$returnArray[$keyValue] = $dataValue;
				}
			}
		}

		if (method_exists($this->iPageObject, "afterGetRecord")) {
			$this->iPageObject->afterGetRecord($returnArray);
		}
		ajaxResponse($returnArray);
	}

	/**
	 *    function saveChanges
	 *
	 *    Save changes made by the user to the data displayed in the form. Three functions in the page can be executed
	 *    in this context. beforeSaveChanges is executed before changes are started, to give the developer a chance to
	 *    manipulate the data before it is saved. afterSaveChanges is executed after the changes are saved, but before
	 *    the changes are committed to the database. If afterSaveChanges returns anything but true, the return is used
	 *    as the error message and all the changes are rolled back. afterSaveDone is executed after the changes are saved
	 *    and committed. This allows the developer to do some processing without having to be concerned about the
	 *    primary changes.
	 */
	function saveChanges() {
		$returnArray = array();
		if ($this->iReadonly || $GLOBALS['gPermissionLevel'] < _READWRITE) {
			$returnArray['error_message'] = getSystemMessage("denied");
			ajaxResponse($returnArray);
		}
		$columns = $this->iDataSource->getColumns();
		foreach ($columns as $columnName => $thisColumn) {
			if ($thisColumn->getControlValue('readonly')) {
				if (empty($_POST['primary_id']) && strlen($thisColumn->getControlValue('default_value')) > 0) {
					$_POST[$thisColumn->getControlValue("column_name")] = $thisColumn->getControlValue('default_value');
				} else {
					unset($_POST[$thisColumn->getControlValue("column_name")]);
				}
			}
		}
		if (method_exists($this->iPageObject, "afterSaveChanges")) {
			$this->iDataSource->setAfterSaveFunction(array("object" => $this->iPageObject, "method" => "afterSaveChanges"));
		}
		$this->iDataSource->disableTransactions();
		$this->iDataSource->getDatabase()->startTransaction();
		if (method_exists($this->iPageObject, "beforeSaveChanges")) {
			$errorMessage = $this->iPageObject->beforeSaveChanges($_POST);
			if ($errorMessage !== true) {
				$returnArray['error_message'] = $errorMessage;
				$this->iDataSource->getDatabase()->rollbackTransaction();
				ajaxResponse($returnArray);
			}
		}
		if (!$primaryId = $this->iDataSource->saveRecord(array("name_values" => $_POST, "primary_id" => $_POST['primary_id']))) {
			$returnArray['error_message'] = $this->iDataSource->getErrorMessage();
		} else {
			$joinPrimaryId = $this->iDataSource->getJoinPrimaryId();
			if (empty($_POST['primary_id'])) {
				$returnArray['primary_id'] = $primaryId;
				$_POST['primary_id'] = $primaryId;
				if ($this->iDataSource->getJoinTable()) {
					$returnArray[$this->iDataSource->getJoinTable()->getPrimaryKey()] = $joinPrimaryId;
					$_POST[$this->iDataSource->getJoinTable()->getPrimaryKey()] = $joinPrimaryId;
				}
			}
			foreach ($columns as $columnName => $thisColumn) {
				if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
					$controlClass = $thisColumn->getControlValue("control_class");
					$customControl = new $controlClass($thisColumn, $this->iPageObject);
					if (!$customControl->saveData($_POST)) {
						$returnArray['error_message'] = $customControl->getErrorMessage();
						break;
					}
				}
			}
		}
		if (empty($returnArray['error_message'])) {
			$this->iDataSource->getDatabase()->commitTransaction();
		} else {
			$this->iDataSource->getDatabase()->rollbackTransaction();
		}
		if (empty($returnArray['error_message']) && method_exists($this->iPageObject, "afterSaveDone")) {
			$this->iPageObject->afterSaveDone($_POST);
		}
		ajaxResponse($returnArray);
	}

	/**
	 *    function deleteRecord
	 *
	 *    delete function is called when the user requests to delete a record. If the method beforeDeleteRecord exists in
	 *    the page, it will be executed before deleting the record. If it returns false, the record won't be deleted.
	 */
	function deleteRecord() {
		$returnArray = array();
		if ($this->iReadonly || $GLOBALS['gPermissionLevel'] < _FULLACCESS) {
			$returnArray['error_message'] = getSystemMessage("denied");
			ajaxResponse($returnArray);
		}
		if (empty($_POST['primary_id'])) {
			ajaxResponse($returnArray);
		}

		$this->iDataSource->disableTransactions();
		$this->iDataSource->getDatabase()->startTransaction();
		if (method_exists($this->iPageObject, "beforeDeleteRecord")) {
			$deleteReturn = $this->iPageObject->beforeDeleteRecord($_POST['primary_id']);
			if ($deleteReturn !== true) {
				$returnArray['error_message'] = $deleteReturn;
				$this->iDataSource->getDatabase()->rollbackTransaction();
				ajaxResponse($returnArray);
			}
		}
		if (!$this->iDataSource->deleteRecord(array("primary_id" => $_POST['primary_id']))) {
			$returnArray['error_message'] = $this->iDataSource->getErrorMessage();
			$this->iDataSource->getDatabase()->rollbackTransaction();
		} else {
			$this->iDataSource->getDatabase()->commitTransaction();
		}
		ajaxResponse($returnArray);
	}

	/**
	 *    function hiddenElements
	 *
	 *    The form requires a hidden iframe for submission. Normally, form submission happens through ajax, but
	 *    if there is a file input element, that is not possible, so the submission happens to the iframe. This creates
	 *    the appearance that it happens through ajax.
	 */
	function hiddenElements() {
		?>
        <div id="autocomplete_options">
            <ul>
            </ul>
        </div>
		<?php include "contactpicker.inc" ?>
		<?php include "userpicker.inc" ?>

        <div id="_image_picker_new_image_dialog" class="dialog-box">
            <form id="_new_image" enctype='multipart/form-data'>
                <table>
                    <tr>
                        <td class="field-label"><label for="image_picker_new_image_description">Description</label></td>
                        <td class="field-text"><input type="text" class="field-text validate[required]" size="40" id="image_picker_new_image_description" name="image_picker_new_image_description"/></td>
                    </tr>
                    <tr>
                        <td class="field-label"><label for="image_picker_file_content_file">Image</label></td>
                        <td class="field-text"><input type="file" id="image_picker_file_content_file" class="validate[required]" name="image_picker_file_content_file"/></td>
                    </tr>
                </table>
            </form>
        </div>

        <iframe id="_post_iframe" name="post_iframe"></iframe>
		<?php
	}

	/**
	 *    function jqueryTemplates
	 *
	 *    If there are any custom fields, these may use templates, so they are output in this function.
	 */
	function jqueryTemplates() {
		$columns = $this->iDataSource->getColumns();
		foreach ($columns as $columnName => $thisColumn) {
			if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
				$controlClass = $thisColumn->getControlValue("control_class");
				$customControl = new $controlClass($thisColumn, $this->iPageObject);
				echo $customControl->getTemplate();
			}
		}
	}

	/**
	 *    function getSortList
	 *
	 *    Not used in this class, but required because it is abstract in MaintenancePage
	 */
	function getSortList() {
	}

	/**
	 *    function setPreferences
	 *
	 *    Not used in this class, but required because it is abstract in MaintenancePage
	 */
	function setPreferences() {
	}

	/**
	 *    function getDataList
	 *
	 *    Not used in this class, but required because it is abstract in MaintenancePage
	 */
	function getDataList() {
	}

	/**
	 *    function exportCSV
	 *
	 *    Not used in this class, but required because it is abstract in MaintenancePage
	 */
	function exportCSV($exportAll = false) {
	}

	/**
	 *    function getSpreadsheetList
	 *
	 *    Not used in this class, but required because it is abstract in MaintenancePage
	 */
	function getSpreadsheetList() {
	}
}
