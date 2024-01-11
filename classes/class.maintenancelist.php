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
 * class MaintenanceList
 *
 * This class generates a list of records from a database table. It works within a template and a page.
 *
 * @author Kim D Geiger
 */
class MaintenanceList extends MaintenancePage {

	function pageElements() {
	}

	/**
	 *    function pageHeader
	 *
	 *    Create the page header. For the list, this will include buttons for the actions that can be executed from the list.
	 *    The list header also includes a bunch of actions that can be executed. If the developer set some custom actions,
	 *    these would appear in the actions dropdown. Filters can also be added so that the list can be filtered in set
	 *    ways as defined by the developer. A search field is in the header allowing the user to filter the list.
	 *
	 * @return true indicating that the page header has been completely done.
	 */
function pageHeader($pageHeaderFile = "") {
	if ($pageHeaderFile && file_exists($GLOBALS['gDocumentRoot'] . "/classes/" . $pageHeaderFile)) {
		include_once($pageHeaderFile);
		return true;
	}
	$startRow = getPreference("MAINTENANCE_START_ROW", $GLOBALS['gPageRow']['page_code']);
	$sortOrderColumn = getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
	$reverseSortOrder = getPreference("MAINTENANCE_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']);
	?>
    <form id="_edit_form" name="_edit_form">
        <div id="_list_header_div">
			<?php
			if ($GLOBALS['gUserRow']['superuser_flag']) {
				$customWhereValue = getPreference("MAINTENANCE_CUSTOM_WHERE_VALUE", $GLOBALS['gPageRow']['page_code']);
				?>
                <input type="hidden" id="_custom_where_value" name="_custom_where_value" value="<?= htmlText($customWhereValue) ?>"/>
			<?php } ?>
            <input type="hidden" id="_start_row" name="_start_row" value="<?= $startRow ?>"/>
            <input type="hidden" id="_next_start_row" name="_next_start_row" value=""/>
            <input type="hidden" id="_previous_start_row" name="_previous_start_row" value=""/>
            <input type="hidden" id="_sort_order_column" name="_sort_order_column" value="<?= $sortOrderColumn ?>"/>
            <input type="hidden" id="_reverse_sort_order" name="_reverse_sort_order" value="<?= ($reverseSortOrder ? "true" : "false") ?>"/>
            <input type="hidden" id="_set_filters" name="_set_filters" value="0">

            <div id='_action_label_section'><?= getLanguageText("Action") ?></div>
            <div id="_action_section">
                <select tabindex="8500" id="_action" name='_action' class="field-text">
                    <option value=''>[<?= getLanguageText("Choose_Action") ?>]</option>
                    <option value='preferences'><?= getLanguageText("Preferences") ?></option>
					<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
                        <option value='customwhere'><?= getLanguageText("Where") ?></option>
					<?php } ?>
					<?php
					if ($GLOBALS['gPermissionLevel'] > _READONLY && $this->iDataSource->getPrimaryTable()->columnExists("sort_order")) {
						$threshold = getPreference("GUI_SORT_THRESHOLD");
						if (empty($threshold)) {
							$threshold = 100;
						}
                        $tableCount = DataTable::getLimitedCount($this->iDataSource->getPrimaryTable()->getName(), $threshold);
						if ($tableCount > 1 and $tableCount <= $threshold) {
							?>
                            <option value='guisort'><?= getLanguageText("Visual_Sort") ?></option>
                            <option value='resetsort'><?= getLanguageText("Reset_Sort") ?></option>
							<?php
						}
					}
					if ($GLOBALS['gPermissionLevel'] > _READONLY && ($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access'] || hasCapability("SPREADSHEET_EDITING"))) {
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
							switch ($thisColumn->getControlValue("data_type")) {
								case "varchar":
								case "text":
								case "mediumtext":
								case "decimal":
								case "date":
								case "time":
									$columnCount++;
									break;
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
                            <option value='spreadsheet'><?= getLanguageText("Editable_Spreadsheet") ?></option>
							<?php
						}
					}
					?>
                    <option value='clearall'><?= getLanguageText("Unselect All") ?></option>
                    <option value='selectall'><?= getLanguageText("Select All") ?></option>
                    <option value='clearlisted'><?= getLanguageText("Unselect Listed") ?></option>
                    <option value='clearnotlisted'><?= getLanguageText("Unselect Not Listed") ?></option>
                    <option value='showselected'><?= getLanguageText("Show Selected") ?></option>
					<?php if (!$this->iRemoveExport) { ?>
                        <option value='exportcsv'><?= getLanguageText("Export Selected to CSV") ?></option>
					<?php } ?>
					<?php
					foreach ($this->iCustomActions as $customAction => $description) {
						?>
                        <option value='<?= $customAction ?>'><?= $description ?></option>
						<?php
					}
					?>
                </select>
            </div>

            <div id="_record_counts_section"><span id="_start_record_display">0</span> - <span id="_end_record_display">0</span> of <span id='_row_count'>0</span><br><span id="_selected_count">0</span> <?= getLanguageText("selected") ?></div>

            <div id="_previous_page_section">
                <span id="_previous_button"><span class='fa fa-chevron-left'></span>Prev<br>Page</span>
            </div>
            <div id="_page_section">
                <select tabindex="8500" class='field-text' id="_page_number" name="_page_number">
                    <option value="1">1</option>
                </select>
            </div>
            <div id="_next_page_section">
                <span id="_next_button">Next<br>Page<span class='fa fa-chevron-right'></span></span>
            </div>

            <div id="_search_section">
                <input tabindex="8000" type='text' id='_filter_text' name='_filter_text' value="<?= $searchValue = getPreference("MAINTENANCE_FILTER_TEXT", $GLOBALS['gPageRow']['page_code']); ?>"/>
                <div id="_search_button"><span class="fa fa-search"></span></div>
            </div>
			<?php if (count($this->iFilters) > 0) { ?>
                <div id="_filter_dialog">
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
						?>
                        <div class="form-line" id="_set_filter_<?= $columnName ?>_row">
                            <label for="<?= "_set_filter_" . $columnName ?>"><?= htmlText($checkbox ? "" : $thisColumn->getControlValue("form_label")) ?></label>
							<?= $thisColumn->getControl($this) ?>
                            <div class='clear-div'></div>
                        </div>
						<?php
					}
					?>
                </div>
			<?php } ?>
			<?php if (!in_array("add", $this->iDisabledFunctions)) { ?>
                <div id="_button_section">
					<?php
					$buttonFunctions = array();
					if (count($this->iFilters) > 0) {
						$buttonFunctions['filter'] = array("label" => getLanguageText("Filters") . "<span id='_filters_on'> SET</span>");
					}

					$buttonFunctions['add'] = array("accesskey" => "a", "label" => getLanguageText("Add"), "disabled" => ($GLOBALS['gPermissionLevel'] < _READWRITE || $this->iReadonly ? true : false));
					$this->displayButtons("all", false, $buttonFunctions);
					?>
                </div>
			<?php } ?>
        </div>
		<?php
		if (count($this->iVisibleFilters) == 0) {
			echo "</form>";
		}
		return true;
		}

