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
 * class TableEditorList
 *
 * This class generates a list of records from a database table. It works within a template and a page.
 *
 * @author Kim D Geiger
 */
class TableEditorList extends TableEditor {

	function __construct($dataSource) {
		$this->iIsList = true;
		parent::__construct($dataSource);
	}

	/**
	 *    function pageElements
	 *
	 *    Fill in the page elements. For the list, this will include buttons for the actions that can be executed from the list.
	 *    The list header also includes a bunch of actions that can be executed. If the developer set some custom actions,
	 *    these would appear in the actions dropdown. Filters can also be added so that the list can be filtered in set
	 *    ways as defined by the developer. A search field is in the header allowing the user to filter the list.
	 *
	 * @return true indicating that the page elements have been completely done.
	 */
	function pageElements() {
		$startRow = getPreference("MAINTENANCE_START_ROW", $GLOBALS['gPageRow']['page_code']);
		$sortOrderColumn = getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$reverseSortOrder = getPreference("MAINTENANCE_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']);
        $showSelected = getPreference("MAINTENANCE_SHOW_SELECTED", $GLOBALS['gPageRow']['page_code']);
        $showUnselected = getPreference("MAINTENANCE_SHOW_UNSELECTED", $GLOBALS['gPageRow']['page_code']);
		?>
        <div class="hidden" id="_page_hidden_elements_content">
			<?php
			if ($GLOBALS['gUserRow']['superuser_flag']) {
				$customWhereValue = getPreference("MAINTENANCE_CUSTOM_WHERE_VALUE", $GLOBALS['gPageRow']['page_code']);
				?>
                <input type="hidden" id="_custom_where_value" name="_custom_where_value" value="<?= htmlText($customWhereValue) ?>"/>
			<?php } ?>
            <input type="hidden" id="_start_row" name="_start_row" value="<?= $startRow ?>"/>
            <input type="hidden" id="_next_start_row" name="_next_start_row" value=""/>
            <input type="hidden" id="_previous_start_row" name="_previous_start_row" value=""/>
            <input type="hidden" id="_show_selected" name="_show_selected" value="<?= $showSelected ?>"/>
            <input type="hidden" id="_show_unselected" name="_show_unselected" value="<?= $showUnselected ?>"/>
            <input type="hidden" id="_sort_order_column" name="_sort_order_column" value="<?= $sortOrderColumn ?>"/>
            <input type="hidden" id="_reverse_sort_order" name="_reverse_sort_order" value="<?= ($reverseSortOrder ? "true" : "false") ?>"/>
            <input type="hidden" id="_set_filters" name="_set_filters" value="0">
        </div> <!-- page_hidden_elements_content -->

        <div class="hidden" id="_page_action_selector_content">
            <div class='action-option' data-value='preferences'><?= getLanguageText("Preferences") ?></div>
			<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
                <div class='action-option' data-value='customwhere'><?= getLanguageText("Where") ?></div>
			<?php } ?>
			<?php
			$guiSorting = false;
			if ($GLOBALS['gPermissionLevel'] > _READONLY && $this->iDataSource->getPrimaryTable()->columnExists("sort_order") && !$this->iIgnoreGuiSorting) {
				$threshold = getPreference("GUI_SORT_THRESHOLD");
				if (empty($threshold)) {
					$threshold = 100;
				}
                $tableCount = DataTable::getLimitedCount($this->iDataSource->getPrimaryTable()->getName(), $threshold);
				if ($tableCount > 1 and $tableCount <= $threshold) {
					$guiSorting = true;
					?>
                    <div class='action-option' data-value='guisort'><?= getLanguageText("Visual Sort") ?></div>
                    <div class='action-option' data-value='resetsort'><?= getLanguageText("Reset Sort for Selected Rows") ?></div>
					<?php
				}
			}
			if (!$guiSorting && $GLOBALS['gPermissionLevel'] > _READONLY && $this->iDataSource->getPrimaryTable()->columnExists("sort_order")) {
				?>
                <div class='action-option' data-value='resetsort'><?= getLanguageText("Reset Sort for Selected Rows") ?></div>
				<?php
			}
			if ($GLOBALS['gPermissionLevel'] > _READONLY && ($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access'] || hasCapability("SPREADSHEET_EDITING")) && !$this->iIgnoreSpreadsheetEditing) {
				$recordThreshold = getPreference("SPREADSHEET_RECORD_THRESHOLD");
				if (empty($recordThreshold)) {
					$recordThreshold = 100;
				}
                $tableCount = DataTable::getLimitedCount($this->iDataSource->getPrimaryTable()->getName(), $recordThreshold);
				$columnCount = 0;
				foreach ($this->iDataSource->getColumns() as $columnName => $thisColumn) {
					if (in_array($columnName, $this->iExcludeListColumns)) {
						continue;
					}
					if (empty($thisColumn->getControlValue("hide_in_list"))) {
						continue;
					}
					switch ($thisColumn->getControlValue("data_type")) {
						case "varchar":
						case "text":
						case "mediumtext":
						case "decimal":
						case "date":
						case "time":
							$columnCount++;
							break;
						case "bigint":
						case "int":
						case "select":
							if (!$thisColumn->getControlValue("subtype")) {
								$columnCount++;
							}
							break;
					}
				}
				if ($columnCount > 0 && $tableCount > 0 && $tableCount <= $recordThreshold && !$this->iReadonly) {
					?>
                    <div class='action-option' data-value='spreadsheet'><?= getLanguageText("Editable_Spreadsheet") ?></div>
					<?php
				}
			}
			?>
            <div class='action-option' data-value='clearall'><?= getLanguageText("Unselect All") ?></div>
            <div class='action-option' data-value='selectall'><?= getLanguageText("Select All") ?></div>
            <div class='action-option' data-value='queryselect'><?= getLanguageText("Select by Query") ?></div>
            <div class='action-option' data-value='clearlisted'><?= getLanguageText("Unselect Listed") ?></div>
            <div class='action-option' data-value='clearnotlisted'><?= getLanguageText("Unselect Not Listed") ?></div>
            <div class='action-option' data-value='showselected'><?= getLanguageText("Show Selected") ?></div>
            <div class='action-option' data-value='showunselected'><?= getLanguageText("Show Unselected") ?></div>
            <div class='action-option' data-value='showall'><?= getLanguageText("Show All") ?></div>
			<?php if (!$this->iRemoveExport) { ?>
                <div class='action-option' data-value='exportallcsv'><?= getLanguageText("Export All to CSV") ?></div>
                <div class='action-option' data-value='exportcsv'><?= getLanguageText("Export Selected to CSV") ?></div>
			<?php } ?>
			<?php
			foreach ($this->iCustomActions as $customAction => $description) {
				?>
                <div class='action-option' data-value='<?= $customAction ?>'><?= $description ?></div>
				<?php
			}
			?>
        </div> <!-- action_selector_content -->

        <div class="hidden" id="_page_buttons_content">
			<?php
			$buttonFunctions = array();
			$enableButtons = array_diff(array("filter", "add"), $this->iDisabledFunctions);
			$addButtonLabel = getPageTextChunk("list_add_button");
			if (empty($addButtonLabel)) {
				$addButtonLabel = getLanguageText("Add");
			}
			$buttonFunctions['add'] = array("icon" => "fas fa-plus", "accesskey" => "a", "label" => $addButtonLabel, "disabled" => ($GLOBALS['gPermissionLevel'] < _READWRITE || $this->iReadonly ? true : false));
			if ($this->iAdditionalListButtons) {
				foreach ($this->iAdditionalListButtons as $buttonCode => $buttonInfo) {
					$buttonFunctions[$buttonCode] = $buttonInfo;
					$enableButtons[] = $buttonCode;
				}
			}
			$this->displayButtons($enableButtons, false, $buttonFunctions);
			?>
        </div> <!-- page_buttons_content -->
		<?php
		return true;
	}

	/**
	 *    function mainContent
	 *
	 *    The list will be filled in by ajax, so the only html markup added here is the empty table.
	 */
	function mainContent() {
		if (count($this->iFilters) > 0) {
			?>
            <div class="dialog-box" id="_filter_dialog">
                <div id="_filter_dialog_contents">
                    <div class='basic-form-line'>
                        <select id="_filter_conjunction" name="_filter_conjunction">
                            <option value='and'>Show records that match ALL of the following</option>
                            <option value='or'>Show records that match ANY of the following</option>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </select>
                    </div>
					<?php
					foreach ($this->iFilters as $columnName => $thisFilter) {
						if ($thisFilter['data_type'] == "header") {
							?>
                            <h3><?= htmlText($thisFilter['form_label']) ?></h3>
							<?php
							continue;
						}
						$dataTypeFound = false;
						$thisColumn = new DataColumn("_set_filter_" . $columnName);
						foreach ($thisFilter as $controlName => $controlValue) {
							if ($controlName == "data_type") {
								$dataTypeFound = true;
							}
							$thisColumn->setControlValue($controlName, $controlValue);
						}
						if (!$dataTypeFound) {
							$thisColumn->setControlValue("data_type", "tinyint");
						}
						$dataType = $thisColumn->getControlValue("data_type");
						if ($dataType == "select" and !array_key_exists("empty_text", $thisFilter)) {
							$thisColumn->setControlValue("empty_text", "[All]");
						}
						$thisColumn->setControlValue("tabindex", "");
						$checkbox = ($thisColumn->getControlValue("data_type") == "tinyint");
						if ($thisColumn->getControlValue("data_type") == "decimal") {
							$thisColumn->setControlValue("decimal_places", "2");
						}
						?>
                        <div class="basic-form-line" id="_set_filter_<?= $columnName ?>_row">
                            <label<?= (empty($thisColumn->getControlValue("not_null")) ? "" : " class='required-label'") ?> for="<?= "_set_filter_" . $columnName ?>"><?= htmlText($checkbox ? "" : $thisColumn->getControlValue("form_label")) ?></label>
							<?= $thisColumn->getControl($this) ?>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>
						<?php
					}
					?>
                </div>
            </div> <!-- filter_dialog -->
			<?php
		}
		if (count($this->iVisibleFilters) > 0) {
			?>
            <div id="_filter_section" class="longer-label">
				<?php
				foreach ($this->iVisibleFilters as $columnName => $thisFilter) {
					if ($thisFilter['data_type'] == "header") {
						?>
                        <h3><?= htmlText($thisFilter['form_label']) ?></h3>
						<?php
						continue;
					}
					$dataTypeFound = false;
					$thisColumn = new DataColumn("_set_filter_" . $columnName);
					foreach ($thisFilter as $controlName => $controlValue) {
						if ($controlName == "data_type") {
							$dataTypeFound = true;
						}
						$thisColumn->setControlValue($controlName, $controlValue);
					}
					if (!$dataTypeFound) {
						$thisColumn->setControlValue("data_type", "tinyint");
					}
					$dataType = $thisColumn->getControlValue("data_type");
					if ($dataType == "select" and !array_key_exists("empty_text", $thisFilter)) {
						$thisColumn->setControlValue("empty_text", "[All]");
					}
					$thisColumn->setControlValue("tabindex", "");
					$checkbox = ($thisColumn->getControlValue("data_type") == "tinyint");
					?>
                    <div class="basic-form-line" id="_set_filter_<?= $columnName ?>_row">
                        <label for="<?= "_set_filter_" . $columnName ?>"><?= htmlText($checkbox ? "" : $thisColumn->getControlValue("form_label")) ?></label>
						<?= $thisColumn->getControl($this) ?>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
					<?php
				}
				?>
            </div>
			<?php
		}
		if (method_exists($this->iPageObject, "beforeList")) {
			$this->iPageObject->beforeList();
		}
		echo "<div id='_maintenance_list_wrapper'><table id='_maintenance_list'></table></div>";
	}

	function inlinePageJavascript() {
		?>
        const filterText = "<?= str_replace("</script>", "", str_replace("<script", "", str_replace('"', "\\\"", trim(getPreference("MAINTENANCE_FILTER_TEXT", $GLOBALS['gPageRow']['page_code']), "\\")))) ?>";
		<?php
	}

	/**
	 *    function onLoadPageJavascript
	 *
	 *    javascript code that is executed once the page is loaded.
	 *
	 * $validDataTypes = array("varchar", "date", "int", "decimal", "tinyint", "text", "mediumtext","select");
	 */
	function onLoadPageJavascript() {
		if ($this->iPageObject->onLoadPageJavascript()) {
			return;
		}
		?>
        <script>
            $(function () {
				<?php if (count($this->iFilters) > 0) { ?>
                $("#_filter_button").removeClass("hidden");
				<?php } ?>
                $(document).on("change", ".query-select-column", function () {
                    const $thisRow = $(this).closest("tr");
                    const dataType = $thisRow.find(".query-select-column").find("option:selected").data("data_type");
                    const foreignKey = $(this).find("option:selected").data("foreign_key");
                    $thisRow.find(".query-select-comparator").val("").find("option[value!='']").remove();
                    if (empty($(this).val())) {
                        return;
                    }
                    let options = [];
                    switch (dataType) {
                        case "date":
                        case "bigint":
                        case "int":
                        case "decimal":
                            options.push({ key_value: "=", description: "Equals" });
                            options.push({ key_value: "<>", description: "Not Equals" });
                            options.push({ key_value: ">", description: "Greater Than" });
                            options.push({ key_value: ">=", description: "Greater Than or Equal" });
                            options.push({ key_value: "<", description: "Less Than" });
                            options.push({ key_value: "<=", description: "Less Than or Equal" });
                            options.push({ key_value: "is not null", description: "Is Not Empty" });
                            options.push({ key_value: "is null", description: "Is Empty" });
                            options.push({ key_value: "between", description: "Between" });
                            break;
                        case "select":
                            options.push({ key_value: "=", description: "Equals" });
                            options.push({ key_value: "<>", description: "Not Equals" });
                            options.push({ key_value: "is not null", description: "Is Not Empty" });
                            options.push({ key_value: "is null", description: "Is Empty" });
                            break;
                        case "tinyint":
                            options.push({ key_value: "true", description: "Is Set" });
                            options.push({ key_value: "false", description: "Not Set" });
                            break;
                        case "text":
                        case "mediumtext":
                        default:
                            options.push({ key_value: "=", description: "Equals" });
                            options.push({ key_value: "<>", description: "Not Equals" });
                            options.push({ key_value: ">", description: "Greater Than" });
                            options.push({ key_value: ">=", description: "Greater Than or Equal" });
                            options.push({ key_value: "<", description: "Less Than" });
                            options.push({ key_value: "<=", description: "Less Than or Equal" });
                            options.push({ key_value: "is not null", description: "Is Not Empty" });
                            options.push({ key_value: "is null", description: "Is Empty" });
                            options.push({ key_value: "starts", description: "Starts With" });
                            options.push({ key_value: "contains", description: "Contains" });
                            options.push({ key_value: "in", description: "Is In (comma separated list)" });
                            options.push({ key_value: "not in", description: "Is NOT In (comma separated list)" });
                            break;
                    }
                    for (const i in options) {
                        $thisRow.find(".query-select-comparator").append($("<option></option>").text(options[i].description).val(options[i].key_value));
                    }
                    $thisRow.find(".query-select-value-fields").html("");
                });
                $(document).on("change", ".query-select-comparator", function () {
                    const $thisRow = $(this).closest("tr");
                    const dataType = $thisRow.find(".query-select-column").find("option:selected").data("data_type");
                    const comparator = $(this).val();
                    const rowId = $thisRow.data("row_id");
                    const columnName = $thisRow.find(".query-select-column").find("option:selected").val();
                    let valueField = true;
                    let endField = false;
                    switch (comparator) {
                        case "is not null":
                        case "is null":
                        case "true":
                        case "false":
                            valueField = false;
                            break;
                        case "between":
                            endField = true;
                            break;
                    }
                    if (valueField) {
                        if ($thisRow.find(".query-select-value").length === 0) {
                            let valueControl = "";
                            switch (dataType) {
                                case "date":
                                    valueControl = "<input type='text' size='12' class='validate[required,custom[date]] date-field query-select-value' tabindex='10' name='query_select_criteria_field_value-" + rowId + "' id='query_select_criteria_field_value-" + rowId + "'>";
                                    break;
                                case "bigint":
                                case "int":
                                case "decimal":
                                    valueControl = "<input type='text' size='10' class='align-right validate[required,custom[number]] numeric-field query-select-value' data-decimal-places='2' tabindex='10' name='query_select_criteria_field_value-" + rowId + "' id='query_select_criteria_field_value-" + rowId + "'>";
                                    break;
                                case "select":
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_query_select_choices&column_name=" + columnName, function(returnArray) {
                                        if ("choices" in returnArray) {
                                            $thisRow.find(".query-select-value-fields").append("<select class='validate[required] query-select-value' name='query_select_criteria_field_value-" + rowId + "' id='query_select_criteria_field_value-" + rowId + "'><option value=''>[Select]</option></select>");
                                            for (const i in returnArray['choices']) {
                                                $("#query_select_criteria_field_value-" + rowId).append($("<option></option>").text(returnArray['choices'][i].description).val(returnArray['choices'][i].key_value));
                                            }
                                        } else {
                                            $thisRow.find(".query-select-value-fields").append("<input type='text' size='10' class='align-right validate[required,custom[number]] numeric-field query-select-value' data-decimal-places='2' tabindex='10' name='query_select_criteria_field_value-" + rowId + "' id='query_select_criteria_field_value-" + rowId + "'>");
                                        }
                                    });
                                    break;
                                default:
                                    valueControl = "<input type='text' size='50' maxlength='255' class='validate[required] query-select-value' tabindex='10' name='query_select_criteria_field_value-" + rowId + "' id='query_select_criteria_field_value-" + rowId + "'>";
                                    break;
                            }
                            if (!empty(valueControl)) {
                                $thisRow.find(".query-select-value-fields").append(valueControl);
                            }
                        }
                        $thisRow.find(".query-select-value").focus();
                    } else {
                        $thisRow.find(".query-select-value").remove();
                    }
                    if (endField) {
                        if ($thisRow.find(".query-select-end-value").length === 0) {
                            let valueControl = "";
                            switch (dataType) {
                                case "date":
                                    valueControl = "<input type='text' size='10' class='validate[required,custom[date]] date-field query-select-end-value' tabindex='10' name='query_select_criteria_field_end_value-" + rowId + "' id='query_select_criteria_field_end_value-" + rowId + "'>";
                                    break;
                                case "bigint":
                                case "int":
                                case "decimal":
                                    valueControl = "<input type='text' size='6' class='align-right validate[required,custom[number]] numeric-field query-select-end-value' data-decimal-places='2' tabindex='10' name='query_select_criteria_field_end_value-" + rowId + "' id='query_select_criteria_field_end_value-" + rowId + "'>";
                                    break;
                                case "select":
                                    break;
                                default:
                                    valueControl = "<input type='text' size='50' class='validate[required] query-select-end-value' tabindex='10' name='query_select_criteria_field_end_value-" + rowId + "' id='query_select_criteria_field_end_value-" + rowId + "'>";
                                    break;
                            }
                            if (!empty(valueControl)) {
                                $thisRow.find(".query-select-value-fields").append(valueControl);
                            }
                        }
                    } else {
                        $thisRow.find(".query-select-end-value").remove();
                    }
                });
                $("#_management_content").wrapInner("<form id='_edit_form'></form>");
                $("#_filter_section").find("input[type=checkbox],input[type=text],select").change(function (event) {
                    $("#_set_filters").val("1");
                    getDataList();
                    return true;
                });
                $(document).keydown(function (event) {
                    let dialogFound = false;
                    $(".ui-dialog-content").each(function () {
                        if ($(this).dialog("isOpen")) {
                            dialogFound = true;
                            return false;
                        }
                    });
                    if (dialogFound) {
                        return true;
                    }
                    if (event.which === 34) {
                        $(".page-next-button").first().trigger("click");
                        return false;
                    } else if (event.which === 33) {
                        $(".page-previous-button").first().trigger("click");
                        return false;
                    }
                    if (!$("#_filter_text").is(":focus") && (!$("#_popup_editor").hasClass('ui-dialog-content') || !$("#_popup_editor").dialog("isOpen"))) {
                        if (event.which === 39) {
                            $(".page-next-button").first().trigger("click");
                            return false;
                        } else if (event.which === 37) {
                            $(".page-previous-button").first().trigger("click");
                            return false;
                        }
                    }
                });
                $(document).on("tap click", ".delete-item", function () {
                    $(this).parent("li").remove();
                });
                $(document).on("tap click", "#_add_button", function () {
                    if (typeof beforeAddButton == "function") {
                        if (!beforeAddButton()) {
                            return false;
                        }
                    }
                    document.location = "<?= (empty($this->iAddUrl) ? $GLOBALS['gLinkUrl'] . "?url_page=new" : $this->iAddUrl) ?>";
                    return false;
                });
                $(document).on("tap click", "#_filter_button", function () {
                    $("#_filter_dialog").find("input,select").each(function () {
                        if ($(this).is("input[type=checkbox]")) {
                            $(this).data("initial_value", $(this).prop("checked") ? 1 : 0);
                        } else {
                            $(this).data("initial_value", $(this).val());
                        }
                    });
                    if ($(this).is("input[type=checkbox]")) {
                        $(this).prop("checked", $(this).data("initial_value") == "1");
                    } else {
                        $(this).val($(this).data("initial_value"));
                    }
                    $("#_filter_dialog").validationEngine();
                    $("#_filter_dialog").dialog({
                        width: 800,
                        modal: true,
                        draggable: true,
                        resizable: true,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        closeOnEscape: true,
                        show: 'fade',
                        close: function (event, ui) {
                            resetFilters();
                        },
                        title: '<?= getLanguageText("Filter the List") ?>',
                        buttons: {
                            "<?= getLanguageText("Save") ?>": function (event) {
                                if ($("#_filter_dialog_contents").validationEngine("validate")) {
                                    $("#_filter_dialog").dialog('destroy');
                                    $("#_set_filters").val("1");
                                    getDataList();
                                }
                            },
                            "<?= getLanguageText("Reset") ?>": function (event) {
                                $("#_filter_dialog").dialog('destroy');
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=clear_all_filters", function(returnArray) {
                                    setTimeout(function() {
                                        getDataList();
                                    },500)
                                });
                            },
                            "<?= getLanguageText("Cancel") ?>": function (event) {
                                $("#_filter_dialog").dialog('close');
                                resetFilters();
                            }
                        }
                    });
                    return false;
                });
                $("#_filter_text").keyup(function (event) {
                    $("#_search_button").data("show_all", "");
                    $("#_list_search_control").removeClass("unchanged");
                    if (event.which === 13 || event.which === 3) {
                        $("#_search_button").trigger("click");
                        return false;
                    }
                    return true;
                });
                $(document).on("tap click", "#_search_button", function (event) {
                    if ($(this).data("show_all") === "true") {
                        $(this).data("show_all", "");
                        $("#_filter_text").val("");
                        $("#_custom_where_value").val("");
                        $("#_show_selected").val("");
                        $("#_show_unselected").val("");
                    }
                    $("#_start_row").val("0");
                    $("#_set_filters").val("1");
                    getDataList();
                    return false;
                });
                $("#_page_number").change(function (event) {
                    $("#_start_row").val($(this).val());
                    $("#_set_filters").val("1");
                    getDataList();
                });
                $(document).on("tap click", ".page-next-button", function (event) {
                    if ($("#_start_row").val() !== $("#_next_start_row").val() && $("#_next_start_row").val().length > 0) {
                        $("#_start_row").val($("#_next_start_row").val());
                        $("#_set_filters").val("1");
                        getDataList();
                    }
                    return false;
                });
                $(document).on("tap click", ".page-previous-button", function (event) {
                    if ($("#_start_row").val() != $("#_previous_start_row").val() && $("#_previous_start_row").val().length > 0) {
                        $("#_start_row").val($("#_previous_start_row").val());
                        $("#_set_filters").val("1");
                        getDataList();
                    }
                    return false;
                });
                $(document).on("tap click", ".data-row", function (event) {
                    if (typeof dataRowClicked == "function") {
                        if (!dataRowClicked()) {
                            return false;
                        }
                    }
					<?php if ($this->canUsePopupEditor()) { ?>
					<?php if (getPreference("maintenance_use_popup", $GLOBALS['gPageCode'])) { ?>
                    if (!event.metaKey || event.ctrlKey) {
						<?php } else { ?>
                        if (event.metaKey || event.ctrlKey) {
							<?php } ?>
                            popupEdit($(this).data('primary_id'));
                        } else {
                            document.location = <?= (empty($this->iListItemUrl) ? "\"" . $GLOBALS['gLinkUrl'] . "?url_page=show&primary_id=\" + $(this).data('primary_id')" : "\"" . $this->iListItemUrl . "\"") ?>;
                        }
						<?php } else { ?>
                        if (event.altKey || event.metaKey || event.ctrlKey) {
                            window.open(<?= (empty($this->iListItemUrl) ? "\"" . $GLOBALS['gLinkUrl'] . "?url_page=show&primary_id=\" + $(this).data('primary_id')" : "\"" . $this->iListItemUrl . "\"") ?>);
                        } else {
                            document.location = <?= (empty($this->iListItemUrl) ? "\"" . $GLOBALS['gLinkUrl'] . "?url_page=show&primary_id=\" + $(this).data('primary_id')" : "\"" . $this->iListItemUrl . "\"") ?>;
                        }
						<?php } ?>
                    }
                )
                ;
                $(document).on("tap click", ".column-header", function () {
                    if (!$(this).is(".no-sort")) {
                        $("#_start_row").val("0");
                        $("#_sort_order_column").val($(this).data("column_name"));
                        $("#_reverse_sort_order").val(($(this).is(".sort-normal") ? "true" : "false"));
                        $("#_set_filters").val("1");
                        getDataList();
                    }
                });
                if ($(".page-next-button").is("button")) {
                    disableButtons($(".page-next-button"));
                }
                $(document).on("click", ".action-option", function () {
                    const thisValue = $(this).data("value");
                    switch (thisValue) {
                        case "guisort":
                        case "spreadsheet":
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=" + thisValue;
                            break;
                        case "resetsort":
                            $("#_reset_sort_order").val("");
                            if ($("#reset_sort_order_form").validationEngine("validate")) {
                                $("#_reset_sort_order_dialog").dialog({
                                    width: 600,
                                    modal: true,
                                    draggable: true,
                                    resizable: false,
                                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                                    closeOnEscape: true,
                                    show: 'fade',
                                    title: 'Reset Sort Order Values',
                                    buttons: {
                                        "<?= getLanguageText("Save") ?>": function (event) {
                                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=" + thisValue + "&sort_order=" + $("#_reset_sort_order").val();
                                            $("#_reset_sort_order_dialog").dialog('close');
                                        },
                                        "<?= getLanguageText("Cancel") ?>": function (event) {
                                            $("#_reset_sort_order_dialog").dialog('close');
                                        }
                                    }
                                });
                            }
                            break;
                        case "preferences":
                            preferences();
                            break;
					<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
                        case "customwhere":
                            customWhere();
                            break;
					<?php } ?>
					<?php if (!$this->iRemoveExport) { ?>
                        case "exportcsv":
                        case "exportallcsv":
                            $("#_export_frame").attr("src", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=" + thisValue);
                            break;
					<?php } ?>
                        case "queryselect":
                            querySelect();
                            break;
                        case "showselected":
                            $("#_show_selected").val("1");
                            $("#_show_unselected").val("");
                            getDataList();
                            break;
                        case "showunselected":
                            $("#_show_selected").val("");
                            $("#_show_unselected").val("1");
                            getDataList();
                            break;
                        case "clearall":
                            $("#_show_selected").val("");
                            $("#_show_unselected").val("");
                            getDataList(thisValue);
                            break;
                        case "showall":
                            $("#_show_selected").val("");
                            $("#_show_unselected").val("");
                            $("#_filter_text").val("");
                            $("#_custom_where_value").val("");
                            $("#_start_row").val("0");
                            $("#_set_filters").val("1");
                        default:
                            if (typeof customActions == "function") {
                                if (customActions(thisValue)) {
                                    break;
                                }
                            }
                            getDataList(thisValue);
                            break;
                    }
                });
                $(document).on("tap click", "#_preference_close_box", function () {
                    $("#_cancel_preferences").trigger("click");
                });
                $(document).on("tap click", ".select-checkbox", function (event) {
                    const alreadyChecked = $(this).find(".fa-check-square").length > 0;
                    if (alreadyChecked) {
                        $(this).find("span").removeClass("fa-check-square");
                        $(this).find("span").addClass("fa-square");
                    } else {
                        $(this).find("span").removeClass("fa-square");
                        $(this).find("span").addClass("fa-check-square");
                    }
                    event.stopPropagation();
                    $("body").addClass("no-waiting-for-ajax");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_row&primary_id=" + $(this).closest("tr").data("primary_id") + "&set=" + (alreadyChecked ? "no" : "yes"), function(returnArray) {
                        $(".page-select-count").html(returnArray['.page-select-count']);
                        if (returnArray['.page-select-count'] == 0) {
                        	$(".page-select-count-wrapper").addClass("hidden");
                        } else {
                        	$(".page-select-count-wrapper").removeClass("hidden");
                        }
                        $("body").removeClass("no-waiting-for-ajax");
                    });
                });
				<?php if ($this->canUsePopupEditor()) { ?>
                $(document).on("tap click", ".popup-edit", function () {
                    const primaryId = $(this).closest(".data-row").data('primary_id');
                    popupEdit(primaryId);
                    return false;
                });
				<?php } ?>
				<?php if (!empty($this->iErrorMessage)) { ?>
                displayErrorMessage("<?= htmlText($this->iErrorMessage) ?>");
				<?php } ?>
                $("#_filter_text").val(filterText);
                if (empty(filterText)) {
                    $("#_list_search_control").removeClass("unchanged");
                }
				<?php
				$userPreferenceCodes = array(
					"item_count",
					"list_data_length",
					"list_shaded",
					"save_no_list",
					"show_inactive",
					"use_popup"
				);
				foreach ($userPreferenceCodes as $preferenceCode) {
				?>
                if ($("#maintenance_<?= $preferenceCode ?>").length > 0) {
                    if ($("#maintenance_<?= $preferenceCode ?>").is("input[type=checkbox]")) {
                        $("#maintenance_<?= $preferenceCode ?>").prop("checked", <?= (getPreference("MAINTENANCE_" . strtoupper($preferenceCode), $GLOBALS['gPageRow']['page_code']) == "true" ? "true" : "false") ?>);
                    } else {
                        $("#maintenance_<?= $preferenceCode ?>").val("<?= str_replace('"', "\\\"", trim(getPreference("MAINTENANCE_" . strtoupper($preferenceCode), $GLOBALS['gPageRow']['page_code']), "\\")) ?>");
                    }
                }
				<?php
				}
				?>
                displayListHeader();
                getDataList();
                if ($("#_edit_form").length > 0 && $().validationEngine) {
                    $("#_edit_form").validationEngine();
                }
            });
        </script>
		<?php
	}

	function canUsePopupEditor() {
		if (!$this->iDataSource) {
			return false;
		}
		$primaryTable = $this->iDataSource->getPrimaryTable();
		if (!$primaryTable) {
			return false;
		}
		$tableName = $primaryTable->getName();
		if (!Database::isControlTable($tableName)) {
			return false;
		}
		if (!empty($GLOBALS['gPageRow']['script_filename'])) {
			return false;
		}
		$pageControlId = getFieldFromId("page_control_id", "page_controls", "page_id", $GLOBALS['gPageId'], "control_name = 'data_type' and control_value in ('custom','custom_control')");
		if (!empty($pageControlId)) {
			return false;
		}
		$pageControlId = getFieldFromId("page_control_id", "page_controls", "page_id", $GLOBALS['gPageId'], "control_name = 'wysiwyg'");
		if (!empty($pageControlId)) {
			return false;
		}
		return true;
	}

	/**
	 *    function pageJavascript
	 *
	 *    javascript code, mostly functions, that are added to the page.
	 */
	function pageJavascript() {
		if (function_exists("_localServerJavascript")) {
			_localServerJavascript();
		}
		if ($this->iPageObject->pageJavascript()) {
			return;
		}
		?>
        <script>
			<?php if ($this->canUsePopupEditor()) { ?>
            function popupEdit(primaryId) {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_page=show&url_action=get_record&primary_id=" + primaryId, function(returnArray) {
                    $("#_popup_form").clearForm();
                    for (let i in returnArray) {
                        if (empty(i)) {
                            continue;
                        }
                        if (i.substr(0, 1) === ".") {
                            if ($(i).length > 0) {
                                $(i).html(returnArray[i]);
                            }
                            continue;
                        }
                        if (typeof returnArray[i] == "object" && "data_value" in returnArray[i]) {
                            if ($("input[type=radio][name='" + i + "']").length > 0) {
                                $("input[type=radio][name='" + i + "']").prop("checked", false);
                                $("input[type=radio][name='" + i + "'][value='" + returnArray[i]['data_value'] + "']").prop("checked", true);
                            } else if ($("#" + i).is("input[type=checkbox]")) {
                                $("#" + i).prop("checked", returnArray[i].data_value == 1);
                            } else {
                                $("#" + i).val(returnArray[i].data_value);
                            }
                        }
                    }
                    $("#_popup_form").find("select,input[type=checkbox],input[type=radio]").filter(".not-editable").prop("disabled", true);
                    $("#_popup_form").find("input[type=password],input[type=text],textarea").filter(".not-editable").prop("readonly", true);
                    $("#_popup_editor").dialog({
                        width: 800,
                        modal: true,
                        draggable: false,
                        resizable: false,
                        closeOnEscape: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        show: 'fade',
                        title: 'Maintenance Form',
                        buttons: [
                            {
                                text: "<?= getLanguageText("Save") ?>",
                                tabindex: 10,
                                click: function (event) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_page=show&url_action=save_changes", $("#_popup_form").serialize(), function(returnArray) {
                                        if ("error_message" in returnArray) {
                                            $("#popup_error_message").html(returnArray['error_message']);
                                        } else {
                                            $("#_popup_editor").dialog('close');
                                            displayInfoMessage("<?= getSystemMessage("RECORD_UPDATED") ?>");
                                            getDataList();
                                        }
                                    });
                                }
                            },
                            {
                                text: "<?= getLanguageText("Cancel") ?>",
                                tabindex: 10,
                                click: function (event) {
                                    $("#_popup_editor").dialog('close');
                                }
                            }
                        ]
                    });
                });
            }
			<?php } ?>
            function displayListHeader() {
                $(".page-action-selector").html($("#_page_action_selector_content").html());
                $("#_page_action_selector_content").html("");
                $(".page-heading").html("<?= htmlText($GLOBALS['gPageRow']['description']) ?>");
                $(".page-buttons,.page-list-buttons").html($("#_page_buttons_content").html());
                $("#_page_buttons_content").html("");
                $("#_edit_form").prepend($("#_page_hidden_elements_content").html());
                $("#_page_hidden_elements_content").html("");
                $(".page-form-control").hide();
                $(".page-controls").show();
            }
			<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
            function customWhere() {
                $("#_custom_where_entry").val($("#_custom_where_value").val());
                $("#_custom_where").dialog({
                    height: 300,
                    width: 600,
                    modal: true,
                    draggable: false,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    show: 'fade',
                    title: '<?= getLanguageText("Custom Where Statement") ?>',
                    buttons: {
                        "<?= getLanguageText("Save") ?>": function (event) {
                            $("#_custom_where_value").val($("#_custom_where_entry").val());
                            $("#_custom_where").dialog('close');
                            getDataList();
                        },
                        "<?= getLanguageText("Cancel") ?>": function (event) {
                            $("#_custom_where").dialog('close');
                        }
                    }
                });
            }
			<?php } ?>

            function querySelect() {
                $("#_query_select_criteria_table").find(".editable-list-remove").trigger("click");
                $("#_query_select_criteria_table").find(".editable-list-add").trigger("click");
                $("#_query_select_dialog").dialog({
                    height: 500,
                    width: 1000,
                    modal: true,
                    draggable: true,
                    resizable: true,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    show: 'fade',
                    title: '<?= getLanguageText("Query Select") ?>',
                    buttons: {
                        "<?= getLanguageText("Select") ?>": function (event) {
                            if ($("#_query_select_form").validationEngine("validate")) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_by_query", $("#_query_select_form").serialize(), function(returnArray) {
                                    $("#_show_selected").val("1");
                                    $("#_show_unselected").val("");
                                    getDataList();
                                });
                                $("#_query_select_dialog").dialog('close');
                                $("#_query_select_criteria_table").find(".editable-list-remove").trigger("click");
                                $("#_query_select_criteria_table").find(".editable-list-add").trigger("click");
                            }
                        },
                        "<?= getLanguageText("Cancel") ?>": function (event) {
                            $("#_query_select_dialog").dialog('close');
                            $("#_query_select_criteria_table").find(".editable-list-remove").trigger("click");
                            $("#_query_select_criteria_table").find(".editable-list-add").trigger("click");
                        }
                    }
                });
            }

            function preferences() {
                $("#_preferences").dialog({
                    width: 1000,
                    modal: true,
                    draggable: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    show: 'fade',
                    title: '<?= getLanguageText("Preferences") ?>',
                    close: function (event, ui) {
                        preferenceAction(false);
                    },
                    buttons: {
                        "<?= getLanguageText("Save") ?>": function (event) {
                            preferenceAction(true);
                            $("#_preferences").dialog('close');
                        },
                        "<?= getLanguageText("Cancel") ?>": function (event) {
                            $("#_preferences").dialog('close');
                        }
                    }
                });
            }

            function preferenceAction(savePreferences) {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=preferences&subaction=" + (savePreferences ? "save" : "cancel"), $("#_preference_form").serialize(), function(returnArray) {
                    $("#maintenance_item_count").val(returnArray['maintenance_item_count']);
                    getDataList();
                });
            }

            function resetFilters(clearAll) {
                if (!empty(clearAll)) {
                    $("#_filter_dialog").find("input,select").each(function () {
                        $(this).data("initial_value", "");
                    });
                }
                $("#_filter_dialog").find("input,select").each(function () {
                    if ($(this).is("input[type=checkbox]")) {
                        $(this).prop("checked", $(this).data("initial_value") == "1");
                    } else {
                        $(this).val($(this).data("initial_value"));
                    }
                });
            }

            function getDataList(subaction) {
                subaction = typeof subaction !== 'undefined' ? subaction : "";
                disableButtons();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_data_list&subaction=" + subaction, $("#_edit_form").serialize(), function(returnArray) {
                    enableButtons();

                    if ("use_list_fragment" in returnArray) {
                        $("#_maintenance_list_wrapper").html(returnArray['list_fragment_contents']);
                    } else {
                        $("#_maintenance_list tr").remove();
                        $("#_maintenance_list").removeClass("small-text-size");
                        $("#_maintenance_list").removeClass("medium-small-text-size");
                        $("#_maintenance_list").removeClass("medium-large-text-size");
                        $("#_maintenance_list").removeClass("large-text-size");
                        $("#_maintenance_list").addClass(returnArray['text_size'] + "-text-size");
                        if ("column_headers" in returnArray && typeof returnArray['column_headers'] == "object") {
                            let headerRow = "<tr><th>&nbsp;</th>";
                            for (let i in returnArray['column_headers']) {
                                headerRow += "<th data-column_name='" + returnArray['column_headers'][i]['column_name'] + "' class='column-header " + returnArray['column_headers'][i]['class_names'] + "'>" + returnArray['column_headers'][i]['description'] + "</th>";
                            }
                            headerRow += "<th></th></tr>";
                            $("#_maintenance_list").append(headerRow);
                        }
                        if ("data_list" in returnArray && typeof returnArray['data_list'] == "object") {
                            for (let i in returnArray['data_list']) {
                                let rowClasses = "";
                                if ("row_classes" in returnArray['data_list'][i]) {
                                    rowClasses = " " + returnArray['data_list'][i]['row_classes'];
                                }
                                let dataRow = "<tr class='data-row" + rowClasses + "' data-primary_id='" + returnArray['data_list'][i]['_primary_id'] + "'><td class='select-checkbox'><span class='far fa-" + (returnArray['data_list'][i]['_selected'] == 1 ? "check-" : "") + "square'></span></td>";
                                for (let j in returnArray['column_headers']) {
                                    let columnName = (returnArray['column_headers'][j]['column_name'].indexOf(".") < 0 ? returnArray['column_headers'][j]['column_name'] : returnArray['column_headers'][j]['column_name'].substring(returnArray['column_headers'][j]['column_name'].indexOf(".") + 1));
                                    dataRow += "<td class='data-row-data " + returnArray['data_list'][i][columnName]['class_names'] + "'>" + returnArray['data_list'][i][columnName]['data_value'] + "</td>";
                                }
                                dataRow += "<td><?= ($this->canUsePopupEditor() ? "<button class='popup-edit'><span class='fa fa-bolt'></span></button>" : "") ?></td></tr>";
                                $("#_maintenance_list").append(dataRow);
                            }
                        }
                    }
                    for (let i in returnArray) {
                        if (i.substr(0, 1) == ".") {
                            if ($(i).length > 0) {
                                $(i).html(returnArray[i]);
                            }
                        } else if (typeof returnArray[i] == "number" || typeof returnArray[i] == "string") {
                            if ($("#" + i).is("input[type=checkbox]")) {
                                $("#" + i).prop("checked", returnArray[i] == "1")
                            } else if ($("#" + i).is("span")) {
                                $("span#" + i).html(returnArray[i]);
                            } else {
                                $("#" + i).val(returnArray[i]);
                            }
                        } else if ((Array.isArray(returnArray[i]) || typeof returnArray[i] === "object") && $("select#" + i).length > 0) {
                            if (!("data_value" in returnArray[i])) {
                                $("#" + i + " option").remove();
                                let selectedOption = "";
                                for (let j in returnArray[i]) {
                                    $("#" + i).append("<option value='" + returnArray[i][j]['value'] + "'>" + returnArray[i][j]['description'] + "</option>");
                                    if ("selected" in returnArray[i][j]) {
                                        selectedOption = returnArray[i][j]['value'];
                                    }
                                }
                                if (!empty(selectedOption)) {
                                    $("#" + i).val(selectedOption);
                                }
                            } else {
                                $("#" + i).val(returnArray[i]['data_value']);
                            }
                        } else if (returnArray[i] != null && typeof returnArray[i] == "object" && "data_value" in returnArray[i]) {
                            if ($("#" + i).is("input[type=checkbox]")) {
                                $("#" + i).prop("checked", returnArray[i]['data_value'] == "1").data("initial_value", returnArray[i]['data_value']);
                            } else if ($("#" + i).is("div") || $("#" + i).is("span") || $("#" + i).is("td") || $("#" + i).is("tr")) {
                                $("span#" + i).html(returnArray[i]['data_value']);
                            } else {
                                $("input#" + i).val(returnArray[i]['data_value']);
                            }
                        }
                    }
					if (empty(returnArray['.page-select-count'])) {
						$(".page-select-count-wrapper").addClass("hidden");
					} else {
						$(".page-select-count-wrapper").removeClass("hidden");
					}
                    if ("database_query_count" in returnArray && $("#database_query_count").length > 0) {
                        $("#database_query_count").html($("#database_query_count").html() + "," + returnArray['database_query_count']);
                    }
                    if ("filter_set" in returnArray) {
                        $("#_filters_on").addClass("filters-on");
                        $("#_filter_button").addClass("filters-on");
                    } else {
                        $("#_filters_on").removeClass("filters-on");
                        $("#_filter_button").removeClass("filters-on");
                    }
                    if ("list_shaded" in returnArray && returnArray['list_shaded']) {
                        $("#_maintenance_list").addClass("shaded");
                    } else {
                        $("#_maintenance_list").removeClass("shaded");
                    }
                    if ($("#_next_start_row").val().length === 0) {
                        disableButtons($(".page-next-button"));
                    } else {
                        enableButtons($(".page-next-button"));
                    }
                    if ($("#_previous_start_row").val().length === 0) {
                        disableButtons($(".page-previous-button"));
                    } else {
                        enableButtons($(".page-previous-button"));
                    }
                    $("#_search_button").data("show_all", "true");
                    if (empty($("#_filter_text").val())) {
                        $("#_list_search_control").removeClass("unchanged");
                    } else {
                        $("#_list_search_control").addClass("unchanged");
                    }
                    if (typeof afterGetDataList == "function") {
                        afterGetDataList();
                    }
                });
            }
        </script>
		<?php
	}

	function getDataList() {
		$primaryTableKey = $this->iDataSource->getPrimaryTable()->getName() . "." . $this->iDataSource->getPrimaryTable()->getPrimaryKey();
		if (array_key_exists("_sort_order_column", $_POST) && !empty($_POST['_sort_order_column'])) {
            if ($primaryTableKey == $_POST['_sort_order_column']) {
	            setUserPreference("MAINTENANCE_SECONDARY_SORT_ORDER_COLUMN", "", $GLOBALS['gPageRow']['page_code']);
	        } else {
	            $originalSortOrderColumn = getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
	            if ($originalSortOrderColumn != $_POST['_sort_order_column']) {
		            setUserPreference("MAINTENANCE_SECONDARY_SORT_ORDER_COLUMN", getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']), $GLOBALS['gPageRow']['page_code']);
		            setUserPreference("MAINTENANCE_SECONDARY_REVERSE_SORT_ORDER", getPreference("MAINTENANCE_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']), $GLOBALS['gPageRow']['page_code']);
	            }
            }
			setUserPreference("MAINTENANCE_SORT_ORDER_COLUMN", $_POST['_sort_order_column'], $GLOBALS['gPageRow']['page_code']);
			setUserPreference("MAINTENANCE_REVERSE_SORT_ORDER", $_POST['_reverse_sort_order'], $GLOBALS['gPageRow']['page_code']);
		}
		$userPreferenceCodes = array(
            "show_selected",
            "show_unselected",
			"item_count",
			"list_data_length",
			"text_size",
			"list_shaded",
			"save_no_list",
			"show_inactive",
			"use_popup",
			"start_row",
			"custom_where_value",
			"filter_text",
			"filter_column"
		);
		foreach ($userPreferenceCodes as $preferenceCode) {
			if (array_key_exists("maintenance_" . $preferenceCode, $_POST)) {
				setUserPreference("MAINTENANCE_" . strtoupper($preferenceCode), trim($_POST['maintenance_' . $preferenceCode]), $GLOBALS['gPageRow']['page_code']);
			}
			if (array_key_exists("_" . $preferenceCode, $_POST)) {
				setUserPreference("MAINTENANCE_" . strtoupper($preferenceCode), trim($_POST['_' . $preferenceCode]), $GLOBALS['gPageRow']['page_code']);
			}
		}
		$returnArray = array();
		if (count($this->iFilters) > 0 || count($this->iVisibleFilters) > 0) {
			$setFilters = array();
			if (array_key_exists("_set_filters", $_POST) && $_POST['_set_filters'] == "1") {
				foreach (array_merge($this->iFilters, $this->iVisibleFilters) as $filterCode => $filterInfo) {
					$setFilters[$filterCode] = $_POST["_set_filter_" . $filterCode];
				}
				$setFilters['_filter_conjunction'] = $_POST['_filter_conjunction'];
				setUserPreference("MAINTENANCE_SET_FILTERS", jsonEncode($setFilters), $GLOBALS['gPageRow']['page_code']);
				if (method_exists($this->iPageObject, "filtersChanged")) {
					$this->iPageObject->filtersChanged($setFilters);
				}
			} else {
				$setFilterText = getPreference("MAINTENANCE_SET_FILTERS", $GLOBALS['gPageRow']['page_code']);
				if (strlen($setFilterText) > 0) {
					$setFilters = json_decode($setFilterText, true);
				} else {
					foreach (array_merge($this->iFilters, $this->iVisibleFilters) as $filterCode => $filterInfo) {
						if ($filterInfo['set_default'] && ($filterInfo['data_type'] == "tinyint" || empty($filterInfo['data_type']))) {
							$setFilters[$filterCode] = 1;
						}
					}
					setUserPreference("MAINTENANCE_SET_FILTERS", jsonEncode($setFilters), $GLOBALS['gPageRow']['page_code']);
				}
				if (method_exists($this->iPageObject, "filtersLoaded")) {
					$this->iPageObject->filtersLoaded($setFilters);
				}
			}
			if (method_exists($this->iPageObject, "afterSetFilters")) {
				$this->iPageObject->afterSetFilters();
			}
			if (!array_key_exists("_filter_conjunction", $setFilters)) {
				$setFilters['_filter_conjunction'] = "and";
			}
			$returnArray['_filter_conjunction'] = array("data_value" => $setFilters['_filter_conjunction']);
			$filterAndWhere = "";
			$filterWhere = "";
			foreach (array_merge($this->iFilters, $this->iVisibleFilters) as $filterCode => $filterInfo) {
				if (!$filterInfo['visible_filter'] && empty($filterInfo['conjunction'])) {
					$filterInfo['conjunction'] = $setFilters['_filter_conjunction'];
				}
				if (strlen($setFilters[$filterCode]) == 0 && !empty($filterInfo['default_value'])) {
					$setFilters[$filterCode] = $filterInfo['default_value'];
				}
				if (method_exists($this->iPageObject, "filterCustomWhere")) {
					$thisWhere = $this->iPageObject->filterCustomWhere($filterCode, $setFilters[$filterCode]);
					if (!empty($thisWhere)) {
						$filterInfo['where'] = $thisWhere;
					}
				}
				if (!empty($filterInfo['where'])) {
					if (!empty($setFilters[$filterCode])) {
						$filterValueParameter = ($filterInfo['data_type'] == "date" ? makeDateParameter($setFilters[$filterCode]) : makeParameter($setFilters[$filterCode]));
						$filterLikeParameter = makeParameter("%" . $setFilters[$filterCode] . "%");
						if ($filterInfo['conjunction'] == "and") {
							$filterAndWhere .= (empty($filterAndWhere) ? "" : " and ") . "(" . str_replace("%key_value%", $setFilters[$filterCode], str_replace("%filter_value%", $filterValueParameter, str_replace("%like_value%", $filterLikeParameter, $filterInfo['where']))) . ")";
						} else {
							$filterWhere .= (empty($filterWhere) ? "" : " or ") . "(" . str_replace("%key_value%", $setFilters[$filterCode], str_replace("%filter_value%", $filterValueParameter, str_replace("%like_value%", $filterLikeParameter, $filterInfo['where']))) . ")";
						}
					} else {
						if (!empty($filterInfo['not_where'])) {
							if ($filterInfo['conjunction'] == "and") {
								$filterAndWhere .= (empty($filterAndWhere) ? "" : " and ") . "(" . $filterInfo['not_where'] . ")";
							} else {
								$filterWhere .= (empty($filterWhere) ? "" : " or ") . "(" . $filterInfo['not_where'] . ")";
							}
						}
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
        if (!empty($_POST['_show_selected'])) {
            $this->iDataSource->addFilterWhere($this->iDataSource->getPrimaryTable()->getName() . "." .
                $this->iDataSource->getPrimaryTable()->getPrimaryKey() . " in (select primary_identifier from selected_rows " .
                "where user_id = " . $GLOBALS['gUserId'] . " and page_id = " . $GLOBALS['gPageId'] . ")");
        }

        if (!empty($_POST['_show_unselected'])) {
            $this->iDataSource->addFilterWhere($this->iDataSource->getPrimaryTable()->getName() . "." .
                $this->iDataSource->getPrimaryTable()->getPrimaryKey() . " not in (select primary_identifier from selected_rows " .
                "where user_id = " . $GLOBALS['gUserId'] . " and page_id = " . $GLOBALS['gPageId'] . ")");
        }

        $subaction = $_GET['subaction'];
		if (!empty($GLOBALS['gUserId']) && !empty($GLOBALS['gPageId'])) {
			switch ($subaction) {
				case "clearall":
					executeQuery("delete from selected_rows where user_id = ? and page_id = ?", array($GLOBALS['gUserId'], $GLOBALS['gPageId']));
					break;
			}
		}
		if ($this->iDataSource->getPrimaryTable()->columnExists("inactive") && !getPreference("MAINTENANCE_SHOW_INACTIVE", $GLOBALS['gPageRow']['page_code'])) {
			$this->iDataSource->addFilterWhere($this->iDataSource->getPrimaryTable()->getName() . ".inactive = 0");
		}

		$returnArray['list_shaded'] = getPreference("MAINTENANCE_LIST_SHADED", $GLOBALS['gPageRow']['page_code']);
		$returnArray['_filter_text'] = getPreference("MAINTENANCE_FILTER_TEXT", $GLOBALS['gPageRow']['page_code']);
		if (method_exists($this->iPageObject, "filterTextProcessing")) {
			$this->iPageObject->filterTextProcessing($returnArray['_filter_text']);
		} else {
			$this->iDataSource->setFilterText($returnArray['_filter_text']);
		}
		if ($GLOBALS['gUserRow']['superuser_flag']) {
			$customWhereValue = getPreference("MAINTENANCE_CUSTOM_WHERE_VALUE", $GLOBALS['gPageRow']['page_code']);
			if (!empty($customWhereValue)) {
				$this->iDataSource->addFilterWhere($customWhereValue);
			}
		}

		$foreignKeys = $this->iDataSource->getForeignKeyList();
		$customSearchFields = $this->iDataSource->searchFieldsSet();
		if (!$customSearchFields) {
			foreach ($foreignKeys as $columnName => $foreignKeyInfo) {
				if (!in_array($columnName, $this->iExcludeSearchColumns)) {
					if (!empty($foreignKeyInfo['description'])) {
						$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => $foreignKeyInfo['referenced_table_name'],
							"referenced_column_name" => $foreignKeyInfo['referenced_column_name'], "foreign_key" => $foreignKeyInfo['column_name'],
							"description" => $foreignKeyInfo['description'], "table_name" => $foreignKeyInfo['table_name']));
					}
				}
			}
		}

		$columns = $this->iDataSource->getColumns();
		foreach ($foreignKeys as $columnName => $foreignKeyInfo) {
			if (in_array($columnName, $this->iExcludeListColumns)) {
				continue;
			}
			$thisColumn = $columns[$columnName];
			if (!empty($thisColumn) && $thisColumn->getControlValue("hide_in_list")) {
				continue;
			}
			if (!empty($foreignKeyInfo['description'])) {
				$this->iDataSource->addColumnControl($foreignKeyInfo['column_name'] . "_display", "select_value",
					"select concat_ws(' '," . implode(",", $foreignKeyInfo['description']) . ") from " .
					$foreignKeyInfo['referenced_table_name'] . " as subselect_table where subselect_table." .
					$foreignKeyInfo['referenced_column_name'] . " = " . $columnName);
			}
		}

		$allSearchColumns = array();
		foreach ($columns as $columnName => $thisColumn) {
			switch ($thisColumn->getControlValue('mysql_type')) {
				case "date":
				case "time":
				case "bigint":
				case "int":
				case "text":
				case "mediumtext":
				case "varchar":
				case "decimal":
					if ($columns[$columnName]->getControlValue("primary_key") || !in_array($columnName, $this->iExcludeSearchColumns)) {
						$allSearchColumns[] = $columnName;
					}
					break;
				default;
					break;
			}
		}

		$sortOrderColumn = getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$secondarySortOrderColumn = getPreference("MAINTENANCE_SECONDARY_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$listColumns = explode(",", getPreference("MAINTENANCE_LIST_COLUMNS", $GLOBALS['gPageRow']['page_code']));
		if (count($listColumns) == 0 || (count($listColumns) == 1 && empty($listColumns[0]))) {
			$listColumns = array();
			if (count($this->iListSortOrder) > 0) {
				foreach ($this->iListSortOrder as $columnName) {
					if (count($listColumns) >= $this->iMaximumListColumns && $this->iMaximumListColumns > 0) {
						break;
					}
					$fullColumnName = "";
					if (strpos($columnName, ".") === false) {
						$fullColumnName = $this->iDataSource->getPrimaryTable()->getName() . "." . $columnName;
					}
					if ($fullColumnName == $primaryTableKey) {
						continue;
					}
					if (!empty($fullColumnName) && array_key_exists($fullColumnName, $columns)) {
						$listColumns[] = $fullColumnName;
					} else {
						if (array_key_exists($columnName, $columns)) {
							$listColumns[] = $columnName;
						}
					}
				}
			}
			foreach ($columns as $columnName => $thisColumn) {
				if (count($listColumns) >= $this->iMaximumListColumns && $this->iMaximumListColumns > 0) {
					break;
				}
				if (in_array($columnName, $listColumns) || $columnName == $primaryTableKey || $columnName == $this->iDataSource->getPrimaryTable()->getPrimaryKey()) {
					continue;
				}
				$dataType = $thisColumn->getControlValue("data_type");
				$subType = $thisColumn->getControlValue('subtype');
				if (empty($thisColumn->getControlValue('hide_in_list')) && !in_array($columnName, $this->iExcludeListColumns) && $dataType != "longblob" && $dataType != "custom_control" && $dataType != "hidden" && $dataType != "custom" && $dataType != "button" && empty($subType)) {
					$listColumns[] = $columnName;
				}
			}
		}
		foreach ($listColumns as $thisIndex => $columnName) {
			if (!array_key_exists($columnName, $columns) || $columns[$columnName]->getControlValue("primary_key") || in_array($columnName, $this->iExcludeListColumns) || !empty($thisColumn->getControlValue('hide_in_list')) ||
				$columns[$columnName]->getControlValue("data_type") == "longblob" || $columns[$columnName]->getControlValue("data_type") == "custom_control" || $columns[$columnName]->getControlValue("data_type") == "custom" ||
				$columns[$columnName]->getControlValue("data_type") == "button") {
				unset($listColumns[$thisIndex]);
				continue;
			}
		}
		$hideIdColumn = getPreference("MAINTENANCE_HIDE_IDS", $GLOBALS['gPageRow']['page_code']);
		$primaryKeyColumn = $columns[$primaryTableKey];
		if (empty($hideIdColumn)) {
			if (empty($primaryKeyColumn) || empty($primaryKeyColumn->getControlValue("hide_in_list"))) {
				array_unshift($listColumns, $primaryTableKey);
			}
		}
		$reverseSortOrder = false;
		if (empty($sortOrderColumn) || !in_array($sortOrderColumn, $listColumns)) {
			$sortOrderColumn = $this->iDefaultSortOrderColumn;
			if (!empty($sortOrderColumn) && strpos(".", $sortOrderColumn) === false) {
				if ($this->iDataSource->getPrimaryTable()->columnExists($sortOrderColumn)) {
					$sortOrderColumn = $this->iDataSource->getPrimaryTable()->getName() . "." . $sortOrderColumn;
				} else if ($this->iDataSource->getJoinTable() && $this->iDataSource->getJoinTable()->columnExists($sortOrderColumn)) {
					$sortOrderColumn = $this->iDataSource->getJoinTable()->getName() . "." . $sortOrderColumn;
				}
			}
			$reverseSortOrder = $this->iDefaultReverseSortOrder;
			setUserPreference("MAINTENANCE_SORT_ORDER_COLUMN", $sortOrderColumn, $GLOBALS['gPageRow']['page_code']);
			setUserPreference("MAINTENANCE_REVERSE_SORT_ORDER", ($reverseSortOrder ? "true" : "false"), $GLOBALS['gPageRow']['page_code']);
			$secondarySortOrderColumn = "";
			setUserPreference("MAINTENANCE_SECONDARY_SORT_ORDER_COLUMN", $secondarySortOrderColumn, $GLOBALS['gPageRow']['page_code']);
			setUserPreference("MAINTENANCE_SECONDARY_REVERSE_SORT_ORDER", "false", $GLOBALS['gPageRow']['page_code']);
		}
		if (array_key_exists($sortOrderColumn, $columns)) {
			$sortOrderColumns = array($sortOrderColumn);
			$reverseSortOrder = getPreference("MAINTENANCE_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']);
			$reverseSortOrders = array($reverseSortOrder ? "desc" : "asc");
			$primaryTableKey = $this->iDataSource->getPrimaryTable()->getName() . "." . $this->iDataSource->getPrimaryTable()->getPrimaryKey();
			if (!empty($secondarySortOrderColumn) && $sortOrderColumn != $primaryTableKey) {
				$sortOrderColumns[] = $secondarySortOrderColumn;
				$reverseSortOrders[] = (getPreference("MAINTENANCE_SECONDARY_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']) ? "desc" : "asc");
			}
			if ($sortOrderColumn != $primaryTableKey) {
				$sortOrderColumns[] = $this->iDataSource->getPrimaryTable()->getPrimaryKey();
				$reverseSortOrders[] = "asc";
			}
			foreach ($sortOrderColumns as $index => $thisSortOrderColumn) {
				if (array_key_exists($thisSortOrderColumn, $foreignKeys)) {
					$sortOrderColumns[$index] = $foreignKeys[$thisSortOrderColumn]['column_name'] . "_display";
				}
			}
			$this->iDataSource->setSortOrder($sortOrderColumns, $reverseSortOrders);
		} else {
			$this->iDataSource->setSortOrder($this->iDataSource->getPrimaryTable()->getPrimaryKey());
		}
		if (!$customSearchFields) {
			$this->iDataSource->setSearchFields($allSearchColumns);
		}
		$itemCount = getPreference("MAINTENANCE_ITEM_COUNT", $GLOBALS['gPageRow']['page_code']);
		if (empty($itemCount)) {
			$itemCount = 20;
		}
		if ($itemCount > 1000) {
			$itemCount = 1000;
		}
		$startRow = getPreference("MAINTENANCE_START_ROW", $GLOBALS['gPageRow']['page_code']);
		if (empty($startRow) || $startRow <= 0) {
			$startRow = 0;
		}
		$textSizeOptions = array("small" => "Small", "medium-small" => "Medium Small", "medium-large" => "Medium Large", "large" => "Large");
		$textSize = getPreference("MAINTENANCE_TEXT_SIZE", $GLOBALS['gPageRow']['page_code']);
		if (empty($textSize) || !array_key_exists($textSize, $textSizeOptions)) {
			$textSize = 'medium-large';
		}
		$returnArray['text_size'] = $textSize;
		$dataList = $this->iDataSource->getDataList(array("row_count" => $itemCount, "start_row" => $startRow));
		if ($startRow > 0 && count($dataList) == 0) {
			$startRow = 0;
			setUserPreference("MAINTENANCE_START_ROW", $startRow, $GLOBALS['gPageRow']['page_code']);
			$dataList = $this->iDataSource->getDataList(array("row_count" => $itemCount, "start_row" => $startRow));
		}
		foreach ($dataList as $index => $dataRow) {
			foreach ($this->iExtraColumns as $columnData) {
				if (substr($columnData['data_value'], 0, strlen("return ")) == "return ") {
					if (substr($columnData['data_value'], -1) != ";") {
						$columnData['data_value'] .= ";";
					}
					$columnData['data_value'] = eval($columnData['data_value']);
				}
				$dataList[$index][$columnData['column_name']] = $columnData['data_value'];
			}
		}
		if (method_exists($this->iPageObject, "dataListProcessing")) {
			$this->iPageObject->dataListProcessing($dataList);
		}
		if (function_exists("_localServerDataListProcessing")) {
			_localServerDataListProcessing($dataList);
		}
		if (!empty($GLOBALS['gUserId']) && !empty($GLOBALS['gPageId'])) {
			$whereStatementParts = $this->iDataSource->getQueryWhere();
			$whereStatement = $whereStatementParts['where_statement'];
			$whereParameters = $whereStatementParts['where_parameters'];
			switch ($subaction) {
				case "clearlisted":
				case "selectall":
					$primaryIds = array();
					$resultSet = executeQuery("select primary_identifier from selected_rows where user_id = ? and page_id = ?" .
						" and primary_identifier in (select " . $this->iDataSource->getPrimaryTable()->getName() . "." . $this->iDataSource->getPrimaryTable()->getPrimaryKey() .
						" from " . $this->iDataSource->getTableList() . (empty($whereStatement) ? "" : " where " . $whereStatement) . ")", array_merge(array($GLOBALS['gUserId'], $GLOBALS['gPageId']), $whereParameters));
					while ($row = getNextRow($resultSet)) {
						$primaryIds[] = $row['primary_identifier'];
					}
					if (!empty($primaryIds)) {
						executeQuery("delete from selected_rows where user_id = ? and page_id = ? and primary_identifier in (" . implode(",", $primaryIds) . ")", $GLOBALS['gUserId'], $GLOBALS['gPageId']);
					}
					if ($subaction == "clearlisted") {
						break;
					}
					executeQuery("insert ignore into selected_rows (selected_row_id,user_id,page_id,primary_identifier,version) " .
						"select null,?,?," . $this->iDataSource->getPrimaryTable()->getName() . "." . $this->iDataSource->getPrimaryTable()->getPrimaryKey() . ",1 from " .
						$this->iDataSource->getTableList() . (empty($whereStatement) ? "" : " where " . $whereStatement), array_merge(array($GLOBALS['gUserId'], $GLOBALS['gPageId']), $whereParameters));
					break;
				case "clearnotlisted":
					$primaryIds = array();
					$resultSet = executeQuery("select primary_identifier from selected_rows where user_id = ? and page_id = ?" .
						" and primary_identifier not in (select " . $this->iDataSource->getPrimaryTable()->getName() . "." . $this->iDataSource->getPrimaryTable()->getPrimaryKey() .
						" from " . $this->iDataSource->getTableList() . (empty($whereStatement) ? "" : " where " . $whereStatement) . ")", array_merge(array($GLOBALS['gUserId'], $GLOBALS['gPageId']), $whereParameters));
					while ($row = getNextRow($resultSet)) {
						$primaryIds[] = $row['primary_identifier'];
					}
					if (!empty($primaryIds)) {
						executeQuery("delete from selected_rows where user_id = ? and page_id = ? and primary_identifier in (" . implode(",", $primaryIds) . ")", $GLOBALS['gUserId'], $GLOBALS['gPageId']);
					}
					break;
			}
		}

		$returnArray['.page-row-count'] = $this->iDataSource->getDataListCount();
		$returnArray['.page-first-record-display'] = min($startRow + 1, $this->iDataSource->getDataListCount());
		$returnArray['.page-last-record-display'] = min($returnArray['.page-first-record-display'] - 1 + $itemCount, $this->iDataSource->getDataListCount());
		$pageCount = max(ceil($this->iDataSource->getDataListCount() / $itemCount), 1);
		$thisPageNumber = ceil(($returnArray['.page-first-record-display'] - 1) / $itemCount) + 1;
		$returnArray['.page-record-number'] = $thisPageNumber;
		$pageNumbers = array();
		for ($x = 1; $x <= $pageCount; $x++) {
			$pageNumbers[$x - 1] = array("value" => ($itemCount * ($x - 1)), "description" => $x);
			if ($x == $thisPageNumber) {
				$pageNumbers[$x - 1]['selected'] = true;
			}
		}
		$returnArray['.page-number'] = $pageNumbers;
		if ($startRow == 0) {
			$previousStartRow = "";
		} else {
			$previousStartRow = max($startRow - $itemCount, 0);
		}
		$nextStartRow = $startRow + $itemCount;
		if ($nextStartRow >= $returnArray['.page-row-count']) {
			$nextStartRow = "";
		}
		$returnArray['_start_row'] = $startRow;
		$returnArray['_previous_start_row'] = $previousStartRow;
		$returnArray['_next_start_row'] = $nextStartRow;
		$returnArray['data_list'] = array();
		$returnArray['column_headers'] = array();

		$columnIndex = 0;
		foreach ($listColumns as $thisIndex => $columnName) {
			$returnArray['column_headers'][$columnIndex] = array();
			$returnArray['column_headers'][$columnIndex]['column_name'] = $columnName;
			$listHeader = $columns[$columnName]->getControlValue('list_header');
			if (empty($listHeader) && array_key_exists($columnName, $foreignKeys)) {
				$parts = explode(".", $columnName);
				$thisColumnName = (count($parts) == 1 ? $parts[0] : $parts[1]);
				if (array_key_exists($thisColumnName . "_display", $columns)) {
					$listHeader = $columns[$thisColumnName . "_display"]->getControlValue('list_header');
				}
			}
			if (empty($listHeader)) {
				$listHeader = $columns[$columnName]->getControlValue('form_label');
			}
			$returnArray['column_headers'][$columnIndex]['description'] = $listHeader .
				($sortOrderColumn == $columnName ? "&nbsp;" . ($reverseSortOrder ? "<span class='fad fa-sort-alpha-down-alt'></span>" : "<span class='fad fa-sort-alpha-down'></span>") : "");
			$classes = array();
			if ($columns[$columnName]->getControlValue('not_sortable')) {
				$classes[] = "no-sort";
			}
			if ($columns[$columnName]->getControlValue('data_type') == "decimal" || $columns[$columnName]->getControlValue('data_type') == "int" || $columns[$columnName]->getControlValue('data_type') == "bigint") {
				$classes[] = "align-right";
			}
			if ($sortOrderColumn == $columnName) {
				$classes[] = ($reverseSortOrder ? "sort-reverse" : "sort-normal");
			}
			$returnArray['column_headers'][$columnIndex]['class_names'] = implode(" ", $classes);
			$columnIndex++;
		}

		foreach ($this->iExtraColumns as $columnData) {
			$returnArray['column_headers'][$columnIndex] = array();
			$returnArray['column_headers'][$columnIndex]['column_name'] = $columnData['column_name'];
			$returnArray['column_headers'][$columnIndex]['description'] = $columnData['description'];
			$returnArray['column_headers'][$columnIndex]['class_names'] = $columnData['class_names'];
			$columnIndex++;
		}

		$selectedRowIndexes = array();
		$selectedRowValues = "";
		foreach ($dataList as $rowIndex => $columnRow) {
			$selectedRowIndexes[$columnRow[$this->iDataSource->getPrimaryTable()->getPrimaryKey()]] = $rowIndex;
			if (!empty($selectedRowValues)) {
				$selectedRowValues .= ",";
			}
			$selectedRowValues .= "'" . $columnRow[$this->iDataSource->getPrimaryTable()->getPrimaryKey()] . "'";
		}
		$selectedRowPrimaryKeys = array();
		if (!empty($selectedRowValues)) {
			$resultSet = executeQuery("select * from selected_rows where page_id = ? and user_id = ? and primary_identifier in (" . $selectedRowValues . ")", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
			while ($row = getNextRow($resultSet)) {
				$rowIndex = $selectedRowIndexes[$row['primary_identifier']];
				$selectedRowPrimaryKeys[$rowIndex] = true;
			}
			freeResult($resultSet);
		}

		$listTemplate = false;
		if (!empty($GLOBALS['gTableEditorListFilename']) && file_exists($GLOBALS['gDocumentRoot'] . "/forms/" . $GLOBALS['gTableEditorListFilename'])) {
			$listTemplate = $GLOBALS['gTableEditorListFilename'];
		} else if (!empty($GLOBALS['gPageRow']['script_filename']) && file_exists($GLOBALS['gDocumentRoot'] . "/forms/local_" . str_replace(".php", ".lst", $GLOBALS['gPageRow']['script_filename']))) {
			$listTemplate = "local_" . str_replace(".php", ".lst", $GLOBALS['gPageRow']['script_filename']);
		} else if (!empty($GLOBALS['gPageRow']['script_filename']) && file_exists($GLOBALS['gDocumentRoot'] . "/forms/" . str_replace(".php", ".lst", $GLOBALS['gPageRow']['script_filename']))) {
			$listTemplate = str_replace(".php", ".lst", $GLOBALS['gPageRow']['script_filename']);
		}
		$listFragment = false;
		if (!empty($listTemplate)) {
			$filename = $GLOBALS['gDocumentRoot'] . "/forms/" . $listTemplate;
			$listFragment = file_get_contents($filename);
			$returnArray['use_list_fragment'] = true;
			$returnArray['list_fragment_contents'] = "";
			foreach ($dataList as $rowIndex => $columnRow) {
				$listTemplateLines = getContentLines($listFragment);
				$useLine = true;
				$ifStatements = array(true);
				foreach ($listTemplateLines as $line) {
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
					if ($line == "%else%") {
						$useLine = !$useLine;
						continue;
					}
					if (substr($line, 0, strlen("%if:")) == "%if:") {
						$evalStatement = substr($line, strlen("%if:"), -1);
						if (substr($evalStatement, 0, strlen("return ")) != "return ") {
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
					if (substr($line, 0, strlen("%if_has_value:")) == "%if_has_value:") {
						$fieldName = substr($line, strlen("%if_has_value:"), -1);
						if (substr($fieldName, -1) == "%") {
							$fieldName = substr($fieldName, 0, -1);
						}
						if (substr($fieldName, -1) == ";") {
							$fieldName = substr($fieldName, 0, -1);
						}
						$useLine = $useLine && !empty($columnRow[$fieldName]);
						continue;
					}
					if (!$useLine) {
						continue;
					}
					$primaryId = $columnRow[$this->iDataSource->getPrimaryTable()->getPrimaryKey()];
					if (substr($line, 0, strlen("%method:")) == "%method:") {
						$parameterString = false;
						$functionName = str_replace("%", "", substr($line, strlen("%method:")));
						if (strpos($functionName, ":") !== false) {
							$parameterString = substr($functionName, strpos($functionName, ":") + 1);
							$functionName = substr($functionName, 0, strpos($functionName, ":"));
						}
						if (method_exists($this->iPageObject, $functionName)) {
							ob_start();
							if ($parameterString) {
								$this->iPageObject->$functionName($parameterString);
							} else {
								$this->iPageObject->$functionName($primaryId);
							}
							$returnArray['list_fragment_contents'] .= ob_get_clean() . "\n";
						}
						continue;
					}
					$line = str_replace("%primary_id%", $primaryId, $line);
					foreach ($columnRow as $fieldName => $fieldData) {
						$line = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $line);
					}
					$returnArray['list_fragment_contents'] .= $line . "\n";
				}
			}
		}

		foreach ($dataList as $rowIndex => $columnRow) {
			$returnArray['data_list'][$rowIndex] = array();
			if (array_key_exists($rowIndex, $selectedRowPrimaryKeys)) {
				$returnArray['data_list'][$rowIndex]["_selected"] = "1";
			} else {
				$returnArray['data_list'][$rowIndex]["_selected"] = "0";
			}
			$returnArray['data_list'][$rowIndex]["_primary_id"] = $columnRow[$this->iDataSource->getPrimaryTable()->getPrimaryKey()];
			if (method_exists($this->iPageObject, "getListRowClasses")) {
				$returnArray['data_list'][$rowIndex]['row_classes'] = $this->iPageObject->getListRowClasses($columnRow);
			}
			foreach ($this->iExtraColumns as $columnData) {
				$returnArray['data_list'][$rowIndex][$columnData['column_name']] = array("data_value" => $columnRow[$columnData['column_name']]);
			}
			foreach ($listColumns as $fullColumnName) {
				$columnName = (strpos($fullColumnName, ".") === false ? $fullColumnName : substr($fullColumnName, strpos($fullColumnName, ".") + 1));
				$returnArray['data_list'][$rowIndex][$columnName] = array();
				$classNames = array();
				$mysqlType = $columns[$fullColumnName]->getControlValue("mysql_type");
				if (empty($mysqlType)) {
					$mysqlType = $columns[$fullColumnName]->getControlValue('data_type');
				}
				switch ($columns[$fullColumnName]->getControlValue('data_type')) {
					case "datetime":
						$dateFormat = $columns[$fullColumnName]->getControlValue("list_date_format");
						if (empty($dateFormat)) {
							$dateFormat = $columns[$fullColumnName]->getControlValue("date_format");
						}
						if (empty($dateFormat)) {
							$dateFormat = "m/d/Y g:i:sa";
						}
						$returnArray['data_list'][$rowIndex][$columnName]['data_value'] = (empty($columnRow[$columnName]) ? "" : date($dateFormat, strtotime($columnRow[$columnName])));
						break;
					case "date":
						$dateFormat = $columns[$fullColumnName]->getControlValue("list_date_format");
						if (empty($dateFormat)) {
							$dateFormat = $columns[$fullColumnName]->getControlValue("date_format");
						}
						if (empty($dateFormat)) {
							$dateFormat = "m/d/Y";
						}
						$returnArray['data_list'][$rowIndex][$columnName]['data_value'] = (empty($columnRow[$columnName]) ? "" : date($dateFormat, strtotime($columnRow[$columnName])));
						break;
					case "time":
						$dateFormat = $columns[$fullColumnName]->getControlValue("list_date_format");
						if (empty($dateFormat)) {
							$dateFormat = $columns[$fullColumnName]->getControlValue("date_format");
						}
						if (empty($dateFormat)) {
							$dateFormat = "g:i a";
						}
						$returnArray['data_list'][$rowIndex][$columnName]['data_value'] = (empty($columnRow[$columnName]) ? "" : date($dateFormat, strtotime($columnRow[$columnName])));
						break;
					case "bigint":
					case "int":
					case "select":
					case "autocomplete":
					case "hidden":
					case "user_picker":
					case "contact_picker":
						if (array_key_exists($fullColumnName, $foreignKeys)) {
							$returnArray['data_list'][$rowIndex][$columnName]['data_value'] = htmlText(getFirstPart($columnRow[$columnName . "_display"], $this->iListDataLength));
						} else {
							$returnArray['data_list'][$rowIndex][$columnName]['data_value'] = (strlen($columnRow[$columnName]) == 0 ? "" : ($mysqlType == "int" && is_numeric($columnRow[$columnName]) ? number_format($columnRow[$columnName], 0, "", "") : $columnRow[$columnName]));
							if ($mysqlType == "int") {
								$classNames[] = "align-right";
							}
						}
						break;
					case "decimal":
						$returnArray['data_list'][$rowIndex][$columnName]['data_value'] = (strlen($columnRow[$columnName]) == 0 ? "" : number_format($columnRow[$columnName], $columns[$fullColumnName]->getControlValue('decimal_places'), ".", ","));
						$classNames[] = "align-right";
						break;
					default:
						if ($mysqlType == "tinyint") {
							$trueValue = $columns[$fullColumnName]->getControlValue('true_value');
							if (empty($trueValue)) {
								$trueValue = "<span class='fa fa-check-square color-green'></span>";
							}
							$falseValue = $columns[$fullColumnName]->getControlValue('false_value');
							if (empty($falseValue)) {
								$falseValue = "<span class='fa fa-times color-red'></span>";
							}
							$returnArray['data_list'][$rowIndex][$columnName]['data_value'] = (empty($columnRow[$columnName]) ? $falseValue : $trueValue);
							$classNames[] = "align-center";
							break;
						}
						$returnArray['data_list'][$rowIndex][$columnName]['data_value'] = ($columns[$fullColumnName]->getControlValue('dont_escape') ? $columnRow[$columnName] : htmlText(getFirstPart($columnRow[$columnName], $this->iListDataLength)));
				}
				$returnArray['data_list'][$rowIndex][$columnName]['class_names'] = implode(" ", $classNames);
			}
		}
		$returnArray['.page-select-count'] = 0;
		executeQuery("delete from selected_rows where user_id = ? and page_id = ?" .
			" and primary_identifier not in (select " . $this->iDataSource->getPrimaryTable()->getPrimaryKey() . " from " .
			$this->iDataSource->getPrimaryTable()->getName() . ")", array($GLOBALS['gUserId'], $GLOBALS['gPageId']));
		$resultSet = executeQuery("select count(*) from selected_rows where user_id = ? and page_id = ?", array($GLOBALS['gUserId'], $GLOBALS['gPageId']));
		if ($row = getNextRow($resultSet)) {
			$returnArray['.page-select-count'] = $row['count(*)'];
		}
		freeResult($resultSet);
		if (method_exists($this->iPageObject, "addDataListReturnValues")) {
			$this->iPageObject->addDataListReturnValues($returnArray);
		}
		ajaxResponse($returnArray);
	}

	/**
	 *    function setPreferences
	 *
	 *    One of the actions in the list is preferences. It displays a dialog box allowing the user to set numerous options
	 *    for this page. This function saves the options the user sets.
	 */
	function setPreferences() {
		$returnArray = array();
		$userPreferenceCodes = array(
			"item_count",
			"text_size",
			"list_data_length",
			"list_shaded",
			"show_inactive",
			"hide_ids",
			"use_popup",
			"list_columns",
			"export_columns",
			"save_no_list",
			"format_export"
		);
		$numericValues = array("maintenance_item_count" => "20", "maintenance_list_data_length" => "30");
		foreach ($_POST as $fieldName => $fieldValue) {
			if (!array_key_exists($fieldName, $numericValues)) {
				continue;
			}
			if (!is_numeric($fieldValue)) {
				$_POST[$fieldName] = $numericValues[$fieldName];
			}
		}
		foreach ($userPreferenceCodes as $preferenceCode) {
			if ($_GET['subaction'] == "save") {
				setUserPreference("MAINTENANCE_" . strtoupper($preferenceCode), $_POST['maintenance_' . $preferenceCode], $GLOBALS['gPageRow']['page_code']);
			} else {
				$_POST['maintenance_' . $preferenceCode] = getPreference("MAINTENANCE_" . strtoupper($preferenceCode), $GLOBALS['gPageRow']['page_code']);
			}
			$returnArray['maintenance_' . $preferenceCode] = $_POST['maintenance_' . $preferenceCode];
		}

		ajaxResponse($returnArray);
	}

	/**
	 *    function internalPageCSS
	 *
	 *    not used by this class, but needed because it is abstract in tableEditor
	 */
	function internalPageCSS() {
		$this->iPageObject->internalPageCSS();
		if (function_exists("_localServerInternalCSS")) {
			_localServerInternalCSS();
		}
	}

	/**
	 *    function hiddenElements
	 *
	 *    Dialog boxes and an iframe for exporting the data are in the hidden area.
	 */
	function hiddenElements() {
		$columns = $this->iDataSource->getColumns();
		$listColumns = explode(",", getPreference("MAINTENANCE_LIST_COLUMNS", $GLOBALS['gPageRow']['page_code']));
		$exportColumns = explode(",", getPreference("MAINTENANCE_EXPORT_COLUMNS", $GLOBALS['gPageRow']['page_code']));
		?>
        <iframe id="_export_frame" class="hidden"></iframe>

		<?php if ($this->canUsePopupEditor()) { ?>
            <div id="_popup_editor" class="dialog-box">
                <p class="error-message" id="popup_error_message"></p>
                <form id="_popup_form">
                    <input type="hidden" id="primary_id" name="primary_id">
                    <input type="hidden" id="version" name="version">
					<?php
					$primaryKeyColumnName = $this->iDataSource->getPrimaryTable()->getName() . "." . $this->iDataSource->getPrimaryTable()->getPrimaryKey();
					$formColumns = array();

					# Get all columns from the data source
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

					$defaultFormLineArray = array();
					$filename = $GLOBALS['gDocumentRoot'] . "/forms/defaultformline.frm";
					$handle = fopen($filename, 'r');
					while ($thisLine = fgets($handle)) {
						$defaultFormLineArray[] = $thisLine;
					}
					fclose($handle);

					$basicFormLineArray = array();
					$filename = $GLOBALS['gDocumentRoot'] . "/forms/basicformline.frm";
					$handle = fopen($filename, 'r');
					while ($thisLine = fgets($handle)) {
						$basicFormLineArray[] = $thisLine;
					}
					fclose($handle);

					$formContentArray = array();
					$formTemplate = "maintenance.frm";
					$filename = $GLOBALS['gDocumentRoot'] . "/forms/" . $formTemplate;
					$handle = fopen($filename, 'r');
					while ($thisLine = fgets($handle)) {
						$thisLine = trim($thisLine);
						if ($thisLine == "%default_form_line%") {
							$formContentArray = array_merge($formContentArray, $defaultFormLineArray);
						} else if ($thisLine == "%basic_form_line%") {
                            $formContentArray = array_merge($formContentArray, $basicFormLineArray);
						} else {
							$formContentArray[] = $thisLine;
						}
					}
					fclose($handle);

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
					while (true) {
						if ($contentIndex > (count($formContentArray) + 1)) {
							break;
						}
						$line = trim($formContentArray[$contentIndex]);
                        $line = str_replace("%help_label%","",$line);
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
						if (substr($line, 0, strlen("%if:")) == "%if:") {
							$evalStatement = substr($line, strlen("%if:"), -1);
							if (substr($evalStatement, 0, strlen("return ")) != "return ") {
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
						} else {
							if (substr($line, 0, strlen("%field:")) == "%field:") {
								if ($thisColumn) {
									if ($firstField) {
										array_unshift($formColumns, $thisColumn->getControlValue("full_column_name"));
										$usedFormColumns[] = $thisColumn->getControlValue("full_column_name");
										$firstField = false;
									}
								}
								$fieldName = trim(str_replace("%", "", substr($line, strlen("%field:"))));
								if (strpos($fieldName, ".") === false) {
									if ($this->iDataSource->getPrimaryTable()->columnExists($fieldName)) {
										$fieldName = $this->iDataSource->getPrimaryTable()->getName() . "." . $fieldName;
									} else {
										if ($this->iDataSource->getJoinTable() && $this->iDataSource->getJoinTable()->columnExists($fieldName)) {
											$fieldName = $this->iDataSource->getJoinTable()->getName() . "." . $fieldName;
										}
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
							} else {
								if (preg_match('/^%repeat*%/i', $line)) {
									$repeatCount = str_replace("repeat", "", str_replace("%", "", str_replace(" ", "", trim($line))));
									if (empty($repeatCount) || !is_numeric($repeatCount)) {
										$repeatCount = 9999;
									}
									$repeatStartLine = $contentIndex;
									continue;

# End repeat
								} else {
									if ($line == "%end repeat%") {
										$repeatCount--;
										if ($repeatStartLine > 0 && $repeatCount > 0 && $thisColumn !== false) {
											$contentIndex = $repeatStartLine;
										} else {
											$repeatStartLine = 0;
										}
										continue;
# Execute a method in the page object
									} else {
										if (substr($line, 0, strlen("%method:")) == "%method:") {
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
									}
								}
							}
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
							if ($thisColumn->getControlValue('data_type') == "tinyint" && !$thisColumn->getControlValue('normal_label')) {
								$line = str_replace("%form_label%", "", $line);
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
            </div>
		<?php } ?>

		<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
            <div id="_custom_where" class="dialog-box">
                <div class="basic-form-line" id="_custom_where_entry_row">
                    <label for="_custom_where_entry"><?= getLanguageText("Where") ?></label>
                    <textarea id="_custom_where_entry" name="_custom_where_entry"></textarea>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
            </div>
		<?php } ?>

        <div id="_reset_sort_order_dialog" class="dialog-box">
            <form id="_reset_sort_order_form">
                <div class="basic-form-line" id="_reset_sort_order_row">
                    <label for="_reset_sort_order"><?= getLanguageText("Sort Order Value") ?></label>
                    <input type='text' size="10" class='validate[required,custom[integer],min[0]] align-right' id="_reset_sort_order" name="_reset_sort_order"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
            </form>
        </div>

        <div id="_query_select_dialog" class="dialog-box">
            <form id="_query_select_form">

                <div class="basic-form-line" id="_query_select_clear_existing_row">
                    <input type="checkbox" value="1" tabindex="10" checked id="query_select_clear_existing" name="query_select_clear_existing"><label class="checkbox-label" for="query_select_clear_existing">Clear existing selected records</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_query_select_which_records_row">
                    <label for="query_select_which_records">Select rows which match</label>
                    <select tabindex="10" id="query_select_which_records" name="query_select_which_records">
                        <option value='all'>All Criteria</option>
                        <option value='any'>Any Criteria</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line custom-control-form-line custom-control-no-help" id="_query_select_criteria_row">
                    <label for="query_select_criteria">Field Criteria</label>
					<?php
					$fieldCriteria = new DataColumn("query_select_criteria");
					$fieldCriteria->setControlValue("data_type", "custom");
					$fieldCriteria->setControlValue("control_class", "EditableList");
					$fieldCriteria->setControlValue("column_list", "column_name,comparator,field_value");
					$fieldCriteria->setControlValue("tabindex", "10");
					$fieldControls = array();
					$fieldControls['column_name'] = array("data_type" => "select", "form_label" => "Field", "choices" => array(), "classes" => "query-select-column", "not_null" => true);
					$fieldControls['comparator'] = array("data_type" => "select", "form_label" => "", "choices" => array(), "not_null" => true, "classes" => "query-select-comparator");
					$fieldControls['field_value'] = array("data_type" => "varchar", "form_label" => "Value", "classes" => "query-select-value", "cell_classes" => "query-select-value-fields");
					$fieldCriteria->setControlValue("list_table_controls", $fieldControls);
					$fieldCriteriaControl = new EditableList($fieldCriteria, $this->iPageObject);
					echo $fieldCriteriaControl->getControl();
					?>
                </div>
            </form>
        </div>

        <div id="_preferences" class="dialog-box">
            <form id="_preference_form" name="_preference_form">

                <div class='half-width'>
                    <div class="basic-form-line" id="_maintenance_item_count_row">
                        <label for="maintenance_item_count"><?= getLanguageText("Rows_per_page") ?></label>
                        <input type="text" size="4" maxlength="4" class="validate[custom[integer],min[4],max[1000]]" id="maintenance_item_count" name="maintenance_item_count" value="<?= getPreference("MAINTENANCE_ITEM_COUNT", $GLOBALS['gPageRow']['page_code']) ?>"/>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line" id="_maintenance_list_data_length_row">
                        <label for="maintenance_list_data_length"><?= getLanguageText("Maximum_characters_per_column") ?></label>
                        <input type="text" size="4" maxlength="4" class="validate[custom[integer],min[10]]" id="maintenance_list_data_length" name="maintenance_list_data_length" value="<?= getPreference("MAINTENANCE_LIST_DATA_LENGTH", $GLOBALS['gPageRow']['page_code']) ?>"/>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line" id="_maintenance_text_size_row">
                        <label for="maintenance_text_size"><?= getLanguageText("List Font Size") ?></label>
                        <select id="maintenance_text_size" name="maintenance_text_size">
							<?php
							$textSizeOptions = array("small" => "Small", "medium-small" => "Medium Small", "medium-large" => "Medium Large", "large" => "Large");
							$textSize = getPreference("MAINTENANCE_TEXT_SIZE", $GLOBALS['gPageRow']['page_code']);
							if (empty($textSize) || !array_key_exists($textSize, $textSizeOptions)) {
								$textSize = 'medium-large';
							}
							foreach ($textSizeOptions as $keyValue => $description) {
								?>
                                <option value="<?= $keyValue ?>" <?= ($keyValue == $textSize ? " selected" : "") ?>><?= $description ?></option>
							<?php } ?>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>
                </div>

                <div class='half-width'>
                    <div class="basic-form-line" id="_maintenance_list_shaded_row">
                        <label for="maintenance_list_shaded"></label>
                        <input type="checkbox" id="maintenance_list_shaded" name="maintenance_list_shaded" value="true" <?= (getPreference("MAINTENANCE_LIST_SHADED", $GLOBALS['gPageRow']['page_code']) ? " checked" : "") ?>/><label class="checkbox-label" for="maintenance_list_shaded"><?= getLanguageText("Use_Shaded_List_Type") ?></label>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line" id="_maintenance_save_no_list_row">
                        <label for="maintenance_save_no_list"></label>
                        <input type="checkbox" id="maintenance_save_no_list" name="maintenance_save_no_list" value="true" <?= (getPreference("MAINTENANCE_SAVE_NO_LIST", $GLOBALS['gPageRow']['page_code']) ? " checked" : "") ?>/><label class="checkbox-label" for="maintenance_save_no_list"><?= getLanguageText("Stay_on_record_after_save") ?></label>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line" id="_maintenance_show_inactive_row">
                        <label for="maintenance_show_inactive"></label>
                        <input type="checkbox" id="maintenance_show_inactive" name="maintenance_show_inactive" value="true" <?= (getPreference("MAINTENANCE_SHOW_INACTIVE", $GLOBALS['gPageRow']['page_code']) ? " checked" : "") ?>/><label class="checkbox-label" for="maintenance_show_inactive"><?= getLanguageText("Show_inactive_records") ?></label>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line" id="_maintenance_hide_ids_row">
                        <label for="maintenance_hide_ids"></label>
                        <input type="checkbox" id="maintenance_hide_ids" name="maintenance_hide_ids" value="true" <?= (getPreference("MAINTENANCE_HIDE_IDS", $GLOBALS['gPageRow']['page_code']) ? " checked" : "") ?>/><label class="checkbox-label" for="maintenance_hide_ids"><?= getLanguageText("Hide ID Column") ?></label>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

					<?php if ($this->canUsePopupEditor()) { ?>
                        <div class="basic-form-line" id="_maintenance_use_popup_row">
                            <label for="maintenance_use_popup"></label>
                            <input type="checkbox" id="maintenance_use_popup" name="maintenance_use_popup" value="true" <?= (getPreference("maintenance_use_popup", $GLOBALS['gPageRow']['page_code']) ? " checked" : "") ?>/><label class="checkbox-label" for="maintenance_use_popup"><?= getLanguageText("Default_is_popup_editor") ?></label>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>
					<?php } ?>
                </div>
                <div class='clear-div'></div>

				<?php
				$choices = array();
				foreach ($columns as $columnName => $thisColumn) {
					$subType = $thisColumn->getControlValue('subtype');
					$columnText = $thisColumn->getControlValue("form_label");
					if ($this->iUseColumnNameInFilter || empty($columnText) || is_null($columnText)) {
						$columnText = ucwords(str_replace("_", " ", $thisColumn->getControlValue("column_name")));
					}
					if ($thisColumn->getControlValue("data_type") != "hidden" && !$thisColumn->getControlValue("primary_key") &&
						!in_array($columnName, $this->iExcludeListColumns) && !empty($thisColumn->getControlValue("data_type") && empty($thisColumn->getControlValue('hide_in_list')) &&
							$thisColumn->getControlValue('data_type') != "longblob" && empty($subType) && $thisColumn->getControlValue("data_type") != "custom_control" && $thisColumn->getControlValue("data_type") != "custom" && $thisColumn->getControlValue("data_type") != "button")) {
						$choices[$columnName] = array("description" => $columnText);
					}
				}
				$values = array();
				foreach ($listColumns as $columnName) {
					$values[$columnName] = $columnName;
				}
				$listColumnControl = new DataColumn("maintenance_list_columns");
				$listColumnControl->setControlValue("data_type", "custom");
				$listColumnControl->setControlValue("control_class", "MultiSelect");
				$listColumnControl->setControlValue("choices", $choices);
				$listColumnControl->setControlValue("selected_values", $values);
				$listColumnControl->setControlValue("user_sets_order", true);
				$customControl = new MultipleSelect($listColumnControl, $this->iPageObject);
				?>
                <div class="basic-form-line custom-control-no-help custom-control-form-line" id="_maintenance_list_columns_row">
                    <label for="maintenance_list_columns">List Columns</label>
					<?= $customControl->getControl() ?>
                </div>

				<?php
				$values = array();
				foreach ($exportColumns as $columnName) {
					$values[$columnName] = $columnName;
				}
				$listColumnControl = new DataColumn("maintenance_export_columns");
				$listColumnControl->setControlValue("data_type", "custom");
				$listColumnControl->setControlValue("control_class", "MultiSelect");
				$listColumnControl->setControlValue("choices", $choices);
				$listColumnControl->setControlValue("selected_values", $values);
				$listColumnControl->setControlValue("user_sets_order", true);
				$customControl = new MultipleSelect($listColumnControl, $this->iPageObject);
				?>
                <div class="basic-form-line custom-control-no-help custom-control-form-line" id="_maintenance_export_columns_row">
                    <label for="maintenance_export_columns">Export Columns</label>
					<?= $customControl->getControl() ?>
                </div>

            </form>
        </div>
		<?php
	}

	/**
	 *    function exportCSV
	 *
	 *    function to export the selected records.
	 */
	function exportCSV($exportAll = false) {
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");
		header("Content-Disposition: attachment;filename=" . $this->iExportFilename);
		header("Content-Transfer-Encoding: binary");

		if ($this->iRemoveExport) {
			exit;
		}

		$formatExport = getPreference("MAINTENANCE_FORMAT_EXPORT", $GLOBALS['gPageRow']['page_code']);

		$primaryKey = $this->iDataSource->getPrimaryTable()->getName() . "." . $this->iDataSource->getPrimaryTable()->getPrimaryKey();

		if (!$exportAll) {
			$this->iDataSource->addFilterWhere($primaryKey . " in (select primary_identifier from selected_rows " .
				"where user_id = " . $GLOBALS['gUserId'] . " and page_id = " . $GLOBALS['gPageId'] . ")");
		}

		$columns = $this->iDataSource->getColumns();

		$foreignKeys = $this->iDataSource->getForeignKeyList();
		foreach ($foreignKeys as $columnName => $foreignKeyInfo) {
			if (in_array($columnName, $this->iExcludeListColumns)) {
				continue;
			}
			$thisColumn = $columns[$columnName];
			if (!empty($thisColumn) && !empty($thisColumn->getControlValue('hide_in_list'))) {
				continue;
			}
			if (!empty($foreignKeyInfo['description'])) {
				$this->iDataSource->addColumnControl($foreignKeyInfo['column_name'] . "_display", "select_value",
					"select concat_ws(' '," . implode(",", $foreignKeyInfo['description']) . ") from " .
					$foreignKeyInfo['referenced_table_name'] . " where " . $foreignKeyInfo['referenced_column_name'] . " = " . $columnName);
				$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => $foreignKeyInfo['referenced_table_name'],
					"referenced_column_name" => $foreignKeyInfo['referenced_column_name'], "foreign_key" => $foreignKeyInfo['column_name'],
					"description" => $foreignKeyInfo['description']));
			}
		}

		$sortOrderColumn = getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$secondarySortOrderColumn = getPreference("MAINTENANCE_SECONDARY_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$listColumns = explode(",", getPreference("MAINTENANCE_EXPORT_COLUMNS", $GLOBALS['gPageRow']['page_code']));
		if (count($listColumns) == 0 || (count($listColumns) == 1 && empty($listColumns[0]))) {
			$listColumns = array();
			foreach ($columns as $columnName => $thisColumn) {
				if (!in_array($columnName, $this->iExcludeListColumns) && empty($thisColumn->getControlValue('hide_in_list')) && $thisColumn->getControlValue('data_type') != "longblob") {
					$listColumns[] = $columnName;
				}
			}
		}
		foreach ($listColumns as $thisIndex => $columnName) {
			$thisColumn = $columns[$columnName];
			if (!array_key_exists($columnName, $columns) || ($columnName != $primaryKey && in_array($columnName, $this->iExcludeListColumns)) && (!empty($thisColumn) && empty($thisColumn->getControlValue("hide_in_list")))) {
				unset($listColumns[$thisIndex]);
				continue;
			}
		}
		if (!in_array($sortOrderColumn, $listColumns)) {
			$sortOrderColumn = $this->iDefaultSortOrderColumn;
			$reverseSortOrder = $this->iDefaultReverseSortOrder;
			$secondarySortOrderColumn = "";
			setUserPreference("MAINTENANCE_SORT_ORDER_COLUMN", $sortOrderColumn, $GLOBALS['gPageRow']['page_code']);
			setUserPreference("MAINTENANCE_REVERSE_SORT_ORDER", ($reverseSortOrder ? "true" : "false"), $GLOBALS['gPageRow']['page_code']);
			setUserPreference("MAINTENANCE_SECONDARY_SORT_ORDER_COLUMN", $secondarySortOrderColumn, $GLOBALS['gPageRow']['page_code']);
			setUserPreference("MAINTENANCE_SECONDARY_REVERSE_SORT_ORDER", "false", $GLOBALS['gPageRow']['page_code']);
		}

		if (array_key_exists($sortOrderColumn, $columns)) {
			$sortOrderColumns = array($sortOrderColumn);
			$reverseSortOrder = getPreference("MAINTENANCE_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']);
			$reverseSortOrders = array($reverseSortOrder);
			if (!empty($secondarySortOrderColumn)) {
				$sortOrderColumns[] = $secondarySortOrderColumn;
				$reverseSortOrders[] = (getPreference("MAINTENANCE_SECONDARY_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']) ? "desc" : "asc");
			}
			$sortOrderColumns[] = $this->iDataSource->getPrimaryTable()->getPrimaryKey();
			$reverseSortOrders[] = "asc";
			foreach ($sortOrderColumns as $index => $thisSortOrderColumn) {
				if (array_key_exists($thisSortOrderColumn, $foreignKeys)) {
					$sortOrderColumns[$index] = $foreignKeys[$thisSortOrderColumn]['column_name'] . "_display";
				}
			}
			$this->iDataSource->setSortOrder($sortOrderColumns, $reverseSortOrders);
		}
        $dataList = $this->iDataSource->getDataList(array("export_all"=>$exportAll));
		if (!empty($this->iPageObject) && method_exists($this->iPageObject, "dataListProcessing")) {
			$this->iPageObject->dataListProcessing($dataList);
		}

		$columnHeaders = ($formatExport ? $this->iDataSource->getPrimaryTable()->getPrimaryKey() : "ID");
		foreach ($listColumns as $thisIndex => $columnName) {
			if ($columnName == $primaryKey) {
				continue;
			}
			if ($formatExport) {
				if (!empty($columns[$columnName]->getControlValue('select_value'))) {
					continue;
				}
				$thisColumnHeader = $columns[$columnName]->getControlValue('column_name');
			} else {
				$thisColumnHeader = str_replace(" ", "", ucwords(strtolower($columns[$columnName]->getControlValue('form_label'))));
				if (empty($thisColumnHeader)) {
					$thisColumnHeader = str_replace(" ", "", ucwords(strtolower(str_replace("_", " ", $columnName))));
				}
			}
			$columnHeaders .= (empty($columnHeaders) ? "" : ",") . $thisColumnHeader;
		}
		foreach ($this->iAdditionalExportFields as $fieldName) {
			$thisColumnHeader = str_replace(" ", "", ucwords(strtolower(str_replace("_", " ", $fieldName))));
			$columnHeaders .= (empty($columnHeaders) ? "" : ",") . $thisColumnHeader;
		}

		echo $columnHeaders . "\r\n";

		foreach ($dataList as $rowIndex => $columnRow) {
			$dataLine = $columnRow[$this->iDataSource->getPrimaryTable()->getPrimaryKey()];
			foreach ($listColumns as $fullColumnName) {
				if ($fullColumnName == $primaryKey) {
					continue;
				}
				$columnName = (strpos($fullColumnName, ".") === false ? $fullColumnName : substr($fullColumnName, strpos($fullColumnName, ".") + 1));
				if (!empty($dataLine)) {
					$dataLine .= ",";
				}
				if ($formatExport) {
					if (!empty($columns[$fullColumnName]->getControlValue('select_value'))) {
						continue;
					}
				}
				$mysqlType = $columns[$fullColumnName]->getControlValue("mysql_type");
				if (empty($mysqlType)) {
					$mysqlType = $columns[$fullColumnName]->getControlValue('data_type');
				}
				switch ($columns[$fullColumnName]->getControlValue('data_type')) {
					case "datetime":
						$dataValue = (empty($columnRow[$columnName]) ? "" : date("m/d/Y g:i:sa", strtotime($columnRow[$columnName])));
						break;
					case "date":
						$dataValue = (empty($columnRow[$columnName]) ? "" : date("m/d/Y", strtotime($columnRow[$columnName])));
						break;
					case "time":
						$dataValue = (empty($columnRow[$columnName]) ? "" : date("g:i a", strtotime($columnRow[$columnName])));
						break;
					case "bigint":
					case "int":
					case "select":
					case "autocomplete":
						if (array_key_exists($fullColumnName, $foreignKeys)) {
							$dataValue = $columnRow[$columnName . "_display"];
						} else {
							$dataValue = (strlen($columnRow[$columnName]) == 0 ? "" : ($mysqlType == "int" ? numberFormat($columnRow[$columnName], 0, "", "") : $columnRow[$columnName]));
						}
						break;
					case "decimal":
						$dataValue = (strlen($columnRow[$columnName]) == 0 ? "" : numberFormat($columnRow[$columnName], $columns[$fullColumnName]->getControlValue('decimal_places'), ".", ","));
						break;
					case "tinyint":
						$dataValue = ($columnRow[$columnName] ? "YES" : "no");
						break;
					default:
						$dataValue = $columnRow[$columnName];
				}
				$dataLine .= '"' . str_replace('"', '""', $dataValue) . '"';
			}
			foreach ($this->iAdditionalExportFields as $fieldName) {
				if (!empty($dataLine)) {
					$dataLine .= ",";
				}
				$dataLine .= '"' . str_replace('"', '""', $columnRow[$fieldName]) . '"';
			}
			echo $dataLine . "\r\n";
		}
		exit;
	}

	/**
	 *    function getSortList
	 *
	 *    not used by this class, but needed because it is abstract in tableEditor
	 */
	function getSortList() {
	}

	/**
	 *    function getRecord
	 *
	 *    not used by this class, but needed because it is abstract in tableEditor
	 */
	function getRecord() {
	}

	/**
	 *    function saveChanges
	 *
	 *    not used by this class, but needed because it is abstract in tableEditor
	 */
	function saveChanges() {
	}

	/**
	 *    function deleteRecord
	 *
	 *    not used by this class, but needed because it is abstract in tableEditor
	 */
	function deleteRecord() {
	}

	/**
	 *    function getSpreadsheetList
	 *
	 *    not used by this class, but needed because it is abstract in tableEditor
	 */
	function getSpreadsheetList() {
	}

	/**
	 *    function jQueryTemplates
	 *
	 *    not used by this class, but needed because it is abstract in tableEditor
	 */
	function jQueryTemplates() {
		$ignoreFields = array("client_id", "version");
		$columns = $this->iDataSource->getPrimaryTable()->getColumns();
		if (!empty($this->iDataSource->getJoinTable())) {
			$ignoreFields[] = $this->iDataSource->getJoinTable()->getPrimaryKey();
			$joinColumns = $this->iDataSource->getJoinTable()->getColumns();
			if (!empty($joinColumns)) {
				$columns = array_merge($columns, $joinColumns);
			}
		}
		$fieldCriteria = new DataColumn("query_select_criteria");
		$fieldCriteria->setControlValue("data_type", "custom");
		$fieldCriteria->setControlValue("control_class", "EditableList");
		$fieldCriteria->setControlValue("column_list", "column_name,comparator,field_value");
		$fieldCriteria->setControlValue("tabindex", "10");
		$fieldControls = array();
		$fieldColumnList = array();
		$validDataTypes = array("varchar", "date", "bigint", "int", "decimal", "tinyint", "text", "mediumtext", "select");
		/** @var DataColumn $thisColumn */
		foreach ($columns as $thisColumn) {
			$columnName = $thisColumn->getControlValue("column_name");
			$description = $thisColumn->getControlValue("form_label");
			$dataType = $thisColumn->getControlValue("data_type");
			if (in_array($columnName, $ignoreFields) || empty($description) || !in_array($dataType, $validDataTypes) || array_key_exists($columnName, $fieldColumnList)) {
				continue;
			}
			$fieldColumnList[$columnName] = array("key_value" => $thisColumn->getControlValue("full_column_name"), "description" => $description, "data-data_type" => $dataType, "data-foreign_key" => $thisColumn->getReferencedTable());
		}
		ksort($fieldColumnList);
		$fieldControls['column_name'] = array("data_type" => "select", "form_label" => "Field", "choices" => $fieldColumnList, "classes" => "query-select-column", "not_null" => true);
		$fieldControls['comparator'] = array("data_type" => "select", "form_label" => "", "choices" => array(), "not_null" => true, "classes" => "query-select-comparator");
		$fieldControls['field_value'] = array("data_type" => "varchar", "form_label" => "Value", "classes" => "query-select-value", "cell_classes" => "query-select-value-fields");
		$fieldCriteria->setControlValue("list_table_controls", $fieldControls);
		$fieldCriteriaControl = new EditableList($fieldCriteria, $this->iPageObject);
		echo $fieldCriteriaControl->getTemplate();
	}
}
