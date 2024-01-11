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

$GLOBALS['gPageCode'] = "IMPORTEMAILDEFINITIONS";
require_once "shared/startup.inc";

class ImportEmailDefinitionsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "import_emails":
				$definitionArray = json_decode($_POST['email_definitions_json'], true);
				if (empty($definitionArray)) {
					$returnArray['error_message'] = "Invalid JSON object";
					ajaxResponse($returnArray);
					break;
				}
				ob_start();
				?>
                <h2>Verify all changes and click Update</h2>
				<?php
				foreach ($definitionArray as $newEmailInfo) {
					if (empty($newEmailInfo['email_code'])) {
						$returnArray['error_message'] = "Invalid JSON object";
						ajaxResponse($returnArray);
						break;
					}
					?>
                    <h3><?= $newEmailInfo['email_code'] . " - " . $newEmailInfo['description'] ?></h3>
					<?php
					$existingEmailRow = getRowFromId("emails", "email_code", $newEmailInfo['email_code']);
					if (empty($existingEmailRow)) {
						?>
                        <p class="info-message">Email does not exist. Will be created.</p>
						<?php
					} else {
						?>
                        <p class="info-message">Changes to email will be made.</p>
						<?php
					}
				}
				$returnArray['results'] = ob_get_clean();
				$returnArray['hash_value'] = md5($_POST['email_definitions_json']);
				ajaxResponse($returnArray);
				break;
			case "update_emails":
				$hashValue = md5($_POST['email_definitions_json']);
				if ($hashValue != $_POST['hash_value']) {
					$returnArray['error_message'] = "JSON Object changed and must be revalidated.";
					ajaxResponse($returnArray);
					break;
				}
				$definitionArray = json_decode($_POST['email_definitions_json'], true);
				if (empty($definitionArray)) {
					$returnArray['error_message'] = "Invalid JSON object";
					ajaxResponse($returnArray);
					break;
				}
				$errorFound = false;
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				ob_start();
				foreach ($definitionArray as $newEmailInfo) {
					?>
                    <p class="info-message">Updating Email '<?= $newEmailInfo['email_code'] . " - " . $newEmailInfo['description'] ?>'.</p>
					<?php

					$existingEmailRow = getRowFromId("emails", "email_code", $newEmailInfo['email_code']);
					$newEmailInfo['client_id'] = $GLOBALS['gClientId'];
					unset($newEmailInfo['version']);

					$emailDataSource = new DataSource("emails");
					$emailDataSource->setSaveOnlyPresent(true);

					// Foreign key references
					$newEmailInfo['email_credential_id'] = getFieldFromId("email_credential_id", "email_credentials",
						"email_credential_code", $newEmailInfo['email_credential_code']);

					$primaryId = empty($existingEmailRow) ? "" : $existingEmailRow['email_id'];
					if (!($emailId = $emailDataSource->saveRecord(array("name_values" => $newEmailInfo, "primary_id" => $primaryId)))) {
						echo "<p class='error-message'>" . $emailDataSource->getErrorMessage() . "</p>";
						$errorFound = true;
						break;
					}

					executeQuery("delete from email_copies where email_id = ?", $emailId);
					foreach ($newEmailInfo['email_copies'] as $emailCopy) {
						$emailCopyDataSource = new DataSource("email_copies");
						$emailCopy['email_id'] = $emailId;
						unset($emailCopy['version']);
						if (!($emailCopyDataSource->saveRecord(array("name_values" => $emailCopy, "primary_id" => "")))) {
							echo "<p class='error-message'>" . $emailCopyDataSource->getErrorMessage() . "</p>";
							$errorFound = true;
							break 2;
						}
					}
				}
				if ($errorFound) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				} else {
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
				}
				$returnArray['error_found'] = $errorFound;
				$returnArray['results'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#import_emails").on("click", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_emails", $("#_edit_form").serialize(), function (returnArray) {
                    if ("results" in returnArray) {
                        $("#import_emails").hide();
                        $("#update_emails").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#update_emails").show();
                            $("#hash_value").val(returnArray['hash_value']);
                        }
                        $("#email_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#update_emails").on("click", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_emails", $("#_edit_form").serialize(), function (returnArray) {
                    if ("results" in returnArray) {
                        $("#import_emails").hide();
                        $("#update_emails").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#email_definitions_json").val("");
                            $("#reenter_json").show();
                        }
                        $("#email_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#reenter_json").on("click", function () {
                $("#reenter_json").hide();
                $("#update_emails").hide();
                $("#import_emails").show();
                $("#email_definitions").show();
                $("#results").hide();
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #update_emails {
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

            #email_definitions label {
                display: block;
                margin: 10px 0;
            }

            #email_definitions_json {
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
                <button id="import_emails">Import Emails</button>
                <button id="reenter_json">Re-enter JSON</button>
                <button id="update_emails">Update Emails</button>
            </p>
            <div id="email_definitions">
                <label>Email Definitions JSON</label>
                <textarea id="email_definitions_json" name="email_definitions_json" aria-label="Email definitions"></textarea>
            </div>
            <div id="results">
            </div>
        </form>
		<?php
		return true;
	}
}

$pageObject = new ImportEmailDefinitionsPage();
$pageObject->displayPage();
