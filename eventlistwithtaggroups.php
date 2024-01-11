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

$GLOBALS['gPageCode'] = "EVENTLISTWITHTAGGROUPS";
require_once "shared/startup.inc";

class EventListWithTagGroupsPage extends Page {

	function mainContent() {
		echo $this->iPageData['content'];
		$substitutions = array();

		$preFilterLocations = getPageTextChunk("PREFILTER_LOCATIONS");
		if (!empty($preFilterLocations)) {
			$preFilterLocations = explode(",", $preFilterLocations);
		}

        if (!empty($_GET['event_type_id'])) {
			$eventTypeRow = getRowFromId("event_types", "event_type_id", $_GET['event_type_id']);
			if (!empty($eventTypeRow)) {
				$preFilterEventTypes[] = $eventTypeRow['event_type_code'];
			}
        } else {
			$preFilterEventTypes = getPageTextChunk("PREFILTER_EVENT_TYPES");
			if (!empty($preFilterEventTypes)) {
				$preFilterEventTypes = explode(",", $preFilterEventTypes);
			}
        }

        if (!empty($preFilterEventTypes) && count($preFilterEventTypes) === 1) {
            $eventTypeRow = getRowFromId("event_types", "event_type_code", $preFilterEventTypes[0]);

			$eventTypeRequirements = "";
			$eventTypeRequirement = $this->getPageTextChunk("EVENT_LIST_WITH_TAG_GROUPS_EVENT_TYPE_REQUIREMENT", $substitutions);
			if (empty($eventTypeRequirement)) {
				$eventTypeRequirement = $this->getFragment("EVENT_LIST_WITH_TAG_GROUPS_EVENT_TYPE_REQUIREMENT", $substitutions);
			}
			if (empty($eventTypeRequirement)) {
                $eventTypeURLTemplate = getPageTextChunk("EVENT_TYPE_URL_TEMPLATE") ?: "/event-type-details?id=%event_type_id%";+
				ob_start();
				?>
                <a href="<?= $eventTypeURLTemplate ?>" class="event-type-requirement" data-certification_type_id="%certification_type_id%" data-any_requirement="%any_requirement%"
                   data-event_type_id="%event_type_id%" data-certification_type_code="%certification_type_code%">%event_type_description%</a>
				<?php
				$eventTypeRequirement = ob_get_clean();
			}

			$resultSet = executeReadQuery("select certification_type_requirements.event_type_id, certification_types.*"
                . " from certification_type_requirements join certification_types using (certification_type_id)"
                . " where certification_type_id in (select certification_type_id from event_type_requirements where event_type_requirements.event_type_id = ?)"
                . " and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0")
                . " order by sort_order, description", $eventTypeRow['event_type_id']);
			while ($requirementRow = getNextRow($resultSet)) {
				$eventTypeRequirementRow = getRowFromId("event_types", "event_type_id", $requirementRow['event_type_id']);
				$eventTypeRequirements .= PlaceHolders::massageContent($eventTypeRequirement,
                    array_merge($requirementRow, Events::getEventTypeSubstitutions($eventTypeRequirementRow)));
			}
			$substitutions = array_merge($substitutions, Events::getEventTypeSubstitutions($eventTypeRow));
			$substitutions['event_type_requirements'] = $eventTypeRequirements;

			$customFields = CustomField::getCustomFields("event_types");
			foreach ($customFields as $customField) {
                $substitutionKey = 'event_type_' . strtolower($customField['custom_field_code']);
				$customField = CustomField::getCustomField($customField['custom_field_id']);
				$customField->setPrimaryIdentifier($eventTypeRow['event_type_id']);
                $substitutions[$substitutionKey] = $customField->getDisplayData();
			}
        }

		$eventFilter = $this->getPageTextChunk("EVENT_LIST_WITH_TAG_GROUPS_EVENT_FILTER", $substitutions);
		if (empty($eventFilter)) {
			$eventFilter = $this->getFragment("EVENT_LIST_WITH_TAG_GROUPS_EVENT_FILTER", $substitutions);
		}
		if (empty($eventFilter)) {
			ob_start();
			?>
            <div class="popover-filter">
                <input id="%filter_id%" value="%filter_value%" type="checkbox" %filter_checked% name="%description%" class="%filter_class%">
                <label class="checkbox-label" for="%filter_id%">%description%</label>
            </div>
			<?php
			$eventFilter = ob_get_clean();
		}

