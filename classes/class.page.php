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
 * abstract class Page
 *
 *    The page class is the core of the system. Virtually every page will use this class.
 *
 * @author Kim D Geiger
 */
class Page {
	var $iTemplateAddendumObject = null;
	/**
	 * @access private
	 * @var string
	 */
	protected $iTemplateObject = null;
	/**
	 * @var DataSource
	 */
	protected $iDataSource = false;
	/**
	 * @var Database
	 */
	protected $iDatabase = false;
	protected $iPrimaryTableName = "";
	protected $iPageData = array();
	protected $iPageTextChunks = false;
	protected $iProgramTextChunks = false;

	/**
	 * function construct
	 *
	 * The page can be instantiated with the name of the primary table that the page is dealing with. First, allow the developer
	 *    to massage the URL parameters, if necessary. Then instantiate the template used by this page and initialize the page
	 *    data. Finally, run the setup method of the template.
	 */
	function __construct($primaryTableName = "") {
#       If ANYTHING changes in the CSS or Javascript for the template or for the page, caching will break the page. Turning it off for now.

		if (!$GLOBALS['gLoggedIn'] && empty($_GET['ajax']) && empty($_GET['url_action']) && empty($GLOBALS['gTemplateRow']['include_crud']) && empty($GLOBALS['gCacheProhibited']) && $GLOBALS['gPageRow']['allow_cache']) {
			$pageContent = getCachedData("page_contents", $GLOBALS['gPageRow']['page_code']);
			if (!empty($pageContent)) {
				$GLOBALS['gPageCacheUsed'] = true;
				echo $pageContent;
				exit;
			}
		}
		$this->initialize();
		$this->iPrimaryTableName = $primaryTableName;
        if (empty($this->iPrimaryTableName)) {
            $this->iPrimaryTableName = $GLOBALS['gPageRow']['primary_table_name'];
        }
		if (empty($this->iPrimaryTableName) && !array_key_exists("primary_table_name",$GLOBALS['gPageRow'])) {
			$this->iPrimaryTableName = getReadFieldFromId("text_data","page_data","page_id",$GLOBALS['gPageId'],"template_data_id = (select template_data_id from template_data where data_name = 'primary_table_name')");
        }
		if (!is_scalar($this->iPrimaryTableName) || !$GLOBALS['gPrimaryDatabase']->tableExists($this->iPrimaryTableName)) {
			$this->iPrimaryTableName = "";
		}
		$this->massageUrlParameters();
		$this->iTemplateObject = new Template($this);
		if (class_exists("TemplateAddendum", false)) {
			$this->iTemplateAddendumObject = new TemplateAddendum();
		}
		if (empty($GLOBALS['gPageObject'])) {
			$GLOBALS['gPageObject'] = $this;
		}
		if (!empty($this->iTemplateObject)) {
			$this->iTemplateObject->setup();
		}
		$this->initializePageData();
		if (empty($primaryTableName)) {
			$this->iPrimaryTableName = $this->iPageData['primary_table_name'];
		}
	}

	/**
	 * function initialize
	 *
	 *  initialize data and anything else for page
	 */
	function initialize() {
		return false;
	}

	/**
	 * function massageUrlParameters
	 *
	 *    In the concrete object of this class, this function can be overridden to massage the URL parameters
	 */
	function massageUrlParameters() {
	}

