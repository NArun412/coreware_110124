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

/*
%module:tagged_events:event_type_code=xxxxx:wrapper_element_id=element_id%

Options:
select_limit=10 - limit number of events
event_type_code=xxxxx - limit events to this event type
event_type_tag_code=xxxxx - limit events to this event type tag
product_tag_code=xxxxx - limit events to products with this product tag
location_code=xxxxx - limit events to this location
days_to_show=30 - controls the end date of the shown events
wrapper_element_id=XXXXX - default is _tagged_events_ + random string
empty_message=XXXXX - default message if there is no events for the given filter (event type, event type tag or location)
available_events_only=true
eligible_events_only=true
fragment_code=XXXXXX - fragment to use for the event results. see below for default html
no_style=true
*/

class TaggedEventsPageModule extends PageModule {

	function createContent() {
        if (!empty($this->iParameters['event_type_code'])) {
            $eventTypeId = getFieldFromId("event_type_id", "event_types", "event_type_code", $this->iParameters['event_type_code'],
                "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
        }
        if (!empty($this->iParameters['event_type_tag_code'])) {
            $eventTypeTagId = getFieldFromId("event_type_tag_id", "event_type_tags", "event_type_tag_code", $this->iParameters['event_type_tag_code'],
                "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
        }
        if (!empty($this->iParameters['product_tag_code'])) {
            $productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", $this->iParameters['product_tag_code'],
                "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
        }
        if (!empty($this->iParameters['location_code'])) {
            $locationId = getFieldFromId("location_id", "locations", "location_code", $this->iParameters['location_code'],
                "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
        }
        if (empty($eventTypeId) && empty($eventTypeTagId) && empty($locationId) && empty($productTagId)) {
            ?>
                Invalid tagged events parameters <span class="hidden"><?= jsonEncode($this->iParameters) ?></span>
            <?php
            return;
        }

        $selectLimit = empty($this->iParameters['select_limit']) ? 10 : $this->iParameters['select_limit'];
        $daysToShow = !empty($this->iParameters['days_to_show']) && is_numeric($this->iParameters['days_to_show']) ? $this->iParameters['days_to_show'] : 30;

        $wrapperElementId = $this->iParameters['wrapper_element_id'] ?: "_tagged_events_" . getRandomString(12);
        ?>
            <div id="<?= $wrapperElementId ?>" class="hidden tagged-events">
                <p class="hidden no-events-message"><?= empty($this->iParameters['empty_message']) ? "No events available." : $this->iParameters['empty_message'] ?></p>
            </div>

            <div id="<?= $wrapperElementId . "_fragment" ?>" class="hidden">
                <?php
                    $fragmentId = getFieldFromId("fragment_id", "fragments", "fragment_code", $this->iParameters['fragment_code']);
                    if (!empty($fragmentId)) {
                        ?>
                            <?= getFieldFromId("content", "fragments", "fragment_id", $fragmentId) ?>
                        <?php
                    } else {
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
                                        <span class="event-header-detail event-header-price %hidden_if_empty:product_price%">%product_price%</span>
                                    </div>
                                </div>

                                <div class="event-body">
                                    <h4 class="event-title">%description%</h4>
                                    <div class="event-description %hidden_if_empty:detailed_description%">%detailed_description%</div>
                                    <div class="event-type-description %hidden_if_empty:event_type_detailed_description%">%event_type_detailed_description%</div>
                                    <div class="event-type-excerpt %hidden_if_empty:event_type_excerpt%">%event_type_excerpt%</div>

                                    <a href="%event_type_url%" class="event-details-link %hidden_if_empty:event_type_url%">Details</a>

                                    <div class="event-product-details %hidden_if_empty:product_id%">
                                        <span class="event-completed %hidden_if_empty:product_completed%">Class completed</span>

                                        <div class="%hidden_if_not_empty:spots_left% %hidden_if_not_empty:product_completed%">
                                            <a class="button event-full add-to-wishlist add-to-wishlist-%product_id%"
                                               href="#" data-product_id="%product_id%" data-in_text="Unsubscribe"
                                               data-text="Get notified">
                                                <span class="%hidden_if_empty:in_wishlist%">Unsubscribe</span>
                                                <span class="%hidden_if_not_empty:in_wishlist%">Get notified</span>
                                            </a>
                                        </div>

                                        <div class="%hidden_if_empty:spots_left% %hidden_if_not_empty:product_completed%">
                                            <a class="button event-register add-to-cart add-to-cart-%product_id%"
                                               href="#" data-product_id="%product_id%" data-in_text="Registered" data-text="Register"
                                               data-adding_text="Registering...">Register</a>
                                            <span>%spots_left%/%attendees% slots available</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="event-footer">%facilities%</div>
                            </div>
                        <?php
                    }
                ?>
            </div>

            <div id="<?= $wrapperElementId . "_facility_fragment" ?>" class="hidden">
                <?php
                $facilityFragmentId = getFieldFromId("fragment_id", "fragments", "fragment_code", $this->iParameters['facility_fragment_code']);
                if (!empty($facilityFragmentId)) {
                    ?>
                    <?= getFieldFromId("content", "fragments", "fragment_id", $facilityFragmentId) ?>
                    <?php
                } else {
                    ?>
                    <div class="event-facility" data-facility_id="%facility_id%">
                        <a class="event-direction %hidden_if_empty:location_url%" href="%location_url%" target="_blank"><i class="fa fa-map-marker-alt"></i> %location_city_state%</a>
                        <p class="event-location %hidden_if_not_empty:location_url%">%location_city_state%</p>
                    </div>
                    <?php
                }
                ?>
            </div>

            <script>
                let eventDateFormat = "<?= getPageTextChunk("EVENT_DATE_FORMAT") ?: "MMM d, yyyy" ?>";
                let eventDayFormat = "<?= getPageTextChunk("EVENT_DAY_FORMAT") ?: "EEEE" ?>";
                let eventTimeFormat = "<?= getPageTextChunk("EVENT_TIME_FORMAT") ?: "hh:mm a" ?>";

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

                $(function() {
                    const wrapperElementId = "<?= $wrapperElementId ?>";

                    if (typeof beforeGetTaggedEvents == "function") {
                        beforeGetTaggedEvents(wrapperElementId);
                    }

                    const date = new Date();
                    const startDate = new Date();
                    const endDate = new Date(date.setDate(date.getDate() + <?= $daysToShow - 1 ?>));

                    const url = "/retail-store-controller?ajax=true&url_action=get_detailed_events"
                        + `&start_date=${$.formatDate(startDate, "yyyy-MM-dd")}`
                        + `&end_date=${$.formatDate(endDate, "yyyy-MM-dd")}`
                        + "<?= empty($this->iParameters['available_events_only']) ? "" : "&available_events_only=true" ?>"
                        + "<?= empty($this->iParameters['eligible_events_only']) ? "" : "&eligible_events_only=true" ?>"
                        + "<?= empty($eventTypeTagId) ? "" : "&event_type_tag_ids=" . $eventTypeTagId ?>"
                        + "<?= empty($productTagId) ? "" : "&product_tag_ids=" . $productTagId ?>"
                        + "<?= empty($eventTypeId) ? "" : "&event_type_ids=" . $eventTypeId ?>"
                        + "<?= empty($locationId) ? "" : "&location_ids=" . $locationId ?>"
                        + "&limit=<?= $selectLimit ?>"
                        + "&include_locations=true";

                    $("body").addClass("waiting-for-ajax");

                    loadAjaxRequest(url, function(returnArray) {
                        $(`#${wrapperElementId} .event`).remove();

                        const eventTypeURLTemplate = "<?= getPageTextChunk("EVENT_TYPE_URL_TEMPLATE") ?: "/event-type-details?id=%event_type_id%" ?>";
                        for (let [eventTypeId, eventType] of Object.entries(returnArray.event_types)) {
                            let eventTypeTags = "";
                            if (eventType.tags) {
                                for (const [eventTypeTagGroup, eventTypeTagValues] of Object.entries(eventType.tags)) {
                                    eventTypeTagValues.forEach(eventTypeTagValue => {
                                        eventTypeTags += `<span class="event-type-tag event-type-tag-${eventTypeTagGroup}">${eventTypeTagValue}</span>`;
                                    });
                                }
                            }
                            eventType.tags = eventTypeTags;
                            eventType.price = empty(eventType.price) ? "" : RoundFixed(eventType.price, eventType.price % 1 !== 0 ? 2 : 0);
                            eventType.url = eventTypeURLTemplate;
                            const replacementFields = ["event_type_id", "event_type_code", "link_name"];
                            replacementFields.forEach(replacementField => {
                                eventType.url = eventType.url.replaceAll(`%${replacementField}%`, eventType[replacementField]);
                            });

                            // Add event_type prefix so same name fields (e.g. description) doesn't conflict with event field substitution values
                            for (let [eventTypeField, eventTypeValue] of Object.entries(eventType)) {
                                if (!eventTypeField.startsWith("event_type")) {
                                    eventType[`event_type_${eventTypeField}`] = eventTypeValue;
                                    delete eventType[eventTypeField];
                                }
                            }
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
                                replacementFields['end_time'] = $.formatDate(endDate, eventTimeFormat);
                                replacementFields['day'] = $.formatDate(startDate, eventDayFormat);
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
                                        facilities += massageTaggedEventsContent($(`#${wrapperElementId}_facility_fragment`).html(), facilityReplacementFields);
                                    }
                                });
                                replacementFields['facilities'] = facilities;
                                $(`#${wrapperElementId}`).append(massageTaggedEventsContent($(`#${wrapperElementId}_fragment`).html(), replacementFields));

                                if (!empty(event.product_id) && !empty(event.in_wishlist)) {
                                    const addToWishlist = $(".add-to-wishlist-" + event.product_id);
                                    const inWishlistText = addToWishlist.data("in_text");
                                    if (!empty(inWishlistText)) {
                                        addToWishlist.html(inWishlistText);
                                    }
                                }
                            });

                        $(`#${wrapperElementId}`).removeClass("hidden");

                        if (empty(returnArray.events)) {
                            $(`#${wrapperElementId} .no-events-message`).removeClass("hidden");
                        }
                        if (typeof afterGetTaggedEvents == "function") {
                            afterGetTaggedEvents(wrapperElementId, returnArray);
                        }
                    });
                })
            </script>
        <?php

