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

$GLOBALS['gPageCode'] = "TASKMANAGER";
require_once "shared/startup.inc";

class ThisPage extends Page {
	var $iTaskTypeAttributes = array();

	function setup() {
		$this->iPrimaryTableName = "tasks";
		$this->iDatabase = $GLOBALS['gPrimaryDatabase'];
	}

	function getDataSource() {
		if (!$this->iDataSource) {
			$this->iDataSource = new DataSource("tasks");
			$this->iDataSource->getPageControls();
		}
		return $this->iDataSource;
	}

	function addProjectLog($projectId, $logEntry) {
		if (!empty($projectId) && !empty($logEntry)) {
			$insertSet = executeQuery("insert into project_log (project_id,user_id,content) values (?,?,?)",
				$projectId, $GLOBALS['gUserId'], $logEntry);
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "renumber_tasks":
				$resultSet = executeQuery("select task_id from tasks where parent_task_id is null and date_completed is null and " .
					"(assigned_user_id = ? or (assigned_user_id is null and creator_user_id = ?) or (assigned_user_id is null and user_group_id in " .
					"(select user_group_id from user_group_members where user_id = ?))) and (task_type_id not in " .
					"(select task_type_id from task_type_attributes where task_attribute_id = (select task_attribute_id from task_attributes where task_attribute_code = 'APPOINTMENT'))) and " .
					"(start_time is null or start_time <= now()) and (end_time is null or end_time >= now()) and " .
					"repeat_rules is null order by priority,task_id", $GLOBALS['gUserId'], $GLOBALS['gUserId'], $GLOBALS['gUserId']);
				$priority = 1;
				while ($row = getNextRow($resultSet)) {
					executeQuery("update tasks set priority = ? where task_id = ?", $priority++, $row['task_id']);
				}
				ajaxResponse($returnArray);

# When the user chooses a project in the task dialog, get a list of the milestones that are part of the project for the milestone dropdown.

			case "get_milestones":
				$projectId = $_GET['project_id'];
				$returnArray['project_milestones'] = array();
				$resultSet = executeQuery("select * from project_milestones where project_id = ? order by target_date", $projectId);
				while ($row = getNextRow($resultSet)) {
					$returnArray['project_milestones'][$row['project_milestone_id']] = $row['description'];
				}
				ajaxResponse($returnArray);

# Remove a custom tab that the user previously added

			case "delete_tab":
				$tabKey = $_GET['tab_key'];
				$valuesArray = Page::getPagePreferences();
				unset($valuesArray['filter_tab-' . $tabKey]);
				Page::setPagePreferences($valuesArray);
				ajaxResponse($returnArray);

# Change the type of a task. This can result in access to some data being removed

			case "change_task_type":
				$taskId = $_GET['task_id'];
				$resultSet = executeQuery("select task_type_id from task_types where client_id = ? and inactive = 0 and (task_type_id not in " .
					"(select task_type_id from task_type_attributes where task_attribute_id = (select task_attribute_id from task_attributes " .
					"where task_attribute_code = 'APPOINTMENT'))) and task_type_id = ?", $GLOBALS['gClientId'], $_GET['task_type_id']);
				$taskTypeId = "";
				if ($row = getNextRow($resultSet)) {
					$taskTypeId = $row['task_type_id'];
				}
				if (empty($taskTypeId)) {
					$returnArray['error_message'] = getSystemMessage("invalid_task_type");
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("update tasks set task_type_id = ? where parent_task_id is null and " .
					"task_id = ? and (assigned_user_id = ? or ((assigned_user_id is null or date_completed is not null) and user_group_id in " .
					"(select user_group_id from user_group_members where user_id = ?)) or " .
					"(assigned_user_id is null and user_group_id is null and creator_user_id = ?) or " .
					"(task_type_id in (select task_type_id from task_types where responsible_user_id = ?))) and (task_type_id not in " .
					"(select task_type_id from task_type_attributes where task_attribute_id = (select task_attribute_id from " .
					"task_attributes where task_attribute_code = 'APPOINTMENT')))",
					$taskTypeId, $taskId, $GLOBALS['gUserId'], $GLOBALS['gUserId'], $GLOBALS['gUserId'], $GLOBALS['gUserId']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
				} else {
					$this->addProjectLog(getFieldFromId("project_id", "tasks", "task_id", $taskId), "Changed the task type of task '" . getFieldFromId("description", "tasks", "task_id", $taskId) . "' to '" . getFieldFromId("description", "task_types", "task_type_id", $taskTypeId) . "'.");
				}
				ajaxResponse($returnArray);

# When a user is attempting to change the task type of a task, this will show the user which fields will be lost

			case "task_type_losses":
				$returnArray['losses'] = "";
				$taskId = $_GET['task_id'];
				$taskTypeId = $_GET['task_type_id'];
				$oldTaskTypeId = getFieldFromId("task_type_id", "tasks", "task_id", $taskId);
				$losses = array();
				if (!empty($taskTypeId) && $taskTypeId != $oldTaskTypeId) {
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'ALLOW_FILE')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'ALLOW_FILE'))) {
						$losses[] = "Attached files";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'ALLOW_IMAGES')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'ALLOW_IMAGES'))) {
						$losses[] = "Images";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'DATE_COMPLETED')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'DATE_COMPLETED'))) {
						$losses[] = "Date completed";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'DUE_DATE')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'DUE_DATE'))) {
						$losses[] = "Due Date";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'END_DATE')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'END_DATE'))) {
						$losses[] = "End Date";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'ESTIMATED_HOURS')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'ESTIMATED_HOURS'))) {
						$losses[] = "Estimated Hours";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'HAS_SUBTASKS')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'HAS_SUBTASKS'))) {
						$losses[] = "Subtasks";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'LOG_TIME')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'LOG_TIME'))) {
						$losses[] = "Time Log";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'PERCENT_COMPLETE')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'PERCENT_COMPLETE'))) {
						$losses[] = "Percent Complete";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'PRIORITY')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'PRIORITY'))) {
						$losses[] = "Priority";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'PROJECT')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'PROJECT'))) {
						$losses[] = "Project";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'REPEATABLE')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'REPEATABLE'))) {
						$losses[] = "Repeat Rules";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'START_DATE')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'START_DATE'))) {
						$losses[] = "Start Date";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'TASK_LOG')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'TASK_LOG'))) {
						$losses[] = "Task Log";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'USER_ASSIGNED')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'USER_ASSIGNED'))) {
						$losses[] = "Assigned User";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'USER_GROUP_ASSIGNED')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'USER_GROUP_ASSIGNED'))) {
						$losses[] = "Assigned User Group";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'USES_CATEGORIES')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'USES_CATEGORIES'))) {
						$losses[] = "Task Categories";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'USE_DETAILED_DESCRIPTION')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'USE_DETAILED_DESCRIPTION'))) {
						$losses[] = "Detailed Description";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'USE_PREREQUISITES')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'USE_PREREQUISITES'))) {
						$losses[] = "Prerequisites";
					}
					if (!$this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_attributes" => 'USE_TASK_GROUP')) && $this->taskTypeAttribute(array("task_type_id" => $oldTaskTypeId, "task_attributes" => 'USE_TASK_GROUP'))) {
						$losses[] = "Task Group";
					}
					if (!empty($losses)) {
						$lossText = "<p class='highlighted-text'>The following data will be lost with this change:</p><ul>";
						foreach ($losses as $loss) {
							$lossText .= "<li>" . $loss . "</li>";
						}
						$lossText .= "</ul>";
						$returnArray['losses'] = $lossText;
					}
				}
				ajaxResponse($returnArray);

# Delete a work flow instance. If the work flow instance has not yet created any tasks or sent any emails, then completely
# delete it and its detail record. Otherwise, just make it inactive

			case "delete_work_flow_instance":
				$workFlowInstanceId = $_GET['work_flow_instance_id'];
				if (!empty($workFlowInstanceId)) {
					$this->iDatabase->startTransaction();
					$detailCount = 0;
					$resultSet = executeQuery("select count(*) from work_flow_instance_details where " .
						"(task_id is not null or email_sent is not null) and work_flow_instance_id = ?", $workFlowInstanceId);
					if ($row = getNextRow($resultSet)) {
						$detailCount = $row['count(*)'];
					}
					if ($detailCount == 0) {
						$resultSet = executeQuery("delete from work_flow_instance_details where work_flow_instance_id = ?", $workFlowInstanceId);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						$resultSet = executeQuery("delete from work_flow_instances where work_flow_instance_id = ?", $workFlowInstanceId);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					} else {
						$resultSet = executeQuery("update work_flow_instances set inactive = 1 where work_flow_instance_id = ?", $workFlowInstanceId);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}

						# inactivate future details

						$resultSet = executeQuery("update work_flow_instance_details set inactive = 1 where task_id is null and email_sent is null and " .
							"work_flow_instance_id = ?", $workFlowInstanceId);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}

						# mark as completed tasks that are part of the work flow that are not yet completed

						$resultSet = executeQuery("update tasks set date_completed = now(), completing_user_id = ? where date_completed is null and " .
							"task_id in (select task_id from work_flow_instance_details where work_flow_instance_id = ? and task_id is not null and " .
							"inactive = 0)", $GLOBALS['gUserId'], $workFlowInstanceId);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
					$this->iDatabase->commitTransaction();
				}
				ajaxResponse($returnArray);