	/**
	 * private function initializePageData
	 *
	 * get template data stored for this page. Store this data in the iPageData array.
	 */
	private function initializePageData() {
		$cachedTemplatePageData = getCachedData("initialized_template_page_data", $GLOBALS['gPageId']);
		$pageDataArray = false;
		$templateDataArray = false;
		if (is_array($cachedTemplatePageData)) {
			$pageDataArray = $cachedTemplatePageData['page_data'];
			$templateDataArray = $cachedTemplatePageData['template_data'];
		}
		$needToCache = false;
		if (!is_array($pageDataArray)) {
			$pageDataArray = array();
			$resultSet = executeQuery("select * from page_data where page_id = ?", $GLOBALS['gPageId']);
			while ($row = getNextRow($resultSet)) {
				$pageDataArray[$row['page_data_id']] = $row;
			}
			$needToCache = true;
		}
		if (!is_array($templateDataArray)) {
			$resultSet = executeQuery("select * from template_data where template_data_id in (select template_data_id from template_data_uses where template_id = ?)", $GLOBALS['gPageRow']['template_id']);
			while ($row = getNextRow($resultSet)) {
				$templateDataArray[] = $row;
			}
			$needToCache = true;
		}
		if ($needToCache) {
			setCachedData("initialized_template_page_data", $GLOBALS['gPageId'], array("page_data" => $pageDataArray, "template_data" => $templateDataArray), .25);
		}
		foreach ($templateDataArray as $row) {
			$row['data_name'] = strtolower($row['data_name']);
			if ($row['allow_multiple'] == 1 && !empty($row['group_identifier'])) {
				if (!array_key_exists(strtolower($row['group_identifier']), $this->iPageData)) {
					$this->iPageData[strtolower($row['group_identifier'])] = array();
				}
				foreach ($pageDataArray as $row1) {
					if ($row['template_data_id'] != $row1['template_data_id']) {
						continue;
					}
					if (!array_key_exists($row1['sequence_number'], $this->iPageData[strtolower($row['group_identifier'])])) {
						$this->iPageData[strtolower($row['group_identifier'])][$row1['sequence_number']] = array();
					}
					switch ($row['data_type']) {
						case "bigint":
						case "int":
						case "datasource":
							$this->iPageData[strtolower($row['group_identifier'])][$row1['sequence_number']][$row['data_name']] = $row1['integer_data'];
							break;
						case "decimal":
							$this->iPageData[strtolower($row['group_identifier'])][$row1['sequence_number']][$row['data_name']] = $row1['number_data'];
							break;
						case "date":
							$this->iPageData[strtolower($row['group_identifier'])][$row1['sequence_number']][$row['data_name']] = (empty($row1['date_data']) ? "" : date("m/d/y", strtotime($row1['date_data'])));
							break;
						case "tinyint":
							$this->iPageData[strtolower($row['group_identifier'])][$row1['sequence_number']][$row['data_name']] = $row1['integer_data'] != 0;
							break;
						case "image":
							$this->iPageData[strtolower($row['group_identifier'])][$row1['sequence_number']][$row['data_name']] = $row1['image_id'];
							break;
						default:
							$thisPageData = PlaceHolders::massageContent($row1['text_data']);
							$thisPageData = $this->replaceImageReferences($thisPageData);
							if (!empty($this->iTemplateObject)) {
								$thisPageData = $this->parsePageContent($thisPageData);
							}
							$this->iPageData[strtolower($row['group_identifier'])][$row1['sequence_number']][$row['data_name']] = $thisPageData;
							break;
					}
				}
			} else if ($row['allow_multiple'] == 0 || $row['data_type'] == "image") {
				foreach ($pageDataArray as $row1) {
					if ($row['template_data_id'] != $row1['template_data_id']) {
						continue;
					}
					if (isset($this->iPageData[$row['data_name']])) {
						continue;
					}
					switch ($row['data_type']) {
						case "bigint":
						case "int":
						case "datasource":
							$this->iPageData[$row['data_name']] = $row1['integer_data'];
							break;
						case "decimal":
							$this->iPageData[$row['data_name']] = $row1['number_data'];
							break;
						case "date":
							$this->iPageData[$row['data_name']] = (empty($row1['date_data']) ? "" : date("m/d/y", strtotime($row1['date_data'])));
							break;
						case "tinyint":
							$this->iPageData[$row['data_name']] = $row1['integer_data'] != 0;
							break;
						case "image":
							$this->iPageData[$row['data_name']] = $row1['image_id'];
							break;
						default:
							if (!empty($this->iTemplateObject)) {
								$thisPageData = $this->parsePageContent($row1['text_data']);
							}
							$thisPageData = $this->replaceImageReferences($thisPageData);
							$this->iPageData[$row['data_name']] = PlaceHolders::massageContent($thisPageData);
							break;
					}
				}
			} else {
				$this->iPageData[$row['data_name']] = array();
				$index = 0;
				foreach ($pageDataArray as $row1) {
					if ($row['template_data_id'] != $row1['template_data_id']) {
						continue;
					}
					switch ($row['data_type']) {
						case "bigint":
						case "int":
						case "datasource":
							$this->iPageData[$row['data_name']][$index++] = $row1['integer_data'];
							break;
						case "decimal":
							$this->iPageData[$row['data_name']][$index++] = $row1['number_data'];
							break;
						case "date":
							$this->iPageData[$row['data_name']][$index++] = (empty($row1['date_data']) ? "" : date("m/d/y", strtotime($row1['date_data'])));
							break;
						case "tinyint":
							$this->iPageData[$row['data_name']][$index++] = $row1['integer_data'] != 0;
							break;
						case "image":
							$this->iPageData[$row['data_name']][$index++] = $row1['image_id'];
							break;
						default:
							if (!empty($this->iTemplateObject)) {
								$thisPageData = $this->parsePageContent($row1['text_data']);
							}
							$thisPageData = $this->replaceImageReferences($thisPageData);
							$thisPageData = PlaceHolders::massageContent($thisPageData);
							$this->iPageData[$row['data_name']][$index++] = $thisPageData;
							break;
					}
				}
			}
		}
		freeResult($resultSet);
	}

