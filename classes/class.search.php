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
 * abstract class Search
 *
 * @author Kim D Geiger
 */
class Search {

	/**
	 * function getSearchResults
	 *
	 */
	final public function getSearchResults($searchCriteriaId, $originalSearchText) {
		ProductCatalog::getStopWords();
		if (empty($searchCriteriaId)) {
			$searchCriteriaId = getFieldFromId("search_criteria_id", "search_criteria", "search_criteria_code", "DEFAULT");
		}
		$searchCriteriaId = getFieldFromId("search_criteria_id", "search_criteria", "search_criteria_id", $searchCriteriaId);
		if (empty($searchCriteriaId)) {
			$searchCriteriaId = getFieldFromId("search_criteria_id", "search_criteria", "search_criteria_code", "DEFAULT", "client_id = ?", $GLOBALS['gDefaultClientId']);
		}
		$searchText = strtolower(trim($originalSearchText));
		if (empty($searchCriteriaId) || empty($searchText)) {
			return false;
		}
		if (!$GLOBALS['gPrimaryDatabase']->tableExists("search_terms") && mb_strlen($searchText) <= $GLOBALS['gMaxSearchTermLength'] && mb_strlen($searchText) > 1) {
			$resultSet = executeQuery("select * from search_terms where search_term = ? and client_id = ?", $searchText, $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				$searchTermId = $row['search_term_id'];
				executeQuery("update search_terms set use_count = use_count + 1 where search_term_id = ?", $searchTermId);
			} else {
				$insertSet = executeQuery("insert into search_terms (client_id,search_term,use_count) values (?,?,1)", $GLOBALS['gClientId'], $searchText);
				$searchTermId = $insertSet['insert_id'];
			}
		}
		$queryWords = array();
		foreach (explode(" ", $searchText) as $thisWord) {
			if ($thisWord == $searchText || array_key_exists($thisWord, $GLOBALS['gStopWords'])) {
				continue;
			}
			$queryWords[] = $thisWord;
		}

		$resultArray = array();
		$criteriaSet = executeQuery("select * from search_criteria_tables where search_criteria_id = ?", $searchCriteriaId);
		while ($criteriaRow = getNextRow($criteriaSet)) {
			$queryText = $criteriaRow['query_text'];
			if (strpos($criteriaRow['table_name'], ".") !== false) {
				$tableParts = explode(".", $criteriaRow['table_name']);
				if (count($tableParts) == 2) {
					$resultSet = executeReadQuery("select TABLE_NAME from information_schema.tables where table_schema = ? and table_name = ?",
						$tableParts[0], $tableParts[1]);
					if (!$row = getNextRow($resultSet)) {
						continue;
					}
					$tableName = $criteriaRow['table_name'];
				} else {
					continue;
				}
			} else {
				$tableName = getFieldFromId("table_name", "tables", "table_name", $criteriaRow['table_name']);
				if (empty($tableName)) {
					continue;
				}
			}
			$resultSet = executeQuery("show columns from " . $tableName . " where Field = 'inactive'");
			if ($row = getNextRow($resultSet)) {
				$queryText .= (strpos($queryText, " where ") === false ? " where " : " and ") . "inactive = 0";
			}
			if (!$GLOBALS['gInternalConnection']) {
				$resultSet = executeQuery("show columns from " . $tableName . " where Field = 'internal_use_only'");
				if ($row = getNextRow($resultSet)) {
					$queryText .= (strpos($queryText, " where ") === false ? " where " : " and ") . "internal_use_only = 0";
				}
			}
			$resultSet = executeQuery("show columns from " . $tableName . " where Field = 'client_id'");
			if ($row = getNextRow($resultSet)) {
				$queryText .= (strpos($queryText, " where ") === false ? " where " : " and ") . "client_id = " . $GLOBALS['gClientId'];
			}
			$dataSet = executeQuery($queryText);
			while ($dataRow = getNextRow($dataSet)) {
				$thisResultArray = array();
				$thisResultArray['primary_key'] = reset($dataRow);
				$ranking = 0;
				$resultSet = executeQuery("select * from search_criteria_table_columns where search_criteria_table_id = ?", $criteriaRow['search_criteria_table_id']);
				while ($row = getNextRow($resultSet)) {
					$ranking += substr_count(strtolower($dataRow[$row['column_name']]), $searchText) * 100 * $row['search_multiplier'];
					foreach ($queryWords as $thisWord) {
						$ranking += substr_count(strtolower($dataRow[$row['column_name']]), $searchText) * $row['search_multiplier'];
					}
				}
				if ($ranking == 0) {
					continue;
				}
				$ranking *= $criteriaRow['search_multiplier'];
				$thisResultArray['ranking'] = $ranking;
				$titleText = $criteriaRow['title_text'];
				foreach ($dataRow as $fieldName => $fieldData) {
					$titleText = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $titleText);
				}
				$thisResultArray['description'] = $titleText;
				$excerpt = $criteriaRow['excerpt'];

				foreach ($dataRow as $fieldName => $fieldData) {
					if (strpos($excerpt, "%" . $fieldName . "%") === false &&
						strpos($excerpt, "%excerpt:" . $fieldName . "%") === false &&
						strpos($excerpt, "%" . $fieldName . ":") === false) {
						continue;
					}
					if (strlen($fieldData) == 10 && strtotime($fieldData) !== false) {
						$fieldData = date("m/d/Y", strtotime($fieldData));
						$excerpt = str_ireplace("%" . $fieldName . "%", $fieldData, $excerpt);
						$excerpt = str_ireplace("%excerpt:" . $fieldName . "%", $fieldData, $excerpt);
						continue;
					}
					$excerpt = str_ireplace("%" . $fieldName . "%", $fieldData, $excerpt);
					$excerpt = str_ireplace("%excerpt:" . $fieldName . "%", $this->getContentExcerpt(strip_tags($fieldData), $searchText, $queryWords), $excerpt);
					$startPosition = 0;
					while (strpos($excerpt, "%" . $fieldName . ":", $startPosition) !== false) {
						$startPosition = strpos($excerpt, "%" . $fieldName . ":");
						$endPosition = strpos($excerpt, "%", $startPosition + strlen("%" . $fieldName . ":"));
						if ($endPosition === false) {
							$startPosition += strlen("%" . $fieldName . ":");
							continue;
						}
						$descriptionField = substr($excerpt, $startPosition + strlen("%" . $fieldName . ":"), $endPosition - ($startPosition + strlen("%" . $fieldName . ":")));
						$descriptionField = getFieldFromId("column_name", "column_definitions", "column_name", $descriptionField);
						if (empty($descriptionField)) {
							$startPosition += strlen("%" . $fieldName . ":");
							continue;
						}
						$descriptionData = "";
						$resultSet = executeQuery("select table_name from tables where table_id = (select table_id from table_columns where " .
							"column_definition_id = (select column_definition_id from column_definitions where column_name = ?) and " .
							"primary_table_key = 1)", $fieldName);
						if ($row = getNextRow($resultSet)) {
							$tableName = $row['table_name'];
							$descriptionData = getFieldFromId($descriptionField, $tableName, $fieldName, $fieldData);
						}
						$excerpt = str_ireplace("%" . $fieldName . ":" . $descriptionField . "%", $descriptionData, $excerpt);
					}
				}

				$thisResultArray['content'] = $excerpt;
				$linkUrl = $criteriaRow['link_url'];
				if (startsWith($linkUrl, "return ")) {
					if (substr($linkUrl, -1) != ";") {
						$linkUrl .= ";";
					}
					$linkUrl = eval($linkUrl);
				}
				foreach ($dataRow as $fieldName => $fieldData) {
					$linkUrl = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $linkUrl);
				}
				$thisResultArray['url'] = $linkUrl;
				$resultArray[] = $thisResultArray;
			}
		}
		usort($resultArray, array($this, "rankingSort"));
		if (!empty($searchTermId)) {
			executeQuery("insert into search_term_log (search_term_id,user_id,log_time,result_count) values (?,?,now(),?)", $searchTermId, $GLOBALS['gUserId'], count($resultArray));
		}
		return $resultArray;
	}

	function rankingSort($a, $b) {
		if ($a['ranking'] == $b['ranking']) {
			if ($a['primary_key'] == $b['primary_key']) {
				return 0;
			} else {
				return ($a['primary_key'] > $b['primary_key']) ? -1 : 1;
			}
		}
		return ($a['ranking'] > $b['ranking']) ? -1 : 1;
	}

	function getContentExcerpt($content, $searchText, $queryWords) {
		$content = str_replace("\r", " ", $content);
		$content = str_replace("\n", " ", $content);
		while (strpos($content, "  ") === true) {
			$content = str_replace("  ", " ", $content);
		}
		if (strpos($searchText, " ") !== false) {
			while (stripos($content, $searchText) !== false) {
				$position = stripos($content, $searchText);
				$content = substr($content, 0, $position) . str_replace(" ", "&nbsp;", substr($content, $position, strlen($searchText))) .
					substr($content, $position + strlen($searchText));
			}
		}
		$contentParts = explode(" ", $content);
		$foundPhrase = (stripos($content, str_replace(" ", "&nbsp;", $searchText)) === false);
		$foundWord = true;
		if (!empty($queryWords)) {
			foreach ($contentParts as $thisWord) {
				if (in_array($thisWord, $queryWords)) {
					$foundWord = false;
					break;
				}
			}
		}
		$useContent = array();
		foreach ($contentParts as $contentPart) {
			if (stripos($contentPart, str_replace(" ", "&nbsp;", $searchText)) !== false) {
				$foundPhrase = true;
			}
			if (!$foundPhrase && count($useContent) > 10) {
				array_shift($useContent);
			}
			if (in_array($contentPart, $queryWords)) {
				$foundWord = true;
			}
			$useContent[] = $contentPart;
			if ($foundWord && $foundPhrase && count($useContent) >= 60) {
				break;
			}
		}
		foreach ($useContent as $index => $thisWord) {
			if (stripos($thisWord, str_replace(" ", "&nbsp;", $searchText)) !== false) {
				$useContent[$index] = "<span class='found-word'>" . $thisWord . "</span>";
			} else if (in_array(strtolower($thisWord), $queryWords)) {
				$useContent[$index] = "<span class='found-word'>" . $thisWord . "</span>";
			} else {
				foreach (explode(" ", $searchText) as $thisQueryWord) {
					$useContent[$index] = str_ireplace($thisQueryWord, "<span class='found-word'>" . $thisQueryWord . "</span>", $useContent[$index]);
				}
			}
		}
		$returnContent = implode(" ", $useContent);
		return $returnContent;
	}
}
