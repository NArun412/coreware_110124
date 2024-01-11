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
%module:banner_group:banner_group_code=home_page[:element_id=_top_slider_content][:classes=banner-class][:randomize][:limit=9]%
*/

class BannerGroupPageModule extends PageModule {
	function createContent() {
		$bannerGroupCode = (empty($this->iParameters['banner_group_code']) ? array_shift($this->iParameters) : $this->iParameters['banner_group_code']);
		$bannerGroupId = getFieldFromId("banner_group_id", "banner_groups", "banner_group_code", $bannerGroupCode, "inactive = 0 and internal_use_only = 0");
		if (empty($bannerGroupId)) {
			return;
		}

		$bannerElementId = makeCode((empty($this->iParameters['element_id']) ? array_shift($this->iParameters) : $this->iParameters['element_id']), array("lowercase" => true));
		$resultSet = executeQuery("select * from banners join banner_group_links using (banner_id) where banner_group_id = ? and (start_time is null or start_time <= current_time) and " .
			"(end_time is null or end_time >= current_time) and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") .
			" and client_id = ? order by sequence_number", $bannerGroupId, $GLOBALS['gClientId']);
		?>
        <div id="<?= $bannerElementId ?>" class="banner-group <?= $this->iParameters['classes'] ?>">
			<?php
			$banners = array();
			while ($row = getNextRow($resultSet)) {
				$banners[] = $row;
			}
			if (in_array("randomize", $this->iParameters) || $this->iParameters['randomize']) {
				shuffle($banners);
			}

			$count = 0;
			foreach ($banners as $row) {
				$count++;
				if (!empty($this->iParameters['limit']) && is_numeric($this->iParameters['limit']) && $count > $this->iParameters['limit']) {
					break;
				}
				$thisBannerId = makeCode($bannerElementId . "-" . $row['banner_code'], array("lowercase" => true, "allow_dash" => true));
				if (substr($row['link_url'], 0, 4) != "http" && substr($row['link_url'], 0, 1) != "/" && $row['link_url'] != "#") {
					$row['link_url'] = "https://" . $row['link_url'];
				}
				$cssClasses = str_replace(",", " ", $row['css_classes']);
                $loading = $count == 1 ? "eager" : "lazy";
				?>
                <div class="banner <?= $cssClasses ?>" data-banner_id="<?= $row['banner_id'] ?>" id="<?= $thisBannerId ?>">
					<?php
					if ($row['use_content']) {
						echo $row['content'];
					} else {
						if ($row['link_url'] == "#" || empty($row['link_url'])) {
							$linkElement = "<img loading='$loading' alt='" . htmlText($row['description']) . "' src='" . getImageFilename($row['image_id'],array("use_cdn"=>true)) . "'>";
						} else {
							$linkElement = "<a href='" . $row['link_url'] . "'><img loading='$loading' alt='" . htmlText($row['description']) . "' src='" . getImageFilename($row['image_id'],array("use_cdn"=>true)) . "'></a>";
						}
						echo $linkElement;
						?>
						<?php if (empty($row['hide_description'])) { ?>
                            <div class="banner-description"><?= htmlText($row['description']) ?></div>
						<?php } ?>
						<?= $row['content'] ?>
					<?php } ?>
                </div>
				<?php
			}
			?>
        </div>
		<?php
	}
}
