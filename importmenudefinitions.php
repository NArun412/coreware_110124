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

$GLOBALS['gPageCode'] = "IMPORTMENUDEFINITIONS";
require_once "shared/startup.inc";

class ImportMenuDefinitionsPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "import_menus":
				$definitionArray = json_decode($_POST['menu_definitions_json'], true);
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
				$menuCodes = array();
				foreach ($definitionArray as $newMenuInfo) {
					if (empty($newMenuInfo['menu_code'])) {
						$returnArray['error_message'] = "Invalid JSON object";
						ajaxResponse($returnArray);
						break;
					}
					$menuCodes[] = $newMenuInfo['menu_code'];
				}
				foreach ($definitionArray as $newMenuInfo) {
					?>
                    <h3><?= $newMenuInfo['menu_code'] . " - " . $newMenuInfo['description'] ?></h3>
					<?php
					$menuErrorFound = false;
					foreach ($newMenuInfo['menu_items'] as $menuItem) {
						if (!empty($menuItem['subsystem_code'])) {
							$subsystemId = getFieldFromId("subsystem_id", "subsystems", "subsystem_code", $menuItem['subsystem_code']);
							if (empty($subsystemId)) {
								?>
								<p class="error-message">Subsystem does not exist: <?= $menuItem['subsystem_code'] ?>.</p>
								<?php
								$errorFound = true;
								$menuErrorFound = true;
							}
						}
						if (!empty($menuItem['page_code'])) {
							$pageId = getFieldFromId("page_id", "pages", "page_code", $menuItem['page_code']);
							if (empty($pageId)) {
								?>
								<p class="error-message">Page does not exist: <?= $menuItem['page_code'] ?>.</p>
								<?php
								$errorFound = true;
								$menuErrorFound = true;

							}
						}
						if (!empty($menuItem['menu_code'])) {
							$menuId = getFieldFromId("menu_id", "menus", "menu_code", $menuItem['menu_code']);
							if (empty($menuId) && !in_array($menuItem['menu_code'], $menuCodes)) {
								?>
								<p class="error-message">Menu does not exist: <?= $menuItem['page_code'] ?>.</p>
								<?php
								$errorFound = true;
								$menuErrorFound = true;
							}
						}

						$menuItemCount = getFieldFromId("count(*)", "menu_items", "description", $menuItem['description']);
						if ($menuItemCount > 1) {
							?>
							<p class="error-message">Multiple menu items found with same description: <?= $menuItem['description'] ?>.</p>
							<?php
							$errorFound = true;
							$menuErrorFound = true;
						}
					}

					if (empty($menuErrorFound)) {
						$existingMenuRow = getRowFromId("menus", "menu_code", $newMenuInfo['menu_code']);
						if (empty($existingMenuRow)) {
							?>
							<p class="info-message">Menu does not exist. Will be created.</p>
							<?php
						} else {
							?>
							<p class="info-message">Changes to menu will be made.</p>
							<?php
						}
					}
				}
				$returnArray['error_found'] = $errorFound;
				$returnArray['results'] = ob_get_clean();
				$returnArray['hash_value'] = md5($_POST['menu_definitions_json']);
				ajaxResponse($returnArray);
				break;
			case "update_menus":
				$hashValue = md5($_POST['menu_definitions_json']);
				if ($hashValue != $_POST['hash_value']) {
					$returnArray['error_message'] = "JSON Object changed and must be revalidated.";
					ajaxResponse($returnArray);
					break;
				}
				$definitionArray = json_decode($_POST['menu_definitions_json'], true);
				if (empty($definitionArray)) {
					$returnArray['error_message'] = "Invalid JSON object";
					ajaxResponse($returnArray);
					break;
				}
				$errorFound = false;
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				ob_start();

				$menuCodes = array();
				// Save all menus first as they might be referenced by a menu item
				foreach ($definitionArray as $newMenuInfo) {
					if (empty($newMenuInfo['menu_code'])) {
						$returnArray['error_message'] = "Invalid JSON object";
						ajaxResponse($returnArray);
						break;
					}
					$menuCodes[] = $newMenuInfo['menu_code'];
					?>
                    <p class="info-message">Updating menu '<?= $newMenuInfo['menu_code'] . " - " . $newMenuInfo['description'] ?>'.</p>
					<?php

					$existingMenuRow = getRowFromId("menus", "menu_code", $newMenuInfo['menu_code']);
					$newMenuInfo['client_id'] = $GLOBALS['gClientId'];
					unset($newMenuInfo['version']);

					$menuDataSource = new DataSource("menus");
					$menuDataSource->setSaveOnlyPresent(true);

					$primaryId = empty($existingMenuRow) ? "" : $existingMenuRow['menu_id'];
					if (!($menuDataSource->saveRecord(array("name_values" => $newMenuInfo, "primary_id" => $primaryId)))) {
						echo "<p class='error-message'>" . $menuDataSource->getErrorMessage() . "</p>";
						$errorFound = true;
						break;
					}
				}

				foreach ($definitionArray as $newMenuInfo) {
					?>
					<p class="info-message">Updating menu items for Menu '<?= $newMenuInfo['menu_code'] . " - " . $newMenuInfo['description'] ?>'.</p>
					<?php
					$menuId = getFieldFromId("menu_id", "menus", "menu_code", $newMenuInfo['menu_code']);

					executeQuery("delete from menu_contents where menu_id = ?", $menuId);
					foreach ($newMenuInfo['menu_items'] as $menuItem) {
						$subsystemId = "";
						if (!empty($menuItem['subsystem_code'])) {
							$subsystemId = getFieldFromId("subsystem_id", "subsystems", "subsystem_code", $menuItem['subsystem_code']);
							if (empty($subsystemId)) {
								?>
								<p class="error-message">Subsystem does not exist: <?= $menuItem['subsystem_code'] ?>.</p>
								<?php
								$errorFound = true;
								break 2;
							}
						}

						$pageId = "";
						if (!empty($menuItem['page_code'])) {
							$pageId = getFieldFromId("page_id", "pages", "page_code", $menuItem['page_code']);
							if (empty($pageId)) {
								?>
								<p class="error-message">Page does not exist: <?= $menuItem['page_code'] ?>.</p>
								<?php
								$errorFound = true;
								break 2;
							}
						}

						$subMenuId = "";
						if (!empty($menuItem['menu_code'])) {
							$subMenuId = getFieldFromId("menu_id", "menus", "menu_code", $menuItem['menu_code']);
							if (empty($subMenuId) && !in_array($menuItem['menu_code'], $menuCodes)) {
								?>
								<p class="error-message">Menu does not exist: <?= $menuItem['page_code'] ?>.</p>
								<?php
								$errorFound = true;
								break 2;
							}
						}

						$menuItemDataSource = new DataSource("menu_items");

						$menuItem['client_id'] = $GLOBALS['gClientId'];
						$menuItem['page_id'] = $pageId;
						$menuItem['subsystem_id'] = $subsystemId;
						$menuItem['menu_id'] = $subMenuId;
						$menuItem['image_id'] = getFieldFromId("image_id", "images",
							"image_code", $menuItem['image_code']);

						unset($menuItem['version']);

						$primaryId = "";
						$resultSet = executeQuery("select * from menu_items where description = ? and client_id = ?", $menuItem['description'], $GLOBALS['gClientId']);
						if ($resultSet['row_count'] > 1) {
							echo "<p class='error-message'>Multiple menu items found with same description found.</p>";
							$errorFound = true;
							break 2;
						} else if ($resultSet['row_count'] == 1) {
							$primaryId = getNextRow($resultSet)['menu_item_id'];
						}

						if (!($menuItemId = $menuItemDataSource->saveRecord(array("name_values" => $menuItem, "primary_id" => $primaryId)))) {
							echo "<p class='error-message'>" . $menuItemDataSource->getErrorMessage() . "</p>";
							$errorFound = true;
							break 2;
						}

						$menuContentDataSource = new DataSource("menu_contents");
						$menuContent = array("menu_id" => $menuId, "menu_item_id" => $menuItemId, "sequence_number" => $menuItem['sequence_number']);
						if (!($menuContentDataSource->saveRecord(array("name_values" => $menuContent, "primary_id" => "")))) {
							echo "<p class='error-message'>" . $menuItemDataSource->getErrorMessage() . "</p>";
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
				removeCachedData("menu_contents", "*");
				removeCachedData("admin_menu", "*");
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#import_menus").on("click", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_menus", $("#_edit_form").serialize(), function (returnArray) {
                    if ("results" in returnArray) {
                        $("#import_menus").hide();
                        $("#update_menus").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#update_menus").show();
                            $("#hash_value").val(returnArray['hash_value']);
                        }
                        $("#menu_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#update_menus").on("click", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_menus", $("#_edit_form").serialize(), function (returnArray) {
                    if ("results" in returnArray) {
                        $("#import_menus").hide();
                        $("#update_menus").hide();
                        if (returnArray['error_found']) {
                            $("#reenter_json").show();
                        } else {
                            $("#menu_definitions_json").val("");
                            $("#reenter_json").show();
                        }
                        $("#menu_definitions").hide();
                        $("#results").html(returnArray['results']).show();
                    }
                });
                return false;
            });
            $("#reenter_json").on("click", function () {
                $("#reenter_json").hide();
                $("#update_menus").hide();
                $("#import_menus").show();
                $("#menu_definitions").show();
                $("#results").hide();
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #update_menus {
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

            #menu_definitions label {
                display: block;
                margin: 10px 0;
            }

            #menu_definitions_json {
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
                <button id="import_menus">Import Menus</button>
                <button id="reenter_json">Re-enter JSON</button>
                <button id="update_menus">Update Menus</button>
            </p>
            <div id="menu_definitions">
                <label>Menu Definitions JSON</label>
                <textarea id="menu_definitions_json" name="menu_definitions_json" aria-label="Menu definitions"></textarea>
            </div>
            <div id="results">
            </div>
        </form>
		<?php
		return true;
	}

}

$pageObject = new ImportMenuDefinitionsPage();
$pageObject->displayPage();
