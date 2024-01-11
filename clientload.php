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

$GLOBALS['gPageCode'] = "CLIENTLOAD";
require_once "shared/startup.inc";

# For De Leon Pharmacy

$filename = "clientdump.txt";

# existing domain name
$domainName = "ybtactical.coreware.com";

# connection key for existing client that has access to all downloads and images
$connectionKey = "15C914EC8769028F49D21BBB0042257E";
$convertClientId = "";
$externalFileDirectory = getPreference("EXTERNAL_FILE_DIRECTORY");
if (empty($externalFileDirectory)) {
	$externalFileDirectory = "/documents/";
}
if (strtoupper(substr($externalFileDirectory, 0, 3)) == "S3:") {
	$awsAccessKey = getPreference("AWS_S3_ACCESS_KEY");
	$awsSecretKey = getPreference("AWS_S3_SECRET_KEY");
	$awsRegion = getPreference("AWS_REGION");
	$s3 = new S3($awsAccessKey, $awsSecretKey, false, 's3' . (empty($awsRegion) ? "" : "." . $awsRegion) . '.amazonaws.com', $awsRegion);
	$s3->setSignatureVersion('v4');
} else {
	$s3 = false;
}

$testMode = false;
$errorCount = 0;

if (!$GLOBALS['gCommandLine']) {
	echo "Command Line Only";
	exit;
}

if (!file_exists("/documents/" . $filename)) {
	addDebugLog("File does not exist");
	exit;
}
$handle = fopen("/documents/" . $filename, "r");
if (!$handle) {
	addDebugLog("Unable to open file");
	exit;
}
$rowCount = 0;

$resultSet = executeQuery("select * from information_schema.key_column_usage where constraint_schema = ? and referenced_table_name is not null", $GLOBALS['gPrimaryDatabase']->getName());
while ($row = getNextRow($resultSet)) {
	executeQuery("alter table " . $row['TABLE_NAME'] . " drop foreign key " . $row['CONSTRAINT_NAME']);
}

$translations = array();
$translationTables = array("templates" => "", "preferences" => "preference_code", "subsystems" => "subsystem_code", "countries" => "country_code", "template_data" => "data_name", "form_fields" => "", "fragments" => "",
	"menus" => "", "merchant_accounts" => "", "order_methods" => "", "order_status" => "", "pages" => "", "related_product_types" => "", "menu_items" => "",
	"search_term_parameters" => "", "security_levels" => "", "security_questions" => "security_question", "shipping_carriers" => "", "custom_field_types" => "custom_field_type_code", "visit_types" => "description");
$skipTables = array("hacking_terms", "merchant_service_field_labels", "product_distributor_field_labels", "price_calculation_types", "random_data_chunks", "debug_log", "login_providers");

$superuserContacts = array();
$superuserUsers = array();

$resultSet = executeQuery("select * from users where superuser_flag = 1");
while ($row = getNextRow($resultSet)) {
	$superuserUsers[$row['user_id']] = $row['user_id'];
	$superuserContacts[$row['contact_id']] = $row;
}

$clientContactId = "";
$equivalentFields = array();
$equivalentFields['responsible_user_id'] = "user_id";
$equivalentFields['creator_user_id'] = "user_id";
$equivalentFields['user_page_id'] = "page_id";
$equivalentFields['admin_page_id'] = "page_id";
$saveTableName = "";
$clientName = "";
$newClientId = "";

$clearKeyTables = array();

$primaryKeys = array();
$resultSet = executeQuery("select *,(select table_name from tables where table_id = table_columns.table_id) as table_name," .
	"(select column_name from column_definitions where column_definition_id = table_columns.column_definition_id) as column_name," .
	"(select foreign_key_id from foreign_keys where referenced_table_column_id = table_columns.table_column_id limit 1) as foreign_key_id from table_columns where primary_table_key = 1");
while ($row = getNextRow($resultSet)) {
	$primaryKeys[$row['table_name']] = $row['column_name'];
	if (empty($row['foreign_key_id'])) {
		$clearKeyTables[] = $row['table_name'];
	}
}

