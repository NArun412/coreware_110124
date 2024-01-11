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

$GLOBALS['gPageCode'] = "SEOREPORT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function executePageUrlActions() {
		if ($_GET['url_action'] == "create_csv") {
			header("Content-Type: text/csv");
			header("Content-Disposition: attachment; filename=seooutput.csv");
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			echo "Page ID,Description,Last Changed,Last Validated,Meta Tag,Value\r";
			$resultSet = executeQuery("select * from pages where inactive = 0 and client_id = ? and " .
				"page_id in (select page_id from page_access where permission_level = 3 and public_access = 1) order by description",
				$GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$pageTitle = $row['window_title'];
				if (empty($pageTitle)) {
					$pageTitle = (empty($row['window_description']) ? $row['description'] : $row['window_description']) . " | " . $GLOBALS['gClientName'];
				}
				$lastUpdated = "";
				$resultSet1 = executeQuery("select max(time_changed) from change_log where (table_name = 'pages' and primary_identifier = ?) or " .
					"(table_name = 'page_data' and primary_identifier in (select page_data_id from page_data where page_id = ?))",
					$row['page_id'],$row['page_id']);
				if ($row1 = getNextRow($resultSet1)) {
					if (!empty($row1['max(time_changed)'])) {
						$lastUpdated = date("m/d/Y",strtotime($row1['max(time_changed)']));
					}
				}
				echo $row['page_id'] . "," .
					"\"" . $row['description'] . "\"," .
					"\"" . $lastUpdated . "\"," .
					"\"" . (empty($row['validation_date']) ? "never" : date("m/d/Y",strtotime($row['validation_date']))) . "\"," .
					"\"Window Title\"," .
					"\"" . $pageTitle . "\"\r";
				echo ",,,,\"Meta Description\",\"" . $row['meta_description'] . "\"\r";
				echo ",,,,\"Meta Keywords\",\"" . $row['meta_keywords'] . "\"\r";
				$resultSet1 = executeQuery("select * from page_meta_tags where page_id = ?",$row['page_id']);
				while ($row1 = getNextRow($resultSet1)) {
					echo ",,,,\"" . $row1['meta_value'] . "\",\"" . $row1['content'] . "\"\r";
				}
			}
			exit;
		}
	}

	function onLoadJavascript() {
?>
$(document).on("tap click","#create_csv",function() {
	document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_action=create_csv";
});
<?php
	}

	function mainContent() {
?>
<div id="report_parameters">
<p class="align-center"><button id="create_csv">Create CSV File</button></p>
</div>
<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
