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
	require_once "managementtemplate.inc";
	require_once "databaseupdates.inc";
} else {
	require_once "../shared/startup.inc";
	require_once "../managementtemplate.inc";
	require_once "../databaseupdates.inc";
}

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class ThisBackgroundProcess extends BackgroundProcess {

	var $iDatabaseUpdatesMade = false;

	function setProcessCode() {
		$this->iProcessCode = "core_database_updates";
	}

	function versionSort($a, $b) {
		return ($a->getVersion() < $b->getVersion()) ? -1 : 1;
	}

	function process() {

		# update management template

		$managementTemplateId = getFieldFromId("template_id", "templates", "template_code", "MANAGEMENT");
		$templateContents = getManagementTemplate();
		$javascriptCode = $templateContents['javascript_code'];
		$cssContent = $templateContents['css_content'];
		$content = $templateContents['html_content'];
		if (empty($managementTemplateId)) {
			$resultSet = executeQuery("insert into templates (client_id,template_code,description,css_content,javascript_code,content,include_crud) values (1,?,?,?,?,?,1)",
				"MANAGEMENT", "Management Template", $cssContent, $javascriptCode, $content);
			$managementTemplateId = $resultSet['insert_id'];
			$this->addResult("Management Template Created");
		} else {
			$resultSet = executeQuery("update templates set css_content = ?,javascript_code = ?,content = ? where template_id = ?", trim($cssContent), trim($javascriptCode), trim($content), $managementTemplateId);
			if ($resultSet['affected_rows'] > 0) {
				$this->addResult("Management Template Updated");
			}
		}
		$templateDataId = getFieldFromId("template_data_id", "template_data", "data_name", "primary_table_name");
		$templateDataUseId = getFieldFromId("template_data_use_id", "template_data_uses", "template_id", $managementTemplateId, "template_data_id = ?", $templateDataId);
		if (empty($templateDataUseId)) {
			$resultSet = executeQuery("insert ignore into template_data_uses (template_data_id,template_id,sequence_number) values (?,?,?)",
				$templateDataId, $managementTemplateId, 1);
		}
		$textChunk = getFieldFromId("content", "template_text_chunks", "template_id", $managementTemplateId, "template_text_chunk_code = 'COLOR_OVERRIDE'");
		if ($textChunk != $templateContents['color_override']) {
			executeQuery("delete from template_text_chunks where template_id = ? and template_text_chunk_code = 'COLOR_OVERRIDE'", $managementTemplateId);
			executeQuery("insert into template_text_chunks (template_text_chunk_code,template_id,description,content) values (?,?,?,?)", "COLOR_OVERRIDE", $managementTemplateId, "Color Override", $templateContents['color_override']);
			$this->addResult("Management Template Text Chunk Updated");
		}

		# Look for database updates

		$databaseVersion = getPreference("DATABASE_VERSION");
		$databaseUpdates = array();

		$updateClasses = array();
		foreach (get_declared_classes() as $class) {
			if (is_subclass_of($class, 'AbstractDatabaseUpdate')) {
				$updateClasses[] = $class;
			}
		}
		sort($updateClasses);
		foreach ($updateClasses as $class) {
			$updateNumber = str_replace("DatabaseUpdate", "", $class) - 0;
			if ($updateNumber > $databaseVersion) {
				if (class_exists($class)) {
					$thisUpdate = new $class($updateNumber);
					$databaseUpdates[] = $thisUpdate;
				}
			}
		}
		if (count($databaseUpdates) == 0) {
			$this->addResult("No updates to run");
			return;
		}
		$this->addResult(count($databaseUpdates) . " update" . (count($databaseUpdates) == 1 ? "" : "s") . " to run");
		usort($databaseUpdates, array($this, "versionSort"));
		$GLOBALS['gDatabaseUpdatesObject'] = $this;
		foreach ($databaseUpdates as $index => $thisUpdate) {
			$success = $thisUpdate->execute();
			if ($success) {
				$this->addResult("Finished Update " . $thisUpdate->getVersion());
			} else {
				$updateErrors = true;
				break;
			}
		}

		if ($this->iDatabaseUpdatesMade) {
			$pageId = $GLOBALS['gAllPageCodes']["CLEARCACHE"];
			$domainName = false;
			$resultSet = executeQuery("select * from domain_names where domain_client_id = ? and forward_domain_name is null and link_url is null order by domain_name_id",$GLOBALS['gDefaultClientId']);
			if ($row = getNextRow($resultSet)) {
				$domainName = $row['domain_name'];
			}
			if (!empty($pageId) && !empty($domainName)) {
				executeQuery("delete from page_text_chunks where page_id = ?", $pageId);
				$randomString = getRandomString(8);
				executeQuery("insert into page_text_chunks (page_text_chunk_code,page_id,description,content) values ('ACCESS_CODE',?,'Access Code',?)", $pageId, $randomString);

				if (substr($domainName,0,4) != "http") {
					$domainName = "https://" . $domainName;
				}
				$linkUrl = $domainName . "/clearcache.php?access_code=" . $randomString;
				$startTime = getMilliseconds();
				$curlHandle = curl_init($linkUrl);
				curl_setopt($curlHandle, CURLOPT_HEADER, 0);
				curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($curlHandle, CURLOPT_CONNECTTIMEOUT, 180);
				curl_setopt($curlHandle, CURLOPT_TIMEOUT, ($GLOBALS['gCurlTimeout'] * 4));
				$siteContent = curl_exec($curlHandle);
				curl_close($curlHandle);
				$endTime = getMilliseconds();
				if ($siteContent === false) {
					$this->addResult("Clear Cache URL ERROR: " . $linkUrl);
					$this->iErrorsFound = true;
				} else {
					$this->addResult("Clear Cache URL loaded: " . $linkUrl . ", Took " . round(($endTime - $startTime) / 1000, 2) . " seconds");
				}
				executeQuery("delete from page_text_chunks where page_id = ?", $pageId);
			}
		}
	}

	/*
	 * Return true if database changes are successfully made and no errors occurred
	 */
	function processDatabaseChanges($parameters) {
		$this->iDatabaseUpdatesMade = true;
		$GLOBALS['gPrimaryDatabase']->startTransaction();
		$results = Database::updateDatabase($parameters);
		if (is_array($results) && array_key_exists("errors",$results) && !empty($results['errors'])) {
			$this->iErrorsFound = true;
			foreach ($results['errors'] as $thisError) {
				$this->addResult($thisError);
			}
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
		} else {
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
		}
		if (is_array($results) && array_key_exists("output",$results) && !empty($results['output'])) {
			foreach ($results['output'] as $thisLine) {
				$this->addResult($thisLine);
			}
		}
		return (is_scalar($results) ? $results : empty($results['errors']));
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
