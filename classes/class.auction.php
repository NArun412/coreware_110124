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

/**
 * class Auction
 *
 * Auction Listing searches
 *
 * @author Kim D Geiger
 */
class Auction {
	var $iSearchText = "";
	var $iDisplaySearchText = "";
	var $iOffset = 0;
	var $iSelectLimit = 20;
	var $iLimitQuery = false;
	var $iResultCount = 0;
	var $iOutOfStock = false;
	var $iQuery = "";
	var $iQueryTime = 0;
	var $iAllowInternalUseOnly = false;
	var $iDontLogSearchTerm = false;
	private $iAuctionItemIds = array();
	private $iSpecificAuctionItemIds = array();
	private $iAuctionItemResults = array();
	private $iDepartmentIds = array();
	private $iCategoryIds = array();
	private $iTagIds = array();
	private $iExcludeCategories = array();
	private $iExcludeDepartments = array();
	private $iExcludeManufacturers = array();
	private $iAllCategoryIds = array();
	private $iBidStatistics = array();
	private $iErrorMessage = "";

	function __construct() {
		ProductCatalog::getStopWords();
	}

	/**
	 * Get HTML for the auction item search results
	 */
	public static function getAuctionResultHtml() {
		$listType = $_COOKIE['result_display_type'];

		if ($listType == "list") {
			$auctionResultHtml = getFragment("auction_list_result");
			if (empty($auctionResultHtml)) {
				ob_start();
				?>
                <div id="_auction_result" class="hidden">
                    <div class="auction-item auction-list-item %other_classes%" id="auction_item_%auction_item_id%" data-auction_item_id="%auction_item_id%">
                        <input type="hidden" class="auction-detail-link" value="%auction_item_detail_link%">
                        <div class="click-auction-detail">
                            <div class="auction-item-thumbnail">
                                <img loading='lazy' alt='thumbnail image' src="/getimage.php?id=%auction_item_image%">
                            </div>
                            <div class="auction-item-description">%description%</div>
                            <div>
                                <p>Start Time: <span>%start_time%</span></p>
                                <p>End Time: <span>%end_time%</span></p>
                            </div>
                        </div>
                    </div>
                </div>
				<?php
				$auctionResultHtml = ob_get_clean();
			}
		}
		if (empty($auctionResultHtml)) {
			$auctionResultHtml = getPageTextChunk("auction_result");
		}
		if (empty($auctionResultHtml)) {
			$auctionResultHtml = getFragment("auction_result");
		}
		if (empty($auctionResultHtml)) {
			ob_start();
			?>
            <div id="_auction_result" class="hidden">
                <div class="auction-item %other_classes%" id="auction_item_%auction_item_id%" data-auction_item_id="%auction_item_id%">
                    <input type="hidden" class="auction-detail-link" value="%auction_item_detail_link%">
                    <div class="click-auction-detail">
                        <div class="auction-item-thumbnail">
                            <img loading='lazy' alt='thumbnail image' %image_src%="%small_image_url%">
                        </div>
                        <div class="auction-item-description">%description%</div>
                        <div class="auction-item-price-wrapper"><span class="dollar">$</span><span class="catalog-item-price">%sale_price%</span></div>
                    </div>
                </div>
            </div>
			<?php
			$auctionResultHtml = ob_get_clean();
		}
		return $auctionResultHtml;
	}

	/**
	 * For each of the passed in auction item ID, reindex the auction item.
	 */
	public static function reindexAuctionItems($auctionItemIdArray) {
		ProductCatalog::getStopWords();

		$count = 0;
		if (!is_array($auctionItemIdArray)) {
			$auctionItemIdArray = array($auctionItemIdArray);
		}

		foreach ($auctionItemIdArray as $auctionItemId) {
			$GLOBALS['gPrimaryDatabase']->startTransaction();
			executeQuery("delete from auction_item_search_word_values where auction_item_id = ?", $auctionItemId);
			$wordValues = array();
			$auctionItemRow = self::getCachedAuctionItemRow($auctionItemId);
			$fieldValues = array($auctionItemRow['description'], $auctionItemRow['detailed_description']);
			foreach ($auctionItemRow['auction_item_specifications'] as $thisRecord) {
				$fieldValues[] = $thisRecord['field_value'];
			}
			foreach ($fieldValues as $thisFieldValue) {
				$wordListInfo = ProductCatalog::getSearchWords($thisFieldValue);
				$wordList = $wordListInfo['search_words'];
				foreach ($wordList as $thisWord) {
					$thisWord = strtolower(trim($thisWord));
					if (empty($thisWord)) {
						continue;
					}
					if (array_key_exists($thisWord, $GLOBALS['gStopWords'])) {
						continue;
					}
					$thisWord = trim(Inflect::singularize($thisWord));
					if (strlen($thisWord) <= 1) {
						continue;
					}
					if (!array_key_exists($thisWord, $wordValues)) {
						$wordValues[$thisWord] = 1;
					} else {
						$wordValues[$thisWord]++;
					}
				}
			}

			foreach ($wordValues as $thisWord => $wordValue) {
				if (empty($wordValue) || strlen($thisWord) <= 1) {
					continue;
				}
				$productSearchWordId = getFieldFromId("product_search_word_id", "product_search_words", "search_term", strtolower($thisWord));
				if (empty($productSearchWordId)) {
					$insertSet = executeQuery("insert into product_search_words (client_id,search_term) values (?,?)", $GLOBALS['gClientId'], strtolower($thisWord));
					$productSearchWordId = $insertSet['insert_id'];
				}
				$auctionItemSearchWordValueId = getFieldFromId("auction_item_search_word_value_id", "auction_item_search_word_values", "product_search_word_id", $productSearchWordId, "auction_item_id = ?", $auctionItemId);
				if (empty($auctionItemSearchWordValueId)) {
					$resultSet = executeQuery("insert into auction_item_search_word_values (product_search_word_id,auction_item_id,search_value) values (?,?,?)",
						$productSearchWordId, $auctionItemId, $wordValue);
				}
			}
			executeQuery("update auction_items set reindex = 0 where auction_item_id = ?", $auctionItemId);
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
			$count++;
		}
		return $count;
	}

