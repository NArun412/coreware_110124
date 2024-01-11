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

$GLOBALS['gPageCode'] = "LOCATIONSMAP";
require_once "shared/startup.inc";

class LocationsMap extends Page {

    var $iLocations = array();
    var $iCenterLatitude = "";
    var $iCenterLongitude = "";

    function setup() {
        $resultSet = executeQuery("select *,(select latitude from postal_codes where latitude is not null and postal_code = contacts.postal_code and " .
            "country_id = 1000 limit 1) postal_latitude,(select longitude from postal_codes where longitude is not null and postal_code = contacts.postal_code and " .
            "country_id = 1000 limit 1) postal_longitude from contacts join locations using (contact_id) where locations.client_id = ? and country_id = 1000 and product_distributor_id is null", $GLOBALS['gClientId']);
        $totalLatitude = 0;
        $totalLongitude = 0;
        while ($row = getNextRow($resultSet)) {
            $locationStatusRow = array();
            if (!empty($row['location_status_id'])) {
                $locationStatusRow = getRowFromId("location_statuses", "location_status_id", $row['location_status_id']);
            }
            $row['location_status'] = $locationStatusRow;
            if (empty($row['latitude'])) {
                $row['latitude'] = $row['postal_latitude'];
                $row['longitude'] = $row['postal_longitude'];
            }
            if (empty($row['latitude']) || empty($row['longitude'])) {
                continue;
            }
            $row['display_name'] = getDisplayName($row['contact_id']);
            $row['phone_numbers'] = array();
            $phoneSet = executeQuery("select * from phone_numbers where contact_id = ?", $row['contact_id']);
            while ($phoneRow = getNextRow($phoneSet)) {
                if (!empty($phoneRow['phone_type_id'])) {
                    $phoneRow['description'] = getFieldFromId("description", "phone_types", "phone_type_id", $phoneRow['phone_type_id']);
                }
                $row['phone_numbers'] = $phoneRow;
            }
            $totalLatitude += $row['latitude'];
            $totalLongitude += $row['longitude'];
            $this->iLocations[] = $row;
        }
        $this->iCenterLatitude = $totalLatitude / count($this->iLocations);
        $this->iCenterLongitude = $totalLongitude / count($this->iLocations);
    }

    function javascript() {
        ?>
        <script>
            let currentLatitude = "";
            let currentLongitude = "";
            let locations = <?= jsonEncode($this->iLocations) ?>;

            function getLocationElements() {
                let fullContent = "";
                for (const index in locations) {
                    let thisElement = "<div class='location-element'><span class='business-name'>" + locations[index]['business_name'] + "</span>" +
                        "<span class='address-1'>" + locations[index]['address_1'] + "</span>" +
                        (empty(locations[index]['address_2']) ? "" : "<span class='address-2'>" + locations[index]['address_2'] + "</span>") +
                        (empty(locations[index]['city']) ? "" : "<span class='city'>" + locations[index]['city'] + (empty(locations[index]['state']) ? "" : ", " + locations[index]['state']) + (empty(locations[index]['postal_code']) ? "" : " " + locations[index]['postal_code']) + "</span>") +
                        (empty(locations[index]['email_address']) ? "" : "<span class='email-address'>" + locations[index]['email_address'] + "</span>");
                    for (const phoneIndex in locations['phone_numbers']) {
                        thisElement += locations['phone_numbers'][phoneIndex]['description'] + " - " + locations['phone_numbers'][phoneIndex]['phone_number'];
                    }
                    thisElement += "</div>";
                    fullContent += thisElement;
                }
                return fullContent;
            }

            function addInfoWindow(marker, message, map) {
                let info = message;
                let infoWindow = new google.maps.InfoWindow({
                    content: message
                });
                google.maps.event.addListener(marker, 'click', function () {
                    map.panTo(marker.getPosition());
                    map.panBy(0, 0);
                    infoWindow.open(map, marker);
                });
            }

            function initialize() {
                if ($("#map").length === 0) {
                    return;
                }
                const mapOptions = {
                    zoom: 6,
                    center: new google.maps.LatLng(<?= $this->iCenterLatitude ?>, <?= $this->iCenterLongitude ?>),
                    mapTypeId: 'roadmap'
                };
                const map = new google.maps.Map(document.getElementById('map'), mapOptions);

                let markers = [];
                for (const locationIndex in locations) {
                    const latLng = new google.maps.LatLng(locations[locationIndex].latitude, locations[locationIndex].longitude);
                    let displayName = "";
                    let markerContent = "";
                    if (typeof generateMarkerTitle === "function") {
                        displayName = generateMarkerTitle();
                    } else {
                        displayName = locations[locationIndex].display_name;
                    }
                    if (typeof generateMarkerContent === "function") {
                        markerContent = generateMarkerTitle();
                    } else {
                        markerContent = locations[locationIndex].display_name;
                        if (!empty(locations[locationIndex].address_1)) {
                            markerContent += "<br>" + locations[locationIndex].address_1
                        }
                        if (!empty(locations[locationIndex].address_2)) {
                            markerContent += "<br>" + locations[locationIndex].address_2;
                        }
                        if (!empty(locations[locationIndex].city)) {
                            markerContent += "<br>" + locations[locationIndex].city + (empty(locations[locationIndex].state) ? "" : ", " + locations[locationIndex].state) + (empty(locations[locationIndex].postal_code) ? "" : " " + locations[locationIndex].postal_code);
                        }
                        for (const phoneIndex in locations['phone_numbers']) {
                            markerContent += "<br>" + locations[locationIndex].phone_numbers[phoneIndex]['description'] + " - " + locations[locationIndex].phone_numbers[phoneIndex]['phone_number'];
                        }
                    }
                    var marker = placeOnMap(map, latLng, displayName, markerContent);
                    markers.push(marker);
                }
                var mcOptions = {maxZoom: 10, minimumClusterSize: 40};
                var markerCluster = new MarkerClusterer(map, markers, mcOptions);
            }

            function placeOnMap(map, latLng, displayName, markerContent) {
                const marker = new google.maps.Marker({
                    position: latLng, content: "<div class='map-marker'>" + markerContent + "</div>", title: displayName, map: map
                });
                google.maps.event.addListener(marker, 'click', function () {
                    var infowindow = new google.maps.InfoWindow({
                        content: marker.content,
                        maxWidth: 500,
                        pane: "floatPane"
                    });
                    infowindow.open(map, marker);
                });
                return marker;
            }

            google.maps.event.addDomListener(window, 'load', initialize);
        </script>
        <?php
    }

