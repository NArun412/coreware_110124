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

$runEnvironment = php_sapi_name();
$GLOBALS['gCommandLine'] = ($runEnvironment == "cli");
$GLOBALS['gApcuEnabled'] = !$GLOBALS['gCommandLine'] && ((extension_loaded('apc') && ini_get('apc.enabled')) || (extension_loaded('apcu') && ini_get('apc.enabled')));

if (!empty($_GET['id'])) {
    $_GET['image_id'] = $_GET['id'];
}

$apcuKey = "";
if (empty($_GET['no_cache']) && empty($_GET['force_download']) && empty($_GET['type'])) {
    if (!empty($_GET['image_id'])) {
        $apcuKey = "ALL|image_id_filename|" . $_GET['image_id'] . ":" . ($_GET['tiny'] ? "1" : "") . ":" . ($_GET['small'] ? "1" : "") . ":" . ($_GET['thumb'] ? "1" : "") . ":" . $_GET['image_type'];
    } else if (!empty($_GET['code'])) {
        $apcuKey = "ALL|" . $_SERVER['HTTP_HOST'] . ":image_code_filename|" . strtoupper($_GET['code']) . ":" . ($_GET['tiny'] ? "1" : "") . ":" . ($_GET['small'] ? "1" : "") . ":" . ($_GET['thumb'] ? "1" : "") . ":" . $_GET['image_type'];
    }
}
if ($GLOBALS['gApcuEnabled'] && !empty($apcuKey)) {
    if (apcu_exists($apcuKey)) {
        $filename = apcu_fetch($apcuKey);
        if (!empty($filename)) {
            $imageData = file_get_contents($filename, true);
            if (!empty($imageData)) {
                header("Content-Type: image/jpeg");
                echo $imageData;
                exit;
            }
        }
    }
}

$GLOBALS['gPageCode'] = "GETIMAGE";
$GLOBALS['gPreemptivePage'] = true;
require_once "shared/startup.inc";

