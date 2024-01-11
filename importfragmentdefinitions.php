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

$GLOBALS['gPageCode'] = "IMPORTFRAGMENTDEFINITIONS";
require_once "shared/startup.inc";

class ImportFragmentDefinitionsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "import_fragments":
				$definitionArray = json_decode($_POST['fragment_definitions_json'], true);
				if (empty($definitionArray)) {
					$returnArray['error_message'] = "Invalid JSON object";
					ajaxResponse($returnArray);
					break;
				}
				ob_start();
				?>
                <h2>Verify all changes and click Update</h2>
				<?php
				foreach ($definitionArray as $newFragmentInfo) {
					if (empty($newFragmentInfo['fragment_code'])) {
						$returnArray['error_message'] = "Invalid JSON object";
						ajaxResponse($returnArray);
						break;
					}
					?>
                    <h3><?= $newFragmentInfo['fragment_code'] . " - " . $newFragmentInfo['description'] ?></h3>
					<?php
					$existingFragmentRow = getRowFromId("fragments", "fragment_code", $newFragmentInfo['fragment_code']);
					if (empty($existingFragmentRow)) {
						?>
                        <p class="info-message">Fragment does not exist. Will be created.</p>
						<?php
					} else {
						?>
                        <p class="info-message">Changes to fragment will be made.</p>
						<?php
					}
				}
				$returnArray['results'] = ob_get_clean();
				$returnArray['hash_value'] = md5($_POST['fragment_definitions_json']);
				ajaxResponse($returnArray);
				break;
			case "update_fragments":
				$hashValue = md5($_POST['fragment_definitions_json']);
				if ($hashValue != $_POST['hash_value']) {
					$returnArray['error_message'] = "JSON Object changed and must be revalidated.";
					ajaxResponse($returnArray);
					break;
				}
				$definitionArray = json_decode($_POST['fragment_definitions_json'], true);
				if (empty($definitionArray)) {
					$returnArray['error_message'] = "Invalid JSON object";
					ajaxResponse($returnArray);
					break;
				}
				$errorFound = false;
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				ob_start();
				foreach ($definitionArray as $newFragmentInfo) {
					?>
                    <p class="info-message">Updating Fragment '<?= $newFragmentInfo['fragment_code'] . " - " . $newFragmentInfo['description'] ?>'.</p>
					<?php
					$existingFragmentRow = getRowFromId("fragments", "fragment_code", $newFragmentInfo['fragment_code']);
					$newFragmentInfo['client_id'] = $GLOBALS['gClientId'];
					unset($newFragmentInfo['version']);

					$fragmentDataSource = new DataSource("fragments");
					$fragmentDataSource->setSaveOnlyPresent(true);

					// Foreign key references
					$newFragmentInfo['fragment_type_id'] = getFieldFromId("fragment_type_id", "fragment_types",
						"fragment_type_code", $newFragmentInfo['fragment_type_code']);
					$newFragmentInfo['image_id'] = getFieldFromId("image_id", "images",
						"image_code", $newFragmentInfo['image_code']);

					$primaryId = empty($existingFragmentRow) ? "" : $existingFragmentRow['fragment_id'];
					if (!($fragmentDataSource->saveRecord(array("name_values" => $newFragmentInfo, "primary_id" => $primaryId)))) {
						echo "<p class='error-message'>" . $fragmentDataSource->getErrorMessage() . "</p>";
						$errorFound = true;
						break;
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
            $("#import_fragments").on("click", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_fragments", $("#_edit_form").serialize(), function (returnArray) {
                    if ("results" in returnArray) {
                        $("#import_fragments").hide();
                        $("#update_fragments").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#update_fragments").show();
                            $("#hash_value").val(returnArray['hash_value']);
                        }
                        $("#fragment_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#update_fragments").on("click", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_fragments", $("#_edit_form").serialize(), function (returnArray) {
                    if ("results" in returnArray) {
                        $("#import_fragments").hide();
                        $("#update_fragments").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#fragment_definitions_json").val("");
                            $("#reenter_json").show();
                        }
                        $("#fragment_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#reenter_json").on("click", function () {
                $("#reenter_json").hide();
                $("#update_fragments").hide();
                $("#import_fragments").show();
                $("#fragment_definitions").show();
                $("#results").hide();
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #update_fragments {
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

            #fragment_definitions label {
                display: block;
                margin: 10px 0;
            }

            #fragment_definitions_json {
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
                <button id="import_fragments">Import Fragments</button>
                <button id="reenter_json">Re-enter JSON</button>
                <button id="update_fragments">Update Fragments</button>
            </p>
            <div id="fragment_definitions">
                <label>Fragment Definitions JSON</label>
                <textarea id="fragment_definitions_json" name="fragment_definitions_json" aria-label="Fragment definitions"></textarea>
            </div>
            <div id="results">
            </div>
        </form>
		<?php
		return true;
	}
}

$pageObject = new ImportFragmentDefinitionsPage();
$pageObject->displayPage();
