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

$GLOBALS['gPageCode'] = "AUCTIONCONTROLLER";
$GLOBALS['gCacheProhibited'] = true;
require_once "shared/startup.inc";

class AuctionControllerPage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "make_offer":
				$auctionObject = new Auction();

                $auctionItemId = getFieldFromId("auction_item_id", "auction_items", "auction_item_id", $_POST['auction_item_id']);
                $auctionItem = getRowFromId("auction_items", "auction_item_id", $auctionItemId);
                if ($auctionItem['user_id'] == $GLOBALS['gUserId']) {
                    $returnArray['error_message'] = "You cannot make an offer for your own listing.";
                    ajaxResponse($returnArray);
                    break;
                }

				if ($auctionObject->createOffer($_POST['auction_item_id'], $_POST['amount'])) {
					$returnArray['info_message'] = "Your offer has been received and will be considered";
				} else {
					$returnArray['error_message'] = $auctionObject->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;

			case "get_full_auction_details":
				$auctionItemId = getFieldFromId("auction_item_id", "auction_items", "auction_item_id", $_GET['auction_item_id'],
					"published = 1 and (end_time is null or end_time > current_date)");
				if (empty($auctionItemId)) {
					echo jsonEncode(array("error_message" => "Item Not Found"));
					exit;
				}
				$returnArray['auction_item_id'] = $auctionItemId;
				$auctionItemDetails = new AuctionItemDetails($auctionItemId);
				$returnArray['content'] = $auctionItemDetails->getFullPage();
				$linkName = getFieldFromId("link_name", "auction_items", "auction_item_id", $auctionItemId);
				$returnArray['link_url'] = "https://" . $_SERVER['HTTP_HOST'] . "/" . (empty($linkName) ? "auction-item-details?id=" . $auctionItemId : "auction/" . $linkName) . "#auction_item_detail";
				ajaxResponse($returnArray);
				exit;

			case "get_auction_item_search_results":
				if (is_array($_SESSION['auction_item_search_results_array'])) {
					$returnArray['auction_item_search_results'] = $_SESSION['auction_item_search_results_array'][$_GET['results_key']];
					unset($_SESSION['auction_item_search_results_array'][$_GET['results_key']]);
					unset($_SESSION['auction_item_search_results_timestamp'][$_GET['results_key']]);
				}
				if (is_array($_SESSION['auction_item_search_results_timestamp'])) {
					foreach ($_SESSION['auction_item_search_results_timestamp'] as $resultsKey => $timestamp) {
						if (($timestamp + 60) < time()) {
							unset($_SESSION['auction_item_search_results_array'][$resultsKey]);
							unset($_SESSION['auction_item_search_results_timestamp'][$resultsKey]);
						}
					}
				}
				saveSessionData();
				ajaxResponse($returnArray);
				exit;

			case "get_auction_items":
				if (!empty($_POST['no_cache'])) {
					$_GET['no_cache'] = true;
				}
				if ($_GET['url_source'] == "tagged_products" && empty($_POST['product_tag_code'])) {
					ajaxResponse($returnArray);
					break;
				}
				$cacheKey = md5(jsonEncode($_GET) . "-" . jsonEncode($_POST));
				if (empty($_GET['no_cache'])) {
					$cachedResponse = getCachedData("get_products_response", $cacheKey);
					if (!empty($cachedResponse) && is_array($cachedResponse)) {
						ajaxResponse($cachedResponse);
						break;
					}
				}

				if (!empty($_GET['related_product_id'])) {
					$productIds = explode(",", $_GET['related_product_id']);
					$productIdString = "";
					foreach ($productIds as $productId) {
						if (!empty($productId) && is_numeric($productId)) {
							$productIdString .= (empty($productIdString) ? "" : ",") . $productId;
						}
					}
					if (empty($productIdString)) {
						ajaxResponse($returnArray);
						break;
					} else {
						$resultSet = executeQuery("select count(*) from related_products where product_id in (" . $productIdString . ")");
						if ($row = getNextRow($resultSet)) {
							if ($row['count(*)'] == 0) {
								ajaxResponse($returnArray);
								break;
							}
						}
					}
				}
				$productFieldNames = array();
				$productResults = array();
				$resultCount = 0;
				$inventoryCounts = array();
				$productCodeArray = array();
				$productCatalog = new Auction();

				$_POST = array_merge($_POST, $_GET);
				if (empty($_POST['select_limit'])) {
					$_POST['select_limit'] = 100000;
				}
				if (!empty($_POST)) {
					if (array_key_exists("search_text", $_POST)) {
						$productCatalog->setSearchText($_POST['search_text']);
					}
					$productCatalog->showOutOfStock(empty($_POST['exclude_out_of_stock']));
					$productCatalog->needSidebarInfo(false);
					$productCatalog->setSelectLimit($_POST['select_limit']);
					$productCatalog->setGetProductSalePrice(true);
					$productCatalog->setIgnoreManufacturerLogo(true);
					$productCatalog->setBaseImageFilenameOnly(true);
					$missingProductImage = getImageFilenameFromCode("NO_PRODUCT_IMAGE");
					if (empty($missingProductImage) || $missingProductImage == "/images/empty.jpg") {
						$missingProductImage = getPreference("DEFAULT_PRODUCT_IMAGE");
					}
					if (empty($missingProductImage)) {
						$missingProductImage = "/images/empty.jpg";
					}
					$productCatalog->setDefaultImage($missingProductImage);

					if (array_key_exists("sort_by", $_POST)) {
						$productCatalog->setSortBy($_POST['sort_by']);
					}
					if (array_key_exists("ignore_products_without_image", $_POST)) {
						$productCatalog->ignoreProductsWithoutImages($_POST['ignore_products_without_image']);
					}
					if (array_key_exists("specific_product_ids", $_POST)) {
						$productCatalog->setSpecificProductIds($_POST['specific_product_ids']);
					}
					$relatedProductIds = explode(",", $_POST['related_product_id']);
					foreach ($relatedProductIds as $relatedProductId) {
						$productCatalog->setRelatedProduct($relatedProductId);
					}
					if (array_key_exists("related_product_type_code", $_POST)) {
						$productCatalog->setRelatedProductTypeCode($_POST['related_product_type_code']);
					}
					$productDepartmentIds = array();
					if (array_key_exists("product_department_ids", $_POST) && !empty($_POST['product_department_ids'])) {
						if (!is_array($_POST['product_department_ids'])) {
							$_POST['product_department_ids'] = explode("|", $_POST['product_department_ids']);
						}
						foreach ($_POST['product_department_ids'] as $productDepartmentId) {
							$productDepartmentId = getFieldFromId("product_department_id", "product_departments", "product_department_id", $productDepartmentId);
							if (!empty($productDepartmentId) && !in_array($productDepartmentId, $productDepartmentIds)) {
								$productDepartmentIds[] = $productDepartmentId;
							}
						}
					}
					if (array_key_exists("product_department_codes", $_POST) && !empty($_POST['product_department_codes'])) {
						if (!is_array($_POST['product_department_codes'])) {
							$_POST['product_department_codes'] = explode("|", $_POST['product_department_codes']);
						}
						foreach ($_POST['product_department_codes'] as $productDepartmentCode) {
							$productDepartmentId = getFieldFromId("product_department_id", "product_departments", "product_department_code", $productDepartmentCode);
							if (!empty($productDepartmentId) && !in_array($productDepartmentId, $productDepartmentIds)) {
								$productDepartmentIds[] = $productDepartmentId;
							}
						}
					}
					if (array_key_exists("product_department_id", $_POST) && !empty($_POST['product_department_id'])) {
						if (!is_array($_POST['product_department_id'])) {
							$_POST['product_department_id'] = explode("|", $_POST['product_department_id']);
						}
						foreach ($_POST['product_department_id'] as $productDepartmentId) {
							$productDepartmentId = getFieldFromId("product_department_id", "product_departments", "product_department_id", $productDepartmentId);
							if (!empty($productDepartmentId) && !in_array($productDepartmentId, $productDepartmentIds)) {
								$productDepartmentIds[] = $productDepartmentId;
							}
						}
					}
					if (array_key_exists("product_department_code", $_POST) && !empty($_POST['product_department_code'])) {
						if (!is_array($_POST['product_department_code'])) {
							$_POST['product_department_code'] = explode("|", $_POST['product_department_code']);
						}
						foreach ($_POST['product_department_code'] as $productDepartmentCode) {
							$productDepartmentId = getFieldFromId("product_department_id", "product_departments", "product_department_code", $productDepartmentCode);
							if (!empty($productDepartmentId) && !in_array($productDepartmentId, $productDepartmentIds)) {
								$productDepartmentIds[] = $productDepartmentId;
							}
						}
					}
					if (!empty($productDepartmentIds)) {
						$productCatalog->setDepartments($productDepartmentIds);
					}

					$productCategoryIds = array();
					if (array_key_exists("product_category_ids", $_POST) && !empty($_POST['product_category_ids'])) {
						if (!is_array($_POST['product_category_ids'])) {
							$_POST['product_category_ids'] = explode("|", $_POST['product_category_ids']);
						}
						foreach ($_POST['product_category_ids'] as $productCategoryId) {
							$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $productCategoryId);
							if (!empty($productCategoryId) && !in_array($productCategoryId, $productCategoryIds)) {
								$productCategoryIds[] = $productCategoryId;
							}
						}
					}
					if (array_key_exists("product_category_codes", $_POST) && !empty($_POST['product_category_codes'])) {
						if (!is_array($_POST['product_category_codes'])) {
							$_POST['product_category_codes'] = explode("|", $_POST['product_category_codes']);
						}
						foreach ($_POST['product_category_codes'] as $productCategoryCode) {
							$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_code", $productCategoryCode);
							if (!empty($productCategoryId) && !in_array($productCategoryId, $productCategoryIds)) {
								$productCategoryIds[] = $productCategoryId;
							}
						}
					}
					if (array_key_exists("product_category_id", $_POST) && !empty($_POST['product_category_id'])) {
						if (!is_array($_POST['product_category_id'])) {
							$_POST['product_category_id'] = explode("|", $_POST['product_category_id']);
						}
						foreach ($_POST['product_category_id'] as $productCategoryId) {
							$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $productCategoryId);
							if (!empty($productCategoryId) && !in_array($productCategoryId, $productCategoryIds)) {
								$productCategoryIds[] = $productCategoryId;
							}
						}
					}
					if (array_key_exists("product_category_code", $_POST) && !empty($_POST['product_category_code'])) {
						if (!is_array($_POST['product_category_code'])) {
							$_POST['product_category_code'] = explode("|", $_POST['product_category_code']);
						}
						foreach ($_POST['product_category_code'] as $productCategoryCode) {
							$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_code", $productCategoryCode);
							if (!empty($productCategoryId) && !in_array($productCategoryId, $productCategoryIds)) {
								$productCategoryIds[] = $productCategoryId;
							}
						}
					}
					if (!empty($productCategoryIds)) {
						$productCatalog->setCategories($productCategoryIds);
					}

					$productCategoryGroupIds = array();
					if (array_key_exists("product_category_group_ids", $_POST) && !empty($_POST['product_category_group_ids'])) {
						if (!is_array($_POST['product_category_group_ids'])) {
							$_POST['product_category_group_ids'] = explode("|", $_POST['product_category_group_ids']);
						}
						foreach ($_POST['product_category_group_ids'] as $productCategoryGroupId) {
							$productCategoryGroupId = getFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_id", $productCategoryGroupId);
							if (!empty($productCategoryGroupId) && !in_array($productCategoryGroupId, $productCategoryGroupIds)) {
								$productCategoryGroupIds[] = $productCategoryGroupId;
							}
						}
					}
					if (array_key_exists("product_category_group_codes", $_POST) && !empty($_POST['product_category_group_codes'])) {
						if (!is_array($_POST['product_category_group_codes'])) {
							$_POST['product_category_group_codes'] = explode("|", $_POST['product_category_group_codes']);
						}
						foreach ($_POST['product_category_group_codes'] as $productCategoryCode) {
							$productCategoryGroupId = getFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_code", $productCategoryCode);
							if (!empty($productCategoryGroupId) && !in_array($productCategoryGroupId, $productCategoryGroupIds)) {
								$productCategoryGroupIds[] = $productCategoryGroupId;
							}
						}
					}
					if (array_key_exists("product_category_group_id", $_POST) && !empty($_POST['product_category_group_id'])) {
						if (!is_array($_POST['product_category_group_id'])) {
							$_POST['product_category_group_id'] = explode("|", $_POST['product_category_group_id']);
						}
						foreach ($_POST['product_category_group_id'] as $productCategoryGroupId) {
							$productCategoryGroupId = getFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_id", $productCategoryGroupId);
							if (!empty($productCategoryGroupId) && !in_array($productCategoryGroupId, $productCategoryGroupIds)) {
								$productCategoryGroupIds[] = $productCategoryGroupId;
							}
						}
					}
					if (array_key_exists("product_category_group_code", $_POST) && !empty($_POST['product_category_group_code'])) {
						if (!is_array($_POST['product_category_group_code'])) {
							$_POST['product_category_group_code'] = explode("|", $_POST['product_category_group_code']);
						}
						foreach ($_POST['product_category_group_code'] as $productCategoryCode) {
							$productCategoryGroupId = getFieldFromId("product_category_group_id", "product_category_groups", "product_category_group_code", $productCategoryCode);
							if (!empty($productCategoryGroupId) && !in_array($productCategoryGroupId, $productCategoryGroupIds)) {
								$productCategoryGroupIds[] = $productCategoryGroupId;
							}
						}
					}

					if (!empty($productCategoryGroupIds)) {
						$productCatalog->setCategoryGroups($productCategoryGroupIds);
					}

					$productManufacturerIds = array();
					if (array_key_exists("product_manufacturer_ids", $_POST) && !empty($_POST['product_manufacturer_ids'])) {
						if (!is_array($_POST['product_manufacturer_ids'])) {
							$_POST['product_manufacturer_ids'] = explode("|", $_POST['product_manufacturer_ids']);
						}
						foreach ($_POST['product_manufacturer_ids'] as $productManufacturerId) {
							$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $productManufacturerId);
							if (!empty($productManufacturerId) && !in_array($productManufacturerId, $productManufacturerIds)) {
								$productManufacturerIds[] = $productManufacturerId;
							}
						}
					}
					if (array_key_exists("product_manufacturer_codes", $_POST) && !empty($_POST['product_manufacturer_codes'])) {
						if (!is_array($_POST['product_manufacturer_codes'])) {
							$_POST['product_manufacturer_codes'] = explode("|", $_POST['product_manufacturer_codes']);
						}
						foreach ($_POST['product_manufacturer_codes'] as $productManufacturerCode) {
							$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_code", $productManufacturerCode);
							if (!empty($productManufacturerId) && !in_array($productManufacturerId, $productManufacturerIds)) {
								$productManufacturerIds[] = $productManufacturerId;
							}
						}
					}
					if (array_key_exists("product_manufacturer_id", $_POST) && !empty($_POST['product_manufacturer_id'])) {
						if (!is_array($_POST['product_manufacturer_id'])) {
							$_POST['product_manufacturer_id'] = explode("|", $_POST['product_manufacturer_id']);
						}
						foreach ($_POST['product_manufacturer_id'] as $productManufacturerId) {
							$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $productManufacturerId);
							if (!empty($productManufacturerId) && !in_array($productManufacturerId, $productManufacturerIds)) {
								$productManufacturerIds[] = $productManufacturerId;
							}
						}
					}
					if (array_key_exists("product_manufacturer_code", $_POST) && !empty($_POST['product_manufacturer_code'])) {
						if (!is_array($_POST['product_manufacturer_code'])) {
							$_POST['product_manufacturer_code'] = explode("|", $_POST['product_manufacturer_code']);
						}
						foreach ($_POST['product_manufacturer_code'] as $productManufacturerCode) {
							$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_code", $productManufacturerCode);
							if (!empty($productManufacturerId) && !in_array($productManufacturerId, $productManufacturerIds)) {
								$productManufacturerIds[] = $productManufacturerId;
							}
						}
					}
					if (!empty($productManufacturerIds)) {
						$productCatalog->setManufacturers($productManufacturerIds);
					}
					$locationIds = array();
					if (array_key_exists("location_ids", $_POST) && !empty($_POST['location_ids'])) {
						if (!is_array($_POST['location_ids'])) {
							$_POST['location_ids'] = explode("|", $_POST['location_ids']);
						}
						foreach ($_POST['location_ids'] as $locationId) {
							$locationId = getFieldFromId("location_id", "locations", "location_id", $locationId);
							if (!empty($locationId) && !in_array($locationId, $locationIds)) {
								$locationIds[] = $locationId;
							}
						}
					}
					if (array_key_exists("location_codes", $_POST) && !empty($_POST['location_codes'])) {
						if (!is_array($_POST['location_codes'])) {
							$_POST['location_codes'] = explode("|", $_POST['location_codes']);
						}
						foreach ($_POST['location_codes'] as $locationCode) {
							$locationId = getFieldFromId("location_id", "locations", "location_code", $locationCode);
							if (!empty($locationId) && !in_array($locationId, $locationIds)) {
								$locationIds[] = $locationId;
							}
						}
					}
					if (array_key_exists("location_id", $_POST) && !empty($_POST['location_id'])) {
						if (!is_array($_POST['location_id'])) {
							$_POST['location_id'] = explode("|", $_POST['location_id']);
						}
						foreach ($_POST['location_id'] as $locationId) {
							$locationId = getFieldFromId("location_id", "locations", "location_id", $locationId);
							if (!empty($locationId) && !in_array($locationId, $locationIds)) {
								$locationIds[] = $locationId;
							}
						}
					}
					if (array_key_exists("location_code", $_POST) && !empty($_POST['location_code'])) {
						if (!is_array($_POST['location_code'])) {
							$_POST['location_code'] = explode("|", $_POST['location_code']);
						}
						foreach ($_POST['location_code'] as $locationCode) {
							$locationId = getFieldFromId("location_id", "locations", "location_code", $locationCode);
							if (!empty($locationId) && !in_array($locationId, $locationIds)) {
								$locationIds[] = $locationId;
							}
						}
					}
					if (!empty($locationIds)) {
						$productCatalog->setLocations($locationIds);
					}
					$productTagIds = array();
					if (array_key_exists("product_tag_ids", $_POST) && !empty($_POST['product_tag_ids'])) {
						if (!is_array($_POST['product_tag_ids'])) {
							$_POST['product_tag_ids'] = explode("|", $_POST['product_tag_ids']);
						}
						foreach ($_POST['product_tag_ids'] as $productTagId) {
							$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_id", $productTagId);
							if (!empty($productTagId) && !in_array($productTagId, $productTagIds)) {
								$productTagIds[] = $productTagId;
							}
						}
					}
					if (array_key_exists("product_tag_codes", $_POST) && !empty($_POST['product_tag_codes'])) {
						if (!is_array($_POST['product_tag_codes'])) {
							$_POST['product_tag_codes'] = explode("|", $_POST['product_tag_codes']);
						}
						foreach ($_POST['product_tag_codes'] as $productTagCode) {
							$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", $productTagCode);
							if (!empty($productTagId) && !in_array($productTagId, $productTagIds)) {
								$productTagIds[] = $productTagId;
							}
						}
					}
					if (array_key_exists("product_tag_id", $_POST) && !empty($_POST['product_tag_id'])) {
						if (!is_array($_POST['product_tag_id'])) {
							$_POST['product_tag_id'] = explode("|", $_POST['product_tag_id']);
						}
						foreach ($_POST['product_tag_id'] as $productTagId) {
							$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_id", $productTagId);
							if (!empty($productTagId) && !in_array($productTagId, $productTagIds)) {
								$productTagIds[] = $productTagId;
							}
						}
					}
					if (array_key_exists("product_tag_code", $_POST) && !empty($_POST['product_tag_code'])) {
						if (!is_array($_POST['product_tag_code'])) {
							$_POST['product_tag_code'] = explode("|", $_POST['product_tag_code']);
						}
						foreach ($_POST['product_tag_code'] as $productTagCode) {
							$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", $productTagCode);
							if (!empty($productTagId) && !in_array($productTagId, $productTagIds)) {
								$productTagIds[] = $productTagId;
							}
						}
					}
					if (!empty($productTagIds)) {
						$productCatalog->setTags($productTagIds);
					}
					if (array_key_exists("include_product_tags_without_start_date", $_POST)) {
						$productCatalog->includeProductTagsWithNoStartDate($_POST['include_product_tags_without_start_date']);
					}
					$productFacetOptionIds = array();
					if (array_key_exists("product_facet_option_ids", $_POST) && !empty($_POST['product_facet_option_ids'])) {
						if (!is_array($_POST['product_facet_option_ids'])) {
							$_POST['product_facet_option_ids'] = explode("|", $_POST['product_facet_option_ids']);
						}
						foreach ($_POST['product_facet_option_ids'] as $productFacetOptionId) {
							$productFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_option_id", $productFacetOptionId);
							if (!empty($productFacetOptionId) && !in_array($productFacetOptionId, $productFacetOptionIds)) {
								$productFacetOptionIds[] = $productFacetOptionId;
							}
						}
					}
					if (array_key_exists("product_facet_option_id", $_POST) && !empty($_POST['product_facet_option_id'])) {
						if (!is_array($_POST['product_facet_option_id'])) {
							$_POST['product_facet_option_id'] = explode("|", $_POST['product_facet_option_id']);
						}
						foreach ($_POST['product_facet_option_id'] as $productFacetOptionId) {
							$productFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_option_id", $productFacetOptionId);
							if (!empty($productFacetOptionId) && !in_array($productFacetOptionId, $productFacetOptionIds)) {
								$productFacetOptionIds[] = $productFacetOptionId;
							}
						}
					}
					if (!empty($productFacetOptionIds)) {
						$productCatalog->setFacetOptions($productFacetOptionIds);
					}

					if (array_key_exists("states", $_POST) && !empty($_POST['states'])) {
						if (!is_array($_POST['states'])) {
							$_POST['states'] = explode("|", $_POST['states']);
						}
						$stateArray = getStateArray();
						foreach ($_POST['states'] as $thisState) {
							if (!empty($thisState) && array_key_exists($thisState, $stateArray)) {
								$productCatalog->addCompliantState($thisState);
							}
						}
					}

					$excludeIds = array();
					if (array_key_exists("exclude_product_category_ids", $_POST) && !empty($_POST['exclude_product_category_ids'])) {
						if (!is_array($_POST['exclude_product_category_ids'])) {
							$_POST['exclude_product_category_ids'] = explode("|", $_POST['exclude_product_category_ids']);
						}
						foreach ($_POST['exclude_product_category_ids'] as $excludeId) {
							$excludeId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $excludeId);
							if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
								$excludeIds[] = $excludeId;
							}
						}
					}
					if (array_key_exists("exclude_product_category_id", $_POST) && !empty($_POST['exclude_product_category_id'])) {
						if (!is_array($_POST['exclude_product_category_id'])) {
							$_POST['exclude_product_category_id'] = explode("|", $_POST['exclude_product_category_id']);
						}
						foreach ($_POST['exclude_product_category_id'] as $excludeId) {
							$excludeId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $excludeId);
							if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
								$excludeIds[] = $excludeId;
							}
						}
					}
					if (array_key_exists("exclude_internal_product_categories", $_POST) && $_POST['exclude_internal_product_categories']) {
						$resultSet = executeQuery("select * from product_categories where client_id = ? and internal_use_only = 1", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							if (!in_array($row['product_category_id'], $excludeIds)) {
								$excludeIds[] = $row['product_category_id'];
							}
						}
					}
					if (!empty($excludeIds)) {
						$productCatalog->setExcludeCategories($excludeIds);
					}
					$excludeIds = array();
					if (array_key_exists("exclude_product_department_ids", $_POST) && !empty($_POST['exclude_product_department_ids'])) {
						if (!is_array($_POST['exclude_product_department_ids'])) {
							$_POST['exclude_product_department_ids'] = explode("|", $_POST['exclude_product_department_ids']);
						}
						foreach ($_POST['exclude_product_department_ids'] as $excludeId) {
							$excludeId = getFieldFromId("product_department_id", "product_departments", "product_department_id", $excludeId);
							if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
								$excludeIds[] = $excludeId;
							}
						}
					}
					if (array_key_exists("exclude_product_department_id", $_POST) && !empty($_POST['exclude_product_department_id'])) {
						if (!is_array($_POST['exclude_product_department_id'])) {
							$_POST['exclude_product_department_id'] = explode("|", $_POST['exclude_product_department_id']);
						}
						foreach ($_POST['exclude_product_department_id'] as $excludeId) {
							$excludeId = getFieldFromId("product_department_id", "product_departments", "product_department_id", $excludeId);
							if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
								$excludeIds[] = $excludeId;
							}
						}
					}
					if (array_key_exists("exclude_internal_product_departments", $_POST) && $_POST['exclude_internal_product_departments']) {
						$resultSet = executeQuery("select * from product_departments where client_id = ? and internal_use_only = 1", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							if (!in_array($row['product_department_id'], $excludeIds)) {
								$excludeIds[] = $row['product_department_id'];
							}
						}
					}
					if (!empty($excludeIds)) {
						$productCatalog->setExcludeDepartments($excludeIds);
					}
					$excludeIds = array();
					if (array_key_exists("exclude_product_manufacturer_ids", $_POST) && !empty($_POST['exclude_product_manufacturer_ids'])) {
						if (!is_array($_POST['exclude_product_manufacturer_ids'])) {
							$_POST['exclude_product_manufacturer_ids'] = explode("|", $_POST['exclude_product_manufacturer_ids']);
						}
						foreach ($_POST['exclude_product_manufacturer_ids'] as $excludeId) {
							$excludeId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $excludeId);
							if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
								$excludeIds[] = $excludeId;
							}
						}
					}
					if (array_key_exists("exclude_product_manufacturer_id", $_POST) && !empty($_POST['exclude_product_manufacturer_id'])) {
						if (!is_array($_POST['exclude_product_manufacturer_id'])) {
							$_POST['exclude_product_manufacturer_id'] = explode("|", $_POST['exclude_product_manufacturer_id']);
						}
						foreach ($_POST['exclude_product_manufacturer_id'] as $excludeId) {
							$excludeId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $excludeId);
							if (!empty($excludeId) && !in_array($excludeId, $excludeIds)) {
								$excludeIds[] = $excludeId;
							}
						}
					}
					if (array_key_exists("exclude_internal_product_manufacturers", $_POST) && $_POST['exclude_internal_product_manufacturers']) {
						$resultSet = executeQuery("select * from product_manufacturers where client_id = ? and internal_use_only = 1", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							if (!in_array($row['product_manufacturer_id'], $excludeIds)) {
								$excludeIds[] = $row['product_manufacturer_id'];
							}
						}
					}
					if (!empty($excludeIds)) {
						$productCatalog->setExcludeManufacturers($excludeIds);
					}

					$cacheKey = $productCatalog->getCacheKey();
					$productResults = false;
					$queryTime = "";
					$startTime = getMilliseconds();
					$callForPriceText = $this->getFragment("CALL_FOR_PRICE");
					if (empty($callForPriceText)) {
						$callForPriceText = getLanguageText("Call for Price");
					}

					if (empty($_GET['no_cache'])) {
						$cachedResults = getCachedData("auction_item_search_results", $cacheKey);
						if (!empty($cachedResults)) {
							$cachedProductResults = $cachedResults['cached_product_results'];
							$cachedProductResultKeys = $cachedResults['cached_product_result_keys'];
							if (!empty($cachedProductResults) && !empty($cachedProductResultKeys)) {
								$productResults = array();
								foreach ($cachedProductResults as $thisResult) {
									$thisProductResult = array();
									foreach ($thisResult as $index => $thisFieldData) {
										$thisProductResult[$cachedProductResultKeys[$index]] = $thisFieldData;
									}
									$productResults[] = $thisProductResult;
								}
							}
							$constraints = $cachedResults['constraints'];
							$displaySearchText = $cachedResults['display_search_text'];
							if (empty($productResults) || empty($constraints)) {
								$productResults = false;
							} else {
								$cachedResultsUsed = true;
								$resultCount = $cachedResults['result_count'];
								$productIds = array();
								$cachedPrices = 0;
								$storedPrices = 0;
								$customProductSalePriceFunctionExists = false;
								if (function_exists("customProductSalePrice")) {
									$customProductSalePriceFunctionExists = true;
								}
								if (count($productResults) > 0) {
									if ($customProductSalePriceFunctionExists) {
										foreach ($productResults as $index => $thisProduct) {
											$productCodeArray[$thisProduct['product_code']] = $thisProduct['product_code'];
										}
									}
									$productSalePrices = array();
									$GLOBALS['gHideProductsWithNoPrice'] = getPreference("HIDE_PRODUCTS_NO_PRICE");
									$GLOBALS['gHideProductsWithZeroPrice'] = getPreference("HIDE_PRODUCTS_ZERO_PRICE");
									if ($customProductSalePriceFunctionExists) {
										/** @noinspection PhpUndefinedFunctionInspection */
										$productSalePrices = customProductSalePrice(array("product_code_array" => $productCodeArray));
										if (empty($productSalePrices)) {
											$productSalePrices = array();
										}
									}
									foreach ($productResults as $index => $thisProduct) {
										$productIds[] = $thisProduct['product_id'];
										$mapEnforced = false;
										$callPrice = false;
										if (array_key_exists($thisProduct['product_code'], $productSalePrices)) {
											$salePriceInfo = $productSalePrices[$thisProduct['product_code']];
											if (!is_array($salePriceInfo)) {
												$salePriceInfo = array("sale_price" => $salePriceInfo);
											}
										} else {
											$salePriceInfo = $productCatalog->getProductSalePrice($thisProduct['product_id'], array("product_information" => $thisProduct, "no_cache" => !empty($_GET['no_cache'])));
										}
										$originalSalePrice = $salePriceInfo['original_sale_price'];
										$salePrice = $salePriceInfo['sale_price'];
										if (!empty($originalSalePrice) && ($originalSalePrice < $salePrice || (!empty($thisProduct['manufacturer_advertised_price']) && $thisProduct['manufacturer_advertised_price'] > $originalSalePrice))) {
											$originalSalePrice = "";
										}
										$mapEnforced = $salePriceInfo['map_enforced'];
										$callPrice = $salePriceInfo['call_price'];
										if ($salePriceInfo['cached']) {
											$cachedPrices++;
										} else if ($salePriceInfo['stored']) {
											$storedPrices++;
										}
										if (!empty($originalSalePrice) && $originalSalePrice <= $salePrice) {
											$originalSalePrice = "";
										}
										if (empty($originalSalePrice) && !empty($thisProduct['list_price'])) {
											$originalSalePrice = $thisProduct['list_price'];
										}
										if (!empty($originalSalePrice) && $originalSalePrice <= $salePrice) {
											$originalSalePrice = "";
										}
										if (($salePrice === false && $GLOBALS['gHideProductsWithNoPrice']) || ($salePrice == 0 && $GLOBALS['gHideProductsWithZeroPrice'])) {
											$resultCount--;
											unset($productResults[$index]);
											continue;
										}
										if (!empty($_POST['minimum_price']) && ($salePrice === false || $salePrice < $_POST['minimum_price'])) {
											$resultCount--;
											unset($productResults[$index]);
											continue;
										}
										if (!empty($_POST['maximum_price']) && ($salePrice === false || $salePrice > $_POST['maximum_price'])) {
											$resultCount--;
											unset($productResults[$index]);
											continue;
										}
										$productResults[$index]['sale_price'] = ($salePrice === false || !is_numeric($salePrice) ? ($salePrice === false || is_numeric($salePrice) ? $callForPriceText : $salePrice) : number_format($salePrice, 2));
										$productResults[$index]['original_sale_price'] = (empty($originalSalePrice) ? "" : number_format($originalSalePrice, 2, ".", ","));
										$productResults[$index]['hide_dollar'] = $salePrice === false || !is_numeric($salePrice);
										$productResults[$index]['map_enforced'] = $mapEnforced;
										$productResults[$index]['call_price'] = $callPrice;
									}
								}
								$endTime = getMilliseconds();
								$queryTime .= "Get latest prices: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
								$queryTime .= "Cached Prices: " . $cachedPrices . "\n";
								$queryTime .= "Stored Prices: " . $storedPrices . "\n";
								$startTime = getMilliseconds();
								if (empty($neverOutOfStock)) {
									$inventoryCounts = $productCatalog->getInventoryCounts(true, $productIds);
									$endTime = getMilliseconds();
									$queryTime .= "Get inventory counts: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
									$startTime = getMilliseconds();
								} else {
									$inventoryCounts = array();
								}
							}
						}
					}

					if ($productResults === false) {
						$productResults = $productCatalog->getProducts();
						$endTime = getMilliseconds();
						$queryTime .= "Get Search Results: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
						$startTime = getMilliseconds();
						$displaySearchText = $productCatalog->getDisplaySearchText();
						$constraints = $productCatalog->getConstraints(false, false);
						$resultCount = $productCatalog->getResultCount();
						$cachedProductResults = array();
						$cachedProductResultKeys = false;
						foreach ($productResults as $index => $thisProduct) {
							if ($cachedProductResultKeys === false) {
								$cachedProductResultKeys = array_keys($thisProduct);
							}
							$cachedProductResults[$index] = array_values($thisProduct);
						}
						setCachedData("auction_item_search_results", $cacheKey, array("cached_product_results" => $cachedProductResults, "cached_product_result_keys" => $cachedProductResultKeys, "constraints" => $constraints, "result_count" => $resultCount, "display_search_text" => $displaySearchText), 18);
						$cachedProductResults = null;
						$cachedProductResultKeys = null;
						if (!empty($_POST['minimum_price']) || !empty($_POST['maximum_price'])) {
							foreach ($productResults as $index => $thisProduct) {
								if (!empty($_POST['minimum_price']) && ($thisProduct['sale_price'] === false || $thisProduct['sale_price'] < $_POST['minimum_price'])) {
									$resultCount--;
									unset($productResults[$index]);
									continue;
								}
								if (!empty($_POST['maximum_price']) && ($thisProduct['sale_price'] === false || $thisProduct['sale_price'] > $_POST['maximum_price'])) {
									$resultCount--;
									unset($productResults[$index]);
								}
							}
						}
						$queryTime .= $productCatalog->getQueryTime() . "\n";
						$inventoryCounts = $productCatalog->getInventoryCounts(true);
					}

