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

/* text instructions
<p>Several page text chunks can be used on the donation page. The code is important and must match the code listed here. The description is just informational and can contain anything. The value is what is used by the page.</p>
<ul>
    <li><strong>time_increment</strong> - Time increments to use in the start and end time dropdowns. The default is every 15 minutes. The text chunk value can be 'half' or 'hour' to change that.</li>
    <li><strong>days_ahead_user_group_XXXX</strong>, <strong>days_ahead_user_type_YYYY</strong>, and <strong>days_ahead</strong> - These three text chunks work together. Typically, there are no restrictions on making reservations. A user could reserve a facility a year in advance, if wanted. These options put a restriction on this. XXXX represents the user group code and YYYY represents the user type code. User group takes precedence, then user type, then simply the days ahead. The value must be a numeric value representing the limit of the number of days before that the facility can be reserved.</li>
    <li><strong>business_name_required</strong> - If set to a non-empty value, the business name is required.</li>
    <li><strong>phone_number_required</strong> - If set to a non-empty value, the phone number is required.</li>
    <li><strong>start_hour</strong> - The first hour that appears in the start and end time dropdowns.</li>
    <li><strong>end_hour</strong> - The last hour that appears in the start and end time dropdowns.</li>
</ul>
*/

$GLOBALS['gPageCode'] = "RESERVEFACILITY";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gForceSSL'] = true;
require_once "shared/startup.inc";

class ThisPage extends Page {

	var $iRecurringAllowed = false;

