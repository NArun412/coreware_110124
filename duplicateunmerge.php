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

$GLOBALS['gPageCode'] = "DUPLICATEUNMERGE";
require_once "shared/startup.inc";

class DuplicateUnmergePage extends Page {
	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setReadonly(true);
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("contact_id", "full_name", "log_time", "user_display_name"));
			$this->iTemplateObject->getTableEditorObject()->addIncludeFormColumn(array("contact_id", "full_name", "log_time", "user_id", "merge_log_details", "unmerge_button"));
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "unmerge_contacts":
				$mergeLogId = $_GET['primary_id'];
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$detailCount = 0;
				$resultSet = executeQuery("select * from merge_log_details where merge_log_id = ? order by merge_log_detail_id desc", $mergeLogId);
				$totalCount = $resultSet['row_count'];
				while ($row = getNextRow($resultSet)) {
					$detailCount++;
					$tableName = $row['table_name'];
					$primaryKey = "";
					try {
						$dataTable = new DataTable($tableName);
						$primaryKey = $dataTable->getPrimaryKey();
					} catch (Exception $e) {
						continue;
					}
					$oldData = json_decode($row['old_value'], true);
					$newData = (empty($row['new_value']) ? array() : json_decode($row['new_value'], true));
					switch ($row['merge_action']) {
						case "insert":
							$updateSet = executeQuery("delete from " . $tableName . " where " . $primaryKey . " = ?", $newData[$primaryKey]);
							if (!empty($updateSet['sql_error']) || $updateSet['affected_rows'] != 1) {
								$returnArray['error_message'] = "Cannot unmerge these contacts" . ($GLOBALS['gUserRow']['superuser_flag'] ? ": " . $tableName . ", " . $row['merge_action'] . ":" . $updateSet['query'] . ":" . jsonEncode($newData[$primaryKey]) : "");
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
							break;
						case "delete":
							$queryString = "";
							$parameterString = "";
							$parameters = array();
							foreach ($oldData as $fieldName => $fieldValue) {
								$queryString .= (empty($queryString) ? "" : ",") . $fieldName;
								$parameterString .= (empty($parameterString) ? "" : ",") . "?";
								$parameters[] = $fieldValue;
							}
							$updateSet = executeQuery("insert into " . $tableName . " (" . $queryString . ") values (" . $parameterString . ")", $parameters);
							if (!empty($updateSet['sql_error'])) {
								$returnArray['error_message'] = "Cannot unmerge these contacts" . ($GLOBALS['gUserRow']['superuser_flag'] ? ": " . $tableName . ", " . $row['merge_action'] . ":" . $updateSet['query'] . ":" . jsonEncode($parameters) : "");
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
							break;
						case "update":
							$queryString = "";
							$parameters = array();
							foreach ($newData as $fieldName => $fieldValue) {
								if ($fieldValue != $oldData[$fieldName]) {
									$queryString .= (empty($queryString) ? "" : ",") . $fieldName . " = ?";
									$parameters[] = $oldData[$fieldName];
								}
							}
							if (!empty($queryString)) {
								$parameters[] = $oldData[$primaryKey];
								$updateSet = executeQuery("update " . $tableName . " set " . $queryString . " where " . $primaryKey . " = ?", $parameters);
								if (!empty($updateSet['sql_error'])) {
									$returnArray['error_message'] = "Cannot unmerge these contacts" . ($GLOBALS['gUserRow']['superuser_flag'] ? ": " . $tableName . ", " . $row['merge_action'] . ":" . $updateSet['query'] . ":" . jsonEncode($parameters) : "");
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
							}
							break;
						default:
							$returnArray['error_message'] = "Cannot unmerge these contacts";
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
					}
				}
				if ($detailCount != $totalCount) {
					$returnArray['error_message'] = "Cannot unmerge these contacts";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("delete from merge_log_details where merge_log_id = ?", $_GET['primary_id']);
				executeQuery("delete from merge_log where merge_log_id = ?", $_GET['primary_id']);
				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				$returnArray['info_message'] = $detailCount . " details processed, Contacts successfully unmerged";
				ajaxResponse($returnArray);
				break;
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("unmerge_button", "button_label", "Unmerge These Contacts");
		$this->iDataSource->addColumnControl("unmerge_button", "data_type", "button");
		$this->iDataSource->addColumnControl("contact_id", "form_label", "Primary Contact");
		$this->iDataSource->addColumnControl("contact_id", "data_type", "varchar");
		$this->iDataSource->addColumnControl("full_name", "form_label", "Merged Contact");
		$this->iDataSource->addColumnControl("log_time", "form_label", "Merged At");
		$this->iDataSource->addColumnControl("user_id", "form_label", "Merged By");
		$this->iDataSource->addColumnControl("user_id", "get_choices", "userChoices");
		$this->iDataSource->addColumnControl("user_display_name", "select_value", "select concat_ws(' ',first_name,last_name) from contacts where contact_id = (select contact_id from users where user_id = merge_log.user_id)");
		$this->iDataSource->addColumnControl("user_display_name", "form_label", "Merged By");
		$this->iDataSource->addColumnControl("merge_log_details", "form_label", "Merge Details");
		$this->iDataSource->addColumnControl("merge_log_details", "data_type", "custom");
		$this->iDataSource->addColumnControl("merge_log_details", "list_table", "merge_log_details");
		$this->iDataSource->addColumnControl("merge_log_details", "control_class", "EditableList");
		$this->iDataSource->setFilterWhere("contact_id in (select contact_id from contacts where client_id = " . $GLOBALS['gClientId'] . ")");
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['contact_id'] = array("data_value" => $returnArray['contact_id']['data_value'] . " - " . getDisplayName($returnArray['contact_id']['data_value']));
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#unmerge_button").click(function () {
                $(this).addClass("hidden");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=unmerge_contacts&primary_id=" + $("#primary_id").val(), function(returnArray) {
                    if (!("error_message" in returnArray)) {
                        setTimeout(function () {
                            $("#_list_button").trigger("click");
                        }, 2000);
                    } else {
                        $(this).removeClass("hidden");
                    }
                });
                return false;
            });
        </script>
		<?php
	}
}

$pageObject = new DuplicateUnmergePage("merge_log");
$pageObject->displayPage();
