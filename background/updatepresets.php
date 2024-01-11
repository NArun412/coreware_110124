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
	function setProcessCode() {
		$this->iProcessCode = "update_presets";
	}

	function process() {
		if ($GLOBALS['gSystemName'] == "COREWARE") {
			return;
		}
		$postParameters = array("connection_key" => "B6AA29BB062F44B94ADEA4766F3142EF");
		$response = getCurlReturn("https://defaults.coreware.com/api.php?action=get_presets", $postParameters);
		$responseArray = json_decode($response, true);

		$presetRecords = array();
		$insertCount = 0;
		$updateCount = 0;
		$this->addResult((is_array($responseArray) && array_key_exists("preset_records",$responseArray) ? count($responseArray['preset_records']) . " preset records found" : "Unable to get presets"));
		if (is_array($responseArray) && array_key_exists("preset_records",$responseArray)) {
			foreach ($responseArray['preset_records'] as $row) {
				$presetRecordRow = getRowFromId("preset_records", "preset_record_code", $row['preset_record_code']);
				if (empty($presetRecordRow)) {
					$insertSet = executeQuery("insert into preset_records (preset_record_code,description,table_name) values (?,?,?)", $row['preset_record_code'], $row['description'], $row['table_name']);
					$presetRecordRow = array("preset_record_id" => $insertSet['insert_id'], "preset_record_code" => $row['preset_record_code'], "description" => $row['description']);
					$insertCount++;
				} elseif ($presetRecordRow['description'] != $row['description']) {
					executeQuery("update preset_records set description = ? where preset_record_id = ?", $row['description'], $presetRecordRow['preset_record_id']);
					$updateCount++;
				}
				$presetRecords[$presetRecordRow['preset_record_code']] = $presetRecordRow['preset_record_id'];
			}
		}
		if (is_array($responseArray) && array_key_exists("preset_record_values",$responseArray)) {
			foreach ($responseArray['preset_record_values'] as $row) {
				$presetRecordId = $presetRecords[$row['preset_record_code']];
				if (empty($presetRecordId)) {
					continue;
				}
				$presetRecordValueRow = getRowFromId("preset_record_values", "preset_record_id", $presetRecordId, "column_name = ?", $row['column_name']);
				if (empty($presetRecordValueRow)) {
					$insertSet = executeQuery("insert into preset_record_values (preset_record_id,column_name,text_data) values (?,?,?)", $presetRecordId, $row['column_name'], $row['text_data']);
					$presetRecordRow = array("preset_record_id" => $insertSet['insert_id'], "preset_record_code" => $row['preset_record_code'], "description" => $row['description']);
				} elseif ($presetRecordRow['text_data'] != $row['text_data']) {
					executeQuery("update preset_record_values set text_data = ? where preset_record_value_id = ?", $row['text_data'], $presetRecordValueRow['preset_record_value_id']);
				}
			}
		}
		$this->addResult($insertCount . " presets added");
		$this->addResult($updateCount . " presets updated");
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
