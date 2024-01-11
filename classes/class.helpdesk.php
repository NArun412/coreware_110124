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

class HelpDesk {

	private $iHelpDeskEntryId = false;
	private $iHelpDeskEntryRow = array();
	private $iSubmittedData = array();
	private $iErrorMessage = "";
	private $iNotifications = "";
	private $iDelayedNotification = false;

	function __construct($helpDeskEntryId = "") {
		if (!empty($helpDeskEntryId)) {
			if (is_array($helpDeskEntryId)) {
				$this->iHelpDeskEntryRow = $helpDeskEntryId;
			} else {
				$resultSet = executeQuery("select * from help_desk_entries where help_desk_entry_id = ? and client_id = ?", $helpDeskEntryId, $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$this->iHelpDeskEntryRow = $row;
					$this->iHelpDeskEntryId = $row['help_desk_entry_id'];
				} else {
					$this->iErrorMessage = "Help Desk Entry not found";
				}
			}
		}
	}

	public static function canAccessHelpDeskTicket($helpDeskEntryId, $asHelpDeskStaff = true, $userId = "") {
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
		$contactId = Contact::getUserContactId($userId);
		$helpDeskEntryId = getFieldFromId("help_desk_entry_id", "help_desk_entries", "help_desk_entry_id", $helpDeskEntryId);
		if (empty($helpDeskEntryId)) {
			return false;
		}
		if (!$asHelpDeskStaff) {
			$companyId = getFieldFromId("company_id", "contacts", "contact_id", $contactId);
			if (!empty($companyId)) {
				$companyContactId = getFieldFromId("contact_id", "companies", "company_id", $companyId);
				if (empty(CustomField::getCustomFieldData($companyContactId, "SHARE_COMPANY_TICKETS"))) {
					$companyId = "";
				}
			}
			$canAccessId = getFieldFromId("help_desk_entry_id", "help_desk_entries", "help_desk_entry_id", $helpDeskEntryId,
				"contact_id = ?" . (empty($companyId) ? "" : " or contact_id in (select contact_id from contacts where company_id = " . $companyId . ")"), $contactId);
			return (empty($canAccessId) ? false : $helpDeskEntryId);
		}
		$canAccessId = getFieldFromId("help_desk_entry_id", "help_desk_entries", "help_desk_entry_id", $helpDeskEntryId, "(user_id = ? or user_group_id in (select user_group_id from user_group_members where user_id = ?))", $userId, $userId);
		if (!empty($canAccessId)) {
			return $helpDeskEntryId;
		}
		$fullClientAccess = getFieldFromId("full_client_access", "users", "user_id", $userId);
		$superuserFlag = getFieldFromId("superuser_flag", "users", "user_id", $userId);
		if (isInUserGroupCode($userId, "HELP_DESK_ADMIN") || isInUserGroupCode($userId, "HELP_DESK_STAFF") || isInUserGroup($userId, "HELP_DESK") || !empty($fullClientAccess) || !empty($superuserFlag)) {
			return $helpDeskEntryId;
		}
		$canAccessId = getFieldFromId("help_desk_entry_user_id", "help_desk_entry_users", "help_desk_entry_id", $helpDeskEntryId, "user_id = ?", $userId);
		if (!empty($canAccessId)) {
			return $helpDeskEntryId;
		}
		return false;
	}

	function getErrorMessage() {
		return $this->iErrorMessage;
	}

	function setDelayedNotification($delayedNotification) {
		$this->iDelayedNotification = $delayedNotification;
	}

	function addSubmittedData($submittedData) {
		if (is_array($submittedData)) {
			$this->iSubmittedData = $submittedData;
		}
	}

	function setHelpDeskTypeCode($helpDeskTypeCode) {
		if (!empty($this->iHelpDeskEntryId)) {
			$this->iErrorMessage = "Help Desk Type cannot be changed";
			return false;
		}
		$helpDeskTypeId = getFieldFromId("help_desk_type_id", "help_desk_types", "help_desk_type_code", $helpDeskTypeCode);
		if (empty($helpDeskTypeId)) {
			$this->iErrorMessage = "Invalid Help Desk Type";
			return false;
		}
		$this->iHelpDeskEntryRow['help_desk_type_id'] = $helpDeskTypeId;
		return true;
	}

	function setHelpDeskType($helpDeskTypeId) {
		if (!empty($this->iHelpDeskEntryId)) {
			$this->iErrorMessage = "Help Desk Type cannot be changed";
			return false;
		}
		$helpDeskTypeId = getFieldFromId("help_desk_type_id", "help_desk_types", "help_desk_type_id", $helpDeskTypeId);
		if (empty($helpDeskTypeId)) {
			$this->iErrorMessage = "Invalid Help Desk Type";
			return false;
		}
		$this->iHelpDeskEntryRow['help_desk_type_id'] = $helpDeskTypeId;
		return true;
	}

	function addHelpDeskTag($helpDeskTagId) {
		executeQuery("insert ignore into help_desk_tag_links (help_desk_entry_id,help_desk_tag_id) values (?,?)",$this->iHelpDeskEntryId,$helpDeskTagId);
	}

	function removeHelpDeskTag($helpDeskTagId) {
		executeQuery("delete from help_desk_tag_links where help_desk_entry_id = ? and help_desk_tag_id = ?",$this->iHelpDeskEntryId,$helpDeskTagId);
	}

