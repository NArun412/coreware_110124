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

$GLOBALS['gPageCode'] = "HELPDESKDASHBOARD";
$GLOBALS['gIgnoreNotices'] = true;
require_once "shared/startup.inc";

class HelpDeskDashboardPage extends Page {

	private $iHelpDeskAdmin = false;
	private $iColumnChoices = array();
	private $iPagePreferences = array();

	function setup() {

		$this->iColumnChoices = array();
		$this->iColumnChoices['time_submitted'] = array("description" => "Time Submitted");
		$this->iColumnChoices['date_due'] = array("description" => "Date Due");
		$this->iColumnChoices['contact_id'] = array("description" => "Creator", "staff_only" => true);
		$this->iColumnChoices['user_id'] = array("description" => "Assigned User", "staff_only" => true);
		$this->iColumnChoices['help_desk_type_id'] = array("description" => "Help Desk Type");
		$this->iColumnChoices['help_desk_category_id'] = array("description" => "Category");
		$this->iColumnChoices['help_desk_status_id'] = array("description" => "Status");
		$this->iColumnChoices['help_desk_tag_ids'] = array("description" => "Tags");
		$this->iColumnChoices['description'] = array("description" => "Subject");
		$this->iColumnChoices['last_activity'] = array("description" => "Last Activity");
		$this->iColumnChoices['user_group_id'] = array("description" => "Assigned User Group", "staff_only" => true);
		$this->iColumnChoices['priority'] = array("description" => "Priority", "staff_only" => true);
		$this->iColumnChoices['time_closed'] = array("description" => "Time Closed");

		$this->iHelpDeskAdmin = (isInUserGroupCode($GLOBALS['gUserId'], "HELP_DESK_ADMIN") || isInUserGroupCode($GLOBALS['gUserId'], "HELP_DESK_STAFF") || $GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access']);
		$this->iPagePreferences = Page::getPagePreferences("HELPDESKDASHBOARD");
		if ($GLOBALS['gLoggedIn'] && empty($_GET['check_for_updates'])) {
			if (array_key_exists("ticket_user_type", $_GET) && !empty($_GET['ticket_user_type'])) {
				$this->iPagePreferences['ticket_user_type'] = $_GET['ticket_user_type'];
			}
			if ($this->iPagePreferences['ticket_user_type'] != "staff" && $this->iPagePreferences['ticket_user_type'] != "user") {
				$this->iPagePreferences['ticket_user_type'] = "user";
			}
			Page::setPagePreferences($this->iPagePreferences, "HELPDESKDASHBOARD");
		}
	}

	function userPresets() {
		$userArray = getCachedData("user_presets", "help_desk");
		if (empty($userArray) || !is_array($userArray)) {
			$userArray = array($GLOBALS['gUserId'] => getUserDisplayName(array("include_company" => false)));
			$this->iHelpDeskAdmin = (isInUserGroupCode($GLOBALS['gUserId'], "HELP_DESK_ADMIN") || isInUserGroupCode($GLOBALS['gUserId'], "HELP_DESK_STAFF") || $GLOBALS['gUserRow']['superuser_flag'] || $GLOBALS['gUserRow']['full_client_access']);
			if ($this->iHelpDeskAdmin) {
				$resultSet = executeQuery("select * from users join contacts using (contact_id) where (user_id in " .
					"(select user_id from help_desk_entries where user_id is not null and client_id = ? and time_submitted > date_sub(current_date,interval 90 day)) or user_id in (select previous_user_id from help_desk_entries " .
					"where previous_user_id is not null and client_id = ? and time_submitted > date_sub(current_date,interval 90 day))) and users.inactive = 0 and administrator_flag = 1 order by first_name,last_name", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if ($row['user_id'] != $GLOBALS['gUserId']) {
						$userArray[$row['user_id']] = getUserDisplayName($row['user_id'], array("include_company" => false));
					}
				}
				if (!array_key_exists($GLOBALS['gUserId'], $userArray)) {
					$userArray[$GLOBALS['gUserId']] = getUserDisplayName();
				}
			}
			setCachedData("user_presets", "help_desk", $userArray, 2);
		}
		return $userArray;
	}

	function sortHelpDeskEntries($a, $b) {
		$descendingSortOrder = $this->iPagePreferences['sort_direction'] == -1;
		$secondaryDescendingSortOrder = $this->iPagePreferences['secondary_sort_direction'] == -1;
		$tertiaryDescendingSortOrder = $this->iPagePreferences['tertiary_sort_direction'] == -1;

		$sortValueA = strtoupper((array_key_exists($this->iPagePreferences['column_name'], $a) ? array_key_exists("sort_value", $a[$this->iPagePreferences['column_name']]) ? $a[$this->iPagePreferences['column_name']]['sort_value'] : $a[$this->iPagePreferences['column_name']]['display_value'] : ""));
		if (empty($sortValueA) && !$descendingSortOrder) {
			$sortValueA = "zzzzzzzzzzzzzzzzzzz";
		}
		$sortValueB = strtoupper((array_key_exists($this->iPagePreferences['column_name'], $b) ? array_key_exists("sort_value", $b[$this->iPagePreferences['column_name']]) ? $b[$this->iPagePreferences['column_name']]['sort_value'] : $b[$this->iPagePreferences['column_name']]['display_value'] : ""));
		if (empty($sortValueB) && !$descendingSortOrder) {
			$sortValueB = "zzzzzzzzzzzzzzzzzzz";
		}

		if ($sortValueA == $sortValueB) {
			$sortValueA = (empty($this->iPagePreferences['secondary_column_name']) ? "" : strtoupper((array_key_exists($this->iPagePreferences['secondary_column_name'], $a) ? array_key_exists("sort_value", $a[$this->iPagePreferences['secondary_column_name']]) ? $a[$this->iPagePreferences['secondary_column_name']]['sort_value'] : $a[$this->iPagePreferences['secondary_column_name']]['display_value'] : "")));
			if (empty($sortValueA) && !$secondaryDescendingSortOrder) {
				$sortValueA = "zzzzzzzzzzzzzzzzzzz";
			}
			$sortValueB = (empty($this->iPagePreferences['secondary_column_name']) ? "" : strtoupper((array_key_exists($this->iPagePreferences['secondary_column_name'], $b) ? array_key_exists("sort_value", $b[$this->iPagePreferences['secondary_column_name']]) ? $b[$this->iPagePreferences['secondary_column_name']]['sort_value'] : $b[$this->iPagePreferences['secondary_column_name']]['display_value'] : "")));
			if (empty($sortValueB) && !$secondaryDescendingSortOrder) {
				$sortValueB = "zzzzzzzzzzzzzzzzzzz";
			}
			if (empty($this->iPagePreferences['secondary_column_name'])) {
				return 0;
			}
			if ($sortValueA == $sortValueB) {
				$sortValueA = (empty($this->iPagePreferences['tertiary_column_name']) ? "" : strtoupper((array_key_exists($this->iPagePreferences['tertiary_column_name'], $a) ? array_key_exists("sort_value", $a[$this->iPagePreferences['tertiary_column_name']]) ? $a[$this->iPagePreferences['tertiary_column_name']]['sort_value'] : $a[$this->iPagePreferences['tertiary_column_name']]['display_value'] : "")));
				if (empty($sortValueA) && !$tertiaryDescendingSortOrder) {
					$sortValueA = "zzzzzzzzzzzzzzzzzzz";
				}
				$sortValueB = (empty($this->iPagePreferences['tertiary_column_name']) ? "" : strtoupper((array_key_exists($this->iPagePreferences['tertiary_column_name'], $b) ? array_key_exists("sort_value", $b[$this->iPagePreferences['tertiary_column_name']]) ? $b[$this->iPagePreferences['tertiary_column_name']]['sort_value'] : $b[$this->iPagePreferences['tertiary_column_name']]['display_value'] : "")));
				if (empty($sortValueB) && !$tertiaryDescendingSortOrder) {
					$sortValueB = "zzzzzzzzzzzzzzzzzzz";
				}
				if (empty($this->iPagePreferences['tertiary_column_name']) || $sortValueA == $sortValueB) {
					return 0;
				} else {
					return ($sortValueA > $sortValueB ? 1 : -1) * ($tertiaryDescendingSortOrder ? -1 : 1);
				}

			} else {
				return ($sortValueA > $sortValueB ? 1 : -1) * ($secondaryDescendingSortOrder ? -1 : 1);
			}
		}
		return ($sortValueA > $sortValueB ? 1 : -1) * ($descendingSortOrder ? -1 : 1);
	}

	function executePageUrlActions() {
		$labels = array();
		if (is_array($GLOBALS['gPageRow']['page_text_chunks'])) {
			foreach ($GLOBALS['gPageRow']['page_text_chunks'] as $pageTextChunkCode => $pageTextChunkContent) {
				$labels[strtolower($pageTextChunkCode)] = $pageTextChunkContent;
			}
		}

		$returnArray = array();
		switch ($_GET['url_action']) {
			case "edit_subject":
				$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_POST['help_desk_entry_id'], $this->iPagePreferences['ticket_user_type'] == "staff");
				if (empty($helpDeskEntryId)) {
					$returnArray['error_message'] = "Help Desk Entry not found";
					ajaxResponse($returnArray);
					break;
				}
				$dataTable = new DataTable("help_desk_entries");
				$dataTable->setSaveOnlyPresent(true);
				if (!$dataTable->saveRecord(array("name_values" => array("description" => $_POST['description']), "primary_id" => $helpDeskEntryId))) {
					$returnArray['error_message'] = $dataTable->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "mark_spam":
				$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_GET['help_desk_entry_id'], $this->iPagePreferences['ticket_user_type'] == "staff");
				if (empty($helpDeskEntryId)) {
					$returnArray['error_message'] = "Help Desk Entry not found";
				} else {
					$helpDesk = new HelpDesk($helpDeskEntryId);
					$helpDesk->markClosed();
				}
				$contactId = getFieldFromId("contact_id", "help_desk_entries", "help_desk_entry_id", $helpDeskEntryId);
				$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $contactId);
				if (!empty($emailAddress)) {
					executeQuery("insert into help_desk_ignore_email_addresses (email_address) values (?)", $emailAddress);
				}
				ajaxResponse($returnArray);
				break;
			case "get_review":
				$helpDeskEntryReviewRow = getRowFromId("help_desk_entry_reviews", "help_desk_entry_id", $_GET['help_desk_entry_id']);
				if (empty($helpDeskEntryReviewRow)) {
					$returnArray['content'] = "<p>No Review Found</p>";
					ajaxResponse($returnArray);
					break;
				}
				ob_start();
				?>
				<p><span class='highlighted-text'>Time Submitted</span>: <?= date("m/d/Y g:ia", strtotime($helpDeskEntryReviewRow['time_submitted'])) ?></p>
				<?php
				$resultSet = executeQuery("select * from help_desk_review_questions where inactive = 0 order by sort_order,description");
				while ($row = getNextRow($resultSet)) {
					?>
					<p><span class='highlighted-text'><?= htmlText($row['content']) ?></span><br>
						<?php
						$answer = getFieldFromId("content", "help_desk_entry_review_answers", "help_desk_entry_review_id", $helpDeskEntryReviewRow['help_desk_entry_review_id'],
							"help_desk_review_question_id = ?", $row['help_desk_review_question_id']);
						switch ($row['data_type']) {
							case "tinyint":
								echo(empty($answer) ? "no" : "YES");
								break;
							case "select":
								echo(empty($answer) ? "NO RESPONSE" : $answer);
								break;
							case "text":
								echo(empty($answer) ? "NO RESPONSE" : str_replace("\n", "<br><br>", htmlText($answer)));
								break;
						}
						?>
					</p>
					<?php
				}
				if (!empty($helpDeskEntryReviewRow['comments'])) {
					?>
					<p><span class='highlighted-text'>Additional Comments: </span><?= str_replace("\n", "<br><br>", htmlText($helpDeskEntryReviewRow['comments'])) ?></p>
					<?php
				}
				$returnArray['content'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "unselect_tickets":
				executeQuery("delete from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $GLOBALS['gPageId']);
				ajaxResponse($returnArray);
				break;
			case "select_ticket":
				$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_GET['help_desk_entry_id'], $this->iPagePreferences['ticket_user_type'] == "staff");
				if ($_GET['checked']) {
					executeQuery("insert ignore into selected_rows (user_id,page_id,primary_identifier) values (?,?,?)", array($GLOBALS['gUserId'], $GLOBALS['gPageId'], $helpDeskEntryId));
				} else {
					executeQuery("delete from selected_rows where user_id = ? and page_id = ? and primary_identifier = ?", $GLOBALS['gUserId'], $GLOBALS['gPageId'], $helpDeskEntryId);
				}
				ajaxResponse($returnArray);
				break;
			case "get_checklist":
				$returnArray['content'] = getFieldFromId("content", "help_desk_checklists", "help_desk_checklist_id", $_GET['help_desk_checklist_id']);
				ajaxResponse($returnArray);
				break;
			case "upload_files":
				$privateAccess = (!empty($_GET['private_access']));
				$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_GET['help_desk_entry_id'], $this->iPagePreferences['ticket_user_type'] == "staff");
				if (!empty($helpDeskEntryId)) {
					$helpDesk = new HelpDesk($helpDeskEntryId);
					$helpDesk->addFiles($privateAccess);
				}
				ajaxResponse($returnArray);
				break;
			case "get_ticket_files":
				$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_POST['help_desk_entry_id'], $this->iPagePreferences['ticket_user_type'] == "staff");
				ob_start();
				$imageCount = 0;
				$resultSet = executeQuery("select * from images join help_desk_entry_images using (image_id) where help_desk_entry_id = ?" . ($this->iPagePreferences['ticket_user_type'] == "staff" ? "" : " and help_desk_entry_images.private_access = 0") . " order by log_time desc", $helpDeskEntryId);
				while ($row = getNextRow($resultSet)) {
					$imageCount++;
					?>
					<div class='help-desk-entry-image'>
						<a href='<?= getImageFilename($row['image_id'], array("use_cdn" => true)) ?>' class='pretty-photo'>
							<img alt='ticket image' src="<?= getImageFilename($row['image_id'], array("use_cdn" => true)) ?>">
							<span><?= $row['filename'] ?> - <?= date("m/d g:i a", strtotime($row['log_time'])) ?></span>
						</a>
					</div>
					<?php
				}
				$returnArray['help_desk_entry_images'] = ob_get_clean();
				ob_start();
				$fileCount = 0;
				$resultSet = executeQuery("select * from files join help_desk_entry_files using (file_id) where help_desk_entry_id = ?" . ($this->iPagePreferences['ticket_user_type'] == "staff" ? "" : " and help_desk_entry_files.private_access = 0") . " order by log_time desc", $helpDeskEntryId);
				while ($row = getNextRow($resultSet)) {
					$fileCount++;
					?>
					<div class='help-desk-entry-file'>
						<a target="_blank" href='/download.php?id=<?= $row['file_id'] ?>'><span><?= $row['filename'] ?> - <?= date("m/d/Y g:i a", strtotime($row['log_time'])) ?></span></a>
					</div>
					<?php
				}
				if ($imageCount > 0 || $fileCount > 0) {
					$returnArray['display_files_count'] = "";
					if ($imageCount > 0) {
						$returnArray['display_files_count'] = $imageCount . " image" . ($imageCount == 1 ? "" : "s") . " attached";
					}
					if ($fileCount > 0) {
						$returnArray['display_files_count'] .= ($imageCount > 0 ? " and " : "") . $fileCount . " file" . ($fileCount == 1 ? "" : "s") . " attached";
					}
				} else {
					$returnArray['display_files_count'] = "";
				}
				$returnArray['help_desk_entry_files'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "set_list_item_order";
				$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_POST['help_desk_entry_id'], $this->iPagePreferences['ticket_user_type'] == "staff");
				foreach ($_POST['sequence_numbers'] as $helpDeskEntryListItemId => $sequenceNumber) {
					executeQuery("update help_desk_entry_list_items set sequence_number = ? where help_desk_entry_id = ? and help_desk_entry_list_item_id = ?", $sequenceNumber, $helpDeskEntryId, $helpDeskEntryListItemId);
				}
				ajaxResponse($returnArray);
				break;
			case "get_list_items":
				$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_POST['help_desk_entry_id'], $this->iPagePreferences['ticket_user_type'] == "staff");
				ob_start();
				$resultSet = executeQuery("select * from help_desk_entry_list_items where help_desk_entry_id = ? order by sequence_number,help_desk_entry_list_item_id", $helpDeskEntryId);
				while ($row = getNextRow($resultSet)) {
					?>
					<div class='help-desk-entry-list-item' id="help_desk_entry_list_item_<?= $row['help_desk_entry_list_item_id'] ?>" data-help_desk_entry_list_item_id="<?= $row['help_desk_entry_list_item_id'] ?>">
						<div><span class='help-desk-entry-list-item-marked-completed far fa-square<?= (empty($row['marked_completed']) ? "" : " hidden") ?>'></span><span class='help-desk-entry-list-item-marked-completed fad fa-check-square<?= (empty($row['marked_completed']) ? " hidden" : "") ?>'></span></div>
						<textarea class='help-desk-entry-list-item-description' id="help_desk_entry_list_item_description_<?= $row['help_desk_entry_list_item_id'] ?>" name="help_desk_entry_list_item_description_<?= $row['help_desk_entry_list_item_id'] ?>"><?= htmlText($row['content']) ?></textarea>
						<div><span class='delete-list-item fad fa-trash-alt'></span><input type='hidden' class='help-desk-entry-list-item-sequence-number' value='<?= $row['sequence_number'] ?>'><input type='hidden' class='marked-completed' value='<?= $row['marked_completed'] ?>'></div>
						<div><span class='fad fa-bars'></span></div>
					</div>
					<?php
				}
				$returnArray['help_desk_entry_list_items'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "update_help_desk_entry_list_item":
				$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_POST['help_desk_entry_id'], $this->iPagePreferences['ticket_user_type'] == "staff");
				$helpDeskEntryListItemId = getFieldFromId("help_desk_entry_list_item_id", "help_desk_entry_list_items", "help_desk_entry_list_item_id", $_POST['help_desk_entry_list_item_id'], "help_desk_entry_id = ?", $helpDeskEntryId);
				if (empty($helpDeskEntryListItemId) && !empty($_POST['help_desk_entry_list_item_id'])) {
					$returnArray['error_message'] = "Invalid Help Desk List Item";
					ajaxResponse($returnArray);
					break;
				}
				$dataTable = new DataTable("help_desk_entry_list_items");
				$dataTable->setSaveOnlyPresent(true);
				if (empty($helpDeskEntryListItemId)) {
					if (!empty($_POST['content'])) {
						$contentList = getContentLines($_POST['content']);
						foreach ($contentList as $thisContent) {
							$_POST['content'] = $thisContent;
							$dataTable->saveRecord(array("name_values" => $_POST, "primary_id" => $helpDeskEntryListItemId));
						}
					}
				} else {
					$dataTable->saveRecord(array("name_values" => $_POST, "primary_id" => $helpDeskEntryListItemId));
				}
				ajaxResponse($returnArray);
				break;
			case "delete_help_desk_entry_list_item":
				$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_POST['help_desk_entry_id'], $this->iPagePreferences['ticket_user_type'] == "staff");
				$helpDeskEntryListItemId = getFieldFromId("help_desk_entry_list_item_id", "help_desk_entry_list_items", "help_desk_entry_list_item_id", $_POST['help_desk_entry_list_item_id'], "help_desk_entry_id = ?", $helpDeskEntryId);
				if (empty($helpDeskEntryListItemId) && !empty($_POST['help_desk_entry_list_item_id'])) {
					$returnArray['error_message'] = "Invalid Help Desk List Item";
					ajaxResponse($returnArray);
					break;
				}
				$dataTable = new DataTable("help_desk_entry_list_items");
				$dataTable->setSaveOnlyPresent(true);
				$dataTable->deleteRecord(array("primary_id" => $helpDeskEntryListItemId));
				ajaxResponse($returnArray);
				break;
			case "set_ticket_type":
				if ($GLOBALS['gLoggedIn']) {
					$this->iPagePreferences = Page::getPagePreferences("HELPDESKDASHBOARD");
					$this->iPagePreferences['ticket_type'] = $_GET['ticket_type'];
					Page::setPagePreferences($this->iPagePreferences, "HELPDESKDASHBOARD");
				}
				ajaxResponse($returnArray);
				break;
			case "delete_ticket":
				$helpDeskEntryId = getFieldFromId("help_desk_entry_id", "help_desk_entries", "help_desk_entry_id", $_POST['help_desk_entry_id']);
				if (empty($helpDeskEntryId)) {
					$returnArray['error_message'] = "Invalid Help Desk Entry. Did you get logged out? Did you simulate another user?";
					ajaxResponse($returnArray);
					break;
				}
				$this->iDatabase->startTransaction();
				$subTables = array("help_desk_entry_activities", "help_desk_entry_files", "help_desk_entry_images", "help_desk_entry_list_items",
					"help_desk_entry_users", "help_desk_private_notes", "help_desk_public_notes");
				$fileIds = array();
				$imageIds = array();
				$resultSet = executeQuery("select file_id from help_desk_entry_files where help_desk_entry_id = ?", $helpDeskEntryId);
				while ($row = getNextRow($resultSet)) {
					$fileIds[] = $row['file_id'];
				}
				$resultSet = executeQuery("select image_id from help_desk_entry_images where help_desk_entry_id = ?", $helpDeskEntryId);
				while ($row = getNextRow($resultSet)) {
					$imageIds[] = $row['image_id'];
				}
				foreach ($subTables as $thisTable) {
					$resultSet = executeQuery("delete from " . $thisTable . " where help_desk_entry_id = ?", $helpDeskEntryId);
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
				$resultSet = executeQuery("delete from help_desk_entries where help_desk_entry_id = ?", $helpDeskEntryId);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->ignoreError(true);
				foreach ($fileIds as $fileId) {
					executeQuery("delete ignore from files where file_id = ?", $fileId);
				}
				foreach ($imageIds as $imageId) {
					executeQuery("delete ignore from images where image_id = ?", $imageId);
				}
				$GLOBALS['gPrimaryDatabase']->ignoreError(false);
				$returnArray['info_message'] = "Ticket successfully deleted";
				$this->iDatabase->commitTransaction();
				ajaxResponse($returnArray);
				break;
			case "merge_tickets":
				$helpDeskEntryId = getFieldFromId("help_desk_entry_id", "help_desk_entries", "help_desk_entry_id", $_POST['help_desk_entry_id']);
				$mergeIntoHelpDeskEntryId = getFieldFromId("help_desk_entry_id", "help_desk_entries", "help_desk_entry_id", $_POST['merge_into_help_desk_entry_id']);
				if (empty($helpDeskEntryId) || empty($mergeIntoHelpDeskEntryId)) {
					$returnArray['error_message'] = "Invalid Help Desk Entry. Did you get logged out? Did you simulate another user?";
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("delete from help_desk_entry_users where help_desk_entry_id = ?", $helpDeskEntryId);
				executeQuery("delete from help_desk_entry_activities where help_desk_entry_id = ?", $helpDeskEntryId);
				executeQuery("update help_desk_private_notes set help_desk_entry_id = ? where help_desk_entry_id = ?", $mergeIntoHelpDeskEntryId, $helpDeskEntryId);
				executeQuery("update help_desk_public_notes set help_desk_entry_id = ? where help_desk_entry_id = ?", $mergeIntoHelpDeskEntryId, $helpDeskEntryId);
				executeQuery("update help_desk_private_notes set help_desk_entry_id = ? where help_desk_entry_id = ?", $mergeIntoHelpDeskEntryId, $helpDeskEntryId);
				executeQuery("update ignore help_desk_entry_files set help_desk_entry_id = ? where help_desk_entry_id = ?", $mergeIntoHelpDeskEntryId, $helpDeskEntryId);
				executeQuery("update ignore help_desk_entry_images set help_desk_entry_id = ? where help_desk_entry_id = ?", $mergeIntoHelpDeskEntryId, $helpDeskEntryId);
				executeQuery("update ignore help_desk_entry_votes set help_desk_entry_id = ? where help_desk_entry_id = ?", $mergeIntoHelpDeskEntryId, $helpDeskEntryId);
				executeQuery("update ignore help_desk_entry_list_items set help_desk_entry_id = ? where help_desk_entry_id = ?", $mergeIntoHelpDeskEntryId, $helpDeskEntryId);
				executeQuery("update ignore help_desk_tag_links set help_desk_entry_id = ? where help_desk_entry_id = ?", $mergeIntoHelpDeskEntryId, $helpDeskEntryId);
				executeQuery("delete from help_desk_entry_files where help_desk_entry_id = ?", $helpDeskEntryId);
				executeQuery("delete from help_desk_entry_images where help_desk_entry_id = ?", $helpDeskEntryId);
				executeQuery("delete from help_desk_entry_votes where help_desk_entry_id = ?", $helpDeskEntryId);
				executeQuery("delete from help_desk_tag_links where help_desk_entry_id = ?", $helpDeskEntryId);
				$helpDeskEntryRow = getRowFromId("help_desk_entries", "help_desk_entry_id", $helpDeskEntryId);
				$userId = Contact::getContactUserId($helpDeskEntryRow['contact_id']);
				executeQuery("insert into help_desk_public_notes (help_desk_entry_id,user_id,email_address,time_submitted,content) values (?,?,?,?,?)",
					$mergeIntoHelpDeskEntryId, $userId, getFieldFromId("email_address", "contacts", "contact_id", $helpDeskEntryRow['contact_id']),
					$helpDeskEntryRow['time_submitted'], $helpDeskEntryRow['content']);
				executeQuery("delete from help_desk_entries where help_desk_entry_id = ?", $helpDeskEntryId);
				ajaxResponse($returnArray);
				break;
			case "check_for_updates":
				$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_GET['help_desk_entry_id'], $this->iPagePreferences['ticket_user_type'] == "staff");
				if (empty($helpDeskEntryId)) {
					ajaxResponse($returnArray);
					break;
				}
				$returnArray['user_list'] = $this->addHelpDeskEntryActivity($helpDeskEntryId);
				$helpDeskEntryRow = getRowFromId("help_desk_entries", "help_desk_entry_id", $helpDeskEntryId, ($GLOBALS['gUserRow']['superuser_flag'] ? "client_id is not null" : ""));
				if (empty($_GET['ticket_closed']) && !empty($helpDeskEntryRow['time_closed'])) {
					$returnArray['ticket_closed'] = "<p>Ticket was just closed</p>";
				}

				$resultSet = executeQuery("select help_desk_public_note_id as note_id,user_id,time_submitted,content,'public' as note_type from help_desk_public_notes where help_desk_entry_id = " . $helpDeskEntryRow['help_desk_entry_id'] .
					($this->iPagePreferences['ticket_user_type'] == "staff" ? " union select help_desk_private_note_id as note_id,user_id,time_submitted,content,'private' as note_type from help_desk_private_notes where help_desk_entry_id = " . $helpDeskEntryRow['help_desk_entry_id'] : "") .
					" order by time_submitted desc");
				$displayedNoteCount = $_GET['notes_count'];
				$totalNoteCount = $resultSet['row_count'];
				ob_start();
				while ($displayedNoteCount < $totalNoteCount) {
					$row = getNextRow($resultSet);
					if ($this->iPagePreferences['ticket_user_type'] != "staff" && $row['note_type'] == "private") {
						continue;
					}
					$userImageId = Contact::getUserContactField($row['user_id'], "image_id");
					?>
					<div class="ticket-note <?= $row['note_type'] ?>-note"
					     id="ticket_note_<?= $row['note_type'] ?>_<?= $row['note_id'] ?>">
						<p class="highlighted-text"><?= ($row['note_type'] == "private" ? "PRIVATE NOTE " : "") ?>Posted
							by <?= (empty($row['user_id']) ? $row['email_address'] : ($row['user_id'] == $GLOBALS['gUserId'] ? "yourself" : (empty($userImageId) ? "" : "<a class='pretty-photo' href='/getimage.php?id=" . $userImageId . "'>") .
								getUserDisplayName($row['user_id'], array("include_company" => false)) . (empty($userImageId) ? "" : "</a>"))) ?>
							on <?= date("m/d/Y g:ia", strtotime($row['time_submitted'])) ?></p>
						<?= (hasLegitimateHtml($row['content']) ? makeHtml(cleanHtml($row['content'])) : "<p>" . str_replace("\n", "<br>", str_replace("<", "&lt;", str_replace(">", "&gt;", $row['content']))) . "</p>") ?>
					</div>
					<?php
					$displayedNoteCount++;
				}
				$returnArray['notes_count'] = $displayedNoteCount;
				$returnArray['additional_notes'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "get_answer":
				$returnArray['content'] = getFieldFromId("content", "help_desk_answers", "help_desk_answer_id", $_GET['help_desk_answer_id'], ($GLOBALS['gUserRow']['superuser_flag'] ? "client_id is not null" : ""));
				ajaxResponse($returnArray);
				break;
			case "mass_edit":
				$helpDeskEntryIds = array();
				foreach (explode(",", $_POST['mass_edit_help_desk_entry_ids']) as $helpDeskEntryId) {
					$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($helpDeskEntryId);
					if (!empty($helpDeskEntryId)) {
						$helpDeskEntryIds[] = $helpDeskEntryId;
					}
				}
				foreach ($helpDeskEntryIds as $helpDeskEntryId) {
					$helpDesk = new HelpDesk($helpDeskEntryId);
					if (!empty($_POST['mass_edit_close_ticket'])) {
						$helpDesk->markClosed();
					}
					if (!empty($_POST['mass_edit_help_desk_status_id'])) {
						$helpDesk->setStatus($_POST['mass_edit_help_desk_status_id']);
					}
					if (!empty($_POST['mass_edit_help_desk_tag_id'])) {
						$helpDesk->addHelpDeskTag($_POST['mass_edit_help_desk_tag_id']);
					}
					if (!empty($_POST['mass_edit_remove_help_desk_tag_id'])) {
						$helpDesk->removeHelpDeskTag($_POST['mass_edit_remove_help_desk_tag_id']);
					}
					if (!empty($_POST['mass_edit_public_note'])) {
						$helpDesk->addPublicNote($_POST['mass_edit_public_note'] . (empty($this->iPagePreferences['notes_signature']) ? "" : "\n\n" . $this->iPagePreferences['notes_signature']));
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_user_group_members":
				$returnArray['users'] = array();
				if ($this->iHelpDeskAdmin) {
					$resultSet = executeQuery("select user_id from users join contacts using (contact_id) where " .
						"user_id in (select user_id from help_desk_entries where client_id = ?) and user_id in " .
						"(select user_id from user_group_members where user_group_id = ?) order by last_name,first_name",
						$GLOBALS['gClientId'], $_GET['user_group_id']);
					while ($row = getNextRow($resultSet)) {
						$returnArray['users'][] = array("key_value" => $row['user_id'], "description" => getUserDisplayName($row['user_id'], array("include_company" => false)));
					}
				}
				ajaxResponse($returnArray);
				break;
			case "set_sort_order":
				$_POST['tertiary_column_name'] = $this->iPagePreferences['secondary_column_name'];
				$_POST['tertiary_sort_direction'] = $this->iPagePreferences['secondary_sort_direction'];
				$_POST['secondary_column_name'] = $this->iPagePreferences['column_name'];
				$_POST['secondary_sort_direction'] = $this->iPagePreferences['sort_direction'];
			case "set_preferences":
				if ($GLOBALS['gLoggedIn']) {
					$this->iPagePreferences = Page::getPagePreferences("HELPDESKDASHBOARD");
					foreach ($_POST as $fieldName => $fieldData) {
						$this->iPagePreferences[$fieldName] = $fieldData;
					}
					if ($this->iPagePreferences['tertiary_column_name'] == $this->iPagePreferences['secondary_column_name'] || $this->iPagePreferences['tertiary_column_name'] == $this->iPagePreferences['column_name']) {
						$this->iPagePreferences['tertiary_column_name'] = "";
						$this->iPagePreferences['tertiary_sort_direction'] = 1;
					}
					if ($this->iPagePreferences['secondary_column_name'] == $this->iPagePreferences['column_name']) {
						$this->iPagePreferences['secondary_column_name'] = "";
						$this->iPagePreferences['secondary_sort_direction'] = 1;
					}
					Page::setPagePreferences($this->iPagePreferences, "HELPDESKDASHBOARD");
				}
				ajaxResponse($returnArray);
				break;
			case "reopen_ticket":
				$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_GET['help_desk_entry_id'], $this->iPagePreferences['ticket_user_type'] == "staff");
				if (empty($helpDeskEntryId)) {
					$returnArray['error_message'] = "Help Desk Entry not found";
				} else {
					$helpDesk = new HelpDesk($helpDeskEntryId);
					$helpDesk->reopen();
				}
				ajaxResponse($returnArray);
				break;
			case "close_ticket":
				$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_GET['help_desk_entry_id'], $this->iPagePreferences['ticket_user_type'] == "staff");
				if (empty($helpDeskEntryId)) {
					$returnArray['error_message'] = "Help Desk Entry not found";
				} else {
					$helpDesk = new HelpDesk($helpDeskEntryId);
					$helpDesk->markClosed();
				}
				ajaxResponse($returnArray);
				break;
			case "get_ticket_details":
				if ($GLOBALS['gUserRow']['superuser_flag']) {
					$clientId = getFieldFromId("client_id", "help_desk_entries", "help_desk_entry_id", $_GET['help_desk_entry_id'], "client_id is not null");
					if (!empty($clientId) && $clientId != $GLOBALS['gUserRow']['client_id']) {
						$resultSet = executeQuery("update users set client_id = ? where user_id = ?", $clientId, $GLOBALS['gUserId']);
						if ($resultSet['affected_rows'] > 0) {
							executeQuery("delete from selected_rows where user_id = ?", $GLOBALS['gUserId']);
						}
						executeQuery("update contacts set client_id = ? where contact_id = ?", $clientId, $GLOBALS['gUserRow']['contact_id']);
						$returnArray['reload_ticket'] = true;
						ajaxResponse($returnArray);
						break;
					}
				}
				$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_GET['help_desk_entry_id'], $this->iPagePreferences['ticket_user_type'] == "staff");
				if (empty($helpDeskEntryId)) {
					ob_start(); ?>
					<div id="_ticket_details_header">
						<button tabindex="10" accesskey="l" id="ticket_list_button"><span class="fas fa-arrow-left"></span></button>
						<span id="_ticket_number">Ticket # <?= $_GET['help_desk_entry_id'] ?> Not Found</span>
					</div>
					<?php
					$returnArray['ticket_details'] = ob_get_clean();
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("delete from help_desk_entry_activities where help_desk_entry_id = ? and time_submitted < DATE_SUB(NOW(),INTERVAL 1 MINUTE)", $helpDeskEntryId);
				$returnArray['user_list'] = $this->addHelpDeskEntryActivity($helpDeskEntryId);
				ob_start();
				$helpDeskEntryRow = getRowFromId("help_desk_entries", "help_desk_entry_id", $helpDeskEntryId, ($GLOBALS['gUserRow']['superuser_flag'] ? "client_id is not null" : ""));
				$contactDisplayName = htmlText(getDisplayName($helpDeskEntryRow['contact_id'], array("include_company" => true)));
				$contactUserId = Contact::getContactUserId($helpDeskEntryRow['contact_id']);
				if (!empty($contactUserId)) {
					$contactDisplayName .= " (" . getFieldFromId("user_name", "users", "user_id", $contactUserId) . ")";
				}
				if (empty($contactDisplayName)) {
					$contactDisplayName .= getFieldFromId("email_address", "contacts", "contact_id", $helpDeskEntryRow['contact_id']);
				}
				if (empty($contactDisplayName)) {
					$contactDisplayName .= "Anonymous";
				}
				if (canAccessPageCode("CONTACTMAINT")) {
					$contactDisplayName = "<a target='_blank' href='/contactmaintenance.php?clear_filter=true&url_page=show&primary_id=" . $helpDeskEntryRow['contact_id'] . "'>" . $contactDisplayName . "</a>";
				}
				?>

				<div id="_ticket_details_header">
					<button tabindex="10" accesskey="l" id="ticket_list_button"><span class="fas fa-arrow-left"></span></button>
					<span id="_ticket_number">Ticket # <?= $helpDeskEntryRow['help_desk_entry_id'] ?></span> <span id="time_created"><?= date("m/d/Y g:i a", strtotime($helpDeskEntryRow['time_submitted'])) ?></span>
				</div>

				<div id="_others_list"></div>

				<?php
				$notesEntrySection = "";
				?>
				<p class="error-message"></p>
				<div id="_ticket_settings_wrapper">
					<?php
					# Section to add notes

					if (empty($helpDeskEntryRow['time_closed'])) {
					?>
					<div id="_main_details_wrapper">
						<div id="_original_ticket">
							<input type="hidden" id="help_desk_entry_id" value="<?= $helpDeskEntryRow['help_desk_entry_id'] ?>">

							<?php if ($this->iPagePreferences['ticket_user_type'] == "staff") { ?>
								<?= createFormControl("help_desk_entries", "contact_id", array("form_line_classes" => "inline-block", "data_type" => "contact_picker", "form_label" => "Created By", "initial_value" => $helpDeskEntryRow['contact_id'], "classes" => "update-ticket")) ?>
							<?php } else { ?>
								<div class="basic-form-line inline-block">
									<label><?= (array_key_exists("contact_id_label", $labels) ? $labels['contact_id_label'] : "Created By") ?></label>
									<span><?= $contactDisplayName ?></span>
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>
							<?php } ?>

							<?php
							if ($this->iPagePreferences['ticket_user_type'] == "staff") {
								$creatingUserId = Contact::getContactUserId($helpDeskEntryRow['contact_id']);
								if (!empty($creatingUserId)) {
									$creatingUserDisplayName = getDisplayName($helpDeskEntryRow['contact_id'], array("include_company" => true));
									$creatingUserName = $userName = getFieldFromId("user_name", "users", "contact_id", $helpDeskEntryRow['contact_id']);
									$canAccessUserMaint = canAccessPageCode("USERMAINT");
									if ($canAccessUserMaint) {
										$creatingUserName = "<a target='_blank' href='/usermaintenance.php?clear_filter=true&url_page=show&primary_id=" . $creatingUserId . "'>" . $creatingUserName . "</a>";
									}
									$creatingUserType = getReadFieldFromId("description", "user_types", "user_type_id", getFieldFromId("user_type_id", "users", "user_id", $creatingUserId));
									$creatingUserEmail = getFieldFromId("email_address", "contacts", "contact_id", $helpDeskEntryRow['contact_id']);
									$creatingUserPhone = Contact::getContactPhoneNumber($helpDeskEntryRow['contact_id']);
									?>
									<div class="basic-form-line float-right inline-block">
										<label>User</label>
										<span><?= $creatingUserDisplayName . "<br>" . $creatingUserName . (empty($creatingUserType) ? "" : "<br>" . $creatingUserType) . (empty($creatingUserEmail) || $userName == $creatingUserEmail ? "" : "<br>" . $creatingUserEmail) . (empty($creatingUserPhone) ? "" : "<br>" . $creatingUserPhone) ?></span>
										<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
									</div>
									<?php
								} else {
									$creatingUserEmail = getFieldFromId("email_address", "contacts", "contact_id", $helpDeskEntryRow['contact_id']);
									$creatingUserPhone = Contact::getContactPhoneNumber($helpDeskEntryRow['contact_id']);
									?>
									<div class="basic-form-line float-right inline-block">
										<label>Contact Info</label>
										<span><?= $creatingUserEmail . (empty($creatingUserPhone) || empty($creatingUserEmail) ? "" : ", " . $creatingUserPhone) ?></span>
										<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
									</div>
									<?php
								}
							}
							?>
							<div class='clear-div'></div>

							<div class="basic-form-line inline-block" id="_subject_row">
								<label><?= htmlText(getReadFieldFromId("description", "help_desk_types", "help_desk_type_id", $helpDeskEntryRow['help_desk_type_id'], ($GLOBALS['gUserRow']['superuser_flag'] ? "client_id is not null" : ""))) ?></label>
								<input type='hidden' id='existing_description' value='<?= htmlText($helpDeskEntryRow['description']) ?>'>
								<span class='fad fa-edit' id='edit_subject_button'></span><span id='help_desk_entry_subject'><?= htmlText($helpDeskEntryRow['description']) ?></span>
								<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
							</div>

							<div class="basic-form-line inline-block float-right">
								<label><?= (array_key_exists("help_desk_category_id_label", $labels) ? $labels['help_desk_category_id_label'] : "Category") ?></label>
								<?php if (!$this->iHelpDeskAdmin) { ?>
									<span><?= htmlText(getReadFieldFromId("description", "help_desk_categories", "help_desk_category_id", $helpDeskEntryRow['help_desk_category_id'], ($GLOBALS['gUserRow']['superuser_flag'] ? "client_id is not null" : ""))) ?></span>
								<?php } else { ?>
									<span>
        <select id="help_desk_category_id" name="help_desk_category_id">
            <option value="">[None]</option>
<?php
$resultSet = executeQuery("select * from help_desk_categories where help_desk_category_id in (select help_desk_category_id from help_desk_type_categories where " .
	"help_desk_type_id = ?) and inactive = 0 and client_id = ? order by sort_order,description", $helpDeskEntryRow['help_desk_type_id'], $GLOBALS['gClientId']);
while ($row = getNextRow($resultSet)) {
	?>
	<option value="<?= $row['help_desk_category_id'] ?>" <?= ($row['help_desk_category_id'] == $helpDeskEntryRow['help_desk_category_id'] ? " selected" : "") ?>><?= htmlText($row['description']) ?></option>
	<?php
}
?>
        </select>
    </span>
								<?php } ?>
								<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
							</div>

							<div class='clear-div'></div>

							<div class="basic-form-line">
								<label><?= (array_key_exists("content_label", $labels) ? $labels['content_label'] : "Original Message") ?></label>
								<div id="_original_message">
									<?= (hasLegitimateHtml($helpDeskEntryRow['content']) ? makeHtml(cleanHtml($helpDeskEntryRow['content'])) : "<p>" . str_replace("\n", "<br>", str_replace("<", "&lt;", str_replace(">", "&gt;", $helpDeskEntryRow['content']))) . "</p>") ?>
								</div>
								<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
							</div>

							<p id="display_notes_count_wrapper"><a class='jump-link' href='#' data-element_id='notes_wrapper'><span id="display_notes_count">0</span> notes added to ticket.</a></p>
							<?php
							if ($this->iPagePreferences['ticket_user_type'] == "staff") {
								$helpDesk = new HelpDesk($helpDeskEntryRow['help_desk_entry_id']);
								$voteCount = $helpDesk->getVoteCount();
								if ($voteCount > 1) {
									?>
									<p id="display_vote_count"><?= $voteCount ?> upvotes for this ticket.</p>
									<?php
								}
							}
							?>
							<p id="display_files_count_wrapper"><a class='jump-link' href='#' data-element_id='_files_wrapper' id="display_files_count"></a></p>

						</div> <!-- original_ticket -->
						<?php
						ob_start();
						?>
						<div id="_note_section">
							<form id="_help_desk_note_form" enctype='multipart/form-data'>
								<input type="hidden" id="note_help_desk_entry_id" name="help_desk_entry_id"
								       value="<?= $helpDeskEntryRow['help_desk_entry_id'] ?>">
								<input type="hidden" id="note_close_ticket" name="note_close_ticket" value="">
								<input type="hidden" id="public_private" name="public_private" value="public">
								<?php
								if ($this->iPagePreferences['ticket_user_type'] == "staff") {
									$resultSet = executeQuery("select * from help_desk_answers where (help_desk_type_id is null or help_desk_type_id = ?) and client_id = ?" .
										($GLOBALS['gUserRow']['superuser_flag'] ? " or client_id = " . $GLOBALS['gDefaultClientId'] : "") . " order by description", $helpDeskEntryRow['help_desk_type_id'], $GLOBALS['gClientId']);
									if ($resultSet['row_count'] > 0) {
										?>
										<div class="basic-form-line">
											<label>Standard Answers</label>
											<select id="help_desk_answer_id">
												<option value="">[Select]</option>
												<?php
												while ($row = getNextRow($resultSet)) {
													?>
													<option value="<?= $row['help_desk_answer_id'] ?>"><?= htmlText($row['description']) ?></option>
													<?php
												}
												?>
											</select>
											<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
										</div>
										<?php
									}
									?>
									<p class="only-public-note">This note will be seen by the creator of the ticket</p>
									<p class="only-private-note hidden">This will be an internal note</p>
								<?php } else { ?>
									<h3>Add a note to the ticket</h3>
								<?php } ?>
								<textarea tabindex="10" id="note_content" name="content" maxlength="65536" class="ck-editor validate[required]" data-include_mentions="true"></textarea>
								<div id="note_type_wrapper">
									<?php if ($this->iPagePreferences['ticket_user_type'] == "staff") { ?>
										<div id="public_note" class="selected">Public</div>
										<div id="private_note">Internal</div>
									<?php } ?>
								</div>

								<div id="_add_attachments"
								     class="align-right attached-wrapper <?= ($this->iPagePreferences['ticket_user_type'] == "staff" ? "" : "no-private") ?>">
									<button tabindex="10" id="create_note">Create Note</button>
									<button tabindex="10" id="create_note_close">Create Note & Close Ticket</button>
								</div>
							</form>
						</div> <!-- _note_section -->

						<?php
						$notesEntrySection = ob_get_clean();

						} else {
							?>
							<input type="hidden" id="help_desk_entry_id" value="<?= $helpDeskEntryRow['help_desk_entry_id'] ?>">
							<div id="closed_wrapper">
								<div class="basic-form-line">
									<label><?= (array_key_exists("contact_id_label", $labels) ? $labels['contact_id_label'] : "Created By") ?></label>
									<span><?= $contactDisplayName ?></span>
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="basic-form-line" id="_subject_row">
									<label><?= htmlText(getReadFieldFromId("description", "help_desk_types", "help_desk_type_id", $helpDeskEntryRow['help_desk_type_id'], ($GLOBALS['gUserRow']['superuser_flag'] ? "client_id is not null" : ""))) ?></label>
									<span><?= htmlText($helpDeskEntryRow['description']) ?></span>
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="basic-form-line">
									<label><?= (array_key_exists("content_label", $labels) ? $labels['content_label'] : "Original Message") ?></label>
									<div id="_original_message">
										<?= (hasLegitimateHtml($helpDeskEntryRow['content']) ? makeHtml(cleanHtml($helpDeskEntryRow['content'])) : "<p>" . str_replace("\n", "<br>", str_replace("<", "&lt;", str_replace(">", "&gt;", $helpDeskEntryRow['content']))) . "</p>") ?>
									</div>
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>

								<div class="public-note">
									<p>This ticket is closed.</p>
									<?php if (canAccessPageCode("KNOWLEDGEBASEMAINT") && $this->iPagePreferences['ticket_user_type'] == "staff") { ?>
										<p>
											<button id="add_knowledge_base">Add as Knowledge Base Article</button>
										</p>
									<?php } ?>
								</div>
							</div>
							<?php
						}
						if (empty($this->iPagePreferences['notes_entry_below'])) {
							echo $notesEntrySection;
						}

						?>
					</div>

					<?php if ($this->iPagePreferences['ticket_user_type'] != "staff" || !empty($helpDeskEntryRow['time_closed'])) { ?>
						<input type="hidden" name="help_desk_entry_id" id="update_ticket_help_desk_entry_id" value="<?= $helpDeskEntryRow['help_desk_entry_id'] ?>">
					<?php } else { ?>
						<div id="_update_ticket_form_wrapper">
							<form id="_update_ticket_form">
								<input type="hidden" name="help_desk_entry_id" id="update_ticket_help_desk_entry_id" value="<?= $helpDeskEntryRow['help_desk_entry_id'] ?>">
								<input type="hidden" name="new_contact_id" id="new_contact_id" value="">
								<input type="hidden" name="new_help_desk_category_id" id="new_help_desk_category_id"
								       value="">
								<div id="_outer_update_ticket_wrapper">
									<div id="_update_ticket_wrapper">

										<?= createFormControl("help_desk_entries", "user_id", array("data_type" => "user_picker", "form_label" => "Assign to User", "user_presets" => "userPresets", "initial_value" => $helpDeskEntryRow['user_id'], "classes" => "update-ticket")) ?>

										<?php if ($helpDeskEntryRow['user_id'] != $GLOBALS['gUserId']) { ?>
											<div class="basic-form-line">
												<button id="assign_to_me">assign to me</button>
												<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
											</div>
										<?php } ?>
										<?php if (!empty($helpDeskEntryRow['previous_user_id']) && $helpDeskEntryRow['previous_user_id'] != $GLOBALS['gUserId']) { ?>
											<div class="basic-form-line">
												<button id="assign_to_previous" data-previous_user_id="<?= $helpDeskEntryRow['previous_user_id'] ?>">assign to <?= getUserDisplayName($helpDeskEntryRow['previous_user_id'], array("include_company" => false)) ?></button>
											</div>
										<?php } ?>

										<?= createFormControl("help_desk_entries", "user_group_id", array("form_label" => "Assign to User Group", "initial_value" => $helpDeskEntryRow['user_group_id'], "classes" => "update-ticket")) ?>
										<?php

										if (($GLOBALS['gUserId'] == $helpDeskEntryRow['user_id'] || $this->iHelpDeskAdmin)) {
											$usersColumn = new DataColumn("help_desk_entry_users");
											$usersColumn->setControlValue("primary_table", "help_desk_entries");
											$usersColumn->setControlValue("data_type", "custom");
											$usersColumn->setControlValue("control_class", "EditableList");
											$usersColumn->setControlValue("list_table", "help_desk_entry_users");
											$usersColumn->setControlValue("classes", "update-ticket");
											$usersColumn->setControlValue("list_table_controls", array("user_id" => array("user_presets" => "userPresets", "not_null" => false, "classes" => "update-ticket")));

											$helpDeskEntryUsers = new EditableList($usersColumn, $this);
											$returnArray['help_desk_entry_users'] = $helpDeskEntryUsers->getRecord($helpDeskEntryRow['help_desk_entry_id']);
											?>
											<div class="basic-form-line custom-control-form-line custom-control-no-help">
												<label>Other Authorized Users</label>
												<?php
												echo $helpDeskEntryUsers->getControl();
												?>
											</div>
											<?php
										}
										?>
										<?= createFormControl("help_desk_entries", "help_desk_status_id", array("form_label" => "Set Status", "initial_value" => $helpDeskEntryRow['help_desk_status_id'], "classes" => "update-ticket")) ?>
										<?= createFormControl("help_desk_entries", "date_due", array("form_label" => "Date Due", "initial_value" => $helpDeskEntryRow['date_due'], "classes" => "update-ticket datepicker")) ?>
										<?php
										$helpDeskTagCount = getFieldFromId("count(*)", "help_desk_tags");
										if ($helpDeskTagCount > 0) {
											$tagsColumn = new DataColumn("help_desk_tag_links");
											$tagsColumn->setControlValue("primary_table", "help_desk_entries");
											$tagsColumn->setControlValue("data_type", "custom");
											$tagsColumn->setControlValue("control_class", "EditableList");
											$tagsColumn->setControlValue("list_table", "help_desk_tag_links");
											$tagsColumn->setControlValue("classes", "update-ticket");
											$tagsColumn->setControlValue("list_table_controls", array("help_desk_tag_id" => array("remove_add_new" => true, "not_null" => false, "classes" => "update-ticket")));

											$helpDeskTagLinks = new EditableList($tagsColumn, $this);
											$returnArray['help_desk_tag_links'] = $helpDeskTagLinks->getRecord($helpDeskEntryRow['help_desk_entry_id']);
											?>
											<div class="basic-form-line custom-control-form-line custom-control-no-help">
												<label>Tags</label>
												<?php
												echo $helpDeskTagLinks->getControl();
												?>
												<div class='clear-div'></div>
											</div>
											<?php
										}
										?>
										<?= createFormControl("help_desk_entries", "priority", array("form_label" => "Priority", "minimum_value" => "0", "maximum_value" => "9999.99", "initial_value" => $helpDeskEntryRow['priority'], "classes" => "update-ticket")) ?>

										<div class="basic-form-line" id="_merge_into_help_desk_entry_id_row">
											<label>Merge into Ticket</label>
											<span class="help-label">The displayed ticket will become a note in the selected ticket</span>
											<select id="merge_into_help_desk_entry_id"
											        name="merge_into_help_desk_entry_id">
												<option value="">[Select]</option>
												<?php
												$companyId = getFieldFromId("company_id", "contacts", "contact_id", $helpDeskEntryRow['contact_id']);
												if (!empty($companyId)) {
													$companyContactId = getFieldFromId("contact_id", "companies", "company_id", $companyId);
													if (empty(CustomField::getCustomFieldData($companyContactId, "SHARE_COMPANY_TICKETS"))) {
														$companyId = "";
													}
												}
												$resultSet = executeQuery("select * from help_desk_entries where (contact_id = ?" . (empty($companyId) ? "" : " or contact_id in (select contact_id from contacts where company_id = " . $companyId . ")") .
													") and help_desk_entry_id <> ? and time_closed is null order by help_desk_entry_id", $helpDeskEntryRow['contact_id'], $helpDeskEntryRow['help_desk_entry_id']);
												while ($row = getNextRow($resultSet)) {
													?>
													<option value="<?= $row['help_desk_entry_id'] ?>"><?= $row['help_desk_entry_id'] . " - " . htmlText($row['description']) ?></option>
													<?php
												}
												?>
											</select>
											<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
										</div>

										<?php
										$projectId = "";
										if (!empty($helpDeskEntryRow['project_id'])) {
											$query = "select * from projects where project_id = ?";
											$parameters = array($helpDeskEntryRow['project_id']);
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
										}
										if (!empty($projectId)) {
											?>
											<div class="basic-form-line" id="_project_id_row">
												<label>Project</label>
												<p><a href='/project.php?id=<?= $projectId ?>' target="_blank"><?= htmlText($row['description']) ?></a></p>
												<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
											</div>
										<?php } ?>

										<?php
										if ($this->iPagePreferences['ticket_user_type'] == "staff" && $this->iHelpDeskAdmin && canAccessPageCode("HELPDESKIGNOREEMAILADDRESSMAINTENANCE") && empty($helpDeskEntryRow['time_closed'])) {
											$potentialSpamId = getFieldFromId("help_desk_entry_id", "help_desk_entries", "help_desk_entry_id", $helpDeskEntryRow['help_desk_entry_id'],
												"help_desk_entry_id not in (select help_desk_entry_id from help_desk_public_notes) and help_desk_entry_id not in (select help_desk_entry_id from help_desk_private_notes)");
											if (!empty($potentialSpamId)) {
												?>
												<p>
													<button id='mark_spam'>Mark As Spam</button>
												</p>
												<?php
											}
										}
										$customFields = CustomField::getCustomFields("help_desk");
										foreach ($customFields as $thisCustomField) {
											$helpDeskTypeCustomFieldId = getFieldFromId("help_desk_type_custom_field_id", "help_desk_type_custom_fields", "custom_field_id", $thisCustomField['custom_field_id'],
												"help_desk_type_id = (select help_desk_type_id from help_desk_entries where help_desk_entry_id = ?)", $helpDeskEntryId);
											if (empty($helpDeskTypeCustomFieldId)) {
												continue;
											}
											$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
											if ($this->iPagePreferences['ticket_user_type'] == "staff") {
												echo $customField->getControl(array("basic_form_line"=>true, "primary_id" => $helpDeskEntryRow['help_desk_entry_id'], "classes" => "update-ticket"));
											} else {
												echo $customField->displayData($helpDeskEntryRow['help_desk_entry_id']);
											}
										}
										?>
									</div> <!-- update_ticket_wrapper -->
								</div>
							</form>
						</div>
						<?php
					}
					?>
				</div> <!-- ticket_settings_wrapper -->
				<?php

				if (empty($helpDeskEntryRow['time_closed'])) {
					?>
					<div class="basic-form-line" id="_close_ticket_button_row">
						<button tabindex="10" id="close_ticket_button">Close Ticket</button>
						<?php if (hasPageCapability("DELETE_TICKET")) { ?>
							<button tabindex="10" id="delete_ticket_button">Delete Ticket</button>
						<?php } ?>
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
					</div>
					<?php
				} else {
					?>
					<div class="basic-form-line" id="_open_ticket_button_row">
						<button tabindex="10" id="reopen_ticket_button">Reopen Ticket</button>
					</div>
					<?php
				}

				# Section for Help Desk list items

				if ($this->iPagePreferences['ticket_user_type'] == "staff") {
					?>
					<div id="_list_items_wrapper">
						<h2>Checklist</h2>
						<?php
						if (empty($helpDeskEntryRow['time_closed'])) {
							$resultSet = executeQuery("select * from help_desk_checklists where inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
							if ($resultSet['row_count'] > 0) {
								?>
								<div class='basic-form-line'>
									<label>Predefined Checklists</label>
									<select id="help_desk_checklist_id" name="help_desk_checklist_id">
										<option value="">[Select]</option>
										<?php
										while ($row = getNextRow($resultSet)) {
											?>
											<option value="<?= $row['help_desk_checklist_id'] ?>"><?= htmlText($row['description']) ?></option>
											<?php
										}
										?>
									</select>
									<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
								</div>
								<?php
							}
						}
						?>
						<div id="_help_desk_entry_list_items">
						</div>
						<?php if (empty($helpDeskEntryRow['time_closed'])) { ?>
							<p id="_add_list_item_wrapper">
								<button id="add_list_item">Add item</button>
							</p>
						<?php } ?>
					</div>
					<?php
				}

				?>
				<div id="_files_wrapper">
					<h2>Files & Images</h2>
					<?php if ($this->iPagePreferences['ticket_user_type'] == "staff") { ?>
						<p><input type="checkbox" value="1" id="private_access" name='private_access'><label class='checkbox-label' for='private_access'>Make uploaded files internal, so they can't be seen by the creator of the ticket.</label></p>
						<p class='red-text'>Dropped files and images will be viewable by the creator of the ticket unless the checkbox above is selected.</p>
					<?php } ?>
					<div id="file_content_headers">
						<h3>Images</h3>
						<h3>Files</h3>
						<h3>Add Others</h3>
					</div>
					<div id="file_content_wrapper">
						<div id="help_desk_entry_images">
						</div>
						<div id="help_desk_entry_files">
						</div>
						<div id="help_desk_drop_zone" class='dropzone'>
							<div class='dz-message'>
								<p class="align-left">Drop files or images here or click to upload.</p>
							</div>
						</div>
					</div>
				</div>

				<h2>Notes</h2>
				<div id="notes_wrapper">
					<?php
					if ($this->iPagePreferences['ticket_user_type'] == "staff") {
						$resultSet = executeQuery("select help_desk_public_note_id as note_id,user_id,time_submitted,content,'public' as note_type from help_desk_public_notes where help_desk_entry_id = ? " .
							"union select help_desk_private_note_id as note_id,user_id,time_submitted,content,'private' as note_type from help_desk_private_notes where help_desk_entry_id = ? " .
							"order by time_submitted desc", $helpDeskEntryRow['help_desk_entry_id'], $helpDeskEntryRow['help_desk_entry_id']);
					} else {
						$resultSet = executeQuery("select help_desk_public_note_id as note_id,user_id,time_submitted,content,'public' as note_type from help_desk_public_notes where help_desk_entry_id = ? " .
							"order by time_submitted desc", $helpDeskEntryRow['help_desk_entry_id']);
					}
					?>
					<input type="hidden" id="notes_count" value="<?= $resultSet['row_count'] ?>">
					<?php
					while ($row = getNextRow($resultSet)) {
						if ($this->iPagePreferences['ticket_user_type'] != "staff" && $row['note_type'] == "private") {
							continue;
						}
						$userImageId = Contact::getUserContactField($row['user_id'], "image_id");
						$postedBySuperuser = getFieldFromId("superuser_flag", "users", "user_id", $row['user_id']);
						?>
						<div class="ticket-note <?= $row['note_type'] ?>-note"
						     id="ticket_note_<?= $row['note_type'] ?>_<?= $row['note_id'] ?>">
							<p class="highlighted-text"><?= ($row['note_type'] == "private" ? "PRIVATE NOTE " : "") ?>Posted
								by <?= (empty($row['user_id']) ? $row['email_address'] : ($row['user_id'] == $GLOBALS['gUserId'] ? "yourself" : (empty($userImageId) ? "" : "<a class='pretty-photo' href='/getimage.php?id=" . $userImageId . "'>") .
									getUserDisplayName($row['user_id'], array("include_company" => false)) . (empty($userImageId) ? "" : "</a>"))) ?>
								on <?= date("m/d/Y g:ia", strtotime($row['time_submitted'])) ?></p>
							<?= (hasLegitimateHtml($row['content']) ? makeHtml(cleanHtml($row['content'])) : "<p>" . str_replace("\n", "<br>", str_replace("<", "&lt;", str_replace(">", "&gt;", $row['content']))) . "</p>") ?>
						</div>
						<?php
					}
					?>
				</div>
				<?php
				if (!empty($this->iPagePreferences['notes_entry_below'])) {
					echo $notesEntrySection;
				}
				?>
				<p id="_show_changes_wrapper">
					<button id="_changes_button">Show Changes</button>
				</p>
				<?php
				if ($this->iPagePreferences['ticket_user_type'] == "staff") {
					$helpDeskEntryReviewId = getFieldFromId("help_desk_entry_review_id", "help_desk_entry_reviews", "help_desk_entry_id", $helpDeskEntryRow['help_desk_entry_id']);
					if (!empty($helpDeskEntryReviewId)) {
						?>
						<p>
							<button id="_view_review_button">View Customer Review</button>
						</p>
						<div id="_customer_review"></div>
						<?php
					}
				}

				$returnArray['ticket_details'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "get_ticket_list":
				$this->iPagePreferences = Page::getPagePreferences("HELPDESKDASHBOARD");
				$helpDeskStaff = ($this->iPagePreferences['ticket_user_type'] == "staff");
				ob_start();
				if (!empty($GLOBALS['gDomainClientId']) || !$GLOBALS['gUserRow']['superuser_flag']) {
					$this->iPagePreferences['client_id'] = "";
				}

				$whereStatement = "";
				$parameters = array();

				if (!empty($_POST['ticket_type'])) {
					$this->iPagePreferences['ticket_type'] = $_POST['ticket_type'];
				}

				if ($helpDeskStaff) {
					switch ($this->iPagePreferences['ticket_type']) {
						case "group":
							$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(user_id = ? or user_group_id in (select user_group_id from user_group_members where user_id = ?))";
							$parameters[] = $GLOBALS['gUserId'];
							$parameters[] = $GLOBALS['gUserId'];
							break;
						case "unassigned":
							if ($this->iHelpDeskAdmin) {
								$whereStatement .= (empty($whereStatement) ? "" : " and ") . "user_id is null";
								break;
							}
						case "user":
							if ($this->iHelpDeskAdmin) {
								if (empty($this->iPagePreferences['preferences_user_id'])) {
									$whereStatement .= (empty($whereStatement) ? "" : " and ") . "user_group_id = ?";
									$parameters[] = $this->iPagePreferences['preferences_user_group_id'];
								} else {
									$whereStatement .= (empty($whereStatement) ? "" : " and ") . "user_id = ?";
									$parameters[] = $this->iPagePreferences['preferences_user_id'];
								}
								break;
							}
						case "all":
							if (isInUserGroupCode($GLOBALS['gUserId'], "HELP_DESK") || $this->iHelpDeskAdmin) {
								break;
							}
						case "assigned_plus":
							if ($this->iHelpDeskAdmin) {
								$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(user_id is null or user_id = ?)";
								$parameters[] = $GLOBALS['gUserId'];
								break;
							}
						case "commented":
							if ($this->iHelpDeskAdmin) {
								$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(help_desk_entry_id in (select help_desk_entry_id from help_desk_public_notes where user_id = ?)"
									. " or help_desk_entry_id in (select help_desk_entry_id from help_desk_private_notes where user_id = ?))";
								$parameters[] = $GLOBALS['gUserId'];
								$parameters[] = $GLOBALS['gUserId'];
								break;
							}
						case "my_tickets":
							$whereStatement .= (empty($whereStatement) ? "" : " and ") . "contact_id = ?";
							$parameters[] = $GLOBALS['gUserRow']['contact_id'];
							break;
						default:
							$whereStatement .= (empty($whereStatement) ? "" : " and ") . "user_id = ?";
							$parameters[] = $GLOBALS['gUserId'];
							break;
					}
				} else {
					$parameters = array($GLOBALS['gUserRow']['contact_id']);
					$companyId = $GLOBALS['gUserRow']['company_id'];
					if (!empty($companyId)) {
						$companyContactId = getFieldFromId("contact_id", "companies", "company_id", $companyId);
						if (empty(CustomField::getCustomFieldData($companyContactId, "SHARE_COMPANY_TICKETS"))) {
							$companyId = "";
						}
					}
					$whereStatement = (empty($whereStatement) ? "" : " and ") . "(contact_id = ?" . (empty($companyId) ? "" : " or contact_id in (select contact_id from contacts where company_id = " .
							$companyId . ")") . ")";
				}
				if (!empty($whereStatement)) {
					$whereStatement = "(" . $whereStatement . " or help_desk_entry_id in (select help_desk_entry_id from help_desk_entry_users where user_id = ?))";
					$parameters[] = $GLOBALS['gUserId'];
				}
				if (!empty($_GET['search_text'])) {
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(description like ? or content like ? or " .
						"help_desk_entry_id in (select help_desk_entry_id from help_desk_public_notes where content like ?) or " .
						"help_desk_entry_id in (select help_desk_entry_id from help_desk_private_notes where content like ?)";
					$parameters[] = "%" . $_GET['search_text'] . "%";
					$parameters[] = "%" . $_GET['search_text'] . "%";
					$parameters[] = "%" . $_GET['search_text'] . "%";
					$parameters[] = "%" . $_GET['search_text'] . "%";
					if (is_numeric($_GET['search_text'])) {
						$whereStatement .= " or help_desk_entry_id = ?";
						$parameters[] = $_GET['search_text'];
					} else {
						$whereStatement .= " or contact_id in (select contact_id from contacts where first_name like ? or last_name like ? or business_name like ? or email_address like ?)";
						$parameters[] = $_GET['search_text'] . "%";
						$parameters[] = $_GET['search_text'] . "%";
						$parameters[] = $_GET['search_text'] . "%";
						$parameters[] = $_GET['search_text'] . "%";
						$searchParts = explode(" ", $_GET['search_text']);
						if (count($searchParts) == 2) {
							$whereStatement .= " or contact_id in (select contact_id from contacts where first_name like ? and last_name like ?)";
							$parameters[] = $searchParts[0] . "%";
							$parameters[] = $searchParts[1] . "%";
						}
						$whereStatement .= " or (user_id is not null and user_id in (select user_id from users where contact_id in " .
							"(select contact_id from contacts where first_name like ? or last_name like ?)))";
						$parameters[] = $_GET['search_text'] . "%";
						$parameters[] = $_GET['search_text'] . "%";
						if (count($searchParts) == 2) {
							$whereStatement .= " or (user_id is not null and user_id in (select user_id from users where contact_id in " .
								"(select contact_id from contacts where first_name like ? and last_name like ?)))";
							$parameters[] = $searchParts[0] . "%";
							$parameters[] = $searchParts[1] . "%";
						}
					}
					$whereStatement .= ")";
				}
				if (!empty($GLOBALS['gDomainClientId']) || !$GLOBALS['gUserRow']['superuser_flag']) {
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "client_id = ?";
					$parameters[] = $GLOBALS['gClientId'];
				} else {
					if (!empty($this->iPagePreferences['client_id'])) {
						$whereStatement .= (empty($whereStatement) ? "" : " and ") . "client_id = ?";
						$parameters[] = $this->iPagePreferences['client_id'];
					}
				}
				$customSelect = $_POST['custom_select'];
				if (!empty($customSelect)) {
					$this->iPagePreferences['hide_closed'] = true;
					$this->iPagePreferences['since_time_closed'] = "";
					$this->iPagePreferences['only_closed'] = false;
					$this->iPagePreferences['new_tickets'] = false;
					$this->iPagePreferences['help_desk_status_id'] = "";
					switch ($customSelect) {
						case "open_ticket":
							break;
						case "new_ticket":
							$this->iPagePreferences['new_tickets'] = true;
							break;
						case "help_desk_status":
							if (array_key_exists("help_desk_status_ids", $_POST)) {
								$this->iPagePreferences['help_desk_status_ids'] = $_POST['help_desk_status_ids'];
							}
							$helpDeskStatusIds = array_filter(explode(",", $this->iPagePreferences['help_desk_status_ids']));
							if (array_key_exists("help_desk_status_id", $_POST)) {
								$helpDeskStatusId = getFieldFromId("help_desk_status_id", "help_desk_statuses", "help_desk_status_id", $_POST['help_desk_status_id']);
								if (!empty($helpDeskStatusId)) {
									if (in_array($_POST['help_desk_status_id'], $helpDeskStatusIds)) {
										$helpDeskStatusIds = array_diff($helpDeskStatusIds, array($helpDeskStatusId));
									} else {
										$helpDeskStatusIds[] = $helpDeskStatusId;
									}
								}
							}
							$this->iPagePreferences['help_desk_status_ids'] = $returnArray['help_desk_status_ids'] = implode(",", $helpDeskStatusIds);
							break;
						case "closed_today":
							$this->iPagePreferences['hide_closed'] = false;
							$this->iPagePreferences['since_time_closed'] = date("Y-m-d");
							$this->iPagePreferences['only_closed'] = true;
							break;
						case "closed_week":
							$this->iPagePreferences['hide_closed'] = false;
							$this->iPagePreferences['since_time_closed'] = date("Y-m-d", strtotime('-7 days'));
							$this->iPagePreferences['only_closed'] = true;
							break;
						case "total_closed":
							$this->iPagePreferences['hide_closed'] = false;
							$this->iPagePreferences['only_closed'] = true;
							break;
					}
				} else {
					$this->iPagePreferences['since_time_closed'] = "";
					$this->iPagePreferences['only_closed'] = false;
					$this->iPagePreferences['new_tickets'] = false;
					$this->iPagePreferences['help_desk_status_id'] = "";
				}
				if (!empty($this->iPagePreferences['help_desk_status_ids'])) {
					$helpDeskStatusIds = array_filter(explode(",", $this->iPagePreferences['help_desk_status_ids']));
					if (!empty($helpDeskStatusIds)) {
						$helpDeskStatusIdString = "";
						foreach ($helpDeskStatusIds as $thisHelpDeskStatusId) {
							$thisHelpDeskStatusId = getFieldFromId("help_desk_status_id", "help_desk_statuses", "help_desk_status_id", $thisHelpDeskStatusId);
							if (!empty($thisHelpDeskStatusId)) {
								$helpDeskStatusIdString .= (empty($helpDeskStatusIdString) ? "" : ",") . $thisHelpDeskStatusId;
							}
						}
					}
					$this->iPagePreferences['help_desk_status_ids'] = $helpDeskStatusIdString;
				}
				Page::setPagePreferences($this->iPagePreferences, "HELPDESKDASHBOARD");
				if (!empty($this->iPagePreferences['new_tickets'])) {
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "time_closed is null and help_desk_status_id is null and " .
						"help_desk_entry_id not in (select help_desk_entry_id from help_desk_public_notes) and " .
						"help_desk_entry_id not in (select help_desk_entry_id from help_desk_private_notes)";
				}
				if (!empty($this->iPagePreferences['assigned_user_id'])) {
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "user_id = ?";
					$parameters[] = $this->iPagePreferences['assigned_user_id'];
				}
				if (!empty($this->iPagePreferences['assigned_user_group_id'])) {
					$userSet = executeQuery("select user_id from user_group_members where user_group_id = ?", $this->iPagePreferences['assigned_user_group_id']);
					$userIds = array();
					while ($userRow = getNextRow($userSet)) {
						$userIds[] = $userRow['user_id'];
					}
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(user_group_id = ?" . (empty($userIds) ? "" : " or user_id in (" . implode(",", $userIds) . ") or " .
							"help_desk_entry_id in (select help_desk_entry_id from help_desk_entry_users where user_id in (" . implode(",", $userIds) . "))") .
						($this->iPagePreferences['ticket_type'] == "assigned_plus" ? " or user_id is null" : "") . ")";
					$parameters[] = $this->iPagePreferences['assigned_user_group_id'];
				}
				if (!empty($this->iPagePreferences['hide_closed'])) {
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "time_closed is null";
				}
				if (!empty($this->iPagePreferences['only_with_reviews'])) {
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "help_desk_entry_id in (select help_desk_entry_id from help_desk_entry_reviews)";
				}
				if (!empty($this->iPagePreferences['only_closed'])) {
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "time_closed is not null";
				}
				if (!empty($this->iPagePreferences['help_desk_tag_id'])) {
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "help_desk_entry_id in (select help_desk_entry_id from help_desk_tag_links where help_desk_tag_id = ?)";
					$parameters[] = $this->iPagePreferences['help_desk_tag_id'];
				}
				if (!empty($this->iPagePreferences['help_desk_tag_group_id'])) {
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "help_desk_entry_id in (select help_desk_entry_id from help_desk_tag_links where help_desk_tag_id in (select help_desk_tag_id from help_desk_tags where help_desk_tag_group_id = ?))";
					$parameters[] = $this->iPagePreferences['help_desk_tag_group_id'];
				}
				$helpDeskStatusIds = array_filter(explode(",", $this->iPagePreferences['help_desk_status_ids']));
				if (!empty($this->iPagePreferences['since_time_closed'])) {
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "time_closed >= ?";
					$parameters[] = date("Y-m-d", strtotime($this->iPagePreferences['since_time_closed']));
				}
				if (!empty($this->iPagePreferences['start_date_submitted'])) {
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "time_submitted >= ?";
					$parameters[] = date("Y-m-d", strtotime($this->iPagePreferences['start_date_submitted']));
				}
				if (!empty($this->iPagePreferences['end_date_submitted'])) {
					$whereStatement .= (empty($whereStatement) ? "" : " and ") . "time_submitted <= ?";
					$parameters[] = date("Y-m-d 23:59:59", strtotime($this->iPagePreferences['end_date_submitted']));
				}
				$resultSet = executeQuery("select *,(select group_concat(description order by help_desk_tags.sort_order,help_desk_tags.description SEPARATOR ', ') from help_desk_tags where " .
					"help_desk_tag_id in (select help_desk_tag_id from help_desk_tag_links where help_desk_entry_id = help_desk_entries.help_desk_entry_id)) as help_desk_tag_ids from help_desk_entries " . (empty($whereStatement) ? "" : "where " . $whereStatement) .
					" order by time_submitted desc" . (empty($this->iPagePreferences['maximum_list_count']) ? " limit 100" : " limit " . $this->iPagePreferences['maximum_list_count']), $parameters);
				$helpDeskEntryRows = array();
				while ($row = getNextRow($resultSet)) {
					$helpDeskEntryRows[] = $row;
				}
				if ($helpDeskStaff) {
					$countUserFilter = "";
					switch ($this->iPagePreferences['ticket_type']) {
						case "group":
							$countUserFilter .= " and (user_id = " . $GLOBALS['gUserId'] . " or user_group_id in (select user_group_id from user_group_members where user_id = " . $GLOBALS['gUserId'] . "))";
							break;
						case "unassigned":
							if ($this->iHelpDeskAdmin) {
								$countUserFilter .= " and user_id is null";
								break;
							}
						case "user":
							if ($this->iHelpDeskAdmin) {
								if (empty($this->iPagePreferences['preferences_user_id'])) {
									$countUserFilter .= " and user_group_id = " . makeNumberParameter($this->iPagePreferences['preferences_user_group_id']);
								} else {
									$countUserFilter .= " and user_id = " . makeNumberParameter($this->iPagePreferences['preferences_user_id']);
								}
								break;
							}
						case "all":
							if (isInUserGroupCode($GLOBALS['gUserId'], "HELP_DESK") || $this->iHelpDeskAdmin) {
								break;
							}
						case "assigned_plus":
							if ($this->iHelpDeskAdmin) {
								$countUserFilter .= " and (user_id is null or user_id = " . $GLOBALS['gUserId'] . ")";
								break;
							}
						case "commented":
							if ($this->iHelpDeskAdmin) {
								$countUserFilter .= " and (help_desk_entry_id in (select help_desk_entry_id from help_desk_public_notes where user_id = " . $GLOBALS['gUserId'] . ")"
									. " or help_desk_entry_id in (select help_desk_entry_id from help_desk_private_notes where user_id = " . $GLOBALS['gUserId'] . "))";
								break;
							}

						case "my_tickets":
							$countUserFilter .= " and (help_desk_entries.contact_id = " . $GLOBALS['gUserRow']['contact_id'] . ")";
							break;

						default:
							$countUserFilter .= " and user_id = " . $GLOBALS['gUserId'];
							break;

					}

					/*
					 * Options
					 * Open
					 * Closed today
					 * Closed this week
					 * Total Closed
					 */

					$ticketSelection = "";
					switch ($this->iPagePreferences['ticket_type']) {
						case "assigned_plus":
							$ticketSelection = "Assigned To Me & Unassigned";
							break;
						case "group":
							$ticketSelection = "Assigned To My Group(s)";
							break;
						case "unassigned":
							$ticketSelection = "Unassigned";
							break;
						case "all":
							$ticketSelection = "ALL Tickets";
							break;
						case "assigned":
							$ticketSelection = "Assigned To Me";
							break;
						case "my_tickets":
							$ticketSelection = "My Tickets";
							break;
						case "commented":
							$ticketSelection = "Tickets with my notes";
							break;
					}

					?>
					<div id="stats_line">
						<div class='ticket-stat' id="ticket_chooser">
							<div class="ticket-stat-content">
								<p class="ticket-stat-number"><?= $ticketSelection ?></p>
								<p class="ticket-stat-tag"><span class='fas fa-chevron-down'></span></p>
							</div>
							<div id="_ticket_choices" class='hidden'>
								<ul>
									<li data-ticket_type="assigned"<?= ($this->iPagePreferences['ticket_type'] == "assigned" ? " class='selected'" : "") ?>>Assigned to Me</li>
									<li data-ticket_type="assigned_plus"<?= ($this->iPagePreferences['ticket_type'] == "assigned_plus" ? " class='selected'" : "") ?>>Assigned to Me & Unassigned</li>
									<li data-ticket_type="group"<?= ($this->iPagePreferences['ticket_type'] == "group" ? " class='selected'" : "") ?>>Assigned to My Group(s)</li>
									<li data-ticket_type="unassigned"<?= ($this->iPagePreferences['ticket_type'] == "unassigned" ? " class='selected'" : "") ?>>Unassigned</li>
									<li data-ticket_type="my_tickets"<?= ($this->iPagePreferences['ticket_type'] == "my_tickets" ? " class='selected'" : "") ?>>My Tickets</li>
									<li data-ticket_type="commented"<?= ($this->iPagePreferences['ticket_type'] == "commented" ? " class='selected'" : "") ?>>Tickets with my notes</li>
									<li data-ticket_type="all"<?= ($this->iPagePreferences['ticket_type'] == "all" ? " class='selected'" : "") ?>>All Tickets</li>
								</ul>
							</div>
						</div>
						<?php
						$countSet = executeReadQuery("select count(*) from help_desk_entries where time_closed is null" . $countUserFilter .
							(!empty($GLOBALS['gDomainClientId']) || !$GLOBALS['gUserRow']['superuser_flag'] ? " and client_id = " . $GLOBALS['gClientId'] : ""));
						$countRow = getNextRow($countSet);
						?>
						<div data-custom_select="open_ticket" class="ticket-stat">
							<div class="ticket-stat-content">
								<p class="ticket-stat-number"><?= $countRow['count(*)'] ?></p>
								<p class="ticket-stat-tag">Open</p>
							</div>
						</div>
						<?php
						$countSet = executeReadQuery("select count(*) from help_desk_entries where time_closed is not null and time_closed >= current_date" . $countUserFilter .
							(!empty($GLOBALS['gDomainClientId']) || !$GLOBALS['gUserRow']['superuser_flag'] ? " and client_id = " . $GLOBALS['gClientId'] : ""));
						$countRow = getNextRow($countSet);
						?>
						<div data-custom_select="closed_today" class="ticket-stat">
							<div class="ticket-stat-content">
								<p class="ticket-stat-number"><?= $countRow['count(*)'] ?></p>
								<p class="ticket-stat-tag">Closed Today</p>
							</div>
						</div>
						<?php
						$countSet = executeReadQuery("select count(*) from help_desk_entries where time_closed is not null and time_closed >= date_sub(current_date,interval 7 day)" . $countUserFilter .
							(!empty($GLOBALS['gDomainClientId']) || !$GLOBALS['gUserRow']['superuser_flag'] ? " and client_id = " . $GLOBALS['gClientId'] : ""));
						$countRow = getNextRow($countSet);
						?>
						<div data-custom_select="closed_week" class="ticket-stat">
							<div class="ticket-stat-content">
								<p class="ticket-stat-number"><?= $countRow['count(*)'] ?></p>
								<p class="ticket-stat-tag">Closed Week</p>
							</div>
						</div>
						<?php
						$countSet = executeReadQuery("select count(*) from help_desk_entries where time_closed is not null" . $countUserFilter .
							(!empty($GLOBALS['gDomainClientId']) || !$GLOBALS['gUserRow']['superuser_flag'] ? " and client_id = " . $GLOBALS['gClientId'] : ""));
						$countRow = getNextRow($countSet);
						?>
						<div data-custom_select="total_closed" class="ticket-stat">
							<div class="ticket-stat-content">
								<p class="ticket-stat-number"><?= $countRow['count(*)'] ?></p>
								<p class="ticket-stat-tag">Total Closed</p>
							</div>
						</div>
						<?php
						if (empty($_SESSION['help_desk_statistics'])) {
							ob_start();
							$countSet = executeQuery("select avg(TIMESTAMPDIFF(SECOND,time_submitted,time_closed)) as time_diff from help_desk_entries where " .
								"(help_desk_status_id is null or help_desk_status_id not in (select help_desk_status_id from help_desk_statuses where long_term_project = 1 or exclude_from_stats = 1)) and " .
								"help_desk_entry_id not in (select help_desk_entry_id from help_desk_tag_links where help_desk_tag_id in (select help_desk_tag_id from help_desk_tags where exclude_from_stats = 1)) and time_closed is not null" . $countUserFilter .
								(!empty($GLOBALS['gDomainClientId']) || !$GLOBALS['gUserRow']['superuser_flag'] ? " and client_id = " . $GLOBALS['gClientId'] : "") . " and time_submitted > date_sub(now(),interval 2 month)");
							$countRow = getNextRow($countSet);
							if (!empty($countRow['time_diff'])) {
								?>
								<div class="ticket-stat" id="_average_resolution">
									<div class="ticket-stat-content">
										<?php
										$resolutionTime = "";
										$totalSeconds = $countRow['time_diff'];
										$days = floor($totalSeconds / 86400);
										if ($days > 0) {
											$resolutionTime .= (empty($resolutionTime) ? "" : ", ") . $days . "&nbsp;day" . ($days == 1 ? "" : "s");
										}
										$hours = floor(($countRow['time_diff'] / 3600) % 24);
										if ($hours > 0) {
											$resolutionTime .= (empty($resolutionTime) ? "" : ", ") . $hours . "&nbsp;hr" . ($hours == 1 ? "" : "s");
										}
										$minutes = floor(($countRow['time_diff'] / 60) % 60);
										if ($minutes > 0) {
											$resolutionTime .= (empty($resolutionTime) ? "" : ", ") . $minutes . "&nbsp;min" . ($minutes == 1 ? "" : "s");
										}
										?>
										<p class="ticket-stat-number" id="resolution_time"><?= $resolutionTime ?></p>
										<p class="ticket-stat-tag">Avg Resolution</p>
									</div>
								</div>
								<?php
							}
							$_SESSION['help_desk_statistics'] = ob_get_clean();
							saveSessionData();
						}
						echo $_SESSION['help_desk_statistics'];
						?>
					</div>
					<?php
					if (empty($this->iPagePreferences['hide_stats'])) {
						?>
						<div id="additional_stats_line">
						<span class='fad fa<?= (empty($helpDeskStatusIds) ? "-check" : "") ?>-square' id='select_all_statuses'></span>
						<?php

						if (!empty($GLOBALS['gDomainClientId']) || !$GLOBALS['gUserRow']['superuser_flag']) {
							$statusSet = executeQuery("select * from help_desk_statuses where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
							while ($statusRow = getNextRow($statusSet)) {
								$count = 0;
								foreach ($helpDeskEntryRows as $row) {
									if ($row['help_desk_status_id'] == $statusRow['help_desk_status_id']) {
										$count++;
									}
								}
								?>
								<div id='help_desk_status_selector_<?= $statusRow['help_desk_status_id'] ?>' <?= (empty($statusRow['display_color']) ? "" : "style='background-color: " . $statusRow['display_color'] . ";' ") ?>data-custom_select="help_desk_status" data-help_desk_status_id="<?= $statusRow['help_desk_status_id'] ?>" class="ticket-stat help-desk-status<?= (in_array($statusRow['help_desk_status_id'], $helpDeskStatusIds) ? " selected" : "") ?>">
									<div class="ticket-stat-content">
										<span class='selected-icon fad fa-check-circle'></span>
										<p class="ticket-stat-number"><?= $count ?></p>
										<p class="ticket-stat-tag"><?= $statusRow['description'] ?></p>
									</div>
								</div>
								<?php
							}
						}
					}
					?>
					</div> <!-- additional_stats_line -->

				<?php } ?>
				<div id="options_line">
					<div id="options_line_buttons">
						<button tabindex="10" id="_preferences_button"><span class="fas fa-cog"></span>Preferences</button>
						<button tabindex="10" id="_filters_button"><span class="fad fa-sliders-h"></span>Filters</button>
						<button tabindex="10" id="_add_ticket_button"><span class="fas fa-plus-square"></span>New Ticket</button>
						<?php if ($helpDeskStaff) { ?>
							<button tabindex="10" id="_mass_edit_button" class="hidden"><span class="fas fa-edit"></span>Edit Selected</button>
						<?php } ?>
					</div>
					<div id="_search_block">
						<input tabindex="5" type="text" id="text_filter" placeholder="Filter">
						<input tabindex="5" type="text" id="search_text" name="search_text" placeholder="Search" value="<?= htmlText($_GET['search_text']) ?>">
						<span id="_search_icon" class="fa fa-search"></span>
					</div>
				</div>
				<?php
				if (!empty($helpDeskStatusIds)) {
					$newHelpDeskEntryRows = array();
					foreach ($helpDeskEntryRows as $row) {
						if (empty($row['help_desk_status_id']) || in_array($row['help_desk_status_id'], $helpDeskStatusIds)) {
							$newHelpDeskEntryRows[] = $row;
						}
					}
					$helpDeskEntryRows = $newHelpDeskEntryRows;
				}
				if (count($helpDeskEntryRows) > 0) {
					?>
					<p id="_ticket_count_wrapper"><span id="_ticket_count"></span> listed</p>
					<table class="grid-table header-sortable empty-last" id="ticket_table" data-after_sort="afterSortList">
						<tr class="header-row">
							<?php if ($helpDeskStaff) { ?>
								<th id="_selected_header" class='not-sortable align-center'><span class='far fa-square'></span></th>
							<?php } ?>
							<th data-column_name="help_desk_entry_id" class="<?= ($this->iPagePreferences['column_name'] == "help_desk_entry_id" ? "header-sorted-column header-sorted-" . ($this->iPagePreferences['sort_direction'] == -1 ? "down" : "up") : "") ?>">
								#<?= ($this->iPagePreferences['column_name'] == "help_desk_entry_id" ? "<span class='fad fa-sort-alpha-down" . ($this->iPagePreferences['sort_direction'] == -1 ? "-alt" : "") . "'></span>" : "") ?></th>
							<?php
							$columnList = array_filter(explode(",", $this->iPagePreferences['list_columns']));
							if (empty($columnList)) {
								foreach ($this->iColumnChoices as $columnName => $columnInfo) {
									$columnList[] = $columnName;
									if (count($columnList) >= 8) {
										break;
									}
								}
							}
							foreach ($columnList as $columnName) {
								if (!array_key_exists($columnName, $this->iColumnChoices)) {
									continue;
								}
								$thisColumnInfo = $this->iColumnChoices[$columnName];
								if (!$helpDeskStaff && !empty($thisColumnInfo['staff_only'])) {
									continue;
								}
								?>
								<th data-column_name="<?= $columnName ?>"
								    class="<?= ($this->iPagePreferences['column_name'] == $columnName ? "header-sorted-column header-sorted-" . ($this->iPagePreferences['sort_direction'] == -1 ? "down" : "up") : "") ?>"><?= htmlText($thisColumnInfo['description']) ?><?= ($this->iPagePreferences['column_name'] == $columnName ? "<span class='fad fa-sort-alpha-down" . ($this->iPagePreferences['sort_direction'] == -1 ? "-alt" : "") . "'></span>" : "") ?></th>
								<?php
							}

							$helpDeskEntries = array();
							foreach ($helpDeskEntryRows as $row) {
								$status = (!empty($row['time_closed']) ? "Closed" : getReadFieldFromId("description", "help_desk_statuses", "help_desk_status_id", $row['help_desk_status_id'], ($GLOBALS['gUserRow']['superuser_flag'] && empty($GLOBALS['gDomainClientId']) ? "client_id is not null" : "")));
								$activitySet = executeQuery("select time_submitted,user_id,email_address from help_desk_public_notes where help_desk_entry_id = ?" .
									($helpDeskStaff ? " union select time_submitted,user_id,null as email_address from help_desk_private_notes where help_desk_entry_id = " . $row['help_desk_entry_id'] : "") . " order by time_submitted desc", $row['help_desk_entry_id']);
								if ($activityRow = getNextRow($activitySet)) {
									$lastActivity = date("m/d g:ia", strtotime($activityRow['time_submitted'])) . ", " . (empty($activityRow['user_id']) ? $activityRow['email_address'] : getUserDisplayName($activityRow['user_id'], array("include_company" => false)));
									$sortLastActivity = date("Y-m-d H:i:s", strtotime($activityRow['time_submitted']));
								} else {
									if (empty($status)) {
										$status = "New";
									}
									$lastActivity = date("m/d g:ia", strtotime($row['time_submitted'])) . ", " . (empty($row['contact_id']) ? "" : getDisplayName($row['contact_id'], array("include_company" => false)));
									$sortLastActivity = date("Y-m-d H:i:s", strtotime($row['time_submitted']));
								}
								if (!$GLOBALS['gUserRow']['superuser_flag'] || $row['client_id'] == $GLOBALS['gClientId']) {
									$submittedBy = getDisplayName($row['contact_id'], array("include_company" => true));
								} else {
									$submittedBy = getDisplayName($row['contact_id']) . ", " . getReadFieldFromId("client_code", "clients", "client_id", $row['client_id']);
								}

								$displayFields = array();
								$displayFields['help_desk_entry_row'] = $row;
								$displayFields['help_desk_entry_id'] = array("display_value" => $row['help_desk_entry_id']);
								$displayFields['time_submitted'] = array("display_value" => date("m/d g:ia", strtotime($row['time_submitted'])), "sort_value" => date("Y-m-d H:i:s", strtotime($row['time_submitted'])));
								$displayFields['contact_id'] = array("display_value" => $submittedBy);
								$displayFields['user_id'] = array("display_value" => (empty($row['user_id']) ? "" : getUserDisplayName($row['user_id'], array("include_company" => false))));
								$displayFields['help_desk_type_id'] = array("display_value" => getReadFieldFromId("description", "help_desk_types", "help_desk_type_id", $row['help_desk_type_id'], ($GLOBALS['gUserRow']['superuser_flag'] ? "client_id is not null" : "")));
								$displayFields['help_desk_category_id'] = array("display_value" => getReadFieldFromId("description", "help_desk_categories", "help_desk_category_id", $row['help_desk_category_id'], ($GLOBALS['gUserRow']['superuser_flag'] ? "client_id is not null" : "")));
								$displayFields['help_desk_status_id'] = array("display_value" => $status, "raw_value" => $row['help_desk_status_id']);
								$displayFields['help_desk_tag_ids'] = array("display_value" => getFirstPart($row['help_desk_tag_ids'], 40));
								$displayFields['description'] = array("display_value" => getFirstPart($row['description'], 40));
								$displayFields['last_activity'] = array("display_value" => $lastActivity, "sort_value" => $sortLastActivity);
								$displayFields['user_group_id'] = array("display_value" => getReadFieldFromId('description', "user_groups",
									"user_group_id", $row['user_group_id'], ($GLOBALS['gUserRow']['superuser_flag'] ? "client_id is not null" : "")));
								$displayFields['priority'] = array("display_value" => $row['priority']);
								$displayFields['date_due'] = array("display_value" => (empty($row['date_due']) ? "" : date("m/d/Y", strtotime($row['date_due']))), "sort_value" => (empty($row['date_due']) ? "" : date("Y-m-d", strtotime($row['date_due']))));
								$displayFields['time_closed'] = array("display_value" => (empty($row['time_closed']) ? "" : date("m/d g:ia", strtotime($row['time_closed']))), "sort_value" => (empty($row['time_closed']) ? "" : date("Y-m-d H:i:s", strtotime($row['time_closed']))));
								$helpDeskEntries[] = $displayFields;
							}

							if (!empty($this->iPagePreferences['column_name'])) {
								usort($helpDeskEntries, array($this, "sortHelpDeskEntries"));
							}

							$selectedTickets = array();
							$resultSet = executeQuery("select primary_identifier from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $GLOBALS['gPageId']);
							while ($row = getNextRow($resultSet)) {
								$selectedTickets[] = $row['primary_identifier'];
							}

							foreach ($helpDeskEntries

							as $displayFields) {
							$selected = in_array($displayFields['help_desk_entry_row']['help_desk_entry_id'], $selectedTickets);
							?>
						<tr class="data-row<?= (empty($displayFields['time_closed']['display_value']) ? "" : " help-desk-entry-closed") ?> help-desk-category-<?= $displayFields['help_desk_entry_row']['help_desk_category_id'] ?> help-desk-status-<?= $displayFields['help_desk_entry_row']['help_desk_status_id'] ?>" data-help_desk_entry_id="<?= $displayFields['help_desk_entry_row']['help_desk_entry_id'] ?>" data-client_id="<?= $displayFields['help_desk_entry_row']['client_id'] ?>">
							<?php if ($helpDeskStaff) { ?>
								<td class="select-checkbox"><span class="far fa<?= ($selected ? "-check" : "") ?>-square"></span></td>
							<?php } ?>
							<td><?= $displayFields['help_desk_entry_id']['display_value'] ?></td>
							<?php
							foreach ($columnList as $columnName) {
								if (!array_key_exists($columnName, $this->iColumnChoices)) {
									continue;
								}
								$thisColumnInfo = $this->iColumnChoices[$columnName];
								if (!$helpDeskStaff && !empty($thisColumnInfo['staff_only'])) {
									continue;
								}
								?>
								<td <?= (empty($displayFields[$columnName]['sort_value']) ? "" : "data-sort_value='" . $displayFields[$columnName]['sort_value'] . "' ") ?>><?= $displayFields[$columnName]['display_value'] ?></td>
								<?php
							}
							?>
						</tr>
						<?php
						}
						?>
					</table>
					<script>$("#_ticket_count").html("<?= count($helpDeskEntries) ?>")</script>
					<?php
				} else {
					?>
					<h3>No Tickets Found</h3>
					<?php
				}
				$returnArray['ticket_list'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "get_custom_data":
				$resultSet = executeQuery("select * from help_desk_categories where help_desk_category_id in (select help_desk_category_id from help_desk_type_categories where help_desk_type_id = ?) and " .
					"client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $_GET['help_desk_type_id'], $GLOBALS['gClientId']);
				$returnArray['help_desk_categories'] = array();
				while ($row = getNextRow($resultSet)) {
					$returnArray['help_desk_categories'][] = array("key_value" => $row['help_desk_category_id'], "description" => $row['description']);
				}
				ob_start();
				$this->customData($_GET['help_desk_type_id']);
				$returnArray['custom_data'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;

			case "create_note":
				$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_POST['help_desk_entry_id'], $this->iPagePreferences['ticket_user_type'] == "staff");
				if (empty($helpDeskEntryId)) {
					$returnArray['error_message'] = "Invalid Help Desk Entry. Did you get logged out? Did you simulate another user?";
					ajaxResponse($returnArray);
					break;
				}
				$returnArray['user_list'] = $this->addHelpDeskEntryActivity($helpDeskEntryId);
				$this->iDatabase->startTransaction();
				$helpDesk = new HelpDesk($helpDeskEntryId);
				$parameters = array();
				if (!empty($_POST['note_close_ticket'])) {
					$parameters['close_ticket'] = true;
				}
				if ($_POST['public_private'] == "public") {
					if (!$helpDesk->addPublicNote($_POST['content'] . (empty($this->iPagePreferences['notes_signature']) ? "" : "\n\n" . $this->iPagePreferences['notes_signature']), $parameters)) {
						$returnArray['error_message'] = $helpDesk->getErrorMessage();
						$this->iDatabase->rollbackTransaction();
					} else {
						$this->iDatabase->commitTransaction();
					}
				} else {
					if (!$helpDesk->addPrivateNote($_POST['content'] . (empty($this->iPagePreferences['notes_signature']) ? "" : "\n\n" . $this->iPagePreferences['notes_signature']), $parameters)) {
						$returnArray['error_message'] = $helpDesk->getErrorMessage();
						$this->iDatabase->rollbackTransaction();
					} else {
						$this->iDatabase->commitTransaction();
					}
				}
				ajaxResponse($returnArray);
				break;

			case "update_ticket":
				if ($this->iPagePreferences['ticket_user_type'] != "staff") {
					$returnArray['error_message'] = "Ticket Cannot be updated";
					ajaxResponse($returnArray);
					break;
				}
				$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_POST['help_desk_entry_id']);
				if (empty($helpDeskEntryId)) {
					$returnArray['error_message'] = "Invalid Help Desk Entry. Did you get logged out? Did you simulate another user?";
					ajaxResponse($returnArray);
					break;
				}
				$returnArray['user_list'] = $this->addHelpDeskEntryActivity($helpDeskEntryId);
				$this->iDatabase->startTransaction();
				$helpDesk = new HelpDesk($helpDeskEntryId);
				if (array_key_exists("new_contact_id", $_POST) && !empty($_POST['new_contact_id'])) {
					if (!$helpDesk->setContact($_POST['new_contact_id'])) {
						$returnArray['error_message'] = $helpDesk->getErrorMessage();
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
				if (!$helpDesk->setStatus($_POST['help_desk_status_id'])) {
					$returnArray['error_message'] = $helpDesk->getErrorMessage();
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				if (!$helpDesk->assignToUser($_POST['user_id'])) {
					$returnArray['error_message'] = $helpDesk->getErrorMessage();
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				if (!empty($_POST['new_help_desk_category_id'])) {
					if (!$helpDesk->setCategory($_POST['new_help_desk_category_id'])) {
						$returnArray['error_message'] = $helpDesk->getErrorMessage();
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
				$helpDeskStatusId = getFieldFromId("help_desk_status_id", "help_desk_entries", "help_desk_entry_id", $helpDeskEntryId);
				if ($_POST['help_desk_status_id'] != $helpDeskStatusId) {
					$_POST['help_desk_status_id'] = $helpDeskStatusId;
					if (!$helpDesk->setStatus($_POST['help_desk_status_id'])) {
						$returnArray['error_message'] = $helpDesk->getErrorMessage();
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$returnArray['help_desk_status_id'] = $helpDeskStatusId;
				}
				if (!$helpDesk->assignToUserGroup($_POST['user_group_id'])) {
					$returnArray['error_message'] = $helpDesk->getErrorMessage();
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				if (!$helpDesk->setPriority($_POST['priority'])) {
					$returnArray['error_message'] = $helpDesk->getErrorMessage();
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				if (!$helpDesk->setDateDue($_POST['date_due'])) {
					$returnArray['error_message'] = $helpDesk->getErrorMessage();
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}

				$userIds = array();
				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("help_desk_entry_users_user_id-")) != "help_desk_entry_users_user_id-") {
						continue;
					}
					$userId = getFieldFromId("user_id", "users", "user_id", $fieldData, "client_id is not null");
					if (empty($userId) || in_array($userId, $userIds)) {
						continue;
					}
					executeQuery("insert ignore into help_desk_entry_users (help_desk_entry_id,user_id) values (?,?)", $helpDeskEntryId, $userId);
					$userIds[] = $userId;
				}
				executeQuery("delete from help_desk_entry_users where help_desk_entry_id = ?" . (empty($userIds) ? "" : " and user_id not in (" . implode(",", $userIds) . ")"), $helpDeskEntryId);

				$tagIds = array();
				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("help_desk_tag_links_help_desk_tag_id-")) != "help_desk_tag_links_help_desk_tag_id-") {
						continue;
					}
					$tagId = getFieldFromId("help_desk_tag_id", "help_desk_tags", "help_desk_tag_id", $fieldData, "client_id is not null");
					if (empty($tagId) || in_array($tagId, $tagIds)) {
						continue;
					}
					executeQuery("insert ignore into help_desk_tag_links (help_desk_entry_id,help_desk_tag_id) values (?,?)", $helpDeskEntryId, $tagId);
					$tagIds[] = $tagId;
				}
				executeQuery("delete from help_desk_tag_links where help_desk_entry_id = ?" . (empty($tagIds) ? "" : " and help_desk_tag_id not in (" . implode(",", $tagIds) . ")"), $helpDeskEntryId);

				$customFields = CustomField::getCustomFields("help_desk");
				foreach ($customFields as $thisCustomField) {
					$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
					if (!$customField->saveData($_POST, $helpDeskEntryId)) {
						$returnArray['error_message'] = $customField->getErrorMessage();
					}
				}

				$this->iDatabase->commitTransaction();
				ajaxResponse($returnArray);
				break;

			case "create_help_desk_entry":
				if (!$GLOBALS['gLoggedIn']) {
					$returnArray = array("error_message" => getSystemMessage("logged_out"));
					ajaxResponse($returnArray);
					break;
				}
				$this->iDatabase->startTransaction();
				$_POST['contact_id'] = $GLOBALS['gUserRow']['contact_id'];
				$helpDeskEntry = new HelpDesk();
				$helpDeskEntry->addSubmittedData($_POST);
				if (!$helpDeskEntry->save()) {
					$returnArray['error_message'] = $helpDeskEntry->getErrorMessage();
					$this->iDatabase->rollbackTransaction();
				} else {
					$this->iDatabase->commitTransaction();
				}
				$helpDeskEntry->addFiles();
				addActivityLog("Created Help Desk Ticket");
				ajaxResponse($returnArray);
				break;
		}
	}

	function addHelpDeskEntryActivity($helpDeskEntryId) {
		$userList = "";
		if ($this->iHelpDeskAdmin) {
			executeQuery("delete from help_desk_entry_activities where help_desk_entry_id = ? and time_submitted < DATE_SUB(NOW(),INTERVAL 1 MINUTE)", $helpDeskEntryId);
			executeQuery("insert into help_desk_entry_activities (help_desk_entry_id,user_id,time_submitted) values (?,?,now())", $helpDeskEntryId, $GLOBALS['gUserId']);
			$resultSet = executeQuery("select distinct user_id from help_desk_entry_activities where help_desk_entry_id = ? and user_id <> ?", $helpDeskEntryId, $GLOBALS['gUserId']);
			while ($row = getNextRow($resultSet)) {
				$userList .= (empty($userList) ? "" : ", ") . getUserDisplayName($row['user_id']);
			}
		}
		return $userList;
	}

	function customData($helpDeskTypeId) {
		if (empty($helpDeskTypeId)) {
			return;
		}
		$customFields = CustomField::getCustomFields("help_desk");
		foreach ($customFields as $thisCustomField) {
			$helpDeskTypeCustomFieldId = getFieldFromId("help_desk_type_custom_field_id", "help_desk_type_custom_fields", "help_desk_type_id",
				$helpDeskTypeId, "custom_field_id = ?", $thisCustomField['custom_field_id']);
			if (empty($helpDeskTypeCustomFieldId)) {
				continue;
			}
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl();
		}
	}

	function onLoadJavascript() {
		$helpDeskEntryId = HelpDesk::canAccessHelpDeskTicket($_GET['id'], $this->iPagePreferences['ticket_user_type'] == "staff");
		?>
		<script>
            $(document).on("click", ".contact-picker-open-contact", function () {
                var fieldName = $(this).data("field_name");
                if (fieldName != "" && fieldName != undefined) {
                    var contactId = $("#" + fieldName).val();
                    if (contactId != "" && contactId != undefined) {
                        window.open("/contactmaintenance.php?url_page=show&clear_filter=true&primary_id=" + contactId);
                    }
                }
                return false;
            });
            $(document).on("change", "#help_desk_status_ids", function () {
                const helpDeskStatusIds = $("#help_desk_status_ids").val().split(",");
                $("#additional_stats_line").find(".help-desk-status").removeClass("selected");
                for (const i in helpDeskStatusIds) {
                    $("#help_desk_status_selector_" + helpDeskStatusIds[i]).addClass("selected");
                }
                $("#search_text").val("");
                getTicketList(true, { help_desk_status_ids: $("#help_desk_status_ids").val(), custom_select: "help_desk_status" });
            });
            $(document).on("click", "#select_all_statuses", function () {
                if (empty($("#help_desk_status_ids").val())) {
                    let helpDeskStatusIds = "";
                    $("#additional_stats_line").find(".help-desk-status").addClass("selected");
                } else {
                    $("#additional_stats_line").find(".help-desk-status").removeClass("selected");
                }
                filterByStatus();
            })
            $(document).on("change", "#assigned_user_group_id", function () {
                $("#assigned_user_id").find("option").unwrap("span");
                if (!empty($(this).val())) {
                    const userGroupId = $(this).val();
                    $("#assigned_user_id").find("option[value!='']").each(function () {
                        if (empty($(this).data("user_group_id_" + userGroupId))) {
                            $(this).wrap("<span></span>");
                        }
                    });
                }
            });
            $(window).on("click", "#mark_spam", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mark_spam&help_desk_entry_id=" + $("#update_ticket_help_desk_entry_id").val(), function (returnArray) {
                    $(".ticket-list").removeClass("hidden");
                    $(".ticket-details").addClass("hidden");
                    getTicketList(true);
                });
            });
            $(window).on('popstate', function (event) {
                if (getURLParameter("show_list") == "true") {
                    $(".ticket-list").removeClass("hidden");
                    $(".ticket-details").addClass("hidden");
                    getTicketList(true);
                }
            });

            $(document).on("click", "#ticket_chooser", function () {
                $("#_ticket_choices").toggleClass("hidden");
                if (!empty(choicesTimer)) {
                    clearTimeout(choicesTimer);
                    choicesTimer = null;
                }
                if (!$("#_ticket_choices").hasClass("hidden")) {
                    choicesTimer = setTimeout(function () {
                        $("#_ticket_choices").addClass("hidden", 400);
                        choicesTimer = null;
                    }, 3000);
                }
            });
            $(document).on("click", "#_ticket_choices li", function () {
                if (!empty(choicesTimer)) {
                    clearTimeout(choicesTimer);
                    choicesTimer = null;
                }
                $("#_ticket_choices").removeClass("hidden");
                $("#search_text").val("");
                getTicketList(true, $(this).data());
            });
            $(document).on("click", "#_selected_header", function () {
                if ($(".select-checkbox").first().find("span").hasClass("fa-check-square")) {
                    $(".select-checkbox").find("span.fa-check-square").each(function () {
                        $(this).find("span").removeClass("fa-check-square");
                        $(this).find("span").addClass("fa-square");
                    });
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=unselect_tickets", function (returnArray) {
                        getTicketList(true);
                    });
                } else {
                    $(".select-checkbox").find("span.fa-square").each(function () {
                        $(this).trigger("click");
                    });
                }
            });
            $(document).on("change", "#help_desk_checklist_id", function () {
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_checklist&help_desk_checklist_id=" + $(this).val(), function (returnArray) {
                        if ("content" in returnArray) {
                            const rowNumber = $(".help-desk-entry-list-item").length;
                            $("#_help_desk_entry_list_items").append("<div class='help-desk-entry-list-item' id='help_desk_entry_list_item_new_" + rowNumber + "' data-help_desk_entry_list_item_id=''>" +
                                "<div><span class='help-desk-entry-list-item-marked-completed far fa-square'></span><span class='help-desk-entry-list-item-marked-completed fad fa-check-square hidden'></span></div>" +
                                "<textarea class='validate[required] help-desk-entry-list-item-description'></textarea><div><span class='delete-list-item fad fa-trash-alt'></span>" +
                                "<input type='hidden' class='help-desk-entry-list-item-sequence-number' value='" + (rowNumber * 1000) + "'><input type='hidden' class='marked-completed' value='0'></div><div><span class='fad fa-bars'></span></div></div>");
                            $("#help_desk_entry_list_item_new_" + rowNumber).find(".help-desk-entry-list-item-description").val(returnArray['content']).trigger("change");
                        }
                    });
                }
            });
            $(document).on("click", ".delete-list-item", function () {
                const helpDeskEntryListItemId = $(this).closest(".help-desk-entry-list-item").data("help_desk_entry_list_item_id");
                const thisListItem = $(this).closest(".help-desk-entry-list-item");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_help_desk_entry_list_item", { help_desk_entry_id: $("#update_ticket_help_desk_entry_id").val(), help_desk_entry_list_item_id: helpDeskEntryListItemId }, function (returnArray) {
                    thisListItem.remove();
                });
            });
            $(document).on("click", ".help-desk-entry-list-item-marked-completed", function () {
                $(this).closest(".help-desk-entry-list-item").find(".help-desk-entry-list-item-marked-completed").toggleClass("hidden");
                const completed = Math.abs($(this).closest(".help-desk-entry-list-item").find(".marked-completed").val() - 1);
                const helpDeskEntryListItemId = $(this).closest(".help-desk-entry-list-item").data("help_desk_entry_list_item_id");
                if (!empty(helpDeskEntryListItemId)) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_help_desk_entry_list_item", { help_desk_entry_id: $("#update_ticket_help_desk_entry_id").val(), help_desk_entry_list_item_id: helpDeskEntryListItemId, marked_completed: completed, sequence_number: $(this).closest(".help-desk-entry-list-item").find(".help-desk-entry-list-item-sequence-number").val() });
                }
            });
            $(document).on("change", ".help-desk-entry-list-item-description", function () {
                const completed = $(this).closest(".help-desk-entry-list-item").find(".marked-completed").val();
                const helpDeskEntryListItemId = $(this).closest(".help-desk-entry-list-item").data("help_desk_entry_list_item_id");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_help_desk_entry_list_item", { help_desk_entry_id: $("#update_ticket_help_desk_entry_id").val(), help_desk_entry_list_item_id: helpDeskEntryListItemId, content: $(this).val(), marked_completed: completed, sequence_number: $(this).closest(".help-desk-entry-list-item").find(".help-desk-entry-list-item-sequence-number").val() }, function (returnArray) {
                        if (empty(helpDeskEntryListItemId)) {
                            getListItems();
                        }
                    });
                }
            });
            $(document).on("click", "#add_list_item", function () {
                const rowNumber = $(".help-desk-entry-list-item").length;
                $("#_help_desk_entry_list_items").append("<div class='help-desk-entry-list-item' id='help_desk_entry_list_item_new_" + rowNumber + "' data-help_desk_entry_list_item_id=''>" +
                    "<div><span class='help-desk-entry-list-item-marked-completed far fa-square'></span><span class='help-desk-entry-list-item-marked-completed fad fa-check-square hidden'></span></div>" +
                    "<textarea class='validate[required] help-desk-entry-list-item-description'></textarea><div><span class='delete-list-item fad fa-trash-alt'></span>" +
                    "<input type='hidden' class='help-desk-entry-list-item-sequence-number' value='" + (rowNumber * 1000) + "'><input type='hidden' class='marked-completed' value='0'></div><div><span class='fad fa-bars'></span></div></div>");
                $("#help_desk_entry_list_item_new_" + rowNumber).find(".help-desk-entry-list-item-description").focus();
            });
            $(document).on("keyup", "#text_filter", function (event) {
                const textFilter = $(this).val().toLowerCase();
                if (empty(textFilter)) {
                    $("#ticket_table .data-row").removeClass("hidden");
                } else {
                    $("#ticket_table .data-row").each(function () {
                        const description = $(this).text().toLowerCase();
                        if (description.indexOf(textFilter) >= 0) {
                            $(this).removeClass("hidden");
                        } else {
                            $(this).addClass("hidden");
                        }
                    });
                }
                $("#_ticket_count").html($("#ticket_table .data-row").not(".hidden").length);
            });
            $(document).on("click", ".ticket-filter-button", function () {
                const ticketFilter = $(this).data("ticket_type");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_ticket_type&ticket_type=" + ticketFilter, function (returnArray) {
                    $("#ticket_type").val(ticketFilter);
                    getTicketList(true);
                });
            });
            $(document).on("click", "#edit_subject_button", function () {
                $("#edit_subject").val($("#existing_description").val());
                $('#_edit_subject_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Edit Ticket Subject',
                    buttons: {
                        Save: function (event) {
                            if (!empty($("#edit_subject").val())) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=edit_subject", { help_desk_entry_id: $("#help_desk_entry_id").val(), description: $("#edit_subject").val() }, function (returnArray) {
                                    if (!("error_message" in returnArray)) {
                                        $("#_edit_subject_dialog").dialog('close');
                                        $("#help_desk_entry_subject").html($("#edit_subject").val());
                                        $("#existing_description").html($("#edit_subject").val());
                                    }
                                });
                            } else {
                                $("#_edit_subject_dialog").dialog('close');
                            }
                        },
                        Cancel: function (event) {
                            $("#_edit_subject_dialog").dialog('close');
                        }
                    }
                });
            });
            $(document).on("click", "#delete_ticket_button", function () {
                $('#_confirm_delete_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 750,
                    title: 'Confirm Delete',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_ticket", { help_desk_entry_id: $("#update_ticket_help_desk_entry_id").val() }, function (returnArray) {
                                if (!("error_message" in returnArray)) {
                                    $('#_confirm_delete_dialog').dialog('close');
                                    $("#content").val("");
                                    $("#ticket_list_button").trigger("click");
                                }
                            });
                        },
                        Cancel: function (event) {
                            $("#_confirm_delete_dialog").dialog('close');
                        }
                    }
                });
            });
            $(document).on("change", "#_original_ticket #help_desk_category_id", function () {
                $("#new_help_desk_category_id").val($(this).val());
                updateTicket();
            });
            $(document).on("change", "#merge_into_help_desk_entry_id", function () {
                if (empty($(this).val())) {
                    return false;
                }
                $('#_confirm_merge_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 750,
                    title: 'Confirm Merge',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=merge_tickets", { help_desk_entry_id: $("#update_ticket_help_desk_entry_id").val(), merge_into_help_desk_entry_id: $("#merge_into_help_desk_entry_id").val() }, function (returnArray) {
                                $("#_confirm_merge_dialog").dialog('close');
                                $("#content").val("");
                                $("#ticket_list_button").trigger("click");
                            });
                        },
                        Cancel: function (event) {
                            $("#_confirm_merge_dialog").dialog('close');
                        }
                    }
                });
            });
            $(document).on("click", "#_changes_button", function () {
                showChanges("<?= $GLOBALS['gLinkUrl'] ?>", $("#help_desk_entry_id").val(), "help_desk_entries");
                return false;
            });
            $(document).on("click", "#_view_review_button", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_review&help_desk_entry_id=" + $("#help_desk_entry_id").val(), function (returnArray) {
                    if ("content" in returnArray) {
                        $("#_customer_review").html(returnArray['content']);
                    }
                });
                return false;
            });
            $(document).on("click", "#add_knowledge_base", function () {
                window.open("/knowledge-base?url_page=new&help_desk_entry_id=" + $("#help_desk_entry_id").val());
            });
            $(document).on("change", "#help_desk_answer_id", function () {
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_answer&help_desk_answer_id=" + $(this).val(), function (returnArray) {
                        if ("content" in returnArray) {
                            CKEDITOR.instances['note_content'].setData(returnArray['content']);
                        }
                        $("#help_desk_answer_id").val("");
                    });
                }
            });
            $(document).on("click", "#_mass_edit_button", function () {
                const $selectCheckbox = $(".select-checkbox .fa-check-square");
                if ($selectCheckbox.length === 0) {
                    $("#_mass_edit_button").addClass("hidden");
                    return;
                }
                let helpDeskEntryIds = "";
                let count = 0;
                $selectCheckbox.each(function () {
                    helpDeskEntryIds += (empty(helpDeskEntryIds) ? "" : ",") + $(this).closest("tr").data("help_desk_entry_id");
                    count++;
                });

                const $massEditForm = $("#_mass_edit_form");
                const $massEditDialog = $('#_mass_edit_dialog');
                $massEditForm.clearForm();
                $("#mass_edit_help_desk_entry_ids").val(helpDeskEntryIds);
                $("#mass_edit_count").html(count + " ticket" + (count == 1 ? "" : "s") + " being edited");
                $massEditDialog.dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 750,
                    title: 'Mass Edit Tickets',
                    buttons: {
                        Save: function (event) {
                            if ($massEditForm.validationEngine('validate')) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=mass_edit", $massEditForm.serialize(), function (returnArray) {
                                    if (!("error_message" in returnArray)) {
                                        $massEditDialog.dialog('close');
                                        getTicketList();
                                    }
                                });
                            }
                        },
                        Cancel: function (event) {
                            $massEditDialog.dialog('close');
                        }
                    }
                });
            });
            $(document).on("click", ".select-checkbox", function (event) {
                const alreadyChecked = $(this).find(".fa-check-square").length > 0;
                if (alreadyChecked) {
                    $(this).find("span").removeClass("fa-check-square");
                    $(this).find("span").addClass("fa-square");
                } else {
                    $(this).find("span").removeClass("fa-square");
                    $(this).find("span").addClass("fa-check-square");
                }
                if ($(".select-checkbox .fa-check-square").length > 0) {
                    $("#_mass_edit_button").removeClass("hidden");
                } else {
                    $("#_mass_edit_button").addClass("hidden");
                }
                const helpDeskEntryId = $(this).closest("tr").data("help_desk_entry_id");
                $("body").addClass("no-waiting-for-ajax");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_ticket&help_desk_entry_id=" + helpDeskEntryId + "&checked=" + (alreadyChecked ? 0 : 1));
                event.stopPropagation();
            });
            $(document).on("click", "#public_note", function () {
                $("#public_private").val("public");
                $(".only-private-note").addClass("hidden");
                $(".only-public-note").removeClass("hidden");
                $(this).addClass("selected");
                $("#private_note").removeClass("selected");
                $("#note_content").removeClass("private-note-content");
                $("#cke_note_content").removeClass("private-note-content");
            });
            $(document).on("keydown", "#note_content", function (event) {
                if ((event.metaKey || event.ctrlKey) && event.which === 13) {
                    event.preventDefault();
                    setTimeout(function () {
                        $("#_list_button").trigger("click");
                    }, 50);
                }
                return true;
            });

            $(document).on("click", "#private_note", function () {
                $("#public_private").val("private");
                $(".only-private-note").removeClass("hidden");
                $(".only-public-note").addClass("hidden");
                $(this).addClass("selected");
                $("#public_note").removeClass("selected");
                $("#note_content").addClass("private-note-content");
                $("#cke_note_content").addClass("private-note-content");
            });
            $(document).on("click", ".ticket-stat", function () {
                if ($(this).hasClass("help-desk-status")) {
                    return false;
                }
                const customSelect = $(this).data("custom_select");
                if (empty(customSelect)) {
                    return false;
                }
                $("#search_text").val("");
                $("#custom_select").val(customSelect);
                getTicketList(true, $(this).data());
            });
            $(document).on("click", ".help-desk-status", function (event) {
                $(this).toggleClass("selected");
                filterByStatus();
                event.stopPropagation();
                return false;
            });
            $(document).on("change", "#preferences_user_group_id", function () {
                $("#preferences_user_id").find("option[value!='']").remove();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_user_group_members&user_group_id=" + $(this).val(), function (returnArray) {
                    if ("users" in returnArray) {
                        for (const i in returnArray['users']) {
                            const thisOption = $("<option></option>").attr("value", returnArray['users'][i]['key_value']).text(returnArray['users'][i]['description']);
                            $("#preferences_user_id").append(thisOption);
                        }
                    }
                });
            });
            $(document).on("change", "#ticket_user_type", function () {
                $(".ticket-user-type-option").addClass("hidden");
                $(".ticket-user-type-" + $(this).val()).removeClass("hidden");
                $("#ticket_type").trigger("change");
            });
            $(document).on("change", "#ticket_type", function () {
                $(".ticket-type-option").addClass("hidden");
                $(".ticket-type-" + $(this).val()).removeClass("hidden");
            });
            $(document).on("click", "#_preferences_button", function () {
                $("#custom_select").val("");
                $(".ticket-user-type-option").addClass("hidden");
                $(".ticket-user-type-" + $("#ticket_user_type").val()).removeClass("hidden");
                $(".ticket-type-option").addClass("hidden");
                $(".ticket-type-" + $("#ticket_type").val()).removeClass("hidden");
                const $preferencesForm = $("#_preferences_form");
                const $preferencesDialog = $("#_preferences_dialog");
                addCKEditor();
                $preferencesDialog.dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 1000,
                    title: 'Preferences',
                    buttons: {
                        Save: function (event) {
                            if ($preferencesForm.validationEngine('validate')) {
                                for (instance in CKEDITOR.instances) {
                                    CKEDITOR.instances[instance].updateElement();
                                }
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_preferences", $preferencesForm.serialize(), function (returnArray) {
                                    refreshTime = $("#refresh_time").val();
                                    if (empty(refreshTime)) {
                                        refreshTime = 0;
                                    }
                                    if (!empty(refreshTimer)) {
                                        clearInterval(refreshTimer);
                                        refreshTimer = null;
                                    }
                                    if (refreshTime > 0) {
                                        refreshTimer = setInterval(function () {
                                            if (!$(".ticket-list").hasClass("hidden") && (!$("#_new_ticket_dialog").hasClass("ui-dialog-content") || !$("#_new_ticket_dialog").dialog('isOpen')) &&
                                                (!$("#_preferences_dialog").hasClass("ui-dialog-content") || !$("#_preferences_dialog").dialog('isOpen')) &&
                                                (!$("#filters_dialog").hasClass("ui-dialog-content") || !$("#filters_dialog").dialog('isOpen'))) {
                                                getTicketList(false);
                                            }
                                        }, (refreshTime * 1000));
                                    }
                                    setTimeout(function () {
                                        getTicketList(true);
                                    }, 100);
                                });
                                $preferencesDialog.dialog('close');
                            }
                        },
                        Cancel: function (event) {
                            $preferencesDialog.dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", "#_filters_button", function () {
                $("#ticket_type").trigger("change");
                const $filtersForm = $("#_filters_form");
                const $filtersDialog = $("#_filters_dialog");

                let ticketType = $("#_ticket_choices").find("li.selected").data("ticket_type");
                if (empty(ticketType)) {
                    ticketType = "assigned";
                }
                $("#ticket_type").val(ticketType);
                $("#assigned_user_group_id").trigger("change");

                $filtersDialog.dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 1000,
                    title: 'Filters',
                    buttons: {
                        Save: function (event) {
                            if ($filtersForm.validationEngine('validate')) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_preferences", $filtersForm.serialize(), function (returnArray) {
                                    refreshTime = $("#refresh_time").val();
                                    if (empty(refreshTime)) {
                                        refreshTime = 0;
                                    }
                                    if (!empty(refreshTimer)) {
                                        clearInterval(refreshTimer);
                                        refreshTimer = null;
                                    }
                                    if (refreshTime > 0) {
                                        refreshTimer = setInterval(function () {
                                            if (!$(".ticket-list").hasClass("hidden") && (!$("#_new_ticket_dialog").hasClass("ui-dialog-content") || !$("#_new_ticket_dialog").dialog('isOpen')) &&
                                                (!$("#_preferences_dialog").hasClass("ui-dialog-content") || !$("#_preferences_dialog").dialog('isOpen')) &&
                                                (!$("#filters_dialog").hasClass("ui-dialog-content") || !$("#filters_dialog").dialog('isOpen'))) {
                                                getTicketList(false);
                                            }
                                        }, (refreshTime * 1000));
                                    }
                                    setTimeout(function () {
                                        getTicketList(true);
                                    }, 100);
                                });
                                $filtersDialog.dialog('close');
                            }
                        },
                        Cancel: function (event) {
                            $filtersDialog.dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", "#assign_to_me", function () {
                $("#user_id_selector").val("<?= $GLOBALS['gUserId'] ?>").trigger("change");
                return false;
            });
            $(document).on("click", "#assign_to_previous", function () {
                $("#user_id_selector").val($(this).data("previous_user_id")).trigger("change");
                return false;
            });
            $(document).on("change", ".update-ticket", function () {
                if (!$(this).hasClass("no-update")) {
                    updateTicket();
                }
            });
            var ctrlPressed = false;
            $(document).on("keyup", function (event) {
                let dialogOpen = false;
                $(".dialog-box").each(function () {
                    if ($(this).hasClass("ui-dialog-content")) {
                        if ($(this).is(":visible")) {
                            dialogOpen = true;
                        }
                    }
                });
                if ($(".pp_pic_holder").is(":visible")) {
                    dialogOpen = true;
                }
                if (!dialogOpen) {
                    if (event.which === 27) {
                        $("#ticket_list_button").trigger("click");
                    } else if (event.which === 17) {
                        if (ctrlPressed) {
                            openTicketChooser();
                        } else {
                            ctrlPressed = true;
                            setTimeout(function () {
                                ctrlPressed = false;
                            }, 300);
                        }
                    } else {
                        ctrlPressed = false;
                    }
                }
            });

            function openTicketChooser() {
                const ticketChooserDialog = $("#_ticket_chooser_dialog");

                if (ticketChooserDialog.is('dialog') && ticketChooserDialog.dialog('isOpen')) {
                    return;
                }
                $("#_ticket_chooser_filter").val("");
                ticketChooserDialog.dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: true,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 800,
                    title: 'Go to ticket',
                    buttons: {
                        Close: function (event) {
                            ticketChooserDialog.dialog('close');
                        },
                    }
                });
            }

            $("#_ticket_chooser_filter").keyup(function (event) {
                if (event.which == 13 || event.which == 3) {
                    if (!isNaN($(this).val())) {
                        getTicketDetails($(this).val());
                        $("#_ticket_chooser_dialog").dialog('close');
                    } else {
                        $(this).val("");
                    }
                    return;
                }
            });
            $(document).on("click", "#ticket_list_button", function () {
                try {
                    for (var i in CKEDITOR.instances) {
                        CKEDITOR.instances[i].updateElement();
                    }
                } catch (e) {
                }
                if ($("#_ticket_list").hasClass("hidden") && !empty($("#note_content").val())) {
                    displayErrorMessage("Unsaved Notes");
                    return;
                }
                if (typeof canLeaveTicket == "function") {
                    if (!canLeaveTicket()) {
                        return
                    }
                }
                $(".ticket-list").removeClass("hidden");
                $(".ticket-details").addClass("hidden");
                if (getURLParameter("show_details") == "true") {
                    history.back();
                }
                getTicketList(true);
            });
            $(document).on("click", "#ticket_table tr.data-row", function (event) {
                if (event.metaKey || event.ctrlKey) {
                    window.open("<?= $GLOBALS['gLinkUrl'] ?>?id=" + $(this).data("help_desk_entry_id"));
                } else {
                    getTicketDetails($(this).data("help_desk_entry_id"));
                }
                return false;
            });
            $(document).on("click", "#reopen_ticket_button", function () {
                const helpDeskEntryId = $("#help_desk_entry_id").val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=reopen_ticket&help_desk_entry_id=" + helpDeskEntryId, function (returnArray) {
                    if ("error_message" in returnArray) {
                        $('html,body').animate({ scrollTop: 0 }, 400, 'swing');
                    } else {
                        getTicketDetails(helpDeskEntryId);
                    }
                });
                return false;
            });
            $(document).on("click", "#close_ticket_button", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=close_ticket&help_desk_entry_id=" + $("#help_desk_entry_id").val(), function (returnArray) {
                    if ("error_message" in returnArray) {
                        $('html,body').animate({ scrollTop: 0 }, 400, 'swing');
                    } else {
                        $(".ticket-list").removeClass("hidden");
                        $(".ticket-details").addClass("hidden");
                        if (getURLParameter("show_details") == "true") {
                            history.back();
                        }
                        getTicketList(true);
                    }
                });
                return false;
            });
            $(document).on("click", "#create_note_close", function () {
                const $helpDeskNoteForm = $("#_help_desk_note_form");
                const $postIframe = $("#_post_iframe");
                for (var i in CKEDITOR.instances) {
                    CKEDITOR.instances[i].updateElement();
                }
                if ($helpDeskNoteForm.validationEngine('validate')) {
                    $("body").addClass("waiting-for-ajax");
                    $("#note_close_ticket").val("1");
                    $helpDeskNoteForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_note").attr("method", "POST").attr("target", "post_iframe").submit();
                    $postIframe.off("load");
                    $postIframe.on("load", function () {
                        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                        const returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            return;
                        }
                        if ("user_list" in returnArray) {
                            updateOthersList(returnArray['user_list']);
                        }
                        if (!("error_message" in returnArray)) {
                            $(".ticket-details").addClass("hidden");
                            if (getURLParameter("show_details") == "true") {
                                history.back();
                            }
                            $("#note_content").val("");
                            for (var i in CKEDITOR.instances) {
                                CKEDITOR.instances[i].setData("");
                            }
                            $("#_ticket_details").html("");
                            $(".ticket-list").removeClass("hidden");
                            getTicketList(true);
                        }
                    });
                }
                return false;
            });
            $(document).on("click", "#create_note", function () {
                const $helpDeskNoteForm = $("#_help_desk_note_form");
                const $postIframe = $("#_post_iframe");
                for (var i in CKEDITOR.instances) {
                    CKEDITOR.instances[i].updateElement();
                }
                if ($helpDeskNoteForm.validationEngine('validate')) {
                    $("body").addClass("waiting-for-ajax");
                    $("#note_close_ticket").val("");
                    $helpDeskNoteForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_note").attr("method", "POST").attr("target", "post_iframe").submit();
                    $postIframe.off("load");
                    $postIframe.on("load", function () {
                        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                        const returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            return;
                        }
                        if ("user_list" in returnArray) {
                            updateOthersList(returnArray['user_list']);
                        }
                        if (!("error_message" in returnArray)) {
                            getTicketDetails($("#help_desk_entry_id").val());
                        }
                    });
                }
                return false;
            });
            $(document).on("click", "#_add_ticket_button", function () {
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
                            const $newTicketForm = $("#_new_ticket_form");
                            const $postIframe = $("#_post_iframe");
                            if ($newTicketForm.validationEngine('validate')) {
                                for (const instance in CKEDITOR.instances) {
                                    CKEDITOR.instances[instance].updateElement();
                                }
                                $("body").addClass("waiting-for-ajax");
                                $newTicketForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_help_desk_entry").attr("method", "POST").attr("target", "post_iframe").submit();
                                $postIframe.off("load");
                                $postIframe.on("load", function () {
                                    $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                                    const returnText = $(this).contents().find("body").html();
                                    const returnArray = processReturn(returnText);
                                    if (returnArray === false) {
                                        return;
                                    }
                                    if (!("error_message" in returnArray)) {
                                        getTicketList(true);
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
            $("#help_desk_type_id").change(function () {
                $("#custom_data").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_custom_data&help_desk_type_id=" + $(this).val(), function (returnArray) {
                        if ("custom_data" in returnArray) {
                            $("#custom_data").html(returnArray['custom_data']);
                            $("#custom_data .required-label").append("<span class='required-tag fa fa-asterisk'></span>");
                            $("#custom_data .datepicker").datepicker({
                                showOn: "button",
                                buttonText: "<span class='fad fa-calendar-alt'></span>",
                                constrainInput: false,
                                dateFormat: "mm/dd/y",
                                yearRange: "c-100:c+10"
                            });
                        }
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
            $(document).on("keydown", "#search_text", function (event) {
                if (event.which === 13) {
                    $("#_search_icon").trigger("click");
                    return false;
                }
            });
            $(document).on("click", "#_search_icon", function () {
                getTicketList(true);
            });
            $(document).on("click", "#private_access", function () {
                if ($("div.dropzone").hasClass("dz-clickable")) {
                    Dropzone.forElement("div.dropzone").options.url = scriptFilename + "?url_action=upload_files&help_desk_entry_id=" + $("#update_ticket_help_desk_entry_id").val() + "&private_access=" + ($("#private_access").prop("checked") ? "1" : "0");
                }
            })
			<?php if (!empty($helpDeskEntryId)) { ?>
            getTicketDetails(<?= $helpDeskEntryId ?>);
			<?php } else { ?>
            getTicketList(true);
			<?php } ?>
            if (refreshTime > 0) {
                refreshTimer = setInterval(function () {
                    if (!$(".ticket-list").hasClass("hidden") && (!$("#_new_ticket_dialog").hasClass("ui-dialog-content") || !$('#_new_ticket_dialog').dialog('isOpen')) &&
                        (!$("#_preferences_dialog").hasClass("ui-dialog-content") || !$('#_preferences_dialog').dialog('isOpen')) &&
                        (!$("#_filters_dialog").hasClass("ui-dialog-content") || !$('#_filters_dialog').dialog('isOpen'))) {
                        getTicketList(false);
                    }
                }, (refreshTime * 1000));
            }
		</script>
		<?php
	}

	function javascript() {
		if (strlen($this->iPagePreferences['refresh_time']) == 0 || !is_numeric($this->iPagePreferences['refresh_time'])) {
			$this->iPagePreferences['refresh_time'] = 30;
		}
		?>
		<script>
            let refreshTime = <?= $this->iPagePreferences['refresh_time'] ?>;
            let refreshTimer = null;
            let notesCheckTimer = null;
            let choicesTimer = null;

            function filterByStatus() {
                let helpDeskStatusIds = "";
                $("#additional_stats_line").find(".help-desk-status.selected").each(function () {
                    helpDeskStatusIds += (empty(helpDeskStatusIds) ? "" : ",") + $(this).data("help_desk_status_id");
                });
                $("#help_desk_status_ids").val(helpDeskStatusIds).trigger("change");
            }
            function afterEditableListRemove(listIdentifier) {
                updateTicket();
            }
            function changesMade() {
                try {
                    for (var i in CKEDITOR.instances) {
                        CKEDITOR.instances[i].updateElement();
                    }
                } catch (e) {
                }
                if ($("#_ticket_list").hasClass("hidden") && !empty($("#note_content").val())) {
                    return true;
                }
                return false;
            }

            function changeListItemSortOrder() {
                let sequenceNumber = 100;
                let sequenceNumbers = {};
                sequenceNumbers['sequence_numbers'] = {};
                $("#_help_desk_entry_list_items").find(".help-desk-entry-list-item").each(function () {
                    $(this).find(".sequence-number").val(sequenceNumber);
                    const helpDeskEntryListItemId = $(this).data("help_desk_entry_list_item_id");
                    if (!empty(helpDeskEntryListItemId)) {
                        sequenceNumbers['sequence_numbers'][helpDeskEntryListItemId] = sequenceNumber;
                    }
                    sequenceNumber += 100;
                });
                sequenceNumbers['help_desk_entry_id'] = $("#update_ticket_help_desk_entry_id").val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_list_item_order", sequenceNumbers);
            }

            function getListItems() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_list_items", { help_desk_entry_id: $("#update_ticket_help_desk_entry_id").val() }, function (returnArray) {
                    if ("help_desk_entry_list_items" in returnArray) {
                        $("#_help_desk_entry_list_items").html(returnArray['help_desk_entry_list_items']);
                    }
                    $("#_help_desk_entry_list_items").sortable({
                        update: function () {
                            changeListItemSortOrder();
                        }
                    });
                });
            }

            function updateOthersList(userList) {
                if (empty(userList)) {
                    $("#_others_list").html("").removeClass("visible");
                } else {
                    $("#_others_list").html("Ticket open by " + userList).addClass("visible");
                }
            }

            function afterSortList() {
                const columnName = $("#ticket_table").find(".header-sorted-column").data("column_name");
                const sortDirection = ($("#ticket_table").find(".header-sorted-column").hasClass("header-sorted-up") ? 1 : -1);
                $("body").addClass("no-waiting-for-ajax");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_sort_order", { column_name: columnName, sort_direction: sortDirection });
                return false;
            }

            function updateTicket() {
                if ($("#_update_ticket_form").validationEngine('validate')) {
                    $("#new_contact_id").val($("#contact_id").val());
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_ticket", $("#_update_ticket_form").serialize(), function (returnArray) {
                        if ("user_list" in returnArray) {
                            updateOthersList(returnArray['user_list']);
                        }
                        if (!("error_message" in returnArray)) {
                            displayInfoMessage("Ticket Updated");
                            if ("help_desk_status_id" in returnArray) {
                                $("#help_desk_status_id").val(returnArray['help_desk_status_id']);
                            }
                        }
                    });
                }
            }

            let gettingTicketDetails = false;

            function checkForUpdates() {
                if (gettingTicketDetails) {
                    return;
                }
                const noteCount = $("#notes_count").val();
                const ticketClosed = $("#close_ticket_button").length === 0;
                const helpDeskEntryId = $("#help_desk_entry_id").val();
                $("body").addClass("no-waiting-for-ajax");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_for_updates&help_desk_entry_id=" + helpDeskEntryId + "&notes_count=" + noteCount + "&ticket_closed=" + (ticketClosed ? "true" : ""), function (returnArray) {
                    if ("user_list" in returnArray) {
                        updateOthersList(returnArray['user_list']);
                    }
                    if ("ticket_closed" in returnArray) {
                        $("#_close_ticket_button_row").html(returnArray['ticket_closed']);
                    }
                    if ("additional_notes" in returnArray) {
                        $("#notes_wrapper").prepend(returnArray['additional_notes']);
                        $("#notes_count").val(returnArray['notes_count']);
                        $("#display_notes_count").html(returnArray['notes_count']);
                    }
                    $("#_ticket_details a.pretty-photo").prettyPhoto({
                        social_tools: false,
                        default_height: 480,
                        default_width: 854,
                        deeplinking: false
                    });
                });
            }

            function getTicketDetails(helpDeskEntryId) {
                gettingTicketDetails = true;
                history.pushState("", "Help Desk Tickets", "<?= $GLOBALS['gLinkUrl'] . (strpos($GLOBALS['gLinkUrl'], "?") === false ? "?" : "&") . "show_list=true" ?>");
                history.pushState("", "Help Desk Tickets", "<?= $GLOBALS['gLinkUrl'] . (strpos($GLOBALS['gLinkUrl'], "?") === false ? "?" : "&") . "show_details=true" ?>");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_ticket_details&help_desk_entry_id=" + helpDeskEntryId, function (returnArray) {
                    if ("reload_ticket" in returnArray) {
                        document.location = "<?= $GLOBALS['gLinkUrl'] ?>?id=" + helpDeskEntryId;
                        return;
                    }
                    if ("ticket_details" in returnArray) {
                        $("#_ticket_details").html(returnArray['ticket_details']);
                        $(".ticket-list").addClass("hidden");
                        $(".ticket-details").removeClass("hidden");
                        $("#_ticket_details a.pretty-photo").prettyPhoto({
                            social_tools: false,
                            default_height: 480,
                            default_width: 854,
                            deeplinking: false
                        });
                        let hostName = location.host;
                        const parts = location.host.split(".");
                        if (parts.length > 1) {
                            hostName = parts[parts.length - 2] + "." + parts[parts.length - 1];
                        }
                        $("a[href^='http']").add("area[href^='http']").add("a[href^='//']").add("area[href^='http']").add("a[href*='download.php']").add("a.download-file-link").not("a[rel^='prettyPhoto']").not("a[href*='" + hostName + "']").not(".same-page").attr("target", "_blank");
                        if ($("#_update_ticket_form").length > 0) {
                            $("#_update_ticket_form").validationEngine();
                        }
                        $("#_main_content").find(".autocomplete-field").trigger("get_autocomplete_text");
                    }
                    if ("help_desk_entry_users" in returnArray) {
                        $("#_help_desk_entry_users_delete_ids").val(returnArray['help_desk_entry_users']['_help_desk_entry_users_delete_ids']['data_value']);
                        $("#_help_desk_entry_users_delete_ids").data("crc_value", getCrcValue(returnArray['help_desk_entry_users']['_help_desk_entry_users_delete_ids']['data_value']));
                        for (const j in returnArray['help_desk_entry_users']['help_desk_entry_users']) {
                            addEditableListRow('help_desk_entry_users', returnArray['help_desk_entry_users']['help_desk_entry_users'][j]);
                        }
                        $(".editable-list-remove").addClass("update-ticket");
                    }
                    if ("help_desk_tag_links" in returnArray) {
                        $("#_help_desk_tag_links_delete_ids").val(returnArray['help_desk_tag_links']['_help_desk_tag_links_delete_ids']['data_value']);
                        $("#_help_desk_tag_links_delete_ids").data("crc_value", getCrcValue(returnArray['help_desk_tag_links']['_help_desk_tag_links_delete_ids']['data_value']));
                        for (const j in returnArray['help_desk_tag_links']['help_desk_tag_links']) {
                            addEditableListRow('help_desk_tag_links', returnArray['help_desk_tag_links']['help_desk_tag_links'][j]);
                        }
                        $(".editable-list-remove").addClass("update-ticket");
                    }
                    $("#display_notes_count").html($("#notes_count").val());
                    if (!empty(notesCheckTimer)) {
                        clearInterval(notesCheckTimer);
                        notesCheckTimer = null;
                    }
                    if ("user_list" in returnArray) {
                        updateOthersList(returnArray['user_list']);
                    }
                    if ($("#_help_desk_entry_list_items").length > 0) {
                        getListItems();
                    }
                    getTicketFiles();
					<?php
					$checkForUpdates = $this->getPageTextChunk("check_for_updates");
					if (!empty($checkForUpdates)) {
					?>
                    notesCheckTimer = setInterval(function () {
                        checkForUpdates();
                    }, 10000);
					<?php } ?>
                    gettingTicketDetails = false;
                    $(".ticket-details .datepicker").datepicker({
                        showOn: "button",
                        buttonText: "<span class='fad fa-calendar-alt'></span>",
                        constrainInput: false,
                        dateFormat: "mm/dd/y",
                        yearRange: "c-100:c+10"
                    });
                    addCKEditor();
                    if ($("#_help_desk_entry_users_table").find(".editable-list-data-row").length == 0) {
                        $("#_help_desk_entry_users_table").addClass("no-update");
                        $("#_help_desk_entry_users_table").find(".editable-list-add").trigger("click");
                        $("#help_desk_entry_users_user_id-1_selector").blur();
                        $("#_help_desk_entry_users_table").removeClass("no-update");
                    }
                    if ($("#_help_desk_tag_links_table").find(".editable-list-data-row").length == 0) {
                        $("#_help_desk_tag_links_table").find(".editable-list-add").trigger("click");
                        $("#help_desk_tag_links_help_desk_tag_id-1").blur();
                    }
                }, function (returnArray) {
                    gettingTicketDetails = false;
                });
            }

            function getTicketFiles() {
                if ($("div.dropzone").hasClass("dz-clickable")) {
                    Dropzone.forElement("div.dropzone").removeAllFiles();
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_ticket_files", { help_desk_entry_id: $("#update_ticket_help_desk_entry_id").val() }, function (returnArray) {
                    for (const i in returnArray) {
                        $("#" + i).html(returnArray[i]);
                    }
                    $("#help_desk_entry_images a.pretty-photo").prettyPhoto({
                        social_tools: false,
                        default_height: 480,
                        default_width: 854,
                        deeplinking: false
                    });
                    if (!$("div.dropzone").hasClass("dz-clickable")) {
                        $("div.dropzone").dropzone({
                            timeout: 120000,
                            url: scriptFilename + "?url_action=upload_files&help_desk_entry_id=" + $("#update_ticket_help_desk_entry_id").val() + "&private_access=" + ($("#private_access").prop("checked") ? "1" : "0"),
                            queuecomplete: function () {
                                getTicketFiles();
                            }
                        });
                    }
                });
            }

            function getTicketList(scrollToTop, customSelectData) {
                if ($("#_help_desk_entry_users_delete_ids").length > 0) {
                    $("#_help_desk_entry_users_delete_ids").val("").data("crc_value", getCrcValue(""));
                }
                if ($("#_help_desk_tag_links_delete_ids").length > 0) {
                    $("#_help_desk_tag_links_delete_ids").val("").data("crc_value", getCrcValue(""));
                }
                if (!empty(notesCheckTimer)) {
                    clearInterval(notesCheckTimer);
                    notesCheckTimer = null;
                }
                if (empty(scrollToTop)) {
                    scrollToTop = false;
                }
                if (empty(customSelectData)) {
                    customSelectData = [];
                }
                let searchText = $("#search_text").val();
                if (empty(searchText)) {
                    searchText = "";
                }
                let filterText = $("#text_filter").val();
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_ticket_list&search_text=" + encodeURIComponent(searchText), customSelectData, function (returnArray) {
                    if ("ticket_list" in returnArray) {
                        $("#_ticket_list").html(returnArray['ticket_list']);
                    }
                    if ("help_desk_status_ids" in returnArray) {
                        $("#help_desk_status_ids").val(returnArray['help_desk_status_ids']);
                    }
                    if ("column_name" in returnArray) {
                        if ($("#ticket_table").find("th[data-column_name='" + returnArray['column_name'] + "']").length > 0) {
                            $("#ticket_table").find("th[data-column_name='" + returnArray['column_name'] + "']").trigger("click");
                            if ("sort_direction" in returnArray && returnArray['sort_direction'] === "1") {
                                $("#ticket_table").find("th[data-column_name='" + returnArray['column_name'] + "']").trigger("click");
                            }
                        }
                    }
                    if (scrollToTop) {
                        $('html,body').animate({ scrollTop: 0 }, 400, 'swing');
                    }
                    if ($(".select-checkbox .fa-check-square").length > 0) {
                        $("#_mass_edit_button").removeClass("hidden");
                    } else {
                        $("#_mass_edit_button").addClass("hidden");
                    }
                    if (empty(filterText)) {
                        $("#text_filter").focus();
                    } else {
                        $("#text_filter").val(filterText).trigger("keyup");
                    }
                });
            }
		</script>
		<?php
	}

	function mainContent() {
		echo $this->getPageData("content");
		?>
		<div class="ticket-list" id="_ticket_list">
		</div>
		<div class="ticket-details hidden" id="_ticket_details">
		</div>
		<?php
		echo $this->getPageData("after_form_content");
		return true;
	}

	function hiddenElements() {
		$labels = array();
		if (is_array($GLOBALS['gPageRow']['page_text_chunks'])) {
			foreach ($GLOBALS['gPageRow']['page_text_chunks'] as $pageTextChunkCode => $pageTextChunkContent) {
				$labels[strtolower($pageTextChunkCode)] = $pageTextChunkContent;
			}
		}
		?>
		<iframe id="_post_iframe" name="post_iframe"></iframe>

		<div id="_confirm_merge_dialog" class="dialog-box">
			This will result in this ticket being deleted and it's contents being added as a note to the selected
			ticket. Are you sure?
		</div> <!-- _confirm_merge_dialog -->

		<div id="_edit_subject_dialog" class="dialog-box">
			<div class="basic-form-line" id="_edit_subject_row">
				<label>Subject</label>
				<input type='text' tabindex="10" id="edit_subject" name="edit_subject">
				<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
			</div>
		</div> <!-- _confirm_merge_dialog -->

		<div id="_confirm_delete_dialog" class="dialog-box">
			This will result in this ticket being deleted and unrecoverable. Are you sure?
		</div> <!-- _confirm_delete_dialog -->

		<?php include "userpicker.inc" ?>
		<?php include "contactpicker.inc" ?>

		<div id="_image_picker_dialog" class="dialog-box">
			<input type="hidden" id="_image_picker_column_name"/>
			<input tabindex="10" type="text" id="image_picker_filter" size="40" placeholder="Filter"/>
			<button tabindex="10" id="image_picker_new_image">New Image</button>

			<p class="align-center">Click description to see full image, click anywhere in row to select image.</p>
			<div id="_image_picker_list"></div>
		</div>

		<div id="_image_picker_new_image_dialog" class="dialog-box">
			<form id="_new_image" enctype='multipart/form-data'>
				<table>
					<tr>
						<td class=""><label for="image_picker_new_image_description">Description</label></td>
						<td class=""><input tabindex="10" type="text" class="validate[required]" size="40"
						                    id="image_picker_new_image_description"
						                    name="image_picker_new_image_description"/></td>
					</tr>
					<tr>
						<td class=""><label for="image_picker_file_content_file">Image</label></td>
						<td class=""><input tabindex="10" type="file" id="image_picker_file_content_file"
						                    class="validate[required]" name="image_picker_file_content_file"/></td>
					</tr>
				</table>
			</form>
		</div>

		<div id="_mass_edit_dialog" class="dialog-box">
			<form id="_mass_edit_form">
				<input type="hidden" name="mass_edit_help_desk_entry_ids" id="mass_edit_help_desk_entry_ids">
				<div class="basic-form-line" id="_mass_edit_public_note_row">
					<p id="mass_edit_count"></p>
					<label>Add Public Note</label>
					<textarea tabindex="10" id="mass_edit_public_note" name="mass_edit_public_note"></textarea>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<?= createFormControl("help_desk_entries", "help_desk_status_id", array("column_name" => "mass_edit_help_desk_status_id", "form_label" => "Set Status")) ?>
				<?= createFormControl("help_desk_tag_links", "help_desk_tag_id", array("column_name" => "mass_edit_help_desk_tag_id", "form_label" => "Add Tag", "not_null" => false)) ?>
				<?= createFormControl("help_desk_tag_links", "help_desk_tag_id", array("column_name" => "mass_edit_remove_help_desk_tag_id", "form_label" => "Remove Tag", "not_null" => false)) ?>

				<div class="basic-form-line" id="_mass_edit_close_ticket_row">
					<input type="checkbox" tabindex="10" id="mass_edit_close_ticket" name="mass_edit_close_ticket" value="1"><label class="checkbox-label" for="mass_edit_close_ticket">Close Selected Tickets</label>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

			</form>
		</div>

		<div class="dialog-box" id="_new_ticket_dialog">
			<p>PLEASE only include ONE issue per ticket. If you have multiple issues, create multiple tickets. Tickets that include a list of issues will be closed with a note to create new tickets.</p>
			<p class='error-message'></p>
			<form id="_new_ticket_form" enctype='multipart/form-data'>
				<div class="basic-form-line" id="_help_desk_type_id_row">
					<label class="required-label"><?= (array_key_exists("help_desk_type_id_label", $labels) ? $labels['help_desk_type_id_label'] : "Request Type") ?></label>
					<select tabindex="10" class="validate[required]" id="help_desk_type_id" name="help_desk_type_id">
						<option value="">[Select]</option>
						<?php
						$helpDeskTypeId = "";
						$resultSet = executeQuery("select * from help_desk_types where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							if ($resultSet['row_count'] == 1) {
								$helpDeskTypeId = $row['help_desk_type_id'];
							}
							?>
							<option <?= ($row['help_desk_type_id'] == $helpDeskTypeId ? "selected" : "") ?>
								value="<?= $row['help_desk_type_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
					</select>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<div class="basic-form-line" id="_help_desk_category_id_row">
					<label><?= (array_key_exists("help_desk_category_id_label", $labels) ? $labels['help_desk_category_id_label'] : "What is this about?") ?></label>
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
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<div class="basic-form-line" id="_description_row">
					<label class="required-label"><?= (array_key_exists("description_label", $labels) ? $labels['description_label'] : "Subject") ?></label>
					<input tabindex="10" type="text" id="description" name="description" class="validate[required]">
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<?php
				$contentLabel = $labels['content_label'];
				if (empty($contentLabel)) {
					$contentLabel = "Message";
					echo createFormControl("help_desk_entries", "content", array("not_null" => true, "form_label" => $contentLabel, "classes" => "ck-editor", "data-include_mentions" => true));
				}
				?>

				<div class="basic-form-line" id="_file_id_row">
					<label><?= (array_key_exists("file_id_label", $labels) ? $labels['file_id_label'] : "Send a file or image if that helps") ?></label>
					<input tabindex="10" type="file" id="file_id_file" name="file_id_file">
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<div id="custom_data">
				</div>

			</form>
		</div> <!-- new_ticket_dialog -->

		<div id="_preferences_dialog" class="dialog-box">
			<form id="_preferences_form">
				<input type="hidden" name="custom_select" id="custom_select" value="">
				<?php if (empty($_GET['ticket_user_type'])) { ?>
					<div class="basic-form-line inline-block">
						<label for="ticket_user_type">How are you using the Help Desk Dashboard?</label>
						<select id="ticket_user_type" name="ticket_user_type" tabindex="10">
							<option value="user" <?= ($this->iPagePreferences['ticket_user_type'] == "user" ? " selected" : "") ?>>Help Desk User</option>
							<option value="staff" <?= ($this->iPagePreferences['ticket_user_type'] == "staff" ? " selected" : "") ?>>Help Desk Staff</option>
						</select>
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
					</div>
				<?php } else { ?>
					<input type="hidden" id="ticket_user_type" name="ticket_user_type"
					       value="<?= $this->iPagePreferences['ticket_user_type'] ?>">
				<?php } ?>

				<?php
				if (empty($GLOBALS['gDomainClientId']) && $GLOBALS['gUserRow']['superuser_flag']) {
					$resultSet = executeQuery("select client_id from clients");
					if ($resultSet['row_count'] > 1) {
						?>
						<div class='basic-form-line ticket-user-type-option ticket-user-type-staff inline-block'>
							<label for='client_id'>Client</label>
							<select id="client_id" name="client_id">
								<option value="" <?= (empty($this->iPagePreferences['client_id']) ? " selected" : "") ?>>[All]
								</option>
								<?php
								$resultSet = executeQuery("select *,(select business_name from contacts where contact_id = clients.contact_id) business_name from clients where client_id in (select client_id from help_desk_entries where" .
									(!empty($this->iPagePreferences['hide_closed']) ? " time_closed is null and" : "") .
									" (user_id = ? or user_group_id in (select user_group_id from user_group_members where user_id = ?)))", $GLOBALS['gUserId'], $GLOBALS['gUserId']);
								while ($row = getNextRow($resultSet)) {
									?>
									<option value="<?= $row['client_id'] ?>" <?= ($this->iPagePreferences['client_id'] == $row['client_id'] ? " selected" : "") ?>><?= htmlText((empty($row['business_name']) ? $row['client_code'] : $row['business_name'])) ?></option>
									<?php
								}
								?>
							</select>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
						</div>
						<?php
					}
				}

				?>
				<div class="basic-form-line">
					<input type="hidden" name='hide_stats' value='0'>
					<input type="checkbox" id="hide_stats" name="hide_stats" <?= ($this->iPagePreferences['hide_stats'] ? " checked" : "") ?> value="1"><label class="checkbox-label" for="hide_stats">Hide Statistics Section</label>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<div class="basic-form-line">
					<input type="hidden" name='notes_entry_below' value='0'>
					<input type="checkbox" id="notes_entry_below" name="notes_entry_below" <?= ($this->iPagePreferences['notes_entry_below'] ? " checked" : "") ?> value="1"><label class="checkbox-label" for="notes_entry_below">Put Notes Entry Below Existing Notes</label>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<div class="basic-form-line ticket-user-type-option ticket-user-type-staff inline-block">
					<label for="refresh_time">Refresh Time</label>
					<span class="help-label">List will refresh every x seconds (default is 30, 0 = never)</span>
					<input type="text" id="refresh_time" name="refresh_time"
					       class="validate[custom[integer],min[0]] align-right" size="8"
					       value="<?= $this->iPagePreferences['refresh_time'] ?>">
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<div class="basic-form-line ticket-user-type-staff inline-block">
					<label for="maximum_list_count">Maximum to display</label>
					<input type="text" id="maximum_list_count" name="maximum_list_count"
					       class="validate[custom[integer],min[0],max[1000]] align-right" size="8"
					       value="<?= $this->iPagePreferences['maximum_list_count'] ?>">
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<div class="basic-form-line ticket-user-type-staff">
					<label for="notes_signature">Notes Signature</label>
					<textarea class='ck-editor' id="notes_signature" name="notes_signature"><?= $this->iPagePreferences['notes_signature'] ?></textarea>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<?php
				$setColumns = array_filter(explode(",", $this->iPagePreferences['list_columns']));
				$values = array();
				foreach ($setColumns as $columnName) {
					if (array_key_exists($columnName, $this->iColumnChoices)) {
						$values[$columnName] = $columnName;
					}
				}
				$listColumnControl = new DataColumn("list_columns");
				$listColumnControl->setControlValue("data_type", "custom");
				$listColumnControl->setControlValue("control_class", "MultiSelect");
				$listColumnControl->setControlValue("choices", $this->iColumnChoices);
				$listColumnControl->setControlValue("selected_values", $values);
				$listColumnControl->setControlValue("user_sets_order", true);
				$customControl = new MultipleSelect($listColumnControl, $this);
				?>
				<div class="basic-form-line custom-control-no-help custom-control-form-line" id="_list_columns_row">
					<label for="list_columns">List Columns</label>
					<?= $customControl->getControl() ?>
				</div>

			</form>
		</div> <!-- preferences_dialog -->

		<div id="_filters_dialog" class="dialog-box">
			<form id="_filters_form">
				<div class="basic-form-line ticket-user-type-option ticket-user-type-staff inline-block">
					<label for="ticket_type">Which Tickets</label>
					<select id="ticket_type" name="ticket_type" tabindex="10">
						<option value="assigned" <?= ($this->iPagePreferences['ticket_type'] == "assigned" ? " selected" : "") ?>>Tickets Assigned To Me</option>
						<option value="assigned_plus" <?= ($this->iPagePreferences['ticket_type'] == "assigned_plus" ? " selected" : "") ?>>Tickets Assigned To Me and Unassigned</option>
						<option value="group" <?= ($this->iPagePreferences['ticket_type'] == "group" ? " selected" : "") ?>>Tickets Assigned To My Group(s)</option>
						<?php if ($this->iHelpDeskAdmin) { ?>
							<option value="unassigned" <?= ($this->iPagePreferences['ticket_type'] == "unassigned" ? " selected" : "") ?>>Unassigned</option>
						<?php } ?>
						<option value="commented" <?= ($this->iPagePreferences['ticket_type'] == "my_tickets" ? " selected" : "") ?>>My Tickets</option>
						<option value="commented" <?= ($this->iPagePreferences['ticket_type'] == "commented" ? " selected" : "") ?>>Tickets with my notes</option>
						<?php if ($this->iHelpDeskAdmin || isInUserGroupCode($GLOBALS['gUserId'], "HELP_DESK")) { ?>
							<option value="all" <?= ($this->iPagePreferences['ticket_type'] == "all" ? " selected" : "") ?>>ALL Tickets</option>
						<?php } ?>
					</select>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<div class="basic-form-line">
					<input type="hidden" name='hide_closed' value='0'>
					<input type="checkbox" id="hide_closed" name="hide_closed" <?= ($this->iPagePreferences['hide_closed'] ? " checked" : "") ?> value="1"><label class="checkbox-label" for="hide_closed">Hide Closed Tickets</label>
				</div>

				<?php
				$resultSet = executeQuery("select count(*) from help_desk_entry_reviews where help_desk_entry_id in (select help_desk_entry_id from help_desk_entries where client_id = ?)", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					if ($row['count(*)'] > 0) {
						?>
						<div class="basic-form-line">
							<input type="hidden" name='only_with_reviews' value='0'>
							<input type="checkbox" id="only_with_reviews" name="only_with_reviews" <?= ($this->iPagePreferences['only_with_reviews'] ? " checked" : "") ?> value="1"><label class="checkbox-label" for="only_with_reviews">Only show tickets with reviews</label>
							<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
						</div>
						<?php
					}
				}
				?>

				<div class="basic-form-line inline-block">
					<label>Tickets on or after</label>
					<input type="text" id="start_date_submitted" name="start_date_submitted" class="datepicker validate[custom[date]]" value="<?= (empty($this->iPagePreferences['start_date_submitted']) ? "" : date("m/d/Y", strtotime($this->iPagePreferences['start_date_submitted']))) ?>">
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<div class="basic-form-line inline-block">
					<label>Tickets on or before</label>
					<input type="text" id="end_date_submitted" name="end_date_submitted" class="datepicker validate[custom[date]]" value="<?= (empty($this->iPagePreferences['end_date_submitted']) ? "" : date("m/d/Y", strtotime($this->iPagePreferences['end_date_submitted']))) ?>">
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<div class="basic-form-line">
					<label>Only tickets assigned to user group</label>
					<select id="assigned_user_group_id" name="assigned_user_group_id">
						<option value=''>[All]</option>
						<?php
						$resultSet = executeQuery("select * from user_groups where client_id = ? and user_group_id in (select user_group_id from help_desk_entries where client_id = ?)", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
							<option<?= ($this->iPagePreferences['assigned_user_group_id'] == $row['user_group_id'] ? " selected" : "") ?> value='<?= $row['user_group_id'] ?>'><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
					</select>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<div class="basic-form-line">
					<label>Only tickets assigned to user</label>
					<select id="assigned_user_id" name="assigned_user_id">
						<option value=''>[All]</option>
						<?php
						$resultSet = executeQuery("select *,(select group_concat(user_group_id) from user_group_members where user_id = users.user_id) user_group_ids from users join contacts using (contact_id) where inactive = 0 and (users.client_id = ? or superuser_flag = 1) and user_id in (select user_id from help_desk_entries where client_id = ?) order by first_name, last_name", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							$userGroupIds = explode(",", $row['user_group_ids']);
							$userGroupData = "";
							foreach ($userGroupIds as $userGroupId) {
								if (empty($userGroupId)) {
									continue;
								}
								$userGroupData .= (empty($userGroupData) ? "" : " ") . "data-user_group_id_" . $userGroupId . "='true'";
							}
							?>
							<option<?= ($this->iPagePreferences['assigned_user_id'] == $row['user_id'] ? " selected" : "") ?> <?= $userGroupData ?> value='<?= $row['user_id'] ?>'><?= getDisplayName($row['contact_id']) ?></option>
							<?php
						}
						?>
					</select>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
				</div>

				<?php
				$resultSet = executeQuery("select * from help_desk_tag_groups where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
				if ($resultSet['row_count'] > 0) {
					?>
					<div class="basic-form-line">
						<label>Help Desk Tag Group</label>
						<select id="help_desk_tag_group_id" name="help_desk_tag_group_id">
							<option value=''>[All]</option>
							<?php
							while ($row = getNextRow($resultSet)) {
								?>
								<option<?= ($this->iPagePreferences['help_desk_tag_group_id'] == $row['help_desk_tag_group_id'] ? " selected" : "") ?> value='<?= $row['help_desk_tag_group_id'] ?>'><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
						</select>
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
					</div>
					<?php
				}

				$resultSet = executeQuery("select * from help_desk_tags where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
				if ($resultSet['row_count'] > 0) {
					?>
					<div class="basic-form-line">
						<label>Help Desk Tag</label>
						<select id="help_desk_tag_id" name="help_desk_tag_id">
							<option value=''>[All]</option>
							<?php
							while ($row = getNextRow($resultSet)) {
								?>
								<option<?= ($this->iPagePreferences['help_desk_tag_id'] == $row['help_desk_tag_id'] ? " selected" : "") ?> value='<?= $row['help_desk_tag_id'] ?>'><?= htmlText($row['description']) ?></option>
								<?php
							}
							?>
						</select>
						<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
					</div>
					<?php
				}

				$setColumns = array_filter(explode(",", $this->iPagePreferences['help_desk_status_ids']));
				$values = array();
				foreach ($setColumns as $columnName) {
					$values[$columnName] = $columnName;
				}
				$listColumnControl = new DataColumn("help_desk_status_ids");
				$listColumnControl->setControlValue("data_type", "custom");
				$listColumnControl->setControlValue("control_class", "MultiSelect");
				$listColumnControl->setControlValue("choices", $GLOBALS['gPrimaryDatabase']->getControlRecords("help_desk_statuses"));
				$listColumnControl->setControlValue("selected_values", $values);
				$customControl = new MultipleSelect($listColumnControl, $this);
				?>
				<div class="basic-form-line custom-control-form-line custom-control-no-help" id="_help_desk_status_ids_row">
					<label for="help_desk_status_ids">Include Only These Statuses</label>
					<span class='help-label'>If none are selected, all will be included</span>
					<?= $customControl->getControl() ?>
				</div>

			</form>
		</div> <!-- filters_dialog -->
		<div id="_ticket_chooser_dialog" class="dialog-box">
			<input type="text" id="_ticket_chooser_filter" placeholder="Ticket #">
		</div>
		<?php
	}

	function jqueryTemplates() {
		$customFields = CustomField::getCustomFields("help_desk");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getTemplate();
		}
		$usersColumn = new DataColumn("help_desk_entry_users");
		$usersColumn->setControlValue("primary_table", "help_desk_entries");
		$usersColumn->setControlValue("data_type", "custom");
		$usersColumn->setControlValue("control_class", "EditableList");
		$usersColumn->setControlValue("list_table", "help_desk_entry_users");
		$usersColumn->setControlValue("list_table_controls", array("user_id" => array("user_presets" => "userPresets", "not_null" => false, "classes" => "update-ticket")));

		$helpDeskEntryUsers = new EditableList($usersColumn, $this);
		echo $helpDeskEntryUsers->getTemplate();

		$tagsColumn = new DataColumn("help_desk_tag_links");
		$tagsColumn->setControlValue("primary_table", "help_desk_entries");
		$tagsColumn->setControlValue("data_type", "custom");
		$tagsColumn->setControlValue("control_class", "EditableList");
		$tagsColumn->setControlValue("list_table", "help_desk_tag_links");
		$tagsColumn->setControlValue("classes", "update-ticket");
		$tagsColumn->setControlValue("list_table_controls", array("help_desk_tag_id" => array("remove_add_new" => true, "not_null" => false, "classes" => "update-ticket")));

		$helpDeskTagLinks = new EditableList($tagsColumn, $this);
		echo $helpDeskTagLinks->getTemplate();
	}

	function internalCSS() {
		?>
		<style>
			#_update_ticket_wrapper .basic-form-line input[type=text] {
				width: 100%;
			}

            #select_all_statuses {
                position: absolute;
                top: 5px;
                right: 5px;
                cursor: pointer;
                font-size: 1.2rem;
                color: rgb(255, 255, 255);
            }

            tr.invert-colors td {
                transition: background-color 1s;
                background-color: rgb(192, 0, 0) !important;
            }

            input[type=text].datepicker {
                max-width: 120px;
            }

            #edit_subject {
                width: 100%;
            }

            #edit_subject_button {
                font-size: .8rem;
                margin-right: 10px;
                cursor: pointer;
            }

            #display_notes_count_wrapper a {
                font-size: 1.2rem;
            }

            .ticket-note img {
                max-width: 100%;
            }

            .help-desk-entry-list-item {
                width: 100%;
                padding: 0 20px;
                border: 1px solid rgb(220, 220, 220);
                border-top: none;
                font-size: 0;
                height: 55px;
                display: flex;

            input, span {
                display: inline-block;
                font-size: 1.2rem;
                line-height: 50px;
            }

            .fa-bars {
                color: rgb(100, 100, 100);
                font-size: 1.4rem;
                margin-left: 20px;
                cursor: pointer;
            }

            }

            .help-desk-entry-list-item:first-of-type {
                border-top: 1px solid rgb(220, 220, 220);
            }

            .help-desk-entry-list-item-marked-completed {
                font-size: 1.4rem;
            }

            .help-desk-entry-list-item-description {
                width: 90%;
                height: 47px;
                margin: 4px 20px;
                font-size: 1rem;
                display: inline-block;
                padding: 0 5px;
            }

            .delete-list-item {
                cursor: pointer;
            }

            #_add_list_item_wrapper, #_show_changes_wrapper {
                margin: 20px 0 0 0;
            }

            #_others_list {
                opacity: 0;
                transition: all .5s;
                position: fixed;
                top: 90px;
                right: 40px;
                width: 300px;
                height: 50px;
                border: 2px solid rgb(40, 160, 80);
                background-color: rgb(240, 240, 240);
                border-radius: 10px;
                font-size: 12px;
                padding: 10px;
                z-index: 10000;
            }

            #_others_list.visible {
                opacity: 1;
                transition: all .5s;
            }

            #_preferences_dialog .inline-block {
                margin-right: 80px;
            }

            #_filters_dialog .inline-block {
                margin-right: 80px;
            }

			<?php
		$resultSet = executeQuery("select * from help_desk_categories where display_color is not null");
		while ($row = getNextRow($resultSet)) {
	?>
            tr.help-desk-category-<?= $row['help_desk_category_id'] ?> td {
                color: <?= $row['display_color'] ?>;
                font-weight: 600;
            }

			<?php
		}
	?>
			<?php
		$resultSet = executeQuery("select * from help_desk_statuses where display_color is not null");
		while ($row = getNextRow($resultSet)) {
	?>
            tr.help-desk-status-<?= $row['help_desk_status_id'] ?> td {
                background-color: <?= $row['display_color'] ?>;
            }

			<?php
		}
	?>

            tr.help-desk-entry-closed td {
                background-color: rgb(180, 180, 180);
            }
            #ticket_filters {
                margin-bottom: 5px;

            button {
                font-size: .6rem;
                margin-bottom: 5px;
                padding: 5px 10px;
            }

            }

            #_original_message p {
                line-height: 1.2;
            }

            #_original_message br {
                line-height: .8;
            }

            #options_line {
                display: flex;
                flex-wrap: wrap;
                position: relative;
                padding-bottom: 5px;
            }

            #options_line_buttons, #_search_block {
                flex: 1 1 auto;
            }

            #options_line_buttons {
                margin-bottom: 5px;
            }

            #options_line_buttons span.fas {
                margin-right: 10px;
            }

            #options_line_buttons span.fad {
                margin-right: 10px;
            }

            #_search_block {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                margin-bottom: 5px;

            input {
                margin-right: 5px;
            }

            }

            #_search_icon {
                cursor: pointer;
                font-size: 20px;
                margin-left: 5px;
            }

            #options_line_buttons button, #_search_block input, #_search_icon {
                margin-bottom: 5px;
            }

            #ticket_table {
                width: 100%;
            }

            #description {
                width: 600px;
            }

            #ticket_table.grid-table {
                border: 1px solid rgb(220, 220, 220);
            }

            #ticket_table.grid-table th {
                border: 0;
            }

            #ticket_table.grid-table td {
                border: 0;
                border-right: 1px solid rgb(220, 220, 220);
            }

            #ticket_table.grid-table td:last-child {
                border-right: 0;
            }

            #ticket_table.grid-table tr:nth-child(2n+2) {
                background-color: rgb(240, 240, 240);
            }

            #ticket_table th, #ticket_table td {
                padding: 6px;
                cursor: pointer;
                font-size: .7rem;
            }

            #ticket_table tr:hover td {
                background-color: rgb(240, 240, 250);
            }

            button .fas, button .fad {
                cursor: pointer;
                font-size: 1.2rem;
            }

            #assign_to_previous, #assign_to_me {
                font-size: .7rem;
                text-transform: none;
                padding: 4px 15px;
            }

            p#error_message.error-message {
                font-size: 1.2rem;
                margin-bottom: 0;
                line-height: 50px;
            }

            #_main_content {
                margin-top: 0;
                padding: 20px 10px 200px 10px;
            }

            #_management_header {
                padding-bottom: 0;
                margin-bottom: 0;
                display: none;
            }

            #stats_line, #additional_stats_line {
                display: flex;
                flex-wrap: wrap;
                margin-bottom: 20px;
                justify-content: flex-start;
                background-color: rgb(0, 124, 124);
                padding: 5px 30px 5px 5px;
                position: relative;

            div {
                flex: 0 0 250px;
            }

            div#_average_resolution {
                flex: 0 0 385px;
            }

            }

            .ticket-stat {
                margin: 5px;
                background-color: rgb(34, 93, 96);
                height: 50px;
                width: 80px;
                position: relative;
                cursor: pointer;
                border-radius: 4px;
                text-shadow: 0 0 4px rgba(50, 50, 50, .8);
            }

            #stats_line div#_ticket_choices {
                position: absolute;
                top: 100%;
                left: 0;
                background-color: rgb(255, 255, 255);
                width: 100%;
                padding: 10px;
                z-index: 5000;
                border: 1px solid rgb(34, 93, 96);
                height: auto;
            }

            #_ticket_choices ul li {
                padding: 5px;
                list-style: none;
                text-shadow: none;
            }

            #_ticket_choices ul li:hover {
                background-color: rgb(255, 250, 200);
            }

            .ticket-stat-content {
                width: 100%;
                height: 100%;
                z-index: 1000;
                position: relative;
                padding: 5px;
                text-align: center;
            }

            .help-desk-status .ticket-stat-content .selected-icon {
                position: absolute;
                top: 5px;
                right: 10px;
                font-size: 1rem;
                display: none;
            }

            .help-desk-status.selected .ticket-stat-content .selected-icon {
                position: absolute;
                top: 5px;
                right: 10px;
                font-size: 1rem;
                display: block;
            }

            #_main_content .ticket-stat-content p.ticket-stat-number {
                font-size: 1.6rem;
                line-height: 1;
                color: rgb(255, 255, 255);
                margin: 0;
                width: 100%;
            }

            #_main_content #ticket_chooser .ticket-stat-content p.ticket-stat-number {
                font-size: 1rem;
                padding-bottom: 4px;
            }

            #_main_content .ticket-stat-content p#resolution_time {
                font-size: 1.2rem;
                margin-top: 5px;
                font-weight: 700;
            }

            #_main_content .ticket-stat-content p.ticket-stat-tag {
                font-size: .8rem;
                line-height: 1;
                color: rgb(255, 255, 255);
                padding: 0 20px;
                width: 100%;
                text-transform: uppercase;
            }

            #_ticket_details_header {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 70px;
                background-color: rgb(34, 93, 96);
                color: rgb(255, 255, 255);
                padding: 0 20px;
            }

            #_ticket_details_header button {
                line-height: 70px;
                padding: 0;
            }

            #ticket_list_button {
                border: none;
                background: none;
                border-radius: 0;
                padding: 10px;
                color: rgb(255, 255, 255);
                margin-right: 20px;
            }

            #ticket_list_button span {
                font-size: 1.2rem;
                line-height: 70px;
            }

            #_ticket_number {
                font-size: 1.6rem;
                line-height: 70px;
            }

            #ticket_list_button:hover {
                color: rgb(15, 50, 100);
            }

            #time_created {
                margin-left: 20px;
                font-size: .8rem;
            }

            #_original_ticket {
                padding: 10px;
                margin-bottom: 10px;
            }

            #_original_ticket label {
                font-size: .9rem;
                margin-right: 20px;
            }

            #_original_message {
                border: 1px solid rgb(15, 50, 100);
                padding: 10px;
                margin-top: 5px;
                min-height: 100px;
                line-height: 1.2;
                border-radius: 2px;
                overflow-x: scroll;
            }

            #_original_message p {
                font-size: .9rem;
                color: rgb(15, 50, 100);
                font-weight: 300;
            }

            #_original_message ul {
                margin: 10px 0 10px 40px;
                list-style: disc;
            }

            #_original_message ol {
                margin: 10px 0 10px 40px;
                list-style: decimal;
            }

            p.attached-wrapper {
                text-align: right;
            }

            p.attached-wrapper a {
                display: inline-block;
                margin-left: 20px;
            }

            #_outer_update_ticket_wrapper {
                margin-bottom: 20px;
            }

            #_outer_update_ticket_wrapper select {
                max-width: 220px;
                width: 100%;
                min-width: 0;
            }

            #assign_to_me, #assign_to_previous {
                font-size: .7rem;
            }

            #user_id_selector {
                width: 200px;
                min-width: 0;
            }

            button.user-picker {
                padding: 4px 10px;
            }

            #_outer_update_ticket_wrapper .editable-list select.user-picker-selector {
                width: 180px;
            }

            #_update_ticket_wrapper button {
                font-size: .8rem;
            }

            #_ticket_settings_wrapper {
                border-bottom: 2px solid rgb(220, 220, 220);
                margin-bottom: 20px;
                display: flex;
                flex-direction: row;
            }

            #_main_details_wrapper {
                flex: 1 1 auto;
                max-width: 100%;
            }

            #_update_ticket_form_wrapper {
                margin-left: 20px;
                padding-left: 20px;
                border-left: 2px solid rgb(200, 200, 200);
                flex: 0 0 320px;
            }

            #_note_section {
                padding-bottom: 20px;
            }

            #note_content {
                width: 100%;
                max-width: 100%;
                height: 200px;
                border-radius: 0;
                display: block;
                padding: 8px;
            }

            .private-note-content {

            iframe {
                background-color: rgb(255, 255, 235) !important;
            }

            .cke_top {
                background-color: rgb(255, 255, 235) !important;
            }

            }

            #note_type_wrapper {
                display: flex;
                justify-content: flex-start;
                position: relative;
                top: -1px;
            }

            #note_type_wrapper div {
                flex: 0 0 auto;
            }

            #public_note {
                z-index: 100;
                width: 100px;
                border: 1px solid rgb(200, 200, 200);
                padding: 8px 0;
                text-align: center;
                cursor: pointer;
            }

            #public_note.selected {
                border-top: 1px solid rgb(255, 255, 255);
            }

            #private_note {
                z-index: 100;
                width: 100px;
                border: 1px solid rgb(200, 200, 200);
                padding: 8px 0;
                text-align: center;
                border-left: none;
                background-color: rgb(255, 255, 230);
                cursor: pointer;
            }

            #private_note.selected {
                border-top: 1px solid rgb(255, 255, 230);
            }

            #_add_attachments {
                position: relative;
                margin: -30px 0 0;
            }

            #_add_attachments button {
                font-size: .8rem;
                padding: 5px 20px;
                position: relative;
            }

            #_add_attachments.no-private {
                margin-top: 0;
            }

            .only-public-note {
                color: rgb(192, 0, 0);
            }

            .public-note {
                margin: 10px 0;
                padding: 10px 10px 0 10px;
                background-color: rgb(240, 240, 240);
                border: 2px solid rgb(200, 200, 200);
                width: 100%;
                max-width: 100%;
            }

            .private-note {
                margin: 10px 0;
                padding: 10px 10px 0 10px;
                background-color: rgb(255, 255, 230);
                border: 2px solid rgb(200, 200, 200);
                width: 100%;
                max-width: 100%;
            }

            .public-note .attached-wrapper {
                padding-right: 20px;
            }

            .private-note .attached-wrapper {
                padding-right: 20px;
            }

            td.select-checkbox {
                text-align: center;
                width: 50px;
                padding: 0;
            }

            td.select-checkbox .fal {
                font-size: 1rem;
            }

            #_main_content .ticket-details {
                padding-top: 80px;
                position: relative;
            }

            #_main_content .ticket-details p {
                padding-bottom: 5px;
                font-size: .9rem;
            }

            #_main_content .ticket-details li {
                font-size: .8rem;
                margin-bottom: 5px;
            }

            #_main_content .ticket-details div > ul {
                margin-bottom: 15px;
            }

            #_main_content .ticket-details ul {
                list-style: disc;
                margin: 5px 0 5px 30px;
            }

            #_main_content .ticket-details ul ul {
                list-style: circle;
            }

            #_main_content .ticket-details ul ul ul {
                list-style: square;
            }

            #_main_content .ticket-details ul ul ul ul {
                list-style: circle;
            }

            #_main_content .ticket-details div > ol {
                margin-bottom: 15px;
            }

            #_main_content .ticket-details ol {
                list-style: decimal;
                margin: 5px 0 5px 30px;
            }

            #_main_content .ticket-details ol ol {
                list-style: upper-alpha;
            }

            #_main_content .ticket-details ol ol ol {
                list-style: lower-roman;
            }

            #_main_content .ticket-details ol ol ol ol {
                list-style: lower-alpha;
            }

            #_list_items_wrapper {
                margin-bottom: 40px;
            }

            #file_content_headers {
                display: flex;

            > h3 {
                flex: 0 0 33.3333%;
                margin: 0;
                padding-left: 10px
            }

            }

            #file_content_wrapper {
                display: flex;
                margin-bottom: 40px;

            > div {
                flex: 0 0 33.3333%;
                background-color: rgb(240, 240, 240);
                border: 2px solid rgb(200, 200, 200);
                border-right: none;
            }

            }

            .help-desk-entry-image {
                height: 50px;
                border-bottom: 1px solid rgb(240, 240, 240);
                overflow: hidden;

            img {
                max-height: 50px;
                max-width: 25%;
                float: left;
                margin-right: 20px;
            }

            span {
                line-height: 50px;
                font-size: .7rem;
            }

            }

            .help-desk-entry-file {
                height: 50px;
                padding: 0 20px;
                border-bottom: 1px solid rgb(240, 240, 240);
                overflow: hidden;

            span {
                line-height: 50px;
                font-size: .8rem;
            }

            }

            #file_content_wrapper div.dropzone {
                border: 2px dashed rgb(200, 200, 200);
                margin: 0;
            }

            @media only screen and (max-width: 1400px) {
                #_original_ticket {

                .basic-form-line.inline-block {
                    display: block;
                    float: none;
                }
            }

            }

            @media only screen and (max-width: 1150px) {
                #_ticket_settings_wrapper {
                    display: block;
                }

                #_update_ticket_form_wrapper {
                    margin-left: 0;
                    padding-left: 0;
                    border-left: none;
                    flex: 0 0 300px;
                }
            }

		</style>
		<?php
	}
}

$pageObject = new HelpDeskDashboardPage();
$pageObject->displayPage();
