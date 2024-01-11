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
} else {
	require_once "../shared/startup.inc";
}

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
	var $iColumnNames = array("css_content","javascript_code","link_name","link_url","script_arguments","script_filename","template_id");

	function setProcessCode() {
		$this->iProcessCode = "page_changes";
	}

	function process() {
		$resultSet = executeQuery("select * from page_data_changes where inactive = 0 and date_completed is null and change_date <= current_date");
		while ($row = getNextRow($resultSet)) {
			$clientId = getFieldFromId("client_id","pages","page_id",$row['page_id'],"client_id is not null");
			changeClient($clientId);
			if (!empty($row['template_data_id'])) {
				$templateDataRow = getRowFromId("template_data","template_data_id",$row['template_data_id']);
				$dataField = getFieldFromDataType($templateDataRow['data_type']);
				$fieldData = $row[$dataField];
				$pageDataSource = new DataSource("page_data");
				$pageDataSource->setSaveOnlyPresent(true);
				$dataSet = executeQuery("select * from page_data where page_id = ? and template_data_id = ?",$row['page_id'],$row['template_data_id']);
				if ($dataRow = getNextRow($dataSet)) {
					if (empty($fieldData)) {
						$pageDataSource->deleteRecord(array("primary_id"=>$row['page_data_id'],"ignore_subtables"=>array("images")));
					} else {
						$primaryId = $dataRow['page_data_id'];
					}
				} else {
					$dataRow = array("page_id"=>$row['page_id'],"template_data_id"=>$row['template_data_id']);
					$primaryId = "";
				}
				if (!empty($fieldData)) {
					$dataRow[$dataField] = $fieldData;
					$result = $pageDataSource->saveRecord(array("name_values"=>$dataRow,"primary_id"=>$primaryId));
					if (!$result) {
						$this->addResult($pageDataSource->getErrorMessage());
						$this->iErrorsFound = true;
					}
				} else {
					executeQuery("update page_data_changes set date_completed = now() where page_data_change_id = ?", $row['page_data_change_id']);
					$this->addResult("Change made to page data for template data '" . getFieldFromId("description", "template_data", "template_data_id", $row['template_data_id']) . "' for page '" .
						getFieldFromId("description", "pages", "page_id", $row['page_id']) . "'");
				}
			} else {
				$columnName = $row['column_name'];
				if (!in_array($columnName,$this->iColumnNames)) {
					continue;
				}
				switch ($columnName) {
				case "template_id":
					$dataType = "select";
					break;
				case "css_content":
				case "javascript_code":
					$dataType = "text";
					break;
				default:
					$dataType = "varchar";
				}
				$dataField = getFieldFromDataType($dataType);
				$fieldData = $row[$dataField];
				$pageDataSource = new DataSource("pages");
				$pageDataSource->setSaveOnlyPresent(true);
				$dataRow = array();
				$dataRow[$columnName] = $fieldData;
				$result = $pageDataSource->saveRecord(array("name_values"=>$dataRow,"primary_id"=>$row['page_id']));
				if (!$result) {
					$this->addResult($pageDataSource->getErrorMessage());
					$this->iErrorsFound = true;
				} else {
					executeQuery("update page_data_changes set date_completed = now() where page_data_change_id = ?",$row['page_data_change_id']);
					$this->addResult("Change made to page for column '" . $row['column_name'] . "' for page '" . getFieldFromId("description", "pages", "page_id", $row['page_id']) . "'");
				}
			}
		}
		$resultSet = executeQuery("select * from email_changes where inactive = 0 and date_completed is null and change_date <= current_date");
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			$emailSource = new DataSource("emails");
			$emailSource->setSaveOnlyPresent(true);
			$dataRow = array("content"=>$row['content']);
			if (!empty($row['subject'])) {
				$dataRow['subject'] = $row['subject'];
			}
			$result = $emailSource->saveRecord(array("name_values"=>$dataRow,"primary_id"=>$row['email_id']));
			if (!$result) {
				$this->addResult($emailSource->getErrorMessage());
				$this->iErrorsFound = true;
			} else {
				executeQuery("update email_changes set date_completed = now() where email_change_id = ?", $row['email_change_id']);
				$this->addResult("Change made to email '" . getFieldFromId("description", "emails", "email_id", $row['email_id']) . "'");
			}
		}

		$resultSet = executeQuery("select * from fragment_changes where date_completed is null and change_date <= current_date");
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			$fragmentSource = new DataSource("fragments");
			$fragmentSource->setSaveOnlyPresent(true);
			$dataRow = array("content"=>$row['content']);
			$result = $fragmentSource->saveRecord(array("name_values"=>$dataRow,"primary_id"=>$row['fragment_id']));
			if (!$result) {
				$this->addResult($fragmentSource->getErrorMessage());
				$this->iErrorsFound = true;
			}
			$this->addResult("Change made to fragment '" . getFieldFromId("description","fragments","fragment_id",$row['fragment_id']) . "'");
			executeQuery("update fragments set content = ? where fragment_id = ?",$row['content'],$row['fragment_id']);

		}
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
