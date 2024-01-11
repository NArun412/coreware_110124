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

$GLOBALS['gPageCode'] = "MOVEDOCUMENTSTOS3";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 1200000;

class MoveDocumentsToS3Page extends Page {
	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "convert_files":
                $moveDbFiles = $_GET['move_from_database_also'] == "true";
				$fileDirectory = getPreference("EXTERNAL_FILE_DIRECTORY");
				if (strtoupper(substr($fileDirectory, 0, 3)) != "S3:") {
					$returnArray['response'] = "New directory is not S3: " . $fileDirectory;
					ajaxResponse($returnArray);
					break;
				}
				$limit = "";
				$maximumConvert = 1000000;
				switch ($_GET['scope']) {
					case "convert_one":
						$limit = "limit 10";
						$maximumConvert = 1;
						break;
					case "convert_hundred":
						$limit = "limit 1000";
						$maximumConvert = 100;
						break;
					default:
						break;
				}
				$totalCount = 0;
                $resultSet = executeQuery("select file_id,description,os_filename,extension from files where (" .
                    ($moveDbFiles ? "os_filename is null or " : "os_filename is not null and ") . "os_filename not like 'S3:%') " . $limit);
				$fileCount = 0;
				while ($row = getNextRow($resultSet)) {
					if (empty($row['os_filename'])) {
						$fileContents = $row['file_content'];
					} else {
						$fileContents = getExternalFileContents($row['os_filename']);
					}
					if (empty($fileContents)) {
						continue;
					}
					putExternalFileContents($row['file_id'], $row['extension'], $fileContents);
					$fileCount++;
					$totalCount++;
					if ($totalCount >= $maximumConvert) {
						break;
					}
				}
				$imageCount = 0;
				if ($totalCount < $maximumConvert) {
                    $resultSet = executeQuery("select * from images where (" .
                        ($moveDbFiles ? "os_filename is null or " : "os_filename is not null and ") . "os_filename not like 'S3:%') " . $limit);
					while ($row = getNextRow($resultSet)) {
						if (empty($row['os_filename'])) {
							$fileContents = $row['file_content'];
						} else {
							$fileContents = getExternalImageContents($row['os_filename']);
						}
						if (empty($fileContents)) {
							continue;
						}
						putExternalImageContents($row['image_id'], $row['extension'], $fileContents);
						$imageCount++;
						$maximumConvert++;
						if ($totalCount >= $maximumConvert) {
							break;
						}
					}
				}
				$returnArray['response'] = $fileCount . " files converted. " . $imageCount . " images converted.";
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#convert_all,#convert_one,#convert_hundred", function () {
                $("#response").html("");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=convert_files&scope=" + $(this).attr("id")
                    + "&move_from_database_also=" + $("#move_from_database_also").prop("checked"), function(returnArray) {
                    if ("response" in returnArray) {
                        $("#response").html(returnArray['response']);
                    }
                });
            });
        </script>
		<?php
	}

	function mainContent() {
		echo $this->iPageData['content'];
		echo "<h3>Files</h3>";
		$resultSet = executeQuery("select substring(os_filename,1,10) as prefix,count(*) from files group by prefix");
		$fileCount = 0;
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['prefix']) && !startsWith($row['prefix'],"S3:")) {
				$fileCount += $row['count(*)'];
			}
			echo "<p>" . (empty($row['prefix']) ? "Stored in DB" : $row['prefix']) . ": " . $row['count(*)'] . "</p>";
		}
		echo "<h3>Images</h3>";
		$resultSet = executeQuery("select substring(os_filename,1,10) as prefix,count(*) from images group by prefix");
		$imageCount = 0;
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['prefix']) && !startsWith($row['prefix'],"S3:")) {
				$imageCount = $row['count(*)'];
			}
			echo "<p>" . (empty($row['prefix']) ? "Stored in DB" : $row['prefix']) . ": " . $row['count(*)'] . "</p>";
		}
		?>
        <p><?= $fileCount ?> files in file system found to move.</p>
        <p><?= $imageCount ?> images in file system found to move.</p>

        <p><input type="checkbox" id="move_from_database_also" value="1"> <label for="move_from_database_also">Move files and images in Database also</label></p>
        <p class="red-text" id="response"></p>
        <p>
            <button id="convert_all">Convert All</button>
            <button id="convert_one">Convert One</button>
            <button id="convert_hundred">Convert One Hundred</button>
        </p>
		<?php
		return true;
	}

}

$pageObject = new MoveDocumentsToS3Page();
$pageObject->displayPage();
