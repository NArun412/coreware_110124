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

$GLOBALS['gPageCode'] = "IMPORTBANNERDEFINITIONS";
require_once "shared/startup.inc";

class ImportBannerDefinitionsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "import_banners":
				$definitionArray = json_decode($_POST['banner_definitions_json'], true);
				if (empty($definitionArray) || empty($definitionArray['banners'])) {
					$returnArray['error_message'] = "Invalid JSON object";
					ajaxResponse($returnArray);
					break;
				}
				$errorFound = false;
				ob_start();
				?>
				<h2>Verify all changes and click Update</h2>
				<?php
				foreach ($definitionArray['banners'] as $newBannerInfo) {
					?>
					<h3><?= $newBannerInfo['banner_code'] . " - " . $newBannerInfo['description'] ?></h3>
					<?php
					$bannerErrorFound = false;

					if (!empty($newBannerInfo['image_code'])) {
						$imageId = getFieldFromId("image_id", "images", "image_code", $newBannerInfo['image_code']);
						if (empty($imageId)) {
							?>
							<p class="error-message">Image does not exist: <?= $newBannerInfo['image_code'] ?>.</p>
							<?php
							$errorFound = true;
							$bannerErrorFound = true;
						}
					}

					foreach ($newBannerInfo['banner_context'] as $bannerContext) {
						if (!empty($bannerContext['page_code'])) {
							$bannerContextPageId = getFieldFromId("page_id", "pages", "page_code", $bannerContext['page_code']);
							if (empty($bannerContextPageId)) {
								?>
								<p class="error-message">Page does not exist: <?= $bannerContext['page_code'] ?>.</p>
								<?php
								$errorFound = true;
								$bannerErrorFound = true;
							}
						}
					}

					if (empty($bannerErrorFound)) {
						$existingBannerRow = getRowFromId("banners", "banner_code", $newBannerInfo['banner_code']);
						if (empty($existingBannerRow)) {
							?>
							<p class="info-message">Banner does not exist. Will be created.</p>
							<?php
						} else {
							?>
							<p class="info-message">Changes to banner will be made.</p>
							<?php
						}
					}
				}
				$returnArray['error_found'] = $errorFound;
				$returnArray['results'] = ob_get_clean();
				$returnArray['hash_value'] = md5($_POST['banner_definitions_json']);
				ajaxResponse($returnArray);
				break;
			case "update_banners":
				$hashValue = md5($_POST['banner_definitions_json']);
				if ($hashValue != $_POST['hash_value']) {
					$returnArray['error_message'] = "JSON Object changed and must be revalidated.";
					ajaxResponse($returnArray);
					break;
				}
				$definitionArray = json_decode($_POST['banner_definitions_json'], true);
				if (empty($definitionArray) || empty($definitionArray['banners'])) {
					$returnArray['error_message'] = "Invalid JSON object";
					ajaxResponse($returnArray);
					break;
				}
				$errorFound = false;
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				ob_start();

                // Banner groups
				foreach ($definitionArray['banner_groups'] as $newBannerGroup) {
					if (empty($newBannerGroup['banner_group_code'])) {
						$returnArray['error_message'] = "Invalid banner group";
						ajaxResponse($returnArray);
						break;
					}
					?>
                    <p class="info-message">Updating banner group '<?= $newBannerGroup['banner_group_code'] . " - " . $newBannerGroup['description'] ?>'.</p>
					<?php

					$existingBannerGroupRow = getRowFromId("banner_groups", "banner_group_code", $newBannerGroup['banner_group_code']);
					$newBannerGroup['client_id'] = $GLOBALS['gClientId'];
					unset($newBannerGroup['version']);

					$primaryId = empty($existingBannerGroupRow) ? "" : $existingBannerGroupRow['banner_group_id'];
					$bannerGroupDataSource = new DataSource("banner_groups");
					$bannerGroupDataSource->setSaveOnlyPresent(true);

					if (!$bannerGroupDataSource->saveRecord(array("name_values" => $newBannerGroup, "primary_id" => $primaryId))) {
						echo "<p class='error-message'>" . $bannerGroupDataSource->getErrorMessage() . "</p>";
						$errorFound = true;
						break;
					}
				}

				// Banner tags
				foreach ($definitionArray['banner_tags'] as $newBannerTag) {
					if (empty($newBannerTag['banner_tag_code'])) {
						$returnArray['error_message'] = "Invalid banner tag";
						ajaxResponse($returnArray);
						break;
					}
					?>
                    <p class="info-message">Updating banner tag '<?= $newBannerTag['banner_tag_code'] . " - " . $newBannerTag['description'] ?>'.</p>
					<?php

					$existingBannerTagRow = getRowFromId("banner_tags", "banner_tag_code", $newBannerTag['banner_tag_code']);
					$newBannerTag['client_id'] = $GLOBALS['gClientId'];
					unset($newBannerTag['version']);

					$primaryId = empty($existingBannerTagRow) ? "" : $existingBannerTagRow['banner_tag_id'];
					$bannerTagDataSource = new DataSource("banner_tags");
					$bannerTagDataSource->setSaveOnlyPresent(true);

					if (!$bannerTagDataSource->saveRecord(array("name_values" => $newBannerTag, "primary_id" => $primaryId))) {
						echo "<p class='error-message'>" . $bannerTagDataSource->getErrorMessage() . "</p>";
						$errorFound = true;
						break;
					}
				}

                // Banners
				foreach ($definitionArray['banners'] as $newBannerInfo) {
					if (empty($newBannerInfo['banner_code'])) {
						$returnArray['error_message'] = "Invalid banner";
						ajaxResponse($returnArray);
						break;
					}
					?>
					<p class="info-message">Updating banner '<?= $newBannerInfo['banner_code'] . " - " . $newBannerInfo['description'] ?>'.</p>
					<?php

					$existingBannerRow = getRowFromId("banners", "banner_code", $newBannerInfo['banner_code']);
					$newBannerInfo['client_id'] = $GLOBALS['gClientId'];

					// Foreign key references
					$newBannerInfo['image_id'] = getFieldFromId("image_id", "images",
						"image_code", $newBannerInfo['image_code']);

					unset($newBannerInfo['version']);

					$primaryId = empty($existingBannerRow) ? "" : $existingBannerRow['banner_id'];
					$bannerDataSource = new DataSource("banners");
					$bannerDataSource->setSaveOnlyPresent(true);

					if (!($bannerId = $bannerDataSource->saveRecord(array("name_values" => $newBannerInfo, "primary_id" => $primaryId)))) {
						echo "<p class='error-message'>" . $bannerDataSource->getErrorMessage() . "</p>";
						$errorFound = true;
						break;
					}

					executeQuery("delete from banner_context where banner_id = ?", $bannerId);
					foreach ($newBannerInfo['banner_context'] as $bannerContext) {
						$bannerContextDatasource = new DataSource("banner_context");
						$bannerContext['banner_id'] = $bannerId;
						$bannerContext['page_id'] = getFieldFromId("page_id", "pages", "page_code", $bannerContext['page_code']);
						unset($bannerContext['version']);

						if (!($bannerContextDatasource->saveRecord(array("name_values" => $bannerContext, "primary_id" => "")))) {
							echo "<p class='error-message'>" . $bannerContextDatasource->getErrorMessage() . "</p>";
							$errorFound = true;
							break 2;
						}
					}

					executeQuery("delete from banner_group_links where banner_id = ?", $bannerId);
					foreach ($newBannerInfo['banner_group_links'] as $bannerGroupLink) {
						$bannerGroupLinkDatasource = new DataSource("banner_group_links");
						$bannerGroupLink['banner_id'] = $bannerId;
						$bannerGroupLink['banner_group_id'] = getFieldFromId("banner_group_id", "banner_groups",
                            "banner_group_code", $bannerGroupLink['banner_group_code']);
						unset($bannerGroupLink['version']);

						if (!($bannerGroupLinkDatasource->saveRecord(array("name_values" => $bannerGroupLink, "primary_id" => "")))) {
							echo "<p class='error-message'>" . $bannerGroupLinkDatasource->getErrorMessage() . "</p>";
							$errorFound = true;
							break 2;
						}
					}

					executeQuery("delete from banner_tag_links where banner_id = ?", $bannerId);
					foreach ($newBannerInfo['banner_tag_links'] as $bannerTagLink) {
						$bannerTagLinkDatasource = new DataSource("banner_tag_links");
						$bannerTagLink['banner_id'] = $bannerId;
						$bannerTagLink['banner_tag_id'] = getFieldFromId("banner_tag_id", "banner_tags",
							"banner_tag_code", $bannerTagLink['banner_tag_code']);
						unset($bannerTagLink['version']);

						if (!($bannerTagLinkDatasource->saveRecord(array("name_values" => $bannerTagLink, "primary_id" => "")))) {
							echo "<p class='error-message'>" . $bannerTagLinkDatasource->getErrorMessage() . "</p>";
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
            $("#import_banners").on("click", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_banners", $("#_edit_form").serialize(), function (returnArray) {
                    if ("results" in returnArray) {
                        $("#import_banners").hide();
                        $("#update_banners").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#update_banners").show();
                            $("#hash_value").val(returnArray['hash_value']);
                        }
                        $("#banner_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#update_banners").on("click", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_banners", $("#_edit_form").serialize(), function (returnArray) {
                    if ("results" in returnArray) {
                        $("#import_banners").hide();
                        $("#update_banners").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#banner_definitions_json").val("");
                            $("#reenter_json").show();
                        }
                        $("#banner_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#reenter_json").on("click", function () {
                $("#reenter_json").hide();
                $("#update_banners").hide();
                $("#import_banners").show();
                $("#banner_definitions").show();
                $("#results").hide();
                return false;
            });
		</script>
		<?php
	}

	function internalCSS() {
		?>
		<style>
            #update_banners {
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

            #banner_definitions label {
                display: block;
                margin: 10px 0;
            }

            #banner_definitions_json {
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
				<button id="import_banners">Import Banners</button>
				<button id="reenter_json">Re-enter JSON</button>
				<button id="update_banners">Update Banners</button>
			</p>
			<div id="banner_definitions">
				<label>Banner Definitions JSON</label>
				<textarea id="banner_definitions_json" name="banner_definitions_json" aria-label="Banner definitions"></textarea>
			</div>
			<div id="results">
			</div>
		</form>
		<?php
		return true;
	}

}

$pageObject = new ImportBannerDefinitionsPage();
$pageObject->displayPage();
