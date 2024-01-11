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

$GLOBALS['gPageCode'] = "PROJECTPAGE";
require_once "shared/startup.inc";

if (empty($_GET['ajax'])) {
	$projectId = "";
	$query = "select * from projects where project_id = ?";
	$parameters = array($_GET['id']);
	if (!$GLOBALS['gUserRow']['superuser_flag']) {
		$query .= " and ((members_only = 1 and (user_id = ? or leader_user_id = ? or project_id in (select project_id from project_member_users where user_id = ?) or " .
			"project_id in (select project_id from project_member_user_groups where user_group_id in (select user_group_id from user_group_members where user_id = ?))))";
		$parameters[] = $GLOBALS['gUserId'];
		$parameters[] = $GLOBALS['gUserId'];
		$parameters[] = $GLOBALS['gUserId'];
		$parameters[] = $GLOBALS['gUserId'];
		$query .= " or (members_only = 0 and internal_use_only = 0)";
		if ($GLOBALS['gInternalConnection']) {
			$query .= " or (members_only = 0 and internal_use_only = 1)";
		}
		$query .= ")";
	}
	$resultSet = executeQuery($query, $parameters);
	if ($row = getNextRow($resultSet)) {
		$projectId = $row['project_id'];
	}
	if (empty($projectId)) {
		header("Location: /index.php");
		exit;
	}
}

class ProjectPage extends Page {

	var $iProjectId = "";

	function setup() {
		$this->iProjectId = $_GET['id'];
		if (empty($this->iProjectId)) {
			$this->iProjectId = $_GET['project_id'];
		}
		if ($GLOBALS['gLoggedIn']) {
			$resultSet = executeQuery("select * from projects where project_id = ? and (user_id = ? or leader_user_id = ? or project_id in " .
				"(select project_id from project_member_users where user_id = ?) or project_id in (select project_id from project_member_user_groups " .
				"where user_group_id in (select user_group_id from user_group_members where user_id = ?)))",
				$this->iProjectId, $GLOBALS['gUserId'], $GLOBALS['gUserId'], $GLOBALS['gUserId'], $GLOBALS['gUserId']);
			if ((!$row = getNextRow($resultSet)) && !$GLOBALS['gUserRow']['superuser_flag']) {
				$GLOBALS['gPermissionLevel'] = _READONLY;
			}
		}
	}

	function headerIncludes() {
		$cssFileId = getFieldFromId("css_file_id", "project_types", "project_type_id", getFieldFromId("project_type_id", "projects", "project_id", $this->iProjectId));
		if (!empty($cssFileId)) {
			?>
            <link type="text/css" rel="stylesheet" property="stylesheet" href="<?= createCSSFile($cssFileId) ?>"/>
			<?php
		}
		$cssFileId = getFieldFromId("css_file_id", "projects", "project_id", $this->iProjectId);
		if (!empty($cssFileId)) {
			?>
            <link type="text/css" rel="stylesheet" property="stylesheet" href="<?= createCSSFile($cssFileId) ?>"/>
			<?php
		}
	}

