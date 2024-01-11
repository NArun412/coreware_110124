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

$GLOBALS['gPageCode'] = "CHANGETEMPLATE";
require_once "shared/startup.inc";
$GLOBALS['gDefaultAjaxTimeout'] = 1200000;

class ChangeTemplatePage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "change_template":
				$templateGroupCode = $this->getPageTextChunk("template_group_code");
				if (empty($templateGroupCode)) {
					$templateGroupCode = "COREFIRE";
				}
				$templateGroupId = getFieldFromId("template_group_id", "template_groups", "template_group_code", $templateGroupCode, "inactive = 0 and client_id = ?", $GLOBALS['gDefaultClientId']);
				$templateId = getFieldFromId("template_id", "templates", "template_id", $_GET['template_id'], "client_id = ? and inactive = 0 and template_group_id = ?", $GLOBALS['gDefaultClientId'], $templateGroupId);
				if (empty($templateId)) {
					$returnArray['error_message'] = "Invalid template";
					ajaxResponse($returnArray);
					exit;
				}
				$resultSet = executeQuery("select template_id from templates where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
				if ($resultSet['row_count'] != 1) {
					$returnArray['error_message'] = "The client must only have ONE active template in order to change templates.";
					ajaxResponse($returnArray);
					exit;
				}
				if ($row = getNextRow($resultSet)) {
					$existingTemplateId = $row['template_id'];
				}
				$GLOBALS['gChangeLogNotes'] = "Client changed template";
				$existingTemplateRow = getRowFromId("templates", "template_id", $existingTemplateId);
				$templateRow = getRowFromId("templates", "template_id", $templateId, "client_id = ?", $GLOBALS['gDefaultClientId']);
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				if (empty($templateRow['css_file_id'])) {
					$existingTemplateRow['css_file_id'] = "";
				} else {
					$cssFileRow = getRowFromId("css_files", "css_file_id", $templateRow['css_file_id'], "client_id = ?", $GLOBALS['gDefaultClientId']);
					$cssFileId = getFieldFromId("css_file_id", "css_files", "css_file_code", $cssFileRow['css_file_code']);
					if (empty($cssFileId)) {
						$insertSet = executeQuery("insert into css_files (client_id,css_file_code,description,content) values (?,?,?,?)",
							$GLOBALS['gClientId'], $cssFileRow['css_file_code'], $cssFileRow['description'], $cssFileRow['content']);
						$cssFileId = $insertSet['insert_id'];
					}
					$existingTemplateRow['css_file_id'] = $cssFileId;
				}
				$dataTable = new DataTable("templates");
				foreach (array("description", "css_content", "javascript_code", "content") as $fieldName) {
					$existingTemplateRow[$fieldName] = $templateRow[$fieldName];
				}
				if (!$dataTable->saveRecord(array("name_values" => $existingTemplateRow, "primary_id" => $existingTemplateId))) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $dataTable->getErrorMessage();
					ajaxResponse($returnArray);
					break;
				}
				addDebugLog("Change Template: updated template", true);
				executeQuery("delete from template_data_uses where template_id = ?", $existingTemplateId);
				$insertSet = executeQuery("insert into template_data_uses (template_data_id,template_id,sequence_number) select template_data_id,?,sequence_number from template_data_uses where template_id = ?", $existingTemplateId, $templateId);
				if (!empty($insertSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $insertSet['sql_error'];
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("delete from template_sass_headers where template_id = ?", $existingTemplateId);
				executeQuery("delete from sass_headers where client_id = ?", $GLOBALS['gClientId']);
				$resultSet = executeQuery("select * from sass_headers join template_sass_headers using (sass_header_id) where template_id = ?", $templateId);
				while ($row = getNextRow($resultSet)) {
					$sassHeaderId = getFieldFromId("sass_header_id", "sass_headers", "sass_header_code", $row['sass_header_code']);
					if (empty($sassHeaderId)) {
						$insertSet = executeQuery("insert into sass_headers (client_id,sass_header_code,description,content) values (?,?,?,?)", $GLOBALS['gClientId'], $row['sass_header_code'], $row['description'], $row['content']);
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $insertSet['sql_error'];
							ajaxResponse($returnArray);
							break;
						}
						$sassHeaderId = $insertSet['insert_id'];
					}
					$insertSet = executeQuery("insert into template_sass_headers (template_id,sass_header_id,sequence_number) values (?,?,?)", $existingTemplateId, $sassHeaderId, $row['sequence_number']);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $insertSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					}
				}

				# Copy Images

                $imageRow = getRowFromId("images","image_code","HEADER_LOGO");
				if (empty($imageRow['file_content']) && !empty($imageRow['os_filename'])) {
					$imageContents = getExternalImageContents($imageRow['os_filename']);
				} else {
					$imageContents = $imageRow['file_content'];
				}
                addDebugLog("Creating: " . $templateRow['template_code'] . "_HEADER_LOGO",true);
                $logoHeaderImageId = createImage(array("file_content" => $imageContents, "extension"=>$imageRow['extension'], "maximum_width" => 500, "maximum_height" => 500, "image_code" => $templateRow['template_code'] . "_HEADER_LOGO", "client_id" => $GLOBALS['gClientId']));
                if (empty($logoHeaderImageId)) {
	                $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
	                $returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $insertSet['sql_error'];
	                ajaxResponse($returnArray);
	                break;
                }

				$imageIdTranslations = array();
				$resultSet = executeQuery("select * from images where client_id = ? and (image_id in (select image_id from album_images where album_id = " .
					"(select album_id from albums where album_code = ? and client_id = ?)) or image_id in (select image_id from template_images where template_id = ?))",
					$GLOBALS['gDefaultClientId'], $templateRow['template_code'], $GLOBALS['gDefaultClientId'], $templateId);
				while ($row = getNextRow($resultSet)) {
					$imageId = getFieldFromId("image_id", "images", "image_code", $row['image_code']);
					if (!empty($imageId)) {
                        addDebugLog("Found existing image: " . $row['image_code'],true);
						$imageIdTranslations[$row['image_id']] = $imageId;
						continue;
					}
                    addDebugLog("Creating Image: " . $row['image_code']);
					if (empty($row['file_content']) && !empty($row['os_filename'])) {
						$imageContents = getExternalImageContents($row['os_filename']);
					} else {
						$imageContents = $row['file_content'];
					}
					if (empty($imageContents)) {
						continue;
					}
					$imageId = createImage(array("client_id" => $GLOBALS['gClientId'], "image_code" => $row['image_code'], "extension" => $row['extension'], "file_content" => $imageContents, "name" => $row['file_name'], "description" => $row['description'], "detailed_description" => $row['detailed_description']));
					$imageIdTranslations[$row['image_id']] = $imageId;
				}
				addDebugLog("Change Template: Added Images", true);

				# copy pages

				$resultSet = executeQuery("select * from pages where template_id = ?", $templateId);
				$updateFields = array("description", "meta_description", "meta_keywords", "link_name", "script_filename", "script_arguments", "login_script", "window_scription",
					"window_title", "header_includes", "javascript_code", "css_content", "allow_cache", "exclude_sitemap", "not_searchable");
				while ($row = getNextRow($resultSet)) {
					$pageId = getFieldFromId("page_id", "pages", "link_name", $row['link_name'], "template_id = ?", $existingTemplateId);
					if (empty($pageId)) {
						$pageId = getFieldFromId("page_id", "pages", "script_filename", $row['script_filename'], "template_id = ?", $existingTemplateId);
					}
					if (empty($pageId)) {
						$pageId = getFieldFromId("page_id", "pages", "page_code", $row['page_code'], "template_id = ?", $existingTemplateId);
					}
					$dataTable = new DataTable("pages");
					$dataTable->setSaveOnlyPresent(true);
					if (empty($pageId)) {
						addDebugLog("Change Template: adding new page - " . $row['description'], true);
						$row['page_id'] = "";
						$row['client_id'] = $GLOBALS['gClientId'];
						$row['date_created'] = date("Y-m-d");
						$row['template_id'] = $existingTemplateId;
						$originalPageCode = $row['page_code'];
						$row['page_code'] = $row['page_code'] . "_" . $GLOBALS['gClientRow']['client_code'];
						if (!$pageId = $dataTable->saveRecord(array("name_values" => $row, "primary_id" => ""))) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $dataTable->getErrorMessage();
							ajaxResponse($returnArray);
							break;
						}
					} else {
						addDebugLog("Change Template: updating existing page - " . $row['description'], true);
						$nameValues = array();
						foreach ($updateFields as $fieldName) {
							$nameValues[$fieldName] = $row[$fieldName];
						}
						if (!$pageId = $dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $pageId))) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $dataTable->getErrorMessage();
							ajaxResponse($returnArray);
							break;
						}
					}
					executeQuery("delete from page_data where page_id = ?", $pageId);
					$insertSet = executeQuery("insert into page_data (page_id,template_data_id,sequence_number,integer_data,number_data,text_data,date_data,image_id,file_id) select ?,template_data_id,sequence_number,integer_data,number_data,text_data,date_data,image_id,file_id from page_data where page_id = ?", $pageId, $row['page_id']);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $insertSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					}
					executeQuery("delete from page_text_chunks where page_id = ?", $pageId);
					$insertSet = executeQuery("insert into page_text_chunks (page_text_chunk_code,page_id,description,content) select page_text_chunk_code,?,description,content from page_text_chunks where page_id = ?", $pageId, $row['page_id']);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $insertSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					}
				}
				addDebugLog("Change Template: copy pages", true);

				# Create Banners

				$bannerGroupTranslations = array();
				$bannerGroupSet = executeQuery("select * from banner_groups where client_id = ? and banner_group_id in (select banner_group_id from template_banner_groups where template_id = ?)", $GLOBALS['gDefaultClientId'], $templateId);
				while ($bannerGroupRow = getNextRow($bannerGroupSet)) {
					$bannerGroupId = getFieldFromId("banner_group_id", "banner_groups", "banner_group_code", $bannerGroupRow['banner_group_code']);
					if (!empty($bannerGroupId)) {
						$bannerGroupTranslations[$bannerGroupRow['banner_group_id']] = $bannerGroupId;
						continue;
					}
					$insertSet = executeQuery("insert into banner_groups (client_id,banner_group_code,description) values (?,?,?)", $GLOBALS['gClientId'], $bannerGroupRow['banner_group_code'], $bannerGroupRow['description']);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $insertSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					}
					$bannerGroupTranslations[$bannerGroupRow['banner_group_id']] = $insertSet['insert_id'];
				}
				foreach ($bannerGroupTranslations as $oldBannerGroupId => $newBannerGroupId) {
					executeQuery("delete from banner_group_links where banner_group_id = ?", $newBannerGroupId);
					$resultSet = executeQuery("select * from banners join banner_group_links using (banner_id) where banner_group_id = ? and inactive = 0 order by sequence_number", $oldBannerGroupId);
					$sequenceNumber = 0;
					while ($row = getNextRow($resultSet)) {
						$bannerId = getFieldFromId("banner_id", "banners", "banner_code", $row['banner_code']);
						if (empty($bannerId)) {
							$imageId = "";
							if (!empty($row['image_id'])) {
								$imageId = $imageIdTranslations[$row['image_id']];
								if (empty($imageId)) {
									$imageRow = getRowFromId("images", "image_id", $row['image_id'],"client_id is not null");
									if (empty($imageRow['file_content']) && !empty($imageRow['os_filename'])) {
										$imageContents = getExternalImageContents($imageRow['os_filename']);
									} else {
										$imageContents = $imageRow['file_content'];
									}
                                    if (empty($imageContents)) {
	                                    $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
	                                    $returnArray['error_message'] = "contents for image are empty: " . __LINE__ . ":" . jsonEncode($imageRow);
	                                    ajaxResponse($returnArray);
	                                    break;
                                    }
									if (!empty($imageContents)) {
										$imageId = getFieldFromId("image_id", "images", "image_code", $imageRow['image_code'], "client_id = ?", $GLOBALS['gClientId']);
										if (empty($imageId)) {
											$imageId = createImage(array("client_id" => $GLOBALS['gClientId'], "image_code" => $imageRow['image_code'], "extension" => $imageRow['extension'], "file_content" => $imageContents, "name" => $imageRow['file_name'], "description" => $imageRow['description'], "detailed_description" => $imageRow['detailed_description']));
											if (empty($imageId)) {
												$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
												$returnArray['error_message'] = "Unable to create image: " . __LINE__ . ":" . strlen($imageContents);
												ajaxResponse($returnArray);
												break;
											}
										}
										$imageIdTranslations[$row['image_id']] = $imageId;
									}
								}
							}
							$insertSet = executeQuery("insert into banners (client_id,banner_code,description,content,css_classes,image_id,link_url,domain_name,sort_order,use_content,hide_description) values (?,?,?,?,?, ?,?,?,?,?, ?)",
								$GLOBALS['gClientId'], $row['banner_code'], $row['description'], $row['content'], $row['css_classes'], $imageId, $row['link_url'], $row['domain_name'], $row['sort_order'], $row['use_content'], $row['hide_description']);
							if (!empty($insertSet['sql_error'])) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $insertSet['sql_error'];
								ajaxResponse($returnArray);
								break;
							}
                            $bannerId = $insertSet['insert_id'];
						}
						$insertSet = executeQuery("insert into banner_group_links (banner_group_id,banner_id,sequence_number) values (?,?,?)", $newBannerGroupId, $bannerId, $row['sequence_number']);
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $insertSet['sql_error'];
							ajaxResponse($returnArray);
							break;
						}
					}
				}
				addDebugLog("Change Template: copy banner", true);

				$menuIdTranslations = array();
				$menuSet = executeQuery("select * from menus where client_id = ? and (menu_code = 'WEBSITE_MENU' or menu_id in (select menu_id from template_menus where template_id = ?))", $GLOBALS['gDefaultClientId'], $templateId);
				while ($menuRow = getNextRow($menuSet)) {
					$existingMenuId = getFieldFromId("menu_id", "menus", "menu_code", $menuRow['menu_code'], "client_id = ?", $GLOBALS['gClientId']);
					if (!empty($existingMenuId)) {
						$menuIdTranslations[$menuRow['menu_id']] = $existingMenuId;
						continue;
					}
					$insertSet = executeQuery("insert into menus (client_id,menu_code,description) values (?,?,?)", $GLOBALS['gClientId'], $menuRow['menu_code'], $menuRow['description']);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $insertSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					}
					$newMenuId = $insertSet['insert_id'];
					$menuIdTranslations[$menuRow['menu_id']] = $newMenuId;
				}
				$menuItemIdArray = array();
				foreach ($menuIdTranslations as $oldMenuId => $newMenuId) {
					$resultSet = executeQuery("select * from menu_contents join menu_items using (menu_item_id) where menu_contents.menu_id = ? order by sequence_number", $oldMenuId);
					$sequenceNumber = 0;
					while ($row = getNextRow($resultSet)) {
						$linkName = getFieldFromId("link_name", "pages", "page_id", $row['page_id']);
						$pageId = getFieldFromId("page_id", "pages", "link_name", $linkName);
						$menuId = $menuIdTranslations[$row['menu_id']];
						if (empty($pageId) && empty($menuId)) {
							continue;
						}
						$menuItemId = $menuItemIdArray[$row['menu_item_id']];
						if (empty($menuItemId)) {
							$menuItemId = getFieldFromId("menu_item_id", "menu_items", "client_id", $GLOBALS['gClientId'], "menu_id <=> ? and page_id <=> ?", $menuId, $pageId);
							if (empty($menuItemId)) {
								$insertSet = executeQuery("insert into menu_items (client_id,description,link_title,link_url,list_item_identifier,list_item_classes,menu_id,page_id,query_string,not_logged_in,logged_in,administrator_access) values (?,?,?,?,?, ?,?,?,?,?, ?,?)",
									$GLOBALS['gClientId'], $row['description'], $row['link_title'], $row['link_url'], $row['list_item_identifier'], $row['list_item_classes'], $menuId, $pageId, $row['query_string'], $row['not_logged_in'], $row['logged_in'], $row['administrator_access']);
								if (!empty($insertSet['sql_error'])) {
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									$returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $insertSet['sql_error'];
									ajaxResponse($returnArray);
									break;
								}
								$menuItemId = $insertSet['insert_id'];
							}
							$menuItemIdArray[$row['menu_item_id']] = $menuItemId;
						}
						$insertSet = executeQuery("insert into menu_contents (menu_id,menu_item_id,sequence_number) values (?,?,?)", $newMenuId, $menuItemId, $row['sequence_number']);
						if (!empty($insertSet['sql_error'])) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $insertSet['sql_error'];
							ajaxResponse($returnArray);
							break;
						}
					}
				}
				addDebugLog("Change Template: Copy menus", true);

				$resultSet = executeQuery("select * from fragments where fragment_id in (select fragment_id from template_fragments where template_id = ?)", $templateId);
				while ($row = getNextRow($resultSet)) {
					$fragmentId = getFieldFromId("fragment_id", "fragments", "fragment_code", $row['fragment_code']);
					if (!empty($fragmentId)) {
						continue;
					}
					$insertSet = executeQuery("insert into fragments (client_id,fragment_code,description,detailed_description,content) " .
						"values (?,?,?,?,?)", $GLOBALS['gClientId'], $row['fragment_code'], $row['description'], $row['detailed_description'], $row['content']);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = "Unable to change template. Please contact customer service: " . __LINE__ . ":" . $insertSet['sql_error'];
						ajaxResponse($returnArray);
						break;
					}
				}
				addDebugLog("Change Template: copy fragments", true);
				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				clearClientCache();
				removeCachedData("sass_headers", $existingTemplateId,true);
				removeCachedData("template_row", $existingTemplateId,true);
                $resultSet = executeQuery("select * from pages where client_id = ?",$GLOBALS['gClientId']);
                while ($row = getNextRow($resultSet)) {
	                removeCachedData($GLOBALS['gPrimaryDatabase']->getName() . "-page_row_by_code", $row['page_code'], true);
	                removeCachedData($GLOBALS['gPrimaryDatabase']->getName() . "-page_row_by_id", $row['page_id'], true);
	                removeCachedData("page_contents", $row['page_code']);
                }

				ajaxResponse($returnArray);
				exit;
		}
	}

	function internalCSS() {
		?>
        <style>
            #change_template_wrapper ul {
                padding-left: 20px;
                list-style-type: disc;
                font-size: 10px;
                padding-bottom: 10px;
            }

            #change_template_wrapper ul li {
                list-style-type: disc;
                font-size: 10px;
            }
        </style>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", "#change_template", function () {
                if (empty($("#template_id").val())) {
                    return;
                }
                if (!$("#understand_risks").prop("checked")) {
                    displayErrorMessage("Check the box that you understand the risks");
                    return;
                }
                $(this).addClass("hidden");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=change_template&template_id=" + $("#template_id").val(), function (returnArray) {
                    if ("error_message" in returnArray) {
                        $("#change_template").removeClass("hidden");
                    } else {
                        $("div#change_template_wrapper").html("<p>Your template has been changed</p>");
                    }
                });
            });
        </script>
		<?php
	}

	function mainContent() {
		?>
        <div id="change_template_wrapper">
			<?php
			if ($GLOBALS['gClientId'] == $GLOBALS['gDefaultClientId']) {
				?>
                <p>This page can only be used on client sites. It is not applicable to the primary client.</p>
				<?php
				return true;
			}
			$resultSet = executeQuery("select count(*) from templates where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				if ($row['count(*)'] != 1) {
					?>
                    <p>The client must only have ONE active template in order to change templates.</p>
					<?php
				}
			}
			$templateGroupCode = $this->getPageTextChunk("template_group_code");
			if (empty($templateGroupCode)) {
				$templateGroupCode = "COREFIRE";
			}
			$templateGroupId = getFieldFromId("template_group_id", "template_groups", "template_group_code", $templateGroupCode, "inactive = 0 and client_id = ?", $GLOBALS['gDefaultClientId']);
			$resultSet = executeQuery("select * from templates where client_id = ? and template_group_id = ?", $GLOBALS['gDefaultClientId'], $templateGroupId);
			if ($resultSet['row_count'] == 0) {
				?>
                <p>No templates available for changing.</p>
				<?php
				return true;
			}
			?>
            <H3>Change Site Template</H3>
            <p>Changing the site template is not a trivial task. There might be wide variations in the design of different templates. Some cautions and things to note about this change:</p>
            <ul>
                <li>ANY custom changes you or Coreware staff has made to your template or your pages, including custom images, will be lost in this process.</li>
                <li>While you can change BACK to your original site, the end result might not be the same.</li>
                <li>Every page that uses your old template will be changed to use this new template.</li>
                <li>We have tested changing multiple combinations of templates. However, though remote, there is a risk that changing your template <strong>could</strong> make your site unusable and result in billable time by Coreware staff to fix it.</li>
            </ul>
            <div class='basic-form-line'>
                <label>New Template</label>
                <select id='template_id' name='template_id'>
                    <option value=''>[Select]</option>
					<?php
					while ($row = getNextRow($resultSet)) {
						?>
                        <option value='<?= $row['template_id'] ?>'><?= htmlText($row['description']) ?></option>
						<?php
					}
					?>
                </select>
            </div>
            <p><input type='checkbox' id='understand_risks' name='understand_risks'><label class='checkbox-label' for='understand_risks'>I understand the risks and still want to change my template.</label></p>
            <p>
                <button id='change_template'>Change Template</button>
            </p>
        </div>
		<?php
	}
}

$pageObject = new ChangeTemplatePage();
$pageObject->displayPage();
