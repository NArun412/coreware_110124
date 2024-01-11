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

$GLOBALS['gPageCode'] = "POSTCOMMENTMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {
	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->getColumns("post_id")->setReferencedDescriptionColumns("title_text");
		$this->iDataSource->getPrimaryTable()->getColumns("contact_id")->setReferencedDescriptionColumns("first_name,last_name,email_address");
		$this->iDataSource->setFilterWhere("post_id in (select post_id from posts where client_id = " . $GLOBALS['gClientId'] . ")");
	}

	function setup() {
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addExcludeColumn(array("parent_post_comment_id"));
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add"));
		}
	}

	function afterGetRecord(&$returnArray) {
		$displayName = "";
		if (!empty($returnArray['contact_id']['data_value'])) {
			$displayName = getDisplayName($returnArray['contact_id']['data_value']);
			$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $returnArray['contact_id']['data_value']);
			$userName = getFieldFromId("user_name", "users", "contact_id", $returnArray['contact_id']['data_value']);
			if (!empty($emailAddress)) {
				if (!empty($displayName)) {
					$displayName .= ", ";
				}
				$displayName .= $emailAddress;
			}
			if (!empty($displayName)) {
				$displayName .= ", ";
			}
			if (!empty($userName)) {
				$displayName .= $userName;
			} else {
				$displayName .= "Not a user";
			}
		}
		$thisUserId = getFieldFromId("user_id", "users", "contact_id", $returnArray['contact_id']['data_value']);
		$returnArray['contact_id']['data_value'] = $displayName;
		if (empty($thisUserId)) {
			$returnArray['_auto_approve_row']['data_value'] = "<td colspan='2'><input type='hidden' id='auto_approve' name='auto_approve' value='0' /></td>";
		} else {
			$returnArray['_auto_approve_row']['data_value'] = "<td class='field-label'>&nbsp</td><td class='field-text'><input tabindex='10' class='field-text' type='checkbox' value='1' name='auto_approve' id='auto_approve' /><label class='checkbox-label' for='auto_approve'>Automatically approve comments from this user in the future</label></td>";
			$postApproval = getFieldFromId("post_comment_approval_id", "post_comment_approvals", "user_id", $thisUserId);
			$returnArray['auto_approve']['data_value'] = (empty($postApproval) ? "0" : "1");
			$returnArray['auto_approve']['crc_value'] = getCrcValue(empty($postApproval) ? "0" : "1");
		}
	}

	function beforeSaveChanges(&$dataValues) {
		unset($dataValues['contact_id']);
		unset($dataValues['post_id']);
		return true;
	}

	function afterSaveChanges($nameValues, $actionPerformed) {
		$thisContactId = getFieldFromId("contact_id", "post_comments", "post_comment_id", $nameValues['primary_id']);
		$thisUserId = Contact::getContactUserId($thisContactId);
		if (!empty($thisUserId)) {
			executeQuery("delete from post_comment_approvals where user_id = ?", $thisUserId);
			if ($nameValues['auto_approve'] == "1") {
				executeQuery("insert into post_comment_approvals (user_id) values (?)", $thisUserId);
				executeQuery("update post_comments set approved = 1 where contact_id = ?", $thisContactId);
			}
		}
		return true;
	}
}

$pageObject = new ThisPage("post_comments");
$pageObject->displayPage();
