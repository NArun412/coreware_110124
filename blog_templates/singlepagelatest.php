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

	function setup() {
		if (empty($_GET['post_id']) && !empty($_GET['id'])) {
			$_GET['post_id'] = $_GET['id'];
		}
		$this->iPostId = $_GET['post_id'];
		if (empty($this->iPostId)) {
			$query = "select * from posts where inactive = 0 and client_id = ?" . ($GLOBALS['gLoggedIn'] ? "" : " and public_access = 1") .
				(empty($_GET['post_category_id']) ? "" : " and post_id in (select post_id from post_category_links where post_category_id = ?)") .
				($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") .
				($GLOBALS['gInternalConnection'] ? "" : " and published = 1 and publish_time <= current_time") . " order by publish_time desc";
			$parameters = array($GLOBALS['gClientId']);
			if (!empty($_GET['post_category_id'])) {
				$parameters[] = $_GET['post_category_id'];
			}
			$resultSet = executeQuery($query, $parameters);
			while ($postRow = getNextRow($resultSet)) {
				if ($GLOBALS['gUserRow']['administrator_flag'] || !empty($postRow['public_access']) || (!empty($postRow['user_type_id']) && $postRow['user_type_id'] != $GLOBALS['gUserRow']['user_type_id'])) {
					$this->iPostId = $postRow['post_id'];
					break;
				}
			}
			$_GET['post_id'] = $this->iPostId;
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
		?>
        <input type="hidden" id="post_id" name="post_id" value="<?= $this->iPostId ?>">
		<?php
		$resultSet = executeQuery("select * from posts where post_id = ?", $this->iPostId);
		if (!$postRow = getNextRow($resultSet)) {
			echo $this->getPageData("after_form_content");
			return true;
		}
		$postCategoryId = $_GET['post_category_id'];
		$postCategoryRow = getRowFromId("post_categories", "post_category_id", $_GET['post_category_id']);
		?>
        <input type="hidden" id="blog_link" name="blog_link" value="<?= (substr($GLOBALS['gPageRow']['link_name'], 0, 1) == "/" ? "" : "/") . $GLOBALS['gPageRow']['link_name'] ?>"/>
		<?php if (!empty($postCategoryRow['description'])) { ?>
            <h1><?= htmlText($postCategoryRow['description']) ?></h1>
		<?php } ?>
		<?php if (!empty($postCategoryRow['image_id'])) { ?>
            <div id="post_category_image"><img src="<?= getImageFilename($postCategoryRow['image_id'], array("use_cdn" => true)) ?>"></div>
		<?php } ?>
        <div id="_blog_detail_entry"></div>
        <div class='clear-div'></div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
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
                    }
                    $("#_blog_detail_entry").append("<div id='end-of-blog' class='clear-div'></div>");
                }
            });
        </script>
		<?php
		return true;
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
            #_blog_detail_entry #_blog_excerpt_image {
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
		?>

        # Blog Detail Block - Used for the display of a single blog entry

        <div id="_blog_detail_block">
            <div class="blog-detail-block" id="blog_block_%post_id%">

                <div class="blog-image">%blog_image%</div>

                <p>
                    <span class="blog-date">%publish_date%</span>
                    <span class="author-name">By <a href="<?= $GLOBALS['gLinkUrl'] ?>?contributor_id=%contributor_id%">%contributor_name%</a></span>
                </p>

				<?php if (empty($_GET['post_category_id'])) { ?>
                    <h1>%title_text%</h1>
				<?php } else { ?>
                    <h2>%title_text%</h2>
				<?php } ?>
                <div class="blog-content">%content%</div>
                <div class="blog-post-links">
                    %post_links%
                </div>

                <div class='clear-div'></div>

				<?= $this->getFragment("BLOG_FOOTER") ?>

            </div><!-- blog-detail-block -->
        </div><!-- blog_detail_block -->

		<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
