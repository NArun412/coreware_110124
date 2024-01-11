<?php

# Help Desk Methods

switch ($_POST['action']) {
	case "create_ticket":
		$nameValues = $_POST;
		$nameValues['contact_id'] = $GLOBALS['gUserRow']['contact_id'];
		if (empty($nameValues['contact_id'])) {
			$returnArray['error_message'] = "Login is required";
			break;
		}
		if (!empty($_POST['help_desk_type_id'])) {
			$nameValues['help_desk_type_id'] = getFieldFromId("help_desk_type_id", "help_desk_types", "help_desk_type_id", $_POST['help_desk_type_id']);
		} else {
			$nameValues['help_desk_type_id'] = getFieldFromId("help_desk_type_id", "help_desk_types", "help_desk_type_code", $_POST['help_desk_type_code']);
		}
		if (empty($nameValues['help_desk_type_id'])) {
			$returnArray['error_message'] = "Invalid Help Desk Type";
			break;
		}
		if (empty($nameValues['description']) || empty($nameValues['help_desk_type_id']) || empty($nameValues['content'])) {
			$returnArray['error_message'] = "Help Desk Type, Content and Description are required";
			break;
		}
		$helpDeskEntry = new HelpDesk();
		$helpDeskEntry->addSubmittedData($nameValues);
		if (!$helpDeskEntry->save()) {
			$returnArray['error_message'] = $helpDeskEntry->getErrorMessage();
			break;
		}
		break;

	case "get_help_desk_tickets":
        $limitByContact = !empty($_POST['contact_id']) || !empty($_POST['email_address']);
		$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id']);
		if (empty($contactId)) {
			$contactId = getFieldFromId("contact_id", "contacts", "email_address", $_POST['email_address'], "contact_id in (select contact_id from help_desk_entries)");
		}
		$query = "select help_desk_entry_id,description,help_desk_type_id,help_desk_category_id,user_id,time_submitted,time_closed,help_desk_status_id," .
			"(select concat_ws('|',time_submitted,user_id) from help_desk_public_notes where help_desk_entry_id = help_desk_entries.help_desk_entry_id union " .
			"select concat_ws('|',time_submitted,user_id) from help_desk_private_notes where help_desk_entry_id = help_desk_entries.help_desk_entry_id order by time_submitted desc limit 1) as last_activity from " .
			"help_desk_entries where client_id = ?" . (empty($_POST['include_closed']) ? " and time_closed is null" : "");
		$parameters = array($GLOBALS['gClientId']);
		if ($limitByContact) {
			$query .= " and contact_id = ?";
			$parameters[] = $contactId;
		}
		if (empty($_POST['limit']) || !is_numeric($_POST['limit']) || $_POST['limit'] > 100 || $_POST['limit'] < 0) {
			$_POST['limit'] = 100;
		}
		if (empty($_POST['offset']) || !is_numeric($_POST['offset']) || $_POST['offset'] < 0) {
			$_POST['offset'] = 0;
		}
		$query .= " limit " . $_POST['limit'] . " offset " . $_POST['offset'];

		$resultSet = executeQuery($query,$parameters);
		$returnArray['help_desk_entries'] = array();
		while ($row = getNextRow($resultSet)) {
			$lastActivityParts = explode("|",$row['last_activity']);
			$row['last_activity_time'] = (empty($lastActivityParts[0]) ? "" : date("Y-m-d H:i:s", strtotime($lastActivityParts[0])));
			$row['last_activity_user_id'] = $lastActivityParts[1];
            $returnArray['help_desk_entries'][] = $row;
        }
		break;

	case "get_help_desk_ticket_details":
		$ticketSet = executeQuery("select * from help_desk_entries where client_id = ? and help_desk_entry_id = ?",$GLOBALS['gClientId'],$_POST['help_desk_entry_id']);
		$domainName = getDomainName();
		if ($ticketRow = getNextRow($ticketSet)) {
			$ticketRow['help_desk_entry_files'] = array();
			$ticketRow['content'] = str_replace("\"/getimage.php","\"" . $domainName . "/getimage.php",$ticketRow['content']);
			$resultSet = executeQuery("select *,(select description from files where file_id = help_desk_entry_files.file_id) description from help_desk_entry_files where help_desk_entry_id = ?",$ticketRow['help_desk_entry_id']);
			while ($row = getNextRow($resultSet)) {
				$ticketRow['help_desk_entry_files'][] = $row;
			}
			$ticketRow['help_desk_entry_images'] = array();
			$resultSet = executeQuery("select *,(select description from images where image_id = help_desk_entry_images.image_id) description from help_desk_entry_images where help_desk_entry_id = ?",$ticketRow['help_desk_entry_id']);
			while ($row = getNextRow($resultSet)) {
				$ticketRow['help_desk_entry_images'][] = $row;
			}
			$ticketRow['help_desk_entry_list_items'] = array();
			$resultSet = executeQuery("select * from help_desk_entry_list_items where help_desk_entry_id = ? order by sequence_number",$ticketRow['help_desk_entry_id']);
			while ($row = getNextRow($resultSet)) {
				$ticketRow['help_desk_entry_list_items'][] = $row;
			}
			$ticketRow['help_desk_entry_users'] = array();
			$resultSet = executeQuery("select * from help_desk_entry_users where help_desk_entry_id = ?",$ticketRow['help_desk_entry_id']);
			while ($row = getNextRow($resultSet)) {
				$ticketRow['help_desk_entry_users'][] = $row;
			}
			$ticketRow['help_desk_private_notes'] = array();
			$resultSet = executeQuery("select * from help_desk_private_notes where help_desk_entry_id = ?",$ticketRow['help_desk_entry_id']);
			while ($row = getNextRow($resultSet)) {
				$row['content'] = str_replace("\"/getimage.php","\"" . $domainName . "/getimage.php",$row['content']);
				$ticketRow['help_desk_private_notes'][] = $row;
			}
			$ticketRow['help_desk_public_notes'] = array();
			$resultSet = executeQuery("select * from help_desk_public_notes where help_desk_entry_id = ?",$ticketRow['help_desk_entry_id']);
			while ($row = getNextRow($resultSet)) {
				$row['content'] = str_replace("\"/getimage.php","\"" . $domainName . "/getimage.php",$row['content']);
				$ticketRow['help_desk_public_notes'][] = $row;
			}
            $returnArray['help_desk_entries'] = $ticketRow;
		} else {
			$returnArray['error_message'] = "Help Desk Entry not found";
		}
		break;

	case "close_help_desk_ticket":
		$helpDeskEntryId = getFieldFromId("help_desk_entry_id","help_desk_entries","help_desk_entry_id",$_POST['help_desk_entry_id']);
		if (empty($helpDeskEntryId)) {
			$returnArray['error_message'] = "Help Desk Entry not found";
			break;
		} else {
			$helpDesk = new HelpDesk($helpDeskEntryId);
			$helpDesk->markClosed();
		}
		break;

	case "reopen_help_desk_ticket":
		$helpDeskEntryId = getFieldFromId("help_desk_entry_id","help_desk_entries","help_desk_entry_id",$_POST['help_desk_entry_id']);
		if (empty($helpDeskEntryId)) {
			$returnArray['error_message'] = "Help Desk Entry not found";
		} else {
			$helpDesk = new HelpDesk($helpDeskEntryId);
			$helpDesk->reopen();
		}
		break;

	case "add_help_desk_ticket_private_note":
		$privateNote = true;
	case "add_help_desk_ticket_public_note":
		$helpDeskEntryId = getFieldFromId("help_desk_entry_id","help_desk_entries","help_desk_entry_id",$_POST['help_desk_entry_id']);
		if (empty($helpDeskEntryId)) {
			$returnArray['error_message'] = "Help Desk Entry not found";
			break;
		}
		$parameters = array();
		$userId = getFieldFromId("user_id","users","user_id",$_POST['user_id'],"inactive = 0");
		if (empty($userId) && !empty($_POST['user_id'])) {
			$returnArray['error_message'] = "Invalid User";
			break;
		}
		if ($privateNote) {
			if (empty($userId)) {
				$returnArray['error_message'] = "Invalid User";
				break;
			}
		} else {
			if (empty($_POST['email_address'])) {
				$returnArray['error_message'] = "User or email address is required";
				break;
			}
		}
		if (!empty($userId)) {
			$parameters['user_id'] = $userId;
		}
		if (!empty($_POST['email_address'])) {
			$parameters['email_address'] = $_POST['email_address'];
		}
		if (empty($_POST['content'])) {
			$returnArray['error_message'] = "Content is required";
			break;
		}
		$this->iDatabase->startTransaction();
		$helpDesk = new HelpDesk($helpDeskEntryId);
		if (!empty($_POST['close_ticket'])) {
			$parameters['close_ticket'] = true;
		}
		if ($privateNote) {
			if (!$helpDesk->addPrivateNote($_POST['content'], $parameters)) {
				$returnArray['error_message'] = $helpDesk->getErrorMessage();
				$this->iDatabase->rollbackTransaction();
				break;
			} else {
				$this->iDatabase->commitTransaction();
			}
		} else {
			if (!$helpDesk->addPublicNote($_POST['content'], $parameters)) {
				$returnArray['error_message'] = $helpDesk->getErrorMessage();
				$this->iDatabase->rollbackTransaction();
				break;
			} else {
				$this->iDatabase->commitTransaction();
			}
		}
		break;

	case "get_help_desk_types":
		$returnArray['help_desk_types'] = array();
		$resultSet = executeQuery("select * from help_desk_types where client_id = ? and inactive = 0",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$returnArray['help_desk_types'][] = $row;
		}
		break;

	case "get_help_desk_categories":
		$returnArray['help_desk_categories'] = array();
		$resultSet = executeQuery("select * from help_desk_categories where client_id = ? and inactive = 0",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$returnArray['help_desk_categories'][] = $row;
		}
		break;

	case "get_help_desk_statuses":
		$returnArray['help_desk_statuses'] = array();
		$resultSet = executeQuery("select * from help_desk_statuses where client_id = ? and inactive = 0",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$returnArray['help_desk_statuses'][] = $row;
		}
		break;

	case "get_help_desk_tags":
		$returnArray['help_desk_tags'] = array();
		$resultSet = executeQuery("select * from help_desk_tags where client_id = ? and inactive = 0",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$returnArray['help_desk_tags'][] = $row;
		}
		break;
}