		$primaryFilter = $this->getPageTextChunk("EVENT_LIST_WITH_TAG_GROUPS_PRIMARY_FILTER", $substitutions);
		if (empty($primaryFilter)) {
			$primaryFilter = $this->getFragment("EVENT_LIST_WITH_TAG_GROUPS_PRIMARY_FILTER", $substitutions);
		}
		if (empty($primaryFilter)) {
			ob_start();
			?>
            <div id="%filter_group_code%_filter" class="events-filter" data-has-popover>
                <small>%description%</small>
                <p class="events-filter-selected"></p>
            </div>

            <div id="%filter_group_code%_filter_popover" class="popover-wrapper">
                <div class="popover-content">
                    <div class="popover-filter popover-filter-%filter_group_code%">
                        <p>%description%</p>
                        <div>%popover_filters%</div>
                    </div>
                    <div class="popover-arrow" data-popper-arrow></div>
                </div>
            </div>
			<?php
			$primaryFilter = ob_get_clean();
		}

		$secondaryFilter = $this->getPageTextChunk("EVENT_LIST_WITH_TAG_GROUPS_SECONDARY_FILTER", $substitutions);
		if (empty($primaryFilter)) {
			$secondaryFilter = $this->getFragment("EVENT_LIST_WITH_TAG_GROUPS_SECONDARY_FILTER", $substitutions);
		}
		if (empty($secondaryFilter)) {
			ob_start();
			?>
            <div class="popover-filter popover-filter-%filter_group_code%">
                <p>%description%</p>
                <div>%popover_filters%</div>
            </div>
			<?php
			$secondaryFilter = ob_get_clean();
		}
		$substitutions['primary_event_tag_filters'] = $this->createEventTagFilters(true, $primaryFilter, $eventFilter);
		$substitutions['secondary_event_tag_filters'] = $this->createEventTagFilters(false, $secondaryFilter, $eventFilter);

