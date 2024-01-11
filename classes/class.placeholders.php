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

class PlaceHolders {
	private static $iPlaceholderValues = false;
	private static $iCustomFieldDefaultValues = false;
	private static $iClientId = false;
	private static $iGlobalPlaceholders = false;

	public static function setPlaceholderValue($fieldName, $fieldValue) {
		self::massageContent($fieldValue);
		self::$iPlaceholderValues[$fieldName] = $fieldValue;
	}

	public static function setPlaceholderKeyValue($keyName, $keyValue) {
		$primaryId = "";
		switch ($keyName) {
			case "product_id":
				$primaryId = $keyValue;
			case "product_code":
				if (empty($primaryId)) {
					$primaryId = getFieldFromId("product_id","products","product_code",strtoupper($keyValue));
				}
			case "upc_code":
				if (empty($primaryId)) {
					$primaryId = getFieldFromId("product_id","product_data","upc_code",$keyValue);
				}
				if (empty($primaryId)) {
					break;
				}
				$dataRow = ProductCatalog::getCachedProductRow($primaryId);
				$productCatalog = new ProductCatalog();
				$salePriceInfo = $productCatalog->getProductSalePrice($primaryId, array("product_information"=>$dataRow));
				$dataRow['sale_price'] = $salePriceInfo['sale_price'];
				$quantityOnHandArray = $productCatalog->getInventoryCounts(true, $primaryId);
				$dataRow['inventory_quantity'] = $quantityOnHandArray[$primaryId];
				unset($dataRow['version']);
				unset($dataRow['base_cost']);
				unset($dataRow['client_id']);
				$dataRow['image_url'] = (empty($dataRow['image_id']) ? "" : getImageFilename($dataRow['image_id'],array("use_cdn"=>true)));
				foreach ($dataRow as $fieldName => $fieldValue) {
					if (is_array($fieldValue) || is_object($fieldValue)) {
						continue;
					}
					self::massageContent($fieldValue);
					self::$iPlaceholderValues["product." . $fieldName] = $fieldValue;
				}
				break;
			case "location_id":
				$primaryId = $keyValue;
			case "location_code":
				if (empty($primaryId)) {
					$primaryId = getFieldFromId("location_id","locations","location_code",strtoupper($keyValue));
				}
				$dataRow = getRowFromId("locations","location_id",$primaryId);
				unset($dataRow['version']);
				unset($dataRow['client_id']);
				foreach ($dataRow as $fieldName => $fieldValue) {
					self::massageContent($fieldValue);
					self::$iPlaceholderValues["location." . $fieldName] = $fieldValue;
				}
				break;
			case "designation_id":
				$primaryId = $keyValue;
			case "designation_code":
				if (empty($primaryId)) {
					$primaryId = getFieldFromId("designation_id","designations","designation_code",strtoupper($keyValue));
				}
				$dataRow = getRowFromId("designations","designation_id",$primaryId);
				unset($dataRow['version']);
				unset($dataRow['client_id']);
				unset($dataRow['merchant_account_id']);
				unset($dataRow['gl_account_number']);
				unset($dataRow['class_code']);
				unset($dataRow['secondary_class_code']);
				unset($dataRow['account_number']);
				unset($dataRow['routing_number']);
				unset($dataRow['secondary_class_code']);
				unset($dataRow['reimbursable_expenses']);
				$dataRow['image_url'] = (empty($dataRow['image_id']) ? "" : getImageFilename($dataRow['image_id'],array("use_cdn"=>true)));
				foreach ($dataRow as $fieldName => $fieldValue) {
					self::massageContent($fieldValue);
					self::$iPlaceholderValues["designation." . $fieldName] = $fieldValue;
				}
				break;
			case "product_manufacturer_id":
				$primaryId = $keyValue;
			case "product_manufacturer_code":
				if (empty($primaryId)) {
					$primaryId = getFieldFromId("product_manufacturer_id","product_manufacturers","product_manufacturer_code",strtoupper($keyValue));
				}
				$resultSet = executeQuery("select * from product_manufacturers join contacts using (contact_id) where product_manufacturer_id = ? and client_id = ?",$primaryId,$GLOBALS['gClientId']);
				if (!$dataRow = getNextRow($resultSet)) {
					$dataRow = array();
				}
				unset($dataRow['version']);
				unset($dataRow['client_id']);
				$dataRow['image_url'] = (empty($dataRow['image_id']) ? "" : getImageFilename($dataRow['image_id'],array("use_cdn"=>true)));
				foreach ($dataRow as $fieldName => $fieldValue) {
					self::massageContent($fieldValue);
					self::$iPlaceholderValues["product_manufacturer." . $fieldName] = $fieldValue;
				}
				break;
			case "ffl_license_number":
				$primaryId = (new FFL(array("license_number"=>$keyValue)))->getFieldData("federal_firearms_licensee_id");
			case "ffl_license_lookup":
				if (empty($primaryId)) {
					$primaryId = (new FFL(array("license_lookup"=>$keyValue)))->getFieldData("federal_firearms_licensee_id");
				}
				$dataRow = (new FFL($primaryId))->getFFLRow();
				unset($dataRow['version']);
				unset($dataRow['client_id']);
				foreach ($dataRow as $fieldName => $fieldValue) {
					self::massageContent($fieldValue);
					self::$iPlaceholderValues["ffl." . $fieldName] = $fieldValue;
				}
				break;
			case "event_id":
				$primaryId = getFieldFromId("event_id","events","event_id",$keyValue);
			case "event_code":
				if (empty($primaryId)) {
					$primaryId = getFieldFromId("event_id","events","event_code",strtoupper($keyValue));
				}
				$resultSet = executeQuery("select * from events where event_id = ? and client_id = ?",$primaryId,$GLOBALS['gClientId']);
				if ($dataRow = getNextRow($resultSet)) {
					$dataRow['class_instructor'] = (empty($dataRow['class_instructor_id']) ? "" : getDisplayName(getFieldFromId("contact_id","class_instructors","class_instructor_id",$dataRow['class_instructor_id'])));
				} else {
					$dataRow = array();
				}
				unset($dataRow['version']);
				unset($dataRow['client_id']);
				foreach ($dataRow as $fieldName => $fieldValue) {
					self::massageContent($fieldValue);
					self::$iPlaceholderValues["event." . $fieldName] = $fieldValue;
				}
				break;
		}
	}

