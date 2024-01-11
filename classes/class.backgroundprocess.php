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

/**
 * abstract class BackgroundProcess
 *
 * Abstract implementation of a background process.
 *
 * @author Kim D Geiger
 */
abstract class BackgroundProcess {

    protected $iResults = "";
    protected $iProcessCode = "";
    protected $iErrorsFound = false;
    protected $iInteractive = false;
    protected $iBackgroundProcessRow = false;
	protected $iLastStartTime = false;

    function startProcess() {
# don't log for background processes
        $GLOBALS['gAllowLongRun'] = true;

        $this->setProcessCode();
        $startTime = getMilliseconds();
        try {
            $this->iBackgroundProcessRow = getRowFromId("background_processes","background_process_code",$this->iProcessCode);
			$this->iLastStartTime = $this->iBackgroundProcessRow['last_start_time'];
            $updateSet = executeQuery("update background_processes set last_start_time = ?,run_immediately = 0 where background_process_id = ?",date("Y-m-d H:i:s"),$this->iBackgroundProcessRow['background_process_id']);
            freeResult($updateSet);
            $this->process();
        } catch (Exception $e) {
            $GLOBALS['gPrimaryDatabase']->logError($e->getMessage());
        }
        $backgroundProcessId = getFieldFromId("background_process_id", "background_processes", "background_process_code", strtoupper($this->iProcessCode));
        if (empty($backgroundProcessId)) {
            $this->iResults = "Invalid background process code: " . $this->iProcessCode . "\n\n" . $this->iResults;
            return;
        }
        $sendEmail = false;
        if (!empty($this->iResults)) {
            $sendEmail = true;
        }
        $endTime = getMilliseconds();
        $totalSeconds = round(($endTime - $startTime) / 1000, 1);
	    $this->addResult("Total Time: " . getTimeElapsed($startTime, $endTime));
	    $this->addResult("Process memory use: " . number_format(memory_get_peak_usage(), 0, "", ","));

        executeQuery("insert into background_process_log (background_process_id,results,elapsed_time) values (?,?,?)",
            $backgroundProcessId, $this->iResults, $totalSeconds);
        $emailAddresses = array();
        $resultSet = executeQuery("select * from background_process_notifications where background_process_id = ?", $backgroundProcessId);
        while ($row = getNextRow($resultSet)) {
        	if (empty($row['error_only']) || $this->iErrorsFound) {
		        $emailAddresses[] = $row['email_address'];
	        }
        }
        if (!empty($emailAddresses) && $sendEmail) {
            sendEmail(array("subject" => "Background process " . getFieldFromId("description", "background_processes", "background_process_id", $backgroundProcessId) . " from " . $GLOBALS['gSystemName'],
                "body" => str_replace("\n", "<br>", $this->iResults), "email_addresses" => $emailAddresses, "no_copy"=>true));
        }
        if (!$GLOBALS['gCommandLine']) {
            echo "<p>Process '" . $this->iProcessCode . "' completed.</p>";
            echo "<p>" . str_replace("\n", "<br>", $this->iResults) . "</p>";
        }
    }

    function addResult($resultLine = "") {
	    if (empty($resultLine)) {
		    return;
	    }
		if (getPreference("LOG_BACKGROUND_PROGRESS") == $this->iBackgroundProcessRow['background_process_code']) {
			addDebugLog($this->iBackgroundProcessRow['background_process_code'] . " - " . $resultLine,true);
		}
    	if (is_array($resultLine)) {
    		$resultLine = jsonEncode($resultLine);
	    }
        $this->iResults .= date("m/d/Y H:i:s") . " - " . $resultLine . "\n";
        if ($this->iInteractive) {
        	echo str_replace("\n",$GLOBALS['gLineEnding'],$resultLine) . $GLOBALS['gLineEnding'];
        }
    }

    /**
     *
     * Simple function to set the class variable of the process code. This is necessary for the process to create a log
     *
     */

    abstract function setProcessCode();

    /**
     *
     * This function is the guts of the background process. It does the real work of the process. Any results or information from the
     * process should be added to the class variable iResults.
     *
     */

    abstract function process();

}
