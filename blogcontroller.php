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

$GLOBALS['gPageCode'] = "BLOGFEED";
require_once "shared/startup.inc";

class BlogFeedPage extends Page {

	function executePageUrlActions() {
		switch ($_GET['url_action']) {
			case "subscribe":
				$returnArray = array();
				$contactId = "";
				if ($GLOBALS['gLoggedIn']) {
					$contactId = $GLOBALS['gUserRow']['contact_id'];
				} else {
					$emailAddress = $_POST['subscribe_email'];
					if (!empty($emailAddress)) {
						$resultSet = executeQuery("select contact_id from contacts where email_address = ?", $emailAddress);
						if ($row = getNextRow($resultSet)) {
							$contactId = $row['contact_id'];
						} else {
							$sourceId = getFieldFromId("source_id", "sources", "source_id", $_COOKIE['source_id'], "inactive = 0");
							if (empty($sourceId)) {
								$sourceId = getSourceFromReferer($_SERVER['HTTP_REFERER']);
							}
							$contactDataTable = new DataTable("contacts");
							$contactId = $contactDataTable->saveRecord(array("name_values" => array("email_address" => $emailAddress,"source_id"=>$sourceId)));
						}
					}
					makeWebUserContact($contactId);
				}
				if (!empty($contactId)) {
					$postCategoryId = getFieldFromId("post_category_id", "post_categories", "post_category_id",
						$_POST['subscribe_post_category_id'], "inactive = 0 and client_id = ?" .
						($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"), $GLOBALS['gClientId']);
					$resultSet = executeQuery("select * from blog_subscriptions where contact_id = ? and (post_category_id <=> ? or post_category_id is null)", $contactId, $postCategoryId);
					if (!$row = getNextRow($resultSet)) {
						executeQuery("insert into blog_subscriptions (post_category_id,contact_id,start_date) values (?,?,now())", $postCategoryId, $contactId);
					}
				} else {
					$returnArray['error_message'] = "Unable to subscribe to blog";
				}
				$taskSourceId = getFieldFromId("task_source_id", "task_sources", "task_source_code", "BLOG");
				if (!empty($contactId) && !empty($taskSourceId)) {
					executeQuery("insert into tasks (client_id,contact_id,description,date_completed,simple_contact_task,task_source_id) values " .
						"(?,?,'Subscribe to Blog',now(),1,?)", $GLOBALS['gClientId'], $contactId, $taskSourceId);
				}
				ajaxResponse($returnArray);
				break;
			case "publish":
				$returnArray = array();
				if (!$GLOBALS['gUserRow']['administrator_flag']) {
					$returnArray['error_message'] = "Unable to publish post";
					ajaxResponse($returnArray);
					break;
				}
				$postId = $_GET['post_id'];
				if (empty($postId)) {
					$postId = $_POST['page_post_id'];
				}
				$postId = getFieldFromId("post_id", "posts", "post_id", $postId, "published = 0 and client_id = " . $GLOBALS['gClientId'] .
					($GLOBALS['gUserRow']['superuser_flag'] ? "" : " and creator_user_id = " . $GLOBALS['gUserId']));
				if (!empty($postId)) {
					$resultSet = executeQuery("update posts set published = 1 where post_id = ?", $postId);
					if ($resultSet['affected_rows'] > 0) {
						$GLOBALS['gPrimaryDatabase']->createChangeLog('posts', 'Published', $postId, "false", "true");
					}
				} else {
					$returnArray['error_message'] = "Unable to publish post";
				}
				ajaxResponse($returnArray);
				break;
			case "approve":
				$returnArray = array();
				if (!$GLOBALS['gUserRow']['administrator_flag']) {
					$returnArray['error_message'] = "Unable to approve comment";
					ajaxResponse($returnArray);
					break;
				}
				$returnArray = array();
				$postCommentId = getFieldFromId("post_comment_id", "post_comments", "post_comment_id", $_GET['post_comment_id'], "approved = 0");
				if (!empty($postCommentId)) {
					$resultSet = executeQuery("update post_comments set approved = 1 where post_comment_id = ? and post_id in " .
						"(select post_id from posts where client_id = ?)", $postCommentId, $GLOBALS['gClientId']);
					if ($resultSet['affected_rows'] > 0) {
						$GLOBALS['gPrimaryDatabase']->createChangeLog('post_comments', 'Approved', $postCommentId, "false", "true");
					}
				} else {
					$returnArray['error_message'] = "Unable to approve comment";
				}
				ajaxResponse($returnArray);
				break;
			case "delete":
				$returnArray = array();
				if (!$GLOBALS['gUserRow']['administrator_flag'] || canAccessPageCode("POSTCOMMENTMAINT") < _FULLACCESS) {
					$returnArray['error_message'] = "Unable to delete comment";
					ajaxResponse($returnArray);
					break;
				}
				$returnArray = array();
				$postCommentId = getFieldFromId("post_comment_id", "post_comments", "post_comment_id", $_GET['post_comment_id'],
					"post_id in (select post_id from posts where client_id = " . $GLOBALS['gClientId'] . ")");
				if (!empty($postCommentId)) {
					executeQuery("update post_comments set parent_post_comment_id = null where parent_post_comment_id = ?", $postCommentId);
					$content = getFieldFromId("content", "post_comments", "post_comment_id", $postCommentId);
					$resultSet = executeQuery("delete from post_comments where post_comment_id = ?", $postCommentId);
					if ($resultSet['affected_rows'] > 0 && !empty($content)) {
						$GLOBALS['gPrimaryDatabase']->createChangeLog('post_comments', 'Deleted', $postCommentId, $content, "");
					}
				} else {
					$returnArray['error_message'] = "Unable to delete comment";
				}
				ajaxResponse($returnArray);
				break;
			case "post_comment":
				$returnArray = array();
				$resultSet = executeQuery("select * from posts where post_id = ? and client_id = ?", $_POST['post_id'], $GLOBALS['gClientId']);
				if ($postRow = getNextRow($resultSet)) {
					$allowComments = $postRow['allow_comments'] == "1" && ($GLOBALS['gLoggedIn'] || empty($postRow['logged_in'])) &&
						(empty($postRow['logged_in']) || $GLOBALS['gUserRow']['user_type_id'] == $postRow['user_type_id'] || empty($postRow['user_type_id']));
					if ($allowComments) {
						$contactId = "";
						if ($GLOBALS['gLoggedIn'] && $GLOBALS['gUserId'] == $_POST['user_id']) {
							$contactId = Contact::getUserContactId($GLOBALS['gUserId']);
						}
						if (empty($contactId)) {
							if (!empty($_POST['alternate_name']) && !empty($_POST['email_address'])) {
								$alternateName = $_POST['alternate_name'];
								$emailAddress = $_POST['email_address'];
								$resultSet = executeQuery("select * from contacts where alternate_name = ? and email_address = ?", $alternateName, $emailAddress);
								if ($row = getNextRow($resultSet)) {
									$contactId = $row['contact_id'];
								} else {
									$sourceId = getFieldFromId("source_id", "sources", "source_id", $_COOKIE['source_id'], "inactive = 0");
									if (empty($sourceId)) {
										$sourceId = getSourceFromReferer($_SERVER['HTTP_REFERER']);
									}
									$contactDataTable = new DataTable("contacts");
									$contactId = $contactDataTable->saveRecord(array("name_values" => array("alternate_name" => $alternateName, "email_address" => $emailAddress,"source_id"=>$sourceId)));
								}
								makeWebUserContact($contactId);
							} else {
								$returnArray['error_message'] = getSystemMessage("name_email_required");
								ajaxResponse($returnArray);
								break;
							}
						}
						if (empty($contactId)) {
							$returnArray['error_message'] = getSystemMessage("comment_problem");
							ajaxResponse($returnArray);
							break;
						}
						$taskSourceId = getFieldFromId("task_source_id", "task_sources", "task_source_code", "BLOG");
						if (!empty($taskSourceId)) {
							executeQuery("insert into tasks (client_id,contact_id,description,date_completed,simple_contact_task,task_source_id) values " .
								"(?,?,'Comment on Blog post',now(),1,?)", $GLOBALS['gClientId'], $contactId, $taskSourceId);
						}
						$comment = $_POST['comment'];
						$notifyFollowup = ($_POST['notify_followup'] == "1" ? 1 : 0);
						$approvedUserId = getFieldFromId("user_id", "post_comment_approvals", "user_id", $GLOBALS['gUserId']);
						$approved = ($GLOBALS['gUserRow']['administrator_flag'] || !empty($approvedUserId) || $postRow['approve_comments'] == 0 ? 1 : 0);
						$parentPostCommentId = getFieldFromId("post_comment_id", "post_comments", "post_comment_id", $_POST['parent_post_comment_id'], "post_id = " . $postRow['post_id']);
						$resultSet = executeQuery("insert into post_comments (post_id,contact_id,content,notify_followup,approved,parent_post_comment_id) values " .
							"(?,?,?,?,?,?)", $_POST['post_id'], $contactId, $comment, $notifyFollowup, $approved, $parentPostCommentId);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("comment_problem");
							ajaxResponse($returnArray);
							break;
						}
						$postCommentId = $resultSet['insert_id'];

						$commentEmailAddresses = getNotificationEmails("ALL_BLOG_COMMENTS");
						if (!empty($postRow['comment_notification'])) {
							$emailAddress = Contact::getUserContactField($postRow['creator_user_id'],"email_address");
							if (!empty($emailAddress) && !in_array($emailAddress, $commentEmailAddresses)) {
								$commentEmailAddresses[] = $emailAddress;
							}
						}

						if (!empty($postRow['author_notification'])) {
							$emailAddress = getFieldFromId("email_address", "contributors", "contributor_id", $postRow['contributor_id']);
							if (!empty($emailAddress) && !in_array($emailAddress, $commentEmailAddresses)) {
								$commentEmailAddresses[] = $emailAddress;
							}
						}

						$resultSet = executeQuery("select * from post_notifications where post_id = ?", $_POST['post_id']);
						while ($row = getNextRow($resultSet)) {
							if (!empty($row['email_address']) && !in_array($row['email_address'], $commentEmailAddresses)) {
								$commentEmailAddresses[] = $row['email_address'];
							}
						}
						$urlAliasTypeCode = getUrlAliasTypeCode("posts","post_id", "id");
						if (empty($urlAliasTypeCode)) {
							$urlAliasTypeCode = "blog";
						}
						$linkUrl = "http://" . $_SERVER['HTTP_HOST'] . "/" . $urlAliasTypeCode . "?post_id=" . $_POST['post_id'];
						$unsubscribeUrl = "http://" . $_SERVER['HTTP_HOST'] . "/unsubscribe?type=G9W3&cid=" . $row['contact_id'] . "&pid=" . $postRow['post_id'];
						if (!empty($commentEmailAddresses)) {
							sendEmail(array("subject" => "Blog Post Comments",
								"body" => "The blog post '" . $postRow['title_text'] .
									"', has had a comment added to it. " .
									(empty($postRow['approve_comments']) ? "" : "The comment will need to be approved in order to be viewed by the public. ") .
									"You can see the post and comments at: " . $linkUrl, "email_addresses" => $commentEmailAddresses));
						}

						$resultSet = executeQuery("select contact_id,email_address from contacts where client_id = ? and email_address is not null and contact_id in " .
							"(select contact_id from post_comments where post_id = ? and notify_followup = 1 and post_comment_id <> ?)",
							$GLOBALS['gClientId'], $_POST['post_id'], $postCommentId);
						$sentEmailAddresses = array();
						while ($row = getNextRow($resultSet)) {
							if (!in_array($row['email_address'], $sentEmailAddresses)) {
								sendEmail(array("subject" => "Blog Post Comments",
									"body" => "A comment has been added to the Blog post titled '" . $postRow['title_text'] .
										"'. You can see the post and comments at <a href='" . $linkUrl . "'>" . $linkUrl . "</a>.<br>\n" .
										"If you wish to unsubscribe to these emails, click <a href='" . $unsubscribeUrl . "'>here</a> or go to " . $unsubscribeUrl . ".",
									"email_addresses" => $row['email_address']));
								$sentEmailAddresses[] = $row['email_address'];
							}
						}

						$resultSet = executeQuery("select * from post_comments where post_comment_id = ?", $postCommentId);
						$row = getNextRow($resultSet);
						$imageId = getFieldFromId("image_id", "contacts", "contact_id", $contactId);
						if (empty($imageId)) {
							$imageUrl = "/images/noimage.png";
						} else {
							$imageUrl = getImageFilename($imageId);
						}
						$level = 0;
						while (!empty($parentPostCommentId)) {
							$level++;
							$parentPostCommentId = getFieldFromId("parent_post_comment_id", "post_comments", "post_comment_id", $parentPostCommentId);
						}
						$commentInfo = array();
						$commentInfo['level'] = $level;
						$commentInfo['post_comment_id'] = $postCommentId;
						$commentInfo['image_url'] = $imageUrl;
						$commentInfo['display_name'] = htmlText(getDisplayName($contactId));
						$commentInfo['entry_date'] = date("M j, Y", strtotime($row['entry_time']));
						$commentInfo['entry_time'] = date("g:i a", strtotime($row['entry_time']));
						$commentInfo['approved'] = $row['approved'];
						$commentInfo['content'] = makeHtml(htmlText($row['content']));
						$commentInfo['controller'] = ($GLOBALS['gUserRow']['administrator_flag'] && empty($row['approved']) && canAccessPageCode('POSTCOMMENTMAINT') > _READONLY);
						$returnArray['new_comment'] = $commentInfo;
					} else {
						$returnArray['error_message'] = getSystemMessage("comments_not_allowed");
					}
				} else {
					$returnArray['error_message'] = getSystemMessage("post_not_found");
				}
				ajaxResponse($returnArray);
				break;
			case "get_more":
				$returnArray = array();
				$postArray = array();
				$displayed = $_GET['displayed'];
				if (!is_numeric($displayed)) {
					$displayed = 0;
				}

				# Use blog base link passed in from the page
				$blogBaseLink = empty($_POST['blog_link']) ? $_GET['blog_link'] : $_POST['blog_link'];
				if (empty($blogBaseLink)) {
					$blogBaseLink = "blog";
				}
				$postCategoryId = $_GET['post_category_id'];
				$postCategoryId = getFieldFromId("post_category_id", "post_categories", "post_category_id", $postCategoryId,
					"client_id = ? and inactive = 0" .
					($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"), $GLOBALS['gClientId']);
				$postCategoryGroupId = $_GET['post_category_group_id'];
				$postCategoryGroupId = getFieldFromId("post_category_group_id", "post_category_groups", "post_category_group_id", $postCategoryGroupId,
					"client_id = ? and inactive = 0" .
					($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"), $GLOBALS['gClientId']);
				$contributorId = $_GET['contributor_id'];
				$contributorId = getFieldFromId("contributor_id", "contributors", "contributor_id", $contributorId,
					"client_id = ?", $GLOBALS['gClientId']);
				$month = $_GET['month'];
				$searchMonth = "";
				$searchYear = "";
				if (strlen($month) == 6 && is_numeric($month)) {
					$searchYear = substr($month, 0, 4);
					$searchMonth = substr($month, 4);
					if ($searchYear > date("Y") || $searchYear < (date("Y") - 10) || $searchMonth <= 0 || $searchMonth > 12) {
						$month = "";
					}
				} else {
					$month = "";
				}
				$query = "select * from posts where inactive = 0 and client_id = ?" .
					($GLOBALS['gLoggedIn'] ? "" : " and public_access = 1") .
					($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") .
					(($GLOBALS['gLoggedIn'] && $GLOBALS['gUserRow']['administrator_flag']) || !empty($_GET['post_id']) ? "" : " and publish_time <= current_time") .
					($GLOBALS['gLoggedIn'] && $GLOBALS['gUserRow']['administrator_flag'] ? "" : " and published = 1 and publish_time <= current_time") .
					(empty($month) ? "" : " and month(publish_time) = " . $searchMonth . " and year(publish_time) = " . $searchYear);
				$queryParameters = array($GLOBALS['gClientId']);
				if (!empty($postCategoryId)) {
					$query .= " and post_id in (select post_id from post_category_links where post_category_id = ?)";
					$queryParameters[] = $postCategoryId;
				}
				if (!empty($postCategoryGroupId)) {
					$query .= " and post_id in (select post_id from post_category_links where post_category_id in (select post_category_id from post_category_group_links where post_category_group_id = ?))";
					$queryParameters[] = $postCategoryGroupId;
				}
				if (!empty($_GET['search_text'])) {
					$query .= " and (title_text like ? or content like ?)";
					$queryParameters[] = "%" . $_GET['search_text'] . "%";
					$queryParameters[] = "%" . $_GET['search_text'] . "%";
				}
				if (!empty($_GET['post_id'])) {
					$query .= " and post_id = ?";
					$queryParameters[] = $_GET['post_id'];
				}
				if (!empty($contributorId)) {
					$query .= " and contributor_id = ?";
					$queryParameters[] = $contributorId;
				}
				$query .= " order by publish_time desc,post_id desc";
				$resultSet = executeQuery($query, $queryParameters);
				while ($postRow = getNextRow($resultSet)) {
					if (empty($_GET['post_id']) && !empty($postRow['hide_in_lists'])) {
						continue;
					}
					if (!$GLOBALS['gUserRow']['administrator_flag'] && empty($row['public_access']) && !empty($row['user_type_id']) && $row['user_type_id'] != $GLOBALS['gUserRow']['user_type_id']) {
						continue;
					}
					if ($postRow['public_access'] == 1 && $postRow['publish_time'] <= date("Y-m-d H:i:s") && $postRow['published'] == 1) {
						$postRow['appear_for_public'] = 1;
					} else {
						$postRow['appear_for_public'] = 0;
					}
					if (!empty($_GET['post_id'])) {
						if (!isHtml($postRow['content'])) {
							$postRow['content'] = makeHtml($this->replaceImageReferences($postRow['content']));
						} else {
							$postRow['content'] = $this->replaceImageReferences($postRow['content']);
						}
						$postRow['content'] = massageFragmentContent($postRow['content']);
					}
					if (!empty($postRow['image_id'])) {
						$postRow['blog_image'] = "<img src='" . getImageFilename($postRow['image_id'],array("use_cdn"=>true)) . "' id='_blog_image'>";
					} else {
						$postRow['blog_image'] = "";
					}
					$postArray[] = $postRow;
				}
				$blogCount = 0;
				$usedCount = 0;
				if (empty($_GET['blog_count']) || !is_numeric($_GET['blog_count'])) {
					$_GET['blog_count'] = 6;
				}
				$noMorePosts = true;
				$returnPosts = array();
				foreach ($postArray as $postRow) {
					$blogCount++;
					if ($blogCount <= $displayed) {
						continue;
					}
					if ($usedCount >= $_GET['blog_count']) {
						$noMorePosts = false;
						break;
					}
					$thisPost = $postRow;
					$thisPost['publish_date'] = date("F j, Y", strtotime($postRow['publish_time']));
					$usedCount++;
					$categoryList = array();
					$resultSet = executeQuery("select * from post_categories where post_category_id in (select post_category_id from post_category_links where post_id = ?) and inactive = 0" .
						($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $postRow['post_id']);
					while ($row = getNextRow($resultSet)) {
						$categoryList[$row['post_category_id']] = htmlText($row['description']);
					}
					$thisPost['categories'] = $categoryList;

					$relatedPosts = array();
					$resultSet = executeQuery("select * from posts where post_id in (select associated_post_id from related_posts where post_id = ?)" .
						($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and inactive = 0 order by publish_time desc, post_id desc", $postRow['post_id']);
					while ($row = getNextRow($resultSet)) {
						$row['link_url'] = "/" . (empty($row['link_name']) ? ltrim($blogBaseLink, "/") . "?post_id=" . $row['post_id'] : ltrim($blogBaseLink, "/") . "/" . $row['link_name']);
						$relatedPosts[$row['post_id']] = array("post_id"=>$row['post_id'], "link_url"=>$row['link_url'], "description"=>htmlText($row['title_text']));
					}
					$thisPost['related_posts'] = $relatedPosts;

					$commentCount = 0;
					$resultSet = executeQuery("select count(*) from post_comments where " . ($GLOBALS['gUserRow']['administrator_flag'] ? "" : "approved = 1 and ") . "post_id = ?", $postRow['post_id']);
					if ($row = getNextRow($resultSet)) {
						$commentCount = $row['count(*)'];
					}
					$thisPost['comment_count'] = $commentCount;
					$thisPost['comment_count_text'] = (empty($commentCount) ? "No" : $commentCount) . " comment" . ($commentCount == 1 ? "" : "s");
					$thisPost['contributor_name'] = htmlText(getFieldFromId("full_name", "contributors", "contributor_id", $postRow['contributor_id']));
					$thisPost['image_url'] = getImageFilename($postRow['image_id']);
					$thisPost['small_image_url'] = str_replace("-full-", "-small-", $thisPost['image_url']);
					$thisPost['thumb_image_url'] = str_replace("-full-", "-thumbnail-", $thisPost['image_url']);
					$thisPost['allow_comments'] = $postRow['allow_comments'] == "1" && ($GLOBALS['gLoggedIn'] || empty($postRow['logged_in'])) &&
						(empty($postRow['logged_in']) || $GLOBALS['gUserRow']['user_type_id'] == $postRow['user_type_id'] || empty($postRow['user_type_id']));
					$thisPost['post_comments'] = $this->getPostComments($postRow['post_id'], $GLOBALS['gUserRow']['contact_id']);
					$thisPost['blog_link'] = "/" . (empty($postRow['link_name']) ? ltrim($blogBaseLink, "/") . "?post_id=" . $postRow['post_id'] : ltrim($blogBaseLink, "/") . "/" . $postRow['link_name']);
					$linkList = array();
					$resultSet = executeQuery("select * from post_links where post_id = ?", $postRow['post_id']);
					while ($row = getNextRow($resultSet)) {
						$linkList[] = $row;
					}
					$thisPost['post_links'] = $linkList;
					$returnPosts[] = $thisPost;
				}
				$returnArray['no_more_posts'] = $noMorePosts;
				$returnArray['posts'] = $returnPosts;
				ajaxResponse($returnArray);
				break;
		}
	}

	function getPostComments($postId, $contactId, $parentPostCommentId = "", $level = 0) {
		$postCommentsArray = array();
		$resultSet = executeQuery("select * from post_comments where post_id = ? and parent_post_comment_id " .
			(empty($parentPostCommentId) ? "is null" : "= " . $parentPostCommentId) .
			($GLOBALS['gUserRow']['administrator_flag'] ? "" : " and (approved = 1" . (empty($contactId) ? "" : " or contact_id = " . $contactId) . ")"), $postId);
		while ($row = getNextRow($resultSet)) {
			$row['level'] = $level;
			$row['entry_date'] = date("m/d/Y", strtotime($row['entry_time']));
			$row['entry_time'] = date("g:ia", strtotime($row['entry_time']));
			$row['display_name'] = getDisplayName($row['contact_id']);
			$imageId = getFieldFromId("image_id", "contacts", "contact_id", $row['contact_id']);
			if (empty($imageId)) {
				$row['image_url'] = "/images/noimage.png";
			} else {
				$row['image_url'] = getImageFilename($imageId);
			}
			$postCommentsArray[] = $row;
			$replyArray = $this->getPostComments($postId, $contactId, $row['post_comment_id'], ($level + 1));
			$postCommentsArray = array_merge($postCommentsArray, $replyArray);
		}
		return $postCommentsArray;
	}
}

$pageObject = new BlogFeedPage();
$pageObject->displayPage();
