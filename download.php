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

$GLOBALS['gPageCode'] = "DOWNLOAD";
require_once "shared/startup.inc";

$fileId = $_GET['id'];
if (empty($fileId)) {
	$fileId = $_GET['file_id'];
}
if (empty($_GET['code']) && !empty($_GET['component_code'])) {
	$_GET['code'] = $_GET['component_code'];
}
if (!empty($_GET['code'])) {
	$fileId = getFieldFromId("file_id", "files", "file_code", $_GET['code']);
}
if (!empty($_GET['media_audio'])) {
	$fileId = getFieldFromId("audio_file_id", "media", "video_identifier", $_GET['media_audio'], "audio_file_id is not null");
}
if (!empty($_GET['media_powerpoint'])) {
	$fileId = getFieldFromId("powerpoint_file_id", "media", "video_identifier", $_GET['media_powerpoint'], "powerpoint_file_id is not null");
}
if (!empty($_GET['media_notes'])) {
	$fileId = getFieldFromId("notes_file_id", "media", "video_identifier", $_GET['media_notes'], "notes_file_id is not null");
}

$resultSet = executeQuery("select * from files where file_id = ? and client_id = ?", $fileId, $GLOBALS['gClientId']);
if (!$fileRow = getNextRow($resultSet)) {
	header("Location: /index.php");
	exit;
}
if (isset($GLOBALS['gMaximumUploadFileSize']) && $fileRow['file_size'] > $GLOBALS['gMaximumUploadFileSize']) {
	header("Content-Type: application/octet-stream");
	header("Content-Disposition: attachment; filename=\"" . $fileRow['filename'] . "\"");
	header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	header('Pragma: public');
	echo "";
	exit;
}

$canAccessDocument = canAccessFile($fileId);
if (!$canAccessDocument) {
	if (!empty($_GET['connection_key'])) {
		$developerId = getFieldFromId("developer_id", "developers", "connection_key", $_GET['connection_key'], "full_access = 1 and contact_id in (select contact_id from contacts where client_id = ? or contact_id <= 10000)", getFieldFromId("client_id", "files", "file_id", $fileId));
		if (empty($developerId)) {
			if (FFL::fflFileIdExists($fileId)) {
				$apiMethodGroupId = getFieldFromId("api_method_group_id", "api_method_groups", "api_method_group_code", "DEALER_PRODUCT_CATALOG_FEED");
				$developerId = getFieldFromId("developer_id", "developer_api_method_groups", "api_method_group_id", $apiMethodGroupId,
					"developer_id in (select developer_id from developers where connection_key = ? and contact_id in (select contact_id from contacts where client_id = ?))", $_GET['connection_key'],
					getFieldFromId("client_id", "files", "file_id", $fileId));
			}
		}
		if (!empty($developerId)) {
			$canAccessDocument = true;
		} elseif (!$GLOBALS['gLoggedIn']) {
			$developerRow = getRowFromId("developers", "connection_key", $_GET['connection_key'], "inactive = 0");
			$userId = $developerRow['user_id'] ?: Contact::getContactUserId($developerRow['contact_id']);
			if (!empty($userId)) {
				login($userId, false);
				$canAccessDocument = canAccessFile($fileId);
				logout();
			}
		}
	}
}

if (file_exists($GLOBALS['gDocumentRoot'] . "/download.inc")) {
	include_once("download.inc");
}

if (!empty($_GET['file_id_only'])) {
	if ($canAccessDocument) {
		echo $fileId;
	} else {
		echo "";
	}
	exit;
}
if (!$canAccessDocument && !$GLOBALS['gLoggedIn']) {
	$pageId = getFieldFromId("page_id", "pages", "link_name", "login");
	if (empty($pageId)) {
		header("Location: /loginform.php?url=%2Fdownload.php%3Fid%3D" . $fileId);
	} else {
		header("Location: /login?url=%2Fdownload.php%3Fid%3D" . $fileId);
	}
	exit;
}

if ($canAccessDocument) {
	if (!$GLOBALS['gDevelopmentServer'] && !$GLOBALS['gInternalConnection']) {
		executeQuery("insert into download_log (file_id,ip_address,user_id) values (?,?,?)", $fileRow['file_id'], $_SERVER['REMOTE_ADDR'], $GLOBALS['gUserId']);
	}
	executeQuery("update files set access_count = access_count + 1 where file_id = ?", $fileRow['file_id']);
	if (empty($_GET['force_download']) || isMobile()) {
		if ($fileRow['extension'] == "pdf" && !empty($_GET['pdftojpg'])) {
			$pdfFilename = getRandomString(10) . ".pdf";
			$jpgFilename = getRandomString(10) . ".jpg";
			file_put_contents($GLOBALS['gDocumentRoot'] . "/cache/" . $pdfFilename, $fileRow['file_content']);
			try {
				shell_exec("/usr/bin/pdftoppm -jpeg " . $GLOBALS['gDocumentRoot'] . "/cache/" . $pdfFilename . ">" . $GLOBALS['gDocumentRoot'] . "/cache/" . $jpgFilename);
				$fileRow['file_content'] = file_get_contents($GLOBALS['gDocumentRoot'] . "/cache/" . $jpgFilename);
				$fileRow['extension'] = "jpg";
				unlink($GLOBALS['gDocumentRoot'] . "/cache/" . $pdfFilename);
				unlink($GLOBALS['gDocumentRoot'] . "/cache/" . $jpgFilename);
			} catch (Exception $e) {
			}
		}
		$thisMime = "text/plain";
		foreach ($GLOBALS['gMimeTypes'] as $mimeType => $extension) {
			if ($extension == $fileRow['extension']) {
				$thisMime = $mimeType;
				break;
			}
		}

		header("Content-Type: " . $thisMime);
		if ($fileRow['extension'] == 'pdf') {
			header("Content-Disposition: inline; filename=\"" . $fileRow['filename'] . "\"");
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
		} elseif ($fileRow['extension'] != "html" && $fileRow['extension'] != 'jpg') {
			header("Content-Disposition: attachment; filename=\"" . $fileRow['filename'] . "\"");
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
		}
	} else {
		header("Content-Type: application/octet-stream");
		header("Content-Disposition: attachment; filename=\"" . $fileRow['filename'] . "\"");
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');
	}

	if (empty($fileRow['os_filename'])) {
		header("Content-Length: " . strlen($fileRow['file_content']));
		if (strlen($fileRow['file_content']) > 0 && strlen($fileRow['file_content']) != $fileRow['file_size']) {
			executeQuery("update files set file_size = ? where file_id = ?", strlen($fileRow['file_content']), $fileRow['file_id']);
		}
		if (isset($GLOBALS['gMaximumUploadFileSize']) && strlen($fileRow['file_content']) > $GLOBALS['gMaximumUploadFileSize']) {
			$fileRow['file_content'] = "";
		}
		shutdown();
		echo $fileRow['file_content'];
	} else {
		$fileContents = getExternalFileContents($fileRow['os_filename']);
		header("Content-Length: " . strlen($fileContents));
		if (strlen($fileContents) > 0 && strlen($fileContents) != $fileRow['file_size']) {
			executeQuery("update files set file_size = ? where file_id = ?", strlen($fileContents), $fileRow['file_id']);
		}
		if (isset($GLOBALS['gMaximumUploadFileSize']) && strlen($fileContents) > $GLOBALS['gMaximumUploadFileSize']) {
			$fileContents = "";
		}
		shutdown();
		echo $fileContents;
	}
	exit;
}

header("Location: /");
