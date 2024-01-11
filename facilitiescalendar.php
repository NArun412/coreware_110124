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

$GLOBALS['gPageCode'] = "FACILITIESCALENDAR";
require_once "shared/startup.inc";

class FacilitiesCalenderPage extends Page {

	function headerIncludes() {
		?>
        <link type="text/css" rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.2.0/fullcalendar.min.css"/>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.17.1/moment.min.js"></script>
        <script src="//cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.2.0/fullcalendar.min.js"></script>
        <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/fullcalendar.css') ?>"/>
		<?php
	}

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "save", "delete"));
			$this->iTemplateObject->getTableEditorObject()->setReadonly(true);
			$this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("print" => array("label" => getLanguageText("Print"), "disabled" => false)));
		}
	}

	function internalCSS() {
		?>
        <style>
            .fc-content-col {
                cursor: pointer;
            }
            #facility_calendar {
                border: 1px solid rgb(200, 200, 200);
                padding: 20px;
            }
        </style>
        <style id="_printable_style">
            body > #_report_content .fc-left, body > #_report_content .fc-right {
                display: none;
            }
            .fc-scroller {
                max-height: 100%;
                height: auto !important;
            }
        </style>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("tap click", "#_print_button", function () {
                window.open("/printable.html");
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function initializeCalendar(viewName) {
                if (viewName == null) {
                    viewName = $("#facility_calendar").data("view");
                }
                if (empty(viewName)) {
                    viewName = "agendaWeek";
                }
                $("#facility_calendar").data("view", viewName).fullCalendar("destroy");
                if (empty($("#primary_id").val())) {
                    return;
                }
                let calendar = $('#facility_calendar').fullCalendar({
                    allDaySlot: false,
                    height: 850,
                    defaultView: viewName,
                    allDayDefault: false,
                    minTime: "5:00",
                    header: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'month,agendaWeek,agendaDay'
                    },
                    selectable: false,
                    selectHelper: false,
                    eventSources: [
                        {
                            url: '<?= $GLOBALS['gLinkUrl'] ?>?url_action=get_events&facility_id=' + $("#primary_id").val(),
                            type: 'GET',
                            editable: false,
                            allDayDefault: false
                        }
                    ],
                    eventClick: function (event, jsEvent, view) {
                        <?php if (canAccessPageCode("EVENTMAINT")) { ?>
                        const eventId = event.event_id;
                        window.open("/eventmaintenance.php?clear_filter=true&url_page=show&primary_id=" + eventId);
                        <?php } ?>
                    },
                    editable: false
                });
            }

            function afterGetRecord() {
                initializeCalendar();
            }
        </script>
		<?php
	}

	function executePageUrlActions() {
		if ($_GET['url_action'] == "get_events") {
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
			$currentStartDate = "";
			$currentEventId = "";
			$currentRepeatRules = "";
			$lastHour = "";
			$recurIndex = -1;
			$facilityId = $_GET['facility_id'];
			$resultSet = executeQuery("select * from event_facility_recurrences where facility_id = ? order by event_id,facility_id,repeat_rules,hour", $facilityId);
			while ($row = getNextRow($resultSet)) {
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
						$thisEndHour = floor($row['end'] + .25) . ":" . str_pad(($row['end'] + .25 - floor($row['end'] + .25)) * 60, 2, "0", STR_PAD_LEFT) . ":00";
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
		}
	}
}

$pageObject = new FacilitiesCalenderPage("facilities");
$pageObject->displayPage();