	/**
	 * Get auction Item row for passed in auction item ID
	 */
	public static function getCachedAuctionItemRow($auctionItemId, $additionalWhere = "") {
		if (empty($auctionItemId)) {
			return false;
		}
		if (!is_array($GLOBALS['gAuctionItemRows'])) {
			$GLOBALS['gAuctionItemRows'] = array();
		}
		if (!array_key_exists($auctionItemId, $GLOBALS['gAuctionItemRows'])) {
			$resultSet = executeQuery("select *,(select max(amount) from auction_item_bids where auction_item_id = auction_items.auction_item_id and inactive = 0) as high_bid_amount," .
				"(select user_id from auction_item_bids where auction_item_id = auction_items.auction_item_id and inactive = 0 and amount = (select max(amount) from auction_item_bids where " .
				"auction_item_id = auction_items.auction_item_id and inactive = 0) order by log_time desc limit 1) as high_bid_user_id," .
				"(select group_concat(concat_ws('|||',image_id,description) SEPARATOR '||||||') from auction_item_images where " .
				"auction_item_id = auction_items.auction_item_id) as auction_item_images,(select group_concat(concat_ws('|||',auction_specification_id,field_value) SEPARATOR '||||||') from " .
				"auction_item_specifications where auction_item_id = auction_items.auction_item_id) as auction_item_specifications from auction_items where auction_items.auction_item_id = ?" .
				(empty($additionalWhere) ? "" : " and " . $additionalWhere), $auctionItemId);
			if ($auctionItemRow = getNextRow($resultSet)) {
				$auctionItemRow['seller_information'] = self::getSellerInformation($auctionItemId);
				$auctionItemImages = array();
				$auctionItemImageRecords = explode('||||||', $auctionItemRow['auction_item_images']);
				foreach ($auctionItemImageRecords as $thisRecord) {
					$parts = explode("|||", $thisRecord);
					if (empty($parts[0]) || empty($parts[1])) {
						continue;
					}
					$thisItemImage = array("image_id" => $parts[0], "description" => $parts[1], "image_url" => getImageFilename($parts[0]), "image_urls" => array());
					foreach ($GLOBALS['gImageTypes'] as $imageTypeRow) {
						$imageTypeCode = strtolower($imageTypeRow['image_type_code']);
						$thisItemImage["image_urls"][$imageTypeCode] = getImageFilename($parts[0], array("image_type_code" => $imageTypeCode));
					}
					$auctionItemImages[] = $thisItemImage;
				}
				$auctionItemRow['auction_item_images'] = $auctionItemImages;
				$auctionItemSpecifications = array();
				$auctionItemSpecificationRecords = explode('||||||', $auctionItemRow['auction_item_specifications']);
				foreach ($auctionItemSpecificationRecords as $thisRecord) {
					$parts = explode("|||", $thisRecord);
					if (empty($parts[0]) || empty($parts[1])) {
						continue;
					}
					$auctionItemSpecifications[] = array("auction_specification_id" => $parts[0], "field_value" => $parts[1]);
				}
				$auctionItemRow['auction_item_specifications'] = $auctionItemSpecifications;
				$GLOBALS['gAuctionItemRows'][$auctionItemId] = $auctionItemRow;
			}
		}
		$auctionItemRow = $GLOBALS['gAuctionItemRows'][$auctionItemId];
		if (empty($auctionItemRow)) {
			return false;
		}
		return $auctionItemRow;
	}

	public static function getSellerInformation($auctionItemId) {
		$userId = getFieldFromId("user_id","auction_items","auction_item_id",$auctionItemId);
		$sellerInformationRow['seller_username'] = getFieldFromId("user_name", "users", "user_id", $userId);

		# Use Business name if exists for seller information
		if (!empty(getPreference("USE_FFL_INFORMATION_FOR_AUCTION"))) {
			$resultSet = executeQuery("select contact_id,business_name from contacts where contact_id in (select contact_id from federal_firearms_licensees where federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id = ?))", $userId);
			$row = getNextRow($resultSet);
			$sellerInformationRow['seller_address_block'] = getAddressBlock(Contact::getContact($row['contact_id']));
			$sellerInformationRow['seller_phone_number'] = Contact::getContactPhoneNumber($row['contact_id'], 'Store', true);
			if (!empty($sellerRow['business_name'])) {
				$sellerInformationRow['seller_username'] = $row['business_name'];
			}
		}
		return (empty($sellerInformationRow) ? false : $sellerInformationRow);
	}
	public function getErrorMessage() {
		return $this->iErrorMessage;
	}

	public function getBidStatistics($auctionItemId) {
		return $this->iBidStatistics;
	}

	function setSearchText($searchText) {
		$this->iSearchText = $searchText;
	}

	function getSearchText() {
		return $this->iSearchText;
	}

	function getDisplaySearchText() {
		return $this->iDisplaySearchText;
	}

	function getResultCount() {
		return $this->iResultCount;
	}

	function getQueryTime() {
		return $this->iQueryTime;
	}

	function setOffset($offset) {
		if (!is_numeric($offset) || $offset < 0) {
			$offset = 0;
		}
		$this->iOffset = $offset;
	}

	function getOffset() {
		return $this->iOffset;
	}

	function setSelectLimit($selectLimit) {
		if (!is_numeric($selectLimit) || $selectLimit <= 0) {
			$selectLimit = 20;
		}
		$this->iSelectLimit = $selectLimit;
	}

	function getSelectLimit() {
		return $this->iSelectLimit;
	}

	function setLimitQuery($limitQuery) {
		$this->iLimitQuery = $limitQuery;
	}

	function setDepartments($departmentIds) {
		if (!is_array($departmentIds)) {
			$departmentIds = array($departmentIds);
		}
		foreach ($departmentIds as $departmentId) {
			$departmentId = getFieldFromId("product_department_id", "product_departments", "product_department_id", $departmentId, "inactive = 0");
			if (!empty($departmentId) && !in_array($departmentId, $this->iDepartmentIds)) {
				$this->iDepartmentIds[] = $departmentId;
			}
		}
	}

	function setCategories($categoryIds) {
		if (!is_array($categoryIds)) {
			$categoryIds = array($categoryIds);
		}
		if (empty($this->iAllCategoryIds)) {
			$resultSet = executeReadQuery("select * from product_categories where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$this->iAllCategoryIds[$row['product_category_id']] = $row['product_category_id'];
			}
			freeReadResult($resultSet);
		}
		foreach ($categoryIds as $categoryId) {
			if (!empty($categoryId) && array_key_exists($categoryId, $this->iAllCategoryIds) && !in_array($categoryId, $this->iCategoryIds)) {
				$this->iCategoryIds[] = $categoryId;
			}
		}
	}

	function setCategoryGroups($categoryGroupIds) {
		if (!is_array($categoryGroupIds)) {
			$categoryGroupIds = array($categoryGroupIds);
		}
		$this->iCategoryIds[] = "0";
		foreach ($categoryGroupIds as $categoryGroupId) {
			$resultSet = executeReadQuery("select * from product_categories where product_category_id in (select product_category_id from product_category_group_links where product_category_group_id = ?) and client_id = ? and inactive = 0", $categoryGroupId, $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (!in_array($row['product_category_id'], $this->iCategoryIds)) {
					$this->iCategoryIds[] = $row['product_category_id'];
				}
			}
			freeReadResult($resultSet);
		}
	}

	function setTags($tagIds) {
		if (!is_array($tagIds)) {
			$tagIds = array($tagIds);
		}
		foreach ($tagIds as $tagId) {
			$tagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_id", $tagId, "inactive = 0");
			if (!empty($tagId) && !in_array($tagId, $this->iTagIds)) {
				$this->iTagIds[] = $tagId;
			}
		}
	}

	function setExcludeCategories($categoryIds) {
		if (!is_array($categoryIds)) {
			$categoryIds = array($categoryIds);
		}
		if (empty($this->iAllCategoryIds)) {
			$resultSet = executeReadQuery("select * from product_categories where client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$this->iAllCategoryIds[$row['product_category_id']] = $row['product_category_id'];
			}
			freeReadResult($resultSet);
		}
		foreach ($categoryIds as $categoryId) {
			if (!empty($categoryId) && array_key_exists($categoryId, $this->iAllCategoryIds) && !in_array($categoryId, $this->iCategoryIds)) {
				$this->iExcludeCategories[] = $categoryId;
			}
		}
	}

	function setExcludeDepartments($departmentIds) {
		if (!is_array($departmentIds)) {
			$departmentIds = array($departmentIds);
		}
		foreach ($departmentIds as $departmentId) {
			$departmentId = getFieldFromId("product_department_id", "product_departments", "product_department_id", $departmentId);
			if (!empty($departmentId) && !in_array($departmentId, $this->iExcludeDepartments)) {
				$this->iExcludeDepartments[] = $departmentId;
			}
		}
	}

