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

$GLOBALS['gPageCode'] = "VIDEO";
require_once "shared/startup.inc";

class VideoPage extends Page {

	var $iMediaId = "";
	var $iMediaCategoryId = "";
	var $iMediaCategoryGroupId = "";
	var $iMediaSeriesId = "";
	var $iPageContent = "";

	function setup() {
		$this->iPageData['page_title'] = "";
		$this->iPageContent = $this->getPageData("content");
		if (!empty($_GET['type'])) {
			$this->iPageContent = "";
			$displayType = $_GET['type'];
			foreach ($_GET as $index => $value) {
				if ($index != $displayType) {
					$_GET[$index] = "";
				}
			}
		}
		if (empty($_GET['media_id']) && !empty($_GET['id'])) {
			$_GET['media_id'] = $_GET['id'];
		}
		if (!empty($_GET['media_id'])) {
			$this->iMediaId = getFieldFromId("media_id", "media", "media_id", $_GET['media_id'],
				"client_id = " . $GLOBALS['gClientId'] . " and video_identifier is not null and inactive = 0 and internal_use_only = 0");
		}
		if (!empty($this->iMediaId)) {
			$mediaSet = executeQuery("select * from media where media_id = ?", $this->iMediaId);
			$mediaRow = getNextRow($mediaSet);
			$description = $mediaRow['detailed_description'];
			if (empty($description)) {
				$description = $mediaRow['subtitle'];
			}
			$GLOBALS['gPageRow']['meta_description'] = $description;
			if (!empty($mediaRow['meta_keywords'])) {
				$GLOBALS['gPageRow']['meta_keywords'] = $mediaRow['meta_keywords'];
			}
		}
		if (!empty($_GET['code'])) {
			$this->iMediaCategoryId = getFieldFromId("media_category_id", "media_categories", "media_category_code", $_GET['code'],
				"inactive = 0 and internal_use_only = 0 and client_id = ?", $GLOBALS['gClientId']);
		}
		if (!empty($_GET['group'])) {
			$this->iMediaCategoryGroupId = getFieldFromId("media_category_group_id", "media_category_groups", "media_category_group_code",
				$_GET['group'], "inactive = 0 and internal_use_only = 0 and client_id = ?", $GLOBALS['gClientId']);
		}
		if (!empty($_GET['series'])) {
			$this->iMediaSeriesId = getFieldFromId("media_series_id", "media_series", "media_series_id", $_GET['series'],
				"client_id = " . $GLOBALS['gClientId'] . " and inactive = 0 and internal_use_only = 0");
		}
	}

	function headerIncludes() {
		$mediaId = getFieldFromId("media_id", "media", "media_id", $_GET['media_id'],
			"client_id = " . $GLOBALS['gClientId'] . " and video_identifier is not null and inactive = 0 and internal_use_only = 0");
		if (!empty($mediaId)) {
			$mediaSet = executeQuery("select * from media where media_id = ?", $mediaId);
			$mediaRow = getNextRow($mediaSet);
			$description = $mediaRow['detailed_description'];
			if (empty($description)) {
				$description = $mediaRow['subtitle'];
			}
			$urlAliasTypeCode = getReadFieldFromId("url_alias_type_code", "url_alias_types", "parameter_name", "media_id",
				"table_id = (select table_id from tables where table_name = 'media')");
			$urlLink = "https://" . $_SERVER['HTTP_HOST'] . "/" .
				(empty($urlAliasTypeCode) || empty($mediaRow['link_name']) ? "video.php?media_id=" . $mediaId : $urlAliasTypeCode . "/" . $mediaRow['link_name']);
			$imageUrl = (empty($mediaRow['image_id']) ? "" : "https://" . $_SERVER['HTTP_HOST'] . "/getimage.php?id=" . $mediaRow['image_id']);
			?>
            <meta property="og:title" content="<?= str_replace('"', "'", $mediaRow['description']) ?>"/>
            <meta property="og:type" content="website"/>
            <meta property="og:url" content="<?= $urlLink ?>"/>
            <meta property="og:image" content="<?= $imageUrl ?>"/>
            <meta property="og:description" content="<?= str_replace('"', "'", $description) ?>"/>
			<?php
		}
	}

