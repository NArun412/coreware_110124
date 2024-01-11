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

abstract class PageModule {
	private static $iPageModuleClasses = array();
	protected $iErrorMessage = "";
	protected $iParameters = array();
	private $iPageModuleCode;

	function __construct() {
	}

	public static function getPageModuleInstance($pageModuleCode) {
		$pageModuleCode = strtoupper($pageModuleCode);
		if (empty(self::$iPageModuleClasses)) {
			$resultSet = executeQuery("select class_name,page_module_code from page_modules where all_client_access = 1 or page_module_id in (select page_module_id from page_module_access where client_id = ?)", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				self::$iPageModuleClasses[$row['page_module_code']] = $row['class_name'];
			}
		}
		$pageModuleClass = self::$iPageModuleClasses[$pageModuleCode];
		if (empty($pageModuleClass)) {
			return false;
		} else {
			$pageModule = new $pageModuleClass();
			$pageModule->setModuleCode($pageModuleCode);
			return $pageModule;
		}
	}

	function getErrorMessage() {
		return $this->iErrorMessage;
	}

	public function setModuleCode($pageModuleCode) {
		$this->iPageModuleCode = $pageModuleCode;
	}

	public function setParameters($parameters) {
		if (!is_array($parameters)) {
			$parameters = explode(":", $parameters);
		}
		$this->iParameters = array();
		foreach ($parameters as $index => $thisParameter) {
			if (is_numeric($index)) {
				$parameterParts = explode("=", $thisParameter, 2);
				if (count($parameterParts) == 2) {
					if (array_key_exists($parameterParts[0], $this->iParameters)) {
						if (!is_array($this->iParameters[$parameterParts[0]])) {
							$this->iParameters[strtolower($parameterParts[0])] = array($this->iParameters[$parameterParts[0]]);
						}
						$this->iParameters[strtolower($parameterParts[0])][] = $parameterParts[1];
					} else {
						$this->iParameters[strtolower($parameterParts[0])] = $parameterParts[1];
					}
				} else {
					$this->iParameters[] = trim($thisParameter);
				}
			} else {
				$this->iParameters[strtolower($index)] = trim($thisParameter);
			}
		}
		foreach ($this->iParameters as $index => $thisParameter) {
		    if ($thisParameter == "false") {
		        $this->iParameters[$index] = false;
            }
        }
	}

	public function displayContent() {
		if (method_exists($this,"massageParameters")) {
			$this->massageParameters();
		}
		$cacheKey = md5($this->iPageModuleCode . "-" . jsonEncode($this->iParameters));
		if (empty($this->iParameters['no_cache']) && !$GLOBALS['gUserRow']['administrator_flag']) {
			$htmlContent = getCachedData("page_module-" . strtolower($this->iPageModuleCode), $cacheKey);
			if (!empty($htmlContent)) {
				echo $htmlContent;
				return;
			}
		}
		ob_start();
		$this->createContent();
		$htmlContent = ob_get_clean();
		if (empty($this->iParameters['no_cache']) && !$GLOBALS['gUserRow']['administrator_flag']) {
			$cacheTime = 1;
			if (!empty($this->iParameters['cache_time']) && is_numeric($this->iParameters['cache_time'])) {
				$cacheTime = $this->iParameters['cache_time'];
			}
			setCachedData("page_module-" . strtolower($this->iPageModuleCode), $cacheKey, $htmlContent, $cacheTime);
		}
		echo $htmlContent;
	}

	abstract function createContent();
}
