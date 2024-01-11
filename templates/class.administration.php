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

class Template extends AbstractTemplate {
	/**
	 * @var DataSource
	 */
	private $iDataSource = false;
	private $iErrorMessage = "";

	function setup() {
		if (!empty($_GET['clear_filter'])) {
			executeQuery("delete from user_preferences where user_id = ? and preference_qualifier = ? and preference_id in " .
				"(select preference_id from preferences where preference_code in ('MAINTENANCE_FILTER_COLUMN','MAINTENANCE_FILTER_TEXT','MAINTENANCE_SET_FILTERS'))", $GLOBALS['gUserId'], $GLOBALS['gPageCode']);
		}
		if (!empty($_GET['primary_id_only'])) {
			setUserPreference("MAINTENANCE_FILTER_TEXT", $_GET['primary_id'], $GLOBALS['gPageRow']['page_code']);
		}
		if (empty($_GET['url_action']) && empty($_GET['url_page'])) {
			setUserPreference("MAINTENANCE_START_ROW", 0, $GLOBALS['gPageRow']['page_code']);
		}
		$primaryTableName = $this->iPageObject->getPrimaryTableName();
		if (!empty($primaryTableName)) {
			$this->iDataSource = new DataSource($primaryTableName);
			$this->iPageObject->setDataSource($this->iDataSource);
			$this->iPageObject->setDatabase($this->iDataSource->getDatabase());
			$this->iPageObject->massageDataSource();
			if (function_exists("_localServerMassageDataSource")) {
				_localServerMassageDataSource($this->iPageObject);
			}
			addDataLimitations($this->iDataSource);
			$this->iDataSource->getPageControls();
			if (!empty($_GET['primary_id'])) {
				$primaryId = getFieldFromId($this->iDataSource->getPrimaryTable()->getPrimaryKey(),
					$this->iDataSource->getPrimaryTable()->getName(), $this->iDataSource->getPrimaryTable()->getPrimaryKey(),
					$_GET['primary_id'], ($this->iDataSource->getPrimaryTable()->isLimitedByClient() || !$this->iDataSource->getPrimaryTable()->columnExists("client_id") ? "" : "client_id is not null"));
				if (empty($primaryId)) {
					$this->iErrorMessage = getSystemMessage("not_found");
					$_GET['url_page'] = "";
				} else {
					$this->iDataSource->setPrimaryId($primaryId);
				}
			}
		} else {
			$this->iPageObject->setDatabase($GLOBALS['gPrimaryDatabase']);
		}
		$this->iPageObject->executeSubaction();
		if ($this->iDataSource) {
			switch ($_GET['url_page']) {
				case "show":
				case "new":
					$this->iTableEditorObject = new TableEditorForm($this->iDataSource);
					break;
				case "guisort":
					$this->iTableEditorObject = new TableEditorSorter($this->iDataSource);
					break;
				case "spreadsheet":
					$this->iTableEditorObject = new TableEditorSpreadsheet($this->iDataSource);
					break;
				case "resetsort":
					$sortOrder = $_GET['sort_order'];
					if (empty($sortOrder) || !is_numeric($sortOrder)) {
						$sortOrder = 0;
					}
					executeQuery("update " . $this->iDataSource->getPrimaryTable()->getName() . " set sort_order = ? where " .
						$this->iDataSource->getPrimaryTable()->getPrimaryKey() . " in (select primary_identifier from selected_rows where page_id = ? and user_id = ?)", $sortOrder, $GLOBALS['gPageId'], $GLOBALS['gUserId']);
				default:
					$this->iTableEditorObject = new TableEditorList($this->iDataSource);
					break;
			}
		}
		if ($this->iTableEditorObject) {
			$this->iTableEditorObject->setPageObject($this->iPageObject);
		}
		$this->iPageObject->setup();
		if (function_exists("_localServerSetup")) {
			_localServerSetup($this->iPageObject);
		}
	}

	/**
	 * @return TableEditor
	 */
	function getTableEditorObject() {
		return $this->iTableEditorObject;
	}

