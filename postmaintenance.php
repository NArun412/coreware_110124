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

$GLOBALS['gPageCode'] = "POSTMAINT";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function setup() {
		$filters = array();
		$resultSet = executeQuery("select * from post_categories where client_id = ? order by sort_order,description",$GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$filters['post_category_id_' . $row['post_category_id']] = array("form_label"=>$row['description'],"where"=>"post_id in (select post_id from post_category_links where post_category_id = " . $row['post_category_id'] . ")","data_type"=>"tinyint");
		}
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->addFilters($filters);
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("post_comments","post_links","post_category_links","blog_subscription_emails","related_posts"));
		$this->iDataSource->addColumnControl("creator_user_id","get_choices","userChoices");
		$this->iDataSource->addColumnControl("date_created","readonly",true);
		$this->iDataSource->addColumnControl("date_created","default_value",date("m/d/Y"));
		$this->iDataSource->addColumnControl("publish_date","data_type","date");
		$this->iDataSource->addColumnControl("publish_date","default_value",date("m/d/Y"));
		$this->iDataSource->addColumnControl("publish_date","not_null",true);
		$this->iDataSource->addColumnControl("publish_date","form_label","Publish Date");
        $this->iDataSource->addColumnControl("publish_time_part","data_type","time");
        $this->iDataSource->addColumnControl("publish_time_part","form_label","Publish Time");
        $this->iDataSource->addColumnControl("publish_time_part","help_label","leave blank for midnight");

        $this->iDataSource->addColumnControl("related_posts","data_type","custom");
        $this->iDataSource->addColumnControl("related_posts","form_label","Related Posts");
        $this->iDataSource->addColumnControl("related_posts","control_class","MultipleSelect");
        $this->iDataSource->addColumnControl("related_posts","control_table","posts");
		$this->iDataSource->addColumnControl("related_posts","links_table","related_posts");
		$this->iDataSource->addColumnControl("related_posts","control_description_field","title_text");
		$this->iDataSource->addColumnControl("related_posts","control_key","associated_post_id");
	}

	function beforeDeleteRecord($primaryId) {
		executeQuery("update post_comments set parent_post_comment_id = null where post_id = ?",$primaryId);
		executeQuery("delete from related_posts where associated_post_id = ?",$primaryId);
		return true;
	}

	function beforeSaveChanges(&$nameValues) {
		foreach ($nameValues as $fieldName => $fieldContent) {
			$nameValues[$fieldName] = processBase64Images($fieldContent);
		}
		$nameValues['publish_time'] = date("Y-m-d",strtotime($nameValues['publish_date'])) . " " . (empty($nameValues['publish_time_part']) ? "00:00:00" : date("H:i:s",strtotime($nameValues['publish_time_part'])));
		return true;
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['publish_date'] = array("data_value"=>date("m/d/Y",strtotime($returnArray['publish_time']['data_value'])));
		$returnArray['publish_date']['crc_value'] = getCrcValue($returnArray['publish_date']['data_value']);
		$returnArray['publish_time_part'] = array("data_value"=>(empty($returnArray['primary_id']['data_value']) ? "" : date("g:i a",strtotime($returnArray['publish_time']['data_value']))));
		$returnArray['publish_time_part']['crc_value'] = getCrcValue($returnArray['publish_time_part']['data_value']);
		$customFields = CustomField::getCustomFields("posts");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			$customFieldData = $customField->getRecord($returnArray['primary_id']['data_value']);
			if (array_key_exists("select_values",$returnArray) && array_key_exists("select_values",$customFieldData)) {
				$returnArray['select_values'] = $customFieldData['select_values'] = array_merge($returnArray['select_values'],$customFieldData['select_values']);
			}
			$returnArray = array_merge($returnArray,$customFieldData);
		}
	}

	function addCustomFields() {
		$customFields = CustomField::getCustomFields("posts");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getControl();
		}
	}

	function jqueryTemplates() {
		$customFields = CustomField::getCustomFields("posts");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			echo $customField->getTemplate();
		}
	}

	function afterSaveChanges($nameValues,$actionPerformed) {
		$customFields = CustomField::getCustomFields("posts");
		foreach ($customFields as $thisCustomField) {
			$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
			if (!$customField->saveData($nameValues)) {
				return $customField->getErrorMessage();
			}
		}
		return true;
	}
}

$pageObject = new ThisPage("posts");
$pageObject->displayPage();