	function replaceImageReferences($content) {
		if (empty($content)) {
			return $content;
		}
		$startPosition = 0;
		while (true) {
			$stringPosition = strpos($content, "download.php?", $startPosition);
			if ($stringPosition === false || $stringPosition > 200000) {
				break;
			}
			$endPosition = $stringPosition + strlen("download.php?");
			while (!in_array(substr($content, $endPosition, 1), array("'", '"', " ", ","))) {
				$endPosition++;
			}
			$fileIdentifier = substr($content, $stringPosition + strlen("download.php?"), $endPosition - $stringPosition - strlen("download.php?"));
			$fileId = str_replace("id=", "", str_replace("code=", "", $fileIdentifier));
			$filename = getFileFilename($fileId);
			if (!empty($filename)) {
				$content = substr($content, 0, $stringPosition - (substr($content,($stringPosition - 1), 1) == "/" ? 1 : 0)) . $filename . substr($content, $endPosition);
			}
			$startPosition = $stringPosition + strlen("download.php?");
		}
		while (true) {
			$stringPosition = strpos($content, "<img ", $startPosition);
			if ($stringPosition === false || $stringPosition > 200000) {
				break;
			}
			$endPosition = $stringPosition + strlen("<img ");
			while (substr($content, $endPosition, 1) != ">") {
				$endPosition++;
			}
			$imageCode = substr($content, $stringPosition + strlen("<img "), $endPosition - $stringPosition - strlen("<img "));
			$imageCode = str_replace("'", '"', $imageCode);
			do {
				$imageCodeLength = strlen($imageCode);
				$imageCode = str_replace(" =", "=", $imageCode);
				$imageCode = str_replace("= ", "=", $imageCode);
			} while ($imageCodeLength != strlen($imageCode));
			$imageIndex = 0;
			$imageNameValues = array();
			$standaloneValues = array();
			$currentName = "";
			$currentValue = "";
			$onName = true;
			$withinQuotes = false;
			$imageCode .= " ";
			while ($imageIndex < strlen($imageCode)) {
				$thisChar = substr($imageCode, $imageIndex, 1);
				if ($thisChar == "=" && !$withinQuotes && $onName) {
					$onName = false;
					$imageNameValues[$currentName] = "";
					$imageIndex++;
					$withinQuotes = false;
					if ($imageIndex < strlen($imageCode) && substr($imageCode, $imageIndex, 1) == "\"") {
						$withinQuotes = true;
						$imageIndex++;
					}
					continue;
				} else if ($thisChar == " " && !$withinQuotes) {
					if ($onName) {
						if ($currentName) {
							$imageNameValues[$currentName] = "";
							$currentName = "";
						}
					} else {
						$imageNameValues[$currentName] = trim($currentValue);
						$onName = true;
						$currentName = "";
						$currentValue = "";
					}
					$imageIndex++;
					continue;
				} else if ($thisChar == "\"" && $withinQuotes && !$onName) {
					$imageNameValues[$currentName] = trim($currentValue);
					$currentName = "";
					$currentValue = "";
					$withinQuotes = false;
					$onName = true;
					$imageIndex++;
					continue;
				}
				if ($onName) {
					$currentName .= $thisChar;
				} else {
					$currentValue .= $thisChar;
					$imageNameValues[$currentName] = trim($currentValue);
				}
				$imageIndex++;
			}
			$imageRecreated = false;
			$language = $imageNameValues['language'];
			if (empty($language) && !empty($GLOBALS['gLanguageCode'])) {
				$language = $GLOBALS['gLanguageCode'];
			}
			if (empty(getPreference("NO_LAZY_LOADING_IMAGES")) && !array_key_exists("loading", $imageNameValues)) {
				$imageNameValues['loading'] = "lazy";
				$imageRecreated = true;
			}
			if (array_key_exists("src", $imageNameValues)) {
				$imageId = "";
				$imageParts = array();
				if (strpos($imageNameValues['src'], "getimage.php?code=") !== false) {
					$imageCode = str_replace("\"", "", str_replace("/", "", str_replace("getimage.php?code=", "", $imageNameValues['src'])));
					$imageParts = array();
					if (!empty($language)) {
						$imageParts = getCachedData("image_code_details", $imageCode . "_" . $language);
						if (!is_array($imageParts)) {
							$imageParts = getMultipleFieldsFromId(array("image_id", "description", "detailed_description"), "images", "image_code", $imageCode . "_" . $language);
							setCachedData("image_code_details", $imageCode . "_" . $language, $imageParts, (empty($imageParts) ? 1 : 4));
							if (empty($imageParts)) {
								$imageParts = false;
							}
						}
					}
					if (empty($imageParts)) {
						$imageParts = getCachedData("image_code_details", $imageCode);
						if (!is_array($imageParts)) {
							$imageParts = getMultipleFieldsFromId(array("image_id", "description", "detailed_description"), "images", "image_code", $imageCode);
							setCachedData("image_code_details", $imageCode, $imageParts, (empty($imageParts) ? 1 : 4));
						}
					}
				} else if (strpos($imageNameValues['src'], "getimage.php?id=") !== false) {
					$imageId = str_replace("\"", "", str_replace("/", "", str_replace("getimage.php?id=", "", $imageNameValues['src'])));
					$imageParts = getCachedData("image_id_details", $imageCode);
					if (!is_array($imageParts)) {
						$imageParts = getMultipleFieldsFromId(array("image_id", "description", "detailed_description"), "images", "image_id", $imageId);
						setCachedData("image_id_details", $imageCode, $imageParts, (empty($imageParts) ? 1 : 4));
					}
				} else if (strpos($imageNameValues['src'], "getimage.php?image_id=") !== false) {
					$imageId = str_replace("\"", "", str_replace("/", "", str_replace("getimage.php?image_id=", "", $imageNameValues['src'])));
					$imageParts = getCachedData("image_id_details", $imageCode);
					if (!is_array($imageParts)) {
						$imageParts = getMultipleFieldsFromId(array("image_id", "description", "detailed_description"), "images", "image_id", $imageId);
						setCachedData("image_id_details", $imageCode, $imageParts, (empty($imageParts) ? 1 : 4));
					}
				}
				$imageId = $imageParts['image_id'];
				if (!empty($imageId)) {
					$sourceParameters = array();
					$sourceParameterString = $imageNameValues['src'];
					while (substr($sourceParameterString, 0, 1) == "/") {
						$sourceParameterString = substr($sourceParameterString, 1);
					}
					$sourceParameterString = str_replace("getimage.php?", "", $sourceParameterString);
					parse_str($sourceParameterString, $sourceParameters);
					$sourceParameters['use_cdn'] = true;
					$imageUrl = getImageFilename($imageId, $sourceParameters);
					if (!empty($imageUrl)) {
						$imageNameValues['src'] = $imageUrl;
						if (!array_key_exists("alt", $imageNameValues)) {
							if (empty($imageParts['detailed_description'])) {
								$imageNameValues['alt'] = str_replace("\"", "'", $imageParts['description']);
							} else {
								$imageNameValues['alt'] = str_replace("\"", "'", $imageParts['detailed_description']);
							}
						}
						if (!array_key_exists("title", $imageNameValues)) {
							$imageNameValues['title'] = str_replace("\"", "'", $imageParts['description']);
						}
						if (false && !array_key_exists("srcset", $imageNameValues)) {
							$imageNameValues['srcset'] = getImageFilename($imageId, array("image_type" => "mobile")) . " 1200w";
						}
						$imageRecreated = true;
					}
				}
			}
			if ($imageRecreated) {
				foreach ($imageNameValues as $attribute => $value) {
					if (!empty($value)) {
						$standaloneValues[] = $attribute . "='" . $value . "'";
					}
				}
				$newImageTag = implode(" ", $standaloneValues);
				$content = substr($content, 0, $stringPosition + strlen("<img ")) . $newImageTag . substr($content, $endPosition);
			}
			$startPosition = $stringPosition + 1;
		}
		return $this->replaceOtherImageReferences($content);
	}

