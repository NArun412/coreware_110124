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

$GLOBALS['gPageCode'] = "TESTIMONIALS";
$GLOBALS['gCacheProhibited'] = true;
require_once "shared/startup.inc";

class ThisPage extends Page {
	function displayTestimonials($parameters = array()) {
		$parameters = array($GLOBALS['gClientId']);
		if (!empty($parameters['testimonial_tag_code'])) {
			$parameters[] = $parameters['testimonial_tag_code'];
		}
		$count = 0;
		$resultSet = executeQuery("select * from testimonials where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") .
			(empty($parameters['testimonial_tag_code']) ? "" : " and testimonial_tag_id in (select testimonial_tag_id from testimonial_tags where testimonial_tag_code = ?)") .
			" order by sort_order,testimonial_id desc",$parameters);
		while ($row = getNextRow($resultSet)) {
			$count++;
?>
<div class="testimonial-wrapper">

<?php if (!empty($row['image_id'])) { ?>
<img class="image-<?= ($count % 2 == 0 ? "right" : "left") ?>" src="<?= getImageFilename($row['image_id'],array("use_cdn"=>true)) ?>">
<?php } ?>

<p class="testimonial-content"><?= htmlText($row['content']) ?></p>
<?php if (!empty($row['link_url'])) { ?>
<p class="testimonial-link"><a href="<?= $row['link_url'] ?>">More Info</a></p>
<?php } ?>
<?php if (!empty($row['full_name'])) { ?>
<p class="testimonial-author"><?= htmlText($row['full_name']) . (empty($row['job_title']) ? "" : ", " . $row['job_title']) ?></p>
<?php } ?>
</div>
<?php
		}
	}

	function internalCSS() {
?>
.testimonial-wrapper { margin: 20px; border-bottom: 1px solid rgb(200,200,200); padding: 20px; }
.testimonial-content { font-style: italic; }
.testimonial-author { text-align: right; font-weight: 600; }
<?php
	}
}

$pageObject = new ThisPage();
$pageObject->displayPage();
