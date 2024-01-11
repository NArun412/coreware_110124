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

function afterSaveGenerateForm($formId,$contactId,$postFields) {
	$nameValues = array();
	foreach ($postFields as $fieldName => $thisField) {
		$nameValues[$fieldName] = $thisField['value'];
	}
	$nameValues['contact_id'] = $contactId;
	if (empty($nameValues['contact_id'])) {
		$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
		$returnArray['error_message'] = "User login is required";
		ajaxResponse($returnArray);
	}

	$emailAddress = $nameValues['email_address'] ?: $nameValues['email'];
	if(empty($emailAddress)) {
	    $emailAddress = getFieldFromId('email_address', 'contacts', 'contact_id', $contactId);
    }
    $helpDeskIgnoreId = getFieldFromId("help_desk_ignore_email_address_id","help_desk_ignore_email_addresses","email_address",$emailAddress);
    if (!empty($helpDeskIgnoreId)) {
        addProgramLog("Help desk form submitted with email in ignore list: " . $emailAddress . "\n\n" . jsonEncode($nameValues));
        return true;
    }

    if (!array_key_exists("help_desk_type_id",$nameValues) || empty($nameValues['help_desk_type_id'])) {
		$nameValues['help_desk_type_id'] = getFieldFromId("help_desk_type_id","help_desk_types","help_desk_type_code",$nameValues['help_desk_type_code']);
	}

	if (!array_key_exists("help_desk_type_id",$nameValues) || empty($nameValues['help_desk_type_id'])) {
		$resultSet = executeQuery("select * from form_fields where client_id = ? and form_field_id in (select form_field_id " .
			"from form_field_controls where control_name = 'proxy_field' and control_value = 'help_desk_type_id')",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (!empty($nameValues[strtolower($row['form_field_code'])])) {
				$nameValues['help_desk_type_id'] = $nameValues[strtolower($row['form_field_code'])];
				break;
			}
		}
	}

	if (!array_key_exists("help_desk_type_id",$nameValues) || empty($nameValues['help_desk_type_id'])) {
		$resultSet = executeQuery("select * from form_fields where client_id = ? and form_field_id in (select form_field_id " .
			"from form_field_controls where control_name = 'proxy_field' and control_value = 'help_desk_type_code')",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (!empty($nameValues[strtolower($row['form_field_code'])])) {
				$nameValues['help_desk_type_id'] = getFieldFromId("help_desk_type_id","help_desk_types","help_desk_type_code",$nameValues[strtolower($row['form_field_code'])]);
				break;
			}
		}
	}

	if (!array_key_exists("help_desk_category_id",$nameValues) || empty($nameValues['help_desk_category_id'])) {
		$nameValues['help_desk_category_id'] = getFieldFromId("help_desk_category_id","help_desk_categories","help_desk_category_code",$nameValues['help_desk_category_code']);
	}

	if (!array_key_exists("help_desk_category_id",$nameValues) || empty($nameValues['help_desk_category_id'])) {
		$resultSet = executeQuery("select * from form_fields where client_id = ? and form_field_id in (select form_field_id " .
			"from form_field_controls where control_name = 'proxy_field' and control_value = 'help_desk_category_id')",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (!empty($nameValues[strtolower($row['form_field_code'])])) {
				$nameValues['help_desk_category_id'] = $nameValues[strtolower($row['form_field_code'])];
				break;
			}
		}
	}

	if (!array_key_exists("help_desk_category_id",$nameValues) || empty($nameValues['help_desk_category_id'])) {
		$resultSet = executeQuery("select * from form_fields where client_id = ? and form_field_id in (select form_field_id " .
			"from form_field_controls where control_name = 'proxy_field' and control_value = 'help_desk_category_code')",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (!empty($nameValues[strtolower($row['form_field_code'])])) {
				$nameValues['help_desk_category_id'] = getFieldFromId("help_desk_category_id","help_desk_categories","help_desk_category_code",$nameValues[strtolower($row['form_field_code'])]);
				break;
			}
		}
	}

	$nameValues['help_desk_type_id'] = getFieldFromId("help_desk_type_id","help_desk_types","help_desk_type_id",$nameValues['help_desk_type_id']);
	$requiredFields = array('content', 'description', 'help_desk_type_id');
	$missingFields = array();
	foreach($requiredFields as $thisField) {
	    if(empty($nameValues[$thisField])) {
	        $missingFields[] = $thisField;
        }
    }
	if(count($missingFields) > 0) {
		$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
		$returnArray['error_message'] = "Some required information is missing. Required field(s) missing: " . implode(",",$missingFields) . " \n"
            . ($GLOBALS['gUserRow']['superuser_flag'] ? jsonEncode($nameValues) : "");
		ajaxResponse($returnArray);
	}

	$resultSet = executeQuery("select * from form_fields where client_id = ?",$GLOBALS['gClientId']);
	while ($row = getNextRow($resultSet)) {
		if (array_key_exists(strtolower($row['form_field_code']),$nameValues) && !empty($row['custom_field_id'])) {
			$customFieldId = getFieldFromId("custom_field_id","custom_fields","custom_field_id",$row['custom_field_id'],"custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'HELP_DESK')");
			if (!empty($customFieldId) && !array_key_exists("custom_field_id_" . $customFieldId,$nameValues)) {
				$nameValues['custom_field_id_' . $customFieldId] = $nameValues[strtolower($row['form_field_code'])];
			}
		}
	}
	$helpDeskEntry = new HelpDesk();
	$helpDeskEntry->addSubmittedData($nameValues);
	if (!$helpDeskEntry->save()) {
		return $helpDeskEntry->getErrorMessage();
	}
	$helpDeskEntry->addFiles();
	return true;
}