	function setup() {
		$this->iRecurringAllowed = pageFunctionExists("RECURRING");
		if (!empty($_GET['start_date'])) {
			$_GET['date_needed'] = date("m/d/Y", strtotime($_GET['start_date']));
			$minutes = date("i", strtotime($_GET['start_date']));
			$hour = date("G", strtotime($_GET['start_date']));
			if (empty($_GET['facility_id'])) {
				$reservationStart = "";
			} else {
				$reservationStart = getFieldFromId("reservation_start", "facilities", "facility_id", $_GET['facility_id']);
			}
			if (strlen($reservationStart) > 0) {
				if ($reservationStart > $minutes) {
					$minutes = $reservationStart;
					$hour--;
				} else if ($reservationStart < $minutes) {
					$minutes = $reservationStart;
				}
			}
			$minutePart = floor($minutes * 4 / 60) / 4;
			$_GET['reserve_start_time'] = $hour + $minutePart;
			$_GET['reserve_end_time'] = $_GET['reserve_start_time'] + .75;
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "check_availability":
				if (!$this->iRecurringAllowed) {
					$_POST['recurring'] = "";
				}
				if (empty($_POST['recurring'])) {
					$parameters = array();
					if (!empty($_POST['facility_id'])) {
						$parameters[] = $_POST['facility_id'];
					}
					if (!empty($_POST['facility_type_id'])) {
						$parameters[] = $_POST['facility_type_id'];
					}
					$parameters[] = $_POST['number_people'];
					$parameters[] = $GLOBALS['gClientId'];
					$query = "select count(*) from facilities where " . (empty($_POST['facility_id']) ? "" : "facility_id = ? and ") . (empty($_POST['facility_type_id']) ? "" : "facility_type_id = ? and ") .
						"maximum_capacity >= ? and client_id = ?";
					$resultSet = executeQuery($query, $parameters);
					if ($row = getNextRow($resultSet)) {
						if ($row['count(*)'] == 0) {
							$roomDescription = getFieldFromId("description", "facilities", "facility_id", $_POST['facility_id']);
							$returnArray['availability'] = (empty($roomDescription) ? "No facility can" : $roomDescription . " cannnot") . " handle this number of people.";
							$returnArray['class_name'] = "error-message";
							ajaxResponse($returnArray);
							break;
						}
					}
					$foundFacilityIds = Events::getAvailableFacilities($_POST['facility_type_id'], $_POST['facility_id'], $_POST['date_needed'], $_POST['reserve_start_time'], $_POST['reserve_end_time'], $_POST['number_people']);
					if (!is_array($foundFacilityIds)) {
						$returnArray['availability'] = $foundFacilityIds;
						$returnArray['class_name'] = "error-message";
						ajaxResponse($returnArray);
						break;
					}
				} else {
					$repeatRules = Events::makeRepeatRules($_POST);
					$facilityIds = array();
					if (!empty($_POST['facility_id'])) {
						$facilityIds[] = $_POST['facility_id'];
					}
					$resultSet = executeQuery("select * from facilities where facility_type_id = ?", $_POST['facility_type_id']);
					while ($row = getNextRow($resultSet)) {
						if (!in_array($row['facility_id'], $facilityIds)) {
							$facilityIds[] = $row['facility_id'];
						}
					}
					$foundFacilityIds = array();
					foreach ($facilityIds as $facilityId) {
						$existingEventInfo = Events::getEventForRecurringTime($repeatRules, $_POST['reserve_start_time'], $_POST['reserve_end_time'], $facilityId);
						if ($existingEventInfo === false) {
							$foundFacilityIds[] = $facilityId;
						}
					}
				}

				$foundMessage = "";
				$lastValue = end($foundFacilityIds);
				foreach ($foundFacilityIds as $facilityId) {
					if (!empty($foundMessage)) {
						$foundMessage .= ($facilityId == $lastValue ? " and " : ", ");
					}
					$foundMessage .= getFieldFromId("description", "facilities", "facility_id", $facilityId);
				}
				if (empty($foundMessage)) {
					$roomDescription = getFieldFromId("description", "facilities", "facility_id", $_POST['facility_id']);
					$foundMessage = (empty($roomDescription) ? "No facilities are" : $roomDescription . " is not") . " available for this date and time and number of people.";
					$className = "error-message";
				} else {
					$foundMessage .= (count($foundFacilityIds) == 1 ? " is " : " are all ") . "available for this date and time.";
					$className = "success-message";
				}
				$returnArray['availability'] = $foundMessage;
				$returnArray['class_name'] = $className;
				ajaxResponse($returnArray);
				break;
			case "calculate_price":
				$startDate = ($_POST['date_needed']);
				if (!empty($_POST['recurring']) && !empty($_POST['recurring_start_date'])) {
					$startDate = $_POST['recurring_start_date'];
				}
				$endDate = $_POST['date_needed'];
				if (!empty($_POST['recurring'])) {
					$endDate = $_POST['until'];
				}
				if (empty($_POST['recurring'])) {
					$parameters = array();
					if (!empty($_POST['facility_id'])) {
						$parameters[] = $_POST['facility_id'];
					}
					if (!empty($_POST['facility_type_id'])) {
						$parameters[] = $_POST['facility_type_id'];
					}
					$parameters[] = $_POST['number_people'];
					$parameters[] = $GLOBALS['gClientId'];
					$query = "select count(*) from facilities where " . (empty($_POST['facility_id']) ? "" : "facility_id = ? and ") . (empty($_POST['facility_type_id']) ? "" : "facility_type_id = ? and ") .
						"maximum_capacity >= ? and client_id = ?";
					$resultSet = executeQuery($query, $parameters);
					if ($row = getNextRow($resultSet)) {
						if ($row['count(*)'] == 0) {
							$roomDescription = getFieldFromId("description", "facilities", "facility_id", $_POST['facility_id']);
							$returnArray['availability'] = (empty($roomDescription) ? "No facility can" : $roomDescription . " cannnot") . " handle this number of people.";
							$returnArray['class_name'] = "error-message";
							ajaxResponse($returnArray);
							break;
						}
					}

					$foundFacilityIds = Events::getAvailableFacilities($_POST['facility_type_id'], $_POST['facility_id'], $_POST['date_needed'], $_POST['reserve_start_time'], $_POST['reserve_end_time'], $_POST['number_people']);
					if (!is_array($foundFacilityIds)) {
						$returnArray['availability'] = $foundFacilityIds;
						$returnArray['class_name'] = "error-message";
						ajaxResponse($returnArray);
						break;
					}
				} else {
					$repeatRules = Events::makeRepeatRules($_POST);
					$facilityIds = array();
					if (!empty($_POST['facility_id'])) {
						$facilityIds[] = $_POST['facility_id'];
					}
					$resultSet = executeQuery("select * from facilities where facility_type_id = ?", $_POST['facility_type_id']);
					while ($row = getNextRow($resultSet)) {
						if (!in_array($row['facility_id'], $facilityIds)) {
							$facilityIds[] = $row['facility_id'];
						}
					}
					$foundFacilityIds = array();
					foreach ($facilityIds as $facilityId) {
						$existingEventInfo = Events::getEventForRecurringTime($repeatRules, $_POST['reserve_start_time'], $_POST['reserve_end_time'], $facilityId);
						if ($existingEventInfo === false) {
							$foundFacilityIds[] = $facilityId;
						}
					}
				}
				if (!is_array($foundFacilityIds) || empty($foundFacilityIds)) {
					$returnArray['error_message'] = "No facilities are available for this date and time and number of people.";
					ajaxResponse($returnArray);
					break;
				}

				$facilityId = $foundFacilityIds[array_rand($foundFacilityIds)];
				$resultSet = executeQuery("select * from facilities where facility_id = ? and client_id = ?", $facilityId, $GLOBALS['gClientId']);
				$contactId = "";
				if (!$facilityRow = getNextRow($resultSet)) {
					$returnArray['error_message'] = getSystemMessage("basic");
					ajaxResponse($returnArray);
					break;
				}
				if (strlen($_POST['reserve_start_time']) == 0) {
					$_POST['reserve_start_time'] = 0;
				}
				if (strlen($_POST['reserve_end_time']) == 0) {
					$_POST['reserve_end_time'] = 23.75;
				}
				$eventTypeId = getFieldFromId("event_type_id", "event_types", "event_type_id", $_POST['event_type_id']);
				$productId = getFieldFromId("product_id", "event_types", "event_type_id", $eventTypeId, "inactive = 0");
				$timeIncrement = $this->getPageTextChunk("time_increment");
				if ($timeIncrement == "hour") {
					$timeIncrement = 1;
				} else if ($timeIncrement == "half") {
					$timeIncrement = .5;
				} else {
					$timeIncrement = .25;
				}
				$hours = ($_POST['reserve_end_time'] - $_POST['reserve_start_time'] + $timeIncrement);
				if (!empty($productId)) {
					if (!empty($GLOBALS['gUserRow']['user_type_id'])) {
						$salePrice = getFieldFromId("price", "product_prices", "product_id", $productId,
							"product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'SALE_PRICE') and " .
							"user_type_id = ? and location_id is null and (start_date is null or start_date <= current_date) and " .
							"(end_date is null or end_date >= current_date)", $GLOBALS['gUserRow']['user_type_id']);
						if (strlen($salePrice) > 0) {
							$facilityRow['cost_per_hour'] = $salePrice;
						}
					}
				}

				$dateNeededWeekday = date("w", strtotime($startDate));
				$facilityPrices = array();
				$resultSet = executeQuery("select * from facility_prices where facility_id = ?", $facilityRow['facility_id']);
				while ($row = getNextRow($resultSet)) {
					if (!empty($row['weekday']) && $row['weekday'] != $dateNeededWeekday) {
						continue;
					}
					if (!empty($row['user_type_id']) && $row['user_type_id'] != $GLOBALS['gUserRow']['user_type_id']) {
						continue;
					}
					if ($hours < $row['minimum_amount'] || $hours > $row['maximum_amount']) {
						continue;
					}
					$facilityPrices[] = $row;
				}
				$numberPeople = $_POST['number_people'];
				$personCosts = array();
				$costMessage = "";
				for ($x = 1; $x <= $numberPeople; $x++) {
					$personCosts[$x] = $facilityRow['cost_per_hour'];
					foreach ($facilityPrices as $row) {
						if ($x < $row['minimum_quantity'] || $x > $row['maximum_quantity']) {
							continue;
						}
						$personCosts[$x] = $row['cost_per_hour'];
					}
				}
				$hourlyCosts = 0;
				foreach ($personCosts as $personNumber => $costPerHour) {
					$hourlyCosts += $costPerHour;
					$costMessage .= "<p>" . ($numberPeople > 1 ? ordinal($personNumber) . " person: " : "") . "$" . number_format($costPerHour, 2, ".", "") . " per hour</p>";
				}
				$totalCost = $hourlyCosts * $hours;
				$locationContactRow = $this->getContactRow($facilityRow['location_id']);
				$itemArray = array(array("shopping_cart_item_id" => 1, "product_id" => $productId, "quantity" => 1, "sale_price" => $totalCost));
				$locationContactRow['shopping_cart_items'] = $itemArray;
				$locationContactRow['from_country_id'] = $locationContactRow['country_id'];
				$locationContactRow['from_address_1'] = $locationContactRow['address_1'];
				$locationContactRow['from_city'] = $locationContactRow['city'];
				$locationContactRow['from_state'] = $locationContactRow['state'];
				$locationContactRow['from_postal_code'] = $locationContactRow['postal_code'];
				$tax = ShoppingCart::estimateTax($locationContactRow);
				$costMessage .= "<p>Total Cost per Hour: $" . number_format($hourlyCosts, 2, ".", "") . "</p>";
				if (!empty(floatval($facilityRow['cost_per_day'])) && $totalCost > $facilityRow['cost_per_day']) {
					$costMessage .= "<p>Cost per day: $" . number_format($facilityRow['cost_per_day'], 2, ".", "") . "</p>";
					$totalCost = $facilityRow['cost_per_day'];
				}
				$costMessage .= ($tax > 0 ? "<p>Estimated Tax: $" . number_format($tax, 2, ".", "") . "</p>" : "");
				$costMessage .= "<p>Total Cost: $" . number_format($totalCost + $tax, 2, ".", "") . "</p>";

				$returnArray['cost_message'] = $costMessage;
				$returnArray['cost'] = $totalCost;
				ajaxResponse($returnArray);
				break;
			case "make_reservation":
				if (!$this->iRecurringAllowed) {
					$_POST['recurring'] = "";
				}
				if (function_exists("_localCheckValidReservation")) {
					$returnValue = _localCheckValidReservation($_POST);
					if ($returnValue !== true) {
						if ($returnValue) {
							$returnArray['error_message'] = $returnValue;
						} else {
							$returnArray['error_message'] = "Unable to make reservation";
						}
						ajaxResponse($returnArray);
						break;
					}
				}
				$startDate = ($_POST['date_needed']);
				if (!empty($_POST['recurring']) && !empty($_POST['recurring_start_date'])) {
					$startDate = $_POST['recurring_start_date'];
				}
				$endDate = $_POST['date_needed'];
				if (!empty($_POST['recurring'])) {
					$endDate = $_POST['until'];
				}
				if (empty($_POST['recurring'])) {
					$foundFacilityIds = Events::getAvailableFacilities($_POST['facility_type_id'], $_POST['facility_id'], $_POST['date_needed'], $_POST['reserve_start_time'], $_POST['reserve_end_time'], $_POST['number_people']);
					if (!is_array($foundFacilityIds)) {
						$returnArray['availability'] = $foundFacilityIds;
						$returnArray['class_name'] = "error-message";
						ajaxResponse($returnArray);
						break;
					}
				} else {
					$repeatRules = Events::makeRepeatRules($_POST);
					$facilityIds = array();
					if (!empty($_POST['facility_id'])) {
						$facilityIds[] = $_POST['facility_id'];
					}
					$resultSet = executeQuery("select * from facilities where facility_type_id = ?", $_POST['facility_type_id']);
					while ($row = getNextRow($resultSet)) {
						if (!in_array($row['facility_id'], $facilityIds)) {
							$facilityIds[] = $row['facility_id'];
						}
					}
					$foundFacilityIds = array();
					foreach ($facilityIds as $facilityId) {
						$existingEventInfo = Events::getEventForRecurringTime($repeatRules, $_POST['reserve_start_time'], $_POST['reserve_end_time'], $facilityId);
						if ($existingEventInfo === false) {
							$foundFacilityIds[] = $facilityId;
						}
					}
				}
				if (!is_array($foundFacilityIds) || empty($foundFacilityIds)) {
					$returnArray['error_message'] = "No facilities are available for this date and time and number of people.";
					ajaxResponse($returnArray);
					break;
				}

				$facilityId = $foundFacilityIds[array_rand($foundFacilityIds)];
				$resultSet = executeQuery("select * from facilities where facility_id = ? and client_id = ?", $facilityId, $GLOBALS['gClientId']);
				$contactId = "";
				if (!$facilityRow = getNextRow($resultSet)) {
					$returnArray['error_message'] = getSystemMessage("basic");
					ajaxResponse($returnArray);
					break;
				}
				if (strlen($_POST['reserve_start_time']) == 0) {
					$_POST['reserve_start_time'] = 0;
				}
				if (strlen($_POST['reserve_end_time']) == 0) {
					$_POST['reserve_end_time'] = 23.75;
				}
				$eventTypeId = getFieldFromId("event_type_id", "event_types", "event_type_id", $_POST['event_type_id']);
				$productId = getFieldFromId("product_id", "event_types", "event_type_id", $eventTypeId, "inactive = 0");
				$timeIncrement = $this->getPageTextChunk("time_increment");
				if ($timeIncrement == "hour") {
					$timeIncrement = 1;
				} else if ($timeIncrement == "half") {
					$timeIncrement = .5;
				} else {
					$timeIncrement = .25;
				}
				$hours = ($_POST['reserve_end_time'] - $_POST['reserve_start_time'] + $timeIncrement);
				if (!empty($productId)) {
					if (!empty($GLOBALS['gUserRow']['user_type_id'])) {
						$salePrice = getFieldFromId("price", "product_prices", "product_id", $productId,
							"product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'SALE_PRICE') and " .
							"user_type_id = ? and location_id is null and (start_date is null or start_date <= current_date) and " .
							"(end_date is null or end_date >= current_date)", $GLOBALS['gUserRow']['user_type_id']);
						if (strlen($salePrice) > 0) {
							$facilityRow['cost_per_hour'] = $salePrice;
						}
					}
				}

				$dateNeededWeekday = date("w", strtotime($startDate));
				$facilityPrices = array();
				$resultSet = executeQuery("select * from facility_prices where facility_id = ?", $facilityRow['facility_id']);
				while ($row = getNextRow($resultSet)) {
					if (!empty($row['weekday']) && $row['weekday'] != $dateNeededWeekday) {
						continue;
					}
					if (!empty($row['user_type_id']) && $row['user_type_id'] != $GLOBALS['gUserRow']['user_type_id']) {
						continue;
					}
					if ($hours < $row['minimum_amount'] || $hours > $row['maximum_amount']) {
						continue;
					}
					$facilityPrices[] = $row;
				}
				$personCosts = array();
				for ($x = 1; $x <= $_POST['number_people']; $x++) {
					$personCosts[$x] = $facilityRow['cost_per_hour'];
					foreach ($facilityPrices as $row) {
						if ($x < $row['minimum_quantity'] || $x > $row['maximum_quantity']) {
							continue;
						}
						$personCosts[$x] = $row['cost_per_hour'];
					}
				}
				$hourlyCosts = 0;
				foreach ($personCosts as $costPerHour) {
					$hourlyCosts += ($costPerHour * $hours);
				}

				$cost = empty(floatval($facilityRow['cost_per_day'])) ? $hourlyCosts : min($facilityRow['cost_per_day'], $hourlyCosts);

				$this->iDatabase->startTransaction();
				if ($GLOBALS['gLoggedIn']) {
					$contactId = $GLOBALS['gUserRow']['contact_id'];
					$_POST['first_name'] = getFieldFromId("first_name", "contacts", "contact_id", $contactId);
					$_POST['last_name'] = getFieldFromId("last_name", "contacts", "contact_id", $contactId);
					$_POST['email_address'] = getFieldFromId("email_address", "contacts", "contact_id", $contactId);
				} else {
					$resultSet = executeQuery("select contact_id from contacts where client_id = ? and first_name = ? and last_name = ? and email_address = ? and " .
						"contact_id not in (select contact_id from users) and contact_id not in (select contact_id from orders) and contact_id not in (select contact_id from donations)",
						$GLOBALS['gClientId'], $_POST['first_name'], $_POST['last_name'], $_POST['email_address']);
					if ($row = getNextRow($resultSet)) {
						$contactId = $row['contact_id'];
					} else {
						if (empty($_POST['source_id'])) {
							$_POST['source_id'] = getSourceFromReferer($_SERVER['HTTP_REFERER']);
						}
						$contactDataTable = new DataTable("contacts");
						if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'],
							"business_name" => $_POST['business_name'], "email_address" => $_POST['email_address'], "source_id" => $_POST['source_id'])))) {
							$returnArray['error_message'] = $contactDataTable->getErrorMessage();
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
					if (!empty($_POST['phone_number'])) {
						$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "phone_number", $_POST['phone_number']);
						if (empty($phoneNumberId)) {
							executeQuery("insert into phone_numbers (contact_id,phone_number) values (?,?)", $contactId, $_POST['phone_number']);
						}
					}
				}
				$contactRow = Contact::getContact($contactId);
				makeWebUserContact($contactId);
				$facilityTypeId = getFieldFromId("facility_type_id", "facilities", "facility_id", $facilityId);
				$resultSet = executeQuery("insert into events (client_id,description,detailed_description,event_type_id,contact_id,start_date,end_date,cost,user_id,date_created,attendees,tentative) values " .
					"(?,?,?,?,?,?,?,?,?,now(),?,?)", $GLOBALS['gClientId'], $_POST['description'], $_POST['detailed_description'], $eventTypeId, $contactId,
					$this->iDatabase->makeDateParameter($startDate), $this->iDatabase->makeDateParameter($endDate), $cost, $GLOBALS['gUserId'], $_POST['number_people'], $facilityRow['requires_approval']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				} else {
					$eventId = $resultSet['insert_id'];
				}
				for ($x = $_POST['reserve_start_time']; $x <= $_POST['reserve_end_time'] + $timeIncrement - .25; $x += .25) {
					if (empty($_POST['recurring'])) {
						$resultSet = executeQuery("insert into event_facilities (event_id,facility_id,date_needed,hour) values " .
							"(?,?,?,?)", $eventId, $facilityId, $this->iDatabase->makeDateParameter($_POST['date_needed']), $x);
					} else {
						$resultSet = executeQuery("insert into event_facility_recurrences (event_id,facility_id,repeat_rules,hour) values (?,?,?,?)",
							$eventId, $facilityId, $repeatRules, $x);
					}
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}

				if (!empty($productId) && $cost > 0) {
					$productRow = ProductCatalog::getCachedProductRow($productId);
					if ($cost != $_POST['payment_amount']) {
						$returnArray['error_message'] = "Some costs have changed. Please reload the page and try again." . ($GLOBALS['gUserRow']['superuser_flag'] ? " " . $cost . ":" . $_POST['payment_amount'] : "");
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}

					$orderObject = new Order();
					$orderObject->setOrderField("contact_id", $contactId);
					$orderObject->setOrderField("user_id", $GLOBALS['gUserId']);
					$description = $hours . " hours in " . getFieldFromId("description", "facilities", "facility_id", $facilityId);
					$thisItem = array("product_id" => $productId, "description" => $description, "sale_price" => $cost, "quantity" => 1);
					$orderObject->addOrderItem($thisItem);
					$orderObject->setOrderField("full_name", getDisplayName($contactId));
					$orderObject->setOrderField("payment_method_id", $_POST['payment_method_id']);
					$orderObject->setOrderField("date_completed", date("Y-m-d"));
					$accountAddressId = getFieldFromId("address_id", "addresses", "contact_id", $contactId, "address_1 <=> ? and address_2 <=> ? and city <=> ? and state <=> ? and postal_code <=> ? and country_id = ?",
						$_POST['billing_address_1'], $_POST['billing_address_2'], $_POST['billing_city'], $_POST['billing_state'], $_POST['billing_postal_code'], $_POST['billing_country_id']);
					if (empty($accountAddressId)) {
						$insertSet = executeQuery("insert into addresses (contact_id,address_label,address_1,address_2,city,state,postal_code,country_id,version) values (?,?,?,?,?, ?,?,?,500)",
							$contactId, "Billing Address", $_POST['billing_address_1'], $_POST['billing_address_2'], $_POST['billing_city'],
							$_POST['billing_state'], $_POST['billing_postal_code'], $_POST['billing_country_id']);
						if (!empty($insertSet['sql_error'])) {
							$this->iDatabase->rollbackTransaction();
							$returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
							ajaxResponse($returnArray);
							break;
						}
						$accountAddressId = $insertSet['insert_id'];
					}
					$orderObject->setOrderField("address_id", $accountAddressId);
					$locationContactRow = $this->getContactRow($facilityRow['facility_id']);
					$taxCharge = $orderObject->getTax($locationContactRow['contact_id']);
					if (empty($taxCharge)) {
						$taxCharge = 0;
					}
					$orderObject->setOrderField("tax_charge", $taxCharge);
					$itemCost = $cost;
					$cost += $taxCharge;
					if (!$orderObject->generateOrder()) {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = $orderObject->getErrorMessage();
						ajaxResponse($returnArray);
						break;
					}

					$orderId = $orderObject->getOrderId();
					executeQuery("update events set order_id = ? where event_id = ?", $orderId, $eventId);

					if (empty($_POST['account_id'])) {
						$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types",
							"payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id",
								$_POST['payment_method_id']));
						$isBankAccount = ($paymentMethodTypeCode == "BANK_ACCOUNT");

						if ($paymentMethodTypeCode == "GIFT_CARD") {
							$giftCard = new GiftCard(array("user_id" => $GLOBALS['gUserId'], "gift_card_number" => $_POST['gift_card_number']));
							if (!$giftCard->isValid()) {
								$this->iDatabase->rollbackTransaction();
								$returnArray['error_message'] = "Gift Card doesn't exist";
								ajaxResponse($returnArray);
								break;
							}
                            $balance = $giftCard->getBalance();
							if ($balance < $cost) {
								$this->iDatabase->rollbackTransaction();
								$returnArray['error_message'] = "Not enough on the gift card to complete this transaction";
								ajaxResponse($returnArray);
								break;
							}
                            if (!$giftCard->adjustBalance(false,($cost * -1),"Usage for order for facility reservation")) {
								$this->iDatabase->rollbackTransaction();
								$returnArray['error_message'] = "Unable to use gift card to pay for reservation";
								ajaxResponse($returnArray);
								break;
							}
						} else {
							$merchantAccountId = $GLOBALS['gMerchantAccountId'];
							$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
							$achMerchantAccount = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "ACH", "inactive = 0");
							if (!empty($achMerchantAccount)) {
								$achECommerce = eCommerce::getEcommerceInstance($achMerchantAccount);
							}
							$useECommerce = ($achMerchantAccount && $isBankAccount ? $achECommerce : $eCommerce);

							if (!$useECommerce) {
								$this->iDatabase->rollbackTransaction();
								$returnArray['error_message'] = "Unable to connect to Merchant Services. Please contact customer service.";
								ajaxResponse($returnArray);
								break;
							}
							$paymentArray = array("amount" => $cost, "order_number" => $orderId, "description" => $description,
								"first_name" => $contactRow['first_name'], "last_name" => $contactRow['last_name'],
								"business_name" => $contactRow['business_name'], "address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
								"postal_code" => $_POST['billing_postal_code'], "country_id" => $_POST['billing_country_id'],
								"email_address" => $contactRow['email_address'], "contact_id" => $contactId);
							if ($isBankAccount) {
								$paymentArray['bank_routing_number'] = $_POST['routing_number'];
								$accountNumber = $paymentArray['bank_account_number'] = $_POST['bank_account_number'];
								$paymentArray['bank_account_type'] = strtolower(str_replace("_", "", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id'])));
							} else {
								$accountNumber = $paymentArray['card_number'] = $_POST['account_number'];
								$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
								$paymentArray['card_code'] = $_POST['cvv_code'];
							}
							$success = ($GLOBALS['gDevelopmentServer'] ? true : $useECommerce->authorizeCharge($paymentArray));
							$response = ($GLOBALS['gDevelopmentServer'] ? array("transaction_id" => "238559279234", "authorization_code" => "d92fwd") : $useECommerce->getResponse());
							if ($success) {
								$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']) . " - " . substr($accountNumber, -4);

								$insertSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name,address_id,account_number,expiration_date,merchant_account_id,inactive) values (?,?,?,?,?, ?,?,?,?)",
									$contactId, $accountLabel, $_POST['payment_method_id'], getDisplayName($contactId), $accountAddressId, "XXXX-" . substr($accountNumber, -4),
									(empty($_POST['expiration_year']) ? "" : date("Y-m-d", strtotime($_POST['expiration_month'] . "/01/" . $_POST['expiration_year']))),
									$useECommerce->getMerchantAccountId(), 1);
								$accountId = $insertSet['insert_id'];

								$orderObject->createOrderPayment($itemCost, array("payment_method_id" => $_POST['payment_method_id'], "tax_charge" => $taxCharge, "account_id" => $accountId,
									"authorization_code" => $response['authorization_code'], "transaction_identifier" => $response['transaction_id']));
							} else {
								$this->iDatabase->rollbackTransaction();
								$returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
								$useECommerce->writeLog(($isBankAccount ? $paymentArray['bank_account_number'] : $paymentArray['card_number']), $response['response_reason_text'], true);
								ajaxResponse($returnArray);
								break;
							}
						}
					} else {
						$accountRow = getRowFromId("accounts", "account_id", $_POST['account_id'], "contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
						if (empty($accountRow)) {
							$this->iDatabase->rollbackTransaction();
							$returnArray['error_message'] = "Unable to charge this existing payment account. Choose another or create a new one.";
							ajaxResponse($returnArray);
							break;
						}
						$accountId = $accountRow['account_id'];
						$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types",
							"payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id",
								$accountRow['payment_method_id']));
						$isBankAccount = ($paymentMethodTypeCode == "BANK_ACCOUNT");

						$merchantAccountId = $GLOBALS['gMerchantAccountId'];
						$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
						$achMerchantAccount = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "ACH", "inactive = 0");
						if (!empty($achMerchantAccount)) {
							$achECommerce = eCommerce::getEcommerceInstance($achMerchantAccount);
						}
						$useECommerce = ($achMerchantAccount && $isBankAccount ? $achECommerce : $eCommerce);

						if (!$useECommerce) {
							$this->iDatabase->rollbackTransaction();
							$returnArray['error_message'] = "Unable to connect to Merchant Services. Please contact customer service.";
							ajaxResponse($returnArray);
							break;
						}
						$accountMerchantIdentifier = getFieldFromId("merchant_identifier", "accounts", "account_id", $accountId);
						if (empty($accountMerchantIdentifier)) {
							$accountMerchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $contactId, "merchant_account_id = ?", $merchantAccountId);
						}
						$addressId = getFieldFromId("address_id", "accounts", "account_id", $accountId);
						$success = $eCommerce->createCustomerProfileTransactionRequest(array("amount" => $_POST['amount'], "order_number" => $orderId,
							"merchant_identifier" => $accountMerchantIdentifier, "account_token" => $accountRow['account_id'], "address_id" => $accountRow['address_id']));
						$response = $eCommerce->getResponse();
						if ($success) {
							$orderObject->createOrderPayment($itemCost, array("payment_method_id" => $accountRow['payment_method_id'], "tax_charge" => $taxCharge,
								"authorization_code" => $response['authorization_code'], "transaction_identifier" => $response['transaction_id']));
						} else {
							$this->iDatabase->rollbackTransaction();
							$returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
							$useECommerce->writeLog($accountRow['account_number'], $response['response_reason_text'], true);
							ajaxResponse($returnArray);
							break;
						}
					}
				}

				$substitutions = $_POST;
				$substitutions['cost'] = number_format($cost, 2, ".", ",");
				$substitutions['approval'] = ($facilityRow['requires_approval'] ? " though it will require approval" : "");

				if (empty($_POST['reserve_start_time']) && $_POST['reserve_end_time'] == 47) {
					$displayTime = "All Day";
				} else {
					$workingHour = floor($_POST['reserve_start_time']);
					$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
					$displayMinutes = ($_POST['reserve_start_time'] - $workingHour) * 60;
					$displayAmpm = ($_POST['reserve_start_time'] == 0 ? "midnight" : ($_POST['reserve_start_time'] == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
					$displayTime = $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm;
					$workingHour = floor($_POST['reserve_end_time'] + $timeIncrement);
					$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
					$displayMinutes = ($_POST['reserve_end_time'] + $timeIncrement - $workingHour) * 60;
					$displayAmpm = ($_POST['reserve_end_time'] + $timeIncrement == 24 ? "midnight" : ($_POST['reserve_end_time'] + $timeIncrement == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
					$displayTime .= "-" . $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm;
				}

				$substitutions['display_time'] = $displayTime;
				$substitutions['facility_description'] = getFieldFromId("description", "facilities", "facility_id", $facilityId);
				if (empty($substitutions['phone_number'])) {
					$substitutions['phone_number'] = "";
				}
				$substitutions['recurring'] = (empty($_POST['recurring']) ? "" : "This event is recurring");
				$emailId = getFieldFromId("email_id", "emails", "email_code", "RESERVATION_CONFIRMATION",  "inactive = 0");
				$subject = "Facility Reservation";
				$body = "<p>Your facility has been reserved%approval%. Here are the details:</p><p>Number of People: %number_people%<br>First Name: %first_name%<br />Last Name: %last_name%<br />" .
					"Email: %email_address%<br />Phone: %phone_number%<br />Event Description: %description%<br />" .
					"Facility: %facility_description%<br />Comments: %detailed_description%<br />" .
					(empty($cost) ? "" : "Cost: %cost%<br />") .
					"Date Reserved: %date_needed%<br />Time: %display_time%" . (empty($_POST['recurring']) ? "" : "<br />This event is recurring") . "</p>";
				$ccEmailAddresses = array();
				$resultSet = executeQuery("select * from facility_notifications where facility_id = ?", $facilityId);
				while ($row = getNextRow($resultSet)) {
					$ccEmailAddresses[] = $row['email_address'];
				}
				$resultSet = executeQuery("select * from facility_type_notifications where facility_type_id = ?", $facilityTypeId);
				while ($row = getNextRow($resultSet)) {
					$ccEmailAddresses[] = $row['email_address'];
				}
				sendEmail(array("email_id" => $emailId, "body" => $body, "subject" => $subject, "substitutions" => $substitutions, "email_addresses" => $_POST['email_address'], "contact_id" => $contactId, "bcc_addresses" => $ccEmailAddresses));

				Events::sendEventNotifications($eventId);

				addActivityLog("Reserved facility");
				$response = getFieldFromId("response_content", "facility_types", "facility_type_id", $facilityTypeId);
				if (empty($response)) {
					$response = $this->getFragment("reservation_success");
				}
				if (empty($response)) {
					$response = $body;
				}
				$response = PlaceHolders::massageContent($response, $substitutions);
				$returnArray['response'] = makeHtml($response);
				$this->iDatabase->commitTransaction();
				ajaxResponse($returnArray);
				break;
		}
	}

	private function getContactRow($facilityId) {
		$locationRow = getRowFromId("locations", "location_id", $facilityId);
		$locationContactRow = array();
		if (!empty($locationRow)) {
			$locationContactRow = Contact::getContact($locationRow['contact_id']);
		}
		if (!isset($locationContactRow['postal_code']) || empty($locationContactRow['postal_code'])) {
			$locationContactRow = Contact::getContact($GLOBALS['gClientRow']['contact_id']);
		}
		return $locationContactRow;
	}

	function mainContent() {
		echo $this->getPageData("content");
		$facilityTypeId = getFieldFromId("facility_type_id", "facility_types", "facility_type_code", strtoupper($_GET['facility_type']), "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
		$eventTypeId = getFieldFromId("event_type_id", "event_types", "event_type_code", $_GET['event_type']);
		if (empty($eventTypeId)) {
			$eventTypeId = getFieldFromId("event_type_id", "facility_types", "facility_type_id", $facilityTypeId);
		}
		$productId = getFieldFromId("product_id", "event_types", "event_type_id", $eventTypeId);
		$typeReservable = getFieldFromId("reservable", "facility_types", "facility_type_id", $facilityTypeId);
		$someHaveCost = false;
		if (!empty($facilityTypeId)) {
			$resultSet = executeQuery("select facility_id from facilities where cost_per_hour is not null and cost_per_hour > 0 and facility_type_id = ?", $facilityTypeId);
			if ($row = getNextRow($resultSet)) {
				$someHaveCost = true;
			}
		}
		$billable = (!empty($productId) && !empty($eventTypeId) && $someHaveCost);
		$daysAhead = 0;
		$resultSet = executeQuery("select * from user_groups where user_group_id in (select user_group_id from user_group_members where user_id = ?) order by sort_order", $GLOBALS['gUserId']);
		while ($row = getNextRow($resultSet)) {
			$daysAhead = $this->getPageTextChunk("DAYS_AHEAD_USER_GROUP_" . $row['user_group_code']);
			if (!empty($daysAhead)) {
				break;
			}
		}

		if (empty($daysAhead)) {
			$userTypeCode = getFieldFromId("user_type_code", "user_types", "user_type_id", $GLOBALS['gUserRow']['user_type_id']);
			if (!empty($userTypeCode)) {
				$daysAhead = $this->getPageTextChunk("DAYS_AHEAD_USER_TYPE_" . $userTypeCode);
			}
		}

		if (empty($daysAhead)) {
			$daysAhead = $this->getPageTextChunk("DAYS_AHEAD");
		}
		$maximumDate = "";
		$dateNeeded = (empty($_GET['date_needed']) ? "" : date("Y-m-d", strtotime($_GET['date_needed'])));
		if (!empty($daysAhead)) {
			$maximumDate = Date('Y-m-d', strtotime('+' . $daysAhead . ' days'));
			if (!empty($dateNeeded) && $dateNeeded > $maximumDate) {
				$dateNeeded = "";
			}
		}
		$facilityId = getFieldFromId("facility_id", "facilities", "facility_id", $_GET['facility_id'], "inactive = 0 and internal_use_only = 0");
		$businessNameRequired = (!empty($this->getPageTextChunk("business_name_required")));
		$phoneNumberRequired = (!empty($this->getPageTextChunk("phone_number_required")));
		$phoneNumber = Contact::getContactPhoneNumber($GLOBALS['gUserRow']['contact_id']);
		$maximumPeople = (!empty($_GET['maximum_people']) && is_numeric($_GET['maximum_people']) ? $_GET['maximum_people'] : "");
		?>
        <p id="_error_message" class="error-message"></p>
        <div id="_form_div">
            <form id="_edit_form" name="_edit_form">
                <input type="hidden" id="facility_type_id" name="facility_type_id" value="<?= $facilityTypeId ?>">

                <div class="form-line" id="_first_name_row">
                    <label for="first_name" class="required-label">First Name</label>
                    <input type="text" id="first_name" name="first_name" size="20" maxlength="25" <?= ($GLOBALS['gLoggedIn'] ? " disabled='disabled'" : "class='validate[required]'") ?> value="<?= $GLOBALS['gUserRow']['first_name'] ?>">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_last_name_row">
                    <label for="last_name" class="required-label">Last Name</label>
                    <input type="text" id="last_name" name="last_name" size="25" maxlength="35" <?= ($GLOBALS['gLoggedIn'] ? " disabled='disabled'" : "class=\"validate[required]\"") ?> value="<?= $GLOBALS['gUserRow']['last_name'] ?>">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_business_name_row">
                    <label for="business_name" class="<?= ($businessNameRequired ? "required-label" : "") ?>">Business Name</label>
                    <input type="text" id="business_name" name="business_name" size="60" maxlength="60" <?= ($GLOBALS['gLoggedIn'] ? " disabled='disabled'" : ($businessNameRequired ? "class=\"validate[required]\"" : "")) ?> value="<?= $GLOBALS['gUserRow']['business_name'] ?>">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_email_address_row">
                    <label for="email_address" class="required-label">Email</label>
                    <input type="text" id="email_address" name="email_address" size="30" maxlength="60" <?= ($GLOBALS['gLoggedIn'] ? " disabled='disabled'" : "class=\"validate[required,custom[email]]\"") ?> value="<?= $GLOBALS['gUserRow']['email_address'] ?>">
                    <div class='clear-div'></div>
                </div>

				<?php if (!$GLOBALS['gLoggedIn'] || !empty($phoneNumber)) { ?>
                    <div class="form-line" id="_phone_number_row">
                        <label for="phone_number" class="<?= ($phoneNumberRequired ? "required-label" : "") ?>">Phone</label>
                        <input type="text" id="phone_number" name="phone_number" size="25" maxlength="25" <?= ($GLOBALS['gLoggedIn'] ? " disabled='disabled'" : "class=\"validate[" . ($phoneNumberRequired ? "required," : "") . "custom[phone]]\"") ?> value="<?= $phoneNumber ?>">
                        <div class='clear-div'></div>
                    </div>
				<?php } ?>

                <div class="form-line" id="_number_people_row">
                    <label for="number_people" class='required-label'>Number of People</label>
                    <input type="text" id="number_people" name="number_people" size="6" class="align-right validate[required,custom[integer],min[1]<?= (empty($maximumPeople) ? "" : ",max[" . $maximumPeople . "]") ?>]" value="1">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_date_needed_row">
                    <label for="date_needed" class="required-label">Date Needed</label>
                    <input type="text" id="date_needed" name="date_needed" size="12" class="validate[required,custom[date],future<?= (empty($maximumDate) ? "" : ",max[" . date("Y-m-d", strtotime($maximumDate)) . "]") ?>] datepicker" value="<?= (empty($dateNeeded) ? "" : $dateNeeded) ?>">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_reserve_start_time_row">
                    <label for="reserve_start_time">Start Time</label>
                    <select id="reserve_start_time" name="reserve_start_time">
                        <option value="">[Select]</option>
						<?php
						if (empty($facilityId)) {
							$reservationStart = "";
						} else {
							$reservationStart = getFieldFromId("reservation_start", "facilities", "facility_id", $facilityId);
						}
						$startHour = $this->getPageTextChunk("start_hour");
						if (strpos($startHour, ":")) {
							$parts = explode(":", $startHour);
							$startHour = $parts[0] + (floor($parts[1] * 4) / 4);
						}
						$endHour = $this->getPageTextChunk("end_hour");
						if (strpos($endHour, ":")) {
							$parts = explode(":", $endHour);
							$endHour = $parts[0] + (floor($parts[1] * 4) / 4);
						}
						$timeIncrement = $this->getPageTextChunk("time_increment");
						if ($timeIncrement == "hour") {
							$timeIncrement = 1;
						} else if ($timeIncrement == "half") {
							$timeIncrement = .5;
						} else {
							$timeIncrement = .25;
						}
						for ($x = 0; $x < 24; $x += $timeIncrement) {
							if (!empty($startHour) && $x < $startHour) {
								continue;
							}
							if (!empty($endHour) && $x > $endHour) {
								continue;
							}
							$workingHour = floor($x);
							$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
							$displayMinutes = ($x - $workingHour) * 60;
							if (strlen($reservationStart) > 0 && $reservationStart != $displayMinutes) {
								continue;
							}
							$displayAmpm = ($x == 0 ? "midnight" : ($x == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
							$selected = ($_GET['reserve_start_time'] == $x ? "selected" : "");
							?>
                            <option <?= $selected ?> value="<?= $x ?>" data-hour="<?= $workingHour ?>" data-minute="<?= $displayMinutes ?>"><?= $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm ?></option>
						<?php } ?>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_reserve_end_time_row">
                    <label for="reserve_end_time">End Time</label>
                    <select id="reserve_end_time" name="reserve_end_time">
                        <option value="">[Select]</option>
						<?php
						if (empty($facilityId)) {
							$reservationStart = "";
						} else {
							$reservationStart = getFieldFromId("reservation_start", "facilities", "facility_id", $facilityId);
						}
						for ($x = 0; $x < 24; $x += $timeIncrement) {
							if (!empty($startHour) && $x < ($startHour - .25)) {
								continue;
							}
							if (!empty($endHour) && $x > ($endHour - .25)) {
								continue;
							}
							$workingHour = floor($x + $timeIncrement);
							$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
							$displayMinutes = ($x + $timeIncrement - $workingHour) * 60;
							if (strlen($reservationStart) > 0 && $reservationStart != $displayMinutes) {
								continue;
							}
							$displayAmpm = (($x + $timeIncrement) == 24 ? "midnight" : (($x + $timeIncrement) == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
							if (!empty($startHour) && $workingHour < $startHour) {
								continue;
							}
							$selected = ($_GET['reserve_end_time'] == $x ? "selected" : "");
							?>
                            <option <?= $selected ?> value="<?= $x ?>" data-hour="<?= $workingHour ?>" data-minute="<?= $displayMinutes ?>"><?= $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm ?></option>
						<?php } ?>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_facility_id_row">
                    <label for="facility_id" <?= ($typeReservable && !$billable ? "" : " class='required-label'") ?>>Facility</label>
                    <select <?= ($typeReservable && !$billable ? "" : "class='validate[required]'") ?> id="facility_id" name="facility_id">
                        <option value=""><?= ($typeReservable && !$billable ? "[Any Available]" : "[Select]") ?></option>
						<?php
						$resultSet = executeQuery("select * from facilities where client_id = ? and inactive = 0" .
							($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") .
							(empty($facilityTypeId) ? "" : " and facility_type_id = " . $facilityTypeId) .
							(empty($facilityId) ? "" : " and facility_id = " . $facilityId) .
							" order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							if (!empty($GLOBALS['gUserRow']['user_type_id']) && $billable) {
								$salePrice = getFieldFromId("price", "product_prices", "product_id", $productId,
									"product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'SALE_PRICE') and " .
									"user_type_id = ? and location_id is null and (start_date is null or start_date <= current_date) and " .
									"(end_date is null or end_date >= current_date)", $GLOBALS['gUserRow']['user_type_id']);
								if (strlen($salePrice) > 0) {
									$row['cost_per_hour'] = $salePrice;
								}
							}
							$selected = ($_GET['facility_id'] == $row['facility_id'] ? "selected" : "");
							?>
                            <option data-reservation_start="<?= $row['reservation_start'] ?>" <?= $selected ?> value="<?= $row['facility_id'] ?>" data-cost_per_hour="<?= $row['cost_per_hour'] ?>" data-cost_per_day="<?= $row['cost_per_day'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <p id="cost_message"></p>
                <p id="availability_message"></p>

				<?php
				if (empty($eventTypeId)) {
					?>
                    <div class="form-line" id="_event_type_id_row">
                        <label for="event_type_id" class='required-label'>Purpose</label>
                        <select class='validate[required]' id="event_type_id" name="event_type_id">
                            <option value="">[Select]</option>
							<?php
							$resultSet = executeQuery("select * from event_types where client_id = ? and inactive = 0" .
								($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								if (empty($eventTypeId) && $resultSet['row_count'] == 1) {
									$eventTypeId = $row['event_type_id'];
								}
								?>
                                <option value="<?= $row['event_type_id'] ?>" <?= ($eventTypeId == $row['event_type_id'] ? " selected" : "") ?>><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
                        </select>
                        <div class='clear-div'></div>
                    </div>
				<?php } else { ?>
                    <input type='hidden' id="event_type_id" name="event_type_id" value="<?= $eventTypeId ?>">
				<?php } ?>

				<?php
				$briefDescription = getFieldFromId("description", "event_types", "event_type_id", $eventTypeId);
				?>
                <div class="form-line" id="_description_row">
                    <label for="description" class="required-label">Brief Description of Use</label>
                    <input type="text" id="description" name="description" size="30" maxlength="255" class="validate[required]" value="<?= $briefDescription ?>">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_detailed_description_row">
                    <label for="detailed_description">Comments</label>
                    <textarea id="detailed_description" name="detailed_description"></textarea>
                    <div class='clear-div'></div>
                </div>

				<?php if ($this->iRecurringAllowed && !$billable) { ?>
                    <div class="form-line" id="_recurring_row">
                        <input class="recurring-event-field" type="checkbox" id="recurring" name="recurring" value="1"/><label class="checkbox-label" for="recurring">Recurring Event</label>
                        <div class='clear-div'></div>
                    </div>

                    <div id="recurring_schedule">
                        <h3>Recurring Schedule</h3>

                        <div class="form-line" id="_recurring_start_date_row">
                            <label for="recurring_start_date" class="required-label">Starting on or after</label>
                            <input class='recurring-event-field validate[custom[date],required] datepicker' type='text' value='' size='12' maxlength='12' name='recurring_start_date' id='recurring_start_date'/>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_frequency_row">
                            <label for="frequency">Frequency</label>
                            <select class="recurring-event-field" name='frequency' id='frequency'>
                                <option value="DAILY">Daily</option>
                                <option value="WEEKLY">Weekly</option>
                                <option value="MONTHLY">Monthly</option>
                                <option value="YEARLY">Yearly</option>
                            </select>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_interval_row">
                            <label for="interval">Interval</label>
                            <input class='recurring-event-field validate[custom[integer],min[1]] align-right' type='text' value='' size='4' maxlength='4' name='interval' id='interval'/>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_bymonth_row">
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
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_byday_row">
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
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_count_row">
                            <label for="count">Number of occurrences</label>
                            <input type="text" size="6" class="recurring-event-field validate[custom[integer],min[1]]" id="count" name="count"/>
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_until_row">
                            <label for="until">End Date</label>
                            <input type="text" size="12" maxlength="12" class="recurring-event-field validate[custom[date]] datepicker" id="until" name="until"/>
                            <div class='clear-div'></div>
                        </div>

                    </div>
				<?php } ?>

				<?php if ($billable) { ?>
                    <div id="payment_section" class="hidden">
                        <h2>Payment</h2>
                        <div class="form-line" id="_payment_amount_row">
                            <label for="payment_amount" class="required-label">Amount (Not including tax, if applicable)</label>
                            <input tabindex="10" type="text" size="12" class="align-right validate[custom[number],required]"
                                   data-decimal-places="2" readonly='readonly' id="payment_amount" name="payment_amount" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_account_id_row">
                            <label for="account_id" class="">Select Account</label>
                            <select tabindex="10" id="account_id" name="account_id">
                                <option value="">[New Account]</option>
								<?php
								$resultSet = executeQuery("select * from accounts where contact_id = ? and inactive = 0 and account_token is not null", $GLOBALS['gUserRow']['contact_id']);
								while ($row = getNextRow($resultSet)) {
									?>
                                    <option value="<?= $row['account_id'] ?>"><?= htmlText((empty($row['account_label']) ? $row['account_number'] : $row['account_label'])) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='clear-div'></div>
                        </div>

                        <div id="_new_account">
                            <div class="form-line" id="_payment_method_id_row">
                                <label for="payment_method_id" class="">Payment Method</label>
                                <select tabindex="10" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" id="payment_method_id" name="payment_method_id">
                                    <option value="">[Select]</option>
									<?php
									$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
										"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where " .
										($GLOBALS['gLoggedIn'] ? "" : "requires_user = 0 and ") .
										"(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
										(empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") . ") and " .
										"inactive = 0 and internal_use_only = 0 and client_id = ? and payment_method_type_id in " .
										"(select payment_method_type_id from payment_method_types where inactive = 0 and internal_use_only = 0 and " .
										"client_id = ? and payment_method_type_code in ('CREDIT_CARD','BANK_ACCOUNT','GIFT_CARD')) order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
									while ($row = getNextRow($resultSet)) {
										?>
                                        <option value="<?= $row['payment_method_id'] ?>" data-no_address_required="<?= $row['no_address_required'] ?>" data-payment_method_type_code="<?= strtolower($row['payment_method_type_code']) ?>"><?= htmlText($row['description']) ?></option>
										<?php
									}
									?>
                                </select>
                                <div class='clear-div'></div>
                            </div>

                            <div class="payment-method-fields" id="payment_method_credit_card">
                                <div class="form-line" id="_account_number_row">
                                    <label for="account_number" class="">Card Number</label>
                                    <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" size="20" maxlength="20" id="account_number" name="account_number" placeholder="Account Number" value="">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_expiration_month_row">
                                    <label for="expiration_month" class="">Expiration Date</label>
                                    <select tabindex="10" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" id="expiration_month" name="expiration_month">
                                        <option value="">[Month]</option>
										<?php
										for ($x = 1; $x <= 12; $x++) {
											?>
                                            <option value="<?= $x ?>"><?= $x . " - " . date("F", strtotime($x . "/01/2000")) ?></option>
											<?php
										}
										?>
                                    </select>
                                    <select tabindex="10" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" id="expiration_year" name="expiration_year">
                                        <option value="">[Year]</option>
										<?php
										for ($x = 0; $x < 12; $x++) {
											$year = date("Y") + $x;
											?>
                                            <option value="<?= $year ?>"><?= $year ?></option>
											<?php
										}
										?>
                                    </select>
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_cvv_code_row">
                                    <label for="cvv_code" class="">Security Code</label>
                                    <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" size="5" maxlength="4" id="cvv_code" name="cvv_code" placeholder="CVV Code" value="">
                                    <a href="https://www.cvvnumber.com/cvv.html" target="_blank"><img id="cvv_image" src="/images/cvv_code.gif" alt="CVV Code"></a>
                                    <div class='clear-div'></div>
                                </div>
                            </div> <!-- payment_method_credit_card -->

                            <div class="payment-method-fields" id="payment_method_bank_account">
                                <div class="form-line" id="_routing_number_row">
                                    <label for="routing_number" class="">Bank Routing Number</label>
                                    <input tabindex="10" type="text" class="validate[required,custom[routingNumber]]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" size="20" maxlength="20" id="routing_number" name="routing_number" placeholder="Routing Number" value="">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_bank_account_number_row">
                                    <label for="bank_account_number" class="">Account Number</label>
                                    <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" size="20" maxlength="20" id="bank_account_number" name="bank_account_number" placeholder="Bank Account Number" value="">
                                    <div class='clear-div'></div>
                                </div>
                            </div> <!-- payment_method_bank_account -->

                            <div class="payment-method-fields" id="payment_method_gift_card">
                                <div class="form-line" id="_gift_card_number_row">
                                    <label for="gift_card_number" class="">Card Number</label>
                                    <input tabindex="10" type="text" class="validate[required]"
                                           data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" size="30" maxlength="30" id="gift_card_number" name="gift_card_number" placeholder="Card Number" value="">
                                    <div class='clear-div'></div>
                                </div>
                                <p class="gift-card-information"></p>
                            </div> <!-- payment_method_gift_card -->

                            <div id="_billing_address" class='hidden'>
                                <div class="form-line" id="_billing_address_1_row">
                                    <label for="billing_address_1" class="">Billing Address</label>
                                    <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" size="30" maxlength="60" id="billing_address_1" name="billing_address_1" placeholder="Billing Address" value="">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_billing_city_row">
                                    <label for="billing_city" class="">City</label>
                                    <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" size="30" maxlength="60" id="billing_city" name="billing_city" placeholder="City" value="">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_billing_state_row">
                                    <label for="billing_state" class="">State</label>
                                    <input tabindex="10" type="text" class="validate[required]" data-conditional-required="($('#payment_amount').val() != '') && $('#billing_country_id').val() == 1000 && parseFloat($('#payment_amount').val()) > 0" size="10" maxlength="30" id="billing_state" name="billing_state" placeholder="State" value="">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_billing_state_select_row">
                                    <label for="billing_state_select" class="">State</label>
                                    <select tabindex="10" id="billing_state_select" name="billing_state_select" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && $('#billing_country_id').val() == 1000 && parseFloat($('#payment_amount').val()) > 0">
                                        <option value="">[Select]</option>
										<?php
										foreach (getStateArray() as $stateCode => $state) {
											?>
                                            <option value="<?= $stateCode ?>"><?= htmlText($state) ?></option>
											<?php
										}
										?>
                                    </select>
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_billing_postal_code_row">
                                    <label for="billing_postal_code" class="">Postal Code</label>
                                    <input tabindex="10" type="text" class="validate[required]" size="10" maxlength="10" data-conditional-required="$('#payment_amount').val() != '' && $('#billing_country_id').val() == 1000 && parseFloat($('#payment_amount').val()) > 0" id="billing_postal_code" name="billing_postal_code" placeholder="Postal Code" value="">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_billing_country_id_row">
                                    <label for="billing_country_id" class="">Country</label>
                                    <select tabindex="10" class="validate[required]" data-conditional-required="$('#payment_amount').val() != '' && parseFloat($('#payment_amount').val()) > 0" id="billing_country_id" name="billing_country_id">
										<?php
										foreach (getCountryArray() as $countryId => $countryName) {
											?>
                                            <option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
											<?php
										}
										?>
                                    </select>
                                    <div class='clear-div'></div>
                                </div>
                            </div> <!-- billing_address -->
                        </div>
                    </div> <!-- payment_section -->
				<?php } ?>

                <p id="_error_message_bottom" class="error-message"></p>
                <p class="align-center">
                    <button id="reserve_room">Make Reservation</button>
                </p>
            </form>
        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#account_id").change(function () {
                if (!empty($(this).val())) {
                    $("#_new_account").addClass('hidden');
                    $("#payment_method_id").val("").trigger("change");
                } else {
                    $("#_new_account").removeClass('hidden');
                }
            });
            $("#facility_id").change(function () {
                const reserveStartTime = $("#reserve_start_time").val();
                const reserveEndTime = $("#reserve_end_time").val();
                $("#reserve_start_time").find("option").unwrap("span");
                $("#reserve_end_time").find("option").unwrap("span");
                if (empty($(this).val())) {
                    return false;
                }
                const reservationStart = $(this).find("option:selected").data("reservation_start");
                if ((reservationStart + "").length > 0) {
                    $("#reserve_start_time").val("");
                    $("#reserve_end_time").val("");
                    const startMinute = parseInt(reservationStart);
                    $("#reserve_start_time").find("option").each(function () {
                        if (empty($(this).val())) {
                            return true;
                        }
                        const dataMinute = parseInt($(this).data("minute"));
                        if (dataMinute != startMinute) {
                            $(this).wrap("<span></span>");
                        } else if (reserveStartTime == $(this).val()) {
                            $(this).prop("selected", true);
                        }
                    });
                    $("#reserve_end_time").find("option").each(function () {
                        if (empty($(this).val())) {
                            return true;
                        }
                        const dataMinute = parseInt($(this).data("minute"));
                        if (dataMinute != startMinute) {
                            $(this).wrap("<span></span>");
                        } else if (reserveEndTime == $(this).val()) {
                            $(this).prop("selected", true);
                        }
                    });
                }
            });
            $("#event_type_id").change(function () {
                if (empty($("#description").val()) && !empty($(this).val())) {
                    $("#description").val($("#event_type_id option:selected").text());
                }
            });
            $("#payment_method_id").change(function () {
                $(".payment-method-fields").hide();
                if (empty($(this).val())) {
                    $("#_billing_address").addClass("hidden");
                } else {
                    $(".payment-method-fields").hide();
                    const noAddressRequired = $(this).find("option:selected").data("no_address_required");
                    if (empty(noAddressRequired)) {
                        $("#_billing_address").removeClass("hidden");
                    } else {
                        $("#_billing_address").addClass("hidden");
                    }
                    if (!empty($(this).val())) {
                        var paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");
                        $("#payment_method_" + paymentMethodTypeCode.toLowerCase()).show();
                    }
                }
            });
            if ($("#country_id").length > 0 && $("#state").length > 0 && $("#state_select").length > 0) {
                $("#country_id").change(function () {
                    if ($(this).val() == "1000") {
                        $("#_state_row").hide();
                        $("#_state_select_row").show();
                    } else {
                        $("#_state_row").show();
                        $("#_state_select_row").hide();
                    }
                }).trigger("change");
                $("#state_select").change(function () {
                    $("#state").val($(this).val());
                });
            }
            $("#billing_country_id").change(function () {
                if ($(this).val() == "1000") {
                    $("#_billing_state_row").hide();
                    $("#_billing_state_select_row").show();
                } else {
                    $("#_billing_state_row").show();
                    $("#_billing_state_select_row").hide();
                }
            }).trigger("change");
            $("#billing_state_select").change(function () {
                $("#billing_state").val($(this).val());
            });
            $("#date_needed").blur(function () {
                checkAvailability(false);
            });
            $("#reserve_start_time").add("#reserve_end_time").add("#facility_id").add(".recurring-event-field").change(function () {
                checkAvailability(false);
            });
            $("#reserve_start_time").add("#reserve_end_time").add("#facility_id").add("#number_people").change(function () {
                calculatePrice();
            });
            $(document).on("click", "#reserve_room", function () {
                if ($("#reserve_start_time").val() == "") {
                    $("#reserve_start_time").val("0");
                }
                if ($("#reserve_end_time").val() == "") {
                    $("#reserve_end_time").val("47");
                }
                if ($("#reserve_start_time").val() != "" && parseInt($("#reserve_end_time").val()) < parseInt($("#reserve_start_time").val())) {
                    $("#reserve_end_time").validationEngine("showPrompt", "End time must be after start time", "error", "topLeft", true);
                    return false;
                }
				<?php if ($this->iRecurringAllowed) { ?>
                if ($("#recurring").prop("checked")) {
                    if ($("#frequency").val() == "WEEKLY" && $(".byday-weekday:checked").length == 0) {
                        $(".byday-weekday:first").validationEngine("showPrompt", "Choose a weekday", "error", "topLeft", true);
                        return false;
                    }
                    if ($("#frequency").val() == "MONTHLY" && $("#ordinal_day_1").val() == "") {
                        $("#ordinal_day_1").validationEngine("showPrompt", "Choose a day of the month", "error", "topLeft", true);
                        return false;
                    }
                    if ($("#frequency").val() == "YEARLY" && $(".bymonth-month:checked").length == 0) {
                        $(".bymonth-month:first").validationEngine("showPrompt", "Choose a month", "error", "topLeft", true);
                        return false;
                    }
                    if ($("#frequency").val() == "YEARLY" && $("#ordinal_day_1").val() == "") {
                        $("#ordinal_day_1").validationEngine("showPrompt", "Choose a day of the month", "error", "topLeft", true);
                        return false;
                    }
                }
				<?php } ?>
                if ($("#_edit_form").validationEngine("validate")) {
                    checkAvailability(true);
                }
                return false;
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
                var thisValue = $(this).val();
                if (thisValue == "WEEKLY") {
                    $("#_byday_row").show();
                    $("#byday_weekly_table").show();
                } else if (thisValue == "MONTHLY") {
                    $("#_byday_row").show();
                    $("#byday_monthly_table").show();
                    $(".byday-monthly-row:first-child").show();
                } else if (thisValue == "YEARLY") {
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

            disableButtons($("#reserve_room"));
            $("#facility_id").trigger("change");
        </script>
		<?php
	}

	function javascript() {
		$timeIncrement = $this->getPageTextChunk("time_increment");
		if ($timeIncrement == "hour") {
			$timeIncrement = 1;
		} else if ($timeIncrement == "half") {
			$timeIncrement = .5;
		} else {
			$timeIncrement = .25;
		}
		?>
        <script>
            function calculatePrice() {
                if (!empty($("#date_needed").val()) && !empty($("#facility_id").val()) && !empty($("#reserve_start_time").val()) && !empty($("#reserve_end_time").val()) && !empty($("#number_people").val())) {
                    $("#cost_message").html("");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=calculate_price", $("#_edit_form").serialize(), function (returnArray) {
                        if ("cost_message" in returnArray) {
                            $("#cost_message").html(returnArray['cost_message']);
                        }
                        $("#payment_section").addClass("hidden");
                        $("#payment_amount").val("0");
                        if ("cost" in returnArray) {
                            if (returnArray['cost'] > 0) {
                                $("#payment_section").removeClass("hidden");
                                $("#payment_amount").val(RoundFixed(returnArray['cost'], 2));
                            }
                        }
                    });
                }
            }

            function makeReservation() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=make_reservation", $("#_edit_form").serialize(), function (returnArray) {
                    if ("response" in returnArray) {
                        $("#_form_div").html(returnArray['response']);
                    } else {
                        enableButtons($("#reserve_room"));
                    }
                });
            }

            function checkAvailability(reserveRoom) {
                if (empty($("#date_needed").val()) || empty($("#reserve_start_time").val()) || empty($("#reserve_end_time").val())) {
                    return;
                }
                if ($("#facility_id").attr("class") == "validate[required]" && empty($("#facility_id").val())) {
                    return;
                }

                disableButtons($("#reserve_room"));
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_availability", $("#_edit_form").serialize(), function (returnArray) {
                    if ("availability" in returnArray) {
                        $("#availability_message").removeClass("error-message").removeClass("success-message").html(returnArray['availability']).addClass(returnArray['class_name']);
                        if ($("#availability_message").is(".success-message")) {
                            if (reserveRoom) {
                                setTimeout(function () {
                                    makeReservation();
                                }, 200);
                            } else {
                                enableButtons($("#reserve_room"));
                            }
                        } else {
                            disableButtons($("#reserve_room"));
                        }
                    }
                });
            }

            $(window).load(function () {
				<?php
				$dateNeeded = $_GET['date_needed'];
				if (!empty($dateNeeded)) {
					$dateNeeded = date("Y-m-d", strtotime($dateNeeded));
					if ($dateNeeded < '1950-01-01') {
						$dateNeeded = "";
					}
				}
				?>
				<?php if (empty($dateNeeded)) { ?>
                $("#date_needed").focus();
				<?php } ?>
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #cost_message {
                font-size: 1.2rem;
                color: #b42912;
            }

            .payment-method-fields {
                display: none;
            }

            #availability_message {
                padding: 0;
                margin: 0;
                padding-top: 10px;
                padding-bottom: 20px;
                font-weight: 500;
                border: none;
            }

            #_form_div {
                width: 800px;
                max-width: 90%;
                margin: 20px 0;
            }

            .success-message {
                color: rgb(15, 180, 50);
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
        </style>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
