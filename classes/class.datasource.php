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
 * class DataSource
 *
 * DataSource is an encapsulation of the database functions needed for a page that deals with one database table, with an
 * optional joined table.
 *
 * @author Kim D Geiger
 */
class DataSource {
	private $iDatabase = null;
	private $iPrimaryTable = false;
	private $iSortOrder = array();            # each entry is an array like array("column_name"=>"column_name","sort_direction"=>"asc/desc");
	private $iReverseSort = false;            # if true, the sort order will be reversed
	private $iFilterText = "";
	private $iFilterWhere = "";
	private $iQueryWhere = "";
	private $iQueryParameters = array();
	private $iQueryString = "";
	private $iDataList = false;
	private $iErrorMessage = "";
	private $iSqlErrorNumber = false;
	private $iAdditionalColumns = array();
	private $iSearchFieldsSet = false;
	private $iSearchableSubfields = array();
	private $iSearchWhereStatements = array();        # array of strings that are "or'd" into the search string
	private $iDataListCount = 0;
	private $iUseTransactions = true;
	private $iBeforeSaveFunction = array(); # array with object and method name to call before save function
	private $iAfterSaveFunction = array();
	private $iFilterFunction = array(); # array with object and method name to call to filter data list
	private $iConditionalJoinFunction = array(); # function that can optionally be called to determine if the join table record should be created.
	private $iJoinTable = false;
	private $iJoinOuter = false;
	private $iDontUpdateJoinTable = false;
	private $iJoinPrimaryColumn = "";
	private $iJoinColumn = "";
	private $iJoinWhere = "";
	private $iJoinPrimaryId = "";
	private $iNullLastSort = true;
	private $iPrimaryId = "";
	private $iColumnsChanged = 0;

	/**
	 * Construct - When creating the class, you must set the primary table
	 * @param
	 *    $tableName - name of the table for which this data source is being constructed
	 *    $database - database object to be used for this data source. If not passed in, the primary database connection is used.
	 * @return
	 *    none
	 */
	function __construct($tableName, $database = "") {
		if (empty($database)) {
			$this->iDatabase = $GLOBALS['gPrimaryDatabase'];
		} else {
			$this->iDatabase = $database;
		}
		$this->iPrimaryTable = new DataTable($tableName);
	}

	public static function returnPageControls() {
		$pageControls = array();
		if (array_key_exists("page_controls", $GLOBALS['gPageRow'])) {
			foreach ($GLOBALS['gPageRow']['page_control'] as $row) {
				$pageControls[$row['column_name']][$row['control_name']] = DataSource::massageControlValue($row['control_name'], $row['control_value']);
			}
		} else {
			$resultSet = executeReadQuery("select * from page_controls where page_id = ?", $GLOBALS['gPageId']);
			while ($row = getNextRow($resultSet)) {
				$pageControls[$row['column_name']][$row['control_name']] = DataSource::massageControlValue($row['control_name'], $row['control_value']);
			}
			freeResult($resultSet);
		}
		return $pageControls;
	}

	public static function massageControlValue($controlName, $controlValue) {
		if (empty($thisObject)) {
			$thisObject = $GLOBALS['gPageObject'];
		}
		if ($controlValue === "false") {
			$controlValue = false;
		} else if ($controlValue === "true") {
			$controlValue = true;
		}
		if ($controlName == "choices" && is_string($controlValue) && substr($controlValue, 0, strlen("array(")) == "array(" && substr($controlValue, -1) == ")") {
			$controlValue = "return " . $controlValue;
		}
		if ($controlName == "choices" && is_string($controlValue) && substr($controlValue, 0, strlen("[")) == "[" && substr($controlValue, -1) == "]") {
			$controlValue = "return " . $controlValue;
		}
		if (is_string($controlValue) && startsWith($controlValue, "return ") && $controlName != "form_label") {
			if (substr($controlValue, -1) != ";") {
				$controlValue .= ";";
			}
			$controlValue = str_replace("\\\$this->iDatabase", "\\\$GLOBALS['gPrimaryDatabase']", $controlValue);
			$controlValue = str_replace("\\\$this", "\\\$GLOBALS['gPageObject']", $controlValue);
			$controlValue = str_replace("\$this->iDatabase", "\$GLOBALS['gPrimaryDatabase']", $controlValue);
			$controlValue = str_replace("\$this", "\$GLOBALS['gPageObject']", $controlValue);
			try {
				$controlValue = eval($controlValue);
			} catch (Exception $e) {
				$GLOBALS['gPrimaryDatabase']->logError($controlValue . " - " . $e->getMessage());
			}
		} else if (is_string($controlValue) && startsWith($controlValue, "control-values")) {
			$controlValues = array();
			$controlValueText = substr($controlValue, strlen("control-values"));
			$controlValueLines = getContentLines($controlValueText);
			foreach ($controlValueLines as $thisLine) {
				$parts = explode(":", $thisLine, 2);
				if (empty($parts[0]) || empty($parts[1])) {
					continue;
				}
				$nameValue = explode("=", $parts[1], 2);
				if (empty($nameValue[0])) {
					continue;
				}
				if (!array_key_exists($parts[0], $controlValues)) {
					$controlValues[$parts[0]] = array();
				}
				$thisControlValue = DataSource::massageControlValue($nameValue[0], $nameValue[1]);
				$controlValues[$parts[0]][$nameValue[0]] = $thisControlValue;
			}
			$controlValue = $controlValues;
		}
		return $controlValue;
	}

	/**
	 * @return bool|DataTable
	 */
	function getPrimaryTable() {
		return $this->iPrimaryTable;
	}

	/**
	 * setNullLastSort - set whether null will appear last in the sort
	 * @param
	 *    true or flase
	 * @return
	 *    none
	 */
	function setNullLastSort($nullLastSort) {
		$this->iNullLastSort = $nullLastSort;
	}

	function setDontUpdateJoinTable($dontUpdateJoinTable) {
		$this->iDontUpdateJoinTable = $dontUpdateJoinTable;
	}