	function setPageTitle() {
		if (empty($_GET['media_id']) && !empty($_GET['id'])) {
			$_GET['media_id'] = $_GET['id'];
		}
		$mediaDescription = getFieldFromId("description", "media", "media_id", $_GET['media_id'], "inactive = 0 and internal_use_only = 0");
		if (!empty($mediaDescription)) {
			return $GLOBALS['gClientRow']['business_name'] . " | " . $mediaDescription;
		}
		$mediaDescription = getFieldFromId("description", "media_categories", "media_category_id", $this->iMediaCategoryId);
		if (!empty($mediaDescription)) {
			return $GLOBALS['gClientRow']['business_name'] . " | " . $mediaDescription;
		}
		$mediaDescription = getFieldFromId("description", "media_category_groups", "media_category_group_id", $this->iMediaCategoryGroupId);
		if (!empty($mediaDescription)) {
			return $GLOBALS['gClientRow']['business_name'] . " | " . $mediaDescription;
		}
		$mediaDescription = getFieldFromId("description", "media_series", "media_series_id", $this->iMediaSeriesId);
		if (!empty($mediaDescription)) {
			return $GLOBALS['gClientRow']['business_name'] . " | " . $mediaDescription;
		}
	}

	function mainContent() {
		$limit = (!empty($_GET['limit']));
		$displayAsPlaylist = (!empty($_GET['playlist']));
		?>
        <div class="margin-div">
			<?php
			if (!empty($this->iMediaId)) {
				$resultSet = executeQuery("select * from media where inactive = 0 and client_id = ? and media_id = ?", $GLOBALS['gClientId'], $this->iMediaId);
				$row = getNextRow($resultSet);
				$mediaServicesRow = getRowFromId("media_services", "media_service_id", $row['media_service_id']);
				?>
                <h1><?= htmlText($row['description']) ?></h1>
				<?= $this->iPageContent ?>
                <div id="media_player">
                    <div class='embed-container'>
                        <iframe src="//<?= $mediaServicesRow['link_url'] . $row['video_identifier'] ?>?autoplay=1" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                    </div>
					<?php if (!empty($row['detailed_description'])) { ?>
                        <p><?= htmlText($row['detailed_description']) ?></p>
					<?php } ?>
					<?php if (!empty($row['date_created'])) { ?>
                        <p>Created on <?= date("m/d/Y", strtotime($row['date_created'])) ?></p>
					<?php } ?>
                </div>
				<?php
			} else if (!empty($this->iMediaCategoryGroupId)) {
				?>
                <h1><?= htmlText(getFieldFromId("description", "media_category_groups", "media_category_group_id", $this->iMediaCategoryGroupId)) ?></h1>
				<?= $this->iPageContent ?>
                <div>
					<?php
					$resultSet = executeQuery("select * from media_series where client_id = ? and inactive = 0 and internal_use_only = 0 and " .
						"(media_series_id in (select distinct media_series_id from media where media_series_id is not null and " .
						"inactive = 0 and internal_use_only = 0) || link_url is not null) and media_series_id in (select media_series_id from media_series_category_links where " .
						"media_category_id = (select media_category_id from media_categories " .
						"where media_category_group_id = ?)) order by sort_order,description", $GLOBALS['gClientId'], $this->iMediaCategoryGroupId);
					$count = 0;
					while ($row = getNextRow($resultSet)) {
						$linkUrl = (empty($row['link_url']) ? $GLOBALS['gLinkUrl'] . "?type=series&amp;series=" . $row['media_series_id'] : $row['link_url']);
						$count++;
						?>
                        <div class="video-div three-per-row<?= ($limit && $count > 3 ? " hidden" : "") ?>">
                            <p class="video-title"><?= htmlText($row['description']) ?></p>
							<?php if (!empty($row['image_id'])) { ?>
                                <p><a href="<?= $linkUrl ?>"><img title="<?= htmlText($row['description']) ?>" src="<?= getImageFilename($row['image_id'],array("use_cdn"=>true)) ?>" alt="<?= htmlText($row['description']) ?>"/></a></p>
							<?php } ?>
							<?php if (!empty($row['detailed_description'])) { ?>
                                <p><?= $row['detailed_description'] ?></p>
							<?php } ?>
                            <p><a href="<?= $linkUrl ?>"><span class="button">View Teachings</span></a></p>
                            <div class='clear-div'></div>
                        </div>
						<?php
					}
					?>
                </div>
				<?php if ($limit) { ?>
                    <div class='clear-div'></div>
                    <p id="show_more">
                        <button>Show More</button>
                    </p>
				<?php } ?>
				<?php
			} else if (!empty($this->iMediaCategoryId)) {
				$categoryDetails = getFieldFromId("detailed_description", "media_categories", "media_category_id", $this->iMediaCategoryId);
				?>
                <h1><?= htmlText(getFieldFromId("description", "media_categories", "media_category_id", $this->iMediaCategoryId)) ?></h1>
				<?php if (!empty($categoryDetails)) { ?>
                    <p><?= htmlText($categoryDetails) ?></p>
				<?php } ?>
				<?= $this->iPageContent ?>
                <div>
					<?php
					$mediaSet = executeQuery("select * from media where client_id = ? and inactive = 0 and video_identifier is not null and " .
						"media_id in (select media_id from media_category_links where media_category_id = ?) order by date_created desc,sort_order,description",
						$GLOBALS['gClientId'], $this->iMediaCategoryId);
					$count = 0;
					while ($row = getNextRow($mediaSet)) {
						$count++;
						$mediaServicesRow = getRowFromId("media_services", "media_service_id", $row['media_service_id']);
						?>
                        <div class="video-div three-per-row<?= ($limit && $count > 3 ? " hidden" : "") ?>">
                            <p class="video-title"><?= htmlText($row['description']) ?></p>
                            <div class='embed-container'>
                                <iframe src="//<?= $mediaServicesRow['link_url'] . $row['video_identifier'] ?>" allow="encrypted-media" allowfullscreen></iframe>
                            </div>
							<?php if (!empty($row['detailed_description'])) { ?>
                                <p><?= htmlText($row['detailed_description']) ?></p>
							<?php } ?>
							<?php if (!empty($row['date_created'])) { ?>
                                <p>Created on <?= date("m/d/Y", strtotime($row['date_created'])) ?></p>
							<?php } ?>
                            <div class='clear-div'></div>
                        </div>
						<?php
					}
					?>
                </div>
				<?php if ($limit) { ?>
                    <div class='clear-div'></div>
                    <p id="show_more">
                        <button>Show More</button>
                    </p>
				<?php } ?>
				<?php
			} else if (!empty($this->iMediaSeriesId)) {
				$seriesDetails = getFieldFromId("detailed_description", "media_series", "media_series_id", $this->iMediaSeriesId);
				if ($displayAsPlaylist) {
					?>
                    <div id="media_series_container">
                    <div id="media_iframe_container">
                        <iframe allow="autoplay; encrypted-media" allowfullscreen></iframe>
                        <h2 class="media-description"></h2>
                    </div>

                    <div id="media_series_list">
				<?php } ?>

                <h1 id="media_series_description"><?= htmlText(getFieldFromId("description", "media_series", "media_series_id", $this->iMediaSeriesId)) ?></h1>
				<?php if (!empty($seriesDetails)) { ?>
                    <p id="media_series_detailed_description"><?= htmlText($seriesDetails) ?></p>
				<?php } ?>
				<?= $this->iPageContent ?>
				<?php
				$resultSet = executeQuery("select * from media"
					. " where client_id = ? and media_series_id = ? and inactive = 0"
					. ($GLOBALS['gInternalConnection'] ? "" : " and media.internal_use_only = 0")
					. " order by date_created desc, sort_order, description", $GLOBALS['gClientId'], $this->iMediaSeriesId);
				while ($row = getNextRow($resultSet)) {
					$mediaDescription = htmlText($row['description']);
					$mediaServicesRow = getRowFromId("media_services", "media_service_id", $row['media_service_id']);
					?>
                    <div class="media-div"
                         data-video_identifier="<?= $row['video_identifier'] ?>"
                         data-media_services_link_url="<?= $mediaServicesRow['link_url'] ?>"
                         data-media_service_code="<?= $mediaServicesRow['media_service_code'] ?>"
                         data-description="<?= htmlText($row['description']) ?>"
                         data-media_id="<?= $row['media_id'] ?>">

						<?php if ($displayAsPlaylist) { ?>
                            <div class="media-thumbnail">
                                <img title="<?= $mediaDescription ?>" src="<?= getImageFilename($row['image_id'],array("use_cdn"=>true)) ?>" alt="<?= $mediaDescription ?>"/>
                                <i class="fa fa-play-circle center-div" aria-hidden="true"></i>
                            </div>
						<?php } ?>

                        <div class="media-content">
                            <h3><a class="media-link" href="<?= $GLOBALS['gLinkUrl'] ?>?type=media_id&amp;media_id=<?= $row['media_id'] ?>"><?= htmlText($row['description']) ?></a></h3>

							<?php if (!empty($row['subtitle'])) { ?>
                                <p class="content-subtitle"><?= htmlText($row['subtitle']) ?></p>
							<?php } ?>
							<?php if (!empty($row['full_name'])) { ?>
                                <p class="content-author">by <?= htmlText($row['full_name']) ?></p>
							<?php } ?>
							<?php if (!empty($row['detailed_description'])) { ?>
                                <p><?= htmlText($row['detailed_description']) ?></p>
							<?php } ?>
							<?php if (!empty($row['date_created'])) { ?>
                                <p>Created on <?= date("m/d/Y", strtotime($row['date_created'])) ?></p>
							<?php } ?>
                            <p>
								<?php if (!empty($row['video_identifier'])) { ?>
                                    <a data-video_identifier="<?= $row['video_identifier'] ?>" data-media_id="<?= $row['media_id'] ?>" data-web_page="<?= $mediaServicesRow['web_page'] ?>" class="watch-video"><span class="button">Watch Video</span></a>
								<?php } ?>
								<?php if (!empty($row['audio_file_id'])) { ?>
                                    <a data-description="<?= str_replace('"', "", $row['description']) . " Audio" ?>" href="/download.php?id=<?= $row['audio_file_id'] ?>"><span class="button">Download Audio</span></a>
								<?php } ?>
								<?php if (!empty($row['powerpoint_file_id'])) { ?>
                                    <a data-description="<?= str_replace('"', "", $row['description']) . " Powerpoint" ?>" href="/download.php?id=<?= $row['powerpoint_file_id'] ?>"><span class="button">Download Powerpoint</span></a>
								<?php } ?>
								<?php if (!empty($row['notes_file_id'])) { ?>
                                    <a data-description="<?= str_replace('"', "", $row['description']) . " Worksheet" ?>" href="/download.php?id=<?= $row['notes_file_id'] ?>"><span class="button">Download Worksheet</span></a>
								<?php } ?>
                            </p>
                        </div>
                    </div>
					<?php
				}

				if ($displayAsPlaylist) {
					?>
                    </div>
                    </div>

                    <?php if (empty($_GET['hide_others'])) { ?>
                        <div id="other_media_series">
                            <?php
                            $playlistsResultSet = executeQuery("select media_series.media_series_id, media_series.description, media_series.image_id, count(*) media_count"
                                . " from media_series join media using (media_series_id)"
                                . " where media_series.client_id = ? and media_series.inactive = 0 and media.inactive = 0"
                                . ($GLOBALS['gInternalConnection'] ? "" : " and media.internal_use_only = 0 and media_series.internal_use_only = 0")
                                . " group by media_series.media_series_id, media_series.description, media_series.image_id",
                                $GLOBALS['gClientId']);
                            while ($row = getNextRow($playlistsResultSet)) {
                                $mediaSeriesDescription = htmlText($row['description']);
                                ?>
                                <div class="other-media-series-item" data-media_series_id="<?= $row['media_series_id'] ?>">
                                    <img title="<?= $mediaSeriesDescription ?>" src="<?= getImageFilename($row['image_id'],array("use_cdn"=>true)) ?>" alt="<?= $mediaSeriesDescription ?>"/>
                                    <p>
                                        <span class="other-media-series-item-description"><?= $mediaSeriesDescription ?></span>
                                        <span class="other-media-series-item-count"><?= $row['media_count'] ?></span>
                                    </p>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    <?php } ?>

					<?php
				}
			} else {
				?>
                <h1>Video Categories</h1>
				<?= $this->iPageContent ?>
				<?php
				$resultSet = executeQuery("select * from media_categories where client_id = ? and inactive = 0 and internal_use_only = 0", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
                    <p><a href="<?= $GLOBALS['gLinkUrl'] ?>?type=code&amp;code=<?= $row['media_category_code'] ?>">View <?= htmlText($row['description']) ?></a></p>
					<?php
				}
			}
			?>
            <div class='clear-div'></div>
        </div>
		<?php
		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $("#show_more button").click(function () {
                $(".video-div").removeClass("hidden");
                $("#show_more").remove();
                return false;
            });
            $(".watch-video").each(function () {
                const webPage = $(this).data("web_page");
                if ($(window).width() > 800) {
                    $(this).attr("href", "//" + webPage + $(this).data("video_identifier")).attr("rel", "prettyPhoto");
                    $(this).prettyPhoto({social_tools: false, default_height: 480, default_width: 854, deeplinking: false});
                } else {
                    $(this).attr("href", "<?= $GLOBALS['gLinkUrl'] ?>?type=media_id&amp;media_id=" + $(this).data("media_id"));
                }
            });

            const mediaSeriesElements = $(".other-media-series-item");
            mediaSeriesElements.click(function() {
                const selectedSeries = $(this);
                mediaSeriesElements.removeClass("selected");
                selectedSeries.addClass("selected");

                const mediaSeriesId = $(this).data("media_series_id");
                loadAjaxRequest(`/retail-store-controller?ajax=true&url_action=get_media_series&media_series_id=${mediaSeriesId}`, function(returnArray) {
                    $("#media_series_description").html(returnArray.description);
                    $("#media_series_detailed_description").html(returnArray.detailed_description);
                    $("#media_series_list .media-div").remove();

                    const mediaSeriesQueue = $("#media_series_list");
                    returnArray.media.forEach(mediaItem => {
                        mediaSeriesQueue.append(`<div class="media-div"
                            data-video_identifier="${mediaItem.video_identifier}"
                            data-media_services_link_url="${mediaItem.media_services_link_url}"
                            data-media_service_code="${mediaItem.media_service_code}"
                            data-description="${mediaItem.description}"
                            data-media_id="${mediaItem.media_id}">

                            <div class="media-thumbnail">
                                <img title="${mediaItem.description}" src="${mediaItem.image_filename}" alt="${mediaItem.description}" />
                                <i class="fa fa-play-circle center-div" aria-hidden="true"></i>
                            </div>

                            <div class="media-content">
                                <h3><a class="media-link" href="#">${mediaItem.description}</a></h3>

                                ${mediaItem.subtitle ? `<p class="content-subtitle">${mediaItem.subtitle}</p>` : ""}
                                ${mediaItem.full_name ? `<p class="content-author">by ${mediaItem.full_name}</p>` : ""}
                                ${mediaItem.detailed_description ? `<p class="content-detailed-description">${mediaItem.detailed_description}</p>` : ""}
                                ${mediaItem.date_created ? `<p class="content-date-created">Created on ${mediaItem.date_created}</p>` : ""}

                                <p>
                                    ${mediaItem.video_identifier ? `<a data-video_identifier="${mediaItem.video_identifier}" data-media_id="${mediaItem.media_id}" data-web_page="${mediaItem.web_page}" class="watch-video"><span class="button">Watch Video</span></a>` : ""}
                                    ${mediaItem.audio_file_id ? `<a href="/download.php?id=${mediaItem.audio_file_id}"><span class="button">Download Audio</span></a>` : ""}
                                    ${mediaItem.powerpoint_file_id ? `<a href="/download.php?id=${mediaItem.powerpoint_file_id}"><span class="button">Download Powerpoint</span></a>` : ""}
                                    ${mediaItem.notes_file_id ? `<a href="/download.php?id=${mediaItem.notes_file_id}"><span class="button">Download Worksheet</span></a>` : ""}
                                </p>
                            </div>
                        </div>`);
                    })

                    addMediaClickListeners();
                    loadMediaThumbnails();
                });
            });

            function addMediaClickListeners() {
                // Add onclick to playlist items
                const mediaElements = $("#media_series_container .media-div");
                mediaElements.click(function (event) {
                    if ($(event.target).hasClass("media-link")) {
                        event.preventDefault();
                    }

                    const selectedVideo = $(this);
                    mediaElements.removeClass("selected");
                    selectedVideo.addClass("selected");

                    const embedContainer = $("#media_iframe_container");
                    embedContainer.find("iframe").attr("src", `//${selectedVideo.data("media_services_link_url")}${selectedVideo.data("video_identifier")}?autoplay=1`);
                    embedContainer.find(".media-description").html(selectedVideo.data("description"));
                });
                mediaElements.first().click();
            }

            function loadMediaThumbnails() {
                // Load thumbnails based on media service provider
                $("#media_series_container .media-div").each(function () {
                    const videoElement = $(this);
                    const videoId = videoElement.data("video_identifier");
                    const thumbnailElement = videoElement.find("img");
                    switch (videoElement.data("media_service_code")) {
                        case "YOUTUBE":
                            thumbnailElement.attr("src", `https://img.youtube.com/vi/${videoId}/sddefault.jpg`);
                            break;
                        case "VIMEO":
                            $("body").addClass("no-waiting-for-ajax");
                            loadAjaxRequest(`https://vimeo.com/api/oembed.json?url=https://vimeo.com/${videoId}`, function(returnArray) {
                                thumbnailElement.attr("src", returnArray.thumbnail_url);
                            });
                            break;
                    }
                });
            }

            addMediaClickListeners();
            loadMediaThumbnails();
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            p.video-title {
                text-align: left;
                font-size: 1.5rem;
                margin-bottom: 10px;
                font-weight: 600;
            }

            .media-div {
                border-bottom: 1px solid rgb(200, 200, 200);
                padding-top: 10px;
                padding-bottom: 10px;
                margin: 10px auto;
            }

            #media_player {
                margin: 10px auto;
                width: 100%;
                max-width: 720px;
            }

            #media_player p {
                padding-top: 20px;
            }

