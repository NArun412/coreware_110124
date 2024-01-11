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

/*

Generals divs of blog posts for a specific category.

%module:blog_posts:post_category_code=used_category_code:wrapper_element_id=element_id[:randomize]%

Options:
limit=8 - limit to this number of posts
randomize - random order
*/

class BlogPostsPageModule extends PageModule {
	function createContent() {
		$postCategoryId = getFieldFromId("post_category_id", "post_categories", "post_category_code", $this->iParameters['post_category_code'], "inactive = 0 and internal_use_only = 0");
		if (empty($postCategoryId) && is_numeric($this->iParameters['post_category_id'])) {
			$postCategoryId = getFieldFromId("post_category_id", "post_categories", "post_category_id", $this->iParameters['post_category_id'], "inactive = 0 and internal_use_only = 0");
		}
		$wrapperElementId = $this->iParameters['wrapper_element_id'];
		if (empty($wrapperElementId)) {
			$wrapperElementId = "_blog_posts_wrapper";
		}
		if (empty($postCategoryId)) {
			?>
            <div id="<?= $wrapperElementId ?>">Invalid Category</div>
			<?php
			return;
		}
		$selectLimit = $this->iParameters['limit'];
		if (!is_numeric($selectLimit)) {
			$selectLimit = 0;
		}

		$resultSet = executeQuery("select * from posts join post_category_links using (post_id) where post_category_id = ? and client_id = ? and inactive = 0" .
			($GLOBALS['gLoggedIn'] ? "" : " and public_access = 1") .
			($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") .
			($GLOBALS['gInternalConnection'] ? "" : " and published = 1 and publish_time <= current_timestamp") .
			" order by " . (empty($this->iParameters['randomize']) ? "publish_time desc" : "rand()"), $postCategoryId, $GLOBALS['gClientId']);
		$posts = array();
		$count = 0;
		while ($row = getNextRow($resultSet)) {
			$posts[] = $row;
			$count++;
			if ($selectLimit > 0 && $count >= $selectLimit) {
				break;
			}
		}

		?>
        <div id="<?= $wrapperElementId ?>">
			<?php
			$urlAliasTypeCode = getUrlAliasTypeCode("posts","post_id", "id");
			if (empty($urlAliasTypeCode)) {
			    $urlAliasTypeCode = "blog";
            }
			foreach ($posts as $row) {
				$linkUrl = "/" . $urlAliasTypeCode . "/" . $row['link_name'];
				$imageUrl = getImageFilename($row['image_id'],array("use_cdn"=>true));
				?>

                <div class="blog-post">
                    <div class="blog-post-title"><?= makeHtml($row['title_text']) ?></div>
                    <div class="blog-post-image"><img src="<?= $imageUrl ?>"></div>
                    <div class="blog-post-excerpt"><?= makeHtml($row['excerpt']) ?></div>
                    <div class="blog-post-button-block"><a href="<?= $linkUrl ?>">Continue Reading</a></div>
                </div>
				<?php
			}
			?>
        </div>
		<?php
	}
}

