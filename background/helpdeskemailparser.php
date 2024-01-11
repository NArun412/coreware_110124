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
	include_once "classes/fetch/autoload.php";
} else {
	require_once "../shared/startup.inc";
	include_once "../classes/fetch/autoload.php";
}

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
	var $maxDBSize = 1000000;
	var $iIgnoreMessages = array();
	var $iIgnoreHeaders = array();
	var $iMessageCounts = array();
	var $iMaxMessages = 10;

	function setProcessCode() {
		$this->iProcessCode = "help_desk_email_parser";
	}

	function setIgnoreKeywords() {
        /* Preventing email loops:
        1. Check headers for auto-responder indicators
        2. Check email body for common auto-responder messages
        3. Only process iMaxMessages from the same email address at one time.
        Preference is set per client, so this needs to be re-run every time the client changes.
        */

        $this->iIgnoreHeaders[] = "auto-submitted";
        $this->iIgnoreHeaders[] = "x-auto-response-suppress";
        $this->iIgnoreHeaders[] = "list-id";
        $this->iIgnoreHeaders[] = "feedback-id";

        $this->iIgnoreMessages[] = "automated";
        $this->iIgnoreMessages[] = "out of the office";
        $this->iIgnoreMessages[] = "will get back to you";
        $this->iIgnoreMessages[] = "delivery has failed";
        $this->iIgnoreMessages[] = "couldn't be delivered";
        $this->iIgnoreMessages[] = "non-delivery report";
        $this->iIgnoreMessages[] = "mailbox is full";

        $allowHeaders = getPreference("HELP_DESK_ALLOW_EMAIL_HEADERS");
        if (!empty($allowHeaders)) {
            $allowHeaders = explode(",", $allowHeaders);
            foreach ($this->iIgnoreHeaders as $index => $header) {
                if (in_array($header, $allowHeaders)) {
                    unset($this->iIgnoreHeaders[$index]);
                }
            }
        }
        $allowMessages = getPreference("HELP_DESK_ALLOW_EMAIL_MESSAGES");
        if (!empty($allowMessages)) {
            $allowMessages = explode(",", $allowMessages);
            foreach ($this->iIgnoreMessages as $index => $message) {
                if (in_array($message, $allowMessages)) {
                    unset($this->iIgnoreMessages[$index]);
                }
            }
        }
    }

	function process() {

	    $this->setIgnoreKeywords();

		$this->maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
		if (empty($this->maxDBSize) || !is_numeric($this->maxDBSize)) {
			$this->maxDBSize = 1000000;
		}

# Scrap emails from email servers and create or update tickets

		$ticketsCreated = 0;
		$notesCreated = 0;
		$emailsProcessed = 0;
		$duplicatesFound = 0;
		$emailCredentialSet = executeQuery("select * from email_credentials where pop_host is not null and pop_port is not null and (email_credential_code = 'HELP_DESK' or " .
			"email_credential_id in (select email_credential_id from help_desk_types)) order by client_id");
		while ($emailCredentialRow = getNextRow($emailCredentialSet)) {
			if ($GLOBALS['gClientId'] != $emailCredentialRow['client_id']) {
				changeClient($emailCredentialRow['client_id']);
				$this->setIgnoreKeywords();
			}

			$server = new Server($emailCredentialRow['pop_host'], $emailCredentialRow['pop_port']);
			$server->setAuthentication((empty($emailCredentialRow['pop_user_name']) ? $emailCredentialRow['smtp_user_name'] : $emailCredentialRow['pop_user_name']),
				(empty($emailCredentialRow['pop_password']) ? $emailCredentialRow['smtp_password'] : $emailCredentialRow['pop_password']));
			$securitySetting = (empty($emailCredentialRow['pop_security_setting']) ? $emailCredentialRow['security_setting'] : $emailCredentialRow['pop_security_setting']);
			if (!empty($securitySetting) && $securitySetting != "none") {
				$server->setFlag($securitySetting);
			}
			$tryCount = 0;
			$errorMessage = "";
			while ($tryCount < 5) {
				$tryCount++;
				try {
					$messages = $server->search("UNSEEN", 5000);
					$errorMessage = "";
					break;
				} catch (Exception $error) {
					$errorMessage = $error->getMessage();

					$server = new Server($emailCredentialRow['pop_host'], $emailCredentialRow['pop_port']);
					$server->setAuthentication((empty($emailCredentialRow['pop_user_name']) ? $emailCredentialRow['smtp_user_name'] : $emailCredentialRow['pop_user_name']),
						(empty($emailCredentialRow['pop_password']) ? $emailCredentialRow['smtp_password'] : $emailCredentialRow['pop_password']));
					$securitySetting = (empty($emailCredentialRow['pop_security_setting']) ? $emailCredentialRow['security_setting'] : $emailCredentialRow['pop_security_setting']);
					if (!empty($securitySetting) && $securitySetting != "none") {
						$server->setFlag($securitySetting);
					}
					continue;
				}
			}
			if (!empty($errorMessage)) {
				$this->addResult($GLOBALS['gClientRow']['client_code'] . " - " . $emailCredentialRow['email_credential_code'] . " - " . $emailCredentialRow['description'] . ": " . $error->getMessage());
				continue;
			}
			$this->addResult($GLOBALS['gClientRow']['client_code'] . ": " . count($messages) . " emails found for email credential: " . $emailCredentialRow['email_credential_code'] . " - " . $emailCredentialRow['description']);

			foreach ($messages as $message) {
				if ($GLOBALS['gClientId'] != $emailCredentialRow['client_id']) {
					changeClient($emailCredentialRow['client_id']);
				}
				$toEmails = $message->getAddresses("to");
				$toEmailAddress = trim($toEmails[0]);
				$helpDeskIgnoreId = getFieldFromId("help_desk_ignore_email_address_id", "help_desk_ignore_email_addresses", "email_address", $toEmailAddress);
				if (!empty($helpDeskIgnoreId)) {
					$this->addResult("Ignore email (to): " . $toEmailAddress);
					try {
						$message->setFlag(Message::FLAG_SEEN);
					} catch (Exception $e) {
						$this->addResult($e->getMessage());
					}
					continue;
				}
				$fromEmails = $message->getAddresses("from");
				$fromEmailAddress = trim($fromEmails[0]);
				if (empty($this->iMessageCounts[$fromEmailAddress])) {
					$this->iMessageCounts[$fromEmailAddress] = 1;
				} else if ($this->iMessageCounts[$fromEmailAddress] >= $this->iMaxMessages) {
					if ($this->iMessageCounts[$fromEmailAddress] == $this->iMaxMessages) {
						$this->addResult("Ignoring more than " . $this->iMaxMessages . " emails from: " . $fromEmailAddress);
						$this->iMessageCounts[$fromEmailAddress]++;
					}
					try {
						$message->setFlag(Message::FLAG_SEEN);
					} catch (Exception $e) {
						$this->addResult($e->getMessage());
					}
					continue;
				} else {
					$this->iMessageCounts[$fromEmailAddress]++;
				}
				if ($fromEmailAddress == $emailCredentialRow['email_address']) {
					$this->addResult("Ignore email: " . $fromEmailAddress);
					try {
						$message->setFlag(Message::FLAG_SEEN);
					} catch (Exception $e) {
						$this->addResult($e->getMessage());
					}
					continue;
				}
				$helpDeskIgnoreId = getFieldFromId("help_desk_ignore_email_address_id", "help_desk_ignore_email_addresses", "email_address", $fromEmailAddress);
				if (!empty($helpDeskIgnoreId)) {
					$this->addResult("Ignore email (from): " . $fromEmailAddress);
					try {
						$message->setFlag(Message::FLAG_SEEN);
					} catch (Exception $e) {
						$this->addResult($e->getMessage());
					}
					continue;
				}

				$programLog = "";
				$subject = $message->getSubject();
				$programLog .= $subject;
				$addressTypes = array('to', 'cc', 'bcc', 'from', 'sender', 'replyTo');
				foreach ($addressTypes as $addressType) {
					$programLog .= ", " . ucwords($addressType) . ": " . implode(",", $message->getAddresses($addressType));
				}
				$ticketId = "";
				if (strpos($subject, "Ticket #") !== false) {
					$ticketPart = substr($subject, strpos($subject, "Ticket #") + strlen("Ticket #"));
					$ticketParts = explode(" ", $ticketPart);
					$ticketId = $ticketParts[0];
					if (is_numeric($ticketId)) {
						$ticketId = getFieldFromId("help_desk_entry_id", "help_desk_entries", "help_desk_entry_id", $ticketId, ($GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId'] ? "client_id is not null" : ""));
					} else {
						$ticketId = "";
					}
				}
				if (empty($ticketId) && strpos($subject, "[#") !== false) {
					$ticketPart = substr($subject, strpos($subject, "[#") + strlen("[#"));
					$ticketParts = explode(" ", $ticketPart);
					$ticketId = str_replace("]", "", str_replace(":", "", $ticketParts[0]));
					if (is_numeric($ticketId)) {
						$ticketId = getFieldFromId("help_desk_entry_id", "help_desk_entries", "help_desk_entry_id", $ticketId, ($GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId'] ? "client_id is not null" : ""));
					} else {
						$ticketId = "";
					}
				}
				if (empty($ticketId) && strpos($subject, "(Ticket #") !== false) {
					$ticketPart = substr($subject, strpos($subject, "(Ticket #") + strlen("(Ticket #"));
					$ticketParts = explode(" ", $ticketPart);
					$ticketId = str_replace(")", "", str_replace(":", "", $ticketParts[0]));
					if (is_numeric($ticketId)) {
						$ticketId = getFieldFromId("help_desk_entry_id", "help_desk_entries", "help_desk_entry_id", $ticketId, ($GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId'] ? "client_id is not null" : ""));
					} else {
						$ticketId = "";
					}
				}
				$newTicketDays = getPreference("CREATE_NEW_TICKET_AFTER_DAYS");
				if (!empty($newTicketDays) && is_numeric($newTicketDays) && $newTicketDays > 0) {
					$timeClosed = getFieldFromId("time_closed","help_desk_entries","help_desk_entry_id",$ticketId);
					if (!empty($timeClosed)) {
						$now = strtotime(date("Y-m-d"));
						$dateClosed = strtotime($timeClosed);
						$dateDifference = ($now - $dateClosed) / (60 * 60 * 24);
						if ($dateDifference > $newTicketDays) {
							$ticketId = "";
						}
					}
				}

				$messageDate = date("Y-m-d", $message->getDate());
				// Check for auto-response headers (e.g. non-delivery reports, mailbox full, etc.)
				$messageHeaders = $message->getRawHeaders();
				foreach ($this->iIgnoreHeaders as $thisIgnoreHeader) {
					if (stripos($messageHeaders, $thisIgnoreHeader) !== false) {
						$this->addResult("Ignore header: " . $thisIgnoreHeader . ", from " . $fromEmailAddress);
						try {
							$message->setFlag(Message::FLAG_SEEN);
						} catch (Exception $e) {
							$this->addResult($e->getMessage());
						}
						continue 2;
					}
				}
				$messageBody = $message->getMessageBody();
				foreach ($this->iIgnoreMessages as $thisIgnoreMessage) {
					if (stripos($messageBody, $thisIgnoreMessage) !== false) {
						$this->addResult("Ignore message: " . $thisIgnoreMessage);
						try {
							$message->setFlag(Message::FLAG_SEEN);
						} catch (Exception $e) {
							$this->addResult($e->getMessage());
						}
						continue 2;
					}
				}

				$attachments = $message->getAttachments();
				$attachedFiles = array();
				$attachedImages = array();
				if (!$attachments) {
					$attachments = array();
				}
				usort($attachments, array($this, "sortAttachments"));
				foreach ($attachments as $thisAttachment) {
					$originalFilename = $thisAttachment->getFileName();
					$mimeType = $thisAttachment->getMimeType();
					if (array_key_exists($mimeType, $GLOBALS['gMimeTypes'])) {
						$extension = $GLOBALS['gMimeTypes'][$mimeType];
					} else {
						$fileNameParts = explode(".", $originalFilename);
						$extension = $fileNameParts[count($fileNameParts) - 1];
					}

					if ($extension == "jpg" || $extension == "png") {
						$attachedImages[] = $thisAttachment;
					} else {
						$attachedFiles[] = $thisAttachment;
					}
				}
				$resultSet = executeQuery("select * from help_desk_email_limitations where client_id = ? and (email_address is null or email_address = ?) order by sort_order", $GLOBALS['gClientId'], strtolower($fromEmailAddress));
				while ($row = getNextRow($resultSet)) {
					if (empty($row['search_text']) && empty($row['email_address'])) {
						continue;
					}
					if (!empty($row['search_text'])) {
						if (strpos($messageBody, $row['search_text']) === false) {
							continue;
						}
					}
					if (!empty($row['ignore_images'])) {
						$attachedImages = array();
					}
					if (!empty($row['ignore_files'])) {
						$attachedFiles = array();
					}
					if (!empty($row['ignore_content'])) {
						$messageBody = "";
					}
				}
				if (empty($ticketId)) {
					foreach ($toEmails as $thisToEmailAddress) {
						$helpDeskTypeId = getFieldFromId("help_desk_type_id", "help_desk_types", "email_address", $thisToEmailAddress);
						if (!empty($helpDeskTypeId)) {
							break;
						}
					}
					if (empty($helpDeskTypeId)) {
						$helpDeskTypeId = getFieldFromId("help_desk_type_id", "help_desk_types", "email_credential_id", $emailCredentialRow['email_credential_id']);
					}
					if (empty($helpDeskTypeId)) {
						$this->addResult("No Help Desk Type found (#671), cannot create ticket: " . $toEmailAddress . ", " . $emailCredentialRow['email_credential_code'] . " - " . $emailCredentialRow['description']);
						continue;
					}
					$helpDeskTypeCode = getFieldFromId("help_desk_type_code", "help_desk_types", "help_desk_type_id", $helpDeskTypeId);
					$nameValues = array();
					$nameValues['description'] = getFirstPart($subject, 250);
					if (empty($nameValues['description'])) {
						$nameValues['description'] = "No Subject";
					}
					$nameValues['content'] = trim($messageBody);
					if (empty($nameValues['content'])) {
						$nameValues['content'] = $subject;
					}
					if (empty($nameValues['content'])) {
						$nameValues['content'] = "No Content";
					}
					$useClientId = $GLOBALS['gClientId'];
					$contactId = "";
					if ($GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId']) {
						$this->addResult("Processing Primary Client Ticket");
						$this->addResult("From Email: " . $fromEmailAddress);
						$resultSet = executeQuery("select * from contacts where email_address = ? and deleted = 0", $fromEmailAddress);
						if ($resultSet['row_count'] == 0) {
							$resultSet = executeQuery("select * from contacts where contact_id in (select contact_id from contact_emails where email_address = ?) and deleted = 0", $fromEmailAddress);
						}
						if ($resultSet['row_count'] == 0) {
							$fromEmailParts = explode("@", $fromEmailAddress);
							if (!empty($fromEmailParts[1])) {
								$resultSet = executeQuery("select * from domain_names where domain_client_id is not null and (domain_name = ? or domain_name = ?)",
									$fromEmailParts[1], (substr($fromEmailParts[1], 0, strlen("www.")) == "www." ? substr($fromEmailParts[1], strlen("www.")) : "www." . $fromEmailParts[1]));
								if ($row = getNextRow($resultSet)) {
									$useClientId = $row['domain_client_id'];
								}
							}
							$contactDataTable = new DataTable("contacts");
							$contactId = $contactDataTable->saveRecord(array("name_values" => array("email_address" => $fromEmailAddress,"client_id"=>$useClientId)));
						} else if ($resultSet['row_count'] == 1) {
							$row = getNextRow($resultSet);
							$contactId = $row['contact_id'];
							$useClientId = $row['client_id'];
						} else {
							$resultSet = executeQuery("select * from contacts where email_address = ? and deleted = 0 and contact_id in (select contact_id from help_desk_entries) order by date_created", $fromEmailAddress);
							if ($row = getNextRow($resultSet)) {
								$contactId = $row['contact_id'];
								$useClientId = $row['client_id'];
							}
							if (empty($contactId)) {
								$resultSet = executeQuery("select * from users where inactive = 0 and " .
									"contact_id in (select contact_id from contacts where email_address = ? and deleted = 0) order by last_login desc", $fromEmailAddress);
								if ($resultSet['row_count'] == 0) {
									$resultSet = executeQuery("select * from users where inactive = 0 and contact_id in (select contact_id from contact_emails where email_address = ? and contact_id in (select contact_id from contacts where deleted = 0)) order by last_login desc", $fromEmailAddress);
								}
								if ($row = getNextRow($resultSet)) {
									$contactId = $row['contact_id'];
									$useClientId = $row['client_id'];
								}
							}
							if (empty($contactId)) {
								$resultSet = executeQuery("select * from contacts where email_address = ? and deleted = 0 order by date_created", $fromEmailAddress);
								if ($resultSet['row_count'] == 0) {
									$resultSet = executeQuery("select * from contacts where contact_id in (select contact_id from contact_emails where email_address = ?) and deleted = 0 order by date_created", $fromEmailAddress);
								}
								if ($row = getNextRow($resultSet)) {
									$contactId = $row['contact_id'];
									$useClientId = $row['client_id'];
								}
							}
						}
						$helpDeskTypeId = getFieldFromId("help_desk_type_id", "help_desk_types", "help_desk_type_code", $helpDeskTypeCode, "client_id = ?", $useClientId);
						if (empty($helpDeskTypeId)) {
							$this->addResult("No Help Desk Type found (#925), cannot create ticket: " . $toEmailAddress . ", " . $emailCredentialRow['email_credential_code'] . " - " . $emailCredentialRow['description']);
							continue;
						}
						$nameValues['help_desk_type_id'] = $helpDeskTypeId;
					} else {
						$resultSet = executeQuery("select contact_id from contacts where email_address = ? and client_id = ? and deleted = 0", $fromEmailAddress, $GLOBALS['gClientId']);
						if ($resultSet['row_count'] == 0) {
							$resultSet = executeQuery("select contact_id from contacts where contact_id in (select contact_id from contact_emails where email_address = ?) and client_id = ? and deleted = 0", $fromEmailAddress, $GLOBALS['gClientId']);
						}
						if ($resultSet['row_count'] == 0) {
							$contactDataTable = new DataTable("contacts");
							$contactId = $contactDataTable->saveRecord(array("name_values" => array("email_address" => $fromEmailAddress)));
						} else if ($resultSet['row_count'] == 1) {
							$row = getNextRow($resultSet);
							$contactId = $row['contact_id'];
						} else {
							$resultSet = executeQuery("select contact_id from users where client_id = ? and inactive = 0 and " .
								"contact_id in (select contact_id from contacts where email_address = ? and client_id = ? and deleted = 0) order by last_login desc",
								$GLOBALS['gClientId'], $fromEmailAddress, $GLOBALS['gClientId']);
							if ($resultSet['row_count'] == 0) {
								$resultSet = executeQuery("select contact_id from users where client_id = ? and inactive = 0 and " .
									"contact_id in (select contact_id from contact_emails where email_address = ?) and " .
									"contact_id in (select contact_id from contacts where client_id = ? and deleted = 0) order by last_login desc",
									$GLOBALS['gClientId'], $fromEmailAddress, $GLOBALS['gClientId']);
							}
							if ($resultSet['row_count'] == 0) {
								$resultSet = executeQuery("select contact_id from contacts where email_address = ? and client_id = ? and deleted = 0 order by date_created", $fromEmailAddress, $GLOBALS['gClientId']);
								if ($resultSet['row_count'] == 0) {
									$resultSet = executeQuery("select contact_id from contacts where contact_id in (select contact_id from contact_emails where email_address = ?) and client_id = ? and deleted = 0 order by date_created", $fromEmailAddress, $GLOBALS['gClientId']);
								}
								$row = getNextRow($resultSet);
								$contactId = $row['contact_id'];
							} else {
								$row = getNextRow($resultSet);
								$contactId = $row['contact_id'];
							}
						}
						$nameValues['help_desk_type_id'] = $helpDeskTypeId;
					}

					if (empty($contactId)) {
						$this->addResult("Unable to get contact: " . $fromEmailAddress . ", " . $emailCredentialRow['email_credential_code'] . " - " . $emailCredentialRow['description']);
						continue;
					}
					if ($GLOBALS['gClientId'] != $useClientId) {
						changeClient($useClientId);
					}
					$programLog .= ", Contact: " . $contactId . "\n";
					$nameValues['contact_id'] = $contactId;

					$helpDeskEntryId = getFieldFromId("help_desk_entry_id", "help_desk_entries", "contact_id", $contactId, "description = ? and content = ? and help_desk_type_id = ?",
						$nameValues['description'], $nameValues['content'], $nameValues['help_desk_type_id']);
					if (!empty($helpDeskEntryId)) {
						$message->delete();
						$server->expunge();
						$duplicatesFound++;
						$this->addResult("Unable to create ticket, duplicate help desk ticket found");
						continue;
					}

					$helpDeskEntry = new HelpDesk();
					$helpDeskEntry->addSubmittedData($nameValues);
					if (!$ticketId = $helpDeskEntry->save()) {
						$GLOBALS['gPrimaryDatabase']->logError("Unable to create Ticket: " . $fromEmailAddress . ", " . $emailCredentialRow['email_credential_code'] . " - " .
							$emailCredentialRow['description'] . " - " . $helpDeskEntry->getErrorMessage());
						$this->addResult("Error: " . $helpDeskEntry->getErrorMessage());
						continue;
					}
					if (count($attachedImages) > 0) {
						$imageId = $this->createImage(array_shift($attachedImages));
						if (!empty($imageId)) {
							$helpDeskEntry->addImageId($imageId);
						}
					}
					if (count($attachedFiles) > 0) {
						$fileId = $this->createFile(array_shift($attachedFiles));
						if (!empty($fileId)) {
							$helpDeskEntry->addFileId($fileId);
						}
					}
					$ticketsCreated++;
					$messageBody = "";
					$userId = Contact::getContactUserId(getFieldFromId("contact_id", "help_desk_entries", "help_desk_entry_id", $ticketId));
				} else {
					$programLog .= ", Ticket: " . $ticketId . ", From: " . $fromEmailAddress . "\n";
					$useClientId = getFieldFromId("client_id", "help_desk_entries", "help_desk_entry_id", $ticketId, "client_id is not null");
					if ($GLOBALS['gClientId'] != $useClientId) {
						changeClient($useClientId);
					}

					$contactId = "";
					$resultSet = executeQuery("select * from contacts where email_address = ? and deleted = 0 and (client_id = ? or contact_id in (select contact_id from users where " .
						"superuser_flag = 1)) and (contact_id in (select contact_id from help_desk_entries where help_desk_entry_id = ?) or contact_id in (select " .
						"contact_id from users where user_id = (select user_id from help_desk_entries where help_desk_entry_id = ?) or user_id in (select user_id " .
						"from user_group_members where user_group_id = (select user_group_id from help_desk_entries where help_desk_entry_id = ?)) or user_id in " .
						"(select user_id from help_desk_entry_users where help_desk_entry_id = ?)))", $fromEmailAddress, $useClientId, $ticketId, $ticketId, $ticketId, $ticketId);
					if ($resultSet['row_count'] == 0) {
						$resultSet = executeQuery("select * from contacts where contact_id in (select contact_id from contact_emails where email_address = ?) and deleted = 0 and (client_id = ? or contact_id in (select contact_id from users where " .
							"superuser_flag = 1)) and (contact_id in (select contact_id from help_desk_entries where help_desk_entry_id = ?) or contact_id in (select " .
							"contact_id from users where user_id = (select user_id from help_desk_entries where help_desk_entry_id = ?) or user_id in (select user_id " .
							"from user_group_members where user_group_id = (select user_group_id from help_desk_entries where help_desk_entry_id = ?)) or user_id in " .
							"(select user_id from help_desk_entry_users where help_desk_entry_id = ?)))", $fromEmailAddress, $useClientId, $ticketId, $ticketId, $ticketId, $ticketId);
					}
					if ($resultSet['row_count'] == 0) {
						$resultSet = executeQuery("select * from contacts where email_address = ? and deleted = 0 and (client_id = ? or contact_id in (select contact_id from users where " .
							"superuser_flag = 1))", $fromEmailAddress, $useClientId);
					}
					if ($resultSet['row_count'] == 0) {
						$resultSet = executeQuery("select * from contacts where contact_id in (select contact_id from contact_emails where email_address = ?) and deleted = 0 and (client_id = ? or contact_id in (select contact_id from users where " .
							"superuser_flag = 1))", $fromEmailAddress, $useClientId);
					}
					if ($row = getNextRow($resultSet)) {
						$contactId = $row['contact_id'];
					}
					$userId = Contact::getContactUserId($contactId);
					if (empty($userId)) {
						$userId = getFieldFromId("user_id", "users", "contact_id", $contactId, "(client_id = ? or superuser_flag = 1)", $useClientId);
					}
				}

				$helpDesk = new HelpDesk($ticketId);
				while (!empty($messageBody) || count($attachedFiles) > 0 || count($attachedImages) > 0) {
					if (getPreference("REOPEN_HELP_DESK_ENTRY")) {
						$helpDesk->reopen();
					}
					if (empty($messageBody)) {
						if (count($attachedFiles) > 0) {
							$messageBody = "Attached File";
						}
						if (count($attachedImages) > 0) {
							$messageBody .= (empty($messageBody) ? "" : " and ") . "Attached Image";
						}
					} else {
						$helpDeskPublicNoteId = getFieldFromId("help_desk_public_note_id", "help_desk_public_notes", "help_desk_entry_id", $ticketId,
							"content = ?", $messageBody);
						if (!empty($helpDeskPublicNoteId)) {
							break;
						}
					}

					$publicNoteId = $helpDesk->addPublicNote($messageBody, array("user_id" => $userId, "email_address" => $fromEmailAddress));
					if (!empty($publicNoteId)) {
						if (count($attachedImages) > 0) {
							$imageId = $this->createImage(array_shift($attachedImages));
							if (!empty($imageId)) {
								$helpDesk->addImageId($imageId);
							} else {
								$GLOBALS['gPrimaryDatabase']->logError("Error creating image");
							}
						}
						if (count($attachedFiles) > 0) {
							$fileId = $this->createFile(array_shift($attachedFiles));
							if (!empty($fileId)) {
								$helpDesk->addFileId($fileId);
							} else {
								$GLOBALS['gPrimaryDatabase']->logError("Error creating file");
							}
						}
					} else {
						$GLOBALS['gPrimaryDatabase']->logError("Error creating public note: " . $helpDesk->getErrorMessage());
						break;
					}
					$messageBody = "";
					$notesCreated++;
				}
				$message->delete();
				$server->expunge();
				$emailsProcessed++;
				$this->addResult($programLog);
			}
		}
		$this->addResult($emailsProcessed . " emails processed");
		$this->addResult($ticketsCreated . " tickets created");
		$this->addResult($notesCreated . " ticket notes created");
	}

	function createImage($attachment) {
		if ($attachment->getSize() == 0) {
			return false;
		}
		$imageId = "";
		$originalFilename = $attachment->getFileName();
		if (empty($originalFilename)) {
			return false;
		}
		$mimeType = $attachment->getMimeType();
		if (array_key_exists($mimeType, $GLOBALS['gMimeTypes'])) {
			$extension = $GLOBALS['gMimeTypes'][$mimeType];
		} else {
			$fileNameParts = explode(".", $originalFilename);
			$extension = $fileNameParts[count($fileNameParts) - 1];
		}
		if ($attachment->getSize() < $this->maxDBSize) {
			$fileContent = $attachment->getData();
			$osFilename = "";
		} else {
			$fileContent = "";
			$osFilename = "/documents/tmp." . $extension;
		}
		$imageSize = $attachment->getSize();
		$resultSet = executeQuery("select * from images where client_id = ? and image_size = ?", $GLOBALS['gClientId'], $imageSize);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['os_filename'])) {
				$existingFileContent = $row['file_content'];
			} else {
				$existingFileContent = getExternalFileContents($row['os_filename']);
			}
			if (!empty($existingFileContent) && $attachment->getData() == $existingFileContent) {
				return $row['image_id'];
			}
		}
		if (empty($osFilename) && empty($fileContent)) {
			return false;
		}
		$resultSet = executeQuery("insert into images (client_id,description,os_filename,extension,filename,file_content,image_size,date_uploaded) values (?,?,?,?, ?,?,?,now())",
			$GLOBALS['gClientId'], $originalFilename, $osFilename, $extension, $originalFilename, $fileContent, $imageSize);
		$imageId = $resultSet['insert_id'];
		if (!empty($osFilename)) {
			putExternalImageContents($imageId, $extension, $attachment->getData());
		}
		return $imageId;
	}

	function createFile($attachment) {
		if ($attachment->getSize() == 0) {
			return false;
		}
		$fileId = "";
		$originalFilename = $attachment->getFileName();
		if (empty($originalFilename)) {
			return false;
		}
		$mimeType = $attachment->getMimeType();
		if (array_key_exists($mimeType, $GLOBALS['gMimeTypes'])) {
			$extension = $GLOBALS['gMimeTypes'][$mimeType];
		} else {
			$fileNameParts = explode(".", $originalFilename);
			$extension = $fileNameParts[count($fileNameParts) - 1];
		}

		if ($attachment->getSize() < $this->maxDBSize) {
			$fileContent = $attachment->getData();
			$osFilename = "";
		} else {
			$fileContent = "";
			$osFilename = "/documents/tmp." . $extension;
		}
		$resultSet = executeQuery("insert into files (file_id,client_id,description,date_uploaded," .
			"filename,extension,file_content,os_filename,administrator_access) values (null,?,?,now(),?,?,?,?,1)",
			$GLOBALS['gClientId'], $originalFilename, $originalFilename, $extension, $fileContent, $osFilename);
		$fileId = $resultSet['insert_id'];
		if (!empty($osFilename)) {
			putExternalFileContents($fileId, $extension, $attachment->getData());
		}
		return $fileId;
	}

	function sortAttachments($a, $b) {
		return ($a->getSize() > $b->getSize()) ? -1 : 1;
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
