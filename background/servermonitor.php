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

$GLOBALS['gPageCode'] = "BACKGROUNDPROCESS";
$runEnvironment = php_sapi_name();
if ($runEnvironment == "cli") {
	require_once "shared/startup.inc";
} else {
	require_once "../shared/startup.inc";
}

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "server_monitor";
	}

	function process() {
		executeQuery("delete from server_monitor_log where log_time < (now() - INTERVAL 1 DAY)");
		$resultSet = executeQuery("select * from server_monitors where inactive = 0 order by sort_order");
		while ($row = getNextRow($resultSet)) {
			$this->addResult("Running Monitor '" . $row['description'] . "'");
			$logSet = executeQuery("select * from server_monitor_log where server_monitor_id = ? order by log_time desc limit 1",$row['server_monitor_id']);
			if (!$logRow = getNextRow($logSet)) {
				$logRow = array();
			}
			$lapsedTime = (empty($logRow['log_time']) ? $row['units_between'] * 60 : date("U") - date("U",strtotime($logRow['log_time'])));
			if ($lapsedTime >= round($row['units_between'] * 60 *.9)) {
				$firstTest = true;
				while (true) {
					$curlHandle = curl_init($row['link_url']);
					curl_setopt($curlHandle, CURLOPT_HEADER, 0);
					curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
					curl_setopt($curlHandle, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
					curl_setopt($curlHandle, CURLOPT_SSL_VERIFYPEER, 0);
					$siteContent = curl_exec($curlHandle);
					curl_close($curlHandle);
					$siteDown = ((strpos($siteContent,$row['search_text']) === false && empty($row['text_found'])) ||
						(strpos($siteContent,$row['search_text']) !== false && !empty($row['text_found'])));
					if ($siteDown && $firstTest) {
						sleep(30);
						$firstTest = false;
					} else {
						break;
					}
				}

				if ($siteDown) {
					$this->addResult("Site down");
				} else {
					$this->addResult("Site up");
				}
				$notificationsSent = $siteDown || $logRow['server_down'];
				if ($notificationsSent) {
					executeQuery("insert into server_monitor_log (server_monitor_id,content,server_down,notifications_sent) values (?,?,?,?)",
						$row['server_monitor_id'],$siteContent,($siteDown ? 1 : 0),($notificationsSent ? 1 : 0));
					$emailAddresses = array();
					$emailSet = executeQuery("select * from server_monitor_notifications where server_monitor_id = ?",$row['server_monitor_id']);
					while ($emailRow = getNextRow($emailSet)) {
						if (!in_array($emailRow['email_address'],$emailAddresses)) {
							$emailAddresses[] = $emailRow['email_address'];
						}
					}
					if (!empty($emailAddresses)) {
						sendEmail(array("subject"=>"Site " . ($siteDown ? "Down" : "Back Up"),"body"=>"Site '" . $row['link_url'] . "' is " . ($siteDown ? "down.<br><br>" . $siteContent : "back up") . ".",
                            "send_immediately"=>true,"no_notifications"=>true,"email_addresses"=>$emailAddresses));
					}
				}
			}
		}
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