	/**
	 * enableTransactions - By default, the DataSource class uses transactions. If a program has database actions outside the DataSource class,
	 *    these can be turned off.
	 * @param
	 *    none
	 * @return
	 *    none
	 */
	function enableTransactions() {
		$this->iUseTransactions = true;
	}

	/**
	 * disableTransactions - By default, the DataSource class uses transactions. If a program has database actions outside the DataSource class,
	 *        these can be turned off.
	 * @param
	 *    none
	 * @return
	 *    none
	 */
	function disableTransactions() {
		$this->iUseTransactions = false;
	}

	/**
	 * setSaveOnlyPresent - By default, the DataSource class will update every row in the primary table which is in the name/value
	 *    pairs passed to the save function. At times, this is not desired. Primarily, this has to do with a HTML checkbox and tinyint
	 *    column, since an unchecked checkbox does not get passed in the $_POST array.
	 * @param
	 *    none
	 * @return
	 *    none
	 */
	function setSaveOnlyPresent($saveOnlyPresent) {
		$this->iPrimaryTable->setSaveOnlyPresent($saveOnlyPresent);
		if ($this->iJoinTable) {
			$this->iJoinTable->setSaveOnlyPresent($saveOnlyPresent);
		}
	}

	function ignoreVersion() {
		$this->iPrimaryTable->ignoreVersion();
	}

	/**
	 * setBeforeSaveFunction - set the name of a function that is called within the saveRecord method. This is used to massage data before writing to database.
	 * @param
	 *    Array in which the element 'object' is the page object and 'method' is the method to be executed
	 * @return
	 *    none
	 */
	function setBeforeSaveFunction($beforeSaveFunction) {
		if (is_array($beforeSaveFunction)) {
			$this->iBeforeSaveFunction = $beforeSaveFunction;
		}
	}

	/**
	 * setAfterSaveFunction - set the name of a function that is called within the saveRecord method. This is used to massage data after writing to database.
	 * @param
	 *    Array in which the element 'object' is the page object and 'method' is the method to be executed
	 * @return
	 *    none
	 */
	function setAfterSaveFunction($afterSaveFunction) {
		if (is_array($afterSaveFunction)) {
			$this->iAfterSaveFunction = $afterSaveFunction;
		}
	}

	/**
	 * setFilterFunction - set the name of a function that is called within the getDataList method. This is used to filter the list coming from the table.
	 * @param
	 *    Array in which the element 'object' is the page object and 'method' is the method to be executed
	 * @return
	 *    none
	 */
	function setFilterFunction($filterFunction) {
		$this->iFilterFunction = $filterFunction;
	}

	function addSearchWhereStatement($searchWhereStatement) {
		$this->iSearchWhereStatements[] = $searchWhereStatement;
	}

	/**
	 * setConditionalJoinFunction - set the name of a function that is called within save changes for the join table.
	 *    This is used to determine if the join record should be created.
	 * @param
	 *    Array in which the element 'object' is the page object and 'method' is the method to be executed
	 * @return
	 *    none
	 */
	function setConditionalJoinFunction($conditionalJoinFunction) {
		if (is_array($conditionalJoinFunction)) {
			$this->iConditionalJoinFunction = $conditionalJoinFunction;
		}
	}

	/**
	 * setJoinTable - set the name and where part of a join table. This table will be updated and treated as if it is part of the primary table
	 * @param
	 *    $tableName - Name of the join table
	 *    $primaryTableJoinColumn - column in the primary table that joins with this table
	 *    $joinColumn - column in the join table that is connected to the primary table
	 *    $outerJoin - true if the join table record is not required to exist
	 * @return
	 *    none
	 */
	function setJoinTable($tableName, $primaryTableJoinColumn = "", $joinColumn = "", $outerJoin = false) {
		if (!$tableName) {
			$this->iJoinTable = false;
		} else {
			$this->iJoinTable = new DataTable($tableName);
			$this->iJoinOuter = $outerJoin;
			$this->iJoinPrimaryColumn = (empty($primaryTableJoinColumn) ? $this->iPrimaryTable->getPrimaryKey() : $primaryTableJoinColumn);
			$this->iJoinColumn = (empty($joinColumn) ? $this->iPrimaryTable->getPrimaryKey() : $joinColumn);
			$this->iJoinWhere = $this->iPrimaryTable->getName() . "." . $this->iJoinPrimaryColumn . " = " . $tableName . "." . $this->iJoinColumn;
		}
	}

	/**
	 * @return DataTable
	 */
	function getJoinTable() {
		return $this->iJoinTable;
	}

	/**
	 * setSortOrder - Set the sort order of the data list that is generated.
	 * @param
	 *    either a string of fieldname and directional values, such as "fieldname,fieldname2 desc" or an array of
	 *    fieldnames and an optional array of directional strings
	 * @return
	 *    none
	 */
	function setSortOrder($columnNames, $columnDirectionArray = array()) {
		$this->iSortOrder = array();
		if (!is_array($columnNames)) {
			$columnNamesString = $columnNames;
			$columnNames = array();
			$sortOrderParts = explode(",", $columnNamesString);
			$columnDirectionArray = array();
			foreach ($sortOrderParts as $thisPart) {
				$theseBits = explode(" ", $thisPart);
				if (count($theseBits) < 1 || count($theseBits) > 2) {
					continue;
				} else {
					if (count($theseBits) == 1) {
						$theseBits[] = "asc";
					}
				}
				$columnNames[] = reset($theseBits);
				$columnDirectionArray[] = next($theseBits);
			}
		}
		foreach ($columnNames as $index => $columnName) {
			if (array_key_exists($columnName, $this->iAdditionalColumns)) {
				$thisColumnName = $columnName;
			} else {
				$thisColumnName = $this->iPrimaryTable->columnExists($columnName);
				if ($thisColumnName === false && $this->iJoinTable) {
					$thisColumnName = $this->iJoinTable->columnExists($columnName);
					if ($thisColumnName) {
						$thisColumnName = $this->iJoinTable->getName() . "." . $thisColumnName;
					}
				} else {
					if ($thisColumnName) {
						$thisColumnName = $this->iPrimaryTable->getName() . "." . $thisColumnName;
					}
				}
			}
			if ($thisColumnName) {
				$this->iSortOrder[] = array("column_name" => $thisColumnName, "sort_direction" => ($columnDirectionArray[$index] == "desc" ? "desc" : "asc"));
			}
		}
	}

