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
 * class ProductCatalog
 *
 * Catalog searches, prices, inventory
 *
 * @author Kim D Geiger
 */
class ProductCatalog {

	private static $iProductInventoryQuantities = array();
	private static $iInventoryCountLocationRows = false;
	private static $iNonInventoryProducts = false;
	private static $iLocations = false;
	private static $iDefaultLocation = false;
	private static $iDefaultLocationDescription = false;
	private static $iLocationAvailabilityTexts = false;
	private static $iPricingStructureSurcharges = false;
	private static $iUserTypePricingStructures = false;
	private static $iLocationRows = false;
	private static $iStoredPricingStructureIds = false;
	private static $iBackorderProducts = false;
	private static $iProductPacks = false;
	private static $iProductPacksClientId = false;
	private static $iPickupLocationIds = false;
	private static $iLocalOnlyProductIds = false;
	private static $iOutOfStockProducts = false;
	private static $iMapPolicies = false;

	private $iSearchText = "";
	private $iDisplaySearchText = "";
	private $iOffset = 0;
	private $iSelectLimit = 20;
	private $iLimitQuery = false;
	private $iSortBy = "";
	private $iResultCount = 0;
	private $iShowOutOfStock = false;
	private $iNeedSidebarInfo = true;
	private $iQuery = "";
	private $iQueryTime = 0;
	private $iIncludeTagsWithNoStartDate = true;
	private $iIgnoreClient = false;
	private $iReturnIdsOnly = false;
	private $iReturnCountOnly = false;
	private $iAllowInternalUseOnly = false;
	private $iDontLogSearchTerm = false;
	private $iGetProductSalePrice = false;
	private $iTruncateDescriptions = true;
	private $iIgnoreProductGroupDescription = false;
	private $iValidSorts = array("relevance", "alphabetical", "alphabetical_reverse", "price_low", "price_high", "manufacturer", "manufacturer_reverse", "random", "tagged_order", "sku", "sku_reverse");
	private $iProductIds = array();
	private $iSpecificProductIds = array();
	private $iTemporaryTableName = "";
	private $iTemporaryTableThreshold = 1000;
	private $iProductInventoryCounts = false;
	private $iPriceCalculationLog = false;
	private $iSendAllFields = false;
	private $iProductTypeIds = array();
	private $iDepartmentIds = array();
	private $iCategoryIds = array();
	private $iManufacturerIds = array();
	private $iLocationIds = array();
	private $iTagIds = array();
	private $iSearchGroupIds = array();
	private $iContributorIds = array();
	private $iProductFacetIds = array();
	private $iFacetOptionIds = array();
	private $iDescriptionIds = array();
	private $iExcludeCategories = array();
	private $iExcludeDepartments = array();
	private $iExcludeManufacturers = array();
	private $iSidebarFacetCodes = array();
	private $iSidebarFacetLimit = 0;
	private $iSidebarProductDataFields = array();
	private $iAllProductTypes = false;
	private $iAllCategoryIds = array();
	private $iDefaultImage = false;
	private $iBaseImageFilenameOnly = false;
	private $iIgnoreManufacturerLogo = false;
	private $iRelatedProductIds = array();
	private $iRelatedProductTypeId = "";
	private $iIgnoreProductsWithoutImages = false;
	private $iIgnoreProductsWithoutCategory = false;
	private $iProductTypePricingStructures = false;
	private $iProductManufacturerPricingStructures = false;
	private $iProductCategoryPricingStructures = false;
	private $iProductDepartmentPricingStructures = false;
	private $iPricingStructures = array();
	private $iPricingRules = false;
	private $iDefaultPricingStructureId = false;
	private $iIgnoreMapProducts = false;
	private $iPricingStructureUserTypes = false;
	private $iPricingStructureContactTypes = false;
	private $iUniqueManufacturers = array();
	private $iAddDomainName = false;
	private $iCompliantStates = array();
	private $iPushInStockToTop;
	private $iNoCacheStoredPrices = array();
	private $iIncludeNoInventoryProducts = false;
	private $iValidPricingStructures = false;
    private $iProductSalePrices = array();

	function __construct() {
		ProductCatalog::getStopWords();
		$this->iPushInStockToTop = getPreference("PUSH_IN_STOCK_TO_TOP");
	}

	public static function resetStaticVariables() {
		self::$iProductInventoryQuantities = array();
		self::$iInventoryCountLocationRows = false;
		self::$iNonInventoryProducts = false;
		self::$iLocations = false;
		self::$iDefaultLocation = false;
		self::$iDefaultLocationDescription = false;
		self::$iLocationAvailabilityTexts = false;
		self::$iPricingStructureSurcharges = false;
		self::$iUserTypePricingStructures = false;
		self::$iLocationRows = false;
		self::$iStoredPricingStructureIds = false;
		self::$iBackorderProducts = false;
		self::$iProductPacks = false;
		self::$iProductPacksClientId = false;
		self::$iPickupLocationIds = false;
		self::$iLocalOnlyProductIds = false;
		self::$iOutOfStockProducts = false;
	}

	function setIncludeNoInventoryProducts($noInventoryProducts) {
		$this->iIncludeNoInventoryProducts = $noInventoryProducts;
	}

	public static function getStopWords() {
		if ($GLOBALS['gStopWords'] === false) {
			$GLOBALS['gStopWords'] = getCachedData("stop_words", "", true);
			if (!$GLOBALS['gStopWords'] || !is_array($GLOBALS['gStopWords'])) {
				$GLOBALS['gStopWords'] = array();
				$resultSet = executeReadQuery("select * from stop_words order by search_term");
				while ($row = getNextRow($resultSet)) {
					$GLOBALS['gStopWords'][$row['search_term']] = $row['search_term'];
				}
				setCachedData("stop_words", "", $GLOBALS['gStopWords'], 24, true);
			}
		}
	}

	public static function processProductImages($productId, $parameters = array()) {
		$productRow = ProductCatalog::getCachedProductRow($productId);
		$parameters['product_code'] = $parameters['product_code'] ?: $productRow['product_code'];

		$imageUrls = array_filter(explode("|", str_replace(",", "|", $parameters['image_urls'])));
		$replaceExistingImages = !empty($parameters['replace_existing_images']) && !empty($imageUrls);
		$imageIds = array();
		$imageId = "";

		$existingImageIds = array();
		if (!empty($productRow['image_id'])) {
			$existingImageIds[] = $productRow['image_id'];
		}
		if (is_array($productRow) && array_key_exists("product_images", $productRow)) {
			foreach ($productRow['product_images'] as $thisImage) {
				$existingImageIds[] = $thisImage['image_id'];
			}
		} else {
			$resultSet = executeQuery("select * from product_images where product_id = ?", $productId);
			while ($row = getNextRow($resultSet)) {
				$existingImageIds[] = $row['image_id'];
			}
		}

		$firstImageId = false;
		foreach ($imageUrls as $thisUrl) {
			$imageContents = file_get_contents($thisUrl);
			if (!empty($imageContents) && strpos($imageContents, "<body") === false && strpos($imageContents, "</body>") === false && strpos($imageContents, "</html>") === false) {
				$extension = (substr($thisUrl, -3) == "png" ? "png" : "jpg");
				$thisImageId = createImage(array("extension" => $extension, "file_content" => $imageContents, "name" => $parameters['product_code'] . ".jpg", "description" => $parameters['description'], "detailed_description" => $parameters['detailed_description']));
				if (!empty($thisImageId)) {
					$firstImageId = $firstImageId ?: $thisImageId;
					if (($replaceExistingImages || !in_array($thisImageId, $existingImageIds)) && !in_array($thisImageId, $imageIds)) {
						$imageIds[] = $thisImageId;
					}
				}
			}
		}
		if ($replaceExistingImages) {
			foreach ($existingImageIds as $existingImageId) {
				executeQuery("delete from product_images where product_id = ? and image_id = ?", $productId, $existingImageId);
				if (!in_array($existingImageId, $imageIds)) {
					executeQuery("delete ignore from images where image_id = ?", $existingImageId);
				}
			}
		}
		if ($replaceExistingImages || !empty($parameters['replace_primary_image']) || (empty($productRow['image_id'])
				&& empty(getFieldFromId("image_identifier", "product_remote_images", "product_id", $productId, "primary_image = 1")))) {
			$imageId = array_shift($imageIds);
			$imageId = $imageId ?: $firstImageId;
		}
		return array("image_id" => $imageId, "product_images" => $imageIds);
	}

	public static function createProductImageFiles($productId, $imageId = "") {
		if ($GLOBALS['gClientRow']['client_code'] != "COREWARE_SHOOTING_SPORTS") {
			return false;
		}
		$imageIdArray = array();
		if (empty($imageId)) {
			$imageId = getFieldFromId("image_id", "products", "product_id", $productId);
		}
		if (!empty($imageId)) {
			$imageIdArray[] = $imageId;
		}
		$resultSet = executeQuery("select image_id from product_images where product_id = ?", $productId);
		while ($row = getNextRow($resultSet)) {
			$imageIdArray[] = $row['image_id'];
		}
		$validImageFilenames = array();
		foreach ($imageIdArray as $imageId) {
			$checkImageId = getFieldFromId("image_id", "images", "image_id", $imageId, "os_filename is null and file_content is null");
			if (!empty($checkImageId)) {
				executeQuery("update products set image_id = null where product_id = ? and image_id = ?", $productId, $imageId);
				executeQuery("delete from product_images where product_id = ? and image_id = ?", $productId, $imageId);
				executeQuery("delete from image_data where image_id = ?", $imageId);
				executeQuery("delete from images where image_id = ?", $imageId);
				continue;
			}
			$filename = getImageFilename($imageId);
			if (!file_exists($GLOBALS['gDocumentRoot'] . $filename) || filesize($GLOBALS['gDocumentRoot'] . $filename) == 0) {
				continue;
			}
			$validImageFilenames[] = $productId . "-" . $imageId . ".jpg";
			if (!file_exists($GLOBALS['gDocumentRoot'] . "/images/products/" . $productId . "-" . $imageId . ".jpg") || filesize($GLOBALS['gDocumentRoot'] . $filename) != filesize($GLOBALS['gDocumentRoot'] . "/images/products/" . $productId . "-" . $imageId . ".jpg")) {
				copy($GLOBALS['gDocumentRoot'] . $filename, $GLOBALS['gDocumentRoot'] . "/images/products/" . $productId . "-" . $imageId . ".jpg");
			}
			$filename = getImageFilename($imageId, array("image_type" => "small"));
			$validImageFilenames[] = "small-" . $productId . "-" . $imageId . ".jpg";
			if (!file_exists($GLOBALS['gDocumentRoot'] . "/images/products/small-" . $productId . "-" . $imageId . ".jpg") || filesize($GLOBALS['gDocumentRoot'] . $filename) != filesize($GLOBALS['gDocumentRoot'] . "/images/products/small-" . $productId . "-" . $imageId . ".jpg")) {
				copy($GLOBALS['gDocumentRoot'] . $filename, $GLOBALS['gDocumentRoot'] . "/images/products/small-" . $productId . "-" . $imageId . ".jpg");
			}
		}
		return true;
	}

	public static function getProductImage($productId, $parameters = array()) {
		if (function_exists("_localGetCustomProductImage")) {
			$url = _localGetCustomProductImage($productId, $parameters);
			if ($url) {
				return $url;
			}
		}
		if (array_key_exists("product_row", $parameters)) {
			$productRow = $parameters['product_row'];
		} else {
			$productRow = ProductCatalog::getCachedProductRow($productId);
		}
		if (!empty($productRow['image_id'])) {
			if (empty($productRow['image_id'])) {
				return getImageFilename($productRow['image_id'], $parameters);
			}
			if (empty($parameters['no_cache_filename'])) {
				$filename = getImageFilename($productRow['image_id'], $parameters);
			} else {
				$filename = "/getimage.php?id=" . $productRow['image_id'];
			}
			$cdnDomain = ($GLOBALS['gDevelopmentServer'] ? "" : getPreference("CDN_DOMAIN"));
			if (!empty($cdnDomain) && substr($cdnDomain, 0, 4) != "http") {
				$cdnDomain = "https://" . $cdnDomain;
			}
			return $cdnDomain . $filename;
		} elseif (!empty($productRow['remote_identifier'])) {
			$imageIdentifier = $parameters['image_identifier'];
			if (empty($imageIdentifier)) {
				$imageIdentifier = $productRow['product_remote_images'][0];
			}
			if (empty($imageIdentifier)) {
				return getImageFilename($productRow['image_id'], $parameters);
			}
			return "https://images.coreware.com/images/products/" . ($parameters['image_type'] == "small" || $parameters['image_type'] == "thumbnail" ? "small-" : "") . $productRow['remote_identifier'] . "-" . $imageIdentifier . ".jpg";
		} elseif (!empty($productRow['remote_image_urls'][0])) {
			return (substr($productRow['remote_image_urls'][0], 0, 4) == "http" ? "" : "https://") . $productRow['remote_image_urls'][0];
		}
	}

	public static function getCachedProductRow($productId, $additionalFilters = array()) {
		if (empty($productId)) {
			return false;
		}

		# if it is an array, then preload all those product IDs

		if (is_array($productId)) {
			$whereProductIds = "";
			foreach ($productId as $thisProductId) {
				if (!is_numeric($thisProductId) || empty($thisProductId)) {
					continue;
				}
				if (!array_key_exists($thisProductId, $GLOBALS['gProductRows'])) {
					$whereProductIds .= (empty($whereProductIds) ? "" : ",") . $thisProductId;
				}
			}
			if (!empty($whereProductIds)) {
				$resultSet = executeReadQuery("select *,products.client_id as product_client_id,(select description from product_manufacturers where product_manufacturer_id = products.product_manufacturer_id) manufacturer_name," .
					"(select map_policy_id from product_manufacturers where product_manufacturer_id = products.product_manufacturer_id) map_policy_id," .
					"(select group_concat(concat_ws('|',product_inventory_id,location_id,quantity)) FROM product_inventories WHERE product_id = products.product_id) inventory_quantities," .
					"(select group_concat(concat_ws('|',image_id,product_image_code)) FROM product_images WHERE product_id = products.product_id) product_images," .
					"(select count(*) from related_products where product_id = products.product_id and exists (select product_id from products where product_id = related_products.associated_product_id and inactive = 0)) as related_products_count," .
					"(select count(*) from related_products where product_id = products.product_id and exists (select product_id from products where product_id = related_products.associated_product_id and inactive = 0) and related_product_type_id is null) as general_related_products_count," .
					"(select group_concat(product_facet_option_id) from product_facet_values where product_id = products.product_id) as product_facet_option_ids," .
					"(select group_concat(image_identifier) from product_remote_images where product_id = products.product_id and image_identifier is not null order by primary_image desc,product_remote_image_id) as product_remote_images," .
					"(select group_concat(link_url) from product_remote_images where product_id = products.product_id and link_url is not null order by primary_image desc,product_remote_image_id) as remote_image_urls," .
					"(select group_concat(product_category_id) from product_category_links where product_id = products.product_id) as product_category_ids, " .
					"(select group_concat(product_tag_id) from product_tag_links where product_id = products.product_id and (start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date > current_date)) as product_tag_ids " .
					"from products left outer join product_data using (product_id)" .
					($GLOBALS['gPrimaryDatabase']->viewExists("view_of_additional_product_data") ? " join view_of_additional_product_data using (product_id)" : "") .
					" where products.product_id in (" . $whereProductIds . ")");
				while ($productRow = getNextRow($resultSet)) {
					$productRow['product_category_ids'] = array_filter(explode(",", $productRow['product_category_ids']));
					$productRow['product_tag_ids'] = array_filter(explode(",", $productRow['product_tag_ids']));
					$productRow['product_facet_option_ids'] = array_filter(explode(",", $productRow['product_facet_option_ids']));
					$productRow['product_remote_images'] = array_filter(explode(",", $productRow['product_remote_images']));
					$productRow['remote_image_urls'] = array_filter(explode(",", $productRow['remote_image_urls']));
					$productRow['client_id'] = $productRow['product_client_id'];
					$productImages = explode(",", $productRow['product_images']);
					$productRow['product_images'] = array();
					foreach ($productImages as $thisImage) {
						$parts = explode("|", $thisImage);
						if (!empty($parts[0]) && is_numeric($parts[0])) {
							$productRow['product_images'][] = array("image_id" => $parts[0], "product_image_code" => $parts[1]);
						}
					}
					$productRow['product_inventories'] = array();
					$productInventoryQuantities = explode(",", $productRow['inventory_quantities']);
					foreach ($productInventoryQuantities as $thisQuantity) {
						if (!array_key_exists($productRow['product_id'], self::$iProductInventoryQuantities)) {
							self::$iProductInventoryQuantities[$productRow['product_id']] = array();
						}
						if (!empty($thisQuantity)) {
							$parts = explode("|", $thisQuantity);
							self::$iProductInventoryQuantities[$productRow['product_id']][$parts[1]] = $parts[2];
						}
						$productRow['product_inventories'][] = array("product_inventory_id" => $parts[0], "location_id" => $parts[1], "quantity" => $parts[2]);
					}
					$GLOBALS['gProductRows'][$productRow['product_id']] = $productRow;
					$GLOBALS['gProductCodes'][$productRow['product_code']] = $productRow['product_id'];
				}
			}
			return false;
		}
		if (!array_key_exists($productId, $GLOBALS['gProductRows'])) {
			$resultSet = executeReadQuery("select *,products.client_id as product_client_id,(select description from product_manufacturers where product_manufacturer_id = products.product_manufacturer_id) manufacturer_name," .
				"(select map_policy_id from product_manufacturers where product_manufacturer_id = products.product_manufacturer_id) map_policy_id," .
				"(select group_concat(concat_ws('|',product_inventory_id,location_id,quantity)) FROM product_inventories WHERE product_id = products.product_id) inventory_quantities," .
				"(select group_concat(concat_ws('|',image_id,product_image_code)) FROM product_images WHERE product_id = products.product_id) product_images," .
				"(select count(*) from related_products where product_id = products.product_id and exists (select product_id from products where product_id = related_products.associated_product_id and inactive = 0)) as related_products_count," .
				"(select count(*) from related_products where product_id = products.product_id and exists (select product_id from products where product_id = related_products.associated_product_id and inactive = 0) and related_product_type_id is null) as general_related_products_count," .
				"(select group_concat(product_facet_option_id) from product_facet_values where product_id = products.product_id) as product_facet_option_ids," .
				"(select group_concat(image_identifier) from product_remote_images where product_id = products.product_id and image_identifier is not null order by primary_image desc,product_remote_image_id) as product_remote_images," .
				"(select group_concat(link_url) from product_remote_images where product_id = products.product_id and link_url is not null order by primary_image desc,product_remote_image_id) as remote_image_urls," .
				"(select group_concat(product_category_id) from product_category_links where product_id = products.product_id) as product_category_ids, " .
				"(select group_concat(product_tag_id) from product_tag_links where product_id = products.product_id and (start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date > current_date)) as product_tag_ids " .
				"from products left outer join product_data using (product_id)" .
				($GLOBALS['gPrimaryDatabase']->viewExists("view_of_additional_product_data") ? " join view_of_additional_product_data using (product_id)" : "") .
				" where products.product_id = ?", $productId);
			if ($productRow = getNextRow($resultSet)) {
				$productRow['product_category_ids'] = array_filter(explode(",", $productRow['product_category_ids']));
				$productRow['product_tag_ids'] = array_filter(explode(",", $productRow['product_tag_ids']));
				$productRow['product_facet_option_ids'] = array_filter(explode(",", $productRow['product_facet_option_ids']));
				$productRow['product_remote_images'] = array_filter(explode(",", $productRow['product_remote_images']));
				$productRow['remote_image_urls'] = array_filter(explode(",", $productRow['remote_image_urls']));
				$productRow['client_id'] = $productRow['product_client_id'];
				$productImages = explode(",", $productRow['product_images']);
				$productRow['product_images'] = array();
				foreach ($productImages as $thisImage) {
					$parts = explode("|", $thisImage);
					if (!empty($parts[0]) && is_numeric($parts[0])) {
						$productRow['product_images'][] = array("image_id" => $parts[0], "product_image_code" => $parts[1]);
					}
				}
				$productRow['product_inventories'] = array();
				$productInventoryQuantities = explode(",", $productRow['inventory_quantities']);
				foreach ($productInventoryQuantities as $thisQuantity) {
					if (!array_key_exists($productRow['product_id'], self::$iProductInventoryQuantities)) {
						self::$iProductInventoryQuantities[$productRow['product_id']] = array();
					}
					if (!empty($thisQuantity)) {
						$parts = explode("|", $thisQuantity);
						self::$iProductInventoryQuantities[$productRow['product_id']][$parts[1]] = $parts[2];
					}
					$productRow['product_inventories'][] = array("product_inventory_id" => $parts[0], "location_id" => $parts[1], "quantity" => $parts[2]);
				}
				$GLOBALS['gProductRows'][$productId] = $productRow;
				$GLOBALS['gProductCodes'][$productRow['product_code']] = $productRow['product_id'];
			}
		}
		$productRow = $GLOBALS['gProductRows'][$productId];
		if (empty($productRow)) {
			return false;
		}
		foreach ($additionalFilters as $fieldName => $fieldValue) {
			if (strlen($fieldValue) == 0) {
				continue;
			}
			if ($productRow[$fieldName] != $fieldValue) {
				return false;
			}
		}
		return $productRow;
	}

	public static function getProductAlternateImages($productId, $parameters = array()) {
		if (array_key_exists("product_row", $parameters)) {
			$productRow = $parameters['product_row'];
		} else {
			$productRow = ProductCatalog::getCachedProductRow($productId);
		}
		$imageUrls = array();

		$cdnDomain = ($GLOBALS['gDevelopmentServer'] ? "" : getPreference("CDN_DOMAIN"));
		if (!empty($cdnDomain) && substr($cdnDomain, 0, 4) != "http") {
			$cdnDomain = "https://" . $cdnDomain;
		}
		if (array_key_exists("product_images", $productRow)) {
			$productImages = $productRow['product_images'];
		} else {
			$resultSet = executeReadQuery("select * from product_images where product_id = ?", $productId);
			$productImages = array();
			while ($row = getNextRow($resultSet)) {
				$productImages[] = array("image_id" => $row['image_id'], "product_image_code" => $row['product_image_code']);
			}
		}
		foreach ($productImages as $row) {
			if (is_array($row)) {
				$imageId = $row['image_id'];
				$imageCode = $row['product_image_code'];
			} else {
				$imageId = $row;
				$imageCode = "";
			}
			if (empty($parameters['no_cache_filename'])) {
				$filename = getImageFilename($imageId, $parameters);
			} else {
				$filename = "/getimage.php?id=" . $imageId;
			}
			if ($parameters['include_code']) {
				$imageUrls[] = array("url" => $cdnDomain . $filename, "code" => $imageCode);
			} else {
				$imageUrls[] = array("url" => $cdnDomain . $filename);
			}
		}
		if (empty($imageUrls)) {
			$skippedOne = false;
			if (array_key_exists("image_identifiers", $parameters)) {
				$productRemoteImages = $parameters['image_identifiers'];
			} else {
				$productRemoteImages = $productRow['product_remote_images'];
			}
			foreach ($productRemoteImages as $imageIdentifier) {
				if (!$skippedOne) {
					$skippedOne = true;
					continue;
				}
				if ($parameters['include_code']) {
					$imageUrls[] = array("url" => "https://images.coreware.com/images/products/" . ($parameters['image_type'] == "small" || $parameters['image_type'] == "thumbnail" ? "small-" : "") . $productRow['remote_identifier'] . "-" . $imageIdentifier . ".jpg");
				} else {
					$imageUrls[] = array("url" => "https://images.coreware.com/images/products/" . ($parameters['image_type'] == "small" || $parameters['image_type'] == "thumbnail" ? "small-" : "") . $productRow['remote_identifier'] . "-" . $imageIdentifier . ".jpg");
				}
			}
		}
		if (!empty($productRow['remote_image_urls'])) {
			$skippedOne = false;
			foreach ($productRow['remote_image_urls'] as $imageUrl) {
				if (!$skippedOne) {
					$skippedOne = true;
					continue;
				}
				$imageUrls[] = array("url" => (substr($imageUrl, 0, 4) == "http" ? "" : "https://") . $imageUrl);
			}
		}
		return $imageUrls;
	}