        if (empty($this->iParameters['no_style'])) {
            ?>
            <style>
                .tagged-events .event {
                    border: 1px solid gainsboro;
                    display: inline-block;
                    margin-right: 1rem;
                    margin-bottom: 1rem;
                    max-width: 25rem;
                }
                .event-header {
                    padding: 1rem;
                    align-items: center;
                    display: flex;
                    flex-grow: 0;
                }
                .event-header .event-day {
                    display: block;
                    color: gray;
                    margin-top: 0.25rem;
                }
                .event-header .event-start-time,
                .event-header .event-end-time {
                    display: none;
                }
                .event-header .event-header-details {
                    text-align: right;
                }
                .event-header .event-header-detail {
                    margin-right: 0.5rem;
                    border-right: 1px solid gainsboro;
                    padding-right: 0.5rem;
                }
                .event-header .event-header-detail:last-child {
                    margin: 0;
                    border: 0;
                    padding: 0;
                }
                .event-body {
                    padding: 1rem;
                }
                .event-body .event-title {
                    font-size: 1.5rem;
                    text-transform: uppercase;
                    margin-bottom: 0.5rem;
                }
                .event-body .event-details-link {
                    font-weight: bold;
                    text-transform: uppercase;
                    display: inline-block;
                    font-size: 0.85rem;
                    margin: 1rem 0;
                }
                .event-body .event-product-details {
                    display: flex;
                    align-items: center;
                }
                .event-body .event-product-details .button {
                    background-color: whitesmoke;
                    border: 2px solid whitesmoke;
                    margin: 0 1rem 0 0;
                    font-weight: bold;
                    text-transform: uppercase;
                }
                .event-body .event-product-details .button.event-full {
                    background-color: white;
                }
                .event-body .event-product-details span {
                    font-size: 0.85rem;
                    color: gray;
                }
                .event-footer {
                    padding: 1rem;
                }
            </style>
            <?php
        }
	}

}
