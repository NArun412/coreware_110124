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
 * abstract class CustomControl
 *
 * Typically, a form control is an input element, a select dropdown, a textarea or maybe even a button. At times,
 * however, there is a need to create a custom control, in order to display or allow entry of data in a unique way.
 * This is an abstract class for implementing custom controls. It defines the functions that any custom control needs
 * in order to be used in the system.
 *
 * @author Kim D Geiger
 */
abstract class CustomControl {

	protected $iPageObject = false;

	/**
	 * @var boolean
	 * @access protected
	 */
	protected $iColumn = false;

	/**
	 * @var string
	 * @access protected
	 */
	protected $iErrorMessage = "";

	/**
	 * @var string
	 * @access protected
	 */
	protected $iPrimaryId = "";

	/**
	 *	CustomControl Class Constructor
	 *
	 *	The constructor sets the column and page objects for the control.
	 *
	 *  @param DataColumn $column
	 *  @param Page $pageObject
	 */
	function __construct($column,$pageObject) {
		$this->iColumn = $column;
		$this->iPageObject = $pageObject;
	}

	/**
	 *	function getErrorMessage
	 *
	 *	return the last error
	 *
	 *	@return string the last error that occurred in this control
	 */
	function getErrorMessage() {
		return $this->iErrorMessage;
	}

	/**
	 *	abstract function getControl
	 *
	 *	Return the html markup that is the control
	 *
	 */
	abstract function getControl();

	/**
	 *	abstract function getTemplate
	 *
	 *	Many of the controls will include a Jquery template. This template is a chunk of HTML markup that Jquery can
	 *	copy and add to the control. For instance, the control might be an HTML table. The template might be a row that
	 *	can be copies and added to the table. This template is not part of the control itself, but is included in the
	 *	JQueryTemplates section of the page. For that reason, this function is separated from the getControl function.
	 *
	 */
	abstract function getTemplate();

	/**
	 *	abstract function getRecord
	 *
	 *	get the data for a particular primary ID. Typically, the custom control will be displaying data from a subtable
	 *	of the primary table. The data will most likely need to be formatted uniquely for the type of control.
	 *
	 */
	abstract function getRecord($primaryId);

	/**
	 *	abstract function setPrimaryId
	 *
	 *	Most of the time, the primary ID will be set by the getRecord function or the saveData function. This method
	 *	allows the code to set the primary ID separately.
	 *
	 */
	abstract function setPrimaryId($primaryId);

	/**
	 *	abstract function saveData
	 *
	 *	save the data for a particular primary ID. This will be custom for each type of control.
	 *
	 */
	abstract function saveData($nameValues,$parameters=array());

	function getControlName() {
		if ($this->iColumn) {
			return $this->iColumn->getControlValue("column_name");
		} else {
			return false;
		}
	}

	function getColumn() {
		return $this->iColumn;
	}
}

?>
