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

$GLOBALS['gPageCode'] = "DELETECLIENT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 1200000;
ini_set("memory_limit", "4096M");

class DeleteClientPage extends Page {

	var $iTableArray = array();
	var $iTableIds = array();
	var $iTableNames = array();
	var $iTablesDone = array();
	var $iColumnNames = array();
	var $iTableColumns = array();
	var $iDeleteClientId = "";
	var $iClearFields = array();
	var $iDeleteRows = array();
	var $iSkipTableNames = array();
	var $iTopLevelTableId = "";
	var $iIgnoreDone = array();
	var $iProgramLogId = false;
	var $iCustomDeleteTables = array("product_search_word_values", "product_inventory_log", "product_facet_values");
    var $iProductCount = 0;

	function setup() {
		if (empty($GLOBALS['gUserRow']['superuser_flag'])) {
			header("Location: /");
			exit;
		}
	}

	function displayProgress($text, $logOnly = false) {
		if (!$logOnly) {
			echo "<p>" . date("H:i:s") . "-" . $text . "</p>\n";
		}
		$this->iProgramLogId = addProgramLog(date("H:i:s") . "-" . $text, $this->iProgramLogId);
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "delete_client":
				$clientId = $_POST['client_id'];
				$clientCode = $_POST['client_code'];
				$correctClientCode = getFieldFromId("client_code", "clients", "client_id", $clientId);
				if ($clientCode != $correctClientCode) {
					$returnArray['error_message'] = "Wrong client code";
					ajaxResponse($returnArray);
					break;
				}
				$this->iDeleteClientId = $clientId;

                $resultSet = executeQuery("select count(*) from products where client_id = ?",$this->iDeleteClientId);
                if ($row = getNextRow($resultSet)) {
                    $this->iProductCount = $row['count(*)'];
                }

				executeQuery("update contacts set client_id = 1 where client_id = ? and contact_id in (select contact_id from users where superuser_flag = 1)", $clientId);
				executeQuery("update users set client_id = 1 where client_id = ? and superuser_flag = 1", $clientId);

				$this->iClearFields[] = array("table_name" => "pages", "column_name" => "page_pattern_id");
				$this->iClearFields[] = array("table_name" => "contacts", "column_name" => "image_id");
				$this->iClearFields[] = array("table_name" => "contacts", "column_name" => "responsible_user_id");
				$this->iClearFields[] = array("table_name" => "contacts", "column_name" => "contact_type_id");
				$this->iClearFields[] = array("table_name" => "contacts", "column_name" => "source_id");
				$this->iClearFields[] = array("table_name" => "users", "column_name" => "user_type_id");
				$this->iClearFields[] = array("table_name" => "user_types", "column_name" => "contact_type_id");
				$this->iClearFields[] = array("table_name" => "sources", "column_name" => "contact_id");
				$this->iClearFields[] = array("table_name" => "forms", "column_name" => "parent_form_id", "client_table_name" => "form_definitions", "client_column_name" => "form_definition_id");
				$this->iClearFields[] = array("table_name" => "post_comments", "column_name" => "parent_post_comment_id", "client_table_name" => "posts", "client_column_name" => "post_id");
				$this->iClearFields[] = array("table_name" => "project_log", "column_name" => "parent_log_id", "client_table_name" => "projects", "client_column_name" => "project_id");
				$this->iClearFields[] = array("table_name" => "images", "column_name" => "image_folder_id");

				$this->iDeleteRows[] = array("table_name" => "selected_rows", "client_table_name" => "users", "client_column_name" => "user_id");
				$this->iDeleteRows[] = array("table_name" => "domain_name_pages", "client_table_name" => "domain_names", "client_column_name" => "domain_name_id", "client_id_field" => "domain_client_id");
				$this->iDeleteRows[] = array("table_name" => "domain_names", "client_id_field" => "domain_client_id");
				$this->iDeleteRows[] = array("table_name" => "user_access", "client_table_name" => "users", "client_column_name" => "user_id");
				$this->iDeleteRows[] = array("table_name" => "client_page_templates", "client_id_field" => "client_id");

				$this->iIgnoreDone[] = "merge_log";
				$this->iIgnoreDone[] = "merge_log_details";

				$this->iSkipTableNames = array("clients", "contacts", "users", "images");

				$resultSet = executeQuery("select * from tables where subsystem_id is not null and (subsystem_id in (select subsystem_id from subsystems where subsystem_code not like 'CORE_%'))");
				while ($row = getNextRow($resultSet)) {
					if ($row['subsystem_id'] == $_POST['subsystem_id']) {
						continue;
					}
					$this->iSkipTableNames[] = $row['table_name'];
				}

				ob_start();

				foreach ($this->iClearFields as $thisClearField) {

					$resultSet = executeQuery("update " . $thisClearField['table_name'] . " set " . $thisClearField['column_name'] . " = null where " .
						(empty($thisClearField['client_table_name']) ? "client_id = " . $this->iDeleteClientId : $thisClearField['client_column_name'] . " in (select " . $thisClearField['client_column_name'] . " from " .
							$thisClearField['client_table_name'] . " where client_id = " . $this->iDeleteClientId . ")"));
					if (!empty($resultSet['sql_error'])) {
						$this->displayProgress($resultSet['sql_error'] . ":" . $resultSet['query']);
						$returnArray['results'] = ob_get_clean();
						$returnArray['error_message'] = $resultSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					} else {
						$this->displayProgress($resultSet['affected_rows'] . " rows updated for " . $thisClearField['table_name']);
					}
				}

				foreach ($this->iDeleteRows as $thisDeleteRow) {
					$GLOBALS['gStartTime'] = getMilliseconds();
					$resultSet = executeQuery("delete from " . $thisDeleteRow['table_name'] . " where " .
						(empty($thisDeleteRow['client_table_name']) ? (empty($thisDeleteRow['client_id_field']) ? "client_id" : $thisDeleteRow['client_id_field']) . " = " . $this->iDeleteClientId : $thisDeleteRow['client_column_name'] . " in (select " . $thisDeleteRow['client_column_name'] . " from " .
							$thisDeleteRow['client_table_name'] . " where " . (empty($thisDeleteRow['client_id_field']) ? "client_id" : $thisDeleteRow['client_id_field']) . " = " . $this->iDeleteClientId . ")"));
					$GLOBALS['gEndTime'] = getMilliseconds();
					if (!empty($resultSet['sql_error'])) {
						$this->displayProgress($resultSet['sql_error'] . ":" . $resultSet['query']);
						$returnArray['results'] = ob_get_clean();
						$returnArray['error_message'] = $resultSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					} else {
						$this->displayProgress($resultSet['affected_rows'] . " rows deleted from " . $thisDeleteRow['table_name']);
					}
				}

				$this->iTableIds = array();
				$this->iTableNames = array();
				$resultSet = executeQuery("select * from tables");
				while ($row = getNextRow($resultSet)) {
					$this->iTableIds[$row['table_name']] = $row['table_id'];
					$this->iTableNames[$row['table_id']] = $row['table_name'];
				}

				$this->iColumnNames = array();
				$resultSet = executeQuery("select * from column_definitions");
				while ($row = getNextRow($resultSet)) {
					$this->iColumnNames[$row['column_definition_id']] = $row['column_name'];
				}

				$this->iTableColumns = array();
				$resultSet = executeQuery("select * from table_columns join tables using (table_id) join column_definitions using (column_definition_id) where " .
					"table_column_id in (select table_column_id from foreign_keys) or table_column_id in (select referenced_table_column_id from foreign_keys)");
				while ($row = getNextRow($resultSet)) {
					$this->iTableColumns[$row['table_column_id']] = array("table_id" => $row['table_id'], "column_definition_id" => $row['column_definition_id']);
				}

				$this->iTableArray = array();
				$resultSet = executeQuery("select * from tables where (subsystem_id is null or subsystem_id in (select subsystem_id from subsystems where subsystem_code like 'CORE_%')) and table_name not in (" .
					implode(",", array_fill(0, count($this->iSkipTableNames), "?")) .
					") and table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'client_id'))", $this->iSkipTableNames);
				while ($row = getNextRow($resultSet)) {
					$this->iTableArray[$row['table_id']] = $row['table_name'];
				}
				foreach ($this->iTableArray as $tableId => $tableName) {
					if (in_array($tableId, $this->iTablesDone)) {
						continue;
					}
					if (($errorMessage = $this->deleteFromTable($tableId)) !== true) {
						$returnArray['error_message'] = "Error: " . $errorMessage;
						$returnArray['results'] = ob_get_clean();
						ajaxResponse($returnArray);
						break;
					}
				}
				if (($errorMessage = $this->deleteFromTable($this->iTableIds['images'], false)) !== true) {
					$returnArray['error_message'] = "Error: " . $errorMessage;
					$returnArray['results'] = ob_get_clean();
					ajaxResponse($returnArray);
					break;
				}
				if (($errorMessage = $this->deleteFromTable($this->iTableIds['users'], false)) !== true) {
					$returnArray['error_message'] = "Error: " . $errorMessage;
					$returnArray['results'] = ob_get_clean();
					ajaxResponse($returnArray);
					break;
				}
				if (($errorMessage = $this->deleteFromTable($this->iTableIds['contacts'], false)) !== true) {
					$returnArray['error_message'] = "Error: " . $errorMessage;
					$returnArray['results'] = ob_get_clean();
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("delete from clients where client_id = ?", $this->iDeleteClientId);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = $resultSet['sql_error'];
					$this->displayProgress("Error: " . $resultSet['sql_error']);
					$returnArray['results'] = ob_get_clean();
					ajaxResponse($returnArray);
					break;
				}
				$this->displayProgress("Client successfully deleted");
				$returnArray['results'] = ob_get_clean();
				$returnArray['done'] = true;
				if ($GLOBALS['gApcuEnabled']) {
					apcu_clear_cache();
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function deleteFromTable($tableId, $chainTables = array()) {
        if (in_array($tableId, $this->iTablesDone)) {
            return true;
        }
		$topLevelTable = false;
		if ($chainTables === false) {
			$topLevelTable = true;
			$chainTables = array();
		}
		if (empty($chainTables)) {
			$this->iTopLevelTableId = $tableId;
		}
		$tableName = $this->iTableNames[$tableId];
		if (in_array($tableName, $this->iCustomDeleteTables)) {
			return $this->customDeleteTable($tableName);
		}
		if (!$topLevelTable && in_array($tableName, $this->iSkipTableNames)) {
			return true;
		}
		if (empty($chainTables)) {
			$this->displayProgress("Deleting top level: " . $this->iTableNames[$tableId]);
		} else {
			$this->displayProgress("Deleting from " . $this->iTableNames[$tableId]);
		}

		$referencedTableColumnId = getFieldFromId("table_column_id", "table_columns", "table_id", $tableId, "primary_table_key = 1");

		$referencingTableColumnIds = array();
		$resultSet = executeQuery("select * from foreign_keys where referenced_table_column_id = ? and table_column_id not in (select table_column_id from table_columns where table_id = (select table_id from tables where table_name = 'clients'))", $referencedTableColumnId);
		while ($row = getNextRow($resultSet)) {
			$referencingTableId = $this->iTableColumns[$row['table_column_id']]['table_id'];
			$referencingTableColumnIds[] = $row;
		}

		foreach ($referencingTableColumnIds as $referencingTableColumnRow) {
			$referencingTableColumnId = $referencingTableColumnRow['table_column_id'];
			$columnDefinitionId = $this->iTableColumns[$referencingTableColumnId]['column_definition_id'];
			$referencingTableId = $this->iTableColumns[$referencingTableColumnId]['table_id'];
			$skipColumn = false;
			foreach ($this->iClearFields as $thisClearField) {
				if ($this->iTableNames[$referencingTableId] == $thisClearField['table_name'] && $this->iColumnNames[$columnDefinitionId] == $thisClearField['column_name']) {
					$skipColumn = true;
				}
			}
			if ($skipColumn) {
				continue;
			}
			if ($topLevelTable && in_array($referencingTableId, $this->iTablesDone)) {
				continue;
			}
			if ($referencingTableId == $tableId) {
				$updateSet = executeQuery("update " . $this->iTableNames[$tableId] . " set " . $this->iColumnNames[$columnDefinitionId] . " = null where client_id = " . $this->iDeleteClientId);
				if (!empty($updateSet['sql_error'])) {
					$this->displayProgress("Error: " . $updateSet['sql_error'] . ":" . $updateSet['query']);
					return $updateSet['sql_error'];
				} else {
					$this->displayProgress($updateSet['affected_rows'] . " rows updated for " . $this->iTableNames[$tableId]);
				}
				continue;
			}
			if (in_array($this->iTableNames[$referencingTableId], $this->iTableArray)) {
				$notNull = getFieldFromId("not_null", "table_columns", "table_column_id", $referencingTableColumnId);
			}
			if (!in_array($this->iTableNames[$referencingTableId], $this->iTableArray) || !empty($notNull)) {
				if (!in_array($referencingTableId, $this->iTablesDone)) {
					$thisChain = array_merge(array($referencingTableColumnRow), $chainTables);
					$errorMessage = $this->deleteFromTable($referencingTableId, $thisChain);
					if ($errorMessage !== true) {
						return $errorMessage;
					}
				}
			} else {
				$updateSet = executeQuery("update " . $this->iTableNames[$referencingTableId] . " set " . $this->iColumnNames[$columnDefinitionId] . " = null where client_id = " . $this->iDeleteClientId);
				if (!empty($updateSet['sql_error'])) {
					$this->displayProgress("Error: " . $updateSet['sql_error'] . ":" . $updateSet['query']);
					return $updateSet['sql_error'];
				} else {
					$this->displayProgress($updateSet['affected_rows'] . " rows updated for " . $this->iTableNames[$referencingTableId]);
				}
			}
		}
		$tableName = $this->iTableNames[$tableId];
		if (!$topLevelTable && !empty($chainTables) && in_array($tableName, $this->iSkipTableNames)) {
			return true;
		}
		$GLOBALS['gStartTime'] = getMilliseconds();
		$deleteQuery = "delete from " . $tableName . " where ";
		$chainCount = 0;
		$notNull = false;
		foreach ($chainTables as $chainTableColumnRow) {
			$chainTableColumnId = $chainTableColumnRow['table_column_id'];
			$chainReferencedTableColumnId = $chainTableColumnRow['referenced_table_column_id'];
			$sourceColumnName = $this->iColumnNames[$this->iTableColumns[$chainTableColumnId]['column_definition_id']];
			$notNull = getFieldFromId("not_null", "table_columns", "table_column_id", $chainTableColumnId);
			$columnName = $this->iColumnNames[$this->iTableColumns[$chainReferencedTableColumnId]['column_definition_id']];
			$tableName = $this->iTableNames[$this->iTableColumns[$chainReferencedTableColumnId]['table_id']];
			$deleteQuery .= $sourceColumnName . " is not null and " . $sourceColumnName . " in (select " . $columnName . " from " . $tableName . " where ";
			$chainCount++;
			if (in_array($tableName, $this->iTableArray)) {
				break;
			}
		}
		$deleteQuery .= "client_id = " . $this->iDeleteClientId;
		for ($x = 0; $x < $chainCount; $x++) {
			$deleteQuery .= ")";
		}
		if ($chainCount <= 1 && !empty($notNull)) {
			if (!in_array($this->iTableNames[$tableId], $this->iIgnoreDone)) {
				$this->iTablesDone[] = $tableId;
			}
		}
		$this->displayProgress($deleteQuery, true);
		$updateSet = executeQuery($deleteQuery);
		$GLOBALS['gEndTime'] = getMilliseconds();
		if (!empty($updateSet['sql_error'])) {
			$this->displayProgress("Error: " . $updateSet['sql_error'] . ":" . $deleteQuery);
			return $updateSet['sql_error'];
		} else {
			$this->displayProgress($updateSet['affected_rows'] . " rows deleted for " . $this->iTableNames[$tableId]);
		}
		return true;
	}

	function customDeleteTable($tableName) {
		switch ($tableName) {
			case "product_search_word_values":
                if ($this->iProductCount > 0) {
	                $deleteSet = executeQuery("delete from product_search_word_values where exists (select product_id from products where product_id = product_search_word_values.product_id and client_id = ?)", $this->iDeleteClientId);
	                $this->displayProgress($deleteSet['query'], true);
	                $GLOBALS['gEndTime'] = getMilliseconds();
	                if (!empty($updateSet['sql_error'])) {
		                $this->displayProgress("Error: " . $deleteSet['sql_error'] . ":" . $deleteSet['query']);
		                return $updateSet['sql_error'];
	                } else {
		                $this->displayProgress($deleteSet['affected_rows'] . " rows deleted for " . $tableName);
	                }
                }
				return true;
			case "product_inventory_log":
                if ($this->iProductCount > 0) {
	                $inventoryAdjustmentTypes = array();
	                $resultSet = executeQuery("select inventory_adjustment_type_id from inventory_adjustment_types where client_id = ?", $this->iDeleteClientId);
	                while ($row = getNextRow($resultSet)) {
		                $inventoryAdjustmentTypes[] = $row['inventory_adjustment_type_id'];
	                }
	                if (!empty($inventoryAdjustmentTypes)) {
		                $deleteSet = executeQuery("delete from product_inventory_log where inventory_adjustment_type_id in (" . implode(",", $inventoryAdjustmentTypes) . ")");
		                $this->displayProgress($deleteSet['query'], true);
		                $GLOBALS['gEndTime'] = getMilliseconds();
		                if (!empty($updateSet['sql_error'])) {
			                $this->displayProgress("Error: " . $deleteSet['sql_error'] . ":" . $deleteSet['query']);
			                return $updateSet['sql_error'];
		                } else {
			                $this->displayProgress($deleteSet['affected_rows'] . " rows deleted for " . $tableName);
		                }
	                }
                }
				return true;
			case "product_facet_values":
				if ($this->iProductCount > 0) {
					$productFacetIds = array();
					$resultSet = executeQuery("select product_facet_id from product_facets where client_id = ?", $this->iDeleteClientId);
					while ($row = getNextRow($resultSet)) {
						$productFacetIds[] = $row['product_facet_id'];
					}
					if (!empty($productFacetIds)) {
						$deleteSet = executeQuery("delete from product_facet_values where product_facet_id in (" . implode(",", $productFacetIds) . ")");
						$this->displayProgress($deleteSet['query'], true);
						$GLOBALS['gEndTime'] = getMilliseconds();
						if (!empty($updateSet['sql_error'])) {
							$this->displayProgress("Error: " . $deleteSet['sql_error'] . ":" . $deleteSet['query']);
							return $updateSet['sql_error'];
						} else {
							$this->displayProgress($deleteSet['affected_rows'] . " rows deleted for " . $tableName);
						}
					}
				}
				return true;
		}
        return "Custom Table not found";
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#delete_client").click(function () {
                if (empty($("#client_id").val())) {
                    displayErrorMessage("Select a client");
                    return;
                }

                $("#_post_iframe").html("");
                $("body").addClass("waiting-for-ajax");
                $("#_post_iframe").off("load");
                $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_client").attr("method", "POST").attr("target", "post_iframe").submit();
                $("#_post_iframe").on("load", function () {
                    $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                    const returnText = $(this).contents().find("body").html();
                    const returnArray = processReturn(returnText);
                    if (returnArray === false) {
                        return;
                    }
                    if ("results" in returnArray) {
                        $("#results").html(returnArray['results']);
                    }
                    if ("done" in returnArray) {
                        $("#delete_client").closest("p").addClass("red-text").html("Client Deleted");
                    }
                });
                postTimeout = setTimeout(function () {
                    postTimeout = null;
                    $("#_post_iframe").off("load");
                    $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                    displayErrorMessage("Server not responding");
                }, 1200000);
            });
        </script>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];
		?>
        <form id="_edit_form">
            <div class="basic-form-line" id="_client_id_row">
                <label for="client_id">Client</label>
                <select tabindex="10" class="validate[required]" id="client_id" name="client_id">
                    <option value="">[Select]</option>
					<?php
					$resultSet = executeQuery("select * from contacts join clients using (contact_id) where clients.client_id > 1 and inactive = 1 order by business_name");
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['client_id'] ?>"><?= htmlText($row['business_name']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

            <div class="basic-form-line" id="_subsystem_id_row">
                <label for="subsystem_id">Include Subsystem</label>
                <select tabindex="10" class="" id="subsystem_id" name="subsystem_id">
                    <option value="">[None]</option>
					<?php
					$resultSet = executeQuery("select * from subsystems where subsystem_code not like 'CORE_%'order by description");
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['subsystem_id'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
            </div>

            <div class="basic-form-line" id="_client_code_row">
                <label for="client_code">Client Code</label>
                <input tabindex="10" type="text" class="validate[required] code-value uppercase" id="client_code" name="client_code">
                <div class='basic-form-line-messages'><span class="help-label">for security reasons, you must enter the client code for the client you want to delete</span><span class='field-error-text'></span></div>
            </div>
        </form>
        <p class='highlighted-text'>THIS CANNOT BE UNDONE. THIS DELETION IS PERMANENT.</p>
        <p>
            <button id="delete_client">Delete this Client</button>
        </p>
        <div id="results">
        </div>
		<?php
		return true;
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>
		<?php
	}
}

$pageObject = new DeleteClientPage();
$pageObject->displayPage();
