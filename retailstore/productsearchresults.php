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

$GLOBALS['gPageCode'] = "RETAILSTOREPRODUCTSEARCHRESULTS";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gLogTimeRequired'] = true;
$GLOBALS['gMultipleProductsForWaitingQuantities'] = true;
require_once "shared/startup.inc";

class RetailStoreProductSearchResultsPage extends Page {

	var $iLimitedResultsCount = 10000;
	var $iSearchCriteria = array();
	var $iSearchCriteriaTables = array();
	var $iSearchCriteriaCount = 0;
	var $iSearchDescription = false;
	var $iSearchDetailedDescription = false;
	var $iPageGroupingData = array();

	function initialize() {

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

		$defaultLocationId = "";
		if ($GLOBALS['gLoggedIn']) {
			$defaultLocationId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID");
		}
		if (empty($defaultLocationId)) {
			$defaultLocationId = $_COOKIE['default_location_id'];
		}
		$defaultLocationId = getFieldFromId("location_id", "locations", "location_id", $defaultLocationId, "product_distributor_id is null and inactive = 0 and internal_use_only = 0");
		if ($GLOBALS['gLoggedIn']) {
			CustomField::setCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID", $defaultLocationId);
		}
		setCoreCookie("default_location_id", $defaultLocationId, (24 * 365 * 10));
		$_COOKIE['default_location_id'] = $defaultLocationId;
		$this->generateSearchCriteria();
	}

	private function generateSearchCriteria() {
		$parameters = array_merge($_POST, $_GET);
		if (!empty($parameters['search_parameter_group_id'])) {
			$searchParameterGroupRow = getRowFromId("search_parameter_groups", "search_parameter_group_id", $parameters['search_parameter_group_id']);
			if (!empty($searchParameterGroupRow)) {
				$this->iPageGroupingData['primary_id'] = $searchParameterGroupRow['search_parameter_group_id'];
				$this->iPageGroupingData['primary_key'] = "search_parameter_group_id";
				$this->iPageGroupingData['label'] = "Search Parameter Group";
				$this->iPageGroupingData['description'] = $searchParameterGroupRow['description'];
				$GLOBALS['gPageRow']['meta_title'] = $searchParameterGroupRow['description'];
				$this->iPageGroupingData['detailed_description'] = $searchParameterGroupRow['detailed_description'];
				$GLOBALS['gPageRow']['meta_description'] = $searchParameterGroupRow['detailed_description'];
				$this->iPageGroupingData['image_id'] = $searchParameterGroupRow['image_id'];
				$this->iPageGroupingData['image_url'] = (empty($this->iPageGroupingData['image_id']) ? "" : getImageFilename($this->iPageGroupingData['image_id'], array("use_cdn" => true)));
				$resultSet = executeQuery("select * from search_parameter_group_details where search_parameter_group_id = ?", $parameters['search_parameter_group_id']);
				while ($row = getNextRow($resultSet)) {
					$parameters[$row['parameter_name']] = $row['parameter_value'];
				}
			}
		}
		$this->iSearchCriteriaTables["product_departments"] = array("label" => "Department", "function_name" => "setDepartments", "exclude_function_name" => "setExcludeDepartments");
		$this->iSearchCriteriaTables["product_categories"] = array("label" => "Category", "function_name" => "setCategories", "exclude_function_name" => "setExcludeCategories");
		$this->iSearchCriteriaTables["product_types"] = array("label" => "Product Type", "function_name" => "setProductTypes");
		$this->iSearchCriteriaTables["product_category_groups"] = array("label" => "Category Group", "function_name" => "setCategoryGroups");
		$this->iSearchCriteriaTables["product_manufacturers"] = array("label" => "Manufacturer", "function_name" => "setManufacturers", "exclude_function_name" => "setExcludeManufacturers", "contact_image" => true);
		$this->iSearchCriteriaTables["product_manufacturer_tags"] = array("function_name" => "setManufacturerTags");
		$this->iSearchCriteriaTables["product_tags"] = array("label" => "Product Tag", "function_name" => "setTags");
		$this->iSearchCriteriaTables["product_tag_groups"] = array("function_name" => "setTagGroups");
		$this->iSearchCriteriaTables["search_groups"] = array("function_name" => "setSearchGroups");
		$this->iSearchCriteriaTables["contributors"] = array("label" => "Contributor", "function_name" => "setContributors", "description_field" => "full_name");
		$this->iSearchCriteriaTables["locations"] = array("label" => "Location", "function_name" => "setLocations", "contact_image" => true);
		$this->iSearchCriteriaTables["product_facet_options"] = array("function_name" => "setFacetOptions");
		$this->iSearchCriteriaTables["product_facets"] = array("function_name" => "setProductFacets");
		$this->iSearchCriteriaTables["related_products"] = array("function_name" => "setRelatedProduct");
		$this->iSearchCriteriaTables["products"] = array("function_name" => "setSpecificProductIds");
		foreach ($this->iSearchCriteriaTables as $tableName => $thisSearchTable) {
			$dataTable = new DataTable($tableName);
			$primaryKey = $dataTable->getPrimaryKey();
			$this->iSearchCriteriaTables[$tableName]['primary_key'] = $primaryKey;
            
			$valuesArray = array();
			$excludeValuesArray = array();
			$fieldsArray = array($primaryKey, $primaryKey . "s");
			$codeFieldsArray = array();
			if ($dataTable->columnExists(substr($primaryKey, 0, -2) . "code")) {
				$codeFieldsArray[] = substr($primaryKey, 0, -2) . "code";
				$codeFieldsArray[] = substr($primaryKey, 0, -2) . "codes";
			}
			foreach (array_merge($fieldsArray, $codeFieldsArray) as $fieldName) {
				if (!empty($parameters[$fieldName])) {
					if (is_array($parameters[$fieldName])) {
						$criteriaArray = $parameters[$fieldName];
					} else {
						$criteriaArray = explode("|", $parameters[$fieldName]);
					}
					foreach ($criteriaArray as $valueKey) {
						if (in_array($fieldName, $codeFieldsArray)) {
							$valueKey = getReadFieldFromId($primaryKey, $tableName, substr($primaryKey, 0, -2) . "code", $valueKey);
						}
						if (!empty($valueKey)) {
							$valuesArray[] = $valueKey;
						}
					}
				}
				if (!empty($thisSearchTable['exclude_function_name'])) {
					if (!empty($parameters["exclude_" . $fieldName])) {
						if (is_array($parameters["exclude_" . $fieldName])) {
							$criteriaArray = $parameters["exclude_" . $fieldName];
						} else {
							$criteriaArray = explode("|", $parameters["exclude_" . $fieldName]);
						}
						foreach ($criteriaArray as $valueKey) {
							if (in_array($fieldName, $codeFieldsArray)) {
								$valueKey = getReadFieldFromId($primaryKey, $tableName, substr($primaryKey, 0, -2) . "code", $valueKey);
							}
							if (!empty($valueKey)) {
								$excludeValuesArray[] = $valueKey;
							}
						}
					}
				}
			}
          
			if (!empty($valuesArray) || !empty($excludeValuesArray)) {
				$this->iSearchCriteria[] = array("table_name" => $tableName, "values_array" => $valuesArray, "exclude_values_array" => $excludeValuesArray);
				$this->iSearchCriteriaCount += count($valuesArray) + count($excludeValuesArray);
			}
		}
		if (empty($this->iPageGroupingData) && $this->iSearchCriteriaCount == 1) {
			foreach ($this->iSearchCriteria as $thisSearchCriteria) {
				$tableRow = getRowFromId($thisSearchCriteria['table_name'], $this->iSearchCriteriaTables[$thisSearchCriteria['table_name']]['primary_key'], $thisSearchCriteria['values_array'][0]);
				
                if ($this->iSearchCriteriaTables[$thisSearchCriteria['table_name']]['contact_image']) {
					$tableRow['image_id'] = getFieldFromId("image_id", "contacts", "contact_id", $tableRow['contact_id']);
				}
				if (!empty($tableRow) && !empty($this->iSearchCriteriaTables[$thisSearchCriteria['table_name']]['label'])) {
					$this->iPageGroupingData['primary_id'] = $tableRow[$this->iSearchCriteriaTables[$thisSearchCriteria['table_name']]['primary_key']];
					$this->iPageGroupingData['primary_key'] = $this->iSearchCriteriaTables[$thisSearchCriteria['table_name']]['primary_key'];
					$this->iPageGroupingData['label'] = $this->iSearchCriteriaTables[$thisSearchCriteria['table_name']]['label'];
					if (empty($this->iSearchCriteriaTables[$thisSearchCriteria['table_name']]['description_field'])) {
						$descriptionField = "description";
					} else {
						$descriptionField = $this->iSearchCriteriaTables[$thisSearchCriteria['table_name']]['description_field'];
					}
					$this->iPageGroupingData['description'] = $tableRow[$descriptionField];
					$this->iPageGroupingData['detailed_description'] = $tableRow['detailed_description'];
					$this->iPageGroupingData['image_id'] = $tableRow['image_id'];
					$this->iPageGroupingData['image_url'] = (empty($this->iPageGroupingData['image_id']) ? "" : getImageFilename($this->iPageGroupingData['image_id'], array("use_cdn" => true)));
				}
			}
		}
	}

