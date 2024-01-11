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
 * abstract class DatabaseUpdate
 *
 * @author Kim D Geiger
 */
abstract class AbstractDatabaseUpdate {

	protected $iVersionNumber = false;

	/**
	 *	AbstractTemplate Class Constructor
	 *
	 *	The constructor makes the template aware of the page object and sets the title.
	 *
	 *  @param string $pageObject
	 */
	function __construct($versionNumber) {
		$this->iVersionNumber = $versionNumber;
	}

# return true if database alter is required, false if the update finished successfully, anything else means the update didn't finish

	function execute() {
		if ($this->runUpdate()) {
			$resultSet = executeQuery("update preferences set system_value = ? where preference_code = 'DATABASE_VERSION'",$this->iVersionNumber);
			return true;
		} else {
			return false;
		}
	}

	function getVersion() {
		return $this->iVersionNumber;
	}

# return true if database update is required, false if it completed successfully, anything else means the update didn't finish successfully

	protected abstract function runUpdate();
}
