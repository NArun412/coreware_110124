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
	%module:blog_categories%

	Options:
	show_category_groups=true	 show category groups as well, doesn't show by default. Only works if a single category group is not being displayed
	show_count=true	 displays count element, doesn't show by default
	category_group=xxx  filter by category group, displays all by default
	blog_link=xxx	   modifies the base URL for the blog category links, defaults to /blog
	classes=xxx		 appends class name to each blog-category
*/

class BlogCategoriesPageModule extends PageModule {

	function createContent() {
		$categoryGroup = $this->iParameters['category_group'];
		if (!empty($categoryGroup)) {
			$this->iParameters['show_category_groups'] = false;
		}

		$categoryGroupArray = array();
		if ($this->iParameters['show_category_groups']) {
			$resultSet = executeQuery("select * from post_category_groups where client_id = ? and inactive = 0 and internal_use_only = 0", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$categoryGroupArray[] = $row['post_category_group_id'];
			}
		} else if ($categoryGroup) {
			$categoryGroupArray[] = getFieldFromId("post_category_group_id", "post_category_groups", "post_category_group_code", $categoryGroup, "inactive = 0");
		} else {
			$categoryGroupArray[] = false;
		}
		foreach ($categoryGroupArray as $postCategoryGroupId) {
			$queryParameters = array($GLOBALS['gClientId']);
			$query = "select post_categories.post_category_id, post_categories.post_category_code, post_categories.description,"
					. " count(post_category_links.post_category_link_id) as count"
					. " from post_categories"
					. " left join post_category_links on post_category_links.post_category_id = post_categories.post_category_id"
					. " left join posts on post_category_links.post_id = posts.post_id"
					. " where post_categories.inactive = 0 and posts.inactive = 0 and post_categories.client_id = ?"
					. ($GLOBALS['gInternalConnection'] ? "" : " and posts.internal_use_only = 0 and post_categories.internal_use_only = 0")
					. ($GLOBALS['gLoggedIn'] && $GLOBALS['gUserRow']['administrator_flag'] ? "" : " and posts.published = 1 and posts.publish_time <= current_time")
					. ($GLOBALS['gLoggedIn'] ? "" : " and posts.public_access = 1");

			if (!empty($postCategoryGroupId)) {
				$query .= " and post_category_id in (select post_category_id from post_category_group_links where post_category_group_id = ?)";
				$queryParameters[] = $postCategoryGroupId;
			}
			$query .= " group by post_categories.post_category_id, post_categories.post_category_code, post_categories.description";
			$query .= " order by sort_order";

			if ($this->iParameters['show_category_groups'] && !empty($postCategoryGroupId)) {
				?>
				<div class='blog-category-group'>
				<p><?= htmlText(getFieldFromId("description", "post_category_groups", "post_category_group_id", $postCategoryGroupId)) ?></p>
				<?php
			}
			$resultSet = executeQuery($query, $queryParameters);
			while ($row = getNextRow($resultSet)) {
				$categoryLink = (empty($this->iParameters['blog_link']) ? "/blog" : $this->iParameters['blog_link']) . "?post_category_id=" . $row['post_category_id'];
				?>
				<div class="blog-category <?= $this->iParameters['classes'] ?>">
					<a href="<?= $categoryLink ?>">
						<?= htmlText($row['description']) ?>

						<?php if (!empty($this->iParameters['show_count'])) { ?>
							<span class="blog-category-count">(<?= $row['count'] ?>)</span>
						<?php } ?>
					</a>
				</div>
				<?php
			}
			if ($this->iParameters['show_category_groups'] && !empty($postCategoryGroupId)) {
				?>
				</div>
				<?php
			}
		}
	}
}