	/**
	 * addExcludeUpdateColumns - Provide the class with a list of column names that should not be included in the update for
	 *    the table.
	 * @param
	 *    either a string of comma separated fieldnames, such as "fieldname,fieldname2" or an array of fieldnames
	 * @return
	 *    none
	 */
	function addExcludeUpdateColumns($columnNames) {
		$this->iPrimaryTable->addExcludeUpdateColumns($columnNames);
		if ($this->iJoinTable) {
			$this->iJoinTable->addExcludeUpdateColumns($columnNames);
		}
	}

	/**
	 * setReverseSort - set the sort order to be in reverse order
	 * @param
	 *    flag to indicate whether the sort order is reversed or not
	 * @return
	 *    none
	 */
	function setReverseSort($reverseSort) {
		$this->iReverseSort = $reverseSort;
	}

	/**
	 * getReverseSort - get the sort order reverse flag
	 * @param
	 *    none
	 * @return
	 *    true or false
	 */
	function getReverseSort() {
		return $this->iReverseSort;
	}

	/**
	 * setSearchFields - set fields that can be searched.
	 * @param
	 *    Can be an array of field names or a comma separated list of fieldnames.
	 * @return
	 *    none
	 */
	function setSearchFields($searchFields) {
		foreach ($this->iPrimaryTable->getColumns() as $columnName => $thisColumn) {
			$thisColumn->setSearchable(false);
		}
		if ($this->iJoinTable) {
			foreach ($this->iJoinTable->getColumns() as $columnName => $thisColumn) {
				$thisColumn->setSearchable(false);
			}
		}
		$this->addSearchFields($searchFields);
	}

	/**
	 * addSearchFields - Add to the list of search fields
	 * @param
	 *    can be an array of field names or a comma separated list of fieldnames.
	 * @return
	 *    none
	 */
	function addSearchFields($searchFields) {
		$this->iSearchFieldsSet = true;
		if (!is_array($searchFields)) {
			$searchFields = explode(",", $searchFields);
		}
		foreach ($searchFields as $columnName) {
			$thisColumn = $this->iPrimaryTable->getColumns($columnName);
			if (!$thisColumn && $this->iJoinTable) {
				$thisColumn = $this->iJoinTable->getColumns($columnName);
			}

			if ($thisColumn) {
				$thisColumn->setSearchable(true);
			}
		}
	}

	function getSearchFields() {
		$columns = $this->iPrimaryTable->getColumns();
		if ($this->iJoinTable) {
			$columns = array_merge($columns, $this->iJoinTable->getColumns());
		}
		$searchFields = array();
		foreach ($columns as $thisColumn) {
			if (!$thisColumn->isSearchable()) {
				continue;
			}
			$searchFields[] = $thisColumn;
		}
		return $searchFields;
	}

	function searchFieldsSet() {
		return $this->iSearchFieldsSet;
	}

	/**
	 * removeSearchFields - Remove fields from the list of search fields
	 * @param
	 *    can be an array of field names or a comma separated list of fieldnames.
	 * @return
	 *    none
	 */
	function removeSearchFields($searchFields) {
		if (!is_array($searchFields)) {
			$searchFields = explode(",", $searchFields);
		}
		foreach ($searchFields as $columnName) {
			$thisColumn = $this->iPrimaryTable->getColumns($columnName);
			if (!$thisColumn && $this->iJoinTable) {
				$thisColumn = $this->iJoinTable->columnExists($columnName);
			}

			if ($thisColumn) {
				$thisColumn->setSearchable(false);
			}
		}
	}

	/**
	 * setFilterText - Set text that the data list will search for in the search fields.
	 * @param
	 *    text that will be used to filter the list
	 * @return
	 *    none
	 */
	function setFilterText($filterText) {
		$this->iFilterText = $filterText;
	}

	/**
	 * getFilterText - returns text that the data list is using to filter
	 * @param
	 *    none
	 * @return
	 *    filter text
	 */
	function getFilterText() {
		return $this->iFilterText;
	}

	/**
	 * getQueryString - returns text of the query string
	 * @param
	 *    none
	 * @return
	 *    query string
	 */
	function getQueryString() {
		return $this->iQueryString;
	}

	/**
	 * getQueryWhere - returns text of the query where statement
	 * @param
	 *    none
	 * @return
	 *    query where string
	 */
	function getQueryWhere() {
		return array("where_statement" => $this->iQueryWhere, "where_parameters" => $this->iQueryParameters);
	}

	/**
	 * setFilterWhere - set a unique where statement that will be used to filter the data list
	 * @param
	 *    Content of the where statement of a query, not including the "where"
	 * @return
	 *    none
	 */
	function setFilterWhere($whereStatement) {
		$this->iFilterWhere = $whereStatement;
	}

	/**
	 * addFilterWhere - add to the where statement that will be used to filter the data list
	 * @param
	 *    Content of the where statement of a query, not including the "where"
	 * @return
	 *    none
	 */
	function addFilterWhere($whereStatement) {
		if (empty($this->iFilterWhere)) {
			$this->iFilterWhere = $whereStatement;
		} else {
			if (!empty($whereStatement)) {
				$this->iFilterWhere = "(" . $this->iFilterWhere . ") and (" . $whereStatement . ")";
			}
		}
	}

	/**
	 * getFilterWhere - get the unique where statement that will be used to filter the data list
	 * @param
	 *    none
	 * @return
	 *    the filter where statement
	 */
	function getFilterWhere() {
		return $this->iFilterWhere;
	}

	/**
	 * addSearchableSubfield - add searchable subfield to the data list. This is used for subselects.
	 * @param
	 *    array of subfield
	 *        referenced_table_name - the referenced table
	 *        referenced_column_name - the referenced column
	 *        foreign_key - the column in the table that is a foreign key
	 *        description - The description field that will be searched
	 * @return
	 *    none
	 */
	function addSearchableSubfield($columnArray) {
		$this->iSearchableSubfields[] = $columnArray;
		return true;
	}