    public static function calculateProductCost($productId, $parameters = array()) {
        if (!empty($parameters) && !is_array($parameters)) {
            $parameters = array("reason" => $parameters);
        }
        removeCachedData("*", $productId);

        # For marketplace sites and stores with multiple local locations, allow an option to only use distributor and warehouse locations for product cost calculations
        $ignoreLocalLocations = getPreference("IGNORE_LOCAL_LOCATIONS_FOR_PRODUCT_COST");

        ProductCatalog::getInventoryAdjustmentTypes();
        $totalWaitingQuantity = ProductCatalog::getWaitingToShipQuantity($productId);
        $changeLogEntry = "Base cost recalculated" . (empty($parameters['reason']) ? "" : " because " . $parameters['reason']) . "\n";
        $changeLogEntry .= "Total Waiting Quantity: " . $totalWaitingQuantity . "\n";

        $inventoryCosts = $parameters['inventory_costs'];
        $changeLogEntry .= "Inventory found:\n";
        if (empty($inventoryCosts) || !is_array($inventoryCosts)) {
            ProductCatalog::updateAllProductLocationCosts($productId);
			$resultSet = executeQuery("select product_inventories.*, locations.description from product_inventories join locations using (location_id) 
                where product_inventories.location_id in (select location_id from locations where inactive = 0 and internal_use_only = 0 and ignore_inventory = 0 and
                (product_distributor_id is null or primary_location = 1)" . (empty($ignoreLocalLocations) ? "" : " and (product_distributor_id is not null or warehouse_location = 1)") . ") and 
                location_id not in (select location_id from product_prices where location_id is not null and product_id = ? and (start_date is null or start_date <= current_date) and 
                (end_date is null or end_date >= current_date)) and product_id = ?", $productId, $productId);
            if($resultSet['row_count'] == 0) {
                $resultSet = executeQuery("select product_inventories.*, locations.description from product_inventories join locations using (location_id) where product_inventories.location_id in (select location_id from locations where inactive = 0 and internal_use_only = 0 and ignore_inventory = 0 and " .
                    (empty($ignoreLocalLocations) ? "(product_distributor_id is null or primary_location = 1)" : "(warehouse_location = 1 or (product_distributor_id is not null and primary_location = 1))") . ") and product_id = ?", $productId);
            }
                $inventoryCosts = array();
                while ($row = getNextRow($resultSet)) {
				$inventoryCosts[] = array("location_id" => $row['location_id'], "location_description"=>$row['description'], "quantity" => $row['quantity'], "location_cost" => $row['location_cost']);
                $changeLogEntry .= $row['location_description'] . ": " . $row['quantity'] . " - " . $row['location_cost'] . "\n";
            }
        }
        usort($inventoryCosts, array('ProductCatalog', "sortProductCosts"));
        $remainingWaitingQuantity = $totalWaitingQuantity;
        $totalAvailable = 0;
        $baseCost = false;
        foreach ($inventoryCosts as $index => $inventoryInfo) {
            if (!is_array($inventoryInfo) || !array_key_exists("quantity",$inventoryInfo)) {
                continue;
            }
            $waitingQuantity = min($inventoryInfo['quantity'], $remainingWaitingQuantity);
            $inventoryCosts[$index]['waiting'] = $waitingQuantity;
            $inventoryCosts[$index]['available'] = $inventoryInfo['quantity'] - $waitingQuantity;
            $totalAvailable += $inventoryCosts[$index]['available'];
            $remainingWaitingQuantity -= $waitingQuantity;
        }
        $changeLogEntry .= "Total Available: " . $totalAvailable . "\n";
        foreach ($inventoryCosts as $inventoryInfo) {
            if ($totalAvailable <= 0) {
                $baseCost = $inventoryInfo['location_cost'];
                break;
			} elseif ($inventoryInfo['available'] > 0) {
                $baseCost = $inventoryInfo['location_cost'];
                break;
            }
        }
        if (!empty($baseCost) && $baseCost > 0) {
            $originalBaseCost = $parameters['original_base_cost'];
            if (empty($originalBaseCost)) {
                $originalBaseCost = getFieldFromId("base_cost", "products", "product_id", $productId);
            }
            if ((floatval($originalBaseCost) !== floatval($baseCost))) {
                $GLOBALS['gChangeLogNotes'] = $changeLogEntry;
                $dataTable = new DataTable("products");
                $dataTable->setSaveOnlyPresent(true);
                $dataTable->saveRecord(array("name_values" => array("base_cost" => $baseCost), "primary_id" => $productId));
                $GLOBALS['gChangeLogNotes'] = "";
                productCatalog::calculateAllProductSalePrices($productId);
            }
        }
        setCachedData("base_cost", $productId, $baseCost, 1);
    }

	public static function getInventoryAdjustmentTypes() {
		if ($GLOBALS['gInventoryAdjustmentTypeId'] === false) {
			$GLOBALS['gInventoryAdjustmentTypeId'] = getFieldFromId("inventory_adjustment_type_id", "inventory_adjustment_types", "inventory_adjustment_type_code", "INVENTORY");
			if (empty($GLOBALS['gInventoryAdjustmentTypeId'])) {
				$insertSet = executeQuery("insert into inventory_adjustment_types (client_id,inventory_adjustment_type_code,description,adjustment_type) values " .
					"(?,'INVENTORY','Inventory','R')", $GLOBALS['gClientId']);
				$GLOBALS['gInventoryAdjustmentTypeId'] = $insertSet['insert_id'];
			}
		}
		if ($GLOBALS['gRestockAdjustmentTypeId'] === false) {
			$GLOBALS['gRestockAdjustmentTypeId'] = getFieldFromId("inventory_adjustment_type_id", "inventory_adjustment_types", "inventory_adjustment_type_code", "RESTOCK");
			if (empty($GLOBALS['gRestockAdjustmentTypeId'])) {
				$insertSet = executeQuery("insert into inventory_adjustment_types (client_id,inventory_adjustment_type_code,description,adjustment_type) values " .
					"(?,'RESTOCK','Restock','A')", $GLOBALS['gClientId']);
				$GLOBALS['gRestockAdjustmentTypeId'] = $insertSet['insert_id'];
			}
		}
		if ($GLOBALS['gSalesAdjustmentTypeId'] === false) {
			$GLOBALS['gSalesAdjustmentTypeId'] = getFieldFromId("inventory_adjustment_type_id", "inventory_adjustment_types", "inventory_adjustment_type_code", "SALES");
			if (empty($GLOBALS['gSalesAdjustmentTypeId'])) {
				$insertSet = executeQuery("insert into inventory_adjustment_types (client_id,inventory_adjustment_type_code,description,adjustment_type) values " .
					"(?,'SALES','Sales','S')", $GLOBALS['gClientId']);
				$GLOBALS['gSalesAdjustmentTypeId'] = $insertSet['insert_id'];
			}
		}
	}

	public static function getLocationBaseCost($productId, $locationId, $productInventoryRow = array(), $useProductBaseCost = true) {
		if (empty($productInventoryRow)) {
			$productInventoryRow = getRowFromId("product_inventories", "product_id", $productId, "location_id = ?", $locationId);
		}
		if (empty($productInventoryRow['location_cost'])) {
			$locationCost = ProductCatalog::updateLocationBaseCost($productId, $locationId);
		} else {
			$locationCost = $productInventoryRow['location_cost'];
		}
		if (empty($locationCost) && $useProductBaseCost) {
			$locationCost = getFieldFromId("base_cost", "products", "product_id", $productId);
		}
		return $locationCost;
	}

	public static function updateAllProductLocationCosts($productId) {
		$resultSet = executeQuery("select location_id from product_inventories where product_id = ?",$productId);
		$locations = array();
		while ($row = getNextRow($resultSet)) {
			$locations[] = $row['location_id'];
		}
		foreach ($locations as $locationId) {
			ProductCatalog::updateLocationBaseCost($productId, $locationId);
		}
	}

	public static function updateLocationBaseCost($productId, $locationId) {
		$resultSet = executeQuery("select product_inventory_log.* from product_inventory_log join product_inventories using (product_inventory_id) " .
			"where product_inventory_log.quantity > 0 and total_cost is not null and total_cost > 0 and product_id = ? and location_id = ? " .
			"order by log_time desc", $productId, $locationId);
		$usedLocationCost = false;
		while ($row = getNextRow($resultSet)) {
			$locationCost = round($row['total_cost'] / $row['quantity'], 2);
			if ($locationCost <= 0) {
				continue;
			}
			executeQuery("update product_inventories set location_cost = ? where product_inventory_id = ?", $locationCost, $row['product_inventory_id']);
			$usedLocationCost = $locationCost;
			break;
		}
		freeResult($resultSet);
		return $usedLocationCost;
	}

	public static function getWaitingToShipQuantity($productId) {
		$waitingQuantity = getCachedData("product_waiting_quantity", $productId);
		if ($waitingQuantity == "NONE") {
			$waitingQuantity = 0;
		}
		if ($waitingQuantity !== false && strlen($waitingQuantity) > 0 && is_numeric($waitingQuantity)) {
			return $waitingQuantity;
		}
		if ($GLOBALS['gMultipleProductsForWaitingQuantities'] && is_array($GLOBALS['gProductWaitingQuantities']) && !array_key_exists($productId, $GLOBALS['gProductWaitingQuantities']) && count($GLOBALS['gProductWaitingQuantities']) > 10) {
			$waitingQuantity = $GLOBALS['gProductWaitingQuantities'][$productId] = 0;
			setCachedData("product_waiting_quantity", $productId, ($waitingQuantity == 0 ? "NONE" : $waitingQuantity), .1);
			return $waitingQuantity;
		}
		if (!array_key_exists("gProductWaitingQuantities", $GLOBALS) || !is_array($GLOBALS['gProductWaitingQuantities']) || !array_key_exists($productId, $GLOBALS['gProductWaitingQuantities'])) {
			$GLOBALS['gProductWaitingQuantities'] = array();
			$parameters = array($GLOBALS['gClientId']);
			if (!$GLOBALS['gMultipleProductsForWaitingQuantities']) {
				$parameters[] = $productId;
			}
			$resultSet = executeReadQuery("select product_id,sum(quantity) as quantity_ordered, (select sum(quantity) from order_shipment_items where exists (select order_shipment_id from order_shipments " .
				"where order_shipment_id = order_shipment_items.order_shipment_id and secondary_shipment = 0) and order_item_id in (select order_item_id from order_items as oi where " .
				"product_id = order_items.product_id and deleted = 0 and order_id in (select order_id from orders where deleted = 0 and date_completed is null))) as quantity_shipped from order_items " .
				"where deleted = 0 and order_id in (select order_id from orders where deleted = 0 and date_completed is null and client_id = ?) " .
				($GLOBALS['gMultipleProductsForWaitingQuantities'] ? "" : "and product_id = ? ") . "group by product_id", $parameters);
			while ($row = getNextRow($resultSet)) {
				if (empty($row['quantity_ordered'])) {
					$row['quantity_ordered'] = 0;
				}
				if (empty($row['quantity_shipped'])) {
					$row['quantity_shipped'] = 0;
				}
				$waitingQuantity = max(0, $row['quantity_ordered'] - $row['quantity_shipped']);
				$GLOBALS['gProductWaitingQuantities'][$row['product_id']] = $waitingQuantity;
			}
			if (!array_key_exists($productId, $GLOBALS['gProductWaitingQuantities'])) {
				$GLOBALS['gProductWaitingQuantities'][$productId] = 0;
			}
		}
		if (array_key_exists($productId, $GLOBALS['gProductWaitingQuantities'])) {
			$waitingQuantity = $GLOBALS['gProductWaitingQuantities'][$productId];
		} else {
			$waitingQuantity = 0;
		}
		setCachedData("product_waiting_quantity", $productId, ($waitingQuantity == 0 ? "NONE" : $waitingQuantity), .1);
		return $waitingQuantity;
	}

	public static function calculateAllProductSalePrices($productId) {
		if (!is_array($GLOBALS['gMaxedOutProductSalePrices'])) {
			$GLOBALS['gMaxedOutProductSalePrices'] = array();
			$resultSet = executeQuery("select product_id from product_sale_prices where hours_between > 720 and expiration_time > date_sub(current_time, interval 2 day)");
			while ($row = getNextRow($resultSet)) {
				$GLOBALS['gMaxedOutProductSalePrices'][$row['product_id']] = true;
			}
		}
		$ignoreStoredPrices = getPreference("IGNORE_STORED_PRICES");
		if (!empty($ignoreStoredPrices)) {
			return;
		}
		if (!is_array($GLOBALS['gProductSalePrices']) || !array_key_exists($productId, $GLOBALS['gProductSalePrices']) || empty($GLOBALS['gProductSalePrices'])) {
			if (!isset($GLOBALS['gProductSalePrices']) || !is_array($GLOBALS['gProductSalePrices'])) {
				$GLOBALS['gProductSalePrices'] = array();
			}
			if ($GLOBALS['gCommandLine']) {
				$resultSet = executeReadQuery("select product_id,parameters from product_sale_prices where product_id in (select product_id from products where client_id = ?)", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$GLOBALS['gProductSalePrices'][$row['product_id']] = json_decode($row['parameters'], true);
				}
			}
		}
		if (!array_key_exists($productId, $GLOBALS['gProductSalePrices'])) {
			$resultSet = executeReadQuery("select product_id,parameters from product_sale_prices where product_id in (select product_id from products where client_id = ?) and product_id = ?", $GLOBALS['gClientId'], $productId);
			while ($row = getNextRow($resultSet)) {
				$GLOBALS['gProductSalePrices'][$row['product_id']] = json_decode($row['parameters'], true);
			}
		}

		$productRow = ProductCatalog::getCachedProductRow($productId);

		if (empty($GLOBALS['gCalculateAllProductCatalog'])) {
			$GLOBALS['gCalculateAllProductCatalog'] = new ProductCatalog();
		}
		$salePriceInfo = $GLOBALS['gCalculateAllProductCatalog']->getProductSalePrice($productId, array("product_information" => $productRow, "no_cache" => true, "no_stored_prices" => true, "contact_type_id" => "", "user_type_id" => ""));
		unset($salePriceInfo['calculation_log']);
		$salePriceInfo['sale_price'] = number_format(round($salePriceInfo['sale_price'], 2), 2, ".", "");
		if (array_key_exists("original_sale_price", $salePriceInfo) && !empty($salePriceInfo['original_sale_price'])) {
			$salePriceInfo['original_sale_price'] = number_format(round($salePriceInfo['original_sale_price'], 2), 2, ".", "");
		}
		foreach ($salePriceInfo as $index => $value) {
			if ($value === false) {
				unset($salePriceInfo[$index]);
			}
		}
		if ($salePriceInfo['sale_price'] > 0) {
			ksort($salePriceInfo);
			$defaultPrice = jsonEncode($salePriceInfo);

			if (array_key_exists($productId, $GLOBALS['gProductSalePrices'])) {
				if ($defaultPrice == jsonEncode($GLOBALS['gProductSalePrices'][$productId])) {
					if (!array_key_exists($productId, $GLOBALS['gMaxedOutProductSalePrices'])) {
						executeQuery("update product_sale_prices set time_changed = now(), hours_between = least(hours_between + 4,2160), expiration_time = date_add(now(),interval least(hours_between + 4,2160) hour), version = version + 1 where product_id = ?", $productId);
					}
				} else {
					executeQuery("update product_sale_prices set time_changed = now(), hours_between = 12, expiration_time = date_add(now(),interval 12 hour), parameters = ?, version = version + 1 where product_id = ?", $defaultPrice, $productId);
				}
			} else {
				executeQuery("insert ignore into product_sale_prices (product_id,hours_between,time_changed,expiration_time,parameters) values (?,12,now(),date_add(now(),interval 12 hour),?)", $productId, $defaultPrice);
			}

			$GLOBALS['gProductSalePrices'][$productId] = $salePriceInfo;
		}
	}

	public static function productIsInDepartment($productId, $productDepartmentId) {
		if ($GLOBALS['gDepartmentProducts'] === false) {
			$GLOBALS['gDepartmentProducts'] = getCachedData("is_in_department_array", "");
		}
		if (empty($GLOBALS['gDepartmentProducts']) || !is_array($GLOBALS['gDepartmentProducts'])) {
			$GLOBALS['gDepartmentProducts'] = array();
		}
		if (!array_key_exists($productDepartmentId, $GLOBALS['gDepartmentProducts'])) {
			$GLOBALS['gDepartmentProducts'][$productDepartmentId] = array();
			$resultSet = executeReadQuery("select product_id from product_category_links where product_category_id in (select product_category_id from product_categories where inactive = 0) and " .
				"product_category_id in (select product_category_id from product_category_departments where " .
				"product_department_id = ? and product_department_id in (select product_department_id from product_departments where inactive = 0))", $productDepartmentId);
			while ($row = getNextRow($resultSet)) {
				$GLOBALS['gDepartmentProducts'][$productDepartmentId][$row['product_id']] = $row['product_id'];
			}
			$resultSet = executeReadQuery("select product_id from product_category_links where product_category_id in (select product_category_id from product_categories where inactive = 0) and " .
				"product_category_id in (select product_category_id from product_category_group_links where " .
				"product_category_group_id in (select product_category_group_id from product_category_group_departments where product_department_id = ? and " .
				"product_department_id in (select product_department_id from product_departments where inactive = 0)) and product_category_group_id in " .
				"(select product_category_group_id from product_category_groups where inactive = 0))", $productDepartmentId);
			while ($row = getNextRow($resultSet)) {
				$GLOBALS['gDepartmentProducts'][$productDepartmentId][$row['product_id']] = $row['product_id'];
			}
			setCachedData("is_in_department_array", "", $GLOBALS['gDepartmentProducts'], 1);
		}
		return (array_key_exists($productId, $GLOBALS['gDepartmentProducts'][$productDepartmentId]));
	}

	public static function productIsInCategory($productId, $productCategoryId) {
		if (empty($productId) || empty($productCategoryId)) {
			return false;
		}
		if (empty($GLOBALS['gProductCategoryLinks']) || !is_array($GLOBALS['gProductCategoryLinks'])) {
			$GLOBALS['gProductCategoryLinks'] = array();
		}
		if (!array_key_exists($productCategoryId, $GLOBALS['gProductCategoryLinks'])) {
			$GLOBALS['gProductCategoryLinks'][$productCategoryId] = array();
			$resultSet = executeReadQuery("select product_id from product_category_links where product_category_id = ?", $productCategoryId);
			while ($row = getNextRow($resultSet)) {
				$GLOBALS['gProductCategoryLinks'][$productCategoryId][$row['product_id']] = $row['product_id'];
			}
		}
		return array_key_exists($productId, $GLOBALS['gProductCategoryLinks'][$productCategoryId]);
	}

	public static function getCatalogResultHtml($forceTile = false) {
		$listType = $_COOKIE['result_display_type'];
		$addToCartText = "Add To Cart";
		$noGuestCheckout = getPreference("RETAIL_STORE_NO_GUEST_CART");
		if (!empty($noGuestCheckout) && !$GLOBALS['gLoggedIn']) {
			$addToCartText = "Login to Purchase";
		}
		$callForPriceText = getFragment("CALL_FOR_PRICE");
		if (empty($callForPriceText)) {
			$callForPriceText = getLanguageText("Call for Price");
		}
		
		if ($listType == "list" && !$forceTile) {
			$catalogResultTemplate = getFragment("RETAIL_STORE_CATALOG_LIST_RESULT");
			if (empty($catalogResultTemplate)) {
				ob_start();
				?>
				<div id="_catalog_result" class="hidden">
					<div class="catalog-item catalog-list-item %other_classes%" id="catalog_item_%product_id%" data-product_id="%product_id%">
						<input type="hidden" class="product-detail-link" value="%product_detail_link%">
						<div class="catalog-item-thumbnail">
							<a href='%image_url%' class='pretty-photo'><!--suppress RequiredAttributes -->
								<img loading='lazy' alt='thumbnail image' %image_src%="%small_image_url%"></a>
						</div>
						<div class="click-product-detail">
							<div class="catalog-item-description">%description%</div>
						</div>
						<div>
							<div class="catalog-item-price-wrapper"><span class="dollar">$</span><span class="catalog-item-price">%sale_price%</span></div>
						</div>
						<div class="catalog-item-button-wrapper">
							<div class="catalog-item-out-of-stock">
								<button class="out-of-stock out-of-stock-%product_id%" data-product_id="%product_id%">Out of Stock<span class='button-subtext'><br>Notify when in stock</span></button>
							</div>
							<div class="catalog-item-add-to-cart">
								<button class="add-to-cart add-to-cart-%product_id%" data-product_id="%product_id%" data-text="<?= $addToCartText ?>" data-strict_map="Email for Price" data-call_price="<?= $callForPriceText ?>" data-in_text="Item In Cart" data-adding_text="Adding"><?= $addToCartText ?><span class='button-subtext'><br>for best price</span></button>
							</div>
							<div class="catalog-item-add-to-wishlist">
								<button class="add-to-wishlist add-to-wishlist-%product_id%" data-product_id="%product_id%" data-text="Add to Wishlist" data-in_text="Item In Wishlist" data-adding_text="Adding">Add to Wishlist</button>
							</div>
						</div>
					</div>
				</div>
				<?php
				$catalogResultTemplate = ob_get_clean();
			}
		} else {
			$catalogResultTemplate = getPageTextChunk("retail_store_catalog_result");
			if (empty($catalogResultTemplate)) {
				$catalogResultTemplate = getFragment("retail_store_catalog_result");
			}
			if (empty($catalogResultTemplate)) {
				$fragmentId = getPreference("PRODUCT_RESULT_HTML_FRAGMENT_ID");
				if (!empty($fragmentId)) {
					$catalogResultTemplate = getFieldFromId("content", "fragments", "fragment_id", $fragmentId, "client_id = ?", $GLOBALS['gDefaultClientId']);
				}
			}
			if (empty($catalogResultTemplate)) {
				$catalogResultTemplate = getFieldFromId("content", "fragments", "fragment_code", "RETAIL_STORE_CATALOG_DETAIL", "client_id = ?", $GLOBALS['gDefaultClientId']);
			}
		}
		if (empty($catalogResultTemplate)) {
			ob_start();
			?>
			<div id="_catalog_result" class="hidden">
				<div class="catalog-item %other_classes%" id="catalog_item_%product_id%" data-product_id="%product_id%">
					<input type="hidden" class="product-detail-link" value="%product_detail_link%">
					<div class="click-product-detail">
						<div class="catalog-item-thumbnail">
							<!--suppress RequiredAttributes -->
							<img loading='lazy' alt='thumbnail image' %image_src%="%small_image_url%">
						</div>
						<div class="catalog-item-description">%description%</div>
						<div class="catalog-item-brand"><span class="info-label">Brand </span><span class="highlighted-text">%manufacturer_name%</span></div>
						<div class="catalog-item-manufacturer-sku"><span class="info-label">SKU </span><span class="highlighted-text">%manufacturer_sku%</span></div>
						<div class="catalog-item-upc-code"><span class="info-label">UPC </span><span class="highlighted-text">%upc_code%</span></div>
						<div class="catalog-item-price-wrapper"><span class="catalog-item-original-price strikeout">%original_sale_price%</span></div>
						<div class="catalog-item-price-wrapper"><span class="dollar">$</span><span class="catalog-item-price">%sale_price%</span></div>
					</div>
					%product_tags%
					<div class="catalog-item-credova-financing">
						%credova_financing%
					</div>
					<div class='catalog-item-compare-wrapper'><input type='checkbox' class='catalog-item-compare' id='catalog_item_compare_%product_id%' value='1'><label for="catalog_item_compare_%product_id%" class='checkbox-label catalog-item-compare-label'>Select</label>
						<button class='catalog-item-compare-button'>Compare</button>
					</div>
					<div class="catalog-item-button-wrapper">
						<div class="catalog-item-out-of-stock">
							<button class="out-of-stock out-of-stock-%product_id%" data-product_id="%product_id%">Out of Stock<span class='button-subtext'><br>Notify when in stock</span></button>
						</div>
						<div class="catalog-item-add-to-cart">
							<button class="add-to-cart add-to-cart-%product_id%" data-product_id="%product_id%" data-text="<?= $addToCartText ?>" data-strict_map="Email for Price" data-call_price="<?= $callForPriceText ?>" data-in_text="Item In Cart" data-adding_text="Adding"><?= $addToCartText ?><span class='button-subtext'><br>for best price</span></button>
						</div>
						<div class="catalog-item-add-to-wishlist">
							<button class="add-to-wishlist add-to-wishlist-%product_id%" data-notify_when_in_stock="Y" data-product_id="%product_id%" data-text="Add to Wishlist" data-in_text="Item In Wishlist" data-adding_text="Adding">Add to Wishlist</button>
						</div>
					</div>
					<div class="catalog-item-location-availability">
						%location_availability%
					</div>
				</div>
			</div>
			<?php
			$catalogResultTemplate = ob_get_clean();
		}
		$catalogResultTemplate = str_replace("%call_for_price_text%", $callForPriceText, $catalogResultTemplate);
		$catalogResultTemplate = str_replace("%add_to_cart_text%", $addToCartText, $catalogResultTemplate);
		// mahathi come 1
		return $catalogResultTemplate;
	}

	public static function getFFLChoiceElement($showExpired = false) {
		$fflChoiceElement = getFragment("retail_store_ffl_choice");
		if (empty($fflChoiceElement)) {
			$fflChoiceElement = "<div class='ffl-choice'><p class='ffl-choice-business-name'>%business_name%</p><p class='ffl-choice-address'>%address_1%</p>" .
				"<p class='ffl-choice-city'>%city%, %state% %postal_code%</p><p class='ffl-phone-number'>%phone_number%</p><p class='ffl-distance distance-%distance%-miles'>" .
				"%distance% miles away</p>" . ($showExpired ? "%expiration_date_notice%" : "") . "</p><input type='hidden' class='ffl-latitude' value='%latitude%'>" .
				"<input type='hidden' class='ffl-longitude' value='%longitude%'></div>";
		}
		return $fflChoiceElement;
	}

	public static function getEmailForPriceDialog() {
		?>
		<div id="_email_for_price_quote_dialog" class="dialog-box">

			<p class="error-message"></p>
			<form id="_email_for_price_form">
				<input type="hidden" id="email_for_price_zip_code" name="email_for_price_zip_code" value="">
				<input type="hidden" id="email_for_price_product_id" name="email_for_price_product_id" value="">
				<?php
				$emailPriceText = getPageTextChunk("retail_store_email_for_price_text");
				if (empty($email_forPriceText)) {
					$emailPriceText = getFragment("retail_store_email_for_price_text");
				}
				if (empty($emailPriceText)) {
					$emailPriceText = "Fill out this form and we'll send you a custom quote for this product. The quote will only work for you and only for 24 hours. " .
						"Since you are " . ($GLOBALS['gLoggedIn'] ? "" : "not ") . "logged in when requesting this quote, " .
						($GLOBALS['gLoggedIn'] ? "you MUST be logged in when you try to use it." : "you must use the same browser when using the quote.");
				}
				echo makeHtml($emailPriceText);
				?>
				<div class="form-line">
					<label>First Name</label>
					<input class="validate[required]" type="text" id="email_for_price_first_name" name="email_for_price_first_name" maxlength="25">
					<div class='clear-div'></div>
				</div>

				<div class="form-line">
					<label>Last Name</label>
					<input class="validate[required]" type="text" id="email_for_price_last_name" name="email_for_price_last_name" maxlength="35">
					<div class='clear-div'></div>
				</div>

				<div class="form-line">
					<label>Email Address</label>
					<input class="validate[required,custom[email]]" type="text" id="email_for_price_email_address" name="email_for_price_email_address" maxlength="60">
					<div class='clear-div'></div>
				</div>

				<?php
				$imageId = getFieldFromId("image_id", "images", "image_code", "sum_captcha");
				if (!empty($imageId)) {
					?>

					<div class="form-line">
						<label><img alt='captcha image' src='<?= getImageFilename($imageId) ?>'></label>
						<input class="validate[required,custom[integer]]" type="text" id="sum_captcha" name="sum_captcha" maxlength="4" size="4">
						<div class='clear-div'></div>
					</div>

				<?php } ?>

			</form>
		</div>
		<?php
	}

	public static function getReviewStars($rating) {
		$starsElement = "";
		if (strlen($rating) == 0) {
			return $starsElement;
		}
		$starRating = (ceil($rating / .5) * .5);
		$starsAdded = 0;
		for ($x = 1; $x <= 5; $x++) {
			if ($starRating >= $x) {
				$starsElement .= "<span class='fas fa-star'></span>";
				$starsAdded++;
			}
		}
		if ($starsAdded < $starRating) {
			$starsElement .= "<span class='fas fa-star-half'></span><span class='far fa-star-half empty-half-star'></span>";
			$starsAdded++;
		}
		while ($starsAdded < 5) {
			$starsElement .= "<span class='far fa-star'></span>";
			$starsAdded++;
		}
		return $starsElement;
	}

	public static function getLocationGroupInventory($locationGroupId, $productArray) {
		$productInventories = array();
		$resultSet = executeQuery("select * from locations where location_group_id = ?", $locationGroupId);
		while ($row = getNextRow($resultSet)) {
			$locationInventory = self::getLocationInventory($row['location_id'], $productArray);
			$productInventories[$row['location_id']] = $locationInventory;
		}
		return $productInventories;
	}

	public static function getLocationInventory($locationId, $productArray) {
		if (!is_array($productArray)) {
			$productArray = array($productArray);
		}
		$locationRow = getRowFromId("locations", "location_id", $locationId);
		if (!empty($locationRow['product_distributor_id']) && empty($locationRow['primary_location'])) {
			$locationCredentialId = getFieldFromId("location_credential_id", "location_credentials", "location_id", $locationId);
			if (empty($locationCredentialId)) {
				$locationId = false;
			} else {
				$locationId = getFieldFromId("location_id", "locations", "product_distributor_id", $locationRow['product_distributor_id'], "primary_location = 1");
			}
		}
		$productInventories = array();
		if (empty($productArray) || empty($locationId)) {
			return $productInventories;
		}

		if (count($productArray) < 1000) {
			$whereProducts = "";
			foreach ($productArray as $productId) {
				if (!empty($productId) && is_numeric($productId)) {
					$whereProducts .= (empty($whereProducts) ? "" : ",") . $productId;
				}
			}
			$resultSet = executeReadQuery("select product_id,sum(quantity) from product_inventories join locations using (location_id) where inactive = 0 and " .
				"product_id in (" . $whereProducts . ") and location_id = ? group by product_id", ProductDistributor::getInventoryLocation($locationId));
			while ($row = getNextRow($resultSet)) {
				$productArray = array_diff($productArray, array($row['product_id']));
				$productInventories[$row['product_id']] = $row['sum(quantity)'];
			}
		} else {
			$limitProductArray = array();
			foreach ($productArray as $thisProductId) {
				$limitProductArray[$thisProductId] = true;
			}
			$resultSet = executeReadQuery("select product_id,sum(quantity) from product_inventories join locations using (location_id) where inactive = 0 and location_id = ? group by product_id", $locationId);
			while ($row = getNextRow($resultSet)) {
				if (!array_key_exists($row['product_id'], $limitProductArray)) {
					continue;
				}
				$productArray = array_diff($productArray, array($row['product_id']));
				$productInventories[$row['product_id']] = $row['sum(quantity)'];
			}
		}

		foreach ($productArray as $productId) {
			$productInventories[$productId] = 0;
		}
		return $productInventories;
	}

	public static function productIsTagged($productId, $productTagId) {
		$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productId,
			"product_tag_id = ? and (start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date >= current_date)", $productTagId);
		return (!empty($productTagLinkId));
	}

	public static function productIsInCategoryGroup($productId, $productCategoryGroupId) {
		if ($GLOBALS['gCategoryGroupProducts'] === false) {
			$GLOBALS['gCategoryGroupProducts'] = getCachedData("is_in_category_group_array", "");
		}
		if ($GLOBALS['gCategoryGroupProducts'] === false || !is_array($GLOBALS['gCategoryGroupProducts'])) {
			$GLOBALS['gCategoryGroupProducts'] = array();
		}
		if (!array_key_exists($productCategoryGroupId, $GLOBALS['gCategoryGroupProducts'])) {
			$GLOBALS['gCategoryGroupProducts'][$productCategoryGroupId] = array();
			$resultSet = executeReadQuery("select product_id from product_category_links where product_category_id in (select product_category_id from product_categories where inactive = 0) and " .
				"product_category_id in (select product_category_id from product_category_group_links where " .
				"product_category_group_id = ? and product_category_group_id in (select product_category_group_id from product_category_groups where inactive = 0))", $productCategoryGroupId);
			while ($row = getNextRow($resultSet)) {
				$GLOBALS['gCategoryGroupProducts'][$productCategoryGroupId][$row['product_id']] = $row['product_id'];
			}
		}
		setCachedData("is_in_category_group_array", "", $GLOBALS['gCategoryGroupProducts'], .5);
		return (array_key_exists($productId, $GLOBALS['gCategoryGroupProducts'][$productCategoryGroupId]));
	}

	public static function getProductAvailabilityText($productRow, $productLocationAvailability = array(), $indexOnly = false, $textOnly = false) {
		$showLocationAvailability = getPreference("RETAIL_STORE_SHOW_LOCATION_AVAILABILITY");
		if (!is_array($productLocationAvailability)) {
			$productLocationAvailability = array();
		}

		if (empty($showLocationAvailability)) {
			return false;
		}
		if (self::$iLocations === false) {
			self::$iLocations = array();
			$resultSet = executeReadQuery("select * from locations where client_id = ? and inactive = 0 and internal_use_only = 0 and not_searchable = 0 and warehouse_location = 0 and product_distributor_id is null", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				self::$iLocations[$row['location_id']] = $row;
			}
		}
		$locationCount = count(self::$iLocations);

		if ($locationCount <= 0) {
			return false;
		}

		/*
         * Location availability text. Options:
         *
         * 1 - Available at all local stores and at least one distributor
         * 2 - Available from distributor(s), but no local stores
         * 3 - Available from some local stores, but not from any distributor
         * 4 - Available from distributor(s) and some (not all) local stores
         * 5 - Available from all local stores, but not from any distributor
         * 6 - Available from some local stores including default store, but not from any distributor
         * 7 - Available from some local stores but NOT default store, but not from any distributor
         * 8 - Available from distributor(s) and some (not all) local stores, including default store
         * 9 - Available from distributor(s) and some (not all) local stores, but NOT default store
         *
         */

		# Get default location
		if (self::$iDefaultLocation === false) {
			if ($GLOBALS['gLoggedIn']) {
				$defaultLocationId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID");
			} else {
				$defaultLocationId = $_COOKIE['default_location_id'];
			}
			$defaultLocationId = getFieldFromId("location_id", "locations", "location_id", $defaultLocationId, "inactive = 0 and internal_use_only = 0 and location_id in (select location_id from shipping_methods where inactive = 0 and internal_use_only = 0 and pickup = 1)");
			if (getPreference("USE_BUSINESS_NAME_AS_LOCATION_DESCRIPTION")) {
				$defaultLocationDescription = getFieldFromId("business_name", "contacts", "contact_id", getFieldFromId("contact_id", "locations", "location_id", $defaultLocationId));
			} else {
				$defaultLocationDescription = getFieldFromId("description", "locations", "location_id", $defaultLocationId);
			}
			self::$iDefaultLocation = $defaultLocationId;
			self::$iDefaultLocationDescription = $defaultLocationDescription;
		} else {
			$defaultLocationId = self::$iDefaultLocation;
			$defaultLocationDescription = self::$iDefaultLocationDescription;
		}

		$locationList = "<ul>";
		$foundAtDefaultLocation = false;
		if (!empty($productRow['product_id']) && (empty($productLocationAvailability) || !array_key_exists($productRow['product_id'], $productLocationAvailability))) {
			$productCatalog = new ProductCatalog();
			$productLocationAvailability = $productCatalog->getLocationAvailability($productRow['product_id']);
		}

		$localCountCalculated = false;
		$localCount = 0;
		if (!empty($productRow['product_id']) && array_key_exists($productRow['product_id'], $productLocationAvailability)) {
			$localCountCalculated = true;
			foreach ($productLocationAvailability[$productRow['product_id']] as $locationId => $thisLocationQuantity) {
				if (!is_numeric($locationId)) {
					continue;
				}
				if ($thisLocationQuantity > 0) {
					$location = self::$iLocations[$locationId]['description'];
					if (!empty($location)) {
						if ($locationId == $defaultLocationId) {
							$foundAtDefaultLocation = true;
						}
						$locationList .= "<li>" . $location . "</li>";
					}
				}
				if ($thisLocationQuantity > 0) {
					$localCount++;
				}
			}
			$locationList .= "</ul>";
		}

		if (self::$iLocationAvailabilityTexts === false || $textOnly) {

			# 1 - Available Everywhere

			if (fragmentExists("retail_store_availability_everywhere")) {
				$availableEverywhere = getFragment("retail_store_availability_everywhere");
				$availableEverywhere = makeHtml($availableEverywhere);
			} else {
				$availableEverywhere = "<p>Available online and for pickup at all stores</p>";
			}

			# 2 - $only Available from Distributors

			if (fragmentExists("retail_store_availability_only_distributors")) {
				$onlyAvailableDistributors = getFragment("retail_store_availability_only_distributors");
				$onlyAvailableDistributors = makeHtml($onlyAvailableDistributors);
			} else {
				$onlyAvailableDistributors = "<p>Ships from our warehouse for local pickup or have it shipped</p>";
			}

			# 3 - Available from some stores

			if (fragmentExists("retail_store_availability_only_selected_stores")) {
				$onlyAvailableSomeLocalStores = getFragment("retail_store_availability_only_selected_stores");
				$onlyAvailableSomeLocalStores = makeHtml($onlyAvailableSomeLocalStores);
			} else {
				if ((!$localCountCalculated && $locationCount > 10) || ($localCountCalculated && $localCount > 10) || $textOnly) {
					$onlyAvailableSomeLocalStores = "<p>Available at selected stores</p>";
				} elseif ($locationCount > 1) {
					$onlyAvailableSomeLocalStores = "<p>Available at the following store(s):</p>%location_list%";
				} else {
					$onlyAvailableSomeLocalStores = "<p>Available at our store</p>";
				}
			}

			# 4 - Available from distributors and some local stores

			if (fragmentExists("retail_store_availability_distributors_selected_stores")) {
				$availableDistributorsSomeLocalStores = getFragment("retail_store_availability_distributors_selected_stores");
				$availableDistributorsSomeLocalStores = makeHtml($availableDistributorsSomeLocalStores);
			} else {
				if ((!$localCountCalculated && $locationCount > 10) || ($localCountCalculated && $localCount > 10) || $textOnly) {
					$availableDistributorsSomeLocalStores = "<p>Available online and at selected stores; have it shipped or pickup locally</p>";
				} elseif ($locationCount > 1) {
					$availableDistributorsSomeLocalStores = "<p>Available online and at selected stores; have it shipped or pickup locally at the following stores:</p>%location_list%";
				} else {
					$availableDistributorsSomeLocalStores = "<p>Available at our store</p>";
				}
			}

			# 5 - Available at all local stores

			if (fragmentExists("retail_store_availability_local_stores")) {
				$onlyAvailableLocalStores = getFragment("retail_store_availability_local_stores");
				$onlyAvailableLocalStores = makeHtml($onlyAvailableLocalStores);
			} else {
				$onlyAvailableLocalStores = "<p>Available " . (count(self::$iLocations) == 1 ? "in store" : "at all stores") . (empty($defaultLocationId) || count(self::$iLocations) == 1 ? "" : ($textOnly || $foundAtDefaultLocation ? ", including <span class='default-location'>%default_location_description%</span>" : "")) . "</p>";
			}

			# 6 - Available at some local stores, including default

			if (fragmentExists("retail_store_availability_only_selected_stores_including_default")) {
				$onlyAvailableSomeLocalStoresIncludingDefault = getFragment("retail_store_availability_only_selected_stores_including_default");
				$onlyAvailableSomeLocalStoresIncludingDefault = makeHtml($onlyAvailableSomeLocalStoresIncludingDefault);
			} else {
				$onlyAvailableSomeLocalStoresIncludingDefault = "<p>Available at selected stores, including <span class='default-location'>%default_location_description%</span></p>";
			}

			# 7 - Available at some local stores, but NOT default

			if (fragmentExists("retail_store_availability_only_selected_stores_not_default")) {
				$onlyAvailableSomeLocalStoresNotDefault = getFragment("retail_store_availability_only_selected_stores_not_default");
				$onlyAvailableSomeLocalStoresNotDefault = makeHtml($onlyAvailableSomeLocalStoresNotDefault);
			} else {
				$onlyAvailableSomeLocalStoresNotDefault = "<p>Available at selected stores, but NOT <span class='default-location'>%default_location_description%</span></p>";
			}

			# 8 - Available from distributors and some local stores, including default

			if (fragmentExists("retail_store_availability_distributors_selected_stores_including_default")) {
				$availableDistributorsSomeLocalStoresIncludingDefault = getFragment("retail_store_availability_distributors_selected_stores_including_default");
				$availableDistributorsSomeLocalStoresIncludingDefault = makeHtml($availableDistributorsSomeLocalStoresIncludingDefault);
			} else {
				if ((!$localCountCalculated && $locationCount > 10) || ($localCountCalculated && $localCount > 10) || $textOnly) {
					$availableDistributorsSomeLocalStoresIncludingDefault = "<p>Available online and at selected stores; have it shipped or pickup locally, including at <span class='default-location'>%default_location_description%</span></p>";
				} elseif ($locationCount > 1) {
					$availableDistributorsSomeLocalStoresIncludingDefault = "<p>Available online and at selected stores; have it shipped or pickup locally at the following stores:</p>%location_list%";
				} else {
					$availableDistributorsSomeLocalStoresIncludingDefault = "<p>Available at our store</p>";
				}
			}

			# 9 - Available from distributors and some local stores, but NOT default

			if (fragmentExists("retail_store_availability_distributors_selected_stores_not_default")) {
				$availableDistributorsSomeLocalStoresNotDefault = getFragment("retail_store_availability_distributors_selected_stores_not_default");
				$availableDistributorsSomeLocalStoresNotDefault = makeHtml($availableDistributorsSomeLocalStoresNotDefault);
			} else {
				if ((!$localCountCalculated && $locationCount > 10) || ($localCountCalculated && $localCount > 10) || $textOnly) {
					$availableDistributorsSomeLocalStoresNotDefault = "<p>Available online and at selected stores; have it shipped or pickup locally, but NOT at <span class='default-location'>%default_location_description%</span></p>";
				} elseif ($locationCount > 1) {
					$availableDistributorsSomeLocalStoresNotDefault = "<p>Available online and at selected stores; have it shipped or pickup locally at the following stores:</p>%location_list%";
				} else {
					$availableDistributorsSomeLocalStoresNotDefault = "<p>Available at our store</p>";
				}
			}

			self::$iLocationAvailabilityTexts = array("1" => $availableEverywhere, "2" => $onlyAvailableDistributors, "3" => $onlyAvailableSomeLocalStores,
				"4" => $availableDistributorsSomeLocalStores, "5" => $onlyAvailableLocalStores, "6" => $onlyAvailableSomeLocalStoresIncludingDefault, "7" => $onlyAvailableSomeLocalStoresNotDefault,
				"8" => $availableDistributorsSomeLocalStoresIncludingDefault, "9" => $availableDistributorsSomeLocalStoresNotDefault);
		}

		$locationAvailabilityTexts = self::$iLocationAvailabilityTexts;
		if ($textOnly) {
			foreach ($locationAvailabilityTexts as $index => $thisText) {
				$locationAvailabilityTexts[$index] = str_replace("%default_location_description%", $defaultLocationDescription, str_replace("%location_list%", $locationList, $thisText));
			}
			return $locationAvailabilityTexts;
		}

		$distributorInventory = $productLocationAvailability[$productRow['product_id']]['distributor'];
		$localCount = 0;
		$localOutOfStock = false;

		if (array_key_exists($productRow['product_id'], $productLocationAvailability)) {
			foreach ($productLocationAvailability[$productRow['product_id']] as $thisLocationId => $thisLocationQuantity) {
				if (!is_numeric($thisLocationId)) {
					continue;
				}
				if ($thisLocationQuantity <= 0) {
					$localOutOfStock = true;
				} else {
					$localCount++;
				}
			}
		}

		if ($localCount <= 0 && $distributorInventory <= 0) {
			$locationAvailabilityIndex = 0;
		} elseif ($localCount <= 0) {
			$locationAvailabilityIndex = "2";
		} else {
			if ($localOutOfStock) {
				$locationAvailabilityIndex = ($distributorInventory <= 0 ? "3" : "4");
				if (!empty($defaultLocationId)) {
					if ($locationAvailabilityIndex == "3") {
						$locationAvailabilityIndex = ($foundAtDefaultLocation ? "6" : "7");
					} else {
						$locationAvailabilityIndex = ($foundAtDefaultLocation ? "8" : "9");
					}
				}
			} else {
				$locationAvailabilityIndex = ($distributorInventory <= 0 ? "5" : "1");
			}
		}
		if ($indexOnly) {
			return $locationAvailabilityIndex;
		}
		return str_replace("%default_location_description%", $defaultLocationDescription, str_replace("%location_list%", $locationList, $locationAvailabilityTexts[$locationAvailabilityIndex]));
	}