# save changes to the work flow instance. If the work flow instance is new, then create all the details records for it.

			case "save_work_flow_instance":
				$workFlowDefinitionId = getFieldFromId("work_flow_definition_id", "work_flow_definitions", "work_flow_definition_id",
					$_POST['work_flow_definition_id']);
				$workFlowInstanceId = $_POST['work_flow_instance_id'];
				$this->iDatabase->startTransaction();
				if (empty($workFlowInstanceId)) {
					$projectDescription = $_POST['wfi_project'];
					if (!empty($projectDescription)) {
						$resultSet = executeQuery("insert into projects (client_id,description,date_created,user_id,leader_user_id) values (?,?,now(),?,?)",
							$GLOBALS['gClientId'], $projectDescription, $GLOBALS['gUserId'], $_POST['wfi_responsible_user_id']);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						$projectId = $resultSet['insert_id'];
					} else {
						$projectId = "";
					}
					$resultSet = executeQuery("insert into work_flow_instances (work_flow_definition_id,creator_user_id," .
						"responsible_user_id,project_id,start_date,date_due,date_completed,completing_user_id,work_flow_status_id,notes) values " .
						"(?,?,?,?,?,?,?,?,?,?)", $workFlowDefinitionId, $GLOBALS['gUserId'], $_POST['wfi_responsible_user_id'], $projectId,
						$this->iDatabase->makeDateParameter($_POST['wfi_start_date']), $this->iDatabase->makeDateParameter($_POST['wfi_date_due']), $this->iDatabase->makeDateParameter($_POST['wfi_date_completed']),
						(empty($_POST['wfi_date_completed']) ? "" : $GLOBALS['gUserId']), $_POST['wfi_work_flow_status_id'], $_POST['wfi_notes']);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$workFlowInstanceId = $resultSet['insert_id'];
					$resultSet = executeQuery("select * from work_flow_details where work_flow_definition_id = ?", $workFlowDefinitionId);
					while ($row = getNextRow($resultSet)) {
						$insertSet = executeQuery("insert into work_flow_instance_details (work_flow_detail_code,work_flow_instance_id," .
							"task_type_id,task_description,email_id,email_address,sequence_number,work_flow_status_id,start_rules," .
							"days_required,user_id,user_group_id,inactive) values " .
							"(?,?,?,?,?,?,?,?,?,?,?,?,?)", $row['work_flow_detail_code'], $workFlowInstanceId, $row['task_type_id'],
							(empty($row['task_description']) ? $row['description'] : $row['task_description']), $row['email_id'],
							$row['email_address'], $row['sequence_number'], $row['work_flow_status_id'], $row['start_rules'], $row['days_required'],
							$row['user_id'], $row['user_group_id'], (empty($_POST['wfi_date_completed']) ? 0 : 1));
						if (!empty($insertSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
					$workFlowInstanceRow = array();
				} else {
					$resultSet = executeQuery("select * from work_flow_instances where work_flow_instance_id = ?", $workFlowInstanceId);
					if (!$workFlowInstanceRow = getNextRow($resultSet)) {
						$returnArray['error_message'] = getSystemMessage("instance_not_found");
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$resultSet = executeQuery("update work_flow_instances set responsible_user_id = ?, date_due = ?, date_completed = ?," .
						"work_flow_status_id = ?, notes = ? where work_flow_instance_id = ?", $_POST['wfi_responsible_user_id'],
						$this->iDatabase->makeDateParameter($_POST['wfi_date_due']), $this->iDatabase->makeDateParameter($_POST['wfi_date_completed']),
						$_POST['wfi_work_flow_status_id'], $_POST['wfi_notes'], $workFlowInstanceId);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}

# If the work flow is newly flagged as completed, complete the tasks associated with it, inactivate future details
# and create another work flow instance, if it is repeating and a new one is necessary

				if (empty($workFlowInstanceRow['date_completed']) && !empty($_POST['wfi_date_completed'])) {
					$resultSet = executeQuery("update work_flow_instances set completing_user_id = ? where work_flow_instance_id = ?",
						$GLOBALS['gUserId'], $workFlowInstanceId);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$resultSet = executeQuery("update tasks set date_completed = ?, completing_user_id = ? where date_completed is null and " .
						"task_id in (select task_id from work_flow_instance_details where work_flow_instance_id = ? and task_id is not null and " .
						"inactive = 0)", $this->iDatabase->makeDateParameter($_POST['wfi_date_completed']), $GLOBALS['gUserId'], $workFlowInstanceId);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$resultSet = executeQuery("update work_flow_instance_details set inactive = 1 where task_id is null and email_sent is null and " .
						"work_flow_instance_id = ?", $workFlowInstanceId);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}

# if the work flow definition is repeating and frequency is after, look for later instances. If none, check and create next instance.

					$resultSet = executeQuery("select * from work_flow_definitions where work_flow_definition_id = ? and " .
						"client_id = ? and repeat_rules is not null", $workFlowDefinitionId, $GLOBALS['gClientId']);
					while ($workFlowDefinitionRow = getNextRow($resultSet)) {
						$repeatRules = parseNameValues($workFlowDefinitionRow['repeat_rules']);
						if ($repeatRules['frequency'] != "AFTER") {
							break;
						}
						$endDate = "";
						if (!empty($repeatRules['until'])) {
							$endDate = date("Y-m-d", strtotime($repeatRules['until']));
						}
						if (!empty($repeatRules['count'])) {
							$count = 0;
							$countSet = executeQuery("select count(*) from work_flow_instances where work_flow_definition_id = ?",
								$workFlowInstanceRow['work_flow_definition_id']);
							if ($countRow = getNextRow($countSet)) {
								$count = $countRow['count(*)'];
							}
							if ($count > $repeatRules['count']) {
								break;
							}
						}
						if (empty($repeatRules['interval'])) {
							$repeatRules['interval'] = "0";
						}
						$startDate = date("Y-m-d", strtotime("+" . $repeatRules['interval'] . " " . $repeatRules['units'],
							strtotime($_POST['wfi_date_completed'])));
						if (!empty($endDate) && $startDate > $endDate) {
							break;
						}
						$checkSet = executeQuery("select * from work_flow_instances where start_date >= ? and work_flow_definition_id = ?",
							$startDate, $workFlowInstanceRow['work_flow_definition_id']);
						if ($checkSet['row_count'] > 0) {
							break;
						}
						$dateDue = (empty($repeatRules['due_after']) ? "" : date('Y-m-d', strtotime("+" . $repeatRules['due_after'] . " days", strtotime($startDate))));
						$resultSet = executeQuery("insert into work_flow_instances (work_flow_definition_id,creator_user_id," .
							"responsible_user_id,start_date,date_due) values (?,?,?,?,?)", $workFlowInstanceRow['work_flow_definition_id'],
							$GLOBALS['gUserId'], $workFlowInstanceRow['responsible_user_id'], $startDate, $dateDue);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						$workFlowInstanceId = $resultSet['insert_id'];
						$resultSet = executeQuery("select * from work_flow_details where work_flow_definition_id = ?",
							$workFlowDefinitionId);
						while ($row = getNextRow($resultSet)) {
							$insertSet = executeQuery("insert into work_flow_instance_details (work_flow_detail_code,work_flow_instance_id," .
								"task_type_id,task_description,email_id,email_address,sequence_number,work_flow_status_id,start_rules," .
								"days_required,user_id,user_group_id) values " .
								"(?,?,?,?,?,?,?,?,?,?,?,?)", $row['work_flow_detail_code'], $workFlowInstanceRow['work_flow_definition_id'],
								$row['task_type_id'], (empty($row['task_description']) ? $row['description'] : $row['task_description']),
								$row['email_id'], $row['email_address'], $row['sequence_number'], $row['work_flow_status_id'],
								$row['start_rules'], $row['days_required'], $row['user_id'], $row['user_group_id']);
							if (!empty($insertSet['sql_error'])) {
								$returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
								$this->iDatabase->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
						}
					}
				}

# Check to see if any tasks should be created or emails sent. This can happen if an instance is created and a task/email is
# scheduled to happen immediately (0 days after instance is created). It can also happen if a date due is added to the
# instance and there are tasks/emails scheduled to happen some number of days before the date due.

				$resultSet = executeQuery("select * from work_flow_instances where work_flow_instance_id = ?", $workFlowInstanceId);
				if (!$workFlowInstanceRow = getNextRow($resultSet)) {
					$workFlowInstanceRow = array();
				}
				$resultSet = executeQuery("select * from work_flow_instance_details where work_flow_instance_id = ? and " .
					"task_id is null and email_sent is null order by sequence_number", $workFlowInstanceId);
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
						$insertSet = executeQuery("insert into tasks (client_id,description,project_id,creator_user_id,assigned_user_id,user_group_id,task_type_id," .
							"start_time,date_due) values (?,?,?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $row['task_description'], $workFlowInstanceRow['project_id'],
							$workFlowInstanceRow['creator_user_id'], $row['user_id'], $row['user_group_id'], $row['task_type_id'],
							$this->iDatabase->makeDatetimeParameter($startDate), $dateDue);
						$newTaskId = $insertSet['insert_id'];
						$updateSet = executeQuery("update work_flow_instance_details set task_id = ? where work_flow_instance_detail_id = ? and task_id is null",
							$newTaskId, $row['work_flow_instance_detail_id']);
						if ($updateSet['affected_rows'] == 0) {
							executeQuery("delete from tasks where task_id = ?", $newTaskId);
						} else if (!empty($row['work_flow_status_id'])) {
							executeQuery("update work_flow_instances set work_flow_status_id = ? where work_flow_instance_id = ?",
								$row['work_flow_status_id'], $row['work_flow_instance_id']);
							if (!empty($row['user_id']) && $row['user_id'] != $GLOBALS['gUserId']) {
								$emailAddress = Contact::getUserContactField($row['user_id'], "email_address");
								if (!empty($emailAddress)) {
									$body = "A task has been assigned to you. The task is described as '" . $row['task_description'] . "'" .
										(empty($workFlowInstanceRow['project_id']) ? "" : " and is part of the project '" . getFieldFromId("description", "projects", "project_id", $workFlowInstanceRow['project_id']) . "'") .
										". You can view the task <a href='http://" . $_SERVER['HTTP_HOST'] . "/taskmanager.php?task_id=" . $newTaskId . "'>here</a>.";
									sendEmail(array("body" => $body, "subject" => "Task Assigned", "email_addresses" => $emailAddress));
								}
							}
						}
					} else if (!empty($row['email_id'])) {
						$emailAddress = (empty($row['email_address']) ? getFieldFromId("email_address", "contacts", "contact_id", $workFlowInstanceRow['contact_id']) : $row['email_address']);
						if (!empty($emailAddress)) {
							$updateSet = executeQuery("update work_flow_instance_details set email_sent = now() where work_flow_instance_detail_id = ? and email_sent is null",
								$row['work_flow_instance_detail_id']);
							if (!empty($workFlowInstanceRow['contact_id'])) {
								$contactSet = executeQuery("select * from contacts where client_id = ? and contact_id = ?",
									$GLOBALS['gClientId'], $workFlowInstanceRow['contact_id']);
								if (!$substitutions = getNextRow($contactSet)) {
									$substitutions = array();
								}
							}
							if ($updateSet['affected_rows'] > 0) {
								sendEmail(array("email_id" => $row['email_id'], "email_addresses" => $emailAddress, "substitutions" => $substitutions));
								if (!empty($row['work_flow_status_id'])) {
									executeQuery("update work_flow_instances set work_flow_status_id = ? where work_flow_instance_id = ?", $row['work_flow_status_id'], $row['work_flow_instance_id']);
								}
							}
						}
					}
				}

				$this->iDatabase->commitTransaction();
				ajaxResponse($returnArray);

# Get the list of work Flow instances that are active and either created or owned by the user

			case "get_work_flow_instance_list":
				$columnArray = array("description", "creator_user", "responsible_user", "start_date", "date_due", "status");
				$valuesArray = Page::getPagePreferences();
				$valuesArray['show_completed_work_flow_instances'] = (empty($_POST['show_completed_work_flow_instances']) ? "false" : "true");
				$showCompletedWorkFlowInstances = ($valuesArray['show_completed_work_flow_instances'] == "true");
				if ($showCompletedWorkFlowInstances) {
					$columnArray[] = "date_completed";
					$columnArray[] = "completing_user";
				}
				$returnArray['show_completed_work_flow_instances'] = $showCompletedWorkFlowInstances;

				$sortOrderColumn = $_POST['_work_flow_instance_sort_order_column'];
				$reverseSortOrder = ($_POST['_work_flow_instance_reverse_sort_order'] == "true");
				if (empty($sortOrderColumn) || !in_array($sortOrderColumn, $columnArray)) {
					$sortOrderColumn = "start_date";
					$reverseSortOrder = false;
				}
				$valuesArray['work_flow_instance_sort_order_column'] = $sortOrderColumn;
				$valuesArray['work_flow_instance_reverse_sort_order'] = ($reverseSortOrder ? "true" : "false");
				Page::setPagePreferences($valuesArray);
				$orderBy = "order by ISNULL(" . $sortOrderColumn . ")," . $sortOrderColumn . ($reverseSortOrder ? " desc" : "");

				$workFlowInstanceList = array();

				$resultSet = executeQuery("select work_flow_instance_id,(select description from work_flow_definitions where work_flow_definition_id = work_flow_instances.work_flow_definition_id) description," .
					"(select concat_ws(' ',first_name,last_name) from contacts where contact_id = (select contact_id from users where user_id = work_flow_instances.creator_user_id)) creator_user," .
					"(select concat_ws(' ',first_name,last_name) from contacts where contact_id = (select contact_id from users where user_id = work_flow_instances.responsible_user_id)) responsible_user," .
					"start_date,date_due,date_completed,(select description from work_flow_status where work_flow_status_id = work_flow_instances.work_flow_status_id) status," .
					"(select display_color from work_flow_status where work_flow_status_id = work_flow_instances.work_flow_status_id) display_color," .
					"(select concat_ws(' ',first_name,last_name) from contacts where contact_id = (select contact_id from users where user_id = work_flow_instances.completing_user_id)) completing_user " .
					"from work_flow_instances where inactive = 0 and (creator_user_id = ? or responsible_user_id = ?) " . $orderBy, $GLOBALS['gUserId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$row['date_completed'] = (empty($row['date_completed']) ? "" : date("m/d/Y", strtotime($row['date_completed'])));
					$row['date_due'] = (empty($row['date_due']) ? "" : date("m/d/Y", strtotime($row['date_due'])));
					$row['start_date'] = (empty($row['start_date']) ? "" : date("m/d/Y", strtotime($row['start_date'])));
					$row['description'] = getFirstPart($row['description'], 60);
					$row['milestone'] = getFirstPart($row['milestone'], 20);
					foreach ($row as $fieldName => $fieldData) {
						if (is_null($fieldData)) {
							$row[$fieldName] = "";
						}
					}
					if (!empty($row['display_color'])) {
						$row['cell_style'] = "font-weight: bold; color: " . $row['display_color'] . ";";
					}
					if ($showCompletedWorkFlowInstances || empty($row['date_completed'])) {
						$workFlowInstanceList[] = $row;
					}
				}
				$returnArray['column_headers'] = array();
				$returnArray['column_headers'][] = array("column_name" => "description", "description" => "Description" . ($sortOrderColumn == "description" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				$returnArray['column_headers'][] = array("column_name" => "creator_user", "description" => "Created By" . ($sortOrderColumn == "creator_user" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				$returnArray['column_headers'][] = array("column_name" => "responsible_user", "description" => "Supervisor" . ($sortOrderColumn == "responsible_user" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				$returnArray['column_headers'][] = array("column_name" => "start_date", "description" => "Started" . ($sortOrderColumn == "start_date" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				$returnArray['column_headers'][] = array("column_name" => "date_due", "description" => "Date Due" . ($sortOrderColumn == "date_due" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				$returnArray['column_headers'][] = array("column_name" => "status", "description" => "Status" . ($sortOrderColumn == "status" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				if ($showCompletedWorkFlowInstances) {
					$returnArray['column_headers'][] = array("column_name" => "date_completed", "description" => "Completed" . ($sortOrderColumn == "date_completed" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
					$returnArray['column_headers'][] = array("column_name" => "completing_user", "description" => "Completed By" . ($sortOrderColumn == "completing_user" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				}
				$returnArray['work_flow_instance_list'] = $workFlowInstanceList;
				ajaxResponse($returnArray);

# Create a task category, either for the user or for everyone

			case "create_task_category":
				$description = $_POST['task_category_description'];
				$userId = ($_POST['task_category_user'] == "Y" ? "" : $GLOBALS['gUserId']);
				$resultSet = executeQuery("select * from task_categories where description = ? and (user_id = ? or user_id is null) and " .
					"client_id = ?", $description, $GLOBALS['gUserId'], $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['error_message'] = getSystemMessage("task_category_exists");
				} else {
					$resultSet = executeQuery("insert into task_categories (client_id,description,user_id,creator_user_id) values (?,?,?,?)",
						$GLOBALS['gClientId'], $description, $userId, $GLOBALS['gUserId']);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = $resultSet['sql_error'];
					}
				}
				ajaxResponse($returnArray);

# get a list of all the task_categories either for the user or for everyone

			case "get_categories":
				$returnArray['category_list'] = array();
				$resultSet = executeQuery("select * from task_categories where client_id = ? and " .
					"inactive = 0 and (user_id is null or user_id = ?) order by sort_order,description",
					$GLOBALS['gClientId'], $GLOBALS['gUserId']);
				while ($row = getNextRow($resultSet)) {
					$returnArray['category_list'][] = array("task_category_id" => $row['task_category_id'], "description" => $row['description'], "access" => (empty($row['user_id']) ? "Everyone" : "Only You"));
				}
				ajaxResponse($returnArray);

# delete a task. Make sure the task belongs to the user and it is not an appointment. If it is an
# instance of a repeating task, make sure to update the repeating task to exclude that date.

			case "delete_task":
				$this->iDatabase->startTransaction();
				$taskId = $_GET['task_id'];
				$resultSet = executeQuery("select * from tasks where parent_task_id is null and task_id = ? and (assigned_user_id = ? or " .
					"((assigned_user_id is null or date_completed is not null) and user_group_id in " .
					"(select user_group_id from user_group_members where user_id = ?)) or " .
					"creator_user_id = ?) and (task_type_id not in " .
					"(select task_type_id from task_type_attributes where task_attribute_id = (select task_attribute_id from task_attributes where task_attribute_code = 'APPOINTMENT'))) and " .
					"(start_time is not null or repeating_task_id is null) and repeat_rules is null",
					$taskId, $GLOBALS['gUserId'], $GLOBALS['gUserId'], $GLOBALS['gUserId']);
				if ($taskRow = getNextRow($resultSet)) {
					if (!empty($taskRow['repeating_task_id'])) {
						$repeatRules = getFieldFromId("repeat_rules", "tasks", "task_id", $taskRow['repeating_task_id']);
						if (!empty($repeatRules)) {
							$parsedRepeatRules = parseNameValues($repeatRules);
							if ($parsedRepeatRules['frequency'] != "AFTER") {
								$repeatRules .= "NOT=" . date("m/d/Y", strtotime($taskRow['start_time'])) . ";";
								executeQuery("update tasks set repeat_rules = ? where task_id = ?", $repeatRules, $taskRow['repeating_task_id']);
							}
						}
					}
					executeQuery("update work_flow_instance_details set task_id = null where task_id = ?", $taskId);
					$this->addProjectLog(getFieldFromId("project_id", "tasks", "task_id", $taskId), "Deleted task '" . getFieldFromId("description", "tasks", "task_id", $taskId) . "'.");
					$deleteRows = array();
					$resultSet = executeQuery("select * from task_images where task_id = ?", $taskId);
					while ($row = getNextRow($resultSet)) {
						$deleteRows[] = array("table_name" => "images", "key_name" => "image_id", "key_value" => $row['image_id']);
					}
					$resultSet = executeQuery("select * from task_data where task_id = ? and image_id is not null", $taskId);
					while ($row = getNextRow($resultSet)) {
						$deleteRows[] = array("table_name" => "images", "key_name" => "image_id", "key_value" => $row['image_id']);
					}
					$resultSet = executeQuery("select * from task_attachments where task_id = ?", $taskId);
					while ($row = getNextRow($resultSet)) {
						$deleteRows[] = array("table_name" => "files", "key_name" => "file_id", "key_value" => $row['file_id']);
					}
					$resultSet = executeQuery("select * from task_data where task_id = ? and file_id is not null", $taskId);
					while ($row = getNextRow($resultSet)) {
						$deleteRows[] = array("table_name" => "files", "key_name" => "file_id", "key_value" => $row['file_id']);
					}
					executeQuery("update tasks set repeating_task_id = null where repeating_task_id = ?", $taskId);
					$taskTables = array("task_category_links", "task_data", "task_images", "task_attachments", "task_log", "task_time_log", "task_user_attendees", "task_user_group_attendees", array("table_name" => "tasks", "key_name" => "parent_task_id"), "tasks");
					foreach ($taskTables as $deleteTable) {
						if (is_array($deleteTable)) {
							$tableName = $deleteTable['table_name'];
							$keyName = $deleteTable['key_name'];
						} else {
							$tableName = $deleteTable;
							$keyName = "task_id";
						}
						$resultSet = executeQuery("delete from $tableName where $keyName = ?", $taskId);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
					foreach ($deleteRows as $deleteInfo) {
						executeQuery("delete from " . $deleteInfo['table_name'] . " where " . $deleteInfo['key_name'] . " = " . $deleteInfo['key_value']);
					}
					$this->iDatabase->commitTransaction();
					if (!empty($taskRow['assigned_user_id']) && $taskRow['assigned_user_id'] != $taskRow['creator_user_id'] && $taskRow['creator_user_id'] == $GLOBALS['gUserId']) {
						$emailAddress = Contact::getUserContactField($taskRow['assigned_user_id'],"email_address");
						if (!empty($emailAddress)) {
							$body = "The task '" . $taskRow['description'] . "', which was assigned to you, was deleted by the creator of the task (" . getUserDisplayName() . ").";
							sendEmail(array("body" => $body, "subject" => "Task Deleted", "email_addresses" => $emailAddress));
						}
					}
				}
				ajaxResponse($returnArray);

# Save changes to the task. This is a long and complicated process, because there could be many bits of data for a task.
# Also, whenever a task is marked completed, we have to check to see if another task should be created, either because the
# task is repeating or because the task is part of a work flow instance and other tasks are dependent on this task.

			case "save_task":
				$taskId = $_POST['task_id'];
				$this->getDataSource()->setPrimaryId($taskId);

# generate the repeat rules, if any

				$repeatRules = "";
				if ($_POST['repeating'] == "1") {
					$_POST['date_due'] = "";
					if (empty($_POST['due_days'])) {
						$_POST['due_days'] = "0";
					}
					$repeatRules = "FREQUENCY=" . $_POST['frequency'] . ";";
					$repeatRules .= "START_DATE=" . (empty($_POST['start_date']) ? date("m/d/Y") : date("m/d/Y", strtotime($_POST['start_date']))) . ";";
					if (!empty($_POST['end_date'])) {
						$repeatRules .= "UNTIL=" . date("m/d/Y", strtotime($_POST['end_date'])) . ";";
					}
					if (!empty($_POST['interval']) && $_POST['interval'] != "1") {
						$repeatRules .= "INTERVAL=" . $_POST['interval'] . ";";
					} else {
						$repeatRules .= "INTERVAL=1;";
					}
					if ($_POST['frequency'] == "AFTER") {
						$repeatRules .= "UNITS=" . $_POST['units'] . ";";
					} else if ($_POST['frequency'] == "YEARLY") {
						$repeatRules .= "BYMONTH=";
						$parts = "";
						foreach ($_POST as $fieldName => $fieldData) {
							if (substr($fieldName, 0, strlen("bymonth_")) == "bymonth_" && !empty($fieldData)) {
								if (!empty($parts)) {
									$parts .= ",";
								}
								$parts .= $fieldData;
							}
						}
						$repeatRules .= $parts . ";";
						$repeatRules .= "BYDAY=";
						$parts = "";
						foreach ($_POST as $fieldName => $fieldData) {
							if (substr($fieldName, 0, strlen("ordinal_day_")) == "ordinal_day_" && !empty($fieldData)) {
								$fieldNumber = substr($fieldName, strlen("ordinal_day_"));
								if (!empty($fieldData)) {
									if (!empty($parts)) {
										$parts .= ",";
									}
									$parts .= $fieldData;
									$parts .= $_POST['weekday_' . $fieldNumber];
								}
							}
						}
						$repeatRules .= $parts . ";";
					} else if ($_POST['frequency'] == "WEEKLY") {
						$repeatRules .= "BYDAY=";
						$parts = "";
						foreach ($_POST as $fieldName => $fieldData) {
							if (substr($fieldName, 0, strlen("byday_")) == "byday_" && !empty($fieldData)) {
								if (!empty($parts)) {
									$parts .= ",";
								}
								$parts .= $fieldData;
							}
						}
						$repeatRules .= $parts . ";";
					} else if ($_POST['frequency'] == "MONTHLY") {
						$repeatRules .= "BYDAY=";
						$parts = "";
						foreach ($_POST as $fieldName => $fieldData) {
							if (substr($fieldName, 0, strlen("ordinal_day_")) == "ordinal_day_" && !empty($fieldData)) {
								$fieldNumber = substr($fieldName, strlen("ordinal_day_"));
								if (!empty($fieldData)) {
									if (!empty($parts)) {
										$parts .= ",";
									}
									$parts .= $fieldData;
									$parts .= $_POST['weekday_' . $fieldNumber];
								}
							}
						}
						$repeatRules .= $parts . ";";
					}
					if (!empty($_POST['count'])) {
						$repeatRules .= "COUNT=" . $_POST['count'] . ";";
					}
					if (!empty($taskId)) {
						$oldRepeatRules = parseNameValues(getFieldFromId("repeat_rules", "tasks", "task_id", $taskId));
						if (!empty($oldRepeatRules['not'])) {
							$repeatRules .= "NOT=" . $oldRepeatRules['not'] . ";";
						}
					}
				} else {
					$_POST['due_days'] = "0";
				}

				$this->getDataSource()->disableTransactions();
				$this->iDatabase->startTransaction();

# If there is an existing task (ie. the task is being updated and not created), get the original data.

				if (!empty($taskId)) {
					$resultSet = executeQuery("select * from tasks where task_id = ?", $taskId);
					if (!$taskRow = getNextRow($resultSet)) {
						$returnArray['error_message'] = getSystemMessage("task_not_found");
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$taskTypeId = $taskRow['task_type_id'];
				} else {
					$taskRow = array();
					$taskTypeId = $_POST['task_type_id'];
				}

# insert the new task or update the existing task

				$taskData = $_POST;
				$taskData['start_time'] = $this->iDatabase->makeDatetimeParameter($_POST['start_date'], $_POST['start_time']);
				$taskData['end_time'] = $this->iDatabase->makeDatetimeParameter($_POST['end_date'], $_POST['end_time']);
				$taskData['repeat_rules'] = $repeatRules;
				$taskData['primary_id'] = $taskId;
				$taskId = $this->getDataSource()->saveRecord(array("name_values" => $taskData));
				if ($taskId === false) {
					$returnArray['error_message'] = getSystemMessage("basic", $this->getDataSource()->getErrorMessage());
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}

# Send Email for newly assigned task

				if (!empty($_POST['assigned_user_id']) && $_POST['assigned_user_id'] != $taskRow['assigned_user_id'] && $_POST['assigned_user_id'] != $GLOBALS['gUserId']) {
					$emailAddress = Contact::getUserContactField($_POST['assigned_user_id'],"email_address");
					if (!empty($emailAddress)) {
						$body = "A task described as '" . $taskData['description'] . "'" . (empty($taskData['project_id']) ? "" : " and part of the project '" . getFieldFromId("description", "projects", "project_id", $taskData['project_id']) . "'") .
							" has been assigned to you. You can view the task <a href='http://" . $_SERVER['HTTP_HOST'] . "/taskmanager.php?task_id=" . $taskId . "'>here</a>.";
						sendEmail(array("body" => $body, "subject" => "Task Assigned", "email_addresses" => $emailAddress));
					}
				}

# log changes or additions to task that is in a project

				if (empty($taskRow['task_id'])) {
					if (!empty($_POST['project_id'])) {
						$content = "";
						if (!empty($_POST['assigned_user_id'])) {
							$content = "is assigned to " . getUserDisplayName($_POST['assigned_user_id']);
						}
						if (!empty($_POST['date_due'])) {
							if (!empty($content)) {
								$content .= " and ";
							}
							$content .= "is due on " . date("m/d/Y", strtotime($_POST['date_due']));
						}
						if (!empty($_POST['date_completed'])) {
							if (!empty($content)) {
								$content .= " and ";
							}
							$content .= "was completed on " . date("m/d/Y", strtotime($_POST['date_completed']));
						}
						if (!empty($_POST['project_milestone_id'])) {
							if (!empty($content)) {
								$content .= " and ";
							}
							$content .= "is in milestone '" . getFieldFromId("description", "project_milestones", "project_milestone_id", $_POST['project_milestone_id']) . "'";
						}
						$content = "Created task '" . $_POST['description'] . "' and added it to the project." . (empty($content) ? "" : " It " . $content);
						$this->addProjectLog($_POST['project_id'], $content);
					}
				} else {
					if ($taskRow['project_id'] != $_POST['project_id']) {
						$this->addProjectLog($taskRow['project_id'], "Removed task '" . $taskRow['description'] . "' from the project.");
						if (!empty($_POST['project_id'])) {
							$content = "";
							if (!empty($taskRow['assigned_user_id'])) {
								$content = "is assigned to " . getUserDisplayName($taskRow['assigned_user_id']);
							}
							if (!empty($taskRow['date_due'])) {
								if (!empty($content)) {
									$content .= " and ";
								}
								$content .= "is due on " . date("m/d/Y", strtotime($taskRow['date_due']));
							}
							if (!empty($taskRow['date_completed'])) {
								if (!empty($content)) {
									$content .= " and ";
								}
								$content .= "was completed on " . date("m/d/Y", strtotime($taskRow['date_completed']));
							}
							if (!empty($taskRow['project_milestone_id'])) {
								if (!empty($content)) {
									$content .= " and ";
								}
								$content .= "is in milestone '" . getFieldFromId("description", "project_milestones", "project_milestone_id", $taskRow['project_milestone_id']) . "'";
							}
							$content = "Added task '" . $_POST['description'] . "' to the project." . (empty($content) ? "" : " It " . $content);
							$this->addProjectLog($_POST['project_id'], $content);
						}
					} else if (!empty($taskRow['project_id'])) {
						$content = "";
						if ($taskRow['description'] != $_POST['description']) {
							$content .= "is '" . $_POST['description'] . "'";
						}
						if ($taskRow['assigned_user_id'] != $_POST['assigned_user_id']) {
							if (!empty($content)) {
								$content .= " and ";
							}
							$content .= "is assigned to " . (empty($_POST['assigned_user_id']) ? "no individual" : getUserDisplayName($_POST['assigned_user_id']));
						}
						if ($taskRow['date_due'] != (empty($_POST['date_due']) ? "" : date("Y-m-d", strtotime($_POST['date_due'])))) {
							if (!empty($content)) {
								$content .= " and ";
							}
							$content .= (empty($_POST['date_due']) ? "has no due date" : "is due on " . date("m/d/Y", strtotime($_POST['date_due'])));
						}
						if ($taskRow['date_completed'] != (empty($_POST['date_completed']) ? "" : date("Y-m-d", strtotime($_POST['date_completed'])))) {
							if (!empty($content)) {
								$content .= " and ";
							}
							$content .= (empty($_POST['date_completed']) ? "is not completed" : "is set as completed on " . date("m/d/Y", strtotime($taskRow['date_completed'])));
						}
						if ($taskRow['project_milestone_id'] != $_POST['project_milestone_id']) {
							if (!empty($content)) {
								$content .= " and ";
							}
							$content .= (empty($_POST['project_milestone_id']) ? "is not part of a milestone" : "is part of milestone '" . getFieldFromId("description", "project_milestones", "project_milestone_id", $_POST['project_milestone_id']) . "'");
						}
						if (!empty($content)) {
							$this->addProjectLog($_POST['project_id'], "Updated task '" . $taskRow['description'] . "'. It now " . $content);
						}
					}
				}

# if this task is completed and it is part of a work flow, check to see if another task should be created

				while (empty($_POST['my_task']) && empty($taskRow['date_completed']) && !empty($_POST['date_completed'])) {
					$emailAddresses = array();
					$creatorUserId = getFieldFromId("creator_user_id", "tasks", "task_id", $taskId);
					if (!empty($creatorUserId) && $creatorUserId != $GLOBALS['gUserId']) {
						$thisEmailAddress = Contact::getUserContactField($creatorUserId,"email_address");
						if (!empty($thisEmailAddress) && !in_array($thisEmailAddress, $emailAddresses)) {
							$emailAddresses[] = $thisEmailAddress;
						}
					}
					$projectDescription = getFieldFromId("description", "projects", "project_id", getFieldFromId("project_id", "tasks", "task_id", $taskId));
					$body = "The task '" . getFieldFromId("description", "tasks", "task_id", $taskId) . "'" . (empty($projectDescription) ? "" : " from the project '" . $projectDescription . "'") . ", which you created, has been completed.";
					if (!empty($emailAddresses)) {
						sendEmail(array("subject" => "Task Completed", "body" => $body, "email_addresses" => $emailAddresses));
					}
# see if this task is part of a work flow instance
					$resultSet = executeQuery("select * from work_flow_instance_details where task_id = ?", $taskId);
					if (!$instanceDetailRow = getNextRow($resultSet)) {
						break;
					}
# see if there are other tasks that are not yet created that might be dependent on this task being completed
					$resultSet = executeQuery("select * from work_flow_instance_details where task_id is null and email_sent is null and " .
						"work_flow_instance_id = ? order by sequence_number", $instanceDetailRow['work_flow_instance_id']);
					while ($row = getNextRow($resultSet)) {
						$startRules = parseNameValues($row['start_rules']);
						if (!empty($startRules['completed'])) {
							$detailCodes = explode(",", $startRules['completed']);
							$allCompleted = true;
							foreach ($detailCodes as $detailCode) {
								$taskId = getFieldFromId("task_id", "work_flow_instance_details", "work_flow_detail_code", $detailCode,
									"work_flow_instance_id = " . $row['work_flow_instance_id'] . " and task_id in (select task_id from tasks where " .
									"tasks.task_id = work_flow_instance_details.task_id and date_completed is not null)");
								$emailSent = getFieldFromId("email_sent", "work_flow_instance_details", "work_flow_detail_code", $detailCode,
									"work_flow_instance_id = " . $row['work_flow_instance_id']);
								if (empty($taskId) && empty($emailSent)) {
									$allCompleted = false;
									break;
								}
							}
							if ($allCompleted) {
								$workFlowInstanceSet = executeQuery("select * from work_flow_instances where work_flow_instance_id = ?", $instanceDetailRow['work_flow_instance_id']);
								$workFlowInstanceRow = getNextRow($workFlowInstanceSet);
								if (!empty($row['task_type_id'])) {
									$startDate = date('Y-m-d', strtotime(date("Y-m-d", strtotime($_POST['date_completed'])) . " + " . $startRules['after'] . " " . $startRules['units']));
									$dateDue = (empty($row['days_required']) ? "" : date('Y-m-d', strtotime("+" . $row['days_required'] . " days", strtotime($startDate))));
									$insertSet = executeQuery("insert into tasks (client_id,description,project_id,creator_user_id,assigned_user_id,user_group_id,task_type_id," .
										"start_time,date_due) values (?,?,?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $row['task_description'], $workFlowInstanceRow['project_id'], $workFlowInstanceRow['creator_user_id'],
										$row['user_id'], $row['user_group_id'], $row['task_type_id'], $this->iDatabase->makeDatetimeParameter($startDate), $dateDue);
									$newTaskId = $insertSet['insert_id'];
									$updateSet = executeQuery("update work_flow_instance_details set task_id = ? where work_flow_instance_detail_id = ? and task_id is null",
										$newTaskId, $row['work_flow_instance_detail_id']);
									if ($updateSet['affected_rows'] == 0) {
										executeQuery("delete from tasks where task_id = ?", $newTaskId);
									} else if (!empty($row['work_flow_status_id'])) {
										executeQuery("update work_flow_instances set work_flow_status_id = ? where work_flow_instance_id = ?", $row['work_flow_status_id'], $row['work_flow_instance_id']);
										if (!empty($row['user_id']) && $row['user_id'] != $GLOBALS['gUserId']) {
											$emailAddress = Contact::getUserContactField($row['user_id'],"email_address");
											if (!empty($emailAddress)) {
												$body = "A task described as '" . $row['task_description'] . "'" . (empty($workFlowInstanceRow['project_id']) ? "" : " and part of the project '" . getFieldFromId("description", "projects", "project_id", $workFlowInstanceRow['project_id']) . "'") .
													" has been assigned to you. You can view the task <a href='http://" . $_SERVER['HTTP_HOST'] . "/taskmanager.php?task_id=" . $newTaskId . "'>here</a>.";
												sendEmail(array("body" => $body, "subject" => "Task Assigned", "email_addresses" => $emailAddress));
											}
										}
									}
								} else if (!empty($row['email_id'])) {
									$emailAddress = (empty($row['email_address']) ? getFieldFromId("email_address", "contacts", "contact_id", $workFlowInstanceRow['contact_id']) : $row['email_address']);
									if (!empty($emailAddress)) {
										$updateSet = executeQuery("update work_flow_instance_details set email_sent = now() where work_flow_instance_detail_id = ? and email_sent is null",
											$row['work_flow_instance_detail_id']);
										if ($updateSet['affected_rows'] > 0) {
											sendEmail(array("email_id" => $row['email_id'], "email_addresses" => $emailAddress));
											if (!empty($row['work_flow_status_id'])) {
												executeQuery("update work_flow_instances set work_flow_status_id = ? where work_flow_instance_id = ?", $row['work_flow_status_id'], $row['work_flow_instance_id']);
											}
										}
									}
								}
							}
						}
					}
					break;
				}

# If this task is an instance of a repeating task and the repeating task's frequency is "AFTER", create a new task, if necessary

				if (empty($_POST['my_task']) && !empty($taskRow['repeating_task_id'])) {
					$repeatRules = parseNameValues(getFieldFromId("repeat_rules", "tasks", "task_id", $taskRow['repeating_task_id']));
					if (empty($repeatRules['interval'])) {
						$repeatRules['interval'] = 1;
					}
					if ($repeatRules['frequency'] == "AFTER" && empty($taskRow['date_completed']) && !empty($_POST['date_completed'])) {
						$resultSet = executeQuery("select * from tasks where repeating_task_id = ? and start_time > ?",
							$taskRow['repeating_task_id'], $taskRow['start_date']);
						if (!$row = getNextRow($resultSet)) {
							$startDate = date('Y-m-d', strtotime(date("Y-m-d", strtotime($_POST['date_completed'])) . " + " . $repeatRules['interval'] . " " . $repeatRules['units']));
							$resultSet = executeQuery("select * from tasks where task_id = ?", $taskRow['repeating_task_id']);
							if ($row = getNextRow($resultSet)) {
								$endDate = (empty($row['end_time']) ? "" : date("Y-m-d", strtotime($row['end_time'])));
								$dateCompleted = (empty($row['date_completed']) ? "" : date("Y-m-d", strtotime($row['date_completed'])));
								if ($dateCompleted < $endDate || empty($endDate)) {
									$endDate = $dateCompleted;
								}
								if (empty($endDate) || $startDate <= $endDate) {
									$instanceRow = $row;
									if (!empty($row['due_days'])) {
										$dateDue = date('Y-m-d', strtotime($startDate . " + " . $row['due_days'] . " days"));
										$instanceRow['date_due'] = $dateDue;
										$instanceRow['due_days'] = 0;
									}

# Create the new task
									$insertSet = executeQuery("insert into tasks (client_id,description,detailed_description,prerequisites,task_group,project_id,project_milestone_id," .
										"creator_user_id,assigned_user_id,user_group_id,task_type_id,priority,start_time,end_time,date_due,due_days,date_completed," .
										"completing_user_id,all_day,public_access,percent_complete,estimated_hours) values (" .
										"?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $instanceRow['description'], $instanceRow['detailed_description'], $instanceRow['prerequisites'],
										$instanceRow['task_group'], $instanceRow['project_id'], $instanceRow['project_milestone_id'], $instanceRow['creator_user_id'], $instanceRow['assigned_user_id'], $instanceRow['user_group_id'],
										$instanceRow['task_type_id'], $instanceRow['priority'], $this->iDatabase->makeDatetimeParameter($startDate, $instanceRow['start_time']),
										$this->iDatabase->makeDatetimeParameter($instanceRow['end_time'], $instanceRow['end_time']), $this->iDatabase->makeDateParameter($instanceRow['date_due']), $instanceRow['due_days'],
										$this->iDatabase->makeDateParameter($instanceRow['date_completed']), $instanceRow['completing_user_id'], ($instanceRow['all_day'] == "1" ? 1 : 0),
										($instanceRow['public_access'] == "1" ? 1 : 0), $instanceRow['percent_complete'], $instanceRow['estimated_hours']);
									$newTaskId = $insertSet['insert_id'];

									if (!empty($instanceRow['assigned_user_id']) && $instanceRow['assigned_user_id'] != $GLOBALS['gUserId']) {
										$emailAddress = Contact::getUserContactField($instanceRow['assigned_user_id'],"email_address");
										if (!empty($emailAddress)) {
											$body = "A task described as '" . $instanceRow['description'] . "'" . (empty($instanceRow['project_id']) ? "" : " and part of the project '" . getFieldFromId("description", "projects", "project_id", $instanceRow['project_id']) . "'") .
												" has been assigned to you. You can view the task <a href='http://" . $_SERVER['HTTP_HOST'] . "/taskmanager.php?task_id=" . $newTaskId . "'>here</a>.";
											sendEmail(array("body" => $body, "subject" => "Task Assigned", "email_addresses" => $emailAddress));
										}
									}
									if (!empty($instanceRow['project_id'])) {
										$content = "";
										if (!empty($instanceRow['assigned_user_id'])) {
											$content = "is assigned to " . getUserDisplayName($instanceRow['assigned_user_id']);
										}
										if (!empty($instanceRow['date_due'])) {
											if (!empty($content)) {
												$content .= " and ";
											}
											$content .= "is due on " . date("m/d/Y", strtotime($instanceRow['date_due']));
										}
										if (!empty($instanceRow['date_completed'])) {
											if (!empty($content)) {
												$content .= " and ";
											}
											$content .= "was completed on " . date("m/d/Y", strtotime($instanceRow['date_completed']));
										}
										if (!empty($instanceRow['project_milestone_id'])) {
											if (!empty($content)) {
												$content .= " and ";
											}
											$content .= "is in milestone '" . getFieldFromId("description", "project_milestones", "project_milestone_id", $instanceRow['project_milestone_id']) . "'";
										}
										$content = "Created task '" . $_POST['description'] . "' and added it to the project." . (empty($content) ? "" : " It " . $content);
										$this->addProjectLog($instanceRow['project_id'], $content);
									}

# Create the new subtasks
									$subSet = executeQuery("select * from tasks where parent_task_id = ?", $row['task_id']);
									while ($subRow = getNextRow($subSet)) {
										$insertSet = executeQuery("insert into tasks (client_id,description,detailed_description,date_completed,parent_task_id) " .
											"values (?,?,?,?,?)", $GLOBALS['gClientId'], $subRow['description'], $subRow['detailed_description'],
											$subRow['date_completed'], $newTaskId);
									}

# Create the new task attachments
									$subSet = executeQuery("select * from task_attachments where task_id = ?", $row['task_id']);
									while ($subRow = getNextRow($subSet)) {
										$insertSet = executeQuery("insert into files (client_id,file_code,description,detailed_description,date_uploaded," .
											"filename,extension,file_content,os_filename,image_id,public_access,all_user_access,administrator_access," .
											"sort_order,internal_use_only,inactive) select client_id,file_code,description,detailed_description,date_uploaded," .
											"filename,extension,file_content,os_filename,image_id,public_access,all_user_access,administrator_access," .
											"sort_order,internal_use_only,inactive from files where file_id = ?", $subRow['file_id']);
										$newFileId = $insertSet['insert_id'];
										$insertSet = executeQuery("insert into task_attachments (task_id,description,file_id) values (?,?,?)", $newTaskId, $subRow['description'], $newFileId);
									}
# Create the new task category links
									$subSet = executeQuery("insert ignore into task_category_links (task_id,task_category_id) select $newTaskId,task_category_id from task_category_links where task_id = ?", $row['task_id']);

# Create the new task custom data fields
									$subSet = executeQuery("select * from task_data where task_id = ?", $row['task_id']);
									while ($subRow = getNextRow($subSet)) {
										if (!empty($subRow['file_id'])) {
											$insertSet = executeQuery("insert into files (client_id,file_code,description,detailed_description,date_uploaded," .
												"filename,extension,file_content,os_filename,image_id,public_access,all_user_access,administrator_access," .
												"sort_order,internal_use_only,inactive) select client_id,file_code,description,detailed_description,date_uploaded," .
												"filename,extension,file_content,os_filename,image_id,public_access,all_user_access,administrator_access," .
												"sort_order,internal_use_only,inactive from files where file_id = ?", $subRow['file_id']);
											$newFileId = $insertSet['insert_id'];
										} else {
											$newFileId = "";
										}
										if (!empty($subRow['image_id'])) {
											$insertSet = executeQuery("insert into images (client_id,image_code,extension,filename,description,detailed_description,date_uploaded," .
												"file_content,image_size) select client_id,image_code,extension,filename,description,detailed_description,date_uploaded," .
												"file_content,image_size from images where image_id = ?", $subRow['image_id']);
											$newImageId = $insertSet['insert_id'];
										} else {
											$newImageId = "";
										}
										$insertSet = executeQuery("insert into task_data (task_id,task_data_definition_id,sequence_number," .
											"integer_data,number_data,text_data,date_data,image_id,file_id) values (?,?,?,?,?,?,?,?,?)",
											$newTaskId, $subRow['task_data_definition_id'], $subRow['sequence_number'], $subRow['integer_data'],
											$subRow['number_data'], $subRow['text_data'], $subRow['date_data'], $newImageId, $newFileId);
									}

# Create the new task images
									$subSet = executeQuery("select * from task_images where task_id = ?", $row['task_id']);
									while ($subRow = getNextRow($subSet)) {
										$insertSet = executeQuery("insert into images (client_id,image_code,extension,filename,description,detailed_description,date_uploaded," .
											"file_content,image_size) select client_id,image_code,extension,filename,description,detailed_description,date_uploaded," .
											"file_content,image_size from images where image_id = ?", $subRow['image_id']);
										$newImageId = $insertSet['insert_id'];
										$insertSet = executeQuery("insert into task_images (task_id,description,image_id) values (?,?,?)",
											$newTaskId, $subRow['description'], $newImageId);
									}
								}
							}
						}
					}
				}

# Save task images

				if (empty($_POST['my_task']) && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'ALLOW_IMAGES'))) {
					$thisColumn = $this->getDataSource()->getColumns("task_images");
					if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
						$controlClass = $thisColumn->getControlValue("control_class");
						$customControl = new $controlClass($thisColumn, $this);
						$customControl->setPrimaryId($taskId);
						if (!$customControl->saveData($_POST)) {
							$returnArray['error_message'] = $customControl->getErrorMessage();
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
				}

# Save task attachments

				if (empty($_POST['my_task']) && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'ALLOW_FILE'))) {
					$thisColumn = $this->getDataSource()->getColumns("task_attachments");
					if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
						$controlClass = $thisColumn->getControlValue("control_class");
						$customControl = new $controlClass($thisColumn, $this);
						$customControl->setPrimaryId($taskId);
						if (!$customControl->saveData($_POST)) {
							$returnArray['error_message'] = $customControl->getErrorMessage();
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
				}

# Save subtasks

				if (empty($_POST['my_task']) && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'HAS_SUBTASKS'))) {
					$thisColumn = $this->getDataSource()->getColumns("subtasks");
					if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
						$controlClass = $thisColumn->getControlValue("control_class");
						$customControl = new $controlClass($thisColumn, $this);
						$customControl->setPrimaryId($taskId);
						if (!$customControl->saveData($_POST)) {
							$returnArray['error_message'] = $customControl->getErrorMessage();
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
				}

# Complete task if all subtasks are completed and that attribute is set

				if (empty($_POST['my_task'])) {
					if ($this->taskTypeAttribute(array("task_type_id" => $_POST['task_type_id'], "task_attributes" => array('HAS_SUBTASKS', "SUBTASKS_COMPLETE"), "all_required" => true)) && empty($_POST['repeating']) && empty($_POST['date_completed'])) {
						$resultSet = executeQuery("select count(*) from tasks where parent_task_id = ?", $taskId);
						if ($row = getNextRow($resultSet)) {
							if ($row['count(*)'] > 0) {
								$resultSet = executeQuery("select count(*) from tasks where parent_task_id = ? and date_completed is null", $taskId);
								if ($row = getNextRow($resultSet)) {
									if ($row['count(*)'] == 0) {
										executeQuery("update tasks set date_completed = current_date where task_id = ?", $taskId);
									}
								}
							}
						}
					}
				}

# Save task time log

				if (empty($_POST['my_task']) && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'LOG_TIME'))) {
					$thisColumn = $this->getDataSource()->getColumns("task_time_log");
					if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
						$controlClass = $thisColumn->getControlValue("control_class");
						$customControl = new $controlClass($thisColumn, $this);
						$customControl->setPrimaryId($taskId);
						if (!$customControl->saveData($_POST)) {
							$returnArray['error_message'] = $customControl->getErrorMessage();
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
				}

# Save task log

				if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'TASK_LOG'))) {
					$taskLogArray = array();
					$resultSet = executeQuery("select * from task_log where task_id = ?", $taskId);
					while ($row = getNextRow($resultSet)) {
						$taskLogArray[$row['log_id']] = $row['content'];
					}
					$thisColumn = $this->getDataSource()->getColumns("task_log");
					if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
						$controlClass = $thisColumn->getControlValue("control_class");
						$customControl = new $controlClass($thisColumn, $this);
						$customControl->setPrimaryId($taskId);
						if (!$customControl->saveData($_POST)) {
							$returnArray['error_message'] = $customControl->getErrorMessage();
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
				}

# Save task categories

				if (empty($_POST['my_task']) && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'USES_CATEGORIES'))) {
					$thisColumn = $this->getDataSource()->getColumns("task_categories");
					if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
						$controlClass = $thisColumn->getControlValue("control_class");
						$customControl = new $controlClass($thisColumn, $this);
						$customControl->setPrimaryId($taskId);
						if (!$customControl->saveData($_POST)) {
							$returnArray['error_message'] = $customControl->getErrorMessage();
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
				}

# Save custom task data fields

				if (empty($_POST['my_task'])) {
					$taskDataSource = new DataSource("task_data");
					$taskDataSource->disableTransactions();
					$taskDataDefinitionSet = executeQuery("select * from task_data_definitions where task_data_definition_id in (select task_data_definition_id from task_type_data where task_type_id = ?)", $_POST['task_type_id']);
					while ($taskDataDefinitionRow = getNextRow($taskDataDefinitionSet)) {
						$dataName = "task_data_definitions-" . strtolower($taskDataDefinitionRow['task_data_definition_code']) . "-" . $taskDataDefinitionRow['task_data_definition_id'];
						if ($taskDataDefinitionRow['allow_multiple'] == 0) {
							$dataValue = $_POST[$dataName];
							$resultSet = executeQuery("select * from task_data where task_id = ? and task_data_definition_id = ? and sequence_number is null",
								$taskId, $taskDataDefinitionRow['task_data_definition_id']);
							if ($row = getNextRow($resultSet)) {
								$taskDataSource->setPrimaryId($row['task_data_id']);
								if (empty($dataValue)) {
									if (!$taskDataSource->deleteRecord()) {
										$returnArray['error_message'] = $taskDataSource->getErrorMessage();
										$this->iDatabase->rollbackTransaction();
										ajaxResponse($returnArray);
										break;
									}
								}
							} else {
								$taskDataSource->setPrimaryId("");
								$row = array("task_id" => $taskId, "task_data_definition_id" => $taskDataDefinitionRow['task_data_definition_id']);
							}
							if (!empty($dataValue)) {
								$fieldName = $this->getFieldFromDataType($taskDataDefinitionRow['data_type']);
								$row[$fieldName] = $dataValue;
								if (!$taskDataSource->saveRecord(array("name_values" => $row))) {
									$returnArray['error_message'] = $taskDataSource->getErrorMessage();
									$this->iDatabase->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
							}
						} else {
							$controlName = (empty($taskDataDefinitionRow['group_identifier']) ? strtolower($taskDataDefinitionRow['task_data_definition_code']) : str_replace(" ", "_", strtolower($taskDataDefinitionRow['group_identifier'])));
							$dataName = $controlName . "_" . $dataName;
							$sequenceNumberArray = array();
							foreach ($_POST as $fieldName => $dataValue) {
								if (substr($fieldName, 0, strlen($dataName . "-")) == $dataName . "-") {
									$sequenceNumber = substr($fieldName, strlen($dataName . "-"));
									if (!is_numeric($sequenceNumber)) {
										continue;
									}
									$sequenceNumberArray[] = $sequenceNumber;
									$resultSet = executeQuery("select * from task_data where task_id = ? and task_data_definition_id = ? and sequence_number = ?",
										$taskId, $taskDataDefinitionRow['task_data_definition_id'], $sequenceNumber);
									if ($row = getNextRow($resultSet)) {
										$taskDataSource->setPrimaryId($row['task_data_id']);
										if (empty($dataValue)) {
											if (!$taskDataSource->deleteRecord()) {
												return $taskDataSource->getErrorMessage();
											}
										}
									} else {
										$taskDataSource->setPrimaryId("");
										$row = array("task_id" => $taskId, "task_data_definition_id" => $taskDataDefinitionRow['task_data_definition_id'], "sequence_number" => $sequenceNumber);
									}
									if (!empty($dataValue)) {
										$fieldName = $this->getFieldFromDataType($taskDataDefinitionRow['data_type']);
										$row[$fieldName] = $dataValue;
										if (!$taskDataSource->saveRecord(array("name_values" => $row))) {
											return $taskDataSource->getErrorMessage();
										}
									}
								}
							}
							$sequenceNumberList = implode(",", $sequenceNumberArray);
							$resultSet = executeQuery("delete from task_data where task_id = ? and task_data_definition_id = ?" .
								(empty($sequenceNumberList) ? "" : " and sequence_number not in ($sequenceNumberList)"),
								$taskId, $taskDataDefinitionRow['task_data_definition_id']);
						}
					}
				}

				$this->iDatabase->commitTransaction();
				$resultSet = executeQuery("select * from task_log where task_id = ?", $taskId);
				$emailTaskLog = "";
				while ($row = getNextRow($resultSet)) {
					if ($taskLogArray[$row['log_id']] != $row['content']) {
						$emailTaskLog .= $row['content'] . "<br>\n<br>\n";
					}
				}
				if (!empty($emailTaskLog)) {
					$emailAddresses = array();
					$creatorUserId = getFieldFromId("creator_user_id", "tasks", "task_id", $taskId);
					$assignedUserId = getFieldFromId("assigned_user_id", "tasks", "task_id", $taskId);
					if (!empty($creatorUserId) && $creatorUserId != $GLOBALS['gUserId']) {
						$thisEmailAddress = Contact::getUserContactField($creatorUserId,"email_address");
						if (!empty($thisEmailAddress) && !in_array($thisEmailAddress, $emailAddresses)) {
							$emailAddresses[] = $thisEmailAddress;
						}
					}
					if (!empty($assignedUserId) && $assignedUserId != $GLOBALS['gUserId']) {
						$thisEmailAddress = Contact::getUserContactField($assignedUserId,"email_address");
						if (!empty($thisEmailAddress) && !in_array($thisEmailAddress, $emailAddresses)) {
							$emailAddresses[] = $thisEmailAddress;
						}
					}
					$projectDescription = getFieldFromId("description", "projects", "project_id", getFieldFromId("project_id", "tasks", "task_id", $taskId));
					$body = "A task log entry was made on the task '" . getFieldFromId("description", "tasks", "task_id", $taskId) .
						"'" . (empty($projectDescription) ? "" : ", part of project '" . $projectDescription . "'") .
						". The addition(s) and/or change(s) are below:<br>\n<br>\n" . $emailTaskLog;
					if (!empty($emailAddresses)) {
						sendEmail(array("subject" => "Task Log", "body" => $body, "email_addresses" => $emailAddresses));
					}
				}
				ajaxResponse($returnArray);

# Set a task as completed.

			case "set_completed":
				$taskId = "";
				$resultSet = executeQuery("select * from tasks where task_id = ? and (assigned_user_id = ? or " .
					"(assigned_user_id is null and user_group_id in " .
					"(select user_group_id from user_group_members where user_id = ?)) or " .
					"(assigned_user_id is null and user_group_id is null and creator_user_id = ?)) and date_completed is null",
					$_GET['task_id'], $GLOBALS['gUserId'], $GLOBALS['gUserId'], $GLOBALS['gUserId']);
				if ($row = getNextRow($resultSet)) {
					$taskId = $row['task_id'];
				}
				if (empty($taskId)) {
					$returnArray['error_message'] = getSystemMessage("task_not_found");
					ajaxResponse($returnArray);
					break;
				}
				$returnArray['task_id'] = $taskId;
				$startDate = getFieldFromId("start_time", "tasks", "task_id", $taskId);
				$repeatingTaskId = getFieldFromId("repeating_task_id", "tasks", "task_id", $taskId);
				executeQuery("update tasks set date_completed = now() where task_id = ?", $taskId);
				$returnArray['date_completed'] = date("m/d/Y");

# If this task is an instance of a repeating task and the repeating task's frequency is "AFTER", create a new task, if necessary

				if (!empty($repeatingTaskId)) {
					$repeatRules = parseNameValues(getFieldFromId("repeat_rules", "tasks", "task_id", $repeatingTaskId));
					if (empty($repeatRules['interval'])) {
						$repeatRules['interval'] = 1;
					}
					if ($repeatRules['frequency'] == "AFTER") {
						$resultSet = executeQuery("select * from tasks where repeating_task_id = ? and start_time > ?",
							$repeatingTaskId, $startDate);
						if (!$row = getNextRow($resultSet)) {
							$startDate = date('Y-m-d', strtotime(date("Y-m-d") . " + " . $repeatRules['interval'] . " " . $repeatRules['units']));
							$resultSet = executeQuery("select * from tasks where task_id = ?", $repeatingTaskId);
							if ($row = getNextRow($resultSet)) {
								$endDate = (empty($row['end_time']) ? "" : date("Y-m-d", strtotime($row['end_time'])));
								$dateCompleted = (empty($row['date_completed']) ? "" : date("Y-m-d", strtotime($row['date_completed'])));
								if ($dateCompleted < $endDate || empty($endDate)) {
									$endDate = $dateCompleted;
								}
								if (empty($endDate) || $startDate <= $endDate) {
									$instanceRow = $row;
									if (!empty($row['due_days'])) {
										$dateDue = date('Y-m-d', strtotime($startDate . " + " . $row['due_days'] . " days"));
										$instanceRow['date_due'] = $dateDue;
										$instanceRow['due_days'] = 0;
									}

									$insertSet = executeQuery("insert into tasks (client_id,description,detailed_description,prerequisites,task_group,project_id,project_milestone_id," .
										"creator_user_id,assigned_user_id,user_group_id,task_type_id,priority,start_time,end_time,date_due,due_days,date_completed," .
										"completing_user_id,all_day,public_access,percent_complete,estimated_hours) values (" .
										"?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $instanceRow['description'], $instanceRow['detailed_description'], $instanceRow['prerequisites'],
										$instanceRow['task_group'], $instanceRow['project_id'], $instanceRow['project_milestone_id'], $instanceRow['creator_user_id'], $instanceRow['assigned_user_id'], $instanceRow['user_group_id'],
										$instanceRow['task_type_id'], $instanceRow['priority'], $this->iDatabase->makeDatetimeParameter($startDate, $instanceRow['start_time']),
										$this->iDatabase->makeDatetimeParameter($instanceRow['end_time'], $instanceRow['end_time']), $this->iDatabase->makeDateParameter($instanceRow['date_due']), $instanceRow['due_days'],
										$this->iDatabase->makeDateParameter($instanceRow['date_completed']), $instanceRow['completing_user_id'], ($instanceRow['all_day'] == "1" ? 1 : 0),
										($instanceRow['public_access'] == "1" ? 1 : 0), $instanceRow['percent_complete'], $instanceRow['estimated_hours']);
									$newTaskId = $insertSet['insert_id'];

									if (!empty($instanceRow['assigned_user_id']) && $instanceRow['assigned_user_id'] != $GLOBALS['gUserId']) {
										$emailAddress = Contact::getUserContactField($instanceRow['assigned_user_id'],"email_address");
										if (!empty($emailAddress)) {
											$body = "A task described as '" . $instanceRow['description'] . "'" . (empty($instanceRow['project_id']) ? "" : " and part of the project '" . getFieldFromId("description", "projects", "project_id", $instanceRow['project_id']) . "'") .
												" has been assigned to you. You can view the task <a href='http://" . $_SERVER['HTTP_HOST'] . "/taskmanager.php?task_id=" . $newTaskId . "'>here</a>.";
											sendEmail(array("body" => $body, "subject" => "Task Assigned", "email_addresses" => $emailAddress));
										}
									}
									if (!empty($instanceRow['project_id'])) {
										$content = "";
										if (!empty($instanceRow['assigned_user_id'])) {
											$content = "is assigned to " . getUserDisplayName($instanceRow['assigned_user_id']);
										}
										if (!empty($instanceRow['date_due'])) {
											if (!empty($content)) {
												$content .= " and ";
											}
											$content .= "is due on " . date("m/d/Y", strtotime($instanceRow['date_due']));
										}
										if (!empty($instanceRow['date_completed'])) {
											if (!empty($content)) {
												$content .= " and ";
											}
											$content .= "was completed on " . date("m/d/Y", strtotime($instanceRow['date_completed']));
										}
										if (!empty($instanceRow['project_milestone_id'])) {
											if (!empty($content)) {
												$content .= " and ";
											}
											$content .= "is in milestone '" . getFieldFromId("description", "project_milestones", "project_milestone_id", $instanceRow['project_milestone_id']) . "'";
										}
										$content = "Created task '" . $_POST['description'] . "' and added it to the project." . (empty($content) ? "" : " It " . $content);
										$this->addProjectLog($instanceRow['project_id'], $content);
									}

# Create the new subtasks
									$subSet = executeQuery("select * from tasks where parent_task_id = ?", $row['task_id']);
									while ($subRow = getNextRow($subSet)) {
										$insertSet = executeQuery("insert into tasks (client_id,description,detailed_description,date_completed,parent_task_id) " .
											"values (?,?,?,?,?)", $GLOBALS['gClientId'], $subRow['description'], $subRow['detailed_description'],
											$subRow['date_completed'], $newTaskId);
									}

									$subSet = executeQuery("select * from task_attachments where task_id = ?", $row['task_id']);
									while ($subRow = getNextRow($subSet)) {
										$insertSet = executeQuery("insert into files (client_id,file_code,description,detailed_description,date_uploaded," .
											"filename,extension,file_content,os_filename,image_id,public_access,all_user_access,administrator_access," .
											"sort_order,internal_use_only,inactive) select client_id,file_code,description,detailed_description,date_uploaded," .
											"filename,extension,file_content,os_filename,image_id,public_access,all_user_access,administrator_access," .
											"sort_order,internal_use_only,inactive from files where file_id = ?", $subRow['file_id']);
										$newFileId = $insertSet['insert_id'];
										$insertSet = executeQuery("insert into task_attachments (task_id,description,file_id) values (?,?,?)", $newTaskId, $subRow['description'], $newFileId);
									}
									$subSet = executeQuery("insert ignore into task_category_links (task_id,task_category_id) select $newTaskId,task_category_id from task_category_links where task_id = ?", $row['task_id']);
									$subSet = executeQuery("select * from task_data where task_id = ?", $row['task_id']);
									while ($subRow = getNextRow($subSet)) {
										if (!empty($subRow['file_id'])) {
											$insertSet = executeQuery("insert into files (client_id,file_code,description,detailed_description,date_uploaded," .
												"filename,extension,file_content,os_filename,image_id,public_access,all_user_access,administrator_access," .
												"sort_order,internal_use_only,inactive) select client_id,file_code,description,detailed_description,date_uploaded," .
												"filename,extension,file_content,os_filename,image_id,public_access,all_user_access,administrator_access," .
												"sort_order,internal_use_only,inactive from files where file_id = ?", $subRow['file_id']);
											$newFileId = $insertSet['insert_id'];
										} else {
											$newFileId = "";
										}
										if (!empty($subRow['image_id'])) {
											$insertSet = executeQuery("insert into images (client_id,image_code,extension,filename,description,detailed_description,date_uploaded," .
												"file_content,image_size) select client_id,image_code,extension,filename,description,detailed_description,date_uploaded," .
												"file_content,image_size from images where image_id = ?", $subRow['image_id']);
											$newImageId = $insertSet['insert_id'];
										} else {
											$newImageId = "";
										}
										$insertSet = executeQuery("insert into task_data (task_id,task_data_definition_id,sequence_number," .
											"integer_data,number_data,text_data,date_data,image_id,file_id) values (?,?,?,?,?,?,?,?,?)",
											$newTaskId, $subRow['task_data_definition_id'], $subRow['sequence_number'], $subRow['integer_data'],
											$subRow['number_data'], $subRow['text_data'], $subRow['date_data'], $newImageId, $newFileId);
									}
									$subSet = executeQuery("select * from task_images where task_id = ?", $row['task_id']);
									while ($subRow = getNextRow($subSet)) {
										$insertSet = executeQuery("insert into images (client_id,image_code,extension,filename,description,detailed_description,date_uploaded," .
											"file_content,image_size) select client_id,image_code,extension,filename,description,detailed_description,date_uploaded," .
											"file_content,image_size from images where image_id = ?", $subRow['image_id']);
										$newImageId = $insertSet['insert_id'];
										$insertSet = executeQuery("insert into task_images (task_id,description,image_id) values (?,?,?)",
											$newTaskId, $subRow['description'], $newImageId);
									}
								}
							}
						}
					}
				}

# if this task is part of a work flow, check to see if another task should be created

				while (true) {
					$emailAddresses = array();
					$creatorUserId = getFieldFromId("creator_user_id", "tasks", "task_id", $taskId);
					if (!empty($creatorUserId) && $creatorUserId != $GLOBALS['gUserId']) {
						$thisEmailAddress = Contact::getUserContactField($creatorUserId,"email_address");
						if (!empty($thisEmailAddress) && !in_array($thisEmailAddress, $emailAddresses)) {
							$emailAddresses[] = $thisEmailAddress;
						}
					}
					$projectDescription = getFieldFromId("description", "projects", "project_id", getFieldFromId("project_id", "tasks", "task_id", $taskId));
					$body = "The task '" . getFieldFromId("description", "tasks", "task_id", $taskId) . "'" . (empty($projectDescription) ? "" : " from the project '" . $projectDescription . "'") . ", which you created has been completed.";
					if (!empty($emailAddresses)) {
						sendEmail(array("subject" => "Task Completed", "body" => $body, "email_addresses" => $emailAddresses));
					}

# see if this task is part of a work flow instance
					$resultSet = executeQuery("select * from work_flow_instance_details where task_id = ?", $taskId);
					if (!$instanceDetailRow = getNextRow($resultSet)) {
						break;
					}

# see if there are other tasks that are not yet created that might be dependent on this task being completed
					$resultSet = executeQuery("select * from work_flow_instance_details where task_id is null and email_sent is null and " .
						"work_flow_instance_id = ? order by sequence_number", $instanceDetailRow['work_flow_instance_id']);
					while ($row = getNextRow($resultSet)) {
						$startRules = parseNameValues($row['start_rules']);
						if (!empty($startRules['completed'])) {
							$detailCodes = explode(",", $startRules['completed']);
							$allCompleted = true;
							foreach ($detailCodes as $detailCode) {
								$taskId = getFieldFromId("task_id", "work_flow_instance_details", "work_flow_detail_code", $detailCode,
									"work_flow_instance_id = " . $row['work_flow_instance_id'] . " and task_id in (select task_id from tasks where " .
									"tasks.task_id = work_flow_instance_details.task_id and date_completed is not null)");
								$emailSent = getFieldFromId("email_sent", "work_flow_instance_details", "work_flow_detail_code", $detailCode,
									"work_flow_instance_id = " . $row['work_flow_instance_id']);
								if (empty($taskId) && empty($emailSent)) {
									$allCompleted = false;
									break;
								}
							}
							if ($allCompleted) {
								$workFlowInstanceSet = executeQuery("select * from work_flow_instances where work_flow_instance_id = ?", $instanceDetailRow['work_flow_instance_id']);
								$workFlowInstanceRow = getNextRow($workFlowInstanceSet);
								if (!empty($row['task_type_id'])) {
									$startDate = date('Y-m-d', strtotime(date("Y-m-d") . " + " . $startRules['after'] . " " . $startRules['units']));
									$dateDue = (empty($row['days_required']) ? "" : date('Y-m-d', strtotime("+" . $row['days_required'] . " days", strtotime($startDate))));
									$insertSet = executeQuery("insert into tasks (client_id,description,project_id,creator_user_id,assigned_user_id,user_group_id,task_type_id," .
										"start_time,date_due) values (?,?,?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $row['task_description'], $workFlowInstanceRow['project_id'], $workFlowInstanceRow['creator_user_id'],
										$row['user_id'], $row['user_group_id'], $row['task_type_id'], $this->iDatabase->makeDatetimeParameter($startDate), $dateDue);
									$newTaskId = $insertSet['insert_id'];
									$updateSet = executeQuery("update work_flow_instance_details set task_id = ? where work_flow_instance_detail_id = ? and task_id is null",
										$newTaskId, $row['work_flow_instance_detail_id']);
									if ($updateSet['affected_rows'] == 0) {
										executeQuery("delete from tasks where task_id = ?", $newTaskId);
									} else if (!empty($row['work_flow_status_id'])) {
										executeQuery("update work_flow_instances set work_flow_status_id = ? where work_flow_instance_id = ?", $row['work_flow_status_id'], $row['work_flow_instance_id']);
										if (!empty($row['user_id']) && $row['user_id'] != $GLOBALS['gUserId']) {
											$emailAddress = Contact::getUserContactField($row['user_id'],"email_address");
											if (!empty($emailAddress)) {
												$body = "A task described as '" . $row['task_description'] . "'" . (empty($workFlowInstanceRow['project_id']) ? "" : " and part of the project '" . getFieldFromId("description", "projects", "project_id", $workFlowInstanceRow['project_id']) . "'") .
													" has been assigned to you. You can view the task <a href='http://" . $_SERVER['HTTP_HOST'] . "/taskmanager.php?task_id=" . $newTaskId . "'>here</a>.";
												sendEmail(array("body" => $body, "subject" => "Task Assigned", "email_addresses" => $emailAddress));
											}
										}
									}
								} else if (!empty($row['email_id'])) {
									$emailAddress = (empty($row['email_address']) ? getFieldFromId("email_address", "contacts", "contact_id", $workFlowInstanceRow['contact_id']) : $row['email_address']);
									if (!empty($emailAddress)) {
										$updateSet = executeQuery("update work_flow_instance_details set email_sent = now() where work_flow_instance_detail_id = ? and email_sent is null",
											$row['work_flow_instance_detail_id']);
										if ($updateSet['affected_rows'] > 0) {
											sendEmail(array("email_id" => $row['email_id'], "email_addresses" => $emailAddress));
											if (!empty($row['work_flow_status_id'])) {
												executeQuery("update work_flow_instances set work_flow_status_id = ? where work_flow_instance_id = ?", $row['work_flow_status_id'], $row['work_flow_instance_id']);
											}
										}
									}
								}
							}
						}
					}
					$this->addProjectLog(getFieldFromId("project_id", "tasks", "task_id", $taskId), "Marked task '" . getFieldFromId("description", "tasks", "task_id", $taskId) . "' as completed.");
					break;
				}

				ajaxResponse($returnArray);

# Get a work flow instance record.

			case "get_work_flow_instance":

				$workFlowInstanceId = $_GET['work_flow_instance_id'];
				$resultSet = executeQuery("select work_flow_instance_id,(select description from work_flow_definitions where work_flow_definition_id = work_flow_instances.work_flow_definition_id) description," .
					"(select concat_ws(' ',first_name,last_name) from contacts where contact_id = (select contact_id from users where user_id = work_flow_instances.creator_user_id)) creator_user," .
					"responsible_user_id,start_date,date_due,date_completed,work_flow_status_id,notes," .
					"(select concat_ws(' ',first_name,last_name) from contacts where contact_id = (select contact_id from users where user_id = work_flow_instances.completing_user_id)) completing_user " .
					"from work_flow_instances where (creator_user_id = ? or responsible_user_id = ?) and work_flow_instance_id = ?", $GLOBALS['gUserId'], $GLOBALS['gUserId'], $workFlowInstanceId);
				if ($row = getNextRow($resultSet)) {
					$returnArray['data_values'] = array();
					$returnArray['data_values']['work_flow_instance_id'] = $row['work_flow_instance_id'];
					$returnArray['data_values']['work_flow_definition_id'] = $row['work_flow_definition_id'];
					$returnArray['data_values']['wfi_description'] = $row['description'];
					$returnArray['data_values']['wfi_creator_user'] = $row['creator_user'];
					$returnArray['data_values']['wfi_responsible_user_id'] = $row['responsible_user_id'];
					$returnArray['data_values']['wfi_work_flow_status_id'] = $row['work_flow_status_id'];
					$returnArray['data_values']['wfi_start_date'] = (empty($row['start_date']) ? "" : date("m/d/Y", strtotime($row['start_date'])));
					$returnArray['data_values']['wfi_date_due'] = (empty($row['date_due']) ? "" : date("m/d/Y", strtotime($row['date_due'])));
					$returnArray['data_values']['wfi_date_completed'] = (empty($row['date_completed']) ? "" : date("m/d/Y", strtotime($row['date_completed'])));
					$returnArray['data_values']['wfi_completing_user'] = $row['completing_user'];
					$returnArray['data_values']['wfi_notes'] = $row['notes'];
					$returnArray['instance_details'] = array();
					$resultSet = executeQuery("select * from work_flow_instance_details where work_flow_instance_id = ? order by sequence_number", $workFlowInstanceId);
					while ($row = getNextRow($resultSet)) {
						$description = "";
						$assignment = "";
						$progress = "";
						if (!empty($row['task_type_id'])) {
							$description = "Create '" . getFieldFromId("description", "task_types", "task_type_id", $row['task_type_id']) . "', " . $row['task_description'];
							if (!empty($row['user_id'])) {
								$assignment = getUserDisplayName($row['user_id']);
							}
							if (!empty($row['user_group_id'])) {
								if (!empty($assignment)) {
									$assignment .= " or ";
								}
								$assignment = getFieldFromId("description", "user_groups", "user_group_id", $row['user_group_id']);
							}
							if (empty($row['task_id'])) {
								$progress = "Not yet created";
							} else {
								$dateCompleted = getFieldFromId("date_completed", "tasks", "task_id", $row['task_id']);
								if (empty($dateCompleted)) {
									$progress = "In process";
								} else {
									$completingUser = getUserDisplayName(getFieldFromId("completing_user_id", "tasks", "task_id", $row['task_id']));
									$progress = "Completed" . (empty($completingUser) ? "" : " by " . $completingUser) . " on " . date("m/d/Y", strtotime($dateCompleted));
								}
							}
						} else if (!empty($row['email_id'])) {
							$description = "Send email '" . getFieldFromId("description", "emails", "email_id", $row['email_id']) . "'" . (empty($row['email_address']) ? "" : " to " . $row['email_address']);
							if (empty($row['email_sent'])) {
								$progress = "Not yet sent";
							} else {
								$progress = "Sent on " . date("m/d/Y g:ia", strtotime($row['email_sent']));
							}
						}
						$returnArray['instance_details'][] = array("description" => $description, "assignment" => $assignment, "progress" => $progress);
					}
				} else {
					$returnArray['error_message'] = getSystemMessage("instance_not_found");
					ajaxResponse($returnArray);
					break;
				}

				ajaxResponse($returnArray);

# Get a task record

			case "get_task":
				$myTask = ($_GET['my_task'] == "true");
				$valuesArray = Page::getPagePreferences();

				$taskId = $_GET['task_id'];
				$parameters = array($taskId, $GLOBALS['gUserId'], $GLOBALS['gUserId']);
				if (!$myTask) {
					$parameters[] = $GLOBALS['gUserId'];
					$parameters[] = $GLOBALS['gUserId'];
				}
				$resultSet = executeQuery("select * from tasks where parent_task_id is null and " .
					"task_id = ? and " . ($myTask ? "creator_user_id = ? and assigned_user_id is not null and assigned_user_id <> ?" : "(assigned_user_id = ? or " .
						"((assigned_user_id is null or date_completed is not null) and user_group_id in " .
						"(select user_group_id from user_group_members where user_id = ?)) or " .
						"(assigned_user_id is null and user_group_id is null and creator_user_id = ?) or " .
						"(task_type_id in (select task_type_id from task_types where responsible_user_id = ?)))") . " and (task_type_id not in " .
					"(select task_type_id from task_type_attributes where task_attribute_id = (select task_attribute_id from task_attributes " .
					"where task_attribute_code = 'APPOINTMENT'))) and " .
					"(start_time is null or start_time <= now())", $parameters);
				if ($row = getNextRow($resultSet)) {
					$taskTypeId = $row['task_type_id'];
				}
				if (empty($taskTypeId)) {
					$returnArray['error_message'] = getSystemMessage("task_not_found");
					ajaxResponse($returnArray);
					break;
				}
				ob_start();
				$otherData = $this->getTaskTypeForm($taskTypeId, $taskId, $myTask);
				$returnArray['task_dialog'] = ob_get_clean();
				$returnArray['template'] = $otherData['template'];
				$returnArray['data_values'] = $otherData['data_values'];

				if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'USES_CATEGORIES'))) {
					$thisColumn = $this->getDataSource()->getColumns("task_categories");
					if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
						$controlClass = $thisColumn->getControlValue("control_class");
						$customControl = new $controlClass($thisColumn, $this);
						foreach ($customControl->getRecord($taskId) as $keyValue => $dataValue) {
							$returnArray['data_values'][$keyValue] = $dataValue;
						}
					}
				}

				if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'ALLOW_IMAGES'))) {
					$thisColumn = $this->getDataSource()->getColumns("task_images");
					if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
						$controlClass = $thisColumn->getControlValue("control_class");
						$customControl = new $controlClass($thisColumn, $this);
						foreach ($customControl->getRecord($taskId) as $keyValue => $dataValue) {
							$returnArray['data_values'][$keyValue] = $dataValue;
						}
					}
				}

				if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'ALLOW_FILE'))) {
					$thisColumn = $this->getDataSource()->getColumns("task_attachments");
					if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
						$controlClass = $thisColumn->getControlValue("control_class");
						$customControl = new $controlClass($thisColumn, $this);
						foreach ($customControl->getRecord($taskId) as $keyValue => $dataValue) {
							$returnArray['data_values'][$keyValue] = $dataValue;
						}
					}
				}

				if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'HAS_SUBTASKS'))) {
					$thisColumn = $this->getDataSource()->getColumns("subtasks");
					if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
						$controlClass = $thisColumn->getControlValue("control_class");
						$customControl = new $controlClass($thisColumn, $this);
						foreach ($customControl->getRecord($taskId) as $keyValue => $dataValue) {
							$returnArray['data_values'][$keyValue] = $dataValue;
						}
					}
				}

				if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'LOG_TIME'))) {
					$thisColumn = $this->getDataSource()->getColumns("task_time_log");
					if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
						$controlClass = $thisColumn->getControlValue("control_class");
						$customControl = new $controlClass($thisColumn, $this);
						foreach ($customControl->getRecord($taskId) as $keyValue => $dataValue) {
							$returnArray['data_values'][$keyValue] = $dataValue;
						}
					}
				}

				if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'TASK_LOG'))) {
					$thisColumn = $this->getDataSource()->getColumns("task_log");
					if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
						$controlClass = $thisColumn->getControlValue("control_class");
						$customControl = new $controlClass($thisColumn, $this);
						foreach ($customControl->getRecord($taskId) as $keyValue => $dataValue) {
							$returnArray['data_values'][$keyValue] = $dataValue;
						}
					}
				}

				ajaxResponse($returnArray);

