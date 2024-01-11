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
 * class DataTable
 *
 * DataTable represents a table in the database. It contains functions to manipulate the table, including save and delete.
 *    The class uses reflection to get as much information as possible about the structure of the table.
 *
 * @author Kim D Geiger
 */
class DataTable {
	private static $iTableUniqueKeys = false;
	private static $iTableActions = false;
	private static $iNeverLogChanges = array("shopping_carts","shopping_cart_items","product_facet_values");
	private $iDatabase = null;
	private $iTableName = "";
	private $iPrimaryKey = "";
	private $iColumns = array();
	private $iUniqueKeys = array();
	private $iErrorMessage = "";
	private $iSqlErrorNumber = false;
	private $iSubTables = array();
	private $iLimitByClient = false;
	private $iForceNoLimitByClient = false;
	private $iSaveOnlyPresent = false;
	private $iPrimaryId = "";
	private $iExcludeUpdateColumns = array();
	private $iIgnoreVersion = false;
	private $iColumnsChanged = 0;

	/**
	 * Construct - When creating the class, you must set the primary table
	 * @param
	 *    $tableName - name of the table for which this data source is being constructed
	 * @return
	 *    none
	 */
	function __construct($tableName, $database = "") {
		if (self::$iTableUniqueKeys === false) {
			self::$iTableUniqueKeys = array();
		}
		if (empty($database)) {
			$this->iDatabase = $GLOBALS['gPrimaryDatabase'];
		} else {
			$this->iDatabase = $database;
		}
		if (!$this->iDatabase->tableExists($tableName)) {
			throw new Exception('Table not found: ' . $tableName);
		}
		$this->iTableName = $tableName;
		$columnInformation = $this->iDatabase->getTableColumns($this->iTableName);
		foreach ($columnInformation as $row) {
			if ($row['COLUMN_KEY'] == "PRI") {
				$this->iPrimaryKey = $row['COLUMN_NAME'];
				break;
			}
		}
		$this->getColumnInformation();
		foreach ($this->iColumns as $columnName => $thisColumn) {
			if ($thisColumn->getControlValue('foreign_key')) {
				$foreignKeys = $this->getForeignKeyList();
				if ($thisColumn->getControlValue('subtype')) {
					$thisColumn->setControlValue("data_type", $thisColumn->getControlValue('subtype'));
				} else {
					if (array_key_exists($thisColumn->getControlValue("column_name"), $GLOBALS['gAutocompleteFields'])) {
						$thisColumn->setControlValue("data_type", "autocomplete");
						$thisColumn->setControlValue("data-autocomplete_tag", $thisColumn->getReferencedTable());
					} else {
						$thisColumn->setControlValue("data_type", "select");
					}
				}
			}
		}
		if (array_key_exists($this->iTableName . ".client_id", $this->iColumns)) {
			$this->iLimitByClient = true;
			$this->iColumns[$this->iTableName . '.client_id']->setControlValue("default_value", $GLOBALS['gClientId']);
		}
	}

