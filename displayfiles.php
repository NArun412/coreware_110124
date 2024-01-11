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

$GLOBALS['gPageCode'] = "DISPLAYFILES";
require_once "shared/startup.inc";

class DisplayFilesPage extends Page {

	function mainContent() {
		echo $this->iPageData['content'];
		$parameters = array($GLOBALS['gClientId']);
		if (!empty($_GET['folder'])) {
			$parameters[] = $_GET['folder'];
			$resultSet = executeQuery("select * from files where client_id = ?" .
				($GLOBALS['gUserRow']['administrator_flag'] ? " and (administrator_access = 1 or all_user_access = 1 or public_access = 1)" :
					($GLOBALS['gLoggedIn'] ? " and (all_user_access = 1 or public_access = 1)" : " and public_access = 1")) .
				(empty($_GET['folder']) ? "" : " and file_folder_id in (select file_folder_id from file_folders where file_folder_code = ?)") .
				" order by sort_order,date_uploaded desc,description", $parameters);
			while ($row = getNextRow($resultSet)) {
				if (canAccessFile($row['file_id'])) {
					?>
                    <div class='file-section'>
						<?= makeHtml($row['detailed_description']) ?>
                        <h3><a id="_download_file_id_<?= $row['file_id'] ?>" href="/download.php?id=<?= $row['file_id'] ?>"><?= $row['description'] ?></a></h3>
                    </div>
					<?php
				}
			}
		}
		echo $this->iPageData['after_form_content'];
		return true;
	}
}

$pageObject = new DisplayFilesPage();
$pageObject->displayPage();
