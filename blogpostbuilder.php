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

$GLOBALS['gPageCode'] = "BLOGPOSTBUILDER";
require_once "shared/startup.inc";

class BlogPostBuilderPage extends Page {

	function setup() {
		setUserPreference("MAINTENANCE_SAVE_NO_LIST", "true", $GLOBALS['gPageRow']['page_code']);
		if (method_exists($this->iTemplateObject, "getTableEditorObject")) {
			$this->iTemplateObject->getTableEditorObject()->setDefaultSortOrder("publish_time", true);
			$this->iTemplateObject->getTableEditorObject()->addIncludeListColumn(array("creator_user_id", "contributor_id", "publish_time", "title_text", "link_name"));
			$this->iTemplateObject->getTableEditorObject()->setListSortOrder(array("creator_user_id", "contributor_id", "publish_time", "title_text", "link_name"));
		}
	}

	function executePageUrlActions() {
		switch ($_GET['url_action']) {
			case "preview_not_available":
				?>
				<body>
				<h1 class="align-center">Preview not available until blog post is created.</h1>
				</body>
				<?php
				exit;
		}
	}

	function massageDataSource() {
		$this->iDataSource->getPrimaryTable()->setSubtables(array("post_comments", "post_links", "post_category_links", "blog_subscription_emails", "related_posts"));
		$this->iDataSource->addColumnControl("content", "wysiwyg", true);
		$this->iDataSource->addColumnControl("creator_user_id", "default_value", $GLOBALS['gUserId']);
		$this->iDataSource->addColumnControl("creator_user_id_display", "form_label", "Created By");
		$this->iDataSource->addColumnControl("creator_user_id_display", "default_value", getUserDisplayName());
		$this->iDataSource->addColumnControl("creator_user_id_display", "readonly", true);
		$this->iDataSource->addColumnControl("creator_user_id_display", "data_type", "varchar");
		$this->iDataSource->addColumnControl("date_created", "default_value", date("m/d/Y"));
		$this->iDataSource->addColumnControl("date_created", "readonly", true);
		$this->iDataSource->addColumnControl("link_name", "classes", "url-link");
		$this->iDataSource->addColumnControl("link_name", "not_null", true);
		$this->iDataSource->addColumnControl("title_text", "css-width", "500px");
		$this->iDataSource->addColumnControl("title_text", "data_type", "varchar");
		$this->iDataSource->addColumnControl("image_id", "data_type", "image_input");
		$this->iDataSource->addColumnControl("inactive", "data_type", "hidden");


		$this->iDataSource->addColumnControl("related_posts", "data_type", "custom");
		$this->iDataSource->addColumnControl("related_posts", "form_label", "Related Posts");
		$this->iDataSource->addColumnControl("related_posts", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("related_posts", "control_table", "posts");
		$this->iDataSource->addColumnControl("related_posts", "links_table", "related_posts");
		$this->iDataSource->addColumnControl("related_posts", "control_description_field", "title_text");
		$this->iDataSource->addColumnControl("related_posts", "control_key", "associated_post_id");

		$this->iDataSource->addColumnControl("post_category_links", "data_type", "custom");
		$this->iDataSource->addColumnControl("post_category_links", "form_label", "Post Categories");
		$this->iDataSource->addColumnControl("post_category_links", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("post_category_links", "control_table", "post_categories");
		$this->iDataSource->addColumnControl("post_category_links", "links_table", "post_category_links");


		$this->iDataSource->addColumnControl("approve_comments", "default_value", "1");
		$this->iDataSource->addColumnControl("allow_comments", "default_value", "1");
		$this->iDataSource->addColumnControl("comment_notification", "default_value", "1");

		$this->iDataSource->addColumnControl("contributor_id", "form_label", "Author/Contributor");
		$this->iDataSource->addColumnControl("contributor_id", "get_choices", "contributorChoices");
		$this->iDataSource->addColumnControl("full_name", "data_type", "varchar");
		$this->iDataSource->addColumnControl("full_name", "not_null", "true");
		$this->iDataSource->addColumnControl("full_name", "data-conditional-required", "$(\"#contributor_id\").val() == -1");
		$this->iDataSource->addColumnControl("full_name", "no_required_label", true);
		$this->iDataSource->addColumnControl("full_name", "form_label", "Contributor Name");
		$this->iDataSource->addColumnControl("full_name", "help_label", "for new contributor");

		$this->iDataSource->addColumnControl("publish_date", "data_type", "date");
		$this->iDataSource->addColumnControl("publish_date", "default_value", date("m/d/Y"));
		$this->iDataSource->addColumnControl("publish_date", "not_null", true);
		$this->iDataSource->addColumnControl("publish_date", "form_label", "Publish Date");
		$this->iDataSource->addColumnControl("publish_time_part", "data_type", "time");
		$this->iDataSource->addColumnControl("publish_time_part", "form_label", "Publish Time");
		$this->iDataSource->addColumnControl("publish_time_part", "help_label", "leave blank for midnight");
	}

	function contributorChoices($showInactive = false) {
		$contributorChoices[-1] = array("key_value" => -1, "description" => "[Create New]", "inactive" => false);
		$resultSet = executeQuery("select * from contributors where client_id = ? order by full_name", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			if ((empty($row['inactive']) && $GLOBALS['gClientId'] == $row['client_id']) || $showInactive) {
				$contributorChoices[$row['contributor_id']] = array("key_value" => $row['contributor_id'], "description" => $row['full_name'], "inactive" => $row['inactive'] == 1 || $row['client_id'] != $GLOBALS['gClientId']);
			}
		}
		freeResult($resultSet);
		return $contributorChoices;
	}

	function onLoadJavascript() {
		?>
		<script>
			$(document).on("click", "#make_inactive", function () {
				$("#inactive").val("1");
				$(this).attr("id", "make_active").html("Undo Delete");
				return false;
			});
			$(document).on("click", "#save_post", function () {
				$("#_save_button").trigger("click");
			});
			$("#_add_button").html("Create New Post");
			$("#contributor_id").change(function () {
				if ($(this).val() === -1) {
					$("#_full_name_row").removeClass("hidden");
				} else {
					$("#_full_name_row").addClass("hidden");
				}
			});
			$(document).on("click", ".preview-controls span", function () {
				const screen = $(this).data("screen");
				$(this).closest(".preview-outer-wrapper").find(".page-preview-iframe").removeClass("desktop").removeClass("tablet").removeClass("mobile").addClass(screen);
				$(this).closest(".preview-outer-wrapper").find(".preview-controls span").removeClass("selected");
				$(this).addClass("selected");
			});
		</script>
		<?php
	}

	function javascript() {
		?>
		<script>
			function afterGetRecord() {
				$("#_full_name_row").addClass("hidden");
				const $primaryId = $("#primary_id");
				const $linkName = $("#link_name");
				const $pagePreview = $("#_page_preview");
				const $openPost = $("#open_post");
				if (empty($primaryId.val())) {
					$pagePreview.attr("src", "<?= $GLOBALS['gLinkUrl'] ?>?url_action=preview_not_available");
					$openPost.addClass("hidden");
				} else {
					const linkName = "/blog" + (empty($linkName.val()) ? "?id=" + $primaryId.val() : "/" + $linkName.val());
					$pagePreview.attr("src", linkName);
					$openPost.removeClass("hidden").attr("href", linkName);
				}
			}
		</script>
		<?php
	}

	function beforeSaveChanges(&$nameValues) {
		$nameValues['publish_time'] = date("Y-m-d", strtotime($nameValues['publish_date'])) . " " . (empty($nameValues['publish_time_part']) ? "00:00:00" : date("H:i:s", strtotime($nameValues['publish_time_part'])));
		if ($nameValues['contributor_id'] == -1) {
			if (empty($nameValues['full_name'])) {
				return "Contributor name is required";
			}
			$contributorId = getFieldFromId("contributor_id", "contributors", "full_name", $nameValues['full_name']);
			if (empty($contributorId)) {
				$insertSet = executeQuery("insert into contributors (client_id,full_name) values (?,?)", $GLOBALS['gClientId'], $nameValues['full_name']);
				$contributorId = $insertSet['insert_id'];
			}
			$nameValues['contributor_id'] = $contributorId;
		}
		if (!empty($nameValues['allow_comments'])) {
			$nameValues['comment_notification'] = 1;
			$nameValues['approve_comments'] = 1;
		}
		$nameValues['published'] = 1;
		$nameValues['public_access'] = 1;
		foreach ($nameValues as $fieldName => $fieldContent) {
			$nameValues[$fieldName] = processBase64Images($fieldContent);
		}
		return true;
	}

	function beforeDeleteRecord($primaryId) {
		executeQuery("update post_comments set parent_post_comment_id = null where post_id = ?", $primaryId);
		executeQuery("delete from related_posts where associated_post_id = ?", $primaryId);
		return true;
	}

	function afterGetRecord(&$returnArray) {
		$returnArray['publish_date'] = array("data_value" => date("m/d/Y", strtotime($returnArray['publish_time']['data_value'])));
		$returnArray['publish_date']['crc_value'] = getCrcValue($returnArray['publish_date']['data_value']);
		$returnArray['publish_time_part'] = array("data_value" => (empty($returnArray['primary_id']['data_value']) ? "" : date("g:i a", strtotime($returnArray['publish_time']['data_value']))));
		$returnArray['publish_time_part']['crc_value'] = getCrcValue($returnArray['publish_time_part']['data_value']);
		$returnArray['creator_user_id_display'] = array("data_value" => getUserDisplayName($returnArray['creator_user_id']['data_value']));
	}

	function internalCSS() {
		?>
		<style>
			.preview-wrapper {
				height: 700px;
				background-color: rgb(220, 220, 220);
				padding: 20px;
				margin: 0 0 20px 0;
			}

			.preview-controls span {
				padding: 10px 20px;
				text-align: center;
				cursor: pointer;
			}

			.preview-controls span.selected {
				background-color: rgb(220, 220, 220);
			}

			.page-preview-iframe {
				background-color: rgb(255, 255, 255);
				height: 100%;
			}

			.page-preview-iframe.desktop {
				width: 100%;
			}

			.page-preview-iframe.tablet {
				width: 750px;
			}

			.page-preview-iframe.mobile {
				width: 400px;
			}

			#_page_preview_outer_wrapper {
				margin-top: 40px;
			}
		</style>
		<?php
	}
}

$pageObject = new BlogPostBuilderPage("posts");
$pageObject->displayPage();
