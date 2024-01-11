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

$GLOBALS['gPageCode'] = "SYSTEMNOTICEMAINT";
require_once "shared/startup.inc";
$GLOBALS['gLimitEditableListRows'] = true;

class ThisPage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setFormSortOrder(array("subject","content","creator_user_id","time_submitted","start_time","end_time","display_color","all_user_access","inactive","system_notice_users"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("system_notice_users"));
		$this->iDataSource->addColumnControl("all_clients","data_type","tinyint");
		$this->iDataSource->addColumnControl("all_clients","form_label","Add to all clients");

		$this->iDataSource->addColumnControl("start_date","data_type","date");
		$this->iDataSource->addColumnControl("start_date","form_label","Start Date");
		$this->iDataSource->addColumnControl("start_time_part","data_type","time");
		$this->iDataSource->addColumnControl("start_time_part","form_label","Start Time");
		$this->iDataSource->addColumnControl("end_date","data_type","date");
		$this->iDataSource->addColumnControl("end_date","form_label","End Date");
		$this->iDataSource->addColumnControl("end_time_part","data_type","time");
		$this->iDataSource->addColumnControl("end_time_part","form_label","End Time");
	}

	function beforeSaveChanges(&$nameValues) {
		if (empty($nameValues['start_date'])) {
			$nameValues['start_time'] = "";
		} else {
			if (empty($nameValues['start_time_part'])) {
				$nameValues['start_time_part'] = "00:00";
			}
			$nameValues['start_time'] = $nameValues['start_date'] . " " . $nameValues['start_time_part'];
		}
		if (empty($nameValues['end_date'])) {
			$nameValues['end_time'] = "";
		} else {
			if (empty($nameValues['end_time_part'])) {
				$nameValues['end_time_part'] = "23:59";
			}
			$nameValues['end_time'] = $nameValues['end_date'] . " " . $nameValues['end_time_part'];
		}
		return true;
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['get_user_group'] = array("data_value"=>"");
		$returnArray['all_clients'] = array("data_value"=>"0");
		if (empty($returnArray['start_time']['data_value'])) {
			$returnArray['start_date'] = array("data_value" => "", "crc_value" => getCrcValue(""));
			$returnArray['start_time_part'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		} else {
			$returnArray['start_date'] = array("data_value" => date("m/d/Y", strtotime($returnArray['start_time']['data_value'])), "crc_value" => getCrcValue(date("m/d/Y", strtotime($returnArray['start_time']['data_value']))));
			$returnArray['start_time_part'] = array("data_value" => date("g:i a", strtotime($returnArray['start_time']['data_value'])), "crc_value" => getCrcValue(date("g:i a", strtotime($returnArray['start_time']['data_value']))));
		}
		if (empty($returnArray['end_time']['data_value'])) {
			$returnArray['end_date'] = array("data_value" => "", "crc_value" => getCrcValue(""));
			$returnArray['end_time_part'] = array("data_value" => "", "crc_value" => getCrcValue(""));
		} else {
			$returnArray['end_date'] = array("data_value" => date("m/d/Y", strtotime($returnArray['end_time']['data_value'])), "crc_value" => getCrcValue(date("m/d/Y", strtotime($returnArray['end_time']['data_value']))));
			$returnArray['end_time_part'] = array("data_value" => date("g:i a", strtotime($returnArray['end_time']['data_value'])), "crc_value" => getCrcValue(date("g:i a", strtotime($returnArray['end_time']['data_value']))));
		}
	}

	function afterSaveDone($nameValues) {
		$userIds = array();
		$resultSet = executeQuery("select * from system_notice_users where system_notice_id = ? order by system_notice_user_id",$nameValues['primary_id']);
		while ($row = getNextRow($resultSet)) {
			if (in_array($row['user_id'],$userIds)) {
				$deleteSet = executeQuery("delete from system_notice_users where system_notice_user_id = ?",$row['system_notice_user_id']);
			} else {
				$userIds[] = $row['user_id'];
			}
		}
		if (!empty($nameValues['all_clients']) && (!empty($nameValues['all_user_access']) || !empty($nameValues['full_client_access'])) && $GLOBALS['gUserRow']['superuser_flag']) {
			$resultSet = executeQuery("select * from clients where inactive = 0");
			while ($row = getNextRow($resultSet)) {
				if ($row['client_id'] != $GLOBALS['gClientId']) {
					$nameValues['client_id'] = $row['client_id'];
					$nameValues['_add_hash'] = "";
					$this->iDataSource->saveRecord(array("name_values"=>$nameValues,"primary_id"=>""));
				}
			}
		}
		return true;
	}
}

$pageObject = new ThisPage("system_notices");
$pageObject->displayPage();
