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

$GLOBALS['gPageCode'] = "EVENTSHAPPENINGNOW";
require_once "shared/startup.inc";

/* text instructions
<p>Several page text chunks can be used on the donation page. The code is important and must match the code listed here. The description is just informational and can contain anything. The value is what is used by the page.</p>
<ul>
    <li><strong>happening_now_segments</strong> - The number of time segments to show. The default is 8.</li>
    <li><strong>include_facility_type_codes</strong> - Comma separated list of facility types to include in the calendar. The default is all of them. This is useful if the calendar is only for one or two facility types.</li>
    <li><strong>exclude_facility_type_codes</strong> - Comma separated list of facility types to exclude. This is useful if the calendar is for all but one or more facility types.</li>
    <li><strong>facility_id_9999</strong> - The value is the link URL for events for the facility with ID 9999. Any number of these can be included. If no URL is set, the event doesn't redirect anywhere.</li>
    <li><strong>facility_type_id_9999</strong> - The value is the link URL for facilities whose facility type ID is 9999. Any number of these can be included. If no URL is set, the event doesn't redirect anywhere.</li>
    <li><strong>facility_type_code_XXXX</strong> - The value is the link URL for facilities whose facility type code is XXXX. Any number of these can be included. If no URL is set, the event doesn't redirect anywhere.</li>
    <li><strong>days_ahead_user_group_XXXX</strong>, <strong>days_ahead_user_type_YYYY</strong>, and <strong>days_ahead</strong> - These three text chunks work together. Typically, there are no restrictions on making reservations. A user could reserve a facility a year in advance, if wanted. These options put a restriction on this. XXXX represents the user group code and YYYY represents the user type code. User group takes precedence, then user type, then simply the days ahead. The value must be a numeric value representing the limit of the number of days before that the facility can be reserved.</li>
</ul>
*/

class ThisPage extends Page {

