<?php

/*      This software is the unpublished, confidential, proprietary, intellectual
        property of Kim David Software, LLC and may not be copied, duplicated, retransmitted
        or used in any manner without expressed written consent from Kim David Software, LLC.
        Kim David Software, LLC owns all rights to this work and intends to keep this
        software confidential so as to maintain its value as a trade secret.

        Copyright 2004-Present, Kim David Software, LLC.
*/

$GLOBALS['gPageCode'] = "BLOGIMPORT";
require_once "shared/startup.inc";

class BlogImportPage extends Page {

	var $iProgramLogId = "";

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "remove_import":
				$csvImportId = getFieldFromId("csv_import_id", "csv_imports", "csv_import_id", $_GET['csv_import_id']);
				if (empty($csvImportId)) {
					$returnArray['error_message'] = "Invalid CSV Import";
					ajaxResponse($returnArray);
					break;
				}
				$changeLogId = getFieldFromId("log_id", "change_log", "table_name", "contacts", "primary_identifier in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($changeLogId)) {
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to contacts";
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$deleteSet = executeQuery("delete from phone_numbers where contact_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to contacts";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from contact_categories where contact_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to contacts";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from contact_mailing_lists where contact_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to contacts";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from users where contact_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to contacts";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from contact_redirect where contact_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to contacts";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from contacts where contact_id in (select primary_identifier from csv_import_details where csv_import_id = ?)", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to contacts";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_import_details where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to remove import due to use of or changes to contacts";
					ajaxResponse($returnArray);
					break;
				}

				$deleteSet = executeQuery("delete from csv_imports where csv_import_id = ?", $csvImportId);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = $deleteSet['sql_error'];
					ajaxResponse($returnArray);
					break;
				}

				$returnArray['info_message'] = "Import successfully removed";
				$returnArray['csv_import_id'] = $csvImportId;
				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				ajaxResponse($returnArray);

				break;
			case "select_posts":
				$pageId = $GLOBALS['gAllPageCodes']["POSTMAINT"];
				executeQuery("delete from selected_rows where user_id = ? and page_id = ?", $GLOBALS['gUserId'], $pageId);
				executeQuery("insert into selected_rows (user_id,page_id,primary_identifier) select " . $GLOBALS['gUserId'] . "," . $pageId .
					",primary_identifier from csv_import_details where csv_import_id = ?", $_GET['csv_import_id']);
				$returnArray['info_message'] = "Posts selected in Post Maintenance program";
				ajaxResponse($returnArray);
				break;
			case "import_csv":
				if (!array_key_exists("csv_file", $_FILES)) {
					$returnArray['error_message'] = "No File uploaded";
					ajaxResponse($returnArray);
					break;
				}

				$GLOBALS['gStartTime'] = getMilliseconds();
				$this->iProgramLogId = addProgramLog((getMilliseconds() - $GLOBALS['gStartTime']) . ": Start Import", $this->iProgramLogId);
				$fileContents = file_get_contents($_FILES['csv_file']['tmp_name']);
				$hashCode = md5($fileContents);
				$csvImportId = getFieldFromId("csv_import_id", "csv_imports", "hash_code", $hashCode);
				if (!empty($csvImportId)) {
					$returnArray['error_message'] = "This file has already been imported.";
					ajaxResponse($returnArray);
					break;
				}