	function setPageTitle() {
		if ($this->iSearchCriteriaCount == 1) {
			foreach ($this->iSearchCriteria as $thisSearchCriteria) {
				$tableRow = getRowFromId($thisSearchCriteria['table_name'], $this->iSearchCriteriaTables[$thisSearchCriteria['table_name']]['primary_key'], $thisSearchCriteria['values_array'][0]);
				if (!empty($tableRow['meta_description'])) {
					$GLOBALS['gPageRow']['meta_description'] = $tableRow['meta_description'];
				}
				if (empty($tableRow['meta_title'])) {
					if (empty($this->iSearchCriteriaTables[$thisSearchCriteria['table_name']]['description_field'])) {
						$descriptionField = "description";
					} else {
						$descriptionField = $this->iSearchCriteriaTables[$thisSearchCriteria['table_name']]['description_field'];
					}
					return $tableRow[$descriptionField] . " | " . $GLOBALS['gClientName'];
				} else {
					return $tableRow['meta_title'];
				}
			}
		}
		return false;
	}

	function mainContent() {
		?>
        <div id="_search_result_details_wrapper" class="hidden"></div>
		<?php
		return false;
	}

	function onLoadJavascript() {
		$cdnDomain = ($GLOBALS['gDevelopmentServer'] ? "" : getPreference("CDN_DOMAIN"));
		if (!empty($cdnDomain) && substr($cdnDomain, 0, 4) != "http") {
			$cdnDomain = "https://" . $cdnDomain;
			$cdnDomain = trim($cdnDomain, "/");
		}
		?>
        <script>
			<?php if (!empty($cdnDomain)) { ?>
            cdnDomain = "<?= $cdnDomain ?>";
			<?php } ?>
            $(document).on("click", "#reload_cache", function () {
                let thisUrl = $(location).attr('href');
                if (thisUrl.indexOf("?") > 0) {
                    thisUrl += "&no_cache=true";
                } else {
                    thisUrl += "?no_cache=true";
                }
                document.location = thisUrl;
            });
        </script>
		<?php
	}

