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
 * abstract class MaintenancePage
 *
 * Abstract class for the various parts of a maintenance program. The parts are list, form, visual sort and editable spreadsheet.
 *
 * @author Kim D Geiger
 */
abstract class MaintenancePage {

	protected $iDataSource = false;
	protected $iPageObject = false;
	protected $iExcludeFormColumns = array();
	protected $iExcludeListColumns = array();
	protected $iExcludeSearchColumns = array();
	protected $iFilterText = "";
	protected $iErrorMessage = "";
	protected $iAddUrl = "";
	protected $iSaveUrl = "";
	protected $iListUrl = "";
	protected $iListItemUrl = "";
	protected $iDisabledFunctions = array();
	protected $iReadonly = false;
	protected $iCustomActions = array();
	protected $iFilters = array();
	protected $iVisibleFilters = array();
	protected $iDefaultSortOrderColumn = "";
	protected $iDefaultReverseSortOrder = false;
	protected $iExtraColumns = array();
	protected $iMaximumListColumns = 5;
	protected $iFormSortOrder = array();
	protected $iListSortOrder = array();
	protected $iFileUpload = false;
	protected $iRemoveExport = false;
	protected $iUseColumnNameInFilter = false;
	protected $iAdditionalButtons = array();
	protected $iListDataLength = 30;
	protected $iSaveAction = "";

/**
 * function construct
 *
 * Constructor for the maintenance classes
 */
	function __construct($dataSource) {
		$this->iDataSource = $dataSource;
		$columns = $this->iDataSource->getColumns();
		foreach ($columns as $columnName => $thisColumn) {
			if ($thisColumn->getControlValue('subtype') == "image" || $thisColumn->getControlValue('subtype') == "file" || $thisColumn->getControlValue('data_type') == "longblob") {
				$this->iFileUpload = true;
			}
		}
		$this->addExcludeColumn(array($this->iDataSource->getPrimaryTable()->getPrimaryKey(),"client_id","version"));
	}

/**
 * function setFileUpload
 *
 * The form requires special attributes if files are being uploaded. Generally, the maintenance class will determine this on
 *	its own. However, there may be times when the developer wants to set the file upload explicitly.
 */
	function setFileUpload($fileUpload) {
		$this->iFileUpload = $fileUpload;
	}