	public static function massageContent($content,$additionalSubstitutions = array()) {
		if (self::$iPlaceholderValues === false || $GLOBALS['gClientId'] != self::$iClientId) {
			self::initializePlaceholderValues();
		}
        $placeHolderValues = self::$iPlaceholderValues;
        if(!empty($additionalSubstitutions) && is_array($additionalSubstitutions)) {
            foreach($additionalSubstitutions as $fieldName => $fieldValue) {
                $placeHolderValues[$fieldName] = $fieldValue;
            }
        }
		$newContent = "";
		$contentLines = getContentLines($content);
		$useLine = true;
		foreach ($contentLines as $thisLine) {
			if ($thisLine == "%endif%") {
				$useLine = true;
				continue;
			}
			if ($thisLine == "%else%") {
				$useLine = !$useLine;
				continue;
			}
			if (substr($thisLine, 0, strlen("%if_has_value:")) == "%if_has_value:") {
				$substitutionFieldName = strtolower(trim(substr($thisLine, strlen("%if_has_value:")),"%"));
				$useLine = !empty($placeHolderValues[$substitutionFieldName]);
				continue;
			}
			if (substr($thisLine, 0, strlen("%if_has_no_value:")) == "%if_has_no_value:") {
				$substitutionFieldName = strtolower(trim(substr($thisLine, strlen("%if_has_no_value:")),"%"));
				$useLine = empty($placeHolderValues[$substitutionFieldName]);
				continue;
			}
			if ($useLine) {
				$newContent .= $thisLine . "\n";
			}
		}
		$content = $newContent;

		$lines = getContentLines($content);
		$newLines = array();
		foreach ($lines as $thisLine) {
			if (startsWith($thisLine,"%key_value:")) {
				$parts = explode(":", trim(substr($thisLine, strlen("%key_value:")), "%"));
				self::setPlaceholderKeyValue($parts[0], $parts[1]);
				continue;
			}
			foreach ($placeHolderValues as $fieldName => $fieldValue) {
				$fieldName = strtolower($fieldName);
                $fieldValue = is_scalar($fieldValue) ? $fieldValue : "";
				$thisLine = str_ireplace("%" . $fieldName . "%", $fieldValue, $thisLine);
                $thisLine = str_replace("%hidden_if_empty:" . $fieldName . "%", (empty($fieldValue) ? "hidden" : ""), $thisLine);
				$thisLine = str_replace("%hidden_if_not_empty:" . $fieldName . "%", (empty($fieldValue) ? "" : "hidden"), $thisLine);
			}
			$newLines[] = $thisLine;
		}
		$content = implode("\n",$newLines);
		if (strpos($content, "%date:") !== false) {
			$offset = strpos($content, "%date:");
			while ($offset !== false) {
				$endOffset = strpos($content, "%", $offset + 5);
				if ($endOffset === false) {
					break;
				}
				$dateFormat = substr($content, $offset, ($endOffset - $offset + 1));
				$content = str_replace($dateFormat, date(substr($dateFormat, 6, -1)), $content);
				$offset = strpos($content, "%date:");
			}
		}
		return $content;
	}