	function replaceOtherImageReferences($content) {
		$startPosition = 0;
		$times = 0;
		$language = $GLOBALS['gLanguageCode'];
		while (true) {
			if ($startPosition > strlen($content)) {
				break;
			}
			$stringPosition = strpos($content, "getimage.php", $startPosition);
			if ($stringPosition === false || $stringPosition > 200000) {
				break;
			}
			$delimiter = "";
			$foundSlash = false;
			for ($x = 2; $x > 0; $x--) {
				if ($stringPosition - $x < 0) {
					continue;
				}
				$thisChar = substr($content, $stringPosition - $x, 1);
				if ($thisChar == "/") {
					$foundSlash = true;
				} else if ($thisChar == "'" || $thisChar == '"' || $thisChar == "(") {
					$delimiter = $thisChar;
				}
			}
			$endPosition = $stringPosition + strlen("getimage.php");
			if (empty($delimiter)) {
				$startPosition = $endPosition + 1;
				continue;
			}
			if ($delimiter == "(") {
				$delimiter = ")";
			}
			$stringPosition += ($foundSlash ? -1 : 0);
			while (substr($content, $endPosition, 1) != $delimiter) {
				if ($endPosition > 100000) {
					break;
				}
				$endPosition++;
			}
			$imageReference = str_replace(" ", "", substr($content, $stringPosition, $endPosition - $stringPosition));
			$imageParts = explode("=", $imageReference);
			if (count($imageParts) != 2) {
				$startPosition = $endPosition + 1;
				continue;
			}
			$imageCode = $imageParts[1];
			$imageId = "";
			if (strpos($imageReference, "getimage.php?code=") !== false) {
				$imageParts = array();
				if (!empty($language)) {
					$imageParts = getCachedData("image_code_details", $imageCode . "_" . $language);
					if (!is_array($imageParts)) {
						$imageParts = getMultipleFieldsFromId(array("image_id", "description", "detailed_description"), "images", "image_code", $imageCode . "_" . $language);
						setCachedData("image_code_details", $imageCode . "_" . $language, $imageParts, (empty($imageParts) ? 1 : 4));
						if (empty($imageParts)) {
							$imageParts = false;
						}
					}
				}
				if (empty($imageParts)) {
					$imageParts = getCachedData("image_code_details", $imageCode);
					if (!is_array($imageParts)) {
						$imageParts = getMultipleFieldsFromId(array("image_id", "description", "detailed_description"), "images", "image_code", $imageCode);
						setCachedData("image_code_details", $imageCode, $imageParts, (empty($imageParts) ? 1 : 4));
					}
				}
				$imageId = $imageParts['image_id'];
			} else if (strpos($imageReference, "getimage.php?id=") !== false) {
				$imageId = getFieldFromId("image_id", "images", "image_id", $imageCode);
			} else if (strpos($imageReference, "getimage.php?image_id=") !== false) {
				$imageId = getFieldFromId("image_id", "images", "image_id", $imageCode);
			}
			if (!empty($imageId)) {
				$sourceParameters = array();
				$sourceParameterString = $imageReference;
				while (substr($sourceParameterString, 0, 1) == "/") {
					$sourceParameterString = substr($sourceParameterString, 1);
				}
				$sourceParameterString = str_replace("getimage.php?", "", $sourceParameterString);
				parse_str($sourceParameterString, $sourceParameters);
				$imageUrl = getImageFilename($imageId, $sourceParameters);
				if (!empty($imageUrl)) {
					$content = substr($content, 0, $stringPosition) . $imageUrl . substr($content, $endPosition);
				}
			}
			$startPosition = $endPosition + 1;
		}
		return $content;
	}

	private function parsePageContent($htmlContent) {
		$this->getPageTextChunks();
		foreach ($this->iPageTextChunks as $pageTextChunkCode => $content) {
			$htmlContent = str_ireplace("%page_text_chunk:" . $pageTextChunkCode . "%", $content, $htmlContent);
		}

		if ($this->iProgramTextChunks === false) {
			$this->iProgramTextChunks = getCachedData("program_text", "program_text", true);
			if (!is_array($this->iProgramTextChunks)) {
				$this->iProgramTextChunks = array();
				$resultSet = executeReadQuery("select * from program_text");
				while ($row = getNextRow($resultSet)) {
					$this->iProgramTextChunks[strtolower($row['program_text_code'])] = $row['content'];
				}
				setCachedData("program_text", "program_text", $this->iProgramTextChunks, 24, true);
			}
			if (function_exists("massageProgramText")) {
				massageProgramText($this->iProgramTextChunks);
			}
			if (method_exists($this, "massageProgramText")) {
				$this->massageProgramText($this->iProgramTextChunks);
			}
		}
		foreach ($this->iProgramTextChunks as $programTextChunkCode => $content) {
			$htmlContent = str_ireplace("%program_text:" . $programTextChunkCode . "%", $content, $htmlContent);
		}

		foreach ($this->iPageTextChunks as $pageTextChunkCode => $pageTextChunkContent) {
			$htmlContent = str_ireplace("%page_text_chunk:" . strtolower($pageTextChunkCode) . "%", $pageTextChunkContent, $htmlContent);
		}
		ob_start();
		$htmlContentLines = getContentLines($htmlContent);
		foreach ($htmlContentLines as $thisLine) {
			if (substr($thisLine, 0, strlen("%method:")) == "%method:") {
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
				if (method_exists($this, $methodName)) {
					if (!empty($parts)) {
						call_user_func(array($this, $methodName), $parts);
					} else {
						call_user_func(array($this, $methodName));
					}
				} else if (!empty($this->iTemplateAddendumObject) && method_exists($this->iTemplateAddendumObject, $methodName)) {
					if (!empty($parts)) {
						call_user_func(array($this->iTemplateAddendumObject, $methodName), $parts);
					} else {
						call_user_func(array($this->iTemplateAddendumObject, $methodName));
					}
				}
			} else if (substr($thisLine, 0, strlen("%module:")) == "%module:") {
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
				echo $thisLine . "\n";
			}
		}
		$pageContent = ob_get_clean();
		return trim($pageContent);
	}

	private function getPageTextChunks() {
		if ($this->iPageTextChunks === false) {
			$this->iPageTextChunks = array();
			if (is_array($GLOBALS['gPageRow']['page_text_chunks'])) {
				foreach ($GLOBALS['gPageRow']['page_text_chunks'] as $pageTextChunkCode => $pageTextChunkContent) {
					$this->iPageTextChunks[strtolower($pageTextChunkCode)] = $pageTextChunkContent;
				}
				if (function_exists("massagePageTextChunks")) {
					massagePageTextChunks($this->iPageTextChunks);
				}
				if (method_exists($this, "massagePageTextChunks")) {
					$this->massagePageTextChunks($this->iPageTextChunks);
				}
			}
		}
	}

