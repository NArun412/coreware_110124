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
distributor checklist

- Connect
- get list of manufacturers
- get list of categories
- get list of facets
- get product list
- insert new products
	- get image for product
	- get cost of product
	- get manufacturer
	- set product data
	- set categories
	- set facet values
	- tag if FFL product
- update existing product
	- add image if not one
	- update cost if more
	- set manufacturer if not set
	- set facet value if one isn't set
- update inventory
	- set quantity
	- add cost of inventory
- place customer order
- place dealer order
- get shipment tracking information
- Calculate product cost
*/

abstract class ProductDistributor {
	protected $iErrorMessage = "";
	protected $iResponse = array();
	protected $iLocationCredentialRow = array();
	protected $iLocationRow = array();
	protected $iLocationContactRow = array();
	protected $iProductDistributorRow = array();
	protected $iClass3Products = false;
	protected $iFFLRequiredProductTagId = false;
	protected $iClass3ProductTagId = false;
	protected $iLocationId = false;
	protected $iInventoryAdjustmentTypeId = false;
	protected $iProductCostDifference = false;
	protected $iProductDistributorCode = false;
	protected $iProductDistributors = array();
	protected $iLoggingOn = false;
	protected $iLogContent = array();
	protected $iProductUpdateFields = array();
	protected $iIsTopDistributor = false;
	protected $iUsedDistributorProductCodes = array();
	protected $iIgnoreProductsNotFound = true;

	protected static $iCorewareShootingSports = false;
	protected static $iAuthoritativeSite = false;
	protected static $iLoadedValues = array();
	protected static $iValuesClientId = false;
	protected static $iHighestPriorityProductDistributorId = false;
	private $iLastMemory = 0;
	private $iLogEnabled = null;
	protected $iLogLength = 1000;
    protected $iInventoryLogBatch = array();
    protected $iInventoryBatch = array();
    protected $iBatchSize = 1000;

	function __construct($locationId) {
		$this->iLocationId = $locationId;
		self::$iCorewareShootingSports = $GLOBALS['gClientRow']['client_code'] == "COREWARE_SHOOTING_SPORTS";
        if($GLOBALS['gLocalExecution'] && !empty(getPreference("DEVELOPMENT_TEST_CATALOG"))) {
            self::$iCorewareShootingSports = true;
        }
		$this->iProductUpdateFields = ProductCatalog::getProductUpdateFields();
		$resultSet = executeQuery("select * from client_product_update_settings where client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$this->iProductUpdateFields[strtolower($row['product_update_field_code'])]['update_setting'] = $row['update_setting'];
		}
		self::loadValues("product_distributors");
		$this->getCredentials();
	}