	function mainContent() {
		echo $this->getPageData("content");
		if (!empty($_GET['event_date'])) {
			$eventDate = date("Y-m-d", strtotime($_GET['event_date']));
		} else {
			$eventDate = date("Y-m-d");
		}
		if (strlen($_GET['hour']) > 0 && is_numeric($_GET['hour']) && $_GET['hour'] >= 0 && $_GET['hour'] < 24) {
			$eventHour = $_GET['hour'];
			$eventMinute = 0;
		} else {
			$eventHour = date("G");
			$eventMinute = (date("i") < 30 ? 0 : 30);
		}
		$happeningNowSegments = $this->getPageTextChunk("happening_now_segments");
		if (empty($happeningNowSegments)) {
			$happeningNowSegments = 8;
		}
		$includeFacilityTypeCodesString = $this->getPageTextChunk("INCLUDE_FACILITY_TYPE_CODES");
		$includeFacilityTypeIds = array();
		if (!empty($includeFacilityTypeCodesString)) {
			foreach (explode(",", $includeFacilityTypeCodesString) as $facilityTypeCode) {
				$facilityTypeId = getFieldFromId("facility_type_id", "facility_types", "facility_type_code", $facilityTypeCode);
				if (!empty($facilityTypeId)) {
					$includeFacilityTypeIds[] = $facilityTypeId;
				}
			}
		}
		$excludeFacilityTypeCodesString = $this->getPageTextChunk("EXCLUDE_FACILITY_TYPE_CODES");
		$excludeFacilityTypeIds = array();
		if (!empty($excludeFacilityTypeCodesString)) {
			foreach (explode(",", $excludeFacilityTypeCodesString) as $facilityTypeCode) {
				$facilityTypeId = getFieldFromId("facility_type_id", "facility_types", "facility_type_code", $facilityTypeCode);
				if (!empty($facilityTypeId)) {
					$excludeFacilityTypeIds[] = $facilityTypeId;
				}
			}
		}
		$facilityStatuses = array();
		$resultSet = executeQuery("select *,(select description from facility_types where facility_type_id = facilities.facility_type_id) facility_type," .
			"(select sort_order from facility_types where facility_type_id = facilities.facility_type_id) facility_type_sort from facilities where " .
			"inactive = 0 and client_id = ? and internal_use_only = 0 and facility_type_id in (select facility_type_id from facility_types where " .
			"inactive = 0 and internal_use_only = 0" . (empty($includeFacilityTypeIds) ? "" : " and facility_type_id in (" . implode(",", $includeFacilityTypeIds) . ")") .
			(empty($excludeFacilityTypeIds) ? "" : " and facility_type_id not in (" . implode(",", $excludeFacilityTypeIds) . ")") .
			") order by facility_type_sort,facility_type_id,sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$currentDate = $eventDate;
			$currentHour = $eventHour;
			$currentMinute = $eventMinute;
			if ($currentMinute > 0 && $currentMinute <= 15) {
				$currentHour += .25;
			} else if ($currentMinute > 15 && $currentMinute <= 30) {
				$currentHour += .5;
			} else if ($currentMinute > 30) {
				$currentHour += .75;
			}
			$sortTotal = 0;
			for ($x = 1; $x <= $happeningNowSegments; $x++) {
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
		?>
        <input type='hidden' name='schedule_date' id='schedule_date' value='<?= $eventDate ?>'>
        <div class='form-line inline-block' id="_event_date_row">
            <label>Date</label>
            <input type='text' class='datepicker validate[custom[date]]' id='event_date' name='event_date' value="<?= date("m/d/Y", strtotime($eventDate)) ?>">
        </div>
        <div class='form-line inline-block' id="_hour_row">
            <label>Time</label>
            <select id='hour' name='hour' value="<?= date("m/d/Y", strtotime($eventDate)) ?>">
				<?php for ($x = 0; $x < 24; $x++) { ?>
                    <option<?= ($x == $eventHour ? " selected" : "") ?> value='<?= $x ?>'><?= ($x == 0 ? "Midnight" : ($x == 12 ? "Noon" : ($x < 12 ? $x . ":00am" : ($x - 12) . ":00pm"))) ?></option>
				<?php } ?>
            </select>
        </div>
        <div class='form-line inline-block' id='_go_button_row'>
            <button id='go_button'>Go</button>
        </div>

        <?php
            $previousHour = $eventHour - floor($happeningNowSegments / 4);
            $nextHour = $eventHour + floor($happeningNowSegments / 4);
            if($previousHour >= 0) {
                echo '<div class="form-line inline-block" id="_previous_hour_row">';
                echo sprintf('<a href="%s?event_date=%s&hour=%s">&lt; %s</a>',$GLOBALS['gLinkUrl'] , urlencode($eventDate) , $previousHour , Events::getDisplayTime($previousHour));
                echo '</div>';
            }
            if($nextHour < 24) {
                echo '<div class="form-line inline-block" id="_next_hour_row">';
                echo sprintf('<a href="%s?event_date=%s&hour=%s">%s &gt;</a>',$GLOBALS['gLinkUrl'] , urlencode($eventDate) , $nextHour , Events::getDisplayTime($nextHour));
                echo '</div>';
            }
            ?>
        <div id="happening_now_calendar">
            <table id="happening_now_table">
                <tr>
                    <td></td>
					<?php
					$currentHour = $eventHour;
					$currentMinute = $eventMinute;
					if ($currentMinute > 0 && $currentMinute <= 15) {
						$currentHour += .25;
					} else if ($currentMinute > 15 && $currentMinute <= 30) {
						$currentHour += .5;
					} else if ($currentMinute > 30) {
						$currentHour += .75;
					}
					$displayDate = date("Y-m-d",strtotime($eventDate));
					$displayDates = array();
					for ($x = 1; $x <= $happeningNowSegments; $x++) {
						$workingHour = floor($currentHour);
						$displayHour = ($workingHour == 0 ? 12 : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
						$displayMinute = ($currentHour - $workingHour) * 60;
						$amPm = ($currentHour == 0 ? "midnight" : ($currentHour == 12 ? "noon" : ($currentHour >= 12 ? "PM" : "AM")));
                        if ($happeningNowSegments > 12 && ($displayMinute == 15 || $displayMinute == 45)) {
                            $displayTime = "";
                        } else {
                            $displayTime = $displayHour . ":" . str_pad($displayMinute, 2, "0", STR_PAD_LEFT) . " " . $amPm;
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
				if (!empty($daysAhead)) {
					$maximumDate = Date('Y-m-d', strtotime('+' . $daysAhead . ' days'));
				}

				$saveFacilityType = "";
				foreach ($facilityStatuses as $index => $facilityInfo) {
					if ($saveFacilityType != $facilityInfo['facility_type']) {
						?>
                        <tr>
                            <td colspan="<?= $happeningNowSegments + 1 ?>" class='highlighted-text align-left facility-type-header'><?= htmlText($facilityInfo['facility_type']) ?></td>
                        </tr>
						<?php
						$saveFacilityType = $facilityInfo['facility_type'];
					}
					$reservationStart = getFieldFromId("reservation_start","facilities","facility_id",$facilityInfo['facility_id']);
					if ($eventDate >= date("Y-m-d")) {
						$linkLocation = $this->getPageTextChunk("facility_id_" . $facilityInfo['facility_id']);
						if (empty($linkLocation)) {
							$linkLocation = $this->getPageTextChunk("facility_type_id_" . $facilityInfo['facility_type_id']);
						}
						if (empty($linkLocation)) {
							$linkLocation = $this->getPageTextChunk("facility_type_code_" . getFieldFromId("facility_type_code", "facility_types", "facility_type_id", $facilityInfo['facility_type_id']));
						}
					} else {
						$linkLocation = "";
					}
					?>
                    <tr class="facility-type-id-<?= $facilityInfo['facility_type_id'] ?>" data-link_location="<?= $linkLocation ?>" data-facility_id="<?= $facilityInfo['facility_id'] ?>" data-facility_type_id="<?= $facilityInfo['facility_type_id'] ?>">
                        <td><?= htmlText($facilityInfo['description']) ?></td>
						<?php
						$lastEventId = "";
						for ($x = 1; $x <= $happeningNowSegments; $x++) {
							$startMinute = round(($displayDates[$x]['current_hour'] - floor($displayDates[$x]['current_hour'])) * 60);
							$checkStartHour = $displayDates[$x]['current_hour']; # 16.25
						    if (strlen($reservationStart) == 0) {
							    $checkEndHour = $displayDates[$x]['current_hour'];
						    } else {
							    $checkStartHour += .25;
							    do {
								    $checkStartHour -= .25;
								    $checkStartMinute = round(($checkStartHour - floor($checkStartHour)) * 60);
							    } while ($checkStartMinute != $reservationStart);
							    $checkEndHour = $checkStartHour + .75;
						    }
							$availableFacilities = Events::getAvailableFacilities($facilityInfo['facility_type_id'], $facilityInfo['facility_id'], $eventDate, $checkStartHour, $checkEndHour);
							if (!is_array($availableFacilities) || !in_array($facilityInfo['facility_id'], $availableFacilities) || empty($linkLocation) || (!empty($maximumDate) && $eventDate > $maximumDate)) {
								$class = "unavailable";
							} else {
								$class = "available reservable";
							}
							$titleTime = "";
							if (strlen($reservationStart) > 0) {
                                if ($startMinute != $reservationStart) {
                                    $class .= (empty($class) ? "" : " ") . "not-reservation-start " . $startMinute . "-" . $reservationStart;
                                    $titleHour = floor($displayDates[$x]['current_hour']);
                                    if ($reservationStart > $startMinute) {
                                        $titleHour -= 1;
                                    }
                                    $titleMinute = $reservationStart;
                                    $titleTime = ($titleHour > 12 ? ($titleHour - 12) : $titleHour) . ":" . ($titleMinute == 0 ? "00" : $titleMinute) . ($titleHour == 12 && $titleMinute == 0 ? "noon" : ($titleHour > 11 ? "pm" : "am"));
                                }
                            }
							if (!empty($facilityInfo['event_id_' . $x]['event_id'])) {
								$class = "happening-now happening-now-" . $facilityInfo['event_id_' . $x]['event_id'] . " reserved";
							}
							if (!empty($facilityInfo['event_id_' . $x]['event_id']) && $facilityInfo['event_id_' . $x]['event_id'] == $lastEventId) {
								$class .= " continuing-event";
								$displayDescription = "";
							} else {
								$displayDescription = (empty($facilityInfo['event_id_' . $x]['description']) ? "" : ($GLOBALS['gUserRow']['administrator_flag'] || $GLOBALS['gUserRow']['contact_id'] == $facilityInfo['event_id_' . $x]['contact_id'] ? $facilityInfo['event_id_' . $x]['description'] : "Reserved"));
							}
							$lastEventId = $facilityInfo['event_id_' . $x]['event_id'];
							$endTime = date("Y-m-d H:i:s", strtotime("+15 minute", strtotime($displayDates[$x]['description'])));
							$eventColorClass = ($GLOBALS['gUserRow']['administrator_flag'] ? Events::getEventColorClass($facilityInfo['event_id_' . $x],$facilityInfo) : "");
							if (!empty($eventColorClass)) {
								$class .= (empty($class) ? "" : " ") . $eventColorClass;
							}
							$titleText = str_replace("'","",$facilityInfo['description']) . ", " . date("D, M jS " . (empty($titleTime) ? "g:ia" : ""),strtotime($displayDates[$x]['description'])) . $titleTime;
							?>
                            <td class="<?= $class ?>" title="<?= $titleText ?>" data-event_id="<?= $facilityInfo['event_id_' . $x]['event_id'] ?>" data-start_date="<?= $displayDates[$x]['description'] ?>" data-start_time="<?= $displayDates[$x]['current_hour'] ?>" data-end_date="<?= $endTime ?>" data-end_time="<?= $displayDates[$x]['current_hour'] ?>"><?= htmlText($displayDescription) ?></td>
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
		echo $this->getPageData("after_form_content");
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".reservable", function () {
                const linkLocation = $(this).closest("tr").data("link_location");
                if (empty(linkLocation)) {
                    return false;
                }
                const facilityId = $(this).closest("tr").data("facility_id");
                const startDate = $(this).data("start_date");
                document.location = linkLocation + (linkLocation.indexOf("?") < 0 ? "?" : "&") + "facility_id=" + facilityId + "&start_date=" + encodeURIComponent(startDate);
            });
            $(document).on("click", "#go_button", function () {
                document.location = "<?= $GLOBALS['gLinkUrl'] ?>?event_date=" + encodeURIComponent($("#event_date").val()) + "&hour=" + $("#hour").val();
                return false;
            });
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #happening_now_table td.facility-type-header {
                padding-top: 5px;
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
                height: 30px;
                padding: 0 0 0 5px;
                font-size: .75rem;
                border-left: 2px solid rgb(230, 230, 230);
                white-space: nowrap;
                overflow: hidden;
                border-bottom: 3px solid rgb(230, 230, 230);
                vertical-align: middle;
            }

            #happening_now_table td.not-reservation-start {
                border-left: none;
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
            }
            #happening_now_table td.unavailable {
                background-color: rgb(200, 200, 200);
            }
            #happening_now_table td.reservable {
                cursor: pointer;
            }

            #happening_now_table td.reserved {
                background-color: rgb(204, 58, 69);
                color: white;
            }

            #happening_now_table td.reserved.continuing-event {
                border-left: 1px solid rgb(204, 58, 69);
            }

            #happening_now_table td.upcoming-event.continuing-event {
                border-left: 1px solid rgb(215, 179, 73);
            }

            #event_date {
                width: 180px;
            }
            #hour {
                width: 150px;
            }
            .form-line {
                width: auto;
                padding-right: 40px;
            }
            .form-line#_event_date_row button {
                color: rgb(50, 50, 50);
            }
            .form-line#_event_date_row .ui-datepicker-trigger:hover, table .ui-datepicker-trigger:hover {
                color: rgb(150, 150, 150);
            }
            <?php
                    $resultSet = executeQuery("select * from event_colors where client_id = ?",$GLOBALS['gClientId']);
                    while ($row = getNextRow($resultSet)) {
                    ?>
            #happening_now_table td.event-color-id-<?= $row['event_color_id'] ?> {
                background-color: <?= $row['display_color'] ?>;
            }
            <?php
                }
         ?>

        </style>
		<?php
	}

}

$pageObject = new ThisPage();
$pageObject->displayPage();
