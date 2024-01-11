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

$GLOBALS['gPageCode'] = "EVENTS";
require_once "shared/startup.inc";

class EventsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_events":
				$locationIds = array();
				if (!empty($_GET['location_ids'])) {
					foreach (explode(",", $_GET['location_ids']) as $thisLocationId) {
						$locationId = getFieldFromId("location_id", "locations", "location_id", $thisLocationId, "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
						if (!empty($locationId)) {
							$locationIds[] = $locationId;
						}
					}
				}
				if (empty($locationIds)) {
					$returnArray["events"] = array();
					ajaxResponse($returnArray);
					exit;
				}
				$returnArray = Events::getEvents($_GET);
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		$monthsToShow = (!is_numeric($_GET['months_to_show']) || empty($_GET['months_to_show']) ? 4 : $_GET['months_to_show']);
		echo $this->iPageData['content'];
		?>
        <div id="events_container">
            <div id="events_header">
                <h1>Available courses</h1>
                <div id="event_months">
					<?php
					$monthDateTime = new DateTime();
					$monthDateTime->setDate($monthDateTime->format('Y'), $monthDateTime->format('m'), 1);

					for ($months = 0; $months < $monthsToShow; $months++) {
						$year = date_format($monthDateTime, "Y");
						?>
                        <a class="event-month" data-month="<?= date_format($monthDateTime, "n") ?>" data-year="<?= $year ?>">
							<?= date_format($monthDateTime, "F") ?> <span><?= $year ?></span>
                        </a>
						<?php
						$monthDateTime->add(new DateInterval("P1M"));
					}
					?>
                </div>
            </div>

            <div id="events_content">
                <div id="events_filters">
                    <div class="filter-groups">
                        <div class="filter-group">
                            <h5>Locations</h5>

							<?php
							$resultSet = executeQuery("select * from locations where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and product_distributor_id is null order by sort_order,description", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								$checked = ($resultSet['row_count'] == 1 || $row['location_id'] == $_GET['location_id'] ? "checked" : "");
								$description = htmlText($row['description']);
								?>
                                <div class="event-filter">
                                    <input id="<?= $description ?>" value="<?= $row['location_id'] ?>" <?= $checked ?> type="checkbox" name="<?= $description ?>" class="location-filter">
                                    <label class="checkbox-label" for="<?= $description ?>"><?= $description ?></label>
                                </div>
								<?php
							}
							?>
                        </div>

                        <div class="filter-group">
                            <h5>Event types</h5>

							<?php
							$resultSet = executeQuery("select * from event_types where client_id = ? and hide_in_calendar = 0 and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								$checked = ($resultSet['row_count'] == 1 || $row['event_type_id'] == $_GET['event_type_id'] ? "checked" : "");
								$description = htmlText($row['description']);
								?>
                                <div class="event-filter">
                                    <input id="<?= $description ?>" value="<?= $row['event_type_id'] ?>" <?= $checked ?> type="checkbox" name="<?= $description ?>" class="event-type-filter">
                                    <label class="checkbox-label" for="<?= $description ?>"><?= $description ?></label>
                                </div>
								<?php
							}
							?>
                        </div>

                        <div class="filter-group">
                            <a id="clear_event_filters" class="hidden">Clear filters</a>
                        </div>
                    </div>
                </div>

                <div id="event_types">
					<?php
					$resultSet = executeQuery("select * from event_types where client_id = ? and hide_in_calendar = 0 and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$description = htmlText($row['description']);
						?>
                        <div class="event-type" data-event-type-id="<?= $row['event_type_id'] ?>" data-event-type-code="<?= $row['event_type_code'] ?>">
							<?php if (!empty($row['image_id'])) { ?>
                                <img alt="<?= $description ?>" src="<?= getImageFilename($row['image_id'], array("use_cdn" => true)) ?>"/>
							<?php } ?>

                            <div>
                                <h5 class="description"><?= $description ?></h5>
                                <p class="detailed-description"><?= $row['detailed_description'] ?></p>
                            </div>
                        </div>
						<?php
					}
					?>
                </div>

                <div id="events" class="hidden">
                    <div id="event_type_details">
                        <img alt="Event type" src="/images/empty.jpg"/>
                        <a id="events_back_link">
                            <i class="fa fa-arrow-circle-left" aria-hidden="true"></i> Back
                        </a>
                        <h2>Event type description</h2>
                        <p class="detailed-description">Event type detailed description</p>
                    </div>

                    <p id="events_empty_message" class="hidden">No events available.</p>
                </div>
            </div>
        </div>

		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            function clearEventFilters() {
                $('.location-filter').prop("checked", false);
                $('.event-type-filter').prop("checked", false);
                $(".event-month").removeClass("selected");

                $("#event_types").removeClass("hidden");
                $("#events").addClass("hidden");
                $("#clear_event_filters").addClass("hidden");
            }

            function getFilteredEvents() {
                const locationIds = $('.location-filter:checked').map((index, element) => element.value).get().join(',');
                const eventTypeIds = $('.event-type-filter:checked').map((index, element) => element.value).get().join(',');
                const selectedMonth = $(".event-month.selected");

                const monthsToShow = <?= (!is_numeric($_GET['months_to_show']) || empty($_GET['months_to_show']) ? 4 : $_GET['months_to_show']) ?>;
                const date = new Date();

                let startDate = new Date(date.getFullYear(), date.getMonth(), 1);
                let endDate = new Date(date.getFullYear(), date.getMonth() + monthsToShow, 0);

                if (selectedMonth.length) {
                    const month = selectedMonth.data("month");
                    const year = selectedMonth.data("year");
                    startDate = new Date(year, month - 1, 1);
                    endDate = new Date(year, month, 0);
                }

				<?php if (empty($_GET['show_past_events'])) { ?>
                startDate = startDate <= date ? date : startDate;
				<?php } ?>

                startDate = $.formatDate(startDate, "yyyy-MM-dd");
                endDate = $.formatDate(endDate, "yyyy-MM-dd");

                const url = "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_events"
                    + (empty(eventTypeIds) ? "" : `&event_type_ids=${eventTypeIds}`)
                    + (empty(locationIds) ? "" : `&location_ids=${locationIds}`)
                    + (empty(startDate) ? "" : `&start_date=${startDate}`)
                    + (empty(endDate) ? "" : `&end_date=${endDate}`);

                $("body").addClass("waiting-for-ajax");

                loadAjaxRequest(url, function(returnArray) {
                    $("#events .event, #events .event-date").remove();

                    // Group events by event ID, location and facility
                    const groupedEvents = new Map();
                    returnArray.events.forEach((event) => {
                        const key = $.formatDate(new Date(event.start.substr(0, 19)), "MM/dd/yyyy");
                        const collection = groupedEvents.get(key);
                        if (!collection) {
                            groupedEvents.set(key, [event]);
                        } else {
                            collection.push(event);
                        }
                    });

                    groupedEvents.forEach((eventGroup, date) => {
                        $("#events").append(`<h3 class="event-date">${date}</h3>`);

                        eventGroup.forEach(event => {
                            let productDetails = "";
                            if (event.product_id) {
                                if (event.product_completed) {
                                    productDetails = "<p class='event-completed'>This class is already completed.</p>";
                                } else if (event.spots_left <= 0) {
                                    productDetails = "<p class='event-full'>This class is full. <a href='#' class='add-to-wishlist' data-product_id='" + event.product_id + "'>Get on waiting list.</a></p>";
                                } else {
                                    productDetails = `<a class="button event-register" href="/product-details?id=${event.product_id}"><i class="fa fa-user-plus"></i> Register now</a>`
                                        + `<p>${event.spots_left} ${event.spots_left > 1 ? "spots" : "spot"} left!</p>`;
                                }
                            }

                            let facilities = "";
                            event.facilities.forEach(facility => {
                                const locationUrl = facility.location && facility.location.longitude && facility.location.latitude ?
                                    `https://www.google.com/maps/search/?api=1&query=${facility.location.latitude},${facility.location.longitude}` : null;
                                const locationLink = locationUrl ? `<a class="event-show-direction" href="${locationUrl}" target="_blank"><i class="fa fa-map-pin"></i> Show Directions</a>` : "";

                                facilities += `<div class="event-facility" data-facility_id="${facility.facility_id}">
                                        <p class="facility">${facility.description}</p>
                                        ${facility.location ? `<p class="event-location" data-location_id="${facility.location.location_id}">${facility.location.full_address}${locationLink}</p>` : ""}
                                    </div>`;
                            });

                            // Remove timezone so date displays are not client dependent
                            const startDate = new Date(event.start.substr(0, 19));
                            const endDate = new Date(event.end.substr(0, 19));

                            $("#events").append(`<div class="event">
                                    ${event.product_image_filename ? `<img src="${event.product_image_filename}" alt="${event.description}" />` : ""}
                                    <div>
                                        <h4 class="title">${event.description}</h4>
                                        ${event.detailed_description ? `<div class="description">${event.detailed_description}</div>` : ""}
                                        ${facilities}
                                        <div class="event-time">
                                            <span class="event-start-date">${$.formatDate(startDate, "MM/dd/yyyy")}</span>
                                            <span class="event-start-time">${$.formatDate(startDate, "hh:mm a")}</span>
                                            <span class="event-end-time">${$.formatDate(endDate, "hh:mm a")}</span>
                                        </div>
                                    </div>
                                    <div class="event-product-details">${productDetails}</div>
                                </div>`);
                        });
                    });

                    $("#event_types").addClass("hidden");
                    $("#events").removeClass("hidden");
                    $("#clear_event_filters").removeClass("hidden");

                    if ($('.event-type-filter:checked').length === 1) {
                        const selectedEventType = $(`#event_types [data-event-type-id="${eventTypeIds}"]`);
                        $("#event_type_details h2").html(selectedEventType.find(".description").html());
                        $("#event_type_details img").attr("src", selectedEventType.find("img").attr("src"));
                        $("#event_type_details p.detailed-description").html(selectedEventType.find(".detailed-description").html());

                        $("#event_type_details").removeClass("hidden");
                    } else {
                        $("#event_type_details").addClass("hidden");
                    }

                    if (empty(returnArray.events)) {
                        $("#events_empty_message").removeClass("hidden");
                    } else {
                        $("#events_empty_message").addClass("hidden");
                    }

                    if (typeof afterGetRecord == "function") {
                        afterGetRecord(returnArray);
                    }
                });
                return false;
            }

            $(document).on("click", ".event-month", function () {
                $(".event-month").removeClass("selected");
                $(this).addClass("selected");
                getFilteredEvents();
            });

            $(document).on("click", ".event-type", function () {
                const eventTypeId = $(this).data("event-type-id");
                $(`input[value="${eventTypeId}"].event-type-filter`).prop("checked", true);
                getFilteredEvents();
            });

            $(document).on("click", "#events_filters input:checkbox", function () {
                if ($('#events_filters input:checkbox:checked').length === 0 && $(".event-month.selected").length === 0) {
                    clearEventFilters();
                } else {
                    getFilteredEvents();
                }
            });

            $(document).on("click", "#events_back_link, #clear_event_filters, #events_header h1", clearEventFilters);
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #events_container p:empty {
                display: none;
            }

            #events_container p.detailed-description {
                font-size: 0.875rem;
                color: gray;
            }

            #events_header {
                padding: 1rem;
                display: flex;
                align-items: center;
            }

            #event_months {
                overflow-x: auto;
                white-space: nowrap;
                flex-shrink: 1;
            }

            #events_header h1 {
                flex: 0 0 40%;
                margin: 0;
                font-size: 2rem;
                cursor: pointer;
            }

            #events_header a {
                font-weight: normal;
                cursor: pointer;
                padding: 0.5rem;
                min-width: 7.5rem;
                margin-right: 1rem;
                display: inline-block;
                border-radius: 5px;
            }

            #events_header a.selected {
                text-decoration: underline;
                font-weight: bold;
            }

            #events_header a span {
                display: block;
                font-size: 0.75rem;
            }

            #events_content {
                display: flex;
            }

            #events_filters {
                flex: 0 0 20%;
                text-align: center;
                padding: 1rem;
            }

            #events_filters .filter-groups {
                display: inline-block;
                text-align: left;
            }

            #events_filters .filter-group {
                margin-bottom: 1rem;
                padding-bottom: 1rem;
                border-bottom: 1px solid whitesmoke;
            }

            #events_filters .filter-group:last-child {
                border: none;
                margin: 0;
                padding: 0;
            }

            #events_filters .event-filter {
                display: flex;
                align-items: center;
                margin-bottom: 0.75rem;
            }

            #clear_event_filters {
                cursor: pointer;
            }

            #event_types,
            #events {
                flex: 1;
            }

            .event-type {
                display: flex;
                padding: 1rem;
                cursor: pointer;
                border-bottom: 1px solid whitesmoke;
                font-size: 1rem;
            }

            .event-type:last-child {
                border: none;
            }

            .event-type > div {
                flex-shrink: 1;
            }

            .event-type img {
                width: 240px;
                height: 135px;
                object-fit: cover;
                margin-right: 1rem;
            }

            #event_type_details {
                border-bottom: 1px solid whitesmoke;
            }

            #event_type_details img {
                width: 100%;
                height: 300px;
                object-fit: cover;
            }

            #events_back_link {
                background-color: whitesmoke;
                margin-top: 0.5rem;
                padding: 0.5rem;
                border-radius: 5px;
                font-size: 0.75rem;
                display: inline-block;
                cursor: pointer;
            }

            #events_empty_message {
                margin: 1rem 0;
            }

            .event {
                padding: 1rem 0;
                border-bottom: 1px solid whitesmoke;
                margin-bottom: 1rem;
                font-size: 1rem;
                display: flex;
            }

            .event > div {
                flex-shrink: 1;
                padding-right: 1rem;
            }

            .event img {
                height: 100%;
                max-width: 250px;
                margin-right: 2rem;
                align-self: center;
            }

            .event h4 {
                font-weight: normal;
                margin-bottom: 0;
            }

            .event .description {
                color: gray;
                margin-top: 1rem;
                font-size: 0.875rem;
            }

            .event .event-facility p {
                color: gray;
                margin: 0;
            }

            .event .event-show-direction {
                margin-left: 0.5rem;
            }

            .event .event-time {
                margin-top: 1rem;
                margin-bottom: 0;
            }

            .event .event-time span {
                margin-right: 1rem;
            }

            .event .event-product-details {
                align-self: center;
                flex: 0 0 15rem;
                padding: 1rem;
            }

            .event .event-product-details a {
                font-size: 0.875rem;
                border-radius: 5px;
                text-transform: none;
                display: table;
                margin-bottom: 0.5rem;
            }

            .event:last-child {
                border: none;
            }

            @media only screen and (max-width: 768px) {
                #events_header {
                    display: block;
                }

                #events_header h1 {
                    font-size: 1.5rem;
                }

                #events_header a {
                    margin: 1rem;
                    min-width: auto;
                }

                #events_content {
                    display: block;
                }

                .event-type {
                    display: block;
                }

                .event-type h5 {
                    margin-top: 1rem;
                }

                #events {
                    padding: 1rem;
                }

                .event {
                    display: block;
                }

                .event .event-product-details {
                    padding-bottom: 0;
                    text-align: center;
                }

                .event .event-product-details a {
                    display: block;
                    padding: 0.75rem;
                }
            }
        </style>
		<?php
	}

}

$pageObject = new EventsPage();
$pageObject->displayPage();
