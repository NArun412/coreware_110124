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

$GLOBALS['gPageCode'] = "EVENTSCHEDULER";
require_once "shared/startup.inc";

class EventSchedulerPage extends Page {

	var $iHappeningNowSegments = 6;
	var $iHappeningNowRowHeight = 50;
	var $iHideVisitorSidebar = false;

	function setup() {
		$valuesArray = Page::getPagePreferences();
		$this->iHappeningNowSegments = $valuesArray['segment_count'];
		if (empty($this->iHappeningNowSegments) || !is_numeric($this->iHappeningNowSegments) || $this->iHappeningNowSegments < 6) {
			$this->iHappeningNowSegments = 6;
		}
		$this->iHappeningNowRowHeight = $valuesArray["row_height"];
		if (empty($this->iHappeningNowRowHeight) || !is_numeric($this->iHappeningNowRowHeight) || $this->iHappeningNowRowHeight < 20) {
			$this->iHappeningNowRowHeight = 50;
		}
		$this->iHideVisitorSidebar = (!empty($valuesArray["hide_visitor_sidebar"]));
	}

	function sortFacilities($a, $b) {
		if ($a['sort_total'] == $b['sort_total']) {
			if ($a['description'] == $b['description']) {
				return 0;
			}
			return ($a['description'] < $b['description']) ? -1 : 1;
		}
		return ($a['sort_total'] < $b['sort_total']) ? 1 : -1;
	}

