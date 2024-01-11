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

class Events {

	private static $iEventColors = false;

	public static function getAttendeeCounts($eventId) {
		$combinedEventId = getFieldFromId("combined_event_id", "combined_event_links", "event_id", $eventId);
		if (empty($combinedEventId)) {
			$attendeeLimit = getFieldFromId("attendees", "events", "event_id", $eventId);
			$registrants = 0;
			$resultSet = executeQuery("select count(*) from event_registrants where event_id = ?", $eventId);
			if ($row = getNextRow($resultSet)) {
				$registrants = $row['count(*)'];
				if (empty($registrants)) {
					$registrants = 0;
				}
			}
		} else {
			$attendeeLimit = getFieldFromId("attendees", "combined_events", "combined_event_id", $combinedEventId);
			$registrants = 0;
			$resultSet = executeQuery("select count(*) from event_registrants where event_id in (select event_id from combined_event_links where combined_event_id = ?)", $combinedEventId);
			if ($row = getNextRow($resultSet)) {
				$registrants = $row['count(*)'];
				if (empty($registrants)) {
					$registrants = 0;
				}
			}
		}
		return array("attendees" => $attendeeLimit, "registrants" => $registrants);
	}

    public static function generateEventCertificates($parameters) {
        $contactRow = $parameters['contact_row'] ?: Contact::getContact($parameters['contact_id']);
        if(empty($contactRow) || !is_array($contactRow)) {
            return false;
        }
        $eventAttendanceStatuses = $parameters['event_attendance_status_rows'];
        if(empty($eventAttendanceStatuses) || !is_array($eventAttendanceStatuses)) {
            $resultSet = executeQuery("select * from event_attendance_statuses where client_id = ?", $GLOBALS['gClientId']);
            while($row = getNextRow($resultSet)) {
                $eventAttendanceStatuses[$row['event_attendance_status_id']] = $row;
            }
        }
        $returnArray = array();
        $resultSet = executeQuery("select event_registrants.*,start_date,event_type_id from event_registrants join events using (event_id) 
                                    where event_attendance_status_id is not null and event_registrants.contact_id = ?", $contactRow['contact_id']);
        while ($row = getNextRow($resultSet)) {
            $contactEventTypeId = getFieldFromId("contact_event_type_id", "contact_event_types", "event_type_id", $row['event_type_id'],
                "contact_id = ? and date_completed >= ? and failed = ?", $row['contact_id'], $row['start_date'],
                $eventAttendanceStatuses[$row['event_attendance_status_id']]['incomplete']);
            if (empty($contactEventTypeId) || $parameters['regenerate']) {
                $fileId = $row['file_id'];
                if(!empty($fileId) && $parameters['regenerate']) {
                    executeQuery("update event_registrants set file_id = null where event_registrant_id = ?", $row['event_registrant_id']);
                    executeQuery("update contact_event_types set file_id = null where contact_event_type_id = ?", $contactEventTypeId);
                    executeQuery("delete ignore from files where file_id = ?", $fileId);
                    $fileId = false;
                }
                if (empty($fileId)) {
                    $fileId = Events::generateSingleEventCertificate(["event_registrant_row" => $row,
                        "event_attendance_status_rows" => $eventAttendanceStatuses, "send_email" => false]);
                        executeQuery("update event_registrants set file_id = ? where event_registrant_id = ?", $fileId, $row['event_registrant_id']);
                }
                if(empty($contactEventTypeId)) {
                    executeQuery("insert into contact_event_types (contact_id,event_type_id,date_completed,file_id,failed) values (?,?,?,?,?)",
                        $row['contact_id'], $row['event_type_id'], $row['start_date'], $fileId, $eventAttendanceStatuses[$row['event_attendance_status_id']]['incomplete']);
                } else {
                    executeQuery("update contact_event_types set file_id = ? where contact_event_type_id = ?", $fileId,$contactEventTypeId);
                }
                Events::createCertifications($row['contact_id']);
                $returnArray[$row['event_registrant_id']] = $fileId;
            }
        }
        return $returnArray;
    }
    public static function generateSingleEventCertificate($parameters) {
        $eventRegistrantRow = $parameters['event_registrant_row'];
        if(empty($eventRegistrantRow) || !is_array($eventRegistrantRow)) {
            return false;
        }
        $contactRow = $parameters['contact_row'] ?: Contact::getContact($eventRegistrantRow['contact_id']);
        if(empty($contactRow) || !is_array($contactRow)) {
            return false;
        }
        $eventRow = $parameters['event_row'] ?: getRowFromId("events", "event_id", $eventRegistrantRow['event_id']);
        if(empty($eventRow) || !is_array($eventRow)) {
            return false;
        }
        $eventAttendanceStatuses = $parameters['event_attendance_status_rows'];
        if(empty($eventAttendanceStatuses) || !is_array($eventAttendanceStatuses)) {
            $resultSet = executeQuery("select * from event_attendance_statuses where client_id = ?", $GLOBALS['gClientId']);
            while($row = getNextRow($resultSet)) {
                $eventAttendanceStatuses[$row['event_attendance_status_id']] = $row;
            }
        }
        $fileId = false;
        if (!empty($eventAttendanceStatuses[$eventRegistrantRow['event_attendance_status_id']]['fragment_id'])) {
            $substitutions = Events::getEventRegistrationSubstitutions($eventRow,$contactRow['contact_id']);
            $substitutions['full_name'] = getDisplayName($contactRow['contact_id']);
            if(empty($substitutions['end_date']) || $substitutions['end_date'] == $substitutions['start_date']) {
                $substitutions['event_date'] = $substitutions['start_date'];
            } else {
                $substitutions['event_date'] = $substitutions['start_date'] . " to " . $substitutions['end_date'];
            }
            $expirationDays = CustomField::getCustomFieldData($eventRow['event_type_id'], "CERTIFICATE_EXPIRATION_DAYS", "EVENT_TYPES");
            if(is_numeric($expirationDays) && $expirationDays > 0) {
                $dateFormat = getPreference("EVENT_EMAIL_DATE_FORMAT") ?: "m/d/Y";
                $substitutions['expiration_date'] = date($dateFormat, strtotime(($substitutions['end_date'] ?: $substitutions['start_date']) . " +" . $expirationDays . " days"));
            } else {
                $substitutions['expiration_date'] = "";
            }
            $fileId = outputPDF(false, array("substitutions" => $substitutions, "create_file" => true, "fragment_id" => $eventAttendanceStatuses[$eventRegistrantRow['event_attendance_status_id']]['fragment_id'],
                "filename" => "certificate.pdf", "description" => sprintf("Certificate for %s on %s",
                    getFieldFromId("description", "event_types", "event_type_id", $eventRow['event_type_id']),
                    date("m/d/Y", strtotime($eventRow['start_date'])))));
            if (!empty($fileId) && !empty($parameters['send_email'])) {
                $emailId = getFieldFromId("email_id", "emails", "email_code", "CERTIFICATE_ATTACHED",  "inactive = 0");
                if (empty($emailId)) {
                    sendEmail(array("subject" => "Class Certificate", "body" => "<p>Your certificate is attached</p>", "attachment_file_id" => $fileId, "email_address" => $contactRow['email_address']));
                } else {
                    sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "attachment_file_id" => $fileId, "email_address" => $contactRow['email_address']));
                }
            }
            executeQuery("update event_registrants set file_id = ? where event_registrant_id = ?", $fileId, $eventRegistrantRow['event_registrant_id']);
        }
        return $fileId;
    }

	public static function getEventForTime($eventDate, $startHour, $endHour, $facilityId, $eventId = "") {
		if (!array_key_exists("gEventFacilitiesForDate",$GLOBALS)) {
			$GLOBALS['gEventFacilitiesForDate'] = array();
			$resultSet = executeQuery("select * from event_facilities where event_id in (select event_id from events where start_date <= ? and (end_date is null or end_date >= ?) and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . ") and " .
				"date_needed = ?", date("Y-m-d", strtotime($eventDate)), date("Y-m-d", strtotime($eventDate)), date("Y-m-d", strtotime($eventDate)));
			while ($row = getNextRow($resultSet)) {
				$GLOBALS['gEventFacilitiesForDate'][] = $row;
			}
		}
		foreach ($GLOBALS['gEventFacilitiesForDate'] as $row) {
			if ($row['facility_id'] != $facilityId) {
				continue;
			}
			if ($row['hour'] < $startHour || $row['hour'] > $endHour) {
				continue;
			}
			if ($row['event_id'] != $eventId) {
				return Events::getEventInformation($row['event_id'], $eventDate);
			}
		}
		if (!array_key_exists("gEventFacilityRecurrencesForDate",$GLOBALS)) {
			$GLOBALS['gEventFacilityRecurrencesForDate'] = array();
			$resultSet = executeQuery("select * from event_facility_recurrences where event_id in (select event_id from events where start_date <= ? and (end_date is null or end_date >= ?) and " .
				"inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . ")", date("Y-m-d", strtotime($eventDate)), date("Y-m-d", strtotime($eventDate)));
			while ($row = getNextRow($resultSet)) {
				$GLOBALS['gEventFacilityRecurrencesForDate'][] = $row;
			}
		}
		foreach ($GLOBALS['gEventFacilityRecurrencesForDate'] as $row) {
			if ($row['facility_id'] != $facilityId) {
				continue;
			}
			if ($row['hour'] < $startHour || $row['hour'] > $endHour) {
				continue;
			}
			if ($eventId == $row['event_id']) {
				continue;
			}
			if (isInSchedule($eventDate, $row['repeat_rules'])) {
				return Events::getEventInformation($row['event_id'], $eventDate);
			}
		}
		return false;
	}

	public static function getEventInformation($eventId, $conflictDate) {
		if (!array_key_exists("gEventInformation",$GLOBALS)) {
			$GLOBALS['gEventInformation'] = array();
		}
		if (!array_key_exists($eventId,$GLOBALS['gEventInformation'])) {
			$eventInfo = getMultipleFieldsFromId(array("contact_id", "description", "event_type_id"), "events", "event_id", $eventId);
			$contactId = $eventInfo['contact_id'];
			$contact = (empty($contactId) ? "" : getDisplayName($contactId));
			$description = (empty($contact) ? "" : $contact . " - ") . $eventInfo['description'];
			$conflictDescription = (empty($contact) ? "" : $contact . " - ") . $description . " on %conflict_date%";
			$returnArray = array("conflict_description" => $conflictDescription, "description" => $description, "event_id" => $eventId, "contact_id" => $contactId, "event_type_id" => $eventInfo['event_type_id']);
			$GLOBALS['gEventInformation'][$eventId] = $returnArray;
		}
		$returnArray = $GLOBALS['gEventInformation'][$eventId];
		$conflictDate = date("m/d/Y", strtotime($conflictDate));
		foreach ($returnArray as $index => $value) {
			$returnArray[$index] = str_replace("%conflict_date%",$conflictDate,$value);
		}
		return $returnArray;
	}

	public static function getEventForRecurringTime($repeatRules, $startHour, $endHour, $facilityId, $eventId = "") {
		if (!is_array($repeatRules)) {
			$repeatRulesArray = array();
			$nameValues = explode(";", $repeatRules);
			foreach ($nameValues as $thisValue) {
				$parts = explode("=", $thisValue);
				$repeatRulesArray[strtolower($parts[0])] = $parts[1];
			}
		} else {
			$repeatRulesArray = $repeatRules;
		}
		foreach ($repeatRulesArray as $fieldName => $fieldValue) {
			$repeatRulesArray[strtolower($fieldName)] = $fieldValue;
		}
		if (empty($repeatRulesArray['start_date'])) {
			$startDate = date("Y-m-d");
		} else {
			$startDate = date("Y-m-d", strtotime($repeatRulesArray['start_date']));
		}
		if ($startDate < date("Y-m-d")) {
			$startDate = date("Y-m-d");
		}
		if (empty($repeatRulesArray['until'])) {
			if (!empty($repeatRulesArray['count'])) {
				$addDays = 0;
				switch ($repeatRulesArray['frequency']) {
					case "WEEKLY":
						$addDays = 7 * $repeatRulesArray['count'];
						break;
					case "MONTHLY":
						$addDays = round((365 / 12) * $repeatRulesArray['count']);
						break;
					case "YEARLY":
						$addDays = $repeatRulesArray['count'] * 365;
						break;
					default:
						$addDays = $repeatRulesArray['count'];
				}
				$endDate = date("Y-m-d", strtotime("+" . $addDays . " days", strtotime($startDate)));
			} else {
				$endDate = date("Y-m-d", strtotime("+28 years", strtotime($startDate)));
			}
		} else {
			$endDate = date("Y-m-d", strtotime($repeatRulesArray['until']));
		}
		$query = "select * from event_facilities where facility_id = ?";
		$parameters = array($facilityId);
		$query .= " and event_id in (select event_id from events where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0");
		$query .= " and start_date >= ?";
		$parameters[] = $startDate;
		if (!empty($endDate)) {
			$query .= " and (end_date is null or end_date <= ?)";
			$parameters[] = $endDate;
		}
		$query .= ")";
		$query .= " and hour between ? and ?";
		$parameters[] = $startHour;
		$parameters[] = $endHour;
		$resultSet = executeQuery($query, $parameters);
		while ($row = getNextRow($resultSet)) {
			if ($eventId == $row['event_id']) {
				continue;
			}
			if (isInSchedule($row['date_needed'], $repeatRules)) {
				return Events::getEventInformation($row['event_id'], $row['date_needed']);
			}
		}
		$recurringEvents = array();
		$query = "select *,(select end_date from events where inactive = 0 and event_id = event_facility_recurrences.event_id) end_date from event_facility_recurrences where facility_id = ?";
		$parameters = array($facilityId);
		$query .= " and event_id in (select event_id from events where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0");
		$query .= " and start_date <= ?";
		$parameters[] = $startDate;
		if (!empty($endDate)) {
			$query .= " and (end_date is null or end_date >= ?)";
			$parameters[] = $endDate;
		}
		$query .= ")";
		$query .= " and hour between ? and ?";
		$parameters[] = $startHour;
		$parameters[] = $endHour;
		$resultSet = executeQuery($query, $parameters);
		while ($row = getNextRow($resultSet)) {
			if ($eventId == $row['event_id']) {
				continue;
			}
			$recurringEvents[$row['event_facility_recurrence_id']] = $row;
		}
		while ($startDate <= $endDate) {
			foreach ($recurringEvents as $index => $recurringEventRow) {
				if (!empty($recurringEventRow['end_date']) && $recurringEventRow['end_date'] < $startDate) {
					unset($recurringEvents[$index]);
					continue;
				}
				$parts = parseNameValues($recurringEventRow['repeat_rules']);
				if (!empty($parts['until']) && $parts['until'] < $startDate) {
					unset($recurringEvents[$index]);
					continue;
				}
				if (isInSchedule($startDate, $recurringEventRow['repeat_rules'])) {
					return Events::getEventInformation($recurringEventRow['event_id'], $startDate);
				}
			}
			if (empty($recurringEvents)) {
				break;
			}
			$startDate = date("Y-m-d", strtotime("+1 days", strtotime($startDate)));
		}
		return false;
	}

	public static function getAvailableFacilities($facilityTypeId, $facilityId, $dateNeeded, $startHour, $endHour, $numberPeople = 1) {
		$dateString = strtotime($dateNeeded);
		if ($dateString === false || empty($dateNeeded) || strlen($dateNeeded) == 0) {
			$dateNeeded = "";
		}
		if (empty($dateNeeded) || $endHour < $startHour) {
			return "Invalid time slot: Date is required and end time must be later than start time.";
		}
		if (strlen($startHour) == 0) {
			$startHour = 0;
		}
		if (strlen($endHour) == 0) {
			$endHour = 23.75;
		}
		if ($numberPeople <= 0) {
			$numberPeople = 1;
		}
		$facilityTypeId = getFieldFromId("facility_type_id", "facility_types", "facility_type_id", $facilityTypeId, "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and reservable = 1");
		$facilityId = getFieldFromId("facility_id", "facilities", "facility_id", $facilityId, "internal_use_only = 0 and inactive = 0 and (maximum_capacity is null or maximum_capacity >= ?)", $numberPeople);
		if (empty($facilityId) && empty($facilityTypeId)) {
			return "No facility selected.";
		}
		$resultSet = executeQuery("select * from facility_closures where closure_date = ? and " .
			"(facility_id = ? or facility_type_id = ? or (facility_type_id is null and facility_id is null))",
			date("Y-m-d", $dateString), $facilityId, $facilityTypeId);
		if ($row = getNextRow($resultSet)) {
			if (empty($row['start_time']) && empty($row['end_time'])) {
				return "Facility Closed All Day";
			}
			$closureStartHour = (empty($row['start_time']) ? 0 : date("G", strtotime($row['start_time'])));
			if (!empty($row['start_time'])) {
				$minute = date("i", strtotime($row['start_time']));
				if ($minute > 0 && $minute <= 15) {
					$closureStartHour += .25;
				} else if ($minute > 15 && $minute <= 30) {
					$closureStartHour += .5;
				} else if ($minute > 30) {
					$closureStartHour += .75;
				}
			}
			$closureEndHour = (empty($row['end_time']) ? 23.75 : date("G", strtotime($row['end_time'])));
			if (!empty($row['end_time'])) {
				$minute = date("i", strtotime($row['end_time']));
				if ($minute > 0 && $minute <= 15) {
					$closureEndHour += .25;
				} else if ($minute > 15 && $minute <= 30) {
					$closureEndHour += .5;
				} else if ($minute > 30) {
					$closureEndHour += .75;
				}
			}
			if ($startHour < $closureStartHour || $endHour > $closureEndHour) {
				return "Facility Closed" . (empty($row['start_time']) ? "" : " until " . date("g:i a", strtotime($row['start_time']))) .
					(empty($row['end_time']) ? "" : (empty($row['start_time']) ? "" : " and") . " after " . date("g:i a", strtotime($row['end_time'])));
			}
		}
		$facilityIdArray = array();
		if (empty($facilityId)) {
			$resultSet = executeQuery("select facility_id from facilities where client_id = ? and (maximum_capacity is null or maximum_capacity >= ?) and facility_type_id = ? and inactive = 0" .
				($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"), $GLOBALS['gClientId'], $numberPeople, $facilityTypeId);
			while ($row = getNextRow($resultSet)) {
				$facilityIdArray[] = $row['facility_id'];
			}
		} else {
			$facilityIdArray[] = $facilityId;
		}
		$weekday = date("w", strtotime($dateNeeded));
		$notAvailable = array();
		foreach ($facilityIdArray as $facilityId) {
			$availableHours = array();
			$resultSet = executeQuery("select * from facility_availability where weekday = ? and facility_id = ?", $weekday, $facilityId);
			while ($row = getNextRow($resultSet)) {
				$availableHours[] = $row['hour'];
			}
			for ($x = $startHour; $x <= $endHour; $x += .25) {
				if (!in_array(floor($x), $availableHours)) {
					$notAvailable[] = $facilityId;
					break;
				}
			}
			if (in_array($facilityId, $notAvailable)) {
				continue;
			}
			$resultSet = executeQuery("select * from event_facilities where facility_id = ? and date_needed = ? and hour between ? and ?",
				$facilityId, $GLOBALS['gPrimaryDatabase']->makeDateParameter($dateNeeded), $startHour, $endHour);
			if ($row = getNextRow($resultSet)) {
				$notAvailable[] = $facilityId;
				continue;
			}
			$recurrenceArray = array();
			$notValid = array();
			$resultSet = executeQuery("select * from event_facility_recurrences where facility_id = ?", $facilityId);
			while ($row = getNextRow($resultSet)) {
				if (array_key_exists($row['repeat_rules'], $recurrenceArray)) {
					$recurrenceArray[$row['repeat_rules']][] = $row['hour'];
				} else if (in_array($row['repeat_rules'], $notValid)) {
					continue;
				} else {
					if (isInSchedule($dateNeeded, $row['repeat_rules'])) {
						$recurrenceArray[$row['repeat_rules']] = array($row['hour']);
					} else {
						$notValid[] = $row['repeat_rules'];
					}
				}
			}
			foreach ($recurrenceArray as $hoursUsed) {
				for ($x = $startHour; $x <= $endHour; $x += .25) {
					if (in_array($x, $hoursUsed)) {
						$notAvailable[] = $facilityId;
						break;
					}
				}
				if (in_array($facilityId, $notAvailable)) {
					break;
				}
			}
		}
		return array_diff($facilityIdArray, $notAvailable);
	}

	public static function getEventRegistrationSubstitutions($eventRow, $contactId) {
        $removeFields = array("password_salt" => "", "password" => "", "security_question_id" => "", "secondary_security_question_id" => "", "answer_text" => "",
            "secondary_answer_text" => "", "superuser_flag" => "", "administrator_flag" => "", "last_login" => "", "last_password_change" => "", "time_locked_out" => "",
            "force_password_change" => "", "full_client_access" => "", "security_level_id" => "", "verification_code" => "", "locked" => "", "user_date_created" => "");
        $dateFormat = getPreference("EVENT_EMAIL_DATE_FORMAT") ?: "m/d/Y";
		$eventTypeRow = getRowFromId("event_types", "event_type_id", $eventRow['event_type_id']);
		$substitutions = array_diff_key(Contact::getContact($contactId), $removeFields);
		$substitutions = array_merge($substitutions, $eventRow, Events::getEventTypeSubstitutions($eventTypeRow));
        $substitutions['order_id'] = getFieldFromId("order_id", "event_registrants", "contact_id", $contactId, "event_id = ?", $eventRow['event_id']);
		$locationRow = getRowFromId("locations", "location_id", $eventRow['location_id']);
		$substitutions['location'] = $locationRow['description'];
		$substitutions['location_address_block'] = getAddressBlock(Contact::getContact($locationRow['contact_id']));
		$substitutions['start_date'] = date($dateFormat, strtotime($substitutions['start_date']));
		$substitutions['end_date'] = (empty($substitutions['end_date']) ? "" : date($dateFormat, strtotime($substitutions['end_date'])));

		$substitutions['start_time'] = "";
		$hour = "";
		$hourSet = executeQuery("select * from event_facilities where date_needed = ? and event_id = ? and facility_id in (select facility_id from facilities where inactive = 0 and internal_use_only = 0) order by hour", $eventRow['start_date'], $eventRow['event_id']);
		if ($hourRow = getNextRow($hourSet)) {
			$substitutions['facility'] = getFieldFromId("description", "facilities", "facility_id", $hourRow['facility_id']);
			$hour = $hourRow['hour'];
		}
		if (!empty($hour)) {
			$workingHour = floor($hour);
			$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
			$displayMinutes = ($hour - $workingHour) * 60;
			$displayAmpm = ($hour == 0 ? "midnight" : ($hour == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
			$displayTime = $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm;
			$substitutions['start_time'] = $displayTime;
		}

		$substitutions['end_time'] = "";
		$hour = "";
		$hourSet = executeQuery("select * from event_facilities where date_needed = ? and event_id = ? and facility_id in (select facility_id from facilities where inactive = 0 and internal_use_only = 0) order by hour desc", (empty($eventRow['end_date']) ? $eventRow['start_date'] : $eventRow['end_date']), $eventRow['event_id']);
		if ($hourRow = getNextRow($hourSet)) {
			$hour = $hourRow['hour'];
		}
		if (!empty($hour)) {
			$hour += .25;
			$workingHour = floor($hour);
			$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
			$displayMinutes = ($hour - $workingHour) * 60;
			$displayAmpm = ($hour == 0 ? "midnight" : ($hour == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
			$displayTime = $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm;
			$substitutions['end_time'] = $displayTime;
		}
		$substitutions['product_url'] = "";
		if (!empty($eventRow['product_id'])) {
			$productRow = ProductCatalog::getCachedProductRow($eventRow['product_id']);
			$urlAliasTypeCode = getUrlAliasTypeCode("products", "product_id", "id");
			if (!empty($urlAliasTypeCode) && !empty($productRow['link_name'])) {
				$substitutions['product_url'] = getDomainName() . "/" . $urlAliasTypeCode . "/" . $productRow['link_name'];
			}
		}
		return $substitutions;
	}

	public static function getEventTypeSubstitutions($eventTypeRow) {
		$substitutions = array();
		$eventTypeRow['image_filename'] = getImageFilename($eventTypeRow['image_id'], array("use_cdn" => true));
		$eventTypeRow['description'] = htmlText($eventTypeRow['description']);
		$eventTypeRow['detailed_description'] = htmlText($eventTypeRow['detailed_description']);
		$eventTypeRow['excerpt'] = htmlText($eventTypeRow['excerpt']);
		$eventTypeRow['qualified'] = Events::isQualified(array("event_type_id" => $eventTypeRow['event_type_id']));

		foreach (array("event_type_id", "event_type_code", "description", "detailed_description", "excerpt", "link_name", "class_instructor_id",
					 "attendees", "product_id", "price", "change_days", "cancellation_days", "any_requirement", "image_id", "image_filename", "qualified") as $fieldName) {
			$substitutionKey = startsWith($fieldName, "event_type") ? $fieldName : "event_type_" . $fieldName;
			$substitutions[$substitutionKey] = $eventTypeRow[$fieldName];
		}
		$substitutions['event_type'] = $eventTypeRow['description'];
		return $substitutions;
	}

	public static function sendEventNotifications($eventId,$registrantContactIds = array()) {
		$eventRow = getRowFromId("events", "event_id", $eventId);
		$eventTypeId = $eventRow['event_type_id'];
		$emailAddresses = array();
		$resultSet = executeQuery("select * from event_type_notifications where event_type_id = ?", $eventTypeId);
		while ($row = getNextRow($resultSet)) {
			if (!in_array($row['email_address'], $emailAddresses)) {
				$emailAddresses[] = $row['email_address'];
			}
		}
		if (empty($registrantContactIds)) {
			$body = "<p>An event of type '" . getFieldFromId("description", "event_types", "event_type_id", $eventTypeId) . "' has been created or updated. Event Details:</p>" .
				"<p>ID: " . $eventRow['event_id'] . "</p>" .
				"<p>Admin Page: <a href='https://" . $_SERVER['HTTP_HOST'] . "/event-maintenance?url_page=show&clear_filter=true&primary_id=" . $eventRow['event_id'] . "'>Here</a></p>" .
				"<p>Description: " . $eventRow['event_id'] . "</p>" .
				"<p>Date: " . date("m/d/Y", strtotime($eventRow['start_date'])) . "</p>";
			$resultSet = executeQuery("select facility_id,min(hour),max(hour) from event_facilities where event_id = ?", $eventId);
			while ($row = getNextRow($resultSet)) {
				$thisStartHour = floor($row['hour']) . ":" . str_pad(($row['hour'] - floor($row['hour'])) * 60, 2, "0", STR_PAD_LEFT) . ":00";
				$thisEndHour = floor($row['hour'] + .25) . ":" . str_pad((($row['hour'] + .25) - floor($row['hour'] + .25)) * 60, 2, "0", STR_PAD_LEFT) . ":00";
				$body .= "<p>Location: " . getFieldFromId("description", "facilities", "facility_id", $row['facility_id']) . ($row['date_needed'] == $eventRow['start_date'] ? "" : ", " . date("m/d/Y", strtotime($row['date_needed']))) .
					", " . $thisStartHour . "-" . $thisEndHour;
			}
			$resultSet = executeQuery("select * from event_requirement_data join event_requirements using (event_requirement_id) where event_id = ? order by sort_order,description", $eventId);
			if ($resultSet['row_count'] > 0) {
				$body .= "<p>The event has the following requirements:</p><ul>";
				while ($row = getNextRow($resultSet)) {
					if (empty($row['notifications_sent'])) {
						$emailSet = executeQuery("select * from event_requirement_notifications where event_requirement_id = ?", $row['event_requirement_id']);
						while ($emailRow = getNextRow($emailSet)) {
							if (!in_array($emailRow['email_address'], $emailAddresses)) {
								$emailAddresses[] = $emailRow['email_address'];
							}
						}
					}
					$body .= "<li>" . $row['description'] . "</li>";
				}
				$body .= "</ul>";
			}
			if (!empty($emailAddresses)) {
				sendEmail(array("body" => $body, "subject" => "Event Created/Updated", "email_addresses" => $emailAddresses));
			}
		} else {
			$registrantNames = array();
			foreach ($registrantContactIds as $contactId => $eventRegistrantId) {
				$registrantNames[] = getDisplayName($contactId);
				$resultSet = executeQuery("select text_data from custom_field_data where primary_identifier = ? and custom_field_id in (select custom_field_id from " .
					"event_registration_custom_fields where event_id = ? and custom_field_id in (select custom_field_id from custom_field_controls where " .
					"control_name = 'additional_registrants' and control_value is not null and control_value <> 'false'))", $eventRegistrantId,$eventRow['event_id']);
				while ($row = getNextRow($resultSet)) {
					if (startsWith($row['text_data'],"[{")) {
						$fieldValues = json_decode($row['text_data'],true);
						if (is_array($fieldValues)) {
							foreach ($fieldValues as $thisEntry) {
								if (array_key_exists("name",$thisEntry) && !empty($thisEntry['name'])) {
									$registrantNames[] = $thisEntry['name'];
								}
							}
						}
					}
				}
			}
			if (!empty($registrantNames)) {
				$body = "<p>The following person(s) have registered for the event '" . $eventRow['description'] . "':</p><ul>";
				foreach ($registrantNames as $thisName) {
					$body .= "<li>" . $thisName . "</li>";
				}
				$body .= "</ul>";
				$emailAddresses = array();
				$resultSet = executeQuery("select * from event_registration_notifications where event_id = ?", $eventId);
				while ($row = getNextRow($resultSet)) {
					$emailAddresses[] = $row['email_address'];
				}
				if (!empty($emailAddresses)) {
					sendEmail(array("body" => $body, "subject" => "Registrations for event", "email_addresses" => $emailAddresses));
				}
			}
		}
	}

	public static function assembleRepeatRules($parts) {
		$rules = "";
		foreach ($parts as $name => $value) {
			if (!empty($name) && !in_array($name, array("start", "end", "facility_id"))) {
				$rules .= strtoupper($name) . "=" . $value . ";";
			}
		}
		return $rules;
	}

	public static function makeRepeatRules($repeatParameters) {
		$repeatRules = "";
		if (!empty($repeatParameters['recurring'])) {
			$repeatRules = "START_DATE=" . $repeatParameters['recurring_start_date'] . ";FREQUENCY=" . $repeatParameters['frequency'] . ";";
			if (!empty($repeatParameters['interval']) && $repeatParameters['interval'] > 1) {
				$repeatRules .= "INTERVAL=" . $repeatParameters['interval'] . ";";
			}
			if ($repeatParameters['frequency'] == "YEARLY") {
				$repeatRules .= "BYMONTH=";
				$parts = "";
				foreach ($repeatParameters as $fieldName => $fieldValue) {
					if (empty($fieldValue)) {
						continue;
					}
					if (substr($fieldName, 0, strlen("bymonth_")) == "bymonth_") {
						$parts .= (empty($parts) ? "" : ",") . $fieldValue;
					}
				}
				$repeatRules .= $parts . ";";
			}
			if ($repeatParameters['frequency'] == "WEEKLY") {
				$repeatRules .= "BYDAY=";
				$parts = "";
				foreach ($repeatParameters as $fieldName => $fieldValue) {
					if (empty($fieldValue)) {
						continue;
					}
					if (substr($fieldName, 0, strlen("byday_")) == "byday_") {
						$parts .= (empty($parts) ? "" : ",") . $fieldValue;
					}
				}
				$repeatRules .= $parts . ";";
			}
			if ($repeatParameters['frequency'] == "MONTHLY" || $repeatParameters['frequency'] == "YEARLY") {
				$repeatRules .= "BYDAY=";
				$parts = "";
				foreach ($repeatParameters as $fieldName => $fieldValue) {
					if (empty($fieldValue)) {
						continue;
					}
					if (substr($fieldName, 0, strlen("ordinal_day_")) == "ordinal_day_") {
						$rowNumber = substr($fieldName, strlen("ordinal_day_"));
						$parts .= (empty($parts) ? "" : ",") . $fieldValue . $repeatParameters['weekday_' . $rowNumber];
					}
				}
				$repeatRules .= $parts . ";";
			}
			if (!empty($repeatParameters['count'])) {
				$repeatRules .= "COUNT=" . $repeatParameters['count'] . ";";
			}
			if (!empty($repeatParameters['until'])) {
				$repeatRules .= "UNTIL=" . $repeatParameters['until'] . ";";
			}
		}
		return $repeatRules;
	}

	public static function getEventColor($eventData, $facilityData) {
		return Events::getEventColorClass($eventData, $facilityData, true);
	}

	public static function getEventColorClass($eventData, $facilityData, $rawColor = false) {
		if (self::$iEventColors === false) {
			self::$iEventColors = array();
			$resultSet = executeQuery("select * from event_colors where client_id = ? order by sort_order,event_color_id", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				self::$iEventColors[] = $row;
			}
		}
		$eventId = (is_array($eventData) ? $eventData['event_id'] : $eventData);
		$eventColorClass = getCachedData("event_color_class", $eventId);
		$eventColor = getCachedData("event_color", $eventId);
		if (!empty($eventColorClass) && !$rawColor) {
			return $eventColorClass;
		}
		if (!empty($eventColor) && $rawColor) {
			return $eventColor;
		}
		if (is_array($eventData)) {
			$eventRow = $eventData;
		} else {
			$eventRow = getRowFromId("events", "event_id", $eventData);
		}
		if (is_array($facilityData)) {
			$facilityRow = $facilityData;
		} else {
			$facilityRow = getRowFromId("facilities", "facility_id", $facilityData);
		}
		$eventColorClass = "";
		$eventColor = "";
		if (!empty(self::$iEventColors)) {
			$contactId = $eventRow['contact_id'];
			$userId = Contact::getContactUserId($contactId);
			foreach (self::$iEventColors as $thisEventColor) {
				switch ($thisEventColor['comparator']) {
					case "order":
						if (!empty($eventRow['order_id']) && empty($thisEventColor['exclude'])) {
							$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
						} else if (empty($eventRow['order_id']) && !empty($thisEventColor['exclude'])) {
							$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
						}
						break;
					case "event_type":
						if ($eventRow['event_type_id'] == $thisEventColor['field_value'] && empty($thisEventColor['exclude'])) {
							$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
						} else if (!empty($eventRow['event_type_id']) && $eventRow['event_type_id'] != $thisEventColor['field_value'] && !empty($thisEventColor['exclude'])) {
							$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
						}
						break;
					case "facility_type":
						if ($facilityRow['facility_type_id'] == $thisEventColor['field_value'] && empty($thisEventColor['exclude'])) {
							$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
						} else if (!empty($facilityRow['facility_type_id']) && $facilityRow['facility_type_id'] != $thisEventColor['field_value'] && !empty($thisEventColor['exclude'])) {
							$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
						}
						break;
					case "contact_type":
						if (!empty($contactId)) {
							$contactTypeId = getFieldFromId("contact_type_id", "contacts", "contact_id", $contactId);
							if ($contactTypeId == $thisEventColor['field_value'] && empty($thisEventColor['exclude'])) {
								$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
							} else if (!empty($contactTypeId) && $contactTypeId != $thisEventColor['field_value'] && !empty($thisEventColor['exclude'])) {
								$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
							}
						}
						break;
					case "contact_category":
						if (!empty($contactId)) {
							$contactCategoryId = getFieldFromId("contact_category_id", "contact_categories", "contact_id", $contactId, "category_id = ?", $thisEventColor['field_value']);
							if (!empty($contactCategoryId) && empty($thisEventColor['exclude'])) {
								$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
							} else if (empty($contactCategoryId) && !empty($thisEventColor['exclude'])) {
								$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
							}
						}
						break;
					case "user_type":
						if (!empty($userId)) {
							$userTypeId = getFieldFromId("user_type_id", "users", "user_id", $userId);
							if ($userTypeId == $thisEventColor['field_value'] && empty($thisEventColor['exclude'])) {
								$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
							} else if (!empty($userTypeId) && $userTypeId != $thisEventColor['field_value'] && !empty($thisEventColor['exclude'])) {
								$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
							}
						}
						break;
					case "user_group":
						if (!empty($userId)) {
							$userGroupMemberId = getFieldFromId("user_group_member_id", "user_group_members", "user_id", $userId, "user_group_id = ?", $thisEventColor['field_value']);
							if (!empty($userGroupMemberId) && empty($thisEventColor['exclude'])) {
								$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
							} else if (empty($userGroupMemberId) && !empty($thisEventColor['exclude'])) {
								$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
							}
						}
						break;
					case "reserved":
						if (!empty($contactId) && empty($thisEventColor['exclude'])) {
							$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
						} else if (empty($contactId) && !empty($thisEventColor['exclude'])) {
							$eventColorClass = "event-color-id-" . $thisEventColor['event_color_id'];
						}
						break;
				}
				if (!empty($eventColorClass)) {
					$eventColor = $thisEventColor['display_color'];
					break;
				}
			}
		}
		if (!empty($eventId)) {
			setCachedData("event_color_class", $eventId, $eventColorClass, .25);
			setCachedData("event_color", $eventId, $eventColor, .25);
		}
		return ($rawColor ? $eventColor : $eventColorClass);
	}

	public static function getDisplayTime($hour, $endTime = false, $array = false, $increment = .25) {
		if (strlen($hour) == 0) {
			return "";
		}
		if ($endTime) {
			$hour += $increment;
		}
		if ($hour >= 24) {
			$hour -= 24;
		}
		$workingHour = floor($hour);
		$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
		$displayMinutes = round(($hour - $workingHour) * 60);
		$displayAmpm = ($hour == 0 ? "midnight" : ($hour == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
		$displayTime = $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm;
		if (!$array) {
			return $displayTime;
		} else {
			return array("hour" => $displayHour, "minutes" => $displayMinutes, "ampm" => $displayAmpm, "formatted" => $displayTime);
		}
	}

	// e.g. 23.75 to 23:45:00
	public static function getMilitaryTime($inputHours) {
		$hours = floor($inputHours);
		$minutes = ($inputHours - $hours) * 60;
		$seconds = ($minutes - floor($minutes)) * 60;
		return sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
	}

	public static function getTime($time, $endTime = false, $increment = .25) {
		$timeParts = explode(":", $time);
		$timeMinuteParts = explode(" ", $timeParts[1]);
		if ($timeMinuteParts[1] === "pm") {
			$returnHour = $timeParts[0] < 12 ? $timeParts[0] + 12 : $timeParts[0];
		} else {
			$returnHour = $timeParts[0] == 12 ? 0 : $timeParts[0];
		}
		$minuteDivisor = 60 * $increment;
		$roundedOffMinute = $endTime ? ceil($timeMinuteParts[0] / $minuteDivisor) : floor($timeMinuteParts[0] / $minuteDivisor);
		return $returnHour + ($roundedOffMinute * $increment);
	}

	public static function eventChoices($showInactive = false) {
		$eventChoices = array();
		$resultSet = executeReadQuery("select * from events where start_date >= current_date and inactive = 0 and client_id = ?",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$eventChoices[$row['event_id']] = array("key_value" => $row['event_id'], "description" => $row['description'] . " - " . date("m/d/Y",strtotime($row['start_date'])), "inactive" => $row['inactive'] == 1);
			}
		}
		return $eventChoices;
	}

	public static function notifyCRM($eventId) {
		// InfusionSoft event registration updates take too long to process in the foreground; this is now done in the syncmailchimp background process.
	}

	public static function isQualified($parameters) {
		$eventTypeId = false;
		if (!empty($parameters['event_type_id'])) {
			$eventTypeId = getFieldFromId("event_type_id","event_types","event_type_id",$parameters['event_type_id']);
			if (empty($eventTypeId)) {
				return false;
			}
		}
		if (!empty($parameters['event_type_code'])) {
			$eventTypeId = getFieldFromId("event_type_id","event_types","event_type_code",$parameters['event_type_code']);
			if (empty($eventTypeId)) {
				return false;
			}
		}
		if (!empty($parameters['event_id'])) {
			$eventTypeId = getFieldFromId("event_type_id","events","event_id",$parameters['event_id']);
			if (empty($eventTypeId)) {
				return false;
			}
		}
		if (empty($eventTypeId)) {
			return false;
		}
		$contactId = $parameters['contact_id'];
		if (empty($contactId)) {
			$contactId = $GLOBALS['gUserRow']['contact_id'];
		}
        $eventTypeRow = getRowFromId("event_types","event_type_id",$eventTypeId);
		$userCertified = true;
		$resultSet = executeReadQuery("select * from certification_types join event_type_requirements using (certification_type_id) where event_type_id = ? order by sort_order,description", $eventTypeId);
		if ($resultSet['row_count'] > 0) {
			$requirementsFound = 0;
			$userCertified = !empty($contactId);
			while ($row = getNextRow($resultSet)) {
				if (!empty($contactId)) {
					$statusSet = executeReadQuery("select * from contact_certifications where contact_id = ? and certification_type_id = ? and date_issued <= current_date", $contactId, $row['certification_type_id']);
					$contactCertificationRow = false;
					while ($statusRow = getNextRow($statusSet)) {
						if (empty($statusRow['expiration_date'])) {
							$contactCertificationRow = $statusRow;
							break;
						}
						if (empty($contactCertificationRow)) {
							$contactCertificationRow = $statusRow;
						} else if ($contactCertificationRow['expiration_date'] < $statusRow['expiration_date']) {
							$contactCertificationRow = $statusRow;
						}
					}
                    if (!empty($contactCertificationRow['expiration_date']) && $contactCertificationRow['expiration_date'] < date("Y-m-d")) {
						$contactCertificationRow = false;
					}
					if (empty($contactCertificationRow)) {
						if (empty($eventTypeRow['any_requirement'])) {
							$userCertified = false;
						}
					} else {
						$requirementsFound++;
					}
				}
				if ($requirementsFound == 0) {
					$userCertified = false;
				}
			}
		}
		return $userCertified;
	}

	// Displays events using event facilities, single event that spans across multiple days will show as multiple records
    public static function getEvents($parameters) {
        $returnArray = array();
        $events = array();
        $locationIds = array();
		$eventTypeIds = array();

        $parameters['wishlist_product_ids'] = array();
        if ($GLOBALS['gLoggedIn']) {
            $wishList = new WishList();
            foreach ($wishList->getWishListItems() as $wishListItem) {
				$parameters['wishlist_product_ids'][] = $wishListItem['product_id'];
            }
        }
        if (!empty($parameters['location_ids'])) {
            foreach (explode(",", $parameters['location_ids']) as $thisLocationId) {
                $locationId = getFieldFromId("location_id", "locations", "location_id", $thisLocationId, "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
                if (!empty($locationId)) {
                    $locationIds[] = $locationId;
                }
            }
        }
        if (!empty($parameters['event_type_ids'])) {
            foreach (explode(",", $parameters['event_type_ids']) as $thisEventTypeId) {
                $eventTypeId = getFieldFromId("event_type_id", "event_types", "event_type_id", $thisEventTypeId, "hide_in_calendar = 0 and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
                if (!empty($eventTypeId)) {
                    $eventTypeIds[] = $eventTypeId;
                }
            }
        }

        $startDate = empty($parameters['start_date']) ? date("Y-m-d") : makeDateParameter($parameters['start_date']);
        $endDate = empty($parameters['end_date']) ? date("Y-m-d", strtotime('+1 month', strtotime(date("Y-m-d")))) : makeDateParameter($parameters['end_date']);

        $eventFacilitiesQuery = "select * from event_facilities where date_needed between ? and ?"
			. " and event_id in (select event_id from events where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0")
			. " and event_type_id in (select event_type_id from event_types where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0")
			. " and hide_in_calendar = 0))";
		$whereParameters = array($startDate, $endDate, $GLOBALS['gClientId'], $GLOBALS['gClientId']);

		if (!empty($locationIds)) {
			$eventFacilitiesQuery .= " and (facility_id in (select facility_id from facilities where inactive = 0 and client_id = ? and location_id in (" . implode(",", $locationIds) . ")" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . ")"
				. " or event_id in (select event_id from events where location_id in (" . implode(",", $locationIds) . ")))";
			$whereParameters[] = $GLOBALS['gClientId'];
		}
        if (!empty($eventTypeIds)) {
            $eventFacilitiesQuery .= " and event_id in (select event_id from events where event_type_id in (" . implode(",", $eventTypeIds) . "))";
        }

        $eventFacilitiesQuery .= " order by date_needed, event_id, facility_id, hour";
        $resultSet = executeQuery($eventFacilitiesQuery, $whereParameters);

        $currentDate = "";
        $currentEventId = "";
        $currentFacilityId = "";
        $lastHour = "";
        $firstHour = "";
        $index = -1;
        while ($row = getNextRow($resultSet)) {
			$parameters['event_id'] = $row['event_id'];
            $thisDate = date("Y-m-d", strtotime($row['date_needed']));
            $thisStartHour = self::getMilitaryTime($row['hour']);
            $thisEndHour = self::getMilitaryTime($row['hour'] + .25);
			if ($currentDate != $row['date_needed'] || $currentEventId != $row['event_id']) {
                $index++;
                $events[$index] = self::getEventDetails($parameters);
				$events[$index]['start'] = date("c", strtotime($thisDate . " " . $thisStartHour));
				$events[$index]['end'] = date("c", strtotime($thisDate . " " . $thisEndHour));
                $events[$index]['facilities'][] = self::getFacilityDetails($row['facility_id']);

                $firstHour = $row['hour'];
                $lastHour = $row['hour'];
                $currentDate = $row['date_needed'];
                $currentEventId = $row['event_id'];
                $currentFacilityId = $row['facility_id'];
                continue;
            }
            if ($row['hour'] <= ($lastHour + .25)) {
                if ($currentFacilityId != $row['facility_id']) {
                    $events[$index]['facilities'][] = self::getFacilityDetails($row['facility_id']);
                }

                if ($row['hour'] > $lastHour) {
                    $events[$index]["end"] = date("c", strtotime($thisDate . " " . $thisEndHour));
                    $lastHour = $row['hour'];
                } else if ($row['hour'] < $firstHour) {
                    $events[$index]['start'] = date("c", strtotime($thisDate . " " . $thisStartHour));
                    $firstHour = $row['hour'];
                }
            } else {
                $index++;
                $events[$index] = self::getEventDetails($parameters);
				$events[$index]['start'] = date("c", strtotime($thisDate . " " . $thisStartHour));
				$events[$index]['end'] = date("c", strtotime($thisDate . " " . $thisEndHour));
                $events[$index]['facilities'][] = self::getFacilityDetails($row['facility_id']);
            }
            $currentDate = $row['date_needed'];
            $currentEventId = $row['event_id'];
            $currentFacilityId = $row['facility_id'];
        }

        $recurrenceArray = array();
        $currentEventId = "";
        $currentFacilityId = "";
        $currentRepeatRules = "";
        $lastHour = "";
        $recurIndex = -1;
        $resultSet = executeQuery("select * from event_facility_recurrences where"
            . " event_id in (select event_id from events where inactive = 0 and client_id = " . $GLOBALS['gClientId'] . " and event_type_id in (select event_type_id from event_types where inactive = 0 and hide_in_calendar = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . "))"
            . (empty($locationIds) ? "" : " and facility_id in (select facility_id from facilities where location_id in (" . implode(",", $locationIds) . "))")
            . " order by event_id, facility_id, repeat_rules, hour");
        while ($row = getNextRow($resultSet)) {
            $row['product_id'] = getFieldFromId("product_id", "events", "event_id", $row['event_id']);
            if ($currentEventId != $row['event_id'] || $currentFacilityId != $row['facility_id'] || $currentRepeatRules != $row['repeat_rules']) {
                $recurIndex++;
                $row['start'] = $row['hour'];
                $row['end'] = $row['hour'];
                $recurrenceArray[$recurIndex] = $row;
                $recurrenceArray[$recurIndex]['facility_ids'][] = $row['facility_id'];
                $lastHour = $row['hour'];
                $currentEventId = $row['event_id'];
                $currentFacilityId = $row['facility_id'];
                $currentRepeatRules = $row['repeat_rules'];
                continue;
            }
            if ($row['hour'] <= ($lastHour + .25)) {
                if ($row['hour'] > $lastHour) {
                    $recurrenceArray[$recurIndex]["end"] = $row['hour'];
                }
            } else {
                $recurIndex++;
                $row['start'] = $row['hour'];
                $row['end'] = $row['hour'];
                $recurrenceArray[$recurIndex] = $row;
            }

            $facilityIds = $recurrenceArray[$recurIndex]['facility_ids'];
            if (empty($facilityIds) || !in_array($row['facility_id'], $facilityIds)) {
                $recurrenceArray[$recurIndex]['facility_ids'][] = $row['facility_id'];
            }

            $lastHour = $row['hour'];
            $currentEventId = $row['event_id'];
            $currentFacilityId = $row['facility_id'];
            $currentRepeatRules = $row['repeat_rules'];
        }
        while ($startDate <= $endDate) {
            foreach ($recurrenceArray as $repeatIndex => $row) {
                if (empty($row['repeat_rules'])) {
                    continue;
                }
                if (isInSchedule($startDate, $row['repeat_rules'])) {
                    $index++;
					$parameters['event_id'] = $row['event_id'];
					$events[$index] = self::getEventDetails($parameters);
					$events[$index]['start'] = date("c", strtotime($startDate . " " . self::getMilitaryTime($row['start'])));
					$events[$index]['end'] = date("c", strtotime($startDate . " " . self::getMilitaryTime($row['end'] + .25)));
                    $events[$index]['id'] = "repeat_rules_" . $repeatIndex . "_" . date("Ymd", strtotime($startDate));

                    foreach ($events[$index]['facility_ids'] as $facilityId) {
                        $events[$index]['facilities'][] = self::getFacilityDetails($facilityId);
                    }
                }
            }
            $startDate = date("Y-m-d", strtotime("+1 day", strtotime($startDate)));
        }
        $returnArray['total_events_count'] = count($events);
        $offset = empty($parameters['offset']) ? 0 : $parameters['offset'];
        $limit = empty($parameters['limit']) ? null : $parameters['limit'];
        $returnArray['events'] = array_slice($events, $offset, $limit);
        return $returnArray;
    }

	// Oppose to Events::getEvents this function return just one record for each event regardless if it spans across multiple days
	public static function getDistinctEvents($parameters) {
		$returnArray = array();
		$events = array();
		$locationIds = array();

		$parameters['wishlist_product_ids'] = array();
		if ($GLOBALS['gLoggedIn']) {
			$wishList = new WishList();
			foreach ($wishList->getWishListItems() as $wishListItem) {
				$parameters['wishlist_product_ids'][] = $wishListItem['product_id'];
			}
		}
		if (!empty($parameters['location_ids'])) {
			foreach (explode(",", $parameters['location_ids']) as $thisLocationId) {
				$locationId = getFieldFromId("location_id", "locations", "location_id", $thisLocationId, "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
				if (!empty($locationId)) {
					$locationIds[] = $locationId;
				}
			}
		}

		$eventTypeIds = array();
		$eventTypeTagGroupIds = array();
		$productTagIds = array();
		$excludeProductTagIds = array();
		$eventTypeCustomFields = array();

		if (!empty($parameters['event_type_ids'])) {
			foreach (explode(",", $parameters['event_type_ids']) as $thisEventTypeId) {
				$eventTypeId = getFieldFromId("event_type_id", "event_types", "event_type_id", $thisEventTypeId, "hide_in_calendar = 0 and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
				if (!empty($eventTypeId)) {
					$eventTypeIds[] = $eventTypeId;
				}
			}
		}
		if (!empty($parameters['event_type_tag_ids'])) {
			foreach (explode(",", $parameters['event_type_tag_ids']) as $thisEventTypeTagId) {
				$eventTypeTagRow = getRowFromId("event_type_tags", "event_type_tag_id", $thisEventTypeTagId,
					"inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
				if (!empty($eventTypeTagRow) && !empty($eventTypeTagRow['event_type_tag_group_id'])) {
					$eventTypeTagGroupIds[$eventTypeTagRow['event_type_tag_group_id']][] = $eventTypeTagRow['event_type_tag_id'];
				}
			}
		}
		if (!empty($parameters['product_tag_ids'])) {
			foreach (explode(",", $parameters['product_tag_ids']) as $thisProductTagId) {
				$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_id", $thisProductTagId,
					"inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
				if (!empty($productTagId)) {
					$productTagIds[] = $productTagId;
				}
			}
		}
		if (!empty($parameters['exclude_product_tags'])) {
			foreach (explode(",", $parameters['exclude_product_tags']) as $thisProductTag) {
				$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_id", $thisProductTag, "inactive = 0");
				if (empty($productTagId)) {
					$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", $thisProductTag, "inactive = 0");
				}
				if (!empty($productTagId)) {
					$excludeProductTagIds[] = $productTagId;
				}
			}
		}
		if (!empty($parameters['event_type_custom_fields'])) {
			$eventTypeCustomFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "EVENT_TYPES");
			foreach (explode(":", $parameters['event_type_custom_fields']) as $eventTypeCustomField) {
				$eventTypeCustomFieldParts = explode("=", $eventTypeCustomField);
				$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", $eventTypeCustomFieldParts[0],
					"custom_field_type_id = ? and client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"),
					$eventTypeCustomFieldTypeId, $GLOBALS['gClientId']);
				$customFieldValues = explode(",", $eventTypeCustomFieldParts[1]);

				if (!empty($customFieldId) && !empty($customFieldValues)) {
					$eventTypeCustomFields[$customFieldId] = array();
					foreach ($customFieldValues as $customFieldValue) {
						$eventTypeCustomFields[$customFieldId][] = makeParameter($customFieldValue);
					}
				}
			}
		}

		$eventsQuery = "select * from events where client_id = ?"
			. " and event_type_id in (select event_type_id from event_types where inactive = 0 and hide_in_calendar = 0)"
			. " and inactive = 0". ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0");
		$whereParameters = array($GLOBALS['gClientId']);

		if (!empty($parameters['start_date'])) {
			$eventsQuery .= " and start_date >= ?";
			$whereParameters[] = makeDateParameter($parameters['start_date']);
		}
		if (!empty($parameters['end_date'])) {
			$eventsQuery .= " and start_date <= ?";
			$whereParameters[] = makeDateParameter($parameters['end_date']);
		}
		if (!empty($parameters['weekend_events_only'])) {
			$eventsQuery .= " and dayofweek(start_date) in (1, 7)";
		}

		if (!empty($locationIds)) {
			$eventsQuery .= " and location_id in (" . implode(",", $locationIds) . ")";
		}
		if (!empty($eventTypeIds)) {
			$eventsQuery .= " and event_type_id in (" . implode(",", $eventTypeIds) . ")";
		}
		if (!empty($eventTypeTagGroupIds)) {
			foreach ($eventTypeTagGroupIds as $eventTypeTagIds) {
				$eventsQuery .= " and event_type_id in (select event_type_id from event_type_tag_links where event_type_tag_id in (" . implode(",", $eventTypeTagIds) . "))";
			}
		}
		if (!empty($productTagIds)) {
			$eventsQuery .= " and product_id in (select product_id from product_tag_links where product_tag_id in (" . implode(",", $productTagIds). ")"
				. " and (start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date > current_date))";
		}
		if (!empty($excludeProductTagIds)) {
			$eventsQuery .= " and product_id not in (select product_id from product_tag_links where product_tag_id in (" . implode(",", $excludeProductTagIds). ")"
				. " and (start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date > current_date))";
		}
		if (!empty($parameters['search_text'])) {
			$searchTextQuery = "";
			foreach (explode("|", $parameters['search_text']) as $searchText) {
				$searchTextQuery .= (empty($searchTextQuery) ? "" : " or ") . "description like " . makeParameter("%" . $searchText . "%");
			}
			$eventsQuery .= " and (" . $searchTextQuery . ")";
		}
		if (!empty($eventTypeCustomFields)) {
			foreach ($eventTypeCustomFields as $customFieldId => $customFieldValues) {
				$eventsQuery .= " and event_type_id in (select primary_identifier from custom_field_data where custom_field_id = " . $customFieldId
					. " and text_data in (" . implode(",", $customFieldValues) . "))";
			}
		}
		$eventsQuery .= " order by start_date, event_id";
		$resultSet = executeQuery($eventsQuery, $whereParameters);

		while ($row = getNextRow($resultSet)) {
			$parameters['event_id'] = $row['event_id'];
			$eventDetails = self::getEventDetails($parameters);
			$events[] = $eventDetails;
		}

		if (!empty($parameters['available_events_only'])) {
			$events = array_values(array_filter($events, function ($event) {
				return $event['spots_left'] > 0 && empty($event['product_completed']);
			}));
		}
		if (!empty($parameters['eligible_events_only'])) {
			$events = array_values(array_filter($events, function ($event) {
				return $event['qualified'] == true;
			}));
		}
		$returnArray['total_events_count'] = count($events);
		$offset = empty($parameters['offset']) ? 0 : $parameters['offset'];
		$limit = empty($parameters['limit']) ? null : $parameters['limit'];
		$returnArray['events'] = array_slice($events, $offset, $limit);

		// Processing after 1.) getting the total count and 2.) slicing the results given the offset and limit
		if (empty($parameters['exclude_facilities'])) {
			foreach ($returnArray['events'] as &$event) {
				$eventDateTimeResultSet = executeReadQuery("select min(date_needed) first_date, max(date_needed) last_date,"
					. " (select min(hour) from event_facilities where event_id = schedule.event_id and date_needed = (select min(date_needed) from event_facilities where event_id = schedule.event_id)) start_hour,"
					. " (select max(hour) from event_facilities where event_id = schedule.event_id and date_needed = (select max(date_needed) from event_facilities where event_id = schedule.event_id)) end_hour"
					. " from event_facilities schedule where event_id = ?", $event['event_id']);
				if ($eventDateTimeRow = getNextRow($eventDateTimeResultSet)) {
					$event['start'] = date("c", strtotime($eventDateTimeRow['first_date'] . " " . self::getMilitaryTime($eventDateTimeRow['start_hour'])));
					$event['end'] = date("c", strtotime($eventDateTimeRow['last_date'] . " " . self::getMilitaryTime($eventDateTimeRow['end_hour'] + .25)));
				}
				$event['facilities'] = array();
				$facilitiesResultSet = executeQuery("select distinct facility_id from event_facilities where event_id = ? order by facility_id", $event['event_id']);
				while ($facilityRow = getNextRow($facilitiesResultSet)) {
					$event['facilities'][] = self::getFacilityDetails($facilityRow['facility_id']);
				}
			}
		}
		return $returnArray;
	}

    public static function getEventDetails($parameters) {
		$eventRow = getRowFromId("events", "event_id", $parameters['event_id']);
		$productId = $eventRow['product_id'];
        $displayColor = getFieldFromId("display_color", "event_types", "event_type_id", $eventRow['event_type_id']);

		$eventDetails = array();
		foreach (array("event_id", "description", "detailed_description", "location_id", "product_id", "event_type_id",
					 "cost", "date_created", "link_name", "link_url") as $fieldName) {
			$eventDetails[$fieldName] = $eventRow[$fieldName];
		}

        if (!empty($displayColor)) {
            $eventDetails['backgroundColor'] = $displayColor;
        }
        if (!empty($parameters['eligible_events_only'])) {
            $eventDetails['qualified'] = self::isQualified(array("event_id" => $parameters['event_id']));
        }

        if (!empty($productId)) {
            $productRow = ProductCatalog::getCachedProductRow($productId);
            if (!empty($productRow['image_id'])) {
                $eventDetails['product_image_filename'] = getImageFilename($productRow['image_id'], array("use_cdn" => true));
            }
            $productPrice = getFieldFromId("price", "product_prices", "product_id", $productId,
                "product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'SALE_PRICE')" .
                " and location_id is null and (start_date is null or start_date <= current_date) and (end_date is null or end_date >= current_date)");
            $eventDetails['product_price'] = $productPrice;
			$eventDetails['product_list_price'] = $productRow['list_price'];

            $eventDetails['product_description'] = htmlText($productRow['description']);
            $eventDetails['product_completed'] = $eventRow['start_date'] < date("Y-m-d") || !empty($productRow['inactive']);

            $attendeeCounts = Events::getAttendeeCounts($parameters['event_id']);
            $spotsLeft = $attendeeCounts['attendees'] - $attendeeCounts['registrants'];
            $eventDetails['attendees'] = $attendeeCounts['attendees'];
            $eventDetails['registrants'] = $attendeeCounts['registrants'];
			$eventDetails['spots_left'] = max($spotsLeft, 0);
            $eventDetails['in_wishlist'] = in_array($productId, $parameters['wishlist_product_ids']);
        }
        return $eventDetails;
    }

    private static function getFacilityDetails($facilityId) {
        $facilityRow = getRowFromId("facilities", "facility_id", $facilityId);
        $facilityDetails = array(
            "facility_id" => $facilityRow['facility_id'],
            "description" => htmlText($facilityRow['description'])
        );

        if (!empty($facilityRow['location_id'])) {
            $locationRow = getRowFromId("locations", "location_id", $facilityRow['location_id']);
            $contactRow = Contact::getContact($locationRow['contact_id']);

            $locationFullAddress = empty($contactRow['address_1']) ? "" : $contactRow['address_1'];
            if (!empty($contactRow['city'])) {
                $locationFullAddress .= (empty($locationFullAddress) ? "" : ", ") . $contactRow['city'];
            }
            if (!empty($contactRow['state'])) {
                $locationFullAddress .= (empty($locationFullAddress) ? "" : ", ") . $contactRow['state'];
            }
            if (!empty($contactRow['postal_code'])) {
                $locationFullAddress .= (empty($locationFullAddress) ? "" : ", ") . $contactRow['postal_code'];
            }
            $facilityDetails['location'] = array(
                "location_id" => $locationRow['location_id'],
                "location_code" => $locationRow['location_code'],
                "description" => htmlText($locationRow['description']),
                "longitude" => $contactRow['longitude'],
                "latitude" => $contactRow['latitude'],
                "address_1" => htmlText($contactRow['address_1']),
                "city" => $contactRow['city'],
                "state" => $contactRow['state'],
                "postal_code" => $contactRow['postal_code'],
                "full_address" => htmlText($locationFullAddress)
            );
        }
        return $facilityDetails;
    }

	public static function createCertifications($contactId) {
		$resultSet = executeQuery("select *,(select group_concat(event_type_id) from certification_type_requirements where certification_type_id = certification_types.certification_type_id) event_type_ids " .
			"from certification_types where client_id = ? and inactive = 0 and certification_type_id in (select certification_type_id from certification_type_requirements)", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$contactCertificationId = getFieldFromId("contact_certification_id", "contact_certifications", "contact_id", $contactId,
				"certification_type_id = ? and (expiration_date is null or expiration_date > current_date)", $row['certification_type_id']);
			if (!empty($contactCertificationId)) {
				continue;
			}
			$certificationSet = executeQuery("select * from contact_certifications where contact_id = ? and expiration_date is not null order by expiration_date desc", $contactId);
			$certificationRow = getNextRow($certificationSet);
			$eventTypeIds = explode(",", $row['event_type_ids']);
			$foundAll = true;
			$foundOne = false;
			foreach ($eventTypeIds as $eventTypeId) {
				$contactEventTypeId = getFieldFromId("contact_event_type_id", "contact_event_types", "contact_id", $contactId,
					"event_type_id = ? and failed = 0 and date_completed " . (empty($certificationRow['expiration_date']) ? "is not null" : ">" . makeDateParameter($certificationRow['expiration_date'])), $eventTypeId);
				if (empty($contactEventTypeId)) {
					$foundAll = false;
					if (empty($row['any_requirement'])) {
						break;
					}
				} else {
					$foundOne = true;
					if (!empty($row['any_requirement'])) {
						break;
					}
				}
			}
			if ($foundAll || (!empty($row['any_requirement']) && $foundOne)) {
				executeQuery("insert into contact_certifications (contact_id,certification_type_id,date_issued,expiration_date) values (?,?,?,?)",
					$contactId, $row['certification_type_id'], date("Y-m-d"), (empty($row['months_between']) ? "" : date("Y-m-d", strtotime("+" . $row['months_between'] . " months"))));
			}
		}
	}

}
