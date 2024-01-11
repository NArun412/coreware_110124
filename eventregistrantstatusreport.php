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

$GLOBALS['gPageCode'] = "EVENTREGISTRANTSTATUSREPORT";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 300000;
ini_set("memory_limit", "4096M");

class EventRegistrantStatusReportPage extends Page {

    function mainContent() {
        $eventRow = getRowFromId("events", "event_id", $_GET['event_id'],
            "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
        if (!empty($eventRow)) {
            $resultSet = executeQuery("select count(*) from event_registrants where event_id = ?", $eventRow['event_id']);
            if ($row = getNextRow($resultSet)) {
                $attendeeCount = $row['count(*)'];
            }
        }
        if (empty($eventRow) || empty($attendeeCount)) {
            ?>
            <p id="no_attendee_found">No event registrant available.</p>
            <?php
            return true;
        }
        $resultSet = executeReadQuery("select contact_id, event_registrant_id, event_id, registration_time, check_in_time, order_id, event_attendance_status_id, file_id,"
            . " first_name, middle_name, last_name, business_name, address_1, address_2, city, state, postal_code, country_id, email_address from event_registrants join contacts using (contact_id)"
            . " where event_id = ? order by event_registrant_id", $eventRow['event_id']);
        $customFields = CustomField::getCustomFields("contacts");

        if (function_exists("_localGetEventReportHeader")) {
            $eventReportHeader = _localGetEventReportHeader($eventRow);
        } else {
			$instructorName = empty($eventRow['class_instructor_id']) ? "" : getDisplayName(getFieldFromId("contact_id", "class_instructors",
                "class_instructor_id", $eventRow['class_instructor_id']));

			$eventFacilitySet = executeReadQuery("select date_needed, facility_id, min(hour) from event_facilities where event_id = ?"
				. " group by date_needed, facility_id order by date_needed desc", $eventRow['event_id']);
			if ($eventFacilityRow = getNextRow($eventFacilitySet)) {
				$eventDateFormat = getPageTextChunk("EVENT_DATE_FORMAT") ?: "l, M j, Y";
				$eventDate = date($eventDateFormat, strtotime($eventFacilityRow['date_needed']));
				$eventTime = Events::getDisplayTime($eventFacilityRow['min(hour)']);
				$facilityName = getFieldFromId("description", "facilities", "facility_id", $eventFacilityRow['facility_id']);
			}
			ob_start();
			?>
            <h3><?= $eventRow['description'] ?></h3>
            <h4>
				<?= empty($eventDate) ? "" : "<span class='event-date'>" . $eventDate . "</span>" ?>
				<?= empty($eventTime) ? "" : "<span class='event-time'>" . $eventTime . "</span>" ?>
				<?= empty($facilityName) ? "" : "<span class='facility-name'>" . $facilityName . "</span>" ?>
				<?= empty($instructorName) ? "" : "<span class='instructor-name'>" . $instructorName . "</span>" ?>
            </h4>
			<?php
			$eventReportHeader = ob_get_clean();
		}
        ?>

        <div id="_button_row">
            <button id="printable_button">Printable Report</button>
            <button id="pdf_button">Download PDF</button>
        </div>

        <h1 id="_report_title">Class Roster</h1>
        <div id="_report_content">
			<?= $eventReportHeader ?>
            <table class="grid-table">
                <?php
                    // Header
                    $headersElement = $this->getPageTextChunk("EVENT_REGISTRANT_STATUS_REPORT_HEADER");
                    if (empty($headersElement)) {
                        $headersElement = $this->getFragment("EVENT_REGISTRANT_STATUS_REPORT_HEADER");
                    }
                    if (empty($headersElement)) {
                        ob_start();
                        ?>
                        <tr>
                            <th>Name</th>
                            <th>Contact Info</th>
                            <th>Notes</th>
                            <th>Active CC?</th>
                        </tr>
                        <?php
                        $headersElement = ob_get_clean();
                    }
                    echo $headersElement;

                    // Content
                    while ($row = getNextRow($resultSet)) {
                        $substitutions = $row;
                        $substitutions['full_name'] = getDisplayName($row['contact_id']);

                        $cityState = empty($row['city']) ? "" : $row['city'];
                        if (!empty($row['state'])) {
                            $cityState .= (empty($cityState) ? "" : ", ") . $row['state'];
                        }
                        $substitutions['city_state'] = $cityState;
                        $substitutions['phone_number'] = Contact::getContactPhoneNumber($row['contact_id']);

                        foreach ($customFields as $customField) {
                            $customFieldCode = $customField['custom_field_code'];
                            $substitutions['custom_field_' . strtolower($customFieldCode)] = CustomField::getCustomFieldData($row['contact_id'], $customFieldCode);
                        }

                        if (function_exists("_localServerGetAdditionalContactInfo")) {
                            $substitutions = array_merge($substitutions, _localServerGetAdditionalContactInfo($row['contact_id']));
                        }

                        $activeAccount = getRowFromId("accounts", "contact_id", $row['contact_id'], "inactive = 0 and account_token is not null");
                        if (!empty($activeAccount)) {
                            $substitutions['account_id'] = $activeAccount['account_id'];
                            $substitutions['account_label'] = $activeAccount['account_label'];
                            $substitutions['account_number'] = $activeAccount['account_number'];
                            $substitutions['account_expiration_date'] = $activeAccount['expiration_date'];
                        }

                        $eventRegistrantElement = $this->getPageTextChunk("EVENT_REGISTRANT_STATUS_REPORT_ROW", $substitutions);
                        if (empty($eventRegistrantElement)) {
                            $eventRegistrantElement = $this->getFragment("EVENT_REGISTRANT_STATUS_REPORT_ROW", $substitutions);
                        }
                        if (empty($eventRegistrantElement)) {
                            ob_start();
                            ?>
                            <tr class="event-registrant">
                                <td>%full_name%</td>
                                <td>
                                    %if_has_value:email_address%
                                        %email_address% <br/>
                                    %endif%
                                    %if_has_value:city_state%
                                        %city_state% <br/>
                                    %endif%
                                    %if_has_value:phone_number%
                                        %phone_number% <br/>
                                    %endif%
                                </td>
                                <td></td>
                                %if_has_value:account_id%
                                    <td>Yes - %account_number%</td>
                                %else%
                                    <td class="disabled">
                                        No card on file
                                    </td>
                                %endif%
                            </tr>
                            <?php
                            $eventRegistrantElement = ob_get_clean();
                        }
                        echo PlaceHolders::massageContent($eventRegistrantElement, $substitutions);
                    }
                ?>
            </table>
        </div>

        <div id="_pdf_data" class="hidden">
            <form id="_pdf_form"></form>
        </div>
        <?php
    }

    function onLoadJavascript() {
        ?>
        <script>
            $(document).on("tap click", "#printable_button", function () {
                window.open("/printable.html");
                return false;
            });
            $(document).on("tap click", "#pdf_button", function () {
                $("#_pdf_form").html("")
                    .append($("<input>").attr("type", "hidden").attr("name", "report_title").val($("#_report_title").html()))
                    .append($("<input>").attr("type", "hidden").attr("name", "report_content").val($("#_report_content").html()))
                    .append($("<input>").attr("type", "hidden").attr("name", "printable_style").val($("#_printable_style").html()))
                    .append($("<input>").attr("type", "hidden").attr("name", "filename").val("classroster.pdf"))
                    .attr("action", "/reportpdf.php")
                    .attr("method", "POST")
                    .submit();
                return false;
            });
        </script>
        <?php
    }

    function internalCSS() {
        ?>
        <style>
            #_button_row {
                margin-bottom: 20px;
            }
        </style>
        <style id="_printable_style">
            #_report_content h4 span {
                display: inline-block;
                margin-right: 20px;
                margin-bottom: 20px;
            }

            #_report_content table.grid-table td.disabled {
                background-color: rgb(200, 200, 200);
            }
        </style>
        <?php
    }

}

$pageObject = new EventRegistrantStatusReportPage();
$pageObject->displayPage();