	function inlineJavascript() {
		$queryTime = "";
		$startTime = getMilliseconds();
		$endTime = getMilliseconds();
		$queryTime .= "Start Search Results: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
		$startTime = getMilliseconds();

		$productFieldNames = array();
		$productResults = array();
		$productCategoryGroupIds = array();
		$constraints = array();
		$resultCount = 0;
		$cachedResultsUsed = false;

		$displaySearchText = "";

		$userParameters = array_merge($_POST, $_GET);
		$showInStoreOnlyPrice = getPreference("SHOW_IN_STORE_ONLY_PRICE");
		$missingProductImage = getImageFilenameFromCode("NO_PRODUCT_IMAGE");
		if (empty($missingProductImage) || $missingProductImage == "/images/empty.jpg") {
			$missingProductImage = getPreference("DEFAULT_PRODUCT_IMAGE");
		}
		if (empty($missingProductImage)) {
			$missingProductImage = "/images/empty.jpg";
		}
		$neverOutOfStock = getPreference("RETAIL_STORE_NEVER_OUT_OF_STOCK");
		$inventoryCounts = array();
		$callForPriceText = $this->getFragment("CALL_FOR_PRICE");
		if (empty($callForPriceText)) {
			$callForPriceText = getLanguageText("Call for Price");
		}
		$catalogResultHtml = ProductCatalog::getCatalogResultHtml(true);
		$foundSearchParameters = $this->iSearchCriteriaCount > 0;
		if (!$foundSearchParameters) {
			$searchableParameters = array("exclude_out_of_stock", "related_product_type_code", "search_text", "states");
			foreach ($searchableParameters as $key) {
				if (!empty($userParameters[$key])) {
					$foundSearchParameters = true;
					break;
				}
			}
		}
		if (!$foundSearchParameters) {
			$productCount = getFieldFromId("count(*)", "products", "client_id", $GLOBALS['gClientId']);
			if ($productCount <= 1000) {
				$foundSearchParameters = true;
			}
		}
		$limitedResults = false;
		if ($foundSearchParameters) {
			$productCatalog = new ProductCatalog();
			if (array_key_exists("search_text", $userParameters)) {
				$productCatalog->setSearchText($userParameters['search_text']);
			}
			$productCatalog->showOutOfStock(empty($userParameters['exclude_out_of_stock']));
			$productCatalog->needSidebarInfo(true);
			$productCatalog->setSelectLimit(isWebCrawler() || $_SESSION['speed_tester'] ? 20 : ($GLOBALS['gClientCount'] > 20 ? 10000 : 50000));
			$productCatalog->setLimitQuery(true);
			$productCatalog->setGetProductSalePrice(true);
			$productCatalog->setIgnoreManufacturerLogo((strpos($catalogResultHtml, "logo_image_url") === false));
			$productCatalog->setBaseImageFilenameOnly(true);
			$productCatalog->setDefaultImage($missingProductImage);
			$sidebarFacetLimit = getPreference("RETAIL_STORE_SIDEBAR_FACET_LIMIT");
			if (isWebCrawler() || $_SESSION['speed_tester']) {
				$sidebarFacetLimit = 5;
			} elseif (strlen($sidebarFacetLimit) == 0 || $sidebarFacetLimit > 20) {
				$sidebarFacetLimit = getReadFieldFromId("maximum_value", "preferences", "preference_code", "RETAIL_STORE_SIDEBAR_FACET_LIMIT");
			}
			$productCatalog->setSidebarFacetLimit($sidebarFacetLimit);

			$pickupLocationCount = 0;
			$resultSet = executeQuery("select count(*) from shipping_methods where location_id is not null and pickup = 1 and client_id = ?", $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				$pickupLocationCount = $row['count(*)'];
			}

			if (array_key_exists("sort_by", $userParameters)) {
				$productCatalog->setSortBy($userParameters['sort_by']);
			}
			if (array_key_exists("ignore_products_without_image", $userParameters)) {
				$productCatalog->ignoreProductsWithoutImages($userParameters['ignore_products_without_image']);
			}
			if (array_key_exists("related_product_type_code", $userParameters) && !empty($userParameters['related_product_type_code'])) {
				$productCatalog->setRelatedProductTypeCode($userParameters['related_product_type_code']);
			}
			if (array_key_exists("states", $userParameters) && !empty($userParameters['states'])) {
				if (!is_array($userParameters['states'])) {
					$userParameters['states'] = explode("|", $userParameters['states']);
				}
				$stateArray = getStateArray();
				foreach ($userParameters['states'] as $thisState) {
					if (!empty($thisState) && array_key_exists($thisState, $stateArray)) {
						$productCatalog->addCompliantState($thisState);
					}
				}
			}
			if (array_key_exists("include_product_tags_without_start_date", $userParameters)) {
				$productCatalog->includeProductTagsWithNoStartDate($userParameters['include_product_tags_without_start_date']);
			}
			foreach ($this->iSearchCriteria as $thisSearchCriteria) {
				$tableInfo = $this->iSearchCriteriaTables[$thisSearchCriteria['table_name']];
				if (!empty($tableInfo['function_name']) && !empty($thisSearchCriteria['values_array'])) {
					$functionName = $tableInfo['function_name'];
					$productCatalog->$functionName($thisSearchCriteria['values_array']);
				}
				if (!empty($tableInfo['exclude_function_name']) && !empty($thisSearchCriteria['exclude_values_array'])) {
					$functionName = $tableInfo['exclude_function_name'];
					$productCatalog->$functionName($thisSearchCriteria['exclude_values_array']);
				}
			}

			$totalParameters = count($this->iSearchCriteria);
			$limitableSearch = ($totalParameters == 1 && $this->iSearchCriteria[0]['table_name'] == "product_departments" && $GLOBALS['gClientCount'] > 20);

			if (empty($this->iPageGroupingData) && !empty($userParameters['search_text'])) {
				$sidebarFacetLimit = getPreference("RETAIL_STORE_TEXT_SEARCH_SIDEBAR_FACET_LIMIT");
				if (strlen($sidebarFacetLimit) == 0 || $sidebarFacetLimit > 30) {
					$sidebarFacetLimit = getReadFieldFromId("maximum_value", "preferences", "preference_code", "RETAIL_STORE_TEXT_SEARCH_SIDEBAR_FACET_LIMIT");
				}
				$productCatalog->setSidebarFacetLimit($sidebarFacetLimit);
			}

			if (!empty($userParameters['minimum_price']) && !is_numeric($userParameters['minimum_price'])) {
				$userParameters['minimum_price'] = "";
			}
			if (!empty($userParameters['maximum_price']) && !is_numeric($userParameters['maximum_price'])) {
				$userParameters['maximum_price'] = "";
			}
			$cacheKey = $productCatalog->getCacheKey();
			$productResults = false;
			$endTime = getMilliseconds();
			$queryTime .= "Catalog Object Built: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
			$startTime = getMilliseconds();
			if (empty($_GET['no_cache'])) {
				$cachedResults = false;
				if (!isWebCrawler() && !$_SESSION['speed_tester']) {
					$cachedResults = getCachedData("product_search_results", $cacheKey);
					if (is_array($cachedResults) && count($cachedResults['cached_product_results']) == 0) {
						$cachedResults = false;
					}
				}
				if (!empty($cachedResults) && count($cachedResults['cached_product_results']) > 0) {
					$endTime = getMilliseconds();
					$queryTime .= "Before separating parts: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
					$startTime = getMilliseconds();
					$cachedProductResults = $cachedResults['cached_product_results'];
					$cachedProductResultKeys = $cachedResults['cached_product_result_keys'];
					$constraints = $cachedResults['constraints'];
					$displaySearchText = $cachedResults['display_search_text'];
					$endTime = getMilliseconds();
					$queryTime .= "After separating parts: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
					$startTime = getMilliseconds();
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
					$endTime = getMilliseconds();
					$queryTime .= "After creating product results: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
					$startTime = getMilliseconds();
					if (empty($productResults) || empty($constraints)) {
						$productResults = false;
						unset($cachedProductResults);
						unset($cachedProductResultKeys);
						unset($constraints);
						unset($displaySearchText);
						unset($cachedResults);
					} else {
						$cachedResultsUsed = true;
						$GLOBALS['gProductSearchResultsCount'] = $resultCount = $cachedResults['result_count'];
						$productIds = array();
						$productCodeArray = array();
						$cachedPrices = 0;
						$storedPrices = 0;
						unset($cachedProductResults);
						unset($cachedProductResultKeys);
						unset($cachedResults);
						$endTime = getMilliseconds();
						$queryTime .= "After unset: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
						$startTime = getMilliseconds();
						$customProductSalePriceFunctionExists = false;
						if (function_exists("customProductSalePrice")) {
							$customProductSalePriceFunctionExists = true;
						}
						if (count($productResults) > 0) {
							if ($limitableSearch && count($productResults) == $this->iLimitedResultsCount) {
								$limitedResults = true;
							}
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
							$endTime = getMilliseconds();
							$queryTime .= "Got cached results: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
							$startTime = getMilliseconds();
							foreach ($productResults as $index => $thisProduct) {
								$productIds[] = $thisProduct['product_id'];
							}
							$GLOBALS['gProductSearchResultsProductIds'] = $productIds;
							foreach ($productResults as $index => $thisProduct) {
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
								} elseif ($salePriceInfo['stored']) {
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
								if (getPreference("ALWAYS_USE_LIST_PRICE_FOR_ORIGINAL_SALE_PRICE") && !empty($thisProduct['list_price'])) {
									$originalSalePrice = $thisProduct['list_price'];
								}
								if (($salePrice === false && $GLOBALS['gHideProductsWithNoPrice']) || ($salePrice == 0 && $GLOBALS['gHideProductsWithZeroPrice'])) {
									$resultCount--;
									unset($productResults[$index]);
									continue;
								}
								if (!empty($userParameters['minimum_price']) && ($salePrice === false || $salePrice < $userParameters['minimum_price'])) {
									$resultCount--;
									unset($productResults[$index]);
									continue;
								}
								if (!empty($userParameters['maximum_price']) && ($salePrice === false || $salePrice > $userParameters['maximum_price'])) {
									$resultCount--;
									unset($productResults[$index]);
									continue;
								}
								$productResults[$index]['sale_price'] = ($salePrice === false || (!empty($thisProduct['no_online_order']) && empty($showInStoreOnlyPrice)) || !is_numeric($salePrice) ? ($salePrice === false || is_numeric($salePrice) ? $callForPriceText : $salePrice) : number_format($salePrice, 2, ".", ","));
								$productResults[$index]['original_sale_price'] = (empty($originalSalePrice) ? "" : number_format($originalSalePrice, 2, ".", ","));
								$productResults[$index]['hide_dollar'] = ($salePrice === false || (!empty($thisProduct['no_online_order']) && empty($showInStoreOnlyPrice)) || !is_numeric($salePrice) ? true : false);
								$productResults[$index]['map_enforced'] = $mapEnforced;
								$productResults[$index]['call_price'] = $callPrice;
							}
							if (getPreference("DONT_CACHE_AVAILABILITY") || $pickupLocationCount > 1) {
								$productLocationAvailability = $productCatalog->getLocationAvailability($productIds);
								foreach ($productResults as $index => $thisProduct) {
									$productResults[$index]['location_availability'] = $productCatalog::getProductAvailabilityText($thisProduct, $productLocationAvailability, true);
								}
							}
						}
						$endTime = getMilliseconds();
						$queryTime .= "Get latest prices: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
						$queryTime .= "Cached Prices: " . $cachedPrices . "\n";
						$queryTime .= "Stored Prices: " . $storedPrices . "\n";
						$queryTime .= "Recalculated Prices: " . (count($productResults) - $cachedPrices - $storedPrices) . "\n";
						$startTime = getMilliseconds();
						if (empty($neverOutOfStock)) {
							$inventoryCounts = $productCatalog->getInventoryCounts(false, $productIds);
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
				$endTime = getMilliseconds();
				$queryTime .= "Before Get Search Results: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
				$startTime = getMilliseconds();
				if ($limitableSearch) {
					$limitedResults = true;
					$productCatalog->setSelectLimit($this->iLimitedResultsCount);
					$productCatalog->showOutOfStock(false);
				}
				$productResults = $productCatalog->getProducts();
                // print_r($productResults);die;
				$endTime = getMilliseconds();
				$queryTime .= "Get Search Results: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
				$startTime = getMilliseconds();
				$showLocationAvailability = getPreference("RETAIL_STORE_SHOW_LOCATION_AVAILABILITY");
				if (!empty($showLocationAvailability)) {
					$productLocationAvailability = $productCatalog->getLocationAvailability();
					foreach ($productResults as $index => $thisProduct) {
						$productResults[$index]['location_availability'] = $productCatalog::getProductAvailabilityText($thisProduct, $productLocationAvailability, true);
					}
				}
				$displaySearchText = $productCatalog->getDisplaySearchText();
				$constraints = $productCatalog->getConstraints(false, false);
				$inventoryCounts = $productCatalog->getInventoryCounts();
				$resultCount = $productCatalog->getResultCount();
				$cachedProductResults = array();
				$cachedProductResultKeys = false;
				foreach ($productResults as $index => $thisProduct) {
					if ($cachedProductResultKeys === false) {
						$cachedProductResultKeys = array_keys($thisProduct);
					}
					$cachedProductResults[$index] = array_values($thisProduct);
				}
				if (!isWebCrawler() && !$_SESSION['speed_tester']) {
					if ($totalParameters == 1 && empty($userParameters['search_text']) && $resultCount > 200) {
						setCachedData("product_search_results", $cacheKey, array("cached_product_results" => $cachedProductResults, "cached_product_result_keys" => $cachedProductResultKeys, "constraints" => $constraints, "result_count" => $resultCount, "display_search_text" => $displaySearchText), 2);
					}
				}
				$cachedProductResults = null;
				$cachedProductResultKeys = null;
				if (!empty($userParameters['minimum_price']) || !empty($userParameters['maximum_price'])) {
					foreach ($productResults as $index => $thisProduct) {
						$salePrice = str_replace(",", "", $productResults[$index]['sale_price']);
						if ($productResults[$index]['sale_price'] === false || !is_numeric($salePrice)) {
							$resultCount--;
							unset($productResults[$index]);
							continue;
						}
						if (!empty($userParameters['minimum_price']) && $salePrice < $userParameters['minimum_price']) {
							$resultCount--;
							unset($productResults[$index]);
							continue;
						}
						if (!empty($userParameters['maximum_price']) && $salePrice > $userParameters['maximum_price']) {
							$resultCount--;
							unset($productResults[$index]);
							continue;
						}
					}
				}
				$queryTime .= $productCatalog->getQueryTime() . "\n";
				if ($GLOBALS['gUserRow']['superuser_flag']) {
					$queryString = $productCatalog->getQueryString();
				}
				unset($productCatalog);
				$productCatalog = false;
			}
		} else {
			$productCatalog = false;
		}
		$endTime = getMilliseconds();
		$queryTime .= "After products are loaded: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
		$startTime = getMilliseconds();

		$productCategories = array();
		$resultSet = executeReadQuery("select * from product_categories where inactive = 0 and internal_use_only = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$productCategories[] = array("id" => $row['product_category_id'], "description" => $row['description']);
		}

		$facetDescriptions = array();
		$resultSet = executeReadQuery("select * from product_facets where inactive = 0 and internal_use_only = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$facetDescriptions[] = array("id" => $row['product_facet_id'], "description" => $row['description']);
		}
		$contributorTypes = array();
		if (strpos($catalogResultHtml, "contributor") !== false) {
			$resultSet = executeReadQuery("select * from contributor_types where client_id = ?", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$contributorTypes[] = $row;
			}
		}
		$endTime = getMilliseconds();
		$queryTime .= "Search Finished: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
		$startTime = getMilliseconds();

		$validFacetOptionIds = array();
		if (is_array($constraints['facets'])) {
			foreach ($constraints['facets'] as $facetOptionValues) {
				foreach ($facetOptionValues as $thisOptionValue) {
					$validFacetOptionIds[$thisOptionValue['product_facet_option_id']] = $thisOptionValue['product_facet_option_id'];
				}
			}
		}
		foreach ($productResults as $index => $thisProduct) {
			$productFacetOptionIds = explode(",", $thisProduct['product_facet_option_ids']);
			$newOptionIds = array();
			foreach ($productFacetOptionIds as $thisOptionId) {
				if (array_key_exists($thisOptionId, $validFacetOptionIds)) {
					$newOptionIds[$thisOptionId] = $thisOptionId;
				}
			}
			$productResults[$index]['product_facet_option_ids'] = implode(",", $newOptionIds);
		}
		$endTime = getMilliseconds();
		$queryTime .= "Facets: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
		$startTime = getMilliseconds();

		$necessaryFields = array("product_id", "product_code", "description", "product_manufacturer_id", "product_category_ids", "product_tag_ids", "product_facet_option_ids",
			"image_base_filename", "remote_image", "sale_price", "hide_dollar", "manufacturer_advertised_price", "inventory_quantity", "product_detail_link", "map_enforced",
			"call_price", "no_online_order", "relevance", "inventory_quantity_distributor", "product_group");
		$removeFields = array("manufacturer_name" => "manufacturer_name");

		$excludeNonDefaultLocations = getPreference("EXCLUDE_LOCATIONS_FROM_SIDEBAR");
		if ($GLOBALS['gLoggedIn']) {
			$defaultLocationId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID");
		} else {
			$defaultLocationId = $_COOKIE['default_location_id'];
		}
		$locationInventories = array();
		$resultSet = executeReadQuery("select * from locations where client_id = ? and user_location = 0 and not_searchable = 0 and inactive = 0 and internal_use_only = 0 and " .
			(empty($excludeNonDefaultLocations) ? "" : "location_id = " . (empty($defaultLocationId) ? "0" : makeParameter($defaultLocationId)) . " and ") .
			"product_distributor_id is null order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$locationInventories[] = $row['location_id'];
			$necessaryFields[] = "inventory_quantity_" . $row['location_id'];
		}
		$endTime = getMilliseconds();
		$queryTime .= "Locations: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
		$startTime = getMilliseconds();

		if (count($productResults) > 0) {
			$remoteImageUrlUsed = false;
			foreach ($productResults as $index => $thisProduct) {
				if (empty($thisProduct['product_group']) && empty($neverOutOfStock) && empty($thisProduct['non_inventory_item'])) {
					$quantity = $inventoryCounts[$thisProduct['product_id']]['total'];
					if (empty($quantity) || $quantity < 0) {
						$quantity = 0;
					}
				} else {
					$quantity = 1;
				}
				if (strpos($catalogResultHtml, "contributor") !== false) {
					$resultSet = executeReadQuery("select * from product_contributors join contributors using (contributor_id) join contributor_types using (contributor_type_id) where product_id = ?",
						$thisProduct['product_id']);
					while ($row = getNextRow($resultSet)) {
						if (!array_key_exists("contributor:" . strtolower($row['contributor_type_code']), $productResults[$index])) {
							$productResults[$index]['contributor:' . strtolower($row['contributor_type_code'])] = $row['full_name'];
						}
					}
					foreach ($contributorTypes as $row) {
						if (!array_key_exists("contributor:" . strtolower($row['contributor_type_code']), $productResults[$index])) {
							$productResults[$index]['contributor:' . strtolower($row['contributor_type_code'])] = "";
						}
					}
				}
				$productResults[$index]['inventory_quantity'] = $quantity;

				foreach ($locationInventories as $locationId) {
					$thisQuantity = $inventoryCounts[$thisProduct['product_id']][$locationId];
					$productResults[$index]['inventory_quantity_' . $locationId] = (empty($thisQuantity) ? 0 : $thisQuantity);
				}
				$productResults[$index]['inventory_quantity_distributor'] = $inventoryCounts[$thisProduct['product_id']]['distributor'];

				if ((!empty($thisProduct['no_online_order']) && empty($showInStoreOnlyPrice)) || empty($productResults[$index]['sale_price'])) {
					$productResults[$index]['sale_price'] = $callForPriceText;
					$productResults[$index]['hide_dollar'] = true;
				}
				$productResults[$index]['product_format'] = (empty($thisProduct['product_format_id']) ? "" : getReadFieldFromId("description", "product_formats", "product_format_id", $thisProduct['product_format_id']));
				if (!empty($thisProduct['remote_image_url'])) {
					$remoteImageUrlUsed = true;
				}
			}
			if ($remoteImageUrlUsed) {
				$necessaryFields['remote_image_url'] = "remote_image_url";
			}

			$allFields = array_keys(reset($productResults));

			foreach ($allFields as $thisFieldName) {
				if (in_array($thisFieldName, $necessaryFields)) {
					continue;
				}
				if (strpos($catalogResultHtml, "%" . $thisFieldName . "%") === false) {
					$removeFields[$thisFieldName] = $thisFieldName;
				}
			}

			$productFieldNames = array_keys(array_diff_key(reset($productResults), $removeFields));
			foreach ($productResults as $index => $result) {
				$productResults[$index] = array_values(array_diff_key($result, $removeFields));
			}
		}
		$manufacturerNames = array();
		$endTime = getMilliseconds();
		$queryTime .= "Process Products: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
		$startTime = getMilliseconds();

		$mapPolicies = getCachedData("map_policies", "", true);
		if (empty($mapPolicies)) {
			$mapPolicies = array();
			$resultSet = executeQuery("select * from map_policies");
			while ($row = getNextRow($resultSet)) {
				$mapPolicies[$row['map_policy_id']] = $row['map_policy_code'];
			}
			setCachedData("map_policies", "", $mapPolicies, 168, true);
		}
		$defaultMapPolicyId = getPreference("DEFAULT_MAP_POLICY_ID");
		$resultSet = executeReadQuery("select product_manufacturer_id,description,map_policy_id,(select product_manufacturer_map_holiday_id from product_manufacturer_map_holidays where " .
			"product_manufacturer_id = product_manufacturers.product_manufacturer_id and start_date <= current_date and end_date >= current_date limit 1) map_holiday from " .
			"product_manufacturers where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$row['map_policy_id'] = $defaultMapPolicyId ?: $row['map_policy_id'];
			$mapPolicyCode = $mapPolicies[$row['map_policy_id']];
			$manufacturerNames[$row['product_manufacturer_id']] = array($row['description'], ($mapPolicyCode == "IGNORE" || !empty($row['map_holiday']) ? "1" : "0"), $mapPolicyCode);
		}
		$endTime = getMilliseconds();
		$queryTime .= "MAP Policies: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";

		$shoppingCartProductIds = array();
		$shoppingCartId = ShoppingCart::getShoppingCartIdOnly("RETAIL");
		$resultSet = executeReadQuery("select product_id from shopping_cart_items where shopping_cart_id = ?", $shoppingCartId);
		while ($row = getNextRow($resultSet)) {
			$shoppingCartProductIds[] = $row['product_id'];
		}
		$endTime = getMilliseconds();
		$queryTime .= "Shopping Cart: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
		$wishListProductIds = array();
		if ($GLOBALS['gLoggedIn']) {
			$wishListId = getReadFieldFromId("wish_list_id", "wish_lists", "user_id", $GLOBALS['gUserId']);
			if (!empty($wishListId)) {
				$resultSet = executeReadQuery("select product_id from wish_list_items where wish_list_id = ?", $wishListId);
				while ($row = getNextRow($resultSet)) {
					$wishListProductIds[] = $row['product_id'];
				}
			}
		}
		$endTime = getMilliseconds();
		$queryTime .= "Wish List: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
		$productTagCodes = array();
		$resultSet = executeReadQuery("select * from product_tags where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$productTagCodes[$row['product_tag_id']] = strtolower($row['product_tag_code']);
		}
		$orderedItems = array();
		if ($GLOBALS['gLoggedIn']) {
			$resultSet = executeReadQuery("select * from orders join order_items using (order_id) where contact_id = ? and orders.deleted = 0 and order_items.deleted = 0 order by order_time", $GLOBALS['gUserRow']['contact_id']);
			while ($row = getNextRow($resultSet)) {
				$orderedItems[] = array("product_id" => $row['product_id'], "order_date" => date("m/d/Y", strtotime($row['order_time'])));
			}
		}
		$credovaCredentials = getCredovaCredentials();
		$credovaUserName = $credovaCredentials['username'];
		$credovaPassword = $credovaCredentials['password'];
		$credovaTest = $credovaCredentials['test_environment'];

		$resultsKey = "";
		$problemCaching = false;

		# If the number of results is greater than 1000, store results in the SESSION so that the page loads quickly. Results will be gotten by ajax.

		if (count($productResults) > 1000) {
			$resultsKey = getRandomString(32);
			if (!is_array($_SESSION['product_search_results_array'])) {
				$_SESSION['product_search_results_array'] = array();
			}
			if (!is_array($_SESSION['product_search_results_timestamp'])) {
				$_SESSION['product_search_results_timestamp'] = array();
			}
			$_SESSION['product_search_results_array'][$resultsKey] = $productResults;
			$_SESSION['product_search_results_timestamp'][$resultsKey] = time();
			if (!is_array($_SESSION['product_search_results_array'][$resultsKey]) || empty($_SESSION['product_search_results_array'][$resultsKey])) {
				$resultsKey = "";
				$problemCaching = true;
			}
			saveSessionData();
		}
		if (empty($resultsKey)) {
			$resultsString = jsonEncode($productResults);
			$resultsString = str_replace(".0000", "", $resultsString);
			$resultsString = str_replace(",false", ",", $resultsString);
			$resultsString = str_replace(',""', ',', $resultsString);
		} else {
			$resultsString = "";
		}
		$endTime = getMilliseconds();
		$queryTime .= "Search Completed: " . round(($endTime - $startTime) / 1000, 2) . ", Memory Used: " . number_format(memory_get_usage(), 0, "", ",") . "\n";
		$startTime = getMilliseconds();

		$neverOutOfStock = getPreference("RETAIL_STORE_NEVER_OUT_OF_STOCK");
		if (empty(getPreference("IGNORE_USER_DEFAULT_LOCATION"))) {
			$defaultLocationId = $_COOKIE['default_location_id'];
		}

		$siteSearchPageLink = false;
		if (!empty($userParameters['search_text'])) {
			$siteSearchPageId = getFieldFromId("page_id", "pages", "script_filename", "search.php", "inactive = 0 and (domain_name is null or domain_name = ?)", getDomainName(true));
			if (!empty($siteSearchPageId) && !canAccessPage($siteSearchPageId)) {
				$siteSearchPageId = "";
			}
			if (!empty($siteSearchPageId)) {
				$pageRow = getRowFromId("pages", "page_id", $siteSearchPageId);
				$siteSearchPageLink = (empty($pageRow['link_name']) ? "/search.php" : "/" . $pageRow['link_name']) . "?search_text=" . urlencode($userParameters['search_text']);
			}
		}
		?>
        <script>
			<?php if ($limitedResults && $resultCount > ($this->iLimitedResultsCount - 100)) { ?>
            setTimeout(function () {
                displayErrorMessage("Limited results displayed. Narrow search for better results.");
            });
			<?php } ?>
            forceTile = false;
			<?php if ($siteSearchPageLink) { ?>
            siteSearchPageLink = "<?= $siteSearchPageLink ?>";
			<?php } ?>
			<?php if ($problemCaching) { ?>
            var problemCaching = true;
            console.log("Problem Caching Results");
			<?php } ?>
            var productSearchResultsKey = "<?= $resultsKey ?>";
			<?php if (!empty($neverOutOfStock)) { ?>
            neverOutOfStock = true;
			<?php } ?>
            credovaTestEnvironment = <?= (empty($credovaTest) ? "false" : "true") ?>;
            credovaUserName = "<?= $credovaUserName ?>";
            manufacturerNames = <?= jsonEncode($manufacturerNames) ?>;
            postVariables = <?= jsonEncode($userParameters) ?>;
            displaySearchText = "<?= $displaySearchText ?>";
            productFieldNames = <?= jsonEncode($productFieldNames) ?>;
            productKeyLookup = false;
			<?php if (empty($resultsKey)) { ?>
            productResults = <?= $resultsString ?>;
			<?php } else { ?>
            productResults = false;
			<?php } ?>
            facetDescriptions = <?= jsonEncode($facetDescriptions) ?>;
            productCategories = <?= jsonEncode($productCategories) ?>;
            constraints = <?= jsonEncode($constraints) ?>;
            resultCount = <?= (empty($resultCount) ? 0 : $resultCount) ?>;
            queryTime = <?= jsonEncode(array($queryTime)) ?>;
            pageGroupingData = <?= jsonEncode($this->iPageGroupingData) ?>;
            productCategoryGroupIds = <?= jsonEncode($productCategoryGroupIds) ?>;
            emptyImageFilename = "<?= $missingProductImage ?>";
            shoppingCartProductIds = <?= jsonEncode($shoppingCartProductIds) ?>;
            wishListProductIds = <?= jsonEncode($wishListProductIds) ?>;
            productTagCodes = <?= jsonEncode($productTagCodes) ?>;
            orderedItems = <?= jsonEncode($orderedItems) ?>;
            userDefaultLocationId = "<?= $defaultLocationId ?>";
            inStorePurchaseOnlyText = "<?= (getFragment("IN_STORE_PURCHASE_ONLY_TEXT") ?: "In-store purchase only") ?>";
			<?php
			$availabilityTexts = productCatalog::getProductAvailabilityText(array(), array(), false, true);
			if (empty($availabilityTexts)) {
				$availabilityTexts = array();
			}
			?>
            availabilityTexts = <?= jsonEncode($availabilityTexts) ?>;
			<?php
			?>

			<?php if ($cachedResultsUsed) { ?>
            console.log("Cached Results Used");
			<?php } ?>
            console.log(queryTime[0]);
			<?php if ($GLOBALS['gUserRow']['superuser_flag']) { ?>
			<?php if (!empty($queryString)) { ?>
            console.log("<?= $queryString ?>");
			<?php } ?>
			<?php } ?>
        </script>
		<?php
	}

	function javascript() {

		/* possible classes
		page-grouping-data-primary_id
		page-grouping-data-primary_key
		page-grouping-data-label
		page-grouping-data-description
		page-grouping-data-detailed_description
		page-grouping-data-image_id
		page-grouping-data-image_url
		*/

		?>
        <!--suppress JSUnresolvedVariable -->
        <script>
            function initialProductLoad() {
				<?php if (isWebCrawler() || $_SESSION['speed_tester']) { ?>
                $(".results-count-wrapper").css("display", "none");
				<?php } ?>
                for (let i in pageGroupingData) {
                    const $pageGroupingData = $(".page-grouping-data-" + i);
                    if ($pageGroupingData.length > 0) {
                        $pageGroupingData.each(function () {
                            if ($(this).is("textarea") || $(this).is("select") || $(this).is("input")) {
                                $(this).val(pageGroupingData[i]);
                            } else {
                                $(this).html(pageGroupingData[i]);
                            }
                        });
                    }
                }
                for (let i in postVariables) {
                    const $postElement = $("#" + i);
                    if ($postElement.length > 0) {
                        $postElement.val(postVariables[i]);
                    }
                }
                if (typeof beforeDisplaySearchResults === "function") {
                    if (beforeDisplaySearchResults()) {
                        return;
                    }
                }
                if ("label" in pageGroupingData && !empty(pageGroupingData['label'])) {
                    addFilter(pageGroupingData['label'], pageGroupingData['description'], pageGroupingData['primary_key']);
                }
                if (!empty($.cookie("product_sort_order"))) {
                    $("#product_sort_order").val($.cookie("product_sort_order"));
                }
                if (!empty($.cookie("show_count"))) {
                    $("#show_count").val($.cookie("show_count"));
                }
                if (typeof productResults !== 'object') {
                    location.reload();
                }
                displaySearchResults();
                buildSidebarFilters();
                if (!empty(displaySearchText)) {
                    addFilter("Search Text", displaySearchText, "search_text");
                    if (!empty(siteSearchPageLink)) {
                        $("#selected_filters").append("<div id='_site_search_link'><a href='" + siteSearchPageLink + "'></a></div>");
                        $("#_site_search_link").find("a").text("search site for '" + displaySearchText + "'");
                    }
                }
				<?php if (empty(getPreference("DONT_RERUN_EMPTY_RESULTS"))) { ?>
                if (resultCount <= 0 && !empty(postVariables['search_text']) && "primary_id" in pageGroupingData && !empty(pageGroupingData['primary_id'])) {
                    $("#_search_results").append("<h2 class='align-center' id='searching_just_text'>Rerunning search with just text</h2>");
                    setTimeout(function () {
                        $("#selected_filters").find(".primary-selected-filter").each(function () {
                            const fieldName = $(this).data("field_name");
                            if (fieldName !== "search_text") {
                                $(this).trigger("click");
                                return false;
                            }
                        });
                    }, 2000);
                }
				<?php } else { ?>
                if (resultCount <= 0) { 
                    $("#_search_results").append("<h2 class='align-center'>No Results Found MAHATHI INFOTECH RESULTS</h2>");
                }
				<?php } ?>
            }

            $(function () {
                setTimeout(function () {
                    if (productSearchResultsKey !=undefined && empty(productSearchResultsKey)) {
                        initialProductLoad();
                    } else {
                        $("body").addClass("no-waiting-for-ajax");
                        $("#_search_results").html("<h3 class='align-left' style='margin: 40px auto;text-align: left;'>Loading initial results</h3><p class='align-left' style='margin-bottom: 40px;'><span style='font-size: 6rem' class='fad fa-spinner fa-spin'></span></p>");
                        loadAjaxRequest("/retail-store-controller?ajax=true&url_action=get_product_search_results&results_key=" + productSearchResultsKey, function (returnArray) {
                            if ("product_search_results" in returnArray) {
                                productResults = returnArray['product_search_results'];
                                initialProductLoad();
                            }
                        });
                    }
                }, 500);
            });
        </script>
		<?php
	}

	function productSearchResults() {
		?>
        <div id="_search_results_outer">
            <div id="_search_controls">
				<?php
				$sortOptions = array("relevance" => "Relevance", "description" => "Product Title", "lowest_price" => "Lowest Price", "highest_price" => "Highest Price", "brand" => "Brand", "sku" => "SKU");
				$currentSort = $_COOKIE['product_sort_order'];
				if (empty($currentSort) || !array_key_exists($currentSort, $sortOptions)) {
					$currentSort = "relevance";
				}
				if ($GLOBALS['gUserRow']['administrator_flag']) {
					?>
                    <span id="reload_cache" class="fas fa-redo-alt"></span>
				<?php } ?>

                <div id="product_sort_order_control_wrapper">
                    <label id="product_sort_order_label" for='product_sort_order'>Sort By</label>
                    <select id="product_sort_order">
						<?php foreach ($sortOptions as $thisSort => $description) { ?>
                            <option <?= ($thisSort == $currentSort ? "selected " : "") ?> value="<?= $thisSort ?>"><?= $description ?></option>
						<?php } ?>
                    </select>
                </div>

				<?php
				$countOptions = array("8", "20", "40", "60", "80", "100");
				$showCount = $_COOKIE['show_count'];
				if (empty($showCount) || !in_array($showCount, $countOptions)) {
					$showCount = "40";
				}
				?>
                <div id="show_count_control_wrapper">
                    <label id="show_count_label" for="show_count">Show</label>
                    <select id="show_count">
						<?php foreach ($countOptions as $thisCount) { ?>
                            <option <?= ($thisCount == $showCount ? "selected" : "") ?> value="<?= $thisCount ?>"><?= $thisCount ?></option>
						<?php } ?>
                    </select>
                </div>

				<?php
				$listType = $_COOKIE['result_display_type'];
				if (empty($listType) || $listType != "list") {
					$listType = "tile";
				}
				?>
                <div id="result_display_type_wrapper">
                    <span id="result_display_type_tile" data-result_display_type="tile" class='result-display-type fad fa-th<?= ($listType != "list" ? " selected" : "") ?>'></span><span id="result_display_type_list" data-result_display_type="list" class='result-display-type fad fa-list<?= ($listType == "list" ? " selected" : "") ?>'></span>
                </div>

                <div id="paging_control_wrapper" class="paging-control hidden align-right">
                    <input type="hidden" id="page_number" value="1">
                    <a href="#" class="previous-page"><span class="fas fa-backward"></span></a><span id="_top_paging_control_pages"></span><a href="#" class="next-page"><span class="fas fa-forward"></span></a>
                </div>

            </div> <!-- _search_controls -->

            <div id="_search_results">
            </div>

            <div id="bottom_paging_control_wrapper" class="paging-control hidden">
                <a href="#" class="previous-page"><span class="fas fa-backward"></span></a><span id="_bottom_paging_control_pages"></span><a href="#" class="next-page"><span class="fas fa-forward"></span></a>
            </div>

        </div> <!-- _search_results_outer -->
		<?php
		return true;
	}

	function jqueryTemplates() {
		?>
        <div id='catalog_result_product_tags_template'>
            <div class='catalog-result-product-tags'>
				<?php
				$resultSet = executeReadQuery("select * from product_tags where client_id = ? and display_color is not null and internal_use_only = 0 and inactive = 0 order by sort_order,description", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					?>
                    <div class='catalog-result-product-tag catalog-result-product-tag-<?= strtolower(str_replace("_", "-", $row['product_tag_code'])) ?>'><?= htmlText($row['description']) ?></div>
					<?php
				}
				?>
            </div>
        </div>
		<?php
		$sidebarFilter = $this->getPageTextChunk("retail_store_sidebar_filter");
		if (empty($sidebarFilter)) {
			$sidebarFilter = $this->getFragment("retail_store_sidebar_filter");
		}
		if (empty($sidebarFilter)) {
			ob_start();
			?>
            <div id="_sidebar_filter">
                <div class='sidebar-filter %other_classes%' data-field_name='%field_name%' id="%filter_id%">
                    <h3>%filter_title%<span class='fa fa-plus'></span><span class='fa fa-minus'></span></h3>
                    <div class='filter-options'>
                        <p class="filter-text-filter-wrapper"><input type="text" class="filter-text-filter" placeholder="%search_text%"></p>
                        <div class='filter-checkboxes'>
                            %filter_options%
                        </div>
                    </div>
                </div>
            </div>
			<?php
			$sidebarFilter = ob_get_clean();
		}
		echo $sidebarFilter;
	}

	function internalCSS() {
		?>
        <style>
            #_site_search_link {
                margin: 0 0 10px 50px;
                font-size: .7rem;
                padding: 0;
            }
            .catalog-item-compare-wrapper {
                text-align: center;
            }
            #_search_results_outer label.checkbox-label.catalog-item-compare-label {
                font-size: .8rem;
                padding-left: 10px;
            }
            button.catalog-item-compare-button {
                padding: 2px 8px;
                font-size: .6rem;
                height: auto;
                font-weight: 300;
                margin-left: 20px;
                width: auto;
            }
            .catalog-item.catalog-list-item {
                float: none;
                width: 100%;
                max-width: 100%;
                display: flex;
                height: auto;
                margin: 0 0 5px 0;
                padding: 10px 0;
                border: 1px solid rgb(180, 180, 180);
            }

            .catalog-item.catalog-list-item:hover {
                border: 1px solid rgb(180, 180, 180);
            }

            .catalog-item.catalog-list-item .catalog-item-thumbnail a {
                height: 100%;
                display: block;
                position: relative;
            }

            .catalog-item.catalog-list-item > div {
                flex: 0 0 15%;
                padding: 0;
            }

            .catalog-item.catalog-list-item > div.click-product-detail {
                flex: 0 0 35%;
            }

            .catalog-item.catalog-list-item > div.catalog-item-button-wrapper {
                flex: 0 0 35%;
                display: flex;
                justify-content: flex-start;
            }

            .catalog-item.catalog-list-item > div.catalog-item-button-wrapper > div {
                padding: 0;
                margin: 0 20px 0 0;
                flex-grow: 0;
            }

            .catalog-item.catalog-list-item .catalog-item-thumbnail {
                height: auto;
                min-height: 0;
                border: none;
                padding: 0;
                margin: 0;
            }

            .catalog-item.catalog-list-item .catalog-item-description {
                text-align: left;
                height: auto;
                max-height: none;
                padding-top: 5px;
            }

            .catalog-item.catalog-list-item button {
                font-size: .6rem;
                display: block;
                padding: 5px 20px;
                width: auto;
                height: auto;
                margin: 0;
                line-height: 1.2;
                min-height: 40px;
            }

            .catalog-item.catalog-list-item button span.button-subtext {
                font-size: .6rem;
            }

            .catalog-item.catalog-list-item .catalog-item-thumbnail img {
                max-width: 70px;
                max-height: 50px;
                position: relative;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                display: block;
            }

            .catalog-item.catalog-list-item .catalog-item-description:after {
                display: none;
            }

            #result_display_type_wrapper {
                width: 120px;
                max-width: 120px;
            }

            #result_display_type_wrapper span {
                font-size: 30px;
                cursor: pointer;
                position: absolute;
                top: 50%;
                left: 30%;
                transform: translate(-50%, -50%);
                color: rgb(200, 200, 200);
            }

