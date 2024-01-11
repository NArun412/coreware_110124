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

$GLOBALS['gPageCode'] = "COPYMENUS";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 150000;
ini_set("memory_limit", "4096M");

class CopyMenusPage extends Page {

	function getMenuIds($menuId) {
		$menuIdArray = array();
		$menuIdArray[] = $menuId;
		$resultSet = executeQuery("select * from menu_contents join menu_items using (menu_item_id) where menu_contents.menu_id = ?", $menuId);
		while ($row = getNextRow($resultSet)) {
			if (!empty($row['menu_id'])) {
				$menuIdArray = array_unique(array_merge($menuIdArray, $this->getMenuIds($row['menu_id'])));
			}
		}
		return $menuIdArray;
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_menus":

                # copy Client Page Templates

                $clientPageTemplates = array();
                $resultSet = executeQuery("select * from client_page_templates where client_id = ?",$GLOBALS['gClientId']);
                while ($row = getNextRow($resultSet)) {
	                $clientPageTemplates[] = $row;
                }
                $resultSet = executeQuery("select * from clients where client_id <> ? and client_id not in (select client_id from users where full_client_access = 1 and superuser_flag = 0)",$GLOBALS['gClientId']);
                while ($row = getNextRow($resultSet)) {
                    foreach ($clientPageTemplates as $clientPageTemplate) {
	                    executeQuery("insert ignore into client_page_templates (client_id,page_id,template_id) values (?,?,?)", $row['client_id'], $clientPageTemplate['page_id'], $clientPageTemplate['template_id']);
                    }
                }

				# copy user group

				if (!empty($_GET['user_group_id'])) {
					$userGroupRow = getRowFromId("user_groups", "user_group_id", $_GET['user_group_id']);
					if (empty($userGroupRow)) {
						$returnArray['error_message'] = "Invalid User Group";
						ajaxResponse($returnArray);
						exit;
					}
					$userGroupAccess = array();
					$resultSet = executeQuery("select * from user_group_access where user_group_id = ?", $userGroupRow['user_group_id']);
					while ($row = getNextRow($resultSet)) {
						$userGroupAccess[] = $row;
					}
					$clientSet = executeQuery("select * from clients where client_id <> ? and client_id not in (select client_id from users where full_client_access = 1 and superuser_flag = 0)", $GLOBALS['gClientId']);
					while ($clientRow = getNextRow($clientSet)) {
						$userGroupId = getFieldFromId("user_group_id", "user_groups", "user_group_code", $userGroupRow['user_group_code'], "client_id = ?", $clientRow['client_id']);
						if (empty($userGroupId)) {
							$insertSet = executeQuery("insert into user_groups (user_group_code,description) values (?,?)", $userGroupRow['user_group_code'], $userGroupRow['description']);
							$userGroupId = $insertSet['insert_id'];
						}
						foreach ($userGroupAccess as $row) {
							$userGroupAccessRow = getRowFromId("user_group_access", "user_group_id", $userGroupId, "page_id = ?", $row['page_id']);
							if (empty($userGroupAccessRow)) {
								executeQuery("insert into user_group_access (user_group_id,page_id,permission_level) values (?,?,?)", $userGroupId, $row['page_id'], $row['permission_level']);
							} else if ($userGroupAccessRow['permission_level'] != $row['permission_level']) {
								executeQuery("update user_group_access set permission_level = ? where user_group_access_id = ?", $row['permission_level'], $userGroupAccessRow['user_group_access_id']);
							}
						}
					}
				}

				# copy menus

				$menuId = getFieldFromId("menu_id", "menus", "menu_id", $_GET['menu_id']);
				if (empty($menuId)) {
					$returnArray['error_message'] = "Invalid Menu";
					ajaxResponse($returnArray);
					exit;
				}
				$originalMenuCode = getFieldFromId("menu_code", "menus", "menu_id", $menuId);

				$menuIdArray = $this->getMenuIds($menuId);

				$coreMenuInfo = array();
				$resultSet = executeQuery("select * from menus where menu_id in (" . implode(",", $menuIdArray) . ")");
				while ($row = getNextRow($resultSet)) {
					$thisMenu = array("menu_code" => $row['menu_code'], "description" => $row['description']);
					$itemArray = array();
					$itemSet = executeQuery("select description,link_title,link_url,(select menu_code from menus where menu_id = menu_items.menu_id) menu_code," .
						"(select page_code from pages where page_id = menu_items.page_id) page_code,(select subsystem_code from subsystems where subsystem_id = menu_items.subsystem_id) subsystem_code," .
						"not_logged_in,logged_in,administrator_access,query_string,separate_window from menu_contents join menu_items using (menu_item_id) " .
						"where menu_contents.menu_id = ? order by sequence_number", $row['menu_id']);
					while ($itemRow = getNextRow($itemSet)) {
						$itemArray[] = $itemRow;
					}
					$thisMenu['items'] = $itemArray;
					$coreMenuInfo[$row['menu_id']] = $thisMenu;
				}

				$subsystemArray = array();
				$resultSet = executeQuery("select * from subsystems");
				while ($row = getNextRow($resultSet)) {
					$subsystemArray[$row['subsystem_code']] = $row['subsystem_id'];
				}

				$allPageCodes = array();
				$resultSet = executeQuery("select page_id,page_code from pages where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$allPageCodes[$row['page_code']] = $row['page_id'];
				}

				$returnArray['response'] = "";

				$clientSet = executeQuery("select * from clients where client_id <> ? and client_id not in (select client_id from users where full_client_access = 1 and superuser_flag = 0 and inactive = 0)", $GLOBALS['gClientId']);
				while ($clientRow = getNextRow($clientSet)) {
                    $addedMenu = false;
					$clientId = $clientRow['client_id'];

					foreach ($coreMenuInfo as $menuInfo) {
						$menuId = getFieldFromId("menu_id", "menus", "menu_code", $menuInfo['menu_code'], "client_id = ?", $clientId);
						if (empty($menuId)) {
							$resultSet = executeQuery("insert into menus (client_id,menu_code,description) values (?,?,?)",
								$clientId, $menuInfo['menu_code'], $menuInfo['description']);
                            if ($resultSet['affected_rows'] > 0) {
                                $addedMenu = true;
                            }
						}
					}

					$clientMenus = array();
					$resultSet = executeQuery("select menu_id,menu_code from menus where client_id = ?", $clientId);
					while ($row = getNextRow($resultSet)) {
						$clientMenus[$row['menu_code']] = $row['menu_id'];
					}

					foreach ($coreMenuInfo as $index => $menuInfo) {
						$sequenceNumber = 10;
						$menuId = $clientMenus[$menuInfo['menu_code']];
						$menuContents = array();
						$resultSet = executeQuery("select * from menu_contents where menu_id = ?", $menuId);
						while ($row = getNextRow($resultSet)) {
							$menuContents[] = $row['menu_item_id'];
						}
						$usedMenuContents = array();
						foreach ($menuInfo['items'] as $menuItemInfo) {
							$thisMenuId = $clientMenus[$menuItemInfo['menu_code']];
							$thisPageId = $allPageCodes[$menuItemInfo['page_code']];
							$menuItemRow = getRowFromId("menu_items", "client_id", $clientId, "client_id = ? and menu_id <=> ? and page_id <=> ? and query_string <=> ? and " .
								"link_url <=> ? and menu_item_id in (select menu_item_id from menu_contents where menu_id = ?)", $clientId, $thisMenuId, $thisPageId, $menuItemInfo['query_string'], $menuItemInfo['link_url'], $menuId);
							if (empty($menuItemRow)) {
								$menuItemRow = getRowFromId("menu_items", "client_id", $clientId, "client_id = ? and menu_id <=> ? and page_id <=> ? and query_string <=> ? and " .
									"link_url <=> ?", $clientId, $thisMenuId, $thisPageId, $menuItemInfo['query_string'], $menuItemInfo['link_url']);
							}
							$menuItemId = $menuItemRow['menu_item_id'];
							$subsystemId = $subsystemArray[$menuItemInfo['subsystem_code']];
							if (empty($menuItemId)) {
								$resultSet = executeQuery("insert into menu_items (client_id,description,link_title,menu_id,page_id,link_url,subsystem_id,administrator_access,query_string,separate_window) values " .
									"(?,?,?,?,?,?,?,1,?,?)", $clientId, $menuItemInfo['description'], $menuItemInfo['link_title'], $thisMenuId, $thisPageId, $menuItemInfo['link_url'], $subsystemId, $menuItemInfo['query_string'], $menuItemInfo['separate_window']);
								$menuItemId = $resultSet['insert_id'];
								$addedMenu = true;
							} else {
								do {
									$duplicateMenuItemId = getFieldFromId("menu_item_id", "menu_items", "client_id", $clientId, "menu_item_id <> ? and client_id = ? and menu_id <=> ? and " .
										"page_id <=> ? and query_string <=> ? and link_url <=> ? and link_title = ?", $menuItemId, $clientId, $thisMenuId, $thisPageId,
										$menuItemRow['query_string'], $menuItemRow['link_url'], $menuItemRow['link_title']);
									if (!empty($duplicateMenuItemId)) {
										executeQuery("delete from menu_contents where menu_item_id = ?", $duplicateMenuItemId);
										executeQuery("delete from menu_items where menu_item_id = ?", $duplicateMenuItemId);
										$addedMenu = true;
									}
								} while (!empty($duplicateMenuItemId));
								if ($menuItemRow['description'] != $menuItemInfo['description'] || $subsystemId != $menuItemInfo['subsystem_id'] || $menuItemRow['link_title'] != $menuItemInfo['link_title']) {
									$updateSet = executeQuery("update menu_items set description = ?,link_title = ?,subsystem_id = ? where menu_item_id = ?",
										$menuItemInfo['description'], $menuItemInfo['link_title'], $subsystemId, $menuItemId);
									if ($updateSet['affected_rows'] > 0) {
										$addedMenu = true;
									}
								}
							}
							if (!in_array($menuItemId, $menuContents)) {
								$insertSet = executeQuery("insert ignore into menu_contents (menu_id,menu_item_id,sequence_number) values (?,?,?)", $menuId, $menuItemId, $sequenceNumber);
								if ($insertSet['affected_rows'] > 0) {
									$addedMenu = true;
								}
								$sequenceNumber += 10;
								$menuContents[] = $menuItemId;
							}
							$usedMenuContents[] = $menuItemId;
						}
						$deleteMenuContents = array_diff($menuContents, $usedMenuContents);
						$deleteSet = executeQuery("delete from menu_contents where menu_id = ? and menu_item_id in (" . implode(",", $deleteMenuContents) . ")", $menuId);
						if ($deleteSet['affected_rows'] > 0) {
							$addedMenu = true;
						}
						if ($menuInfo['menu_code'] != $originalMenuCode) {
							sortMenu($menuInfo['menu_code'], $clientId);
						}
					}
					removeCachedData("menu_contents", "*", $clientId);
                    if ($addedMenu) {
	                    $returnArray['response'] .= "<p>Sync'd menus for " . $clientRow['client_code'] . "</p>";
                    }
				}
				$returnArray['response'] .= "<p>Menus copied to all clients</p>";
				ajaxResponse($returnArray);
				exit;
		}
	}

	function mainContent() {
		if ($GLOBALS['gClientId'] != $GLOBALS['gDefaultClientId']) {
			echo "<p>This page can only be run from the primary client.</p>";
			return true;
		}
		?>
        <p>Copy the following menu structure to clients. All menus, submenus, and menu items will be copied.</p>
        <div class='form-line'>
            <label>Choose Menu</label>
            <select id="menu_id" name="menu_id">
                <option value=''>[Select]</option>
				<?php
				$resultSet = executeQuery("select * from menus where client_id = ? and menu_code like '%ADMIN%' order by description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
                    <option value='<?= $row['menu_id'] ?>'><?= htmlText($row['description']) ?></option>
					<?php
				}
				?>
            </select>
        </div>

        <p>Copy the following user group permissions to all clients.</p>
        <div class='form-line'>
            <label>Choose Group</label>
            <select id="user_group_id" name="user_group_id">
                <option value=''>[Select]</option>
				<?php
				$resultSet = executeQuery("select * from user_groups where client_id = ? order by description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
                    <option value='<?= $row['user_group_id'] ?>'><?= htmlText($row['description']) ?></option>
					<?php
				}
				?>
            </select>
        </div>
        <p class='error-message'></p>
        <p id='create_menus_wrapper' class='info-message'>
            <button id='create_menus'>Copy</button>
        </p>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#create_menus", function () {
                if (empty($("#menu_id").val())) {
                    return false;
                }
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_menus&menu_id=" + $("#menu_id").val() + "&user_group_id=" + $("#user_group_id").val(), function (returnArray) {
                    if (empty(returnArray['error_message'])) {
                        if ("response" in returnArray) {
                            $("#create_menus_wrapper").html(returnArray['response']);
                        }
                    }
                });
                return false;
            });
        </script>
		<?php
	}
}

$pageObject = new CopyMenusPage();
$pageObject->displayPage();
