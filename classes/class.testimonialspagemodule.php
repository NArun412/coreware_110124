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
%module:testimonials:[testimonial_tag_code=home_page][:element_id=_top_slider_content][:classes=css-class]%
*/

class TestimonialsPageModule extends PageModule {
	function createContent() {
		$testimonialTagCode = $this->iParameters['testimonial_tag_code'];
		$testimonialTagId = getFieldFromId("testimonial_tag_id","testimonial_tags","testimonial_tag_code",$testimonialTagCode);

		$elementId = $this->iParameters['element_id'];
		if (empty($elementId)) {
		    $elementId = "_testimonials";
        }
		$resultSet = executeQuery("select * from testimonials where " . (empty($testimonialTagId) ? "" : "testimonial_tag_id = " . $testimonialTagId . " and ") . "client_id = ? and " .
			"inactive = 0 and internal_use_only = 0 order by sort_order",$GLOBALS['gClientId']);
?>
<div id="<?= $elementId ?>" class="testimonials <?= $this->iParameters['classes'] ?>">
<?php
		while ($row = getNextRow($resultSet)) {
?>
	<div class="testimonial" data-testimonial_id="<?= $row['testimonial_id'] ?>">
<?php if (!empty($row['image_id'])) { ?>
		<a class="testimonial-image" href="<?= $row['link_url'] ?>"><img src="<?= getImageFilename($row['image_id'],array("use_cdn"=>true)) ?>" /></a>
<?php } ?>
		<div class="testimonial-content">
			<?= $row['content'] ?>
<?php if (!empty($row['full_name'])) { ?>
			<p><?= htmlText($row['full_name'] . (empty($row['job_title']) ? "" : "," . $row['job_title'])) ?></p>
<?php } ?>
		</div>
	</div>
<?php
		}
?>
</div>
<?php
	}
}
