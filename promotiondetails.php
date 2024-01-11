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

$GLOBALS['gPageCode'] = "PROMOTIONDETAILS";
require_once "shared/startup.inc";

class PromotionDetailsPage extends Page {
	function setup() {
		if (empty($_GET['promotion_id']) && !empty($_GET['id'])) {
			$_GET['promotion_id'] = $_GET['id'];
		}
		$promotionId = getFieldFromId("promotion_id", "promotions", "promotion_id", $_GET['promotion_id'],
			"client_id = " . $GLOBALS['gClientId'] . " and inactive = 0 and internal_use_only = 0 and user_id is null");
	}

	function headerIncludes() {
		$promotionId = getFieldFromId("promotion_id", "promotions", "promotion_id", $_GET['promotion_id'],
			"client_id = " . $GLOBALS['gClientId'] . " and inactive = 0 and internal_use_only = 0 and user_id is null");
		if (!empty($promotionId)) {
			$promotionRow = ShoppingCart::getCachedPromotionRow($promotionId);
			$urlAliasTypeCode = getReadFieldFromId("url_alias_type_code", "url_alias_types", "parameter_name", "promotion_id",
				"table_id = (select table_id from tables where table_name = 'promotions')");
			$urlLink = "https://" . $_SERVER['HTTP_HOST'] . "/" .
				(empty($urlAliasTypeCode) || empty($promotionRow['link_name']) ? "promotiondetails.php?id=" . $promotionId : $urlAliasTypeCode . "/" . $promotionRow['link_name']);
			$imageUrl = (empty($promotionRow['image_id']) ? "" : "https://" . $_SERVER['HTTP_HOST'] . "/getimage.php?id=" . $promotionRow['image_id']);
			?>
			<meta property="og:title" content="<?= str_replace('"', "'", $promotionRow['description']) ?>"/>
			<meta property="og:type" content="website"/>
			<meta property="og:url" content="<?= $urlLink ?>"/>
			<meta property="og:image" content="<?= $imageUrl ?>"/>
			<meta property="og:description" content="<?= str_replace('"', "'", $promotionRow['description']) ?>"/>
			<?php
		}
	}

	function setPageTitle() {
		if (empty($_GET['promotion_id']) && !empty($_GET['id'])) {
			$_GET['promotion_id'] = $_GET['id'];
		}
		$description = getFieldFromId("description", "promotions", "promotion_id", $_GET['promotion_id'], "inactive = 0 and internal_use_only = 0 and user_id is null");
		if (!empty($description)) {
			return $GLOBALS['gClientRow']['business_name'] . " | " . $description;
		}
	}

	function mainContent() {
		$promotionId = getFieldFromId("promotion_id", "promotions", "promotion_id", $_GET['promotion_id'],
			"client_id = " . $GLOBALS['gClientId'] . " and inactive = 0 and internal_use_only = 0 and user_id is null");
		if (empty($promotionId)) {
			$urlAliasTypeCode = getReadFieldFromId("url_alias_type_code", "url_alias_types", "parameter_name", "promotion_id",
				"table_id = (select table_id from tables where table_name = 'promotions')");
			$resultSet = executeQuery("select * from promotions where inactive = 0 and internal_use_only = 0 and user_id is null and client_id = ?", $GLOBALS['gClientId']);
			if ($resultSet['row_count'] > 0) {
				?>
				<ul id='promotions_list'>
					<?php
					while ($promotionRow = getNextRow($resultSet)) {
						$urlLink = "/" . (empty($urlAliasTypeCode) || empty($promotionRow['link_name']) ? "promotiondetails.php?id=" . $promotionRow['promotion_id'] : $urlAliasTypeCode . "/" . $promotionRow['link_name']);
						?>
						<li><a href='<?= $urlLink ?>'><?= htmlText($promotionRow['description']) ?></a></li>
						<?php
					}
					?>
				</ul>
				<?php
			}
		} else {
			$promotionRow = ShoppingCart::getCachedPromotionRow($promotionId);
			$promotionRow['start_date'] = (empty($promotionRow['start_date']) ? "" : date("m/d/Y",strtotime($promotionRow['start_date'])));
			$promotionRow['expiration_date'] = (empty($promotionRow['expiration_date']) ? "" : date("m/d/Y",strtotime($promotionRow['expiration_date'])));
			$promotionRow['publish_start_date'] = (empty($promotionRow['publish_start_date']) ? "" : date("m/d/Y",strtotime($promotionRow['publish_start_date'])));
			$promotionRow['publish_end_date'] = (empty($promotionRow['publish_end_date']) ? "" : date("m/d/Y",strtotime($promotionRow['publish_end_date'])));
			$promotionRow['event_start_date'] = (empty($promotionRow['event_start_date']) ? "" : date("m/d/Y",strtotime($promotionRow['event_start_date'])));
			$promotionRow['event_end_date'] = (empty($promotionRow['event_end_date']) ? "" : date("m/d/Y",strtotime($promotionRow['event_end_date'])));
			$promotionRow['location'] = getFieldFromId("description", "location", "location_id", $promotionRow['location_id']);
			$promotionRow['product_manufacturer'] = getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $promotionRow['product_manufacturer_id']);
			$promotionRow['image_url'] = getImageFilename($promotionRow['image_id']);
			echo PlaceHolders::massageContent($this->iPageData['content'], $promotionRow);
			echo PlaceHolders::massageContent($this->iPageData['after_form_content'], $promotionRow);
		}
		return true;
	}
}

$pageObject = new PromotionDetailsPage();
$pageObject->displayPage();