	public static function mergeManufacturers($duplicateProductManufacturerCode, $productManufacturerCode, $allClients = true) {
		$tableColumnId = "";
		$resultSet = executeQuery("select table_column_id from table_columns where table_id = " .
			"(select table_id from tables where table_name = 'product_manufacturers' and database_definition_id = " .
			"(select database_definition_id from database_definitions where database_name = ?)) and column_definition_id = " .
			"(select column_definition_id from column_definitions where column_name = 'product_manufacturer_id')", $GLOBALS['gPrimaryDatabase']->getName());
		if ($row = getNextRow($resultSet)) {
			$tableColumnId = $row['table_column_id'];
		}
		$deleteTables = array();
		$resultSet = executeQuery("select (select table_name from tables where table_id = table_columns.table_id) table_name," .
			"(select column_name from column_definitions where column_definition_id = table_columns.column_definition_id) column_name " .
			"from table_columns where table_column_id in " .
			"(select table_column_id from foreign_keys where referenced_table_column_id = ?)", $tableColumnId);
		while ($row = getNextRow($resultSet)) {
			if ($row['table_name'] == "products" || $row['table_name'] == "distributor_product_codes") {
				continue;
			}
			$deleteTables[] = $row;
		}

		$clientSet = executeReadQuery("select * from clients" . ($allClients ? "" : " where client_id = " . $GLOBALS['gClientId']));
		while ($clientRow = getNextRow($clientSet)) {
			$duplicateProductManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_code", $duplicateProductManufacturerCode, "client_id = ?", $clientRow['client_id']);
			if (empty($duplicateProductManufacturerId)) {
				continue;
			}
			$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_code", $productManufacturerCode, "client_id = ?", $clientRow['client_id']);
			if (empty($productManufacturerId)) {
				executeQuery("update product_manufacturers set product_manufacturer_code = ? where product_manufacturer_id = ?", $productManufacturerCode, $duplicateProductManufacturerId);
				continue;
			}
			$resultSet = executeQuery("update products set time_changed = now(), product_manufacturer_id = ? where product_manufacturer_id = ?", $productManufacturerId, $duplicateProductManufacturerId);
			if (!empty($resultSet['sql_error'])) {
				continue;
			}
			$resultSet = executeQuery("update product_manufacturers set parent_product_manufacturer_id = ? where parent_product_manufacturer_id = ? and product_manufacturer_id <> ? and product_manufacturer_id <> ?",
				$productManufacturerId, $duplicateProductManufacturerId, $productManufacturerId, $duplicateProductManufacturerId);
			if (!empty($resultSet['sql_error'])) {
				continue;
			}
			executeQuery("update product_distributor_conversions set primary_identifier = ? where primary_identifier = ? and table_name = 'product_manufacturers' and client_id = ?", $productManufacturerId, $duplicateProductManufacturerId, $GLOBALS['gClientId']);
			foreach ($deleteTables as $thisTable) {
				$resultSet = executeQuery("delete from " . $thisTable['table_name'] . " where " . $thisTable['column_name'] . " = ?", $duplicateProductManufacturerId);
				if (!empty($resultSet['sql_error'])) {
					continue 2;
				}
			}
			$contactId = getFieldFromId("contact_id", "product_manufacturers", "product_manufacturer_id", $duplicateProductManufacturerId, "client_id = ?", $clientRow['client_id']);
			executeQuery("delete from product_manufacturers where product_manufacturer_id = ?", $duplicateProductManufacturerId);
			executeQuery("delete from phone_numbers where contact_id = ?", $contactId);
			executeQuery("delete from contacts where contact_id = ?", $contactId);
		}
	}

	public static function copyProductCategoryAddons($productId) {
		$resultSet = executeQuery("select * from product_category_addons where product_category_id in (select product_category_id from product_category_links where product_id = ?)", $productId);
		$productCatalog = new ProductCatalog();
		while ($row = getNextRow($resultSet)) {
			if (empty($row['sale_price'])) {
				$row['sale_price'] = 0;
			}
			if (!empty($row['percentage'])) {
				$salePriceInfo = $productCatalog->getProductSalePrice($productId);
				if (is_array($salePriceInfo)) {
					$salePrice = $salePriceInfo['sale_price'];
				} else {
					$salePrice = $salePriceInfo;
				}
				$percentAddonPrice = round(($row['percentage'] / 100) * $salePrice, 2);
				if ($percentAddonPrice > $row['sale_price']) {
					$row['sale_price'] = $percentAddonPrice;
				}
			}
			$productAddonRow = getRowFromId("product_addons", "product_id", $productId, "description = ? and group_description <=> ?", $row['description'], $row['group_description']);
			if (empty($productAddonRow)) {
				if (empty($row['inactive'])) {
					executeQuery("insert into product_addons (product_id,description,group_description,manufacturer_sku,form_definition_id,inventory_product_id,maximum_quantity,sale_price,sort_order,internal_use_only) values (?,?,?,?,?, ?,?,?,?,?)",
						$productId, $row['description'], $row['group_description'], $row['manufacturer_sku'], $row['form_definition_id'], $row['inventory_product_id'], $row['maximum_quantity'], $row['sale_price'], $row['sort_order'], $row['internal_use_only']);
				}
			} elseif ($productAddonRow['sale_price'] != $row['sale_price'] || $productAddonRow['inactive'] != $row['inactive'] || $productAddonRow['internal_use_only'] != $row['internal_use_only'] ||
				$productAddonRow['manufacturer_sku'] != $row['manufacturer_sku'] || $productAddonRow['form_definition_id'] != $row['form_definition_id'] ||
				$productAddonRow['inventory_product_id'] != $row['inventory_product_id'] || $productAddonRow['maximum_quantity'] != $row['maximum_quantity']) {
				executeQuery("update product_addons set manufacturer_sku = ?,form_definition_id = ?,inventory_product_id = ?,maximum_quantity = ?,sale_price = ?,internal_use_only = ?,inactive = ? where product_addon_id = ?",
					$row['manufacturer_sku'], $row['form_definition_id'], $row['inventory_product_id'], $row['maximum_quantity'], $row['sale_price'],
					(empty($productAddonRow['internal_use_only']) ? $row['internal_use_only'] : $productAddonRow['internal_use_only']),
					(empty($productAddonRow['inactive']) ? $row['inactive'] : $productAddonRow['inactive']), $productAddonRow['product_addon_id']);
			}
		}
	}

	public static function getProductUpdateFields() {
		$updateFields = array();
		$updateFields['description'] = array("product_update_field_code" => "description", "description" => "Description", "update_setting" => "M", "table_name" => "products");
		$updateFields['detailed_description'] = array("product_update_field_code" => "detailed_description", "description" => "Detailed Description", "update_setting" => "M", "table_name" => "products");
		$updateFields['weight'] = array("product_update_field_code" => "weight", "description" => "Weight", "update_setting" => "Y", "table_name" => "product_data");
		$updateFields['length'] = array("product_update_field_code" => "length", "description" => "Length", "update_setting" => "Y", "table_name" => "product_data");
		$updateFields['width'] = array("product_update_field_code" => "width", "description" => "Width", "update_setting" => "Y", "table_name" => "product_data");
		$updateFields['height'] = array("product_update_field_code" => "height", "description" => "Height", "update_setting" => "Y", "table_name" => "product_data");
		$updateFields['model'] = array("product_update_field_code" => "model", "description" => "Model", "update_setting" => "Y", "table_name" => "product_data");
		$updateFields['product_manufacturer_id'] = array("product_update_field_code" => "product_manufacturer_id", "description" => "Manufacturer", "update_setting" => "Y", "table_name" => "products");
		$updateFields['manufacturer_sku'] = array("product_update_field_code" => "manufacturer_sku", "description" => "Manufacturer SKU", "update_setting" => "Y", "table_name" => "product_data");
		$updateFields['product_categories'] = array("product_update_field_code" => "product_categories", "description" => "Product Categories", "update_setting" => "M");
		$updateFields['product_facets'] = array("product_update_field_code" => "product_facets", "description" => "Product Facets", "update_setting" => "M");
		$updateFields['list_price'] = array("product_update_field_code" => "list_price", "description" => "List Price", "update_setting" => "Y", "table_name" => "products");
		$updateFields['minimum_price'] = array("product_update_field_code" => "minimum_price", "description" => "Minimum Price", "update_setting" => "Y", "table_name" => "product_data");
		$updateFields['unit_id'] = array("product_update_field_code" => "unit_id", "description" => "Unit of Measure", "update_setting" => "Y", "table_name" => "product_data");
		$updateFields['remote_identifier'] = array("product_update_field_code" => "remote_identifier", "description" => "Remote Identifier", "update_setting" => "Y", "table_name" => "products", "internal_use_only" => true);
		$updateFields['serializable'] = array("product_update_field_code" => "serializable", "description" => "Serializable", "update_setting" => "Y", "table_name" => "products", "internal_use_only" => true);
		$updateFields['link_name'] = array("product_update_field_code" => "link_name", "description" => "Link Name", "update_setting" => "M", "table_name" => "products", "internal_use_only" => true);
		$updateFields['manufacturer_advertised_price'] = array("product_update_field_code" => "manufacturer_advertised_price", "description" => "MAP", "update_setting" => "Y", "table_name" => "product_data", "internal_use_only" => true);
		$updateFields['upc_code'] = array("product_update_field_code" => "upc_code", "description" => "UPC Code", "update_setting" => "Y", "table_name" => "product_data", "internal_use_only" => true);
		return $updateFields;
	}