$lineNumber = 0;
while (($line = fgets($handle)) !== false) {
	$line = trim($line);
	$lineNumber++;
	$thisData = json_decode($line, true);
	$tableName = $thisData['table_name'];
	if (!$GLOBALS['gPrimaryDatabase']->tableExists($tableName) || in_array($tableName, $skipTables)) {
		continue;
	}
	if ($saveTableName != $tableName) {
		addDebugLog("Loading " . $tableName);
		$saveTableName = $tableName;
	}

	$dumpTag = $thisData['tag'];
	$fieldNames = $thisData['keys'];
	$rows = $thisData['rows'];
	$insertStatement = "";
	$insertParameters = array();
	$clientFields = array("client_id","domain_client_id");

	foreach ($rows as $thisRow) {
		$fieldData = array();
		foreach ($fieldNames as $index => $fieldName) {
			$fieldData[$fieldName] = $thisRow[$index];
			if (in_array($fieldName,$clientFields) && !empty($convertClientId) && $thisRow[$index] > 1) {
				$fieldData[$fieldName] = $convertClientId;
			}
		}
		if (!empty($dumpTag)) {
			switch ($dumpTag) {
				case "table_conversion":
					$tableId = getFieldFromId("table_id", "tables", "table_name", $fieldData['table_name']);
					if ($tableId != $fieldData['table_id']) {
						$translations['table_id'][$fieldData['table_id']] = $tableId;
					}
					break;
				case "client_contact":
					$nameValues = $fieldData;
					$nameValues['contact_id'] = "";
					$resultSet = executeQuery("select max(contact_id) from contacts where contact_id < 10000");
					if ($row = getNextRow($resultSet)) {
						$nameValues['contact_id'] = $row['max(contact_id)'] + 1;
					}
					$insertSet = executeQuery("insert into contacts (" . implode(",", array_keys($nameValues)) . ") values (" . implode(",", array_fill(0, count($nameValues), "?")) . ")", $nameValues);
					if (!empty($insertSet['sql_error'])) {
						addDebugLog("Error: Line " . $lineNumber . ", " . $tableName . ": " . $insertSet['sql_error'] . ":" . $insertSet['query']);
						$errorCount++;
						if (!$testMode) {
							exit;
						}
					}
					$clientName = $fieldData['business_name'];
					$clientContactId = $insertSet['insert_id'];
					break;
				case "client_phone_numbers":
					$nameValues = $fieldData;
					$nameValues['contact_id'] = $clientContactId;
					$insertSet = executeQuery("insert into phone_numbers (" . implode(",", array_keys($nameValues)) . ") values (" . implode(",", array_fill(0, count($nameValues), "?")) . ")", $nameValues);
					if (!empty($insertSet['sql_error'])) {
						addDebugLog("Error: Line " . $lineNumber . ", " . $tableName . ": " . $insertSet['sql_error'] . ":" . $insertSet['query']);
						$errorCount++;
						if (!$testMode) {
							exit;
						}
					}
					break;
				case "client":
					$nameValues = $fieldData;
					$nameValues['contact_id'] = $clientContactId;
					$insertSet = executeQuery("insert into clients (" . implode(",", array_keys($nameValues)) . ") values (" . implode(",", array_fill(0, count($nameValues), "?")) . ")", $nameValues);
					if (!empty($insertSet['sql_error'])) {
						addDebugLog("Error: Line " . $lineNumber . ", " . $tableName . ": " . $insertSet['sql_error'] . ":" . $insertSet['query']);
						$errorCount++;
						if (!$testMode) {
							exit;
						}
					}
					$newClientId = $insertSet['insert_id'];
					break;
				case "superuser_contacts":
					$superuserContacts[$fieldData['contact_id']] = $fieldData;
					break;
				case "superusers":
					if (!array_key_exists("user_id", $translations)) {
						$translations['user_id'] = array();
					}
					if (!array_key_exists("contact_id", $translations)) {
						$translations['contact_id'] = array();
					}
					$keyValueId = getFieldFromId("user_id", "users", "user_name", $fieldData['user_name'], "superuser_flag = 1 and client_id = 1");
					if (empty($keyValueId)) {
						$nameValues = $superuserContacts[$fieldData['contact_id']];
						$nameValues['contact_id'] = "";
						$resultSet = executeQuery("select max(contact_id) from contacts where contact_id < 10000");
						if ($row = getNextRow($resultSet)) {
							$nameValues['contact_id'] = $row['max(contact_id)'] + 1;
						}
						$insertSet = executeQuery("insert into contacts (" . implode(",", array_keys($nameValues)) . ") values (" . implode(",", array_fill(0, count($nameValues), "?")) . ")", $nameValues);
						if (!empty($insertSet['sql_error'])) {
							addDebugLog("Error: Line " . $lineNumber . ", " . $tableName . ": " . $insertSet['sql_error'] . ":" . $insertSet['query']);
							$errorCount++;
							if (!$testMode) {
								exit;
							}
						}
						$contactId = $insertSet['insert_id'];
						$superuserContacts[$contactId] = $nameValues;
						$nameValues = $fieldData;
						$nameValues['contact_id'] = $contactId;
						$nameValues['user_id'] = "";
						$resultSet = executeQuery("select max(user_id) from users where user_id < 10000");
						if ($row = getNextRow($resultSet)) {
							$nameValues['user_id'] = $row['max(user_id)'] + 1;
						}
						$insertSet = executeQuery("insert into users (" . implode(",", array_keys($nameValues)) . ") values (" . implode(",", array_fill(0, count($nameValues), "?")) . ")", $nameValues);
						if (!empty($insertSet['sql_error'])) {
							addDebugLog("Error: Line " . $lineNumber . ", " . $tableName . ": " . $insertSet['sql_error'] . ":" . $insertSet['query']);
							$errorCount++;
							if (!$testMode) {
								exit;
							}
						}
						$keyValueId = $insertSet['insert_id'];
						$superuserUsers[$keyValueId] = $contactId;
					} else {
						$contactId = Contact::getUserContactId($keyValueId);
						$superuserUsers[$keyValueId] = $contactId;
					}
					if ($keyValueId != $fieldData['user_id']) {
						$translations['user_id'][$fieldData['user_id']] = $keyValueId;
					}
					if ($contactId != $fieldData['contact_id']) {
						$translations['contact_id'][$fieldData['contact_id']] = $contactId;
					}
					executeQuery("update users set client_id = 1");
					executeQuery("update contacts set client_id = 1");
					break;
				case "superuser_addresses":
					if (!array_key_exists("address_id", $translations)) {
						$translations['address_id'] = array();
					}

					$nameValues = $fieldData;
					if (array_key_exists($fieldData['contact_id'], $translations['contact_id'])) {
						$nameValues['contact_id'] = $translations['contact_id'][$fieldData['contact_id']];
					}
					$insertSet = executeQuery("insert into " . $tableName . " (" . implode(",", array_keys($nameValues)) . ") values (" . implode(",", array_fill(0, count($nameValues), "?")) . ")", $nameValues);
					if (!empty($insertSet['sql_error'])) {
						addDebugLog("Error: Line " . $lineNumber . ", " . $tableName . ": " . $insertSet['sql_error'] . ":" . $insertSet['query']);
						$errorCount++;
						if (!$testMode) {
							exit;
						}
					}
					$keyValueId = $insertSet['insert_id'];

					if ($keyValueId != $fieldData['address_id']) {
						$translations['address_id'][$fieldData['address_id']] = $keyValueId;
					}
					break;
				case "superuser_accounts":
					if (!array_key_exists("account_id", $translations)) {
						$translations['account_id'] = array();
					}

					$nameValues = $fieldData;
					if (array_key_exists($fieldData['merchant_account_id'], $translations['merchant_account_id'])) {
						$nameValues['merchant_account_id'] = $translations['merchant_account_id'][$fieldData['merchant_account_id']];
					}
					if (array_key_exists($fieldData['contact_id'], $translations['contact_id'])) {
						$nameValues['contact_id'] = $translations['contact_id'][$fieldData['contact_id']];
					}
					$insertSet = executeQuery("insert into " . $tableName . " (" . implode(",", array_keys($nameValues)) . ") values (" . implode(",", array_fill(0, count($nameValues), "?")) . ")", $nameValues);
					if (!empty($insertSet['sql_error'])) {
						addDebugLog("Error: Line " . $lineNumber . ", " . $tableName . ": " . $insertSet['sql_error'] . ":" . $insertSet['query']);
						$errorCount++;
						if (!$testMode) {
							exit;
						}
					}
					$keyValueId = $insertSet['insert_id'];

					if ($keyValueId != $fieldData['account_id']) {
						$translations['account_id'][$fieldData['account_id']] = $keyValueId;
					}
					break;
				case "superuser_phone_numbers":
					$nameValues = $fieldData;
					if (array_key_exists($fieldData['contact_id'], $translations['contact_id'])) {
						$nameValues['contact_id'] = $translations['contact_id'][$fieldData['contact_id']];
					}
					$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "contact_id", $nameValues['contact_id'], "phone_number = ?", $nameValues['phone_number']);
					if (empty($phoneNumberId)) {
						$insertSet = executeQuery("insert into " . $tableName . " (" . implode(",", array_keys($nameValues)) . ") values (" . implode(",", array_fill(0, count($nameValues), "?")) . ")", $nameValues);
						if (!empty($insertSet['sql_error'])) {
							addDebugLog("Error: Line " . $lineNumber . ", " . $tableName . ": " . $insertSet['sql_error'] . ":" . $insertSet['query']);
							$errorCount++;
							if (!$testMode) {
								exit;
							}
						}
					}
					break;
				case "core_templates":
					if (!array_key_exists("template_id", $translations)) {
						$translations['template_id'] = array();
					}
					$keyValueId = getFieldFromId("template_id", "templates", "template_code", $fieldData['template_code'], "client_id = " . $GLOBALS['gDefaultClientId']);
					if (empty($keyValueId)) {
						$nameValues = $fieldData;
						$nameValues['template_id'] = "";
						$insertSet = executeQuery("insert into " . $tableName . " (" . implode(",", array_keys($nameValues)) . ") values (" . implode(",", array_fill(0, count($nameValues), "?")) . ")", $nameValues);
						if (!empty($insertSet['sql_error'])) {
							addDebugLog("Error: Line " . $lineNumber . ", " . $tableName . ": " . $insertSet['sql_error'] . ":" . $insertSet['query']);
							$errorCount++;
							if (!$testMode) {
								exit;
							}
						}
						$keyValueId = $insertSet['insert_id'];
					}
					if (!empty($keyValueId) && $keyValueId != $fieldData['template_id']) {
						$translations['template_id'][$fieldData['template_id']] = $keyValueId;
					}
					break;
				case "core_pages":
					if (!array_key_exists("page_id", $translations)) {
						$translations['page_id'] = array();
					}
					$keyValueId = getFieldFromId("page_id", "pages", "page_code", $fieldData['page_code'], "client_id = 1");
					if (empty($keyValueId)) {
						$keyValueId = getFieldFromId("page_id", "pages", "page_code", $fieldData['page_code'], "client_id = ?", $newClientId);
					}
					if (empty($keyValueId)) {
						$nameValues = $fieldData;
						$nameValues['page_id'] = "";
						if (array_key_exists($nameValues['template_id'], $translations['template_id'])) {
							$nameValues['template_id'] = $translations['template_id'][$nameValues['template_id']];
						}
						$insertSet = executeQuery("insert into " . $tableName . " (" . implode(",", array_keys($nameValues)) . ") values (" . implode(",", array_fill(0, count($nameValues), "?")) . ")", $nameValues);
						if (!empty($insertSet['sql_error'])) {
							addDebugLog("Error: Line " . $lineNumber . ", " . $tableName . ": " . $insertSet['sql_error'] . ":" . $insertSet['query']);
							$errorCount++;
							if (!$testMode) {
								exit;
							}
						}
						$keyValueId = $insertSet['insert_id'];
					}
					if (!empty($keyValueId) && $keyValueId != $fieldData['page_id']) {
						$translations['page_id'][$fieldData['page_id']] = $keyValueId;
					}
					break;
			}
			continue;
		}

		foreach ($fieldData as $fieldName => $fieldValue) {
			$searchFieldName = $fieldName;
			if (array_key_exists($searchFieldName, $equivalentFields)) {
				$searchFieldName = $equivalentFields[$searchFieldName];
			}
			if (array_key_exists($searchFieldName, $translations)) {
				if (array_key_exists($fieldValue, $translations[$searchFieldName])) {
					$fieldData[$fieldName] = $translations[$searchFieldName][$fieldValue];
				}
			}
		}

		switch ($tableName) {
			case "images":
				if (empty($fieldData['extension'])) {
					$parts = explode(".", $fieldData['filename']);
					$fieldData['extension'] = end($parts);
				}
				$imageUrl = "/getimage.php?id=" . $fieldData['image_id'] . "&source=client_load&connection_key=" . $connectionKey;
				$fieldData['hash_code'] = "";
				$contentHash = $fieldData['content_hash'];
				unset($fieldData['content_hash']);
				$resultSet = executeQuery("insert into " . $tableName . " (" . implode(",", array_keys($fieldData)) . ") values (" . implode(",", array_fill(0, count($fieldData), "?")) . ")", $fieldData);
				if (!empty($resultSet['sql_error'])) {
					addDebugLog("Error: Line " . $lineNumber . ", " . $tableName . ": " . $resultSet['sql_error']);
					$errorCount++;
					if (!$testMode) {
						exit;
					}
				}
				$rowCount++;
				if ($rowCount % 100000 == 0) {
					addDebugLog(number_format($rowCount, 0, ".", ",") . " rows written");
				}
				$imageId = $fieldData['image_id'];
				$createFile = true;
				if (!empty($s3)) {
					$filename = "image" . $imageId . "." . $fieldData['extension'];
					$fileDirectory = substr($externalFileDirectory, 3);
					$fileDirectory = trim($fileDirectory, "/");
					if (strpos($fileDirectory, "/") === false) {
						$fileDirectory .= "/" . strtolower(getPreference("SYSTEM_NAME"));
					}
					$osFilename = "S3://" . $fileDirectory . "/" . $filename;
					$fileParts = explode("/",str_replace("S3://","",$osFilename),2);
					$bucketName = $fileParts[0];
					$objectName = $fileParts[1];
					$objectInfo = $s3->getObjectInfo($bucketName,$objectName);
					if (!empty($objectInfo) && $contentHash == $objectInfo['hash']) {
						$createFile = false;
						executeQuery("update images set os_filename = ?, file_content = null where image_id = ?", $osFilename, $imageId);
					}
				}

				$imageContent = file_get_contents("https://" . $domainName . $imageUrl);
				if (empty($imageContent)) {
					addDebugLog("Image ID " . $fieldData['image_id'] . " has no content");
				}
				if (strpos($imageContent, "<body>") !== false) {
					addDebugLog("Image ID " . $fieldData['image_id'] . " has HTML content");
				}
				putExternalImageContents($fieldData['image_id'], $fieldData['extension'], $imageContent);
				break;
			case "files":
				if (empty($fieldData['extension'])) {
					$parts = explode(".", $fieldData['filename']);
					$fieldData['extension'] = end($parts);
				}
				$contentHash = $fieldData['content_hash'];
				unset($fieldData['content_hash']);
				$resultSet = executeQuery("insert into " . $tableName . " (" . implode(",", array_keys($fieldData)) . ") values (" . implode(",", array_fill(0, count($fieldData), "?")) . ")", array_values($fieldData));
				if (!empty($resultSet['sql_error'])) {
					addDebugLog("Error: Line " . $lineNumber . ", " . $tableName . ": " . $resultSet['sql_error']);
					$errorCount++;
					if (!$testMode) {
						exit;
					}
				}
				$rowCount++;
				if ($rowCount % 100000 == 0) {
					addDebugLog(number_format($rowCount, 0, ".", ",") . " rows written");
				}
				$fileId = $fieldData['file_id'];
				$createFile = true;
				if (!empty($s3)) {
					$filename = "file" . $fileId . "." . $fieldData['extension'];
					$fileDirectory = substr($externalFileDirectory, 3);
					$fileDirectory = trim($fileDirectory, "/");
					if (strpos($fileDirectory, "/") === false) {
						$fileDirectory .= "/" . strtolower(getPreference("SYSTEM_NAME"));
					}
					$osFilename = "S3://" . $fileDirectory . "/" . $filename;
					$fileParts = explode("/",str_replace("S3://","",$osFilename),2);
					$bucketName = $fileParts[0];
					$objectName = $fileParts[1];
					$objectInfo = $s3->getObjectInfo($bucketName,$objectName);
					if (!empty($objectInfo) && $contentHash == $objectInfo['hash']) {
						$createFile = false;
						executeQuery("update files set os_filename = ?, file_content = null where file_id = ?", $osFilename, $fileId);
					}
				}

				if ($createFile) {
					$fileContents = file_get_contents("https://" . $domainName . "/download.php?id=" . $fileId . "&source=client_load&connection_key=" . $connectionKey);
					if (empty($fileContents)) {
						addDebugLog("File ID " . $fieldData['file_id'] . " has no content");
					}
					if (strpos($fileContents, "<body>") !== false) {
						addDebugLog("File ID " . $fieldData['file_id'] . " has HTML content");
					}
					putExternalImageContents($fieldData['file_id'], $fieldData['extension'], $fileContents);
				}
				break;
			case "contacts":
			case "users":
				if (array_key_exists($fieldData['contact_id'], $superuserContacts) || array_key_exists($fieldData['user_id'], $superuserUsers)) {
					break;
				}
			default:
				$primaryKey = $primaryKeys[$tableName];
				if (in_array($tableName, $clearKeyTables)) {
					$fieldData[$primaryKey] = "";
				}

				if (array_key_exists($tableName, $translationTables)) {
					if (!array_key_exists($primaryKey, $translations)) {
						$translations[$primaryKey] = array();
					}
					$keyValueId = "";
					if (!empty($translationTables[$tableName])) {
						$keyValueId = getFieldFromId($primaryKey, $tableName, $translationTables[$tableName], $fieldData[$translationTables[$tableName]]);
					}
					if (empty($keyValueId)) {
						$nameValues = $fieldData;
						$nameValues[$primaryKey] = "";
						$insertSet = executeQuery("insert into " . $tableName . " (" . implode(",", array_keys($nameValues)) . ") values (" . implode(",", array_fill(0, count($nameValues), "?")) . ")", $nameValues);
						if (!empty($insertSet['sql_error'])) {
							addDebugLog("Error: Line " . $lineNumber . ", " . $tableName . ": " . $insertSet['sql_error'] . ":" . $insertSet['query']);
							$errorCount++;
							if (!$testMode) {
								exit;
							}
						}
						$keyValueId = $insertSet['insert_id'];
					}
					if ($keyValueId != $fieldData[$primaryKey]) {
						$translations[$primaryKey][$fieldData[$primaryKey]] = $keyValueId;
					}
					continue;
				}

				if (empty($insertStatement)) {
					$insertStatement = "insert into " . $tableName . " (" . implode(",", array_keys($fieldData)) . ") values ";
				} else {
					$insertStatement .= ",";
				}
				$insertStatement .= "(" . implode(",", array_fill(0, count($fieldData), "?")) . ")";
				foreach ($fieldData as $thisParameter) {
					$insertParameters[] = $thisParameter;
				}
				$rowCount++;
				if ($rowCount % 100000 == 0) {
					addDebugLog(number_format($rowCount, 0, ".", ",") . " rows written");
				}
				break;
		}
	}
	if (!empty($insertStatement)) {
		$resultSet = executeQuery($insertStatement, $insertParameters);
		if (!empty($resultSet['sql_error'])) {
			addDebugLog("Error: Line " . $lineNumber . ", " . $tableName . ": " . $resultSet['sql_error']);
			$errorCount++;
			if (!$testMode) {
				exit;
			}
		}
	}
}