if (empty($_GET['code']) && !empty($_GET['component_code'])) {
    $_GET['code'] = $_GET['component_code'];
}
$filename = false;
if (!empty($_GET['code'])) {
    $_GET['image_id'] = getFieldFromId("image_id", "images", "image_code", $_GET['code']);
    if (empty($_GET['image_id'])) {
	    if ($_GET['code'] == "header_logo") {
		    $filename = $GLOBALS['gDocumentRoot'] . "/images/coreware.png";
		    setCachedData($_SERVER['HTTP_HOST'] . ":image_code_filename", strtoupper($_GET['code']) . ":" . ($_GET['tiny'] ? "1" : "") . ":" . ($_GET['small'] ? "1" : "") . ":" . ($_GET['thumb'] ? "1" : "") . ":" . $_GET['image_type'], $filename, 24, true);
		    if (file_exists($filename)) {
			    header("Content-Type: image/jpeg");
			    header("Content-Length: " . filesize($filename));
			    header("Cache-Control: max-age=2592000");
			    header('Expires: ' . gmdate('D, d M Y H:i:s', strtotime('+1 month')) . ' GMT');
			    header('Pragma: cache');
			    readfile($filename);
			    exit;
		    }
	    } else {
		    $hitCount = getCachedData("invalid_image", $_SERVER['REMOTE_ADDR']);
		    $countExpiration = getCachedData("invalid_image_count_expiration", $_SERVER['REMOTE_ADDR']);
		    if (empty($hitCount) || time() > $countExpiration) {
			    $hitCount = 0;
			    $countExpiration = time() + 360; // check for invalid accesses in the next 6 minutes
			    setCachedData("invalid_image_count_expiration", $_SERVER['REMOTE_ADDR'], $countExpiration, .1);
		    }
		    $hitCount++;

		    # if more than 100 invalid image codes in 6 minutes, probably a DDOS attack

		    setCachedData("invalid_image", $_SERVER['REMOTE_ADDR'], $hitCount, .1);
		    if ($hitCount > 100 && empty($GLOBALS['gUserRow']['administrator_flag'])) {
			    blacklistIpAddress($_SERVER['REMOTE_ADDR'], "Too many invalid image accesses");
			    exit;
		    }
	    }
    }
}
$_GET['image_id'] = str_replace("/", "", $_GET['image_id']);
$filename = getImageFilename($_GET['image_id']);
$imageData = "";
if (!empty($filename)) {
    if (!empty($_GET['small']) || !empty($_GET['tiny'])) {
        $filename = $GLOBALS['gDocumentRoot'] . str_replace("-full-", "-small-", $filename);
    } else if (!empty($_GET['thumb'])) {
        $filename = $GLOBALS['gDocumentRoot'] . str_replace("-full-", "-thumbnail-", $filename);
    } else if (!empty($_GET['image_type'])) {
        $filename = $GLOBALS['gDocumentRoot'] . str_replace("-full-", "-" . strtolower(makeCode($_GET['image_type'])) . "-", $filename);
    } else {
        $filename = $GLOBALS['gDocumentRoot'] . $filename;
    }
	$imageData = file_get_contents($filename);
}
$resultSet = executeQuery("select * from images where image_id = ? and client_id = ?", $_GET['image_id'], $GLOBALS['gClientId']);
if ($row = getNextRow($resultSet)) {
    if (empty($row['security_level']) && empty($row['user_group_id'])) {
        setCachedData($_SERVER['HTTP_HOST'] . ":image_code_filename", strtoupper($row['image_code']) . ":" . ($_GET['tiny'] ? "1" : "") . ":" . ($_GET['small'] ? "1" : "") . ":" . ($_GET['thumb'] ? "1" : "") . ":" . $_GET['image_type'], $filename, 24, true);
        setCachedData("image_id_filename", $row['image_id'] . ":" . ($_GET['tiny'] ? "1" : "") . ":" . ($_GET['small'] ? "1" : "") . ":" . ($_GET['thumb'] ? "1" : "") . ":" . $_GET['image_type'], $filename, 24, true);
    }
    if (!empty($imageData)) {
	    if (empty($_GET['force_download'])) {
		    header("Content-Type: image/jpeg");
	    } else {
		    header("Content-Type: application/octet-stream");
		    header("Content-Disposition: attachment; filename=\"" . (empty($row['image_code']) ? "image" . $_GET['image_id'] : strtolower($row['image_code'])) . "." . $row['extension'] . "\"");
		    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		    header('Pragma: public');
	    }
        echo $imageData;
        exit;
    }
    $accessible = true;
    if (!empty($row['user_group_id'])) {
        if (!$GLOBALS['gLoggedIn']) {
            if (empty($_GET['hash'])) {
                $accessible = false;
            } else {
                $userId = getFieldFromId("user_id", "users", "client_id", $GLOBALS['gClientId'], "contact_id in (select contact_id from contacts where hash_code = ?)", $_GET['hash']);
                if (empty($userId) || !isInUserGroup($userId, $row['user_group_id'])) {
                    $accessible = false;
                }
            }
        } else {
            if (!$GLOBALS['gUserRow']['superuser_flag'] && !isInUserGroup($GLOBALS['gUserId'], $row['user_group_id'])) {
                $accessible = false;
            }
        }
    }
    if (!$accessible) {
	    if (empty($_GET['force_download'])) {
		    header("Content-Type: image/jpeg");
	    } else {
		    header("Content-Type: application/octet-stream");
		    header("Content-Disposition: attachment; filename=\"" . (empty($row['image_code']) ? "image" . $_GET['image_id'] : strtolower($row['image_code'])) . "." . $row['extension'] . "\"");
		    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		    header('Pragma: public');
	    }
        $imageData = file_get_contents("images/empty.jpg", true);
        echo $imageData;
        exit;
    }
    if (!empty($row['security_level'])) {
        executeQuery("update images set hash_code = null where image_id = ?", $row['image_id']);
    }
    $imageData = $row['file_content'];
    if (empty($imageData) && !empty($row['os_filename'])) {
        $imageData = getExternalImageContents($row['os_filename']);
    }
    if (empty($imageData) && !empty($row['remote_storage'])) {
        $remoteImageTypeCode = strtoupper($_GET['remote_image_type']);
        if (empty($remoteImageTypeCode)) {
            $remoteImageTypeCode = "FULL";
        }
        $linkUrl = getFieldFromId("link_url", "remote_image_type_data", "image_id", $row['image_id'],
            "remote_image_type_id = (select remote_image_type_id from remote_image_types where remote_image_type_code = ? and client_id = ?)", $remoteImageTypeCode, $row['client_id']);
        if (empty($linkUrl)) {
            $linkUrl = getFieldFromId("link_url", "remote_image_type_data", "image_id", $row['image_id']);
        }
        if (!empty($linkUrl)) {
            $linkUrlPrefix = getPreference((empty($_GET['secure']) ? "" : "SECURE_") . "REMOTE_IMAGE_URL");
            if (!empty($linkUrlPrefix) && substr($linkUrl, 0, 1) != "/" && substr($linkUrlPrefix, -1) != "/") {
                $linkUrlPrefix .= "/";
            }
            $linkUrl = $linkUrlPrefix . $linkUrl;
            $imageData = file_get_contents($linkUrl);
        }
    }
}
if (empty($imageData)) {
    $imageData = file_get_contents("images/empty.jpg", true);
}
if (empty($row['extension'])) {
    $row['extension'] = "jpg";
}
if (empty($_GET['force_download'])) {
    if ($_GET['type'] == "pdf") {
        header("Content-Type: application/pdf");
    } else {
        header("Content-Type: image/jpeg");
        header("Content-Length: " . strlen($imageData));
        header("Cache-Control: max-age=2592000");
        header('Expires: ' . gmdate('D, d M Y H:i:s', strtotime('+1 month')) . ' GMT');
        header('Pragma: cache');
    }
} else {
    header("Content-Type: application/octet-stream");
    header("Content-Disposition: attachment; filename=\"" . (empty($row['image_code']) ? "image" . $_GET['image_id'] : strtolower($row['image_code'])) . "." . $row['extension'] . "\"");
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
}
echo $imageData;
