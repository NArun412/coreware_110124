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
		$this->iProcessCode = "generate_pdf_receipts";
	}

	function process() {
		$resultSet = executeQuery("select * from pdf_receipt_batches where time_finished is null order by client_id");
		$this->addResult($resultSet['row_count'] . " PDF Receipt Batches found");
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			$entrySet = executeQuery("select * from pdf_receipt_entries where pdf_receipt_batch_id = ? and contact_id not in (select contact_id from contact_files where pdf_receipt_batch_id = ?)",
				$row['pdf_receipt_batch_id'], $row['pdf_receipt_batch_id']);
			$this->addResult($entrySet['row_count'] . " entries found");
			$fileCount = 0;
			$errorCount = 0;
			while ($entryRow = getNextRow($entrySet)) {
				$htmlContent = getFieldFromId("content", "fragments", "fragment_id", $row['fragment_id']);
				$substitutions = json_decode($entryRow['parameters'], true);
				$htmlContent = PlaceHolders::massageContent($htmlContent, $substitutions);
				$fileId = outputPDF($htmlContent, array("create_file" => true, "filename" => "receipt.pdf", "description" => $row['description']));
				if ($fileId) {
					$insertSet = executeQuery("insert into contact_files (contact_id,description,file_id,pdf_receipt_batch_id) values (?,?,?,?)",
						$entryRow['contact_id'], $row['description'], $fileId, $row['pdf_receipt_batch_id']);
					if (empty($insertSet['sql_error'])) {
						$fileCount++;
					}
				} else {
					$errorCount++;
				}
			}
			$countSet = executeQuery("select count(*) from pdf_receipt_entries where pdf_receipt_batch_id = ? and contact_id not in (select contact_id from contact_files where pdf_receipt_batch_id = ?)",
				$row['pdf_receipt_batch_id'], $row['pdf_receipt_batch_id']);
			if ($countRow = getNextRow($countSet)) {
				if ($countRow['count(*)'] == 0) {
					executeQuery("update pdf_receipt_batches set time_finished = now() where pdf_receipt_batch_id = ?", $row['pdf_receipt_batch_id']);
					sendEmail(array("body" => "<p>PDF Receipt batch '" . $row['description'] . "' is completed.</p>", "subject" => "PDF Receipt Batch",
						"email_address" => Contact::getUserContactField($row['user_id'], "email_address")));
				}
			}
			$this->addResult($fileCount . " PDF receipts created for receipt batch created on " . date("m/d/Y g:ia", strtotime($row['time_submitted'])) . " by " . getUserDisplayName($row['user_id']));
			if ($errorCount > 0) {
				$this->addResult($errorCount . " errors encountered when creating PDF receipts");
				$this->iErrorsFound = true;
			}
		}
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
