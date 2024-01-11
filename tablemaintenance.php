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

$GLOBALS['gPageCode'] = "TABLEMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setAddUrl("tableadd.php");
			$this->iTemplateObject->getTableEditorObject()->addIncludeColumn(array("table_id", "database_definition_id", "table_name", "description", "detailed_description", "page_code", "subsystem_id"));
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("table_id", "database_definition_id", "table_name", "description", "subsystem_id","column_count"));
			$this->iTemplateObject->getTableEditorObject()->setMaximumListColumns(6);
			$filters = array();
			$versionColumnDefinitionId = getFieldFromId("column_definition_id", "column_definitions", "column_name", "version");
			$filters['issues'] = array("form_label" => "Tables with problems", "where" => "table_id in (select table_id from " .
				"(select a.table_id,column_definition_id from table_columns a inner join (select table_id, max(sequence_number) sequence_number from table_columns group by table_id) b on " .
				"a.table_id = b.table_id and a.sequence_number = b.sequence_number) check_tables where check_tables.column_definition_id <> " . $versionColumnDefinitionId .
				" order by check_tables.table_id) or table_id in (select table_id from tables where table_view = 0 and (select count(*) from table_columns where table_id = tables.table_id) <= 1) or " .
				"table_id in (select table_id from tables where (select count(*) from table_columns where table_id = tables.table_id) = 5 and " .
				"table_id not in (select table_id from unique_keys) and (select count(*) from foreign_keys where table_column_id in (select table_column_id from table_columns where " .
				"table_id = tables.table_id)) = 2 and table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'sequence_number'))) or " .
				"table_id in (select table_id from tables where (select count(*) from table_columns where table_id = tables.table_id) = 4 and table_id not in (select table_id from unique_keys) and (select count(*) from foreign_keys where " .
				"table_column_id in (select table_column_id from table_columns where table_id = tables.table_id)) = 2) or " .
				"table_id in (select tables.table_id from table_columns join tables using (table_id) join column_definitions using (column_definition_id) where column_name like '%_code' and " .
				"table_column_id not in (select table_column_id from unique_key_columns) and code_value = 1)",
				"data_type" => "tinyint", "conjunction" => "and");

			$filters['no_page'] = array("form_label" => "Control Table without Page", "where" => "table_name not in (select text_data from page_data where template_data_id = (select template_data_id from template_data where data_name = 'primary_table_name')) and " .
				"table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'description')) and " .
				"table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'sort_order')) and " .
				"table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'internal_use_only')) and " .
				"table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'inactive')) and " .
				"table_id not in (select table_id from table_columns where column_definition_id not in (select column_definition_id from column_definitions where column_type in ('date','int','varchar','decimal','tinyint','text','mediumtext')))");

			$resultSet = executeQuery("select * from subsystems order by sort_order,description");
			while ($row = getNextRow($resultSet)) {
				$filters['subsystem_' . $row['subsystem_id']] = array("form_label" => $row['description'], "where" => "subsystem_id = " . $row['subsystem_id'], "data_type" => "tinyint", "conjunction" => "and");
			}
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function massageDataSource() {
		$this->iDataSource->setFilterWhere("table_view = 0");
		$this->iDataSource->addColumnControl("column_count","data_type","int");
		$this->iDataSource->addColumnControl("column_count","select_value","select count(*) from table_columns where table_id = tables.table_id");
		$this->iDataSource->addColumnControl("column_count","form_label","Columns");
	}

	function internalCSS() {
		?>
        <style>
            .section {
                margin: 20px 0;
                position: relative;
            }

            #create_unique_key {
                margin-left: 40px;
            }

            #create_foreign_keys {
                margin-left: 40px;
            }

            #columns {
                width: 90%;
            }

            #columns th {
                font-size: 10px;
                font-weight: bold;
                vertical-align: middle;
            }

            #columns .delete-row {
                display: none;
            }

            #columns .drag-strip {
                display: none;
            }

            #columns .select-box {
                display: none;
            }

            .delete-row {
                margin: 4px;
            }

            #columns textarea {
                height: 22px;
                width: 100px;
            }

            #columns textarea:focus {
                height: 50px;
                width: 150px;
            }

            .grid-table td {
                vertical-align: middle;
            }

            .default-value {
                width: 50px;
            }

            #unique_keys {
                width: 800px;
                max-width: 90%;
            }

            .column-row:hover {
                background-color: rgb(250, 250, 200);
            }

            .sortable-row {
                cursor: pointer;
            }

            #data_content {
                overflow: scroll;
                height: 600px;
            }
        </style>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#view_data").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_data&table_name=" + $("#table_name").val() + "&database_definition_id=" + $("#database_definition_id").val(), function(returnArray) {
                    if ("table_data" in returnArray) {
                        $("#data_content").html(returnArray['table_data']);
                        $('#_data_dialog').dialog({
                            closeOnEscape: true,
                            draggable: true,
                            modal: true,
                            resizable: true,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 1000,
                            title: 'Data Sample',
                            buttons: {
                                Cancel: function (event) {
                                    $("#_data_dialog").dialog('close');
                                }
                            }
                        });
                    }
                });
                return false;
            });
            $("#columns").sortable({
                update: function () {
                    manualChangesMade = true;
                    changeSortOrder();
                },
                items: ".sortable-row"
            });
            $(document).on("tap click", "#create_unique_key", function () {
                var fields = "";
                $("input[id^=select_]:checked").each(function () {
                    if (fields != "") {
                        fields += ",";
                    }
                    var rowNumber = $(this).closest("tr").data("row_number");
                    fields += $("#column_name_" + rowNumber).val();
                    $(this).prop("checked", false);
                });
                if (!empty(fields)) {
                    manualChangesMade = true;
                    rowNumber = createUniqueKeyRow();
                    $("#uk_fields_" + rowNumber).val(fields);
                }
                return false;
            });
            $(document).on("tap click", ".delete-row", function () {
                manualChangesMade = true;
                var columnName = $(this).closest("tr").find(".column-name").val();
                $(this).closest("tr").remove();
                if (columnName != "" && columnName != undefined) {
                    $(".unique-key-fields").each(function () {
                        if (isInArray(columnName, $(this).val().split(","), true)) {
                            $(this).closest("tr").remove();
                        }
                    });
                    $(".fk-column-name").each(function () {
                        if ($(this).val() == columnName) {
                            $(this).closest("tr").remove();
                        }
                    });
                }
            });
            $(document).on("change", "#_main_content .manual-change", function () {
                manualChangesMade = true;
            });
            $(document).on("tap click", "#_main_content .manual-click", function () {
                manualChangesMade = true;
            });
            $(document).on("change", ".fk-referenced-table-id", function () {
                var rowNumber = $(this).closest("tr").data("row_number");
                setReferencedColumns(rowNumber);
            });
            $(document).on("tap click", "#create_foreign_keys", function () {
                $("#foreign_keys .foreign-key-row").remove();
                $("#foreign_keys").data("row_number", "0");
                manualChangesMade = true;

                $(".column-name").each(function () {
                    if ($(this).val() != "" && $(this).val().substr($(this).val().length - 3) == "_id" && $(this).attr("id").substr($(this).attr("id").length - 2) != "_1") {
                        var rowNumber = createForeignKeyRow();
                        var columnName = $(this).val();
                        $("#fk_column_name_display_" + rowNumber).html(columnName);
                        $("#fk_column_name_" + rowNumber).val(columnName);
                        $("#fk_column_name_" + rowNumber).closest("tr").find(".delete-row").show();
                        var foundOne = false;
                        var tableName = columnName.substr(0, columnName.length - 3) + "s";
                        $("#fk_referenced_table_id_" + rowNumber + " option").each(function () {
                            if ($(this).text() == tableName) {
                                $("#fk_referenced_table_id_" + rowNumber).val($(this).val());
                                foundOne = true;
                                return false;
                            }
                        });
                        if (!foundOne) {
                            tableName = columnName.substr(0, columnName.length - 3);
                            $("#fk_referenced_table_id_" + rowNumber + " option").each(function () {
                                if ($(this).text() == tableName) {
                                    $("#fk_referenced_table_id_" + rowNumber).val($(this).val());
                                    foundOne = true;
                                    return false;
                                }
                            });
                        }
                        if (!foundOne) {
                            tableName = columnName.substr(0, columnName.length - 3) + "es";
                            $("#fk_referenced_table_id_" + rowNumber + " option").each(function () {
                                if ($(this).text() == tableName) {
                                    $("#fk_referenced_table_id_" + rowNumber).val($(this).val());
                                    foundOne = true;
                                    return false;
                                }
                            });
                        }
                        if (!foundOne) {
                            tableName = columnName.substr(0, columnName.length - 4) + "ies";
                            $("#fk_referenced_table_id_" + rowNumber + " option").each(function () {
                                if ($(this).text() == tableName) {
                                    $("#fk_referenced_table_id_" + rowNumber).val($(this).val());
                                    foundOne = true;
                                    return false;
                                }
                            });
                        }
                        if (!foundOne) {
                            var offset = columnName.indexOf("_");
                            if (offset >= 0) {
                                tableName = columnName.substr(offset + 1, columnName.length - 4 - offset) + "s";
                                $("#fk_referenced_table_id_" + rowNumber + " option").each(function () {
                                    if ($(this).text() == tableName) {
                                        $("#fk_referenced_table_id_" + rowNumber).val($(this).val());
                                        foundOne = true;
                                        return false;
                                    }
                                });
                            }
                        }
                        if (!foundOne) {
                            var offset = columnName.indexOf("_");
                            if (offset >= 0) {
                                tableName = columnName.substr(offset + 1, columnName.length - 4 - offset) + "es";
                                $("#fk_referenced_table_id_" + rowNumber + " option").each(function () {
                                    if ($(this).text() == tableName) {
                                        $("#fk_referenced_table_id_" + rowNumber).val($(this).val());
                                        foundOne = true;
                                        return false;
                                    }
                                });
                            }
                        }
                        if (foundOne) {
                            $("#fk_referenced_table_id_" + rowNumber).trigger("change");
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
            var databaseDefinitionId = "";

            function changeSortOrder() {
                var sequenceNumber = 100;
                $("#columns").find("input.sequence-number").each(function () {
                    $(this).val(sequenceNumber);
                    sequenceNumber += 100;
                });
            }

            function columnName(fieldName) {
                if ($("#" + fieldName).length == 0) {
                    return;
                }
                var field = $("#" + fieldName);
                var newRowNumber = "";
                var rowNumber = $(field).closest("tr").data("row_number");
                if (rowNumber == 1) {
                    return;
                }
                if ($(field).val() != "" && !$(field).closest("tr").find(".delete-row").is(":visible")) {
                    if ($("#_permission").val() != "1") {
                        $(field).closest("tr").find(".delete-row").show();
                        $(field).closest("tr").find(".drag-strip").show();
                        $(field).closest("tr").find(".select-box").show();
                        $(field).closest("tr").addClass("sortable-row");
                        newRowNumber = createColumnRow();
                    }
                }
                $("#column_type_" + rowNumber).prop("disabled", false);
                $("#data_size_" + rowNumber).prop("readonly", false);
                $("#decimal_places_" + rowNumber).prop("readonly", false);
                if ($(field).val() != "") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_column_info&column_name=" + $(field).val() + "&row_number=" + rowNumber, function(returnArray) {
                        if ("error_message" in returnArray) {
                            displayErrorMessage(returnArray['error_message']);
                        } else {
                            var rowNumber = returnArray['row_number'];
                            $("#description_" + rowNumber).val(returnArray['description']);
                            if ("column_definition_id" in returnArray) {
                                $("#column_definition_id_" + rowNumber).val(returnArray['column_definition_id']);
                                $("#column_type_" + rowNumber).val(returnArray['column_type']).prop("disabled", true);
                                $("#data_size_" + rowNumber).val(returnArray['data_size']).prop("readonly", true);
                                $("#decimal_places_" + rowNumber).val(returnArray['decimal_places']).prop("readonly", true);
                                if (returnArray['not_null'] == 1) {
                                    $("#not_null_" + rowNumber).prop("checked", true);
                                }
                                $("#default_value_" + rowNumber).val(returnArray['default_value']);
                                if (newRowNumber != "") {
                                    setTimeout(function () {
                                        $("#column_name_" + newRowNumber).focus();
                                    }, 100);
                                }
                            } else {
                                $("#column_definition_id_" + rowNumber).val("");
                            }
                        }
                    });
                } else {
                    $("#column_definition_id_" + rowNumber).val("");
                }
            }

            function additionalValidation() {
                var sequenceNumber = 100;
                $("#columns").find("input.sequence-number").each(function () {
                    $(this).val(sequenceNumber);
                    sequenceNumber += 100;
                });
                $(".fk-column-name").each(function () {
                    var columnName = $(this).find("option:selected").text();
                    if ($(this).val() != "" && columnName != "") {
                        $(".column-name").filter(function () {
                            return this.value == columnName;
                        }).closest("tr").find(".indexed").prop("checked", true);
                    }
                });
                return true;
            }

            function beforeGetRecord(returnArray) {
                if (databaseDefinitionId == "" || databaseDefinitionId != $("#database_definition_id").val()) {
                    $("#primary_id").val(returnArray['primary_id']['data_value']);
                    getTables();
                    return true;
                }
                return true;
            }

            function afterGetRecord() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_table_details&table_id=" + $("#primary_id").val(), function(returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    }
                    $("#columns tr.column-row").remove();
                    $("#columns").data("row_number", "0");
                    $("#unique_keys tr.unique-key-row").remove();
                    $("#unique_keys").data("row_number", "0");
                    $("#foreign_keys tr.foreign-key-row").remove();
                    $("#foreign_keys").data("row_number", "0");
                    for (var i in returnArray['column_array']) {
                        createColumnRow(returnArray['column_array'][i]);
                    }
                    createColumnRow();
                    for (var i in returnArray['unique_key_array']) {
                        createUniqueKeyRow(returnArray['unique_key_array'][i]);
                    }
                    for (var i in returnArray['foreign_key_array']) {
                        createForeignKeyRow(returnArray['foreign_key_array'][i]);
                    }
                });
                if ($("#_permission").val() == "1") {
                    $("#create_unique_key").hide();
                    $("#create_foreign_keys").hide();
                    $(".drag-strip").hide();
                } else {
                    $("#create_unique_key").show();
                    $("#create_foreign_keys").show();
                    $(".drag-strip").show();
                }
            }

            function getTables() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_tables&table_id=" + $("#primary_id").val(), function(returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    }
                    $("#fk_referenced_table_id_zzz").find("option").remove();
                    $("#fk_referenced_table_id_zzz").append('<option value="" data-id="" data-nm="[Select]">[Select]</option>');
                    $("#fk_referenced_table_id_zzz").append(returnArray['tables']);
                    databaseDefinitionId = $("#database_definition_id").val();
                    getRecord($("#primary_id").val());
                });
            }

            function setDescription() {
                if ($("#description").val() == "") {
                    var str = $("#table_name").val().toLowerCase().replace(/_/g, " ").replace(/\b[a-z]/g, function (letter) {
                        return letter.toUpperCase();
                    });
                    $("#description").val(str);
                }
            }

            function createColumnRow(dataArray) {
                var showIcons = true;
                if (dataArray == null) {
                    if ($("#_permission").val() == "1") {
                        return;
                    }
                    dataArray = new Object();
                    dataArray['column_definition_id'] = dataArray['column_name'] = dataArray['description'] = dataArray['column_type'] = "";
                    dataArray['data_size'] = dataArray['decimal_places'] = dataArray['primary_table_key'] = dataArray['indexed'] = dataArray['full_text'] = "";
                    dataArray['not_null'] = dataArray['default_value'] = dataArray['table_column_id'] = "";
                    showIcons = false;
                }
                var rowData = $("#column_row").html();
                var rowNumber = $("#columns").data("row_number") - 0 + 1;
                $("#columns").data("row_number", rowNumber);
                rowData = rowData.replace(/zzz/g, rowNumber);
                $("#columns").append(rowData);
                $("#column_definition_id_" + rowNumber).val(dataArray['column_definition_id']);
                $("#sequence_number_" + rowNumber).val(dataArray['sequence_number']);
                $("#column_name_" + rowNumber).val(dataArray['column_name']);
                $("#description_" + rowNumber).val(dataArray['description']);
                $("#detailed_description_" + rowNumber).val(dataArray['detailed_description']);
                $("#column_type_" + rowNumber).val(dataArray['column_type']);
                $("#data_size_" + rowNumber).val(dataArray['data_size']);
                $("#decimal_places_" + rowNumber).val(dataArray['decimal_places']);
                $("#primary_table_key_" + rowNumber).val(dataArray['primary_table_key']);
                $("#indexed_" + rowNumber).prop("checked", dataArray['indexed'] == 1);
                $("#full_text_" + rowNumber).prop("checked", dataArray['full_text'] == 1);
                $("#not_null_" + rowNumber).prop("checked", dataArray['not_null'] == 1);
                $("#default_value_" + rowNumber).val(dataArray['default_value']);
                $("#table_column_id_" + rowNumber).val(dataArray['table_column_id']);
                if (showIcons) {
                    if ($("#_permission").val() != "1") {
                        $("#column_row_" + rowNumber).find(".drag-strip").show();
                        $("#column_row_" + rowNumber).find(".select-box").show();
                        $("#column_row_" + rowNumber).addClass("sortable-row");
                        $("#delete_column_" + rowNumber).show();
                    } else {
                        $("#column_name_" + rowNumber).prop("readonly", true);
                        $("#description_" + rowNumber).prop("readonly", true);
                        $("#detailed_description_" + rowNumber).prop("readonly", true);
                        $("#default_value_" + rowNumber).prop("readonly", true);
                    }
                    $("#column_type_" + rowNumber).prop("disabled", true);
                    $("#data_size_" + rowNumber).prop("readonly", true);
                    $("#decimal_places_" + rowNumber).prop("readonly", true);
                }
                if (rowNumber == 1) {
                    $("#column_row_" + rowNumber).find(".drag-strip").hide();
                    $("#column_row_" + rowNumber).find(".select-box").hide();
                    $("#column_row_" + rowNumber).removeClass("sortable-row");
                    $("#column_name_" + rowNumber).prop("readonly", true);
                    $("#description_" + rowNumber).prop("readonly", true).hide();
                    $("#detailed_description_" + rowNumber).prop("readonly", true).hide();
                    $("#data_size_" + rowNumber).prop("readonly", true).hide();
                    $("#decimal_places_" + rowNumber).prop("readonly", true).hide();
                    $("#indexed_" + rowNumber).prop("disabled", true);
                    $("#full_text_" + rowNumber).prop("disabled", true);
                    $("#not_null_" + rowNumber).prop("disabled", true);
                    $("#default_value_" + rowNumber).prop("readonly", true).hide();
                    $("#delete_column_" + rowNumber).hide();
                }
                return rowNumber;
            }

            function createUniqueKeyRow(dataArray) {
                if (dataArray == null) {
                    dataArray = new Object();
                    dataArray['unique_key_id'] = dataArray['fields'] = "";
                }
                var rowData = $("#unique_key_row").html();
                var rowNumber = $("#unique_keys").data("row_number") - 0 + 1;
                $("#unique_keys").data("row_number", rowNumber);
                rowData = rowData.replace(/zzz/g, rowNumber);
                $("#unique_keys").append(rowData);
                $("#unique_key_id_" + rowNumber).val(dataArray['unique_key_id']);
                $("#uk_fields_" + rowNumber).val(dataArray['fields']);
                return rowNumber;
            }

            function createForeignKeyRow(dataArray) {
                var showIcons = true;
                if (dataArray == null) {
                    dataArray = new Object();
                    dataArray['foreign_key_id'] = dataArray['table_column_id'] = dataArray['column_definition_id'] = dataArray['column_name'] = "";
                    dataArray['referenced_table_id'] = dataArray['referenced_column_definition_id'] = dataArray['referenced_column_name'] = "";
                    showIcons = false;
                }
                var rowData = $("#foreign_key_row").html();
                var rowNumber = $("#foreign_keys").data("row_number") - 0 + 1;
                $("#foreign_keys").data("row_number", rowNumber);
                rowData = rowData.replace(/zzz/g, rowNumber);
                $("#foreign_keys").append(rowData);
                $("#foreign_key_id_" + rowNumber).val(dataArray['foreign_key_id']);
                $("#fk_column_definition_id_" + rowNumber).val(dataArray['column_definition_id']);
                $("#fk_column_name_display_" + rowNumber).html(dataArray['column_name']);
                $("#fk_column_name_" + rowNumber).val(dataArray['column_name']);
                $("#fk_referenced_table_id_" + rowNumber).val(dataArray['referenced_table_id']);
                $("#fk_referenced_column_definition_id_" + rowNumber).find("option").remove();
                if (dataArray['referenced_column_name'] == "") {
                    $("#fk_referenced_column_definition_id_" + rowNumber).append('<option value="">[Select]</option>');
                } else {
                    $("#fk_referenced_column_definition_id_" + rowNumber).append('<option value="' + dataArray['referenced_column_definition_id'] + '">' + dataArray['referenced_column_name'] + '</option>');
                }
                if (showIcons) {
                    $("#delete_foreign_key_" + rowNumber).show();
                }
                if ($("#_permission").val() == "1") {
                    $("#fk_referenced_table_id_" + rowNumber).prop("disabled", true);
                    $("#fk_referenced_column_definition_id_" + rowNumber).prop("disabled", true);
                    $("#delete_foreign_key_" + rowNumber).hide();
                }
                return rowNumber;
            }

            function setReferencedColumns(rowNumber) {
                $("#fk_referenced_column_definition_id_" + rowNumber).find("option").remove();
                var tableField = $("#fk_referenced_table_id_" + rowNumber + " option:selected");
                $("#fk_referenced_column_definition_id_" + rowNumber).append('<option value="' + tableField.data('id') + '">' + tableField.data('nm') + '</option>');
            }
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$pageId = getFieldFromId("page_id", "page_data", "text_data", $returnArray['table_name']['data_value'], "template_data_id = (select template_data_id from template_data where data_name = 'primary_table_name')");
		if (!$GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gDefaultClientId'] != $GLOBALS['gClientId'] || !empty($pageId) || !$GLOBALS['gPrimaryDatabase']->isControlTable($returnArray['table_name']['data_value'], true)) {
			$returnArray['create_page'] = array("data_value" => ($GLOBALS['gUserRow']['superuser_flag'] && $GLOBALS['gDefaultClientId'] == $GLOBALS['gClientId'] && $GLOBALS['gPrimaryDatabase']->isControlTable($returnArray['table_name']['data_value']) ? "<p>Page already exists</p>" : ""));
		} else {
			$returnArray['create_page'] = array("data_value" => "<div class='basic-form-line'><label>Create Page With Access</label>" .
				"<select id='create_page' name='create_page'><option value=''>[None]</option><option value='superuser'>Superuser Only</option><option value='admin'>Admin on All Clients</option></select>" .
				"</div>");
		}
		if (!$GLOBALS['gUserRow']['superuser_flag']) {
			$restrictedAccess = getFieldFromId("restricted_access", "subsystems", "subsystem_id", $returnArray['subsystem_id']['data_value']);
			if (!empty($restrictedAccess)) {
				$userId = getFieldFromId("user_id", "subsystem_users", "subsystem_id", $returnArray['subsystem_id']['data_value'], "user_id = ?", $GLOBALS['gUserId']);
				if (empty($userId)) {
					$returnArray['_permission'] = array("data_value" => _READONLY);
				}
			}
		}
	}

	function jqueryTemplates() {
		$columnTypeArray = array("bigint", "date", "datetime", "decimal", "int", "longblob", "mediumblob", "mediumtext", "point", "text", "tinyint", "varchar");
		?>
        <table>
            <tbody id="column_row">
            <tr class="column-row" id="column_row_zzz" data-row_number="zzz">
                <td class="align-center"><img src="/images/drag_strip.png" class="drag-strip" alt="Drag Strip"/></td>
                <td class="align-center"><input type="hidden" value="1" name="primary_table_key_zzz" id="primary_table_key_zzz"/><input type="hidden" size="4" class="sequence-number" name="sequence_number_zzz" id="sequence_number_zzz"/><input type="checkbox" class="select-box" value="1" name="select_zzz" id="select_zzz"/></td>
                <td><input tabindex="10" type="hidden" name="column_definition_id_zzz" id="column_definition_id_zzz"/><input tabindex="10" type="text" size="25" maxlength="40" value="" name="column_name_zzz" id="column_name_zzz" class="validate[] column-name code-value lowercase field-text size-10-point manual-change" data-success-function="columnName"/></td>
                <td><input tabindex="10" type="text" size="25" maxlength="255" name="description_zzz" id="description_zzz" class="field-text size-10-point manual-change"/></td>
                <td><textarea tabindex="10" name="detailed_description_zzz" id="detailed_description_zzz" class="field-text size-10-point manual-change"></textarea></td>
                <td><select tabindex="10" name="column_type_zzz" id="column_type_zzz" class="field-text size-10-point manual-change">
                        <option value="">[Select]</option>
						<?php
						foreach ($columnTypeArray as $columnType) {
							?>
                            <option value="<?= $columnType ?>"><?= $columnType ?></option>
							<?php
						}
						?>
                    </select></td>
                <td><input class="field-text size-10-point manual-change validate[custom[integer]] align-right" tabindex="10" type="text" size="3" maxlength="8" name="data_size_zzz" id="data_size_zzz"/></td>
                <td><input class="field-text size-10-point manual-change validate[custom[integer]] align-right" tabindex="10" type="text" size="3" maxlength="8" name="decimal_places_zzz" id="decimal_places_zzz"/></td>
                <td class="align-center"><input class="field-text manual-click indexed" tabindex="10" type="checkbox" value="1" name="indexed_zzz" id="indexed_zzz"/><input tabindex="10" type="hidden" name="table_column_id_zzz" id="table_column_id_zzz"/></td>
                <td class="align-center"><input class="field-text manual-click" tabindex="10" type="checkbox" value="1" name="full_text_zzz" id="full_text_zzz"/></td>
                <td class="align-center"><input class="field-text manual-click not-null" tabindex="10" type="checkbox" value="1" name="not_null_zzz" id="not_null_zzz"/></td>
                <td><input class="field-text size-10-point manual-change default-value" tabindex="10" type="text" name="default_value_zzz" id="default_value_zzz"/></td>
                <td class="align-center"><img alt="Delete Row" src="images/delete.gif" class="delete-row" id="delete_column_zzz"/></td>
            </tr>
            </tbody>
        </table>
        <table>
            <tbody id="unique_key_row">
            <tr class="unique-key-row" data-row_number="zzz">
                <td class="field-text"><input type="text" readonly class="field-text unique-key-fields" size="100" name="uk_fields_zzz" id="uk_fields_zzz"/></td>
                <td class="align-center"><input tabindex="10" type="hidden" name="unique_key_id_zzz" id="unique_key_id_zzz"/><img alt="Delete Row" class="delete-row" src="images/delete.gif" id="delete_unique_key_zzz"/></td>
            </tr>
            </tbody>
        </table>
        <table>
            <tbody id="foreign_key_row">
            <tr class="foreign-key-row" data-row_number="zzz">
                <td class="field-text" id="fk_column_name_display_zzz"></td>
                <td><input type="hidden" name="fk_column_name_zzz" id="fk_column_name_zzz"/><input type="hidden" name="foreign_key_id_zzz" id="foreign_key_id_zzz"/><input type="hidden" name="fk_column_definition_id_zzz" id="fk_column_definition_id_zzz"/><select tabindex="10" class="fk-referenced-table-id field-text manual-change" name="fk_referenced_table_id_zzz" id="fk_referenced_table_id_zzz">
                        <option value='' data-id='' data-nm='[Select]'>[Select]</option>
                    </select></td>
                <td><select tabindex="10" class="fk-referenced-column-definition-id field-text manual-change" name="fk_referenced_column_definition_id_zzz" id="fk_referenced_column_definition_id_zzz">
                        <option value=''>[Select]</option>
                    </select></td>
                <td class="align-center"><img alt="Delete Row" src="images/delete.gif" class="delete-row" id="delete_foreign_key_zzz"/></td>
            </tr>
            </tbody>
        </table>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_data":
				$tableId = getFieldFromId("table_id", "tables", "table_name", $_GET['table_name'], "database_definition_id = ?", $_GET['database_definition_id']);
				if (empty($tableId)) {
					$returnArray['error_message'] = "Invalid Table: " . $_GET['table_name'];
				} else {
					ob_start();
					$rowCount = 0;
					$resultSet = executeQuery("select count(*) from " . $_GET['table_name']);
					if ($row = getNextRow($resultSet)) {
						$rowCount = $row['count(*)'];
					}
					?>
                    <p><?= $rowCount ?> row<?= ($rowCount == 1 ? "" : "s") ?> found in table.</p>
                    <table class="grid-table">
						<?php
						$columnDefinitions = array();
						$resultSet = executeQuery("select * from column_definitions join table_columns using (column_definition_id) where table_id = ? order by sequence_number", $tableId);
						while ($row = getNextRow($resultSet)) {
							$columnDefinitions[] = $row;
						}
						?>
                        <tr>
							<?php
							foreach ($columnDefinitions as $row) {
								?>
                                <th><?= $row['description'] ?></th>
								<?php
							}
							?>
                        </tr>
						<?php
						$resultSet = executeQuery("select * from " . $_GET['table_name'] . " limit 50");
						while ($row = getNextRow($resultSet)) {
							?>
                            <tr>
								<?php
								foreach ($columnDefinitions as $columnRow) {
									$classNames = "";
									switch ($columnRow['column_type']) {
										case "longblob":
										case "mediumblob":
										case "point":
											$displayValue = "Raw Data";
											break;
										case "date":
											$displayValue = (empty($row[$columnRow['column_name']]) ? "" : date("m/d/Y", strtotime($row[$columnRow['column_name']])));
											break;
										case "datetime":
										case "timestamp":
											$displayValue = (empty($row[$columnRow['column_name']]) ? "" : date("m/d/Y g:i:sa", strtotime($row[$columnRow['column_name']])));
											break;
										case "bigint":
										case "int":
										case "decimal":
											$classNames = "align-right";
											$displayValue = $row[$columnRow['column_name']];
											break;
										case "tinyint":
											$classNames = "align-center";
											$displayValue = (empty($row[$columnRow['column_name']]) ? "no" : "YES");
											break;
										default:
											$displayValue = htmlText(getFirstPart($row[$columnRow['column_name']], 40));
											break;
									}
									?>
                                    <td class="<?= $classNames ?>"><?= $displayValue ?></td>
									<?php
								}
								?>
                            </tr>
							<?php
						}
						?>
                    </table>
					<?php
					$returnArray['table_data'] = ob_get_clean();
				}
				ajaxResponse($returnArray);
				break;
			case ("get_table_details"):
				$columnArray = array();
				$resultSet = executeQuery("select * from column_definitions,table_columns where table_id = ? and " .
					"column_definitions.column_definition_id = table_columns.column_definition_id order by sequence_number,table_column_id", $_GET['table_id']);
				$rowNumber = 0;
				while ($row = getNextRow($resultSet)) {
					$rowNumber++;
					$columnArray[$rowNumber] = array("column_definition_id" => $row['column_definition_id'], "column_name" => $row['column_name'], "description" => $row['description'],
						"detailed_description" => $row['detailed_description'], "column_type" => $row['column_type'], "data_size" => $row['data_size'], "decimal_places" => $row['decimal_places'],
						"primary_table_key" => $row['primary_table_key'], "indexed" => $row['indexed'], "full_text" => $row['full_text'], "not_null" => $row['not_null'], "sequence_number" => $row['sequence_number'],
						"default_value" => $row['default_value'], "table_column_id" => $row['table_column_id']);
				}
				$returnArray['column_array'] = $columnArray;

				$uniqueKeyArray = array();
				$resultSet = executeQuery("select * from unique_keys where table_id = ?", $_GET['table_id']);
				$rowNumber = 0;
				while ($row = getNextRow($resultSet)) {
					$rowNumber++;
					$fields = "";
					$resultSet1 = executeQuery("select * from unique_key_columns,table_columns,column_definitions where " .
						"unique_key_columns.unique_key_id = " . $row['unique_key_id'] . " and unique_key_columns.table_column_id = table_columns.table_column_id " .
						"and table_columns.column_definition_id = column_definitions.column_definition_id");
					while ($row1 = getNextRow($resultSet1)) {
						if (!empty($fields)) {
							$fields .= ",";
						}
						$fields .= $row1['column_name'];
					}
					$uniqueKeyArray[$rowNumber] = array("unique_key_id" => $row['unique_key_id'], "fields" => $fields);
				}
				$returnArray['unique_key_array'] = $uniqueKeyArray;

				$foreignKeyArray = array();
				$resultSet = executeQuery("select * from foreign_keys where table_column_id in (select table_column_id from table_columns where table_id = ?)", $_GET['table_id']);
				$rowNumber = 0;

				while ($row = getNextRow($resultSet)) {
					$rowNumber++;
					$foreignKeyArray[$rowNumber] = array("foreign_key_id" => $row['foreign_key_id'], "table_column_id" => $row['table_column_id'],
						"column_definition_id" => getFieldFromId("column_definition_id", "table_columns", "table_column_id", $row['table_column_id']),
						"column_name" => getFieldFromId("column_name", "column_definitions", "column_definition_id", getFieldFromId("column_definition_id", "table_columns", "table_column_id", $row['table_column_id'])),
						"referenced_table_id" => getFieldFromId("table_id", "table_columns", "table_column_id", $row['referenced_table_column_id']),
						"referenced_column_definition_id" => getFieldFromId("column_definition_id", "table_columns", "table_column_id", $row['referenced_table_column_id']),
						"referenced_column_name" => getFieldFromId("column_name", "column_definitions", "column_definition_id", getFieldFromId("column_definition_id", "table_columns", "table_column_id", $row['referenced_table_column_id'])));
				}
				$returnArray['foreign_key_array'] = $foreignKeyArray;
				ajaxResponse($returnArray);
				break;

			case ("get_tables"):
				$returnArray = array();
				$resultSet = executeQuery("select * from tables where database_definition_id = (select database_definition_id from tables where table_id = ?) order by table_name", $_GET['table_id']);
				$tableNameArray = array();
				while ($row = getNextRow($resultSet)) {
					$resultSet1 = executeQuery("select * from table_columns,column_definitions where " .
						"table_columns.column_definition_id = column_definitions.column_definition_id and table_id = ? and primary_table_key = 1 order by sequence_number limit 1", $row['table_id']);
					$row1 = getNextRow($resultSet1);
					$tableNameArray[] = array("table_id" => $row['table_id'], "table_name" => $row['table_name'], "column_definition_id" => $row1['column_definition_id'], "column_name" => $row1['column_name']);
				}
				$returnArray['tables'] = "";
				foreach ($tableNameArray as $tableInfo) {
					$returnArray['tables'] .= '<option value="' . $tableInfo['table_id'] .
						'" data-id="' . $tableInfo['column_definition_id'] . '" data-nm="' .
						$tableInfo['column_name'] . '">' . $tableInfo['table_name'] . '</option>';
				}
				ajaxResponse($returnArray);
				break;

			case ("get_column_info"):
				$returnArray = array();
				$resultSet = executeQuery("select * from column_definitions where column_name = ?", $_GET['column_name']);
				if ($row = getNextRow($resultSet)) {
					$description = ucwords(str_replace("_", " ", $row['column_name']));
					if (substr($description, -3) == " Id") {
						$description = substr($description, 0, -3);
					}
					$returnArray = array("column_definition_id" => $row['column_definition_id'],
						"description" => $description, "column_type" => $row['column_type'],
						"data_size" => $row['data_size'], "decimal_places" => $row['decimal_places'], "not_null" => $row['not_null'],
						"default_value" => $row['default_value']);
				} else {
					$description = ucwords(str_replace("_", " ", $_GET['column_name']));
					if (substr($description, -3) == " Id") {
						$description = substr($description, 0, -3);
					}
					$returnArray = array("description" => $description);
				}
				$returnArray['row_number'] = $_GET['row_number'];
				ajaxResponse($returnArray);
				break;
		}
	}

	function saveChanges() {
		$returnArray = array();
		if (!empty($_POST['primary_id'])) {
			$tableId = $_POST['primary_id'];

			$this->iDatabase->startTransaction();
			$resultSet = executeQuery("select * from tables where table_id = ?", $tableId);
			if ($resultSet['row_count'] == 0) {
				$returnArray['error_message'] = getSystemMessage("not_found");
				ajaxResponse($returnArray);
			}
			$row = getNextRow($resultSet);

			$recordChanged = false;
			$recordChanged = $this->iDatabase->createChangeLog('tables', 'Table Name', $tableId, $row['table_name'], $_POST['table_name']) || $recordChanged;
			$recordChanged = $this->iDatabase->createChangeLog('tables', 'Description', $tableId, $row['description'], $_POST['description']) || $recordChanged;
			$recordChanged = $this->iDatabase->createChangeLog('tables', 'Detailed Description', $tableId, $row['detailed_description'], $_POST['detailed_description']) || $recordChanged;
			$recordChanged = $this->iDatabase->createChangeLog('tables', 'Subsystem', $tableId, $row['subsystem_id'], $_POST['subsystem_id']) || $recordChanged;
			$recordChanged = $this->iDatabase->createChangeLog('tables', 'Page Code', $tableId, $row['page_code'], $_POST['page_code']) || $recordChanged;
			if ($recordChanged) {
				$resultSet = executeQuery("update tables set table_name = ?,description = ?,detailed_description = ?,page_code = ?,subsystem_id = ?,version = version + 1 where table_id = ? and version = ?",
					$_POST['table_name'], $_POST['description'], $_POST['detailed_description'], $_POST['page_code'], $_POST['subsystem_id'], $tableId, $_POST['version']);
				if ($resultSet['affected_rows'] == 0) {
					$returnArray['error_message'] = getSystemMessage("record_changed");
					echo jsonEncode($returnArray);
					$this->iDatabase->rollbackTransaction();
					exit;
				}
			}

			$foreignKeys = array();
			$columnNames = array();
			$resultSet = executeQuery("select * from foreign_keys where referenced_table_column_id in " .
				"(select table_column_id from table_columns where table_id = ?) and " .
				"table_column_id not in (select table_column_id from table_columns where table_id = ?)", $tableId, $tableId);
			while ($row = getNextRow($resultSet)) {
				$foreignKeys[] = array("table_column_id" => $row['table_column_id'], "referenced_column_name" => getFieldFromId("column_name", "column_definitions", "column_definition_id", getFieldFromId("column_definition_id", "table_columns", "table_column_id", $row['referenced_table_column_id'])));
			}
			executeQuery("delete from unique_key_columns where unique_key_id in (select unique_key_id from unique_keys where table_id = ?)", $tableId);
			executeQuery("delete from unique_keys where table_id = ?", $tableId);
			executeQuery("delete from foreign_keys where table_column_id in (select table_column_id from table_columns where table_id = ?) or " .
				"referenced_table_column_id in (select table_column_id from table_columns where table_id = ?)", $tableId, $tableId);
			$viewColumns = array();
			$resultSet = executeQuery("select *,(select column_name from column_definitions where column_definition_id = (select column_definition_id from table_columns where table_column_id = view_columns.table_column_id)) column_name " .
				"from view_columns where table_column_id in (select table_column_id from table_columns where table_id = ?)", $tableId);
			while ($row = getNextRow($resultSet)) {
				$viewColumns[] = $row;
			}
			executeQuery("delete from view_columns where table_column_id in (select table_column_id from table_columns where table_id = ?)", $tableId);
			executeQuery("delete from table_columns where table_id = ? and primary_table_key = 0", $tableId);
			executeQuery("update table_columns set sequence_number = 1 where primary_table_key = 1 and table_id = ?", $tableId);
			$tableColumnId = getFieldFromId("table_column_id", "table_columns", "table_id", $tableId);
			$columnNames[$_POST['column_name_1']] = $tableColumnId;
			foreach ($_POST as $fieldName => $fieldData) {
				if (substr($fieldName, 0, strlen("column_name_")) == "column_name_" && !empty($fieldData)) {
					$rowNumber = substr($fieldName, strlen("column_name_"));
					if ($rowNumber == 1) {
						continue;
					}
					if (empty($_POST['column_definition_id_' . $rowNumber])) {
						$columnName = $_POST['column_name_' . $rowNumber];
						$columnType = $_POST['column_type_' . $rowNumber];
						$dataSize = $_POST['data_size_' . $rowNumber];
						$decimalPlaces = $_POST['decimal_places_' . $rowNumber];
						$defaultValue = $_POST['default_value_' . $rowNumber];
						$notNull = (empty($_POST['not_null_' . $rowNumber]) ? 0 : 1);
						$codeValue = (substr($columnName, -5) == "_code" && $columnType == "varchar" && $dataSize == 100 ? 1 : 0);
						$letterCase = (substr($columnName, -5) == "_code" && $columnType == "varchar" && $dataSize == 100 ? "U" : "");
						$resultSet = executeQuery("insert into column_definitions (column_name,column_type," .
							"data_size,decimal_places,not_null,code_value,letter_case,default_value) values (?,?,?,?,?,?,?,?)", $columnName,
							$columnType, $dataSize, $decimalPlaces, $notNull, $codeValue, $letterCase, $defaultValue);
						$_POST['column_definition_id_' . $rowNumber] = $resultSet['insert_id'];
					}
					$columnDefinitionId = $_POST['column_definition_id_' . $rowNumber];
					$description = $_POST['description_' . $rowNumber];
					$detailedDescription = $_POST['detailed_description_' . $rowNumber];
					$sequenceNumber = $_POST['sequence_number_' . $rowNumber];
					$primaryTableKey = (empty($_POST['primary_table_key_' . $rowNumber]) ? 0 : 1);
					$indexed = (empty($_POST['indexed_' . $rowNumber]) ? 0 : 1);
					$fullText = (empty($_POST['full_text_' . $rowNumber]) ? 0 : 1);
					$notNull = (empty($_POST['not_null_' . $rowNumber]) ? 0 : 1);
					$defaultValue = $_POST['default_value_' . $rowNumber];
					$resultSet = executeQuery("insert into table_columns (table_column_id,table_id,column_definition_id," .
						"description,detailed_description,sequence_number,primary_table_key,indexed,full_text,not_null,default_value,version) " .
						"values (null,?,?,?,?,?,?,?,?,?,?,1)", $tableId, $columnDefinitionId, $description, $detailedDescription, $sequenceNumber, $primaryTableKey,
						$indexed, $fullText, $notNull, $defaultValue);
					$columnNames[$_POST['column_name_' . $rowNumber]] = $resultSet['insert_id'];
				}
			}
			executeQuery("set @sequenceNumber := 0");
			executeQuery("update table_columns set sequence_number = @sequenceNumber := @sequenceNumber + 100 where table_id = " .
				$tableId . " ORDER BY sequence_number,table_column_id");
			executeQuery("update table_columns set sequence_number = 1 where primary_table_key = 1 and table_id = ?", $tableId);
			foreach ($viewColumns as $viewInfo) {
				$tableColumnId = getFieldFromId("table_column_id", "table_columns", "table_id", $tableId, "column_definition_id = (select column_definition_id from column_definitions where column_name = ?)", $viewInfo['column_name']);
				if (!empty($tableColumnId)) {
					executeQuery("insert ignore into view_columns (table_id,table_column_id,sequence_number) values (?,?,?)", $viewInfo['table_id'], $tableColumnId, $viewInfo['sequence_number']);
				}
			}
			foreach ($foreignKeys as $foreignKeyData) {
				if (array_key_exists($foreignKeyData['referenced_column_name'], $columnNames)) {
					executeQuery("insert ignore into foreign_keys (foreign_key_id,table_column_id,referenced_table_column_id,version) values " .
						"(null,?,?,1)", $foreignKeyData['table_column_id'], $columnNames[$foreignKeyData['referenced_column_name']]);
				}
			}
			foreach ($_POST as $fieldName => $fieldData) {
				if (substr($fieldName, 0, strlen("fk_column_name_")) == "fk_column_name_" && !empty($fieldData)) {
					$rowNumber = substr($fieldName, strlen("fk_column_name_"));
					$tableColumnId = $columnNames[$fieldData];
					$referencedTableColumnId = getFieldFromId("table_column_id", "table_columns", "table_id", $_POST['fk_referenced_table_id_' . $rowNumber], "column_definition_id = " . $this->iDatabase->makeNumberParameter($_POST['fk_referenced_column_definition_id_' . $rowNumber]));
					if (!empty($tableColumnId) && !empty($referencedTableColumnId)) {
						executeQuery("insert ignore into foreign_keys (foreign_key_id,table_column_id,referenced_table_column_id,version) values " .
							"(null,?,?,1)", $tableColumnId, $referencedTableColumnId);
						executeQuery("update table_columns set indexed = 1 where table_column_id = ?", $tableColumnId);
					}
				}
			}
			foreach ($_POST as $fieldName => $fieldData) {
				if (substr($fieldName, 0, strlen("uk_fields_")) == "uk_fields_" && !empty($fieldData)) {
					$resultSet = executeQuery("insert into unique_keys (unique_key_id,table_id) values (null,?)", $tableId);
					$uniqueKeyId = $resultSet['insert_id'];
					$columnArray = explode(",", $fieldData);
					$oneWritten = false;
					foreach ($columnArray as $columnName) {
						if (!empty($columnName)) {
							$columnDefinitionId = getFieldFromId("column_definition_id", "column_definitions", "column_name", $columnName);
							$tableColumnId = getFieldFromId("table_column_id", "table_columns", "column_definition_id", $columnDefinitionId, "table_id = " . $this->iDatabase->makeNumberParameter($tableId));
							if (!empty($tableColumnId)) {
								executeQuery("insert ignore into unique_key_columns (unique_key_column_id,unique_key_id,table_column_id,version) values " .
									"(null,?,?,1)", $uniqueKeyId, $tableColumnId);
								$oneWritten = true;
							}
						}
					}
					if (!$oneWritten) {
						executeQuery("delete from unique_keys where unique_key_id = ?", $uniqueKeyId);
					}
				}
			}
			executeQuery("update database_definitions set checked = 0");

			if (!empty($_POST['create_page'])) {
				$pageCode = strtoupper($_POST['table_name']) . "_MAINTENANCE";
				$uniqueNumber = 0;
				do {
					$pageId = $GLOBALS['gAllPageCodes'][$pageCode . (empty($uniqueNumber) ? "" : $uniqueNumber)];
					if (!empty($pageId)) {
						$uniqueNumber++;
					}
				} while (!empty($pageId));
				$linkName = makeCode($_POST['description'] . " Maintenance", array("lowercase" => true));
				$resultSet = executeQuery("insert into pages(client_id, page_code, description, link_name, template_id, date_created, creator_user_id) values " .
					"(?,?,?,?,?,now(),?)", $GLOBALS['gDefaultClientId'], $pageCode . (empty($uniqueNumber) ? "" : $uniqueNumber), $_POST['description'] . " Maintenance", $linkName, $GLOBALS['gManagementTemplateId'], $GLOBALS['gUserId']);
				$pageId = $resultSet['insert_id'];
				if (!empty($pageId)) {
					$templateDataId = getFieldFromId("template_data_id", "template_data", "data_name", "primary_table_name");
					executeQuery("insert into page_data (page_id,template_data_id,text_data) values (?,?,?)", $pageId, $templateDataId, $_POST['table_name']);
					if ($_POST['create_page'] == "admin") {
						executeQuery("insert into page_access (page_id, all_client_access, administrator_access, permission_level) values(?,1,1,3)", $pageId);
					}
				}
			}

			$this->iDatabase->commitTransaction();
		}
		ajaxResponse($returnArray);
	}

	function deleteRecord() {
		$returnArray = array();
		if (!empty($_POST['primary_id'])) {
			$tableId = $_POST['primary_id'];

			$resultSet = executeQuery("select * from tables where table_id = ?", $tableId);
			$row = getNextRow($resultSet);

			$this->iDatabase->startTransaction();
			executeQuery("delete from unique_key_columns where unique_key_id in (select unique_key_id from unique_keys where table_id = ?)", $tableId);
			executeQuery("delete from unique_keys where table_id = ?", $tableId);
			executeQuery("delete from language_text where language_column_id in (select language_column_id from language_columns where table_id = ?)", $tableId);
			executeQuery("delete from language_columns where table_id = ?", $tableId);
			executeQuery("delete from foreign_keys where table_column_id in (select table_column_id from table_columns where table_id = ?) or referenced_table_column_id in (select table_column_id from table_columns where table_id = ?)", $tableId, $tableId);
			executeQuery("delete from table_columns where table_id = ?", $tableId);
			executeQuery("update database_definitions set checked = 0");
			$resultSet = executeQuery("delete from tables where table_id = ?", $tableId);
			if (!empty($resultSet['sql_error'])) {
				$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
				$this->iDatabase->rollbackTransaction();
			} else {
				if ($resultSet['affected_rows'] > 0) {
					$this->iDatabase->createChangeLog("tables", "Row Deleted", $row['table_id'] . ", " . $row['table_name'], null, null);
				}
				$this->iDatabase->commitTransaction();
			}
		}
		ajaxResponse($returnArray);
	}

	function hiddenElements() {
		?>
        <div id="_data_dialog" class="dialog-box">
            <div id="data_content">
            </div>
        </div>
		<?php
	}
}

$pageObject = new ThisPage("tables");
$pageObject->displayPage();
