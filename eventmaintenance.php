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

$GLOBALS['gPageCode'] = "EVENTMAINT";
require_once "shared/startup.inc";

class EventMaintenancePage extends Page {

	var $iSearchFields = array("description", "detailed_description", "email_address", "events.notes", "first_name", "last_name", "business_name");

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("description", "event_type_id", "location_id", "start_date", "end_date", "cost", "payment_date", "contacts.first_name", "contacts.last_name", "contacts.email_address", "inactive", "internal_use_only"));
			$this->iTemplateObject->getTableEditorObject()->addIncludeSearchColumn(array("first_name", "last_name", "email_address", "description"));
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);

			$filters = array();
			$filters['hide_past'] = array("form_label" => "Hide Past Events", "where" => "(end_date is null and start_date >= current_date) or end_date >= current_date", "data_type" => "tinyint", "set_default" => true);
			$filters['available_slots'] = array("form_label" => "Registration not full", "where" => "(select count(*) from event_registrants where event_id = events.event_id) < attendees", "data_type" => "tinyint");
            $filters['start_date_after'] = array("form_label" => "Start Date On or After", "where" => "start_date >= '%filter_value%'", "data_type" => "date", "conjunction" => "and");
            $filters['start_date_before'] = array("form_label" => "Start Date On or Before", "where" => "start_date <= '%filter_value%'", "data_type" => "date", "conjunction" => "and");

            $resultSet = executeQuery("select * from locations where client_id = ? and product_distributor_id is null and inactive = 0 order by description", $GLOBALS['gClientId']);
			if ($resultSet['row_count'] > 10) {
				$locations = array();
				while ($row = getNextRow($resultSet)) {
					$locations[$row['location_id']] = $row['description'];
				}
				$filters['location_id'] = array("form_label" => "Location", "where" => "location_id = %key_value%", "data_type" => "select", "choices" => $locations, "conjunction" => "and");
			} else {
				$filters['location_header'] = array("form_label" => "Locations", "data_type" => "header");
				while ($row = getNextRow($resultSet)) {
					$filters['location_id_' . $row['location_id']] = array("form_label" => $row['description'], "where" => "location_id = " . $row['location_id'] . ")", "data_type" => "tinyint");
				}
			}

			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function classInstructorChoices($showInactive = false) {
		$classInstructorChoices = array();
		$resultSet = executeQuery("select * from class_instructors join contacts using (contact_id) where class_instructors.client_id = ? order by last_name,first_name", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['inactive']) || $showInactive) {
				$classInstructorChoices[$row['class_instructor_id']] = array("key_value" => $row['class_instructor_id'], "description" => getDisplayName($row['contact_id']), "inactive" => $row['inactive'] == 1, "data-assigned_user_id" => $row['user_id']);
			}
		}
		return $classInstructorChoices;
	}

	function headerIncludes() {
		?>
        <link type="text/css" rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.2.0/fullcalendar.min.css"/>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.17.1/moment.min.js"></script>
        <script src="//cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.2.0/fullcalendar.min.js"></script>
        <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/fullcalendar.css') ?>"/>
		<?php
	}

	function checkContact($nameValues) {
		if (!empty($nameValues['primary_id']) && empty($nameValues['contact_id'])) {
			return false;
		}
		return true;
	}

	function massageDataSource() {

		$this->iDataSource->addColumnControl("event_id", "form_label", "Event ID");
		$this->iDataSource->addColumnControl("attendees", "help_label", "For events that require registration, this is the maximum that can register");

		$this->iDataSource->setConditionalJoinFunction(array("object" => $this, "method" => "checkContact"));
		$this->iDataSource->addColumnControl("event_registrant_count", "data_type", "int");
		$this->iDataSource->addColumnControl("event_registrant_count", "form_label", "Registrant Count");
		$this->iDataSource->addColumnControl("event_registrant_count", "readonly", true);

		$this->iDataSource->addColumnControl("event_group_links", "data_type", "custom");
		$this->iDataSource->addColumnControl("event_group_links", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("event_group_links", "form_label", "Groups");
		$this->iDataSource->addColumnControl("event_group_links", "links_table", "event_group_links");
		$this->iDataSource->addColumnControl("event_group_links", "control_table", "event_groups");

		$this->iDataSource->addColumnControl("class_instructor_id", "form_label", "Primary Instructor");
		$this->iDataSource->addColumnControl("class_instructor_id", "get_choices", "classInstructorChoices");
		$this->iDataSource->addColumnControl("event_class_instructors", "data_type", "custom");
		$this->iDataSource->addColumnControl("event_class_instructors", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("event_class_instructors", "form_label", "Additional Instructors");
		$this->iDataSource->addColumnControl("event_class_instructors", "links_table", "event_class_instructors");
		$this->iDataSource->addColumnControl("event_class_instructors", "control_table", "class_instructors");
		$this->iDataSource->addColumnControl("event_class_instructors", "get_choices", "classInstructorChoices");

		$this->iDataSource->addColumnControl("event_registrants", "data_type", "custom");
		$this->iDataSource->addColumnControl("event_registrants", "list_table", "event_registrants");
		$this->iDataSource->addColumnControl("event_registrants", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("event_registrants", "column_list", "contact_id,registration_time,check_in_time,order_id,notes");
		$this->iDataSource->addColumnControl("event_registrants", "list_table_controls", array("registration_time" => array("default_value" => date("m/d/Y g:i:s a")), "order_id" => array("form_label" => "Order ID", "data_type" => "int", "subtype" => "int", "classes" => "event-order-id", "readonly" => true)));
		$this->iDataSource->addColumnControl("event_registrants", "additional_column", array("form_label" => "Email", "content" => "<span class='fad fa-envelope email-registrant'></span>"));

		$this->iDataSource->addColumnControl("product_id", "form_label", "Registration Product");

		$this->iDataSource->addColumnControl("event_registration_products", "form_label", "Other Products");
		$this->iDataSource->addColumnControl("event_registration_products", "data_type", "custom");
		$this->iDataSource->addColumnControl("event_registration_products", "list_table", "event_registration_products");
		$this->iDataSource->addColumnControl("event_registration_products", "control_class", "EditableList");

		$this->iDataSource->addColumnControl("event_images", "form_label", "Images");
		$this->iDataSource->addColumnControl("event_images", "data_type", "custom");
		$this->iDataSource->addColumnControl("event_images", "list_table", "event_images");
		$this->iDataSource->addColumnControl("event_images", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("link_name", "help_label", "for registration");
		$this->iDataSource->addColumnControl("link_url", "data_type", "varchar");
		$this->iDataSource->addColumnControl("link_url", "css-width", "500px");
		$this->iDataSource->addColumnControl("date_created", "readonly", "true");
		$this->iDataSource->addColumnControl("date_created", "default_value", date("m/d/Y"));
		$this->iDataSource->setJoinTable("contacts", "contact_id", "contact_id", true);
		$this->iDataSource->getPrimaryTable()->setSubtables(array("event_facilities", "event_facility_recurrences", "event_requirement_data", "event_registration_products", "event_images", "event_registration_custom_fields"));
		$this->iDataSource->setConditionalJoinFunction("checkContactData");
		$this->iDataSource->addColumnControl("event_registration_custom_fields", "form_label", "Registration Custom Fields");
		$this->iDataSource->addColumnControl("event_registration_custom_fields", "data_type", "custom");
		$this->iDataSource->addColumnControl("event_registration_custom_fields", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("event_registration_custom_fields", "links_table", "event_registration_custom_fields");
		$this->iDataSource->addColumnControl("event_registration_custom_fields", "control_table", "custom_fields");
		$this->iDataSource->addColumnControl("event_registration_custom_fields", "choice_where", "custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'EVENT_REGISTRATIONS')");

		$this->iDataSource->addColumnControl("event_registration_notifications", "form_label", "Notifications");
		$this->iDataSource->addColumnControl("event_registration_notifications", "data_type", "custom");
		$this->iDataSource->addColumnControl("event_registration_notifications", "list_table", "event_registration_notifications");
		$this->iDataSource->addColumnControl("event_registration_notifications", "control_class", "EditableList");
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['product_link'] = array("data_value"=>(empty($returnArray['product_id']['data_value']) ? "" : "<a target='_blank' href='/productmaintenance.php?clear_filter=true&url_page=show&primary_id=" . $returnArray['product_id']['data_value'] . "'>Go To Registration Product</a>"));
		$returnArray['event_registrant_count'] = array("data_value" => "0");
		$resultSet = executeQuery("select count(*) from event_registrants where event_id = ?", $returnArray['event_id']['data_value']);
		if ($row = getNextRow($resultSet)) {
			$returnArray['event_registrant_count'] = array("data_value" => $row['count(*)']);
		}
		$customFieldInformation = $this->getCustomFieldInformation($returnArray['primary_id']['data_value'], $returnArray['event_type_id']['data_value']);
		if (array_key_exists("select_values", $returnArray) && array_key_exists("select_values", $customFieldInformation)) {
			$returnArray['select_values'] = $customFieldInformation['select_values'] = array_merge($returnArray['select_values'], $customFieldInformation['select_values']);
		}
		$returnArray = array_merge($returnArray, $customFieldInformation);
		$scheduleArray = array();
		$resultSet = executeQuery("select * from event_facilities where event_id = ? order by event_id,facility_id,date_needed,hour", $returnArray['primary_id']['data_value']);
		$currentDate = "";
		$currentFacilityId = "";
		$lastHour = "";
		$index = 0;
		while ($row = getNextRow($resultSet)) {
			if ($currentDate != $row['date_needed'] || $currentFacilityId != $row['facility_id']) {
				$index++;
				$date = strtotime($row['date_needed']);
				$scheduleArray[$index] = array("facility_id" => $row['facility_id'], "year" => date("Y", $date),
					"month" => date("m", $date), "day" => date("d", $date), "start_hour" => floor($row['hour']),
					"start_minute" => (($row['hour'] - floor($row['hour'])) * 60), "end_hour" => floor($row['hour'] + .25),
					"end_minute" => ((($row['hour'] + .25) - floor($row['hour'] + .25)) * 60));
				$lastHour = $row['hour'];
				$currentDate = $row['date_needed'];
				$currentFacilityId = $row['facility_id'];
				continue;
			}
			if ($row['hour'] <= ($lastHour + .25)) {
				if ($row['hour'] > $lastHour) {
					$scheduleArray[$index]["end_hour"] = floor($row['hour'] + .25);
					$scheduleArray[$index]["end_minute"] = ((($row['hour'] + .25) - floor($row['hour'] + .25)) * 60);
				}
			} else {
				$index++;
				$date = strtotime($row['date_needed']);
				$scheduleArray[$index] = array("facility_id" => $row['facility_id'], "year" => date("Y", $date),
					"month" => date("m", $date), "day" => date("d", $date), "start_hour" => floor($row['hour']),
					"start_minute" => (($row['hour'] - floor($row['hour'])) * 60), "end_hour" => floor($row['hour'] + .25),
					"end_minute" => ((($row['hour'] + .25) - floor($row['hour'] + .25)) * 60));
			}
			$lastHour = $row['hour'];
			$currentDate = $row['date_needed'];
			$currentFacilityId = $row['facility_id'];
		}
		$facilitiesSchedule = jsonEncode($scheduleArray);
		$returnArray['facilities_schedule'] = array("data_value" => $facilitiesSchedule, "crc_value" => getCrcValue($facilitiesSchedule));
		$returnArray['next_id'] = array("data_value" => ++$index);

		$scheduleArray = array();
		$resultSet = executeQuery("select * from event_facility_recurrences where event_id = ? order by facility_id,repeat_rules,hour", $returnArray['primary_id']['data_value']);
		$currentRepeatRules = "";
		$lastHour = "";
		$index = 0;
		while ($row = getNextRow($resultSet)) {
			if ($currentRepeatRules != $row['repeat_rules'] || $currentFacilityId != $row['facility_id']) {
				$index++;
				$scheduleArray[$index] = array("facility_id" => $row['facility_id'], "repeat_rules" => $row['repeat_rules'],
					"start" => $row['hour'], "end" => $row['hour']);
				$lastHour = $row['hour'];
				$currentRepeatRules = $row['repeat_rules'];
				$currentFacilityId = $row['facility_id'];
				continue;
			}
			if ($row['hour'] <= ($lastHour + .25)) {
				if ($row['hour'] > $lastHour) {
					$scheduleArray[$index]["end"] = $row['hour'];
				}
			} else {
				$index++;
				$scheduleArray[$index] = array("facility_id" => $row['facility_id'], "repeat_rules" => $row['repeat_rules'],
					"start" => $row['hour'], "end" => $row['hour']);
			}
			$lastHour = $row['hour'];
			$currentRepeatRules = $row['repeat_rules'];
			$currentFacilityId = $row['facility_id'];
		}
		$repeatRules = array();
		foreach ($scheduleArray as $repeatIndex => $repeatInfo) {
			$repeatRules[$repeatIndex] = "START=" . $repeatInfo['start'] .
				";END=" . $repeatInfo['end'] . ";FACILITY_ID=" . $repeatInfo['facility_id'] . ";" . $repeatInfo['repeat_rules'];
		}
		$repeatRulesValue = jsonEncode($repeatRules);
		$returnArray['recurring_list'] = array("data_value" => $repeatRulesValue, "crc_value" => getCrcValue($repeatRulesValue));
		if (empty($returnArray['country_id']['data_value'])) {
			$returnArray['country_id'] = array("data_value" => "1000");
			$returnArray['date_created'] = array("data_value" => date("m/d/Y"));
		}
		$returnArray['recurring_start_date'] = array("data_value" => $returnArray['start_date']['data_value']);
		$returnArray['until'] = array("data_value" => $returnArray['end_date']['data_value']);
		return true;
	}

	function getCustomFieldInformation($eventId, $eventTypeId) {
		$customFieldInformation = array();
		ob_start();
		$customFields = CustomField::getCustomFields("events");
		foreach ($customFields as $thisCustomField) {
			$eventTypeCustomFieldId = getFieldFromId("event_type_custom_field_id", "event_type_custom_fields", "event_type_id",
				$eventTypeId, "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($eventTypeCustomFieldId)) {
				continue;
			}
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl();
		}
		$customFieldInformation['custom_data'] = array("data_value" => ob_get_clean());
		foreach ($customFields as $thisCustomField) {
			$eventTypeCustomFieldId = getFieldFromId("event_type_custom_field_id", "event_type_custom_fields", "event_type_id",
				$eventTypeId, "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($eventTypeCustomFieldId)) {
				continue;
			}
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldData = $customField->getRecord($eventId);
			if (is_array($customFieldInformation['select_values']) && is_array($customFieldData['select_values'])) {
				$customFieldData['select_values'] = array_merge($customFieldInformation['select_values'], $customFieldData['select_values']);
			}
			$customFieldInformation = array_merge($customFieldInformation, $customFieldData);
		}
		return $customFieldInformation;
	}

	function jqueryTemplates() {
		$customFields = CustomField::getCustomFields("events");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getTemplate();
		}
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$eventTypeId = getFieldFromId("event_type_id", "events", "event_id", $nameValues['primary_id']);
		$customFields = CustomField::getCustomFields("events");
		foreach ($customFields as $thisCustomField) {
			$eventTypeCustomFieldId = getFieldFromId("event_type_custom_field_id", "event_type_custom_fields", "event_type_id",
				$eventTypeId, "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($eventTypeCustomFieldId)) {
				continue;
			}
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if (!$customField->saveData($nameValues)) {
				return $customField->getErrorMessage();
			}
		}
		$facilitiesSchedule = json_decode($nameValues['facilities_schedule'], true);
		executeQuery("delete from event_facilities where event_id = ?", $nameValues['primary_id']);
		foreach ($facilitiesSchedule as $reservation) {
			if (!empty($reservation['event_id']) && $reservation['event_id'] != $nameValues['primary_id']) {
				continue;
			}
			$facilityId = $reservation['facility_id'];
			$dateNeeded = $reservation['year'] . "-" . ($reservation['month'] < 10 ? "0" : "") . $reservation['month'] . "-" . ($reservation['day'] < 10 ? "0" : "") . $reservation['day'];
			$startHour = $reservation['start_hour'] + ($reservation['start_minute'] / 60);
			$endHour = $reservation['end_hour'] + ($reservation['end_minute'] / 60);
			for ($x = $startHour; $x < $endHour; $x += .25) {
				$resultSet = executeQuery("select * from event_facilities where event_id = ? and facility_id = ? and date_needed = ? and hour = ?",
					$nameValues['primary_id'], $facilityId, $dateNeeded, $x);
				if (!$row = getNextRow($resultSet)) {
					executeQuery("insert into event_facilities (event_id,facility_id,date_needed,hour) values (?,?,?,?)",
						$nameValues['primary_id'], $facilityId, $dateNeeded, $x);
				}
			}
		}
		foreach ($nameValues as $fieldName => $fieldValue) {
			if (!substr($fieldName, 0, strlen("existing_facility_reservation_")) == "existing_facility_reservation_") {
				continue;
			}
			$rowNumber = substr($fieldName, strlen("existing_facility_reservation_"));
			if (empty($nameValues['delete_existing_facility_reservation_' . $rowNumber])) {
				continue;
			}
			$reservationArray = json_decode($fieldValue, true);
			$facilityDate = date("Y-m-d", strtotime($reservationArray['year'] . "-" . $reservationArray['month'] . "-" . $reservationArray['day']));
			$startHour = $reservationArray['start_hour'] + (round($reservationArray['start_minute'] * 4 / 60, 0) / 4);
			$endHour = $reservationArray['end_hour'] + (round($reservationArray['end_minute'] * 4 / 60, 0) / 4) - .25;
			$resultSet = executeQuery("delete from event_facilities where event_id = ? and facility_id = ? and date_needed = ? and hour between ? and ?", $nameValues['primary_id'], $reservationArray['facility_id'], $facilityDate, $startHour, $endHour);
		}

		executeQuery("delete from event_facility_recurrences where event_id = ?", $nameValues['primary_id']);
		foreach ($_POST as $fieldName => $recurringSchedule) {
			if (substr($fieldName, 0, strlen("repeat_rules_")) == "repeat_rules_" && !empty($recurringSchedule)) {
				$parts = parseNameValues($recurringSchedule);
				if (!empty($parts['facility_id'])) {
					$facilityId = $parts['facility_id'];
					$startHour = $parts['start'];
					if (empty($startHour)) {
						$startHour = 0;
					}
					$endHour = $parts['end'];
					if (empty($endHour)) {
						$endHour = 23.75;
					}
					$repeatRules = Events::assembleRepeatRules($parts);
					for ($x = $startHour; $x <= $endHour; $x += .25) {
						executeQuery("insert into event_facility_recurrences (event_id,facility_id,repeat_rules,hour) values (?,?,?,?)",
							$nameValues['primary_id'], $facilityId, $repeatRules, $x);
					}
				}
			}
		}
		return true;
	}

	function afterSaveDone($nameValues) {
		$attendeeCounts = Events::getAttendeeCounts($nameValues['primary_id']);
		if (!empty($nameValues['product_id'])) {
			if ($attendeeCounts['registrants'] >= $attendeeCounts['attendees']) {
				executeQuery("update products set non_inventory_item = 0 where product_id = ?", $nameValues['product_id']);
				executeQuery("update product_inventories set quantity = 0 where product_id = ?", $nameValues['product_id']);
			} else {
				executeQuery("update products set non_inventory_item = 1 where product_id = ?", $nameValues['product_id']);
			}
		}
		Events::notifyCRM($nameValues['primary_id']);
		Events::sendEventNotifications($nameValues['primary_id']);
		return true;
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "email_registrant":
				$eventRow = getRowFromId("events", "event_id", $_GET['event_id']);
				if (empty($eventRow)) {
					$returnArray['error_message'] = "Invalid Event";
					ajaxResponse($returnArray);
				}
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
				if (empty($contactId)) {
					$returnArray['error_message'] = "Invalid Contact";
					ajaxResponse($returnArray);
				}
				$substitutions = Events::getEventRegistrationSubstitutions($eventRow, $contactId);

				if (empty($eventRow['email_id'])) {
					$eventRow['email_id'] = getFieldFromId("email_id", "event_type_location_emails", "event_type_id", $eventRow['event_type_id'], "location_id = ?", $eventRow['location_id']);
					if (empty($eventRow['email_id'])) {
						$eventRow['email_id'] = getFieldFromId("email_id", "event_types", "event_type_id", $eventRow['event_type_id']);
					}
				}
				if (!empty($eventRow['email_id'])) {
					sendEmail(array("email_id" => $eventRow['email_id'], "email_address" => $substitutions['email_address'], "substitutions" => $substitutions));
					$returnArray['info_message'] = "Email sent to " . getDisplayName($_GET['contact_id']);
				} else {
					$returnArray['error_message'] = "No Email defined for this event";
				}
				echo jsonEncode($returnArray);
				exit;
			case "get_custom_data":
				$returnArray = $this->getCustomFieldInformation($_GET['primary_id'], $_GET['event_type_id']);
				ajaxResponse($returnArray);
				break;
			case "get_recurring":
				$repeatRulesArray = json_decode($_POST['recurring_list']);
				$events = array();
				$startDate = date("Y-m-d", strtotime($_POST['start']));
				$endDate = date("Y-m-d", strtotime($_POST['end']));
				$eventEndDate = (empty($_POST['end_date']) ? "" : date("Y-m-d", strtotime($_POST['end_date'])));
				$facilityId = $_POST['facility_id'];
				while ($startDate <= $endDate) {
					foreach ($repeatRulesArray as $repeatIndex => $repeatRules) {
						if (empty($repeatRules)) {
							continue;
						}
						$parts = parseNameValues($repeatRules);
						if ($facilityId != $parts['facility_id']) {
							continue;
						}
						if (!empty($eventEndDate) && (empty($parts['until']) || date("Y-m-d", strtotime($parts['until'])) > $eventEndDate)) {
							$parts['until'] = date("m/d/Y", strtotime($eventEndDate));
						}
						if (isInSchedule($startDate, $repeatRules)) {
							$parts = parseNameValues($repeatRules);
							$thisStartHour = floor($parts['start']) . ":" . str_pad(($parts['start'] - floor($parts['start'])) * 60, 2, "0", STR_PAD_LEFT) . ":00";
							$thisEndHour = floor($parts['end'] + .25) . ":" . str_pad(($parts['end'] + .25 - floor($parts['end'] + .25)) * 60, 2, "0", STR_PAD_LEFT) . ":00";
							$events[] = array("id" => "repeat_rules_" . $repeatIndex . "_" . date("Ymd", strtotime($startDate)),
								"title" => "This event (recurring)", "start" => date("c", strtotime($startDate . " " . $thisStartHour)),
								"end" => date("c", strtotime($startDate . " " . $thisEndHour)), "editable" => false, "allDay" => false,
								"deletable" => true, "instance_date" => date("m/d/Y", strtotime($startDate)), "repeat_index" => $repeatIndex);
						}
					}
					$startDate = date("Y-m-d", strtotime("+1 day", strtotime($startDate)));
				}
				$returnArray['events'] = $events;
				ajaxResponse($returnArray);
				break;
			case "get_other_events":
				$eventId = $_GET['event_id'];
				$facilityId = $_GET['facility_id'];
				$startDate = date("Y-m-d", strtotime($_GET['start']));
				$endDate = date("Y-m-d", strtotime($_GET['end']));
				$scheduleArray = array();
				$resultSet = executeQuery("select * from event_facilities where facility_id = ? and date_needed between ? and ? order by event_id,facility_id,date_needed,hour", $facilityId, $startDate, $endDate);
				$currentDate = "";
				$currentEventId = "";
				$lastHour = "";
				$index = -1;
				while ($row = getNextRow($resultSet)) {
					if ($eventId == $row['event_id']) {
						continue;
					}
					$thisDate = date("Y-m-d", strtotime($row['date_needed']));
					$thisStartHour = floor($row['hour']) . ":" . str_pad(($row['hour'] - floor($row['hour'])) * 60, 2, "0", STR_PAD_LEFT) . ":00";
					$thisEndHour = floor($row['hour'] + .25) . ":" . str_pad((($row['hour'] + .25) - floor($row['hour'] + .25)) * 60, 2, "0", STR_PAD_LEFT) . ":00";
					if ($currentDate != $row['date_needed'] || $currentEventId != $row['event_id']) {
						$index++;
						$scheduleArray[$index] = array("id" => $row['event_facility_id'], "event_id" => $row['event_id'], "title" => getFieldFromId("description", "events", "event_id", $row['event_id']),
							"start" => date("c", strtotime($thisDate . " " . $thisStartHour)), "end" => date("c", strtotime($thisDate . " " . $thisEndHour)));
						$lastHour = $row['hour'];
						$currentDate = $row['date_needed'];
						$currentEventId = $row['event_id'];
						continue;
					}
					if ($row['hour'] <= ($lastHour + .25)) {
						if ($row['hour'] > $lastHour) {
							$scheduleArray[$index]["end"] = date("c", strtotime($thisDate . " " . $thisEndHour));
						}
					} else {
						$index++;
						$scheduleArray[$index] = array("id" => $row['event_facility_id'], "event_id" => $row['event_id'], "title" => getFieldFromId("description", "events", "event_id", $row['event_id']),
							"start" => date("c", strtotime($thisDate . " " . $thisStartHour)), "end" => date("c", strtotime($thisDate . " " . $thisEndHour)));
					}
					$lastHour = $row['hour'];
					$currentDate = $row['date_needed'];
					$currentEventId = $row['event_id'];
				}

				$recurrenceArray = array();
				$currentEventId = "";
				$currentRepeatRules = "";
				$lastHour = "";
				$recurIndex = -1;
				$facilityId = $_GET['facility_id'];
				$resultSet = executeQuery("select * from event_facility_recurrences where facility_id = ? order by event_id,facility_id,repeat_rules,hour", $facilityId);
				while ($row = getNextRow($resultSet)) {
					if ($row['event_id'] == $eventId) {
						continue;
					}
					if ($currentEventId != $row['event_id'] || $currentRepeatRules != $row['repeat_rules']) {
						$recurIndex++;
						$row['start'] = $row['hour'];
						$row['end'] = $row['hour'];
						$recurrenceArray[$recurIndex] = $row;
						$lastHour = $row['hour'];
						$currentEventId = $row['event_id'];
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
					$lastHour = $row['hour'];
					$currentEventId = $row['event_id'];
					$currentRepeatRules = $row['repeat_rules'];
				}
				while ($startDate <= $endDate) {
					foreach ($recurrenceArray as $repeatIndex => $row) {
						if (empty($row['repeat_rules'])) {
							continue;
						}
						if (isInSchedule($startDate, $row['repeat_rules'])) {
							$parts = parseNameValues($row['repeat_rules']);
							$thisStartHour = floor($row['start']) . ":" . str_pad(($row['start'] - floor($row['start'])) * 60, 2, "0", STR_PAD_LEFT) . ":00";
							$thisEndHour = floor($row['end'] + .25) . ":" . str_pad((($row['end'] + .25) - floor($row['end'] + .25)) * 60, 2, "0", STR_PAD_LEFT) . ":00";
							$scheduleArray[++$index] = array("id" => "repeat_rules_" . $repeatIndex . "_" . date("Ymd", strtotime($startDate)),
								"title" => getFieldFromId("description", "events", "event_id", $row['event_id']),
								"start" => date("c", strtotime($startDate . " " . $thisStartHour)), "end" => date("c", strtotime($startDate . " " . $thisEndHour)),
								"editable" => false, "allDay" => false, "deletable" => false);
						}
					}
					$startDate = date("Y-m-d", strtotime("+1 day", strtotime($startDate)));
				}
				echo jsonEncode($scheduleArray);
				exit;
			case "get_readable":
				$ends = array('th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th');
				$parts = parseNameValues($_POST['repeat_rules']);
				$index = $_POST['index'];
				$readableText = getFieldFromId("description", "facilities", "facility_id", $parts['facility_id']) . ", ";
				if (!empty($parts['start_date'])) {
					$readableText .= "Starting " . $parts['start_date'] . ", ";
				}
				$readableText .= "Every " . ($parts['interval'] == "1" || empty($parts['interval']) ? "" : ($parts['interval'] == "2" ? "other" : $parts['interval'])) . " ";
				switch ($parts['frequency']) {
					case "DAILY":
						$readableText .= ($parts['interval'] == "1" || empty($parts['interval']) || $parts['interval'] == "2" ? "day" : "days");
						break;
					case "WEEKLY":
						$readableText .= ($parts['interval'] == "1" || empty($parts['interval']) || $parts['interval'] == "2" ? "week" : "weeks");
						break;
					case "MONTHLY":
						$readableText .= ($parts['interval'] == "1" || empty($parts['interval']) || $parts['interval'] == "2" ? "month" : "months");
						break;
					case "YEARLY":
						$readableText .= ($parts['interval'] == "1" || empty($parts['interval']) || $parts['interval'] == "2" ? "year" : "years");
						break;
				}
				if (!empty($parts['bymonth'])) {
					$monthList = "";
					$monthArray = explode(",", $parts['bymonth']);
					sort($monthArray);
					foreach ($monthArray as $month) {
						if (array_key_exists($month, $GLOBALS['gMonthArray'])) {
							if (!empty($monthList)) {
								$monthList .= ", ";
							}
							$monthList .= $GLOBALS['gMonthArray'][$month];
						}
					}
					if (!empty($monthList)) {
						$readableText .= ", in " . $monthList;
					}
				}
				if (!empty($parts['byday'])) {
					$dayList = "";
					$dayParts = explode(",", $parts['byday']);
					$dayIndex = 0;
					foreach ($dayParts as $day) {
						$dayIndex++;
						if (strlen($day) == 3) {
							$weekday = $day;
							$day = "";
						} else if (strlen($day) > 2) {
							$weekday = substr($day, 1);
							$day = substr($day, 0, 1);
						} else {
							$weekday = "";
						}
						if (!empty($dayList)) {
							$dayList .= ($dayIndex == count($dayParts) ? " and " : ", ");
						}
						if ($day == "-") {
							$dayList .= "last " . (empty($weekday) ? "day" : $GLOBALS['gWeekdayCodes'][strtoupper($weekday)]);
						} else if (!empty($day)) {
							if (($day % 100) >= 11 && ($day % 100) <= 13) {
								$dayList .= $day . "th";
							} else {
								$dayList .= $day . $ends[$day % 10];
							}
							if (!empty($weekday)) {
								$dayList .= " " . $GLOBALS['gWeekdayCodes'][strtoupper($weekday)];
							}
						} else {
							$dayList .= $GLOBALS['gWeekdayCodes'][strtoupper($weekday)];
						}
					}
					if (!empty($dayList)) {
						$readableText .= ", on " . ($parts['frequency'] == "WEEKLY" ? "" : "the ") . $dayList;
					}
				}
				if (!empty($parts['count'])) {
					$readableText .= ", only " . $parts['count'] . " time" . ($parts['count'] == 1 ? "" : "s");
				}
				if (!empty($parts['until'])) {
					$readableText .= ", until " . $parts['until'];
				}
				if (!empty($parts['not'])) {
					$readableText .= ", but not on " . str_replace(",", ", ", $parts['not']);
				}
				if ($parts['start'] == 0 && $parts['end'] == 48) {
					$readableText .= ", All day";
				} else {
					$workingHour = floor($parts['start']);
					$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
					$displayMinutes = ($parts['start'] - $workingHour) * 60;
					$displayAmpm = ($parts['start'] == 0 ? "midnight" : ($parts['start'] == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
					$readableText .= ", " . $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm;
					$workingHour = floor($parts['end'] + .25);
					$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
					$displayMinutes = ($parts['end'] + .25 - $workingHour) * 60;
					$displayAmpm = ($parts['end'] == 23.75 ? "midnight" : ($parts['end'] == 11.75 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
					$readableText .= "-" . $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm;
				}
				$returnArray['readable_text'] = $readableText;
				$returnArray['index'] = $index;
				ajaxResponse($returnArray);
				break;
		}
	}

	function internalCSS() {
		?>
        <style>
	        #product_link {
		        margin-bottom: 20px;
	        }
            .event-order-id {
                cursor: pointer;
            }

            #room_reservations li {
                padding: 5px;

            span {
                margin-left: 10px;
                color: rgb(192, 0, 0);
                font-size: 20px;
                cursor: pointer;
            }

            }
            .email-registrant {
                font-size: 24px;
                cursor: pointer;
            }

            #facility_calendar {
                border: 1px solid rgb(200, 200, 200);
                padding: 20px;
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

            #readable_recurring_list {
                margin-top: 20px;
                margin-left: 50px;
                width: 800px;
            }

            #readable_recurring_list td {
                padding: 5px;
            }

            .delete-recur {
                width: 30px;
                text-align: center;
                cursor: pointer;
            }

            .deleted-schedule {
                text-decoration: line-through;
            }

            #room_reservations ul {
                list-style: none;
            }

            .subheader {
                margin-top: 20px;
                font-weight: 700;
            }

            #_maintenance_form #_event_registrants_row .editable-list select {
                max-width: 400px;
                width: 400px;
            }

            #_maintenance_form #_event_registrants_row .editable-list input[type=text] {
                width: 200px;
            }
        </style>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
			<?php if (canAccessPageCode("ORDERDASHBOARD")) { ?>
            $(document).on("click", ".event-order-id", function () {
                if (!empty($(this).val())) {
                    window.open("/order-dashboard?url_page=show&primary_id=" + $(this).val());
                }
            });
			<?php } ?>
			$(document).on("change", "#product_id", function() {
                $("#product_link").html("");
			});
            $(document).on("click", ".delete-reservation", function () {
                $(this).closest("li").addClass("hidden");
                $(this).closest("li").find(".delete-existing").val("1");
            });
            $(document).on("click", ".email-registrant", function () {
                const eventId = $("#primary_id").val();
                if (empty(eventId)) {
                    displayErrorMessage("Save the event first");
                    return;
                }
                const contactId = $(this).closest("tr").find(".contact-picker-value").val();
                if (empty(contactId)) {
                    displayErrorMessage("No contact set yet");
                    return;
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=email_registrant&contact_id=" + contactId + "&event_id=" + eventId);
            });
            $("#event_type_id").change(function () {
                if (empty($("#description").val())) {
                    $("#description").val($("#event_type_id option:selected").text());
                }
                $("#custom_data").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_custom_data&event_type_id=" + $(this).val() + "&primary_id=" + $("#primary_id").val(), function(returnArray) {
                        if ("custom_data" in returnArray && "data_value" in returnArray['custom_data']) {
                            $("#custom_data").html(returnArray['custom_data']['data_value']);
                            afterGetRecord(returnArray);
                        }
                    });
                }
            });
            $("#postal_code").blur(function () {
                if ($("#country_id").val() === "1000") {
                    validatePostalCode();
                }
            });
            $("#country_id").change(function () {
                $("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
                $("#_city_row").show();
                $("#_city_select_row").hide();
                if ($("#country_id").val() === "1000") {
                    validatePostalCode();
                }
            });
            $("#city_select").change(function () {
                $("#city").val($(this).val());
                $("#state").val($(this).find("option:selected").data("state"));
            });
            $(document).on("click", ".delete-recur", function () {
                const thisDeleteRow = $(this);
                $('#_confirm_delete_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    title: 'Delete Record?',
                    buttons: {
                        Yes: function (event) {
                            thisDeleteRow.closest("tr").find("td:first-child").addClass("deleted-schedule");
                            thisDeleteRow.closest("tr").find("input[type=hidden]").val("");
                            $("#_confirm_delete_dialog").dialog('close').dialog('destroy');
                            const recurringList = {};
                            $(".repeat-rules").each(function () {
                                recurringList[$(this).data("repeat_index")] = $(this).val();
                            });
                            $("#recurring_list").val(JSON.stringify(recurringList));
                        },
                        Cancel: function (event) {
                            $("#_confirm_delete_dialog").dialog('close').dialog('destroy');
                        }
                    }
                });
            });
            $("#start_date").blur(function () {
                $("#recurring_start_date").val($("#start_date").val());
            });
            $("#start_date").datepicker("option", "onClose", function (date) {
                $("#recurring_start_date").val($("#start_date").val());
            });
            $("#end_date").blur(function () {
                $("#until").val($("#end_date").val());
            });
            $("#end_date").datepicker("option", "onClose", function (date) {
                $("#until").val($("#end_date").val());
            });
            $("#_create_reservations").click(function () {
                if (empty($("#facility_ids").val())) {
                    displayErrorMessage("You must choose at least one facility");
                    return false;
                }
                if (empty($("#reserve_start_date").val()) || $("#reserve_start_date").is(".formFieldError")) {
                    displayErrorMessage("Start Date is required");
                    return false;
                }
                if (empty($("#reserve_end_date").val()) || $("#reserve_end_date").is(".formFieldError")) {
                    displayErrorMessage("End Date is required");
                    return false;
                }
                if ($.formatDate($("#reserve_end_date").val(), "yyyy-MM-dd") < $.formatDate($("#reserve_start_date").val(), "yyyy-MM-dd")) {
                    displayErrorMessage("End Date is before Start Date");
                    return false;
                }
                disableButtons($(this).html("Creating"));
                setTimeout("setReservations()", 100);
                return false;
            });
            $("#calendar_facility_id").change(function () {
                initializeCalendar();
            });
            $('.tabbed-form').tabs({
                activate: function (event, ui) {
                    if (ui.newTab.index() === 4) {
                        initializeCalendar();
                    }
                    if (ui.newTab.index() === 3) {
                        displaySchedules();
                    }
                }
            })
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
            $("#facility_id").click(function () {
                $("#facility_id").validationEngine("hideAll").removeClass("formFieldError");
            });
            $(".bymonth-month").click(function () {
                $(".bymonth-month:first").validationEngine("hideAll").removeClass("formFieldError");
            });
            $(".byday-weekday").click(function () {
                $(".byday-weekday:first").validationEngine("hideAll").removeClass("formFieldError");
            });
            $("#end_time").change(function () {
                $("#end_time").validationEngine("hideAll").removeClass("formFieldError");
            });
            $("#create_recurring_schedule").click(function () {
                if ($("#until").validationEngine("validate") || $("#count").validationEngine("validate") || $("#interval").validationEngine("validate")) {
                    return false;
                }
                if (empty($("#facility_id").val())) {
                    $("#facility_id").validationEngine("showPrompt", "Facility is required", "error", "topLeft", true);
                    return false;
                }
                if (empty($("#recurring_start_date").val())) {
                    $("#recurring_start_date").validationEngine("showPrompt", "Start date is required", "error", "topLeft", true);
                    return false;
                }
                if (!empty($("#start_time").val()) && parseInt($("#end_time").val()) < parseInt($("#start_time").val())) {
                    $("#end_time").validationEngine("showPrompt", "End time must be after start time", "error", "topLeft", true);
                    return false;
                }
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
                if (empty($("#start_time").val())) {
                    $("#start_time").val("0");
                }
                if (empty($("#end_time").val())) {
                    $("#end_time").val("23.75");
                }
                let recurringSchedule = "FACILITY_ID=" + $("#facility_id").val() + ";START_DATE=" + $("#recurring_start_date").val() + ";START=" + $("#start_time").val() + ";END=" + $("#end_time").val() + ";FREQUENCY=" + $("#frequency").val() + ";";
                if (!empty($("#interval").val()) && $("#interval").val() !== "1") {
                    recurringSchedule += "INTERVAL=" + $("#interval").val() + ";";
                }
                if ($("#frequency").val() === "YEARLY") {
                    recurringSchedule += "BYMONTH=";
                    let parts = "";
                    $(".bymonth-month:checked").each(function () {
                        parts += (empty(parts) ? "" : ",") + $(this).val();
                    });
                    recurringSchedule += parts + ";";
                }
                if ($("#frequency").val() === "WEEKLY") {
                    recurringSchedule += "BYDAY=";
                    let parts = "";
                    $(".byday-weekday:checked").each(function () {
                        parts += (empty(parts) ? "" : ",") + $(this).val();
                    });
                    recurringSchedule += parts + ";";
                }
                if ($("#frequency").val() === "MONTHLY" || $("#frequency").val() === "YEARLY") {
                    recurringSchedule += "BYDAY=";
                    let parts = "";
                    $(".ordinal-day").each(function () {
                        if (!empty($(this).val())) {
                            parts += (empty(parts) ? "" : ",") + $(this).val();
                            parts += $(this).closest("tr").find(".weekday-select").val();
                        }
                    });
                    recurringSchedule += parts + ";";
                }
                if (!empty($("#count").val())) {
                    recurringSchedule += "COUNT=" + $("#count").val() + ";";
                }
                if (!empty($("#until").val())) {
                    recurringSchedule += "UNTIL=" + $("#until").val() + ";";
                }
                const recurringList = JSON.parse($("#recurring_list").val());
                let newIndex = 0;
                for (const i in recurringList) {
                    if (i > newIndex) {
                        newIndex = i;
                    }
                }
                newIndex++;
                recurringList[newIndex] = recurringSchedule;
                $("#recurring_list").val(JSON.stringify(recurringList));
                displayRecurringItem(newIndex, recurringSchedule);
                $("#interval").val("");
                $(".bymonth-month").prop("checked", false);
                $(".byday-weekday").prop("checked", false);
                $(".ordinal-day").val("").each(function () {
                    $(this).closest(".byday-monthly-row").hide();
                }).each(function () {
                    $(this).closest(".byday-monthly-row").show();
                    return false;
                });
                $(".weekday-select").val("");
                $("#count").val("");
                $("#until").val("");
                $("#start_time").val("");
                $("#end_time").val("");
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function setReservations() {
                const schedulesArray = JSON.parse($("#facilities_schedule").val());
                const facilityIds = $("#facility_ids").val().split(",");
                for (const i in facilityIds) {
                    let thisDay = $.formatDate($("#reserve_start_date").val(), "MM/dd/yyyy");
                    while ($.formatDate(thisDay, "yyyy-MM-dd") <= $.formatDate($("#reserve_end_date").val(), "yyyy-MM-dd")) {
                        const year = $.formatDate(thisDay, "yyyy");
                        const month = $.formatDate(thisDay, "M");
                        const day = $.formatDate(thisDay, "d");
                        const thisId = $("#next_id").val();
                        $("#next_id").val(parseInt(thisId) + 1);
                        schedulesArray[thisId] = {};
                        schedulesArray[thisId]['facility_id'] = facilityIds[i];
                        schedulesArray[thisId]['year'] = year;
                        schedulesArray[thisId]['month'] = month;
                        schedulesArray[thisId]['day'] = day;
                        schedulesArray[thisId]['start_hour'] = $("#reserve_start_time option:selected").data("hour");
                        schedulesArray[thisId]['start_minute'] = $("#reserve_start_time option:selected").data("minute");
                        schedulesArray[thisId]['end_hour'] = $("#reserve_end_time option:selected").data("hour");
                        schedulesArray[thisId]['end_minute'] = $("#reserve_end_time option:selected").data("minute");
                        thisDay = addDays(thisDay, 1);
                    }
                }
                $("#facility_ids").val("").trigger("change");
                $("#reserve_start_date").add("#reserve_end_date").add("#reserve_start_time").add("#reserve_end_time").val("");
                $("#facilities_schedule").val(JSON.stringify(schedulesArray));
                displayInfoMessage("Reservation added to calendar");
                enableButtons($("#_create_reservations").html("Create Reservation"));
            }

            function displaySchedules() {
                let roomReservations = "";
                if ($(".recurring-description").not(".deleted-schedule").length > 0) {
                    roomReservations += "<p class='subheader'>Recurring Reservations</p><ul>";
                    $(".recurring-description").not(".deleted-schedule").each(function () {
                        roomReservations += "<li>" + $(this).html() + "</li>";
                    });
                    roomReservations += "</ul>";
                }
                const schedulesArray = JSON.parse($("#facilities_schedule").val());
                if (Object.keys(schedulesArray).length > 0) {
                    roomReservations += "<p class='subheader'>Room Reservations</p>";
                    roomReservations += "<p><strong>Note:</strong> To extend a reservation, do not delete the existing reservation. It is only necessary to reserve the additional hours.</p><ul>";
                    for (const i in schedulesArray) {
                        const testDate = $.formatDate(new Date(schedulesArray[i]['year'], schedulesArray[i]['month'] - 1, schedulesArray[i]['day']), "yyyy-MM-dd");
                        const facility = $("#calendar_facility_id option[value='" + schedulesArray[i]['facility_id'] + "']").text();
                        const eventDate = $.formatDate(new Date(schedulesArray[i]['year'], schedulesArray[i]['month'] - 1, schedulesArray[i]['day']), "EEEE, MMMM d, yyyy");
                        const startTime = $.formatDate(new Date(schedulesArray[i]['year'], schedulesArray[i]['month'] - 1, schedulesArray[i]['day'], schedulesArray[i]['start_hour'], schedulesArray[i]['start_minute']), "h:mm a");
                        const endTime = $.formatDate(new Date(schedulesArray[i]['year'], schedulesArray[i]['month'] - 1, schedulesArray[i]['day'], schedulesArray[i]['end_hour'], schedulesArray[i]['end_minute']), "h:mm a");
                        roomReservations += "<li>" + facility + ", " + eventDate + ", " + startTime + " - " + endTime + "<input type='hidden' id='existing_facility_reservation_" + i +
                            "' name='existing_facility_reservation_" + i + "' value='" + JSON.stringify(schedulesArray[i]) + "'><input type='hidden' class='delete-existing' id='delete_existing_facility_reservation_" + i +
                            "' name='delete_existing_facility_reservation_" + i + "' value=''> <span class='delete-reservation fad fa-trash'></span></li>";
                    }
                    roomReservations += "</ul>";
                }
                $("#room_reservations").html(roomReservations);
            }

            function initializeCalendar(viewName) {
                if (viewName == null) {
                    viewName = $("#facility_calendar").data("view");
                }
                if (empty(viewName)) {
                    viewName = "agendaWeek";
                }
                $("#facility_calendar").data("view", viewName).fullCalendar("destroy");
                if (empty($("#calendar_facility_id").val())) {
                    return;
                }
                const calendar = $('#facility_calendar').fullCalendar({
                    allDaySlot: false,
                    height: 500,
                    defaultView: viewName,
                    defaultEventMinutes: 30,
                    allDayDefault: false,
                    header: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'month,agendaWeek,agendaDay'
                    },
                    viewDisplay: function (view) {
                        if (view.name !== viewName) {
                            initializeCalendar(view.name);
                        }
                    },
                    selectable: false,
                    selectHelper: false,
                    eventSources: [
                        {
                            url: '<?= $GLOBALS['gLinkUrl'] ?>?url_action=get_other_events&event_id=' + $("#primary_id").val() + "&facility_id=" + $("#calendar_facility_id").val(),
                            type: 'GET',
                            backgroundColor: 'gray',
                            editable: false,
                            allDayDefault: false
                        },
                        {
                            events: function (start, end, timezone, callback) {
                                const events = [];
                                const startTime = start.format("X");
                                const endTime = end.format("X");
                                const schedulesArray = JSON.parse($("#facilities_schedule").val());
                                for (const i in schedulesArray) {
                                    if (schedulesArray[i]['facility_id'] === $("#calendar_facility_id").val()) {
                                        const thisDate = Math.round((new Date(schedulesArray[i]['year'], schedulesArray[i]['month'] - 1, schedulesArray[i]['day'], schedulesArray[i]['start_hour'], schedulesArray[i]['start_minute'])).getTime() / 1000);
                                        if (thisDate <= endTime && thisDate >= startTime) {
                                            events.push({
                                                id: i,
                                                title: 'This Event',
                                                start: new Date(schedulesArray[i]['year'], schedulesArray[i]['month'] - 1, schedulesArray[i]['day'], schedulesArray[i]['start_hour'], schedulesArray[i]['start_minute']),
                                                end: new Date(schedulesArray[i]['year'], schedulesArray[i]['month'] - 1, schedulesArray[i]['day'], schedulesArray[i]['end_hour'], schedulesArray[i]['end_minute']),
                                                editable: false,
                                                allDay: false
                                            });
                                        }
                                    }
                                }
                                callback(events);
                            }
                        },
                        {
                            events: function (start, end, timezone, callback) {
                                const postData = {};
                                postData['recurring_list'] = $("#recurring_list").val();
                                postData['start'] = start.format("YYYY-MM-DD");
                                postData['end'] = end.format("YYYY-MM-DD");
                                postData['end_date'] = $("#end_date").val();
                                postData['facility_id'] = $("#calendar_facility_id").val();
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_recurring", postData, function(returnArray) {
                                    callback(returnArray['events']);
                                });
                            }
                        }
                    ],
                    editable: false
                });
            }

            let dataArray = {};

            function afterGetRecord(returnArray) {
                if (!empty(returnArray['primary_id']['data_value']) && empty(returnArray['contact_id']['data_value'])) {
                    $("#tab_2").addClass("hidden");
                } else {
                    $("#tab_2").removeClass("hidden");
                }
                $("#custom_data .datepicker").datepicker({
                    showOn: "button",
                    buttonText: "<span class='fad fa-calendar-alt'></span>",
                    constrainInput: false,
                    dateFormat: "mm/dd/y",
                    yearRange: "c-100:c+10"
                });
                $("#custom_data .required-label").append("<span class='required-tag'>*</span>");
                $("#custom_data a[rel^='prettyPhoto']").prettyPhoto({social_tools: false, default_height: 480, default_width: 854, deeplinking: false});
                dataArray = returnArray;
                setTimeout("setCustomData()", 100);
                initializeCalendar();
                displayRecurringList();
                displaySchedules();
                $("#city").add("#state").prop("readonly", $("#country_id").val() === "1000");
                $("#_city_select_row").hide();
                $("#_city_row").show();
            }

            function setCustomData() {
                if ("select_values" in dataArray) {
                    for (const i in dataArray['select_values']) {
                        if (!$("#" + i).is("select")) {
                            continue;
                        }
                        $("#" + i + " option").each(function () {
                            if ($(this).data("inactive") === "1") {
                                $(this).remove();
                            }
                        });
                        for (const j in dataArray['select_values'][i]) {
                            if ($("#" + i + " option[value='" + dataArray['select_values'][i][j]['key_value'] + "']").length === 0) {
                                const inactive = ("inactive" in dataArray['select_values'][i][j] ? dataArray['select_values'][i][j]['inactive'] : "0");
                                $("#" + i).append("<option data-inactive='" + inactive + "' value = '" + dataArray['select_values'][i][j]['key_value'] + "'>" + dataArray['select_values'][i][j]['description'] + "</option>");
                            }
                        }
                    }
                }
                for (const i in dataArray) {
                    if (i.substr(0, 13) != "custom_field_") {
                        continue;
                    }
                    if (typeof dataArray[i] == "object" && "data_value" in dataArray[i]) {
                        if ($("input[type=radio][name='" + i + "']").length > 0) {
                            $("input[type=radio][name='" + i + "']").prop("checked", false);
                            $("input[type=radio][name='" + i + "'][value='" + dataArray[i]['data_value'] + "']").prop("checked", true);
                        } else if ($("#" + i).is("input[type=checkbox]")) {
                            $("#" + i).prop("checked", (!empty(dataArray[i].data_value)));
                        } else if ($("#" + i).is("a")) {
                            $("#" + i).attr("href", dataArray[i].data_value).css("display", (empty(dataArray[i].data_value) ? "none" : "inline"));
                        } else if ($("#_" + i + "_table").is(".editable-list")) {
                            for (const j in dataArray[i].data_value) {
                                addEditableListRow(i, dataArray[i]['data_value'][j]);
                            }
                        } else {
                            $("#" + i).val(dataArray[i].data_value);
                        }
                        if ("crc_value" in dataArray[i]) {
                            $("#" + i).data("crc_value", dataArray[i]['crc_value']);
                        } else {
                            $("#" + i).removeData("crc_value");
                        }
                    }
                }
                $(".selector-value-list").trigger("change");
                $(".multiple-dropdown-values").trigger("change");
            }

            function deleteEvent(eventId) {
                const thisEvent = $("#facility_calendar").fullCalendar('clientEvents', eventId);
                if ("repeat_index" in thisEvent[0]) {
                    $('#_confirm_delete_event_dialog').dialog({
                        closeOnEscape: true,
                        draggable: false,
                        modal: true,
                        resizable: false,
                        position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                        title: 'Delete Event?',
                        buttons: {
                            "Only This One": function (event) {
                                const repeatIndex = thisEvent[0]['repeat_index'];
                                const instanceDate = thisEvent[0].instance_date;
                                const recurringList = JSON.parse($("#recurring_list").val());
                                recurringList[repeatIndex] += "NOT=" + instanceDate + ";";
                                $("#recurring_list").val(JSON.stringify(recurringList));
                                displayRecurringList();
                                $("#facility_calendar").fullCalendar('removeEvents', eventId);
                                $("#_confirm_delete_event_dialog").dialog('close').dialog('destroy');
                            },
                            "All Future": function (event) {
                                const repeatIndex = thisEvent[0]['repeat_index'];
                                const instanceDate = thisEvent[0].instance_date;
                                const recurringList = JSON.parse($("#recurring_list").val());
                                recurringList[repeatIndex] += "UNTIL=" + addDays(instanceDate, -1) + ";";
                                $("#recurring_list").val(JSON.stringify(recurringList));
                                displayRecurringList();
                                $("#facility_calendar").fullCalendar('refetchEvents');
                                $("#_confirm_delete_event_dialog").dialog('close').dialog('destroy');
                            },
                            Cancel: function (event) {
                                $("#_confirm_delete_event_dialog").dialog('close').dialog('destroy');
                            }
                        }
                    });
                } else {
                    const schedulesArray = JSON.parse($("#facilities_schedule").val());
                    delete schedulesArray[eventId];
                    $("#facilities_schedule").val(JSON.stringify(schedulesArray));
                    $("#facility_calendar").fullCalendar('removeEvents', eventId);
                }
            }

            function displayRecurringList() {
                $("#readable_recurring_list").find("tr").remove();
                const recurringList = JSON.parse($("#recurring_list").val());
                for (const i in recurringList) {
                    if (!empty(recurringList[i])) {
                        displayRecurringItem(i, recurringList[i]);
                    }
                }
            }

            function displayRecurringItem(recurringIndex, recurringValue) {
                $("#readable_recurring_list").append("<tr id = 'recurring_value_" + recurringIndex + "'><td class='recurring-description'></td><td class='delete-recur'><input class='repeat-rules' type='hidden'" +
                    " data-repeat_index='" + recurringIndex + "' name='repeat_rules_" + recurringIndex + "' id='repeat_rules_" + recurringIndex + "' value='" + recurringValue + "'/><img alt='Delete' src='/images/delete.gif'/></td></tr>");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_readable", {repeat_rules: recurringValue, index: recurringIndex}, function(returnArray) {
                    $("#recurring_value_" + returnArray['index'] + " td:first-child").html(returnArray['readable_text']);
                    displaySchedules();
                });
            }
        </script>
		<?php
	}

	function reserveFacilities() {
		?>
        <p>For quick reservation of multiple facilities, use this form. For single reservations, the calendar might be easier.</p>
        <input type="hidden" id="facilities_schedule" name="facilities_schedule" value=""/>
        <input type="hidden" id="next_id" name="next_id" value="1"/>

        <div class="basic-form-line" id="_facility_ids_row">
            <label>Facilities</label>
			<?php
			$facilitySelector = new DataColumn("facility_ids");
			$facilitySelector->setControlValue("data_type", "custom_control");
			$facilitySelector->setControlValue("control_class", "MultipleSelect");
			$facilitySelector->setControlValue("primary_table", "events");
			$facilitySelector->setControlValue("control_table", "facilities");
			echo $facilitySelector->getControl();
			?>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line" id="_reserve_start_date_row">
            <label for="reserve_start_date">Reserve Start Date</label>
            <input tabindex='10' class='datepicker validate[custom[date]]' type='text' value='' size='12' maxlength='10' name='reserve_start_date' id='reserve_start_date'/>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line" id="_reserve_end_date_row">
            <label for="reserve_end_date">Reserve End Date</label>
            <input tabindex='10' class='datepicker validate[custom[date]]' type='text' value='' size='12' maxlength='10' name='reserve_end_date' id='reserve_end_date'/>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line" id="_reserve_start_time_row">
            <label for="reserve_start_time">Reserve Start Time</label>
            <select tabindex='10' id="reserve_start_time" name="reserve_start_time">
                <option value="" data-hour="0" data-minute="0">[All Day]</option>
				<?php
				for ($x = 0; $x < 24; $x += .25) {
					$workingHour = floor($x);
					$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
					$displayMinutes = ($x - $workingHour) * 60;
					$displayAmpm = ($x == 0 ? "midnight" : ($x == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
					?>
                    <option value="<?= $x ?>" data-hour="<?= $workingHour ?>" data-minute="<?= $displayMinutes ?>"><?= $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm ?></option>
				<?php } ?>
            </select>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line" id="_reserve_end_time_row">
            <label for="reserve_end_time">Reserve End Time</label>
            <select tabindex='10' id="reserve_end_time" name="reserve_end_time">
                <option value="" data-hour="24" data-minute="0">[All Day]</option>
				<?php
				for ($x = 0; $x < 24; $x += .25) {
					$workingHour = floor($x + .25);
					$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
					$displayMinutes = ($x + .25 - $workingHour) * 60;
					$displayAmpm = (($x + .25) == 24 ? "midnight" : (($x + .24) == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
					?>
                    <option value="<?= $x ?>" data-hour="<?= $workingHour ?>" data-minute="<?= $displayMinutes ?>"><?= $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm ?></option>
				<?php } ?>
            </select>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line" id="_create_reservations_row">
            <button tabindex='10' id="_create_reservations">Create Reservations</button>
        </div>

		<?php
	}

	function facilityCalendars() {
		?>
        <p><select id="calendar_facility_id" name="calendar_facility_id" class="no-clear">
                <option value="" data-cost_per_hour="0" data-cost_per_day="0">[Select facility to see calendar]</option>
				<?php
				$resultSet = executeQuery("select * from facilities where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
                    <option data-cost_per_hour="<?= $row['cost_per_hour'] ?>" data-cost_per_day="<?= $row['cost_per_day'] ?>" value="<?= $row['facility_id'] ?>"><?= htmlText($row['description']) ?></option>
					<?php
				}
				?>
            </select></p>
        <div id="facility_calendar"></div>
		<?php
	}

	function showReservations() {
		?>
        <div id="room_reservations">
        </div>
		<?php
	}

	function recurringSchedules() {
		?>
        <p class="subheader">Recurring Schedules</p>

        <div class="basic-form-line" id="_facility_id_row">
            <label for="facility_id">Facility</label>
            <select tabindex='10' id="facility_id" name="facility_id">
                <option value="">[Select]</option>
				<?php
				$resultSet = executeQuery("select * from facilities where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
                    <option value="<?= $row['facility_id'] ?>"><?= htmlText($row['description']) ?></option>
					<?php
				}
				?>
            </select>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line" id="_recurring_start_date_row">
            <label for="recurring_start_date">Starting on or after</label>
            <input tabindex='10' class='validate[custom[date]] datepicker' type='text' value='' size='12' maxlength='12' name='recurring_start_date' id='recurring_start_date'/>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line" id="_frequency_row">
            <label for="frequency">Frequency</label>
            <select tabindex='10' name='frequency' id='frequency'>
                <option value="DAILY">Daily</option>
                <option value="WEEKLY">Weekly</option>
                <option value="MONTHLY">Monthly</option>
                <option value="YEARLY">Yearly</option>
            </select>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line" id="_interval_row">
            <label for="interval">Interval</label>
            <input tabindex='10' class='validate[custom[integer],min[1]] align-right' type='text' value='' size='4' maxlength='4' name='interval' id='interval'/>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line" id="_bymonth_row">
            <label>Months</label>
            <table id='bymonth_table'>
                <tr>
                    <td><input tabindex='10' class='bymonth-month' type='checkbox' value='1' name='bymonth_1' id='bymonth_1'/><label for="bymonth_1" class="checkbox-label">January</label></td>
                    <td><input tabindex='10' class='bymonth-month' type='checkbox' value='4' name='bymonth_4' id='bymonth_4'/><label for="bymonth_4" class="checkbox-label">April</label></td>
                    <td><input tabindex='10' class='bymonth-month' type='checkbox' value='7' name='bymonth_7' id='bymonth_7'/><label for="bymonth_7" class="checkbox-label">July</label></td>
                    <td><input tabindex='10' class='bymonth-month' type='checkbox' value='10' name='bymonth_10' id='bymonth_10'/><label for="bymonth_10" class="checkbox-label">October</label></td>
                </tr>
                <tr>
                    <td><input tabindex='10' class='bymonth-month' type='checkbox' value='2' name='bymonth_2' id='bymonth_2'/><label for="bymonth_2" class="checkbox-label">February</label></td>
                    <td><input tabindex='10' class='bymonth-month' type='checkbox' value='5' name='bymonth_5' id='bymonth_5'/><label for="bymonth_5" class="checkbox-label">May</label></td>
                    <td><input tabindex='10' class='bymonth-month' type='checkbox' value='8' name='bymonth_8' id='bymonth_8'/><label for="bymonth_8" class="checkbox-label">August</label></td>
                    <td><input tabindex='10' class='bymonth-month' type='checkbox' value='11' name='bymonth_11' id='bymonth_11'/><label for="bymonth_11" class="checkbox-label">November</label></td>
                </tr>
                <tr>
                    <td><input tabindex='10' class='bymonth-month' type='checkbox' value='3' name='bymonth_3' id='bymonth_3'/><label for="bymonth_3" class="checkbox-label">March</label></td>
                    <td><input tabindex='10' class='bymonth-month' type='checkbox' value='6' name='bymonth_6' id='bymonth_6'/><label for="bymonth_6" class="checkbox-label">June</label></td>
                    <td><input tabindex='10' class='bymonth-month' type='checkbox' value='9' name='bymonth_9' id='bymonth_9'/><label for="bymonth_9" class="checkbox-label">September</label></td>
                    <td><input tabindex='10' class='bymonth-month' type='checkbox' value='12' name='bymonth_12' id='bymonth_12'/><label for="bymonth_12" class="checkbox-label">December</label></td>
                </tr>
            </table>
        </div>

        <div class="basic-form-line" id="_byday_row">
            <label>Days</label>
            <table id="byday_weekly_table">
                <tr>
                    <td><input tabindex='10' class='byday-weekday' type='checkbox' value='SUN' name='byday_sun' id='byday_sun'/><label for="byday_sun" class="checkbox-label">Sunday</label></td>
                    <td><input tabindex='10' class='byday-weekday' type='checkbox' value='MON' name='byday_mon' id='byday_mon'/><label for="byday_mon" class="checkbox-label">Monday</label></td>
                    <td><input tabindex='10' class='byday-weekday' type='checkbox' value='TUE' name='byday_tue' id='byday_tue'/><label for="byday_tue" class="checkbox-label">Tuesday</label></td>
                    <td><input tabindex='10' class='byday-weekday' type='checkbox' value='WED' name='byday_wed' id='byday_wed'/><label for="byday_wed" class="checkbox-label">Wednesday</label></td>
                    <td><input tabindex='10' class='byday-weekday' type='checkbox' value='THU' name='byday_thu' id='byday_thu'/><label for="byday_thu" class="checkbox-label">Thursday</label></td>
                    <td><input tabindex='10' class='byday-weekday' type='checkbox' value='FRI' name='byday_fri' id='byday_fri'/><label for="byday_fri" class="checkbox-label">Friday</label></td>
                    <td><input tabindex='10' class='byday-weekday' type='checkbox' value='SAT' name='byday_sat' id='byday_sat'/><label for="byday_sat" class="checkbox-label">Saturday</label></td>
                </tr>
            </table>
            <table id="byday_monthly_table">
                <tr class="byday-monthly-row">
                    <td><select tabindex='10' class='ordinal-day' id='ordinal_day_1' name='ordinal_day_1'>
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
                    <td><select tabindex='10' class='weekday-select' id='weekday_1' name='weekday_1'>
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
                    <td><select tabindex='10' class='ordinal-day' id='ordinal_day_2' name='ordinal_day_2'>
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
                    <td><select tabindex='10' class='weekday-select' id='weekday_2' name='weekday_2'>
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
                    <td><select tabindex='10' class='ordinal-day' id='ordinal_day_3' name='ordinal_day_3'>
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
                    <td><select tabindex='10' class='weekday-select' id='weekday_3' name='weekday_3'>
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
                    <td><select tabindex='10' class='ordinal-day' id='ordinal_day_4' name='ordinal_day_4'>
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
                    <td><select tabindex='10' class='weekday-select' id='weekday_4' name='weekday_4'>
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
                    <td><select tabindex='10' class='ordinal-day' id='ordinal_day_5' name='ordinal_day_5'>
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
                    <td><select tabindex='10' class='weekday-select' id='weekday_5' name='weekday_5'>
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
            <input tabindex='10' type="text" size="6" class="validate[custom[integer],min[1]]" id="count" name="count"/>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line" id="_until_row">
            <label for="until">End Date</label>
            <input tabindex='10' type="text" size="12" maxlength="12" class="validate[custom[date]] datepicker" id="until" name="until"/>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line" id="_start_time_row">
            <label for="start_time">Start Time</label>
            <select tabindex='10' id="start_time" name="start_time">
                <option value="">[All Day]</option>
				<?php
				for ($x = 0; $x < 24; $x += .25) {
					$workingHour = floor($x);
					$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
					$displayMinutes = ($x - $workingHour) * 60;
					$displayAmpm = ($x == 0 ? "midnight" : ($x == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
					?>
                    <option value="<?= $x ?>"><?= $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm ?></option>
				<?php } ?>
            </select>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line" id="_end_time_row">
            <label for="end_time">End Time</label>
            <select tabindex='10' id="end_time" name="end_time">
                <option value="">[All Day]</option>
				<?php
				for ($x = 0; $x < 24; $x += .25) {
					$workingHour = floor($x + .25);
					$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
					$displayMinutes = ($x + .25 - $workingHour) * 60;
					$displayAmpm = (($x + .25) == 24 ? "midnight" : (($x + .24) == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
					?>
                    <option value="<?= $x ?>"><?= $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm ?></option>
				<?php } ?>
            </select>
            <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
        </div>

        <div class="basic-form-line" id="_create_recurring_schedule_row">
            <button tabindex='10' id="create_recurring_schedule">Create</button>
        </div>

        <input type="hidden" name="recurring_list" id="recurring_list"/>
        <table id="readable_recurring_list" class="grid-table">
        </table>
		<?php
	}

	function filterTextProcessing($filterText) {
		if (!empty($filterText)) {
			$parts = explode(" ", $filterText);
			if (count($parts) == 2) {
				$whereStatement = "(first_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[0] . "%") .
					" and last_name like " . $GLOBALS['gPrimaryDatabase']->makeParameter($parts[1] . "%") . ")";
				foreach ($this->iSearchFields as $fieldName) {
					$whereStatement .= " or " . $fieldName . " like " . $GLOBALS['gPrimaryDatabase']->makeParameter("%" . $filterText . "%");
				}
				$this->iDataSource->addFilterWhere($whereStatement);
			} else {
				$this->iDataSource->setFilterText($filterText);
			}
		}
	}

	function hiddenElements() {
		?>
        <div id="_confirm_delete_event_dialog" class="dialog-box">
            Do you want to delete just this occurrence or this and all future occurrences?
        </div>
		<?php
	}

	function checkContactData($nameValues) {
		if (empty($nameValues['contact_id']) && empty($nameValues['first_name']) && empty($nameValues['last_name']) &&
			empty($nameValues['email_address'])) {
			return false;
		}
		return true;
	}
}

$pageObject = new EventMaintenancePage("events");
$pageObject->displayPage();
