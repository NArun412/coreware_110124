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

$GLOBALS['gPageCode'] = "EXPORTCLIENTDATA";
require_once "shared/startup.inc";

class ExportClientDataPage extends Page {

	var $iBlobColumns = array();
	var $iClientColumns = array();

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "export_data":
				header("Content-Type: application/octet-stream");
				header("Content-Disposition: attachment; filename=\"export.txt\"");
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');

				$resultSet = executeQuery("select * from column_definitions where column_type like '%blob%'");
				while ($row = getNextRow($resultSet)) {
					$this->iBlobColumns[] = $row['column_name'];
				}

				$clientColumnId = getFieldFromId("column_definition_id", "column_definitions", "column_name", "client_id");
				$referencesClientId = getFieldFromId("table_column_id", "table_columns", "column_definition_id", $clientColumnId, "table_id = (select table_id from tables where table_name = 'clients')");
				$resultSet = executeQuery("select * from table_columns join tables using (table_id) join column_definitions using (column_definition_id) " .
					"where table_column_id in (select table_column_id from foreign_keys where referenced_table_column_id = ?)",
					$referencesClientId);
				while ($row = getNextRow($resultSet)) {
					$columnDefinitionId = getFieldFromId("column_definition_id", "table_columns", "table_id", $row['table_id'],
						"primary_table_key = 1");
					$columnName = getFieldFromId("column_name", "column_definitions", "column_definition_id", $columnDefinitionId);
					$this->iClientColumns[$row['table_id']] = array("table_name" => $row['table_name'], "column_name" => $row['column_name'],
						"column_definition_id" => $row['column_definition_id'], "table_id" => $row['column_definition_id'],
						"primary_key" => $columnName, "primary_key_id" => $columnDefinitionId);
				}
				$resultSet = executeQuery("select * from tables order by table_name");
				while ($row = getNextRow($resultSet)) {
					$tableName = $row['table_name'];
					$firstRow = true;
					if (!array_key_exists($row['table_id'], $this->iClientColumns)) {
						$whereStatement = "";
						foreach ($this->iClientColumns as $columnInfo) {
							if ($columnInfo['table_name'] == $tableName) {
								continue;
							}
							$clientTableColumnId = getFieldFromId("table_column_id", "table_columns", "table_id", $row['table_id'],
								"column_definition_id = ?", $columnInfo['primary_key_id']);
							if (!empty($clientTableColumnId)) {
								$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(" . $columnInfo['primary_key'] .
									" is null or " . $columnInfo['primary_key'] . " in (select " .
									$columnInfo['primary_key'] . " from " . $columnInfo['table_name'] .
									" where client_id = " . $GLOBALS['gClientId'] . "))";
							}
						}
					} else {
						$whereStatement = $this->iClientColumns[$row['table_id']]['column_name'] . " = " . $GLOBALS['gClientId'];
					}
					$dumpSet = executeQuery("select * from " . $tableName . (empty($whereStatement) ? "" : " where " . $whereStatement));
					if ($dumpSet['row_count'] > 0) {
						echo "Export for " . $tableName . "\n";
						while ($dumpRow = getNextRow($dumpSet)) {
							if ($firstRow) {
								$firstRow = false;
								echo $this->arrayToCsv(array_keys($dumpRow)) . "\n";
							}
							echo $this->arrayToCsv($dumpRow) . "\n";
						}
						echo "--------------------------------------------------------------------------\n";
					}
				}

				exit;
		}
	}

	function arrayToCsv($array) {
		$csvString = "";
		foreach ($array as $fieldName => $value) {
			$csvString .= (empty($csvString) ? "" : ",") . '"' . (in_array($fieldName, $this->iBlobColumns, true) ? $fieldName . ":" . base64_encode($value) : str_replace('"', '""', $value)) . '"';
		}
		return $csvString;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#export_data").click(function () {
                document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_action=export_data";
            });
        </script>
		<?php
	}

	function mainContent() {
		?>
        <p class="align-center">
            <button id="export_data">Export Client Data</button>
        </p>
		<?php
		return true;
	}
}

$pageObject = new ExportClientDataPage();
$pageObject->displayPage();
