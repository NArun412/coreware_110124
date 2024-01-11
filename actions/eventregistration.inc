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
		$returnArray['error_message'] = "Contact is required to register for the event";
		ajaxResponse($returnArray);
	}

	$nameValues['event_id'] = getFieldFromId("event_id","events","event_id",$nameValues['event_id'], "start_date > current_date");
	$requiredFields = array('event_id');
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

	$resultSet = executeQuery("insert into event_registrants (event_id,contact_id,registration_time) values (?,?,now())", $nameValues['event_id'], $contactId);
	if (!empty($resultSet['sql_error'])) {
		$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
		$returnArray['error_message'] = "Unable to create registration";
		ajaxResponse($returnArray);
	}
	return true;
}