	/**
	 * getColumnInformation - Private method that gathers data about the primary table's columns
	 * @param
	 *    none
	 * @return
	 *    none
	 */
	private function getColumnInformation() {
		$this->iColumns = array();
		$columnInformation = $this->iDatabase->getTableColumns($this->iTableName);
		foreach ($columnInformation as $row) {
			$this->iColumns[$this->iTableName . "." . $row['COLUMN_NAME']] = new DataColumn($row['COLUMN_NAME'], $this->iTableName);
			if ($row['COLUMN_NAME'] == $this->iPrimaryKey) {
				$this->iColumns[$this->iTableName . "." . $row['COLUMN_NAME']]->setControlValue("primary_key", true);
			}
		}
		if (!array_key_exists($this->iTableName, self::$iTableUniqueKeys)) {
			self::$iTableUniqueKeys[$this->iTableName] = getCachedData("table_unique_keys", $this->iDatabase->getName() . "-" . $this->iTableName, true);
		}
		if (!array_key_exists($this->iTableName, self::$iTableUniqueKeys) || self::$iTableUniqueKeys[$this->iTableName] === false || !is_array(self::$iTableUniqueKeys[$this->iTableName])) {
			self::$iTableUniqueKeys[$this->iTableName] = array();
			$resultSet = executeReadQuery("select * from information_schema.table_constraints where table_schema = ? and table_name = ? and constraint_type = 'UNIQUE'",
				$this->iDatabase->getName(), $this->iTableName);
			while ($row = $this->iDatabase->getNextRow($resultSet)) {
				self::$iTableUniqueKeys[$this->iTableName][] = $row;
			}
			freeResult($resultSet);
			setCachedData("table_unique_keys", $this->iDatabase->getName() . "-" . $this->iTableName, self::$iTableUniqueKeys[$this->iTableName], 24, true);
		}

		if (empty($GLOBALS['gKeyColumnUsage']) || !is_array($GLOBALS['gKeyColumnUsage'])) {
			$GLOBALS['gKeyColumnUsage'] = array();
		}
		$tableKey = $this->iDatabase->getName() . "." . $this->iTableName;
		if (!array_key_exists($tableKey,$GLOBALS['gKeyColumnUsage'])) {
			$GLOBALS['gKeyColumnUsage'][$tableKey] = getCachedData("all_key_column_usage", $tableKey, true);
			if (empty($GLOBALS['gKeyColumnUsage'][$tableKey]) || !is_array($GLOBALS['gKeyColumnUsage'][$tableKey])) {
				$GLOBALS['gKeyColumnUsage'][$tableKey] = array();
				$resultSet = executeReadQuery("select * from information_schema.KEY_COLUMN_USAGE where table_schema = ? and table_name = ? order by ordinal_position", $this->iDatabase->getName(), $this->iTableName);
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['COLUMN_NAME'], $GLOBALS['gKeyColumnUsage'][$tableKey])) {
						$GLOBALS['gKeyColumnUsage'][$tableKey][$row['COLUMN_NAME']] = array();
					}
					$GLOBALS['gKeyColumnUsage'][$tableKey][$row['COLUMN_NAME']][] = $row;
				}
				freeResult($resultSet);
				setCachedData("all_key_column_usage", $tableKey, $GLOBALS['gKeyColumnUsage'][$tableKey], 168, true);
			}
		}
		$this->iUniqueKeys = array();
		foreach (self::$iTableUniqueKeys[$this->iTableName] as $row) {
			$columnList = array();
			foreach ($GLOBALS['gKeyColumnUsage'][$tableKey] as $columnRows) {
				foreach ($columnRows as $row1) {
					if ($row1['CONSTRAINT_NAME'] != $row['CONSTRAINT_NAME']) {
						continue;
					}
					$columnList[] = $row1['COLUMN_NAME'];
				}
			}
			$this->iUniqueKeys[$row['CONSTRAINT_NAME']] = $columnList;
		}
	}

	/**
	 * getForeignKeyList - Return list of foreign keys. This will come from the column meta data
	 * @param
	 *    none
	 * @return
	 *    array of foreign keys
	 */
	function getForeignKeyList() {
		$foreignKeys = array();
		foreach ($this->iColumns as $columnName => $thisColumn) {
			if ($thisColumn->isForeignKey()) {
				$foreignKeys[$thisColumn->getFullName()] = array("table_name" => $this->iTableName,
					"column_name" => $thisColumn->getName(),
					"referenced_table_name" => $thisColumn->getReferencedTable(),
					"referenced_column_name" => $thisColumn->getReferencedColumn(),
					"description" => $thisColumn->getReferencedDescriptionColumns());

			}
		}
		return $foreignKeys;
	}

	public static function setLinkNames($tableName) {
		$tableId = getFieldFromId("table_id", "tables", "table_name", $tableName);
		$primaryKeyName = getFieldFromId("column_name", "column_definitions", "column_definition_id",
			getFieldFromId("column_definition_id", "table_columns", "table_id", $tableId, "primary_table_key = 1"));
		$linkNameColumnId = getFieldFromId("table_column_id", "table_columns", "column_definition_id",
			getFieldFromId("column_definition_id", "column_definitions", "column_name", "link_name"), "table_id = ?", $tableId);
		if (empty($linkNameColumnId)) {
			return array("error_message" => "Link Name column does not exist");
		}
		$selectedIds = array();
		$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
		while ($row = getNextRow($resultSet)) {
			$selectedIds[] = $row['primary_identifier'];
		}
		$count = 0;
		$failedCount = 0;
		if (!empty($selectedIds)) {
			$dataTable = new DataTable($tableName);
			$dataTable->setSaveOnlyPresent(true);
			$resultSet = executeQuery("select * from " . $tableName . " where " . $primaryKeyName . " in (" . implode(",", $selectedIds) . ")");
			$successIds = array();
			while ($row = getNextRow($resultSet)) {
				$nameValues = array("link_name" => makeCode($row['description'], array("use_dash" => true, "lowercase" => true)));
				$duplicateId = getFieldFromId($primaryKeyName, $tableName, "link_name", $nameValues['link_name']);
				if (empty($duplicateId) && $dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $row[$primaryKeyName]))) {
					$count++;
					$successIds[] = $row[$primaryKeyName];
				} else {
					$failedCount++;
				}
			}
			executeQuery("delete from selected_rows where page_id = ? and user_id = ? and primary_identifier in (" . implode(",", $successIds) . ")", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
		}
		$infoMessage = "Link name set for " . $count . " records.";
		if ($failedCount > 0) {
			$infoMessage .= " " . $failedCount . " link names could not be set.  Check for duplicate descriptions.";
		}
		return array("info_message" => $infoMessage);
	}

	public static function setInactive($tableName, $inactive = true) {
		$tableId = getFieldFromId("table_id", "tables", "table_name", $tableName);
		$primaryKeyName = getFieldFromId("column_name", "column_definitions", "column_definition_id",
			getFieldFromId("column_definition_id", "table_columns", "table_id", $tableId, "primary_table_key = 1"));
		$selectedIds = array();
		$resultSet = executeQuery("select primary_identifier from selected_rows where page_id = ? and user_id = ?", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
		while ($row = getNextRow($resultSet)) {
			$selectedIds[] = $row['primary_identifier'];
		}
		$count = 0;
		$failedCount = 0;
		if (!empty($selectedIds)) {
			$dataTable = new DataTable($tableName);
			$dataTable->setSaveOnlyPresent(true);
			$resultSet = executeQuery("select * from " . $tableName . " where " . $primaryKeyName . " in (" . implode(",", $selectedIds) . ")");
			$successIds = array();
			while ($row = getNextRow($resultSet)) {
				if ($dataTable->saveRecord(array("name_values" => array("inactive" => $inactive), "primary_id" => $row[$primaryKeyName]))) {
					$count++;
					$successIds[] = $row[$primaryKeyName];
				} else {
					$failedCount++;
				}
			}
			executeQuery("delete from selected_rows where page_id = ? and user_id = ? and primary_identifier in (" . implode(",", $successIds) . ")", $GLOBALS['gPageId'], $GLOBALS['gUserId']);
		}
		$infoMessage = $count . " records set to " . ($inactive ? "" : "not ") . " inactive.";
		if ($failedCount > 0) {
			$infoMessage .= " " . $failedCount . " records could not be changed.";
		}
		return array("info_message" => $infoMessage);
	}

	/**
	 * setSaveOnlyPresent - Set whether the save function should only update columns that are present in the name/value pair array
	 *
	 * @param true or false
	 */
	function setSaveOnlyPresent($saveOnlyPresent) {
		$this->iSaveOnlyPresent = $saveOnlyPresent;
	}

	/**
	 * saveRecord - Save the data to the database, based on the parameters passed in. If the field "version" is in the name/value pairs, it will
	 *    be used for optimistic locking
	 * @param
	 *    Parameter List:
	 *        table_name: table name of table. Default: primary table.
	 *        primary_id: key of row to be updated in database. Empty value means the row is to be added. Default: primary ID.
	 *        name_values: array of name/value pairs
	 * @return
	 *    false if there is any kind of error
	 *    the ID of the record added or updated if it is successful
	 */
	function saveRecord($parameterList = array()) {
		$this->iColumnsChanged = 0;
		if (!array_key_exists("name_values", $parameterList) || !is_array($parameterList['name_values'])) {
			$this->iErrorMessage = getSystemMessage("NO_NAME_VALUES");
			return false;
		}
		if (!array_key_exists("primary_id", $parameterList)) {
			$parameterList['primary_id'] = $this->iPrimaryId;
		}
		if (in_array($this->iTableName,self::$iNeverLogChanges)) {
			$parameterList['no_change_log'] = true;
		}
		$primaryId = $parameterList['primary_id'];

# If it is a new record, set the defaults

		if (empty($primaryId)) {
			foreach ($this->iColumns as $columnName => $thisColumn) {
				if ($thisColumn->getControlValue("data_type") != "tinyint" &&
					(!array_key_exists($thisColumn->getControlValue('column_name'), $parameterList['name_values']) ||
                        !is_scalar($parameterList['name_values'][$thisColumn->getControlValue('column_name')]) ||
						strlen($parameterList['name_values'][$thisColumn->getControlValue('column_name')]) == 0)) {
					$defaultValue = $thisColumn->getControlValue('default_value');
					if ($thisColumn->getControlValue("data_type") == "date" && $defaultValue == "now") {
						$defaultValue = date("Y-m-d");
					} else if ($thisColumn->getControlValue("data_type") == "datetime" && $defaultValue == "now") {
						$defaultValue = date("Y-m-d H:i:s");
					}
					$parameterList['name_values'][$thisColumn->getControlValue('column_name')] = $defaultValue;
				}

				$databaseMetadata = $thisColumn->getControlValue("database_metadata");
				if (is_array($databaseMetadata) && $databaseMetadata['IS_NULLABLE'] == "NO" && !empty($databaseMetadata['COLUMN_DEFAULT']) && $thisColumn->getControlValue("data_type") != "tinyint" && strlen($parameterList['name_values'][$thisColumn->getControlValue('column_name')]) == 0) {
					$defaultValue = $databaseMetadata['COLUMN_DEFAULT'];
					if ($databaseMetadata['DATA_TYPE'] == "date" && $defaultValue == "CURRENT_TIMESTAMP") {
						$defaultValue = date("Y-m-d");
					} else if ($databaseMetadata['DATA_TYPE'] == "datetime" && $defaultValue == "CURRENT_TIMESTAMP") {
						$defaultValue = date("Y-m-d H:i:s");
					}
					$parameterList['name_values'][$thisColumn->getControlValue('column_name')] = $defaultValue;
				}
			}
		}

# if updating, get the old data so we can add rows to change log

		if (!empty($primaryId)) {
			$resultSet = $this->iDatabase->executeQuery("select * from " . $this->iTableName . " where " . $this->iPrimaryKey . " = ?", $primaryId);
			if (!$oldValueRow = $this->iDatabase->getNextRow($resultSet)) {
				$this->iErrorMessage = getSystemMessage("not_found", $resultSet['query']);
				return false;
			}
			freeResult($resultSet);
		} else {
			$oldValueRow = array();
		}
		$foreignKey = $this->iDatabase->getChangeLogForeignKey($this->iTableName);
		$foreignKeyIdentifier = "";
		if (!empty($foreignKey)) {
			if (empty($oldValueRow)) {
				$foreignKeyIdentifier = $parameterList['name_values'][$foreignKey];
			} else if (empty($parameterList['name_values'][$foreignKey])) {
				$foreignKeyIdentifier = $oldValueRow[$foreignKey];
			} else {
				$foreignKeyIdentifier = $parameterList['name_values'][$foreignKey];
			}
		}

# Check to see if a required field is empty

		foreach ($this->iColumns as $columnName => $thisColumn) {
			if ($thisColumn->getControlValue('column_name') != $this->iPrimaryKey && $thisColumn->getControlValue('data_type') != "tinyint" && empty($thisColumn->getControlValue['default_value']) &&
				$thisColumn->getControlValue('data_type') != "longblob" && $thisColumn->getControlValue('subtype') != "image" &&
				$thisColumn->getControlValue('subtype') != "file" && $thisColumn->getControlValue('not_null') && empty($thisColumn->getControlValue('data-conditional-required')) &&
				((array_key_exists($thisColumn->getControlValue('column_name'), $parameterList['name_values']) &&
						strlen($parameterList['name_values'][$thisColumn->getControlValue('column_name')]) == 0) ||
					(empty($primaryId) && !array_key_exists($thisColumn->getControlValue('column_name'), $parameterList['name_values'])))) {

				if ($thisColumn->getControlValue('code_value') && substr($thisColumn->getControlValue('column_name'), -5) == "_code") {
					$parameterList['name_values'][$thisColumn->getControlValue('column_name')] = strtoupper(getRandomString(40));
				} else {
					$this->iErrorMessage = getSystemMessage("REQUIRED_FIELD", $this->iTableName, array("field_name" => $thisColumn->getControlValue('form_label'))) . ($GLOBALS['gUserRow']['superuser_flag'] ? " - " . $this->iTableName : "");
					return false;
				}
			}
		}

# Make sure the row has not already been added, based on the _add_hash parameters

		if (!$parameterList['join_table'] && empty($primaryId) && !empty($parameterList['name_values']['_add_hash'])) {
			$resultSet = $this->iDatabase->executeQuery("select * from add_hashes where add_hash = ?", $parameterList['name_values']['_add_hash']);
			if ($row = $this->iDatabase->getNextRow($resultSet)) {
				$this->iErrorMessage = getSystemMessage("already_added");
				return false;
			}
			freeResult($resultSet);
			$this->iDatabase->executeQuery("insert into add_hashes (add_hash_id,add_hash,date_used,version) values (null,?,now(),1)", $parameterList['name_values']['_add_hash']);
		}

# Check to make sure the update or insert will not result in a duplicate record

		foreach ($this->iUniqueKeys as $columnList) {
			$query = "";
			$parameters = array();
			$emptyColumnFound = false;
			if (count($columnList) == 1 && !empty($primaryId)) {
				$skipUniqueCheck = false;
				foreach ($columnList as $columnName) {
					if (!empty($parameterList['name_values'][$columnName]) && $parameterList['name_values'][$columnName] == $oldValueRow[$columnName]) {
						$skipUniqueCheck = true;
					}
				}
				if ($skipUniqueCheck) {
					continue;
				}
			}
			foreach ($columnList as $columnName) {
				if (!empty($query)) {
					$query .= " and ";
				}
				$query .= $columnName . " = ?";
				if (empty($parameterList['name_values'][$columnName]) && $columnName == "client_id") {
					$parameters[] = $GLOBALS['gClientId'];
				} else {
					if (empty($parameterList['name_values'][$columnName])) {
						$emptyColumnFound = true;
					}
					$parameters[] = $parameterList['name_values'][$columnName];
				}
			}
			if ($emptyColumnFound || empty($query)) {
				continue;
			}
			if (!empty($primaryId)) {
				$query .= " and " . $this->iPrimaryKey . " <> ?";
				$parameters[] = $primaryId;
			}
			$resultSet = $this->iDatabase->executeQuery("select * from " . $this->iTableName . " where " . $query, $parameters);
			if ($row = $this->iDatabase->getNextRow($resultSet)) {
				$uniqueFields = "";
				foreach ($columnList as $columnName) {
					if ($columnName == "client_id" && count($columnList) > 1) {
						continue;
					}
					if (!empty($uniqueFields)) {
						$uniqueFields .= " and ";
					}
					$uniqueFields .= ($GLOBALS['gUserRow']['superuser_flag'] ? $this->iTableName . "." . $columnName : $this->iColumns[$this->iTableName . "." . $columnName]->getControlValue('form_label'));
				}
				$this->iErrorMessage = getSystemMessage("unique_fields", "", array("unique_fields" => $uniqueFields));
				return false;
			}
			freeResult($resultSet);
		}

		$deleteRows = array();
		foreach ($this->iColumns as $columnName => $thisColumn) {
			if ($this->iSaveOnlyPresent && !array_key_exists($thisColumn->getControlValue('column_name'), $parameterList['name_values'])) {
				continue;
			}
			switch ($thisColumn->getControlValue('subtype')) {

# If subtype is image, update or replace the image, if needed

				case "image":
					if ($thisColumn->getControlValue('data_type') == "longblob" && $this->iTableName == "images") {
						break;
					}
					if ($thisColumn->getControlValue('data_type') == "image_picker") {
						break;
					}

# Delete existing image if it is being removed or replaced

					if (!empty($primaryId) && !empty($parameterList['name_values']['remove_' . $thisColumn->getControlValue('column_name')]) ||
						(array_key_exists($thisColumn->getControlValue('column_name') . "_file", $_FILES) &&
							!empty($_FILES[$thisColumn->getControlValue('column_name') . '_file']['name']))) {
						$oldImageId = getFieldFromId($thisColumn->getControlValue('column_name'), $this->iTableName, $this->iPrimaryKey, $primaryId);
						if (!empty($oldImageId)) {
							$parameterList['name_values'][$thisColumn->getControlValue('column_name')] = "";
							$deleteRows[] = array("table_name" => "images", "key_name" => "image_id", "key_value" => $oldImageId);
						}
					}

# create new image, if necessary
					if (array_key_exists($thisColumn->getControlValue('column_name') . "_file", $_FILES) && !empty($_FILES[$thisColumn->getControlValue('column_name') . '_file']['name']) && empty($parameterList['name_values']['remove_' . $thisColumn->getControlValue('column_name')])) {
						$maxDimension = $thisColumn->getControlValue('maximum_dimension');
						$maxWidth = $thisColumn->getControlValue('maximum_width');
						if (empty($maxWidth)) {
							$maxWidth = $maxDimension;
						}
						$maxHeight = $thisColumn->getControlValue('maximum_height');
						if (empty($maxHeight)) {
							$maxHeight = $maxDimension;
						}
						$parameters = array("maximum_width" => $maxWidth, "maximum_height" => $maxHeight, "compression" => $thisColumn->getControlValue('compression'));
						$parameterList['name_values'][$thisColumn->getControlValue('column_name')] = createImage($thisColumn->getControlValue('column_name') . "_file", $parameters);
						if ($parameterList['name_values'][$thisColumn->getControlValue('column_name')] === false) {
							$this->iErrorMessage = "Error writing image";
							return false;
						}
					}
					break;
				case "file":
					if ($thisColumn->getControlValue('data_type') == "longblob" && $this->iTableName == "files") {
						break;
					}

# Delete existing file if it is being removed or replaced

					if (!empty($primaryId) && !empty($parameterList['name_values']['remove_' . $thisColumn->getControlValue('column_name')]) ||
						(array_key_exists($thisColumn->getControlValue('column_name') . "_file", $_FILES) &&
							!empty($_FILES[$thisColumn->getControlValue('column_name') . '_file']['name']))) {
						$oldFileId = getFieldFromId($thisColumn->getControlValue('column_name'), $this->iTableName, $this->iPrimaryKey, $primaryId);
						if (!empty($oldFileId)) {
							$parameterList['name_values'][$thisColumn->getControlValue('column_name')] = "";
							$deleteRows[] = array("table_name" => "download_log", "key_name" => "file_id", "key_value" => $oldFileId);
							$deleteRows[] = array("table_name" => "files", "key_name" => "file_id", "key_value" => $oldFileId);
						}
					}

# create file if one is uploaded. If the file is larger than a preset file size, store it in the file system and not in the DB

					if (array_key_exists($thisColumn->getControlValue('column_name') . "_file", $_FILES) && !empty($_FILES[$thisColumn->getControlValue('column_name') . '_file']['name']) && empty($parameterList['name_values']['remove_' . $thisColumn->getControlValue('column_name')])) {
						$originalFilename = $_FILES[$thisColumn->getControlValue('column_name') . '_file']['name'];
						if (array_key_exists($_FILES[$thisColumn->getControlValue('column_name') . '_file']['type'], $GLOBALS['gMimeTypes'])) {
							$extension = $GLOBALS['gMimeTypes'][$_FILES[$thisColumn->getControlValue('column_name') . '_file']['type']];
						} else {
							$fileNameParts = explode(".", $_FILES[$thisColumn->getControlValue('column_name') . '_file']['name']);
							if (count($fileNameParts) > 1) {
								$extension = $fileNameParts[count($fileNameParts) - 1];
								if (strlen($extension) > 10) {
									$extension = "";
								}
							} else {
								$extension = "";
							}
						}
						$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
						if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
							$maxDBSize = 1000000;
						}
						if ($_FILES[$thisColumn->getControlValue('column_name') . '_file']['size'] < $maxDBSize) {
							$fileContent = file_get_contents($_FILES[$thisColumn->getControlValue('column_name') . '_file']['tmp_name']);
							if (!empty($GLOBALS['gPageObject']) && method_exists($GLOBALS['gPageObject'],"massageFileContent")) {
								$GLOBALS['gPageObject']->massageFileContent($parameters['name_values'],$fileContent);
							}
							if (strlen($fileContent) == 0) {
								$this->iErrorMessage = "Empty file uploaded";
								return false;
							}
							$osFilename = "";
						} else {
							$fileContent = "";
							$osFilename = "/documents/tmp." . $extension;
						}
						$resultSet = $this->iDatabase->executeQuery("insert into files (client_id,description,date_uploaded," .
							"filename,extension,file_content,os_filename,public_access,all_user_access,administrator_access) values " .
							"(?,?,now(),?,?,?,?," . (empty($thisColumn->getControlValue("public_access")) ? "0" : "1") . "," .
							(empty($thisColumn->getControlValue("all_user_access")) ? "0" : "1") . "," .
							(empty($thisColumn->getControlValue("public_access")) && empty($thisColumn->getControlValue("all_user_access")) ? "1" : "0") . ")",
							$GLOBALS['gClientId'], $originalFilename, $originalFilename, $extension, $fileContent, $osFilename);
						if (!empty($resultSet['sql_error'])) {
							$this->iErrorMessage = getSystemMessage("basic", $resultSet['sql_error']);
							return false;
						}
						$parameterList['name_values'][$thisColumn->getControlValue('column_name')] = $resultSet['insert_id'];
						if (!empty($osFilename)) {
							$fileContent = file_get_contents($_FILES[$thisColumn->getControlValue('column_name') . '_file']['tmp_name']);
							if (!empty($GLOBALS['gPageObject']) && method_exists($GLOBALS['gPageObject'],"massageFileContent")) {
								$GLOBALS['gPageObject']->massageFileContent($parameters['name_values'],$fileContent);
							}
							putExternalFileContents($parameterList['name_values'][$thisColumn->getControlValue('column_name')], $extension, $fileContent);
						}
					}
					break;
			}
		}

		$actionPerformed = "";
		if (!empty($primaryId)) {
			$saveStatement = "";
			$saveParameters = array();
			$optimisticLocking = false;
			$columnInformation = $this->iDatabase->getTableColumns($this->iTableName);
			$timeChangedFound = false;
			$timeChangedSet = false;
			foreach ($columnInformation as $row) {
				if ($row['COLUMN_NAME'] == $this->iPrimaryKey) {
					continue;
				}
				if ($row['COLUMN_NAME'] == "version" && array_key_exists("version", $parameterList['name_values']) && !$parameterList['join_table']) {
					$optimisticLocking = !$this->iIgnoreVersion;
					continue;
				}
				if ($row['COLUMN_NAME'] == "time_changed") {
					$timeChangedFound = true;
				}
				$updateField = false;
				$fieldValue = null;
				if (!in_array($row['COLUMN_NAME'], $this->iExcludeUpdateColumns) && (array_key_exists($row['COLUMN_NAME'], $parameterList['name_values']) || (substr($row['COLUMN_TYPE'], 0, 7) == "tinyint" && !$this->iSaveOnlyPresent))) {
					if ($row['COLUMN_NAME'] == "time_changed") {
						$timeChangedSet = true;
					}
					$columnType = strtok($row['COLUMN_TYPE'] . "(", "(");
					switch ($columnType) {
						case "bigint":
						case "int":
						case "decimal":
							if (empty($parameterList['no_change_log'])) {
								$updateField = $updateField || $this->iDatabase->createNumberChangeLog($this->iTableName, $row['COLUMN_NAME'], $primaryId, $oldValueRow[$row['COLUMN_NAME']], $parameterList['name_values'][$row['COLUMN_NAME']], $GLOBALS['gUserId'], $foreignKeyIdentifier);
							} else {
								$updateField = $updateField || $oldValueRow[$row['COLUMN_NAME']] != $parameterList['name_values'][$row['COLUMN_NAME']] ||
									(strlen($oldValueRow[$row['COLUMN_NAME']]) == 0 && strlen($parameterList['name_values'][$row['COLUMN_NAME']]) > 0) ||
									(strlen($oldValueRow[$row['COLUMN_NAME']]) > 0 && strlen($parameterList['name_values'][$row['COLUMN_NAME']]) == 0);
							}
							$fieldValue = $this->iDatabase->makeNumberParameter($parameterList['name_values'][$row['COLUMN_NAME']]);
							break;
						case "date":
							if (empty($parameterList['no_change_log'])) {
								$updateField = $updateField || $this->iDatabase->createDateChangeLog($this->iTableName, $row['COLUMN_NAME'], $primaryId, $oldValueRow[$row['COLUMN_NAME']], $parameterList['name_values'][$row['COLUMN_NAME']], $GLOBALS['gUserId'], $foreignKeyIdentifier);
							} else {
								$updateField = $updateField || $oldValueRow[$row['COLUMN_NAME']] != $parameterList['name_values'][$row['COLUMN_NAME']];
							}
							$fieldValue = $this->iDatabase->makeDateParameter($parameterList['name_values'][$row['COLUMN_NAME']]);
							break;
						case "datetime":
							if (empty($parameterList['no_change_log'])) {
								$updateField = $updateField || $this->iDatabase->createDatetimeChangeLog($this->iTableName, $row['COLUMN_NAME'], $primaryId, $oldValueRow[$row['COLUMN_NAME']], $parameterList['name_values'][$row['COLUMN_NAME']], $GLOBALS['gUserId'], $foreignKeyIdentifier);
							} else {
								$updateField = $updateField || $oldValueRow[$row['COLUMN_NAME']] != $parameterList['name_values'][$row['COLUMN_NAME']];
							}
							$fieldValue = $this->iDatabase->makeDateTimeParameter($parameterList['name_values'][$row['COLUMN_NAME']]);
							break;
						case "tinyint":
							if (empty($parameterList['no_change_log'])) {
								$updateField = $updateField || $this->iDatabase->createBooleanChangeLog($this->iTableName, $row['COLUMN_NAME'], $primaryId, $oldValueRow[$row['COLUMN_NAME']], $parameterList['name_values'][$row['COLUMN_NAME']], $GLOBALS['gUserId'], $foreignKeyIdentifier);
							} else {
								$updateField = $updateField || $oldValueRow[$row['COLUMN_NAME']] != $parameterList['name_values'][$row['COLUMN_NAME']];
							}
							$fieldValue = (empty($parameterList['name_values'][$row['COLUMN_NAME']]) ? 0 : 1);
							break;
						case "longblob":
							if (strlen($parameterList['name_values'][$row['COLUMN_NAME']]) > 0) {
								$updateField = true;
								$fieldValue = $parameterList['name_values'][$row['COLUMN_NAME']];
							} else if (array_key_exists($row['COLUMN_NAME'] . "_file", $_FILES) && !empty($_FILES[$row['COLUMN_NAME'] . '_file']['name']) && $this->iTableName != "files") {
								$updateField = true;
								$fieldValue = file_get_contents($_FILES[$row['COLUMN_NAME'] . '_file']['tmp_name']);
							}
							break;
						default:
							if (empty($parameterList['no_change_log'])) {
								$updateField = $updateField || $this->iDatabase->createChangeLog($this->iTableName, $row['COLUMN_NAME'], $primaryId, $oldValueRow[$row['COLUMN_NAME']], $parameterList['name_values'][$row['COLUMN_NAME']], $GLOBALS['gUserId'], $foreignKeyIdentifier);
							} else {
								$updateField = $updateField || $oldValueRow[$row['COLUMN_NAME']] != $parameterList['name_values'][$row['COLUMN_NAME']] || strlen($oldValueRow[$row['COLUMN_NAME']]) != strlen($parameterList['name_values'][$row['COLUMN_NAME']]);
							}
							$fieldValue = trim($parameterList['name_values'][$row['COLUMN_NAME']]);
							break;
					}
				}
				if ($updateField) {
					$saveStatement .= (empty($saveStatement) ? "" : ",") . $row['COLUMN_NAME'] . " = ?";
					$saveParameters[] = $fieldValue;
					$this->iColumnsChanged++;
				}
			}
			if (!empty($saveStatement)) {
				$saveParameters[] = $primaryId;
				if ($optimisticLocking) {
					$saveParameters[] = $parameterList['name_values']['version'];
				}
				$resultSet = $this->iDatabase->executeQuery("update " . $this->iTableName . " set " . $saveStatement . ($optimisticLocking ? ",version = version + 1" : "") .
					($timeChangedFound && !$timeChangedSet ? ",time_changed = now()" : "") .
					" where " . $this->iPrimaryKey . " = ?" . ($optimisticLocking ? " and version = ?" : ""), $saveParameters);
				if ($resultSet['affected_rows'] == 0 && $optimisticLocking) {
					$this->iErrorMessage = getSystemMessage("RECORD_CHANGED");
					return false;
				}
				$actionPerformed = "update";
			}
		} else {
			$columnStatement = "(" . $this->iPrimaryKey;
			$valuesStatement = "(null";
			$parameters = array();
			$columnInformation = $this->iDatabase->getTableColumns($this->iTableName);
			$primaryKeyDataType = false;
			foreach ($columnInformation as $row) {
				if ($row['COLUMN_NAME'] == $this->iPrimaryKey) {
					$primaryKeyDataType = strtok($row['COLUMN_TYPE'] . "(", "(");
					continue;
				}
				$fieldValue = "";
				$fieldParameter = "";
				if (array_key_exists($row['COLUMN_NAME'], $parameterList['name_values'])) {
					$columnType = strtok($row['COLUMN_TYPE'] . "(", "(");
					switch ($columnType) {
						case "bigint":
						case "int":
						case "decimal":
							$fieldValue = $this->iDatabase->makeNumberParameter($parameterList['name_values'][$row['COLUMN_NAME']]);
							break;
						case "date":
							$fieldValue = $this->iDatabase->makeDateParameter($parameterList['name_values'][$row['COLUMN_NAME']]);
							break;
						case "datetime":
							if ((empty($parameterList['name_values'][$row['COLUMN_NAME']]) || $parameterList['name_values'][$row['COLUMN_NAME']] == "CURRENT_TIMESTAMP") && $row['IS_NULLABLE'] == "NO" && !empty($row['COLUMN_DEFAULT'])) {
								$fieldParameter = "now()";
							} else {
								$fieldValue = $this->iDatabase->makeDatetimeParameter($parameterList['name_values'][$row['COLUMN_NAME']]);
							}
							break;
						case "tinyint":
							$fieldValue = (empty($parameterList['name_values'][$row['COLUMN_NAME']]) ? 0 : 1);
							break;
						case "longblob":
							if (strlen($parameterList['name_values'][$row['COLUMN_NAME']]) > 0) {
								$fieldValue = $parameterList['name_values'][$row['COLUMN_NAME']];
							} else if (array_key_exists($row['COLUMN_NAME'] . "_file", $_FILES) && !empty($_FILES[$row['COLUMN_NAME'] . '_file']['name']) && $this->iTableName != "files") {
								$fieldValue = file_get_contents($_FILES[$row['COLUMN_NAME'] . '_file']['tmp_name']);
							}
							break;
						default:
							$fieldValue = $parameterList['name_values'][$row['COLUMN_NAME']];
							break;
					}
					$columnStatement .= "," . $row['COLUMN_NAME'];
					$valuesStatement .= "," . ($fieldParameter ?: "?");
					$parameters[] = $fieldValue;
				}
			}
			$resultSet = $this->iDatabase->executeQuery("insert into " . $this->iTableName . " " . $columnStatement . ") values " . $valuesStatement . ")", $parameters);
			$parameterList['name_values']['primary_id'] = $parameterList['name_values'][$this->iPrimaryKey] = $primaryId = $resultSet['insert_id'];
			if ($primaryKeyDataType == "int" && $parameterList['name_values']['primary_id'] > 2000000000) {
				executeQuery("update column_definitions set column_type = 'bigint' where column_name = ?",$this->iPrimaryKey);
			}
			$actionPerformed = "insert";
			if (empty($parameterList['no_change_log'])) {
				foreach ($columnInformation as $row) {
					if ($row['COLUMN_NAME'] == $this->iPrimaryKey) {
						continue;
					}
					if (array_key_exists($row['COLUMN_NAME'], $parameterList['name_values'])) {
						$columnType = strtok($row['COLUMN_TYPE'] . "(", "(");
						switch ($columnType) {
							case "bigint":
							case "int":
							case "decimal":
								if (strlen($parameterList['name_values'][$row['COLUMN_NAME']]) > 0 && $row['COLUMN_NAME'] != "client_id") {
									$this->iDatabase->createNumberChangeLog($this->iTableName, $row['COLUMN_NAME'], $primaryId, "[NEW RECORD]", $parameterList['name_values'][$row['COLUMN_NAME']], $GLOBALS['gUserId'], $foreignKeyIdentifier);
								}
								break;
							case "date":
								if (strlen($parameterList['name_values'][$row['COLUMN_NAME']]) > 0) {
									$this->iDatabase->createDateChangeLog($this->iTableName, $row['COLUMN_NAME'], $primaryId, "[NEW RECORD]", $parameterList['name_values'][$row['COLUMN_NAME']], $GLOBALS['gUserId'], $foreignKeyIdentifier);
								}
								break;
							case "datetime":
								if (strlen($parameterList['name_values'][$row['COLUMN_NAME']]) > 0) {
									$this->iDatabase->createDatetimeChangeLog($this->iTableName, $row['COLUMN_NAME'], $primaryId, "[NEW RECORD]", $parameterList['name_values'][$row['COLUMN_NAME']], $GLOBALS['gUserId'], $foreignKeyIdentifier);
								}
								break;
							case "tinyint":
								$this->iDatabase->createBooleanChangeLog($this->iTableName, $row['COLUMN_NAME'], $primaryId, "[NEW RECORD]", $parameterList['name_values'][$row['COLUMN_NAME']], $GLOBALS['gUserId'], $foreignKeyIdentifier);
								break;
							default:
								if (strlen($parameterList['name_values'][$row['COLUMN_NAME']]) > 0) {
									$this->iDatabase->createChangeLog($this->iTableName, $row['COLUMN_NAME'], $primaryId, "[NEW RECORD]", $parameterList['name_values'][$row['COLUMN_NAME']], $GLOBALS['gUserId'], $foreignKeyIdentifier);
								}
								break;
						}
					}
				}
			}
		}
		$this->iSqlErrorNumber = $resultSet['sql_error_number'];
		if (!empty($resultSet['sql_error'])) {
			$this->iErrorMessage = getSystemMessage("basic", $resultSet['sql_error']);
			return false;
		}
		$this->iDatabase->ignoreError(true);
		foreach ($deleteRows as $deleteInfo) {
			$this->iDatabase->executeQuery("delete from " . $deleteInfo['table_name'] . " where " . $deleteInfo['key_name'] . " = " . $deleteInfo['key_value']);
		}
		$this->iDatabase->ignoreError(false);

# Check for actions
		if (self::$iTableActions === false) {
			self::$iTableActions = array();
			$resultSet = executeQuery("select *,(select table_name from tables where table_id = (select update_table_id from action_types where action_type_id = actions.action_type_id)) table_name from actions where inactive = 0");
			while ($row = getNextRow($resultSet)) {
                $actionKey = $row['client_id'] . ":" . $row['table_name'];
				if (!array_key_exists($actionKey, self::$iTableActions)) {
					self::$iTableActions[$actionKey] = array();
				}
				self::$iTableActions[$actionKey][] = $row;
			}
		}

		self::processDefaultActions($this->iTableName, $oldValueRow, $parameterList['name_values']);
		if (array_key_exists($GLOBALS['gClientId'] . ":" . $this->iTableName, self::$iTableActions)) {
			foreach (self::$iTableActions[$GLOBALS['gClientId'] . ":" . $this->iTableName] as $row) {
				processAction($row, $oldValueRow, $parameterList['name_values']);
			}
		}
		return $primaryId;
	}

	static function processDefaultActions($tableName, $oldValueRow, $newValueRow) {

		# check for local function to process table changes. If function returns true, it has done all processing that is needed

		if (function_exists("_localProcessTableActions")) {
			if (_localProcessTableActions($tableName, $oldValueRow, $newValueRow)) {
				return;
			}
		}
		switch ($tableName) {
			case "product_reviews":
				if (!empty($newValueRow['user_id']) && empty($newValueRow['requires_approval']) && !empty($oldValueRow['requires_approval'])) {
					$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", getFieldFromId("contact_id","users", "user_id", $newValueRow['user_id']));
					$emailId = getFieldFromId("email_id", "emails", "email_code", "PRODUCT_REVIEW_PUBLISHED", "inactive = 0");
					if (!empty($emailId) && !empty($emailAddress)) {
						$substitutions = getRowFromId("products", "product_id", $newValueRow['product_id']);
						sendEmail(array("email_id"=>$emailId, "email_address"=>$emailAddress, "substitutions"=>$substitutions));
					}
				}
				break;
			case "product_answers":
				if (empty($newValueRow['product_question_id'])) {
					break;
				}
				$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", getFieldFromId("contact_id","users", "user_id", getFieldFromId("user_id", "product_questions", "product_question_id", $newValueRow['product_question_id'])));
				if (empty($emailAddress)) {
					break;
				}
				$emailId = getFieldFromId("email_id","emails","email_code","PRODUCT_ANSWER", "inactive = 0");
				if (empty($emailId)) {
					break;
				}
				$substitutions = getRowFromId("product_questions", "product_question_id", $newValueRow['product_question_id']);
				$resultSet = executeQuery("select * from products left outer join product_data using (product_id) where product_id = (select product_id from product_questions where product_question_id = ?)",$newValueRow['product_question_id']);
				$productRow = getNextRow($resultSet);
				$substitutions = array_merge($substitutions,$productRow);
				$resultSet = executeQuery("select * from product_answers where product_question_id = ? and inactive = 0 and requires_approval = 0",$newValueRow['product_question_id']);
				$substitutions['answers_table'] = "<table><tr><td>Date</td><td>By</td><td>Answer</td></tr>";
				$substitutions['answers_html'] = "";
				while ($row = getNextRow($resultSet)) {
					$substitutions['answers_table'] .= "<tr><td class='answer-date'>" . date("m/d/Y",strtotime($row['date_created'])) . "</td><td class='answer-user'>" .
						(empty($row['user_id']) ? $row['full_name'] : getUserDisplayName($row['user_id'])) . "</td><td class='answer-content'>" . $row['content'] . "</td></tr>";
					$substitutions['answers_html'] .= "<p class='answer-intro'>On " . date("m/d/Y",strtotime($row['date_created'])) . ", " .
						(empty($row['user_id']) ? $row['full_name'] : getUserDisplayName($row['user_id'])) . " said:</p><div class='answer-content'>" . makeHtml($row['content']) . "</div>";
				}
				$substitutions['answers_table'] .= "</table>";
				sendEmail(array("email_id"=>$emailId, "email_address"=>$emailAddress, "substitutions"=>$substitutions));
				break;
			case "products":
				$changeFields = array("description", "detailed_description", "product_manufacturer_id", "link_name");
				foreach ($changeFields as $fieldName) {
					if ($oldValueRow[$fieldName] != $newValueRow[$fieldName]) {
						ProductCatalog::reindexProducts($newValueRow['product_id']);
						break;
					}
				}
				break;
			case "product_data":
				$changeFields = array("upc_code", "manufacturer_sku", "model");
				foreach ($changeFields as $fieldName) {
					if ($oldValueRow[$fieldName] != $newValueRow[$fieldName]) {
						ProductCatalog::reindexProducts($newValueRow['product_id']);
						break;
					}
				}
				break;
			case "product_types":
				removeCachedData("order_upsell_product_type_id","");
				break;
			case "sass_headers":
				removeCachedData("sass_headers", "*", true);
				break;
			case "ip_address_blacklist":
				createBlackList();
				break;
			case "users":
				if (empty($GLOBALS['gDontReloadUsersContacts'])) {
					Contact::getUser($newValueRow['primary_id'], true);
				}
				if (empty($oldValueRow['superuser_flag']) && !empty($newValueRow['superuser_flag'])) {
					$GLOBALS['gPrimaryDatabase']->logError("Superuser set for user '" . $newValueRow['user_name'] . "' in client '" . $GLOBALS['gClientRow']['client_code'] . " by " . getUserDisplayName());
				}
				break;
			case "contacts":
				if (empty($GLOBALS['gDontReloadUsersContacts'])) {
					Contact::getContact($newValueRow['primary_id'], true);
				}
				if (empty($newValueRow['contact_id']) && !empty($newValueRow['primary_id'])) {
					$newValueRow['contact_id'] = $newValueRow['primary_id'];
				}
				if (!empty($newValueRow['contact_id'])) {
					$resultSet = executeQuery("select * from contact_identifier_types where client_id = ? and autogenerate = 1 and inactive = 0",$GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$contactIdentifierId = getFieldFromId("contact_identifier_id","contact_identifiers","contact_id",$newValueRow['contact_id'],"contact_identifier_type_id = ?",$row['contact_identifier_type_id']);
						if (empty($contactIdentifierId)) {
                            $idString = generateContactIdentifier($row['contact_identifier_type_id']);
							executeQuery("insert into contact_identifiers (contact_id,contact_identifier_type_id,identifier_value) values (?,?,?)",
								$newValueRow['contact_id'],$row['contact_identifier_type_id'],$idString);
						}
					}
				}
                $reason = "updated";
                $contactId = $oldValueRow['contact_id'];
                if(empty($oldValueRow)) {
                    $reason = "created";
                    $contactId = $newValueRow['contact_id'];
                } elseif(!empty($newValueRow['deleted'])) {
                    $reason = "deleted";
                }
                coreSTORE::contactNotification($contactId, $reason);
                break;
		}
	}

	/**
	 * isLimitedByClient - Is this table restricted by client ID.
	 *
	 * @return true or false
	 */
	function isLimitedByClient() {
		return $this->iLimitByClient;
	}

	function isForceNoLimitByClient() {
		return $this->iForceNoLimitByClient;
	}

	/**
	 * ignoreVersion - Don't use optimistic locking
	 *
	 * @param true or false
	 */
	function ignoreVersion() {
		$this->iIgnoreVersion = true;
	}

	/**
	 * setLimitByClient - Set whether this table is limited by client ID or not. This should be used very cautiously because
	 *    the developer does not want to expose data of one client to users of another client.
	 *
	 * @param true or false
	 */
	function setLimitByClient($limitByClient) {
		$this->iLimitByClient = $limitByClient;
		$this->iForceNoLimitByClient = !$limitByClient;
	}

	/**
	 * setPrimaryId - Set the primary ID of the current row of the table
	 *
	 * @param value of the primary ID
	 */
	function setPrimaryId($primaryId) {
		$this->iPrimaryId = $primaryId;
	}

	/**
	 * getPrimaryKey - Returns the primary key of the table.
	 * @param
	 *    none
	 * @return
	 *    the name of the primary key field of the table specified
	 */
	function getPrimaryKey() {
		return $this->iPrimaryKey;
	}

	/**
	 * getErrorMessage - return the error message from the most recent error
	 * @param
	 *    none
	 * @return
	 *    error message
	 */
	function getErrorMessage() {
		return $this->iErrorMessage;
	}

	function getErrorNumber() {
		return $this->iSqlErrorNumber;
	}

	function getColumnsChanged() {
		return $this->iColumnsChanged;
	}

	/**
	 * setSubTables - set tables that are subrecords of the primary table. If a row is deleted in the primary table, rows in
	 *    subtables with matching foreign keys are also deleted.
	 * @param
	 *    array of associative arrays with "table_name" & "foreign_key" values
	 *    array of table names. Function will use the primary key name as the foreign key
	 *    string of comma separated table names. Function will use primary key name as the foreign key
	 * @return
	 *    none
	 */
	function setSubTables($subTables) {
		$this->iSubTables = array();
		$this->addSubTables($subTables);
	}

	/**
	 * addSubTables - add tables to those that are subrecords of the primary table
	 * @param
	 *    array of associative arrays with "table_name" & "foreign_key" values
	 *    array of table names. Function will use the primary key name as the foreign key
	 *    string of comma separated table names. Function will use primary key name as the foreign key
	 * @return
	 *    none
	 */
	function addSubTables($subTables) {
		if (!is_array($subTables)) {
			$subTables = explode(",", $subTables);
		}
		foreach ($subTables as $subTable) {
			if (!in_array($subTable, $this->iSubTables)) {
				$this->iSubTables[] = $subTable;
			}
		}
	}

	/**
	 * deleteRecord - Delete row from table. If any subtables have been declared, rows from those tables will be deleted as well.
	 *    Any other tables are considered to be dependent tables and records in those tables will generate an error.
	 * @param
	 *    Parameter List:
	 *        primary_id: key of row to be deleted from database. Default: primary ID.
	 */
	function deleteRecord($parameterList = array()) {
		if (!array_key_exists("primary_id", $parameterList) || empty($parameterList['primary_id'])) {
			$parameterList['primary_id'] = $this->iPrimaryId;
		}
		if (empty($parameterList['primary_id'])) {
			$this->iErrorMessage = getSystemMessage("no_key_delete");
			return false;
		}
		if (in_array($this->iTableName,self::$iNeverLogChanges)) {
			$parameterList['no_change_log'] = true;
		}

		$dependentSet = executeReadQuery("select * from information_schema.key_column_usage where referenced_table_name = ? " .
			"and referenced_column_name = ? and constraint_schema = ?", $this->iTableName, $this->iPrimaryKey, $this->iDatabase->getName());
		while ($dependentRow = getNextRow($dependentSet)) {
			if (in_array($dependentRow['TABLE_NAME'], $this->iSubTables)) {
				continue;
			}
			$resultSet = $this->iDatabase->executeQuery("select count(*) from " . $dependentRow['TABLE_NAME'] . " where " . $dependentRow['COLUMN_NAME'] . " = ?", $parameterList['primary_id']);
			if ($row = $this->iDatabase->getNextRow($resultSet)) {
				if ($row['count(*)'] > 0) {
					$this->iErrorMessage = getSystemMessage("dependent_record", "", array("table_name" => $dependentRow['TABLE_NAME']));
					return false;
				}
			}
		}
		freeResult($dependentSet);
		foreach ($this->iSubTables as $subTable) {
			$dependentSet = executeReadQuery("select * from information_schema.key_column_usage where referenced_table_name = ? " .
				"and referenced_column_name = ? and constraint_schema = ? and table_name = ?", $this->iTableName, $this->iPrimaryKey,
				$this->iDatabase->getName(), $subTable);
			while ($dependentRow = getNextRow($dependentSet)) {
				$resultSet = $this->iDatabase->executeQuery("delete from " . $subTable . " where " . $dependentRow['COLUMN_NAME'] . " = ?", $parameterList['primary_id']);
				if (!empty($resultSet['sql_error'])) {
					$this->iErrorMessage = getSystemMessage("basic", $resultSet['sql_error']);
					return false;
				}
				freeResult($resultSet);
			}
			freeResult($dependentSet);
		}
		$subtableDeletes = array();
		$resultSet = $this->iDatabase->executeQuery("select * from " . $this->iTableName . " where " . $this->iPrimaryKey . " = ?", $parameterList['primary_id']);
		if ($oldValueRow = $this->iDatabase->getNextRow($resultSet)) {
			foreach ($this->iColumns as $columnName => $thisColumn) {
				if (!is_array($parameterList['ignore_subtables']) || !in_array("images", $parameterList['ignore_subtables'])) {
					if ($thisColumn->getControlValue('data_type') == "image") {
						$subtableDeletes[] = array("table_name" => "images", "primary_key" => "image_id", "primary_id" => $oldValueRow[$thisColumn->getControlValue("column_name")], "sub_table" => "image_data");
					}
				}
				if (!is_array($parameterList['ignore_subtables']) || !in_array("files", $parameterList['ignore_subtables'])) {
					if ($thisColumn->getControlValue('data_type') == "file") {
						$subtableDeletes[] = array("table_name" => "files", "primary_key" => "file_id", "primary_id" => $oldValueRow[$thisColumn->getControlValue("column_name")], "sub_table" => "download_log");
					}
				}
			}
			$primaryId = $parameterList['primary_id'];
			$columnInformation = $this->iDatabase->getTableColumns($this->iTableName);
			if (empty($parameterList['no_change_log'])) {
				foreach ($columnInformation as $thisColumnInfo) {
					if ($thisColumnInfo['COLUMN_NAME'] == $this->iPrimaryKey || $thisColumnInfo['COLUMN_NAME'] == "client_id") {
						continue;
					}
					$columnType = strtok($thisColumnInfo['COLUMN_TYPE'] . "(", "(");
					if (!empty($oldValueRow[$thisColumnInfo['COLUMN_NAME']]) || $columnType == "tinyint") {
						switch ($columnType) {
							case "bigint":
							case "int":
							case "decimal":
								$this->iDatabase->createNumberChangeLog($this->iTableName, $thisColumnInfo['COLUMN_NAME'], $primaryId, $oldValueRow[$thisColumnInfo['COLUMN_NAME']], "[DELETED]");
								break;
							case "date":
								$this->iDatabase->createDateChangeLog($this->iTableName, $thisColumnInfo['COLUMN_NAME'], $primaryId, $oldValueRow[$thisColumnInfo['COLUMN_NAME']], "[DELETED]");
								break;
							case "datetime":
								$this->iDatabase->createDatetimeChangeLog($this->iTableName, $thisColumnInfo['COLUMN_NAME'], $primaryId, $oldValueRow[$thisColumnInfo['COLUMN_NAME']], "[DELETED]");
								break;
							case "tinyint":
								$this->iDatabase->createBooleanChangeLog($this->iTableName, $thisColumnInfo['COLUMN_NAME'], $primaryId, $oldValueRow[$thisColumnInfo['COLUMN_NAME']], "[DELETED]");
								break;
							case "longblob":
								break;
							default:
								$this->iDatabase->createChangeLog($this->iTableName, $thisColumnInfo['COLUMN_NAME'], $primaryId, $oldValueRow[$thisColumnInfo['COLUMN_NAME']], "[DELETED]");
								break;
						}
					}
				}
			}
		}
		freeResult($resultSet);
		$resultSet = $this->iDatabase->executeQuery("delete from " . $this->iTableName . " where " . $this->iPrimaryKey . " = ?", $parameterList['primary_id']);
		if (!empty($resultSet['sql_error'])) {
			$this->iErrorMessage = getSystemMessage("basic", $resultSet['sql_error']);
			return false;
		}
		freeResult($resultSet);
		foreach ($subtableDeletes as $tableInfo) {
			if (!empty($tableInfo['sub_table'])) {
				$this->iDatabase->executeQuery("delete from " . $tableInfo['sub_table'] . " where " . $tableInfo['primary_key'] . " = ?", $tableInfo['primary_id']);
			}
			$this->iDatabase->executeQuery("delete from " . $tableInfo['table_name'] . " where " . $tableInfo['primary_key'] . " = ?", $tableInfo['primary_id']);
		}

# Check for actions
		self::processDefaultActions($this->iTableName, $oldValueRow, array());
		$resultSet = executeQuery("select * from actions where inactive = 0 and action_type_id in (select action_type_id from action_types where update_table_id = (select table_id from tables where table_name = ?))", $this->iTableName);
		while ($row = getNextRow($resultSet)) {
			processAction($row, $oldValueRow, array());
		}

		return true;
	}

	/**
	 * getColumns - Return list of column names and descriptions. This will come from the column meta data
	 * @param
	 *    column name. If none provided, all columns are returned
	 * @return
	 *    array of column names
	 */
	function getColumns($columnName = "") {
		if (empty($columnName)) {
			return $this->iColumns;
		} else {
			if (!startsWith($columnName, $this->iTableName . ".")) {
				$columnName = $this->iTableName . "." . $columnName;
			}
			return $this->iColumns[$columnName];
		}
	}

	/**
	 * getRow - Return the row corresponding to the primary ID for this table
	 *
	 * @return
	 *    array of the data of the row
	 */
	function getRow($primaryId = "") {
		if (empty($primaryId)) {
			$primaryId = $this->iPrimaryId;
		}
		if (!empty($primaryId)) {
			$resultSet = $this->iDatabase->executeQuery("select * from " . $this->iTableName . " where " . $this->iPrimaryKey . " = ?", $primaryId);
			if ($row = $this->iDatabase->getNextRow($resultSet)) {
				freeResult($resultSet);
				return $row;
			}
		}
		return array();
	}

	/**
	 * getUniqueKeyList - Return list of unique keys. This will come from the column meta data
	 * @param
	 *    none
	 * @return
	 *    array of unique keys
	 */
	function getUniqueKeyList() {
		return $this->iUniqueKeys;
	}

	/**
	 * getName - Return the name of the table represented by this object
	 *
	 * @return
	 *    string name of table
	 */
	function getName() {
		return $this->iTableName;
	}

	/**
	 * getDatabase - Return the database object used by this table object
	 *
	 * @return
	 *    database object
	 */
	function getDatabase() {
		return $this->iDatabase;
	}

	/**
	 * addExcludeUpdateColumns - add column that should not be included in the update of this table
	 *
	 * @param
	 *    a comma separated list of column names or an array of column names
	 */
	function addExcludeUpdateColumns($columnNames) {
		if (!is_array($columnNames)) {
			$columnNames = explode(",", $columnNames);
		}
		foreach ($columnNames as $columnName) {
			$columnName = $this->columnExists($columnName);
			if ($columnName !== false) {
				if (!in_array($columnName, $this->iExcludeUpdateColumns)) {
					$this->iExcludeUpdateColumns[] = $columnName;
				}
			}
		}
	}

	/**
	 * columnExists - return true or false if this column exists in this table
	 *
	 * @return
	 *    true or false
	 */
	function columnExists($columnName) {
		if (startsWith($columnName, $this->iTableName . ".")) {
			$columnName = substr($columnName, strlen($this->iTableName . "."));
		}
		if (!empty($columnName) && array_key_exists($this->iTableName . "." . $columnName, $this->iColumns)) {
			return $columnName;
		} else {
			return false;
		}
	}

	function getDescription() {
		return getFieldFromId("description", "tables", "table_name", $this->iTableName, "database_definition_id = " .
			"(select database_definition_id from database_definitions where database_name = '" . $this->iDatabase->getName() . "')");
	}

    /**
     * isEmpty - return true or false if the table has data
     *
     * @return bool true or false
     */
    static function isEmpty($tableName, $limitByClient = false): bool {
        if(empty($GLOBALS['gTableKeys'][$tableName])) {
            return false;
        }
        $existsQuery = "select 1 from $tableName" . ($limitByClient ?" where client_id = {$GLOBALS['gClientId']}" : "");

        $resultSet = executeQuery("select exists($existsQuery) as has_rows");

        if($row = getNextRow($resultSet)) {
            $hasRows = $row['has_rows'];
        } else {
            $hasRows = false;
        }
        return !$hasRows;
    }

    /**
     * getLimitedCount - return the number of rows in the table up to $rowLimit.
     * Used for determining if a feature should be enabled based on a row threshold for a table
     * Should be significantly faster than count(*) as long as $rowLimit is significantly less than the number of rows in the table
     * @param string $tableName
     * @param int $rowLimit
     * @param bool $limitByClient
     * @return int number of rows in the table up to $rowLimit
     */
    static function getLimitedCount(string $tableName, int $rowLimit, bool $limitByClient = false) : int {
        $primaryKey = $GLOBALS['gTableKeys'][$tableName];
        if(empty($primaryKey)) {
            return 0;
        }

        $limitQuery = "select $primaryKey from $tableName" . ($limitByClient ? " where client_id = {$GLOBALS['gClientId']}" : "") . " limit $rowLimit";

        $resultSet = executeQuery("select count(*) limited_count from ($limitQuery) as limit_table");

        if(!$row = getNextRow($resultSet)) {
            $row = ['limited_count' => 0];
        }
        return $row['limited_count'];
    }
}
