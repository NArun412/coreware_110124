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

$GLOBALS['gPageCode'] = "IMPORTCUSTOMFIELDDEFINITIONS";
require_once "shared/startup.inc";

class ImportCustomFieldDefinitionsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "import_custom_fields":
				$definitionArray = json_decode($_POST['custom_field_definitions_json'], true);
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
				foreach ($definitionArray as $newCustomFieldInfo) {
					?>
					<h3><?= $newCustomFieldInfo['custom_field_code'] . " - " . $newCustomFieldInfo['description'] ?></h3>
					<?php
					$customFieldErrorFound = false;

					if (!empty($newCustomFieldInfo['custom_field_type_code'])) {
						$customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", $newCustomFieldInfo['custom_field_type_code']);
						if (empty($customFieldTypeId)) {
							?>
                            <p class="error-message">Custom field type does not exist: <?= $newCustomFieldInfo['custom_field_type_code'] ?>.</p>
							<?php
							$errorFound = true;
							$customFieldErrorFound = true;
						}
					}
					if (!empty($newCustomFieldInfo['user_group_code'])) {
						$userGroupId = getFieldFromId("user_group_id", "user_groups", "user_group_code", $newCustomFieldInfo['user_group_code']);
						if (empty($userGroupId)) {
							?>
							<p class="error-message">User group does not exist: <?= $newCustomFieldInfo['user_group_code'] ?>.</p>
							<?php
							$errorFound = true;
							$customFieldErrorFound = true;
						}
					}

					foreach ($newCustomFieldInfo['custom_field_group_links'] as $groupLinkData) {
						if (!empty($groupLinkData['custom_field_group_code'])) {
							$customFieldGroupId = getFieldFromId("custom_field_group_id", "custom_field_groups", "custom_field_group_code", $groupLinkData['custom_field_group_code']);
							if (empty($customFieldGroupId)) {
								?>
								<p class="error-message">Custom field group does not exist: <?= $groupLinkData['custom_field_group_code'] ?>.</p>
								<?php
								$errorFound = true;
								$customFieldErrorFound = true;
							}
						}
					}

					if (empty($customFieldErrorFound)) {
						$existingCustomFieldRow = getRowFromId("custom_fields", "custom_field_code", $newCustomFieldInfo['custom_field_code']);
						if (empty($existingCustomFieldRow)) {
							?>
							<p class="info-message">Custom field does not exist. Will be created.</p>
							<?php
						} else {
							?>
							<p class="info-message">Changes to custom field will be made.</p>
							<?php
						}
					}
				}
				$returnArray['error_found'] = $errorFound;
				$returnArray['results'] = ob_get_clean();
				$returnArray['hash_value'] = md5($_POST['custom_field_definitions_json']);
				ajaxResponse($returnArray);
				break;
			case "update_custom_fields":
				$hashValue = md5($_POST['custom_field_definitions_json']);
				if ($hashValue != $_POST['hash_value']) {
					$returnArray['error_message'] = "JSON Object changed and must be revalidated.";
					ajaxResponse($returnArray);
					break;
				}
				$definitionArray = json_decode($_POST['custom_field_definitions_json'], true);
				if (empty($definitionArray)) {
					$returnArray['error_message'] = "Invalid JSON object";
					ajaxResponse($returnArray);
					break;
				}
				$errorFound = false;
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				ob_start();

				foreach ($definitionArray as $newCustomFieldInfo) {
					if (empty($newCustomFieldInfo['custom_field_code'])) {
						$returnArray['error_message'] = "Invalid JSON object";
						ajaxResponse($returnArray);
						break;
					}
					?>
					<p class="info-message">Updating custom field '<?= $newCustomFieldInfo['custom_field_code'] . " - " . $newCustomFieldInfo['description'] ?>'.</p>
					<?php

					$existingCustomFieldRow = getRowFromId("custom_fields", "custom_field_code", $newCustomFieldInfo['custom_field_code']);
					$newCustomFieldInfo['client_id'] = $GLOBALS['gClientId'];

					// Foreign key references
					$newCustomFieldInfo['custom_field_type_id'] = getFieldFromId("custom_field_type_id", "custom_field_types",
						"custom_field_type_code", $newCustomFieldInfo['custom_field_type_code']);
					$newCustomFieldInfo['user_group_id'] = getFieldFromId("user_group_id", "user_groups",
						"user_group_code", $newCustomFieldInfo['user_group_code']);

					unset($newCustomFieldInfo['version']);

					if (empty($existingCustomFieldRow)) {
						$newCustomFieldInfo['date_created'] = date("Y-m-d");
						$primaryId = "";
					} else {
						unset($newCustomFieldInfo['user_id']);
						$primaryId = $existingCustomFieldRow['custom_field_id'];
					}

					$customFieldDataSource = new DataSource("custom_fields");
					$customFieldDataSource->setSaveOnlyPresent(true);

					if (!($customFieldId = $customFieldDataSource->saveRecord(array("name_values" => $newCustomFieldInfo, "primary_id" => $primaryId)))) {
						echo "<p class='error-message'>" . $customFieldDataSource->getErrorMessage() . "</p>";
						$errorFound = true;
						break;
					}

					executeQuery("delete from custom_field_choices where custom_field_id = ?", $customFieldId);
					foreach ($newCustomFieldInfo['custom_field_choices'] as $customFieldChoice) {
						$customFieldChoiceDataSource = new DataSource("custom_field_choices");
						$customFieldChoice['custom_field_id'] = $customFieldId;
						unset($customFieldChoice['version']);
						if (!($customFieldChoiceDataSource->saveRecord(array("name_values" => $customFieldChoice, "primary_id" => "")))) {
							echo "<p class='error-message'>" . $customFieldChoiceDataSource->getErrorMessage() . "</p>";
							$errorFound = true;
							break 2;
						}
					}

					executeQuery("delete from custom_field_controls where custom_field_id = ?", $customFieldId);
					foreach ($newCustomFieldInfo['custom_field_controls'] as $customFieldControl) {
						$customFieldControlDataSource = new DataSource("custom_field_controls");
						$customFieldControl['custom_field_id'] = $customFieldId;
						unset($customFieldControl['version']);
						if (!($customFieldControlDataSource->saveRecord(array("name_values" => $customFieldControl, "primary_id" => "")))) {
							echo "<p class='error-message'>" . $customFieldControlDataSource->getErrorMessage() . "</p>";
							$errorFound = true;
							break 2;
						}
					}

					executeQuery("delete from custom_field_group_links where custom_field_id = ?", $customFieldId);
					foreach ($newCustomFieldInfo['custom_field_group_links'] as $groupLinkData) {
						$groupLinkDatasource = new DataSource("custom_field_group_links");
						$groupLinkData['custom_field_id'] = $customFieldId;
						$groupLinkData['custom_field_group_id'] = getFieldFromId("custom_field_group_id", "custom_field_groups",
							"custom_field_group_code", $groupLinkData['custom_field_group_code']);
						unset($groupLinkData['version']);

						if (!($groupLinkDatasource->saveRecord(array("name_values" => $groupLinkData, "primary_id" => "")))) {
							echo "<p class='error-message'>" . $groupLinkDatasource->getErrorMessage() . "</p>";
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
            $("#import_custom_fields").on("click", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_custom_fields", $("#_edit_form").serialize(), function (returnArray) {
                    if ("results" in returnArray) {
                        $("#import_custom_fields").hide();
                        $("#update_custom_fields").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#update_custom_fields").show();
                            $("#hash_value").val(returnArray['hash_value']);
                        }
                        $("#custom_field_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#update_custom_fields").on("click", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_custom_fields", $("#_edit_form").serialize(), function (returnArray) {
                    if ("results" in returnArray) {
                        $("#import_custom_fields").hide();
                        $("#update_custom_fields").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#custom_field_definitions_json").val("");
                            $("#reenter_json").show();
                        }
                        $("#custom_field_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#reenter_json").on("click", function () {
                $("#reenter_json").hide();
                $("#update_custom_fields").hide();
                $("#import_custom_fields").show();
                $("#custom_field_definitions").show();
                $("#results").hide();
                return false;
            });
		</script>
		<?php
	}

	function internalCSS() {
		?>
		<style>
            #update_custom_fields {
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

            #custom_field_definitions label {
                display: block;
                margin: 10px 0;
            }

            #custom_field_definitions_json {
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
				<button id="import_custom_fields">Import Custom Fields</button>
				<button id="reenter_json">Re-enter JSON</button>
				<button id="update_custom_fields">Update Custom Fields</button>
			</p>
			<div id="custom_field_definitions">
				<label>Custom Field Definitions JSON</label>
				<textarea id="custom_field_definitions_json" name="custom_field_definitions_json" aria-label="Custom field definitions"></textarea>
			</div>
			<div id="results">
			</div>
		</form>
		<?php
		return true;
	}

}

$pageObject = new ImportCustomFieldDefinitionsPage();
$pageObject->displayPage();