    function onLoadJavascript() {
        ?>
        <script>
            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(function (position) {
                    currentLatitude = position.coords.latitude;
                    currentLongitude = position.coords.longitude;
                }, function (error) {
                });
            }
        </script>
        <?php
    }

    function headerIncludes() {
        echo $this->getPageTextChunk("includes");
        $googleMapsKey = getPreference("GOOGLE_API_KEY");
        ?>
        <script src="https://maps.google.com/maps/api/js?key=<?= $googleMapsKey ?>"></script>
        <script src="/js/markerclusterer.js"></script>
        <?php
    }

    function mainContent() {
        echo $this->getPageData("content");
        ?>
        <div id="location_wrapper">
            <div id="location_sidebar">
                <p><input type="text" id="search_locations" name="search_locations" placeholder="Search Locations"></p>
                <div id="locations_controls">
                    <div id="locations_count"><span id="visible_count"><?= count($this->iLocations) ?></span> of <span id="total_count"><?= count($this->iLocations) ?></span></div>
                    <div id="show_all_locations_wrapper"><a href='#' id='show_all_locations'>Show All</a></div>
                </div>
                <div id="location_legend">
                    <?php
                    $displayedLocations = array();
                    foreach ($this->iLocations as $thisLocation) {
                        if (empty($thisLocation['location_status_id']) || in_array($thisLocation['location_status_id'], $displayedLocations)) {
                            continue;
                        }
                        $displayedLocations[] = $thisLocation['location_status_id'];
                        ?>
                        <p class='location-legend'><?= (empty($thisLocation['location_status']['pin_color']) ? "" : "<img class='location-marker' src='http://maps.google.com/mapfiles/ms/icons/" . $thisLocation['location_status']['pin_color'] . ".png'>") ?> <?= $thisLocation['location_status']['description'] ?></p>
                        <?php
                    }
                    ?>
                </div>
                <h2>Locations Near You</h2>
                <div id="location_address_wrapper">
                    <?php
                    foreach ($this->iLocations as $thisLocation) {
                        $displayAddress = $thisLocation['address_1'];
                        $displayCity = $thisLocation['city'];
                        if (!empty($thisLocation['state'])) {
                            $displayCity .= (empty($displayCity) ? "" : ", " . $thisLocation['state']);
                        }
                        if (!empty($thisLocation['postal_code'])) {
                            $displayCity .= (empty($displayCity) ? "" : " " . $thisLocation['postal_code']);
                        }
                        if (!empty($displayCity)) {
                            $displayAddress .= (empty($displayAddress) ? "" : ", ") . $displayCity;
                        }
                        ?>
                        <div class='location-address'>
                            <p class='location-name'><?= $thisLocation['description'] ?></p>
                            <p class='location-address'><span class='fas fa-map-marker-alt'></span><?= $displayAddress ?></p>
                            <?php if (!empty($thisLocation['latitude']) && !empty($thisLocation['longitude'])) { ?>
                                <p class='location-directions'><a target='_blank' href='https://www.google.com/maps?daddr=(<?= $thisLocation['latitude'] ?>,+<?= $thisLocation['longitude'] ?>)'>Get Directions</a></p>
                            <?php } ?>
                        </div>
                        <?php
                    }
                    ?>
                </div>
            </div>
            <div id="location_map">
            </div>
        </div>
        <?php
        echo $this->getPageData("after_form_content");
    }

    function internalCSS() {
        ?>
        <style>
            #location_wrapper {
                display: flex;
                width: 100%;
                height: 800px;
            }

            #location_sidebar {
                flex: 0 0 460px;
                overflow: scroll;
            }

            #location_map {
                height: 800px;
            }
        </style>
        <?php
    }
}

$pageObject = new LocationsMap();
$pageObject->displayPage();