	/**
	 * getDataListCount - get the count of the last data list gotten
	 * @param
	 *    none
	 * @return
	 *    data list count
	 */
	function getDataListCount() {
		return $this->iDataListCount;
	}

	/**
	 * setPrimaryId - Set the value of the primary ID that will be the current record
	 * @param
	 *    primary ID value
	 * @return
	 *    none
	 */
	function setPrimaryId($primaryId) {
		$this->iPrimaryId = $primaryId;
	}

	/**
	 * getPrimaryId - Get the value of the primary ID that will be the current record
	 * @param
	 *    none
	 * @return
	 *    primary ID value
	 */
	function getPrimaryId() {
		return $this->iPrimaryId;
	}

	/**
	 * getJoinPrimaryId - Get the value of the primary ID for the join table
	 * @param
	 *    none
	 * @return
	 *    join table primary ID value
	 */
	function getJoinPrimaryId() {
		return $this->iJoinPrimaryId;
	}

	/**
	 * getRow - Get row of the primary table that corresponds to the primary ID. If no primary ID is set, an empty row will be returned.
	 * @param
	 *    primary ID of row to return. If no argument is passed, use the primary ID set in this object.
	 * @return
	 *    false if row is not found, otherwise row
	 */
	function getRow($primaryId = "") {
		if (func_num_args() == 0) {
			$primaryId = $this->iPrimaryId;
		} else {
			$this->iPrimaryid = $primaryId;
		}
		if (empty($primaryId)) {
			return array();
		}
		$queryString = "select * from " . $this->getTableList();
		$whereStatement = ($this->iJoinTable && !$this->iJoinOuter ? $this->iJoinWhere : "");
		if (!empty($this->iFilterWhere)) {
			if (!empty($whereStatement)) {
				$whereStatement .= " and ";
			}
			$whereStatement .= "(" . $this->iFilterWhere . ")";
		}
		$resultSet = executeReadQuery($queryString . " where " . (empty($whereStatement) ? "" : $whereStatement . " and ") .
			$this->iPrimaryTable->getName() . "." . $this->iPrimaryTable->getPrimaryKey() . " = ?", $primaryId);
		if (!$row = $this->iDatabase->getNextRow($resultSet)) {
			$this->iErrorMessage = getSystemMessage("not_found");
			return false;
		}
		freeResult($resultSet);
		return $row;
	}

	/**
	 * getTableList - returns the list of tables used in the select statement. This is only different than the primary table
	 * if there is a join table
	 * @param
	 *    none
	 * @return
	 *    comma separated list of tables for use in a select statement
	 */
	function getTableList() {
		$tableList = "";
		if ($this->iJoinTable) {
			if ($this->iJoinOuter) {
				$tableList = $this->iJoinTable->getName() . " right join " . $this->iPrimaryTable->getName() . " on " . $this->iJoinWhere;
			} else {
				if ($this->iJoinPrimaryColumn == $this->iJoinColumn) {
					$tableList = $this->iJoinTable->getName() . " join " . $this->iPrimaryTable->getName() . " using (" . $this->iJoinColumn . ")";
					$this->iJoinWhere = "";
				} else {
					$tableList = $this->iJoinTable->getName() . "," . $this->iPrimaryTable->getName();
				}
			}
		} else {
			$tableList = $this->iPrimaryTable->getName();
		}
		return $tableList;
	}

	/**
	 * getNextRecordId - Based on the sort order of the data list and the primary ID of the current row, return the ID of the next record.
	 * @param
	 *    none
	 * @return
	 *    Id of Next row. Empty if there is no next row.
	 */
	function getNextRecordId() {
		if (!is_array($this->iDataList)) {
			$this->getDataList();
		}
		if (empty($this->iPrimaryId) || !array_key_exists($this->iPrimaryId, $this->iDataList)) {
			return false;
		}
		reset($this->iDataList);
		$thisArray = current($this->iDataList);
		$nextRecordId = $thisArray[$this->iPrimaryTable->getPrimaryKey()];
		while ($nextRecordId != $this->iPrimaryId) {
			if (next($this->iDataList) === false) {
				break;
			}
			$thisArray = current($this->iDataList);
			$nextRecordId = $thisArray[$this->iPrimaryTable->getPrimaryKey()];
		}
		if (next($this->iDataList) === false) {
			return false;
		} else {
			$thisArray = current($this->iDataList);
			$nextRecordId = $thisArray[$this->iPrimaryTable->getPrimaryKey()];
			return $nextRecordId;
		}
	}