	/**
	 * function setup
	 *
	 *    setup for the page.
	 */
	function setup() {
		return false;
	}

	/**
	 * function headerIncludes
	 *
	 *    header includes for the page. Return false so that the template will know that it still needs to deal with header
	 *    includes. If true is returned, the template will not deal with header includes.
	 */
	function headerIncludes() {
		return false;
	}

	/**
	 * function onLoadJavascript
	 *
	 *    onload javascript code for the page. Return false so that the template will know that it still needs to add its own
	 *    onload javascript code. If true is returned, the template will not add onload javascript code.
	 */
	final function onLoadPageJavascript() {
		ob_start();
		$returnValue = $this->onLoadJavascript();
		$pageJavascript = ob_get_clean();
		$holdJavascriptLines = getContentLines($pageJavascript);
		$javascriptLines = array();
		foreach ($holdJavascriptLines as $thisLine) {
			if (substr($thisLine, 0, strlen("<!--suppress")) != "<!--suppress") {
				$javascriptLines[] = $thisLine;
			}
		}
		$startTag = "<script>";
		$startFunction = "$(function() {";
		$endTag = "</script>";
		$endFunction = "});";
		if (count($javascriptLines) > 0) {
			if (strpos($javascriptLines[count($javascriptLines) - 1], "</script") !== false) {
				$endTag = array_pop($javascriptLines);
			}
			if (strpos($javascriptLines[0], "<script") !== false) {
				$startTag = array_shift($javascriptLines);
			}
			if (strpos($javascriptLines[0], "$(function() {") !== false) {
				$startFunction = array_shift($javascriptLines);
				$endFunction = array_pop($javascriptLines);
			}
		}
		$pageJavascript = implode("\n", $javascriptLines);
		if (!empty($_SESSION['original_user_id'])) {
			$pageJavascript .= "\n$('body').addClass('simulate-user-on');\n";
		}
		$pageJavascript .= ($GLOBALS['gLoggedIn'] ? "\n$('.user-not-logged-in').remove();\n" : "\n$('.user-logged-in').remove();\n");
		$pageJavascript .= ($GLOBALS['gLoggedIn'] && $GLOBALS['gUserRow']['administrator_flag'] ? "\n$('.admin-not-logged-in').remove();\n" : "\n$('.admin-logged-in').remove();\n");
		$pageJavascript .= ($GLOBALS['gLoggedIn'] && $GLOBALS['gUserRow']['superuser_flag'] ? "\n$('.super-not-logged-in').remove();\n" : "\n$('.super-logged-in').remove();\n");
		$pageJavascript .= ($GLOBALS['gDevelopmentServer'] ? "\n$('.development-server').removeClass('hidden');\n" : "\n$('.development-server').remove();\n");

		$removeTargets = array();
		$resultSet = executeQuery("select * from user_types where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$removeTargets[] = ".user-type-" . ($GLOBALS['gUserRow']['user_type_id'] == $row['user_type_id'] ? "not-" : "") . str_replace("_", "-", strtolower($row['user_type_code']));
		}
		$resultSet = executeQuery("select * from user_groups left outer join user_group_members on user_group_members.user_group_id = user_groups.user_group_id and " .
			"user_group_members.user_id = ? where client_id = ?", $GLOBALS['gUserId'], $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$removeTargets[] = ".user-group-" . ($GLOBALS['gUserId'] == $row['user_id'] ? "not-" : "") . str_replace("_", "-", strtolower($row['user_group_code']));
		}
		if (!empty($removeTargets)) {
			$pageJavascript .= "\n$('" . implode(",", $removeTargets) . "').remove();\n";
		}
		ob_start();
		?>
        if ("commaSeparatedProductCategoryCodes" in window) {
        const productCategoryCodes = commaSeparatedProductCategoryCodes.toLowerCase().split(",");
        $(".product-category").each(function() {
        const productCategoryCode = $(this).data("product_category_code");
        if (!empty(productCategoryCode) && !isInArray(productCategoryCode, productCategoryCodes)) {
        $(this).remove();
        }
        });
        }
        if ("commaSeparatedProductDepartmentCodes" in window) {
        const productDepartmentCodes = commaSeparatedProductDepartmentCodes.toLowerCase().split(",");
        $(".product-department").each(function() {
        const productDepartmentCode = $(this).data("product_department_code");
        if (!empty(productDepartmentCode) && !isInArray(productDepartmentCode, productDepartmentCodes)) {
        $(this).remove();
        }
        });
        }
        if ("commaSeparatedProductCategoryGroupCodes" in window) {
        const productCategoryGroupCodes = commaSeparatedProductCategoryGroupCodes.toLowerCase().split(",");
        $(".product-category-group").each(function() {
        const productCategoryGroupCode = $(this).data("product_category_group_code");
        if (!empty(productCategoryGroupCode) && !isInArray(productCategoryGroupCode, productCategoryGroupCodes)) {
        $(this).remove();
        }
        });
        }
		<?php
		$pageJavascript .= ob_get_clean();
		echo $startTag . "\n" . $startFunction . "\n" . $pageJavascript . "\n" . $endFunction . "\n" . $endTag . "\n";
		return $returnValue;
	}

	function onLoadJavascript() {
		return false;
	}

