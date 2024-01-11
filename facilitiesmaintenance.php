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

$GLOBALS['gPageCode'] = "FACILITIESMAINT";
require_once "shared/startup.inc";

class FacilitiesMaintenancePage extends Page {

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
			$this->iTemplateObject->getTableEditorObject()->setFileUpload(true);
            if ($GLOBALS['gPermissionLevel'] > _READONLY) {
                $this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("duplicate" => array("icon" => "fad fa-copy", "label" => getLanguageText("Duplicate"), "disabled" => false)));
            }
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("facility_availability", "facility_closures"));
		$this->iDataSource->addColumnControl("detailed_description", "wysiwyg", "true");
		$this->iDataSource->addColumnControl("reservation_start", "data_type", "select");
		$this->iDataSource->addColumnControl("reservation_start", "help_label", "Always start reservations at this time");
		$this->iDataSource->addColumnControl("reservation_start", "choices", array("0" => "Top of the hour", "15" => "Quarter After", "30" => "Half Past", "45" => "Quarter Til"));
		$this->iDataSource->addColumnControl("link_url","help_label","For an external link");

		$this->iDataSource->addColumnControl("facility_prices", "data_type", "custom");
		$this->iDataSource->addColumnControl("facility_prices", "form_label", "Extended Prices");
		$this->iDataSource->addColumnControl("facility_prices", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("facility_prices", "list_table", "facility_prices");
		$this->iDataSource->addColumnControl("facility_prices", "list_table_controls", array("weekday"=>array("data_type"=>"select","empty_text"=>"[Any]","choices"=>array("0"=>"Sunday","1"=>"Monday","2"=>"Tuesday","3"=>"Wednesday","4"=>"Thursday","5"=>"Friday","6"=>"Saturday"))));

		$this->iDataSource->addColumnControl("cost_per_hour", "default_value", "0.00");
		$this->iDataSource->addColumnControl("cost_per_day", "default_value", "0.00");

		$this->iDataSource->addColumnControl("facility_tag_links", "data_type", "custom");
		$this->iDataSource->addColumnControl("facility_tag_links", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("facility_tag_links", "links_table", "facility_tag_links");
		$this->iDataSource->addColumnControl("facility_tag_links", "control_table", "facility_tags");

        if ($_GET['url_page'] == "show" && $_GET['subaction'] == "duplicate" && $GLOBALS['gPermissionLevel'] > _READONLY) {
            $facilityId = getFieldFromId("facility_id", "facilities", "facility_id", $_GET['primary_id'], "client_id is not null");
            if (empty($facilityId)) {
                return;
            }
            $resultSet = executeQuery("select * from facilities where facility_id = ?", $facilityId);
            $facilityRow = getNextRow($resultSet);
            $queryString = "";
            foreach ($facilityRow as $fieldName => $fieldData) {
                if (empty($queryString)) {
                    $facilityRow[$fieldName] = "";
                }
                if ($fieldName == "client_id") {
                    $facilityRow[$fieldName] = $GLOBALS['gClientId'];
                }
                $queryString .= (empty($queryString) ? "" : ",") . "?";
            }
            $newFacilityId = "";
            $facilityRow['description'] .= " Copy";
            while (empty($newFacilityId)) {
                $resultSet = executeQuery("insert into facilities values (" . $queryString . ")", $facilityRow);
                $newFacilityId = $resultSet['insert_id'];
            }
            $_GET['primary_id'] = $newFacilityId;
            $subTables = array("facility_availability","facility_closures","facility_group_contents","facility_notifications","facility_prices","facility_tag_links");
            foreach ($subTables as $tableName) {
                $resultSet = executeQuery("select * from " . $tableName . " where facility_id = ?", $facilityId);
                while ($row = getNextRow($resultSet)) {
                    $queryString = "";
                    foreach ($row as $fieldName => $fieldData) {
                        if (empty($queryString)) {
                            $row[$fieldName] = "";
                        }
                        $queryString .= (empty($queryString) ? "" : ",") . "?";
                    }
                    $row['facility_id'] = $newFacilityId;
                    executeQuery("insert into " . $tableName . " values (" . $queryString . ")", $row);
                }
            }
        }
	}

	function availabilityHours() {
		?>
        <table class="grid-table">
            <tr>
                <th id="all_available">Hour</th>
                <th class="weekday" data-weekday="0">Sunday</th>
                <th class="weekday" data-weekday="1">Monday</th>
                <th class="weekday" data-weekday="2">Tuesday</th>
                <th class="weekday" data-weekday="3">Wednesday</th>
                <th class="weekday" data-weekday="4">Thursday</th>
                <th class="weekday" data-weekday="5">Friday</th>
                <th class="weekday" data-weekday="6">Saturday</th>
            </tr>
			<?php
			for ($hour = 0; $hour < 24; $hour++) {
				$displayHour = ($hour == 0 ? "12 midnight" : ($hour > 12 ? ($hour - 12) . " pm" : ($hour == 12 ? $hour . " noon" : $hour . " am")));
				?>
                <tr>
                    <th class="hour" data-hour="<?= $hour ?>"><?= $displayHour ?></th>
					<?php for ($weekday = 0; $weekday < 7; $weekday++) { ?>
                        <td class="align-center"><input type="checkbox"<?= ($GLOBALS['gPermissionLevel'] < 2 ? "disabled='disabled' " : "") ?> id="available_<?= $weekday ?>_<?= $hour ?>" name="available_<?= $weekday ?>_<?= $hour ?>" value="1"/></td>
					<?php } ?>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
	}

	function internalCSS() {
		?>
        <style>
			#facility_calendar {
				border: 1px solid rgb(200, 200, 200);
				padding: 20px;
			}
        </style>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            <?php
            if ($GLOBALS['gPermissionLevel'] > _READONLY) {
            ?>
            $(document).on("tap click", "#_duplicate_button", function () {
                const $primaryId = $("#primary_id");
                if (!empty($primaryId.val())) {
                    if (changesMade()) {
                        askAboutChanges(function () {
                            $('body').data('just_saved', 'true');
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $primaryId.val();
                        });
                    } else {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_page=show&subaction=duplicate&primary_id=" + $primaryId.val();
                    }
                }
                return false;
            });
            <?php } ?>
            $("#all_available").click(function () {
                $("input[type=checkbox][id^=available_]").prop("checked", !$("#available_0_0").is(":checked"));
            });
            $(".weekday").click(function () {
                $("input[type=checkbox][id^=available_" + $(this).data("weekday") + "]").prop("checked", !$("#available_" + $(this).data("weekday") + "_0").is(":checked"));
            });
            $(".hour").click(function () {
                $("input[type=checkbox][id^=available_]").filter("input[type=checkbox][id$=_" + $(this).data("hour") + "]").prop("checked", !$("#available_0_" + $(this).data("hour")).is(":checked"));
            });
            $('.tabbed-form').tabs({
                activate: function (event, ui) {
                    if (ui.newTab.index() === 3) {
                        initializeCalendar();
                    }
                }
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
                    height: 600,
                    defaultView: viewName,
                    allDayDefault: false,
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
                    editable: false
                });
            }

            function afterGetRecord() {
                initializeCalendar();
                <?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                if (empty($("#primary_id").val())) {
                    disableButtons($("#_duplicate_button"));
                } else {
                    enableButtons($("#_duplicate_button"));
                }
                <?php } ?>
            }
        </script>
		<?php
	}

	function afterGetRecord(&$returnArray) {
		$resultSet = executeQuery("select * from facility_availability where facility_id = ?", $returnArray['primary_id']);
		while ($row = getNextRow($resultSet)) {
			$returnArray['available_' . $row['weekday'] . "_" . round($row['hour'], 0)] = array("data_value" => "1", "crc_value" => getCrcValue("1"));
		}
		for ($hour = 0; $hour < 24; $hour++) {
			for ($weekday = 0; $weekday < 7; $weekday++) {
				$fieldName = "available_" . $weekday . "_" . $hour;
				if (!array_key_exists($fieldName, $returnArray)) {
					$returnArray[$fieldName] = array("data_value" => "0", "crc_value" => getCrcValue("0"));
				}
			}
		}
		return true;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$resultSet = executeQuery("delete from facility_availability where facility_id = ?", $nameValues['primary_id']);
		foreach ($nameValues as $fieldName => $fieldValue) {
			if (!empty($fieldValue) && substr($fieldName, 0, strlen("available_")) == "available_") {
				$parts = explode("_", $fieldName);
				$weekday = $parts[1];
				$hour = $parts[2];
				$resultSet = executeQuery("insert into facility_availability (facility_id,weekday,hour) values (?,?,?)",
					$nameValues['primary_id'], $weekday, $hour);
				if (!empty($resultSet['sql_error'])) {
					return getSystemMessage("basic", $resultSet['sql_error']);
				}
			}
		}
		return true;
	}

	function facilityCalendar() {
		?>
        <div id="facility_calendar"></div>
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
		}
	}
}

$pageObject = new FacilitiesMaintenancePage("facilities");
$pageObject->displayPage();