	function setExcludeManufacturers($manufacturerIds) {
		if (!is_array($manufacturerIds)) {
			$manufacturerIds = array($manufacturerIds);
		}
		foreach ($manufacturerIds as $manufacturerId) {
			$manufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $manufacturerId);
			if (!empty($manufacturerId) && !in_array($manufacturerId, $this->iExcludeManufacturers)) {
				$this->iExcludeManufacturers[] = $manufacturerId;
			}
		}
	}

	function getQueryString() {
		return $this->iQuery;
	}

	function getAuctionItems() {
		$totalTime = 0;
		$startTime = getMilliseconds();
		$this->iQueryTime = "";
		$originalSearchTerm = $this->iSearchText;
		if ($GLOBALS['gSearchTermSynonyms'] === false) {
			ProductCatalog::getSearchTermSynonyms();
		}
		$searchTermSynonymRow = $GLOBALS['gSearchTermSynonyms'][strtoupper($this->iSearchText)];
		if (!empty($searchTermSynonymRow)) {
			if (empty($this->iCategoryIds)) {
				foreach ($searchTermSynonymRow['product_category_ids'] as $productCategoryId) {
					$this->iCategoryIds[] = $productCategoryId;
				}
			}
			if (empty($this->iDepartmentIds)) {
				foreach ($searchTermSynonymRow['product_department_ids'] as $productDepartmentId) {
					$this->iDepartmentIds[] = $productDepartmentId;
				}
			}
			if (empty($this->iManufacturerIds)) {
				foreach ($searchTermSynonymRow['product_manufacturer_ids'] as $productManufacturerId) {
					$this->iManufacturerIds[] = $productManufacturerId;
				}
			}
			$this->iSearchText = $searchTermSynonymRow['search_term'];
		}

		if (!empty($this->iDepartmentIds)) {
			$departmentCategoryIds = array();
			$resultSet = executeQuery("select product_category_id from product_categories where inactive = 0 and product_category_id in (select product_category_id from product_category_departments where " .
				"product_department_id in (" . implode(",", $this->iDepartmentIds) . ") union select product_category_id from product_category_group_links where product_category_group_id in " .
				"(select product_category_group_id from product_category_group_departments where product_department_id in (" . implode(",", $this->iDepartmentIds) . ")))");
			while ($row = getNextRow($resultSet)) {
				$departmentCategoryIds[] = $row['product_category_id'];
			}
			if (empty($this->iCategoryIds)) {
				$this->iCategoryIds = $departmentCategoryIds;
			} else {
				$this->iCategoryIds = array_unique(array_intersect($this->iCategoryIds, $departmentCategoryIds));
			}
		}
		if (!empty($this->iExcludeDepartments)) {
			$resultSet = executeQuery("select product_category_id from product_categories where inactive = 0 and product_category_id in (select product_category_id from product_category_departments where " .
				"product_department_id in (" . implode(",", $this->iExcludeDepartments) . ") union select product_category_id from product_category_group_links where product_category_group_id in " .
				"(select product_category_group_id from product_category_group_departments where product_department_id in (" . implode(",", $this->iExcludeDepartments) . ")))");
			while ($row = getNextRow($resultSet)) {
				$this->iExcludeCategories[] = $row['product_category_id'];
			}
		}
		$this->iExcludeCategories = array_unique($this->iExcludeCategories);
		$this->iCategoryIds = array_unique($this->iCategoryIds);

		$searchWordInfo = ProductCatalog::getSearchWords($this->iSearchText);

		$this->iDisplaySearchText = $searchWordInfo['display_search_text'];
		$searchWords = $searchWordInfo['search_words'];

		$endTime = getMilliseconds();
		$this->iQueryTime .= "query prep: " . round(($endTime - $startTime) / 1000, 2) . "\n";
		$totalTime += ($endTime - $startTime);
		$startTime = getMilliseconds();

		$query = "select auction_item_id,description,link_name,start_time,end_time,starting_bid,buy_now_price,can_offer,quantity,published,deleted," .
			"(select count(*) from auction_item_bids where auction_item_id = auction_items.auction_item_id) bid_count," .
			"(IFNULL(reserve_price,'No Reserve')) reserve_price," .
			"(IFNULL((select max(amount) from auction_item_bids where auction_item_id = auction_items.auction_item_id and inactive = 0),auction_items.starting_bid)) current_bid," .
			"(select user_id from auction_item_bids where auction_item_id = auction_items.auction_item_id and inactive = 0 and amount = (select max(amount) from auction_item_bids where auction_item_id = auction_items.auction_item_id and inactive = 0) order by log_time desc limit 1) as high_bid_user_id," .
			"(select image_id from auction_item_images where auction_item_id = auction_items.auction_item_id order by sequence_number limit 1) as auction_item_image," .
			"(select field_value from auction_item_specifications where auction_item_id = auction_items.auction_item_id and auction_specification_id in (select auction_specification_id from auction_specifications where auction_specification_code = 'CONDITION')) as auction_specification_condition," .
			"(select group_concat(concat_ws('|||',auction_specification_id,field_value,md5(field_value)) SEPARATOR '||||||') from auction_item_specifications where auction_item_id = auction_items.auction_item_id) as auction_item_specifications," .
			"(select product_category_id from auction_item_product_category_links where auction_item_id = auction_items.auction_item_id order by sequence_number limit 1) product_category_id," .
			"(SELECT group_concat(product_category_id) FROM auction_item_product_category_links WHERE auction_item_id = auction_items.auction_item_id order by sequence_number) product_category_ids," .
			"(SELECT group_concat(product_tag_id) FROM auction_item_product_tag_links WHERE auction_item_id = auction_items.auction_item_id) product_tag_ids";
		$parameters = array();
		if (empty($searchWords)) {
			$query .= ",0 as relevance";
		} else {
			$query .= ",(select sum(search_value) from auction_item_search_word_values where auction_item_id = auction_items.auction_item_id and " .
				"product_search_word_id in (select product_search_word_id from product_search_words where search_term in (" . implode(",", array_fill(0, count($searchWords), "?")) . "))) as relevance";
			$parameters = array_merge($parameters, $searchWords);
		}
		$query .= " from auction_items where ";
		$whereStatement = "auction_items.client_id = ? and published = 1 and start_time <= now() and end_time >= now()";
		$parameters[] = $GLOBALS['gClientId'];
		$whereStatement .= " and auction_items.deleted = 0";
		$excludedProductCategories = array();
		$resultSet = executeReadQuery("select product_category_id from product_categories where client_id = ? and (cannot_sell = 1" .
			(!$GLOBALS['gInternalConnection'] && !$this->iAllowInternalUseOnly ? " or product_category_code = 'INTERNAL_USE_ONLY'" : "") . ")", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$excludedProductCategories[] = $row['product_category_id'];
		}
		freeReadResult($resultSet);
		if (!empty($excludedProductCategories)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "auction_items.auction_item_id not in (select auction_item_id from auction_item_product_category_links where product_category_id in (" . implode(",", $excludedProductCategories) . "))";
		}

		$excludedProductTags = array();
		$resultSet = executeReadQuery("select product_tag_id from product_tags where client_id = ? and (cannot_sell = 1" .
			($GLOBALS['gLoggedIn'] ? (empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or product_tag_id in (select product_tag_id from user_type_product_tag_restrictions where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") : " or requires_user = 1") . ")", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$excludedProductTags[] = $row['product_tag_id'];
		}
		freeReadResult($resultSet);
		if (!empty($excludedProductTags)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "auction_items.auction_item_id not in (select auction_item_id from auction_item_product_tag_links where product_tag_id in (" . implode(",", $excludedProductTags) . "))";
		}

		$inactiveCategory = getFieldFromId("product_category_id", "product_categories", "product_category_code", "INACTIVE");
		if (!empty($inactiveCategory)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "auction_items.auction_item_id not in (select auction_item_id from auction_item_product_category_links where product_category_id = " . $inactiveCategory . ")";
		}
		$discontinuedCategory = getFieldFromId("product_category_id", "product_categories", "product_category_code", "DISCONTINUED");
		if (!empty($discontinuedCategory)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "auction_items.auction_item_id not in (select auction_item_id from auction_item_product_category_links where product_category_id = " . $discontinuedCategory . ")";
		}
		$notSearchableCategory = getFieldFromId("product_category_id", "product_categories", "product_category_code", "NOT_SEARCHABLE");
		if (!empty($notSearchableCategory)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "auction_items.auction_item_id not in (select auction_item_id from auction_item_product_category_links where product_category_id = " . $notSearchableCategory . ")";
		}
		if (!empty($this->iCategoryIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "exists (select auction_item_id from auction_item_product_category_links where auction_item_id = auction_items.auction_item_id and product_category_id in (" . implode(",", $this->iCategoryIds) . "))";
		}
		if (!empty($this->iExcludeCategories)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "auction_items.auction_item_id not in (select auction_item_id from auction_item_product_category_links where product_category_id in (" . implode(",", $this->iExcludeCategories) . "))";
		}

		if (!empty($searchWords)) {
			$searchWords = array_unique($searchWords);
			foreach ($searchWords as $thisWord) {
				$thisWordArray = array($thisWord);
				if (is_numeric($thisWord) && strlen($thisWord) >= 8) {
					$thisWordArray[] = ProductCatalog::makeValidUPC($thisWord);
					$thisWordArray[] = ProductCatalog::makeValidISBN($thisWord);
					$thisWordArray[] = ProductCatalog::makeValidISBN13($thisWord);
				}
				$thisWordArray = array_unique($thisWordArray);
				$whereStatement .= (empty($whereStatement) ? "" : " and ") .
					"auction_item_id in (select auction_item_id from auction_item_search_word_values where product_search_word_id in " .
					"(select product_search_word_id from product_search_words where client_id = ? and search_term in (" . implode(",", array_fill(0, count($thisWordArray), "?")) . ")))";
				$parameters[] = $GLOBALS['gClientId'];
				$parameters = array_merge($parameters, $thisWordArray);
			}
		}
		if (!empty($this->iTagIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "auction_items.auction_item_id in (select auction_item_id from auction_item_product_tag_links " .
				"where product_tag_id in (" . implode(",", $this->iTagIds) . "))";
		}
		if (!empty($this->iSpecificAuctionItemIds)) {
			$auctionItemIdList = "";
			foreach ($this->iSpecificAuctionItemIds as $thisAuctionItemId) {
				if (!empty($thisAuctionItemId) && is_numeric($thisAuctionItemId)) {
					$auctionItemIdList .= (empty($auctionItemIdList) ? "" : ",") . $auctionItemIdList;
				}
			}
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "auction_items.auction_item_id in (" . $auctionItemIdList . ")";
		}
		$query .= $whereStatement;
		if ($this->iLimitQuery) {
			$query .= " limit " . $this->iSelectLimit;
		}
		$this->iQuery = $query;
		foreach ($parameters as $fieldName => $fieldValue) {
			$this->iQuery .= ", " . $fieldName . " = " . $fieldValue;
		}

		$urlAliasTypeCode = getUrlAliasTypeCode("auction_items", "auction_item_id", "id");
		$resultSet = executeReadQuery($query, $parameters);
		$auctionItemArray = array();
		$resultCount = 0;
		$this->iResultCount = $resultSet['row_count'];
		$endTime = getMilliseconds();
		$this->iQueryTime .= "query run: " . round(($endTime - $startTime) / 1000, 2) . "\n";
		$totalTime += ($endTime - $startTime);
		$startTime = getMilliseconds();

		while ($row = getNextRow($resultSet)) {
			$resultCount++;
			$this->iAuctionItemIds[] = $row['auction_item_id'];
			if (count($auctionItemArray) >= $this->iSelectLimit) {
				continue;
			}
			if ($resultCount <= $this->iOffset) {
				continue;
			}
			$row['auction_item_detail_link'] = "/" . (empty($urlAliasTypeCode) || empty($row['link_name']) ? "auction-item-details?id=" . $row['auction_item_id'] : $urlAliasTypeCode . "/" . $row['link_name']);

			$auctionItemImages = array();
			$auctionItemImageRecords = explode('||||||', $row['auction_item_images']);
			foreach ($auctionItemImageRecords as $thisRecord) {
				$parts = explode("|||", $thisRecord);
				if (empty($parts[0]) || empty($parts[1])) {
					continue;
				}
				$imageFilename = getImageFilename($parts[0]);
				$auctionItemImages[] = array("image_id" => $parts[0], "description" => $parts[1], "image_filename" => $imageFilename);
			}
			$row['auction_item_images'] = $auctionItemImages;
			$auctionItemSpecifications = array();
			$auctionItemSpecificationRecords = explode('||||||', $row['auction_item_specifications']);
			foreach ($auctionItemSpecificationRecords as $thisRecord) {
				$parts = explode("|||", $thisRecord);
				if (empty($parts[0]) || empty($parts[1])) {
					continue;
				}
				$auctionItemSpecifications[] = array("auction_specification_id" => $parts[0], "field_value" => $parts[1], "hash" => $parts[2]);
			}
			$row['auction_item_specifications'] = $auctionItemSpecifications;

			$auctionItemArray[] = $row;
		}
		freeReadResult($resultSet);
		$endTime = getMilliseconds();
		$this->iQueryTime .= "post query: " . round(($endTime - $startTime) / 1000, 2) . "\n";
		$totalTime += ($endTime - $startTime);
		$startTime = getMilliseconds();
		$this->iAuctionItemResults = $auctionItemArray;

		if (!$this->iDontLogSearchTerm && !empty($originalSearchTerm)) {
			$searchTermParameters = array();
			if (!empty($this->iDepartmentIds)) {
				sort($this->iDepartmentIds);
				$searchTermParameters['product_department_ids'] = $this->iDepartmentIds;
			}
			if (!empty($this->iCategoryIds)) {
				sort($this->iCategoryIds);
				$searchTermParameters['product_category_ids'] = $this->iCategoryIds;
			}
			if (!empty($this->iManufacturerIds)) {
				sort($this->iManufacturerIds);
				$searchTermParameters['product_manufacturer_ids'] = $this->iManufacturerIds;
			}
			ProductCatalog::addSearchTerm($originalSearchTerm, $searchTermParameters, count($auctionItemArray));
		}

		return $auctionItemArray;
	}

	function getConstraints() {
		$startTime = getMilliseconds();
		$constraintArrays = array();
		$auctionItemIds = $this->iAuctionItemIds;

		if (count($auctionItemIds) > 0) {

# Get Product Tags

			$productTagIds = array();
			foreach ($this->iAuctionItemResults as $thisResult) {
				$thisProductTagIds = explode(",", $thisResult['product_tag_ids']);
				foreach ($thisProductTagIds as $thisProductTagId) {
					if (!empty($thisProductTagId) && is_numeric($thisProductTagId)) {
						if (!array_key_exists($thisProductTagId, $productTagIds)) {
							$productTagIds[$thisProductTagId] = array("product_tag_id" => $thisProductTagId, "count" => 0);
						}
						$productTagIds[$thisProductTagId]['count']++;
					}
				}
			}
			if (empty($productTagIds)) {
				$productTagIds[0] = array("count" => 0);
			}
			$constraintArrays['product_tags'] = array();
			$resultSet = executeReadQuery("select * from product_tags where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") .
				" and product_tag_id in (" . implode(",", array_keys($productTagIds)) . ") order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$constraintArrays['product_tags'][$row['product_tag_id']] = array();
				$constraintArrays['product_tags'][$row['product_tag_id']]['count'] = $productTagIds[$row['product_tag_id']]['count'];
				$constraintArrays['product_tags'][$row['product_tag_id']]['description'] = $row['description'];
				$constraintArrays['product_tags'][$row['product_tag_id']]['product_tag_id'] = $row['product_tag_id'];
			}
			$constraintArrays['product_tags'] = array_values($constraintArrays['product_tags']);

			$endTime = getMilliseconds();
			$this->iQueryTime .= "product tags: " . round(($endTime - $startTime) / 1000, 2) . "\n";
			$startTime = getMilliseconds();

# Get Category Constraints

			$productCategoryIds = array();
			foreach ($this->iAuctionItemResults as $thisResult) {
				$thisProductCategoryIds = explode(",", $thisResult['product_category_ids']);
				foreach ($thisProductCategoryIds as $thisProductCategoryId) {
					if (!empty($thisProductCategoryId) && is_numeric($thisProductCategoryId)) {
						if (!array_key_exists($thisProductCategoryId, $productCategoryIds)) {
							$productCategoryIds[$thisProductCategoryId] = array("product_category_id" => $thisProductCategoryId, "count" => 0);
						}
						$productCategoryIds[$thisProductCategoryId]['count']++;
					}
				}
			}
			if (empty($productCategoryIds)) {
				$productCategoryIds[0] = array("count" => 0);
			}
			$constraintArrays['categories'] = array();
			$resultSet = executeQuery("select * from product_categories where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] || $this->iAllowInternalUseOnly ? "" : " and internal_use_only = 0") .
				" and product_category_id in (" . implode(",", array_keys($productCategoryIds)) . ") order by sort_order,description", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$constraintArrays['categories'][$row['product_category_id']] = array();
				$constraintArrays['categories'][$row['product_category_id']]['product_count'] = 1;
				$constraintArrays['categories'][$row['product_category_id']]['description'] = $row['description'];
				$constraintArrays['categories'][$row['product_category_id']]['product_category_id'] = $row['product_category_id'];
			}
			$constraintArrays['categories'] = array_values($constraintArrays['categories']);

			$endTime = getMilliseconds();
			$this->iQueryTime .= "categories constraints: " . round(($endTime - $startTime) / 1000, 2) . "\n";
			$startTime = getMilliseconds();

# Get Specifications

			$constraintArrays['specifications'] = array();
			$specifications = array();
			$resultSet = executeReadQuery("select * from auction_specifications where " . ($GLOBALS['gInternalConnection'] ? "" : "internal_use_only = 0 and ") . "inactive = 0 and client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$specifications[$row['auction_specification_id']] = $row;
			}
			$specificationConstraints = array();
			foreach ($this->iAuctionItemResults as $thisResult) {
				foreach ($thisResult['auction_item_specifications'] as $thisSpecification) {
					if (empty($thisSpecification['field_value']) || !array_key_exists($thisSpecification['auction_specification_id'], $specifications)) {
						continue;
					}
					$auctionSpecificationId = $thisSpecification['auction_specification_id'];
					if (!array_key_exists($auctionSpecificationId, $specificationConstraints)) {
						$specificationConstraints[$auctionSpecificationId] = array("auction_specification_id" => $auctionSpecificationId, "field_values" => array(), "description" => $specifications[$auctionSpecificationId]['description']);
					}
					$specificationHash = $thisSpecification['hash'];
					if (!array_key_exists($specificationHash, $specificationConstraints[$auctionSpecificationId]['field_values'])) {
						$specificationConstraints[$auctionSpecificationId]['field_values'][$specificationHash] = array("hash" => $specificationHash, "field_value" => $thisSpecification['field_value'], "count" => 0);
					}
					$specificationConstraints[$auctionSpecificationId]['field_values'][$specificationHash]['count']++;
				}
			}
			foreach ($specificationConstraints as $thisSpecification) {
				ksort($thisSpecification['field_values']);
			}
			$constraintArrays['specifications'] = $specificationConstraints;

			$endTime = getMilliseconds();
			$this->iQueryTime .= "specification constraints: " . round(($endTime - $startTime) / 1000, 2) . "\n";
			$startTime = getMilliseconds();

		}
		return $constraintArrays;
	}

	/**
	 * Get the highest current active bid for an item. This method will also look at maximum bids and add bids as necessary. If User A has the
	 * current highest bid at, say, $50, but user B has a maximum bid set to $100, then a bid will be created for user B of $50 plus the bid increment. Then,
	 * if user C has a maximum bid of $110, it will continue this process until the current maximum bid is such that no other user would outbid it
	 * based on their maximum bid. If two users have the same maximum bid, the one who first put that maximum bid in the system will have the winning bid.
	 */
	function getCurrentBid($auctionItemId) {
		$auctionItemRow = self::getCachedAuctionItemRow($auctionItemId);
		if (empty($auctionItemRow)) {
			return false;
		}
		if (empty($auctionItemRow['bid_increment'])) {
			$auctionItemRow['bid_increment'] = 1;
		}
		if (empty($auctionItemRow['starting_bid'])) {
			$auctionItemRow['starting_bid'] = $auctionItemRow['bid_increment'];
		}
		$resultSet = executeQuery("select * from auction_item_bids where auction_item_id = ? and inactive = 0 order by amount desc",$auctionItemId);
		$currentBidRow = getNextRow($resultSet);
		$maximumBids = array();

		# only check user maximum bids if the auction is still active.
		if ($auctionItemRow['end_time'] > date("Y-m-d H:i:s")) {
			$resultSet = executeQuery("select * from user_maximum_bids where inactive = 0" . (empty($currentBidRow['amount']) ? "" : " and maximum_amount > " . $currentBidRow['amount']) . " and auction_item_id = ? order by log_time", $auctionItemId);
			while ($row = getNextRow($resultSet)) {
				$maximumBids[] = $row;
			}
		}
		do {
			$foundBid = false;
			foreach ($maximumBids as $thisMaximumBid) {
				if ($thisMaximumBid['user_id'] == $currentBidRow['user_id']) {
					continue;
				}
				$nextBid = empty($currentBidRow['amount']) ? $auctionItemRow['starting_bid'] : $currentBidRow['amount'] + $auctionItemRow['bid_increment'];

				if ($thisMaximumBid['maximum_amount'] < $nextBid) {
					continue;
				}
				$foundBid = true;
				executeQuery("update auction_item_bids set inactive = 1 where auction_item_id = ? and user_id = ?",$auctionItemId, $thisMaximumBid['user_id']);
				$insertSet = executeQuery("insert into auction_item_bids (auction_item_id, user_id, amount, log_time) values (?,?,?,now())",
					$auctionItemId, $thisMaximumBid['user_id'], $nextBid);
				$currentBidRow = getRowFromId("auction_item_bids", "auction_item_bid_id", $insertSet['insert_id']);
			}
		} while ($foundBid);

		$resultSet = executeQuery("select count(*) total_bid_count from auction_item_bids where auction_item_id = ? group by auction_item_id", $auctionItemId);
		if ($row = getNextRow($resultSet)) {
			$currentBidRow['total_bid_count'] = $row['total_bid_count'];
		}
		return $currentBidRow;
	}

	function getMinimumBid($auctionItemId) {
		$auctionItemRow = self::getCachedAuctionItemRow($auctionItemId);
		if (empty($auctionItemRow)) {
			return false;
		}
		if (empty($auctionItemRow['bid_increment'])) {
			$auctionItemRow['bid_increment'] = 1;
		}
		if (empty($auctionItemRow['starting_bid'])) {
			$auctionItemRow['starting_bid'] = $auctionItemRow['bid_increment'];
		}
		$currentBid = $this->getCurrentBid($auctionItemId);
		$currentBidAmount = $currentBid['amount'];
		$minimumBid = (empty($currentBidAmount) ? $auctionItemRow['starting_bid'] : $currentBidAmount + $auctionItemRow['bid_increment']);
		return $minimumBid;
	}

	/**
	 * Set the status of an auction item. This can include sending an email.
	 */
	function setAuctionItemStatus($auctionItemId, $auctionItemStatusId) {
		$auctionItemStatusRow = getRowFromId("auction_item_statuses", "auction_item_status_id", $auctionItemStatusId);
		if (empty($auctionItemStatusRow)) {
			return false;
		}
		$auctionItemRow = self::getCachedAuctionItemRow($auctionItemId);
		if ($auctionItemStatusRow['mark_completed'] && empty($auctionItemRow['date_completed'])) {
			$dataTable = new DataTable("auction_items");
			$dataTable->saveRecord(array("name_values" => array("date_completed" => date("Y-m-d")), "primary_id" => $auctionItemId));
		}
		$currentBidRow = $this->getCurrentBid($auctionItemId);

		$insertSet = executeQuery("insert ignore into auction_item_status_links (auction_item_id, auction_item_status_id) values (?,?)", $auctionItemId, $auctionItemStatusId);
		if ($insertSet['affected_rows'] > 0) {
			if (!empty($auctionItemStatusRow['email_id'])) {
				$substitutions = $auctionItemRow;
				$substitutions['current_bid'] = $currentBidRow['amount'];
				$resultSet = executeQuery("select * from contacts where contact_id = (select contact_id from users where user_id = ?)", $auctionItemRow['user_id']);
				$sellerUserRow = getNextRow($resultSet);
				foreach ($sellerUserRow as $fieldName => $fieldValue) {
					$substitutions['seller.' . $fieldName] = $fieldValue;
				}
				$resultSet = executeQuery("select * from contacts where contact_id = (select contact_id from users where user_id = ?)", $currentBidRow['user_id']);
				$buyerUserRow = getNextRow($resultSet);
				foreach ($buyerUserRow as $fieldName => $fieldValue) {
					$substitutions['buyer.' . $fieldName] = $fieldValue;
				}
				if (!empty($buyerUserRow['email_address'])) {
					sendEmail(array("email_id" => $auctionItemStatusRow['email_id'], "substitutions" => $substitutions, "email_address" => $buyerUserRow['email_address'], "contact_id" => $buyerUserRow['contact_id']));
				}
			}
			return true;
		}
		return false;
	}

	function setAuctionItemStatusCode($auctionItemId, $auctionItemStatusCode) {
		$auctionItemStatusId = getFieldFromId("auction_item_status_id", "auction_item_statuses", "auction_item_status_code", $auctionItemStatusCode);
		return $this->setAuctionItemStatus($auctionItemId, $auctionItemStatusId);
	}

	/**
	 * Create a purchase for an auction item
	 */
	function createAuctionItemPurchase($auctionItemId, $winningBidRow = array()) {
		$auctionItemRow = self::getCachedAuctionItemRow($auctionItemId);
		if (empty($auctionItemRow)) {
			return false;
		}
		if (empty($winningBidRow)) {
			$winningBidRow = $this->getCurrentBid($auctionItemId);
			if (empty($winningBidRow)) {
				return false;
			}
		}
		if (!empty($auctionItemRow['reserve_price']) && $winningBidRow['amount'] < $auctionItemRow['reserve_price']) {
			return false;
		}
		$auctionItemPurchaseId = getFieldFromId("auction_item_purchase_id", "auction_item_purchases", "auction_item_id", $auctionItemId, "inactive = 0");
		if (!empty($auctionItemPurchaseId)) {
			return true;
		}
		executeQuery("insert into auction_item_purchases (auction_item_id,user_id,log_time,quantity,price) values (?,?,now(),?,?)",
			$auctionItemId, $winningBidRow['user_id'], $auctionItemRow['quantity'], $winningBidRow['amount']);
		return true;
	}

	function relistAuctionItem($auctionItemId) {
		$auctionItemRow = self::getCachedAuctionItemRow($auctionItemId);
		if (empty($auctionItemRow)) {
			return false;
		}
		$newEndTime = strtotime($auctionItemRow['end_time']) + (strtotime($auctionItemRow['end_time']) - strtotime($auctionItemRow['start_time']));
		$dataTable = new DataTable("auction_items");
		$dataTable->saveRecord(array("name_values" => array("start_time" => date("Y-m-d H:i:s", strtotime($auctionItemRow['end_time'])), "end_time" => date("Y-m-d H:i:s", $newEndTime)), "primary_id" => $auctionItemId));
		return true;
	}

	/**
	 * Purchase an item at the buy now price
	 */
	function buyNow($auctionItemId, $userId = false) {
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		} else {
			$userId = getFieldFromId("user_id", "users", "user_id", $userId);
		}
		$auctionItemRow = self::getCachedAuctionItemRow($auctionItemId, "date_completed is null and deleted = 0 and end_time > now()");
		if (empty($auctionItemRow) || empty($userId)) {
			$this->iErrorMessage = (empty($auctionItemRow) ? "This auction is no longer available." : "You've been logged out. Please log back in.") ;
			return false;
		}
		if (empty($auctionItemRow['buy_now_price'])) {
			$this->iErrorMessage = "There is no buy now price for this item";
			return false;
		}
		$dataTable = new DataTable("auction_items");
		$dataTable->setSaveOnlyPresent(true);

		# End the auction and make sure reserve price is at or below the buy now price
		$endTime = date("Y-m-d H:i:s");
		$nameValues = array("end_time" => $endTime);
		if (!empty($auctionItemRow['reserve_price']) && $auctionItemRow['reserve_price'] > $auctionItemRow['buy_now_price']) {
			$nameValues['reserve_price'] = $auctionItemRow['buy_now_price'];
		}
		$dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $auctionItemRow['auction_item_id']));

		# Make all other bids inactive and add create a bid for the buy now price
		executeQuery("update auction_item_bids set inactive = 1 where auction_item_id = ?", $auctionItemRow['auction_item_id']);
		executeQuery("insert into auction_item_bids (auction_item_id, user_id, amount, log_time) values (?,?,?,?)",
			$auctionItemRow['auction_item_id'], $userId, $auctionItemRow['buy_now_price'], $endTime);
		return true;
	}

	/**
	 * Create an offer on an auction item. If the offer is at or above the auto accept price, the item will be marked as sold. If the offer is at or below the auto reject price,
	 * the offer will automatically be rejected.
	 */
	function createOffer($auctionItemId, $amount, $userId = false) {
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		} else {
			$userId = getFieldFromId("user_id", "users", "user_id", $userId);
		}
		$auctionItemRow = self::getCachedAuctionItemRow($auctionItemId, "date_completed is null and deleted = 0 and end_time > now()");
		if (empty($auctionItemRow) || empty($userId) || !is_numeric($amount) || $amount < 0) {
			$this->iErrorMessage = (empty($auctionItemRow) ? "This auction is no longer available." : (empty($userId) ? "You've been logged out. Please log back in." : "Invalid offer amount."));
			return false;
		}
		$auctionItemOfferId = getFieldFromId("auction_item_offer_id", "auction_item_offers", "auction_item_id", $auctionItemId, "user_id = ?", $userId);
		if (!empty($auctionItemOfferId)) {
			$this->iErrorMessage = "Only one offer is allowed per auction item.";
			return false;
		}
		$insertSet = executeQuery("insert into auction_item_offers (auction_item_id,user_id,time_submitted,amount) values (?,?,now(),?)", $auctionItemId, $userId, $amount);
		$auctionItemOfferId = $insertSet['insert_id'];
		if (!empty($auctionItemRow['auto_reject_price']) && $amount <= $auctionItemRow['auto_reject_price']) {
			$this->markOfferRejected($auctionItemOfferId);
		} elseif (!empty($auctionItemRow['auto_accept_price']) && $amount >= $auctionItemRow['auto_accept_price']) {
			$this->markOfferAccepted($auctionItemOfferId);
		}
		return true;
	}

	/**
	 * Set the status of the offer, which can include a notification. If the status code is either ACCEPTED or REJECTED, process the offer and the auction item.
	 */
	function setOfferStatus($auctionItemOfferId, $auctionItemOfferStatusId) {
		$auctionItemOfferStatusRow = getRowFromId("auction_item_offer_statuses", "auction_item_offer_status_id", $auctionItemOfferStatusId);
		if (empty($auctionItemOfferStatusRow)) {
			$this->iErrorMessage = "Unable to set offer status.";
			return false;
		}
		$auctionItemOfferRow = getRowFromId("auction_item_offers", "auction_item_offer_id", $auctionItemOfferId, "inactive = 0");
		$auctionItemRow = self::getCachedAuctionItemRow($auctionItemOfferRow['auction_item_id']);
		if (empty($auctionItemOfferRow) || empty($auctionItemRow) || $auctionItemOfferRow['auction_item_offer_status_id'] == $auctionItemOfferStatusId) {
			$this->iErrorMessage = "Unable to set offer status.";
			return false;
		}

		switch ($auctionItemOfferStatusRow['auction_item_offer_status_code']) {
			case "ACCEPTED":
				$resultSet = executeQuery("select * from auction_item_offers where auction_item_offer_id <> ? and auction_item_id = ? and inactive = 0", $auctionItemOfferId, $auctionItemRow['auction_item_id']);
				while ($row = getNextRow($resultSet)) {
					$this->setOfferStatusCode($row['auction_item_offer_id'], "REJECTED");
				}
				$dataTable = new DataTable("auction_item_offers");
				$dataTable->setSaveOnlyPresent(true);
				$nameValues = array("auction_item_offer_status_id" => $auctionItemOfferStatusId);
				$dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $auctionItemOfferRow['auction_item_offer_id']));

				$dataTable = new DataTable("auction_items");
				$dataTable->setSaveOnlyPresent(true);

				# End the auction and make sure reserve price is at or below the accepted offer
				$nameValues = array("end_time" => $auctionItemOfferRow['time_submitted']);
				if (!empty($auctionItemRow['reserve_price']) && $auctionItemRow['reserve_price'] > $auctionItemOfferRow['amount']) {
					$nameValues['reserve_price'] = $auctionItemOfferRow['amount'];
				}
				$dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $auctionItemRow['auction_item_id']));

				# Make all other bids inactive and add create a bid for the offer
				executeQuery("update auction_item_bids set inactive = 1 where auction_item_id = ?", $auctionItemRow['auction_item_id']);
				executeQuery("insert into auction_item_bids (auction_item_id, user_id, amount, log_time) values (?,?,?,?)",
					$auctionItemRow['auction_item_id'], $auctionItemOfferRow['user_id'], $auctionItemOfferRow['amount'], $auctionItemOfferRow['time_submitted']);
				break;
			case "CANCELLED":
			case "REJECTED":
				$existingAuctionItemOfferStatus = getFieldFromId("auction_item_offer_status_code", "auction_item_offer_statuses",
					"auction_item_offer_status_id", $auctionItemOfferRow['auction_item_offer_status_id']);
				if ($existingAuctionItemOfferStatus == "ACCEPTED") {
					$this->iErrorMessage = "Unable to set offer to " . strtolower($auctionItemOfferStatusRow['description']) . ". Offer already accepted.";
					return false;
				}
				if (!empty($auctionItemOfferRow['inactive'])) {
					return true;
				}
				$dataTable = new DataTable("auction_item_offers");
				$dataTable->setSaveOnlyPresent(true);
				$nameValues = array("auction_item_offer_status_id" => $auctionItemOfferStatusId, "inactive" => 1);
				$dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $auctionItemOfferRow['auction_item_offer_id']));
				break;
		}

		if (!empty($auctionItemOfferStatusRow['email_id'])) {
			$substitutions = array_merge($auctionItemRow, $auctionItemOfferRow);
			$resultSet = executeQuery("select * from contacts where contact_id = (select contact_id from users where user_id = ?)", $auctionItemRow['user_id']);
			$sellerUserRow = getNextRow($resultSet);
			foreach ($sellerUserRow as $fieldName => $fieldValue) {
				$substitutions['seller.' . $fieldName] = $fieldValue;
			}
			$resultSet = executeQuery("select * from contacts where contact_id = (select contact_id from users where user_id = ?)", $auctionItemOfferRow['user_id']);
			$buyerUserRow = getNextRow($resultSet);
			foreach ($buyerUserRow as $fieldName => $fieldValue) {
				$substitutions['buyer.' . $fieldName] = $fieldValue;
			}
			if (!empty($buyerUserRow['email_address'])) {
				sendEmail(array("email_id" => $auctionItemOfferStatusRow['email_id'], "substitutions" => $substitutions, "email_address" => $buyerUserRow['email_address'], "contact_id" => $buyerUserRow['contact_id']));
			}
		}
		return true;
	}

	function setOfferStatusCode($auctionItemOfferId, $auctionItemOfferStatusCode) {
		$auctionItemOfferStatusId = getFieldFromId("auction_item_offer_status_id", "auction_item_offer_statuses", "auction_item_offer_status_code", $auctionItemOfferStatusCode, "inactive = 0");
		return $this->setOfferStatus($auctionItemOfferId, $auctionItemOfferStatusId);
	}

	function markOfferAccepted($auctionItemOfferId) {
		return $this->setOfferStatusCode($auctionItemOfferId, "ACCEPTED");
	}

	function markOfferRejected($auctionItemOfferId) {
		return $this->setOfferStatusCode($auctionItemOfferId, "REJECTED");
	}

	/**
	 * Create a maximum bid for an auction item for a user. By calling getCurrentBid at the end of the function
	 */
	function addMaximumBid($auctionItemId, $amount, $userId = false) {
		$auctionItemRow = self::getCachedAuctionItemRow($auctionItemId, "date_completed is null and deleted = 0 and end_time > now()");
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		} else {
			$userId = getFieldFromId("user_id", "users", "user_id", $userId);
		}
		if (empty($auctionItemRow) || empty($userId) || !is_numeric($amount) || $amount < 0) {
			return false;
		}
		if (!empty($auctionItemRow['bid_close_extension'])) {
			if ($auctionItemRow['end_time'] > date("Y-m-d H:i:s", strtotime("-" . $auctionItemRow['bid_close_extension'] . " minutes"))) {
				$dataTable = new DataTable("auction_items");
				$dataTable->setSaveOnlyPresent(true);
				$nameValues = array("end_time" => date("Y-m-d H:i:s", strtotime($auctionItemRow['end_time']) + (60 * $auctionItemRow['bid_close_extension'])));
				$dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $auctionItemRow['auction_item_id']));
			}
		}
		$userMaximumBidRow = getRowFromId("user_maximum_bids", "auction_item_id", $auctionItemRow['auction_item_id'], "user_id = ?", $userId);
		if (empty($userMaximumBidRow)) {
			executeQuery("insert into user_maximum_bids (auction_item_id, user_id, maximum_amount, log_time) values (?,?,?,now())",
				$auctionItemRow['auction_item_id'], $userId, $amount);
		} else {
			if ($amount != $userMaximumBidRow['maximum_amount']) {
				executeQuery("update user_maximum_bids set maximum_amount = ? where user_maximum_bid_id = ?", $amount, $userMaximumBidRow['user_maximum_bid_id']);
			}
		}
		$this->getCurrentBid($auctionItemId);
		return true;
	}

	function addBid($auctionItemId, $amount, $userId = false) {
		$auctionItemRow = self::getCachedAuctionItemRow($auctionItemId, "date_completed is null and deleted = 0 and end_time > now()");
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		} else {
			$userId = getFieldFromId("user_id", "users", "user_id", $userId);
		}
		if (empty($auctionItemRow) || empty($userId) || !is_numeric($amount) || $amount < 0) {
			return false;
		}
		if (!empty($auctionItemRow['bid_close_extension'])) {
			if ($auctionItemRow['end_time'] > date("Y-m-d H:i:s", strtotime("-" . $auctionItemRow['bid_close_extension'] . " minutes"))) {
				$dataTable = new DataTable("auction_items");
				$dataTable->setSaveOnlyPresent(true);
				$nameValues = array("end_time" => date("Y-m-d H:i:s", strtotime($auctionItemRow['end_time']) + (60 * $auctionItemRow['bid_close_extension'])));
				$dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $auctionItemRow['auction_item_id']));
			}
		}
		$auctionItemBidRow = getRowFromId("auction_item_bids", "auction_item_id", $auctionItemRow['auction_item_id'], "inactive = 0 and user_id = ?", $userId);
		if (empty($auctionItemBidRow)) {
			executeQuery("insert into auction_item_bids (auction_item_id, user_id, amount, log_time) values (?,?,?,now())",$auctionItemRow['auction_item_id'], $userId, $amount);
		} elseif ($amount > $auctionItemBidRow['amount']) {
			executeQuery("update auction_item_bids set inactive = 1 where auction_item_id = ? and user_id = ?",$auctionItemRow['auction_item_id'], $userId);
			executeQuery("insert into auction_item_bids (auction_item_id, user_id, amount, log_time) values (?,?,?,now())",$auctionItemRow['auction_item_id'], $userId, $amount);
		} else {
			return false;
		}
		$this->getCurrentBid($auctionItemId);
	}

	function getUsersCurrentBid($auctionItemId, $userId = "") {
		$auctionItemRow = self::getCachedAuctionItemRow($auctionItemId, "date_completed is null and deleted = 0 and end_time > now()");
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		} else {
			$userId = getFieldFromId("user_id", "users", "user_id", $userId);
		}
		if (empty($auctionItemRow) || empty($userId)) {
			return false;
		}
		$auctionItemBidRow = getRowFromId("auction_item_bids", "auction_item_id", $auctionItemRow['auction_item_id'], "inactive = 0 and user_id = ?", $userId);
		return (empty($auctionItemBidRow) ? false : $auctionItemBidRow);
	}

	function getUsersMaximumBid($auctionItemId, $userId = "") {
		$auctionItemRow = self::getCachedAuctionItemRow($auctionItemId, "date_completed is null and deleted = 0 and end_time > now()");
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		} else {
			$userId = getFieldFromId("user_id", "users", "user_id", $userId);
		}
		if (empty($auctionItemRow) || empty($userId)) {
			return false;
		}
		$userMaximumBidRow = getRowFromId("user_maximum_bids", "auction_item_id", $auctionItemRow['auction_item_id'], "user_id = ?", $userId);
		return (empty($userMaximumBidRow) ? false : $userMaximumBidRow);
	}

	/**
	 * Sends a notification email to user that he has been outbid
	 */
	function notifyUserOutbid($auctionItem) {
		$auctionItemId = $auctionItem['auction_item_id'];
		$resultSet = executeQuery("select * from auction_item_bids where auction_item_id = ? and inactive = 0 order by log_time desc, amount desc limit 2", $auctionItemId);
		$currentWinningBidRow = $this->getCurrentBid($auctionItemId);
		while ($row = getNextRow($resultSet)) {
			if ($row['auction_item_bid_id'] === $currentWinningBidRow['auction_item_bid_id']) {
				continue;
			}
			$userMaximumBid = getFieldFromId("maximum_amount", "user_maximum_bids", "user_id", $row['user_id'], "auction_item_id = ?", $auctionItemId);
			if ($userMaximumBid == $row['amount'] || $userMaximumBid == $currentWinningBidRow['amount']) {
				$resultSet = executeQuery("select * from contacts where contact_id = (select contact_id from users where user_id = ?)", $row['user_id']);
				$contactRow = getNextRow($resultSet);
				if (!empty($contactRow['email_address'])) {
					$linkName = getFieldFromId("link_name", "auction_items", "auction_item_id", $auctionItemId);
					$linkUrl = "https://" . $_SERVER['HTTP_HOST'] . "/" . (empty($linkName) ? "auction-item-details?id=" . $auctionItemId : "auction/" . $linkName);
					$substitutions = array("first_name" => $contactRow['first_name'], "description" => $auctionItem['description'], "auction_item_url" => $linkUrl);
					$emailId = getFieldFromId("email_id", "emails", "email_code", "AUCTION_ITEM_OUTBID", "inactive = 0");
					$auctionOutbidFragment = getFragment('AUCTION_ITEM_OUTBID');
					if (!empty($auctionOutbidFragment)) {
						sendEmail(array("subject" => "Gunstores Auction - You have been outbid!", "body" => $auctionOutbidFragment, "substitutions" => $substitutions, "email_address" => $contactRow['email_address'], "contact_id" => $contactRow['contact_id'],"send_immediately" => true));
					} else if (!empty($emailId)) {
						sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_address" => $contactRow['email_address'], "contact_id" => $contactRow['contact_id'],"send_immediately" => true));
					}
				}
			}
		}
	}

	/**
	 * Sends a notification email to seller that auction has ended
	 */
	function notifySeller($auctionItem,$notificationType=false) {
		$auctionItemId = $auctionItem['auction_item_id'];
		$resultSet = executeQuery("select * from contacts where contact_id = (select contact_id from users where user_id = ?)", $auctionItem['user_id']);
		$contactRow = getNextRow($resultSet);
		if (!empty($contactRow['email_address'])) {
			$linkName = getFieldFromId("link_name", "auction_items", "auction_item_id", $auctionItemId);
			$linkUrl = "https://" . $_SERVER['HTTP_HOST'] . "/" . (empty($linkName) ? "auction-item-details?id=" . $auctionItemId : "auction/" . $linkName);
			$substitutions = array("first_name" => $contactRow['first_name'], "description" => $auctionItem['description'], "auction_item_url" => $linkUrl);
            if ($notificationType == 'sold') {
	            $notificationCode = "AUCTION_ITEM_SOLD";
                $notificationSubject = "Gunstores Auction - Your auction item has sold!";
            } else {
	            $notificationCode = "AUCTION_ITEM_ENDED";
	            $notificationSubject = "Gunstores Auction - Your auction item has ended! Relist Now!";
            }
			$emailId = getFieldFromId("email_id", "emails", "email_code", $notificationCode, "inactive = 0");
			$auctionNotificationFragment = getFragment($notificationCode);
			if (!empty($auctionNotificationFragment)) {
				sendEmail(array("subject" => $notificationSubject, "body" => $auctionNotificationFragment, "substitutions" => $substitutions, "email_address" => $contactRow['email_address'], "contact_id" => $contactRow['contact_id']));
			} else if (!empty($emailId)) {
				sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_address" => $contactRow['email_address'], "contact_id" => $contactRow['contact_id']));
			}
		}
	}

	function getBidsInformation($auctionItemId) {
		$resultSet = executeQuery("select * from auction_item_bids where auction_item_id = ? order by amount desc, log_time", $auctionItemId);

		$bids = array();
		$winningUserId = null;
		$winningBidId = null;
		$userHasBid = "";
		$isWinning = "";
		while ($row = getNextRow($resultSet)) {
			$bids[] = $row;
			if ($row['user_id'] == $GLOBALS['gUserId']) {
				$userHasBid = true;
			}
		}

		$totalBids = count($bids);

		if (!empty($bids)) {
			$winningUserId = $bids[0]['user_id'];
			$winningBidId = $bids[0]['auction_item_id'];
		}

		if ($winningUserId == $GLOBALS['gUserId']) {
			$isWinning = true;
		}

		return array('auction_item_bids' => $bids, 'winning_bid_id' => $winningBidId, 'is_winning' => $isWinning,
			'winning_user_id' => $winningUserId,'total_bid_count' => $totalBids, 'user_has_bid' => $userHasBid);
	}
}