	/**
	 * function javascript
	 *
	 *    javascript code for the page. Return false so that the template will know that it still needs to add its own
	 *    javascript code. If true is returned, the template will not add javascript code.
	 */
	final function pageJavascript() {
		ob_start();
		$returnValue = $this->javascript();
		$pageJavascript = ob_get_clean();
		$holdJavascriptLines = getContentLines($pageJavascript);
		$javascriptLines = array();
		foreach ($holdJavascriptLines as $thisLine) {
			if (substr($thisLine, 0, strlen("<!--suppress")) != "<!--suppress") {
				$javascriptLines[] = $thisLine;
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
		$pageJavascript = implode("\n", $javascriptLines);

		$userGroupCodes = "";
		$resultSet = executeQuery("select * from user_groups join user_group_members using (user_group_id) where user_group_members.user_id = ?", $GLOBALS['gUserId']);
		while ($row = getNextRow($resultSet)) {
			$userGroupCodes .= (empty($userGroupCodes) ? "" : ",") . strtolower($row['user_group_code']);
		}

		echo $startTag . "\n";
		echo "var userIsLoggedIn = " . ($GLOBALS['gLoggedIn'] ? "true" : "false") . ";\n";
		echo "var adminIsLoggedIn = " . ($GLOBALS['gLoggedIn'] && $GLOBALS['gUserRow']['administrator_flag'] ? "true" : "false") . ";\n";
		echo "var loggedInUserTypeCode = '" . getFieldFromId("user_type_code", "user_types", "user_type_id", $GLOBALS['gUserRow']['user_type_id']) . "';\n";
		echo "var loggedInUserGroupCodes = '" . $userGroupCodes . "';\n";
		echo "var loggedInUserId = '" . $GLOBALS['gUserRow']['user_id'] . "';\n";
		echo $pageJavascript . "\n" . ($returnValue ? "" : $GLOBALS['gPageRow']['javascript_code']) . "\n" . $endTag . "\n";
		return $returnValue;
	}

	function javascript() {
		return false;
	}

	/**
	 * function internalPageCSS
	 *
	 *    css styles included in the header of the page. Return false so that the template will know that it still needs to add its own
	 *    css styles. If true is returned, the template will not add css styles.
	 */
	final function internalPageCSS() {
		ob_start();
		$returnValue = $this->internalCSS();
		$pageCSS = ob_get_clean();
		$cssLines = getContentLines($pageCSS);
		$startTag = "<style>";
		$endTag = "</style>";
		if (count($cssLines) > 0) {
			if (strpos($cssLines[count($cssLines) - 1], "</style") !== false) {
				$endTag = array_pop($cssLines);
			}
			if (strpos($cssLines[0], "<style") !== false) {
				$startTag = array_shift($cssLines);
			}
		}
		$pageCSS = implode("\n", $cssLines);
		if (!empty($pageCSS)) {
			echo $startTag . "\n/* PHP Page CSS */\n" . processCssContent($pageCSS) . "\n" . $endTag . "\n";
			$pageCSS = "";
		}
		$pageCSS .= getSassHeaders() . $GLOBALS['gPageRow']['css_content'];
		if (!empty($pageCSS)) {
			echo $startTag . "\n/* Page CSS */\n" . processCssContent($pageCSS) . "\n" . $endTag . "\n";
		}
		return $returnValue;
	}

	function internalCSS() {
		return false;
	}

	/**
	 * function jqueryTemplates
	 *
	 *    JQuery templates for the page. Return false so that the template will know that it still needs to add its own
	 *    JQuery templates. If true is returned, the template will not add JQuery templates.
	 */
	function jqueryTemplates() {
		return false;
	}

	/**
	 * function hiddenElements
	 *
	 *    hidden elements, like dialogs, for the page. Return false so that the template will know that it still needs to add its own
	 *    hidden elements. If true is returned, the template will not add hidden elements.
	 */
	function hiddenElements() {
		return false;
	}

	/**
	 * function mainContent
	 *
	 *    the main content for the page. Return false so that the template will know that it still needs to add its own
	 *    main content. If true is returned, the template will not add anything to the main content.
	 */
	function mainContent() {
		return false;
	}

	/**
	 * function footer
	 *
	 *    footer for the page. Return false so that the template will know that it still needs to add its own
	 *    footer. If true is returned, the template will not add a footer.
	 */
	function footer() {
		return false;
	}

	/**
	 * function massageDataSource
	 *
	 *    In the concrete object of this class, this function can be overridden to add join tables, and all the other things
	 *    that you can do to the data source
	 */
	function massageDataSource() {
	}

	/**
	 * function executeSubaction
	 *
	 *    In the concrete object of this class, this function can be overridden to execute subactions
	 */
	function executeSubaction() {
	}

	/**
	 * function getPrimaryTableName
	 *
	 *    get the name of the primary table used by this page
	 */
	function getPrimaryTableName() {
		return $this->iPrimaryTableName;
	}

	/**
	 * function setPrimaryTableName
	 *
	 *    Set the name of the primary table used by this page
	 */
	function setPrimaryTableName($primaryTableName) {
		$this->iPrimaryTableName = $primaryTableName;
	}

	/**
	 * function displayPage
	 *
	 *    Main function that displays the page to the browser. First, execute the url actions, then display the page
	 */
	function displayPage() {
		if ($GLOBALS['gCommandLine'] && $this instanceof BackgroundReport) {
			return;
		}
		if (!$GLOBALS['gCommandLine'] && !$_SERVER['HTTPS'] && (file_exists($GLOBALS['gDocumentRoot'] . "/force_https") || $GLOBALS['gForceSSL'])) {
			header("HTTP/1.1 301 Moved Permanently");
			header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
			exit;
		}
		if ($GLOBALS['gEmbeddedPage']) {
			if (!$GLOBALS['gEmbeddablePage']) {
				echo "<body><p>Page cannot be embedded.</p></body>";
				exit;
			}
			$domainName = str_replace("/", "", explode("/", str_replace("https://", "", $_SERVER['HTTP_REFERER']))[0]);
			$resultSet = executeQuery("select * from domain_names where domain_name = ? and inactive = 0", $domainName);
			if (!$domainNameRow = getNextRow($resultSet)) {
				$domainNameRow = array();
				if (substr($domainName, 0, 4) == "www.") {
					$resultSet = executeQuery("select * from domain_names where domain_name = ? and include_www = 1 and inactive = 0", substr($domainName, 4));
					if (!$domainNameRow = getNextRow($resultSet)) {
						$domainNameRow = array();
					}
				}
			}
			if (empty($domainNameRow['allow_embeds']) && !empty($_GET['ajax'])) {
				echo "<body><p>Unauthorized Embed: " . htmlText($domainName) . "</p></body>";
				exit;
			}
		}

		$this->getPageTextChunks();
		foreach ($this->iPageTextChunks as $pageTextChunkCode => $pageTextChunkContent) {
			foreach ($GLOBALS['gPageRow'] as $fieldName => $fieldData) {
				$GLOBALS['gPageRow'][$fieldName] = str_ireplace("%page_text_chunk:" . strtolower($pageTextChunkCode) . "%", $pageTextChunkContent, $fieldData);
			}
			foreach ($this->iPageData as $index => $data) {
				if (!is_array($data)) {
					$this->iPageData[$index] = str_ireplace("%" . strtolower($pageTextChunkCode) . "%", $pageTextChunkContent, $data);
				}
			}
		}
		$this->executeUrlActions();
		$this->iTemplateObject->displayPage();
	}

	/**
	 * function executeUrlActions
	 *
	 * execute actions from the url, starting with the page and then the template
	 */
	final public function executeUrlActions() {
		$this->executePageUrlActions();
		$this->iTemplateObject->executeUrlActions();
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "page_module_search_knowledge_base":
				$knowledgeBaseCategoryId = getFieldFromId("knowledge_base_category_id", "knowledge_base_categories", "knowledge_base_category_id", $_POST['knowledge_base_category_id']);
				$displayLimit = $_POST['display_limit'];
				if (empty($displayLimit)) {
					$displayLimit = 10;
				}
				$resultSet = executeQuery("select * from knowledge_base where knowledge_base_id in " .
					"(select knowledge_base_id from knowledge_base_category_links where knowledge_base_category_id = ?) " .
					(empty($_POST['search_text']) ? "" : "and (title_text like " . makeParameter("%" . $_POST['search_text'] . "%") . " or content like " . makeParameter("%" . $_POST['search_text'] . "%") . ") ") .
					"order by date_entered desc,knowledge_base_id desc limit " . $displayLimit, $knowledgeBaseCategoryId);
				ob_start();
				while ($row = getNextRow($resultSet)) {
					?>
                    <div class='knowledge-base-entry'>
                        <h3><?= htmlText($row['title_text']) ?></h3>
                        <h4><?= date("m/d/Y", strtotime($row['date_entered'])) ?></h4>
						<?= makeHtml($row['content']) ?>
                    </div>
					<?php
				}
				$returnArray['knowledge_base_entries'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
		}
	}

	/**
	 * function executePageUrlActions
	 *
	 *    In the concrete object of this class, this function can be overridden to execute any page specific URL parameter functions
	 */
	protected function executePageUrlActions() {
	}

	/**
	 * function getTemplate
	 *
	 *    get the template object used by this page
	 */
	function getTemplate() {
		return $this->iTemplateObject;
	}

	/**
	 * function getDataSource
	 *
	 *    get the data source used by this page
	 */
	function getDataSource() {
		return $this->iDataSource;
	}

	/**
	 * function setDataSource
	 *
	 *    set the data source for this page
	 */
	function setDataSource($dataSource) {
		$this->iDataSource = $dataSource;
	}

	/**
	 * function getDatabase
	 *
	 *    get the database connection used by this page
	 */
	function getDatabase() {
		return $this->iDatabase;
	}

	/**
	 * function setDatabase
	 *
	 *    set the database used by this page
	 */
	function setDatabase($database) {
		$this->iDatabase = $database;
	}

	/**
	 * function getPageData
	 *
	 *    return the template data set for this page
	 */
	function getPageData($dataName) {
		return $this->iPageData[$dataName];
	}

	/**
	 * function setPageData
	 *
	 *    set the template data for this page
	 */
	function setPageData($dataName, $dataValue) {
		$this->iPageData[$dataName] = PlaceHolders::massageContent($dataValue);
	}

	function getFragment($fragmentCode, $substitutionValues = array()) {
		if (!is_array($GLOBALS['gFragmentContents']) || !isset($GLOBALS['gFragmentContents'])) {
			$GLOBALS['gFragmentContents'] = array();
			$resultSet = executeReadQuery("select * from fragments where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$GLOBALS['gFragmentContents'][$row['fragment_code']] = $row['content'];
			}
		}
		$fragmentCode = strtoupper($fragmentCode);
		if (array_key_exists($fragmentCode, $GLOBALS['gFragmentContents'])) {
			$content = $GLOBALS['gFragmentContents'][$fragmentCode];
		}
		$content = massageFragmentContent($content, $substitutionValues);
		return $this->replaceImageReferences($content);
	}

	function getBanner($parameters = array()) {
		$whereStatement = "client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") .
			" and (banner_id in (select banner_id from banner_context where page_id = ?" .
			(empty($parameters['context']) ? "" : " and context_qualifier like ?") . ") or banner_id not in (select banner_id from banner_context)) and " .
			"(start_time is null or start_time <= now()) and (end_time is null or end_time >= now())";
		$whereParameters = array($GLOBALS['gClientId'], $GLOBALS['gPageId']);
		if (!empty($parameters['context'])) {
			$whereParameters[] = "%" . $parameters['context'] . "%";
		}
		if (!empty($parameters['banner_group_code'])) {
			$whereStatement .= " and banner_id in (select banner_id from banner_group_links where banner_group_id = " .
				"(select banner_group_id from banner_groups where banner_group_code = ? and client_id = ?))";
			$whereParameters[] = $parameters['banner_group_code'];
			$whereParameters[] = $GLOBALS['gClientId'];
		}
		$whereStatement .= " order by rand()";
		$resultSet = executeQuery("select * from banners where " . $whereStatement, $whereParameters);
		if ($row = getNextRow($resultSet)) {
			return $row;
		}
		return false;
	}

	function getBanners($parameters = array()) {
		$whereStatement = "client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") .
			" and (banner_id in (select banner_id from banner_context where page_id = ?" .
			(empty($parameters['context']) ? "" : " and context_qualifier like ?") . ") or banner_id not in (select banner_id from banner_context)) and " .
			"(start_time is null or start_time <= now()) and (end_time is null or end_time >= now())";
		$whereParameters = array($GLOBALS['gClientId'], $GLOBALS['gPageId']);
		if (!empty($parameters['context'])) {
			$whereParameters[] = "%" . $parameters['context'] . "%";
		}
		if (!empty($parameters['banner_group_code'])) {
			$whereStatement .= " and banner_id in (select banner_id from banner_group_links where banner_group_id = " .
				"(select banner_group_id from banner_groups where banner_group_code = ? and client_id = ?))";
			$whereParameters[] = $parameters['banner_group_code'];
			$whereParameters[] = $GLOBALS['gClientId'];
		}
		$whereStatement .= " order by sort_order";
		$banners = array();
		$resultSet = executeQuery("select * from banners where " . $whereStatement, $whereParameters);
		while ($row = getNextRow($resultSet)) {
			$banners[] = $row;
		}
		if (count($banners) > 0) {
			if ($parameters['randomize']) {
				shuffle($banners);
			}
			return $banners;
		} else {
			return false;
		}
	}