	public static function mergeProducts($productId, $duplicateProductId) {
		$updateSubtables = array("order_items", "distributor_order_items", "wish_list_items", "shopping_cart_items", "product_availability_notifications", "product_offers");
		$deleteSubtables = array("product_data", "product_addons", "product_category_links", "product_change_details", "product_restrictions", "product_sale_notifications",
			"product_contributors", "product_inventories", "product_reviews", "product_vendors", "product_search_word_values", "product_payment_methods", "product_prices",
			"product_shipping_methods", "product_tag_links", "product_facet_values", "distributor_product_codes", "product_serial_numbers", "product_inventory_notifications",
			"product_images", "quotations", "related_products", "shopping_cart_items", "wish_list_items", "product_remote_images", "product_group_variants", "product_custom_fields",
			"product_sale_prices", "product_view_log", "product_distributor_dropship_prohibitions", "distributor_order_products", "product_map_overrides", "cost_difference_log",
			"product_videos", "search_group_products", "related_products");
		$productRow = getRowFromId("products", "product_id", $productId, "client_id is not null");
		$duplicateProductId = getFieldFromId("product_id", "product_data", "product_id", $duplicateProductId, "client_id = ?", $productRow['client_id']);
		if (empty($duplicateProductId) || empty($productRow)) {
			return array("results" => "Invalid Product(s)");
		}
		if ($duplicateProductId == $productId) {
			return array("results" => "Product and duplicate are the same product");
		}
		$GLOBALS['gPrimaryDatabase']->startTransaction();
		foreach ($updateSubtables as $thisTable) {
			executeQuery("update ignore " . $thisTable . " set product_id = ? where product_id = ?", $productId, $duplicateProductId);
			$resultSet = executeQuery("delete from " . $thisTable . " where product_id = ?", $duplicateProductId);
			if (!empty($resultSet['sql_error'])) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				return array("results" => getSystemMessage("basic", $resultSet['sql_error']), "console" => $resultSet['sql_error']);
			}
		}
		$resultSet = executeQuery("select product_inventory_id from product_inventories where product_id = ?", $duplicateProductId);
		while ($row = getNextRow($resultSet)) {
			while (true) {
				$deleteSet = executeQuery("delete from product_inventory_log where product_inventory_id = ? limit 10000", $row['product_inventory_id']);
				if (!empty($deleteSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					return array("results" => getSystemMessage("basic", $deleteSet['sql_error']), "console" => $deleteSet['sql_error']);
				}
				if ($deleteSet['affected_rows'] == 0) {
					break;
				}
			}
		}
		$resultSet = executeQuery("update ignore product_addons set product_id = ? where product_id = ?", $productId, $duplicateProductId);
		if (!empty($resultSet['sql_error'])) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return array("results" => getSystemMessage("basic", $resultSet['sql_error']), "console" => $resultSet['sql_error']);
		}
		$resultSet = executeQuery("select * from product_addons where product_id = ? order by product_addon_id desc", $productId);
		while ($row = getNextRow($resultSet)) {
			while (true) {
				$productAddonId = getFieldFromId("product_addon_id", "product_addons", "product_addon_id", $row['product_addon_id']);
				if (empty($productAddonId)) {
					break;
				}
				if (!empty($row['group_description'])) {
					$duplicateProductAddonId = getFieldFromId("product_addon_id", "product_addons", "product_id", $productId,
						"description = ? and group_description = ? and product_addon_id <> ?", $row['description'], $row['group_description'], $productAddonId);
				} else {
					$duplicateProductAddonId = getFieldFromId("product_addon_id", "product_addons", "product_id", $productId,
						"description = ? and group_description is null and product_addon_id <> ?", $row['description'], $productAddonId);
				}
				if (empty($duplicateProductAddonId)) {
					break;
				}
				$result = self::mergeProductAddons($productAddonId, $duplicateProductAddonId);
				if ($result !== true) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					return array("results" => getSystemMessage("basic", $result), "console" => $result);
				}
			}
		}
		$resultSet = executeQuery("update ignore distributor_product_codes set product_id = ? where product_id = ?", $productId, $duplicateProductId);
		if (!empty($resultSet['sql_error'])) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return array("results" => getSystemMessage("basic", $resultSet['sql_error']), "console" => $resultSet['sql_error']);
		}
		$resultSet = executeQuery("update ignore related_products set product_id = ? where product_id = ?", $productId, $duplicateProductId);
		if (!empty($resultSet['sql_error'])) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return array("results" => getSystemMessage("basic", $resultSet['sql_error']), "console" => $resultSet['sql_error']);
		}
		$resultSet = executeQuery("update ignore related_products set associated_product_id = ? where associated_product_id = ?", $productId, $duplicateProductId);
		if (!empty($resultSet['sql_error'])) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return array("results" => getSystemMessage("basic", $resultSet['sql_error']), "console" => $resultSet['sql_error']);
		}
		$resultSet = executeQuery("delete from related_products where associated_product_id = ?", $duplicateProductId);
		if (!empty($resultSet['sql_error'])) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return array("results" => getSystemMessage("basic", $resultSet['sql_error']), "console" => $resultSet['sql_error']);
		}
		foreach ($deleteSubtables as $thisTable) {
			$resultSet = executeQuery("delete from " . $thisTable . " where product_id = ?", $duplicateProductId);
			if (!empty($resultSet['sql_error'])) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				return array("results" => getSystemMessage("basic", $resultSet['sql_error']), "console" => $resultSet['sql_error']);
			}
		}
		executeQuery("delete from potential_product_duplicates where product_id in (?,?) or duplicate_product_id in (?,?)",
			$duplicateProductId, $productId, $duplicateProductId, $productId);
		executeQuery("delete from custom_field_data where primary_identifier = ? and custom_field_id in (select custom_field_id from custom_fields
            where custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'PRODUCTS'))",
			$duplicateProductId);
		$resultSet = executeQuery("delete from products where product_id = ?", $duplicateProductId);
		if (!empty($resultSet['sql_error'])) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			return array("results" => getSystemMessage("basic", $resultSet['sql_error']), "console" => $resultSet['sql_error']);
		}
		executeQuery("update products set reindex = 1 where product_id = ?", $productId);
		executeQuery("insert into change_log (client_id,user_id,table_name,column_name,primary_identifier,foreign_key_identifier," .
			"old_value,new_value,notes) values (?,?,?,?,?, ?,?,?,?)", array($productRow['client_id'], $GLOBALS['gUserId'], "products", "product_id",
			$duplicateProductId, "", "", "Merged into product ID " . $productId, (empty($_SESSION['original_user_id']) ? "" : "Simulated by " . getUserDisplayName($_SESSION['original_user_id']))));
		$GLOBALS['gPrimaryDatabase']->commitTransaction();
		return true;
	}

	public static function mergeProductAddons($productAddonId, $duplicateProductAddonId) {
		$updateSubtables = array("order_item_addons", "recurring_payment_order_item_addons", "shopping_cart_item_addons");
		foreach ($updateSubtables as $thisTable) {
			executeQuery("update ignore " . $thisTable . " set product_addon_id = ? where product_addon_id = ?", $productAddonId, $duplicateProductAddonId);
			$resultSet = executeQuery("delete from " . $thisTable . " where product_addon_id = ?", $duplicateProductAddonId);
			if (!empty($resultSet['sql_error'])) {
				return $resultSet['sql_error'];
			}
		}
		$resultSet = executeQuery("delete from product_addons where product_addon_id = ?", $duplicateProductAddonId);
		if (!empty($resultSet['sql_error'])) {
			return $resultSet['sql_error'];
		}
		return true;
	}

	public static function reindexProducts($productIdArray) {
		if (!is_array($productIdArray)) {
			$productIdArray = array_filter(array($productIdArray));
		}
		if (empty($productIdArray)) {
			return;
		}
		$testProductId = $productIdArray[0];
		$inactiveClientId = getFieldFromId("client_id", "clients", "inactive", "1", "client_id in (select client_id from products where product_id = ?)", $testProductId);
		if (!empty($inactiveClientId)) {
			return;
		}
		ProductCatalog::getStopWords();
		$productSearchFields = array();
		$resultSet = executeQuery("select * from product_search_fields");
		while ($row = getNextRow($resultSet)) {
			$dataTableName = "";
			$descriptionColumn = "";
			if (substr($row['column_name'], -3) == "_id") {
				$dataTableName = getFieldFromId("table_name", "tables", "table_id", getFieldFromId("table_id", "table_columns", "column_definition_id",
					getFieldFromId("column_definition_id", "column_definitions", "column_name", $row['column_name']), "primary_table_key = 1"));
				if (!empty($dataTableName)) {
					if ($GLOBALS['gPrimaryDatabase']->fieldExists($dataTableName, "description")) {
						$descriptionColumn = "description";
					} else {
						$columnInformation = $GLOBALS['gPrimaryDatabase']->getTableColumns($dataTableName);
						foreach ($columnInformation as $thisColumn) {
							if (substr($thisColumn['COLUMN_TYPE'], 0, 7) == "varchar" && substr($thisColumn['COLUMN_NAME'], -5) != "_code") {
								$descriptionColumn = $thisColumn['COLUMN_NAME'];
								break;
							}
						}
						if (empty($descriptionColumn)) {
							foreach ($columnInformation as $thisColumn) {
								if (substr($thisColumn['COLUMN_TYPE'], 0, 7) == "varchar") {
									$descriptionColumn = $thisColumn['COLUMN_NAME'];
									break;
								}
							}
						}
					}
				}
			}
			if (empty($descriptionColumn)) {
				$dataTableName = "";
			}
			$row['data_table_name'] = $dataTableName;
			$row['description_column'] = $descriptionColumn;
			$productSearchFields[] = $row;
		}

		$productSearchFieldSettings = array();
		$resultSet = executeQuery("select * from product_search_field_settings where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$productSearchFieldSettings[$row['product_search_field_id']] = $row;
		}
		$count = 0;

		foreach ($productIdArray as $productId) {
			$GLOBALS['gPrimaryDatabase']->startTransaction();
			executeQuery("delete from product_search_word_values where product_id = ?", $productId);
			$wordValues = array();
			foreach ($productSearchFields as $thisProductSearchField) {
				$searchMultiplier = $productSearchFieldSettings[$thisProductSearchField['product_search_field_id']]['search_multiplier'];
				if (strlen($searchMultiplier) == 0) {
					$searchMultiplier = 1;
				}
				if (empty($searchMultiplier)) {
					continue;
				}
				$joinColumnName = (empty($thisProductSearchField['join_table_name']) ? "" : getFieldFromId("column_name", "column_definitions", "column_definition_id", getFieldFromId("column_definition_id", "table_columns", "table_id",
					getFieldFromId("table_id", "tables", "table_name", $thisProductSearchField['join_table_name']), "primary_table_key = 1")));

				$resultSet = executeQuery("select * from " . $thisProductSearchField['table_name'] .
					(empty($thisProductSearchField['join_table_name']) ? "" : " join " . $thisProductSearchField['join_table_name'] . " using (" . $joinColumnName . ")") .
					(empty($thisProductSearchField['data_table_name']) ? "" : " join " . $thisProductSearchField['data_table_name'] . " using (" . $thisProductSearchField['column_name'] . ")") .
					" where product_id = ?", $productId);
				while ($row = getNextRow($resultSet)) {
					$searchField = (empty($thisProductSearchField['description_column']) ? $thisProductSearchField['column_name'] : $thisProductSearchField['description_column']);
					$wordListInfo = ProductCatalog::getSearchWords($row[$searchField]);
					$wordList = $wordListInfo['search_words'];
					$dataTypeSearchMultiplier = (empty($row['search_multiplier']) ? 1 : $row['search_multiplier']);
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
							$wordValues[$thisWord] = 0;
						}
						$wordValues[$thisWord] += ($searchMultiplier * $dataTypeSearchMultiplier);
					}
				}
			}

			$errorFound = false;
			foreach ($wordValues as $thisWord => $wordValue) {
				if (empty($wordValue) || strlen($thisWord) <= 1) {
					continue;
				}
				$count = 0;
				while (true) {
					$productSearchWordId = getFieldFromId("product_search_word_id", "product_search_words", "search_term", strtolower($thisWord));
					if (empty($productSearchWordId)) {
						$insertSet = executeQuery("insert into product_search_words (client_id,search_term) values (?,?)", $GLOBALS['gClientId'], strtolower($thisWord));
						$productSearchWordId = $insertSet['insert_id'];
					}
					$productSearchWordValueId = getFieldFromId("product_search_word_value_id", "product_search_word_values", "product_search_word_id", $productSearchWordId, "product_id = ?", $productId);
					if (empty($productSearchWordValueId)) {
						executeQuery("insert into product_search_word_values (product_search_word_id,product_id,search_value) values (?,?,?)",
							$productSearchWordId, $productId, $wordValue);
					}
					$count++;
					$alternateWord = ltrim($thisWord, "0");
					if (strlen($alternateWord) <= 1 || strlen($alternateWord) == strlen($thisWord) || $count > 2) {
						break;
					}
					$thisWord = $alternateWord;
				}
			}
			executeQuery("update products set reindex = 0 where product_id = ?", $productId);
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
			$count++;
		}
		return $count;
	}

	public static function getSearchWords($searchString) {
		$wordCharacters = array(".", ",", "!", "?", "(", ")", "[", "]", "{", "}", "=", "+", "'", "\"", ":", ";", "/", "<", ">", "’", "”", "“", "‘", "`");
		$removeCharacters = array("-", "#", "@", "$", "%", "*", "|");
		if ($GLOBALS['gSearchTermSynonyms'] === false) {
			ProductCatalog::getSearchTermSynonyms();
		}
		ProductCatalog::getStopWords();
		foreach ($wordCharacters as $thisCharacter) {
			$searchString = str_replace($thisCharacter, " ", $searchString);
		}
		foreach ($removeCharacters as $thisCharacter) {
			$searchString = str_replace($thisCharacter, "", $searchString);
		}
		$wordList = array();
		$candidateWordList = explode(" ", $searchString);
		foreach ($candidateWordList as $thisPart) {
			$searchTermSynonymRow = $GLOBALS['gSearchTermSynonyms'][strtoupper($thisPart)];
			if (!empty($searchTermSynonymRow)) {
				$thisPart = $searchTermSynonymRow['search_term'];
			}
			foreach ($wordCharacters as $thisCharacter) {
				$searchString = str_replace($thisCharacter, " ", $searchString);
			}
			foreach ($removeCharacters as $thisCharacter) {
				$searchString = str_replace($thisCharacter, "", $searchString);
			}
			$thisWordList = explode(" ", $thisPart);
			foreach ($thisWordList as $thisWord) {
				if (!in_array($thisWord, $wordList)) {
					$wordList[] = $thisWord;
				}
			}
		}

		$displaySearchText = "";
		$searchWords = array();
		foreach ($wordList as $thisPart) {
			$thisPart = strtolower(trim($thisPart));
			if (empty($thisPart)) {
				continue;
			}
			if (array_key_exists($thisPart, $GLOBALS['gStopWords']) || strlen($thisPart) < 2) {
				$displaySearchText .= (empty($displaySearchText) ? "" : " ") . "<span class='strikethrough'>" . $thisPart . "</span>";
				continue;
			}
			$displaySearchText .= (empty($displaySearchText) ? "" : " ") . $thisPart;
			$thisPart = trim(Inflect::singularize($thisPart));
			if (array_key_exists($thisPart, $GLOBALS['gStopWords']) || strlen($thisPart) < 2) {
				$displaySearchText .= (empty($displaySearchText) ? "" : " ") . "<span class='strikethrough'>" . $thisPart . "</span>";
				continue;
			}
			$searchWords[] = $thisPart;
		}
		// mahathi come 2
		return array("display_search_text" => $displaySearchText, "search_words" => $searchWords);
	}

	public static function getSearchTermSynonyms() {
		$resultSet = executeReadQuery("select search_term_synonyms.search_term,search_term_synonym_redirects.search_term redirected_search_term," .
			"(select group_concat(product_category_id) from search_term_synonym_product_categories where search_term_synonym_id = search_term_synonyms.search_term_synonym_id) product_category_ids," .
			"(select group_concat(product_department_id) from search_term_synonym_product_departments where search_term_synonym_id = search_term_synonyms.search_term_synonym_id) product_department_ids," .
			"(select group_concat(product_manufacturer_id) from search_term_synonym_product_manufacturers where search_term_synonym_id = search_term_synonyms.search_term_synonym_id) product_manufacturer_ids " .
			"from search_term_synonyms join search_term_synonym_redirects using (search_term_synonym_id) where " .
			"(search_term_synonyms.domain_name = ? or search_term_synonyms.domain_name is null) and search_term_synonyms.client_id = ?",
			$_SERVER['HTTP_HOST'], $GLOBALS['gClientId']);
		$GLOBALS['gSearchTermSynonyms'] = array();
		while ($row = getNextRow($resultSet)) {
			$row['product_category_ids'] = array_filter(explode(",", $row['product_category_ids']));
			$row['product_department_ids'] = array_filter(explode(",", $row['product_department_ids']));
			$row['product_manufacturer_ids'] = array_filter(explode(",", $row['product_manufacturer_ids']));
			$GLOBALS['gSearchTermSynonyms'][strtoupper($row['redirected_search_term'])] = $row;
		}
		// mahathi come 3
	}

	public static function importProductFromUPC($upcCode, $parameters = array()) {
		$returnArray = array();
		$useTransaction = empty($parameters['no_transaction']);
		unset($parameters['no_transaction']);
		$parameters['connection_key'] = "760C0DCAB2BD193B585EB9734F34B3B6";
		$parameters['upc_code'] = $upcCode;
		$hostUrl = "https://shootingsports.coreware.com/api.php?action=get_product_information";
		$postParameters = "";
		foreach ($parameters as $parameterKey => $parameterValue) {
			$postParameters .= (empty($postParameters) ? "" : "&") . $parameterKey . "=" . rawurlencode($parameterValue);
		}
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postParameters);
		curl_setopt($ch, CURLOPT_URL, $hostUrl);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, ($GLOBALS['gCurlTimeout'] * 4));
		$response = curl_exec($ch);
		$productInformation = json_decode($response, true);
		if (!is_array($productInformation)) {
			return $returnArray;
		}
		if (!array_key_exists('product_information', $productInformation)) {
			$returnArray['error_message'] = "Product not found in Coreware Catalog";
			return $returnArray;
		}
		$productInformation = $productInformation['product_information'];
		if (!empty($parameters['return_data_only'])) {
			return $productInformation;
		}

		if ($useTransaction) {
			$GLOBALS['gPrimaryDatabase']->startTransaction();
		}
		$GLOBALS['gChangeLogNotes'] = "UPC " . $upcCode . " imported from Coreware catalog";
		$copyFields = array("description", "detailed_description", "base_cost", "list_price", "low_inventory_quantity", "low_inventory_surcharge_amount", "virtual_product", "cart_minimum", "cart_maximum", "order_maximum", "serializable");
		$productData = array("client_id" => $GLOBALS['client_id'], "date_created" => date("Y-m-d"), "reindex" => 1);
		foreach ($copyFields as $fieldName) {
			$productData[$fieldName] = $productInformation[$fieldName];
		}
		$useProductNumber = 0;
		do {
			$useProductCode = substr($productInformation['product_code'], 0, 95) . (empty($useProductNumber) ? "" : "_" . $useProductNumber);
			$dupProductId = getFieldFromId("product_id", "products", "product_code", $useProductCode);
			$useProductNumber++;
		} while (!empty($dupProductId));
		$productData['product_code'] = $useProductCode;

		$useProductNumber = 0;
		do {
			$useLinkName = makeCode($productInformation['description'], array("use_dash" => true, "lowercase" => true)) . (empty($useProductNumber) ? "" : "-" . $useProductNumber);
			$dupProductId = getFieldFromId("product_id", "products", "link_name", $useLinkName);
			$useProductNumber++;
		} while (!empty($dupProductId));
		$productData['link_name'] = $useLinkName;
		$productData['product_manufacturer_id'] = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_code", $productInformation['product_manufacturer_code']);
		$productData['remote_identifier'] = $productInformation['product_id'];

		$productDataTable = new DataTable("products");
		if (!$productId = $productDataTable->saveRecord(array("name_values" => $productData, "primary_id" => ""))) {
			$returnArray['error_message'] = $productDataTable->getErrorMessage();
			if ($useTransaction) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			}
			return $returnArray;
		}
		// Sample: 602686441100
		$productData = array("product_id" => $productId, "client_id" => $GLOBALS['gClientId']);
		$copyFields = array("model", "upc_code", "isbn", "isbn_13", "manufacturer_sku", "minimum_price", "manufacturer_advertised_price", "width", "length", "height", "weight");
		foreach ($copyFields as $fieldName) {
			$productData[$fieldName] = $productInformation[$fieldName];
		}
		$productDataTable = new DataTable("product_data");
		if (!$productDataId = $productDataTable->saveRecord(array("name_values" => $productData, "primary_id" => ""))) {
			$returnArray['error_message'] = $productDataTable->getErrorMessage();
			if ($useTransaction) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			}
			return $returnArray;
		}

		# add categories
		$productCategoryCodes = explode(",", $productInformation['product_category_codes']);
		foreach ($productCategoryCodes as $productCategoryCode) {
			$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_code", $productCategoryCode);
			if (!empty($productCategoryId)) {
				$productCategoryLinksDataTable = new DataTable("product_category_links");
				$productCategoryLinksDataTable->saveRecord(array("name_values" => array("product_category_id" => $productCategoryId, "product_id" => $productId)));
			}
		}

		# add facets
		$existingProductFacetIds = array();
		$productFacetValues = explode("||||", $productInformation['product_facets']);
		foreach ($productFacetValues as $thisFacetValue) {
			$parts = explode("||", $thisFacetValue);
			$productFacetCode = $parts[0];
			$facetValue = $parts[1];
			$productFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_code", $productFacetCode);
			if (empty($productFacetId) || empty($facetValue)) {
				continue;
			}
			$productFacetRow = getRowFromId("product_facets", "product_facet_id", $productFacetId);
			$productFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "facet_value", $facetValue, "product_facet_id = ?", $productFacetId);
			if (empty($productFacetOptionId) && ($GLOBALS['gClientRow']['client_code'] != "COREWARE_SHOOTING_SPORTS" || empty($productFacetRow['catalog_lock']))) {
				$insertSet = executeQuery("insert into product_facet_options (product_facet_id,facet_value) values (?,?)", $productFacetId, $facetValue);
				$productFacetOptionId = $insertSet['insert_id'];
				freeResult($insertSet);
			}
			if (!empty($productFacetOptionId)) {
				if (!array_key_exists($productFacetId, $existingProductFacetIds)) {
					$insertSet = executeQuery("insert into product_facet_values (product_id,product_facet_id,product_facet_option_id) values (?,?,?)", $productId, $productFacetId, $productFacetOptionId);
					$existingProductFacetIds[$productFacetId] = $productFacetOptionId;
					freeResult($insertSet);
				} else {
					if ($productFacetOptionId != $existingProductFacetIds[$productFacetId]) {
						$updateSet = executeQuery("update product_facet_values set product_facet_option_id = ? where product_id = ? and product_facet_id = ?", $productFacetOptionId, $productId, $productFacetId);
						freeResult($updateSet);
					}
				}
			}
		}

		# add product tag if class 3 or ffl required
		if (!empty($productInformation['product_tag_codes'])) {
			$productTagCodes = explode(",", $productInformation['product_tag_codes']);
			foreach ($productTagCodes as $productTagCode) {
				$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", $productTagCode);
				if (empty($productTagId)) {
					$insertSet = executeQuery("insert into product_tags (client_id,product_tag_code,description) values (?,?,?)", $GLOBALS['gClientId'], $productTagCode, ucwords(strtolower(str_replace("_", " ", $productTagCode))));
					$productTagId = $insertSet['insert_id'];
				}
				if (!empty($productTagId)) {
					executeQuery("insert ignore into product_tag_links (product_id,product_tag_id) values (?,?)", $productId, $productTagId);
				}
			}
		}

		# add images
		$imageIds = array();
		if (!empty($productInformation['image_id'])) {
			$imageIds[] = $productInformation['image_id'];
		}
		if (!empty($productInformation['alternate_images'])) {
			$alternateImages = explode(",", $productInformation['alternate_images']);
			foreach ($alternateImages as $imageId) {
				if (!empty($imageId) && !in_array($imageId, $imageIds)) {
					$imageIds[] = $imageId;
				}
			}
		}

		if (!empty($imageIds)) {
			$primaryImage = true;
			foreach ($imageIds as $imageId) {
				$resultSet = executeQuery("insert into product_remote_images (product_id,image_identifier,primary_image) values (?,?,?)", $productId, $imageId, ($primaryImage ? 1 : 0));
				freeResult($resultSet);
				$primaryImage = false;
			}
		}

		# add restricted states
		if (!empty($productInformation['restricted_states'])) {
			$restrictedStates = explode(",", $productInformation['restricted_states']);
			foreach ($restrictedStates as $thisState) {
				if (!empty($thisState)) {
					executeQuery("insert into product_restrictions (product_id,state,country_id) values (?,?,1000)", $productId, $thisState);
				}
			}
		}

		if ($useTransaction) {
			$GLOBALS['gPrimaryDatabase']->commitTransaction();
		}
		$GLOBALS['gChangeLogNotes'] = "";
		$returnArray['product_id'] = $productId;
		$returnArray['info_message'] = "Product ID " . $productId . " created successfully.";
		return $returnArray;
	}

	public static function getActiveManufacturers() {
		$manufacturers = getCachedData("active_manufacturers", "");
		if (empty($manufacturers)) {
			$resultSet = executeQuery("select product_manufacturer_id as id,description from product_manufacturers where product_manufacturer_id in " .
				"(select product_manufacturer_id from products where internal_use_only = 0 and inactive = 0) and inactive = 0 and internal_use_only = 0 and client_id = ?", $GLOBALS['gClientId']);
			$manufacturers = array();
			while ($row = getNextRow($resultSet)) {
				$manufacturers[] = $row;
			}
			setCachedData("active_manufacturers", "", $manufacturers, 8);
		}
		return $manufacturers;
	}

	public static function getCachedProductRowByCode($productCode, $additionalFilters = array()) {
		if (!array_key_exists($productCode, $GLOBALS['gProductCodes'])) {
			$resultSet = executeReadQuery("select *,products.client_id as product_client_id,(select description from product_manufacturers where product_manufacturer_id = products.product_manufacturer_id) manufacturer_name," .
				"(select map_policy_id from product_manufacturers where product_manufacturer_id = products.product_manufacturer_id) map_policy_id," .
				"(select group_concat(concat_ws('|',product_inventory_id,location_id,quantity)) FROM product_inventories WHERE product_id = products.product_id) inventory_quantities," .
				"(select group_concat(concat_ws('|',image_id,product_image_code)) FROM product_images WHERE product_id = products.product_id) product_images," .
				"(select count(*) from related_products where product_id = products.product_id and exists (select product_id from products where product_id = related_products.associated_product_id and inactive = 0)) as related_products_count," .
				"(select count(*) from related_products where product_id = products.product_id and exists (select product_id from products where product_id = related_products.associated_product_id and inactive = 0) and related_product_type_id is null) as general_related_products_count," .
				"(select group_concat(product_facet_option_id) from product_facet_values where product_id = products.product_id) as product_facet_option_ids," .
				"(select group_concat(image_identifier) from product_remote_images where product_id = products.product_id and image_identifier is not null order by primary_image desc,product_remote_image_id) as product_remote_images," .
				"(select group_concat(link_url) from product_remote_images where product_id = products.product_id and link_url is not null order by primary_image desc,product_remote_image_id) as remote_image_urls," .
				"(select group_concat(product_category_id) from product_category_links where product_id = products.product_id) as product_category_ids, " .
				"(select group_concat(product_tag_id) from product_tag_links where product_id = products.product_id and (start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date > current_date)) as product_tag_ids " .
				"from products left outer join product_data using (product_id)" .
				($GLOBALS['gPrimaryDatabase']->viewExists("view_of_additional_product_data") ? " join view_of_additional_product_data using (product_id)" : "") .
				" where products.product_code = ?", $productCode);
			if ($productRow = getNextRow($resultSet)) {
				$productRow['product_category_ids'] = array_filter(explode(",", $productRow['product_category_ids']));
				$productRow['product_tag_ids'] = array_filter(explode(",", $productRow['product_tag_ids']));
				$productRow['product_facet_option_ids'] = array_filter(explode(",", $productRow['product_facet_option_ids']));
				$productRow['product_remote_images'] = array_filter(explode(",", $productRow['product_remote_images']));
				$productRow['remote_image_urls'] = array_filter(explode(",", $productRow['remote_image_urls']));
				$productRow['client_id'] = $productRow['product_client_id'];
				$productImages = explode(",", $productRow['product_images']);
				$productRow['product_images'] = array();
				foreach ($productImages as $thisImage) {
					$parts = explode("|", $thisImage);
					if (!empty($parts[0]) && is_numeric($parts[0])) {
						$productRow['product_images'][] = array("image_id" => $parts[0], "product_image_code" => $parts[1]);
					}
				}
				$productRow['product_inventories'] = array();
				$productInventoryQuantities = explode(",", $productRow['inventory_quantities']);
				foreach ($productInventoryQuantities as $thisQuantity) {
					if (!array_key_exists($productRow['product_id'], self::$iProductInventoryQuantities)) {
						self::$iProductInventoryQuantities[$productRow['product_id']] = array();
					}
					if (!empty($thisQuantity)) {
						$parts = explode("|", $thisQuantity);
						self::$iProductInventoryQuantities[$productRow['product_id']][$parts[1]] = $parts[2];
					}
					$productRow['product_inventories'][] = array("product_inventory_id" => $parts[0], "location_id" => $parts[1], "quantity" => $parts[2]);
				}
				$GLOBALS['gProductRows'][$productRow['product_id']] = $productRow;
				$GLOBALS['gProductCodes'][$productRow['product_code']] = $productRow['product_id'];
			}
		}
		$productId = $GLOBALS['gProductCodes'][$productCode];
		$productRow = $GLOBALS['gProductRows'][$productId];
		if (empty($productRow)) {
			return false;
		}
		foreach ($additionalFilters as $fieldName => $fieldValue) {
			if (strlen($fieldValue) == 0) {
				continue;
			}
			if ($productRow[$fieldName] != $fieldValue) {
				return false;
			}
		}
		return $productRow;
	}

	public static function makeValidUPC($rawCode, $parameters = array()) {
		if (!is_array($parameters)) {
			$parameters = array("string_length" => $parameters);
		}
		$onlyValidValues = (!empty($parameters['only_valid_values']));
		if ($onlyValidValues) {
			$rawCode = str_replace(" ", "", $rawCode);
			$rawCode = str_replace("-", "", $rawCode);
			$rawCode = str_replace("'", "", $rawCode);
			$rawCode = str_replace('"', "", $rawCode);
			$rawCode = str_replace('#', "", $rawCode);
		}
		if (!is_numeric($rawCode)) {
			return ($onlyValidValues ? false : $rawCode);
		}
		if (empty($parameters['string_length'])) {
			$parameters['string_length'] = 12;
		}
		if (!array_key_exists("minimum_length", $parameters)) {
			$parameters['minimum_length'] = 10;
		}
		if (!array_key_exists("maximum_length", $parameters)) {
			$parameters['maximum_length'] = 15;
		}
		if (strlen($rawCode) < $parameters['minimum_length']) {
			return ($onlyValidValues ? false : $rawCode);
		}
		if (strlen($rawCode) > $parameters['maximum_length']) {
			return ($onlyValidValues ? false : $rawCode);
		}
		if (preg_match('/^(\d)\1+$/', $rawCode)) { // ignore repeated number UPCs
			return ($onlyValidValues ? false : $rawCode);
		}
		$validFakeUpcs = array("012345678901", "123456789012");
		if (in_array($rawCode, $validFakeUpcs)) {
			return ($onlyValidValues ? false : $rawCode);
		}
		if (strlen($rawCode) == 0 || empty($rawCode)) {
			return ($onlyValidValues ? false : $rawCode);
		}
		return str_pad($rawCode, $parameters['string_length'], "0", STR_PAD_LEFT);
	}

	public static function makeValidISBN($rawCode) {
		return ProductCatalog::makeValidUPC($rawCode, 10);
	}

	public static function makeValidISBN13($rawCode) {
		return ProductCatalog::makeValidUPC($rawCode, 13);
	}

	public static function addSearchTerm($searchTerm, $searchTermParameters, $resultCount) {
		if (empty($searchTerm) || strlen($searchTerm) > $GLOBALS['gMaxSearchTermLength']) {
			return;
		}

		$searchTermId = getFieldFromId("search_term_id", "search_terms", "search_term", $searchTerm);
		if (empty($searchTermId)) {
			$insertSet = executeQuery("insert ignore into search_terms (client_id,search_term,use_count) values (?,?,1)", $GLOBALS['gClientId'], $searchTerm);
			$searchTermId = $insertSet['insert_id'];
			if (empty($searchTermId)) {
				$searchTermId = getFieldFromId("search_term_id", "search_terms", "search_term", $searchTerm);
			}
		} else {
			executeQuery("update search_terms set use_count = use_count + 1 where search_term_id = ?", $searchTermId);
		}
		$searchTermParametersHash = (empty($searchTermParameters) ? "" : md5(jsonEncode($searchTermParameters)));
		if (empty($searchTermParametersHash)) {
			$searchTermParameterId = "";
		} else {
			$searchTermParameterId = getFieldFromId("search_term_parameter_id", "search_term_parameters", "hash_code", $searchTermParametersHash);
			if (empty($searchTermParameterId)) {
				$insertSet = executeQuery("insert into search_term_parameters (client_id,hash_code,parameters) values (?,?,?)", $GLOBALS['gClientId'], $searchTermParametersHash, jsonEncode($searchTermParameters));
				$searchTermParameterId = $insertSet['insert_id'];
			}
		}
		executeQuery("insert into search_term_log (search_term_id,search_term_parameter_id,user_id,log_time,result_count) values (?,?,?,now(),?)", $searchTermId, $searchTermParameterId, $GLOBALS['gUserId'], $resultCount);
	}

	public static function sortProductCosts($a, $b) {
        // put empty costs last
        $aCost = $a['location_cost'] ?: PHP_INT_MAX;
        $bCost = $b['location_cost'] ?: PHP_INT_MAX;
        return $aCost <=> $bCost;
	}

	function getProductSalePrice($productId, $parameters = array()) {
		// print_r($parameters);die;
		if (!array_key_exists("quantity", $parameters)) {
			$parameters['quantity'] = 1;
		}
		if (!array_key_exists("ignore_map", $parameters)) {
			$parameters['ignore_map'] = false;
		}
		if (!array_key_exists("return_pricing_structure_only", $parameters)) {
			$parameters['return_pricing_structure_only'] = false;
		}
		if (!array_key_exists("no_cache", $parameters)) {
			$parameters['no_cache'] = false;
		}
		if (!array_key_exists("contact_type_id", $parameters)) {
			$parameters['contact_type_id'] = $GLOBALS['gUserRow']['contact_type_id'];
		}
		if (!array_key_exists("user_type_id", $parameters)) {
			$parameters['user_type_id'] = $GLOBALS['gUserRow']['user_type_id'];
		}
		if (!empty($parameters['user_type_id']) && getPreference("IGNORE_MAP_FOR_USER_TYPES")) {
			$parameters['ignore_map'] = true;
		}
		// print_r($GLOBALS['gPriceCalculationTypes']);die;
		if (empty($GLOBALS['gPriceCalculationTypes'])) {
			$resultSet = executeQuery("select * from price_calculation_types");
			$GLOBALS['gPriceCalculationTypes'] = array();
			// print_r($resultSet);die;
			while ($row = getNextRow($resultSet)) {
				// echo $row['price_calculation_type_code'];
				$GLOBALS['gPriceCalculationTypes'][$row['price_calculation_type_id']] = $row['price_calculation_type_code'];
			}
			//print_r($GLOBALS['gPriceCalculationTypes']);die;
		}
		// print_r($this->iPricingStructureContactTypes);die;
		# get and store user and contact types. Only ignore pricing cache if the user's type or contact type is actually used
		if ($this->iPricingStructureContactTypes === false) {		
			$this->iPricingStructureContactTypes = getCachedData("pricing_structure_data", "contact_types");			
			if (!is_array($this->iPricingStructureContactTypes)) {
				$this->iPricingStructureContactTypes = array();
				$resultSet = executeReadQuery("select contact_type_id from pricing_structure_quantity_discounts where contact_type_id is not null union select contact_type_id from pricing_structure_user_discounts where contact_type_id is not null");				
				// print_r($resultSet);die;
				while ($row = getNextRow($resultSet)) {
					$this->iPricingStructureContactTypes[$row['contact_type_id']] = $row['contact_type_id'];
				}
				setCachedData("pricing_structure_data", "contact_types", $this->iPricingStructureContactTypes);
			}
		}
		if ($this->iPricingStructureUserTypes === false) {
			$this->iPricingStructureUserTypes = getCachedData("pricing_structure_data", "user_types");	
			if (!is_array($this->iPricingStructureUserTypes)) {
				$this->iPricingStructureUserTypes = array();
				$resultSet = executeReadQuery("select user_type_id from pricing_structure_quantity_discounts where user_type_id is not null union " .
					"select user_type_id from pricing_structure_user_discounts where user_type_id is not null union " .
					"select user_type_id from pricing_structures where user_type_id is not null union " .
					"select user_type_id from user_types where pricing_structure_id is not null union " .
					"select user_type_id from product_prices where user_type_id is not null");
					// print_r(getNextRow($resultSet));die;
				while ($row = getNextRow($resultSet)) {
					$this->iPricingStructureUserTypes[$row['user_type_id']] = $row['user_type_id'];
				}
				setCachedData("pricing_structure_data", "user_types", $this->iPricingStructureUserTypes);
			}
		}

		$pricingUserTypeId = (empty($parameters['user_type_id']) || !array_key_exists($parameters['user_type_id'], $this->iPricingStructureUserTypes) ? "" : $parameters['user_type_id']);
		$pricingContactTypeId = (empty($parameters['contact_type_id']) || !array_key_exists($parameters['contact_type_id'], $this->iPricingStructureContactTypes) ? "" : $parameters['contact_type_id']);

		$this->iPriceCalculationLog = "";
		if (!empty($_GET['no_cache']) && $GLOBALS['gUserRow']['administrator_flag']) {
			$parameters['no_cache'] = true;
		}

		$useCachedPrice = false;
		if (!$parameters['no_cache'] && empty($parameters['shopping_cart_id']) && $parameters['quantity'] == 1 && !$parameters['ignore_map'] && !$parameters['return_pricing_structure_only']) {
			$useCachedPrice = true;
		}
		$productSalePriceCacheKey = "";
		if (function_exists("customProductSalePriceCacheKey")) {
			$productSalePriceCacheKey = customProductSalePriceCacheKey($pricingContactTypeId, $pricingUserTypeId);
		}
		if (empty($productSalePriceCacheKey)) {
			$productSalePriceCacheKey = "price:" . $pricingContactTypeId . ":" . $pricingUserTypeId;
		}
		$cachedPrice = false;
		if ($useCachedPrice) {
			$cachedPrice = getCachedData($productSalePriceCacheKey, $productId);
			if (!empty($cachedPrice)) {
				if (is_array($cachedPrice)) {
					$cachedPrice['cached'] = true;
					$cachedSalePrice = $cachedPrice['sale_price'];
				} else {
					$cachedSalePrice = $cachedPrice;
					$cachedPrice = array("sale_price" => $cachedSalePrice, "cached" => true);
				}
				return $cachedPrice;
			}
		}
		$useStoredPrices = false;
		// print_r($parameters);die;
		if (empty($_GET['no_stored_prices']) && empty($parameters['no_stored_prices']) && empty($parameters['shopping_cart_id']) && $parameters['quantity'] == 1 &&
			!$parameters['ignore_map'] && !$parameters['return_pricing_structure_only'] && empty($pricingUserTypeId) && empty($pricingContactTypeId)) {
			$ignoreStoredPrices = getPreference("IGNORE_STORED_PRICES");
			if (empty($ignoreStoredPrices)) {
				$useStoredPrices = true;
			}
		}
		$cachedPrice = false;
		if ($useStoredPrices) {
			if ($GLOBALS['gApcuEnabled']) {
				$storePricesCached = getCachedData("stored_prices_were_cached", "stored_prices_were_cached");
				if (empty($storePricesCached)) {
					$foundCachedPrices = false;
					$resultSet = executeReadQuery("select product_id,parameters from product_sale_prices where expiration_time > now() and product_id in (select product_id from products where client_id = ?)", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$cachedPrice = json_decode($row['parameters'], true);
						if (is_array($cachedPrice)) {
							$cachedSalePrice = $cachedPrice['sale_price'];
						} else {
							$cachedSalePrice = $cachedPrice;
							$cachedPrice = array("sale_price" => $cachedSalePrice);
						}
						setCachedData("price::", $row['product_id'], $cachedPrice, ($cachedSalePrice > 0 ? 2 : .25));
						if ($row['product_id'] == $productId) {
							$foundCachedPrices = $cachedPrice;
						}
					}
					setCachedData("stored_prices_were_cached", "stored_prices_were_cached", true, 1);
					if ($foundCachedPrices) {
						unset($foundCachedPrices['cached']);
						$foundCachedPrices['stored'] = true;
						return $foundCachedPrices;
					}
				}
				$_GET['no_cache'] = "";
			} else {
				if (empty($this->iNoCacheStoredPrices)) {
					$resultSet = executeReadQuery("select product_id,parameters from product_sale_prices where expiration_time > now() and product_id in (select product_id from products where client_id = ?)", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$storedPrice = json_decode($row['parameters'], true);
						if (!is_array($storedPrice)) {
							$storedPrice = array("sale_price" => $storedPrice);
						}
						if ($storedPrice['sale_price'] > 0) {
							$storedPrice['stored'] = true;
							$this->iNoCacheStoredPrices[$row['product_id']] = $storedPrice;
						}
					}
				}
				if (!empty($this->iNoCacheStoredPrices[$productId])) {
					return $this->iNoCacheStoredPrices[$productId];
				}
			}
			// mahathi come 4
		}

		if (!is_array($parameters['product_information']) || empty($parameters['product_information'])) {
			$parameters['product_information'] = array();
		}
		if (!array_key_exists("product_type_id", $parameters['product_information']) || !array_key_exists("product_manufacturer_id", $parameters['product_information']) ||
			!array_key_exists("pricing_structure_id", $parameters['product_information']) || !array_key_exists("list_price", $parameters['product_information']) ||
			!array_key_exists("base_cost", $parameters['product_information']) || !array_key_exists("minimum_price", $parameters['product_information']) ||
			!array_key_exists("manufacturer_advertised_price", $parameters['product_information'])) {
			$productRow = ProductCatalog::getCachedProductRow($productId);
			if (!$productRow) {
				$productRow = array();
			}
			$parameters['product_information'] = array_merge($parameters['product_information'], $productRow);
		}

		if (self::$iMapPolicies === false) {
			self::$iMapPolicies = getCachedData("map_policies", "", true);
			if (empty(self::$iMapPolicies)) {
				self::$iMapPolicies = array();
				$resultSet = executeQuery("select * from map_policies");
				while ($row = getNextRow($resultSet)) {
					self::$iMapPolicies[$row['map_policy_id']] = $row['map_policy_code'];
				}
				setCachedData("map_policies", "", self::$iMapPolicies, 168, true);
			}
		}

		$defaultMapPolicyId = getPreference("DEFAULT_MAP_POLICY_ID");
		if ($this->iProductManufacturerPricingStructures === false) {
			$this->iProductManufacturerPricingStructures = array();
			$resultSet = executeReadQuery("select product_manufacturer_id,pricing_structure_id,percentage,map_policy_id,(select product_manufacturer_map_holiday_id from " .
				"product_manufacturer_map_holidays where product_manufacturer_id = product_manufacturers.product_manufacturer_id and start_date <= current_date and " .
				"end_date >= current_date limit 1) map_holiday from product_manufacturers where client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$row['map_policy_id'] = $defaultMapPolicyId ?: $row['map_policy_id'];
				$row['map_policy_code'] = self::$iMapPolicies[$row['map_policy_id']];
				$this->iProductManufacturerPricingStructures[$row['product_manufacturer_id']] = $row;
			}
		}
		if (!array_key_exists("map_policy_id", $parameters['product_information'])) {
			$parameters['product_information']['map_policy_id'] = $this->iProductManufacturerPricingStructures[$parameters['product_information']['product_manufacturer_id']]['map_policy_id'];
		}
		if (empty($parameters['product_information']['map_policy_id'])) {
			$parameters['product_information']['map_policy_id'] = $defaultMapPolicyId;
		}
		$parameters['product_information']['map_policy_code'] = self::$iMapPolicies[$parameters['product_information']['map_policy_id']];

		if (function_exists("customProductSalePrice") && !$parameters['return_pricing_structure_only'] && !$parameters['ignore_custom_product_sale_price']) {
			$salePrice = customProductSalePrice(array("product_id" => $productId, "product_row" => $parameters['product_information'], "quantity" => $parameters['quantity'],
				"shopping_cart_id" => $parameters['shopping_cart_id'], "ignore_map" => $parameters['ignore_map']));
			if ($salePrice !== false) {
				if (is_array($salePrice)) {
					$thisSalePrice = $salePrice[$parameters['product_information']['product_code']];
				} else {
					$thisSalePrice = $salePrice;
				}
				if (!is_array($thisSalePrice)) {
					$thisSalePrice = array("sale_price" => $salePrice);
				}
				return $thisSalePrice;
			}
		}

		if ($this->iIgnoreMapProducts === false) {
			$this->iIgnoreMapProducts = array();
			$customFieldId = getReadFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "IGNORE_MAP", "inactive = 0 and " .
				"custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'PRODUCTS')");
			$ignoreMapSet = executeReadQuery("select primary_identifier from custom_field_data where custom_field_id = ? and text_data = '1'", $customFieldId);
			while ($ignoreMapRow = getNextRow($ignoreMapSet)) {
				$this->iIgnoreMapProducts[$ignoreMapRow['primary_identifier']] = $ignoreMapRow['primary_identifier'];
			}
			freeReadResult($ignoreMapSet);
			$ignoreMapSet = executeReadQuery("select product_id from product_tag_links where product_tag_id = (select product_tag_id from product_tags where product_tag_code = 'IGNORE_MAP' and client_id = ?) and " .
				"(start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date >= current_date)", $GLOBALS['gClientId']);
			while ($ignoreMapRow = getNextRow($ignoreMapSet)) {
				$this->iIgnoreMapProducts[$ignoreMapRow['product_id']] = $ignoreMapRow['product_id'];
			}
			freeReadResult($ignoreMapSet);
		}
		$productIgnoreMap = $this->iIgnoreMapProducts[$productId];
		$alwaysIgnoreMap = getPreference("ALWAYS_IGNORE_MAP");
		if (!empty($productIgnoreMap) || !empty($alwaysIgnoreMap) || $parameters['product_information']['map_policy_code'] == "IGNORE_MAP" || $parameters['product_information']['map_policy_code'] == "IGNORE") {
			$parameters['ignore_map'] = true;
			$parameters['product_information']['manufacturer_advertised_price'] = "";
		}

		$inventorySurcharge = 0;
		if (!empty($parameters['product_information']['low_inventory_quantity']) && $parameters['product_information']['low_inventory_quantity'] > 0 &&
			!empty($parameters['product_information']['low_inventory_surcharge_amount']) && $parameters['product_information']['low_inventory_surcharge_amount'] > 0) {
			$inventoryCounts = $this->getInventoryCounts(true, $productId);
			if ($inventoryCounts[$productId] <= $parameters['product_information']['low_inventory_quantity']) {
				$inventorySurcharge = $parameters['product_information']['low_inventory_surcharge_amount'];
			}
		}

		if ($this->iValidPricingStructures === false) {
			$this->iValidPricingStructures = array();
			$resultSet = executeQuery("select pricing_structure_id from pricing_structures where client_id = ? and inactive = 0 and internal_use_only = 0", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$this->iValidPricingStructures[$row['pricing_structure_id']] = true;
			}
		}

		$salePriceProductPrices = ($parameters['no_cache'] ? false : getCachedData("product_prices", $productId));
		if ($salePriceProductPrices === false) {	
			$salePriceProductPrices = array();
			$salePriceProductPriceTypeId = getCachedData("product_price_type_id", "SALE_PRICE");
			if (empty($salePriceProductPriceTypeId)) {
				$salePriceProductPriceTypeId = getFieldFromId("product_price_type_id", "product_price_types", "product_price_type_code", "SALE_PRICE");
				setCachedData("product_price_type_id", "SALE_PRICE", $salePriceProductPriceTypeId, 168);
			}
			if (!empty($salePriceProductPriceTypeId)) {
                if($parameters['preload_product_sale_prices']) { // for search results, get all sale prices once
                    if (empty($this->iProductSalePrices)) {
                        $resultSet = executeReadQuery("select *,(select quantity from product_inventories where product_id = product_prices.product_id and 
                        location_id = product_prices.location_id and location_id in (select location_id from locations where inactive = 0 and internal_use_only = 0 and ignore_inventory = 0)) location_quantity 
                        from product_prices where product_price_type_id = ? and product_id in (select product_id from products where client_id = ?) and
                        (start_date is null or start_date <= current_date) and (end_date is null or end_date >= current_date) order by price", $salePriceProductPriceTypeId, $GLOBALS['gClientId']);
                        while ($row = getNextRow($resultSet)) {
                            $this->iProductSalePrices[$row['product_id']][] = $row;
                        }
                    }
                } else {  // for individual products, get only prices for the requested product
                    if (empty($this->iProductSalePrices[$productId])) {
                        $resultSet = executeReadQuery("select *,(select quantity from product_inventories where product_id = product_prices.product_id and 
                        location_id = product_prices.location_id and location_id in (select location_id from locations where inactive = 0 and internal_use_only = 0 and ignore_inventory = 0)) location_quantity 
                        from product_prices where product_price_type_id = ? and product_id = ? and
                        (start_date is null or start_date <= current_date) and (end_date is null or end_date >= current_date) order by price", $salePriceProductPriceTypeId, $productId);
                        while ($row = getNextRow($resultSet)) {
                            $this->iProductSalePrices[$row['product_id']][] = $row;
                        }
                    }
                }
                $waitingQuantities = array();
                foreach($this->iProductSalePrices[$productId] as $row) {
					if (!empty($row['sale_count'])) {
						$countSet = executeReadQuery("select sum(quantity) from order_items where product_id = ? and deleted = 0 and exists (" .
							"select order_id from orders where order_id = order_items.order_id and deleted = 0" . (empty($row['start_date']) ? "" : " and date(order_time) >= '" . date("Y-m-d", strtotime($row['start_date'])) . "'") . ")", $row['product_id']);
						if ($countRow = getNextRow($countSet)) {
							if (!empty($countRow['sum(quantity)'])) {
								if ($countRow['sum(quantity)'] >= $row['sale_count']) {
									executeQuery("delete from product_prices where product_price_id = ?", $row['product_price_id']);
									continue;
								}
							}
						}
					}
					if (!empty($row['location_id'])) {
						$locationCount = $row['location_quantity'];
						if (empty($locationCount)) {
							$locationCount = 0;
						}
						if ($locationCount <= 0) {
							continue;
						}
						if (!array_key_exists($row['product_id'], $waitingQuantities)) {
							$waitingQuantity = ProductCatalog::getWaitingToShipQuantity($row['product_id']);
							if (empty($waitingQuantity)) {
								$waitingQuantity = 0;
							}
							$waitingQuantities[$row['product_id']] = $waitingQuantity;
						}
						$remainingWaitingQuantity = $waitingQuantities[$row['product_id']];
						if ($remainingWaitingQuantity <= 0) {
							$remainingWaitingQuantity = 0;
						}
						$usedWaitingQuantity = min($locationCount, $remainingWaitingQuantity);
						$locationCount -= $remainingWaitingQuantity;
						$remainingWaitingQuantity -= $usedWaitingQuantity;
						$waitingQuantities[$row['product_id']] = $remainingWaitingQuantity;

						if ($locationCount < $parameters['quantity']) {
							continue;
						}
					}
					$row['price'] += $inventorySurcharge;
					$salePriceProductPrices[] = $row;
				}
			}
			setCachedData("product_prices", $productId, $salePriceProductPrices, .5);
		}
		$thisProductSalePrice = false;
		foreach ($salePriceProductPrices as $thisSalePrice) {
			if (empty($thisSalePrice['user_type_id']) || $thisSalePrice['user_type_id'] == $GLOBALS['gUserRow']['user_type_id']) {
				$thisProductSalePrice = $thisSalePrice;
				break;
			}
		}

		if ($this->iPricingRules === false) {
			$this->iPricingRules = array();
			$resultSet = executeReadQuery("select * from product_pricing_rules where client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$this->iPricingRules[] = $row;
			}
		}

		if (self::$iUserTypePricingStructures === false) {
			self::$iUserTypePricingStructures = getCachedData("user_type_pricing_structures", "user_type_pricing_structures");
			if (!is_array(self::$iUserTypePricingStructures)) {
				self::$iUserTypePricingStructures = array();
				$resultSet = executeReadQuery("select user_type_id,pricing_structure_id from user_types where client_id = ? and pricing_structure_id is not null and pricing_structure_id in (select pricing_structure_id from pricing_structures where inactive = 0 and internal_use_only = 0)", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					self::$iUserTypePricingStructures[$row['user_type_id']] = $row['pricing_structure_id'];
				}
				setCachedData("user_type_pricing_structures", "user_type_pricing_structures", self::$iUserTypePricingStructures, 1);
			}
		}
		$pricingStructureId = self::$iUserTypePricingStructures[$pricingUserTypeId];
		if (self::$iStoredPricingStructureIds === false) {
			self::$iStoredPricingStructureIds = getCachedData("stored_pricing_structure_ids_v2", "stored_pricing_structure_ids_v2");
			if (!is_array(self::$iStoredPricingStructureIds)) {
				self::$iStoredPricingStructureIds = array();
				$structureSet = executeQuery("select pricing_structure_id,user_type_id from pricing_structures where client_id = ? and inactive = 0 and internal_use_only = 0", $GLOBALS['gClientId']);
				while ($structureRow = getNextRow($structureSet)) {
					self::$iStoredPricingStructureIds[$structureRow['pricing_structure_id'] . ":" . $structureRow['user_type_id']] = $structureRow['pricing_structure_id'];
				}
				setCachedData("stored_pricing_structure_ids_v2", "stored_pricing_structure_ids_v2", self::$iStoredPricingStructureIds, 1);
			}
		}
		if (array_key_exists($pricingStructureId . ":" . $pricingUserTypeId, self::$iStoredPricingStructureIds)) {
			$pricingStructureId = self::$iStoredPricingStructureIds[$pricingStructureId . ":" . $pricingUserTypeId];
		} elseif (!empty($pricingUserTypeId) && array_key_exists($pricingStructureId . ":", self::$iStoredPricingStructureIds)) {
			$pricingStructureId = self::$iStoredPricingStructureIds[$pricingStructureId . ":"];
		} elseif (!empty($pricingStructureId)) {
			self::$iStoredPricingStructureIds[$pricingStructureId . ":" . $pricingUserTypeId] = "";
		}

		# Check pricing structure set on the product itself
		if (empty($pricingStructureId)) {
			$thisPricingStructureId = $parameters['product_information']['pricing_structure_id'];
			if (!array_key_exists($thisPricingStructureId, $this->iValidPricingStructures)) {
				$thisPricingStructureId = "";
			}
			if (array_key_exists($thisPricingStructureId . ":" . $pricingUserTypeId, self::$iStoredPricingStructureIds)) {
				$pricingStructureId = self::$iStoredPricingStructureIds[$thisPricingStructureId . ":" . $pricingUserTypeId];
			} elseif (!empty($pricingUserTypeId) && array_key_exists($thisPricingStructureId . ":", self::$iStoredPricingStructureIds)) {
				$pricingStructureId = self::$iStoredPricingStructureIds[$thisPricingStructureId . ":"];
			} elseif (!empty($thisPricingStructureId)) {
				self::$iStoredPricingStructureIds[$thisPricingStructureId . ":" . $pricingUserTypeId] = "";
			}
		}

		# check Product Type pricing Structure
		if (empty($pricingStructureId) && !empty($parameters['product_information']['product_type_id'])) {
			if ($this->iProductTypePricingStructures === false) {
				$this->iProductTypePricingStructures = array();
				$resultSet = executeReadQuery("select product_type_id,pricing_structure_id from product_types where pricing_structure_id is not null and client_id = ? and pricing_structure_id in (select pricing_structure_id from pricing_structures where inactive = 0 and internal_use_only = 0)", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$this->iProductTypePricingStructures[$row['product_type_id']] = $row['pricing_structure_id'];
				}
			}
			$thisPricingStructureId = $this->iProductTypePricingStructures[$parameters['product_information']['product_type_id']];
			if (!array_key_exists($thisPricingStructureId, $this->iValidPricingStructures)) {
				$thisPricingStructureId = "";
			}
			if (array_key_exists($thisPricingStructureId . ":" . $pricingUserTypeId, self::$iStoredPricingStructureIds)) {
				$pricingStructureId = self::$iStoredPricingStructureIds[$thisPricingStructureId . ":" . $pricingUserTypeId];
			} elseif (!empty($pricingUserTypeId) && array_key_exists($thisPricingStructureId . ":", self::$iStoredPricingStructureIds)) {
				$pricingStructureId = self::$iStoredPricingStructureIds[$thisPricingStructureId . ":"];
			} elseif (!empty($thisPricingStructureId)) {
				self::$iStoredPricingStructureIds[$thisPricingStructureId . ":" . $pricingUserTypeId] = "";
			}
		}

		# Check manufacturer Pricing structure
		if (empty($pricingStructureId) && !empty($parameters['product_information']['product_manufacturer_id'])) {
			$thisPricingStructureId = $this->iProductManufacturerPricingStructures[$parameters['product_information']['product_manufacturer_id']]['pricing_structure_id'];
			if (!array_key_exists($thisPricingStructureId, $this->iValidPricingStructures)) {
				$thisPricingStructureId = "";
			}
			if (array_key_exists($thisPricingStructureId . ":" . $pricingUserTypeId, self::$iStoredPricingStructureIds)) {
				$pricingStructureId = self::$iStoredPricingStructureIds[$thisPricingStructureId . ":" . $pricingUserTypeId];
			} elseif (!empty($pricingUserTypeId) && array_key_exists($thisPricingStructureId . ":", self::$iStoredPricingStructureIds)) {
				$pricingStructureId = self::$iStoredPricingStructureIds[$thisPricingStructureId . ":"];
			} elseif (!empty($thisPricingStructureId)) {
				self::$iStoredPricingStructureIds[$thisPricingStructureId . ":" . $pricingUserTypeId] = "";
			}
		}
		if (empty($pricingStructureId)) {
			if (!array_key_exists("product_category_ids", $parameters['product_information'])) {
				if (empty($GLOBALS['gProductCategories']) || !is_array($GLOBALS['gProductCategories'])) {
					$GLOBALS['gProductCategories'] = array();
					if (!empty($this->iProductIds) || !empty($this->iTemporaryTableName)) {
						$resultSet = executeReadQuery("select * from product_category_links where product_id in (" . (empty($this->iTemporaryTableName) ? implode(",", $this->iProductIds) : "select product_id from " . $this->iTemporaryTableName) . ")");
						while ($row = getNextRow($resultSet)) {
							if (!array_key_exists($row['product_id'], $GLOBALS['gProductCategories'])) {
								$GLOBALS['gProductCategories'][$row['product_id']] = array();
							}
							$GLOBALS['gProductCategories'][$row['product_id']][] = $row['product_category_id'];
						}
						foreach ($this->iProductIds as $thisProductId) {
							if (!array_key_exists($row['product_id'], $GLOBALS['gProductCategories'])) {
								$GLOBALS['gProductCategories'][$row['product_id']] = array();
							}
						}
					}
				}
				if (is_array($GLOBALS['gProductCategories']) && array_key_exists($productId, $GLOBALS['gProductCategories'])) {
					$parameters['product_information']['product_category_ids'] = implode(",", $GLOBALS['gProductCategories'][$productId]);
				} elseif (!array_key_exists("product_category_ids", $parameters['product_information'])) {
					$resultSet = executeReadQuery("select group_concat(product_category_id) from product_category_links where product_id = ? order by sequence_number", $productId);
					if ($row = getNextRow($resultSet)) {
						$productCategoryIds = $row['group_concat(product_category_id)'];
					}
					$parameters['product_information']['product_category_ids'] = $productCategoryIds;
				}
			}
			if (!empty($parameters['product_information']['product_category_ids'])) {
				if ($this->iProductCategoryPricingStructures === false) {
					$this->iProductCategoryPricingStructures = array();
					$resultSet = executeReadQuery("select product_category_id,pricing_structure_id from product_categories where pricing_structure_id is not null and client_id = ? and pricing_structure_id in (select pricing_structure_id from pricing_structures where inactive = 0 and internal_use_only = 0)", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$this->iProductCategoryPricingStructures[$row['product_category_id']] = $row['pricing_structure_id'];
					}
				}
				if (is_array($parameters['product_information']['product_category_ids'])) {
					$productCategoryIds = $parameters['product_information']['product_category_ids'];
				} else {
					$productCategoryIds = explode(",", $parameters['product_information']['product_category_ids']);
				}

				# check pricing structure of category
				foreach ($productCategoryIds as $thisProductCategoryId) {
					$thisPricingStructureId = $this->iProductCategoryPricingStructures[$thisProductCategoryId];
					if (!array_key_exists($thisPricingStructureId, $this->iValidPricingStructures)) {
						$thisPricingStructureId = "";
					}
					if (array_key_exists($thisPricingStructureId . ":" . $pricingUserTypeId, self::$iStoredPricingStructureIds)) {
						$pricingStructureId = self::$iStoredPricingStructureIds[$thisPricingStructureId . ":" . $pricingUserTypeId];
					} elseif (!empty($pricingUserTypeId) && array_key_exists($thisPricingStructureId . ":", self::$iStoredPricingStructureIds)) {
						$pricingStructureId = self::$iStoredPricingStructureIds[$thisPricingStructureId . ":"];
					} elseif (!empty($thisPricingStructureId)) {
						self::$iStoredPricingStructureIds[$thisPricingStructureId . ":" . $pricingUserTypeId] = "";
					}
					if (!empty($pricingStructureId)) {
						break;
					}
				}
			}
		}

		$mapEnforced = false;

