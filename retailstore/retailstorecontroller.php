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

$GLOBALS['gPageCode'] = "RETAILSTORECONTROLLER";
$GLOBALS['gCacheProhibited'] = true;
require_once "shared/startup.inc";

class RetailStoreControllerPage extends Page {

	function shoppingCartItemDescriptionSort($a, $b) {
		if ($a['description'] == $b['description']) {
			return 0;
		}
		return ($a['description'] > $b['description']) ? 1 : -1;
	}

	function shoppingCartItemIdSort($a, $b) {
		if ($a['shopping_cart_item_id'] == $b['shopping_cart_item_id']) {
			return 0;
		}
		return ($a['shopping_cart_item_id'] > $b['shopping_cart_item_id']) ? 1 : -1;
	}

	function paymentMethodSort($a, $b) {
		if ($a['primary_payment_method'] == $b['primary_payment_method']) {
			return 0;
		}
		return ($a['primary_payment_method'] > $b['primary_payment_method']) ? 1 : -1;
	}

	function distanceSort($a, $b) {
		if ($a['default_location'] == $b['default_location']) {
			if ($a['distance'] == $b['distance']) {
				return 0;
			}
			return ($a['distance'] > $b['distance']) ? 1 : -1;
		}
		return ($b['default_location'] ? 1 : -1);
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_events":
				$eventGroupId = getFieldFromId("event_group_id", "event_groups", "event_group_code", $_GET['event_group_code']);
				if (empty($eventGroupId)) {
					$returnArray['error_message'] = "Invalid Event Group";
					ajaxResponse($returnArray);
				}
				$events = array();
				$resultSet = executeQuery("select * from events where client_id = ? and inactive = 0 " . ($GLOBALS['gInternalConnection'] ? "" : "and internal_use_only = 0 ") .
					"and event_id in (select event_id from event_group_links where event_group_id = ?) and start_date >= current_date", $GLOBALS['gClientId'], $eventGroupId);
				while ($row = getNextRow($resultSet)) {
					$events[] = $row;
				}
				$returnArray['events'] = $events;
				ajaxResponse($returnArray);
				break;
			case "get_detailed_events":
				$parameters = $_GET;
				$parameters['limit'] = empty($_GET['limit']) ? 10 : $_GET['limit'];
				$returnArray = Events::getDistinctEvents($parameters);

				$eventTypeResultSet = executeQuery("select * from event_types where client_id = ? and hide_in_calendar = 0 and inactive = 0"
					. ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $GLOBALS['gClientId']);

				while ($eventTypeRow = getNextRow($eventTypeResultSet)) {
					$eventTypeTags = array();
					$tagResultSet = executeQuery("select event_type_tag_group_code, event_type_tags.* from event_type_tags join event_type_tag_groups using (event_type_tag_group_id)"
						. " where event_type_tags.client_id = ? and event_type_tags.inactive = 0 and event_type_tag_groups.inactive = 0"
						. " and event_type_tag_id in (select event_type_tag_id from event_type_tag_links where event_type_id = ?)"
						. ($GLOBALS['gInternalConnection'] ? "" : " and event_type_tags.internal_use_only = 0 and event_type_tag_groups.internal_use_only = 0")
						. " order by event_type_tag_groups.sort_order, event_type_tag_groups.description, event_type_tags.sort_order, event_type_tags.description",
						$GLOBALS['gClientId'], $eventTypeRow['event_type_id']);
					while ($tagRow = getNextRow($tagResultSet)) {
						$tagGroupCode = strtolower($tagRow['event_type_tag_group_code']);
						$eventTypeTags[$tagGroupCode][] = htmlText($tagRow['description']);
					}
					$returnArray["event_types"][$eventTypeRow['event_type_id']] = array("event_type_id" => $eventTypeRow['event_type_id'], "event_type_code" => $eventTypeRow['event_type_code'],
						"description" => $eventTypeRow['description'], "detailed_description" => htmlText($eventTypeRow['detailed_description']), "excerpt" => htmlText($eventTypeRow['excerpt']),
						"link_name" => $eventTypeRow['link_name'], "price" => $eventTypeRow['price'], "tags" => $eventTypeTags,
						"image_filename" => getImageFilename($eventTypeRow['image_id'], array("use_cdn" => true)));
				}

				if ($_GET['include_locations'] && !empty($returnArray['events'])) {
					foreach ($returnArray['events'] as $event) {
						$locationId = $event['location_id'];
						if (!empty($locationId) && empty($returnArray['locations'][$locationId])) {
							$locationRow = getRowFromId("locations", "location_id", $locationId);
							$contactRow = Contact::getContact($locationRow['contact_id']);

							$locationFullAddress = empty($contactRow['address_1']) ? "" : $contactRow['address_1'];
							if (!empty($contactRow['city'])) {
								$locationFullAddress .= (empty($locationFullAddress) ? "" : ", ") . $contactRow['city'];
							}
							if (!empty($contactRow['state'])) {
								$locationFullAddress .= (empty($locationFullAddress) ? "" : ", ") . $contactRow['state'];
							}
							if (!empty($contactRow['postal_code'])) {
								$locationFullAddress .= (empty($locationFullAddress) ? "" : ", ") . $contactRow['postal_code'];
							}
							$returnArray['locations'][$locationId] = array(
								"location_id" => $locationRow['location_id'],
								"location_code" => $locationRow['location_code'],
								"description" => htmlText($locationRow['description']),
								"longitude" => $contactRow['longitude'],
								"latitude" => $contactRow['latitude'],
								"address_1" => htmlText($contactRow['address_1']),
								"city" => $contactRow['city'],
								"state" => $contactRow['state'],
								"postal_code" => $contactRow['postal_code'],
								"full_address" => htmlText($locationFullAddress)
							);
						}
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_event_types":
				$eventTypeTagGroupIds = array();
				$eventTypeCustomFields = array();

				if (!empty($_GET['event_type_tags'])) {
					foreach (explode(",", $_GET['event_type_tags']) as $eventTypeTag) {
						$eventTypeTagRow = getRowFromId("event_type_tags", "event_type_tag_id", $eventTypeTag,
							"inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
						if (empty($eventTypeTagRow)) {
							$eventTypeTagRow = getRowFromId("event_type_tags", "event_type_tag_code", $eventTypeTag,
								"inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
						}
						if (!empty($eventTypeTagRow) && !empty($eventTypeTagRow['event_type_tag_group_id'])) {
							$eventTypeTagGroupIds[$eventTypeTagRow['event_type_tag_group_id']][] = $eventTypeTagRow['event_type_tag_id'];
						}
					}
				}
				if (!empty($_GET['event_type_custom_fields'])) {
					$eventTypeCustomFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "EVENT_TYPES");
					foreach (explode(":", $_GET['event_type_custom_fields']) as $eventTypeCustomField) {
						$eventTypeCustomFieldParts = explode("=", $eventTypeCustomField);
						$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", $eventTypeCustomFieldParts[0],
							"custom_field_type_id = ? and client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"),
							$eventTypeCustomFieldTypeId, $GLOBALS['gClientId']);
						$customFieldValues = explode(",", $eventTypeCustomFieldParts[1]);

						if (!empty($customFieldId) && !empty($customFieldValues)) {
							$eventTypeCustomFields[$customFieldId] = array();
							foreach ($customFieldValues as $customFieldValue) {
								$eventTypeCustomFields[$customFieldId][] = makeParameter($customFieldValue);
							}
						}
					}
				}

				$eventTypesQuery = "select event_type_id, event_type_code, description, detailed_description, excerpt, link_name, price, image_id from event_types"
					. " where client_id = ? and hide_in_calendar = 0 and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0");

				if (!empty($eventTypeTagGroupIds)) {
					foreach ($eventTypeTagGroupIds as $eventTypeTagIds) {
						$eventTypesQuery .= " and event_type_id in (select event_type_id from event_type_tag_links where event_type_tag_id in (" . implode(",", $eventTypeTagIds) . "))";
					}
				}
				if (!empty($eventTypeCustomFields)) {
					foreach ($eventTypeCustomFields as $customFieldId => $customFieldValues) {
						$eventTypesQuery .= " and event_type_id in (select primary_identifier from custom_field_data where custom_field_id = " . $customFieldId
							. " and text_data in (" . implode(",", $customFieldValues) . "))";
					}
				}

				$eventTypesQuery .= " order by sort_order, description";
				$resultSet = executeQuery($eventTypesQuery, $GLOBALS['gClientId']);

				while ($eventTypeRow = getNextRow($resultSet)) {
					$eventTypeTags = array();
					$tagResultSet = executeQuery("select event_type_tag_group_code, event_type_tags.* from event_type_tags join event_type_tag_groups using (event_type_tag_group_id)"
						. " where event_type_tags.client_id = ? and event_type_tags.inactive = 0 and event_type_tag_groups.inactive = 0"
						. " and event_type_tag_id in (select event_type_tag_id from event_type_tag_links where event_type_id = ?)"
						. ($GLOBALS['gInternalConnection'] ? "" : " and event_type_tags.internal_use_only = 0 and event_type_tag_groups.internal_use_only = 0")
						. " order by event_type_tag_groups.sort_order, event_type_tag_groups.description, event_type_tags.sort_order, event_type_tags.description",
						$GLOBALS['gClientId'], $eventTypeRow['event_type_id']);
					while ($tagRow = getNextRow($tagResultSet)) {
						$tagGroupCode = strtolower($tagRow['event_type_tag_group_code']);
						$eventTypeTags[$tagGroupCode][] = htmlText($tagRow['description']);
					}
					$eventTypeRow['image_filename'] = getImageFilename($eventTypeRow['image_id'], array("use_cdn" => true));
					$eventTypeRow['detailed_description'] = htmlText($eventTypeRow['description']);
					$eventTypeRow['excerpt'] = htmlText($eventTypeRow['excerpt']);
					$eventTypeRow['tags'] = $eventTypeTags;
					$returnArray["event_types"][] = $eventTypeRow;
				}
				ajaxResponse($returnArray);
				break;
			case "get_event_type_details":
				$eventTypeRow = getRowFromId("event_types", "event_type_code", $_POST['event_type_code']);
				if (empty($eventTypeRow)) {
					$eventTypeRow = getRowFromId("event_types", "event_type_id", $_POST['event_type_id']);
				}
				if (empty($eventTypeRow)) {
					$eventTypeRow = getRowFromId("event_types", "link_name", $_POST['event_type_link_name']);
				}
				if (empty($eventTypeRow)) {
					$returnArray['error_message'] = "Invalid event type";
					ajaxResponse($returnArray);
				}
				$eventTypeRow['image_filename'] = getImageFilename($eventTypeRow['image_id'], array("use_cdn" => true));
				$eventTypeRow['description'] = htmlText($eventTypeRow['description']);
				$eventTypeRow['detailed_description'] = htmlText($eventTypeRow['detailed_description']);
				$eventTypeRow['excerpt'] = htmlText($eventTypeRow['excerpt']);

				if (!empty($_POST['include_tags'])) {
					$eventTypeRow['tags'] = array();
					$eventTypeTagsResultSet = executeQuery("select event_type_tag_group_code, event_type_tag_groups.description event_type_tag_group_description, event_type_tags.*"
						. " from event_type_tags join event_type_tag_groups using (event_type_tag_group_id)"
						. " where event_type_tags.client_id = ? and event_type_tags.inactive = 0 and event_type_tag_groups.inactive = 0"
						. " and event_type_tag_id in (select event_type_tag_id from event_type_tag_links where event_type_id = ?)"
						. ($GLOBALS['gInternalConnection'] ? "" : " and event_type_tags.internal_use_only = 0 and event_type_tag_groups.internal_use_only = 0")
						. " order by event_type_tag_groups.sort_order, event_type_tag_groups.description, event_type_tags.sort_order, event_type_tags.description",
						$GLOBALS['gClientId'], $eventTypeRow['event_type_id']);
					while ($eventTypeTagRow = getNextRow($eventTypeTagsResultSet)) {
						$eventTypeRow['tags'][] = $eventTypeTagRow;
					}
				}

				if (!empty($_POST['include_events'])) {
					$eventsArray = Events::getDistinctEvents(array(
						"event_type_ids" => $eventTypeRow['event_type_id'],
						"start_date" => $_POST['events_start_date'],
						"end_date" => $_POST['events_end_date'],
						"limit" => empty($_POST['events_count_limit']) ? 10 : $_POST['events_count_limit']
					));
					$returnArray['events'] = $eventsArray['events'];
				}

				$returnArray['event_type'] = $eventTypeRow;
				ajaxResponse($returnArray);
				break;
			case "compare_products":
                $_GET['product_ids'] = is_scalar($_GET['product_ids']) ? $_GET['product_ids'] : "";
				$productIds = explode("|", $_GET['product_ids']);
				ob_start();
				$productIdList = "";
				$productRows = array();
				$productCatalog = new ProductCatalog();
				$missingProductImage = getImageFilenameFromCode("NO_PRODUCT_IMAGE");
				if (empty($missingProductImage) || $missingProductImage == "/images/empty.jpg") {
					$missingProductImage = getPreference("DEFAULT_PRODUCT_IMAGE");
				}
				if (empty($missingProductImage)) {
					$missingProductImage = "/images/empty.jpg";
				}
				$showInStoreOnlyPrice = getPreference("SHOW_IN_STORE_ONLY_PRICE");
				$callForPriceText = $this->getFragment("CALL_FOR_PRICE");
				if (empty($callForPriceText)) {
					$callForPriceText = getLanguageText("Call for Price");
				}
				foreach ($productIds as $productId) {
					if (!is_numeric($productId)) {
						continue;
					}
					if (count($productRows) > 4) {
						break;
					}
					$productId = getFieldFromId("product_id", "products", "product_id", $productId);
					if (empty($productId)) {
						continue;
					}
					$productRow = ProductCatalog::getCachedProductRow($productId);
					if (!empty($productRow) && $productRow['product_id'] == $productId) {
						$productRow = array_merge($productRow, getRowFromId("product_data", "product_id", $productRow['product_id']));
						$mapPolicyId = getPreference("DEFAULT_MAP_POLICY_ID") ?: getReadFieldFromId("map_policy_id", "product_manufacturers", "product_manufacturer_id", $productRow['product_manufacturer_id']);
						$mapPolicyCode = getReadFieldFromId("map_policy_code", "map_policies", "map_policy_id", $mapPolicyId);
						$ignoreMap = ($mapPolicyCode == "IGNORE");
						if (!$ignoreMap) {
							$ignoreMap = CustomField::getCustomFieldData($productRow['product_id'], "IGNORE_MAP", "PRODUCTS");
						}
						$salePriceInfo = $productCatalog->getProductSalePrice($productRow['product_id'], array("product_information" => $productRow));
						$salePrice = (!$ignoreMap && !empty($productRow['manufacturer_advertised_price'])
							&& $productRow['manufacturer_advertised_price'] > $salePriceInfo['sale_price']) ? $productRow['manufacturer_advertised_price'] : $salePriceInfo['sale_price'];
						$callPrice = $salePriceInfo['call_price'];
						$productRow['sale_price'] = ($callPrice || $salePrice === false || ($productRow['no_online_order'] && empty($showInStoreOnlyPrice)) ? $callForPriceText : number_format($salePrice, 2));
						$productRows[] = $productRow;
						$productIdList .= (empty($productIdList) ? "" : ",") . $productRow['product_id'];
					}
				}
				$productFacets = array();
				if (!empty($productIdList)) {
					$resultSet = executeQuery("select * from product_facets where inactive = 0 and internal_use_only = 0 and product_facet_id in " .
						"(select product_facet_id from product_facet_values where product_id in (" . $productIdList . ")) order by sort_order,description");
					while ($row = getNextRow($resultSet)) {
						$dataValues = array();
						$dataSet = executeQuery("select * from product_facet_values join product_facet_options using (product_facet_option_id) where product_id in (" . $productIdList . ") and product_facet_values.product_facet_id = ?", $row['product_facet_id']);
						while ($dataRow = getNextRow($dataSet)) {
							$dataValues[$dataRow['product_id']] = $dataRow['facet_value'];
						}
						if (count($dataValues) > 1) {
							$row['data_values'] = $dataValues;
							$productFacets[] = $row;
						}
					}
				}
				?>
				<table class='grid-table' id="compare_product_table">
					<tr>
						<td></td>
						<?php
						foreach ($productRows as $productRow) {
							?>
							<td><p><img alt='product image' src="<?php echo ProductCatalog::getProductImage($productRow['product_id'], array("image_type" => "small", "default_image" => $missingProductImage)) ?>"></p>
								<p><?php echo htmlText($productRow['description']) ?></p>
								<p>UPC <?php echo $productRow['upc_code'] ?></p>
							</td>
							<?php
						}
						?>
					</tr>
					<tr>
						<td class="highlighted-text">Brand</td>
						<?php
						foreach ($productRows as $productRow) {
							?>
							<td><?php echo getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $productRow['product_manufacturer_id']) ?></td>
							<?php
						}
						?>
					</tr>
					<tr>
						<td class="highlighted-text">Sale Price</td>
						<?php
						foreach ($productRows as $productRow) {
							?>
							<td class='align-right'><?= $productRow['sale_price'] ?></td>
							<?php
						}
						?>
					</tr>
					<?php
					$shadedRow = true;
					foreach ($productFacets as $productFacet) {
						$saveValue = false;
						$differenceFound = false;
						foreach ($productRows as $productRow) {
							if ($saveValue === false) {
								$saveValue = $productFacet['data_values'][$productRow['product_id']];
								continue;
							}
							if ($productFacet['data_values'][$productRow['product_id']] != $saveValue) {
								$differenceFound = true;
							}
						}
						if (!$differenceFound) {
							continue;
						}
						?>
						<tr<?php echo($shadedRow ? " class='shaded-gray'" : "") ?>>
							<td class="highlighted-text"><?php echo $productFacet['description'] ?></td>
							<?php
							foreach ($productRows as $productRow) {
								?>
								<td><?php echo htmlText($productFacet['data_values'][$productRow['product_id']]) ?></td>
								<?php
							}
							?>
						</tr>
						<?php
						$shadedRow = !$shadedRow;
					}
					?>
				</table>
				<?php
				$returnArray['compare_products_data'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "get_store_locations":
				$returnAllStoreLocations = true;
			case "get_pickup_locations":
				if (function_exists("_localGetPickupLocations")) {
					$returnArray = _localGetPickupLocations();
					ajaxResponse($returnArray);
					break;
				}
				$productId = false;
				if (empty($_GET['product_id'])) {
					if (empty($_GET['shopping_cart_code'])) {
						$_GET['shopping_cart_code'] = "RETAIL";
					}

					$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
					$shoppingCartItems = $shoppingCart->getShoppingCartItems();
				} else {
					$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id'], "inactive = 0");
					if (empty($productId)) {
						$returnArray['error_message'] = "Invalid Product";
						ajaxResponse($returnArray);
						break;
					}
				}

				$locationIds = array();
				$resultSet = executeQuery("select * from locations where client_id = ? and inactive = 0 and internal_use_only = 0 and product_distributor_id is null" .
					($returnAllStoreLocations ? "" : " and location_id in (select location_id from shipping_methods where " .
						"inactive = 0 and internal_use_only = 0 and location_id is not null)"), $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$locationIds[] = $row['location_id'];
				}
				if (empty($productId) && !$returnAllStoreLocations) {
					$productCatalog = new ProductCatalog();
					foreach ($shoppingCartItems as $thisItem) {
						$inventoryCounts = $productCatalog->getLocationAvailability($thisItem['product_id']);
						$inventoryCounts = $inventoryCounts[$thisItem['product_id']];
						if ($inventoryCounts['distributor'] > 0) {
							continue;
						}
						foreach ($locationIds as $index => $thisLocationId) {
							if (is_array($inventoryCounts) && !array_key_exists($thisLocationId, $inventoryCounts) || $inventoryCounts[$thisLocationId] <= 0) {
								unset($locationIds[$index]);
							}
							if ($inventoryCounts[$thisLocationId] < $thisItem['quantity']) {
								unset($locationIds[$index]);
							}
						}
					}
				}
				if (empty($locationIds)) {
					$returnArray['pickup_locations'] = "No pickup Locations available" . (empty($productId) ? "" : " for these products #593");
					ajaxResponse($returnArray);
					break;
				}

				$homePoint = array();

				# Home Point
				/*
				 * Use User address first
				 * If that doesn't exist, use browser_latitude, browser_longitude, if they exist
				 * Finally, use the default location geopoint
				 *
				 */

				if (!empty($_GET['postal_code'])) {
					$homePoint = getPointForZipCode($_GET['postal_code']);
				}
				if (empty($homePoint) && $GLOBALS['gLoggedIn']) {
					if (empty($GLOBALS['gUserRow']['latitude']) || empty($GLOBALS['gUserRow']['longitude'])) {
						if ($GLOBALS['gUserRow']['country_id'] == 1000 && !empty($GLOBALS['gUserRow']['address_1']) && !empty($GLOBALS['gUserRow']['city']) &&
							!empty($GLOBALS['gUserRow']['state']) && !empty($GLOBALS['gUserRow']['postal_code'])) {
							$address = array("address_1" => $GLOBALS['gUserRow']['address_1'], "address_2" => $GLOBALS['gUserRow']['address_2'], "city" => $GLOBALS['gUserRow']['city'], "state" => $GLOBALS['gUserRow']['state'], "postal_code" => $GLOBALS['gUserRow']['postal_code']);
							$geoCode = getAddressGeocode($address);
							if (!empty($geoCode) && !empty($geoCode['validation_status']) && !empty($geoCode['latitude']) && !empty($geoCode['longitude'])) {
								executeQuery("update contacts set latitude = ?,longitude = ? where contact_id = ?", $geoCode['latitude'], $geoCode['longitude'], $GLOBALS['gUserRow']['contact_id']);
							}
							if (!empty($geoCode) && !empty($geoCode['latitude']) && !empty($geoCode['longitude'])) {
								$homePoint = array("latitude" => $geoCode['latitude'], "longitude" => $geoCode['longitude']);
							}
						}
						if (empty($homePoint) && $GLOBALS['gUserRow']['country_id'] == 1000 && !empty($GLOBALS['gUserRow']['postal_code'])) {
							$homePoint = getPointForZipCode($GLOBALS['gUserRow']['postal_code']);
						}
					} else {
						$homePoint = array("latitude" => $GLOBALS['gUserRow']['latitude'], "longitude" => $GLOBALS['gUserRow']['longitude']);
					}
				}
				if (empty($homePoint)) {
					if (!empty($_COOKIE['browser_latitude']) && !empty($_COOKIE['browser_longitude'])) {
						$homePoint = array("latitude" => $_COOKIE['browser_latitude'], "longitude" => $_COOKIE['longitude']);
					}
				}

				$defaultLocationId = "";
				if ($GLOBALS['gLoggedIn']) {
					$defaultLocationId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID");
				}
				if (empty($defaultLocationId)) {
					$defaultLocationId = $_COOKIE['default_location_id'];
				}
				$defaultLocationId = getFieldFromId("location_id", "locations", "location_id", $defaultLocationId, "product_distributor_id is null and inactive = 0 and internal_use_only = 0 and location_id in (select location_id from shipping_methods where inactive = 0 and internal_use_only = 0 and location_id is not null)");
				if (!empty($defaultLocationId) && empty($homePoint)) {
					$contactId = getFieldFromId("contact_id", "locations", "location_id", $defaultLocationId);
					$latitude = getFieldFromId("latitude", "contacts", "contact_id", $contactId);
					$longitude = getFieldFromId("longitude", "contacts", "contact_id", $contactId);
					if (!empty($latitude) && !empty($longitude)) {
						$homePoint = array("latitude" => $latitude, "longitude" => $longitude);
					}
					if (empty($homePoint)) {
						$homePoint = getPointForZipCode(getFieldFromId("postal_code", "contacts", "contact_id", $contactId));
					}
				}
				$returnArray['home_point'] = $homePoint;

				if (empty($productId) && !$returnAllStoreLocations) {
					$pickupLocations = $shoppingCart->getShippingOptions();
					if ($pickupLocations === false) {
						$returnArray['pickup_locations'] = "No pickup Locations available for these products #119";
						ajaxResponse($returnArray);
						break;
					}
				} else {
					$pickupLocations = array();
					if (!empty($productId)) {
						$productCatalog = new ProductCatalog();
						$inventoryCounts = $productCatalog->getInventoryCounts(false, array($productId));
						$inventoryCounts = $inventoryCounts[$productId];
						foreach ($inventoryCounts as $locationId => $inventoryCount) {
							if (!is_numeric($locationId)) {
								continue;
							}
							$pickupLocations[$locationId] = array("pickup" => true, "location_id" => $locationId, "inventory_count" => $inventoryCounts[$locationId]);
						}
					}
					foreach ($locationIds as $thisLocationId) {
						if (!array_key_exists($thisLocationId, $pickupLocations)) {
							$pickupLocations[$thisLocationId] = array("pickup" => true, "location_id" => $thisLocationId);
						}
					}
				}

				$pickupLocationIds = array();
				$pickupShippingMethodIds = array();
				foreach ($pickupLocations as $thisPickupLocation) {
					if (empty($thisPickupLocation['pickup'])) {
						continue;
					}
					$locationId = $thisPickupLocation['location_id'];
					if (empty($locationId)) {
						$locationId = getFieldFromId("location_id", "shipping_methods", "shipping_method_id", $thisPickupLocation['shipping_method_id']);
					}
					if (empty($locationId)) {
						$pickupShippingMethodIds[] = $thisPickupLocation['shipping_method_id'];
					} elseif (in_array($locationId, $locationIds)) {
						$pickupLocationIds[] = $locationId;
					}
				}

				if (empty($pickupLocationIds) && empty($pickupShippingMethodIds)) {
					$returnArray['pickup_locations'] = $returnArray['error_message'] = "No pickup Locations available" . (empty($productId) ? "" : " for these products #345");
					ajaxResponse($returnArray);
					break;
				}

				$locationRows = array();
				$resultSet = executeQuery("select * from locations join contacts using (contact_id) where inactive = 0 and location_id in (" . implode(",", $pickupLocationIds) . ")");
				while ($row = getNextRow($resultSet)) {
					if (array_key_exists($row['location_id'], $pickupLocations)) {
						$row['inventory_count'] = $pickupLocations[$row['location_id']]['inventory_count'];
					} else {
						$row['inventory_count'] = 0;
					}
					$row['phone_number'] = Contact::getContactPhoneNumber($row['contact_id'], 'store');
					if (!empty($row['store_information'])) {
						$row['store_information'] = makeHtml($row['store_information']);
					} else {
						$row['store_information'] = "";
					}
					if (!empty($row['address_1']) && !empty($row['city']) && !empty($row['state'])) {
						$row['directions_url'] = "https://www.google.com/maps?saddr=My+Location&daddr=" . urlencode($row['address_1'] . " " . $row['city'] . " " . $row['state'] . " " . $row['postal_code']);
					} else {
						$row['directions_url'] = "";
					}
					if ((empty($row['latitude']) || empty($row['latitude'])) && !empty($row['postal_code'])) {
						$geoCode = getPointForZipCode($row['postal_code']);
						$row['latitude'] = $geoCode['latitude'];
						$row['longitude'] = $geoCode['longitude'];
					}
					if (empty($homePoint) || empty($row['latitude']) || empty($row['longitude'])) {
						$row['distance'] = 9999;
					} else {
						$row['distance'] = calculateDistance($homePoint, array("latitude" => $row['latitude'], "longitude" => $row['longitude']));
					}
					$row['default_location'] = ($defaultLocationId == $row['location_id']);
					if (!$row['default_location'] && !empty($_GET['maximum_distance']) && is_numeric($_GET['maximum_distance']) && $row['distance'] > $_GET['maximum_distance']) {
						continue;
					}
					$locationRows[] = $row;
				}
				usort($locationRows, array($this, "distanceSort"));

				ob_start();
				foreach ($locationRows as $thisLocation) {
					$shippingMethodId = getFieldFromId("shipping_method_id", "shipping_methods", "location_id", $thisLocation['location_id']);
					$address = $thisLocation['address_1'];
					$city = trim($thisLocation['city'] . (empty($thisLocation['city']) || empty($thisLocation['state']) ? "" : ", ") . $thisLocation['state'] . " " . $thisLocation['postal_code']);
					if (!empty($city)) {
						$address .= (empty($address) ? "" : "<br>") . $city;
					}
					?>
					<div title='Click to select' class='pickup-location-wrapper pickup-location-choice' data-location_id="<?= $thisLocation['location_id'] ?>" data-location_code="<?= $thisLocation['location_code'] ?>" data-shipping_method_id="<?= $shippingMethodId ?>">
						<?php if (!empty($thisLocation['image_id'])) { ?>
							<img alt='location image' src="<?= getImageFilename($thisLocation['image_id'], array("use_cdn" => true)) ?>">
						<?php } ?>
						<p class='pickup-location-description'><?= $thisLocation['description'] ?></p>
						<p class='pickup-location-address'><?= $address ?></p>
						<?php if (!empty($thisLocation['link_name'])) { ?>
							<p><a href='<?= $thisLocation['link_name'] ?>' target='_blank'>View Store Page</a></p>
						<?php } ?>
						<div class='clear-div'></div>
					</div>
					<?php
				}
				foreach ($pickupShippingMethodIds as $shippingMethodId) {
					$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $shippingMethodId);
					?>
					<div title='Click to select' class='pickup-location-wrapper pickup-location-choice' data-location_id="" data-location_code="" data-shipping_method_id="<?= $shippingMethodId ?>">
						<p class='pickup-location-description'><?= $shippingMethodRow['description'] ?></p>
						<div class='clear-div'></div>
					</div>
					<?php
				}
				$returnArray['pickup_locations'] = ob_get_clean();
				$returnArray['store_locations'] = $locationRows;
				$returnArray['no_stores_available_text'] = getFragment("no_stores_available_text");
				if (empty($returnArray['no_stores_available_text'])) {
					$returnArray['no_stores_available_text'] = "<p id='no_stores_available_text'>No stock available in any stores.</p>";
				}
				ajaxResponse($returnArray);
				break;

			case "get_delivery_addresses":
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}

				$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				$shoppingCartItems = $shoppingCart->getShoppingCartItems();

				$addressDescriptions = array();
				if ($GLOBALS['gLoggedIn']) {
					$contactId = $GLOBALS['gUserRow']['contact_id'];
				} else {
					$shoppingCart = ShoppingCart::getShoppingCart("RETAIL");
					$contactId = $shoppingCart->getContact();
				}
				if (empty($contactId)) {
					ajaxResponse($returnArray);
					break;
				}
				$contactRow = Contact::getContact($contactId);
				$resultSet = executeQuery("select * from addresses where contact_id = ? and address_1 is not null and city is not null and inactive = 0", $contactId);
				$foundDefault = false;
				while ($row = getNextRow($resultSet)) {
					$description = (empty($row['address_label']) ? "Alternate Address" : $row['address_label']);
					$city = trim($row['city'] . (empty($row['state']) ? "" : ", ") . $row['state'] . " " . $row['postal_code']);
					$content = "<p>" . (empty($row['full_name']) ? getDisplayName($contactId) : $row['full_name']) . "<br>" . (empty($row['address_1']) ? "" : $row['address_1'] . "<br>") . (empty($row['address_2']) ? "" : $row['address_2'] . "<br>") . $city . "</p>";
					$addressDescriptions[$row['address_id']] = array("address_id" => $row['address_id'], "description" => $description, "content" => $content, "default_shipping_address" => (!$foundDefault && !empty($row['default_shipping_address'])));
					if ($row['default_shipping_address']) {
						$foundDefault = true;
					}
				}
				if (!empty($contactRow['address_1']) && !empty($contactRow['city'])) {
					$city = trim($contactRow['city'] . (empty($contactRow['state']) ? "" : ", ") . $contactRow['state'] . " " . $contactRow['postal_code']);
					$content = "<p>" . getDisplayName($contactId) . "<br>" . $contactRow['address_1'] . "<br>" . (empty($contactRow['address_2']) ? "" : $contactRow['address_2'] . "<br>") . $city . "</p>";
					$addressDescriptions["-1"] = array("address_id" => "-1", "description" => "Primary Address", "content" => $content, "default_shipping_address" => (!$foundDefault));
				}

				ob_start();
				foreach ($addressDescriptions as $thisAddress) {
					?>
					<div class='delivery-address-wrapper'>
						<p class='delivery-address-description'><?= $thisAddress['description'] ?></p>
						<p class='delivery-address-content'><?= $thisAddress['content'] ?></p>
						<p class='make-default-shipping-address-wrapper'><input type='radio' <?= ($thisAddress['default_shipping_address'] ? "checked='checked' " : "") ?>class='make-default-shipping-address' id='make_default_shipping_address_<?= $thisAddress['address_id'] ?>' name='make_default_shipping_address' value='<?= $thisAddress['address_id'] ?>'><label class='checkbox-label' for='make_default_shipping_address_<?= $thisAddress['address_id'] ?>'>Default Shipping Address</label></p>
						<p class='shipping-address-choice-wrapper'><a href='#' class='delivery-address-choice' data-address_id="<?= $thisAddress['address_id'] ?>"><span class='fad fa-truck' title='Select this address'></span></a><?php if ($thisAddress['address_id'] > 0) { ?> <a href='#' class='delete-delivery-address' data-address_id="<?= $thisAddress['address_id'] ?>"><span class='fad fa-trash'></span></a><?php } ?></p>
						<div class='clear-div'></div>
					</div>
					<?php
				}
				$returnArray['delivery_addresses'] = ob_get_clean();

				ajaxResponse($returnArray);
				break;

			case "delete_address":
				if ($GLOBALS['gLoggedIn']) {
					$contactId = $GLOBALS['gUserRow']['contact_id'];
				} else {
					$shoppingCart = ShoppingCart::getShoppingCart("RETAIL");
					$contactId = $shoppingCart->getContact();
				}
				if (empty($contactId)) {
					ajaxResponse($returnArray);
					break;
				}
				$deleteSet = executeQuery("delete from addresses where address_id = ? and contact_id = ? and not exists (select address_id from orders where address_id = addresses.address_id) and not exists (select address_id from accounts where address_id = addresses.address_id)", $_GET['address_id'], $contactId);
				if ($deleteSet['affected_row'] == 0) {
					executeQuery("update addresses set inactive = 1 where address_id = ? and contact_id = ?", $_GET['address_id'], $contactId);
				}
				ajaxResponse($returnArray);
				break;

			case "make_address_default":
				if ($GLOBALS['gLoggedIn']) {
					$contactId = $GLOBALS['gUserRow']['contact_id'];
				} else {
					$shoppingCart = ShoppingCart::getShoppingCart("RETAIL");
					$contactId = $shoppingCart->getContact();
				}
				if (empty($contactId)) {
					ajaxResponse($returnArray);
					break;
				}
				executeQuery("update addresses set default_shipping_address = ?, default_billing_address = ? where address_id = ? and contact_id = ?", (empty($_GET['default_shipping_address']) ? 0 : 1), (empty($_GET['default_billing_address']) ? 0 : 1), $_GET['address_id'], $contactId);
				if ($_GET['default_shipping_address']) {
					executeQuery("update addresses set default_shipping_address = 0 where contact_id = ? and address_id <> ?", $contactId, $_GET['address_id']);
				}
				if ($_GET['default_billing_address']) {
					executeQuery("update addresses set default_billing_address = 0 where contact_id = ? and address_id <> ?", $contactId, $_GET['address_id']);
				}
				ajaxResponse($returnArray);
				break;

			case "create_address":
				$contactId = "";
				if ($GLOBALS['gUserRow']['administrator_flag']) {
					$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['new_address_contact_id']);
				}
				if (empty($contactId)) {
					if ($GLOBALS['gLoggedIn']) {
						$contactId = $GLOBALS['gUserRow']['contact_id'];
					} else {
						if (empty($_GET['shopping_cart_code'])) {
							$_GET['shopping_cart_code'] = "RETAIL";
						}
						$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
						$contactId = $shoppingCart->getContact();
					}
				}
				if (empty($contactId) || empty($_POST['new_address_address_1']) || empty($_POST['new_address_city'])) {
					$returnArray['console'] = "Empty Address";
					ajaxResponse($returnArray);
					break;
				}
				$addressId = getFieldFromId("address_id", "addresses", "contact_id", $contactId, "address_1 is not null and city is not null and full_name <=> ? and address_1 <=> ? and address_2 <=> ? and city <=> ? and state <=> ? and postal_code <=> ? and country_id = ?",
					$_POST['new_address_full_name'], $_POST['new_address_address_1'], $_POST['new_address_address_2'], $_POST['new_address_city'], $_POST['new_address_state'], $_POST['new_address_postal_code'], $_POST['new_address_country_id']);
				if (empty($addressId)) {
					$insertSet = executeQuery("insert into addresses (contact_id,address_label,full_name,address_1,address_2,city,state,postal_code,country_id,default_shipping_address,default_billing_address,version) values (?,?,?,?,?, ?,?,?,?,?, ?,100)",
						$contactId, $_POST['new_address_address_label'], $_POST['new_address_full_name'], $_POST['new_address_address_1'], $_POST['new_address_address_2'], $_POST['new_address_city'], $_POST['new_address_state'],
						$_POST['new_address_postal_code'], $_POST['new_address_country_id'], (empty($_POST['new_address_default_shipping_address']) ? 0 : 1), (empty($_POST['new_address_default_billing_address']) ? 0 : 1));
					if (!empty($insertSet['sql_error'])) {
						ajaxResponse($returnArray);
						break;
					}
					$addressId = $insertSet['insert_id'];
					if ($_POST['new_address_default_shipping_address']) {
						executeQuery("update addresses set default_shipping_address = 0 where contact_id = ? and address_id <> ?", $contactId, $addressId);
					}
					if ($_POST['new_address_default_billing_address']) {
						executeQuery("update addresses set default_billing_address = 0 where contact_id = ? and address_id <> ?", $contactId, $addressId);
					}
				}
				$contactRow = Contact::getContact($contactId);
				if (empty($contactRow['address_1']) && empty($contactRow['city'])) {
					$addressRow = getRowFromId("addresses", "address_id", $addressId);
					executeQuery("update contacts set address_1 = ?,address_2 = ?,city = ?,state = ?,postal_code = ?,country_id = ? where contact_id = ?",
						$addressRow['address_1'], $addressRow['address_2'], $addressRow['city'], $addressRow['state'], $addressRow['postal_code'], $addressRow['country_id'], $contactId);
				}
				$addressRow = getRowFromId("addresses", "address_id", $addressId);
				if ($addressRow['inactive']) {
					executeQuery("update addresses set inactive = 0 where address_id = ?", $addressId);
				}
				if ($addressRow['address_label'] != $_POST['new_address_address_label']) {
					executeQuery("update addresses set address_label = ? where address_id = ?", $_POST['new_address_address_label'], $addressId);
				}
				$description = (empty($addressRow['address_label']) ? "" : $addressRow['address_label'] . ": ") . $addressRow['address_1'] . ", " . $addressRow['city'];
				$city = trim($addressRow['city'] . (empty($addressRow['state']) ? "" : ", ") . $addressRow['state'] . " " . $addressRow['postal_code']);
				$content = "<p>" . (empty($addressRow['full_name']) ? getDisplayName($contactId) : $addressRow['full_name']) . "<br>" . $addressRow['address_1'] . "<br>" . (empty($addressRow['address_2']) ? "" : $addressRow['address_2'] . "<br>") . $city . "</p>";
				$dropdownDescription = (empty($addressRow['full_name']) ? getDisplayName($contactId) : $addressRow['full_name']) . "<br>" . $addressRow['address_1'] . "<br>" . (empty($addressRow['address_2']) ? "" : $addressRow['address_2'] . "<br>") . $city;
				$returnArray['address_description'] = array("address_id" => $addressRow['address_id'], "description" => $description, "dropdown_description" => $dropdownDescription, "content" => $content, "country_id" => $addressRow['country_id'],
					"postal_code" => $addressRow['postal_code'], "state" => $addressRow['state'], "default_shipping_address" => (!empty($addressRow['default_shipping_address'])), "default_billing_address" => (!empty($addressRow['default_billing_address'])));
				$returnArray['full_name'] = $addressRow['full_name'];
				$returnArray['display_shipping_address'] = $addressRow['address_1'] . "<br>" . (empty($addressRow['address_2']) ? "" : $addressRow['address_2'] . "<br>") . $city;
				ajaxResponse($returnArray);
				break;

			case "get_address_description":
				if ($GLOBALS['gLoggedIn']) {
					$contactId = $GLOBALS['gUserRow']['contact_id'];
				} else {
					$shoppingCart = ShoppingCart::getShoppingCart("RETAIL");
					$contactId = $shoppingCart->getContact();
				}
				$returnArray['contact_id'] = $contactId;
				if (!empty($contactId)) {
					if ($_GET['address_id'] == "-1") {
						$contactRow = Contact::getContact($contactId);
						if (!empty($contactRow['address_1']) && !empty($contactRow['city'])) {
							$city = trim($contactRow['city'] . (empty($contactRow['state']) ? "" : ", ") . $contactRow['state'] . " " . $contactRow['postal_code']);
							$content = "<p>" . getDisplayName($contactId) . "<br>" . $contactRow['address_1'] . "<br>" . $city . "</p>";
							$returnArray['address_description'] = array("address_id" => "-1", "description" => "Primary Address", "content" => $content, "country_id" => $contactRow['country_id'], "postal_code" => $contactRow['postal_code'], "state" => $contactRow['state']);
						}
					} else {
						$resultSet = executeQuery("select * from addresses where contact_id = ? and address_1 is not null and city is not null and inactive = 0 and address_id = ?", $contactId, $_GET['address_id']);
						while ($row = getNextRow($resultSet)) {
							$description = (empty($row['address_label']) ? "Alternate Address" : $row['address_label']);
							$city = trim($row['city'] . (empty($row['state']) ? "" : ", ") . $row['state'] . " " . $row['postal_code']);
							$content = "<p>" . (empty($row['full_name']) ? getDisplayName($contactId) : $row['full_name']) . "<br>" . $row['address_1'] . "<br>" . (empty($row['address_2']) ? "" : $row['address_1'] . "<br>") . $city . "</p>";
							$returnArray['address_description'] = array("address_id" => $row['address_id'], "description" => $description, "content" => $content, "country_id" => $row['country_id'], "postal_code" => $row['postal_code'], "state" => $row['state']);
						}
					}
				}
				ajaxResponse($returnArray);
				break;
            case "get_pickup_location_description":
				$resultSet = executeQuery("select *,shipping_methods.description as shipping_method_description from shipping_methods left outer join locations using (location_id) left outer join (contacts) using (contact_id) where shipping_methods.inactive = 0 and locations.inactive = 0 and pickup = 1 and " .
					($GLOBALS['gInternalConnection'] ? "" : "locations.internal_use_only = 0 and shipping_methods.internal_use_only = 0 and ") . "shipping_method_id = ?", $_GET['shipping_method_id']);
				if ($row = getNextRow($resultSet)) {
					$address = $row['address_1'];
					$city = trim($row['city'] . (empty($row['city']) || empty($row['state']) ? "" : ", ") . $row['state'] . " " . $row['postal_code']);
					if (!empty($city)) {
						$address .= (empty($address) ? "" : "<br>") . $city;
					}
					if (empty($row['description'])) {
						$row['description'] = $row['shipping_method_description'];
					}
					$returnArray['pickup_location_description'] = array("shipping_method_id" => $row['shipping_method_id'], "description" => $row['description'], "content" => "<p>" . $row['description'] . (empty($address) ? "" : "<br>" . $address) . "</p>",
						"country_id" => (empty($row['country_id']) ? "1000" : $row['country_id']), "postal_code" => $row['postal_code'], "state" => $row['state']);
				}
				$returnArray['shipping_method_id'] = $_GET['shipping_method_id'];
				ajaxResponse($returnArray);
				break;
			case "remove_default_location":
				if ($GLOBALS['gLoggedIn']) {
					CustomField::setCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID");
				}
				$_COOKIE['default_location_id'] = "";
				setCoreCookie("default_location_id", "", 0);
				ajaxResponse($returnArray);
				break;
			case "set_default_location":
				$customFieldId = CustomField::getCustomFieldIdFromCode("DEFAULT_LOCATION_ID");
				if (empty($customFieldId)) {
					ajaxResponse($returnArray);
					break;
				}

				$locationCode = $_POST['location_code'];
				if (!empty($locationCode)) {
					$defaultLocationId = getFieldFromId("location_id", "locations", "location_code", $_POST['location_code'], "warehouse_location = 0 and inactive = 0 and internal_use_only = 0 and product_distributor_id is null");
					if (!empty($defaultLocationId)) {
						if ($GLOBALS['gLoggedIn']) {
							CustomField::setCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID", $defaultLocationId);
						}
						setCoreCookie("default_location_id", $defaultLocationId, (24 * 365 * 10));
						$_COOKIE['default_location_id'] = $defaultLocationId;
						$returnArray['default_location_id'] = $defaultLocationId;
						$locationRow = getRowFromId("locations", "location_id", $defaultLocationId);
						$returnArray['location_description'] = $locationRow['description'];
						$returnArray['location_link'] = $locationRow['link_name'];
						ajaxResponse($returnArray);
						break;
					}
				}
				$latitude = $_POST['latitude'];
				$longitude = $_POST['longitude'];
				$defaultLocationId = "";
				if (empty($latitude) || empty($longitude)) {
					$resultSet = executeQuery("select location_id from locations where client_id = ? and warehouse_location = 0 and inactive = 0 and internal_use_only = 0 and product_distributor_id is null order by sort_order", $GLOBALS['gClientId']);
					if ($row = getNextRow($resultSet)) {
						$defaultLocationId = $row['location_id'];
					}
				}
				if (empty($defaultLocationId)) {
					$currentPoint = array("latitude" => $latitude, "longitude" => $longitude);
					$distance = 0;
					$resultSet = executeQuery("select *,(select latitude from postal_codes where latitude is not null and postal_code = contacts.postal_code and " .
						"country_id = 1000 limit 1) postal_latitude,(select longitude from postal_codes where longitude is not null and postal_code = contacts.postal_code and " .
						"country_id = 1000 limit 1) postal_longitude from locations join contacts using (contact_id) where warehouse_location = 0 and inactive = 0 and internal_use_only = 0 and " .
						"product_distributor_id is null and locations.client_id = ?", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$returnArray['locations'] .= $row['location_id'] . "\n";
						$latitude = $row['latitude'];
						$longitude = $row['longitude'];
						if (empty($latitude) || empty($longitude)) {
							$latitude = $row['postal_latitude'];
							$longitude = $row['postal_longitude'];
						}
						$thisDistance = calculateDistance($currentPoint, array("latitude" => $latitude, "longitude" => $longitude));
						if (empty($defaultLocationId) || $thisDistance < $distance) {
							$defaultLocationId = $row['location_id'];
							$distance = $thisDistance;
						}
					}
					if (!empty($distance)) {
						$returnArray['distance'] = $distance;
					}
				}

				if (empty($defaultLocationId) && !empty($latitude) && !empty($longitude)) {
					setCoreCookie("browser_latitude", $latitude, (24 * 365 * 10));
					$_COOKIE['browser_latitude'] = $latitude;
					setCoreCookie("browser_longitude", $longitude, (24 * 365 * 10));
					$_COOKIE['browser_longitude'] = $longitude;
				}
				if (!empty($defaultLocationId)) {
					if ($GLOBALS['gLoggedIn']) {
						CustomField::setCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID", $defaultLocationId);
					}
					setCoreCookie("default_location_id", $defaultLocationId, (24 * 365 * 10));
					$_COOKIE['default_location_id'] = $defaultLocationId;
					$returnArray['default_location_id'] = $defaultLocationId;
					$locationRow = getRowFromId("locations", "location_id", $defaultLocationId);
					$locationContactRow = Contact::getContact($locationRow['contact_id']);
					$returnArray = array_merge_recursive($returnArray, $locationContactRow);
					$returnArray['location_description'] = $locationRow['description'];
					$returnArray['location_link'] = $locationRow['link_name'];
					$returnArray['address_block'] = $locationRow['address_1'] . " " . $locationRow['address_1'] . " " . $locationRow['city'] . ", " . $locationRow['state'] . " " . $locationRow['postal_code'];
				}
				ajaxResponse($returnArray);
				break;
			case "get_default_location":
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
				$returnArray['default_location_id'] = $defaultLocationId;
				if (!empty($defaultLocationId)) {
					$locationRow = getRowFromId("locations", "location_id", $defaultLocationId);
					$locationContactRow = Contact::getContact($locationRow['contact_id']);
					$returnArray = $locationContactRow;
					$returnArray['default_location_id'] = $defaultLocationId;
					$returnArray['location_description'] = $locationRow['description'];
					$returnArray['location_link'] = $locationRow['link_name'];
				}
				ajaxResponse($returnArray);
				break;
			case "make_offer":
				if (!$GLOBALS['gLoggedIn']) {
					$returnArray['error_message'] = "User login is required";
					ajaxResponse($returnArray);
					break;
				}
				$productId = getFieldFromId("product_id", "products", "product_id", $_POST['product_id'], "inactive = 0 and internal_use_only = 0");
				if (empty($productId) || !is_numeric($_POST['amount']) || empty($_POST['amount'])) {
					$returnArray['error_message'] = "Unable to create offer";
					ajaxResponse($returnArray);
					break;
				}

				$minimumOffer = getFieldFromId("price", "product_prices", "product_id", $productId, "product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'MINIMUM_OFFER')");
				if (empty($minimumOffer)) {
					$minimumOffer = 1;
				}
				if ($_POST['amount'] < $minimumOffer) {
					$returnArray['error_message'] = "Sorry, your offer was not accepted.";
					ajaxResponse($returnArray);
					break;
				}
				$minimumAccept = getFieldFromId("price", "product_prices", "product_id", $_POST['product_id'], "product_price_type_id in (select product_price_type_id from product_price_types where product_price_type_code = 'MINIMUM_ACCEPT_OFFER')");
				if (!empty($minimumAccept) && $_POST['amount'] >= $minimumAccept) {
					$promotionCode = $substitutions['promotion_code'] = strtoupper(getRandomString(24));
					$resultSet = executeQuery("insert into promotions (client_id,promotion_code,description,start_date,expiration_date,requires_user,user_id,maximum_usages) values (?,?,?,current_date,date_add(current_date,interval 14 day),1, ?,1)",
						$GLOBALS['gClientId'], $promotionCode, "Product Offer Accepted", $GLOBALS['gUserId']);
					$promotionId = $resultSet['insert_id'];
					executeQuery("insert into promotion_rewards_products (promotion_id,product_id,maximum_quantity,amount) values (?,?,1,?)",
						$promotionId, $productId, $_POST['amount']);
					$emailId = getFieldFromId("email_id", "emails", "email_code", "ACCEPT_PRODUCT_OFFER", "inactive = 0");
					$productRow = ProductCatalog::getCachedProductRow($productId);
					$substitutions = array("amount" => $_POST['amount'], "contact_id" => $GLOBALS['gUserRow']['contact_id']);
					$substitutions['time_submitted'] = date("m/d/Y g:i a");
					$substitutions['full_name'] = getDisplayName($substitutions['contact_id']);
					$substitutions['product_description'] = $productRow['description'];
					$substitutions['promotion_code'] = $promotionCode;
					$emailParameters = array("email_address" => $substitutions['email_address'], "substitutions" => $substitutions);
					if (empty($emailId)) {
						$emailParameters['subject'] = "Your offer has been accepted";
						$emailParameters['body'] = "<p>The offer you made on '%product_description%' in the amount of $%amount% has been accepted! To complete the purchase, log in to the online store and " .
							"add the product to your shopping cart. In the checkout process, use the promotion code %promotion_code% to reduce the price to your offer amount.</p><p>Thank you for your business!</p>";
					} else {
						$emailParameters['email_id'] = $emailId;
					}
					$emailParameters['contact_id'] = $substitutions['contact_id'];
					sendEmail($emailParameters);
					$returnArray['info_message'] = "Your offer has been accepted. See the email for instructions.";
					ajaxResponse($returnArray);
					break;
				}
				$productId = getFieldFromId("product_id", "products", "product_id", $_POST['product_id'], "inactive = 0 and internal_use_only = 0");
				$makeOfferProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "MAKE_OFFER");
				$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productId,
					"product_tag_id = ? and (start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date >= current_date)", $makeOfferProductTagId);
				if (isInUserGroupCode($GLOBALS['gUserId'], "NO_PRODUCT_OFFERS") || empty($productTagLinkId)) {
					$returnArray['error_message'] = "Unable to create offer";
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("insert into product_offers (client_id,product_id,user_id,time_submitted,amount) values (?,?,?,now(),?)", $GLOBALS['gClientId'], $productId, $GLOBALS['gUserId'], $_POST['amount']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = "Unable to create offer";
					ajaxResponse($returnArray);
					break;
				}
				$subject = "Offer Received";
				$body = "An offer was received by " . getUserDisplayName() . " for product ID " . $productId . " (" . getFieldFromId("description", "products", "product_id", $productId) . ").";
				sendEmail(array("subject" => $subject, "body" => $body, "notification_code" => "product_offers"));
				$returnArray['info_message'] = "Your offer has been received and will be considered";
				ajaxResponse($returnArray);
				break;
			case "get_credova_user_name":
				$credovaCredentials = getCredovaCredentials();
				$returnArray['credova_user_name'] = $credovaCredentials['username'];
				$returnArray['credova_test_environment'] = $credovaCredentials['test_environment'];
				ajaxResponse($returnArray);
				break;
			case "save_addons":
				if (!empty($_POST['shopping_cart_code']) && empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = $_POST['shopping_cart_code'];
				}
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				if (!$GLOBALS['gUserRow']['administrator_flag'] || empty($_POST['contact_id'])) {
					$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				} else {
					$shoppingCart = ShoppingCart::getShoppingCartForContact($_POST['contact_id'], $_GET['shopping_cart_code']);
				}

				foreach ($_POST as $fieldName => $fieldValue) {
					if (substr($fieldName, 0, strlen("addon_")) == "addon_") {
						$parts = explode("_", $fieldName);
						$productAddonId = $parts[1];
						$shoppingCartItemId = $parts[2];
						if (empty($shoppingCartItemId)) {
							$shoppingCartItemId = $_POST['shopping_cart_item_id'];
						}
						if (empty($shoppingCartItemId)) {
							$shoppingCartItemId = $shoppingCart->getShoppingCartItemId($_POST['product_id']);
						}
						if (empty($shoppingCartItemId)) {
							continue;
						}
						$shoppingCart->updateItem($shoppingCartItemId, array("product_addon_" . $productAddonId => $fieldValue));
					}
				}

				ajaxResponse($returnArray);

				break;
			case "create_credova_application":

				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$addressId = "";
				$confirmUserAccount = false;
				$shoppingCart = ShoppingCart::getShoppingCart("RETAIL");

				$copyAddressFields = array("first_name", "last_name", "business_name", "address_1", "address_2", "city", "state", "postal_code", "country_id");
				foreach ($copyAddressFields as $fieldName) {
					if (empty($_POST[$fieldName])) {
						$_POST[$fieldName] = $_POST['billing_' . $fieldName];
					}
				}

				if ($GLOBALS['gLoggedIn']) {
					$addressRow = array();
					$contactId = $GLOBALS['gUserRow']['contact_id'];
					if (strlen($_POST['address_id']) == 0 || $_POST['address_id'] == -1) {
						if (!empty($_POST['address_1']) && !empty($_POST['city']) && ($_POST['address_1'] != $GLOBALS['gUserRow']['address_1'] || $_POST['address_2'] != $GLOBALS['gUserRow']['address_2'] ||
								$_POST['city'] != $GLOBALS['gUserRow']['city'] || $_POST['state'] != $GLOBALS['gUserRow']['state'] ||
								$_POST['postal_code'] != $GLOBALS['gUserRow']['postal_code'] || $_POST['country_id'] != $GLOBALS['gUserRow']['country_id'])) {
							$addressId = getFieldFromId("address_id", "addresses", "contact_id", $contactId, "address_1 is not null and city is not null and address_label <=> ? and address_1 <=> ? and address_2 <=> ? and city <=> ? and state <=> ? and postal_code <=> ? and country_id = ?",
								$_POST['address_label'], $_POST['address_1'], $_POST['address_2'], $_POST['city'], $_POST['state'], $_POST['postal_code'], $_POST['country_id']);
							if (empty($addressId)) {
								$insertSet = executeQuery("insert into addresses (contact_id,address_label,address_1,address_2,city,state,postal_code,country_id,version) values (?,?,?,?,?, ?,?,?,200)",
									$contactId, $_POST['address_label'], $_POST['address_1'], $_POST['address_2'], $_POST['city'], $_POST['state'], $_POST['postal_code'], $_POST['country_id']);
								if (!empty($insertSet['sql_error'])) {
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									$returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
									ajaxResponse($returnArray);
									break;
								}
								$addressId = $insertSet['insert_id'];
							}
							$addressRow = getRowFromId("addresses", "address_id", $addressId);
						} else {
							$addressId = "";
							$addressRow = $GLOBALS['gUserRow'];
						}
					} else {
						if (!empty($_POST['address_id'])) {
							$addressId = getFieldFromId("address_id", "addresses", "contact_id", $contactId, "address_1 is not null and city is not null and address_id = ?", $_POST['address_id']);
							$addressRow = getRowFromId("addresses", "address_id", $addressId);
						} else {
							$addressId = "";
							$contactFields = array("address_1", "address_2", "city", "state", "postal_code", "country_id");
							$contactTable = new DataTable("contacts");
							$contactTable->setSaveOnlyPresent(true);
							$parameterArray = array();
							foreach ($contactFields as $fieldName) {
								$parameterArray[$fieldName] = $_POST[$fieldName];
							}
							if (!$contactTable->saveRecord(array("name_values" => $parameterArray, "primary_id" => $contactId))) {
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								$returnArray['error_message'] = $contactTable->getErrorMessage();
								ajaxResponse($returnArray);
								break;
							}
							$addressRow = Contact::getContact($contactId);
						}
					}
				} else {
					$contactId = $shoppingCart->getContact();
					$addressId = "";
					if (empty($contactId)) {
						$resultSet = executeQuery("select * from contacts where client_id = ? and email_address = ? and contact_id not in (select contact_id from accounts) and " .
							"contact_id not in (select contact_id from donations) and contact_id not in (select contact_id from orders) and contact_id not in (select contact_id from users)", $GLOBALS['gClientId'], $_POST['email_address']);
						if ($row = getNextRow($resultSet)) {
							$contactId = $row['contact_id'];
							$shoppingCart->setValues(array("contact_id" => $contactId));
						}
					}
					if (empty($contactId)) {
						$contactDataTable = new DataTable("contacts");
						if (!$contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'],
							"business_name" => $_POST['business_name'], "address_1" => $_POST['address_1'], "address_2" => $_POST['address_2'], "city" => $_POST['city'],
							"state" => $_POST['state'], "postal_code" => $_POST['postal_code'], "email_address" => $_POST['email_address'], "country_id" => $_POST['country_id'])))) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $contactDataTable->getErrorMessage();
							ajaxResponse($returnArray);
							break;
						}
						$contactId = $resultSet['insert_id'];
						$shoppingCart->setValues(array("contact_id" => $contactId));
					} else {
						$contactFields = array("first_name", "last_name", "business_name", "address_1", "address_2", "city", "state", "postal_code", "country_id", "email_address");
						$contactTable = new DataTable("contacts");
						$contactTable->setSaveOnlyPresent(true);
						$parameterArray = array();
						foreach ($contactFields as $fieldName) {
							$parameterArray[$fieldName] = $_POST[$fieldName];
						}
						if (!$contactTable->saveRecord(array("name_values" => $parameterArray, "primary_id" => $contactId))) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $contactTable->getErrorMessage();
							ajaxResponse($returnArray);
							break;
						}
					}
				}

				$orderObject = new Order();
				$orderObject->populateFromShoppingCart($shoppingCart);
				if (array_key_exists('donation_id', $_POST)) {
					$orderObject->setOrderField("donation_id", $_POST['donation_id']);
				}
				if (array_key_exists('referral_contact_id', $_POST)) {
					$orderObject->setOrderField("referral_contact_id", $_POST['referral_contact_id']);
				}
				if (array_key_exists('source_id', $_POST)) {
					$orderObject->setOrderField("source_id", $_POST['source_id']);
				}
				$orderObject->setOrderField("shipping_charge", $_POST['shipping_charge']);
				$orderObject->setOrderField("order_discount", $_POST['discount_amount']);
				$orderObject->setOrderField("federal_firearms_licensee_id", $_POST['federal_firearms_licensee_id']);
				$fullName = $_POST['first_name'];
				$fullName .= (empty($fullName) ? "" : " ") . $_POST['last_name'];
				$fullName .= (empty($_POST['business_name']) || empty($fullName) ? "" : ", " . $_POST['business_name']);
				$orderObject->setOrderField("full_name", $fullName);
				$orderMethodId = getFieldFromId("order_method_id", "order_methods", "order_method_code", ($GLOBALS['gInternalConnection'] ? "INTERNAL_" : "") . "WEBSITE");
				$orderObject->setOrderField("order_method_id", $orderMethodId);
				$orderObject->setOrderField("shipping_method_id", ($_POST['shipping_method_id'] <= 0 ? "" : $_POST['shipping_method_id']));
				$orderObject->setOrderField("business_address", $_POST['business_address']);
				$orderObject->setOrderField("address_id", $addressId);
				$orderObject->setOrderField("attention_line", $_POST['attention_line']);
				$orderObject->setOrderField("gift_order", $_POST['gift_order']);
				$orderObject->setOrderField("gift_text", $_POST['gift_text']);
				if (strlen($_POST['signature']) > 250) {
					$orderObject->setOrderField("signature", $_POST['signature']);
				}
				$orderObject->setOrderField("phone_number", ($_POST['phone_number'] ?: $_POST['cell_phone_number']));

				$taxCharge = $orderObject->getTax();
				if (empty($taxCharge)) {
					$taxCharge = 0;
				}
				$taxChargeDiscrepancy = false;
				if ($taxCharge > 0 && $_POST['tax_charge'] != $taxCharge) {
					addProgramLog("Tax Charge is different: post - " . $_POST['tax_charge'] . ", calculated - " . $taxCharge);
					$taxChargeDiscrepancy = false;
					$_POST['tax_charge'] = $taxCharge;
				}
				$_POST['order_total'] = $_POST['cart_total'] + $_POST['tax_charge'] + $_POST['shipping_charge'] + $_POST['handling_charge'];

				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();

				$credovaCredentials = getCredovaCredentials();
				$credovaUserName = $credovaCredentials['username'];
				$credovaPassword = $credovaCredentials['password'];
				$credovaTest = $credovaCredentials['test_environment'];
				$credovaPaymentMethodId = $credovaCredentials['credova_payment_method_id'];
				if (empty($credovaPaymentMethodId)) {
					$returnArray['error_message'] = "Credova payment method unavailable";
					ajaxResponse($returnArray);
					break;
				}

				$orderTotal = str_replace(",", "", $_POST['order_total']);
				$otherPayments = 0;
				$credovaPaymentAmount = 0;
				$credovaRemainder = false;
				foreach ($_POST as $fieldName => $fieldValue) {
					if (substr($fieldName, 0, strlen("payment_method_number_")) == "payment_method_number_") {
						$paymentMethodNumber = substr($fieldName, strlen("payment_method_number_"));
						if ($_POST['payment_method_id_' . $paymentMethodNumber] == $credovaPaymentMethodId) {
							$credovaPaymentAmount = $_POST['payment_amount_' . $paymentMethodNumber];
							$credovaRemainder = (empty($credovaPaymentAmount));
						} else {
							if (!empty($_POST['payment_amount_' . $paymentMethodNumber])) {
								$otherPayments += $_POST['payment_amount_' . $paymentMethodNumber];
							}
						}
					}
				}
				if ($credovaRemainder) {
					$credovaPaymentAmount = $orderTotal - $otherPayments;
				}
				if ($credovaPaymentAmount < 300 || $credovaPaymentAmount > 5000) {
					$returnArray['error_message'] = "Credova financing amount must be between $300 and $5000";
					ajaxResponse($returnArray);
					break;
				}

				$headers = array("Content-Type: application/x-www-form-urlencoded");
				$fields = array("username" => $credovaUserName, "password" => $credovaPassword);

				$ch = curl_init();

				curl_setopt($ch, CURLOPT_URL, "https://" . ($credovaTest ? "sandbox-" : "") . "lending-api.credova.com/v2/token");
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_POST, TRUE);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$curlError = curl_error($ch);

				$response = curl_exec($ch);
				$decoded = json_decode($response, TRUE);
				$authenticationToken = $decoded["jwt"];
				$returnArray['authentication_token'] = $authenticationToken;
				addProgramLog("Credova Authenticate:\n" . jsonEncode($_POST) . "\n" . jsonEncode($fields) . "\n" . jsonEncode($decoded) . "\n" .
					$response . "\n" . $authenticationToken . "\n" . $httpCode . "\n" . $curlError . "\nCart: " . $_POST['cart_total'] . "\nTax" . $_POST['tax_charge'] . ", " . $taxCharge .
					"\nShipping: " . $_POST['shipping_charge'] . "\nHandling: " . $_POST['handling_charge']);

				$headers = array("Content-Type: application/json", "Authorization: Bearer " . $authenticationToken);

				$credovaFirstName = $_POST['first_name'];
				if (empty($credovaFirstName)) {
					$credovaFirstName = $GLOBALS['gUserRow']['first_name'];
				}
				if (empty($credovaFirstName)) {
					$credovaFirstName = getFieldFromId("first_name", "contacts", "contact_id", $contactId);
				}
				$credovaLastName = $_POST['last_name'];
				if (empty($credovaLastName)) {
					$credovaLastName = $GLOBALS['gUserRow']['last_name'];
				}
				if (empty($credovaLastName)) {
					$credovaLastName = getFieldFromId("last_name", "contacts", "contact_id", $contactId);
				}
				$fields = array(
					"publicId" => $_POST['public_identifier'],
					"storeCode" => $credovaUserName,
					"firstName" => $credovaFirstName,
					"lastName" => $credovaLastName,
					"mobilePhone" => str_replace(" ", "", str_replace("(", "", str_replace(")", "", str_replace("-", "", formatPhoneNumber($_POST['mobile_phone']))))),
					"email" => ($GLOBALS['gLoggedIn'] ? $GLOBALS['gUserRow']['email_address'] : $_POST['email_address']),

					"address" => array(
						"street" => $_POST['address_1'],
						"suiteApartment" => "",
						"city" => $_POST['city'],
						"state" => $_POST['state'],
						"zipCode" => substr($_POST['postal_code'], 0, 5))
				);

				$productsArray = array();
				$shoppingCartProductIds = array();
				$shoppingCartItems = $shoppingCart->getShoppingCartItems(array("reset_sale_price" => true));
				$itemsTotal = 0;
				foreach ($shoppingCartItems as $thisItem) {
					$itemsTotal += ($thisItem['quantity'] * $thisItem['sale_price']);
				}
				$itemCount = 0;
				$totalRemainder = $credovaPaymentAmount;
				foreach ($shoppingCartItems as $thisItem) {
					$itemCount++;
					$productRow = ProductCatalog::getCachedProductRow($thisItem['product_id']);
					$thisProduct = array();
					$thisProduct['id'] = $thisItem['product_id'];
					$thisProduct['description'] = substr($productRow['description'], 0, 255);
					$thisProduct['quantity'] = $thisItem['quantity'];
					$thisItemTotal = ($thisItem['quantity'] * $thisItem['sale_price']);
					if ($itemCount == count($shoppingCartItems)) {
						$thisProduct['value'] = $totalRemainder;
					} else {
						$thisProduct['value'] = round(($thisItemTotal / $itemsTotal) * $credovaPaymentAmount, 2);
						$totalRemainder -= $thisProduct['value'];
					}
					$productsArray[] = $thisProduct;
				}
				$fields['products'] = $productsArray;

				$ch = curl_init();

				curl_setopt($ch, CURLOPT_URL, "https://" . ($credovaTest ? "sandbox-" : "") . "lending-api.credova.com/v2/applications");
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
				curl_setopt($ch, CURLOPT_POST, TRUE);
				curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$curlError = curl_error($ch);

				$response = curl_exec($ch);
				$decoded = json_decode($response, TRUE);

				$programlogId = addProgramLog("Credova Application:\n" . jsonEncode($_POST) . "\n" . $GLOBALS['gUserId'] . "\n" . jsonEncode($fields) . "\n" . jsonEncode($decoded) . "\n" . $response . "\n" . $authenticationToken . "\n" . $httpCode . "\n" . $curlError);
				$publicIdentifier = $decoded["publicId"];
				if (empty($publicIdentifier)) {
					$errorMessage = $decoded['errors'][0];
					if (empty($errorMessage)) {
						$errorMessage = "Unable to get Credova information";
					}
					$returnArray['error_message'] = $errorMessage;
					addProgramLog("\n" . $errorMessage, $programlogId);
					ajaxResponse($returnArray);
					break;
				}
				$credovaLoanId = getFieldFromId("credova_loan_id", "credova_loans", "public_identifier", $publicIdentifier);
				if (empty($credovaLoanId)) {
					if (!empty($contactId)) {
						executeQuery("insert into credova_loans (contact_id,public_identifier,authentication_token) values (?,?,?)", $contactId, $publicIdentifier, $authenticationToken);
					}
				} else {
					executeQuery("update credova_loans set authentication_token = ? where public_identifier = ?", $authenticationToken, $publicIdentifier);
				}
				$returnArray['public_identifier'] = $decoded["publicId"];
				$returnArray['link'] = $decoded["link"];
				ajaxResponse($returnArray);
				break;
			case "create_credova":
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				if ($GLOBALS['gLoggedIn']) {
					$contactId = $GLOBALS['gUserRow']['contact_id'];
				} else {
					$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
					$contactId = $shoppingCart->getContact();
				}

				if (!empty($contactId)) {
					$publicId = $_POST['public_identifier'];
					$credovaLoanRow = getRowFromId("credova_loans", "public_identifier", $publicId);
					if (!empty($credovaLoanRow['contact_id'])) {
						$returnArray['error_message'] = ($credovaLoanRow['contact_id'] == $contactId || empty($contactId) ? "" : "Invalid Loan ID: " . $contactId . "-" . $credovaLoanRow['contact_id']);
					} else {
						executeQuery("delete from credova_loans where contact_id = ? and order_id is null", $contactId);
						$resultSet = executeQuery("insert into credova_loans (contact_id,public_identifier) values (?,?)", $contactId, $publicId);
						$returnArray['loan_created'] = true;
					}
				}
				ajaxResponse($returnArray);
				break;
			case "validate_address":
				$linkUrl = 'https://secure.shippingapis.com/ShippingAPITest.dll?API=Verify&XML=' . urlencode('<AddressValidateRequest USERID="614COREW2690"><Address ID="0"><Address1>' .
						$_POST['address_1'] . "</Address1><Address2></Address2><City>" . $_POST['city'] . "</City><State>" . $_POST['state'] . "</State><Zip5>" . $_POST['postal_code'] .
						"</Zip5><Zip4></Zip4></Address></AddressValidateRequest>");
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_POST, 0);
				curl_setopt($ch, CURLOPT_URL, $linkUrl);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $GLOBALS['gCurlTimeout']);
				curl_setopt($ch, CURLOPT_TIMEOUT, $GLOBALS['gCurlTimeout']);
				$response = curl_exec($ch);
				$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				curl_close($ch);
				$addressResponse = "";
				if ($httpCode == 200) {
					if (!empty($response)) {
						$response = html_entity_decode($response);
						$response = trim(str_replace("<?xml version=\"1.0\" encoding=\"UTF-8\"?>", "", $response), " \r\n\t\0");
						$response = trim(str_replace("\r\n", "\r", $response), " \r\n\t\0");
						if (!empty($response)) {
							$addressResponse = simplexml_load_string($response, "SimpleXMLElement", LIBXML_NOWARNING);
							$addressResponse = processXml($addressResponse);
							if (array_key_exists("AddressValidateResponse", $addressResponse) && array_key_exists("Address", $addressResponse['AddressValidateResponse']) && !empty($addressResponse['AddressValidateResponse']['Address']['Address2'])) {
								$returnArray['address_1'] = ucwords(strtolower($addressResponse['AddressValidateResponse']['Address']['Address2']));
								$returnArray['city'] = ucwords(strtolower($addressResponse['AddressValidateResponse']['Address']['City']));
								$returnArray['state'] = $addressResponse['AddressValidateResponse']['Address']['State'];
								$returnArray['postal_code'] = $addressResponse['AddressValidateResponse']['Address']['Zip5'] . (empty($addressResponse['AddressValidateResponse']['Address']['Zip4']) ? "" : "-" . $addressResponse['AddressValidateResponse']['Address']['Zip4']);
								if ($_POST['address_1'] == $returnArray['address_1'] && $_POST['city'] == $returnArray['city'] && $_POST['postal_code'] == $returnArray['postal_code']) {
									$returnArray = array();
								} else {
									$returnArray['entered_address'] = $_POST['address_1'] . "<br>" . $_POST['city'] . ", " . $_POST['state'] . " " . $_POST['postal_code'];
									$returnArray['validated_address'] = $returnArray['address_1'] . "<br>" . $returnArray['city'] . ", " . $returnArray['state'] . " " . $returnArray['postal_code'];
								}
							}
						}
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_all_reviews":
				$reviewCount = 0;
				$starCount = 0;
				$totalStars = 0;
				$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id'], "inactive = 0");
				switch ($_GET['sort_order']) {
					case "like_count":
						$sortOrder = "like_count desc,date_created desc";
						break;
					case "date_created":
						$sortOrder = "date_created desc";
						break;
					default:
						$sortOrder = "rating desc,date_created desc";
						break;
				}
				$resultSet = executeQuery("select * from product_reviews where product_id = ? and inactive = 0 and requires_approval = 0 order by " . $sortOrder, $productId);
				ob_start();
				while ($row = getNextRow($resultSet)) {
					$reviewCount++;
					if (strlen($row['rating']) > 0) {
						$starCount++;
						$totalStars += $row['rating'];
					}
					$reviewer = $row['reviewer'];
					if (empty($reviewer)) {
						$contactId = Contact::getUserContactId($row['user_id']);
						$contactFields = Contact::getMultipleContactFields($contactId, array("first_name", "last_name", "email_address"));
						if (!empty($contactFields['first_name'])) {
							$reviewer = $contactFields['first_name'] . " " . substr($contactFields['last_name'], 0, 1);
						} else {
							$reviewer = $contactFields['email_address'];
							if (strpos($reviewer, "@") !== false) {
								$parts = explode("@", $reviewer);
								$reviewer = $parts[0];
							}
						}
					}
					$starReviews = ProductCatalog::getReviewStars($row['rating']);
					$purchased = false;
					if (!empty($row['user_id'])) {
						$orderItemId = getReadFieldFromId("order_item_id", "order_items", "product_id", $row['product_id'], "order_id in (select order_id from orders where contact_id = (select contact_id from users where user_id = ?))", $row['user_id']);
						if (!empty($orderItemId)) {
							$purchased = true;
						}
					}
					$alreadyLiked = $_COOKIE[$row['product_id'] . "-" . $row['product_review_id'] . "-review"];
					?>
					<div class='review-wrapper'>
						<div class='review-date'><?= date("m/d/Y", strtotime($row['date_created'])) ?></div>
						<?php if ($purchased) { ?>
							<p class='reviewer-purchased'>Reviewer has purchased this product.</p>
						<?php } ?>
						<div class='reviewer-name'><?= htmlText($reviewer) ?></div>
						<?php if (!empty($starReviews)) { ?>
							<div class='review-stars'><?= $starReviews ?></div>
						<?php } ?>
						<div class='like-wrapper like-review<?= (empty($alreadyLiked) ? "" : " already-liked") ?>' data-product_review_id='<?= $row['product_review_id'] ?>'><span class='fad fa-thumbs-up'></span> <span class='like-count'><?= $row['like_count'] ?></span></div>
						<div class='review-title'><?= htmlText($row['title_text']) ?></div>
						<div class='review-content'><?= makeHtml($row['content']) ?></div>
						<?php if (!empty($row['response_content'])) { ?>
							<div class='review-response-content'><?= makeHtml($row['response_content']) ?></div>
						<?php } ?>
					</div>
					<?php
				}
				$returnArray['all_reviews'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "save_review":
				if (!empty($_POST['_add_hash'])) {
					$resultSet = $GLOBALS['gPrimaryDatabase']->executeQuery("select * from add_hashes where add_hash = ?", $_POST['_add_hash']);
					if ($row = $GLOBALS['gPrimaryDatabase']->getNextRow($resultSet)) {
						ajaxResponse($returnArray);
						break;
					}
				}
				$reviewRequiresUser = getPreference("RETAIL_STORE_REVIEW_REQUIRES_USER");
				if (!empty($reviewRequiresUser) && !$GLOBALS['gLoggedIn']) {
					$returnArray['error_message'] = "User account required";
					ajaxResponse($returnArray);
					break;
				}
				if (empty($_POST['title_text']) || empty($_POST['content']) || empty($_POST['product_id'])) {
					$returnArray['error_message'] = "Missing information" . ($GLOBALS['gUserRow']['superuser_flag'] ? ": " . jsonEncode($_POST) : "");
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				if (!empty($_POST['_add_hash'])) {
					executeQuery("insert into add_hashes (add_hash,date_used) values (?,now())", $_POST['_add_hash']);
				}
				$requiresApproval = getPreference("RETAIL_STORE_REVIEWS_REQUIRE_APPROVAL");
				$resultSet = executeQuery("insert into product_reviews (product_id,user_id,reviewer,date_created,rating,title_text,content,requires_approval) values (?,?,?,current_date,?,?,?,?)",
					$_POST['product_id'], $GLOBALS['gUserId'], $_POST['reviewer'], $_POST['rating'], $_POST['title_text'], $_POST['content'], (empty($requiresApproval) ? 0 : 1));
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = "Unable to save review";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				} else {
					$returnArray['info_message'] = "Product review successfully saved";
					if ($requiresApproval) {
						sendEmail(array("subject" => "Product Review", "body" => "A product review has been submitted and requires approval", "notification_code" => "PRODUCT_REVIEW"));
					}
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
				}
				ajaxResponse($returnArray);
				break;
			case "get_all_questions":
				$resultSet = executeReadQuery("select * from product_questions where product_question_id in (select product_question_id from product_answers where inactive = 0 and requires_approval = 0) and product_id = ? and inactive = 0 and requires_approval = 0 order by like_count desc,date_created desc", $_GET['product_id']);
				ob_start();
				while ($row = getNextRow($resultSet)) {
					$questioner = $row['full_name'];
					if (empty($questioner)) {
						$contactFields = Contact::getContactFromUserId($row['user_id']);
						if (!empty($contactFields['first_name'])) {
							$questioner = $contactFields['first_name'] . " " . substr($contactFields['last_name'], 0, 1);
						} else {
							$questioner = $contactFields['email_address'];
							if (strpos($questioner, "@") !== false) {
								$parts = explode("@", $questioner);
								$questioner = $parts[0];
							}
						}
					}
					$alreadyLiked = $_COOKIE[$this->iProductId . "-" . $row['product_question_id'] . "-question"];
					?>
					<div class='question-wrapper'>
						<div class='question-date'><?= date("m/d/Y", strtotime($row['date_created'])) ?> by <?= htmlText($questioner) ?>
							<div class='like-wrapper like-question<?= (empty($alreadyLiked) ? "" : " already-liked") ?>' data-product_question_id='<?= $row['product_question_id'] ?>'><span class='fad fa-thumbs-up'></span> <span class='like-count'><?= $row['like_count'] ?></span></div>
						</div>
						<div class='question-content'><?= (isHtml($row['content']) ? $row['content'] : makeHtml($row['content'])) ?></div>
						<div class='question-answer-wrapper'>
							<?php
							$answerSet = executeReadQuery("select * from product_answers where product_question_id = ? and inactive = 0 and requires_approval = 0 order by like_count desc,date_created desc", $row['product_question_id']);
							while ($answerRow = getNextRow($answerSet)) {
								$answerer = $answerRow['full_name'];
								if (empty($answerer)) {
									$contactFields = Contact::getContactFromUserId($answerRow['user_id']);
									if (!empty($contactFields['first_name'])) {
										$answerer = $contactFields['first_name'] . " " . substr($contactFields['last_name'], 0, 1);
									} else {
										$answerer = $contactFields['email_address'];
										if (strpos($answerer, "@") !== false) {
											$parts = explode("@", $questioner);
											$questioner = $parts[0];
										}
									}
								}
								$alreadyLiked = $_COOKIE[$this->iProductId . "-" . $row['product_answer_id'] . "-question"];
								?>
								<div class='answer-wrapper'>
									<div class='answer-date'><?= date("m/d/Y", strtotime($answerRow['date_created'])) ?> by <?= htmlText($answerer) ?>
										<div class='like-wrapper like-answer<?= (empty($alreadyLiked) ? "" : " already-liked") ?>' data-product_answer_id='<?= $answerRow['product_answer_id'] ?>'><span class='fad fa-thumbs-up'></span> <span class='like-count'><?= $answerRow['like_count'] ?></span></div>
									</div>
									<div class='answer-content'><?= (isHtml($answerRow['content']) ? $answerRow['content'] : makeHtml($answerRow['content'])) ?></div>
								</div>
								<?php
							}
							?>
						</div>
					</div>
					<?php
				}
				$returnArray['all_questions'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "save_product_question":
				if (!empty($_POST['_add_hash'])) {
					$resultSet = $GLOBALS['gPrimaryDatabase']->executeQuery("select * from add_hashes where add_hash = ?", $_POST['_add_hash']);
					if ($row = $GLOBALS['gPrimaryDatabase']->getNextRow($resultSet)) {
						ajaxResponse($returnArray);
						break;
					}
				}
				$questionRequiresUser = getPreference("RETAIL_STORE_QUESTION_REQUIRES_USER");
				if (!empty($questionRequiresUser) && !$GLOBALS['gLoggedIn']) {
					$returnArray['error_message'] = "User account required";
					ajaxResponse($returnArray);
					break;
				}
				if (empty($_POST['content']) || empty($_POST['product_id'])) {
					$returnArray['error_message'] = "Missing information" . ($GLOBALS['gUserRow']['superuser_flag'] ? ": " . jsonEncode($_POST) : "");
					ajaxResponse($returnArray);
					break;
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				if (!empty($_POST['_add_hash'])) {
					executeQuery("insert into add_hashes (add_hash,date_used) values (?,now())", $_POST['_add_hash']);
				}
				$resultSet = executeQuery("insert into product_questions (product_id,user_id,full_name,content,date_created,requires_approval) values (?,?,?,?,current_date,1)",
					$_POST['product_id'], $GLOBALS['gUserId'], $_POST['full_name'], $_POST['content']);
				if (!empty($resultSet['sql_error'])) {
					$returnArray['error_message'] = "Unable to save question";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				} else {
					$returnArray['info_message'] = "Product question successfully saved";
					sendEmail(array("subject" => "Product Question", "body" => "A product question has been submitted and requires approval and an answer", "notification_code" => "PRODUCT_QUESTION"));
					$GLOBALS['gPrimaryDatabase']->commitTransaction();
				}
				ajaxResponse($returnArray);
				break;
			case "like_product_data":
				if (empty($_POST['product_id']) || (empty($_POST['product_question_id']) && empty($_POST['product_answer_id']) && empty($_POST['product_review_id']))) {
					ajaxResponse($returnArray);
					break;
				}
				if (!empty($_POST['product_question_id']) && !empty($_COOKIE[$_POST['product_id'] . "-" . $_POST['product_question_id'] . "-question"])) {
					$returnArray['console'] = "already liked";
					ajaxResponse($returnArray);
					break;
				}
				if (!empty($_POST['product_answer_id']) && !empty($_COOKIE[$_POST['product_id'] . "-" . $_POST['product_answer_id'] . "-answer"])) {
					$returnArray['console'] = "already liked";
					ajaxResponse($returnArray);
					break;
				}
				if (!empty($_POST['product_review_id']) && !empty($_COOKIE[$_POST['product_id'] . "-" . $_POST['product_review_id'] . "-review"])) {
					$returnArray['console'] = "already liked";
					ajaxResponse($returnArray);
					break;
				}
				$updateCount = 0;
				if (!empty($_POST['product_question_id'])) {
					$updateSet = executeQuery("update product_questions set like_count = like_count + 1 where product_id = ? and product_question_id = ?", $_POST['product_id'], $_POST['product_question_id']);
					$updateCount += $updateSet['affected_rows'];
					$_COOKIE[$_POST['product_id'] . "-" . $_POST['product_question_id'] . "-question"] = true;
					setCoreCookie($_POST['product_id'] . "-" . $_POST['product_question_id'] . "-question", true);
				}
				if (!empty($_POST['product_answer_id'])) {
					$updateSet = executeQuery("update product_answers set like_count = like_count + 1 where product_question_id in (select product_question_id from product_questions where product_id = ?) and product_answer_id = ?", $_POST['product_id'], $_POST['product_answer_id']);
					$updateCount += $updateSet['affected_rows'];
					$_COOKIE[$_POST['product_id'] . "-" . $_POST['product_answer_id'] . "-answer"] = true;
					setCoreCookie($_POST['product_id'] . "-" . $_POST['product_answer_id'] . "-answer", true);
				}
				if (!empty($_POST['product_review_id'])) {
					$updateSet = executeQuery("update product_reviews set like_count = like_count + 1 where product_id = ? and product_review_id = ?", $_POST['product_id'], $_POST['product_review_id']);
					$updateCount += $updateSet['affected_rows'];
					$_COOKIE[$_POST['product_id'] . "-" . $_POST['product_review_id'] . "-review"] = true;
					setCoreCookie($_POST['product_id'] . "-" . $_POST['product_review_id'] . "-review", true);
				}
				$returnArray['count_increased'] = ($updateCount > 0);
				ajaxResponse($returnArray);
				break;
			case "add_promotion_code":
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				if (!$GLOBALS['gUserRow']['administrator_flag'] || empty($_GET['contact_id']) || $_GET['shopping_cart_code'] != "ORDERENTRY") {
					$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				} else {
					$shoppingCart = ShoppingCart::getShoppingCartForContact($_GET['contact_id'], $_GET['shopping_cart_code']);
				}
				$resultSet = executeQuery("update product_map_overrides set override_code = null where shopping_cart_id = ? and override_code = ?", $shoppingCart->getShoppingCartId(), $_GET['promotion_code']);
				if ($resultSet['affected_rows'] > 0) {
					$returnArray['promotion_code'] = "";
					$returnArray['info_message'] = "Custom quote price applied successfully.";
					ajaxResponse($returnArray);
					break;
				}
				$promotionCode = makeCode($_GET['promotion_code']);
				if (empty($promotionCode)) {
					$shoppingCart->removePromotion();
				} elseif (!$shoppingCart->applyPromotionCode($promotionCode)) {
					$returnArray['error_message'] = $shoppingCart->getErrorMessage();
					$returnArray['promotion_code'] = "";
				} else {
					$promotionId = $shoppingCart->getPromotionId();
					$promotionRow = getReadRowFromId("promotions","promotion_id",$promotionId);
					$oneTimeUsePromotionCodeId = getReadFieldFromId("one_time_use_promotion_code_id","one_time_use_promotion_codes","promotion_id",$promotionId,"order_id is null");
					$returnArray['promotion_code'] = $_GET['promotion_code'];
					$returnArray['promotion_id'] = $promotionRow['promotion_id'];
					$returnArray['promotion_code_description'] = $promotionRow['description'];
					$returnArray['promotion_code_details'] = makeHtml($promotionRow['detailed_description']);
				}
				$shoppingCartItems = $shoppingCart->getShoppingCartItems(array("reset_sale_price" => true));

				ajaxResponse($returnArray);
				break;
			case "get_account_limit":
				$accountRow = getRowFromId("accounts", "account_id", $_GET['account_id'], "contact_id = ? and inactive = 0 and " .
					"payment_method_id in (select payment_method_id from payment_methods where client_id = ? and payment_method_type_id in " .
					"(select payment_method_type_id from payment_method_types where payment_method_type_code = 'CHARGE_ACCOUNT'))", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId']);
				if (empty($accountRow)) {
					$returnArray['error_message'] = "Account does not exist";
				} else {
					$charges = 0;
					$payments = 0;
					$resultSet = executeQuery("select sum(amount + shipping_charge + tax_charge + handling_charge) from order_payments where account_id = ?", $accountRow['account_id']);
					if ($row = getNextRow($resultSet)) {
						$charges = $row['sum(amount + shipping_charge + tax_charge + handling_charge)'];
					}
					$resultSet = executeQuery("select sum(amount) from account_payments where account_id = ?", $accountRow['account_id']);
					if ($row = getNextRow($resultSet)) {
						$payments = $row['sum(amount)'];
					}
					$balance = $charges - $payments;
					$creditLimit = $accountRow['credit_limit'] - $balance;
					$returnArray['info_message'] = ($creditLimit <= 0 ? "No available credit on this account" : "Credit Limit of $" . number_format($creditLimit, 2));
				}
				ajaxResponse($returnArray);
				break;
			case "get_credit_account_limit":
				$accountRow = getRowFromId("accounts", "account_id", $_GET['account_id'], "contact_id = ? and inactive = 0 and " .
					"payment_method_id in (select payment_method_id from payment_methods where client_id = ? and payment_method_type_id in " .
					"(select payment_method_type_id from payment_method_types where payment_method_type_code = 'CREDIT_ACCOUNT'))", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId']);
				if (empty($accountRow)) {
					$returnArray['error_message'] = "Account does not exist";
				} else {
					$creditLimit = $accountRow['credit_limit'];
					$returnArray['info_message'] = ($creditLimit <= 0 ? "No available credit on this account" : "Credit Limit of $" . number_format($creditLimit, 2));
				}
				ajaxResponse($returnArray);
				break;
			case "get_email_for_price_dialog":
				ob_start();
				ProductCatalog::getEmailForPriceDialog();
				$returnArray['dialog'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "email_for_price":
				$sumCaptcha = getFieldFromId("notes", "images", "image_code", "sum_captcha");
				if (!empty($sumCaptcha) && (!is_numeric($_POST['sum_captcha']) || ($sumCaptcha - $_POST['sum_captcha']) != 0)) {
					$returnArray['error_message'] = "Unable to get custom quote at this time.";
					ajaxResponse($returnArray);
					break;
				}
				$firstName = $_POST['email_for_price_first_name'];
				$lastName = $_POST['email_for_price_last_name'];
				$emailAddress = $_POST['email_for_price_email_address'];
				$zipCode = $_POST['email_for_price_zip_code'];
				$productId = getFieldFromId("product_id", "products", "product_id", $_POST['email_for_price_product_id'], "inactive = 0 and internal_use_only = 0");
				$mapPolicyId = getFieldFromId("map_policy_id", "product_manufacturers", "product_manufacturer_id", getFieldFromId("product_manufacturer_id", "products", "product_id", $productId));
				$mapPolicyCode = getFieldFromId("map_policy_code", "map_policies", "map_policy_id", $mapPolicyId);
				$emailIdArray = array();
				$emailId = "";
				$resultSet = executeQuery("select * from emails where client_id = ? and email_code like ? and email_code not like ? and inactive = 0",
					$GLOBALS['gClientId'], "RETAIL_STORE_CUSTOM_QUOTE" . ($mapPolicyCode == "STRICT_CODE" ? "_CODE" : "") . "%", ($mapPolicyCode == "STRICT_CODE" ? "XXXXXX" : "RETAIL_STORE_CUSTOM_QUOTE_CODE%"));
				while ($row = getNextRow($resultSet)) {
					$emailIdArray[] = $row['email_id'];
					$emailId = $row['email_id'];
				}
				if (count($emailIdArray) > 1) {
					$arrayKey = array_rand($emailIdArray);
					$emailId = $emailIdArray[$arrayKey];
				}
				if (empty($emailAddress) || empty($firstName) || empty($lastName) || !empty($zipCode) || empty($emailId) || empty($productId)) {
					$returnArray['error_message'] = "Sorry, unable to get custom quote at this time.";
					ajaxResponse($returnArray);
					break;
				}
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				$contactId = $shoppingCart->getContact();
				if (empty($contactId)) {
					$sourceId = getFieldFromId("source_id", "sources", "source_id", $_COOKIE['source_id'], "inactive = 0");
					if (empty($sourceId)) {
						$sourceId = getSourceFromReferer($_SERVER['HTTP_REFERER']);
					}
					$contactDataTable = new DataTable("contacts");
					if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $firstName, "last_name" => $lastName,
						"email_address" => $emailAddress, "source_id" => $sourceId)))) {
						$returnArray['error_message'] = $contactDataTable->getErrorMessage();
						ajaxResponse($returnArray);
						break;
					}
					$shoppingCart->setValues(array("contact_id" => $contactId));
				} else {
					if (!$GLOBALS['gLoggedIn']) {
						$resultSet = executeQuery("update contacts set first_name = ?,last_name = ?,email_address = ? where contact_id = ?",
							$firstName, $lastName, $emailAddress, $contactId);
					}
				}
				$overrideCode = ($mapPolicyCode == "STRICT_CODE" ? strtoupper(getRandomString(16)) : "");
				executeQuery("delete from product_map_overrides where time_requested < date_sub(now(),interval 24 hour) or (product_id = ? and shopping_cart_id = ?)", $productId, $shoppingCart->getShoppingCartId());
				$insertSet = executeQuery("insert into product_map_overrides (product_id,shopping_cart_id,time_requested,override_code,inactive) values (?,?,now(),?,?)",
					$productId, $shoppingCart->getShoppingCartId(), $overrideCode, ($mapPolicyCode == "STRICT_CODE" ? 0 : 1));
				$productRow = ProductCatalog::getCachedProductRow($productId);
				$productDataRow = getRowFromId("product_data", "product_id", $productId);
				$productCatalog = new ProductCatalog();

				$salePriceInfo = $productCatalog->getProductSalePrice($productId, array("product_information" => array_merge($productRow, $productDataRow), "shopping_cart_id" => $shoppingCart->getShoppingCartId(), "ignore_map" => true));
				$originalSalePrice = "";
				$originalSalePrice = $salePriceInfo['original_sale_price'];
				$salePrice = $salePriceInfo['sale_price'];
				$mapEnforced = $salePriceInfo['map_enforced'];
				$callPrice = $salePriceInfo['call_price'];
				if (empty($originalSalePrice)) {
					$originalSalePrice = $productRow['list_price'];
				}
				if ($originalSalePrice <= $salePrice) {
					$originalSalePrice = "";
				}

				$addToCartLink = ($mapPolicyCode == "STRICT_CODE" ? "" : getDomainName() . "/shopping-cart?quote_id=" . $insertSet['insert_id']);
				$substitutions = array_merge($productRow, $productDataRow, array("add_to_cart_link" => $addToCartLink, "override_code" => $overrideCode, "product_map_override_id" => $insertSet['insert_id'], "first_name" => $firstName,
					"last_name" => $lastName, "email_address" => $emailAddress, "sale_price" => number_format($salePrice, 2), "original_sale_price" => (empty($originalSalePrice) ? "" : "$" . number_format($originalSalePrice, 2)),
					"expiration_time" => date("m/d/Y g:ia", strtotime("+24 hours"))));

				$minimumSeconds = 300;
				$openingHour = getPreference("EMAIL_FOR_QUOTE_OPENING_HOUR");
				if (empty($openingHour)) {
					$openingHour = 7.0;
				}
				$closingHour = getPreference("EMAIL_FOR_QUOTE_CLOSING_HOUR");
				if (empty($closingHour)) {
					$closingHour = 19.0;
				}
				$currentHour = intval(date("H")) + (intval(date("i")) / 60);
				if ($currentHour < $openingHour || $currentHour > $closingHour) {
					$minimumSeconds += ($currentHour < $openingHour ? $openingHour - $currentHour : 24 - $currentHour + $openingHour) * 3600;
				}
				$seconds = rand($minimumSeconds, $minimumSeconds + 300);
				sendEmail(array("send_after" => date("Y-m-d H:i:s", strtotime("+" . $seconds . " seconds")), "email_id" => $emailId, "contact_id" => $contactId, "email_address" => $emailAddress, "substitutions" => $substitutions));
				if ($mapPolicyCode == "STRICT_CODE") {
					$returnArray['add_to_cart'] = true;
					$returnArray['info_message'] = $this->getFragment("REQUEST_QUOTE_RESPONSE");
					if (empty($returnArray['info_message'])) {
						$returnArray['info_message'] = "The item has been added to your cart at full price. Check your email for instructions on getting your custom quote. " .
							"Our typical business hours are " . Events::getDisplayTime($openingHour) . "-" . Events::getDisplayTime($closingHour) . ". If it is outside business hours, the email might not come right away.";
					}
				} else {
					$returnArray['info_message'] = $this->getFragment("REQUEST_QUOTE_RESPONSE");
					if (empty($returnArray['info_message'])) {
						$returnArray['info_message'] = "Check your email for your custom quote. Our typical business hours are " . Events::getDisplayTime($openingHour) . "-" .
							Events::getDisplayTime($closingHour) . ". If it is outside business hours, the email might not come right away.";
					}
				}

				ajaxResponse($returnArray);

				break;
			case "create_gift_card":
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				$amount = $_POST['gift_card_amount'];
				if ($amount <= 0 || !is_numeric($amount)) {
					$returnArray['error_message'] = "Invalid Amount";
					ajaxResponse($returnArray);
					break;
				}
				$productId = "";
				if (!empty($_POST['product_id'])) {
					$productId = getFieldFromId("product_id", "products", "product_id", $_POST['product_id'],
						"inactive = 0 and client_id = ? and product_type_id = (select product_type_id from product_types where product_type_code = 'GIFT_CARD' and client_id = ?)",
						$GLOBALS['gClientId'], $GLOBALS['gClientId']);
				} else {
					$productId = getFieldFromId("product_id", "products", "product_code", "GIFT_CARD", "inactive = 0");
				}
				$productCatalog = new ProductCatalog();
				$salePriceInfo = $productCatalog->getProductSalePrice($productId);
				if (!empty($salePriceInfo) && !empty($salePriceInfo['sale_price'])) {
					$returnArray['error_message'] = "Invalid Product";
					ajaxResponse($returnArray);
					break;
				}
				if (empty($productId)) {
					$returnArray['error_message'] = "Invalid Product";
					ajaxResponse($returnArray);
					break;
				}
				$quantity = 1;
				$shoppingCart->addItem(array("product_id" => $productId, "quantity" => $quantity, "set_quantity" => true, "sale_price" => $amount));
				$returnArray['shopping_cart_item_count'] = $shoppingCart->getShoppingCartItemsCount();
				$returnArray['product_id'] = $productId;
				$returnArray['response'] = getFragment("retail_store_gift_card_response");
				if (empty($returnArray['response'])) {
					$returnArray['response'] = "A gift card was added to your shopping cart for the amount of $" . number_format($amount, 2) . ".";
				} else {
					$returnArray['response'] = str_replace("%amount%", number_format($amount, 2), $returnArray['response']);
				}

				ajaxResponse($returnArray);

				break;
			case "get_product_search_results":
				if (is_array($_SESSION['product_search_results_array'])) {
					$returnArray['product_search_results'] = $_SESSION['product_search_results_array'][$_GET['results_key']];
					unset($_SESSION['product_search_results_array'][$_GET['results_key']]);
					unset($_SESSION['product_search_results_timestamp'][$_GET['results_key']]);
				}
				if (is_array($_SESSION['product_search_results_timestamp'])) {
					foreach ($_SESSION['product_search_results_timestamp'] as $resultsKey => $timestamp) {
						if (($timestamp + 60) < time()) {
							unset($_SESSION['product_search_results_array'][$resultsKey]);
							unset($_SESSION['product_search_results_timestamp'][$resultsKey]);
						}
					}
				}
				saveSessionData();
				ajaxResponse($returnArray);
				exit;
			case "get_products":
				if (!empty($_POST['no_cache'])) {
					$_GET['no_cache'] = true;
				}
				if ($_GET['url_source'] == "tagged_products" && empty($_POST['product_tag_code'])) {
					$returnArray['debug'] = "No Tag Code";
					ajaxResponse($returnArray);
					break;
				}

				# Wish List Products
				if ($_GET['url_source'] == "wish_list_products") {
					if (!$GLOBALS['gLoggedIn']) {
						ajaxResponse($returnArray);
						break;
					}
					try {
						$wishList = new WishList();
						$wishListItems = $wishList->getWishListItems();
					} catch (Exception $e) {
						$wishListItems = array();
					}
					if (empty($wishListItems)) {
						ajaxResponse($returnArray);
						break;
					}
					$productIds = "";
					foreach ($wishListItems as $index => $thisItem) {
						$productIds .= (empty($productIds) ? "" : ",") . $thisItem['product_id'];
					}
					if (empty($productIds)) {
						ajaxResponse($returnArray);
						break;
					}
					$_POST['specific_product_ids'] = $productIds;
					$_POST['exclude_out_of_stock'] = true;
				}

				#Also Interested Products
				if ($_GET['url_source'] == "also_interested_products") {
					$cartProductIds = explode(",", $_GET['cart_product_ids']);
					$productIds = array();
					if ($GLOBALS['gLoggedIn']) {
						$resultSet = executeQuery("select * from product_view_log where contact_id = ? and product_id in (select product_id from products where inactive = 0 and internal_use_only = 0) and " .
							"product_id in (select product_id from product_inventories where product_id = product_view_log.product_id and quantity > 0) order by log_time desc", $GLOBALS['gUserRow']['contact_id']);
						while ($row = getNextRow($resultSet)) {
							if (!in_array($row['product_id'], $cartProductIds)) {
								$productIds[] = $row['product_id'];
							}
							if (count($productIds) >= 5) {
								break;
							}
						}
					}
					foreach ($cartProductIds as $thisProductId) {
						$count = 0;
						$resultSet = executeQuery("select product_manufacturer_id, (select product_category_id from product_category_links where product_id = products.product_id order by sequence_number limit 1) product_category_id from products where product_id = ?", $thisProductId);
						if ($row = getNextRow($resultSet)) {
							$productManufacturerId = $row['product_manufacturer_id'];
							$productCategoryId = $row['product_category_id'];
						} else {
							continue;
						}
						if (empty($productManufacturerId) && empty($productCategoryId)) {
							continue;
						}
						$resultSet = executeQuery("select product_id,(select count(*) from order_items where product_id = ?) sale_count from products where inactive = 0 and internal_use_only = 0 and " .
							"product_id in (select product_id from product_inventories where product_id = product_view_log.product_id and quantity > 0) and product_id <> ? and client_id = ? and " .
							"(product_manufacturer_id = ? or product_id in (select product_id from product_category_links where product_category_id = ?)) order by sale_count desc",
							$thisProductId, $thisProductId, $GLOBALS['gClientId'], $productManufacturerId, $productCategoryId);
						while ($row = getNextRow($resultSet)) {
							if (in_array($row['product_id'], $cartProductIds) || in_array($row['product_id'], $productIds)) {
								continue;
							}
							$count++;
							$productIds[] = $row['product_id'];
							if ($count >= 5) {
								break;
							}
						}
					}
					$_POST['specific_product_ids'] = $productIds;
					$_POST['exclude_out_of_stock'] = true;
					$_GET['no_cache'] = true;
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
				$productCatalog = new ProductCatalog();

				$_POST = array_merge($_POST, $_GET);
				if (empty($_POST['select_limit'])) {
					$_POST['select_limit'] = 50000;
				}

				if (!empty($_POST['product_tag_group_code'])) {
					$resultSet = executeQuery("select product_tag_id from product_tags where client_id = ? and product_tag_group_id in (select product_tag_group_id from product_tag_groups where product_tag_group_code = ?)",
						$GLOBALS['gClientId'], $_POST['product_tag_group_code']);
					while ($row = getNextRow($resultSet)) {
						$_POST['product_tag_ids'] .= (empty($_POST['product_tag_ids']) ? "" : "|") . $row['product_tag_id'];
					}
				}

				$parameters = $_POST;
				$searchableParameters = array("contributor_id", "location_code", "location_codes", "location_id", "location_ids",
					"product_category_code", "product_category_codes", "product_category_group_code", "product_category_group_codes", "product_category_group_id", "product_category_group_ids", "product_category_id",
					"product_category_ids", "product_department_code", "product_department_codes", "product_department_id", "product_department_ids", "product_facet_option_id", "product_facet_option_ids",
					"product_manufacturer_code", "product_manufacturer_codes", "product_manufacturer_id", "product_manufacturer_ids", "product_manufacturer_tag_code", "product_manufacturer_tag_id",
					"product_tag_code", "product_tag_codes", "product_tag_id", "product_tag_ids", "product_type_code", "product_type_codes", "product_type_id", "product_type_ids", "related_product_id",
					"related_product_type_code", "search_group", "search_parameter_group_id", "search_text", "specific_product_ids", "states");
				foreach ($parameters as $key => $keyValue) {
					if (empty($keyValue) || !in_array($key, $searchableParameters)) {
						unset($parameters[$key]);
					}
				}
				if (!empty($parameters)) {
					if (array_key_exists("search_text", $_POST)) {
						$productCatalog->setSearchText($_POST['search_text']);
					}
					$productCatalog->showOutOfStock(empty($_POST['exclude_out_of_stock']));
					if ($_GET['url_source'] == "tagged_products") {
						$productCatalog->setPushInStockToTop(true);
					}
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
					if (array_key_exists("related_product_type_code", $_POST) && !empty($_POST['related_product_type_code'])) {
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

                    $productFacetIds = array();
                    if (array_key_exists("product_facet_ids", $_POST) && !empty($_POST['product_facet_ids'])) {
                        if (!is_array($_POST['product_facet_ids'])) {
                            $_POST['product_facet_ids'] = explode("|", $_POST['product_facet_ids']);
                        }
                        foreach ($_POST['product_facet_ids'] as $productFacetId) {
                            $productFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_id", $productFacetId);
                            if (!empty($productFacetId) && !in_array($productFacetId, $productFacetIds)) {
                                $productFacetIds[] = $productFacetId;
                            }
                        }
                    }
                    if (!empty($productFacetIds)) {
                        $productCatalog->setProductFacets($productFacetIds);
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
					if (empty($_POST['search_text']) && empty($_POST['related_product_id']) && empty($_POST['related_product_type_code']) && empty($_POST['states']) && empty($_POST['specific_product_ids']) && empty($productDepartmentIds) &&
						empty($productCategoryIds) && empty($productTypeIds) && empty($productCategoryGroupIds) && empty($productManufacturerIds) && empty($locationIds) && empty($searchGroupIds) &&
						empty($productTagIds) && empty($productFacetOptionIds) && empty($contributorIds)) {
						$productCatalog->setSpecificProductIds(-1);
					}

					$productCatalogCacheKey = $productCatalog->getCacheKey();
					$productResults = false;
					$queryTime = "";
					$startTime = getMilliseconds();
					$callForPriceText = $this->getFragment("CALL_FOR_PRICE");
					if (empty($callForPriceText)) {
						$callForPriceText = getLanguageText("Call for Price");
					}

					if (empty($_GET['no_cache'])) {
						$cachedResults = getCachedData("product_search_results", $productCatalogCacheKey);
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
						setCachedData("product_search_results", $productCatalogCacheKey, array("cached_product_results" => $cachedProductResults, "cached_product_result_keys" => $cachedProductResultKeys, "constraints" => $constraints, "result_count" => $resultCount, "display_search_text" => $displaySearchText), 18);
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

					$showLocationAvailability = getPreference("RETAIL_STORE_SHOW_LOCATION_AVAILABILITY");
					if (!empty($showLocationAvailability)) {
						$productLocationAvailability = $productCatalog->getLocationAvailability();
						foreach ($productResults as $index => $thisProduct) {
							$productResults[$index]['location_availability'] = $productCatalog::getProductAvailabilityText($thisProduct, $productLocationAvailability);
						}
					}

					$resultCount = $productCatalog->getResultCount();
					$queryTime = $productCatalog->getQueryTime();
				}

				$contributorTypes = array();
				$catalogResultHtml = ProductCatalog::getCatalogResultHtml(true);
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

				$productIds = array();
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
					$remoteImageUrlUsed = false;
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
						$productResults[$index]['product_detail_link'] = (empty($thisProduct['link_name']) ? "product-details?id=" . $thisProduct['product_id'] : "/product/" . $thisProduct['link_name']);

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
						if (!empty($thisProduct['remote_image_url'])) {
							$remoteImageUrlUsed = true;
						}
					}
					$resultCount = count($productResults);
					$necessaryFields = array("product_id", "product_code", "description", "product_manufacturer_id", "product_category_ids", "product_tag_ids", "product_facet_option_ids", "image_base_filename", "remote_image", "sale_price", "hide_dollar", "manufacturer_advertised_price", "inventory_quantity", "product_detail_link", "map_enforced", "call_price", "no_online_order");
					$removeFields = array();
					if ($remoteImageUrlUsed) {
						$necessaryFields['remote_image_url'] = "remote_image_url";
					}
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
				$manufacturerNames = array();
				$resultSet = executeQuery("select product_manufacturer_id,description,map_policy_id,(select product_manufacturer_map_holiday_id from product_manufacturer_map_holidays where " .
					"product_manufacturer_id = product_manufacturers.product_manufacturer_id and start_date <= current_date and end_date >= current_date limit 1) map_holiday from " .
					"product_manufacturers where client_id = ?", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$row['map_policy_id'] = $defaultMapPolicyId ?: $row['map_policy_id'];
					$mapPolicyCode = $mapPolicies[$row['map_policy_id']];
					$manufacturerNames[$row['product_manufacturer_id']] = array($row['description'], ($mapPolicyCode == "IGNORE" || !empty($row['map_holiday']) ? "1" : "0"), $mapPolicyCode);
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
				if (!empty($_POST['reductive_field_list']) && !empty($productResults)) {
					$reductiveData = array();
					$fieldList = explode(",", $_POST['reductive_field_list']);

					if (!empty($temporaryTableName = $productCatalog->getTemporaryTableName())) {
						$productsWhereStatement = "product_id in (select product_id from " . $temporaryTableName . ")";
					} else {
						$productsWhereStatement = "product_id in (" . implode(",", $productIds) . ")";
					}

					foreach ($fieldList as $thisFieldName) {
						switch ($thisFieldName) {
							case "product_manufacturers":
								$reductiveData[$thisFieldName] = array();
								$resultSet = executeQuery("select distinct product_manufacturer_id from products where " . $productsWhereStatement);
								while ($row = getNextRow($resultSet)) {
									$reductiveData[$thisFieldName][] = $row['product_manufacturer_id'];
								}
								break;
							case "product_categories":
								$reductiveData[$thisFieldName] = array();
								$resultSet = executeQuery("select distinct product_category_id from product_category_links where " . $productsWhereStatement);
								while ($row = getNextRow($resultSet)) {
									$reductiveData[$thisFieldName][] = $row['product_category_id'];
								}
								break;
							case "product_tags":
								$reductiveData[$thisFieldName] = array();
								$resultSet = executeQuery("select distinct product_tag_id from product_tag_links where (start_date is null or start_date <= current_date) and " .
									"(expiration_date is null or expiration_date > current_date) and " . $productsWhereStatement);
								while ($row = getNextRow($resultSet)) {
									$reductiveData[$thisFieldName][] = $row['product_tag_id'];
								}
								break;
							default:
								if (!startsWith($thisFieldName, "product_facet_code-")) {
									break;
								}
								$facetCode = substr($thisFieldName, strlen("product_facet_code-"));
								$productFacetId = getFieldFromId("product_facet_id", "product_facets", "product_facet_code",
									$facetCode, "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
								if (empty($productFacetId)) {
									break;
								}
								$reductiveData[$thisFieldName] = array();
								$resultSet = executeQuery("select distinct product_facet_option_id from product_facet_values where product_facet_id = ? and " . $productsWhereStatement, $productFacetId);
								while ($row = getNextRow($resultSet)) {
									$reductiveData[$thisFieldName][] = $row['product_facet_option_id'];
								}
								break;
						}
					}
					$returnArray['reductive_data'] = $reductiveData;
				}
				if ($GLOBALS['gUserRow']['superuser_flag']) {
					$returnArray['console'] = $productCatalog->getQueryString();
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
			case "get_product_tag_html":
				ob_start();
				?>
				<div id='catalog_result_product_tags_template' class='hidden'>
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
				$returnArray['catalog_result_product_tags_template'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "get_catalog_result_html":
			case "get_related_result_html":
				$returnArray['catalog_result_html'] = ProductCatalog::getCatalogResultHtml($_GET['force_tile'] == "true");
				$relatedResultTemplate = getFragment("RETAIL_STORE_RELATED_PRODUCT_RESULT");
				$returnArray['catalog_result_html'] .= (empty($relatedResultTemplate) ? "<div id='_related_product_result'></div>" : $relatedResultTemplate);
				ajaxResponse($returnArray);
				break;
			case "get_create_user_dialog":
				$createUserDialog = $this->getPageTextChunk("retail_store_create_user");
				if (empty($createUserDialog)) {
					$createUserDialog = $this->getFragment("retail_store_create_user");
				}
				if (empty($createUserDialog)) {
					ob_start();
					?>
					<p>This feature is only available for logged in users. Go <a href="/create-account">here</a> to create an
						account an enjoy the benefits of membership, including wish lists, notifications of products coming back
						into stock, saving shipping addresses and payment methods, notifications of sales, and special pricing.
					</p>
					<?php
					$createUserDialog = ob_get_clean();
				}
				ob_start();
				?>
				<div id="_create_user_dialog" class="dialog-box">
					<?php
					echo $createUserDialog;
					?>
				</div>
				<?php
				$returnArray['dialog'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "get_out_of_stock_dialog":
				$outOfStockDialog = $this->getPageTextChunk("retail_store_out_of_stock_dialog");
				if (empty($outOfStockDialog)) {
					$outOfStockDialog = $this->getFragment("retail_store_out_of_stock_dialog");
				}
				if (empty($outOfStockDialog)) {
					ob_start();
					?>
					<p>As a user, you can add this item to your wishlist and, in your wishlist, indicate that you want to be
						notified when it is back in stock.</p>
					<p class="align-center">
						<button class='add-to-wishlist' data-notify_when_in_stock="Y" data-product_id="">Add To Wishlist</button>
					</p>
					<?php
					$outOfStockDialog = ob_get_clean();
				}
				ob_start();
				?>
				<div id="_out_of_stock_dialog" class="dialog-box">
					<?php
					echo $outOfStockDialog;
					?>
				</div>
				<?php
				$returnArray['dialog'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;

			case "get_non_user_out_of_stock_dialog":
				$outOfStockDialog = $this->getPageTextChunk("retail_store_non_user_out_of_stock_dialog");
				if (empty($outOfStockDialog)) {
					$outOfStockDialog = $this->getFragment("retail_store_non_user_out_of_stock_dialog");
				}
				if (empty($outOfStockDialog)) {
					ob_start();
					?>
					<p>If you create a user account, you can maintain a wishlist and, in your wishlist, indicate that you want to be notified when items are back in stock. Without a user account, you can still get a one-time notification when the item comes available.</p>
					<div class='form-line'>
						<label>First Name</label>
						<input type='text' tabindex='10' size='60' class='validate[required]' id='non_user_out_of_stock_first_name' name='first_name'>
					</div>
					<div class='form-line'>
						<label>Last Name</label>
						<input type='text' tabindex='10' size='60' class='validate[required]' id='non_user_out_of_stock_last_name' name='last_name'>
					</div>
					<div class='form-line'>
						<label>Email Address</label>
						<input type='text' tabindex='10' size='60' class='validate[required,custom[email]]' id='non_user_out_of_stock_email_address' name='email_address'>
					</div>
					<?php
					$outOfStockDialog = ob_get_clean();
				}
				ob_start();
				?>
				<div id="_non_user_out_of_stock_dialog" class="dialog-box">
					<form id='_non_user_out_of_stock_form'>
						<input type='hidden' id='non_user_out_of_stock_product_id' name='product_id'>
						<?php
						echo $outOfStockDialog;
						?>
					</form>
				</div>
				<?php
				$returnArray['dialog'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;

			case "create_non_user_out_of_stock_notification":
				$productId = getFieldFromId("product_id", "products", "product_id", $_POST['product_id'], "inactive = 0");
				if (empty($productId)) {
					$returnArray['error_message'] = "Invalid Product";
					ajaxResponse($returnArray);
					break;
				}
				$emailAddress = $_POST['email_address'];
				$firstName = $_POST['first_name'];
				$lastName = $_POST['last_name'];
				if (empty($emailAddress) || empty($firstName) || empty($lastName)) {
					$returnArray['error_message'] = "Contact information is required";
					ajaxResponse($returnArray);
					break;
				}
				$contactId = getFieldFromId("contact_id", "contacts", "email_address", $emailAddress, "first_name = ? and last_name = ?", $firstName, $lastName);
				if (empty($contactId)) {
					$contactDataTable = new DataTable("contacts");
					$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $firstName, "last_name" => $lastName,
						"email_address" => $emailAddress)));
				}
				$insertSet = executeQuery("insert ignore into product_availability_notifications (product_id,contact_id) values (?,?)", $productId, $contactId);
				if (empty($insertSet['sql_error'])) {
					$returnArray['info_message'] = "Notification Created";
				} else {
					$returnArray['error_message'] = "Unable to create notification";
				}
				ajaxResponse($returnArray);
				break;

			case "create_order":
				$orderEntryCreated = (!empty($_GET['order_entry']) && $GLOBALS['gUserRow']['administrator_flag']);
				if (!empty($_POST['bank_name']) || !empty($_POST['agree_terms']) || !empty($_POST['confirm_human'])) {
					sleep(30);
					$returnArray['error_message'] = "Charge failed: Transaction declined (8638)";
					addProgramLog("Order unable to be completed because BOT detection fields were populated.\n\n" . jsonEncode($this->cleanPostData()));
					ajaxResponse($returnArray);
					break;
				}

				$credovaLoanId = "";
				$credovaPaymentExists = false;
				$credovaCredentials = getCredovaCredentials();
				$credovaUserName = $credovaCredentials['username'];
				$credovaPassword = $credovaCredentials['password'];
				$credovaTest = $credovaCredentials['test_environment'];
				$credovaPaymentMethodId = $credovaCredentials['credova_payment_method_id'];
				$paymentMethodNumber = 1;
				foreach ($_POST as $fieldName => $fieldValue) {
					if (substr($fieldName, 0, strlen("payment_method_number_")) == "payment_method_number_") {
						$paymentMethodNumber = substr($fieldName, strlen("payment_method_number_"));
						if (!empty($credovaPaymentMethodId) && $_POST["payment_method_id_" . $paymentMethodNumber] == $credovaPaymentMethodId) {
							$credovaPaymentExists = true;
						}
					}
				}

				if ($credovaPaymentExists) {
					if ($credovaTest != $GLOBALS['gDevelopmentServer']) {
						addProgramLog("Order unable to be completed because Credova environment does not match server environment.\nCredova test mode: "
							. ($credovaTest ? "true" : "false") . ", Development client: " . ($GLOBALS['gDevelopmentServer'] ? "true" : "false")
							. "\n\n" . jsonEncode($this->cleanPostData()));
						$returnArray['error_message'] = "Credova error occurred. Contact support.";
						ajaxResponse($returnArray);
						break;
					}
					addProgramLog("Credova Order Placed: " . jsonEncode($this->cleanPostData()));
				} elseif (!$orderEntryCreated && empty($_POST['quick_checkout_flag']) && !$GLOBALS['gUserRow']['administrator_flag']) {
					if (!empty(getPreference("ORDER_RECAPTCHA_V2_SITE_KEY")) && !empty(getPreference("ORDER_RECAPTCHA_V2_SECRET_KEY"))) {
						if (empty($_POST['g-recaptcha-response'])) {
							$returnArray['error_message'] = "Invalid captcha";
							addProgramLog("Order unable to be completed because of invalid captcha: User response token missing in request.\n\n" . jsonEncode($this->cleanPostData()));
							ajaxResponse($returnArray);
							break;
						}

						$ch = curl_init();
						$recaptchaVerifyURL = "https://www.google.com/recaptcha/api/siteverify?secret=" . getPreference("ORDER_RECAPTCHA_V2_SECRET_KEY") . "&response=" . $_POST['g-recaptcha-response'];
						curl_setopt($ch, CURLOPT_URL, $recaptchaVerifyURL);
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
						curl_setopt($ch, CURLOPT_POST, TRUE);
						curl_setopt($ch, CURLOPT_HEADER, 0);

						$response = curl_exec($ch);
						$decodedResponse = json_decode($response, TRUE);
						$_POST['decoded_recaptcha_response'] = $decodedResponse;

						if (empty($decodedResponse) || empty($decodedResponse['success']) || empty($decodedResponse['hostname'])) {
							$returnArray['error_message'] = "Invalid captcha";
							addProgramLog("Order unable to be completed because of invalid captcha: Site verification failed.\n\n" . jsonEncode($this->cleanPostData()));
							ajaxResponse($returnArray);
							break;
						}

						$matchDomain = false;
						if (empty(strcasecmp($GLOBALS['gDomainNameRow']['domain_name'], $decodedResponse['hostname']))) {
							$matchDomain = true;
						}
						if (!$matchDomain && $GLOBALS['gDomainNameRow']['include_www'] && empty(strcasecmp($GLOBALS['gDomainNameRow']['domain_name'], str_replace("www.", "", $decodedResponse['hostname'])))) {
							$matchDomain = true;
						}
						if (!$matchDomain) {
							$returnArray['error_message'] = "Invalid captcha";
							addProgramLog("Order unable to be completed because of invalid captcha: Invalid hostname.\n\n" . jsonEncode($this->cleanPostData()));
							ajaxResponse($returnArray);
							break;
						}
					} elseif (getPreference("USE_ORDER_CAPTCHA")) {
						$captchaCode = getFieldFromId("captcha_code", "captcha_codes", "captcha_code_id", $_POST['captcha_code_id']);
						if (empty($_POST['captcha_code']) || strtoupper($captchaCode) != strtoupper($_POST['captcha_code'])) {
							$_SESSION['wrong_captcha_count'] = (empty($_SESSION['wrong_captcha_count']) ? 1 : $_SESSION['wrong_captcha_count'] + 1);
							saveSessionData();
							if ($_SESSION['wrong_captcha_count'] > 10) {
								blacklistIpAddress($_SERVER['REMOTE_ADDR'], "Too many captcha failures");
							}
							$returnArray['error_message'] = "Invalid captcha code" . ($GLOBALS['gUserRow']['administrator_flag'] ? ":" . $_POST['captcha_code'] . ":" . $captchaCode : "");
							addProgramLog("Order unable to be completed because of invalid captcha code.\n\n" . jsonEncode($this->cleanPostData()));
							ajaxResponse($returnArray);
							break;
						}
					}
				}
				if (empty($_POST['shopping_cart_code'])) {
					$_POST['shopping_cart_code'] = "RETAIL";
				}
				$contactId = false;
				if (function_exists("_localGetOrderContact")) {
					$contactId = _localGetOrderContact($_POST);
					if (!empty($contactId)) {
						$shoppingCart = ShoppingCart::getShoppingCartForContact($GLOBALS['gUserRow']['contact_id'], $_POST['shopping_cart_code']);
						$shoppingCart->setValues(array("contact_id" => $contactId));
					}
				}

				if (empty($contactId)) {
					if ($orderEntryCreated) {
						$contactId = $_POST['contact_id'];
					} else {
						$contactId = $GLOBALS['gUserRow']['contact_id'];
					}
				}
                $userId = getFieldFromId("user_id", "users", "contact_id", $contactId);
				$contactRow = Contact::getContact($contactId);
				if (empty($_POST['_add_hash'])) {
					$returnArray['error_message'] = "Invalid Order Data";
					$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
					addProgramLog("Order unable to be completed because of invalid Order Data.\n\n" . jsonEncode($this->cleanPostData()));
					ajaxResponse($returnArray);
					break;
				}
				if (empty($_POST['country_id'])) {
					$_POST['country_id'] = 1000;
				}

				if (empty($_POST['checkout_version']) && !$GLOBALS['gLoggedIn'] && empty($_POST['email_address']) && !$GLOBALS['gInternalConnection']) {
					$returnArray['error_message'] = "Email address is required";
					$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
					addProgramLog("Order unable to be completed because of missing email address.\n\n" . jsonEncode($this->cleanPostData()));
					ajaxResponse($returnArray);
					break;
				}

				if ($orderEntryCreated) {
					$shoppingCart = ShoppingCart::getShoppingCartForContact($contactId, $_POST['shopping_cart_code']);
				} else {
					$shoppingCart = ShoppingCart::getShoppingCart($_POST['shopping_cart_code']);
				}

				# make sure all order item addons are updated in the shopping cart

				foreach ($_POST as $fieldName => $fieldValue) {
					if (substr($fieldName, 0, strlen("addon_")) == "addon_") {
						$parts = explode("_", $fieldName);
						if ($parts[1] == "select") {
							$productAddonId = $fieldValue;
							$shoppingCartItemId = $parts[3];
							$quantity = $_POST['quantity_' . $fieldName] ?: 1;
						} else {
							$productAddonId = $parts[1];
							$shoppingCartItemId = $parts[2];
							$quantity = $fieldValue;
						}
						if (empty($shoppingCartItemId)) {
							continue;
						}
						$shoppingCart->updateItem($shoppingCartItemId, array("product_addon_" . $productAddonId => $quantity));
					}
				}

				if (function_exists("_localServerPreOrderInventoryCheck")) {
					$returnValue = _localServerPreOrderInventoryCheck($shoppingCart);
					if ($returnValue !== true) {
						$returnArray['error_message'] = ($returnValue === false ? "Unable to process order" : $returnValue);
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						addProgramLog("Order unable to be completed because an error occurred: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
						ajaxResponse($returnArray);
						break;
					}
				}
				$returnValue = $shoppingCart->checkInventoryLevels();
				if (!$returnValue) {
					$returnArray['error_message'] = "Some items are no longer available. Quantities have been adjusted. Remove products that are out of stock.";
					$returnArray['reload_cart'] = true;
					$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
					addProgramLog("Order unable to be completed because an error occurred: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
					ajaxResponse($returnArray);
					break;
				}

				if (function_exists("_localServerPreprocessOrder")) {
					$returnValue = _localServerPreprocessOrder($shoppingCart);
					if ($returnValue !== true) {
						$returnArray['error_message'] = $returnValue;
						$returnArray['recalculate'] = true;
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						addProgramLog("Order unable to be completed because an error occurred: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
						ajaxResponse($returnArray);
						break;
					}
				}

				if (!empty($_SESSION['form_displayed']) && !$GLOBALS['gDevelopmentServer'] && !$GLOBALS['gUserRow']['administrator_flag']) {
					$_SESSION['form_submitted'] = date("U");
					saveSessionData();
					$timeToSubmit = $_SESSION['form_submitted'] - $_SESSION['form_displayed'];
					if (!$GLOBALS['gLoggedIn'] && $timeToSubmit <= 5) {
						sleep(10);
						$returnArray['error_message'] = "Charge failed: Transaction declined (5748)";
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						addProgramLog("Order unable to be completed because time to submit is invalid: " . $timeToSubmit . "\n\n" . jsonEncode($this->cleanPostData()));
						ajaxResponse($returnArray);
						break;
					}
					if ($timeToSubmit > 3600) {
						$returnArray['error_message'] = "Shopping cart timed out... reloading page";
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						$returnArray['reload_page'] = true;
						ajaxResponse($returnArray);
						break;
					}
				}
				if (empty($_SESSION['form_displayed']) && empty($GLOBALS['gUserRow']['administrator_flag'])) {
					sleep(30);
					$returnArray['error_message'] = "Charge failed: Transaction declined (6582)";
					addProgramLog("Order unable to be completed because SESSION variable 'form_displayed' is empty" . "\n\n" . jsonEncode($this->cleanPostData()));
					$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
					ajaxResponse($returnArray);
					break;
				}

				$shoppingCartIpAddresses = getCachedData("shopping_cart_ip_addresses", "shopping_cart_ip_addresses");
				if (!is_array($shoppingCartIpAddresses)) {
					$shoppingCartIpAddresses = array();
				}
				$changeFound = false;
				if (array_key_exists($shoppingCart->getShoppingCartId(), $shoppingCartIpAddresses)) {
					if (!in_array($_SERVER['REMOTE_ADDR'], $shoppingCartIpAddresses[$shoppingCart->getShoppingCartId()])) {
						$shoppingCartIpAddresses[$shoppingCart->getShoppingCartId()][] = $_SERVER['REMOTE_ADDR'];
						$changeFound = true;
					}
				} else {
					$shoppingCartIpAddresses[$shoppingCart->getShoppingCartId()] = array($_SERVER['REMOTE_ADDR']);
					$changeFound = true;
				}
				if ($changeFound) {
					setCachedData("shopping_cart_ip_addresses", "shopping_cart_ip_addresses", $shoppingCartIpAddresses, 12);
				}
				if (count($shoppingCartIpAddresses[$shoppingCart->getShoppingCartId()]) > 3) {
					sleep(30);
					addProgramLog("Order unable to be completed because shopping cart ID " . $shoppingCart->getShoppingCartId() . " failed from multiple IP addresses" . "\n\n" . jsonEncode($this->cleanPostData()));
					$returnArray['error_message'] = "Charge failed: Transaction declined (1182)";
					$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
					ajaxResponse($returnArray);
					break;
				}

				$resultSet = executeQuery("select * from add_hashes where add_hash = ?", $_POST['_add_hash']);
				if ($row = getNextRow($resultSet)) {
					$returnArray['error_message'] = "This order has already been created.";
					// don't cancel Credova order in this case as the existing order may have been a successful Credova order.
					addProgramLog("Order unable to be completed because an error occurred: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
					ajaxResponse($returnArray);
					break;
				}
				if (!$GLOBALS['gLoggedIn'] && $shoppingCart->requiresUser()) {
					$returnArray['error_message'] = "Login is required to place this order.";
					$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
					addProgramLog("Order unable to be completed because an error occurred: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
					ajaxResponse($returnArray);
					break;
				}

				if (!empty($_POST['promotion_code'])) {
					$shoppingCart->applyPromotionCode($_POST['promotion_code']);
				}
				$shoppingCartItems = $shoppingCart->getShoppingCartItems(array("reset_sale_price" => (!$orderEntryCreated)));
				$enteredFFLId = $_POST['federal_firearms_licensee_id'];

				$productIds = array();
				$productCatalog = new ProductCatalog();
				$shippingRequired = false;
				$shippingState = false;
				$somePickupProducts = false;
				$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
				$crRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "CR_REQUIRED", "inactive = 0 and cannot_sell = 0");
				$fflRequired = false;
				$allFFLProductCRRequired = true;
				$fflNumber = CustomField::getCustomFieldData($contactId, "FFL_NUMBER");
				foreach ($shoppingCartItems as $index => $thisItem) {
					$productRow = ProductCatalog::getCachedProductRow($thisItem['product_id']);
					if (!empty($productRow['no_online_order'])) {
						$returnArray['error_message'] = "In-store purchase only products cannot be ordered online";
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
						$returnArray['recalculate'] = true;
						ajaxResponse($returnArray);
						break;
					}
					if ($fflRequiredProductTagId && empty($fflNumber)) {
						if (array_key_exists("product_tag_ids", $productRow) && is_array($productRow['product_tag_ids'])) {
							$productTagLinkId = in_array($fflRequiredProductTagId, $productRow['product_tag_ids']);
						} else {
							$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productRow['product_id'], "product_tag_id = ?", $fflRequiredProductTagId);
						}
						if (!empty($productTagLinkId)) {
							$fflRequired = true;
							if (array_key_exists("product_tag_ids", $productRow) && is_array($productRow['product_tag_ids'])) {
								$crRequiredProductTagLinkId = in_array($crRequiredProductTagId, $productRow['product_tag_ids']);
							} else {
								$crRequiredProductTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id",
									$productRow['product_id'], "product_tag_id = ?", $crRequiredProductTagId);
							}
							$allFFLProductCRRequired = $allFFLProductCRRequired && !empty($crRequiredProductTagLinkId);
						}
					}
					$shoppingCartItems[$index]['product_row'] = $productRow;
					$pickupOnly = CustomField::getCustomFieldData($thisItem['product_id'], "PICKUP_ONLY", "PRODUCTS");
					if (!empty($pickupOnly)) {
						$somePickupProducts = true;
					}
					if (empty($productRow['virtual_product']) && empty($pickupOnly)) {
						$shippingRequired = true;
					}

					$productIds[] = $thisItem['product_id'];
				}
				if (!$fflRequired) {
					$_POST['federal_firearms_licensee_id'] = "";
				}

				$noShippingRequired = getPreference("RETAIL_STORE_NO_SHIPPING");
				$onlyOnePayment = getPreference("RETAIL_STORE_ONLY_ONE_PAYMENT");
				$forcePaymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_id",
					getPreference("RETAIL_STORE_FORCE_PAYMENT_METHOD_ID", ($orderEntryCreated ? "" : $GLOBALS['gUserRow']['user_type']['user_type_code'])), "inactive = 0");
				$forcePaymentMethodId = $forcePaymentMethodId ?: getFieldFromId("payment_method_id", "payment_methods", "payment_method_id", getPreference("RETAIL_STORE_FORCE_PAYMENT_METHOD_ID"), "inactive = 0");

				if (!empty($forcePaymentMethodId)) {
					$onlyOnePayment = true;
				}
				$validatePaymentOnly = (getPreference("RETAIL_STORE_VALIDATE_PAYMENT_ONLY") && $noShippingRequired && $onlyOnePayment);
				$alwaysTokenizePaymentMethod = getPreference("RETAIL_STORE_ALWAYS_TOKENIZE_PAYMENT_METHOD");
				if ($shippingRequired) {
					if (!empty($noShippingRequired)) {
						$shippingRequired = false;
					}
				}
				$shippingMethodPickup = getFieldFromId("pickup", "shipping_methods", "shipping_method_id", $_POST['shipping_method_id']);
				$shippingMethodLocationId = getFieldFromId("location_id", "shipping_methods", "shipping_method_id", $_POST['shipping_method_id']);
				if (!empty($shippingMethodPickup) && !empty($shippingMethodLocationId)) {
					$federalFirearmsLicenseeId = false;
					$licenseLookup = getFieldFromId("license_lookup", "locations", "location_id", $shippingMethodLocationId);
					if (!empty($licenseLookup)) {
						$federalFirearmsLicenseeId = (new FFL(array("license_lookup" => $licenseLookup)))->getFieldData("federal_firearms_licensee_id");
					}
					if (empty($federalFirearmsLicenseeId)) {
						$federalFirearmsLicenseeId = getFieldFromId("federal_firearms_licensee_id", "ffl_locations", "location_id", $shippingMethodLocationId);
					}
					if (!empty($federalFirearmsLicenseeId)) {
						$_POST['federal_firearms_licensee_id'] = $federalFirearmsLicenseeId;
					}
				}
				if ($shippingRequired) {
					if (!empty($shippingMethodPickup)) {
						$shippingRequired = false;
					}
				}
				if ($shippingRequired && $GLOBALS['gInternalConnection']) {
					$shippingMethodCode = getFieldFromId("shipping_method_code", "shipping_methods", "shipping_method_id", $_POST['shipping_method_id']);
					if ($shippingMethodCode == "PICKUP") {
						$shippingRequired = false;
					}
				}
				if (!$shippingRequired) {
					$copyAddressFields = array("first_name", "last_name", "business_name", "address_1", "address_2", "city", "state", "postal_code", "country_id");
                    $billingAddressFields = array_filter($_POST, function ($value, $key) {
                        return !empty($value) && startsWith($key, "billing_");
                    }, ARRAY_FILTER_USE_BOTH);
                    foreach ($copyAddressFields as $fieldName) {
                        if (empty($_POST[$fieldName])) {
                            foreach ($billingAddressFields as $postKey => $postValue) {
                                if (startsWith($postKey, 'billing_' . $fieldName)) {
                                    $_POST[$fieldName] = $postValue;
                                    break;
                                }
                            }
                        }
					}
					if ($shippingMethodPickup && empty($noShippingRequired) && empty($shippingMethodLocationId)) {
						$fflRequired = false;
					}
				}
				if ($shippingRequired && $fflRequired && empty($_POST['federal_firearms_licensee_id']) && empty($_POST['ffl_dealer_not_found'])) {
					$returnArray['error_message'] = "An FFL must be selected to which to send the order";
					$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
					addProgramLog("Order unable to be completed because an FFL is not selected" . "\n\n" . jsonEncode($this->cleanPostData()));
					ajaxResponse($returnArray);
					break;
				}

				$inventoryCounts = $productCatalog->getInventoryCounts(true, $productIds);
				$inventoryDetailCounts = $productCatalog->getInventoryCounts(false, $productIds);
				$neverOutOfStock = getPreference("RETAIL_STORE_NEVER_OUT_OF_STOCK");
				$eventRegistrationProductTypeId = getFieldFromId("product_type_id", "product_types", "product_type_code", "EVENT_REGISTRATION");
				if (!$neverOutOfStock) {
					$outOfStock = false;
					foreach ($shoppingCartItems as $index => $thisItem) {
						$nonInventoryItem = getFieldFromId("non_inventory_item", "products", "product_id", $thisItem['product_id']);
						if (!empty($nonInventoryItem)) {
							$productTypeId = getFieldFromId("product_type_id", "products", "product_id", $thisItem['product_id']);
							if (!empty($eventRegistrationProductTypeId) && $productTypeId == $eventRegistrationProductTypeId) {
								$eventId = getFieldFromId("event_id", "events", "product_id", $thisItem['product_id']);
								$spotsLeft = 0;
								if (!empty($eventId)) {
									$attendeeCounts = Events::getAttendeeCounts($eventId);
									$spotsLeft = $attendeeCounts['attendees'] - $attendeeCounts['registrants'];
								}
								if ($thisItem['quantity'] > $spotsLeft) {
									$outOfStock = ($outOfStock === false ? "" : $outOfStock . ", ") . "'" . getFieldFromId("description", "products", "product_id",
											$thisItem['product_id']) . "' has " . ($spotsLeft == 0 ? "none" : "only " . $spotsLeft . " spots left");
								}
							}
							continue;
						}
						$inStockQuantity = (!array_key_exists($thisItem['product_id'], $inventoryCounts) ? 0 : $inventoryCounts[$thisItem['product_id']]);
						if ($thisItem['quantity'] > $inStockQuantity) {
							$outOfStock = ($outOfStock === false ? "" : $outOfStock . ", ") . "'" . getFieldFromId("description", "products", "product_id",
									$thisItem['product_id']) . "' has " . ($inStockQuantity == 0 ? "none" : "only " . $inStockQuantity . " in stock");
						}
					}
					if ($outOfStock !== false) {
						$returnArray['error_message'] = "Some products cannot be ordered: " . $outOfStock;
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
						$returnArray['recalculate'] = true;
						ajaxResponse($returnArray);
						break;
					}
				}

				if (!empty($_COOKIE['avmws'])) {
					$sourceId = getFieldFromId("source_id", "sources", "source_code", "AVANTLINK", "inactive = 0");
				}
				if (empty($sourceId)) {
					$sourceId = getFieldFromId("source_id", "sources", "source_id", $_POST['source_id'], "inactive = 0");
				}
				if (empty($sourceId)) {
					$sourceId = getFieldFromId("source_id", "sources", "source_id", $_COOKIE['source_id'], "inactive = 0");
				}
				if (empty($sourceId)) {
					$sourceId = getSourceFromReferer($_SERVER['HTTP_REFERER']);
				}
				if (empty($sourceId)) {
					$sourceId = getFieldFromId("source_id", "sources", "source_code", "WEBSITE");
				}
				$addressId = "";
				$confirmUserAccount = false;
				$randomCode = "";

                if (!empty($userId)) {

                    $contactFields = array("first_name", "last_name", "address_1", "address_2", "city", "state", "postal_code", "country_id", "email_address");
					$contactTable = new DataTable("contacts");
					$contactTable->setSaveOnlyPresent(true);
					$parameterArray = array();
					foreach ($contactFields as $fieldName) {
						if (!empty($_POST[$fieldName]) && empty($contactRow[$fieldName])) {
							$parameterArray[$fieldName] = $_POST[$fieldName];
                            $contactRow[$fieldName] = $_POST[$fieldName];
						}
					}
					if (!$contactTable->saveRecord(array("name_values" => $parameterArray, "primary_id" => $contactId))) {
						$returnArray['error_message'] = $contactTable->getErrorMessage();
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
						ajaxResponse($returnArray);
						break;
					}

					$addressRow = array();
					if (strlen($_POST['address_id']) == 0 || $_POST['address_id'] == -1) {
						if (!empty($_POST['address_1']) && !empty($_POST['city']) && ($_POST['address_1'] != $contactRow['address_1'] || $_POST['address_2'] != $contactRow['address_2'] ||
								$_POST['city'] != $contactRow['city'] || $_POST['state'] != $contactRow['state'] ||
								$_POST['postal_code'] != $contactRow['postal_code'] || $_POST['country_id'] != $contactRow['country_id'])) {
							$addressId = getFieldFromId("address_id", "addresses", "contact_id", $contactId, "address_1 is not null and city is not null and address_label <=> ? and address_1 <=> ? and address_2 <=> ? and city <=> ? and state <=> ? and postal_code <=> ? and country_id = ?",
								$_POST['address_label'], $_POST['address_1'], $_POST['address_2'], $_POST['city'], $_POST['state'], $_POST['postal_code'], $_POST['country_id']);
							if (empty($addressId)) {
								$insertSet = executeQuery("insert into addresses (contact_id,address_label,address_1,address_2,city,state,postal_code,country_id,version) values (?,?,?,?,?, ?,?,?,300)",
									$contactId, $_POST['address_label'], $_POST['address_1'], $_POST['address_2'], $_POST['city'], $_POST['state'], $_POST['postal_code'], $_POST['country_id']);
								if (!empty($insertSet['sql_error'])) {
									$returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
									$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
									addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
									ajaxResponse($returnArray);
									break;
								}
								$addressId = $insertSet['insert_id'];
							}
							$addressRow = getRowFromId("addresses", "address_id", $addressId);
						} else {
							$addressId = "";
							$addressRow = $contactRow;
						}
					} else {
						if (!empty($_POST['address_id'])) {
							$addressId = getFieldFromId("address_id", "addresses", "contact_id", $contactId, "address_1 is not null and city is not null and address_id = ?", $_POST['address_id']);
							$addressRow = getRowFromId("addresses", "address_id", $addressId);
						} else {
							$addressId = "";
							$contactFields = array("address_1", "address_2", "city", "state", "postal_code", "country_id");
							$contactTable = new DataTable("contacts");
							$contactTable->setSaveOnlyPresent(true);
							$parameterArray = array();
							foreach ($contactFields as $fieldName) {
								$parameterArray[$fieldName] = $_POST[$fieldName];
							}
							if (!$contactTable->saveRecord(array("name_values" => $parameterArray, "primary_id" => $contactId))) {
								$returnArray['error_message'] = $contactTable->getErrorMessage();
								$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
								addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
								ajaxResponse($returnArray);
								break;
							}
							$addressRow = Contact::getContact($contactId);
						}
					}
					if ($shippingRequired && (empty($addressRow['address_1']) || empty($addressRow['city']))) {
						$returnArray['error_message'] = "No Shipping Address specified";
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
						ajaxResponse($returnArray);
						break;
					}
				} else {
					$contactId = $shoppingCart->getContact();
					$addressBlacklistId = getFieldFromId("address_blacklist_id", "address_blacklist", "postal_code", $_POST['postal_code'], "city = ? and instr(?,address_1) > 0",
						$_POST['city'], $_POST['address_1']);
					if (!empty($addressBlacklistId)) {
						sleep(30);
						$returnArray['error_message'] = "Charge failed: Transaction declined (8639)";
						addProgramLog("Order unable to be completed because mailing address matched blacklist.\n\n" . jsonEncode($this->cleanPostData()));
						ajaxResponse($returnArray);
					}
					$addressBlacklistId = getFieldFromId("address_blacklist_id", "address_blacklist", "postal_code", $_POST['billing_address_postal_code_1'], "city = ? and instr(?,address_1) > 0",
						$_POST['billing_address_city_1'], $_POST['billing_address_address_1_1']);
					if (!empty($addressBlacklistId)) {
						sleep(30);
						$returnArray['error_message'] = "Charge failed: Transaction declined (8637)";
						addProgramLog("Order unable to be completed because billing address matched blacklist.\n\n" . jsonEncode($this->cleanPostData()));
						ajaxResponse($returnArray);
					}

					$addressId = "";
					$addressRow = array();
					if (!empty($_POST['address_id'])) {
						$addressId = getFieldFromId("address_id", "addresses", "contact_id", $contactId, "address_1 is not null and city is not null and address_id = ?", $_POST['address_id']);
						$addressRow = getRowFromId("addresses", "address_id", $addressId);
					}
					if (empty($contactId)) {
						$resultSet = executeQuery("select * from contacts where client_id = ? and email_address = ? and contact_id not in (select contact_id from accounts) and " .
							"contact_id not in (select contact_id from donations) and contact_id not in (select contact_id from orders) and contact_id not in (select contact_id from users)", $GLOBALS['gClientId'], $_POST['email_address']);
						if ($row = getNextRow($resultSet)) {
							$contactId = $row['contact_id'];
							$shoppingCart->setValues(array("contact_id" => $contactId));
						}
					}
					if (empty($contactId)) {
						if (empty($_POST['first_name']) || empty($_POST['last_name']) || empty($_POST['email_address'])) {
							$returnArray['error_message'] = "Name & email are required";
							$returnArray['reload_page'] = true;
							ajaxResponse($returnArray);
							break;
						}
						$contactDataTable = new DataTable("contacts");
						if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'],
							"business_name" => $_POST['business_name'], "address_1" => $_POST['address_1'], "address_2" => $_POST['address_2'], "city" => $_POST['city'], "state" => $_POST['state'],
							"postal_code" => $_POST['postal_code'], "email_address" => $_POST['email_address'], "country_id" => $_POST['country_id'], "source_id" => $sourceId)))) {
							$returnArray['error_message'] = $contactDataTable->getErrorMessage();
							$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
							addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
							ajaxResponse($returnArray);
							break;
						}
						$shoppingCart->setValues(array("contact_id" => $contactId));
					} else {
						$contactFields = array("first_name", "last_name", "business_name", "address_1", "address_2", "city", "state", "postal_code", "country_id", "email_address");
						$contactTable = new DataTable("contacts");
						$contactTable->setSaveOnlyPresent(true);
						$parameterArray = array();
						foreach ($contactFields as $fieldName) {
							if (!empty($_POST[$fieldName])) {
								$parameterArray[$fieldName] = $_POST[$fieldName];
							}
						}
						if (!$contactTable->saveRecord(array("name_values" => $parameterArray, "primary_id" => $contactId))) {
							$returnArray['error_message'] = $contactTable->getErrorMessage();
							$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
							addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
							ajaxResponse($returnArray);
							break;
						}
					}
					if (empty($addressRow) && $_POST['address_id'] == -1) {
						$addressRow = Contact::getContact($contactId);
						if (empty($addressRow['address_1']) || empty($addressRow['city'])) {
							$addressRow = array();
						}
					}
					if ($shippingRequired && (empty($addressRow['address_1']) || empty($addressRow['city'])) && (empty($_POST['address_1']) || empty($_POST['city']))) {
						$returnArray['error_message'] = "No Shipping Address specified";
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
						ajaxResponse($returnArray);
						break;
					}
					if (!empty($_POST['password'])) {
						$resultSet = executeQuery("select count(*) from users where client_id = ? and inactive = 1 and contact_id in (select contact_id from contacts where email_address = ?)",
							$GLOBALS['gClientId'], $_POST['email_address']);
						if ($row = getNextRow($resultSet)) {
							if ($row['count(*)'] > 0) {
								$returnArray['error_message'] = "Unable to create user account";
								$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
								addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
								ajaxResponse($returnArray);
								break;
							}
						}
						$userName = $_POST['user_name'];
						if (empty($userName)) {
							$userName = $_POST['email_address'];
						}

						$resultSet = executeQuery("select user_id from users where contact_id in (select contact_id from contacts where client_id = ? and email_address = ? and " .
							"contact_id in (select contact_id from users)) and client_id = ?", $GLOBALS['gClientId'], $_POST['email_address'], $GLOBALS['gClientId']);
						if ($row = getNextRow($resultSet)) {
							$returnArray['error_message'] = "User account already exists with this email address.";
							$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
							addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
							ajaxResponse($returnArray);
							break;
						}
						$userName = makeCode($userName, array("lowercase" => true));
						$resultSet = executeQuery("select * from users where user_name = ? and client_id = ?", $userName, $GLOBALS['gClientId']);
						if ($row = getNextRow($resultSet)) {
							$returnArray['error_message'] = "User name is already taken. Please select another.";
							$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
							addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
							ajaxResponse($returnArray);
							break;
						}
						$passwordSalt = getRandomString(64);
						$password = hash("sha256", $passwordSalt . $_POST['password']);
						$checkUserId = getFieldFromId("user_id", "users", "user_name", $userName, "client_id = ? or superuser_flag = 1", $GLOBALS['gClientId']);
						if (!empty($checkUserId)) {
							$returnArray['error_message'] = "User name is unavailable. Choose another";
							$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
							addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
							ajaxResponse($returnArray);
							break;
						}
						$confirmUserAccount = getPreference("CONFIRM_USER_ACCOUNT");

						$usersTable = new DataTable("users");
						if (!$userId = $usersTable->saveRecord(array("name_values" => array("client_id" => $GLOBALS['gClientId'], "contact_id" => $contactId, "user_name" => $userName,
							"password_salt" => $passwordSalt, "password" => $password, "date_created" => date("Y-m-d H:i:s"))))) {
							$returnArray['error_message'] = $usersTable->getErrorMessage();
							$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
							addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
							ajaxResponse($returnArray);
							break;
						}
						if (!empty($confirmUserAccount)) {
							$randomCode = getRandomString(6, "23456789ABCDEFGHJKLMNPQRSTUVWXYZ");
							executeQuery("update users set verification_code = ?,locked = 1 where user_id = ?", $randomCode, $userId);
						}
						$password = hash("sha256", $userId . $passwordSalt . $_POST['password']);
						executeQuery("insert into user_passwords (user_id,password_salt,password) values (?,?,?)", $userId, $passwordSalt, $password);
						$resultSet = executeQuery("update users set password = ?,last_password_change = now() where user_id = ?", $password, $userId);
						if (!empty($resultSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
							$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
							addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
							ajaxResponse($returnArray);
							break;
						}
						makeWebUserContact($contactId);
						login($userId);
						$shoppingCart->setValues(array("user_id" => $userId));
						$emailId = getFieldFromId("email_id", "emails", "email_code", "NEW_ACCOUNT", "inactive = 0");
						if (!empty($emailId)) {
							$substitutions = $_POST;
							unset($substitutions['password']);
							unset($substitutions['password_again']);
							sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_address" => $_POST['email_address'], "contact_id" => $contactId));
						}
						sendEmail(array("subject" => "User Account Created", "body" => "User account '" . $_POST['user_name'] . "' for contact " . getDisplayName($contactId) . " was created.", "email_address" => getNotificationEmails("USER_MANAGEMENT")));
					}
				}
				if (empty($contactId)) {
					$returnArray['error_message'] = "Error creating order. Please try again";
					$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
					addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
					ajaxResponse($returnArray);
					break;
				}

				# Make sure pickup state is the same as the contact's state

				if ($fflRequired && $shippingMethodPickup && !empty($shippingMethodLocationId) && empty(getPreference("ALLOW_OUT_OF_STATE_FFL_PICKUP"))) {
					$contactState = strtolower(getFieldFromId("state", "contacts", "contact_id", $contactId));
					$pickupState = strtolower(getFieldFromId("state", "contacts", "contact_id", getFieldFromId("contact_id", "locations", "location_id", $shippingMethodLocationId)));
					$stateArray = getStateArray();
					$fullStateName = strtolower($stateArray[strtoupper($pickupState)]);
					if (!empty($contactState) && !empty($pickupState) && $contactState != $pickupState && $contactState != $fullStateName) {
						$returnArray['error_message'] = "Pickup is only allowed in your state of residence for firearms.";
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
						ajaxResponse($returnArray);
						break;
					}
				}

				if (!empty($_POST['phone_number'])) {
					$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "phone_number", $_POST['phone_number'], "contact_id = ?", $contactId);
					if (empty($phoneNumberId)) {
						$phoneDescription = (empty($_POST['receive_text_notifications']) ? "primary" : "cell");
						executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,?)", $contactId, $_POST['phone_number'], $phoneDescription);
					}
				}
				if (!empty($_POST["cell_phone_number"])) {
					$resultSet = executeQuery("select * from phone_numbers where contact_id = ? and description = 'cell'", $contactId);
					if ($row = getNextRow($resultSet)) {
						if ($_POST["cell_phone_number"] != $row['phone_number']) {
							executeQuery("update phone_numbers set phone_number = ? where phone_number_id = ?",
								$_POST["cell_phone_number"], $row['phone_number_id']);
							$phoneUpdated = true;
						}
					} else {
						executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,'cell')",
							$contactId, $_POST["cell_phone_number"]);
						$phoneUpdated = true;
					}
					$customFieldId = CustomField::getCustomFieldIdFromCode("RECEIVE_SMS");
					if (empty($customFieldId)) {
						$customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "CONTACTS");
						$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
							$GLOBALS['gClientId'], "RECEIVE_SMS", "Receive Text Notifications", $customFieldTypeId, "Receive Text Notifications");
						$customFieldId = $insertSet['insert_id'];
						executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,?,?)", $customFieldId, "data_type", "tinyint");
					}
					CustomField::setCustomFieldData($contactId, "RECEIVE_SMS", 'true');
					if (empty($_POST['phone_number'])) {
						$_POST['phone_number'] = $_POST['cell_phone_number'];
					}
				}

				if ($GLOBALS['gInternalConnection'] && !empty($_POST['tax_exempt_id'])) {
					CustomField::setCustomFieldData($contactId, "TAX_EXEMPT_ID", $_POST['tax_exempt_id']);
				}
				if (!empty($_POST['public_identifier']) && !empty($_POST['authentication_token'])) {
					$credovaLoanId = getFieldFromId("credova_loan_id", "credova_loans", "public_identifier", $_POST['public_identifier']);
					if (empty($credovaLoanId)) {
						executeQuery("insert into credova_loans (contact_id,public_identifier,authentication_token) values (?,?,?)", $contactId, $_POST['public_identifier'], $_POST['authentication_token']);
					}
				}

				$contactRow = Contact::getContact($contactId);
				if (empty($contactRow['address_1']) || empty($contactRow['city'])) {
					if (!empty($addressRow['address_1']) && !empty($addressRow['city'])) {
						executeQuery("update contacts set address_1 = ?,address_2 = ?,city = ?,state = ?,postal_code = ? where contact_id = ?", $addressRow['address_1'],
							$addressRow['address_2'], $addressRow['city'], $addressRow['state'], $addressRow['postal_code'], $contactId);
						$contactRow['address_1'] = $addressRow['address_1'];
						$contactRow['address_2'] = $addressRow['address_2'];
						$contactRow['city'] = $addressRow['city'];
						$contactRow['state'] = $addressRow['state'];
						$contactRow['postal_code'] = $addressRow['postal_code'];
					}
				}

				if (empty($addressId) && $_POST['address_id'] != -1 && !empty($_POST['address_1']) && !empty($_POST['city'])) {
					$addressId = getFieldFromId("address_id", "addresses", "contact_id", $contactId, "address_1 is not null and city is not null and address_1 <=> ? and address_2 <=> ? and city <=> ? and state <=> ? and postal_code <=> ? and country_id = ?",
						$_POST['address_1'], $_POST['address_2'], $_POST['city'], $_POST['state'], $_POST['postal_code'], $_POST['country_id']);
					if (empty($addressId)) {
						$insertSet = executeQuery("insert into addresses (contact_id,address_label,address_1,address_2,city,state,postal_code,country_id,version) values (?,?,?,?,?, ?,?,?,400)",
							$contactId, $_POST['address_label'], $_POST['address_1'], $_POST['address_2'], $_POST['city'], $_POST['state'], $_POST['postal_code'], $_POST['country_id']);
						if (!empty($insertSet['sql_error'])) {
							$returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
							$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
							addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
							ajaxResponse($returnArray);
							break;
						}
						$addressId = $insertSet['insert_id'];
					}
				}
				$shippingAddress = (empty($addressId) ? $contactRow : array_merge($contactRow, array_filter(getRowFromId("addresses", "address_id", $addressId))));
				if (empty($shippingAddress['address_1']) || empty($shippingAddress['city'])) {
					$foundBillingAddressIndex = 0;
					for ($x = 1; $x < 10; $x++) {
						if (!empty($_POST['billing_address_1_' . $x])) {
							$foundBillingAddressIndex = $x;
							break;
						}
					}
					if ($foundBillingAddressIndex) {
						$addressFields = array("address_1", "address_2", "city", "state", "postal_code", "country_id");
						foreach ($addressFields as $fieldName) {
							$shippingAddress[$fieldName] = $_POST['billing_' . $fieldName . "_" . $foundBillingAddressIndex];
						}
					}
				}
				$shippingState = $shippingAddress['state'];
				$addressBlacklistId = getFieldFromId("address_blacklist_id", "address_blacklist", "postal_code", $shippingAddress['postal_code'], "city = ? and instr(?,address_1) > 0",
					$shippingAddress['city'], $shippingAddress['address_1']);
				if (!empty($addressBlacklistId)) {
					sleep(30);
					$returnArray['error_message'] = "Charge failed: Transaction declined (8636)";
					addProgramLog("Order unable to be completed because shipping address matched blacklist.\n\n" . jsonEncode($this->cleanPostData()));
					ajaxResponse($returnArray);
				}

				if (!$fflRequired && empty($_POST['federal_firearms_licensee_id']) && !empty($shippingState)) {
					$fflRequiredStateProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED_" . $shippingState, "inactive = 0 and cannot_sell = 0");
					if (!empty($fflRequiredStateProductTagId)) {
						foreach ($shoppingCartItems as $thisItem) {
							$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $thisItem['product_id'], "product_tag_id = ?", $fflRequiredStateProductTagId);
							if (!empty($productTagLinkId)) {
								$fflRequired = true;
								break;
							}
						}
					}
					if ($fflRequired) {
						$_POST['federal_firearms_licensee_id'] = $enteredFFLId;
					}
				}

				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("mailing_list_id_")) == "mailing_list_id_") {
						$mailingListId = getFieldFromId("mailing_list_id", "mailing_lists", "mailing_list_id", substr($fieldName, strlen("mailing_list_id_")));
						if (!empty($mailingListId)) {
							$mailingListRow = getRowFromId("contact_mailing_lists", "mailing_list_id", $mailingListId, "contact_id = ?", $contactId);
							if (!empty($mailingListRow)) {
								if (!empty($fieldData)) {
									if (!empty($mailingListRow['date_opted_out'])) {
										$contactMailingListSource = new DataSource("contact_mailing_lists");
										$contactMailingListSource->saveRecord(array("name_values" => array("date_opted_in" => date("Y-m-d"), "ip_address" => $_SERVER['REMOTE_ADDR'], "date_opted_out" => ""), "primary_id" => $mailingListRow['contact_mailing_list_id']));
										addActivityLog("Opted in to mailing list '" . getFieldFromId("description", "mailing_lists", "mailing_list_id", $mailingListId) . "'");
									}
								} else {
									if (empty($mailingListRow['date_opted_out'])) {
										$contactMailingListSource = new DataSource("contact_mailing_lists");
										$contactMailingListSource->saveRecord(array("name_values" => array("date_opted_out" => date("Y-m-d")), "primary_id" => $mailingListRow['contact_mailing_list_id']));
										executeQuery("update contact_mailing_lists set date_opted_out = now() where contact_mailing_list_id = ?",
											$mailingListRow['contact_mailing_list_id']);
										addActivityLog("Opted out of mailing list '" . getFieldFromId("description", "mailing_lists", "mailing_list_id", $mailingListId) . "'");
									}
								}
							} else {
								if (!empty($fieldData)) {
									$contactMailingListSource = new DataSource("contact_mailing_lists");
									$contactMailingListSource->saveRecord(array("name_values" => array("date_opted_in" => date("Y-m-d"), "ip_address" => $_SERVER['REMOTE_ADDR'], "contact_id" => $contactId, "mailing_list_id" => $mailingListId)));
									addActivityLog("Opted in to mailing list '" . getFieldFromId("description", "mailing_lists", "mailing_list_id", $mailingListId) . "'");
								}
							}
						}
					}
				}

				$saveAddressFields = array("first_name", "last_name", "business_name", "address_1", "address_2", "city", "state", "postal_code", "country_id");
				$primaryAccountId = "";
				$primaryPaymentMethodId = "";
				$credovaPaymentAmount = 0;
				$otherPaymentsAmount = 0;

				# Get valid payment methods

				$validPaymentMethods = array();
				$invalidPaymentMethods = array();
				$giftCardExists = false;
				$class3Exists = false;

				foreach ($shoppingCartItems as $index => $thisItem) {
					if (!empty($credovaPaymentMethodId) && !$class3Exists && !empty($class3ProductTagId)) {
						$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $thisItem['product_id'], "product_tag_id = ?", $class3ProductTagId);
						if (!empty($productTagLinkId)) {
							$class3Exists = true;
						}
					}
					$productTypeCode = getFieldFromId("product_type_code", "product_types", "product_type_id", getFieldFromId("product_type_id", "products", "product_id", $thisItem['product_id']));
					if ($productTypeCode == "GIFT_CARD") {
						if (!$giftCardExists) {
                            $skipPaymentMethods = "'CREDIT_CARD','CREDOVA'" . ($GLOBALS['gInternalConnection'] || $GLOBALS['gDevelopmentServer'] ? ",'CASH','CHECK'" : "");
							$resultSet = executeQuery(sprintf("select * from payment_methods where client_id = ? and payment_method_type_id not in 
                                (select payment_method_type_id from payment_method_types where payment_method_type_code in (%s))", $skipPaymentMethods), $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
								if (!in_array($row['payment_method_id'], $invalidPaymentMethods) && $row['payment_method_id'] != $credovaPaymentMethodId) {
									$invalidPaymentMethods[] = $row['payment_method_id'];
								}
							}
							$giftCardExists = true;
						}
					}

					$paymentMethods = array();
					$resultSet = executeQuery("select product_id,group_concat(payment_method_id) from product_payment_methods where product_id = ?", $thisItem['product_id']);
					if ($row = getNextRow($resultSet)) {
						$paymentMethods = array_filter(explode(",", $row['group_concat(payment_method_id)']));
						if (!empty($paymentMethods)) {
							if (empty($validPaymentMethods)) {
								$validPaymentMethods = $paymentMethods;
							} else {
								$validPaymentMethods = array_intersect($validPaymentMethods, $paymentMethods);
							}
						}
					}

					$resultSet = executeQuery("select * from product_tag_payment_methods where product_tag_id in (select product_tag_id from product_tag_links where product_id = ?)", $thisItem['product_id']);
					while ($row = getNextRow($resultSet)) {
						if (!in_array($row['payment_method_id'], $invalidPaymentMethods)) {
							$invalidPaymentMethods[] = $row['payment_method_id'];
						}
					}
				}
				if (!empty($credovaPaymentMethodId) && $class3Exists) {
					if (empty($validPaymentMethods)) {
						$resultSet = executeQuery("select * from payment_methods where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and payment_method_code <> 'CREDOVA'", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							$validPaymentMethods[] = $row['payment_method_id'];
						}
					} else {
						foreach ($validPaymentMethods as $index => $thisPaymentMethod) {
							if ($thisPaymentMethod == $credovaPaymentMethodId) {
								unset($validPaymentMethods[$index]);
							}
						}
					}
				}
				if (empty($validPaymentMethods)) {
					$resultSet = executeQuery("select * from payment_methods where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"), $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$validPaymentMethods[] = $row['payment_method_id'];
					}
				}

				$uniqueValidPaymentMethods = array();
				foreach ($validPaymentMethods as $thisPaymentMethodId) {
					if (!empty($thisPaymentMethodId) && !in_array($thisPaymentMethodId, $uniqueValidPaymentMethods) && !in_array($thisPaymentMethodId, $invalidPaymentMethods)) {
						$uniqueValidPaymentMethods[] = $thisPaymentMethodId;
					}
				}

				if ($onlyOnePayment) {
					$lowestPaymentNumber = 0;
					foreach ($_POST as $fieldName => $fieldValue) {
						if (substr($fieldName, 0, strlen("payment_method_number_")) == "payment_method_number_") {
							$paymentMethodNumber = substr($fieldName, strlen("payment_method_number_"));
							if (empty($lowestPaymentNumber) || $paymentMethodNumber < $lowestPaymentNumber) {
								$lowestPaymentNumber = $paymentMethodNumber;
							}
						}
					}
					foreach ($_POST as $fieldName => $fieldValue) {
						if (substr($fieldName, 0, strlen("payment_method_number_")) == "payment_method_number_") {
							$paymentMethodNumber = substr($fieldName, strlen("payment_method_number_"));
							if ($paymentMethodNumber != $lowestPaymentNumber) {
								unset($_POST['payment_method_number_' . $paymentMethodNumber]);
								unset($_POST['primary_payment_method_' . $paymentMethodNumber]);
							}
						}
					}
					$_POST['primary_payment_method_1'] = true;
				}

				$paymentMethodArray = array();
				$paymentMethodFields = array("account_id", "payment_method_id", "account_number", "expiration_month", "expiration_year", "cvv_code", "routing_number",
					"bank_account_number", "gift_card_number", "gift_card_pin", "loan_number", "lease_number", "account_label", "same_address", "billing_first_name", "billing_last_name",
					"billing_business_name", "billing_address_1", "billing_address_2", "billing_city", "billing_state", "billing_postal_code",
					"billing_country_id", "primary_payment_method", "payment_amount", "payment_time", "reference_number", "default_payment_method", "default_billing_address", "billing_address_id");

				if (empty($forcePaymentMethodId)) {
					foreach ($_POST as $fieldName => $fieldValue) {
						if (startsWith($fieldName, "payment_method_number_")) {
							$paymentMethodNumber = substr($fieldName, strlen("payment_method_number_"));
							$thisPaymentMethod = array();
							foreach ($paymentMethodFields as $thisField) {
								$thisPaymentMethod[$thisField] = $_POST[$thisField . "_" . $paymentMethodNumber];
							}
							if (empty($thisPaymentMethod['payment_method_id']) && $paymentMethodNumber == 1) {
								$thisPaymentMethod['payment_method_id'] = $_POST['payment_method_id'];
							}
							if (empty($thisPaymentMethod['payment_method_id']) && empty($thisPaymentMethod['account_id'])) {
								continue;
							}
							if (empty($_POST['primary_payment_method_' . $paymentMethodNumber]) && (!is_numeric($thisPaymentMethod['payment_amount']) || (strlen($thisPaymentMethod['payment_amount']) > 0 && $thisPaymentMethod['payment_amount'] <= 0))) {
								continue;
							}
							if (!empty($credovaPaymentMethodId) && $thisPaymentMethod['payment_method_id'] == $credovaPaymentMethodId) {
								$credovaPaymentExists = true;
								$credovaPaymentAmount = $thisPaymentMethod['payment_amount'];
							} else {
								if (empty($_POST['primary_payment_method_' . $paymentMethodNumber])) {
									$otherPaymentsAmount += $thisPaymentMethod['payment_amount'];
								}
							}
							if (!empty($_POST['primary_payment_method_' . $paymentMethodNumber])) {
								if (empty($primaryPaymentMethodId)) {
									$primaryPaymentMethodId = $_POST['payment_method_id_' . $paymentMethodNumber];
									$primaryAccountId = $_POST['account_id_' . $paymentMethodNumber];
								} else {
									$returnArray['error_message'] = "More than one payment method set as primary";
									$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
									addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
									ajaxResponse($returnArray);
									break;
								}
							}
							$forceSameAddress = getPreference("FORCE_SAME_BILLING_SHIPPING") && empty(CustomField::getCustomFieldData($contactId, "ALLOW_DIFFERENT_SHIPPING_ADDRESS"));
							if (!empty($forceSameAddress)) {
								$_POST['same_address_' . $paymentMethodNumber] = 1;
							}

							if (!empty($_POST['same_address_' . $paymentMethodNumber])) {
								foreach ($saveAddressFields as $thisField) {
									$thisPaymentMethod['billing_' . $thisField] = $shippingAddress[$thisField];
									if (empty($thisPaymentMethod['billing_' . $thisField])) {
										$thisPaymentMethod['billing_' . $thisField] = $contactRow[$thisField];
									}
								}
							}
							$paymentMethodArray[] = $thisPaymentMethod;
						}
					}
					if (count($paymentMethodArray) == 1) {
						$paymentMethodArray[0]['payment_amount'] = "";
					}
				} else {
					$thisPaymentMethod = array();
					foreach ($paymentMethodFields as $thisField) {
						$thisPaymentMethod[$thisField] = $_POST[$thisField . "_" . $paymentMethodNumber];
					}
					$thisPaymentMethod['payment_method_id'] = $forcePaymentMethodId;
					$primaryPaymentMethodId = $forcePaymentMethodId;
					foreach ($saveAddressFields as $thisField) {
						$thisPaymentMethod['billing_' . $thisField] = $shippingAddress[$thisField];
						if (empty($thisPaymentMethod['billing_' . $thisField])) {
							$thisPaymentMethod['billing_' . $thisField] = $contactRow[$thisField];
						}
					}
					$paymentMethodArray[] = $thisPaymentMethod;
				}

# Round numbers to 2 digits to deal with possible javascript issues

				$roundParameters = array("tax_charge", "shipping_charge", "handling_charge", "cart_total", "order_total");
				foreach ($roundParameters as $parameterName) {
					$parameterValue = floatval(str_replace(",", "", $_POST[$parameterName]));
					$_POST[$parameterName] = number_format($parameterValue, 2, ".", "");
				}

				if ($primaryPaymentMethodId == $credovaPaymentMethodId) {
					$credovaPaymentAmount = $_POST['order_total'] - $otherPaymentsAmount;
				}

				$credovaLoanRow = array();
				if ($credovaPaymentExists) {
					$credovaLoanRow = getRowFromId("credova_loans", "contact_id", $contactId, "order_id is null and public_identifier = ? and authentication_token = ?", $_POST['public_identifier'], $_POST['authentication_token']);
					if (empty($credovaLoanRow) || empty($credovaUserName) || empty($credovaPassword)) {
						$returnArray['error_message'] = "No Credova financing options exist";
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
						ajaxResponse($returnArray);
						break;
					}

					$headers = array(
						"Content-Type: application/json",
						"Authorization: Bearer " . $credovaLoanRow['authentication_token']
					);
					$ch = curl_init();

					$statusUrl = "https://" . ($credovaTest ? "sandbox-" : "") . "lending-api.credova.com/v2/applications/" . urlencode($_POST['public_identifier']) . "/status";
					curl_setopt($ch, CURLOPT_URL, $statusUrl);
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
					curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

					$response = curl_exec($ch);
					$decoded = json_decode($response, TRUE);
					$decoded['credova_payment_amount'] = $credovaPaymentAmount;
#                    addProgramLog("Credova Status: " . jsonEncode($decoded));

					if (empty($decoded['applicationId']) || empty($decoded['approvalAmount']) || ($decoded['approvalAmount'] - $credovaPaymentAmount) < 0) {
						$returnArray['error_message'] = "Credova payment exceeds approved amount: Approval amount of " . number_format($decoded['approvalAmount'], 2) . ", but " . number_format($credovaPaymentAmount, 2) . " being charged.";
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
						ajaxResponse($returnArray);
						break;
					}
				}

				if (empty($primaryPaymentMethodId) && !empty($primaryAccountId)) {
					$primaryPaymentMethodId = getFieldFromId("payment_method_id", "accounts", "account_id", $primaryAccountId, "contact_id = ?", $contactId);
				}
				usort($paymentMethodArray, array($this, "paymentMethodSort"));

				$eCommerceRequired = (!$GLOBALS['gDevelopmentServer'] && !$orderEntryCreated);
				if (!$eCommerceRequired) {
					foreach ($paymentMethodArray as $thisPaymentMethod) {
						$paymentMethodRow = getRowFromId("payment_methods", "payment_method_id", $thisPaymentMethod['payment_method_id']);
						$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", $paymentMethodRow['payment_method_type_id']);
						if (in_array($paymentMethodTypeCode, array("CREDIT_CARD", "BANK_ACCOUNT")) || !empty($thisPaymentMethod['account_id'])) {
							$eCommerceRequired = true;
						}
					}
				}

				if (empty($contactRow['address_1']) || empty($contactRow['city'])) {
					foreach ($paymentMethodArray as $thisPaymentMethod) {
						if (!empty($thisPaymentMethod['billing_address_1']) && !empty($thisPaymentMethod['billing_city'])) {
							executeQuery("update contacts set address_1 = ?,address_2 = ?,city = ?,state = ?,postal_code = ? where contact_id = ?", $thisPaymentMethod['billing_address_1'],
								$thisPaymentMethod['billing_address_2'], $thisPaymentMethod['billing_city'], $thisPaymentMethod['billing_state'], $thisPaymentMethod['billing_postal_code'], $contactId);
							$contactRow['address_1'] = $thisPaymentMethod['billing_address_1'];
							$contactRow['address_2'] = $thisPaymentMethod['billing_address_2'];
							$contactRow['city'] = $thisPaymentMethod['billing_city'];
							$contactRow['state'] = $thisPaymentMethod['billing_state'];
							$contactRow['postal_code'] = $thisPaymentMethod['billing_postal_code'];
							break;
						}
					}
				}

# if shipping is turned off, use the ffl dealer address

				$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $_POST['shipping_method_id']);

				$fflRow = array();
				if ($fflRequired) {
					$fflRow = (new FFL(array("federal_firearms_licensee_id" => $_POST['federal_firearms_licensee_id'], "only_if_valid" => true)))->getFFLRow();
				}

				if ($orderEntryCreated) {
					$shippingCharge = $_POST['shipping_charge'];
				} else {
					$shippingCharge = false;
					$shippingMethods = $shoppingCart->getShippingOptions($_POST['country_id'], $_POST['state'], $_POST['postal_code']);

					if ($shippingMethods === false) {
						$returnArray['error_message'] = $shoppingCart->getErrorMessage();
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
						ajaxResponse($returnArray);
						break;
					} else {
						foreach ($shippingMethods as $thisShippingMethod) {
							$returnArray['shipping_option_' . $thisShippingMethod['shipping_method_id']] = $thisShippingMethod;
						}
						if ($noShippingRequired) {
							$_POST['shipping_charge'] = $shippingCharge = 0;
						} else {
							foreach ($shippingMethods as $thisShippingMethod) {
								if ($thisShippingMethod['shipping_method_id'] == $_POST['shipping_method_id']) {
									$shippingCharge = $thisShippingMethod['shipping_charge'];
								}
							}
							if ($shippingCharge === false) {
								$returnArray['error_message'] = "No matching shipping method found for " . $_POST['state'] . ", " . $_POST['postal_code'];
								$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
								addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
								ajaxResponse($returnArray);
								break;
							}
						}
					}
				}
				if (!$shippingRequired) {
					$addressId = "";
				}

				$cartTotal = 0;
				$recurringProduct = false;
				$productAdditionalCharges = array();
				$cartTotalQuantity = 0;
				foreach ($shoppingCartItems as $index => $thisItem) {
					$cartTotalQuantity += $thisItem['quantity'];
					$additionalCharges = $_POST['shopping_cart_item_additional_charges_' . $thisItem['shopping_cart_item_id']];
					if (empty($additionalCharges)) {
						$additionalCharges = 0;
					}
					if ($additionalCharges > 0) {
						$shoppingCart->addAdditionalCharges($thisItem['shopping_cart_item_id'], $additionalCharges);
						$productAdditionalCharges[$thisItem['product_id']] = $additionalCharges;
					}
					$cartTotal += round($thisItem['quantity'] * ($additionalCharges + $thisItem['sale_price']), 2);
					if (is_array($thisItem['product_addons'])) {
						foreach ($thisItem['product_addons'] as $thisAddon) {
							$quantity = $thisAddon['quantity'];
							if (empty($quantity) || !is_numeric($quantity) || $quantity <= 0) {
								$quantity = 1;
							}
							$cartTotal += $thisAddon['sale_price'] * $quantity * $thisItem['quantity'];
						}
					}
					if (!$recurringProduct) {
						$checkRecurringProductIds = array($thisItem['product_id']);
						$packResult = executeQuery("select * from product_pack_contents where product_id = ?", $thisItem['product_id']);
						while ($packRow = getNextRow($packResult)) {
							$checkRecurringProductIds[] = $packRow['contains_product_id'];
						}
						foreach ($checkRecurringProductIds as $checkRecurringProductId) {
							$recurringProduct = $recurringProduct ?: getFieldFromId("recurring_payment_type_id", "subscription_products", "setup_product_id", $checkRecurringProductId);
							$recurringProduct = $recurringProduct ?: getFieldFromId("recurring_payment_type_id", "subscription_products", "product_id", $checkRecurringProductId);
						}
					}
				}
				$additionalPaymentHandlingCharge = getPreference("RETAIL_STORE_ADDITIONAL_PAYMENT_METHOD_HANDLING_CHARGE");
				if (empty($additionalPaymentHandlingCharge)) {
					$additionalPaymentHandlingCharge = 0;
				}

				if (!$orderEntryCreated && round($shippingCharge - $_POST['shipping_charge'], 2) != 0 || round($cartTotalQuantity - $_POST['cart_total_quantity'], 2) != 0 ||
					round($cartTotal - $_POST['cart_total'], 2) != 0) {
					$returnArray['error_message'] = "Some prices may have changed. Please refresh the page.";
					$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
					$returnArray['console'] = "Shipping: " . $shippingCharge . " : " . $_POST['shipping_charge'] . "\n" .
						"Cart Quantity: " . $cartTotalQuantity . " : " . $_POST['cart_total_quantity'] . "\n" .
						"Cart Total: " . $cartTotal . " : " . $_POST['cart_total'];
					addProgramLog("price problem: " . $returnArray['console']);
					$returnArray['recalculate'] = true;
					ajaxResponse($returnArray);
					break;
				}

				$userTypeId = ($orderEntryCreated ? getFieldFromId("user_type_id", "users", "contact_id", $contactId) : $GLOBALS['gUserRow']['user_type_id']);
				$resultSet = executeQuery("select * from loyalty_programs where client_id = ? and (user_type_id = ? or user_type_id is null) and inactive = 0 and " .
					"internal_use_only = 0 order by user_type_id desc,sort_order,description", $GLOBALS['gClientId'], $userTypeId);
				if (!$loyaltyProgramRow = getNextRow($resultSet)) {
					$loyaltyProgramRow = array();
				}
				$loyaltyProgramPointsRow = getRowFromId("loyalty_program_points", "user_id", $userId, "loyalty_program_id = ?", $loyaltyProgramRow['loyalty_program_id']);
				if (!empty($userId) && empty($loyaltyProgramPointsRow) && !empty($loyaltyProgramRow)) {
					$resultSet = executeQuery("insert into loyalty_program_points (loyalty_program_id,user_id,point_value) values (?,?,0)", $loyaltyProgramRow['loyalty_program_id'], $userId);
					$loyaltyProgramPointsRow = getRowFromId("loyalty_program_points", "user_id", $userId, "loyalty_program_id = ?", $loyaltyProgramRow['loyalty_program_id']);
				}

				$pointDollarValue = 0;
				$resultSet = executeQuery("select max(point_value) from loyalty_program_values where loyalty_program_id = ? and minimum_amount <= ?", $loyaltyProgramRow['loyalty_program_id'], $cartTotal);
				if ($row = getNextRow($resultSet)) {
					$pointDollarValue = $row['max(point_value)'];
				}
				if (empty($pointDollarValue)) {
					$pointDollarValue = 0;
				}
				$pointDollarsAvailable = ($loyaltyProgramPointsRow['point_value'] < $loyaltyProgramRow['minimum_amount'] ? 0 : floor($loyaltyProgramPointsRow['point_value'])) * $pointDollarValue;
				if (!empty($loyaltyProgramRow['maximum_amount']) && $pointDollarsAvailable > $loyaltyProgramRow['maximum_amount']) {
					$pointDollarsAvailable = $loyaltyProgramRow['maximum_amount'];
				}

				$merchantAccountInformation = false;
				$achMerchantAccountId = false;
				if ($eCommerceRequired) {
					if (function_exists("_localGetMerchantAccountInformation")) {
						$merchantAccountInformation = _localGetMerchantAccountInformation($_POST);
					}
					$noFraudToken = getPreference("NOFRAUD_TOKEN");
					if (empty($merchantAccountInformation)) {
						$merchantAccountId = $GLOBALS['gMerchantAccountId'];
						$eCommerce = $achECommerce = eCommerce::getEcommerceInstance($merchantAccountId);
						$achMerchantAccountId = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "ACH", "inactive = 0");
						if (!empty($achMerchantAccountId)) {
							$achECommerce = eCommerce::getEcommerceInstance($achMerchantAccountId);
						}
						$preAuthOnly = ((!empty(getPreference("RETAIL_STORE_CAPTURE_AT_SHIPMENT")) || !empty($noFraudToken)) && !empty($eCommerce) && $eCommerce->canDoPreAuthOnly());
					} else {
						if (is_array($merchantAccountInformation)) {
							$merchantAccountId = $merchantAccountInformation['merchant_account_id'];
							$achMerchantAccountId = $merchantAccountInformation['ach_merchant_account_id'];
							$preAuthOnly = $merchantAccountInformation['pre_auth_only'];
						} else {
							$merchantAccountId = $merchantAccountInformation;
							$achMerchantAccountId = false;
							$preAuthOnly = false;
						}
						$eCommerce = $achECommerce = eCommerce::getEcommerceInstance($merchantAccountId);
						if (!empty($achMerchantAccountId) && $achMerchantAccountId != $merchantAccountId) {
							$achECommerce = eCommerce::getEcommerceInstance($achMerchantAccountId);
						}
					}
					$donationsECommerce = false;
					$donationsMerchantAccountId = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "DONATION_MERCHANT_ACCOUNT", "inactive = 0");
					if (!empty($donationsMerchantAccountId)) {
						$donationsECommerce = eCommerce::getEcommerceInstance($donationsMerchantAccountId);
					}

					if (!$eCommerce) {
						$returnArray['error_message'] = "Unable to connect to Merchant Services. Please contact customer service. #6984";
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
						ajaxResponse($returnArray);
						break;
					}
				}

				$forcePaymentMethodTypeCode = "";
				if (!empty($forcePaymentMethodId)) {
					$forcePaymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id", $forcePaymentMethodId));
				}

				$merchantIdentifier = false;
				if ($eCommerceRequired && $eCommerce->hasCustomerDatabase() && (empty($forcePaymentMethodTypeCode) || in_array($forcePaymentMethodTypeCode, array("CREDIT_CARD", "BANK_ACCOUNT")))) {
					$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $contactId, "merchant_account_id = ?", $merchantAccountId);
					if (empty($merchantIdentifier)) {
						$success = $eCommerce->createCustomerProfile(array("contact_id" => $contactId, "first_name" => $contactRow['first_name'],
							"last_name" => $contactRow['last_name'], "business_name" => $contactRow['business_name'], "address_1" => $contactRow['address_1'], "city" => $contactRow['city'],
							"state" => $contactRow['state'], "postal_code" => $contactRow['postal_code'], "email_address" => $contactRow['email_address'], "country_id" => $contactRow['country_id']));
						$response = $eCommerce->getResponse();
						if ($success) {
							$merchantIdentifier = $response['merchant_identifier'];
						}
					}

					if (empty($merchantIdentifier)) {
						$returnArray['error_message'] = "Unable to create the merchant customer account. #8498";
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($response) . "\n\n" . jsonEncode($this->cleanPostData()));
						ajaxResponse($returnArray);
						break;
					}
				} else {
					$preAuthOnly = false;
				}

				$achMerchantIdentifier = false;
				if ($achMerchantAccountId && $achECommerce->hasCustomerDatabase()) {
					$achMerchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $contactId, "merchant_account_id = ?", $achMerchantAccountId);
					if (empty($achMerchantIdentifier)) {
						$success = $achECommerce->createCustomerProfile(array("contact_id" => $contactId, "first_name" => $contactRow['first_name'],
							"last_name" => $contactRow['last_name'], "business_name" => $contactRow['business_name'], "address_1" => $contactRow['address_1'], "city" => $contactRow['city'],
							"state" => $contactRow['state'], "postal_code" => $contactRow['postal_code'], "email_address" => $contactRow['email_address'], "country_id" => $contactRow['country_id']));
						$response = $achECommerce->getResponse();
						if ($success) {
							$achMerchantIdentifier = $response['merchant_identifier'];
						}
					}

					if (empty($achMerchantIdentifier)) {
						$returnArray['error_message'] = "Unable to create the merchant customer account. #9183";
						$this->cancelCredovaLoan($credovaLoanId, $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $returnArray['error_message']);
						addProgramLog("Order unable to be completed: " . $returnArray['error_message'] . "\n\n" . jsonEncode($this->cleanPostData()));
						ajaxResponse($returnArray);
						break;
					}
				}

				if (!empty($confirmUserAccount)) {
					$confirmLink = "https://" . $_SERVER['HTTP_HOST'] . "/confirmuseraccount.php?user_id=" . $userId . "&hash=" . $randomCode;
					sendEmail(array("email_address" => $_POST['email_address'], "send_immediately" => true, "email_code" => "ACCOUNT_CONFIRMATION", "substitutions" => array("confirmation_link" => $confirmLink), "subject" => "Confirm Email Address", "body" => "<p>Click <a href='" . $confirmLink . "'>here</a> to confirm your email address and complete the creation of your user account.</p>"));
				}

				$logEntry = "Order placed by contact ID " . $contactId . ":\n\n";
				foreach ($shoppingCartItems as $index => $thisItem) {
					$productRow = ProductCatalog::getCachedProductRow($thisItem['product_id']);
					$productDataRow = getRowFromId("product_data", "product_id", $thisItem['product_id']);
					$logEntry .= $productRow['product_code'] . " | " . $productRow['description'] . " | " . $productDataRow['upc_code'] . " | " . $thisItem['quantity'] . " | Inventory: " . jsonEncode($inventoryDetailCounts[$thisItem['product_id']]) . "\n";
					$logEntry .= "Price calculation: " . $thisItem['price_calculation'] . "\n\n";
				}
				$logEntry .= jsonEncode($this->cleanPostData()) . "\n";
				$programLogId = addProgramLog($logEntry);

				$GLOBALS['gPrimaryDatabase']->startTransaction();

				executeQuery("insert into add_hashes (add_hash,date_used) values (?,now())", $_POST['_add_hash']);
				$chargedTransactionIdentifiers = array();

				$donationId = "";
				$donationAlreadyProcessed = false;
				if (!empty($_POST['donation_amount']) && $_POST['donation_amount'] > 0 && !empty($_POST['designation_id'])) {
					if (!empty($donationsECommerce)) {
						$thisPaymentMethod = false;
						foreach ($paymentMethodArray as $checkPaymentMethod) {
							if (!empty($checkPaymentMethod['account_id'])) {
								continue;
							}
							$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id", $checkPaymentMethod['payment_method_id']));
							if ($paymentMethodTypeCode != "CREDIT_CARD") {
								continue;
							}
							$thisPaymentMethod = $thisPaymentMethod;
							break;
						}
						if (empty($thisPaymentMethod)) {
							$_POST['donation_amount'] = 0;
							sendEmail(array("notification_code" => "FAILED_ORDER_DONATION",
								"body" => "<p>Donation failed on the Donation Merchant Account because no payment method found. Payment details:<br><br>" . jsonEncode($paymentMethodArray) . "</p>",
								"subject" => "Donation Failure"));
						} else {
							$paymentArray = array("amount" => $_POST['donation_amount'], "order_number" => $donationId, "description" => "Product Order Donation",
								"first_name" => $thisPaymentMethod['billing_first_name'], "last_name" => $thisPaymentMethod['billing_last_name'], "business_name" => $thisPaymentMethod['billing_business_name'],
								"address_1" => $thisPaymentMethod['billing_address_1'], "city" => $thisPaymentMethod['billing_city'], "state" => $thisPaymentMethod['billing_state'],
								"postal_code" => $thisPaymentMethod['billing_postal_code'], "country_id" => $thisPaymentMethod['billing_country_id'],
								"email_address" => $shippingAddress['email_address'], "phone_number" => $_POST['phone_number'], "contact_id" => $contactId, "shipping_address_1" => $shippingAddress['address_1'],
								"shipping_address_2" => $shippingAddress['address_2'], "shipping_city" => $shippingAddress['city'], "shipping_state" => $shippingAddress['state'],
								"shipping_postal_code" => $shippingAddress['postal_code'], "shipping_country_id" => $shippingAddress['country_id']);
							$paymentArray['card_number'] = $thisPaymentMethod['account_number'];
							$paymentArray['expiration_date'] = $thisPaymentMethod['expiration_month'] . "/01/" . $thisPaymentMethod['expiration_year'];
							$paymentArray['card_code'] = $thisPaymentMethod['cvv_code'];
							$success = $donationsECommerce->authorizeCharge($paymentArray);
							$response = $donationsECommerce->getResponse();
							if (!$success) {
								$_POST['donation_amount'] = 0;
								sendEmail(array("notification_code" => "FAILED_ORDER_DONATION",
									"body" => "<p>Donation failed on the Donation Merchant Account because of gateway error. Error:<br><br>" . jsonEncode($response) . "</p>",
									"subject" => "Donation Failure"));
							} else {
								$chargedTransactionIdentifiers[] = array("donation" => true, "transaction_identifier" => $response['transaction_id']);
								$donationAlreadyProcessed = true;
							}
						}
					}
					if (!empty($_POST['donation_amount']) && $_POST['donation_amount'] > 0) {
						$donationSourceId = "";
						if (!empty($sourceId)) {
							$donationSourceId = getFieldFromId("donation_source_id", "donation_sources", "donation_source_code", getFieldFromId("source_code", "sources", "source_id", $sourceId), "inactive = 0");
						}
						if (empty($donationSourceId)) {
							$donationSourceId = getFieldFromId("donation_source_id", "donation_sources", "donation_source_code", "RETAIL_STORE", "inactive = 0");
						}
						$donationFee = Donations::getDonationFee(array("designation_id" => $_POST['designation_id'], "amount" => $_POST['donation_amount'], "payment_method_id" => $primaryPaymentMethodId));
						$resultSet = executeQuery("insert into donations (client_id,contact_id,donation_date,payment_method_id," .
							"account_id,designation_id,amount,donation_fee,donation_source_id) values (?,?,now(),?,?,?,?,?,?)",
							$GLOBALS['gClientId'], $contactId, $primaryPaymentMethodId, $primaryAccountId, $_POST['designation_id'], $_POST['donation_amount'], $donationFee, $donationSourceId);
						if (!empty($resultSet['sql_error'])) {
							$this->rollbackOrder(array("contact_id" => $contactId, "charged_transaction_identifiers" => $chargedTransactionIdentifiers, "donation_e_commerce" => $donationsECommerce));
							$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
							addProgramLog("\nOrder unable to be completed: " . $returnArray['error_message'], $programLogId);
							ajaxResponse($returnArray);
							break;
						}
						$donationId = $resultSet['insert_id'];
						Donations::processDonation($donationId);
						$requiresAttention = getFieldFromId("requires_attention", "designations", "designation_id", $_POST['designation_id']);
						if ($requiresAttention) {
							sendEmail(array("subject" => "Designation Requires Attention", "body" => "Donation ID " . $donationId . " was created with a designation that requires attention.", "email_address" => getNotificationEmails("DONATIONS")));
						}
						addActivityLog("Made a donation for '" . getFieldFromId("description", "designations", "designation_id", $_POST['designation_id']) . "'");
					}
				}
				$discounts = $shoppingCart->getCartDiscount();
				$discountAmount = ($discounts['discount_amount'] * 1);
				$discountPercent = ($discounts['discount_percent'] * 1);
				if ($discountAmount <= 0 && $discountPercent > 0) {
					$discountAmount = round($cartTotal * ($discountPercent / 100), 2);
				}
				if ($discountAmount < 0) {
					$discountAmount = 0;
				}

				$orderObject = new Order();
				$orderObject->populateFromShoppingCart($shoppingCart);
				$outOfStock = $orderObject->checkOutOfStock();
				if ($outOfStock !== false) {
					$this->rollbackOrder(array("contact_id" => $contactId, "charged_transaction_identifiers" => $chargedTransactionIdentifiers, "donation_e_commerce" => $donationsECommerce));
					$returnArray['error_message'] = "Some products cannot be ordered: " . $outOfStock;
					addProgramLog("Order unable to be completed: " . $returnArray['error_message'], $programLogId);
					$returnArray['recalculate'] = true;
					ajaxResponse($returnArray);
					break;
				}
				$orderObject->setOrderField("donation_id", $donationId);
				$orderObject->setOrderField("referral_contact_id", $_POST['referral_contact_id']);
				$orderObject->setOrderField("source_id", $sourceId);
				$orderObject->setOrderField("shipping_charge", $shippingCharge);
				$orderObject->setOrderField("order_discount", $discountAmount);
				if ($fflRequired) {
					$orderObject->setOrderField("federal_firearms_licensee_id", $_POST['federal_firearms_licensee_id']);
				}
				if (empty($_POST['business_name']) && empty($addressId)) {
					$_POST['business_name'] = $contactRow['business_name'];
				}
				$fullName = getFieldFromId("full_name", "addresses", "address_id", $addressId);
				if (empty($fullName)) {
					$fullName = $_POST['first_name'];
					$fullName .= (empty($fullName) ? "" : " ") . $_POST['last_name'];
					$fullName .= (empty($_POST['business_name']) || empty($fullName) ? "" : ", " . $_POST['business_name']);
				}
				$orderObject->setOrderField("full_name", $fullName);
				$orderMethodId = getFieldFromId("order_method_id", "order_methods", "order_method_code", $_POST['order_method_code'], "inactive = 0");
                if(empty($orderMethodId) && !empty($_SESSION['original_user_id'])) {
                    $orderMethodId = getFieldFromId("order_method_id", "order_methods", "order_method_code", 'SIMULATE_USER', "inactive = 0");
                }
				if (empty($orderMethodId)) {
					$orderMethodId = getFieldFromId("order_method_id", "order_methods", "order_method_code", ($GLOBALS['gInternalConnection'] ? "INTERNAL_" : "") . "WEBSITE", "inactive = 0");
				}
				$orderObject->setOrderField("order_method_id", $orderMethodId);
				$orderObject->setOrderField("shipping_method_id", ($_POST['shipping_method_id'] <= 0 ? "" : $_POST['shipping_method_id']));
				$orderObject->setOrderField("business_address", $_POST['business_address']);
				$orderObject->setOrderField("address_id", $addressId);
				$orderObject->setOrderField("attention_line", $_POST['attention_line']);
				$orderObject->setOrderField("purchase_order_number", $_POST['purchase_order_number']);
				$orderObject->setOrderField("gift_order", $_POST['gift_order']);
				$orderObject->setOrderField("gift_text", $_POST['gift_text']);
				if (strlen($_POST['signature']) > 250) {
					$orderObject->setOrderField("signature", $_POST['signature']);
				}
				$orderObject->setOrderField("phone_number", $_POST['phone_number']);

				if ($orderEntryCreated) {
					$taxCharge = $_POST['tax_charge'];
					$orderObject->setOrderItemTaxes($taxCharge);
				} else {
					$taxCharge = $orderObject->getTax("", $programLogId);
				}
				if (empty($taxCharge)) {
					$taxCharge = 0;
				}

				$taxChargeDiscrepancy = false;
				$taxDiscrepancyAmount = 0;
				if ($taxCharge != $_POST['tax_charge']) {
					$taxDiscrepancyAmount = $taxCharge - $_POST['tax_charge'];
					addProgramLog("Tax Charge is different: post - " . $_POST['tax_charge'] . ", calculated - " . $taxCharge . ", difference of " . $taxDiscrepancyAmount, $programLogId);
					$_POST['tax_charge'] = $taxCharge;
					$taxChargeDiscrepancy = true;
				}

				$handlingCharge = 0;
				$handlingTotal = round($cartTotal + $taxCharge + $shippingCharge, 2);
				$firstOne = true;
				foreach ($paymentMethodArray as $index => $thisPaymentMethod) {
					if (!empty($thisPaymentMethod['payment_method_id']) && !in_array($thisPaymentMethod['payment_method_id'], $uniqueValidPaymentMethods)) {
						$this->rollbackOrder(array("contact_id" => $contactId, "charged_transaction_identifiers" => $chargedTransactionIdentifiers, "donation_e_commerce" => $donationsECommerce));
						$returnArray['error_message'] = "Invalid payment method used for products in this order" .
							($GLOBALS['gUserRow']['superuser_flag'] ? ": " . $thisPaymentMethod['payment_method_id'] . ":" . jsonEncode($uniqueValidPaymentMethods) . ":" . jsonEncode($validPaymentMethods) . ":" . jsonEncode($invalidPaymentMethods) : "");
						addProgramLog("\nOrder unable to be completed: " . $returnArray['error_message'], $programLogId);
						ajaxResponse($returnArray);
						break 2;
					}
					$paymentMethodArray[$index]['handling_charge'] = ($firstOne ? 0 : $additionalPaymentHandlingCharge);
					$firstOne = false;
					$paymentMethodRow = getRowFromId("payment_methods", "payment_method_id", $thisPaymentMethod['payment_method_id']);
					$paymentMethodArray[$index]['payment_method_row'] = $paymentMethodRow;
					if (!empty($thisPaymentMethod['primary_payment_method']) || empty($thisPaymentMethod['payment_amount'])) {
						$amount = $handlingTotal;
					} else {
						$amount = $thisPaymentMethod['payment_amount'];
					}
					if (empty($paymentMethodRow['flat_rate']) || $paymentMethodRow['flat_rate'] == 0) {
						$paymentMethodRow['flat_rate'] = 0;
					}
					if (empty($paymentMethodRow['fee_percent']) || $paymentMethodRow['fee_percent'] == 0) {
						$paymentMethodRow['fee_percent'] = 0;
					}
					if ((empty($paymentMethodRow['flat_rate']) || $paymentMethodRow['flat_rate'] == 0) && (empty($paymentMethodRow['fee_percent']) || $paymentMethodRow['fee_percent'] == 0)) {
						$handlingTotal -= $amount;
						$handlingCharge += $paymentMethodArray[$index]['handling_charge'];
						continue;
					}
					$thisHandlingCharge = round($paymentMethodRow['flat_rate'] + ($amount * $paymentMethodRow['fee_percent'] / 100), 2);
					$paymentMethodArray[$index]['handling_charge'] += $thisHandlingCharge;
					$handlingCharge += $paymentMethodArray[$index]['handling_charge'];
					$handlingTotal -= $amount;
				}
				if ($orderEntryCreated) {
					$handlingCharge = $_POST['handling_charge'];
				}

				$orderTotal = round($cartTotal + $taxCharge + $shippingCharge + $handlingCharge, 2);
				if (empty($_POST['donation_amount']) || $_POST['donation_amount'] < 0) {
					$_POST['donation_amount'] = 0;
				}
				if (!empty($_POST['donation_amount']) && !$donationAlreadyProcessed) {
					$orderTotal += $_POST['donation_amount'];
				}
				$orderTotal = $orderTotal - $discountAmount;
				addProgramLog("Order Total: " . $orderTotal . " - cart: " . $cartTotal . ", tax: " . $taxCharge . ", shipping: " . $shippingCharge . ", handling: " . $handlingCharge, $programLogId);

				foreach ($paymentMethodArray as $index => $thisPaymentMethod) {
					$maxPercentage = getFieldFromId("percentage", "payment_methods", "payment_method_id", $thisPaymentMethod['payment_method_id']);
					if (!empty($maxPercentage) && $maxPercentage > 0 && $maxPercentage < 100) {
						if (empty($thisPaymentMethod['payment_amount']) || round($orderTotal * $maxPercentage / 100, 2) < $thisPaymentMethod['payment_amount']) {
							$this->rollbackOrder(array("contact_id" => $contactId, "charged_transaction_identifiers" => $chargedTransactionIdentifiers, "donation_e_commerce" => $donationsECommerce));
							$returnArray['error_message'] = "Invalid payment amount for " . getFieldFromId("description", "payment_methods", "payment_method_id", $thisPaymentMethod['payment_method_id']) .
								($GLOBALS['gUserRow']['superuser_flag'] ? ": " . $thisPaymentMethod['payment_method_id'] . ":" . jsonEncode($uniqueValidPaymentMethods) . ":" . jsonEncode($validPaymentMethods) . ":" . jsonEncode($invalidPaymentMethods) : "");
							addProgramLog("\nOrder unable to be completed: " . $returnArray['error_message'], $programLogId);
							ajaxResponse($returnArray);
							break 2;
						}
					}
				}

				$orderObject->setOrderField("tax_charge", $taxCharge);
				$orderObject->setOrderField("handling_charge", $handlingCharge);

				if (!$validatePaymentOnly) {
					$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", "PAYMENTS_NOT_COMPLETE");
					if (empty($orderStatusId)) {
						$insertSet = executeQuery("insert into order_status (client_id,order_status_code,description,display_color,internal_use_only) values (?,?,?,?,1)", $GLOBALS['gClientId'], "PAYMENTS_NOT_COMPLETE", "Payments may not have gotten completed", "#CC0000");
						$orderStatusId = $insertSet['insert_id'];
					}
					$orderObject->setOrderField("order_status_id", $orderStatusId);
				}

				$orderObject->setCustomFields($_POST);
				if (!$orderObject->generateOrder()) {
					$this->rollbackOrder(array("contact_id" => $contactId, "charged_transaction_identifiers" => $chargedTransactionIdentifiers, "donation_e_commerce" => $donationsECommerce));
					addProgramLog("\nOrder Unable to be completed: " . $orderObject->getErrorMessage(), $programLogId);
					$returnArray['error_message'] = $orderObject->getErrorMessage();
					ajaxResponse($returnArray);
					break;
				}

				if (!empty($userId)) {
					$defaultFFLDealer = CustomField::getCustomFieldData($contactId, "DEFAULT_FFL_DEALER");
					$defaultFFLDealer = (new FFL(array("federal_firearms_licensee_id" => $defaultFFLDealer, "only_if_valid" => true)))->getFieldData("federal_firearms_licensee_id");
					if (empty($defaultFFLDealer)) {
						if (!empty($_POST['federal_firearms_licensee_id'])) {
							CustomField::setCustomFieldData($contactId, "DEFAULT_FFL_DEALER", $_POST['federal_firearms_licensee_id']);
						}
					}
				}

				$orderId = $orderObject->getOrderId();
				if (!empty($_POST['promotion_code']) && $shoppingCart->isOneTimeUsePromotionCode()) {
					executeQuery("update one_time_use_promotion_codes set order_id = ? where promotion_code = ? and client_id = ?", $orderId, strtoupper($_POST['promotion_code']), $GLOBALS['gClientId']);
				}
				if (!empty($credovaLoanRow['credova_loan_id'])) {
					executeQuery("update credova_loans set order_id = ? where credova_loan_id = ?", $orderId, $credovaLoanRow['credova_loan_id']);
				}
				$orderNoteUserId = $GLOBALS['gUserId']; // Use Logged in user here even for Order Entry because it will only be for notes
				if (empty($orderNoteUserId)) {
					$orderNoteUserId = getFieldFromId("user_id", "users", "full_client_access", "1", "superuser_flag = 0");
				}
				if (!empty($_POST['order_notes_content'])) {
					executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $orderNoteUserId, $_POST['order_notes_content']);
				}
				$orderPackNotes = $orderObject->getPackNotes();
				if (!empty($orderPackNotes)) {
					executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $orderNoteUserId, $orderPackNotes);
				}
				foreach ($_POST as $fieldName => $fieldValue) {
					if (!empty($fieldValue) && substr($fieldName, 0, strlen("retail_agreement_id_")) == "retail_agreement_id_") {
						$retailAgreementId = substr($fieldName, strlen("retail_agreement_id_"));
						executeQuery("insert ignore into order_retail_agreements (order_id,retail_agreement_id) values (?,?)", $orderId, $retailAgreementId);
					}
				}

				$createShipment = false;
				$shippingMethodLocationId = "";
				if (!empty($_POST['shipping_method_id'])) {
					$createShipment = getFieldFromId("create_shipment", "shipping_methods", "shipping_method_id", $_POST['shipping_method_id']);
					$shippingMethodLocationId = getFieldFromId("location_id", "shipping_methods", "shipping_method_id", $_POST['shipping_method_id']);
					if (empty($shippingMethodLocationId)) {
						$createShipment = false;
					}
				}
				if ($createShipment) {
					$locationId = $shippingMethodLocationId;
					$resultSet = executeQuery("insert into order_shipments (order_id,location_id,date_shipped) values (?,?,current_date)",
						$orderId, $locationId);
					$orderShipmentId = $resultSet['insert_id'];
					$orderItemCount = 0;
					$orderItems = $orderObject->getOrderItems();
					foreach ($orderItems as $thisOrderItem) {
						$cost = ProductCatalog::getLocationBaseCost($thisOrderItem['product_id'], $locationId, false, false);
						executeQuery("insert into order_shipment_items (order_shipment_id,order_item_id,quantity,cost) values (?,?,?,?)",
							$orderShipmentId, $thisOrderItem['order_item_id'], $thisOrderItem['quantity'], $cost);
						$orderItemCount++;

						# add to product inventory log
						$productDistributorId = getFieldFromId("product_distributor_id", "locations", "location_id", $locationId);

						if (!empty($productDistributorId)) {
							ProductDistributor::setPrimaryDistributorLocation();
						}
						$inventoryLocationId = ProductDistributor::getInventoryLocation($locationId);

						$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $thisOrderItem['product_id'], "location_id = ?", $inventoryLocationId);
						if (!empty($productInventoryId)) {
							$productInventoryLogId = getFieldFromId("product_inventory_log_id", "product_inventory_log", "product_inventory_id", $productInventoryId,
								"inventory_adjustment_type_id = ? and order_id = ?", $GLOBALS['gSalesAdjustmentTypeId'], $orderId);
							if (empty($productInventoryLogId)) {
								executeQuery("insert into product_inventory_log (product_inventory_id,inventory_adjustment_type_id,order_id,user_id,log_time,quantity) values " .
									"(?,?,?,?,now(),?)", $productInventoryId, $GLOBALS['gSalesAdjustmentTypeId'], $orderId, $userId, $thisOrderItem['quantity']);
							} else {
								executeQuery("update product_inventory_log set quantity = quantity + " . $thisOrderItem['quantity'] . " where product_inventory_log_id = ?", $productInventoryLogId);
							}
						}
						executeQuery("update product_inventories set quantity = greatest(0,quantity - " . $thisOrderItem['quantity'] . ") where product_inventory_id = ?", $productInventoryId);

					}
				}

				$substitutions = $shippingAddress;
				$substitutions['source_id'] = $sourceId;
				$substitutions['source'] = getFieldFromId("description", "sources", "source_id", $sourceId);
				$substitutions['shipping_address_block'] = $shippingAddress['address_1'];
				if (!empty($shippingAddress['address_2'])) {
					$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . $shippingAddress['address_2'];
				}
				$shippingCityLine = $shippingAddress['city'] . (empty($shippingAddress['city']) || empty($shippingAddress['state']) ? "" : ", ") . $shippingAddress['state'];
				if (!empty($shippingAddress['postal_code'])) {
					$shippingCityLine .= (empty($shippingCityLine) ? "" : " ") . $shippingAddress['postal_code'];
				}
				if (!empty($shippingCityLine)) {
					$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . $shippingCityLine;
				}
				if (!empty($shippingAddress['country_id']) && $shippingAddress['country_id'] != 1000) {
					$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . getFieldFromId("country_name", "countries", "country_id", $shippingAddress['country_id']);
				}
				$substitutions['country_code'] = getFieldFromId("country_code", "countries", "country_id", $shippingAddress['country_id']);
				$substitutions = array_merge($substitutions, $orderObject->getOrderRow());
				$substitutions['order_id'] = $orderId;
				$substitutions['order_total'] = number_format($orderTotal, 2);
				$substitutions['tax_charge'] = number_format($taxCharge, 2);
				$substitutions['shipping_charge'] = number_format($shippingCharge, 2);
				$substitutions['handling_charge'] = number_format($handlingCharge, 2);
				$substitutions['shipping_method'] = getFieldFromId("description", "shipping_methods", "shipping_method_id", $_POST['shipping_method_id']);
				$shippingMethodLocationId = getFieldFromId("location_id", "shipping_methods", "shipping_method_id", $_POST['shipping_method_id']);
				$substitutions['location'] = getFieldFromId("description", "locations", "location_id", $shippingMethodLocationId);

				$locationRow = getRowFromId("locations", "location_id", $shippingMethodLocationId);
				$locationContactRow = Contact::getContact($locationRow['contact_id']);
				$substitutions['pickup_location_phone_number'] = getFieldFromId("phone_number", "phone_numbers", "contact_id", $locationRow['contact_id']);
				$substitutions['pickup_location_name'] = $locationContactRow['business_name'];
				$substitutions['pickup_location_address'] = $locationContactRow['address_1'] . ", " . $locationContactRow['city'] . ", " . $locationContactRow['state'] . " " . $locationContactRow['postal_code'];

				$substitutions['cart_total'] = number_format($cartTotal, 2);
				$substitutions['cart_total_quantity'] = $cartTotalQuantity;
				$substitutions['order_discount'] = $discountAmount;
				$promotionRow = ShoppingCart::getCachedPromotionRow(getFieldFromId("promotion_id", "order_promotions", "order_id", $orderId));
				$substitutions['promotion_code'] = $promotionRow['promotion_code'];
				$substitutions['promotion_description'] = $promotionRow['description'];
				$substitutions['donation_amount'] = number_format((empty($_POST['donation_amount']) ? 0 : $_POST['donation_amount']), 2);
				$substitutions['designation_code'] = getFieldFromId("designation_code", "designations", "designation_id", $_POST['designation_id']);
				$substitutions['designation_description'] = getFieldFromId("description", "designations", "designation_id", $_POST['designation_id']);
				$substitutions['order_date'] = date("m/d/Y");
				$fflRow = array();
				if ($fflRequired) {
					$fflRow = (new FFL(array("federal_firearms_licensee_id" => $_POST['federal_firearms_licensee_id'], "only_if_valid" => true)))->getFFLRow();
				}
				$substitutions['ffl_name'] = $fflRow['business_name'];
				$substitutions['ffl_phone_number'] = $fflRow['phone_number'];
				$substitutions['ffl_license_number'] = $fflRow['license_number'];
				$substitutions['ffl_license_number_masked'] = maskString($fflRow['license_number'], "#-##-XXX-XX-XX-#####");
				$substitutions['ffl_address'] = $fflRow['address_1'] . ", " . $fflRow['city'] . ", " . $fflRow['state'] . " " . $fflRow['postal_code'];
				$substitutions['billing_address_block'] = "";

				$createdMerchantAccounts = array();

				$chargedOrderTotal = round($orderTotal, 2);

				$paymentMethodCount = 0;
				$eCommerceResponses = array();
				foreach ($paymentMethodArray as $index => $thisPaymentMethod) {
					$failedCreditCardNameKey = $thisPaymentMethod['billing_first_name'] . ":" . $thisPaymentMethod['billing_last_name'];
					$failedCreditCardCount = getCachedData("failed_credit_card_names", $failedCreditCardNameKey);
					if (!empty($failedCreditCardCount) && !$orderEntryCreated) {
						if ($failedCreditCardCount > 5) {
							sleep(30);
							$returnArray['error_message'] = "Charge failed: Transaction declined (5931)";
							$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
								"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
								"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
							addProgramLog("\nOrder ID " . $orderId . " unable to be completed because billing name appeared in list of frequent failed credit card names", $programLogId);
							ajaxResponse($returnArray);
							break;
						}
					} else {
						$failedCreditCardCount = 0;
					}

					if (empty($substitutions['billing_address_block']) && !empty($thisPaymentMethod['billing_address_1'])) {
						$substitutions['billing_address_block'] = $thisPaymentMethod['billing_address_1'];
						if (!empty($thisPaymentMethod['billing_address_2'])) {
							$substitutions['billing_address_block'] .= (empty($substitutions['billing_address_block']) ? "" : "<br>") . $thisPaymentMethod['billing_address_2'];
						}
						$billingCityLine = $thisPaymentMethod['billing_city'] . (empty($thisPaymentMethod['billing_city']) || empty($thisPaymentMethod['billing_state']) ? "" : ", ") . $thisPaymentMethod['billing_state'];
						if (!empty($thisPaymentMethod['billing_postal_code'])) {
							$billingCityLine .= (empty($billingCityLine) ? "" : " ") . $thisPaymentMethod['billing_postal_code'];
						}
						if (!empty($billingCityLine)) {
							$substitutions['billing_address_block'] .= (empty($substitutions['billing_address_block']) ? "" : "<br>") . $billingCityLine;
						}
						if (!empty($thisPaymentMethod['billing_country_id']) && $thisPaymentMethod['billing_country_id'] != 1000) {
							$substitutions['billing_address_block'] .= (empty($substitutions['billing_address_block']) ? "" : "<br>") . getFieldFromId("country_name", "countries", "country_id", $thisPaymentMethod['billing_country_id']);
						}
					}

					if ($chargedOrderTotal <= 0) {
						break;
					}
					$thisPaymentMethod['account_number'] = str_replace("-", "", str_replace(" ", "", $thisPaymentMethod['account_number']));
					$thisPaymentMethod['bank_account_number'] = str_replace("-", "", str_replace(" ", "", $thisPaymentMethod['bank_account_number']));

# Determine the type of payment method and set parameters for it

					if (!empty($thisPaymentMethod['account_id'])) {
						$thisPaymentMethod['payment_method_id'] = getFieldFromId("payment_method_id", "accounts", "account_id", $thisPaymentMethod['account_id']);
						$thisPaymentMethod['payment_method_row'] = getRowFromId("payment_methods", "payment_method_id", $thisPaymentMethod['payment_method_id']);
						$thisPaymentMethod['accounts_row'] = getRowFromId("accounts", "account_id", $thisPaymentMethod['account_id']);
					}
					$paymentMethodRow = $thisPaymentMethod['payment_method_row'];
					if (!empty($paymentMethodRow['percentage'] && $paymentMethodRow['percentage'] > 0)) {
						$limitAmount = round($orderTotal * ($paymentMethodRow['percentage'] / 100), 2);
						if ($limitAmount < $thisPaymentMethod['payment_amount']) {
							$thisPaymentMethod['payment_amount'] = $limitAmount;
						}
					}

					$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", $paymentMethodRow['payment_method_type_id']);
					addProgramLog("Payment Method: " . $paymentMethodRow['payment_method_code'] . ", " . $paymentMethodTypeCode . ", " . $thisPaymentMethod['amount'], $programLogId);
					$accountNumber = "";
					$saveAccountNumber = false;
					$canSaveAccount = false;
					$accountTokenRequired = false;
					$isBankAccount = false;
					switch ($paymentMethodTypeCode) {
						case "BANK_ACCOUNT":
							$accountTokenRequired = $eCommerceRequired && (empty($achMerchantAccountId) ? $eCommerce->hasCustomerDatabase() : !empty($achECommerce) && $achECommerce->hasCustomerDatabase());
							$accountNumber = $thisPaymentMethod['bank_account_number'];
							$isBankAccount = true;
							$canSaveAccount = true;
							break;
						case "CREDIT_CARD":
							$accountTokenRequired = $eCommerceRequired && $eCommerce->hasCustomerDatabase();
							$accountNumber = $thisPaymentMethod['account_number'];
							$canSaveAccount = true;
							break;
						case "GIFT_CARD":
							$accountNumber = (empty($thisPaymentMethod['account_id']) ? $thisPaymentMethod['gift_card_number'] : $thisPaymentMethod['accounts_row']['account_number']);
							$giftCardPin = $thisPaymentMethod['gift_card_pin'];
							$saveAccountNumber = true;
							break;
						case "LOYALTY_POINTS":
							$accountNumber = "";
							$saveAccountNumber = false;
							break;
						case "LOAN":
							$accountNumber = (empty($thisPaymentMethod['account_id']) ? $thisPaymentMethod['loan_number'] : $thisPaymentMethod['accounts_row']['account_number']);
							$saveAccountNumber = true;
							break;
						case "LEASE":
							$accountNumber = (empty($thisPaymentMethod['account_id']) ? $thisPaymentMethod['lease_number'] : $thisPaymentMethod['accounts_row']['account_number']);
							$saveAccountNumber = true;
							break;
					}
					$useECommerce = ($achMerchantAccountId && $isBankAccount ? $achECommerce : $eCommerce);

# Create the account in the Coreware database

					$eCommerceResponse = array();
					$saveAccount = (!empty($thisPaymentMethod['account_label']) || !empty($thisPaymentMethod['default_payment_methoid'])) && $canSaveAccount;
					$accountId = false;
					if (empty($thisPaymentMethod['account_id'])) {
						$accountLabel = $thisPaymentMethod['account_label'];
						if (empty($accountLabel)) {
							$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $thisPaymentMethod['payment_method_id']) . " - " . substr($accountNumber, -4);
						} else {
							if ($achMerchantAccountId && $isBankAccount) {
								if (empty($achECommerce) || !$achECommerce->hasCustomerDatabase()) {
									$saveAccount = false;
								}
							} else {
								if (!$eCommerce->hasCustomerDatabase()) {
									$saveAccount = false;
								}
							}
						}
						$saveAccount = $saveAccount || (!empty($recurringProduct));

						$fullName = $thisPaymentMethod['billing_first_name'] . " " . $thisPaymentMethod['billing_last_name'] . (empty($thisPaymentMethod['billing_business_name']) ? "" : ", " . $thisPaymentMethod['billing_business_name']);
						$accountAddressId = $thisPaymentMethod['billing_address_id'];
						if (!empty($accountAddressId)) {
							$accountAddressId = getFieldFromId("address_id", "addresses", "address_id", $accountAddressId, "contact_id = ?", $contactId);
						}

						if (empty($accountAddressId)) {
							if ($thisPaymentMethod['billing_address_1'] != $_POST['address_1'] || $thisPaymentMethod['billing_city'] != $_POST['city'] || $thisPaymentMethod['postal_code'] != $_POST['postal_code']) {
								if (empty($thisPaymentMethod['billing_country_id'])) {
									$thisPaymentMethod['billing_country_id'] = "1000";
								}
								$accountAddressId = getFieldFromId("address_id", "addresses", "contact_id", $contactId, "address_1 <=> ? and address_2 <=> ? and city <=> ? and state <=> ? and postal_code <=> ? and country_id = ?",
									$thisPaymentMethod['billing_address_1'], $thisPaymentMethod['billing_address_2'], $thisPaymentMethod['billing_city'], $thisPaymentMethod['billing_state'], $thisPaymentMethod['billing_postal_code'], $thisPaymentMethod['billing_country_id']);
								if (empty($accountAddressId)) {
									$insertSet = executeQuery("insert into addresses (contact_id,address_label,address_1,address_2,city,state,postal_code,country_id,default_billing_address) values (?,?,?,?,?, ?,?,?,?)",
										$contactId, "Billing Address", $thisPaymentMethod['billing_address_1'], $thisPaymentMethod['billing_address_2'], $thisPaymentMethod['billing_city'],
										$thisPaymentMethod['billing_state'], $thisPaymentMethod['billing_postal_code'], $thisPaymentMethod['billing_country_id'], (empty($thisPaymentMethod['default_billing_address']) ? 0 : 1));
									if (!empty($insertSet['sql_error'])) {
										$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
										$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
											"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
											"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
										addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . getSystemMessage("basic", $resultSet['sql_error']), $programLogId);
										ajaxResponse($returnArray);
										break;
									}
									$accountAddressId = $insertSet['insert_id'];
								}
							}
						}
						if ($saveAccount || !empty($accountNumber)) {
							$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name,address_id,account_number,expiration_date,merchant_account_id,default_payment_method,inactive) values (?,?,?,?,?, ?,?,?,?,?)",
								$contactId, $accountLabel, $thisPaymentMethod['payment_method_id'], $fullName, $accountAddressId, ($saveAccountNumber ? $accountNumber : "XXXX-" . substr($accountNumber, -4)),
								(empty($thisPaymentMethod['expiration_year']) ? "" : date("Y-m-d", strtotime($thisPaymentMethod['expiration_month'] . "/01/" . $thisPaymentMethod['expiration_year']))),
								$merchantAccountId, ($canSaveAccount && $saveAccount ? 1 : 0), ($canSaveAccount && $saveAccount ? 0 : 1));
							if (!empty($resultSet['sql_error'])) {
								$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
								$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
									"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
									"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
								addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . getSystemMessage("basic", $resultSet['sql_error']), $programLogId);
								ajaxResponse($returnArray);
								break;
							}
							$accountId = $resultSet['insert_id'];
							if ($saveAccount && !empty($thisPaymentMethod['default_payment_method'])) {
								executeQuery("update accounts set default_payment_method = 0 where contact_id = ? and account_id <> ?", $contactId, $accountId);
							}
						} else {
							$accountId = "";
						}
					} else {
						$useAccount = false;
						if ($achMerchantAccountId) {
							$useAccount = (!empty($achECommerce) && $achECommerce->hasCustomerDatabase());
						} else {
							$useAccount = ($eCommerce->hasCustomerDatabase());
						}
						if ($useAccount) {
							$accountId = getFieldFromId("account_id", "accounts", "account_id", $thisPaymentMethod['account_id'], "contact_id = ?", $contactId);
							if (empty($accountId) || empty($thisPaymentMethod['payment_method_id'])) {
								$returnArray['error_message'] = "There is a problem this account. #6831: " . $accountId . ":" . $thisPaymentMethod['payment_method_id'];
								$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
									"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
									"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
								addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "There is a problem this account. #6831: " . $accountId . ":" . $thisPaymentMethod['payment_method_id'], $programLogId);
								ajaxResponse($returnArray);
								break;
							}
							if (getPreference("RETAIL_STORE_REQUIRE_SAME_NAME_SAVED_ACCOUNT")) {
								$accountFullName = getFieldFromId("full_name", "accounts", "account_id", $accountId);
								$accountFullNameKey = preg_replace("/[^a-z0-9]/", '', strtolower($accountFullName));
								$fullNameKey = preg_replace("/[^a-z0-9]/", '', strtolower($fullName));
								$shortNameKey = preg_replace("/[^a-z0-9]/", '', strtolower($thisPaymentMethod['billing_first_name'] . $thisPaymentMethod['billing_last_name']));
								if ($accountFullNameKey != $fullNameKey && $accountFullNameKey != $shortNameKey) {
									$returnArray['error_message'] = "This saved account cannot be used because the name does not match.";
									$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
										"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
										"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
									addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "This saved account cannot be used because the name does not match. order: " . $fullName . " acount: " . $accountFullName, $programLogId);
									ajaxResponse($returnArray);
									break;
								}
							}
						}
					}
					if ($accountTokenRequired) {
						$accountToken = getFieldFromId("account_token", "accounts", "account_id", $accountId, "contact_id = ?", $contactId);
						if (empty($accountToken) && !empty($thisPaymentMethod['account_id'])) {
							$returnArray['error_message'] = "There is a problem using an existing payment method. Please create a new one. #4383";
							$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
								"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
								"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
							addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "There is a problem using an existing payment method. Please create a new one. #4383", $programLogId);
							ajaxResponse($returnArray);
							break;
						}

						$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($accountId);
						if ($accountMerchantAccountId != $merchantAccountId) {
							$returnArray['error_message'] = "There is a problem this account. #5699";
							$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
								"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
								"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
							addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "There is a problem this account. #5699", $programLogId);
							ajaxResponse($returnArray);
							break;
						}
					}

# Create the customer payment profile if saving the account

					if ($accountTokenRequired && ($saveAccount || $preAuthOnly || $alwaysTokenizePaymentMethod) && empty($accountToken)) {
						$createPaymentAccount = false;
						if ($achMerchantAccountId && $isBankAccount) {
							$createPaymentAccount = (!empty($achECommerce) && $achECommerce->hasCustomerDatabase());
						} else {
							$createPaymentAccount = $eCommerce->hasCustomerDatabase();
						}
						if ($createPaymentAccount) {
							$paymentArray = array("contact_id" => $contactId, "account_id" => $accountId, "merchant_identifier" => ($achMerchantAccountId && $isBankAccount ? $achMerchantIdentifier : $merchantIdentifier),
								"first_name" => $thisPaymentMethod['billing_first_name'], "last_name" => $thisPaymentMethod['billing_last_name'], "business_name" => $thisPaymentMethod['billing_business_name'],
								"address_1" => $thisPaymentMethod['billing_address_1'], "city" => $thisPaymentMethod['billing_city'], "state" => $thisPaymentMethod['billing_state'],
								"postal_code" => $thisPaymentMethod['billing_postal_code'], "country_id" => $thisPaymentMethod['billing_country_id']);
							if ($isBankAccount) {
								$paymentArray['bank_routing_number'] = $thisPaymentMethod['routing_number'];
								$paymentArray['bank_account_number'] = $thisPaymentMethod['bank_account_number'];
								$paymentArray['bank_account_type'] = str_replace(" ", "", lcfirst(ucwords(strtolower(str_replace("_", " ", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $thisPaymentMethod['payment_method_id']))))));
							} else {
								$paymentArray['card_number'] = $thisPaymentMethod['account_number'];
								$paymentArray['expiration_date'] = $thisPaymentMethod['expiration_month'] . "/01/" . $thisPaymentMethod['expiration_year'];
								$paymentArray['card_code'] = ($GLOBALS['gInternalConnection'] && empty($thisPaymentMethod['cvv_code']) ? "SKIP_CARD_CODE" : $thisPaymentMethod['cvv_code']);
							}
							$success = $useECommerce->createCustomerPaymentProfile($paymentArray);
							$response = $useECommerce->getResponse();
							if ($success) {
								$customerPaymentProfileId = $accountToken = $response['account_token'];
								$createdMerchantAccounts[] = array("ach" => ($achMerchantAccountId && $isBankAccount), "customer_payment_profile_id" => $accountToken);
								if (!$saveAccount) {
									executeQuery("update accounts set inactive = 1 where account_id = ?", $accountId);
								}
							} else {
								$errorMessage = "Unable to create payment account: " . ($response['response_reason_text'] ?: "Unknown error occurred");
								$returnArray['error_message'] = $errorMessage;
								$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
									"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
									"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
								addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . $errorMessage, $programLogId);
								ajaxResponse($returnArray);
								break;
							}
							$eCommerceResponse = array_merge($paymentArray, $response);
						}
					}

# Charge the account

					$paymentAmount = floatval(str_replace(",", "", $thisPaymentMethod['payment_amount']));
					if (empty($paymentAmount) || $paymentAmount > $chargedOrderTotal) {
						$paymentAmount = $chargedOrderTotal;
					}
					if (count($paymentMethodArray) > 1) {
						if (in_array($paymentMethodTypeCode, array("CREDIT_CARD", "BANK_ACCOUNT")) && $taxChargeDiscrepancy) {
							addProgramLog("Payment amount increased by " . $taxDiscrepancyAmount . " because of tax difference", $programLogId);
							$paymentAmount += $taxDiscrepancyAmount;
							$taxChargeDiscrepancy = false;
							$taxDiscrepancyAmount = 0;
							addProgramLog("New Payment Amount: " . $paymentAmount, $programLogId);
						}
					}
					$thisPaymentAmount = $paymentAmount;
					$thisTaxCharge = round(($taxCharge / $orderTotal) * $thisPaymentAmount, 2);
					$thisShippingCharge = round(($shippingCharge / $orderTotal) * $thisPaymentAmount, 2);
					if ($orderEntryCreated) {
						$thisHandlingCharge = round(($handlingCharge / $orderTotal) * $thisPaymentAmount, 2);
					} else {
						$thisHandlingCharge = $thisPaymentMethod['handling_charge'];
					}

					$thisPaymentAmount -= $thisTaxCharge;
					$thisPaymentAmount -= $thisShippingCharge;
					$thisPaymentAmount -= $thisHandlingCharge;
					addProgramLog("Payment Amount: " . $paymentAmount . " - cart: " . $thisPaymentAmount . ", tax: " . $thisTaxCharge . ", shipping: " . $thisShippingCharge . ", handling: " . $thisHandlingCharge, $programLogId);
					if ($validatePaymentOnly) {
						$validateAmount = (rand(100, 200) / 100);
						switch ($paymentMethodTypeCode) {
							case "BANK_ACCOUNT":
							case "CREDIT_CARD":
								if (empty($accountToken)) {
									$paymentArray = array("amount" => $validateAmount, "order_number" => $orderId, "description" => "Product Order",
										"first_name" => $thisPaymentMethod['billing_first_name'], "last_name" => $thisPaymentMethod['billing_last_name'], "business_name" => $thisPaymentMethod['billing_business_name'],
										"address_1" => $thisPaymentMethod['billing_address_1'], "city" => $thisPaymentMethod['billing_city'], "state" => $thisPaymentMethod['billing_state'],
										"postal_code" => $thisPaymentMethod['billing_postal_code'], "country_id" => $thisPaymentMethod['billing_country_id'],
										"email_address" => $shippingAddress['email_address'], "phone_number" => $_POST['phone_number'], "contact_id" => $contactId, "shipping_address_1" => $shippingAddress['address_1'],
										"shipping_address_2" => $shippingAddress['address_2'], "shipping_city" => $shippingAddress['city'], "shipping_state" => $shippingAddress['state'],
										"shipping_postal_code" => $shippingAddress['postal_code'], "shipping_country_id" => $shippingAddress['country_id']);
									if ($isBankAccount) {
										$paymentArray['bank_routing_number'] = $thisPaymentMethod['routing_number'];
										$paymentArray['bank_account_number'] = $thisPaymentMethod['bank_account_number'];
										$paymentArray['bank_account_type'] = strtolower(str_replace("_", "", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $thisPaymentMethod['payment_method_id'])));
									} else {
										$paymentArray['card_number'] = $thisPaymentMethod['account_number'];
										$paymentArray['expiration_date'] = $thisPaymentMethod['expiration_month'] . "/01/" . $thisPaymentMethod['expiration_year'];
										$paymentArray['card_code'] = ($GLOBALS['gInternalConnection'] && empty($thisPaymentMethod['cvv_code']) ? "SKIP_CARD_CODE" : $thisPaymentMethod['cvv_code']);
									}
									$success = $useECommerce->authorizeCharge($paymentArray);
									$response = $useECommerce->getResponse();
									if ($success) {
										$paymentArray['transaction_identifier'] = $response['transaction_id'];
										$useECommerce->voidCharge($paymentArray);
									} else {
										$returnArray['error_message'] = "Charge failed (2292): " . $response['response_reason_text'];
										$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
											"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
											"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
										addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Charge failed (2292): " . $response['response_reason_text'] . "\n" . jsonEncode($response) . "\n", $programLogId);
										$failedCreditCardCount++;
										setCachedData("failed_credit_card_names", $failedCreditCardNameKey, $failedCreditCardCount);
										ajaxResponse($returnArray);
										break;
									}
									$eCommerceResponse = array_merge($eCommerceResponse, $paymentArray, $response);
								} else {
									$useAccount = ($achMerchantAccountId && $isBankAccount ? (!empty($achECommerce) && $achECommerce->hasCustomerDatabase()) : $eCommerce->hasCustomerDatabase());
									if ($useAccount) {
										$accountMerchantIdentifier = getFieldFromId("merchant_identifier", "accounts", "account_id", $accountId);
										if (empty($accountMerchantIdentifier)) {
											$accountMerchantIdentifier = ($achMerchantAccountId && $isBankAccount ? $achMerchantIdentifier : $merchantIdentifier);
										}
										$billingAddressId = getFieldFromId("address_id", "accounts", "acccount_id", $accountId);
										$paymentArray = array("amount" => $validateAmount, "order_number" => $orderId, "description" => "Product Order",
											"merchant_identifier" => $accountMerchantIdentifier, "account_token" => $accountToken, "address_id" => $billingAddressId);
										$paymentArray['card_code'] = (empty($thisPaymentMethod['cvv_code']) ? "SKIP_CARD_CODE" : $thisPaymentMethod['cvv_code']);
										$success = $useECommerce->createCustomerProfileTransactionRequest($paymentArray);
										$response = $useECommerce->getResponse();
										if ($success) {
											$paymentArray['transaction_identifier'] = $response['transaction_id'];
											$useECommerce->voidCharge($paymentArray);
										} else {
											$returnArray['error_message'] = "Charge failed (2315): " . $response['response_reason_text'];
											$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
												"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
												"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
											addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Charge failed (2315): " . $response['response_reason_text'] . "\n" . jsonEncode($response), $programLogId);
											ajaxResponse($returnArray);
											break;
										}
										$eCommerceResponse = array_merge($eCommerceResponse, $response);
									}
								}
								$fullName = $thisPaymentMethod['billing_first_name'] . " " . $thisPaymentMethod['billing_last_name'] . (empty($thisPaymentMethod['billing_business_name']) ? "" : ", " . $thisPaymentMethod['billing_business_name']);
								if (empty($accountId)) {
									$resultSet = executeQuery("insert into accounts (contact_id,payment_method_id,full_name,account_number,expiration_date,default_payment_method) values (?,?,?,?,?, ?)",
										$contactId, $thisPaymentMethod['payment_method_id'], $fullName, ($saveAccountNumber ? $accountNumber : "XXXX-" . substr($accountNumber, -4)),
										(empty($thisPaymentMethod['expiration_year']) ? "" : date("Y-m-d", strtotime($thisPaymentMethod['expiration_month'] . "/01/" . $thisPaymentMethod['expiration_year']))),
										($canSaveAccount && $saveAccount ? 1 : 0));
									$accountId = $resultSet['insert_id'];
									if ($saveAccount && !empty($thisPaymentMethod['default_payment_method'])) {
										executeQuery("update accounts set default_payment_method = 0 where contact_id = ? and account_id <> ?", $contactId, $accountId);
									}
								}
								executeQuery("update orders set account_id = ? where order_id = ?", $accountId, $orderId);
								break;
							case "GIFT_CARD":
								$giftCard = new GiftCard(array("gift_card_number" => $accountNumber, "gift_card_pin" => $giftCardPin, "user_id" => $userId));
								if (!$giftCard->isValid()) {
									$returnArray['error_message'] = "Gift Card doesn't exist";
									$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
										"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
										"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
									addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Gift Card doesn't exist", $programLogId);
									ajaxResponse($returnArray);
									break;
								}
								break;
							case "LOYALTY_POINTS":
								if (empty($loyaltyProgramPointsRow)) {
									$returnArray['error_message'] = "No loyalty points available";
									$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
										"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
										"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
									addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Loyalty points do not exist", $programLogId);
									ajaxResponse($returnArray);
									break;
								}
								break;
						}
						$chargedOrderTotal = 0;
						continue;
					}
					switch ($paymentMethodTypeCode) {
						case "INVOICE":
						case "LAYAWAY":
							$dateDue = "";
							$invoiceDays = getPreference("LAYAWAY_INVOICE_DAYS");
							if (!empty($invoiceDays)) {
								$dateDue = date("Y-m-d", strtotime("+" . $invoiceDays . " days"));
							}
							$invoiceId = getFieldFromId("invoice_id", "invoices", "invoice_number", $orderId, "inactive = 0");
							if (!empty($invoiceId)) {
								$returnArray['error_message'] = "Layaway and invoices can only be used for one payment";
								$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
									"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
									"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
								addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Failed to create Order Payment: " . $orderObject->getErrorMessage(), $programLogId);
								ajaxResponse($returnArray);
								break;
							}
							$invoiceTypeId = getFieldFromId("invoice_type_id", "invoice_types", "invoice_type_code", $paymentMethodTypeCode);
							if (empty($invoiceTypeId) && $paymentMethodTypeCode == "LAYAWAY") {
								$insertSet = executeQuery("insert into invoice_types (client_id,invoice_type_code,description) values (?,?,?)", $GLOBALS['gClientId'], 'LAYAWAY', 'Layaway Invoice');
								$invoiceTypeId = $insertSet['insert_id'];
							}
							$insertSet = executeQuery("insert into invoices (client_id,invoice_number,contact_id,invoice_type_id,invoice_date,date_due) values (?,?,?,?,current_date,?)",
								$GLOBALS['gClientId'], $orderId, $contactId, $invoiceTypeId, $dateDue);
							if (!empty($insertSet['sql_error'])) {
								$returnArray['error_message'] = "Layaway and invoices can only be used for one payment";
								$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
									"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
									"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
								addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Failed to create Order Payment: " . $orderObject->getErrorMessage(), $programLogId);
								ajaxResponse($returnArray);
								break;
							}
							$invoiceId = $insertSet['insert_id'];
							$invoiceDetailsArray = array(array("description" => "Order #" . $orderId, "unit_price" => $thisPaymentAmount),
								array("description" => "Order #" . $orderId . " Tax", "unit_price" => $thisTaxCharge),
								array("description" => "Order #" . $orderId . " Shipping", "unit_price" => $thisShippingCharge),
								array("description" => "Order #" . $orderId . " Handling", "unit_price" => $thisHandlingCharge));
							foreach ($invoiceDetailsArray as $thisInvoiceDetail) {
								if ($thisInvoiceDetail["unit_price"] > 0) {
									$insertSet = executeQuery("insert into invoice_details (invoice_id,detail_date,description,amount,unit_price) values (?,current_date,?,1,?)",
										$invoiceId, $thisInvoiceDetail["description"], $thisInvoiceDetail["unit_price"]);
									if (!empty($insertSet['sql_error'])) {
										$returnArray['error_message'] = "Layaway and invoices can only be used for one payment";
										$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
											"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
											"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
										addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Failed to create Order Payment: " . $orderObject->getErrorMessage(), $programLogId);
										ajaxResponse($returnArray);
										break;
									}
								}
							}
							if (!$orderObject->createOrderPayment($thisPaymentAmount, array("payment_method_id" => $thisPaymentMethod['payment_method_id'], "invoice_id" => $invoiceId,
								"shipping_charge" => $thisShippingCharge, "tax_charge" => $thisTaxCharge, "handling_charge" => $thisHandlingCharge))) {
								$returnArray['error_message'] = "Failed to create Order Payment: " . $orderObject->getErrorMessage();
								$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
									"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
									"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
								addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Failed to create Order Payment: " . $orderObject->getErrorMessage(), $programLogId);
								ajaxResponse($returnArray);
								break;
							}
							addProgramLog("\nOrder ID " . $orderId . " paid with invoice #" . $invoiceId . ": Total Invoice: " . $paymentAmount . ", Order Payment of " . $thisPaymentAmount . ", shipping: " . $thisShippingCharge . ", tax: " . $thisTaxCharge . ", handling: " . $thisHandlingCharge, $programLogId);
							break;
						case "BANK_ACCOUNT":
						case "CREDIT_CARD":
							if (empty($accountToken)) {
								$paymentArray = array("amount" => $paymentAmount, "order_number" => $orderId, "description" => "Product Order",
									"first_name" => $thisPaymentMethod['billing_first_name'], "last_name" => $thisPaymentMethod['billing_last_name'], "business_name" => $thisPaymentMethod['billing_business_name'],
									"address_1" => $thisPaymentMethod['billing_address_1'], "city" => $thisPaymentMethod['billing_city'], "state" => $thisPaymentMethod['billing_state'],
									"postal_code" => $thisPaymentMethod['billing_postal_code'], "country_id" => $thisPaymentMethod['billing_country_id'],
									"email_address" => $shippingAddress['email_address'], "phone_number" => $_POST['phone_number'], "contact_id" => $contactId, "shipping_address_1" => $shippingAddress['address_1'],
									"shipping_address_2" => $shippingAddress['address_2'], "shipping_city" => $shippingAddress['city'], "shipping_state" => $shippingAddress['state'],
									"shipping_postal_code" => $shippingAddress['postal_code'], "shipping_country_id" => $shippingAddress['country_id']);
								if ($isBankAccount) {
									$paymentArray['bank_routing_number'] = $thisPaymentMethod['routing_number'];
									$paymentArray['bank_account_number'] = $thisPaymentMethod['bank_account_number'];
									$paymentArray['bank_account_type'] = strtolower(str_replace("_", "", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $thisPaymentMethod['payment_method_id'])));
								} else {
									$paymentArray['card_number'] = $thisPaymentMethod['account_number'];
									$paymentArray['expiration_date'] = $thisPaymentMethod['expiration_month'] . "/01/" . $thisPaymentMethod['expiration_year'];
									$paymentArray['card_code'] = ($GLOBALS['gInternalConnection'] && empty($thisPaymentMethod['cvv_code']) ? "SKIP_CARD_CODE" : $thisPaymentMethod['cvv_code']);
								}
								$paymentArray['order_items'] = array();
								foreach ($shoppingCartItems as $thisItem) {
									$paymentArray['order_items'][] = $thisItem;
								}
								$success = $useECommerce->authorizeCharge($paymentArray);
								$response = $useECommerce->getResponse();
								if ($success) {
									$chargedTransactionIdentifiers[] = array("ach" => ($achMerchantAccountId && $isBankAccount), "transaction_identifier" => $response['transaction_id']);
									if (!$orderObject->createOrderPayment($thisPaymentAmount, array("payment_method_id" => $thisPaymentMethod['payment_method_id'], "account_id" => $accountId,
										"authorization_code" => $response['authorization_code'], "transaction_identifier" => $response['transaction_id'], "shipping_charge" => $thisShippingCharge,
										"tax_charge" => $thisTaxCharge, "handling_charge" => $thisHandlingCharge))) {
										$returnArray['error_message'] = "Failed to create Order Payment: " . $orderObject->getErrorMessage();
										$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
											"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
											"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
										addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Failed to create Order Payment: " . $orderObject->getErrorMessage(), $programLogId);
										ajaxResponse($returnArray);
										break;
									}
								} else {
									$returnArray['error_message'] = "Charge failed (2408): " . $response['response_reason_text'];
									$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
										"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
										"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
									addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Charge failed (2408): " . $response['response_reason_text'] . "\n" . jsonEncode($response), $programLogId);
									$failedCreditCardCount++;
									setCachedData("failed_credit_card_names", $failedCreditCardNameKey, $failedCreditCardCount);
									ajaxResponse($returnArray);
									break;
								}
								$eCommerceResponse = array_merge($eCommerceResponse, $paymentArray, $response);
							} else {
								$useAccount = false;
								if ($achMerchantAccountId && $isBankAccount) {
									$useAccount = (!empty($achECommerce) && $achECommerce->hasCustomerDatabase());
								} else {
									$useAccount = ($eCommerce->hasCustomerDatabase());
								}
								if ($useAccount) {
									$paymentOrderItems = array();
									foreach ($shoppingCartItems as $thisItem) {
										$paymentOrderItems[] = $thisItem;
									}
									$accountMerchantIdentifier = getFieldFromId("merchant_identifier", "accounts", "account_id", $accountId);
									if (empty($accountMerchantIdentifier)) {
										$accountMerchantIdentifier = ($achMerchantAccountId && $isBankAccount ? $achMerchantIdentifier : $merchantIdentifier);
									}
									$billingAddressId = getFieldFromId("address_id", "accounts", "account_id", $accountId);
									$paymentArray = array("amount" => $paymentAmount, "order_number" => $orderId, "authorize_only" => ($paymentMethodTypeCode == "BANK_ACCOUNT" ? false : $preAuthOnly),
										"merchant_identifier" => $accountMerchantIdentifier, "account_token" => $accountToken, "order_items" => $paymentOrderItems, "address_id" => $billingAddressId);
									$paymentArray['card_code'] = (empty($thisPaymentMethod['cvv_code']) ? "SKIP_CARD_CODE" : $thisPaymentMethod['cvv_code']);
									$success = $useECommerce->createCustomerProfileTransactionRequest($paymentArray);
									$response = $useECommerce->getResponse();
									if ($success) {
										$chargedTransactionIdentifiers[] = array("ach" => ($achMerchantAccountId && $isBankAccount), "transaction_identifier" => $response['transaction_id']);
										if (!$orderObject->createOrderPayment($thisPaymentAmount, array("payment_method_id" => $thisPaymentMethod['payment_method_id'], "account_id" => $accountId,
											"authorization_code" => $response['authorization_code'], "transaction_identifier" => $response['transaction_id'], "shipping_charge" => $thisShippingCharge,
											"tax_charge" => $thisTaxCharge, "handling_charge" => $thisHandlingCharge, "not_captured" => ($paymentMethodTypeCode == "BANK_ACCOUNT" ? false : $preAuthOnly)))) {
											$returnArray['error_message'] = "Failed to create Order Payment: " . $orderObject->getErrorMessage();
											$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
												"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
												"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
											addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Failed to create Order Payment: " . $orderObject->getErrorMessage(), $programLogId);
											ajaxResponse($returnArray);
											break;
										}
									} else {
										$returnArray['error_message'] = "Charge failed (2450): " . $response['response_reason_text'];
										$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
											"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
											"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
										addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Charge failed (2450): " . $response['response_reason_text'] . "\n" . jsonEncode($response), $programLogId);
										ajaxResponse($returnArray);
										break;
									}
									$eCommerceResponse = array_merge($eCommerceResponse, $response);
								}
							}
							break;
						case "LOAN":
							if (function_exists("_localPaymentMethodLoanProcessing")) {
								$loanAmountAvailable = _localPaymentMethodLoanProcessing($accountNumber, $orderTotal, $orderId);

								if (empty($loanAmountAvailable) || $loanAmountAvailable == 0 || !is_numeric($loanAmountAvailable)) {
									$returnArray['error_message'] = (is_numeric($loanAmountAvailable) ? "Loan doesn't exist" : $loanAmountAvailable);
									$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
										"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
										"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
									addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . (is_numeric($loanAmountAvailable) ? "Loan has no balance" : $loanAmountAvailable), $programLogId);
									ajaxResponse($returnArray);
									break;
								}
								if ($loanAmountAvailable < $paymentAmount) {
									addProgramLog("Attempt to charge more than loan balance: " . $loanAmountAvailable . ":" . $paymentAmount);
									$paymentAmount = $loanAmountAvailable;
									$thisPaymentAmount = $paymentAmount;
									$thisTaxCharge = round(($taxCharge / $orderTotal) * $thisPaymentAmount, 2);
									$thisShippingCharge = round(($shippingCharge / $orderTotal) * $thisPaymentAmount, 2);
									$thisHandlingCharge = $thisPaymentMethod['handling_charge'];
									$thisPaymentAmount -= $thisTaxCharge;
									$thisPaymentAmount -= $thisShippingCharge;
									$thisPaymentAmount -= $thisHandlingCharge;
								}
							}
							if (!$orderObject->createOrderPayment($thisPaymentAmount, array("payment_method_id" => $thisPaymentMethod['payment_method_id'], "account_id" => $accountId,
								"shipping_charge" => $thisShippingCharge, "tax_charge" => $thisTaxCharge, "handling_charge" => $thisHandlingCharge, "reference_number" => $thisPaymentMethod['reference_number']))) {
								$returnArray['error_message'] = "Failed to create Order Payment: " . $orderObject->getErrorMessage();
								$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
									"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
									"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
								addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Failed to create Order Payment: " . $orderObject->getErrorMessage(), $programLogId);
								ajaxResponse($returnArray);
								break;
							}
							break;
						case "GIFT_CARD":
							$giftCard = new GiftCard(array("gift_card_number" => $accountNumber, "gift_card_pin" => $giftCardPin, "user_id" => $userId));
							if (!$giftCard->isValid()) {
								$returnArray['error_message'] = "Gift Card doesn't exist";
								$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
									"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
									"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
								addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Gift Card doesn't exist", $programLogId);
								ajaxResponse($returnArray);
								break;
							}
							$balance = $giftCard->getBalance();
							if ($balance < $paymentAmount) {
								addProgramLog("\nAttempt to charge more than card balance: " . $balance . ":" . $paymentAmount, $programLogId);
								$paymentAmount = $balance;
								$thisPaymentAmount = $paymentAmount;
								$thisTaxCharge = round(($taxCharge / $orderTotal) * $thisPaymentAmount, 2);
								$thisShippingCharge = round(($shippingCharge / $orderTotal) * $thisPaymentAmount, 2);
								$thisHandlingCharge = $thisPaymentMethod['handling_charge'];
								$thisPaymentAmount -= $thisTaxCharge;
								$thisPaymentAmount -= $thisShippingCharge;
								$thisPaymentAmount -= $thisHandlingCharge;
							}
							if (!$giftCard->adjustBalance(false, ($paymentAmount * -1), "Usage for order", $orderId)) {
								$returnArray['error_message'] = "Gift card error: " . $giftCard->getErrorMessage();
								$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
									"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
									"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
								addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . $giftCard->getErrorMessage(), $programLogId);
								ajaxResponse($returnArray);
								break;
							}
							if (!$orderObject->createOrderPayment($thisPaymentAmount, array("payment_method_id" => $thisPaymentMethod['payment_method_id'], "account_id" => $accountId, "payment_time" => $thisPaymentMethod['payment_time'],
								"shipping_charge" => $thisShippingCharge, "tax_charge" => $thisTaxCharge, "handling_charge" => $thisHandlingCharge, "reference_number" => $thisPaymentMethod['reference_number']))) {
								$returnArray['error_message'] = "Failed to create Order Payment: " . $orderObject->getErrorMessage();
								$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
									"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
									"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
								addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Failed to create Order Payment: " . $orderObject->getErrorMessage(), $programLogId);
								ajaxResponse($returnArray);
								break;
							}
							break;
						case "LOYALTY_POINTS":
							if (empty($loyaltyProgramPointsRow)) {
								$returnArray['error_message'] = "No loyalty points available";
								$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
									"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
									"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
								addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "No loyalty points available", $programLogId);
								ajaxResponse($returnArray);
								break;
							}

							if ($pointDollarsAvailable < $paymentAmount) {
								addProgramLog("\nAttempt to redeem more point dollars than available: " . $pointDollarsAvailable . ":" . $paymentAmount, $programLogId);
								$paymentAmount = $pointDollarsAvailable;
								$thisPaymentAmount = $paymentAmount;
								$thisTaxCharge = round(($taxCharge / $orderTotal) * $thisPaymentAmount, 2);
								$thisShippingCharge = round(($shippingCharge / $orderTotal) * $thisPaymentAmount, 2);
								$thisHandlingCharge = $thisPaymentMethod['handling_charge'];
								$thisPaymentAmount -= $thisTaxCharge;
								$thisPaymentAmount -= $thisShippingCharge;
								$thisPaymentAmount -= $thisHandlingCharge;
							}
							$pointsUsed = $paymentAmount / $pointDollarValue;

							$updateSet = executeQuery("update loyalty_program_points set point_value = ? where loyalty_program_point_id = ? and point_value = ?", max(0, ($loyaltyProgramPointsRow['point_value'] - $pointsUsed)), $loyaltyProgramPointsRow['loyalty_program_point_id'], $loyaltyProgramPointsRow['point_value']);
							if (!empty($updateSet['sql_error']) || $updateSet['affected_rows'] == 0) {
								$returnArray['error_message'] = getSystemMessage("basic", $updateSet['sql_error']);
								$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
									"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
									"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
								addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . getSystemMessage("basic", $updateSet['sql_error']) . ", affected rows: " . $updateSet['affected_rows'], $programLogId);
								ajaxResponse($returnArray);
								break;
							}
							$loyaltyProgramPointsRow['point_value'] = ($loyaltyProgramPointsRow['point_value'] - $pointsUsed);
							$pointsUsed = $pointsUsed * -1;
							executeQuery("insert into loyalty_program_point_log (loyalty_program_point_id,log_time,order_id,point_value) values (?,now(),?,?)", $loyaltyProgramPointsRow['loyalty_program_point_id'], $orderId, $pointsUsed);
						default:
							if (empty($thisPaymentMethod['payment_method_id'])) {
								$returnArray['error_message'] = "No payment method for order payment";
								$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
									"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
									"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
								addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Failed to create Order Payment: " . $orderObject->getErrorMessage(), $programLogId);
								ajaxResponse($returnArray);
								break;
							}
							if (!$orderObject->createOrderPayment($thisPaymentAmount, array("payment_method_id" => $thisPaymentMethod['payment_method_id'], "account_id" => $accountId, "payment_time" => $thisPaymentMethod['payment_time'],
								"shipping_charge" => $thisShippingCharge, "tax_charge" => $thisTaxCharge, "handling_charge" => $thisHandlingCharge, "reference_number" => $thisPaymentMethod['reference_number']))) {
								$returnArray['error_message'] = "Failed to create Order Payment: " . $orderObject->getErrorMessage();
								$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
									"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
									"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
								addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Failed to create Order Payment: " . $orderObject->getErrorMessage(), $programLogId);
								ajaxResponse($returnArray);
								break;
							}
							break;
					}
					$chargedOrderTotal = round($chargedOrderTotal - $paymentAmount, 2);
					$eCommerceResponses[] = $eCommerceResponse;
				}

				if ($chargedOrderTotal >= 0.01) {
					$returnArray['error_message'] = "Unable to fully pay for the order with the supplied payment methods" . ($GLOBALS['gUserRow']['superuser_flag'] ? ": " . $chargedOrderTotal : "");
					$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
						"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
						"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
					addProgramLog("\nOrder ID " . $orderId . " unable to be completed: " . "Unable to fully pay for the order with the supplied payment methods: " . $chargedOrderTotal, $programLogId);
					ajaxResponse($returnArray);
					break;
				}

				if (!empty($noFraudToken)) {
					if ($preAuthOnly) {
						$noFraud = new NoFraud($noFraudToken);
						$orderRequiresFraudCheck = $noFraud->orderRequiresFraudCheck($orderId);
						$noFraudPassed = false;
						if ($orderRequiresFraudCheck) {
							$result = $noFraud->getDecision($orderId, $eCommerceResponses);
							if (!empty($result)) {
								if ($result['decision'] == "fail") {
									$returnArray['error_message'] = $this->getFragment("ORDER_RESPONSE_FRAUD") ?: "Unable to complete order because of fraud risk";
									$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
										"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
										"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
									addProgramLog("\nOrder ID " . $orderId . " unable to be completed because fraud check failed: " . jsonEncode($result), $programLogId);
									ajaxResponse($returnArray);
									break;
								}
								CustomField::setCustomFieldData($orderId, "NOFRAUD_RESULT", jsonEncode($result), "ORDERS");
								addProgramLog("NoFraud decision: " . $result['decision'] . "\n" . jsonEncode($result), $programLogId);
								$noFraudPassed = ($result['decision'] == "pass");
							} else {
								addProgramLog("Fraud check returned error: " . $noFraud->getErrorMessage(), $programLogId);
							}
						} else {
							addProgramLog("Fraud check not performed because no products in order require fraud checks.", $programLogId);
						}
						if (empty(getPreference("RETAIL_STORE_CAPTURE_AT_SHIPMENT")) && ($noFraudPassed || !$orderRequiresFraudCheck)) {
							$captureResult = Order::capturePayment($orderId);
							if (!empty($captureResult['error_message'])) {
								$returnArray['error_message'] = "Unable to complete order because capturing payment failed";
								$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
									"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
									"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
								addProgramLog("\nOrder ID " . $orderId . " unable to be completed because capturing payment failed: " . jsonEncode($captureResult), $programLogId);
								ajaxResponse($returnArray);
								break;
							}
						}
					} else {
						addProgramLog("Fraud check not performed because Merchant Account does not support preauth only transactions.", $programLogId);
					}
				}

				if (function_exists("_localServerFinalOrderProcessing")) {
					$response = _localServerFinalOrderProcessing($orderId);
					if ($response !== true) {
						$returnArray['error_message'] = (is_array($response) && !empty($response['error_message']) ? $response['error_message'] : "Unable to complete order");
						$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
							"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
							"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
						addProgramLog("\nOrder ID " . $orderId . " unable to be completed because local final processing failed" . (is_array($response) && !empty($response['error_message']) ? ": " . $response['error_message'] : ""), $programLogId);
						if (function_exists("_localServerFinalOrderProcessingFailure")) {
							$shoppingCart = ShoppingCart::getShoppingCart("RETAIL");
							$responseArray = _localServerFinalOrderProcessingFailure($shoppingCart, $response);
							if (is_array($responseArray)) {
								$returnArray = array_merge($returnArray, $responseArray);
							}
						}
						if (empty($returnArray['error_message'])) {
							$returnArray['error_message'] = "Unable to process order";
						}
						ajaxResponse($returnArray);
						break;
					}
				}

# award points for order

				if (!empty($loyaltyProgramPointsRow['loyalty_program_point_id']) && $cartTotal > 0 && $cartTotal > $discountAmount) {
					$pointAwards = 0;
					$netCartTotal = $cartTotal - $discountAmount;
					$calculationPercentage = $netCartTotal / $cartTotal;
					$resultSet = executeQuery("select max(point_value) from loyalty_program_awards where loyalty_program_id = ? and minimum_amount <= ?", $loyaltyProgramRow['loyalty_program_id'], $netCartTotal);
					if ($row = getNextRow($resultSet)) {
						$pointAwards = $row['max(point_value)'];
					}
					$pointsEarned = 0;
					addProgramLog("\nCalculating Points Earned:", $programLogId);
					addProgramLog("Points per $100:", $pointAwards, $programLogId);
					foreach ($shoppingCartItems as $thisItem) {
						$pointsMultiplier = getFieldFromId("points_multiplier", "products", "product_id", $thisItem['product_id']);
						if (empty($pointsMultiplier) || $pointsMultiplier < 1) {
							$pointsMultiplier = 1;
						}
						$resultSet = executeQuery("select points_multiplier from product_categories where points_multiplier > 1 and product_category_id in " .
							"(select product_category_id from product_category_links where product_id = ?)", $thisItem['product_id']);
						while ($row = getNextRow($resultSet)) {
							if ($row['points_multiplier'] > $pointsMultiplier) {
								$pointsMultiplier = $row['points_multiplier'];
							}
						}
						$resultSet = executeQuery("select points_multiplier from product_tags where points_multiplier > 1 and product_tag_id in " .
							"(select product_tag_id from product_tag_links where product_id = ? and (start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date >= current_date))",
							$thisItem['product_id']);
						while ($row = getNextRow($resultSet)) {
							if ($row['points_multiplier'] > $pointsMultiplier) {
								$pointsMultiplier = $row['points_multiplier'];
							}
						}
						$thisPointsEarned = ($thisItem['quantity'] * $thisItem['sale_price']) * $pointsMultiplier * $pointAwards * $calculationPercentage / 100;
						addProgramLog("Product ID " . $thisItem['product_id'] . " earned " . $thisPointsEarned . ($pointsMultiplier == 1 ? "" : ", multiplier " . $pointsMultiplier), $programLogId);
						$pointsEarned += $thisPointsEarned;
					}
					addProgramLog("Total Points Earned: " . $pointsEarned, $programLogId);

					if ($pointsEarned > 0) {
						$updateSet = executeQuery("update loyalty_program_points set point_value = greatest(0,point_value + ?) where loyalty_program_point_id = ?", $pointsEarned, $loyaltyProgramPointsRow['loyalty_program_point_id']);
						if (empty($updateSet['sql_error']) && $updateSet['affected_rows'] > 0) {
							executeQuery("insert into loyalty_program_point_log (loyalty_program_point_id,log_time,order_id,point_value) values (?,now(),?,?)", $loyaltyProgramPointsRow['loyalty_program_point_id'], $orderId, $pointsEarned);
						}
					}
					$loyaltyProgramPointsRow['point_value'] += $pointsEarned;
					$substitutions['loyalty_points_earned'] = $pointsEarned;
				}
				$substitutions['loyalty_points_total'] = $loyaltyProgramPointsRow['point_value'];


# Create the notifications

				$substitutions['domain_name'] = getDomainName();
				$substitutions['store_name'] = $GLOBALS['gClientName'];
				$substitutions = array_merge(Order::getOrderItemsSubstitutions($orderId), $substitutions);
				$noPriceSubstitutions = Order::getOrderItemsSubstitutions($orderId, false);
				$substitutions['order_items_no_prices'] = $noPriceSubstitutions['order_items'];
				$substitutions['order_items_table_no_prices'] = $noPriceSubstitutions['order_items_table'];

				$orderPayments = "";
				$orderPaymentsTable = "<table id='order_payments_table'><tr><th class='payment_method-header'>Payment Method</th>" .
					"<th class='payment-amount-header'>Amount</th></tr>";
				$resultSet = executeQuery("select * from order_payments where order_id = ?", $orderId);
				while ($thisPayment = getNextRow($resultSet)) {
					if ($resultSet['row_count'] > 1) {
						$substitutions['payment_method'] = "Multiple methods";
					} else {
						$substitutions['payment_method'] = getFieldFromId("account_label", "accounts", "account_id", $row['account_id'])
							?: getFieldFromId("description", "payment_methods", "payment_method_id", $row['payment_method_id']);
					}
					$orderPayments .= "<div class='order-payment-line'><span class='payment-method'>" . getFieldFromId("description", "payment_methods", "payment_method_id", $thisPayment['payment_method_id']) . "</span>" .
						"<span class='payment-amount'>" . number_format($thisPayment['amount'] + $thisPayment['shipping_charge'] + $thisPayment['tax_charge'] + $thisPayment['handling_charge'], 2) . "</span>" .
						"</div>";
					$orderPaymentsTable .= "<tr class='order-payment-row'><td class='payment-method'>" . getFieldFromId("description", "payment_methods", "payment_method_id", $thisPayment['payment_method_id']) . "</td>" .
						"<td class='payment-amount'>" . number_format($thisPayment['amount'] + $thisPayment['shipping_charge'] + $thisPayment['tax_charge'] + $thisPayment['handling_charge'], 2) . "</td></tr>";
				}
				$orderPaymentsTable .= "</table>";
				$substitutions['order_payments'] = $orderPayments;
				$substitutions['payment_table'] = $substitutions['order_payments_table'] = $orderPaymentsTable;
				if (function_exists("_localServerAdditionalOrderSubstitutions")) {
					$substitutions = array_merge($substitutions, _localServerAdditionalOrderSubstitutions($orderId));
				}

				if ($orderEntryCreated) {
					$responseContent = "Order ID " . $orderId . " created";
				} else {
					$responseContent = $this->getFragment("RETAIL_STORE_ORDER_RESPONSE", $substitutions);
				}
				if (empty($responseContent)) {
					$responseContent = "<p class='align-center'>Your order has been placed.</p><p class='align-center'>Check your email for the order receipt and confirmation.</p>";
				}
				$responseContent = PlaceHolders::massageContent($responseContent, $substitutions);
				if ($GLOBALS['gInternalConnection'] && canAccessPageCode("RETAILSTOREPAPERRECEIPT") && !$orderEntryCreated) {
					$responseContent .= "<p><a href='/paper-receipt?order_id=" . base64_encode($orderId) . "' target='_blank'>Paper Receipt</a></p>";
				}

				if (!$orderEntryCreated) {
					$newCustomer = true;
					$resultSet = executeQuery("select count(*) from contacts where contact_id = ?", $contactRow['contact_id']);
					if ($row = getNextRow($resultSet)) {
						$newCustomer = ($row['count(*)'] <= 1);
					}
					ob_start();
					?>
					<script>
                        const orderData = {
                            customerFirstName: atob('<?= base64_encode($contactRow['first_name']) ?>'),
                            customerLastName: atob('<?= base64_encode($contactRow['last_name']) ?>'),
                            customerEmail: atob('<?= base64_encode($shippingAddress['email_address']) ?>'),
                            customerState: atob('<?= base64_encode($shippingAddress['state']) ?>'),
                            customerCountry: atob('<?= base64_encode(getFieldFromId("country_code", "countries", "country_id", $shippingAddress['country_id'])) ?>'),
                            orderId: atob('<?= base64_encode($orderId) ?>'),
                            orderTotal: <?= $orderTotal ?>,
                            promotionCode: atob('<?= base64_encode($promotionRow['promotion_code']) ?>'),
                            cartTotal: <?= $cartTotal ?>,
                            shippingCharge: <?= $shippingCharge ?>,
                            taxCharge: <?= $taxCharge ?>,
                            handlingCharge: <?= $handlingCharge ?>,
                            newCustomer: '<?= ($newCustomer ? "Y" : "N") ?>',
                            orderItems: [
								<?php
								$count = 0;
								foreach ($shoppingCartItems as $thisItem) {
								$productRow = ProductCatalog::getCachedProductRow($thisItem['product_id']);
								$productDataRow = getRowFromId("product_data", "product_id", $thisItem['product_id']);
								$categoryResult = executeReadQuery("select description from product_categories where product_category_id ="
									. " (select product_category_id from product_category_links where product_id = ? order by sequence_number limit 1)", $productRow['product_id']);
								if ($categoryRow = getNextRow($categoryResult)) {
									$productCategory = str_replace("'", "", $categoryRow['description']);
								} else {
									$productCategory = "";
								}
								?>
								<?= ($count > 0 ? "," : "") ?>{
                                    productId: atob('<?= base64_encode($thisItem['product_id']) ?>'),
                                    productCode: atob('<?= base64_encode($productRow['product_code']) ?>'),
                                    upcCode: atob('<?= base64_encode($productDataRow['upc_code']) ?>'),
                                    productManufacturer: atob('<?= base64_encode(getFieldFromId("description", "product_manufacturers", "product_manufacturer_id", $productRow['product_manufacturer_id'])) ?>'),
                                    productCategory: atob('<?= base64_encode($productCategory) ?>'),
                                    description: atob('<?= base64_encode(str_replace("'", "", $productRow['description'])) ?>'),
                                    salePrice: <?= $thisItem['sale_price'] ?>,
                                    quantity: <?= $thisItem['quantity'] ?>
                                }
								<?php
								$count++;
								}
								?>
                            ]
                        }
						<?php if (stristr($responseContent, "orderData") === false) {
							echo 'sendAnalyticsEvent("purchase", orderData);';
						} ?>
					</script>
					<?php

					$responseContent = ob_get_clean() . "\n" . $responseContent;
				}
				$returnArray['response'] = $responseContent;

				setCoreCookie("shopping_cart_id", "", 0);
				$_COOKIE['shopping_cart_id'] = "";

				$orderRow = getRowFromId("orders", "order_id", $orderId);
				if (empty($orderRow)) {
					$returnArray['error_message'] = "Order unable to be completed. Please try again.";
					$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
						"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
						"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
					addProgramLog("\nOrder ID " . $orderId . " unable to be completed: No order just before commit", $programLogId);
					ajaxResponse($returnArray);
					break;
				}
				$commitSet = $GLOBALS['gPrimaryDatabase']->commitTransaction();
				if (!$commitSet || !empty($commitSet['sql_error'])) {
					$returnArray['error_message'] = "Order unable to be completed. Please try again.";
					$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
						"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
						"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
					addProgramLog("\nOrder ID " . $orderId . " unable to be completed: Commit failure: " . $commitSet['sql_error'], $programLogId);
					ajaxResponse($returnArray);
					break;
				}
				if (!$validatePaymentOnly) {
					$resultSet = executeQuery("select sum(amount + shipping_charge + tax_charge + handling_charge) order_total from order_payments where order_id = ?", $orderId);
					if ($row = getNextRow($resultSet)) {
						if (abs($orderTotal - $row['order_total']) < .01) {
							executeQuery("update orders set order_status_id = null where order_id = ?", $orderId);
						} else {
							$GLOBALS['gPrimaryDatabase']->logError("Insufficient Order Payments, should be: " . $orderTotal . ", is: " . $row['order_total']);
						}
					}
				}

				$orderRow = getRowFromId("orders", "order_id", $orderId);
				if (empty($orderRow)) {
					$returnArray['error_message'] = "Order unable to be completed. Please try again.";
					$this->rollbackOrder(array("contact_id" => $contactId, "created_merchant_accounts" => $createdMerchantAccounts,
						"charged_transaction_identifiers" => $chargedTransactionIdentifiers, "e_commerce" => $eCommerce, "ach_e_commerce" => $achECommerce, "donation_e_commerce" => $donationsECommerce,
						"merchant_identifier" => $merchantIdentifier, "ach_merchant_identifier" => $achMerchantIdentifier, "error_message" => $returnArray['error_message'], "order_id" => $orderId));
					addProgramLog("\nOrder ID " . $orderId . " unable to be completed: No order just after     commit: " . $commitSet['sql_error'], $programLogId);
					ajaxResponse($returnArray);
					break;
				}
				addProgramLog("\nOrder Completed, ID " . $orderId, $programLogId);

				executeQuery("delete from product_map_overrides where shopping_cart_id = ?", $shoppingCart->getShoppingCartId());
				$resultSet = executeQuery("select * from shopping_cart_items where shopping_cart_id = ?", $shoppingCart->getShoppingCartId());
				while ($row = getNextRow($resultSet)) {
					executeQuery("delete from shopping_cart_item_addons where shopping_cart_item_id = ?", $row['shopping_cart_item_id']);
					executeQuery("delete from shopping_cart_items where shopping_cart_item_id = ?", $row['shopping_cart_item_id']);
				}
				executeQuery("delete from shopping_carts where shopping_cart_id = ?", $shoppingCart->getShoppingCartId());

				$body = "";
				$subject = "";
				$emailId = getFieldFromId("email_id", "shipping_methods", "shipping_method_id", $_POST['shipping_method_id']);
				if (empty($emailId)) {
					$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_ORDER_CONFIRMATION", "inactive = 0");
				}
				if (empty($emailId)) {
					$body = getFieldFromId("content", "emails", "email_code", "DEFAULT_RETAIL_STORE_ORDER_CONFIRMATION", "client_id = ?", $GLOBALS['gDefaultClientId']);
					$subject = getFieldFromId("subject", "emails", "email_code", "DEFAULT_RETAIL_STORE_ORDER_CONFIRMATION", "client_id = ?", $GLOBALS['gDefaultClientId']);
				}

				if ($fflRequired && empty($_POST['federal_firearms_licensee_id'])) {
					$substitutions['need_ffl_dealer'] = getFragment("RETAIL_STORE_NEED_FFL_DEALER");
				} else {
					$substitutions['need_ffl_dealer'] = "";
				}
				if (empty($shippingAddress['email_address'])) {
					$shippingAddress['email_address'] = $contactRow['email_address'];
				}
				$emailAddresses = array($shippingAddress['email_address']);
				$copyFFLDealer = getPreference("COPY_FFL_DEALER_CONFIRMATION");
				$bccEmailAddresses = array();
				$fflEmailAddresses = array();
				$fflEmailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_ORDER_CONFIRMATION_FOR_FFL", "inactive = 0");
				if (($copyFFLDealer || !empty($fflEmailId)) && $fflRequired && !empty($_POST['federal_firearms_licensee_id'])) {
					$row = (new FFL(array("federal_firearms_licensee_id" => $_POST['federal_firearms_licensee_id'], "only_if_valid" => true)))->getFFLRow();
					if ($row) {
						if (!empty($row['email_address'])) {
							if ($copyFFLDealer) {
								$bccEmailAddresses[] = $row['email_address'];
							}
							if (!empty($fflEmailId)) {
								$fflEmailAddresses[] = $row['email_address'];
							}
						}
					}
				}

				if (empty($body) && empty($emailId)) {
					addProgramLog("\nNo order confirmation email found", $programLogId);
				} else {
					$emailResult = sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "contact_id" => $contactId, "email_addresses" => $emailAddresses, "bcc_addresses" => $bccEmailAddresses));
					if ($emailResult !== true) {
						addProgramLog("\nUnable to send email: " . $emailResult, $programLogId);
					}
				}
				if (!empty($fflEmailId) && !empty($fflEmailAddresses)) {
					$emailResult = sendEmail(array("email_id" => $fflEmailId, "substitutions" => $substitutions, "email_addresses" => $fflEmailAddresses));
				}

				$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_ORDER_NOTIFICATION", "inactive = 0");
				if (!empty($emailId)) {
					$emailAddresses = getNotificationEmails("RETAIL_STORE_ORDER_NOTIFICATION");
					$pickup = getReadFieldFromId("pickup", "shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
					if (!$pickup || Order::hasPhysicalProducts($orderId)) {
						$resultSet = executeQuery("select email_address from shipping_method_notifications where shipping_method_id = ?", $_POST['shipping_method_id']);
						while ($row = getNextRow($resultSet)) {
							$emailAddresses[] = $row['email_address'];
						}
					}
					$emailResult = sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_addresses" => $emailAddresses));
					if (Order::hasPhysicalProducts($orderId)) {
						$emailAddresses = getNotificationEmails("RETAIL_STORE_PHYSICAL_PRODUCT_ORDER");
						$emailResult = sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_addresses" => $emailAddresses));
					}
				}

				$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_FFL_ORDER_NOTIFICATION", "inactive = 0");
				if (!empty($emailId)) {
					$emailAddresses = array();

					if ($fflRequired && !empty($_POST['federal_firearms_licensee_id'])) {
						$row = (new FFL(array("federal_firearms_licensee_id" => $_POST['federal_firearms_licensee_id'], "only_if_valid" => true)))->getFFLRow();
						if ($row) {
							if (!empty($row['email_address'])) {
								$emailAddresses[] = $row['email_address'];
							}
							$emailResult = sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_addresses" => $emailAddresses));
						}
					}
				}

				$resultSet = executeQuery("select * from order_items join order_item_addons using (order_item_id) where order_id = ? and " .
					"product_addon_id in (select product_addon_id from product_addons where inventory_product_id is not null)", $orderId);
				while ($row = getNextRow($resultSet)) {
					$productAddonRow = getRowFromId("product_addons", "product_addon_id", $row['product_addon_id']);
					executeQuery("insert into order_items (order_id,product_id,description,quantity,sale_price) values (?,?,?,?,?)",
						$orderId, $productAddonRow['inventory_product_id'], $productAddonRow['description'] . " (Product Addon)", $row['quantity'], 0);
				}

				Order::processOrderItems($orderId, array("product_additional_charges" => $productAdditionalCharges));
				Order::processOrderAutomation($orderId);
				if (function_exists("_localServerProcessOrder")) {
					_localServerProcessOrder($orderId);
				}
				if (!empty($credovaLoanRow['credova_loan_id'])) {
					$orderReceiptContent = Order::getOrderReceipt($orderId);
					ob_start();
					?>
					<html lang="en">
					<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
					<head>
						<title>Order Receipt</title>
						<link type="text/css" rel="stylesheet" href="file:///<?= $GLOBALS['gDocumentRoot'] ?>/css/reset.css"/>
						<link type="text/css" rel="stylesheet" href="file:///<?= $GLOBALS['gDocumentRoot'] ?>/fontawesome-core/css/all.min.css"/>
						<style>
                            html {
                                font-family: "Helvetica", sans-serif;
                            }

                            #_report_title {
                                width: 880px;
                                font-size: 22px;
                            }

                            #_report_content {
                                width: 880px;
                                padding: 20px;
                            }

                            #_report_title.landscape {
                                width: 1100px;
                            }

                            #_report_content.landscape {
                                width: 1100px;
                            }

                            a {
                                text-decoration: none;
                            }

                            p {
                                font-size: 10px;
                                padding-bottom: 5px;
                            }

                            td {
                                font-size: 10px;
                                page-break-inside: avoid;
                            }

                            th {
                                font-size: 10px;
                                font-weight: bold;
                            }

                            tr {
                                page-break-inside: avoid;
                            }

                            hr {
                                height: 2px;
                                color: rgb(150, 150, 150);
                                background-color: rgb(150, 150, 150);
                            }

                            td, th {
                                padding: 5px;
                            }

                            h1 {
                                font-size: 18px;
                                text-align: center;
                                width: 740px;
                                color: rgb(40, 40, 40);
                            }

                            h2 {
                                font-size: 15px;
                                font-weight: bold;
                            }

                            h3 {
                                font-size: 13px;
                                font-weight: bold;
                            }

                            ul {
                                padding-left: 20px;
                                list-style-type: disc;
                                font-size: 10px;
                                padding-bottom: 10px;
                            }

                            ul li {
                                list-style-type: disc;
                                font-size: 10px;
                            }

                            .grid-table tr:nth-child(odd) td {
                                background-color: rgb(240, 240, 240);
                            }

                            .grid-table tr.thick-top td {
                                border-top-width: 4px;
                            }

                            .grid-table tr.thick-top-black td {
                                border-top: 4px solid rgb(0, 0, 0);
                            }

                            .printable-only {
                                display: block;
                            }
						</style>
					</head>
					<body>
					<h1 id="_report_title">Order Receipt</h1>
					<div id="_report_content">
						<?= $orderReceiptContent ?>
					</div>
					</body>
					</html>
					<?php
					$orderHtml = ob_get_clean();

					$headers = array(
						"Content-Type: multipart/form-data",
						"Authorization: Bearer " . $credovaLoanRow['authentication_token']
					);

					$filename = outputPDF($orderHtml, array("get_filename" => true));
					$fields = array(
						"file" => curl_file_create($filename, "application/pdf", "invoice.pdf")
					);

					$ch = curl_init();
					curl_setopt($ch, CURLOPT_URL, "https://" . ($credovaTest ? "sandbox-" : "") . "lending-api.credova.com/v2/applications/" . urlencode($credovaLoanRow['public_identifier']) . "/uploadinvoice");
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
					curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
					curl_setopt($ch, CURLOPT_POST, TRUE);
					curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);

					$response = curl_exec($ch);
					if (file_exists($filename)) {
						unlink($filename);
					}
				}

				if (!empty($_POST['captcha_code_id'])) {
					executeQuery("delete from captcha_codes where captcha_code_id = ?", $_POST['captcha_code_id']);
				}
				Order::notifyCRM($orderId);

				if (!empty($_POST['order_note'])) {
					if (empty($userId)) {
						$_POST['order_note'] = "NOTE FROM CUSTOMER: \n\n" . $_POST['order_note'];
					}
					executeQuery("insert into order_notes (order_id,user_id,time_submitted,content, public_access) values (?,?,now(),?,1)", $orderId, $orderNoteUserId, $_POST['order_note']);
				}

				$customerOrderFile = getPreference("CUSTOMER_ORDER_FILE");
				if (array_key_exists("order_file_upload", $_FILES) && !empty($customerOrderFile)) {
					$fileId = createFile("order_file_upload");
					if (!empty($fileId)) {
						executeQuery("insert into order_files (order_id,description,file_id) values (?,?,?)", $orderId, $customerOrderFile, $fileId);
					}
				}

				// C&R Processing
				if ($fflRequired && $allFFLProductCRRequired) {
					if (array_key_exists("cr_license_file_upload", $_FILES)) {
						$crLicenseFileId = createFile("cr_license_file_upload");
						if (!empty($crLicenseFileId)) {
							executeQuery("insert into order_files (order_id, description, file_id) values (?,?,?)", $orderId, "C&R License", $crLicenseFileId);
						}
					}
					$crLicenseOnFileCategoryId = getFieldFromId("category_id", "categories", "category_code", "CR_LICENSE_ON_FILE");
					$crLicenseOnFileContactCategoryId = getFieldFromId("contact_category_id", "contact_categories",
						"category_id", $crLicenseOnFileCategoryId, "contact_id = ?", $contactId);
					$crLicenseExpirationDate = CustomField::getCustomFieldData($contactId, "CR_LICENSE_ON_FILE_EXPIRATION_DATE");
					if (!empty($_POST['has_cr_license']) && (empty($crLicenseOnFileContactCategoryId) || empty($crLicenseExpirationDate) || $crLicenseExpirationDate <= date("Y-m-d"))) {
						executeQuery("insert into order_notes (order_id, user_id, time_submitted, content) values (?, ?, now(), ?)", $orderId, $orderNoteUserId, "User indicated that they have a C&R License.");
					}
				}

                if(!empty($_SESSION['original_user_id'])) {
                    executeQuery("insert into order_notes (order_id, user_id, time_submitted, content) values (?, ?, now(), ?)", $orderId, $_SESSION['original_user_id'], "Order placed by admin using Simulate User");
                }

				coreSTORE::orderNotification($orderId, "order_created");
				Order::reportOrderToTaxjar($orderId);
				if (!empty($confirmUserAccount)) {
					logout();
					$returnArray['info_message'] = "Please check your email and confirm your user account before you attempt to log in.";
				}

				ajaxResponse($returnArray);

				break;
			case "get_shipping_methods":
				if (function_exists("_localGetShippingMethods")) {
					$returnArray = _localGetShippingMethods();
					ajaxResponse($returnArray);
					break;
				}
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				if (!$GLOBALS['gUserRow']['administrator_flag'] || empty($_GET['contact_id'])) {
					$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				} else {
					$shoppingCart = ShoppingCart::getShoppingCartForContact($_GET['contact_id'], $_GET['shopping_cart_code']);
				}
				$shoppingCartItems = $shoppingCart->getShoppingCartItems();
				if (!is_array($shoppingCartItems)) {
					$shoppingCartItems = array();
				}

				$locationIds = array();
				$resultSet = executeReadQuery("select * from locations where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and product_distributor_id is null and location_id in (select location_id from shipping_methods where " .
					"inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and location_id is not null)", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$locationIds[] = $row['location_id'];
				}
				$allLocationIds = $locationIds;
				$productCatalog = new ProductCatalog();

				$productIds = array();
				$shoppingCartItems = $shoppingCart->getShoppingCartItems();
				foreach ($shoppingCartItems as $thisItem) {
					$virtualProduct = getReadFieldFromId("virtual_product", "products", "product_id", $thisItem['product_id']);
					if (empty($virtualProduct)) {
						$productIds[] = $thisItem['product_id'];
					}
				}

				$locationAvailability = $productCatalog->getLocationAvailability($productIds);
				foreach ($shoppingCartItems as $thisItem) {
					if (!array_key_exists($thisItem['product_id'], $locationAvailability)) {
						continue;
					}
					$inventoryCounts = $locationAvailability[$thisItem['product_id']];
					if ($inventoryCounts['distributor'] > 0) {
						continue;
					}
					foreach ($locationIds as $index => $thisLocationId) {
						if (is_array($inventoryCounts) && !array_key_exists($thisLocationId, $inventoryCounts) || $inventoryCounts[$thisLocationId] <= 0) {
							unset($locationIds[$index]);
						}
						if ($inventoryCounts[$thisLocationId] < $thisItem['quantity']) {
							unset($locationIds[$index]);
						}
					}
				}

				$shippingMethods = $shoppingCart->getShippingOptions();
				if (!$shippingMethods || !is_array($shippingMethods)) {
					if (!$GLOBALS['gUserRow']['administrator_flag']) {
						addProgramLog($shoppingCart->getShippingCalculationLog());
					} else {
						$returnArray['shipping_calculation_log'] = makeHtml($shoppingCart->getShippingCalculationLog());
					}
					$returnArray['error_message'] = $shoppingCart->getErrorMessage();
					ajaxResponse($returnArray);
					break;
				}

				$pickupLocationIds = array();
				$pickupShippingMethodIds = array();
				foreach ($shippingMethods as $thisShippingMethod) {
					if (empty($thisShippingMethod['pickup'])) {
						continue;
					}
					$locationId = getReadFieldFromId("location_id", "shipping_methods", "shipping_method_id", $thisShippingMethod['shipping_method_id']);
					if (empty($allLocationIds) && empty($locationId)) {
						$pickupShippingMethodIds[] = $thisShippingMethod['shipping_method_id'];
					} elseif (!empty($locationId) && in_array($locationId, $locationIds)) {
						$pickupShippingMethodIds[] = $thisShippingMethod['shipping_method_id'];
					}
				}

				$defaultLocationId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID");
				$defaultLocationId = getReadFieldFromId("location_id", "locations", "location_id", $defaultLocationId, "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and location_id in (select location_id from shipping_methods where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and pickup = 1)");
				$defaultLocationShippingMethodId = getReadFieldFromId("shipping_method_id", "shipping_methods", "location_id", $defaultLocationId, "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and pickup = 1");
				$defaultLocationOutOfStockProducts = array();
				if (!empty($defaultLocationId)) {
					foreach ($locationAvailability as $productId => $inventoryCounts) {
						if ($inventoryCounts['distributor'] > 0) {
							continue;
						}
						if (empty($inventoryCounts[$defaultLocationId])) {
							$defaultLocationOutOfStockProducts[] = array("product_id" => $productId, "description" => getFieldFromId("description", "products", "product_id", $productId));
						}
					}
				}
				$returnArray['default_location_out_of_stock_products'] = $defaultLocationOutOfStockProducts;
				$returnArray['inventory_counts'] = $inventoryCounts;

				$unshippableProductsExist = false;
				$neverOutOfStock = getPreference("RETAIL_STORE_NEVER_OUT_OF_STOCK");
				$productCatalog = new ProductCatalog();
				if (!empty($productIds) && empty($neverOutOfStock)) {
					$shippingLocations = array();
					$resultSet = executeReadQuery("select * from locations where client_id = ? and product_distributor_id is null and cannot_ship = 0 and inactive = 0", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$shippingLocations[] = $row['location_id'];
					}
					foreach ($locationAvailability as $productId => $inventoryCounts) {
						if ($inventoryCounts['distributor'] > 0) {
							continue;
						}
						foreach ($inventoryCounts as $locationId => $inventoryCount) {
							if ($inventoryCount > 0 && in_array($locationId, $shippingLocations)) {
								continue 2;
							}
						}
						$unshippableProductsExist = true;
						break;
					}
				}

				# check to see if any products that are NOT virtual products are only available at locations that are NOT distributors and cannot ship
				# if there are ANY products like this, they cannot be shipped and ONLY pickup locations should be returned

				$limitShippingToDefaultLocation = getPreference("LIMIT_SHIPPING_DEFAULT_LOCATION");
				$forceShippingMethodCode = getPreference("FORCE_SHIPPING_METHOD");
				$forceShippingMethodId = "";
				if (!empty($forceShippingMethodCode)) {
					$forceShippingMethodId = getReadFieldFromId("shipping_method_id", "shipping_methods", "shipping_method_code", $forceShippingMethodCode, "inactive = 0");
				}

				$shippingMethods = $shoppingCart->getShippingOptions($_POST['country_id'], $_POST['state'], $_POST['postal_code']);

				if (!$GLOBALS['gUserRow']['administrator_flag'] && empty($shippingMethods)) {
					addProgramLog($shoppingCart->getShippingCalculationLog());
				} elseif ($GLOBALS['gUserRow']['administrator_flag']) {
					$returnArray['shipping_calculation_log'] = makeHtml($shoppingCart->getShippingCalculationLog());
				}

				if ($shippingMethods === false) {
					$returnArray['error_message'] = $shoppingCart->getErrorMessage();
				} else {
					$returnArray['shipping_methods'] = array();
					$foundDefaultLocationShippingMethod = false;
					foreach ($shippingMethods as $thisShippingMethod) {
						if ($thisShippingMethod['pickup'] && !in_array($thisShippingMethod['shipping_method_id'], $pickupShippingMethodIds)) {
							if ($GLOBALS['gUserRow']['administrator_flag']) {
								$returnArray['shipping_calculation_log'] .= "<p>Shipping Method '" . $thisShippingMethod['description'] . "' skipped because it is not one of the pickup methods.</p>";
							}
							continue;
						}
						if (!empty($forceShippingMethodId) && $thisShippingMethod['shipping_method_id'] != $forceShippingMethodId) {
							if ($GLOBALS['gUserRow']['administrator_flag']) {
								$returnArray['shipping_calculation_log'] .= "<p>Shipping Method '" . $thisShippingMethod['description'] . "' skipped because a shipping method is being forced.</p>";
							}
							continue;
						}
						if (!empty($_GET['exclude_pickup_locations']) && !empty($thisShippingMethod['pickup']) && $thisShippingMethod['shipping_method_id'] != $_GET['pickup_shipping_method_id']) {
							if ($GLOBALS['gUserRow']['administrator_flag']) {
								$returnArray['shipping_calculation_log'] .= "<p>Shipping Method '" . $thisShippingMethod['description'] . "' skipped because it is pickup.</p>";
							}
							continue;
						}
						if ($unshippableProductsExist && empty($thisShippingMethod['pickup'])) {
							if ($GLOBALS['gUserRow']['administrator_flag']) {
								$returnArray['shipping_calculation_log'] .= "<p>Shipping Method '" . $thisShippingMethod['description'] . "' skipped because unshippable product exists.</p>";
							}
							continue;
						}
						if ($thisShippingMethod['shipping_method_id'] == $defaultLocationShippingMethodId) {
							$foundDefaultLocationShippingMethod = true;
						} elseif (!empty($limitShippingToDefaultLocation)) {
							continue;
						}
						$returnArray['shipping_methods'][] = array("key_value" => $thisShippingMethod['shipping_method_id'], "shipping_method_code" => $thisShippingMethod['shipping_method_code'], "shipping_charge" => $thisShippingMethod['shipping_charge'],
							"description" => $thisShippingMethod['description'] . " - $" . number_format($thisShippingMethod['shipping_charge'], 2), "pickup" => $thisShippingMethod['pickup']);
					}
					if ($foundDefaultLocationShippingMethod) {
						$returnArray['default_location_shipping_method_id'] = $defaultLocationShippingMethodId;
					}
				}

				ajaxResponse($returnArray);
				break;
			case "get_ffl_information":
				$fflRow = (new FFL(array("federal_firearms_licensee_id" => $_POST['federal_firearms_licensee_id'], "only_if_valid" => true)))->getFFLRow();
				$fflChoiceElement = "<p><span class='ffl-choice-business-name'>%business_name%</span><br><span class='ffl-choice-address'>%address_1%</span><br><span class='ffl-choice-city'>%city%, %state% %postal_code%</span><br><span class='ffl-phone-number'>%phone_number%</span></p>";
				if ($fflRow) {
					$fflRow['expiration_date_notice'] = "";
					if (!empty($fflRow['expiration_date']) && $fflRow['expiration_date'] < date("Y-m-d", strtotime("+30 days"))) {
						$fflRow['expiration_date_notice'] = "<p class='" . ($fflRow['expiration_date'] < date("Y-m-d", strtotime("+30 days")) ? "red-text" : "") . "'>" .
							date("m/d/Y", strtotime($fflRow['expiration_date'])) . "</p>";
					}
					$returnArray['ffl_information'] = $fflChoiceElement;
					foreach ($fflRow as $fieldName => $fieldData) {
						$returnArray['ffl_information'] = str_replace("%" . $fieldName . "%", (is_scalar($fieldData) ? $fieldData : ""), $returnArray['ffl_information']);
					}
					$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);

					if ($GLOBALS['gLoggedIn']) {
						$contactId = $GLOBALS['gUserRow']['contact_id'];
					} else {
						$contactId = $shoppingCart->getContact();
					}
					if (!empty($contactId)) {
						CustomField::setCustomFieldData($contactId, "DEFAULT_FFL_DEALER", $fflRow['federal_firearms_licensee_id']);
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_ffl_tax_charge":
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				$fflRow = (new FFL(array("federal_firearms_licensee_id" => $_POST['federal_firearms_licensee_id'], "only_if_valid" => true)))->getFFLRow();
				if (!empty($fflRow) && is_array($fflRow)) {

					# Get tax for FFL location

					$returnArray['tax_charge'] = $shoppingCart->getEstimatedTax($fflRow);
					$taxRate = CustomField::getCustomFieldData($fflRow['contact_id'], "STORE_TAX_RATE");
					if ($taxRate !== false) {
						$returnArray['tax_charge'] = $shoppingCart->getEstimatedTax();
					}
				}

				ajaxResponse($returnArray);

				break;
			case "get_tax_charge":
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				if (!$GLOBALS['gUserRow']['administrator_flag'] || empty($_POST['contact_id']) || $_GET['shopping_cart_code'] != "ORDERENTRY") {
					$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				} else {
					$shoppingCart = ShoppingCart::getShoppingCartForContact($_POST['contact_id'], $_GET['shopping_cart_code']);
				}
				if (!empty($_POST['contact_id'])) {
					$resultSet = executeReadQuery("select address_1,city,state,postal_code,country_id from contacts where contact_id = ?", $_POST['contact_id']);
					if ($row = getNextRow($resultSet)) {
						$_POST = array_merge($_POST, $row);
					}
				}
				$fromCountryId = $GLOBALS['gClientRow']['country_id'];
				$fromAddress1 = $GLOBALS['gClientRow']['address_1'];
				$fromCity = $GLOBALS['gClientRow']['city'];
				$fromState = $GLOBALS['gClientRow']['state'];
				$fromPostalCode = $GLOBALS['gClientRow']['postal_code'];

				$countryId = $_POST['country_id'];
				$address1 = $_POST['address_1'];
				$city = $_POST['city'];
				$state = $_POST['state'];
				$postalCode = $_POST['postal_code'];
				$noShippingRequired = false;
				if ($_POST['shipping_method_id'] == -1) {
					$noShippingRequired = true;
				} else {
					$shippingMethodRow = getReadRowFromId("shipping_methods", "shipping_method_id", $_POST['shipping_method_id']);
					if (!empty($shippingMethodRow['pickup']) && !empty($shippingMethodRow['location_id'])) {
						$contactId = getReadFieldFromId("contact_id", "locations", "location_id", $shippingMethodRow['location_id']);
						$contactRow = Contact::getContact($contactId);
						if (empty($contactRow['state']) && empty($contactRow['postal_code'])) {
							$contactRow = $GLOBALS['gClientRow'];
						}
						if (!empty($contactRow['state'])) {
							$fromCountryId = $countryId = $contactRow['country_id'];
							$fromState = $state = $contactRow['state'];
							$fromCity = $city = $contactRow['city'];
							$fromAddress1 = $address1 = $contactRow['address_1'];
							$fromPostalCode = $postalCode = $contactRow['postal_code'];
						}
						$noShippingRequired = true;
					}
				}
				$shippingCharge = 0;
				if (!$noShippingRequired) {
					$shippingOptions = $shoppingCart->getShippingOptions($countryId, $state, $postalCode);
					if ($shippingOptions !== false) {
						foreach ($shippingOptions as $thisShippingMethod) {
							if ($thisShippingMethod['shipping_method_id'] == $_POST['shipping_method_id']) {
								$shippingCharge = $thisShippingMethod['shipping_charge'];
							}
						}
					}
				}

				if (empty($countryId)) {
					$countryId = 1000;
				}

				$parameters = array("country_id" => $countryId, "city" => $city, "state" => $state,
					"postal_code" => $postalCode, "address_1" => $address1, "from_country_id" => $fromCountryId, "from_city" => $fromCity, "from_state" => $fromState,
					"from_postal_code" => $fromPostalCode, "from_address_1" => $fromAddress1, "shipping_charge" => $shippingCharge);
				if (!empty($_POST['contact_id'])) {
					$parameters['contact_id'] = $_POST['contact_id'];
				}
				$returnArray['tax_charge'] = $shoppingCart->getEstimatedTax($parameters);

				ajaxResponse($returnArray);

				break;
			case "check_loan":

# no default processing for loans in Coreware yet

				if (function_exists("_localPaymentMethodLoanCheck")) {
					$returnArray = _localPaymentMethodLoanCheck();
				}
				ajaxResponse($returnArray);
				break;
			case "check_gift_card":
				if (!empty($_GET['order_entry']) && $GLOBALS['gUserRow']['administrator_flag'] && !empty($_GET['contact_id'])) {
					$userId = Contact::getContactUserId($_GET['contact_id']);
				} else {
					$userId = $GLOBALS['gUserId'];
				}
				$giftCardNumber = makeCode($_GET['gift_card_number'], array("allow_dash" => true));
				$giftCard = new GiftCard(array("gift_card_number" => $giftCardNumber, "gift_card_pin" => $_GET['gift_card_pin'], "user_id" => $userId));
				if ($giftCard->isValid()) {
					$balance = $giftCard->getBalance();
					$returnArray['maximum_payment_amount'] = $balance;
					$returnArray['gift_card_information'] = "This card has a balance of $" . number_format($balance, 2);
				} else {
					$returnArray['gift_card_error'] = "Card not found: " . $giftCardNumber;
					if (!$GLOBALS['gWhiteListed'] && !canAccessPageCode('GIFTCARDMAINT')) {
						addSecurityLog($_POST['login_user_name'], "INVALID-GIFT-CARD", "Invalid Gift Card - " . $giftCardNumber . ($GLOBALS['gLoggedIn'] ? " by " . getUserDisplayName() : ""));
						$resultSet = executeQuery("select * from security_log where security_log_type = 'INVALID-GIFT-CARD' and entry_time > (now() - interval 120 minute) and ip_address = ? order by entry_time desc", $_SERVER['REMOTE_ADDR']);
						if ($resultSet['row_count'] > 5) {
							$blacklistNote = "Repeated invalid gift card number: \n\n";
							while ($row = getNextRow($resultSet)) {
								$blacklistNote .= $row['log_entry'] . "\n";
							}
							blacklistIpAddress($_SERVER['REMOTE_ADDR'], $blacklistNote);
							if (!empty($GLOBALS['gUserId'])) {
								executeQuery("update users set locked = 1 where user_id = ?", $GLOBALS['gUserId']);
							}
						}
					}
				}
				ajaxResponse($returnArray);
				break;
			case "create_checkout_user":
				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("create_account_")) == "create_account_") {
						$_POST[substr($fieldName, strlen("create_account_"))] = $fieldData;
						unset($_POST[$fieldName]);
					}
				}
				if (empty($_POST['user_name'])) {
					$_POST['user_name'] = $_POST['email_address'];
				}
				$requiredFields = array(
					"first_name" => array(),
					"last_name" => array(),
					"email_address" => array(),
					"user_name" => array(),
					"password" => array());
				$missingFields = "";
				foreach ($requiredFields as $fieldName => $fieldInformation) {
					foreach ($fieldInformation as $checkFieldName => $checkValue) {
						if ($_POST[$checkFieldName] != $checkValue) {
							continue 2;
						}
					}
					if (empty($_POST[$fieldName])) {
						$missingFields .= (empty($missingFields) ? "" : ", ") . $fieldName;
					}
				}
				if (!empty($missingFields)) {
					$returnArray['error_message'] = "Required information is missing: " . $missingFields;
					ajaxResponse($returnArray);
					break;
				}
				if (!empty($_POST['password']) && !isPCIPassword($_POST['password'])) {
					$minimumPasswordLength = getPreference("minimum_password_length");
					if (empty($minimumPasswordLength)) {
						$minimumPasswordLength = 10;
					}
					$noPasswordRequirements = getPreference("no_password_requirements");
					$returnArray['error_message'] = getSystemMessage("password_minimum_standards", "Password does not meet minimum standards. Must be at least " . $minimumPasswordLength .
						" characters long" . ($noPasswordRequirements ? "" : " and include an upper and lowercase letter and a number"));
					ajaxResponse($returnArray);
					break;
				}
				if (!empty($_POST['email_address'])) {
					$existingContactId = getFieldFromId("contact_id", "contacts", "email_address", $_POST['email_address'], "contact_id in (select contact_id from users)");
					if (!empty($existingContactId)) {
						$returnArray['error_message'] = "A User already exists with this email address. Please log in.";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
				if (empty($_POST['country_id'])) {
					$_POST['country_id'] = 1000;
				}
				$this->iDatabase->startTransaction();
				$contactId = "";
				$resultSet = executeQuery("select contact_id from contacts where client_id = ? and email_address = ? and contact_id not in (select contact_id from accounts) and " .
					"contact_id not in (select contact_id from donations) and contact_id not in (select contact_id from orders) and contact_id not in (select contact_id from users)", $GLOBALS['gClientId'], $_POST['email_address']);
				if ($row = getNextRow($resultSet)) {
					$contactId = $row['contact_id'];
					$contactTable = new DataTable("contacts");
					$contactTable->setSaveOnlyPresent(true);
					$contactTable->saveRecord(array("name_values" => $_POST, "primary_id" => $contactId));
				}
				$sourceId = getFieldFromId("source_id", "sources", "source_code", "CHECKOUT");
				if (empty($contactId)) {
					$contactDataTable = new DataTable("contacts");
					if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name'], "last_name" => $_POST['last_name'],
						"business_name" => $_POST['business_name'], "address_1" => $_POST['address_1'], "address_2" => $_POST['address_2'], "city" => $_POST['city'], "state" => $_POST['state'],
						"postal_code" => $_POST['postal_code'], "email_address" => $_POST['email_address'], "country_id" => $_POST['country_id'], "source_id" => $sourceId)))) {
						$this->iDatabase->rollbackTransaction();
						$returnArray['error_message'] = $contactDataTable->getErrorMessage();
						ajaxResponse($returnArray);
						break;
					}
				}
				$resultSet = executeQuery("select * from users where user_name = ? and client_id = ?", strtolower($_POST['user_name']), $GLOBALS['gClientId']);
				if ($row = getNextRow($resultSet)) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = "User name is already taken. Please select another.";
					ajaxResponse($returnArray);
					break;
				}
				$passwordSalt = getRandomString(64);
				$password = hash("sha256", $passwordSalt . $_POST['password']);
				$checkUserId = getFieldFromId("user_id", "users", "user_name", strtolower($_POST['user_name']), "client_id = ? or superuser_flag = 1", $GLOBALS['gClientId']);
				if (!empty($checkUserId)) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = "User name is unavailable. Choose another";
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("select count(*) from users where client_id = ? and inactive = 1 and contact_id in (select contact_id from contacts where email_address = ?)",
					$GLOBALS['gClientId'], $_POST['email_address']);
				if ($row = getNextRow($resultSet)) {
					if ($row['count(*)'] > 0) {
						$returnArray['error_message'] = "Unable to create user account";
						$this->iDatabase->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
				$usersTable = new DataTable("users");
				if (!$userId = $usersTable->saveRecord(array("name_values" => array("client_id" => $GLOBALS['gClientId'], "contact_id" => $contactId, "user_name" => strtolower($_POST['user_name']),
					"password_salt" => $passwordSalt, "password" => $password, "security_question_id" => $_POST['security_question_id'], "answer_text" => $_POST['answer_text'],
					"secondary_security_question_id" => $_POST['secondary_security_question_id'], "secondary_answer_text" => $_POST['secondary_answer_text'],
					"date_created" => date("Y-m-d H:i:s"))))) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = $usersTable->getErrorMessage();
					ajaxResponse($returnArray);
					break;
				}
				$confirmUserAccount = getPreference("CONFIRM_USER_ACCOUNT");
				if (!empty($confirmUserAccount)) {
					$randomCode = getRandomString(6, "23456789ABCDEFGHJKLMNPQRSTUVWXYZ");
					executeQuery("update users set verification_code = ?,locked = 1 where user_id = ?", $randomCode, $userId);
				}
				$password = hash("sha256", $userId . $passwordSalt . $_POST['password']);
				executeQuery("insert into user_passwords (user_id,password_salt,password) values (?,?,?)", $userId, $passwordSalt, $password);
				$resultSet = executeQuery("update users set password = ?,last_password_change = now() where user_id = ?", $password, $userId);
				if (!empty($resultSet['sql_error'])) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				makeWebUserContact($contactId);
				$_SESSION = array();
				saveSessionData();
				login($userId);
				$emailId = getFieldFromId("email_id", "emails", "email_code", "NEW_ACCOUNT", "inactive = 0");
				if (!empty($emailId)) {
					$substitutions = $_POST;
					unset($substitutions['password']);
					unset($substitutions['password_again']);
					sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_address" => $_POST['email_address'], "contact_id" => $contactId));
				}

				$phoneDescriptions = array("primary");
				foreach ($phoneDescriptions as $phoneDescription) {
					if (!empty($_POST[$phoneDescription . "_phone_number"])) {
						$resultSet = executeQuery("select * from phone_numbers where contact_id = ? and description = ?", $contactId, $phoneDescription);
						if ($row = getNextRow($resultSet)) {
							executeQuery("update phone_numbers set phone_number = ? where phone_number_id = ?",
								$_POST[$phoneDescription . "_phone_number"], $row['phone_number_id']);
						} else {
							executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,?)",
								$contactId, $_POST[$phoneDescription . "_phone_number"], $phoneDescription);
						}
					} else {
						executeQuery("delete from phone_numbers where description = ? and contact_id = ?", $phoneDescription, $contactId);
					}
				}
				$this->iDatabase->commitTransaction();
				if (!empty($userId)) {
					sendEmail(array("subject" => "User Account Created", "body" => "User account '" . $_POST['user_name'] . "' for contact " . getDisplayName($contactId) . " was created.", "email_address" => getNotificationEmails("USER_MANAGEMENT")));
				}
				if (!empty($confirmUserAccount)) {
					$confirmLink = getDomainName() . "/confirmuseraccount.php?user_id=" . $userId . "&hash=" . $randomCode;
					sendEmail(array("email_address" => $_POST['email_address'], "send_immediately" => true, "email_code" => "ACCOUNT_CONFIRMATION", "substitutions" => array("confirmation_link" => $confirmLink), "subject" => "Confirm Email Address", "body" => "<p>Click <a href='" . $confirmLink . "'>here</a> to confirm your email address and complete the creation of your user account.</p>"));
				}
				ajaxResponse($returnArray);
				break;
			case "set_shopping_cart_contact":
				$emailAddress = $_GET['email_address'];
				if (empty($emailAddress) || $GLOBALS['gLoggedIn']) {
					ajaxResponse($returnArray);
					break;
				}
				if ((array_key_exists("first_name", $_GET) && empty($_GET['first_name'])) || (array_key_exists("last_name", $_GET) && empty($_GET['last_name']))) {
					$returnArray['error_message'] = "First and last name are required";
					ajaxResponse($returnArray);
					break;
				}
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				$contactId = $shoppingCart->getContact();
				if (!empty($contactId)) {
					$query = "update contacts set email_address = ?";
					$parameters = array($_GET['email_address']);
					if (!empty($_GET['first_name'])) {
						$query .= ", first_name = ?";
						$parameters[] = $_GET['first_name'];
					}
					if (!empty($_GET['last_name'])) {
						$query .= ", last_name = ?";
						$parameters[] = $_GET['last_name'];
					}
					$query .= " where contact_id = ?";
					$parameters[] = $contactId;
					executeQuery($query, $parameters);
					ajaxResponse($returnArray);
					break;
				}
				$publicIdentifier = getFieldFromId("public_identifier", "credova_loans", "contact_id", $GLOBALS['gUserRow']['contact_id'], "order_id is null");
				if (!empty($publicIdentifier)) {
					$returnArray['public_identifier'] = $publicIdentifier;
				}
				$resultSet = executeQuery("select * from users where client_id = ? and (user_name = ? or contact_id in (select contact_id from contacts where email_address = ?))", $GLOBALS['gClientId'], $emailAddress, $emailAddress);
				$returnArray['found_user'] = false;
				if (empty($_GET['confirm']) && $resultSet['row_count'] > 0) {
					$returnArray['error_message'] = "This email address is already associated with a user. Do you want to login?";
					$returnArray['found_user'] = true;
				} else {
					$sourceId = getFieldFromId("source_id", "sources", "source_id", $_COOKIE['source_id'], "inactive = 0");
					if (empty($sourceId)) {
						$sourceId = getSourceFromReferer($_SERVER['HTTP_REFERER']);
					}
					$contactId = false;
					$resultSet = executeQuery("select * from contacts where first_name = ? and last_name = ? and client_id = ? and email_address = ? and source_id <=> ? and " .
						"contact_id not in (select contact_id from orders) and contact_id not in (select contact_id from accounts) and contact_id not in (select contact_id from users) and " .
						"contact_id not in (select contact_id from invoices) and address_1 is null and city is null and postal_code is null", $_GET['first_name'], $_GET['last_name'], $GLOBALS['gClientId'], $emailAddress, $sourceId);
					if ($row = getNextRow($resultSet)) {
						$contactId = $row['contact_id'];
					}
					if (empty($contactId)) {
						$contactDataTable = new DataTable("contacts");
						if (!$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_GET['first_name'], "last_name" => $_GET['last_name'],
							"email_address" => $emailAddress, "source_id" => $sourceId)))) {
							$returnArray['error_message'] = $contactDataTable->getErrorMessage();
							ajaxResponse($returnArray);
							break;
						}
					} else {
						executeQuery("delete from shopping_cart_item_addons where shopping_cart_item_id in (select shopping_cart_item_id from shopping_cart_items where shopping_cart_id in (select shopping_cart_id from shopping_carts where contact_id = ? and shopping_cart_code = ?))", $contactId, $_GET['shopping_cart_code']);
						executeQuery("delete from shopping_cart_items where shopping_cart_id in (select shopping_cart_id from shopping_carts where contact_id = ? and shopping_cart_code = ?)", $contactId, $_GET['shopping_cart_code']);
						executeQuery("delete from product_map_overrides where shopping_cart_id in (select shopping_cart_id from shopping_carts where contact_id = ? and shopping_cart_code = ?)", $contactId, $_GET['shopping_cart_code']);
						executeQuery("delete from shopping_carts where contact_id = ? and shopping_cart_code = ?", $contactId, $_GET['shopping_cart_code']);
					}
					$shoppingCart->setValues(array("contact_id" => $contactId));
				}
				$shoppingCart->setValues(array("start_time" => date("Y-m-d H:i:s"), "abandon_email_sent" => 0));
				ajaxResponse($returnArray);
				break;
			case "checkout_started":
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				if (function_exists("_localCheckoutStarted")) {
					_localCheckoutStarted();
				}
				$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				$shoppingCart->setValues(array("start_time" => date("Y-m-d H:i:s"), "abandon_email_sent" => 0));
				ajaxResponse($returnArray);
				break;
			case "get_shopping_cart_item_count":
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				$returnArray['shopping_cart_item_count'] = $shoppingCart->getShoppingCartItemsCount();
			case "get_wish_list_item_count":
				if ($GLOBALS['gLoggedIn']) {
					if (!empty($_GET['wish_list_id'])) {
						$_GET['wish_list_id'] = getFieldFromId("wish_list_id", "wish_lists", "wish_list_id", $_GET['wish_list_id'], "user_id = ?", $GLOBALS['gUserId']);
					}
					try {
						$wishList = new WishList($GLOBALS['gUserId'], $_GET['wish_list_id']);
						$returnArray['wish_list_item_count'] = $wishList->getWishListItemsCount();
					} catch (Exception $e) {
						$returnArray['wish_list_item_count'] = 0;
					}
				}

				# also get the loyalty points the user has

				$resultSet = executeQuery("select * from loyalty_programs where client_id = ? and (user_type_id = ? or user_type_id is null) and inactive = 0 and " .
					"internal_use_only = 0 order by user_type_id desc,sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gUserRow']['user_type_id']);
				if (!$loyaltyProgramRow = getNextRow($resultSet)) {
					$loyaltyProgramRow = array();
				}
				$loyaltyProgramPointsRow = getRowFromId("loyalty_program_points", "user_id", $GLOBALS['gUserId'], "loyalty_program_id = ?", $loyaltyProgramRow['loyalty_program_id']);
				if (!empty($loyaltyProgramPointsRow) && $loyaltyProgramPointsRow['point_value'] > 0) {
					$returnArray['loyalty_points_total'] = floor($loyaltyProgramPointsRow['point_value']);
				} else {
					$returnArray['loyalty_points_total'] = 0;
				}

				ajaxResponse($returnArray);

				break;
			case "buy_again":
				$orderItemId = getFieldFromId("order_item_id", "order_items", "order_item_id", $_GET['order_item_id'], "order_id in (select order_id from orders where contact_id = ?)", $GLOBALS['gUserRow']['contact_id']);
				if (empty($orderItemId)) {
					$returnArray['unable_to_add'] = true;
					ajaxResponse($returnArray);
					break;
				}
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				$contactId = $GLOBALS['gUserRow']['contact_id'];
				$shoppingCart = ShoppingCart::getShoppingCartForContact($contactId, $_GET['shopping_cart_code']);
				$shoppingCartItems = $shoppingCart->getShoppingCartItems(array());
				$productId = getFieldFromId("product_id", "order_items", "order_item_id", $orderItemId, "product_id in (select product_id from products where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . ")");
				if (empty($orderItemId)) {
					$returnArray['error_message'] = "Unable to add product to shopping cart";
					ajaxResponse($returnArray);
					break;
				}
				$productAddons = array();
				$resultSet = executeQuery("select * from order_item_addons where order_item_id = ?", $orderItemId);
				while ($row = getNextRow($resultSet)) {
					$productAddons[] = $row;
				}

				if (!$shoppingCartItemId = $returnArray['shopping_cart_item_id'] = $shoppingCart->addItem(array("product_id" => $productId, "quantity" => 1, "has_addons" => (count($productAddons) > 0)))) {
					$returnArray['error_message'] = $shoppingCart->getErrorMessage();
					ajaxResponse($returnArray);
					break;
				}
				foreach ($productAddons as $productAddonRow) {
					$shoppingCart->updateItem($shoppingCartItemId, array("product_addon_" . $productAddonRow['product_addon_id'] => $productAddonRow['quantity']));
				}

				ajaxResponse($returnArray);
				break;
			case "change_shopping_cart_quantity":
				$setQuantity = true;
			case "add_to_shopping_cart":
				if (!isset($setQuantity)) {
					$setQuantity = false;
				}
				$_GET = array_merge($_POST, $_GET);
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				if ($GLOBALS['gUserRow']['administrator_flag'] && !empty($_GET['contact_id'])) {
					$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
				} else {
					$contactId = "";
				}
				if (empty($contactId)) {
					$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				} else {
					$shoppingCart = ShoppingCart::getShoppingCartForContact($contactId, $_GET['shopping_cart_code']);
				}
				$orderUpsellProductTypeId = getCachedData("order_upsell_product_type_id", "");
				if ($orderUpsellProductTypeId === false) {
					$orderUpsellProductTypeId = getFieldFromId("product_type_id", "product_types", "product_type_code", "order_upsell_product");
					if (empty($orderUpsellProductTypeId)) {
						$orderUpsellProductTypeId = 0;
					}
					setCachedData("order_upsell_product_type_id", "", $orderUpsellProductTypeId, 168);
				}
				if (!empty($orderUpsellProductTypeId)) {
					$returnArray['reload_cart'] = true;
				}
				$shoppingCartItems = $shoppingCart->getShoppingCartItems(array());
				if (!empty($_GET['shopping_cart_item_id'])) {
					foreach ($shoppingCartItems as $index => $thisItem) {
						if ($thisItem['shopping_cart_item_id'] == $_GET['shopping_cart_item_id']) {
							$shoppingCartItemId = $thisItem['shopping_cart_item_id'];
							$originalQuantity = $thisItem['quantity'];
							if (empty($originalQuantity)) {
								$originalQuantity = 0;
							}
							$quantity = intval($_GET['quantity']);
							if (empty($quantity)) {
								if ($setQuantity) {
									$quantity = 0;
								} else {
									$quantity = 1;
								}
							}
							if ($setQuantity && $quantity == 0) {
								$shoppingCart->removeItem($shoppingCartItemId);
								$returnArray['reload_cart'] = true;
							} else {
								$newQuantity = ($setQuantity ? $quantity : $originalQuantity + $quantity);
								if (!$shoppingCart->updateItem($shoppingCartItemId, $newQuantity)) {
									$returnArray['error_message'] = $shoppingCart->getErrorMessage();
									ajaxResponse($returnArray);
									break;
								}
								$returnArray['shopping_cart_item_count'] = $shoppingCart->getShoppingCartItemsCount();
							}
							$returnValue = $shoppingCart->checkInventoryLevels();
							if (!$returnValue) {
								$returnArray['error_message'] = "Item added to cart successfully. However, one or more items in your cart is not available in the quantity selected. Quantities have been adjusted.";
								$returnArray['reload_cart'] = true;
							}
							$returnArray['product_id'] = $thisItem['product_id'];
                            if($_GET['shopping_cart_code'] == "RETAIL") {
                                $productRow = ProductCatalog::getCachedProductRow($thisItem['product_id']);
                                $analyticsEvent = array();
                                if ($setQuantity) {
                                    $analyticsEvent['event'] = ($originalQuantity > $quantity ? "remove_from_cart" : "add_to_cart");
                                    $analyticsEvent['event_data']['quantity'] = abs($originalQuantity - $quantity);
                                } else {
                                    $analyticsEvent['event'] = ($originalQuantity > $newQuantity ? "remove_from_cart" : "add_to_cart");
                                    $analyticsEvent['event_data']['quantity'] = $quantity;
                                }
                                $analyticsEvent['event_data']['product_key'] = getAnalyticsProductKey($productRow);
                                $analyticsEvent['event_data']['upc_code'] = $productRow['upc_code'];
                                $analyticsEvent['event_data']['product_name'] = $productRow['description'];
                                $analyticsEvent['event_data']['manufacturer_name'] = $productRow['manufacturer_name'];
                                $analyticsEvent['event_data']['sale_price'] = floatval(str_replace(["$", ","], "", $thisItem['sale_price']));
                                $analyticsEvent['event_data']['product_category'] = getFieldFromId("description", "product_categories", "product_category_id", $productRow['product_category_ids'][0]);
                                $analyticsEvent['event_data']['items'] = $shoppingCart->getShoppingCartItems(array("include_upc_code" => true));
                                $returnArray["analytics_event"] = $analyticsEvent;
                            }
							ajaxResponse($returnArray);
							break;
						}
					}
				}
				$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id'], "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
				$productAddonRow = false;

				$originalQuantity = (empty($_GET['addon_count']) ? $shoppingCart->getProductQuantity($productId) : 0);
				if (empty($originalQuantity)) {
					$originalQuantity = 0;
				}
				$quantity = intval($_GET['quantity']);
				if (empty($quantity)) {
					if ($setQuantity) {
						$quantity = 0;
					} else {
						$quantity = 1;
					}
				}
				if ($setQuantity && $quantity == 0) {
					$shoppingCart->removeProduct($productId);
					$returnArray['reload_cart'] = true;
				} else {
					$newQuantity = ($setQuantity ? $quantity : $originalQuantity + $quantity);
					if (!$shoppingCartItemId = $returnArray['shopping_cart_item_id'] = $shoppingCart->addItem(array("product_id" => $productId, "quantity" => $quantity, "set_quantity" => $setQuantity, "allow_order_upsell_products" => (!empty($_POST['order_upsell_product'])), "has_addons" => ($_GET['addon_count'] > 0)))) {
						$returnArray['error_message'] = $shoppingCart->getErrorMessage();
						ajaxResponse($returnArray);
						break;
					}
					$returnArray['shopping_cart_item_count'] = $shoppingCart->getShoppingCartItemsCount();
					$finalQuantity = $shoppingCart->getProductQuantity($productId, $shoppingCartItemId);
					if ($finalQuantity == 1) {
						$productAddonRow = getRowFromId("product_addons", "product_id", $productId, "form_definition_id is not null and form_definition_id in (select form_definition_id from form_definitions where inactive = 0 and internal_use_only = 0)");
					}
					if ($newQuantity > $finalQuantity) {
						$returnArray['error_message'] = "Maximum order quantity exceeded";
						$returnArray['reload_cart'] = true;
					} else {
						if ($newQuantity > 0 && $newQuantity < $finalQuantity) {
							$returnArray['error_message'] = "Minimum order quantity applied";
							$returnArray['reload_cart'] = true;
						}
					}
					if ($finalQuantity == 0 || !empty($shoppingCart->getPromotionId())) {
						$returnArray['reload_cart'] = true;
					}
				}

				if (!empty($shoppingCartItemId)) {
					foreach ($_GET as $fieldName => $fieldValue) {
						if (substr($fieldName, 0, strlen("addon_")) == "addon_" && !empty($fieldValue)) {
							$parts = explode("_", $fieldName);
							$productAddonId = $parts[1];
							if (is_numeric($productAddonId)) {
								$shoppingCart->updateItem($shoppingCartItemId, array("product_addon_" . $productAddonId => $fieldValue));
							}
						}
					}
				}
				$resultSet = executeQuery("select * from related_products where product_id = ? and related_product_type_id in " .
					"(select related_product_type_id from related_product_types where related_product_type_code = 'SHOPPING_CART') and " .
					"product_id in (select product_id from products where inactive = 0 and internal_use_only = 0) and product_id in " .
					"(select product_id from product_inventories where quantity > 0)", $productId);
				if ($resultSet['row_count'] > 0) {
					$returnArray['related_products'] = true;
				}

				$productRow = ProductCatalog::getCachedProductRow($productId);

				$eventId = getReadFieldFromId("event_id", "events", "product_id", $productRow['product_id']);
				if (!empty($eventId)) {
					$eventTypeId = getReadFieldFromId("event_type_id", "events", "event_id", $eventId);
					$resultSet = executeReadQuery("select * from certification_types join event_type_requirements using (certification_type_id) where event_type_id = ? order by sort_order,description", $eventTypeId);
					$addToCartAlert = "";
					if ($resultSet['row_count'] > 0) {
						$userCertified = $GLOBALS['gLoggedIn'];
						$requirementsFound = 0;
						$messageAdded = false;
						while ($row = getNextRow($resultSet)) {
							if (!$messageAdded) {
								if ($row['any_requirement']) {
									$addToCartAlert .= "<p id='_event_requirements'>This class requires one of the following:</p><ul>";
								} else {
									$addToCartAlert .= "<p id='_event_requirements'>This class has the following requirements:</p><ul>";
								}
							}
							if ($GLOBALS['gLoggedIn']) {
								$statusSet = executeReadQuery("select * from contact_certifications where contact_id = ? and certification_type_id = ? and date_issued <= current_date", $GLOBALS['gUserRow']['contact_id'], $row['certification_type_id']);
								$contactCertificationRow = false;
								while ($statusRow = getNextRow($statusSet)) {
									if (empty($statusRow['expiration_date'])) {
										$contactCertificationRow = $statusRow;
										break;
									}
									if (empty($contactCertificationRow)) {
										$contactCertificationRow = $statusRow;
									} elseif ($contactCertificationRow['expiration_date'] < $statusRow['expiration_date']) {
										$contactCertificationRow = $statusRow;
									}
								}
								$contactStatus = "";
								if (empty($contactCertificationRow)) {
									if (empty($row['any_requirement'])) {
										$userCertified = false;
									}
								} else {
									$requirementsFound++;
									if (empty($contactCertificationRow['expiration_date'])) {
										$contactStatus = "Issued on " . date("m/d/Y", strtotime($contactCertificationRow['date_issued']));
									} else {
										$contactStatus = "Expire" . ($contactCertificationRow['expiration_date'] < date("Y-m-d") ? "d" : "s") . " on " . date("m/d/Y", strtotime($contactCertificationRow['expiration_date']));
										if ($contactCertificationRow['expiration_date'] < date("Y-m-d")) {
											$userCertified = false;
										}
									}
								}
							}
							$addToCartAlert .= "<li>" . htmlText($row['description']) . (empty($contactStatus) ? "" : " - " . $contactStatus) . "</li>";
							if ($requirementsFound == 0) {
								$userCertified = false;
							}
						}
						$addToCartAlert .= "</ul>";
						if (!$userCertified) {
							$notQualifiedMessage = CustomField::getCustomFieldData($productRow['product_id'], "CLASS_NOT_QUALIFIED", "PRODUCTS");
							if (empty($notQualifiedMessage)) {
								$notQualifiedMessage = getFragment("CLASS_NOT_QUALIFIED");
							}
							if (empty($notQualifiedMessage)) {
								$addToCartAlert .= "<p id='_not_qualified'>You are not qualified for this class. You can schedule the class now, but must complete the prerequisites before attending.</p>";
							}
							$addToCartAlertFragment = getFragment("CLASS_NOT_QUALIFIED");
							if (!empty($addToCartAlertFragment)) {
								$addToCartAlert = $addToCartAlertFragment;
							}
							$returnArray['add_to_cart_alert'] = $addToCartAlert;
						}
					}
					if (function_exists("_localServerAddEventRegistrationToCart")) {
						$additionalProductArray = _localServerAddEventRegistrationToCart($shoppingCart->getContact(), $productId);
						if (!empty($additionalProductArray) && is_array($additionalProductArray)) {
							if (array_key_exists('block_checkout', $additionalProductArray)) {
								$returnArray['error_message'] = $additionalProductArray['block_checkout'];
								$shoppingCart->removeProduct($productId);
								$returnArray['reload_cart'] = true;
								ajaxResponse($returnArray);
								break;
							} else {
								$shoppingCart->addItem($additionalProductArray);
							}
						}
					}
				}

				$returnArray['upc_code'] = getFieldFromId("upc_code", "product_data", "product_id", $productId);
				$returnArray['description'] = $productRow['description'];
				$returnArray['product_manufacturer'] = getFieldFromId('description', 'product_manufacturers', 'product_manufacturer_id',
					$productRow['product_manufacturer_id']);

				$returnValue = $shoppingCart->checkInventoryLevels();
				if (!$returnValue) {
					$returnArray['error_message'] = "Item added to cart successfully. However, one or more items in your cart is not available in the quantity selected. Quantities have been adjusted.";
					$returnArray['reload_cart'] = true;
				}
				if (!empty($productAddonRow) && $_GET['shopping_cart_code'] == "RETAIL") {
					$returnArray['form_definition_code'] = getFieldFromId("form_definition_code", "form_definitions", "form_definition_id", $productAddonRow['form_definition_id']);
					$returnArray['form_link'] = getFieldFromId("link_name", "pages", "script_filename", "generateform.php", "inactive = 0 and internal_use_only = 0 and script_arguments = 'code=" . $returnArray['form_definition_code'] . "'") .
						"?shopping_cart_item_id=" . $returnArray['shopping_cart_item_id'] . "&product_addon_id=" . $productAddonRow['product_addon_id'];
				}
                if($_GET['shopping_cart_code'] == "RETAIL") {
                    $productRow = ProductCatalog::getCachedProductRow($productId);
                    $analyticsEvent = array();
                    if ($setQuantity) {
                        $analyticsEvent['event'] = ($originalQuantity > $quantity ? "remove_from_cart" : "add_to_cart");
                        $analyticsEvent['event_data']['quantity'] = abs($originalQuantity - $quantity);
                    } else {
                        $analyticsEvent['event'] = "add_to_cart";
                        $analyticsEvent['event_data']['quantity'] = $quantity;
                    }
                    $analyticsEvent['event_data']['product_key'] = getAnalyticsProductKey($productRow);
                    $analyticsEvent['event_data']['upc_code'] = $productRow['upc_code'];
                    $analyticsEvent['event_data']['product_name'] = $productRow['description'];
                    $analyticsEvent['event_data']['manufacturer_name'] = $productRow['manufacturer_name'];
                    $shoppingCartItem = $shoppingCart->getShoppingCartItem(["shopping_cart_item_id"=>$shoppingCartItemId]);
                    $analyticsEvent['event_data']['sale_price'] = floatval(str_replace(["$", ","], "", $shoppingCartItem['sale_price']));
                    $analyticsEvent['event_data']['product_category'] = getFieldFromId("description", "product_categories", "product_category_id", $productRow['product_category_ids'][0]);
                    $analyticsEvent['event_data']['items'] = $shoppingCart->getShoppingCartItems(array("include_upc_code" => true));
                    $returnArray["analytics_event"] = $analyticsEvent;
                }

                ajaxResponse($returnArray);

				break;
			case "add_product_code":
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				$productId = getFieldFromId("product_id", "product_data", "upc_code", ProductCatalog::makeValidUPC($_GET['product_search_value']), "product_id in(select product_id from products where inactive = 0)");
				if (empty($productId)) {
					$productId = getFieldFromId("product_id", "product_data", "isbn_13", ProductCatalog::makeValidISBN13($_GET['product_search_value']), "product_id in(select product_id from products where inactive = 0)");
				}
				if (empty($productId)) {
					$productId = getFieldFromId("product_id", "product_data", "isbn", ProductCatalog::makeValidISBN($_GET['product_search_value']), "product_id in(select product_id from products where inactive = 0)");
				}
				$productId = getFieldFromId("product_id", "products", "product_id", $productId,
					"(product_manufacturer_id is null or product_manufacturer_id not in(select product_manufacturer_id from product_manufacturers where inactive = 1)) and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
				if (empty($productId)) {
					$productId = getFieldFromId("product_id", "products", "product_code", $_GET['product_search_value'], "(product_manufacturer_id is null or product_manufacturer_id not in(select product_manufacturer_id from product_manufacturers where inactive = 1)) and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
				}

				if (!empty($productId)) {
					$excludedProductCategories = array();
					$resultSet = executeQuery("select product_category_id from product_categories where client_id = ? and (cannot_sell = 1" .
						($GLOBALS['gInternalConnection'] ? "" : " or product_category_code = 'INTERNAL_USE_ONLY'") . ")", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$excludedProductCategories[] = $row['product_category_id'];
					}
					if (!empty($excludedProductCategories)) {
						$productCategoryLinkId = getFieldFromId("product_category_link_id", "product_category_links", "product_id", $productId, "product_category_id in(" . implode(", ", $excludedProductCategories) . ")");
						if (!empty($productCategoryLinkId)) {
							$productId = "";
						}
					}
				}

				if (!empty($productId)) {
					$excludedProductTags = array();
					$resultSet = executeQuery("select product_tag_id from product_tags where client_id = ? and cannot_sell = 1", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$excludedProductTags[] = $row['product_tag_id'];
					}
					if (!empty($excludedProductTags)) {
						$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productId, "product_tag_id in(" . implode(", ", $excludedProductTags) . ")");
						if (!empty($productTagLinkId)) {
							$productId = "";
						}
					}
				}

				if (empty($productId)) {
					$returnArray['error_message'] = "Product Not Found";
				} else {
					$productRow = ProductCatalog::getCachedProductRow($productId);

					if (empty($productRow['virtual_product'])) {
						$returnArray['shipping_required'] = true;
					}

					$quantity = intval($_GET['quantity']);
					if (empty($quantity)) {
						$quantity = 1;
					}
					if (!$shoppingCart->addItem(array("product_id" => $productId, "quantity" => $quantity, "set_quantity" => false))) {
						$returnArray['error_message'] = $shoppingCart->getErrorMessage();
						ajaxResponse($returnArray);
						break;
					}
					$returnArray['shopping_cart_item_count'] = $shoppingCart->getShoppingCartItemsCount();
                    if($_GET['shopping_cart_code'] == "RETAIL") {
                        $analyticsEvent = array();
                        $analyticsEvent['event'] = "add_to_cart";
                        $analyticsEvent['event_data']['quantity'] = $quantity;
                        $analyticsEvent['event_data']['product_key'] = getAnalyticsProductKey($productRow);
                        $analyticsEvent['event_data']['upc_code'] = $productRow['upc_code'];
                        $analyticsEvent['event_data']['product_name'] = $productRow['description'];
                        $analyticsEvent['event_data']['manufacturer_name'] = $productRow['manufacturer_name'];
                        $shoppingCartItem = $shoppingCart->getShoppingCartItem(["product_id"=>$productId]);
                        $analyticsEvent['event_data']['sale_price'] = floatval(str_replace(["$", ","], "",$shoppingCartItem['sale_price']));
                        $analyticsEvent['event_data']['product_category'] = getFieldFromId("description", "product_categories", "product_category_id", $productRow['product_category_ids'][0]);
                        $analyticsEvent['event_data']['items'] = $shoppingCart->getShoppingCartItems(array("include_upc_code" => true));
                        $returnArray["analytics_event"] = $analyticsEvent;
                    }
				}
				ajaxResponse($returnArray);
				break;
			case "remove_from_shopping_cart":
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				if ($GLOBALS['gUserRow']['administrator_flag'] && !empty($_GET['contact_id'])) {
					$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_GET['contact_id']);
				} else {
					$contactId = "";
				}
				if (empty($contactId)) {
					$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				} else {
					$shoppingCart = ShoppingCart::getShoppingCartForContact($contactId, $_GET['shopping_cart_code']);
				}
				$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id'], "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
				$returnArray['upc_code'] = getFieldFromId("upc_code", "product_data", "product_id", $productId);
				if (empty($_GET['shopping_cart_item_id'])) {
                    $shoppingCartItem = $shoppingCart->getShoppingCartItem(["product_id"=>$productId]);
					$shoppingCart->removeProduct($productId);
				} else {
                    $shoppingCartItem = $shoppingCart->getShoppingCartItem(["shopping_cart_item_id"=>$_GET['shopping_cart_item_id']]);
					$shoppingCart->removeItem($_GET['shopping_cart_item_id']);
				}
				$returnArray['shopping_cart_item_count'] = $shoppingCart->getShoppingCartItemsCount();
				if (!empty($shoppingCart->getPromotionId())) {
					$returnArray['reload_cart'] = true;
				}
                if($_GET['shopping_cart_code'] == "RETAIL") {
                    $productRow = ProductCatalog::getCachedProductRow($shoppingCartItem['product_id']);
                    $analyticsEvent = array();
                    $analyticsEvent['event'] = "remove_from_cart";
                    $analyticsEvent['event_data']['product_key'] = getAnalyticsProductKey($productRow);
                    $analyticsEvent['event_data']['quantity'] = $shoppingCartItem['quantity'];
                    $analyticsEvent['event_data']['upc_code'] = $productRow['upc_code'];
                    $analyticsEvent['event_data']['product_name'] = $productRow['description'];
                    $analyticsEvent['event_data']['manufacturer_name'] = $productRow['manufacturer_name'];
                    $analyticsEvent['event_data']['sale_price'] = floatval(str_replace(["$", ","], "", $shoppingCartItem['sale_price']));
                    $analyticsEvent['event_data']['product_category'] = getFieldFromId("description", "product_categories", "product_category_id", $productRow['product_category_ids'][0]);
                    $analyticsEvent['event_data']['items'] = $shoppingCart->getShoppingCartItems(array("include_upc_code" => true));
                    $returnArray["analytics_event"] = $analyticsEvent;
                }
				ajaxResponse($returnArray);
				break;
			case "add_to_wish_list":
				if ($GLOBALS['gLoggedIn']) {
					if (!empty($_GET['wish_list_id'])) {
						$_GET['wish_list_id'] = getFieldFromId("wish_list_id", "wish_lists", "wish_list_id", $_GET['wish_list_id'], "user_id = ?", $GLOBALS['gUserId']);
					}
					try {
						$wishList = new WishList($GLOBALS['gUserId'], $_GET['wish_list_id']);
						$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id'], "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
						$defaultNotifyWhenInStock = getPreference("DEFAULT_NOTIFY_WHEN_IN_STOCK");
						$wishList->addItem(array("product_id" => $productId, "notify_when_in_stock" => !empty($defaultNotifyWhenInStock) || !empty($_GET['notify_when_in_stock'])));
						$returnArray['wish_list_item_count'] = $wishList->getWishListItemsCount();
						$returnArray['upc_code'] = getFieldFromId("upc_code", "product_data", "product_id", $productId);
                        $productRow = ProductCatalog::getCachedProductRow($productId);
                        $analyticsEvent = array();
                        $analyticsEvent['event'] = "add_to_wishlist";
                        $analyticsEvent['event_data']['quantity'] = 1;
                        $analyticsEvent['event_data']['product_key'] = getAnalyticsProductKey($productRow);
                        $analyticsEvent['event_data']['upc_code'] = $productRow['upc_code'];
                        $analyticsEvent['event_data']['product_name'] = $productRow['description'];
                        $analyticsEvent['event_data']['manufacturer_name'] = $productRow['manufacturer_name'];
                        $productCatalog = new ProductCatalog();
                        $salePriceInfo = $productCatalog->getProductSalePrice($productId);
                        $analyticsEvent['event_data']['sale_price'] = floatval(str_replace(["$", ","], "", $salePriceInfo['sale_price']));
                        $analyticsEvent['event_data']['product_category'] = getFieldFromId("description", "product_categories", "product_category_id", $productRow['product_category_ids'][0]);
                        $returnArray["analytics_event"] = $analyticsEvent;
					} catch (Exception $e) {
						$returnArray['wish_list_item_count'] = 0;
					}
				}
				ajaxResponse($returnArray);
				break;
			case "remove_from_wish_list":
				if ($GLOBALS['gLoggedIn']) {
					if (!empty($_GET['wish_list_id'])) {
						$_GET['wish_list_id'] = getFieldFromId("wish_list_id", "wish_lists", "wish_list_id", $_GET['wish_list_id'], "user_id = ?", $GLOBALS['gUserId']);
					}
					try {
						$wishList = new WishList($GLOBALS['gUserId'], $_GET['wish_list_id']);
						$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id']);
						$returnArray['upc_code'] = getFieldFromId("upc_code", "product_data", "product_id", $productId);
						$wishList->removeProduct($productId);
						$returnArray['wish_list_item_count'] = $wishList->getWishListItemsCount();
                        $productRow = ProductCatalog::getCachedProductRow($productId);
                        $analyticsEvent = array();
                        $analyticsEvent['event'] = "remove_from_wishlist";
                        $analyticsEvent['event_data']['quantity'] = 1;
                        $analyticsEvent['event_data']['product_key'] = getAnalyticsProductKey($productRow);
                        $analyticsEvent['event_data']['upc_code'] = $productRow['upc_code'];
                        $analyticsEvent['event_data']['product_name'] = $productRow['description'];
                        $analyticsEvent['event_data']['manufacturer_name'] = $productRow['manufacturer_name'];
                        $productCatalog = new ProductCatalog();
                        $salePriceInfo = $productCatalog->getProductSalePrice($productId);
                        $analyticsEvent['event_data']['sale_price'] = floatval(str_replace(["$", ","], "", $salePriceInfo['sale_price']));
                        $analyticsEvent['event_data']['product_category'] = getFieldFromId("description", "product_categories", "product_category_id", $productRow['product_category_ids'][0]);
                        $returnArray["analytics_event"] = $analyticsEvent;
					} catch (Exception $e) {
						$returnArray['wish_list_item_count'] = 0;
					}
				}
				ajaxResponse($returnArray);
				break;
			case "set_wish_list_item_notify":
				if ($GLOBALS['gLoggedIn']) {
					if (!empty($_GET['wish_list_id'])) {
						$_GET['wish_list_id'] = getFieldFromId("wish_list_id", "wish_lists", "wish_list_id", $_GET['wish_list_id'], "user_id = ?", $GLOBALS['gUserId']);
					}
					try {
						$wishList = new WishList($GLOBALS['gUserId'], $_GET['wish_list_id']);
						$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id'], "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
						$wishList->addItem(array("product_id" => $productId, "notify_when_in_stock" => !empty($_GET['notify_when_in_stock'])));
					} catch (Exception $e) {
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_shopping_cart_items":
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				if (!$GLOBALS['gUserRow']['administrator_flag'] || empty($_GET['contact_id'])) {
					$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				} else {
					$shoppingCart = ShoppingCart::getShoppingCartForContact($_GET['contact_id'], $_GET['shopping_cart_code']);
				}
				$returnArray['order_upsell_products'] = $shoppingCart->getOrderUpsellProducts();
				$shoppingCartItems = $shoppingCart->getShoppingCartItems(array("reset_sale_price" => ($_GET['shopping_cart_code'] != "ORDERENTRY")));
				$shippingMethods = $shoppingCart->getShippingOptions($_POST['country_id'], $_POST['state'], $_POST['postal_code']);
				$estimatedShippingCharge = 0;
				foreach ($shippingMethods as $thisShippingMethod) {
					if (empty($thisShippingMethod['pickup']) && $thisShippingMethod['shipping_charge'] > 0) {
						if (empty($estimatedShippingCharge) || $thisShippingMethod['shipping_charge'] < $estimatedShippingCharge) {
							$estimatedShippingCharge = $thisShippingMethod['shipping_charge'];
						}
					}
				}
				if (!empty($estimatedShippingCharge)) {
					$returnArray['estimated_shipping_charge'] = number_format($estimatedShippingCharge, 2);
				}

				$missingProductImage = getImageFilenameFromCode("NO_PRODUCT_IMAGE");
				if (empty($missingProductImage) || $missingProductImage == " / images / empty.jpg") {
					$missingProductImage = getPreference("DEFAULT_PRODUCT_IMAGE");
				}
				if (empty($missingProductImage)) {
					$missingProductImage = " / images / empty.jpg";
				}

				$productIds = array();
				$productCatalog = new ProductCatalog();
				foreach ($shoppingCartItems as $thisItem) {
					$productIds[] = $thisItem['product_id'];
				}

				$pickupLocations = array();
				$resultSet = executeReadQuery("select * from locations where client_id = ? and inactive = 0 and internal_use_only = 0 and product_distributor_id is null and warehouse_location = 0 and location_id in(select location_id from shipping_methods where " .
					"inactive = 0 and internal_use_only = 0 and location_id is not null)", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$pickupLocations[] = $row['location_id'];
				}

				$defaultLocationId = "";
				if ($GLOBALS['gLoggedIn']) {
					$defaultLocationId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID");
				}
				if (empty($defaultLocationId)) {
					$defaultLocationId = $_COOKIE['default_location_id'];
				}
				if (empty($defaultLocationId) && count($pickupLocations) == 1) {
					$defaultLocationId = $pickupLocations[0];
				}

				$defaultLocationId = getFieldFromId("location_id", "locations", "location_id", $defaultLocationId, "product_distributor_id is null and inactive = 0 and internal_use_only = 0");
				if ($GLOBALS['gLoggedIn']) {
					CustomField::setCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID", $defaultLocationId);
				}
				setCoreCookie("default_location_id", $defaultLocationId, (24 * 365 * 10));
				$_COOKIE['default_location_id'] = $defaultLocationId;
				$returnArray['default_location_id'] = $defaultLocationId;
				if (!empty($defaultLocationId)) {
					$locationRow = getRowFromId("locations", "location_id", $defaultLocationId);
					$defaultLocationCanShip = (empty($locationRow['cannot_ship']));
					$locationContactRow = Contact::getContact($locationRow['contact_id']);
					$returnArray['location_description'] = $locationRow['description'];
				}

				$inventoryCounts = $productCatalog->getInventoryCounts(true, $productIds);
				$inventoryCountDetails = $productCatalog->getInventoryCounts(false, $productIds);

				$validPaymentMethods = array();
				$invalidPaymentMethods = array();
				$shippingRequired = false;
				$somePickupProducts = false;
				$returnArray['jquery_templates'] = "";
				$returnArray['custom_field_data'] = array();
				$credovaPaymentMethodId = getReadFieldFromId("payment_method_id", "payment_methods", "payment_method_code", "CREDOVA", "inactive = 0");
				$class3ProductTagId = getReadFieldFromId("product_tag_id", "product_tags", "product_tag_code", "CLASS_3");
				$class3Exists = false;
				$giftCardExists = false;

				$resultSet = executeReadQuery("select * from shipping_methods where client_id = ? and inactive = 0 and pickup = 1" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"), $GLOBALS['gClientId']);
				$pickupCount = $resultSet['row_count'];
				$cartTotal = 0;
				$totalSavings = 0;

				$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");

				foreach ($shoppingCartItems as $index => $thisItem) {
					$cartTotal += ($thisItem['sale_price'] * $thisItem['quantity']);
					$unitPrice = $thisItem['sale_price'];
					if (is_array($thisItem['product_addons'])) {
						foreach ($thisItem['product_addons'] as $thisAddon) {
							$cartTotal += $thisAddon['quantity'] * $thisAddon['sale_price'];
							$unitPrice += $thisAddon['sale_price'];
						}
					}
					$shoppingCartItems[$index]['unit_price'] = $thisItem['unit_price'] = $unitPrice;

					if (!empty($credovaPaymentMethodId) && !$class3Exists && !empty($class3ProductTagId)) {
						$productTagLinkId = getReadFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $thisItem['product_id'], "product_tag_id = ?", $class3ProductTagId);
						if (!empty($productTagLinkId)) {
							$class3Exists = true;
						}
					}
					$productTypeCode = getReadFieldFromId("product_type_code", "product_types", "product_type_id", getFieldFromId("product_type_id", "products", "product_id", $thisItem['product_id']));
					switch ($productTypeCode) {
						case "GIFT_CARD":
							if (!$giftCardExists) {
								$resultSet = executeReadQuery("select * from payment_methods where client_id = ? and payment_method_type_id not in(select payment_method_type_id from payment_method_types where payment_method_type_code in('CREDIT_CARD', 'CREDOVA'))", $GLOBALS['gClientId']);
								while ($row = getNextRow($resultSet)) {
									if (!in_array($row['payment_method_id'], $invalidPaymentMethods)) {
										$invalidPaymentMethods[] = $row['payment_method_id'];
									}
								}
								$giftCardExists = true;
							}
							break;
						case "EVENT_REGISTRATION":
							$customFieldTypeId = getReadFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "ORDER_ITEMS");
							$customFieldId = getReadFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "EVENT_REGISTRANTS", "custom_field_type_id = ?", $customFieldTypeId);
							if (empty($customFieldId)) {
								$insertSet = executeQuery("insert into custom_fields(client_id, custom_field_code, description, custom_field_type_id, form_label) values(?,?,?,?,?)",
									$GLOBALS['gClientId'], "EVENT_REGISTRANTS", "Event Registrants", $customFieldTypeId, "Event Registrants");
								$customFieldId = $insertSet['insert_id'];
								executeQuery("insert into custom_field_controls(custom_field_id, control_name, control_value) values(?,'data_type','custom')", $customFieldId);
								executeQuery("insert into custom_field_controls(custom_field_id, control_name, control_value) values(?,'control_class','EditableList')", $customFieldId);
								executeQuery("insert into custom_field_controls(custom_field_id, control_name, control_value) values(?,'column_list','first_name,last_name,email_address,phone_number')", $customFieldId);
								executeQuery("insert into custom_field_controls(custom_field_id, control_name, control_value) values(?,'list_table_controls','control-values\nfirst_name:data_type=varchar\nfirst_name:form_label=First Name\nlast_name:data_type=varchar\nlast_name:form_label=Last Name\nemail_address:data_type=varchar\nemail_address:data_format=email\nemail_address:form_label=Email\nphone_number:data_type=varchar\nphone_number:data_format=phone\nphone_number:form_label=Phone')", $customFieldId);
								executeQuery("insert into custom_field_controls(custom_field_id, control_name, control_value) values(?,'primary_table','order_items')", $customFieldId);
								executeQuery("insert into custom_field_controls(custom_field_id, control_name, control_value) values(?,'one_per_item','true')", $customFieldId);
							}
							$productCustomFieldId = getFieldFromId("product_custom_field_id", "product_custom_fields", "product_id", $thisItem['product_id'], "custom_field_id = ?", $customFieldId);
							if (empty($productCustomFieldId)) {
								executeQuery("insert into product_custom_fields(product_id, custom_field_id) values(?,?)", $thisItem['product_id'], $customFieldId);
							}

							break;
					}

					$paymentMethods = array();
					$resultSet = executeReadQuery("select product_id,group_concat(payment_method_id) from product_payment_methods where product_id = ?", $thisItem['product_id']);
					if ($row = getNextRow($resultSet)) {
						$paymentMethods = array_filter(explode(",", $row['group_concat(payment_method_id)']));
						if (!empty($paymentMethods)) {
							if (empty($validPaymentMethods)) {
								$validPaymentMethods = $paymentMethods;
							} else {
								$validPaymentMethods = array_intersect($validPaymentMethods, $paymentMethods);
							}
						}
					}
					$productRow = ProductCatalog::getCachedProductRow($thisItem['product_id']);

					$resultSet = executeReadQuery("select * from product_tag_payment_methods where product_tag_id in(select product_tag_id from product_tag_links where product_id = ?)", $thisItem['product_id']);
					while ($row = getNextRow($resultSet)) {
						if (!in_array($row['payment_method_id'], $invalidPaymentMethods)) {
							$invalidPaymentMethods[] = $row['payment_method_id'];
						}
					}

					if ($thisItem['sale_price'] > $productRow['manufacturer_advertised_price']) {
						$productRow['manufacturer_advertised_price'] = "";
					}

					if (!empty($productRow['order_maximum']) && $GLOBALS['gLoggedIn']) {
						$resultSet = executeReadQuery("select sum(quantity) from order_items where deleted = 0 and product_id = ? and order_id in(select order_id from orders where deleted = 0 and contact_id = ?)",
							$productRow['product_id'], $GLOBALS['gUserRow']['contact_id']);
						$purchased = 0;
						if ($row = getNextRow($resultSet)) {
							$purchased = $row['sum(quantity)'];
							if (empty($purchased)) {
								$purchased = 0;
							}
						}
						$cartMaximum = max(0, $productRow['order_maximum'] - $purchased);
						if (empty($productRow['cart_maximum']) || $cartMaximum < $productRow['cart_maximum']) {
							$productRow['cart_maximum'] = $cartMaximum;
						}
                        // Make sure cart_minimum can't override order_maximum
                        if(!empty($productRow['cart_minimum']) && $cartMaximum < $productRow['cart_minimum']) {
                            $productRow['cart_minimum'] = $cartMaximum;
                        }
					}

					$pickupOnly = CustomField::getCustomFieldData($productRow['product_id'], "PICKUP_ONLY", "PRODUCTS");
					if (!empty($pickupOnly)) {
						$somePickupProducts = true;
					}
					if (empty($productRow['virtual_product']) && (empty($pickupOnly) || $pickupCount > 0)) {
						$shippingRequired = true;
					}

					$shoppingCartItems[$index]['description'] = getFirstPart($productRow['description'], 80);
					$shoppingCartItems[$index]['map_policy'] = getFieldFromId("map_policy_code", "map_policies", "map_policy_id", getFieldFromId("map_policy_id", "product_manufacturers", "product_manufacturer_id", $productRow['product_manufacturer_id']));
					$shoppingCartItems[$index]['product_code'] = getFirstPart($productRow['product_code'], 40);
					$shoppingCartItems[$index]['upc_code'] = $productRow['upc_code'];
					$shoppingCartItems[$index]['manufacturer_sku'] = $productRow['manufacturer_sku'];
					$shoppingCartItems[$index]['model'] = $productRow['model'];
					$shoppingCartItems[$index]['list_price'] = (empty($productRow['list_price']) ? "" : number_format($productRow['list_price'], 2));
					$shoppingCartItems[$index]['small_image_url'] = ProductCatalog::getProductImage($productRow['product_id'], array("image_type" => "small", "default_image" => $missingProductImage));
					$shoppingCartItems[$index]['image_url'] = ProductCatalog::getProductImage($productRow['product_id'], array("default_image" => $missingProductImage));
					$shoppingCartItems[$index]['sale_price'] = number_format($thisItem['sale_price'], 2);
					$shoppingCartItems[$index]['unit_price'] = number_format($thisItem['unit_price'], 2);
					if ($GLOBALS['gUserRow']['administrator_flag']) {
						$shoppingCartItems[$index]['base_cost'] = number_format($productRow['base_cost'], 2);
					}

					if (empty($shoppingCartItems[$index]['original_sale_price']) && $productRow['list_price'] > $thisItem['sale_price']) {
						$shoppingCartItems[$index]['original_sale_price'] = $productRow['list_price'];
					}
					if (!empty($shoppingCartItems[$index]['original_sale_price'])) {
						$shoppingCartItems[$index]['original_sale_price'] = number_format($shoppingCartItems[$index]['original_sale_price'], 2);
					} else {
						$shoppingCartItems[$index]['original_sale_price'] = "";
					}
					if (!empty($shoppingCartItems[$index]['original_sale_price']) && !empty($thisItem['unit_price']) & $shoppingCartItems[$index]['original_sale_price'] > 0) {
						$originalSalePrice = (float)str_replace(",", "", $shoppingCartItems[$index]['original_sale_price']);
						$shoppingCartItems[$index]['discount'] = round(100 - ($thisItem['unit_price'] * 100 / $originalSalePrice)) . "%";
						$shoppingCartItems[$index]['savings'] = number_format(round($originalSalePrice - $thisItem['unit_price'], 2), 2);
						$totalSavings += ($thisItem['quantity'] * ($originalSalePrice - $thisItem['unit_price']));
					} else {
						$shoppingCartItems[$index]['discount'] = "";
						$shoppingCartItems[$index]['savings'] = "";
					}
					$neverOutOfStock = getPreference("RETAIL_STORE_NEVER_OUT_OF_STOCK");
					if (empty($neverOutOfStock) && empty($productRow['non_inventory_item'])) {
						$shoppingCartItems[$index]['inventory_quantity'] = $inventoryCounts[$thisItem['product_id']];
					} else {
						$shoppingCartItems[$index]['inventory_quantity'] = 1;
					}

					$productRestrictions = "";
					if ($GLOBALS['gLoggedIn']) {
						$ignoreProductRestrictions = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "IGNORE_PRODUCT_RESTRICTIONS");
					} else {
						$ignoreProductRestrictions = true;
					}
					if (empty($ignoreProductRestrictions)) {
						$resultSet = executeQuery("select state,postal_code,country_id from product_restrictions where product_id = ? and state = ? and (postal_code = ? or postal_code is null) and country_id = ? union " .
							"select state,postal_code,country_id from product_category_restrictions where product_category_id in(select product_category_id from product_category_links where product_id = ?) and state = ? and (postal_code = ? or postal_code is null) and country_id = ? union " .
							"select state,postal_code,country_id from product_department_restrictions where(product_department_id in(select product_department_id from product_category_departments where " .
							"product_category_id in(select product_category_id from product_category_links where product_id = ? and state = ? and (postal_code = ? or postal_code is null) and country_id = ?)) or product_department_id in(select product_department_id from " .
							"product_category_group_departments where product_category_group_id in(select product_category_group_id from product_category_group_links where product_category_id in " .
							"(select product_category_id from product_category_links where product_id = ? and state = ? and (postal_code = ? or postal_code is null) and country_id = ?))))",
							$thisItem['product_id'], $GLOBALS['gUserRow']['state'], $GLOBALS['gUserRow']['postal_code'], $GLOBALS['gUserRow']['country_id'],
							$thisItem['product_id'], $GLOBALS['gUserRow']['state'], $GLOBALS['gUserRow']['postal_code'], $GLOBALS['gUserRow']['country_id'],
							$thisItem['product_id'], $GLOBALS['gUserRow']['state'], $GLOBALS['gUserRow']['postal_code'], $GLOBALS['gUserRow']['country_id'],
							$thisItem['product_id'], $GLOBALS['gUserRow']['state'], $GLOBALS['gUserRow']['postal_code'], $GLOBALS['gUserRow']['country_id']);
						$usedRestrictions = array();
						$stateArray = getStateArray();
						while ($row = getNextRow($resultSet)) {
							if (in_array(jsonEncode($row), $usedRestrictions)) {
								continue;
							}
							if (empty($row['state']) && empty($row['postal_code']) && empty($row['country_id'])) {
								continue;
							}
							if (empty($row['state']) && empty($row['postal_code']) && $row['country_id'] == 1000) {
								continue;
							}
							$ignoreStateRestriction = CustomField::getCustomFieldData($thisItem['product_id'], "IGNORE_RESTRICTIONS_" . strtoupper($row['state']), "PRODUCTS");
							if (!empty($ignoreStateRestriction)) {
								continue;
							}
							$usedRestrictions[] = jsonEncode($row);
							$restrictions = "";
							if (!empty($row['state'])) {
								$state = $stateArray[$row['state']];
								if (empty($state)) {
									$state = $row['state'];
								}
								$restrictions .= (empty($restrictions) ? "" : ", ") . $state;
							}
							if (!empty($row['postal_code'])) {
								$restrictions .= (empty($restrictions) ? "" : ", ") . $row['postal_code'];
							}
							if (!empty($row['country_id']) && $row['country_id'] != 1000) {
								$restrictions .= (empty($restrictions) ? "" : ", ") . getFieldFromId("country_name", "countries", "country_id", $row['country_id']);
							}
							if (empty($restrictions)) {
								continue;
							}
							$productRestrictions .= (empty($productRestrictions) ? "" : "; ") . $restrictions;
						}
					}
					$shoppingCartItems[$index]['product_restrictions'] = (empty($productRestrictions) ? "" : "This product cannot be sold in " . $productRestrictions);

					/* inventory flags:
						Store pickup - default location is selected, product is in stock at that location
						Ship to My Store - distributor inventory available
						Ship to Home - product is NOT FFL required and distributor inventory available or default location has inventory AND not tagged cannot ship
						Ship to Dealer - product IS FFL required and distributor inventory available or default location has inventory AND not tagged cannot ship
					*/

					$productTagIds = explode(",", $thisItem['product_tag_ids']);
					$fflRequired = !empty($fflRequiredProductTagId) && in_array($fflRequiredProductTagId, $productTagIds);
					$shippingOptions = array();
					$itemInventory = $inventoryCountDetails[$thisItem['product_id']];

					$shippingOptions["pickup"] = (!empty($defaultLocationId) && !empty($itemInventory[$defaultLocationId]));
					$shippingOptions["ship_to_store"] = (!$pickupOnly && !empty($defaultLocationId) && empty($itemInventory[$defaultLocationId]) && !empty($itemInventory['distributor']));
					$shippingOptions["ship_to_home"] = !$pickupOnly && !$fflRequired && (!empty($itemInventory['distributor']) || (!empty($defaultLocationId) && $defaultLocationCanShip && !empty($itemInventory[$defaultLocationId])));
					$shippingOptions["ship_to_ffl"] = !$pickupOnly && $fflRequired && (!empty($itemInventory['distributor']) || (!empty($defaultLocationId) && $defaultLocationCanShip && !empty($itemInventory[$defaultLocationId])));
					$shoppingCartItems[$index]['shipping_options'] = $shippingOptions;
					$shoppingCartItems[$index]['pickup'] = ($shippingOptions['pickup'] ? "shipping-option-pickup" : "");
					$shoppingCartItems[$index]['ship_to_store'] = ($shippingOptions['ship_to_store'] ? "shipping-option-ship-to-store" : "");
					$shoppingCartItems[$index]['ship_to_home'] = ($shippingOptions['ship_to_home'] ? "shipping-option-ship-to-home" : "");
					$shoppingCartItems[$index]['ship_to_ffl'] = ($shippingOptions['ship_to_ffl'] ? "shipping-option-ship-to-ffl" : "");

					$shoppingCartItems[$index]['cart_maximum'] = $productRow['cart_maximum'];
					if (is_numeric($productRow['cart_maximum']) && $thisItem['quantity'] > $productRow['cart_maximum']) {
						$shoppingCartItems[$index]['quantity'] = $productRow['cart_maximum'];
					}
                    if($shoppingCartItems[$index]['quantity'] <= 0) {
                        unset($shoppingCartItems[$index]);
                        continue;
                    }
					$shoppingCartItems[$index]['cart_minimum'] = $productRow['cart_minimum'];
					if (!empty($productRow['cart_minimum']) && $productRow['cart_minimum'] > 0 && $thisItem['quantity'] < $productRow['cart_minimum']) {
						$shoppingCartItems[$index]['quantity'] = $productRow['cart_minimum'];
					}
					$shoppingCartItems[$index]['no_online_order'] = $productRow['no_online_order'];

					ob_start();
					$customFields = CustomField::getCustomFields("order_items");
					foreach ($customFields as $thisCustomField) {
						$productCustomFieldId = getFieldFromId("product_custom_field_id", "product_custom_fields", "custom_field_id", $thisCustomField['custom_field_id'],
							"product_id = ?", $thisItem['product_id']);
						if (empty($productCustomFieldId)) {
							continue;
						}
						$columnName = "custom_field_" . $thisCustomField['custom_field_id'] . "_item_" . $thisItem['shopping_cart_item_id'];
						$customField = new CustomField($thisCustomField['custom_field_id'], $columnName);
						$customField->addColumnControl("classes", "order-item-custom-field");
						$customField->addColumnControl("form_line_classes", "order-item-custom-field");
						if ($customField) {
							switch ($thisCustomField['custom_field_code']) {
								case "ADDITIONAL_MEMBERS":
									$additionalMemberLimit = CustomField::getCustomFieldData($thisItem['product_id'], "ADDITIONAL_MEMBERS_LIMIT", "PRODUCTS");
									if (!empty($additionalMemberLimit)) {
										$customField->addColumnControl("maximum_rows", $additionalMemberLimit);
									}
									break;
							}
							$contactCustomFieldCode = getFieldFromId("control_value", "custom_field_controls", "custom_field_id", $thisCustomField['custom_field_id'], "control_name = 'contact_custom_field_code'");
							if (!empty($contactCustomFieldCode)) {
								$customFieldValue = CustomField::getCustomFieldData($shoppingCart->getContact(), $contactCustomFieldCode);
								$customField->addColumnControl("initial_value", $customFieldValue);
							}
							if ($customField->getColumnControl("one_per_item")) {
								$customField->addColumnControl("maximum_rows", $thisItem['quantity']);
								$customField->addColumnControl("minimum_rows", $thisItem['quantity']);
							}
							echo $customField->getControl();
							$returnArray['jquery_templates'] .= $customField->getTemplate();
							$customFieldData = $customField->getRecord();
							unset($customFieldData['select_values']);
							foreach ($customFieldData as $thisIndex => $thisData) {
								$returnArray['custom_field_data'][$thisIndex] = $thisData;
							}
						}
					}
					$shoppingCartItems[$index]['custom_fields'] = ob_get_clean();
					$shoppingCartItems[$index]['custom_field_classes'] = (empty($shoppingCartItems[$index]['custom_fields']) ? "" : "active-fields");

					ob_start();
					# copy product category addons to the product
					ProductCatalog::copyProductCategoryAddons($thisItem['product_id']);
					$productAddons = array();

					$resultSet = executeReadQuery("select * from product_addons where product_id = ? and inactive = 0 order by sort_order", $thisItem['product_id']);
					$addonIndex = -1;
					while ($row = getNextRow($resultSet)) {
						if (!empty($row['inventory_product_id'])) {
							$addonInventoryCounts = $productCatalog->getInventoryCounts(true, $row['inventory_product_id']);
							if (empty($addonInventoryCounts[$row['inventory_product_id']]) || $addonInventoryCounts[$row['inventory_product_id']] < 0) {
								continue;
							}
						}
						$row['maximum_quantity'] = (empty($row['maximum_quantity']) || $row['maximum_quantity'] <= 0 ? 1 : $row['maximum_quantity']);
						if (empty($row['group_description'])) {
							$addonIndex++;
							if ($row['maximum_quantity'] <= 1) {
								$row['data_type'] = "tinyint";
							} else {
								$row['data_type'] = "int";
								$row['minimum_value'] = "0";
							}
							$productAddons[$addonIndex] = $row;
						} else {
							$foundIndex = false;
							foreach ($productAddons as $addonIndex => $checkAddon) {
								if ($checkAddon['data_type'] == "select" && $checkAddon['group_description'] == $row['group_description']) {
									$foundIndex = $addonIndex;
									break;
								}
							}
							if ($foundIndex === false) {
								$addonIndex++;
								$productAddons[$addonIndex] = array("data_type" => "select", "group_description" => $row['group_description'], "maximum_quantity" => $row['maximum_quantity'], "options" => array());
								$foundIndex = $addonIndex;
							}
							$productAddons[$foundIndex]['maximum_quantity'] = max($productAddons[$foundIndex]['maximum_quantity'], $row['maximum_quantity']);
							$productAddons[$foundIndex]['options'][] = $row;
						}
					}

					foreach ($productAddons as $addonIndex => $thisAddon) {
						if (!empty($thisAddon['form_definition_id'])) {
							$shoppingCartItemAddonRow = getReadRowFromId("shopping_cart_item_addons", "shopping_cart_item_id", $thisItem['shopping_cart_item_id'], "product_addon_id = ?", $thisAddon['product_addon_id']);
							if (!empty($shoppingCartItemAddonRow)) {
								if (function_exists("_localProductAddonSummary")) {
									_localProductAddonSummary($thisAddon['form_definition_id'], $shoppingCartItemAddonRow['content']);
								} else {
									$formDefinitionCode = getFieldFromId("form_definition_code", "form_definitions", "form_definition_id", $thisAddon['form_definition_id']);
									$summaryFragmentContent = getFieldFromId("content", "fragments", "fragment_code", $formDefinitionCode . "_SUMMARY");
									$additionalSubstitutions = array_merge($thisAddon, (empty($shoppingCartItemAddonRow['content']) ? array() : json_decode($shoppingCartItemAddonRow['content'], true)));
									echo Placeholders::massageContent($summaryFragmentContent, $additionalSubstitutions);
									$description = $thisAddon['description'] . ($thisAddon['sale_price'] == 0 ? "" : " ($" . number_format($thisAddon['sale_price'], 2, ".", ",") . ")");
									$formLink = getFieldFromId("link_name", "pages", "script_filename", "generateform.php", "inactive = 0 and internal_use_only = 0 and script_arguments = 'code=" . $formDefinitionCode . "'") .
										"?shopping_cart_item_id=" . $thisItem['shopping_cart_item_id'] . "&product_addon_id=" . $thisAddon['product_addon_id'];
									?>
									<div class='form-line'>
										<p><?= $description ?> - <a href="/<?= $formLink ?>">Make changes</a></p>
									</div>
								<?php }
							}
							?>
							<input type='hidden' class='product-addon' data-sale_price="<?= $shoppingCartItemAddonRow['sale_price'] ?>" id='addon_<?= $thisAddon['product_addon_id'] ?>_<?= $thisItem['shopping_cart_id'] ?>' name='addon_<?= $thisAddon['product_addon_id'] ?>_<?= $thisItem['shopping_cart_id'] ?>' value='1'>
							<?php
							continue;
						}
						if ($thisAddon['data_type'] == "tinyint") {
							$columnName = "addon_" . $thisAddon['product_addon_id'] . "_" . $thisItem['shopping_cart_item_id'];
							$description = $thisAddon['description'] . ($thisAddon['sale_price'] == 0 ? "" : " ($" . number_format($thisAddon['sale_price'], 2, ".", ",") . ")");
							$shoppingCartItemAddonId = getReadFieldFromId("shopping_cart_item_addon_id", "shopping_cart_item_addons", "shopping_cart_item_id", $thisItem['shopping_cart_item_id'], "product_addon_id = ?", $thisAddon['product_addon_id']);
							?>
							<div class="form-line" id="_<?= $columnName ?>_row">
								<input tabindex='10' class='product-addon' data-sale_price="<?= $thisAddon['sale_price'] ?>" type='checkbox' <?= (empty($shoppingCartItemAddonId) ? "" : " checked") ?> id='<?= $columnName ?>' name='<?= $columnName ?>' value="1"><label class='checkbox-label' for='<?= $columnName ?>'><?= htmlText($description) ?></label>
								<div class='clear-div'></div>
							</div>
							<?php
						} elseif ($thisAddon['data_type'] == "int") {
							$columnName = "addon_" . $thisAddon['product_addon_id'] . "_" . $thisItem['shopping_cart_item_id'];
							$description = $thisAddon['description'] . " (" . "$" . number_format($thisAddon['sale_price'], 2) . " each)";
							$description = $thisAddon['description'] . ($thisAddon['sale_price'] == 0 ? "" : " ($" . number_format($thisAddon['sale_price'], 2, ".", ",") . " each)");
							$quantity = getReadFieldFromId("quantity", "shopping_cart_item_addons", "shopping_cart_item_id", $thisItem['shopping_cart_item_id'], "product_addon_id = ?", $thisAddon['product_addon_id']);
							?>
							<div class="form-line" id="_<?= $columnName ?>_row">
								<label><?= htmlText($description) ?></label>
								<input tabindex='10' type='text' data-maximum_quantity='<?= $thisAddon['maximum_quantity'] ?>' class='product-addon-quantity product-addon validate[required,custom[integer],min[0],max[<?= $thisAddon['maximum_quantity'] ?>]' data-sale_price="<?= $thisAddon['sale_price'] ?>" id='<?= $columnName ?>' name='<?= $columnName ?>' value="<?= $quantity ?>">
								<div class='clear-div'></div>
							</div>
							<?php
						} else {
							$columnName = "addon_select_" . $addonIndex . "_" . $thisItem['shopping_cart_item_id'];
							?>
							<div class="form-line" id="_<?= $columnName ?>_row">
								<label><?= $thisAddon['group_description'] ?><span class='required-tag fa fa-asterisk'></span></label>
								<?php if ($thisAddon['maximum_quantity'] > 1) { ?>
									<input type='text' class='addon-select-quantity validate[custom[integer],min[1]]' value='1' name='quantity_<?= $columnName ?>' id='quantity_<?= $columnName ?>'>
								<?php } else { ?>
									<input type='hidden' class='addon-select-quantity' value='1' name='quantity_<?= $columnName ?>' id='quantity_<?= $columnName ?>'>
								<?php } ?>
								<select tabindex='10' class='validate[required] product-addon' id='<?= $columnName ?>' name='<?= $columnName ?>'>
									<option value="">[Select]</option>
									<?php
									foreach ($thisAddon['options'] as $thisOption) {
										$optionId = "addon_" . $thisOption['product_addon_id'] . "_" . $thisItem['shopping_cart_item_id'];
										$shoppingCartItemAddonId = getFieldFromId("shopping_cart_item_addon_id", "shopping_cart_item_addons", "shopping_cart_item_id", $thisItem['shopping_cart_item_id'], "product_addon_id = ?", $thisOption['product_addon_id']);
										?>
										<option id="<?= $optionId ?>" data-maximum_quantity='<?= $thisOption['maximum_quantity'] ?>' <?= (empty($shoppingCartItemAddonId) ? "" : " selected") ?> value='<?= $thisOption['product_addon_id'] ?>' data-sale_price="<?= $thisOption['sale_price'] ?>"><?= $thisOption['description'] . ($thisOption['sale_price'] == 0 ? "" : " ($" . number_format($thisOption['sale_price'], 2) . ")") ?></option>
										<?php
									}
									?>
								</select>
								<div class='clear-div'></div>
							</div>
							<?php
						}
					}
					$shoppingCartItems[$index]['item_addons'] = ob_get_clean();
					$shoppingCartItems[$index]['addon_classes'] = (empty($shoppingCartItems[$index]['item_addons']) ? "" : "active-fields");

					$subscriptionProductId = getReadFieldFromId("subscription_product_id", "subscription_products", "setup_product_id", $thisItem['product_id'], "recurring_payment_type_id is not null");
					$subscriptionRenewalProductId = getReadFieldFromId("subscription_product_id", "subscription_products", "product_id", $thisItem['product_id'], "recurring_payment_type_id is not null");
					if (!empty($subscriptionProductId) || !empty($subscriptionRenewalProductId)) {
						$shoppingCartItems[$index]['recurring_payment'] = true;
					}
					$shoppingCartItems[$index]['other_classes'] = "";
					$resultSet = executeReadQuery("select * from product_tag_links join product_tags using (product_tag_id) where product_id = ?", $thisItem['product_id']);
					while ($row = getNextRow($resultSet)) {
						$shoppingCartItems[$index]['other_classes'] .= (empty($shoppingCartItems[$index]['other_classes']) ? "" : " ") . "product-tag-" . strtolower($row['product_tag_code']);
					}
				}
				$returnArray['total_savings'] = number_format($totalSavings, 2, ".", "");
				$returnArray['retail_agreements'] = array();
				$resultSet = executeReadQuery("select * from retail_agreements where client_id = ? and inactive = 0 and internal_use_only = 0 order by sort_order", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if ($cartTotal < $row['amount']) {
						continue;
					}

					if (!empty($row['product_tag_id'])) {
						$foundProduct = false;
						foreach ($shoppingCartItems as $index => $thisItem) {
							$productTagLinkId = getReadFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $thisItem['product_id'], "product_tag_id = ?", $row['product_tag_id']);
							if (!empty($productTagLinkId)) {
								$foundProduct = true;
								break;
							}
						}
						if (!$foundProduct) {
							continue;
						}
					}
					if (!empty($row['product_department_id'])) {
						$foundProduct = false;
						foreach ($shoppingCartItems as $index => $thisItem) {
							if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $row['product_department_id'])) {
								$foundProduct = true;
								break;
							}
						}
						if (!$foundProduct) {
							continue;
						}
					}
					$returnArray['retail_agreements'][] = $row;
				}
				$noShippingRequired = getPreference("RETAIL_STORE_NO_SHIPPING");
				if (!empty($noShippingRequired)) {
					$shippingRequired = false;
				}
				$returnArray['shipping_required'] = $shippingRequired;
				$returnArray['pickup_locations'] = $somePickupProducts;
				if (!empty($credovaPaymentMethodId) && $class3Exists) {
					if (empty($validPaymentMethods)) {
						$resultSet = executeReadQuery("select * from payment_methods where client_id = ? and inactive = 0 and internal_use_only = 0 and payment_method_code <> 'CREDOVA'", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							$validPaymentMethods[] = $row['payment_method_id'];
						}
					} else {
						foreach ($validPaymentMethods as $index => $thisPaymentMethod) {
							if ($thisPaymentMethod == $credovaPaymentMethodId) {
								unset($validPaymentMethods[$index]);
							}
						}
					}
				}
				$resultSet = executeReadQuery("select * from payment_methods where client_id = ? and inactive = 0 and internal_use_only = 0 and minimum_amount > 0", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					if ($row['minimum_amount'] > $cartTotal) {
						$invalidPaymentMethods[] = $row['payment_method_id'];
					}
				}
				if (empty($validPaymentMethods) && !empty($invalidPaymentMethods)) {
					$resultSet = executeReadQuery("select * from payment_methods where client_id = ? and inactive = 0 and internal_use_only = 0", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$validPaymentMethods[] = $row['payment_method_id'];
					}
				}

				$uniqueValidPaymentMethods = array();
				foreach ($validPaymentMethods as $thisPaymentMethodId) {
					if (!empty($thisPaymentMethodId) && !in_array($thisPaymentMethodId, $uniqueValidPaymentMethods) && !in_array($thisPaymentMethodId, $invalidPaymentMethods)) {
						$uniqueValidPaymentMethods[] = $thisPaymentMethodId;
					}
				}
				$returnArray['valid_payment_methods'] = implode(",", $uniqueValidPaymentMethods);
				usort($shoppingCartItems, array($this, "shoppingCartItemIdSort"));
				$returnArray['shopping_cart_items'] = $shoppingCartItems;
				$promotionId = $shoppingCart->getPromotionId();
				if (!empty($promotionId)) {
					$promotionRow = getReadRowFromId("promotions","promotion_id",$promotionId);
					$oneTimeUsePromotionCodeId = getReadFieldFromId("one_time_use_promotion_code_id","one_time_use_promotion_codes","promotion_id",$promotionId,"order_id is null");
					if (empty($oneTimeUsePromotionCodeId)) {
						$returnArray['promotion_code'] = $promotionRow['promotion_code'];
					} else {
						$returnArray['promotion_code'] = $shoppingCart->getPromotionCode();
					}
					$returnArray['promotion_id'] = $promotionRow['promotion_id'];
					$returnArray['promotion_code_description'] = $promotionRow['description'];
					$returnArray['promotion_code_details'] = makeHtml($promotionRow['detailed_description']);
					$discounts = $shoppingCart->getCartDiscount();
					$returnArray['discount_amount'] = $discounts['discount_amount'];
					$returnArray['discount_percent'] = $discounts['discount_percent'];
				} else {
					$returnArray['promotion_id'] = "";
					$returnArray['promotion_code'] = "";
					$returnArray['promotion_code_description'] = "";
					$returnArray['promotion_code_details'] = "";
					$returnArray['discount_amount'] = 0;
					$returnArray['discount_percent'] = 0;
				}
				if (empty($_GET['ignore_user_required']) && $shoppingCart->requiresUser() && !$GLOBALS['gLoggedIn']) {
					$sectionText = $this->getPageTextChunk("retail_store_no_guest_checkout");
					if (empty($sectionText)) {
						$sectionText = $this->getFragment("retail_store_no_guest_checkout");
					}
					if (empty($sectionText)) {
						ob_start();
						?>
						<p class='red-text'>The contents of this shopping cart require a user login. Please create an
							account <a href='/my-account'>here</a> or log in <a href='/login'>here</a> to order these
							product(s).</p>
						<?php
						$sectionText = ob_get_clean();
					}
					$sectionText = "<div id='requires_user'>" . $sectionText . "</div>";

					$returnArray['requires_user'] = $sectionText;
					ajaxResponse($returnArray);
					break;
				}

				$resultSet = executeReadQuery("select * from loyalty_programs where client_id = ? and (user_type_id = ? or user_type_id is null) and inactive = 0 and " .
					"internal_use_only = 0 order by user_type_id desc,sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gUserRow']['user_type_id']);
				if (!$loyaltyProgramRow = getNextRow($resultSet)) {
					$loyaltyProgramRow = array();
				}

				$pointAwards = 0;
				$resultSet = executeReadQuery("select max(point_value) from loyalty_program_awards where loyalty_program_id = ? and minimum_amount <= ?", $loyaltyProgramRow['loyalty_program_id'], $cartTotal);
				if ($row = getNextRow($resultSet)) {
					$pointAwards = $row['max(point_value)'];
				}
				$pointsEarned = 0;
				$itemCount = 0;

				foreach ($shoppingCartItems as $thisItem) {
					$pointsMultiplier = getFieldFromId("points_multiplier", "products", "product_id", $thisItem['product_id']);
					if (empty($pointsMultiplier) || $pointsMultiplier < 1) {
						$pointsMultiplier = 1;
					}
					$resultSet = executeReadQuery("select points_multiplier from product_categories where points_multiplier > 1 and product_category_id in " .
						"(select product_category_id from product_category_links where product_id = ?)", $thisItem['product_id']);
					while ($row = getNextRow($resultSet)) {
						if ($row['points_multiplier'] > $pointsMultiplier) {
							$pointsMultiplier = $row['points_multiplier'];
						}
					}
					$resultSet = executeReadQuery("select points_multiplier from product_tags where points_multiplier > 1 and product_tag_id in " .
						"(select product_tag_id from product_tag_links where product_id = ? and (start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date >= current_date))",
						$thisItem['product_id']);
					while ($row = getNextRow($resultSet)) {
						if ($row['points_multiplier'] > $pointsMultiplier) {
							$pointsMultiplier = $row['points_multiplier'];
						}
					}
					$thisPointsEarned = ($thisItem['quantity'] * str_replace(",", "", $thisItem['sale_price'])) * $pointsMultiplier * $pointAwards / 100;
					$pointsEarned += $thisPointsEarned;
					$itemCount += $thisItem['quantity'];
				}

				$returnArray['shopping_cart_item_count'] = $itemCount;
				$returnArray['loyalty_points_awarded'] = "";
				if ($pointsEarned > 0) {
					$returnArray['loyalty_points_awarded'] = "This purchase will add " . round($pointsEarned) . " loyalty points to your account.";
					$resultSet = executeReadQuery("select minimum_amount,point_value from loyalty_program_awards where loyalty_program_id = ? and minimum_amount > ? order by minimum_amount", $loyaltyProgramRow['loyalty_program_id'], $cartTotal);
					if ($row = getNextRow($resultSet)) {
						if ($row['point_value'] > $pointAwards) {
							$additionalPurchase = $row['minimum_amount'] - $cartTotal;
							$returnArray['loyalty_points_awarded'] .= " Add $" . number_format($additionalPurchase, 2) . " in product to this order to jump to the next level.";

						}
					}
				}

                if ($_GET['shopping_cart_code'] == "RETAIL" && !empty($shoppingCartItems)) {
                    $analyticsEvent = array();
                    $analyticsEvent['event'] = "load_cart";
                    $analyticsEvent['event_data']['items'] = $shoppingCartItems;
                    $returnArray["analytics_event"] = $analyticsEvent;
                }

				ajaxResponse($returnArray);

				break;
			case "get_item_availability_texts":
				if (empty($_GET['shopping_cart_code'])) {
					$_GET['shopping_cart_code'] = "RETAIL";
				}
				if (!$GLOBALS['gUserRow']['administrator_flag'] || empty($_GET['contact_id'])) {
					$shoppingCart = ShoppingCart::getShoppingCart($_GET['shopping_cart_code']);
				} else {
					$shoppingCart = ShoppingCart::getShoppingCartForContact($_GET['contact_id'], $_GET['shopping_cart_code']);
				}
				$shoppingCartItems = $shoppingCart->getShoppingCartItems(array("reset_sale_price" => true));

				$productIds = array();
				$productCatalog = new ProductCatalog();
				foreach ($shoppingCartItems as $index => $thisItem) {
					$productIds[] = $thisItem['product_id'];
				}
				$defaultLocationId = "";
				if ($GLOBALS['gLoggedIn']) {
					$defaultLocationId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID");
				}
				if (empty($defaultLocationId)) {
					$defaultLocationId = $_COOKIE['default_location_id'];
				}
				$inventoryCounts = $productCatalog->getInventoryCounts(true, $productIds);
				$locationAvailability = $productCatalog->getLocationAvailability($productIds);
				$showLocationAvailability = getPreference("RETAIL_STORE_SHOW_LOCATION_AVAILABILITY");

				foreach ($shoppingCartItems as $index => $thisItem) {
					$virtualProduct = getFieldFromId("virtual_product", "products", "product_id", $thisItem['product_id']);
					if (empty($defaultLocationId) || empty($showLocationAvailability) || !empty($virtualProduct)) {
						$returnArray['availability_' . $thisItem['shopping_cart_item_id']] = "";
						$returnArray['mini_cart_availability_' . $thisItem['shopping_cart_item_id']] = "";
					} else {
						if (!empty($locationAvailability[$thisItem['product_id']][$defaultLocationId])) {
							$returnArray['availability_' . $thisItem['shopping_cart_item_id']] = "Available for pickup at " . getFieldFromId("description", "locations", "location_id", $defaultLocationId);
							$returnArray['mini_cart_availability_' . $thisItem['shopping_cart_item_id']] = "Available for pickup at " . getFieldFromId("description", "locations", "location_id", $defaultLocationId);
						} else {
							$returnArray['availability_' . $thisItem['shopping_cart_item_id']] = "Will be ordered from our warehouse and may take a few days";
							$returnArray['mini_cart_availability_' . $thisItem['shopping_cart_item_id']] = "Will be ordered from our warehouse and may take a few days";
						}
					}
				}
				ajaxResponse($returnArray);
				break;
			case "get_wish_list_items":
				$wishListRow = array();
				if (!empty($_GET['wish_list_id'])) {
					$wishListRow = getRowFromId("wish_lists", "wish_list_id", $_GET['wish_list_id'], "user_id in (select user_id from users where client_id = ?)", $GLOBALS['gClientId']);
					if (empty($wishListRow)) {
						ajaxResponse($returnArray);
						break;
					}
				}
				try {
					$wishList = new WishList($wishListRow['user_id'], $wishListRow['wish_list_id']);
					$wishListItems = $wishList->getWishListItems();
				} catch (Exception $e) {
					$wishListItems = array();
				}
				$productCatalog = new ProductCatalog();
				$missingProductImage = getImageFilenameFromCode("NO_PRODUCT_IMAGE");
				if (empty($missingProductImage) || $missingProductImage == "/images/empty.jpg") {
					$missingProductImage = getPreference("DEFAULT_PRODUCT_IMAGE");
				}
				$productIds = array();
				foreach ($wishListItems as $index => $thisItem) {
					$productIds[] = $thisItem['product_id'];
				}
				$inventoryCounts = $productCatalog->getInventoryCounts(true, $productIds);
				foreach ($wishListItems as $index => $thisItem) {
					$productRow = ProductCatalog::getCachedProductRow($thisItem['product_id']);
					$productDataRow = getRowFromId("product_data", "product_id", $thisItem['product_id']);
					if (!is_array($productRow) || !is_array($productDataRow) || !is_array($thisItem)) {
						continue;
					}
					$wishListItems[$index] = array_merge($productRow, $productDataRow, $thisItem);
					$wishListItems[$index]['small_image_url'] = ProductCatalog::getProductImage($productRow['product_id'], array("image_type" => "small", "default_image" => $missingProductImage));
					$wishListItems[$index]['image_url'] = ProductCatalog::getProductImage($productRow['product_id'], array("default_image" => $missingProductImage));

					$salePriceInfo = $productCatalog->getProductSalePrice($thisItem['product_id'], array("product_information" => array_merge($productRow, $productDataRow)));
					$wishListItems[$index]['sale_price'] = $salePriceInfo['sale_price'];

					$neverOutOfStock = getPreference("RETAIL_STORE_NEVER_OUT_OF_STOCK");
					if (empty($neverOutOfStock)) {
						$wishListItems[$index]['inventory_quantity'] = $inventoryCounts[$thisItem['product_id']];
					} else {
						$wishListItems[$index]['inventory_quantity'] = 1;
					}
					if ($wishListItems[$index]['sale_price'] === false) {
						$wishListItems[$index]['sale_price'] = "Call";
					} else {
						$wishListItems[$index]['sale_price'] = number_format($wishListItems[$index]['sale_price'], 2);
					}
				}
				usort($wishListItems, array($this, "shoppingCartItemDescriptionSort"));
				$returnArray['wish_list_items'] = $wishListItems;
				ajaxResponse($returnArray);
				break;
			case "get_full_product_details":
				$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id'],
					"inactive = 0 and (expiration_date is null or expiration_date > current_date)" . ($GLOBALS['gUserRow']['administrator_flag'] ? "" : " and internal_use_only = 0"));
				if (empty($productId)) {
					echo jsonEncode(array("error_message" => "Product Not Found"));
					exit;
				}
				$returnArray['product_id'] = $productId;
				$productDetails = new ProductDetails($productId);
				$returnArray['content'] = $productDetails->getFullPage();
				$returnArray['page_title'] = $productDetails->getPageTitle();
				$linkName = getFieldFromId("link_name", "products", "product_id", $productId);
				$returnArray['link_url'] = "https://" . $_SERVER['HTTP_HOST'] . "/" . (empty($linkName) ? "product-details?id=" . $productId : "product/" . $linkName) . "#product_detail";
				ajaxResponse($returnArray);
				break;
			case "get_product_details":
				$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id'],
					"inactive = 0 and (expiration_date is null or expiration_date > current_date)" . ($GLOBALS['gUserRow']['administrator_flag'] ? "" : " and internal_use_only = 0"));
				if (empty($productId)) {
					echo jsonEncode(array("error_message" => "Product Not Found"));
					exit;
				}
				$returnArray['product_id'] = $productId;
				$productDetails = array();
				$removeFields = array("client_id", "base_cost", "version");
				$productSet = executeQuery("select *,(select description from product_manufacturers where product_manufacturer_id = products.product_manufacturer_id) manufacturer_name from products " .
					"left outer join product_data using (product_id) where product_id = ?", $productId);
				$productCatalog = new ProductCatalog();
				if (!$productDetails = getNextRow($productSet)) {
					echo jsonEncode(array("error_message" => "Product Not Found"));
					exit;
				}

				foreach ($removeFields as $fieldName) {
					unset($productDetails[$fieldName]);
				}

				$mapEnforced = false;
				$salePriceInfo = $productCatalog->getProductSalePrice($productDetails['product_id'], array("product_information" => $productDetails, "no_cache" => true));
				$originalSalePrice = $salePriceInfo['original_sale_price'];
				$salePrice = $salePriceInfo['sale_price'];
				$mapEnforced = $salePriceInfo['map_enforced'];
				$callPrice = $salePriceInfo['call_price'];
				if (empty($originalSalePrice)) {
					$originalSalePrice = $productDetails['list_price'];
				}
				if ($originalSalePrice <= $salePrice) {
					$originalSalePrice = "";
				}

				$productDetails['sale_price_array'] = $salePriceInfo;
				$credovaCredentials = getCredovaCredentials();
				$credovaUserName = $credovaCredentials['username'];
				$credovaPassword = $credovaCredentials['password'];
				$credovaTest = $credovaCredentials['test_environment'];
				$class3ProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "CLASS_3");
				$productTagLinkId = "";
				if (empty($class3ProductTagId)) {
					$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productId, "product_tag_id = ?", $class3ProductTagId);
				}
				if (empty($credovaUserName) || !empty($productTagLinkId)) {
					$productDetails['credova_financing'] = "";
				} else {
					$productDetails['credova_financing'] = "<p class='credova-button' data-amount='" . str_replace("$", "", str_replace(",", "", $salePrice)) . "' data-type='popup'></p>";
				}

				$mapPolicyId = getPreference("DEFAULT_MAP_POLICY_ID") ?: getFieldFromId("map_policy_id", "product_manufacturers", "product_manufacturer_id", $productDetails['product_manufacturer_id']);
				$mapPolicyCode = getFieldFromId("map_policy_code", "map_policies", "map_policy_id", $mapPolicyId);
				$ignoreMap = ($mapPolicyCode == "IGNORE");
				if (empty($ignoreMap)) {
					$ignoreMap = CustomField::getCustomFieldData($productDetails['product_id'], "IGNORE_MAP", "PRODUCTS");
				}
				$callForPriceText = $this->getFragment("CALL_FOR_PRICE");
				if (empty($callForPriceText)) {
					$callForPriceText = getLanguageText("Call for Price");
				}

				$showInStoreOnlyPrice = getPreference("SHOW_IN_STORE_ONLY_PRICE");
				if ($salePrice === false || ($productDetails['no_online_order'] && empty($showInStoreOnlyPrice))) {
					$productDetails['sale_price'] = $callForPriceText;
					$productDetails['hide_dollar'] = true;
					$productDetails['no_sale_price'] = true;
				} else {
					if ($salePrice > $productDetails['manufacturer_advertised_price'] || $ignoreMap) {
						$productDetails['manufacturer_advertised_price'] = "";
					}
					if (!empty($productDetails['manufacturer_advertised_price']) && $salePrice < $productDetails['manufacturer_advertised_price']) {
						$salePrice = $productDetails['manufacturer_advertised_price'];
					}
					$productDetails['sale_price'] = number_format($salePrice, 2);
					$productDetails['original_sale_price'] = (empty($originalSalePrice) ? "" : "$" . number_format($originalSalePrice, 2));
				}
				$productDetails['strict_map'] = ($mapEnforced ? "strict-map" : "");
				$productDetails['call_price'] = ($callPrice ? "call-price" : "");

				$productDetails['detailed_description'] = makeHtml($productDetails['detailed_description']);
				$productDetails['detailed_description_text'] = htmlText($productDetails['detailed_description']);
				$parameters = array();
				$defaultImageFilename = getImageFilenameFromCode("NO_PRODUCT_IMAGE");
				if (empty($defaultImageFilename) || $defaultImageFilename == "/images/empty.jpg") {
					$defaultImageFilename = getPreference("DEFAULT_PRODUCT_IMAGE");
				}
				if (!empty($defaultImageFilename)) {
					$parameters['default_image'] = $defaultImageFilename;
				}
				$productDetails['image_url'] = ProductCatalog::getProductImage($productDetails['product_id'], $parameters);
				foreach ($GLOBALS['gImageTypes'] as $imageTypeRow) {
					$parameters['image_type'] = strtolower($imageTypeRow['image_type_code']);
					$productDetails[strtolower($imageTypeRow['image_type_code']) . "_image_url"] = ProductCatalog::getProductImage($productDetails['product_id'], $parameters);
				}
				$specifications = array();
				$skipFields = array("product_data_id", "product_id", "version");
				$tableId = getReadFieldFromId("table_id", "tables", "table_name", "product_data");
				$resultSet = executeQuery("select description, column_name from table_columns join column_definitions using (column_definition_id) where table_id = ?", $tableId);
				$fieldDescriptions = array();
				while ($row = getNextRow($resultSet)) {
					$fieldDescriptions[$row['column_name']] = $row['description'];
				}
				$resultSet = executeQuery("select * from product_data where product_id = ?", $productId);
				$dataTable = new DataTable("product_data");
				$foreignKeyList = $dataTable->getForeignKeyList();
				while ($row = getNextRow($resultSet)) {
					foreach ($row as $fieldName => $fieldValue) {
						if (in_array($fieldName, $skipFields) || empty($fieldValue)) {
							continue;
						}
						$fieldDescription = $fieldDescriptions[$fieldName];
						if (array_key_exists("product_data." . $fieldName, $foreignKeyList)) {
							$thisFieldValue = "";
							foreach ($foreignKeyList["product_data." . $fieldName]['description'] as $thisDescriptionField) {
								$descriptionFieldValue = getFieldFromId($thisDescriptionField, $foreignKeyList["product_data." . $fieldName]['referenced_table_name'],
									$foreignKeyList["product_data." . $fieldName]['referenced_column_name'], $fieldValue);
								$thisFieldValue .= $descriptionFieldValue;
							}
							$fieldValue = $thisFieldValue;
						}
						$specifications[] = array("field_description" => $fieldDescription, "field_value" => $fieldValue);
					}
				}
				$resultSet = executeQuery("select * from product_facet_values join product_facets using (product_facet_id) join product_facet_options using (product_facet_option_id) where product_id = ? and " .
					"inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $productId);
				while ($row = getNextRow($resultSet)) {
					if (empty($row['facet_value']) || $row['facet_value'] == "N/A") {
						continue;
					}
					$specifications[] = array("field_description" => $row['description'], "field_value" => $row['facet_value']);
				}
				$productDetails['specifications'] = $specifications;
				$productDetails['product_detail_link'] = (empty($productDetails['link_name']) ? "product-details?id=" . $productDetails['product_id'] : "/product/" . $productDetails['link_name']);

				$productDetails['product_restrictions'] = "";
				if ($GLOBALS['gLoggedIn']) {
					$ignoreProductRestrictions = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "IGNORE_PRODUCT_RESTRICTIONS");
				} else {
					$ignoreProductRestrictions = false;
				}
				if (empty($ignoreProductRestrictions)) {
					$resultSet = executeQuery("select state,postal_code,country_id from product_restrictions where product_id = ? union " .
						"select state,postal_code,country_id from product_category_restrictions where product_category_id in (select product_category_id from product_category_links where product_id = ?) union " .
						"select state,postal_code,country_id from product_department_restrictions where (product_department_id in (select product_department_id from product_category_departments where " .
						"product_category_id in (select product_category_id from product_category_links where product_id = ?)) or product_department_id in (select product_department_id from " .
						"product_category_group_departments where product_category_group_id in (select product_category_group_id from product_category_group_links where product_category_id in " .
						"(select product_category_id from product_category_links where product_id = ?))))", $productDetails['product_id'], $productDetails['product_id'], $productDetails['product_id'], $productDetails['product_id']);
					$usedRestrictions = array();
					$stateArray = getStateArray();
					while ($row = getNextRow($resultSet)) {
						if (in_array(jsonEncode($row), $usedRestrictions)) {
							continue;
						}
						if (empty($row['state']) && empty($row['postal_code']) && empty($row['country_id'])) {
							continue;
						}
						if (empty($row['state']) && empty($row['postal_code']) && $row['country_id'] == 1000) {
							continue;
						}
						$ignoreStateRestriction = CustomField::getCustomFieldData($productDetails['product_id'], "IGNORE_RESTRICTIONS_" . strtoupper($row['state']), "PRODUCTS");
						if (!empty($ignoreStateRestriction)) {
							continue;
						}
						$usedRestrictions[] = jsonEncode($row);
						$restrictions = "";
						if (!empty($row['state'])) {
							$state = $stateArray[$row['state']];
							if (empty($state)) {
								$state = $row['state'];
							}
							$restrictions .= (empty($restrictions) ? "" : ", ") . $state;
						}
						if (!empty($row['postal_code'])) {
							$restrictions .= (empty($restrictions) ? "" : ", ") . $row['postal_code'];
						}
						if (!empty($row['country_id']) && $row['country_id'] != 1000) {
							$restrictions .= (empty($restrictions) ? "" : ", ") . getFieldFromId("country_name", "countries", "country_id", $row['country_id']);
						}
						if (empty($restrictions)) {
							continue;
						}
						$productDetails['product_restrictions'] .= (empty($productDetails['product_restrictions']) ? "" : "; ") . $restrictions;
					}
				}
				if (!empty($productDetails['product_restrictions'])) {
					$productDetails['product_restrictions'] = "<p>Sale not allowed in " . $productDetails['product_restrictions'] . "</p>";
				}
				$productDetails['out_of_stock'] = "";
				$inventoryCounts = $productCatalog->getInventoryCounts(true, array($productId));
				$productDetails['inventory_count'] = $inventoryCounts[$productId];
				$productDetails['out_of_stock'] = ($inventoryCounts[$productId] == 0);
				if (!empty($productDetails['no_online_order'])) {
					$productDetails['availability'] = "";
					$productDetails['availability_class'] = "hidden";
				} else {
					if (empty($productDetails['non_inventory_item']) && empty($productDetails['inventory_count']) && empty($neverOutOfStock)) {
						$productDetails['availability'] = $this->getFragment("retail_store_out_of_stock");
						if (empty($productDetails['availability'])) {
							$productDetails['availability'] = "<span class='fa fa-times-circle'></span>Out of Stock";
						}
						$productDetails['availability_class'] = "red-text";
						$productDetails['out_of_stock'] = "out-of-stock";
					} else {
						$productDetails['availability'] = $this->getFragment("retail_store_in_stock");
						if (empty($productDetails['availability'])) {
							$productDetails['availability'] = "<span class='fa fa-check-circle'></span>In Stock";
						}
						$productDetails['availability_class'] = "";
					}
				}
				$productDetails['other_classes'] = "";
				if (!$mapEnforced && !empty($productDetails['manufacturer_advertised_price']) && $productDetails['manufacturer_advertised_price'] > $productDetails['sale_price'] && empty($ignoreMap)) {
					$productDetails['sale_price'] = $productDetails['manufacturer_advertised_price'];
					$productDetails['best_price_message'] = "<p class='best-price-message'>Add to cart for best price</p>";
					$productDetails['other_classes'] = "map-priced-product";
				} else {
					$productDetails['best_price_message'] = "";
				}
				$productDetails['location_availability'] = ProductCatalog::getProductAvailabilityText($productDetails);
				if ($productDetails['location_availability'] === false) {
					$productDetails['location_availability'] = "";
				}

				$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productDetails['product_id'], "product_tag_id in (select product_tag_id from product_tags where product_tag_code = 'FINANCING_AVAILABLE')");
				if (!empty($productTagLinkId)) {
					$salePriceInfo = $productCatalog->getProductSalePrice($productDetails['product_id'], array("product_information" => $productDetails, "ignore_map" => true));
					$salePrice = $salePriceInfo['sale_price'];
					if (empty($salePrice)) {
						$salePrice = 0;
					}
					$productDetails['finance_amount'] = $salePrice;
				}

				$returnArray['product_details'] = $productDetails;
				ajaxResponse($returnArray);
				break;
			case "get_ffl_dealers":
				if (empty($_POST)) {
					$_POST = $_GET;
				}
				$_POST = array_merge($_POST, $_GET);
				$searchParameters = array();
				$whereExpressions = array();
				if (empty($_POST['allow_not_approved'])) {
					$searchParameters['approved'] = "1";
				}
				if (strlen($_POST['search_text']) == 5 && is_numeric($_POST['search_text'])) {
					$_POST['postal_code'] = $_POST['search_text'];
					$_POST['search_text'] = "";
				}
				if (!empty($_POST['has_merchant_account'])) {
					$whereExpressions[] = "federal_firearms_licensee_id in (select federal_firearms_licensee_id from ffl_merchant_accounts)";
				}
				if (!empty($_POST['has_location'])) {
					$whereExpressions[] = "federal_firearms_licensee_id in (select federal_firearms_licensee_id from ffl_locations where location_id in (select location_id from locations where inactive = 0 and internal_use_only = 0))";
				}
				if (empty($_POST['allow_expired'])) {
					$whereExpressions[] = "expiration_date is not null";
					$whereExpressions[] = "expiration_date >= date_sub(current_date,interval 45 day)";
				}
				if (!empty($_POST['preferred_only'])) {
					$whereExpressions[] = "(preferred = 1 or federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensee_details where client_id = " . $GLOBALS['gClientId'] . " and preferred = 1))";
				}
				if (!empty($_POST['have_price_structure_only'])) {
					if ($GLOBALS['gPrimaryDatabase']->tableExists("dealer_price_structures")) {
						$whereExpressions[] = "federal_firearms_licensee_id in (select federal_firearms_licensee_id from user_ffls where user_id in (select creator_user_id from dealer_price_structures where inactive = 0 and internal_use_only = 0))";
					}
				}
				if (!empty($_POST['have_license_only'])) {
					$whereExpressions[] = "(file_id is not null or federal_firearms_licensee_id in (select federal_firearms_licensee_id from federal_firearms_licensee_details where client_id = " . $GLOBALS['gClientId'] . " and file_id is not null))";
				}
				if (!empty($_POST['default_ffl_dealer'])) {
					$defaultFFLDealer = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_FFL_DEALER");
					$defaultFFLDealer = (new FFL(array("federal_firearms_licensee_id" => $defaultFFLDealer, "only_if_valid" => true)))->getFieldData("federal_firearms_licensee_id");
					if (!empty($defaultFFLDealer)) {
						$searchParameters['federal_firearms_licensee_id'] = $defaultFFLDealer;
					}
					$_POST['federal_firearms_licensee_id'] = $defaultFFLDealer;
				}
				if (!empty($_POST['default_location'])) {
					$defaultLocationId = "";
					if ($GLOBALS['gLoggedIn']) {
						$defaultLocationId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_LOCATION_ID");
					}
					if (empty($defaultLocationId)) {
						$defaultLocationId = $_COOKIE['default_location_id'];
					}
					$defaultLocationId = getFieldFromId("location_id", "locations", "location_id", $defaultLocationId, "inactive = 0 and internal_use_only = 0");
					if (empty($defaultLocationId)) {
						ajaxResponse($returnArray);
						break;
					} else {
						$_POST['location_id'] = $defaultLocationId;
					}
				}
				if (!empty($_POST['location_id'])) {
					$whereExpressions[] = "federal_firearms_licensee_id in (select federal_firearms_licensee_id from ffl_locations where location_id = " . makeNumberParameter($_POST['location_id']) . ")";
				}
				if (empty($_POST['location_id']) && empty($_POST['federal_firearms_licensee_id'])) {
					if (!empty($_POST['search_text'])) {
						if (empty($_POST['postal_code']) && strlen($_POST['search_text']) == 5 && is_numeric($_POST['search_text'])) {
							$searchParameters['postal_code'] = $_POST['search_text'];
						} else {
							$searchExpression = $_POST['search_text'] . "%";
							$searchParameters['business_name'] = $searchExpression;
							$searchParameters['licensee_name'] = $searchExpression;
							$searchParameters['license_number'] = $searchExpression;
							$searchParameters['address_1'] = $searchExpression;
							$searchParameters['postal_code'] = $searchExpression;
						}
					} elseif (empty($_POST['postal_code'])) {
						$_POST['postal_code'] = $GLOBALS['gUserRow']['postal_code'];
						if (empty($_POST['postal_code'])) {
							$_POST['postal_code'] = $_POST['billing_postal_code'];
						}
						if (empty($_POST['postal_code'])) {
							$returnArray['error_message'] = "Add shipping address first";
							ajaxResponse($returnArray);
							break;
						}
					}
					$postalCodeArray = array();
					if (!empty($_POST['postal_code']) && is_scalar($_POST['postal_code']) && $_POST['postal_code'] != -1) {
						$postalCode = substr($_POST['postal_code'], 0, 5);
						$resultSet = executeQuery("select * from postal_codes where postal_code = ?", $postalCode);
						$row = getNextRow($resultSet);
						freeResult($resultSet);
						if (empty($row) || empty($row['state'])) {
							ajaxResponse(array("ffl_dealers" => array()));
							break;
						}
						$searchParameters['state'] = $row['state'];
						$postalCodeArray = getZipCodesInRadius($row['latitude'], $row['longitude'], empty($_POST['radius']) ? 50 : $_POST['radius']);
						if (empty($postalCodeArray)) {
							ajaxResponse(array("ffl_dealers" => array()));
							break;
						}
						$postalCodeStatement = "";
						foreach ($postalCodeArray as $thisPostalCode => $distance) {
							if (!empty($thisPostalCode)) {
								$postalCodeStatement .= (empty($postalCodeStatement) ? "" : ",") . makeParameter($thisPostalCode);
							}
						}
						if (!empty($postalCodeStatement)) {
							$postalCodeStatement = "substring(contacts.postal_code,1,5) in (" . $postalCodeStatement . ")";
						}
						$whereExpressions[] = $postalCodeStatement;
					} else {
						$postalCode = null;
					}
				}
				$originGeopoint = getPointForZipCode($_POST['postal_code']);
				$fflDealers = array();
				$fflChoiceElement = ProductCatalog::getFFLChoiceElement(!empty($_POST['allow_expired']));

				if (!empty($_POST['product_manufacturer_codes'])) {
					$productManufacturerCodes = explode(",", $_POST['product_manufacturer_codes']);
					$productManufacturerIds = array();
					foreach ($productManufacturerCodes as $thisCode) {
						$productManufacturerId = getFieldFromId("product_manufacturer_id", "product_manufacturers", "product_manufacturer_code", $thisCode);
						if (!empty($productManufacturerId)) {
							$productManufacturerIds[] = $productManufacturerId;
						}
					}
					if (!empty($productManufacturerIds)) {
						$whereExpressions[] = "federal_firearms_licensee_id in (select federal_firearms_licensee_id from ffl_product_manufacturers where product_manufacturer_id in (" . implode(",", $productManufacturerIds) . "))";
					}
				}
				if (!empty($_POST['product_department_codes'])) {
					$productDepartmentCodes = explode(",", $_POST['product_department_codes']);
					$productDepartmentIds = array();
					foreach ($productDepartmentCodes as $thisCode) {
						$productDepartmentId = getFieldFromId("product_department_id", "product_departments", "product_department_code", $thisCode);
						if (!empty($productDepartmentId)) {
							$productDepartmentIds[] = $productDepartmentId;
						}
					}
					if (!empty($productDepartmentIds)) {
						$whereExpressions[] = "federal_firearms_licensee_id in (select federal_firearms_licensee_id from ffl_product_departments where product_department_id in (" . implode(",", $productDepartmentIds) . "))";
					}
				}
				if (!empty($_POST['open_now'])) {
					$whereExpressions[] = "federal_firearms_licensee_id in (select federal_firearms_licensee_id from ffl_availability where weekday = " . date("w") . " and hour between " . date("G") . " and " . (date("G") + 1) . ")";
				}

				if (!empty($_POST['weekdays']) || $_POST['weekdays'] == "0") {
					$weekdays = array();
					foreach (explode(",", $_POST['weekdays']) as $thisWeekday) {
						if ($thisWeekday >= 0 && $thisWeekday <= 6 && !in_array($thisWeekday, $weekdays)) {
							$weekdays[] = $thisWeekday;
						}
					}
					if (!empty($weekdays)) {
						$whereExpressions[] = "federal_firearms_licensee_id in (select federal_firearms_licensee_id from ffl_availability where weekday in (" . implode(",", $weekdays) . "))";
					}
				}

				if (!empty($_POST['has_gun_range'])) {
					$customFieldId = CustomField::getCustomFieldIdFromCode("HAS_GUN_RANGE", "FFL");
					if (!empty($customFieldId)) {
						$whereExpressions[] = "federal_firearms_licensee_id in (select primary_identifier from custom_field_data where custom_field_id = " . $customFieldId . " and text_data in ('yes','gun_range'))";
					}
				}

				if (!empty($_POST['has_active_subscription'])) {
					$whereExpressions[] = "federal_firearms_licensees.contact_id in (select contact_id from contact_subscriptions where " .
						"start_date <= current_date and (expiration_date is null or expiration_date > current_date) and inactive = 0 and customer_paused = 0)";
				}

				if (!empty($_POST['has_company'])) {
					$whereExpressions[] = "federal_firearms_licensees.contact_id in (select contact_id from companies)";
				}

				$fflDealerRows = FFL::getFFLRecords($searchParameters, $whereExpressions);
				foreach ($fflDealerRows as $index => $row) {
					$subscriptionResultSet = executeQuery("select subscription_code, sort_order from subscriptions where subscription_id in (select subscription_id from contact_subscriptions where " .
						"start_date <= current_date and (expiration_date is null or expiration_date > current_date) and contact_id = ? and inactive = 0 and customer_paused = 0) order by sort_order", $row['contact_id']);
					if ($subscriptionRow = getNextRow($subscriptionResultSet)) {
						$fflDealerRows[$index]['subscription_code'] = $subscriptionRow['subscription_code'];
						$fflDealerRows[$index]['subscription_sort_order'] = $subscriptionRow['sort_order'];
					}
					freeResult($subscriptionResultSet);

					if (!empty($row['postal_code'])) {
						$thisPostalCode = substr($row['postal_code'], 0, 5);
						if ($thisPostalCode === $postalCode) {
							$fflDealerRows[$index]['distance'] = 0;
						} else {
							$distance = ($postalCodeArray[$thisPostalCode] ?? null);
							$fflDealerRows[$index]['distance'] = ($distance === null ? "" : round($distance));
						}
					}
				}

				if (!empty($_POST['sort_by_subscription'])) {
					usort($fflDealerRows, function ($firstDealer, $secondDealer) {
						// No subscriptions last on the list
						$firstDealerSortOrder = empty($firstDealer['subscription_sort_order']) ? PHP_INT_MAX : $firstDealer['subscription_sort_order'];
						$secondDealerSortOrder = empty($secondDealer['subscription_sort_order']) ? PHP_INT_MAX : $secondDealer['subscription_sort_order'];

						if ($firstDealerSortOrder == $secondDealerSortOrder) {
							return ($firstDealer['distance'] > $secondDealer['distance']) ? 1 : -1;
						}
						return $firstDealerSortOrder > $secondDealerSortOrder;
					});
				}

				$offset = empty($_POST['offset']) ? 0 : $_POST['offset'];
				$limit = empty($_POST['limit']) ? null : $_POST['limit'];

				$returnArray['total_dealers_count'] = count($fflDealerRows);
				$fflDealerRows = array_slice($fflDealerRows, $offset, $limit);

				$inventoryCounts = array();
				$productCatalog = new ProductCatalog();
				if (!empty($_POST['product_id'])) {
					$productId = getFieldFromId("product_id", "products", "product_id", $_POST['product_id']);
					if (!empty($productId)) {
						$inventoryCounts = $productCatalog->getInventoryCounts(false, array($productId))[$productId];
						$returnArray['inventory_quantity_distributor'] = $inventoryCounts['distributor'];
					}
				}

				foreach ($fflDealerRows as $row) {
					$fflDealer = $row;
					$fflId = $row['federal_firearms_licensee_id'];
					if (!empty($_POST['update_coordinates']) && (empty($row['latitude']) || empty($row['longitude']))) {
						$fflAddress = array("address_1" => $row['address_1'], "address_2" => $row['address_2'], "city" => $row['city'], "state" => $row['state'], "postal_code" => $row['postal_code']);
						$geoCode = getAddressGeocode($fflAddress);
						if (!empty($geoCode['latitude']) && !empty($geoCode['longitude'])) {
							$row['latitude'] = $geoCode['latitude'];
							$row['longitude'] = $geoCode['longitude'];
							executeQuery("update contacts set latitude = ?, longitude = ? where contact_id = ?", $geoCode['latitude'], $geoCode['longitude'], $row['contact_id']);
						}
					}
					$fflDealer['geocode'] = array('latitude' => $row['latitude'], 'longitude' => $row['longitude']);
					if (!empty($originGeopoint) && !empty($fflDealer['geocode'])) {
						$distance = calculateDistance($originGeopoint, $fflDealer['geocode']);
						if (!empty($distance)) {
							$fflDealer['distance'] = round($distance, 1);
						}
					}

					if (empty($_POST['exclude_custom_fields'])) {
						$fflDealer['custom_fields'] = array();
						$customFields = CustomField::getCustomFields("FFL");
						foreach ($customFields as $thisCustomField) {
							$customFieldRow = getRowFromId("custom_fields", "custom_field_id", $thisCustomField['custom_field_id']);
							$fflDealer['custom_fields'][] = array("custom_field_code" => $customFieldRow['custom_field_code'], "description" => $customFieldRow['description'], "form_label" => $customFieldRow['form_label'],
								"custom_field_data" => CustomField::getCustomFieldData($fflId, $customFieldRow['custom_field_code'], "FFL"));
							$fflDealer['custom_field:' . strtolower($customFieldRow['custom_field_code'])] = CustomField::getCustomFieldData($fflId, $customFieldRow['custom_field_code'], "FFL");
						}
					}

					$fflDealer['have_license'] = (empty($row['file_id']) ? "" : 1);
					$fflDealer['preferred'] = $row['preferred'];
					$fflDealer['expiration_date_notice'] = "";
					if (!empty($_POST['allow_expired'])) {
						if (!empty($row['expiration_date']) && $row['expiration_date'] < date("Y-m-d", strtotime("+30 days"))) {
							$fflDealer['expiration_date_notice'] = "<p class='" . ($row['expiration_date'] < date("Y-m-d", strtotime("+30 days")) ? "red-text" : "") . "'>Expires: " .
								date("m/d/Y", strtotime($row['expiration_date'])) . "</p>";
						}
					}
					$fflDealer['phone_number'] = Contact::getContactPhoneNumber($row['contact_id']);
					if (empty($_POST['json_mode'])) {
						$displayName = $fflChoiceElement;
						$displayName = PlaceHolders::massageContent($displayName, array_merge($row, $fflDealer));
						$fflDealer['display_name'] = $displayName;
						$fflDealer['restricted'] = false;
					} else {
						$fflDealer['merchant_account_id'] = getFieldFromId("merchant_account_id", "ffl_merchant_accounts", "federal_firearms_licensee_id", $fflId, "merchant_account_id in (select merchant_account_id from merchant_accounts where inactive = 0)");
						$fflDealer['availability'] = array();
						$fflAvailabilityResultSet = executeQuery("select weekday,hour from ffl_availability where federal_firearms_licensee_id = ?", $fflId);
						while ($fflAvailabilityRow = getNextRow($fflAvailabilityResultSet)) {
							$fflDealer['availability'][] = $fflAvailabilityRow;
						}
						freeResult($fflAvailabilityResultSet);
						if (!empty($row['image_id'])) {
							$fflImage = array();
							$fflImage['image_filename'] = getImageFilename($row['image_id']);
							$fflImage['description'] = getFieldFromId("description", "images", "image_id", $row['image_id']);
							$fflDealer['primary_image'] = $fflImage;
						}
						$fflDealer['images'] = array();
						$fflImagesResultSet = executeQuery("select description,image_id from ffl_images where federal_firearms_licensee_id = ?", $fflId);
						while ($fflImageRow = getNextRow($fflImagesResultSet)) {
							$fflImage = array();
							$fflImage['image_filename'] = getImageFilename($fflImageRow['image_id'], array("use_cdn" => true));
							$fflImage['description'] = $fflImageRow['description'];
							$fflDealer['images'][] = $fflImage;
						}
						freeResult($fflImagesResultSet);
						$fflDealer['locations'] = array();
						$fflLocationsResultSet = executeQuery("select ffl_location_id, location_id, location_code, description from ffl_locations join locations using (location_id) where federal_firearms_licensee_id = ?", $fflId);
						while ($fflLocationsRow = getNextRow($fflLocationsResultSet)) {
							$locationId = $fflLocationsRow['location_id'];
							$fflLocationsRow['inventory_count'] = empty($inventoryCounts[$locationId]) ? 0 : $inventoryCounts[$locationId];
							$fflDealer['locations'][] = $fflLocationsRow;
						}
						freeResult($fflLocationsResultSet);
						$fflDealer['product_manufacturers'] = array();
						$fflProductManufacturersResultSet = executeQuery("select product_manufacturers.product_manufacturer_id, product_manufacturer_code, " .
							"product_manufacturer_tag_code, product_manufacturers.description from ffl_product_manufacturers join product_manufacturers " .
							"using (product_manufacturer_id) join product_manufacturer_tags using (product_manufacturer_tag_id) where federal_firearms_licensee_id = ?", $fflId);
						while ($fflProductManufacturerRow = getNextRow($fflProductManufacturersResultSet)) {
							$fflDealer['product_manufacturers'][] = $fflProductManufacturerRow;
						}
						freeResult($fflProductManufacturersResultSet);
					}
					if (!empty($_POST['product_id'])) {
                        if (function_exists("getFFLDealerPrice")) {
                            $fflDealer['product_price'] = getFFLDealerPrice($_POST['product_id'], $fflId);
                        }
                        $taxCalculationParameters = array();
                        $productItem = array("product_id" => $_POST['product_id'], "sale_price" => $fflDealer['product_price'], "quantity" => 1);
                        $taxCalculationParameters['product_items'] = array($productItem);
                        $taxCalculationParameters = array_merge($taxCalculationParameters, $fflDealer);
                        $fflDealer['estimated_tax'] = Tax::estimateTax($taxCalculationParameters);
                    }
                    $locationSet = executeQuery("select * from shipping_methods join locations using (location_id) where pickup = 1 and shipping_methods.inactive = 0 and shipping_methods.internal_use_only = 0 and " .
                        "locations.inactive = 0 and locations.internal_use_only = 0 and location_id in (select location_id from ffl_locations where federal_firearms_licensee_id = ?)", $fflId);
                    if ($locationRow = getNextRow($locationSet)) {
                        $fflDealer['shipping_method_id'] = $locationRow['shipping_method_id'];
                        $parameters = array("shipping_method_id" => $fflDealer['shipping_method_id'], "product_id" => $productId, "sale_price" => $fflDealer['product_price']);
                        $shippingRate = Shipping::getDealerShippingCharge($parameters);
                        $fflDealer['shipping_charge'] = $shippingRate['shipping_charge']['rate'];
                    }
					$fflDealers[] = $fflDealer;
				}
				if (empty($_POST['json_mode'])) {
					usort($fflDealers, array($this, "fflDealerSort"));
					if (empty($_POST['shopping_cart_code'])) {
						$_POST['shopping_cart_code'] = "RETAIL";
					}
					$shoppingCart = ShoppingCart::getShoppingCart($_POST['shopping_cart_code']);
					$shoppingCartItems = $shoppingCart->getShoppingCartItems(array("reset_sale_price" => true));
					foreach ($fflDealers as $index => $fflDealerInfo) {
						$restricted = false;

						if (!empty($fflDealerInfo['restricted_product_ids'])) {
							$productIdArray = array();
							foreach (array_filter(explode(",", $fflDealerInfo['restricted_product_ids'])) as $productId) {
								$productIdArray[$productId] = $productId;
							}
							if (!empty($productIdArray)) {
								foreach ($shoppingCartItems as $thisItem) {
									if (array_key_exists($thisItem['product_id'], $productIdArray)) {
										$restricted = true;
										break;
									}
								}
								if ($restricted) {
									$fflDealers[$index]['restricted'] = true;
									continue;
								}
							}
						}
						if (!empty($fflDealerInfo['restricted_product_category_ids'])) {
							$productCategoryIdArray = array();
							foreach (array_filter(explode(",", $fflDealerInfo['restricted_product_category_ids'])) as $productCategoryId) {
								$productCategoryIdArray[$productCategoryId] = $productCategoryId;
							}
							if (!empty($productCategoryIdArray)) {
								foreach ($shoppingCartItems as $thisItem) {
									$productCategoryLinkId = getFieldFromId("product_category_link_id", "product_category_links", "product_id", $thisItem['product_id'], "product_category_id in (" . implode(",", $productCategoryIdArray) . ")");
									if (!empty($productCategoryLinkId)) {
										$restricted = true;
										break;
									}
								}
								if ($restricted) {
									$fflDealers[$index]['restricted'] = true;
									continue;
								}
							}
						}
						if (!empty($fflDealerInfo['restricted_product_manufacturer_ids'])) {
							$productManufacturerIdArray = array();
							foreach (array_filter(explode(",", $fflDealerInfo['restricted_product_manufacturer_ids'])) as $productManufacturerId) {
								$productManufacturerIdArray[$productManufacturerId] = $productManufacturerId;
							}
							if (!empty($productManufacturerIdArray)) {
								foreach ($shoppingCartItems as $thisItem) {
									$productId = getFieldFromId("product_id", "products", "product_id", $thisItem['product_id'], "product_manufacturer_id in (" . implode(",", $productManufacturerIdArray) . ")");
									if (!empty($productId)) {
										$restricted = true;
										break;
									}
								}
								if ($restricted) {
									$fflDealers[$index]['restricted'] = true;
									continue;
								}
							}
						}
					}
				}
				$returnArray['ffl_dealers'] = $fflDealers;
				ajaxResponse($returnArray);
				break;
			case "get_media_series":
				$mediaSeriesId = $_GET['media_series_id'];
				if (!empty($mediaSeriesId)) {
					$mediaResultSet = executeQuery("select media.*, media_services.link_url media_services_link_url, media_services.media_service_code"
						. " from media join media_services using (media_service_id)"
						. " where media.client_id = ? and media.media_series_id = ? and media.inactive = 0"
						. ($GLOBALS['gInternalConnection'] ? "" : " and media.internal_use_only = 0")
						. " order by date_created desc, sort_order, description",
						$GLOBALS['gClientId'], $mediaSeriesId);
					while ($mediaRow = getNextRow($mediaResultSet)) {
						$mediaRow['image_filename'] = getImageFilename($mediaRow['image_id'], array("use_cdn" => true));
						$mediaRow['date_created'] = date("m/d/Y", strtotime($mediaRow['date_created']));
						$returnArray['media'][] = $mediaRow;
					}
					$mediaSeriesRow = getRowFromId("media_series", "media_series_id", $mediaSeriesId);
					$returnArray['description'] = htmlText($mediaSeriesRow['description']);
					$returnArray['detailed_description'] = htmlText($mediaSeriesRow['detailed_description']);
				}
				ajaxResponse($returnArray);
				break;
		}
		ajaxResponse($returnArray);
		exit;
	}

	function cleanPostData() {
		$excludeFields = array("account_number", "expiration_", "cvv", "routing", "bank_account", "password", "g-recaptcha-response");
		foreach ($_POST as $fieldName => $fieldData) {
			$includeField = true;
			foreach ($excludeFields as $thisFieldName) {
				if (substr($fieldName, 0, strlen($thisFieldName)) == $thisFieldName) {
					$includeField = false;
					break;
				}
			}
			if ($includeField) {
				$logArray[$fieldName] = $fieldData;
			}
		}
		return $logArray;
	}

	function cancelCredovaLoan($credovaLoanId, $publicIdentifier, $authenticationToken, $contactId, $reason = "") {
		if (empty($credovaLoanId) && empty($publicIdentifier) && empty($authenticationToken)) {
			return;
		}
		$customerName = getDisplayName($contactId);
		$programLogId = addProgramLog("Credova Return Request: " . $customerName . ", " . $publicIdentifier . ", " . $authenticationToken . "\n\n" . $reason);
		$credovaCredentials = getCredovaCredentials();
		$credovaUserName = $credovaCredentials['username'];
		$credovaPassword = $credovaCredentials['password'];
		$credovaTest = $credovaCredentials['test_environment'];

		if (!empty($publicIdentifier) && !empty($authenticationToken)) {
			$headers = array(
				"Content-Type: application/json",
				"Authorization: Bearer " . $authenticationToken
			);
			$postFields = new stdClass();
			if (!empty($reason)) {
				$postFields->reason = $reason;
				$postFields->returnType = 1; // redraft; this allows the customer to sign again without having to start over.
			}
			$postFields->returnReasonPublicId = getPreference("CREDOVA_RETURN_REASON_PUBLIC_ID") ?: '990e4a26-0661-45de-b5d7-7dbc9a46bd29'; // order generation failed
			$postFields = json_encode($postFields); // Must be encoded as object, not array

			$ch = curl_init();

			curl_setopt($ch, CURLOPT_URL, "https://" . ($credovaTest ? "sandbox-" : "") . "lending-api.credova.com/v2/applications/" . urlencode($publicIdentifier) . "/requestreturn");
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POST, TRUE);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);

			$response = curl_exec($ch);
			curl_close($ch);
			addProgramLog("\nRequest: " . $postFields . "\nResponse: " . $response, $programLogId);
			sendEmail(array("subject" => "Credova Loan cancellation", "body" => "A cancellation request for the loan for " . $customerName . " was sent to Credova for the following reason: " . $reason
				. "\n\nIMPORTANT: The Credova API does not indicate whether the loan was successfully cancelled. Follow up with Credova to verify the status of the loan.",
				"notification_code" => "RETAIL_STORE_ORDER_NOTIFICATION"));
		}
	}

	function fflDealerSort($a, $b) {
		if ($a['preferred'] == $b['preferred']) {
			if ($a['distance'] == $b['distance']) {
				return 0;
			}
			return ($a['distance'] > $b['distance'] ? 1 : -1);
		}
		return ($a['preferred'] > $b['preferred'] ? -1 : 1);
	}

	private function rollbackOrder($parameters) {
		if (!empty($parameters['e_commerce'])) {
			$failedOrderAmounts = getCachedData("failed_order_amounts", "", true);
			if (!is_array($failedOrderAmounts)) {
				$failedOrderAmounts = array();
			}
			$failedOrderAmounts[] = array("amount" => $_GET['cart_total'], "time" => time(), "domain_name" => $_SERVER['HTTP_HOST']);
			setCachedData("failed_order_amounts", "", $failedOrderAmounts, 1, true);
		}
		if (!is_array($parameters)) {
			$parameters = array("contact_id" => $parameters);
		}
		$contactId = $parameters['contact_id'];
		$createdMerchantAccounts = $parameters['created_merchant_accounts'];
		$chargedTransactionIdentifiers = $parameters['charged_transaction_identifiers'];
		$eCommerce = $parameters['e_commerce'];
		$achECommerce = $parameters['ach_e_commerce'];
		$donationsECommerce = $parameters['donation_e_commerce'];
		$merchantIdentifier = $parameters['merchant_identifier'];
		$achMerchantIdentifier = $parameters['ach_merchant_identifier'];
		$errorMessage = $parameters['error_message'];
		$orderId = $parameters['order_id'];

		$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
		if (is_array($createdMerchantAccounts)) {
			foreach ($createdMerchantAccounts as $customerPaymentProfileInfo) {
				if ($customerPaymentProfileInfo['ach']) {
					$achECommerce->deleteCustomerPaymentProfile(array("merchant_identifier" => $achMerchantIdentifier, "account_token" => $customerPaymentProfileInfo['customer_payment_profile_id']));
				} else {
					$eCommerce->deleteCustomerPaymentProfile(array("merchant_identifier" => $merchantIdentifier, "account_token" => $customerPaymentProfileInfo['customer_payment_profile_id']));
				}
			}
		}
		if (is_array($chargedTransactionIdentifiers)) {
			foreach ($chargedTransactionIdentifiers as $transactionIdentifierInfo) {
				if ($transactionIdentifierInfo['ach']) {
					$achECommerce->voidCharge(array("transaction_identifier" => $transactionIdentifierInfo['transaction_identifier']));
				} elseif ($transactionIdentifierInfo['donation']) {
					$donationsECommerce->voidCharge(array("transaction_identifier" => $transactionIdentifierInfo['transaction_identifier']));
				} else {
					$eCommerce->voidCharge(array("transaction_identifier" => $transactionIdentifierInfo['transaction_identifier']));
				}
			}
		}
		$this->cancelCredovaLoan("", $_POST['public_identifier'], $_POST['authentication_token'], $contactId, $errorMessage);
		if (!empty($orderId)) {
			$updateSet = executeQuery("update orders set deleted = 1 where order_id = ?", $orderId);
			if ($updateSet['affected_rows'] > 0) {
				$orderNoteUserId = $GLOBALS['gUserId'];
				if (empty($orderNoteUserId)) {
					$orderNoteUserId = getFieldFromId("user_id", "users", "full_client_access", "1", "superuser_flag = 0");
				}
				if (!empty($_POST['order_notes_content'])) {
					executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $orderNoteUserId, "Order deleted because payment failed and rollback didn't work");
				}
			}
		}
	}
}

$pageObject = new RetailStoreControllerPage();
$pageObject->displayPage();
