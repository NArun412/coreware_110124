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
	%module:blog_latest_posts%

	The following options are just relayed into the blogcontroller > get_more call, so behavior/default values will
	follow what is defined there:
		- displayed
		- blog_count
		- post_category_id
		- post_category_group_id
		- search_text
		- contributor_id
		- blog_link
		- month
*/

class BlogLatestPostsPageModule extends PageModule {

	function createContent() {
		$blogRequestUrl = "/blogcontroller.php?ajax=true&url_action=get_more"
			. $this->getOptionalAppendURL("displayed")
			. $this->getOptionalAppendURL("blog_count")
			. $this->getOptionalAppendURL("post_category_id")
			. $this->getOptionalAppendURL("post_category_group_id")
			. $this->getOptionalAppendURL("search_text")
			. $this->getOptionalAppendURL("contributor_id")
			. $this->getOptionalAppendURL("blog_link")
			. $this->getOptionalAppendURL("month");
		?>

        <div id="_blog_latest_posts">
        </div>

        <script>
            $("body").addClass("no-waiting-for-ajax");
            loadAjaxRequest("<?= $blogRequestUrl ?>", function(returnArray) {
                appendLatestPosts(returnArray);
            });

            function getPostCategories(blog) {
                const tags = [];
                const categories = blog.categories;
                if (categories) {
                    for (const key in categories) {
                        if (categories.hasOwnProperty(key)) {
                            const blogBaseURL = "<?= empty($this->iParameters["blog_link"]) ? "blog" : $this->iParameters["blog_link"] ?>";
                            const tagURL = "/" + blogBaseURL + "?post_category_id=" + key;
                            tags.push(`<a href="${ tagURL }">${ categories[key] }</a>`);
                        }
                    }
                }
                return `<div class="blog-categories">${ tags }</div>`;
            }

            function appendLatestPosts(returnArray) {
                if (returnArray && returnArray.posts) {
                    returnArray.posts.forEach(post => {
                        $("#_blog_latest_posts").append(`
							<div class="blog-latest-post">
								<img src="${ post.small_image_url }" alt="${ post.title_text }">
								<div>
									<a href="${ post.blog_link }">
										<p class="post-title">${ post.title_text }</p>
									</a>
									${ getPostCategories(post) }
									<p class="blog-date"><strong>Posted on</strong> ${ post.publish_date }</p>
								</div>
							</div>
						`);
                    })
                }
            }
        </script>
		<?php
	}

	private function getOptionalAppendURL($parameter): string {
		return empty($this->iParameters[$parameter]) ? "" : "&" . $parameter . "=" . $this->iParameters[$parameter];
	}

}