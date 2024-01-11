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

class ProductSearchResult {
	protected static $iFieldNames = array();
	protected $iValues = array();

	function __construct($productRow) {
		foreach ($productRow as $fieldName => $fieldValue) {
			if (!array_key_exists($fieldName,self::$iFieldNames)) {
				$nextIndex = count(self::$iFieldNames);
				self::$iFieldNames[$fieldName] = $nextIndex;
			}
		}
		foreach (self::$iFieldNames as $fieldName => $index) {
			$this->iValues[$index] = null;
		}
		foreach ($productRow as $fieldName => $fieldValue) {
			$this->iValues[self::$iFieldNames[$fieldName]] = $fieldValue;
		}
	}

	function setValue($fieldName, $fieldValue) {
		if (!array_key_exists($fieldName,self::$iFieldNames)) {
			$nextIndex = count(self::$iFieldNames);
			self::$iFieldNames[$fieldName] = $nextIndex;
		}
		$this->iValues[self::$iFieldNames[$fieldName]] = $fieldValue;
	}

	function getProductRow() {
		$returnRow = array();
		foreach (self::$iFieldNames as $fieldName => $index) {
			$returnRow[$fieldName] = $this->iValues[$index];
		}
		return $returnRow;
	}
}