	protected static function loadValues($valuesKey) {
		if (self::$iValuesClientId != $GLOBALS['gClientId']) {
            self::$iCorewareShootingSports = $GLOBALS['gClientRow']['client_code'] == "COREWARE_SHOOTING_SPORTS";
            if($GLOBALS['gLocalExecution'] && !empty(getPreference("DEVELOPMENT_TEST_CATALOG"))) {
                self::$iCorewareShootingSports = true;
            }
			$saveProductDistributors = false;
			if (array_key_exists("product_distributors", self::$iLoadedValues) && is_array(self::$iLoadedValues['product_distributors']) && !empty(self::$iLoadedValues['product_distributors'])) {
				$saveProductDistributors = self::$iLoadedValues['product_distributors'];
			}
            self::$iLoadedValues = array();
			self::$iValuesClientId = $GLOBALS['gClientId'];
			if (!empty($saveProductDistributors)) {
				self::$iLoadedValues['product_distributors'] = $saveProductDistributors;
			}
		}
		if (array_key_exists($valuesKey, self::$iLoadedValues)) {
			return;
		}
		$startTime = getMilliseconds();
		switch ($valuesKey) {
			case "product_distributors":
				self::$iLoadedValues['product_distributors'] = array();
				$resultSet = executeQuery("select * from product_distributors order by sort_order");
				while ($row = getNextRow($resultSet)) {
					self::$iLoadedValues['product_distributors'][$row['product_distributor_id']] = $row;
					if (empty(self::$iHighestPriorityProductDistributorId) && empty($row['inactive'])) {
						self::$iHighestPriorityProductDistributorId = $row['product_distributor_id'];
					}
				}
				freeResult($resultSet);
				break;
			case "inventory_locations":
				self::$iLoadedValues['inventory_locations'] = array();
				self::$iLoadedValues['locations'] = array();
				$productDistributorLocations = array();
				$resultSet = executeReadQuery("select * from locations where client_id = ? order by product_distributor_id, primary_location desc, location_id", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					self::$iLoadedValues['locations'][$row['location_id']] = $row;
					if (empty($row['product_distributor_id'])) {
						self::$iLoadedValues['inventory_locations'][$row['location_id']] = $row['location_id'];
						continue;
					}
					if (array_key_exists($row['product_distributor_id'], $productDistributorLocations)) {
						self::$iLoadedValues['inventory_locations'][$row['location_id']] = $productDistributorLocations[$row['product_distributor_id']];
						continue;
					}
					self::$iLoadedValues['inventory_locations'][$row['location_id']] = $row['location_id'];
					$productDistributorLocations[$row['product_distributor_id']] = $row['location_id'];
					if (empty($row['primary_location'])) {
						executeQuery("update locations set primary_location = 1 where location_id = ?", $row['location_id']);
					}
				}
				freeResult($resultSet);
				break;
			case "product_facet_values":
				self::$iLoadedValues['product_facet_values'] = array();
				$resultSet = executeReadQuery("select product_facet_value_id,product_id,product_facet_id,product_facet_option_id from product_facet_values where product_id in (select product_id from products where client_id = ?)", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['product_id'], self::$iLoadedValues['product_facet_values'])) {
						self::$iLoadedValues['product_facet_values'][$row['product_id']] = array();
					}
					self::$iLoadedValues['product_facet_values'][$row['product_id']][$row['product_facet_id']] = $row;
				}
				freeResult($resultSet);
				break;
			case "product_categories":
				self::$iLoadedValues['product_categories'] = array();
				$resultSet = executeReadQuery("select * from product_categories where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					self::$iLoadedValues['product_categories'][$row['product_category_code']] = $row['product_category_id'];
				}
				freeResult($resultSet);
				break;
			case "state_compliance_tags":
				self::$iLoadedValues['state_compliance_tags'] = array();
				$allStates = getStateArray();
				$resultSet = executeReadQuery("select * from product_tags where product_tag_code like '%_COMPLIANT' and client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$state = str_replace("_COMPLIANT", "", $row['product_tag_code']);
					if (array_key_exists($state, $allStates)) {
						self::$iLoadedValues['state_compliance_tags'][$state] = $row['product_tag_id'];
					}
				}
				freeResult($resultSet);
				break;
			case "existing_states":
				self::$iLoadedValues['existing_states'] = array();
				$resultSet = executeReadQuery("select product_id,state from product_restrictions where state is not null and postal_code is null and country_id = 1000 and product_id in (select product_id from products where client_id = ?)", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['product_id'], self::$iLoadedValues['existing_states'])) {
						self::$iLoadedValues['existing_states'][$row['product_id']] = array();
					}
					self::$iLoadedValues['existing_states'][$row['product_id']][] = $row['state'];
				}
				freeResult($resultSet);
				break;
			case "products":
			case "product_upc":
			case "product_id_from_upc":
				self::$iLoadedValues['products'] = array();
				self::$iLoadedValues['product_upc'] = array();
				self::$iLoadedValues['product_id_from_upc'] = array();
				$resultSet = executeReadQuery("select *,(select group_concat(image_identifier) from product_remote_images where product_id = products.product_id order by primary_image desc) product_remote_images " .
					"from products left outer join product_data using (product_id) where products.client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (empty($row['client_id'])) {
						$row['client_id'] = $GLOBALS['gClientId'];
					}
					self::$iLoadedValues['products'][$row['product_id']] = $row;
					if (!empty($row['upc_code'])) {
						self::$iLoadedValues['product_upc'][$row['product_id']] = $row['upc_code'];
						self::$iLoadedValues['product_id_from_upc'][$row['upc_code']] = $row['product_id'];
					}
				}
				freeResult($resultSet);
				break;
			case "product_facet_options":
				self::$iLoadedValues['product_facet_options'] = array();
				$resultSet = executeReadQuery("select *,(select product_facet_code from product_facets where product_facet_id = product_facet_options.product_facet_id) product_facet_code from product_facet_options where " .
					"product_facet_id in (select product_facet_id from product_facets where client_id = ?)", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['product_facet_id'], self::$iLoadedValues['product_facet_options'])) {
						self::$iLoadedValues['product_facet_options'][$row['product_facet_id']] = array();
					}
					$facetValue = trim(strtolower($row['facet_value']));
					self::$iLoadedValues['product_facet_options'][$row['product_facet_id']][$facetValue] = $row['product_facet_option_id'];
				}
				freeResult($resultSet);
				$productFacets = array();
				$resultSet = executeQuery("select * from product_facets where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$productFacets[$row['product_facet_code']] = $row['product_facet_id'];
				}
				freeResult($resultSet);
				$facetSet = executeReadQuery("select * from product_distributor_conversions where table_name = 'product_facet_options' and product_distributor_id is null and client_id = ?", $GLOBALS['gClientId']);
				while ($facetRow = getNextRow($facetSet)) {
					$productFacetId = $productFacets[$facetRow['original_value_qualifier']];
					if (!empty($productFacetId)) {
						self::$iLoadedValues['product_facet_options'][$productFacetId][trim(strtolower($facetRow['original_value']))] = $facetRow['primary_identifier'];
					}
				}
				freeResult($facetSet);
				break;
			case "product_facets":
				self::$iLoadedValues['locked_product_facets'] = array();
				self::$iLoadedValues['product_facets'] = array();
				foreach (self::$iLoadedValues['product_distributors'] as $productDistributorId => $row) {
					self::$iLoadedValues['product_facets'][$productDistributorId] = array();
				}
				if (self::$iCorewareShootingSports || self::$iAuthoritativeSite) {
					$resultSet = executeReadQuery("select * from product_facets join product_distributor_conversions on (product_facets.product_facet_id = product_distributor_conversions.primary_identifier) where " .
						"table_name = 'product_facets' and product_facets.client_id = ? and product_distributor_conversions.client_id = ?", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						if (!array_key_exists($row['product_distributor_id'], self::$iLoadedValues['product_facets'])) {
							self::$iLoadedValues['product_facets'][$row['product_distributor_id']] = array();
						}
						self::$iLoadedValues['product_facets'][$row['product_distributor_id']][$row['original_value']] = $row['product_facet_id'];
					}
				} else {
					$resultSet = executeReadQuery("select * from product_facets where client_id = ?", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						if (!empty($row['catalog_lock'])) {
							self::$iLoadedValues['locked_product_facets'][$row['product_facet_id']] = $row['product_facet_id'];
						}
						self::$iLoadedValues['product_facets'][$row['product_facet_code']] = $row['product_facet_id'];
					}
				}
				break;
			case "product_distributor_locations":
				self::$iLoadedValues['product_distributor_locations'] = array();
				$resultSet = executeReadQuery("select * from locations where client_id = ? and inactive = 0 and product_distributor_id is not null order by product_distributor_id,primary_location desc,location_id", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (array_key_exists($row['product_distributor_id'], self::$iLoadedValues['product_distributor_locations'])) {
						continue;
					}
					self::$iLoadedValues['product_distributor_locations'][$row['product_distributor_id']] = $row['location_id'];
					if (!$row['primary_location']) {
						executeQuery("update locations set primary_location = 1 where location_id = ?", $row['location_id']);
					}
				}
				break;
			case "product_inventories":
				self::$iLoadedValues['product_inventories'] = array();
				self::loadValues("inventory_locations");
				ProductCatalog::getInventoryAdjustmentTypes();
				$inventoryAdjustmentTypeId = $GLOBALS['gInventoryAdjustmentTypeId'];
				$restockAdjustmentTypeId = $GLOBALS['gRestockAdjustmentTypeId'];
				if (!empty(self::$iLoadedValues['inventory_locations'])) {
					foreach (self::$iLoadedValues['inventory_locations'] as $locationId) {
						if (!array_key_exists($locationId, self::$iLoadedValues['product_inventories'])) {
							self::$iLoadedValues['product_inventories'][$locationId] = array();
						}
						$resultSet = executeReadQuery("select product_id,product_inventory_id,quantity,location_cost, 
                            (select count(*) from product_inventory_log where product_inventory_log.product_inventory_id = product_inventories.product_inventory_id) log_count from product_inventories where location_id = ?", $locationId);
						while ($row = getNextRow($resultSet)) {
							self::$iLoadedValues['product_inventories'][$locationId][$row['product_id']] = $row;
						}
						freeResult($resultSet);
					}
				}
				foreach (self::$iLoadedValues['product_distributor_locations'] as $locationId) {
					if (!array_key_exists($locationId, self::$iLoadedValues['product_inventories'])) {
						self::$iLoadedValues['product_inventories'][$locationId] = array();
					}
				}
				break;
			case "cannot_sell_products":
			case "cannot_sell_product_manufacturers":
				self::$iLoadedValues['cannot_sell_products'] = array();
				self::$iLoadedValues['cannot_sell_product_manufacturers'] = array();
				foreach (self::$iLoadedValues['product_distributors'] as $productDistributorId => $row) {
					self::$iLoadedValues['cannot_sell_products'][$productDistributorId] = array();
					self::$iLoadedValues['cannot_sell_product_manufacturers'][$productDistributorId] = array();
				}
				$resultSet = executeReadQuery("select * from product_manufacturer_cannot_sell_distributors join products using (product_manufacturer_id) where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['product_distributor_id'], self::$iLoadedValues['cannot_sell_products'])) {
						self::$iLoadedValues['cannot_sell_products'][$row['product_distributor_id']] = array();
					}
					if (!array_key_exists($row['product_distributor_id'], self::$iLoadedValues['cannot_sell_product_manufacturers'])) {
						self::$iLoadedValues['cannot_sell_product_manufacturers'][$row['product_distributor_id']] = array();
					}
					self::$iLoadedValues['cannot_sell_products'][$row['product_distributor_id']][$row['product_id']] = true;
					self::$iLoadedValues['cannot_sell_product_manufacturers'][$row['product_distributor_id']][$row['product_manufacturer_id']] = true;
				}
				$resultSet = executeReadQuery("select * from product_category_cannot_sell_distributors join product_category_links using (product_category_id) where product_category_id in (select product_category_id from product_categories where client_id = ?)", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['product_distributor_id'], self::$iLoadedValues['cannot_sell_products'])) {
						self::$iLoadedValues['cannot_sell_products'][$row['product_distributor_id']] = array();
					}
					self::$iLoadedValues['cannot_sell_products'][$row['product_distributor_id']][$row['product_id']] = true;
				}
				$resultSet = executeReadQuery("select * from product_department_cannot_sell_distributors where product_department_id in (select product_department_id from product_departments where client_id = ?)", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$productSet = executeReadQuery("select product_id from product_category_links where product_category_id in (select product_category_id from product_categories where inactive = 0) and " .
						"product_category_id in (select product_category_id from product_category_departments where product_department_id = ?)", $row['product_department_id']);
					while ($productRow = getNextRow($productSet)) {
						if (!array_key_exists($row['product_distributor_id'], self::$iLoadedValues['cannot_sell_products'])) {
							self::$iLoadedValues['cannot_sell_products'][$row['product_distributor_id']] = array();
						}
						self::$iLoadedValues['cannot_sell_products'][$row['product_distributor_id']][$productRow['product_id']] = true;
					}
					$productSet = executeReadQuery("select product_id from product_category_links where product_category_id in (select product_category_id from product_categories where inactive = 0) and " .
						"product_category_id in (select product_category_id from product_category_group_links where " .
						"product_category_group_id in (select product_category_group_id from product_category_group_departments where product_department_id = ? and " .
						"product_department_id in (select product_department_id from product_departments where inactive = 0)) and product_category_group_id in " .
						"(select product_category_group_id from product_category_groups where inactive = 0))", $row['product_department_id']);
					while ($productRow = getNextRow($productSet)) {
						if (!array_key_exists($row['product_distributor_id'], self::$iLoadedValues['cannot_sell_products'])) {
							self::$iLoadedValues['cannot_sell_products'][$row['product_distributor_id']] = array();
						}
						self::$iLoadedValues['cannot_sell_products'][$row['product_distributor_id']][$productRow['product_id']] = true;
					}

					if (!array_key_exists($row['product_distributor_id'], self::$iLoadedValues['cannot_sell_products'])) {
						self::$iLoadedValues['cannot_sell_products'][$row['product_distributor_id']] = array();
					}
					self::$iLoadedValues['cannot_sell_products'][$row['product_distributor_id']][$row['product_id']] = true;
				}
				break;
			case "product_category_links":
				self::$iLoadedValues['product_category_links'] = array();
				$resultSet = executeReadQuery("select product_id,product_category_id from product_category_links where product_id in (select product_id from products where client_id = ?)", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['product_id'], self::$iLoadedValues['product_category_links'])) {
						self::$iLoadedValues['product_category_links'][$row['product_id']] = array();
					}
					self::$iLoadedValues['product_category_links'][$row['product_id']][] = $row['product_category_id'];
				}
				freeResult($resultSet);
				break;
			case "product_tags":
				self::$iLoadedValues['product_tags'] = array();
				$resultSet = executeReadQuery("select * from product_tag_links where product_id in (select product_id from products where client_id = ?)", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['product_id'], self::$iLoadedValues['product_tags'])) {
						self::$iLoadedValues['product_tags'][$row['product_id']] = array();
					}
					self::$iLoadedValues['product_tags'][$row['product_id']][$row['product_tag_id']] = true;
				}
				break;
			case "product_manufacturers":
				self::$iLoadedValues['product_manufacturers'] = array();
				if (self::$iCorewareShootingSports || self::$iAuthoritativeSite) {
					foreach (self::$iLoadedValues['product_distributors'] as $productDistributorId => $row) {
						self::$iLoadedValues['product_manufacturers'][$productDistributorId] = array();
					}
					$resultSet = executeReadQuery("select * from product_distributor_conversions where table_name = 'product_manufacturers' and client_id = ?", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						if (!array_key_exists($row['product_distributor_id'], self::$iLoadedValues['product_manufacturers'])) {
							self::$iLoadedValues['product_manufacturers'][$row['product_distributor_id']] = array();
						}
						self::$iLoadedValues['product_manufacturers'][$row['product_distributor_id']][$row['original_value']] = $row['primary_identifier'];
					}
				} else {
					self::$iLoadedValues['product_manufacturers']['manufacturer_codes'] = array();
					$resultSet = executeReadQuery("select * from product_manufacturers where client_id = ?", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						self::$iLoadedValues['product_manufacturers']['manufacturer_codes'][$row['product_manufacturer_code']] = $row['product_manufacturer_id'];
					}
				}
				freeResult($resultSet);
				break;
			case "no_update_products":
				self::$iLoadedValues['no_update_products'] = array();
				$resultSet = executeReadQuery("select product_id from products where client_id = ? and no_update = 1", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					self::$iLoadedValues['no_update_products'][$row['product_id']] = true;
				}
				break;
			case "no_update_description_products":
				self::$iLoadedValues['no_update_description_products'] = array();
				$resultSet = executeReadQuery("select primary_identifier from custom_field_data where text_data = '1' and custom_field_id in (select custom_field_id from custom_fields where client_id = ? and " .
					"custom_field_code = 'NEVER_UPDATE_DESCRIPTION' and custom_field_type_id in (select custom_field_type_id from custom_field_types where custom_field_type_code = 'PRODUCTS'))", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					self::$iLoadedValues['no_update_description_products'][$row['product_id']] = true;
				}
				break;
			case "product_distributor_products":                # which distributors have this product
			case "distributor_product_codes":                   # store information accessible by product_distributor_id and product_code
			case "distributor_product_codes_by_product_id":     # store information keyed by product_distributor_id and product_id
				self::$iLoadedValues['product_distributor_products'] = array();
				self::$iLoadedValues['distributor_product_codes'] = array();
				self::$iLoadedValues['distributor_product_codes_by_product_id'] = array();
				foreach (self::$iLoadedValues['product_distributors'] as $productDistributorId => $row) {
					self::$iLoadedValues['distributor_product_codes'][$productDistributorId] = array();
					self::$iLoadedValues['distributor_product_codes_by_product_id'][$productDistributorId] = array();
				}
				$resultSet = executeReadQuery("select distributor_product_code_id,product_id,product_code,product_distributor_id from distributor_product_codes where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if (!array_key_exists($row['product_id'], self::$iLoadedValues['product_distributor_products'])) {
						self::$iLoadedValues['product_distributor_products'][$row['product_id']] = array();
					}
					self::$iLoadedValues['product_distributor_products'][$row['product_id']][] = $row['product_distributor_id'];

					if (!array_key_exists($row['product_distributor_id'], self::$iLoadedValues['distributor_product_codes'])) {
						self::$iLoadedValues['distributor_product_codes'][$row['product_distributor_id']] = array();
					}
					self::$iLoadedValues['distributor_product_codes'][$row['product_distributor_id']][$row['product_code']] = $row;

					if (!array_key_exists($row['product_distributor_id'], self::$iLoadedValues['distributor_product_codes_by_product_id'])) {
						self::$iLoadedValues['distributor_product_codes_by_product_id'][$row['product_distributor_id']] = array();
					}
					self::$iLoadedValues['distributor_product_codes_by_product_id'][$row['product_distributor_id']][$row['product_id']] = $row;
				}
				freeResult($resultSet);
				break;
		}
		$endTime = getMilliseconds();
		if ($GLOBALS['gUserRow']['superuser_flag']) {
			addDebugLog("get " . $valuesKey . ": " . round(($endTime - $startTime) / 1000, 4));
		}
	}

    protected static function getLoadedRow($valuesKey, $rowKey, $subRowKey = false) {
        self::loadValues($valuesKey);
        $row = array();
        if(array_key_exists($rowKey, self::$iLoadedValues[$valuesKey])) {
            $row = self::$iLoadedValues[$valuesKey][$rowKey];
            if($subRowKey !== false) {
                if(array_key_exists($subRowKey, $row)) {
                    return $row[$subRowKey];
                } else {
                    $row = array();
                }
            }
        }
        return $row;
    }

	protected function isTopProductDistributor($productId) {
		if ($this->iIsTopDistributor) {
			return true;
		}
		self::loadValues("product_distributor_products");
		if (!array_key_exists($productId, self::$iLoadedValues['product_distributor_products'])) {
			return false;
		}
		foreach (self::$iLoadedValues['product_distributors'] as $productDistributorId => $row) {
			if (in_array($productDistributorId, self::$iLoadedValues['product_distributor_products'][$productId])) {
				return ($productDistributorId == $this->iProductDistributorRow['product_distributor_id']);
			}
		}
		return false;
	}

	function getCredentials() {
		$resultSet = executeQuery("select * from location_credentials where location_id = ? and location_id in (select location_id from locations where client_id = ?)", $this->iLocationId, $GLOBALS['gClientId']);
		$this->iLocationCredentialRow = getNextRow($resultSet);
		freeResult($resultSet);
		$this->iLocationRow = getRowFromId("locations", "location_id", $this->iLocationId);
		$this->iLocationContactRow = Contact::getContact($this->iLocationRow['contact_id']);
		if (!empty($this->iLocationRow['product_distributor_id'])) {
			if (empty($this->iLocationContactRow['business_name']) || empty($this->iLocationContactRow['address_1']) || empty($this->iLocationContactRow['city']) || empty($this->iLocationContactRow['postal_code'])) {
				if (!empty($this->iLocationRow['user_location'])) {
					$this->iLocationContactRow = Contact::getContactFromUserId($this->iLocationRow['user_id']);
				} else {
					$this->iLocationContactRow = getRowFromId("addresses", "contact_id", $GLOBALS['gClientRow']['contact_id'], "address_label = 'Shipping'");
					if (empty($this->iLocationContactRow)) {
						$this->iLocationContactRow = $GLOBALS['gClientRow'];
					}
					if (empty($this->iLocationContactRow['email_address'])) {
						$this->iLocationContactRow['email_address'] = $GLOBALS['gClientRow']['email_address'];
					}
				}
			}
			$this->iLocationContactRow['phone_number'] = Contact::getContactPhoneNumber($this->iLocationContactRow['contact_id'], 'Primary');
		}
		$this->iProductDistributorRow = getRowFromId("product_distributors", "product_distributor_id", $this->iLocationRow['product_distributor_id']);
		if (self::$iHighestPriorityProductDistributorId == $this->iProductDistributorRow['product_distributor_id']) {
			$this->iIsTopDistributor = true;
		}
	}

	public static function getProductDistributorInstance($locationId) {
		$productDistributorId = getFieldFromId("product_distributor_id", "locations", "location_id", $locationId);
		$productDistributorClass = getFieldFromId("class_name", "product_distributors", "product_distributor_id", $productDistributorId, "inactive = 0");
		if (empty($productDistributorClass)) {
			return false;
		} else {
			/** @var ProductDistributor $productDistributor */
			$productDistributor = new $productDistributorClass($locationId);
			if ($productDistributor->hasCredentials()) {
				return $productDistributor;
			} else {
				return false;
			}
		}
	}

	function hasCredentials() {
		return (!empty($this->iLocationCredentialRow));
	}

	public static function getLocationCodeInstance($locationCode) {
		$locationId = getFieldFromId("location_id", "locations", "location_code", $locationCode);
		$productDistributorId = getFieldFromId("product_distributor_id", "locations", "location_id", $locationId);
		$productDistributorClass = getFieldFromId("class_name", "product_distributors", "product_distributor_id", $productDistributorId, "inactive = 0");
		if (empty($productDistributorClass)) {
			return false;
		} else {
			/** @var ProductDistributor $productDistributor */
			$productDistributor = new $productDistributorClass($locationId);
			if ($productDistributor->hasCredentials()) {
				return $productDistributor;
			} else {
				return false;
			}
		}
	}

	public static function setPrimaryDistributorLocation() {
		$productDistributorIds = array();
		$locationSet = executeQuery("select * from locations where client_id = ? and inactive = 0 and product_distributor_id is not null order by product_distributor_id,primary_location desc,location_id", $GLOBALS['gClientId']);
		while ($locationRow = getNextRow($locationSet)) {
			if (in_array($locationRow['product_distributor_id'], $productDistributorIds)) {
				if (!empty($locationRow['primary_location'])) {
					executeQuery("update locations set primary_location = 0 where location_id = ?", $locationRow['location_id']);
				}
				continue;
			}
			$productDistributorIds[] = $locationRow['product_distributor_id'];
			if (empty($locationRow['primary_location'])) {
				executeQuery("update locations set primary_location = 1 where location_id = ?", $locationRow['location_id']);
			}
		}
	}

	function addLog($resultLine = "", $showMemory = true) {
		if (is_null($this->iLogEnabled)) {
			$this->iLogEnabled = !empty(getPreference("DEBUG_PRODUCT_DISTRIBUTORS"));
			$logLength = getPreference("DEBUG_PRODUCT_DISTRIBUTORS_LOG_LENGTH");
			$this->iLogLength = is_numeric($logLength) ? $logLength : $this->iLogLength;
		}
		if ($this->iLogEnabled) {
			$currentMemory = memory_get_usage() / 1000;
			$memoryChange = $currentMemory - $this->iLastMemory;
			$this->iLastMemory = $currentMemory;
			addDebugLog($resultLine . ($showMemory ? sprintf(" Memory Used: %s KB Change: %s KB", number_format($currentMemory), number_format($memoryChange)) : ""));
		}
	}

	function __destruct() {
		if (!empty($this->iLogContent)) {
			addProgramLog(implode("\n", $this->iLogContent));
		}
	}

	function getProductMetadata() {
		if (self::$iCorewareShootingSports) {
			$GLOBALS['coreware_product_metadata'] = array();
			return;
		}
		if (empty($GLOBALS['coreware_product_metadata'])) {
			self::downloadProductMetaData();
		}
	}

	static function downloadProductMetaData() {
		$rawProductMetadata = getCachedData("coreware_metadata_zip", "");
		if (empty($rawProductMetadata) || strlen($rawProductMetadata) < 10000) {
			$parameters = array("connection_key" => "760C0DCAB2BD193B585EB9734F34B3B6");
			$hostUrl = "https://shootingsports.coreware.com/api.php?action=get_product_metadata";
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
			$rawProductMetadata = $response;
			setCachedData("coreware_metadata_zip", "", $rawProductMetadata, 4);
		}
		if (strlen($rawProductMetadata) < 10000) {
			$GLOBALS['coreware_product_metadata'] = array();
		}

		$uncompressedProductMetadata = json_decode(gzdecode($rawProductMetadata), true);
		$productMetadata = array();
		foreach ($uncompressedProductMetadata['values'] as $index => $row) {
			$thisArray = array();
			foreach ($uncompressedProductMetadata['keys'] as $keyIndex => $thisField) {
				$thisArray[$thisField] = $row[$keyIndex];
			}
			$productMetadata[$index] = $thisArray;
		}
		$GLOBALS['coreware_product_metadata'] = $productMetadata;
	}

	function getErrorMessage() {
		return $this->iErrorMessage;
	}

	function getResponse() {
		return $this->iResponse;
	}

	function getProductDistributorId() {
		return $this->iProductDistributorRow['product_distributor_id'];
	}

	function getClientId() {
		return $this->iLocationRow['client_id'];
	}

	function getInventoryAdjustmentType() {
		if (empty($this->iInventoryAdjustmentTypeId)) {
			ProductCatalog::getInventoryAdjustmentTypes();
			$this->iInventoryAdjustmentTypeId = $GLOBALS['gInventoryAdjustmentTypeId'];
		}
		return $this->iInventoryAdjustmentTypeId;
	}

	function getFirearmsProductTags() {
		$this->iFFLRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED");
		if (empty($this->iFFLRequiredProductTagId)) {
			$insertSet = executeQuery("insert into product_tags (client_id,product_tag_code,description,internal_use_only) values (?,'FFL_REQUIRED','FFL Required',1)", $GLOBALS['gClientId']);
			$this->iFFLRequiredProductTagId = $insertSet['insert_id'];
			freeResult($insertSet);
		}
		$this->iClass3ProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "CLASS_3");
		if (empty($this->iClass3ProductTagId)) {
			$insertSet = executeQuery("insert into product_tags (client_id,product_tag_code,description,internal_use_only) values (?,'CLASS_3','Class 3 Products',1)", $GLOBALS['gClientId']);
			$this->iClass3ProductTagId = $insertSet['insert_id'];
			freeResult($insertSet);
		}
	}

	function getManufacturer($manufacturerCode) {
		self::loadValues("product_manufacturers");
		if (self::$iCorewareShootingSports || self::$iAuthoritativeSite) {
			$manufacturerCode = makeCode($manufacturerCode);
			$productManufacturerId = self::$iLoadedValues['product_manufacturers'][$this->iLocationRow['product_distributor_id']][$manufacturerCode];
		} else {
			$productManufacturerId = self::$iLoadedValues['product_manufacturers']['manufacturer_codes'][$manufacturerCode];
		}
		if ($productManufacturerId <= 0) {
			$productManufacturerId = "";
		}
		return $productManufacturerId;
	}

	function updateManufacturer($productId, $manufacturerCode) {
		if (empty($manufacturerCode) || empty($productId)) {
			return;
		}
		$productManufacturerId = $this->getManufacturer($manufacturerCode);
		if (!empty($productManufacturerId)) {
			$this->updateProductField($productId, "product_manufacturer_id", $productManufacturerId);
		}
	}

	function updateProductInformation($productId, $productInformation, $corewareProductData) {
		self::loadValues("no_update_products");
		self::loadValues("no_update_description_products");
		self::loadValues("product_distributor_products");
		self::loadValues("products");
		if (!is_numeric($corewareProductData['manufacturer_advertised_price']) || $corewareProductData['manufacturer_advertised_price'] < 1) {
			unset($corewareProductData['manufacturer_advertised_price']);
		}
		$thisDistributorPriority = $this->iProductDistributorRow['sort_order'];
		if ($thisDistributorPriority == 0 || empty($thisDistributorPriority)) {
			$thisDistributorPriority = 999999;
		}
		$higherPriorityFound = false;
		foreach (self::$iLoadedValues['product_distributors'] as $thisDistributor) {
			if ($thisDistributor['sort_order'] > 0 && $thisDistributor['sort_order'] < $thisDistributorPriority && array_key_exists($productId, self::$iLoadedValues['product_distributor_products']) && in_array($thisDistributor['product_distributor_id'], self::$iLoadedValues['product_distributor_products'][$productId])) {
				$higherPriorityFound = true;
				if (array_key_exists("manufacturer_advertised_price", $corewareProductData)) {
					unset($corewareProductData['manufacturer_advertised_price']);
				}
				break;
			}
		}
		if (array_key_exists("manufacturer_advertised_price", $corewareProductData)) {
			if (!empty($productInformation['map_expiration_date']) && $productInformation['map_expiration_date'] >= date("Y-m-d")) {
				unset($corewareProductData['manufacturer_advertised_price']);
			}
		}
		$currentDistributorPriority = self::$iLoadedValues['product_distributors'][$productInformation['product_distributor_id']]['sort_order'];
		if (empty($currentDistributorPriority)) {
			$currentDistributorPriority = 999999;
		}

		$productDataRow = array();
		$productRow = array();

		foreach ($this->iProductUpdateFields as $thisProductUpdateField) {
			if ($thisProductUpdateField['product_update_field_code'] == "manufacturer_advertised_price" && !array_key_exists("manufacturer_advertised_price", $corewareProductData)) {
				continue;
			}
			if (!empty($productInformation['no_update']) && empty($thisProductUpdateField['internal_use_only'])) {
				continue;
			}
			if ($thisProductUpdateField['product_update_field_code'] == "description" && array_key_exists($productId, self::$iLoadedValues['no_update_description_products'])) {
				continue;
			}
			$numberValues = array("weight", "height", "length", "width", "list_price", "base_cost", "manufacturer_advertised_price");
			if (empty($thisProductUpdateField['table_name'])) {
				continue;
			}
			if ($thisProductUpdateField['update_setting'] == "N") {
				continue;
			}
			if ($thisProductUpdateField['update_setting'] == "M" && !empty($productInformation[$thisProductUpdateField['product_update_field_code']])) {
				continue;
			}
			if (!array_key_exists($thisProductUpdateField['product_update_field_code'], $corewareProductData) || strlen($corewareProductData[$thisProductUpdateField['product_update_field_code']]) == 0) {
				continue;
			}
			if (!empty($productInformation[$thisProductUpdateField['product_update_field_code']]) && $higherPriorityFound) {
				continue;
			}
			$oldValue = $productInformation[$thisProductUpdateField['product_update_field_code']];
			$newValue = $corewareProductData[$thisProductUpdateField['product_update_field_code']];
			if (in_array($thisProductUpdateField['product_update_field_code'], $numberValues)) {
				if (!is_numeric($newValue)) {
					continue;
				}
				$oldValue = showSignificant($oldValue, 4);
				$newValue = showSignificant($newValue, 4);
				if ($newValue <= 0) {
					continue;
				}
			}
			if ($oldValue != $newValue) {
				switch ($thisProductUpdateField['table_name']) {
					case "product_data":
						if ($thisProductUpdateField['product_update_field_code'] == "manufacturer_advertised_price" || empty($productInformation['product_distributor_id']) || $thisDistributorPriority <= $currentDistributorPriority || empty($oldValue)) {
							$productDataRow[$thisProductUpdateField['product_update_field_code']] = $newValue;
						}
						break;
					case "products":
						$productRow[$thisProductUpdateField['product_update_field_code']] = $newValue;
						break;
				}
			}
		}

		$baseCost = $productInformation['base_cost'];
		if (empty($baseCost)) {
			$baseCost = 0;
		}
		if (!empty($corewareProductData['manufacturer_advertised_price'])) {
			$mapDifference = $corewareProductData['manufacturer_advertised_price'] - $baseCost;
			if ($mapDifference <= 0) {
				$productDataRow['manufacturer_advertised_price'] = "";
			}
		}

		$updated = false;
		if (self::$iCorewareShootingSports) {
			$GLOBALS['gChangeLogNotes'] = "Change (#592) by " . $this->iProductDistributorRow['description'];
		} else {
			$GLOBALS['gChangeLogNotes'] = "Change (#853) from catalog during sync with " . $this->iProductDistributorRow['description'];
		}
		if (!empty($productDataRow)) {
			$changeFound = false;
			foreach ($productDataRow as $fieldName => $fieldData) {
				if (self::$iLoadedValues['products'][$productId][$fieldName] != $fieldData) {
					$changeFound = true;
				}
			}
			if ($changeFound) {
				$dataTable = new DataTable("product_data");
				$productDataRow['product_id'] = $productId;
				$productDataRow['product_distributor_id'] = $this->iProductDistributorRow['product_distributor_id'];
				$dataTable->setSaveOnlyPresent(true);
				$dataTable->saveRecord(array("name_values" => $productDataRow, "primary_id" => $productInformation['product_data_id']));
				foreach ($productDataRow as $fieldName => $fieldData) {
					self::$iLoadedValues['products'][$productId][$fieldName] = $fieldData;
				}
			}
		}
		if (!empty($productRow)) {
			$changeFound = false;
			foreach ($productRow as $fieldName => $fieldData) {
				if (self::$iLoadedValues['products'][$productId][$fieldName] != $fieldData) {
					$changeFound = true;
				}
			}
			if ($changeFound) {
				$dataTable = new DataTable("products");
				$dataTable->setSaveOnlyPresent(true);
				$dataTable->saveRecord(array("name_values" => $productRow, "primary_id" => $productId));
				if (array_key_exists("description", $productRow) || array_key_exists("detailed_description", $productRow) ||
					array_key_exists("product_manufacturer_id", $productRow)) {
					$updated = true;
				}
				if (array_key_exists("base_cost", $productRow)) {
					ProductCatalog::calculateAllProductSalePrices($productId);
				}
				foreach ($productRow as $fieldName => $fieldData) {
					self::$iLoadedValues['products'][$productId][$fieldName] = $fieldData;
				}
			}
		}
		if ($updated) {
			ProductCatalog::reindexProducts($productId);
		}
		$GLOBALS['gChangeLogNotes'] = "";
		return $updated;
	}

	function updateProductField($productId, $fieldName, $value) {
		$GLOBALS['gChangeLogNotes'] = "Change (#857) by " . $this->iProductDistributorRow['description'];
		$nameValues = array("time_changed" => date("Y-m-d H:i:s"), $fieldName => $value);
		$dataTable = new DataTable("products");
		$dataTable->setSaveOnlyPresent(true);
		$result = $dataTable->saveRecord(array("name_values" => $nameValues, "primary_id" => $productId));
		$GLOBALS['gChangeLogNotes'] = "";
		if (in_array($fieldName, array("description", "detailed_description", "product_manufacturer_id"))) {
			ProductCatalog::reindexProducts($productId);
		}
		return !empty($result);
	}


	function addProductTag($productId, $productTagId) {
		if (empty($productTagId) || empty($productId)) {
			return;
		}
		self::loadValues("product_tags");
		if (!array_key_exists($productId, self::$iLoadedValues['product_tags']) || !array_key_exists($productTagId, self::$iLoadedValues['product_tags'][$productId])) {
			$GLOBALS['gChangeLogNotes'] = "Change (#956) by " . $this->iProductDistributorRow['description'];
			$dataTable = new DataTable("product_tag_links");
			$dataTable->saveRecord(array("name_values" => array("product_id" => $productId, "product_tag_id" => $productTagId)));
			if (!array_key_exists($productId, self::$iLoadedValues['product_tags'])) {
				self::$iLoadedValues['product_tags'][$productId] = array();
			}
			self::$iLoadedValues['product_tags'][$productId][$productTagId] = true;
			$GLOBALS['gChangeLogNotes'] = "";
		}
	}

	function removeProductTag($productId, $productTagId) {
		if (empty($productTagId) || empty($productId)) {
			return;
		}
		self::loadValues("product_tags");
		if (array_key_exists($productId, self::$iLoadedValues['product_tags']) && array_key_exists($productTagId, self::$iLoadedValues['product_tags'][$productId])) {
			$deleteSet = executeQuery("delete from product_tag_links where product_id = ? and product_tag_id = ?", $productId, $productTagId);
			freeResult($deleteSet);
			unset(self::$iLoadedValues['product_tags'][$productId][$productTagId]);
		}
	}

	function addProductCategories($productId, $productCategoryCodes, $forceUpdate = false) {
		if (empty($productCategoryCodes)) {
			return;
		}
		if (!self::$iCorewareShootingSports && !$forceUpdate) {
			if ($this->hasProductCategories($productId)) {
				return;
			}
		}
		$dataTable = new DataTable("product_category_links");
		$dataTable->setSaveOnlyPresent(true);
		$GLOBALS['gChangeLogNotes'] = "Change (#183) by " . $this->iProductDistributorRow['description'];
		foreach ($productCategoryCodes as $thisCategoryCode) {
			$productCategoryId = false;
			if (self::$iCorewareShootingSports || self::$iAuthoritativeSite) {
				$productCategoryId = getFieldFromId("primary_identifier", "product_distributor_conversions", "table_name", "product_categories", "product_distributor_id = ? and original_value = ?",
					$this->iLocationRow['product_distributor_id'], $thisCategoryCode);
				$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_id", $productCategoryId);
			}
			if (empty($productCategoryId) && !self::$iCorewareShootingSports) {
				$productCategoryId = getFieldFromId("product_category_id", "product_categories", "product_category_code", $thisCategoryCode, "inactive = 0");
			}
			if (empty($productCategoryId) || $productCategoryId < 0) {
				continue;
			}
			if (!array_key_exists($productId, self::$iLoadedValues['product_category_links']) || !in_array($productCategoryId, self::$iLoadedValues['product_category_links'][$productId])) {
				$dataTable->saveRecord(array("name_values" => array("product_id" => $productId, "product_category_id" => $productCategoryId, "sequence_number" => 1), "primary_id" => ""));
				if (!array_key_exists($productId, self::$iLoadedValues['product_category_links'])) {
					self::$iLoadedValues['product_category_links'][$productId] = array();
				}
				self::$iLoadedValues['product_category_links'][$productId][] = $productCategoryId;
			}
		}
		$GLOBALS['gChangeLogNotes'] = "";
	}

	protected function hasProductCategories($productId) {
		self::loadValues("product_category_links");
		return (array_key_exists($productId, self::$iLoadedValues['product_category_links']) && !empty(self::$iLoadedValues['product_category_links'][$productId]));
	}

	function addProductFacets($productId, $productFacetValues, $forceUpdate = false) {
		if (empty($productFacetValues)) {
			return;
		}
		$facetOptionsTable = new DataTable("product_facet_options");
		$facetOptionsTable->setSaveOnlyPresent(true);
		$facetValuesTable = new DataTable("product_facet_values");
		$facetValuesTable->setSaveOnlyPresent(true);
		$GLOBALS['gChangeLogNotes'] = "Change (#863) by " . $this->iProductDistributorRow['description'];
		self::loadValues("product_facets");

		foreach ($productFacetValues as $thisFacetInformation) {
			$productFacetCode = $thisFacetInformation['product_facet_code'];
			$facetValue = $thisFacetInformation['facet_value'];
			if (self::$iCorewareShootingSports || self::$iAuthoritativeSite) {
				$productFacetId = self::$iLoadedValues['product_facets'][$this->iLocationRow['product_distributor_id']][$productFacetCode];
			} else {
				$productFacetId = self::$iLoadedValues[$productFacetCode];
			}
			if (empty($productFacetId) || $productFacetId < 0) {
				continue;
			}
			$productFacetOptionId = $this->getProductFacetOption($facetValue, $productFacetId);
			if (empty($productFacetOptionId)) {
				$productFacetOptionId = $facetOptionsTable->saveRecord(array("name_values" => array("product_facet_id" => $productFacetId, "facet_value" => $facetValue), "primary_id" => ""));
				if (!array_key_exists($productFacetId, self::$iLoadedValues['product_facet_options'])) {
					self::$iLoadedValues['product_facet_options'][$productFacetId] = array();
				}
				self::$iLoadedValues['product_facet_options'][$productFacetId][trim(strtolower($facetValue))] = $productFacetOptionId;
			}
			if (!array_key_exists($productId, self::$iLoadedValues['product_facet_values'])) {
				self::$iLoadedValues['product_facet_values'][$productId] = array();
			}
			if (!array_key_exists($productFacetId, self::$iLoadedValues['product_facet_values'][$productId])) {
				if (!array_key_exists($productFacetId, self::$iLoadedValues['product_facet_values'][$productId])) {
					$productFacetValueId = $facetValuesTable->saveRecord(array("name_values" => array("product_id" => $productId, "product_facet_id" => $productFacetId, "product_facet_option_id" => $productFacetOptionId), "primary_id" => ""));
					self::$iLoadedValues['product_facet_values'][$productId][$productFacetId] = array("product_facet_value_id" => $productFacetValueId, "product_facet_option_id" => $productFacetOptionId);
				}
			} elseif ($forceUpdate) {
				if ($productFacetOptionId != self::$iLoadedValues['product_facet_values'][$productId][$productFacetId]['product_facet_option_id']) {
					$productFacetValueId = self::$iLoadedValues['product_facet_values'][$productId][$productFacetId]['product_facet_value_id'];
					$facetValuesTable->saveRecord(array("name_values" => array("product_facet_option_id" => $productFacetOptionId), "primary_id" => $productFacetValueId));
					self::$iLoadedValues['product_facet_values'][$productId][$productFacetId] = array("product_facet_value_id" => $productFacetValueId, "product_facet_option_id" => $productFacetOptionId);
				}
			}
		}
		$GLOBALS['gChangeLogNotes'] = "";
	}

	function getProductFacetOption($facetValue, $productFacetId) {
		self::loadValues("product_facet_options");
		$facetValue = trim(strtolower($facetValue));
		if (array_key_exists($productFacetId, self::$iLoadedValues['product_facet_options']) && array_key_exists($facetValue, self::$iLoadedValues['product_facet_options'][$productFacetId])) {
			return self::$iLoadedValues['product_facet_options'][$productFacetId][$facetValue];
		} else {
			return false;
		}
	}

	function getLocation() {
		return $this->iLocationId;
	}

	function adjustQuantity($productId, $quantity, $productCost) {
		if (empty($quantity) || $quantity < 0) {
			$quantity = 0;
		}
		if (!empty($this->iLocationRow['out_of_stock_threshold']) && $quantity <= $this->iLocationRow['out_of_stock_threshold']) {
			$quantity = 0;
		}
		if (!empty($this->iLocationRow['cost_threshold']) && $this->iLocationRow['cost_threshold'] > 0 && $productCost <= $this->iLocationRow['cost_threshold']) {
			$quantity = 0;
		}

		if ($quantity > 0) {
			self::loadValues("cannot_sell_products");
			if (array_key_exists($productId, self::$iLoadedValues['cannot_sell_products'][$this->iLocationRow['product_distributor_id']])) {
				$quantity = 0;
			}
		}
		return $quantity;
	}

	function processInventoryUpdates($inventoryUpdateArray) {
		$allocatedProductTagId = false;
        $startTime = getMilliseconds();
        $this->addLog(sprintf("Processing %s inventory updates for %s", count($inventoryUpdateArray), $this->iProductDistributorCode));

        executeQuery("create temporary table if not exists product_inventory_temp (product_inventory_id INT, quantity INT, location_cost decimal(12,2), INDEX (product_inventory_id, location_cost))");

		executeQuery("update locations set has_allocated_inventory = 0 where location_id = ?", $this->iLocationRow['location_id']);

		$foundProductIds = array();
		$processCount = 0;
		$notFoundCount = 0;
		$sameCount = 0;
		$allocatedSet = false;
		self::loadValues("distributor_product_codes");
		self::loadValues("product_distributor_locations");
		self::loadValues("product_inventories");

        ProcessTracker::reset();
        ProcessTracker::logTime("setup");

		foreach ($inventoryUpdateArray as $thisProductInfo) {
			$productCode = $thisProductInfo['product_code'];
            if(empty($productCode)) {
                continue;
            }
			$cost = $thisProductInfo['cost'];
			if ($cost === false || strlen($cost) == 0) {
				continue;
			}
			if (!is_numeric($cost)) {
				$GLOBALS['gPrimaryDatabase']->logError("Non numeric cost from {$this->iLocationRow['description']} for product code $productCode: " . jsonEncode($thisProductInfo));
				continue;
			}
			$quantity = $thisProductInfo['quantity'];

			$cost = round($cost, 2);
            $productRow = self::getLoadedRow("distributor_product_codes", $this->iLocationRow['product_distributor_id'], $productCode);
			if (empty($productRow) || empty($productRow['product_id'])) {
				$notFoundCount++;
				continue;
			}
            $productId = $productRow['product_id'];
			if (!empty($thisProductInfo['allocated'])) {
				if (!$allocatedSet) {
					executeQuery("update locations set has_allocated_inventory = 1 where location_id = ?", $this->iLocationRow['location_id']);
					$allocatedSet = true;
					$allocatedProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "ALLOCATED");
				}
				if (!empty($allocatedProductTagId)) {
					$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productId, "product_tag_id = ?", $allocatedProductTagId);
					if (empty($productTagLinkId)) {
						executeQuery("insert into product_tag_links (product_id,product_tag_id,expiration_date) values (?,?,date_add(now(), interval 2 day))", $productId, $allocatedProductTagId);
					} else {
						executeQuery("update product_tag_links set expiration_date = date_add(now(), interval 2 day) where product_tag_link_id = ?", $productTagLinkId);
					}
				}
			}

			$foundProductIds[$productId] = true;

			$quantity = $this->adjustQuantity($productId, $quantity, $cost);
			if (empty($quantity) || !is_numeric($quantity)) {
				$quantity = 0;
			}
			$totalCost = $cost * $quantity;
            ProcessTracker::logTime("calculate");

            if($this->updateProductInventory($productId, $quantity, $totalCost, true)) {
                $processCount++;
            } else {
                $sameCount++;
            }
            ProcessTracker::logTime("update_inventory");
            if($processCount % $this->iBatchSize == 0) {
                $this->commitInventoryBatch();
                ProcessTracker::logTime("save_batch");
            }
		}
        $this->commitInventoryBatch();

		if (!$this->iIgnoreProductsNotFound || self::$iCorewareShootingSports) {
			foreach (self::$iLoadedValues['product_inventories'][$this->iLocationRow['location_id']] as $thisProductId => $thisProductRow) {
				if (array_key_exists($thisProductId, $foundProductIds)) {
					continue;
				}
				if ($thisProductRow['quantity'] > 0) {
					$this->updateProductInventory($thisProductId, 0, "");
				}
			}
		}

        if (self::$iCorewareShootingSports) {
			foreach (self::$iLoadedValues['product_inventories'][$this->iLocationRow['location_id']] as $thisProductId => $thisProductRow) {
				if (array_key_exists($thisProductId, $foundProductIds)) {
					continue;
				}
				$productInventoryLogId = getFieldFromId("product_inventory_log_id", "product_inventory_log", "product_inventory_id", $thisProductRow['product_inventory_id'],
					"log_time > date_sub(now(), interval 180 days)");
				if (empty($productInventoryLogId)) {
					executeQuery("delete from product_inventory_log where product_inventory_id = ?", $thisProductRow['product_inventory_id']);
					executeQuery("delete from product_inventories where product_inventory_id = ?", $thisProductRow['product_inventory_id']);
					unset(self::$iLoadedValues['product_inventories'][$this->iLocationRow['location_id']][$thisProductId]);

					$distributorProductCodeRow = getRowFromId("distributor_product_codes", "product_id", $thisProductId, "product_distributor_id = ?", $this->iLocationRow['product_distributor_id']);
					if (!empty($distributorProductCodeRow)) {
						executeQuery("delete from distributor_product_codes where distributor_product_code_id = ?", $distributorProductCodeRow['distributor_product_code_id']);

						if (($removeKey = array_search($distributorProductCodeRow['product_distributor_id'], self::$iLoadedValues['product_distributor_products'][$thisProductId])) !== false) {
							unset(self::$iLoadedValues['product_distributor_products'][$thisProductId][$removeKey]);
						}
						unset(self::$iLoadedValues['distributor_product_codes'][$distributorProductCodeRow['product_distributor_id']][$distributorProductCodeRow['product_code']]);
						unset(self::$iLoadedValues['distributor_product_codes_by_product_id'][$distributorProductCodeRow['product_distributor_id']][$thisProductId]);
					}
				}
			}
		}
        $this->updateCostForChangedProducts();

        ProcessTracker::logTime("after_update");
        $this->addLog(sprintf("Inventory updates complete taking %s", getTimeElapsed($startTime, getMilliseconds())));
        foreach(ProcessTracker::getResults() as $line) {
            $this->addLog($line, false);
        }

		return array("processed" => $processCount, "not_found" => $notFoundCount, "same" => $sameCount);
	}

	function processInventoryQuantities($inventoryUpdateArray) {
		$processCount = 0;
		$notFoundCount = 0;
		$inventoryUpdateDetailsArray = array();
		foreach ($inventoryUpdateArray as $thisArray) {
			$inventoryUpdateDetailsArray[$thisArray['product_code']] = $thisArray;
		}

		$locationIds = array();
		$cannotSellRestrictions = array();
		$resultSet = executeQuery("select product_manufacturers.client_id,product_manufacturer_cannot_sell_distributors.product_distributor_id from product_manufacturer_cannot_sell_distributors join product_manufacturers using (product_manufacturer_id) union " .
			"select product_departments.client_id,product_department_cannot_sell_distributors.product_distributor_id from product_department_cannot_sell_distributors join product_departments using (product_department_id) union " .
			"select product_categories.client_id,product_category_cannot_sell_distributors.product_distributor_id from product_category_cannot_sell_distributors join product_categories using (product_category_id)");
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists($row['client_id'], $cannotSellRestrictions)) {
				$cannotSellRestrictions[$row['client_id']] = array();
			}
			$cannotSellRestrictions[$row['client_id']][$row['product_distributor_id']] = $row['product_distributor_id'];
		}

		$resultSet = executeQuery("select *,locations.description as location_description from locations join location_credentials using (location_id) join product_distributors using (product_distributor_id) where " .
			"product_distributors.inactive = 0 and location_credentials.inactive = 0 and locations.inactive = 0 and client_id in (select client_id from clients where clients.inactive = 0) and product_distributor_id = ?",
			$this->iLocationRow['product_distributor_id']);
		$locationSkipCount = 0;
		while ($row = getNextRow($resultSet)) {
			if (empty($row['has_allocated_inventory']) && empty($row['cost_threshold']) && empty($row['out_of_stock_threshold']) &&
				(!array_key_exists($row['client_id'], $cannotSellRestrictions) || !array_key_exists($row['product_distributor_id'], $cannotSellRestrictions[$row['client_id']]))) {
				$locationIds[] = $row['location_id'];
			} else {
				$locationSkipCount++;
			}
		}
		if (empty($locationIds)) {
			return array("processed" => $processCount, "not_found" => $notFoundCount, "location_skip" => $locationSkipCount);
		}

		$productInventoryQuantities = array();
		$resultSet = executeQuery("select * from product_inventories join distributor_product_codes using (product_id) where product_distributor_id = ? and location_id in (" . implode(",", $locationIds) . ")", $this->iLocationRow['product_distributor_id']);
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists($row['product_code'], $inventoryUpdateDetailsArray)) {
				continue;
			}
			if ($row['quantity'] == $inventoryUpdateDetailsArray[$row['product_code']]['quantity']) {
				continue;
			}
			if (!array_key_exists($row['product_code'], $productInventoryQuantities)) {
				$productInventoryQuantities[$row['product_code']] = array("product_inventory_ids" => array(), "quantities" => array());
			}

			$productInventoryQuantities[$row['product_code']]['product_inventory_ids'][] = $row['product_inventory_id'];
			if (!in_array($row['quantity'], $productInventoryQuantities[$row['product_code']]['quantities'])) {
				$productInventoryQuantities[$row['product_code']]['quantities'][] = $row['quantity'];
			}
		}

		foreach ($inventoryUpdateDetailsArray as $thisProductInfo) {
			if (!array_key_exists($thisProductInfo['product_code'], $productInventoryQuantities)) {
				continue;
			}
			if (count($productInventoryQuantities[$thisProductInfo['product_code']]['quantities']) == 1 && $productInventoryQuantities[$thisProductInfo['product_code']]['quantities'][0] == $thisProductInfo['quantity']) {
				continue;
			}
			$updateArray = $productInventoryQuantities[$thisProductInfo['product_code']]['product_inventory_ids'];
			while (!empty($updateArray)) {
				if (count($updateArray) <= 200) {
					$thisArray = $updateArray;
					$updateArray = array();
				} else {
					$thisArray = array_slice($updateArray, 0, 200);
					$updateArray = array_diff($updateArray, $thisArray);
				}
				$updateSet = executeQuery("update product_inventories set quantity = ? where product_inventory_id in (" . implode(",", $thisArray) . ")", $thisProductInfo['quantity']);
				$processCount += $updateSet['affected_rows'];
			}
		}

		return array("processed" => $processCount, "not_found" => $notFoundCount, "location_skip" => $locationSkipCount);
	}

	function updateProductInventory($productId, $quantity, $totalCost, $batchMode = false) {
		if (empty($quantity) || $quantity < 0) {
			$quantity = 0;
		}
		$inventoryAdjustmentTypeId = $this->getInventoryAdjustmentType();

		if (!empty($this->iLocationRow['percentage']) && $this->iLocationRow['percentage'] > 0 && !empty($totalCost)) {
			$totalCost = $totalCost + (($this->iLocationRow['percentage'] / 100) * $totalCost);
		}

		if (!empty($this->iLocationRow['amount']) && $this->iLocationRow['amount'] > 0 && !empty($totalCost)) {
			$totalCost = $totalCost + ($this->iLocationRow['amount'] * $quantity);
		}

		self::loadValues("product_distributor_locations");
		self::loadValues("product_inventories");
		$inventoryLocationId = self::$iLoadedValues['product_distributor_locations'][$this->iLocationRow['product_distributor_id']];
		if (!array_key_exists($inventoryLocationId, self::$iLoadedValues['product_inventories'])) {
			self::$iLoadedValues['product_inventories'][$inventoryLocationId] = array();
		}
        $productInventoryRow = self::getLoadedRow("product_inventories", $inventoryLocationId, $productId);
		$productInventoryId = $productInventoryRow['product_inventory_id'];
        $productRow = self::getLoadedRow("products", $productId);
        $affectedRows = 0;
		$locationCost = ($quantity <= 0 ? "" : round($totalCost / $quantity,2));
        $baseCost = empty($productRow['base_cost']) ? "" : round($productRow['base_cost']);
        if(!empty($locationCost) && ($locationCost != $baseCost || !empty($GLOBALS['gForceCostUpdate']))) {
            self::$iLoadedValues['cost_updated_products'][$productId] = $productId;
        }
		if (empty($productInventoryId)) {
			$insertSet = executeQuery("insert into product_inventories (product_id,location_id,quantity,location_cost) values (?,?,?,?)", $productId, $inventoryLocationId, $quantity, $locationCost);
			$productInventoryId = $insertSet['insert_id'];
			$affectedRows = 1;
			freeResult($insertSet);
			self::$iLoadedValues['product_inventories'][$inventoryLocationId][$productId] = array("product_inventory_id" => $productInventoryId, "product_id" => $productId, "location_id" => $inventoryLocationId, "quantity" => $quantity,
			"location_cost"=>$locationCost);
		} elseif ($productInventoryRow['quantity'] != $quantity || $productInventoryRow['location_cost'] != $locationCost || $productInventoryRow['log_count'] == 0) {
            if($batchMode) {
                $this->iInventoryBatch[] = ["product_inventory_id" => $productInventoryId,
                    "quantity" => $quantity,
                    "location_cost" => $locationCost];
            } else {
                if (empty($locationCost) && $quantity > 0) {
                    $resultSet = executeQuery("update product_inventories set quantity = ? where product_inventory_id = ?", $quantity, $productInventoryId);
                } else {
                    $resultSet = executeQuery("update product_inventories set quantity = ?, location_cost = ? where product_inventory_id = ?", $quantity, $locationCost, $productInventoryId);
                }
            }
            $affectedRows = 1;
			freeResult($resultSet);
			self::$iLoadedValues['product_inventories'][$inventoryLocationId][$productId]['quantity'] = $quantity;
			self::$iLoadedValues['product_inventories'][$inventoryLocationId][$productId]['location_cost'] = $locationCost;
		}
		if (($locationCost != $productInventoryRow['location_cost'] && !empty($locationCost)) || $quantity != $productInventoryRow['quantity']) {
			$affectedRows = 1;
		}
		$GLOBALS['gChangeLogNotes'] = "Change (#395) by " . $this->iProductDistributorRow['description'];
		$GLOBALS['gMultipleProductsForWaitingQuantities'] = true;
		if ($quantity > 0 && $totalCost > 0 && $affectedRows > 0) {
			$this->checkCostDifference($productId, ($totalCost / $quantity));
		}
		if ($affectedRows > 0) {
            $this->iInventoryLogBatch[] = ['product_inventory_id' => $productInventoryId,
                'inventory_adjustment_type_id' => $inventoryAdjustmentTypeId,
                'user_id' => $GLOBALS['gUserId'],
                'quantity' => $quantity,
                'total_cost' => $totalCost];
            if(!$batchMode) {
                $this->commitInventoryBatch();
            }
		}
		$GLOBALS['gChangeLogNotes'] = "";
        return $affectedRows;
	}

    protected function commitInventoryBatch() {
        # inventory updates
        $valuesQuery = "";
        if(count($this->iInventoryBatch) > 0) {
            foreach ($this->iInventoryBatch as $thisInventory) {
                if(empty(trim($thisInventory['product_inventory_id']))) {
                    continue;
                }
                // if quantity is zero, make sure location_cost is set to null
                // if quantity exists but location_cost doesn't have a value, don't overwrite location_cost
                if($thisInventory['quantity'] <= 0) {
                    $thisInventory['location_cost'] = 'null';
                } elseif (empty($thisInventory['location_cost'])) {
                    $thisInventory['location_cost'] = -1;
                }
                $valuesQuery .= (empty($valuesQuery) ? "" : ",") . sprintf("(%s,%s,%s)",
                        $thisInventory['product_inventory_id'],
                        $thisInventory['quantity'] ?: 0,
                        $thisInventory['location_cost']);
            }
            $insertSet = executeQuery("insert into product_inventory_temp (product_inventory_id,quantity,location_cost) values " . $valuesQuery);
            freeResult($insertSet);
            executeQuery("update product_inventories join product_inventory_temp using (product_inventory_id) set
                                product_inventories.quantity = product_inventory_temp.quantity,
                                product_inventories.location_cost = product_inventory_temp.location_cost
                                where product_inventory_temp.location_cost is null or product_inventory_temp.location_cost <> -1");
            executeQuery("update product_inventories join product_inventory_temp using (product_inventory_id) set
                                product_inventories.quantity = product_inventory_temp.quantity
                                where product_inventory_temp.location_cost = -1");
            executeQuery("delete from product_inventory_temp");
            $this->iInventoryBatch = array();
        }

        # inventory logs
        $valuesQuery = "";
        if(count($this->iInventoryLogBatch) > 0) {
            foreach ($this->iInventoryLogBatch as $thisInventoryLog) {
                if(empty(trim($thisInventory['product_inventory_id'])) || empty(trim($thisInventoryLog['inventory_adjustment_type_id']))) {
                    continue;
                }

                $valuesQuery .= (empty($valuesQuery) ? "" : ",") . sprintf("(%s,%s,now(),%s,%s,%s,'Update product inventory from distributor')",
                        $thisInventoryLog['product_inventory_id'],
                        $thisInventoryLog['inventory_adjustment_type_id'],
                        $thisInventoryLog['user_id'] ?: 'null',
                        $thisInventoryLog['quantity'] ?: 0,
                        $thisInventoryLog['total_cost'] ?: 0);
            }
        $insertSet = executeQuery("insert into product_inventory_log (product_inventory_id,inventory_adjustment_type_id,log_time,user_id,quantity,total_cost,notes) values " .
            $valuesQuery);
        freeResult($insertSet);
            $this->iInventoryLogBatch = array();
        }
    }

    // 2023-09-23 no longer used
	protected function updateProductCost($productId) {
		$ignoreLocalLocations = getPreference("IGNORE_LOCAL_LOCATIONS_FOR_PRODUCT_COST");

		$baseCost = false;
		self::loadValues('inventory_locations');
		self::loadValues('product_inventories');
		self::loadValues("products");
		$inventoryCosts = array();
		foreach (self::$iLoadedValues['product_inventories'] as $locationId => $productInventories) {

			# don't use locations that are made inactive to calculate costs

			if (!array_key_exists($locationId, self::$iLoadedValues['locations']) || !empty(self::$iLoadedValues['locations'][$locationId]['inactive']) ||
				!empty(self::$iLoadedValues['locations'][$locationId]['internal_use_only']) || !empty(self::$iLoadedValues['locations'][$locationId]['ignore_inventory'])) {
				continue;
			}
			if (array_key_exists($productId, $productInventories)) {
				if ($ignoreLocalLocations && empty(self::$iLoadedValues['locations'][$locationId]['product_distributor_id']) && empty(self::$iLoadedValues['locations'][$locationId]['warehouse_location'])) {
					continue;
				}
				$inventoryCosts[] = array("location_id" => $locationId, "quantity" => $productInventories[$productId]['quantity'], "location_cost" => $productInventories[$productId]['location_cost']);
			}
		}
		foreach ($inventoryCosts as $index => $inventoryInfo) {
			$inventoryCosts[$index]['location_description'] = self::$iLoadedValues['locations'][$inventoryInfo['location_id']]['description'];
		}
		$parameters = array("reason" => $this->iProductDistributorRow['description'] . " inventory updates", "inventory_costs" => $inventoryCosts, "original_base_cost" => self::$iLoadedValues['products'][$productId]['base_cost']);
		ProductCatalog::calculateProductCost($productId, $parameters);
	}

    protected function updateCostForChangedProducts() {
        $ignoreLocalLocations = getPreference("IGNORE_LOCAL_LOCATIONS_FOR_PRODUCT_COST");

        self::loadValues('inventory_locations');
        self::loadValues('product_inventories');
        self::loadValues("products");

        $GLOBALS['gMultipleProductsForWaitingQuantities'] = true;
        ProductCatalog::getInventoryAdjustmentTypes();
        $changeLogBatch = array();
        $costBatch = array();
        executeQuery("create temporary table if not exists product_cost_temp (product_id INT, base_cost decimal(12,2), INDEX (product_id))");

        foreach(self::$iLoadedValues['cost_updated_products'] as $productId) {
            $inventoryCosts = array();
            foreach (self::$iLoadedValues['product_inventories'] as $locationId => $productInventories) {

                # don't use locations that are made inactive to calculate costs
                $locationRow = self::getLoadedRow("locations", $locationId);
                if(empty($locationRow) || $locationRow['inactive'] || $locationRow['internal_use_only'] || $locationRow['ignore_inventory']) {
                    continue;
                }

                if (array_key_exists($productId, $productInventories)) {
                    if ($ignoreLocalLocations && empty($locationRow['product_distributor_id']) && empty($locationRow['warehouse_location'])) {
                        continue;
                    }
                    $inventoryCosts[] = array("location_id" => $locationId, "quantity" => $productInventories[$productId]['quantity'], "location_cost" => $productInventories[$productId]['location_cost'],
                        'location_description' => $locationRow['description']);
                }
            }

            $totalWaitingQuantity = ProductCatalog::getWaitingToShipQuantity($productId);
            $changeLogEntry = "Base cost recalculated after " .  $this->iProductDistributorRow['description'] . " inventory updates\n";
            $changeLogEntry .= "Total Waiting Quantity: $totalWaitingQuantity\n";

            $changeLogEntry .= "Inventory found:\n";
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
                } elseif ($inventoryInfo['available'] > 0 && !empty($inventoryInfo['location_cost'])) {
                    $baseCost = $inventoryInfo['location_cost'];
                    break;
                }
            }
            ProcessTracker::logTime("check_costs");
            if (!empty($baseCost) && $baseCost > 0) {
                $originalBaseCost = self::$iLoadedValues['products'][$productId]['base_cost'];
                if (empty($originalBaseCost)) {
                    $originalBaseCost = getFieldFromId("base_cost", "products", "product_id", $productId);
                }
                if ((floatval($originalBaseCost) !== floatval($baseCost))) {
                    $costBatch[$productId] = ["product_id"=>$productId,"base_cost"=>$baseCost];
                    $changeLogBatch[$productId] = ["primary_identifier"=>$productId,
                        "old_value"=>$originalBaseCost,
                        "new_value"=>$baseCost,
                        "notes"=>$changeLogEntry];
                    if(count($costBatch) % $this->iBatchSize == 0) {
                        $this->commitCostBatch($costBatch,$changeLogBatch);
                    }
                    self::$iLoadedValues['products'][$productId]['base_cost'] = $baseCost;
                }
            }
        }
        $this->commitCostBatch($costBatch,$changeLogBatch);
        self::$iLoadedValues['cost_updated_products'] = array();
    }

    protected function commitCostBatch(&$costBatch,&$changeLogBatch) {
        # base cost updates
        $valuesQuery = "";
        if(!empty($costBatch)) {
            foreach ($costBatch as $thisProduct) {
                $valuesQuery .= (empty($valuesQuery) ? "" : ",") . sprintf("(%s,%s)",
                        $thisProduct["product_id"],
                        $thisProduct["base_cost"]);
            }
            $insertSet = executeQuery("insert into product_cost_temp (product_id,base_cost) values " . $valuesQuery);
            freeResult($insertSet);
            executeQuery("update products join product_cost_temp using (product_id) set 
                                products.base_cost = product_cost_temp.base_cost,
                                products.time_changed = now()                                                          
                                where product_cost_temp.base_cost is not null");
            executeQuery("delete from product_cost_temp");
            ProcessTracker::logTime("save_cost_batch");
            foreach ($costBatch as $thisProduct) {
                productCatalog::calculateAllProductSalePrices($thisProduct["product_id"]);
            }
            ProcessTracker::logTime("calculate_sale_prices");
            $costBatch = array();
        }

        # change logs
        $valuesQuery = "";
        if(!empty($changeLogBatch)) {
            foreach ($changeLogBatch as $changeLogEntry) {
                $valuesQuery .= (empty($valuesQuery) ? "" : ",") . sprintf("(%s,%s,'products','base_cost',%s,'%s','%s','%s')",
                        $GLOBALS['gClientId'],
                        $GLOBALS['gUserId'] ?: "null",
                        $changeLogEntry["primary_identifier"],
                        $changeLogEntry["old_value"],
                        $changeLogEntry["new_value"],
                        $changeLogEntry["notes"]);
            }
            $insertSet = executeQuery("insert into change_log (client_id, user_id, table_name, column_name, primary_identifier, old_value, new_value, notes) values " .
                $valuesQuery);
            freeResult($insertSet);
            ProcessTracker::logTime("write_change_logs");
            $changeLogBatch = array();
        }
    }

	function checkCostDifference($productId, $newCost) {
		if (empty($newCost)) {
			return;
		}
		self::loadValues("products");
		$baseCost = self::$iLoadedValues['products'][$productId]['base_cost'];
		if (!empty($baseCost) && $newCost < $baseCost) {
			if ($this->iProductCostDifference === false) {
				$this->iProductCostDifference = getPreference("PRODUCT_COST_DIFFERENCE");
			}
			if (empty($this->iProductCostDifference)) {
				$this->iProductCostDifference = 20;
			}
			$costDeviation = abs($baseCost - $newCost);
			if ($costDeviation > ($baseCost * ($this->iProductCostDifference / 100)) && $costDeviation > 20) {
				if (empty($GLOBALS['gCostDifferenceEmailSent'])) {
					$body = "<p>Product cost difference log entries have been created. You can see these logs at Tools->Logs->Cost difference.</p>";
					sendEmail(array("send_after" => date("Y-m-d H:i:s", strtotime("+5 minutes")), "subject" => "Big difference in product cost", "body" => $body, "notification_code" => "PRODUCT_COST_DIFFERENCE"));
					$GLOBALS['gCostDifferenceEmailSent'] = true;
				}
				executeQuery("insert into cost_difference_log (product_id,product_distributor_id,original_base_cost,base_cost) values (?,?,?,?)",
					$productId, $this->iProductDistributorRow['product_distributor_id'], $baseCost, $newCost);
			}
		}
		$productTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "PRICE_DROP");
		if (!empty($productTagId) && !empty($baseCost)) {
			$priceDropPercentage = getPreference("PRICE_DROP_TAG_PERCENTAGE") ?: 10;
			if ($newCost < $baseCost && $baseCost - $newCost > $baseCost * ($priceDropPercentage / 100)) {
				executeQuery("insert ignore into product_tag_links (product_id, product_tag_id, expiration_date) values (?,?,?)",
					$productId, $productTagId, date("Y-m-d", strtotime("+1 week")));
			}
			executeQuery("delete ignore from product_tag_links where product_id = ? and product_tag_id = ? and expiration_date < current_date", $productId, $productTagId);
		}
	}

	abstract function testCredentials();

	abstract function syncProducts($parameters = array());

	abstract function syncInventory($parameters = array());

	abstract function getManufacturers($parameters = array());

	abstract function getCategories($parameters = array());

	abstract function getFacets($parameters = array());