		/**
		 *    function mainContent
		 *
		 *    The list will be filled in by ajax, so the only html markup added here is the empty table.
		 */
		function mainContent() {
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
                <div class="form-line" id="_set_filter_<?= $columnName ?>_row">
                    <label for="<?= "_set_filter_" . $columnName ?>"><?= htmlText($checkbox ? "" : $thisColumn->getControlValue("form_label")) ?></label>
					<?= $thisColumn->getControl($this) ?>
                    <div class='clear-div'></div>
                </div>
				<?php
			}
			?>
        </div>
    </form>
	<?php
}
	if (method_exists($this->iPageObject, "beforeList")) {
		$this->iPageObject->beforeList();
	}
	echo "<table id='_maintenance_list'></table>";
}

	/**
	 *    function onLoadPageJavascript
	 *
	 *    javascript code that is executed once the page is loaded.
	 */
	function onLoadPageJavascript() {
		if ($this->iPageObject->onLoadPageJavascript()) {
			return;
		}
		?>
        <script>
            $(function () {
                $("#_filter_section").find("input[type=checkbox],input[type=text],select").change(function () {
                    $("#_set_filters").val("1");
                    getDataList();
                    return true;
                });
                $(document).keydown(function (event) {
                    if (event.which == 34) {
                        $("#_next_button").trigger("click");
                        return false;
                    } else if (event.which == 33) {
                        $("#_previous_button").trigger("click");
                        return false;
                    }
                    if (!$("#_filter_text").is(":focus")) {
                        if (event.which == 39) {
                            $("#_next_button").trigger("click");
                            return false;
                        } else if (event.which == 37) {
                            $("#_previous_button").trigger("click");
                            return false;
                        }
                    }
                });
                $(document).on("tap click", ".delete-item", function () {
                    $(this).parent("li").remove();
                });
                $("#_list_columns,#_export_columns").sortable({
                    revert: true,
                    receive: function () {
                        $(this).find("li").each(function () {
                            if ($(this).find("img").length == 0) {
                                $(this).append("<img class='delete-item' src='images/delete.gif' alt='delete'>");
                            }
                        })
                    }
                }).disableSelection();
                $("#_column_list li").draggable({
                    connectToSortable: ".connected-sortable",
                    helper: "clone",
                    revert: "invalid"
                }).disableSelection();
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
                    $("#_filter_dialog").dialog({
                        width: 600,
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
							<?= getLanguageText("Save") ?>: function (event) {
                                $("#_filter_dialog").dialog('destroy');
                                $("#_set_filters").val("1");
                                getDataList();
                            },
							<?= getLanguageText("Cancel") ?>: function (event) {
                                $("#_filter_dialog").dialog('close');
                                resetFilters();
                            }
                        }
                    });
                    return false;
                });
                $(document).on("tap click", "#_search_fields_icon", function () {
                    var showSearchFields = true;
                    if ($("#_filter_column").is(":visible")) {
                        $("#_filter_column").hide();
                        $("#_search_fields_icon_image").removeClass("fa-search-minus").addClass("fa-search-plus");
                        $("#_search_section").css("padding-right", "20px");
                        showSearchFields = false;
                    } else {
                        $("#_filter_column").show();
                        $("#_search_fields_icon_image").removeClass("fa-search-plus").addClass("fa-search-minus");
                        $("#_search_section").css("padding-right", "0px");
                    }
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=show_search_fields&value=" + (showSearchFields ? "true" : ""));
                });
                $("#_filter_column").change(function () {
                    $("#_search_button").data("show_all", "");
                });
                $("#_filter_text").keyup(function (event) {
                    $("#_search_button").data("show_all", "");
                    if (event.which == 13 || event.which == 3) {
                        $("#_search_button").trigger("click");
                        return false;
                    }
                    return true;
                });
                $(document).on("tap click", "#_search_button", function (event) {
                    if ($(this).data("show_all") == "true") {
                        $(this).data("show_all", "");
                        $("#_filter_column").val("");
                        $("#_filter_text").val("");
                        $("#_custom_where_value").val("");
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
                $(document).on("tap click", "#_next_button", function (event) {
                    if ($("#_start_row").val() != $("#_next_start_row").val() && $("#_next_start_row").val() != "") {
                        $("#_start_row").val($("#_next_start_row").val());
                        $("#_set_filters").val("1");
                        getDataList();
                    }
                    return false;
                });
                $(document).on("tap click", "#_previous_button", function (event) {
                    if ($("#_start_row").val() != $("#_previous_start_row").val() && $("#_previous_start_row").val() != "") {
                        $("#_start_row").val($("#_previous_start_row").val());
                        $("#_set_filters").val("1");
                        getDataList();
                    }
                    return false;
                });
                $(document).on("tap click", ".data-row", function () {
                    if (typeof dataRowClicked == "function") {
                        if (!dataRowClicked()) {
                            return false;
                        }
                    }
					<?= (empty($this->iListItemUrl) ? "document.location = \"" . $GLOBALS['gLinkUrl'] . "?url_page=show&primary_id=\" + $(this).data('primary_id')" : $this->iListItemUrl) ?>;
                });
                $(document).on("tap click", ".column-header", function () {
                    if (!$(this).is(".no-sort")) {
                        $("#_start_row").val("0");
                        $("#_sort_order_column").val($(this).data("column_name"));
                        $("#_reverse_sort_order").val(($(this).is(".sort-normal") ? "true" : "false"));
                        $("#_set_filters").val("1");
                        getDataList();
                    }
                });
                if ($("#_next_button").is("button")) {
                    $("#_next_button").button("disable");
                }
                $("#_action").change(function () {
                    switch ($(this).val()) {
                        case "guisort":
                        case "spreadsheet":
                        case "resetsort":
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=" + $(this).val();
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
                            $("#_export_frame").attr("src", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=exportcsv");
                            break;
					<?php } ?>
                        default:
                            if (typeof customActions == "function") {
                                if (customActions($(this).val())) {
                                    break;
                                }
                            }
                            getDataList($(this).val());
                            break;
                    }
                    $("#_action").val("");
                });
                $(document).on("tap click", "#_preference_close_box", function () {
                    $("#_cancel_preferences").trigger("click");
                });
                $(document).on("tap click", ".select-checkbox", function (event) {
                    event.stopPropagation();
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_row&primary_id=" + $(this).closest("tr").data("primary_id") + "&set=" + ($(this).prop("checked") ? "yes" : "no"), function(returnArray) {
                        $("#_selected_count").html(returnArray['_selected_count']);
                    });
                });
				<?php if (!empty($this->iErrorMessage)) { ?>
                displayErrorMessage("<?= htmlText($this->iErrorMessage) ?>");
				<?php } ?>
                getDataList();
				<?php if (getPreference("MAINTENANCE_SHOW_FILTER_COLUMNS", $GLOBALS['gPageRow']['page_code'])) { ?>
                $("#_search_fields_icon").trigger("click");
				<?php } ?>
            });
        </script>
		<?php
	}

	/**
	 *    function pageJavascript
	 *
	 *    javascript code, mostly functions, that are added to the page.
	 */
	function pageJavascript() {
		if ($this->iPageObject->pageJavascript()) {
			return;
		}
		?>
        <script>
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
						<?= getLanguageText("Save") ?>: function (event) {
                            $("#_custom_where_value").val($("#_custom_where_entry").val());
                            $("#_custom_where").dialog('close');
                            getDataList();
                        },
						<?= getLanguageText("Cancel") ?>: function (event) {
                            $("#_custom_where").dialog('close');
                        }
                    }
                });
            }
			<?php } ?>

            function preferences() {
                $("#_preferences").dialog({
                    height: 550,
                    width: 600,
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
						<?= getLanguageText("Save") ?>: function (event) {
                            preferenceAction(true);
                            $("#_preferences").dialog('close');
                        },
						<?= getLanguageText("Cancel") ?>: function (event) {
                            $("#_preferences").dialog('close');
                        }
                    }
                });
            }

            function preferenceAction(savePreferences) {
                $("#_export_columns_items").add("#_list_columns_items").val("");
                var listItems = "";
                $("#_list_columns li").each(function () {
                    if (listItems != "") {
                        listItems += ",";
                    }
                    listItems += $(this).data("column_name");
                });
                $("#_list_columns_items").val(listItems);
                listItems = "";
                $("#_export_columns li").each(function () {
                    if (listItems != "") {
                        listItems += ",";
                    }
                    listItems += $(this).data("column_name");
                });
                $("#_export_columns_items").val(listItems);
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=preferences&subaction=" + (savePreferences ? "save" : "cancel"), $("#_preference_form").serialize(), function(returnArray) {
                    $("#maintenance_item_count").val(returnArray['maintenance_item_count']);
                    $("#_list_columns li").remove();
                    for (var i in returnArray['list_columns']) {
                        $("#_list_columns").append("<li class='ui-state-default' data-column_name='" + i + "'>" + returnArray['list_columns'][i] + "<img class='delete-item' src='images/delete.gif' alt='delete'></li>");
                    }
                    $("#_export_columns li").remove();
                    for (var i in returnArray['export_columns']) {
                        $("#_export_columns").append("<li class='ui-state-default' data-column_name='" + i + "'>" + returnArray['export_columns'][i] + "<img class='delete-item' src='images/delete.gif' alt='delete'></li>");
                    }
                    getDataList();
                });
            }

            function resetFilters() {
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
                    $("#_maintenance_list tr").remove();
                    if ("column_headers" in returnArray && typeof returnArray['column_headers'] == "object") {
                        var headerRow = "<tr><th>&nbsp;</th>";
                        for (var i in returnArray['column_headers']) {
                            headerRow += "<th data-column_name='" + returnArray['column_headers'][i]['column_name'] + "' class='column-header " + returnArray['column_headers'][i]['class_names'] + "'>" + returnArray['column_headers'][i]['description'] + "</th>";
                        }
                        headerRow += "<th></th></tr>";
                        $("#_maintenance_list").append(headerRow);
                    }
                    if ("data_list" in returnArray && typeof returnArray['data_list'] == "object") {
                        for (var i in returnArray['data_list']) {
                            var rowClasses = "";
                            if ("row_classes" in returnArray['data_list'][i]) {
                                rowClasses = " " + returnArray['data_list'][i]['row_classes'];
                            }
                            var dataRow = "<tr class='data-row" + rowClasses + "' data-primary_id='" + returnArray['data_list'][i]['_primary_id'] + "'><td><input type='checkbox' class='select-checkbox'" + (returnArray['data_list'][i]['_selected'] == 1 ? " checked" : "") + " /></td>";
                            for (var j in returnArray['column_headers']) {
                                var columnName = (returnArray['column_headers'][j]['column_name'].indexOf(".") < 0 ? returnArray['column_headers'][j]['column_name'] : returnArray['column_headers'][j]['column_name'].substring(returnArray['column_headers'][j]['column_name'].indexOf(".") + 1));
                                dataRow += "<td class='data-row-data " + returnArray['data_list'][i][columnName]['class_names'] + "'>" + returnArray['data_list'][i][columnName]['data_value'] + "</td>";
                            }
                            dataRow += "<td></td></tr>";
                            $("#_maintenance_list").append(dataRow);
                        }
                    }
                    for (var i in returnArray) {
                        if (typeof returnArray[i] == "number" || typeof returnArray[i] == "string") {
                            if ($("#" + i).is("input[type=checkbox]")) {
                                $("#" + i).prop("checked", returnArray[i] == "1")
                            } else if ($("#" + i).is("span")) {
                                $("span#" + i).html(returnArray[i]);
                            } else {
                                $("#" + i).val(returnArray[i]);
                            }
                        } else if ((typeof returnArray[i] == "array" || typeof returnArray[i] == "object") && $("select#" + i).length > 0) {
                            if (!("data_value" in returnArray[i])) {
                                $("#" + i + " option").remove();
                                var selectedOption = "";
                                for (var j in returnArray[i]) {
                                    $("#" + i).append("<option value='" + returnArray[i][j]['value'] + "'>" + returnArray[i][j]['description'] + "</option>");
                                    if ("selected" in returnArray[i][j]) {
                                        selectedOption = returnArray[i][j]['value'];
                                    }
                                }
                                if (selectedOption != "") {
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
                    if ("_filter_column" in returnArray && returnArray['_filter_column'] != "") {
                        $("#_search_fields_icon").addClass("color-red").attr("title", "<?= getLanguageText("Column filter is set") ?>");
                    } else {
                        $("#_search_fields_icon").removeClass("color-red").attr("title", "");
                    }
                    if ("list_shaded" in returnArray && returnArray['list_shaded'] == "true") {
                        $("#_maintenance_list").addClass("shaded");
                    } else {
                        $("#_maintenance_list").removeClass("shaded");
                    }
                    $("#_next_button").css("visibility", ($("#_next_start_row").val().length == 0 ? "hidden" : "visible"));
                    $("#_page_number").css("visibility", ($("#_next_start_row").val().length == 0 && $("#_previous_start_row").val().length == 0 ? "hidden" : "visible"));
                    $("#_previous_button").css("visibility", ($("#_previous_start_row").val().length == 0 ? "hidden" : "visible"));
                    $("#_search_button").data("show_all", "true");
                    if (typeof afterGetDataList == "function") {
                        afterGetDataList();
                    }
                });
            }
        </script>
		<?php
	}

	/**
	 *    function getDataList
	 *
	 *    Filter the list and send it to the browser, where it is displayed.
	 */
	function getDataList() {
		if (array_key_exists("_sort_order_column", $_POST)) {
			$originalSortOrderColumn = getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
			if ($originalSortOrderColumn != $_POST['_sort_order_column']) {
				setUserPreference("MAINTENANCE_SECONDARY_SORT_ORDER_COLUMN", getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']), $GLOBALS['gPageRow']['page_code']);
				setUserPreference("MAINTENANCE_SECONDARY_REVERSE_SORT_ORDER", getPreference("MAINTENANCE_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']), $GLOBALS['gPageRow']['page_code']);
			}
			setUserPreference("MAINTENANCE_SORT_ORDER_COLUMN", $_POST['_sort_order_column'], $GLOBALS['gPageRow']['page_code']);
			setUserPreference("MAINTENANCE_REVERSE_SORT_ORDER", $_POST['_reverse_sort_order'], $GLOBALS['gPageRow']['page_code']);
		}
		$userPreferenceCodes = array(
			"start_row",
			"custom_where_value",
			"filter_text",
			"filter_column"
		);
		foreach ($userPreferenceCodes as $preferenceCode) {
			if (array_key_exists("_" . $preferenceCode, $_POST)) {
				setUserPreference("MAINTENANCE_" . strtoupper($preferenceCode), $_POST['_' . $preferenceCode], $GLOBALS['gPageRow']['page_code']);
			}
		}
		$returnArray = array();
		if (count($this->iFilters) > 0 || count($this->iVisibleFilters) > 0) {
			$setFilters = array();
			if (array_key_exists("_set_filters", $_POST) && $_POST['_set_filters'] == "1") {
				foreach (array_merge($this->iFilters, $this->iVisibleFilters) as $filterCode => $filterInfo) {
					$setFilters[$filterCode] = $_POST["_set_filter_" . $filterCode];
				}
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

		$subaction = $_GET['subaction'];
		if (!empty($GLOBALS['gUserId']) && !empty($GLOBALS['gPageId'])) {
			switch ($subaction) {
				case "clearall":
					executeQuery("delete from selected_rows where user_id = ? and page_id = ?", array($GLOBALS['gUserId'], $GLOBALS['gPageId']));
					break;
				case "showselected":
					$_POST['filter_text'] = "";
					$this->iDataSource->addFilterWhere($this->iDataSource->getPrimaryTable()->getName() . "." .
						$this->iDataSource->getPrimaryTable()->getPrimaryKey() . " in (select primary_identifier from selected_rows " .
						"where user_id = " . $GLOBALS['gUserId'] . " and page_id = " . $GLOBALS['gPageId'] . ")");
					break;
			}
		}
		if ($this->iDataSource->getPrimaryTable()->columnExists("inactive") && !getPreference("MAINTENANCE_SHOW_INACTIVE", $GLOBALS['gPageRow']['page_code'])) {
			$this->iDataSource->addFilterWhere($this->iDataSource->getPrimaryTable()->getName() . ".inactive = 0");
		}

		$returnArray['list_shaded'] = getPreference("MAINTENANCE_LIST_SHADED", $GLOBALS['gPageRow']['page_code']);
		$returnArray['_filter_text'] = getPreference("MAINTENANCE_FILTER_TEXT", $GLOBALS['gPageRow']['page_code']);
		$searchColumn = getPreference("MAINTENANCE_FILTER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		if (method_exists($this->iPageObject, "filterTextProcessing")) {
			$this->iPageObject->filterTextProcessing($returnArray['_filter_text']);
		} else {
			$this->iDataSource->setFilterText($returnArray['_filter_text']);
		}
		if ($GLOBALS['gUserRow']['superuser_flag']) {
			$customWhereValue = getPreference("MAINTENANCE_CUSTOM_WHERE_VALUE", $GLOBALS['gPageRow']['page_code']);
			if (!empty($customWhereValue)) {
				$this->iDataSource->setFilterWhere($customWhereValue);
			}
		}

		$foreignKeys = $this->iDataSource->getForeignKeyList();
		foreach ($foreignKeys as $columnName => $foreignKeyInfo) {
			if (!in_array($columnName, $this->iExcludeListColumns)) {
				if (!empty($foreignKeyInfo['description'])) {
					$this->iDataSource->addColumnControl($foreignKeyInfo['column_name'] . "_display", "select_value",
						"select concat_ws(' '," . implode(",", $foreignKeyInfo['description']) . ") from " .
						$foreignKeyInfo['referenced_table_name'] . " as subselect_table where subselect_table." .
						$foreignKeyInfo['referenced_column_name'] . " = " . $columnName);
					if (empty($searchColumn) || $searchColumn == $columnName) {
						$this->iDataSource->addSearchableSubfield(array("referenced_table_name" => $foreignKeyInfo['referenced_table_name'],
							"referenced_column_name" => $foreignKeyInfo['referenced_column_name'], "foreign_key" => $foreignKeyInfo['column_name'],
							"description" => $foreignKeyInfo['description']));
					}
					$this->iExcludeListColumns[] = $foreignKeyInfo['column_name'] . "_display";
				}
			}
		}
		$columns = $this->iDataSource->getColumns();
		$allSearchColumns = array();
		foreach ($columns as $columnName => $thisColumn) {
			switch ($thisColumn->getControlValue('data_type')) {
				case "date":
				case "time":
				case "int":
				case "select":
				case "text":
				case "mediumtext":
				case "varchar":
				case "decimal":
					if ($columns[$columnName]->getControlValue("primary_key") || !in_array($columnName, $this->iExcludeSearchColumns)) {
						$allSearchColumns[] = $columnName;
					}
					break;
				default;
					continue 2;
			}
		}

		$sortOrderColumn = getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$secondarySortOrderColumn = getPreference("MAINTENANCE_SECONDARY_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$listColumns = explode(",", getPreference("MAINTENANCE_LIST_COLUMNS", $GLOBALS['gPageRow']['page_code']));
		if (count($listColumns) == 0 || (count($listColumns) == 1 && empty($listColumns[0]))) {
			$listColumns = array();
			if (count($this->iListSortOrder) > 0) {
				foreach ($this->iListSortOrder as $columnName) {
					if (strpos($columnName, ".") === false) {
						$fullColumnName = $this->iDataSource->getPrimaryTable()->getName() . "." . $columnName;
					}
					if (array_key_exists($fullColumnName, $columns)) {
						$listColumns[] = $fullColumnName;
					} else if (array_key_exists($columnName, $columns)) {
						$listColumns[] = $columnName;
					}
				}
			}
			foreach ($columns as $columnName => $thisColumn) {
				if (count($listColumns) >= $this->iMaximumListColumns && $this->iMaximumListColumns > 0) {
					break;
				}
				if (in_array($columnName, $listColumns)) {
					continue;
				}
				$dataType = $thisColumn->getControlValue("data_type");
				$subType = $thisColumn->getControlValue('subtype');
				if (!in_array($columnName, $this->iExcludeListColumns) && $dataType != "longblob" && $dataType != "custom_control" && $dataType != "custom" && $dataType != "button" && empty($subType)) {
					$listColumns[] = $columnName;
				}
			}
		}
		foreach ($listColumns as $thisIndex => $columnName) {
			if (!array_key_exists($columnName, $columns) || (!$columns[$columnName]->getControlValue("primary_key") && in_array($columnName, $this->iExcludeListColumns))
				|| $columns[$columnName]->getControlValue("data_type") == "longblob" || $columns[$columnName]->getControlValue("data_type") == "custom_control" || $columns[$columnName]->getControlValue("data_type") == "custom" || $columns[$columnName]->getControlValue("data_type") == "button") {
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
			$reverseSortOrder = getPreference("MAINTENANCE_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']);
			$reverseSortOrders = array($reverseSortOrder ? "desc" : "asc");
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
		} else {
			$this->iDataSource->setSortOrder($this->iDataSource->getPrimaryTable()->getPrimaryKey());
		}
		if (empty($searchColumn) || !in_array($searchColumn, $allSearchColumns)) {
			$this->iDataSource->setSearchFields($allSearchColumns);
			$returnArray['_filter_column'] = "";
		} else {
			$this->iDataSource->setSearchFields($searchColumn);
			$returnArray['_filter_column'] = $searchColumn;
		}
		$itemCount = getPreference("MAINTENANCE_ITEM_COUNT", $GLOBALS['gPageRow']['page_code']);
		if (empty($itemCount)) {
			$itemCount = 20;
		}
		$startRow = getPreference("MAINTENANCE_START_ROW", $GLOBALS['gPageRow']['page_code']);
		if (empty($startRow) || $startRow <= 0) {
			$startRow = 0;
		}
		$dataList = $this->iDataSource->getDataList(array("row_count" => $itemCount, "start_row" => $startRow));
		if ($startRow > 0 && count($dataList) == 0) {
			$startRow = 0;
			setUserPreference("MAINTENANCE_START_ROW", $startRow, $GLOBALS['gPageRow']['page_code']);
			$dataList = $this->iDataSource->getDataList(array("row_count" => $itemCount, "start_row" => $startRow));
		}
		if (method_exists($this->iPageObject, "dataListProcessing")) {
			$this->iPageObject->dataListProcessing($dataList);
		}
		if (!empty($GLOBALS['gUserId']) && !empty($GLOBALS['gPageId'])) {
			$whereStatementParts = $this->iDataSource->getQueryWhere();
			$whereStatement = $whereStatementParts['where_statement'];
			$whereParameters = $whereStatementParts['where_parameters'];
			switch ($subaction) {
				case "selectall":
					executeQuery("delete from selected_rows where user_id = ? and page_id = ?" .
						" and primary_identifier in (select " . $this->iDataSource->getPrimaryTable()->getName() . "." . $this->iDataSource->getPrimaryTable()->getPrimaryKey() .
						" from " . $this->iDataSource->getTableList() . (empty($whereStatement) ? "" : " where " . $whereStatement) . ")", array_merge(array($GLOBALS['gUserId'], $GLOBALS['gPageId']), $whereParameters));
					executeQuery("insert into selected_rows (selected_row_id,user_id,page_id,primary_identifier,version) " .
						"select null,?,?," . $this->iDataSource->getPrimaryTable()->getName() . "." . $this->iDataSource->getPrimaryTable()->getPrimaryKey() . ",1 from " .
						$this->iDataSource->getTableList() . (empty($whereStatement) ? "" : " where " . $whereStatement), array_merge(array($GLOBALS['gUserId'], $GLOBALS['gPageId']), $whereParameters));
					break;
				case "clearlisted":
					executeQuery("delete from selected_rows where user_id = ? and page_id = ?" .
						" and primary_identifier in (select " . $this->iDataSource->getPrimaryTable()->getName() . "." . $this->iDataSource->getPrimaryTable()->getPrimaryKey() .
						" from " . $this->iDataSource->getTableList() . (empty($whereStatement) ? "" : " where " . $whereStatement) . ")", array_merge(array($GLOBALS['gUserId'], $GLOBALS['gPageId']), $whereParameters));
					break;
				case "clearnotlisted":
					executeQuery("delete from selected_rows where user_id = ? and page_id = ?" .
						" and primary_identifier not in (select " . $this->iDataSource->getPrimaryTable()->getName() . "." . $this->iDataSource->getPrimaryTable()->getPrimaryKey() .
						" from " . $this->iDataSource->getTableList() . (empty($whereStatement) ? "" : " where " . $whereStatement) . ")", array_merge(array($GLOBALS['gUserId'], $GLOBALS['gPageId']), $whereParameters));
					break;
			}
		}

		$returnArray['_row_count'] = $this->iDataSource->getDataListCount();
		$returnArray['_start_record_display'] = min($startRow + 1, $this->iDataSource->getDataListCount());
		$returnArray['_end_record_display'] = min($returnArray['_start_record_display'] - 1 + $itemCount, $this->iDataSource->getDataListCount());
		$pageCount = max(ceil($this->iDataSource->getDataListCount() / $itemCount), 1);
		$thisPageNumber = ceil(($returnArray['_start_record_display'] - 1) / $itemCount) + 1;
		$pageNumbers = array();
		for ($x = 1; $x <= $pageCount; $x++) {
			$pageNumbers[$x - 1] = array("value" => ($itemCount * ($x - 1)), "description" => $x);
			if ($x == $thisPageNumber) {
				$pageNumbers[$x - 1]['selected'] = true;
			}
		}
		$returnArray['_page_number'] = $pageNumbers;
		if ($startRow == 0) {
			$previousStartRow = "";
		} else {
			$previousStartRow = max($startRow - $itemCount, 0);
		}
		$nextStartRow = $startRow + $itemCount;
		if ($nextStartRow >= $returnArray['_row_count']) {
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
			if (empty($listHeader)) {
				$listHeader = $columns[$columnName]->getControlValue('form_label');
			}
			$returnArray['column_headers'][$columnIndex]['description'] = $listHeader .
				($sortOrderColumn == $columnName ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-sort-down'></span>" : "<span class='fa fa-sort-up'></span>") : "");
			$classes = array();
			if ($columns[$columnName]->getControlValue('not_sortable')) {
				$classes[] = "no-sort";
			}
			if ($columns[$columnName]->getControlValue('data_type') == "decimal" || $columns[$columnName]->getControlValue('data_type') == "int") {
				$classes[] = "align-right";
			}
			if ($sortOrderColumn == $columnName) {
				$classes[] = ($reverseSortOrder ? "sort-reverse" : "sort-normal");
			}
			$returnArray['column_headers'][$columnIndex]['class_names'] = implode(" ", $classes);
			$columnIndex++;
		}

		foreach ($this->iExtraColumns as $columnName => $columnData) {
			$returnArray['column_headers'][$columnIndex] = array();
			$returnArray['column_headers'][$columnIndex]['column_name'] = $columnName;
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
				$selectedRowPrimaryKeys[] = $rowIndex;
			}
			freeResult($resultSet);
		}

		foreach ($dataList as $rowIndex => $columnRow) {
			$returnArray['data_list'][$rowIndex] = array();
			if (in_array($rowIndex, $selectedRowPrimaryKeys)) {
				$returnArray['data_list'][$rowIndex]["_selected"] = "1";
			} else {
				$returnArray['data_list'][$rowIndex]["_selected"] = "0";
			}
			$returnArray['data_list'][$rowIndex]["_primary_id"] = $columnRow[$this->iDataSource->getPrimaryTable()->getPrimaryKey()];
			if (method_exists($this->iPageObject, "getListRowClasses")) {
				$returnArray['data_list'][$rowIndex]['row_classes'] = $this->iPageObject->getListRowClasses($columnRow);
			}
			foreach ($this->iExtraColumns as $columnName => $columnData) {
				if (startsWith($columnData['data_value'], "return ")) {
					if (substr($columnData['data_value'], -1) != ";") {
						$columnData['data_value'] .= ";";
					}
					$columnData['data_value'] = eval($columnData['data_value']);
				}
				$returnArray['data_list'][$rowIndex][$columnName] = $columnData;
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
					case "int":
					case "select":
					case "autocomplete":
						if (array_key_exists($fullColumnName, $foreignKeys)) {
							$returnArray['data_list'][$rowIndex][$columnName]['data_value'] = htmlText(getFirstPart($columnRow[$columnName . "_display"], $this->iListDataLength));
						} else {
							$returnArray['data_list'][$rowIndex][$columnName]['data_value'] = (strlen($columnRow[$columnName]) == 0 ? "" : ($mysqlType == "int" ? number_format($columnRow[$columnName], 0, "", "") : $columnRow[$columnName]));
							if ($mysqlType == "int") {
								$classNames[] = "align-right";
							}
						}
						break;
					case "decimal":
						$returnArray['data_list'][$rowIndex][$columnName]['data_value'] = (strlen($columnRow[$columnName]) == 0 ? "" : number_format($columnRow[$columnName], $columns[$fullColumnName]->getControlValue('decimal_places'), ".", ","));
						$classNames[] = "align-right";
						break;
					case "tinyint":
						$returnArray['data_list'][$rowIndex][$columnName]['data_value'] = (empty($columnRow[$columnName]) ? "" : "YES");
						$classNames[] = "align-center";
						break;
					default:
						$returnArray['data_list'][$rowIndex][$columnName]['data_value'] = ($columns[$fullColumnName]->getControlValue('dont_escape') ? $columnRow[$columnName] : htmlText(getFirstPart($columnRow[$columnName], $this->iListDataLength)));
				}
				$returnArray['data_list'][$rowIndex][$columnName]['class_names'] = implode(" ", $classNames);
			}
		}
		$returnArray['_selected_count'] = 0;
		executeQuery("delete from selected_rows where user_id = ? and page_id = ?" .
			" and primary_identifier not in (select " . $this->iDataSource->getPrimaryTable()->getPrimaryKey() . " from " .
			$this->iDataSource->getPrimaryTable()->getName() . ")", array($GLOBALS['gUserId'], $GLOBALS['gPageId']));
		$resultSet = executeQuery("select count(*) from selected_rows where user_id = ? and page_id = ?", array($GLOBALS['gUserId'], $GLOBALS['gPageId']));
		if ($row = getNextRow($resultSet)) {
			$returnArray['_selected_count'] = $row['count(*)'];
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
			"list_shaded",
			"show_inactive",
			"save_no_list"
		);
		foreach ($userPreferenceCodes as $preferenceCode) {
			if ($_GET['subaction'] == "save") {
				setUserPreference("MAINTENANCE_" . strtoupper($preferenceCode), $_POST['maintenance_' . $preferenceCode], $GLOBALS['gPageRow']['page_code']);
			} else {
				$_POST['maintenance_' . $preferenceCode] = getPreference("MAINTENANCE_" . strtoupper($preferenceCode), $GLOBALS['gPageRow']['page_code']);
			}
			$returnArray['maintenance_' . $preferenceCode] = $_POST['maintenance_' . $preferenceCode];
		}
		$columns = $this->iDataSource->getColumns();

		$exportColumnArray = array();
		if ($_GET['subaction'] == "save") {
			$exportColumns = array();
			foreach (explode(",", $_POST['_export_columns_items']) as $columnName) {
				if (!in_array($columnName, $exportColumns)) {
					if (array_key_exists($columnName, $columns)) {
						$thisColumn = $columns[$columnName];
						$subType = $thisColumn->getControlValue('subtype');
						$columnText = $thisColumn->getControlValue("form_label");
						if ($this->iUseColumnNameInFilter || empty($columnText) || is_null($columnText)) {
							$columnText = ucwords(str_replace("_", " ", $thisColumn->getControlValue("column_name")));
						}
						if ($thisColumn->getControlValue("primary_key") || (!in_array($columnName, $this->iExcludeListColumns) &&
								$thisColumn->getControlValue('data_type') != "longblob" && empty($subType) && $thisColumn->getControlValue("data_type") != "custom_control" && $thisColumn->getControlValue("data_type") != "custom" && $thisColumn->getControlValue("data_type") != "button")) {
							$exportColumns[] = $columnName;
						}
					}
				}
			}
			setUserPreference("MAINTENANCE_EXPORT_COLUMNS", implode(",", $exportColumns), $GLOBALS['gPageRow']['page_code']);
		}
		$exportColumns = explode(",", getPreference("MAINTENANCE_EXPORT_COLUMNS", $GLOBALS['gPageRow']['page_code']));
		foreach ($exportColumns as $columnName) {
			if (array_key_exists($columnName, $columns)) {
				if (array_key_exists($columnName, $columns)) {
					$thisColumn = $columns[$columnName];
					$subType = $thisColumn->getControlValue('subtype');
					$columnText = $thisColumn->getControlValue("form_label");
					if ($this->iUseColumnNameInFilter || empty($columnText) || is_null($columnText)) {
						$columnText = ucwords(str_replace("_", " ", $thisColumn->getControlValue("column_name")));
					}
					if ($thisColumn->getControlValue("primary_key") || (!in_array($columnName, $this->iExcludeListColumns) &&
							$thisColumn->getControlValue('data_type') != "longblob" && empty($subType) && $thisColumn->getControlValue("data_type") != "custom_control" && $thisColumn->getControlValue("data_type") != "custom" && $thisColumn->getControlValue("data_type") != "button")) {
						$exportColumnArray[$columnName] = $columns[$columnName]->getControlValue('form_label');
					}
				}
			}
		}
		$returnArray['export_columns'] = $exportColumnArray;

		$listColumnArray = array();
		if ($_GET['subaction'] == "save") {
			$listColumns = array();
			foreach (explode(",", $_POST['_list_columns_items']) as $columnName) {
				if (!in_array($columnName, $listColumns)) {
					if (array_key_exists($columnName, $columns)) {
						$thisColumn = $columns[$columnName];
						$subType = $thisColumn->getControlValue('subtype');
						$columnText = $thisColumn->getControlValue("form_label");
						if ($this->iUseColumnNameInFilter || empty($columnText) || is_null($columnText)) {
							$columnText = ucwords(str_replace("_", " ", $thisColumn->getControlValue("column_name")));
						}
						if ($thisColumn->getControlValue("primary_key") || (!in_array($columnName, $this->iExcludeListColumns) &&
								$thisColumn->getControlValue('data_type') != "longblob" && empty($subType) && $thisColumn->getControlValue("data_type") != "custom_control" && $thisColumn->getControlValue("data_type") != "custom" && $thisColumn->getControlValue("data_type") != "button")) {
							$listColumns[] = $columnName;
						}
					}
				}
			}
			setUserPreference("MAINTENANCE_LIST_COLUMNS", implode(",", $listColumns), $GLOBALS['gPageRow']['page_code']);
		}
		$listColumns = explode(",", getPreference("MAINTENANCE_LIST_COLUMNS", $GLOBALS['gPageRow']['page_code']));
		foreach ($listColumns as $columnName) {
			if (array_key_exists($columnName, $columns)) {
				$thisColumn = $columns[$columnName];
				$subType = $thisColumn->getControlValue('subtype');
				$columnText = $thisColumn->getControlValue("form_label");
				if ($this->iUseColumnNameInFilter || empty($columnText) || is_null($columnText)) {
					$columnText = ucwords(str_replace("_", " ", $thisColumn->getControlValue("column_name")));
				}
				if ($thisColumn->getControlValue("primary_key") || (!in_array($columnName, $this->iExcludeListColumns) &&
						$thisColumn->getControlValue('data_type') != "longblob" && empty($subType) && $thisColumn->getControlValue("data_type") != "custom_control" && $thisColumn->getControlValue("data_type") != "custom" && $thisColumn->getControlValue("data_type") != "button")) {
					$listColumnArray[$columnName] = $columns[$columnName]->getControlValue('form_label');
				}
			}
		}
		$returnArray['list_columns'] = $listColumnArray;

		ajaxResponse($returnArray);
	}

	/**
	 *    function internalPageCSS
	 *
	 *    not used by this class, but needed because it is abstract in MaintenancePage
	 */
	function internalPageCSS() {
		$this->iPageObject->internalPageCSS();
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
        <iframe id="_export_frame"></iframe>
        <div id="_custom_where">
            <table>
                <tr>
                    <td class="field-label"><label for="_custom_where_entry"><?= getLanguageText("Where") ?></label></td>
                    <td><textarea id="_custom_where_entry" name="_custom_where_entry" class="field-text"></textarea></td>
                </tr>
            </table>
        </div>
        <div id="_preferences">
            <form id="_preference_form" name="_preference_form">
                <table>
                    <tr>
                        <td class="field-label"><label for="maintenance_item_count"><?= getLanguageText("Rows_per_page") ?></label></td>
                        <td><input type="text" size="4" maxlength="4" class="validate[custom[integer],min[4]]" id="maintenance_item_count" name="maintenance_item_count" value="<?= getPreference("MAINTENANCE_ITEM_COUNT", $GLOBALS['gPageRow']['page_code']) ?>"/></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td><input type="checkbox" id="maintenance_list_shaded" name="maintenance_list_shaded" value="true"<?= (getPreference("MAINTENANCE_LIST_SHADED", $GLOBALS['gPageRow']['page_code']) ? " checked" : "") ?> /><label class="checkbox-label" for="maintenance_list_shaded"><?= getLanguageText("Use_Shaded_List_Type") ?></label></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td><input type="checkbox" id="maintenance_save_no_list" name="maintenance_save_no_list" value="true"<?= (getPreference("MAINTENANCE_SAVE_NO_LIST", $GLOBALS['gPageRow']['page_code']) ? " checked" : "") ?> /><label class="checkbox-label" for="maintenance_save_no_list"><?= getLanguageText("Stay_on_record_after_save") ?></label></td>
                    </tr>
                    <tr>
                        <td></td>
                        <td><input type="checkbox" id="maintenance_show_inactive" name="maintenance_show_inactive" value="true"<?= (getPreference("MAINTENANCE_SHOW_INACTIVE", $GLOBALS['gPageRow']['page_code']) ? " checked" : "") ?> /><label class="checkbox-label" for="maintenance_show_inactive"><?= getLanguageText("Show_inactive_records") ?></label></td>
                    </tr>
                </table>
                <hr>
                <table class="align-center">
                    <tr>
                        <td>
                            <table>
                                <tr>
                                    <td colspan="2" class="align-center"><?= getLanguageText("Drag and drop to add and change order of fields") ?></td>
                                </tr>
                                <tr>
                                    <td class="field-label align-center"><?= getLanguageText("Available Fields") ?></td>
                                    <td class="field-label align-center"><?= getLanguageText("Fields to include in list") ?></td>
                                </tr>
                                <tr>
                                    <td class="align-top">
                                        <div id="_column_list_div">
                                            <ul id="_column_list" class="connected-sortable">
												<?php
												foreach ($columns as $columnName => $thisColumn) {
													$subType = $thisColumn->getControlValue('subtype');
													$columnText = $thisColumn->getControlValue("form_label");
													if ($this->iUseColumnNameInFilter || empty($columnText) || is_null($columnText)) {
														$columnText = ucwords(str_replace("_", " ", $thisColumn->getControlValue("column_name")));
													}
													if ($thisColumn->getControlValue("primary_key") || (!in_array($columnName, $this->iExcludeListColumns) &&
															$thisColumn->getControlValue('data_type') != "longblob" && empty($subType) && $thisColumn->getControlValue("data_type") != "custom_control" && $thisColumn->getControlValue("data_type") != "custom" && $thisColumn->getControlValue("data_type") != "button")) {
														?>
                                                        <li class="ui-state-default" data-column_name="<?= $columnName ?>"><?= htmlText($columnText) ?></li>
														<?php
													}
												}
												?>
                                            </ul>
                                        </div>
                                    </td>
                                    <td class="align-top">
                                        <table>
                                            <tr>
                                                <td><input type="hidden" name="_list_columns_items" id="_list_columns_items"/>
                                                    <div id="_list_columns_div">
                                                        <ul id="_list_columns" class="connected-sortable">
															<?php
															foreach ($listColumns as $columnName) {
																if (array_key_exists($columnName, $columns)) {
																	$thisColumn = $columns[$columnName];
																	$subType = $thisColumn->getControlValue('subtype');
																	$columnText = $thisColumn->getControlValue("form_label");
																	if ($this->iUseColumnNameInFilter || empty($columnText) || is_null($columnText)) {
																		$columnText = ucwords(str_replace("_", " ", $thisColumn->getControlValue("column_name")));
																	}
																	if ($thisColumn->getControlValue("primary_key") || (!in_array($columnName, $this->iExcludeListColumns) &&
																			$thisColumn->getControlValue('data_type') != "longblob" && empty($subType) && $thisColumn->getControlValue("data_type") != "custom_control" && $thisColumn->getControlValue("data_type") != "custom" && $thisColumn->getControlValue("data_type") != "button")) {
																		?>
                                                                        <li class="ui-state-default" data-column_name="<?= $columnName ?>"><?= htmlText($columnText) ?><img class='delete-item' src='images/delete.gif' alt='delete'></li>
																		<?php
																	}
																}
															}
															?>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="field-label align-center"><?= getLanguageText("Fields to export") ?></td>
                                            </tr>
                                            <tr>
                                                <td><input type="hidden" name="_export_columns_items" id="_export_columns_items"/>
                                                    <div id="_export_columns_div">
                                                        <ul id="_export_columns" class="connected-sortable">
															<?php
															foreach ($exportColumns as $columnName) {
																if (array_key_exists($columnName, $columns)) {
																	$thisColumn = $columns[$columnName];
																	$subType = $thisColumn->getControlValue('subtype');
																	$columnText = $thisColumn->getControlValue("form_label");
																	if ($this->iUseColumnNameInFilter || empty($columnText) || is_null($columnText)) {
																		$columnText = ucwords(str_replace("_", " ", $thisColumn->getControlValue("column_name")));
																	}
																	if ($thisColumn->getControlValue("primary_key") || (!in_array($columnName, $this->iExcludeListColumns) &&
																			$thisColumn->getControlValue('data_type') != "longblob" && empty($subType) && $thisColumn->getControlValue("data_type") != "custom_control" && $thisColumn->getControlValue("data_type") != "custom" && $thisColumn->getControlValue("data_type") != "button")) {
																		?>
                                                                        <li class="ui-state-default" data-column_name="<?= $columnName ?>"><?= htmlText($columns[$columnName]->getControlValue('form_label')) ?><img class='delete-item' src='images/delete.gif' alt='delete'></li>
																		<?php
																	}
																}
															}
															?>
                                                        </ul>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
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
		header("Content-Disposition: attachment;filename=export.csv");
		header("Content-Transfer-Encoding: binary");

		if ($this->iRemoveExport) {
			exit;
		}

		$this->iDataSource->addFilterWhere($this->iDataSource->getPrimaryTable()->getName() . "." . $this->iDataSource->getPrimaryTable()->getPrimaryKey() . " in (select primary_identifier from selected_rows " .
			"where user_id = " . $GLOBALS['gUserId'] . " and page_id = " . $GLOBALS['gPageId'] . ")");

		$columns = $this->iDataSource->getColumns();

		$foreignKeys = $this->iDataSource->getForeignKeyList();
		foreach ($foreignKeys as $columnName => $foreignKeyInfo) {
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

		$sortOrderColumn = getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$secondarySortOrderColumn = getPreference("MAINTENANCE_SECONDARY_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$listColumns = explode(",", getPreference("MAINTENANCE_EXPORT_COLUMNS", $GLOBALS['gPageRow']['page_code']));
		if (count($listColumns) == 0 || (count($listColumns) == 1 && empty($listColumns[0]))) {
			$listColumns = array();
			foreach ($columns as $columnName => $thisColumn) {
				if (!in_array($columnName, $this->iExcludeListColumns) && $thisColumn->getControlValue('data_type') != "longblob") {
					$listColumns[] = $columnName;
				}
			}
		}
		foreach ($listColumns as $thisIndex => $columnName) {
			if (!array_key_exists($columnName, $columns) || in_array($columnName, $this->iExcludeListColumns)) {
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
		$dataList = $this->iDataSource->getDataList();

		$columnHeaders = "";
		foreach ($listColumns as $thisIndex => $columnName) {
			if (!empty($columnHeaders)) {
				$columnHeaders .= ",";
			}
			$columnHeaders .= str_replace(" ", "", ucwords(strtolower($columns[$columnName]->getControlValue('form_label'))));
		}
		echo $columnHeaders . "\r\n";
		foreach ($dataList as $rowIndex => $columnRow) {
			$dataLine = "";
			foreach ($listColumns as $fullColumnName) {
				$columnName = (strpos($fullColumnName, ".") === false ? $fullColumnName : substr($fullColumnName, strpos($fullColumnName, ".") + 1));
				if (!empty($dataLine)) {
					$dataLine .= ",";
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
					case "int":
					case "select":
					case "autocomplete":
						if (array_key_exists($fullColumnName, $foreignKeys)) {
							$dataValue = $columnRow[$columnName . "_display"];
						} else {
							$dataValue = (strlen($columnRow[$columnName]) == 0 ? "" : ($mysqlType == "int" ? number_format($columnRow[$columnName], 0, "", "") : $columnRow[$columnName]));
						}
						break;
					case "decimal":
						$dataValue = (strlen($columnRow[$columnName]) == 0 ? "" : number_format($columnRow[$columnName], $columns[$fullColumnName]->getControlValue('decimal_places'), ".", ","));
						break;
					case "tinyint":
						$dataValue = (empty($columnRow[$columnName]) ? "" : "YES");
						break;
					default:
						$dataValue = $columnRow[$columnName];
				}
				$dataLine .= '"' . str_replace('"', '""', $dataValue) . '"';
			}
			echo $dataLine . "\r\n";
		}
		exit;
	}

	/**
	 *    function getSortList
	 *
	 *    not used by this class, but needed because it is abstract in MaintenancePage
	 */
	function getSortList() {
	}

	/**
	 *    function getRecord
	 *
	 *    not used by this class, but needed because it is abstract in MaintenancePage
	 */
	function getRecord() {
	}

	/**
	 *    function saveChanges
	 *
	 *    not used by this class, but needed because it is abstract in MaintenancePage
	 */
	function saveChanges() {
	}

	/**
	 *    function deleteRecord
	 *
	 *    not used by this class, but needed because it is abstract in MaintenancePage
	 */
	function deleteRecord() {
	}

	/**
	 *    function getSpreadsheetList
	 *
	 *    not used by this class, but needed because it is abstract in MaintenancePage
	 */
	function getSpreadsheetList() {
	}

	/**
	 *    function jQueryTemplates
	 *
	 *    not used by this class, but needed because it is abstract in MaintenancePage
	 */
	function jQueryTemplates() {
	}
}
