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

$GLOBALS['gPageCode'] = "CONTACTMAP";
require_once "shared/startup.inc";

class ContactMapPage extends Page {

	function displayPage() {
		$this->executeUrlActions();
		$googleMapsKey = getPreference("GOOGLE_API_KEY");
		?>
        <!DOCTYPE html>
        <html xmlns:og="http://opengraphprotocol.org/schema/" xmlns:fb="http://www.facebook.com/2008/fbml" itemscope itemtype="http://schema.org/Thing" lang="en">

        <head>
            <title>Contacts Map</title>
            <script src="https://maps.google.com/maps/api/js?key=<?= $googleMapsKey ?>"></script>
            <script src="<?= autoVersion("/js/jquery-3.4.0.min.js") ?>"></script>
            <script src="<?= autoVersion("/js/jquery-migrate-3.0.1.min.js") ?>"></script>
            <script src="/js/markerclusterer.js"></script>
			<?php $this->onLoadJavascript() ?>
			<?php $this->javascript() ?>
			<?php $this->internalCSS() ?>

        </head>
        <body>
        <h3>Contact Map</h3>
        <div id="map-container">
            <div id="map"></div>
        </div>
        </body>
        </html>
		<?php
	}

	function javascript() {
		$resultSet = executeQuery("select contact_id,address_1,city,latitude,longitude,(select latitude from postal_codes where latitude is not null and postal_code = contacts.postal_code and " .
			"country_id = 1000 limit 1) postal_latitude,(select longitude from postal_codes where longitude is not null and postal_code = contacts.postal_code and " .
			"country_id = 1000 limit 1) postal_longitude from contacts where client_id = ? and country_id = 1000 and contact_id in (select primary_identifier from " .
			"selected_rows where user_id = ? and page_id = (select page_id from pages where page_code = 'CONTACTMAINT'))", $GLOBALS['gClientId'], $GLOBALS['gUserId']);
		$points = array();
		$usedPoints = array();
		$totalLatitude = 0;
		$totalLongitude = 0;
		while ($row = getNextRow($resultSet)) {
			if (empty($row['latitude'])) {
				$row['latitude'] = $row['postal_latitude'];
				$row['longitude'] = $row['postal_longitude'];
			}
			if (empty($row['latitude']) || empty($row['longitude'])) {
				continue;
			}
			if (in_array($row['latitude'] . ":" . $row['longitude'], $usedPoints)) {
				continue;
			}
			$totalLatitude += $row['latitude'];
			$totalLongitude += $row['longitude'];
			$points[] = array("latitude" => $row['latitude'], "longitude" => $row['longitude'], "display_name" => getDisplayName($row['contact_id']) . ", " . $row['address_1'] . ", " . $row['city']);
			$usedPoints[] = $row['latitude'] . ":" . $row['longitude'];
		}
		$centerLatitude = $totalLatitude / count($points);
		$centerLongitude = $totalLongitude / count($points);
		?>
        <!--suppress JSUnresolvedVariable -->
        <script>
            const points = <?= jsonEncode($points) ?>;

            function addInfoWindow(marker, message, map) {
                const info = message;
                const infoWindow = new google.maps.InfoWindow({
                    content: message
                });
                google.maps.event.addListener(marker, 'click', function () {
                    map.panTo(marker.getPosition());
                    map.panBy(0, 0);
                    infoWindow.open(map, marker);
                });
            }

            function initialize() {
                const mapOptions = {
                    zoom: 6,
                    center: new google.maps.LatLng(<?= $centerLatitude ?>, <?= $centerLongitude ?>),
                    mapTypeId: 'roadmap'
                };
                const map = new google.maps.Map(document.getElementById('map'), mapOptions);

                const markers = [];
                for (const i in points) {
                    const latLng = new google.maps.LatLng(points[i].latitude, points[i].longitude);
                    const marker = placeOnMap(map, latLng, points[i].display_name);
                    markers.push(marker);
                }
                const mcOptions = {maxZoom: 10, minimumClusterSize: 40};
                const markerCluster = new MarkerClusterer(map, markers, mcOptions);
            }

            function placeOnMap(map, latLng, displayName) {
                const marker = new google.maps.Marker({
                    position: latLng, content: "<div style='min-width: 220px; font-size: 14px; font-weight: bold;'> Contact: " + displayName + "</div>", title: displayName, map: map
                });
                google.maps.event.addListener(marker, 'click', function () {
                    const infowindow = new google.maps.InfoWindow({
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

	function internalCSS() {
		?>
        <style>
			body {
				margin: 0;
				padding: 10px 20px 20px;
				font-family: Arial, sans-serif;
				font-size: 16px;
			}

			#map-container {
				padding: 6px;
				border-width: 1px;
				border-style: solid;
				border-color: #ccc #ccc #999 #ccc;
				-webkit-box-shadow: rgba(64, 64, 64, 0.5) 0 2px 5px;
				-moz-box-shadow: rgba(64, 64, 64, 0.5) 0 2px 5px;
				box-shadow: rgba(64, 64, 64, 0.1) 0 2px 5px;
				width: 100%;
				height: 850px;
			}

			#map {
				width: 100%;
				height: 100%;
			}

			#actions {
				list-style: none;
				padding: 0;
			}

			.item {
				margin-left: 20px;
			}
        </style>
		<?php
	}
}

$pageObject = new ContactMapPage();
$pageObject->displayPage();
