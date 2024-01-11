<?php

/* This software is the unpublished, confidential, proprietary, intellectual
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
require_once "shared/startup.inc";
require_once "databaseupdates.inc";

if (!$GLOBALS['gCommandLine'] && !$GLOBALS['gUserRow']['superuser_flag']) {
    echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
    exit;
}

$coreUpdateRun = false;
$newCodeCounter = 0;

while (true) {
    // If new code is running, wait to start to avoid running code in an inconsistent state
    if (file_exists("/var/www/control/newcode_running") || file_exists("/var/www/html/cache/newcode_running")) {
        executeQuery("insert into background_process_log (results) values (?)", "Don't start background process while newcode is running");
        $newCodeCounter++;
        if ($newCodeCounter > 10) {
            $GLOBALS['gPrimaryDatabase']->logError("Newcode running more than 10 minutes on server " . $GLOBALS['gClientRow']['client_code']);
            break;
        }
        sleep(60);
        continue;
    }
    $results = "";
    $branchRef = getBranchRef();
    if(!empty($branchRef)) {
        $results .= "Running branch: $branchRef\n";
    }
    $databaseVersion = getPreference("DATABASE_VERSION");
    $updateClasses = array();
    foreach (get_declared_classes() as $class) {
        if (is_subclass_of($class, 'AbstractDatabaseUpdate')) {
            $updateClasses[] = $class;
        }
    }
    sort($updateClasses);
    foreach ($updateClasses as $class) {
        $updateNumber = intval(str_replace("DatabaseUpdate", "", $class));
        $lastUpdateVersion = $updateNumber;
    }
    $results .= "Current Database Version: $databaseVersion, Latest Update Version: $lastUpdateVersion\n";
    if ($lastUpdateVersion > $databaseVersion) {
        if (!$coreUpdateRun) {
            $resultSet = executeQuery("update background_processes set run_immediately = 1, inactive = 1 where background_process_code = 'core_database_updates'");
            if ($resultSet['affected_rows'] > 0) {
                $results .= "Core Database Updates tagged to run\n";
            }
            $coreUpdateRun = true;
        }
    }
    $allBackgroundProcesses = array();
    $resultSet = executeQuery("select * from background_processes");
    while ($row = getNextRow($resultSet)) {
        $allBackgroundProcesses[$row['background_process_code']] = $row;
    }
    // concurrent process restrictions
    $concurrentProcessRestrictions = array(['UPDATE_DISTRIBUTOR_INVENTORY_QUANTITIES', 'UPDATE_DISTRIBUTOR_INVENTORY','CALCULATE_PRODUCT_PRICES']);

    $psResult = shell_exec("ps -aeo pid,etime,command | grep background.*php$");
    foreach(array_map("trim", explode("\n", $psResult)) as $line) {
        foreach ($allBackgroundProcesses as $backgroundProcessCode => $backgroundProcess) {
            if (strpos($line, $backgroundProcess['script_filename']) !== false) {
                $parts = preg_split('/ +/', $line);
                $pid = $parts[0];
                $etime = $parts[1];
                $results .= "Process {$backgroundProcess['description']} running with pid $pid for $etime\n";
                $allBackgroundProcesses[$backgroundProcessCode]['pid'] = $pid;
            }
        }
    }
    $results .= "\n";

    $resultSet = executeQuery("select * from background_processes where inactive = 0 or run_immediately = 1");
    while ($row = getNextRow($resultSet)) {
        $results .= "Checking: " . $row['description'] . "\n";
        if(!empty($allBackgroundProcesses[$row['background_process_code']]['pid'])) {
            $results .= "Process already running\n";
            continue;
        }
        foreach($concurrentProcessRestrictions as $restrictedProcessCodes) {
            if(in_array($row['background_process_code'], $restrictedProcessCodes)) {
                $concurrentProcesses = 0;
                foreach($restrictedProcessCodes as $processCode) {
                    if(!empty($allBackgroundProcesses[$processCode]['pid'])) {
                        $results .= "Process with concurrency restriction already running: $processCode\n";
                        continue 3;
                    }
                }
            }
        }
        if (!empty($row['run_immediately'])) {
            $runProcess = true;
            $results .= "Run immediately\n";
        } else {
            $lastStartEpoch = (empty($row['last_start_time']) ? 0 : round(date("U", strtotime($row['last_start_time'])) / 60));
            $currentEpoch = round(date("U") / 60);
            $minutesSinceRun = $currentEpoch - $lastStartEpoch;
            $repeatParts = explode(":", $row['repeat_rules']);
            $repeatFields['frequency'] = $repeatParts[0];
            $repeatFields['minute_interval'] = $repeatParts[1];
            $repeatFields['months'] = explode(",", $repeatParts[2]);
            $repeatFields['month_days'] = explode(",", $repeatParts[3]);
            $repeatFields['weekdays'] = explode(",", $repeatParts[4]);
            $repeatFields['hours'] = explode(",", $repeatParts[5]);
            $repeatFields['hour_minute'] = $repeatParts[6];
            $results .= "Minutes Since Run: " . $minutesSinceRun . "\n";

            if ($minutesSinceRun < 0) {
                $runProcess = true;
            } else {
                $runProcess = false;
                switch ($repeatFields['frequency']) {
                    case "MINUTES":
                        $runProcess = ($minutesSinceRun >= $repeatFields['minute_interval']);
                        break;
                    case "HOURLY":
                        $runProcess = (($minutesSinceRun > 10 && date("i") == $repeatFields['hour_minute']) || (date("i") > $repeatFields['hour_minute'] && $minutesSinceRun > 50));
                        break;
                    case "DAILY":
                        $runProcess = ($minutesSinceRun > (25 * 60)) || (in_array(date("G"), $repeatFields['hours']) && (($minutesSinceRun > 10 && date("i") == $repeatFields['hour_minute']) || (date("i") > $repeatFields['hour_minute'] && $minutesSinceRun > 50)));
                        break;
                    case "WEEKLY":
                        $runProcess = ($minutesSinceRun > (25 * 60 * 7)) || (in_array(date("w"), $repeatFields['weekdays']) && in_array(date("G"), $repeatFields['hours']) && (($minutesSinceRun > 10 && date("i") == $repeatFields['hour_minute']) || (date("i") > $repeatFields['hour_minute'] && $minutesSinceRun > 50)));
                        break;
                    case "MONTHLY":
                        $runProcess = ($minutesSinceRun > (25 * 60 * 31)) || ((in_array(date("j"), $repeatFields['month_days']) || (date("j") == date("t") && in_array(31, $repeatFields['month_days']))) && in_array(date("n"), $repeatFields['months']) &&
                                in_array(date("G"), $repeatFields['hours']) && (($minutesSinceRun > 10 && date("i") == $repeatFields['hour_minute']) || (date("i") > $repeatFields['hour_minute'] && $minutesSinceRun > 50)));
                        break;
                }
            }
        }

        if ($runProcess) {
            $scriptFilename = $row['script_filename'];
            if (file_exists($GLOBALS['gDocumentRoot'] . "/background/" . $scriptFilename)) {
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    $shellScript = "START /B C:\php\php.exe " . $GLOBALS['gDocumentRoot'] . "/background/" . $scriptFilename;
                } else {
                    $shellScript = "/usr/local/bin/" . $GLOBALS['gCommandLineDatabaseName'] . "backgroundprocess " . $GLOBALS['gDocumentRoot'] . "/background/" . $scriptFilename . ">>/var/log/background.log 2>&1 &";
                }
                $shellResult = shell_exec($shellScript);
                $results .= "Background Process '" . $row['description'] . "' started with script '" . $GLOBALS['gDocumentRoot'] . "/background/" . $scriptFilename . "'" .
                    (empty($shellResult) ? "" : " with result '" . $shellResult . "'") . "\n";
                $allBackgroundProcesses[$row['background_process_code']]['pid'] = true;
            } else {
                $results .= "Script file does not exist in background directory: " . $GLOBALS['gDocumentRoot'] . "/background/" . $scriptFilename . "\n";
            }
        }
    }
    freeResult($resultSet);
    if (!empty($results)) {
        executeQuery("insert into background_process_log (results) values (?)", $results);
    }
    if (!$GLOBALS['gCommandLine']) {
        break;
    }
    $minute = intval(date("i"));
    if ($minute % 10 == 8) {
        break;
    } else {
        sleep(60);
    }
}
