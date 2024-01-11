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
%module:video:media-link-name%
%module:video:media_id=222%
%module:video:link_name=xxx-xxx-xxx%
%module:video:media_series_id=222%
%module:video:media_series_code=XXXXXX%
%module:video:media_category_id=222%
%module:video:media_category_code=XXXXXX%
%module:video:media_category_group_id=222%
%module:video:media_category_group_code=XXXXXX%

Options

[hide_h1=true]  - Don't show H1 elements
[link_url]      - URL to go to when a link is clicked on. Default is video.php
[as_playlist]   - Only applicable for media series, display it as playlist with an embedded player rather than links
[no_autoplay]   - Don't autoplay the videos, false by default
*/

class VideoPageModule extends PageModule {
	function createContent() {
        $mediaServicesRows = getCachedData("media_services_rows","media_services_rows");
        if (empty($mediaServicesRows)) {
            $resultSet = executeQuery("select * from media_services");
            while ($row = getNextRow($resultSet)) {
                $mediaServicesRows[$row['media_service_id']] = $row;
            }
            setCachedData("media_services_rows","media_services_rows",$mediaServicesRows,168);
        }
        $mediaCategoryRows = getCachedData("media_categories","media_categories");
        if (empty($mediaCategoryRows)) {
	        $resultSet = executeQuery("select * from media_categories where client_id = ? and inactive = 0 and internal_use_only = 0", $GLOBALS['gClientId']);
	        while ($row = getNextRow($resultSet)) {
                $mediaCategoryRows[$row['media_category_id']] = $row;
	        }
	        setCachedData("media_categories","media_categories",$mediaCategoryRows);
        }
		if (count($this->iParameters) == 1 && !empty($this->iParameters[0]) && !is_numeric($this->iParameters[0])) {
			$this->iParameters['link_name'] = $this->iParameters[0];
		}
		if (!empty($this->iParameters['link_name']) && empty($this->iParameters['media_id'])) {
			$this->iParameters['media_id'] = getFieldFromId("media_id", "media", "link_name", $this->iParameters['link_name']);
		}
		$autoplay = empty($this->iParameters['no_autoplay']);
		if (!empty($this->iParameters['media_id'])) {
			$resultSet = executeQuery("select * from media where inactive = 0 and client_id = ? and media_id = ?", $GLOBALS['gClientId'], $this->iParameters['media_id']);
			$row = getNextRow($resultSet);
			$mediaServicesRow = $mediaServicesRows[$row['media_service_id']];
			?>
			<?php if (empty($this->iParameters['hide_h1'])) { ?>
                <h1><?= htmlText($row['description']) ?></h1>
			<?php } ?>
            <div id="media_player">
                <div class='embed-container'>
                    <iframe src="//<?= $mediaServicesRow['link_url'] . $row['video_identifier'] ?><?= $autoplay ? "?autoplay=1" : "" ?>" allow="autoplay; encrypted-media" frameborder="0" allowfullscreen></iframe>
                </div>
				<?php if (!empty($row['detailed_description'])) { ?>
                    <p class="media-detailed-description"><?= htmlText($row['detailed_description']) ?></p>
				<?php } ?>
				<?php if (!empty($row['date_created'])) { ?>
                    <p class="media-date-created">Created on <?= date("m/d/Y", strtotime($row['date_created'])) ?></p>
				<?php } ?>
            </div>
			<?php
		} else if (!empty($this->iParameters['media_category_group_id']) || !empty($this->iParameters['media_category_group_code'])) {
			if (empty($this->iParameters['media_category_group_id'])) {
				$this->iParameters['media_category_group_id'] = getFieldFromId("media_category_group_id", "media_category_groups", "media_category_group_code", $this->iParameters['media_category_group_code']);
			}
			?>
			<?php if (empty($this->iParameters['hide_h1'])) { ?>
                <h1><?= htmlText(getFieldFromId("description", "media_category_groups", "media_category_group_id", $this->iParameters['media_category_group_id'])) ?></h1>
			<?php } ?>
            <div class="media-wrapper">
				<?php
				$resultSet = executeQuery("select * from media_series where client_id = ? and inactive = 0 and internal_use_only = 0 and " .
					"(media_series_id in (select distinct media_series_id from media where media_series_id is not null and " .
					"inactive = 0 and internal_use_only = 0) || link_url is not null) and media_series_id in (select media_series_id from media_series_category_links where " .
					"media_category_id = (select media_category_id from media_categories " .
					"where media_category_group_id = ?)) order by sort_order,description", $GLOBALS['gClientId'], $this->iParameters['media_category_group_id']);
				$count = 0;
				while ($row = getNextRow($resultSet)) {
					$linkUrl = (empty($this->iParameters['link_url']) ? "/video.php" : $this->iParameters['link_url']) . "?media_series_id=" . $row['media_series_id'];
					$count++;
					?>
                    <div class="video-div three-per-row">
                        <p class="video-title"><?= htmlText($row['description']) ?></p>
						<?php if (!empty($row['image_id'])) { ?>
                            <p><a href="<?= $linkUrl ?>"><img title="<?= htmlText($row['description']) ?>" src="<?= getImageFilename($row['image_id'], array("use_cdn" => true)) ?>" alt="<?= htmlText($row['description']) ?>"/></a></p>
						<?php } ?>
						<?php if (!empty($row['detailed_description'])) { ?>
                            <p class="media-detailed-description"><?= $row['detailed_description'] ?></p>
						<?php } ?>
                        <p><a href="<?= $linkUrl ?>"><span class="button">View Videos</span></a></p>
                        <div class='clear-div'></div>
                    </div>
					<?php
				}
				?>
            </div>
			<?php
		} else if (!empty($this->iParameters['media_category_id']) || !empty($this->iParameters['media_category_code'])) {
			if (empty($this->iParameters['media_category_id'])) {
                foreach ($mediaCategoryRows as $categoryRow) {
                    if ($this->iParameters['media_category_code'] == $categoryRow['media_category_code']) {
	                    $this->iParameters['media_category_id'] = $categoryRow['media_category_id'];
                    }
                }
			}
			$categoryDetails = $mediaCategoryRows[$this->iParameters['media_category_id']]['detailed_description'];
			?>
			<?php if (empty($this->iParameters['hide_h1'])) { ?>
                <h1><?= htmlText($mediaCategoryRows[$this->iParameters['media_category_id']]['description']) ?></h1>
			<?php } ?>
			<?php if (!empty($categoryDetails)) { ?>
                <p class="media-category-detailed-description"><?= htmlText($categoryDetails) ?></p>
			<?php } ?>
            <div class="media-wrapper">
				<?php
				$mediaSet = executeQuery("select * from media where client_id = ? and inactive = 0 and video_identifier is not null and " .
					"media_id in (select media_id from media_category_links where media_category_id = ?) order by date_created desc,sort_order,description",
					$GLOBALS['gClientId'], $this->iParameters['media_category_id']);
				$count = 0;
				while ($row = getNextRow($mediaSet)) {
					$count++;
					$mediaServicesRow = $mediaServicesRows[$row['media_service_id']];
					?>
                    <div class="video-div three-per-row">
                        <p class="video-title"><?= htmlText($row['description']) ?></p>
                        <div class='embed-container'>
                            <iframe src="//<?= $mediaServicesRow['link_url'] . $row['video_identifier'] ?>" frameborder="0" allow="encrypted-media" allowfullscreen></iframe>
                        </div>
						<?php if (!empty($row['detailed_description'])) { ?>
                            <p class="media-detailed-description"><?= htmlText($row['detailed_description']) ?></p>
						<?php } ?>
						<?php if (!empty($row['date_created'])) { ?>
                            <p class="media-date-created">Created on <?= date("m/d/Y", strtotime($row['date_created'])) ?></p>
						<?php } ?>
                        <div class='clear-div'></div>
                    </div>
					<?php
				}
				?>
            </div>
			<?php
		} else if (!empty($this->iParameters['media_series_id']) || !empty($this->iParameters['media_series_code'])) {
			if (empty($this->iParameters['media_series_id'])) {
				$this->iParameters['media_series_id'] = getFieldFromId("media_series_id", "media_series",
					"media_series_code", $this->iParameters['media_series_code']);
			}
			$displayAsPlaylist = !empty($this->iParameters['as_playlist']);
			$seriesDetails = getFieldFromId("detailed_description", "media_series", "media_series_id", $this->iParameters['media_series_id']);
			if ($displayAsPlaylist) {
				?>
                <div id="media_series_container">
                <div id="media_iframe_container">
                    <iframe allow="autoplay; encrypted-media" allowfullscreen></iframe>
                    <h2 class="media-description"></h2>
                </div>

                <div id="media_series_list">
			<?php } ?>

			<?php if (empty($this->iParameters['hide_h1'])) { ?>
                <h1 id="media_series_description"><?= htmlText(getFieldFromId("description", "media_series", "media_series_id", $this->iParameters['media_series_id'])) ?></h1>
			<?php } ?>
			<?php if (!empty($seriesDetails)) { ?>
                <p id="media_series_detailed_description"><?= htmlText($seriesDetails) ?></p>
			<?php } ?>
			<?= $this->iPageContent ?>
			<?php
			$resultSet = executeQuery("select * from media"
				. " where client_id = ? and media_series_id = ? and inactive = 0"
				. ($GLOBALS['gInternalConnection'] ? "" : " and media.internal_use_only = 0")
				. " order by date_created desc, sort_order, description", $GLOBALS['gClientId'], $this->iParameters['media_series_id']);
			while ($row = getNextRow($resultSet)) {
				$mediaLinkUrl = (empty($this->iParameters['link_url']) ? "/video.php" : $this->iParameters['link_url']) . "?media_id=" . $row['media_id'];
				$mediaDescription = htmlText($row['description']);
				$mediaServicesRow = $mediaServicesRows[$row['media_service_id']];
				?>
                <div class="media-div"
                     data-video_identifier="<?= $row['video_identifier'] ?>"
                     data-media_services_link_url="<?= $mediaServicesRow['link_url'] ?>"
                     data-media_service_code="<?= $mediaServicesRow['media_service_code'] ?>"
                     data-description="<?= htmlText($row['description']) ?>"
                     data-media_id="<?= $row['media_id'] ?>">

					<?php if ($displayAsPlaylist) { ?>
                        <div class="media-thumbnail">
                            <img title="<?= $mediaDescription ?>" src="<?= getImageFilename($row['image_id'], array("use_cdn" => true)) ?>" alt="<?= $mediaDescription ?>"/>
                            <i class="fa fa-play-circle center-div" aria-hidden="true"></i>
                        </div>
					<?php } ?>

                    <div class="media-content">
                        <h3><a class="media-link" href="<?= $mediaLinkUrl ?>"><?= htmlText($row['description']) ?></a></h3>

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
				<?php
			}
		} else {
			?>
			<?php if (empty($this->iParameters['hide_h1'])) { ?>
                <h1>Video Categories</h1>
			<?php } ?>
			<?php
			foreach ($mediaCategoryRows as $row) {
				$linkUrl = (empty($this->iParameters['link_url']) ? "/video.php" : $this->iParameters['link_url']) . "?media_category_code=" . $row['media_category_code'];
				?>
                <div class="media-category"><a href="<?= $linkUrl ?>">View <?= htmlText($row['description']) ?></a></div>
				<?php
			}
		} ?>

        <style>
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
        </style>

        <script>
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
                embedContainer.find("iframe").attr("src", `//${ selectedVideo.data("media_services_link_url") }${ selectedVideo.data("video_identifier") }<?= $autoplay ? "?autoplay=1" : "" ?>`);
                embedContainer.find(".media-description").html(selectedVideo.data("description"));
            });
            mediaElements.first().click();

            // Load thumbnails based on media service provider
            mediaElements.each(function () {
                const videoElement = $(this);
                const videoId = videoElement.data("video_identifier");
                const thumbnailElement = videoElement.find("img");
                switch (videoElement.data("media_service_code")) {
                    case "YOUTUBE":
                        thumbnailElement.attr("src", `https://img.youtube.com/vi/${ videoId }/sddefault.jpg`);
                        break;
                    case "VIMEO":
                        $("body").addClass("no-waiting-for-ajax");
                        loadAjaxRequest(`https://vimeo.com/api/oembed.json?url=https://vimeo.com/${ videoId }`, function(returnArray) {
                            thumbnailElement.attr("src", returnArray.thumbnail_url);
                        });
                        break;
                }
            });
        </script>

		<?php
	}
}
