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

$GLOBALS['gPageCode'] = "EVENTICS";
require_once "shared/startup.inc";

$eventId = getFieldFromId("event_id", "events", "event_id", $_GET['id']);
if (empty($eventId)) {
	echo "No Event";
	exit;
}
$eventRow = getRowFromId("events", "event_id", $eventId);
$facilityId = false;
$startDate = false;
$startHour = false;
$endDate = false;
$endHour = false;
$resultSet = executeQuery("select * from event_facilities where event_id = ? order by date_needed,hour",$eventId);
while ($row = getNextRow($resultSet)) {
	if ($facilityId === false) {
		$facilityId = $row['facility_id'];
		$startDate = $row['date_needed'];
		$startHour = $row['hour'];
	}
	$endDate = $row['date_needed'];
	$endHour = $row['hour'];
}
if ($facilityId === false) {
	echo "No Facility";
	exit;
}
$timeParts = Events::getDisplayTime($startHour, false, true);
if ($timeParts['ampm'] == "pm") {
	$timeParts['hour'] += 12;
}
$eventStartTime = $startDate . " " . ($timeParts['hour'] < 10 ? "0" : "") . $timeParts['hour'] . ":" . ($timeParts['minute'] < 10 ? "0" : "") . $timeParts['minute'] . ":00";
$timeParts = Events::getDisplayTime($endHour, true, true);
if ($timeParts['ampm'] == "pm") {
	$timeParts['hour'] += 12;
}
$eventEndTime = $endDate . " " . ($timeParts['hour'] < 10 ? "0" : "") . $timeParts['hour'] . ":" . ($timeParts['minute'] < 10 ? "0" : "") . $timeParts['minute'] . ":00";

$icsObject = new ics();
$icsObject->set("summary", $eventRow['description']);
$icsObject->set("dtstart", $eventStartTime);
$icsObject->set("dtend", $eventEndTime);
$icsObject->set("location", getFieldFromId("description","facilities","facility_id",$facilityId));

header('Content-type: text/calendar; charset=utf-8');
header('Content-Disposition: inline; filename=calendar.ics');
echo $icsObject->toString();
exit;
