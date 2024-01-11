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
		$this->iProcessCode = "cross_client_check";
	}

	function process() {

		$resultSet = executeQuery("select client_id from clients");
		if ($resultSet['row_count'] == 1) {
			return;
		}

		$defaultClientTables = array("pages","templates");
		$ignoreCombos = array();
		$ignoreCombos['clients'] = "contact_id";
        $ignoreCombos['help_desk_types'] = "email_credential_id";
        $ignoreCombos['product_departments'] = "fragment_id";
        $ignoreCombos['product_category_groups'] = "fragment_id";

		$superuserContactIds = array();
		$superuserUserIds = array();
		$resultSet = executeQuery("select user_id,contact_id from users where superuser_flag = 1");
		while ($row = getNextRow($resultSet)) {
			$superuserUserIds[] = $row['user_id'];
			$superuserContactIds[] = $row['contact_id'];
		}

		$primaryKeys = array();
		$tableKeys = array();
		$resultSet = executeQuery("select * from table_columns join tables using (table_id) join column_definitions using (column_definition_id) where primary_table_key = 1");
		while ($row = getNextRow($resultSet)) {
			$primaryKeys[$row['column_name']] = $row['table_name'];
			$tableKeys[$row['table_name']] = $row['column_name'];
		}

		$tableArray = array();
		$tableSet = executeQuery("select * from table_columns join tables using (table_id) where primary_table_key = 0 and table_column_id in (select table_column_id from foreign_keys) order by table_name,sequence_number");
		while ($tableRow = getNextRow($tableSet)) {
			$columnDefinitionRow = getRowFromId("column_definitions","column_definition_id",$tableRow['column_definition_id']);
			if (!array_key_exists($tableRow['table_name'],$tableArray)) {
				$tableArray[$tableRow['table_name']] = array();
				$dataTable = new DataTable($tableRow['table_name']);
				$tableArray[$tableRow['table_name']]['has_client_id'] = $dataTable->columnExists("client_id");
				$tableArray[$tableRow['table_name']]['foreign_keys'] = array();
			}
			$referencedTableColumnId = getFieldFromId("referenced_table_column_id","foreign_keys","table_column_id",$tableRow['table_column_id']);
			$referencedTableName = getFieldFromId("table_name","tables","table_id",getFieldFromId("table_id","table_columns","table_column_id",$referencedTableColumnId));
			$tableArray[$tableRow['table_name']]['foreign_keys'][] = array("column_name"=>$columnDefinitionRow['column_name'],"referenced_table_name"=>$referencedTableName);
		}

		foreach ($tableArray as $tableName => $thisTable) {
			if (empty($thisTable['foreign_keys'])) {
				continue;
			}
			$columnList = $tableKeys[$tableName];
			if (empty($columnList)) {
				$this->addResult($tableName . " - Key not found");
				continue;
			}
			foreach ($thisTable['foreign_keys'] as $thisKey) {
				$columnList .= "," . $thisKey['column_name'];
			}
			$resultSet = executeQuery("select " . $columnList . " from " . $tableName);
			while ($row = getNextRow($resultSet)) {
				if ($tableName == "contacts" && in_array($row['contact_id'],$superuserContactIds)) {
					continue;
				}
				if ($tableName == "users" && in_array($row['user_id'],$superuserUserIds)) {
					continue;
				}
				if ($thisTable['has_client_id']) {
					foreach ($thisTable['foreign_keys'] as $foreignKeyInfo) {
						if (array_key_exists($tableName,$ignoreCombos) && $ignoreCombos[$tableName] == $foreignKeyInfo['column_name']) {
							continue;
						}
						if ($foreignKeyInfo['referenced_table_name'] == "users" && in_array($row[$foreignKeyInfo['column_name']],$superuserUserIds)) {
							continue;
						}
						if ($foreignKeyInfo['referenced_table_name'] == "contacts" && in_array($row[$foreignKeyInfo['column_name']],$superuserContactIds)) {
							continue;
						}
						if (empty($row[$foreignKeyInfo['column_name']])) {
							continue;
						}
						if ($tableArray[$foreignKeyInfo['referenced_table_name']]['has_client_id']) {
							$foreignRow = getRowFromId($foreignKeyInfo['referenced_table_name'],$tableKeys[$foreignKeyInfo['referenced_table_name']],$row[$foreignKeyInfo['column_name']],
								"client_id <> ?" . (in_array($foreignKeyInfo['referenced_table_name'],$defaultClientTables) ? " and client_id <> " . $GLOBALS['gDefaultClientId'] : ""),$row['client_id']);
							if (!empty($foreignRow)) {
								$this->addResult("Record ID " . $row[$tableKeys[$tableName]] . " from table " . $tableName . " references record in " . $foreignKeyInfo['referenced_table_name'] . " in different client");
							}
						}
					}
				} else {
					$clientIds = array();
					foreach ($thisTable['foreign_keys'] as $foreignKeyInfo) {
						if (array_key_exists($tableName,$ignoreCombos) && $ignoreCombos[$tableName] == $foreignKeyInfo['column_name']) {
							continue;
						}
						if ($foreignKeyInfo['referenced_table_name'] == "users" && in_array($row[$foreignKeyInfo['column_name']],$superuserUserIds)) {
							continue;
						}
						if ($foreignKeyInfo['referenced_table_name'] == "contacts" && in_array($row[$foreignKeyInfo['column_name']],$superuserContactIds)) {
							continue;
						}
						if (!$tableArray[$foreignKeyInfo['referenced_table_name']]['has_client_id']) {
							continue;
						}
						if (empty($row[$foreignKeyInfo['column_name']])) {
							continue;
						}
						$thisClientId = getFieldFromId("client_id",$foreignKeyInfo['referenced_table_name'],$tableKeys[$foreignKeyInfo['referenced_table_name']],$row[$foreignKeyInfo['column_name']],"client_id > 0");
						if ($thisClientId == $GLOBALS['gDefaultClientId'] && in_array($foreignKeyInfo['referenced_table_name'],$defaultClientTables)) {
							continue;
						}
						$clientIds[] = array("column_name"=>$foreignKeyInfo['column_name'],"table_name"=>$foreignKeyInfo['referenced_table_name'],"client_id"=>$thisClientId);
					}
					$saveClientInfo = false;
					foreach ($clientIds as $thisClientInfo) {
						if (empty($saveClientInfo)) {
							$saveClientInfo = $thisClientInfo;
							continue;
						}
						if ($thisClientInfo['client_id'] != $saveClientInfo['client_id']) {
							$this->addResult("Record ID " . $row[$tableKeys[$tableName]] . " from table " . $tableName . ", column " . $thisClientInfo['table_name'] .
								"." . $thisClientInfo['column_name'] . " (" . $thisClientInfo['client_id'] . ") and column " . $saveClientInfo['table_name'] . "." . $saveClientInfo['column_name'] .
								"(" . $saveClientInfo['client_id'] . ") reference data from different clients");
						}
						$saveClientInfo = $thisClientInfo;
					}
				}
			}
		}
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