# check to see if the MAP price IS the sale price

		$thisManufacturer = $this->iProductManufacturerPricingStructures[$parameters['product_information']['product_manufacturer_id']];
		if (empty($thisManufacturer)) {
			$thisManufacturer = array("map_policy_code" => "MAP_MINIMUM");
		}
		if (empty($thisManufacturer['percentage'])) {
			$thisManufacturer['percentage'] = 0;
		}
		if (!empty($thisManufacturer['map_holiday']) || $parameters['ignore_map']) {
			$parameters['product_information']['manufacturer_advertised_price'] = "";
		}

		if (!$parameters['ignore_map'] && $parameters['product_information']['map_policy_code'] == "MAP_MINIMUM" && !empty($parameters['product_information']['manufacturer_advertised_price']) && $parameters['product_information']['manufacturer_advertised_price'] > 0) {
			if (empty($parameters['product_information']['minimum_price']) || $parameters['product_information']['minimum_price'] < $parameters['product_information']['manufacturer_advertised_price']) {
				$parameters['product_information']['minimum_price'] = $parameters['product_information']['manufacturer_advertised_price'];
			}
		}
		if (!$parameters['ignore_map'] && !$parameters['return_pricing_structure_only'] && $parameters['product_information']['map_policy_code'] == "USE_MAP" && !empty($parameters['product_information']['manufacturer_advertised_price']) && $parameters['product_information']['manufacturer_advertised_price'] > 0) {
			$salePriceArray = array("sale_price" => $parameters['product_information']['manufacturer_advertised_price'], "map_policy_code" => "USE_MAP");
			setCachedData($productSalePriceCacheKey, $productId, $salePriceArray, 2);
			return $salePriceArray;
		}

		if (empty($pricingStructureId)) {
			if ($this->iProductDepartmentPricingStructures === false) {
				$this->iProductDepartmentPricingStructures = array();
				$resultSet = executeReadQuery("select product_department_id,pricing_structure_id from product_departments where pricing_structure_id is not null and client_id = ? and pricing_structure_id in (select pricing_structure_id from pricing_structures where inactive = 0 and internal_use_only = 0) order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$this->iProductDepartmentPricingStructures[$row['product_department_id']] = $row['pricing_structure_id'];
				}
			}

			# check pricing structure of department
			foreach ($this->iProductDepartmentPricingStructures as $productDepartmentId => $thisPricingStructureId) {
				$thisPricingStructureId = $this->iProductDepartmentPricingStructures[$productDepartmentId];
				if (!array_key_exists($thisPricingStructureId, $this->iValidPricingStructures)) {
					$thisPricingStructureId = "";
				}
				if (ProductCatalog::productIsInDepartment($productId, $productDepartmentId)) {
					if (array_key_exists($thisPricingStructureId . ":" . $pricingUserTypeId, self::$iStoredPricingStructureIds)) {
						$pricingStructureId = self::$iStoredPricingStructureIds[$thisPricingStructureId . ":" . $pricingUserTypeId];
					} elseif (!empty($pricingUserTypeId) && array_key_exists($thisPricingStructureId . ":", self::$iStoredPricingStructureIds)) {
						$pricingStructureId = self::$iStoredPricingStructureIds[$thisPricingStructureId . ":"];
					} elseif (!empty($thisPricingStructureId)) {
						self::$iStoredPricingStructureIds[$thisPricingStructureId . ":" . $pricingUserTypeId] = "";
					}
					break;
				}
			}
		}

		if (array_key_exists($pricingStructureId . ":" . $pricingUserTypeId, self::$iStoredPricingStructureIds)) {
			$newPricingStructureId = self::$iStoredPricingStructureIds[$pricingStructureId . ":" . $pricingUserTypeId];
			if (!empty($newPricingStructureId)) {
				$pricingStructureId = $newPricingStructureId;
			}
		} elseif (!empty($pricingUserTypeId) && array_key_exists($pricingStructureId . ":", self::$iStoredPricingStructureIds)) {
			$newPricingStructureId = self::$iStoredPricingStructureIds[$pricingStructureId . ":"];
			if (!empty($newPricingStructureId)) {
				$pricingStructureId = $newPricingStructureId;
			}
		} elseif (!empty($pricingStructureId)) {
			self::$iStoredPricingStructureIds[$pricingStructureId . ":" . $pricingUserTypeId] = "";
		}
		if (array_key_exists($pricingStructureId . ":" . $pricingUserTypeId, self::$iStoredPricingStructureIds)) {
			$newPricingStructureId = self::$iStoredPricingStructureIds[$pricingStructureId . ":" . $pricingUserTypeId];
			if (!empty($newPricingStructureId)) {
				$pricingStructureId = $newPricingStructureId;
			}
		} elseif (!empty($pricingUserTypeId) && array_key_exists($pricingStructureId . ":", self::$iStoredPricingStructureIds)) {
			$newPricingStructureId = self::$iStoredPricingStructureIds[$pricingStructureId . ":"];
			if (!empty($newPricingStructureId)) {
				$pricingStructureId = $newPricingStructureId;
			}
		} elseif (!empty($pricingStructureId)) {
			self::$iStoredPricingStructureIds[$pricingStructureId . ":" . $pricingUserTypeId] = "";
		}

		if (empty($pricingStructureId)) {
			if ($this->iDefaultPricingStructureId == false) {
				$this->iDefaultPricingStructureId = getReadFieldFromId("pricing_structure_id", "pricing_structures", "pricing_structure_code",
					"DEFAULT", "(user_type_id is null or user_type_id = ?) and inactive = 0 and internal_use_only = 0", $pricingUserTypeId);
			}
			$pricingStructureId = $this->iDefaultPricingStructureId;
		}
		if (empty($pricingStructureId)) {
			if ($this->iDefaultPricingStructureId == false) {
				$this->iDefaultPricingStructureId = getReadFieldFromId("pricing_structure_id", "pricing_structures", "pricing_structure_code", "PUBLIC",
					"(user_type_id is null or user_type_id = ?) and inactive = 0 and internal_use_only = 0", $pricingUserTypeId);
			}
			$pricingStructureId = $this->iDefaultPricingStructureId;
		}
		if ($parameters['return_pricing_structure_only']) {
			return $pricingStructureId;
		}
		$clientSetSalePrice = false;
		$clientSetSalePriceArray = false;
		if (!empty($thisProductSalePrice)) {
			$clientSetSalePrice = $thisProductSalePrice['price'];
			if (empty($thisProductSalePrice['user_type_id']) && $parameters['product_information']['map_policy_code'] == "MAP_MINIMUM" && !empty($parameters['product_information']['minimum_price']) && $parameters['product_information']['minimum_price'] > 0 && $clientSetSalePrice < $parameters['product_information']['minimum_price']) {
				$clientSetSalePrice = $parameters['product_information']['minimum_price'];
			}
			if (in_array($parameters['product_information']['map_policy_code'], array("STRICT", "STRICT_CODE")) && !$parameters['ignore_map']) {
				$productMapOverrideId = "";
				if (!empty($parameters['shopping_cart_id'])) {
					executeQuery("delete from product_map_overrides where time_requested < date_sub(now(),interval 24 hour)");
					$productMapOverrideId = getReadFieldFromId("product_map_override_id", "product_map_overrides", "shopping_cart_id", $parameters['shopping_cart_id'], "override_code is null and inactive = 0 and time_requested > date_sub(now(),interval 24 hour) and product_id = ?", $productId);
				}
				if (empty($productMapOverrideId) && !empty($parameters['product_information']['manufacturer_advertised_price']) && $parameters['product_information']['manufacturer_advertised_price'] > 0 && $clientSetSalePrice < $parameters['product_information']['manufacturer_advertised_price']) {
					$clientSetSalePrice = $parameters['product_information']['manufacturer_advertised_price'] + round(($thisManufacturer['percentage'] / 100) * $parameters['product_information']['manufacturer_advertised_price'], 2);
					$mapEnforced = true;
				}
			}
			if (in_array($parameters['product_information']['map_policy_code'], array("CALL_PRICE")) && !$parameters['ignore_map']) {
				if (!empty($parameters['product_information']['manufacturer_advertised_price']) && $parameters['product_information']['manufacturer_advertised_price'] > 0 && $clientSetSalePrice < $parameters['product_information']['manufacturer_advertised_price']) {
					$clientSetSalePrice = $parameters['product_information']['manufacturer_advertised_price'];
					$mapEnforced = false;
				}
			}
			if ($parameters['product_information']['map_policy_code'] == "CALL_PRICE") {
				$clientSetSalePriceArray = array("map_enforced" => $mapEnforced, "call_price" => true, "map_policy_code" => "CALL_PRICE", "sale_price" => $clientSetSalePrice);
			} else {
				$clientSetSalePriceArray = (!$parameters['ignore_map'] && ($mapEnforced || in_array($parameters['product_information']['map_policy_code'], array("STRICT", "STRICT_CODE"))) ? array("map_enforced" => $mapEnforced, "map_policy_code" => "STRICT", "sale_price" => $clientSetSalePrice) : array("map_enforced" => false, "ignore_map" => $parameters['ignore_map'], "sale_price" => $clientSetSalePrice));
			}
			if (getPreference("ALWAYS_USE_LIST_PRICE_FOR_ORIGINAL_SALE_PRICE") && !empty($parameters['product_information']['list_price'])) {
				$clientSetSalePriceArray["original_sale_price"] = $parameters['product_information']['list_price'];
			}
		}

		$this->iPriceCalculationLog .= "Product ID: " . $productId . " - " . $parameters['product_information']['description'] . "\n";
		if (empty($clientSetSalePriceArray)) {
			$this->iPriceCalculationLog .= "No Client Sale Price Found" . "\n";
		} else {
			$this->iPriceCalculationLog .= "Client Sale Price Found: " . $clientSetSalePrice . "\n";
		}

		if (array_key_exists($pricingStructureId, $this->iPricingStructures)) {
			$pricingStructureRow = $this->iPricingStructures[$pricingStructureId];
		} else {
			$pricingStructureRow = getRowFromId("pricing_structures", "pricing_structure_id", $pricingStructureId);
			$userDiscounts = array();
			$resultSet = executeReadQuery("select * from pricing_structure_user_discounts where pricing_structure_id = ?", $pricingStructureId);
			while ($row = getNextRow($resultSet)) {
				$userDiscounts[] = $row;
			}
			$pricingStructureRow['user_discounts'] = $userDiscounts;

			$quantityDiscounts = array();
			$resultSet = executeReadQuery("select * from pricing_structure_quantity_discounts where pricing_structure_id = ? order by minimum_quantity", $pricingStructureId);
			while ($row = getNextRow($resultSet)) {
				$quantityDiscounts[] = $row;
			}
			$pricingStructureRow['quantity_discounts'] = $quantityDiscounts;

			$categoryDiscounts = array();
			$resultSet = executeReadQuery("select * from pricing_structure_category_quantity_discounts where pricing_structure_id = ? order by minimum_quantity", $pricingStructureId);
			while ($row = getNextRow($resultSet)) {
				$categoryDiscounts[] = $row;
			}
			$pricingStructureRow['category_discounts'] = $categoryDiscounts;

			$priceDiscounts = array();
			$resultSet = executeReadQuery("select * from pricing_structure_price_discounts where pricing_structure_id = ?", $pricingStructureId);
			while ($row = getNextRow($resultSet)) {
				$priceDiscounts[] = $row;
			}
			$pricingStructureRow['price_discounts'] = $priceDiscounts;

			$this->iPricingStructures[$pricingStructureId] = $pricingStructureRow;
		}
		$userDiscount = 0;
		if (!empty($pricingUserTypeId) || !empty($pricingContactTypeId)) {
			foreach ($pricingStructureRow['user_discounts'] as $thisDiscount) {
				if ((!empty($thisDiscount['user_type_id']) && $thisDiscount['user_type_id'] == $pricingUserTypeId) ||
					(!empty($thisDiscount['contact_type_id']) && $thisDiscount['contact_type_id'] == $pricingContactTypeId)) {
					$userDiscount = max($userDiscount, $thisDiscount['percentage']);
				}
			}
		}
		$startingPercentage = $pricingStructureRow['percentage'];
		$percentIsMarkup = ($GLOBALS['gPriceCalculationTypes'][$pricingStructureRow['price_calculation_type_id']] != "DISCOUNT");

		if ($percentIsMarkup) {
			if (strlen($parameters['product_information']['base_cost']) == 0) {
                if (!empty($clientSetSalePriceArray)) {
                    if ($useCachedPrice) {
                        setCachedData($productSalePriceCacheKey, $productId, $clientSetSalePriceArray, ($clientSetSalePriceArray['sale_price'] > 0 ? 2 : .25));
                    }
                    return $clientSetSalePriceArray;
                }
                if (!empty($pricingStructureRow['use_list_price']) && !empty($parameters['product_information']['list_price'])) {
					if ($useCachedPrice) {
						$cachedPrice = array("sale_price" => $parameters['product_information']['list_price'], "map_policy_code" => $parameters['product_information']['map_policy_code']);
						setCachedData($productSalePriceCacheKey, $productId, $cachedPrice, ($parameters['product_information']['list_price'] > 0 ? 2 : .25));
					}
					return array("sale_price" => $parameters['product_information']['list_price'], "map_policy_code" => $parameters['product_information']['map_policy_code']);
				}
				setCachedData($productSalePriceCacheKey, $productId, array("sale_price" => false), .25);
				return array("sale_price" => false);
			}
			$referencePrice = $parameters['product_information']['base_cost'];
		} elseif (strlen($parameters['product_information']['list_price']) == 0) {
			if (!empty($clientSetSalePriceArray)) {
				if ($useCachedPrice) {
					setCachedData($productSalePriceCacheKey, $productId, $clientSetSalePriceArray, ($clientSetSalePriceArray['sale_price'] > 0 ? 2 : .25));
				}
				return $clientSetSalePriceArray;
			}
			setCachedData($productSalePriceCacheKey, $productId, array("sale_price" => false), .25);
			return array("sale_price" => false);
		} else {
			$referencePrice = $parameters['product_information']['list_price'];
		}
		if (empty($referencePrice)) {
			$referencePrice = 0;
		}
		$this->iPriceCalculationLog .= "Reference Price: " . $referencePrice . "\n";
		$this->iPriceCalculationLog .= "Using pricing structure: " . $pricingStructureRow['description'] . "\n";

		foreach ($pricingStructureRow['price_discounts'] as $thisDiscount) {
			$this->iPriceCalculationLog .= "Checking price discounts: " . $thisDiscount['minimum_price'] . " - " . $thisDiscount['maximum_price'] . "\n";
			if ($referencePrice < $thisDiscount['minimum_price']) {
				continue;
			}
			if (!empty($thisDiscount['maximum_price']) && $referencePrice > $thisDiscount['maximum_price']) {
				continue;
			}
			$this->iPriceCalculationLog .= "Price discount Used: " . $thisDiscount['percentage'] . ", Starting Percentage: " . $startingPercentage . "\n";
			$startingPercentage = $thisDiscount['percentage'];
		}
		foreach ($pricingStructureRow['category_discounts'] as $thisDiscount) {
			if (!ProductCatalog::productIsInCategory($productId, $thisDiscount['product_category_id'])) {
				continue;
			}

			if ($thisDiscount['minimum_quantity'] > 1) {
				if (empty($parameters['shopping_cart_id'])) {
					continue;
				}
				$shoppingCart = ShoppingCart::getShoppingCartById($parameters['shopping_cart_id']);
				if (!$shoppingCart) {
					break;
				}
				$shoppingCartItems = $shoppingCart->getShoppingCartItems();
				$categoryQuantity = 0;
				foreach ($shoppingCartItems as $thisItem) {
					if (ProductCatalog::productIsInCategory($thisItem['product_id'], $thisDiscount['product_category_id'])) {
						$categoryQuantity += $thisItem['quantity'];
					}
				}
			} else {
				$categoryQuantity = 1;
			}
			if ($categoryQuantity < $thisDiscount['minimum_quantity']) {
				continue;
			}

			$startingPercentage = $thisDiscount['percentage'];
		}
		$dollarDiscount = 0;
		foreach ($pricingStructureRow['quantity_discounts'] as $thisDiscount) {
			if (!empty($thisDiscount['user_type_id']) && $thisDiscount['user_type_id'] != $pricingUserTypeId) {
				continue;
			}
			if (!empty($thisDiscount['contact_type_id']) && $thisDiscount['contact_type_id'] != $pricingContactTypeId) {
				continue;
			}
			if ($parameters['quantity'] < $thisDiscount['minimum_quantity']) {
				continue;
			}
			if ($thisDiscount > 0) {
				$startingPercentage = $thisDiscount['percentage'];
			}
			if (!empty($thisDiscount['amount']) && $thisDiscount['amount'] > 0) {
				$dollarDiscount = $thisDiscount['amount'];
			}
		}

		if (!empty($pricingStructureRow['low_inventory_quantity']) && $pricingStructureRow['low_inventory_quantity'] > 0 && !empty($pricingStructureRow['low_inventory_percentage']) && $pricingStructureRow['low_inventory_percentage'] > 0) {
			$inventoryCounts = $this->getInventoryCounts(true, $productId);
			$productInventory = (is_array($inventoryCounts) ? $inventoryCounts[$productId] : $inventoryCounts);
			$this->iPriceCalculationLog .= "Current Inventory is " . $productInventory . "\n";
			if ($productInventory <= $pricingStructureRow['low_inventory_quantity']) {
				$this->iPriceCalculationLog .= "Inventory at or below " . $pricingStructureRow['low_inventory_quantity'] . ", additional percentage: " . $pricingStructureRow['low_inventory_percentage'] . "\n";
				if ($percentIsMarkup) {
					$startingPercentage += $pricingStructureRow['low_inventory_percentage'];
				} else {
					$startingPercentage -= $pricingStructureRow['low_inventory_percentage'];
				}
			}
		}

