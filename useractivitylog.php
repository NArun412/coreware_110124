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

$GLOBALS['gPageCode'] = "USERACTIVITYLOG";
require_once "shared/startup.inc";

$userId = getFieldFromId("user_id","users","user_id",$_GET['user_id'],($GLOBALS['gUserRow']['superuser_flag'] ? "" : "superuser_flag = 0"));
if (empty($userId)) {
	$userId = getFieldFromId("user_id","users","contact_id",$_GET['contact_id'],($GLOBALS['gUserRow']['superuser_flag'] ? "" : "superuser_flag = 0"));
}
if (empty($userId)) {
	echo jsonEncode(array());
	exit;
}
ob_start();
$resultSet = executeQuery("select * from user_activity_log where user_id = ? order by log_time desc",$userId);
if ($resultSet['row_count'] > 0) {
	$count = 0;
?>
<table class="grid-table">
	<tr>
		<th>Activity Time</th>
		<th>Activity Record</th>
	</tr>
<?php
	while ($row = getNextRow($resultSet)) {
		$count++;
		if ($count > 50 && $row['log_time'] < date("Y-m-d",strtotime("-2 months"))) {
			break;
		}
?>
<tr>
	<td><?= date("m/d/Y g:ia",strtotime($row['log_time'])) ?></td>
	<td><?= htmlText($row['log_entry']) ?></td>
</tr>
<?php
	}
?>
</table>
<?php
} else {
?>
<p>No Activity Found</p>
<?php
}
$returnArray = array("activity_log"=>ob_get_clean());
echo jsonEncode($returnArray);
exit;