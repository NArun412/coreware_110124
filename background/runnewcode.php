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
require_once __DIR__ . "/../shared/startup.inc";

if (empty($GLOBALS['gCommandLine'])) {
    echo "ERROR: Because of system permissions, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
    exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
    function setProcessCode() {
        $this->iProcessCode = "run_newcode";
    }

    private function runNewcode() {
        $documentRoot = $GLOBALS['gDocumentRoot'];
        $scriptName = "/usr/local/bin/newcode";
        $script = <<< EOT
#!/bin/bash

user=`whoami`

if [ \"\$user\" == \"root\" ]; then
    sudo -u ec2-user $scriptName
fi

if [ \"\$user\" == \"ec2-user\" ]; then
    echo User: `whoami`
    cd $documentRoot
    echo Directory: `pwd`
    # prevent ssh-agent from running multiple times
    export SSH_AUTH_SOCK=~/.ssh/ssh-agent.sock
    ssh-add -l 2>/dev/null >/dev/null
    [ $? -ge 2 ] && ssh-agent -a "\$SSH_AUTH_SOCK" >/dev/null
    ssh-add ~/.ssh/deploy_key
    git fetch
    git stash
    git pull
    git status -uno
    touch index.php
    chmod 700 .git
    cp -n htaccess.txt .htaccess
fi
EOT;
        file_put_contents($scriptName, $script);
        shell_exec("chmod +x $scriptName");
        return shell_exec("$scriptName 2>&1");
    }
    function process() {
        $branchRef = getBranchRef();
        if(empty($branchRef)) {
            $this->addResult("Unable to retrieve branch name.  Check that git is configured correctly.");
            $this->iErrorsFound = true;
            return;
        }
        $result = $this->runNewcode();
        if(stristr($result,"fatal") !== false) {
            $this->addResult("Running newcode from background process failed\n$result");
            $this->iErrorsFound = true;
        } else {
            $this->addResult("Newcode run by background process\n$result");
        }
        // Make sure newcode does not run unintentionally
        executeQuery("update background_processes set inactive = 1 where background_process_code = 'run_newcode'");
    }
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
