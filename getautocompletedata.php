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

$GLOBALS['gPageCode'] = "GETAUTOCOMPLETEDATA";
require_once "shared/startup.inc";

$defaultLimit = 200;

$returnArray = array();

$multipleValues = false;
$valueRequests = array();
if ($_GET['get_value'] == "true" || $_GET['get_values'] == "true") {
	$multipleValues = ($_GET['get_values'] == "true");
	if ($multipleValues) {
		$returnArray['autocomplete_text_values'] = array();
		if (is_array($_POST['autocomplete_fields'])) {
			$autocompleteFields = $_POST['autocomplete_fields'];
		} else {
			$autocompleteFields = array();
		}
		foreach ($autocompleteFields as $thisField) {
			$valueRequests[] = array("field_name" => $thisField['field_name'], "value_id" => $thisField['value_id'], "additional_filter" => $thisField['additional_filter'], "tag" => $thisField['tag']);
		}
	} else {
		$valueRequests[] = array("field_name" => "", "value_id" => $_GET['value_id'], "tag" => $_GET['tag'], "additional_filter" => $_GET['additional_filter']);
	}
}

if (file_exists($GLOBALS['gDocumentRoot'] . "/getautocompletedata.inc")) {
	include_once "getautocompletedata.inc";
}

if ($_GET['get_value'] == "true" || $_GET['get_values'] == "true") {
	foreach ($valueRequests as $thisRequest) {
		$returnValue = array();
		if (function_exists("_localGetAutocompleteValueText")) {
			$returnValue = _localGetAutocompleteValueText($thisRequest);
		}
		if (empty($returnValue)) {
			switch ($thisRequest['tag']) {
				case "contacts":
					$resultSet = executeQuery("select contact_id,first_name,last_name,address_1,city from contacts where client_id = ? and contact_id = ?", $GLOBALS['gClientId'], $thisRequest['value_id']);
					if ($row = getNextRow($resultSet)) {
						$returnValue['autocomplete_text'] = getDisplayName($row['contact_id']);
					}
					break;
				case "referral_contacts":
					$resultSet = executeQuery("select contact_id,first_name,last_name,address_1,city from contacts where client_id = ? and contact_id = ? and contact_id in " .
						"(select contact_id from contact_categories where category_id = (select category_id from categories where client_id = ? and category_code = 'REFERRER'))", $GLOBALS['gClientId'], $thisRequest['value_id'], $GLOBALS['gClientId']);
					if ($row = getNextRow($resultSet)) {
						$returnValue['autocomplete_text'] = $row['contact_id'] . ", " . getDisplayName($row['contact_id']) . (empty($row['address_1']) ? "" : ", " . $row['address_1']) . (empty($row['city']) ? "" : ", " . $row['city']);
					}
					break;
				case "events":
					$resultSet = executeQuery("select event_id,description from events where client_id = ? and event_id = ?", $GLOBALS['gClientId'], $thisRequest['value_id']);
					if ($row = getNextRow($resultSet)) {
						$returnValue['autocomplete_text'] = $row['description'] . " - " . $row['start_date'];
					}
					break;
				case "languages":
					$resultSet = executeQuery("select iso_code,description from languages where language_id = ?", $thisRequest['value_id']);
					if ($row = getNextRow($resultSet)) {
						$returnValue['autocomplete_text'] = $row['iso_code'] . " - " . $row['description'];
					}
					break;
				case "products":
					$resultSet = executeQuery("select product_code,description from products where client_id = ? and product_id = ?", $GLOBALS['gClientId'], $thisRequest['value_id']);
					if ($row = getNextRow($resultSet)) {
						$returnValue['autocomplete_text'] = $row['product_code'] . " - " . $row['description'];
					}
					break;
				case "product_manufacturers":
					$resultSet = executeQuery("select product_manufacturer_code,description from product_manufacturers where client_id = ? and product_manufacturer_id = ?", $GLOBALS['gClientId'], $thisRequest['value_id']);
					if ($row = getNextRow($resultSet)) {
						$returnValue['autocomplete_text'] = $row['description'];
					}
					break;
				case "product_facets":
					$resultSet = executeQuery("select description from product_facets where client_id = ? and product_facet_id = ?", $GLOBALS['gClientId'], $thisRequest['value_id']);
					if ($row = getNextRow($resultSet)) {
						$returnValue['autocomplete_text'] = $row['description'];
					}
					break;
				case "product_facet_options":
					$resultSet = executeQuery("select facet_value from product_facet_options where product_facet_id in (select product_facet_id from product_facets where client_id = ?) and product_facet_option_id = ?", $GLOBALS['gClientId'], $thisRequest['value_id']);
					if ($row = getNextRow($resultSet)) {
						$returnValue['autocomplete_text'] = $row['facet_value'];
					}
					break;
				case "designations":
					$resultSet = executeQuery("select designation_code,description from designations where client_id = ? and designation_id = ?", $GLOBALS['gClientId'], $thisRequest['value_id']);
					if ($row = getNextRow($resultSet)) {
						$returnValue['autocomplete_text'] = $row['designation_code'] . " - " . $row['description'];
					}
					break;
				case "contributors":
					$resultSet = executeQuery("select full_name from contributors where client_id = ? and contributor_id = ?", $GLOBALS['gClientId'], $thisRequest['value_id']);
					if ($row = getNextRow($resultSet)) {
						$returnValue['autocomplete_text'] = $row['full_name'];
					}
					break;
				case "companies":
					$resultSet = executeQuery("select * from companies where contact_id in (select contact_id from contacts where client_id = ?) and company_id = ?", $GLOBALS['gClientId'], $thisRequest['value_id']);
					if ($row = getNextRow($resultSet)) {
						$returnValue['autocomplete_text'] = getDisplayName($row['contact_id'], array("use_company" => true));
					}
					break;
				default:
					$resultSet = executeQuery("select table_name from tables where table_name = ? and " .
						"table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'internal_use_only')) and " .
						"table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'inactive')) and " .
						"table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'client_id'))", $thisRequest['tag']);
					if ($row = getNextRow($resultSet)) {
						$tableName = $row['table_name'];
					} else {
						$tableName = "";
					}
					if (!empty($tableName)) {
						$controlTable = new DataTable($tableName);
						$primaryKey = $controlTable->getPrimaryKey();
						$resultSet = executeQuery("select description from " . $tableName . " where client_id = ? and " . $primaryKey . " = ?", $GLOBALS['gClientId'], $thisRequest['value_id']);
						if ($row = getNextRow($resultSet)) {
							$returnValue['autocomplete_text'] = $row['description'];
						}
					}
					break;
			}
		}
		if (!empty($returnValue)) {
			if ($multipleValues) {
				$returnValue['field_name'] = $thisRequest['field_name'];
				$returnArray['autocomplete_text_values'][] = $returnValue;
			} else {
				$returnArray = $returnValue;
			}
		}
	}
	if (!$multipleValues && !empty($returnArray['autocomplete_text'])) {
		setCachedData("getautocomplete_text",$_GET['tag'] . ":" . $_GET['value_id'],$returnArray['autocomplete_text'],24,true);
	}
} else {
	$returnArray['results'] = array();

	switch ($_GET['tag']) {
		case "languages":
			$resultSet = executeQuery("select language_id,iso_code,description from languages where (description like ? or iso_code like ?) and inactive = 0 order by description limit " . $defaultLimit, $_GET['search_text'] . "%", $_GET['search_text'] . "%");
			while ($row = getNextRow($resultSet)) {
				$returnArray['results'][] = array("key_value" => $row['language_id'], "description" => $row['iso_code'] . " - " . $row['description']);
			}
			break;
		case "products":
			$resultSet = executeQuery("select product_id,product_code,(select upc_code from product_data where product_id = products.product_id) upc_code,description from products where client_id = ? and (description like ? or product_code like ? or " .
				"product_id in (select product_id from product_data where upc_code = ?))" .
				(empty($_GET['additional_filter']) ? " and inactive = 0" : "") .
				" order by description limit " . $defaultLimit,
				$GLOBALS['gClientId'], $_GET['search_text'] . "%", $_GET['search_text'] . "%", $_GET['search_text']);
			while ($row = getNextRow($resultSet)) {
				$returnArray['results'][] = array("key_value" => $row['product_id'], "description" => $row['product_code'] . " - " . (empty($row['upc_code']) ? "" : $row['upc_code'] . " - ") . $row['description']);
			}
			break;
		case "product_manufacturers":
			$resultSet = executeQuery("select product_manufacturer_id,product_manufacturer_code,description from product_manufacturers where client_id = ? and description like ? and inactive = 0 order by description limit " . $defaultLimit,
				$GLOBALS['gClientId'], "%" . $_GET['search_text'] . "%");
			while ($row = getNextRow($resultSet)) {
				$returnArray['results'][] = array("key_value" => $row['product_manufacturer_id'], "description" => $row['description']);
			}
			break;
		case "product_facets":
			$searchText = "%" . $_GET['search_text'] . "%";
			$resultSet = executeQuery("select product_facet_id,description from product_facets where client_id = ? and description like ? and inactive = 0 order by description limit " . $defaultLimit, $GLOBALS['gClientId'], $searchText);
			while ($row = getNextRow($resultSet)) {
				$returnArray['results'][] = array("key_value" => $row['product_facet_id'], "description" => $row['description']);
			}
			break;
		case "product_facet_options":
			$searchText = "%" . $_GET['search_text'] . "%";
			$resultSet = executeQuery("select product_facet_option_id,facet_value from product_facet_options where product_facet_id in (select product_facet_id from product_facets where client_id = ?) and facet_value like ? and product_facet_id = ? order by facet_value limit " . $defaultLimit, $GLOBALS['gClientId'], $searchText, $_GET['additional_filter']);
			while ($row = getNextRow($resultSet)) {
				$returnArray['results'][] = array("key_value" => $row['product_facet_option_id'], "description" => $row['facet_value']);
			}
			break;
		case "designations":
			$searchText = "%" . $_GET['search_text'] . "%";
			$resultSet = executeQuery("select designation_id,designation_code,description from designations where client_id = ? and (description like ? or designation_code like ?) and inactive = 0 order by description limit " . $defaultLimit, $GLOBALS['gClientId'], $searchText, $searchText);
			while ($row = getNextRow($resultSet)) {
				$returnArray['results'][] = array("key_value" => $row['designation_id'], "description" => $row['designation_code'] . " - " . $row['description']);
			}
			break;
		case "contributors":
			$searchText = "%" . $_GET['search_text'] . "%";
			$resultSet = executeQuery("select contributor_id,full_name from contributors where client_id = ? and full_name like ? and inactive = 0 order by full_name limit " . $defaultLimit, $GLOBALS['gClientId'], $searchText);
			while ($row = getNextRow($resultSet)) {
				$returnArray['results'][] = array("key_value" => $row['contributor_id'], "description" => $row['full_name']);
			}
			break;
		case "companies":
			$searchText = "%" . $_GET['search_text'] . "%";
			$resultSet = executeQuery("select companies.company_id,contacts.contact_id from companies join contacts using (contact_id) where client_id = ? and (business_name like ? or first_name like ? or last_name like ?) and inactive = 0 order by business_name,last_name,first_name limit " . $defaultLimit, $GLOBALS['gClientId'], $searchText, $searchText, $searchText);
			while ($row = getNextRow($resultSet)) {
				$returnArray['results'][] = array("key_value" => $row['company_id'], "description" => getDisplayName($row['contact_id']));
			}
			break;
		case "contacts":
			$searchText = $_GET['search_text'] . "%";
			$resultSet = executeQuery("select contact_id,first_name,last_name,address_1,city from contacts where client_id = ? and (contact_id = ? or business_name like ? or first_name like ? or last_name like ? or address_1 like ?) and deleted = 0 " .
				"order by business_name,last_name,first_name limit " . $defaultLimit, $GLOBALS['gClientId'], $_GET['search_text'], $searchText, $searchText, $searchText, $searchText);
			while ($row = getNextRow($resultSet)) {
				$returnArray['results'][] = array("key_value" => $row['contact_id'], "description" => $row['contact_id'] . ", " . getDisplayName($row['contact_id']) .
					(empty($row['address_1']) ? "" : ", " . $row['address_1']) . (empty($row['city']) ? "" : ", " . $row['city']));
			}
			break;
		case "referral_contacts":
			$searchText = $_GET['search_text'] . "%";
			$resultSet = executeQuery("select contact_id,first_name,last_name,address_1,city from contacts where client_id = ? and " .
				"(contact_id = ? or business_name like ? or first_name like ? or last_name like ? or address_1 like ?) and deleted = 0 " .
				"and contact_id in (select contact_id from contact_categories where category_id = (select category_id from categories where client_id = ? and category_code = 'REFERRER')) " .
				"order by business_name,last_name,first_name limit " . $defaultLimit, $GLOBALS['gClientId'], $_GET['search_text'], $searchText, $searchText, $searchText, $searchText, $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$returnArray['results'][] = array("key_value" => $row['contact_id'], "description" => getDisplayName($row['contact_id']));
			}
			break;
        case "events":
            $resultSet = executeQuery("select event_id, description, start_date from events where client_id = ? and (description like ? or detailed_description like ?)"
                . ($_GET['additional_filter'] == "future" ? " and start_date >= current_date" : "") . " and inactive = 0 order by description limit " . $defaultLimit,
                $GLOBALS['gClientId'], "%" . $_GET['search_text'] . "%", "%" . $_GET['search_text'] . "%");
            while ($row = getNextRow($resultSet)) {
                $returnArray['results'][] = array("key_value" => $row['event_id'], "description" => $row['description'] . " - " . $row['start_date']);
            }
            break;
		default:
			$resultSet = executeQuery("select table_name from tables where table_name = ? and " .
				"table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'internal_use_only')) and " .
				"table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'inactive')) and " .
				"table_id in (select table_id from table_columns where column_definition_id = (select column_definition_id from column_definitions where column_name = 'client_id'))", $_GET['tag']);
			if ($row = getNextRow($resultSet)) {
				$tableName = $row['table_name'];
			} else {
				$tableName = "";
			}
			if (!empty($tableName)) {
				$searchText = "%" . $_GET['search_text'] . "%";
				$controlTable = new DataTable($tableName);
				$primaryKey = $controlTable->getPrimaryKey();
				$resultSet = executeQuery("select " . $primaryKey . ",description from " . $tableName . " where client_id = ? and " .
					"description like ? and inactive = 0 order by description limit " . $defaultLimit, $GLOBALS['gClientId'], $searchText);
				while ($row = getNextRow($resultSet)) {
					$returnArray['results'][] = array("key_value" => $row[$primaryKey], "description" => $row['description']);
				}
			}
			break;
	}
}

echo jsonEncode($returnArray);
exit;
