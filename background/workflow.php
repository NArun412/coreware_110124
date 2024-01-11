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
		$this->iProcessCode = "work_flow";
	}

	function process() {

# look for repeating work flow definitions and create a new instance is necessary

		$resultSet = executeQuery("select * from work_flow_definitions where repeat_rules is not null and inactive = 0 and " .
			"work_flow_definition_id not in (select work_flow_definition_id from work_flow_instances where start_date = now())");
		while ($row = getNextRow($resultSet)) {
			if (isInSchedule(date("Y-m-d"), $row['repeat_rules'])) {
				$repeatRules = parseNameValues($row['repeat_rules']);
				$dateDue = (empty($repeatRules['due_after']) ? "" : date('Y-m-d', strtotime("+" . $repeatRules['due_after'] . " days")));
				$insertSet = executeQuery("insert into work_flow_instances (work_flow_definition_id,creator_user_id," .
					"responsible_user_id,start_date,date_due) values (?,?,?,?,?,?,?,?)", $row['work_flow_definition_id'],
					$row['creator_user_id'], $row['responsible_user_id'], date("Y-m-d"), $dateDue);
				if (!empty($resultSet['sql_error'])) {
					$this->addResult("ERROR: " . $resultSet['sql_error']);
					continue;
				}
				$workFlowInstanceId = $resultSet['insert_id'];
				$detailSet = executeQuery("select * from work_flow_details where work_flow_definition_id = ?", $row['work_flow_definition_id']);
				while ($detailRow = getNextRow($detailSet)) {
					$insertSet = executeQuery("insert into work_flow_instance_details (work_flow_detail_code,work_flow_instance_id," .
						"task_type_id,task_description,email_id,email_address,sequence_number,work_flow_status_id,start_rules,days_required,user_id,user_group_id) values " .
						"(?,?,?,?,?,?,?,?,?,?,?,?)", $detailRow['work_flow_detail_code'], $workFlowInstanceId, $detailRow['task_type_id'],
						(empty($detailRow['task_description']) ? $detailRow['description'] : $detailRow['task_description']), $detailRow['email_id'],
						$detailRow['email_address'], $detailRow['sequence_number'], $detailRow['work_flow_status_id'], $detailRow['start_rules'], $detailRow['days_required'], $detailRow['user_id'], $detailRow['user_group_id']);
					if (!empty($insertSet['sql_error'])) {
						$this->addResult("ERROR: " . $resultSet['sql_error']);
						continue 2;
					}
				}
			}
		}

		# Check to see if any tasks should be created or emails sent. This can happen if a task/email is
		# scheduled to happen some amount of time after an instance is created or before the date due.

		$workFlowInstanceSet = executeQuery("select * from work_flow_instances where inactive = 0");
		while ($workFlowInstanceRow = getNextRow($workFlowInstanceSet)) {
			$GLOBALS['gClientId'] = getFieldFromId("client_id", "work_flow_definitions", "work_flow_definition_id", $workFlowInstanceRow['work_flow_definition_id'], "client_id is not null");
			$webUrl = getDomainName();

			$GLOBALS['gSystemPreferences'] = array();
			$resultSet = executeQuery("select * from work_flow_instance_details where work_flow_instance_id = ? and task_id is null and email_sent is null " .
				"order by sequence_number", $workFlowInstanceRow['work_flow_instance_id']);
			while ($row = getNextRow($resultSet)) {
				$startRules = parseNameValues($row['start_rules']);
				if (!empty($startRules['completed'])) {
					continue;
				}
				$onAfterDate = "";
				if (array_key_exists("after", $startRules) && !empty($workFlowInstanceRow['start_date'])) {
					$onAfterDate = date('Y-m-d', strtotime(date("Y-m-d", strtotime($workFlowInstanceRow['start_date'])) . " + " . $startRules['after'] . " " . $startRules['units']));
				} else if (array_key_exists("before", $startRules) && !empty($workFlowInstanceRow['date_due'])) {
					$onAfterDate = date('Y-m-d', strtotime(date("Y-m-d", strtotime($workFlowInstanceRow['date_due'])) . " - " . $startRules['before'] . " " . $startRules['units']));
				}
				if (empty($onAfterDate) || $onAfterDate > date("Y-m-d")) {
					continue;
				}
				if (!empty($row['task_type_id'])) {
					$startDate = $onAfterDate;
					$dateDue = (empty($row['days_required']) ? "" : date('Y-m-d', strtotime("+" . $row['days_required'] . " days", strtotime($startDate))));
					$insertSet = executeQuery("insert into tasks (description,project_id,creator_user_id,assigned_user_id,user_group_id,task_type_id," .
						"start_time,date_due) values (?,?,?,?,?,?,?,?)", $row['task_description'], $workFlowInstanceRow['project_id'], $workFlowInstanceRow['creator_user_id'],
						$row['user_id'], $row['user_group_id'], $row['task_type_id'], makeDatetimeParameter($startDate), $dateDue);
					$newTaskId = $insertSet['insert_id'];
					$updateSet = executeQuery("update work_flow_instance_details set task_id = ? where work_flow_instance_detail_id = ? and task_id is null",
						$newTaskId, $row['work_flow_instance_detail_id']);
					if ($updateSet['affected_rows'] == 0) {
						executeQuery("delete from tasks where task_id = ?", $newTaskId);
					} else if (!empty($row['work_flow_status_id'])) {
						executeQuery("update work_flow_instances set work_flow_status_id = ? where work_flow_instance_id = ?", $row['work_flow_status_id'], $row['work_flow_instance_id']);
						if (!empty($row['user_id'])) {
							$emailAddress = Contact::getUserContactField($row['user_id'], "email_address");
							if (!empty($emailAddress)) {
								$body = "A task has been assigned to you. You can view the task <a href='" . $webUrl . "/taskmanager.php?task_id=" . $newTaskId . "'>here</a>.";
								sendEmail(array("body" => $body, "subject" => "Task Assigned", "email_addresses" => $emailAddress, "send_immediately" => true));
							}
						}
					}
				} else if (!empty($row['email_id'])) {
					$emailAddress = (empty($row['email_address']) ? getFieldFromId("email_address", "contacts", "contact_id", $workFlowInstanceRow['contact_id']) : $row['email_address']);
					if (!empty($emailAddress)) {
						$updateSet = executeQuery("update work_flow_instance_details set email_sent = now() where work_flow_instance_detail_id = ? and email_sent is null",
							$row['work_flow_instance_detail_id']);
						if ($updateSet['affected_rows'] > 0) {
							sendEmail(array("email_id" => $row['email_id'], "email_addresses" => $emailAddress, "send_immediately" => true));
							if (!empty($row['work_flow_status_id'])) {
								executeQuery("update work_flow_instances set work_flow_status_id = ? where work_flow_instance_id = ?", $row['work_flow_status_id'], $row['work_flow_instance_id']);
							}
						}
					}
				}
			}
		}

		# Send emails to assigned users for tasks due today

		$resultSet = executeQuery("select * from tasks where date_completed is null and date_due is not null and date_due <= current_date and assigned_user_id is not null");
		while ($row = getNextRow($resultSet)) {
			$GLOBALS['gClientId'] = $row['client_id'];
			$emailAddress = Contact::getUserContactField($row['assigned_user_id'], "email_address");
			if (!empty($emailAddress)) {
				$emailId = getFieldFromId("email_id", "emails", "email_code", "TOUCHPOINT_TASK_DUE",  "inactive = 0");
				$body = "A task titled '" . $row['description'] . "'" . (empty($row['contact_id']) ? "" : " for contact " . $row['contact_id']) . ", assigned to you," .
					($row['date_due'] == date("Y-m-d") ? " is due today." : " was due on " . date("m/d/Y", strtotime($row['date_due'])) . " and is overdue. ");
				if (empty($row['contact_id'])) {
					$body .= "You can get to the task by going to Tasks->Tasks.";
				} else {
					$body .= "You can get to the task by going to Contacts->Contacts, finding contact ID " . $row['contact_id'] . " and going to the Touchpoints tab.";
				}
				$substitutions = array("description" => $row['description'], "contact_id" => $row['contact_id'], "display_name" => (empty($row['contact_id']) ? "" : getDisplayName($row['contact_id'])), "date_due" => date("m/d/Y", strtotime($row['date_due'])));
				sendEmail(array("body" => $body, "subject" => "Task " . ($row['date_due'] == date("Y-m-d") ? "Due Today" : "Overdue"), "email_id" => $emailId, "email_addresses" => $emailAddress, "send_immediately" => true));
			}
		}

		# Send emails to responsible users for overdue tasks

		$resultSet = executeQuery("select * from tasks where date_completed is null and date_due is not null and date_due <= current_date and (task_type_id in " .
			"(select task_type_id from task_types where responsible_user_id is not null) or " .
			"task_id in (select task_id from work_flow_instance_details where inactive = 0 and work_flow_instance_id in (select work_flow_instance_id from " .
			"work_flow_instances where responsible_user_id is not null and inactive = 0)) or project_id in (select project_id from projects where leader_user_id is not null))");
		while ($row = getNextRow($resultSet)) {
			$GLOBALS['gClientId'] = $row['client_id'];
			$emailAddresses = array();
			$taskTypeUserId = getFieldFromId("responsible_user_id", "task_types", "task_type_id", $row['task_type_id']);
			if (!empty($taskTypeUserId)) {
				$emailAddress = Contact::getUserContactField($taskTypeUserId, "email_address");
				if (!empty($emailAddress)) {
					$emailAddresses[] = $emailAddress;
				}
			}
			$workFlowUserId = getFieldFromId("responsible_user_id", "work_flow_instances", "work_flow_instance_id", getFieldFromId("work_flow_instance_id", "work_flow_instance_details", "task_id", $row['task_id']));
			if (!empty($workFlowUserId)) {
				$emailAddress = Contact::getUserContactField($workFlowUserId, "email_address");
				if (!empty($emailAddress) && !in_array($emailAddress, $emailAddresses)) {
					$emailAddresses[] = $emailAddress;
				}
			}
			$projectLeaderUserId = getFieldFromId("leader_user_id", "projects", "project_id", $row['task_id']);
			if (!empty($projectLeaderUserId)) {
				$emailAddress = Contact::getUserContactField($projectLeaderUserId, "email_address");
				if (!empty($emailAddress) && !in_array($emailAddress, $emailAddresses)) {
					$emailAddresses[] = $emailAddress;
				}
			}
			if (count($emailAddresses) > 0) {
				$assignedTo = (empty($row['assigned_user_id']) ? "" : getUserDisplayName($row['assigned_user_id']));
				if (!empty($row['user_group_id'])) {
					if (!empty($assignedTo)) {
						$assignedTo .= " or ";
					}
					$assignedTo .= getFieldFromId("description", "user_groups", "user_group_id", $row['user_group_id']);
				}
				$body = "A task titled '" . $row['description'] . "'" . (empty($assignedTo) ? "" : ", assigned to " . $assignedTo) .
					($row['date_due'] == date("Y-m-d") ? " is due today." : " was due on " . date("m/d/Y", strtotime($row['date_due'])) . " and is overdue.");
				sendEmail(array("body" => $body, "subject" => "Overdue task", "email_addresses" => $emailAddresses, "send_immediately" => true));
			}
		}

		# Send emails to project members who request notification
		$emailCount = 0;
		$projectSet = executeQuery("select * from projects where inactive = 0");
		while ($projectRow = getNextRow($projectSet)) {
			ob_start();
			$this->getMessageBoards(array("project_id" => $projectRow['project_id']));
			$messageBoard = ob_get_clean();
			if (!empty($messageBoard)) {
				$body = "Daily email update for project '" . $projectRow['description'] . "'. The project page is at <a href='" . $webUrl . "project.php?id=" .
					$projectRow['project_id'] . "'>" . $webUrl . "project.php?id=" .
					$projectRow['project_id'] . "</a>.: <br/><br/>\n\n" . $messageBoard;
				$emailAddresses = array();
				$resultSet = executeQuery("select distinct email_address from contacts where email_address is not null and contact_id in (select contact_id from users where " .
					"(user_id in (select user_id from project_notifications where project_id = ?) or " .
					"user_id in (select user_id from project_member_users where project_id = ?) or " .
					"user_id in (select user_id from user_group_members where user_group_id in (select user_group_id from project_member_user_groups where project_id = ?)) or " .
					"user_id = (select user_id from projects where project_id = ?) or " .
					"user_id = (select leader_user_id from projects where project_id = ?)) and user_id not in (select user_id from project_notification_exclusions where project_id = ?))",
					$projectRow['project_id'], $projectRow['project_id'], $projectRow['project_id'], $projectRow['project_id'], $projectRow['project_id'], $projectRow['project_id']);
				while ($row = getNextRow($resultSet)) {
					$emailAddresses[] = $emailAddress;
				}
				if (!empty($emailAddresses)) {
					sendEmail(array("body" => $body, "subject" => "Project Update", "email_addresses" => $emailAddresses, "send_immediately" => true));
					$emailCount++;
				}
			}
		}
		$this->addResult($emailCount . " project emails sent");

	}

	function getMessageBoards($parameters) {
		$projectId = $parameters['project_id'];
		$parentLogId = $parameters['parent_log_id'];
		$level = (array_key_exists("level", $parameters) ? $parameters['level'] : 0);
		$resultSet = executeQuery("select * from project_log where date(log_time) > current_date() - interval 2 day and project_id = ? and parent_log_id <=> ? order by log_id", $projectId, $parentLogId);
		while ($row = getNextRow($resultSet)) {
			?>
            <p style="margin-left: <?= ($level * 20) ?>px;"><span style='font-weight: bold;'><?= date("m/d/Y g:i a", strtotime($row['log_time'])) ?><?= (empty($row['user_id']) ? "" : " by " . getUserDisplayName($row['user_id'])) ?></span>: <?= htmlText($row['content']) ?></p>
			<?php
			$this->getMessageBoards(array("project_id" => $projectId, "parent_log_id" => $row['log_id'], "level" => ($level + 1)));
		}
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
