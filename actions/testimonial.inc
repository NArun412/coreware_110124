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
	$fullName = $nameValues['full_name'];
	if (empty($fullName)) {
		$fullName = getUserDisplayName();
	}
	if (empty($fullName)) {
		$fullName = getDisplayName($contactId);
	}
	$jobTitle = $nameValues['job_title'];
	$imageId = $nameValues['image_id'];
	$content = $nameValues['content'];
	$testimonialTagId = getFieldFromId("testimonial_tag_id","testimonial_tags","testimonial_tag_id",$nameValues['testimonial_tag_id']);
	if (empty($testimonialTagId)) {
		$testimonialTagId = getFieldFromId("testimonial_tag_id", "testimonial_tags", "testimonial_tag_code", $nameValues['testimonial_tag_code']);
	}
	if (empty($fullName) || empty($content)) {
		$missingFields = array();
		if (empty($fullName)) {
			$missingFields[] = "Full Name";
		}
		if (empty($content)) {
			$missingFields[] = "Content";
		}
		$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
		$returnArray['error_message'] = "Some required information is missing. Required field(s) missing: " . implode(",", $missingFields) . " \n"
			. ($GLOBALS['gUserRow']['superuser_flag'] ? jsonEncode($nameValues) : "");
		ajaxResponse($returnArray);
	}
	executeQuery("insert into testimonials (client_id,full_name,job_title,image_id,content,testimonial_tag_id,internal_use_only) values (?,?,?,?,?, ?,1)",
		$GLOBALS['gClientId'],$fullName,$jobTitle,$imageId,$content,$testimonialTagId);
	return true;
}
