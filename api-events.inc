<?php

# Events & Facilities Methods

switch ($_POST['action']) {
	case "get_visit_types":
		$visitTypes = array();
		$resultSet = executeQuery("select * from visit_types where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$visitTypes[] = $row;
		}
		$returnArray['visit_types'] = $visitTypes;
		break;
	case "get_visitor_log":
		if (empty($_POST['visit_date'])) {
			$_POST['visit_date'] = date("Y-m-d");
		}
		$visitorLog = array();
		$resultSet = executeQuery("select * from contacts join visitor_log using (contact_id) where date(visit_time) = ? and visitor_log.client_id = ?", makeDateParameter($_POST['visit_date']), $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$reservations = array();
			$reservationSet = executeQuery("select event_id,description from events where contact_id = ? and start_date = ?", $row['contact_id'], makeDateParameter($_POST['visit_date']));
			while ($reservationRow = getNextRow($reservationSet)) {
				$reservationRow['facilities'] = array();
				$facilitySet = executeQuery("select facility_id,(select description from facilities where facility_id = event_facilities.facility_id) description,min(hour),max(hour) from event_facilities where event_id = ? group by facility_id,description", $reservationRow['event_id']);
				while ($facilityRow = getNextRow($facilitySet)) {
					$reservationRow['facilities'][] = array("facility_id" => $facilityRow['facility_id'], "description" => $facilityRow['description'],
						"start_time" => Events::getDisplayTime($facilityRow['min(hour)']), "end_time" => Events::getDisplayTime($facilityRow['max(hour)'], true));
				}
				$reservations[] = $reservationRow;
			}
			$row['reservations'] = $reservations;
			$visitorLog[] = $row;
		}
		$returnArray['visitor_log'] = $visitorLog;
		break;
	case "add_visitor_log":
		$resultSet = executeQuery("select contact_id from contacts where client_id = ? and first_name = ? and last_name = ? and email_address = ?",
			$GLOBALS['gClientId'], $_POST['first_name'], $_POST['last_name'], $_POST['email_address']);
		if ($row = getNextRow($resultSet)) {
			$contactId = $row['contact_id'];
		} else {
			$contactDataTable = new DataTable("contacts");
			if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'],
				"email_address" => $_POST['email_address'])))) {
				$returnArray['error_message'] = $contactDataTable->getErrorMessage();
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				break;
			}
		}
		$visitTypeId = getFieldFromId("visit_type_id", "visit_types", "visit_type_id", $_POST['visit_type_id']);
		if (empty($visitTypeId) && !empty($_POST['visit_type_id'])) {
			$returnArray['error_message'] = "Invalid visit type";
			break;
		}
		$visitorLogId = getFieldFromId("visitor_log_id", "visitor_log", "contact_id", $contactId, "date(visit_time) = current_date and end_time is null");
		if (empty($visitorLogId)) {
			executeQuery("insert into visitor_log (client_id,contact_id,visit_type_id,notes) values (?,?,?,'from API')", $GLOBALS['gClientId'], $contactId, $visitTypeId);
		}
		break;
	case "get_facilities":
		$whereStatement = "client_id = ?";
		$parameters = array($GLOBALS['gClientId']);
		if (!empty($_POST['facility_type_id'])) {
			$whereStatement .= " and facility_type_id = ?";
			$parameters[] = $_POST['facility_type_id'];
		} else if (!empty($_POST['facility_type_code'])) {
			$parameters[] = $_POST['facility_type_code'];
			$whereStatement .= " and facility_type_id in (select facility_type_id from facility_types where facility_type_code = ?)";
		}
		if (!empty($_POST['facility_tag_id'])) {
			$whereStatement .= " and facility_id in (select facility_id from facility_tag_links where facility_tag_id = ?)";
			$parameters[] = $_POST['facility_tag_id'];
		} else if (!empty($_POST['facility_tag_code'])) {
			$whereStatement .= " and facility_id in (select facility_id from facility_tag_links where facility_tag_id in (select facility_tag_id from facility_tags where facility_tag_code = ?))";
			$parameters[] = $_POST['facility_tag_code'];
		}
		$facilities = array();
		$resultSet = executeReadQuery("select * from facilities where " . $whereStatement, $parameters);
		while ($row = getNextRow($resultSet)) {
			$row['facility_availability'] = array();
			$subtableSet = executeQuery("select * from facility_availability where facility_id = ?", $row['facility_id']);
			while ($subtableRow = getNextRow($subtableSet)) {
				$row['facility_availability'][] = $subtableRow;
			}
			$row['facility_closures'] = array();
			$subtableSet = executeQuery("select * from facility_closures where facility_id = ? and closure_date > date_sub(current_date,interval 1 day)", $row['facility_id']);
			while ($subtableRow = getNextRow($subtableSet)) {
				$row['facility_closures'][] = $subtableRow;
			}
			$facilities[] = $row;
		}
		$returnArray['facilities'] = $facilities;
		break;

	case "get_facility_reservations":
		$reservations = getCachedData("get_facility_reservations", jsonEncode($_POST));
		if (is_array($reservations)) {
			$returnArray['reservations'] = $reservations;
			break;
		}
		if (empty(strlen($_POST['military_time']))) {
			$parameters = array($GLOBALS['gClientId'], date("Y-m-d", strtotime($_POST['reservation_date'])));
			$whereStatement = "client_id = ? and date_needed = ?";
		} else {
			$militaryTime = str_replace(":", "", $_POST['military_time']);
			$hour = floor($militaryTime / 100);
			$minute = floor(($militaryTime - ($hour * 100)) / 15) * .25;
			$parameters = array($GLOBALS['gClientId'], date("Y-m-d", strtotime($_POST['reservation_date'])), $hour + $minute);
			$whereStatement = "client_id = ? and date_needed = ? and hour = ?";
		}
		if (!empty($_POST['facility_id'])) {
			$whereStatement .= " and event_facilities.facility_id = ?";
			$parameters[] = $_POST['facility_id'];
		}
		if (!empty($_POST['contact_id'])) {
			$whereStatement .= " and contacts.contact_id = ?";
			$parameters[] = $_POST['contact_id'];
		}

		$eventIds = array();
		$reservations = array();
		$contactRows = array();
		$facilityRows = array();
		$resultSet = executeReadQuery("select *,(select event_type_code from event_types where event_type_id = events.event_type_id) event_type_code," .
			"(select phone_number from phone_numbers where contact_id = events.contact_id limit 1) phone_number from events join event_facilities using (event_id) where " . $whereStatement . " order by hour", $parameters);
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists($row['event_id'], $eventIds)) {
				$eventIds[$row['event_id']] = array();
			}
			if (array_key_exists($row['facility_id'], $eventIds[$row['event_id']])) {
				continue;
			}
			if (array_key_exists($row['contact_id'], $contactRows)) {
				$contactRow = $contactRows[$row['contact_id']];
			} else {
				$contactRow = Contact::getContact($row['contact_id']);
				$contactRows[$row['contact_id']] = $contactRow;
			}
			if (array_key_exists($row['facility_id'], $facilityRows)) {
				$facilityRow = $facilityRows[$row['facility_id']];
			} else {
				$facilityRow = getRowFromId("facilities", "facility_id", $row['facility_id']);
				$facilityRows[$row['facility_id']] = $facilityRow;
			}
			$eventIds[$row['event_id']][$row['facility_id']] = $row['facility_id'];
			$thisEvent = array();
			$thisEvent['event_id'] = $row['event_id'];
			$thisEvent['event_type_id'] = $row['event_type_id'];
			$thisEvent['event_type_code'] = $row['event_type_code'];
			$thisEvent['facility_id'] = $facilityRow['facility_id'];
			$thisEvent['facility_description'] = $facilityRow['description'];
			$thisEvent['event_description'] = $row['description'];
			$thisEvent['detailed_description'] = $row['detailed_description'];
			$thisEvent['first_name'] = $contactRow['first_name'];
			$thisEvent['last_name'] = $contactRow['last_name'];
			$thisEvent['email_address'] = $contactRow['email_address'];
			$thisEvent['phone_number'] = $row['phone_number'];
			$hour = floor($row['hour']);
			$thisEvent['start_military_time'] = ($hour * 100) + (($row['hour'] - $hour) * 60);
			$thisEvent['start_date'] = date("m/d/Y", strtotime($row['start_date']));
			$endSet = executeReadQuery("select date_needed,max(hour) from event_facilities where event_id = ? and facility_id = ? group by date_needed order by date_needed desc", $row['event_id'], $row['facility_id']);
			if ($endRow = getNextRow($endSet)) {
				$thisEvent['end_date'] = date("m/d/Y", strtotime($endRow['date_needed']));
				$hour = floor($endRow['max(hour)'] + .25);
				$thisEvent['end_military_time'] = ($hour * 100) + (($endRow['max(hour)'] - $hour + .25) * 60);
			}
			$thisEvent['event_color'] = Events::getEventColor($row, $facilityRow);
			$reservations[] = $thisEvent;
		}
		setCachedData("get_facility_reservations", jsonEncode($_POST), $reservations, .025);
		setCachedData("get_facility_reservations", $_SERVER['HTTP_HOST'] . ":" . json_encode($_POST), $reservations, .025, true);
		$returnArray['reservations'] = $reservations;
		break;
	case "get_event_types":
		$parameters = array($GLOBALS['gClientId']);
		$eventTypes = array();
		$resultSet = executeQuery("select * from event_types where client_id = ?", $parameters);
		while ($row = getNextRow($resultSet)) {
			$eventTypes[] = $row;
		}
		$returnArray['event_types'] = $eventTypes;
		break;
	case "get_user_registrations":
		$userId = getFieldFromId("user_id", "users", "user_id", $_POST['user_id']);
		if (empty($userId)) {
			$userId = Contact::getContactUserId($_POST['contact_id']);
		}
		if (empty($userId)) {
			$returnArray['error_message'] = "Invalid User";
			break;
		}
		$returnArray['events'] = array();
		$resultSet = executeQuery("select * from event_registrants where contact_id = (select contact_id from users where user_id = ?)", $userId);
		while ($row = getNextRow($resultSet)) {
			$returnArray['events'] = $row;
		}
		break;
	case "get_events":
		$whereStatement = "client_id = ? and inactive = 0";
		$parameters = array($GLOBALS['gClientId']);
		$events = array();
		if (!empty($_POST['event_type_id'])) {
			$whereStatement .= " and event_type_id = ?";
			$parameters[] = $_POST['event_type_id'];
		}
		if (!empty($_POST['event_id'])) {
			$whereStatement .= " and event_id = ?";
			$parameters[] = $_POST['event_id'];
		}
		if (!empty($_POST['start_date'])) {
			$whereStatement .= " and start_date >= ?";
			$parameters[] = $_POST['start_date'];
		}
		if (!empty($_POST['end_date'])) {
			$whereStatement .= " and ((end_date is null and start_date <= ?) or (end_date is not null and end_date <= ?))";
			$parameters[] = $_POST['end_date'];
			$parameters[] = $_POST['end_date'];
		}
		if (!empty($_POST['facility_id'])) {
			$whereStatement .= " and (event_id in (select event_id from event from event_facilities where facility_id = ?) or " .
				"event_id in (select event_id from event_facility_recurrences where facility_id = ?))";
			$parameters[] = $_POST['facility_id'];
			$parameters[] = $_POST['facility_id'];
		}
		# Check location
		if (!empty($_POST['location_id'])) {
			$whereStatement .= " and location_id = ?";
			$parameters[] = $_POST['location_id'];
		} else if (!empty($_POST['location_code'])) {
			$whereStatement .= " and location_id in (select location_id from locations where location_code = ?)";
			$parameters[] = $_POST['location_code'];
		}
		$resultSet = executeQuery("select *,(select count(*) from event_registrants where event_id = events.event_id) registrant_count," .
			"(select description from event_types where event_type_id = events.event_type_id) event_type," .
			"(select description from locations where location_id = events.location_id) location," .
			"(select location_code from locations where location_id = events.location_id) location_code," .
			"(select group_concat(concat_ws('|',date_needed,hour)) from event_facilities where event_id = events.event_id) date_times from events where " . $whereStatement, $parameters);
		$productCatalog = new ProductCatalog();
		while ($row = getNextRow($resultSet)) {
			$dateTimes = explode(",",$row['date_times']);
			$firstDateNeeded = false;
			$lastDateNeeded = false;
			$firstHourNeeded = false;
			$lastHourNeeded = false;
			foreach ($dateTimes as $thisDateTime) {
				$parts = explode("|", $thisDateTime);
				if (empty($firstDateNeeded) || $parts[0] < $firstDateNeeded) {
					$firstDateNeeded = $parts[0];
				}
				if ($firstDateNeeded == $parts[0] && ($firstHourNeeded === false || $parts[1] < $firstHourNeeded)) {
					$firstHourNeeded = $parts[1];
				}
				if (empty($lastDateNeeded) || $parts[0] > $lastDateNeeded) {
					$lastDateNeeded = $parts[0];
				}
				if ($lastDateNeeded == $parts[0] && ($lastHourNeeded === false || $parts[1] > $lastHourNeeded)) {
					$lastHourNeeded = $parts[1];
				}
			}
			$row['first_date_needed'] = $firstDateNeeded;
			$row['last_date_needed'] = $lastDateNeeded;
			$row['first_hour_needed'] = $firstHourNeeded;
			$row['last_hour_needed'] = $lastHourNeeded;
			if (!empty($row['product_id']) && empty($_POST['exclude_sale_price'])) {
				$salePriceInfo = $productCatalog->getProductSalePrice($row['product_id']);
				$row['sale_price'] = number_format($salePriceInfo['sale_price'], 2, ".", "");
			}
			$row['display_first_time_needed'] = Events::getDisplayTime($row['first_hour_needed']);
			$row['display_last_time_needed'] = Events::getDisplayTime($row['last_hour_needed'], true);
			$events[] = $row;
		}
		$returnArray['events'] = $events;
		break;

	case "visitor_check_in":
		$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id']);
		if (empty($contactId)) {
			$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $_POST['contact_id']);
		}
		if (empty($contactId)) {
			$returnArray['error_message'] = "Invalid Contact";
			break;
		}
		$visitTypeId = getFieldFromId("visit_type_id", "visit_types", "visit_type_id", $_POST['visit_type_id']);
		$resultSet = executeQuery("select * from visitor_log where contact_id = ? and date(visit_time) = current_date and end_time is null", $contactId);
		if ($resultSet['row_count'] == 0) {
			executeQuery("insert into visitor_log (client_id,contact_id,visit_time,visit_type_id) values (?,?,now(),?)", $GLOBALS['gClientId'], $contactId, $visitTypeId);
		}
		break;

	case "reserve_facility":
		$startDate = $_POST['date_needed'];
		$endDate = $_POST['date_needed'];

		$resultSet = executeQuery("select * from facilities where facility_id = ? and client_id = ?", $_POST['facility_id'], $GLOBALS['gClientId']);
		if (!$facilityRow = getNextRow($resultSet)) {
			$returnArray['error_message'] = "Facility does not exist";
			break;
		}

		if (strpos(":", $_POST['reserve_start_time']) !== false) {
			$parts = explode(":", $_POST['reserve_start_time']);
			$hour = $parts[0];
			$minute = $parts[1];
			$time = floor(($hour + ($minute / 60)) * 4) / 4;
			$_POST['reserve_start_time'] = $time;
		} else if ($_POST['reserve_start_time'] > 50) {
			$hour = floor($_POST['reserve_start_time'] / 100);
			$minute = floor(($_POST['reserve_start_time'] - ($hour * 100)) / 60);
			$time = floor(($hour + ($minute / 60)) * 4) / 4;
			$_POST['reserve_start_time'] = $time;
		} else {
			if (strlen($_POST['reserve_start_time']) == 0 || $_POST['reserve_start_time'] < 0) {
				$_POST['reserve_start_time'] = 0;
			}
		}

		if (strpos(":", $_POST['reserve_end_time']) !== false) {
			$parts = explode(":", $_POST['reserve_end_time']);
			$hour = $parts[0];
			$minute = $parts[1];
			$time = floor(($hour + ($minute / 60)) * 4) / 4;
			$_POST['reserve_end_time'] = $time;
		} else if ($_POST['reserve_end_time'] > 50) {
			$hour = floor($_POST['reserve_end_time'] / 100);
			$minute = floor(($_POST['reserve_end_time'] - ($hour * 100)) / 60);
			$time = floor(($hour + ($minute / 60)) * 4) / 4;
			$_POST['reserve_end_time'] = $time;
		} else {
			if (strlen($_POST['reserve_end_time']) == 0 || $_POST['reserve_end_time'] > 23.75) {
				$_POST['reserve_end_time'] = 24;
			}
		}
		if ($_POST['reserve_end_time'] > 0) {
			$_POST['reserve_end_time'] -= .25;
		}

		$timeIncrement = $_POST['time_increment'];
		if ($timeIncrement == "hour" || $timeIncrement == 1) {
			$timeIncrement = 1;
		} else if ($timeIncrement == "half" || $timeIncrement == .5) {
			$timeIncrement = .5;
		} else {
			$timeIncrement = .25;
		}

		$hours = ($_POST['reserve_end_time'] - $_POST['reserve_start_time'] + $timeIncrement);
		$GLOBALS['gPrimaryDatabase']->startTransaction();
		$resultSet = executeQuery("select contact_id from contacts where client_id = ? and first_name = ? and last_name = ? and email_address = ? and " .
			"contact_id not in (select contact_id from users) and contact_id not in (select contact_id from orders) and contact_id not in (select contact_id from donations)",
			$GLOBALS['gClientId'], $_POST['first_name'], $_POST['last_name'], $_POST['email_address']);
		if ($row = getNextRow($resultSet)) {
			$contactId = $row['contact_id'];
		} else {
			$contactDataTable = new DataTable("contacts");
			if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'],
				"email_address" => $_POST['email_address'])))) {
				$returnArray['error_message'] = $contactDataTable->getErrorMessage();
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				break;
			}
		}
		if (!empty($_POST['phone_number'])) {
			$phoneNumber = formatPhoneNumber($_POST['phone_number']);
			$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "phone_number", $phoneNumber, "contact_id = ?", $contactId);
			if (empty($phoneNumberId)) {
				executeQuery("insert into phone_numbers (contact_id,phone_number) values (?,?)", $contactId, $phoneNumber);
			}
		}

		$contactRow = Contact::getContact($contactId);
		$facilityId = getFieldFromId("facility_id", "facilities", "facility_id", $_POST['facility_id'], "inactive = 0");
		if (empty($facilityId)) {
			$returnArray['error_message'] = "Invalid Facility";
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			break;
		}
		$facilityTypeId = getFieldFromId("facility_type_id", "facilities", "facility_id", $facilityId);
		$eventTypeId = getFieldFromId("event_type_id", "event_types", "event_type_id", $_POST['event_type_id']);
		if (empty($eventTypeId)) {
			$returnArray['error_message'] = "Invalid Event Type";
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			break;
		}
		$resultSet = executeQuery("insert into events (client_id,description,detailed_description,event_type_id,contact_id,start_date,end_date,user_id,date_created,attendees,tentative) values " .
			"(?,?,?,?,?,?,?,?,now(),?,?)", $GLOBALS['gClientId'], $_POST['description'], $_POST['detailed_description'], $eventTypeId, $contactId, makeDateParameter($startDate),
			makeDateParameter($endDate), $GLOBALS['gUserId'], (empty($_POST['number_people']) ? 1 : $_POST['number_people']), $facilityRow['requires_approval']);
		if (!empty($resultSet['sql_error'])) {
			$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			break;
		} else {
			$eventId = $resultSet['insert_id'];
		}
		for ($x = $_POST['reserve_start_time']; $x <= $_POST['reserve_end_time'] + $timeIncrement - .25; $x += .25) {
			$resultSet = executeQuery("insert into event_facilities (event_id,facility_id,date_needed,hour) values " .
				"(?,?,?,?)", $eventId, $facilityId, makeDateParameter($_POST['date_needed']), $x);
			if (!empty($resultSet['sql_error'])) {
				$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				ajaxResponse($returnArray);
				break;
			}
		}

		Events::sendEventNotifications($eventId);

		$GLOBALS['gPrimaryDatabase']->commitTransaction();
		break;

	case "delete_event":
		$eventId = getFieldFromId("event_id", "events", "event_id", $_POST['event_id']);
		if (empty($eventId)) {
			$returnArray['error_message'] = "Invalid Event";
			break;
		}
		$eventRegistrantId = getFieldFromId("event_registrant_id", "event_registrants", "event_id", $eventId);
		if (!empty($eventRegistrantId)) {
			$returnArray['error_message'] = "Event has registrants and cannot be deleted";
			break;
		}
		$GLOBALS['gPrimaryDatabase']->startTransaction();
		$subtables = array("event_facilities", "event_facility_recurrences", "event_requirement_data", "event_registration_products", "event_images", "event_registration_custom_fields");
		foreach ($subtables as $tableName) {
			$resultSet = executeQuery("delete from " . $tableName . " where event_id = ?", $eventId);
		}
		$dataTable = new DataTable("events");
		if (!$dataTable->deleteRecord(array("primary_id" => $eventId))) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$returnArray['error_message'] = $dataTable->getErrorMessage();
		} else {
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
		}
		break;

	case "register_contact":
		$contactId = "";
		if (empty($_POST['contact_id'])) {
			if (!empty($_POST['email_address']) && !empty($_POST['first_name']) && !empty($_POST['last_name'])) {
				$resultSet = executeQuery("select * from contacts where first_name = ? and last_name = ? and email_address = ? and client_id = ?",
					$_POST['first_name'], $_POST['last_name'], $_POST['email_address'], $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$contactId = $row['contact_id'];
				} else {
					$contactDataTable = new DataTable("contacts");
					if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'],
						"email_address" => $_POST['email_address'])))) {
						$returnArray['error_message'] = $contactDataTable->getErrorMessage();
						break;
					}
				}
			}
		} else {
			$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id'], "deleted = 0");
			if (empty($contactId)) {
				$contactId = getFieldFromId("contact_id", "contact_redirect", "retired_contact_identifier", $_POST['contact_id']);
			}
		}
		$eventId = getFieldFromId("event_id", "events", "event_id", $_POST['event_id'], "inactive = 0");
		if (empty($contactId)) {
			$returnArray['error_message'] = "Invalid Contact";
			break;
		}
		if (!empty($_POST['phone_number'])) {
			$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "phone_number", $_POST['phone_number'], "contact_id = ?", $contactId);
			if (empty($phoneNumberId)) {
				executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,?)", $contactId, $_POST['phone_number'], $_POST['description']);
			}
			if ($_POST['description'] == "text") {
				$customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "CONTACTS");
				$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "RECEIVE_SMS", "custom_field_type_id = ?", $customFieldTypeId);
				if (empty($customFieldId)) {
					$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
						$GLOBALS['gClientId'], "RECEIVE_SMS", "Receive Text Notifications", $customFieldTypeId, "Receive Text Notifications");
					$customFieldId = $insertSet['insert_id'];
					executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,?,?)", $customFieldId, "data_type", "tinyint");
				}
				CustomField::setCustomFieldData($contactId, "RECEIVE_SMS", 'true');
			}
		}
		if (empty($eventId)) {
			$returnArray['error_message'] = "Invalid Event";
			break;
		}
		$registrantCounts = Events::getAttendeeCounts($eventId);
		if ($registrantCounts['registrants'] >= $registrantCounts['attendees']) {
			$returnArray['error_message'] = "This event is already full";
			break;
		}
		$resultSet = executeQuery("insert into event_registrants (event_id,contact_id,registration_time) values (?,?,now())", $eventId, $contactId);
		if (!empty($resultSet['sql_error'])) {
			$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
		}
		$eventRow = getRowFromId("events", "event_id", $eventId);
		if (!empty($eventRow['product_id'])) {
			$attendeeCounts = Events::getAttendeeCounts($eventRow['event_id']);
			if ($attendeeCounts['registrants'] >= $attendeeCounts['attendees']) {
				executeQuery("update products set non_inventory_item = 0 where product_id = ?", $eventRow['product_id']);
				executeQuery("update product_inventories set quantity = 0 where product_id = ?", $eventRow['product_id']);
			} else {
				executeQuery("update products set non_inventory_item = 1 where product_id = ?", $eventRow['product_id']);
			}
		}
		if (empty($eventRow['email_id'])) {
			$eventRow['email_id'] = getFieldFromId("email_id", "event_type_location_emails", "event_type_id", $eventRow['event_type_id'], "location_id = ?", $eventRow['location_id']);
			if (empty($eventRow['email_id'])) {
				$eventRow['email_id'] = getFieldFromId("email_id", "event_types", "event_type_id", $eventRow['event_type_id']);
			}
		}
		if (!empty($eventRow['email_id'])) {
			$substitutions = Events::getEventRegistrationSubstitutions($eventRow, $contactId);
			sendEmail(array("email_id" => $eventRow['email_id'], "email_address" => $substitutions['email_address'], "substitutions" => $substitutions, "contact_id" => $contactId));
		}
		Events::notifyCRM($eventId);
		break;
	case "get_event_attendance_statuses":
		$eventAttendanceStatuses = array();
		$resultSet = executeQuery("select * from event_attendance_statuses where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$eventAttendanceStatuses[] = $row;
		}
		$returnArray['event_attendance_statuses'] = $eventAttendanceStatuses;
		break;
	case "get_certification_types":
		$certificationTypes = array();
		$resultSet = executeQuery("select * from certification_types where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$certificationTypes[] = $row;
		}
		$returnArray['certification_types'] = $certificationTypes;
		break;
	case "get_contact_events":
		$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id']);
		if (empty($contactId)) {
			$returnArray['error_message'] = "Invalid Contact";
			break;
		}
		$resultSet = executeQuery("select * from events join event_registrants using (event_id) where event_registrants.contact_id = ?", $contactId);
		$returnArray['contact_events'] = array();
		while ($row = getNextRow($resultSet)) {
			$returnArray['contact_events'][] = $row;
		}
		break;
}
