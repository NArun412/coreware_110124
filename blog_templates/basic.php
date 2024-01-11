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

$GLOBALS['gPageCode'] = "BLOG";
require_once "shared/startup.inc";

class ThisPage extends Page {

	var $iPostId = "";
	var $iPostCategoryId = "";
	var $iPostCategoryGroupId = "";
	var $iContributorId = "";
	var $iAllowComments = false;

	function setup() {
		if (empty($_GET['post_id']) && !empty($_GET['id'])) {
			$_GET['post_id'] = $_GET['id'];
		}
		if (empty($_GET['post_category_id']) && !empty($_GET['post_category_code'])) {
			$_GET['post_category_id'] = getFieldFromId("post_category_id", "post_categories", "post_category_code", $_GET['post_category_code']);
		}
		if (empty($_GET['post_category_group_id']) && !empty($_GET['post_category_group_code'])) {
			$_GET['post_category_group_id'] = getFieldFromId("post_category_group_id", "post_category_groups", "post_category_group_code", $_GET['post_category_group_code']);
		}
		$this->iPostId = $_GET['post_id'];
		if (!empty($this->iPostId)) {
			$resultSet = executeQuery("select * from posts where post_id = ? and inactive = 0 and client_id = ?" .
				($GLOBALS['gLoggedIn'] ? "" : " and public_access = 1") .
				($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") .
				($GLOBALS['gInternalConnection'] ? "" : " and published = 1 and publish_time <= current_time"),
				$this->iPostId, $GLOBALS['gClientId']);
			if ($postRow = getNextRow($resultSet)) {
				if (!$GLOBALS['gUserRow']['administrator_flag'] && empty($postRow['public_access']) && !empty($postRow['user_type_id']) && $postRow['user_type_id'] != $GLOBALS['gUserRow']['user_type_id']) {
					$this->iPostId = "";
				}
			} else {
				$this->iPostId = "";
			}
		}

		$this->iAllowComments = $postRow['allow_comments'] == "1" && ($GLOBALS['gLoggedIn'] || empty($postRow['logged_in'])) &&
			(empty($postRow['logged_in']) || $GLOBALS['gUserRow']['user_type_id'] == $postRow['user_type_id'] || empty($postRow['user_type_id']));

		$this->iPostCategoryId = getFieldFromId("post_category_id", "post_categories", "post_category_id", $_GET['post_category_id'],
			"client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"), $GLOBALS['gClientId']);
		$this->iPostCategoryGroupId = getFieldFromId("post_category_group_id", "post_category_groups", "post_category_group_id", $_GET['post_category_group_id'],
			"client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"), $GLOBALS['gClientId']);
		$this->iContributorId = getFieldFromId("contributor_id", "contributors", "contributor_id", $_GET['contributor_id'],
			"client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"), $GLOBALS['gClientId']);
		if (!empty($this->iPostId)) {

			$metaKeywords = CustomField::getCustomFieldData($this->iPostId, "META_KEYWORDS", "POSTS");
			if (empty($metaKeywords)) {
				$categories = "";
				$resultSet = executeQuery("select * from post_categories where post_category_id in " .
					"(select post_category_id from post_category_links where post_id = ?) and inactive = 0" .
					($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"), $this->iPostId);
				while ($row = getNextRow($resultSet)) {
					$categories .= (empty($categories) ? "" : ",") . $row['description'];
				}
				$metaKeywords = $categories;
			}
			$metaDescription = CustomField::getCustomFieldData($this->iPostId, "META_DESCRIPTION", "POSTS");
			if (empty($metaDescription)) {
				$metaDescription = $postRow['title_text'];
			}
			$GLOBALS['gPageRow']['meta_description'] = $metaDescription;
			$GLOBALS['gPageRow']['meta_keywords'] = $metaKeywords;
		}
	}

	function headerIncludes() {
		$domainName = getDomainName();
		if (!empty($this->iPostId)) {
			$postSet = executeQuery("select * from posts where post_id = ?", $this->iPostId);
			$postRow = getNextRow($postSet);
			?>
            <meta property="og:title" content="<?= str_replace('"', "'", $postRow['title_text']) ?>"/>
            <meta property="og:type" content="website"/>
            <meta property="og:url" content="<?= $domainName ?>/<?= (empty($postRow['link_name']) ? "blog?post_id=" . $this->iPostId : "blog/" . $postRow['link_name']) ?>"/>
            <meta property="og:image" content="<?= (empty($postRow['image_id']) ? "" : $domainName . "/getimage.php?id=" . $postRow['image_id']) ?>"/>
            <meta property="og:description" content="<?= str_replace('"', "'", $postRow['excerpt']) ?>"/>

            <meta property="twitter:card" content="summary"/>
            <meta property="twitter:title" content="<?= str_replace('"', "'", $postRow['title_text']) ?>"/>
            <meta property="twitter:description" content="<?= str_replace('"', "'", $postRow['excerpt']) ?>"/>
            <meta property="twitter:image" content="<?= (empty($postRow['image_id']) ? "" : $domainName . "/getimage.php?id=" . $postRow['image_id']) ?>"/>
            <meta property="twitter:url" content="<?= $domainName ?>/<?= (empty($postRow['link_name']) ? "blog?post_id=" . $this->iPostId : "blog/" . $postRow['link_name']) ?>"/>
			<?php

			$urlAliasTypeCode = getUrlAliasTypeCode("posts", "post_id", "id");
			if (!empty($urlAliasTypeCode) && !empty($this->$postRow['link_name'])) {
				$linkUrl = $urlAliasTypeCode . "/" . $postRow['link_name'];
				$GLOBALS['gCanonicalLink'] = '<link rel="canonical" href="' . $domainName . '/' . $linkUrl . '"/>';
			}
			return true;
		}
	}

	function setPageTitle() {
		if (empty($_GET['post_id']) && !empty($_GET['id'])) {
			$_GET['post_id'] = $_GET['id'];
		}
		$postDescription = getFieldFromId("title_text", "posts", "post_id", $_GET['post_id']);
		return (empty($postDescription) ? false : $GLOBALS['gClientName'] . " | " . $postDescription);
	}

	function mainContent() {
		echo $this->getPageData("content");
		if (!empty($this->iContributorId)) {
			echo "<p class='criteria'>Showing posts by author " . getFieldFromId("full_name", "contributors", "contributor_id", $this->iContributorId) . "</p>";
		}
		if (!empty($this->iPostCategoryId)) {
			echo "<p class='criteria'>Showing posts in category '" . getFieldFromId("description", "post_categories", "post_category_id", $this->iPostCategoryId) . "'</p>";
		}
		$commentApproval = false;
		$resultSet = executeQuery("select * from posts where post_id = ?", $this->iPostId);
		if ($postRow = getNextRow($resultSet)) {
			$approvedUserId = ($GLOBALS['gLoggedIn'] && getFieldFromId("user_id", "post_comment_approvals", "user_id", $GLOBALS['gUserId']));
			$commentApproval = !$approvedUserId && ($postRow['approve_comments'] == 1) && !$GLOBALS['gUserRow']['administrator_flag'];
		}
		?>
        <input type="hidden" id="blog_link" name="blog_link" value="<?= (substr($GLOBALS['gPageRow']['link_name'], 0, 1) == "/" ? "" : "/") . $GLOBALS['gPageRow']['link_name'] ?>"/>
		<?php if (empty($this->iPostId)) { ?>
            <div id="_blog_entries"></div>
            <div id="_blog_bottom"></div>
		<?php } else { ?>
            <div id="_blog_detail_entry"></div>
            <div class='clear-div'></div>
		<?php } ?>
		<?php
		echo $this->getPageData("after_form_content");
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
			<?php
			if ($GLOBALS['gUserRow']['administrator_flag']) {
			?>
            $(document).on("click touchstart", ".approve-comment", function () {
                var postCommentId = $(this).data("id");
                loadAjaxRequest("/blogcontroller.php?ajax=true&url_action=approve&post_comment_id=" + postCommentId, function(returnArray) {
                    if (!("error_message" in returnArray)) {
                        $("#approval_directive_" + postCommentId).closest(".blog-comment-block").find(".unapproved").remove();
                        $("#approval_directive_" + postCommentId).html("");
                    }
                });
                return false;
            });
            $(document).on("click touchstart", ".delete-comment", function () {
                var postCommentId = $(this).data("id");
                $('#_delete_comment_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 400,
                    title: 'Delete Comment',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("/blogcontroller.php?ajax=true&url_action=delete&post_comment_id=" + postCommentId, function(returnArray) {
                                if (!("error_message" in returnArray)) {
                                    $("#comment_id_" + postCommentId).remove();
                                }
                            });
                            $("#_delete_comment_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_delete_comment_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
			<?php } ?>
            $(document).on("click touchstart", "#submit_comment", function () {
                if ($("#_comment_form").validationEngine("validate")) {
                    $(this).hide();
                    loadAjaxRequest("/blogcontroller.php?ajax=true&url_action=post_comment", $("#_comment_form").serialize(), function(returnArray) {
                        if (!("error_message" in returnArray)) {
                            var parentPostCommentId = $("#parent_post_comment_id").val();
                            $("#_comment_form input[type=text],#_comment_form textarea").val("");
                            $("#_comment_form input[type=checkbox]").prop("checked", false);

                            var htmlContent = $("#_blog_comment_block").html();
                            if ("new_comment" in returnArray) {
                                for (var i in returnArray['new_comment']) {
                                    htmlContent = htmlContent.replace(new RegExp("%" + i + "%", 'g'), returnArray['new_comment'][i]);
                                }
                                htmlContent = htmlContent.replace(new RegExp("%src%", 'g'), "src");
                                if ("title_text" in returnArray['new_comment']) {
                                    htmlContent = htmlContent.replace(new RegExp("%encode_title%", 'g'), encodeURIComponent(returnArray['new_comment']['title_text']));
                                }
                                if (parentPostCommentId != "" && $("#comment_id_" + parentPostCommentId).length > 0) {
                                    $("#comment_id_" + parentPostCommentId).after(htmlContent);
                                } else {
                                    $(".blog-comment-wrapper").append(htmlContent);
                                }
                                if (returnArray['new_comment']['approved'] == "1") {
                                    $("#comment_id_" + returnArray['new_comment']['post_comment_id']).find(".remove-approved").remove();
                                }
                            }

                            $(".blog-comment-entry").removeClass("reply-entry").remove().insertAfter($(".blog-comment-wrapper"));
                            $("#parent_post_comment_id").val("");
							<?php if ($this->iAllowComments) { ?>
                            $("#entry_title").html("Add a new comment");
							<?php } ?>
                            $("#_form_content").removeClass("reply-comment");
                        }
                        $("#submit_comment").show();
                    });
                }
                return false;
            });
			<?php if (empty($this->iPostId)) { ?>
            $(window).scroll(function () {
                if (!noMorePosts && isScrolledIntoView($("#_blog_bottom"))) {
                    showMorePosts();
                }
            })
            showMorePosts();
			<?php } else { ?>
            loadAjaxRequest("/blogcontroller.php?ajax=true&url_action=get_more&post_id=<?= $this->iPostId ?>", { blog_link: $("#blog_link").val() }, function(returnArray) {
                if ("no_more_posts" in returnArray) {
                    noMorePosts = returnArray['no_more_posts'];
                }
                if ("posts" in returnArray) {
                    $("#_blog_detail_entry").find("#end-of-blog").remove();
                    for (var i in returnArray['posts']) {
                        var htmlContent = $("#_blog_detail_block").html();
                        $("#_blog_detail_block").html("");
                        var postLinks = "";
                        if ("post_links" in returnArray['posts'][i] && returnArray['posts'][i]['post_links'].length > 0) {
                            postLinks += "<h2>Related Links</h2>";
                            for (var j in returnArray['posts'][i]['post_links']) {
                                postLinks += "<p><a href='" + returnArray['posts'][i]['post_links'][j]['link_url'] + "' target='_blank'>" + returnArray['posts'][i]['post_links'][j]['description'] + "</a></p>";
                            }
                        }
                        htmlContent = htmlContent.replace("%post_links%", postLinks);

                        var relatedPosts = "";
                        if ("related_posts" in returnArray['posts'][i] && returnArray['posts'][i]['related_posts'].length > 0) {
                            relatedPosts += "<h2>Related Posts</h2>";
                            for (var j in returnArray['posts'][i]['related_posts']) {
                                relatedPosts += "<p><a href='" + returnArray['posts'][i]['related_posts'][j]['link_url'] + "'>" + returnArray['posts'][i]['related_posts'][j]['description'] + "</a></p>";
                            }
                        }
                        htmlContent = htmlContent.replace("%related_posts%", relatedPosts);

                        for (var j in returnArray['posts'][i]) {
                            htmlContent = htmlContent.replace(new RegExp("%" + j + "%", 'g'), returnArray['posts'][i][j]);
                        }
                        htmlContent = htmlContent.replace(new RegExp("%src%", 'g'), "src");
                        if ("title_text" in returnArray['posts'][i]) {
                            htmlContent = htmlContent.replace(new RegExp("%encode_title%", 'g'), encodeURIComponent(returnArray['posts'][i]['title_text']));
                        }
                        var categoryList = "";
                        for (var j in returnArray['posts'][i]['categories']) {
                            if (categoryList != "") {
                                categoryList += ", ";
                            }
                            categoryList += "<a href='<?= $GLOBALS['gLinkUrl'] ?>?post_category_id=" + j + "'>" + returnArray['posts'][i]['categories'][j] + "</a>";
                        }
                        if (categoryList != "") {
                            htmlContent = htmlContent.replace("%category_list%", categoryList);
                        }
                        $("#_blog_detail_entry").append(htmlContent);
                        if (returnArray['posts'][i]['image_url'] == "") {
                            $("#blog_block_" + returnArray['posts'][i]['post_id']).find(".excerpt-image-block").remove();
                        }
                        if (!returnArray['posts'][i]['allow_comments'] && returnArray['posts'][i]['comment_count'] == 0) {
                            $("#blog_block_" + returnArray['posts'][i]['post_id']).find(".blog-comments").remove();
                        }
                        if (returnArray['posts'][i]['contributor_name'] == "") {
                            $("#blog_block_" + returnArray['posts'][i]['post_id']).find(".author-name").remove();
                        }
                        if (categoryList == "") {
                            $("#blog_block_" + returnArray['posts'][i]['post_id']).find(".category-list").remove();
                        }
                        if ("post_comments" in returnArray['posts'][i]) {
                            for (var j in returnArray['posts'][i]['post_comments']) {
                                var htmlContent = $("#_blog_comment_block").html();
                                for (var k in returnArray['posts'][i]['post_comments'][j]) {
                                    htmlContent = htmlContent.replace(new RegExp("%" + k + "%", 'g'), returnArray['posts'][i]['post_comments'][j][k]);
                                }
                                htmlContent = htmlContent.replace(new RegExp("%src%", 'g'), "src");
                                $(".blog-comment-wrapper").append(htmlContent);
                                if (returnArray['posts'][i]['post_comments'][j]['approved'] == "1") {
                                    $("#comment_id_" + returnArray['posts'][i]['post_comments'][j]['post_comment_id'] + " .remove-approved").remove();
                                }
                            }
                        }
                        if ($().prettyPhoto) {
                            $("a[rel^='prettyPhoto'],a.pretty-photo").prettyPhoto({ social_tools: false, default_height: 480, default_width: 854, deeplinking: false });
                        }
                    }
                    $("#_blog_detail_entry").append("<div id='end-of-blog' class='clear-div'></div>");
                }
            });

			<?php } ?>
            $(document).on("click touchstart", ".blog-add-comment-link", function () {
                $(".blog-comment-entry").removeClass("reply-entry").remove().insertAfter($(".blog-comment-wrapper"));
                $('html, body').animate({
                    scrollTop: $(".blog-comment-entry").offset().top
                }, 1000);
                $("#parent_post_comment_id").val("");
				<?php if ($this->iAllowComments) { ?>
                $("#entry_title").html("Add a new comment");
				<?php } ?>
                $("#_form_content").removeClass("reply-comment");
                $("#_comment_form").find(":input:visible:first").focus();
                return false;
            });
            $(document).on("click touchstart", ".blog-comment-reply-link", function () {
                $(".blog-comment-entry").addClass("reply-entry").remove().insertAfter($(this).closest(".blog-comment-block"));
                $("#parent_post_comment_id").val($(this).data("id"));
				<?php if ($this->iAllowComments) { ?>
                $("#entry_title").html("Add a reply to this comment<br><a href='#' class='blog-add-comment-link'>Add a new comment</a>");
				<?php } ?>
                $("#_form_content").addClass("reply-comment");
                $("#_comment_form").find(":input:visible:first").focus();
                return false;
            });
        </script>
		<?php
		return true;
	}

	function javascript() {
		?>
        <script>
            var noMorePosts = false;
			<?php
			if (empty($this->iPostId)) {
			?>
            var gettingMore = false;

            function showMorePosts() {
                if (gettingMore) {
                    return;
                }
                gettingMore = true;
                var displayed = $("#_blog_entries .blog-block").length;
                loadAjaxRequest("/blogcontroller.php?ajax=true&url_action=get_more&displayed=" + displayed + "&search_text=" + encodeURIComponent("<?= str_replace('"', '', $_GET['search_text']) ?>") +
                    "&contributor_id=<?= $this->iContributorId ?>&post_category_id=<?= $this->iPostCategoryId ?>&post_category_group_id=<?= $this->iPostCategoryGroupId ?>", { blog_link: $("#blog_link").val() }, function(returnArray) {
                    if ("no_more_posts" in returnArray) {
                        noMorePosts = returnArray['no_more_posts'];
                    }
                    if ("posts" in returnArray) {
                        $("#_blog_entries").find("#end-of-blog").remove();
                        for (var i in returnArray['posts']) {
                            var htmlContent = $("#_blog_entry_block").html();
                            for (var j in returnArray['posts'][i]) {
                                htmlContent = htmlContent.replace(new RegExp("%" + j + "%", 'g'), returnArray['posts'][i][j]);
                            }
                            htmlContent = htmlContent.replace(new RegExp("%src%", 'g'), "src");
                            if ("title_text" in returnArray['posts'][i]) {
                                htmlContent = htmlContent.replace(new RegExp("%encode_title%", 'g'), encodeURIComponent(returnArray['posts'][i]['title_text']));
                            }
                            var categoryList = "";
                            for (var j in returnArray['posts'][i]['categories']) {
                                if (categoryList != "") {
                                    categoryList += ", ";
                                }
                                categoryList += "<a href='<?= $GLOBALS['gLinkUrl'] ?>?post_category_id=" + j + "'>" + returnArray['posts'][i]['categories'][j] + "</a>";
                            }
                            if (categoryList != "") {
                                htmlContent = htmlContent.replace("%category_list%", categoryList);
                            }
                            $("#_blog_entries").append(htmlContent);
                            if (returnArray['posts'][i]['image_url'] == "") {
                                $("#blog_block_" + returnArray['posts'][i]['post_id']).find(".excerpt-image-block").remove();
                            }
                            if (!returnArray['posts'][i]['allow_comments'] && returnArray['posts'][i]['comment_count'] == 0) {
                                $("#blog_block_" + returnArray['posts'][i]['post_id']).find(".blog-comments").remove();
                            }
                            if (returnArray['posts'][i]['contributor_name'] == "") {
                                $("#blog_block_" + returnArray['posts'][i]['post_id']).find(".author-name").remove();
                            }
                            if (categoryList == "") {
                                $("#blog_block_" + returnArray['posts'][i]['post_id']).find(".category-list").remove();
                            }
                        }
                        $("#_blog_entries").append("<div id='end-of-blog' class='clear-div'></div>");
                        gettingMore = false;
                    }
                });
            }
			<?php
			}
			?>
        </script>
		<?php
		return false;
	}

	function internalCSS() {
		?>
        <style>
            #_form_content {
                padding: 20px 5%;
            }

            #_form_content.reply-comment {
                margin-left: 20px;
                max-width: 500px;
            }

            .blog-comment-wrapper {
                width: 100%;
                margin: 0 auto;
                margin-right: 5%;
                margin-top: 20px;
            }

            .blog-comment-image {
                float: left;
                width: 50px;
            }

            .blog-comment-image img {
                width: 40px;
            }

            .blog-comment-author {
                width: 100px;
                float: left;
            }

            .blog-comment-author p {
                padding: 0;
                margin: 0;
                font-size: .7rem;
            }

            .blog-comment-content {
                margin-left: 150px;
            }

            .blog-comment-content p {
                margin: 0;
                padding: 0;
                padding-bottom: 5px;
                font-size: .9rem;
            }

            .blog-comment-reply {
                padding-top: 20px;
            }

            .blog-comment-reply p {
                font-size: .8rem;
            }

            .blog-comment-block {
                max-width: 700px;
                margin-left: 0;
                margin-bottom: 20px;
                padding: 20px;
                background-color: rgb(225, 225, 225);
            }

            .blog-comment-level-1 {
                margin-left: 20px;
            }

            .blog-comment-level-2 {
                margin-left: 40px;
            }

            .blog-comment-level-3 {
                margin-left: 60px;
            }

            .blog-comment-level-4 {
                margin-left: 80px;
            }

            .blog-comment-level-5 {
                margin-left: 100px;
            }

            .blog-comment-entry {
                max-width: 700px;
                width: 90%;
                margin-left: 5%;
                margin-bottom: 40px;
                margin-right: 5%;
            }

            .blog-comment-entry.reply-entry {
                width: 100%;
                float: none;
                margin-right: 0;
                margin-left: 0;
            }

            .blog-comment-entry > p {
                width: 90%;
                margin: 20px auto;
            }

            #comment {
                height: 100px;
                width: 600px;
                max-width: 90%;
            }

            #_comment_form {
                padding-bottom: 10px;
                max-width: 500px;
            }

            .blog-add-comment {
                margin: 20px 0;
                font-size: 1.0rem;
            }

            #_blog_entries {
                width: 90%;
                margin: 0 auto;
                max-width: 1920px;
                position: relative;
            }

            #_blog_detail_entry {
                width: 90%;
                margin: 40px auto;
                max-width: 800px;
                position: relative;
                padding: 0;
            }

            #_blog_detail_entry #_blog_image {
                max-width: 90%;
                margin: 20px auto;
                display: block;
            }

            #_blog_detail_entry iframe {
                max-width: 100%;
            }

            .blog-block {
                width: 80%;
                margin: 40px auto;
                max-width: 800px;
                border-bottom: 1px solid rgb(110, 110, 110);
                position: relative;
                padding-bottom: 65px;
            }

            #_blog_bottom {
                height: 1px;
                width: 100%;
            }

            .excerpt-image-block {
                max-width: 40%;
                float: right;
                margin-bottom: 10px;
                margin-left: 20px;
            }

            .excerpt-image {
                max-width: 100%;
            }

            .blog-block .excerpt-image {
                max-height: 200px;
            }

            p {
                padding: 0;
                margin: 0;
                padding-bottom: 10px;
                font-size: .8rem;
            }

            .blog-comments .far {
                font-size: 1rem;
                padding-right: 10px;
            }

            .blog-comments .fa {
                font-size: 1rem;
                padding-right: 10px;
            }

            p.blog-date, p.blog-comments, p.author-name, p.category-list {
                font-size: .8rem;
                color: rgb(160, 160, 160);
                padding-bottom: 5px;
            }

            p.blog-date a, p.blog-comments a, p.author-name a, p.category-list a {
                font-size: .8rem;
                color: rgb(160, 160, 160);
                font-weight: normal;
            }

            p.blog-date a:hover, p.blog-comments a:hover, p.author-name a:hover, p.category-list a:hover {
                color: rgb(42, 181, 100);
            }

            .blog-block p.blog-title {
                margin: 0;
            }

            p.blog-title {
                font-size: 1.4rem;
                font-weight: bold;
                margin-top: 40px;
                margin-bottom: 20px;
            }

            p.blog-body {
                font-size: .8rem;
                clear: both;
            }

            .button-block {
                position: absolute;
                bottom: 10px;
                right: 0;
            }

            .button-block a {
                margin: 0;
            }

            .unapproved {
                color: rgb(192, 0, 0);
                font-weight: bold;
            }

            .content-section {
                width: 50%;
                position: absolute;
                top: 0;
                bottom: 0;
                right: 0;
                overflow: hidden;
                padding-bottom: 70px;
            }

            span.blog-date {
                padding-right: 20px;
            }

            span.blog-comments {
                padding-right: 20px;
            }

            p.category-list {
                position: absolute;
                left: 0;
                bottom: 40px;
            }

            #_blog_detail_entry .blog-detail-block p.category-list {
                position: relative;
                bottom: 0;
                margin-top: 10px;
            }

            #_blog_detail_entry .blog-content p {
                font-size: 1.1rem;
            }

            #_blog_detail_entry .blog-detail-block p.blog-title {
                font-size: 1.4rem;
                font-weight: bold;
                margin-top: 10px;
                margin-bottom: 10px;
            }

            #_blog_detail_entry .blog-detail-block p.blog-comment-count {
                font-weight: bold;
            }

            p.criteria {
                width: 100%;
                font-size: 1.0rem;
                font-weight: 600;
                text-align: center;
                line-height: normal;
                margin: 0 auto 10px auto;
                font-style: italic;
                color: rgb(42, 181, 100);
            }

            @media only screen and (max-width: 1024px) {
                #_blog_detail_entry {
                    width: 90%;
                    float: none;
                    margin-left: auto;
                }

                .blog-comment-wrapper {
                    width: 90%;
                    margin: 0 auto;
                    float: none;
                }

                .blog-comment-entry {
                    width: 100%;
                    float: none;
                    margin-right: 0;
                }
            }

            @media only screen and (max-width: 768px) {
                .excerpt-image-block {
                    max-width: 100%;
                    width: 100%;
                }

                .blog-block p.blog-title {
                    margin: 0;
                    margin-top: 10px;
                }

                .blog-block p.blog-date, .blog-block p.blog-comments, .blog-block p.author-name {
                    display: inline;
                    margin-right: 20px;
                }

                .blog-block {
                    width: 90%;
                    margin: 20px auto;
                    float: none;
                }

                .content-section {
                    width: 100%;
                    position: relative;
                    bottom: 0;
                    right: 0;
                    padding-bottom: 65px;
                }
            }
        </style>
		<?php
		return true;
	}

	function jqueryTemplates() {
		$commentApproval = false;
		$resultSet = executeQuery("select * from posts where post_id = ?", $this->iPostId);
		if ($postRow = getNextRow($resultSet)) {
			$approvedUserId = ($GLOBALS['gLoggedIn'] && getFieldFromId("user_id", "post_comment_approvals", "user_id", $GLOBALS['gUserId']));
			$commentApproval = !$approvedUserId && ($postRow['approve_comments'] == 1) && !$GLOBALS['gUserRow']['administrator_flag'];
		}
		?>

        # Blog Comment Block - Used for the display of the comments for the blog entry

        <div id="_blog_comment_block">
            <div class="blog-comment-block blog-comment-level-%level%" id="comment_id_%post_comment_id%">
                <div class="blog-comment-image"><img %src%="%image_url%" alt="User Photo Icon"/></div>

                <div class="blog-comment-author">
                    <p>%display_name%</p>
                    <p>%entry_date%</p>
                    <p>at %entry_time%</p>
                </div><!-- blog-comment-author -->

                <div class="blog-comment-content">
                    <p class="remove-approved unapproved">This comment is not yet approved and so will not appear to the general public.</p>
                    <p>%content%</p>
                </div><!-- blog-comment-content -->

                <div class='clear-div'></div>
                <div class="blog-comment-reply"><p><a href='#' class="blog-comment-reply-link" data-id="%post_comment_id%">Reply</a>
						<?= ($GLOBALS['gUserRow']['administrator_flag'] && canAccessPageCode('POSTCOMMENTMAINT') > _READONLY ? "<span class='remove-approved' id='approval_directive_%post_comment_id%'>&nbsp;|&nbsp;This comment is <span class='highlighted-text'>not</span> approved. Click <a href='#' class='approve-comment' data-id='%post_comment_id%'>here</a> to approve it.</span>" : "") ?>
						<?= ($GLOBALS['gUserRow']['administrator_flag'] && canAccessPageCode('POSTCOMMENTMAINT') >= _FULLACCESS ? "&nbsp;|&nbsp;<a href='#' class='delete-comment' data-id='%post_comment_id%'>Delete this comment</a>" : "") ?></p></div>
            </div><!-- blog-comment-block -->
        </div><!-- blog_comment_block -->

        # Blog Detail Block - Used for the display of a single blog entry

        <div id="_blog_detail_block">
            <div class="blog-detail-block" id="blog_block_%post_id%">

                <p>
                    <span class="blog-date">%publish_date%</span>
                    <span class="author-name">By <a href="<?= $GLOBALS['gLinkUrl'] ?>?contributor_id=%contributor_id%">%contributor_name%</a></span>
                </p>

                <h1>%title_text%</h1>
                <div class="blog-content">%content%</div>
                <div class="blog-post-links">
                    %post_links%
                </div>
                <div class="blog-post-related-posts">
                    %related_posts%
                </div>

                <a id="comments"></a>
                <div class="blog-comment-wrapper">
                </div>

                <div class="blog-comment-entry">

                    <div id="_form_content">
						<?php if ($this->iAllowComments) { ?>
                            <h5 id="entry_title">Add a new comment</h5>
							<?php if ($commentApproval) { ?>
                                <p>Comments on this post must be approved by a moderator. New comments will not appear until they are approved.</p>
							<?php } ?>
                            <form id="_comment_form" name="_comment_form">
                                <input type="hidden" id="post_id" name="post_id" value="<?= $this->iPostId ?>"/>
                                <input type="hidden" id="parent_post_comment_id" name="parent_post_comment_id" value=""/>
                                <p id="_error_message" class="error-message"></p>
								<?php if ($GLOBALS['gLoggedIn']) { ?>
                                    <p>Welcome, <?= getUserDisplayName($GLOBALS['gUserId']) ?>!</p>
                                    <input type="hidden" id="user_id" name="user_id" value="<?= $GLOBALS['gUserId'] ?>"/>
								<?php } ?>
								<?php if (!$GLOBALS['gLoggedIn']) { ?>
                                    <p><input type="text" size="30" maxlength="60" id="alternate_name" name="alternate_name" class="field-text validate[required]" placeholder="Name"></p>
                                    <p><input type="text" size="30" maxlength="60" id="email_address" name="email_address" class="field-text validate[required,custom[email]]" placeholder="Email Address"></p>
								<?php } ?>
                                <p><textarea id="comment" name="comment" class="field-text validate[required]"></textarea></p>
                                <p><input type="checkbox" id="notify_followup" name="notify_followup" value="1"/><label for="notify_followup" class="checkbox-label">Email me when further comments are added</label></p>
                                <p class="align-center"><span class="button" id="submit_comment">Submit</span></p>
                            </form>
						<?php } ?>
                    </div>
                </div><!-- blog-comment-entry -->

                <p class="category-list">%category_list%</p>
                <p class="blog-comment-count"><span class="blog-comments"><span class="far fa-comment-alt"></span>%comment_count_text%</span></p>

                <div class='clear-div'></div>

				<?= $this->getFragment("BLOG_FOOTER") ?>

            </div><!-- blog-detail-block -->
        </div><!-- blog_detail_block -->

        # Blog Entry Block - Used for the display of the subsequent blog entries

        <div id="_blog_entry_block">
            <div class="blog-block" data-minimum_width="768" id="blog_block_%post_id%">

                <p>
                    <span class="blog-date">%publish_date%</span>
                    <span class="blog-comments"><span class="fa fa-comment-alt"></span><a href="%blog_link%#comments">%comment_count_text%</a></span>
                    <span class="author-name">By <a href="<?= $GLOBALS['gLinkUrl'] ?>?contributor_id=%contributor_id%">%contributor_name%</a></span>
                </p>
                <p><img class="excerpt-image" %src%="%image_url%" alt="%title_text%"/></p>

                <h2><a href="%blog_link%">%title_text%</a></h2>
                <p class="blog-body">%excerpt%</p><!-- blog-body -->
                <p class="category-list">%category_list%</p>
                <div class="button-block"><a href="%blog_link%">Continue Reading</a></div>
                <div class='clear-div'></div>
            </div><!-- blog-block -->

        </div><!-- blog_entry_block -->
		<?php
	}

	function hiddenElements() {
		?>
        <div id="_delete_comment_dialog" class="dialog-box">
            <p class="align-center">This cannot be reversed. Are you sure you want to delete this comment?</p>
        </div>
		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
