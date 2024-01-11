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
		$this->iProcessCode = "database_alert";
	}

	function process() {
        $resultSet = executeQuery("SELECT * FROM information_schema.processlist ORDER BY id");
        $activeProcessArray = array();
        $totalCount = $resultSet['row_count'];
        while($row = getNextRow($resultSet)) {
            if($row['COMMAND'] != 'Sleep') {
                $activeProcessArray[] = $row;
            }
        }
        $activeCount = count($activeProcessArray);
        $threshold = getPreference("DATABASE_ALERT_THRESHOLD");
        $threshold = (is_numeric($threshold) ? $threshold : 20);
        $totalThreshold = getPreference("DATABASE_ALERT_TOTAL_THRESHOLD");
        $totalThreshold = (is_numeric($totalThreshold) ? $totalThreshold : 500);
        if($activeCount > $threshold || $totalCount > $totalThreshold) {
            $body = sprintf("<p>Total database processes (including sleeping): %s</p><p>Active database processes: %s</p><pre>", $totalCount, $activeCount);
            $logEntry = sprintf("Total database processes (including sleeping): %s\nActive database processes: %s\n", $totalCount, $activeCount);
            $formattedResults = $this->formatResults($activeProcessArray);
            foreach($formattedResults as $line) {
                $body .= $line . "<br>";
                $logEntry .= $line . "\n";
            }
            $body .= "</pre>";
            sendEmail(array("email_address"=>"servers@coreware.com", "send_immediately"=>true, "subject"=>"Database connections exceed threshold", "body"=>$body));
            $logEntry .= "Count above threshold.  Alert sent.";
            $this->addResult($logEntry);
        } else {
            $this->addResult(sprintf("Total database processes (including sleeping): %s\nActive database processes: %s\n", $totalCount, $activeCount));
        }
	}

    function formatResults($inputArray) {
        $fieldValues = array();
        $headers = array();
        foreach($inputArray as $row) {
            $thisRow = array();
            $headerIndex = 0;
            foreach ($row as $fieldName => $fieldData) {
                if (is_numeric($fieldName)) {
                    continue;
                }
                if(strlen($fieldData) > 500) {
                    $fieldData = substr($fieldData,0,497) . "...";
                }
                $thisRow[] = $fieldData;
                if (empty($fieldValues)) {
                    $headers[] = array("label" => $fieldName, "length" => max(strlen($fieldName), strlen($fieldData)));
                } else {
                    $headers[$headerIndex]['length'] = max($headers[$headerIndex]['length'], strlen($fieldData));
                }
                $headerIndex++;
            }
            $fieldValues[] = $thisRow;
            if(count($fieldValues) >= 1000) {
                break;
            }
        }
        if (!empty($headers)) {
            $rowlength = 1;
            foreach ($headers as $thisHeader) {
                $thisLine = " " . str_pad($thisHeader['label'], $thisHeader['length'], " ") . " |";
                $rowlength += strlen($thisLine);
            }
            $separatorLine = str_repeat("-", $rowlength);
            $formattedResults[] = $separatorLine;
            $thisResult = "|";
            foreach ($headers as $thisHeader) {
                $thisLine = " " . str_pad($thisHeader['label'], $thisHeader['length'], " ") . " |";
                $thisResult .= $thisLine;
            }
            $formattedResults[] = $thisResult;
            $formattedResults[] = $separatorLine;
            foreach ($fieldValues as $thisRow) {
                $thisResult = "|";
                foreach ($thisRow as $index => $fieldValue) {
                    $thisResult .= " " . str_pad($fieldValue, $headers[$index]['length'], " ") . " |";
                }
                $formattedResults[] = $thisResult;
            }
            $formattedResults[] = $separatorLine;
        }
        if (count($fieldValues) < count($inputArray)) {
            $formattedResults[] = count($fieldValues) . " rows of " . count($inputArray) . " displayed";
        } else {
            $formattedResults[] = count($fieldValues) . " row" . (count($fieldValues) == 1 ? "" : "s");
        }
        return $formattedResults;
    }
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
