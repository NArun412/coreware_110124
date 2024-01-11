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

$GLOBALS['gPageCode'] = "RETAILSTORESTORELOCATOR";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function headerIncludes() {
		$apiKey = getPreference("GOOGLE_API_KEY");
		?>
        <script src="//maps.google.com/maps/api/js?key=<?php echo $apiKey ?>"></script>
		<?php
	}

	function showSearchForm() {
		$contactTypeCode = getFieldFromId("contact_type_code", "contact_types", "contact_type_code", $_GET['contact_type'], "inactive = 0");
		?>
        <input type="hidden" id="contact_type" name="contact_type" value="<?= $contactTypeCode ?>">
        <div id="store_search">
            <label>Search by Zip Code, Store Name or City</label>
            <input id="store_search_text" type="text">
            <button id="search_store">Search Stores</button>
        </div>
        <p id="search_results"></p>

        <div id="map_wrapper">
            <div id="text_results"></div>
            <div id="map"></div>
        </div>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #map_wrapper {
                width: 100%;
                border-right: 1px solid rgb(200, 200, 200);
                margin-top: 20px;
                display: flex;
            }
            #text_results {
                flex: 0 0 250px;
                height: 600px;
                overflow: scroll;
                background-color: rgb(240, 240, 240);
                padding: 10px;
            }
            #text_results ::-webkit-scrollbar {
                -webkit-appearance: none;
                width: 10px;
            }
            #text_results ::-webkit-scrollbar-thumb {
                border-radius: 5px;
                background-color: rgba(0, 0, 0, .5);
                -webkit-box-shadow: 0 0 1px rgba(255, 255, 255, .5);
            }
            #text_results .store-result {
                border-bottom: 1px solid rgb(200, 200, 200);
                padding-top: 5px;
                cursor: pointer;
            }
            #text_results .store-result p {
                font-size: .7rem;
                margin: 0;
                margin-bottom: 5px;
            }
            #text_results .store-result p.store-name {
                font-size: .8rem;
                font-weight: 700;
            }
            #map {
                flex: 1 1 auto;
                height: 600px;
                background-color: rgb(210, 210, 210);
            }
            #store_search label {
                display: block;
                margin-bottom: 5px;
            }
            #store_search input {
                display: block;
                margin-bottom: 5px;
            }
            #store_search button {
                display: block;
                margin-bottom: 5px;
            }
            #store_search_text {
                width: 200px;
                font-size: 1rem;
                padding: 5px 10px;
                border-radius: 5px;
            }
        </style>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_locations":
				if (empty($_GET['search_text'])) {
					$_GET['search_text'] = $GLOBALS['gUserRow']['postal_code'];
				}

				$returnArray['locations'] = array();
				$parameters = array();
				if (!empty($_GET['contact_type'])) {
					$parameters['contact_type_code'] = $_GET['contact_type'];
				}
				if (!empty($_GET['contact_type_code'])) {
					$parameters['contact_type_code'] = $_GET['contact_type_code'];
				}
				if (!empty($_GET['contact_type_id'])) {
					$parameters['contact_type_id'] = $_GET['contact_type_id'];
				}
				$parameters['city'] = "%" . $_GET['search_text'] . "%";
				$parameters['postal_code'] = "%" . $_GET['search_text'] . "%";
				$parameters['business_name'] = "%" . $_GET['search_text'] . "%";
				$parameters['state'] = $_GET['search_text'];
                $fflRecords = FFL::getFFLRecords($parameters);
				foreach ($fflRecords as $row) {
					if (empty($row['latitude']) || empty($row['longitude'])) {
						continue;
					}
					$row['google_map'] = "https://www.google.com/maps/dir/?api=1&dir_action=navigate&destination=" . urlencode($row['business_name'] . " " . $row['address_1'] . " " . $row['address_2'] . " " . $row['city'] . " " . $row['state'] . " " . $row['postal_code']);
					$returnArray['locations'][] = $row;
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function javascript() {
		$ipAddress = $_SERVER['REMOTE_ADDR'];
		$ipAddressData = getRowFromId("ip_address_metrics", "ip_address", $ipAddress);
		if (empty($ipAddressData)) {
			$curlHandle = curl_init("http://ip-api.com/json/" . $ipAddress);
			curl_setopt($curlHandle, CURLOPT_HEADER, 0);
			curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
			curl_setopt($curlHandle, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
			$ipAddressRaw = curl_exec($curlHandle);
			curl_close($curlHandle);
			if (empty($ipAddressRaw)) {
				$ipAddressData = array();
			} else {
				$ipAddressData = json_decode($ipAddressRaw, true);
			}
			if (!empty($ipAddressData)) {
				executeQuery("insert ignore into ip_address_metrics (ip_address,country_id,city,state,postal_code,latitude,longitude) values (?,?,?,?,?, ?,?)",
					$ipAddress, getFieldFromId("country_id", "countries", "country_code", $ipAddressData['countryCode']), $ipAddressData['city'], $ipAddressData['region'],
					$ipAddressData['zip'], $ipAddressData['lat'], $ipAddressData['lon']);
				$ipAddressData = getRowFromId("ip_address_metrics", "ip_address", $ipAddress);
			}
		}
		if (empty($ipAddressData) || empty($ipAddressData['latitude']) || empty($ipAddressData['longitude'])) {
			$ipAddressData = array('latitude' => "39.828354", "longitude" => "-98.579468");
		}
		?>
        <script>
            var map;
            var markers = [];
            var infoWindow;

            function initializeMap() {
                var myLatlng = new google.maps.LatLng(<?= $ipAddressData['latitude'] ?>, <?= $ipAddressData['longitude'] ?>);
                var myOptions = {
                    zoom: 12,
                    center: myLatlng,
                    mapTypeId: google.maps.MapTypeId.ROADMAP,
                    disableDefaultUI: true,
                    zoomControl: true
                };

                map = new google.maps.Map(document.getElementById('map'), myOptions);
                $("#store_search_text").focus();
                getLocations();
            }

            function clearMarkers() {
                for (var i in markers) {
                    markers[i].setMap(null);
                }
                markers = [];
            }

            function getLocations() {
                clearMarkers();
                $("#search_results").html("");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_locations&search_text=" + encodeURIComponent($("#store_search_text").val()) + "&contact_type=" + encodeURIComponent($("#contact_type").val()), function(returnArray) {
                    if ("locations" in returnArray) {
                        if (returnArray['locations'].length == 0) {
                            $("#search_results").html("No results found");
                        } else {
                            $("#search_results").html(returnArray['locations'].length + " location" + (returnArray['locations'].length == 1 ? "" : "s") + " found");
                        }
                        var centerMap = true;
                        var firstMarker = false;
                        $("#text_results").html("");
                        for (var i in returnArray['locations']) {
                            $("#text_results").append("<div class='store-result'><p class='store-name'>" + returnArray['locations'][i].business_name +
                                "</p><p class='store-address'>" + returnArray['locations'][i].address_1 + "<br>" + returnArray['locations'][i].city + ", " + returnArray['locations'][i].state +
                                " " + returnArray['locations'][i].postal_code + "</p><p class='store-phone'>" + returnArray['locations'][i].phone_number + "</p></div>");
                            var thisPoint = new google.maps.LatLng(returnArray['locations'][i]['latitude'], returnArray['locations'][i]['longitude']);
                            if (centerMap) {
                                map.setCenter(thisPoint);
                                centerMap = false;
                            }
                            var marker = new google.maps.Marker({
                                position: thisPoint,
                                map: map,
                                infoWindow: '<span>' + returnArray['locations'][i].business_name + '</span><br />' +
                                    '<span>' + returnArray['locations'][i].address_1 + '</span>' +
                                    '<span>' + returnArray['locations'][i].address_2 + '</span><br />' +
                                    '<span>' + returnArray['locations'][i].city + ', ' + returnArray['locations'][i].state + ' ' + returnArray['locations'][i].postal_code + '</span><br />' +
                                    '<span>Phone: ' + returnArray['locations'][i].phone_number + '</span><br />' +
                                    '<span><a class="get-directions" target="_blank" href="' + returnArray['locations'][i].google_map + '">Get Directions</a></span>'
                            });
                            markers.push(marker);
                            google.maps.event.addListener(marker, 'click', (function (marker) {
                                return function () {
                                    if (infoWindow != null) {
                                        infoWindow.close();
                                    }
                                    infoWindow = new google.maps.InfoWindow();
                                    var content = marker.infoWindow;
                                    infoWindow.setContent(content);
                                    infoWindow.open(map, marker);
                                }
                            })(marker));
                            if (firstMarker === false) {
                                firstMarker = marker;
                            }
                        }
                        if (firstMarker !== false) {
                            new google.maps.event.trigger(firstMarker, 'click');
                        }
                    }
                });
            }
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#search_store").click(function () {
                if (!empty($("#store_search_text").val())) {
                    getLocations();
                }
                return false;
            });
            $("#store_search_text").keyup(function (event) {
                if (event.which == 13 || event.which == 3) {
                    if (!empty($("#store_search_text").val())) {
                        getLocations();
                    }
                }
                return false;
            });
            initializeMap();
        </script>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
