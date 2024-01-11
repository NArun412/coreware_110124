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
		$this->iProcessCode = "help_desk";
	}

	function process() {

# Notifications for help desk entries that haven't had a timely response

		$resultSet = executeQuery("select * from help_desk_entries where time_closed is null order by client_id");
		$this->addResult($resultSet['row_count'] . " help desk tickets found");
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			$helpDeskTypeCategoryRow = getRowFromId("help_desk_type_categories", "help_desk_type_id", $row['help_desk_type_id'], "help_desk_category_id = ?", $row['help_desk_category_id']);

			$superuserHelpDeskItem = getFieldFromId("superuser_flag", "users", "user_id", $row['user_id']);
			$emailAddresses = array();
			$emailAddress = Contact::getUserContactField($row['user_id'], "email_address");
			if (!empty($emailAddress)) {
				$emailAddresses[] = $emailAddress;
			}
			$notifyUserGroup = getFieldFromId("notify_user_group", "help_desk_types", "help_desk_type_id", $row['help_desk_type_id']);
			if (!empty($notifyUserGroup) && !empty($row['user_group_id'])) {
				$emailSet = executeQuery("select email_address from contacts where email_address is not null and contact_id in (select contact_id from users where user_id in (select user_id from user_group_members where user_group_id = ?))", $row['user_group_id']);
				while ($emailRow = getNextRow($emailSet)) {
					$emailAddresses[] = $emailRow['email_address'];
				}
			}
			$emailSet = executeQuery("select * from help_desk_type_notifications where help_desk_type_id = ?", $row['help_desk_type_id']);
			while ($emailRow = getNextRow($emailSet)) {
				$emailAddresses[] = $emailRow['email_address'];
			}
			if (empty($emailAddresses)) {
				continue;
			}

			$lastActivity = false;
			$activitySet = executeQuery("select * from help_desk_public_notes where help_desk_entry_id = ? order by time_submitted desc", $row['help_desk_entry_id']);
			if ($activityRow = getNextRow($activitySet)) {
				$lastActivity = $activityRow['time_submitted'];
			}
			$activitySet = executeQuery("select * from help_desk_private_notes where help_desk_entry_id = ? order by time_submitted desc", $row['help_desk_entry_id']);
			if ($activityRow = getNextRow($activitySet)) {
				if (!$lastActivity || $activityRow['time_submitted'] > $lastActivity) {
					$lastActivity = $activityRow['time_submitted'];
				}
			}
			$submitted = new DateTime($row['time_submitted']);
			if ($lastActivity) {
				$lastActivity = new DateTime($lastActivity);
			}
			$now = new DateTime();
			$helpDeskStatusRow = getRowFromId("help_desk_statuses", "help_desk_status_id", $row['help_desk_status_id']);

			if (!empty($helpDeskStatusRow['close_after_days'])) {
				$thisActivityDate = (!$lastActivity ? $submitted : $lastActivity);
				$timeSince = $thisActivityDate->diff($now);
				$days = $timeSince->format("%a");
				$this->addResult("Help Desk Ticket #" . $row['help_desk_entry_id'] . " set to close after " . $helpDeskStatusRow['close_after_days'] . " but is " . $days . " since last activity");
				if ($days >= $helpDeskStatusRow['close_after_days']) {
					$this->addResult("Help Desk Ticket #" . $row['help_desk_entry_id'] . " closed based on status");
					$helpDesk = new HelpDesk($row['help_desk_entry_id']);
					$helpDesk->markClosed("Closed because of inactivity. If you need further assistance, please submit a new ticket.");
				}
			}
			if (!empty($helpDeskStatusRow['no_notifications'])) {
				continue;
			}
			if (!$lastActivity && !empty($helpDeskTypeCategoryRow['response_within'])) {
				$timeSince = $submitted->diff($now);
				$days = $timeSince->format("%a");
				if ($days > $helpDeskTypeCategoryRow['response_within']) {
					$body = "<p>Help Desk Ticket # " . $row['help_desk_entry_id'] . " has not yet had a response and it has been " . $days . " days since it was submitted. Please respond to the ticket.</p>";
					if (!empty($superuserHelpDeskItem)) {
						$body .= "From System: " . getPreference("SYSTEM_NAME");
					}
					sendEmail(array("email_addresses" => $emailAddresses, "no_notifications" => true, "subject" => "Help Desk Ticket #" . $row['help_desk_entry_id'] . " Needs Attention", "body" => "<p>----------</p>" . $body . "<p>----------</p>",
						"email_credential_code" => "HELP_DESK", "email_credential_id" => getFieldFromId("email_credential_id", "help_desk_types", "help_desk_type_id", $row['help_desk_type_id'])));
					$this->addResult("Help Desk Ticket #" . $row['help_desk_entry_id'] . " requires attention");
					continue;
				}
			}
			if ($lastActivity && !empty($helpDeskTypeCategoryRow['no_activity_notification'])) {
				$timeSince = $lastActivity->diff($now);
				$days = $timeSince->format("%a");
				if ($days > $helpDeskTypeCategoryRow['no_activity_notification']) {
					$body = "<p>Help Desk Ticket # " . $row['help_desk_entry_id'] . " has not had a response in " . $days . " days but needs one in " . $helpDeskTypeCategoryRow['no_activity_notification'] . " days. Please respond to the ticket.</p>";
					sendEmail(array("email_addresses" => $emailAddresses, "no_notifications" => true, "subject" => "Help Desk Ticket #" . $row['help_desk_entry_id'] . " Needs Attention", "body" => "<p>----------</p>" . $body . "<p>----------</p>",
						"email_credential_code" => "HELP_DESK", "email_credential_id" => getFieldFromId("email_credential_id", "help_desk_types", "help_desk_type_id", $row['help_desk_type_id'])));
					$this->addResult("Help Desk Ticket #" . $row['help_desk_entry_id'] . " requires attention");
				}
			}
		}
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