	function addAdditionalButtons($additionalButtons) {
		foreach ($additionalButtons as $buttonCode => $buttonInfo) {
			$this->iAdditionalButtons[$buttonCode] = $buttonInfo;
		}
	}

/**
 * function setPageObject
 *
 * The maintenance class needs to be aware of the page object of which it is part. This allows that.
 */
	function setPageObject($pageObject) {
		$this->iPageObject = $pageObject;
	}

/**
 * function setSaveAction
 *
 * The maintenance class does something after a record is saved. This changes the default
 */
	function setSaveAction($saveAction) {
		$this->iSaveAction = $saveAction;
	}

/**
 * function setExtraColumns
 *
 * Add extra columns to the list
 */
	function setExtraColumns($extraColumns) {
		$this->iExtraColumns = $extraColumns;
	}

/**
 * function setUseColumnNameInFilter
 *
 * Set the column name to be used in the filter
 */
	function setUseColumnNameInFilter($useColumnNameInFilter) {
		$this->iUseColumnNameInFilter = $useColumnNameInFilter;
	}

/**
 * function setFormSortOrder
 *
 * Set the order the columns will appear in the form
 */
	function setFormSortOrder($formSortOrder) {
		$this->iFormSortOrder = $formSortOrder;
		foreach ($this->iFormSortOrder as $index => $columnName) {
			if (strpos($columnName,".") === false) {
				if ($this->iDataSource->getPrimaryTable()->columnExists($columnName)) {
					$this->iFormSortOrder[$index] = $this->iDataSource->getPrimaryTable()->getName() . "." . $columnName;
				} else if ($this->iDataSource->getJoinTable() && $this->iDataSource->getJoinTable()->columnExists($columnName)) {
					$this->iFormSortOrder[$index] = $this->iDataSource->getJoinTable()->getName() . "." . $columnName;
				}
			}
		}
	}

/**
 * function setListSortOrder
 *
 * Set the order the data will appear in the list
 */
	function setListSortOrder($listSortOrder) {
		$this->iListSortOrder = $listSortOrder;
		foreach ($this->iListSortOrder as $index => $columnName) {
			if (strpos($columnName,".") === false) {
				if ($this->iDataSource->getPrimaryTable()->columnExists($columnName)) {
					$this->iListSortOrder[$index] = $this->iDataSource->getPrimaryTable()->getName() . "." . $columnName;
				} else if ($this->iDataSource->getJoinTable() && $this->iDataSource->getJoinTable()->columnExists($columnName)) {
					$this->iListSortOrder[$index] = $this->iDataSource->getJoinTable()->getName() . "." . $columnName;
				}
			}
		}
	}

/**
 * function setMaximumListColumns
 *
 * Set the maximum number of columns the list will contain when the columns are automatically generated. If the user
 *	sets the columns in the list using preferences, this won't apply.
 */
	function setMaximumListColumns($maximumListColumns) {
		if (is_numeric($maximumListColumns)) {
			$this->iMaximumListColumns = $maximumListColumns;
		}
	}

/**
 * function setDefaultSortOrder
 *
 * Set the default sort order, if one is not yet set.
 */
	function setDefaultSortOrder($sortOrderColumn,$reverseSortOrder) {
		$this->iDefaultSortOrderColumn = $sortOrderColumn;
		$this->iDefaultReverseSortOrder = $reverseSortOrder;
	}

/**
 * function setReadonly
 *
 * Make the data readonly.
 */
	function setReadonly($readonly) {
		$this->iReadonly = $readonly;
	}

/**
 * private function getColumnList
 *
 * get an array of the columns in the datasource of this page.
 *
 * @param columnNames - an array or comma separated list of column names
 * @param notInList - if true, return columns that are in the datasource but not in the list of column names
 */
	private function getColumnList($columnNames,$notInList=false) {
		if (!is_array($columnNames)) {
			$columnNames = explode(",",$columnNames);
		}
		$columnList = array();
		foreach ($columnNames as $columnName) {
			$thisColumnName = $this->iDataSource->getPrimaryTable()->columnExists($columnName);
			if ($thisColumnName) {
				$columnList[] = $this->iDataSource->getPrimaryTable()->getName() . "." . $thisColumnName;
			}
			if ($this->iDataSource->getJoinTable()) {
				$thisColumnName = $this->iDataSource->getJoinTable()->columnExists($columnName);
				if ($thisColumnName) {
					$columnList[] = $this->iDataSource->getJoinTable()->getName() . "." . $thisColumnName;
				}
			}
			if (array_key_exists($columnName,$this->iDataSource->getAdditionalColumns())) {
				$columnList[] = $columnName;
			}
		}
		if ($notInList) {
			$notColumnList = array();
			foreach ($this->iDataSource->getColumns() as $columnName => $thisColumn) {
				if (!in_array($columnName,$columnList)) {
					$notColumnList[] = $columnName;
				}
			}
			return $notColumnList;
		}
		return $columnList;
	}

/**
 * function addExcludeColumn
 *
 * exclude the passed in columns from the list, form and search
 *
 * @param columnNames - an array or comma separated list of column names
 */
	function addExcludeColumn($columnNames) {
		$this->addExcludeListColumn($columnNames);
		$this->addExcludeSearchColumn($columnNames);
		$this->addExcludeFormColumn($columnNames);
	}

/**
 * function addIncludeColumn
 *
 * make the passed in columns the only columns include in the list, form and search
 *
 * @param columnNames - an array or comma separated list of column names
 */
	function addIncludeColumn($columnNames) {
		$this->addIncludeListColumn($columnNames);
		$this->addIncludeSearchColumn($columnNames);
		$this->addIncludeFormColumn($columnNames);
	}

/**
 * function addExcludeListColumn
 *
 * exclude the passed in columns from the list
 *
 * @param columnNames - an array or comma separated list of column names
 */
	function addExcludeListColumn($columnNames) {
		$this->iExcludeListColumns = array_merge($this->iExcludeListColumns,$this->getColumnList($columnNames));
	}

/**
 * function addIncludeListColumn
 *
 * make the passed in columns the only columns include in the list
 *
 * @param columnNames - an array or comma separated list of column names
 */
	function addIncludeListColumn($columnNames) {
		$this->iExcludeListColumns = $this->getColumnList($columnNames,true);
	}

/**
 * function addExcludeSearchColumn
 *
 * exclude the passed in columns from the search
 *
 * @param columnNames - an array or comma separated list of column names
 */
	function addExcludeSearchColumn($columnNames) {
		$this->iExcludeSearchColumns = array_merge($this->iExcludeSearchColumns,$this->getColumnList($columnNames));
	}

/**
 * function addIncludeSearchColumn
 *
 * make the passed in columns the only columns include in the search
 *
 * @param columnNames - an array or comma separated list of column names
 */
	function addIncludeSearchColumn($columnNames) {
		$this->iExcludeSearchColumns = $this->getColumnList($columnNames,true);
	}

/**
 * function addExcludeFormColumn
 *
 * exclude the passed in columns from the form
 *
 * @param columnNames - an array or comma separated list of column names
 */
	function addExcludeFormColumn($columnNames) {
		$this->iExcludeFormColumns = array_merge($this->iExcludeFormColumns,$this->getColumnList($columnNames));
	}

/**
 * function addIncludeFormColumn
 *
 * make the passed in columns the only columns include in the form
 *
 * @param columnNames - an array or comma separated list of column names
 */
	function addIncludeFormColumn($columnNames) {
		$this->iExcludeFormColumns = $this->getColumnList($columnNames,true);
	}

/**
 * function addCustomAction
 *
 * Add an action to the action dropdown in the list and other maintenance classes that use it. The action needs a code
 * and a description. These can either be passed in as an associative array or a code and description.
 */
	function addCustomAction($customActions,$description="") {
		if (!is_array($customActions)) {
			$customActions = array($customActions=>$description);
		}
		foreach ($customActions as $customAction => $description) {
			$this->iCustomActions[$customAction] = $description;
		}
	}

/**
 * function addFilters
 *
 * add filters to the maintenance class. These can be selected by the user from a dropdown to automatically filter the list.
 */
	function addFilters($filters) {
		foreach ($filters as $filterCode => $filterInfo) {
			$this->iFilters[$filterCode] = $filterInfo;
		}
	}

/**
 * function addVisibleFilters
 *
 * add filters to the maintenance class. These can be selected by the user and appear above the list.
 */
	function addVisibleFilters($filters) {
		foreach ($filters as $filterCode => $filterInfo) {
			$this->iVisibleFilters[$filterCode] = $filterInfo;
		}
	}

/**
 * function setErrorMessage
 *
 * set the error message
 */
	function setErrorMessage($errorMessage) {
		$this->iErrorMessage = $errorMessage;
	}

/**
 * function setAddUrl
 *
 * set the url that will be used when the user clicks the Add button.
 */
	function setAddUrl($addUrl) {
		$this->iAddUrl = $addUrl;
	}

/**
 * function setSaveUrl
 *
 * set the url that will be used when the user clicks the Save button.
 */
	function setSaveUrl($saveUrl) {
		$this->iSaveUrl = $saveUrl;
	}

/**
 * function setListUrl
 *
 * set the url that will be used when the user clicks the List button.
 */
	function setListUrl($listUrl) {
		$this->iListUrl = $listUrl;
	}

/**
 * function canExport
 *
 * set whether this page can export data
 */
	function canExport($canExport) {
		$this->iRemoveExport = !$canExport;
	}

/**
 * function setListItemUrl
 *
 * set the url that will be used when the user clicks an item in the list. Normally, this will go to the page with
 *	"url_page=show&primary_id=[primary_id]" will be added to the url. This allows a different URL.
 */
	function setListItemUrl($listItemUrl) {
		$this->iListItemUrl = $listItemUrl;
	}

