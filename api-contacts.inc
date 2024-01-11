<?php

# Contact API Methods

switch ($_POST['action']) {
	case "delete_user":
		$userId = getFieldFromId("user_id", "users", "user_id", $_POST['user_id'], "inactive = 0");
		if (empty($userId)) {
			$returnArray['error_message'] = "Invalid User";
			break;
		}
		$userTable = new DataTable("users");
		$userTable->saveRecord(array("name_values" => array("inactive" => "1"), "primary_id" => $userId));
		break;
	case "expunge_user":
		$userId = getFieldFromId("user_id", "users", "user_id", $_POST['user_id'], "inactive = 0");
		if (empty($userId)) {
			$returnArray['error_message'] = "Invalid User";
			break;
		}
		$userTable = new DataTable("users");
		$userTable->saveRecord(array("name_values" => array("inactive" => "1", "user_name" => $userId . "_" . getRandomString(10)), "primary_id" => $userId));
		$contactId = Contact::getUserContactId($userId);
		$contactTable = new DataTable("contacts");
		$contactTable->saveRecord(array("name_values" => array("email_address" => "", "address_1" => "", "address_2" => "", "city" => "", "state" => "", "postal_code" => ""), "primary_id" => $userId));
		break;
	case "last_form_date":
		$query = "select form_id,form_definition_id,date_created from forms where contact_id = ?";
		$parameters = array($_POST['contact_id']);
		if (!empty($_POST['form_definition_code'])) {
			$query .= " and form_definition_id in (select form_definition_id from form_definitions where client_id = ? and form_definition_code like ?)";
			$parameters[] = $GLOBALS['gClientId'];
			$parameters[] = $_POST['form_definition_code'];
		}
		if (!empty($_POST['form_definition_group_code'])) {
			$query .= " and form_definition_id in (select form_definition_id from form_definition_group_links where form_definition_group_id in (select form_definition_group_id from form_definition_groups where form_definition_group_code = ? and client_id = ?))";
			$parameters[] = $_POST['form_definition_group_code'];
			$parameters[] = $GLOBALS['gClientId'];
		}
		$query .= " order by date_created desc,form_id desc";
		$resultSet = executeQuery($query, $parameters);
		if ($row = getNextRow($resultSet)) {
			$expirationDays = getFieldFromId("expiration_days", "form_definitions", "form_definition_id", $row['form_definition_id']);
			if (empty($expirationDays)) {
				$returnArray['expiration_date'] = "";
			} else {
				$returnArray['expiration_date'] = date("m/d/Y", strtotime($row['date_created'] . " + " . $expirationDays . " days"));
			}
			$returnArray['date_created'] = $row['date_created'];
			$returnArray['form_attachments'] = array();
			$fileSet = executeQuery("select description,file_id from form_attachments where form_id = ?", $row['form_id']);
			$domainName = getDomainName();
			while ($fileRow = getNextRow($fileSet)) {
				$fileRow['file_url'] = $domainName . "/download.php?id=" . $fileRow['file_id'];
				$returnArray['form_attachments'][] = $fileRow;
			}
		} else {
			$returnArray['date_created'] = "";
		}
		setCachedData("last_form_date_" . $_POST['contact_id'], $_POST['form_definition_code'] . ":" . $_POST['form_definition_group_code'], $returnArray, 168, true);
		break;
	case "get_form_data":
		$contactId = false;
		if (!empty($_POST['contact_id'])) {
			$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id']);
			if (empty($contactId)) {
				$returnArray['error_message'] = "Invalid Contact";
			}
		}
		if (empty($_POST['previous_minutes']) || !is_numeric($_POST['previous_minutes'])) {
			if (empty($contactId)) {
				$_POST['previous_minutes'] = 60;
			} else {
				$_POST['previous_minutes'] = false;
			}
		}
		$resultSet = executeQuery("select * from contacts join forms using (contact_id) where " . (empty($contactId) ? "" : " contacts.contact_id = " . $contactId . " and ") .
			"form_definition_id in (select form_definition_id from form_definitions where client_id = ? and form_definition_code like ?)" .
			(empty($_POST['previous_minutes']) ? "" : " and time_submitted > date_sub(current_time,interval " . $_POST['previous_minutes'] . " minute) ") .
			" order by forms.date_created desc", $GLOBALS['gClientId'], $_POST['form_definition_code']);
		$returnArray['forms'] = array();
		if ($row = getNextRow($resultSet)) {
			$expirationDays = getFieldFromId("expiration_days", "form_definitions", "form_definition_id", $row['form_definition_id']);
			if (empty($expirationDays)) {
				$row['expiration_date'] = "";
			} else {
				$row['expiration_date'] = date("m/d/Y", strtotime($row['date_created'] . " + " . $expirationDays . " days"));
			}

			$row['form_attachments'] = array();
			$fileSet = executeQuery("select description,file_id from form_attachments where form_id = ?", $row['form_id']);
			$domainName = getDomainName();
			while ($fileRow = getNextRow($fileSet)) {
				$fileRow['file_url'] = $domainName . "/download.php?id=" . $fileRow['file_id'];
				$row['form_attachments'][] = $fileRow;
			}

			$fieldArray = array();
			$dataSet = executeQuery("select * from form_data join form_fields using (form_field_id) where form_id = ? and " .
				"form_field_id not in (select form_field_id from form_field_controls where control_name = 'data_type' and control_value = 'signature')", $row['form_id']);
			$dataArray = array();
			while ($dataRow = getNextRow($dataSet)) {
				if (!array_key_exists($dataRow['form_field_code'], $fieldArray)) {
					$fieldArray[$dataRow['form_field_code']] = array("form_field_id" => $dataRow['form_field_id'], "description" => $dataRow['description']);
				}
				$dataArray[$dataRow['form_field_id']] = $dataRow;
			}
			$dataValues = array();
			foreach ($fieldArray as $formFieldCode => $formFieldInfo) {
				if (!array_key_exists($formFieldInfo['form_field_id'], $dataArray)) {
					continue;
				}
				$dataRow = $dataArray[$formFieldInfo['form_field_id']];
				if (!empty($dataRow['integer_data'])) {
					$fieldValue = $dataRow['integer_data'];
				} else if (!empty($dataRow['number_data'])) {
					$fieldValue = $dataRow['number_data'];
				} else if (!empty($dataRow['date_data'])) {
					$fieldValue = $dataRow['date_data'];
				} else if (!empty($dataRow['image_id'])) {
					$fieldValue = $dataRow['image_id'];
				} else if (!empty($dataRow['file_id'])) {
					$fieldValue = $dataRow['file_id'];
				} else {
					$fieldValue = $dataRow['text_data'];
				}
				$dataType = getFieldFromId("control_value", "form_field_controls", "form_field_id", $formFieldInfo['form_field_id'], "control_name = 'data_type'");
				switch ($dataType) {
					case "radio":
					case "select":
						$thisColumn = new DataColumn($formFieldInfo['form_field_code']);
						$choicesControlValue = getFieldFromId("control_value", "form_field_controls", "form_field_id", $formFieldInfo['form_field_id'], "control_name = 'choices'");
						$thisColumn->setControlValue("choices", $choicesControlValue);
						$choices = $thisColumn->getChoices(false, true);
						$choiceSet = executeQuery("select * from form_field_choices where form_field_id = (select form_field_id from form_fields where form_field_code = ? and client_id = ?)", $thisColumn->getControlValue('column_name'), $GLOBALS['gClientId']);
						while ($choiceRow = getNextRow($choiceSet)) {
							$choices[$choiceRow['key_value']] = array("key_value" => $choiceRow['key_value'], "description" => $choiceRow['description']);
						}
						if (!empty($choices)) {
							$fieldValue = $choices[$fieldValue]['description'];
						}
						break;
				}
				$dataValues[$formFieldCode] = $fieldValue;
			}
			$row['data_values'] = $dataValues;
			$returnArray['forms'] = $row;
		}
		break;

	case "get_contact_identifier_types":
		$contactIdentifierTypes = array();
		$resultSet = executeQuery("select * from contact_identifier_types where inactive = 0 and client_id = ? order by sort_order,description",
			$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$contactIdentifierTypes[$row['contact_identifier_type_id']] = $row['description'];
		}
		$returnArray['contact_identifier_types'] = $contactIdentifierTypes;
		break;

	case "get_contact_custom_fields":
		$contactCustomFields = array();
		$resultSet = executeQuery("select * from custom_fields where inactive = 0 and client_id = ? and custom_field_type_id = " .
			"(select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS') order by sort_order,description",
			$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$contactCustomFields[$row['custom_field_id']] = $row;
		}
		$returnArray['contact_custom_fields'] = $contactCustomFields;
		break;

    case "update_contact":
        $contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id']);
        if(empty($contactId) && !empty($_POST['user_name'])) {
            $contactId = getFieldFromId("contact_id", "users", "contact_id", $_POST['user_name']);
        }
        if(empty($contactId) && !empty($_POST['email_address'])) {
            $emailCount = getFieldFromId("count(*)", "contacts", "email_address", $_POST['email_address'], "client_id = ?", $GLOBALS['gClientId']);
            if($emailCount == 1) {
                $contactId = getFieldFromId("contact_id", "contacts", "email_address", $_POST['email_address']);
            } else {
                $returnArray['error_message'] = "Email Address can not be matched to a single contact.";
                break;
            }
        }
        if(empty($contactId)) {
            $returnArray['error_message'] = "Contact not found.";
            break;
        }
        $contactRow = getRowFromId("contacts", "contact_id", $contactId);
        $_POST = array_merge($contactRow, $_POST);
        // intentional fall-through
    case "create_contact":
		if (empty($_POST['country_id'])) {
			$_POST['country_id'] = 1000;
		}
		if (empty($_POST['contact_type_id']) && !empty($_POST['contact_type_code'])) {
			$_POST['contact_type_id'] = getFieldFromId("contact_type_id", "contact_types", "contact_type_code", $_POST['contact_type_code']);
		}
		if ($_POST['address_1'] == $_POST['address_2']) {
			$_POST['address_2'] = "";
		}
		if (empty($_POST['first_name']) && empty($_POST['last_name']) && empty($_POST['business_name']) && empty($_POST['email_address']) && empty($_POST['address_1']) && empty($_POST['city'])) {
			$returnArray['error_message'] = "No contact information sent";
			break;
		}
		$contactDataTable = new DataTable("contacts");
        if(empty($contactId)) {
            if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("title" => $_POST['title'], "first_name" => $_POST['first_name'], "middle_name" => $_POST['middle_name'],
                "last_name" => $_POST['last_name'], "suffix" => $_POST['suffix'], "preferred_first_name" => $_POST['preferred_first_name'], "alternate_name" => $_POST['alternate_name'],
                "business_name" => $_POST['business_name'], "address_1" => $_POST['address_1'], "address_2" => $_POST['address_2'], "city" => $_POST['city'], "state" => $_POST['state'],
                "postal_code" => $_POST['postal_code'], "email_address" => $_POST['email_address'], "country_id" => $_POST['country_id'], "contact_type_id" => $_POST['contact_type_id'],
                "source_id" => $_POST['source_id'])))) {
                $returnArray['error_message'] = getSystemMessage("cannot_create_contact", "Error Creating Contact");
                break;
            }
        } else {
            $contactDataTable->setSaveOnlyPresent(true);
            if (!$contactDataTable->saveRecord(array("primary_id"=>$contactId, "name_values" => array("title" => $_POST['title'], "first_name" => $_POST['first_name'], "middle_name" => $_POST['middle_name'],
                "last_name" => $_POST['last_name'], "suffix" => $_POST['suffix'], "preferred_first_name" => $_POST['preferred_first_name'], "alternate_name" => $_POST['alternate_name'],
                "business_name" => $_POST['business_name'], "address_1" => $_POST['address_1'], "address_2" => $_POST['address_2'], "city" => $_POST['city'], "state" => $_POST['state'],
                "postal_code" => $_POST['postal_code'], "email_address" => $_POST['email_address'], "country_id" => $_POST['country_id'], "contact_type_id" => $_POST['contact_type_id'],
                "source_id" => $_POST['source_id'])))) {
                $returnArray['error_message'] = getSystemMessage("cannot_create_contact", "Error Creating Contact");
                break;
            }
        }
		$returnArray['contact_id'] = $contactId;
		$phoneNumbers = $_POST['phone_number'];
		if (!is_array($phoneNumbers)) {
			$phoneNumbers = array();
		}
		if (!empty($_POST['phone_number'])) {
			$phoneNumbers[] = array($_POST['phone_number'], $_POST['description']);
		}
        $phoneNumberTable = new DataTable('phone_numbers');
		foreach ($phoneNumbers as $thisPhoneNumber) {
			if (is_array($thisPhoneNumber)) {
				if (array_key_exists("phone_number", $thisPhoneNumber)) {
					$phoneNumber = $thisPhoneNumber['phone_number'];
					$phoneDescription = $thisPhoneNumber['description'];
				} else {
					$phoneNumber = $thisPhoneNumber[0];
					$phoneDescription = $thisPhoneNumber[1];
				}
			} else {
				$phoneNumber = $thisPhoneNumber;
				$phoneDescription = "";
			}
			if (!empty($phoneNumber)) {
				if ($_POST['country_id'] == 1000 || $_POST['country_id'] == 1001) {
					$phoneNumber = formatPhoneNumber($phoneNumber);
				}
                $phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "contact_id", $contactId, "phone_number = ?", $phoneNumber);
                $parameters = array("name_values"=>array( "contact_id"=>$contactId,"phone_number"=>$phoneNumber,"description"=>$phoneDescription));
                if(!empty($phoneNumberId)) {
                    $parameters['primary_id'] = $phoneNumberId;
                }
                $phoneNumberTable->saveRecord($parameters);
			}
		}
		$_POST['search_text'] = "";
		$_POST['contact_id'] = $contactId;

		foreach ($_POST as $fieldName => $fieldValue) {
			if (empty($fieldValue)) {
				continue;
			}
			if (startsWith($fieldName, "contact_identifier-")) {
				$contactIdentifierTypeCode = substr($fieldName, strlen("contact_identifier-"));
				$contactIdentifierTypeId = getFieldFromId("contact_identifier_type_id", "contact_identifier_types", "contact_identifier_type_code", $contactIdentifierTypeCode);
				if (empty($contactIdentifierTypeId)) {
					continue;
				}
				$contactIdentifierId = getFieldFromId("contact_identifier_id", "contact_identifiers", "contact_id", $contactId, "contact_identifier_type_id = ?", $contactIdentifierTypeId);
				$dataTable = new DataTable("contact_identifier");
				$dataTable->saveRecord(array("name_values" => array("contact_id" => $contactId, "contact_identifier_type_id" => $contactIdentifierTypeId, "identifier_value" => $fieldValue), "primary_id" => $contactIdentifierId));
			}
			if (startsWith($fieldName, "custom_field-")) {
				$customFieldCode = substr($fieldName, strlen("custom_field-"));
				$customFieldId = CustomField::getCustomFieldIdFromCode($customFieldCode);
				if (empty($customFieldId)) {
					continue;
				}
				CustomField::setCustomFieldData($contactId, $customFieldCode, $fieldValue);
			}
		}
        // intentional fall-through
	case "get_contacts":
		$searchFields = array("first_name", "last_name", "business_name", "address_1", "city", "email_address", "user_name");
		$whereStatement = "";
		$parameters = array($GLOBALS['gClientId']);
		if (!empty($_POST['search_parameters']) && is_array($_POST['search_parameters'])) {
			foreach ($_POST['search_parameters'] as $searchFieldName => $searchText) {
				if (!in_array($searchFieldName, $searchFields)) {
					continue;
				}
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . $searchFieldName . " like ?";
				$parameters[] = $searchText . "%";
			}
			if (empty($whereStatement)) {
				$returnArray['error_message'] = "No valid search fields supplied in search_parameters";
				break;
			}
		} else {
			$searchText = $_POST['search_text'];
			$fieldName = $_POST['field_name'];
			if (!in_array($fieldName, $searchFields) && !in_array($fieldName, array("phone_number", "contact_identifier"))) {
				$fieldName = "";
			}
			if (!empty($_POST['contact_id'])) {
				$whereStatement = "contacts.contact_id = ?";
				$parameters[] = $_POST['contact_id'];
			} else {
				if (empty($fieldName)) {
					foreach ($searchFields as $searchFieldName) {
						$whereStatement .= (empty($whereStatement) ? "" : " or ") . $searchFieldName . " like ?";
						$parameters[] = $searchText . "%";
					}
					$whereStatement .= (empty($whereStatement) ? "" : " or ") . "contacts.contact_id in (select contact_id from contact_identifiers where identifier_value like ?)";
					$parameters[] = $searchText . "%";

					$phoneSearchText = "";
					for ($x = 0; $x < strlen($searchText); $x++) {
						if (is_numeric(substr($searchText, $x, 1))) {
							$phoneSearchText .= substr($searchText, $x, 1);
						}
					}
					if (!empty($phoneSearchText) && is_numeric($phoneSearchText) && (strlen($phoneSearchText) == 10 || (strlen($phoneSearchText) == 11 && substr($phoneSearchText, 0, 1) == "1"))) {
						$whereStatement .= (empty($whereStatement) ? "" : " or ") . "contacts.contact_id in (select contact_id from phone_numbers where phone_number = ?)";
						$parameters[] = formatPhoneNumber($phoneSearchText);
					}
				} else {
					if ($fieldName == "phone_number") {
						$searchText = formatPhoneNumber($searchText);
						$whereStatement .= (empty($whereStatement) ? "" : " or ") . "contacts.contact_id in (select contact_id from phone_numbers where phone_number = ?)";
						$parameters[] = $searchText;
					} elseif ($fieldName == "contact_identifier") {
						$whereStatement .= (empty($whereStatement) ? "" : " or ") . "contacts.contact_id in (select contact_id from contact_identifiers where identifier_value like ?)";
						$parameters[] = $searchText . "%";
					} else {
						$whereStatement .= (empty($whereStatement) ? "" : " or ") . $fieldName . " like ?";
						$parameters[] = $searchText . "%";
					}
				}
			}
		}
        $excludeWhere = "";
        // todo: add parameters to exclude locations, ffls and manufacturers by default
		$customFieldIds = getCachedData("custom_field_ids","contacts");
		if (!is_array($customFieldIds)) {
			$customFieldIds = array();
			$resultSet = executeQuery("select * from custom_fields where client_id = ? and inactive = 0 and custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS')", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$customFieldIds[$row['custom_field_id']] = $row['custom_field_code'];
			}
			setCachedData("custom_field_ids","contacts",$customFieldIds,24);
		}
		$contactArray = array();
        if ($GLOBALS['gUserRow']['full_client_access']) {
            $limit = is_numeric($_POST['limit']) ? min((int)$_POST['limit'], 500) : 20;
            $offset = is_numeric($_POST['offset']) ? (int)$_POST['offset'] : 0;
        } else {
            $limit = 20;
            $offset = 0;
        }
		$resultSet = executeQuery("select *,(select group_concat(category_code) from contact_categories join categories " .
			"using (category_id) where contact_id = contacts.contact_id) category_codes from contacts " . ($_POST['users_only'] ? "" : "left outer ") . "join users using (contact_id) where deleted = 0 and " .
			"contacts.client_id = ?" . (empty($excludeWhere) ? "" : " and (" . $excludeWhere . ")") . (empty($whereStatement) ? "" : " and (" . $whereStatement . ")") . " limit " . $limit . " offset " . $offset, $parameters);
		while ($row = getNextRow($resultSet)) {
			$row['full_name'] = getDisplayName($row['contact_id'],array("contact_row"=>$row));
			$row['country_name'] = getFieldFromId("country_name", "countries", "country_id", $row['country_id']);
			$row['phone_numbers'] = array();
			$phoneSet = executeQuery("select * from phone_numbers where contact_id = ?", $row['contact_id']);
			while ($phoneRow = getNextRow($phoneSet)) {
				$row['phone_numbers'][] = $phoneRow;
			}
			if (empty($row['user_type_id'])) {
				$row['user_type'] = array();
			} else {
				$row['user_type'] = getRowFromId("user_types", "user_type_id", $row['user_type_id']);
			}

			# Get custom field data

			foreach ($customFieldIds as $customFieldId => $customFieldCode) {
				$customField = CustomField::getCustomFieldByCode($customFieldCode);
				$row['custom_field-' . $customFieldCode] = CustomField::getCustomFieldData($row['contact_id'], $customFieldCode);
				$row['custom_field-' . $customFieldCode . "-data_type"] = $customField->getColumnControl("data_type");
			}

			$contactSubscriptions = array();
			$subscriptionSet = executeQuery("select contact_subscriptions.*,subscriptions.subscription_code,subscriptions.description," .
				"subscriptions.display_color,subscriptions.sort_order,subscriptions.units_subscription,subscriptions.notify_days,subscriptions.maximum_retries," .
				"subscriptions.user_group_id,subscriptions.internal_use_only from contact_subscriptions join subscriptions using (subscription_id) where (contact_id " .
				"in (select related_contact_id from relationships where relationship_type_id in (select relationship_type_id from relationship_types where " .
				"relationship_type_code = 'SHARES_MEMBERSHIP') and contact_id = ?) or contact_id " .
				"in (select contact_id from relationships where relationship_type_id in (select relationship_type_id from relationship_types where " .
				"relationship_type_code = 'SHARES_MEMBERSHIP') and related_contact_id = ?)) or contact_id = ?", $row['contact_id'], $row['contact_id'], $row['contact_id']);
			while ($subscriptionRow = getNextRow($subscriptionSet)) {
				$contactSubscriptions[] = $subscriptionRow;
			}
			$row['contact_subscriptions'] = $contactSubscriptions;

			# get contact identifiers

			$contactIdentifiers = array();
			$identifierSet = executeQuery("select contact_identifier_type_code,description,identifier_value,required,internal_use_only,inactive from contact_identifiers join contact_identifier_types using (contact_identifier_type_id) where contact_id = ?", $row['contact_id']);
			while ($identifierRow = getNextRow($identifierSet)) {
				$contactIdentifiers[] = $identifierRow;
			}
			$row['contact_identifiers'] = $contactIdentifiers;

			$contactFiles = array();
			$fileSet = executeQuery("select description,file_id from contact_files where contact_id = ?" . (empty($_POST['file_type']) ? "" : " and file_id in (select file_id from files where extension = " . makeParameter($_POST['file_type']) . ")"), $row['contact_id']);
			$domainName = getDomainName();
			while ($fileRow = getNextRow($fileSet)) {
				$fileRow['file_url'] = $domainName . "/download.php?id=" . $fileRow['file_id'];
				$contactFiles[] = $fileRow;
			}
			$row['contact_files'] = $contactFiles;

			$contactCertifications = array();
			$certificationSet = executeQuery("select * from contact_certifications where contact_id = ?", $row['contact_id']);
			while ($certificationRow = getNextRow($certificationSet)) {
				$contactCertifications[] = $certificationRow;
			}
			$row['contact_certifications'] = $contactCertifications;

			$contactArray[] = $row;
		}
		ksort($_GET);
		ksort($_POST);
		$returnArray['contacts'] = $contactArray;
		break;

	case "import_contacts":
		if (!array_key_exists("csv_file", $_FILES)) {
			$returnArray['error_message'] = "No File uploaded";
			break;
		}

		$fieldValue = file_get_contents($_FILES['csv_file']['tmp_name']);
		$hashCode = md5($fieldValue);
		$csvImportId = getFieldFromId("csv_import_id", "csv_imports", "hash_code", $hashCode);
		if (!empty($csvImportId)) {
			$returnArray['error_message'] = "This file has already been imported . ";
			break;
		}
		$openFile = fopen($_FILES['csv_file']['tmp_name'], "r");

		$allValidFields = array("old_contact_id", "contact_id", "title", "first_name", "middle_name", "last_name", "suffix", "salutation", "business_name", "address_1", "address_2",
			"city", "state", "postal_code", "country", "email_address", "web_page", "contact_type_code", "birthdate", "notes", "category_codes", "mailing_list_codes",
			"phone_numbers", "phone_descriptions", "user_name", "password", "user_type_code", "administrator_flag", "source_code", "date_created", "deleted");
		$resultSet = executeQuery("select * from custom_fields where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'CONTACTS') and inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$allValidFields[] = "custom_field-" . strtolower($row['custom_field_code']);
		}

		$fieldNames = array();
		$importRecords = array();
		$count = 0;
		$errorMessage = "";
		while ($csvData = fgetcsv($openFile)) {
			if (empty($csvData)) {
				continue;
			}
			if ($count == 0) {
				foreach ($csvData as $thisName) {
					$fieldNames[] = makeCode(trim($thisName), array("lowercase" => true, "allow_dash" => true));
				}
				$invalidFields = "";
				foreach ($fieldNames as $fieldName) {
					if (!in_array($fieldName, $allValidFields)) {
						$invalidFields .= (empty($invalidFields) ? "" : ", ") . $fieldName;
					}
				}
				if (!empty($invalidFields)) {
					$errorMessage .= " < p>Invalid fields in CSV: " . $invalidFields . "\n";
				}
			} else {
				$fieldData = array();
				$dataFound = false;
				foreach ($csvData as $index => $thisData) {
					$thisFieldName = $fieldNames[$index];
					$fieldData[$thisFieldName] = trim($thisData);
					if ($thisFieldName != "country" && !empty($fieldData[$thisFieldName])) {
						$dataFound = true;
					}
				}
				if ($dataFound) {
					$importRecords[] = $fieldData;
				}
			}
			$count++;
		}
		fclose($openFile);
		$contactTypes = array();
		$sourceCodes = array();
		$userTypes = array();
		$categories = array();
		$mailingLists = array();
		foreach ($importRecords as $index => $thisRecord) {
			if (empty($thisRecord)) {
				continue;
			}
			if (empty($thisRecord['country']) || $thisRecord['country'] == "USA") {
				$countryId = 1000;
			} else {
				$countryId = getFieldFromId("country_id", "countries", "country_name", $thisRecord['country']);
				if (empty($countryId)) {
					$countryId = getFieldFromId("country_id", "countries", "country_code", $thisRecord['country']);
				}
			}
			if (empty($countryId)) {
				$errorMessage .= "Invalid Country: " . $thisRecord['country'] . "\n";
			}
			$importRecords[$index]['country_id'] = $countryId;
			if (!empty($thisRecord['category_codes'])) {
				$thisRecordCategories = explode("|", strtoupper($thisRecord['category_codes']));
				foreach ($thisRecordCategories as $thisCategory) {
					$thisCategory = trim($thisCategory);
					if (!array_key_exists($thisCategory, $categories) && !empty($thisCategory)) {
						$categories[$thisCategory] = "";
					}
				}
			}
			if (!empty($thisRecord['mailing_list_codes'])) {
				$thisRecordMailingListCodes = explode("|", strtoupper($thisRecord['mailing_list_codes']));
				foreach ($thisRecordMailingListCodes as $thisMailingListCode) {
					$thisMailingListCode = trim($thisMailingListCode);
					if (!array_key_exists($thisMailingListCode, $mailingLists) && !empty($thisMailingListCode)) {
						$mailingLists[$thisMailingListCode] = "";
					}
				}
			}
			if (!empty($thisRecord['contact_type_code'])) {
				if (!array_key_exists(strtoupper($thisRecord['contact_type_code']), $contactTypes)) {
					$contactTypes[strtoupper($thisRecord['contact_type_code'])] = "";
				}
			}
			if (!empty($thisRecord['source_code'])) {
				if (!array_key_exists(strtoupper($thisRecord['source_code']), $sourceCodes)) {
					$sourceCodes[strtoupper($thisRecord['source_code'])] = "";
				}
			}
			if (!empty($thisRecord['user_type_code'])) {
				if (!array_key_exists(strtoupper($thisRecord['user_type_code']), $userTypes)) {
					$userTypes[strtoupper($thisRecord['user_type_code'])] = "";
				}
			}
			if (!empty($thisRecord['user_name'])) {
				$importRecords[$index]['user_name'] = $thisRecord['user_name'] = makeCode($thisRecord['user_name'], array("lowercase" => true));
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $thisRecord['contact_id']);
				if (empty($contactId) && !empty($thisRecord['contact_id'])) {
					$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['contact_id']);
				}
				if (empty($contactId) && !empty($thisRecord['old_contact_id'])) {
					$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['old_contact_id']);
				}
				if (!empty($contactId)) {
					$userName = getFieldFromId("user_name", "users", "contact_id", $contactId);
					if (!empty($userName) && $userName != $thisRecord['user_name']) {
						$errorMessage .= "Line " . ($index + 2) . ": Contact already has a user with different user name\n";
					}
				}
				$userId = getFieldFromId("user_id", "users", "user_name", $thisRecord['user_name'], "contact_id <> ?", $contactId);
				if (!empty($userId)) {
					$errorMessage .= "Line " . ($index + 2) . ": User name already exists\n";
				}
				if (empty($thisRecord['password'])) {
					$errorMessage .= "Line " . ($index + 2) . ": User name given, but no password\n";
				}
			}
		}
		foreach ($contactTypes as $thisType => $contactTypeId) {
			$contactTypeId = getFieldFromId("contact_type_id", "contact_types", "contact_type_code", makeCode($thisType));
			if (empty($contactTypeId)) {
				$contactTypeId = getFieldFromId("contact_type_id", "contact_types", "description", $thisType);
			}
			if (empty($contactTypeId)) {
				$errorMessage .= "Invalid Contact Type: " . $thisType . "\n";
			} else {
				$contactTypes[$thisType] = $contactTypeId;
			}
		}
		foreach ($sourceCodes as $thisCode => $sourceId) {
			$sourceId = getFieldFromId("source_id", "sources", "source_code", makeCode($thisCode));
			if (empty($sourceId)) {
				$sourceId = getFieldFromId("source_id", "sources", "description", $thisCode);
			}
			if (empty($sourceId)) {
				$errorMessage .= "Invalid Source Code: " . $thisCode . "\n";
			} else {
				$sourceCodes[$thisCode] = $sourceId;
			}
		}
		foreach ($userTypes as $thisType => $userTypeId) {
			$userTypeId = getFieldFromId("user_type_id", "user_types", "user_type_code", makeCode($thisType));
			if (empty($userTypeId)) {
				$userTypeId = getFieldFromId("user_type_id", "user_types", "description", $thisType);
			}
			if (empty($userTypeId)) {
				$errorMessage .= "Invalid User Type: " . $thisType . "\n";
			} else {
				$userTypes[$thisType] = $userTypeId;
			}
		}
		foreach ($categories as $thisCategory => $categoryId) {
			$categoryId = getFieldFromId("category_id", "categories", "category_code", makeCode($thisCategory));
			if (empty($categoryId)) {
				$categoryId = getFieldFromId("category_id", "categories", "description", $thisCategory);
			}
			if (empty($categoryId)) {
				$errorMessage .= "Invalid Category: " . $thisCategory . "\n";
			} else {
				$categories[$thisCategory] = $categoryId;
			}
		}
		foreach ($mailingLists as $thisMailingList => $mailingListId) {
			$mailingListId = getFieldFromId("mailing_list_id", "mailing_lists", "mailing_list_code", makeCode($thisMailingList));
			if (empty($mailingListId)) {
				$mailingListId = getFieldFromId("mailing_list_id", "mailing_lists", "description", $thisMailingList);
			}
			if (empty($mailingListId)) {
				$errorMessage .= "Invalid Mailing List: " . $mailingListId . "\n";
			} else {
				$mailingLists[$thisMailingList] = $mailingListId;
			}
		}
		if (!empty($errorMessage)) {
			$returnArray['error_message'] = $errorMessage;
			break;
		}

		if (empty($_POST['description'])) {
			$_POST['description'] = "Contacts import at " . date("m / d / Y g:ia");
		}
		$GLOBALS['gPrimaryDatabase']->startTransaction();
		$resultSet = executeQuery("insert into csv_imports(client_id, description, table_name, hash_code, time_submitted, user_id, content) values(?,?,'contacts',?,now(),?,?)",
			$GLOBALS['gClientId'], "API Import", $hashCode, $GLOBALS['gUserId'], file_get_contents($_FILES['csv_file']['tmp_name']));
		if (!empty($resultSet['sql_error'])) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
		}
		$csvImportId = $resultSet['insert_id'];

		$insertCount = 0;
		$updateCount = 0;
		foreach ($importRecords as $index => $thisRecord) {
			if (empty($thisRecord)) {
				continue;
			}
			if (!array_key_exists("business_name", $thisRecord) && empty($thisRecord['first_name']) && !empty($thisRecord['last_name'])) {
				$thisRecord['business_name'] = $thisRecord['last_name'];
				$thisRecord['last_name'] = "";
			}
			$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $thisRecord['contact_id']);
			if (empty($contactId) && !empty($thisRecord['contact_id'])) {
				$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['contact_id']);
			}
			if (empty($contactId) && !empty($thisRecord['old_contact_id'])) {
				$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $thisRecord['old_contact_id']);
			}
			if (empty($contactId) && !empty($thisRecord['email_address'])) {
				$contactId = getFieldFromId("contact_id", "contacts", "email_address", $thisRecord['email_address']);
			}
			if (empty($contactId) && !empty($thisRecord['first_name']) && !empty($thisRecord['last_name']) && !empty($thisRecord['postal_code'])) {
				$contactId = getFieldFromId("contact_id", "contacts", "last_name", $thisRecord['last_name'], "first_name = ? and postal_code = ? and contact_id not in (select contact_id from donations)", $thisRecord['first_name'], $thisRecord['postal_code']);
			}

			if (empty($contactId)) {
				$contactDataTable = new DataTable("contacts");
				if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("title" => $thisRecord['title'], "first_name" => $thisRecord['first_name'], "middle_name" => $thisRecord['middle_name'],
					"last_name" => $thisRecord['last_name'], "suffix" => $thisRecord['suffix'], "salutation" => $thisRecord['salutation'], "preferred_first_name" => $thisRecord['preferred_first_name'], "alternate_name" => $thisRecord['alternate_name'],
					"business_name" => $thisRecord['business_name'], "address_1" => $thisRecord['address_1'], "address_2" => $thisRecord['address_2'], "city" => $thisRecord['city'], "state" => $thisRecord['state'],
					"postal_code" => $thisRecord['postal_code'], "email_address" => $thisRecord['email_address'], "web_page" => $thisRecord['web_page'], "country_id" => $thisRecord['country_id'],
					"contact_type_id" => $contactTypes[strtoupper($thisRecord['contact_type_code'])], "birthdate" => makeDateParameter($thisRecord['birthdate']),
					"notes" => $thisRecord['notes'], "source_id" => $sourceCodes[strtoupper($thisRecord['source_code'])], "date_created" => (empty($thisRecord['date_created']) ? date("Y-m-d") : date("Y-m-d", strtotime($thisRecord['date_created']))),
					"deleted" => ($thisRecord['deleted'] == "false" || empty($thisRecord['deleted']) ? "0" : "1"))))) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'] . ": " . jsonEncode($thisRecord);
					break 2;
				}
				$insertCount++;
			} else {
				$nameValues = array();
				$updateFields = array("title", "first_name", "middle_name", "last_name", "suffix", "salutation", "business_name", "address_1", "address_2",
					"city", "state", "postal_code", "country", "email_address", "web_page", "contact_type_code", "birthdate", "notes");
				foreach ($updateFields as $fieldName) {
					if (!empty($thisRecord[$fieldName])) {
						$nameValues[$fieldName] = $thisRecord[$fieldName];
					}
				}
				$dataTable = new DataTable("contacts");
				$dataTable->setPrimaryId($contactId);
				if (!$dataTable->saveRecord(array("name_values" => $nameValues))) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $returnArray['import_error'] = $dataTable->getErrorMessage();
					break 2;
				}
				$updateCount++;
			}
			if (!empty($thisRecord['user_name'])) {
				$userName = getFieldFromId("user_name", "users", "contact_id", $contactId);
				if (!empty($userName) && $userName != $thisRecord['user_name']) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $returnArray['import_error'] = "Line " . ($index + 2) . ": Contact already has a user with different user name\n";
					break 2;
				}
				$thisRecord['user_name'] = makeCode($thisRecord['user_name'], array("lowercase" => true));
				$userId = getFieldFromId("user_id", "users", "user_name", $thisRecord['user_name'], "contact_id <> ? and (client_id = ? or superuser_flag = 1)", $contactId, $GLOBALS['gClientId']);
				if (!empty($userId)) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $returnArray['import_error'] = "Line " . ($index + 2) . ": User name already exists\n";
					break 2;
				}
				$passwordSalt = getRandomString(64);
				$usersTable = new DataTable("users");
				if (!$userId = $usersTable->saveRecord(array("name_values" => array("client_id" => $GLOBALS['gClientId'], "contact_id" => $contactId, "user_name" => strtolower($thisRecord['user_name']),
					"password_salt" => $passwordSalt, "password" => $passwordSalt, "user_type_id" => $userTypes[strtoupper($thisRecord['user_type_code'])], "force_password_change" => "1", "administrator_flag" => (empty($thisRecord['administrator_flag']) ? "0" : "1"),
					"date_created" => date("Y-m-d H:i:s"))))) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $returnArray['import_error'] = $usersTable->getErrorMessage();
					break 2;
				}
				$resultSet = executeQuery("update users set password = ? where user_id = ?", hash("sha256", $userId . $passwordSalt . $thisRecord['password']), $userId);
				if (!empty($resultSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $returnArray['import_error'] = getSystemMessage("error_creating_user", "Error creating user");
					break 2;
				}
			}
			if (!empty($thisRecord['old_contact_id'])) {
				$contactRedirectId = getFieldFromId("contact_redirect_id", "contact_redirect", "contact_id", $contactId, "retired_contact_identifier = ?", $thisRecord['old_contact_id']);
				if (empty($contactRedirectId)) {
					$insertSet = executeQuery("insert into contact_redirect(client_id, contact_id, retired_contact_identifier) values(?,?,?)", $GLOBALS['gClientId'], $contactId, $thisRecord['old_contact_id']);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
						break 2;
					}
				}
			}
			$thisRecordCategories = explode("|", strtoupper($thisRecord['category_codes']));
			foreach ($thisRecordCategories as $thisCategory) {
				$thisCategory = trim($thisCategory);
				if (!empty($thisCategory)) {
					$categoryId = $categories[$thisCategory];
					$contactCategoryId = getFieldFromId("contact_category_id", "contact_categories", "contact_id", $contactId, "category_id = ?", $categoryId);
					if (empty($contactCategoryId)) {
						$insertSet = executeQuery("insert into contact_categories(contact_id, category_id) values(?,?)", $contactId, $categoryId);
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
							break 3;
						}
					}
				}
			}
			$thisRecordMailingLists = explode("|", strtoupper($thisRecord['mailing_list_codes']));
			foreach ($thisRecordMailingLists as $thisMailingList) {
				$thisMailinglist = trim($thisMailingList);
				if (!empty($thisMailingList)) {
					$mailingListId = $mailingLists[$thisMailingList];
					$contactMailingListId = getFieldFromId("contact_mailing_list_id", "contact_mailing_lists", "contact_id", $contactId, "mailing_list_id = ?", $mailingListId);
					if (empty($contactMailingListId)) {
						$insertSet = executeQuery("insert into contact_mailing_lists(contact_id, mailing_list_id, date_opted_in) values(?,?,current_date)", $contactId, $mailingListId);
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
							break 3;
						}
					}
				}
			}
			if (!empty($thisRecord['phone_numbers'])) {
				$phoneNumbers = explode("|", $thisRecord['phone_numbers']);
				$phoneDescriptions = explode("|", $thisRecord['phone_descriptions']);
				foreach ($phoneNumbers as $phoneIndex => $thisPhoneNumber) {
					if ($thisRecord['country_id'] <= 1001) {
						$thisPhoneNumber = formatPhoneNumber($thisPhoneNumber);
					}
					if (!empty($thisPhoneNumber)) {
						$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "contact_id", $contactId, "phone_number = ?", $thisPhoneNumber);
						if (empty($phoneNumberId)) {
							executeQuery("insert into phone_numbers(contact_id, phone_number, description) values(?,?,?)", $contactId, $thisPhoneNumber, $phoneDescriptions[$phoneIndex]);
						}
					}
				}
			}
			foreach ($fieldNames as $thisFieldName) {
				if (empty($thisRecord[$thisFieldName])) {
					continue;
				}
				if (startsWith($thisFieldName, "custom_field-")) {
					$customFieldCode = strtoupper(substr($thisFieldName, strlen("custom_field-")));
					$customFieldId = CustomField::getCustomFieldIdFromCode($customFieldCode);
					if (empty($customFieldId)) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = "Invalid Custom Field";
						break 3;
					}
					CustomField::setCustomFieldData($contactId, $customFieldCode, $thisRecord[$thisFieldName]);
				}
			}

			$insertSet = executeQuery("insert into csv_import_details(csv_import_id, primary_identifier) values(?,?)", $csvImportId, $contactId);
			if (!empty($insertSet['sql_error'])) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'];
				break 2;
			}
		}

		$GLOBALS['gPrimaryDatabase']->commitTransaction();

		$returnArray['response'] = $insertCount . " contacts imported . \n";
		$returnArray['response'] .= $updateCount . " contacts updated . \n";
		break;
}
