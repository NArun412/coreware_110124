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

$GLOBALS['gPageCode'] = "FILEMANAGER";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 3000000;

class FileManagerPage extends Page {

	var $iFileFolderId = "";
	var $iFileTagId = "";
	var $iSortOrderColumn = "";
	var $iReverseSortOrder = false;
	var $iValidSortFields = array("kind", "file_id", "file_code", "description", "date_uploaded", "size");
	var $iFileTableExemptions = array("download_log");

	function setup() {
		if (!$GLOBALS['gUserRow']['administrator_flag']) {
			$GLOBALS['gPermissionLevel'] = _READONLY;
		}
		if (!empty($_GET['file_tag_code'])) {
			$this->iFileTagId = getFieldFromId("file_tag_id", "file_tags", "file_tag_code", $_GET['file_tag_code']);
		}
		$valuesArray = Page::getPagePreferences();
		if (!empty($_GET['file_folder'])) {
			$fileFolderId = getFieldFromId("file_folder_id", "file_folders", "file_folder_code", $_GET['file_folder'], "inactive = 0");
			$valuesArray['file_folder_id'] = $fileFolderId;
			Page::setPagePreferences($valuesArray);
		}
		$this->iFileFolderId = getFieldFromId("file_folder_id", "file_folders", "file_folder_id", $valuesArray['file_folder_id'], "inactive = 0");
		$this->iSortOrderColumn = getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
		$this->iReverseSortOrder = getPreference("MAINTENANCE_REVERSE_SORT_ORDER", $GLOBALS['gPageRow']['page_code']);
	}

