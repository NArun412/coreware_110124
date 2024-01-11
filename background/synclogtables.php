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
        $this->iProcessCode = "sync_log_tables";
    }

    function process() {
		$logDBName = $GLOBALS['gPrimaryDatabase']->getName() . "logdb";
		$resultSet = executeQuery("show databases like '" . $logDBName . "'");
		if ($resultSet['row_count'] == 0) {
			$this->addResult("No Log DB Found");
			return;
		}
	    $this->addResult("Database " . $logDBName . " found");
        $tableArray = array();
		$resultSet = executeQuery("select * from tables where table_name like '%_log'");
		while ($row = getNextRow($resultSet)) {
			$tableArray[] = $row['table_name'];
		}
        foreach ($tableArray as $thisTable) {
			$resultSet = executeQuery("select * from information_schema.tables where table_schema = ? and table_name = ?",$logDBName,$thisTable);
			if ($resultSet['row_count'] == 0) {
				$resultSet = executeQuery("show create table " . $thisTable);
				if ($row = getNextRow($resultSet)) {
					$createScript = str_replace("CREATE TABLE `" . $thisTable . "`","CREATE TABLE " . $logDBName . "." . $thisTable,$row['Create Table']);
					$scriptLines = getContentLines($createScript);
					$createScript = "";
					foreach ($scriptLines as $index => $thisLine) {
						if (startsWith($thisLine,"CONSTRAINT")) {
							continue;
						}
						$createScript .= $thisLine . "\n";
					}
					$createScript = str_replace(",\n) ENGINE","\n) ENGINE",$createScript);
					$createSet = executeQuery($createScript);
					if (empty($createSet['sql_error'])) {
						$this->addResult($thisTable . " created");
					} else {
						$this->addResult($createSet['sql_error']);
					}
				}
			}
            $dataTable = new DataTable($thisTable);
            $primaryKey = $dataTable->getPrimaryKey();
            $lastId = 0;
            $resultSet = executeQuery("select max(" . $primaryKey . ") as max_id from " . $logDBName . "." . $thisTable);
            if ($row = getNextRow($resultSet)) {
                $lastId = $row['max_id'];
            }
            if (empty($lastId) || $lastId < 0) {
                $lastId = 0;
            }
            $resultSet = executeQuery("insert into " . $logDBName . "." . $thisTable . " select * from " . $thisTable . " where " . $primaryKey . " > " . $lastId);
			if (empty($resultSet['sql_error'])) {
				$this->addResult($resultSet['affected_rows'] . " rows inserted into " . $thisTable);
			} else {
				$this->addResult("Error syncing " . $thisTable . ": " . $resultSet['sql_error']);
			}
        }
    }
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
