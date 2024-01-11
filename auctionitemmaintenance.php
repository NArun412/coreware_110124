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

$GLOBALS['gPageCode'] = "AUCTIONITEMMAINT";
require_once "shared/startup.inc";

/*
 * To Do
 *
 * Auction items that are ended should be readonly
 * Separate date & time controls for start and end times
 * list of current bids... include if user has maximum bid
 * if an auction has ALREADY started, don't allow moving the end time BACK
 *
 */

class AuctionItemMaintenancePage extends Page {

	function setup() {
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
		}
	}

	function massageDataSource() {
		$this->iDataSource->addColumnControl("bid_increment", "help_label", "Minimum amount over current bid. Leave blank to use default.");
		$this->iDataSource->addColumnControl("bid_close_extension", "help_label", "Minutes after last bid to make earliest end time. Leave blank to use default.");

		$this->iDataSource->addColumnControl("auction_item_specifications", "data_type", "custom");
		$this->iDataSource->addColumnControl("auction_item_specifications", "list_table", "auction_item_specifications");
		$this->iDataSource->addColumnControl("auction_item_specifications", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("auction_item_specifications", "form_label", "Specifications");

		$this->iDataSource->addColumnControl("auction_item_product_category_links", "data_type", "custom");
		$this->iDataSource->addColumnControl("auction_item_product_category_links", "links_table", "auction_item_product_category_links");
		$this->iDataSource->addColumnControl("auction_item_product_category_links", "control_table", "product_categories");
		$this->iDataSource->addColumnControl("auction_item_product_category_links", "control_class", "MultipleSelect");
		$this->iDataSource->addColumnControl("auction_item_product_category_links", "form_label", "Product Categories");

		$this->iDataSource->addColumnControl("auction_item_files", "data_type", "custom");
		$this->iDataSource->addColumnControl("auction_item_files", "list_table", "auction_item_files");
		$this->iDataSource->addColumnControl("auction_item_files", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("auction_item_files", "form_label", "Files");

		$this->iDataSource->addColumnControl("auction_item_images", "data_type", "custom");
		$this->iDataSource->addColumnControl("auction_item_images", "list_table", "auction_item_images");
		$this->iDataSource->addColumnControl("auction_item_images", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("auction_item_images", "form_label", "Images");

		$this->iDataSource->addColumnControl("auction_item_videos", "data_type", "custom");
		$this->iDataSource->addColumnControl("auction_item_videos", "list_table", "auction_item_videos");
		$this->iDataSource->addColumnControl("auction_item_videos", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("auction_item_videos", "form_label", "Videos");

		$this->iDataSource->addColumnControl("auction_item_bids", "data_type", "custom");
		$this->iDataSource->addColumnControl("auction_item_bids", "list_table", "auction_item_bids");
		$this->iDataSource->addColumnControl("auction_item_bids", "control_class", "EditableList");
		$this->iDataSource->addColumnControl("auction_item_bids", "readonly", "true");
		$this->iDataSource->addColumnControl("auction_item_bids", "form_label", "Bids");


		$bidCloseExtention = getPreference("AUCTION_BID_EXTENSION");
		if (!empty($bidCloseExtention) && is_numeric($bidCloseExtention) && $bidCloseExtention > 0) {
			$this->iDataSource->addColumnControl("bid_close_extension", "default_value", $bidCloseExtention);
			$this->iDataSource->addColumnControl("bid_close_extension", "readonly", true);
		}
	}

}

$pageObject = new AuctionItemMaintenancePage("auction_items");
$pageObject->displayPage();
