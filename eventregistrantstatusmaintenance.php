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

$GLOBALS['gPageCode'] = "EVENTREGISTRANTSTATUSMAINT";
require_once "shared/startup.inc";

class EventRegistrantStatusMaintenancePage extends Page {

	function setup() {
		executeQuery("insert ignore into event_attendance_statuses (client_id,event_attendance_status_code,description,incomplete) values (?,?,?,0)",$GLOBALS['gClientId'],'COMPLETED',"Successfully Completed");
		executeQuery("insert ignore into event_attendance_statuses (client_id,event_attendance_status_code,description,incomplete) values (?,?,?,1)",$GLOBALS['gClientId'],'INCOMPLETE',"Did not successfully complete");
		executeQuery("insert ignore into event_attendance_statuses (client_id,event_attendance_status_code,description,incomplete) values (?,?,?,1)",$GLOBALS['gClientId'],'NO_SHOW',"Did not attend");
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$filters = array();
            $filters['today_only'] = array("form_label" => "Only show events ending today", "where" => "end_date = current_date or (end_date is null and start_date = current_date)", "data_type" => "tinyint", "conjunction" => "and");
            $filters['past_30_days'] = array("form_label" => "Only show past 30 days", "where" => "start_date > date_sub(current_date,interval 30 day)", "data_type" => "tinyint", "set_default" => true);
			$filters['hide_upcoming'] = array("form_label" => "Hide upcoming events", "where" => "start_date <= current_date", "data_type" => "tinyint", "set_default" => true);
			$filters['hide_finalized'] = array("form_label" => "Hide Finalized Events", "where" => "finalize = 0", "data_type" => "tinyint", "set_default" => true);
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
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add", "delete"));

            if (canAccessPageCode("EVENTREGISTRANTSTATUSREPORT")) {
                $this->iTemplateObject->getTableEditorObject()->addAdditionalButtons(array("print_roster" => array("label" => getLanguageText("Print Roster"))));
            }
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("description", "readonly", true);
		$this->iDataSource->addColumnControl("start_date", "readonly", true);
        $limitDays = getPageTextChunk("FUTURE_EVENTS_DAY_LIMIT");
        if(empty($limitDays) || !is_numeric($limitDays) || $limitDays < 0) {
            $limitDays = 7;
        }
        $this->iDataSource->setFilterWhere("start_date <= date_add(current_date,interval " . $limitDays ." day)");
		$this->iDataSource->addColumnControl("set_check_in", "data_type", "hidden");
		$this->iDataSource->addColumnControl("event_registrants", "data_type", "custom");
		$this->iDataSource->addColumnControl("event_registrants", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("event_registrants", "list_table", "event_registrants");
		$this->iDataSource->addColumnControl("event_registrants", "no_add", "true");
		$this->iDataSource->addColumnControl("event_registrants", "no_delete", "true");
		$this->iDataSource->addColumnControl("event_registrants", "column_list", "contact_id,check_in_time,order_id,event_attendance_status_id,notes");
		$this->iDataSource->addColumnControl("event_registrants", "list_table_controls", array("check_in_time"=>array("classes"=>"check-in-time"),
			"order_id" => array("subtype" => "int", "readonly" => true, "classes" => "order-id"),
			"event_attendance_status_id" => array("classes" => "event-attendance-status-id"),
            "contact_id" => array("inline-width" => "400px", "inline-max-width" => "400px", "readonly" => true, "cell_classes" => "contact-id", "classes" => "contact-picker-control")));

        $this->iDataSource->addColumnControl("event_id_display", "data_type", "int");
        $this->iDataSource->addColumnControl("event_id_display", "readonly", true);
        $this->iDataSource->addColumnControl("event_id_display", "form_label", "Event ID");
	}

	function onLoadJavascript() {
	    $eventAttendanceStatusId = getFieldFromId("event_attendance_status_id","event_attendance_statuses","event_attendance_status_code","COMPLETED");
		?>
        <script>
	        <?php if (canAccessPageCode("CONTACTMAINT")) { ?>
            $(document).on("click", ".contact-id", function () {
                const contactId = $(this).find(".contact-picker-value").val();
                window.open("/contactmaintenance.php?url_page=show&clear_filter=true&primary_id=" + contactId);
            });
	        <?php } ?>
	        <?php if (canAccessPageCode("ORDERDASHBOARD")) { ?>
            $(document).on("click", ".order-id", function () {
                const orderId = $(this).val();
                if (!empty(orderId)) {
                    window.open("/orderdashboard.php?url_page=show&clear_filter=true&primary_id=" + orderId);
                }
            });
	        <?php } ?>
            $(document).on("click",".check-in-time",function() {
                let currentDate = new Date();
                let options = {
                    year: "numeric",
                    month: "numeric",
                    day: "numeric",
                    hour: "numeric",
                    minute: "2-digit",
                    second: "2-digit",
                };
                let datetimeNow = new Intl.DateTimeFormat("default", options).format(currentDate);
                $(this).val(datetimeNow);
                const primaryId = $(this).closest(".editable-list-data-row").find(".editable-list-primary-id").val();
                let currentValue = $("#set_check_in").val();
                currentValue += (empty(currentValue) ? "" : ",") + primaryId;
                $("#set_check_in").val(currentValue);
                if($("#no_status").prop("checked") == false) {
                    $(this).closest("tr").find(".event-attendance-status-id").val("<?= $eventAttendanceStatusId ?>");
                }
            });
            $(document).on("change","#no_status", function(){
                if($("#no_status").prop("checked") == true) {
                    $(".event-attendance-status-id").val('');
                }
            });

            $(document).on("click", "#_print_roster_button", function () {
                window.open("/eventregistrantstatusreport.php?&event_id=" + $("#primary_id").val());
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            td.contact-id, .contact-picker-control,.order-id,.check-in-time {
                cursor: pointer;
            }
        </style>
		<?php
	}

	function afterSaveDone($nameValues) {
	    if (!empty($nameValues['set_check_in'])) {
	        $registrantIds = explode(",",$nameValues['set_check_in']);
	        foreach ($registrantIds as $eventRegistrantId) {
	            executeQuery("update event_registrants set check_in_time = now() where event_registrant_id = ? and event_id = ? and check_in_time is null",$eventRegistrantId,$nameValues['primary_id']);
	        }
	    }
		$startDate = getFieldFromId("start_date","events","event_id",$nameValues['primary_id']);
        $eventRow = getRowFromId("events", "event_id", $nameValues['primary_id']);
		$eventTypeId = $eventRow['event_type_id'];
		if (!empty($nameValues['finalize'])) {
			if (!empty($eventTypeId)) {
				$eventAttendanceStatuses = array();
				$resultSet = executeQuery("select * from event_attendance_statuses where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$eventAttendanceStatuses[$row['event_attendance_status_id']] = $row;
				}
				$resultSet = executeQuery("select * from event_registrants where event_attendance_status_id is not null and event_id = ?", $nameValues['primary_id']);
				while ($row = getNextRow($resultSet)) {
					$contactEventTypeId = getFieldFromId("contact_event_type_id", "contact_event_types", "event_type_id", $eventTypeId,
						"contact_id = ? and date_completed >= ? and failed = ?", $row['contact_id'], $startDate, $eventAttendanceStatuses[$row['event_attendance_status_id']]['incomplete']);
					if (empty($contactEventTypeId)) {
                        $fileId = $row['file_id'];
                        if (empty($fileId)) {
                            $fileId = Events::generateSingleEventCertificate(["event_registrant_row" => $row, "event_row" => $eventRow,
                                "event_attendance_status_rows" => $eventAttendanceStatuses, "send_email" => true]);
                        }
						executeQuery("insert into contact_event_types (contact_id,event_type_id,date_completed,file_id,failed) values (?,?,?,?,?)",
                            $row['contact_id'], $eventTypeId, $startDate, $fileId, $eventAttendanceStatuses[$row['event_attendance_status_id']]['incomplete']);
						Events::createCertifications($row['contact_id']);
					}
				}
			}
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
        $dontSetStatus = empty($returnArray['finalize']['data_value']) && !empty(getPreference("NO_DEFAULT_EVENT_REGISTRANT_STATUS"));
		if ($dontSetStatus) {
            $returnArray['no_status']['data_value'] = true;
        } else {
            $noneSet = true;
            foreach ($returnArray['event_registrants'] as $index => $thisRegistrant) {
                if (!empty($thisRegistrant['event_attendance_status_id']['data_value'])) {
                    $noneSet = false;
                }
            }
            $noShowStatusId = getFieldFromId("event_attendance_status_id", "event_attendance_statuses", "event_attendance_status_code", "NO_SHOW");
            $completedStatusId = getFieldFromId("event_attendance_status_id", "event_attendance_statuses", "event_attendance_status_code", "COMPLETED");
            if ($noneSet) {
                foreach ($returnArray['event_registrants'] as $index => $thisRegistrant) {
                    if (empty($thisRegistrant['check_in_time']['data_value'])) {
                        $returnArray['event_registrants'][$index]['event_attendance_status_id']['data_value'] = $noShowStatusId;
                    } else {
                        $returnArray['event_registrants'][$index]['event_attendance_status_id']['data_value'] = $completedStatusId;
                    }
                }
            }
        }
		$returnArray['event_id_display'] = array("data_value" => $returnArray['primary_id']['data_value']);
	}
}

$pageObject = new EventRegistrantStatusMaintenancePage("events");
$pageObject->displayPage();