	function getPageTextChunk($pageTextChunkCode, $substitutions = array(), $defaultValue = "") {
		$this->getPageTextChunks();
		$content = "";
		if (is_array($GLOBALS['gTemplateRow']) && array_key_exists("template_text_chunks", $GLOBALS['gTemplateRow'])) {
			$content = $GLOBALS['gTemplateRow']['template_text_chunks'][strtolower($pageTextChunkCode)];
		} else {
			$templateTextChunkId = getReadFieldFromId("template_text_chunk_id", "template_text_chunks", "template_id", $GLOBALS['gPageTemplateId'], "template_text_chunk_code = ?", strtoupper($pageTextChunkCode));
			if (!empty($templateTextChunkId)) {
				$content = getReadFieldFromId("content", "template_text_chunks", "template_text_chunk_id", $templateTextChunkId);
			}
		}
		$newContent = $this->iPageTextChunks[strtolower($pageTextChunkCode)];
		if (!empty($newContent)) {
			$content = $newContent;
		}
		foreach ($substitutions as $fieldName => $fieldValue) {
			$content = str_replace("%" . $fieldName . "%", (is_scalar($fieldValue) ? $fieldValue : ""), $content);
		}
		$content = PlaceHolders::massageContent($content, $substitutions);
		$content = $this->replaceImageReferences($content);
		if (strlen($content) == 0) {
			$content = $defaultValue;
		}
		return $content;
	}