		$substitutions['location_popover_filters'] = "";
		$resultSet = executeQuery("select * from locations where client_id = ? and inactive = 0"
			. ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and product_distributor_id is null order by sort_order, description", $GLOBALS['gClientId']);

		$primaryLocations = array();
		if (!empty(getPageTextChunk("PRIMARY_LOCATIONS"))) {
			$primaryLocations = explode(",", getPageTextChunk("PRIMARY_LOCATIONS"));
		}
		$locations = array();
		while ($locationRow = getNextRow($resultSet)) {
			$locations[] = $locationRow;
		}
		usort($locations, function($a, $b) use ($primaryLocations) {
			$locationIndexA = array_search($a['location_code'], $primaryLocations);
			$locationIndexB = array_search($b['location_code'], $primaryLocations);
			if ($locationIndexA === false && $locationIndexB === false) {
				return 0;
			} else if ($locationIndexA === false) {
				return 1;
			} else if ($locationIndexB === false) {
				return -1;
			} else {
				return $locationIndexA - $locationIndexB;
			}
		});
		foreach ($locations as $location) {
			$location['filter_id'] = "location-" . makeCode($location['location_code'], array("lowercase" => true));
			$location['filter_value'] = $location['location_id'];
			$location['description'] = htmlText($location['description']);
			$location['filter_checked'] = !empty($preFilterLocations) && in_array($location['location_code'], $preFilterLocations) ? "checked" : "";
			$location['filter_class'] = "location-filter";
			$substitutions['location_popover_filters'] .= PlaceHolders::massageContent($eventFilter, $location);
		}

		$substitutions['event_type_popover_filters'] = "";
		$resultSet = executeQuery("select * from event_types where client_id = ? and hide_in_calendar = 0 and inactive = 0"
			. ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order, description", $GLOBALS['gClientId']);
		while ($eventTypeRow = getNextRow($resultSet)) {
			$eventTypeRow['filter_id'] = htmlText($eventTypeRow['description']);
			$eventTypeRow['filter_value'] = $eventTypeRow['event_type_id'];
			$eventTypeRow['description'] = htmlText($eventTypeRow['description']);
			$eventTypeRow['filter_checked'] = !empty($preFilterEventTypes) && in_array($eventTypeRow['event_type_code'], $preFilterEventTypes) ? "checked" : "";
			$eventTypeRow['filter_class'] = "event-type-filter";
			$substitutions['event_type_popover_filters'] .= PlaceHolders::massageContent($eventFilter, $eventTypeRow);
		}

		$substitutions['availability_checked'] = empty(getPageTextChunk("PREFILTER_AVAILABILITY")) ? "" : "checked";
		$substitutions['eligibility_checked'] = empty(getPageTextChunk("PREFILTER_ELIGIBILITY")) ? "" : "checked";
		$substitutions['weekends_only_checked'] = empty(getPageTextChunk("PREFILTER_WEEKENDS_ONLY")) ? "" : "checked";

		$substitutions['eligibility_notice'] = getPageTextChunk("ELIGIBILITY_NOTICE", $substitutions);
		if (empty($substitutions['eligibility_notice'])) {
			$substitutions['eligibility_notice'] = getFragment("ELIGIBILITY_NOTICE", $substitutions);
		}
		$substitutions['eligibility_notice_title'] = getPageTextChunk("ELIGIBILITY_NOTICE_TITLE") ?: "Eligibility Notice";

		$eventListWithTagGroups = $this->getPageTextChunk("EVENT_LIST_WITH_TAG_GROUPS", $substitutions);
		if (empty($eventListWithTagGroups)) {
			$eventListWithTagGroups = $this->getFragment("EVENT_LIST_WITH_TAG_GROUPS", $substitutions);
		}
		if (empty($eventListWithTagGroups)) {
			ob_start();
			?>
            <div id="events_container">
                <div id="events_filters_container">
                    <div id="events_filters_content">
                        <h2>Refine the results</h2>
                        <div id="events_filters">
                            %primary_event_tag_filters%

                            <div id="location_filter" class="events-filter" data-has-popover>
                                <small>Location</small>
                                <p class="events-filter-selected"></p>
                            </div>

                            <div id="location_filter_popover" class="popover-wrapper">
                                <div class="popover-content">
                                    <div class="popover-filter popover-filter-location">
                                        <p>Location</p>
                                        <div>%location_popover_filters%</div>
                                    </div>
                                    <div class="popover-arrow" data-popper-arrow></div>
                                </div>
                            </div>

                            <div id="event_type_filter" class="events-filter" data-has-popover>
                                <small>Event types</small>
                                <p class="events-filter-selected"></p>
                            </div>

                            <div id="event_type_filter_popover" class="popover-wrapper">
                                <div class="popover-content">
                                    <div class="popover-filter">
                                        <p>Event types</p>
                                        <div>%event_type_popover_filters%</div>
                                    </div>
                                    <div class="popover-arrow" data-popper-arrow></div>
                                </div>
                            </div>

                            <div id="all_filters" data-has-popover>
                                <span>All filters</span>
                                <small></small>
                            </div>

                            <div id="all_filters_popover" class="popover-wrapper">
                                <div class="popover-content">
                                    <div class="popover-filter popover-filter-availability">
                                        <p>Availability</p>
                                        <div>
                                            <label for="available_events_only">Available courses only</label>
                                            <input id="available_events_only" type="checkbox" %availability_checked%>
                                        </div>
                                    </div>

                                    <div class="popover-filter popover-filter-eligibility">
                                        <p>Eligibility</p>
                                        <div>
                                            <label for="eligible_events_only">Only courses I'm eligible to take</label>
                                            <input id="eligible_events_only" type="checkbox" %eligibility_checked%>
                                        </div>
                                    </div>

                                    <div class="popover-filter popover-filter-dates">
                                        <p>Dates</p>
                                        <div>
                                            <input id="start_date" type="text" aria-label="Start date" placeholder="Start date">
                                            <input id="end_date" type="text" aria-label="End date" placeholder="End date">
                                        </div>
                                    </div>

                                    %secondary_event_tag_filters%
                                    <div class="popover-arrow" data-popper-arrow></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                %if_has_value:eligibility_notice%
                <div id="events_header">
                    <h2>%eligibility_notice_title%</h2>
                    %eligibility_notice%
                </div>
                %endif%

                <div id="events" class="hidden">
                    <p id="events_empty_message" class="hidden">No events available.</p>
                </div>

                <div id="events_pagination">
                    <a id="events_previous_page" class="button" href="#">Previous</a>
                    <a id="events_next_page" class="button" href="#">Next</a>
                </div>
            </div>
			<?php
			$eventListWithTagGroups = ob_get_clean();
		}
		echo PlaceHolders::massageContent($eventListWithTagGroups, $substitutions);
		?>

		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function createEventTagFilters($forPrimary, $eventTagFilter, $eventFilter) {
		$eventTagFilters = "";

		$primaryFilters = getPageTextChunk("PRIMARY_FILTERS");
		if (!empty($primaryFilters)) {
			$primaryFilters = explode(",", $primaryFilters);
		}
		if (empty($primaryFilters)) {
			$primaryFilters = array();
		}

		$preFilterEventTypeTags = getPageTextChunk("PREFILTER_EVENT_TYPE_TAGS");
		if (!empty($preFilterEventTypeTags)) {
			$preFilterEventTypeTags = explode(",", $preFilterEventTypeTags);
		}

		$tagGroupResultSet = executeQuery("select * from event_type_tag_groups where client_id = ? and inactive = 0"
			. ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order, description", $GLOBALS['gClientId']);
		while ($tagGroupRow = getNextRow($tagGroupResultSet)) {
			if ($forPrimary == in_array($tagGroupRow['event_type_tag_group_code'], $primaryFilters)) {
				$tagGroupRow['filter_group_code'] = strtolower($tagGroupRow['event_type_tag_group_code']);
				$tagGroupRow['description'] = htmlText($tagGroupRow['description']);

				$tagGroupRow['popover_filters'] = "";
				$tagResultSet = executeQuery("select * from event_type_tags where event_type_tag_group_id = ? and client_id = ? and inactive = 0"
					. ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order, description", $tagGroupRow['event_type_tag_group_id'], $GLOBALS['gClientId']);
				while ($tagRow = getNextRow($tagResultSet)) {
					$tagRow['filter_id'] = strtolower($tagRow['event_type_tag_code']);
					$tagRow['filter_value'] = $tagRow['event_type_tag_id'];
					$tagRow['description'] = htmlText($tagRow['description']);
					$tagRow['filter_checked'] = !empty($preFilterEventTypeTags) && in_array($tagRow['event_type_tag_code'], $preFilterEventTypeTags) ? "checked" : "";
					$tagRow['filter_class'] = "event-type-tag";
					$tagGroupRow['popover_filters'] .= PlaceHolders::massageContent($eventFilter, $tagRow);
				}
				$eventTagFilters .= PlaceHolders::massageContent($eventTagFilter, $tagGroupRow);
			}
		}
		return $eventTagFilters;
	}

	function headerIncludes() {
		?>
        <script src="https://unpkg.com/@popperjs/core@2"></script>
		<?php
	}

	function javascript() {
		$excludeProductTags = getPreference("EXCLUDE_PRODUCT_TAGS", $GLOBALS['gPageRow']['page_code']) ?: "PRIVATE";
		?>
        <script>
            let eventsCurrentPage = 1;
            let eventsPageSize = <?= getPageTextChunk("EVENT_PAGE_SIZE") ?: 9 ?>;
            let eventsTotalCount = 0;

            let eventDateFormat = "<?= getPageTextChunk("EVENT_DATE_FORMAT") ?: "MMM d, yyyy" ?>";
            let eventDayFormat = "<?= getPageTextChunk("EVENT_DAY_FORMAT") ?: "EEEE" ?>";
            let eventTimeFormat = "<?= getPageTextChunk("EVENT_TIME_FORMAT") ?: "hh:mm a" ?>";

            let excludeProductTags = "<?= !empty($excludeProductTags) ? $excludeProductTags : "" ?>";

            function getFilteredEvents() {
                const checkedCount = $("#all_filters_popover input:checked").length;
                $("#all_filters small").html(checkedCount ? checkedCount : "");

                const locationIds = $("#location_filter_popover input:checked").map((index, element) => element.value).get().join(',');
                const eventTypeIds = $("#event_type_filter_popover input:checked").map((index, element) => element.value).get().join(',');
                const eventTypeTagIds = $("input.event-type-tag:checked").map((index, element) => element.value).get().join(',');

                let eventTypeCustomFields = "";
                $("[data-event_type_custom_field]").each(function() {
                    const customFieldCode = $(this).data("event_type_custom_field");
                    let customFieldValues = $(this).find("input:checked").map((index, element) => element.value).get().join(',');
                    if (empty(customFieldValues)) {
                        customFieldValues = $(this).data("default_value");
                    }
                    if (!empty(customFieldCode) && !empty(customFieldValues)) {
                        eventTypeCustomFields += `${customFieldCode.toUpperCase()}=${customFieldValues}:`;
                    }
                });

                const startDate = $("#start_date").val();
                const endDate = $("#end_date").val();

                let parameters = {
                    include_locations: true,
                    limit: eventsPageSize,
                    offset: (eventsCurrentPage - 1) * eventsPageSize
                };
                if (!empty(startDate)) {
                    parameters['start_date'] = $.formatDate(new Date(startDate), "yyyy-MM-dd");
                }
                if (!empty(endDate)) {
                    parameters['end_date'] = $.formatDate(new Date(endDate), "yyyy-MM-dd");
                }
                if ($("#available_events_only").is(":checked")) {
                    parameters['available_events_only'] = true;
                }
                if ($("#eligible_events_only").is(":checked")) {
                    parameters['eligible_events_only'] = true;
                }
                if ($("#weekend_events_only").is(":checked")) {
                    parameters['weekend_events_only'] = true;
                }
                if (!empty(eventTypeTagIds)) {
                    parameters['event_type_tag_ids'] = eventTypeTagIds;
                }
                if (!empty(eventTypeIds)) {
                    parameters['event_type_ids'] = eventTypeIds;
                }
                if (!empty(locationIds)) {
                    parameters['location_ids'] = locationIds;
                }
                if (!empty(eventTypeCustomFields)) {
                    parameters['event_type_custom_fields'] = eventTypeCustomFields;
                }
                if (!empty(excludeProductTags)) {
                    parameters['exclude_product_tags'] = excludeProductTags;
                }

                if (typeof customGetEventsParameters == "function") {
                    parameters = customGetEventsParameters(parameters);
                }

                let url = "/retail-store-controller?ajax=true&url_action=get_detailed_events";
                for (const [fieldName, value] of Object.entries(parameters)) {
                    url += `&${fieldName}=${value}`;
                }

                $("body").addClass("waiting-for-ajax");

                loadAjaxRequest(url, function(returnArray) {
                    if (typeof cleanUpEvents == "function") {
                        cleanUpEvents(returnArray);
                    } else {
                        $("#events .event").remove();
                    }

                    const eventTypeURLTemplate = "<?= getPageTextChunk("EVENT_TYPE_URL_TEMPLATE") ?: "/event-type-details?id=%event_type_id%" ?>";
                    for (let [eventTypeId, eventType] of Object.entries(returnArray.event_types)) {
                        let eventTypeTags = "";
                        if (eventType.tags) {
                            for (const [eventTypeTagGroup, eventTypeTagValues] of Object.entries(eventType.tags)) {
                                eventTypeTagValues.forEach(eventTypeTagValue => {
                                    eventTypeTags += `<span class="event-header-detail event-type-tag-${eventTypeTagGroup}">${eventTypeTagValue}</span>`;
                                });
                            }
                        }
                        eventType.tags = eventTypeTags;
                        eventType.price = empty(eventType.price) ? "" : RoundFixed(eventType.price, eventType.price % 1 !== 0 ? 2 : 0);
                        let eventTypeURL = eventTypeURLTemplate;

                        // Add event_type prefix so same name fields (e.g. description) doesn't conflict with event field substitution values
                        for (let [eventTypeField, eventTypeValue] of Object.entries(eventType)) {
                            let eventTypeKey = eventTypeField;
                            if (!eventTypeField.startsWith("event_type")) {
                                eventTypeKey = `event_type_${eventTypeField}`;
                                eventType[eventTypeKey] = eventTypeValue;
                                delete eventType[eventTypeField];
                            }
                            eventTypeURL = eventTypeURL.replaceAll(`%${eventTypeKey}%`, eventTypeValue);
                        }
                        eventType['event_type_url'] = eventTypeURL;
                    }

                    if (!empty(returnArray.locations)) {
                        for (let [locationId, location] of Object.entries(returnArray.locations)) {
                            location['city_state'] = empty(location.city) ? "" : location.city;
                            if (!empty(location.state)) {
                                location['city_state'] += (empty(location['city_state']) ? "" : ", ") + location.state;
                            }
                            // Add location prefix so same name fields (e.g. description) doesn't conflict with event field substitution values
                            for (let [locationField, locationValue] of Object.entries(location)) {
                                if (!locationField.startsWith("location")) {
                                    location[`location_${locationField}`] = locationValue;
                                    delete location[locationField];
                                }
                            }
                        }
                    }

                    returnArray.events
                        .forEach((event) => {
                            const eventTypeId = event.event_type_id;
                            const eventType = eventTypeId && returnArray.event_types[eventTypeId] ? returnArray.event_types[eventTypeId] : {};

                            const locationId = event.location_id;
                            const location = locationId && !empty(returnArray.locations) && returnArray.locations[locationId] ? returnArray.locations[locationId] : {};

                            let replacementFields = Object.assign({}, event, eventType, location);

                            // Remove timezone so date displays are not client dependent
                            const startDate = new Date(event.start.substr(0, 19));
                            const endDate = new Date(event.end.substr(0, 19));

                            replacementFields['start_date'] = $.formatDate(startDate, eventDateFormat);
                            replacementFields['start_time'] = $.formatDate(startDate, eventTimeFormat);
                            replacementFields['end_date'] = $.formatDate(endDate, eventDateFormat);
                            replacementFields['end_time'] = $.formatDate(endDate, eventTimeFormat);
                            replacementFields['day'] = $.formatDate(startDate, eventDayFormat);
                            replacementFields['end_day'] = $.formatDate(endDate, eventDayFormat);
                            replacementFields['start_day'] = replacementFields['day'];

                            replacementFields['product_price'] = empty(event.product_price) ? "" : RoundFixed(event.product_price, event.product_price % 1 !== 0 ? 2 : 0);
                            replacementFields['login_to_register'] = !empty("<?= getPreference("RETAIL_STORE_NO_GUEST_CART") ?>") && !userLoggedIn;
                            replacementFields['login_link'] = "<?= getPageTextChunk("LOGIN_LINK") ?: "/login?url='" . $GLOBALS['gLinkUrl'] . "'" ?>";

                            let facilities = "";
                            event.facilities.forEach(facility => {
                                if (facility.location) {
                                    let facilityReplacementFields = Object.assign({}, replacementFields);
                                    const location = facility.location;
                                    location['city_state'] = empty(location.city) ? "" : location.city;
                                    if (!empty(location.state)) {
                                        location['city_state'] += (empty(location['city_state']) ? "" : ", ") + location.state;
                                    }
                                    location['url'] = location.longitude && location.latitude ? `https://www.google.com/maps/search/?api=1&query=${location.latitude},${location.longitude}` : null;

                                    for (const [key, value] of Object.entries(facility)) {
                                        facilityReplacementFields[key.startsWith("facility_") ? key : `facility_${key}`] = value;
                                    }
                                    for (const [key, value] of Object.entries(location)) {
                                        facilityReplacementFields[key.startsWith("location_") ? key : `location_${key}`] = value;
                                    }
                                    facilities += massageTaggedEventsContent($("#event_facility_template").html(), facilityReplacementFields);
                                }
                            });
                            replacementFields['facilities'] = facilities;

                            $("#events").append(massageTaggedEventsContent($("#event_template").html(), replacementFields));

                            if (!empty(event.product_id) && !empty(event.in_wishlist)) {
                                const addToWishlist = $(".add-to-wishlist-" + event.product_id);
                                const inWishlistText = addToWishlist.data("in_text");
                                if (!empty(inWishlistText)) {
                                    addToWishlist.html(inWishlistText);
                                }
                            }
                        });

                    $("#events").removeClass("hidden");

                    if (empty(returnArray.events)) {
                        $("#events_empty_message").removeClass("hidden");
                    } else {
                        $("#events_empty_message").addClass("hidden");
                    }

                    eventsTotalCount = returnArray.total_events_count;
                    updatePaginationPanel();

                    if (typeof afterGetEvents == "function") {
                        afterGetEvents(returnArray);
                    }
                });
                return false;
            }

            function updatePaginationPanel() {
                const paginationElement = $("#events_pagination");
                const previousPageElement = $("#events_previous_page");
                const nextPageElement = $("#events_next_page");

                if (eventsTotalCount <= eventsPageSize) {
                    paginationElement.hide();
                } else {
                    paginationElement.show();
                }

                if (eventsCurrentPage === 1) {
                    previousPageElement.addClass("disabled");
                } else {
                    previousPageElement.removeClass("disabled");
                }

                const eventsPageStart = (eventsCurrentPage - 1) * eventsPageSize;
                const eventsPageEnd = eventsPageStart + eventsPageSize;

                if (eventsPageEnd < eventsTotalCount) {
                    nextPageElement.removeClass("disabled");
                } else {
                    nextPageElement.addClass("disabled");
                }
            }

            function updateSelectedFiltersLabel(triggerElement, popoverElement) {
                let filtersLabel = "";

                if (popoverElement.find(".popover-filter-dates").length) {
                    const startDateValue = $("#start_date").val();
                    const formattedStartDate = empty(startDateValue) ? "" : $.formatDate(new Date(startDateValue), eventDateFormat);

                    const endDateValue = $("#end_date").val();
                    const formattedEndDate = empty(endDateValue) ? "" : $.formatDate(new Date(endDateValue), eventDateFormat);
                    filtersLabel = `${formattedStartDate} - ${formattedEndDate}`;
                } else {
                    const selectedElements = popoverElement.find("input:checked");
                    if (selectedElements.length) {
                        filtersLabel = `${selectedElements.length} selected`;
                        if (selectedElements.length < 3) {
                            filtersLabel = selectedElements.map(function() {
                                return $(this).attr("name");
                            }).get().join(', ');
                        }
                    }
                }
                empty(filtersLabel) ? triggerElement.removeClass("has-selected") : triggerElement.addClass("has-selected");
                triggerElement.find(".events-filter-selected").html(filtersLabel);
            }

            function massageTaggedEventsContent(content, substitutions) {
                for (let [fieldName, fieldValue] of Object.entries(substitutions)) {
                    if (empty(fieldValue)) {
                        fieldValue = "";
                    }
                    content = content.replace(new RegExp(`%${fieldName}%`, 'ig'), fieldValue);
                    content = content.replace(new RegExp(`%hidden_if_empty:${fieldName}%`, 'ig'), (empty(fieldValue) ? "hidden" : ""));
                    content = content.replace(new RegExp(`%hidden_if_not_empty:${fieldName}%`, 'ig'), (empty(fieldValue) ? "" : "hidden"));
                }
                return content;
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            // Default values
            const date = new Date();
            $("#start_date").datepicker({ dateFormat: "mm/dd/yy" }).datepicker("setDate", new Date());
            $("#end_date").datepicker({ dateFormat: "mm/dd/yy" }).datepicker("setDate", new Date(date.setMonth(date.getMonth() + 1)));

            $(document).on("change", "#events_filters input", function () {
                if (typeof beforeGetEvents == "function") {
                    beforeGetEvents();
                }
                eventsCurrentPage = 1;
                getFilteredEvents();
            });

            $("[data-has-popover]").each(function() {
                const triggerElement = $(this);
                const popoverElement = $(`#${triggerElement.attr("id")}_popover`);

                const offset = empty(triggerElement.data("placement")) ? [0, 0] : [0, triggerElement.data("offset")];
                const popperInstance = Popper.createPopper(
                    document.querySelector(`#${triggerElement.attr("id")}`),
                    document.querySelector(`#${triggerElement.attr("id")}_popover`), {
                        placement: empty(triggerElement.data("placement")) ? "bottom" : triggerElement.data("placement"),
                        modifiers: [
                            { name: "offset", options: { offset }}
                        ]
                    }
                );

                triggerElement.on("click", function () {
                    if (popoverElement.hasClass("hover")) {
                        popoverElement.removeClass("hover").removeAttr("data-show");
                    } else {
                        $('.popover-wrapper').removeClass("hover").removeAttr("data-show");
                        popoverElement.addClass("hover").attr("data-show", true);
                        popperInstance.update();
                    }
                });

                updateSelectedFiltersLabel(triggerElement, popoverElement);
                popoverElement.find("input").on("click change", function () {
                    updateSelectedFiltersLabel(triggerElement, popoverElement);
                });
            });

            // Close popover when clicked outside it
            $(document).on("click", function (event) {
                const element = $(event.target);
                if (!element.hasClass("popover-wrapper") && !element.closest(".popover-wrapper").length && !element.closest("[data-has-popover]").length) {
                    $('.popover-wrapper').removeClass("hover").removeAttr("data-show");
                }
            });

            $(document).on("click", "#events_previous_page", function () {
                if (!$(this).hasClass("disabled")) {
                    eventsCurrentPage--;
                    getFilteredEvents();
                }
                return false;
            });

            $(document).on("click", "#events_next_page", function () {
                eventsCurrentPage++;
                getFilteredEvents();
                return false;
            });

            if (typeof beforeGetEvents == "function") {
                beforeGetEvents();
            }
            getFilteredEvents();
        </script>
		<?php
	}

	function jqueryTemplates() {
		$eventTemplate = $this->getPageTextChunk("EVENT_LIST_WITH_TAG_GROUPS_EVENT");
		if (empty($eventTemplate)) {
			$eventTemplate = $this->getFragment("EVENT_LIST_WITH_TAG_GROUPS_EVENT");
		}
		if (empty($eventTemplate)) {
			$eventCompletedLabel = getPageTextChunk("EVENT_COMPLETED_LABEL") ?: "Class completed";

			$registerLabel = getPageTextChunk("EVENT_REGISTER_LABEL") ?: "Register";
			$registeringLabel = getPageTextChunk("EVENT_REGISTERING_LABEL") ?: "Registering...";
			$registeredLabel = getPageTextChunk("EVENT_REGISTERED_LABEL") ?: "Registered";

			$addWishlistLabel = getPageTextChunk("EVENT_ADD_WISHLIST_LABEL") ?: "Get notified";
			$removeWishlistLabel = getPageTextChunk("EVENT_REMOVE_WISHLIST_LABEL") ?: "Unsubscribe";

			$loginToRegisterLabel = getPageTextChunk("EVENT_LOGIN_TO_REGISTER_LABEL") ?: "Login to register";

			ob_start();
			?>
            <div class="event">
                <div class="event-header">
                    <div class="event-date">
                        <span class="event-start-date">%start_date%</span>
                        <span class="event-day">%day%</span>
                        <span class="event-start-time">%start_time%</span>
                        <span class="event-end-time">%end_time%</span>
                    </div>

                    <div class="event-header-details">
                        %event_type_tags%
                        <span class="event-header-detail event-header-price">
                            <span class="%hidden_if_empty:event_type_price%">$%event_type_price%</span>
                            <span class="%hidden_if_empty:product_price%">$%product_price%</span>
                        </span>
                    </div>
                </div>

                <div class="event-body">
                    <h4 class="title">%description%</h4>
                    <div class="event-description %hidden_if_empty:detailed_description%">%detailed_description%</div>
                    <div class="event-type-description %hidden_if_empty:event_type_detailed_description%">%event_type_detailed_description%</div>
                    <div class="event-type-excerpt %hidden_if_empty:event_type_excerpt%">%event_type_excerpt%</div>

                    <a href="%event_type_url%" class="event-details-link %hidden_if_empty:event_type_url%">Details</a>

                    <div class="event-product-details %hidden_if_empty:product_id%">
                        <span class="event-completed %hidden_if_empty:product_completed%"><?= $eventCompletedLabel ?></span>

                        <div class="%hidden_if_not_empty:spots_left% %hidden_if_not_empty:product_completed%">
                            <a class="button event-full add-to-wishlist add-to-wishlist-%product_id%"
                               href="#" data-product_id="%product_id%" data-in_text="<?= $removeWishlistLabel ?>"
                               data-text="<?= $addWishlistLabel ?>">
                                <span class="%hidden_if_empty:in_wishlist%"><?= $removeWishlistLabel ?></span>
                                <span class="%hidden_if_not_empty:in_wishlist%"><?= $addWishlistLabel ?></span>
                            </a>
                        </div>

                        <a class="button %hidden_if_empty:spots_left% %hidden_if_not_empty:product_completed% %hidden_if_empty:login_to_register%" href="%login_link%"><?= $loginToRegisterLabel ?></a>
                        <a class="button event-register add-to-cart add-to-cart-%product_id% %hidden_if_empty:spots_left% %hidden_if_not_empty:product_completed% %hidden_if_not_empty:login_to_register%"
                           href="#" data-product_id="%product_id%" data-in_text="<?= $registeredLabel ?>" data-text="<?= $registerLabel ?>"
                           data-adding_text="<?= $registeringLabel ?>"><?= $registerLabel ?></a>
                        <span class="%hidden_if_empty:spots_left% %hidden_if_not_empty:product_completed%">%spots_left%/%attendees% slots available</span>
                    </div>
                </div>

                <div class="event-footer">%facilities%</div>
            </div>
			<?php
			$eventTemplate = ob_get_clean();
		}

		$eventFacilityTemplate = $this->getPageTextChunk("EVENT_LIST_WITH_TAG_GROUPS_EVENT_FACILITY");
		if (empty($eventFacilityTemplate)) {
			$eventFacilityTemplate = $this->getFragment("EVENT_LIST_WITH_TAG_GROUPS_EVENT_FACILITY");
		}
		if (empty($eventFacilityTemplate)) {
			ob_start();
			?>
            <div class="event-facility" data-facility_id="%facility_id%">
                <a class="event-direction %hidden_if_empty:location_url%" href="%location_url%" target="_blank"><i class="fa fa-map-marker-alt"></i> %location_city_state%</a>
                <p class="event-location %hidden_if_not_empty:location_url%">%location_city_state%</p>
            </div>
			<?php
			$eventFacilityTemplate = ob_get_clean();
		}

		?>
        <div id="event_template"><?= $eventTemplate ?></div>
        <div id="event_facility_template"><?= $eventFacilityTemplate ?></div>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            .popover-wrapper {
                display: none;
                z-index: 1000;
                padding: 1.5rem;
            }

            .popover-wrapper[data-show] {
                display: block;
            }

            .popover-content {
                background: white;
                position: relative;
                padding: 1.5rem;
                font-size: 1rem;
                border: 1px solid darkgray;
                box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15);
            }

            [data-popper-placement^='top'] .popover-arrow {
                bottom: -8px;
            }

            [data-popper-placement^='top'] .popover-arrow,
            [data-popper-placement^='top'] .popover-arrow::before {
                border-right: 1px solid darkgray;
                border-bottom: 1px solid darkgray;
            }

            [data-popper-placement^='bottom'] .popover-arrow {
                top: -10px;
            }

            [data-popper-placement^='bottom'] .popover-arrow,
            [data-popper-placement^='bottom'] .popover-arrow::before {
                border-top: 1px solid darkgray;
                border-left: 1px solid darkgray;
            }

            .popover-arrow,
            .popover-arrow::before {
                position: absolute;
                width: 16px;
                height: 16px;
                background: inherit;
            }

            .popover-arrow {
                visibility: hidden;
            }

            .popover-arrow::before {
                visibility: visible;
                content: '';
                transform: rotate(45deg);
            }
        </style>
		<?php
	}

}

$pageObject = new EventListWithTagGroupsPage();
$pageObject->displayPage();