	function addImageId($imageId, $privateAccess = false) {
		executeQuery("insert ignore into help_desk_entry_images (help_desk_entry_id,image_id,private_access) values (?,?,?)",
			$this->iHelpDeskEntryId, $imageId, ($privateAccess ? 1 : 0));
	}

	function addFileId($fileId, $privateAccess = false) {
		executeQuery("insert ignore into help_desk_entry_files (help_desk_entry_id,file_id,private_access) values (?,?,?)",
			$this->iHelpDeskEntryId, $fileId, ($privateAccess ? 1 : 0));
	}

	function addFiles($privateAccess = false) {
		$fileAdded = false;
		foreach ($_FILES as $fileKey => $fileInfo) {
			if (!empty($fileInfo['error'])) {
				continue;
			}
			$isImage = false;
			if (array_key_exists($_FILES[$fileKey]['type'], $GLOBALS['gMimeTypes'])) {
				$extension = $GLOBALS['gMimeTypes'][$_FILES[$fileKey]['type']];
				if (in_array($extension, array("gif", "jpg", "jpeg", "png"))) {
					$isImage = true;
				}
			}
			if ($isImage) {
				$imageId = createImage($fileKey);
				executeQuery("insert ignore into help_desk_entry_images (help_desk_entry_id,image_id,private_access) values (?,?,?)",
					$this->iHelpDeskEntryId, $imageId, ($privateAccess ? 1 : 0));
			} else {
				$fileId = createFile($fileKey);
				executeQuery("insert ignore into help_desk_entry_files (help_desk_entry_id,file_id,private_access) values (?,?,?)",
					$this->iHelpDeskEntryId, $fileId, ($privateAccess ? 1 : 0));
			}
			$fileAdded = true;
		}
		if (!$privateAccess && $fileAdded) {
			$emailId = getFieldFromId("email_id", "emails", "email_code", "HELP_DESK_ENTRY_UPDATED", "inactive = 0");
			if (empty($emailId)) {
				$helpDeskEntryDescription = getFieldFromId("description", "help_desk_entries", "help_desk_entry_id", $this->iHelpDeskEntryId);
				$body = "<p>Help Desk Ticket #" . $this->iHelpDeskEntryId . " (" . $helpDeskEntryDescription . ") has had a file added by %current_user_display_name%. Action Taken: %action_taken%.</p><p><strong>Content:</strong></p>";
				$subject = "[" . $GLOBALS['gClientRow']['business_name'] . "] (Ticket #" . $this->iHelpDeskEntryId . ") " . $helpDeskEntryDescription;
			}
			$this->sendNotification(array("email_id" => $emailId, "body" => $body, "subject" => $subject, "action_taken" => "File added",
                "additional_content"=> "A file was attached to the ticket.","include_contact" => true));
		}
	}

