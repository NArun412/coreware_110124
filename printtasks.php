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

$GLOBALS['gPageCode'] = "PRINTTASKS";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function mainContent() {
?>
<h3>Task List for <?= getUserDisplayName($GLOBALS['gUserId']) ?></h3>
<?php
		$resultSet = executeQuery("select * from tasks where date_completed is null and " .
			"((assigned_user_id is not null and assigned_user_id = ?) or " .
			"(creator_user_id = ? and assigned_user_id is null and user_group_id is null) or " .
			"(assigned_user_id is null and user_group_id in (select user_group_id from user_group_members where user_id = ?))) and " .
			"(start_time is null or start_time <= now()) and (end_time is null or end_time >= now()) and repeat_rules is null " .
			"order by start_time,priority",$GLOBALS['gUserId'],$GLOBALS['gUserId'],$GLOBALS['gUserId']);
		while ($row = getNextRow($resultSet)) {
?>
<table class="task-table">
<tr>
	<td><?= (empty($row['priority']) ? (empty($row['start_time']) ? "" : date("m/d/Y",strtotime($row['start_time']))) : $row['priority']) ?></td>
	<td><?= htmlText($row['description']) ?></td>
</tr>
<?php if (!empty($row['detailed_description'])) { ?>
<tr>
	<td class="no-border">&nbsp;</td>
	<td><?= makeHtml(htmlText($row['detailed_description'])) ?></td>
</tr>
<?php } ?>
<?php if (!empty($row['prerequisites'])) { ?>
<tr>
	<td class="no-border">&nbsp;</td>
	<td><?= makeHtml(htmlText($row['prerequisites'])) ?></td>
</tr>
<?php } ?>
<?php if (!empty($row['date_due'])) { ?>
<tr>
	<td class="no-border">&nbsp;</td>
	<td>Due on <?= date("m/d/Y",strtotime($row['date_due'])) ?></td>
</tr>
<?php } ?>
<?php
			$subtaskSet = executeQuery("select * from tasks where date_completed is null and parent_task_id = ?",$row['task_id']);
			while ($subtaskRow = getNextRow($subtaskSet)) {
?>
<tr>
	<td class="no-border">&nbsp;</td>
	<td><?= $subtaskRow['description'] . (empty($subtaskRow['detailed_description']) ? "" : ": " . $subtaskRow['detailed_description']) ?></td>
</tr>
</table>
<?php
			}
		}
	}

	function internalCSS() {
?>
body { width: 650px; margin: 10px; }
.no-border { border: none; }
.task-table { width: 650px; margin-bottom: 10px; page-break-inside: avoid; }
td { border: 1px solid rgb(180,180,180); font-size: 11px; }
td:first-child { width: 70px; }
p { padding: 0; margin: 0; font-size: 11px; }
<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
