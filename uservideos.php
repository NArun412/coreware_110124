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

$GLOBALS['gPageCode'] = "USERVIDEOS";
require_once "shared/startup.inc";

class UserVideosPage extends Page {

    function mainContent() {
        echo $this->iPageData['content'];
        $resultSet = executeQuery("select * from user_media join media using (media_id) where user_media.user_id = ? and (start_date is null or start_date >= current_date) and inactive = 0 order by user_media_id desc", $GLOBALS['gUserId']);
        while ($row = getNextRow($resultSet)) {
            $mediaServicesRow = getRowFromId("media_services", "media_service_id", $row['media_service_id']);
            ?>
            <div class="media-player">
                <div class='embed-container'>
                    <iframe src="//<?= $mediaServicesRow['link_url'] . $row['video_identifier'] ?>" allow="encrypted-media" allowfullscreen></iframe>
                </div>
            </div>
            <?php
        }

        echo $this->iPageData['after_form_content'];
        return true;
    }
}

$pageObject = new UserVideosPage();
$pageObject->displayPage();