	function sendNotification($parameters) {
		if (empty($parameters['action_taken']) && empty($parameters['original_data'])) {
			$newRecord = true;
		} else {
			$newRecord = false;
		}
		$emailAddresses = array();
		$bccEmailAddresses = array();
		$blindCopyStaff = false;
		if ($parameters['include_contact'] && $this->iHelpDeskEntryRow['contact_id'] != $GLOBALS['gUserRow']['contact_id']) {
			$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $this->iHelpDeskEntryRow['contact_id']);
			if (!empty($emailAddress) && !in_array($emailAddress, $emailAddresses)) {
				$emailAddresses[] = $emailAddress;
				$blindCopyStaff = true;
			}
		}
		if ($GLOBALS['gUserId'] != $this->iHelpDeskEntryRow['user_id']) {
			$emailAddress = Contact::getUserContactField($this->iHelpDeskEntryRow['user_id'],"email_address");
			if ($blindCopyStaff) {
				if (!empty($emailAddress) && !in_array($emailAddress, $bccEmailAddresses)) {
					$bccEmailAddresses[] = $emailAddress;
				}
			} else {
				if (!empty($emailAddress) && !in_array($emailAddress, $emailAddresses)) {
					$emailAddresses[] = $emailAddress;
				}
			}
		}
		$notifyUserGroup = getFieldFromId("notify_user_group", "help_desk_types", "help_desk_type_id", $this->iHelpDeskEntryRow['help_desk_type_id']);
		if (!empty($notifyUserGroup) && !empty($this->iHelpDeskEntryRow['user_group_id'])) {
			$resultSet = executeQuery("select email_address from contacts where email_address is not null and " .
				"contact_id in (select contact_id from users where inactive = 0 and user_id in (select user_id from user_group_members where user_id <> ? and user_group_id = ?))", $GLOBALS['gUserId'], $this->iHelpDeskEntryRow['user_group_id']);
			while ($row = getNextRow($resultSet)) {
				$emailAddress = $row['email_address'];
				if ($emailAddress == $GLOBALS['gUserRow']['email_address']) {
					continue;
				}
				if ($blindCopyStaff) {
					if (!empty($emailAddress) && !in_array($emailAddress, $bccEmailAddresses)) {
						$bccEmailAddresses[] = $emailAddress;
					}
				} else {
					if (!empty($emailAddress) && !in_array($emailAddress, $emailAddresses)) {
						$emailAddresses[] = $emailAddress;
					}
				}
			}
		}
		$resultSet = executeQuery("select * from help_desk_type_notifications where help_desk_type_id = ?", $this->iHelpDeskEntryRow['help_desk_type_id']);
		while ($row = getNextRow($resultSet)) {
			$emailAddress = $row['email_address'];
			if ($emailAddress == $GLOBALS['gUserRow']['email_address']) {
				continue;
			}
			if ($blindCopyStaff) {
				if (!empty($emailAddress) && !in_array($emailAddress, $bccEmailAddresses)) {
					$bccEmailAddresses[] = $emailAddress;
				}
			} else {
				if (!empty($emailAddress) && !in_array($emailAddress, $emailAddresses)) {
					$emailAddresses[] = $emailAddress;
				}
			}
		}
		$resultSet = executeQuery("select * from help_desk_entry_users join users using (user_id) join contacts using (contact_id) where help_desk_entry_id = ?", $this->iHelpDeskEntryRow['help_desk_entry_id']);
		while ($row = getNextRow($resultSet)) {
			$emailAddress = $row['email_address'];
			if ($emailAddress == $GLOBALS['gUserRow']['email_address']) {
				continue;
			}
			if ($blindCopyStaff) {
				if (!empty($emailAddress) && !in_array($emailAddress, $bccEmailAddresses)) {
					$bccEmailAddresses[] = $emailAddress;
				}
			} else {
				if (!empty($emailAddress) && !in_array($emailAddress, $emailAddresses)) {
					$emailAddresses[] = $emailAddress;
				}
			}
		}
		$substitutions = array_merge($parameters, $this->iHelpDeskEntryRow);
		if (!empty($parameters['original_data'])) {
			foreach ($parameters['original_data'] as $fieldName => $fieldData) {
				$substitutions['original_' . $fieldName] = $fieldData;
			}
		}
		if (!empty($substitutions['help_desk_type_id'])) {
			$substitutions['help_desk_type'] = getFieldFromId("description", "help_desk_types", "help_desk_type_id", $substitutions['help_desk_type_id']);
		}
		if (!empty($substitutions['help_desk_category_id'])) {
			$substitutions['help_desk_category'] = getFieldFromId("description", "help_desk_categories", "help_desk_category_id", $substitutions['help_desk_category_id']);
		}
		if (!empty($substitutions['help_desk_status_id'])) {
			$substitutions['help_desk_status'] = getFieldFromId("description", "help_desk_statuses", "help_desk_status_id", $substitutions['help_desk_status_id']);
		}
		$substitutions['time_submitted'] = date("m/d/Y g:i a", strtotime($substitutions['time_submitted']));
		if (!empty($substitutions['contact_id'])) {
			$substitutions = array_merge($substitutions, Contact::getMultipleContactFields($substitutions['contact_id'],array("first_name", "last_name", "business_name", "city", "email_address")));
		}
		if (empty($substitutions['action_taken'])) {
			if ($newRecord) {
				$substitutions['action_taken'] = "Help Desk Entry added";
			} else {
				$substitutions['action_taken'] = "Help Desk Entry updated";
			}
		}
		$substitutions['current_user_display_name'] = getUserDisplayName();
		$substitutions['user_display_name'] = getUserDisplayName($this->iHelpDeskEntryRow['user_id']);
		$substitutions['contact_display_name'] = getDisplayName($this->iHelpDeskEntryRow['contact_id']);
		if (empty($substitutions['contact_display_name'])) {
			$substitutions['contact_display_name'] = getFieldFromId("email_address", "contacts", "contact_id", $this->iHelpDeskEntryRow['contact_id']);
		}
		if (fragmentExists("HELP_DESK_EMAIL_TEXT")) {
			$emailText = getFragment("HELP_DESK_EMAIL_TEXT");
		} else {
			$emailText = "Thank you for contacting us. Our team will get back to you as soon as possible. When replying, please make sure that the ticket ID is kept in the subject so that we can track your replies.";
		}
		if (empty($substitutions['additional_content'])) {
			$substitutions['additional_content'] = "";
		}
		$domainName = getDomainName();
		if (!empty($emailAddresses)) {
			$body = "<p>" . $emailText . "</p><p>----------</p><p><a href='" . $domainName . "/help-desk-dashboard?id=%help_desk_entry_id%'>Link to ticket</a></p>" . $parameters['body'] . ($newRecord ? "" : "<p>Original Subject: " . $this->iHelpDeskEntryRow['description'] . "</p>" .
					"<p>Original Content:</p>" . makeHtml($this->iHelpDeskEntryRow['content'])) . "<p>----------</p>";
			$substitutions['email_text'] = $emailText;
			$substitutions['original_content'] = makeHtml($this->iHelpDeskEntryRow['content']);
			$substitutions['original_description'] = $this->iHelpDeskEntryRow['description'];
			$body = str_replace("/getimage.php", $domainName . "/getimage.php", $body);
			sendEmail(array("email_id" => $parameters['email_id'], "body" => $body, "subject" => $parameters['subject'], "substitutions" => $substitutions,
				"email_addresses" => $emailAddresses, "bcc_addresses" => $bccEmailAddresses, "email_credential_code" => "HELP_DESK", "contact_id" => ($parameters['include_contact'] ? $this->iHelpDeskEntryRow['contact_id'] : ""),
				"email_credential_id" => getFieldFromId("email_credential_id", "help_desk_types", "help_desk_type_id", $substitutions['help_desk_type_id'])));
		}
		if ($newRecord) {
			$emailId = getFieldFromId("email_id", "help_desk_type_categories", "help_desk_type_id", $this->iHelpDeskEntryRow['help_desk_type_id'], "help_desk_category_id = ?", $this->iHelpDeskEntryRow['help_desk_category_id']);
			if (empty($emailId)) {
				$emailId = getFieldFromId("email_id", "help_desk_types", "help_desk_type_id", $this->iHelpDeskEntryRow['help_desk_type_id']);
			}
			if (!empty($emailId)) {
				$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $this->iHelpDeskEntryRow['contact_id']);
				if (!empty($emailAddress)) {
					$body = "<p>" . $emailText . "</p><p>----------</p><p><a href='" . $domainName . "/help-desk-dashboard?id=%help_desk_entry_id%'>Link to ticket</a></p>" . $parameters['body'] . "<p>----------</p>";
					$body = str_replace("/getimage.php", $domainName . "/getimage.php", $body);
					sendEmail(array("email_id" => $emailId, "body" => $body, "subject" => $parameters['subject'], "email_address" => $emailAddress, "substitutions" => $substitutions, "contact_id" => $this->iHelpDeskEntryRow['contact_id'],
						"email_credential_code" => "HELP_DESK", "email_credential_id" => getFieldFromId("email_credential_id", "help_desk_types", "help_desk_type_id", $substitutions['help_desk_type_id'])));
				}
			}
		}

		$mentionedUsersEmailAddresses = $this->getMentionedUsersEmailAddresses($newRecord ? $this->iHelpDeskEntryRow['content'] : $parameters['additional_content'], $emailAddresses);
		if (!empty($mentionedUsersEmailAddresses)) {
			$body = "<p>" . $emailText . "</p><p>----------</p><p><a href='" . $domainName . "/help-desk-dashboard?id=" . $this->iHelpDeskEntryRow['help_desk_entry_id'] . "'>Link to ticket</a></p>"
				. "You've been mentioned in Help Desk Ticket #" . $this->iHelpDeskEntryRow['help_desk_entry_id']
				. ($newRecord ? "" : "<p>Original Subject: " . $this->iHelpDeskEntryRow['description'] . "</p>" . "<p>Original Content:</p>" . makeHtml($this->iHelpDeskEntryRow['content'])) . "<p>----------</p>";
			$body = str_replace("/getimage.php", $domainName . "/getimage.php", $body);
			sendEmail(array("email_id" => $parameters['email_id'], "body" => $body, "subject" => $parameters['subject'], "substitutions" => $substitutions,
				"email_addresses" => $mentionedUsersEmailAddresses, "bcc_addresses" => $bccEmailAddresses, "email_credential_code" => "HELP_DESK", "contact_id" => ($parameters['include_contact'] ? $this->iHelpDeskEntryRow['contact_id'] : ""),
				"email_credential_id" => getFieldFromId("email_credential_id", "help_desk_types", "help_desk_type_id", $substitutions['help_desk_type_id'])));
		}
	}

	function getMentionedUsersEmailAddresses($content, $emailAddresses) {
		$mentionedUsersEmailAddresses = array();
		if (strpos($content, "user-mention") !== false) {
			try {
				$domDocument = new DOMDocument();
				$domDocument->loadHTML($content);

				foreach (iterator_to_array($domDocument->getElementsByTagName("a")) as $link) {
					$attributes = iterator_to_array($link->attributes);
					if ($attributes['class']->value == "user-mention" && !empty($attributes['data-user-id'])) {
						$mentionedUserEmailAddress = Contact::getUserContactField($attributes['data-user-id']->value,"email_address");

						// Filter out those email addresses that are already sent an email notification with
						if (!empty($mentionedUserEmailAddress) && !in_array($mentionedUserEmailAddress, $emailAddresses)) {
							$mentionedUsersEmailAddresses[] = $mentionedUserEmailAddress;
						}
					}
				}
			} catch (Exception $e) {
				$this->iErrorMessage = "Error parsing content to look for mentioned users.";
			}
		}
		return $mentionedUsersEmailAddresses;
	}

	function addPrivateNote($content, $parameters = array()) {
		if (empty($this->iHelpDeskEntryId)) {
			$this->iErrorMessage = "Save the Help Desk Entry first";
			return false;
		}
		if (empty($content)) {
			$this->iErrorMessage = "No content submitted";
			return false;
		}

		$dataSource = new DataTable("help_desk_private_notes");
		$content = processBase64Images($content);
		if (!$privateNoteId = $dataSource->saveRecord(array("name_values" => array("content" => $content, "user_id" => $GLOBALS['gUserId'], "help_desk_entry_id" => $this->iHelpDeskEntryId)))) {
			$this->iErrorMessage = getSystemMessage("basic", $dataSource->getErrorMessage());
			return false;
		}
		$emailId = getFieldFromId("email_id", "emails", "email_code", "HELP_DESK_ENTRY_UPDATED", "inactive = 0");
		if (empty($emailId)) {
			$helpDeskEntryDescription = getFieldFromId("description", "help_desk_entries", "help_desk_entry_id", $this->iHelpDeskEntryId);
			$body = "<p>Help Desk Ticket #" . $this->iHelpDeskEntryId . " (" . $helpDeskEntryDescription . ") has been updated by %current_user_display_name%. Action Taken: %action_taken%.</p><p><strong>Content:</strong></p>" . makeHtml($content);
			$subject = "[" . $GLOBALS['gClientRow']['business_name'] . "] (Ticket #" . $this->iHelpDeskEntryId . ") " . $helpDeskEntryDescription;
		}
		if ($parameters['close_ticket']) {
			$this->iHelpDeskEntryRow['time_closed'] = date("Y-m-d H:i:s");
			$this->save(array("no_notifications" => true, "ticket_closed" => true));
		}
		$this->sendNotification(array("email_id" => $emailId, "body" => $body, "subject" => $subject, "action_taken" => "Admin note added" . ($parameters['close_ticket'] ? " and ticket closed" : ""), "include_contact" => false, "additional_content" => makeHtml($content)));
		return $privateNoteId;
	}

	function save($parameters = array()) {
		if (is_array($this->iSubmittedData) && !empty($this->iSubmittedData)) {
			if (!empty($this->iHelpDeskEntryId)) {
				unset($this->iSubmittedData['contact_id']);
				unset($this->iSubmittedData['help_desk_type_id']);
				unset($this->iSubmittedData['content']);
			}
			foreach ($this->iSubmittedData as $fieldName => $fieldData) {
				$this->iHelpDeskEntryRow[$fieldName] = $fieldData;
			}
		}
		unset($this->iHelpDeskEntryRow['version']);
		if (empty($helpDeskEntryId)) {
			$this->iHelpDeskEntryRow['help_desk_type_id'] = getFieldFromId("help_desk_type_id", "help_desk_types", "help_desk_type_id", $this->iHelpDeskEntryRow['help_desk_type_id']);
			if (empty($this->iHelpDeskEntryRow['contact_id']) || empty($this->iHelpDeskEntryRow['help_desk_type_id']) || empty($this->iHelpDeskEntryRow['content']) || empty($this->iHelpDeskEntryRow['description'])) {
				$this->iErrorMessage = "Required information is missing. Contact, Help Desk Type, Description and Content are required";
				return false;
			}
		}
		if (empty($this->iHelpDeskEntryId)) {
			$originalData = array();
			$helpDeskTypeRow = getRowFromId("help_desk_types", "help_desk_type_id", $this->iHelpDeskEntryRow['help_desk_type_id']);
			foreach (array("user_group_id", "user_id", "priority") as $fieldName) {
				if (empty($this->iHelpDeskEntryRow[$fieldName])) {
					$this->iHelpDeskEntryRow[$fieldName] = $helpDeskTypeRow[$fieldName];
				}
			}
			if (!empty($this->iHelpDeskEntryRow['help_desk_type_id']) && empty($this->iHelpDeskEntryRow['help_desk_status_id'])) {
				$this->iHelpDeskEntryRow['help_desk_status_id'] = getFieldFromId("help_desk_status_id", "help_desk_type_categories", "help_desk_type_id", $this->iHelpDeskEntryRow['help_desk_type_id'], "help_desk_category_id = ?", $this->iHelpDeskEntryRow['help_desk_category_id']);
			}
		} else {
			$originalData = getRowFromId("help_desk_entries", "help_desk_entry_id", $this->iHelpDeskEntryId);
		}
		$dataSource = new DataTable("help_desk_entries");
		$this->iHelpDeskEntryRow['content'] = processBase64Images($this->iHelpDeskEntryRow['content']);
		if (empty($this->iHelpDeskEntryId) && !empty($this->iHelpDeskEntryRow['contact_id']) && empty($this->iHelpDeskEntryRow['user_id'])) {
			$assignedUserId = CustomField::getCustomFieldData($this->iHelpDeskEntryRow['contact_id'],"TICKET_ASSIGNED_USER_ID");
			$assignedUserId = getFieldFromId("user_id","users","user_id",$assignedUserId,"superuser_flag = 1");
			if (!empty($assignedUserId)) {
				$this->iHelpDeskEntryRow['user_id'] = $assignedUserId;
			}
		}
		$helpDeskEntryId = $dataSource->saveRecord(array("name_values" => $this->iHelpDeskEntryRow, "primary_id" => $this->iHelpDeskEntryId));
		if (!$helpDeskEntryId) {
			$this->iErrorMessage = $dataSource->getErrorMessage();
			return false;
		}
		if (!empty($this->iHelpDeskEntryRow['contact_id'])) {
			executeQuery("insert ignore into help_desk_entry_votes (help_desk_entry_id,contact_id) values (?,?)", $helpDeskEntryId, $this->iHelpDeskEntryRow['contact_id']);
		}
		$this->iHelpDeskEntryRow = getRowFromId("help_desk_entries", "help_desk_entry_id", $helpDeskEntryId);
		$body = "";
		$subject = "";
		if (empty($this->iHelpDeskEntryId)) {
			$emailId = getFieldFromId("email_id", "emails", "email_code", "HELP_DESK_ENTRY_CREATED", "inactive = 0");
		} else {
			$emailId = getFieldFromId("email_id", "emails", "email_code", "HELP_DESK_ENTRY_UPDATED", "inactive = 0");
		}
		if (empty($emailId)) {
			$helpDeskEntryDescription = getFieldFromId("description", "help_desk_entries", "help_desk_entry_id", $this->iHelpDeskEntryId);
			if (empty($this->iHelpDeskEntryId)) {
				$body = "<p>Help Desk Ticket #" . $helpDeskEntryId . " has been created by %contact_display_name%.</p><p>Content:</p>%content%";
				$subject = "[" . $GLOBALS['gClientRow']['business_name'] . "] (Ticket #" . $helpDeskEntryId . ") " . $helpDeskEntryDescription;
			} else {
				$body = "<p>Help Desk Ticket #" . $this->iHelpDeskEntryId . " (" . $originalData['description'] . ") has been updated by %current_user_display_name%. Action Taken: %action_taken%.</p>";
				$subject = "[" . $GLOBALS['gClientRow']['business_name'] . "] (Ticket #" . $this->iHelpDeskEntryId . ") " . $helpDeskEntryDescription;
			}
		}

		$customFields = CustomField::getCustomFields("help_desk");
		foreach ($customFields as $thisCustomField) {
			$helpDeskTypeCustomFieldId = getFieldFromId("help_desk_type_custom_field_id", "help_desk_type_custom_fields", "custom_field_id", $thisCustomField['custom_field_id'],
				"help_desk_type_id = (select help_desk_type_id from help_desk_entries where help_desk_entry_id = ?)", $helpDeskEntryId);
			if (empty($helpDeskTypeCustomFieldId)) {
				continue;
			}
			$customField = new CustomField($thisCustomField['custom_field_id']);
			if (!$customField) {
				$this->iErrorMessage = "Invalid Custom Field";
				return false;
			}
			$columnName = $customField->getColumnName();
			if (array_key_exists($columnName, $this->iSubmittedData)) {
				if (!$customField->saveData(array_merge(array("primary_id" => $helpDeskEntryId), $this->iSubmittedData))) {
					$this->iErrorMessage = $customField->getErrorMessage();
					return false;
				}
			}
		}

		$this->iHelpDeskEntryId = $helpDeskEntryId;
		if (empty($parameters['no_notifications'])) {
			$this->sendNotification(array("email_id" => $emailId, "body" => $body, "subject" => $subject, "original_data" => $originalData, "action_taken" => $parameters['action_taken']));
		}
		if ($parameters['ticket_closed']) {
			$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $this->iHelpDeskEntryRow['contact_id']);
			$reviewLinkName = getFieldFromId("link_name", "pages", "script_filename", "helpdeskentryreview.php");
			$resultSet = executeQuery("select * from help_desk_review_questions where inactive = 0");
			if (!empty($emailAddress) && !empty($reviewLinkName) && $resultSet['row_count'] > 0) {
				$emailId = getFieldFromId("email_id", "emails", "email_code", "HELP_DESK_REVIEW_INVITE", "inactive = 0");
				$body = "<p>Ticket #%help_desk_entry_id%, %description%, has been closed. Please help us improve our customer support by filling out a review regarding your experience with this ticket. Click <a href='%review_url%'>here</a> to open the review page.</p><p>%review_url%</p>";
				$subject = "Help Desk Ticket Review";
				$hashCode = md5($this->iHelpDeskEntryRow['contact_id'] . ":" . $this->iHelpDeskEntryRow['time_submitted']);
				$substitions = array("help_desk_entry_id" => $this->iHelpDeskEntryRow['help_desk_entry_id'], "description" => $this->iHelpDeskEntryRow['description'],
					"review_url" => "https://" . $_SERVER['HTTP_HOST'] . "/" . $reviewLinkName . "?id=" . $this->iHelpDeskEntryRow['help_desk_entry_id'] . "&hash=" . $hashCode);
				sendEmail(array("email_address" => $emailAddress, "body" => $body, "subject" => $subject, "email_id" => $emailId, "substitutions" => $substitions, "contact_id" => $this->iHelpDeskEntryRow['contact_id']));
			}
		}
		return $this->iHelpDeskEntryId;
	}

	function addVote($contactId) {
		$contactId = getFieldFromId("contact_id","contacts","contact_id",$contactId);
		if (empty($contactId)) {
			return false;
		}
		$helpDeskEntryVoteId = getFieldFromId("help_desk_entry_vote_id","help_desk_entry_votes","help_desk_entry_id",$this->iHelpDeskEntryId,"contact_id = ?",$contactId);
		if (empty($helpDeskEntryVoteId)) {
			$resultSet = executeQuery("insert ignore into help_desk_entry_votes (help_desk_entry_id,contact_id) values (?,?)",$this->iHelpDeskEntryId,$contactId);
			return empty($resultSet['sql_error']);
		}
		return true;
	}

	function getVoteCount() {
		$helpDeskEntryVoteId = getFieldFromId("help_desk_entry_vote_id","help_desk_entry_votes","help_desk_entry_id",$this->iHelpDeskEntryId,"contact_id = ?",$this->iHelpDeskEntryRow['contact_id']);
		if (empty($helpDeskEntryVoteId)) {
			$resultSet = executeQuery("insert ignore into help_desk_entry_votes (help_desk_entry_id,contact_id) values (?,?)",$this->iHelpDeskEntryId,$this->iHelpDeskEntryRow['contact_id']);
		}
		$count = 0;
		$resultSet = executeQuery("select count(*) from help_desk_entry_votes where help_desk_entry_id = ?",$this->iHelpDeskEntryId);
		if ($row = getNextRow($resultSet)) {
			$count = $row['count(*)'];
		}
		return $count;
	}

	function addPublicNote($content, $parameters = array()) {
		if (empty($this->iHelpDeskEntryId)) {
			$this->iErrorMessage = "Save the Help Desk Entry first";
			return false;
		}
		if (empty($content)) {
			$this->iErrorMessage = "No content submitted";
			return false;
		}

		if (array_key_exists("user_id", $parameters)) {
			$userId = $parameters['user_id'];
		} else {
			$userId = $GLOBALS['gUserId'];
		}
		if (empty($userId) && array_key_exists("email_address", $parameters)) {
			$emailAddress = $parameters['email_address'];
		} else {
			$emailAddress = "";
		}
		$dataSource = new DataTable("help_desk_public_notes");
		$content = processBase64Images($content);
		if (!$publicNoteId = $dataSource->saveRecord(array("name_values" => array("content" => $content, "user_id" => $userId, "email_address" => $emailAddress, "help_desk_entry_id" => $this->iHelpDeskEntryId)))) {
			$this->iErrorMessage = getSystemMessage("basic", $dataSource->getErrorMessage());
			return false;
		}
		$emailId = getFieldFromId("email_id", "emails", "email_code", "HELP_DESK_ENTRY_UPDATED", "inactive = 0");
		if (empty($emailId)) {
			$helpDeskEntryDescription = getFieldFromId("description", "help_desk_entries", "help_desk_entry_id", $this->iHelpDeskEntryId);
			$body = "<p>Help Desk Ticket #" . $this->iHelpDeskEntryId . " (" . $helpDeskEntryDescription . ") has been updated by %current_user_display_name%. Action Taken: %action_taken%.</p><p><strong>Content:</strong></p>" . makeHtml($content);
			$subject = "[" . $GLOBALS['gClientRow']['business_name'] . "] (Ticket #" . $this->iHelpDeskEntryId . ") " . $helpDeskEntryDescription;
		}
		if ($parameters['close_ticket']) {
			$this->iHelpDeskEntryRow['time_closed'] = date("Y-m-d H:i:s");
			$this->save(array("no_notifications" => true, "ticket_closed" => true));
		}
		$this->sendNotification(array("email_id" => $emailId, "body" => $body, "subject" => $subject, "action_taken" => "Public note added" . ($parameters['close_ticket'] ? " and ticket closed" : ""), "include_contact" => true, "additional_content" => makeHtml($content)));
		return $publicNoteId;
	}

	function markClosed($message = "") {
		if (!empty($this->iHelpDeskEntryRow['time_closed'])) {
			return true;
		}
		$this->iHelpDeskEntryRow['time_closed'] = date("Y-m-d H:i:s");
		if (!empty($this->iHelpDeskEntryId)) {
			return $this->save(array("action_taken" => (empty($message) ? "Mark closed" : $message), "ticket_closed" => true));
		} else {
			return true;
		}
	}

	function reopen() {
		if (empty($this->iHelpDeskEntryRow['time_closed'])) {
			return true;
		}
		$this->iHelpDeskEntryRow['time_closed'] = "";
		if (!empty($this->iHelpDeskEntryId)) {
			return $this->save(array("action_taken" => "Reopen"));
		} else {
			return true;
		}
	}

	function assignToUser($userId) {
		if ($this->iHelpDeskEntryRow['user_id'] == $userId) {
			return true;
		}
		if (!empty($userId)) {
			$userId = getFieldFromId("user_id", "users", "user_id", $userId, "client_id = ? or superuser_flag = 1", $GLOBALS['gClientId']);
			if (empty($userId)) {
				$this->iErrorMessage = "Invalid User";
				return false;
			}
		}
		if (!empty($this->iHelpDeskEntryRow['user_id']) && $this->iHelpDeskEntryRow['user_id'] != $this->iHelpDeskEntryRow['previous_user_id']) {
			$this->iHelpDeskEntryRow['previous_user_id'] = $this->iHelpDeskEntryRow['user_id'];
		}
		$this->iHelpDeskEntryRow['user_id'] = $userId;
		if (!empty($this->iHelpDeskEntryId)) {
			return $this->save(array("action_taken" => "Assign to user " . getUserDisplayName($userId)));
		} else {
			return true;
		}
	}

	function assignToUserGroup($userGroupId) {
		if ($this->iHelpDeskEntryRow['user_group_id'] == $userGroupId) {
			return true;
		}
		if (!empty($userGroupId)) {
			$userGroupId = getFieldFromId("user_group_id", "user_groups", "user_group_id", $userGroupId, "client_id = ? or client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gDefaultClientId']);
			if (empty($userGroupId)) {
				$this->iErrorMessage = "Invalid User Group";
				return false;
			}
		}
		$this->iHelpDeskEntryRow['user_group_id'] = $userGroupId;
		if (!empty($this->iHelpDeskEntryId)) {
			return $this->save(array("no_notifications" => true));
		} else {
			return true;
		}
	}

	function setPriority($priority) {
		if ($this->iHelpDeskEntryRow['priority'] == $priority) {
			return true;
		}
		if (strlen($priority) > 0 && (!is_numeric($priority) || $priority < 0)) {
			$this->iErrorMessage = "Invalid Priority: " . $priority;
			return false;
		}
		$this->iHelpDeskEntryRow['priority'] = $priority;
		if (!empty($this->iHelpDeskEntryId)) {
			return $this->save(array("no_notifications" => true));
		} else {
			return true;
		}
	}

	function setDateDue($dateDue) {
		$dateDue = (empty($dateDue) ? "" : date("Y-m-d", strtotime($dateDue)));
		if ($this->iHelpDeskEntryRow['date_due'] == $dateDue) {
			return true;
		}
		$this->iHelpDeskEntryRow['date_due'] = $dateDue;
		if (!empty($this->iHelpDeskEntryId)) {
			return $this->save(array("no_notifications" => true));
		} else {
			return true;
		}
	}

	function setContact($contactId) {
		$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $contactId, "client_id is not null");
		if (empty($contactId)) {
			$this->iErrorMessage = "Invalid Contact";
			return false;
		}
		$this->iHelpDeskEntryRow['contact_id'] = $contactId;
		if (!empty($this->iHelpDeskEntryId)) {
			return $this->save(array("no_notifications" => true));
		} else {
			return true;
		}
	}

	function setContent($content) {
		if (!empty($this->iHelpDeskEntryId)) {
			$this->iErrorMessage = "Content cannot be changed";
			return false;
		}
		$this->iHelpDeskEntryRow['content'] = $content;
		return true;
	}

	function setDescription($description) {
		if ($this->iHelpDeskEntryRow['description'] == $description) {
			return true;
		}
		$this->iHelpDeskEntryRow['description'] = $description;
		if (!empty($this->iHelpDeskEntryId)) {
			return $this->save(array("action_taken" => "Set Description"));
		} else {
			return true;
		}
	}

	function setCategory($helpDeskCategoryId) {
		if ($this->iHelpDeskEntryRow['help_desk_category_id'] == $helpDeskCategoryId) {
			return true;
		}
		if (!empty($helpDeskCategoryId)) {
			$helpDeskCategoryId = getFieldFromId("help_desk_category_id", "help_desk_categories", "help_desk_category_id", $helpDeskCategoryId);
			if (empty($helpDeskCategoryId)) {
				$this->iErrorMessage = "Invalid Category";
				return false;
			}
		}
		$this->iHelpDeskEntryRow['help_desk_category_id'] = $helpDeskCategoryId;
		if (!empty($this->iHelpDeskEntryId)) {
			return $this->save(array("no_notifications" => true));
		} else {
			return true;
		}
	}

	function setCategoryCode($helpDeskCategoryCode) {
		if (empty($helpDeskCategoryCode)) {
			$helpDeskCategoryId = "";
		} else {
			$helpDeskCategoryId = getFieldFromId("help_desk_category_id", "help_desk_categories", "help_desk_category_code", $helpDeskCategoryCode);
			if (empty($helpDeskCategoryId)) {
				$this->iErrorMessage = "Invalid Category";
				return false;
			}
		}
		if ($this->iHelpDeskEntryRow['help_desk_category_id'] == $helpDeskCategoryId) {
			return true;
		}
		$this->iHelpDeskEntryRow['help_desk_category_id'] = $helpDeskCategoryId;
		if (!empty($this->iHelpDeskEntryId)) {
			return $this->save(array("no_notifications" => true));
		} else {
			return true;
		}
	}

	function setStatus($helpDeskStatusId) {
		if ($this->iHelpDeskEntryRow['help_desk_status_id'] == $helpDeskStatusId) {
			return true;
		}
		if (!empty($helpDeskStatusId)) {
			$helpDeskStatusId = getFieldFromId("help_desk_status_id", "help_desk_statuses", "help_desk_status_id", $helpDeskStatusId);
			if (empty($helpDeskStatusId)) {
				$this->iErrorMessage = "Invalid Status";
				return false;
			}
		}
		$this->iHelpDeskEntryRow['help_desk_status_id'] = $helpDeskStatusId;
		if (!empty($this->iHelpDeskEntryId)) {
			return $this->save(array("no_notifications" => true));
		} else {
			return true;
		}
	}

	function setStatusCode($helpDeskStatusCode) {
		if (empty($helpDeskStatusCode)) {
			$helpDeskStatusId = "";
		} else {
			$helpDeskStatusId = getFieldFromId("help_desk_status_id", "help_desk_statuses", "help_desk_status_code", $helpDeskStatusCode);
			if (empty($helpDeskStatusId)) {
				$this->iErrorMessage = "Invalid Status";
				return false;
			}
		}
		if ($this->iHelpDeskEntryRow['help_desk_status_id'] == $helpDeskStatusId) {
			return true;
		}
		$this->iHelpDeskEntryRow['help_desk_status_id'] = $helpDeskStatusId;
		if (!empty($this->iHelpDeskEntryId)) {
			return $this->save(array("no_notifications" => true));
		} else {
			return true;
		}
	}

# return whether the user has access to the Help Desk Ticket, either as staff to edit the ticket or the user to just view and add notes

	function getHelpDeskType() {
		return $this->iHelpDeskEntryRow['help_desk_type_id'];
	}

	function getSubmissionResponse() {
		$response = getFieldFromId("response_content", "help_desk_types", "help_desk_type_id", $this->iHelpDeskEntryRow['help_desk_type_id']);
		if (empty($response) && !empty($GLOBALS['gPageObject'])) {
			$response = getFragment("HELP_DESK_RESPONSE");
		}
		if (empty($response)) {
			$response = "The help desk ticket has been submitted.";
		}
		return makeHtml($response);
	}

}
