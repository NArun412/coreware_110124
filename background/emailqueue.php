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
CREATE TABLE email_queue (
	email_queue_id int NOT NULL auto_increment,
	client_id int NOT NULL,
	parameters text NOT NULL,
	time_submitted datetime NOT NULL,
	attempts int NOT NULL default 0,
	deleted int NOT NULL default 0,
	version int NOT NULL default 1,
	PRIMARY KEY(email_queue_id)
) engine=innoDB;
CREATE TABLE email_log (
	email_log_id int NOT NULL auto_increment,
	client_id int NOT NULL,
	parameters text NOT NULL,
	time_submitted datetime NOT NULL,
	email_queue_identifier int,
	log_entry mediumtext,
	version int NOT NULL default 1,
	PRIMARY KEY(email_log_id)
) engine=innoDB;
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
		$this->iProcessCode = "email_queue";
	}

	function process() {
		if (!$GLOBALS['gPrimaryDatabase']->tableExists("email_queue")) {
			$this->addResult("ERROR: Email Queue not found");
			$this->iErrorsFound = true;
		} else {
			$resultSet = executeQuery("select count(*) from email_queue where deleted = 0");
			if ($row = getNextRow($resultSet)) {
				$this->addResult($row['count(*)'] . " emails waiting to be sent.");
				if ($row['count(*)'] > 500) {
					$this->addResult("Sending a maximum of 500 this time.");
					$this->iErrorsFound = true;
				}
			}
			$emailCount = 0;
			$skipCount = 0;
			$resultSet = executeQuery("select * from email_queue where deleted = 0 order by time_submitted");
			while ($row = getNextRow($resultSet)) {
				if ($GLOBALS['gPrimaryDatabase']->tableExists("email_log")) {
					$emailLogId = getFieldFromId("email_log_id", "email_log", "email_queue_identifier", $row['email_queue_id']);
					if (!empty($emailLogId)) {
						executeQuery("update email_queue set deleted = 1 where email_queue_id = ?", $row['email_queue_id']);
						continue;
					}
				}
				$GLOBALS['gSystemPreferences'] = array();
				changeClient($row['client_id']);
				$parameters = json_decode($row['parameters'], true);
				if (!is_array($parameters)) {
					$parameters = array();
				}
				if (!empty($parameters['send_after'])) {
					$serverTimeZone = date_default_timezone_get();
					$clientTimeZone = getPreference("TIMEZONE");
					if ($serverTimeZone == $clientTimeZone || empty($clientTimeZone)) {
						$compareTime = date("Y-m-d H:i:s");
					} else {
						$dateTimeZoneServer = new DateTimeZone($serverTimeZone);
						$dateTimeZoneClient = new DateTimeZone($clientTimeZone);
						$serverOffset = $dateTimeZoneServer->getOffset(new DateTime());
						$clientOffset = $dateTimeZoneClient->getOffset(new DateTime());
						$timeDifference = $serverOffset - $clientOffset;
						$compareTime = date("Y-m-d H:i:s", strtotime("+ " . $timeDifference . " hours"));
					}
					if ($parameters['send_after'] > $compareTime) {
						$this->addResult("Skipped: Send time: " . $parameters['send_after'] . ", current time: " . $compareTime);
						$skipCount++;
						continue;
					}
				}
				$parameters['send_immediately'] = true;
				$parameters['time_submitted'] = $row['time_submitted'];
				$parameters['email_queue_id'] = $row['email_queue_id'];
				$result = sendEmail($parameters);
				if ($result === true || $result == "No Email Address included" || $result == "No Email Addresses" || $result == "Nothing in email body" || strlen($row['parameters']) > 100000) {
					executeQuery("update email_queue set deleted = 1 where email_queue_id = ?", $row['email_queue_id']);
					$emailCount++;
				} else {
					$emailAddresses = array();
					if (!empty($parameters['email_address'])) {
						if (is_array($parameters['email_address'])) {
							$emailAddresses = array_merge($emailAddresses, $parameters['email_address']);
						} else {
							$emailAddresses[] = $parameters['email_address'];
						}
					}
					if (!empty($parameters['email_addresses'])) {
						if (is_array($parameters['email_addresses'])) {
							$emailAddresses = array_merge($emailAddresses, $parameters['email_addresses']);
						} else {
							$emailAddresses[] = $parameters['email_addresses'];
						}
					}
					$this->addResult("Email from client " . getFieldFromId("client_code", "clients", "client_id", $GLOBALS['gClientId']) . " to " .
						implode(",", $emailAddresses) . " failed: " . $result);
					if ($row['attempts'] > 2) {
						$this->iErrorsFound = true;
					}
					if ($row['attempts'] > 4) {
						if (!empty($parameters['donation_id'])) {
							executeQuery("update donations set receipt_sent = null where donation_id = ?", $parameters['donation_id']);
						}
						executeQuery("update email_queue set deleted = 1 where email_queue_id = ?", $row['email_queue_id']);
					} else {
						executeQuery("update email_queue set attempts = attempts + 1 where email_queue_id = ?", $row['email_queue_id']);
					}
				}
				if ($emailCount > 500 || ($emailCount + $skipCount) > 1000) {
					break;
				}
				$emailPause = getPreference("EMAIL_QUEUE_PAUSE");
				if (!empty($emailPause) && is_numeric($emailPause)) {
				    sleep($emailPause);
                }
				usleep(100000);
			}
			executeQuery("delete from email_queue where deleted = 1");
			$this->addResult($emailCount . " emails sent");
			$this->addResult($skipCount . " emails skipped");
		}

		# Check for events that need texts sent

		$currentHour = date("H") + date("i") / 60;
		$resultSet = executeQuery("select *,(select min(hour) from event_facilities where event_id = events.event_id and date_needed = events.start_date) as start_hour," .
			"(select facility_id from event_facilities where event_id = events.event_id limit 1) facility_id from events where " .
			"start_date = current_date and contact_id is not null and event_type_id in (select event_type_id from event_type_tag_links where " .
			"event_type_tag_id in (select event_type_tag_id from event_type_tags where event_type_tag_code = 'SEND_READY_TEXT')) order by client_id");
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			if ($currentHour > $row['start_hour']) {
				continue;
			}
			if ($currentHour < ($row['start_hour'] - (1/8))) {
				continue;
			}
			$hashCode = md5($row['event_id'] . ":" . $row['contact_id'] . ":" . $row['start_date']);
			$addHashId = getFieldFromId("add_hash_id","add_hashes","add_hash",$hashCode);
			if (!empty($addHashId)) {
				continue;
			}

			executeQuery("insert into add_hashes (add_hash,date_used) values (?,current_date)",$hashCode);
			$textContent = getFieldFromId("content","fragments","fragment_code","RESERVATION_READY_TEXT","client_id = ?",$row['client_id']);
			if (empty($textContent)) {
				$textContent = "Your reservation starts at %start_time% in %facility%.";
			}
			$substitutions = array();
			$substitutions['start_time'] = Events::getDisplayTime($row['start_hour']);
			$substitutions['start_date'] = date("m/d/Y",strtotime($row['start_date']));
			$substitutions['description'] = $row['description'];
			$substitutions['facility'] = getFieldFromId("description","facilities","facility_id",$row['facility_id']);
			$substitutions['display_name'] = getDisplayName($row['contact_id']);
			sendEmail(array("contact_id"=>$row['contact_id'],"body"=>$textContent,"subject"=>"Reservation","text_only"=>true,"send_immediately"=>true,"substitutions"=>$substitutions));
		}
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