	function setListDataLength($listDataLength) {
		if (is_numeric($listDataLength)) {
			$this->iListDataLength = $listDataLength;
		}
	}

/**
 * function setDisabledFunctions
 *
 * Allows the developer to disable some set of buttons on the maintenance page
 */
	function setDisabledFunctions($disabledFunctions) {
		if (!empty($disabledFunctions)) {
			if (is_array($disabledFunctions)) {
				$this->iDisabledFunctions = $disabledFunctions;
			} else {
				$this->iDisabledFunctions = array($disabledFunctions);
			}
		}
	}

/**
 * function selectRow
 *
 * set a record in the list as selected or unselected
 */
	function selectRow() {
		$returnArray = array();
		if (empty($GLOBALS['gUserId']) || empty($GLOBALS['gPageId'])) {
			ajaxResponse($returnArray);
		}
		$primaryId = getFieldFromId($this->iDataSource->getPrimaryTable()->getPrimaryKey(),$this->iDataSource->getPrimaryTable()->getName(),$this->iDataSource->getPrimaryTable()->getPrimaryKey(),$_GET['primary_id']);
		if (!empty($primaryId)) {
			executeQuery("delete from selected_rows where primary_identifier = ? and page_id = ? and user_id = ?",array($primaryId,$GLOBALS['gPageId'],$GLOBALS['gUserId']));
			if ($_GET['set'] == "yes") {
				executeQuery("insert into selected_rows (selected_row_id,user_id,page_id,primary_identifier,version) values " .
					"(null,?,?,?,1)",array($GLOBALS['gUserId'],$GLOBALS['gPageId'],$primaryId));
			}
		} else {
			$returnArray['error_message'] = getSystemMessage("missing_primary_id");
		}
		$returnArray['_selected_count'] = 0;
		executeQuery("delete from selected_rows where user_id = ? and page_id = ?" .
			" and primary_identifier not in (select " . $this->iDataSource->getPrimaryTable()->getPrimaryKey() . " from " . $this->iDataSource->getPrimaryTable()->getName() . ")",array($GLOBALS['gUserId'],$GLOBALS['gPageId']));
		$resultSet = executeQuery("select count(*) from selected_rows where user_id = ? and page_id = ?",array($GLOBALS['gUserId'],$GLOBALS['gPageId']));
		if ($row = getNextRow($resultSet)) {
			$returnArray['_selected_count'] = $row['count(*)'];
		}
		freeResult($resultSet);
		ajaxResponse($returnArray);
	}

/**
 * function displayButtons
 *
 * create html markup for the buttons in the header
 */
	function displayButtons($enableButtons,$readonly=false,$buttonFunctions="") {
		$enableAll = false;
		if (!is_array($enableButtons)) {
			if ($enableButtons == "all") {
				$enableAll = true;
			} else {
				$enableButtons = func_get_args();
			}
		}
		if (empty($buttonFunctions)) {
			$buttonFunctions = array(
				"add"=>array("accesskey"=>"a","label"=>getLanguageText("Add"),"disabled"=>($GLOBALS['gPermissionLevel'] < _READWRITE || $readonly ? true : false)),
				"save"=>array("accesskey"=>"s","label"=>getLanguageText("Save"),"disabled"=>($GLOBALS['gPermissionLevel'] < _READWRITE || $readonly ? true : false)),
				"previous"=>array("accesskey"=>",","label"=>"<<","disabled"=>false),
				"next"=>array("accesskey"=>".","label"=>">>","disabled"=>false),
				"delete"=>array("label"=>getLanguageText("Delete"),"disabled"=>($GLOBALS['gPermissionLevel'] < _FULLACCESS || $readonly ? true : false)),
				"list"=>array("accesskey"=>"l","label"=>getLanguageText("List"),"disabled"=>false)
			);
		}
?>
<img src="images/locked.png" id="_locked_image" alt='Locked' />
<?php
		foreach ($buttonFunctions as $buttonName => $buttonInfo) {
			$enabled = !$buttonInfo['disabled'] && ($enableAll || in_array($buttonName,$enableButtons));
?>
<button tabindex="9000"<?= (empty($buttonInfo['accesskey']) ? "" : " accesskey='" . $buttonInfo['accesskey'] . "'") ?> class="<?= ($enabled ? "enabled" : "disabled") ?>-button" id="_<?= $buttonName ?>_button"><?= $buttonInfo['label'] ?></button>
<?php
		}
	}

/**
 * abstract functions
 *
 * functions that need to be defined by classes implementing this class.
 */
	abstract function mainContent();

	abstract function onLoadPageJavascript();

	abstract function pageJavascript();

	abstract function internalPageCSS();

	abstract function hiddenElements();

	abstract function jqueryTemplates();

	abstract function getSortList();

	abstract function setPreferences();

	abstract function getDataList();

	abstract function exportCSV($exportAll);

	abstract function getRecord();

	abstract function saveChanges();

	abstract function deleteRecord();

	abstract function getSpreadsheetList();

	abstract function pageElements();
}