# get the quantity and cost currently available from this distributor

	function getProductInventoryQuantity($productId) {
		return false;
	}

	public static function getInventoryLocation($locationId) {
        return self::getLoadedRow("inventory_locations", $locationId);
	}

	function splitOrder($orderId, $orderItems) {
		$customerOrderItemRows = array();
		$fflOrderItemRows = array();
		$class3OrderItemRows = array();
		$dealerOrderItemRows = array();
		self::loadValues("inventory_locations");

		$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED");
		if (empty($fflRequiredProductTagId)) {
			$insertSet = executeQuery("insert into product_tags (client_id,product_tag_code,description,internal_use_only) values (?,'FFL_REQUIRED','FFL Required',1)", $GLOBALS['gClientId']);
			$fflRequiredProductTagId = $insertSet['insert_id'];
			freeResult($insertSet);
		}
		$class3ProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "CLASS_3");
		if (empty($class3ProductTagId)) {
			$insertSet = executeQuery("insert into product_tags (client_id,product_tag_code,description,internal_use_only) values (?,'CLASS_3','Class 3 Products',1)", $GLOBALS['gClientId']);
			$class3ProductTagId = $insertSet['insert_id'];
			freeResult($insertSet);
		}

# determine which items go into which orders

		$orderRow = getRowFromId("orders", "order_id", $orderId);
		$pickup = getFieldFromId("pickup", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);

		foreach ($orderItems as $thisOrderItemInfo) {
			if (!is_array($thisOrderItemInfo)) {
				$thisOrderItemInfo = array("order_item_id" => $thisOrderItemInfo);
			}
			$thisOrderItemId = $thisOrderItemInfo['order_item_id'];
			$thisOrderItemRow = getRowFromId("order_items", "order_item_id", $thisOrderItemId);
			if (array_key_exists("quantity", $thisOrderItemInfo)) {
				$thisOrderItemRow['quantity'] = $thisOrderItemInfo['quantity'];
			}
			if (empty($thisOrderItemRow) || empty($thisOrderItemInfo['quantity'])) {
				continue;
			}
			$thisOrderItemRow['product_row'] = array_merge(ProductCatalog::getCachedProductRow($thisOrderItemRow['product_id']), getRowFromId("product_data", "product_id", $thisOrderItemRow['product_id']));
			$distributorProductCode = getFieldFromId("product_code", "distributor_product_codes", "product_distributor_id",
				$this->iLocationRow['product_distributor_id'], "product_id = ?", $thisOrderItemRow['product_id']);
			if (empty($distributorProductCode) && method_exists($this, "getCustomDistributorProductCode")) {
				$distributorProductCode = $this->getCustomDistributorProductCode($thisOrderItemRow['product_id']);
			}
			if (empty($distributorProductCode)) {
				$this->iErrorMessage = "Distributor Product code for '" . $thisOrderItemRow['product_row']['description'] . "' is missing";
				return false;
			}
			$inventoryQuantity = getFieldFromId("quantity", "product_inventories", "product_id", $thisOrderItemRow['product_id'], "location_id = ?", ProductDistributor::getInventoryLocation($this->iLocationId));
			if (empty($inventoryQuantity)) {
				$this->iErrorMessage = "Product '" . $thisOrderItemRow['product_row']['description'] . "' has zero inventory from this distributor.";
				return false;
			}
			$thisOrderItemRow['distributor_product_code'] = $distributorProductCode;
			$isClass3Product = ($this->iClass3Products ? getFieldFromId("product_tag_link_id", "product_tag_links", "product_tag_id", $class3ProductTagId, "product_id = ?", $thisOrderItemRow['product_id']) : false);
			$isFFLProduct = getFieldFromId("product_tag_link_id", "product_tag_links", "product_tag_id", $fflRequiredProductTagId, "product_id = ?", $thisOrderItemRow['product_id']);
			if (!$isFFLProduct) {
				$state = getFieldFromId("state", "addresses", "address_id", $orderRow['address_id']);
				if (empty($state)) {
					$state = getFieldFromId("state", "contacts", "contact_id", $orderRow['contact_id']);
				}
				if (!empty($state)) {
					$fflRequiredStateProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED_" . $state, "inactive = 0 and cannot_sell = 0");
					if (!empty($fflRequiredStateProductTagId)) {
						$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $thisOrderItemRow['product_id'], "product_tag_id = ?", $fflRequiredStateProductTagId);
						if (!empty($productTagLinkId)) {
							$isFFLProduct = true;
						}
					}
				}
			}
			$cannotDropship = $thisOrderItemRow['product_row']['cannot_dropship'];
			if (!$cannotDropship) {
				$cannotDropship = getFieldFromId("cannot_dropship", "product_manufacturers", "product_manufacturer_id", $thisOrderItemRow['product_row']['product_manufacturer_id']);
			}
			if (!$cannotDropship) {
				$cannotDropship = getFieldFromId("product_manufacturer_dropship_exclusion_id", "product_manufacturer_dropship_exclusions", "product_manufacturer_id",
					$thisOrderItemRow['product_row']['product_manufacturer_id'], "product_department_id in (select product_department_id from product_category_departments where " .
					"product_category_id in (select product_category_id from product_category_links where product_id = ?))", $thisOrderItemRow['product_row']['product_id']);
			}
			if ($cannotDropship) {
				$productManufacturerDistributorDropshipId = getFieldFromId("product_manufacturer_distributor_dropship_id", "product_manufacturer_distributor_dropships", "product_manufacturer_id",
					$thisOrderItemRow['product_row']['product_manufacturer_id'], "product_distributor_id = ?", $this->iLocationRow['product_distributor_id']);
				if (!empty($productManufacturerDistributorDropshipId)) {
					$cannotDropship = false;
				}
			}
			if (!$cannotDropship) {
				if (!empty($this->iLocationRow['cannot_ship'])) {
					$cannotDropship = true;
				}
			}
			if (!$cannotDropship) {
				$productDistributorDropshipProhibitionId = getFieldFromId("product_distributor_dropship_prohibition_id", "product_distributor_dropship_prohibitions", "product_id", $thisOrderItemRow['product_id'],
					"product_distributor_id = ?", $this->iLocationRow['product_distributor_id']);
				if (!empty($productDistributorDropshipProhibitionId)) {
					$cannotDropship = true;
				}
			}

			if ($pickup) {
				$thisOrderItemInfo['ship_to'] = "dealer";
			}
			if ($thisOrderItemInfo['ship_to'] == "customer" && $isFFLProduct) {
				$thisOrderItemInfo['ship_to'] = "";
			}
			$thisOrderItemRow['cannot_dropship'] = $cannotDropship;
			if ($cannotDropship || $thisOrderItemInfo['ship_to'] == "dealer") {
				$dealerOrderItemRows[] = $thisOrderItemRow;
			} elseif ($this->iClass3Products && ($isClass3Product || $thisOrderItemInfo['ship_to'] == "class3")) {
				$class3OrderItemRows[] = $thisOrderItemRow;
			} elseif ($isFFLProduct || $thisOrderItemInfo['ship_to'] == "ffl") {
				$fflOrderItemRows[] = $thisOrderItemRow;
			} else {
				$customerOrderItemRows[] = $thisOrderItemRow;
			}
		}
		$orderItemsArray = array("ffl_order_items" => $fflOrderItemRows, "customer_order_items" => $customerOrderItemRows, "dealer_order_items" => $dealerOrderItemRows);
		if ($this->iClass3Products) {
			$orderItemsArray['class_3_order_items'] = $class3OrderItemRows;
		}
		return $orderItemsArray;
	}

	abstract function placeOrder($orderId, $orderItems);

	abstract function placeDistributorOrder($productArray, $parameters);

	abstract function getOrderTrackingData($orderShipmentId);

	protected function getProductRow($productId) {
        return self::getLoadedRow("products", $productId);
	}

