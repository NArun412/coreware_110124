<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "MOVEREGISTRANTS";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 120000;

class MoveRegistrantsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_registrants":
				$eventId = getFieldFromId("event_id", "events", "event_id", $_GET['event_id']);
				if (empty($eventId)) {
					$returnArray['error_message'] = "Event not found";
					ajaxResponse($returnArray);
					break;
				}
				$returnArray['registrant_list'] = "";
				$resultSet = executeQuery("select * from event_registrants where event_id = ? order by registration_time", $eventId);
				$registrantCount = 0;
				while ($row = getNextRow($resultSet)) {
					$registrantCount++;
					$fullName = getDisplayName($row['contact_id']);
					$returnArray['registrant_list'] .= "<p><input type='checkbox' id='event_registrant_id_" . $registrantCount . "' name='event_registrant_id_" . $registrantCount . "' value='" .
						$row['event_registrant_id'] . "'><label class='checkbox-label' for='event_registrant_id_" . $registrantCount . "'>" . $fullName . ", Contact ID " . $row['contact_id'] .
						(empty($row['order_id']) ? "" : ", Order ID " . $row['order_id']) . "</label></p>";
				}
				ajaxResponse($returnArray);
				break;
			case "move_registrants";
                $cancelOnly = !empty($_POST['cancel_only']);
                $canceledEventRow = getRowFromId("events", "event_id", $_POST['canceled_event_id']);
                $eventRow = getRowFromId("events", "event_id", $_POST['event_id']);
				$canceledEventId = $canceledEventRow['event_id'];
				$canceledFacilityId = getFieldFromId("facility_id", "event_facilities", "event_id", $canceledEventId);
				$eventId = $eventRow['event_id'];
				$facilityId = getFieldFromId("facility_id", "event_facilities", "event_id", $eventId);
				if (empty($canceledEventId) || (!$cancelOnly && empty($eventId))) {
					$returnArray['error_message'] = "Event not found";
					ajaxResponse($returnArray);
					break;
				}
				if ($canceledEventId == $eventId) {
					$returnArray['error_message'] = "Events are the same";
					ajaxResponse($returnArray);
					break;
				}
                $canceledEventString = $canceledEventRow['description'] . " on " . $canceledEventRow['start_date'] .
                    (empty($canceledEventRow['location_id']) ? "" : " at " . getFieldFromId("description", "locations", "location_id", $canceledEventRow['location_id']));
                $eventString = $eventRow['description'] . " on " . $eventRow['start_date'] .
                    (empty($eventRow['location_id']) ? "" : " at " . getFieldFromId("description", "locations", "location_id", $eventRow['location_id']));
				$registrantIdSet = executeQuery("select event_registrant_id, contact_id from event_registrants where event_id = ?", $canceledEventId);
				$registrants = array();
				while ($row = getNextRow($registrantIdSet)) {
					if (empty($_POST['move_all'])) {
						$foundRegistrant = false;
						foreach ($_POST as $fieldName => $fieldData) {
							if (startsWith($fieldName, "event_registrant_id_")) {
								if ($fieldData == $row['event_registrant_id']) {
									$foundRegistrant = true;
								}
							}
						}
						if (!$foundRegistrant) {
							continue;
						}
					}
					$registrants[] = $row;
				}
                if ($cancelOnly && !empty($registrants)) {
                    $returnArray['error_message'] = "Event to be cancelled still has registrants";
                    ajaxResponse($returnArray);
                    break;
                }

                $GLOBALS['gPrimaryDatabase']->startTransaction();
				// Move Registrants
				$registrantsDataTable = new DataTable("event_registrants");
				$registrantsDataTable->setSaveOnlyPresent(true);
                $GLOBALS['gChangeLogNotes'] = "Registrant moved from " . $canceledEventString . " to " . $eventString;
				foreach ($registrants as $thisRegistrant) {
					if (!$registrantsDataTable->saveRecord(array("name_values" => array("event_id" => $eventId), "primary_id" => $thisRegistrant['event_registrant_id']))) {
						$returnArray['error_message'] = $registrantsDataTable->getErrorMessage();
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
                $GLOBALS['gChangeLogNotes'] = "";

                // Make event and registration product inactive
				if (!empty($_POST['mark_product_inactive'])) {
					$registrationProductId = getFieldFromId("product_id", "events", "event_id", $canceledEventId);
					if (!empty($registrationProductId)) {
						$productsDataTable = new DataTable("products");
						$productsDataTable->setSaveOnlyPresent(true);
						if (!$productsDataTable->saveRecord(array("name_values" => array("inactive" => 1), "primary_id" => $registrationProductId))) {
							$returnArray['error_message'] = $productsDataTable->getErrorMessage();
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
                    $eventsDataTable = new DataTable('events');
                    $eventsDataTable->setSaveOnlyPresent(true);
                    $eventsDataTable->saveRecord(array("name_values" => array("inactive" => 1), "primary_id" => $canceledEventId));
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$registrantCount = count($registrants);
                if(!$cancelOnly) {
                    $returnArray['results'] = count($registrants) . " Registrant" . (count($registrants) == 1 ? "" : "s") . " moved successfully.";
                } else {
                    $returnArray['results'] = "Event canceled successfully (no registrants to move).";
                }

                $domainName = getDomainName();
                $substitutions = array(
                    'canceled_event' => $canceledEventRow['description'],
                    'old_event' => $canceledEventRow['description'],
                    'canceled_event_facility' => getFieldFromId("description", "facilities", "facility_id", $canceledFacilityId),
                    'old_event_facility' => getFieldFromId("description", "facilities", "facility_id", $canceledFacilityId),
                    'canceled_event_date' => date("m/d/Y", strtotime($canceledEventRow['start_date'])),
                    'old_event_date' => date("m/d/Y", strtotime($canceledEventRow['start_date'])),
                    'new_event' => $eventRow['description'],
                    'new_event_date' => date("m/d/Y", strtotime($eventRow['start_date'])),
                    'new_event_facility' => getFieldFromId("description", "facilities", "facility_id", $facilityId),
                    'cancellation_link' => $domainName . "/my-account?url_page=event_registrations",
                    'registrant_count' => $registrantCount);

                $adminEmailAddresses = array();
				if (!empty($_POST['send_admin_emails'])) {
					$resultSet = executeQuery("select * from facility_notifications where facility_id in (?,?)", $canceledFacilityId, $facilityId);
					while ($row = getNextRow($resultSet)) {
						if (!in_array($row['email_address'], $adminEmailAddresses)) {
							$adminEmailAddresses[] = $row['email_address'];
						}
					}

					if (!empty($adminEmailAddresses)) {
                        if(!$cancelOnly) {
                            $emailId = getFieldFromId("email_id", "emails", "email_code", "MOVE_REGISTRANTS_FACILITY_EMAIL",  "inactive = 0");
                            $subject = "Registrants moved";
                            $body = "<p>%registrant_count% registrant(s) from event '%canceled_event' at %canceled_event_facility% on %canceled_event_date%" .
                                " have been moved to event '%new_event%' at %new_event_facility% on %new_event_date%.</p>";
                        } else {
                            $emailId = getFieldFromId("email_id", "emails", "email_code", "EVENT_CANCELED_FACILITY_EMAIL",  "inactive = 0");
                            $subject = "Event canceled";
                            $body = "<p>Event '%canceled_event' at %canceled_event_facility% on %canceled_event_date% has been cancelled. There were no registrants.</p>";
                        }
						sendEmail(array("email_id"=>$emailId, "subject" => $subject, "body" => $body, "email_address" => $adminEmailAddresses, "substitutions"=>$substitutions));
					}
				}

				$emailCount = 0;
				if (!empty($_POST['send_emails'])) {
					// Email registrants
					$emailId = getFieldFromId("email_id", "emails", "email_code", "MOVE_REGISTRANTS_CANCELLATION_EMAIL",  "inactive = 0");
					$subject = "Your event has been rescheduled";
					$body = "The event %canceled_event% scheduled for %canceled_event_date% for which you registered has been rescheduled.  Your registration has been changed "
						. " to %new_event% on %new_event_date%.  For more information about your registration, click <a href='%cancellation_link%'>here</a>.";
					foreach ($registrants as $thisRegistrant) {
						$emailAddress = getFieldFromId('email_address', 'contacts', 'contact_id', $thisRegistrant['contact_id']);
						if (!empty($emailAddress)) {
							sendEmail(array("email_id" => $emailId, "subject" => $subject, "body" => $body, "email_address" => $emailAddress, "substitutions" => $substitutions, "contact_id" => $thisRegistrant['contact_id']));
							$emailCount++;
						}
					}
				}
				if (!empty($eventRow['product_id'])) {
					$attendeeCounts = Events::getAttendeeCounts($eventRow['event_id']);
					if ($attendeeCounts['registrants'] >= $attendeeCounts['attendees']) {
						executeQuery("update products set non_inventory_item = 0 where product_id = ?", $eventRow['product_id']);
						executeQuery("update product_inventories set quantity = 0 where product_id = ?", $eventRow['product_id']);
					} else {
						executeQuery("update products set non_inventory_item = 1 where product_id = ?", $eventRow['product_id']);
					}
				}
				Events::notifyCRM($eventId);
				Events::notifyCRM($canceledEventId);
				if ($emailCount > 0) {
					$returnArray['results'] .= " Emails sent to " . $emailCount . " registrants.";
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#move_all", function () {
                if ($(this).prop("checked")) {
                    $("#registrant_list").addClass("hidden");
                } else {
                    $("#registrant_list").removeClass("hidden");
                }
            })
            $(document).on("change", "#canceled_event_id", function () {
                $("#registrant_list").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_registrants&event_id=" + $(this).val(), function(returnArray) {
                        if ("registrant_list" in returnArray) {
                            $("#registrant_list").html(returnArray['registrant_list']);
                            if(!empty(returnArray['registrant_list'])) {
                                $("#_cancel_only_row").addClass("hidden");
                                $("#cancel_only").prop("checked", false);
                            } else {
                                $("#_cancel_only_row").removeClass("hidden");
                            }
                        }
                    });
                }
            });
            $(document).on("change", "#cancel_only", function () {
                if($("#cancel_only").prop("checked")) {
                    $("#event_id").val("");
                    $("#event_id_autocomplete_text").val("");
                    $("#_event_id_row").addClass("hidden");
                } else {
                    $("#_event_id_row").removeClass("hidden");
                }
            });
            $(document).on("click", "#move_registrants", function () {
                $("#results").html("");
                if ($("#_edit_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=move_registrants", $("#_edit_form").serialize(), function(returnArray) {
                        if ("results" in returnArray) {
                            $("#results").html(returnArray['results']);
                            $("#canceled_event_id").val("");
                            $("#event_id").val("");
                            $("#canceled_event_id_autocomplete_text").val("");
                            $("#event_id_autocomplete_text").val("");
                        }
                    });
                }
            });
        </script>
		<?php
	}

	function mainContent() {
		?>
        <h2>Move one or more registrants from one event to another event</h2>
        <p class='red-text highlighted-text'>Moving registrants is PERMANENT and CANNOT be reversed. Be sure that you understand all of the below.</p>
        <p>Move will be performed as follows:</p>
        <ul>
            <li>All chosen registrants for the old event will be moved to the target event.</li>
            <li>If indicated, the registration product for the old event will be marked inactive.</li>
            <li>All registrants that are moved will, optionally, receive an email informing them of the change and giving them the link to cancel their registration.</li>
        </ul>
        <p>To customize the email sent to registrants, update the email MOVE_REGISTRANTS_CANCELLATION_EMAIL. It includes the following substitutions:</p>
        <ul>
            <li>canceled_event/old_event</li>
            <li>canceled_event_date/old_event_date</li>
            <li>canceled_event_facility/old_event_facility</li>
            <li>new_event</li>
            <li>new_event_date</li>
            <li>new_event_facility</li>
            <li>cancellation_link</li>
            <li>registrant_count</li>
        </ul>
        <p>An email can also be sent to contacts for both facilities. To customize this email, update the email MOVE_REGISTRANTS_FACILITY_EMAIL. It uses the same substitutions.</p>

        <p class='error-message'></p>
        <p id="results"></p>

        <form id="_edit_form">
            <div class="basic-form-line" id="_canceled_event_id_row">
                <label for="canceled_event_id" class="required-label">OLD Event</label>
                <input type="hidden" id="canceled_event_id" name="canceled_event_id" value="">
                <input autocomplete="chrome-off" autocomplete="off" tabindex="10" class="autocomplete-field validate[required]" type="text" size="50" name="canceled_event_id_autocomplete_text" id="canceled_event_id_autocomplete_text" data-additional_filter="future" data-autocomplete_tag="events">
                <div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
            </div>

            <div id="registrant_list" class='hidden'></div>

            <div class="basic-form-line" id="_event_id_row">
                <label for="event_id" class="required-label">Event to receive registrants</label>
                <input type="hidden" id="event_id" name="event_id" value="">
                <input autocomplete="chrome-off" autocomplete="off" tabindex="10" class="autocomplete-field validate[required]" type="text" size="50" name="event_id_autocomplete_text" id="event_id_autocomplete_text" data-additional_filter="future" data-autocomplete_tag="events" data-conditional-required="!($('#cancel_only').prop('checked'))">
                <div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
            </div>

            <div class="basic-form-line" id="_move_all_row">
                <input type="checkbox" id="move_all" name="move_all" value="1" checked><label class="checkbox-label" for="move_all">Move all registrants (uncheck to choose from a list of registrants)</label>
                <div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
            </div>

            <div class="basic-form-line" id="_mark_product_inactive_row">
                <input type="checkbox" id="mark_product_inactive" name="mark_product_inactive" value="1" checked><label class="checkbox-label" for="mark_product_inactive">Mark the registration product for the OLD event inactive</label>
                <div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
            </div>

            <div class="basic-form-line" id="_send_emails_row">
                <input type="checkbox" id="send_emails" name="send_emails" value="1" checked><label class="checkbox-label" for="send_emails">Send an email to each moved registrant</label>
                <div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
            </div>

            <div class="basic-form-line" id="_send_admin_emails_row">
                <input type="checkbox" id="send_admin_emails" name="send_admin_emails" value="1" checked><label class="checkbox-label" for="send_admin_emails">Send notifications to facilities about moves</label>
                <div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
            </div>

            <div class="basic-form-line" id="_cancel_only_row">
                <input type="checkbox" id="cancel_only" name="cancel_only" value="1"><label class="checkbox-label" for="cancel_only">Old event has no registrants - Cancel</label>
                <div class='basic-form-line-messages'><span class="help-label">%help_label%</span><span class='field-error-text'></span></div>
            </div>
        </form>

        <p>
            <button id="move_registrants">Move Registrants</button>
        </p>
		<?php

		return true;
	}

	function internalCSS() {
		?>
        <style>
            input.autocomplete-field {
                width: 800px;
            }

            #_main_content ul {
                list-style: disc;
                margin: 20px 0 40px 30px;
            }

            #_main_content ul li {
                margin: 5px;
            }
        </style>
		<?php
	}
}

$pageObject = new MoveRegistrantsPage();
$pageObject->displayPage();