	public static function getClientPagePreferences($pageCode = "", $preferenceCode = "PROGRAM_SPECIFIC_SETTINGS") {
		if (empty($pageCode)) {
			$pageCode = $GLOBALS['gPageCode'];
		}
		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", $preferenceCode);

		$settings = getFieldFromId("preference_value", "client_preferences", "preference_id", $preferenceId, "client_id = ? and preference_qualifier = ?", $GLOBALS['gClientId'], $pageCode);
		$valuesArray = json_decode($settings, true);
		if (empty($valuesArray)) {
			$valuesArray = array();
		}
		return $valuesArray;
	}

	public static function setClientPagePreferences($valuesArray = array(), $pageCode = "", $preferenceCode = "PROGRAM_SPECIFIC_SETTINGS") {
		if (empty($pageCode)) {
			$pageCode = $GLOBALS['gPageCode'];
		}
		$preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", $preferenceCode);

		$clientPreferenceId = getFieldFromId("client_preference_id", "client_preferences", "preference_id", $preferenceId, "client_id = ? and preference_qualifier = ?", $GLOBALS['gClientId'], $pageCode);
		$dataTable = new DataTable("client_preferences");
		$dataTable->setSaveOnlyPresent(true);
		$nameValues = array("client_id" => $GLOBALS['gClientId'], "preference_id" => $preferenceId, "preference_qualifier" => $pageCode, "preference_value" => jsonEncode($valuesArray));
		$dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $clientPreferenceId));
	}

	public static function getPagePreferences($pageCode = "", $preferenceCode = "PROGRAM_SPECIFIC_SETTINGS") {
		if (empty($pageCode)) {
			$pageCode = $GLOBALS['gPageCode'];
		}
		$settings = getPreference($preferenceCode, $pageCode);
		$valuesArray = json_decode($settings, true);
		if (empty($valuesArray)) {
			$valuesArray = array();
		}
		return $valuesArray;
	}

	public static function setPagePreferences($valuesArray = array(), $pageCode = "", $preferenceCode = "PROGRAM_SPECIFIC_SETTINGS") {
		if (empty($pageCode)) {
			$pageCode = $GLOBALS['gPageCode'];
		}
		setUserPreference($preferenceCode, jsonEncode($valuesArray), $pageCode);
	}

	public static function pageIsUnderMaintenance() {
		if (!empty($GLOBALS['gUserRow']['superuser_flag'])) {
			return false;
		}
		$resultSet = executeQuery("select * from page_maintenance_schedules where page_id = ? and (start_date is null or start_date <= current_date) and " .
			"(end_date is null or end_date >= current_date) order by page_maintenance_schedule_id", $GLOBALS['gPageId']);
		$maintenanceContent = false;
		while ($row = getNextRow($resultSet)) {
			if ($GLOBALS['gLoggedIn'] && !empty($row['user_group_id']) && isInUserGroup($GLOBALS['gUserId'], $row['user_group_id'])) {
				continue;
			}
			if (!empty($row['public_access'])) {
				$maintenanceContent = $row['content'];
				break;
			}
			if (!$GLOBALS['gLoggedIn'] && !empty($row['not_logged_in'])) {
				$maintenanceContent = $row['content'];
				break;
			}
			if ($GLOBALS['gLoggedIn'] && !empty($row['logged_in']) && empty($GLOBALS['gUserRow']['administrator_flag'])) {
				$maintenanceContent = $row['content'];
				break;
			}
			if ($GLOBALS['gLoggedIn'] && !empty($row['administrator_access']) && !empty($GLOBALS['gUserRow']['administrator_flag'])) {
				$maintenanceContent = $row['content'];
				break;
			}
		}
		if ($maintenanceContent !== false) {
			echo $maintenanceContent;
			return true;
		} else {
			return false;
		}
	}

}
