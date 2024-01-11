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
%module:event_countdown:event_id[:element_id=element_id][:format=%D days %H:%M:%S]%
*/

class EventCountdownPageModule extends PageModule {
	function createContent() {
		$eventId = array_shift($this->iParameters);
		$elementId = $this->iParameters['element_id'];
		if (empty($elementId)) {
			$elementId = "event_countdown";
		}
		$startDate = getFieldFromId("start_date","events","event_id",$eventId);
		if (empty($startDate)) {
			echo "<div id='" . $elementId . "'>Event does not exist</div>";
			return;
		}
		$hour = false;
		$hourSet = executeQuery("select * from event_facilities where date_needed = ? and event_id = ? order by hour",$startDate,$eventId);
		if ($hourRow = getNextRow($hourSet)) {
			if ($hour === false) {
				$hour = $hourRow['hour'];
			}
		}
		$format = $this->iParameters['format'];
		if (empty($format)) {
			$format = "%-D day%!D, %-H hour%!H, %-M minute%!M, %-S second%!S";
		}
		$startDate = date("Y/m/d",strtotime($startDate));
		if (!empty($hour)) {
			$hours = floor($hour);
			$minutes = ($hour - $hours) * 60;
			$startDate .= " " . str_pad($hours,2,"0",STR_PAD_LEFT) . ":" . str_pad($minutes,2,"0",STR_PAD_LEFT) . ":00";
		}
		?>
		<div class='event-countdown' id="<?= $elementId ?>"></div>
		<script>
			$(function() {
				$('#<?= $elementId ?>').countdown('<?= $startDate ?>', function(event) {
					$(this).html(event.strftime('<?= $format ?>'));
				});
			});
		</script>
<?php
	}
}
