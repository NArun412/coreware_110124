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

$GLOBALS['gPageCode'] = "EVENTCALENDAR";
require_once "shared/startup.inc";

/* text instructions
<p>Several page text chunks can be used on this page. The code is important and must match the code listed here. The description is just informational and can contain anything. The value is what is used by the page.</p>
<ul>
    <li><strong>allow_show_all</strong> - If a value other than "0" is entered here, events from all locations can be shown on the calendar together.</li>
    <li><strong>hide_past_events</strong> - If a value other than "0" is entered here, past events will not appear in the calendar.</li>
    <li><strong>use_registration_page</strong> - If a value other than "0" is entered here, the registration link will go to the event registration page instead of the product details page.</li>
</ul>
*/

class EventCalendarPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		$useRegistrationPage = !empty($this->getPageTextChunk("use_registration_page"));
		switch ($_GET['url_action']) {
			case "get_product_info":
				$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id'], "inactive = 0 and product_id in (select product_id from events where product_id is not null)");
				if (empty($productId)) {
					ajaxResponse($returnArray);
					break;
				}
				$productRow = ProductCatalog::getCachedProductRow($productId);
				$eventRow = getRowFromId("events", "product_id", $productId);
				ob_start();
				if (!empty($productRow['image_id'])) {
					?>
                    <p id="class_description_image"><img src="<?= getImageFilename($productRow['image_id'], array("use_cdn" => true)) ?>"/></p>
					<?php
				}
				?>
                <p id="class_description_title"><?= htmlText($productRow['description']) ?></p>
				<?php
				echo makeHtml($productRow['detailed_description']);
				if ($eventRow['start_date'] < date("Y-m-d") || !empty($productRow['inactive'])) {
					?>
                    <p id="class_description_completed">This class is already completed.</p>
					<?php
				} else {
					$attendeeCounts = Events::getAttendeeCounts($eventRow['event_id']);
					$spotsLeft = $attendeeCounts['attendees'] - $attendeeCounts['registrants'];
					$registrationLink = ($useRegistrationPage ? "/event-registration?id=" . $eventRow['event_id'] : "/product-details?id=" . $productId);
					if ($spotsLeft <= 0) {
						?>
                        <p id="class_description_full">This class is full. <a href='#' class='add-to-wishlist' data-product_id='<?= $productId ?>'>Get on waiting list.</a></p>
						<?php
					} else {
						?>
                        <p id="class_description_register"><a href='<?= $registrationLink ?>'><?= $spotsLeft ?> spot<?= ($spotsLeft == 1 ? "" : "s") ?> left. Register Now.</a></p>
						<?php
					}
				}
				$returnArray['class_description'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "get_events":
				$showAll = (!empty($this->getPageTextChunk("allow_show_all")));
				$hidePastEvents = (!empty($this->getPageTextChunk("hide_past_events")));

				$locationId = getFieldFromId("location_id", "locations", "location_id", $_GET['location_id'], "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
				if (!$showAll && empty($locationId)) {
					$returnArray['error_message'] = "Invalid Location";
					ajaxResponse($returnArray);
					break;
				}
				$eventTypeId = getFieldFromId("event_type_id", "event_types", "event_type_id", $_GET['event_type_id'], "hide_in_calendar = 0 and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
				$startDate = ($hidePastEvents ? ((date("Y-m-d", strtotime($_GET['start'])) > date("Y-m-d")) ? date("Y-m-d", strtotime($_GET['start'])) : date("Y-m-d")) : date("Y-m-d", strtotime($_GET['start'])));
				$endDate = date("Y-m-d", strtotime($_GET['end']));
				$scheduleArray = array();

				$resultSet = executeQuery("select * from event_facilities where " .
					(empty($eventTypeId) ? "event_id in (select event_id from events where client_id = " . $GLOBALS['gClientId'] . " and event_type_id in (select event_type_id from event_types where inactive = 0 and hide_in_calendar = 0" .
						($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . ")) and " : "event_id in (select event_id from events where client_id = " . $GLOBALS['gClientId'] . " and event_type_id = " . $eventTypeId . ") and ") .
					"(facility_id in (select facility_id from facilities where " . (empty($locationId) ? "" : "location_id = " . $locationId . " and ") . "inactive = 0" .
					($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . ") or " .
					"event_id in (select event_id from events where client_id = " . $GLOBALS['gClientId'] . " and " . (empty($locationId) ? "" : "location_id = " . $locationId . " and ") . "inactive = 0))" .
					($GLOBALS['gInternalConnection'] ? "" : " and event_id in (select event_id from events where " . ($GLOBALS['gInternalConnection'] ? "" : "internal_use_only = 0 and ") . "event_type_id in (select event_type_id from event_types where inactive = 0 and hide_in_calendar = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . "))") .
					" and date_needed between ? and ? order by event_id,date_needed,facility_id,hour", $startDate, $endDate);
				$currentDate = "";
				$currentEventId = "";
				$lastHour = "";
				$firstHour = "";
				$index = -1;
				while ($row = getNextRow($resultSet)) {
					$displayColor = getFieldFromId("display_color", "event_types", "event_type_id", getFieldFromId("event_type_id", "events", "event_id", $row['event_id']));

					if ($row['date_needed'] < date('Y-m-d')) {
						$displayColor = '#CCCCCC';
					}

					$productId = getFieldFromId("product_id", "events", "event_id", $row['event_id']);
					$thisDate = date("Y-m-d", strtotime($row['date_needed']));
					$thisStartHour = floor($row['hour']) . ":" . str_pad(($row['hour'] - floor($row['hour'])) * 60, 2, "0", STR_PAD_LEFT) . ":00";
					$thisEndHour = floor($row['hour'] + .25) . ":" . str_pad((($row['hour'] + .25) - floor($row['hour'] + .25)) * 60, 2, "0", STR_PAD_LEFT) . ":00";
					if ($currentDate != $row['date_needed'] || $currentEventId != $row['event_id']) {
						$index++;
						$scheduleArray[$index] = array("id" => $row['event_facility_id'], "event_id" => $row['event_id'], "product_id" => $productId, "title" => getFieldFromId("description", "events", "event_id", $row['event_id']),
							"link_url" => getFieldFromId("link_url", "events", "event_id", $row['event_id']), "start" => date("c", strtotime($thisDate . " " . $thisStartHour)), "end" => date("c", strtotime($thisDate . " " . $thisEndHour)), "can_register" => ($thisDate >= date("Y-m-d")));
						if (!empty($displayColor)) {
							$scheduleArray[$index]['backgroundColor'] = $displayColor;
						}
						$firstHour = $row['hour'];
						$lastHour = $row['hour'];
						$currentDate = $row['date_needed'];
						$currentEventId = $row['event_id'];
						continue;
					}
					if ($row['hour'] <= ($lastHour + .25)) {
						if ($row['hour'] > $lastHour) {
							$scheduleArray[$index]["end"] = date("c", strtotime($thisDate . " " . $thisEndHour));
							$lastHour = $row['hour'];
						} else if ($row['hour'] < $firstHour) {
							$scheduleArray[$index]['start'] = date("c", strtotime($thisDate . " " . $thisStartHour));
							$firstHour = $row['hour'];
						}
					} else {
						$index++;
						$scheduleArray[$index] = array("id" => $row['event_facility_id'], "event_id" => $row['event_id'], "product_id" => $productId, "title" => getFieldFromId("description", "events", "event_id", $row['event_id']),
							"link_url" => getFieldFromId("link_url", "events", "event_id", $row['event_id']), "start" => date("c", strtotime($thisDate . " " . $thisStartHour)), "end" => date("c", strtotime($thisDate . " " . $thisEndHour)), "can_register" => ($thisDate >= date("Y-m-d")));
						if (!empty($displayColor)) {
							$scheduleArray[$index]['backgroundColor'] = $displayColor;
						}
					}
					$currentDate = $row['date_needed'];
					$currentEventId = $row['event_id'];
				}

				$recurrenceArray = array();
				$currentEventId = "";
				$currentRepeatRules = "";
				$lastHour = "";
				$recurIndex = -1;
				$resultSet = executeQuery("select * from event_facility_recurrences where facility_id in (select facility_id from facilities where location_id = ?) and " .
					"event_id in (select event_id from events where client_id = " . $GLOBALS['gClientId'] . " and event_type_id in (select event_type_id from event_types where inactive = 0 and hide_in_calendar = 0" .
					($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . ")) order by event_id,facility_id,repeat_rules,hour", $locationId);
				while ($row = getNextRow($resultSet)) {
					$productId = getFieldFromId("product_id", "events", "event_id", $row['event_id']);
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
								"link_url" => getFieldFromId("link_url", "events", "event_id", $row['event_id']), "title" => getFieldFromId("description", "events", "event_id", $row['event_id']), "event_id" => $row['event_id'],
								"product_id" => $productId, "start" => date("c", strtotime($startDate . " " . $thisStartHour)), "end" => date("c", strtotime($startDate . " " . $thisEndHour)),
								"editable" => false, "allDay" => false, "deletable" => false, "can_register" => ($startDate >= date("Y-m-d")));
						}
					}
					$startDate = date("Y-m-d", strtotime("+1 day", strtotime($startDate)));
				}
				echo jsonEncode($scheduleArray);
				exit;
		}
	}

	function headerIncludes() {
		?>
        <link type="text/css" rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.2.0/fullcalendar.min.css"/>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.17.1/moment.min.js"></script>
        <script src="//cdnjs.cloudflare.com/ajax/libs/fullcalendar/3.2.0/fullcalendar.min.js"></script>
        <link type="text/css" rel="stylesheet" href="<?= autoVersion('/css/fullcalendar.css') ?>"/>
		<?php
	}

	function displayCalendar() {
		echo $this->iPageData['content'];
		$showAll = (!empty($this->getPageTextChunk("allow_show_all")));
		?>
        <p>
            <select id="calendar_location_id" name="calendar_location_id">
                <option value="">[<?= ($showAll ? "Show All" : "Select location to see calendar") ?>]</option>
				<?php
				$resultSet = executeQuery("select * from locations where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and product_distributor_id is null and location_id in (select location_id from events) order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$selected = ($resultSet['row_count'] == 1 || $row['location_id'] == $_GET['location_id'] ? "selected" : "");
					?>
                    <option value="<?= $row['location_id'] ?>" <?= $selected ?>><?= htmlText($row['description']) ?></option>
					<?php
				}
				?>
            </select>
            <select id="calendar_event_type_id" name="calendar_event_type_id">
                <option value="">[Show All]</option>
				<?php
				$resultSet = executeQuery("select * from event_types where client_id = ? and hide_in_calendar = 0 and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if ($resultSet['row_count'] == 1 || $row['event_type_id'] == $_GET['event_type_id']) {
						$selected = "selected";
					} else {
						$selected = "";
					}
					?>
                    <option value="<?= $row['event_type_id'] ?>" <?= $selected ?>><?= htmlText($row['description']) ?></option>
					<?php
				}
				?>
            </select>
        </p>
        <div id="_calendar_wrapper">
            <div id="location_calendar"></div>
            <div id="class_description" class="hidden"></div>
        </div>
		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function internalCSS() {
		?>
        <style>
            #location_calendar {
                border: 1px solid rgb(200, 200, 200);
                padding: 20px;
                flex: 1 1 calc(100% - 400px);
            }

            #class_description {
                flex: 0 0 400px;
                max-width: 400px;
                margin: 0 auto;
                padding: 20px;
                border: 1px solid rgb(200, 200, 200);
                margin-left: 20px;
            }

            #class_description img {
                max-width: 100%;
            }

            .fc-event {
                font-size: .8rem;
                cursor: pointer;
            }

            #_calendar_wrapper {
                display: flex;
            }

            #class_description_title, #class_description_image, #class_description_register, #class_description_completed, #class_description_full {
                text-align: center;
                font-weight: 900;
            }

            @media only screen and (max-width: 1000px) {
                #_calendar_wrapper {
                    display: block;
                }
            }
        </style>
		<?php
	}

	function onLoadJavascript() {
		if ($GLOBALS['gLoggedIn']) {
			$defaultLocationId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID");
		} else {
			$defaultLocationId = $_COOKIE['default_location_id'];
		}
		$defaultLocationId = getFieldFromId("location_id", "locations", "location_id", $defaultLocationId, "inactive = 0");
		$showAll = (!empty($this->getPageTextChunk("allow_show_all")));
		?>
        <script>
            $("#calendar_location_id,#calendar_event_type_id").change(function () {
                initializeCalendar();
            });
			<?php if (!empty($defaultLocationId) || $showAll) { ?>
            setTimeout(function () {
                $("#calendar_location_id").val("<?= $defaultLocationId ?>").trigger("change");
            }, 200);
			<?php } ?>
        </script>
		<?php
	}

	function javascript() {
		$showAll = (!empty($this->getPageTextChunk("allow_show_all")));
		$linkName = getFieldFromId("link_name", "pages", "script_filename", "eventregistration.php", "script_arguments is null");
		?>
        <script>
            function initializeCalendar(viewName) {
                if (viewName == null) {
                    viewName = $("#location_calendar").data("view");
                }
                if (viewName == "" || viewName == null) {
                    viewName = "month";
                }
                $("#location_calendar").data("view", viewName).fullCalendar("destroy");
				<?php if (!$showAll) { ?>
                if ($("#calendar_location_id").val() == "") {
                    return;
                }
				<?php } ?>
                var calendar = $('#location_calendar').fullCalendar({
                    allDaySlot: false,
                    defaultView: viewName,
                    defaultEventMinutes: 30,
                    allDayDefault: false,
                    eventBackgroundColor: 'rgb(0,200,0)',
                    header: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'month,agendaWeek,agendaDay'
                    },
                    viewDisplay: function (view) {
                        if (view.name != viewName) {
                            initializeCalendar(view.name);
                        }
                    },
                    selectable: false,
                    aspectRatio: 1.25,
                    selectHelper: false,
                    events: "<?= $GLOBALS['gLinkUrl'] ?>?url_action=get_events&location_id=" + $("#calendar_location_id").val() + "&event_type_id=" + $("#calendar_event_type_id").val(),
                    eventClick: function (event, jsEvent, view) {
                        const productId = event.product_id;
                        if (empty(productId)) {
                            const eventId = event.event_id;
                            const linkUrl = event.link_url;
                            if (!empty(linkUrl)) {
                                if (linkUrl.indexOf("http") == 0) {
                                    window.open(linkUrl);
                                } else if (linkUrl.substring(0, 1) == "/") {
                                    document.location = linkUrl;
                                } else {
                                    document.location = "/" + linkUrl;
                                }
                            } else {
								<?php if (!empty($linkName)) { ?>
                                if (!empty(event.can_register)) {
                                    window.open("/<?= $linkName ?>?id=" + eventId);
                                }
								<?php } ?>
                            }
                        } else {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_product_info&product_id=" + productId, function (returnArray) {
                                if ("class_description" in returnArray) {
                                    $("#class_description").html(returnArray['class_description']).removeClass("hidden");
                                }
                                if (typeof afterClickCalendarEvent === 'function') {
                                    afterClickCalendarEvent();
                                }
                            });
                        }
                    },
                    editable: false
                });
            }
        </script>
		<?php
	}
}

$pageObject = new EventCalendarPage();
$pageObject->displayPage();