	function executeUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_help_file":
				$pageHelp = getFieldFromId("help_text", "pages", "page_id", $GLOBALS['gPageId'], "client_id is not null");
				if (empty($pageHelp)) {
					$pageHelpFileCode = "HELP_FOR_" . $GLOBALS['gPageCode'];
					$helpFileId = getFieldFromId("file_id", "files", "file_code", $pageHelpFileCode);
					if (!empty($helpFileId)) {
						$returnArray['help_url'] = "/download.php?id=" . $helpFileId;
					} else if (!empty($GLOBALS['gPageRow']['core_page'])) {
						$curlHandle = curl_init("https://www.coreware.com/download.php?file_id_only=true&code=help_for_" . strtolower($GLOBALS['gPageCode']));
						curl_setopt($curlHandle, CURLOPT_HEADER, 0);
						curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
						curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 15);
						curl_setopt($curlHandle, CURLOPT_TIMEOUT, 15);
						curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, ($GLOBALS['gDevelopmentServer'] ? false : true));
						$remoteHelpFileId = curl_exec($curlHandle);
						curl_close($curlHandle);
						if (!empty($remoteHelpFileId) && !is_numeric($remoteHelpFileId)) {
							$remoteHelpFileId = "";
						}
						if (!empty($remoteHelpFileId)) {
							$returnArray['help_url'] = "https://www.coreware.com/download.php?id=" . $remoteHelpFileId;
						}
					}
				} else {
					$returnArray['page_help'] = $pageHelp;
				}
				ajaxResponse($returnArray);
				break;
			case "remove_user_menu":
				executeQuery("delete from user_menus where user_id = ? and user_menu_id = ?", $GLOBALS['gUserId'], $_GET['user_menu_id']);
				removeCachedData("admin_menu", "*");
				ajaxResponse($returnArray);
				break;
			case "contact_picker_add_contact":
				$contactDataTable = new DataTable("contacts");
				$returnArray['contact_id'] = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['contact_picker_first_name'], "last_name" => $_POST['contact_picker_last_name'],
					"email_address" => $_POST['contact_picker_email_address'])));
				$returnArray['description'] = $_POST['contact_picker_first_name'] . " " . $_POST['contact_picker_last_name'] . " • " . $_POST['contact_picker_email_address'];
				ajaxResponse($returnArray);
				break;
			case "bookmark_record":
				$sequenceNumber = 0;
				$resultSet = executeQuery("select max(sequence_number) from user_menus where user_id = ?", $GLOBALS['gUserId']);
				if ($row = getNextRow($resultSet)) {
					$sequenceNumber = $row['max(sequence_number)'];
				}
				if (empty($sequenceNumber)) {
					$sequenceNumber += 10;
				} else {
					$sequenceNumber = 10;
				}
				executeQuery("insert into user_menus (user_id,sequence_number,link_title,script_filename) values (?,?,?,?)", $GLOBALS['gUserId'], $sequenceNumber, $_POST['bookmark_link_title'], $_POST['bookmark_script_filename']);
				$returnArray['info_message'] = "Record bookmarked";
				removeCachedData("admin_menu", "*");
				ajaxResponse($returnArray);
				break;
			case "get_control_table_options":
				$controlCode = $_GET['control_code'];
				$controlInfo = $GLOBALS['gPrimaryDatabase']->getAddNewInfo($controlCode);
				if (empty($controlInfo) || empty($controlInfo['table_name'])) {
					ajaxResponse($returnArray);
					break;
				}
				$controlRecords = false;
				if (method_exists($GLOBALS['gPageObject'], "customGetControlRecords")) {
					$controlRecords = $GLOBALS['gPageObject']->customGetControlRecords($controlCode);
				}
				if (empty($controlRecords)) {
					$controlRecords = $GLOBALS['gPrimaryDatabase']->getControlRecords(array("table_name" => $controlInfo['table_name']));
				}
				if (!is_array($controlRecords)) {
					$controlRecords = array();
				}
				$controlRecords = array_values($controlRecords);
				$returnArray['options'] = $controlRecords;
				$resultSet = executeQuery("select * from change_log where table_name = ? and user_id = ? and old_value = '[NEW RECORD]' and time_changed > date_sub(now(),interval 5 minute) order by time_changed desc",
					$controlInfo['table_name'], $GLOBALS['gUserId']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['control_id'] = $row['primary_identifier'];
				}
				ajaxResponse($returnArray);
				break;
			case "load_stored_report":
				$parameters = getFieldFromId("parameters", "stored_reports", "stored_report_id", $_GET['stored_report_id'], "user_id = ?", $GLOBALS['gUserId']);
				$returnArray['parameters'] = json_decode($parameters, true);
				$returnArray['raw_parameters'] = $parameters;
				ajaxResponse($returnArray);
				break;
			case "get_system_notice_content":
				if ($GLOBALS['gLoggedIn']) {
					$resultSet = executeQuery("select * from system_notices where inactive = 0 and client_id = ? and " .
						"(start_time is null or start_time <= current_time) and system_notice_id = ? and " .
						"(end_time is null or end_time >= current_time) and (all_user_access = 1 or system_notice_id in " .
						"(select system_notice_id from system_notice_users where user_id = " . $GLOBALS['gUserId'] . ")" .
						(empty($GLOBALS['gUserRow']['superuser_flag']) && empty($GLOBALS['gUserRow']['full_client_access']) ? "" : " or full_client_access = 1") . ") order by time_submitted",
						$GLOBALS['gClientId'], $_GET['system_notice_id']);
					if ($row = getNextRow($resultSet)) {
						$returnArray['system_notice_content'] = makeHtml($row['content']);
					}
				}
				ajaxResponse($returnArray);
				break;
			case "mark_system_notice_read":
				if ($GLOBALS['gLoggedIn']) {
					$resultSet = executeQuery("select * from system_notices where inactive = 0 and client_id = ? and " .
						"(start_time is null or start_time <= current_time) and system_notice_id = ? and " .
						"(end_time is null or end_time >= current_time) and (all_user_access = 1 or system_notice_id in " .
						"(select system_notice_id from system_notice_users where user_id = " . $GLOBALS['gUserId'] . ")" .
						(empty($GLOBALS['gUserRow']['superuser_flag']) && empty($GLOBALS['gUserRow']['full_client_access']) ? "" : " or full_client_access = 1") . ") order by time_submitted",
						$GLOBALS['gClientId'], $_GET['system_notice_id']);
					if ($row = getNextRow($resultSet)) {
						$systemNoticeUserId = getFieldFromId("system_notice_user_id", "system_notice_users", "system_notice_id", $row['system_notice_id'], "user_id = ?", $GLOBALS['gUserId']);
						if (empty($systemNoticeUserId)) {
							executeQuery("insert into system_notice_users (system_notice_id,user_id,time_read) values (?,?,now())", $row['system_notice_id'], $GLOBALS['gUserId']);
						} else {
							executeQuery("update system_notice_users set time_read = now() where time_read is null and system_notice_id = ? and user_id = ?", $row['system_notice_id'], $GLOBALS['gUserId']);
						}
					}
				}
				ajaxResponse($returnArray);
				break;
			case "log_click":
				if (empty($_POST['description'])) {
					$_POST['description'] = "Click from " . $_SERVER['REQUEST_URI'];
				}
				executeQuery("insert into click_log (client_id,description,user_id,ip_address,log_time) values (?,?,?,?,now())",
					$GLOBALS['gClientId'], $_POST['description'], $GLOBALS['gUserId'], $_SERVER['REMOTE_ADDR']);
				ajaxResponse($returnArray);
				break;
			case "select_by_query":
				if (!empty($_POST['query_select_clear_existing'])) {
					executeQuery("delete from selected_rows where user_id = ? and page_id = ?", array($GLOBALS['gUserId'], $GLOBALS['gPageId']));
				}
				$primaryIds = array();
				$whereStatement = "";
				foreach ($_POST as $fieldName => $columnName) {
					$primaryKey = $this->iDataSource->getPrimaryTable()->getPrimaryKey();
					if (startsWith($fieldName, "query_select_criteria_column_name-")) {
						$column = $this->iDataSource->getColumns($columnName);
						$dataType = $column->getControlValue("data_type");
						$thisWhereStatement = "";
						$rowNumber = substr($fieldName, strlen("query_select_criteria_column_name-"));
						$comparator = $_POST['query_select_criteria_comparator-' . $rowNumber];
						$fieldValue = $_POST['query_select_criteria_field_value-' . $rowNumber];
						$fieldEndValue = $_POST['query_select_criteria_field_end_value-' . $rowNumber];
						if ($dataType == "date") {
							$fieldValue = date("Y-m-d", strtotime($fieldValue));
							$fieldEndValue = date("Y-m-d", strtotime($fieldEndValue));
						}
						switch ($comparator) {
							case "=":
							case "<>":
							case ">":
							case ">=":
							case "<":
							case "<=":
								$thisWhereStatement = $columnName . " " . $comparator . " " . makeParameter($fieldValue);
								break;
							case "not null":
							case "is null":
								$thisWhereStatement = $columnName . " " . $comparator;
								break;
							case "between":
								$thisWhereStatement = $columnName . " between " . makeParameter($fieldValue) . " and " . makeParameter($fieldEndValue);
								break;
							case "true":
								$thisWhereStatement = $columnName . " = 1";
								break;
							case "false":
								$thisWhereStatement = $columnName . " = 0";
								break;
							case "starts":
								$thisWhereStatement = $columnName . " like " . makeParameter($fieldValue . "%");
								break;
							case "contains":
								$thisWhereStatement = $columnName . " like " . makeParameter("%" . $fieldValue . "%");
								break;
							case "in":
							case "not in":
								if (!empty($fieldValue)) {
									$parts = explode(",", $fieldValue);
									$wherePart = "";
									foreach ($parts as $thisPart) {
										$wherePart .= (empty($wherePart) ? "" : ",") . makeParameter($thisPart);
									}
									$thisWhereStatement = $columnName . " " . $comparator . " (" . $wherePart . ")";
								}
								break;
						}
						if (!empty($thisWhereStatement)) {
							$whereStatement .= (empty($whereStatement) ? "" : " " . ($_POST['query_select_which_records'] == "all" ? "and" : "or") . " ") . $thisWhereStatement;
						}
					}
				}
				if (!empty($whereStatement)) {
					$this->iDataSource->setFilterWhere($whereStatement);
					$dataList = $this->iDataSource->getDataList();
					foreach ($dataList as $thisItem) {
						$primaryIds[] = $thisItem[$primaryKey];
					}
				}
				if (!empty($primaryIds)) {
					foreach ($primaryIds as $primaryId) {
						executeQuery("insert into selected_rows (user_id,page_id,primary_identifier) values (?,?,?)", $GLOBALS['gUserId'], $GLOBALS['gPageId'], $primaryId);
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_query_select_choices":
				$columns = $this->iDataSource->getColumns($_GET['column_name']);
				if (empty($columns)) {
					ajaxResponse($returnArray);
					break;
				}
				$referencedTable = $columns->getReferencedTable();
				if (!empty($referencedTable)) {
					$dataTable = new DataTable($referencedTable);
					$resultSet = executeQuery("select count(*) from " . $referencedTable . ($dataTable->columnExists("client_id") ? " where client_id = " . $GLOBALS['gClientId'] : ""));
					if ($row = getNextRow($resultSet)) {
						if ($row['count(*)'] > 100) {
							ajaxResponse($returnArray);
							break;
						}
					}
				}
				$choices = $columns->getChoices($this->iPageObject);
				$returnArray['choices'] = $choices;
				ajaxResponse($returnArray);
				break;
			case "get_page_choices":
				$pageChoices = getCachedData("admin_page_choices", $GLOBALS['gUserId']);
				if (empty($pageChoices)) {
					$resultSet = executeQuery("select * from pages where (template_id in (select template_id from templates where include_crud = 1) or (client_id = ? and page_id in (select page_id from page_access where all_client_access = 0 and administrator_access = 1))) and " .
						"(client_id = ? or (client_id = ?" . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and page_id in (select page_id from page_access where all_client_access = 1)") . "))" .
						($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access'] ? "" : " and page_id in (select page_id from menu_items)") .
						" and inactive = 0 and (publish_start_date is null or (publish_start_date is not null and current_date >= publish_start_date)) and (publish_end_date is null or " .
						"(publish_end_date is not null and current_date <= publish_end_date)) order by description", $GLOBALS['gClientId'], $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
					$returnArray['page_choices'] = "";
					while ($row = getNextRow($resultSet)) {
						if (empty($row['script_filename']) && empty($row['link_name'])) {
							continue;
						}
						$pageSubsystemId = $row['subsystem_id'];
						if (!empty($pageSubsystemId) && !$GLOBALS['gUserRow']['superuser_flag']) {
							if (!in_array($pageSubsystemId, $GLOBALS['gClientSubsystems'])) {
								continue;
							}
						}
						$domainName = $row['domain_name'];
						if (!empty($row['link_name'])) {
							$linkUrl = ($domainName == $_SERVER['HTTP_HOST'] || empty($domainName) || $GLOBALS['gDevelopmentServer'] ? "" : "https://" . $domainName) . "/" . $row['link_name'];
						} else {
							$linkUrl = ($domainName == $_SERVER['HTTP_HOST'] || empty($domainName) || $GLOBALS['gDevelopmentServer'] ? "" : "https://" . $domainName) . "/" . $row['script_filename'] . (empty($row['script_arguments']) ? "" : "?" . $row['script_arguments']);
						}
						if (canAccessPage($row['page_id'])) {
							$returnArray['page_choices'] .= "<div class='page-choice menu-item' data-script_filename='" . $linkUrl . "'>" . htmlText($row['description']) . "</div>";
						}

					}
					setCachedData("admin_page_choices", $GLOBALS['gUserId'], $returnArray['page_choices'], 1);
				} else {
					$returnArray['page_choices'] = $pageChoices;
				}
				ajaxResponse($returnArray);
				break;
			case "get_admin_menu":
				if ($GLOBALS['gLoggedIn']) {
					$menuData = getCachedData("admin_menu", $_GET['menu_code'] . ":" . jsonEncode($_POST) . ":" . $GLOBALS['gUserId']);
				} else {
					$menuData = false;
				}
				if (!$menuData) {
					$menuCode = $_GET['menu_code'];
					$menuParameters = $_POST;
					$menuData = getMenuByCode($menuCode, $menuParameters);
					if ($GLOBALS['gLoggedIn']) {
						setCachedData("admin_menu", $_GET['menu_code'] . ":" . jsonEncode($_POST) . ":" . $GLOBALS['gUserId'], $menuData, 1);
					}
				}
				$returnArray['menu'] = $menuData;
				ajaxResponse($returnArray);
				break;
			case "get_changes":
				$tableName = getFieldFromId("table_name", "tables", "table_name", $_GET['table_name']);
				if (empty($tableName)) {
					ajaxResponse($returnArray);
					break;
				}
				$changeLogForeignKeys = $GLOBALS['gPrimaryDatabase']->getChangeLogForeignKeys($tableName);
				$searchTables = "";
				foreach ($changeLogForeignKeys as $foreignKeyInfo) {
					$searchTables .= (empty($searchTables) ? "" : ",") . "'" . $foreignKeyInfo['table_name'] . "'";
				}
				$tableHtml = "<table id='change_log_table' class='grid-table'><tr><th>Table</th><th>Field</th><th>Who</th><th>When</th><th>From</th><th>To</th><th>Notes</th></tr>";
				$query = "select * from change_log where table_name = ? and primary_identifier = ?" .
					(empty($searchTables) ? "" : " union select * from change_log where table_name in (" . $searchTables . ") and foreign_key_identifier = ?") . " order by log_id desc";
				$parameters = array($tableName, $_GET['primary_id']);
				if (!empty($searchTables)) {
					$parameters[] = $_GET['primary_id'];
				}

				$resultSet = executeReadQuery($query, $parameters);
				$totalCount = $resultSet['row_count'];
				if ($totalCount == 0) {
					$tableHtml .= "<tr><td colspan='7'>No Changes Found</td></tr>";
				}

				$displayCount = 0;
				while ($row = getNextRow($resultSet)) {
					$fieldDescription = ucwords(str_replace("_", " ", $row['column_name']));
					if (strtolower(substr($fieldDescription, -3)) == " id") {
						$fieldDescription = substr($fieldDescription, 0, -3);
					}
					$dateLink = sprintf('<a href="/changelog.php?clear_filter=true&url_page=show&primary_id=%s" target="_blank">%s</a>', $row['log_id'], date("m/d/Y g:i:sa", strtotime($row['time_changed'])));
					$tableHtml .= "<tr><td>" . ucwords(str_replace("_", " ", $row['table_name'])) . "</td>" .
						"<td>" . $fieldDescription . "</td><td>" . (empty($row['user_id']) ? "" : getUserDisplayName($row['user_id'])) . "</td><td>" .
						$dateLink . "</td><td><textarea readonly='readonly'>" .
						htmlText($row['old_value']) . "</textarea></td><td><textarea readonly='readonly'>" . htmlText($row['new_value']) . "</textarea></td><td><textarea readonly='readonly'>" . htmlText($row['notes']) . "</textarea></td></tr>";
					$displayCount++;
					if ($displayCount >= 50) {
						break;
					}
				}
				$tableHtml .= "</table>";

				if ($totalCount > $displayCount) {
					$tableHtml .= "<br><p>" . $displayCount . " of " . $totalCount . " displayed. <a href='/changelog.php?clear_filter=true&url_page=list&primary_identifier=" . urlencode($_GET['primary_id']) . "&table_name=" . $tableName . "' target='_blank'>Show All</a></p>";
				}
				$returnArray['changes'] = $tableHtml;
				ajaxResponse($returnArray);
				break;
			case "admin_user_return":
				if (!empty($_SESSION['original_user_id'])) {
					$newUserId = $_SESSION['original_user_id'];
					$_SESSION['original_user_id'] = "";
					unset($_SESSION['original_user_id']);
					saveSessionData();
					$resultSet = executeReadQuery("select * from users where user_id = ? and inactive = 0 and client_id = ?", $newUserId, $GLOBALS['gClientId']);
					if ($row = getNextRow($resultSet)) {
						login($row['user_id']);
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_sort_list":
				if (!method_exists($this->iPageObject, "getSortList") || !$this->iPageObject->getSortList()) {
					if ($this->iTableEditorObject) {
						$this->iTableEditorObject->getSortList();
					}
				}
				break;
			case "preferences":
				if (!method_exists($this->iPageObject, "setPreferences") || !$this->iPageObject->setPreferences()) {
					if ($this->iTableEditorObject) {
						$this->iTableEditorObject->setPreferences();
					}
				}
				break;
			case "get_data_list":
				if (!method_exists($this->iPageObject, "getDataList") || !$this->iPageObject->getDataList()) {
					if ($this->iTableEditorObject) {
						$this->iTableEditorObject->getDataList();
					}
				}
				break;
			case "clear_all_filters":
				executeQuery("delete from user_preferences where user_id = ? and preference_qualifier = ? and preference_id in " .
					"(select preference_id from preferences where preference_code in ('MAINTENANCE_SET_FILTERS'))", $GLOBALS['gUserId'], $GLOBALS['gPageCode']);
				ajaxResponse($returnArray);
				break;
			case "select_row":
				if (!method_exists($this->iPageObject, "selectRow") || !$this->iPageObject->selectRow()) {
					if ($this->iTableEditorObject) {
						$this->iTableEditorObject->selectRow();
					}
				}
				break;
			case "exportallcsv":
			case "exportcsv":
				if (!method_exists($this->iPageObject, "exportCSV") || !$this->iPageObject->exportCSV()) {
					if ($this->iTableEditorObject) {
						$this->iTableEditorObject->exportCSV($_GET['url_action'] == "exportallcsv");
					}
				}
				break;
			case "get_record":
				if (!method_exists($this->iPageObject, "getRecord") || !$this->iPageObject->getRecord()) {
					if ($this->iTableEditorObject) {
						$this->iTableEditorObject->getRecord();
					}
				}
				break;
			case "save_changes":
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					if (!empty($_POST['primary_id'])) {

# Check to see if data limitations restrict this record to readonly. If so, don't allow save

						$checkQuery = "";
						$permissionLevel = "1";
						$resultSet = executeReadQuery("select * from user_type_data_limitations where user_type_id = ? and page_id = ? and permission_level = ?",
							$GLOBALS['gUserRow']['user_type_id'], $GLOBALS['gPageId'], $permissionLevel);
						while ($row = getNextRow($resultSet)) {
							if (!empty($checkQuery)) {
								$checkQuery .= " or ";
							}
							$checkQuery .= "(" . PlaceHolders::massageContent($row['query_text']) . ")";
						}
						$resultSet = executeReadQuery("select * from user_data_limitations where user_id = ? and page_id = ? and permission_level = ?",
							$GLOBALS['gUserId'], $GLOBALS['gPageId'], $permissionLevel);
						while ($row = getNextRow($resultSet)) {
							if (!empty($checkQuery)) {
								$checkQuery .= " or ";
							}
							$checkQuery .= "(" . PlaceHolders::massageContent($row['query_text']) . ")";
						}
						foreach ($GLOBALS['gUserRow'] as $fieldName => $fieldData) {
							if (!is_array($fieldData)) {
								$checkQuery = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $checkQuery);
							}
						}
						if (!empty($checkQuery)) {
							$countQuery = "select count(*) from " . $this->iDataSource->getPrimaryTable()->getName() .
								" where " . $this->iDataSource->getPrimaryTable()->getPrimaryKey() . " = ? and " . $checkQuery;
							$count = getCachedData("data_source_count", $countQuery . ":" . $_POST['primary_id']);
							if (!$count) {
								$resultSet = executeReadQuery($countQuery, $_POST['primary_id']);
								if ($row = getNextRow($resultSet)) {
									$count = $row['count(*)'];
								} else {
									$count = 0;
								}
								freeResult($resultSet);
								setCachedData("data_source_count", $countQuery . ":" . $_POST['primary_id'], $count, .1);
							}
							if ($count > 0) {
								$GLOBALS['gPermissionLevel'] = $permissionLevel;
							}
						}
					}
				}
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					if (!method_exists($this->iPageObject, "saveChanges") || !$this->iPageObject->saveChanges()) {
						if ($this->iTableEditorObject) {
							$this->iTableEditorObject->saveChanges();
						}
					}
				} else {
					$returnArray = array("error_message" => getSystemMessage("denied"));
					ajaxResponse($returnArray);
					break;
				}
				break;
			case "delete_record":
				if ($GLOBALS['gPermissionLevel'] > _READWRITE) {
					if (!empty($_POST['primary_id'])) {
						$permissionArray = array("1", "2");
						foreach ($permissionArray as $permissionLevel) {
							if ($GLOBALS['gPermissionLevel'] < _FULLACCESS) {
								break;
							}
							$checkQuery = "";
							$resultSet = executeReadQuery("select * from user_type_data_limitations where user_type_id = ? and page_id = ? and permission_level = ?",
								$GLOBALS['gUserRow']['user_type_id'], $GLOBALS['gPageId'], $permissionLevel);
							while ($row = getNextRow($resultSet)) {
								if (!empty($checkQuery)) {
									$checkQuery .= " or ";
								}
								$checkQuery .= "(" . PlaceHolders::massageContent($row['query_text']) . ")";
							}
							$resultSet = executeReadQuery("select * from user_data_limitations where user_id = ? and page_id = ? and permission_level = ?",
								$GLOBALS['gUserId'], $GLOBALS['gPageId'], $permissionLevel);
							while ($row = getNextRow($resultSet)) {
								if (!empty($checkQuery)) {
									$checkQuery .= " or ";
								}
								$checkQuery .= "(" . PlaceHolders::massageContent($row['query_text']) . ")";
							}
							foreach ($GLOBALS['gUserRow'] as $fieldName => $fieldData) {
								if (!is_array($fieldData)) {
									$checkQuery = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $checkQuery);
								}
							}
							if (!empty($checkQuery)) {
								$countQuery = "select count(*) from " . $this->iDataSource->getPrimaryTable()->getName() .
									" where " . $this->iDataSource->getPrimaryTable()->getPrimaryKey() . " = ? and " . $checkQuery;
								$count = getCachedData("data_source_count", $countQuery . ":" . $_POST['primary_id']);
								if (!$count) {
									$resultSet = executeReadQuery($countQuery, $_POST['primary_id']);
									if ($row = getNextRow($resultSet)) {
										$count = $row['count(*)'];
									} else {
										$count = 0;
									}
									freeResult($resultSet);
									setCachedData("data_source_count", $countQuery . ":" . $_POST['primary_id'], $count, .1);
								}
								if ($count > 0) {
									$GLOBALS['gPermissionLevel'] = $permissionLevel;
								}
							}
						}
					}
				}
				if ($GLOBALS['gPermissionLevel'] > _READWRITE) {
					if (!method_exists($this->iPageObject, "deleteRecord") || !$this->iPageObject->deleteRecord()) {
						if ($this->iTableEditorObject) {
							$this->iTableEditorObject->deleteRecord();
						}
					}
				} else {
					$returnArray = array("error_message" => getSystemMessage("denied"));
					ajaxResponse($returnArray);
					break;
				}
				break;
			case "get_spreadsheet_list":
				if (!method_exists($this->iPageObject, "getSpreadsheetList") || !$this->iPageObject->getSpreadsheetList()) {
					if ($this->iTableEditorObject && ($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access'] || hasCapability("SPREADSHEET_EDITING"))) {
						$this->iTableEditorObject->getSpreadsheetList();
					}
				}
				break;
			case "select_tab":
				setUserPreference("MAINTENANCE_ACTIVE_TAB", $_GET['tab_index'], $GLOBALS['gPageRow']['page_code']);
				echo jsonEncode(array());
				exit;
			case "get_preset_record":
				$resultSet = executeReadQuery("select * from preset_record_values where preset_record_id = ?", $_GET['preset_record_id']);
				$columnValues = array();
				while ($row = getNextRow($resultSet)) {
					$columnValues[$row['column_name']] = $row['text_data'];
				}
				echo jsonEncode($columnValues);
				exit;
			case "show_search_fields":
				setUserPreference("MAINTENANCE_SHOW_FILTER_COLUMNS", $_GET['value'], $GLOBALS['gPageRow']['page_code']);
				echo jsonEncode(array());
				exit;
		}
		if (function_exists("_localServerExecutePageUrlActions")) {
			_localServerExecutePageUrlActions($this->iPageObject);
		}
		if (!empty($this->iPageObject->iTemplateAddendumObject) && method_exists($this->iPageObject->iTemplateAddendumObject, "executeUrlActions")) {
			call_user_func(array($this->iPageObject->iTemplateAddendumObject, "executeUrlActions"));
		}
	}

	function footer() {
		$this->iPageObject->footer();
	}

	function displayPage() {
		$GLOBALS['gTemplateRow'] = getReadRowFromId("templates", "template_id", $GLOBALS['gPageTemplateId'], "client_id = ? or client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);

		$pageTextChunks = array();
		if (is_array($GLOBALS['gPageRow']['page_text_chunks'])) {
			foreach ($GLOBALS['gPageRow']['page_text_chunks'] as $pageTextChunkCode => $pageTextChunkContent) {
				$pageTextChunks[strtolower($pageTextChunkCode)] = $pageTextChunkContent;
				PlaceHolders::setPlaceholderValue("page_text_chunk:" . strtolower(strtolower($pageTextChunkCode)),$pageTextChunkContent);
			}
		}
		if (function_exists("massagePageTextChunks")) {
			massagePageTextChunks($pageTextChunks);
		}
		if (method_exists($this->iPageObject, "massagePageTextChunks")) {
			$this->iPageObject->massagePageTextChunks($pageTextChunks);
		}
		foreach ($pageTextChunks as $pageTextChunkCode => $content) {
			foreach ($GLOBALS['gTemplateRow'] as $fieldName => $fieldData) {
				$GLOBALS['gTemplateRow'][$fieldName] = str_replace("%page_text_chunk:" . $pageTextChunkCode . "%", $content, $fieldData);
			}
		}
		$resultSet = executeQuery("select * from template_text_chunks where template_id = ?",$GLOBALS['gTemplateRow']['template_id']);
		$templateTextChunks = array();
		while ($row = getNextRow($resultSet)) {
			$templateTextChunks[strtolower($row['template_text_chunk_code'])] = $row['content'];
			PlaceHolders::setPlaceholderValue("template_text_chunk:" . strtolower($row['template_text_chunk_code']),$row['content']);
		}
		foreach ($templateTextChunks as $templateTextChunkCode => $content) {
			foreach ($GLOBALS['gTemplateRow'] as $fieldName => $fieldData) {
				$GLOBALS['gTemplateRow'][$fieldName] = str_replace("%template_text_chunk:" . $templateTextChunkCode . "%", $content, $fieldData);
			}
		}
		$GLOBALS['gTemplateRow']['template_text_chunks'] = $templateTextChunks;

		$programTextChunks = getCachedData("program_text", "program_text",true);
		if (!is_array($programTextChunks)) {
			$programTextChunks = array();
			$resultSet = executeReadQuery("select * from program_text");
			while ($row = getNextRow($resultSet)) {
				$programTextChunks[strtolower($row['program_text_code'])] = $row['content'];
			}
			setCachedData("program_text", "program_text", $programTextChunks,24,true);
		}
		foreach ($programTextChunks as $programTextChunkCode => $content) {
			PlaceHolders::setPlaceholderValue("program_text:" . strtolower($programTextChunkCode),$content);
		}
		if (function_exists("massageProgramText")) {
			massageProgramText($programTextChunks);
		}
		if (method_exists($this->iPageObject, "massageProgramText")) {
			$this->iPageObject->massageProgramText($programTextChunks);
		}
		foreach ($programTextChunks as $programTextChunkCode => $content) {
			foreach ($GLOBALS['gTemplateRow'] as $fieldName => $fieldData) {
				$GLOBALS['gTemplateRow'][$fieldName] = str_replace("%program_text:" . $programTextChunkCode . "%", $content, $fieldData);
			}
		}

		$templateContent = $GLOBALS['gTemplateRow']['content'];
		if (empty($GLOBALS['gTemplateRow']['content']) && !empty($GLOBALS['gTemplateRow']['filename'])) {
			$contentFilename = $GLOBALS['gDocumentRoot'] . "/templates/" . (empty($GLOBALS['gTemplateRow']['directory_name']) ? "" : $GLOBALS['gTemplateRow']['directory_name'] . "/") . $GLOBALS['gTemplateRow']['filename'];
			$templateContent = file_get_contents($contentFilename);
		}
		$userHomeLink = $GLOBALS['gUserRow']['link_url'];
		if (empty($userHomeLink)) {
			$userHomeLink = "/";
		}
		$userNotificationCount = 0;
		$resultSet = executeReadQuery("select count(*) from user_notifications where user_id = ? and time_deleted is null and time_read is null", $GLOBALS['gUserId']);
		if ($row = getNextRow($resultSet)) {
			$userNotificationCount = $row['count(*)'];
		}
		if ($userNotificationCount > 0) {
			$userNotificationClass = "notifications-exist";
		} else {
			$userNotificationClass = "";
		}
		$templateLines = getContentLines($templateContent);
		foreach ($templateLines as $index => $thisLine) {
			$thisLine = str_replace("<!-- %", "%", $thisLine);
			$thisLine = str_replace("% -->", "%", $thisLine);
			$templateLines[$index] = $thisLine;
		}
		$templateContent = implode("\n", $templateLines);

		$replacementValues = array("%pageDescription%" => (empty($GLOBALS['gPageRow']['meta_description']) ? $GLOBALS['gPageRow']['description'] : $GLOBALS['gPageRow']['meta_description']),
			"%pageTitle%" => $GLOBALS['gPageRow']['description'], "%currentYear%" => date("Y"), "%currentDate%" => date("m/d/Y"), "%userDisplayName%" => getUserDisplayName(array("include_company" => false)),
			"%pageLinkUrl%" => $GLOBALS['gLinkUrl'], "%userHomeLink%" => $userHomeLink, "%userImageFilename%" => getImageFilename($GLOBALS['gUserRow']['image_id'], array("use_cdn" => true, "default_image" => "/images/person.png")),
			"%userNotificationClass%" => $userNotificationClass, "%userNotificationCount%" => $userNotificationCount);
		$resultSet = executeReadQuery("select * from template_data join template_data_uses using (template_data_id) where template_id = ?", $GLOBALS['gPageTemplateId']);
		while ($row = getNextRow($resultSet)) {
			$replacementValues["%pageData:" . $row['data_name'] . "%"] = $this->iPageObject->getPageData($row['data_name']);
		}
		foreach ($replacementValues as $placeholder => $replaceValue) {
			$templateContent = str_replace($placeholder, $replaceValue, $templateContent);
		}
		$templateLines = getContentLines($templateContent);
		$loggedInOnly = false;
		$notLoggedInOnly = false;
		$validData = true;
		$systemVersion = "";
		if ($GLOBALS['gUserRow']['superuser_flag']) {
            $systemVersion = getSystemVersion(true);
		}
		ob_start();
		foreach ($templateLines as $thisLine) {
			switch ($thisLine) {
				case "%favicon%":
					$favicon = getFragment("MANAGEMENT_FAVICON");
					if (empty($favicon)) {
						ob_start();
						?>
                        <link rel="apple-touch-icon" sizes="57x57"
                              href="/favicon/coreware/favicon/apple-icon-57x57.png">
                        <link rel="apple-touch-icon" sizes="60x60"
                              href="/favicon/coreware/favicon/apple-icon-60x60.png">
                        <link rel="apple-touch-icon" sizes="72x72"
                              href="/favicon/coreware/favicon/apple-icon-72x72.png">
                        <link rel="apple-touch-icon" sizes="76x76"
                              href="/favicon/coreware/favicon/apple-icon-76x76.png">
                        <link rel="apple-touch-icon" sizes="114x114"
                              href="/favicon/coreware/favicon/apple-icon-114x114.png">
                        <link rel="apple-touch-icon" sizes="120x120"
                              href="/favicon/coreware/favicon/apple-icon-120x120.png">
                        <link rel="apple-touch-icon" sizes="144x144"
                              href="/favicon/coreware/favicon/apple-icon-144x144.png">
                        <link rel="apple-touch-icon" sizes="152x152"
                              href="/favicon/coreware/favicon/apple-icon-152x152.png">
                        <link rel="apple-touch-icon" sizes="180x180"
                              href="/favicon/coreware/favicon/apple-icon-180x180.png">
                        <link rel="icon" type="image/png" sizes="192x192"
                              href="/favicon/coreware/favicon/android-icon-192x192.png">
                        <link rel="icon" type="image/png" sizes="32x32"
                              href="/favicon/coreware/favicon/favicon-32x32.png">
                        <link rel="icon" type="image/png" sizes="96x96"
                              href="/favicon/coreware/favicon/favicon-96x96.png">
                        <link rel="icon" type="image/png" sizes="16x16"
                              href="/favicon/coreware/favicon/favicon-16x16.png">
                        <link rel="manifest" href="/favicon/coreware/favicon/manifest.json">
                        <meta name="msapplication-TileColor" content="#ffffff">
                        <meta name="msapplication-TileImage" content="/favicon/coreware/favicon/ms-icon-144x144.png">
                        <meta name="msapplication-config" content="/favicon/coreware/favicon/browserconfig.xml">
                        <meta name="theme-color" content="#ffffff">
						<?php
						$favicon = ob_get_clean();
					}
					echo $favicon;
					break;
				case "%crudIncludes%":
					if ($GLOBALS['gDevelopmentServer']) {
						?>
                        <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/reset.css') ?>"/>
                        <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/jquery-ui.css') ?>"/>
                        <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/validationEngine.jquery.css') ?>"/>
                        <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/prettyPhoto.css') ?>"/>
                        <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/jquery.minicolors.css') ?>"/>
                        <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/jquery.timepicker.css') ?>"/>
						<?php
					} else {
						$mergedFilename = getMergedFilename(array("/css/reset.css", "/css/jquery-ui.css",
							"/css/validationEngine.jquery.css", "/css/prettyPhoto.css", "/css/jquery.minicolors.css", "/css/jquery.timepicker.css"), "css");
						?>
                        <link type="text/css" rel="stylesheet" href="<?= autoVersion($mergedFilename) ?>"/>
						<?php
					}
					?>
					<?php if (strpos($templateContent, "/fontawesome") === false) { ?>
                    <link type="text/css" rel="stylesheet" href="<?= autoVersion('/fontawesome-core/css/all.min.css') ?>" media="screen"/>
				<?php } ?>
                    <link type="text/css" rel="stylesheet" href="/css/dropzone.css" media="screen"/>
                    <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/table_editor.css') ?>"/>
					<?php if (!empty($GLOBALS['gPageRow']['script_filename']) && file_exists($GLOBALS['gDocumentRoot'] . "/css/" . str_replace(".php", ".css", $GLOBALS['gPageRow']['script_filename']))) { ?>
                    <link type="text/css" rel="stylesheet"
                          href="<?= autoVersion('/css/' . str_replace(".php", ".css", $GLOBALS['gPageRow']['script_filename'])) ?>"
                          media="screen"/>
				<?php } ?>

                    <script src="<?= autoVersion("/js/jquery-3.4.0.min.js") ?>"></script>
                    <script src="<?= autoVersion("/js/jquery-migrate-3.0.1.min.js") ?>"></script>
                    <script src="/js/dropzone.js"></script>

                    <script src="/ace/ace.js"></script>
                    <script>
                        var CKEDITOR_BASEPATH = "/ckeditor/";
                        Dropzone.autoDiscover = false;
                    </script>
                    <script src="<?= autoVersion("/ckeditor/ckeditor.js") ?>"></script>
					<?php
					if ($GLOBALS['gDevelopmentServer']) {
						?>
                        <script src="<?= autoVersion("/js/json3.js") ?>"></script>
                        <script src="<?= autoVersion("/js/jquery-ui.js") ?>"></script>
                        <script src="<?= autoVersion("/js/jquery.validationEngine-en.js") ?>"></script>
                        <script src="<?= autoVersion("/js/jquery.validationEngine.js") ?>"></script>
                        <script src="<?= autoVersion("/js/jquery.prettyPhoto.js") ?>"></script>
                        <script src="<?= autoVersion("/js/jquery.cookie.js") ?>"></script>
                        <script src="<?= autoVersion("/js/jquery.minicolors.js") ?>"></script>
                        <script src="<?= autoVersion("/js/jquery.timepicker.js") ?>"></script>
                        <script src="<?= autoVersion("/js/general.js") ?>"></script>
                        <script src="<?= autoVersion("/js/editablelist.js") ?>"></script>
                        <script src="<?= autoVersion("/js/multipleselect.js") ?>"></script>
                        <script src="<?= autoVersion("/js/multipledropdown.js") ?>"></script>
                        <script src="<?= autoVersion("/js/checkboxlinks.js") ?>"></script>
                        <script src="<?= autoVersion("/js/flowtype.js") ?>"></script>
                        <script src="<?= autoVersion("/js/formlist.js") ?>"></script>
                        <script src="<?= autoVersion("/js/table_editor.js") ?>"></script>
						<?php
					} else {
						$mergedFilename = getMergedFilename(array("/js/json3.js", "/js/jquery-ui.js", "/js/jquery.validationEngine-en.js", "/js/jquery.validationEngine.js",
							"/js/jquery.prettyPhoto.js", "/js/jquery.cookie.js", "/js/jquery.minicolors.js", "/js/jquery.timepicker.js", "/js/general.js", "/js/editablelist.js",
							"/js/multipleselect.js", "/js/multipledropdown.js", "/js/checkboxlinks.js", "/js/flowtype.js", "/js/formlist.js", "/js/table_editor.js"));
						?>
                        <script src="<?= autoVersion($mergedFilename) ?>"></script>
						<?php
					}
					?>

					<?php if (!empty($GLOBALS['gPageRow']['script_filename']) && file_exists($GLOBALS['gDocumentRoot'] . "/js/" . str_replace(".php", ".js", $GLOBALS['gPageRow']['script_filename']))) { ?>
                    <script src="<?= autoVersion('/js/' . str_replace(".php", ".js", $GLOBALS['gPageRow']['script_filename'])) ?>"></script>
				<?php } ?>
					<?php if (!empty($GLOBALS['gClientRow']['client_code']) && file_exists($GLOBALS['gDocumentRoot'] . "/js/" . strtolower($GLOBALS['gClientRow']['client_code']) . ".js")) { ?>
                    <script src="<?= autoVersion('/js/' . strtolower($GLOBALS['gClientRow']['client_code']) . ".js") ?>"></script>
				<?php } ?>
					<?php if (file_exists($GLOBALS['gDocumentRoot'] . "/js/table_editor_local.js")) { ?>
                    <script src="<?= autoVersion('/js/table_editor_local.js') ?>"></script>
				<?php } ?>
					<?php
					break;
				case "%crudElements%":
					if ($this->iTableEditorObject) {
						$this->iTableEditorObject->pageElements();
					}
					break;
				case "%metaKeywords%":
					if (!empty($GLOBALS['gPageRow']['meta_keywords'])) {
						?>
                        <meta name="Keywords"
                              content="<?= str_replace("\"", "'", $GLOBALS['gPageRow']['meta_keywords']) ?>">
						<?php
					}
					break;
				case "%metaDescription%":
					if (!empty($GLOBALS['gPageRow']['meta_description'])) {
						?>
                        <meta name="Description"
                              content="<?= str_replace("\"", "'", $GLOBALS['gPageRow']['meta_description']) ?>">
						<?php
					}
					break;
				case "%getPageTitle%":
					echo "<title>" . $this->getPageTitle() . "</title>\n";
					break;
				case "%headerIncludes%":
					$this->headerIncludes();
					break;
				case "%getAnalyticsCode%":
					echo $this->getAnalyticsCode();
					break;
				case "%onLoadJavascript%":
					break;
				case "%crudJavascript%":
					break;
				case "%javascript%":
					ob_start();
					if ($this->iTableEditorObject) {
						if (method_exists($this->iTableEditorObject, "inlinePageJavascript")) {
							$this->iTableEditorObject->inlinePageJavascript();
							$inlineJavascript = ob_get_clean();
							$holdJavascriptLines = getContentLines($inlineJavascript);
							$javascriptLines = array();
							foreach ($holdJavascriptLines as $thisJavascriptLine) {
								if (!startsWith($thisJavascriptLine, "<!--suppress")) {
									$javascriptLines[] = $thisJavascriptLine;
								}
							}
							$startTag = "<script>";
							$endTag = "</script>";
							if (count($javascriptLines) > 0) {
								if (strpos($javascriptLines[count($javascriptLines) - 1], "</script") !== false) {
									$endTag = array_pop($javascriptLines);
								}
								if (strpos($javascriptLines[0], "<script") !== false) {
									$startTag = array_shift($javascriptLines);
								}
							}
							$inlineJavascript = $startTag . "\n" . implode("\n", $javascriptLines) . "\n" . $endTag . "\n";
							echo $inlineJavascript;
						}
					}
					if (method_exists($this->iPageObject, "inlineJavascript")) {
						$this->iPageObject->inlineJavascript();
						$inlineJavascript = ob_get_clean();
						$holdJavascriptLines = getContentLines($inlineJavascript);
						$javascriptLines = array();
						foreach ($holdJavascriptLines as $thisJavascriptLine) {
							if (!startsWith($thisJavascriptLine, "<!--suppress")) {
								$javascriptLines[] = $thisJavascriptLine;
							}
						}
						$startTag = "<script>";
						$endTag = "</script>";
						if (count($javascriptLines) > 0) {
							if (strpos($javascriptLines[count($javascriptLines) - 1], "</script") !== false) {
								$endTag = array_pop($javascriptLines);
							}
							if (strpos($javascriptLines[0], "<script") !== false) {
								$startTag = array_shift($javascriptLines);
							}
						}
						$inlineJavascript = $startTag . "\n" . implode("\n", $javascriptLines) . "\n" . $endTag . "\n";
						echo $inlineJavascript;
					}
					$logoLink = str_replace("%user_id%", $GLOBALS['gUserId'], getPreference("ADMIN_LOGO_LINK"));
					?>
                    <script>
                        var developmentServer = <?= ($GLOBALS['gDevelopmentServer'] ? "true" : "false") ?>;
                        var displayErrors = <?= ($GLOBALS['gUserRow']['superuser_flag'] ? "true" : "false") ?>;
                        var scriptFilename = "<?= $GLOBALS['gLinkUrl'] ?>";
                        var userLoggedIn = <?= $GLOBALS['gLoggedIn'] ? "true" : "false" ?>;
                        var adminLoggedIn = <?= (!empty($GLOBALS['gUserRow']['administrator_flag']) ? "true" : "false") ?>;
                        var logoLink = "<?= $logoLink ?>";
						<?php if (!empty($GLOBALS['gDefaultAjaxTimeout']) && is_numeric($GLOBALS['gDefaultAjaxTimeout'])) { ?>
                        setTimeout(function() {
                            gDefaultAjaxTimeout = <?= $GLOBALS['gDefaultAjaxTimeout'] ?>;
                        },500)
						<?php } ?>
                    </script>
					<?php
					$templateContent = ob_get_clean();
					ob_start();
					?>

                    $(function() {
					<?php if (!empty($_SESSION['original_user_id'])) { ?>
                    $(".page-logout").click(function(event) {
                    event.stopPropagation();
                    if (scriptFilename == "reset-password") {
                    scriptFilename = "resetpassword.php";
                    }
                    loadAjaxRequest(scriptFilename + "?ajax=true&url_action=admin_user_return",function(returnArray){
                    document.location = "/";
                    });
                    });
				<?php } ?>
                    });

                    function saveChanges(afterFunction,regardlessFunction) {
                    for (instance in CKEDITOR.instances) {
                    CKEDITOR.instances[instance].updateElement();
                    }
                    if ($("#_edit_form").validationEngine('validate')) {
                    loadAjaxRequest(scriptFilename + "?ajax=true&url_action=save_changes",$("#_edit_form").serialize(),function(returnArray){
                    if ("error_message" in returnArray) {
                    regardlessFunction();
                    } else {
                    displayInfoMessage("<?= getSystemMessage("save_success") ?>");
                    afterFunction();
                    }
                    });
                    } else {
                    regardlessFunction();
                    }
                    }

					<?php if ($GLOBALS['gDevelopmentServer'] || $GLOBALS['gUserRow']['superuser_flag']) { ?>
                    function showError(errorText) {
                    console.log(errorText);
                    console.log(new Error().stack);
                    $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                    displayErrorMessage("Errors in console log");
                    }
				<?php } ?>
					<?php
					$this->onLoadJavascript();
					if (!empty($GLOBALS['gTemplateRow']['javascript_code'])) {
						echo "<script>\n" . $GLOBALS['gTemplateRow']['javascript_code'] . "\n</script>\n";
					}
					$this->javascript();
					if (!empty($systemVersion)) {
						?>
                        $(function() {
                        $("#_system_version").html("<?= ($GLOBALS['gDevelopmentServer'] ? "<span class='red-text'>Development, </span>" : "") . $systemVersion ?>");
                        });
						<?php
					}
					$fullPageJavascript = ob_get_clean();
					ob_start();
					echo $templateContent;
					$fullPageJavascript = str_replace("<script>", "", $fullPageJavascript);
					$fullPageJavascript = str_replace("</script>", "", $fullPageJavascript);
					if ($GLOBALS['gLocalExecution']) {
						echo "<script>\n";
						echo $fullPageJavascript . "\n";
						echo "</script>\n";
					} else {
						$mergedFilename = getMergedFilename($fullPageJavascript, "js")
						?>
                        <script src="<?= autoVersion($mergedFilename) ?>"></script>
						<?php
					}
					$subsystemString = "";
					$resultSet = executeQuery("select * from client_subsystems join subsystems using (subsystem_id) where client_id = ?", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$subsystemString .= (empty($subsystemString) ? "" : ",") . str_replace("core_", "", strtolower($row['subsystem_code']));
					}
					if ($GLOBALS['gDevelopmentServer'] || empty($GLOBALS['gUserRow']['administrator_flag'])) {
						?>
                        <script>
                            $(function () {
                                $("#_whats_new_label").remove();
                            });
                        </script>
						<?php
					} else {
						?>
                        <script>
	                        setTimeout(function() {
                                window.announcekit = (window.announcekit || {
                                    queue: [], on: function (n, x) {
                                        window.announcekit.queue.push([ n, x ]);
                                    }, push: function (x) {
                                        window.announcekit.queue.push(x);
                                    }
                                });

                                window.announcekit.push({
                                    "widget": "https://announcekit.app/widget/jkEbC",
                                    "selector": ".announcekit-widget",
                                    "data": {
                                        "user-type": "<?= $subsystemString ?>",
                                        "user_id": "<?= $GLOBALS['gUserId'] ?>",
                                        "user_email": "<?= $GLOBALS['gUserRow']['email_address'] ?>",
                                        "user_name": "<?= $GLOBALS['gUserRow']['display_name'] ?>"
                                    },
                                    "version": 2
                                })
	                        },500);
                        </script>
                        <script async src="https://cdn.announcekit.app/widget.js"></script>
						<?php
					}
					break;
				case "%internalCSS%":
					if (!empty($GLOBALS['gTemplateRow']['css_file_id'])) {
						$cssFilename = createCSSFile($GLOBALS['gTemplateRow']['css_file_id']);
						?>
                        <link type="text/css" rel="stylesheet" href="<?= autoVersion($cssFilename) ?>"/>
						<?php
					}
					$templateContent = ob_get_clean();
					ob_start();
					if (!empty($GLOBALS['gTemplateRow']['css_content'])) {
						echo "<style>\n/* Template CSS */\n" . processCssContent(getSassHeaders($GLOBALS['gTemplateRow']['template_id']) . $GLOBALS['gTemplateRow']['css_content']) . "\n</style>\n";
					}

					if ($GLOBALS['gTemplateRow']['template_code'] == "MANAGEMENT") {
						$managementColor = getFragment("MANAGEMENT_COLOR");
						if (!empty($managementColor)) {
							if (strlen($managementColor) < 8) {
								$rgbArray = hex2rgb($managementColor);
								$managementColor = "rgb(" . $rgbArray[0] . "," . $rgbArray[1] . "," . $rgbArray[2] . ")";
							}
							$managementColorLight = str_replace(")", ",.1)", str_replace("rgb(", "rgba(", $managementColor));
							$colorOverride = getFieldFromId("content", "template_text_chunks", "template_id", $GLOBALS['gTemplateRow']['template_id'], "template_text_chunk_code = 'COLOR_OVERRIDE'");
							if (!empty($colorOverride)) {
								$colorOverride = str_replace("%management_color%", $managementColor, $colorOverride);
								$colorOverride = str_replace("%management_color_light%", $managementColorLight, $colorOverride);
								echo "<style>\n/* Management Color Override CSS */\n" . processCssContent($colorOverride) . "\n</style>\n";
							}
						}

						$cssFileContent = getFieldFromId("content", "css_files", "css_file_code", "MANAGEMENT");
						if (!empty($cssFileContent)) {
							echo "<style>\n/* Template Override CSS */\n" . processCssContent($cssFileContent) . "\n</style>\n";
						}
					}
					$this->internalCSS();
					$fullPageCSS = ob_get_clean();
					ob_start();
					echo $templateContent;
					if (strpos($fullPageCSS, "<style ") !== false) {
						echo $fullPageCSS;
					} else {
						$fullPageCSS = str_replace("<style>", "", $fullPageCSS);
						$fullPageCSS = str_replace("</style>", "", $fullPageCSS);
						$fullPageCSS = $this->iPageObject->replaceImageReferences($fullPageCSS);
						if ($GLOBALS['gLocalExecution']) {
							?>
                            <style>
                                <?= $fullPageCSS ?>
                            </style>
							<?php
						} else {
							$mergedFilename = getMergedFilename($fullPageCSS, "css")
							?>
                            <link type="text/css" rel="stylesheet" href="<?= autoVersion($mergedFilename) ?>"/>
							<?php
						}
					}
					break;
				case "%mainContent%":
					$this->mainContent();
					break;
				case "%hiddenElements%":
					if ($GLOBALS['gUserRow']['superuser_flag']) {
						?>
                        <input type="hidden" id="_superuser_logged_in" value="1">
						<?php
					}
					$this->hiddenElements();
					?>
                    <div class="modal"><span class="fad fa-spinner fa-spin"></span></div>

                    <div id="_save_changes_dialog" class="dialog-box" data-keypress_added="false">
                        Do you want to save changes?
                    </div> <!-- save_changes_dialog -->

                    <div id="_changes_dialog" class="dialog-box">
                        <div id="_changes_table">
                        </div>
                    </div>

                    <div id="_confirm_delete_dialog" class="dialog-box">
						<p class='dialog-text'>Are you sure you want to <span id="_delete_tag">delete</span> this record?</p>
                    </div> <!-- confirm_delete_dialog -->

                    <div id="_page_help" class="dialog-box">
                    </div> <!-- page_help -->

					<?php
					if ($this->iTableEditorObject) {
						$this->iTableEditorObject->pageElements();
					}
					break;
				case "%jqueryTemplates%":
					$this->jqueryTemplates();
					break;
				case "%postIframe%":
					break;
				case "%endif%":
					$loggedInOnly = false;
					$notLoggedInOnly = false;
					$validData = true;
					break;
				case "%ifLoggedIn%":
					$loggedInOnly = true;
					$notLoggedInOnly = false;
					$validData = true;
					break;
				case "%ifNotLoggedIn%":
					$loggedInOnly = false;
					$notLoggedInOnly = true;
					$validData = true;
					break;
				default:
					if (startsWith($thisLine, "%pageData:")) {
						;
					} else {
						if (startsWith($thisLine, "%ifPageData:")) {
							$pageDataCode = substr($thisLine, strlen("%ifPageData:"), -1);
							$thisData = $this->iPageObject->getPageData($pageDataCode);
							$validData = !empty($thisData);
						} else {
							if (startsWith($thisLine, "%ifNotPageData:")) {
								$pageDataCode = substr($thisLine, strlen("%ifNotPageData:"), -1);
								$thisData = $this->iPageObject->getPageData($pageDataCode);
								$validData = empty($thisData);
							} else {
								if (startsWith($thisLine, "%method:")) {
									$methodName = substr($thisLine, strlen("%method:"));
									if (substr($methodName, -1) == "%") {
										$methodName = substr($methodName, 0, -1);
									}
									$parts = explode(":", $methodName);
									if (count($parts) > 1) {
										$methodName = array_shift($parts);
									} else {
										$parts = array();
									}
									if (!empty($this->iPageObject->iTemplateAddendumObject) && method_exists($this->iPageObject->iTemplateAddendumObject, $methodName)) {
										if (!empty($parts)) {
											call_user_func(array($this->iPageObject->iTemplateAddendumObject, $methodName), $parts);
										} else {
											call_user_func(array($this->iPageObject->iTemplateAddendumObject, $methodName));
										}
									}
								} else {
									if (startsWith($thisLine, "%module:")) {
										$methodName = substr($thisLine, strlen("%module:"));
										if (substr($methodName, -1) == "%") {
											$methodName = substr($methodName, 0, -1);
										}
										$parts = explode(":", $methodName);
										if (count($parts) > 1) {
											$methodName = array_shift($parts);
										}
										$pageModule = PageModule::getPageModuleInstance($methodName);
										if (!empty($pageModule)) {
											$pageModule->setParameters($parts);
											$pageModule->displayContent();
										}
									} else {
										if (startsWith($thisLine, "%cssFileCode:")) {
											$cssFileCode = getFieldFromId("css_file_code", "css_files", "css_file_code", substr($thisLine, strlen("%cssFileCode:"), -1));
											if (!empty($cssFileCode)) {
												?>
                                                <link type="text/css" rel="stylesheet"
                                                      href="<?= autoVersion(getCSSFilename($cssFileCode)) ?>"/>
												<?php
											}
										} else {
											if (startsWith($thisLine, "%cssFile:")) {
												$cssFilename = substr($thisLine, strlen("%cssFile:"), -1);
												if (strpos($cssFilename, "..") === false) {
													?>
                                                    <link type="text/css" rel="stylesheet" href="<?= autoVersion($cssFilename) ?>"/>
													<?php
												}
											} else {
												if (startsWith($thisLine, "%javascriptFile:")) {
													$javascriptFilename = substr($thisLine, strlen("%javascriptFile:"), -1);
													if (strpos($javascriptFilename, "..") === false) {
														?>
                                                        <script src="<?= autoVersion($javascriptFilename) ?>"></script>
														<?php
													}
												} else {
													if (startsWith($thisLine, "%getMenuByCode:")) {
														$menuInfo = substr($thisLine, strlen("%getMenuByCode:"), -1);
														$menuInfoParts = explode(",", $menuInfo);
														$menuCode = "";
														$menuParameters = array();
														foreach ($menuInfoParts as $thisPart) {
															if (empty($menuCode)) {
																$menuCode = trim($thisPart);
															} else {
																$thisPartParts = explode("=", $thisPart, 2);
																$menuParameters[$thisPartParts[0]] = $thisPartParts[1];
															}
														}
														echo getMenuByCode($menuCode, $menuParameters);
													} else {
														if (startsWith($thisLine, "%getMenu:")) {
															$menuInfo = substr($thisLine, strlen("%getMenu:"), -1);
															$menuInfoParts = explode(",", $menuInfo);
															$menuId = "";
															$menuParameters = array();
															foreach ($menuInfoParts as $thisPart) {
																if (empty($menuId)) {
																	$menuId = $thisPart;
																} else {
																	$thisPartParts = explode("=", $thisPart, 2);
																	$menuParameters[$thisPartParts[0]] = $thisPartParts[1];
																}
															}
															echo getMenu($menuId, $menuParameters);
														} else {
															if ($validData && ((!$loggedInOnly && !$notLoggedInOnly) || ($GLOBALS['gLoggedIn'] && $loggedInOnly) || (!$GLOBALS['gLoggedIn'] && $notLoggedInOnly))) {
																echo $thisLine . "\n";
															}
														}
													}
												}
											}
										}
									}
								}
							}
						}
					}
					break;
			}
		}
		$templateContent = ob_get_clean();
		$templateContent = $this->iPageObject->replaceImageReferences($templateContent);
		echo $templateContent;
	}

	function headerIncludes() {
		if (!$this->iPageObject->headerIncludes()) {
			$resultSet = executeReadQuery("select * from page_meta_tags where page_id = ?", $GLOBALS['gPageRow']['page_id']);
			$propertyArray = array();
			while ($row = getNextRow($resultSet)) {
				if (!empty($row['content'])) {
					$propertyArray[] = $row['meta_value'];
					?>
                    <meta <?= $row['meta_name'] ?>="<?= $row['meta_value'] ?>" content="<?= str_replace("\"", "'", str_replace("\n", " ", $row['content'])) ?>" />
					<?php
				}
			}
		}
		echo $GLOBALS['gPageRow']['header_includes'];
		echo $GLOBALS['gCanonicalLink'] . "\n";
		if (empty($GLOBALS['gCanonicalLink']) && strpos($GLOBALS['gPageRow']['header_includes'], "canonical") === false) {
			echo "<link rel='canonical' href='https://" . str_replace("'","", $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) . "'>\n";
		}
		$cssFileCode = getFieldFromId("css_file_code", "css_files", "css_file_code", $GLOBALS['gPageRow']['css_file_id']);
		if (!empty($cssFileCode)) {
			?>
            <link type="text/css" rel="stylesheet" href="<?= autoVersion(getCSSFilename($cssFileCode)) ?>"/>
			<?php
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(function () {
                $(".page-heading").html("<?= htmlText($GLOBALS['gPageRow']['description']) ?>");
                $(document).on("click", ".remove-user-menu", function (event) {
                    const userMenuId = $(this).closest("li").data("user_menu_id");
                    loadAjaxRequest(scriptFilename + "?ajax=true&url_action=remove_user_menu&user_menu_id=" + userMenuId);
                    $(this).closest("li").remove();
                    sessionStorage.clear();
                    event.stopPropagation();
                });
            });
        </script>
		<?php
		if ($this->iTableEditorObject) {
			$this->iTableEditorObject->onLoadPageJavascript();
		} else {
			$this->iPageObject->onLoadPageJavascript();
		}
	}

	function javascript() {
		if ($this->iTableEditorObject) {
			$this->iTableEditorObject->pageJavascript();
		} else {
			if (function_exists("_localServerJavascript")) {
				_localServerJavascript();
			}
			$this->iPageObject->pageJavascript();
		}
	}

	function internalCSS() {
		if ($this->iTableEditorObject) {
			$this->iTableEditorObject->internalPageCSS();
		} else {
			$this->iPageObject->internalPageCSS();
		}
	}

	function mainContent() {
		if (Page::pageIsUnderMaintenance()) {
			return;
		}
		if (!$this->iPageObject->mainContent()) {
			if ($this->iTableEditorObject) {
				echo $this->iPageObject->getPageData("content");
				$this->iTableEditorObject->mainContent();
				echo $this->iPageObject->getPageData("after_form_content");
			} else {
				echo $this->iPageObject->getPageData("content");
				echo $this->iPageObject->getPageData("after_form_content");
			}
		}
	}

	function hiddenElements() {
		if (!$this->iPageObject->hiddenElements() && $this->iTableEditorObject) {
			$this->iTableEditorObject->hiddenElements();
		}
	}

	function jqueryTemplates() {
		?>
        <div id="_templates">
			<?php
			if (!$this->iPageObject->jqueryTemplates() && $this->iTableEditorObject) {
				$this->iTableEditorObject->jqueryTemplates();
			}
			?>
        </div>
		<?php
	}
}