            #result_display_type_wrapper span:last-child {
                left: 70%;
            }

            #result_display_type_wrapper span.selected {
                color: rgb(0, 125, 0);
            }

            #result_display_type_wrapper span:hover {
                color: rgb(100, 100, 100);
            }

            #hide_out_of_stock_wrapper {
                text-align: left;
                padding-left: 10px;
            }

            #available_in_store_today_wrapper {
                text-align: left;
                padding-left: 10px;
            }

            #_search_result_details_wrapper {
                position: relative;
                padding: 20px;
            }

            #_search_result_close_details_wrapper {
                position: absolute;
                top: 30px;
                left: 30px;
                width: 40px;
                height: 40px;
                cursor: pointer;
            }

            #_search_result_close_details_wrapper span {
                color: rgb(200, 200, 200);
                font-size: 1.6rem;
            }

            #location_availability_wrapper {
                display: block;
                text-align: right;
            }

            #reload_cache {
                font-size: 1.2rem;
                cursor: pointer;
                position: relative;
                margin: 5px 20px 0 10px;
            }

            #_search_results_outer {
                flex: 1 1 auto;
            }

            #_search_results {
                padding: 0 20px;
            }

            #results_count_wrapper {
                background: none;
                color: rgb(5, 57, 107);
                font-size: 1.5rem;
                font-weight: 700;
                text-align: left;
            }

            #_search_controls {
                padding: 10px;
                border: 1px solid rgba(177, 177, 177, 0.44);
                margin: 0 20px 20px 20px;
                background: #fff;
                box-shadow: 0 1px 0.5px 0 rgba(177, 177, 177, 0.44);
                border-radius: 3px;
                display: flex;
                justify-content: flex-start;
            }

            #_search_controls div {
                padding-right: 20px;
                position: relative;
            }

            #_search_results_outer select {
                width: auto;
                max-width: 250px;
                border: 1px solid rgb(224, 224, 224);
                color: rgb(0, 0, 0);
                font-family: 'Muli', serif;
                font-size: 1.0rem;
                font-weight: normal;
                margin: 0;
                padding: 5px;
                border-radius: 3px;
                background: rgb(251, 251, 251);
                height: 34px;
            }

            #_search_results_outer label {
                margin-right: 20px;
                font-weight: 700;
            }

            #_search_results_outer label.checkbox-label {
                padding: 0;
                margin: 0;
            }

            #_search_results_outer select.page-number {
                margin: 0 10px;
            }

            #bottom_paging_control_wrapper {
                text-align: center;
                padding-bottom: 40px;
            }

            #_bottom_paging_control_pages {
                margin: 0 10px;
            }

            #_bottom_paging_control_pages a {
                margin: 0 10px;
                font-weight: 300;
            }

            #_bottom_paging_control_pages a.current-page {
                font-weight: 900;
                font-size: 150%;
            }

            #bottom_paging_control_wrapper select.page-number {
                margin: 0 10px;
                height: 34px;
            }

            #_top_paging_control_pages {
                margin: 0 10px;
            }

            #_top_paging_control_pages a {
                margin: 0 10px;
                font-weight: 300;
            }

            #_top_paging_control_pages a.current-page {
                font-weight: 900;
                font-size: 150%;
            }

            #top_paging_control_wrapper select.page-number {
                margin: 0 10px;
                height: 34px;
            }

            #results_wrapper {
                display: flex;
                width: 100%;
                padding: 0 20px;
            }

            #filter_sidebar {
                flex: 0 0 300px;
                border: 1px solid rgb(200, 200, 200);
                margin: 0 0 20px 20px;
                background: #fff;
                box-shadow: 0 1px 0.5px 0 rgba(177, 177, 177, 0.44);
                border-radius: 3px;
                max-width: 300px;
            }

            #sidebar_filter_title {
                padding: 20px;
                background-color: rgb(200, 200, 200);
                width: 100%;
                color: #ffffff;
                font-family: 'Muli', sans-serif;
                font-weight: 700;
                text-transform: uppercase;
                position: relative;
            }

            #results_count_wrapper {
                margin: 0 auto 20px auto;
                padding: 20px 40px;
                text-transform: uppercase;
            }

            h2#results_count_wrapper span {
                font-size: inherit;
            }

            h2#results_count_wrapper span.results-count {
                font-size: 2.4rem;
            }

            #sidebar_filters {
                margin-top: 10px;
            }

            #_selected_filter_wrapper {
                border-bottom: 1px solid rgb(200, 200, 200);
                padding-bottom: 20px;
                margin: 0 20px 10px 20px;
            }

            #_selected_filter_wrapper h3 {
                text-align: left;
                margin-left: auto;
                margin-right: auto;
                font-size: 1.2rem;
                color: rgb(50, 50, 50);
                position: relative;
                padding: 20px;
            }

            #_selected_filter_wrapper h3 span {
                position: absolute;
                right: 0;
                top: 50%;
                transform: translate(0px, -50%);
            }

            .sidebar-filter {
                padding: 0;
                width: 100%;
                border-bottom: 1px solid rgb(200, 200, 200);
            }

            .sidebar-filter h3 {
                text-align: left;
                font-size: 1.0rem;
                font-weight: 700;
                position: relative;
                margin: 0 auto;
                color: rgb(50, 50, 50);
                padding: 5px 10px;
                cursor: pointer;
            }

            .sidebar-filter h3 span {
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translate(0px, -50%);
                font-size: 1.2rem;
            }

            .sidebar-filter h3 span.fa-plus {
                display: inline;
            }

            .sidebar-filter h3 span.fa-minus {
                display: none;
            }

            .sidebar-filter.opened h3 span.fa-plus {
                display: none;
            }

            .sidebar-filter.opened h3 span.fa-minus {
                display: inline;
            }

            .sidebar-filter div.filter-options {
                display: none;
                padding-bottom: 10px;
            }

            .sidebar-filter.opened div.filter-options {
                display: block;
            }

            .sidebar-filter input[type=text] {
                font-size: .9rem;
                padding: 5px 10px;
                height: auto;
                width: 100%;
                max-width: 100%;
                margin: 0;
            }

            .sidebar-filter p {
                font-size: .9rem;
                font-weight: 300;
            }

            .sidebar-filter div label {
                font-weight: 300;
                font-size: .9rem;
                white-space: normal;
                display: block;
                flex: 1 1 auto;
                line-height: 1.2;
                cursor: pointer;
            }

            .sidebar-filter div.filter-checkboxes {
                margin-top: 5px;
                max-height: 150px;
                overflow: auto;
            }

            .sidebar-filter div.filter-options div.filter-option {
                margin: 0;
                display: flex;
                align-items: center;
            }

            .sidebar-filter div.filter-option-checkbox {
                padding: 0 10px 4px 10px;
                flex: 0 0 auto;
            }

            .sidebar-filter div.filter-option-label {
                flex: 1 1 auto;
            }

            div.selected_filters span.reductive-count {
                display: none;
            }

            div.selected-filter {
                width: auto;
                padding: 8px 16px;
                font-size: .8rem;
                cursor: pointer;
                font-weight: 900;
                background-color: rgb(240, 240, 240);
                border-radius: 15px;
                display: inline-block;
                margin: 0 10px 10px 0;
            }

            div.selected-filter .filter-text-value {
                font-weight: 400;
            }

            div.selected-filter span.fa-times-circle {
                margin-left: 10px;
                color: rgb(0, 150, 0);
                font-weight: 900;
                font-size: 1rem;
            }

            div.selected-filter.not-removable span.fa-times-circle {
                display: none;
            }

            .sidebar-filter.no-filter-text p.filter-text-filter-wrapper {
                display: none;
            }

            p.filter-text-filter-wrapper {
                margin: 0 auto;
                width: 90%;
            }

            #_product_details_content {
                font-family: 'Muli', serif;
                width: 100%;
                margin: 20px auto;
                padding-bottom: 20px;
            }

            #_product_details_wrapper {
                display: flex;
                margin-bottom: 20px;
            }

            #_product_details_image {
                flex: 1 1 50%;
                text-align: center;
                padding: 20px;
                position: relative;
                min-height: 300px;
            }

            #_product_details_image img {
                max-height: 300px;
                max-width: 90%;
                display: block;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
            }

            #_product_details {
                flex: 1 1 50%;
                font-size: 1.4rem;
            }

            #_product_details div {
                margin-bottom: 10px;
                color: rgb(88, 87, 87);
            }

            #_product_details_description {
                font-size: 1.8rem;
                margin-bottom: 30px;
            }

            #_product_details_full_page {
                width: 90%;
                margin: 0 auto 50px auto;
            }

            #_product_details_detailed_description {
                width: 90%;
                margin: auto;
                font-size: 1.2rem;
            }

            #_product_details_detailed_description p {
                letter-spacing: 1.5px;
                color: rgb(88, 87, 87);
            }

            #_product_details_content h3 {
                width: 90%;
                margin: auto;
                display: none;
            }

            #_product_details_specifications_wrapper {
                margin: 10px 0;
                display: none;
            }

            #_product_details_price_wrapper {
                margin: 20px 0;
                font-size: 1.6rem;
            }

            #_product_details_price {
                font-size: 2.0rem;
                font-weight: 600;
            }

            #_product_details_quantity {
                text-align: right;
                padding: 5px 10px;
                border-radius: 4px;
                margin-right: 10px;
                width: 80px;
                font-size: 1.2rem;
            }

            #_product_details_buttons {
                margin-top: 10px;
                display: flex;
                flex-direction: column;
                width: 50%;
            }

            #_product_details_buttons button {
                width: auto;
                margin-bottom: 10px;
                margin-right: 10px;
                font-family: 'Black Ops One', sans-serif;
            }

            #_product_details_brand {
                text-transform: uppercase;
            }

            #_product_details_product_code {
                text-transform: uppercase;
            }

            #_product_details_quantity_wrapper {
                text-transform: uppercase;
            }

            .product-details-specification {
                display: flex;
                width: 50%;
                margin: 0 60px;
            }

            .product-details-specification div {
                flex: 0 0 50%;
                padding: 8px 10px;
                box-shadow: 1px 0 0 0 rgb(220, 220, 220), 0 1px 0 0 #dcdcdc, 1px 1px 0 0 #dcdcdc, 1px 0 0 0 #dcdcdc inset, 0 1px 0 0 #dcdcdc inset;
            }

            .product-details-specification:nth-child(odd) {
                background-color: rgb(220, 220, 220);
            }

            .product-tag-code-class-3 .catalog-item-credova-financing {
                display: none;
            }

            @media (max-width: 1400px) {
                #_search_controls div {
                    padding-right: 10px;
                }

                #_search_results_outer label {
                    margin-right: 10px;
                }

                #_search_results_outer label {
                    display: block;
                }

                #_search_results_outer select {
                    height: 24px;
                    margin-top: 4px;
                }

                #_search_controls div#paging_control_wrapper {
                    padding-top: 15px;
                }
            }

            @media (max-width: 1000px) {
                #_search_controls {
                    display: block;
                }

                #_search_controls div {
                    margin-bottom: 10px;
                }

                #_search_controls div#paging_control_wrapper {
                    padding-top: 0;
                }
            }

            @media (max-width: 800px) {
                #_product_details_wrapper {
                    flex-direction: column;
                }

                #_product_details_image {
                    min-height: 150px;
                }

                #_product_details_buttons {
                    width: 100%;
                }

                #_product_details_image img {
                    max-height: 300px;
                    max-width: 90%;
                    display: block;
                    position: relative;
                    top: 0;
                    left: 0;
                    transform: none;
                    margin: auto;
                }

                #_product_details_buttons {
                    margin-right: 0;
                }

                #_product_details_content h3 {
                    text-align: center;
                }

                .product-details-specification {
                    width: 80%;
                    margin: auto;
                }

                #sidebar_filters {
                    max-height: 300px;
                    overflow-y: scroll;
                }
            }

            @media (max-width: 625px) {
                #results_wrapper {
                    flex-direction: column;
                }

                #_search_controls {
                    margin: 10px 0;
                }
            }

            @media (max-width: 800px) {
                #filter_sidebar {
                    margin: 0;
                }

                #_product_details_description {
                    font-size: 1.5rem;
                    font-weight: 600;
                }

                #_product_details_buttons button {
                    margin-bottom: 5px;
                    margin-right: 0;
                }

                #category_banner h1 {
                    font-size: 3rem;
                }
            }

            #_product_details_content {
                width: 100%;
                margin: 20px auto;
                padding-bottom: 20px;
            }

            #_product_details_wrapper {
                margin-bottom: 20px;
            }

            #_product_details_image {
                text-align: center;
                padding: 20px;
                position: relative;
                min-height: 300px;
            }

            #_product_details_image img {
                max-height: 300px;
                max-width: 90%;
                display: block;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
            }

            #_product_details {
                font-size: 1.4rem;
                display: block;
            }

            #_product_details div {
                margin-bottom: 10px;
                color: rgb(88, 87, 87);
            }

            #_product_details_description {
                font-size: 1.8rem;
                margin-bottom: 30px;
            }

            #_product_details_full_page {
                width: 90%;
                margin: 0 auto 50px auto;
            }

            #_product_details_detailed_description {
                width: 90%;
                margin: auto;
                font-size: 1.2rem;
            }

            #_product_details_detailed_description p {
                letter-spacing: 1.5px;
                color: rgb(88, 87, 87);
            }

            #_product_details_content h3 {
                width: 90%;
                margin: auto;
            }

            #_product_details_specifications_wrapper {
                margin: 10px 0;
            }

            #_product_details_price_wrapper {
                margin: 20px 0;
                font-size: 1.6rem;
            }

            #_product_details_price {
                font-size: 2.0rem;
                font-weight: 600;
            }

            #_product_details_quantity {
                text-align: right;
                padding: 5px 10px;
                border-radius: 4px;
                margin-right: 10px;
                width: 80px;
                font-size: 1.2rem;
            }

            #_product_details_buttons {
                margin-top: 10px;
                display: flex;
                flex-direction: column;
                width: 50%;
            }

            #_product_details_buttons button {
                width: auto;
                margin-bottom: 10px;
                margin-right: 10px;
                font-family: 'Black Ops One', sans-serif;
            }

            #_product_details_brand {
                text-transform: uppercase;
            }

            #_product_details_product_code {
                text-transform: uppercase;
            }

            #_product_details_quantity_wrapper {
                text-transform: uppercase;
            }

            .product-details-specification {
                display: flex;
                width: 50%;
                margin: 0 60px;
            }

            .product-details-specification div {
                flex: 0 0 50%;
                padding: 8px 10px;
                box-shadow: 1px 0 0 0 rgb(220, 220, 220), 0 1px 0 0 #dcdcdc, 1px 1px 0 0 #dcdcdc, 1px 0 0 0 #dcdcdc inset, 0 1px 0 0 #dcdcdc inset;
            }

            .product-details-specification:nth-child(odd) {
                background-color: rgb(220, 220, 220);
            }

            #specifications_table tbody tr td.specification-name {
                font-weight: 600;
            }

            @media (max-width: 800px) {
                #_product_details_wrapper {
                    flex-direction: column;
                }

                #_product_details_image {
                    min-height: 150px;
                }

                #_product_details_buttons {
                    width: 100%;
                }

                #_product_details_image img {
                    max-height: 300px;
                    max-width: 90%;
                    display: block;
                    position: relative;
                    top: 0;
                    left: 0;
                    transform: none;
                    margin: auto;
                }

                #_product_details_buttons {
                    margin-right: 0;
                }

                #_product_details_content h3 {
                    text-align: center;
                }

                .product-details-specification {
                    width: 80%;
                    margin: auto;
                }

                #_search_controls select {
                    margin-right: 20px;
                }
            }

            @media (max-width: 625px) {
                #_search_controls {
                    margin: 10px 0;
                }
            }

            @media only screen and (max-width: 800px) {
                #_product_details_description {
                    font-size: 1.5rem;
                    font-weight: 600;
                }

                #_product_details_buttons button {
                    margin-bottom: 5px;
                    margin-right: 0;
                }
            }

            #_search_results_wrapper {
                display: flex;
                flex-wrap: wrap;
            }

            .catalog-item {
                width: 280px;
                margin: 0 20px 20px 0;
                border: 1px solid rgb(200, 200, 200);
                padding: 20px;
                line-height: 1.2;
                background: #fff;
            }

            .catalog-item:hover {
                box-shadow: 0 1px 5px #aaa;
                border: 1px solid rgba(68, 68, 68, 0.62);
            }

            .catalog-item .info-label {
                font-size: 90%;
                margin-right: 10px;
            }

            .catalog-item img {
                max-width: 100%;
            }

            .click-product-detail {
                cursor: pointer;
            }

            .click-product-detail a:hover {
                color: rgb(140, 140, 140);
            }

            .catalog-item-description {
                font-size: 1.1rem;
                text-align: center;
                font-weight: 700;
                height: 110px;
                overflow: hidden;
                position: relative;
            }

            .catalog-item-description:after {
                content: "";
                position: absolute;
                top: 90px;
                left: 0;
                height: 20px;
                width: 100%;
                background: linear-gradient(rgba(255, 255, 255, 0), rgb(255, 255, 255));
            }

            .catalog-item-detailed-description {
                font-size: .8rem;
                margin-bottom: 10px;
                height: 100px;
                overflow: hidden;
                position: relative;
            }

            .catalog-item-detailed-description:after {
                content: "";
                position: absolute;
                top: 60px;
                left: 0;
                height: 40px;
                width: 100%;
                background: linear-gradient(rgba(255, 255, 255, 0), rgb(255, 255, 255));
            }

            .catalog-item-price-wrapper {
                font-size: 1.5rem;
                font-weight: 700;
                margin-bottom: 20px;
                margin-top: 10px;
                text-align: center;
            }

            .catalog-item-thumbnail {
                text-align: center;
                margin-bottom: 10px;
                height: 120px;
                position: relative;
            }

            .catalog-item-thumbnail img {
                max-height: 120px;
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                cursor: zoom-in;
            }

            .catalog-item-out-of-stock {
                padding: 5px;
                text-align: center;
            }

            .catalog-item-add-to-cart {
                padding: 5px;
                text-align: center;
            }

            .catalog-item-add-to-wishlist {
                padding: 5px;
                text-align: center;
            }

            .button-subtext {
                display: none;
            }

            .map-priced-product .button-subtext {
                display: inline;
            }

            .out-of-stock-product .button-subtext {
                display: inline;
                white-space: pre-line;
            }

            .catalog-item-out-of-stock {
                display: none;
            }

            .out-of-stock-product .catalog-item-out-of-stock {
                display: block;
            }

            .out-of-stock-product .catalog-item-add-to-cart {
                display: none;
            }

            .out-of-stock-product .catalog-item-location-availability {
                display: none;
            }

            .catalog-item-credova-financing {
                text-align: center;
            }

            .catalog-item-ordered {
                text-align: center;
            }

            @media only screen and (max-width: 1050px) {
                #result_display_type_wrapper {
                    display: none;
                }
            }

        </style>
		<?php
	}
}

$pageObject = new RetailStoreProductSearchResultsPage();
$pageObject->displayPage();
