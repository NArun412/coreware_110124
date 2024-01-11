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

$GLOBALS['gPageCode'] = "PRINTFULTTOKEN";
require_once "shared/startup.inc";

class PrintfulTokenPage extends Page {

    function mainContent() {
        if (!empty($_GET['state']) && !empty($_GET['code'])) {
            $stateArray = array();
            parse_str($_GET['state'], $stateArray);
            $printful = ProductDistributor::getProductDistributorInstance($stateArray['location_id']);
            if(!is_a($printful, "Printful")) {
                $result = "Invalid Printful location ID: " . htmlText($_GET['state']);
            } else {
                $getTokenResult = $printful->getAccessToken($_GET['code']);
                if ($getTokenResult === true) {
                    $result = 'Printful Access Token set successfully.';
                } else {
                    $result = 'Printful Access Token request failed: ' . $getTokenResult;
                }
            }
        } else {
            $result = 'This page must be called from Distributor Credentials.';
        }
        ?>
        <P><?=$result?></P>
        <script>
            setTimeout(function () {
                window.location = "<?= $stateArray['referrer'] ?: "/" ?>";
            }, 2000);
        </script>

        <?php
    }

}

$pageObject = new PrintfulTokenPage();
$pageObject->displayPage();