//                    $showLocationAvailability = getPreference("RETAIL_STORE_SHOW_LOCATION_AVAILABILITY");
//                    if (!empty($showLocationAvailability)) {
//                        $productLocationAvailability = $productCatalog->getLocationAvailability();
//                        foreach ($productResults as $index => $thisProduct) {
//                            $productResults[$index]['location_availability'] = $productCatalog::getProductAvailabilityText($thisProduct, $productLocationAvailability);
//                        }
//                    }

					$resultCount = $productCatalog->getResultCount();
					$queryTime = $productCatalog->getQueryTime();
				}

				$contributorTypes = array();
				$catalogResultHtml = Auction::getAuctionResultHtml(true);
				if (strpos($catalogResultHtml, "contributor") !== false) {
					$resultSet = executeQuery("select * from contributor_types where client_id = ?", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$contributorTypes[] = $row;
					}
				}
				$callForPriceText = $this->getFragment("CALL_FOR_PRICE");
				if (empty($callForPriceText)) {
					$callForPriceText = getLanguageText("Call for Price");
				}

				$showInStoreOnlyPrice = getPreference("SHOW_IN_STORE_ONLY_PRICE");
				if (count($productResults) > 0) {
					foreach ($productResults as $index => $thisProduct) {
						$productIds[] = $thisProduct['product_id'];
						$productCodeArray[$thisProduct['product_code']] = $thisProduct['product_code'];
					}
					$productSalePrices = array();
					if (function_exists("customProductSalePrice")) {
						$productSalePrices = customProductSalePrice(array("product_code_array" => $productCodeArray));
						if (empty($productSalePrices)) {
							$productSalePrices = array();
						}
					}
					if (!empty($_POST['minimum_price']) && !is_numeric($_POST['minimum_price'])) {
						$_POST['minimum_price'] = "";
					}
					if (!empty($_POST['maximum_price']) && !is_numeric($_POST['maximum_price'])) {
						$_POST['maximum_price'] = "";
					}
					foreach ($productResults as $index => $thisProduct) {
						$neverOutOfStock = getPreference("RETAIL_STORE_NEVER_OUT_OF_STOCK");
						if (empty($neverOutOfStock)) {
							$quantity = $inventoryCounts[$thisProduct['product_id']];
							if (empty($quantity) || $quantity < 0) {
								$quantity = 0;
							}
						} else {
							$quantity = 1;
						}
						$productResults[$index]['inventory_quantity'] = $quantity;
						$productResults[$index]['auction_item_detail_link'] = (empty($thisProduct['link_name']) ? "auction-item-details?id=" . $thisProduct['auction_item_id'] : "/auction/" . $thisProduct['link_name']);

						$mapEnforced = false;
						$originalSalePrice = false;
						if (array_key_exists($thisProduct['product_code'], $productSalePrices)) {
							$salePriceInfo = $productSalePrices[$thisProduct['product_code']];
							if (!is_array($salePriceInfo)) {
								$salePriceInfo = array("sale_price" => $salePriceInfo);
							}
						} else {
							$salePriceInfo = $productCatalog->getProductSalePrice($thisProduct['product_id'], array("product_information" => $thisProduct));
						}
						$originalSalePrice = $salePriceInfo['original_sale_price'];
						$salePrice = $salePriceInfo['sale_price'];
						$mapEnforced = $salePriceInfo['map_enforced'];
						$callPrice = $salePriceInfo['call_price'];
						if (empty($originalSalePrice)) {
							$originalSalePrice = $thisProduct['list_price'];
						}
						if ($originalSalePrice <= $salePrice) {
							$originalSalePrice = "";
						}
						if (!empty($_POST['minimum_price']) && ($salePrice === false || $salePrice < $_POST['minimum_price'])) {
							unset($productResults[$index]);
							continue;
						}
						if (!empty($_POST['maximum_price']) && ($salePrice === false || $salePrice > $_POST['maximum_price'])) {
							unset($productResults[$index]);
							continue;
						}

						$productResults[$index]['sale_price'] = ($salePrice === false || ($thisProduct['no_online_order'] && empty($showInStoreOnlyPrice)) ? $callForPriceText : number_format($salePrice, 2));
						$productResults[$index]['original_sale_price'] = (empty($originalSalePrice) || !empty($thisProduct['manufacturer_advertised_price']) ? "" : number_format($originalSalePrice, 2));
						$productResults[$index]['hide_dollar'] = ($salePrice === false || ($thisProduct['no_online_order'] && empty($showInStoreOnlyPrice)));
						$productResults[$index]['map_enforced'] = $mapEnforced;
						$productResults[$index]['call_price'] = $callPrice;

						$productResults[$index]['product_format'] = (empty($thisProduct['product_format_id']) ? "" : getFieldFromId("description", "product_formats", "product_format_id", $thisProduct['product_format_id']));
						if (strpos($catalogResultHtml, "contributor") !== false) {
							$resultSet = executeQuery("select * from product_contributors join contributors using (contributor_id) join contributor_types using (contributor_type_id) where product_id = ?",
								$thisProduct['product_id']);
							while ($row = getNextRow($resultSet)) {
								$productResults[$index]['contributor:' . strtolower($row['contributor_type_code'])] = $row['full_name'];
							}
							foreach ($contributorTypes as $row) {
								if (!array_key_exists("contributor:" . strtolower($row['contributor_type_code']), $productResults[$index])) {
									$productResults[$index]['contributor:' . strtolower($row['contributor_type_code'])] = "";
								}
							}
						}
					}
					$resultCount = count($productResults);
					$necessaryFields = array("product_id", "product_code", "description", "product_manufacturer_id", "product_category_ids", "product_tag_ids", "product_facet_option_ids", "image_base_filename", "remote_image", "sale_price", "hide_dollar", "manufacturer_advertised_price", "inventory_quantity", "product_detail_link", "map_enforced", "call_price", "no_online_order");
					$removeFields = array();
					if (is_array($productResults[0])) {
						$allFields = array_keys($productResults[0]);
					} else {
						$allFields = array();
					}

					foreach ($allFields as $thisFieldName) {
						if (in_array($thisFieldName, $necessaryFields)) {
							continue;
						}
						if (strpos($catalogResultHtml, "%" . $thisFieldName . "%") === false) {
							$removeFields[$thisFieldName] = $thisFieldName;
						}
					}

					if (is_array($productResults[0])) {
						$productFieldNames = array_keys(array_diff_key($productResults[0], $removeFields));
					} else {
						$productFieldNames = array();
					}
					foreach ($productResults as $index => $result) {
						$productResults[$index] = array_values(array_diff_key($result, $removeFields));
					}
				}

				$mapPolicies = getCachedData("map_policies","",true);
				if (empty($mapPolicies)) {
					$mapPolicies = array();
					$resultSet = executeQuery("select * from map_policies");
					while ($row = getNextRow($resultSet)) {
						$mapPolicies[$row['map_policy_id']] = $row['map_policy_code'];
					}
					setCachedData("map_policies","",$mapPolicies,168,true);
				}
				$manufacturerNames = array();
				$resultSet = executeQuery("select product_manufacturer_id,description,map_policy_id," .
					"(select product_manufacturer_map_holiday_id from product_manufacturer_map_holidays where " .
					"product_manufacturer_id = product_manufacturers.product_manufacturer_id and start_date <= current_date and end_date >= current_date limit 1) map_holiday from " .
					"product_manufacturers where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$mapPolicyCode = $mapPolicies[$row['map_policy_id']];
					$manufacturerNames[$row['product_manufacturer_id']] = array($row['description'],($mapPolicyCode == "IGNORE" || !empty($row['map_holiday']) ? "1" : "0"),$mapPolicyCode);
				}

				$shoppingCartProductIds = array();
				$shoppingCart = ShoppingCart::getShoppingCart("RETAIL");
				$shoppingCartItems = $shoppingCart->getShoppingCartItems(array("reset_sale_price" => true));
				foreach ($shoppingCartItems as $thisItem) {
					$shoppingCartProductIds[] = $thisItem['product_id'];
				}
				$wishListProductIds = array();
				if ($GLOBALS['gLoggedIn']) {
					try {
						$wishList = new WishList();
						$wishListItems = $wishList->getWishListItems();
						foreach ($wishListItems as $thisItem) {
							$wishListProductIds[] = $thisItem['product_id'];
						}
					} catch (Exception $e) {
					}
				}
				if (!empty($_POST['reductive_field_list']) && !empty($_POST['force_temporary_table']) && !empty($temporaryTableName = $productCatalog->getTemporaryTableName())) {
					$reductiveData = array();
					$fieldList = explode(",", $_POST['reductive_field_list']);
					foreach ($fieldList as $thisFieldName) {
						switch ($thisFieldName) {
							case "product_manufacturers":
								$reductiveData[$thisFieldName] = array();
								$resultSet = executeQuery("select distinct product_manufacturer_id from products where product_id in (select product_id from " . $temporaryTableName . ")");
								while ($row = getNextRow($resultSet)) {
									$reductiveData[$thisFieldName][] = $row['product_manufacturer_id'];
								}
								break;
							case "product_categories":
								$reductiveData[$thisFieldName] = array();
								$resultSet = executeQuery("select distinct product_category_id from product_category_links where product_id in (select product_id from " . $temporaryTableName . ")");
								while ($row = getNextRow($resultSet)) {
									$reductiveData[$thisFieldName][] = $row['product_category_id'];
								}
								break;
							case "product_tags":
								$reductiveData[$thisFieldName] = array();
								$resultSet = executeQuery("select distinct product_tag_id from product_tag_links where (start_date is null or start_date <= current_date) and " .
									"(expiration_date is null or expiration_date > current_date) and product_id in (select product_id from " . $temporaryTableName . ")");
								while ($row = getNextRow($resultSet)) {
									$reductiveData[$thisFieldName][] = $row['product_tag_id'];
								}
								break;
							default:
								if (substr($thisFieldName, 0, strlen("product_facet_code-")) != "product_facet_code-") {
									break;
								}
								$facetCode = substr($thisFieldName, strlen("product_facet_code-"));
								$productFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_code", $facetCode, "inactive = 0 and internal_use_only = 0");
								if (empty($productFacetId)) {
									break;
								}
								$reductiveData[$thisFieldName] = array();
								$resultSet = executeQuery("select distinct product_facet_option_id from product_facet_values where product_facet_id = ? and product_id in (select product_id from " . $temporaryTableName . ")", $productFacetId);
								while ($row = getNextRow($resultSet)) {
									$reductiveData[$thisFieldName][] = $row['product_facet_option_id'];
								}
								break;
						}
					}
					$returnArray['reductive_data'] = $reductiveData;
				}
				$returnArray['result_count'] = $resultCount;
				if (empty($_POST['count_only'])) {
					$returnArray['product_field_names'] = $productFieldNames;
					$returnArray['product_results'] = $productResults;
					$returnArray['manufacturer_names'] = $manufacturerNames;
					$returnArray['shopping_cart_product_ids'] = $shoppingCartProductIds;
					$returnArray['wishlist_product_ids'] = $wishListProductIds;
					$returnArray['empty_image_filename'] = getImageFilenameFromCode("NO_PRODUCT_IMAGE");
					if (empty($returnArray['empty_image_filename']) || $returnArray['empty_image_filename'] == "/images/empty.jpg") {
						$returnArray['empty_image_filename'] = getPreference("DEFAULT_PRODUCT_IMAGE");
					}
				}
				setCachedData("get_products_response", $cacheKey, $returnArray, 2);

				if (!$GLOBALS['gLoggedIn']) {
					$postVariables = array_merge($GLOBALS['gOriginalGetVariables'], $GLOBALS['gOriginalPostVariables']);
					ksort($postVariables);
					unset($postVariables['_']);
					$urlCacheKey = md5($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] . json_encode($postVariables));
					setCachedData("request_search_result", $urlCacheKey, $returnArray, 2, true);
				}

				ajaxResponse($returnArray);
				break;

			case "get_auction_result_html":
			case "get_related_auction_result_html":
				$returnArray['auction_result_html'] = Auction::getAuctionResultHtml($_GET['force_tile'] == "true");
				ajaxResponse($returnArray);
				break;

			case "get_user_watchlists":
				$watchlists = array();
				$whereExpressions = array("user_watchlists.user_id = ?", "published = 1", "end_time > now()");
				$parameterValues = array($GLOBALS['gUserId']);

				if (!empty($_GET['search_text'])) {
					$whereExpressions[] = "(description like " . makeParameter("%" . $_GET['search_text'] . "%")
						. " or detailed_description like " . makeParameter("%" . $_GET['search_text'] . "%")
						. " or link_name like " . makeParameter("%" . $_GET['search_text'] . "%") . ")";
				}

				$resultSet = executeQuery("select * from user_watchlists join auction_items using (auction_item_id)"
					. " where " . implode(" and ", $whereExpressions), $parameterValues);

				while ($row = getNextRow($resultSet)) {
					$watchlist = array_merge($row, Auction::getCachedAuctionItemRow($row['auction_item_id']));
					$topBidResultSet = executeQuery("select max(amount) amount from auction_item_bids where auction_item_id = ? and inactive = 0", $row['auction_item_id']);
					if ($topBidRow = getNextRow($topBidResultSet)) {
						$watchlist['top_bid'] = $topBidRow['amount'];
					}
					$watchlists[] = $watchlist;
				}

				$returnArray['user_watchlists'] = $watchlists;
				ajaxResponse($returnArray);
				break;

			case "add_to_user_watchlist":
				if (empty($_POST['auction_item_id'])) {
					$returnArray['error_message'] = "Auction item is required.";
					ajaxResponse($returnArray);
					break;
				}

				if (!empty(getRowFromId("user_watchlists", "auction_item_id", $_POST['auction_item_id'], "user_id = ?", $GLOBALS['gUserId']))) {
					executeQuery("delete from user_watchlists where auction_item_id = ? and user_id = ?", $_POST['auction_item_id'], $GLOBALS['gUserId']);
					$returnArray['message'] = "User watchlist removed successfully.";
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("insert into user_watchlists (auction_item_id, user_id) values (?, ?)", $_POST['auction_item_id'], $GLOBALS['gUserId']);
				$returnArray['message'] = "User watchlist added successfully.";
				ajaxResponse($returnArray);
				break;

			case "remove_from_auction_watchlist":
				if (empty($_POST['auction_item_id'])) {
					$returnArray['error_message'] = "Auction item is required.";
					ajaxResponse($returnArray);
					break;
				}
				if (empty(getRowFromId("user_watchlists", "auction_item_id", $_POST['auction_item_id'], "user_id = ?", $GLOBALS['gUserId']))) {
					$returnArray['error_message'] = "User watchlist doesn't exist for auction item.";
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("delete from user_watchlists where auction_item_id = ? and user_id = ?", $_POST['auction_item_id'], $GLOBALS['gUserId']);
				$returnArray['message'] = "User watchlist removed successfully.";
				ajaxResponse($returnArray);
				break;

			case "place_auction_item_bid":
				$auctionObject = new Auction();

                $auctionItemId = $_POST['auction_item_id'];
                $auctionItem = getRowFromId("auction_items", "auction_item_id", $auctionItemId);
                if ($auctionItem['user_id'] == $GLOBALS['gUserId']) {
                    $returnArray['error_message'] = "You cannot place a bid for your own listing.";
                    ajaxResponse($returnArray);
                    break;
                }

				if ($auctionObject->addBid($_POST['auction_item_id'], $_POST['bid_amount'])) {
					$returnArray['info_message'] = "Bid successfully placed for $" . $_POST['bid_amount'];
				} else {
					$returnArray['error_message'] = $auctionObject->getErrorMessage();
				}
				$returnArray = array_merge($returnArray,$auctionObject->getBidStatistics($_POST['auction_item_id']));
				ajaxResponse($returnArray);
				break;

			case "place_auction_item_maximum_bid":
				$auctionObject = new Auction();

                $auctionItemId = $_POST['auction_item_id'];
                $auctionItem = getRowFromId("auction_items", "auction_item_id", $auctionItemId);
                if ($auctionItem['user_id'] == $GLOBALS['gUserId']) {
                    $returnArray['error_message'] = "You cannot place a bid for your own listing.";
                    ajaxResponse($returnArray);
                    break;
                }

				if ($auctionObject->addMaximumBid($auctionItemId, $_POST['bid_amount'])) {
					$returnArray['info_message'] = "Maximum bid successfully placed for $" . $_POST['bid_amount'];
                    $auctionObject->notifyUserOutbid($auctionItem);
				} else {
					$returnArray['error_message'] = $auctionObject->getErrorMessage();
				}
				$returnArray = array_merge($returnArray,$auctionObject->getBidStatistics($_POST['auction_item_id']));
				ajaxResponse($returnArray);
				break;

			case "get_buy_now_link":
                $auctionItemRow = getRowFromId("auction_items", "auction_item_id", $_GET['auction_item_id'],
					"date_completed is null and deleted = 0 and end_time > now() and buy_now_price is not null");
				if (empty($auctionItemRow)) {
					$returnArray['error_message'] = "Auction item is invalid or no longer available.";
					ajaxResponse($returnArray);
					break;
				}
				$auctionItemId = $auctionItemRow['auction_item_id'];
                if ($auctionItemRow['user_id'] == $GLOBALS['gUserId']) {
                    $returnArray['error_message'] = "You cannot buy your own listing.";
                    ajaxResponse($returnArray);
                    break;
                }
				$linkName = getFieldFromId("link_name", "pages", "script_filename", "auction/auctionpayment.php");
				if (empty($linkName)) {
					$returnArray['error_message'] = "Payment page not configured, contact support.";
					ajaxResponse($returnArray);
					break;
				}
				$hash = md5("buy_now:" . $auctionItemId . ":" . $auctionItemRow['user_id'] . ":" . $auctionItemRow['start_time']);
				$returnArray['buy_now_link'] = getDomainName(false, false, true) . "/" . $linkName . "?id=" . $auctionItemId . "&hash=" . $hash;
				ajaxResponse($returnArray);
				break;
		}
	}
}

$pageObject = new AuctionControllerPage();
$pageObject->displayPage();