	function contentSort($a, $b) {
		if ($a['data'][$this->iSortOrderColumn] == $b['data'][$this->iSortOrderColumn]) {
			return 0;
		}
		return ($a['data'][$this->iSortOrderColumn] > $b['data'][$this->iSortOrderColumn] ? -1 : 1) * ($this->iReverseSortOrder ? 1 : -1);
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "set_upload_accessibility":
				$valuesArray = Page::getPagePreferences();
				$valuesArray['upload_accessibility'] = $_GET['upload_accessibility'];
				Page::setPagePreferences($valuesArray);
				ajaxResponse($returnArray);
				break;
			case "upload_file":
				$this->iDatabase->startTransaction();
				$table = new DataTable("files");
				$table->setSaveOnlyPresent(true);
				if (array_key_exists("file", $_FILES) && !empty($_FILES['file']['name']) && !empty($_FILES['file']['tmp_name'])) {
					$_POST['date_uploaded'] = date("Y-m-d");
					$_POST['filename'] = $_FILES['file']['name'];
					if (array_key_exists($_FILES['file']['type'], $GLOBALS['gMimeTypes'])) {
						$_POST['extension'] = $GLOBALS['gMimeTypes'][$_FILES['file']['type']];
					} else {
						$fileNameParts = explode(".", $_FILES['file']['name']);
						$_POST['extension'] = $fileNameParts[count($fileNameParts) - 1];
					}
					$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
					if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
						$maxDBSize = 1000000;
					}
					if ($_FILES['file']['size'] < $maxDBSize) {
						$_POST['file_content'] = file_get_contents($_FILES['file']['tmp_name']);
						$_POST['os_filename'] = "";
					} else {
						$_POST['file_content'] = "";
						$_POST['os_filename'] = "/documents/tmp." . $_POST['extension'];
					}
				}
				$valuesArray = Page::getPagePreferences();
				$_POST['internal_use_only'] = 0;
				$_POST['file_size'] = $_FILES['file']['size'];
				$_POST['public_access'] = (empty($valuesArray['upload_accessibility']) ? 1 : 0);
				$_POST['all_user_access'] = ($valuesArray['upload_accessibility'] == "1" ? 1 : 0);
				$_POST['administrator_access'] = ($valuesArray['upload_accessibility'] == "2" ? 1 : 0);
				$_POST['file_folder_id'] = $this->iFileFolderId;
				$_POST['file_tag_id'] = $this->iFileTagId;
				$_POST['description'] = $_FILES['file']['name'];
				$table->setPrimaryId("");
				$primaryId = $table->saveRecord(array("name_values" => $_POST));
				if (empty($_POST['file_code'])) {
					executeQuery("update files set file_code = ? where file_id = ?", "FILE" . $primaryId, $primaryId);
				}
				if (!$primaryId) {
					$returnArray['error_message'] = $table->getErrorMessage();
					$this->iDatabase->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				} else if (!empty($_POST['os_filename']) && array_key_exists("file", $_FILES) && !empty($_FILES['file']['name'])) {
					putExternalFileContents($primaryId, $_POST['extension'], file_get_contents($_FILES['file']['tmp_name']));
				}
				$this->iDatabase->commitTransaction();
				ajaxResponse($returnArray);
				break;
			case "get_file_folder_info":
				$resultSet = executeQuery("select * from file_folders where file_folder_id = ?", $_GET['file_folder_id']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['file_folder_id'] = $row['file_folder_id'];
					$returnArray['file_folder_code'] = $row['file_folder_code'];
					$returnArray['description'] = $row['description'];
					$returnArray['detailed_description'] = $row['detailed_description'];
					$returnArray['user_group_id'] = $row['user_group_id'];
				}
				ajaxResponse($returnArray);
				break;
			case "set_sort_order":
				$originalSortOrderColumn = getPreference("MAINTENANCE_SORT_ORDER_COLUMN", $GLOBALS['gPageRow']['page_code']);
				if ($originalSortOrderColumn != $_GET['sort_field']) {
					$this->iReverseSortOrder = false;
				} else {
					$this->iReverseSortOrder = !$this->iReverseSortOrder;
				}
				$this->iSortOrderColumn = $_GET['sort_field'];
				if (!in_array($this->iSortOrderColumn, $this->iValidSortFields)) {
					$this->iSortOrderColumn = "kind";
				}
				setUserPreference("MAINTENANCE_SORT_ORDER_COLUMN", $this->iSortOrderColumn, $GLOBALS['gPageRow']['page_code']);
				setUserPreference("MAINTENANCE_REVERSE_SORT_ORDER", ($this->iReverseSortOrder ? "true" : "false"), $GLOBALS['gPageRow']['page_code']);
				ajaxResponse($returnArray);
				break;
			case "save_file_folder_changes":
				$returnArray = array();
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					$fileFolderId = $_POST['file_folder_id'];
					if (empty($fileFolderId)) {
						$fileFolderCode = makeCode($_POST['folder_description']);
						$fileFolderNumber = -1;
						do {
							$fileFolderNumber++;
							$fileFolderId = getFieldFromId("file_folder_id", "file_folders", "file_folder_code", $fileFolderCode . (empty($fileFolderNumber) ? "" : "_" . $fileFolderNumber));
						} while (!empty($fileFolderId));
						$resultSet = executeQuery("insert into file_folders (client_id,file_folder_code,description,detailed_description,parent_file_folder_id,user_group_id) values (?,?,?,?,?,?)",
							$GLOBALS['gClientId'], $fileFolderCode . (empty($fileFolderNumber) ? "" : "_" . $fileFolderNumber), $_POST['folder_description'], $_POST['folder_detailed_description'], $this->iFileFolderId, $_POST['folder_user_group_id']);
						if (empty($resultSet['sql_error'])) {
							$fileFolderId = $resultSet['insert_id'];
						}
					} else {
						if (empty($_POST['file_folder_code'])) {
							$resultSet = executeQuery("update file_folders set description = ?,detailed_description = ?,user_group_id = ? where file_folder_id = ?",
								$_POST['folder_description'], $_POST['folder_detailed_description'], $_POST['folder_user_group_id'], $fileFolderId);
						} else {
							$resultSet = executeQuery("update file_folders set file_folder_code = ?, description = ?, detailed_description = ?,user_group_id = ? where file_folder_id = ?",
								$_POST['file_folder_code'], $_POST['folder_description'], $_POST['folder_detailed_description'], $_POST['folder_user_group_id'], $fileFolderId);
						}
					}
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					}
				}
				ob_start();
				?>
                <select id="new_file_folder_id" name="new_file_folder_id">
                    <option value="">[Root Folder]</option>
					<?php
					$resultSet = executeQuery("select * from file_folders where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['file_folder_id'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
				<?php
				$returnArray['new_file_folder_id_cell'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "move_folder":
				$returnArray = array();
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					if (!empty($_POST['move_file_id'])) {
						$resultSet = executeQuery("select file_id,public_access,all_user_access,administrator_access from files where file_id = ? and client_id = ?", $_POST['move_file_id'], $GLOBALS['gClientId']);
						if ($row = getNextRow($resultSet)) {
							$canAccessDocument = $GLOBALS['gUserRow']['superuser_flag'] ||
								(($row['public_access'] == 1 || ($GLOBALS['gLoggedIn'] && $row['all_user_access'] == 1) ||
									($GLOBALS['gUserRow']['administrator_flag'] && $row['administrator_access'])));
							if (!$canAccessDocument) {
								$returnArray['error_message'] = getSystemMessage("denied");
								ajaxResponse($returnArray);
								break;
							}
						}
						$resultSet = executeQuery("update files set file_folder_id = ? where file_id = ?", $_POST['new_file_folder_id'], $_POST['move_file_id']);
					} else {
						if ($_POST['new_file_folder_id'] == $_POST['move_file_folder_id']) {
							$returnArray['error_message'] = "The folder cannot be moved into itself.";
						} else {
							$resultSet = executeQuery("update file_folders set parent_file_folder_id = ? where file_folder_id = ?", $_POST['new_file_folder_id'], $_POST['move_file_folder_id']);
						}
					}
					if (!empty($resultSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					}
				}
				ajaxResponse($returnArray);
				break;
			case "save_file_changes":
				$returnArray = array();
				if ($GLOBALS['gPermissionLevel'] > _READONLY) {
					if (!empty($_POST['file_id'])) {
						$resultSet = executeQuery("select file_id,public_access,all_user_access,administrator_access from files where file_id = ? and client_id = ? and " .
							"((select security_level from security_levels where security_level_id = files.security_level_id) <= ? or security_level_id is null)",
							$_POST['file_id'], $GLOBALS['gClientId'], $GLOBALS['gUserRow']['security_level']);
						if ($row = getNextRow($resultSet)) {
							$canAccessDocument = $GLOBALS['gUserRow']['superuser_flag'] ||
								(($row['public_access'] == 1 || ($GLOBALS['gLoggedIn'] && $row['all_user_access'] == 1) ||
									($GLOBALS['gUserRow']['administrator_flag'] && $row['administrator_access'])));
							if (!$canAccessDocument) {
								ajaxResponse($returnArray);
								break;
							}
						}
					}

					$this->iDatabase->startTransaction();
					$table = new DataTable("files");
					$table->setSaveOnlyPresent(true);
					if (array_key_exists("file_content_file", $_FILES) && !empty($_FILES['file_content_file']['name'])) {
						$_POST['date_uploaded'] = date("Y-m-d");
						$_POST['filename'] = $_FILES['file_content_file']['name'];
						if (array_key_exists($_FILES['file_content_file']['type'], $GLOBALS['gMimeTypes'])) {
							$_POST['extension'] = $GLOBALS['gMimeTypes'][$_FILES['file_content_file']['type']];
						} else {
							$fileNameParts = explode(".", $_FILES['file_content_file']['name']);
							$_POST['extension'] = $fileNameParts[count($fileNameParts) - 1];
						}
						$maxDBSize = getPreference("EXTERNAL_FILE_SIZE");
						if (empty($maxDBSize) || !is_numeric($maxDBSize)) {
							$maxDBSize = 1000000;
						}
						if ($_FILES['file_content_file']['size'] < $maxDBSize) {
							$_POST['file_content'] = file_get_contents($_FILES['file_content_file']['tmp_name']);
							$_POST['os_filename'] = "";
						} else {
							$_POST['file_content'] = "";
							$_POST['os_filename'] = "/documents/tmp." . $_POST['extension'];
						}
					}
					$_POST['internal_use_only'] = (empty($_POST['internal_use_only']) ? 0 : 1);
					$_POST['public_access'] = (empty($_POST['accessibility']) ? 1 : 0);
					$_POST['all_user_access'] = ($_POST['accessibility'] == "1" ? 1 : 0);
					$_POST['administrator_access'] = ($_POST['accessibility'] == "2" ? 1 : 0);
					$_POST['file_folder_id'] = $this->iFileFolderId;
					$_POST['file_tag_id'] = $this->iFileTagId;
					$table->setPrimaryId($_POST['file_id']);
					$primaryId = $table->saveRecord(array("name_values" => $_POST));
					if (empty($_POST['file_code'])) {
						executeQuery("update files set file_code = ? where file_id = ?", "FILE" . $primaryId, $primaryId);
					}
					if (!$primaryId) {
						$returnArray['error_message'] = $table->getErrorMessage();
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					} else if (!empty($_POST['os_filename']) && array_key_exists("file_content_file", $_FILES) && !empty($_FILES['file_content_file']['name'])) {
						putExternalFileContents($primaryId, $_POST['extension'], file_get_contents($_FILES['file_content_file']['tmp_name']));
					}
					$this->iDatabase->commitTransaction();
                    getFileFilename($primaryId, true);
				}
				ajaxResponse($returnArray);
				break;
			case "delete_folder":
				$returnArray = array();
				if ($GLOBALS['gPermissionLevel'] > _READWRITE) {
					$fileFolderId = $_GET['file_folder_id'];
					$resultSet = executeQuery("delete from file_folders where file_folder_id = ?", $fileFolderId);
				}
				ob_start();
				?>
                <select id="new_file_folder_id" name="new_file_folder_id">
                    <option value="">[Root Folder]</option>
					<?php
					$resultSet = executeQuery("select * from file_folders where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value="<?= $row['file_folder_id'] ?>"><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
				<?php
				$returnArray['new_file_folder_id_cell'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "delete_file":
				$returnArray = array();
				if ($GLOBALS['gPermissionLevel'] > _READWRITE) {
					$fileId = $_GET['file_id'];
					$resultSet = executeQuery("select file_id,public_access,all_user_access,administrator_access from files where file_id = ? and client_id = ? and " .
						"((select security_level from security_levels where security_level_id = files.security_level_id) <= ? or security_level_id is null)",
						$fileId, $GLOBALS['gClientId'], $GLOBALS['gUserRow']['security_level']);
					if ($row = getNextRow($resultSet)) {
						$canAccessDocument = $GLOBALS['gUserRow']['superuser_flag'] ||
							(($row['public_access'] == 1 || ($GLOBALS['gLoggedIn'] && $row['all_user_access'] == 1) ||
								($GLOBALS['gUserRow']['administrator_flag'] && $row['administrator_access'])));
						if (!$canAccessDocument) {
							continue;
						}
						executeQuery("delete from download_log where file_id = ?", $fileId);
						$fileDataTable = new DataTable("files");
						$fileDataTable->deleteRecord(array("primary_id" => $fileId));
					}
				}
				ajaxResponse($returnArray);
				break;
			case "change_folder":
				$this->iFileFolderId = getFieldFromId("file_folder_id", "file_folders", "file_folder_id", $_GET['file_folder_id'], "inactive = 0 and client_id = " . $GLOBALS['gClientId']);
				$valuesArray = Page::getPagePreferences();
				$valuesArray['file_folder_id'] = $this->iFileFolderId;
				Page::setPagePreferences($valuesArray);
			case "get_contents":
				$returnArray = array();
				ob_start();
				$folderContents = array();
				$folderCount = 0;
				$fileCount = 0;
				$resultSet = executeQuery("select * from file_folders where parent_file_folder_id <=> ? and client_id = ? and inactive = 0 order by description", $this->iFileFolderId, $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$folderCount++;
					$row['contents_count'] = 0;
					$countSet = executeQuery("select count(*) from file_folders where parent_file_folder_id = ?", $row['file_folder_id']);
					if ($countRow = getNextRow($countSet)) {
						$row['contents_count'] += $countRow['count(*)'];
					}
					$countSet = executeQuery("select count(*) from files where file_folder_id = ?" . (empty($this->iFileTagId) ? "" : " and file_tag_id = " . $this->iFileTagId), $row['file_folder_id']);
					if ($countRow = getNextRow($countSet)) {
						$row['contents_count'] += $countRow['count(*)'];
					}
					$row['size'] = $this->getFolderSize($row['file_folder_id']);
					$folderContents[] = array("file_type" => "folder", "data" => $row);
				}

				$fileTableColumnId = "";
				$resultSet = executeQuery("select table_column_id from table_columns where table_id = " .
					"(select table_id from tables where table_name = 'files' and database_definition_id = " .
					"(select database_definition_id from database_definitions where database_name = ?)) and column_definition_id = " .
					"(select column_definition_id from column_definitions where column_name = 'file_id')", $GLOBALS['gPrimaryDatabase']->getName());
				if ($row = getNextRow($resultSet)) {
					$fileTableColumnId = $row['table_column_id'];
				}
				$query = "";
				if (empty($this->iFileFolderId)) {
					$resultSet = executeQuery("select table_id,(select table_name from tables where table_id = table_columns.table_id) table_name," .
						"column_definition_id,(select column_name from column_definitions where column_definition_id = table_columns.column_definition_id) column_name " .
						"from table_columns where table_column_id in (select table_column_id from foreign_keys where referenced_table_column_id = ?)", $fileTableColumnId);
					while ($row = getNextRow($resultSet)) {
						if (in_array($row['table_name'], $this->iFileTableExemptions)) {
							continue;
						}
						if (!empty($query)) {
							$query .= " and ";
						}
						$query .= "not exists (select " . $row['column_name'] . " from " . $row['table_name'] . " where " . $row['column_name'] . " = files.file_id)";
					}
				}

				$resultSet = executeQuery("select file_id,file_code,description,detailed_description,security_level_id,user_group_id," .
					"date_uploaded,public_access,all_user_access,administrator_access,internal_use_only,os_filename,file_size from files " .
					"where file_folder_id <=> ? and client_id = ? and inactive = 0 and " . (empty($this->iFileTagId) ? "" : "file_tag_id = " . $this->iFileTagId . " and ") .
					"((select security_level from security_levels where security_level_id = files.security_level_id) <= ? or security_level_id is null)" .
					(empty($query) ? "" : " and " . $query) . " order by description", $this->iFileFolderId, $GLOBALS['gClientId'], $GLOBALS['gUserRow']['security_level']);
				while ($row = getNextRow($resultSet)) {
					$canAccessDocument = $GLOBALS['gUserRow']['superuser_flag'] ||
						(($row['public_access'] == 1 || ($GLOBALS['gLoggedIn'] && $row['all_user_access'] == 1) ||
							($GLOBALS['gUserRow']['administrator_flag'] && $row['administrator_access'])));
					if (!$canAccessDocument) {
						continue;
					}
					$fileCount++;
					if (empty($row['file_size'])) {
						if (empty($row['os_filename'])) {
                            $content = getFieldFromId("file_content","files","file_id",$row['file_id']);
							$row['size'] = strlen($content);
						} else if (file_exists($row['os_filename'])) {
							$fileContents = getExternalFileContents($row['os_filename']);
							$row['size'] = strlen($fileContents);
						} else {
							$row['size'] = 0;
						}
						executeQuery("update files set file_size = ? where file_id = ?", $row['size'], $row['file_id']);
					} else {
						$row['size'] = $row['file_size'];
					}
					$folderContents[] = array("file_type" => "file", "data" => $row);
				}
				$colspan = 2 + ($GLOBALS['gPermissionLevel'] > _READWRITE ? 1 : 0) + ($GLOBALS['gPermissionLevel'] > _READONLY ? 2 : 0);
				$valuesArray = Page::getPagePreferences();
				if (array_key_exists("show_code", $_GET)) {
					$valuesArray['show_code'] = $_GET['show_code'];
					Page::setPagePreferences($valuesArray);
				}
				$showCode = ($valuesArray['show_code'] == "true");
				?>
                <h3><?= (empty($this->iFileFolderId) ? "[Root Folder]" : htmlText(getFieldFromId("description", "file_folders", "file_folder_id", $this->iFileFolderId))) ?></h3>
				<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                <div class="basic-form-line align-left" id="_accessibility_row">
                    <label for="upload_accessibility">Uploaded files will be accessible by</label>
                    <select id="upload_accessibility" name="upload_accessibility">
                        <option value=""<?= (empty($valuesArray['upload_accessibility']) ? " selected" : "") ?>>Everyone</option>
                        <option value="1"<?= ($valuesArray['upload_accessibility'] == "1" ? " selected" : "") ?>>Registered Users</option>
                        <option value="2"<?= ($valuesArray['upload_accessibility'] == "2" ? " selected" : "") ?>>Administrators Only</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class='dropzone'>
                    <div class='dz-message'>
                        <p class="align-left">Drop one or more files here or click to upload.</p>
                    </div>
                </div>
			<?php } ?>
                <p id="file_count"><?= $folderCount ?> folders, <?= $fileCount ?> files</p>
                <p id="controllers">
					<?php if (!empty($this->iFileFolderId)) { ?>
                        <button id="up_level" data-file_folder_id="<?= getFieldFromId("parent_file_folder_id", "file_folders", "file_folder_id", $this->iFileFolderId) ?>">Parent Folder</button><span class="spacer"></span>
					<?php } ?>
					<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                        <button accesskey="a" id="new_document">New Document</button><span class="spacer"></span>
                        <button id="new_folder">New Folder</button><span class="spacer"></span>
                        <input type="checkbox" id="show_code" name="show_code" value="true"<?= ($showCode ? " checked" : "") ?>><label for="show_code" class="checkbox-label">Show Code</label><span class="spacer"></span>
					<?php } ?>
                    <input type="text" id="search_filter" name="search_filter" value=""><span class="spacer"></span>
                </p>
                <table id="folder_contents_table" class="grid-table">
                    <tr>
                        <th data-sort_field="kind"></th>
                        <th class='hidden' data-sort_field="file_id">ID<?= ($this->iSortOrderColumn == "file_id" ? ($this->iReverseSortOrder ? "&nbsp;<span class='fa fa-angle-double-up'></span>" : "&nbsp;<span class='fa fa-angle-double-down'></span>") : "") ?></th>
                        <th data-sort_field="file_code" <?= ($showCode ? "" : "class='hidden'") ?>>Code<?= ($this->iSortOrderColumn == "file_code" ? ($this->iReverseSortOrder ? "&nbsp;<span class='fa fa-angle-double-up'></span>" : "&nbsp;<span class='fa fa-angle-double-down'></span>") : "") ?></th>
                        <th data-sort_field="description">Filename<?= ($this->iSortOrderColumn == "description" ? ($this->iReverseSortOrder ? "&nbsp;<span class='fa fa-angle-double-up'></span>" : "&nbsp;<span class='fa fa-angle-double-down'></span>") : "") ?></th>
                        <th data-sort_field="date_uploaded">Date Added<?= ($this->iSortOrderColumn == "date_uploaded" ? ($this->iReverseSortOrder ? "&nbsp;<span class='fa fa-angle-double-up'></span>" : "&nbsp;<span class='fa fa-angle-double-down'></span>") : "") ?></th>
                        <th data-sort_field="size">Size<?= ($this->iSortOrderColumn == "size" ? ($this->iReverseSortOrder ? "&nbsp;<span class='fa fa-angle-double-up'></span>" : "&nbsp;<span class='fa fa-angle-double-down'></span>") : "") ?></th>
                        <th colspan="<?= $colspan ?>"></th>
                    </tr>
					<?php

					if (!empty($this->iSortOrderColumn) && $this->iSortOrderColumn != "kind") {
						usort($folderContents, array($this, "contentSort"));
					}

					$fileDetails = array();
					foreach ($folderContents as $fileInfo) {
						if ($fileInfo['file_type'] == "file") {
							$fileDetails[$fileInfo['data']['file_id']] = array("file_id" => $fileInfo['data']['file_id'],
								"file_code" => $fileInfo['data']['file_code'],
								"description" => $fileInfo['data']['description'],
								"detailed_description" => $fileInfo['data']['detailed_description'],
								"security_level_id" => $fileInfo['data']['security_level_id'],
								"user_group_id" => $fileInfo['data']['user_group_id'],
								"accessibility" => (!empty($fileInfo['data']['public_access']) ? "" : (!empty($fileInfo['data']['all_user_access']) ? "1" : "2")),
								"internal_use_only" => $fileInfo['data']['internal_use_only']);
						}
						?>
                        <tr class="<?= $fileInfo['file_type'] ?>-row" data-file<?= ($fileInfo['file_type'] == "file" ? "" : "_folder") ?>_id="<?= $fileInfo['data'][($fileInfo['file_type'] == "file" ? "file_id" : "file_folder_id")] ?>">
                            <td><span class='fad fa-<?= $fileInfo['file_type'] ?>'></span></td>
                            <td class='hidden'><?= $fileInfo['data']['file_id'] ?></td>
                            <td class="file-code<?= ($showCode ? "" : " hidden") ?>"><?= ($fileInfo['file_type'] == "file" ? $fileInfo['data']['file_code'] : $fileInfo['data']['file_folder_code']) ?></td>
                            <td class="description"><?php if ($fileInfo['file_type'] == "file") { ?><a href="/download.php?file_id=<?= $fileInfo['data']['file_id'] ?>"><?php } ?><?= $fileInfo['data']['description'] ?><?php if ($fileInfo['file_type'] == "file") { ?></a><?php } ?></td>
                            <td><?= ($fileInfo['file_type'] == "file" ? date("m/d/Y", strtotime($fileInfo['data']['date_uploaded'])) : "") ?></td>
                            <td class="align-right"><?= $fileInfo['data']['size'] ?></td>
							<?php if ($GLOBALS['gPermissionLevel'] > _READWRITE) { ?>
                                <td><span class="fad fa-trash delete-<?= $fileInfo['file_type'] ?>" data-contents_count="<?= $fileInfo['data']['contents_count'] ?>"></span></td>
							<?php } ?>
							<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
                                <td><span class="fad fa-folder-open move-<?= $fileInfo['file_type'] ?>"></span></td>
                                <td><span class="fad fa-edit edit-<?= $fileInfo['file_type'] ?>"></span></td>
							<?php } ?>
                            <td><span class="fad fa-link link-<?= $fileInfo['file_type'] ?>"></span><input type="text" class="hidden link-text"></td>
                            <td><?php if ($fileInfo['file_type'] == "file") { ?><a href="/download.php?file_id=<?= $fileInfo['data']['file_id'] ?>"><span class="fad fa-download"></span></a><?php } ?></td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
				$returnArray['file_details'] = $fileDetails;
				$returnArray['folder_contents'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
		}
	}

	function getFolderSize($fileFolderId) {
		$folderInfo = $this->calculateFileFolderSize($fileFolderId, 0, array($fileFolderId));
		return $folderInfo['folder_size'];
	}

	function calculateFileFolderSize($fileFolderId, $folderSize, $fileFolderIds) {
		$resultSet = executeQuery("select file_id,os_filename,length(file_content) file_content_length,file_size from files where file_folder_id = ?" . (empty($this->iFileTagId) ? "" : " and file_tag_id = " . $this->iFileTagId), $fileFolderId);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['file_size'])) {
				if (empty($row['os_filename'])) {
					$fileSize = $row['file_content_length'];
				} else {
					$fileContents = getExternalFileContents($row['os_filename']);
					$fileSize = strlen($fileContents);
				}
				executeQuery("update files set file_size = ? where file_id = ?", $fileSize, $row['file_id']);
			} else {
				$fileSize = $row['file_size'];
			}
			$folderSize += $fileSize;
		}
		$resultSet = executeQuery("select * from file_folders where parent_file_folder_id = ?", $fileFolderId);
		while ($row = getNextRow($resultSet)) {
			if (in_array($row['file_folder_id'], $fileFolderIds)) {
				continue;
			}
			$fileFolderIds[] = $row['file_folder_id'];
			$folderInfo = $this->calculateFileFolderSize($row['file_folder_id'], $folderSize, $fileFolderIds);
			$folderSize = $folderInfo['folder_size'];
			$fileFolderIds = $folderInfo['file_folder_ids'];
		}
		return array("folder_size" => $folderSize, "file_folder_ids" => $fileFolderIds);
	}

	function mainContent() {
		?>
        <div id="folder_contents"></div>
		<?php
		return true;
	}

	function javascript() {
		?>
        <script>
            let fileDetails = [];

            function getFolderContents() {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_contents" + ($("#show_code").length > 0 ? "&show_code=" + ($("#show_code").prop("checked") ? "true" : "") : ""), function(returnArray) {
                    if ("folder_contents" in returnArray) {
                        $("#folder_contents").html(returnArray['folder_contents']);
                        $("a[href*='download.php']").attr("target", "_blank");
                    }
                    if ("file_details" in returnArray) {
                        fileDetails = returnArray['file_details'];
                    }
                    $("div.dropzone").dropzone({
                        url: scriptFilename + "?url_action=upload_file", queuecomplete: function () {
                            getFolderContents();
                        }
                    });
                });
            }
			<?php if ($GLOBALS['gPermissionLevel'] > _READWRITE) { ?>
            function deleteRecord(fileId) {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_file&file_id=" + fileId, function(returnArray) {
                    getFolderContents();
                });
            }
			<?php } ?>
        </script>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>

            $(document).on("blur", ".link-text", function () {
                $(this).addClass("hidden");
            });
            $(document).on("click", ".link-file", function (event) {
                const fileCode = $(this).closest(".file-row").find(".file-code").text();
                const fileId = $(this).closest(".file-row").data("file_id");
                const linkUrl = "https://<?= $_SERVER['HTTP_HOST'] ?>/download.php?" + (empty(fileCode) ? "id=" + fileId : "code=" + fileCode);
                $(this).closest(".file-row").find(".link-text").val(linkUrl).removeClass("hidden").select().focus();
                event.preventDefault();
                event.stopPropagation();
            });
            $(document).on("click", ".link-folder", function (event) {
                const fileCode = $(this).closest(".folder-row").find(".file-code").text();
                const linkUrl = "https://<?= $_SERVER['HTTP_HOST'] ?><?= $GLOBALS['gLinkUrl'] ?>?file_folder=" + fileCode;
                $(this).closest(".folder-row").find(".link-text").val(linkUrl).removeClass("hidden").select().focus();
                event.preventDefault();
                event.stopPropagation();
            });
            $(document).on("tap click", "#show_code", function () {
                getFolderContents();
            });
            $(document).on("keyup", "#search_filter", function () {
                const searchText = $(this).val();
                if (empty(searchText)) {
                    $("#folder_contents_table tr").show();
                    return;
                }
                $("#folder_contents_table tr").each(function () {
                    if ($(this).find(".description").length === 0) {
                        return true;
                    }
                    let fileCode = "";
                    if ($("#show_code").prop("checked")) {
                        fileCode = $(this).find(".file-code").html();
                    }
                    const fileDescription = $(this).find(".description").html();
                    if ((!empty(fileCode) && fileCode.toLowerCase().indexOf(searchText.toLowerCase()) !== -1) || fileDescription.toLowerCase().indexOf(searchText.toLowerCase()) !== -1) {
                        $(this).show();
                    } else {
                        $(this).hide();
                    }
                });
            });
			<?php if ($GLOBALS['gPermissionLevel'] > _READONLY) { ?>
            $(document).on("tap click", "#new_document", function () {
                $("#_edit_file").find("input,textarea,select").each(function () {
                    if ($(this).is("input[type=checkbox]")) {
                        $(this).prop("checked", false);
                    } else {
                        $(this).val("");
                    }
                });
                $("#file_content_file").addClass("validate[required]");
                $('#_edit_file_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    width: 750,
                    title: 'New Document Details',
                    buttons: {
                        Save: function (event) {
                            if ($("#_edit_file").validationEngine('validate')) {
                                $("body").addClass("waiting-for-ajax");
                                $("#_edit_file").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_file_changes").attr("method", "POST").attr("target", "post_iframe").submit();
                                $("#_post_iframe").off("load");
                                $("#_post_iframe").on("load", function () {
                                    $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                                    const returnText = $(this).contents().find("body").html();
                                    const returnArray = processReturn(returnText);
                                    if (returnArray === false) {
                                        return;
                                    }
                                    getFolderContents();
                                });
                                $("#_edit_file_dialog").dialog('close');
                            }
                        },
                        Cancel: function (event) {
                            $("#_edit_file_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("tap click", "#new_folder", function () {
                $("#_edit_file_folder").find("input,textarea,select").each(function () {
                    $(this).val("");
                });
                $('#_edit_file_folder_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    width: 700,
                    title: 'New Folder',
                    buttons: {
                        Save: function (event) {
                            if ($("#_edit_file_folder").validationEngine('validate')) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_file_folder_changes", $("#_edit_file_folder").serialize(), function(returnArray) {
                                    getFolderContents();
                                    if ("new_file_folder_id_cell" in returnArray) {
                                        $("#new_file_folder_id_cell").html(returnArray['new_file_folder_id_cell']);
                                    }
                                });
                                $("#_edit_file_folder_dialog").dialog('close');
                            }
                        },
                        Cancel: function (event) {
                            $("#_edit_file_folder_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("tap click", ".edit-folder", function () {
                $("#_edit_file_folder").find("input,textarea,select").each(function () {
                    $(this).val("");
                });
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_file_folder_info&file_folder_id=" + $(this).closest("tr").data("file_folder_id"), function(returnArray) {
                    if ("file_folder_id" in returnArray) {
                        $("#file_folder_id").val(returnArray['file_folder_id']);
                        $("#file_folder_code").val(returnArray['file_folder_code']);
                        $("#folder_description").val(returnArray['description']);
                        $("#folder_detailed_description").val(returnArray['detailed_description']);
                        $("#folder_user_group_id").val(returnArray['user_group_id']);
                        $('#_edit_file_folder_dialog').dialog({
                            closeOnEscape: true,
                            draggable: false,
                            modal: true,
                            resizable: false,
                            position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                            width: 700,
                            title: 'Edit Folder',
                            buttons: {
                                Save: function (event) {
                                    if ($("#_edit_file_folder").validationEngine('validate')) {
                                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_file_folder_changes", $("#_edit_file_folder").serialize(), function(returnArray) {
                                            getFolderContents();
                                        });
                                    }
                                    $("#_edit_file_folder_dialog").dialog('close');
                                },
                                Cancel: function (event) {
                                    $("#_edit_file_folder_dialog").dialog('close');
                                }
                            }
                        });
                    }
                });
                return false;
            });
            $(document).on("tap click", ".edit-file", function () {
                const fileId = $(this).closest(".file-row").data("file_id");
                const details = fileDetails[fileId];
                $("#_edit_file").find("input,textarea,select").each(function () {
                    if ($(this).is("input[type=checkbox]")) {
                        $(this).prop("checked", (details[$(this).attr("id")] === "1"));
                    } else {
                        $(this).val(details[$(this).attr("id")]);
                    }
                });
                $("#file_content_file").removeClass("validate[required]");
                $('#_edit_file_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    width: 750,
                    title: 'Edit Document Details',
                    buttons: {
                        Save: function (event) {
                            if ($("#_edit_file").validationEngine('validate')) {
                                $("body").addClass("waiting-for-ajax");
                                $("#_edit_file").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_file_changes").attr("method", "POST").attr("target", "post_iframe").submit();
                                $("#_post_iframe").off("load");
                                $("#_post_iframe").on("load", function () {
                                    $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                                    const returnText = $(this).contents().find("body").html();
                                    const returnArray = processReturn(returnText);
                                    if (returnArray === false) {
                                        return;
                                    }
                                    getFolderContents();
                                });
                            }
                            $("#_edit_file_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_edit_file_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("tap click", ".move-file,.move-folder", function (event) {
                $("#move_file_id").val($(this).closest("tr").data("file_id"));
                $("#move_file_folder_id").val($(this).closest("tr").data("file_folder_id"));
                $('#_move_file_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    width: 600,
                    title: 'Move To New Folder',
                    buttons: {
                        Save: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=move_folder", $("#_move_file").serialize(), function(returnArray) {
                                getFolderContents();
                            });
                            $("#_move_file_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_move_file_dialog").dialog('close');
                        }
                    }
                });
                event.stopPropagation();
                return false;
            });
			<?php } ?>
			<?php if ($GLOBALS['gPermissionLevel'] > _READWRITE) { ?>
            $(document).on("tap click", ".delete-file", function (event) {
                $("#_confirm_delete_dialog").find(".dialog-text").html("Are you sure you want to delete this file?");
                const fileId = $(this).closest("tr.file-row").data("file_id");
                $('#_confirm_delete_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    title: 'Delete Document?',
                    buttons: {
                        Yes: function (event) {
                            deleteRecord(fileId);
                            $("#_confirm_delete_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_delete_dialog").dialog('close');
                        }
                    }
                });
                event.stopPropagation();
                return false;
            });
            $(document).on("tap click", ".delete-folder", function (event) {
                $("#_confirm_delete_dialog").find(".dialog-text").html("Are you sure you want to delete this folder?");
                const contentsCount = $(this).data("contents_count");
                if (contentsCount !== "0" && !empty(contentsCount)) {
                    displayErrorMessage("The folder must be empty to delete it.");
                    return false;
                }
                const fileFolderId = $(this).closest("tr.folder-row").data("file_folder_id");
                $('#_confirm_delete_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: {my: "center top", at: "center top+100px", of: window, collision: "none"},
                    title: 'Delete Folder?',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=delete_folder&file_folder_id=" + fileFolderId, function(returnArray) {
                                getFolderContents();
                                if ("new_file_folder_id_cell" in returnArray) {
                                    $("#new_file_folder_id_cell").html(returnArray['new_file_folder_id_cell']);
                                }
                            });
                            $("#_confirm_delete_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_delete_dialog").dialog('close');
                        }
                    }
                });
                event.stopPropagation();
                return false;
            });
			<?php } ?>
            $(document).on("change", "#upload_accessibility", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_upload_accessibility&upload_accessibility=" + $(this).val());
            });
            $(document).on("tap click", "th", function () {
                const sortField = $(this).data("sort_field");
                if (!empty(sortField)) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=set_sort_order&sort_field=" + sortField, function(returnArray) {
                        if ("folder_contents" in returnArray) {
                            $("#folder_contents").html(returnArray['folder_contents']);
                            $("a[href*='download.php']").attr("target", "_blank");
                        }
                        getFolderContents();
                    });
                }
            });
            $(document).on("tap click", "#up_level", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=change_folder&file_folder_id=" + $(this).data("file_folder_id"), function(returnArray) {
                    if ("folder_contents" in returnArray) {
                        $("#folder_contents").html(returnArray['folder_contents']);
                        $("a[href*='download.php']").attr("target", "_blank");
                    }
                    if ("file_details" in returnArray) {
                        fileDetails = returnArray['file_details'];
                    }
                    $("div.dropzone").dropzone({
                        url: scriptFilename + "?url_action=upload_file", queuecomplete: function () {
                            getFolderContents();
                        }
                    });
                });
                return false;
            });
            $(document).on("tap click", ".folder-row", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=change_folder&file_folder_id=" + $(this).data("file_folder_id"), function(returnArray) {
                    if ("folder_contents" in returnArray) {
                        $("#folder_contents").html(returnArray['folder_contents']);
                        $("a[href*='download.php']").attr("target", "_blank");
                    }
                    if ("file_details" in returnArray) {
                        fileDetails = returnArray['file_details'];
                    }
                    $("div.dropzone").dropzone({
                        url: scriptFilename + "?url_action=upload_file", queuecomplete: function () {
                            getFolderContents();
                        }
                    });
                });
            });
            getFolderContents();
        </script>
		<?php
	}

	function hiddenElements() {
		?>
        <div id="_edit_file_dialog" class="dialog-box">
            <form id="_edit_file" enctype='multipart/form-data'>
                <input type="hidden" id="file_id" name="file_id"/>
                <input type="hidden" id="file_code" name="file_code"/>
                <div class="basic-form-line" id="_description_row">
                    <label for="description" class="required-label">Filename</label>
                    <input type="text" class="validate[required]" size="40" maxlength="255" id="description" name="description"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_file_code_row">
                    <label for="file_code">Code</label>
                    <input type="text" class="code-value uppercase validate[]" size="40" maxlength="100" id="file_code" name="file_code"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_detailed_description_row">
                    <label for="detailed_description">Detailed Description</label>
                    <textarea id="detailed_description" name="detailed_description"></textarea>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_security_level_row">
                    <label for="security_level">Security Level</label>
                    <select id="security_level_id" name="security_level_id">
                        <option value="">[None]</option>
						<?php
						$resultSet = executeQuery("select * from security_levels where inactive = 0 order by sort_order,description");
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['security_level_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_accessibility_row">
                    <label for="accessibility">Accessibility</label>
                    <select id="accessibility" name="accessibility">
                        <option value="">Everyone</option>
                        <option value="1">Registered Users</option>
                        <option value="2">Administrators Only</option>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_user_group_id_row">
                    <label for="user_group_id">User Group</label>
                    <select id="user_group_id" name="user_group_id">
                        <option value="">[None]</option>
						<?php
						$resultSet = executeQuery("select * from user_groups where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['user_group_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_internal_use_only_row">
                    <label></label>
                    <input type="checkbox" id="internal_use_only" name="internal_use_only" value="1"><label class="checkbox-label" for="internal_use_only">Internal Use Only</label>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
                <div class="basic-form-line" id="_file_content_file_row">
                    <label for="file_content_file">Content</label>
                    <input class="validate[required]" type="file" id="file_content_file" name="file_content_file"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
            </form>
        </div>
        <div id="_move_file_dialog" class="dialog-box">
            <form id="_move_file">
                <input type="hidden" id="move_file_id" name="move_file_id"/>
                <input type="hidden" id="move_file_folder_id" name="move_file_folder_id"/>
                <table>
                    <tr>
                        <td class="field-label"><label for="new_file_folder_id">New Folder</label></td>
                        <td id="new_file_folder_id_cell"><select id="new_file_folder_id" name="new_file_folder_id">
                                <option value="">[Root Folder]</option>
								<?php
								$resultSet = executeQuery("select * from file_folders where client_id = ? and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
								while ($row = getNextRow($resultSet)) {
									?>
                                    <option value="<?= $row['file_folder_id'] ?>"><?= htmlText($row['description']) ?></option>
									<?php
								}
								?>
                            </select></td>
                    </tr>
                </table>
            </form>
        </div>
        <div id="_edit_file_folder_dialog" class="dialog-box">
            <form id="_edit_file_folder">
                <input type="hidden" id="file_folder_id" name="file_folder_id"/>

                <div class="basic-form-line" id="_file_folder_code_row">
                    <label for="file_folder_code">Folder Code</label>
                    <input type="text" class="code-value uppercase" size="40" maxlength="100" id="file_folder_code" name="file_folder_code"/>
                    <div class='basic-form-line-messages'><span class="help-label">Leave blank to auto generate</span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_folder_description_row">
                    <label for="folder_description">Folder Name</label>
                    <input type="text" class="validate[required]" size="40" maxlength="255" id="folder_description" name="folder_description"/>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_folder_detailed_description_row">
                    <label for="folder_detailed_description">Detailed Description</label>
                    <textarea id="folder_detailed_description" name="folder_detailed_description"></textarea>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_folder_user_group_id_row">
                    <label for="folder_user_group_id">User Group</label>
                    <select id="folder_user_group_id" name="folder_user_group_id">
                        <option value="">[None]</option>
						<?php
						$resultSet = executeQuery("select * from user_groups where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							?>
                            <option value="<?= $row['user_group_id'] ?>"><?= htmlText($row['description']) ?></option>
							<?php
						}
						?>
                    </select>
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
            </form>
        </div>
        <iframe id="_post_iframe" name="post_iframe"></iframe>
		<?php
		return true;
	}

	function internalCSS() {
		?>
        <style>
            #_edit_file_folder textarea {
                width: 400px;
            }

            #folder_contents {
                padding-top: 5px;
            }

            #folder_contents_table td {
                padding-left: 20px;
                padding-right: 20px;
                height: 30px;
                vertical-align: middle;
            }

            .folder-row {
                cursor: pointer;
            }

            #_edit_file_dialog textarea {
                width: 400px;
            }

            #controllers img {
                margin-right: 10px;
            }

            .spacer {
                width: 40px;
                display: inline-block;
            }

            th {
                text-align: center;
            }

            h3 {
                margin-top: 20px;
                margin-bottom: 5px;
            }

            #file_count {
                font-size: 12px;
                margin-bottom: 20px;
            }

            form {
                margin-top: 40px;
            }

            td.field-label {
                vertical-align: top;
                height: auto;
                padding: 0 10px 10px 0;
            }

            #search_filter {
                font-size: 14px;
                width: 200px;
                background-image: url('/images/search.png');
                background-repeat: no-repeat;
                background-position: 100% 3px;
                padding-right: 20px;
            }

            #folder_contents_table .fad {
                font-size: 20px;
                cursor: pointer;
                color: rgb(0, 124, 124);
            }

            #folder_contents_table .fad:hover {
                font-size: 20px;
                cursor: pointer;
                color: rgb(50, 50, 50);
            }

            #folder_contents_table td {
                position: relative;
            }

            .file-row input[type=text].link-text, .folder-row input[type=text].link-text {
                z-index: 500;
                display: block;
                max-width: none;
                border: 1px solid rgb(200, 200, 200);
                border-radius: 2px;
                padding: 6px 12px;
                width: 500px;
                position: absolute;
                right: 5px;
                top: 80%;
                font-size: 20px;
                background-color: rgb(255, 255, 255);
            }

        </style>
		<?php
	}
}

$pageObject = new FileManagerPage();
$pageObject->displayPage();
