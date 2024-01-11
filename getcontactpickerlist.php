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

$GLOBALS['gPageCode'] = "GETCONTACTPICKERLIST";
require_once "shared/startup.inc";

$returnArray = array();
if (!empty($_GET['contact_id'])) {
	$resultSet = executeQuery("select contact_id,address_1,state,city,email_address from contacts where deleted = 0 and client_id = ? " .
		"and contact_id = ? or contact_id in (select contact_id from contact_redirect where retired_contact_identifier = ? and client_id = ?)",
		$GLOBALS['gClientId'],$_GET['contact_id'],$_GET['contact_id'],$GLOBALS['gClientId']);
	$contactInfo = array();
	if ($row = getNextRow($resultSet)) {
		$description = getDisplayName($row['contact_id'],array("include_company"=>true));
		if (!empty($row['address_1'])) {
			if (!empty($description)) {
				$description .= " • ";
			}
			$description .= $row['address_1'];
		}
		if (!empty($row['state'])) {
			if (!empty($row['city'])) {
				$row['city'] .= ", ";
			}
			$row['city'] .= $row['state'];
		}
		if (!empty($row['city'])) {
			if (!empty($description)) {
				$description .= " • ";
			}
			$description .= $row['city'];
		}
		if (!empty($row['email_address'])) {
			if (!empty($description)) {
				$description .= " • ";
			}
			$description .= $row['email_address'];
		}
		$contactInfo = array("description"=>$description,"contact_id"=>$row['contact_id']);
		$returnArray['contact_info'] = $contactInfo;
	}
	ajaxResponse($returnArray);
}
$filterTextParts = explode(" ",$_POST['contact_picker_filter_text']);
$fields = array("first_name","last_name","business_name","address_1","city","email_address","postal_code");
$whereStatement = "";
$whereParameters = array($GLOBALS['gClientId']);
if ($GLOBALS['gLoggedIn'] && !$GLOBALS['gUserRow']['administrator_flag']) {
	$whereParameters[] = $GLOBALS['gUserId'];
}

if (!empty($_POST['contact_picker_filter_text'])) {
	$whereSegment = "";
	foreach ($filterTextParts as $textPart) {
		if (strlen($textPart) > 1) {
			$thisWherePart = "";
			foreach ($fields as $fieldName) {
				if (!empty($thisWherePart)) {
					$thisWherePart .= " or ";
				}
				$thisWherePart .= $fieldName . " like ?";
				$whereParameters[] = $textPart . "%";
			}
			if (!empty($whereSegment)) {
				$whereSegment .= " and ";
			}
			if (!empty($thisWherePart)) {
				$whereSegment .= "(" . $thisWherePart . ")";
			}
		}
	}
	if (!empty($whereSegment)) {
		$whereStatement .= (empty($whereStatement) ? "" : " or ") . "(" . $whereSegment . ")";
	}

	$phoneNumber = str_replace("-","",$_POST['contact_picker_filter_text']);
	$phoneNumber = str_replace(" ","",$phoneNumber);
	$phoneNumber = str_replace("(","",$phoneNumber);
	$phoneNumber = str_replace(")","",$phoneNumber);
	$phoneNumber = str_replace("+","",$phoneNumber);
	if (!empty($phoneNumber) && is_numeric($phoneNumber)) {
		$whereStatement .= (empty($whereStatement) ? "" : " or ") . "contact_id in (select contact_id from phone_numbers where phone_number = ?)";
		$whereParameters[] = formatPhoneNumber($phoneNumber);
	}

	$whereSegment = "(business_name like ?";
	$whereParameters[] = $_POST['contact_picker_filter_text'] . "%";
	$whereSegment .= " or address_1 like ?";
	$whereParameters[] = $_POST['contact_picker_filter_text'] . "%";
	$lastName = array_pop($filterTextParts);
	$firstName = implode(" ",$filterTextParts);
	$whereSegment .= " or (first_name like ?";
	$whereParameters[] = $firstName . "%";
	$whereSegment .= " and last_name like ?)";
	$whereParameters[] = $lastName . "%";
	$whereSegment .= ")";
	$whereStatement .= (empty($whereStatement) ? "" : " or ") . $whereSegment;
}
$pagePreferences = Page::getPagePreferences("GETCONTACTPICKERLIST");
$pagePreferences['contact_picker_contact_type_id'] = $_POST['contact_picker_contact_type_id'];
Page::setPagePreferences($pagePreferences,"GETCONTACTPICKERLIST");
if (!empty($_POST['contact_picker_contact_type_id'])) {
	$whereStatement .= (empty($whereStatement) ? "" : " and ") . "contact_type_id = ?";
	$whereParameters[] = $_POST['contact_picker_contact_type_id'];
}

$resultSet = executeQuery("select contact_id,address_1,state,city,email_address from contacts where deleted = 0 and client_id = ? " .
	(empty($_POST['_contact_picker_filter_where']) ? "" : "and " . $_POST['_contact_picker_filter_where']) .
	($GLOBALS['gLoggedIn'] && !$GLOBALS['gUserRow']['administrator_flag'] ? " and responsible_user_id = ?" : "") .
	(empty($whereStatement) ? "" : " and (" . $whereStatement . ")") . " order by date_created desc limit 50",$whereParameters);
$contactList = array();
while ($row = getNextRow($resultSet)) {
	$description = getDisplayName($row['contact_id'],array("include_company"=>true));
	if (!empty($row['address_1'])) {
		if (!empty($description)) {
			$description .= " • ";
		}
		$description .= $row['address_1'];
	}
	if (!empty($row['state'])) {
		if (!empty($row['city'])) {
			$row['city'] .= ", ";
		}
		$row['city'] .= $row['state'];
	}
	if (!empty($row['city'])) {
		if (!empty($description)) {
			$description .= " • ";
		}
		$description .= $row['city'];
	}
	if (!empty($row['email_address'])) {
		if (!empty($description)) {
			$description .= " • ";
		}
		$description .= $row['email_address'];
	}
	$contactList[] = array("description"=>$description,"contact_id"=>$row['contact_id']);
}
$returnArray['contacts'] = $contactList;
echo jsonEncode($returnArray);
