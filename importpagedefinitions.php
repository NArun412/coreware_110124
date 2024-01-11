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

$GLOBALS['gPageCode'] = "IMPORTPAGEDEFINITIONS";
require_once "shared/startup.inc";

class ThisPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "import_pages":
				$definitionArray = json_decode($_POST['page_definitions_json'], true);
				if (empty($definitionArray)) {
					$returnArray['error_message'] = "Invalid JSON object";
					ajaxResponse($returnArray);
					break;
				}
				$errorFound = false;
				ob_start();
				?>
                <h2>Verify all changes and click Update</h2>
				<?php
				foreach ($definitionArray as $newPageInfo) {
					if (empty($newPageInfo['page_code'])) {
						$returnArray['error_message'] = "Invalid JSON object";
						ajaxResponse($returnArray);
						break;
					}
					?>
                    <h3><?= $newPageInfo['page_code'] . " - " . $newPageInfo['description'] ?></h3>
					<?php
					if (!empty($newPageInfo['subsystem'])) {
						$subsystemId = getFieldFromId("subsystem_id", "subsystems", "description", $newPageInfo['subsystem']);
						if (empty($subsystemId)) {
							?>
                            <p class="error-message">Subsystem does not exist: <?= $newPageInfo['subsystem'] ?>.</p>
							<?php
							$errorFound = true;
							continue;
						}
					}
					$templateId = getFieldFromId("template_id", "templates", "template_code", $newPageInfo['template_code']);
					if (empty($templateId)) {
						$templateId = getFieldFromId("template_id", "templates", "template_code", $newPageInfo['template_code'],
							"client_id = ?", $GLOBALS['gDefaultClientId']);
					}
					if (empty($templateId)) {
						?>
                        <p class="error-message">Template does not exist: <?= $newPageInfo['template_code'] ?>.</p>
						<?php
						$errorFound = true;
						continue;
					}
					$existingPageRow = getRowFromId("pages", "page_code", $newPageInfo['page_code']);
					if (empty($existingPageRow)) {
						?>
                        <p class="info-message">Page does not exist. Will be created.</p>
						<?php
					} else {
						?>
                        <p class="info-message">Changes to page will be made.</p>
						<?php
					}
				}
				$returnArray['error_found'] = $errorFound;
				$returnArray['results'] = ob_get_clean();
				$returnArray['hash_value'] = md5($_POST['page_definitions_json']);
				ajaxResponse($returnArray);
				break;
			case "update_pages":
				$hashValue = md5($_POST['page_definitions_json']);
				if ($hashValue != $_POST['hash_value']) {
					$returnArray['error_message'] = "JSON Object changed and must be revalidated.";
					ajaxResponse($returnArray);
					break;
				}
				$definitionArray = json_decode($_POST['page_definitions_json'], true);
				if (empty($definitionArray)) {
					$returnArray['error_message'] = "Invalid JSON object";
					ajaxResponse($returnArray);
					break;
				}
				$errorFound = false;
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				ob_start();
				foreach ($definitionArray as $newPageInfo) {
					?>
                    <p class="info-message">Updating Page '<?= $newPageInfo['page_code'] . " - " . $newPageInfo['description'] ?>'.</p>
					<?php
					$subsystemId = "";
					if (!empty($newPageInfo['subsystem'])) {
						$subsystemId = getFieldFromId("subsystem_id", "subsystems", "description", $newPageInfo['subsystem']);
						if (empty($subsystemId)) {
							?>
                            <p class="error-message">Subsystem does not exist: <?= $newPageInfo['subsystem'] ?>.</p>
							<?php
							$errorFound = true;
							break;
						}
					}
					$templateId = getFieldFromId("template_id", "templates", "template_code", $newPageInfo['template_code']);
					if (empty($templateId)) {
						$templateId = getFieldFromId("template_id", "templates", "template_code", $newPageInfo['template_code'],
							"client_id = ?", $GLOBALS['gDefaultClientId']);
					}
					if (empty($templateId)) {
						?>
                        <p class="error-message">Template does not exist: <?= $newPageInfo['template_code'] ?>.</p>
						<?php
						$errorFound = true;
						break;
					}
					$existingPageRow = getRowFromId("pages", "page_code", $newPageInfo['page_code']);
					$newPageInfo['client_id'] = $GLOBALS['gClientId'];
					$newPageInfo['template_id'] = $templateId;
					$newPageInfo['subsystem_id'] = $subsystemId;
					unset($newPageInfo['analytics_code_chunk_id']);
					unset($newPageInfo['version']);
					$pageDataSource = new DataSource("pages");
					$pageDataSource->setSaveOnlyPresent(true);
					if (empty($existingPageRow)) {
						$newPageInfo['creator_user_id'] = $GLOBALS['gUserId'];
						$newPageInfo['date_created'] = date("Y-m-d");
						$primaryId = "";
					} else {
						unset($newPageInfo['creator_user_id']);
						$primaryId = $existingPageRow['page_id'];
					}
					if (!($pageId = $pageDataSource->saveRecord(array("name_values" => $newPageInfo, "primary_id" => $primaryId)))) {
						echo "<p>" . $pageDataSource->getErrorMessage() . "</p>";
						$errorFound = true;
						break;
					}

					$updateSet = executeQuery("delete from page_access where client_type_id is null and user_type_id is null and user_group_id is null and page_id = ?", $pageId);
					foreach ($newPageInfo['page_access'] as $pageData) {
						$pageDataSource = new DataSource("page_access");
						$pageData['page_id'] = $pageId;
						unset($pageData['version']);
						if (!($pageDataId = $pageDataSource->saveRecord(array("name_values" => $pageData, "primary_id" => "")))) {
							echo "<p>" . $pageDataSource->getErrorMessage() . "</p>";
							$errorFound = true;
							break 2;
						}
					}

					$updateSet = executeQuery("delete from page_data where image_id is null and file_id is null and page_id = ?", $pageId);
					foreach ($newPageInfo['page_data'] as $pageData) {
						$pageDataSource = new DataSource("page_data");
						$pageData['page_id'] = $pageId;
						$templateDataId = getFieldFromId("template_data_id", "template_data", "data_name", $pageData['template_data_name']);
						if (empty($templateDataId)) {
							echo "<p>Template data not found: " . $pageData['template_data_name'] . "</p>";
							$errorFound = true;
							break 2;
						}
						$pageData['template_data_id'] = $templateDataId;
						unset($pageData['version']);
						if (!($pageDataId = $pageDataSource->saveRecord(array("name_values" => $pageData, "primary_id" => "")))) {
							echo "<p>" . $pageDataSource->getErrorMessage() . "</p>";
							$errorFound = true;
							break 2;
						}
					}

					$subtables = array("page_controls", "page_functions", "page_meta_tags", "page_notifications", "page_text_chunks");

					foreach ($subtables as $tableName) {
						$updateSet = executeQuery("delete from " . $tableName . " where page_id = ?", $pageId);
						foreach ($newPageInfo[$tableName] as $pageData) {
							$pageDataSource = new DataSource($tableName);
							$pageData['page_id'] = $pageId;
							unset($pageData['version']);
							if (!($pageDataId = $pageDataSource->saveRecord(array("name_values" => $pageData, "primary_id" => "")))) {
								echo "<p>" . $pageDataSource->getErrorMessage() . "</p>";
								$errorFound = true;
								break 3;
							}
						}
					}

					removeCachedData($GLOBALS['gPrimaryDatabase']->getName() . "-page_row_by_id", $pageId, true);
					removeCachedData($GLOBALS['gPrimaryDatabase']->getName() . "-page_row_by_code", $newPageInfo['page_code'], true);
					removeCachedData("initialized_template_page_data", $pageId);
					removeCachedData("page_contents", $newPageInfo['page_code']);
				}
				if ($errorFound) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				} else {
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
				}
				$returnArray['error_found'] = $errorFound;
				$returnArray['results'] = ob_get_clean();
				removeCachedData("all_page_codes", "");
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#import_pages").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_pages", $("#_edit_form").serialize(), function (returnArray) {
                    if ("results" in returnArray) {
                        $("#import_pages").hide();
                        $("#update_pages").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#update_pages").show();
                            $("#hash_value").val(returnArray['hash_value']);
                        }
                        $("#page_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#update_pages").click(function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_pages", $("#_edit_form").serialize(), function (returnArray) {
                    if ("results" in returnArray) {
                        $("#import_pages").hide();
                        $("#update_pages").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#page_definitions_json").val("");
                            $("#reenter_json").show();
                        }
                        $("#page_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#reenter_json").click(function () {
                $("#reenter_json").hide();
                $("#update_pages").hide();
                $("#import_pages").show();
                $("#page_definitions").show();
                $("#results").hide();
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #update_pages {
                display: none;
            }

            #reenter_json {
                display: none;
            }

            #_edit_form button {
                margin-right: 20px;
            }

            #results {
                display: none;
            }

            #page_definitions label {
                display: block;
                margin: 10px 0;
            }

            #page_definitions_json {
                width: 1200px;
                height: 600px;
            }
        </style>
		<?php
	}

	function mainContent() {
		?>
        <form id="_edit_form">
            <input type="hidden" id="hash_value" name="hash_value">
            <p>
                <button id="import_pages">Import Pages</button>
                <button id="reenter_json">Re-enter JSON</button>
                <button id="update_pages">Update Pages</button>
            </p>
            <div id="page_definitions">
                <label>Page Definitions JSON</label>
                <textarea id="page_definitions_json" name="page_definitions_json"></textarea>
            </div>
            <div id="results">
            </div>
        </form>
		<?php
		return true;
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