	function headerIncludes() {
		?>
        <link type="text/css" rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.2.0/fullcalendar.min.css"/>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.17.1/moment.min.js"></script>
        <script src="//cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.2.0/fullcalendar.min.js"></script>
        <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/fullcalendar.css') ?>"/>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "set_wait_time":
				$returnArray['console'] = $_GET;
				updateFieldById("estimated_minutes", $_GET['estimated_minutes'], "visitor_log", "visitor_log_id", $_GET['visitor_log_id']);
				ajaxResponse($returnArray);
				break;
			case "get_custom_contact_data":
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
				$eventId = getFieldFromId("event_id", "events", "event_id", $_GET['event_id']);
				if (!empty($contactId)) {
					if (function_exists("_localEventSchedulerContactData")) {
						$returnValue = _localEventSchedulerContactData($contactId, $eventId);
						if ($returnValue !== false) {
							$returnArray['custom_contact_data'] = $returnValue;
						}
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_preferences":
				$returnArray = Page::getPagePreferences();
				ajaxResponse($returnArray);
				break;
			case "save_preferences":
				$checkboxValues = array("group_by_facility_type", "hide_visitor_sidebar", "ignore_usage_sort");
				foreach ($checkboxValues as $thisValue) {
					if (!array_key_exists($thisValue, $_POST)) {
						$_POST[$thisValue] = "0";
					}
				}
				$valuesArray = Page::getPagePreferences();
				foreach ($_POST as $fieldName => $fieldValue) {
					$valuesArray[$fieldName] = $fieldValue;
				}
				Page::setPagePreferences($valuesArray);
			case ("get_contact_info"):
				$contactId = "";
				$resultSet = executeQuery("select contact_id,first_name,last_name,business_name,email_address from contacts where contact_id = ? and client_id = ?", $_GET['contact_id'], $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					if (empty($row['country_id'])) {
						$row['country_id'] = "1000";
					}
					$contactId = $row['contact_id'];
					$returnArray['event'] = array("contact_id" => $row['contact_id'], "first_name" => $row['first_name'], "last_name" => $row['last_name'], "business_name" => $row['business_name'], "email_address" => $row['email_address'],
						"country_id" => $row['country_id']);
				} else {
					$returnArray['error_message'] = "Contact not found";
				}
				$phoneNumberControl = new DataColumn("phone_numbers");
				$phoneNumberControl->setControlValue("data_type", "custom_control");
				$phoneNumberControl->setControlValue("control_class", "EditableList");
				$phoneNumberControl->setControlValue("primary_table", "contacts");
				$phoneNumberControl->setControlValue("list_table", "phone_numbers");
				$customControl = new EditableList($phoneNumberControl, $this);
				$returnArray = array_merge($returnArray, $customControl->getRecord($contactId));
				ajaxResponse($returnArray);
				break;
			case ("add_event"):
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					$facilityId = $_POST['facility_id'];
					$facilityTypeId = getFieldFromId("facility_type_id", "facilities", "facility_id", $facilityId);
					$facilityRow = getRowFromId("facilities", "facility_id", $facilityId);
					$startDate = strtotime($_POST['start']);
					if (!empty($_POST['recurring']) && !empty($_POST['recurring_start_date'])) {
						$startDate = strtotime($_POST['recurring_start_date']);
					}
					$endDate = strtotime($_POST['end']);
					if (!empty($_POST['recurring'])) {
						$endDate = strtotime($_POST['until']);
					}
					$dateNeeded = date("Y-m-d", $startDate);

					$startHour = $_POST['start_time'];
					$endHour = $_POST['end_time'];
					if ($endHour < 0) {
						$endHour = 23.75;
					}
					$repeatRules = Events::makeRepeatRules($_POST);

					if (empty($_POST['ignore_conflicts'])) {
						if (empty($_POST['recurring'])) {
							$existingEventInfo = Events::getEventForTime($dateNeeded, $startHour, $endHour, $facilityId);
							if ($existingEventInfo !== false) {
								$returnArray['conflict'] = "Conflict found: " . $existingEventInfo['conflict_description'];
								ajaxResponse($returnArray);
								break;
							}
						} else {
							$existingEventInfo = Events::getEventForRecurringTime($repeatRules, $startHour, $endHour, $facilityId);
							if ($existingEventInfo !== false) {
								$returnArray['conflict'] = "Conflict found: " . $existingEventInfo['conflict_description'];
								ajaxResponse($returnArray);
								break;
							}
						}
					}

					$description = $_POST['description'];
					if (empty($description)) {
						$description = getFieldFromId("description", "event_types", "event_type_id", $_POST['event_type_id']);
					}
					$firstName = $_POST['first_name'];
					$lastName = $_POST['last_name'];
					$businessName = $_POST['business_name'];
					$emailAddress = $_POST['email_address'];
					$contactId = "";
					$this->iDatabase->startTransaction();
					if (empty($_POST['contact_id'])) {
						if (!empty($firstName) || !empty($lastName) || !empty($businessName) || !empty($emailAddress)) {
							$resultSet = executeQuery("select contact_id from contacts where client_id = ? and first_name <=> ? and " .
								"last_name <=> ? and business_name <=> ? and email_address <=> ?",
								$GLOBALS['gClientId'], $firstName, $lastName, $businessName, $emailAddress);
							if ($row = getNextRow($resultSet)) {
								$contactId = $row['contact_id'];
							} else {
								$sourceId = getFieldFromId("source_id", "sources", "source_code", "VISITOR");
								if (empty($sourceId)) {
									$resultSet = executeQuery("insert into sources (client_id,source_code,description) values (?,'VISITOR','Visitor')", $GLOBALS['gClientId']);
									$sourceId = $resultSet['insert_id'];
								}
								$contactDataTable = new DataTable("contacts");
								if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $firstName, "last_name" => $lastName,
									"business_name" => $businessName, "email_address" => $emailAddress, "source_id" => $sourceId)))) {
									$returnArray['error_message'] = "An error occurred and the event cannot be added";
									$this->iDatabase->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
							}
						}
					} else {
						$contactId = $_POST['contact_id'];
						$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $contactId);
					}
					if (!empty($contactId)) {
						$phoneNumberControl = new DataColumn("phone_numbers");
						$phoneNumberControl->setControlValue("data_type", "custom_control");
						$phoneNumberControl->setControlValue("control_class", "EditableList");
						$phoneNumberControl->setControlValue("primary_table", "contacts");
						$phoneNumberControl->setControlValue("list_table", "phone_numbers");
						$customControl = new EditableList($phoneNumberControl, $this);
						$customControl->setPrimaryId($contactId);
						if ($customControl->saveData($_POST) !== true) {
							$returnArray['error_message'] = $customControl->getErrorMessage();
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
					$taskSourceId = getFieldFromId("task_source_id", "task_sources", "task_source_code", "FACILITY_RESERVATION", "client_id = ?", $GLOBALS['gClientId']);
					if (!empty($contactId) && !empty($taskSourceId)) {
						executeQuery("insert into tasks (client_id,contact_id,description,date_completed,simple_contact_task,task_source_id) values " .
							"(?,?,'Reserved Facility',now(),1,?)", $GLOBALS['gClientId'], $contactId, $taskSourceId);
					}
					$resultSet = executeQuery("insert into events (client_id,description,detailed_description,event_type_id,contact_id,start_date,end_date,cost,user_id,date_created,attendees,no_statistics) values (?,?,?,?,?,?,?,0,?,now(),?,?)",
						$GLOBALS['gClientId'], $description, $_POST['detailed_description'], $_POST['event_type_id'], $contactId, date("Y-m-d", $startDate), (empty($endDate) ? "" : date("Y-m-d", $endDate)), $GLOBALS['gUserId'], $_POST['attendees'], ($_POST['no_statistics'] ? 1 : 0));
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = "An error occurred and the event cannot be added";
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$eventId = $resultSet['insert_id'];
					$firstEventFacilityId = "";
					$eventFacilityIds = "";
					for ($x = $startHour; $x <= $endHour; $x += .25) {
						if (empty($_POST['recurring'])) {
							$resultSet = executeQuery("select * from event_facilities where event_id = ? and facility_id = ? and date_needed = ? and hour = ?",
								$eventId, $facilityId, $dateNeeded, $x);
							if (!$row = getNextRow($resultSet)) {
								$resultSet = executeQuery("insert into event_facilities (event_id,facility_id,date_needed,hour) values (?,?,?,?)",
									$eventId, $facilityId, $dateNeeded, $x);
								if (!empty($resultSet['sql_error'])) {
									$returnArray['error_message'] = "An error occurred and the event cannot be added";
									$this->iDatabase->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
								if (empty($firstEventFacilityId)) {
									$firstEventFacilityId = $resultSet['insert_id'];
								}
								$eventFacilityIds .= (empty($eventFacilityIds) ? "" : ",") . $resultSet['insert_id'];
							}
						} else {
							$resultSet = executeQuery("insert into event_facility_recurrences (event_id,facility_id,repeat_rules,hour) values (?,?,?,?)",
								$eventId, $facilityId, $repeatRules, $x);
							$eventFacilityIds .= (empty($eventFacilityIds) ? "" : ",") . $resultSet['insert_id'];
							if (empty($firstEventFacilityId)) {
								$firstEventFacilityId = "recurring_" . $resultSet['insert_id'];
							}
						}
					}
					$resultSet = executeQuery("select * from event_requirements where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						if (!empty($_POST['event_requirement_id_' . $row['event_requirement_id']])) {
							executeQuery("insert into event_requirement_data (event_id,event_requirement_id,notes) values (?,?,?)",
								$eventId, $row['event_requirement_id'], $_POST['event_requirement_notes_' . $row['event_requirement_id']]);
						}
					}
					$this->iDatabase->commitTransaction();

					$substitutions = $_POST;
					$substitutions['approval'] = ($facilityRow['requires_approval'] ? " though it will require approval" : "");

					if (empty($startHour) && $endHour == 23.75) {
						$displayTime = "All Day";
					} else {
						$workingHour = floor($startHour);
						$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
						$displayMinutes = ($startHour - $workingHour) * 60;
						$displayAmpm = ($startHour == 0 ? "midnight" : ($startHour == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
						$displayTime = $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . $displayAmpm;
						$workingHour = floor($endHour + .25);
						$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
						$displayMinutes = ($endHour + .25 - $workingHour) * 60;
						$displayAmpm = ($endHour == 23.75 ? "midnight" : ($endHour == 11.75 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
						$displayTime .= "-" . $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . $displayAmpm;
					}

					$substitutions['display_time'] = $displayTime;
					$substitutions['facility_description'] = getFieldFromId("description", "facilities", "facility_id", $facilityId);
					if (empty($substitutions['phone_number'])) {
						$substitutions['phone_number'] = "";
					}
					$substitutions['recurring'] = (empty($_POST['recurring']) ? "" : "This event is recurring");
					if (empty($substitutions['number_people'])) {
						$substitutions['number_people'] = 1;
					}

					if (empty($this->getPageTextChunk("NO_CONFIRMATION_EMAIL"))) {
						$emailId = getFieldFromId("email_id", "emails", "email_code", "RESERVATION_CONFIRMATION",  "inactive = 0");
						$subject = "Room Reservation";
						$body = "<p>A room has been reserved%approval%. Here are the details:</p><p>First Name:&nbsp;%first_name%<br />Last Name: %last_name%<br />" .
							"Email: %email_address%<br />Phone: %phone_number%<br />Event Description: %description%<br />" .
							"Room: %facility_description%<br />Comments: %detailed_description%<br />" .
							"Date Needed: %date_needed%<br />Time: %display_time%" . (empty($_POST['recurring']) ? "" : "<br />This event is recurring") . "</p>";
						$emailAddresses = array();
						if (!empty($emailAddress)) {
							$emailAddresses[] = $emailAddress;
						}
						$resultSet = executeQuery("select * from facility_notifications where facility_id = ?", $facilityId);
						while ($row = getNextRow($resultSet)) {
							$emailAddresses[] = $row['email_address'];
						}
						$resultSet = executeQuery("select * from facility_type_notifications where facility_type_id = ?", $facilityTypeId);
						while ($row = getNextRow($resultSet)) {
							$emailAddresses[] = $row['email_address'];
						}
						if (!empty($emailAddresses)) {
							sendEmail(array("email_id" => $emailId, "body" => $body, "subject" => $subject, "substitutions" => $substitutions, "email_addresses" => $emailAddresses));
						}
					}

					Events::sendEventNotifications($eventId);
					$contact = (empty($contactId) ? "" : getDisplayName($contactId));
					$title = (empty($contact) ? "" : $contact . " - ") . $description;
					$returnArray['new_event'] = array("id" => $firstEventFacilityId, "event_id" => $eventId, "ids" => $eventFacilityIds, "title" => $title, "color" => (empty($_POST['recurring']) ? "rgb(51,102,204)" : "rgb(0,128,0)"),
						"start" => date("c", strtotime($_POST['start'])), "end" => date("c", strtotime((empty($_POST['end']) ? $_POST['start'] : $_POST['end']))), "editable" => $GLOBALS['gPermissionLevel'] > _READONLY, "recurring" => (!empty($_POST['recurring'])));
				}
				ajaxResponse($returnArray);
				break;
			case ("delete_event"):
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					$eventId = getFieldFromId("event_id", "events", "event_id", $_POST['event_id']);
					if (empty($eventId)) {
						$returnArray['error_message'] = "Invalid Event";
						ajaxResponse($returnArray);
						break;
					}
					$GLOBALS['gPrimaryDatabase']->startTransaction();
					$resultSet = executeQuery("select distinct facility_id,date_needed from event_facilities where event_id = ?", $_POST['event_id']);
					$count = $resultSet['row_count'];
					$resultSet = executeQuery("select distinct facility_id,repeat_rules from event_facility_recurrences where event_id = ?", $_POST['event_id']);
					$repeatCount = $resultSet['row_count'];
					if (empty($_POST['old_ids']) && ($count + $repeatCount) == 1) {
						$_POST['old_ids'] = "";
						$resultSet = executeQuery("select * from " . ($_POST['recurring'] ? "event_facility_recurrences" : "event_facilities") . " where event_id = ?", $_POST['event_id']);
						while ($row = getNextRow($resultSet)) {
							$_POST['old_ids'] .= (empty($_POST['old_ids']) ? "" : ",") . $row[($_POST['recurring'] ? "event_facility_recurrence_id" : "event_facility_id")];
						}
					}
					if (!$_POST['recurring']) {
						if (!empty($_POST['old_ids'])) {
							executeQuery("delete from event_facilities where event_id = ? and event_facility_id in (" . $_POST['old_ids'] . ")", $eventId);
							$deleteEventId = getFieldFromId("event_id", "events", "event_id", $eventId,
								"event_id not in (select event_id from event_facilities where event_id = ?) and event_id not in (select event_id from event_facility_recurrences where event_id = ?)", $eventId, $eventId);
							if (!empty($deleteEventId)) {
								executeQuery("delete from event_requirement_data where event_id = ?", $eventId);
								$dataTable = new DataTable("events");
								if (!$dataTable->deleteRecord(array("primary_id" => $eventId))) {
									$returnArray['error_message'] = "Unable to delete event from here. Use Event Maintenance";
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
							}
							$GLOBALS['gPrimaryDatabase']->commitTransaction();
						}
					} else {
						$oldIds = explode(",", $_POST['old_ids']);
						$facilityId = $_POST['facility_id'];
						$idList = "";
						$firstId = "";
						foreach ($oldIds as $oldId) {
							$oldId = getFieldFromId("event_facility_recurrence_id", "event_facility_recurrences", "event_facility_recurrence_id", $oldId);
							if (!empty($oldId)) {
								if (!empty($idList)) {
									$idList .= ",";
								}
								$idList .= $oldId;
								if (empty($firstId)) {
									$firstId = $oldId;
								}
							}
						}
						if (!empty($firstId)) {
							$repeatRules = getFieldFromId("repeat_rules", "event_facility_recurrences", "event_facility_recurrence_id", $firstId);
							$parts = parseNameValues($repeatRules);
							$startDate = date("Y-m-d", strtotime($parts['start_date']));
							$endDate = date("Y-m-d", strtotime('-1 day', strtotime($_POST['instance_date'])));
							if ($endDate < $startDate) {
								executeQuery("delete from event_facility_recurrences where event_facility_recurrence_id in ($idList) and facility_id = ? and event_id = ?", $facilityId, $eventId);
							} else {
								$resultSet = executeQuery("select * from event_facility_recurrences where event_facility_recurrence_id = ? and facility_id = ? and event_id = ?", $firstId, $facilityId, $eventId);
								if ($row = getNextRow($resultSet)) {
									$repeatRules = Events::assembleRepeatRules(parseNameValues($row['repeat_rules'] . ($_POST['all_future'] ? "UNTIL=" . date("m/d/Y", strtotime('-1 day', strtotime($_POST['instance_date']))) : "NOT=" . $_POST['instance_date']) . ";"));
									executeQuery("update event_facility_recurrences set repeat_rules = ? where event_facility_recurrence_id in ($idList) and facility_id = ? and event_id = ?", $repeatRules, $facilityId, $eventId);
								}
							}
						}
						$deleteEventId = getFieldFromId("event_id", "events", "event_id", $eventId,
							"event_id not in (select event_id from event_facilities where event_id = ?) and event_id not in (select event_id from event_facility_recurrences where event_id = ?)", $eventId, $eventId);
						if (!empty($deleteEventId)) {
							executeQuery("delete from event_requirement_data where event_id = ?", $eventId);
							$dataTable = new DataTable("events");
							$dataTable->deleteRecord(array("primary_id" => $eventId));
						}
						$GLOBALS['gPrimaryDatabase']->commitTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
			case ("event_drag"):
			case ("event_drop"):
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					$oldIds = explode(",", $_POST['old_ids']);
					$facilityId = $_POST['facility_id'];
					$eventId = $_POST['event_id'];

					foreach ($oldIds as $oldId) {
						$resultSet = executeQuery("delete from event_facilities where event_facility_id = ? and facility_id = ? and event_id = ?", $oldId, $facilityId, $eventId);
					}
					if (array_key_exists("start", $_POST)) {
						$startDate = strtotime($_POST['start']);
						$endDate = strtotime($_POST['end']);

						$dateNeeded = date("Y-m-d", $startDate);
						$startHour = (date("H", $startDate)) + (date("i", $startDate) / 60);
						$endHour = (date("H", $endDate)) + (date("i", $endDate) / 60) - .25;

						if ($endHour < 0) {
							$endHour = 23.75;
						}
						$newIds = "";
						for ($x = $startHour; $x <= $endHour; $x += .25) {
							$resultSet = executeQuery("select * from event_facilities where event_id = ? and facility_id = ? and date_needed = ? and hour = ?",
								$eventId, $facilityId, $dateNeeded, $x);
							if (!$row = getNextRow($resultSet)) {
								$insertSet = executeQuery("insert into event_facilities (event_id,facility_id,date_needed,hour) values (?,?,?,?)",
									$eventId, $facilityId, $dateNeeded, $x);
								$newIds .= (empty($newIds) ? "" : ",") . $insertSet['insert_id'];
							}
						}
						$returnArray['ids'] = $newIds;
					} else {
						executeQuery("delete from event_requirement_data where event_id = ?", $eventId);
						$deleteEventId = getFieldFromId("event_id", "events", "event_id", $eventId,
							"event_id not in (select event_id from event_facilities where event_id = ?) and event_id not in (select event_id from event_facility_recurrences where event_id = ?)", $eventId, $eventId);
						if (!empty($deleteEventId)) {
							$dataTable = new DataTable("events");
							$dataTable->deleteRecord(array("primary_id" => $eventId));
						}
					}
				}
				ajaxResponse($returnArray);
				break;
			case ("update_event"):
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					$this->iDatabase->startTransaction();
					$resultSet = executeQuery("select event_id,description,detailed_description,event_type_id,first_name,last_name,business_name,email_address,attendees,no_statistics,events.contact_id from events " .
						"left outer join contacts on events.contact_id = contacts.contact_id where event_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and events.client_id = ?", $_POST['event_id'], $GLOBALS['gClientId']);
					if ($eventRow = getNextRow($resultSet)) {
						if (empty($eventRow['contact_id']) || $eventRow['first_name'] != $_POST['first_name']
							|| $eventRow['last_name'] != $_POST['last_name'] || $eventRow['business_name'] != $_POST['business_name']) {
							if (!empty($_POST['first_name']) || !empty($_POST['last_name']) || !empty($_POST['business_name']) || !empty($_POST['email_address'])) {
								$resultSet = executeQuery("select contact_id from contacts where client_id = ? and first_name <=> ? and last_name <=> ? and business_name <=> ? and email_address <=> ?",
									$GLOBALS['gClientId'], $_POST['first_name'], $_POST['last_name'], $_POST['business_name'], $_POST['email_address']);
								if ($row = getNextRow($resultSet)) {
									$contactId = $row['contact_id'];
								} else {
									$sourceId = getFieldFromId("source_id", "sources", "source_code", "VISITOR");
									if (empty($sourceId)) {
										$resultSet = executeQuery("insert into sources (client_id,source_code,description) values (?,'VISITOR','Visitor')", $GLOBALS['gClientId']);
										$sourceId = $resultSet['insert_id'];
									}
									$contactDataTable = new DataTable("contacts");
									if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'],
										"business_name" => $_POST['business_name'], "email_address" => $_POST['email_address'], "source_id" => $sourceId)))) {
										$returnArray['error_message'] = "An error occurred and the event cannot be updated";
										$this->iDatabase->rollbackTransaction();
										ajaxResponse($returnArray);
										break;
									}
								}
							} else {
								$contactId = "";
							}
						} else {
							$contactId = $eventRow['contact_id'];
							if ($eventRow['email_address'] != $_POST['email_address']) {
								$resultSet = executeQuery("update contacts set email_address = ? where contact_id = ?",
									$_POST['email_address'], $contactId);
								if (!empty($resultSet['sql_error'])) {
									$returnArray['error_message'] = "An error occurred and the event cannot be updated";
									$this->iDatabase->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
							}
						}
						$taskSourceId = getFieldFromId("task_source_id", "task_sources", "task_source_code", "FACILITY_RESERVATION", "client_id = ?", $GLOBALS['gClientId']);
						if (!empty($contactId) && !empty($taskSourceId)) {
							executeQuery("insert into tasks (client_id,contact_id,description,date_completed,simple_contact_task,task_source_id) values " .
								"(?,?,'Reserved Facility',now(),1,?)", $GLOBALS['gClientId'], $contactId, $taskSourceId);
						}
						$resultSet = executeQuery("update events set description = ?,detailed_description = ?,event_type_id = ?,attendees = ?,no_statistics = ?,contact_id = ? where event_id = ? and client_id = ?",
							$_POST['description'], $_POST['detailed_description'], $_POST['event_type_id'], $_POST['attendees'], ($_POST['no_statistics'] ? 1 : 0), $contactId, $_POST['event_id'], $GLOBALS['gClientId']);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = "An error occurred and the event cannot be updated";
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						if (!empty($contactId)) {
							$phoneNumberControl = new DataColumn("phone_numbers");
							$phoneNumberControl->setControlValue("data_type", "custom_control");
							$phoneNumberControl->setControlValue("control_class", "EditableList");
							$phoneNumberControl->setControlValue("primary_table", "contacts");
							$phoneNumberControl->setControlValue("list_table", "phone_numbers");
							$customControl = new EditableList($phoneNumberControl, $this);
							$customControl->setPrimaryId($contactId);
							if ($customControl->saveData($_POST) !== true) {
								$returnArray['error_message'] = $customControl->getErrorMessage();
								$this->iDatabase->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
						}
						$resultSet = executeQuery("select distinct facility_id,date_needed from event_facilities where event_id = ?", $_POST['event_id']);
						$count = $resultSet['row_count'];
						$resultSet = executeQuery("select distinct facility_id,repeat_rules from event_facility_recurrences where event_id = ?", $_POST['event_id']);
						$repeatCount = $resultSet['row_count'];
						if ($count == 1 && $repeatCount == 0) {
							if (empty($_POST['ignore_conflicts'])) {
								$existingEventInfo = Events::getEventForTime($_POST['date_needed'], $_POST['start_time'], $_POST['end_time'], $_POST['facility_id'], $_POST['event_id']);
								if ($existingEventInfo !== false) {
									$returnArray['conflict'] = "Conflict found: " . $existingEventInfo['conflict_description'];
									ajaxResponse($returnArray);
									break;
								}
							}

							executeQuery("update events set start_date = ?,end_date = ? where event_id = ?", date("Y-m-d", strtotime($_POST['date_needed'])), date("Y-m-d", strtotime($_POST['date_needed'])), $_POST['event_id']);

							executeQuery("delete from event_facilities where event_id = ?", $_POST['event_id']);

							$thisTime = $_POST['start_time'];
							while ($thisTime <= $_POST['end_time']) {
								$resultSet = executeQuery("insert into event_facilities (event_id,facility_id,date_needed,hour) values (?,?,?,?)",
									$_POST['event_id'], $_POST['facility_id'], date("Y-m-d", strtotime($_POST['date_needed'])), $thisTime);
								if (!empty($resultSet['sql_error'])) {
									$returnArray['error_message'] = "An error occurred and the event cannot be updated";
									$this->iDatabase->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
								$thisTime += .25;
							}
						}
						$resultSet = executeQuery("select * from event_requirements where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							if (!empty($_POST['event_requirement_id_' . $row['event_requirement_id']])) {
								$eventRequirementDataId = getFieldFromId("event_requirement_data_id", "event_requirement_data", "event_id", $_POST['event_id'], "event_requirement_id = ?", $row['event_requirement_id']);
								if (empty($eventRequirementDataId)) {
									executeQuery("insert into event_requirement_data (event_id,event_requirement_id,notes) values (?,?,?)",
										$_POST['event_id'], $row['event_requirement_id'], $_POST['event_requirement_notes_' . $row['event_requirement_id']]);
								} else {
									executeQuery("update event_requirement_data set notes = ? where event_requirement_data_id = ?", $_POST['event_requirement_notes_' . $row['event_requirement_id']], $eventRequirementDataId);
								}
							} else {
								executeQuery("delete from event_requirement_data where event_id = ? and event_requirement_id = ?", $_POST['event_id'], $row['event_requirement_id']);
							}
						}
					} else {
						$returnArray['error_message'] = "Event not found";
					}
					Events::sendEventNotifications($_POST['event_id']);
					$this->iDatabase->commitTransaction();
				}
				$contact = (empty($contactId) ? "" : getDisplayName($contactId));
				$returnArray['display_description'] = (empty($contact) ? "" : $contact . " - ") . $_POST['description'];
				ajaxResponse($returnArray);
				break;
			case ("get_event_details"):
				$resultSet = executeQuery("select event_id,events.contact_id,description,detailed_description,events.user_id,events.date_created,events.start_date,event_type_id,first_name,last_name,business_name,email_address,attendees,no_statistics,events.notes from events " .
					"left outer join contacts on events.contact_id = contacts.contact_id where event_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and events.client_id = ?", $_GET['event_id'], $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					if (empty($row['country_id'])) {
						$row['country_id'] = "1000";
					}
					$contactId = $row['contact_id'];
					$returnArray['event'] = array("event_id" => $row['event_id'], "description" => $row['description'],
						"detailed_description" => $row['detailed_description'], "event_type_id" => $row['event_type_id'], "first_name" => $row['first_name'],
						"last_name" => $row['last_name'], "business_name" => $row['business_name'], "email_address" => $row['email_address'], "notes" => $row['notes'],
						"country_id" => $row['country_id'], "attendees" => $row['attendees'], "no_statistics" => $row['no_statistics'], "contact_id" => $row['contact_id'],
						"date_created" => date("m/d/Y", strtotime($row['date_created'])), "date_needed" => date("m/d/Y", strtotime($row['start_date'])), "user_id_display" => getUserDisplayName($row['user_id']));
				} else {
					$returnArray['error_message'] = "Event not found";
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("select distinct facility_id,date_needed from event_facilities where event_id = ?", $_GET['event_id']);
				$returnArray['event']['facility_count'] = $count = $resultSet['row_count'];
				$resultSet = executeQuery("select distinct facility_id,repeat_rules from event_facility_recurrences where event_id = ?", $_GET['event_id']);
				$returnArray['event']['facility_repeat_count'] = $repeatCount = $resultSet['row_count'];
				# total > 1 && repeat > 0
				if (($count + $repeatCount) > 1 && $repeatCount > 0) {
					$returnArray['hide_recurring'] = true;
					$facilityId = getFieldFromId("facility_id", "facilities", "facility_id", $_GET['facility_id']);
					$resultSet = executeQuery("select min(hour),max(hour) from event_facilities where event_id = ?" . (empty($facilityId) ? "" : " and facility_id = " . $facilityId), $_GET['event_id']);
					if ($row = getNextRow($resultSet)) {
						$returnArray['event']['start_time'] = round($row['min(hour)'], 2);
						$returnArray['event']['end_time'] = round($row['max(hour)'], 2);
					}
					# total = 1 && repeat > 0 == repeatcount = 1
				} else if ($repeatCount > 0) {
					$resultSet = executeQuery("select * from event_facility_recurrences where event_id = ?", $_GET['event_id']);
					if ($row = getNextRow($resultSet)) {
						$returnArray['event']['recurring'] = 1;
						$parts = parseNameValues($row['repeat_rules']);
						foreach ($parts as $fieldName => $fieldValue) {
							$returnArray['event'][strtolower($fieldName)] = $fieldValue;
						}
						$returnArray['event']['recurring_start_date'] = $parts['start_date'];
						if (!empty($parts['bymonth'])) {
							$pieces = explode(",", $parts['bymonth']);
							foreach ($pieces as $thisPiece) {
								$returnArray['event']['bymonth_' . $thisPiece] = 1;
							}
						}
						if (!empty($parts['byday'])) {
							$ordinalIndex = 0;
							$pieces = explode(",", $parts['byday']);
							foreach ($pieces as $thisPiece) {
								if (strlen($thisPiece) == 3) {
									$returnArray['event']['byday_' . strtolower($thisPiece)] = 1;
								} else if (strlen($thisPiece) > 3) {
									$ordinalIndex++;
									$weekday = substr($thisPiece, -3);
									$returnArray['event']['weekday_' . $ordinalIndex] = $weekday;
									$returnArray['event']['ordinal_day_' . $ordinalIndex] = str_replace($weekday, "", $thisPiece);
								} else {
									$ordinalIndex++;
									$returnArray['event']['ordinal_day_' . $ordinalIndex] = $thisPiece;
								}
							}
						}
					}
					$resultSet = executeQuery("select min(hour),max(hour) from event_facility_recurrences where event_id = ?", $_GET['event_id']);
					if ($row = getNextRow($resultSet)) {
						$returnArray['event']['start_time'] = round($row['min(hour)'], 2);
						$returnArray['event']['end_time'] = round($row['max(hour)'], 2);
					}
					# repeat = 0 && count = 1
				} else if ($count == 1) {
					$resultSet = executeQuery("select min(hour),max(hour) from event_facilities where event_id = ?", $_GET['event_id']);
					if ($row = getNextRow($resultSet)) {
						$returnArray['event']['start_time'] = round($row['min(hour)'], 2);
						$returnArray['event']['end_time'] = round($row['max(hour)'], 2);
					}
				} else {
					$facilityId = getFieldFromId("facility_id", "facilities", "facility_id", $_GET['facility_id']);
					$resultSet = executeQuery("select min(hour),max(hour) from event_facilities where event_id = ?" . (empty($facilityId) ? "" : " and facility_id = " . $facilityId), $_GET['event_id']);
					if ($row = getNextRow($resultSet)) {
						$returnArray['event']['start_time'] = round($row['min(hour)'], 2);
						$returnArray['event']['end_time'] = round($row['max(hour)'], 2);
					}
				}
				$phoneNumberControl = new DataColumn("phone_numbers");
				$phoneNumberControl->setControlValue("data_type", "custom_control");
				$phoneNumberControl->setControlValue("control_class", "EditableList");
				$phoneNumberControl->setControlValue("primary_table", "contacts");
				$phoneNumberControl->setControlValue("list_table", "phone_numbers");
				$customControl = new EditableList($phoneNumberControl, $this);
				$returnArray = array_merge($returnArray, $customControl->getRecord($contactId));
				$requirements = array();
				$resultSet = executeQuery("select * from event_requirement_data where event_id = ?", $_GET['event_id']);
				while ($row = getNextRow($resultSet)) {
					$requirements[] = $row;
				}
				$returnArray['requirements'] = $requirements;
				ajaxResponse($returnArray);
				break;
			case ("create_preset"):
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					$facilityIds = explode(",", $_POST['facility_ids']);
					$resultSet = executeQuery("insert into facility_groups (client_id,description) values (?,?)", $GLOBALS['gClientId'], $_POST['description']);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = "Sorry! An error occurred and the preset cannot be created.";
					} else {
						$facilityGroupId = $resultSet['insert_id'];
						$returnArray['facility_group_id'] = $facilityGroupId;
						$returnArray['description'] = htmlText($_POST['description']);
						foreach ($facilityIds as $facilityId) {
							executeQuery("insert ignore into facility_group_contents (facility_group_id,facility_id) values (?,?)", $facilityGroupId, $facilityId);
						}
					}
				}
				ajaxResponse($returnArray);
				break;
			case ("delete_preset"):
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					$facilityGroupId = $_GET['facility_group_id'];
					executeQuery("delete from facility_group_contents where facility_group_id = ?", $facilityGroupId);
					executeQuery("delete from facility_groups where facility_group_id = ?", $facilityGroupId);
				}
				ajaxResponse($returnArray);
				break;
            case "clear_cached_events":
	            $_SESSION['session_cached_data'] = array();
	            saveSessionData();
                ajaxResponse($returnArray);
                break;
			case ("get_events"):
				ksort($_GET);

				$scheduleArray = array();
				if (empty($_GET['facility_id'])) {
					$originalFacilityId = false;
				} else {
					$originalFacilityId = getFieldFromId("facility_id", "facilities", "facility_id", $_GET['facility_id'], "inactive = 0");

					if (empty($originalFacilityId)) {
						echo jsonEncode($scheduleArray);
						exit;
					}
				}

				$facilityScheduleArray = array();
				$facilitySet = executeQuery("select * from facilities where client_id = ? and inactive = 0" . (empty($originalFacilityId) ? "" : " and facility_id = " . $originalFacilityId), $GLOBALS['gClientId']);
				while ($facilityRow = getNextRow($facilitySet)) {
					$facilityId = $facilityRow['facility_id'];
					$scheduleArray = array();
					$startDate = date("Y-m-d", strtotime($_GET['start']));
					$endDate = date("Y-m-d", strtotime($_GET['end']));
					$resultSet = executeQuery("select *,(select tentative from events where event_id = event_facilities.event_id) tentative from event_facilities where facility_id = ? and date_needed between ? and ? order by event_id,facility_id,date_needed,hour", $facilityId, $startDate, $endDate);
					$currentDate = "";
					$currentEventId = "";
					$lastHour = "";
					$index = -1;
					while ($row = getNextRow($resultSet)) {
						$tentative = $row['tentative'];
						$thisDate = date("Y-m-d", strtotime($row['date_needed']));

						$thisStartHour = floor($row['hour']) . ":" . str_pad(($row['hour'] - floor($row['hour'])) * 60, 2, "0", STR_PAD_LEFT) . ":00";
						$thisEndHour = floor($row['hour'] + .25) . ":" . str_pad((($row['hour'] + .25) - floor($row['hour'] + .25)) * 60, 2, "0", STR_PAD_LEFT) . ":00";

						if ($currentDate != $row['date_needed'] || $currentEventId != $row['event_id']) {
							$index++;
							$contactId = getFieldFromId("contact_id", "events", "event_id", $row['event_id']);
							$contact = (empty($contactId) ? "" : getDisplayName($contactId));
							$description = (empty($contact) ? "" : $contact . " - ") . getFieldFromId("description", "events", "event_id", $row['event_id']);
							$scheduleArray[$index] = array("id" => $row['event_facility_id'], "event_id" => $row['event_id'], "ids" => $row['event_facility_id'], "title" => $description,
								"start" => date("c", strtotime($thisDate . " " . $thisStartHour)), "end" => date("c", strtotime($thisDate . " " . $thisEndHour)), "editable" => $GLOBALS['gPermissionLevel'] > _READONLY);
							if ($tentative) {
								$scheduleArray[$index]['color'] = "rgb(255,165,0)";
							} else {
								$scheduleArray[$index]['color'] = "rgb(51,102,204)";
							}
							$lastHour = $row['hour'];
							$currentDate = $row['date_needed'];
							$currentEventId = $row['event_id'];
							continue;
						}
						if ($row['hour'] <= ($lastHour + .25)) {
							if ($row['hour'] > $lastHour) {
								$scheduleArray[$index]["ids"] .= "," . $row['event_facility_id'];
								$scheduleArray[$index]["end"] = date("c", strtotime($thisDate . " " . $thisEndHour));
							}
						} else {
							$index++;
							$contactId = getFieldFromId("contact_id", "events", "event_id", $row['event_id']);
							$contact = (empty($contactId) ? "" : getDisplayName($contactId));
							$description = (empty($contact) ? "" : $contact . " - ") . getFieldFromId("description", "events", "event_id", $row['event_id']);
							$scheduleArray[$index] = array("id" => $row['event_facility_id'], "event_id" => $row['event_id'], "ids" => $row['event_facility_id'], "title" => $description,
								"start" => date("c", strtotime($thisDate . " " . $thisStartHour)), "end" => date("c", strtotime($thisDate . " " . $thisEndHour)), "editable" => $GLOBALS['gPermissionLevel'] > _READONLY);
							if ($tentative) {
								$scheduleArray[$index]['color'] = "rgb(255,165,0)";
							} else {
								$scheduleArray[$index]['color'] = "rgb(51,102,204)";
							}
						}
						$lastHour = $row['hour'];
						$currentDate = $row['date_needed'];
						$currentEventId = $row['event_id'];
					}

					$recurrenceArray = array();
					$currentStartDate = "";
					$currentEventId = "";
					$currentRepeatRules = "";
					$lastHour = "";
					$recurIndex = -1;

					$resultSet = executeQuery("select *,(select tentative from events where event_id = event_facility_recurrences.event_id) tentative, " .
						"(select end_date from events where event_id = event_facility_recurrences.event_id) event_end_date from event_facility_recurrences where facility_id = ? order by event_id,facility_id,repeat_rules,hour", $facilityId);
					while ($row = getNextRow($resultSet)) {
						if ($currentEventId != $row['event_id'] || $currentRepeatRules != $row['repeat_rules']) {
							$recurIndex++;
							$row['start'] = $row['hour'];
							$row['end'] = $row['hour'];
							$row['ids'] = $row['event_facility_recurrence_id'];
							$recurrenceArray[$recurIndex] = $row;
							$lastHour = $row['hour'];
							$currentEventId = $row['event_id'];
							$currentRepeatRules = $row['repeat_rules'];
							continue;
						}
						if ($row['hour'] <= ($lastHour + .25)) {
							if ($row['hour'] > $lastHour) {
								$recurrenceArray[$recurIndex]["end"] = $row['hour'];
								$recurrenceArray[$recurIndex]['ids'] .= "," . $row['event_facility_recurrence_id'];
							}
						} else {
							$recurIndex++;
							$row['start'] = $row['hour'];
							$row['end'] = $row['hour'];
							$row['ids'] = $row['event_facility_recurrence_id'];
							$recurrenceArray[$recurIndex] = $row;
						}
						$lastHour = $row['hour'];
						$currentEventId = $row['event_id'];
						$currentRepeatRules = $row['repeat_rules'];
					}
					while ($startDate <= $endDate) {
						foreach ($recurrenceArray as $repeatIndex => $row) {
							if (empty($row['repeat_rules'])) {
								continue;
							}
							$tentative = $row['tentative'];
							$eventEndDate = $row['event_end_date'];
							$parts = parseNameValues($row['repeat_rules']);
							if (!empty($eventEndDate) && (empty($parts['until']) || date("Y-m-d", strtotime($parts['until'])) > $eventEndDate)) {
								$parts['until'] = date("m/d/Y", strtotime($eventEndDate));
							}
							$repeatRules = Events::assembleRepeatRules($parts);
							if (isInSchedule($startDate, $repeatRules)) {
								$parts = parseNameValues($repeatRules);

								$thisStartHour = floor($row['start']) . ":" . str_pad(($row['start'] - floor($row['start'])) * 60, 2, "0", STR_PAD_LEFT) . ":00";
								$thisEndHour = floor($row['end'] + .25) . ":" . str_pad((($row['end'] + .25) - floor($row['end'] + .25)) * 60, 2, "0", STR_PAD_LEFT) . ":00";

								$contactId = getFieldFromId("contact_id", "events", "event_id", $row['event_id']);
								$contact = (empty($contactId) ? "" : getDisplayName($contactId));
								$description = (empty($contact) ? "" : $contact . " - ") . getFieldFromId("description", "events", "event_id", $row['event_id']);
								$scheduleArray[++$index] = array("id" => "repeat_rules_" . $repeatIndex . "_" . date("Ymd", strtotime($startDate)),
									"title" => $description, "event_id" => $row['event_id'],
									"start" => date("c", strtotime($startDate . " " . $thisStartHour)), "end" => date("c", strtotime($startDate . " " . $thisEndHour)),
									"editable" => false, "allDay" => false, "deletable" => true, "instance_date" => date("m/d/Y", strtotime($startDate)),
									"ids" => $row['ids'], "recurring" => true);
								if ($tentative) {
									$scheduleArray[$index]['color'] = "rgb(255,165,0)";
								} else {
									$scheduleArray[$index]['color'] = "rgb(0,128,0)";
								}
							}
						}
						$startDate = date("Y-m-d", strtotime("+1 day", strtotime($startDate)));
					}
					$facilityScheduleArray[$facilityId] = $scheduleArray;
					if (empty($originalFacilityId)) {
						$_GET['facility_id'] = strval($facilityId);
						ksort($_GET);
						$cachedDataKey = md5(jsonEncode(array_diff_key($_GET, array("allow_session_data" => true, "_" => true))));
						if (!array_key_exists("session_cached_data", $_SESSION)) {
							$_SESSION['session_cached_data'] = array();
						}
						$_SESSION['session_cached_data'][$cachedDataKey] = $scheduleArray;
						saveSessionData();
					}
				}

				$scheduleArray = (empty($originalFacilityId) ? array() : $facilityScheduleArray[$originalFacilityId]);
				echo jsonEncode($scheduleArray);
				exit;
			case "get_calendar_content":
				switch ($_GET['calendar_id']) {
					case (strpos($_GET['calendar_id'], "facility_type_id_") === 0 ? $_GET['calendar_id'] : !$_GET['calendar_id']):
						$facilityTypeId = substr($_GET['calendar_id'], strlen("facility_type_id_"));
						$returnArray['calendar'] = "";
						$returnArray['facility_ids'] = array();
						$resultSet = executeQuery("select * from facilities where facility_type_id = ? and client_id = ? and inactive = 0 order by sort_order,description", $facilityTypeId, $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							$returnArray['facility_ids'][] = array("facility_id" => $row['facility_id'], "description" => $row['description']);
						}
						break;
					case "custom_calendars":
						if (empty($_POST['custom_group_form'])) {
							$valuesArray = Page::getPagePreferences();
							if (is_array($valuesArray['custom_facilities'])) {
								$facilityIds = $valuesArray['custom_facilities'];
							} else {
								$facilityIds = array();
							}
						} else {
							$facilityIds = array();
							foreach ($_POST as $fieldName => $facilityId) {
								if (substr($fieldName, 0, strlen("facility_id_")) == "facility_id_") {
									$facilityId = getFieldFromId("facility_id", "facilities", "facility_id", $facilityId, "inactive = 0");
									if (!empty($facilityId)) {
										$facilityIds[] = $facilityId;
									}
								}
							}
						}
						if (empty($facilityIds)) {
							$returnArray['calendar'] = "<p>No Facilities Selected</p>";
						} else {
							$valuesArray = Page::getPagePreferences();
							$valuesArray['custom_facilities'] = $facilityIds;
							Page::setPagePreferences($valuesArray);
							$returnArray['calendar'] = "";
							$returnArray['facility_ids'] = array();
							$resultSet = executeQuery("select * from facilities where facility_id in (" . implode(",", $facilityIds) . ") order by sort_order,description");
							while ($row = getNextRow($resultSet)) {
								$returnArray['facility_ids'][] = array("facility_id" => $row['facility_id'], "description" => $row['description']);
							}
						}
						break;
					case "custom_groups":
						ob_start();
						$valuesArray = Page::getPagePreferences();
						if (is_array($valuesArray['custom_facilities'])) {
							$facilityIds = $valuesArray['custom_facilities'];
						} else {
							$facilityIds = array();
						}
						?>
                        <div id="_select_facilities">
                            <h3>Click on room type header to select all of that type.</h3>
                            <form id="_custom_group_form">
                                <input type="hidden" name="custom_group_form" id="custom_group_form" value="1">
								<?php
								$facilityCount = 0;
								$resultSet = executeQuery("select * from facility_types where client_id = ? and inactive = 0 and facility_type_id in (select facility_type_id from facilities where inactive = 0) order by sort_order,description", $GLOBALS['gClientId']);
								while ($row = getNextRow($resultSet)) {
									?>
                                    <div class="facility-list">
                                        <p class="highlighted-text facility-list-header"><?= htmlText($row['description']) ?></p>
										<?php
										$facilitySet = executeQuery("select * from facilities where client_id = ? and inactive = 0 and facility_type_id = ? order by sort_order,description", $GLOBALS['gClientId'], $row['facility_type_id']);
										while ($facilityRow = getNextRow($facilitySet)) {
											?>
                                            <p><input type="checkbox"<?= (in_array($facilityRow['facility_id'], $facilityIds) ? " checked='checked'" : "") ?> data-description="<?= htmlText($facilityRow['description']) ?>" class="facility-id" id="facility_id_<?= $facilityRow['facility_id'] ?>" name="facility_id_<?= $facilityRow['facility_id'] ?>" value="<?= $facilityRow['facility_id'] ?>"/><label class="checkbox-label" for="facility_id_<?= $facilityRow['facility_id'] ?>"><?= htmlText($facilityRow['description']) ?></label></p>
											<?php
										}
										?>
                                    </div>
									<?php
								}
								?>
                            </form>
                            <div id="preset_list" class="facility-list">
                                <p class="highlighted-text facility-list-header">Predefined Groups</p>
                                <p><a href="#" class="facility-group" data-facility_ids="">Clear All</a></p>
								<?php
								$resultSet = executeQuery("select * from facility_groups where client_id = ? order by description", $GLOBALS['gClientId']);
								while ($row = getNextRow($resultSet)) {
									$facilityIds = "";
									$idSet = executeQuery("select facility_id from facility_group_contents where facility_group_id = ?", $row['facility_group_id']);
									while ($idRow = getNextRow($idSet)) {
										if (!empty($facilityIds)) {
											$facilityIds .= ",";
										}
										$facilityIds .= $idRow['facility_id'];
									}
									?>
                                    <p class='facility-group-choice'><span class='fa fa-times delete-preset' title='Delete Preset' data-facility_group_id='<?= $row['facility_group_id'] ?>'></span><a href='#' class='facility-group' data-facility_ids='<?= $facilityIds ?>'><?= htmlText($row['description']) ?></a></p>
									<?php
								}
								?>
								<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                                    <p id="create_preset_paragraph"><input type="text" size="20" maxlength="255" id="group_description" name="group_description"/>
                                        <button id="create_preset">Create Preset</button>
                                    </p>
								<?php } ?>
                                <p>
                                    <button id="view_custom">View Calendars</button>
                                </p>
                            </div>
                            <div class='clear-div'></div>
                        </div>
						<?php
						$returnArray['calendar'] = ob_get_clean();
						break;
					case "event_overview":
						$valuesArray = Page::getPagePreferences();
						$currentDate = date("Y-m-d", strtotime($_GET['calendar_date']));
						$startHour = $valuesArray["overview_start_hour"];
						if (empty($startHour) || !is_numeric($startHour) || $startHour < 1 || $startHour > 12) {
							$startHour = 8;
						}
						$segmentCount = $valuesArray["overview_segments"];
						if (empty($segmentCount) || !is_numeric($segmentCount) || $segmentCount < 8 || $segmentCount > 80) {
							$segmentCount = 40;
						}
						$facilityStatuses = array();
						$resultSet = executeQuery("select *,(select description from facility_types where facility_type_id = facilities.facility_type_id) facility_type," .
							"(select sort_order from facility_types where facility_type_id = facilities.facility_type_id) facility_type_sort from facilities where " .
							"inactive = 0 and client_id = ? order by facility_type_sort,facility_type,facility_type_id", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							$currentHour = $startHour;
							$currentMinute = 0;
							if ($currentMinute > 0 && $currentMinute <= 15) {
								$currentHour += .25;
							} else if ($currentMinute > 15 && $currentMinute <= 30) {
								$currentHour += .5;
							} else if ($currentMinute > 30) {
								$currentHour += .75;
							}
							for ($x = 1; $x <= $segmentCount; $x++) {
								$row['event_id_' . $x] = Events::getEventForTime($currentDate, $currentHour, $currentHour, $row['facility_id']);
								if ($row['event_id_' . $x] === false) {
									$row['event_id_' . $x] = array();
								}
								$currentHour += .25;
							}
							$facilityStatuses[] = $row;
						}
						ob_start();
						?>
                        <div id="happening_now_calendar">
                            <input type='hidden' id='overview_date' value='<?= date("m/d/Y", strtotime($currentDate)) ?>'>
                            <input type='hidden' id='overview_date_original' value='<?= date("m/d/Y", strtotime($_GET['calendar_date'])) ?>'>
                            <input type='hidden' id='overview_date_raw' value='<?= htmlText($_GET['calendar_date']) ?>'>
                            <table id="happening_now_table">
								<?php
								ob_start();
								?>
                                <tr class='data-only'>
                                    <td class='highlighted-text align-left facility-type-header'>%facility_type%</td>
									<?php
									$currentHour = $startHour;
									$currentMinute = 0;
									if ($currentMinute > 0 && $currentMinute <= 15) {
										$currentHour += .25;
									} else if ($currentMinute > 15 && $currentMinute <= 30) {
										$currentHour += .5;
									} else if ($currentMinute > 30) {
										$currentHour += .75;
									}
									$displayDate = date("Y-m-d", strtotime($_GET['calendar_date']));
									$displayDates = array();
									for ($x = 1; $x <= $segmentCount; $x++) {
										$workingHour = floor($currentHour);
										$displayHour = ($workingHour == 0 ? 12 : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
										$displayMinute = ($currentHour - $workingHour) * 60;
										$amPm = ($currentHour == 0 ? "midnight" : ($currentHour == 12 ? "noon" : ($currentHour >= 12 ? "pm" : "am")));
										if ($segmentCount > 12 && ($displayMinute == 15 || $displayMinute == 45)) {
											$displayTime = "";
										} else {
											$displayTime = $displayHour . ":" . str_pad($displayMinute, 2, "0", STR_PAD_LEFT) . $amPm;
										}
										?>
                                        <td class='align-center'><?= $displayTime ?></td>
										<?php
										$displayDates[$x] = array("current_hour" => $currentHour, "description" => date("Y-m-d H:i:s", strtotime($displayDate . " " . floor($currentHour) . ":" . str_pad($displayMinute, 2, "0", STR_PAD_LEFT) . ":00")));
										$currentHour += .25;
										if ($currentHour > 23.75) {
											$currentHour = 0;
											$displayDate = date("Y-m-d", strtotime("+1 day", strtotime($displayDate)));
										}
									}
									?>
                                </tr>
								<?php
								$timeRow = ob_get_clean();
								$saveFacilityType = "";
								foreach ($facilityStatuses as $facilityInfo) {
									if ($saveFacilityType != $facilityInfo['facility_type']) {
										echo str_replace("%facility_type%", htmlText($facilityInfo['facility_type']), $timeRow);
										$saveFacilityType = $facilityInfo['facility_type'];
									}
									?>
                                    <tr data-facility_id="<?= $facilityInfo['facility_id'] ?>">
                                        <td class='facility-name'><?= htmlText($facilityInfo['description']) ?></td>
										<?php
										$firstEventId = "";
										$lastEventId = "";
										for ($x = 1; $x <= $segmentCount; $x++) {
											$class = "available";
											if (!empty($facilityInfo['event_id_' . $x]['event_id'])) {
												if ($firstEventId == $facilityInfo['event_id_' . $x]['event_id'] || $x == 1) {
													$class = "happening-now happening-now-" . $facilityInfo['event_id_' . $x]['event_id'] . " current-event";
												} else {
													$class = "happening-now happening-now-" . $facilityInfo['event_id_' . $x]['event_id'] . " upcoming-event";
												}
												if ($x == 1) {
													$firstEventId = $facilityInfo['event_id_' . $x]['event_id'];
												}
											}
											if (!empty($facilityInfo['event_id_' . $x]['event_id']) && $facilityInfo['event_id_' . $x]['event_id'] == $lastEventId) {
												$class .= " continuing-event";
												$displayDescription = "";
											} else {
												$displayDescription = $facilityInfo['event_id_' . $x]['description'];
											}
											$lastEventId = $facilityInfo['event_id_' . $x]['event_id'];
											$endTime = date("Y-m-d H:i:s", strtotime("+15 minute", strtotime($displayDates[$x]['description'])));
											$eventColorClass = Events::getEventColorClass($facilityInfo['event_id_' . $x], $facilityInfo);
											if (!empty($eventColorClass)) {
												$class .= (empty($class) ? "" : " ") . $eventColorClass;
											}
											$contactId = $facilityInfo['event_id_' . $x]['contact_id'];
											if (!empty($contactId)) {
												$class .= (empty($class) ? "" : " ") . "contact-id-" . $contactId;
											}
											?>
                                            <td class="<?= $class ?>" data-event_id="<?= $facilityInfo['event_id_' . $x]['event_id'] ?>" data-start_date="<?= $displayDates[$x]['description'] ?>" data-start_time="<?= $displayDates[$x]['current_hour'] ?>" data-end_date="<?= $endTime ?>" data-end_time="<?= ($displayDates[$x]['current_hour']) ?>"><?= htmlText($displayDescription) ?></td>
											<?php
										}
										?>
                                    </tr>
									<?php
								}
								?>
                            </table>
                        </div>
						<?php
						$returnArray['calendar'] = ob_get_clean();
						break;
					case "happening_now":
						$valuesArray = Page::getPagePreferences();
						$ignoreSort = $valuesArray["ignore_sort"];
						$groupByFacilityType = $valuesArray["group_by_facility_type"];
						if ($groupByFacilityType) {
							$ignoreSort = true;
						}
						$facilityStatuses = array();
						$resultSet = executeQuery("select *,(select description from facility_types where facility_type_id = facilities.facility_type_id) facility_type," .
							"(select sort_order from facility_types where facility_type_id = facilities.facility_type_id) facility_type_sort from facilities where " .
							"inactive = 0 and client_id = ? order by " . ($groupByFacilityType ? "facility_type_sort," : "") . "sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							$currentDate = date("Y-m-d");
							$currentHour = date("G");
							$currentMinute = date("i");
							if ($currentMinute > 0 && $currentMinute <= 15) {
								$currentHour += .25;
							} else if ($currentMinute > 15 && $currentMinute <= 30) {
								$currentHour += .5;
							} else if ($currentMinute > 30) {
								$currentHour += .75;
							}
							$sortTotal = 0;
							for ($x = 1; $x <= $this->iHappeningNowSegments; $x++) {
								$row['event_id_' . $x] = Events::getEventForTime($currentDate, $currentHour, $currentHour, $row['facility_id']);
								if ($row['event_id_' . $x] !== false) {
									$sortTotal += pow(2, (4 - $x));
								} else {
									$row['event_id_' . $x] = array();
								}
								$currentHour += .25;
								if ($currentHour > 23.75) {
									$currentDate = date("Y-m-d", strtotime("+1 days", strtotime($currentDate)));
									$currentHour = 0;
								}
							}
							$row['sort_total'] = $sortTotal;
							$facilityStatuses[] = $row;
						}
						if (!$ignoreSort) {
							usort($facilityStatuses, array($this, "sortFacilities"));
						}
						ob_start();
						?>
                        <div id="happening_now_calendar">
                            <table id="happening_now_table">
								<?php
								ob_start();
								?>
                                <tr class='data-only'>
                                    <td class='highlighted-text align-left facility-type-header'>%facility_type%</td>
									<?php
									$currentHour = date("G");
									$currentMinute = date("i");
									if ($currentMinute > 0 && $currentMinute <= 15) {
										$currentHour += .25;
									} else if ($currentMinute > 15 && $currentMinute <= 30) {
										$currentHour += .5;
									} else if ($currentMinute > 30) {
										$currentHour += .75;
									}
									$displayDate = date("Y-m-d");
									$displayDates = array();
									for ($x = 1; $x <= $this->iHappeningNowSegments; $x++) {
										$workingHour = floor($currentHour);
										$displayHour = ($workingHour == 0 ? 12 : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
										$displayMinute = ($currentHour - $workingHour) * 60;
										$amPm = ($currentHour == 0 ? "midnight" : ($currentHour == 12 ? "noon" : ($currentHour >= 12 ? "pm" : "am")));
										if ($this->iHappeningNowSegments > 12 && ($displayMinute == 15 || $displayMinute == 45)) {
											$displayTime = "";
										} else {
											$displayTime = $displayHour . ":" . str_pad($displayMinute, 2, "0", STR_PAD_LEFT) . $amPm;
										}
										?>
                                        <td class='align-center'><?= $displayTime ?></td>
										<?php
										$displayDates[$x] = array("current_hour" => $currentHour, "description" => date("Y-m-d H:i:s", strtotime($displayDate . " " . floor($currentHour) . ":" . str_pad($displayMinute, 2, "0", STR_PAD_LEFT) . ":00")));
										$currentHour += .25;
										if ($currentHour > 23.75) {
											$currentHour = 0;
											$displayDate = date("Y-m-d", strtotime("+1 day", strtotime($displayDate)));
										}
									}
									?>
                                </tr>
								<?php
								$timeRow = ob_get_clean();
								if (!$groupByFacilityType) {
									echo str_replace("%facility_type%", "", $timeRow);
								}
								$saveFacilityType = "";
								foreach ($facilityStatuses as $facilityInfo) {
									if ($groupByFacilityType && $saveFacilityType != $facilityInfo['facility_type']) {
										echo str_replace("%facility_type%", htmlText($facilityInfo['facility_type']), $timeRow);
										$saveFacilityType = $facilityInfo['facility_type'];
									}
									?>
                                    <tr data-facility_id="<?= $facilityInfo['facility_id'] ?>">
                                        <td class='facility-name'><?= htmlText($facilityInfo['description']) ?></td>
										<?php
										$firstEventId = "";
										$lastEventId = "";
										for ($x = 1; $x <= $this->iHappeningNowSegments; $x++) {
											$class = "available";
											if (!empty($facilityInfo['event_id_' . $x]['event_id'])) {
												if ($firstEventId == $facilityInfo['event_id_' . $x]['event_id'] || $x == 1) {
													$class = "happening-now happening-now-" . $facilityInfo['event_id_' . $x]['event_id'] . " current-event";
												} else {
													$class = "happening-now happening-now-" . $facilityInfo['event_id_' . $x]['event_id'] . " upcoming-event";
												}
												if ($x == 1) {
													$firstEventId = $facilityInfo['event_id_' . $x]['event_id'];
												}
											}
											if (!empty($facilityInfo['event_id_' . $x]['event_id']) && $facilityInfo['event_id_' . $x]['event_id'] == $lastEventId) {
												$class .= " continuing-event";
												$displayDescription = "";
											} else {
												$displayDescription = $facilityInfo['event_id_' . $x]['description'];
											}
											$lastEventId = $facilityInfo['event_id_' . $x]['event_id'];
											$endTime = date("Y-m-d H:i:s", strtotime("+15 minute", strtotime($displayDates[$x]['description'])));
											$eventColorClass = Events::getEventColorClass($facilityInfo['event_id_' . $x], $facilityInfo);
											if (!empty($eventColorClass)) {
												$class .= (empty($class) ? "" : " ") . $eventColorClass;
											}
											$contactId = $facilityInfo['event_id_' . $x]['contact_id'];
											if (!empty($contactId)) {
												$class .= (empty($class) ? "" : " ") . "contact-id-" . $contactId;
											}
											?>
                                            <td class="<?= $class ?>" data-event_id="<?= $facilityInfo['event_id_' . $x]['event_id'] ?>" data-start_date="<?= $displayDates[$x]['description'] ?>" data-start_time="<?= $displayDates[$x]['current_hour'] ?>" data-end_date="<?= $endTime ?>" data-end_time="<?= ($displayDates[$x]['current_hour']) ?>"><?= htmlText($displayDescription) ?></td>
											<?php
										}
										?>
                                    </tr>
									<?php
								}
								?>
                            </table>
                        </div>
						<?php
						$returnArray['calendar'] = ob_get_clean();
						break;
				}
				ajaxResponse($returnArray);
				break;
			case "visitor_filter":
				$valuesArray = Page::getPagePreferences();
				$valuesArray['visitor_filter'] = $_GET['visitor_filter'];
				Page::setPagePreferences($valuesArray);
				ajaxResponse($returnArray);
				break;
			case "visitor_check_out":
				executeQuery("update visitor_log set end_time = now() where visitor_log_id = ? and end_time is null", $_GET['visitor_log_id']);
				ajaxResponse($returnArray);
				break;
			case "visitor_check_in":
				$sourceId = getFieldFromId("source_id", "sources", "source_code", "VISITOR", "client_id = " . $GLOBALS['gClientId']);
				if (empty($sourceId)) {
					$resultSet = executeQuery("insert into sources (client_id,source_code,description) values (?,'VISITOR','Visitor')", $GLOBALS['gClientId']);
					$sourceId = $resultSet['insert_id'];
				}
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['check_in_contact_id']);
				if (empty($contactId)) {
					$resultSet = executeQuery("select * from contacts where client_id = ? and last_name = ? and first_name = ? and email_address <=> ? order by contact_id",
						$GLOBALS['gClientId'], $_POST['check_in_last_name'], $_POST['check_in_first_name'], $_POST['check_in_email_address']);
					if ($row = getNextRow($resultSet)) {
						$contactId = $row['contact_id'];
					} else {
						$contactDataTable = new DataTable("contacts");
						if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['check_in_first_name'], "last_name" => $_POST['check_in_last_name'],
							"email_address" => strtolower($_POST['check_in_email_address']), "source_id" => $sourceId)))) {
							$returnArray['error_message'] = "An error occurred";
							ajaxResponse($returnArray);
							break;
						}
					}
				}
				$taskSourceId = getFieldFromId("task_source_id", "task_sources", "task_source_code", "FRONT_DESK", "client_id = ?", $GLOBALS['gClientId']);
				if (!empty($contactId) && !empty($taskSourceId)) {
					executeQuery("insert into tasks (client_id,contact_id,description,date_completed,simple_contact_task,task_source_id) values " .
						"(?,?,'Visit as Front Desk',now(),1,?)", $GLOBALS['gClientId'], $contactId, $taskSourceId);
				}
				if ($contactId) {
					$categoryId = getFieldFromId("category_id", "categories", "category_code", "VISITOR", "client_id = " . $GLOBALS['gClientId']);
					if (empty($categoryId)) {
						$resultSet = executeQuery("insert into categories (client_id,category_code,description) values (?,'VISITOR','Visitor')", $GLOBALS['gClientId']);
						$categoryId = $resultSet['insert_id'];
					}
					$contactCategoryId = getFieldFromId('contact_category_id', 'contact_categories', "contact_id", $contactId, "category_id = " . $categoryId);
					if (empty($contactCategoryId) && !empty($categoryId)) {
						executeQuery("insert into contact_categories (contact_id,category_id) values (?,?)", $contactId, $categoryId);
					}
					$resultSet = executeQuery("select * from visitor_log where contact_id = ? and date(visit_time) = current_date and end_time is null", $contactId);
					if ($resultSet['row_count'] == 0) {
						executeQuery("insert into visitor_log (client_id,contact_id,visit_time,visit_type_id) values (?,?,now(), ?)", $GLOBALS['gClientId'], $contactId, $_POST['visit_type_id']);
					}
				}
				ajaxResponse($returnArray);
				break;
			case "search":
				$contactArray = array();
				$resultSet = executeQuery("select * from contacts where contact_id in (select contact_id from contact_identifiers where identifier_value = ?) and client_id = ? and email_address is not null order by email_address,contact_id",
					$_POST['search_text'], $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$row['email_address'] = strtolower($row['email_address']);
					if (!array_key_exists($row['email_address'], $contactArray)) {
						$contactArray[$row['email_address']] = $row;
					}
				}
				if (is_numeric($_POST['search_text'])) {
					$resultSet = executeQuery("select * from contacts where (contact_id = ? or contact_id in (select contact_id from contact_redirect where retired_contact_identifier = ? and client_id = ?)) and client_id = ? and email_address is not null order by email_address,contact_id",
						$_POST['search_text'], $_POST['search_text'], $GLOBALS['gClientId'], $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$row['email_address'] = strtolower($row['email_address']);
						if (!array_key_exists($row['email_address'], $contactArray)) {
							$contactArray[$row['email_address']] = $row;
						}
					}
				}
				$resultSet = executeQuery("select * from contacts where email_address like ? and contact_id in " .
					"(select contact_id from contact_categories where category_id = (select category_id from categories " .
					"where category_code = 'VISITOR' and client_id = ?)) and client_id = ? order by email_address,contact_id",
					$_POST['search_text'] . "%", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$row['email_address'] = strtolower($row['email_address']);
					if (!array_key_exists($row['email_address'], $contactArray)) {
						$contactArray[$row['email_address']] = $row;
					}
				}
				if (empty($contactArray)) {
					$returnArray['search_results'] = "<p>No results found</p>";
				} else {
					if (count($contactArray) > 8) {
						$returnArray['search_results'] = "<p>Too many to display... keep typing</p>";
					}
					foreach ($contactArray as $row) {
						$returnArray['search_results'] .= "<p class='select-contact' data-contact_id='" . $row['contact_id'] . "' data-first_name='" . str_replace("'", "", $row['first_name']) .
							"' data-last_name='" . str_replace("'", "", $row['last_name']) . "' data-email_address='" . $row['email_address'] . "'>" . (!empty($row['first_name']) || !empty($row['last_name']) ? getDisplayName($row['contact_id']) : "") . ", " . $row['email_address'] . "</p>";
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_information":
				$resultSet = executeQuery("select count(distinct contact_id) from visitor_log where date(visit_time) = current_date and client_id = ?", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['total_visitor_count'] = $row['count(distinct contact_id)'];
				}
				$resultSet = executeQuery("select count(distinct contact_id) from visitor_log where date(visit_time) = current_date and end_time is null and client_id = ?", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['current_visitor_count'] = $row['count(distinct contact_id)'];
				}
				$resultSet = executeQuery("select *,(select waiting_list from visit_types where visit_type_id = visitor_log.visit_type_id) waiting from visitor_log where client_id = ? and date(visit_time) = current_date order by visit_time desc", $GLOBALS['gClientId']);
				$mostRecent = array();
				$returnArray['visitor_list_rows'] = array();
				while ($row = getNextRow($resultSet)) {
					if (empty($mostRecent)) {
						$mostRecent = $row;
					}
					$displayName = getDisplayName($row['contact_id']);
					if (empty($displayName)) {
						$displayName = getFieldFromId("email_address", "contacts", "contact_id", $row['contact_id']);
					}
					$eventColorClass = Events::getEventColorClass(array("contact_id" => $row['contact_id']), array());
					$returnArray['visitor_list_rows'][] = array("contact_id" => $row['contact_id'], "event_color_class" => $eventColorClass, "estimated_minutes" => $row['estimated_minutes'],
						"visitor_log_id" => $row['visitor_log_id'], "left" => (empty($row['end_time']) ? "" : "true"), "waiting" => (empty($row['waiting']) || !empty($row['end_time']) ? "" : "true"),
						"in_time" => date("h:i a", strtotime($row['visit_time'])), "name" => $displayName,
						"out_time" => (empty($row['end_time']) ? "<button class='left-now'>Left Now</button>" : date("h:i a", strtotime($row['end_time']))));
				}
				$returnArray['just_checked_in'] = "";
				if (!empty($mostRecent)) {
					$timeSince = date("U") - date("U", strtotime($mostRecent['visit_time']));
					if ($timeSince < 10) {
						$returnArray['just_checked_in'] = getDisplayName($mostRecent['contact_id']) . " just checked in";
					}
				}
				$informationHash = md5(serialize($returnArray));
				if ($informationHash == $_GET['hash']) {
					$returnArray['no_change'] = true;
				} else {
					$returnArray['information_hash'] = $informationHash;
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		?>
        <div id="_dashboard_wrapper">

            <p class="error-message" id="_error_message"></p>
            <div id="_main_section">
                <div id="_scheduler_header">
                    <span id="calendar_date_sizer"></span>
                    <p id="calendar_date_wrapper"><span class="full-calendar-hide"><input type="text" id="calendar_date" value="<?= date("l, F j, Y") ?>" readonly="readonly"><input type='hidden' id='save_calendar_date' value='<?= date("l, F j, Y") ?>'></span></p>
                    <div id="_controls_wrapper">
                        <span id="_legend_icon" class="float-right fad fa-list"></span>
                        <span id="_preferences_icon" class="float-right fad fa-cog"></span>
                        <span id="_main_section_hider" class="float-right fad fa-compress-arrows-alt"></span>
                        <span class="float-right fas fa-plus" id="new_reservation"></span>
                    </div>
                    <p class="full-calendar-hide header-hideable" id="set_day_buttons">
                        <button id="previous_day">Previous Day</button>
                        <button id="today_day">Today</button>
                        <button id="next_day">Next Day</button>
                    </p>
                </div>
                <div id="_calendar_tabs" class='header-hideable'>
                    <button class="calendar-tab selected" id="happening_now">Happening Now</button>
                    <button class="calendar-tab selected" id="event_overview">Overview</button>
					<?php
					$resultSet = executeQuery("select * from facility_types where client_id = ? and inactive = 0 and facility_type_id in (select facility_type_id from facilities where inactive = 0) order by sort_order,description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						?>
                        <button class="calendar-tab" id="facility_type_id_<?= $row['facility_type_id'] ?>"><?= htmlText($row['description']) ?></button>
						<?php
					}
					?>
                    <button class="calendar-tab" id="custom_groups">Choose</button>
                    <button class="calendar-tab" id="custom_calendars">Custom</button>
                </div>
                <div id="_calendar_wrapper">

                </div>

            </div> <!-- main_section -->

            <div id="_visitor_sidebar"<?= ($this->iHideVisitorSidebar ? " class='hidden'" : "") ?>>

                <p><span id="today_date"><?= date("F j, Y") ?></span><span id="today_time"></span></p>
                <input type="hidden" id="information_hash" value="">
                <div id="current_visitors">
                    <p class="align-center"><span id="current_visitor_count">0</span> Current Visitors</p>
                </div>
                <div id="total_visitors">
                    <p class="align-center"><span id="total_visitor_count">0</span> Visitors Today</p>
                </div>
                <hr>
                <div id="check_in_form_wrapper" class='collapsed'>
                    <h2>Check In Form<span class='fas fa-caret-down'></span><span class='fas fa-caret-left'></span></h2>
                    <div id="_check_in">
                        <input type="hidden" id="clearing_form" value="">
                        <form id="_check_in_form">
                            <input type="hidden" id="check_in_contact_id" name="check_in_contact_id" value="">
                            <div class="basic-form-line">
                                <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#check_in_contact_id').val() == '' && $('#clearing_form').val() == ''" maxlength="25" id="check_in_first_name" name="check_in_first_name" placeholder="First Name">
                                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                            </div>

                            <div class="basic-form-line">
                                <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#check_in_contact_id').val() == '' && $('#clearing_form').val() == ''" maxlength="35" id="check_in_last_name" name="check_in_last_name" placeholder="Last Name">
                                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                            </div>

                            <div class="basic-form-line">
                                <input tabindex="10" type="text" class="validate[custom[email]]" maxlength="60" id="check_in_email_address" name="check_in_email_address" placeholder="Email Address">
                                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                            </div>

                            <div class="basic-form-line">
                                <input type="hidden" id="visit_type_id" name="visit_type_id">
								<?php
								$resultSet = executeQuery("select * from visit_types where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
								while ($row = getNextRow($resultSet)) {
									echo '<button class="visit-type" data-visit_type_id="' . $row['visit_type_id'] . '">' . htmlText($row['description']) . '</button>';
								}
								?>
                                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                            </div>

                            <div id="_search_results">
                            </div> <!-- search_results -->

                            <div class="basic-form-line">
                                <input tabindex="10" type="text" maxlength="60" id="search_text" name="search_text" placeholder="Search Email Address">
                                <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                            </div>

                            <div class="basic-form-line">
                                <button id="_check_in_submit">Submit</button>
                            </div>

                        </form>
                    </div>
                </div>

                <div id="just_checked_in"></div>

				<?php
				$valuesArray = Page::getPagePreferences();
				?>
                <div id="visitor_list">
                    <table id="visitor_table">
                        <tr>
                            <th>In</th>
                            <th></th>
                            <th id="visitor_header"></th>
                            <th></th>
                            <th>Out</th>
                        </tr>
						<?php
						$visitTypeId = getFieldFromId("visit_type_id", "visit_types", "waiting_list", "1", "inactive = 0");
						?>
                        <tr>
                            <td colspan="5">
                                <select id="visitor_filter">
                                    <option value=''<?= (empty($valuesArray['visitor_filter']) ? " selected" : "") ?> data-title="Visitor Log">Show All</option>
                                    <option value='visitor-left'<?= ($valuesArray['visitor_filter'] == "visitor-left" ? " selected" : "") ?> data-title="Visitor Log">Hide Those Who've Left</option>
									<?php if (!empty($visitTypeId)) { ?>
                                        <option value='not-waiting'<?= ($valuesArray['visitor_filter'] == "not-waiting" ? " selected" : "") ?> data-title="Waiting List">Show Those Waiting</option>
									<?php } ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

            </div> <!-- sidebar -->

        </div> <!-- dashboard_wrapper -->
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".set-waiting-time", function () {
                const visitorLogId = $(this).closest("tr").data("visitor_log_id");
                $("#estimated_minutes").val($(this).closest("tr").data("estimated_minutes"));
                $("#_set_estimated_minutes_dialog").dialog({
                    closeOnEscape: true,
                    width: 600,
                    modal: true,
                    draggable: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    show: 'fade',
                    title: 'Estimated Wait Time',
                    buttons: {
                        Save: function (event) {
                            if ($("#_set_estimated_minutes_form").validationEngine("validate")) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_wait_time&visitor_log_id=" + visitorLogId + "&estimated_minutes=" + $("#estimated_minutes").val());
                                $("#_set_estimated_minutes_dialog").dialog('close');
                            }
                        },
                        Cancel: function (event) {
                            $("#_set_estimated_minutes_dialog").dialog('close');
                        }
                    }
                });
            });
            $(document).on("click", "#check_in_form_wrapper h2", function () {
                $("#check_in_form_wrapper").toggleClass("collapsed");
            });
            $(document).on("click", "#_check_in_contact", function () {
                const contactId = $("#_event_details").find("#contact_id").val();
                if (empty(contactId)) {
                    return false;
                }
                if ($(".contact-visit-type").length === 0 || !empty($("#contact_visit_type_id").val()) || $(".contact-visit-type").length === 1) {
                    if ($(".contact-visit-type").length == 1) {
                        $("#contact_visit_type_id").val($(".contact-visit-type").data("visit_type_id"));
                    }
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=visitor_check_in", { check_in_contact_id: contactId, visit_type_id: $("#contact_visit_type_id").val() }, function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            updateInformation();
                            $("#_event_details").dialog('close');
                        }
                    });
                } else {
                    $("#_contact_visit_type_id_row").removeClass("hidden");
                }
                return false;
            });
            $(document).on("click", ".contact-visit-type", function () {
                $("#contact_visit_type_id").val($(this).data("visit_type_id"));
                $("#_check_in_contact").trigger("click");
                return false;
            });
            $(document).on("click", "#_legend_icon", function () {
                $("#_event_colors_legend").dialog({
                    closeOnEscape: true,
                    width: 1000,
                    modal: true,
                    draggable: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    show: 'fade',
                    title: 'Colors Legend',
                    buttons: {
                        Close: function (event) {
                            $("#_event_colors_legend").dialog('close');
                        }
                    }
                });
            });
            $(document).on("click", "#_preferences_icon", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_preferences", function (returnArray) {
                    for (const i in returnArray) {
                        if ($("#_preferences").find("#" + i).length > 0) {
                            if ($("#" + i).is("input[type=checkbox]")) {
                                $("#" + i).prop("checked", !empty(returnArray[i]));
                            } else {
                                $("#" + i).val(returnArray[i]);
                            }
                        }
                    }
                    $("#_preferences").dialog({
                        closeOnEscape: true,
                        width: 1000,
                        modal: true,
                        draggable: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        show: 'fade',
                        title: 'Preferences',
                        buttons: {
                            Save: function (event) {
                                if ($("#_preferences_form").validationEngine("validate")) {
                                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_preferences", $("#_preferences_form").serialize(), function (returnArray) {
                                        $("#_preferences").dialog('close');
                                        location.reload();
                                    });
                                }
                            },
                            Cancel: function (event) {
                                $("#_preferences").dialog('close');
                            }
                        }
                    });
                });
            });
            $(document).on("click", "#_main_section_hider", function () {
                $(".header-hideable").toggleClass("hidden");
            });
            $("#requirements_opener").click(function () {
                $("#requirements_list").toggleClass("folded-up");
                $(this).removeClass("fa-chevron-double-left");
                $(this).removeClass("fa-chevron-double-down");
                if ($("#requirements_list").hasClass("folded-up")) {
                    $(this).addClass("fa-chevron-double-left");
                } else {
                    $(this).addClass("fa-chevron-double-down");
                }
            });
            $("#event_type_id").change(function () {
                if (empty($("#description").val()) || empty($("#description").data("custom_value"))) {
                    if (!empty($("#event_type_id").val())) {
                        $("#description").val($("#event_type_id option:selected").text()).data("custom_value", "");
                    }
                }
            });
            $("#description").change(function () {
                $(this).data("custom_value", "true");
            });
            $(document).on("click", "#view_custom", function () {
                $("#custom_calendars").trigger("click");
            });
            $(document).on("change", "#contact_id_selector", function () {
                if (!empty($("#contact_id").val())) {
                    $("#contact_id").trigger("change");
                    $(".contact-field").addClass("hidden");
                    setTimeout(function () {
                        getContactData();
                    }, 200);
                } else {
                    $(".contact-field").removeClass("hidden");
                    $("#custom_contact_data").html("");
                }
            });
            $(document).on("click", ".visitor-name", function () {
                const contactId = $(this).data("contact_id");
                if (empty(contactId)) {
                    return false;
                }

                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_contact_info&contact_id=" + contactId, function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        const eventDetails = returnArray['event'];
                        eventDetails['phone_numbers'] = returnArray['phone_numbers'];
                        eventDetails['attendees'] = 1;
                        eventDetails['country_id'] = 1000;
                        eventDetails['user_id'] = "<?= $GLOBALS['gUserId'] ?>";
                        eventDetails['user_id_display'] = "<?= getUserDisplayName($GLOBALS['gUserId']) ?>";
                        eventDetails['date_created'] = "<?= date("m/d/Y") ?>";
                        eventDetails['date_needed'] = "<?= date("m/d/Y") ?>";
                        eventDetails['frequency'] = "DAILY";
                        editEventDetails(eventDetails);
                    }
                });
                return false;
            });

            $(document).on("change", "#facility_id", function () {
                const eventTypeId = $(this).find("option:selected").data("event_type_id");
                if (empty($("#event_type_id").val())) {
                    $("#event_type_id").val(eventTypeId).trigger("change");
                }
                if (!empty($("#facility_id").val()) && !empty($("#start_time").val())) {
                    const reservationStart = $("#facility_id").find("option:selected").data("reservation_start") + "";
                    const startMinute = $("#start_time").find("option:selected").data("minute") + "";
                    if (reservationStart.length > 0 && reservationStart != startMinute) {
                        $("#start_time_warning").html("Reservation does not match this facility's start time, which is " + reservationStart + " past the hour");
                    } else {
                        $("#start_time_warning").html("");
                    }
                } else {
                    $("#start_time_warning").html("");
                }
            });
            $(document).on("change", "#start_time", function () {
                if (!empty($("#facility_id").val()) && !empty($("#start_time").val())) {
                    const reservationStart = $("#facility_id").find("option:selected").data("reservation_start") + "";
                    const startMinute = $("#start_time").find("option:selected").data("minute") + "";
                    if (reservationStart.length > 0 && reservationStart != startMinute) {
                        $("#start_time_warning").html("Reservation does not match this facility's start time, which is " + reservationStart + " past the hour");
                    } else {
                        $("#start_time_warning").html("");
                    }
                } else {
                    $("#start_time_warning").html("");
                }
            });

            $("#recurring").click(function () {
                if ($(this).prop("checked")) {
                    $("#recurring_schedule").show();
                    $("#frequency").trigger("change");
                    $("#_date_needed_row").hide();
                    $("#recurring_start_date").val($("#date_needed").val());
                } else {
                    $("#recurring_schedule").hide();
                    $("#_date_needed_row").show();
                    $("#date_needed").val($("#recurring_start_date").val());
                }
            });

            $("#frequency").change(function () {
                $("#_bymonth_row").hide();
                $("#_byday_row").hide();
                $("#byday_weekly_table").hide();
                $("#byday_monthly_table").hide();
                $(".byday-monthly-row").hide();
                $(".ordinal-day").val("");
                $(".weekday-select").val("");
                const thisValue = $(this).val();
                if (thisValue === "WEEKLY") {
                    $("#_byday_row").show();
                    $("#byday_weekly_table").show();
                } else if (thisValue === "MONTHLY") {
                    $("#_byday_row").show();
                    $("#byday_monthly_table").show();
                    $(".byday-monthly-row:first-child").show();
                } else if (thisValue === "YEARLY") {
                    $("#_bymonth_row").show();
                    $("#_byday_row").show();
                    $("#byday_monthly_table").show();
                    $(".byday-monthly-row:first-child").show();
                }
            });

            $(".ordinal-day").change(function () {
                if (!empty($(this).val())) {
                    $(".ordinal-day").each(function () {
                        if (!$(this).is(":visible")) {
                            $(this).closest(".byday-monthly-row").show();
                            return false;
                        } else if (empty($(this).val())) {
                            return false;
                        }
                    });
                }
            });

            $(".bymonth-month").click(function () {
                $(".bymonth-month:first").validationEngine("hideAll").removeClass("formFieldError");
            });

            $(".byday-weekday").click(function () {
                $(".byday-weekday:first").validationEngine("hideAll").removeClass("formFieldError");
            });

            $(document).on("click", "#next_day", function (event) {
                $("#calendar_date").val($.formatDate(addDays($("#calendar_date").val(), 1), "EEEE, MMMM d, yyyy"));
                $(".narrow-calendar").fullCalendar("next");
                sizeCalendarDate();
                return false;
            });

            $(document).on("click", "#today_day", function (event) {
                $("#calendar_date").val($.formatDate(new Date(), "EEEE, MMMM d, yyyy"));
                $(".narrow-calendar").fullCalendar("today");
                sizeCalendarDate();
                return false;
            });

            $(document).on("click", "#previous_day", function (event) {
                $("#calendar_date").val($.formatDate(addDays($("#calendar_date").val(), -1), "EEEE, MMMM d, yyyy"));
                $(".narrow-calendar").fullCalendar("prev");
                sizeCalendarDate();
                return false;
            });

            $("#calendar_date").change(function () {
                const dateObject = new Date($(this).val());
                $(".narrow-calendar").fullCalendar("gotoDate", dateObject);
                sizeCalendarDate();
                return false;
            });

            $(document).on("click", ".editable-list-remove,.editable-list-add", function () {
                if ($("#_phone_numbers_table tr").length > 2) {
                    $("#first_name").addClass("validate[required]");
                    $("#last_name").addClass("validate[required]");
                    if ($("#_first_name_label_tag").length === 0) {
                        $("#first_name_label").before("<span id='_first_name_label_tag' class='required-tag'>*</span>");
                    }
                    if ($("#_last_name_label_tag").length === 0) {
                        $("#last_name_label").before("<span id='_last_name_label_tag' class='required-tag'>*</span>");
                    }
                } else {
                    $("#first_name").removeClass("validate[required]");
                    $("#last_name").removeClass("validate[required]");
                    $("#_first_name_label_tag").remove();
                    $("#_last_name_label_tag").remove();
                }
                return false;
            });

			<?php if (canAccessPageCode("EVENTMAINT")) { ?>
            $("#_event_link").click(function () {
                window.open("/eventmaintenance.php?clear_filter=true&url_page=show&primary_id=" + $("#event_id").val(), "eventWindow");
                return false;
            });
			<?php } ?>


            $(document).on("click", "#new_reservation", function () {
                const eventDetails = {};
                eventDetails['attendees'] = 1;
                eventDetails['country_id'] = 1000;
                eventDetails['user_id'] = "<?= $GLOBALS['gUserId'] ?>";
                eventDetails['user_id_display'] = "<?= getUserDisplayName($GLOBALS['gUserId']) ?>";
                eventDetails['date_created'] = "<?= date("m/d/Y") ?>";
                eventDetails['date_needed'] = "<?= date("m/d/Y") ?>";
                eventDetails['frequency'] = "DAILY";
                editEventDetails(eventDetails);
                return false;
            });
            $(document).on("click", "td.available", function () {
                const eventDetails = {};
                eventDetails['attendees'] = 1;
                eventDetails['country_id'] = 1000;
                eventDetails['user_id'] = "<?= $GLOBALS['gUserId'] ?>";
                eventDetails['user_id_display'] = "<?= getUserDisplayName($GLOBALS['gUserId']) ?>";
                eventDetails['date_created'] = "<?= date("m/d/Y") ?>";
                eventDetails['date_needed'] = $.formatDate($(this).data("start_date").split(" ")[0], "MM/dd/yyyy");
                eventDetails['frequency'] = "DAILY";
                eventDetails['facility_id'] = $(this).closest("tr").data("facility_id");
                eventDetails['start_time'] = $(this).data("start_time");
                eventDetails['end_time'] = $(this).data("start_time") + .75;

                const controls = {};
                controls['disabled'] = [ "facility_id" ];
                controls['readonly'] = [ "date_needed" ];
                controls['autofocus'] = "first_name";

                editEventDetails(eventDetails, controls);
                return false;
            });
            $(document).on("click", ".happening-now", function () {
                const eventId = $(this).data("event_id");
                const facilityId = $(this).closest("tr").data("facility_id");
                editEvent(false, eventId, $(this), facilityId);
            });
            $(document).on("click", ".facility-group", function () {
                $(".facility-id").prop("checked", false);
                const facilityIds = ($(this).data("facility_ids") === "" ? false : $(this).data("facility_ids").split(","));
                if (facilityIds !== false) {
                    for (const i in facilityIds) {
                        $("#facility_id_" + facilityIds[i]).prop("checked", true);
                    }
                    $("#custom_calendars").trigger("click");
                }
                return false;
            });

			<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
            $(document).on("click", "#create_preset", function () {
                if (empty($("#group_description").val())) {
                    displayErrorMessage("A description is required");
                } else {
                    let facilityIds = "";
                    $(".facility-id:checked").each(function () {
                        if (!empty(facilityIds)) {
                            facilityIds += ",";
                        }
                        facilityIds += $(this).val();
                    });
                    if (!empty(facilityIds)) {
                        const postData = {};
                        postData['description'] = $("#group_description").val();
                        postData['facility_ids'] = facilityIds;
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_preset", postData, function (returnArray) {
                            if (!("error_message" in returnArray)) {
                                $("#create_preset_paragraph").before("<p class='facility-group-choice'><span class='fa fa-times delete-preset' data-facility_group_id='" +
                                    returnArray['facility_group_id'] + "'></span><a href='#' class='facility-group' data-facility_ids='" + facilityIds + "'>" + returnArray['description'] + "</a></p>");
                                $("#group_description").val("");
                            }
                        });
                    } else {
                        displayErrorMessage("No facilities are chosen");
                    }
                }
                return false;
            });

            $(document).on("click", ".delete-preset", function (event) {
                event.stopPropagation();
                const facilityGroupId = $(this).data("facility_group_id");
                const thisDeleteElement = $(this);
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_preset&facility_group_id=" + facilityGroupId, function (returnArray) {
                    $(thisDeleteElement).closest("p").remove();
                });
            });
			<?php } ?>

            $("#calendar_date").datepicker({
                beforeShow: function (input, inst) {
                    $(input).trigger("keydown");
                },
                onClose: function (dateText, inst) {
                    $(this).trigger("keydown");
                },
                showOn: "button",
                buttonText: "<span class='far fa-calendar-alt' id='set_date'></span>",
                constrainInput: false,
                dateFormat: "DD, MM d, yy",
                yearRange: "c-100:c+10"
            });

            $("#_management_header").find(".error-message").remove();

            $(".visit-type").click(function () {
                $(".visit-type").removeClass("selected");
                $("#visit_type_id").val($(this).data("visit_type_id"));
                $(this).addClass("selected");
                return false;
            });

            $(document).on("change", "#visitor_filter", function () {
                $("#visitor_header").html($(this).find("option:selected").data("title"));
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=visitor_filter&visitor_filter=" + $(this).val(), function (returnArray) {
                    filterVisitorLog();
                    showContactsOnPremises();
                });
            });
            $(document).on("click", "button.left-now", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=visitor_check_out&visitor_log_id=" + $(this).closest("tr").data("visitor_log_id"), function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        updateInformation()
                        showContactsOnPremises();
                    }
                });
            });
            $("#search_text").keyup(function () {
                if (!empty(searchTimer)) {
                    clearTimeout(searchTimer);
                    searchTimer = null;
                }
                const searchText = $(this).val();
                $("#_search_results").hide().html(searchText);
                if (searchText.length > 2) {
                    searchTimer = setTimeout(function () {
                        $("#_search_results").show().html("<p>Searching...</p>");
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=search", { search_text: $("#search_text").val() }, function (returnArray) {
                            if ("search_results" in returnArray) {
                                $("#_search_results").html(returnArray['search_results']);
                            }
                        });
                    }, 500);
                }
            });
            $(document).on("click", ".select-contact", function () {
                $("#check_in_contact_id").val($(this).data("contact_id"));
                $("#check_in_email_address").val($(this).data("email_address"));
                $("#check_in_first_name").val($(this).data("first_name"));
                $("#check_in_last_name").val($(this).data("last_name"));
                $("#_check_in_submit").trigger("click");
                return false;
            });
            $(document).on("click", "#_check_in_submit", function () {
                if ($(".visit-type").length === 1) {
                    $("#visit_type_id").val($(".visit-type").data("visit_type_id"));
                }
                if ($(".visit-type").length > 0 && empty($("#visit_type_id").val())) {
                    displayErrorMessage("Visit Type is Required");
                    return false;
                }
                if ($("#_check_in_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=visitor_check_in", $("#_check_in_form").serialize(), function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            $("#clearing_form").val("1");
                            $("#_check_in_form").find("input").val("").focus().blur();
                            $(".visit-type").removeClass("selected");
                            $("#_search_results").hide();
                            setTimeout(function () {
                                $("#clearing_form").val("");
                            }, 200);
                            updateInformation();
                        }
                    });
                }
                return false;
            });
            $("#calendar_date").keydown(function () {
                sizeCalendarDate();
            });
            $(".calendar-tab").click(function () {
                if ($(this).attr("id") === "happening_now" || $(this).attr("id") === "custom_groups") {
                    $("#set_day_buttons").addClass("unseen");
                    $("#set_date").addClass("hidden");
                    $("#calendar_date").val($.formatDate(new Date(), "EEEE, MMMM d, yyyy"));
                    $(".narrow-calendar").fullCalendar("today");
                    sizeCalendarDate();
                } else {
                    $("#set_day_buttons").removeClass("unseen");
                    $("#set_date").removeClass("hidden");
                }
                $(".calendar-tab").removeClass("selected");
                $(this).addClass("selected");
                loadCalendar();
            }).first().trigger("click");
            $(document).on("click", ".facility-list-header", function (event) {
                const doCheck = !$(this).closest("div").find("input[type=checkbox]:first").prop("checked");
                if (!doCheck) {
                    $(this).closest("div").find("input[type=checkbox]").prop("checked", false);
                } else {
                    $(this).closest("div").find("input[type=checkbox]").each(function () {
                        $(this).prop("checked", true);
                    });
                }
            });

			<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
            $(document).on("click", ".delete-event", function (event) {
                event.stopPropagation();
                deleteEvent($(this).data("calendar_event_id"), $(this).data("calendar_id"));
            });
			<?php } ?>

            setInterval(function() {
                $("#today_time").html($.formatDate(new Date(), "h:mm a"));
            },1000);
            updateInformation();
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            let updateTimer = null;
            let updateCounter = 0;
            let searchTimer = null;

            function showContactsOnPremises() {
                $(".happening-now").find("span.on-premises").remove();
                $("#visitor_table").find("tr.data-row").not(".visitor-left").each(function () {
                    const contactId = $(this).find(".visitor-name").data("contact_id");
                    if ($(".happening-now.contact-id-" + contactId).length > 0) {
                        $(".happening-now.contact-id-" + contactId).prepend("<span class='fad fa-user-check on-premises'></span>");
                    }
                });
            }

            function getContactData() {
                $("#custom_contact_data").html("");
                const contactId = $("#contact_id").val();
                if (empty(contactId)) {
                    return;
                }
                const eventId = $("#_event_form").find("#event_id").val();
                if (empty(eventId)) {
                    return;
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_custom_contact_data&contact_id=" + contactId + "&event_id=" + eventId, function (returnArray) {
                    if ("custom_contact_data" in returnArray) {
                        $("#custom_contact_data").html(returnArray['custom_contact_data']);
                    }
                });
            }

            function filterVisitorLog() {
                const visitorFilter = $("#visitor_filter").val();
                if (empty(visitorFilter)) {
                    $("#visitor_table").find("tr").removeClass("hidden");
                } else {
                    $("#visitor_table").find("tr").each(function () {
                        if ($(this).hasClass(visitorFilter)) {
                            $(this).addClass("hidden");
                        } else {
                            $(this).removeClass("hidden");
                        }
                    })
                }
                if (visitorFilter == "not-waiting") {
                    $("#visitor_table").find("tr").each(function () {
                        if ($(this).hasClass("hidden")) {
                            return true;
                        }
                        const contactId = $(this).find(".visitor-name").data("contact_id");
                        if ($(".upcoming-event.contact-id-" + contactId).length > 0) {
                            $(this).addClass("hidden");
                        }
                    })
                }
            }

            function updateInformation() {
                if (updateTimer != null) {
                    clearTimeout(updateTimer);
                }
                const todayDate = $.formatDate(new Date(), "MMMM d, yyyy");
                if (todayDate !== $("#today_date").html()) {
                    $("#today_date").html(todayDate);
                    $("#today_day").trigger("click");
                }
                $("body").addClass("no-waiting-for-ajax");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_information&hash=" + $("#information_hash").val(), function (returnArray) {
                    if (!("no_change" in returnArray)) {
                        for (const i in returnArray) {
                            if ($("#" + i).is("input")) {
                                $("#" + i).val(returnArray[i]);
                            } else {
                                $("#" + i).html(returnArray[i]);
                            }
                        }
                        if ("visitor_list_rows" in returnArray) {
                            $("#visitor_table").find("tr.data-row").remove();
                            for (const i in returnArray['visitor_list_rows']) {
                                $("#visitor_table").append("<tr class='data-row" + (returnArray['visitor_list_rows'][i]['left'] === "true" ? " visitor-left" : "") +
                                    (returnArray['visitor_list_rows'][i]['waiting'] === "true" ? "" : " not-waiting") + "' data-visitor_log_id='" +
                                    returnArray['visitor_list_rows'][i]['visitor_log_id'] + "' data-estimated_minutes='" +
                                    returnArray['visitor_list_rows'][i]['estimated_minutes'] + "'><td>" + returnArray['visitor_list_rows'][i]['in_time'] + "</td><td class='event-color-id " +
                                    returnArray['visitor_list_rows'][i]['event_color_class'] + "'></td><td><p class='visitor-name' data-contact_id='" +
                                    returnArray['visitor_list_rows'][i]['contact_id'] + "'>" + returnArray['visitor_list_rows'][i]['name'] +
                                    "</p></td><td><span class='fad fa-clock set-waiting-time'></span></td><td>" + returnArray['visitor_list_rows'][i]['out_time'] + "</td></tr>");
                            }
                            $(".visitor-name").draggable({
                                helper: "clone",
                                appendTo: "body"
                            });
                        }
                        if ("just_checked_in" in returnArray) {
                            $("#just_checked_in").html(returnArray['just_checked_in']);
                            if (empty(returnArray['just_checked_in'])) {
                                $("#just_checked_in").hide();
                            } else {
                                $("#just_checked_in").show();
                            }
                        }
                        setTimeout(function () {
                            $("#visitor_filter").trigger("change");
                            showContactsOnPremises();
                        }, 200);
                    }
                    updateCounter++;
                    if (updateCounter % 6 == 0) {
                        loadCalendar();
                    }
                });
                if (updateTimer != null) {
                    clearTimeout(updateTimer);
                }
                updateTimer = setTimeout(function () {
                    updateInformation();
                }, 10000);
            }

            let narrowCalendars = {};

            function editEventDetails(eventDetails, controls) {
                $("#_event_form").clearForm();
                $("#_event_form").find("select,input").removeAttr("autofocus");
                $("#recurring_schedule").hide();
                if (!("event_id" in eventDetails)) {
                    $("#_event_link").hide();
                } else {
                    $("#_event_link").show();
                }
                $("#_ignore_conflicts_row,#conflict").hide();
                for (const i in eventDetails) {
                    if ($("#" + i).is("input[type=checkbox]")) {
                        $("#" + i).prop("checked", eventDetails[i] === 1);
                    } else {
                        $("#" + i).val(eventDetails[i]);
                    }
                }
                if ($("#recurring").prop("checked")) {
                    $("#recurring_schedule").show();
                    $("#frequency").trigger("change");
                }
                $("#_phone_numbers_table tr").not(":first").not(":last").remove();
                if ("phone_numbers" in eventDetails) {
                    for (const j in eventDetails["phone_numbers"]) {
                        addEditableListRow("phone_numbers", eventDetails["phone_numbers"][j]);
                    }
                }

                // reset values that can be readonly or hidden

                $("#_event_form").find(".hidden").removeClass("hidden");
                $("#_event_form").find(".readonly-control").removeClass("readonly-control").prop("readonly", false);
                $("#_event_form").find(".disabled-control").removeClass("disabled-control").prop("disabled", false);
                if (!empty(eventDetails['contact_id'])) {
                    $("#contact_id").trigger("change");
                    $(".contact-field").addClass("hidden");
                    setTimeout(function () {
                        getContactData();
                    }, 200);
                } else {
                    $(".contact-field").removeClass("hidden");
                    $("#custom_contact_data").html("");
                }

                // set fields to hidden or readonly or disabled

                if (empty(controls)) {
                    controls = {};
                }
                if ("hidden" in controls) {
                    for (const i in controls['hidden']) {
                        $("#" + controls['hidden'][i]).addClass("hidden");
                    }
                }
                if ("readonly" in controls) {
                    for (const i in controls['readonly']) {
                        $("#" + controls['readonly'][i]).addClass("readonly-control").prop("readonly", true).closest(".basic-form-line").find(".ui-datepicker-trigger").addClass("hidden");
                    }
                }
                if ("disabled" in controls) {
                    for (const i in controls['disabled']) {
                        $("#" + controls['disabled'][i]).addClass("disabled-control").prop("disabled", true);
                    }
                }
                if ("change" in controls) {
                    for (const i in controls['change']) {
                        $("#" + controls['change'][i]).trigger("change");
                    }
                }
                if ("autofocus" in controls) {
                    $("#" + controls['autofocus']).attr("autofocus", true);
                }
                $("#facility_id").trigger("change");
                if ("requirements" in eventDetails) {
                    for (const i in eventDetails['requirements']) {
                        $("#event_requirement_id_" + eventDetails['requirements'][i]['event_requirement_id']).prop("checked", true);
                        $("#event_requirement_notes_" + eventDetails['requirements'][i]['event_requirement_id']).val(eventDetails['requirements'][i]['notes']);
                    }
                }

                const buttonObjects = {};
                buttonObjects['Save'] = function (event) {
                    if (empty($("#start_time").val())) {
                        $("#start_time").val("0");
                    }
                    if (empty($("#end_time").val())) {
                        $("#end_time").val("23.75");
                    }
                    if (!empty($("#start_time").val()) && parseInt($("#end_time").val()) < parseInt($("#start_time").val())) {
                        $("#end_time").validationEngine("showPrompt", "End time must be after start time", "error", "topLeft", true);
                        return false;
                    }
                    if ($("#recurring").prop("checked")) {
                        if ($("#frequency").val() === "WEEKLY" && $(".byday-weekday:checked").length === 0) {
                            $(".byday-weekday:first").validationEngine("showPrompt", "Choose a weekday", "error", "topLeft", true);
                            return false;
                        }
                        if ($("#frequency").val() === "MONTHLY" && empty($("#ordinal_day_1").val())) {
                            $("#ordinal_day_1").validationEngine("showPrompt", "Choose a day of the month", "error", "topLeft", true);
                            return false;
                        }
                        if ($("#frequency").val() === "YEARLY" && $(".bymonth-month:checked").length === 0) {
                            $(".bymonth-month:first").validationEngine("showPrompt", "Choose a month", "error", "topLeft", true);
                            return false;
                        }
                        if ($("#frequency").val() === "YEARLY" && empty($("#ordinal_day_1").val())) {
                            $("#ordinal_day_1").validationEngine("showPrompt", "Choose a day of the month", "error", "topLeft", true);
                            return false;
                        }
                    }
                    if ($("#_event_form").validationEngine("validate")) {
                        const startMinute = (($("#start_time").val() - Math.floor($("#start_time").val())) * 60);
                        const startTime = ('calendar_start' in controls ? controls['calendar_start'] : $("#date_needed").val() + " " +
                            Math.floor($("#start_time").val()) + ":" + (startMinute < 10 ? "0" : "") + startMinute + ":00");
                        const endMinute = (($("#end_time").val() - Math.floor($("#end_time").val())) * 60);
                        const endTime = ('calendar_end' in controls ? controls['calendar_end'] : $("#date_needed").val() + " " +
                            (Math.floor($("#end_time").val() / 2)) + ":" + (endMinute < 10 ? "0" : "") + endMinute + ":00");
                        if (empty($("#event_id").val())) {
                            createNewEvent(("calendar_id" in controls ? controls['calendar_id'] : false), startTime, endTime, $("#facility_id").val());
                        } else {
                            updateEvent(controls['calendar_id'], controls['original_event']);
                        }
                    }
                };

                if (!empty($("#event_id").val()) && (eventDetails['facility_count'] + eventDetails['facility_repeat_count']) === 1) {
                    buttonObjects['Delete'] = function (event) {
                        deleteEvent($("#event_id").val(), false, eventDetails['recurring']);
                    };
                }
                buttonObjects['Cancel'] = function (event) {
                    $("#_event_details").dialog('close');
                };

                if (empty(eventDetails['contact_id']) || $("#_visitor_sidebar").hasClass("hidden")) {
                    $("#check_in_contact_wrapper").addClass("hidden");
                } else {
                    $("#check_in_contact_wrapper").removeClass("hidden");
                }

                if ($(".contact-visit-type").length > 3) {
                    $("#_contact_visit_type_id_row").addClass("hidden");
                }
                $("#_event_details").dialog({
                    closeOnEscape: true,
                    width: 1000,
                    modal: true,
                    draggable: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    show: 'fade',
                    title: 'Event Details',
                    open: function (event, ui) {
                        $("#_event_form").find("#event_type_id").focus();
                        $("#custom_contact_data").html("");
                    },
                    close: function (event, ui) {
                        $("#_event_form").validationEngine("hideAll");
                        if ("calendar_id" in controls && controls['calendar_id'] !== false) {
                            narrowCalendars[controls['calendar_id']].fullCalendar('unselect');
                        }
                        setTimeout(function () {
                            filterVisitorLog();
                            showContactsOnPremises();
                        }, 200);
                    },
                    buttons: buttonObjects
                });
            }

            function sizeCalendarDate() {
                if ($("#event_overview").hasClass("selected") && $("#calendar_date").val() != $("#save_calendar_date").val()) {
                    $("#save_calendar_date").val($("#calendar_date").val());
                    loadCalendar();
                }
                setTimeout(function () {
                    $("#calendar_date_sizer").html($("#calendar_date").val());
                    setTimeout(function () {
                        $("#calendar_date").width($("#calendar_date_sizer").width() + 20);
                        $("#calendar_date_wrapper").css("visibility", "visible");
                    }, 200);
                }, 200);
            }

            function loadCalendar() {
                $(".full-calendar-hide").removeClass("invisible");
                $("#_calendar_wrapper").addClass("invisible");
                const calendarId = $(".calendar-tab.selected").attr("id");
                const calendarDate = $("#calendar_date").val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_calendar_content&calendar_id=" + calendarId + "&calendar_date=" + encodeURIComponent(calendarDate), $("#_custom_group_form").serialize(), function (returnArray) {
                    if ("calendar" in returnArray) {
                        $("#_calendar_wrapper").html(returnArray['calendar']);
                    }
                    $(".available").droppable({
                        drop: function (event, ui, helper) {
                            const contactId = $(ui.draggable).data("contact_id");
                            if (empty(contactId)) {
                                return false;
                            }
                            const dateNeeded = $.formatDate($(this).data("start_date").substring(0, 10), "MM/dd/yyyy");
                            const startHour = $(this).data("start_time");
                            const endHour = $(this).data("start_time") + .75;
                            const facilityId = $(this).closest("tr").data("facility_id");

                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_contact_info&contact_id=" + contactId, function (returnArray) {
                                if (!("error_message" in returnArray)) {
                                    const eventDetails = returnArray['event'];
                                    eventDetails['phone_numbers'] = returnArray['phone_numbers'];
                                    eventDetails['attendees'] = 1;
                                    eventDetails['country_id'] = 1000;
                                    eventDetails['user_id'] = "<?= $GLOBALS['gUserId'] ?>";
                                    eventDetails['user_id_display'] = "<?= getUserDisplayName($GLOBALS['gUserId']) ?>";
                                    eventDetails['date_created'] = "<?= date("m/d/Y") ?>";
                                    eventDetails['date_needed'] = dateNeeded;
                                    eventDetails['frequency'] = "DAILY";
                                    eventDetails['start_time'] = startHour;
                                    eventDetails['end_time'] = endHour;
                                    eventDetails['facility_id'] = facilityId;
                                    eventDetails['contact_id'] = returnArray['event']['contact_id'];

                                    const controls = {};
                                    controls['disabled'] = [ "facility_id" ];
                                    controls['readonly'] = [ "date_needed" ];
                                    controls['autofocus'] = "first_name";

                                    editEventDetails(eventDetails, controls);
                                }
                            });
                        }
                    });
                    if ("facility_ids" in returnArray) {
                        if (calendarId === "custom_calendars" && returnArray['facility_ids'].length === 1) {
                            $("#_calendar_wrapper").append("<div class='narrow-wrapper full-calendar-wrapper'><p class='calendar-title-small'>" + returnArray['facility_ids'][0]['description'] + "</p><div id='full_calendar' class='narrow-calendar full-calendar' data-facility_id='" + returnArray['facility_ids'][0]['facility_id'] + "'></div></div>");
                            $("#_calendar_wrapper").append("<div class='clear-div'></div>");
                            $(".full-calendar-hide").addClass("invisible");
                            initializeCalendars(true);
                        } else {
                            for (const i in returnArray['facility_ids']) {
                                $("#_calendar_wrapper").append("<div class='narrow-wrapper'><p class='calendar-title-small'>" + returnArray['facility_ids'][i]['description'] + "</p><div id='narrow_calendar_" + i + "' class='narrow-calendar' data-facility_id='" + returnArray['facility_ids'][i]['facility_id'] + "'></div></div>");
                            }
                            $("#_calendar_wrapper").append("<div class='clear-div'></div>");
                            initializeCalendars();
                        }
                    }
                    $("#_calendar_wrapper").removeClass("invisible");
                    while ($(".continuing-event").length > 0) {
                        const thisCell = $(".continuing-event").first();
                        let colspan = thisCell.prev("td").attr("colspan");
                        if (empty(colspan) || isNaN(colspan)) {
                            colspan = 2;
                        } else {
                            colspan++;
                        }
                        thisCell.prev("td").attr("colspan", colspan);
                        thisCell.remove();
                    }
                });
            }

            function goToDate(dateString) {
                if (!empty(dateString)) {
                    const dateObject = new Date(dateString);
                    $(".narrow-calendar").fullCalendar("gotoDate", dateObject);
                }
            }

            function initializeCalendars(fullCalendar) {
                if (empty(fullCalendar)) {
                    fullCalendar = false;
                }
                const seconds = new Date() / 1000;
                $("#_calendar_display").data("last_initialize", seconds);

                const header = (fullCalendar ? { left: 'prev,next today', center: 'title', right: 'month,agendaWeek,agendaDay' } : false);
                $("#_calendar_display").data("view", "agendaDay");
                $(".narrow-calendar").fullCalendar("destroy");
                narrowCalendars = {};
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=get_events&start=<?= date("Y-m-d") ?>&end=<?= date("Y-m-d", strtotime("tomorrow")) ?>", function (returnArray) {
                    $(".narrow-calendar").each(function () {
                        const thisId = $(this).attr("id");
                        narrowCalendars[thisId] = $(this).fullCalendar({
                            allDaySlot: false,
                            height: 600,
                            defaultDate: $.formatDate(new Date($("#calendar_date").val()), "yyyy-MM-dd"),
                            defaultView: 'agendaDay',
                            allDayDefault: false,
                            columnFormat: "ddd, MMM DD, YYYY",
                            header: header,
                            slotDuration: '00:15:00',
							<?php
							$minTime = $this->getPageTextChunk("calendars_start_time");
							if (!empty($minTime)) {
							?>
                            minTime: '<?= $minTime ?>',
							<?php
							}
							$maxTime = $this->getPageTextChunk("calendars_end_time");
							if (!empty($maxTime)) {
							?>
                            maxTime: '<?= $maxTime ?>',
							<?php
							}
							?>
                            events: "<?= $GLOBALS['gLinkUrl'] ?>?allow_session_data=true&url_action=get_events&facility_id=" + $("#" + thisId).data("facility_id"),
							<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                            eventResize: function (event, dayDelta, revertFunc, jsEvent, ui, view) {
                                const postData = {};
                                postData['old_ids'] = ("ids" in event ? event.ids : "");
                                postData['start'] = event.start.format("YYYY-MM-DD HH:mm:ss");
                                postData['end'] = event.end.format("YYYY-MM-DD HH:mm:ss");
                                postData['facility_id'] = narrowCalendars[thisId].data("facility_id");
                                postData['event_id'] = event.event_id;
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=event_drag", postData, function (returnArray) {
                                    if ("error_message" in returnArray) {
                                        revertFunc();
                                    } else {
                                        if ("ids" in returnArray) {
                                            event.ids = returnArray['ids'];
                                        } else {
                                            event.ids = "";
                                        }
                                    }
                                });
                            },
                            eventClick: function (event, jsEvent, view) {
                                if ($(jsEvent.target).attr("id") === "events_layer") {
                                    editEvent(thisId, event.event_id, event, $("#" + thisId).data("facility_id"));
                                }
                            },
                            eventDrop: function (event, dayDelta, allDay, revertFunc, jsEvent, ui, view) {
                                const postData = {};
                                postData['old_ids'] = ("ids" in event ? event.ids : "");
                                postData['start'] = event.start.format("YYYY-MM-DD HH:mm:ss");
                                postData['end'] = event.end.format("YYYY-MM-DD HH:mm:ss");
                                postData['facility_id'] = narrowCalendars[thisId].data("facility_id");
                                postData['event_id'] = event.event_id;
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=event_drop", postData, function (returnArray) {
                                    if ("error_message" in returnArray) {
                                        revertFunc();
                                    } else {
                                        if ("ids" in returnArray) {
                                            event.ids = returnArray['ids'];
                                        } else {
                                            event.ids = "";
                                        }
                                    }
                                }, function (returnArray) {
                                    revertFunc();
                                });
                            },
                            selectable: true,
                            selectHelper: true,
                            select: function (start, end, allDay) {
                                const eventDetails = {};
                                eventDetails['attendees'] = 1;
                                eventDetails['country_id'] = 1000;
                                eventDetails['user_id'] = "<?= $GLOBALS['gUserId'] ?>";
                                eventDetails['user_id_display'] = "<?= getUserDisplayName($GLOBALS['gUserId']) ?>";
                                eventDetails['date_created'] = "<?= date("m/d/Y") ?>";
                                eventDetails['date_needed'] = $.formatDate(addDays($("#calendar_date").val(), 0), "MM/dd/yyyy");
                                eventDetails['frequency'] = "DAILY";
                                let startHour = start.format("H");
                                const startMinute = start.format("m");
                                if (startMinute > 0 && startMinute <= 15) {
                                    startHour = parseFloat(startHour) + .25;
                                } else if (startMinute > 15 && startMinute <= 30) {
                                    startHour = parseFloat(startHour) + .5;
                                } else if (startMinute > 30) {
                                    startHour = parseFloat(startHour) + .75;
                                }
                                if (startHour < 0) {
                                    startHour = 0;
                                }
                                eventDetails['start_time'] = startHour;
                                let endHour = end.format("H");
                                const endMinute = end.format("m");
                                if (endMinute > 0 && endMinute <= 15) {
                                    endHour = parseFloat(endHour) + .25;
                                } else if (endMinute > 15 && endMinute <= 30) {
                                    endHour = parseFloat(endHour) + .5;
                                } else if (endMinute > 30) {
                                    endHour = parseFloat(endHour) + .75;
                                }
                                if (endHour < 0) {
                                    endHour = 23.75;
                                }
                                eventDetails['end_time'] = endHour - .25;
                                eventDetails['facility_id'] = $("#" + thisId).data("facility_id");

                                const controls = {};
                                controls['disabled'] = [ "facility_id" ];
                                controls['readonly'] = [ "date_needed" ];
                                controls['autofocus'] = "first_name";
                                controls['calendar_id'] = thisId;
                                controls['calendar_start'] = start;
                                controls['calendar_end'] = end;

                                editEventDetails(eventDetails, controls);
                            },
                            eventMouseover: function (event, jsEvent, view) {
                                if (event.editable || event['deletable']) {
                                    const layer = "<div id='events_layer' class='fc-transparent' style='position:absolute; width:100%; height:90%; top:-1px; text-align:right; z-index:100'><a>" +
                                        "<span class='delete-event fa fa-times' data-calendar_id='" + thisId + "' data-calendar_event_id='" + event.id + "' /></div>";
                                    $(this).append(layer);
                                }
                            },
                            eventMouseout: function (event, jsEvent, view) {
                                $("#events_layer").remove();
                            },
							<?php } ?>
                            editable: true
                        });
                    });
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=clear_cached_events");
                });
            }

			<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
            function updateEvent(calendarId, originalEvent) {
                $("#facility_id").prop("disabled", false);
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_event", $("#_event_form").serialize(), function (returnArray) {
                    if ("conflict" in returnArray) {
                        $("#_ignore_conflicts_row,#conflict").show();
                        $("#conflict").html(returnArray['conflict']);
                        return;
                    }
                    $("#_event_details").dialog('close');
                    loadCalendar();
                });
            }

            function editEvent(calendarId, eventId, originalEvent, facilityId) {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_event_details&event_id=" + eventId + "&facility_id=" + facilityId, function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        const eventDetails = returnArray['event'];
                        eventDetails['phone_numbers'] = returnArray['phone_numbers'];
                        eventDetails['facility_id'] = facilityId;
                        eventDetails['requirements'] = returnArray['requirements'];
                        eventDetails['contact_id'] = returnArray['event']['contact_id'];
                        if (calendarId === false) {
                            eventDetails['instance_date'] = "<?= date("m/d/Y") ?>";
                        } else {
                            eventDetails['instance_date'] = $("#" + calendarId).fullCalendar("getDate").format("MM/DD/YYYY");
                        }

                        const controls = {};
                        controls['disabled'] = [];
                        if (eventDetails['facility_count'] > 1 || eventDetails['facility_repeat_count'] > 0) {
                            controls['disabled'].push("facility_id");
                            $("select.event-time-field").each(function () {
                                controls['disabled'].push($(this).attr("id"));
                            });
                        }
                        $("select.recurring-event-field,input[type=checkbox].recurring-event-field").each(function () {
                            controls['disabled'].push($(this).attr("id"));
                        });
                        controls['readonly'] = [];
                        $("input[type=text].recurring-event-field").each(function () {
                            controls['readonly'].push($(this).attr("id"));
                        });
                        if (eventDetails['facility_count'] > 1 || eventDetails['facility_repeat_count'] > 0) {
                            $("input.event-time-field").each(function () {
                                controls['readonly'].push($(this).attr("id"));
                            });
                        }
                        controls['hidden'] = [];
                        if ("hide_recurring" in returnArray) {
                            controls['hidden'].push("recurring_schedule");
                            controls['hidden'].push("_recurring_row");
                        } else if ("recurring" in returnArray && returnArray['recurring'] === "1") {
                            controls['change'] = [];
                            controls['change'].push("frequency");
                            controls['hidden'].push("_date_needed_row");
                        }
                        controls['calendar_id'] = calendarId;
                        controls['original_event'] = originalEvent;
                        controls['autofocus'] = "first_name";

                        editEventDetails(eventDetails, controls);
                    }
                });
            }

            function createNewEvent(calendarId, startTime, endTime, facilityId) {
                let postData;
                if (calendarId === false) {
                    postData = $("#_event_form").serialize() + "&start=" + startTime + "&end=" + endTime + "&facility_id=" + facilityId;
                } else {
                    postData = $("#_event_form").serialize() + "&start=" + startTime.format("YYYY-MM-DD HH:mm:ss") + "&end=" + endTime.format("YYYY-MM-DD HH:mm:ss") + "&facility_id=" + $("#" + calendarId).data("facility_id");
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=add_event", postData, function (returnArray) {
                    if (returnArray === false || "error_message" in returnArray) {
                        if (calendarId !== false) {
                            $("#" + calendarId).fullCalendar('unselect');
                        }
                        return;
                    }
                    if ("conflict" in returnArray) {
                        $("#_ignore_conflicts_row,#conflict").show();
                        $("#conflict").html(returnArray['conflict']);
                        return;
                    }
                    $("#_event_details").dialog('close');
                    if ("error_message" in returnArray) {
                        if (calendarId !== false) {
                            $("#" + calendarId).fullCalendar('unselect');
                        }
                    } else {
                        $("#_event_form").clearForm();
                        if (calendarId !== false) {
                            $("#" + calendarId).fullCalendar('renderEvent', returnArray['new_event'], false);
                            $("#" + calendarId).fullCalendar('unselect');
                        } else {
                            loadCalendar();
                        }
                    }
                }, function (returnArray) {
                    $("#" + calendarId).fullCalendar('unselect');
                });
            }

            function deleteEvent(eventId, calendarId, recurringEvent) {
                let event;
                let instanceDate;
                if (calendarId !== false) {
                    event = $("#" + calendarId).fullCalendar('clientEvents', eventId)[0];
                    recurringEvent = ("recurring" in event && event['recurring']);
                    instanceDate = event.instance_date;
                } else {
                    instanceDate = $("#instance_date").val();
                }
                if (recurringEvent) {
                    $('#_confirm_delete_event_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        title: 'Delete Event?',
                        width: 600,
                        buttons: {
                            "Only This One": function (event) {
                                deleteEventConfirmed(eventId, calendarId, recurringEvent, instanceDate, false);
                                $("#_confirm_delete_event_dialog").dialog('close');
                                if ($("#_event_details").hasClass('ui-dialog-content') && $('#_event_details').dialog('isOpen')) {
                                    $("#_event_details").dialog('close');
                                }
                            },
                            "All Future": function (event) {
                                deleteEventConfirmed(eventId, calendarId, recurringEvent, instanceDate, true);
                                $("#_confirm_delete_event_dialog").dialog('close');
                                if ($("#_event_details").hasClass('ui-dialog-content') && $('#_event_details').dialog('isOpen')) {
                                    $("#_event_details").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_confirm_delete_event_dialog").dialog('close');
                            }
                        }
                    });
                } else {
                    $('#_confirm_delete_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                        title: 'Delete Record?',
                        buttons: {
                            Yes: function (event) {
                                deleteEventConfirmed(eventId, calendarId, recurringEvent, false, false);
                                $("#_confirm_delete_dialog").dialog('close');
                                if ($("#_event_details").hasClass('ui-dialog-content') && $('#_event_details').dialog('isOpen')) {
                                    $("#_event_details").dialog('close');
                                }
                            },
                            Cancel: function (event) {
                                $("#_confirm_delete_dialog").dialog('close');
                            }
                        }
                    });
                }
            }

            function deleteEventConfirmed(eventId, calendarId, recurringEvent, instanceDate, allFuture) {
                let event = [];
                if (calendarId !== false) {
                    event = $("#" + calendarId).fullCalendar('clientEvents', eventId)[0];
                    eventId = event.event_id;
                }
                const postData = {};
                postData['old_ids'] = ("ids" in event ? event.ids : "");
                postData['facility_id'] = (calendarId === false ? $("#facility_id").val() : $("#" + calendarId).data("facility_id"));
                postData['event_id'] = eventId;
                if (recurringEvent) {
                    postData['recurring'] = true;
                    postData['instance_date'] = instanceDate;
                    postData['all_future'] = (allFuture ? 1 : 0);
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_event", postData, function (returnArray) {
                    if (!("error_message" in returnArray)) {
                        if (calendarId !== false) {
                            $("#" + calendarId).fullCalendar('refetchEvents');
                        } else {
                            loadCalendar();
                        }
                        setTimeout(function () {
                            filterVisitorLog();
                            showContactsOnPremises();
                        }, 200)
                    }
                });
            }
			<?php } ?>

            $(window).ready(function () {
                sizeCalendarDate();
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #check_in_form_wrapper.collapsed #_check_in {
                display: none;
            }
            #check_in_form_wrapper h2 {
                cursor: pointer;
                position: relative;
            }
            #check_in_form_wrapper h2 span {
                position: absolute;
                top: 0;
                right: 10px;
                display: block;
            }
            #check_in_form_wrapper h2 span.fa-caret-left {
                display: none;
            }
            #check_in_form_wrapper.collapsed h2 span.fa-caret-left {
                display: block;
            }
            #check_in_form_wrapper h2 span.fa-caret-down {
                display: block;
            }
            #check_in_form_wrapper.collapsed h2 span.fa-caret-down {
                display: none;
            }
            #visitor_list p {
                line-height: 1;
            }
            #start_time_warning {
                color: rgb(250, 140, 25);
            }

            .happening-now span.on-premises {
                color: rgb(0, 0, 0);
                margin: 0 5px;
                font-size: .8rem;
            }

            #_scheduler_header {
                position: relative;
            }

            #_controls_wrapper {
                position: absolute;
                bottom: 0;
                right: 0;
                z-index: 9999;
            }

            #_scheduler_header p {
                margin: 0;
                position: relative;
            }

            #happening_now_table td.facility-type-header {
                padding-top: 5px;
            }

            .fc-time-grid .fc-slats td {
                height: 10px;
            }

            #requirements_wrapper {
                margin-bottom: 20px;
            }

            #requirements_opener {
                margin-left: 20px;
                color: rgb(200, 200, 200);
                font-size: .8rem;
                cursor: pointer;
            }

            #requirements_list {
                border-bottom: 1px solid rgb(200, 200, 200);
                margin-bottom: 20px;
            }

            #requirements_list.folded-up {
                display: none;
            }

            #_management_content {
                background-color: rgb(230, 230, 230);
            }

            #_main_content {
                padding: 0;
            }

            #_dashboard_wrapper {
                min-height: 100vh;
                position: relative;
            }

            #_main_section {
                width: calc(100% - 420px);
                min-height: 100vh;
                position: relative;
            }

            <?php if ($this->iHideVisitorSidebar) { ?>
            #_main_section {
                width: 100%;
            }

            <?php } ?>

            #new_reservation, #_main_section_hider, #_preferences_icon, #_legend_icon {
                cursor: pointer;
                font-size: 1.2rem;
                margin-left: 10px;
            }

            #_visitor_sidebar {
                width: 400px;
                position: absolute;
                top: 40px;
                right: 0;
                height: calc(100vh - 80px);
                background-color: rgb(220, 220, 220);
                padding: 20px;
                overflow: scroll;
            }

            #_visitor_sidebar p {
                font-size: 1.4rem;
                font-weight: bold;
                color: rgb(100, 100, 100);
            }

            #_management_header {
                display: none;
            }

            #today_time {
                display: block;
                float: right;
            }

            #current_visitors, #total_visitors {
                width: 45%;
                margin: 10px 2%;
                display: inline-block;
                padding: 10px;
                background-color: rgb(108, 178, 95);
                -webkit-box-shadow: 3px 3px 4px 0 rgba(0, 0, 0, 0.4);
                -moz-box-shadow: 3px 3px 4px 0 rgba(0, 0, 0, 0.4);
                box-shadow: 3px 3px 4px 0 rgba(0, 0, 0, 0.4);
            }

            #current_visitors p, #total_visitors p {
                font-size: .8rem;
                color: rgb(255, 255, 255);
                font-weight: 300;
                white-space: nowrap;
                margin: 0;
                padding: 0;
            }

            hr {
                border: 1px solid rgb(180, 180, 180);
                border-radius: 2px;
            }

            #_check_in .basic-form-line {
                text-align: center;
            }

            #_check_in input[type=text] {
                background-color: rgb(255, 255, 255);
                border-radius: 0;
                border: none;
                width: 80%;
                color: rgb(80, 80, 80);
            }

            .dialog-box .contact-visit-type, .visit-type {
                font-size: .6rem;
                font-weight: normal;
                padding: 4px 3px;
                border-radius: 0;
                display: inline-block;
                background-color: rgb(200, 200, 200);
                color: rgb(90, 90, 90);
                width: 100px;
                margin: 4px;
            }

            .visit-type:hover {
                color: rgb(220, 220, 220);
                background-color: rgb(100, 100, 100);
            }

            .visit-type.selected {
                color: rgb(220, 220, 220);
                background-color: rgb(100, 100, 100);
            }

            #_search_results {
                width: 100%;
                margin: 10px auto;
                background-color: rgba(255, 255, 255, .7);
                padding: 0;
                display: none;
            }

            #_search_results p {
                font-size: .9rem;
                font-weight: normal;
                padding: 5px 10px;
                margin: 0;
            }

            #_visitor_sidebar p.select-contact {
                font-size: .7rem;
                cursor: pointer;
            }

            #_visitor_sidebar p.select-contact:hover {
                background-color: rgb(240, 240, 160);
            }

            #visitor_table {
                width: 100%;
                border-top: 2px solid rgb(180, 180, 180);
                border-radius: 2px;
            }

            #visitor_table .event-color-id {
                width: 40px;
                border-top: 10px solid rgb(220, 220, 220);
                border-bottom: 10px solid rgb(220, 220, 220);
            }

            #visitor_table h2 {
                margin: 0;
                padding: 0;
            }

            #visitor_table th {
                font-size: .7rem;
                text-align: center;
                background-color: transparent;
            }

            #visitor_table th#visitor_header {
                font-size: 1.8rem;
                text-align: center;
                margin: 0 0 10px;
                font-weight: 500;
                color: rgb(100, 100, 100);
                padding: 10px 0;
            }

            #visitor_table td {
                font-size: .8rem;
                text-align: center;
                color: rgb(100, 100, 100);
                padding: 4px 0;
                height: 30px;
                white-space: nowrap;
            }

            #visitor_table p.visitor-name {
                white-space: normal;
                cursor: pointer;
                margin: 0;
                padding: 0;
                font-size: .8rem;
                font-weight: normal;
            }

            p.visitor-name.ui-draggable-dragging {
                z-index: 9000;
                padding: 10px 40px;
                background-color: rgb(220, 220, 220);
                border: 1px solid rgb(150, 150, 150);
                border-radius: 5px;
                cursor: pointer;
            }

            button.left-now {
                font-size: .6rem;
                padding: 4px 6px;
                font-weight: normal;
                white-space: nowrap;
            }

            #calendar_date_wrapper {
                visibility: hidden;
            }

            #calendar_date_wrapper .ui-datepicker-trigger {
                border: none;
                padding: 0;
                background-color: transparent;
            }

            #calendar_date {
                font-size: 1.6rem;
                border: none;
                background-color: transparent;
                margin-right: 10px;
                color: rgb(100, 100, 100);
                font-weight: 600;
                text-align: left;
            }

            #calendar_date_sizer {
                position: absolute;
                top: -500px;
                left: -500px;
                font-size: 1.6rem;
                color: rgb(100, 100, 100);
                font-weight: 600;
                text-align: left;
            }

            #set_day_buttons button {
                margin-right: 8px;
            }

            .fa-calendar-alt {
                font-size: 1.4rem;
            }

            #_calendar_tabs {
                margin-top: 40px;
            }

            .calendar-tab {
                margin-right: 10px;
                font-size: .9rem;
                padding: 5px 20px;
                border-radius: 0;
                border: none;
                background-color: rgb(200, 200, 200);
                font-weight: 400;
                color: rgb(90, 90, 90);
            }

            .calendar-tab.selected {
                color: rgb(220, 220, 220);
                background-color: rgb(100, 100, 100);
            }

            button.calendar-tab:hover {
                color: rgb(220, 220, 220);
                background-color: rgb(100, 100, 100);
            }

            #_calendar_wrapper {
                padding: 20px;
                background-color: rgb(255, 255, 255);
                min-height: 400px;
            }

            #happening_now_calendar {
                background-color: rgb(230, 230, 230);
                padding: 20px;
                border-radius: 5px;
            }

            #happening_now_table {
                width: 100%;
                table-layout: fixed;
            }

            #happening_now_table td {
                height: <?= $this->iHappeningNowRowHeight ?>px;
                padding: 0 0 0 5px;
                font-size: .7rem;
                border-left: 1px solid rgb(230, 230, 230);
                white-space: nowrap;
                overflow: hidden;
                border-bottom: 3px solid rgb(230, 230, 230);
                vertical-align: middle;
            }

            #happening_now_table td.facility-name {
                width: auto;
            }

            #happening_now_table tr.data-only td {
                height: 20px;
                padding-top: 5px;
                font-weight: 300;
                font-size: .6rem;
            }

            #happening_now_table tr:first-child td {
                vertical-align: middle;
                border-bottom: 2px solid rgb(230, 230, 230);
            }

            #happening_now_table td:first-child {
                text-align: right;
                padding-right: 10px;
                width: 200px;
            }

            #happening_now_table td.available {
                background-color: rgb(108, 178, 95);
                cursor: pointer;
            }

            #happening_now_table td.current-event {
                background-color: rgb(204, 58, 69);
                cursor: pointer;
                color: white;
            }

            #happening_now_table td.upcoming-event {
                background-color: rgb(215, 179, 73);
                cursor: pointer;
                color: white;
            }

            #happening_now_table td.current-event.continuing-event {
                border-left: 1px solid rgb(204, 58, 69);
            }

            #happening_now_table td.upcoming-event.continuing-event {
                border-left: 1px solid rgb(215, 179, 73);
            }

            .narrow-wrapper {
                float: left;
                padding: 0 12px 10px 13px;
            }

            .narrow-calendar {
                width: 250px;
            }

            .narrow-calendar.full-calendar {
                width: 100%;
            }

            .calendar-title-small {
                margin: 0;
                font-size: 1rem;
                font-weight: 400;
                text-align: center;
                color: rgb(80, 80, 80);
                padding: 10px 0 12px;
            }

            #_select_facilities {
                padding: 20px;
                border: 1px solid rgb(200, 200, 200);
                overflow: auto;
            }

            .facility-list {
                float: left;
                padding-right: 60px;
                padding-bottom: 40px;
            }

            .facility-list p {
                padding: 0;
            }

            .facility-list-header {
                cursor: pointer;
                font-size: .9rem;
                color: rgb(15, 100, 50);
            }

            .facility-list-header:hover {
                color: rgb(190, 70, 20);
            }

            .delete-button {
                position: absolute;
                width: 20px;
                height: 20px;
                top: -1px;
                right: 0;
                z-index: 1000;
            }

            #_event_details textarea {
                width: 800px;
                height: 100px;
                border: 1px solid rgb(190, 190, 190);
                border-radius: 0;
                background: none;
            }

            #_event_details h2 {
                text-align: left;
            }

            .delete-event {
                padding: 0;
                color: rgb(180, 180, 180);
                font-size: 14px;
                display: block;
                height: 12px;
                width: 12px;
                position: absolute;
                top: 2px;
                right: 2px;
                cursor: pointer;
                text-align: right;
            }

            .delete-preset {
                margin-right: 10px;
                cursor: pointer;
                color: rgb(180, 180, 180);
            }

            .facility-group-choice {
                font-size: .9rem;
            }

            p#_error_message {
                font-size: 1rem;
                text-align: center;
                color: rgb(192, 0, 0);
                font-weight: bold;
                margin: 0;
                padding: 0 0 5px;
            }

            #_event_details select {
                min-width: 200px;
            }

            #recurring_schedule {
                display: none;
            }

            #recurring_schedule h3 {
                text-align: left;
            }

            #bymonth_table td {
                padding-right: 20px;
            }

            #bymonth_row {
                display: none;
            }

            #byday_row {
                display: none;
            }

            #byday_weekly_table {
                display: none;
            }

            #byday_weekly_table td {
                padding-right: 20px;
            }

            #byday_monthly_table {
                display: none;
            }

            #_event_link {
                font-size: .7rem;
            }

            .fc-unthemed td.fc-today {
                background-color: rgb(230, 230, 230);
            }

            .fc-unthemed td.fc-day {
                background-color: rgb(230, 230, 230);
            }

            .fc-unthemed td.fc-axis {
                background-color: rgb(230, 230, 230);
            }

            .fc-unthemed td.fc-axis span {
                color: rgb(150, 150, 150);
                font-size: 12px;
            }

            .fc-head {
                display: none;
            }

            .narrow-calendar.full-calendar .fc-head {
                display: table-header-group;
            }

            .fc-event {
                border: none;
                padding: 1px;
                border-radius: 0;
                width: 100%;
            }

            #just_checked_in {
                padding: 10px;
                text-align: center;
                background-color: rgb(108, 178, 95);
                display: none;
                margin: 40px 0;
                -webkit-box-shadow: 3px 3px 4px 0 rgba(0, 0, 0, 0.4);
                -moz-box-shadow: 3px 3px 4px 0 rgba(0, 0, 0, 0.4);
                box-shadow: 3px 3px 4px 0 rgba(0, 0, 0, 0.4);
                font-size: 1rem;
                color: rgb(255, 255, 255);
                font-weight: 300;
            }

            ::-webkit-scrollbar {
                width: 0;
                background: transparent;
            }

            .unseen {
                visibility: hidden;
            }

            .event-colors-legend-row {
                display: flex;
            }

            .event-colors-legend-row > div {
                margin: 10px;
                padding: 20px;
            }

            .event-colors-legend-color {
                max-width: 250px;
                width: 250px;
            }

            .set-waiting-time {
                cursor: pointer;
            }

            tr.not-waiting .set-waiting-time {
                visibility: hidden;
            }

            <?php
                        $resultSet = executeQuery("select * from event_colors where client_id = ?",$GLOBALS['gClientId']);
                        while ($row = getNextRow($resultSet)) {
                        ?>
            #happening_now_table td.event-color-id-<?= $row['event_color_id'] ?> {
                background-color: <?= $row['display_color'] ?>;
            }
            #visitor_table td.event-color-id-<?= $row['event_color_id'] ?> {
                background-color: <?= $row['display_color'] ?>;
            }

            <?php
                    }
             ?>
        </style>
		<?php
	}

	function hiddenElements() {
		?>
		<?php include "contactpicker.inc" ?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>
        <div id="_set_estimated_minutes_dialog" class="dialog-box">
            <form id="_set_estimated_minutes_form">
                <div class='basic-form-line' id="_estimated_minutes_row">
                    <label>Estimated Wait Time in Minutes</label>
                    <input type='text' size='10' class='align-right validate[custom[integer],min[1]]' id='estimated_minutes' name='estimated_minutes' value=''>
                </div>
            </form>
        </div>
        <div id="_preferences" class="dialog-box">
            <form id="_preferences_form">
                <h2>Happening Now Preferences</h2>
                <div class="basic-form-line" id="_group_by_facility_type_row">
                    <input type="checkbox" id="group_by_facility_type" name="group_by_facility_type" value="1"><label class='checkbox-label' for='group_by_facility_type'>Group by Facility Type</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_hide_visitor_sidebar_row">
                    <input type="checkbox" id="hide_visitor_sidebar" name="hide_visitor_sidebar" value="1"><label class='checkbox-label' for='hide_visitor_sidebar'>Hide Visitor Sidebar</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_ignore_usage_sort_row">
                    <input type="checkbox" id="ignore_usage_sort" name="ignore_usage_sort" value="1"><label class='checkbox-label' for='ignore_usage_sort'>Sort by facility (default is to sort by usage)</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_row_height_row">
                    <label for="row_height">Row Height (Default: 50)</label>
                    <input type="text" id="row_height" name="row_height" class="validate[custom[integer],min[10]] align-right" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_segment_count_row">
                    <label for="segment_count">Segment Count (4 per hour, default is 6)</label>
                    <input type="text" id="segment_count" name="segment_count" class="validate[custom[integer],min[8]] align-right" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_overview_start_hour_row">
                    <label for="overview_start_hour">Overview tab start military hour (default is 8)</label>
                    <input type="text" id="overview_start_hour" name="overview_start_hour" class="validate[custom[integer],min[1],max[12]] align-right" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_overview_segments_row">
                    <label for="overview_segments">Overview tab segments (4 per hour, default is 40)</label>
                    <input type="text" id="overview_segments" name="overview_segments" class="validate[custom[integer],min[8],max[80]] align-right" value="">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

            </form>
        </div>

		<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
            <div id="_confirm_delete_event_dialog" class="dialog-box">
                Do you want to delete just this occurrence or this and all future occurrences?
            </div>

            <div id="_event_details" class="dialog-box">
                <form id="_event_form" name="_event_form">
                    <input type="hidden" id="event_id" name="event_id"/>
                    <input type="hidden" id="instance_date" name="instance_date"/>
                    <input type="hidden" id="country_id" name="country_id" value="1000"/>
                    <h2>Event Information<?php if (canAccessPageCode("EVENTMAINT")) { ?> <a href="#" tabindex="-1" id="_event_link">(Open Event Page)</a><?php } ?></h2>

                    <div class="basic-form-line" id="_facility_id_row">
                        <label for="facility_id">Facility</label>
                        <select id="facility_id" name="facility_id" class="validate[required]">
                            <option value="">[Select]</option>
							<?php
							$resultSet = executeQuery("select * from facilities where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								$eventTypeId = getFieldFromId("event_type_id", "facility_types", "facility_type_id", $row['facility_type_id']);
								?>
                                <option data-reservation_start="<?= $row['reservation_start'] ?>" data-event_type_id="<?= $eventTypeId ?>" value="<?= htmlText($row['facility_id']) ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line inline-block" id="_date_needed_row">
                        <label for="date_needed">Date Needed</label>
                        <input type="text" size="12" id="date_needed" name="date_needed" class="event-time-field validate[required,custom[date]] datepicker">
                    </div>

                    <div class="basic-form-line inline-block" id="_start_time_row">
                        <label for="start_time">Start Time</label>
                        <select id="start_time" name="start_time" class="event-time-field validate[required]">
                            <option value="">[Select]</option>
							<?php
							for ($x = 0; $x < 24; $x += .25) {
								$workingHour = floor($x);
								$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
								$displayMinutes = ($x - $workingHour) * 60;
								$displayAmpm = ($x == 0 ? "midnight" : ($x == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
								?>
                                <option value="<?= $x ?>" data-hour="<?= $workingHour ?>" data-minute="<?= $displayMinutes ?>"><?= $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . $displayAmpm ?></option>
							<?php } ?>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line inline-block" id="_end_time_row">
                        <label for="end_time">End Time</label>
                        <select id="end_time" name="end_time" class="event-time-field validate[required]">
                            <option value="">[Select]</option>
							<?php
							for ($x = 0; $x < 24; $x += .25) {
								$workingHour = floor($x + .25);
								$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
								$displayMinutes = ($x + .25 - $workingHour) * 60;
								$displayAmpm = (($x + .25) == 24 ? "midnight" : (($x + .24) == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
								?>
                                <option value="<?= $x ?>" data-hour="<?= $workingHour ?>" data-minute="<?= $displayMinutes ?>"><?= $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . $displayAmpm ?></option>
							<?php } ?>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <p id="start_time_warning"></p>

                    <div class='clear-div'></div>

                    <div class="basic-form-line" id="_event_type_id_row">
                        <label for="event_type_id">Event Type</label>
                        <select id="event_type_id" name="event_type_id" class="validate[required]">
                            <option value="">[Select]</option>
							<?php
							$resultSet = executeQuery("select * from event_types where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								?>
                                <option value="<?= htmlText($row['event_type_id']) ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line" id="_description_row">
                        <label for="description">Event Title</label>
                        <input type="text" class="validate[required]" data-custom_value="" size="30" maxlength="255" id="description" name="description" value=""/>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

					<?php echo createFormControl("users", "contact_id", array("not_null" => false, "data_type" => "contact_picker")) ?>

                    <div id="check_in_contact_wrapper">
                        <p>
                            <button id="_check_in_contact">Check In</button>
                        </p>

						<?php
						$resultSet = executeQuery("select * from visit_types where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
						?>
                        <div class="basic-form-line<?= ($resultSet['row_count'] > 3 ? " hidden" : "") ?>" id="_contact_visit_type_id_row">
                            <input type="hidden" id="contact_visit_type_id" name="contact_visit_type_id">
							<?php
							while ($row = getNextRow($resultSet)) {
								echo '<button class="contact-visit-type" data-visit_type_id="' . $row['visit_type_id'] . '">' . htmlText($row['description']) . '</button>';
							}
							?>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>
                    </div>

                    <div id="custom_contact_data"></div>

                    <div class="basic-form-line inline-block contact-field" id="_first_name_row">
                        <label for="first_name">First Name</label>
                        <input type="text" class="validate[required]" size="25" maxlength="25" id="first_name" name="first_name" value=""/>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line inline-block contact-field" id="_last_name_row">
                        <label for="last_name">Last Name</label>
                        <input type="text" size="35" maxlength="35" id="last_name" name="last_name" value=""/>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line contact-field" id="_organization_row">
                        <label for="organization">Organization</label>
                        <input type="text" size="60" maxlength="60" id="organization" name="organization" value=""/>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line contact-field" id="_email_address_row">
                        <label for="email_address">Email</label>
                        <input type="text" size="60" maxlength="60" id="email_address" name="email_address" value=""/>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line contact-field" id="_phone_numbers_row">
						<?php
						$phoneNumberControl = new DataColumn("phone_numbers");
						$phoneNumberControl->setControlValue("data_type", "custom_control");
						$phoneNumberControl->setControlValue("control_class", "EditableList");
						$phoneNumberControl->setControlValue("primary_table", "contacts");
						$phoneNumberControl->setControlValue("list_table", "phone_numbers");
						echo $phoneNumberControl->getControl($this);
						?>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line" id="_detailed_description_row">
                        <label for="detailed_description">Details</label>
                        <textarea id="detailed_description" name="detailed_description"></textarea>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line inline-block" id="_attendees_row">
                        <label for="attendees">Attendees</label>
                        <input type="text" size="6" maxlength="6" class="align-right validate[required,custom[integer]]" id="attendees" name="attendees" value=""/>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div class="basic-form-line inline-block" id="_no_statistics_row">
                        <input type="checkbox" id="no_statistics" name="no_statistics" value="1"/><label class="checkbox-label" for="no_statistics">Don't count in Statistics</label>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div id="requirements_wrapper">
                        <h3 id="requirements_label">Requirements<span id="requirements_opener" class="fas fa-chevron-double-left"></span></h3>
                        <div id="requirements_list" class="folded-up">
							<?php
							$resultSet = executeQuery("select * from event_requirements where inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								?>
                                <div class="basic-form-line inline-block">
                                    <input type="checkbox" id="event_requirement_id_<?= $row['event_requirement_id'] ?>" name="event_requirement_id_<?= $row['event_requirement_id'] ?>">
                                    <label for="event_requirement_id_<?= $row['event_requirement_id'] ?>" class="checkbox-label"><?= htmlText($row['description']) ?></label>
                                </div>

                                <div class="basic-form-line inline-block">
                                    <label>Notes</label>
                                    <textarea id="event_requirement_notes_<?= $row['event_requirement_id'] ?>" name="event_requirement_notes_<?= $row['event_requirement_id'] ?>"></textarea>
                                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                                </div>
								<?php
							}
							?>
                        </div>
                    </div>


                    <div class="basic-form-line" id="_recurring_row">
                        <input class="recurring-event-field" type="checkbox" id="recurring" name="recurring" value="1"/><label class="checkbox-label" for="recurring">Recurring Event</label>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                    <div id="recurring_schedule">
                        <h3>Recurring Schedule</h3>

                        <div class="basic-form-line" id="_recurring_start_date_row">
                            <label for="recurring_start_date" class="required-label">Starting on or after</label>
                            <input class='recurring-event-field validate[custom[date],required] datepicker' type='text' value='' size='12' maxlength='12' name='recurring_start_date' id='recurring_start_date'/>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>

                        <div class="basic-form-line" id="_frequency_row">
                            <label for="frequency">Frequency</label>
                            <select class="recurring-event-field" name='frequency' id='frequency'>
                                <option value="DAILY">Daily</option>
                                <option value="WEEKLY">Weekly</option>
                                <option value="MONTHLY">Monthly</option>
                                <option value="YEARLY">Yearly</option>
                            </select>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>

                        <div class="basic-form-line" id="_interval_row">
                            <label for="interval">Interval</label>
                            <input class='recurring-event-field validate[custom[integer],min[1]] align-right' type='text' value='' size='4' maxlength='4' name='interval' id='interval'/>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>

                        <div class="basic-form-line" id="_bymonth_row">
                            <label>Months</label>
                            <table id='bymonth_table'>
                                <tr>
                                    <td><input class='recurring-event-field bymonth-month' type='checkbox' value='1' name='bymonth_1' id='bymonth_1'/><label for="bymonth_1" class="checkbox-label">January</label></td>
                                    <td><input class='recurring-event-field bymonth-month' type='checkbox' value='4' name='bymonth_4' id='bymonth_4'/><label for="bymonth_4" class="checkbox-label">April</label></td>
                                    <td><input class='recurring-event-field bymonth-month' type='checkbox' value='7' name='bymonth_7' id='bymonth_7'/><label for="bymonth_7" class="checkbox-label">July</label></td>
                                    <td><input class='recurring-event-field bymonth-month' type='checkbox' value='10' name='bymonth_10' id='bymonth_10'/><label for="bymonth_10" class="checkbox-label">October</label></td>
                                </tr>
                                <tr>
                                    <td><input class='recurring-event-field bymonth-month' type='checkbox' value='2' name='bymonth_2' id='bymonth_2'/><label for="bymonth_2" class="checkbox-label">February</label></td>
                                    <td><input class='recurring-event-field bymonth-month' type='checkbox' value='5' name='bymonth_5' id='bymonth_5'/><label for="bymonth_5" class="checkbox-label">May</label></td>
                                    <td><input class='recurring-event-field bymonth-month' type='checkbox' value='8' name='bymonth_8' id='bymonth_8'/><label for="bymonth_8" class="checkbox-label">August</label></td>
                                    <td><input class='recurring-event-field bymonth-month' type='checkbox' value='11' name='bymonth_11' id='bymonth_11'/><label for="bymonth_11" class="checkbox-label">November</label></td>
                                </tr>
                                <tr>
                                    <td><input class='recurring-event-field bymonth-month' type='checkbox' value='3' name='bymonth_3' id='bymonth_3'/><label for="bymonth_3" class="checkbox-label">March</label></td>
                                    <td><input class='recurring-event-field bymonth-month' type='checkbox' value='6' name='bymonth_6' id='bymonth_6'/><label for="bymonth_6" class="checkbox-label">June</label></td>
                                    <td><input class='recurring-event-field bymonth-month' type='checkbox' value='9' name='bymonth_9' id='bymonth_9'/><label for="bymonth_9" class="checkbox-label">September</label></td>
                                    <td><input class='recurring-event-field bymonth-month' type='checkbox' value='12' name='bymonth_12' id='bymonth_12'/><label for="bymonth_12" class="checkbox-label">December</label></td>
                                </tr>
                            </table>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>

                        <div class="basic-form-line" id="_byday_row">
                            <label>Days</label>
                            <table id="byday_weekly_table">
                                <tr>
                                    <td><input class='recurring-event-field byday-weekday' type='checkbox' value='SUN' name='byday_sun' id='byday_sun'/><label for="byday_sun" class="checkbox-label">Sunday</label></td>
                                    <td><input class='recurring-event-field byday-weekday' type='checkbox' value='MON' name='byday_mon' id='byday_mon'/><label for="byday_mon" class="checkbox-label">Monday</label></td>
                                    <td><input class='recurring-event-field byday-weekday' type='checkbox' value='TUE' name='byday_tue' id='byday_tue'/><label for="byday_tue" class="checkbox-label">Tuesday</label></td>
                                    <td><input class='recurring-event-field byday-weekday' type='checkbox' value='WED' name='byday_wed' id='byday_wed'/><label for="byday_wed" class="checkbox-label">Wednesday</label></td>
                                    <td><input class='recurring-event-field byday-weekday' type='checkbox' value='THU' name='byday_thu' id='byday_thu'/><label for="byday_thu" class="checkbox-label">Thursday</label></td>
                                    <td><input class='recurring-event-field byday-weekday' type='checkbox' value='FRI' name='byday_fri' id='byday_fri'/><label for="byday_fri" class="checkbox-label">Friday</label></td>
                                    <td><input class='recurring-event-field byday-weekday' type='checkbox' value='SAT' name='byday_sat' id='byday_sat'/><label for="byday_sat" class="checkbox-label">Saturday</label></td>
                                </tr>
                            </table>
                            <table id="byday_monthly_table">
                                <tr class="byday-monthly-row">
                                    <td><select class='recurring-event-field ordinal-day' id='ordinal_day_1' name='ordinal_day_1'>
                                            <option value="">[Select]</option>
                                            <option value="1">1st</option>
                                            <option value="2">2nd</option>
                                            <option value="3">3rd</option>
                                            <option value="4">4th</option>
                                            <option value="5">5th</option>
                                            <option value="6">6th</option>
                                            <option value="7">7th</option>
                                            <option value="8">8th</option>
                                            <option value="9">9th</option>
                                            <option value="10">10th</option>
                                            <option value="11">11th</option>
                                            <option value="12">12th</option>
                                            <option value="13">13th</option>
                                            <option value="14">14th</option>
                                            <option value="15">15th</option>
                                            <option value="16">16th</option>
                                            <option value="17">17th</option>
                                            <option value="18">18th</option>
                                            <option value="19">19th</option>
                                            <option value="20">20th</option>
                                            <option value="21">21st</option>
                                            <option value="22">22nd</option>
                                            <option value="23">23rd</option>
                                            <option value="24">24th</option>
                                            <option value="25">25th</option>
                                            <option value="26">26th</option>
                                            <option value="27">27th</option>
                                            <option value="28">28th</option>
                                            <option value="29">29th</option>
                                            <option value="30">30th</option>
                                            <option value="31">31st</option>
                                            <option value="-">Last</option>
                                        </select></td>
                                    <td><select class='recurring-event-field weekday-select' id='weekday_1' name='weekday_1'>
                                            <option value="">Day of the Month</option>
                                            <option value="SUN">Sunday</option>
                                            <option value="MON">Monday</option>
                                            <option value="TUE">Tuesday</option>
                                            <option value="WED">Wednesday</option>
                                            <option value="THU">Thursday</option>
                                            <option value="FRI">Friday</option>
                                            <option value="SAT">Saturday</option>
                                        </select></td>
                                </tr>
                                <tr class="byday-monthly-row">
                                    <td><select class='recurring-event-field ordinal-day' id='ordinal_day_2' name='ordinal_day_2'>
                                            <option value="">[Select]</option>
                                            <option value="1">1st</option>
                                            <option value="2">2nd</option>
                                            <option value="3">3rd</option>
                                            <option value="4">4th</option>
                                            <option value="5">5th</option>
                                            <option value="6">6th</option>
                                            <option value="7">7th</option>
                                            <option value="8">8th</option>
                                            <option value="9">9th</option>
                                            <option value="10">10th</option>
                                            <option value="11">11th</option>
                                            <option value="12">12th</option>
                                            <option value="13">13th</option>
                                            <option value="14">14th</option>
                                            <option value="15">15th</option>
                                            <option value="16">16th</option>
                                            <option value="17">17th</option>
                                            <option value="18">18th</option>
                                            <option value="19">19th</option>
                                            <option value="20">20th</option>
                                            <option value="21">21st</option>
                                            <option value="22">22nd</option>
                                            <option value="23">23rd</option>
                                            <option value="24">24th</option>
                                            <option value="25">25th</option>
                                            <option value="26">26th</option>
                                            <option value="27">27th</option>
                                            <option value="28">28th</option>
                                            <option value="29">29th</option>
                                            <option value="30">30th</option>
                                            <option value="31">31st</option>
                                            <option value="-">Last</option>
                                        </select></td>
                                    <td><select class='recurring-event-field weekday-select' id='weekday_2' name='weekday_2'>
                                            <option value="">Day of the Month</option>
                                            <option value="SUN">Sunday</option>
                                            <option value="MON">Monday</option>
                                            <option value="TUE">Tuesday</option>
                                            <option value="WED">Wednesday</option>
                                            <option value="THU">Thursday</option>
                                            <option value="FRI">Friday</option>
                                            <option value="SAT">Saturday</option>
                                        </select></td>
                                </tr>
                                <tr class="byday-monthly-row">
                                    <td><select class='recurring-event-field ordinal-day' id='ordinal_day_3' name='ordinal_day_3'>
                                            <option value="">[Select]</option>
                                            <option value="1">1st</option>
                                            <option value="2">2nd</option>
                                            <option value="3">3rd</option>
                                            <option value="4">4th</option>
                                            <option value="5">5th</option>
                                            <option value="6">6th</option>
                                            <option value="7">7th</option>
                                            <option value="8">8th</option>
                                            <option value="9">9th</option>
                                            <option value="10">10th</option>
                                            <option value="11">11th</option>
                                            <option value="12">12th</option>
                                            <option value="13">13th</option>
                                            <option value="14">14th</option>
                                            <option value="15">15th</option>
                                            <option value="16">16th</option>
                                            <option value="17">17th</option>
                                            <option value="18">18th</option>
                                            <option value="19">19th</option>
                                            <option value="20">20th</option>
                                            <option value="21">21st</option>
                                            <option value="22">22nd</option>
                                            <option value="23">23rd</option>
                                            <option value="24">24th</option>
                                            <option value="25">25th</option>
                                            <option value="26">26th</option>
                                            <option value="27">27th</option>
                                            <option value="28">28th</option>
                                            <option value="29">29th</option>
                                            <option value="30">30th</option>
                                            <option value="31">31st</option>
                                            <option value="-">Last</option>
                                        </select></td>
                                    <td><select class='recurring-event-field weekday-select' id='weekday_3' name='weekday_3'>
                                            <option value="">Day of the Month</option>
                                            <option value="SUN">Sunday</option>
                                            <option value="MON">Monday</option>
                                            <option value="TUE">Tuesday</option>
                                            <option value="WED">Wednesday</option>
                                            <option value="THU">Thursday</option>
                                            <option value="FRI">Friday</option>
                                            <option value="SAT">Saturday</option>
                                        </select></td>
                                </tr>
                                <tr class="byday-monthly-row">
                                    <td><select class='recurring-event-field ordinal-day' id='ordinal_day_4' name='ordinal_day_4'>
                                            <option value="">[Select]</option>
                                            <option value="1">1st</option>
                                            <option value="2">2nd</option>
                                            <option value="3">3rd</option>
                                            <option value="4">4th</option>
                                            <option value="5">5th</option>
                                            <option value="6">6th</option>
                                            <option value="7">7th</option>
                                            <option value="8">8th</option>
                                            <option value="9">9th</option>
                                            <option value="10">10th</option>
                                            <option value="11">11th</option>
                                            <option value="12">12th</option>
                                            <option value="13">13th</option>
                                            <option value="14">14th</option>
                                            <option value="15">15th</option>
                                            <option value="16">16th</option>
                                            <option value="17">17th</option>
                                            <option value="18">18th</option>
                                            <option value="19">19th</option>
                                            <option value="20">20th</option>
                                            <option value="21">21st</option>
                                            <option value="22">22nd</option>
                                            <option value="23">23rd</option>
                                            <option value="24">24th</option>
                                            <option value="25">25th</option>
                                            <option value="26">26th</option>
                                            <option value="27">27th</option>
                                            <option value="28">28th</option>
                                            <option value="29">29th</option>
                                            <option value="30">30th</option>
                                            <option value="31">31st</option>
                                            <option value="-">Last</option>
                                        </select></td>
                                    <td><select class='recurring-event-field weekday-select' id='weekday_4' name='weekday_4'>
                                            <option value="">Day of the Month</option>
                                            <option value="SUN">Sunday</option>
                                            <option value="MON">Monday</option>
                                            <option value="TUE">Tuesday</option>
                                            <option value="WED">Wednesday</option>
                                            <option value="THU">Thursday</option>
                                            <option value="FRI">Friday</option>
                                            <option value="SAT">Saturday</option>
                                        </select></td>
                                </tr>
                                <tr class="byday-monthly-row">
                                    <td><select class='recurring-event-field ordinal-day' id='ordinal_day_5' name='ordinal_day_5'>
                                            <option value="">[Select]</option>
                                            <option value="1">1st</option>
                                            <option value="2">2nd</option>
                                            <option value="3">3rd</option>
                                            <option value="4">4th</option>
                                            <option value="5">5th</option>
                                            <option value="6">6th</option>
                                            <option value="7">7th</option>
                                            <option value="8">8th</option>
                                            <option value="9">9th</option>
                                            <option value="10">10th</option>
                                            <option value="11">11th</option>
                                            <option value="12">12th</option>
                                            <option value="13">13th</option>
                                            <option value="14">14th</option>
                                            <option value="15">15th</option>
                                            <option value="16">16th</option>
                                            <option value="17">17th</option>
                                            <option value="18">18th</option>
                                            <option value="19">19th</option>
                                            <option value="20">20th</option>
                                            <option value="21">21st</option>
                                            <option value="22">22nd</option>
                                            <option value="23">23rd</option>
                                            <option value="24">24th</option>
                                            <option value="25">25th</option>
                                            <option value="26">26th</option>
                                            <option value="27">27th</option>
                                            <option value="28">28th</option>
                                            <option value="29">29th</option>
                                            <option value="30">30th</option>
                                            <option value="31">31st</option>
                                            <option value="-">Last</option>
                                        </select></td>
                                    <td><select class='recurring-event-field weekday-select' id='weekday_5' name='weekday_5'>
                                            <option value="">Day of the Month</option>
                                            <option value="SUN">Sunday</option>
                                            <option value="MON">Monday</option>
                                            <option value="TUE">Tuesday</option>
                                            <option value="WED">Wednesday</option>
                                            <option value="THU">Thursday</option>
                                            <option value="FRI">Friday</option>
                                            <option value="SAT">Saturday</option>
                                        </select></td>
                                </tr>
                            </table>
                        </div>

                        <div class="basic-form-line" id="_count_row">
                            <label for="count">Number of occurrences</label>
                            <input type="text" size="6" class="recurring-event-field validate[custom[integer],min[1]]" id="count" name="count"/>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>

                        <div class="basic-form-line" id="_until_row">
                            <label for="until">End Date</label>
                            <input type="text" size="12" maxlength="12" class="recurring-event-field validate[custom[date]] datepicker" id="until" name="until"/>
                            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                        </div>

                    </div>

                    <p id="conflict" class="error-message"></p>

                    <div class="basic-form-line" id="_ignore_conflicts_row">
                        <input type="checkbox" id="ignore_conflicts" name="ignore_conflicts" value="1"/><label class="checkbox-label" for="ignore_conflicts">Ignore conflicts and create anyway</label>
                        <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                    </div>

                </form>
            </div>
            <div id="_event_colors_legend" class="dialog-box">
				<?php
				$eventColorsResult = executeQuery("select * from event_colors where client_id = ? order by sort_order", $GLOBALS['gClientId']);
				while ($row = getNextRow($eventColorsResult)) {
					?>
                    <div class="event-colors-legend-row">
						<?php
						$comparators = array();
						$comparators['order']['include'] = "Reservation is paid";
						$comparators['order']['exclude'] = "Reservation is not paid";
						$comparators['event_type']['include'] = "Event Type is";
						$comparators['event_type']['exclude'] = "Event Type is not";
						$comparators['facility_type']['include'] = "Facility Type is";
						$comparators['facility_type']['exclude'] = "Facility Type is not";
						$comparators['contact_category']['include'] = "Reserved by Contact with Category";
						$comparators['contact_category']['exclude'] = "Reserved by Contact without Category";
						$comparators['contact_type']['include'] = "Reserved by Contact Type";
						$comparators['contact_type']['exclude'] = "Not reserved by Contact Type";
						$comparators['user_type']['include'] = "Reserved by User Type";
						$comparators['user_type']['exclude'] = "Not reserved by User Type";
						$comparators['user_group']['include'] = "Reserved by User in Group";
						$comparators['user_group']['exclude'] = "Not reserved by User in Group";
						$comparators['reserved']['include'] = "Reserved";
						$comparators['reserved']['exclude'] = "Not reserved";
						$fieldValue = "";
						switch ($row['comparator']) {
							case "event_type":
								$fieldValue = getFieldFromId("description", "event_types", "event_type_id", $row['field_value']);
								break;
							case "facility_type":
								$fieldValue = getFieldFromId("description", "facility_types", "facility_type_id", $row['field_value']);
								break;
							case "contact_category":
								$fieldValue = getFieldFromId("description", "categories", "category_id", $row['field_value']);
								break;
							case "contact_type":
								$fieldValue = getFieldFromId("description", "contact_types", "contact_type_id", $row['field_value']);
								break;
							case "user_type":
								$fieldValue = getFieldFromId("description", "user_types", "user_type_id", $row['field_value']);
								break;
							case "user_group":
								$fieldValue = getFieldFromId("description", "user_groups", "user_group_id", $row['field_value']);
								break;
							case "order":
							case "reserved":
							default:
								$fieldValue = "";
								break;
						}
						$value = $comparators[$row['comparator']][(empty($row['exclude']) ? "include" : "exclude")] . " " . $fieldValue;
						?>
                        <div class="event-colors-legend-color" style="background-color:<?= $row['display_color'] ?>">&nbsp;</div>
                        <div class="event-colors-legend-value"><?= $value ?></div>
                    </div>
					<?php
				}
				?>
            </div>
		<?php } ?>
		<?php
	}

	function jqueryTemplates() {
		$phoneNumberControl = new DataColumn("phone_numbers");
		$phoneNumberControl->setControlValue("data_type", "custom_control");
		$phoneNumberControl->setControlValue("control_class", "EditableList");
		$phoneNumberControl->setControlValue("primary_table", "contacts");
		$phoneNumberControl->setControlValue("list_table", "phone_numbers");
		$customControl = new EditableList($phoneNumberControl, $this);
		echo $customControl->getTemplate();
	}
}

$pageObject = new EventSchedulerPage();
$pageObject->displayPage();
