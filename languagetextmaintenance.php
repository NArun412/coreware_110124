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

$GLOBALS['gPageCode'] = "LANGUAGETEXTMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "delete", "save"));
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("description", "table_id", "column_definition_id"));
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "save_translation":
				if (!empty($_POST['translation'])) {
					$resultSet = executeQuery("select * from language_text where client_id = ? and language_id = ? and language_column_id = ? and " .
						"primary_identifier = ?", $GLOBALS['gClientId'], $GLOBALS['gUserRow']['language_id'], $_POST['primary_id'], $_POST['primary_identifier']);
					if ($row = getNextRow($resultSet)) {
						executeQuery("update language_text set content = ? where language_text_id = ?", $_POST['translation'], $row['language_text_id']);
					} else {
						executeQuery("insert into language_text (client_id,language_id,language_column_id,primary_identifier,content) values (?,?,?,?,?)",
							$GLOBALS['gClientId'], $GLOBALS['gUserRow']['language_id'], $_POST['primary_id'], $_POST['primary_identifier'], $_POST['translation']);
					}
					$tableName = getFieldFromId("table_name", "tables", "table_id", getFieldFromId("table_id", "language_columns", "language_column_id", $_POST['primary_id']));
					$columnName = getFieldFromId("column_name", "column_definitions", "column_definition_id", getFieldFromId("column_definition_id", "language_columns", "language_column_id", $_POST['primary_id']));
					if (!empty($tableName) && !empty($columnName)) {
						$table = new DataTable($tableName);
						$primaryKey = $table->getPrimaryKey();
						$resultSet = executeQuery("select * from " . $tableName . " where " . $primaryKey . " = ?", $_POST['primary_identifier']);
						$existingData = "";
						while ($row = getNextRow($resultSet)) {
							$existingData = $row[$columnName];
						}
						if (!empty($existingData)) {
							$resultSet = executeQuery("select " . $primaryKey . " from " . $tableName . " where " .
								($table->columnExists("client_id") ? "client_id = " . $GLOBALS['gClientId'] . " and " : "") .
								$primaryKey . " not in (select primary_identifier from language_text where client_id = ? and " .
								"language_id = ? and language_column_id = ?) and " . $columnName . " = ?", $GLOBALS['gClientId'], $GLOBALS['gUserRow']['language_id'], $_POST['primary_id'], $existingData);
							while ($row = getNextRow($resultSet)) {
								executeQuery("insert into language_text (client_id,language_id,language_column_id,primary_identifier,content) values (?,?,?,?,?)",
									$GLOBALS['gClientId'], $GLOBALS['gUserRow']['language_id'], $_POST['primary_id'], $row[$primaryKey], $_POST['translation']);
							}
						}
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_list":
				if ($GLOBALS['gUserRow']['language_id'] == $GLOBALS['gEnglishLanguageId']) {
					$returnArray['translation_list'] = "<p>Your user account is set to the English language so no translations can be done.</p>";
					ajaxResponse($returnArray);
					break;
				}
				ob_start();
				?>
                <p><?= getLanguageText("Translation_language") ?>: <?= getFieldFromId("description", "languages", "language_id", $GLOBALS['gUserRow']['language_id']) ?></p>
                <p>Filter:&nbsp;&nbsp;<input tabindex="10" type="text" id="text_filter"></p>
				<?php
				if (!array_key_exists($_GET['primary_id'], $GLOBALS['gLanguageColumnRows'])) {
					$returnArray['translation_list'] = ob_get_clean();
					ajaxResponse($returnArray);
					break;
				}
				?>
                <table class="grid-table">
                    <tr>
                        <th><?= getLanguageText("English Text") ?></th>
                        <th><?= getLanguageText("Translated Text") ?></th>
                    </tr>
					<?php
					$tableName = $GLOBALS['gLanguageColumnRows'][$_GET['primary_id']]['table_name'];
					$columnName = $GLOBALS['gLanguageColumnRows'][$_GET['primary_id']]['column_name'];
					if (!empty($tableName) && !empty($columnName)) {
						$table = new DataTable($tableName);
						$GLOBALS['gNoTranslation'] = true;
						$queryText = $GLOBALS['gLanguageColumnRows'][$_GET['primary_id']]['query_text'];
						if ($table->columnExists("client_id")) {
							$queryText .= (empty($queryText) ? "" : " and ") . "client_id = " . $GLOBALS['gClientId'];
						}
						$primaryKey = $table->getPrimaryKey();
						$resultSet = executeQuery("select " . $primaryKey . "," . $columnName . " from " . $tableName . (empty($queryText) ? "" : " where " . $queryText) .
							" order by " . ($table->columnExists("sort_order") ? "sort_order," : "") . $columnName);
						while ($row = getNextRow($resultSet)) {
							if (empty($row[$columnName])) {
								continue;
							}
							$primaryIdentifier = reset($row);
							$translatedText = getFirstPart(getFieldFromId("content", "language_text", "primary_identifier", $primaryIdentifier, "language_id = " . $GLOBALS['gUserRow']['language_id'] . " and language_column_id = " . $_GET['primary_id']), 40);
							if (empty($translatedText)) {
								$textTranslationId = getFieldFromId("text_translation_id", "text_translations", "english_text", $row[$columnName]);
								if (!empty($textTranslationId)) {
									$translatedText = "[Default Text Translation]";
								}
							}
							?>
                            <tr class="translation-row" id="translation_row_<?= $primaryIdentifier ?>" data-primary_identifier="<?= $primaryIdentifier ?>">
                                <td><?= htmlText(getFirstPart($row[$columnName], 40)) ?></td>
                                <td><?= htmlText($translatedText) ?></td>
                            </tr>
							<?php
						}
						$GLOBALS['gNoTranslation'] = false;
					}
					?>
                </table>
				<?php
				$returnArray['translation_list'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "get_details":
				$returnArray = array();
				if (!array_key_exists($_GET['primary_id'], $GLOBALS['gLanguageColumnRows'])) {
					ajaxResponse($returnArray);
					break;
				}
				$tableName = getFieldFromId("table_name", "tables", "table_id", $GLOBALS['gLanguageColumnRows'][$_GET['primary_id']]['table_id']);
				$columnName = getFieldFromId("column_name", "column_definitions", "column_definition_id", $GLOBALS['gLanguageColumnRows'][$_GET['primary_id']]['column_definition_id']);
				if (!empty($tableName) && !empty($columnName)) {
					$table = new DataTable($tableName);
					$primaryKey = $table->getPrimaryKey();
					$primaryIdentifier = getFieldFromId($primaryKey, $tableName, $primaryKey, $_GET['primary_identifier']);
					$resultSet = executeQuery("select " . $columnName . " from " . $tableName . " where " . $primaryKey . " = ?", $primaryIdentifier);
					if ($row = getNextRow($resultSet)) {
						$returnArray['english_translation'] = $row[$columnName];
					} else {
						$returnArray['english_translation'] = "";
					}
					$returnArray['translation'] = getFieldFromId("content", "language_text", "primary_identifier", $primaryIdentifier, "language_id = " . $GLOBALS['gUserRow']['language_id'] . " and language_column_id = " . $_GET['primary_id']);
					$returnArray['default_text_translation'] = "";
					if (empty($returnArray['translation'])) {
						$textTranslationId = getFieldFromId("text_translation_id", "text_translations", "english_text", $row[$columnName]);
						if (!empty($textTranslationId)) {
							$returnArray['default_text_translation'] = "Default Text Translation will be used";
						}
					}
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("description", "readonly", true);
		$this->iDataSource->addColumnControl("table_id_display", "list_header", "Data Table");
		$this->iDataSource->addColumnControl("column_definition_id_display", "list_header", "Data Field");
	}

	function internalCSS() {
		?>
        <style>
            #default_text_translation {
                height: 30px;
                color: rgb(192, 0, 0);
            }
            #translation_list {
                display: none;
                margin-top: 20px;
            }

            #translation_list table {
                width: 800px;
            }

            #translation_list table td {
                width: 50%;
            }

            .translation-row {
                cursor: pointer;
            }

            .translation-row:hover {
                background-color: rgb(250, 250, 200)
            }

            #translation_details {
                display: none;
                margin-top: 20px;
            }

            #_button_row {
                margin-left: 200px;
            }
        </style>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("keyup", "#text_filter", function (event) {
                var filterValue = $(this).val().toLowerCase();
                $(".translation-row").each(function () {
                    var checkText = $(this).find("td").first().html();
                    if (checkText.toLowerCase().indexOf(filterValue) >= 0) {
                        $(this).removeClass("hidden");
                    } else {
                        $(this).addClass("hidden");
                    }
                });
            });
            $(document).on("tap click", "#cancel_translation", function () {
                $("#translation_list").show();
                $("#translation_details").hide();
                $("#_next_button").css("visibility", ($("#_next_primary_id").val().length == 0 ? "hidden" : "visible"));
                $("#_previous_button").css("visibility", ($("#_previous_primary_id").val().length == 0 ? "hidden" : "visible"));
                enableButtons($("#_list_button"));
                return false;
            });
            $(document).on("tap click", "#save_translation,#list_translation,#next_translation,#previous_translation", function () {
                var action = $(this).data("action");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_translation", $("#_edit_form").serialize(), function(returnArray) {
                    var primaryIdentifier = $("#primary_identifier").val();
                    if (action == "previous") {
                        if ($("#translation_row_" + primaryIdentifier).prev(".translation-row").length > 0) {
                            $("#translation_row_" + primaryIdentifier).prev(".translation-row").trigger("click");
                            return;
                        }
                    }
                    if (action == "next") {
                        if ($("#translation_row_" + primaryIdentifier).next(".translation-row").length > 0) {
                            $("#translation_row_" + primaryIdentifier).next(".translation-row").trigger("click");
                            return;
                        }
                    }
                    getTranslationList();
                });
                return false;
            });
            $(document).on("tap click", ".translation-row", function () {
                if ($(this).next(".translation-row").length > 0) {
                    enableButtons($("#next_translation"));
                } else {
                    disableButtons($("#next_translation"));
                }
                if ($(this).prev(".translation-row").length > 0) {
                    enableButtons($("#previous_translation"));
                } else {
                    disableButtons($("#previous_translation"));
                }
                $("#primary_identifier").val($(this).data("primary_identifier"));
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_details&primary_id=" + $("#primary_id").val() + "&primary_identifier=" + $(this).data("primary_identifier"), function(returnArray) {
                    if ("english_translation" in returnArray) {
                        $("#english_translation").val(returnArray['english_translation']);
                        $("#default_text_translation").html(returnArray['default_text_translation']);
                        $("#translation").val(returnArray['translation']);
                        $("#translation_list").hide();
                        $("#translation_details").show();
                        $("#translation").focus();
                        $("#_next_button").css("visibility", "hidden");
                        $("#_previous_button").css("visibility", "hidden");
                        disableButtons($("#_list_button"));
                    }
                });
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function getTranslationList() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_list&primary_id=" + $("#primary_id").val(), function(returnArray) {
                    if ("translation_list" in returnArray) {
                        $("#translation_list").html(returnArray['translation_list']).show();
                        $("#translation_list").show();
                        $("#translation_details").hide();
                        $("#_next_button").css("visibility", ($("#_next_primary_id").val().length == 0 ? "hidden" : "visible"));
                        $("#_previous_button").css("visibility", ($("#_previous_primary_id").val().length == 0 ? "hidden" : "visible"));
                        enableButtons($("#_list_button"));
                    }
                });
            }

            function afterGetRecord() {
                getTranslationList();
            }
        </script>
		<?php
	}
}

$pageObject = new ThisPage("language_columns");
$pageObject->displayPage();