# Create the form so the user can enter a new task.

			case "create_new_task":
				$resultSet = executeQuery("select task_type_id from task_types where client_id = ? and inactive = 0 and (task_type_id not in " .
					"(select task_type_id from task_type_attributes where task_attribute_id = (select task_attribute_id from task_attributes " .
					"where task_attribute_code = 'APPOINTMENT'))) and task_type_id = ?", $GLOBALS['gClientId'], $_GET['task_type_id']);
				$taskTypeId = "";
				if ($row = getNextRow($resultSet)) {
					$taskTypeId = $row['task_type_id'];
				}
				if (empty($taskTypeId)) {
					$returnArray['error_message'] = getSystemMessage("invalid_task_type");
					ajaxResponse($returnArray);
					break;
				}
				ob_start();
				$otherData = $this->getTaskTypeForm($taskTypeId);
				$returnArray['task_dialog'] = ob_get_clean();
				$returnArray['template'] = $otherData['template'];
				$returnArray['data_values'] = $otherData['data_values'];
				ajaxResponse($returnArray);

# Get list of tasks for the user

			case "get_task_list":

# First, check to see if any instances of repeating tasks need to be created and create them

				$todayDate = date("Y-m-d");
				$resultSet = executeQuery("select * from tasks where repeat_rules is not null and repeating_task_id is null and " .
					"parent_task_id is null and date_completed is null and (task_type_id not in " .
					"(select task_type_id from task_type_attributes where task_attribute_id = (select task_attribute_id from task_attributes where task_attribute_code = 'APPOINTMENT'))) and " .
					"(start_time is null or start_time <= now()) and (end_time is null or end_time >= now())");
				while ($row = getNextRow($resultSet)) {
					$repeatRules = parseNameValues($row['repeat_rules']);
					$instanceCount = 0;
					$countSet = executeQuery("select count(*) from tasks where repeating_task_id = ?", $row['task_id']);
					if ($countRow = getNextRow($countSet)) {
						$instanceCount = $countRow['count(*)'];
					}
					if ($repeatRules['frequency'] != "AFTER" || $instanceCount == 0) {
						if ($repeatRules['frequency'] == "AFTER" || isInSchedule($todayDate, $row['repeat_rules'])) {
							$instanceSet = executeQuery("select * from tasks where repeating_task_id = ? and start_time = ?", $row['task_id'], $todayDate . " 00:00:00");
							if (!$instanceRow = getNextRow($instanceSet)) {
								$instanceRow = $row;
								if (!empty($row['due_days'])) {
									$dateDue = date('Y-m-d', strtotime($todayDate . " + " . $row['due_days'] . " days"));
									$instanceRow['date_due'] = $dateDue;
									$instanceRow['due_days'] = 0;
								}

								$startDate = ($repeatRules['frequency'] == "AFTER" ? $row['start_time'] : $todayDate);
								$insertSet = executeQuery("insert into tasks (client_id,description,detailed_description,prerequisites,task_group,project_id,project_milestone_id," .
									"creator_user_id,assigned_user_id,user_group_id,task_type_id,priority,start_time,end_time,date_due,due_days,date_completed," .
									"completing_user_id,all_day,public_access,percent_complete,estimated_hours,repeating_task_id) values (" .
									"?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", $GLOBALS['gClientId'], $instanceRow['description'], $instanceRow['detailed_description'], $instanceRow['prerequisites'],
									$instanceRow['task_group'], $instanceRow['project_id'], $instanceRow['project_milestone_id'], $instanceRow['creator_user_id'], $instanceRow['assigned_user_id'], $instanceRow['user_group_id'],
									$instanceRow['task_type_id'], $instanceRow['priority'], $this->iDatabase->makeDatetimeParameter($startDate, $instanceRow['start_time']),
									$this->iDatabase->makeDatetimeParameter($instanceRow['end_time'], $instanceRow['end_time']), $this->iDatabase->makeDateParameter($instanceRow['date_due']), $instanceRow['due_days'],
									$this->iDatabase->makeDateParameter($instanceRow['date_completed']), $instanceRow['completing_user_id'], ($instanceRow['all_day'] == "1" ? 1 : 0),
									($instanceRow['public_access'] == "1" ? 1 : 0), $instanceRow['percent_complete'], $instanceRow['estimated_hours'], $instanceRow['task_id']);
								$newTaskId = $insertSet['insert_id'];

								if (!empty($instanceRow['assigned_user_id']) && $instanceRow['assigned_user_id'] != $GLOBALS['gUserId']) {
									$emailAddress = Contact::getUserContactField($instanceRow['assigned_user_id'],"email_address");
									if (!empty($emailAddress)) {
										$body = "A task described as '" . $instanceRow['description'] . "'" . (empty($instanceRow['project_id']) ? "" : " and part of the project '" . getFieldFromId("description", "projects", "project_id", $instanceRow['project_id']) . "'") .
											" has been assigned to you. You can view the task <a href='http://" . $_SERVER['HTTP_HOST'] . "/taskmanager.php?task_id=" . $newTaskId . "'>here</a>.";
										sendEmail(array("body" => $body, "subject" => "Task Assigned", "email_addresses" => $emailAddress));
									}
								}
								if (!empty($instanceRow['project_id'])) {
									$content = "";
									if (!empty($instanceRow['assigned_user_id'])) {
										$content = "is assigned to " . getUserDisplayName($instanceRow['assigned_user_id']);
									}
									if (!empty($instanceRow['date_due'])) {
										if (!empty($content)) {
											$content .= " and ";
										}
										$content .= "is due on " . date("m/d/Y", strtotime($instanceRow['date_due']));
									}
									if (!empty($instanceRow['date_completed'])) {
										if (!empty($content)) {
											$content .= " and ";
										}
										$content .= "was completed on " . date("m/d/Y", strtotime($instanceRow['date_completed']));
									}
									if (!empty($instanceRow['project_milestone_id'])) {
										if (!empty($content)) {
											$content .= " and ";
										}
										$content .= "is in milestone '" . getFieldFromId("description", "project_milestones", "project_milestone_id", $instanceRow['project_milestone_id']) . "'";
									}
									$content = "Created task '" . $_POST['description'] . "' and added it to the project." . (empty($content) ? "" : " It " . $content);
									$this->addProjectLog($instanceRow['project_id'], $content);
								}

# Create the new subtasks
								$subSet = executeQuery("select * from tasks where parent_task_id = ?", $row['task_id']);
								while ($subRow = getNextRow($subSet)) {
									$insertSet = executeQuery("insert into tasks (client_id,description,detailed_description,date_completed,parent_task_id) " .
										"values (?,?,?,?,?)", $GLOBALS['gClientId'], $subRow['description'], $subRow['detailed_description'],
										$subRow['date_completed'], $newTaskId);
								}

								$subSet = executeQuery("select * from task_attachments where task_id = ?", $row['task_id']);
								while ($subRow = getNextRow($subSet)) {
									$insertSet = executeQuery("insert into files (client_id,file_code,description,detailed_description,date_uploaded," .
										"filename,extension,file_content,os_filename,image_id,public_access,all_user_access,administrator_access," .
										"sort_order,internal_use_only,inactive) select client_id,file_code,description,detailed_description,date_uploaded," .
										"filename,extension,file_content,os_filename,image_id,public_access,all_user_access,administrator_access," .
										"sort_order,internal_use_only,inactive from files where file_id = ?", $subRow['file_id']);
									$newFileId = $insertSet['insert_id'];
									$insertSet = executeQuery("insert into task_attachments (task_id,description,file_id) values (?,?,?)", $newTaskId, $subRow['description'], $newFileId);
								}
								$subSet = executeQuery("insert ignore into task_category_links (task_id,task_category_id) select $newTaskId,task_category_id from task_category_links where task_id = ?", $row['task_id']);
								$subSet = executeQuery("select * from task_data where task_id = ?", $row['task_id']);
								while ($subRow = getNextRow($subSet)) {
									if (!empty($subRow['file_id'])) {
										$insertSet = executeQuery("insert into files (client_id,file_code,description,detailed_description,date_uploaded," .
											"filename,extension,file_content,os_filename,image_id,public_access,all_user_access,administrator_access," .
											"sort_order,internal_use_only,inactive) select client_id,file_code,description,detailed_description,date_uploaded," .
											"filename,extension,file_content,os_filename,image_id,public_access,all_user_access,administrator_access," .
											"sort_order,internal_use_only,inactive from files where file_id = ?", $subRow['file_id']);
										$newFileId = $insertSet['insert_id'];
									} else {
										$newFileId = "";
									}
									if (!empty($subRow['image_id'])) {
										$insertSet = executeQuery("insert into images (client_id,image_code,extension,filename,description,detailed_description,date_uploaded," .
											"file_content,image_size) select client_id,image_code,extension,filename,description,detailed_description,date_uploaded," .
											"file_content,image_size from images where image_id = ?", $subRow['image_id']);
										$newImageId = $insertSet['insert_id'];
									} else {
										$newImageId = "";
									}
									$insertSet = executeQuery("insert into task_data (task_id,task_data_definition_id,sequence_number," .
										"integer_data,number_data,text_data,date_data,image_id,file_id) values (?,?,?,?,?,?,?,?,?)",
										$newTaskId, $subRow['task_data_definition_id'], $subRow['sequence_number'], $subRow['integer_data'],
										$subRow['number_data'], $subRow['text_data'], $subRow['date_data'], $newImageId, $newFileId);
								}
								$subSet = executeQuery("select * from task_images where task_id = ?", $row['task_id']);
								while ($subRow = getNextRow($subSet)) {
									$insertSet = executeQuery("insert into images (client_id,image_code,extension,filename,description,detailed_description,date_uploaded," .
										"file_content,image_size) select client_id,image_code,extension,filename,description,detailed_description,date_uploaded," .
										"file_content,image_size from images where image_id = ?", $subRow['image_id']);
									$newImageId = $insertSet['insert_id'];
									$insertSet = executeQuery("insert into task_images (task_id,description,image_id) values (?,?,?)",
										$newTaskId, $subRow['description'], $newImageId);
								}
							}
						}
					}
				}

				$columnArray = array("tasks.priority", "tasks.detailed_description", "task_type", "tasks.description", "tasks.date_due", "assigned_user", "project", "milestone", "task_category_list", "tasks.task_group");

				$valuesArray = Page::getPagePreferences();

				$filterPreference = $_POST['filter_tab_name'] . ":" . getCrcValue($_POST['filter_tab_name'], true);
				$filterOn = (!empty($_POST['filter_on']));
				$filterMatch = $_POST['filter_match'];
				if (!in_array($filterMatch, array("any", "all", "none"))) {
					$filterMatch = "any";
				}
				$filterPreference .= ":" . $filterMatch;
				$filterPreference .= ":" . $_POST['filter_show_completed'];
				$filterPreference .= ":" . $_POST['filter_show_responsible'];
				$valuesArray['filter_match'] = $filterMatch;
				$valuesArray['filter_on'] = ($filterOn ? "Y" : "");
				$filterParameters = array();
				$validFilters = array("task_type" => "tasks.task_type_id = ?", "project" => "tasks.project_id = ?", "project_milestone" => "tasks.project_milestone_id = ?", "task_category" => "tasks.task_id in (select task_id from task_category_links where task_category_id = ?)", "task_group" => "tasks.task_group = ?");
				$filterWhere = "";
				$filterQueryParameters = array();
				for ($x = 1; $x <= 5; $x++) {
					$filterParameters[$x] = array();
					$filterParameters[$x]['filter_field'] = $_POST['filter_field_' . $x];
					if (!array_key_exists($filterParameters[$x]['filter_field'], $validFilters)) {
						$filterParameters[$x]['filter_field'] = "";
					}
					$filterParameters[$x]['filter_value'] = "";
					if (!empty($filterParameters[$x]['filter_field'])) {
						$filterParameters[$x]['filter_value'] = $_POST['filter_' . $filterParameters[$x]['filter_field'] . "_" . $x];
					}
					foreach ($validFilters as $filterName => $selectValue) {
						$valuesArray['filter_' . $filterName . '_' . $x] = "";
					}
					$valuesArray['filter_field_' . $x] = $filterParameters[$x]['filter_field'];

					if (!empty($filterParameters[$x]['filter_field'])) {
						$filterPreference .= ":" . $filterParameters[$x]['filter_field'] . "=" . $filterParameters[$x]['filter_value'];
						$valuesArray['filter_' . $filterParameters[$x]['filter_field'] . '_' . $x] = $filterParameters[$x]['filter_value'];
					}
					if ($filterOn && !empty($filterParameters[$x]['filter_field'])) {
						if (!empty($filterWhere)) {
							$filterWhere .= ($filterMatch == "all" ? " and " : " or ");
						}
						$filterWhere .= $validFilters[$filterParameters[$x]['filter_field']];
						$filterQueryParameters[] = $filterParameters[$x]['filter_value'];
					}
				}
				if (!empty($filterWhere)) {
					$filterWhere = "(" . $filterWhere . ")";
					if ($filterMatch == "none") {
						$filterWhere = "not " . $filterWhere;
					}
				}
				$showCompleted = ($filterOn && !empty($_POST['filter_show_completed']));
				$showResponsible = ($filterOn && !empty($_POST['filter_show_responsible']));
				$valuesArray['filter_show_completed'] = (!empty($_POST['filter_show_completed']) ? "Y" : "");
				$valuesArray['filter_show_responsible'] = (!empty($_POST['filter_show_responsible']) ? "Y" : "");
				if (!empty($_POST['filter_tab_name'])) {
					$valuesArray['filter_tab-' . getCrcValue($_POST['filter_tab_name'], true)] = $filterPreference;
				}
				if ($showCompleted) {
					$columnArray[] = "tasks.date_completed";
				}
				$returnArray['show_completed'] = $showCompleted;
				Page::setPagePreferences($valuesArray);

				if (!empty($_GET['tab_key']) && array_key_exists("filter_tab-" . $_GET['tab_key'], $valuesArray)) {
					$parts = explode(":", $valuesArray['filter_tab-' . $_GET['tab_key']]);
					$filterMatch = $parts[2];
					if (!in_array($filterMatch, array("any", "all", "none"))) {
						$filterMatch = "any";
					}
					$showCompleted = (!empty($parts[3]));
					$showResponsible = (!empty($parts[4]));
					$filterWhere = "";
					$filterQueryParameters = array();
					for ($x = 1; $x <= 5; $x++) {
						$thisPart = $parts[$x + 4];
						$thisParts = explode("=", $thisPart);
						$filterParameters[$x] = array();
						$filterParameters[$x]['filter_field'] = $thisParts[0];
						if (!array_key_exists($filterParameters[$x]['filter_field'], $validFilters)) {
							$filterParameters[$x]['filter_field'] = "";
						}
						$filterParameters[$x]['filter_value'] = "";
						if (!empty($filterParameters[$x]['filter_field'])) {
							$filterParameters[$x]['filter_value'] = $thisParts[1];
						}
						if (!empty($filterParameters[$x]['filter_field'])) {
							if (!empty($filterWhere)) {
								$filterWhere .= ($filterMatch == "all" ? " and " : " or ");
							}
							$filterWhere .= $validFilters[$filterParameters[$x]['filter_field']];
							$filterQueryParameters[] = $filterParameters[$x]['filter_value'];
						}
					}
					if (!empty($filterWhere)) {
						$filterWhere = "(" . $filterWhere . ")";
						if ($filterMatch == "none") {
							$filterWhere = "not " . $filterWhere;
						}
					}
				}
				$searchColumn = $_POST['_task_filter_column'];
				$searchValue = $_POST['_task_filter_text'];
				if (!empty($searchColumn) && !in_array($searchColumn, $columnArray)) {
					$searchColumn = "";
					$searchValue = "";
				}
				setUserPreference("MAINTENANCE_FILTER_COLUMN", $searchColumn, $GLOBALS['gPageCode']);
				setUserPreference("MAINTENANCE_FILTER_TEXT", $searchValue, $GLOBALS['gPageCode']);

				$sortOrderColumn = $_POST['_task_sort_order_column'];
				$reverseSortOrder = ($_POST['_task_reverse_sort_order'] == "true");
				if (empty($sortOrderColumn) || !in_array($sortOrderColumn, $columnArray)) {
					$sortOrderColumn = "tasks.priority";
					$reverseSortOrder = false;
				}
				setUserPreference("MAINTENANCE_SORT_ORDER_COLUMN", $sortOrderColumn, $GLOBALS['gPageCode']);
				setUserPreference("MAINTENANCE_REVERSE_SORT_ORDER", ($reverseSortOrder ? "true" : "false"), $GLOBALS['gPageCode']);
				$orderBy = " order by ISNULL(" . $sortOrderColumn . ")," . $sortOrderColumn . ($reverseSortOrder ? " desc" : "");

				$taskQueue = array();
				$queryParameters = array_merge(array($GLOBALS['gUserId'], $GLOBALS['gUserId'], $GLOBALS['gUserId']), $filterQueryParameters);
				$resultSet = executeQuery("select tasks.contact_id,tasks.task_id,tasks.detailed_description,tasks.priority,tasks.description,tasks.date_due,tasks.date_completed,assigned_user_id,user_group_id," .
					"(select description from projects where project_id = tasks.project_id) project," .
					"(select description from project_milestones where project_milestone_id = tasks.project_milestone_id) milestone," .
					"(select display_color from projects where project_id = tasks.project_id) project_color," .
					"(select display_color from task_types where task_type_id = tasks.task_type_id) task_type_color," .
					"(select description from task_types where task_type_id = tasks.task_type_id) task_type," .
					"(select concat_ws(' ',last_name,first_name) from contacts where contact_id = (select contact_id from users where user_id = tasks.assigned_user_id)) assigned_user," .
					"(select group_concat(description) from task_categories where task_categories.task_category_id in (select task_category_id from task_category_links where task_category_links.task_id = tasks.task_id)) task_category_list," .
					"tasks.task_group,tasks.repeating_task_id from tasks where parent_task_id is null and " . ($showCompleted ? "" : "date_completed is null and ") .
					"(assigned_user_id = ? or ((assigned_user_id is null or date_completed is not null) and user_group_id in " .
					"(select user_group_id from user_group_members where user_id = ?)) or " .
					"(assigned_user_id is null and user_group_id is null and creator_user_id = ?)" .
					($showResponsible ? " or (task_type_id in (select task_type_id from task_types where responsible_user_id = " . $GLOBALS['gUserId'] .
						")) or task_id in (select task_id from work_flow_instance_details where work_flow_instance_id in (select work_flow_instance_id from " .
						"work_flow_instances where responsible_user_id = " . $GLOBALS['gUserId'] . ")) or project_id in (select project_id from projects " .
						"where leader_user_id = " . $GLOBALS['gUserId'] . ")" : "") . ") and (task_type_id not in " .
					"(select task_type_id from task_type_attributes where task_attribute_id = (select task_attribute_id from task_attributes where task_attribute_code = 'APPOINTMENT'))) and " .
					"(start_time is null or start_time <= now()) and " . ($showCompleted ? "" : "(end_time is null or end_time >= now()) and ") .
					"repeat_rules is null" . (empty($filterWhere) ? "" : " and " . $filterWhere) . $orderBy, $queryParameters);
				$useProject = false;
				$useMilestone = false;
				$useTaskCategoryList = false;
				$useTaskGroup = false;
				while ($row = getNextRow($resultSet)) {
					if (!empty($searchValue)) {
						$foundText = false;
						if (!$foundText && ($searchColumn == "tasks.description" || empty($searchColumn))) {
							if (stripos($row['description'], $searchValue) !== false) {
								$foundText = true;
							}
						}
						if (!$foundText && ($searchColumn == "project" || empty($searchColumn))) {
							if (stripos($row['project'], $searchValue) !== false) {
								$foundText = true;
							}
						}
						if (!$foundText && ($searchColumn == "milestone" || empty($searchColumn))) {
							if (stripos($row['milestone'], $searchValue) !== false) {
								$foundText = true;
							}
						}
						if (!$foundText && ($searchColumn == "task_category_list" || empty($searchColumn))) {
							if (stripos($row['task_category_list'], $searchValue) !== false) {
								$foundText = true;
							}
						}
						if (!$foundText && ($searchColumn == "tasks.task_group" || empty($searchColumn))) {
							if (stripos($row['task_group'], $searchValue) !== false) {
								$foundText = true;
							}
						}
						if (!$foundText && ($searchColumn == "task_type" || empty($searchColumn))) {
							if (stripos($row['task_type'], $searchValue) !== false) {
								$foundText = true;
							}
						}
						if (!$foundText && ($searchColumn == "tasks.detailed_description" || empty($searchColumn))) {
							if (stripos($row['detailed_description'], $searchValue) !== false) {
								$foundText = true;
							}
						}
						if (!$foundText) {
							continue;
						}
					}
					$row['date_completed'] = (empty($row['date_completed']) ? "" : date("m/d/Y", strtotime($row['date_completed'])));
					$row['date_due'] = (empty($row['date_due']) ? "" : date("m/d/Y", strtotime($row['date_due'])));
					$row['priority'] = showSignificant($row['priority']);
					$row['description'] = getFirstPart($row['description'], 60);
					$row['milestone'] = getFirstPart($row['milestone'], 20);
					$row['assigned_user'] = (empty($row['assigned_user_id']) ? "" : getUserDisplayName($row['assigned_user_id']));
					if (empty($row['assigned_user']) && !empty($row['user_group_id'])) {
						$row['assigned_user'] = getFieldFromId("description", "user_groups", "user_group_id", $row['user_group_id']);
					}
					foreach ($row as $fieldName => $fieldData) {
						if (is_null($fieldData)) {
							$row[$fieldName] = "";
						}
					}
					$displayColor = (empty($row['project_color']) ? $row['task_type_color'] : $row['project_color']);
					if (!empty($displayColor)) {
						if ($displayColor[0] == '#') {
							$displayColor = substr($displayColor, 1);
						}
						if (strlen($displayColor) == 6) {
							list($r, $g, $b) = array($displayColor[0] . $displayColor[1], $displayColor[2] . $displayColor[3], $displayColor[4] . $displayColor[5]);
						} else if (strlen($displayColor) == 3) {
							list($r, $g, $b) = array($displayColor[0] . $displayColor[0], $displayColor[1] . $displayColor[1], $displayColor[2] . $displayColor[2]);
						} else {
							list($r, $g, $b) = array(0, 0, 0);
						}
						if (!empty($r) || !empty($g) || !empty($b)) {
							$r = hexdec($r);
							$g = hexdec($g);
							$b = hexdec($b);
							if ($r + $g + $b < 575) {
								$row['cell_style'] = "font-weight: bold; color: rgb(" . $r . "," . $g . "," . $b . ")";
							} else {
								$row['row_style'] = "background-color: rgb(" . $r . "," . $g . "," . $b . ")";
							}
						}
					}
					if (!empty($row['project'])) {
						$useProject = true;
					}
					if (!empty($row['contact_id'])) {
						$row['full_name'] = getDisplayName($row['contact_id']);
						$useContact = true;
					} else {
						$row['full_name'] = "";
					}
					if (!empty($row['milestone'])) {
						$useMilestone = true;
					}
					if (!empty($row['task_category_list'])) {
						$useTaskCategoryList = true;
					}
					if (!empty($row['task_group'])) {
						$useTaskGroup = true;
					}
					$taskQueue[] = $row;
				}
				$returnArray['use_project'] = false;
				$returnArray['use_milestone'] = false;
				$returnArray['use_task_category_list'] = false;
				$returnArray['use_task_group'] = false;
				$returnArray['column_headers'] = array();
				$returnArray['column_headers'][] = array("column_name" => "tasks.date_completed", "description" => ($showCompleted ? "Done" . ($sortOrderColumn == "tasks.date_completed" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : "") : "<span class='fa fa-check'></span>"));
				$returnArray['column_headers'][] = array("column_name" => "tasks.priority", "description" => "Pri" . ($sortOrderColumn == "tasks.priority" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				$returnArray['column_headers'][] = array("column_name" => "tasks.description", "description" => "Description" . ($sortOrderColumn == "tasks.description" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				if ($useContact) {
					$returnArray['column_headers'][] = array("column_name" => "project", "description" => "Contact" . ($sortOrderColumn == "contact" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
					$returnArray['use_contact'] = true;
				}
				$returnArray['column_headers'][] = array("column_name" => "tasks.date_due", "description" => "Date Due" . ($sortOrderColumn == "tasks.date_due" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				$returnArray['column_headers'][] = array("column_name" => "assigned_user", "description" => "Assigned To" . ($sortOrderColumn == "assigned_user" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				$returnArray['column_headers'][] = array("column_name" => "task_type", "description" => "Task Type" . ($sortOrderColumn == "task_type" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				if ($useProject) {
					$returnArray['column_headers'][] = array("column_name" => "project", "description" => "Project" . ($sortOrderColumn == "project" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
					$returnArray['use_project'] = true;
				}
				if ($useMilestone) {
					$returnArray['column_headers'][] = array("column_name" => "milestone", "description" => "Milestone" . ($sortOrderColumn == "milestone" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
					$returnArray['use_milestone'] = true;
				}
				if ($useTaskCategoryList) {
					$returnArray['column_headers'][] = array("column_name" => "task_category_list", "description" => "Categories" . ($sortOrderColumn == "task_category_list" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
					$returnArray['use_task_category_list'] = true;
				}
				if ($useTaskGroup) {
					$returnArray['column_headers'][] = array("column_name" => "tasks.task_group", "description" => "Group" . ($sortOrderColumn == "tasks.task_group" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
					$returnArray['use_task_group'] = true;
				}
				$returnArray['task_list'] = $taskQueue;
				ajaxResponse($returnArray);

# Get list of tasks created by the user, but assigned to someone else.

			case "get_my_task_list":

				$columnArray = array("tasks.priority", "tasks.description", "tasks.date_due", "assigned_user", "task_type");

				$valuesArray = Page::getPagePreferences();

				$valuesArray['my_show_completed'] = (empty($_POST['my_show_completed']) ? "false" : "true");
				$showCompleted = ($valuesArray['my_show_completed'] == "true");
				if ($showCompleted) {
					$columnArray[] = "tasks.date_completed";
				}
				$returnArray['show_completed'] = $showCompleted;

				$sortOrderColumn = $_POST['_my_task_sort_order_column'];
				$reverseSortOrder = ($_POST['_my_task_reverse_sort_order'] == "true");
				if (empty($sortOrderColumn) || !in_array($sortOrderColumn, $columnArray)) {
					$sortOrderColumn = "tasks.priority";
					$reverseSortOrder = false;
				}
				$valuesArray['my_sort_order_column'] = $sortOrderColumn;
				$valuesArray['my_reverse_sort_order'] = ($reverseSortOrder ? "true" : "false");
				Page::setPagePreferences($valuesArray);

				$orderBy = " order by ISNULL(" . $sortOrderColumn . ")," . $sortOrderColumn . ($reverseSortOrder ? " desc" : "");

				$taskQueue = array();
				$resultSet = executeQuery("select tasks.task_id,tasks.priority,tasks.description,tasks.date_due,tasks.date_completed," .
					"(select description from projects where project_id = tasks.project_id) project," .
					"(select description from project_milestones where project_milestone_id = tasks.project_milestone_id) milestone," .
					"(select display_color from projects where project_id = tasks.project_id) project_color," .
					"(select display_color from task_types where task_type_id = tasks.task_type_id) task_type_color," .
					"(select description from task_types where task_type_id = tasks.task_type_id) task_type," .
					"(select concat_ws(' ',first_name,last_name) from contacts where contact_id = " .
					"(select contact_id from users where user_id = tasks.assigned_user_id)) assigned_user from tasks where " .
					"parent_task_id is null and " . ($showCompleted ? "" : "date_completed is null and ") .
					"assigned_user_id <> ? and assigned_user_id is not null and creator_user_id = ? and (task_type_id not in " .
					"(select task_type_id from task_type_attributes where task_attribute_id = (select task_attribute_id from task_attributes where task_attribute_code = 'APPOINTMENT'))) and " .
					"(start_time is null or start_time <= now()) and " . ($showCompleted ? "" : "(end_time is null or end_time >= now()) and ") .
					"repeat_rules is null" . $orderBy, $GLOBALS['gUserId'], $GLOBALS['gUserId']);
				$useProject = false;
				$useMilestone = false;
				while ($row = getNextRow($resultSet)) {
					$row['date_completed'] = (empty($row['date_completed']) ? "" : date("m/d/Y", strtotime($row['date_completed'])));
					$row['date_due'] = (empty($row['date_due']) ? "" : date("m/d/Y", strtotime($row['date_due'])));
					$row['priority'] = showSignificant($row['priority']);
					$row['description'] = getFirstPart($row['description'], 60);
					$row['milestone'] = getFirstPart($row['milestone'], 20);
					foreach ($row as $fieldName => $fieldData) {
						if (is_null($fieldData)) {
							$row[$fieldName] = "";
						}
					}
					$displayColor = (empty($row['project_color']) ? $row['task_type_color'] : $row['project_color']);
					if (!empty($displayColor)) {
						if ($displayColor[0] == '#') {
							$displayColor = substr($displayColor, 1);
						}
						if (strlen($displayColor) == 6) {
							list($r, $g, $b) = array($displayColor[0] . $displayColor[1], $displayColor[2] . $displayColor[3], $displayColor[4] . $displayColor[5]);
						} else if (strlen($displayColor) == 3) {
							list($r, $g, $b) = array($displayColor[0] . $displayColor[0], $displayColor[1] . $displayColor[1], $displayColor[2] . $displayColor[2]);
						} else {
							list($r, $g, $b) = array(0, 0, 0);
						}
						if (!empty($r) || !empty($g) || !empty($b)) {
							$r = hexdec($r);
							$g = hexdec($g);
							$b = hexdec($b);
							if ($r + $g + $b < 575) {
								$row['cell_style'] = "font-weight: bold; color: rgb(" . $r . "," . $g . "," . $b . ")";
							} else {
								$row['row_style'] = "background-color: rgb(" . $r . "," . $g . "," . $b . ")";
							}
						}
					}
					if (!empty($row['project'])) {
						$useProject = true;
					}
					if (!empty($row['milestone'])) {
						$useMilestone = true;
					}
					$taskQueue[] = $row;
				}
				$returnArray['column_headers'] = array();
				if ($showCompleted) {
					$returnArray['column_headers'][] = array("column_name" => "tasks.date_completed", "description" => "Done" . ($sortOrderColumn == "tasks.date_completed" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				}
				$returnArray['column_headers'][] = array("column_name" => "tasks.priority", "description" => "Pri" . ($sortOrderColumn == "tasks.priority" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				$returnArray['column_headers'][] = array("column_name" => "tasks.description", "description" => "Description" . ($sortOrderColumn == "tasks.description" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				$returnArray['column_headers'][] = array("column_name" => "tasks.date_due", "description" => "Date Due" . ($sortOrderColumn == "tasks.date_due" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				$returnArray['column_headers'][] = array("column_name" => "assigned_user", "description" => "Assigned To" . ($sortOrderColumn == "assigned_user" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				$returnArray['column_headers'][] = array("column_name" => "task_type", "description" => "Task Type" . ($sortOrderColumn == "task_type" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
				if ($useProject) {
					$returnArray['column_headers'][] = array("column_name" => "project", "description" => "Project" . ($sortOrderColumn == "project" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
					$returnArray['use_project'] = true;
				}
				if ($useMilestone) {
					$returnArray['column_headers'][] = array("column_name" => "milestone", "description" => "Milestone" . ($sortOrderColumn == "milestone" ? "&nbsp;" . ($reverseSortOrder ? "<span class='fa fa-angle-double-up'></span>" : "<span class='fa fa-angle-double-down'></span>") : ""));
					$returnArray['use_milestone'] = true;
				}
				$returnArray['task_list'] = $taskQueue;
				ajaxResponse($returnArray);
				break;
		}
	}

	function getTaskTypeForm($taskTypeId, $taskId = "", $myTask = false) {
		if (!empty($taskId)) {
			$resultSet = executeQuery("select * from tasks where task_id = ?", $taskId);
			if (!$taskRow = getNextRow($resultSet)) {
				$taskRow = array();
			}
		} else {
			$taskRow = array("project_id" => $_GET['project_id']);
		}
		$templateData = "";
		?>
        <form id="_task_form" name="_task_form" enctype='multipart/form-data'>
			<?php if ($myTask) { ?>
                <input type="hidden" id="my_task" name="my_task" value="Y"/>
			<?php } ?>
            <input type="hidden" id="task_id" name="task_id" value="<?= $taskId ?>"/>
            <input type="hidden" id="repeating_task_id" name="repeating_task_id" value="<?= $taskRow['repeating_task_id'] ?>"/>
            <input type="hidden" id="task_type_id" name="task_type_id" value="<?= $taskTypeId ?>"/>
            <p id="dialog_error_message" class="error-message"></p>
            <table id="_new_task_table">
				<?php

				# Display the description control
				$thisColumn = $this->getDataSource()->getColumns("description");
				$thisColumn->setControlValue("readonly", $myTask);
				$thisColumn->setControlValue("initial_value", $taskRow['description']);
				$thisColumn->setControlValue("not_null", true);
				$templateData .= $this->createControl($thisColumn);

				# Display the detailed description control
				if (!$myTask && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'USE_DETAILED_DESCRIPTION'))) {
					$thisColumn = $this->getDataSource()->getColumns("detailed_description");
					$thisColumn->setControlValue("initial_value", $taskRow['detailed_description']);
					$templateData .= $this->createControl($thisColumn);
				}

				# Display the prerequisites control
				if (!$myTask && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'USE_PREREQUISITES'))) {
					$thisColumn = $this->getDataSource()->getColumns("prerequisites");
					$thisColumn->setControlValue("initial_value", $taskRow['prerequisites']);
					$templateData .= $this->createControl($thisColumn);
				}

				# Display the task group control
				if (!$myTask && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'USE_TASK_GROUP'))) {
					$thisColumn = $this->getDataSource()->getColumns("task_group");
					$thisColumn->setControlValue("initial_value", $taskRow['task_group']);
					$templateData .= $this->createControl($thisColumn);
				}

				# Display the project control
				if ($GLOBALS['gUserRow']['superuser_flag']) {
					$resultSet = executeQuery("select * from projects where (inactive = 0 or project_id = ?) and client_id = ?",
						$taskRow['project_id'], $GLOBALS['gClientId']);
				} else {
					$resultSet = executeQuery("select * from projects where (inactive = 0 or project_id = ?) and (" .
						"user_id = " . $GLOBALS['gUserId'] . " or leader_user_id = " . $GLOBALS['gUserId'] .
						" or project_id in (select project_id from project_member_users where user_id = " .
						$GLOBALS['gUserId'] . ") or project_id in (select project_id from project_member_user_groups where user_group_id in " .
						"(select user_group_id from user_group_members where user_id = " . $GLOBALS['gUserId'] . ")))", $taskRow['project_id']);
				}
				if ($resultSet['row_count'] > 0 && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'PROJECT'))) {
					$thisColumn = $this->getDataSource()->getColumns("project_id");
					$thisColumn->setControlValue("initial_value", $taskRow['project_id']);
					$thisColumn->setControlValue("readonly", $myTask);
					$choices = array();
					while ($row = getNextRow($resultSet)) {
						$choices[$row['project_id']] = $row['description'];
					}
					$thisColumn->setControlValue("choices", $choices);
					$templateData .= $this->createControl($thisColumn);
					$resultSet = executeQuery("select * from project_milestones where project_id in (select project_id from projects where inactive = 0 or project_id = ?)", $taskRow['project_id']);
					if ($resultSet['row_count'] > 0) {
						$thisColumn = $this->getDataSource()->getColumns("project_milestone_id");
						$thisColumn->setControlValue("readonly", $myTask);
						$thisColumn->setControlValue("initial_value", $taskRow['project_milestone_id']);
						$choices = array();
						$resultSet = executeQuery("select * from project_milestones where project_id = ? order by target_date", $taskRow['project_id']);
						while ($row = getNextRow($resultSet)) {
							$choices[$row['project_milestone_id']] = $row['description'];
						}
						$thisColumn->setControlValue("choices", $choices);
						$templateData .= $this->createControl($thisColumn);
					}
				}

				# Display the assigned user control
				if (empty($taskId)) {
					$taskRow['assigned_user_id'] = getFieldFromId("user_id", "task_types", "task_type_id", $taskTypeId);
				}
				if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'USER_ASSIGNED'))) {
					$thisColumn = $this->getDataSource()->getColumns("assigned_user_id");
					$thisColumn->setControlValue("initial_value", $taskRow['assigned_user_id']);
					$thisColumn->setControlValue("readonly", $myTask);
					$templateData .= $this->createControl($thisColumn);
				} else {
					?>
                    <input type="hidden" id="assigned_user_id" name="assigned_user_id" value="<?= $taskRow['assigned_user_id'] ?>"/>
					<?php
				}

				# Display the assigned user group control
				if (empty($taskId)) {
					$taskRow['user_group_id'] = getFieldFromId("user_group_id", "task_types", "task_type_id", $taskTypeId);
				}
				$resultSet = executeQuery("select * from user_groups where inactive = 0 order by sort_order,description");
				if (!$myTask && $resultSet['row_count'] > 0 && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'USER_GROUP_ASSIGNED'))) {
					$thisColumn = $this->getDataSource()->getColumns("user_group_id");
					$thisColumn->setControlValue("initial_value", $taskRow['user_group_id']);
					$templateData .= $this->createControl($thisColumn);
				} else {
					?>
                    <input type="hidden" id="user_group_id" name="user_group_id" value="<?= $taskRow['user_group_id'] ?>"/>
					<?php
				}

				# Display the priority control
				if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'PRIORITY'))) {
					$thisColumn = $this->getDataSource()->getColumns("priority");
					$thisColumn->setControlValue("initial_value", $taskRow['priority']);
					$thisColumn->setControlValue("readonly", $myTask);
					$templateData .= $this->createControl($thisColumn);
				}

				# Display the start and end date and time controls
				if (!$myTask && (!empty($taskRow['repeating_task_id']) || $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => array('START_DATE', 'REPEATABLE', 'END_DATE', 'APPOINTMENT'), "all_required" => false)))) {
					$firstField = (!empty($taskRow['repeating_task_id']) || $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => array('START_DATE', 'REPEATABLE', 'APPOINTMENT'), "all_required" => false)) ? "start_date" : "end_date");
					$thisColumn = $this->getDataSource()->getColumns($firstField);
					$thisColumn->setControlValue("initial_value", $taskRow[$firstField]);
					?>
                    <tr id="start_date_row">
                        <td class="field-label"><label for='<?= $firstField ?>' id='<?= $firstField ?>_label'><?= htmlText($thisColumn->getControlValue("form_label")) ?></td>
                        <td class="field-text">
                            <table>
                                <tr>
									<?php if (!empty($taskRow['repeating_task_id']) || $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => array('START_DATE', 'REPEATABLE', 'APPOINTMENT'), "all_required" => false))) { ?>
                                        <td class="field-text"><input <?= (!empty($taskRow['repeating_task_id']) ? " readonly='readonly'" : "") ?> class="field-text validate[required,custom[date]]<?= (empty($taskRow['repeating_task_id']) ? " datepicker" : "") ?>"<?= (empty($taskRow['repeating_task_id']) ? " data-conditional-required=\"$('#repeating').is(':checked')\"" : "") ?> type="text" id="start_date" name="start_date" size="12" value="<?= (empty($taskRow['start_time']) ? "" : date("m/d/Y", strtotime($taskRow['start_time']))) ?>"/>
											<?php if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'APPOINTMENT'))) { ?>
                                                <input class="field-text validate[custom[time]] timepicker" type="text" id="start_time" name="start_time" size="8" value="<?= (empty($taskRow['start_time']) ? "" : date("g:i a", strtotime($taskRow['start_time']))) ?>"/>
											<?php } ?>
                                        </td>
									<?php } ?>
									<?php if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => array('END_DATE', 'APPOINTMENT'), "all_required" => false))) { ?>
										<?php
										if ($firstField != "end_date") {
											$thisColumn = $this->getDataSource()->getColumns("end_date");
											$thisColumn->setControlValue("initial_value", $taskRow["end_date"]);
											?>
                                            <td class="field-label secondary-label"><label for='end_date'><?= htmlText($thisColumn->getControlValue("form_label")) ?></label></td>
										<?php } ?>
                                        <td class="field-text"><input class="field-text validate[custom[date]] datepicker" type="text" id="end_date" name="end_date" size="12" value="<?= (empty($taskRow['end_time']) ? "" : date("m/d/Y", strtotime($taskRow['end_time']))) ?>"/>
											<?php if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'APPOINTMENT'))) { ?>
                                                <input class="field-text validate[custom[time]] timepicker" type="text" id="end_time" name="end_time" size="8" value="<?= (empty($taskRow['end_time']) ? "" : date("g:i a", strtotime($taskRow['end_time']))) ?>"/>
											<?php } ?>
                                        </td>
									<?php } ?>
                                </tr>
                            </table>
                        </td>
                    </tr>
					<?php
				}

				# display date due and date completed controls
				if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => array('DUE_DATE', 'DATE_COMPLETED'), "all_required" => false)) || !empty($taskRow['date_completed'])) {
					$firstField = ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'DUE_DATE')) ? "date_due" : "date_completed");
					$thisColumn = $this->getDataSource()->getColumns($firstField);
					?>
                    <tr>
                        <td class="field-label"><label id='<?= $firstField ?>_label' for='<?= $firstField ?>'><?= htmlText($thisColumn->getControlValue("form_label")) ?></td>
                        <td class="field-text">
                            <table>
                                <tr id="date_due_row">
									<?php
									if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'DUE_DATE'))) {
										$thisColumn = $this->getDataSource()->getColumns("date_due");
										$thisColumn->setControlValue("initial_value", $taskRow["date_due"]);
										$thisColumn->setControlValue("readonly", $myTask);
										$thisColumn->setControlValue("tabindex", "");
										?>
                                        <td class="field-text"><input class="field-text validate[custom[integer],min[0]]" type="text" id="due_days" name="due_days" size="4" value="<?= $taskRow['due_days'] ?>"/><?= $thisColumn->getControl($this) ?></td>
										<?php
									}

									if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'DATE_COMPLETED')) || !empty($taskRow['date_completed'])) {
										if ($firstField != "date_completed") {
											$thisColumn = $this->getDataSource()->getColumns("date_completed");
											$thisColumn->setControlValue("initial_value", $taskRow["date_completed"]);
											$thisColumn->setControlValue("readonly", $myTask);
											$thisColumn->setControlValue("tabindex", "");
											?>
                                            <td class="field-label secondary-label"><label for='date_completed'><?= htmlText($thisColumn->getControlValue("form_label")) ?></label></td>
										<?php } ?>
                                        <td class="field-text"><?= $thisColumn->getControl($this) ?></td>
										<?php
									}
									?>
                                </tr>
                            </table>
                        </td>
                    </tr>
					<?php
				}

				# Display All Day control
				if (!$myTask && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => array('ALL_DAY', 'APPOINTMENT')))) {
					$thisColumn = $this->getDataSource()->getColumns("all_day");
					$thisColumn->setControlValue("initial_value", $taskRow['all_day']);
					$templateData .= $this->createControl($thisColumn);
				}

				# Display Public Access control
				if (!$myTask && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => array('PUBLIC_ACCESS', 'APPOINTMENT')))) {
					$thisColumn = $this->getDataSource()->getColumns("public_access");
					$thisColumn->setControlValue("initial_value", $taskRow['public_access']);
					$templateData .= $this->createControl($thisColumn);
				}

				# Display Estimated Hours and % complete controls
				if (!$myTask && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => array('ESTIMATED_HOURS', 'PERCENT_COMPLETE'), "all_required" => false))) {
					$firstField = ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'ESTIMATED_HOURS')) ? "estimated_hours" : "percent_complete");
					$thisColumn = $this->getDataSource()->getColumns($firstField);
					?>
                    <tr>
                        <td class="field-label"><label for='<?= $firstField ?>'><?= htmlText($thisColumn->getControlValue("form_label")) ?></td>
                        <td class="field-text">
                            <table>
                                <tr>
									<?php
									if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'ESTIMATED_HOURS'))) {
										$thisColumn = $this->getDataSource()->getColumns("estimated_hours");
										$thisColumn->setControlValue("initial_value", $taskRow['estimated_hours']);
										$thisColumn->setControlValue("tabindex", "");
										?>
                                        <td class="field-text"><?= $thisColumn->getControl($this) ?></td>
										<?php
									}
									if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'PERCENT_COMPLETE'))) {
										$thisColumn = $this->getDataSource()->getColumns("percent_complete");
										$thisColumn->setControlValue("initial_value", $taskRow['percent_complete']);
										$thisColumn->setControlValue("tabindex", "");
										if ($firstField != "percent_complete") {
											?>
                                            <td class="field-label secondary-label"><label for='percent_complete'><?= htmlText($thisColumn->getControlValue("form_label")) ?></label></td>
										<?php } ?>
                                        <td class="field-text"><?= $thisColumn->getControl($this) ?></td>
									<?php } ?>
                                </tr>
                            </table>
                        </td>
                    </tr>
					<?php
				}

				# Display Task Category control
				if (!$myTask && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'USES_CATEGORIES'))) {
					$categoryCount = 0;
					$resultSet = executeQuery("select count(*) from task_categories where inactive = 0 and (user_id is null or user_id = ?) and client_id = ?",
						$GLOBALS['gUserId'], $GLOBALS['gClientId']);
					if ($row = getNextRow($resultSet)) {
						$categoryCount = $row['count(*)'];
					}
					if ($categoryCount > 0) {
						$thisColumn = $this->getDataSource()->getColumns("task_categories");
						if ($thisColumn) {
							$thisColumn->setControlValue("initial_value", $taskRow['task_categories']);
							$templateData .= $this->createControl($thisColumn);
						}
					}
				}

				# Display Task Files control
				if (!$myTask && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'ALLOW_FILE'))) {
					$thisColumn = $this->getDataSource()->getColumns("task_attachments");
					if ($thisColumn) {
						$templateData .= $this->createControl($thisColumn);
					}
				}

				# Display Task Images control
				if (!$myTask && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'ALLOW_IMAGES'))) {
					$thisColumn = $this->getDataSource()->getColumns("task_images");
					if ($thisColumn) {
						$templateData .= $this->createControl($thisColumn);
					}
				}

				# Display Subtasks control
				if (!$myTask && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'HAS_SUBTASKS'))) {
					$thisColumn = $this->getDataSource()->getColumns("subtasks");
					if ($thisColumn) {
						$templateData .= $this->createControl($thisColumn);
					}
				}

				# Display Time Log control
				if (!$myTask && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'LOG_TIME'))) {
					$thisColumn = $this->getDataSource()->getColumns("task_time_log");
					if ($thisColumn) {
						$templateData .= $this->createControl($thisColumn);
					}
				}

				# Display Task Log control
				if ($this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'TASK_LOG'))) {
					$thisColumn = $this->getDataSource()->getColumns("task_log");
					if ($thisColumn) {
						$templateData .= $this->createControl($thisColumn);
					}
				}

				# Display Repeat Rules Controls
				if (!$myTask && empty($taskRow['repeating_task_id']) && $this->taskTypeAttribute(array("task_type_id" => $taskTypeId, "task_id" => $taskId, "task_attributes" => 'REPEATABLE'))) {
					$repeatRules = parseNameValues($taskRow['repeat_rules']);
					?>
                    <tr>
                        <td class="field-label"></td>
                        <td class="field-text"><input class="field-text" type="checkbox" id="repeating" value="1" name="repeating"<?= (empty($taskRow['repeat_rules']) ? "" : " checked") ?> /><label class="checkbox-label" for="repeating">Repeating</label></td>
                    </tr>
                    <tr id="repeating_rules">
                        <td colspan="2">
                            <div id="repeating_rules_div">
                                <table id="repeating_rules_table">
                                    <tr>
                                        <td class="field-label"><label for="frequency">Frequency</label></td>
                                        <td class="field-text"><select class='field-text' name='frequency' id='frequency'>
                                                <option value="DAILY"<?= ($repeatRules['frequency'] == "DAILY" ? " selected" : "") ?>>Daily</option>
                                                <option value="WEEKLY"<?= ($repeatRules['frequency'] == "WEEKLY" ? " selected" : "") ?>>Weekly</option>
                                                <option value="MONTHLY"<?= ($repeatRules['frequency'] == "MONTHLY" ? " selected" : "") ?>>Monthly</option>
                                                <option value="YEARLY"<?= ($repeatRules['frequency'] == "YEARLY" ? " selected" : "") ?>>Yearly</option>
                                                <option value="AFTER"<?= ($repeatRules['frequency'] == "AFTER" ? " selected" : "") ?>>After Completion</option>
                                            </select></td>
                                    </tr>
                                    <tr>
                                        <td class="field-label"><label for="interval" class="required-label">Interval</label></td>
                                        <td class="field-text"><input class='field-text validate[required,custom[integer],min[1]] align-right' data-conditional-required='$("#repeating").is(":checked")' type='text' size='4' maxlength='4' name='interval' id='interval' value='<?= $repeatRules['interval'] ?>'/>
                                            <select class='field-text' name='units' id='units'>
                                                <option value="DAY"<?= ($repeatRules['units'] == "DAY" ? " selected" : "") ?>>Days</option>
                                                <option value="WEEK"<?= ($repeatRules['units'] == "WEEK" ? " selected" : "") ?>>Weeks</option>
                                                <option value="MONTH"<?= ($repeatRules['units'] == "MONTH" ? " selected" : "") ?>>Months</option>
                                                <option value="YEAR"<?= ($repeatRules['units'] == "YEAR" ? " selected" : "") ?>>Years</option>
                                            </select></td>
                                    </tr>
                                    <tr id="bymonth_row">
                                        <td class="field-label"><label for="bymonth" class="required-label">Months</label></td>
                                        <td class="field-text">
                                            <table id='bymonth_table'>
												<?php
												$byMonth = explode(",", $repeatRules['bymonth']);
												?>
                                                <tr>
                                                    <td><input class='field-text bymonth-month validate[minCheckbox[1]]' type='checkbox'<?= (in_array("1", $byMonth) ? " checked" : "") ?> rel='bymonth-month' value='1' name='bymonth_1' id='bymonth_1'/><label for="bymonth_1" class="checkbox-label">January</label></td>
                                                    <td><input class='field-text bymonth-month validate[minCheckbox[1]]' type='checkbox'<?= (in_array("4", $byMonth) ? " checked" : "") ?> rel='bymonth-month' value='4' name='bymonth_4' id='bymonth_4'/><label for="bymonth_4" class="checkbox-label">April</label></td>
                                                    <td><input class='field-text bymonth-month validate[minCheckbox[1]]' type='checkbox'<?= (in_array("7", $byMonth) ? " checked" : "") ?> rel='bymonth-month' value='7' name='bymonth_7' id='bymonth_7'/><label for="bymonth_7" class="checkbox-label">July</label></td>
                                                    <td><input class='field-text bymonth-month validate[minCheckbox[1]]' type='checkbox'<?= (in_array("10", $byMonth) ? " checked" : "") ?> rel='bymonth-month' value='10' name='bymonth_10' id='bymonth_10'/><label for="bymonth_10" class="checkbox-label">October</label></td>
                                                </tr>
                                                <tr>
                                                    <td><input class='field-text bymonth-month validate[minCheckbox[1]]' type='checkbox'<?= (in_array("2", $byMonth) ? " checked" : "") ?> rel='bymonth-month' value='2' name='bymonth_2' id='bymonth_2'/><label for="bymonth_2" class="checkbox-label">February</label></td>
                                                    <td><input class='field-text bymonth-month validate[minCheckbox[1]]' type='checkbox'<?= (in_array("5", $byMonth) ? " checked" : "") ?> rel='bymonth-month' value='5' name='bymonth_5' id='bymonth_5'/><label for="bymonth_5" class="checkbox-label">May</label></td>
                                                    <td><input class='field-text bymonth-month validate[minCheckbox[1]]' type='checkbox'<?= (in_array("8", $byMonth) ? " checked" : "") ?> rel='bymonth-month' value='8' name='bymonth_8' id='bymonth_8'/><label for="bymonth_8" class="checkbox-label">August</label></td>
                                                    <td><input class='field-text bymonth-month validate[minCheckbox[1]]' type='checkbox'<?= (in_array("11", $byMonth) ? " checked" : "") ?> rel='bymonth-month' value='11' name='bymonth_11' id='bymonth_11'/><label for="bymonth_11" class="checkbox-label">November</label></td>
                                                </tr>
                                                <tr>
                                                    <td><input class='field-text bymonth-month validate[minCheckbox[1]]' type='checkbox'<?= (in_array("3", $byMonth) ? " checked" : "") ?> rel='bymonth-month' value='3' name='bymonth_3' id='bymonth_3'/><label for="bymonth_3" class="checkbox-label">March</label></td>
                                                    <td><input class='field-text bymonth-month validate[minCheckbox[1]]' type='checkbox'<?= (in_array("6", $byMonth) ? " checked" : "") ?> rel='bymonth-month' value='6' name='bymonth_6' id='bymonth_6'/><label for="bymonth_6" class="checkbox-label">June</label></td>
                                                    <td><input class='field-text bymonth-month validate[minCheckbox[1]]' type='checkbox'<?= (in_array("9", $byMonth) ? " checked" : "") ?> rel='bymonth-month' value='9' name='bymonth_9' id='bymonth_9'/><label for="bymonth_9" class="checkbox-label">September</label></td>
                                                    <td><input class='field-text bymonth-month validate[minCheckbox[1]]' type='checkbox'<?= (in_array("12", $byMonth) ? " checked" : "") ?> rel='bymonth-month' value='12' name='bymonth_12' id='bymonth_12'/><label for="bymonth_12" class="checkbox-label">December</label></td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr id="byday_row">
                                        <td class="field-label"><label for="byday" class="required-label">Days</label></td>
                                        <td class="field-text">
                                            <table id="byday_weekly_table">
												<?php
												if ($repeatRules['frequency'] == "WEEKLY") {
													$byDay = explode(",", $repeatRules['byday']);
												} else {
													$byDay = array();
												}
												?>
                                                <tr>
                                                    <td><input class='field-text byday-weekday validate[minCheckbox[1]]' type='checkbox' rel='byday-weekday' value='SUN' name='byday_sun' id='byday_sun'<?= (in_array("SUN", $byDay) ? " checked" : "") ?> /><label for="byday_sun" class="checkbox-label">Sunday</label></td>
                                                    <td><input class='field-text byday-weekday validate[minCheckbox[1]]' type='checkbox' rel='byday-weekday' value='MON' name='byday_mon' id='byday_mon'<?= (in_array("MON", $byDay) ? " checked" : "") ?> /><label for="byday_mon" class="checkbox-label">Monday</label></td>
                                                    <td><input class='field-text byday-weekday validate[minCheckbox[1]]' type='checkbox' rel='byday-weekday' value='TUE' name='byday_tue' id='byday_tue'<?= (in_array("TUE", $byDay) ? " checked" : "") ?> /><label for="byday_tue" class="checkbox-label">Tuesday</label></td>
                                                    <td><input class='field-text byday-weekday validate[minCheckbox[1]]' type='checkbox' rel='byday-weekday' value='WED' name='byday_wed' id='byday_wed'<?= (in_array("WED", $byDay) ? " checked" : "") ?> /><label for="byday_wed" class="checkbox-label">Wednesday</label></td>
                                                </tr>
                                                <tr>
                                                    <td><input class='field-text byday-weekday validate[minCheckbox[1]]' type='checkbox' rel='byday-weekday' value='THU' name='byday_thu' id='byday_thu'<?= (in_array("THU", $byDay) ? " checked" : "") ?> /><label for="byday_thu" class="checkbox-label">Thursday</label></td>
                                                    <td><input class='field-text byday-weekday validate[minCheckbox[1]]' type='checkbox' rel='byday-weekday' value='FRI' name='byday_fri' id='byday_fri'<?= (in_array("FRI", $byDay) ? " checked" : "") ?> /><label for="byday_fri" class="checkbox-label">Friday</label></td>
                                                    <td><input class='field-text byday-weekday validate[minCheckbox[1]]' type='checkbox' rel='byday-weekday' value='SAT' name='byday_sat' id='byday_sat'<?= (in_array("SAT", $byDay) ? " checked" : "") ?> /><label for="byday_sat" class="checkbox-label">Saturday</label></td>
                                                </tr>
                                            </table>
                                            <table id="byday_monthly_table">
												<?php
												if ($repeatRules['frequency'] == "MONTHLY" || $repeatRules['frequency'] == "YEARLY") {
													$byDay = explode(",", $repeatRules['byday']);
												} else {
													$byDay = array();
												}
												for ($x = 1; $x <= 5; $x++) {
													$thisDay = $byDay[($x - 1)];
													$thisOrdinalDay = "";
													$thisWeekDay = "";
													if (strlen($thisDay) < 3) {
														$thisOrdinalDay = $thisDay;
													} else {
														$thisOrdinalDay = substr($thisDay, 0, -3);
														$thisWeekDay = substr($thisDay, -3);
													}
													?>
                                                    <tr class="byday-monthly-row">
                                                        <td><select class='field-text ordinal-day' id='ordinal_day_<?= $x ?>' name='ordinal_day_<?= $x ?>'>
                                                                <option value="">[Select]</option>
                                                                <option value="1"<?= ($thisOrdinalDay == "1" ? " selected" : "") ?>>1st</option>
                                                                <option value="2"<?= ($thisOrdinalDay == "2" ? " selected" : "") ?>>2nd</option>
                                                                <option value="3"<?= ($thisOrdinalDay == "3" ? " selected" : "") ?>>3rd</option>
                                                                <option value="4"<?= ($thisOrdinalDay == "4" ? " selected" : "") ?>>4th</option>
                                                                <option value="5"<?= ($thisOrdinalDay == "5" ? " selected" : "") ?>>5th</option>
                                                                <option value="6"<?= ($thisOrdinalDay == "6" ? " selected" : "") ?>>6th</option>
                                                                <option value="7"<?= ($thisOrdinalDay == "7" ? " selected" : "") ?>>7th</option>
                                                                <option value="8"<?= ($thisOrdinalDay == "8" ? " selected" : "") ?>>8th</option>
                                                                <option value="9"<?= ($thisOrdinalDay == "9" ? " selected" : "") ?>>9th</option>
                                                                <option value="10"<?= ($thisOrdinalDay == "10" ? " selected" : "") ?>>10th</option>
                                                                <option value="11"<?= ($thisOrdinalDay == "11" ? " selected" : "") ?>>11th</option>
                                                                <option value="12"<?= ($thisOrdinalDay == "12" ? " selected" : "") ?>>12th</option>
                                                                <option value="13"<?= ($thisOrdinalDay == "13" ? " selected" : "") ?>>13th</option>
                                                                <option value="14"<?= ($thisOrdinalDay == "14" ? " selected" : "") ?>>14th</option>
                                                                <option value="15"<?= ($thisOrdinalDay == "15" ? " selected" : "") ?>>15th</option>
                                                                <option value="16"<?= ($thisOrdinalDay == "16" ? " selected" : "") ?>>16th</option>
                                                                <option value="17"<?= ($thisOrdinalDay == "17" ? " selected" : "") ?>>17th</option>
                                                                <option value="18"<?= ($thisOrdinalDay == "18" ? " selected" : "") ?>>18th</option>
                                                                <option value="19"<?= ($thisOrdinalDay == "19" ? " selected" : "") ?>>19th</option>
                                                                <option value="20"<?= ($thisOrdinalDay == "20" ? " selected" : "") ?>>20th</option>
                                                                <option value="21"<?= ($thisOrdinalDay == "21" ? " selected" : "") ?>>21st</option>
                                                                <option value="22"<?= ($thisOrdinalDay == "22" ? " selected" : "") ?>>22nd</option>
                                                                <option value="23"<?= ($thisOrdinalDay == "23" ? " selected" : "") ?>>23rd</option>
                                                                <option value="24"<?= ($thisOrdinalDay == "24" ? " selected" : "") ?>>24th</option>
                                                                <option value="25"<?= ($thisOrdinalDay == "25" ? " selected" : "") ?>>25th</option>
                                                                <option value="26"<?= ($thisOrdinalDay == "26" ? " selected" : "") ?>>26th</option>
                                                                <option value="27"<?= ($thisOrdinalDay == "27" ? " selected" : "") ?>>27th</option>
                                                                <option value="28"<?= ($thisOrdinalDay == "28" ? " selected" : "") ?>>28th</option>
                                                                <option value="29"<?= ($thisOrdinalDay == "29" ? " selected" : "") ?>>29th</option>
                                                                <option value="30"<?= ($thisOrdinalDay == "30" ? " selected" : "") ?>>30th</option>
                                                                <option value="31"<?= ($thisOrdinalDay == "31" ? " selected" : "") ?>>31st</option>
                                                                <option value="-"<?= ($thisOrdinalDay == "-" ? " selected" : "") ?>>Last</option>
                                                            </select></td>
                                                        <td><select class='field-text weekday-select' id='weekday_<?= $x ?>' name='weekday_<?= $x ?>'>
                                                                <option value="">Day of the Month</option>
                                                                <option value="SUN"<?= ($thisWeekDay == "SUN" ? " selected" : "") ?>>Sunday</option>
                                                                <option value="MON"<?= ($thisWeekDay == "MON" ? " selected" : "") ?>>Monday</option>
                                                                <option value="TUE"<?= ($thisWeekDay == "TUE" ? " selected" : "") ?>>Tuesday</option>
                                                                <option value="WED"<?= ($thisWeekDay == "WED" ? " selected" : "") ?>>Wednesday</option>
                                                                <option value="THU"<?= ($thisWeekDay == "THU" ? " selected" : "") ?>>Thursday</option>
                                                                <option value="FRI"<?= ($thisWeekDay == "FRI" ? " selected" : "") ?>>Friday</option>
                                                                <option value="SAT"<?= ($thisWeekDay == "SAT" ? " selected" : "") ?>>Saturday</option>
                                                            </select></td>
                                                    </tr>
												<?php } ?>
                                            </table>
                                        </td>
                                    </tr>
                                    <tr id="count_row">
                                        <td class="field-label"><label for="count">Number of occurrences</label></td>
                                        <td class="field-text"><input type="text" size="6" class="field-text validate[custom[integer],min[1]]" id="count" name="count" value="<?= $repeatRules['count'] ?>"/></td>
                                    </tr>
                                </table>
                            </div>
                        </td>
                    </tr>
					<?php
				}

				# Display Custom data controls
				executeQuery("update task_data_definitions set allow_multiple = 1 where group_identifier is not null");
				executeQuery("update task_data_definitions set allow_multiple = 0,group_identifier = null where data_type = 'image'");
				$taskTypeDataArray = array();
				$completedGroupKeys = array();
				$resultSet = executeQuery("select * from task_data_definitions join task_type_data using (task_data_definition_id) where " .
					"inactive = 0 and task_type_id = ? order by sequence_number,description,task_data_definition_id", $taskTypeId);
				while ($row = getNextRow($resultSet)) {
					$taskTypeDataArray[$row['task_data_definition_id']] = $row;
				}
				$dataArray = array();
				while (!$myTask && count($taskTypeDataArray) > 0) {
					$row = array_shift($taskTypeDataArray);
					$thisColumn = $this->createColumnFromData($row);
					if ((empty($row['group_identifier']) && $row['allow_multiple'] == 0) || $thisColumn->getControlValue("subtype") == "image") {
						$resultSet = executeQuery("select * from task_data where task_id = ? and task_data_definition_id = ? and sequence_number is null",
							$taskId, $row['task_data_definition_id']);
						if ($dataRow = getNextRow($resultSet)) {
							$fieldData = $dataRow[$thisColumn->getControlValue('data_field')];
						} else {
							$fieldData = "";
						}
						$this->createControl($thisColumn);
					} else {
						?>
                        <td class="field-label"><label><?= htmlText(empty($row['group_identifier']) ? $row['description'] : $row['group_identifier']) ?></label></td>
                        <td class="field-text">
						<?php
						$columnArray = array($row['task_data_definition_id'] => $thisColumn);
						if (!empty($row['group_identifier'])) {
							foreach ($taskTypeDataArray as $index => $taskTypeDataRow) {
								if ($taskTypeDataRow['group_identifier'] == $row['group_identifier']) {
									$thisColumn = $this->createColumnFromData($taskTypeDataRow);
									$columnArray[$taskTypeDataRow['task_data_definition_id']] = $thisColumn;
									unset($taskTypeDataArray[$index]);
								}
							}
						}
						$multipleDataRow = array();
						foreach ($columnArray as $taskTypeDataId => $thisColumn) {
							$dataField = $thisColumn->getControlValue("data_field");
							$resultSet = executeQuery("select sequence_number,$dataField from task_data where task_id = ? and " .
								"task_data_definition_id = ? and sequence_number is not null order by sequence_number",
								$taskId, $taskTypeDataId);
							while ($dataRow = getNextRow($resultSet)) {
								if (!array_key_exists($dataRow['sequence_number'], $multipleDataRow)) {
									$multipleDataRow[$dataRow['sequence_number']] = array();
								}
								$fieldData = $dataRow[$thisColumn->getControlValue("data_field")];
								switch ($thisColumn->getControlValue("data_type")) {
									case "datetime":
									case "date":
										$fieldData = (empty($fieldData) ? "" : date("m/d/Y" . ($thisColumn->getControlValue("data_type") == "datetime" ? " g:i:sa" : ""), strtotime($fieldData)));
										break;
									case "tinyint":
										$fieldData = ($fieldData == 1 ? 1 : 0);
										break;
								}
								$multipleDataRow[$dataRow['sequence_number']][$thisColumn->getControlValue("column_name")] = array("data_value" => $fieldData);
							}
						}
						$controlName = (empty($row['group_identifier']) ? strtolower($row['task_data_definition_code']) : str_replace(" ", "_", strtolower($row['group_identifier'])));
						$dataArray[$controlName] = array("data_value" => $multipleDataRow);
						$templateData .= $this->createEditableList($columnArray, $controlName);
					}
					?>
                    </td>
                    </tr>
					<?php
				}
				?>
            </table>
        </form>
		<?php
		return array("data_values" => $dataArray, "template" => $templateData);
	}

	function taskTypeAttribute($parameters) {
		$taskTypeId = $parameters['task_type_id'];
		$addTask = (array_key_exists("task_id", $parameters) && empty($parameters['task_id']));
		$taskAttributes = $parameters['task_attributes'];
		if (!is_array($taskAttributes)) {
			$taskAttributes = array($taskAttributes);
		}
		$requireAll = (array_key_exists("all_required", $parameters) ? $parameters['all_required'] : true);
		if (!array_key_exists($taskTypeId, $this->iTaskTypeAttributes)) {
			$resultSet = executeQuery("select task_type_id,responsible_user_id,user_id from task_types where task_type_id = ? and client_id = ?",
				$taskTypeId, $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				$this->iTaskTypeAttributes[$taskTypeId] = array();
				$this->iTaskTypeAttributes[$taskTypeId]['responsible_user_id'] = $row['responsible_user_id'];
				$this->iTaskTypeAttributes[$taskTypeId]['user_id'] = $row['user_id'];
				$this->iTaskTypeAttributes[$taskTypeId]['attributes'] = array();
				$resultSet = executeQuery("select (select task_attribute_code from task_attributes where task_attribute_id = task_type_attributes.task_attribute_id) task_attribute_code," .
					"edit_only from task_type_attributes where task_type_id = ?", $taskTypeId);
				while ($row = getNextRow($resultSet)) {
					$this->iTaskTypeAttributes[$taskTypeId]['attributes'][$row['task_attribute_code']] = $row['edit_only'];
				}
			}
		}

		if (array_key_exists($taskTypeId, $this->iTaskTypeAttributes)) {
			if ((empty($this->iTaskTypeAttributes[$taskTypeId]['responsible_user_id']) && empty($this->iTaskTypeAttributes[$taskTypeId]['user_id'])) || $this->iTaskTypeAttributes[$taskTypeId]['responsible_user_id'] == $GLOBALS['gUserId'] || $this->iTaskTypeAttributes[$taskTypeId]['user_id'] == $GLOBALS['gUserId']) {
				$addTask = false;
			}
		} else {
			return false;
		}
		foreach ($taskAttributes as $taskAttribute) {
			if (array_key_exists($taskAttribute, $this->iTaskTypeAttributes[$taskTypeId]['attributes'])) {
				$editOnly = $this->iTaskTypeAttributes[$taskTypeId]['attributes'][$taskAttribute];
				if ($requireAll) {
					if ($editOnly && $addTask) {
						return false;
					}
				} else {
					return true;
				}
			} else {
				if ($requireAll) {
					return false;
				}
			}
		}
		return $requireAll;
	}

	function mainContent() {
		$sortOrderColumn = getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageCode']);
		$reverseSortOrder = getPreference("MAINTENANCE_REVERSE_SORT_ORDER", $GLOBALS['gPageCode']);
		$searchColumn = getPreference("MAINTENANCE_FILTER_COLUMN", $GLOBALS['gPageCode']);
		$searchValue = getPreference("MAINTENANCE_FILTER_TEXT", $GLOBALS['gPageCode']);
		$valuesArray = Page::getPagePreferences();
		$showCompleted = ($valuesArray['show_completed'] == "true");
		$workFlowInstanceSortOrderColumn = $valuesArray['work_flow_instance_sort_order_column'];
		$workFlowInstanceReverseSortOrder = ($valuesArray['work_flow_instance_reverse_sort_order'] == "true");
		$showCompletedWorkFlowInstances = ($valuesArray['show_completed_work_flow_instances'] == "true");
		$mySortOrderColumn = $valuesArray['my_sort_order_column'];
		$myReverseSortOrder = ($valuesArray['my_reverse_sort_order'] == "true");
		$myShowCompleted = ($valuesArray['my_show_completed'] == "true");

		$showWorkFlowTab = false;
		$resultSet = executeQuery("select * from work_flow_definitions where client_id = ? and requires_contact = 0 and work_flow_definition_id in " .
			"(select distinct work_flow_definition_id from work_flow_details)" . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and work_flow_definition_id in " .
				"(select work_flow_definition_id from work_flow_user_access where user_id = " . $GLOBALS['gUserId'] . ")"), $GLOBALS['gClientId']);
		if ($resultSet['row_count'] > 0) {
			$showWorkFlowTab = true;
		}
		if (!$showWorkFlowTab) {
			$resultSet = executeQuery("select work_flow_instance_id from work_flow_instances where inactive = 0 and (creator_user_id = ? or responsible_user_id = ?)", $GLOBALS['gUserId'], $GLOBALS['gUserId']);
			if ($resultSet['row_count'] > 0) {
				$showWorkFlowTab = true;
			}
		}

		$taskTypeArray = array();
		$resultSet = executeQuery("select *,((select count(*) from task_type_users where task_type_id = task_types.task_type_id) + " .
			"(select count(*) from task_type_user_groups where task_type_id = task_types.task_type_id)) access_count from task_types " .
			"where inactive = 0 and client_id = ? and (task_type_id not in " .
			"(select task_type_id from task_type_attributes where task_attribute_id = (select task_attribute_id from task_attributes " .
			"where task_attribute_code = 'APPOINTMENT'))) order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if ($row['access_count'] == 0 || $GLOBALS['gUserRow']['superuser_flag']) {
				$taskTypeArray[$row['task_type_id']] = $row['description'];
			} else {
				$accessSet = executeQuery("select task_type_id from task_types where task_type_id = ? and (task_type_id in " .
					"(select task_type_id from task_type_users where user_id = ?) or task_type_id in (select task_type_id from task_type_user_groups " .
					"where user_group_id in (select user_group_id from user_group_members where user_id = ?)))", $row['task_type_id'], $GLOBALS['gUserId'], $GLOBALS['gUserId']);
				if ($accessRow = getNextRow($accessSet)) {
					$taskTypeArray[$row['task_type_id']] = $row['description'];
				}
			}
		}
		if (count($taskTypeArray) > 0) {
			?>
            <button id="_add_new_task">Add New Task</button>
            <div id="_new_task_types">
                <ul>
					<?php
					foreach ($taskTypeArray as $taskTypeId => $description) {
						?>
                        <li class="new-task-type" data-task_type_id="<?= $taskTypeId ?>"><?= htmlText($description) ?></li>
						<?php
					}
					?>
                </ul>
            </div>
		<?php } ?>
        <div class="tabbed-form">
            <ul>
                <li><a href="#task_view">To Do</a></li>
                <li class='custom-tab'><a href="#my_task_view">My Tasks</a></li>
				<?php
				$valuesArray = Page::getPagePreferences();
				$tabArray = array();
				foreach ($valuesArray as $valueName => $valueData) {
					if (substr($valueName, 0, strlen("filter_tab-")) == "filter_tab-") {
						$parts = explode(":", $valueData);
						$tabKey = $parts[1];
						$tabArray[$tabKey] = $parts[0];
					}
				}
				foreach ($tabArray as $tabKey => $tabName) {
					?>
                    <li class='custom-tab' data-tab_key="<?= $tabKey ?>" id="_tab_list_item_<?= $tabKey ?>"><a href="#filter_tab_<?= $tabKey ?>"><?= htmlText($tabName) ?></a><img class="delete-tab" data-tab_key="<?= $tabKey ?>" data-tab_name="<?= htmlText($tabName) ?>" src="/images/delete.png"/></li>
					<?php
				}
				?>
                <li id="_project_li"><a href="#project_view">Projects</a></li>
				<?php if ($showWorkFlowTab) { ?>
                    <li id="_work_flow_li"><a href="#work_flow_view">Work Flow</a></li>
				<?php } ?>
                <li><a href="#setup">Setup</a></li>
            </ul>
            <div id="task_view">
                <div id="_parameters">
                    <form id="_task_list_form" name="_task_list_form">
                        <input type="hidden" id="_task_sort_order_column" name="_task_sort_order_column" value="<?= $sortOrderColumn ?>"/>
                        <input type="hidden" id="_task_reverse_sort_order" name="_task_reverse_sort_order" value="<?= ($reverseSortOrder ? "true" : "false") ?>"/>
                        <table id="_parameters_table">
                            <tr>
                                <td><select id="_task_filter_column" name="_task_filter_column">
                                        <option value="">[All]</option>
                                        <option value="tasks.description"<?= ($searchColumn == "tasks.description" ? " selected" : "") ?>>Description</option>
                                        <option value="tasks.detailed_description"<?= ($searchColumn == "tasks.detailed_description" ? " selected" : "") ?>>Details</option>
                                        <option value="task_type"<?= ($searchColumn == "task_type" ? " selected" : "") ?>>Task Type</option>
                                        <option value="project"<?= ($searchColumn == "project" ? " selected" : "") ?>>Project</option>
                                        <option value="milestone"<?= ($searchColumn == "milestone" ? " selected" : "") ?>>Milestone</option>
                                        <option value="task_category_list"<?= ($searchColumn == "task_category_list" ? " selected" : "") ?>>Category</option>
                                        <option value="tasks.task_group"<?= ($searchColumn == "tasks.task_group" ? " selected" : "") ?>>Group</option>
                                    </select>
                                    <input type="text" size="30" id="_task_filter_text" name="_task_filter_text" value="<?= htmlText($searchValue) ?>"/>
                                    <button id="_search_button">Search</button>
                                </td>
                                <td>
                                    <button id="_filter_tasks">Filter</button>
                                </td>
                                <td>
                                    <button id="_print_tasks">Print</button>
                                </td>
								<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
                                    <td>
                                        <button id="_renumber_tasks">Renumber</button>
                                    </td>
								<?php } ?>
                            </tr>
                        </table>
                    </form>
                </div>
                <table id="_maintenance_list" class="grid-table">
                </table>
            </div>

            <div class='custom-tab-div' id="my_task_view">
                <div id="_my_parameters">
                    <form id="_my_task_list_form" name="_my_task_list_form">
                        <input type="hidden" id="_my_task_sort_order_column" name="_my_task_sort_order_column" value="<?= $mySortOrderColumn ?>"/>
                        <input type="hidden" id="_my_task_reverse_sort_order" name="_my_task_reverse_sort_order" value="<?= ($myReverseSortOrder ? "true" : "false") ?>"/>
                        <table id="_my_parameters_table">
                            <tr>
                                <td><input type="checkbox" id="my_show_completed" name="my_show_completed" value="true"<?= ($myShowCompleted ? " checked" : "") ?> /><label class="checkbox-label" for="my_show_completed">Show Completed</label></td>
                            </tr>
                        </table>
                    </form>
                </div>
                <table id="_my_maintenance_list" class="task-list grid-table">
                </table>
            </div>

			<?php
			foreach ($tabArray as $tabKey => $tabName) {
				?>
                <div class='custom-tab-div' id="filter_tab_<?= $tabKey ?>">
                    <table id="_maintenance_list_<?= $tabKey ?>" class="task-list grid-table"></table>
                </div>
				<?php
			}
			?>

            <div id="project_view">
				<?php if (canAccessPageCode("PROJECTMAINT")) { ?>
                    <button id="_add_project">Create New Project</button>
				<?php } ?>
                <input type="checkbox" id="hide_completed_projects" checked="checked"><label for="hide_completed_projects" class="checkbox-label">Hide Completed</label>
                <ul>
					<?php
					if ($GLOBALS['gUserRow']['superuser_flag']) {
						$resultSet = executeQuery("select * from projects where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
					} else {
						$resultSet = executeQuery("select * from projects where inactive = 0 and (user_id = ? or leader_user_id = ? or " .
							"project_id in (select project_id from project_member_users where user_id = ?) or " .
							"project_id in (select project_id from project_member_user_groups where user_group_id in " .
							"(select user_group_id from user_group_members where user_id = ?))) order by sort_order,description", $GLOBALS['gUserId'], $GLOBALS['gUserId'], $GLOBALS['gUserId'], $GLOBALS['gUserId']);
					}
					while ($row = getNextRow($resultSet)) {
						$resultSet1 = executeQuery("select * from project_milestones where project_id = ? and date_completed is null order by target_date", $row['project_id']);
						$milestone = "";
						if ($row1 = getNextRow($resultSet1)) {
							$milestone = $row1['description'];
						}
						?>
                        <li <?= (!empty($row['date_completed']) ? " class='completed-project'" : "") ?>><?= htmlText($row['description']) ?><?= (empty($milestone) ? "" : " &mdash; " . $milestone) ?> &mdash; <a href="/project.php?id=<?= $row['project_id'] ?>">Project Page</a><?php if (canAccessPageCode("PROJECTMAINT")) { ?> &mdash; <a href="/projectmaintenance.php?url_page=show&primary_id=<?= $row['project_id'] ?>">Project Admin</a><?php } ?></li>
						<?php
					}
					?>
                </ul>
            </div>

			<?php if ($showWorkFlowTab) { ?>
                <div id="work_flow_view">
                    <form id="_work_flow_instance_list_form" name="_work_flow_instance_list_form">
                        <table id="_work_flow_table">
                            <tr>
								<?php
								$resultSet = executeQuery("select * from work_flow_definitions where client_id = ? and requires_contact = 0 and work_flow_definition_id in " .
									"(select distinct work_flow_definition_id from work_flow_details)" . ($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and work_flow_definition_id in " .
										"(select work_flow_definition_id from work_flow_user_access where user_id = " . $GLOBALS['gUserId'] . ")"), $GLOBALS['gClientId']);
								if ($resultSet['row_count'] > 0) {
									?>
                                    <td>
                                        <button id="_add_new_work_flow_instance">Add New</button>
                                        <div id="_new_work_flow_definitions">
                                            <ul>
												<?php
												while ($row = getNextRow($resultSet)) {
													?>
                                                    <li class="new-work-flow" data-responsible_user_id="<?= $row['responsible_user_id'] ?>" data-description="<?= htmlText($row['description']) ?>" data-work_flow_definition_id="<?= $row['work_flow_definition_id'] ?>"><?= htmlText($row['description']) ?></li>
													<?php
												}
												?>
                                            </ul>
                                        </div>
                                    </td>
								<?php } ?>
                                <td><input type="checkbox" id="show_completed_work_flow_instances" name="show_completed_work_flow_instances" value="true"<?= ($showCompletedWorkFlowInstances ? " checked" : "") ?> /><label class="checkbox-label" for="show_completed_work_flow_instances">Show Completed</label></td>
                            </tr>
                        </table>
                        <input type="hidden" id="_work_flow_instance_sort_order_column" name="_work_flow_instance_sort_order_column" value="<?= $workFlowInstanceSortOrderColumn ?>"/>
                        <input type="hidden" id="_work_flow_instance_reverse_sort_order" name="_work_flow_instance_reverse_sort_order" value="<?= ($workFlowInstanceReverseSortOrder ? "true" : "false") ?>"/>
                        <table id="_work_flow_instance_list" class="grid-table">
                        </table>
                    </form>
                </div>
			<?php } ?>
            <div id="setup">
                <div id="_task_categories">
                    <p class="subheader">Task Categories</p>
                    <table id="_category_list" class="grid-table">
                    </table>
                    <p class="subheader">Create a new category:</p>
                    <form id="_task_category_form" name="_task_category_form">
                        <table>
                            <tr>
                                <td class="field-label"><label for="task_category_description">Description</label></td>
                                <td class="field-text"><input type="text" size="30" class="field-text validate[required]" maxlength="255" id="task_category_description" name="task_category_description"/></td>
                            </tr>
                            <tr>
                                <td class="field-label"></td>
                                <td class="field-text"><input type="checkbox" class="field-text" id="task_category_user" name="task_category_user" value="Y"/><label class="checkbox-label" for="task_category_user">Everyone can use</label></td>
                            </tr>
                            <tr>
                                <td class="field-label"></td>
                                <td class="field-text">
                                    <button id="create_task_category">Create Category</button>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>
                <div id="_tab_deletions">
                    <p class="subheader">Custom Filters</p>
                    <table class="grid-table" id="_tab_deletion_table">
						<?php
						foreach ($tabArray as $tabKey => $tabName) {
							?>
                            <tr class="tab-deletion-row" id="_tab_deletion_<?= $tabKey ?>">
                                <td class="field-text"><?= htmlText($tabName) ?></td>
                                <td class="field-text"><img class="delete-tab" data-tab_key="<?= $tabKey ?>" data-tab_name="<?= htmlText($tabName) ?>" src="/images/listdelete.png" alt="Delete"/></td>
                            </tr>
							<?php
						}
						?>
                    </table>
                </div>
                <div class='clear-div'></div>
            </div>
        </div>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#hide_completed_projects").click(function () {
                if ($(this).prop("checked")) {
                    $(".completed-project").hide();
                } else {
                    $(".completed-project").show();
                }
            });
            $(document).on("tap click", "#_add_project", function () {
                document.location = "/projectmaintenance.php?url_page=new";
            });
            $(document).on("change", "#project_id", function () {
                var projectId = $(this).val();
                $("#project_milestone_id").find("option").remove();
                $("#project_milestone_id").append("<option value=''>[None]</option>");
                $("#_project_link").html("");
                if (projectId != "") {
                    $("#_project_link").html("<a href='/project.php?id=" + projectId + "'>Project Page</a>");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_milestones&project_id=" + projectId, function (returnArray) {
                        if ("error_message" in returnArray) {
                            displayErrorMessage(returnArray['error_message']);
                        } else {
                            for (var i in returnArray['project_milestones']) {
                                $("#project_milestone_id").append("<option value='" + i + "'>" + returnArray['project_milestones'][i] + "</option>");
                            }
                        }
                    });
                }
            });
            $(document).on("tap click", ".delete-tab", function (event) {
                var tabKey = $(this).data("tab_key");
                var tabName = $(this).data("tab_name");
                $('#_confirm_delete_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    title: 'Filter Tab "' + tabName + '"',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_tab&tab_key=" + tabKey, function (returnArray) {
                                if ("error_message" in returnArray) {
                                    displayErrorMessage(returnArray['error_message']);
                                } else {
                                    $("#_tab_list_item_" + tabKey).remove();
                                    $("#filter_tab_" + tabKey).remove();
                                    $("#_tab_deletion_" + tabKey).remove();
                                    $('.tabbed-form').tabs("refresh");
                                }
                            });
                            $("#_confirm_delete_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_delete_dialog").dialog('close');
                        }
                    }
                });
                event.stopPropagation();
                return false;
            });
            $("#change_task_type_task_type_id").change(function () {
                $("#_change_task_type_losses").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=task_type_losses&task_id=" + $("#change_task_type_task_id").val() + "&task_type_id=" + $(this).val(), function (returnArray) {
                        if ("error_message" in returnArray) {
                            displayErrorMessage(returnArray['error_message']);
                        } else {
                            $("#_change_task_type_losses").html(returnArray['losses']);
                        }
                    });
                }
            });
            $(document).on("tap click", function () {
                $("#_new_task_types").slideUp("fast");
            });
            $(".filter-field").change(function () {
                var fieldNumber = $(this).data("field_number");
                $(".filter-value-" + fieldNumber).hide();
                if (!empty($(this).val())) {
                    $("#filter_" + $(this).val() + "_" + fieldNumber).show();
                }
            });
            $(".filter-field").trigger("change");
            $(document).on("tap click", "#create_task_category", function () {
                if ($("#_task_category_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_task_category", $("#_task_category_form").serialize(), function (returnArray) {
                        if ("error_message" in returnArray) {
                            displayErrorMessage(returnArray['error_message']);
                        } else {
                            $("#task_category_description").val("");
                            $("#task_category_user").prop("checked", false);
                            getCategories();
                            $("#task_category_description").focus();
                        }
                    });
                }
                return false;
            });
            $(document).on("tap click", ".work-flow-instance-row", function () {
                var workFlowInstanceId = $(this).data("primary_id");
                fetchWorkFlowInstance(workFlowInstanceId);
            });
            $(document).on("tap click", ".data-row", function (event) {
                var taskId = $(this).data("primary_id");
                var contactId = $(this).data("contact_id");
                fetchTask(taskId, false, contactId);
            });
            $(document).on("tap click", ".my-data-row", function () {
                var taskId = $(this).data("primary_id");
                var contactId = $(this).data("contact_id");
                fetchTask(taskId, true, contactId);
            });
            $(document).on("change", "#frequency", function () {
                $("#bymonth_row").hide();
                $("#byday_row").hide();
                $("#byday_weekly_table").hide();
                $("#byday_monthly_table").hide();
                $(".byday-monthly-row").hide();
                $("#units").hide();
                $("#count_row").show();
                var thisValue = $(this).val();
                if (thisValue == "WEEKLY") {
                    $("#byday_row").show();
                    $("#byday_weekly_table").show();
                } else if (thisValue == "MONTHLY") {
                    $("#byday_row").show();
                    $("#byday_monthly_table").show();
                    $(".byday-monthly-row").each(function () {
                        $(this).show();
                        if ($(this).find(".ordinal-day").val() == "") {
                            return false;
                        }
                    });
                } else if (thisValue == "YEARLY") {
                    $("#bymonth_row").show();
                    $("#byday_row").show();
                    $("#byday_monthly_table").show();
                    $(".byday-monthly-row").each(function () {
                        $(this).show();
                        if ($(this).find(".ordinal-day").val() == "") {
                            return false;
                        }
                    });
                } else if (thisValue == "AFTER") {
                    $("#units").show();
                    $("#count_row").val("").hide();
                }
            });
            $(document).on("change", ".ordinal-day", function () {
                if (!empty($(this).val())) {
                    $(".ordinal-day").each(function () {
                        if (!$(this).is(":visible")) {
                            $(this).closest(".byday-monthly-row").show();
                            return false;
                        } else if (empty($(this).val())) {
                            return false;
                        }
                    });
                }
            });
            $(document).on("tap click", "#repeating", function () {
                if ($(this).is(":checked")) {
                    $("#start_date_label").addClass("required-label");
                    $("#start_date_label").append("<span class='required-tag'>*</span>");
                    $("#repeating_rules").show();
                    $("#date_due").hide();
                    $("#date_due").next(".ui-datepicker-trigger").hide();
                    $("#due_days").show();
                    $("#date_due_label").html("Due after X days");
                } else {
                    $("#start_date_label").removeClass("required-label");
                    $("#start_date_row").find(".required-tag").remove();
                    $("#repeating_rules").hide();
                    $("#date_due").show();
                    $("#date_due").next(".ui-datepicker-trigger").show();
                    $("#due_days").hide();
                    $("#date_due_label").html("Due");
                }
            });
            $(document).on("tap click", "#_print_tasks", function () {
                window.open("/printtasks.php");
                return false;
            });
			<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
            $(document).on("tap click", "#_renumber_tasks", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=renumber_tasks", function (returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    } else {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>";
                    }
                });
                return false;
            });
			<?php } ?>
            $('.tabbed-form').tabs();
            $(document).on("tap click", ".work-flow-instance-header", function () {
                var sortOrder = $(this).data("column_name");
                if (sortOrder == $("#_work_flow_instance_sort_order_column").val()) {
                    $("#_work_flow_instance_reverse_sort_order").val($("#_work_flow_instance_reverse_sort_order").val() == "true" ? "false" : "true");
                } else {
                    $("#_work_flow_instance_sort_order_column").val(sortOrder);
                    $("#_work_flow_instance_reverse_sort_order").val("false");
                }
                getWorkFlowInstanceList();
            });
            $(document).on("tap click", ".column-header", function () {
                var sortOrder = $(this).data("column_name");
                if (sortOrder == $("#_task_sort_order_column").val()) {
                    $("#_task_reverse_sort_order").val($("#_task_reverse_sort_order").val() == "true" ? "false" : "true");
                } else {
                    $("#_task_sort_order_column").val(sortOrder);
                    $("#_task_reverse_sort_order").val("false");
                }
                getTaskList();
                getWorkFlowInstanceList();
                displayCustomTabs();
            });
            $(document).on("tap click", ".my-column-header", function () {
                var sortOrder = $(this).data("column_name");
                if (sortOrder == $("#_my_task_sort_order_column").val()) {
                    $("#_my_task_reverse_sort_order").val($("#_my_task_reverse_sort_order").val() == "true" ? "false" : "true");
                } else {
                    $("#_my_task_sort_order_column").val(sortOrder);
                    $("#_my_task_reverse_sort_order").val("false");
                }
                getMyTaskList();
            });
            $(document).on("tap click", "#show_completed", function () {
                getTaskList();
                getWorkFlowInstanceList();
                displayCustomTabs();
            });
            $(document).on("tap click", "#my_show_completed", function () {
                getMyTaskList();
            });
            $(document).on("tap click", "#show_completed_work_flow_instances", function () {
                getWorkFlowInstanceList();
            });
            $("#_task_filter_column").change(function () {
                ($("#_search_button").find("span").length > 0 ? $("#_search_button").find("span").html("Search") : $("#_search_button").html("Search"));
            });
            $("#_task_filter_text").keydown(function (event) {
                ($("#_search_button").find("span").length > 0 ? $("#_search_button").find("span").html("Search") : $("#_search_button").html("Search"));
                if (event.which == 13) {
                    $("#_search_button").trigger("click");
                    return false;
                }
            });
            $(document).on("tap click", "#_filter_tasks", function () {
                $("#filter_tab_name").val("");
                $("#filter_on").prop("checked", true);
                $('#_filter_tasks_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    title: 'Filter Tasks',
                    width: 700,
                    buttons: {
                        Filter: function (event) {
                            getTaskList();
                            getWorkFlowInstanceList();
                            var tabName = $("#filter_tab_name").val();
                            if (tabName != "") {
                                var tabKey = getCrcValue(tabName, true);
                                if ($("#filter_tab_" + tabKey).length == 0) {
                                    $(".custom-tab").last().after("<li class='custom-tab' data-tab_key='" + tabKey + "' id='_tab_list_item_" + tabKey + "'><a href='#filter_tab_" + tabKey + "'>" + tabName +
                                        "</a><img class='delete-tab' data-tab_key='" + tabKey + "' data-tab_name='" + tabName + "' src='/images/delete.png' /></li>");
                                    $(".custom-tab-div").last().after("<div class='custom-tab-div' id='filter_tab_" + tabKey + "'><table id='_maintenance_list_" + tabKey + "' class='task-list grid-table'></table></div>");
                                    $("#_tab_deletion_table").append("<tr class='tab-deletion-row' id='_tab_deletion_" + tabKey + "'><td class='field-text'>" +
                                        tabName + "</td><td class='field-text'><img class='delete-tab' data-tab_key='" + tabKey + "' data-tab_name='" +
                                        tabName + "' src='/images/listdelete.png' alt='Delete' /></td></tr>");
                                    $('.tabbed-form').tabs("refresh");
                                }
                            }
                            displayCustomTabs();
                            $("#_filter_tasks_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_filter_tasks_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("tap click", "#_search_button", function () {
                if ($(this).find(".ui-button-text").html() == "Show All") {
                    $("#_task_filter_column").val("");
                    $("#_task_filter_text").val("");
                    $("#filter_on").prop("checked", false);
                }
                getTaskList();
                getWorkFlowInstanceList();
                displayCustomTabs();
                return false;
            });
            $(document).on("tap click", "#_add_new_task", function () {
                if ($(".new-task-type").length == 1) {
                    createNewTask($(".new-task-type").data("task_type_id"));
                } else {
                    $("#_new_task_types").slideDown("fast");
                }
                return false;
            });
            $(document).on("tap click", ".new-task-type", function () {
                $("#_new_task_types").slideUp("fast");
                createNewTask($(this).data("task_type_id"));
            });
            $("#_new_task_types").mouseleave(function () {
                $(this).slideUp("fast");
            });
            $(document).on("tap click", "#_add_new_work_flow_instance", function () {
                if ($(".new-work-flow").length == 1) {
                    createNewWorkFlowInstance($(".new-work-flow"));
                } else {
                    $("#_new_work_flow_definitions").slideDown("fast");
                }
                return false;
            });
            $(document).on("tap click", ".new-work-flow", function () {
                $("#_new_work_flow_definitions").slideUp("fast");
                createNewWorkFlowInstance($(this));
            });
            $("#_new_work_flow_definitions").mouseleave(function () {
                $(this).slideUp("fast");
            });
            $(document).on("tap click", ".date-completed", function (event) {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_completed&task_id=" + $(this).data('primary_id'), function (returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    } else {
                        getTaskList();
                        getWorkFlowInstanceList();
                        displayCustomTabs();
                    }
                });
                event.stopPropagation();
                return false;
            });
            $(document).on("tap click", ".edit-repeating-task", function (event) {
                var taskId = $(this).data("repeating_task_id");
                fetchTask(taskId);
                event.stopPropagation();
                return false;
            });
            $(document).on("tap click", ".delete-work-flow-instance", function (event) {
                var workFlowInstanceId = $(this).closest("tr").data("primary_id");
                $('#_confirm_delete_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    title: 'Delete Work Flow Instance?',
                    buttons: {
                        Yes: function (event) {
                            deleteWorkFlowInstance(workFlowInstanceId);
                            $("#_confirm_delete_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_delete_dialog").dialog('close');
                        }
                    }
                });
                event.stopPropagation();
                return false;
            });
            $(document).on("tap click", ".delete-task", function (event) {
                var taskId = $(this).closest("tr").data("primary_id");
                $('#_confirm_delete_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    title: 'Delete Task?',
                    buttons: {
                        Yes: function (event) {
                            deleteTask(taskId);
                            $("#_confirm_delete_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_delete_dialog").dialog('close');
                        }
                    }
                });
                event.stopPropagation();
                return false;
            });
            $(document).on("tap click", ".change-task-type", function (event) {
                $("#change_task_type_task_type_id").val("");
                $("#change_task_type_task_id").val($(this).closest("tr").data("primary_id"));
                $("#_change_task_type_description").html($(this).closest("tr").find(".task-description").html());
                $("#_change_task_type_losses").html("");
                $('#_change_task_type_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    title: 'Change Task Type',
                    width: 400,
                    buttons: {
                        Save: function (event) {
                            changeTaskType();
                        },
                        Cancel: function (event) {
                            $("#_change_task_type_dialog").dialog('close');
                        }
                    }
                });
                event.stopPropagation();
                return false;
            });
            getTaskList();
            getMyTaskList();
            getCategories();
            getWorkFlowInstanceList();
            displayCustomTabs();
			<?php
			$taskId = getFieldFromId("task_id", "tasks", "task_id", $_GET['task_id']);
			if (!empty($taskId)) { ?>
            fetchTask("<?= $taskId ?>");
			<?php
			} else {
			$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_id", $_GET['task_type_id']);
			if (!empty($taskTypeId) && $_GET['add_task'] == "true") {
			?>
            createNewTask(<?= $_GET['task_type_id'] ?>, <?= (empty($_GET['project_id']) ? "''" : $_GET['project_id']) ?>)
			<?php
			}
			}
			?>
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function displayCustomTabs() {
                $(".custom-tab").each(function () {
                    var tabKey = $(this).data("tab_key");
                    if (tabKey != undefined && tabKey != "") {
                        var postData = $("#_task_list_form").serialize() + "&" + $("#_filter_tasks_form").serialize();
                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_task_list&tab_key=" + tabKey, postData, function (returnArray) {
                            if ("error_message" in returnArray) {
                                displayErrorMessage(returnArray['error_message']);
                            } else {
                                $("#_maintenance_list_" + tabKey + " tr").remove();
                                var headerHtml = "<tr>";
                                for (var i in returnArray['column_headers']) {
                                    headerHtml += "<th class='column-header' data-column_name='" + returnArray['column_headers'][i]['column_name'] +
                                        "'>" + returnArray['column_headers'][i]['description'] + "</th>";
                                }
                                headerHtml += "<th></th><th></th><th></th><th></th></tr>";
                                $("#_maintenance_list_" + tabKey).append(headerHtml);
                                for (var i in returnArray['task_list']) {
                                    var cellStyle = "";
                                    var rowStyle = "";
                                    if ("cell_style" in returnArray['task_list'][i]) {
                                        cellStyle = returnArray['task_list'][i]['cell_style'];
                                    }
                                    if ("row_style" in returnArray['task_list'][i]) {
                                        rowStyle = returnArray['task_list'][i]['row_style'];
                                    }
                                    var rowHtml = "<tr title='Edit This Task' class='data-row' data-contact_id='" + returnArray['task_list'][i]['contact_id'] + "' data-primary_id='" + returnArray['task_list'][i]['task_id'] + "'" + (rowStyle == "" ? "" : " style='" + rowStyle + "'") + ">";
                                    if (returnArray['task_list'][i]['date_completed'] == "") {
                                        rowHtml += "<td title='Set this task completed' class='align-center date-completed' data-primary_id='" + returnArray['task_list'][i]['task_id'] + "'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + "><input type='checkbox' value='Y' id='date_completed_" + returnArray['task_list'][i]['task_id'] + "' /></td>";
                                    } else {
                                        rowHtml += "<td" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['date_completed'] + "</td>";
                                    }
                                    rowHtml += "<td class='data-row-data align-right'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['priority'] + "</td>";
                                    rowHtml += "<td class='task-description data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['description'] + "</td>";
                                    if (returnArray['use_contact']) {
                                        rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['full_name'] + "</td>";
                                    }
                                    rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['date_due'] + "</td>";
                                    rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['assigned_user'] + "</td>";
                                    rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['task_type'] + "</td>";
                                    if (returnArray['use_project']) {
                                        rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['project'] + "</td>";
                                    }
                                    if (returnArray['use_milestone']) {
                                        rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['milestone'] + "</td>";
                                    }
                                    if (returnArray['use_task_category_list']) {
                                        rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['task_category_list'] + "</td>";
                                    }
                                    if (returnArray['use_task_group']) {
                                        rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['task_group'] + "</td>";
                                    }
                                    rowHtml += "<td title='Change Task Type' class='data-row-data change-task-type'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + "><img alt='Change Task Type' src='/images/change.png' /></td>";
                                    rowHtml += "<td title='Delete Task' class='data-row-data delete-task'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + "><img alt='Delete Task' src='/images/delete.gif' /></td>";
                                    if (returnArray['task_list'][i]['repeating_task_id'] != "") {
                                        rowHtml += "<td title='Edit Repeating Task' class='data-row-data edit-repeating-task' data-repeating_task_id='" + returnArray['task_list'][i]['repeating_task_id'] + "'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + "><img alt='Edit Repeating Task' src='/images/edit.png' /></td>";
                                    } else {
                                        rowHtml += "<td></td>";
                                    }
                                    rowHtml += "<td></td>";
                                    rowHtml += "</tr>"
                                    $("#_maintenance_list_" + tabKey).append(rowHtml);
                                }
                                $("#show_completed").prop("checked", returnArray['show_completed']);
                                var searchLabel = ($("#_task_filter_text").val() == "" && !$("#filter_on").prop("checked") ? "Search" : "Show All");
                                ($("#_search_button").find("span").length > 0 ? $("#_search_button").find("span").html(searchLabel) : $("#_search_button").html(searchLabel));
                            }
                        });
                    }
                })
            }
            function changeTaskType() {
                if ($("#change_task_type_task_type_id").val() != "") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=change_task_type&task_id=" + $("#change_task_type_task_id").val() + "&task_type_id=" + $("#change_task_type_task_type_id").val(), function (returnArray) {
                        if ("error_message" in returnArray) {
                            displayErrorMessage(returnArray['error_message']);
                        } else {
                            $("#_change_task_type_dialog").dialog('close');
                            getTaskList();
                            displayCustomTabs();
                        }
                    });
                }
            }
            function createNewWorkFlowInstance(workFlowDefinition) {
                $("#_work_flow_instance_form").clearForm();
                $("#_wfi_project_row").show();
                $("#work_flow_definition_id").val(workFlowDefinition.data("work_flow_definition_id"));
                $("#wfi_description").val(workFlowDefinition.data("description"));
                $("#wfi_responsible_user_id").val(workFlowDefinition.data("responsible_user_id"));
                $("#wfi_creator_user").val("<?= htmlText(getUserDisplayName($GLOBALS['gUserId'])) ?>");
                $("#wfi_start_date").prop("disabled", false).attr("class", "validate[required,custom[date]]");
                $("#_work_flow_instance_details tr").remove();
                $('#_work_flow_instance_dialog').dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    title: 'New Work Flow Instance',
                    width: 650,
                    buttons: {
                        Save: function (event) {
                            if ($("#_work_flow_instance_form").validationEngine("validate")) {
                                saveWorkFlowInstance();
                            }
                        },
                        Cancel: function (event) {
                            $("#_work_flow_instance_dialog").dialog('close');
                        }
                    }
                });
            }
            function fetchWorkFlowInstance(workFlowInstanceId) {
                $("#_wfi_project_row").hide();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_work_flow_instance&work_flow_instance_id=" + workFlowInstanceId, function (returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    } else {
                        for (var i in returnArray['data_values']) {
                            $("#" + i).val(returnArray['data_values'][i]);
                        }
                        $("#_work_flow_instance_details tr").remove();
                        $("#_work_flow_instance_details").append("<tr><th>Description</th><th>For</th><th>Status</th></tr>");
                        for (var i in returnArray['instance_details']) {
                            $("#_work_flow_instance_details").append("<tr><td>" + returnArray['instance_details'][i]['description'] +
                                "</td><td>" + returnArray['instance_details'][i]['assignment'] + "</td><td>" +
                                returnArray['instance_details'][i]['progress'] + "</td></tr>");
                        }
                        $("#wfi_start_date").prop("disabled", true).attr("class", "");
                        $('#_work_flow_instance_dialog').dialog({
                            closeOnEscape: true,
                            draggable: true,
                            modal: true,
                            resizable: false,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            title: 'Work Flow Instance',
                            width: 650,
                            buttons: {
                                Save: function (event) {
                                    if ($("#_work_flow_instance_form").validationEngine("validate")) {
                                        saveWorkFlowInstance();
                                    }
                                },
                                Cancel: function (event) {
                                    $("#_work_flow_instance_dialog").dialog('close');
                                }
                            }
                        });
                    }
                });
            }
            function fetchTask(taskId, myTask, contactId) {
                if (contactId != "" && contactId != undefined) {
                    window.open("contactmaintenance.php?url_page=show&clear_filter=true&primary_id=" + contactId);
                    return;
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_task&task_id=" + taskId + "&my_task=" + (myTask ? "true" : "false"), function (returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    } else {
                        if ("task_dialog" in returnArray) {
                            displayForm(returnArray['task_dialog']);
                            $('#_task_dialog').dialog({
                                closeOnEscape: true,
                                draggable: true,
                                modal: true,
                                resizable: false,
                                position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                                title: 'Create New Task',
                                width: 850,
                                buttons: {
                                    Save: function (event) {
                                        if ($("#_task_form").validationEngine("validate")) {
                                            if ($("#repeating").is(":checked")) {
                                                var thisValue = $("#frequency").val();
                                                if (thisValue == "MONTHLY" || thisValue == "YEARLY") {
                                                    var foundDay = false;
                                                    for (var x = 1; x <= 5; x++) {
                                                        if ($("#ordinal_day_" + x).val() != "") {
                                                            foundDay = true;
                                                            break;
                                                        }
                                                    }
                                                } else {
                                                    var foundDay = true;
                                                }
                                            } else {
                                                var foundDay = true;
                                            }
                                            if (foundDay) {
                                                saveTask();
                                            } else {
                                                $("#ordinal_day_1").validationEngine("showPrompt", "At least one day is required.");
                                            }
                                        }
                                    },
                                    Cancel: function (event) {
                                        $("#_task_dialog").dialog('close').html("");
                                    }
                                }
                            });
                        }
                    }
                    if ("template" in returnArray) {
                        $("#_templates").html(returnArray['template']);
                    }
                    setTimeout(function () {
                        if ($("#repeating").is(":checked")) {
                            $("#start_date_label").addClass("required-label");
                            $("#start_date_label").append("<span class='required-tag'>*</span>");
                            $("#repeating_rules").show();
                            $("#date_due").hide();
                            $("#date_due").next(".ui-datepicker-trigger").hide();
                            $("#due_days").show();
                            $("#date_due_label").html("Due after X days");
                        } else {
                            $("#start_date_label").removeClass("required-label");
                            $("#start_date_row").find(".required-tag").remove();
                            $("#repeating_rules").hide();
                            $("#date_due").show();
                            $("#date_due").next(".ui-datepicker-trigger").show();
                            $("#due_days").hide();
                            $("#date_due_label").html("Due");
                        }
                        $("#frequency").trigger("change");
                    }, 300);
                    for (var i in returnArray['data_values']) {
                        if ($("#_" + i + "_table").hasClass("editable-list")) {
                            $("#_" + i + "_table tr").not(":first").not(":last").remove();
                            for (var j in returnArray['data_values'][i]) {
                                addEditableListRow(i, returnArray['data_values'][i][j]);
                            }
                        } else if (typeof returnArray['data_values'][i] == "object" && "data_value" in returnArray['data_values'][i]) {
                            if ($("#" + i).is("input[type=checkbox]")) {
                                $("#" + i).prop("checked", returnArray['data_values'][i].data_value != 0);
                            } else if ($("#" + i).is("a")) {
                                $("#" + i).attr("href", returnArray['data_values'][i].data_value).css("display", (returnArray['data_values'][i].data_value == "" ? "none" : "inline"));
                            } else {
                                $("#" + i).val(returnArray['data_values'][i].data_value);
                            }
                        }
                    }
                    $("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({ social_tools: false, default_height: 480, default_width: 854, deeplinking: false });
                    $("a[href^='http']").add("area[href^='http']").add("a[href*='download.php']").add("a.download-file-link").not("a[rel^='prettyPhoto']").attr("target", "_blank");
                    $(".selector-value-list").trigger("change");
                });
            }
            function displayDialogErrorMessage(messageText) {
                $("#dialog_error_message").html(messageText);
            }
            function displayForm(dialogContent) {
                $("#_task_dialog").html(dialogContent);
                $("#_task_dialog .datepicker").datepicker({
                    showOn: "button",
                    buttonText: "<span class='fad fa-calendar-alt'></span>",
                    constrainInput: false,
                    dateFormat: "mm/dd/y",
                    yearRange: "c-100:c+10"
                });
                $("#_task_dialog .timepicker").timepicker({
                    showPeriod: true,
                    showLeadingZero: false
                });
                $("#_task_dialog .required-label").append("<span class='required-tag'>*</span>");
                $(".selection-control").each(function () {
                    var connectsWith = $(this).data("connector");
                    var thisId = $(this).attr("id");
                    var userSetsOrder = ($(this).data("user_order") == "yes");
                    $("#" + thisId + " ul").sortable({
                        connectWith: "." + connectsWith,
                        update: function (e, ui) {
                            var newList = "";
                            $("#" + thisId + " .selection-chosen-div li").each(function () {
                                if ($(this).data("id") != "") {
                                    if (newList != "") {
                                        newList += ",";
                                    }
                                    newList += $(this).data("id");
                                }
                            });
                            $("#" + thisId + " .selector-value-list").val(newList);
                            var thisList = $("#" + thisId + " .selection-choices-div ul");
                            var listitems = thisList.children('li').get();
                            listitems.sort(function (a, b) {
                                var aValue = parseInt($(a).data("sort_order"));
                                var bValue = parseInt($(b).data("sort_order"));
                                return (aValue == bValue ? 0 : (aValue < bValue ? -1 : 1));
                            });
                            $.each(listitems, function (idx, itm) {
                                thisList.append(itm);
                            });
                            if (!userSetsOrder) {
                                var thisList = $("#" + thisId + " .selection-chosen-div ul");
                                var listitems = thisList.children('li').get();
                                listitems.sort(function (a, b) {
                                    var aValue = parseInt($(a).data("sort_order"));
                                    var bValue = parseInt($(b).data("sort_order"));
                                    return (aValue == bValue ? 0 : (aValue < bValue ? -1 : 1));
                                });
                                $.each(listitems, function (idx, itm) {
                                    thisList.append(itm);
                                });
                            }
                        }
                    }).disableSelection();
                });
                $("#_task_form").validationEngine();
            }
            function deleteTask(taskId) {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_task&task_id=" + taskId, function (returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    } else {
                        getTaskList();
                        getMyTaskList();
                        getWorkFlowInstanceList();
                        displayCustomTabs();
                    }
                });
            }
            function deleteWorkFlowInstance(workFlowInstanceId) {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_work_flow_instance&work_flow_instance_id=" + workFlowInstanceId, function (returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    } else {
                        getTaskList();
                        getWorkFlowInstanceList();
                        displayCustomTabs();
                    }
                });
            }
            function saveWorkFlowInstance() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_work_flow_instance", $("#_work_flow_instance_form").serialize(), function (returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    } else {
                        $("#_work_flow_instance_dialog").dialog('close');
                        getTaskList();
                        getWorkFlowInstanceList();
                        displayCustomTabs();
                    }
                });
            }
            function saveTask() {
                $("body").addClass("waiting-for-ajax");
                $("#_task_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_task").attr("method", "POST").attr("target", "post_iframe").submit();
                $("#_post_iframe").off("load");
                $("#_post_iframe").on("load", function () {
                    $("body").removeClass("waiting-for-ajax");
                    var returnText = $(this).contents().find("body").html();
                    const returnArray = processReturn(returnText);
                    if (returnArray === false) {
                        return;
                    }
                    if ("error_message" in returnArray) {
                        displayDialogErrorMessage(returnArray['error_message']);
                    } else {
                        if ($("#_task_dialog").dialog("isOpen") === true) {
                            $("#_task_dialog").dialog('close').html("");
                        }
                        getTaskList();
                        getMyTaskList();
                        getWorkFlowInstanceList();
                        displayCustomTabs();
                    }
                });
            }
            function createNewTask(taskTypeId, projectId) {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_new_task&task_type_id=" + taskTypeId + "&project_id=" + projectId, function (returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    } else {
                        if ("template" in returnArray) {
                            $("#_templates").html(returnArray['template']);
                        }
                        if ("task_dialog" in returnArray) {
                            displayForm(returnArray['task_dialog']);
                            for (var i in returnArray['data_values']) {
                                if ($("#_" + i + "_table").hasClass("editable-list")) {
                                    $("#_" + i + "_table tr").not(":first").not(":last").remove();
                                    for (var j in returnArray['data_values'][i]) {
                                        addEditableListRow(i, returnArray['data_values'][i][j]);
                                    }
                                } else if (typeof returnArray['data_values'][i] == "object" && "data_value" in returnArray['data_values'][i]) {
                                    if ($("#" + i).is("input[type=checkbox]")) {
                                        $("#" + i).prop("checked", returnArray['data_values'][i].data_value != 0);
                                    } else if ($("#" + i).is("a")) {
                                        $("#" + i).attr("href", returnArray['data_values'][i].data_value).css("display", (returnArray['data_values'][i].data_value == "" ? "none" : "inline"));
                                    } else {
                                        $("#" + i).val(returnArray['data_values'][i].data_value);
                                    }
                                }
                            }
                            $(".selector-value-list").trigger("change");
                            $('#_task_dialog').dialog({
                                closeOnEscape: true,
                                draggable: true,
                                modal: true,
                                resizable: false,
                                position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                                title: 'Create New Task',
                                width: 850,
                                buttons: {
                                    Save: function (event) {
                                        if ($("#_task_form").validationEngine("validate")) {
                                            if ($("#repeating").is(":checked")) {
                                                var thisValue = $("#frequency").val();
                                                if (thisValue == "MONTHLY" || thisValue == "YEARLY") {
                                                    var foundDay = false;
                                                    for (var x = 1; x <= 5; x++) {
                                                        if ($("#ordinal_day_" + x).val() != "") {
                                                            foundDay = true;
                                                            break;
                                                        }
                                                    }
                                                } else {
                                                    var foundDay = true;
                                                }
                                            } else {
                                                var foundDay = true;
                                            }
                                            if (foundDay) {
                                                saveTask();
                                            } else {
                                                $("#ordinal_day_1").validationEngine("showPrompt", "At least one day is required.");
                                            }
                                        }
                                    },
                                    Cancel: function (event) {
                                        $("#_task_dialog").dialog('close').html("");
                                    }
                                }
                            });
                        }
                    }
                });
            }
            function getCategories() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_categories", function (returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    } else {
                        $("#_category_list tr").remove();
                        $("#_category_list").append("<tr><th>Description</th><th>Who can use</th></tr>");
                        $(".filter-task-category").each(function () {
                            $(this).data("existing_value", $(this).val());
                            $(this).find("option").remove();
                            $(this).append("<option value=''>[Select]</option>");
                        });
                        for (var i in returnArray['category_list']) {
                            var rowHtml = "<tr>";
                            rowHtml += "<td>" + returnArray['category_list'][i]['description'] + "</td>";
                            rowHtml += "<td>" + returnArray['category_list'][i]['access'] + "</td>";
                            rowHtml += "</tr>"
                            $("#_category_list").append(rowHtml);
                            $(".filter-task-category").each(function () {
                                $(this).append("<option value='" + returnArray['category_list'][i]['task_category_id'] + "'>" + returnArray['category_list'][i]['description'] + "</option>");
                            });
                        }
                        $(".filter-task-category").each(function () {
                            $(this).val($(this).data("existing_value"));
                        });
                    }
                });
            }
            function getWorkFlowInstanceList() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_work_flow_instance_list", $("#_work_flow_instance_list_form").serialize(), function (returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    } else {
                        $("#_work_flow_instance_list tr").remove();
                        var headerHtml = "<tr>";
                        for (var i in returnArray['column_headers']) {
                            headerHtml += "<th class='work-flow-instance-header' data-column_name='" + returnArray['column_headers'][i]['column_name'] +
                                "'>" + returnArray['column_headers'][i]['description'] + "</th>";
                        }
                        headerHtml += "<th></th><th></th></tr>";
                        $("#_work_flow_instance_list").append(headerHtml);
                        for (var i in returnArray['work_flow_instance_list']) {
                            var cellStyle = "";
                            var rowStyle = "";
                            if ("cell_style" in returnArray['work_flow_instance_list'][i]) {
                                cellStyle = returnArray['work_flow_instance_list'][i]['cell_style'];
                            }
                            if ("row_style" in returnArray['work_flow_instance_list'][i]) {
                                rowStyle = returnArray['work_flow_instance_list'][i]['row_style'];
                            }
                            var rowHtml = "<tr title='Edit This Work Flow' class='work-flow-instance-row' data-primary_id='" + returnArray['work_flow_instance_list'][i]['work_flow_instance_id'] + "'" + (rowStyle == "" ? "" : " style='" + rowStyle + "'") + ">";
                            for (var j in returnArray['column_headers']) {
                                rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['work_flow_instance_list'][i][returnArray['column_headers'][j]['column_name']] + "</td>";
                            }
                            rowHtml += "<td title='Delete Work Flow Instance' class='data-row-data delete-work-flow-instance'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + "><img alt='Delete Work Flow Instance' src='/images/delete.gif' /></td>";
                            rowHtml += "<td></td>";
                            rowHtml += "</tr>"
                            $("#_work_flow_instance_list").append(rowHtml);
                        }
                        $("#show_completed_work_flow_instances").prop("checked", returnArray['show_completed_work_flow_instances']);
                    }
                });
            }
            function getTaskList() {
                var postData = $("#_task_list_form").serialize() + "&" + $("#_filter_tasks_form").serialize();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_task_list", postData, function (returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    } else {
                        $("#_maintenance_list tr").remove();
                        var headerHtml = "<tr>";
                        for (var i in returnArray['column_headers']) {
                            headerHtml += "<th class='column-header' data-column_name='" + returnArray['column_headers'][i]['column_name'] +
                                "'>" + returnArray['column_headers'][i]['description'] + "</th>";
                        }
                        headerHtml += "<th></th><th></th><th></th><th></th></tr>";
                        $("#_maintenance_list").append(headerHtml);
                        for (var i in returnArray['task_list']) {
                            var cellStyle = "";
                            var rowStyle = "";
                            if ("cell_style" in returnArray['task_list'][i]) {
                                cellStyle = returnArray['task_list'][i]['cell_style'];
                            }
                            if ("row_style" in returnArray['task_list'][i]) {
                                rowStyle = returnArray['task_list'][i]['row_style'];
                            }
                            var rowHtml = "<tr title='Edit This Task' class='data-row' data-contact_id='" + returnArray['task_list'][i]['contact_id'] + "' data-primary_id='" + returnArray['task_list'][i]['task_id'] + "'" + (rowStyle == "" ? "" : " style='" + rowStyle + "'") + ">";
                            if (returnArray['task_list'][i]['date_completed'] == "") {
                                rowHtml += "<td title='Set this task completed' class='align-center date-completed' data-primary_id='" + returnArray['task_list'][i]['task_id'] + "'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + "><input type='checkbox' value='Y' id='date_completed_" + returnArray['task_list'][i]['task_id'] + "' /></td>";
                            } else {
                                rowHtml += "<td" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['date_completed'] + "</td>";
                            }
                            rowHtml += "<td class='data-row-data align-right'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['priority'] + "</td>";
                            rowHtml += "<td class='task-description data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['description'] + "</td>";
                            if (returnArray['use_contact']) {
                                rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['full_name'] + "</td>";
                            }
                            rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['date_due'] + "</td>";
                            rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['assigned_user'] + "</td>";
                            rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['task_type'] + "</td>";
                            if (returnArray['use_project']) {
                                rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['project'] + "</td>";
                            }
                            if (returnArray['use_milestone']) {
                                rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['milestone'] + "</td>";
                            }
                            if (returnArray['use_task_category_list']) {
                                rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['task_category_list'] + "</td>";
                            }
                            if (returnArray['use_task_group']) {
                                rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['task_group'] + "</td>";
                            }
                            rowHtml += "<td title='Change Task Type' class='data-row-data change-task-type'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + "><img alt='Change Task Type' src='/images/change.png' /></td>";
                            rowHtml += "<td title='Delete Task' class='data-row-data delete-task'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + "><img alt='Delete Task' src='/images/delete.gif' /></td>";
                            if (returnArray['task_list'][i]['repeating_task_id'] != "") {
                                rowHtml += "<td title='Edit Repeating Task' class='data-row-data edit-repeating-task' data-repeating_task_id='" + returnArray['task_list'][i]['repeating_task_id'] + "'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + "><img alt='Edit Repeating Task' src='/images/edit.png' /></td>";
                            } else {
                                rowHtml += "<td></td>";
                            }
                            rowHtml += "<td></td>";
                            rowHtml += "</tr>"
                            $("#_maintenance_list").append(rowHtml);
                        }
                        $("#show_completed").prop("checked", returnArray['show_completed']);
                        var searchLabel = ($("#_task_filter_text").val() == "" && !$("#filter_on").prop("checked") ? "Search" : "Show All");
                        ($("#_search_button").find("span").length > 0 ? $("#_search_button").find("span").html(searchLabel) : $("#_search_button").html(searchLabel));
                    }
                });
            }
            function getMyTaskList() {
                var postData = $("#_my_task_list_form").serialize();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_my_task_list", postData, function (returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    } else {
                        $("#_my_maintenance_list tr").remove();
                        var headerHtml = "<tr>";
                        for (var i in returnArray['column_headers']) {
                            headerHtml += "<th class='my-column-header' data-column_name='" + returnArray['column_headers'][i]['column_name'] +
                                "'>" + returnArray['column_headers'][i]['description'] + "</th>";
                        }
                        headerHtml += "<th></th><th></th></tr>";
                        $("#_my_maintenance_list").append(headerHtml);
                        for (var i in returnArray['task_list']) {
                            var cellStyle = "";
                            var rowStyle = "";
                            if ("cell_style" in returnArray['task_list'][i]) {
                                cellStyle = returnArray['task_list'][i]['cell_style'];
                            }
                            if ("row_style" in returnArray['task_list'][i]) {
                                rowStyle = returnArray['task_list'][i]['row_style'];
                            }
                            var rowHtml = "<tr title='View This Task' class='my-data-row' data-contact_id='" + returnArray['task_list'][i]['contact_id'] + "' data-primary_id='" + returnArray['task_list'][i]['task_id'] + "'" + (rowStyle == "" ? "" : " style='" + rowStyle + "'") + ">";
                            if (returnArray['show_completed']) {
                                rowHtml += "<td" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['date_completed'] + "</td>";
                            }
                            rowHtml += "<td class='data-row-data align-right'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['priority'] + "</td>";
                            rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['description'] + "</td>";
                            rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['date_due'] + "</td>";
                            rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['assigned_user'] + "</td>";
                            rowHtml += "<td class='data-row-data'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + ">" + returnArray['task_list'][i]['task_type'] + "</td>";
                            rowHtml += "<td title='Delete Task' class='data-row-data delete-task'" + (cellStyle == "" ? "" : " style='" + cellStyle + "'") + "><img alt='Delete Task' src='/images/delete.gif' /></td>";
                            rowHtml += "<td></td>";
                            rowHtml += "</tr>"
                            $("#_my_maintenance_list").append(rowHtml);
                        }
                        $("#my_show_completed").prop("checked", returnArray['show_completed']);
                    }
                });
            }
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #project_view li {
                margin: 4px 0;
            }
            #hide_completed_projects {
                margin-left: 20px;
            }
            .completed-project {
                display: none;
            }
            #_project_link {
                padding-left: 20px;
            }
            #wfi_notes {
                width: 400px;
            }
            #_work_flow_instance_list {
                width: 100%;
                border-collapse: collapse;
            }
            #_work_flow_instance_list td {
                border-top: 1px solid rgb(180, 180, 180);
                border-bottom: 1px solid rgb(180, 180, 180);
                padding-left: 10px;
                padding-right: 10px;
                padding-top: 3px;
                padding-bottom: 3px;
                white-space: nowrap;
            }
            #_work_flow_instance_list td:last-child {
                width: 100%;
            }
            #_work_flow_instance_list th {
                font-size: 12px;
                color: rgb(15, 100, 50);
                cursor: pointer;
                text-align: left;
                padding-right: 10px;
                padding-left: 10px;
                border-top: 1px solid rgb(180, 180, 180);
                border-bottom: 1px solid rgb(180, 180, 180);
                white-space: nowrap;
                border-left: 1px dotted rgb(200, 200, 200);
            }
            #_work_flow_instance_list th:hover {
                color: rgb(190, 70, 20)
            }
            #_work_flow_instance_list th:first-child {
                border-left: none;
            }
            #_work_flow_instance_list tr.work-flow-instance-row:hover {
                background-color: rgb(250, 250, 200) !important;
                cursor: pointer;
            }
            #_parameters {
                padding-bottom: 10px;
                position: relative;
            }
            #_parameters_table td {
                padding-right: 30px;
            }
            #_my_parameters {
                padding-bottom: 10px;
                position: relative;
            }
            #_my_parameters_table td {
                padding-right: 30px;
            }
            #_work_flow_table {
                padding-bottom: 10px;
                position: relative;
            }
            #_work_flow_table td {
                padding-right: 40px;
            }
            #_new_task_types {
                display: none;
                border: 1px solid black;
                padding: 0;
                position: absolute;
                background-color: rgb(240, 240, 240);
                min-width: 100px;
                cursor: pointer;
                overflow: auto;
                z-index: 9000;
            }
            #_new_task_types ul {
                list-style: none;
                margin: 0;
                padding: 0;
                padding-top: 5px;
                padding-bottom: 5px;
            }
            #_new_task_types ul li {
                margin: 0;
                padding: 5px;
                padding-left: 15px;
                padding-right: 15px;
                font-weight: bold;
            }
            #_new_task_types ul li:hover {
                background-color: rgb(250, 250, 200);
            }
            #_new_work_flow_definitions {
                display: none;
                border: 1px solid black;
                padding: 5px;
                position: absolute;
                background-color: rgb(255, 255, 255);
                min-width: 100px;
                cursor: pointer;
                overflow: auto;
            }
            #_new_work_flow_definitions ul {
                list-style: none;
                margin: 0;
                padding: 0;
            }
            #_new_work_flow_definitions ul li {
                margin: 0;
                padding: 4px;
                font-weight: bold;
            }
            #_new_work_flow_definitions ul li:hover {
                background-color: rgb(250, 250, 200);
            }
            #_maintenance_list td {
                font-size: 12px;
            }
            .task-list {
                width: 100%;
                border-collapse: collapse;
            }
            .task-list td {
                height: 19px;
                font-size: 10px;
                border-top: 1px solid rgb(180, 180, 180);
                border-bottom: 1px solid rgb(180, 180, 180);
                padding-left: 10px;
                padding-right: 10px;
                padding-top: 3px;
                padding-bottom: 3px;
                white-space: nowrap;
            }
            .task-list td:last-child {
                width: 100%;
            }
            .task-list th {
                font-size: 12px;
                color: rgb(15, 100, 50);
                cursor: pointer;
                text-align: left;
                padding-right: 10px;
                padding-left: 10px;
                border-top: 1px solid rgb(180, 180, 180);
                border-bottom: 1px solid rgb(180, 180, 180);
                white-space: nowrap;
                border-left: 1px dotted rgb(200, 200, 200);
            }
            .task-list th:hover {
                color: rgb(190, 70, 20)
            }
            .task-list th:first-child {
                border-left: none;
            }
            .task-list tr:hover {
                background-color: rgb(250, 250, 200) !important;
                cursor: pointer;
            }
            #detailed_description {
                width: 500px;
                height: 50px;
                font-size: 10px;
            }
            #prerequisites {
                width: 500px;
                height: 50px;
                font-size: 10px;
            }
            #_new_task_table {
                margin-top: 30px;
                margin-left: 20px;
            }
            .secondary-label {
                padding-left: 20px;
            }
            #repeating_rules {
                display: none;
            }
            #repeating_rules_div {
                border: 1px solid black;
                padding: 10px;
            }
            #bymonth_table td {
                padding-right: 20px;
            }
            #bymonth_row {
                display: none;
            }
            #byday_row {
                display: none;
            }
            #byday_weekly_table {
                display: none;
            }
            #byday_weekly_table td {
                padding-right: 20px;
            }
            #byday_monthly_table {
                display: none;
            }
            #units {
                display: none;
            }
            .selection-control div {
                height: 150px;
            }
            .selection-control ul {
                min-height: 145px;
            }
            .file-attachment-link {
                padding-left: 20px;
                padding-right: 20px;
            }
            #due_days {
                display: none;
            }
            #_category_list td {
                font-size: 12px;
                padding-left: 20px;
                padding-right: 20px;
            }
            #_task_categories {
                float: left;
                width: 47%;
                margin-right: 6%;
            }
            #_tab_deletions {
                float: right;
                width: 47%;
            }
            #_category_list {
                width: 100%;
            }
            #_work_flow_instance_details {
                margin-left: auto;
                margin-right: auto;
            }
            #_work_flow_instance_details td {
                padding-left: 20px;
                padding-right: 20px;
            }
            .filter-task-type, .filter-project, .filter-project-milestone, .filter-task-category, .filter-task-group {
                display: none;
            }
            #_change_task_type_losses {
                min-height: 200px;
                padding-top: 10px;
            }
            #_change_task_type_description {
                font-size: 12px;
                font-weight: bold;
            }
            #_tab_deletion_table td img {
                padding-top: 5px;
            }
            #_tab_deletion_table td:first-child {
                padding-right: 50px;
            }
            #_tab_deletion_table tr:hover {
                background-color: rgb(250, 250, 200);
            }
            .delete-tab {
                cursor: pointer;
            }
            .filter-field-cell {
                padding-right: 10px;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_change_task_type_dialog" class="dialog-box">
            <form id="_change_task_type_form" name="_change_task_type_form">
                <p>Change task type of task '<span id="_change_task_type_description"></span>'</p>
                <input type="hidden" id="change_task_type_task_id" name="change_task_type_task_id" value=""/>
                <table>
                    <tr>
                        <td class="field-label"><label for="change_task_type_task_type_id">New Task Type</label></td>
                        <td class="field-text"><select id="change_task_type_task_type_id" name="change_task_type_task_type_id">
                                <option value="">[Select]</option>
								<?php
								$taskTypeArray = array();
								$resultSet = executeQuery("select *,((select count(*) from task_type_users where task_type_id = task_types.task_type_id) + " .
									"(select count(*) from task_type_user_groups where task_type_id = task_types.task_type_id)) access_count from task_types " .
									"where client_id = ? and inactive = 0 and (task_type_id not in " .
									"(select task_type_id from task_type_attributes where task_attribute_id = (select task_attribute_id from task_attributes " .
									"where task_attribute_code = 'APPOINTMENT'))) order by sort_order,description", $GLOBALS['gClientId']);
								while ($row = getNextRow($resultSet)) {
									if ($row['access_count'] == 0 || $GLOBALS['gUserRow']['superuser_flag']) {
										$taskTypeArray[$row['task_type_id']] = $row['description'];
									} else {
										$accessSet = executeQuery("select task_type_id from task_types where task_type_id = ? and (task_type_id in " .
											"(select task_type_id from task_type_users where user_id = ?) or task_type_id in (select task_type_id from task_type_user_groups " .
											"where user_group_id in (select user_group_id from user_group_members where user_id = ?)))", $row['task_type_id'], $GLOBALS['gUserId'], $GLOBALS['gUserId']);
										if ($accessRow = getNextRow($accessSet)) {
											$taskTypeArray[$row['task_type_id']] = $row['description'];
										}
									}
								}
								foreach ($taskTypeArray as $taskTypeId => $description) {
									?>
                                    <option value="<?= $taskTypeId ?>"><?= htmlText($description) ?></option>
									<?php
								}
								?>
                            </select></td>
                    </tr>
                </table>
                <p id="_change_task_type_losses"></p>
            </form>
        </div>

        <div id="_task_dialog" class="dialog-box">
        </div>

        <div id="_filter_tasks_dialog" class="dialog-box">
            <form id="_filter_tasks_form" name="_filter_tasks_form">
				<?php
				$valuesArray = Page::getPagePreferences();
				?>
                <p><input type="checkbox" id="filter_on" name="filter_on" value="Y"<?= (empty($valuesArray['filter_on']) ? "" : " checked") ?>/> Match <select id="filter_match" name="filter_match">
                        <option value="any"<?= ($valuesArray['filter_match'] == "any" ? " selected" : "") ?>>Any</option>
                        <option value="all"<?= ($valuesArray['filter_match'] == "all" ? " selected" : "") ?>>All</option>
                        <option value="none"<?= ($valuesArray['filter_match'] == "none" ? " selected" : "") ?>>Not Any</option>
                    </select> of the following rules.
                </p>
                <table id="filter_selectors">
					<?php
					$taskTypeArray = array();
					$resultSet = executeQuery("select *,((select count(*) from task_type_users where task_type_id = task_types.task_type_id) + " .
						"(select count(*) from task_type_user_groups where task_type_id = task_types.task_type_id)) access_count from task_types " .
						"where client_id = ? and inactive = 0 and (task_type_id not in " .
						"(select task_type_id from task_type_attributes where task_attribute_id = (select task_attribute_id from task_attributes " .
						"where task_attribute_code = 'APPOINTMENT'))) order by sort_order,description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$taskTypeArray[$row['task_type_id']] = $row['description'];
					}
					$projectArray = array();
					if ($GLOBALS['gUserRow']['superuser_flag']) {
						$resultSet = executeQuery("select * from projects where client_id = ? and inactive = 0 and date_completed is null", $GLOBALS['gClientId']);
					} else {
						$resultSet = executeQuery("select * from projects where inactive = 0 and date_completed is null and (user_id = ? or leader_user_id = ? or " .
							"project_id in (select project_id from project_member_users where user_id = ?) or " .
							"project_id in (select project_id from project_member_user_groups where user_group_id in " .
							"(select user_group_id from user_group_members where user_id = ?))) order by sort_order,description", $GLOBALS['gUserId'], $GLOBALS['gUserId'], $GLOBALS['gUserId'], $GLOBALS['gUserId']);
					}
					while ($row = getNextRow($resultSet)) {
						$projectArray[$row['project_id']] = $row['description'];
					}
					$projectMilestoneArray = array();
					if ($GLOBALS['gUserRow']['superuser_flag']) {
						$resultSet = executeQuery("select *,(select description from projects where project_id = project_milestones.project_id) project_description from project_milestones " .
							"where project_id in (select project_id from projects where client_id = ? and inactive = 0 and date_completed is null)", $GLOBALS['gClientId']);
					} else {
						$resultSet = executeQuery("select *,(select description from projects where project_id = project_milestones.project_id) project_description from project_milestones " .
							"where project_id in (select project_id from projects where inactive = 0 and date_completed is null and (user_id = ? or leader_user_id = ? or " .
							"project_id in (select project_id from project_member_users where user_id = ?) or " .
							"project_id in (select project_id from project_member_user_groups where user_group_id in " .
							"(select user_group_id from user_group_members where user_id = ?)))) order by project_description,target_date", $GLOBALS['gUserId'], $GLOBALS['gUserId'], $GLOBALS['gUserId'], $GLOBALS['gUserId']);
					}
					while ($row = getNextRow($resultSet)) {
						$projectMilestoneArray[$row['project_milestone_id']] = $row['project_description'] . " - " . $row['description'];
					}
					$taskCategoryArray = array();
					$resultSet = executeQuery("select * from task_categories where client_id = ? and inactive = 0 and (user_id is null or user_id = ?) order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gUserId']);
					while ($row = getNextRow($resultSet)) {
						$taskCategoryArray[$row['task_category_id']] = $row['description'];
					}
					for ($x = 1; $x <= 5; $x++) {
						?>
                        <tr>
                            <td class="filter-field-cell"><select class="field-text filter-field" id="filter_field_<?= $x ?>" name="filter_field_<?= $x ?>" data-field_number="<?= $x ?>">
                                    <option value="">[Select]</option>
                                    <option value="task_type"<?= ($valuesArray['filter_field_' . $x] == "task_type" ? " selected" : "") ?>>Task Type</option>
                                    <option value="project"<?= ($valuesArray['filter_field_' . $x] == "project" ? " selected" : "") ?>>Project</option>
                                    <option value="project_milestone"<?= ($valuesArray['filter_field_' . $x] == "project_milestone" ? " selected" : "") ?>>Milestone</option>
                                    <option value="task_category"<?= ($valuesArray['filter_field_' . $x] == "task_category" ? " selected" : "") ?>>Task Category</option>
                                    <option value="task_group"<?= ($valuesArray['filter_field_' . $x] == "task_group" ? " selected" : "") ?>>Task Group</option>
                            </td>
                            <td class="filter-value-cell">
                                <select class="filter-task-type filter-value-<?= $x ?>" id="filter_task_type_<?= $x ?>" name="filter_task_type_<?= $x ?>">
                                    <option value="">[Select]</option>
									<?php
									foreach ($taskTypeArray as $taskTypeId => $description) {
										?>
                                        <option value="<?= $taskTypeId ?>"<?= ($valuesArray['filter_task_type_' . $x] == $taskTypeId ? " selected" : "") ?>><?= htmlText($description) ?></option>
										<?php
									}
									?>
                                </select>
                                <select class="filter-project filter-value-<?= $x ?>" id="filter_project_<?= $x ?>" name="filter_project_<?= $x ?>">
                                    <option value="">[None]</option>
									<?php
									foreach ($projectArray as $projectId => $description) {
										?>
                                        <option value="<?= $projectId ?>"<?= ($valuesArray['filter_project_' . $x] == $projectId ? " selected" : "") ?>><?= htmlText($description) ?></option>
										<?php
									}
									?>
                                </select>
                                <select class="filter-project-milestone filter-value-<?= $x ?>" id="filter_project_milestone_<?= $x ?>" name="filter_project_milestone_<?= $x ?>">
                                    <option value="">[None]</option>
									<?php
									foreach ($projectMilestoneArray as $projectMilestoneId => $description) {
										?>
                                        <option value="<?= $projectMilestoneId ?>"<?= ($valuesArray['filter_project_milestone_' . $x] == $projectMilestoneId ? " selected" : "") ?>><?= htmlText($description) ?></option>
										<?php
									}
									?>
                                </select>
                                <select class="filter-task-category filter-value-<?= $x ?>" id="filter_task_category_<?= $x ?>" name="filter_task_category_<?= $x ?>">
                                    <option value="">[None]</option>
									<?php
									foreach ($taskCategoryArray as $taskCategoryId => $description) {
										?>
                                        <option value="<?= $taskCategoryId ?>"<?= ($valuesArray['filter_task_category_' . $x] == $taskCategoryId ? " selected" : "") ?>><?= htmlText($description) ?></option>
										<?php
									}
									?>
                                </select>
                                <input class="filter-task-group filter-value-<?= $x ?>" type="text" id="filter_task_group_<?= $x ?>" name="filter_task_group_<?= $x ?>" size="20" value="<?= htmlText($valuesArray['task_group_' . $x]) ?>"/>
                            </td>
                        </tr>
					<?php } ?>
                </table>
                <table>
                    <tr>
                        <td class="field-text" colspan="2"><input type="checkbox"<?= (empty($valuesArray['filter_show_completed']) ? "" : " checked") ?> class="field-text" id="filter_show_completed" name="filter_show_completed" value="Y"/><label class="checkbox-label" for="filter_show_completed">Show Completed Tasks</label></td>
                    </tr>
                    <tr>
                        <td class="field-text" colspan="2"><input type="checkbox"<?= (empty($valuesArray['filter_show_responsible']) ? "" : " checked") ?> class="field-text" id="filter_show_responsible" name="filter_show_responsible" value="Y"/><label class="checkbox-label" for="filter_show_responsible">Show Tasks I Supervise</label></td>
                    </tr>
                    <tr>
                        <td class="field-label"><label for="filter_tab_name">Save as tab named</label></td>
                        <td class="field-text"><input type="text" id="filter_tab_name" name="filter_tab_name" size="20" maxlength="20"/></td>
                    </tr>
                </table>
            </form>
        </div>

        <div id="_work_flow_instance_dialog" class="dialog-box">
            <form id="_work_flow_instance_form" name="_work_flow_instance_form">
                <input type="hidden" id="work_flow_definition_id" name="work_flow_definition_id"/>
                <input type="hidden" id="work_flow_instance_id" name="work_flow_instance_id"/>
                <table>
                    <tr>
                        <td class="field-label"><label>Description</label></td>
                        <td class="field-text"><input type="text" disabled="disabled" size="40" id="wfi_description" name="wfi_description"/></td>
                    </tr>
                    <tr>
                        <td class="field-label"><label>Created By</label></td>
                        <td class="field-text"><input type="text" disabled="disabled" size="40" id="wfi_creator_user" name="wfi_creator_user"/></td>
                    </tr>
                    <tr>
                        <td class="field-label"><label for="wfi_responsible_user_id">Supervisor</label></td>
                        <td class="field-text"><select id="wfi_responsible_user_id" name="wfi_responsible_user_id">
                                <option value="">[None]</option>
								<?php
								$resultSet = executeQuery("select *,(select first_name from contacts where contact_id = users.contact_id) first_name," .
									"(select last_name from contacts where contact_id = users.contact_id) last_name from users where inactive = 0 and " .
									"administrator_flag = 1 order by first_name,last_name");
								while ($row = getNextRow($resultSet)) {
									?>
                                    <option value="<?= $row['user_id'] ?>"><?= htmlText(getDisplayName($row['contact_id'])) ?></option>
									<?php
								}
								?>
                            </select></td>
                    </tr>
                    <tr id="_wfi_project_row">
                        <td class="field-label"><label for="wfi_project">New Project Description</label></td>
                        <td class="field-text"><input type="text" size="30" class="field-text" id="wfi_project" name="wfi_project"/></td>
                    </tr>
                    <tr>
                        <td class="field-label"><label class="required-label" for="wfi_start_date">Start Date</label></td>
                        <td class="field-text"><input type="text" size="12" class="validate[required,custom[date]]" id="wfi_start_date" name="wfi_start_date"/></td>
                    </tr>
                    <tr>
                        <td class="field-label"><label for="wfi_work_flow_status_id">Status</label></td>
                        <td class="field-text"><select id="wfi_work_flow_status_id" name="wfi_work_flow_status_id">
                                <option value="">[None]</option>
								<?php
								$resultSet = executeQuery("select * from work_flow_status where inactive = 0 order by sort_order,description");
								while ($row = getNextRow($resultSet)) {
									?>
                                    <option value="<?= $row['work_flow_status_id'] ?>"><?= htmlText($row['description']) ?></option>
									<?php
								}
								?>
                            </select></td>
                    </tr>
                    <tr>
                        <td class="field-label"><label for="wfi_date_due">Date Due</label></td>
                        <td class="field-text"><input type="text" size="12" class="validate[custom[date]] datepicker" id="wfi_date_due" name="wfi_date_due"/></td>
                    </tr>
                    <tr>
                        <td class="field-label"><label for="wfi_date_completed">Date Completed</label></td>
                        <td class="field-text"><input type="text" size="12" class="validate[custom[date]] datepicker" id="wfi_date_completed" name="wfi_date_completed"/></td>
                    </tr>
                    <tr>
                        <td class="field-label"><label>Completed By</label></td>
                        <td class="field-text"><input type="text" disabled="disabled" size="40" id="wfi_completing_user" name="wfi_completing_user"/></td>
                    </tr>
                    <tr>
                        <td class="field-label"><label for="wfi_notes">Notes</label></td>
                        <td class="field-text"><textarea id="wfi_notes" name="wfi_notes"></textarea></td>
                    </tr>
                </table>
                <table id="_work_flow_instance_details" class="grid-table">
                </table>
            </form>
        </div>
		<?php
	}

	function createColumnFromData($row) {
		$thisColumn = new DataColumn(strtolower($row['task_data_definition_code']));
		$thisColumn->setControlValue("data_field", $this->getFieldFromDataType($row['data_type']));
		$thisColumn->setControlValue("form_label", $row['description']);
		$thisColumn->setControlValue("minimum_value", $row['minimum_value']);
		$thisColumn->setControlValue("maximum_value", $row['maximum_value']);
		$dataType = $row['data_type'];
		switch ($dataType) {
			case "varchar":
				if (empty($row['data_size'])) {
					$thisColumn->setControlValue("data_type", "text");
				} else {
					$thisColumn->setControlValue("data_type", "varchar");
					$thisColumn->setControlValue("maximum_length", $row['data_size']);
				}
				break;
			case "html":
				$thisColumn->setControlValue("data_type", "text");
				$thisColumn->setControlValue("wysiwyg", true);
				break;
			case "int":
				$thisColumn->setControlValue("data_type", "int");
				break;
			case "decimal":
				$thisColumn->setControlValue("data_type", "decimal");
				$thisColumn->setControlValue("decimal_places", "2");
				break;
			case "date":
				$thisColumn->setControlValue("data_type", "date");
				break;
			case "tinyint":
				$thisColumn->setControlValue("data_type", "tinyint");
				break;
			case "select";
				$thisColumn->setControlValue("data_type", "select");
				$choices = array();
				if (!empty($row['choices'])) {
					$choiceArray = getContentLines($row['choices']);
					foreach ($choiceArray as $choice) {
						$choices[$choice] = $choice;
					}
				} else if (!empty($row['table_name'])) {
					if (empty($row['column_name'])) {
						$row['column_name'] = "description";
					}
					$choicesTable = new DataTable($row['table_name']);
					$resultSet = executeQuery("select * from " . $row['table_name'] . " order by " . $row['column_name']);
					while ($choiceRow = getNextRow($resultSet)) {
						$choices[$choiceRow[$choicesTable->getPrimaryKey()]] = $choiceRow[$row['column_name']];
					}
				}
				$thisColumn->setControlValue("choices", $choices);
				break;
			case "image";
				$thisColumn->setControlValue("data_type", "image");
				$thisColumn->setControlValue("subtype", "image");
				break;
		}
		$thisColumn->setControlValue("not_null", $row['required']);
		$thisColumn->setControlValue("column_name", "task_data_definitions-" . strtolower($row['task_data_definition_code']) . "-" . $row['task_data_definition_id']);
		return $thisColumn;
	}

	function createControl($thisColumn) {
		$thisColumn->setControlValue("tabindex", "");
		$templateData = "";
		?>
        <tr>
            <td class="field-label"><?php if ($thisColumn->getControlValue("data_type") != "tinyint") { ?><label for='<?= $thisColumn->getControlValue("column_name") ?>' class="<?= ($thisColumn->getControlValue("not_null") ? "required-label" : "") ?>"><?= htmlText($thisColumn->getControlValue("form_label")) ?></label><?php } ?></td>
            <td class="field-text"><?= $thisColumn->getControl($this) ?></td>
        </tr>
		<?php
		if ($thisColumn->getControlValue('data_type') == "custom_control" || $thisColumn->getControlValue('data_type') == "custom") {
			$controlClass = $thisColumn->getControlValue("control_class");
			$customControl = new $controlClass($thisColumn, $this->iPageObject);
			$templateData = $customControl->getTemplate();
		}
		return $templateData;
	}

	function createEditableList($columnArray, $controlName) {
		$columnCount = 0;
		?>
        <table class="editable-list" id="_<?= $controlName ?>_table" data-row_number="1">
            <tr class="table-header">
				<?php
				foreach ($columnArray as $thisColumn) {
					$columnCount++;
					?>
                    <th><?= htmlText($thisColumn->getControlValue("form_label")) ?></th>
					<?php
				}
				?>
                <th class="editable-list-row-control"></th>
            </tr>
            <tr class="add-row">
                <th colspan="<?= $columnCount ?>">&nbsp;</th>
                <th class="editable-list-row-control">
                    <button class="no-ui editable-list-add" data-list_identifier="<?= $controlName ?>" id="_<?= $controlName ?>_add"><span class='fad fa-plus-octagon'></span></button>
                </th>
            </tr>
        </table>
		<?php
		ob_start();
		?>
        <table>
            <tbody id="_<?= $controlName ?>_new_row">
            <tr id="_<?= $controlName ?>_row-%rowId%">
				<?php
				foreach ($columnArray as $thisColumn) {
					$thisColumn->setControlValue("column_name", $controlName . "_" . $thisColumn['column_name'] . "-%rowId%");
					$thisColumn->setControlValue("tabindex", "");
					?>
                    <td class="align-center"><?= $thisColumn->getControl($this) ?></td>
					<?php
				}
				?>
                <td class="editable-list-row-control">
                    <button class="no-ui editable-list-remove" data-list_identifier="<?= $controlName ?>"><img src="images/listdelete.png" alt="Delete Row"></button>
                </td>
            </tr>
            </tbody>
        </table>
		<?php
		return ob_get_clean();
	}

	function getFieldFromDataType($dataType) {
		switch ($dataType) {
			case "int":
				return "integer_data";
			case "decimal":
				return "number_data";
			case "date":
				return "date_data";
			case "tinyint":
				return "integer_data";
			case "image":
				return "image_id";
			default:
				return "text_data";
		}
		return "text_data";
	}

	function categoryChoices() {
		$categoryChoices = array();
		$resultSet = executeQuery("select task_category_id,description,inactive from task_categories where client_id = ? and " .
			"(user_id is null or user_id = ?) and inactive = 0 order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gUserId']);
		while ($row = getNextRow($resultSet)) {
			$categoryChoices[$row['task_category_id']] = array("key_value" => $row['task_category_id'], "description" => $row['description'], "inactive" => $row['inactive'] == 1);
		}
		return $categoryChoices;
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