	/**
	 * getDataList - return array of arrays. Each array will be a row from the database of the primary table.
	 * @param
	 *    array of parameter values:
	 *        include_fields: comma separated list of fields or array of field names to include in the list: default: all fields
	 *        exclude_fields: comma separated list of fields or array of field names to exclude from the list: default: none
	 *        start_row: number of row to start with. Default: 1
	 *        row_count: number of rows to return. Default: all
	 */
	function getDataList($parameterList = array()) {
		$parameterList = array_merge(array("include_fields" => array(),
			"exclude_fields" => array(),
			"start_row" => 1,
			"row_count" => 0,
			"group_by" => ""), $parameterList);
		if (!is_array($parameterList['include_fields'])) {
			$parameterList['include_fields'] = explode(",", $parameterList['include_fields']);
		}
		if (!is_array($parameterList['exclude_fields'])) {
			$parameterList['exclude_fields'] = explode(",", $parameterList['exclude_fields']);
		}
		$queryString = "select *";
		foreach ($this->iAdditionalColumns as $columnName => $thisColumn) {
			if ($thisColumn->getControlValue("select_value")) {
				$queryString .= ",(" . $thisColumn->getControlValue("select_value") . ") " . $columnName;
			}
		}
		$queryString .= " from ";
		$whereStatement = "";
		$queryString .= $this->getTableList();
		$whereParameters = array();
		$columns = $this->iPrimaryTable->getColumns();
		if (!empty($this->iFilterText) && empty($parameterList['export_all'])) {
			if ($this->iJoinTable) {
				$columns = array_merge($columns, $this->iJoinTable->getColumns());
			}
			foreach ($columns as $thisColumn) {
				if (!$thisColumn->isSearchable()) {
					continue;
				}
				$thisWherePart = "";
				$thisWhereParameters = array();
				switch ($thisColumn->getControlValue("mysql_type")) {
					case "date":
						$thisWherePart2 = $this->iDatabase->makeDateParameter($this->iFilterText);
						if (!empty($thisWherePart2) && $thisWherePart2 != "NULL") {
							$thisWherePart = $thisColumn->getFullName() . " = ?";
							$thisWhereParameters[] = $thisWherePart2;
						}
						break;
					case "datetime":
						$thisWherePart2 = $this->iDatabase->makeDateTimeParameter($this->iFilterText);
						if (!empty($thisWherePart2) && $thisWherePart2 != "NULL") {
							$thisWherePart = $thisColumn->getFullName() . " = ?";
							$thisWhereParameters[] = $thisWherePart2;
						}
						break;
					case "tinyint":
						$thisWherePart = $thisColumn->getFullName() . " = " . (empty($this->iFilterText) ? "0" : "1");
						break;
					case "bigint":
					case "int":
					case "decimal":
						if (is_numeric($this->iFilterText)) {
							$thisWherePart2 = $this->iDatabase->makeNumberParameter($this->iFilterText);
							if (!empty($thisWherePart2) && $thisWherePart2 != "NULL") {
								$thisWherePart = $thisColumn->getFullName() . " = ?";
								$thisWhereParameters[] = $thisWherePart2;
							}
						}
						break;
					case "select":
						$thisColumnName = $thisColumn->getFullName();
						$mysqlDataType = $thisColumn->getControlValue("mysql_type");
						if ($mysqlDataType == "int" || $mysqlDataType == "bigint" || $mysqlDataType == "decimal") {
							$thisWherePart2 = $this->iDatabase->makeNumberParameter($this->iFilterText);
						} else {
							$thisWherePart2 = $this->iFilterText;
						}
						if (!empty($thisWherePart2) && $thisWherePart2 != "NULL") {
							$thisWherePart = $thisColumn->getFullName();
							$filterText = ($thisColumn->isSearchExact() || $thisColumn->getControlValue("exact_search") || $thisColumn->getControlValue("exact_first") ? "" : "%") . $this->iFilterText . ($thisColumn->isSearchExact() || $thisColumn->getControlValue("exact_search") ? "" : "%");
							$thisWherePart .= ($thisColumn->isSearchExact() || $thisColumn->getControlValue("exact_search") ? " = " : " like ") . "?";
							$thisWhereParameters[] = $filterText;
						}
						break;
					default:
						$thisWherePart = $thisColumn->getFullName();
						$filterText = ($thisColumn->isSearchExact() || $thisColumn->getControlValue("exact_search") || $thisColumn->getControlValue("exact_first") ? "" : "%") . $this->iFilterText . ($thisColumn->isSearchExact() || $thisColumn->getControlValue("exact_search") ? "" : "%");
						$thisWherePart .= ($thisColumn->isSearchExact() || $thisColumn->getControlValue("exact_search") ? " = " : " like ") . "?";
						$thisWhereParameters[] = $filterText;
						break;
				}
				if (!empty($thisWherePart)) {
					$whereStatement .= (empty($whereStatement) ? "" : " or ") . $thisWherePart;
					$whereParameters = array_merge($whereParameters, $thisWhereParameters);
				}
			}
			foreach ($this->iSearchableSubfields as $subField) {
				if ($subField['foreign_key'] == $this->iJoinPrimaryColumn) {
					continue;
				}
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . (empty($subField['table_name']) ? "" : $subField['table_name'] . ".") . $subField['foreign_key'] . " in (select " . $subField['referenced_column_name'] . " from " . $subField['referenced_table_name'] .
					" where (";
				if (!is_array($subField['description'])) {
					$subField['description'] = array($subField['description']);
				}
				$descriptionUsed = false;
				foreach ($subField['description'] as $description) {
					$whereStatement .= ($descriptionUsed ? " or " : "") . $description . " like ?";
					$descriptionUsed = true;
					$whereParameters[] = "%" . $this->iFilterText . "%";
				}
				$whereStatement .= ")";
				if (!empty($subField['extra_where'])) {
					$whereStatement .= " and " . $subField['extra_where'];
				}
				$whereStatement .= ")";
			}
		}
		foreach ($this->iSearchWhereStatements as $thisWhereStatement) {
			$whereStatement .= (empty($whereStatement) ? "" : " or ") . $thisWhereStatement;
		}

		if ($this->iJoinTable && !$this->iJoinOuter && !empty($this->iJoinWhere)) {
			$whereStatement = $this->iJoinWhere . (empty($whereStatement) ? "" : " and (" . $whereStatement . ")");
		}
		if (!empty($whereStatement)) {
			$whereStatement = "(" . $whereStatement . ")";
		}

		if (!empty($this->iFilterWhere)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(" . $this->iFilterWhere . ")";
		}
		if ($this->iPrimaryTable->isLimitedByClient()) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(" . $this->iPrimaryTable->getName() . ".client_id = " . $GLOBALS['gClientId'] . ")";
		} else if (!$this->iPrimaryTable->isForceNoLimitByClient() && (empty($this->iJoinTable) || !$this->iJoinTable->isLimitedByClient())) {

			# make sure subtable is limited by client ID for the major tables

			if ($this->iPrimaryTable->columnExists("contact_id")) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(" . $this->iPrimaryTable->getName() . ".contact_id is null or " . $this->iPrimaryTable->getName() . ".contact_id in (select contact_id from contacts where client_id = " . $GLOBALS['gClientId'] . "))";
			}
			if ($this->iPrimaryTable->columnExists("user_id")) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(" . $this->iPrimaryTable->getName() . ".user_id is null or " . $this->iPrimaryTable->getName() . ".user_id in (select user_id from users where superuser_flag = 1 or client_id = " . $GLOBALS['gClientId'] . "))";
			}
			if ($this->iPrimaryTable->columnExists("order_id")) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(" . $this->iPrimaryTable->getName() . ".order_id is null or " . $this->iPrimaryTable->getName() . ".order_id in (select order_id from orders where client_id = " . $GLOBALS['gClientId'] . "))";
			}
			if ($this->iPrimaryTable->columnExists("product_id")) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(" . $this->iPrimaryTable->getName() . ".product_id is null or " . $this->iPrimaryTable->getName() . ".product_id in (select product_id from products where client_id = " . $GLOBALS['gClientId'] . "))";
			}
		}
		if ($this->iJoinTable && $this->iJoinTable->isLimitedByClient()) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(" . $this->iJoinTable->getName() . ".client_id = " .
				$GLOBALS['gClientId'] . ($this->iJoinOuter ? " or " . $this->iJoinTable->getName() . ".client_id is null" : "") . ")";
		}

		$countQuery = "select count(*) from " . $this->getTableList() . (empty($whereStatement) ? "" : " where " . $whereStatement) .
			(empty($parameterList['group_by']) ? "" : " group by " . $parameterList['group_by']);

		if (empty($_POST['_show_selected']) || empty($_POST['_show_unselected'])) {
			$count = getCachedData("data_source_count", $countQuery . ":" . jsonEncode($whereParameters));
		} else {
			$count = false;
		}
		if (!$count || $count < 1000) {
			$resultSet = executeReadQuery($countQuery, $whereParameters);
			if ($row = $this->iDatabase->getNextRow($resultSet)) {
				$count = $row['count(*)'];
			} else {
				$count = 0;
			}
			freeResult($resultSet);
			if (empty($_POST['_show_selected'])) {
				setCachedData("data_source_count", $countQuery . ":" . jsonEncode($whereParameters), $count, .1);
			}
		}
		$this->iDataListCount = $count;
		if ($count > 5000 && !$parameterList['export_all']) {
			$primarySortOrderField = $this->getPrimarySortOrderField();
			if (!empty($primarySortOrderField) && array_key_exists($primarySortOrderField, $columns)) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . $primarySortOrderField . " is not null";
			}
			$this->iNullLastSort = false;
		}

		if (!empty($whereStatement)) {
			$queryString .= " where " . $whereStatement;
		}
		$sortOrder = $this->getSortOrder();

		if (!empty($parameterList['group_by'])) {
			$queryString .= " group by " . $parameterList['group_by'];
		}

		if (!empty($sortOrder)) {
			$queryString .= " order by " . $sortOrder;
		}

		$this->iQueryString = $queryString;
		$this->iQueryWhere = $whereStatement;
		$this->iQueryParameters = $whereParameters;
		$this->iDataList = array();
		if (empty($this->iFilterFunction)) {
			if ($parameterList['row_count'] > 0 || $parameterList['start_row'] > 1) {
				if (empty($parameterList['start_row'])) {
					$parameterList['start_row'] = 0;
				}
				if (empty($parameterList['row_count'])) {
					$parameterList['row_count'] = "18446744073709551615";
				}
				$queryString .= " limit " . $parameterList['start_row'] . "," . (empty($this->iFilterFunction) ? $parameterList['row_count'] : "18446744073709551615");
			}
		}
		$resultSet = executeReadQuery($queryString, $whereParameters);
		while ($row = $this->iDatabase->getNextRow($resultSet)) {
			$thisArray = array();
			foreach ($row as $columnName => $columnData) {
				if (is_null($columnData)) {
					$columnData = "";
				}
				if ((in_array($columnName, $parameterList['include_fields']) || count($parameterList['include_fields']) == 0) &&
					!in_array($columnName, $parameterList['exclude_fields'])) {
					$thisArray[$columnName] = $columnData;
				}
			}
			if (!empty($this->iFilterFunction)) {
				if (is_array($this->iFilterFunction) && !empty($this->iFilterFunction['object'])) {
					$returnValue = call_user_func(array($this->iFilterFunction['object'], $this->iFilterFunction['method']), $row);
				} else {
					if (is_array($this->iFilterFunction)) {
						$returnValue = call_user_func($this->iFilterFunction['method'], $row);
					} else {
						$returnValue = call_user_func($this->iFilterFunction, $row);
					}
				}
				if (!$returnValue) {
					continue;
				}
			}
			$this->iDataList[] = $thisArray;
		}
		freeResult($resultSet);
		if (!empty($this->iFilterFunction)) {
			$originalDataList = $this->iDataList;
			$this->iDataListCount = count($originalDataList);
			$this->iDataList = array();
			$skipCount = 0;
			foreach ($originalDataList as $dataRow) {
				if (($parameterList['start_row'] - 1) >= $skipCount) {
					$skipCount++;
					continue;
				}
				if ($parameterList['row_count'] && count($this->iDataList) >= $parameterList['row_count']) {
					break;
				}
				$this->iDataList[] = $dataRow;
			}
		}
		return $this->iDataList;
	}

	private function getPrimarySortOrderField() {
		$sortOrderField = false;
		foreach ($this->iSortOrder as $sortField) {
			$sortOrderField = $sortField['column_name'];
			break;
		}
		return $sortOrderField;
	}

	/**
	 * getSortOrder - returns a sort order string that the client can use to see which sort order is being used
	 * @param
	 *    flag indicating whether to just pass fields. Just passing fields would be used for the UI interface.
	 * @return
	 *    String representing the sort order, such as "fieldname,fieldname2 desc"
	 */
	function getSortOrder($columnsOnly = false) {
		$sortOrder = "";
		foreach ($this->iSortOrder as $sortField) {
			if (!empty($sortOrder)) {
				$sortOrder .= ",";
			} else {
				$primaryKey = $this->iPrimaryTable->getName() . "." . $this->iPrimaryTable->getPrimaryKey();
				$sortOrder = ($this->iNullLastSort && $primaryKey != $sortField['column_name'] ? "ISNULL(" . $sortField['column_name'] . ")," : "");
			}
			$sortOrder .= $sortField['column_name'] . ($columnsOnly ? "" : ($sortField['sort_direction'] == ($this->iReverseSort ? "asc" : "desc") ? " desc" : ""));
		}
		return $sortOrder;
	}

	/**
	 * getPreviousRecordId - Based on the sort order of the data list and the primary ID of the current row, return the ID of the previous record.
	 * @param
	 *    none
	 * @return
	 *    Id of Next row. Empty if there is no next row.
	 */
	function getPreviousRecordId() {
		if (!is_array($this->iDataList)) {
			$this->getDataList();
		}
		if (empty($this->iPrimaryId) || !array_key_exists($this->iPrimaryId, $this->iDataList)) {
			return false;
		}
		reset($this->iDataList);
		$thisArray = current($this->iDataList);
		$prevRecordId = $thisArray[$this->iPrimaryTable->getPrimaryKey()];
		while ($prevRecordId != $this->iPrimaryId) {
			if (next($this->iDataList) === false) {
				break;
			}
			$thisArray = current($this->iDataList);
			$prevRecordId = $thisArray[$this->iPrimaryTable->getPrimaryKey()];
		}
		if (prev($this->iDataList) === false) {
			return false;
		} else {
			$thisArray = current($this->iDataList);
			$prevRecordId = $thisArray[$this->iPrimaryTable->getPrimaryKey()];
			return $prevRecordId;
		}
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
	 * saveRecord - Save the data to the database, based on the parameters passed in. If the field "version" is in the name/value pairs, it will
	 *    be used for optimistic locking
	 * @param
	 *    Parameter List:
	 *        table_name: table name of table. Default: primary table.
	 *        primary_id: key of row to be updated in database. Empty value means the row is to be added. Default: primary ID.
	 *        name_values: array of name/value pairs
	 * @return
	 *    false if there is any kind of error
	 *    the ID of the row of the primary table. Additionally, the ID of the row of the join table is set.
	 */
	function saveRecord($parameterList = array()) {
		$this->iColumnsChanged = 0;
		if ($this->iUseTransactions) {
			$this->iDatabase->startTransaction();
		}
		if (!array_key_exists("primary_id", $parameterList) && !empty($this->iPrimaryId)) {
			$parameterList['primary_id'] = $this->iPrimaryId;
		}
		$actionPerformed = (empty($parameterList['primary_id']) ? "insert" : "update");
		if (!empty($this->iBeforeSaveFunction)) {
			$returnValue = call_user_func(array($this->iBeforeSaveFunction['object'], $this->iBeforeSaveFunction['method']), $parameterList['name_values']);
			if (is_array($returnValue)) {
				$parameterList['name_values'] = $returnValue;
			} else {
				if ($returnValue !== true) {
					$this->iErrorMessage = $returnValue;
					if ($this->iUseTransactions) {
						rollbackTransaction();
					}
					return false;
				}
			}
		}
		if (!$this->iDontUpdateJoinTable && $this->iJoinTable && $this->iJoinPrimaryColumn != $this->iPrimaryTable->getPrimaryKey()) {
			$foreignKeyValue = $parameterList['name_values'][$this->iJoinPrimaryColumn];
			$createJoinRow = true;
			if (!empty($this->iConditionalJoinFunction)) {
				$createJoinRow = call_user_func(array($this->iConditionalJoinFunction['object'], $this->iConditionalJoinFunction['method']), $parameterList['name_values']);
			}
			if ($createJoinRow) {
				if (empty($parameterList[$this->iJoinColumn])) {
					$parameterList['name_values'][$this->iJoinColumn] = $foreignKeyValue;
				}
				$joinTableId = $this->iJoinTable->saveRecord(array_merge($parameterList, array("join_table" => true, "primary_id" => $parameterList['name_values'][$this->iJoinTable->getPrimaryKey()])));
				$this->iColumnsChanged += $this->iJoinTable->getColumnsChanged();
				if (!$joinTableId) {
					$this->iErrorMessage = $this->iJoinTable->getErrorMessage();
					$this->iSqlErrorNumber = $this->iJoinTable->getErrorNumber();
					if ($this->iUseTransactions) {
						$this->iDatabase->rollbackTransaction();
					}
					return false;
				}
				$parameterList['name_values'][$this->iJoinPrimaryColumn] = $joinTableId;
			}
		}
		$primaryId = $this->iPrimaryTable->saveRecord($parameterList);
		$this->iColumnsChanged += $this->iPrimaryTable->getColumnsChanged();
		if (!$primaryId) {
			$this->iErrorMessage = $this->iPrimaryTable->getErrorMessage();
			$this->iSqlErrorNumber = $this->iPrimaryTable->getErrorNumber();
			if ($this->iUseTransactions) {
				$this->iDatabase->rollbackTransaction();
			}
			return false;
		}
		if ($this->iJoinTable && $this->iJoinPrimaryColumn == $this->iPrimaryTable->getPrimaryKey()) {
			$foreignKeyValue = $primaryId;
			$createJoinRow = true;
			if (!empty($this->iConditionalJoinFunction)) {
				$createJoinRow = $this->iConditionalJoinFunction['object']->$this->iConditionalJoinFunction['method']($parameterList['name_values']);
			}
			if ($createJoinRow) {
				if (empty($parameterList[$this->iJoinColumn])) {
					$parameterList['name_values'][$this->iJoinColumn] = $foreignKeyValue;
				}
				$joinTableId = $this->iJoinTable->saveRecord(array_merge($parameterList, array("join_table" => true, "primary_id" => $parameterList['name_values'][$this->iJoinTable->getPrimaryKey()])));
				$this->iColumnsChanged += $this->iJoinTable->getColumnsChanged();
				if (!$joinTableId) {
					$this->iErrorMessage = $this->iJoinTable->getErrorMessage();
					$this->iSqlErrorNumber = $this->iJoinTable->getErrorNumber();
					if ($this->iUseTransactions) {
						$this->iDatabase->rollbackTransaction();
					}
					return false;
				}
				$parameterList['name_values'][$this->iJoinPrimaryColumn] = $joinTableId;
			}
		}
		if (!empty($this->iAfterSaveFunction)) {
			$parameterList['name_values']['primary_id'] = $primaryId;
			$returnValue = call_user_func(array($this->iAfterSaveFunction['object'], $this->iAfterSaveFunction['method']), $parameterList['name_values'], $actionPerformed);
			if ($returnValue !== true) {
				$this->iErrorMessage = ($returnValue === false ? "Error saving record" : $returnValue);
				if ($this->iUseTransactions) {
					rollbackTransaction();
				}
				return false;
			}
		}
		if ($this->iUseTransactions) {
			$this->iDatabase->commitTransaction();
		}
		$this->iPrimaryId = $primaryId;
		$this->iJoinPrimaryId = $joinTableId;
		return $primaryId;
	}

	/**
	 * deleteRecord - Delete row from table
	 * @param
	 *    Parameter List:
	 *        primary_id: key of row to be deleted from database. Default: primary ID.
	 */
	function deleteRecord($parameterList = array()) {
		if ($this->iUseTransactions) {
			$this->iDatabase->startTransaction();
		}
		if (!$this->iPrimaryTable->deleteRecord($parameterList)) {
			$this->iErrorMessage = $this->iPrimaryTable->getErrorMessage();
			$this->iSqlErrorNumber = $this->iPrimaryTable->getErrorNumber();
			if ($this->iUseTransactions) {
				$this->iDatabase->rollbackTransaction();
			}
			return false;
		}
		if ($this->iUseTransactions) {
			$this->iDatabase->commitTransaction();
		}
		return true;
	}

	/**
	 * getForeignKeyList - Return list of foreign keys. This will come from the column meta data
	 * @param
	 *    none
	 * @return
	 *    array of foreign keys
	 */
	function getForeignKeyList() {
		$foreignKeys = $this->iPrimaryTable->getForeignKeyList();
		if ($this->iJoinTable) {
			$foreignKeys = array_merge($foreignKeys, $this->iJoinTable->getForeignKeyList());
		}
		return $foreignKeys;
	}

	/**
	 * getPageControls - read controls from the page controls table for the current page and apply to the columns in this data source
	 */
	function getPageControls() {
		$pageControls = array();
		if (array_key_exists("page_controls", $GLOBALS['gPageRow'])) {
			foreach ($GLOBALS['gPageRow']['page_controls'] as $row) {
				$this->addColumnControl($row['column_name'], $row['control_name'], $row['control_value']);
				$pageControls[$row['column_name']][$row['control_name']] = DataSource::massageControlValue($row['control_name'], $row['control_value']);
			}
		} else {
			$resultSet = executeReadQuery("select * from page_controls where page_id = ?", $GLOBALS['gPageId']);
			while ($row = $this->iDatabase->getNextRow($resultSet)) {
				$this->addColumnControl($row['column_name'], $row['control_name'], $row['control_value']);
				$pageControls[$row['column_name']][$row['control_name']] = DataSource::massageControlValue($row['control_name'], $row['control_value']);
			}
			freeResult($resultSet);
		}
		return $pageControls;
	}

	/**
	 * addColumnControl - add column controls. This can also add additional columns if the column name is not in the table(s)
	 * @param
	 *    The key of the column is the data column name.
	 *    The control name
	 *    The control value
	 */
	function addColumnControl($columnName, $controlName, $controlValue) {
		$thisColumn = $this->iPrimaryTable->getColumns($columnName);
		if (!$thisColumn && $this->iJoinTable) {
			$thisColumn = $this->iJoinTable->getColumns($columnName);
		}
		if ($thisColumn) {
			$thisColumn->setControlValue($controlName, $controlValue);
		} else {
			if (!array_key_exists($columnName, $this->iAdditionalColumns)) {
				$this->iAdditionalColumns[$columnName] = new DataColumn($columnName);
				$this->iAdditionalColumns[$columnName]->setControlValue("primary_table", $this->iPrimaryTable->getName());
				$this->iAdditionalColumns[$columnName]->setControlValue("column_name", $columnName);
			}
			$this->iAdditionalColumns[$columnName]->setControlValue($controlName, $controlValue);
		}
	}

	/**
	 * getAdditionalColumns - get the array of independent columns
	 *
	 * @return an array of column objects for all independent (not part of primary or join table) columns
	 */
	function getAdditionalColumns() {
		return $this->iAdditionalColumns;
	}

	/**
	 * getDatabase - get the database object used by this data source
	 *
	 * @return database object
	 */
	function getDatabase() {
		return $this->iDatabase;
	}

	function addColumnLikeColumn($newColumnName, $tableName, $columnName) {
		$tableObject = new DataTable($tableName);
		$columnObject = $tableObject->getColumns($columnName);
		$controls = $columnObject->getAllControlValues();
		foreach ($controls as $controlName => $controlValue) {
			if ($controlName != "full_column_name" && $controlName != "column_name") {
				$this->addColumnControl($newColumnName, $controlName, $controlValue);
			}
		}
		$thisColumn = $this->getColumns($newColumnName);
		$thisColumn->setReferencedColumn($columnObject->getReferencedTable(), $columnObject->getReferencedColumn(), $columnObject->getReferencedDescriptionColumns());
	}

	/**
	 * getColumns - Return column objects. If no column name is passed in, an array of all the columns from the primary table,
	 *    join table and independent columns will be returned. If a column name is passed in, the column object for that column
	 *    will be returned. If a column exists in both the primary and join tables, the primary table column object will be returned.
	 * @param
	 *    optional column name
	 * @return DataColumn|DataColumn[]
	 *    array of column objects, if no column name is passed in. If a column name is passed in, a single column object will be returned.
	 */
	function getColumns($columnName = "") {
		$columns = $this->iPrimaryTable->getColumns($columnName);
		if ((empty($columns) || empty($columnName)) && $this->iJoinTable) {
			$joinColumns = $this->iJoinTable->getColumns($columnName);
			if (empty($columnName)) {
				$columns = array_merge($columns, $joinColumns);
			} else {
				$columns = $joinColumns;
			}
		}
		if (empty($columnName)) {
			$columns = array_merge($columns, $this->iAdditionalColumns);
		}
		if (empty($columns) && array_key_exists($columnName, $this->iAdditionalColumns)) {
			$columns = $this->iAdditionalColumns[$columnName];
		}
		if (empty($columns) && !empty($columnName)) {
			$columns = new DataColumn($columnName);
		}
		return $columns;
	}
}

?>