fclose($handle);

executeQuery("update pages set creator_user_id = 10000 where creator_user_id not in (select user_id from users)");
executeQuery("update contacts set image_id = null where image_id is not null and contact_id in (select contact_id from users where superuser_flag = 1)");
executeQuery("update users set user_type_id = null where user_type_id is not null and superuser_flag = 1 and user_type_id not in (select user_type_id from user_types)");
executeQuery("update products set reindex = 1");

if (!$testMode) {
	$columnDefinitions = array();
	$columnNames = array();
	$tableColumns = array();
	$tables = array();
	$resultSet = executeQuery("select * from column_definitions");
	while ($row = getNextRow($resultSet)) {
		$columnDefinitions[$row['column_definition_id']] = $row;
		$columnNames[$row['column_name']] = $row['column_definition_id'];
	}
	$resultSet = executeQuery("select * from table_columns");
	while ($row = getNextRow($resultSet)) {
		$tableColumns[$row['table_column_id']] = $row;
	}
	$resultSet = executeQuery("select * from tables");
	while ($row = getNextRow($resultSet)) {
		$tables[$row['table_id']] = $row;
	}

	$foreignKeys = array();
	$tableSet = executeQuery("select * from tables");
	while ($tableRow = getNextRow($tableSet)) {
		$resultSet = executeQuery("select *,(select column_name from column_definitions where column_definition_id = " .
			"(select column_definition_id from table_columns where table_column_id = foreign_keys.table_column_id)) column_name " .
			"from foreign_keys where table_column_id in (select table_column_id from table_columns where table_id = ?) order by column_name", $tableRow['table_id']);
		while ($row = getNextRow($resultSet)) {
			$columnName = $columnDefinitions[$tableColumns[$row['table_column_id']]['column_definition_id']]['column_name'];
			$referencedTableName = $tables[$tableColumns[$row['referenced_table_column_id']]['table_id']]['table_name'];
			$referencedColumnName = $columnDefinitions[$tableColumns[$row['referenced_table_column_id']]['column_definition_id']]['column_name'];
			$foreignKeys[] = array("table_name" => $tableRow['table_name'], "column_name"=>$columnName, "script" => "ALTER TABLE " . $tableRow['table_name'] . " ADD CONSTRAINT fk_" . md5($tableRow['table_name'] . "_" . $columnName) .
				" FOREIGN KEY (" . $columnName . ") REFERENCES " . $referencedTableName . "(" . $referencedColumnName . ");");
		}
	}

	foreach ($foreignKeys as $thisStatement) {
		executeQuery("delete from query_log");
		$resultSet = executeQuery($thisStatement['script']);
		if (empty($resultSet['sql_error'])) {
			addDebugLog("Foreign key for " . $thisStatement['table_name'] . "." . $thisStatement['column_name'] . " created");
		} else {
			addDebugLog($thisStatement['table_name'] . ": " . $resultSet['sql_error']);
		}
	}
}

addDebugLog($rowCount . " rows of data inserted");
sendEmail(array("subject" => "Client Load Completed", "body" => "<p>Client load for " . $clientName . " has completed. " . $rowCount . " rows of data were inserted.</p>" . ($errorCount == 0 ? "" : "<p>" . $errorCount . " errors found</p>"), "email_address" => "7193597162@vtext.com"));
exit;
