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

$GLOBALS['gPageCode'] = "PDFRECEIPTMAINT";
require_once "shared/startup.inc";

class PdfReceiptMaintenancePage extends Page {

	function setup() {
		if (method_exists($this->iTemplateObject,"getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("save","add"));
			$this->iTemplateObject->getTableEditorObject()->addIncludeColumn(array("description","fragment_id","user_id","time_submitted","time_finished","total_receipts","created_count"));
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("description","readonly","true");
		$this->iDataSource->addColumnControl("fragment_id","readonly","true");
		$this->iDataSource->addColumnControl("user_id","readonly","true");
		$this->iDataSource->addColumnControl("time_submitted","readonly","true");
		$this->iDataSource->addColumnControl("time_finished","readonly","true");
		$this->iDataSource->addColumnControl("total_receipts","data_type","int");
		$this->iDataSource->addColumnControl("total_receipts","form_label","Total Receipts");
		$this->iDataSource->addColumnControl("total_receipts","readonly","true");
		$this->iDataSource->addColumnControl("created_count","data_type","int");
		$this->iDataSource->addColumnControl("created_count","form_label","Receipts Already Created");
		$this->iDataSource->addColumnControl("created_count","readonly","true");
	}

	function afterGetRecord(&$returnArray) {
		$totalReceipts = 0;
		$resultSet = executeQuery("select count(*) from pdf_receipt_entries where pdf_receipt_batch_id = ?",$returnArray['primary_id']['data_value']);
		if ($row = getNextRow($resultSet)) {
			$totalReceipts = $row['count(*)'];
		}
		$returnArray['total_receipts'] = array("data_value"=>$totalReceipts);
		$createdCount = 0;
		$resultSet = executeQuery("select count(*) from pdf_receipt_entries where pdf_receipt_batch_id = ? and contact_id in (select contact_id from contact_files where pdf_receipt_batch_id = ?)",
			$returnArray['primary_id']['data_value'],$returnArray['primary_id']['data_value']);
		if ($row = getNextRow($resultSet)) {
			$createdCount = $row['count(*)'];
		}
		$returnArray['created_count'] = array("data_value"=>$createdCount);
	}

	function deleteRecord() {
	    $returnArray = array();
		$this->iDatabase->startTransaction();
		$fileIds = array();
		$resultSet = executeQuery("select file_id from contact_files where pdf_receipt_batch_id = ?",$_POST['primary_id']);
		while ($row = getNextRow($resultSet)) {
			$fileIds[] = $row['file_id'];
		}
		$resultSet = executeQuery("delete from pdf_receipt_entries where pdf_receipt_batch_id = ?",$_POST['primary_id']);
		if (!empty($resultSet['sql_error'])) {
			$returnArray['error_message'] = getSystemMessage("basic",$resultSet['sql_error']);
			$this->iDatabase->rollbackTransaction();
			ajaxResponse($returnArray);
		}
		$resultSet = executeQuery("delete from contact_files where pdf_receipt_batch_id = ?",$_POST['primary_id']);
		if (!empty($resultSet['sql_error'])) {
			$returnArray['error_message'] = getSystemMessage("basic",$resultSet['sql_error']);
			$this->iDatabase->rollbackTransaction();
			ajaxResponse($returnArray);
		}
		if (!empty($fileIds)) {
			$resultSet = executeQuery("delete from download_log where file_id in (" . implode(",",$fileIds) . ")");
			if (!empty($resultSet['sql_error'])) {
				$returnArray['error_message'] = getSystemMessage("basic",$resultSet['sql_error']);
				$this->iDatabase->rollbackTransaction();
				ajaxResponse($returnArray);
			}
			$resultSet = executeQuery("delete ignore from files where file_id in (" . implode(",",$fileIds) . ")");
			if (!empty($resultSet['sql_error'])) {
				$returnArray['error_message'] = getSystemMessage("basic",$resultSet['sql_error']);
				$this->iDatabase->rollbackTransaction();
				ajaxResponse($returnArray);
			}
		}
		$this->iDatabase->commitTransaction();
		ajaxResponse($returnArray);
	}

}

$pageObject = new PdfReceiptMaintenancePage("pdf_receipt_batches");
$pageObject->displayPage();
