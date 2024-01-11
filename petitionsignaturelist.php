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

$GLOBALS['gPageCode'] = "PETITIONSIGNATURELIST";
require_once "shared/startup.inc";

$petitionTypeCode = $_GET['code'];
if (empty($petitionTypeCode)) {
	$petitionTypeCode = $_POST['petition_type_code'];
}
$petitionTypeId = getFieldFromId("petition_type_id","petition_types","petition_type_code",$petitionTypeCode,
	"client_id = " . $GLOBALS['gClientId'] . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and inactive = 0") .
	($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
if (empty($petitionTypeId)) {
	header("Location: /");
	exit;
}

class ThisPage extends Page {

	function mainContent() {
		$petitionTypeCode = $_GET['code'];
		$petitionTypeId = getFieldFromId("petition_type_id","petition_types","petition_type_code",$petitionTypeCode,
			"client_id = " . $GLOBALS['gClientId'] . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and inactive = 0") .
			($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
		$resultSet = executeQuery("select * from petition_types where petition_type_id = ? and client_id = ?",$petitionTypeId,$GLOBALS['gClientId']);
		$petitionTypeRow = getNextRow($resultSet);
		if (!$petitionTypeRow) {
			echo "<h1>Petition not found</h1>";
			return true;
		}
		echo $this->iPageData['content'];
		$totalCount = 0;
		$resultSet = executeQuery("select count(*) from petition_signatures where petition_type_id = ?",$petitionTypeId);
		if ($row = getNextRow($resultSet)) {
			$totalCount = $row['count(*)'];
		}
		$stateCounts = array();
		if ($petitionTypeRow['create_contact']) {
			$resultSet = executeQuery("select state,count(*) from contacts join petition_signatures using (contact_id) where state is not null and petition_type_id = ? group by state",$petitionTypeId);
			while ($row = getNextRow($resultSet)) {
				$stateCounts[strtoupper($row['state'])] = $row['count(*)'];
			}
		}
		$stateArray = getStateArray(true);
		$displayCounts = array();
		$totalStateCount = 0;
		foreach ($stateArray as $stateAbbreviation => $stateName) {
			$stateCount = 0;
			if (array_key_exists($stateAbbreviation,$stateCounts)) {
				$stateCount += $stateCounts[$stateAbbreviation];
			}
			if (array_key_exists(strtoupper($stateName),$stateCounts)) {
				$stateCount += $stateCounts[strtoupper($stateName)];
			}
			if ($stateCount > 0) {
				$displayCounts[$stateName] = $stateCount;
				$totalStateCount += $stateCount;
			}
		}
		ksort($displayCounts);
?>
<div id="chart_wrapper">
<?php
		$showRecent = $this->getPageTextChunk("show_recent");
		if (!empty($showRecent)) {
?>
<div id="recent_signatories_wrapper">
<h2 id="recent_signatories">Recent Signatories</h2>
<table class="grid-table" id="recent_signatories_table">
<tr>
	<th>ID</th>
	<th>Date</th>
	<th>Name</th>
<?php if ($petitionTypeRow['create_contact']) { ?>
	<th>City</th>
<?php } ?>
</tr>
<?php
			$resultSet = executeQuery("select petition_signature_id,petition_signatures.contact_id,full_name,first_name,last_name," .
				"petition_signatures.date_created,city,state from petition_signatures left outer join contacts using (contact_id) where petition_type_id = ? order by petition_signature_id desc limit 50",$petitionTypeId);
			while ($row = getNextRow($resultSet)) {
				if (empty($row['contact_id'])) {
					$nameParts = explode(" ",$row['full_name']);
					$nameParts[(count($nameParts) - 1)] = substr($nameParts[(count($nameParts) - 1)],0,1);
					$displayName = implode(" ",$nameParts);
				} else {
					$displayName = $row['first_name'] . " " . substr($row['last_name'],0,1);
				}
?>
<tr>
	<td><?= $row['petition_signature_id'] ?></td>
	<td><?= date("m/d/Y",strtotime($row['date_created'])) ?></td>
	<td><?= htmlText($displayName) ?></td>
<?php if ($petitionTypeRow['create_contact']) { ?>
	<td><?= $row['city'] . (empty($row['state']) ? "" : ", " . $row['state']) ?></td>
<?php } ?>
</tr>
<?php
			}
?>
</table>
</div>
<?php } ?>
<div id="petition_counts_wrapper">
<h2 id="petition_counts">Petition Counts</h2>
<table id="petition_counts_table" class="grid-table header-sortable">
<tr class="header-row">
	<th>State</th>
	<th>Count</th>
</tr>
<?php
		foreach ($displayCounts as $state => $count) {
?>
<tr>
	<td><?= $state ?></td>
	<td class="align-right"><?= $count ?></td>
</tr>
<?php
		}
		if ($totalCount > $totalStateCount) {
?>
<tr>
	<td>Other</td>
	<td class="align-right"><?= ($totalCount - $totalStateCount) ?></td>
</tr>
<?php
		}
?>
<tr class="footer-row">
	<td class="highlighted-text">Total</td>
	<td class="align-right"><?= $totalCount ?></td>
</tr>
</table>
</div>
</div>
<?php
		echo $this->getPageData("after_form_content");
		return true;
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
