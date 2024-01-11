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

$GLOBALS['gPageCode'] = "SITEBUILDER";
require_once "shared/startup.inc";

class SiteBuilderPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "create_structure":
				$templateId = $_POST['template_id'];
				$createdMenus = array();
				$createdMenuItems = array();
				$createdPages = array();
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$menuId = "";
				$submenuId = "";
				foreach ($_POST as $fieldName => $fieldData) {
					if (!(substr($fieldName, 0, strlen("menu_structure_control_menu_name-")) == "menu_structure_control_menu_name-")) {
						continue;
					}
					$rowNumber = substr($fieldName, strlen("menu_structure_control_menu_name-"));
					if (!is_numeric($rowNumber)) {
						continue;
					}
					$menuName = trim($fieldData);
					$menuItemName = trim($_POST['menu_structure_control_menu_item_name-' . $rowNumber]);
					$submenuName = trim($_POST['menu_structure_control_submenu_name-' . $rowNumber]);
					$pageName = trim($_POST['menu_structure_control_page_name-' . $rowNumber]);
					$adminFlag = $_POST['menu_structure_control_admin_flag-' . $rowNumber];
					$userFlag = $_POST['menu_structure_control_user_flag-' . $rowNumber];
					$publicFlag = $_POST['menu_structure_control_public_flag-' . $rowNumber];
					$menuItemId = "";
					$pageId = "";

					if (!empty($menuName)) {
						if (!array_key_exists($menuName, $createdMenus)) {
							$menuCode = makeCode($menuName);
							$menuId = getFieldFromId("menu_id", "menus", "menu_code", $menuCode);
							if (empty($menuId)) {
								$resultSet = executeQuery("insert into menus (client_id,menu_code,description) values (?,?,?)", $GLOBALS['gClientId'], $menuCode, $menuName);
								if (!empty($resultSet['sql_error'])) {
									$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
								$menuId = $resultSet['insert_id'];
							}
							$createdMenus[$menuName] = $menuId;
						} else {
							$menuId = $createdMenus[$menuName];
						}
					}

					if (empty($menuId)) {
						$returnArray['console'] = $menuName . ":" . $menuId . "\n";
						continue;
					}

					if (!empty($submenuName)) {
						if (!array_key_exists($submenuName, $createdMenus)) {
							$menuCode = makeCode($submenuName);
							$submenuId = getFieldFromId("menu_id", "menus", "menu_code", $menuCode);
							if (empty($submenuId)) {
								$resultSet = executeQuery("insert into menus (client_id,menu_code,description) values (?,?,?)", $GLOBALS['gClientId'], $menuCode, $submenuName);
								if (!empty($resultSet['sql_error'])) {
									$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
								$submenuId = $resultSet['insert_id'];
							}
							$createdMenus[$submenuName] = $submenuId;
						} else {
							$submenuId = $createdMenus[$submenuName];
						}
					} else if (!empty($menuItemName)) {
						$submenuId = "";
					}

					if (!empty($pageName)) {
						if (!array_key_exists($pageName, $createdPages)) {
							$pageCode = makeCode($GLOBALS['gClientRow']['client_code'] . " " . $pageName);
							$pageId = $GLOBALS['gAllPageCodes'][$pageCode];
							if (empty($pageId)) {
								$resultSet = executeQuery("insert into pages (client_id,page_code,description,date_created,creator_user_id,link_name,template_id) values (?,?,?,now(),?,?,?)",
									$GLOBALS['gClientId'], $pageCode, $pageName, $GLOBALS['gUserId'], makeCode($pageName, array("use_dash" => true, "lowercase" => true)), $templateId);
								if (!empty($resultSet['sql_error'])) {
									$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
								$pageId = $resultSet['insert_id'];
								if (!empty($adminFlag) || !empty($userFlag) || !empty($publicFlag)) {
									$resultSet = executeQuery("insert into page_access (page_id,administrator_access,all_user_access,public_access,permission_level) values (?,?,?,?,3)",
										$pageId, ($adminFlag ? "1" : "0"), ($userFlag ? "1" : "0"), ($publicFlag ? "1" : "0"));
									if (!empty($resultSet['sql_error'])) {
										$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
										$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
										ajaxResponse($returnArray);
										break;
									}
								}
							}
							$createdPages[$pageName] = $pageId;
						} else {
							$pageId = $createdPages[$pageName];
						}
					}

					if (!empty($menuItemName)) {
						if (!array_key_exists($menuItemName, $createdMenuItems)) {
							$menuItemDescription = $menuItemName . (empty($submenuId) ? "" : " Submenu");
							$menuItemId = getFieldFromId("menu_item_id", "menu_items", "description", $menuItemDescription,
								"link_title = ? and page_id <=> ? and menu_id <=> ?", $menuItemName, $pageId, $submenuId);
							if (empty($menuItemId)) {
								$resultSet = executeQuery("insert into menu_items (client_id,description,link_title,menu_id,page_id,administrator_access,logged_in,not_logged_in) values " .
									"(?,?,?,?,?, 1,1,1)", $GLOBALS['gClientId'], $menuItemDescription, $menuItemName, $submenuId, $pageId);
								if (!empty($resultSet['sql_error'])) {
									$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
								$menuItemId = $resultSet['insert_id'];
							}
							$createdMenuItems[$menuItemName] = $menuItemId;
						}
					}
					if (!empty($menuItemId) && !empty($menuId)) {
						$menuContentId = getFieldFromId("menu_content_id", "menu_contents", "menu_id", $menuId, "menu_item_id = ?", $menuItemId);
						if (empty($menuContentId)) {
							$resultSet = executeQuery("insert ignore into menu_contents (menu_id,menu_item_id) values (?,?)", $menuId, $menuItemId);
							if (!empty($resultSet['sql_error'])) {
								$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
						}
					}

					$menuItemId = "";
					if (!empty($pageName)) {
						if (!array_key_exists($pageName, $createdMenuItems)) {
							$menuItemId = getFieldFromId("menu_item_id", "menu_items", "description", $pageName,
								"link_title = ? and page_id <=> ? and menu_id is null", $pageName, $pageId);
							if (empty($menuItemId)) {
								$resultSet = executeQuery("insert into menu_items (client_id,description,link_title,page_id,administrator_access,logged_in,not_logged_in) values " .
									"(?,?,?,?,1,1,1)", $GLOBALS['gClientId'], $pageName, $pageName, $pageId);
								if (!empty($resultSet['sql_error'])) {
									$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
								$menuItemId = $resultSet['insert_id'];
							}
							$createdMenuItems[$pageName] = $menuItemId;
						}
					}
					if (!empty($menuItemId)) {
						if (!empty($submenuId) || (!empty($menuId) && empty($menuItemName))) {
							$thisMenuId = (empty($submenuId) ? $menuId : $submenuId);
							$menuContentId = getFieldFromId("menu_content_id", "menu_contents", "menu_id", $thisMenuId, "menu_item_id = ?", $menuItemId);
							if (empty($menuContentId)) {
								$resultSet = executeQuery("insert ignore into menu_contents (menu_id,menu_item_id) values (?,?)", $thisMenuId, $menuItemId);
								if (!empty($resultSet['sql_error'])) {
									$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}
							}
						}
					}
				}
				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#create_structure", function () {
                if ($("#_edit_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=create_structure", $("#_edit_form").serialize(), function(returnArray) {
                        if (!("error_message" in returnArray)) {
                            $("#content_wrapper").html("<p>Content Created</p>");
                        }
                    });
                }

            });
        </script>
		<?php
	}

	function mainContent() {
		?>
        <div id="content_wrapper">
            <form id="_edit_form">
				<?php echo createFormControl("pages", "template_id", array("not_null" => true)) ?>
				<?php
				$menuStructureControl = new DataColumn("menu_structure_control");
				$menuStructureControl->setControlValue("data_type", "custom");
				$menuStructureControl->setControlValue("control_class", "EditableList");
				$menuName = new DataColumn("menu_name");
				$menuName->setControlValue("data_type", "varchar");
				$menuName->setControlValue("form_label", "Menu Name");
				$menuItemName = new DataColumn("menu_item_name");
				$menuItemName->setControlValue("data_type", "varchar");
				$menuItemName->setControlValue("form_label", "Menu Item Name");
				$submenuName = new DataColumn("submenu_name");
				$submenuName->setControlValue("data_type", "varchar");
				$submenuName->setControlValue("form_label", "Submenu Name");
				$pageName = new DataColumn("page_name");
				$pageName->setControlValue("data_type", "varchar");
				$pageName->setControlValue("form_label", "Page Name");
				$adminFlag = new DataColumn("admin_flag");
				$adminFlag->setControlValue("data_type", "tinyint");
				$adminFlag->setControlValue("form_label", "Admin?");
				$userFlag = new DataColumn("user_flag");
				$userFlag->setControlValue("data_type", "tinyint");
				$userFlag->setControlValue("form_label", "Users?");
				$publicFlag = new DataColumn("public_flag");
				$publicFlag->setControlValue("data_type", "tinyint");
				$publicFlag->setControlValue("form_label", "Public?");
				$columnList = array("menu_name" => $menuName, "menu_item_name" => $menuItemName, "submenu_name" => $submenuName, "page_name" => $pageName, "admin_flag" => $adminFlag, "user_flag" => $userFlag, "public_flag" => $publicFlag);
				$menuStructureEditableList = new EditableList($menuStructureControl, $this);
				$menuStructureEditableList->setColumnList($columnList);
				?>
                <div class='basic-form-line custom-control-no-help custom-control-form-line'>
                    <label>Menu Structure</label>
					<?= $menuStructureEditableList->getControl() ?>
					<div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>
            </form>
            <div class='basic-form-line'>
                <button tabindex="10" id="create_structure">Create Menus & Pages</button>
            </div>
        </div>
		<?php
		return true;
	}

	function jqueryTemplates() {
		$menuStructureControl = new DataColumn("menu_structure_control");
		$menuStructureControl->setControlValue("data_type", "custom");
		$menuStructureControl->setControlValue("control_class", "EditableList");
		$menuName = new DataColumn("menu_name");
		$menuName->setControlValue("data_type", "varchar");
		$menuName->setControlValue("form_label", "Menu Name");
		$menuItemName = new DataColumn("menu_item_name");
		$menuItemName->setControlValue("data_type", "varchar");
		$menuItemName->setControlValue("form_label", "Menu Item Name");
		$submenuName = new DataColumn("submenu_name");
		$submenuName->setControlValue("data_type", "varchar");
		$submenuName->setControlValue("form_label", "Submenu Name");
		$pageName = new DataColumn("page_name");
		$pageName->setControlValue("data_type", "varchar");
		$pageName->setControlValue("form_label", "Page Name");
		$adminFlag = new DataColumn("admin_flag");
		$adminFlag->setControlValue("data_type", "tinyint");
		$adminFlag->setControlValue("form_label", "Admin?");
		$userFlag = new DataColumn("user_flag");
		$userFlag->setControlValue("data_type", "tinyint");
		$userFlag->setControlValue("form_label", "Users?");
		$publicFlag = new DataColumn("public_flag");
		$publicFlag->setControlValue("data_type", "tinyint");
		$publicFlag->setControlValue("form_label", "Public?");
		$columnList = array("menu_name" => $menuName, "menu_item_name" => $menuItemName, "submenu_name" => $submenuName, "page_name" => $pageName, "admin_flag" => $adminFlag, "user_flag" => $userFlag, "public_flag" => $publicFlag);
		$menuStructureEditableList = new EditableList($menuStructureControl, $this);
		$menuStructureEditableList->setColumnList($columnList);
		echo $menuStructureEditableList->getTemplate();
	}

	function internalCSS() {
		?>
        <style>
            #_menu_structure_control_table input[type=text] {
                width: 180px;
            }
        </style>
		<?php
	}
}

$pageObject = new SiteBuilderPage();
$pageObject->displayPage();