# Check for a distributor surcharge

		if (self::$iPricingStructureSurcharges === false) {
			$resultSet = executeReadQuery("select * from pricing_structure_distributor_surcharges where pricing_structure_id in (select pricing_structure_id from pricing_structures where client_id = ?)", $GLOBALS['gClientId']);
			self::$iPricingStructureSurcharges = array();
			while ($row = getNextRow($resultSet)) {
				self::$iPricingStructureSurcharges[] = $row;
			}
		}
		if (count(self::$iPricingStructureSurcharges) > 0) {
			$inventoryCounts = $this->getInventoryCounts(false, $productId);
			$distributorIds = array();
			if (is_array($inventoryCounts) && array_key_exists($productId, $inventoryCounts)) {
				foreach ($inventoryCounts[$productId] as $thisLocationId => $thisInventoryCount) {
					if (!is_numeric($thisLocationId) || $thisInventoryCount <= 0) {
						continue;
					}
					$productDistributorId = getReadFieldFromId("product_distributor_id", "locations", "location_id", $thisLocationId);
					if (empty($productDistributorId)) {
						continue;
					}
					if (!in_array($productDistributorId, $distributorIds)) {
						$distributorIds[] = $productDistributorId;
					}
					if (count($distributorIds) > 1) {
						break;
					}
				}
			}
			if (count($distributorIds) == 1) {
				foreach (self::$iPricingStructureSurcharges as $row) {
					if ($row['pricing_structure_id'] != $pricingStructureId) {
						continue;
					}
					if (in_array($row['product_distributor_id'], $distributorIds)) {
						$this->iPriceCalculationLog .= "Surcharge for distributor '" . getReadFieldFromId("description", "product_distributors", "product_distributor_id", $row['product_distributor_id']) . "': " . $row['percentage'] . "\n";
						if ($percentIsMarkup) {
							$startingPercentage += $row['percentage'];
						} else {
							$startingPercentage -= $row['percentage'];
						}
					}
				}
			}
		}

		if ($percentIsMarkup) {
			$this->iPriceCalculationLog .= "Base Cost: " . $referencePrice . "\n";
			$percentMarkup = max(($pricingStructureRow['minimum_markup'] + 100), ((100 + $startingPercentage) * ((100 - $userDiscount) / 100)));
			if ($GLOBALS['gPriceCalculationTypes'][$pricingStructureRow['price_calculation_type_id']] == "MARGIN") {
				$percentMarkup = min($percentMarkup, 199); // Margin can never be 100% or more
				$this->iPriceCalculationLog .= "Percent Margin: " . ($percentMarkup - 100) . "\n";
				$salePrice = round($referencePrice / ((100 - ($percentMarkup - 100)) / 100), 2);
			} else {
				$this->iPriceCalculationLog .= "Percent Markup: " . ($percentMarkup - 100) . "\n";
				$salePrice = round($referencePrice * $percentMarkup / 100, 2);
			}
			if (!empty($pricingStructureRow['minimum_amount']) && $pricingStructureRow['minimum_amount'] > 0) {
				$thisAmount = $salePrice - $referencePrice;
				if ($thisAmount < $pricingStructureRow['minimum_amount']) {
					$salePrice = $referencePrice + $pricingStructureRow['minimum_amount'];
					$this->iPriceCalculationLog .= "Minimum Markup Amount used. New Sale price: " . $salePrice . "\n";
				}
			}
		} else {
			$this->iPriceCalculationLog .= "List Price: " . $referencePrice . "\n";
			$percentDiscount = min((100 - $pricingStructureRow['maximum_discount']), (100 - $startingPercentage - ((100 - $startingPercentage) * $userDiscount / 100)));
			$this->iPriceCalculationLog .= "Percent Discount: " . $percentDiscount . "\n";
			$salePrice = round($referencePrice * $percentDiscount / 100, 2);
		}
		$this->iPriceCalculationLog .= "Sale Price: " . $salePrice . "\n";
		if (!empty($this->iPricingRules)) {
			foreach ($this->iPricingRules as $row) {
				if ($salePrice >= $row['minimum_amount'] && (strlen($row['maximum_amount']) == 0 || $salePrice <= $row['maximum_amount'])) {
					$roundAmount = $row['round_amount'];
					if ($roundAmount == 0) {
						$salePrice = ceil($salePrice);
					} elseif (($roundAmount * 100) % 100 == 0) {
						$salePrice = (ceil($salePrice / $roundAmount) * $roundAmount);
					} else {
						if ($salePrice < $roundAmount) {
							$salePrice = $roundAmount;
						} else {
							$roundAmount = number_format($roundAmount, 2, ".", "");
							if (substr($roundAmount, 0, 2) == "0.") {
								$roundAmount = substr($roundAmount, 1);
							}
							while (substr($salePrice, (strlen($roundAmount) * -1)) != $roundAmount) {
								$salePrice += .01;
								$salePrice = number_format($salePrice, 2, ".", "");
							}
						}
					}
					break;
				}
			}
			$this->iPriceCalculationLog .= "Sale Price after rounding rules: " . $salePrice . "\n";
		}
		if (!empty($parameters['product_information']['minimum_price']) && $parameters['product_information']['minimum_price'] > 0 && $salePrice < $parameters['product_information']['minimum_price']) {
			$salePrice = $parameters['product_information']['minimum_price'];
			$this->iPriceCalculationLog .= "Sale Price Minimum: " . $salePrice . "\n";
		}
		$mapEnforced = false;
		if (in_array($parameters['product_information']['map_policy_code'], array("STRICT", "STRICT_CODE")) && !$parameters['ignore_map']) {
			$productMapOverrideId = "";
			if (!empty($parameters['shopping_cart_id'])) {
				executeQuery("delete from product_map_overrides where time_requested < date_sub(now(),interval 24 hour)");
				$productMapOverrideId = getReadFieldFromId("product_map_override_id", "product_map_overrides", "shopping_cart_id", $parameters['shopping_cart_id'], "override_code is null and inactive = 0 and time_requested > date_sub(now(),interval 24 hour) and product_id = ?", $productId);
			}
			if (empty($productMapOverrideId) && !empty($parameters['product_information']['manufacturer_advertised_price']) && $parameters['product_information']['manufacturer_advertised_price'] > 0 && $salePrice < $parameters['product_information']['manufacturer_advertised_price']) {
				$salePrice = $parameters['product_information']['manufacturer_advertised_price'] + round(($thisManufacturer['percentage'] / 100) * $parameters['product_information']['manufacturer_advertised_price'], 2);
				$mapEnforced = true;
			}
		}
		$salePrice += $inventorySurcharge;
		if ($dollarDiscount > 0) {
			$salePrice -= $dollarDiscount;
			$this->iPriceCalculationLog .= "Quantity discount amount of " . $dollarDiscount . ": " . $salePrice . "\n";
		}

		if (empty($pricingStructureId)) {
			$salePriceArray = array("sale_price" => false);
		} elseif (!$parameters['ignore_map'] && ($mapEnforced || in_array($parameters['product_information']['map_policy_code'], array("STRICT", "STRICT_CODE")))) {
			$salePriceArray = array("map_enforced" => $mapEnforced, "map_policy_code" => "STRICT", "sale_price" => $salePrice);
		} elseif (in_array($parameters['product_information']['map_policy_code'], array("CALL_PRICE"))) {
			if (!empty($parameters['product_information']['manufacturer_advertised_price']) && $parameters['product_information']['manufacturer_advertised_price'] > 0 && $salePrice < $parameters['product_information']['manufacturer_advertised_price']) {
				$salePriceArray = array("map_enforced" => $mapEnforced, "map_policy_code" => "CALL_PRICE", "call_price" => true, "sale_price" => $parameters['product_information']['manufacturer_advertised_price']);
				$this->iPriceCalculationLog .= "Call for price used" . "\n";
			} else {
				$salePriceArray = array("map_enforced" => $mapEnforced, "ignore_map" => $parameters['ignore_map'], "sale_price" => $salePrice);
			}
		} else {
			$salePriceArray = array("map_enforced" => $mapEnforced, "ignore_map" => $parameters['ignore_map'], "sale_price" => $salePrice);
		}
		if ($clientSetSalePriceArray !== false) {
			if ($clientSetSalePrice < $salePriceArray['sale_price'] && empty($clientSetSalePriceArray['original_sale_price'])) {
				if ($salePriceArray['map_policy_code'] != "STRICT") {
					$clientSetSalePriceArray['original_sale_price'] = $salePriceArray['sale_price'];
				}
			}
			$salePriceArray = $clientSetSalePriceArray;
		}
		if (!is_array($salePriceArray)) {
			$salePriceArray = array("sale_price" => $salePriceArray);
		}
		if (empty($parameters['shopping_cart_id']) && $parameters['quantity'] == 1 && !$callIgnoreMap) {
			setCachedData($productSalePriceCacheKey, $productId, $salePriceArray, ($salePriceArray['sale_price'] > 0 ? 2 : .25));
		}
		$salePriceArray['calculation_log'] = $this->iPriceCalculationLog;
		return $salePriceArray;
	}

	/*
	 * Function to get inventory counts for products.
	 *
	 * $totalsOnly - If true, the function will return an array with product IDs as keys and the values are the number of that product which is available to be sold. Location
	 * of the product is immaterial. If a location is active and public (not internal use only), the inventory it has for the product is counted. Any product that is sold but
	 * waiting to be shipped is reduced from the total.
	 *
	 * $totalsOnly - If false, the function will return an array of arrays. The key of the array will be Product ID and the array will be location ID as array key and quantity available
	 * at that location as the value. Two additional values will be in the array: 'distributor' will be the number of the item available from ALL distributors; 'waiting' is the number
	 * of the product ordered by waiting to be shipped. If an order is for pickup, ie. has a shipping method marked as pickup AND set to a valid location, it will NOT be included
	 * in the waiting quantity, but will be reduced from that locations quantity. The exception to this is if the location doesn't have enough to fill those orders, then the excess
	 * will be included in the waiting quantity. The end result is an array for each product where:
	 *
	 * 'distributor' - the total number of the product available from all active distributor locations
	 * numeric - the number available at the location (identified by this number as the location ID) LESS the number on order for pickup at this location, though the number would never
	 *      go below zero.
	 * 'waiting' - the total number of the product that are ordered, but not yet shipped, but not including those removed from a specific location's inventory, because they are
	 *      tagged to be picked up at that location
	 * 'total' - the total number of the product that are available to be ordered
	 *
	 */

	function getInventoryCounts($totalsOnly = false, $productIds = false, $allProducts = false, $parameters = array()) {
		if ($productIds === false) {
			$productIds = array();
		}
		if (function_exists("_localGetInventoryCounts")) {
			if (empty($productIds)) {
				$productIds = $this->iProductIds;
			}
			$inventoryCounts = _localGetInventoryCounts($totalsOnly, $productIds);
			if ($inventoryCounts !== false) {
				return $inventoryCounts;
			}
		}
		if ($totalsOnly && empty($productIds) && !$allProducts && $this->iProductInventoryCounts !== false && is_array($this->iProductInventoryCounts)) {
			if ($GLOBALS['gUserRow']['superuser_flag'] && !empty(getPreference('log_inventory_counts'))) {
				addDebugLog($totalsOnly . " : " . (is_array($productIds) ? jsonEncode($productIds) : $productIds) . " : " . (is_array($this->iProductInventoryCounts) ? jsonEncode($this->iProductInventoryCounts) : $this->iProductInventoryCounts));
			}
			return $this->iProductInventoryCounts;
		}
		if (self::$iBackorderProducts === false) {
			self::$iBackorderProducts = array();
			$customFieldId = CustomField::getCustomFieldIdFromCode("BACKORDER", "PRODUCTS");
			if (!empty($customFieldId)) {
				$resultSet = executeQuery("select primary_identifier from custom_field_data where custom_field_id = ? and text_data = '1'", $customFieldId);
				while ($row = getNextRow($resultSet)) {
					self::$iBackorderProducts[$row['primary_identifier']] = true;
				}
			}
		}
		if (empty($parameters['ignore_backorder'])) {
			$backorderProducts = self::$iBackorderProducts;
		} else {
			$backorderProducts = array();
		}

		# Products tagged as Local Inventory only are only sold from local inventory. The distributor inventory is ignored.

		if (self::$iLocalOnlyProductIds === false) {
			self::$iLocalOnlyProductIds = array();
			$localOnlyProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "LOCAL_INVENTORY_ONLY");
			if (!empty($localOnlyProductTagId)) {
				$resultSet = executeQuery("select product_id from product_tag_links where (start_date is null or start_date <= current_date) and " .
					"(expiration_date is null or expiration_date > current_date) and product_tag_id = ?", $localOnlyProductTagId);
				while ($row = getNextRow($resultSet)) {
					self::$iLocalOnlyProductIds[$row['product_id']] = true;
				}
			}
		}

		$saveResults = false;
		$useTemporaryTable = false;
		if (empty($productIds) && !$allProducts) {
			$productIds = $this->iProductIds;
			$useTemporaryTable = true;
			$saveResults = true;
		} elseif ($allProducts) {
			$productIds = array();
			$resultSet = executeReadQuery("select product_id from products where client_id = ? and inactive = 0" .
				($GLOBALS['gInternalConnection'] || $this->iAllowInternalUseOnly ? "" : " and internal_use_only = 0"), $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$productIds[] = $row['product_id'];
			}
			freeReadResult($resultSet);
		} elseif (!is_array($productIds)) {
			if ($totalsOnly && is_array($this->iProductInventoryCounts) && array_key_exists($productIds, $this->iProductInventoryCounts)) {
				if ($GLOBALS['gUserRow']['superuser_flag'] && !empty(getPreference('log_inventory_counts'))) {
					addDebugLog($totalsOnly . " : " . (is_array($productIds) ? jsonEncode($productIds) : $productIds) . " : " . (is_array($this->iProductInventoryCounts[$productIds]) ? jsonEncode($this->iProductInventoryCounts[$productIds]) : $this->iProductInventoryCounts[$productIds]));
				}
				$returnInventory = array($productIds => $this->iProductInventoryCounts[$productIds]);
				return $returnInventory;
			}
			$productIds = array($productIds);
		}
		if (empty($productIds)) {
			return array();
		}

		if (!$useTemporaryTable && count($productIds) > $this->iTemporaryTableThreshold && empty($this->iProductIds) && empty($this->iTemporaryTableName)) {
			$this->iTemporaryTableName = "temporary_products_" . date("Ymd") . "_" . strtolower(getRandomString(12));
			executeQuery("create table " . $this->iTemporaryTableName . "(product_id int not null,primary key (product_id))");
			$temporaryIndexQuery = "";

			foreach ($productIds as $productId) {
				$temporaryIndexQuery .= (empty($temporaryIndexQuery) ? "" : ",") . "(" . $productId . ")";
				if (strlen($temporaryIndexQuery) > 5000) {
					executeQuery("insert ignore into " . $this->iTemporaryTableName . " (product_id) values " . $temporaryIndexQuery);
					$temporaryIndexQuery = "";
				}
			}
			if (!empty($temporaryIndexQuery)) {
				executeQuery("insert ignore into " . $this->iTemporaryTableName . " (product_id) values " . $temporaryIndexQuery);
			}
			$useTemporaryTable = true;
			$count = 0;
			while (true) {
				$count++;
				if ($count > 20) {
					break;
				}
				usleep(10000);
				$resultSet = executeReadQuery("select count(*) from " . $this->iTemporaryTableName);
				if ($row = getNextRow($resultSet)) {
					if ($row['count(*)'] > (count($productIds) - 1000)) {
						break;
					}
				}
			}
		}

		if (self::$iOutOfStockProducts === false) {
			self::$iOutOfStockProducts = array();
			$customFieldId = CustomField::getCustomFieldIdFromCode("OUT_OF_STOCK", "PRODUCTS");
			if (!empty($customFieldId)) {
				$resultSet = executeReadQuery("select * from custom_field_data where custom_field_id = ? and text_data = '1'", $customFieldId);
				while ($row = getNextRow($resultSet)) {
					self::$iOutOfStockProducts[$row['primary_identifier']] = $row['primary_identifier'];
				}
				freeReadResult($resultSet);
			}
			$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "OUT_OF_STOCK", "inactive = 0");
			if (!empty($productTagId)) {
				$resultSet = executeQuery("select * from product_tag_links where (start_date is null or start_date >= current_date) and (expiration_date is null or expiration_date >= current_date) and product_tag_id = ?", $productTagId);
				while ($row = getNextRow($resultSet)) {
					self::$iOutOfStockProducts[$row['product_id']] = $row['product_id'];
				}
				freeResult($resultSet);
			}
		}
		if (self::$iNonInventoryProducts === false) {
			self::$iNonInventoryProducts = array();
			$resultSet = executeReadQuery("select product_id from products where non_inventory_item = 1 and client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				self::$iNonInventoryProducts[$row['product_id']] = true;
			}
		}

		# Some clients want to exclude inventory that is in local locations that are NOT the selected default location of the customer. Setting this preference will do that

		$excludeNonDefaultLocations = getPreference("EXCLUDE_LOCATIONS_FROM_SIDEBAR");
		if ($GLOBALS['gLoggedIn']) {
			$defaultLocationId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID");
		} else {
			$defaultLocationId = $_COOKIE['default_location_id'];
		}

		# Get the list of locations for which inventory needs to be calculated
		if (self::$iInventoryCountLocationRows === false) {
			self::$iInventoryCountLocationRows = array();
			$resultSet = executeReadQuery("select * from locations where internal_use_only = 0 and inactive = 0 and ignore_inventory = 0 and client_id = ? and " .
				(empty($excludeNonDefaultLocations) ? "" : "(product_distributor_id is not null or location_id = " . (empty($defaultLocationId) ? "0" : makeParameter($defaultLocationId)) . ") and ") .
				"(product_distributor_id is null or primary_location = 1) and location_id not in (select location_id from location_credentials where inactive = 1)", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				self::$iInventoryCountLocationRows[$row['location_id']] = $row;
			}
		}
		$locationRows = self::$iInventoryCountLocationRows;
		$locationIds = $this->iLocationIds;
		if (empty($locationIds)) {
			$locationIds = array();
			foreach ($locationRows as $row) {
				$locationIds[] = $row['location_id'] ?: 0;
			}
		}
		if (!empty($excludeNonDefaultLocations)) {
			$originalLocationIds = $locationIds;
			$locationIds = array();
			foreach ($originalLocationIds as $thisLocationId) {
				if (array_key_exists($thisLocationId, $locationRows)) {
					$locationIds[] = $thisLocationId;
				}
			}
		}

		# set initial inventory quantities. If backorder or non-inventory product, unlimited quantity, otherwise zero
		$inventoryRows = array();
		if ($totalsOnly) {
			foreach ($productIds as $thisProductId) {
				$inventoryRows[$thisProductId] = ((array_key_exists($thisProductId, self::$iNonInventoryProducts) || array_key_exists($thisProductId, $backorderProducts)) && !array_key_exists($thisProductId, self::$iOutOfStockProducts) ? 9999999999 : 0);
			}
		}

		$productInventoryRows = array();
		$remainingProductIds = array();

		# load product inventory quantities for products that might have already been read from the database
		foreach ($productIds as $thisProductId) {
			if (array_key_exists($thisProductId, self::$iProductInventoryQuantities)) {
				foreach (self::$iProductInventoryQuantities[$thisProductId] as $locationId => $quantity) {
					$productInventoryRows[] = array("product_id" => $thisProductId, "location_id" => $locationId, "quantity" => $quantity);
				}
			} else {
				$remainingProductIds[$thisProductId] = $thisProductId;
			}
		}

		# load product inventory quantities for remaining products that need to be read from the database. If there are a limited number of products, get them by product ID. Otherwise, get all for locations and filter.
		if (!empty($remainingProductIds)) {
			if (count($remainingProductIds) < 1000) {
				$whereProducts = "";
				foreach ($remainingProductIds as $productId) {
					if (!empty($productId) && is_numeric($productId)) {
						$whereProducts .= (empty($whereProducts) ? "" : ",") . $productId;
					}
				}
				if (empty($whereProducts)) {
					$whereProducts = "0";
				}
				$queryStatement = "select product_id,location_id,quantity from product_inventories where product_id in (" . $whereProducts . ")";
				$resultSet = executeReadQuery($queryStatement);
			} else {
				$queryStatement = "select product_id,location_id,quantity from product_inventories where location_id in (" . implode(",", $locationIds) . ")";
				$resultSet = executeReadQuery($queryStatement);
			}
			while ($row = getNextRow($resultSet)) {
				if (!array_key_exists($row['product_id'], self::$iProductInventoryQuantities)) {
					self::$iProductInventoryQuantities[$row['product_id']] = array();
				}
				self::$iProductInventoryQuantities[$row['product_id']][$row['location_id']] = $row['quantity'];
				if (!array_key_exists($row['product_id'], $remainingProductIds) || !in_array($row['location_id'], $locationIds)) {
					continue;
				}
				$productInventoryRows[] = $row;
			}
		}

		$outOfStockThresholdDepartments = getCachedData("product_departments_with_out_of_stock_threshold", "");
		if (!is_array($outOfStockThresholdDepartments)) {
			$outOfStockThresholdDepartments = array();
			$resultSet = executeQuery("select * from product_departments where inactive = 0 and out_of_stock_threshold is not null and out_of_stock_threshold > 0 and client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$outOfStockThresholdDepartments[] = $row;
			}
			setCachedData("product_departments_with_out_of_stock_threshold", "", $outOfStockThresholdDepartments);
		}

		$productDistributorIds = array();
		$saveProductId = false;
		foreach ($productInventoryRows as $row) {
			if (!in_array($row['location_id'], $locationIds)) {
				continue;
			}
			$row['product_distributor_id'] = $locationRows[$row['location_id']]['product_distributor_id'];
			$row['warehouse_location'] = $locationRows[$row['location_id']]['warehouse_location'];
			if (array_key_exists($row['product_id'], self::$iNonInventoryProducts) || array_key_exists($row['product_id'], $backorderProducts)) {
				continue;
			}
			$outOfStockThreshold = $locationRows[$row['location_id']]['out_of_stock_threshold'];
			if (empty($outOfStockThreshold)) {
				$outOfStockThreshold = 0;
			}
			foreach ($outOfStockThresholdDepartments as $thisDepartment) {
				if ($thisDepartment['out_of_stock_threshold'] > $outOfStockThreshold) {
					if (self::productIsInDepartment($row['product_id'], $thisDepartment['product_department_id'])) {
						$outOfStockThreshold = $thisDepartment['out_of_stock_threshold'];
					}
				}
			}

			if ($row['quantity'] <= $outOfStockThreshold) {
				$row['quantity'] = 0;
			}

			if (!empty($row['product_distributor_id']) && array_key_exists($row['product_id'], self::$iLocalOnlyProductIds)) {
				continue;
			}

			# make sure we only count distributors once
			if ($row['product_id'] != $saveProductId) {
				$productDistributorIds = array();
				$saveProductId = $row['product_id'];
			}
			if (!empty($row['product_distributor_id'])) {
				if (in_array($row['product_distributor_id'], $productDistributorIds)) {
					continue;
				}
				$productDistributorIds[] = $row['product_distributor_id'];
			}
			if (array_key_exists($row['product_id'], self::$iOutOfStockProducts) || $row['quantity'] < 0) {
				$row['quantity'] = 0;
			}
			if ($totalsOnly) {
				if (!array_key_exists($row['product_id'], $inventoryRows)) {
					$inventoryRows[$row['product_id']] = 0;
				}
				$inventoryRows[$row['product_id']] += $row['quantity'];
			} else {
				if (!array_key_exists($row['product_id'], $inventoryRows)) {
					$inventoryRows[$row['product_id']] = array();
					$inventoryRows[$row['product_id']]['distributor'] = 0;
				}
				$inventoryRows[$row['product_id']][$row['location_id']] = ((array_key_exists($row['product_id'], self::$iNonInventoryProducts) || array_key_exists($row['product_id'], $backorderProducts)) && !array_key_exists($row['product_id'], self::$iOutOfStockProducts) ? 9999999999 : $row['quantity']);
				if (!empty($row['product_distributor_id']) || !empty($row['warehouse_location'])) {
					$inventoryRows[$row['product_id']]['distributor'] += $row['quantity'];
				}
			}
		}

		$ignoreSalesForInventory = getPreference("IGNORE_SALES_FOR_INVENTORY");
		if (empty($ignoreSalesForInventory)) {
			if ($totalsOnly) {
				$resultSet = executeReadQuery("select product_id,sum(quantity) as quantity_ordered," .
					"(select sum(quantity) from order_shipment_items where order_shipment_id in (select order_shipment_id from order_shipments where " .
					"secondary_shipment = 0) and order_item_id in (select order_item_id from order_items as oi where product_id = order_items.product_id and deleted = 0 and " .
					"order_id in (select order_id from orders where client_id = ? and deleted = 0 and date_completed is null))) as quantity_shipped " .
					"from order_items where deleted = 0 and order_id in (select order_id from orders where client_id = ? and deleted = 0 and date_completed is null) and product_id in (" .
					(empty($this->iTemporaryTableName) || !$useTemporaryTable ? implode(",", $productIds) : "select product_id from " . $this->iTemporaryTableName) .
					") group by product_id", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (array_key_exists($row['product_id'], $inventoryRows) && is_numeric($inventoryRows[$row['product_id']])) {
						if (empty($row['quantity_shipped'])) {
							$row['quantity_shipped'] = 0;
						}
						if (empty($row['quantity_ordered'])) {
							$row['quantity_ordered'] = 0;
						}
						$waitingQuantity = max(0, ($row['quantity_ordered'] - $row['quantity_shipped']));
						$inventoryRows[$row['product_id']] = max(0, ($inventoryRows[$row['product_id']] - $waitingQuantity));
					} else {
						$inventorRows[$row['product_id']] = 0;
					}
				}
			} else {
				if (self::$iPickupLocationIds === false) {
					self::$iPickupLocationIds = array();
					$resultSet = executeReadQuery("select location_id from shipping_methods where inactive = 0 and pickup = 1 and client_id = ?", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						self::$iPickupLocationIds[] = $row['location_id'];
					}
				}
				$resultSet = executeReadQuery("select product_id,location_id,quantity_ordered,quantity_shipped from (select product_id,(select location_id from shipping_methods where " .
					"shipping_method_id = (select shipping_method_id from orders where order_id = order_items.order_id)) as location_id,sum(quantity) as quantity_ordered,(select sum(quantity) from " .
					"order_shipment_items where order_shipment_id in (select order_shipment_id from order_shipments where secondary_shipment = 0) and order_item_id in (select order_item_id from " .
					"order_items as oi where product_id = order_items.product_id and deleted = 0 and order_id in (select order_id from orders where client_id = ? and deleted = 0 and " .
					"date_completed is null))) as quantity_shipped from order_items where deleted = 0 and order_id in (select order_id from orders where client_id = ? and deleted = 0 and " .
					"date_completed is null) and product_id in (" . (empty($this->iTemporaryTableName) || !$useTemporaryTable ? implode(",", $productIds) : "select product_id from " . $this->iTemporaryTableName) .
					") group by product_id,location_id) as waiting_orders group by product_id,location_id", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (array_key_exists($row['product_id'], $inventoryRows)) {
						if (empty($row['quantity_shipped'])) {
							$row['quantity_shipped'] = 0;
						}
						if (empty($row['quantity_ordered'])) {
							$row['quantity_ordered'] = 0;
						}
						$waitingQuantity = $row['quantity_ordered'] - $row['quantity_shipped'];
						if ($waitingQuantity <= 0) {
							continue;
						}
						if (in_array($row['location_id'], self::$iPickupLocationIds)) {
							if (array_key_exists($row['location_id'], $inventoryRows[$row['product_id']])) {
								$useQuantity = min($waitingQuantity, $inventoryRows[$row['product_id']][$row['location_id']]);
								$inventoryRows[$row['product_id']][$row['location_id']] -= $useQuantity;
								$waitingQuantity -= $useQuantity;
							}
						}
						if ($waitingQuantity <= 0) {
							$waitingQuantity = 0;
						}
						$inventoryRows[$row['product_id']]['waiting'] = $waitingQuantity;
					}
				}
			}
		}
		if (!$totalsOnly) {
			if (!empty($productIds)) {
				foreach ($productIds as $thisProductId) {
					if (!array_key_exists($thisProductId, $inventoryRows)) {
						$inventoryRows[$thisProductId] = array("distributor" => 0, "waiting" => 0);
					}
					if (!array_key_exists("waiting", $inventoryRows[$thisProductId])) {
						$inventoryRows[$thisProductId]['waiting'] = 0;
					}
					if ((array_key_exists($thisProductId, self::$iNonInventoryProducts) || array_key_exists($row['product_id'], $backorderProducts)) && !array_key_exists($thisProductId, self::$iOutOfStockProducts)) {
						$inventoryRows[$thisProductId]['distributor'] = 99999999;
						foreach ($locationIds as $thisLocationId) {
							$inventoryRows[$thisProductId][$thisLocationId] = 99999999;
						}
					}
				}
			}
			foreach ($inventoryRows as $productId => $inventoryQuantities) {
				$totalQuantity = 0;
				foreach ($inventoryQuantities as $locationId => $quantity) {
					if ($locationId == "waiting") {
						$totalQuantity -= $quantity;
					} elseif (is_numeric($locationId)) {
						$totalQuantity += $quantity;
					}
				}
				$inventoryRows[$productId]['total'] = max($totalQuantity, 0);
			}
		}
		if (function_exists("_localMassageInventoryCounts")) {
			$inventoryRows = _localMassageInventoryCounts($inventoryRows, $totalsOnly);
		}

		if ($saveResults && $totalsOnly) {
			$this->iProductInventoryCounts = $inventoryRows;
		}

		$this->getProductPacks();
		foreach ($productIds as $productId) {
			if (array_key_exists($productId, self::$iProductPacks)) {
				$newInventory = $this->getProductPackInventory($productId, $totalsOnly);
				if ($newInventory !== false) {
					$inventoryRows[$productId] = $newInventory[$productId];
					if (is_array($inventoryRows[$productId])) {
						$totalQuantity = 0;
						foreach ($inventoryRows[$productId] as $locationId => $quantity) {
							if ($locationId == "waiting") {
								$totalQuantity -= $quantity;
							} elseif (is_numeric($locationId)) {
								$totalQuantity += $quantity;
							}
						}
						$inventoryRows[$productId]['total'] = max($totalQuantity, 0);
					}
				}
			}
		}
		if ($GLOBALS['gUserRow']['superuser_flag'] && !empty(getPreference('log_inventory_counts'))) {
			addDebugLog($totalsOnly . " : " . (is_array($productIds) ? jsonEncode($productIds) : $productIds) . " : " . (is_array($inventoryRows) ? jsonEncode($inventoryRows) : $inventoryRows));
		}
		return $inventoryRows;
	}

	function getProductPacks() {
		if (self::$iProductPacks === false || !is_array(self::$iProductPacks) || $GLOBALS['gClientId'] != self::$iProductPacksClientId) {
			self::$iProductPacks = array();
			self::$iProductPacksClientId = $GLOBALS['gClientId'];
			$resultSet = executeQuery("select * from product_pack_contents where product_id in (select product_id from products where client_id = ?) and product_id <> contains_product_id", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if ($row['quantity'] <= 0) {
					continue;
				}
				if (!array_key_exists($row['product_id'], self::$iProductPacks)) {
					self::$iProductPacks[$row['product_id']] = array();
				}
				if (!array_key_exists($row['contains_product_id'], self::$iProductPacks[$row['product_id']])) {
					self::$iProductPacks[$row['product_id']][$row['contains_product_id']] = 0;
				}
				self::$iProductPacks[$row['product_id']][$row['contains_product_id']] += $row['quantity'];
			}
		}
	}

	function getProductPackInventory($productId, $totalsOnly) {
		$productPackContents = self::$iProductPacks[$productId];
		if (empty($productPackContents)) {
			return false;
		}
		$productInventories = array();
		foreach ($productPackContents as $containsProductId => $quantity) {
			$inventoryRow = $this->getInventoryCounts($totalsOnly, $containsProductId);
			$productInventories[$containsProductId] = $inventoryRow[$containsProductId];
			if (!$totalsOnly) {
				$totalInventory = $this->getInventoryCounts(true, $containsProductId);
				if ($GLOBALS['gUserRow']['superuser_flag'] && !empty(getPreference('log_inventory_counts'))) {
					addDebugLog("Pack Contents for " . $productId . ": total : " . $containsProductId . " : " . (is_array($totalInventory) ? jsonEncode($totalInventory) : $totalInventory));
				}
				$productInventories[$containsProductId]['total'] = $totalInventory[$containsProductId];
			}
			if ($GLOBALS['gUserRow']['superuser_flag'] && !empty(getPreference('log_inventory_counts'))) {
				addDebugLog("Pack Contents for " . $productId . ": " . $totalsOnly . " : " . $containsProductId . " : " . (is_array($productInventories[$containsProductId]) ? jsonEncode($productInventories[$containsProductId]) : $productInventories[$containsProductId]));
			}
		}
		if (empty($productInventories)) {
			return false;
		}
		$inventoryRow = array();
		if (!$totalsOnly) {
			$inventoryRow[$productId] = array();
		}
		foreach ($productInventories as $containsProductId => $inventory) {
			if ($totalsOnly) {
				$thisProductInventory = floor($inventory / $productPackContents[$containsProductId]);
				if (!array_key_exists($productId, $inventoryRow) || $thisProductInventory < $inventoryRow[$productId]) {
					$inventoryRow[$productId] = $thisProductInventory;
				}
			} else {
				foreach ($inventory as $location => $quantity) {
					$thisProductInventory = min(floor($inventory['total'] / $productPackContents[$containsProductId]), floor($quantity / $productPackContents[$containsProductId]));
					if (!array_key_exists($location, $inventoryRow[$productId]) || $thisProductInventory < $inventoryRow[$productId][$location]) {
						$inventoryRow[$productId][$location] = $thisProductInventory;
					}
				}
			}
		}
		if (!$totalsOnly && array_key_exists("waiting", $inventoryRow[$productId])) {
			$waitingQuantity = ProductCatalog::getWaitingToShipQuantity($productId);
			$inventoryRow[$productId]['waiting'] = $waitingQuantity;
		}
		return $inventoryRow;
	}

	function getLocationAvailability($productIds = array()) {
		$useTemporaryTable = false;
		$this->getProductPacks();
		if (empty($productIds)) {
			$productIds = $this->iProductIds;
			$useTemporaryTable = true;
		}
		if (empty($productIds)) {
			return array();
		}
		if (!is_array($productIds)) {
			$productIds = array($productIds);
		}
		$productLocationAvailability = array();

		if (self::$iLocationRows === false) {
			$resultSet = executeReadQuery("select location_id from locations where inactive = 0 and internal_use_only = 0 and warehouse_location = 0 and product_distributor_id is null and client_id = ?", $GLOBALS['gClientId']);
			self::$iLocationRows = array();
			while ($row = getNextRow($resultSet)) {
				self::$iLocationRows[$row['location_id']] = $row['location_id'];
			}
			freeReadResult($resultSet);
		}
		$locations = self::$iLocationRows;
		if (self::$iInventoryCountLocationRows === false) {
			self::$iInventoryCountLocationRows = array();
			$resultSet = executeReadQuery("select * from locations where client_id = ? and internal_use_only = 0 and inactive = 0 and ignore_inventory = 0 and " .
				"(product_distributor_id is null or primary_location = 1) and location_id not in (select location_id from location_credentials where inactive = 1)", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				self::$iInventoryCountLocationRows[$row['location_id']] = $row;
			}
		}
		$locationRows = self::$iInventoryCountLocationRows;

		$nonInventoryProducts = array();
		$resultSet = executeReadQuery("select product_id from products where non_inventory_item = 1 and product_id in (" .
			(empty($this->iTemporaryTableName) || !$useTemporaryTable ? implode(",", $productIds) : "select product_id from " . $this->iTemporaryTableName) . ")");
		while ($row = getNextRow($resultSet)) {
			$nonInventoryProducts[] = $row['product_id'];
			if (!array_key_exists($row['product_id'], $productLocationAvailability)) {
				$productLocationAvailability[$row['product_id']] = array("distributor" => 99999999);
				foreach ($locations as $locationId) {
					$productLocationAvailability[$row['product_id']][$locationId] = 99999999;
				}
			}
		}

		$productInventoryRows = array();

		# First, look for product packs. These have to have inventory calculated differently

		$productPackProductIds = array();
		if (is_array(self::$iProductPacks) && !empty(self::$iProductPacks)) {
			# get random distributor location ID
			$distributorLocationId = getReadFieldFromId("location_id", "locations", "client_id", $GLOBALS['gClientId'],
				"internal_use_only = 0 and inactive = 0 and ignore_inventory = 0 and product_distributor_id is not null and primary_location = 1 and location_id not in (select location_id from location_credentials where inactive = 1)");
			foreach ($productIds as $thisProductId) {
				if (!array_key_exists($thisProductId, self::$iProductPacks)) {
					continue;
				}
				$productPackInventory = $this->getInventoryCounts(false, $thisProductId);
				foreach ($productPackInventory[$thisProductId] as $locationId => $quantity) {
					if ($locationId == "distributor") {
						if (!empty($distributorLocationId)) {
							$productInventoryRows[] = array("product_id" => $thisProductId, "location_id" => $distributorLocationId, "quantity" => $quantity);
							$productPackProductIds[] = $thisProductId;
						}
					} else {
						$productInventoryRows[] = array("product_id" => $thisProductId, "location_id" => $locationId, "quantity" => $quantity);
						$productPackProductIds[] = $thisProductId;
					}
				}
			}
		}

		$remainingProductIds = array();
		foreach ($productIds as $thisProductId) {
			if (in_array($thisProductId, $productPackProductIds)) {
				continue;
			}
			if (array_key_exists($thisProductId, self::$iProductInventoryQuantities)) {
				foreach (self::$iProductInventoryQuantities[$thisProductId] as $locationId => $quantity) {
					$productInventoryRows[] = array("product_id" => $thisProductId, "location_id" => $locationId, "quantity" => $quantity);
				}
			} else {
				$remainingProductIds[] = $thisProductId;
			}
		}

		if (!empty($remainingProductIds)) {
			$resultSet = executeReadQuery("select product_id,location_id,sum(quantity) as quantity from product_inventories where product_id in (" .
				implode(",", $remainingProductIds) . ") group by product_id,location_id");
			while ($row = getNextRow($resultSet)) {
				$productInventoryRows[] = $row;
			}
			freeReadResult($resultSet);
		}
		foreach ($productInventoryRows as $row) {
			$row['warehouse_location'] = $locationRows[$row['location_id']]['warehouse_location'];
			$row['product_distributor_id'] = $locationRows[$row['location_id']]['product_distributor_id'];
			if (in_array($row['product_id'], $nonInventoryProducts)) {
				continue;
			}
			if (!array_key_exists($row['product_id'], $productLocationAvailability)) {
				$productLocationAvailability[$row['product_id']] = array("distributor" => 0);
				foreach ($locations as $locationId) {
					$productLocationAvailability[$row['product_id']][$locationId] = 0;
				}
			}
			if (empty($row['product_distributor_id']) && empty($row['warehouse_location'])) {
				if (array_key_exists($row['location_id'], $productLocationAvailability[$row['product_id']])) {
					$productLocationAvailability[$row['product_id']][$row['location_id']] += intval($row['quantity']);
				}
			} else {
				$productLocationAvailability[$row['product_id']]['distributor'] += intval($row['quantity']);
			}
		}
		if (function_exists("_localMassageInventoryCounts")) {
			$productLocationAvailability = _localMassageInventoryCounts($productLocationAvailability, false);
		}

		return $productLocationAvailability;
	}

	public function setTruncateDescriptions($truncateDescriptions) {
		$this->iTruncateDescriptions = (!empty($truncateDescriptions));
	}

	public function setIgnoreProductGroupDescription($ignoreProductGroupDescription) {
		$this->iIgnoreProductGroupDescription = (!empty($ignoreProductGroupDescription));
	}

	public function setAddDomainName($addDomainName) {
		$this->iAddDomainName = $addDomainName;
	}

	function getPriceCalculationLog() {
		return $this->iPriceCalculationLog;
	}

	function setSendAllFields($sendAllFields) {
		$this->iSendAllFields = $sendAllFields;
	}

	function setRelatedProduct($productIds) {
		if (!is_array($productIds)) {
			$productIds = array($productIds);
		}
		foreach ($productIds as $productId) {
			if (!empty($productId) && !in_array($productId, $this->iRelatedProductIds) && is_numeric($productId)) {
				$this->iRelatedProductIds[] = $productId;
			}
		}
	}

	function setRelatedProductTypeCode($relatedProductTypeCode) {
		$this->iRelatedProductTypeId = getFieldFromId("related_product_type_id", "related_product_types", "related_product_type_code", $relatedProductTypeCode);
	}

	function setRelatedProductType($relatedProductTypeId) {
		$this->iRelatedProductTypeId = getFieldFromId("related_product_type_id", "related_product_types", "related_product_type_id", $relatedProductTypeId);
	}

	function getTemporaryTableName() {
		return $this->iTemporaryTableName;
	}

	function setGetProductSalePrice($getProductSalePrice) {
		$this->iGetProductSalePrice = $getProductSalePrice;
	}

	function setIgnoreManufacturerLogo($ignoreManufacturerLogo) {
		$this->iIgnoreManufacturerLogo = $ignoreManufacturerLogo;
	}

	function setBaseImageFilenameOnly($baseImageFilenameOnly) {
		$this->iBaseImageFilenameOnly = $baseImageFilenameOnly;
	}

	function setDefaultImage($defaultImage) {
		$this->iDefaultImage = $defaultImage;
	}

	function setDontLogSearchTerm($logSearchTerm) {
		$this->iDontLogSearchTerm = $logSearchTerm;
	}

	function addSidebarFacetCode($sidebarFacetCode) {
		$this->iSidebarFacetCodes[] = strtoupper($sidebarFacetCode);
	}

	function setSidebarFacetLimit($sidebarFacetLimit) {
		if (!empty($sidebarFacetLimit) && is_numeric($sidebarFacetLimit)) {
			$this->iSidebarFacetLimit = $sidebarFacetLimit;
		}
	}

	function addSidebarProductDataField($sidebarProductDataField) {
		$sidebarProductDataField = getFieldFromId("column_name", "column_definitions", "column_name", strtolower($sidebarProductDataField),
			"column_definition_id in (select column_definition_id from table_columns where table_id in (select table_id from tables where table_name = 'product_data'))");
		if (!empty($sidebarProductDataField)) {
			$this->iSidebarProductDataFields[] = $sidebarProductDataField;
		}
	}

	function setAllowInternalUseOnly($allow) {
		$this->iAllowInternalUseOnly = $allow;
	}

	function setSearchText($searchText) {
		$this->iSearchText = $searchText;
	}

	function returnIdsOnly($returnIdsOnly) {
		$this->iReturnIdsOnly = $returnIdsOnly;
	}

	function returnCountOnly($returnCountOnly) {
		$this->iReturnCountOnly = $returnCountOnly;
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

	function showOutOfStock($outOfStock) {
		$this->iShowOutOfStock = $outOfStock;
	}

	function needSidebarInfo($needSidebarInfo) {
		$this->iNeedSidebarInfo = $needSidebarInfo;
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
		if ($GLOBALS['gClientCount'] > 10 && $selectLimit > 10000 && !$GLOBALS['gCommandLine'] && !$GLOBALS['gPageCode'] == "SITEMAP") {
			$selectLimit = 10000;
		}
		$this->iSelectLimit = $selectLimit;
	}

	function getSelectLimit() {
		return $this->iSelectLimit;
	}

	function setLimitQuery($limitQuery) {
		$this->iLimitQuery = $limitQuery;
	}

	function setSortBy($sortBy) {
		if (in_array($sortBy, $this->iValidSorts)) {
			$this->iSortBy = $sortBy;
		} else {
			$this->iSortBy = reset($this->iValidSorts);
		}
		return $this->iSortBy;
	}

	function getSortBy() {
		return $this->iSortBy;
	}

	function ignoreProductsWithoutImages($images) {
		$this->iIgnoreProductsWithoutImages = $images;
	}

	function ignoreProductsWithoutCategory($categories) {
		$this->iIgnoreProductsWithoutCategory = $categories;
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

	function setProductTypes($productTypeIds) {
		if (!is_array($productTypeIds)) {
			$productTypeIds = array($productTypeIds);
		}
		if ($this->iAllProductTypes === false) {
			$this->iAllProductTypes = array();
			$resultSet = executeReadQuery("select * from product_types where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$this->iAllProductTypes[$row['product_type_id']] = $row['product_type_id'];
			}
			freeReadResult($resultSet);
		}
		foreach ($productTypeIds as $productTypeId) {
			if (!empty($productTypeId) && array_key_exists($productTypeId, $this->iAllProductTypes) && !in_array($productTypeId, $this->iProductTypeIds)) {
				$this->iProductTypeIds[] = $productTypeId;
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

	function setManufacturers($manufacturerIds) {
		if (!is_array($manufacturerIds)) {
			$manufacturerIds = array($manufacturerIds);
		}
		foreach ($manufacturerIds as $manufacturerId) {
			$manufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_id", $manufacturerId);
			if (!empty($manufacturerId) && !in_array($manufacturerId, $this->iManufacturerIds)) {
				$this->iManufacturerIds[] = $manufacturerId;
			}
		}
	}

	function setManufacturerTags($manufacturerTagIds) {
		if (!is_array($manufacturerTagIds)) {
			$manufacturerTagIds = array($manufacturerTagIds);
		}
		foreach ($manufacturerTagIds as $manufacturerTagId) {
			$resultSet = executeQuery("select * from product_manufacturer_tag_links where product_manufacturer_tag_id = ? and product_manufacturer_id in (select product_manufacturer_id from product_manufacturers where client_id = ?)", $manufacturerTagId, $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (!in_array($row['product_manufacturer_id'], $this->iManufacturerIds)) {
					$this->iManufacturerIds[] = $row['product_manufacturer_id'];
				}
			}
		}
	}

	function setLocations($locationIds) {
		if (!is_array($locationIds)) {
			$locationIds = array($locationIds);
		}
		foreach ($locationIds as $locationId) {
			$locationId = getFieldFromId("location_id", "locations", "location_id", $locationId, "inactive = 0");
			if (!empty($locationId) && !in_array($locationId, $this->iLocationIds)) {
				$this->iLocationIds[] = $locationId;
			}
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

	function setTagGroups($productTagGroupIds) {
		if (!is_array($productTagGroupIds)) {
			$productTagGroupIds = array($productTagGroupIds);
		}
		foreach ($productTagGroupIds as $productTagGroupId) {
			$resultSet = executeQuery("select * from product_tags where product_tag_group_id = ? and client_id = ?", $productTagGroupId, $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (!in_array($row['product_tag_id'], $this->iTagIds)) {
					$this->iTagIds[] = $row['product_tag_id'];
				}
			}
		}
	}

	function setSearchGroups($searchGroupIds) {
		if (!is_array($searchGroupIds)) {
			$searchGroupIds = array($searchGroupIds);
		}
		foreach ($searchGroupIds as $searchGroupId) {
			$searchGroupId = getFieldFromId("search_group_id", "search_groups", "search_group_id", $searchGroupId, "inactive = 0");
			if (!empty($searchGroupId) && !in_array($searchGroupId, $this->iSearchGroupIds)) {
				$this->iSearchGroupIds[] = $searchGroupId;
			}
		}
	}

	function setContributors($contributorIds) {
		if (!is_array($contributorIds)) {
			$contributorIds = array($contributorIds);
		}
		foreach ($contributorIds as $contributorId) {
			$contributorId = getFieldFromId("contributor_id", "contributors", "contributor_id", $contributorId, "inactive = 0");
			if (!empty($contributorId) && !in_array($contributorId, $this->iContributorIds)) {
				$this->iContributorIds[] = $contributorId;
			}
		}
	}

	function addCompliantState($state) {
		$this->iCompliantStates[] = $state;
	}

	function setPushInStockToTop($value) {
		$this->iPushInStockToTop = $value;
	}

	function includeProductTagsWithNoStartDate($include) {
		$this->iIncludeTagsWithNoStartDate = $include;
	}

    function setProductFacets($productFacetIds) {
        if (!is_array($productFacetIds)) {
            $productFacetIds = array($productFacetIds);
        }
        foreach ($productFacetIds as $productFacetId) {
            $productFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_id", $productFacetId);
            if (!empty($productFacetId) && !in_array($productFacetId, $this->iProductFacetIds)) {
                $this->iProductFacetIds[] = $productFacetId;
            }
        }
    }

	function setFacetOptions($productFacetOptionIds) {
		if (!is_array($productFacetOptionIds)) {
			$productFacetOptionIds = array($productFacetOptionIds);
		}
		foreach ($productFacetOptionIds as $productFacetOptionId) {
			$productFacetOptionId = getFieldFromId("product_facet_option_id", "product_facet_options", "product_facet_option_id", $productFacetOptionId);
			if (!empty($productFacetOptionId) && !in_array($productFacetOptionId, $this->iFacetOptionIds)) {
				$this->iFacetOptionIds[] = $productFacetOptionId;
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

	function setSpecificProductIds($productIds) {
		if (is_array($productIds)) {
			$this->iSpecificProductIds = array_filter($productIds);
		} else {
			$this->iSpecificProductIds = array_filter(explode(",", $productIds));
		}
	}

	function getQueryString() {
		return $this->iQuery;
	}

	function getProducts() {
		$totalTime = 0;
		$startTime = getMilliseconds();
		$this->iQueryTime = "";
		if (!in_array($this->iSortBy, $this->iValidSorts)) {
			$this->iSortBy = reset($this->iValidSorts);
		}
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

		if (!empty($this->iCompliantStates)) {
			$parameters = array_merge(array($GLOBALS['gClientId']), $this->iCompliantStates);			 
			$resultSet = executeQuery("select product_department_id from product_department_restrictions where product_department_id in (select product_department_id from product_departments where client_id = ?) and " .
				"state in (" . implode(",", array_fill(0, count($this->iCompliantStates), "?")) . ")", $parameters);		
			while ($row = getNextRow($resultSet)) {
				$this->iExcludeDepartments[] = $row['product_department_id'];
			}
			$resultSet = executeQuery("select product_category_id from product_category_restrictions where product_category_id in (select product_category_id from product_categories where client_id = ?) and " .
				"state in (" . implode(",", array_fill(0, count($this->iCompliantStates), "?")) . ")", $parameters);
			while ($row = getNextRow($resultSet)) {
				$this->iExcludeCategories[] = $row['product_category_id'];
			}
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

		if ($this->iSortBy == "tagged_order" && count($this->iTagIds) != 1) {
			$this->iSortBy = "";
		}
		if (empty($this->iSortBy)) {
			$this->iSortBy = "relevance";
		}

		$pushInStockToTop = $this->iPushInStockToTop;
		$query = "select products.product_id as primary_product_id," . ($this->iReturnIdsOnly || $this->iReturnCountOnly ? "products.product_id" : "products.*,product_data.*," .
				($GLOBALS['gPrimaryDatabase']->viewExists("view_of_additional_product_data") ? "view_of_additional_product_data.*," : "") .
				"(select product_category_id from product_category_links where product_id = products.product_id and product_category_id in (select product_category_id from product_categories where inactive = 0 and internal_use_only = 0) order by sequence_number limit 1) product_category_id," .
				"(select image_identifier from product_remote_images where product_id = products.product_id and image_identifier is not null order by primary_image desc,product_remote_image_id limit 1) remote_image_identifier," .
				"(select link_url from product_remote_images where product_id = products.product_id and link_url is not null order by primary_image desc,product_remote_image_id limit 1) remote_image_url," .
				"(SELECT group_concat(product_category_id) FROM product_category_links WHERE product_id = products.product_id order by sequence_number) product_category_ids," .
				"(SELECT group_concat(concat_ws('|',location_id,quantity)) FROM product_inventories WHERE product_id = products.product_id) inventory_quantities," .
				"if ((select count(*) from product_inventories where product_id = products.product_id and quantity > 0 and location_id in (select location_id from locations where internal_use_only = 0 and inactive = 0)) > 0,100,0) inventory_available," .
				"(SELECT group_concat(product_tag_id) FROM product_tag_links WHERE (start_date IS NULL OR start_date <= current_date) AND (expiration_date IS NULL OR expiration_date >= current_date) AND product_id = products.product_id) product_tag_ids," .
				"(SELECT group_concat(product_facet_option_id) FROM product_facet_values WHERE product_id = products.product_id) product_facet_option_ids");
		$query .= ",(products.search_multiplier + (select coalesce(sum(search_multiplier),0) from product_categories where inactive = 0 and product_category_id in (select product_category_id from product_category_links where product_id = products.product_id)) + " .
			"(select coalesce(sum(search_multiplier),0) from product_departments where inactive = 0 and product_department_id in (select product_department_id from product_category_departments where product_category_id in (select product_category_id from product_category_links where product_id = products.product_id))) + " .
			"(select coalesce(max(search_multiplier),0) from locations where inactive = 0 and location_id in (select location_id from product_inventories where product_id = products.product_id and quantity > 0)) + " .
			"(select coalesce(max(search_multiplier),0) from product_tags where inactive = 0 and product_tag_id in (select product_tag_id from product_tag_links where product_id = products.product_id and (start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date > current_date))) + " .
			"(select coalesce(sum(search_multiplier),0) from product_facets where inactive = 0 and product_facet_id in (select product_facet_id from product_facet_values where product_id = products.product_id)) + " .
			(empty($pushInStockToTop) ? "" : "if((select count(*) from product_inventories where product_id = products.product_id and quantity > 0 and location_id in (select location_id from locations where internal_use_only = 0 and inactive = 0)) > 0,1000000,0) + ") .
			"(select coalesce(sum(search_multiplier),0) from product_manufacturers where inactive = 0 and product_manufacturer_id = products.product_manufacturer_id)) relevance_multiplier";
		$query .= ",(select group_concat(concat(product_group_id,'|',product_option_id)) from product_group_variants join product_group_variant_choices using (product_group_variant_id) where product_id = products.product_id and product_option_id in (select product_option_id from product_options where show_one = 1)) as product_group_options," .
			"(select description from product_groups where use_group_description = 1 and product_group_id in (select product_group_id from product_group_variants where product_id = products.product_id) limit 1) as product_group_description," .
			"(select round(avg(rating),1) from product_reviews where product_id = products.product_id and rating is not null and requires_approval = 0 and inactive = 0) as product_rating," .
			"(select count(*) from product_reviews where product_id = products.product_id and rating is not null and requires_approval = 0 and inactive = 0) as rating_count";
		if (!empty($this->iRelatedProductIds)) {
			$query .= ",(select min(sort_order) from related_products where product_id = products.product_id and associated_product_id in (" .
				implode(",", $this->iRelatedProductIds) . ")" . (empty($this->iRelatedProductTypeId) ? "" : " and related_product_type_id <=> " . $this->iRelatedProductTypeId) . ") as related_product_sort_order";
		}
		$parameters = array();
		if ($this->iSortBy == "tagged_order") {
			$query .= ",(select sequence_number from product_tag_links where product_id = products.product_id and product_tag_id = ?) as product_tag_sequence";
			$parameters = $this->iTagIds;
		}
		if (!empty($this->iSearchGroupIds) && count($this->iSearchGroupIds) == 1) {
			$this->iSortBy = "search_group";
			$query .= ",(select sort_order from search_group_products where product_id = products.product_id and search_group_id = ?) as search_group_order";
			$parameters[] = $this->iSearchGroupIds[0];
		}
		if (!empty($originalSearchTerm)) {
			$query .= ",(if (products.description = ?,100,0)) as description_relevance";
			$parameters[] = $originalSearchTerm;
		} else {
			$query .= ",0 as description_relevance";
		}
		if (empty($searchWords)) {
			$query .= ",(100 / if(products.sort_order = 0,.5,products.sort_order)) as relevance";
		} else {
			$query .= ",(select sum(search_value) from product_search_word_values where product_id = products.product_id and " .
				"product_search_word_id in (select product_search_word_id from product_search_words where client_id = ? and search_term in (" . implode(",", array_fill(0, count($searchWords), "?")) . "))) as relevance";
			$parameters[] = $GLOBALS['gClientId'];
			$parameters = array_merge($parameters, $searchWords);
		}
		$query .= ",product_manufacturers.description as manufacturer_name,(select image_id from contacts where contact_id = product_manufacturers.contact_id) as manufacturer_image_id ";
		$query .= "from products left outer join product_manufacturers using (product_manufacturer_id) left outer join product_data using (product_id)";
		if ($GLOBALS['gPrimaryDatabase']->viewExists("view_of_additional_product_data")) {
			$query .= " join view_of_additional_product_data using (product_id)";
		}
		$whereStatement = "";
		if (!$this->iIgnoreClient) {
			if ($GLOBALS['gClientId'] != $GLOBALS['gDefaultClientId']) {
				$tableList = explode(",", getPreference("DEFAULT_CLIENT_CONTROL_TABLES"));
				if (in_array("products", $tableList)) {
					$whereStatement = "(products.client_id = ? or products.client_id = ?)";
					$parameters[] = $GLOBALS['gClientId'];
					$parameters[] = $GLOBALS['gDefaultClientId'];
				}
			}
			if (empty($whereStatement)) {
				$whereStatement = "products.client_id = ?";
				$parameters[] = $GLOBALS['gClientId'];
			}
		}
		$whereStatement .= (empty($whereStatement) ? "" : " and ") . "products.inactive = 0  and products.not_searchable = 0";
		if (!$GLOBALS['gInternalConnection'] && !$this->iAllowInternalUseOnly) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "products.internal_use_only = 0";
		}
		$excludedProductCategories = array();
		$resultSet = executeReadQuery("select product_category_id from product_categories where client_id = ? and (cannot_sell = 1" .
			(!$GLOBALS['gInternalConnection'] && !$this->iAllowInternalUseOnly ? " or product_category_code = 'INTERNAL_USE_ONLY'" : "") . ")", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$excludedProductCategories[] = $row['product_category_id'];
		}
		freeReadResult($resultSet);
		if (!empty($excludedProductCategories)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "products.product_id not in (select product_id from product_category_links where product_category_id in (" . implode(",", $excludedProductCategories) . "))";
		}

		$excludedProductTags = array();
		$resultSet = executeReadQuery("select product_tag_id from product_tags where client_id = ? and (cannot_sell = 1" .
			($GLOBALS['gLoggedIn'] ? (empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or product_tag_id in (select product_tag_id from user_type_product_tag_restrictions where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") : " or requires_user = 1") . ")", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$excludedProductTags[] = $row['product_tag_id'];
		}
		freeReadResult($resultSet);
		if (!empty($excludedProductTags)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "products.product_id not in (select product_id from product_tag_links where product_tag_id in (" . implode(",", $excludedProductTags) . "))";
		}

		$inactiveCategory = getFieldFromId("product_category_id", "product_categories", "product_category_code", "INACTIVE");
		if (!empty($inactiveCategory)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "products.product_id not in (select product_id from product_category_links where product_category_id = " . $inactiveCategory . ")";
		}
		$discontinuedCategory = getFieldFromId("product_category_id", "product_categories", "product_category_code", "DISCONTINUED");
		if (!empty($discontinuedCategory)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "products.product_id not in (select product_id from product_category_links where product_category_id = " . $discontinuedCategory . ")";
		}
		$notSearchableCategory = getFieldFromId("product_category_id", "product_categories", "product_category_code", "NOT_SEARCHABLE");
		if (!empty($notSearchableCategory)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "products.product_id not in (select product_id from product_category_links where product_category_id = " . $notSearchableCategory . ")";
		}
		if ($this->iIgnoreProductsWithoutImages) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "products.image_id is not null";
		}
		if ($this->iIgnoreProductsWithoutCategory) {
			// $whereStatement .= (empty($whereStatement) ? "" : " and ") . "exists (select product_id from product_category_links where product_id = products.product_id)";
		}
		if (!empty($this->iProductTypeIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "product_type_id in (" . implode(",", $this->iProductTypeIds) . ")";
		}
		$orderUpsellProductTypeId = getCachedData("order_upsell_product_type_id", "");
		if ($orderUpsellProductTypeId === false) {
			$orderUpsellProductTypeId = getFieldFromId("product_type_id", "product_types", "product_type_code", "ORDER_UPSELL_PRODUCT");
			if (empty($orderUpsellProductTypeId)) {
				$orderUpsellProductTypeId = 0;
			}
			setCachedData("order_upsell_product_type_id", "", $orderUpsellProductTypeId, 168);
		}
		if (!empty($orderUpsellProductTypeId)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(product_type_id is null or product_type_id <> " . $orderUpsellProductTypeId . ")";
		}
		if (!empty($this->iCategoryIds)) {
			// $whereStatement .= (empty($whereStatement) ? "" : " and ") . "exists (select product_id from product_category_links where product_id = products.product_id and product_category_id in (" . implode(",", $this->iCategoryIds) . "))";
		}
		if (!empty($this->iManufacturerIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "product_manufacturer_id in (" . implode(",", $this->iManufacturerIds) . ")";
		}
		if (!empty($GLOBALS['gUserRow']['user_type_id'])) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "product_manufacturer_id not in (select product_manufacturer_id from user_type_product_manufacturer_restrictions where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")";
		}

		if (!empty($this->iExcludeCategories)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "products.product_id not in (select product_id from product_category_links where product_category_id in (" . implode(",", $this->iExcludeCategories) . "))";
		}
		if (!empty($this->iExcludeManufacturers)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(products.product_manufacturer_id is null or (products.product_manufacturer_id not in (" . implode(",", $this->iExcludeManufacturers) . ")))";
		}
		if (!$GLOBALS['gLoggedIn']) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(products.product_manufacturer_id is null or product_manufacturers.requires_user = 0)";
		}
		$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(products.product_manufacturer_id is null or (product_manufacturers.cannot_sell = 0" . ($this->iAllowInternalUseOnly ? "" : " and product_manufacturers.internal_use_only = 0") . "))";
		if (!empty($this->iRelatedProductIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "products.product_id in (select associated_product_id from related_products where product_id in (" .
				implode(",", $this->iRelatedProductIds) . ")" . (empty($this->iRelatedProductTypeId) ? "" : " and related_product_type_id <=> ?") . ")";
			if (!empty($this->iRelatedProductTypeId)) {
				$parameters[] = $this->iRelatedProductTypeId;
			}
		}
		if (!empty($this->iCompliantStates)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "products.product_id not in (select product_id from product_restrictions where state in (" . implode(",", array_fill(0, count($this->iCompliantStates), "?")) . "))";
			foreach ($this->iCompliantStates as $thisState) {
				$parameters[] = $thisState;
			}
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
					"product_id in (select product_id from product_search_word_values where product_search_word_id in " .
					"(select product_search_word_id from product_search_words where client_id = ? and search_term in (" . implode(",", array_fill(0, count($thisWordArray), "?")) . ")))";
				$parameters[] = $GLOBALS['gClientId'];
				$parameters = array_merge($parameters, $thisWordArray);
			}
		}
        if (!empty($this->iProductFacetIds) && !empty($this->iFacetOptionIds)) {
            $facetIdParameters = "";
            $facetOptionIdParameters = "";
            foreach ($this->iProductFacetIds as $facetId) {
                $facetIdParameters .= (empty($facetIdParameters) ? "" : ",") . "?";
                $parameters[] = $facetId;
            }
            foreach ($this->iFacetOptionIds as $facetOptionId) {
                $facetOptionIdParameters .= (empty($facetOptionIdParameters) ? "" : ",") . "?";
                $parameters[] = $facetOptionId;
            }
            $whereStatement .= (empty($whereStatement) ? "" : " and ") . "exists (select product_id from product_facet_values " .
                "where product_id = products.product_id and product_facet_id in (" . $facetIdParameters . ") and " .
                "product_facet_option_id in (" . $facetOptionIdParameters . ") group by product_facet_values.product_id " .
                "having count(distinct product_facet_id) = ?)";
            $parameters[] = count($this->iProductFacetIds);
        } elseif (!empty($this->iFacetOptionIds)) {
            $parameterString = "";
            foreach ($this->iFacetOptionIds as $facetOptionId) {
                $parameterString .= (empty($parameterString) ? "" : ",") . "?";
                $parameters[] = $facetOptionId;
            }
            $whereStatement .= (empty($whereStatement) ? "" : " and ") . "exists (select product_id from product_facet_values " .
                "where product_id = products.product_id and product_facet_option_id in (" . $parameterString . "))";
        }
		if (!empty($this->iTagIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "products.product_id in (select product_id from product_tag_links " .
				"where product_tag_id in (" . implode(",", $this->iTagIds) . ") and " .
				"(expiration_date is null or expiration_date >= current_date) and " .
				"(start_date is " . ($this->iIncludeTagsWithNoStartDate ? "null or " : "not null and") . "start_date <= current_date))";
		}
		if (!empty($this->iSearchGroupIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "exists (select product_id from search_group_products " .
				"where product_id = products.product_id and search_group_id in (" . implode(",", $this->iSearchGroupIds) . "))";
		}
		if (!empty($this->iContributorIds)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "exists (select product_id from product_contributors " .
				"where product_id = products.product_id and contributor_id in (" . implode(",", $this->iContributorIds) . "))";
		}
		if (empty(getPreference("RETAIL_STORE_NEVER_OUT_OF_STOCK"))) {
			if (!empty($this->iLocationIds)) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(non_inventory_item = 1 or exists (select product_id from product_pack_contents where product_id = products.product_id) or exists (select product_id from product_inventories where " .
					"product_id = products.product_id and " . ($this->iShowOutOfStock ? "" : "quantity > 0 and ") . "location_id in (" . implode(",", $this->iLocationIds) . ")))";
			} elseif (!$this->iShowOutOfStock) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(non_inventory_item = 1 or exists (select product_id from product_inventories where " .
					"product_id = products.product_id and quantity > 0))";
			} elseif (!$this->iIncludeNoInventoryProducts) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "(non_inventory_item = 1 or exists (select product_id from product_pack_contents where product_id = products.product_id) or exists (select product_id from product_inventories where " .
					"product_id = products.product_id and " . ($this->iShowOutOfStock ? "" : "quantity > 0 and ") . "location_id in " .
					"(select location_id from locations where internal_use_only = 0 and inactive = 0 and ignore_inventory = 0 and (product_distributor_id is null or primary_location = 1)" . ($this->iIgnoreClient ? "" : " and client_id = ?") . ")))";
				if (!$this->iIgnoreClient) {
					$parameters[] = $GLOBALS['gClientId'];
				}
			}
		}
		if (!empty(getPreference("EXCLUDE_BASE_COST_ZERO"))) {
			// $whereStatement .= (empty($whereStatement) ? "" : " and ") . "products.base_cost > 0";
		}
		if (!empty($this->iSpecificProductIds)) {
			$productIdList = "";
			foreach ($this->iSpecificProductIds as $thisProductId) {
				if (!empty($thisProductId) && is_numeric($thisProductId)) {
					$productIdList .= (empty($productIdList) ? "" : ",") . $thisProductId;
				}
			}
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "products.product_id in (" . $productIdList . ")";
		}
		if (!empty($whereStatement)) {
			$query .= " where " . $whereStatement;
		}
		switch ($this->iSortBy) {
			case "alphabetical":
				$query .= " order by products.description";
				break;
			case "alphabetical_reverse":
				$query .= " order by products.description desc";
				break;
			case "price_low":
				$query .= " order by list_price";
				break;
			case "price_high":
				$query .= " order by list_price desc";
				break;
			case "manufacturer":
				$query .= " order by manufacturer_name";
				break;
			case "manufacturer_reverse":
				$query .= " order by manufacturer_name desc";
				break;
			case "sku":
				$query .= " order by manufacturer_sku";
				break;
			case "sku_reverse":
				$query .= " order by manufacturer_sku desc";
				break;
			case "tagged_order":
				if ($pushInStockToTop) {
					$query .= " order by inventory_available desc,product_tag_sequence";
				} else {
					$query .= " order by product_tag_sequence";
				}
				break;
			case "search_group":
				$query .= " order by search_group_order";
				break;
			case "random":
				$query .= " order by rand()";
				break;
			default:
				# $query .= " order by ((relevance + description_relevance) * relevance_multiplier) desc";
				break;
		}
		if ($this->iLimitQuery) {
			$query .= " limit " . $this->iSelectLimit;
		}
		$this->iQuery = $query;
		// product getting here 
		// echo $this->iQuery;
		// die;
		foreach ($parameters as $fieldName => $fieldValue) {
			$this->iQuery .= ", " . $fieldName . " = " . $fieldValue;
		}
		$endTime = getMilliseconds();
		$this->iQueryTime .= "query price types: " . round(($endTime - $startTime) / 1000, 2) . "\n";
		$totalTime += ($endTime - $startTime);
		$startTime = getMilliseconds();

		$urlAliasTypeCode = getUrlAliasTypeCode("products", "product_id", "id");

// query printed here 
		// echo $query;
		// die;
		$resultSet = executeReadQuery($query, $parameters);
// print_r( $resultSet);
// die;
		$productArray = array();
		$resultCount = 0;
		$this->iResultCount = $resultSet['row_count'];
		
		$endTime = getMilliseconds();
		$this->iQueryTime .= "query run: " . round(($endTime - $startTime) / 1000, 2) . "\n";
		if ($this->iReturnCountOnly) {
			return $this->iResultCount;
		}
		$totalTime += ($endTime - $startTime);
		$startTime = getMilliseconds();
		$productCodeArray = array();
		$imageIdArray = array();
		if ($this->iSendAllFields) {
			$unsetFields = array();
		} else {
			$unsetFields = array("date_created", "detailed_description", "time_changed", "version", "primary_product_id", "low_inventory_quantity", "low_inventory_surcharge_amount", "client_id", "time_changed",
				"virtual_product", "cart_minimum", "cart_maximum", "order_maximum", "date_created", "user_group_id", "product_group_options", "reindex",
				"error_message", "custom_product", "not_taxable", "serializable", "internal_use_only", "inactive", "notes", "version", "product_data_id");
		}
		if ($resultSet['row_count'] > $this->iTemporaryTableThreshold) {
			$this->iTemporaryTableName = "temporary_products_" . date("Ymd") . "_" . strtolower(getRandomString(12));
			executeQuery("create table " . $this->iTemporaryTableName . "(product_id int not null,primary key (product_id))");
		}
		$temporaryIndexQuery = "";

		$maximumDescriptionLength = getPreference("RETAIL_STORE_MAXIMUM_DESCRIPTION_LENGTH");
		if (strlen($maximumDescriptionLength) == 0) {
			$maximumDescriptionLength = 50;
		}

		$productGroupOptions = array();
		$this->iUniqueManufacturers = array();
		while ($row = getNextRow($resultSet)) {
			$row['product_id'] = $row['primary_product_id'];
			if (!empty($row['product_group_description']) && empty($this->iIgnoreProductGroupDescription)) {
				$row['description'] = $row['product_group_description'];
			}

			$productInventoryQuantities = explode(",", $row['inventory_quantities']);
			foreach ($productInventoryQuantities as $thisQuantity) {
				if (!array_key_exists($row['product_id'], self::$iProductInventoryQuantities)) {
					self::$iProductInventoryQuantities[$row['product_id']] = array();
				}
				if (!empty($thisQuantity)) {
					$parts = explode("|", $thisQuantity);
					self::$iProductInventoryQuantities[$row['product_id']][$parts[0]] = $parts[1];
				}
			}

			if ($this->iTruncateDescriptions && !empty($maximumDescriptionLength) && mb_strlen($row['description']) > $maximumDescriptionLength) {
				$row['description'] = mb_substr($row['description'], 0, $maximumDescriptionLength) . "...";
			}

			if (!empty($row['product_manufacturer_id']) && !array_key_exists($row['product_manufacturer_id'], $this->iUniqueManufacturers)) {
				$this->iUniqueManufacturers[$row['product_manufacturer_id']] = $row['product_manufacturer_id'];
			}
			$row['product_group'] = false;
			if (!empty($row['product_group_options'])) {
				if (in_array($row['product_group_options'], $productGroupOptions)) {
					continue;
				}
				$productGroupOptions[] = $row['product_group_options'];
				$row['product_group'] = true;
			}
			if ($this->iSortBy == "tagged_order") {
				$row['relevance'] = (2000000000 - $row['product_tag_sequence']);
				if ($pushInStockToTop && (!empty($row['inventory_available']) || !empty($row['non_inventory_item']))) {
					$row['relevance'] += 1000000;
				}
			} elseif ($this->iSortBy == "search_group") {
				$row['relevance'] = $row['search_group_order'];
			} else {
				if (empty($row['relevance'])) {
					$row['relevance'] = 0;
				}
				if (empty($row['description_relevance'])) {
					$row['description_relevance'] = 0;
				}
				if (empty($row['relevance_multiplier'])) {
					$row['relevance_multiplier'] = 1;
				}
				if (empty($row['related_product_sort_order'])) {
					$row['related_product_sort_order'] = 0;
				}
				$row['relevance'] += 1000000 - $row['related_product_sort_order'];
				$row['relevance'] += $row['description_relevance'];
				$row['relevance'] = $row['relevance'] * $row['relevance_multiplier'];
			}
			$row['remote_image'] = "";
			if (empty($row['image_id']) && !empty($row['remote_identifier']) && !empty($row['remote_image_identifier'])) {
				$row['remote_image'] = $row['remote_identifier'] . "-" . $row['remote_image_identifier'];
			}
			$row['location_availability'] = "";
			if ($this->iIgnoreMapProducts === false) {
				$this->iIgnoreMapProducts = array();
				$customFieldId = getReadFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "IGNORE_MAP", "inactive = 0 and " .
					"custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'PRODUCTS')");
				$ignoreMapSet = executeReadQuery("select primary_identifier from custom_field_data where custom_field_id = ? and text_data = '1'", $customFieldId);
				while ($ignoreMapRow = getNextRow($ignoreMapSet)) {
					$this->iIgnoreMapProducts[$ignoreMapRow['primary_identifier']] = $ignoreMapRow['primary_identifier'];
				}
				freeReadResult($ignoreMapSet);
				$ignoreMapSet = executeReadQuery("select product_id from product_tag_links where product_tag_id = (select product_tag_id from product_tags where product_tag_code = 'IGNORE_MAP' and client_id = ?) and " .
					"(start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date >= current_date)", $GLOBALS['gClientId']);
				while ($ignoreMapRow = getNextRow($ignoreMapSet)) {
					$this->iIgnoreMapProducts[$ignoreMapRow['product_id']] = $ignoreMapRow['product_id'];
				}
				freeReadResult($ignoreMapSet);
			}
			if (is_array($this->iIgnoreMapProducts) && array_key_exists($row['product_id'], $this->iIgnoreMapProducts)) {
				$row['manufacturer_advertised_price'] = "";
			}
// print_r($unsetFields);die;
			foreach ($unsetFields as $thisField) {
				$row[$thisField] = null;
				unset($row[$thisField]);
			}

			$resultCount++;
			// print_r( $row);die;
			$this->iProductIds[] = $row['product_id'];
			if (!empty($this->iTemporaryTableName)) {
				$temporaryIndexQuery .= (empty($temporaryIndexQuery) ? "" : ",") . "(" . $row['product_id'] . ")";
				if (strlen($temporaryIndexQuery) > 5000) {
					executeQuery("insert ignore into " . $this->iTemporaryTableName . " (product_id) values " . $temporaryIndexQuery);
					$temporaryIndexQuery = "";
				}
			}
			if (function_exists("customProductSalePrice")) {
				$productCodeArray[$row['product_code']] = $row['product_code'];
			}
			if (count($productArray) >= $this->iSelectLimit) {
				continue;
			}
			if ($resultCount <= $this->iOffset) {
				continue;
			}
			$row['product_detail_link'] = "/" . (empty($urlAliasTypeCode) || empty($row['link_name']) ? "product-details?id=" . $row['product_id'] : $urlAliasTypeCode . "/" . $row['link_name']);
			if ($this->iReturnIdsOnly) {
				$productArray[] = $row['product_id'];
			} else {
				if (!$this->iIgnoreManufacturerLogo) {
					$row['logo_image_url'] = getImageFilename($row['manufacturer_image_id']);
				}
				$row['map_enforced'] = false;
				$productArray[] = new ProductSearchResult($row);
			}
			if (!empty($row['image_id'])) {
				$imageIdArray[] = $row['image_id'];
			}
		}
		// print_r($imageIdArray);die;
		freeReadResult($resultSet);

		if (!empty($temporaryIndexQuery)) {
			executeQuery("insert ignore into " . $this->iTemporaryTableName . " (product_id) values " . $temporaryIndexQuery);
		}
		// print_r($this->iProductIds);die;
		$GLOBALS['gProductSearchResultsCount'] = $resultCount;
		$GLOBALS['gProductSearchResultsProductIds'] = $this->iProductIds;
		if (!empty($this->iTemporaryTableName)) {
			$count = 0;
			while (true) {
				$count++;
				if ($count > 20) {
					break;
				}
				usleep(10000);
				$resultSet = executeReadQuery("select count(*) from " . $this->iTemporaryTableName);
				if ($row = getNextRow($resultSet)) {
					if ($row['count(*)'] > (count($this->iProductIds) - 1000)) {
						break;
					}
				}
			}
		}
		$endTime = getMilliseconds();
		$this->iQueryTime .= "post query: " . round(($endTime - $startTime) / 1000, 2) . "\n";
		$totalTime += ($endTime - $startTime);
		$startTime = getMilliseconds();

		$imageRowArray = array();
		if (!empty($imageIdArray)) {
			$resultSet = executeReadQuery("select client_id,image_id,hash_code,extension,os_filename,remote_storage from images where image_id in (" . implode(",", $imageIdArray) . ")");
			while ($row = getNextRow($resultSet)) {
				$imageRowArray[$row['image_id']] = $row;
			}
			freeReadResult($resultSet);
		}
		// print_r($this->iReturnIdsOnly);die;
		if (!$this->iReturnIdsOnly) {
			foreach ($productArray as $index => $productResult) {
				$row = $productResult->getProductRow();
				if (!empty($row['remote_image'])) {
					if ($this->iSendAllFields) {
						$productResult->setValue("image_url", "https://images.coreware.com/images/products/" . $row['remote_image'] . ".jpg");
						foreach ($GLOBALS['gImageTypes'] as $imageType) {
							$productResult->setValue(strtolower($imageType['image_type_code']) . '_image_url', "https://images.coreware.com/images/products/" . $imageType . "-" . $row['remote_image'] . ".jpg");
						}
					} elseif ($this->iBaseImageFilenameOnly) {
						$productResult->setValue("image_base_filename", "");
					} else {
						$productResult->setValue("image_url", "");
						foreach ($GLOBALS['gImageTypes'] as $imageType) {
							$productResult->setValue(strtolower($imageType['image_type_code']) . '_image_url', "");
						}
					}
					$productArray[$index] = $productResult;
					continue;
				} elseif (!empty($row['remote_image_url'])) {
					$productResult->setValue("image_url", $row['remote_image_url']);
					continue;
				}
				$parameters = array();
				if ($this->iDefaultImage !== false) {
					$parameters['default_image'] = $this->iDefaultImage;
				}
				if (!empty($row['image_id']) && array_key_exists($row['image_id'], $imageRowArray)) {
					$parameters['image_row'] = $imageRowArray[$row['image_id']];
				}
				if ($this->iBaseImageFilenameOnly) {
					$parameters['base_filename_only'] = true;
					$productResult->setValue("image_base_filename", getImageFilename($row['image_id'], $parameters));
				} else {
					$imageUrl = getImageFilename($row['image_id'], $parameters);
					if ($this->iAddDomainName) {
						$imageUrl = "https://" . $_SERVER['HTTP_HOST'] . (substr($imageUrl, 0, 1) == "/" ? "" : "/") . $imageUrl;
					}
					$productResult->setValue("image_url", $imageUrl);
					foreach ($GLOBALS['gImageTypes'] as $imageType) {
						$imageUrl = getImageFilename($row['image_id'], array("default_image" => $row['image_url'], "image_type" => strtolower($imageType['image_type_code'])));
						if ($this->iAddDomainName) {
							$imageUrl = "https://" . $_SERVER['HTTP_HOST'] . (substr($imageUrl, 0, 1) == "/" ? "" : "/") . $imageUrl;
						}
						$productResult->setValue(strtolower($imageType['image_type_code']) . '_image_url', $imageUrl);
					}
				}
				$productArray[$index] = $productResult;
			}
			// product data 
			
		}
		$endTime = getMilliseconds();
		$this->iQueryTime .= "product images: " . round(($endTime - $startTime) / 1000, 2) . "\n";
		$totalTime += ($endTime - $startTime);
		$startTime = getMilliseconds();
		$callForPriceText = getFragment("CALL_FOR_PRICE");
		if (empty($callForPriceText)) {
			$callForPriceText = getLanguageText("Call for Price");
		}

		if ($this->iGetProductSalePrice) {
			$showInStoreOnlyPrice = getPreference("SHOW_IN_STORE_ONLY_PRICE");
			$this->iProductInventoryCounts = $this->getInventoryCounts(true);
			$endTime = getMilliseconds();
			$this->iQueryTime .= "inventory counts: " . round(($endTime - $startTime) / 1000, 2) . "\n";
			$totalTime += ($endTime - $startTime);
			$startTime = getMilliseconds();

			$productSalePrices = array();
			$GLOBALS['gHideProductsWithNoPrice'] = getPreference("HIDE_PRODUCTS_NO_PRICE");
			$GLOBALS['gHideProductsWithZeroPrice'] = getPreference("HIDE_PRODUCTS_ZERO_PRICE");
			if (function_exists("customProductSalePrice")) {
				$productSalePrices = customProductSalePrice(array("product_code_array" => $productCodeArray));
				if (empty($productSalePrices)) {
					$productSalePrices = array();
				}
			}
			$endTime = getMilliseconds();
			$this->iQueryTime .= "custom prices: " . round(($endTime - $startTime) / 1000, 2) . "\n";
			$totalTime += ($endTime - $startTime);
			$startTime = getMilliseconds();
			$cachedCount = 0;
			$storedCount = 0;
			$recalculatedCount = 0;
			$noPriceCount = 0;
			$recalculatedProductIds = "";
			// print_r($productArray);die;
			foreach ($productArray as $index => $productResult) {
				$row = $productResult->getProductRow();
				// print_r($row);die;
				$salePrice = false;
				if (array_key_exists($row['product_code'], $productSalePrices)) {
					$salePriceInfo = $productSalePrices[$row['product_code']];
					if (!is_array($salePriceInfo)) {
						$salePriceInfo = array("sale_price" => $salePriceInfo);
					}
				} else {
					$salePriceInfo = $this->getProductSalePrice($row['product_id'], array("product_information" => $row,"preload_product_sale_prices"=>true));
				}	
				// print_r($salePriceInfo);die;			
				$salePrice = $salePriceInfo['sale_price'];
				$productResult->setValue('map_enforced', $salePriceInfo['map_enforced']);
				$productResult->setValue('call_price', $salePriceInfo['call_price']);
				$productResult->setValue('original_sale_price', $salePriceInfo['original_sale_price']);
				$row['original_sale_price'] = $salePriceInfo['original_sale_price'];
				if ($salePriceInfo['cached']) {
					$cachedCount++;
				} elseif ($salePriceInfo['stored']) {
					$storedCount++;
				} else {
					$recalculatedCount++;
				}
				if ($salePriceInfo['sale_price'] === false) {
					$noPriceCount++;
				}

				if (($salePrice === false && $GLOBALS['gHideProductsWithNoPrice']) || ($salePrice == 0 && $GLOBALS['gHideProductsWithZeroPrice'])) {
					//$this->iResultCount--;
					//unset($productArray[$index]);
					continue;
				}
				$displayedPrice = $salePrice;
				if (!empty($row['manufacturer_advertised_price']) && $row['manufacturer_advertised_price'] > 0) {
					$displayedPrice = $row['manufacturer_advertised_price'];
				}
				if (empty($row['original_sale_price']) && !empty($row['list_price'])) {
					$row['original_sale_price'] = $row['list_price'];
					$productResult->setValue('original_sale_price', $row['original_sale_price']);
				}
				if (!empty($row['original_sale_price']) && ($row['original_sale_price'] < $salePrice || (!empty($row['manufacturer_advertised_price']) && $row['manufacturer_advertised_price'] > $row['original_sale_price']))) {
					$row['original_sale_price'] = "";
					$productResult->setValue('original_sale_price', $row['original_sale_price']);
				}
				if (!empty($displayedPrice) && !empty($row['original_sale_price']) && ($displayedPrice - $row['original_sale_price']) >= 0) {
					$productResult->setValue('original_sale_price', "");
					$row['original_sale_price'] = "";
				}
				$productResult->setValue('original_sale_price', (empty($row['original_sale_price']) ? "" : $row['original_sale_price']));
				$productResult->setValue('sale_price', ($salePrice === false || ($row['no_online_order'] && empty($showInStoreOnlyPrice)) ? $callForPriceText : number_format($salePrice, 2)));
				$productResult->setValue('hide_dollar', $salePrice === false || ($row['no_online_order'] && empty($showInStoreOnlyPrice)));
				$productArray[$index] = $productResult;
			}
		}

		$endTime = getMilliseconds();
		$this->iQueryTime .= "get prices: " . round(($endTime - $startTime) / 1000, 2) . "\n";
		$this->iQueryTime .= "Cached prices: " . $cachedCount . "\n";
		$this->iQueryTime .= "Stored prices: " . $storedCount . "\n";
		$this->iQueryTime .= "Recalculated prices: " . $recalculatedCount . "\n";
		$this->iQueryTime .= "No Price Found: " . $noPriceCount . "\n";
		$totalTime += ($endTime - $startTime);
		$startTime = getMilliseconds();
		$productPriceTypes = array();
		$resultSet = executeReadQuery("select * from product_price_types where client_id = ? and product_price_type_code <> 'SALE_PRICE' and internal_use_only = 0 and inactive = 0", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$productPriceTypes[$row['product_price_type_id']] = $row['product_price_type_code'];
		}
		freeReadResult($resultSet);
		if (!$this->iReturnIdsOnly && !empty($productPriceTypes) && !empty($this->iProductIds)) {
			$productPrices = array();
			$priceSet = executeReadQuery("select * from product_prices where product_id in (" . (empty($this->iTemporaryTableName) ? implode(",", $this->iProductIds) : "select product_id from " . $this->iTemporaryTableName) . ") and product_price_type_id in (" . implode(",", array_keys($productPriceTypes)) . ") and " .
				"(start_date is null or start_date <= current_date) and (end_date is null or end_date >= current_date)" .
				(!$GLOBALS['gLoggedIn'] || empty($GLOBALS['gUserRow']['user_type_id']) ? " and user_type_id is null" : " and (user_type_id is null or user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")"));
			while ($priceRow = getNextRow($priceSet)) {
				if (!array_key_exists($priceRow['product_id'], $productPrices)) {
					$productPrices[$priceRow['product_id']] = array();
				}
				$productPrices[$priceRow['product_id']][$priceRow['product_price_type_id']] = number_format($priceRow['price'], 2);
			}
			foreach ($productArray as $index => $productResult) {
				$thisProduct = $productResult->getProductRow();
				foreach ($productPriceTypes as $productPriceTypeId => $productPriceTypeCode) {
					if (array_key_exists($thisProduct['product_id'], $productPrices)) {
						$productResult->setValue('price_type_' . strtolower($productPriceTypeCode), $productPrices[$thisProduct['product_id']][$productPriceTypeId]);
					} else {
						$productResult->setValue('price_type_' . strtolower($productPriceTypeCode), "");
					}
					$productArray[$index] = $productResult;
				}
			}
		}

		

		$endTime = getMilliseconds();
		$this->iQueryTime .= "get other price types: " . round(($endTime - $startTime) / 1000, 2) . "\n";
		$totalTime += ($endTime - $startTime);
		$this->iQueryTime .= "total time: " . round($totalTime / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage()) . "\n";
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
			ProductCatalog::addSearchTerm($originalSearchTerm, $searchTermParameters, count($productArray));
		}

		if (!$this->iReturnCountOnly && !$this->iReturnIdsOnly) {
			$returnArray = array();
			foreach ($productArray as $productResult) {
				$returnArray[] = $productResult->getProductRow();
			}
			$productArray = $returnArray;
			unset($returnArray);
		}
		

		return ($this->iReturnCountOnly ? count($productArray) : $productArray);
	}

	function getConstraints($allProducts = false, $includeCounts = true, $parameters = array(), $productIds = array()) {
		$startTime = getMilliseconds();
		$useTemporaryTable = false;
		if (!empty($productIds)) {
			$productIds = array_filter($productIds);
		} elseif ($allProducts) {
			$productIds = array();
			$resultSet = executeReadQuery("select product_id from products where client_id = ? and inactive = 0" .
				($GLOBALS['gInternalConnection'] || $this->iAllowInternalUseOnly ? "" : " and internal_use_only = 0"), $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$productIds[] = $row['product_id'];
			}
		} else {
			$useTemporaryTable = true;
			$productIds = $this->iProductIds;
		}
		$constraintArrays = array();

		if (!$useTemporaryTable && count($productIds) > $this->iTemporaryTableThreshold && empty($this->iProductIds) && empty($this->iTemporaryTableName)) {
			$this->iTemporaryTableName = "temporary_products_" . date("Ymd") . "_" . strtolower(getRandomString(12));
			executeQuery("create table " . $this->iTemporaryTableName . "(product_id int not null,primary key (product_id))");
			$temporaryIndexQuery = "";

			foreach ($productIds as $productId) {
				$temporaryIndexQuery .= (empty($temporaryIndexQuery) ? "" : ",") . "(" . $productId . ")";
				if (strlen($temporaryIndexQuery) > 5000) {
					executeQuery("insert ignore into " . $this->iTemporaryTableName . " (product_id) values " . $temporaryIndexQuery);
					$temporaryIndexQuery = "";
				}
			}
			if (!empty($temporaryIndexQuery)) {
				executeQuery("insert ignore into " . $this->iTemporaryTableName . " (product_id) values " . $temporaryIndexQuery);
			}
			$useTemporaryTable = true;
			$count = 0;
			while (true) {
				$count++;
				if ($count > 20) {
					break;
				}
				usleep(10000);
				$resultSet = executeReadQuery("select count(*) from " . $this->iTemporaryTableName);
				if ($row = getNextRow($resultSet)) {
					if ($row['count(*)'] > (count($productIds) - 1000)) {
						break;
					}
				}
			}
		}

		if (count($productIds) > 0) {
			# if preference is set (not empty), then ONLY default location should be returned
			$excludeNonDefaultLocations = getPreference("EXCLUDE_LOCATIONS_FROM_SIDEBAR");
			if ($GLOBALS['gLoggedIn']) {
				$defaultLocationId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID");
			} else {
				$defaultLocationId = $_COOKIE['default_location_id'];
			}
			if (!empty(getPreference("RETAIL_STORE_SHOW_LOCATION_AVAILABILITY"))) {
				$distributorLocationCount = 0;
				$resultSet = executeReadQuery("select count(*) from locations where client_id = ? and user_location = 0 and not_searchable = 0 and inactive = 0 and " .
					"internal_use_only = 0 and (product_distributor_id is not null or warehouse_location = 1)", $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$distributorLocationCount = $row['count(*)'];
				}

				$constraintArrays['locations'] = array();
				if (!empty($defaultLocationId) || empty($excludeNonDefaultLocations)) {
					$resultSet = executeReadQuery("select * from locations where client_id = ? and user_location = 0 and not_searchable = 0 and inactive = 0 and " .
						(empty($defaultLocationId) || empty($excludeNonDefaultLocations) ? "" : "location_id = " . (empty($defaultLocationId) ? "0" : makeParameter($defaultLocationId)) . " and ") .
						"internal_use_only = 0 and product_distributor_id is null and warehouse_location = 0 order by sort_order,description", $GLOBALS['gClientId']);
					$locationCount = $resultSet['row_count'];
					if ($locationCount > 1 || $distributorLocationCount > 0) {
						while ($row = getNextRow($resultSet)) {
							$constraintArrays['locations'][$row['location_id']]['description'] = $row['description'];
							$constraintArrays['locations'][$row['location_id']]['location_id'] = $row['location_id'];
							if ($includeCounts) {
								$constraintArrays['locations'][$row['location_id']]['product_count'] = 1;
							}
						}
					}
				}
			}

# Get Product Tags

			$resultSet = executeReadQuery("select * from product_tags where client_id = ? and inactive = 0 and exclude_reductive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") .
				" and exists (select product_tag_id from product_tag_links where product_tag_id = product_tags.product_tag_id and product_id in (" .
				(empty($this->iTemporaryTableName) || !$useTemporaryTable ? implode(",", $productIds) : "select product_id from " . $this->iTemporaryTableName) . ")) order by sort_order,description", $GLOBALS['gClientId']);
			if ($resultSet['row_count'] > 0) {
				$constraintArrays['product_tags'] = array();
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['product_tag_id'], $constraintArrays['product_tags'])) {
						$constraintArrays['product_tags'][$row['product_tag_id']] = array();
						if ($includeCounts) {
							$constraintArrays['product_tags'][$row['product_tag_id']]['product_count'] = 1;
						}
					}
					$constraintArrays['product_tags'][$row['product_tag_id']]['description'] = $row['description'];
					$constraintArrays['product_tags'][$row['product_tag_id']]['product_tag_id'] = $row['product_tag_id'];
				}
				if ($includeCounts && count($constraintArrays['product_tags']) > 0) {
					$resultSet = executeReadQuery("select product_tag_id,count(distinct product_id) from product_tag_links " .
						"where product_id in (" . (empty($this->iTemporaryTableName) || !$useTemporaryTable ? implode(",", $productIds) : "select product_id from " . $this->iTemporaryTableName) . ") group by product_tag_id");
					while ($row = getNextRow($resultSet)) {
						if (!array_key_exists($row['product_tag_id'], $constraintArrays['product_tags'])) {
							continue;
						}
						$constraintArrays['product_tags'][$row['product_tag_id']]['product_count'] = $row['count(distinct product_id)'];
					}
				}
				$constraintArrays['product_tags'] = array_values($constraintArrays['product_tags']);

				$endTime = getMilliseconds();
				$this->iQueryTime .= "product tags: " . round(($endTime - $startTime) / 1000, 2) . "\n";
				$startTime = getMilliseconds();
			}

# Get Manufacturer Constraints

			if (!$parameters['ignore_manufacturers']) {
				$constraintArrays['manufacturers'] = array();
				if (empty($this->iUniqueManufacturers)) {
					$resultSet = executeReadQuery("select * from product_manufacturers where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] || $this->iAllowInternalUseOnly ? "" : " and internal_use_only = 0") .
						" and exists (select product_manufacturer_id from products where product_manufacturer_id is not null and product_manufacturer_id = product_manufacturers.product_manufacturer_id and product_id in (" .
						(empty($this->iTemporaryTableName) || !$useTemporaryTable ? implode(",", $productIds) : "select product_id from " . $this->iTemporaryTableName) . ")) order by description", $GLOBALS['gClientId']);
				} else {
					$resultSet = executeReadQuery("select * from product_manufacturers where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] || $this->iAllowInternalUseOnly ? "" : " and internal_use_only = 0") .
						" and product_manufacturer_id in (" . implode(",", $this->iUniqueManufacturers) . ") order by description", $GLOBALS['gClientId']);
				}
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['product_manufacturer_id'], $constraintArrays['manufacturers'])) {
						$constraintArrays['manufacturers'][$row['product_manufacturer_id']] = array();
						if ($includeCounts) {
							$constraintArrays['manufacturers'][$row['product_manufacturer_id']]['product_count'] = 1;
						}
					}
					$constraintArrays['manufacturers'][$row['product_manufacturer_id']]['description'] = $row['description'];
					$constraintArrays['manufacturers'][$row['product_manufacturer_id']]['product_manufacturer_id'] = $row['product_manufacturer_id'];
				}
				if ($includeCounts && count($constraintArrays['manufacturers']) > 0) {
					$resultSet = executeReadQuery("select product_manufacturer_id,count(*) from products where product_id in (" .
						(empty($this->iTemporaryTableName) || !$useTemporaryTable ? implode(",", $productIds) : "select product_id from " . $this->iTemporaryTableName) . ") group by product_manufacturer_id");
					while ($row = getNextRow($resultSet)) {
						if (!array_key_exists($row['product_manufacturer_id'], $constraintArrays['manufacturers'])) {
							continue;
						}
						$constraintArrays['manufacturers'][$row['product_manufacturer_id']]['product_count'] = $row['count(*)'];
					}
				}
				$constraintArrays['manufacturers'] = array_values($constraintArrays['manufacturers']);

				$endTime = getMilliseconds();
				$this->iQueryTime .= "manufacturers constraints: " . round(($endTime - $startTime) / 1000, 2) . "\n";
				$startTime = getMilliseconds();
			}

# Get Category Constraints

			if (!$parameters['ignore_categories']) {
				$constraintArrays['categories'] = array();
				$resultSet = executeQuery("select * from product_categories where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] || $this->iAllowInternalUseOnly ? "" : " and internal_use_only = 0") .
					" and exists (select product_category_id from product_category_links where product_category_id = product_categories.product_category_id and " .
					"product_id in (" . (empty($this->iTemporaryTableName) || !$useTemporaryTable ? implode(",", $productIds) : "select product_id from " . $this->iTemporaryTableName) . ")) order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['product_category_id'], $constraintArrays['categories'])) {
						$constraintArrays['categories'][$row['product_category_id']] = array();
						if ($includeCounts) {
							$constraintArrays['categories'][$row['product_category_id']]['product_count'] = 1;
						}
					}
					$constraintArrays['categories'][$row['product_category_id']]['description'] = $row['description'];
					$constraintArrays['categories'][$row['product_category_id']]['product_category_id'] = $row['product_category_id'];
				}
				$endTime = getMilliseconds();
				$this->iQueryTime .= "categories constraints 1: " . round(($endTime - $startTime) / 1000, 2) . "\n";
				$startTime = getMilliseconds();
				if ($includeCounts && count($constraintArrays['categories']) > 0) {
					$resultSet = executeReadQuery("select product_category_id,count(distinct product_id) from product_category_links " .
						"where product_id in (" . (empty($this->iTemporaryTableName) || !$useTemporaryTable ? implode(",", $productIds) : "select product_id from " . $this->iTemporaryTableName) . ") group by product_category_id");
					while ($row = getNextRow($resultSet)) {
						if (!array_key_exists($row['product_category_id'], $constraintArrays['categories'])) {
							continue;
						}
						$constraintArrays['categories'][$row['product_category_id']]['product_count'] = $row['count(distinct product_id)'];
					}
				}
				$constraintArrays['categories'] = array_values($constraintArrays['categories']);

				$endTime = getMilliseconds();
				$this->iQueryTime .= "categories constraints: " . round(($endTime - $startTime) / 1000, 2) . "\n";
				$startTime = getMilliseconds();
			}

# Get Facet Constraints

			if (!$parameters['ignore_facets']) {
				$constraintArrays['facets'] = array();
				$productFacets = array();
				$resultSet = executeReadQuery("select * from product_facets where inactive = 0 and exclude_reductive = 0 and client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (!empty($this->iSidebarFacetCodes)) {
						if (!in_array($row['product_facet_code'], $this->iSidebarFacetCodes)) {
							continue;
						}
					}
					$productFacets[$row['product_facet_id']] = $row;
				}
				$facetConstraints = array();
				$facetSet = executeReadQuery("select product_facet_options.product_facet_id,product_facet_option_id,facet_value from " .
					"product_facet_values join product_facet_options using (product_facet_option_id) where " .
					"product_id in (" . (empty($this->iTemporaryTableName) || !$useTemporaryTable ? implode(",", $productIds) : "select product_id from " . $this->iTemporaryTableName) . ")");
				while ($facetRow = getNextRow($facetSet)) {
					if (!empty($facetRow['inactive']) || !empty($facetRow['exclude_reductive'])) {
						continue;
					}
					if (!array_key_exists($facetRow['product_facet_id'], $productFacets)) {
						continue;
					}
					if (!array_key_exists($facetRow['product_facet_id'], $facetConstraints)) {
						$facetConstraints[$facetRow['product_facet_id']] = array();
					}
					if (!array_key_exists($facetRow['product_facet_option_id'], $facetConstraints[$facetRow['product_facet_id']])) {
						$facetConstraints[$facetRow['product_facet_id']][$facetRow['product_facet_option_id']] = array();
						if ($includeCounts) {
							$facetConstraints[$facetRow['product_facet_id']][$facetRow['product_facet_option_id']]['product_count'] = 0;
						}
						$facetConstraints[$facetRow['product_facet_id']][$facetRow['product_facet_option_id']]['description'] = $facetRow['facet_value'];
						$facetConstraints[$facetRow['product_facet_id']][$facetRow['product_facet_option_id']]['product_facet_option_id'] = $facetRow['product_facet_option_id'];
					} elseif ($includeCounts) {
						$facetConstraints[$facetRow['product_facet_id']][$facetRow['product_facet_option_id']]['product_count']++;
					}
				}
				foreach ($facetConstraints as $productFacetId => $facetValues) {
					if (empty($facetValues) || count($facetValues) == 1) {
						unset($facetConstraints[$productFacetId]);
					}
				}
				$endTime = getMilliseconds();
				$this->iQueryTime .= "facets constraints 1: " . round(($endTime - $startTime) / 1000, 2) . "\n";
				$startTime = getMilliseconds();
				if (!empty($this->iSidebarFacetLimit)) {
					$sortedFacets = array();
					$facetSet = executeReadQuery("select * from product_facets where client_id = ? and inactive = 0 and exclude_reductive = 0 and internal_use_only = 0 order by sort_order,description", $GLOBALS['gClientId']);
					while ($facetRow = getNextRow($facetSet)) {
						if (array_key_exists($facetRow['product_facet_id'], $facetConstraints)) {
							$thisArray = $facetConstraints[$facetRow['product_facet_id']];
							usort($thisArray, array($this, "facetValueSort"));
							$sortedFacets[$facetRow['product_facet_id']] = $thisArray;
						}
						if (count($sortedFacets) >= $this->iSidebarFacetLimit) {
							break;
						}
					}
					$facetConstraints = $sortedFacets;
				}
				$constraintArrays['facets'] = $facetConstraints;

				$endTime = getMilliseconds();
				$this->iQueryTime .= "facets constraints: " . round(($endTime - $startTime) / 1000, 2) . "\n";
				$startTime = getMilliseconds();
			}

# Get Product Data Constraints

			if (!$parameters['ignore_product_data']) {
				$constraintArrays['product_data'] = array();
				if (!$allProducts && count($this->iSidebarProductDataFields) > 0) {
					foreach ($this->iSidebarProductDataFields as $fieldName) {
						$constraintArrays['product_data'][$fieldName] = array();
					}

					$productDataSet = executeReadQuery("select * from product_data where product_id in (" . (empty($this->iTemporaryTableName) || !$useTemporaryTable ? implode(",", $productIds) : "select product_id from " . $this->iTemporaryTableName) . ")");
					while ($productDataRow = getNextRow($productDataSet)) {
						foreach ($this->iSidebarProductDataFields as $fieldName) {
							if (empty($productDataRow[$fieldName])) {
								continue;
							}
							if (!array_key_exists($productDataRow[$fieldName], $constraintArrays['product_data'][$fieldName])) {
								$constraintArrays['product_data'][$fieldName][$productDataRow[$fieldName]] = array("product_count" => 1);
								$constraintArrays['product_data'][$fieldName][$productDataRow[$fieldName]]['description'] = $productDataRow[$fieldName];
							} else {
								$constraintArrays['product_data'][$fieldName][$productDataRow[$fieldName]]['product_count']++;
							}
						}
					}
				}

				$endTime = getMilliseconds();
				$this->iQueryTime .= "product data constraints: " . round(($endTime - $startTime) / 1000, 2) . "\n";
			}

		}
		return $constraintArrays;
	}

	function getCacheKey() {
		$cacheKey = "|" . $GLOBALS['gClientId'];
		$cacheKey .= "|" . $GLOBALS['gLoggedIn'];
		$cacheKey .= "|" . $GLOBALS['gUserRow']['administrator_flag'];
		$cacheKey .= "|" . $GLOBALS['gUserRow']['user_type_id'];
		if ($GLOBALS['gLoggedIn']) {
			$defaultLocationId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID");
		} else {
			$defaultLocationId = $_COOKIE['default_location_id'];
		}
		$cacheKey .= "|" . $defaultLocationId;
		$cacheKey .= "|" . $this->iSearchText;
		$cacheKey .= "|" . $this->iOffset;
		$cacheKey .= "|" . $this->iSelectLimit;
		$cacheKey .= "|" . ($this->iLimitQuery ? 1 : 0);
		$cacheKey .= "|" . $this->iSidebarFacetLimit;
		$cacheKey .= "|" . $this->iSortBy;
		$cacheKey .= "|" . $this->iResultCount;
		$cacheKey .= "|" . $this->iShowOutOfStock;
		$cacheKey .= "|" . $this->iNeedSidebarInfo;
		$cacheKey .= "|" . $this->iIncludeTagsWithNoStartDate;
		$cacheKey .= "|" . $this->iIgnoreClient;
		$cacheKey .= "|" . $this->iReturnIdsOnly;
		$cacheKey .= "|" . $this->iReturnCountOnly;
		$cacheKey .= "|" . implode(",", $this->iCompliantStates);
		$cacheKey .= "|" . $this->iAllowInternalUseOnly;
		$cacheKey .= "|" . implode(",", $this->iProductTypeIds);
		$cacheKey .= "|" . implode(",", $this->iSearchGroupIds);
		$cacheKey .= "|" . implode(",", $this->iDepartmentIds);
		$cacheKey .= "|" . implode(",", $this->iCategoryIds);
		$cacheKey .= "|" . implode(",", $this->iManufacturerIds);
		$cacheKey .= "|" . implode(",", $this->iLocationIds);
		$cacheKey .= "|" . implode(",", $this->iTagIds);
		$cacheKey .= "|" . implode(",", $this->iContributorIds);
		$cacheKey .= "|" . implode(",", $this->iProductFacetIds);
		$cacheKey .= "|" . implode(",", $this->iFacetOptionIds);
		$cacheKey .= "|" . implode(",", $this->iDescriptionIds);
		$cacheKey .= "|" . implode(",", $this->iExcludeCategories);
		$cacheKey .= "|" . implode(",", $this->iExcludeDepartments);
		$cacheKey .= "|" . implode(",", $this->iExcludeManufacturers);
		$cacheKey .= "|" . implode(",", $this->iSidebarFacetCodes);
		$cacheKey .= "|" . implode(",", $this->iSidebarProductDataFields);
		$cacheKey .= "|" . $this->iBaseImageFilenameOnly;
		$cacheKey .= "|" . $this->iIgnoreManufacturerLogo;
		$cacheKey .= "|" . $this->iIgnoreProductsWithoutImages;
		$cacheKey .= "|" . $this->iIgnoreProductsWithoutCategory;
		$cacheKey .= "|" . implode(",", $this->iRelatedProductIds);
		$cacheKey .= "|" . $this->iRelatedProductTypeId;
		return md5($cacheKey);
	}

	private function facetValueSort($a, $b) {
		if ($a['description'] == $b['description']) {
			return 0;
		}
		return ($a['description'] > $b['description']) ? 1 : -1;
	}
}
