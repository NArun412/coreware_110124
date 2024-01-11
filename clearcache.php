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

$GLOBALS['gPageCode'] = "CLEARCACHE";
require_once "shared/startup.inc";

class ClearCachePage extends Page {

	function mainContent() {
		if ($GLOBALS['gUserRow']['superuser_flag']) {
			if (empty($_GET['confirmed'])) {
				clearClientCache();
				addProgramLog("Cache cleared for client '" . $GLOBALS['gClientRow']['client_code'] . " by " . getUserDisplayName());
				echo "<p>This Client's cache cleared.</p>";
				echo "<p>Clearing the full cache causes ALL client sites on this server to be slow until the cache is reloaded. DO NOT do this unless it is absolutely necessary.</p><p><a class='button' href='/clearcache.php?confirmed=yes'>Clear Full Cache</a></p>";
			} else {
				if ($GLOBALS['gApcuEnabled']) {
					apcu_clear_cache();
					triggerServerClearCache();
				}
				echo "<p>Cache Cleared</p>";
				addProgramLog("Full Cache cleared by superuser - " . getUserDisplayName());
			}
			return true;
		} else if (!empty($_GET['access_code']) && $_GET['access_code'] == $this->getPageTextChunk("access_code")) {
			if ($GLOBALS['gApcuEnabled']) {
				apcu_clear_cache();
				triggerServerClearCache();
			}
			executeQuery("delete from page_text_chunks where page_id = ?", $GLOBALS['gPageRow']['page_id']);
			echo "<p>Cache Cleared</p>";
            addProgramLog("Cache cleared by access code");
		} else {
			if ($GLOBALS['gUserRow']['full_client_access']) {
				$canClear = true;
			} else {
				$canClear = getFieldFromId("user_access_id", "user_access", "user_id", $GLOBALS['gUserId'], "page_id = ? and permission_level = 3", $GLOBALS['gPageId']);
			}
			if ($canClear) {
				clearClientCache();
				echo "<p>Cache Cleared</p>";
				addProgramLog("Cache cleared for client '" . $GLOBALS['gClientRow']['client_code'] . " by " . getUserDisplayName());
			} else {
				echo "<p>Permission not granted to clear cache</p>";
			}
		}
		return true;
	}
}

$pageObject = new ClearCachePage();
$pageObject->displayPage();