            .content-author {
                font-size: 1.0rem;
                color: rgb(96, 96, 96);
            }

            .content-subtitle {
                font-size: 1.0rem;
                font-weight: bold;
                color: rgb(118, 41, 19);
            }

            #media_series_container {
                display: flex;
            }

            #media_series_container iframe {
                width: 800px;
                height: 450px;
            }

            #media_series_list {
                padding: 0 2rem;
                flex-shrink: 1;
            }

            #media_series_container .media-div {
                display: flex;
                align-items: center;
                margin-bottom: 1rem;
                cursor: pointer;
            }

            #media_series_container .media-div img {
                width: 100%;
                height: 100%;
                object-fit: contain;
            }

            #media_series_container .media-div:hover img {
                filter: brightness(50%);
            }

            #media_series_container .media-div .fa {
                color: white;
                opacity: 0.8;
                font-size: 2.5rem;
                display: none;
            }

            #media_series_container .media-div:hover .fa {
                display: block;
            }

            #media_series_container .media-thumbnail {
                width: 25%;
                max-width: 25%;
                margin-right: 1rem;
                position: relative;
            }

            #media_iframe_container,
            #media_series_container .media-content {
                flex-shrink: 1;
            }

            #other_media_series {
                margin-top: 2.5rem;
            }

            #other_media_series .other-media-series-item {
                margin: 1rem;
                cursor: pointer;
            }
        </style>
		<?php
	}
}

$pageObject = new VideoPage();
$pageObject->displayPage();
