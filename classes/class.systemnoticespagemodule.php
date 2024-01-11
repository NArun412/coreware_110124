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
Generates an HTML list of system notices for the user.

%module:system_notices:wrapper_element_id=element_id%
*/

class SystemNoticesPageModule extends PageModule {
    function createContent() {
        $wrapperElementId = $this->iParameters['wrapper_element_id'];
        if (empty($wrapperElementId)) {
            $wrapperElementId = "system_notice_wrapper";
        }
        $systemNotices = array();
        if ($GLOBALS['gLoggedIn']) {
            $resultSet = executeQuery("select * from system_notices where inactive = 0 and client_id = ? and " .
                "(start_time is null or start_time <= current_time) and " .
                "system_notice_id not in (select system_notice_id from system_notice_users where user_id = " . $GLOBALS['gUserId'] . " and (time_read is not null or time_deleted is not null)) and " .
                "(end_time is null or end_time >= current_time) and (all_user_access = 1 or system_notice_id in " .
                "(select system_notice_id from system_notice_users where user_id = " . $GLOBALS['gUserId'] . ")" .
                (empty($GLOBALS['gUserRow']['superuser_flag']) && empty($GLOBALS['gUserRow']['full_client_access']) ? "" : " or full_client_access = 1") . ") order by time_submitted", $GLOBALS['gClientId']);
            while ($row = getNextRow($resultSet)) {
                $systemNotices[] = $row;
            }
        }
        if (!empty($systemNotices)) {
            ?>
            <div id="<?= $wrapperElementId ?>">
                <style>
                    .page-module-system-notice {
                        cursor: pointer;
                    }
                </style>
                <?php
                foreach ($systemNotices as $row) {
                    ?>
                    <div<?= (empty($row['display_color']) ? "" : " style='background-color: " . $row['display_color'] . ";'") ?> class='page-module-system-notice' data-system_notice_id="<?= $row['system_notice_id'] ?>" data-require_acceptance="<?= $row['require_acceptance'] ?>"><u class='system-notice-action'><?= (empty($row['require_acceptance']) ? "dismiss" : "acknowledge") . "</u> " . htmlText($row['subject']) ?></div>
                    <?php
                }
                ?>
            </div>
            <?php
        }
    }
}