				$rss = new DOMDocument();
				$rss->load($_FILES['csv_file']['tmp_name']);
				$postArray = array();
				$imageArray = array();
				foreach ($rss->getElementsByTagName('item') as $node) {

					// Changes to enable import from Fusion Builder posts:
					//  1. ignore [fusion / [/fusion tags in HTML
					//  2. if a meta tag contains an <iframe, pull that in as the video at the start of the post.
					//  3. if the post does not contain any thumbnails, pull a thumbnail from the youtube video.
					$thumbnailId = "";
					$videoHtml = "";
					$postMetadataNode = $node->getElementsByTagName("postmeta");
					foreach ($postMetadataNode as $searchNode) {
						$thisKey = $searchNode->getElementsByTagName('meta_key')->item(0)->nodeValue;
						$thisValue = $searchNode->getElementsByTagName('meta_value')->item(0)->nodeValue;
						if ($thisKey == "_thumbnail_id") {
							$thumbnailId = $thisValue;
						} else if (strpos($thisValue, '<iframe') !== false) {
							$iframeStart = strpos($thisValue, '<iframe');
							$iframeEnd = strpos($thisValue, '</iframe>');
							$videoHtml = substr($thisValue, $iframeStart, $iframeEnd - $iframeStart + 9);
						}
					}

					$categories = "";
					$categoryNode = $node->getElementsByTagName("category");
					foreach ($categoryNode as $searchNode) {
						$thisCategory = $searchNode->getAttribute('nicename');
						$categories .= (empty($categories) ? "" : ",") . $thisCategory;
					}

					$imageUrl = "";
					$guidNode = $node->getElementsByTagName("guid");
					foreach ($guidNode as $searchNode) {
						$thisUrl = $searchNode->nodeValue;
						if (strpos($thisUrl, "upload") !== false && empty($imageUrl)) {
							$imageUrl = $thisUrl;
							break;
						}
					}
					// If no thumbnail image, pull from Youtube if possible
					if (empty($imageUrl) && !empty($videoHtml)) {
						$isYoutube = (substr(strtolower($videoHtml), "youtube") !== false);
						if ($isYoutube) {
							$idStart = (strpos($videoHtml, "watch") !== false ? strpos($videoHtml, "watch") + 6 : strpos($videoHtml, "embed") + 6);
							$idEnd = (strpos($videoHtml, "?") !== false ? strpos($videoHtml, "?") : strpos($videoHtml, '"', $idStart));
							$ytId = substr($videoHtml, $idStart, $idEnd - $idStart);
							$imageUrl = "https://img.youtube.com/vi/$ytId/maxresdefault.jpg";
						}
					}
					$wpPostId = $node->getElementsByTagName('post_id')->item(0)->nodeValue;
					if (!empty($imageUrl)) {
						$filenameParts = explode('.', $imageUrl);
						if (!empty($wpPostId)) {
							$extension = strtolower($filenameParts[count($filenameParts) - 1]);
							$imageContent = file_get_contents($imageUrl);
							$imageId = createImage(array("extension" => $extension, "file_content" => $imageContent, "name" => getRandomString(6) . ".jpg", "description" => "Post Image"));
							if (!empty($imageId)) {
								$imageArray[$wpPostId] = $imageId;
							}
						}
					}

					$content = $node->getElementsByTagName('encoded')->item(0)->nodeValue;
					$contentLines = getContentLines($content);
					$revisedContent = array();
					if (!empty($videoHtml)) {
						$revisedContent[] = $videoHtml;
					}
					$excerpt = "";
					foreach ($contentLines as $thisLine) {
						if (substr($thisLine, 0, 1) == "<") {
							$revisedContent[] = $thisLine;
						} elseif (substr($thisLine, 0, 7) != "[fusion" && substr($thisLine, 0, 8) != "[/fusion") {
							$revisedContent[] = "<p>" . $thisLine . "</p>";
							if (empty($excerpt)) {
								$excerpt = getFirstPart($thisLine, 200, true, true);
							}
						}
					}
					$linkNameParts = explode("/", $node->getElementsByTagName('link')->item(0)->nodeValue);
					$linkName = "";
					foreach ($linkNameParts as $thisPart) {
						if (empty($thisPart)) {
							continue;
						}
						$linkName = $thisPart;
					}
					$content = implode("\n", $revisedContent);
					if (strlen($content) < 100 || count($revisedContent) < 2) {
						continue;
					}
					$item = array(
						'title_text' => $node->getElementsByTagName('title')->item(0)->nodeValue,
						'image_url' => $imageUrl,
						'link_name' => $linkName,
						'publish_time' => date("Y-m-d H:i:s", strtotime($node->getElementsByTagName('pubDate')->item(0)->nodeValue)),
						'content' => implode("\n", $revisedContent),
						'excerpt' => $excerpt,
						'thumbnail_id' => $wpPostId,
						'categories' => $categories
					);
					array_push($postArray, $item);
				}

