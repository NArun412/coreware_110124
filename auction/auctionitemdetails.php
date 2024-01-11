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

$GLOBALS['gPageCode'] = "AUCTIONITEMDETAILS";
$GLOBALS['gCacheProhibited'] = true;
require_once "shared/startup.inc";

class AuctionItemDetailsPage extends Page {

	var $iAuctionItemId = "";
	var $iAuctionItemDetails = null;
	var $iAuctionItemRow = null;

	function setup() {
		$sourceId = "";
		if (array_key_exists("aid", $_GET)) {
			$sourceId = getReadFieldFromId("source_id", "sources", "source_code", strtoupper($_GET['aid']));
		}
		if (array_key_exists("source", $_GET)) {
			$sourceId = getReadFieldFromId("source_id", "sources", "source_code", strtoupper($_GET['source']));
		}
		if (!empty($sourceId)) {
			setCoreCookie("source_id", $sourceId, 6);
		}

		if (empty($_GET['auction_item_id']) && !empty($_GET['id'])) {
			$_GET['auction_item_id'] = $_GET['id'];
		}
		$this->iAuctionItemId = getReadFieldFromId("auction_item_id", "auction_items", "auction_item_id", $_GET['auction_item_id'],"deleted = 0 and published = 1");
		if (empty($this->iAuctionItemId)) {
			header("Location: /");
			exit;
		}
		PlaceHolders::setPlaceholderKeyValue("auction_item_id",$this->iAuctionItemId);
		$this->iAuctionItemDetails = new AuctionItemDetails($this->iAuctionItemId);
		if (strpos($_SERVER['REQUEST_URI'], "id=") !== false) {
			$this->iAuctionItemRow = Auction::getCachedAuctionItemRow($this->iAuctionItemId);
			$urlAliasTypeCode = getUrlAliasTypeCode("auction_items","auction_item_id", "id");
			if (!empty($urlAliasTypeCode) && !empty($this->iAuctionItemRow['link_name'])) {
				$linkUrl = "/" . $urlAliasTypeCode . "/" . $this->iAuctionItemRow['link_name'];
				header("HTTP/1.1 301 Moved Permanently");
				header("Location: " . $linkUrl);
				exit();
			}
		}
	}

	function headerIncludes() {
		if (!empty($this->iAuctionItemId)) {
			$this->iAuctionItemDetails->loadAuctionItemRow();
			$this->iAuctionItemRow = $this->iAuctionItemDetails->iAuctionItemRow;
			$urlAliasTypeCode = getUrlAliasTypeCode("auction_items","auction_item_id", "id");
			$linkUrl = (empty($urlAliasTypeCode) || empty($this->iAuctionItemRow['link_name']) ? "auction-item-details?id=" . $this->iAuctionItemId : $urlAliasTypeCode . "/" . $this->iAuctionItemRow['link_name']);

			$metaDescription = CustomField::getCustomFieldData($this->iAuctionItemRow,"META_DESCRIPTION","AUCTION_ITEMS");
			if (empty($metaDescription)) {
				$metaDescription = $this->iAuctionItemRow['description'];
			}

			$canonicalLink = CustomField::getCustomFieldData($this->iAuctionItemRow,"CANONICAL_LINK","AUCTION_ITEMS");
			if (empty($canonicalLink)) {
				$canonicalLink = $linkUrl;
			}
			$GLOBALS['gCanonicalLink'] = '<link rel="canonical" href="https://' . $_SERVER['HTTP_HOST'] . '/' . $canonicalLink . '"/>';
			?>
			<meta property="og:title" content="<?= str_replace('"', "'", $metaDescription) ?>"/>
			<meta property="og:type" content="auction_item"/>
			<meta property="og:url" content="https://<?= $_SERVER['HTTP_HOST'] ?>/<?= $linkUrl ?>"/>
			<meta property="og:image" content="<?= $this->iAuctionItemRow['image_url'] ?>"/>
			<meta property="og:description" content="<?= str_replace('"', "'", $this->iAuctionItemRow['detailed_description']) ?>"/>
			<?php
			return true;
		}
		return false;
	}

	function setPageTitle() {
		if (empty($_GET['auction_item_id']) && !empty($_GET['id'])) {
			$_GET['auction_item_id'] = $_GET['id'];
		}
		if (!empty($_GET['auction_item_id'])) {
            $metaDescription = getReadFieldFromId("description", "auction_items", "auction_item_id", $_GET['auction_item_id']) . " | " . $GLOBALS['gClientName'];
			return $metaDescription;
		}
		return false;
	}

	function inlineJavascript() {
		echo $this->iAuctionItemDetails->inlineJavascript();
	}

	function onLoadJavascript() {
		echo $this->iAuctionItemDetails->onLoadJavascript();
	}

	function javascript() {
		echo $this->iAuctionItemDetails->javascript();
	}

	function mainContent() {
		echo $this->iPageData['content'];
		echo $this->iAuctionItemDetails->mainContent();
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function internalCSS() {
		echo $this->iAuctionItemDetails->internalCSS();
	}

	function hiddenElements() {
		echo $this->iAuctionItemDetails->hiddenElements();
	}

}

$pageObject = new AuctionItemDetailsPage();
$pageObject->displayPage();
