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
require_once __DIR__ ."/../shared/startup.inc";

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}
if($GLOBALS['gClientId'] != $GLOBALS['gDefaultClientId']) {
    echo "This process must be run on the primary client.";
    exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "update_core_data";
	}

	function process() {
        $filename = "{$GLOBALS['gDocumentRoot']}/cache/pagecodes.txt";
		if ($GLOBALS['gSystemName'] == "COREWARE" && gethostname() == "manage.coreware.com") {
            $beforeHash = hash_file("md5", $filename);
            $results = Database::updateCorePages(["action"=>"export", "file_name"=>$filename]);
            $this->parseResults($results);
            $afterHash = hash_file("md5", $filename);
            if($beforeHash != $afterHash) {
                $webhookResults = sendDeploymentWebhook(["ref"=>"update_core_data", "before"=>$beforeHash, "after"=>$afterHash]);
                $this->addResult("Core data changed; webhook sent to linked servers\n$webhookResults");
            } else {
                $this->addResult("Core data unchanged");
            }

            return;
		}
        $postParameters = array("connection_key" => "D6F353A907A41A56DBFF81448166A137");
        $response = getCurlReturn("https://manage.coreware.com/api.php?action=get_core_data_hash", $postParameters);
        $responseArray = json_decode($response, true);
        if(!is_array($responseArray) || !empty($responseArray['error_message'])) {
            $this->addResult("Unable to get core data hash: {$responseArray['error_message']}");
            return;
        }
        $receivedHash = $responseArray['core_data_hash'];
        $existingHash = hash_file("md5", $filename);
        if($existingHash == $receivedHash) {
            $this->addResult("Core data is up to date");
            return;
        }
		$response = getCurlReturn("https://manage.coreware.com/api.php?action=get_core_data", $postParameters);
        if(strlen($response) < 10000) {
            $this->addResult("Unable to get core data");
            return;
        }
        file_put_contents($filename, $response);

        $results = Database::updateCorePages(["action"=>"update", "limited"=>true, "file_name"=>"$filename"]);

        $this->parseResults($results);
	}

    function parseResults($results) {
        $resultFound = false;
        if (is_array($results) && is_array($results['errors'])) {
            foreach ($results['errors'] as $thisError) {
                $this->addResult("ERROR: " . $thisError);
                $resultFound = true;
            }
        }
        if (is_array($results) && is_array($results['output'])) {
            foreach ($results['output'] as $thisLine) {
                $this->addResult($thisLine);
                $resultFound = true;
            }
        }
        if(!$resultFound) {
            $this->addResult("No results found: " . jsonEncode($results));
        }
    }
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
