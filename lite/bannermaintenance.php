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

$GLOBALS['gPageCode'] = "BANNERMAINT_LITE";
require_once "shared/startup.inc";

class BannerMaintenanceLitePage extends Page {

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("banner_group_links", "banner_context"));
        $this->iDataSource->addColumnControl("content", "wysiwyg", true);
		$this->iDataSource->addColumnControl("start_date", "data_type", "date");
		$this->iDataSource->addColumnControl("start_date", "form_label", "Start Date");
		$this->iDataSource->addColumnControl("start_time_part", "data_type", "time");
		$this->iDataSource->addColumnControl("start_time_part", "form_label", "Time");
		$this->iDataSource->addColumnControl("end_date", "data_type", "date");
		$this->iDataSource->addColumnControl("end_date", "form_label", "End Date");
		$this->iDataSource->addColumnControl("end_time_part", "data_type", "time");
		$this->iDataSource->addColumnControl("end_time_part", "form_label", "Time");

		$this->iDataSource->addColumnControl("banner_group_links", "form_label", "Groups");
		$this->iDataSource->addColumnControl("banner_group_links", "data_type", "custom");
		$this->iDataSource->addColumnControl("banner_group_links", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("banner_group_links", "links_table", "banner_group_links");
		$this->iDataSource->addColumnControl("banner_group_links", "control_table", "banner_groups");

		$this->iDataSource->addColumnControl("css_classes", "help_label", "Comma separated list of classes that will be added to banner");

        $this->iDataSource->addColumnControl("link_url", "help_label", "The Link URL is where the user will be taken when the banner is clicked");
        $this->iDataSource->addColumnControl("link_url", "data_type", "varchar");

	}

	function beforeSaveChanges(&$nameValues) {
        removeCachedData("page_module-banner_group", "*");
        removeCachedData("request_search_result", "*",true);

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
}

$pageObject = new BannerMaintenanceLitePage("banners");
$pageObject->displayPage();
