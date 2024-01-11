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

$GLOBALS['gPageCode'] = "IMPORTIMAGEDEFINITIONS";
require_once "shared/startup.inc";

class ImportImageDefinitionsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "import_images":
				$definitionArray = json_decode($_POST['image_definitions_json'], true);
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
				foreach ($definitionArray as $newImageInfo) {
					?>
					<h3><?= $newImageInfo['image_code'] . " - " . $newImageInfo['description'] ?></h3>
					<?php
					$imageErrorFound = false;

					if (!empty($newImageInfo['source_code'])) {
						$sourceId = getFieldFromId("source_id", "sources", "source_code", $newImageInfo['source_code']);
						if (empty($sourceId)) {
							?>
							<p class="error-message">Source does not exist: <?= $newImageInfo['source_code'] ?>.</p>
							<?php
							$errorFound = true;
							$imageErrorFound = true;
						}
					}
					if (!empty($newImageInfo['user_group_code'])) {
						$userGroupId = getFieldFromId("user_group_id", "user_groups", "user_group_code", $newImageInfo['user_group_code']);
						if (empty($userGroupId)) {
							?>
							<p class="error-message">User group does not exist: <?= $newImageInfo['user_group_code'] ?>.</p>
							<?php
							$errorFound = true;
							$imageErrorFound = true;
						}
					}
					if (!empty($newImageInfo['country_code'])) {
						$countryId = getFieldFromId("country_id", "countries", "country_code", $newImageInfo['country_code']);
						if (empty($countryId)) {
							?>
							<p class="error-message">Country does not exist: <?= $newImageInfo['country_code'] ?>.</p>
							<?php
							$errorFound = true;
							$imageErrorFound = true;
						}
					}
					if (!empty($newImageInfo['image_folder_code'])) {
						$imageFolderId = getFieldFromId("image_folder_id", "image_folders", "image_folder_code", $newImageInfo['image_folder_code']);
						if (empty($imageFolderId)) {
							?>
							<p class="error-message">Image folder does not exist: <?= $newImageInfo['image_folder_code'] ?>.</p>
							<?php
							$errorFound = true;
							$imageErrorFound = true;
						}
					}

					foreach ($newImageInfo['image_data'] as $imageData) {
						if (!empty($imageData['image_data_type_code'])) {
							$imageDataTypeId = getFieldFromId("image_data_type_id", "image_data_types", "image_data_type_code", $imageData['image_data_type_code']);
							if (empty($imageDataTypeId)) {
								?>
								<p class="error-message">Image data type does not exist: <?= $imageData['image_data_type_code'] ?>.</p>
								<?php
								$errorFound = true;
								$imageErrorFound = true;
							}
						}
					}

					foreach ($newImageInfo['album_images'] as $albumImage) {
						if (!empty($albumImage['album_code'])) {
							$albumId = getFieldFromId("album_id", "albums", "album_code", $albumImage['album_code']);
							if (empty($albumId)) {
								?>
								<p class="error-message">Album does not exist: <?= $albumImage['album_code'] ?>.</p>
								<?php
								$errorFound = true;
								$imageErrorFound = true;
							}
						}
					}

					if (empty($imageErrorFound)) {
						$existingImageRow = getRowFromId("images", "image_code", $newImageInfo['image_code']);
						if (empty($existingImageRow)) {
							?>
							<p class="info-message">Image does not exist. Will be created.</p>
							<?php
						} else {
							?>
							<p class="info-message">Changes to image will be made.</p>
							<?php
						}
					}
				}
				$returnArray['error_found'] = $errorFound;
				$returnArray['results'] = ob_get_clean();
				$returnArray['hash_value'] = md5($_POST['image_definitions_json']);
				ajaxResponse($returnArray);
				break;
			case "update_images":
				$hashValue = md5($_POST['image_definitions_json']);
				if ($hashValue != $_POST['hash_value']) {
					$returnArray['error_message'] = "JSON Object changed and must be revalidated.";
					ajaxResponse($returnArray);
					break;
				}
				$definitionArray = json_decode($_POST['image_definitions_json'], true);
				if (empty($definitionArray)) {
					$returnArray['error_message'] = "Invalid JSON object";
					ajaxResponse($returnArray);
					break;
				}
				$errorFound = false;
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				ob_start();

				foreach ($definitionArray as $newImageInfo) {
					if (empty($newImageInfo['image_code'])) {
						$returnArray['error_message'] = "Invalid JSON object";
						ajaxResponse($returnArray);
						break;
					}
					?>
					<p class="info-message">Updating image '<?= $newImageInfo['image_code'] . " - " . $newImageInfo['description'] ?>'.</p>
					<?php

					$existingImageRow = getRowFromId("images", "image_code", $newImageInfo['image_code']);
					$newImageInfo['client_id'] = $GLOBALS['gClientId'];
					$newImageInfo['file_content'] = base64_decode($newImageInfo['file_content']);

					// Foreign key references
					$newImageInfo['source_id'] = getFieldFromId("source_id", "sources",
						"source_code", $newImageInfo['source_code']);
					$newImageInfo['user_group_id'] = getFieldFromId("user_group_id", "user_groups",
						"user_group_code", $newImageInfo['user_group_code']);
					$newImageInfo['country_id'] = getFieldFromId("country_id", "countries",
						"country_code", $newImageInfo['country_code']);
					$newImageInfo['image_folder_id'] = getFieldFromId("image_folder_id", "image_folders",
						"image_folder_code", $newImageInfo['image_folder_code']);

					unset($newImageInfo['version']);
					unset($newImageInfo['security_level_id']);

					if (empty($existingImageRow)) {
						$newImageInfo['user_id'] = $GLOBALS['gUserId'];
						$newImageInfo['date_created'] = date("Y-m-d");
						$primaryId = "";
					} else {
						unset($newImageInfo['user_id']);
						$primaryId = $existingImageRow['image_id'];
					}

					$imageDataSource = new DataSource("images");
					$imageDataSource->setSaveOnlyPresent(true);

					if (!($imageId = $imageDataSource->saveRecord(array("name_values" => $newImageInfo, "primary_id" => $primaryId)))) {
						echo "<p class='error-message'>" . $imageDataSource->getErrorMessage() . "</p>";
						$errorFound = true;
						break;
					}

					executeQuery("delete from image_data where image_id = ?", $imageId);
					foreach ($newImageInfo['image_data'] as $imageData) {
						$imageDataDatasource = new DataSource("image_data");
						$imageData['image_id'] = $imageId;

						$imageDataTypeId = getFieldFromId("image_data_type_id", "image_data_types",
							"image_data_type_code", $imageData['image_data_type_code']);
						if (empty($imageDataTypeId)) {
							echo "<p>Image data type not found: " . $imageData['image_data_type_code'] . "</p>";
							$errorFound = true;
							break 2;
						}
						unset($imageData['version']);

						if (!($imageDataDatasource->saveRecord(array("name_values" => $imageData, "primary_id" => "")))) {
							echo "<p class='error-message'>" . $imageDataDatasource->getErrorMessage() . "</p>";
							$errorFound = true;
							break 2;
						}
					}

					executeQuery("delete from album_images where image_id = ?", $imageId);
					foreach ($newImageInfo['album_images'] as $albumImage) {
						$albumImageDataSource = new DataSource("album_images");
						$albumImage['image_id'] = $imageId;
						$albumImage['album_id'] = getFieldFromId("album_id", "albums",
							"album_code", $albumImage['album_code']);
						unset($albumImage['version']);

						if (!($albumImageDataSource->saveRecord(array("name_values" => $albumImage, "primary_id" => "")))) {
							echo "<p class='error-message'>" . $albumImageDataSource->getErrorMessage() . "</p>";
							$errorFound = true;
							break 2;
						}
					}
					removeCachedData("img_filenames", $imageId);
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
            $("#import_images").on("click", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_images", $("#_edit_form").serialize(), function (returnArray) {
                    if ("results" in returnArray) {
                        $("#import_images").hide();
                        $("#update_images").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#update_images").show();
                            $("#hash_value").val(returnArray['hash_value']);
                        }
                        $("#image_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#update_images").on("click", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_images", $("#_edit_form").serialize(), function (returnArray) {
                    if ("results" in returnArray) {
                        $("#import_images").hide();
                        $("#update_images").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#image_definitions_json").val("");
                            $("#reenter_json").show();
                        }
                        $("#image_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#reenter_json").on("click", function () {
                $("#reenter_json").hide();
                $("#update_images").hide();
                $("#import_images").show();
                $("#image_definitions").show();
                $("#results").hide();
                return false;
            });
		</script>
		<?php
	}

	function internalCSS() {
		?>
		<style>
            #update_images {
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

            #image_definitions label {
                display: block;
                margin: 10px 0;
            }

            #image_definitions_json {
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
				<button id="import_images">Import Images</button>
				<button id="reenter_json">Re-enter JSON</button>
				<button id="update_images">Update Images</button>
			</p>
			<div id="image_definitions">
				<label>Image Definitions JSON</label>
				<textarea id="image_definitions_json" name="image_definitions_json" aria-label="Image definitions"></textarea>
			</div>
			<div id="results">
			</div>
		</form>
		<?php
		return true;
	}

}

$pageObject = new ImportImageDefinitionsPage();
$pageObject->displayPage();