				$skipCount = 0;
				$postCount = 0;
				foreach ($postArray as $thisPost) {
					$blogPostId = getFieldFromId("post_id", "posts", "link_name", $thisPost['link_name']);
					if (!empty($blogPostId) || empty($thisPost['excerpt']) || empty($thisPost['content']) || empty($thisPost['title_text']) || strlen($thisPost['content']) < 100) {
						$skipCount++;
						continue;
					}

					$imageId = $imageArray[$thisPost['thumbnail_id']];

					$resultSet = executeQuery("insert into posts (client_id,creator_user_id,date_created,title_text,content, excerpt,image_id,link_name," .
						"public_access,allow_comments,approve_comments,publish_time,published) values (?,?,now(),?,?, ?,?,?,1,1, 1,?,1)",
						$GLOBALS['gClientId'], $GLOBALS['gUserId'], $thisPost['title_text'], $thisPost['content'], $thisPost['excerpt'], $imageId, $thisPost['link_name'],
						$thisPost['publish_time']);
					if (!empty($resultSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = $resultSet['sql_error'] . " - " . $resultSet['query'] . " - " . jsonEncode($thisPost);
						ajaxResponse($returnArray);
						break;
					}
					$postId = $resultSet['insert_id'];
					if (!empty($thisPost['categories'])) {
						$categoryCodes = explode(",", $thisPost['categories']);
						foreach ($categoryCodes as $categoryCode) {
							if (empty($categoryCode)) {
								continue;
							}
							$description = ucwords(str_replace("-", " ", strtolower($categoryCode)));
							$categoryCode = makeCode($categoryCode);
							$postCategoryId = getFieldFromId("post_category_id", "post_categories", "post_category_code", $categoryCode);
							if (empty($postCategoryId)) {
								$insertSet = executeQuery("insert into post_categories (client_id,post_category_code,description) values (?,?,?)", $GLOBALS['gClientId'], $categoryCode, $description);
								$postCategoryId = $insertSet['insert_id'];
							}
							if (!empty($postCategoryId)) {
								executeQuery("insert into post_category_links (post_id,post_category_id) values (?,?)", $postId, $postCategoryId);
							}
						}
					}
					$postCount++;

					executeQuery("insert into csv_import_details (csv_import_id,primary_identifier) values (?,?)", $csvImportId, $postId);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = $returnArray['import_error'] = $insertSet['sql_error'] . " - " . $insertSet['query'];
						ajaxResponse($returnArray);
						break;
					}
				}
				$this->iProgramLogId = addProgramLog((getMilliseconds() - $GLOBALS['gStartTime']) . ": Finished processing", $this->iProgramLogId);
				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$returnArray['response'] .= "<p>" . $postCount . " posts imported.</p>";
				$returnArray['response'] .= "<p>" . $skipCount . " posts skipped.</p>";
				ajaxResponse($returnArray);
				break;
		}

	}

	function mainContent() {
		echo $this->iPageData['content'];

		?>
        <div id="_form_div">
            <form id="_edit_form" enctype='multipart/form-data'>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="description" class="required-label">Description</label>
                    <input tabindex="10" class="validate[required]" size="40" type="text" id="description"
                           name="description">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div class="basic-form-line" id="_csv_file_row">
                    <label for="csv_file" class="required-label">CSV File</label>
                    <input tabindex="10" class="validate[required]" type="file" id="csv_file" name="csv_file">
                    <div class='basic-form-line-messages'><span class="help-label"></span><span class='field-error-text'></span></div>
                </div>

                <div id="import_error"></div>

                <div class="basic-form-line">
                    <button tabindex="10" id="_submit_form">Import</button>
                    <div id="import_message"></div>
                </div>

            </form>
        </div> <!-- form_div -->

        <table class="grid-table">
            <tr>
                <th>Description</th>
                <th>Imported On</th>
                <th>By</th>
                <th>Count</th>
                <th></th>
				<?php if (canAccessPage("POSTMAINT")) { ?>
                    <th></th>
				<?php } ?>
            </tr>
			<?php
			$resultSet = executeQuery("select * from csv_imports where table_name = 'posts' and client_id = ? order by time_submitted desc", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$importCount = 0;
				$countSet = executeQuery("select count(*) from csv_import_details where csv_import_id = ?", $row['csv_import_id']);
				if ($countRow = getNextRow($countSet)) {
					$importCount = $countRow['count(*)'];
				}
				$minutesSince = (time() - strtotime($row['time_submitted'])) / 60;
				$canUndo = $minutesSince < 48;
				?>
                <tr id="csv_import_id_<?= $row['csv_import_id'] ?>" class="import-row"
                    data-csv_import_id="<?= $row['csv_import_id'] ?>">
                    <td><?= htmlText($row['description']) ?></td>
                    <td><?= date("m/d/Y g:i a", strtotime($row['time_submitted'])) ?></td>
                    <td><?= getUserDisplayName($row['user_id']) ?></td>
                    <td><?= $importCount ?></td>
                    <td><?= ($canUndo ? "<span class='far fa-undo remove-import'></span>" : "") ?></td>
					<?php if (canAccessPage("POSTMAINT")) { ?>
                        <td><span class='far fa-check-square select-posts'></span></td>
					<?php } ?>
                </tr>
				<?php
			}
			?>
        </table>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".select-posts", function () {
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=select_posts&csv_import_id=" + $(this).closest("tr").data("csv_import_id"));
            });
            $(document).on("click", ".remove-import", function () {
                const csvImportId = $(this).closest("tr").data("csv_import_id");
                $('#_confirm_undo_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Remove Import',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=remove_import&csv_import_id=" + csvImportId, function(returnArray) {
                                if ("csv_import_id" in returnArray) {
                                    $("#csv_import_id_" + returnArray['csv_import_id']).remove();
                                }
                            });
                            $("#_confirm_undo_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_confirm_undo_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("tap click", "#_submit_form", function () {
                const $submitForm = $("#_submit_form");
                const $editForm = $("#_edit_form");
                const $postIframe = $("#_post_iframe");
                if ($submitForm.data("disabled") === "true") {
                    return false;
                }
                getElapsedTime("start import");
                if ($editForm.validationEngine("validate")) {
                    disableButtons($submitForm);
                    $("body").addClass("waiting-for-ajax");
                    $editForm.attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=import_csv").attr("method", "POST").attr("target", "post_iframe").submit();
                    $postIframe.off("load");
                    $postIframe.on("load", function () {
                        $("body").removeClass("no-waiting-for-ajax").removeClass("waiting-for-ajax");
                        const returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            enableButtons($submitForm);
                            return;
                        }
                        getElapsedTime("end import");
                        if ("import_error" in returnArray) {
                            $("#import_error").html(returnArray['import_error']);
                        }
                        if ("response" in returnArray) {
                            $("#_form_div").html(returnArray['response']);
                        }
                        enableButtons($submitForm);
                    });
                }
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #import_error {
                color: rgb(192, 0, 0);
            }

            .remove-import {
                cursor: pointer;
            }

            .select-posts {
                cursor: pointer;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_confirm_undo_dialog" class="dialog-box">
            This will result in these posts being removed. Are you sure?
        </div> <!-- confirm_undo_dialog -->
		<?php
	}
}

$pageObject = new BlogImportPage();
$pageObject->displayPage();