# Product methods

	protected function getProductFromUpc($upcCode) {
        $productRow = self::getLoadedRow("product_id_from_upc", $upcCode);
        if(empty($productRow)) {
            $productRow = self::getLoadedRow("product_id_from_upc", ltrim($upcCode, "0"));
        }
		return $productRow ?: false;
	}

	protected function getUpcFromProduct($productId) {
		self::loadValues("product_id_from_upc");
		if (array_key_exists($productId, self::$iLoadedValues['product_id_from_upc'])) {
			return self::$iLoadedValues['product_id_from_upc'][$productId];
		}
		return false;
	}

	protected function syncStates($productId, $newStates) {
		if (!empty(getPreference("DONT_SYNC_STATE_RESTRICTIONS"))) {
			return;
		}
		self::loadValues("no_update_products");
		if (!empty(getPreference("DONT_SYNC_STATE_RESTRICTIONS_FOR_NO_UPDATE_PRODUCTS")) && array_key_exists($productId, self::$iLoadedValues['no_update_products'])) {
			return;
		}
		self::loadValues("existing_states");
		self::loadValues("state_compliance_tags");
		$allStates = getStateArray();
		if (array_key_exists($productId, self::$iLoadedValues['existing_states'])) {
			$existingStates = self::$iLoadedValues['existing_states'][$productId];
		} else {
			$existingStates = array();
		}
		if (!is_array($newStates)) {
			$newStates = array_filter(explode(",", $newStates));
		}
		$changed = false;
		foreach ($allStates as $thisState => $stateName) {
			$hasComplianceTag = array_key_exists($thisState, self::$iLoadedValues['state_compliance_tags']);
			$newExisting = (in_array($thisState, $newStates) ? "1" : "0") . ":" . (in_array($thisState, $existingStates) ? "1" : "0");

			# if restricted, remove the product tag
			# if restriction doesn't exist, add it

			switch ($newExisting) {
				case "1:0":
					executeQuery("insert into product_restrictions (product_id,state,country_id) values (?,?,1000)", $productId, $thisState);
					if ($hasComplianceTag) {
						$this->removeProductTag($productId, self::$iLoadedValues['state_compliance_tags'][$thisState]);
					}
					$changed = true;
					break;
				case "1:1":
					if ($hasComplianceTag) {
						$this->removeProductTag($productId, self::$iLoadedValues['state_compliance_tags'][$thisState]);
					}
					break;
			}
		}
		if ($changed) {
			if (self::$iCorewareShootingSports) {
				$changeLogNotes = "Change (#643) by " . $this->iProductDistributorRow['description'];
			} else {
				$changeLogNotes = "Change (#642) from catalog during sync with " . $this->iProductDistributorRow['description'];
			}
			executeQuery("insert into change_log (client_id,table_name,foreign_key_identifier,column_name,old_value,new_value,notes) values (?,?,?,?,?,?,?)",
				$GLOBALS['gClientId'], 'product_restrictions', $productId, 'state', implode(",", $existingStates), implode(",", $newStates),
				$changeLogNotes);
		}
	}

	protected function addCorewareProductCategories($productId, $corewareProductData) {
		if ($this->iProductUpdateFields['product_categories']['update_setting'] == "N") {
			return false;
		}
		self::loadValues("no_update_products");
		if (array_key_exists($productId, self::$iLoadedValues['no_update_products'])) {
			return false;
		}
		$productCategoryAdded = false;
		if (!$this->hasProductCategories($productId) || $this->iProductUpdateFields['product_categories']['update_setting'] == "Y") {
			self::loadValues("product_categories");
			$productCategoryCodes = explode(",", $corewareProductData['product_category_codes']);
			$dataTable = new DataTable("product_category_links");
			$dataTable->setSaveOnlyPresent(true);
			$GLOBALS['gChangeLogNotes'] = "Change (#592) from catalog during sync with " . $this->iProductDistributorRow['description'];

			foreach ($productCategoryCodes as $thisCode) {
				$productCategoryId = self::$iLoadedValues['product_categories'][$thisCode];
				if (!empty($productCategoryId)) {
					if ($dataTable->saveRecord(array("name_values" => array("product_id" => $productId, "product_category_id" => $productCategoryId), "primary_id" => ""))) {
						$productCategoryAdded = true;
					}
				}
			}
			$GLOBALS['gChangeLogNotes'] = "";
		}
		return $productCategoryAdded;
	}

	protected function addCorewareProductFacets($productId, $corewareProductData) {
		if ($this->iProductUpdateFields['product_facets']['update_setting'] == "N") {
			return;
		}
		self::loadValues("no_update_products");
		if (array_key_exists($productId, self::$iLoadedValues['no_update_products'])) {
			return;
		}
		self::loadValues("product_facet_values");
		if (array_key_exists($productId, self::$iLoadedValues['product_facet_values']) && !empty(self::$iLoadedValues['product_facet_values'][$productId]) && $this->iProductUpdateFields['product_facets']['update_setting'] == "M") {
			return;
		}
		if (!array_key_exists($productId, self::$iLoadedValues['product_facet_values'])) {
			self::$iLoadedValues['product_facet_values'][$productId] = array();
		}
		$facetOptionsTable = new DataTable("product_facet_options");
		$facetOptionsTable->setSaveOnlyPresent(true);
		$facetValuesTable = new DataTable("product_facet_values");
		$facetValuesTable->setSaveOnlyPresent(true);
		$GLOBALS['gChangeLogNotes'] = "Change (#438) by " . $this->iProductDistributorRow['description'];
		self::loadValues("product_facets");

		$productFacetValues = explode("||||", $corewareProductData['product_facets']);
		foreach ($productFacetValues as $thisFacetValue) {
			$parts = explode("||", $thisFacetValue);
			$productFacetCode = $parts[0];
			$facetValue = $parts[1];
			$productFacetId = self::$iLoadedValues['product_facets'][$productFacetCode];
			if (empty($productFacetId) || empty($facetValue)) {
				continue;
			}
			$productFacetOptionId = $this->getProductFacetOption($facetValue, $productFacetId);
			if (empty($productFacetOptionId)) {
				$productFacetOptionId = $facetOptionsTable->saveRecord(array("name_values" => array("product_facet_id" => $productFacetId, "facet_value" => $facetValue), "primary_id" => ""));
				if (!empty($productFacetOptionId)) {
					self::$iLoadedValues['product_facet_options'][$productFacetId][trim(strtolower($facetValue))] = $productFacetOptionId;
				}
			}
			if (!array_key_exists($productFacetId, self::$iLoadedValues['product_facet_values'][$productId])) {
				$productFacetValueId = $facetValuesTable->saveRecord(array("name_values" => array("product_id" => $productId, "product_facet_id" => $productFacetId, "product_facet_option_id" => $productFacetOptionId), "primary_id" => ""));
				self::$iLoadedValues['product_facet_values'][$productId][$productFacetId] = array("product_facet_value_id" => $productFacetValueId, "product_facet_option_id" => $productFacetOptionId);
			} elseif ($productFacetOptionId != self::$iLoadedValues['product_facet_values'][$productId][$productFacetId]) {
				$productFacetValueId = self::$iLoadedValues['product_facet_values'][$productId][$productFacetId]['product_facet_value_id'];
				$facetValuesTable->saveRecord(array("name_values" => array("product_facet_option_id" => $productFacetOptionId), "primary_id" => $productFacetValueId));
				self::$iLoadedValues['product_facet_values'][$productId][$productFacetId] = array("product_facet_value_id" => $productFacetValueId, "product_facet_option_id" => $productFacetOptionId);
			}
		}
		$GLOBALS['gChangeLogNotes'] = "";
	}

	protected function cleanUpDistributorCodes($productId, $productCode) {
		if (array_key_exists($productCode, $this->iUsedDistributorProductCodes) && $this->iUsedDistributorProductCodes[$productCode] != $productId) {
			$GLOBALS['gPrimaryDatabase']->logError("Product code used for multiple product IDs: " . $this->iLocationRow['description'] . ":" . $productId . ":" . $productCode . ":" . jsonEncode($this->iUsedDistributorProductCodes[$productCode]));
			return;
		}
		if (empty($productId) || empty($productCode)) {
			return;
		}
		$this->iUsedDistributorProductCodes[$productCode] = $productId;
		self::loadValues("distributor_product_codes");
		self::loadValues("product_id_from_upc");

		$distributorProductCodesByIdRow = self::$iLoadedValues['distributor_product_codes_by_product_id'][$this->iLocationRow['product_distributor_id']][$productId];
		$distributorProductCodesByCodeRow = self::$iLoadedValues['distributor_product_codes'][$this->iLocationRow['product_distributor_id']][$productCode];
		if (!empty($distributorProductCodesByIdRow) && $distributorProductCodesByIdRow['product_code'] == $productCode && $distributorProductCodesByIdRow['product_id'] == $productId) {
			return;
		}
		if (!empty($distributorProductCodesByCodeRow) && $distributorProductCodesByCodeRow['product_code'] == $productCode && $distributorProductCodesByCodeRow['product_id'] == $productId) {
			return;
		}
		$this->addLogEntry("Change to distributor product code: old (id & code): " . jsonEncode($distributorProductCodesByIdRow) . " and " . jsonEncode($distributorProductCodesByCodeRow) . ", new: " . $productId . " - " . $productCode, true);

		$finalDistributorProductCodeId = false;
		if (!empty($distributorProductCodesByIdRow)) {
			if (!empty($distributorProductCodesByCodeRow)) {
				executeQuery("delete from distributor_product_codes where client_id = ? and distributor_product_code_id = ?",
					$GLOBALS['gClientId'], $distributorProductCodesByCodeRow['distributor_product_code_id']);
			}
			executeQuery("update distributor_product_codes set product_code = ? where distributor_product_code_id = ?", $productCode, $distributorProductCodesByIdRow['distributor_product_code_id']);
			$finalDistributorProductCodeId = $distributorProductCodesByIdRow['distributor_product_code_id'];
		} elseif (!empty($distributorProductCodesByCodeRow)) {
			if (!empty($distributorProductCodesByIdRow)) {
				executeQuery("delete from distributor_product_codes where client_id = ? and distributor_product_code_id = ?",
					$GLOBALS['gClientId'], $distributorProductCodesByIdRow['distributor_product_code_id']);
			}
			executeQuery("update distributor_product_codes set product_id = ? where distributor_product_code_id = ?", $productId, $distributorProductCodesByCodeRow['distributor_product_code_id']);
			$finalDistributorProductCodeId = $distributorProductCodesByIdRow['distributor_product_code_id'];
		} else {
			$insertSet = executeQuery("insert into distributor_product_codes (client_id,product_distributor_id,product_id,product_code) values (?,?,?,?)",
				$GLOBALS['gClientId'], $this->iLocationRow['product_distributor_id'], $productId, $productCode);
			$finalDistributorProductCodeId = $insertSet['insert_id'];
		}
		if (empty($finalDistributorProductCodeId)) {
			return;
		}
		self::$iLoadedValues['distributor_product_codes'][$this->iLocationRow['product_distributor_id']][$productCode] = array("product_id" => $productId, "product_code" => $productCode, "distributor_product_code_id" => $finalDistributorProductCodeId);
		self::$iLoadedValues['distributor_product_codes_by_product_id'][$this->iLocationRow['product_distributor_id']][$productId] = array("product_id" => $productId, "product_code" => $productCode, "distributor_product_code_id" => $finalDistributorProductCodeId);
	}

	protected function processRemoteImages($productRow, $corewareProductData) {
		if (!empty($productRow['image_id'])) {
			return;
		}
		if (!empty(getPreference("NEVER_USE_REMOTE_IMAGES"))) {
			return;
		}
		$imageIdList = $corewareProductData['image_id'];
		if (!empty($corewareProductData['alternate_images'])) {
			$imageIdList .= (empty($imageIdList) ? "" : ",") . $corewareProductData['alternate_images'];
		}

		$imageIds = (empty($imageIdList) ? array() : explode(",", $imageIdList));
		$existingImageIds = (empty($productRow['product_remote_images']) ? array() : explode(",", $productRow['product_remote_images']));

		$processCount = 0;
		foreach ($imageIds as $imageId) {
			$processCount++;
			if (empty($imageId) || in_array($imageId, $existingImageIds)) {
				continue;
			}
			$resultSet = executeQuery("insert ignore into product_remote_images (product_id,image_identifier,primary_image) values (?,?,?)", $productRow['product_id'], $imageId, ($processCount == 1 ? 1 : 0));
			freeResult($resultSet);
			$existingImageIds[] = $imageId;
		}

		$unusedImagesIds = array_diff($existingImageIds, $imageIds);
		if (!empty($unusedImagesIds)) {
			$resultSet = executeQuery("delete from product_remote_images where product_id = ? and image_identifier in (" . implode(",", $unusedImagesIds) . ")", $productRow['product_id']);
		}
	}

	protected function addLogEntry($content) {
		if (!$this->iLoggingOn) {
			return;
		}
		if (empty($this->iLogContent)) {
			$this->iLogContent[] = "Logging for distributor: " . $this->iProductDistributorRow['description'];
		}
		$this->iLogContent[] = $content;
	}

	protected function getProductSales() {
		$productArray = array();
		$resultSet = executeQuery("select product_id from order_items where order_id in (select order_id from orders where client_id = ? and order_time > (now() - INTERVAL 1 DAY))", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$productArray[$row['product_id']] = $row['product_id'];
		}
		freeResult($resultSet);
		return $productArray;
	}
}
