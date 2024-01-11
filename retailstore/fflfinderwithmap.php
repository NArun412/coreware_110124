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

$GLOBALS['gPageCode'] = "RETAILSTOREFFLFINDERWITHMAP";
require_once "shared/startup.inc";

class RetailStoreFFLFinderWithMapPage extends Page {

    function javascript() {
        ?>
        let fflDealers = [];
        let markers = {};
        let fflDealersMap;
        let previousInfoWindow;
        <?php
    }

    function onLoadJavascript() {
        $showAll = false;
        if (!empty($_GET['preferred_only']) && !empty($_GET['show_all'])) {
            $showAll = true;
        }
        ?>
        <script>
            <?php if ($showAll) { ?>
            getFFLDealers(true);
            <?php } ?>
            $(document).on("change", "#postal_code, #ffl_radius", function () {
                searchFFLs();
            });
            $(document).on("click", "#ffl_search", function () {
                searchFFLs();
            });
            $("#ffl_dealer_filter").keyup(function (event) {
                const textFilter = $(this).val().toLowerCase();
                if (empty(textFilter)) {
                    $("ul#ffl_dealers li").removeClass("hidden");
                } else {
                    $("ul#ffl_dealers li").each(function () {
                        const description = $(this).html().toLowerCase();
                        if (description.indexOf(textFilter) >= 0) {
                            $(this).removeClass("hidden");
                        } else {
                            $(this).addClass("hidden");
                        }
                    });
                }
            });

            $(document).on("click", ".ffl-dealer", function(e){
                if (!empty(previousInfoWindow)) {
                    previousInfoWindow.close();
                }
                const fflId = $(this).data("federal_firearms_licensee_id");
                const marker = markers[fflId];
                const infoWindowContent =  $(this).html();
                const infoWindow = new google.maps.InfoWindow({
                    content: infoWindowContent
                });
                fflDealersMap.panTo(marker.getPosition());
                fflDealersMap.setZoom(20);
                infoWindow.open(fflDealersMap, marker);
                previousInfoWindow = infoWindow;
            });


            if ("geolocation" in navigator) {
                navigator.geolocation.getCurrentPosition(function (position) {
                        currentLatitude = position.coords.latitude;
                        currentLongitude = position.coords.longitude;
                        let center = { lat: currentLatitude, lng: currentLongitude };
                        fflDealersMap = new google.maps.Map(document.getElementById("ffl_dealers_map"), {
                            zoom: 10,
                            center: center
                        });
                    },
                    function (error) {
                       fflDealersMap = new google.maps.Map(document.getElementById("ffl_dealers_map"), {
                            zoom: 4,
                            center:  { lat: 39.000, lng: -80.500 }
                        });
                    }
                );
            }

            function removeMarkers(){
                for(let i = 0; i < markers.length; i++){
                    markers[i].setMap(null);
                }
            }

            function searchFFLs() {
                removeMarkers();
                fflDealersMap.setZoom(10);
                getFFLDealers(<?= (empty($_GET['preferred_only']) ? "''" : "true") ?>, true);
            }
        </script>
        <?php
    }

    function mainContent() {
        echo $this->iPageData['content'];
        $showAll = false;
        if (!empty($_GET['preferred_only']) && !empty($_GET['show_all'])) {
            $showAll = true;
        }
        ?>
        <div id="_ffl_finder_wrapper">
            <?php if (!$showAll) { ?>
                <?php
                echo createFormLineControl("contacts", "postal_code", array("no_required_label" => true, "not_null" => true, "data-conditional-required" => "$(\"#country_id\").val() < 1002", "initial_value" => $GLOBALS['gUserRow']['postal_code']));
                ?>
                <button id="ffl_search">Search</button>

                <div id="ffl_dealer_wrapper">
                    <h2><?= getLanguageText("FFL Dealer") ?></h2>
                    <p id="ffl_dealer_count_paragraph"><span id="ffl_dealer_count"></span> <?= getLanguageText("Dealers found within") ?> <select id="ffl_radius">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                            <option value="500">500</option>
                        </select> <?= getLanguageText("miles. Choose one below") ?>.
                    </p>
                    <input tabindex="10" type="text" placeholder="<?= getLanguageText("Search/Filter Dealers") ?>" id="ffl_dealer_filter">
                    <div id="ffl_dealers_wrapper">
                        <ul id="ffl_dealers">
                        </ul>
                    </div>
                    <div id="ffl_dealers_map_wrapper">
                        <div id="ffl_dealers_map">
                        </div>
                    </div>
                </div>
            <?php } else { ?>
                <div id="ffl_dealer_wrapper">
                    <input type='hidden' id="postal_code" value='-1'>
                    <input type='hidden' id="ffl_radius" value='5000'>
                    <input tabindex="10" type="text" placeholder="<?= getLanguageText("Search/Filter Dealers") ?>" id="ffl_dealer_filter">
                    <div id="ffl_dealers_wrapper">
                        <ul id="ffl_dealers">
                        </ul>
                    </div>
                    <div id="ffl_dealers_map_wrapper">
                        <div id="ffl_dealers_map">
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>
        <?php
        echo $this->iPageData['after_form_content'];
        return true;
    }

    function headerIncludes() {
        echo $this->getPageTextChunk("includes");
        $googleMapsKey = getPreference("GOOGLE_API_KEY");
        ?>
        <script src="https://maps.google.com/maps/api/js?key=<?= $googleMapsKey ?>"></script>
        <script src="/js/markerclusterer.js"></script>
        <?php
    }

    function internalCSS() {
        ?>
        <style>
            #_ffl_finder_wrapper {
                background-color: rgb(255, 255, 255);
                padding: 30px;
            }

            #ffl_selection_section {
                display: none;
            }

            #ffl_dealers_wrapper {
                height: 600px;
                overflow: scroll;
                width: 24%;
                float: left;
            }

            #ffl_dealers li {
                padding: 5px 10px;
                cursor: pointer;
                background-color: rgb(220, 220, 220);
                border-bottom: 1px solid rgb(200, 200, 200);
                line-height: 1.2;
            }

            #ffl_dealers li:hover {
                background-color: rgb(180, 190, 200);
            }

            #ffl_dealers li.preferred {
                font-weight: 900;
            }

            #ffl_dealers li.have-license {
                background-color: rgb(180, 230, 180);
            }

            #selected_ffl_dealer {
                font-weight: 900;
                font-size: 1.4rem;
            }

            #ffl_dealer_filter {
                display: block;
                font-size: 1.2rem;
                padding: 5px;
                border-radius: 5px;
                width: 400px;
                margin-bottom: 5px;
            }
            #ffl_dealers_map_wrapper{
                display: block;
                float: left;
                width: 76%;
            }

            #ffl_dealers_map{
                height: 600px;
                /* The height is 400 pixels */
                width: 100%;
                /* The width is the width of the web page */
            }

        </style>
        <?php
    }
}

$pageObject = new RetailStoreFFLFinderWithMapPage();
$pageObject->displayPage();
