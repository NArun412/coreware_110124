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
 * abstract class AbstractTemplate
 *
 * Abstract implementation of a template. The template defines the appearance and most of the functionality of a page.
 * The concrete template needs to implement the abstract functions, but should also implement other functions for the
 * various parts of the page. It should also allow the page to override the template functions. Typically, this is done
 * by checking to see if the function exists in the page object and executing it. If it returns true, the template
 * should interpret this to mean that the page took care of the action and the template need do nothing.
 *
 * @author Kim D Geiger
 */
abstract class AbstractTemplate {

	/**
	 *	@access protected
	 *	@var Page
	 */
	protected $iPageObject = false;

    /**
     *	@var TableEditor
     */
	protected $iTableEditorObject = false;

	/**
	 *	@access protected
	 *	@var string
	 */
	protected $iPageTitle = "";

	/**
	 *	AbstractTemplate Class Constructor
	 *
	 *	The constructor makes the template aware of the page object and sets the title.
	 *
	 *  @param string $pageObject
	 */
	function __construct($pageObject) {
		$this->iPageObject = $pageObject;
		if (method_exists($this->iPageObject,"setPageTitle")) {
			$this->iPageTitle = $this->iPageObject->setPageTitle();
		}
		if (empty($this->iPageTitle)) {
			$this->iPageTitle = $GLOBALS['gPageRow']['window_title'];
		}
	}

    /**
     *    abstract function setup
     *
     *    The setup function is a place where the template can do preliminary setup. The constructor of the page calls
     *    the setup function of the template, which runs and then calls the setup function of the page.
     *
     */
	abstract function setup();

    /**
     *    abstract function executeUrlActions
     *
     *    This function handles any processing that needs to happen before the page is displayed. Typically, the displayPage
     *    function (below) will call this function before it displays the page. Common actions are save and delete. These
     *    should be processed before displaying the page, so that the data on the page reflects those changes.
     *
     */
	abstract function executeUrlActions();

	/**
	 *	abstract function displayPage
	 *
	 *	This is the main function that will display the page of the template. Typically, the template will provide the outer
	 *	"framework" of the page and the page object will provide the real content. Some templates might just get parameters
	 *	from the page and display everything.
	 *
	 */
	abstract function displayPage();

	/**
	 *	function getPageTitle
	 *
	 *  Simple method to get the page title. Typically, the template would just use the title as set in the constructor.
	 *	However, some templates or pages might want to set their own titles. This allows the code to get the window
	 *	title as it is currently set.
	 *
	 *	@return string this instantiated objects page title
	 */
	function getPageTitle() {
		return $this->iPageTitle;
	}

	function headerIncludes() {
		$resultSet = executeQuery("select * from page_meta_tags where page_id = ?",$GLOBALS['gPageRow']['page_id']);
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['content'])) {
?>
<meta <?= $row['meta_name'] ?>="<?= $row['meta_value'] ?>" content="<?= str_replace("\"","'",str_replace("\n"," ",$row['content'])) ?>" />
<?php
			}
		}
		$this->iPageObject->headerIncludes();
	}

    /**
     *    function getAnalyticsCode
     *
     *  Simple method to get the analytics code for the page. The template can have default analytics code, but the page can override it.
     *
     * @param string $analyticsCodeChunkCode
     * @return string the analytics code
     */
	function getAnalyticsCode($analyticsCodeChunkCode = "") {
		if (!empty($analyticsCodeChunkCode)) {
			$analyticsCode = getFieldFromId("content","analytics_code_chunks","analytics_code_chunk_code",strtoupper($analyticsCodeChunkCode),"inactive = 0 and client_id = ?",$GLOBALS['gClientId']);
			return PlaceHolders::massageContent($analyticsCode);
		}
		$analyticsCodeChunkId = getFieldFromId("analytics_code_chunk_id","templates","template_id",$GLOBALS['gPageRow']['template_id'],"client_id = ? or client_id = ?",$GLOBALS['gClientId'],$GLOBALS['gDefaultClientId']);
		if (!empty($GLOBALS['gPageRow']['analytics_code_chunk_id'])) {
			$analyticsCodeChunkId = $GLOBALS['gPageRow']['analytics_code_chunk_id'];
		}
		if ($GLOBALS['gPageRow']['remove_analytics']) {
			$analyticsCodeChunkId = "";
		}
		$analyticsCode = getFieldFromId("content","analytics_code_chunks","analytics_code_chunk_id",$analyticsCodeChunkId,"inactive = 0 and client_id = ?",$GLOBALS['gClientId']);
		$webUserId = (empty($GLOBALS['gWebUserId']) ? 0 : $GLOBALS['gWebUserId']);
		$analyticsCode = str_replace("%web_user_id%",$webUserId,$analyticsCode);
		return PlaceHolders::massageContent($analyticsCode);
	}

	public static function massageTemplateColumnData(&$row) {
		$row['data_field'] = getFieldFromDataType($row['data_type']);
		$dataType = $row['data_type'];
		switch ($dataType) {
			case "varchar":
			case "text":
				if (empty($row['data_size'])) {
					$row['data_type'] = "text";
				} else {
					$row['data_type'] = "varchar";
					$row['maximum_length'] = $row['data_size'];
				}
				break;
			case "html":
				$row['data_type'] = "text";
				$row['wysiwyg'] = true;
				break;
			case "decimal":
				$row['data_type'] = "decimal";
				$row['decimal_places'] = "2";
				break;
			case "select";
				$row['data_type'] = "select";
				$thisChoices = array();
				if (!empty($row['choices'])) {
					$choiceArray = getContentLines($row['choices']);
					foreach ($choiceArray as $choice) {
                        $thisChoices[$choice] = $choice;
					}
				} else if (!empty($row['table_name'])) {
					if (empty($row['column_name'])) {
						$row['column_name'] = "description";
					}
					$choicesDataSource = new DataSource($row['table_name']);
					if ($choicesDataSource->getPrimaryTable()->columnExists("client_id")) {
						if (!empty($row['query_text'])) {
							$row['query_text'] .= " and ";
						}
						$row['query_text'] .= "client_id = " . $GLOBALS['gClientId'];
					}
					$row['query_text'] = str_replace("%client_id%", $GLOBALS['gClientId'], $row['query_text']);
					$resultSet = executeQuery("select * from " . $row['table_name'] . (empty($row['query_text']) ? "" : " where " . $row['query_text']) . " order by " . $row['column_name']);
					while ($choiceRow = getNextRow($resultSet)) {
						$thisChoices[$choiceRow[$choicesDataSource->getPrimaryTable()->getPrimaryKey()]] = $choiceRow[$row['column_name']];
					}
				}
				$row['choices'] = $thisChoices;
				break;
			case "image";
				$row['data_type'] = "image_picker";
				$row['subtype'] = "image";
				break;
		}
		unset($row['table_name']);
		$row['not_null'] = $row['required'];
		$row['column_name'] = "template_data-" . $row['data_name'] . "-" . $row['template_data_id'];
	}
}
