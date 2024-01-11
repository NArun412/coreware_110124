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

class SystemNotices {

    private $iSystemNoticeId = false;
    private $iSystemNoticesRow = array();
    private $iUserId = array();

    function __construct($systemNoticeId="") {
        $this->iUserId = $GLOBALS['gUserId'];
        if (!empty($systemNoticeId)) {
            if (is_array($systemNoticeId)) {
                $this->iSystemNoticeId = $systemNoticeId;
            } else {
                $resultSet = executeQuery("select * from system_notices where system_notice_id = ? and client_id = ?",$systemNoticeId,$GLOBALS['gClientId']);
                if ($row = getNextRow($resultSet)) {
                    $this->iSystemNoticesRow = $row;
                    $this->iSystemNoticeId = $row['system_notice_id'];
                } else {
                    $this->iErrorMessage = "System Notice not found";
                }
            }
        }
    }

    function getErrorMessage() {
        return $this->iErrorMessage;
    }

    function setUserId($userId) {
        $this->iUserId = getFieldFromId("user_id","users","user_id",$userId);
        if (empty($this->iUserId)) {
            $this->iUserId = $GLOBALS['gUserId'];
        }
    }

    function getSystemNoticeList($unreadOnly = true,$includeDeleted = false) {
        $resultSet = executeQuery("select * from system_notices where client_id = ? and (start_time is null or start_time <= current_time) and " .
            "(end_time is null or end_time >= current_time)" . ($unreadOnly ? " and system_notice_id not in (select system_notice_id from system_notice_users where " .
                "user_id = " . $GLOBALS['gUserId'] . " and time_read is not null)" : "") .
            ($includeDeleted ? "" : " and system_notice_id not in (select system_notice_id from system_notice_users where " .
                "user_id = " . $GLOBALS['gUserId'] . " and time_deleted is not null)"), $GLOBALS['gClientId']);
        $systemNotices = array();
        while ($row = getNextRow($resultSet)) {
            $systemNoticeUserRow = getRowFromId("system_notice_users", "system_notice_id", $row['system_notice_id'], "user_id = ?", $GLOBALS['gUserId']);
            $canRead = (!empty($systemNoticeUserRow));
            if ($row['all_user_access']) {
                $canRead = true;
            }
            if (!$canRead && !empty($row['full_client_access'])) {
                $fullClientAccess = getFieldFromId("full_client_access","users","user_id",$this->iUserId);
                if (!empty($fullClientAccess)) {
                    $canRead = true;
                }
            }
            if (!$canRead && !empty($row['user_group_id'])) {
                if (isInUserGroup($this->iUserId,$row['user_group_id'])) {
                    $canRead = true;
                }
            }
            if ($canRead) {
                $row['system_notice_users'] = $systemNoticeUserRow;
                $systemNotices[] = $row;
            }
        }
        return $systemNotices;
    }

    function getCount($unreadOnly = true,$includeDeleted = false) {
        $systemNotices = $this->getSystemNoticeList($unreadOnly,$includeDeleted);
        return count($systemNotices);
    }
}