	public static function getGlobalPlaceholders() {
		if (self::$iPlaceholderValues === false || $GLOBALS['gClientId'] != self::$iClientId) {
			self::initializePlaceholderValues();
		}
		return self::$iGlobalPlaceholders;
	}

	public static function initializePlaceholderValues() {
		self::$iClientId = $GLOBALS['gClientId'];
		self::$iPlaceholderValues = array("user.first_name" => $GLOBALS['gUserRow']['first_name'],
			"user.last_name" => $GLOBALS['gUserRow']['last_name'],
			"user.display_name" => $GLOBALS['gUserRow']['display_name'],
			"user.address_1" => $GLOBALS['gUserRow']['address_1'],
			"user.address_2" => $GLOBALS['gUserRow']['address_2'],
			"user.city" => $GLOBALS['gUserRow']['city'],
			"user.state" => $GLOBALS['gUserRow']['state'],
			"user.city_state" => $GLOBALS['gUserRow']['city'] . (!empty($GLOBALS['gUserRow']['city']) && !empty($GLOBALS['gUserRow']['state']) ? ", " : "") . $GLOBALS['gUserRow']['state'],
			"user.postal_code" => $GLOBALS['gUserRow']['postal_code'],
			"user.country_name" => $GLOBALS['gUserRow']['country_name'],
			"user.email_address" => $GLOBALS['gUserRow']['email_address'],
			"user.user_id" => $GLOBALS['gUserRow']['user_id'],
			"user.contact_id" => $GLOBALS['gUserRow']['contact_id'],
			"user.user_group_codes" => $GLOBALS['gUserRow']['user_group_codes'],
			"user.user_groups" => $GLOBALS['gUserRow']['user_groups'],
			"user.user_type_code" => $GLOBALS['gUserRow']['user_type']['user_type_code'],
			"user.user_type" => $GLOBALS['gUserRow']['user_type']['description'],
			"user.category_codes" => $GLOBALS['gUserRow']['category_codes'],
			"user.categories" => $GLOBALS['gUserRow']['categories'],
			"user.categories_json" => (empty($GLOBALS['gUserRow']['categories']) ? "" : jsonEncode(explode(",", $GLOBALS['gUserRow']['categories']))),
			"user.business_name" => $GLOBALS['gUserRow']['business_name'],
			"client.first_name" => $GLOBALS['gClientRow']['first_name'],
			"client.last_name" => $GLOBALS['gClientRow']['last_name'],
			"client.display_name" => $GLOBALS['gClientName'],
			"client.address_1" => $GLOBALS['gClientRow']['address_1'],
			"client.address_2" => $GLOBALS['gClientRow']['address_2'],
			"client.city" => $GLOBALS['gClientRow']['city'],
			"client.state" => $GLOBALS['gClientRow']['state'],
			"client.city_state" => $GLOBALS['gClientRow']['city'] . (!empty($GLOBALS['gClientRow']['city']) && !empty($GLOBALS['gClientRow']['state']) ? ", " : "") . $GLOBALS['gClientRow']['state'],
			"client.postal_code" => $GLOBALS['gClientRow']['postal_code'],
			"client.country_name" => $GLOBALS['gClientRow']['country_name'],
			"client.address_block" => $GLOBALS['gClientRow']['address_1'] . "<br>" . (empty($GLOBALS['gClientRow']['address_2']) ? "" : $GLOBALS['gClientRow']['address_2'] . "<br>") . $GLOBALS['gClientRow']['city'] . ", " . $GLOBALS['gClientRow']['state'] . " " . $GLOBALS['gClientRow']['postal_code'],
			"client.email_address" => $GLOBALS['gClientRow']['email_address'],
			"client.contact_id" => $GLOBALS['gClientRow']['contact_id'],
			"client.business_name" => $GLOBALS['gClientRow']['business_name'],
			"pageDescription" => (empty($GLOBALS['gPageRow']['meta_description']) ? $GLOBALS['gPageRow']['description'] : $GLOBALS['gPageRow']['meta_description']),
			"pageTitle" => $GLOBALS['gPageRow']['description'],
			"currentYear" => date("Y"),
			"currentDate" => date("m/d/Y"),
			"userDisplayName" => getUserDisplayName(array("include_company" => false)),
			"pageLinkUrl" => $GLOBALS['gLinkUrl'],
            "domain_name" => getDomainName(),
			"userImageFilename" => getImageFilename($GLOBALS['gUserRow']['image_id'],array("use_cdn"=>true,"default_image" => "/images/person.png")));
		self::$iGlobalPlaceholders = array_keys(self::$iPlaceholderValues);
		foreach ($GLOBALS['gClientRow']['phone_numbers'] as $row) {
			if (empty(self::$iPlaceholderValues['client.phone_number'])) {
				self::$iPlaceholderValues['client.phone_number'] = $row['phone_number'];
			}
			self::$iPlaceholderValues['client.phone_number_' . makeCode($row['description'],array("lowercase"=>true))] = $row['phone_number'];
		}

		if (function_exists("_localTemplateSubstitutions")) {
			$additionalSubstitutions = _localTemplateSubstitutions();
			if (is_array($additionalSubstitutions) && !empty($additionalSubstitutions)) {
				foreach ($additionalSubstitutions as $fieldName => $fieldValue) {
					self::$iPlaceholderValues[$fieldName] = $fieldValue;
				}
			}
		}

		if (function_exists("_localPageSubstitutions")) {
			$additionalSubstitutions = _localPageSubstitutions();
			if (is_array($additionalSubstitutions) && !empty($additionalSubstitutions)) {
				foreach ($additionalSubstitutions as $fieldName => $fieldValue) {
					self::$iPlaceholderValues[$fieldName] = $fieldValue;
				}
			}
		}
		$commaSeparatedProductCategoryCodes = strtolower(getFieldFromId("group_concat(product_category_code)","product_categories","client_id",$GLOBALS['gClientId'], "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0")));
		self::$iPlaceholderValues['comma_separated_product_category_codes'] = $commaSeparatedProductCategoryCodes;
		$commaSeparatedProductDepartmentCodes = strtolower(getFieldFromId("group_concat(product_department_code)","product_departments","client_id",$GLOBALS['gClientId'], "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0")));
		self::$iPlaceholderValues['comma_separated_product_department_codes'] = $commaSeparatedProductDepartmentCodes;
		$commaSeparatedProductCategoryGroupCodes = strtolower(getFieldFromId("group_concat(product_category_group_code)","product_category_groups","client_id",$GLOBALS['gClientId'], "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0")));
		self::$iPlaceholderValues['comma_separated_product_category_group_codes'] = $commaSeparatedProductCategoryGroupCodes;

		$customSet = executeQuery("select *, (select control_value from custom_field_controls where custom_field_id = custom_fields.custom_field_id and control_name = 'data_type' limit 1) data_type " .
			"from custom_fields left outer join custom_field_data on (custom_fields.custom_field_id = custom_field_data.custom_field_id and " .
			"custom_field_data.primary_identifier = ?) where custom_field_type_id in (select custom_field_type_id from custom_field_types where " .
			"custom_field_type_code = 'CONTACTS') and inactive = 0 and client_id = ?", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId']);
		while ($customRow = getNextRow($customSet)) {
			$fieldValue = "";
			if (!empty($customRow['custom_field_data_id'])) {
				$dataType = $customRow['data_type'];
				switch ($dataType) {
					case "date":
						$fieldValue = (empty($customRow['date_data']) ? "" : date("m/d/Y", strtotime($customRow['date_data'])));
						break;
					case "bigint":
					case "int":
						$fieldValue = $customRow['integer_data'];
						break;
					case "decimal":
						$fieldValue = number_format($customRow['number_data'], 2);
						break;
					case "tinyint":
						$fieldValue = ($customRow['text_data'] ? "Yes" : "No");
						break;
					default:
						$fieldValue = $customRow['text_data'];
						break;
				}
			}
			if (empty($fieldValue)) {
				if (self::$iCustomFieldDefaultValues === false) {
					self::$iCustomFieldDefaultValues = array();
					$resultSet = executeQuery("select * from custom_field_controls where custom_field_id in (select custom_field_id from custom_fields where client_id = ?) and control_name = 'default_value'",$GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						self::$iCustomFieldDefaultValues[$row['custom_field_id']] = $row['control_value'];
					}
				}
				$fieldValue = self::$iCustomFieldDefaultValues[$customRow['custom_field_id']];
			}
			self::$iPlaceholderValues["user_custom_field:" . $customRow['custom_field_code']] = $fieldValue;
		}

		$customSet = executeQuery("select * from contact_identifier_types left outer join contact_identifiers on (contact_identifier_types.contact_identifier_type_id = " .
			"contact_identifiers.contact_identifier_type_id and contact_identifiers.contact_id = ?) where inactive = 0 and client_id = ?", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId']);
		while ($customRow = getNextRow($customSet)) {
			$fieldValue = $customRow['identifier_value'];
			self::$iPlaceholderValues["contact_identifier:" . $customRow['contact_identifier_type_code']] = $fieldValue;
		}
	}
}
