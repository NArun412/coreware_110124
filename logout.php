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

$GLOBALS['gPageCode'] = "LOGOUT";
$GLOBALS['gPreemptivePage'] = true;
$GLOBALS['gAuthorizeComputer'] = true;
$GLOBALS['gIgnoreNotices'] = true;
$GLOBALS['gEmbeddablePage'] = true;
$GLOBALS['gPasswordReset'] = true;
require_once "shared/startup.inc";

if ($GLOBALS['gLoggedIn']) {
	addSecurityLog($GLOBALS['gUserRow']['user_name'],"LOGOUT","User Logged Out");
}
$originalUserId = "";
if (!empty($_SESSION['original_user_id'])) {
    $newUserId = $_SESSION['original_user_id'];
    $_SESSION['original_user_id'] = "";
    unset($_SESSION['original_user_id']);
	saveSessionData();
    $resultSet = executeQuery("select * from users where user_id = ? and inactive = 0 and client_id = ?", $newUserId, $GLOBALS['gClientId']);
    if ($row = getNextRow($resultSet)) {
        $originalUserId = $row['user_id'];
    }
}
logout();
if (!empty($originalUserId)) {
	login($originalUserId);
}

$linkUrl = $_GET['url'];
if (empty($linkUrl)) {
	header("Location: /");
} else {
	header("Location: " . $linkUrl);
}