	function massageUrlParameters() {
		$_GET['url_subpage'] = $_GET['url_page'];
		$_GET['url_page'] = "show";
		$_GET['primary_id'] = $this->iProjectId;
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_help_desk_entry":
				$pagePreferences = Page::getPagePreferences();
				$pagePreferences['help_desk_type_id'] = $_POST['help_desk_type_id'];
				$pagePreferences['help_desk_category_id'] = $_POST['help_desk_category_id'];
				$pagePreferences['user_id'] = $_POST['user_id'];
				$pagePreferences['user_group_id'] = $_POST['user_group_id'];
				$pagePreferences['project_milestone_id'] = $_POST['project_milestone_id'];
				$pagePreferences['date_due'] = $_POST['date_due'];
				$this->iDatabase->startTransaction();
				$_POST['contact_id'] = $GLOBALS['gUserRow']['contact_id'];
				$_POST['project_id'] = $_GET['project_id'];
				$helpDeskEntry = new HelpDesk();
				$helpDeskEntry->addSubmittedData($_POST);
				if (!$helpDeskEntryId = $helpDeskEntry->save()) {
					$returnArray['error_message'] = $helpDeskEntry->getErrorMessage();
					$this->iDatabase->rollbackTransaction();
				} else {
					$this->iDatabase->commitTransaction();
				}
				$helpDeskEntry->addFiles();
				ob_start();
				?>
                <tr class='milestone-<?= $_POST['project_milestone_id'] ?> editable-ticket' data-help_desk_entry_id='<?= $helpDeskEntryId ?>'>
                    <td><?= htmlText($_POST['description']) ?></td>
                    <td><?= (empty($_POST['user_id']) ? "" : getUserDisplayName($_POST['user_id'])) ?></td>
                    <td class='align-center'><?= (empty($_POST['date_due']) ? "" : date("m/d/Y", strtotime($_POST['date_due']))) ?></td>
                    <td class='align-center date-completed' data-help_desk_entry_id='<?= $helpDeskEntryId ?>' id="date_completed_<?= $helpDeskEntryId ?>"><input title='click to mark ticket completed' type='checkbox'/></td>
                    <td><?= htmlText(getFieldFromId("description", "project_milestones", "project_milestone_id", $_POST['project_milestone_id'])) ?></td>
                </tr>
				<?php
				$returnArray['ticket_row'] = ob_get_clean();
				$description = "Ticket '" . $_POST['description'] . "' Added";
				$resultSet = executeQuery("insert into project_log (project_id,user_id,content) values (?,?,?)",
					$_GET['project_id'], $GLOBALS['gUserId'], $description);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
				} else {
					$logId = $resultSet['insert_id'];
					ob_start();
					$this->getProjectLog(array("project_id" => $_GET['project_id'], "log_id" => $logId));
					$returnArray['log_entry'] = ob_get_clean();
				}
				ajaxResponse($returnArray);
				break;
			case "get_help_desk_categories":
				$resultSet = executeQuery("select * from help_desk_categories where help_desk_category_id in (select help_desk_category_id from help_desk_type_categories where help_desk_type_id = ?) and " .
					"client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $_GET['help_desk_type_id'], $GLOBALS['gClientId']);
				$returnArray['help_desk_categories'] = array();
				while ($row = getNextRow($resultSet)) {
					$returnArray['help_desk_categories'][] = array("key_value" => $row['help_desk_category_id'], "description" => $row['description']);
				}
				ajaxResponse($returnArray);
				break;
			case "add_file":
				if (array_key_exists("new_file_file", $_FILES) && !empty($_FILES['new_file_file']['name'])) {
					$originalFilename = $_FILES['new_file_file']['name'];
					if (array_key_exists($_FILES['new_file_file']['type'], $GLOBALS['gMimeTypes'])) {
						$extension = $GLOBALS['gMimeTypes'][$_FILES['new_file_file']['type']];
					} else {
						$fileNameParts = explode(".", $_FILES['new_file_file']['name']);
						$extension = $fileNameParts[count($fileNameParts) - 1];
					}
					$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
					if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
						$maxDBSize = 1000000;
					}
					if ($_FILES['new_file_file']['size'] < $maxDBSize) {
						$fileContent = file_get_contents($_FILES['new_file_file']['tmp_name']);
						$osFilename = "";
					} else {
						$fileContent = "";
						$osFilename = "/documents/tmp." . $extension;
					}
					$fileSet = $this->iDatabase->executeQuery("insert into files (file_id,client_id,description,date_uploaded," .
						"filename,extension,file_content,os_filename,public_access,all_user_access,administrator_access," .
						"sort_order,version) values (null,?,?,now(),?,?,?,?,0,0,1,0,1)", $GLOBALS['gClientId'], $_POST['new_file_description'],
						$originalFilename, $extension, $fileContent, $osFilename);
					if (!empty($fileSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $fileSet['sql_error']);
						ajaxResponse($returnArray);
						break;
					}
					$fileId = $fileSet['insert_id'];
					if (!empty($osFilename)) {
						putExternalFileContents($fileId, $extension, file_get_contents($_FILES['new_file_file']['tmp_name']));
					}
					executeQuery("insert into project_files (project_id,description,file_id) values (?,?,?)", $_GET['project_id'], $_POST['new_file_description'], $fileId);
					$description = "File '" . $_POST['new_file_description'] . "' Added";
					$resultSet = executeQuery("insert into project_log (project_id,user_id,content) values (?,?,?)",
						$_GET['project_id'], $GLOBALS['gUserId'], $description);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					} else {
						$logId = $resultSet['insert_id'];
						ob_start();
						$this->getProjectLog(array("project_id" => $_GET['project_id'], "log_id" => $logId));
						$returnArray['log_entry'] = ob_get_clean();
					}
				}
				ajaxResponse($returnArray);
				break;
			case "add_image":
				if (array_key_exists("new_image_file", $_FILES) && !empty($_FILES['new_image_file']['name'])) {
					$imageId = createImage("new_image_file", array("description" => $_POST['new_image_description']));
					if ($imageId === false) {
						$returnArray['error_message'] = "Error writing image";
						ajaxResponse($returnArray);
						break;
					}
					executeQuery("insert into project_images (project_id,description,image_id) values (?,?,?)", $_GET['project_id'], $_POST['new_image_description'], $imageId);
					$description = "Image '" . $_POST['new_image_description'] . "' Added";
					$resultSet = executeQuery("insert into project_log (project_id,user_id,content) values (?,?,?)",
						$_GET['project_id'], $GLOBALS['gUserId'], $description);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					} else {
						$logId = $resultSet['insert_id'];
						ob_start();
						$this->getProjectLog(array("project_id" => $_GET['project_id'], "log_id" => $logId));
						$returnArray['log_entry'] = ob_get_clean();
					}
				}
				ajaxResponse($returnArray);
				break;
			case "set_notification":
				$notify = ($_GET['notify'] == "Y");
				$projectNotificationExclusionId = getFieldFromId("project_notification_exclusion_id", "project_notification_exclusions", "user_id", $GLOBALS['gUserId'], "project_id = ?", $_GET['project_id']);
				if (empty($projectNotificationExclusionId)) {
					if (!$notify) {
						$resultSet = executeQuery("insert ignore into project_notification_exclusions (project_id,user_id) values (?,?)",
							$_GET['project_id'], $GLOBALS['gUserId']);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						}
					}
				} else {
					if ($notify) {
						$resultSet = executeQuery("delete from project_notification_exclusions project_notification_exclusion_id = ?",
							$projectNotificationExclusionId);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						}
					}
				}
				if (empty($returnArray['error_message'])) {
					$description = "Requested to " . ($notify ? "receive" : "stop receiving") . " the daily project log email.";
					$resultSet = executeQuery("insert into project_log (project_id,user_id,content) values (?,?,?)",
						$_GET['project_id'], $GLOBALS['gUserId'], $description);
					$logId = $resultSet['insert_id'];
				}
				if (!empty($logId)) {
					ob_start();
					$this->getProjectLog(array("project_id" => $_GET['project_id'], "log_id" => $logId));
					$returnArray['log_entry'] = ob_get_clean();
				}
				ajaxResponse($returnArray);
				break;
			case "complete_milestone":
				$returnArray = array();
				$projectMilestoneId = $_GET['project_milestone_id'];
				$resultSet = executeQuery("update project_milestones set date_completed = now() where date_completed is null and project_milestone_id = ?",
					$projectMilestoneId);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
				} else {
					$dateCompleted = getFieldFromId("date_completed", "project_milestones", "project_milestone_id", $projectMilestoneId);
					if (!empty($dateCompleted)) {
						$returnArray['date_completed'] = date("m/d/Y", strtotime($dateCompleted));
					}
					if ($resultSet['affected_rows'] > 0) {
						$description = "Milestone '" . getFieldFromId("description", "project_milestones", "project_milestone_id", $projectMilestoneId) . "' was marked as completed";
						$resultSet = executeQuery("insert into project_log (project_id,user_id,content) values (?,?,?)",
							$_GET['project_id'], $GLOBALS['gUserId'], $description);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						} else {
							$logId = $resultSet['insert_id'];
							ob_start();
							$this->getProjectLog(array("project_id" => $_GET['project_id'], "log_id" => $logId));
							$returnArray['log_entry'] = ob_get_clean();
						}
					}
				}
				ajaxResponse($returnArray);
				break;
			case "set_ticket_completed":
				$helpDeskEntryId = getFieldFromId("help_desk_entry_id", "help_desk_entries", "help_desk_entry_id", $_GET['help_desk_entry_id'], "project_id = ?", $_GET['project_id']);
				if (empty($helpDeskEntryId)) {
					$returnArray['error_message'] = "Help Desk Entry not found: " . jsonEncode($_GET);
				} else {
					$helpDesk = new HelpDesk($helpDeskEntryId);
					$helpDesk->markClosed();
					$returnArray['time_closed'] = date("m/d/Y g:i a");
					$returnArray['help_desk_entry_id'] = $helpDeskEntryId;
				}
				ajaxResponse($returnArray);
				break;
			case "get_project_log":
				$returnArray = array();
				ob_start();
				$this->getProjectLog($_GET['project_id']);
				$returnArray['project_log'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "save_log":
				$returnArray = array();
				$parentLogId = getFieldFromId("log_id", "project_log", "log_id", $_POST['parent_log_id'], "project_id = ?", $_GET['project_id']);
				$content = $_POST['log_content'];
				$resultSet = executeQuery("insert into project_log (project_id,parent_log_id,user_id,content) values (?,?,?,?)",
					$_GET['project_id'], $parentLogId, $GLOBALS['gUserId'], $content);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
				} else {
					$logId = $resultSet['insert_id'];
					ob_start();
					$this->getProjectLog(array("project_id" => $_GET['project_id'], "log_id" => $logId));
					$returnArray['log_entry'] = ob_get_clean();
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function getProjectLog($parameters) {
		if (!is_array($parameters)) {
			$parameters = array("project_id" => $parameters);
		}
		$projectId = $parameters['project_id'];
		$parentLogId = $parameters['parent_log_id'];
		$level = (array_key_exists("level", $parameters) ? $parameters['level'] : 0);
		$logId = $parameters['log_id'];
		$resultSet = executeQuery("select * from project_log where project_id = ? and parent_log_id <=> ? order by log_id desc", $projectId, $parentLogId);
		while ($row = getNextRow($resultSet)) {
			if (empty($logId) || $row['log_id'] == $logId) {
				?>
                <p id="project_log_<?= $row['log_id'] ?>" data-log_id="<?= $row['log_id'] ?>" class="log-entry <?= ($level == 0 ? "" : "message-reply") ?>" style="margin-left: <?= ($level * 20) ?>px;"><span class='log-date<?= (empty($row['user_id']) ? "" : " color-orange") ?>'><?= date("m/d/Y g:i a", strtotime($row['log_time'])) ?><?= (empty($row['user_id']) ? "" : " by " . getUserDisplayName($row['user_id'])) ?></span>: <?= htmlText($row['content']) ?><?php if ($GLOBALS['gPermissionLevel'] > 1) { ?><a href='' class='add-reply'>reply</a><?php } ?></p>
				<?php
			}
			$this->getProjectLog(array("project_id" => $projectId, "parent_log_id" => $row['log_id'], "level" => ($level + 1), "log_id" => $logId));
		}
	}

	function internalCSS() {
		?>
        <style>
            #description_section ol {
                display: block;
                list-style-type: decimal;
                margin-top: 1em;
                margin-bottom: 1rem;
                margin-left: 0;
                margin-right: 0;
                padding-left: 40px;
            }
            #description_section ul {
                display: block;
                list-style-type: disc;
                margin-top: 1em;
                margin-bottom: 1rem;
                margin-left: 0;
                margin-right: 0;
                padding-left: 40px;
            }
            #new_image_description {
                margin-left: 20px;
            }

            #new_image_file {
                margin-left: 20px;
                margin-right: 20px;
            }

            #new_file_description {
                margin-left: 20px;
            }

            #new_file_file {
                margin-left: 20px;
                margin-right: 20px;
            }

            div.content-section {
                margin-bottom: 15px;
            }

            td {
                font-size: 12px;
            }

            .line-label {
                text-align: right;
                padding-right: 10px;
                font-weight: bold;
            }

            .line-label:after {
                content: ":"
            }

            div#_image_gallery {
                width: 950px;
            }

            div#_image_gallery table {
                width: auto;
            }

            div#_image_gallery td {
                padding: 10px;
            }

            div#_image_gallery p {
                margin: 0;
                padding: 6px 0 5px;
                font-size: 8px;
            }

            .gallery-image-div {
                background-repeat: no-repeat;
                border: 1px solid rgb(200, 200, 200);
                -moz-box-shadow: 3px 3px 5px 0 rgb(100, 100, 100);
                -webkit-box-shadow: 3px 3px 5px 0 rgb(100, 100, 100);
                box-shadow: 3px 3px 5px 0 rgb(100, 100, 100);
                margin-left: auto;
                margin-right: auto;
                overflow: hidden;
                background-size: cover;
                background-position: center;
                width: 70px;
                height: 70px;
            }

            .milestone-row {
                cursor: pointer;
            }

            .highlighted-ticket {
                background-color: rgb(140, 240, 60);
            }

            #_add_project_log {
                width: 650px;
                border: 1px solid rgb(200, 200, 200);
                padding: 20px;
                background-color: rgb(220, 220, 220);
                margin: 10px;
            }

            #log_content {
                width: 600px;
                height: 100px;
                max-width: 100%;
            }

            #_ticket_list, #_milestone_list td {
                height: 20px;
            }

            .message-reply:before {
                content: "\21AA\00A0"
            }

            .log-date {
                font-weight: bold;
            }

            a.add-reply {
                padding-left: 20px;
                font-weight: normal;
                font-size: 10px;
            }

            #_details td {
                padding-bottom: 5px;
            }

            .editable-ticket:hover {
                background-color: rgb(250, 250, 200);
                cursor: pointer;
            }

            .button-paragraph {
                padding-top: 20px;
            }

            h1 {
                color: #000000;
                font-size: 26px;
                text-align: center;
                text-transform: uppercase;
            }

            h3 {
                background: #617983;
                color: #ffffff;
                font-family: Gotham, "Helvetica Neue", Helvetica, Arial, sans-serif;
                padding: 12px;
                margin: -20px -20px 25px;
                text-align: center;
                text-transform: uppercase;
                border-radius: 2px 2px 0 0;
            }

            div.content-section {
                background: #f4f4f2;
                margin-bottom: 35px;
                padding: 20px;
                border-radius: 3px;
            }

            .grid-table th {
                background: #ddd;
                border: 1px solid #bbb;
                color: #555;
                font-weight: bold;
                padding: 5px 10px;
                text-transform: uppercase;
            }

            .grid-table td {
                border: 1px solid #ddd;
                padding: 5px 10px;
            }

            #admin_section {
                margin: 0 0 10px 0;
                padding: 0;
            }

            #admin_section {
                background: none;
            }

            #files_section, #image_section {
                width: 48%;
            }

            #files_section {
                float: right;
            }

            #files_section a {
                color: #333;
            }

            #files_section a:hover {
                font-weight: bolder;
                text-decoration: none;
                color: #aaa;
            }

            #image_section {
                float: left;
            }

            #files_section .fa.fa-download {
                padding: 0 10px;
            }

            #new_file_file, #new_image_file {
                display: block;
                margin: 10px auto;
            }

            #files_section .ui-button, #image_section .ui-button {
                display: block;
                margin: auto;
            }

            #ticket_section {
                clear: both;
            }

            #_new_image, #_new_file {
                border-top: 1px solid #ddd;
                padding-top: 15px;
                margin-top: 10px;
            }

            div#_image_gallery td {
                background: white;
                border: solid 1px #ececec;
                border-radius: 2px;
            }

            .gallery-image-div {
                box-shadow: none;
            }

            .subheader, form#_project_log_form .field-label {
                color: #333;
            }

            .log-entry {
                border-bottom: 1px solid #ddd;
                padding-bottom: 10px;
            }

            #_project_log .log-entry {
                color: #555;
            }

            a.add-reply {
                color: #333;
            }
        </style>
		<?php
	}

	function mainContent() {
		$resultSet = executeQuery("select * from projects where project_id = ?", $this->iProjectId);
		$projectRow = getNextRow($resultSet);
		?>
        <input type="hidden" id="project_id" value="<?= $this->iProjectId ?>"/>
		<?php
		if (canAccessPageCode("PROJECTMAINT") && $GLOBALS['gPermissionLevel'] > 1) {
			?>
            <div class="content-section" id="admin_section">
                <button id="go_to_admin">Go To Admin Page</button>
            </div>
			<?php
		}
		?>
        <div class="content-section" id="description_section">
            <h1><?= htmlText($projectRow['description']) ?></h1>
			<?= $projectRow['content'] ?>
        </div>
        <div class="content-section" id="details_section">
            <table id="_details">
                <tr>
                    <td class="line-label">Project Leader</td>
                    <td><?= getUserDisplayName($projectRow['leader_user_id']) ?></td>
                </tr>
                <tr>
                    <td class="line-label">Project Members</td>
					<?php
					$membersArray = array();
					$resultSet = executeQuery("select user_id from users where inactive = 0 and " .
						"(user_id = (select user_id from projects where project_id = ?) or " .
						"user_id = (select leader_user_id from projects where project_id = ?) or " .
						"user_id in (select user_id from project_member_users where project_id = ?) or " .
						"user_id in (select user_id from user_group_members where user_group_id in (select user_group_id from project_member_user_groups " .
						"where project_id = ?)))", $projectRow['project_id'], $projectRow['project_id'], $projectRow['project_id'], $projectRow['project_id']);
					while ($row = getNextRow($resultSet)) {
						$membersArray[$row['user_id']] = getUserDisplayName($row['user_id']);
					}
					asort($membersArray);
					?>
                    <td><?= htmlText(implode(", ", $membersArray)) ?></td>
                </tr>
				<?php
				$userId = getFieldFromId("user_id", "project_notification_exclusions", "project_id", $this->iProjectId, "user_id = ?", $GLOBALS['gUserId']);
				?>
				<?php if ($GLOBALS['gLoggedIn'] && $GLOBALS['gUserRow']['administrator_flag']) { ?>
                    <tr>
                        <td>&nbsp;</td>
                        <td><input type="checkbox" id="receive_notifications" name="receive_notifications" value="Y"<?= (empty($userId) ? " checked" : "") ?> /><label class="checkbox-label" for="receive_notifications">Receive Daily Project Log Email</label></td>
                    </tr>
				<?php } ?>
            </table>
        </div>
        <div class="content-section" id="image_section">
            <h3>Images</h3>
            <div id="_image_gallery">
                <table>
                    <tr>
						<?php
						$columnCount = 0;
						$resultSet = executeQuery("select images.image_id,images.description from images,project_images where images.image_id = project_images.image_id and project_id = ? order by project_image_id", $projectRow['project_id']);
						while ($row = getNextRow($resultSet)) {
							if ($columnCount >= 9) {
								$columnCount = 0;
								echo "</tr><tr>";
							}
							$columnCount++;
							?>
                            <td class="align-center size-9-point">
                                <a href="<?= getImageFilename($row['image_id'], array("use_cdn" => true)) ?>" class="pretty-photo" title="<?= $row['description'] ?>">
                                    <div class="gallery-image-div" style="background-image: url('<?= getImageFilename($row['image_id'], array("use_cdn" => true)) ?>');">
                                    </div>
                                </a>
                                <p><?= $row['description'] ?></p>
                            </td>
							<?php
						}
						?>
                    </tr>
                </table>
            </div>
            <div id="_new_image">
                <form id="_new_image_form" enctype='multipart/form-data'>
                    <p><label for="new_image_description">Add Image: Description</label><input type="text" size="40" maxlength="255" id="new_image_description" name="new_image_description" class="field-text validate[required]">
                        <input type="file" id="new_image_file" name="new_image_file" class="field-text validate[required]">
                        <button id="upload_new_image">Add Image</button>
                    </p>
                </form>
            </div>
        </div>
        <div class="content-section" id="files_section">
            <h3>Files</h3>
			<?php
			$resultSet = executeQuery("select * from project_files where project_id = ? order by project_file_id", $projectRow['project_id']);
			while ($row = getNextRow($resultSet)) {
				?>
                <p><a href="download.php?file_id=<?= $row['file_id'] ?>"><span class="fa fa-download"></span><?= htmlText($row['description']) ?></a></p>
				<?php
			}
			?>
            <div id="_new_file">
                <form id="_new_file_form" enctype='multipart/form-data'>
                    <p><label for="new_file_description">Add File: Description</label><input type="text" size="40" maxlength="255" id="new_file_description" name="new_file_description" class="field-text validate[required]">
                        <input type="file" id="new_file_file" name="new_file_file" class="field-text validate[required]">
                        <button id="upload_new_file">Add File</button>
                    </p>
                </form>
            </div>
        </div>
        <div class='clear-div'></div>
		<?php
		$resultSet = executeQuery("select * from project_milestones where project_id = ? order by days_before desc,project_milestone_id", $projectRow['project_id']);
		if ($resultSet['row_count'] > 0) {
			?>
            <div class="content-section" id="milestone_section">
                <h3>Milestones</h3>
                <p>Click on milestone to highlight tickets within that milestone</p>
                <table class="grid-table" id="_milestone_list">
                    <tr>
                        <th>Description</th>
                        <th>Target Date</th>
                        <th>Completed</th>
                        <th>Notes</th>
                        <th></th>
                    </tr>
					<?php
					$resultSet = executeQuery("select * from project_milestones where project_id = ? order by project_milestone_id", $projectRow['project_id']);
					while ($row = getNextRow($resultSet)) {
						?>
                        <tr class="milestone-row" id="milestone_<?= $row['project_milestone_id'] ?>" data-project_milestone_id="<?= $row['project_milestone_id'] ?>">
                            <td class="milestone-description"><?= htmlText($row['description']) ?></td>
                            <td class='align-center'><?= date("m/d/Y", strtotime($projectRow['date_due'] . " -" . $row['days_before'] . " days")) ?></td>
                            <td class='align-center' id="milestone_complete_<?= $row['project_milestone_id'] ?>"><?= (empty($row['date_completed']) ? ($GLOBALS['gPermissionLevel'] > 1 ? "<input type='checkbox' class='milestone-complete' value='Y' />" : "") : date("m/d/Y", strtotime($row['date_completed']))) ?></td>
                            <td><?= (isHtml($row['notes']) ? $row['notes'] : makeHtml($row['notes'])) ?></td>
                            <td></td>
                        </tr>
						<?php
					}
					?>
                </table>
            </div>
			<?php
		}
		?>
        <div class="content-section" id="ticket_section">
            <h3>Tickets</h3>
            <table class="grid-table header-sortable" id="_ticket_list">
                <tr class='header-row'>
                    <th>ID</th>
                    <th>Description</th>
                    <th>Assigned To</th>
                    <th>Date Due</th>
                    <th>Completed</th>
                    <th>Milestone</th>
                </tr>
				<?php
				$resultSet = executeQuery("select * from help_desk_entries where project_id = ?", $projectRow['project_id']);
				$helpDeskEntries = array();
				while ($row = getNextRow($resultSet)) {
					$row['sort_date_due'] = $row['date_due'];
					if (empty($row['sort_date_due']) && !empty($row['project_milestone_id'])) {
						$projectMilestoneRow = getRowFromId("project_milestones", "project_milestone_id", $row['project_milestone_id']);
						$row['sort_date_due'] = date("Y-m-d", strtotime($projectRow['date_due'] . " -" . $projectMilestoneRow['days_before'] . " days"));
					}
					$helpDeskEntries[] = $row;
				}
				usort($helpDeskEntries, array($this, "sortTickets"));
				foreach ($helpDeskEntries as $row) {
					$userGroupMemberId = getFieldFromId("user_id", "user_group_members", "user_group_id", $row['user_group_id'], "user_id = ?", $GLOBALS['gUserId']);
					$editable = false;
					if ($GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserId'] == $row['user_id'] || (empty($row['user_id']) && !empty($userGroupMemberId)) ||
						(empty($row['user_id']) && empty($row['user_group_id']) && $row['contact_id'] == $GLOBALS['gUserRow']['contact_id'])) {
						$editable = true;
					}
					?>
                    <tr class='milestone-<?= $row['project_milestone_id'] ?><?= ($editable ? " editable-ticket' data-help_desk_entry_id='" . $row['help_desk_entry_id'] : "") ?>'>
                        <td><?= $row['help_desk_entry_id'] ?></td>
                        <td><?= htmlText($row['description']) ?></td>
                        <td><?= (empty($row['user_id']) ? "" : getUserDisplayName($row['user_id'])) ?></td>
                        <td class='align-center'><?= (empty($row['date_due']) ? (empty($row['milestone_date']) ? "" : "[" . date("m/d/Y", strtotime($row['milestone_date'])) . "]") : date("m/d/Y", strtotime($row['date_due']))) ?></td>
                        <td class='align-center<?= (empty($row['time_closed']) ? " date-completed' data-help_desk_entry_id='" . $row['help_desk_entry_id'] : "") ?>'
                            id="date_completed_<?= $row['help_desk_entry_id'] ?>"><?= (empty($row['time_closed']) ? ($GLOBALS['gPermissionLevel'] > 1 ? "<input title='click to mark ticket completed' type='checkbox' />" : "") : date("m/d/Y g:i a", strtotime($row['time_closed']))) ?></td>
                        <td><?= htmlText(getFieldFromId("description", "project_milestones", "project_milestone_id", $row['project_milestone_id'])) ?></td>
                    </tr>
					<?php
				}
				?>
            </table>
            <p class="button-paragraph">
                <button id="add_ticket">Add Ticket</button>
            </p>
        </div>
        <div class="content-section" id="log_section">
            <h3>Project Log</h3>

            <div id="_project_log">
            </div>
			<?php if ($GLOBALS['gPermissionLevel'] > 1) { ?>
                <div id="_add_project_log">
                    <h4 id="project_log_title">Add a new message</h4>
                    <form name="_project_log_form" id="_project_log_form">
                        <input type="hidden" id="parent_log_id" name="parent_log_id" value=""/>
                        <div class='form-line'>
                            <label>Message</label>
                            <textarea id="log_content" name="log_content"></textarea>
                        </div>
                        <p>
                            <button id="_add_message">Add Message</button>
                        </p>
                    </form>
                </div>
			<?php } ?>

        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#_page_header_wrapper").hide();
            $("#go_to_admin").click(function () {
                document.location = "/projectmaintenance.php?url_page=show&primary_id=" + $("#project_id").val();
            });

            $("#help_desk_type_id").change(function () {
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_help_desk_categories&help_desk_type_id=" + $(this).val() + "&project_id=" + $("#project_id").val(), function(returnArray) {
                        $("#help_desk_category_id").find("option[value!='']").remove();
                        if ("help_desk_categories" in returnArray) {
                            for (const i in returnArray['help_desk_categories']) {
                                const thisOption = $("<option></option>").attr("value", returnArray['help_desk_categories'][i]['key_value']).text(returnArray['help_desk_categories'][i]['description']);
                                $("#help_desk_category_id").append(thisOption);
                            }
                        }
                    });
                }
            });

            $("#upload_new_image").click(function () {
                if ($("#_new_image_form").validationEngine('validate')) {
                    $("body").addClass("waiting-for-ajax");
                    $("#_new_image_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&project_id=" + $("#project_id").val() + "&url_action=add_image").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").off("load");
                    $("#_post_iframe").on("load", function () {
                        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                        const returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            return;
                        }
                        if ("log_entry" in returnArray) {
                            $("#_project_log").prepend(returnArray['log_entry']);
                        }
                        if (!("error_message" in returnArray)) {
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?id=" + $("#project_id").val();
                        }
                    });
                }
                return false;
            });
            $("#upload_new_file").click(function () {
                if ($("#_new_file_form").validationEngine('validate')) {
                    $("body").addClass("waiting-for-ajax");
                    $("#_new_file_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&project_id=" + $("#project_id").val() + "&url_action=add_file").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").off("load");
                    $("#_post_iframe").on("load", function () {
                        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                        const returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            return;
                        }
                        if ("log_entry" in returnArray) {
                            $("#_project_log").append(returnArray['log_entry']);
                        }
                        if (!("error_message" in returnArray)) {
                            document.location = "<?= $GLOBALS['gLinkUrl'] ?>?id=" + $("#project_id").val();
                        }
                    });
                }
                return false;
            });
            $(document).on("tap click", ".date-completed", function (event) {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_ticket_completed&help_desk_entry_id=" + $(this).data('help_desk_entry_id') + "&project_id=" + $("#project_id").val(), function(returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    } else {
                        $("#date_completed_" + returnArray['help_desk_entry_id']).html(returnArray['time_closed']);
                    }
                });
                event.stopPropagation();
                return false;
            });
            $(document).on("tap click", "#add_ticket", function () {
                $("#_new_ticket_form").clearForm();
                CKEDITOR.instances['content'].setData("");
                $('#_new_ticket_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 1000,
                    title: 'New Ticket',
                    open: function () {
                        addCKEditor();
                    },
                    buttons: {
                        Save: function (event) {
                            if ($("#_new_ticket_form").validationEngine('validate')) {
                                for (const instance in CKEDITOR.instances) {
                                    CKEDITOR.instances[instance].updateElement();
                                }
                                $("body").addClass("waiting-for-ajax");
                                $("#_new_ticket_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&project_id=" + $("#project_id").val() + "&url_action=create_help_desk_entry").attr("method", "POST").attr("target", "post_iframe").submit();
                                $("#_post_iframe").off("load");
                                $("#_post_iframe").on("load", function () {
                                    $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                                    const returnText = $(this).contents().find("body").html();
                                    const returnArray = processReturn(returnText);
                                    if (returnArray === false) {
                                        return;
                                    }
                                    if ("ticket_row" in returnArray) {
                                        $("#_ticket_list").append(returnArray['ticket_row']);
                                    }
                                    if ("log_entry" in returnArray) {
                                        $("#_project_log").prepend(returnArray['log_entry']);
                                    }
                                });
                                $("#_new_ticket_dialog").dialog('close');
                            }
                        },
                        Cancel: function (event) {
                            $("#_new_ticket_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("tap click", "#receive_notifications", function () {
                const checked = ($(this).prop("checked") ? "Y" : "");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_notification&project_id=" + $("#project_id").val() + "&notify=" + checked, $("#_project_log_form").serialize(), function(returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    } else {
                        if ("log_entry" in returnArray) {
                            $("#_project_log").prepend(returnArray['log_entry']);
                        }
                    }
                });
            });
            $(document).on("tap click", "#_add_message", function (event) {
                if ($("#log_content").val() != "") {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_log&project_id=" + $("#project_id").val(), $("#_project_log_form").serialize(), function(returnArray) {
                        if ("error_message" in returnArray) {
                            displayErrorMessage(returnArray['error_message']);
                        } else {
                            $("#_add_project_log").insertAfter($("#_project_log"));
                            $("#log_content").val("");
                            const parentLogId = $("#parent_log_id").val();
                            $("#parent_log_id").val("");
                            $("#project_log_title").html("Add a new message");
                            if ("log_entry" in returnArray) {
                                if (parentLogId == "") {
                                    $("#_project_log").prepend(returnArray['log_entry']);
                                } else {
                                    $("#project_log_" + parentLogId).after(returnArray['log_entry']);
                                }
                            }
                        }
                    });
                }
                return false;
            });
			<?php if ($GLOBALS['gPermissionLevel'] > 1) { ?>
            $(document).on("tap click", ".add-reply", function (event) {
                $("#_add_project_log").insertAfter($(this).closest("p"));
                $("#parent_log_id").val($(this).closest("p").data("log_id"));
                $("#project_log_title").html("Reply to this message (<a href='' class='add-new-log'>Add new message</a>)");
                $("#log_content").focus();
                return false;
            });
            $(document).on("tap click", ".add-new-log", function (event) {
                $("#_add_project_log").insertAfter($("#_project_log"));
                $("#parent_log_id").val("");
                $("#project_log_title").html("Add a new message");
                $("#log_content").focus();
                return false;
            });
            $(document).on("tap click", ".milestone-complete", function (event) {
                const projectId = $("#project_id").val();
                const projectMilestoneId = $(this).closest("tr").data("project_milestone_id");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=complete_milestone&project_id=" + projectId + "&project_milestone_id=" + projectMilestoneId, function(returnArray) {
                    if ("error_message" in returnArray) {
                        displayErrorMessage(returnArray['error_message']);
                    } else if ("date_completed" in returnArray) {
                        $("#milestone_complete_" + projectMilestoneId).html(returnArray['date_completed']);
                        if ("log_entry" in returnArray) {
                            $("#_project_log").prepend(returnArray['log_entry']);
                        }
                    } else {
                        $(".milestone-complete").prop("checked", false);
                    }
                });
                event.stopPropagation();
            });
			<?php } ?>
            $(document).on("tap click", ".milestone-row", function () {
                const highlight = !($(this).is(".highlighted-ticket"));
                $(".highlighted-ticket").removeClass("highlighted-ticket");
                if (highlight) {
                    const projectMilestoneId = $(this).data("project_milestone_id");
                    $(this).addClass("highlighted-ticket");
                    $(".milestone-" + projectMilestoneId).addClass("highlighted-ticket");
                }
            });
            $(document).on("tap click", ".editable-ticket", function (event) {
                const helpDeskEntryId = $(this).data("help_desk_entry_id");
                if (helpDeskEntryId != "") {
                    window.open("/help-desk-dashboard?id=" + helpDeskEntryId);
                }
            });
            getProjectLog();
        </script>
		<?php
		return true;
	}

	function hiddenElements() {
		$pagePreferences = Page::getPagePreferences();
		?>
        <div class="dialog-box" id="_new_ticket_dialog">
            <p class='error-message'></p>
            <form id="_new_ticket_form" enctype='multipart/form-data'>
                <div class="form-line" id="_help_desk_type_id_row">
                    <label class="required-label">Ticket Type</label>
                    <select tabindex="10" class="validate[required]" id="help_desk_type_id" name="help_desk_type_id">
						<?php
						$helpDeskTypeId = $pagePreferences['help_desk_type_id'];
						$resultSet = executeQuery("select * from help_desk_types where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $GLOBALS['gClientId']);
						if ($resultSet['row_count'] != 1) {
							?>
                            <option value="">[Select]</option>
							<?php
						}
						while ($row = getNextRow($resultSet)) {
							if ($resultSet['row_count'] == 1 && empty($helpDeskTypeId)) {
								$helpDeskTypeId = $row['help_desk_type_id'];
							}
							?>
                            <option <?= ($row['help_desk_type_id'] == $helpDeskTypeId ? "selected" : "") ?> value="<?= $row['help_desk_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_help_desk_category_id_row">
                    <label>Ticket Category</label>
                    <select tabindex="10" id="help_desk_category_id" name="help_desk_category_id">
                        <option value="">[Other]</option>
						<?php
						if (!empty($helpDeskTypeId)) {
							$resultSet = executeQuery("select * from help_desk_categories where help_desk_category_id in (select help_desk_category_id from help_desk_type_categories where help_desk_type_id = ?) and " .
								"client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $helpDeskTypeId, $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								?>
                                <option value="<?= $row['help_desk_category_id'] ?>"><?= htmlText($row['description']) ?></option>
								<?php
							}
						}
						?>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_description_row">
                    <label class="required-label">Description</label>
                    <input tabindex="10" size="80" type="text" id="description" name="description" class="validate[required]">
                    <div class='clear-div'></div>
                </div>

				<?php
				echo createFormControl("help_desk_entries", "content", array("not_null" => true, "form_label" => "Details", "classes" => "ck-editor"));
				?>

                <div class="form-line" id="_user_id_row">
                    <label>Assign To User</label>
                    <select tabindex="10" id="user_id" name="user_id">
                        <option value=''>[None]</option>
						<?php
						$resultSet = executeQuery("select user_id from users join contacts using (contact_id) where inactive = 0 and (user_id in (select user_id from project_member_users where project_id = ?) or " .
							"user_id in (select user_id from user_group_members where user_group_id in (select user_group_id from project_member_user_groups where project_id = ?)) or " .
							"user_id = (select user_id from projects where project_id = ?) or user_id = (select leader_user_id from projects where project_id = ?)) order by last_name,first_name",
							$this->iProjectId, $this->iProjectId, $this->iProjectId, $this->iProjectId);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option<?= ($pagePreferences['user_id'] = $row['user_id'] ? " selected" : "") ?> value="<?= $row['user_id'] ?>"><?= getUserDisplayName($row['user_id']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_user_group_id_row">
                    <label>Assign To User Group</label>
                    <select tabindex="10" id="user_group_id" name="user_group_id">
                        <option value=''>[None]</option>
						<?php
						$resultSet = executeQuery("select user_group_id,description from user_groups where inactive = 0 and user_group_id in (select user_group_id from project_member_user_groups where project_id = ?) order by sort_order,description", $this->iProjectId);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option<?= ($pagePreferences['user_group_id'] = $row['user_group_id'] ? " selected" : "") ?> value="<?= $row['user_group_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_date_due_row">
                    <label>Date Due</label>
                    <input type='text' tabindex="10" class='validate[custom[date]] datepicker' size='12' id='date_due' name='date_due' value="<?= (empty($pagePreferences['date_due']) ? "" : date("m/d/Y", strtotime($pagePreferences['date_due']))) ?>">
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_project_milestone_id_row">
                    <label>Project Milestone</label>
                    <span class='help-label'>Which milestone (if any) is this ticket part of?</span>
                    <select tabindex="10" id="project_milestone_id" name="project_milestone_id">
                        <option value="">[None]</option>
						<?php
						$resultSet = executeQuery("select * from project_milestones where project_id = ? order by days_before desc,project_milestone_id", $this->iProjectId);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option<?= ($pagePreferences['project_milestone_id'] = $row['project_milestone_id'] ? " selected" : "") ?> value="<?= $row['project_milestone_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='clear-div'></div>
                </div>

                <div class="form-line" id="_file_id_row">
                    <label>File or Image</label>
                    <input tabindex="10" type="file" id="file_id_file" name="file_id_file">
                    <div class='clear-div'></div>
                </div>
            </form>
        </div> <!-- new_ticket_dialog -->
		<?php
	}

	function javascript() {
		?>
        <script>
            function getProjectLog() {
                $("#_project_log").html("");
                const projectId = $("#project_id").val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_project_log&project_id=" + projectId, function(returnArray) {
                    if ("project_log" in returnArray) {
                        $("#_project_log").html(returnArray['project_log']);
                    }
                });
            }
        </script>
		<?php
		return true;
	}

	private function sortTickets($a, $b) {
		if ($a['sort_date_due'] == $b['sort_date_due']) {
			return 0;
		}

		return ($a['sort_date_due'] > $b['sort_date_due'] ? 1 : -1);
	}
}

$pageObject = new ProjectPage("projects");
$pageObject->displayPage();
