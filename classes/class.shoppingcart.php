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

require_once __DIR__ . "/easypost/lib/easypost.php";

class ShoppingCart {

	private $iShoppingCartItemCache = array();
	private $iShoppingCartId = "";
	private $iShoppingCart = array();
	private $iShoppingCartItems = array();
	private $iErrorMessage = "";
	private $iPromotionId = "";
	private $iContactTypeId = "";
	private $iShippingCalculationLog = false;
	private $iProductTypes = array();
	private $iProductShippingMethods = array();
	private $iShippingMethodCategories = array();
	private $iShippingChargeProductInventories = array();
	private $iAlwaysAddProducts = false;
	private $iIsOneTimeUsePromotion = false;

	/**
	 * Construct - This is private intentionally. Create the shopping cart object with the static functions
	 */
	private function __construct() {
	}

	/* Shopping carts have a code that indicates which cart it is. The convention used is determined by the client. Some possible options are:
		- code of 'CURRENT' indicates the current, active cart for a user. All other carts have no code and are saved carts
		- code of 'current' for the active cart, code of 'future' for future orders

	Ways to get a shopping cart
		- for the logged in user for themselves, by shopping cart code
		- for the anonymous user for themselves. Anonymous carts will have no cart code and are saved by ID to the users cookies
		- for the logged in user for another contact ID, if the logged in user is an administrator
		- by Shopping Cart ID. Must belong to the logged in user, unless the logged in User is an administrator and the contact is not empty or the cart is anonymous
		- by Shopping Cart Code. Must belong to the logged in user, unless the logged in User is an administrator and the contact is not empty or the cart is anonymous

	- Contact ID is person/company who is placing the order... the cart's "owner".
	- User ID is person who created or is managing the cart. Typically, this would be the cart's owner, but a company might have account managers who create carts for clients.
	- Contact ID would be blank if the cart was created anonymously
	- User ID would be blank if the cart was created anonymously
	- User ID gets updated whenever the cart is updated, to reflect who is the current manager of the cart... ie. who is making changes.
	- Contact ID never gets updated (except as defined in next point)
	- If an anonymous cart is claimed by a user, it is merged into the user's existing cart. If the user doesn't have an existing cart, the Contact ID & User ID of the cart is set

	# If a user is logged in, get shopping cart for that user
	# If no user is logged in, check cookie for shopping cart code
	# Only create a new cart if neither is found
	# if the user is logged in and has a cart and there is also an anonymous cart, merge the anonymous cart into the users cart

	*/

	/**
	 * @param $shoppingCartCode
	 * @return ShoppingCart
	 */
	static function getShoppingCart($shoppingCartCode) {
		$shoppingCart = new ShoppingCart();
		if ($GLOBALS['gLoggedIn']) {
			$shoppingCartId = getFieldFromId("shopping_cart_id", "shopping_carts", "contact_id", $GLOBALS['gUserRow']['contact_id'], "shopping_cart_code <=> ?", $shoppingCartCode);
		} else {
			$shoppingCartId = getFieldFromId("shopping_cart_id", "shopping_carts", "shopping_cart_id", $_COOKIE['shopping_cart_id'], "user_id is null and shopping_cart_code <=> ?", $shoppingCartCode);
		}

		$shoppingCartRow = array();
		if (!empty($shoppingCartId)) {
			$shoppingCartRow = $shoppingCart->loadShoppingCart($shoppingCartId);
			if ($GLOBALS['gLoggedIn']) {
				$shoppingCartId = getFieldFromId("shopping_cart_id", "shopping_carts", "shopping_cart_id", $_COOKIE['shopping_cart_id'], "user_id is null");
				if (!empty($shoppingCartId)) {
					$shoppingCart->assimilateShoppingCart($shoppingCartId);
				}
			}
		}
		if ($shoppingCartRow['shopping_cart_code'] != $shoppingCartCode || $shoppingCartRow['user_id'] != $GLOBALS['gUserId']) {
			$shoppingCart->setValues(array("shopping_cart_code" => $shoppingCartCode, "user_id" => $GLOBALS['gUserId']));
		}
		return $shoppingCart;
	}

	static function getShoppingCartIdOnly($shoppingCartCode) {
		if ($GLOBALS['gLoggedIn']) {
			$shoppingCartId = getFieldFromId("shopping_cart_id", "shopping_carts", "user_id", $GLOBALS['gUserId'], "shopping_cart_code <=> ?", $shoppingCartCode);
		} else {
			$shoppingCartId = getFieldFromId("shopping_cart_id", "shopping_carts", "shopping_cart_id", $_COOKIE['shopping_cart_id'], "user_id is null and shopping_cart_code <=> ?", $shoppingCartCode);
		}
		return $shoppingCartId;
	}

	static function getShoppingCartById($shoppingCartId) {
		$shoppingCartId = getFieldFromId("shopping_cart_id", "shopping_carts", "shopping_cart_id", $shoppingCartId);
		if (empty($shoppingCartId)) {
			return false;
		}
		$shoppingCart = new ShoppingCart();
		$shoppingCart->loadShoppingCart($shoppingCartId);
		return $shoppingCart;
	}

	static function getShoppingCartForContact($contactId, $shoppingCartCode = "") {
		$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $contactId);
		if (empty($contactId)) {
			return false;
		}
		$shoppingCart = new ShoppingCart();
		$shoppingCartId = getFieldFromId("shopping_cart_id", "shopping_carts", "contact_id", $contactId, "shopping_cart_code <=> ?", $shoppingCartCode);
		if (!empty($shoppingCartId)) {
			$shoppingCart->loadShoppingCart($shoppingCartId);
		} else {
			$shoppingCart->iShoppingCart['contact_id'] = $contactId;
			$shoppingCart->iShoppingCart['shopping_cart_code'] = $shoppingCartCode;
		}
		return $shoppingCart;
	}

	static function getShoppingCartForUser($userId, $shoppingCartCode = "") {
		if (!$GLOBALS['gUserRow']['administrator_flag'] && $userId != $GLOBALS['gUserId']) {
			return false;
		}
		$shoppingCart = new ShoppingCart();
		$shoppingCartId = getFieldFromId("shopping_cart_id", "shopping_carts", "user_id", $userId, "shopping_cart_code <=> ?", $shoppingCartCode);
		if (!empty($shoppingCartId)) {
			$shoppingCart->loadShoppingCart($shoppingCartId);
		} else {
			$shoppingCart->iShoppingCart['user_id'] = $userId;
		}
		return $shoppingCart;
	}

	public static function estimateTax($parameters) {
		$shoppingCartItems = $parameters['shopping_cart_items'];
		$promotionId = $parameters['promotion_id'];
		$countryId = $parameters['country_id'];
		$address1 = $parameters['address_1'];
		$city = $parameters['city'];
		$state = $parameters['state'];
		$postalCode = $parameters['postal_code'];
		$fromCountryId = $parameters['from_country_id'];
		$fromAddress1 = $parameters['from_address_1'];
		$fromCity = $parameters['from_city'];
		$fromState = $parameters['from_state'];
		$fromPostalCode = $parameters['from_postal_code'];
		$shippingCharge = $parameters['shipping_charge'] ?: 0;
		$logAllTaxCalculations = getPreference("LOG_TAX_CALCULATION");
		$taxExemptId = CustomField::getCustomFieldData((empty($parameters['contact_id']) ? $GLOBALS['gUserRow']['contact_id'] : $parameters['contact_id']), "TAX_EXEMPT_ID");
		if (!empty($taxExemptId)) {
			if ($logAllTaxCalculations) {
				addProgramLog("Tax calculation: Contact ID " . (empty($parameters['contact_id']) ? $GLOBALS['gUserRow']['contact_id'] : $parameters['contact_id']) . " has tax exempt ID " . $taxExemptId);
			}
			return 0;
		}
		if (empty($countryId)) {
			$countryId = $GLOBALS['gUserRow']['country_id'] ?: 1000;
		}
		if (empty($address1)) {
			$address1 = $GLOBALS['gUserRow']['address_1'];
		}
		if (empty($state)) {
			$state = $GLOBALS['gUserRow']['state'];
		}
		if (empty($city)) {
			$city = $GLOBALS['gUserRow']['city'];
		}
		if (empty($postalCode)) {
			$postalCode = $GLOBALS['gUserRow']['postal_code'];
		}
		$allowZipPlus4 = !empty(getPreference("ALLOW_ZIP_PLUS_4_FOR_TAX_CALCULATIONS"));
		if (!$allowZipPlus4 && strlen($postalCode) > 5) {
			$postalCode = substr($postalCode, 0, 5);
		}
		if (empty($fromCountryId)) {
			$fromCountryId = $GLOBALS['gClientRow']['country_id'] ?: 1000;
		}
		if (empty($fromAddress1)) {
			$fromAddress1 = $GLOBALS['gClientRow']['address_1'];
		}
		if (empty($fromState)) {
			$fromState = $GLOBALS['gClientRow']['state'];
		}
		if (empty($fromCity)) {
			$fromCity = $GLOBALS['gClientRow']['city'];
		}
		if (empty($fromPostalCode)) {
			$fromPostalCode = $GLOBALS['gClientRow']['postal_code'];
		}
		if (!$allowZipPlus4 && strlen($fromPostalCode) > 5) {
			$fromPostalCode = substr($fromPostalCode, 0, 5);
		}
		if (function_exists("_localEstimateTax")) {
			$result = _localEstimateTax($shoppingCartItems, $promotionId, $countryId, $city, $state, $postalCode, $address1, $shippingCharge);
			if ($result !== false) {
				return $result;
			}
		}

		$cartTotal = 0;
		foreach ($shoppingCartItems as $thisItem) {
			$salePrice = $thisItem['sale_price'];
			if (is_array($thisItem['product_addons'])) {
				foreach ($thisItem['product_addons'] as $thisAddon) {
					$quantity = $thisAddon['quantity'];
					if (empty($quantity) || !is_numeric($quantity) || $quantity <= 0) {
						$quantity = 1;
					}
					if (!empty($thisAddon['sale_price'])) {
						$salePrice += $thisAddon['sale_price'] * $quantity;
					}
				}
			}
			$cartTotal += $thisItem['quantity'] * $salePrice;
		}
		$discountAmount = 0;
		if (!empty($promotionId)) {
			$promotionRow = ShoppingCart::getCachedPromotionRow($promotionId);
			if ($promotionRow['discount_percent'] > 0) {
				$discountAmount = round($cartTotal * ($promotionRow['discount_percent'] / 100), 2);
			}
			if ($promotionRow['discount_amount'] > 0) {
				$discountAmount += $promotionRow['discount_amount'];
			}
		}
		$discountPercent = ($cartTotal <= 0 ? 0 : $discountAmount / $cartTotal);

		$taxjarApiToken = getPreference("taxjar_api_token");
		if (!empty($taxjarApiToken)) {
			require_once __DIR__ . '/../taxjar/vendor/autoload.php';
			try {
				$client = TaxJar\Client::withApiKey($taxjarApiToken);
				$client->setApiConfig('headers', ['x-api-version' => '2022-01-24']);
				$orderData = [
					'from_country' => getFieldFromId("country_code", "countries", "country_id", $fromCountryId),
					'from_zip' => $fromPostalCode,
					'from_state' => $fromState,
					'from_city' => $fromCity,
					'from_street' => $fromAddress1,
					'to_country' => getFieldFromId("country_code", "countries", "country_id", $countryId),
					'to_zip' => $postalCode,
					'to_state' => $state,
					'to_city' => $city,
					'to_street' => $address1,
					'shipping' => $shippingCharge
				];
				$lineItems = array();
				$amount = 0;
				foreach ($shoppingCartItems as $thisItem) {
					$taxableAmount = $thisItem['quantity'] * $thisItem['sale_price'] * (1 - $discountPercent);
					$unitPrice = round($taxableAmount / $thisItem['quantity'], 2);
					$thisLineItem = array("id" => $thisItem['shopping_cart_item_id'], "quantity" => $thisItem['quantity'], "unit_price" => $unitPrice);
					$taxjarProductCategoryCode = CustomField::getCustomFieldData($thisItem['product_id'], "TAXJAR_PRODUCT_CATEGORY_CODE", "PRODUCTS");
					if (!empty($taxjarProductCategoryCode)) {
						$thisLineItem['product_tax_code'] = $taxjarProductCategoryCode;
					} else {
						$taxSet = executeQuery("select product_tax_code from product_categories join product_category_links using (product_category_id) where product_id = ? and product_tax_code is not null order by sequence_number", $thisItem['product_id']);
						if ($taxRow = getNextRow($taxSet)) {
							$thisLineItem['product_tax_code'] = $taxRow['product_tax_code'];
						}
					}
					$amount += ($thisItem['quantity'] * $unitPrice);
					$lineItems[] = $thisLineItem;
				}
				$orderData['amount'] = $amount;
				$orderData['line_items'] = $lineItems;
				$orderData['plugin'] = "coreware";
				$stateList = getCachedData("taxjar", "nexus_list");
				if (empty($stateList)) {
					$nexusList = $client->nexusRegions();
					$stateList = array();
					foreach ((array)$nexusList as $thisNexus) {
						$stateList[] = $thisNexus->region_code;
					}
					setCachedData("taxjar", "nexus_list", $stateList, 1);
				}
				if (!in_array($state, $stateList)) {
					if ($logAllTaxCalculations) {
						addProgramLog("Taxjar Tax estimate: Client does not have TaxJar nexus in state " . $state);
					}
					return 0;
				}
				$tax = $client->taxForOrder($orderData);
				addProgramLog("Taxjar Tax estimate:\n\nOrder Data: " . jsonEncode($orderData) . "\n\nTax: " . jsonEncode($tax));
				return round($tax->amount_to_collect, 2);
			} catch (Exception $e) {
				addProgramLog("Taxjar Tax estimate Error:\n\nOrder Data: " . jsonEncode($orderData) . "\n\nError: " . $e->getMessage());
				$GLOBALS['gPrimaryDatabase']->logError("Taxjar Tax estimate Error:\n\nOrder Data: " . jsonEncode($orderData) . "\n\nError: " . $e->getMessage());
				if (startsWith($e->getMessage(), "403")) {
					sendCredentialsError(["integration_name" => "TaxJar", "error_message" => $e->getMessage()]);
				}
			}
		}
		$logEntry = sprintf("Using country ID %s; city %s; state %s, postal code %s; address %s; shipping charge %s", $countryId, $city, $state, $postalCode, $address1, $shippingCharge);
		$taxRate = CustomField::getCustomFieldData((empty($parameters['contact_id']) ? $GLOBALS['gUserRow']['contact_id'] : $parameters['contact_id']), "TAX_RATE");
		$flatTaxAmount = 0;
		if (strlen($taxRate) > 0) {
			$logEntry = "\nCustom tax rate for contact: " . $taxRate;
		}
		if (empty($taxRate)) {
			$taxRateRow = Order::getPostalCodeTaxRateRow($countryId, $postalCode);
			if (empty($taxRateRow)) {
				$taxRateRow = Order::getStateTaxRateRow($countryId, $state);
			}
			if (empty($taxRateRow['tax_rate'])) {
				$taxRate = 0;
			} else {
				$taxRate = $taxRateRow['tax_rate'];
				$flatTaxAmount = $taxRateRow['flat_rate'];
				$logEntry = sprintf("\nTax rate based on address: %s, %s: %s", $state, $postalCode, $taxRate);
			}
		}
		$totalTax = 0;

		$taxRateRows = array();
		if (!empty($postalCode)) {
			$resultSet = executeQuery("select * from postal_code_tax_rates where client_id = ? and country_id = ? and (postal_code = ? or postal_code = ?) and (product_category_id is not null or product_department_id is not null)",
				$GLOBALS['gClientId'], $countryId, $postalCode, substr($postalCode, 0, 3));
			while ($row = getNextRow($resultSet)) {
				$taxRateRows[] = $row;
			}
		}
		if (!empty($state)) {
			$resultSet = executeQuery("select * from state_tax_rates where client_id = ? and country_id = ? and state = ? and (product_category_id is not null or product_department_id is not null)",
				$GLOBALS['gClientId'], $countryId, $state);
			while ($row = getNextRow($resultSet)) {
				$taxRateRows[] = $row;
			}
		}
		foreach ($shoppingCartItems as $index => $thisItem) {
			$notTaxable = getFieldFromId("not_taxable", "products", "product_id", $thisItem['product_id']);
			if ($notTaxable) {
				$logEntry .= sprintf("\nProduct ID %s is not taxable", $thisItem['product_id']);
				continue;
			}
			$salePrice = $thisItem['sale_price'];
			if (is_array($thisItem['product_addons'])) {
				foreach ($thisItem['product_addons'] as $thisAddon) {
					$quantity = $thisAddon['quantity'];
					if (empty($quantity) || !is_numeric($quantity) || $quantity <= 0) {
						$quantity = 1;
					}
					if (!empty($thisAddon['sale_price'])) {
						$salePrice += $thisAddon['sale_price'] * $quantity;
					}
				}
			}
			$taxableAmount = $thisItem['quantity'] * $salePrice * (1 - $discountPercent);
			$thisTaxRate = false;
			$thisFlatTaxAmount = 0;
			$taxRateId = getFieldFromId("tax_rate_id", "products", "product_id", $thisItem['product_id']);
			if (!empty($taxRateId)) {
				$thisTaxRate = getFieldFromId("tax_rate", "tax_rates", "tax_rate_id", $taxRateId);
				$thisFlatTaxAmount = getFieldFromId("flat_rate", "tax_rates", "tax_rate_id", $taxRateId);
				$logEntry .= sprintf("\nTax rate for on product ID %s: %s", $thisItem['product_id'], $thisTaxRate);
			} else {
				foreach ($taxRateRows as $row) {
					if (!empty($row['product_category_id'])) {
						if (ProductCatalog::productIsInCategory($thisItem['product_id'], $row['product_category_id'])) {
							$thisFlatTaxAmount = $row['flat_rate'];
							$thisTaxRate = $row['tax_rate'];
							$logEntry .= sprintf("\nTax rate based on category for product ID %s: %s", $thisItem['product_id'], $thisTaxRate);
							break;
						}
					}
					if (!empty($row['product_department_id'])) {
						if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $row['product_department_id'])) {
							$thisFlatTaxAmount = $row['flat_rate'];
							$thisTaxRate = $row['tax_rate'];
							$logEntry .= sprintf("\nTax rate based on department for product ID %s: %s", $thisItem['product_id'], $thisTaxRate);
							break;
						}
					}
				}
			}
			if ($thisTaxRate === false) {
				$thisTaxRate = $taxRate;
				$thisFlatTaxAmount = $flatTaxAmount;
			}
			$thisTaxCharge = $thisFlatTaxAmount + round($taxableAmount * $thisTaxRate / 100, 2);
			$logEntry .= "\n" . $thisTaxCharge . " tax for product ID " . $thisItem['product_id'] . ", Tax rate: " . $thisTaxRate . ($thisFlatTaxAmount > 0 ? " + flat tax: " . $thisFlatTaxAmount : "");
			$totalTax += $thisTaxCharge;
		}

		$taxShipping = getPreference("TAX_SHIPPING");
		if ($taxShipping) {
			$thisTaxCharge = round($shippingCharge * $taxRate / 100, 2);
			$totalTax += $thisTaxCharge;
		}
		if ($logAllTaxCalculations) {
			addProgramLog("Tax Calculation: " . $logEntry . "\nTotal Tax: " . (round($totalTax * 100) / 100));
		}

		return (round($totalTax * 100) / 100);
	}

	public static function notifyCRM($shoppingCartId, $substitutions) {

		$result = "";
		$activeCampaignApiKey = getPreference("ACTIVECAMPAIGN_API_KEY");
		$activeCampaignTestMode = getPreference("ACTIVECAMPAIGN_TEST");
		if (!empty($activeCampaignApiKey)) {
			$activeCampaign = new ActiveCampaign($activeCampaignApiKey, $activeCampaignTestMode);
			if (!$activeCampaign->logAbandonedCart($shoppingCartId)) {
				$result = "Sending abandoned cart (ID " . $shoppingCartId . ") to ActiveCampaign failed: " . $activeCampaign->getErrorMessage();
			} else {
				$result = "Abandoned cart (ID " . $shoppingCartId . ") sent to ActiveCampaign successfully.";
			}
		}

		$highLevelAccessToken = getPreference(makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_ACCESS_TOKEN");
		$highLevelLocationId = getPreference(makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_LOCATION_ID");
		if (!empty($highLevelAccessToken)) {
			$highLevel = new HighLevel($highLevelAccessToken, $highLevelLocationId);
			if (empty($highLevel->logAbandonedCart($shoppingCartId, $substitutions))) {
				$result = "Failed sending abandoned cart (ID " . $shoppingCartId . ") to " . HighLevel::HIGHLEVEL_DISPLAY_NAME . ": " . $highLevel->getErrorMessage();
			} else {
				$result = "Abandoned cart (ID " . $shoppingCartId . ") sent to " . HighLevel::HIGHLEVEL_DISPLAY_NAME . " successfully.";
			}
		}

		return $result;
	}

	public static function getCachedPromotionRow($promotionId) {
		if (empty($promotionId)) {
			return array();
		}
		$promotionRow = getCachedData("promotion_row_data", $promotionId);
		if (!is_array($promotionRow)) {
			$promotionRow = getRowFromId("promotions", "promotion_id", $promotionId);
			if (!empty($promotionRow)) {
				$resultSet = executeQuery("select group_concat(contact_type_id) as id_list from promotion_terms_contact_types " .
					"where promotion_id = ?", $promotionId);
				if ($row = getNextRow($resultSet)) {
					$promotionRow['contact_type_id_list'] = $row['id_list'];
				}
				$resultSet = executeQuery("select group_concat(user_type_id) as id_list from promotion_terms_user_types " .
					"where promotion_id = ?", $promotionId);
				if ($row = getNextRow($resultSet)) {
					$promotionRow['user_type_id_list'] = $row['id_list'];
				}
				$resultSet = executeQuery("select group_concat(country_id) as id_list from promotion_terms_countries " .
					"where promotion_id = ?", $promotionId);
				if ($row = getNextRow($resultSet)) {
					$promotionRow['country_id_list'] = $row['id_list'];
				}
				$tableArray = array("promotion_purchased_products", "promotion_purchased_product_categories", "promotion_purchased_product_category_groups", "promotion_purchased_product_departments",
					"promotion_purchased_product_manufacturers", "promotion_purchased_product_tags", "promotion_purchased_product_types", "promotion_purchased_sets", "promotion_terms_products",
					"promotion_terms_product_categories", "promotion_terms_product_category_groups", "promotion_terms_product_departments", "promotion_terms_product_manufacturers", "promotion_terms_product_tags",
					"promotion_terms_product_types", "promotion_terms_sets");
				foreach ($tableArray as $tableName) {
					$promotionRow[$tableName] = array();
					$resultSet = executeQuery("select * from " . $tableName . " where promotion_id = ?", $promotionId);
					while ($row = getNextRow($resultSet)) {
						$promotionRow[$tableName][] = $row;
					}
				}
			}
			setCachedData("promotion_row_data", $promotionId, $promotionRow, 24);
		}
		return $promotionRow;
	}

	function isPromotionValid($promotionId = "", $countryId = "", $addRequirements = false) {
		foreach ($this->iShoppingCartItems as $index => $thisItem) {
			$this->iShoppingCartItems[$index]['promotion_requirements'] = 0;
			unset($this->iShoppingCartItems[$index]['promotional_price']);
		}
		if (empty($promotionId)) {
			$promotionId = $this->iPromotionId;
		}
		if (empty($promotionId)) {
			return false;
		}
		$promotionRow = ShoppingCart::getCachedPromotionRow($promotionId);

		if ($promotionId == $this->iShoppingCart['promotion_id'] && !empty($this->iShoppingCart['promotion_code'])) {
			if ($promotionRow['promotion_code'] != $this->iShoppingCart['promotion_code']) {
				$oneTimeUsePromotionId = getFieldFromId("promotion_id", "one_time_use_promotion_codes", "promotion_code", $this->iShoppingCart['promotion_code']);
				if ($promotionId != $oneTimeUsePromotionId) {
					$promotionId = false;
					$promotionRow = array();
				}
			}
		}

		$promotionId = $promotionRow['promotion_id'];
		if (empty($promotionId)) {
			$this->iPromotionId = "";
			$this->iErrorMessage = "Invalid Promotion";
			return false;
		}
		if ($promotionRow['start_date'] > date("Y-m-d")) {
			$this->iErrorMessage = "Promotion Not Yet Valid";
			return false;
		}
		if (!empty($promotionRow['expiration_date']) && $promotionRow['expiration_date'] < date("Y-m-d")) {
			$this->iErrorMessage = "Promotion Is Expired";
			return false;
		}
		if ($promotionRow['inactive']) {
			$this->iErrorMessage = "Promotion is no longer active";
			return false;
		}
		if (!$GLOBALS['gUserRow']['administrator_flag'] && $promotionRow['internal_use_only']) {
			$this->iErrorMessage = "Invalid Promotion";
			return false;
		}

		if (function_exists("_localIsPromotionValid")) {
			$localIsValid = _localIsPromotionValid($promotionId, $this->iShoppingCartItems);
			if (!is_null($localIsValid)) {
				return (!empty($localIsValid));
			}
		}

		$orderAmount = 0;
		foreach ($this->iShoppingCartItems as $index => $thisItem) {
			$orderAmount += $thisItem['quantity'] * $thisItem['sale_price'];
		}
		if ($orderAmount < $promotionRow['minimum_amount']) {
			$this->iErrorMessage = "Minimum order amount not met";
			return false;
		}
		if ($promotionRow['requires_user'] && !$GLOBALS['gLoggedIn']) {
			$this->iErrorMessage = "Promotion requires a login";
			return false;
		}
		$contactId = $this->getContact() ?: $GLOBALS['gUserRow']['contact_id'];
		$userId = ($contactId == $GLOBALS['gUserRow']['contact_id'] ? $GLOBALS['gUserId'] : Contact::getContactUserId($contactId));
		$userRow = ($userId == $GLOBALS['gUserId'] ? $GLOBALS['gUserRow'] : Contact::getUser($userId));
		if (!empty($promotionRow['user_id']) && $userId != $promotionRow['user_id']) {
			$this->iErrorMessage = "Invalid Promotion";
			return false;
		}
		if ($promotionRow['no_previous_orders']) {
			if ($GLOBALS['gLoggedIn']) {
				$previousOrderId = getFieldFromId("order_id", "orders", "contact_id", $GLOBALS['gUserRow']['contact_id'], "deleted = 0 and order_id in (select order_id from order_items where product_id in (select product_id from products where virtual_product = 0))");
				if (!empty($previousOrderId)) {
					$this->iErrorMessage = "Invalid Promotion";
					return false;
				}
			}
			$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $this->getContact());
			if (!empty($emailAddress)) {
				$previousOrderId = getFieldFromId("order_id", "orders", "deleted", "0", "contact_id in (select contact_id from contacts where email_address = ?) and order_id in (select order_id from order_items where product_id in (select product_id from products where virtual_product = 0))", $emailAddress);
				if (!empty($previousOrderId)) {
					$this->iErrorMessage = "Invalid Promotion";
					return false;
				}
			}
		}
		if (!empty($promotionRow['maximum_per_email'])) {
			$emailAddress = getFieldFromId("email_address", "contacts", "contact_id", $this->iShoppingCart['contact_id']);
			if (!empty($emailAddress)) {
				$usages = 0;
				$resultSet = executeQuery("select count(*) from order_promotions where order_id in (select order_id from orders where deleted = 0 and contact_id in (select contact_id from contacts where email_address = ?)) and promotion_id = ?", $emailAddress, $promotionId);
				if ($row = getNextRow($resultSet)) {
					$usages = $row['count(*)'];
				}
				if ($usages >= $promotionRow['maximum_per_email']) {
					$this->iErrorMessage = "This promotion is no longer valid";
					return false;
				}
			}
			if (!empty($this->iShoppingCart['contact_id'])) {
				$usages = 0;
				$resultSet = executeQuery("select count(*) from order_promotions where order_id in (select order_id from orders where deleted = 0 and contact_id = ?) and promotion_id = ?", $this->iShoppingCart['contact_id'], $promotionId);
				if ($row = getNextRow($resultSet)) {
					$usages = $row['count(*)'];
				}
				if ($usages >= $promotionRow['maximum_per_email']) {
					$this->iErrorMessage = "This promotion is no longer valid";
					return false;
				}
			}
		}
		if (!empty($promotionRow['maximum_usages'])) {
			$usages = 0;
			$resultSet = executeQuery("select count(*) from order_promotions where promotion_id = ?", $promotionId);
			if ($row = getNextRow($resultSet)) {
				$usages = $row['count(*)'];
			}
			if ($usages >= $promotionRow['maximum_usages']) {
				$this->iErrorMessage = "This promotion is completely used up";
				return false;
			}
		}
		if (!empty($promotionRow['contact_type_id_list'])) {
			$idList = explode(",", $promotionRow['contact_type_id_list']);
			$contactTypeId = empty($this->iContactTypeId) ? $userRow['contact_type_id'] : $this->iContactTypeId;
			if ((empty($contactTypeId) || !in_array($contactTypeId, $idList)) && empty($promotionRow['exclude_contact_types'])) {
				$this->iErrorMessage = "You do not qualify for this promotion";
				return false;
			}
			if (!empty($contactTypeId) && in_array($contactTypeId, $idList) && !empty($promotionRow['exclude_contact_types'])) {
				$this->iErrorMessage = "You do not qualify for this promotion";
				return false;
			}
		}
		if (!empty($promotionRow['user_type_id_list'])) {
			$idList = explode(",", $promotionRow['user_type_id_list']);
			if ((empty($userRow['user_type_id']) || !in_array($userRow['user_type_id'], $idList)) && empty($promotionRow['exclude_user_types'])) {
				$this->iErrorMessage = "You do not qualify for this promotion";
				return false;
			}
			if (!empty($userRow['user_type_id']) && in_array($userRow['user_type_id'], $idList) && !empty($promotionRow['exclude_user_types'])) {
				$this->iErrorMessage = "You do not qualify for this promotion";
				return false;
			}
		}
		if (empty($countryId)) {
			$countryId = $userRow['country_id'];
		}
		if (!empty($promotionRow['country_id_list'])) {
			$idList = explode(",", $promotionRow['country_id_list']);
			if (empty($countryId) || !in_array($countryId, $idList)) {
				$this->iErrorMessage = "You do not qualify for this promotion";
				return false;
			}
		}

		foreach ($promotionRow['promotion_purchased_products'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			if ($orderAmount <= 0 && $orderQuantity <= 0) {
				continue;
			}
			if (!$GLOBALS['gLoggedIn']) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
			$purchaseSet = executeQuery("select sum(quantity) total_quantity,sum(sale_price * quantity) total_amount from order_items where " .
				"order_id in (select order_id from orders where contact_id = ?" .
				(empty($row['days_before']) ? "" : " and order_time > date_sub(current_date,interval " . $row['days_before'] . " day)") . ") and " .
				"product_id = ?", $userRow['contact_id'], $row['product_id']);
			$totalQuantity = 0;
			$totalAmount = 0;
			if ($purchaseRow = getNextRow($purchaseSet)) {
				$totalQuantity = $purchaseRow['total_quantity'];
				$totalAmount = $purchaseRow['total_amount'];
			}
			if ($totalQuantity < $orderQuantity || $totalAmount < $orderAmount) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
		}

		foreach ($promotionRow['promotion_purchased_product_categories'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			if ($orderAmount <= 0 && $orderQuantity <= 0) {
				continue;
			}
			if (!$GLOBALS['gLoggedIn']) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
			$purchaseSet = executeQuery("select sum(quantity) total_quantity,sum(sale_price * quantity) total_amount from order_items where " .
				"order_id in (select order_id from orders where contact_id = ?" .
				(empty($row['days_before']) ? "" : " and order_time > date_sub(current_date,interval " . $row['days_before'] . " day)") . ") and " .
				"product_id in (select product_id from product_category_links where product_category_id = ?)", $userRow['contact_id'], $row['product_category_id']);
			$totalQuantity = 0;
			$totalAmount = 0;
			if ($purchaseRow = getNextRow($purchaseSet)) {
				$totalQuantity = $purchaseRow['total_quantity'];
				$totalAmount = $purchaseRow['total_amount'];
			}
			if ($totalQuantity < $orderQuantity || $totalAmount < $orderAmount) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
		}

		foreach ($promotionRow['promotion_purchased_product_category_groups'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			if ($orderAmount <= 0 && $orderQuantity <= 0) {
				continue;
			}
			if (!$GLOBALS['gLoggedIn']) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
			$purchaseSet = executeQuery("select sum(quantity) total_quantity,sum(sale_price * quantity) total_amount from order_items where " .
				"order_id in (select order_id from orders where contact_id = ?" .
				(empty($row['days_before']) ? "" : " and order_time > date_sub(current_date,interval " . $row['days_before'] . " day)") . ") and " .
				"product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from " .
				"product_category_group_links where product_category_group_id = ?))", $userRow['contact_id'], $row['product_category_group_id']);
			$totalQuantity = 0;
			$totalAmount = 0;
			if ($purchaseRow = getNextRow($purchaseSet)) {
				$totalQuantity = $purchaseRow['total_quantity'];
				$totalAmount = $purchaseRow['total_amount'];
			}
			if ($totalQuantity < $orderQuantity || $totalAmount < $orderAmount) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
		}

		foreach ($promotionRow['promotion_purchased_product_departments'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			if ($orderAmount <= 0 && $orderQuantity <= 0) {
				continue;
			}
			if (!$GLOBALS['gLoggedIn']) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
			$purchaseSet = executeQuery("select product_id,sum(quantity) total_quantity,sum(sale_price * quantity) total_amount from order_items where " .
				"order_id in (select order_id from orders where contact_id = ?" .
				(empty($row['days_before']) ? "" : " and order_time > date_sub(current_date,interval " . $row['days_before'] . " day)") . ") " .
				"group by product_id", $userRow['contact_id']);
			$totalQuantity = 0;
			$totalAmount = 0;
			while ($purchaseRow = getNextRow($purchaseSet)) {
				if (!ProductCatalog::productIsInDepartment($purchaseRow['product_id'], $row['product_department_id'])) {
					continue;
				}
				$totalQuantity += $purchaseRow['total_quantity'];
				$totalAmount += $purchaseRow['total_amount'];
			}
			if ($totalQuantity < $orderQuantity || $totalAmount < $orderAmount) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
		}

		foreach ($promotionRow['promotion_purchased_product_manufacturers'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			if ($orderAmount <= 0 && $orderQuantity <= 0) {
				continue;
			}
			if (!$GLOBALS['gLoggedIn']) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
			$purchaseSet = executeQuery("select sum(quantity) total_quantity,sum(sale_price * quantity) total_amount from order_items where " .
				"order_id in (select order_id from orders where contact_id = ?" .
				(empty($row['days_before']) ? "" : " and order_time > date_sub(current_date,interval " . $row['days_before'] . " day)") . ") and " .
				"product_id in (select product_id from products where product_manufacturer_id = ?)", $userRow['contact_id'], $row['product_manufacturer_id']);
			$totalQuantity = 0;
			$totalAmount = 0;
			if ($purchaseRow = getNextRow($purchaseSet)) {
				$totalQuantity = $purchaseRow['total_quantity'];
				$totalAmount = $purchaseRow['total_amount'];
			}
			if ($totalQuantity < $orderQuantity || $totalAmount < $orderAmount) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
		}

		foreach ($promotionRow['promotion_purchased_product_tags'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			if ($orderAmount <= 0 && $orderQuantity <= 0) {
				continue;
			}
			if (!$GLOBALS['gLoggedIn']) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
			$purchaseSet = executeQuery("select sum(quantity) total_quantity,sum(sale_price * quantity) total_amount from order_items where " .
				"order_id in (select order_id from orders where contact_id = ?" .
				(empty($row['days_before']) ? "" : " and order_time > date_sub(current_date,interval " . $row['days_before'] . " day)") . ") and " .
				"product_id in (select product_id from product_tag_links where product_tag_id = ? and (start_date is null or start_date <= current_date) and " .
				"(expiration_date is null or expiration_date >= current_date))", $userRow['contact_id'], $row['product_tag_id']);
			$totalQuantity = 0;
			$totalAmount = 0;
			if ($purchaseRow = getNextRow($purchaseSet)) {
				$totalQuantity = $purchaseRow['total_quantity'];
				$totalAmount = $purchaseRow['total_amount'];
			}
			if ($totalQuantity < $orderQuantity || $totalAmount < $orderAmount) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
		}

		foreach ($promotionRow['promotion_purchased_product_types'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			if ($orderAmount <= 0 && $orderQuantity <= 0) {
				continue;
			}
			if (!$GLOBALS['gLoggedIn']) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
			$purchaseSet = executeQuery("select sum(quantity) total_quantity,sum(sale_price * quantity) total_amount from order_items where " .
				"order_id in (select order_id from orders where contact_id = ?" .
				(empty($row['days_before']) ? "" : " and order_time > date_sub(current_date,interval " . $row['days_before'] . " day)") . ") and " .
				"product_id in (select product_id from products where product_type_id = ?)", $userRow['contact_id'], $row['product_type_id']);
			$totalQuantity = 0;
			$totalAmount = 0;
			if ($purchaseRow = getNextRow($purchaseSet)) {
				$totalQuantity = $purchaseRow['total_quantity'];
				$totalAmount = $purchaseRow['total_amount'];
			}
			if ($totalQuantity < $orderQuantity || $totalAmount < $orderAmount) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
		}

		foreach ($promotionRow['promotion_purchased_sets'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			if ($orderAmount <= 0 && $orderQuantity <= 0) {
				continue;
			}
			if (!$GLOBALS['gLoggedIn']) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
			$purchaseSet = executeQuery("select sum(quantity) total_quantity,sum(sale_price * quantity) total_amount from order_items where " .
				"order_id in (select order_id from orders where contact_id = ?" .
				(empty($row['days_before']) ? "" : " and order_time > date_sub(current_date,interval " . $row['days_before'] . " day)") . ") and " .
				"product_id in (select product_id from promotion_set_products where promotion_set_id = ?)", $userRow['contact_id'], $row['promotion_set_id']);
			$totalQuantity = 0;
			$totalAmount = 0;
			if ($purchaseRow = getNextRow($purchaseSet)) {
				$totalQuantity = $purchaseRow['total_quantity'];
				$totalAmount = $purchaseRow['total_amount'];
			}
			if ($totalQuantity < $orderQuantity || $totalAmount < $orderAmount) {
				$this->iErrorMessage = "Minimum purchased product quantities are not met";
				return false;
			}
		}

		$limitEvents = (!empty($promotionRow['event_start_date']) || !empty($promotionRow['event_end_date']));
		usort($this->iShoppingCartItems, array($this, "sortCartItemsDesc"));

		foreach ($promotionRow['promotion_terms_products'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			foreach ($this->iShoppingCartItems as $index => $thisItem) {
				if ($orderAmount <= 0 && $orderQuantity <= 0) {
					break;
				}
				if ($row['product_id'] == $thisItem['product_id']) {
					$thisItem['quantity'] -= $thisItem['promotion_requirements'];
                    if($thisItem['sale_price'] > 0) {
                        $usedQuantity = max(min($orderQuantity, $thisItem['quantity']),
                            min(ceil($orderAmount / $thisItem['sale_price']), $thisItem['quantity']));
                    } else {
                        $usedQuantity = min($orderQuantity, $thisItem['quantity']);
                    }
					$this->iShoppingCartItems[$index]['promotion_requirements'] += $usedQuantity;
					$orderQuantity -= $usedQuantity;
					$orderAmount -= $usedQuantity * $thisItem['sale_price'];
				}
			}
			if ($orderQuantity > 0 && $addRequirements) {
				$listPrice = getFieldFromId("list_price", "products", "product_id", $row['product_id']);
				foreach ($this->iShoppingCartItems as $index => $thisItem) {
					if ($row['product_id'] == $thisItem['product_id'] && $thisItem['sale_price'] == $listPrice &&
						empty($thisItem['location_id']) && empty($thisItem['serial_number']) &&
						!array_key_exists("promotional_price", $thisItem)) {
						$this->iShoppingCartItems[$index]['quantity'] += $orderQuantity;
						$this->iShoppingCartItems[$index]['promotion_requirements'] += $orderQuantity;
						$orderQuantity = 0;
					}
				}
				if ($orderQuantity > 0) {
					$this->iShoppingCartItems[] = array("product_id" => $row['product_id'], "quantity" => $orderQuantity,
						"sale_price" => $listPrice, "promotion_requirements" => $orderQuantity, "addon_count" => 0);
					$orderQuantity = 0;
				}
			}
			if ($orderQuantity > 0 || $orderAmount > 0) {
				$this->iErrorMessage = "Minimum product quantities are not met";
				return false;
			}
		}
		usort($this->iShoppingCartItems, array($this, "sortCartItemsDesc"));

# get Excluded products, category groups and departments

		$excludedProductIds = array();
		$excludedProductDepartmentIds = array();
		$excludedProductCategoryGroupIds = array();
		$resultSet = executeQuery("select product_id from promotion_terms_excluded_products where promotion_id = ? union " .
			"select product_id from products where product_manufacturer_id is not null and product_manufacturer_id in (select product_manufacturer_id from promotion_terms_excluded_product_manufacturers where promotion_id = ?) union " .
			"select product_id from products where product_type_id is not null and product_type_id in (select product_type_id from promotion_terms_excluded_product_types where promotion_id = ?) union " .
			"select product_id from product_category_links where product_category_id in (select product_category_id from promotion_terms_excluded_product_categories where promotion_id = ?) union " .
			"select product_id from promotion_set_products where promotion_set_id in (select promotion_set_id from promotion_terms_excluded_sets where promotion_id = ?) union " .
			"select product_id from product_tag_links where (start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date >= current_date) and " .
			"product_tag_id in (select product_tag_id from promotion_terms_excluded_product_tags where promotion_id = ?)",
			$promotionId, $promotionId, $promotionId, $promotionId, $promotionId, $promotionId);
		while ($row = getNextRow($resultSet)) {
			$excludedProductIds[$row['product_id']] = $row['product_id'];
		}
		$resultSet = executeQuery("select product_department_id from promotion_terms_excluded_product_departments where promotion_id = ?", $promotionId);
		while ($row = getNextRow($resultSet)) {
			$excludedProductDepartmentIds[] = $row['product_department_id'];
		}
		$resultSet = executeQuery("select product_category_group_id from promotion_terms_excluded_product_category_groups where promotion_id = ?", $promotionId);
		while ($row = getNextRow($resultSet)) {
			$excludedProductCategoryGroupIds[] = $row['product_category_group_id'];
		}

		if ($limitEvents) {
			$resultSet = executeQuery("select product_id from events where product_id is not null and (" . (empty($promotionRow['event_start_date']) ? "" : "start_date < " . makeDateParameter($promotionRow['event_start_date'])) .
				(empty($promotionRow['event_end_date']) ? "" : (empty($promotionRow['event_start_date']) ? "" : " or ") . "start_date > " . makeDateParameter($promotionRow['event_end_date'])) . ")");
			while ($row = getNextRow($resultSet)) {
				$excludedProductIds[$row['product_id']] = $row['product_id'];
			}
		}

# Check to see if product department requirements are met

		foreach ($promotionRow['promotion_terms_product_departments'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			foreach ($this->iShoppingCartItems as $index => $thisItem) {
				if ($orderAmount <= 0 && $orderQuantity <= 0) {
					break;
				}
				if (array_key_exists($thisItem['product_id'], $excludedProductIds)) {
					continue;
				}
				if (!ProductCatalog::productIsInDepartment($thisItem['product_id'], $row['product_department_id'])) {
					continue;
				}
				$excludeProduct = false;
				foreach ($excludedProductDepartmentIds as $departmentId) {
					if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				foreach ($excludedProductCategoryGroupIds as $categoryGroupId) {
					if (ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $categoryGroupId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				$thisItem['quantity'] -= $thisItem['promotion_requirements'];
                if($thisItem['sale_price'] > 0) {
                    $usedQuantity = max(min($orderQuantity, $thisItem['quantity']),
                        min(ceil($orderAmount / $thisItem['sale_price']), $thisItem['quantity']));
                } else {
                    $usedQuantity = min($orderQuantity, $thisItem['quantity']);
                }
				$this->iShoppingCartItems[$index]['promotion_requirements'] += $usedQuantity;
				$orderQuantity -= $usedQuantity;
				$orderAmount -= $usedQuantity * $thisItem['sale_price'];
			}
			if ($orderQuantity > 0 || $orderAmount > 0) {
				$this->iErrorMessage = "Minimum product department quantities not met";
				return false;
			}
		}

# Check to see if product category group requirements are met

		foreach ($promotionRow['promotion_terms_product_category_groups'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			foreach ($this->iShoppingCartItems as $index => $thisItem) {
				if ($orderAmount <= 0 && $orderQuantity <= 0) {
					break;
				}
				if (array_key_exists($thisItem['product_id'], $excludedProductIds)) {
					continue;
				}
				if (!ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $row['product_category_group_id'])) {
					continue;
				}
				$excludeProduct = false;
				foreach ($excludedProductDepartmentIds as $departmentId) {
					if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				foreach ($excludedProductCategoryGroupIds as $categoryGroupId) {
					if (ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $categoryGroupId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				$thisItem['quantity'] -= $thisItem['promotion_requirements'];
                if($thisItem['sale_price'] > 0) {
                    $usedQuantity = max(min($orderQuantity, $thisItem['quantity']),
                        min(ceil($orderAmount / $thisItem['sale_price']), $thisItem['quantity']));
                } else {
                    $usedQuantity = min($orderQuantity, $thisItem['quantity']);
                }
				$this->iShoppingCartItems[$index]['promotion_requirements'] += $usedQuantity;
				$orderQuantity -= $usedQuantity;
				$orderAmount -= $usedQuantity * $thisItem['sale_price'];
			}
			if ($orderQuantity > 0 || $orderAmount > 0) {
				$this->iErrorMessage = "Minimum product category group quantities not met";
				return false;
			}
		}

# Check to see if product category requirements are met

		foreach ($promotionRow['promotion_terms_product_categories'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			foreach ($this->iShoppingCartItems as $index => $thisItem) {
				if ($orderAmount <= 0 && $orderQuantity <= 0) {
					break;
				}
				if (array_key_exists($thisItem['product_id'], $excludedProductIds)) {
					continue;
				}
				if (!ProductCatalog::productIsInCategory($thisItem['product_id'], $row['product_category_id'])) {
					continue;
				}
				$excludeProduct = false;
				foreach ($excludedProductDepartmentIds as $departmentId) {
					if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				foreach ($excludedProductCategoryGroupIds as $categoryGroupId) {
					if (ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $categoryGroupId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				$thisItem['quantity'] -= $thisItem['promotion_requirements'];
                if($thisItem['sale_price'] > 0) {
                    $usedQuantity = max(min($orderQuantity, $thisItem['quantity']),
                        min(ceil($orderAmount / $thisItem['sale_price']), $thisItem['quantity']));
                } else {
                    $usedQuantity = min($orderQuantity, $thisItem['quantity']);
                }
				$this->iShoppingCartItems[$index]['promotion_requirements'] += $usedQuantity;
				$orderQuantity -= $usedQuantity;
				$orderAmount -= $usedQuantity * $thisItem['sale_price'];
			}
			if ($orderQuantity > 0 || $orderAmount > 0) {
				$this->iErrorMessage = "Minimum product category quantities not met";
				return false;
			}
		}

# Check to see if product tag requirements are met

		foreach ($promotionRow['promotion_terms_product_tags'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			foreach ($this->iShoppingCartItems as $index => $thisItem) {
				if ($orderAmount <= 0 && $orderQuantity <= 0) {
					break;
				}
				if (array_key_exists($thisItem['product_id'], $excludedProductIds)) {
					continue;
				}
				if (!ProductCatalog::productIsTagged($thisItem['product_id'], $row['product_tag_id'])) {
					continue;
				}
				$excludeProduct = false;
				foreach ($excludedProductDepartmentIds as $departmentId) {
					if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				foreach ($excludedProductCategoryGroupIds as $categoryGroupId) {
					if (ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $categoryGroupId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				$thisItem['quantity'] -= $thisItem['promotion_requirements'];
                if($thisItem['sale_price'] > 0) {
                    $usedQuantity = max(min($orderQuantity, $thisItem['quantity']),
                        min(ceil($orderAmount / $thisItem['sale_price']), $thisItem['quantity']));
                } else {
                    $usedQuantity = min($orderQuantity, $thisItem['quantity']);
                }
				$this->iShoppingCartItems[$index]['promotion_requirements'] += $usedQuantity;
				$orderQuantity -= $usedQuantity;
				$orderAmount -= $usedQuantity * $thisItem['sale_price'];
			}
			if ($orderQuantity > 0 || $orderAmount > 0) {
				$this->iErrorMessage = "Minimum product tag quantities not met";
				return false;
			}
		}

# Check to see if product manufacturer requirements are met

		foreach ($promotionRow['promotion_terms_product_manufacturers'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			foreach ($this->iShoppingCartItems as $index => $thisItem) {
				if ($orderAmount <= 0 && $orderQuantity <= 0) {
					break;
				}
				if (array_key_exists($thisItem['product_id'], $excludedProductIds)) {
					continue;
				}
				$excludeProduct = false;
				foreach ($excludedProductDepartmentIds as $departmentId) {
					if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				foreach ($excludedProductCategoryGroupIds as $categoryGroupId) {
					if (ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $categoryGroupId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				$productManufacturerId = getFieldFromId("product_manufacturer_id", "products", "product_id", $thisItem['product_id']);
				if ($productManufacturerId != $row['product_manufacturer_id']) {
					continue;
				}
				$thisItem['quantity'] -= $thisItem['promotion_requirements'];
                if($thisItem['sale_price'] > 0) {
                    $usedQuantity = max(min($orderQuantity, $thisItem['quantity']),
                        min(ceil($orderAmount / $thisItem['sale_price']), $thisItem['quantity']));
                } else {
                    $usedQuantity = min($orderQuantity, $thisItem['quantity']);
                }
				$this->iShoppingCartItems[$index]['promotion_requirements'] += $usedQuantity;
				$orderQuantity -= $usedQuantity;
				$orderAmount -= $usedQuantity * $thisItem['sale_price'];
			}
			if ($orderQuantity > 0 || $orderAmount > 0) {
				$this->iErrorMessage = "Minimum product manufacturer quantities not met";
				return false;
			}
		}

# Check to see if product type requirements are met

		foreach ($promotionRow['promotion_terms_product_types'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			foreach ($this->iShoppingCartItems as $index => $thisItem) {
				if ($orderAmount <= 0 && $orderQuantity <= 0) {
					break;
				}
				if (array_key_exists($thisItem['product_id'], $excludedProductIds)) {
					continue;
				}
				$excludeProduct = false;
				foreach ($excludedProductDepartmentIds as $departmentId) {
					if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				foreach ($excludedProductCategoryGroupIds as $categoryGroupId) {
					if (ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $categoryGroupId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				$productTypeId = getFieldFromId("product_type_id", "products", "product_id", $thisItem['product_id']);
				if ($productTypeId != $row['product_type_id']) {
					continue;
				}
				$thisItem['quantity'] -= $thisItem['promotion_requirements'];
                if($thisItem['sale_price'] > 0) {
                    $usedQuantity = max(min($orderQuantity, $thisItem['quantity']),
                        min(ceil($orderAmount / $thisItem['sale_price']), $thisItem['quantity']));
                } else {
                    $usedQuantity = min($orderQuantity, $thisItem['quantity']);
                }
				$this->iShoppingCartItems[$index]['promotion_requirements'] += $usedQuantity;
				$orderQuantity -= $usedQuantity;
				$orderAmount -= $usedQuantity * $thisItem['sale_price'];
			}
			if ($orderQuantity > 0 || $orderAmount > 0) {
				$this->iErrorMessage = "Minimum product type quantities not met";
				return false;
			}
		}

# Check to see if promotion set requirements are met

		foreach ($promotionRow['promotion_terms_sets'] as $row) {
			$orderAmount = $row['minimum_amount'];
			$orderQuantity = $row['minimum_quantity'];
			foreach ($this->iShoppingCartItems as $index => $thisItem) {
				if ($orderAmount <= 0 && $orderQuantity <= 0) {
					break;
				}
				if (array_key_exists($thisItem['product_id'], $excludedProductIds)) {
					continue;
				}
				$excludeProduct = false;
				foreach ($excludedProductDepartmentIds as $departmentId) {
					if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				foreach ($excludedProductCategoryGroupIds as $categoryGroupId) {
					if (ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $categoryGroupId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				$promotionSetProductId = getFieldFromId("promotion_set_product_id", "promotion_set_products", "product_id", $thisItem['product_id'], "promotion_set_id = ?", $row['promotion_set_id']);
				if (empty($promotionSetProductId)) {
					continue;
				}
				$thisItem['quantity'] -= $thisItem['promotion_requirements'];
                if($thisItem['sale_price'] > 0) {
                    $usedQuantity = max(min($orderQuantity, $thisItem['quantity']),
                        min(ceil($orderAmount / $thisItem['sale_price']), $thisItem['quantity']));
                } else {
                    $usedQuantity = min($orderQuantity, $thisItem['quantity']);
                }
				$this->iShoppingCartItems[$index]['promotion_requirements'] += $usedQuantity;
				$orderQuantity -= $usedQuantity;
				$orderAmount -= $usedQuantity * $thisItem['sale_price'];
			}
			if ($orderQuantity > 0 || $orderAmount > 0) {
				$this->iErrorMessage = "Minimum product quantities not met";
				return false;
			}
		}
		return true;
	}

	function getContact() {
		return $this->iShoppingCart['contact_id'];
	}

	function assimilateShoppingCart($shoppingCartId) {
		$resultSet = executeQuery("select * from shopping_cart_items where shopping_cart_id = ?", $shoppingCartId);
		while ($row = getNextRow($resultSet)) {
			$shoppingCartItemId = getFieldFromId("shopping_cart_item_id", "shopping_cart_items", "shopping_cart_id", $this->iShoppingCartId, "product_id = ?", $row['product_id']);
			if (empty($shoppingCartItemId)) {
				executeQuery("update shopping_cart_items set shopping_cart_id = ? where shopping_cart_item_id = ?", $this->iShoppingCartId, $row['shopping_cart_item_id']);
			} else {
				$this->addItem($row);
			}
		}
		executeQuery("delete from shopping_cart_item_addons where shopping_cart_item_id in (select shopping_cart_item_id from shopping_cart_items where shopping_cart_id = ?)", $shoppingCartId);
		executeQuery("delete from shopping_cart_items where shopping_cart_id = ?", $shoppingCartId);
		executeQuery("update product_map_overrides set shopping_cart_id = ? where shopping_cart_id = ?", $this->iShoppingCartId, $shoppingCartId);
		executeQuery("delete from product_map_overrides where shopping_cart_id = ?", $shoppingCartId);
		executeQuery("delete from shopping_carts where shopping_cart_id = ?", $shoppingCartId);
		setCoreCookie("shopping_cart_id", "", -1);
	}

	/**
	 * addItem - add this product to the shopping cart. Update existing item with same product ONLY if the item has no addons
	 * @param
	 *    product ID
	 *    quantity - if not set, the quantity is one
	 *    set_quantity - if false, add the quantity to the existing quantity. The default is false.
	 * @return
	 *    none
	 */
	function addItem($parameters) {
		if (!is_array($parameters)) {
			$parameters = array("product_id" => $parameters);
		}
		$productRow = ProductCatalog::getCachedProductRow($parameters['product_id'], array("client_id" => (array_key_exists("client_id", $parameters) ? $parameters['client_id'] : $GLOBALS['gClientId'])));
		if (!is_array($productRow)) {
			$productRow = array();
		} else {
			$productDataRow = getRowFromId("product_data", "product_id", $productRow['product_id']);
			if (!is_array($productDataRow)) {
				$productDataRow = array();
			}
			$productRow = array_merge($productRow, $productDataRow);
		}
		if (empty($productRow)) {
			$this->iErrorMessage = "Invalid product";
			return false;
		}
		if (!empty($productRow['product_type_id']) && empty($parameters['allow_order_upsell_products'])) {
			$orderUpsellProductTypeId = getCachedData("order_upsell_product_type_id", "");
			if ($orderUpsellProductTypeId === false) {
				$orderUpsellProductTypeId = getFieldFromId("product_type_id", "product_types", "product_type_code", "order_upsell_product");
				if (empty($orderUpsellProductTypeId)) {
					$orderUpsellProductTypeId = 0;
				}
				setCachedData("order_upsell_product_type_id", "", $orderUpsellProductTypeId, 168);
			}
			if (!empty($orderUpsellProductTypeId) && $productRow['product_type_id'] == $orderUpsellProductTypeId) {
				$this->iErrorMessage = "Invalid product";
				return false;
			}
		}
		if (!$GLOBALS['gLoggedIn'] && !$GLOBALS['gCommandLine'] && getPreference("RETAIL_STORE_NO_GUEST_CART")) {
			$this->iErrorMessage = "Login to purchase";
			return false;
		}
		if (function_exists("_localAddToCartCheck")) {
			$result = _localAddToCartCheck($parameters, $this->iShoppingCartItems);
			if ($result !== true) {
				$this->iErrorMessage = $result['error_message'];
				return false;
			}
		}
		$userGroupIds = array();
		if ($GLOBALS['gLoggedIn']) {
			$groupSet = executeQuery("select * from user_group_members where user_id = ?", $GLOBALS['gUserId']);
			while ($groupRow = getNextRow($groupSet)) {
				$userGroupIds[] = $groupRow['user_group_id'];
			}
		}
		if (!empty($productRow['user_group_id'])) {
			if (!in_array($productRow['user_group_id'], $userGroupIds)) {
				$this->iErrorMessage = (empty($productRow['error_message']) ? "Product unavailable" : $productRow['error_message']);
				return false;
			}
		}
		$resultSet = executeQuery("select user_group_id from product_categories where user_group_id is not null and product_category_id in (select product_category_id from product_category_links where product_id = ?)", $productRow['product_id']);
		while ($row = getNextRow($resultSet)) {
			if (!in_array($row['user_group_id'], $userGroupIds)) {
				$this->iErrorMessage = (empty($productRow['error_message']) ? "Product unavailable" : $productRow['error_message']);
				return false;
			}
		}
		$productId = $productRow['product_id'];
		if (!empty($productRow['order_maximum']) && $GLOBALS['gLoggedIn']) {
			$resultSet = executeQuery("select sum(quantity) from order_items where deleted = 0 and product_id = ? and order_id in (select order_id from orders where deleted = 0 and contact_id = ?)",
				$productId, $GLOBALS['gUserRow']['contact_id']);
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
            if(is_numeric($productRow['cart_maximum']) && $productRow['cart_maximum'] <= 0) {
                $this->iErrorMessage = "Maximum order quantity reached";
                return false;
            }
		}
        if (!array_key_exists("quantity", $parameters) || !is_numeric($parameters['quantity'])) {
			$parameters['quantity'] = 1;
		}
		if (!array_key_exists("sale_price", $parameters)) {
			$salePrice = false;
			foreach ($this->iShoppingCartItems as $index => $thisItem) {
				if ($thisItem['product_id'] == $productId && empty($parameters['has_addons']) && $thisItem['addon_count'] == 0 && empty($thisItem['product_addons'])) {
					$salePrice = $thisItem['sale_price'];
				}
			}
			if ($salePrice === false) {
				$productCatalog = new ProductCatalog();
				$salePriceInfo = $productCatalog->getProductSalePrice($parameters['product_id'], array("product_information" => $productRow, "shopping_cart_id" => $this->iShoppingCartId, "no_cache" => true));
				$salePrice = $salePriceInfo['sale_price'];
			}
			if ($salePrice === false) {
				$this->iErrorMessage = "No Sale Price Found";
				return false;
			}
		} else {
			$salePrice = $parameters['sale_price'];
		}
		$shoppingCartItemIndex = false;

		if ($this->iAlwaysAddProducts === false) {
			$this->iAlwaysAddProducts = array();
			$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "ALWAYS_ADD", "inactive = 0 and " .
				"custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'PRODUCTS')");
			$alwaysAddSet = executeQuery("select primary_identifier from custom_field_data where custom_field_id = ? and text_data = '1'", $customFieldId);
			while ($alwaysAddRow = getNextRow($alwaysAddSet)) {
				$this->iAlwaysAddProducts[$alwaysAddRow['primary_identifier']] = $alwaysAddRow['primary_identifier'];
			}
			freeResult($alwaysAddSet);
		}

		if (empty($parameters['has_addons']) && !array_key_exists($productId, $this->iAlwaysAddProducts)) {
			foreach ($this->iShoppingCartItems as $index => $thisItem) {
				if ($productId != $thisItem['product_id'] || (!getPreference("IGNORE_CART_PRICE") && $salePrice != $thisItem['sale_price']) || $thisItem['location_id'] != $parameters['location_id'] || $thisItem['serial_number'] != $parameters['serial_number'] || !empty($thisItem['addon_count']) || !empty($thisItem['product_addons'])) {
					continue;
				}
				$shoppingCartItemIndex = $index;
				break;
			}
		}

		$originalQuantity = ($shoppingCartItemIndex === false ? 0 : $this->iShoppingCartItems[$shoppingCartItemIndex]['quantity']);
		$quantity = ($parameters['set_quantity'] ? $parameters['quantity'] : $originalQuantity + $parameters['quantity']);
		if (!empty($productRow['cart_maximum'])) {
			$cartProductQuantity = $this->getProductQuantity($productId);
			if (($cartProductQuantity + ($quantity - $originalQuantity)) > $productRow['cart_maximum']) {
				$quantity = $productRow['cart_maximum'] - ($cartProductQuantity + ($quantity - $originalQuantity));
			}
		}
		if (!empty($productRow['cart_minimum']) && $productRow['cart_minimum'] > 0 && $quantity < $productRow['cart_minimum']) {
			$quantity = $productRow['cart_minimum'];
		}
		if ($shoppingCartItemIndex === false) {
			if (function_exists("array_key_last")) {
				$lastIndex = array_key_last($this->iShoppingCartItems);
			} else {
				end($this->iShoppingCartItems);
				$lastIndex = key($this->iShoppingCartItems);
			}
			$lastIndex++;
			$this->iShoppingCartItems[$lastIndex] = array("shopping_cart_item_id" => "", "product_id" => $productId, "location_id" => $parameters['location_id'], "serial_number" => $parameters['serial_number'], "quantity" => $quantity, "sale_price" => $salePrice, "addon_count" => 0);
			$shoppingCartItemIndex = $lastIndex;
		} else {
			$this->iShoppingCartItems[$shoppingCartItemIndex]['quantity'] = $quantity;
			$this->iShoppingCartItems[$shoppingCartItemIndex]['time_submitted'] = date("Y-m-d H:i:s");
		}
		$totalQuantity = 0;
		foreach ($this->iShoppingCartItems as $index => $thisItem) {
			$totalQuantity += $thisItem['quantity'];
		}
		$cartMaximumExceeded = false;
		$cartOverage = 0;
		$resultSet = executeQuery("select * from product_departments where cart_maximum is not null and cart_maximum > 0 and (product_department_id in " .
			"(select product_department_id from product_category_departments where product_category_id in (select product_category_id from product_category_links where product_id = ?)) or " .
			"product_department_id in (select product_department_id from product_category_group_departments where product_category_group_id in (select product_category_group_id from " .
			"product_category_group_links where product_category_id in (select product_category_id from product_category_links where product_id = ?))))", $productId, $productId);
		while ($row = getNextRow($resultSet)) {
			if ($row['cart_maximum'] >= $totalQuantity) {
				continue;
			}
			$cartTotal = 0;
			foreach ($this->iShoppingCartItems as $index => $thisItem) {
				$thisProductId = $thisItem['product_id'];
				if ($thisProductId == $productId) {
					$cartTotal += $thisItem['quantity'];
					continue;
				}
				$checkSet = executeQuery("select * from product_departments where product_department_id = ? and cart_maximum is not null and cart_maximum > 0 and (product_department_id in " .
					"(select product_department_id from product_category_departments where product_category_id in (select product_category_id from product_category_links where product_id = ?)) or " .
					"product_department_id in (select product_department_id from product_category_group_departments where product_category_group_id in (select product_category_group_id from " .
					"product_category_group_links where product_category_id in (select product_category_id from product_category_links where product_id = ?))))", $row['product_department_id'], $thisProductId, $thisProductId);
				if ($checkSet['row_count'] > 0) {
					$cartTotal += $thisItem['quantity'];
				}
			}
			if ($cartTotal > $row['cart_maximum']) {
				$cartMaximumExceeded = true;
				$cartOverage = max($cartTotal - $row['cart_maximum'], $cartOverage);
				break;
			}
		}
		if (!$cartMaximumExceeded) {
			$resultSet = executeQuery("select * from product_category_groups where cart_maximum is not null and cart_maximum > 0 and product_category_group_id in " .
				"(select product_category_group_id from product_category_group_links where product_category_id in (select product_category_id from product_category_links " .
				"where product_id = ?))", $productId);
			while ($row = getNextRow($resultSet)) {
				if ($row['cart_maximum'] >= $totalQuantity) {
					continue;
				}
				$cartTotal = 0;
				foreach ($this->iShoppingCartItems as $index => $thisItem) {
					$thisProductId = $thisItem['product_id'];
					if ($thisProductId == $productId) {
						$cartTotal += $thisItem['quantity'];
						continue;
					}
					$checkSet = executeQuery("select * from product_departments where product_department_id = ? and cart_maximum is not null and cart_maximum > 0 and (product_department_id in " .
						"(select product_department_id from product_category_departments where product_category_id in (select product_category_id from product_category_links where product_id = ?)) or " .
						"product_department_id in (select product_department_id from product_category_group_departments where product_category_group_id in (select product_category_group_id from " .
						"product_category_group_links where product_category_id in (select product_category_id from product_category_links where product_id = ?))))", $row['product_department_id'], $thisProductId, $thisProductId);
					if ($checkSet['row_count'] > 0) {
						$cartTotal += $thisItem['quantity'];
					}
				}
				if ($cartTotal > $row['cart_maximum']) {
					$cartMaximumExceeded = true;
					$cartOverage = max($cartTotal - $row['cart_maximum'], $cartOverage);
					break;
				}
			}
		}
		if ($cartMaximumExceeded) {
			$this->iShoppingCartItems[$shoppingCartItemIndex]['quantity'] = max(0, $this->iShoppingCartItems[$shoppingCartItemIndex]['quantity'] - $cartOverage);
		}

		$this->iShoppingCart['last_activity'] = date("Y-m-d H:i:s");
		if (!$this->writeShoppingCart()) {
			return false;
		} else {
			return $this->iShoppingCartItems[$shoppingCartItemIndex]['shopping_cart_item_id'];
		}
	}

# We don't write the shopping cart to the database until something is added to the shopping cart. If the shopping cart is written and it is anonymous, we need to set the cookie.

	function setValues($parameters) {
		foreach ($parameters as $fieldName => $fieldData) {
			if ($fieldName == "shopping_cart_id") {
				continue;
			}
			$this->iShoppingCart[$fieldName] = $fieldData;
		}
		return $this->writeShoppingCart();
	}

	function getShoppingCartItemId($productId) {
		if (empty($productId)) {
			return false;
		}
		foreach ($this->iShoppingCartItems as $index => $thisItem) {
			if ($productId == $thisItem['product_id']) {
				return $thisItem['shopping_cart_item_id'];
			}
		}
		return false;
	}

	public function checkInventoryLevels() {
		$neverOutOfStock = getPreference("RETAIL_STORE_NEVER_OUT_OF_STOCK");
		if (!empty($neverOutOfStock)) {
			return true;
		}
		$productCatalog = new ProductCatalog();
		$productIds = array();
		foreach ($this->iShoppingCartItems as $index => $thisItem) {
			if (!in_array($thisItem['product_id'], $productIds)) {
				$productIds[] = $thisItem['product_id'];
			}
		}
		$inventoryCounts = $productCatalog->getInventoryCounts(true, $productIds);
		$allQuantitiesAvailable = true;

		# Check total inventory levels for each product included in the shopping cart. Make sure to check total of each product in cart, not just the individual line.

		$foundProductIds = array();
		foreach ($this->iShoppingCartItems as $index => $thisItem) {
			if ($inventoryCounts[$thisItem['product_id']] < $thisItem['quantity']) {
				$allQuantitiesAvailable = false;
				if (in_array($thisItem['product_id'], $foundProductIds) || $inventoryCounts[$thisItem['product_id']] > 0) {
					$this->updateItem($thisItem['shopping_cart_item_id'], array("quantity" => $inventoryCounts[$thisItem['product_id']]));
				}
			} else {
				$inventoryCounts[$thisItem['product_id']] -= $thisItem['quantity'];
			}
			$foundProductIds[] = $thisItem['product_id'];
		}
		return $allQuantitiesAvailable;
	}

	function getShoppingCartId() {
		return $this->iShoppingCartId;
	}

	function getShippingCalculationLog() {
		return $this->iShippingCalculationLog;
	}

	function updateItem($shoppingCartItemId, $parameters) {
		if (!is_array($parameters) && is_numeric($parameters)) {
			$parameters = array("quantity" => $parameters);
		}
		$shoppingCartItemIndex = false;
		foreach ($this->iShoppingCartItems as $index => $thisItem) {
			if ($shoppingCartItemId == $thisItem['shopping_cart_item_id']) {
				$shoppingCartItemIndex = $index;
				break;
			}
		}
		if ($shoppingCartItemIndex === false) {
			$this->iErrorMessage = "Item Not Found";
			return false;
		}
		foreach ($parameters as $fieldName => $fieldData) {
			if ($fieldName == "shopping_cart_item_id") {
				continue;
			}
			$this->iShoppingCartItems[$shoppingCartItemIndex][$fieldName] = $fieldData;
		}

		foreach ($parameters as $fieldName => $fieldValue) {
			if (substr($fieldName, 0, strlen("product_addon_")) == "product_addon_") {
				$productAddonId = substr($fieldName, strlen("product_addon_"));
				$productAddonRow = getRowFromId("product_addons", "product_addon_id", $productAddonId, "product_id = ? and inactive = 0" . ($GLOBALS['gUserRow']['administrator_flag'] ? "" : " and internal_use_only = 0"), $this->iShoppingCartItems[$shoppingCartItemIndex]['product_id']);
				if (!empty($productAddonRow['inventory_product_id'])) {
					$productCatalog = new ProductCatalog();
					$addonInventoryCounts = $productCatalog->getInventoryCounts(true, $productAddonRow['inventory_product_id']);
					if (empty($addonInventoryCounts[$productAddonRow['inventory_product_id']]) || $addonInventoryCounts[$productAddonRow['inventory_product_id']] < 0) {
						$productAddonRow = false;
					}
				}
				if (!empty($productAddonRow)) {
					if (empty($fieldValue) || !is_numeric($fieldValue) || $fieldValue < 0) {
						unset($this->iShoppingCartItems[$shoppingCartItemIndex]['product_addons'][$productAddonId]);
					} else {
						$quantity = min($productAddonRow['maximum_quantity'], $fieldValue);
						if (empty($quantity) || !is_numeric($quantity) || $quantity <= 0) {
							$quantity = 1;
						}
						if (!is_array($this->iShoppingCartItems[$shoppingCartItemIndex]['product_addons'])) {
							$this->iShoppingCartItems[$shoppingCartItemIndex]['product_addons'] = array();
						}
						$this->iShoppingCartItems[$shoppingCartItemIndex]['product_addons'][$productAddonId] = array("product_addon_id" => $productAddonId, "quantity" => $quantity, "sale_price" => $productAddonRow['sale_price']);

						# Remove product addons that are in the same group, as only one should be selected.

						if (!empty($productAddonRow['group_description'])) {
							$resultSet = executeQuery("select * from product_addons where product_id = ? and group_description = ? and product_addon_id <> ?",
								$this->iShoppingCartItems[$shoppingCartItemIndex]['product_id'], $productAddonRow['group_description'], $productAddonRow['product_addon_id']);
							while ($row = getNextRow($resultSet)) {
								if (array_key_exists($row['product_addon_id'], $this->iShoppingCartItems[$shoppingCartItemIndex]['product_addons'])) {
									unset($this->iShoppingCartItems[$shoppingCartItemIndex]['product_addons'][$row['product_addon_id']]);
								}
							}
						}

						$this->iShoppingCartItems[$shoppingCartItemIndex]['addon_count'] = count($this->iShoppingCartItems[$shoppingCartItemIndex]['product_addons']);
					}
				}
			}
		}
		$thatItem = $this->iShoppingCartItems[$shoppingCartItemIndex];

		if ($thatItem['addon_count'] == 0 && empty($thisItem['product_addons'])) {
			foreach ($this->iShoppingCartItems as $index => $thisItem) {
				if ($index == $shoppingCartItemIndex) {
					continue;
				}
				if ($thisItem['product_id'] == $thatItem['product_id'] && $thisItem['location_id'] == $thatItem['location_id'] &&
					$thisItem['serial_number'] == $thatItem['serial_number'] && $thisItem['sale_price'] == $thatItem['sale_price'] and $thisItem['addon_count'] == 0 && empty($thisItem['product_addons'])) {
					$this->iShoppingCartItems[$index]['quantity'] += $thatItem['quantity'];
					$this->iShoppingCartItems[$shoppingCartItemIndex]['quantity'] = 0;
					break;
				}
			}
		}
		$this->iShoppingCart['last_activity'] = date("Y-m-d H:i:s");
		return $this->writeShoppingCart();
	}

	/**
	 * removeItem - remove this shopping cart item from the shopping cart
	 * @param
	 *    Shopping Cart Item ID
	 * @return
	 *    none
	 */
	function removeItem($shoppingCartItemId) {
		foreach ($this->iShoppingCartItems as $index => $thisItem) {
			if ($thisItem['shopping_cart_item_id'] == $shoppingCartItemId) {
				$this->iShoppingCartItems[$index]['quantity'] = 0;
				$this->iShoppingCart['last_activity'] = date("Y-m-d H:i:s");
				$this->writeShoppingCart();
				break;
			}
		}
	}

	/**
	 * removeAllItems - remove all items from the shopping cart
	 * @param
	 *    none
	 * @return
	 *    none
	 */
	function removeAllItems() {
		foreach ($this->iShoppingCartItems as $index => $thisItem) {
			$this->iShoppingCartItems[$index]['quantity'] = 0;
		}
		$this->iShoppingCart['last_activity'] = date("Y-m-d H:i:s");
		return $this->writeShoppingCart();
	}

	function removeMapOverrides() {
		executeQuery("delete from product_map_overrides where shopping_cart_id = ?", $this->iShoppingCartId);
	}

	function getUser() {
		return $this->iShoppingCart['user_id'];
	}

	/**
	 * getProductQuantity - Return the count of the given product in the cart
	 * @param
	 *    product ID
	 * @return
	 *    quantity
	 */
	function getProductQuantity($productId, $shoppingCartItemId = false) {
		$quantity = 0;
		foreach ($this->iShoppingCartItems as $shoppingCartItem) {
			if (empty($shoppingCartItemId)) {
				if ($shoppingCartItem['product_id'] == $productId && empty($shoppingCartItem['product_addons'])) {
					$quantity += $shoppingCartItem['quantity'];
				}
			} else {
				if ($shoppingCartItem['shopping_cart_item_id'] == $shoppingCartItemId) {
					$quantity += $shoppingCartItem['quantity'];
					break;
				}
			}
		}
		return $quantity;
	}

	function addAdditionalCharges($shoppingCartItemId, $additionalCharges) {
		foreach ($this->iShoppingCartItems as $index => $shoppingCartItem) {
			if ($shoppingCartItem['shopping_cart_item_id'] == $shoppingCartItemId) {
				$shoppingCartItem['additional_charges'] = $additionalCharges;
				$this->iShoppingCartItems[$index] = $shoppingCartItem;
				break;
			}
		}
	}

	/**
	 * getShoppingCartItemsTotal - Return the number of items in the shopping cart.
	 * @param
	 *    none
	 * @return
	 *    count of items in shopping cart
	 */
	function getShoppingCartItemsCount() {
		$itemCount = 0;
		foreach ($this->iShoppingCartItems as $index => $thisItem) {
			$itemCount += $this->iShoppingCartItems[$index]['quantity'];
		}
		return $itemCount;
	}

	/**
	 * getErrorMessage - return the error message from the most recent error
	 * @param
	 *    none
	 * @return
	 *    error message
	 */
	function getErrorMessage() {
		return $this->iErrorMessage;
	}

	/**
	 * isInCart - searches the itemsInCart array for a product_id
	 * @param
	 *    product ID
	 * @return
	 *    true if product ID is in array
	 */
	function isInCart($productId) {
		foreach ($this->iShoppingCartItems as $shoppingCartItem) {
			if ($shoppingCartItem['product_id'] == $productId) {
				return true;
			}
		}
		return false;
	}

    function getShoppingCartItem($parameters) {
        foreach ($this->iShoppingCartItems as $shoppingCartItem) {
            if($shoppingCartItem['shopping_cart_item_id'] == $parameters['shopping_cart_item_id']) {
                return $shoppingCartItem;
            } elseif ($shoppingCartItem['product_id'] == $parameters['product_id']) {
                return $shoppingCartItem;
            }
        }
        return false;
    }

	function getPromotionId() {
		return $this->iPromotionId;
	}

	function getPromotionCode() {
		return $this->iShoppingCart['promotion_code'];
	}

	function applyPromotionId($promotionId, $addRequirements = false) {
		$this->iPromotionId = getFieldFromId("promotion_id", "promotions", "promotion_id", $promotionId, "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
		if (empty($this->iPromotionId)) {
			$this->iPromotionId = "";
			$this->iShoppingCart['last_activity'] = date("Y-m-d H:i:s");
			$this->iShoppingCart['promotion_code'] = "";
			$this->writeShoppingCart();
			return true;
		}
		if ($this->isPromotionValid("", "", $addRequirements)) {
			$this->iShoppingCart['last_activity'] = date("Y-m-d H:i:s");
			if (!empty($this->iShoppingCart['promotion_code'])) {
				$promotionCode = getFieldFromId("promotion_code", "promotions", "promotion_id", $this->iPromotionId);
				if ($promotionCode != $this->iShoppingCart['promotion_code']) {
					$promotionCode = getFieldFromId("promotion_code", "one_time_use_promotion_codes", "promotion_id", $this->iPromotionId, "promotion_code = ?", $this->iShoppingCart['promotion_code']);
					if ($promotionCode != $this->iShoppingCart['promotion_code']) {
						$this->iShoppingCart['promotion_code'] = "";
					}
				}
			}
			$this->writeShoppingCart();
			return true;
		}
		return false;
	}

	function isOneTimeUsePromotionCode() {
		return $this->iIsOneTimeUsePromotion;
	}

	function applyPromotionCode($promotionCode, $addRequirements = false) {
		$this->iIsOneTimeUsePromotion = false;
		$this->iPromotionId = getFieldFromId("promotion_id", "promotions", "promotion_code", $promotionCode, "inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0"));
		if (empty($this->iPromotionId)) {
			$this->iPromotionId = getFieldFromId("promotion_id", "one_time_use_promotion_codes", "promotion_code", $promotionCode,
				"order_id is null and promotion_id in (select promotion_id from promotions where inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . ")");
			if (!empty($this->iPromotionId)) {
				$this->iIsOneTimeUsePromotion = true;
			}
		}
		if (empty($this->iPromotionId)) {
			$this->iPromotionId = "";
			$this->iShoppingCart['promotion_code'] = "";
			$this->iShoppingCart['last_activity'] = date("Y-m-d H:i:s");
			$this->writeShoppingCart();
			$this->iErrorMessage = "Invalid Promotion Code";
			return false;
		}
		if (!empty($this->iPromotionId) && $this->isPromotionValid("", "", $addRequirements)) {
			$this->iShoppingCart['promotion_code'] = $promotionCode;
			$this->iShoppingCart['last_activity'] = date("Y-m-d H:i:s");
			$this->writeShoppingCart();
			return true;
		}
		return false;
	}

	function removePromotion() {
		$this->iPromotionId = "";
		$this->iShoppingCart['promotion_code'] = $promotionCode;
		$this->iShoppingCart['last_activity'] = date("Y-m-d H:i:s");
		$this->writeShoppingCart();
	}

	function setContactTypeId($contactTypeId) {
		$this->iContactTypeId = $contactTypeId;
	}

	function requiresUser() {
		$noGuestCheckout = getPreference("RETAIL_STORE_NO_GUEST_CHECKOUT");
		if (!empty($noGuestCheckout)) {
			return true;
		}
		foreach ($this->iShoppingCartItems as $index => $thisItem) {
			$virtualProduct = getFieldFromId("virtual_product", "products", "product_id", $thisItem['product_id']);
			$fileId = getFieldFromId("file_id", "products", "product_id", $thisItem['product_id']);
			if (!empty($virtualProduct) && !empty($fileId)) {
				return true;
			}
			$requiresUser = getFieldFromId("requires_user", "products", "product_id", $thisItem['product_id']);
			if (!empty($requiresUser)) {
				return true;
			}
		}
		return false;
	}

	function getCartDiscount() {
		if ($this->isPromotionValid()) {
			$promotionRow = ShoppingCart::getCachedPromotionRow($this->iPromotionId);
			return array("discount_amount" => $promotionRow['discount_amount'], "discount_percent" => $promotionRow['discount_percent']);
		}
		return array("discount_amount" => 0, "discount_percent" => 0);
	}

	function sortLocations($a, $b) {
		if ($a['count'] == $b['count']) {
			return 0;
		}
		return ($a['count'] > $b['count'] ? -1 : 1);
	}

	function sortCartItems($a, $b) {
		if ($a['sale_price'] == $b['sale_price']) {
			return 0;
		}
		return ($a['sale_price'] > $b['sale_price'] ? 1 : -1);
	}

	function sortCartItemsDesc($a, $b) {
		if ($a['sale_price'] == $b['sale_price']) {
			return 0;
		}
		return ($a['sale_price'] < $b['sale_price'] ? 1 : -1);
	}

	function getShippingOptions($countryId = "", $state = "", $postalCode = "", $fullAddress = array()) {
		if (empty($countryId)) {
			$countryId = $GLOBALS['gUserRow']['country_id'];
		}
		if (empty($countryId)) {
			$countryId = 1000;
		}
		if (empty($state) && !empty($postalCode)) {
			$state = getFieldFromId("state", "postal_codes", "postal_code", $postalCode);
		}
		if (empty($this->iShoppingCart['contact_id'])) {
			$contactRow = $GLOBALS['gUserRow'];
		} else {
			$resultSet = executeQuery("select * from contacts left outer join users using (contact_id) where contacts.contact_id = ?", $this->iShoppingCart['contact_id']);
			$contactRow = getNextRow($resultSet);
		}
		if (empty($state)) {
			$state = $contactRow['state'];
		}
		if (empty($postalCode)) {
			$postalCode = $contactRow['postal_code'];
		}
		if (strlen($postalCode) > 5 && $countryId == 1000) {
			$postalCode = substr($postalCode, 0, 5);
		}
		$shippingCalculationLog = "";

# make sure there are no restricted products for this location

		$pickupOnly = false;
		$allVirtualProduct = true;
		$orderTotal = 0;
		foreach ($this->iShoppingCartItems as $index => $thisItem) {
			$productFields = getMultipleFieldsFromId(array("virtual_product", "cannot_dropship"), "products", "product_id", $thisItem['product_id']);
			$virtualProduct = $productFields['virtual_product'];
			if (empty($virtualProduct)) {
				$allVirtualProduct = false;
			}
			$cannotDropship = $productFields['cannot_dropship'];
			if (!empty($cannotDropship)) {
				$shippingCalculationLog .= "Product ID " . $thisItem['product_id'] . " is pickup Only\n";
				$pickupOnly = true;
			} else {
				$productCategoryId = getFieldFromId("product_category_id", "product_category_links", "product_id", $thisItem['product_id'],
					"product_category_id in (select product_category_id from product_categories where cannot_dropship = 1)");
				if (!empty($productCategoryId)) {
					$pickupOnly = true;
				}
			}
			$ignoreProductRestrictions = CustomField::getCustomFieldData($contactRow['contact_id'], "IGNORE_PRODUCT_RESTRICTIONS");
			if (empty($ignoreProductRestrictions)) {
				$ignoreStateRestriction = CustomField::getCustomFieldData($thisItem['product_id'], "IGNORE_RESTRICTIONS_" . strtoupper($state), "PRODUCTS");
				if (!empty($ignoreStateRestriction)) {
					$ignoreProductRestrictions = true;
				}
			}
			if (empty($ignoreProductRestrictions)) {
				$productRestrictionId = getFieldFromId("product_restriction_id", "product_restrictions", "product_id", $thisItem['product_id'],
					"country_id = ? and (state is null or state = ?) and (postal_code is null or postal_code = ?)" .
					($countryId == 1000 ? " and (state is not null or postal_code is not null)" : ""), $countryId, $state, $postalCode);
				if (empty($productRestrictionId)) {
					$productRestrictionId = getFieldFromId("product_category_restriction_id", "product_category_restrictions", "country_id", $countryId,
						"(state is null or state = ?) and (postal_code is null or postal_code = ?) and product_category_id in (select product_category_id from product_category_links where product_id = ?)",
						$state, $postalCode, $thisItem['product_id']);
				}
				if (empty($productRestrictionId)) {
					$productRestrictionId = getFieldFromId("product_department_restriction_id", "product_department_restrictions", "country_id", $countryId,
						"(state is null or state = ?) and (postal_code is null or postal_code = ?) and product_department_id in (select product_department_id from product_category_departments " .
						"where product_category_id in (select product_category_id from product_category_links where product_id = ?))",
						$state, $postalCode, $thisItem['product_id']);
				}
			} else {
				$productRestrictionId = "";
			}
			if (!empty($productRestrictionId)) {
				$this->iErrorMessage = "The product '" . getFieldFromId("description", "products", "product_id", $thisItem['product_id']) . "' cannot be shipped to this location: " .
					" State: " . $state . ", Postal Code: " . $postalCode . ($countryId == 1000 ? "" : ", Country: " . getFieldFromId("country_name", "countries", "country_id", $countryId));
				return false;
			}
			$orderTotal += ($thisItem['sale_price'] * $thisItem['quantity']);
		}
		if ($allVirtualProduct) {
			return array(array("shipping_method_id" => "-1", "shipping_charge" => "0.00", "description" => "None Required"));
		}

# Create an array for all the products that are to be shipped

		$fixedCharges = 0;
		$productIds = array();
		$productDataRows = array();
		$shippingProducts = array();
		foreach ($this->iShoppingCartItems as $index => $thisItem) {
			$productDataRows[$thisItem['product_id']] = getRowFromId("product_data", "product_id", $thisItem['product_id']);
			$productIds[] = $thisItem['product_id'];
			$shippingProducts[$thisItem['shopping_cart_item_id']] = array("product_id" => $thisItem['product_id'], "quantity" => $thisItem['quantity']);
			if (array_key_exists("promotional_price", $thisItem)) {
				$shippingProducts[$thisItem['shopping_cart_item_id']]['promotional_price'] = true;
			}
			$shippingCalculationLog .= "Shipping " . $thisItem['quantity'] . " of product ID " . $thisItem['product_id'] . "\n";
		}
		$shippingCalculationLog .= "\n";

# get Fixed shipping charges for individual products and remove from shipping Products

		$removedProductIds = array();

		foreach ($shippingProducts as $index => $thisProductInfo) {
			$shippingPrice = getFieldFromId("price", "product_prices", "product_id", $thisProductInfo['product_id'], "product_price_type_id = " .
				"(select product_price_type_id from product_price_types where product_price_type_code = 'SHIPPING_CHARGE' and (start_date is null or start_date <= current_date) and " .
				"(end_date is null or end_date >= current_date) and client_id = ?)" .
				(empty($contactRow['user_type_id']) ? " and user_type_id is null" : " and (user_type_id is null or user_type_id = " . $contactRow['user_type_id'] . ")"), $GLOBALS['gClientId']);
			if (strlen($shippingPrice) > 0) {
				if ($thisProductInfo['quantity'] > 1) {
					$additionalShippingPrice = getFieldFromId("price", "product_prices", "product_id", $thisProductInfo['product_id'], "product_price_type_id = " .
						"(select product_price_type_id from product_price_types where product_price_type_code = 'ADDITIONAL_SHIPPING_CHARGE' and (start_date is null or start_date <= current_date) and " .
						"(end_date is null or end_date >= current_date) and client_id = ?)" .
						(empty($contactRow['user_type_id']) ? " and user_type_id is null" : " and (user_type_id is null or user_type_id = " . $contactRow['user_type_id'] . ")"), $GLOBALS['gClientId']);
					if (strlen($additionalShippingPrice) == 0) {
						$additionalShippingPrice = $shippingPrice;
					}
				} else {
					$additionalShippingPrice = $shippingPrice;
				}
				$thisFixedCharge = $shippingPrice + ($additionalShippingPrice * ($thisProductInfo['quantity'] - 1));
				$fixedCharges += $thisFixedCharge;
				$shippingCalculationLog .= "Flat rate of " . number_format($thisFixedCharge, 2) . " for product ID " . $thisProductInfo['product_id'] . "\n";
				$removedProductIds[] = $thisProductInfo['product_id'];
				unset($shippingProducts[$index]);
				continue;
			}
			$shippingPrice = getFieldFromId("shipping_charge", "product_manufacturers", "product_manufacturer_id", getFieldFromId("product_manufacturer_id", "products", "product_id", $thisProductInfo['product_id']));
			if (strlen($shippingPrice) > 0) {
				$fixedCharges += ($shippingPrice * $thisProductInfo['quantity']);
				$shippingCalculationLog .= "Flat rate of " . number_format($shippingPrice, 2) . " for each of product ID " . $thisProductInfo['product_id'] . " from manufacturer\n";
				$removedProductIds[] = $thisProductInfo['product_id'];
				unset($shippingProducts[$index]);
				continue;
			}
		}

		if ($fixedCharges > 0) {
			$shippingCalculationLog .= "Total flat rate charges of " . number_format($fixedCharges, 2) . "\n\n";
		}
		$originalFixedCharged = $fixedCharges;
		$originalRemovedProductIds = $removedProductIds;
		$originalShippingProducts = $shippingProducts;

		$totalCartWeight = $this->getCartShipWeight($removedProductIds);
		$totalCartWeight = round($totalCartWeight, 2);
		$shippingCalculationLog .= "Total weight of cart is " . number_format($totalCartWeight, 2) . "\n\n";
		$shippingRates = array();
		$returnArray = array();
		$signatureRequiredDepartmentCodes = getPreference("EASYPOST_SIGNATURE_REQUIRED_DEPARTMENTS");
		$signatureRequiredDepartmentIds = array();
		if (!empty($signatureRequiredDepartmentCodes)) {
			foreach (explode(",", $signatureRequiredDepartmentCodes) as $departmentCode) {
				$signatureRequiredDepartmentIds[] = getFieldFromId("product_department_id", "product_departments", "product_department_code", $departmentCode);
			}
		}
		$hasPromotion = $this->isPromotionValid();
		if (function_exists("_localGetPossibleShippingMethods")) {
			$shippingMethodRows = _localGetPossibleShippingMethods();
		} else {
			$resultSet = executeQuery("select * from shipping_methods where client_id = ? and inactive = 0" .
				($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " order by sort_order,description", $GLOBALS['gClientId']);
			$shippingMethodRows = array();
			while ($row = getNextRow($resultSet)) {
				$shippingMethodRows[] = $row;
			}
		}
		foreach ($shippingMethodRows as $row) {
			$shippingCalculationLog .= "Calculating shipping method '" . $row['description'] . "'\n";
			$fixedCharges = $originalFixedCharged;
			$removedProductIds = $originalRemovedProductIds;
			$shippingProducts = $originalShippingProducts;

			if ($hasPromotion) {
				$promotionSet = executeQuery("select * from promotion_rewards_shipping_charges where promotion_id = ? and shipping_method_id = ?", $this->iPromotionId, $row['shipping_method_id']);
				while ($promotionRow = getNextRow($promotionSet)) {
					if (empty($promotionRow['only_reward_products'])) {
						continue;
					}
					foreach ($shippingProducts as $index => $thisProductInfo) {
						if ($thisProductInfo['promotional_price'] === true) {
							$fixedCharges += $promotionRow['amount'] * $thisProductInfo['quantity'];
							$shippingCalculationLog .= "Flat rate of " . number_format($promotionRow['amount'], 2) . " for product ID " . $thisProductInfo['product_id'] . " because of promotion\n";
							$removedProductIds[] = $thisProductInfo['product_id'];
							unset($shippingProducts[$index]);
						}
					}
				}
			}

			foreach ($shippingProducts as $index => $thisProductInfo) {
				$shippingPrice = getFieldFromId("price", "product_prices", "product_id", $thisProductInfo['product_id'], "product_price_type_id = " .
					"(select product_price_type_id from product_price_types where product_price_type_code = 'SHIPPING_CHARGE_" . $row['shipping_method_code'] .
					"' and (start_date is null or start_date <= current_date) and (end_date is null or end_date >= current_date) and client_id = ?)" .
					(empty($contactRow['user_type_id']) ? " and user_type_id is null" : " and (user_type_id is null or user_type_id = " . $contactRow['user_type_id'] . ")"), $GLOBALS['gClientId']);
				if (strlen($shippingPrice) > 0) {
					if ($thisProductInfo['quantity'] > 1) {
						$additionalShippingPrice = getFieldFromId("price", "product_prices", "product_id", $thisProductInfo['product_id'], "product_price_type_id = " .
							"(select product_price_type_id from product_price_types where product_price_type_code = 'ADDITIONAL_SHIPPING_CHARGE' and (start_date is null or start_date <= current_date) and " .
							"(end_date is null or end_date >= current_date) and client_id = ?)" .
							(empty($contactRow['user_type_id']) ? " and user_type_id is null" : " and (user_type_id is null or user_type_id = " . $contactRow['user_type_id'] . ")"), $GLOBALS['gClientId']);
						if (strlen($additionalShippingPrice) == 0) {
							$additionalShippingPrice = $shippingPrice;
						}
					} else {
						$additionalShippingPrice = $shippingPrice;
					}
					$fixedCharges += $shippingPrice + ($additionalShippingPrice * ($thisProductInfo['quantity'] - 1));
					$shippingCalculationLog .= "Flat rate of " . number_format($fixedCharges, 2) . " for product ID " . $thisProductInfo['product_id'] .
						" for shipping method '" . $row['description'] . "'\n";
					$removedProductIds[] = $thisProductInfo['product_id'];
					unset($shippingProducts[$index]);
				}
			}

			$canUse = true;
			foreach ($productIds as $thisProductId) {
				if (array_key_exists($thisProductId, $this->iProductShippingMethods)) {
					$productShippingMethods = $this->iProductShippingMethods[$thisProductId];
				} else {
					$this->iProductShippingMethods[$thisProductId] = array();
					$shippingMethodSet = executeQuery("select * from product_shipping_methods where product_id = ?", $thisProductId);
					while ($shippingMethodRow = getNextRow($shippingMethodSet)) {
						$this->iProductShippingMethods[$thisProductId][] = $shippingMethodRow['shipping_method_id'];
					}
					$productShippingMethods = $this->iProductShippingMethods[$thisProductId];
				}
				if (in_array($row['shipping_method_id'], $productShippingMethods)) {
					$shippingCalculationLog .= "Product ID " . $thisProductId . " cannot use this shipping method.\n";
					$canUse = false;
					break;
				}
				if (!array_key_exists($row['shipping_method_id'], $this->iShippingMethodCategories)) {
					$this->iShippingMethodCategories[$row['shipping_method_id']] = array();
					$categorySet = executeQuery("select * from product_categories where client_id = ? and product_category_id in (select product_category_id from product_category_shipping_methods where " .
						"shipping_method_id = ?)", $GLOBALS['gClientId'], $row['shipping_method_id']);
					while ($categoryRow = getNextRow($categorySet)) {
						$this->iShippingMethodCategories[$row['shipping_method_id']][] = $categoryRow;
					}
				}
				foreach ($this->iShippingMethodCategories[$row['shipping_method_id']] as $categoryRow) {
					if (ProductCatalog::productIsInCategory($thisProductId, $categoryRow['product_category_id'])) {
						$shippingCalculationLog .= "Product ID " . $thisProductId . " is in product category '" . $categoryRow['description'] . "', which cannot use this shipping method.\n";
						$canUse = false;
						break;
					}
				}
				if (!$canUse) {
					break;
				}
			}
			if (!$canUse) {
				$shippingCalculationLog .= "Can't use shipping method '" . $row['description'] . "'\n";
				continue;
			}

			$chargeSet = executeQuery("select * from shipping_charges where shipping_method_id = ?", $row['shipping_method_id']);
			$shippingCalculationLog .= $chargeSet['row_count'] . " charges found for shipping method '" . $row['description'] . "'\n";
			while ($chargeRow = getNextRow($chargeSet)) {
				if ((!empty($chargeRow['minimum_amount']) && $orderTotal < $chargeRow['minimum_amount']) || (!empty($chargeRow['maximum_order_amount']) && $orderTotal > $chargeRow['maximum_order_amount'])) {
					$shippingCalculationLog .= "Charge '" . $chargeRow['description'] . "' cannot be used because order total does not meet minimum or maximum amount requirements.\n";
					continue;
				}
				$useCharge = false;
				$locationSet = executeQuery("select * from shipping_locations where shipping_charge_id = ? order by exclude_location,sequence_number",
					$chargeRow['shipping_charge_id']);
				while ($locationRow = getNextRow($locationSet)) {
					if (($countryId == $locationRow['country_id'] || empty($locationRow['country_id'])) &&
						($state == $locationRow['state'] || empty($locationRow['state'])) &&
						($postalCode == $locationRow['postal_code'] || empty($locationRow['postal_code']))) {
						$useCharge = ($locationRow['exclude_location'] ? false : true);
					}
				}
				if (!$useCharge) {
					$shippingCalculationLog .= "Charge '" . $chargeRow['description'] . "' cannot be used because of the location\n";
					continue;
				}
				$shippingCalculationLog .= "Calculating charges for Charge '" . $chargeRow['description'] . "'\n";

				$easyPostShippingCalculation = false;
				while (!$row['pickup'] && !empty($chargeRow['shipping_service_calculation'])) {
					$easyPostActive = getPreference($GLOBALS['gDevelopmentServer'] ? "EASY_POST_TEST_API_KEY" : "EASY_POST_API_KEY");
					if (!$easyPostActive) {
						$shippingCalculationLog .= "EasyPost calculation not available\n";
						break;
					}
					$shippingCalculationLog .= "Attempting to use EasyPost for shipping calculation\n";

					# default is to make sure ALL products are available at a local location
					# need to make a way to allow one or more distributors to count as local locations for this purpose

					$productDistributorsAsLocalIdsString = getPreference("PRODUCT_DISTRIBUTORS_AS_LOCAL_FOR_SHIPPING");
					$productDistributorsAsLocalIds = array_filter(explode(",", $productDistributorsAsLocalIdsString));
					$calculationProblem = false;
					$totalWeight = 0;
					$signatureRequired = getPreference("RETAIL_STORE_REQUIRE_SIGNATURE");
					$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
					$excludedShippingCarrierCodes = array();
					$productIdsArray = array();
					$length = 6;
					$width = 6;
					$height = 6;
					foreach ($shippingProducts as $thisItem) {
						$productId = $thisItem['product_id'];
						$productIdsArray[] = $productId;
						$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $productId, "quantity > 0 and location_id in (select location_id from locations " .
							"where inactive = 0 and internal_use_only = 0 and (product_distributor_id is null" . (empty($productDistributorsAsLocalIds) ? "" : " or product_distributor_id in (" . implode(",", $productDistributorsAsLocalIds) . ")") . "))");
						if (empty($productInventoryId)) {
							$shippingCalculationLog .= "Unable to use EasyPost calculation because product ID " . $productId . " has no inventory in local location.\n";
							$calculationProblem = true;
						}

						$dimensions = getMultipleFieldsFromId(array("weight", "height", "width", "length"), "product_data", "product_id", $productId);
						if (!empty($dimensions['height']) && $dimensions['height'] > $height) {
							$height = $dimensions['height'];
						}
						if (!empty($dimensions['width']) && $dimensions['width'] > $width) {
							$width = $dimensions['width'];
						}
						if (!empty($dimensions['length']) && $dimensions['length'] > $length) {
							$length = $dimensions['length'];
						}
						if (empty($dimensions['weight']) || $dimensions['weight'] < .25) {
							$shippingCalculationLog .= "Unable to use EasyPost calculation because product ID " . $productId . " has no weight or a weight less than 1/4 pound.\n";
							$calculationProblem = true;
						} else {
							$totalWeight += ($thisItem['quantity'] * $dimensions['weight']);
						}
						if (!$signatureRequired && !$calculationProblem) {
							$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productId, "product_tag_id = ?", $fflRequiredProductTagId);
							if (!empty($productTagLinkId)) {
								$signatureRequired = true;
							}
							if (!$signatureRequired) {
								foreach ($signatureRequiredDepartmentIds as $thisDepartmentId) {
									if (ProductCatalog::productIsInDepartment($productId, $thisDepartmentId)) {
										$signatureRequired = true;
										break;
									}
								}
							}
						}
					}
					if ($calculationProblem) {
						break;
					}
					$shippingCarrierSet = executeQuery("select shipping_carrier_code from shipping_carriers where client_id = ? and (shipping_carrier_id in " .
						"(select shipping_carrier_id from product_category_shipping_carriers where product_category_id in (select product_category_id from product_category_links where " .
						"product_id in (" . implode(",", $productIdsArray) . "))) or shipping_carrier_id in (select shipping_carrier_id from product_shipping_carriers where " .
						"product_id in (" . implode(",", $productIdsArray) . ")))", $GLOBALS['gClientId']);
					while ($shippingCarrierRow = getNextRow($shippingCarrierSet)) {
						$excludedShippingCarrierCodes[] = $shippingCarrierRow['shipping_carrier_code'];
						$shippingCalculationLog .= "Excluding carrier '" . $shippingCarrierRow['shipping_carrier_code'] . "'.\n";
					}
					$packageCount = 1;
                    if (!empty($chargeRow['shipping_service_maximum_weight']) && floatval($chargeRow['shipping_service_maximum_weight']) > 0) {
						$packageCount = ceil($totalWeight / $chargeRow['shipping_service_maximum_weight']);
					}
					$packageWeight = round($totalWeight / $packageCount, 2);
					$shippingCalculationLog .= "EasyPost calculation being done for " . $packageCount . " package" . ($packageCount == 1 ? "" : "s") . ", sized " . $length . "x" . $width . "x" . $height . ", at " . $totalWeight . " pounds\n";
					$packageDataArray = array();
					$packageDataArray['from_country_id'] = $GLOBALS['gClientRow']['country_id'];
					$packageDataArray['from_full_name'] = $GLOBALS['gClientName'];
					$packageDataArray['from_address_1'] = $GLOBALS['gClientRow']['address_1'];
					$packageDataArray['from_address_2'] = $GLOBALS['gClientRow']['address_2'];
					$packageDataArray['from_city'] = $GLOBALS['gClientRow']['city'];
					$packageDataArray['from_state'] = $GLOBALS['gClientRow']['state'];
					$packageDataArray['from_postal_code'] = $GLOBALS['gClientRow']['postal_code'];
					$packageDataArray['from_phone_number'] = $GLOBALS['gClientRow']['phone_numbers'][0];

					$packageDataArray['to_country_id'] = $countryId;
					$packageDataArray['to_full_name'] = "Customer";
					$packageDataArray['to_address_1'] = $fullAddress['address_line_1'];
					$packageDataArray['to_city'] = $fullAddress['city'];
					$packageDataArray['to_state'] = $state;
					$packageDataArray['to_postal_code'] = $postalCode;
					$packageDataArray['to_phone_number'] = $GLOBALS['gClientRow']['phone_numbers'][0];
					$packageDataArray['residential_address'] = true;
					$packageDataArray['signature_required'] = $signatureRequired;
					$packageDataArray['length'] = $length;
					$packageDataArray['width'] = $width;
					$packageDataArray['height'] = $height;
					$packageDataArray['weight'] = $packageWeight;

					$easyPostRates = EasyPostIntegration::getLabelRates($easyPostActive, $packageDataArray);
					$usedRate = false;
					foreach ($easyPostRates['rates'] as $thisRate) {
						$shippingCalculationLog .= "EasyPost rate '" . $thisRate['description'] . "' for " . number_format($thisRate['rate'], 2) . " found\n";
						if (in_array($thisRate['carrier'], $excludedShippingCarrierCodes) || empty($thisRate['rate'])) {
							$shippingCalculationLog .= "EasyPost rate '" . $thisRate['description'] . "' not used because product(s) are restricted\n";
							continue;
						}
						if (!$easyPostShippingCalculation || $thisRate['rate'] < $easyPostShippingCalculation) {
							$easyPostShippingCalculation = $thisRate['rate'];
							$usedRate = $thisRate;
						}
					}
					if ($easyPostShippingCalculation) {
						$shippingCalculationLog .= "EasyPost rate '" . $usedRate['description'] . "' for " . number_format($easyPostShippingCalculation, 2) . " used for each package\n";
					}
					break;
				}
				if ($easyPostShippingCalculation) {
					if (!empty($chargeRow['shipping_service_percentage']) && $chargeRow['shipping_service_percentage'] > 0) {
						$easyPostShippingCalculation = round($easyPostShippingCalculation * (100 + $chargeRow['shipping_service_percentage']) / 100, 2);
						$shippingCalculationLog .= "EasyPost rate increased by " . number_format($chargeRow['shipping_service_percentage'], 2) . "%\n";
					}
					if (!empty($chargeRow['shipping_service_flat_rate']) && $chargeRow['shipping_service_flat_rate'] > 0) {
						$easyPostShippingCalculation += ($chargeRow['shipping_service_flat_rate'] * $packageCount);
						$shippingCalculationLog .= "EasyPost rate increased by " . number_format($chargeRow['shipping_service_flat_rate'], 2) . "\n";
					}
					$easyPostShippingCalculation = $easyPostShippingCalculation * $packageCount;
					if ($easyPostShippingCalculation < $chargeRow['minimum_charge'] && !empty($shippingProducts)) {
						$shippingCalculationLog .= "Minimum charge of " . $chargeRow['minimum_charge'] . " used\n";
						$easyPostShippingCalculation = $chargeRow['minimum_charge'];
					}
					if (!array_key_exists($row['shipping_method_id'], $shippingRates) || $easyPostShippingCalculation < $shippingRates[$row['shipping_method_id']]['rate']) {
						$shippingCalculationLog .= "EasyPost Rate for shipping method '" . $row['description'] . ": " . number_format($easyPostShippingCalculation, 2) . "\n\n";
						$shippingRates[$row['shipping_method_id']] = array("description" => $row['description'], "rate" => $easyPostShippingCalculation, "pickup" => $row['pickup'], "shipping_method_code" => $row['shipping_method_code']);
					}
					continue;
				}

# get shipping surcharge for distributors

				$distributorCharges = array();
				foreach ($shippingProducts as $thisItem) {
					$productId = $thisItem['product_id'];

# if there is inventory for a distributor who has no additional charge, skip this product

					if (!array_key_exists($productId, $this->iShippingChargeProductInventories)) {
						$this->iShippingChargeProductInventories[$productId] = array();
					}
					if (!array_key_exists($chargeRow['shipping_charge_id'], $this->iShippingChargeProductInventories[$productId])) {
						$productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $productId, "quantity > 0 and location_id in (select location_id from locations " .
							"where inactive = 0 and (product_distributor_id is null or product_distributor_id not in (select product_distributor_id from product_distributor_shipping_charges where shipping_charge_id = ?)))",
							$chargeRow['shipping_charge_id']);
						$this->iShippingChargeProductInventories[$productId][$chargeRow['shipping_charge_id']] = $productInventoryId;
					} else {
						$productInventoryId = $this->iShippingChargeProductInventories[$productId][$chargeRow['shipping_charge_id']];
					}
					if (!empty($productInventoryId)) {
						continue;
					}
					$distributorSet = executeQuery("select * from product_distributor_shipping_charges join locations using (product_distributor_id) where location_id in " .
						"(select location_id from product_inventories where product_id = ? and quantity > 0) and shipping_charge_id = ? order by flat_rate,additional_item_charge", $productId, $chargeRow['shipping_charge_id']);
					while ($distributorRow = getNextRow($distributorSet)) {
						if (!empty($distributorRow['product_department_id'])) {
							if (!ProductCatalog::productIsInDepartment($productId, $distributorRow['product_department_id'])) {
								continue;
							}
						} else {
							$distributorRow['product_department_id'] = 0;
						}
						if (!array_key_exists($distributorRow['product_distributor_id'], $distributorCharges)) {
							$distributorCharges[$distributorRow['product_distributor_id']] = array();
						}
						if (array_key_exists($distributorRow['product_department_id'], $distributorCharges[$distributorRow['product_distributor_id']])) {
							$distributorCharges[$distributorRow['product_distributor_id']][$distributorRow['product_department_id']] += ($thisItem['quantity'] * $distributorRow['additional_item_charge']);
						} else {
							$distributorCharges[$distributorRow['product_distributor_id']][$distributorRow['product_department_id']] = $distributorRow['flat_rate'] + (($thisItem['quantity'] - 1) * $distributorRow['additional_item_charge']);
						}
						break;
					}
				}
				$distributorCharge = 0;
				foreach ($distributorCharges as $productDistributorId => $thisDepartmentCharge) {
					foreach ($thisDepartmentCharge as $productDepartmentId => $thisCharge) {
						$shippingCalculationLog .= "Additional Shipping charge for distributor " . getFieldFromId("description", "product_distributors", "product_distributor_id", $productDistributorId) .
							(empty($productDepartmentId) ? "" : " in department " . getFieldFromId("description", "product_departments", "product_department_id", $productDepartmentId)) . " of " . $thisCharge . "\n";
						$distributorCharge += $thisCharge;
					}
				}

				$allShippingProducts = $shippingProducts;
				$locations = array();
				$perLocation = (!empty($chargeRow['per_location']));
				if ($perLocation) {
					$locationProductDistributors = array();
					$locationSet = executeQuery("select * from locations where client_id = ? and locations.inactive = 0 and (product_distributor_id is null or primary_location = 1)", $GLOBALS['gClientId']);
					while ($locationRow = getNextRow($locationSet)) {
						if (!empty($row['product_distributor_id'])) {
							if (in_array($row['product_distributor_id'], $locationProductDistributors)) {
								continue;
							}
							$locationProductDistributors[] = $row['product_distributor_id'];
						}
						$thisLocation = array("location_id" => $locationRow['location_id'], "count" => 0);
						foreach ($shippingProducts as $index => $thisProductInfo) {
							$inventoryQuantity = getFieldFromId("quantity", "product_inventories", "product_id", $thisProductInfo['product_id'], "quantity > 0 and location_id = ?", $locationRow['location_id']);
							if ($inventoryQuantity > 0) {
								$thisLocation['count']++;
							}
						}
						$locations[] = $thisLocation;
					}
				}
				usort($locations, array($this, "sortLocations"));

				$allRates = array();
				while (!empty($allShippingProducts)) {
					if ($perLocation) {
						if (empty($locations)) {
							$shippingCalculationLog .= "Locations exhausted\n";
							break;
						}
						$thisLocation = array_shift($locations);
						$thisLocationId = $thisLocation['location_id'];
						$thisShippingProducts = array();
						foreach ($allShippingProducts as $index => $thisProductInfo) {
							$inventoryQuantity = getFieldFromId("quantity", "product_inventories", "product_id", $thisProductInfo['product_id'], "quantity > 0 and location_id = ?", $thisLocationId);
							if (empty($inventoryQuantity)) {
								continue;
							}
							$otherQuantity = getFieldFromId("quantity", "product_inventories", "product_id", $thisProductInfo['product_id'], "quantity > 0 and location_id <> ?", $thisLocationId);
							if (empty($otherQuantity)) {
								$inventoryQuantity = $thisProductInfo['quantity'];
							}
							$useQuantity = min(array($inventoryQuantity, $thisProductInfo['quantity']));
							$thisShippingProducts[] = array("product_id" => $thisProductInfo['product_id'], "quantity" => $useQuantity);
							if ($useQuantity == $thisProductInfo['quantity']) {
								unset($allShippingProducts[$index]);
							} else {
								$allShippingProducts[$index]['quantity'] = $thisProductInfo['quantity'] - $useQuantity;
							}
						}
						if (empty($thisShippingProducts)) {
							continue;
						}
						$totalCartWeight = $this->getCartShipWeight($thisShippingProducts);
					} else {
						$thisShippingProducts = $allShippingProducts;
						$allShippingProducts = array();
					}

# get additional charges for product types

					$typeCharge = 0;
					$productTypeCharges = array();
					$typeSet = executeQuery("select * from shipping_charge_product_types join product_types using (product_type_id) where " .
						"shipping_charge_id = ? and (flat_rate <> 0 or additional_item_charge <> 0) and inactive = 0", $chargeRow['shipping_charge_id']);
					while ($typeRow = getNextRow($typeSet)) {
						if (!array_key_exists($typeRow['product_type_id'], $productTypeCharges)) {
							$productTypeCharges[$typeRow['product_type_id']] = array("product_type_id" => $typeRow['product_type_id'], "description" => "Type: " . $typeRow['description'],
								"first_used" => false, "flat_rate" => $typeRow['flat_rate'], "additional_item_charge" => $typeRow['additional_item_charge']);
						}
					}
					foreach ($thisShippingProducts as $index => $thisItem) {
						if (array_key_exists($thisItem['product_id'], $this->iProductTypes)) {
							$productTypeId = $this->iProductTypes[$thisItem['product_id']];
						} else {
							$productTypeId = getFieldFromId("product_type_id", "products", "product_id", $thisItem['product_id']);
							$this->iProductTypes[$thisItem['product_id']] = $productTypeId;
						}
						$shippingCalculationLog .= "Checking type for product ID " . $thisItem['product_id'] . ", " . $productTypeId . "\n";
						$thisCharge = $productTypeCharges[$productTypeId];
						if (empty($thisCharge)) {
							continue;
						}
						$thisFirstCharge = ($thisCharge['first_used'] ? $thisCharge['additional_item_charge'] : $thisCharge['flat_rate']);
						$thisAdditionalCharge = ($thisCharge['additional_item_charge'] * ($thisItem['quantity'] - 1));
						$thisCharge = $thisFirstCharge + $thisAdditionalCharge;
						$typeCharge += $thisCharge;
						$productTypeCharges[$productTypeId]['first_used'] = true;

						if ($thisCharge > 0) {
							$shippingCalculationLog .= "Additional charge of " . number_format($thisCharge, 2) . " for type '" . $productTypeCharges[$productTypeId]['description'] . "' for product ID " . $thisItem['product_id'] . "\n";
						}
					}

# get additional charges for categories

					$categoryCharge = 0;
					$productCategoryCharges = array();
					foreach ($thisShippingProducts as $index => $thisItem) {
						$categorySet = executeQuery("select * from shipping_charge_product_categories join product_categories using (product_category_id) join product_category_links using (product_category_id) where " .
							"shipping_charge_id = ? and (flat_rate <> 0 or additional_item_charge <> 0) and inactive = 0 and product_category_links.product_id = ? order by sequence_number", $chargeRow['shipping_charge_id'], $thisItem['product_id']);
						while ($categoryRow = getNextRow($categorySet)) {
							if (!array_key_exists($categoryRow['product_category_id'], $productCategoryCharges)) {
								$productCategoryCharges[$categoryRow['product_category_id']] = array("product_category_id" => $categoryRow['product_category_id'], "description" => "Category: " . $categoryRow['description'],
									"first_used" => false, "flat_rate" => $categoryRow['flat_rate'], "additional_item_charge" => $categoryRow['additional_item_charge'], "product_ids" => array());
							}
							$productCategoryCharges[$categoryRow['product_category_id']]['product_ids'][] = $thisItem['product_id'];
						}
					}
					foreach ($thisShippingProducts as $index => $thisItem) {
						$shippingCalculationLog .= "Checking categories for product ID " . $thisItem['product_id'] . "\n";
						$saveProductCategoryId = "";
						$saveCharge = 0;
						foreach ($productCategoryCharges as $productCategoryId => $thisChargeInfo) {
							if (!array_key_exists("product_ids", $thisChargeInfo) || !is_array($thisChargeInfo['product_ids']) || !in_array($thisItem['product_id'], $thisChargeInfo['product_ids'])) {
								continue;
							}
							$thisFirstCharge = ($thisChargeInfo['first_used'] ? $thisChargeInfo['additional_item_charge'] : $thisChargeInfo['flat_rate']);
							$thisAdditionalCharge = ($thisChargeInfo['additional_item_charge'] * ($thisItem['quantity'] - 1));
							$thisCharge = $thisFirstCharge + $thisAdditionalCharge;
							if ($thisCharge > $saveCharge || empty($saveProductCategoryId)) {
								$saveProductCategoryId = $productCategoryId;
								$saveCharge = $thisCharge;
							}
						}
						$productCategoryCharges[$saveProductCategoryId]['first_used'] = true;
						if (!empty($saveProductCategoryId)) {
							$categoryCharge += $saveCharge;
							$shippingCalculationLog .= "Additional charge of " . number_format($saveCharge, 2) . " for category '" . $productCategoryCharges[$saveProductCategoryId]['description'] . "' for product ID " . $thisItem['product_id'] . "\n";
						}
					}

# get Additional charges for departments

					$departmentCharge = 0;
					$productDepartmentCharges = array();
					foreach ($thisShippingProducts as $index => $thisItem) {
						$departmentSet = executeQuery("select * from shipping_charge_product_departments join product_departments using (product_department_id) " .
							"where shipping_charge_id = ? and (flat_rate <> 0 or additional_item_charge <> 0) and inactive = 0", $chargeRow['shipping_charge_id']);
						while ($departmentRow = getNextRow($departmentSet)) {
							if (!ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentRow['product_department_id'])) {
								continue;
							}
							$shippingCalculationLog .= "Product ID " . $thisItem['product_id'] . " is in department " . $departmentRow['description'] . "\n";
							if (!array_key_exists($departmentRow['product_department_id'], $productDepartmentCharges)) {
								$productDepartmentCharges[$departmentRow['product_department_id']] = array("product_department_id" => $departmentRow['product_department_id'], "description" => "Department: " . $departmentRow['description'],
									"first_used" => false, "flat_rate" => $departmentRow['flat_rate'], "additional_item_charge" => $departmentRow['additional_item_charge'], "product_ids" => array());
							}
							$productDepartmentCharges[$departmentRow['product_department_id']]['product_ids'][] = $thisItem['product_id'];
						}
					}
					$totalItems = 0;
					foreach ($thisShippingProducts as $index => $thisItem) {
						$totalItems += $thisItem['quantity'];
						$saveProductDepartmentId = "";
						$saveCharge = 0;
						foreach ($productDepartmentCharges as $productDepartmentId => $thisChargeInfo) {
							if (!array_key_exists("product_ids", $thisChargeInfo) || !is_array($thisChargeInfo['product_ids']) || !in_array($thisItem['product_id'], $thisChargeInfo['product_ids'])) {
								continue;
							}
							$thisFirstCharge = ($thisChargeInfo['first_used'] ? $thisChargeInfo['additional_item_charge'] : $thisChargeInfo['flat_rate']);
							$thisAdditionalCharge = ($thisChargeInfo['additional_item_charge'] * ($thisItem['quantity'] - 1));
							$thisCharge = $thisFirstCharge + $thisAdditionalCharge;
							if ($thisCharge > $saveCharge || empty($saveProductDepartmentId)) {
								$saveProductDepartmentId = $productDepartmentId;
								$saveCharge = $thisCharge;
							}
						}
						$productDepartmentCharges[$saveProductDepartmentId]['first_used'] = true;
						if (!empty($saveProductDepartmentId)) {
							$departmentCharge += $saveCharge;
							$shippingCalculationLog .= "Additional charge of " . number_format($saveCharge, 2) . " for department '" . $productDepartmentCharges[$saveProductDepartmentId]['description'] . "' for product ID " . $thisItem['product_id'] . "\n";
						}
					}

					$shippingCalculationLog .= "Found matching shipping location\n";
					if ($totalItems > 0) {
						$itemCharge = (empty($chargeRow['flat_rate']) ? 0 : $chargeRow['flat_rate']);
						$itemCharge += ($totalItems - 1) * (empty($chargeRow['additional_item_charge']) ? 0 : $chargeRow['additional_item_charge']);
					} else {
						$itemCharge = 0;
					}
					$shippingCalculationLog .= "Per item charge: " . number_format($itemCharge, 2) . "\n";
					if ($chargeRow['percentage'] > 0) {
						$percentageCharge = round($orderTotal * ($chargeRow['percentage'] / 100), 2);
						$shippingCalculationLog .= "Percentage of order charge: " . number_format($percentageCharge, 2) . "\n";
						$itemCharge += $percentageCharge;
					}
					if (!empty($chargeRow['product_department_id'])) {
						$rateWeight = $this->getCartShipWeight($removedProductIds, false, $chargeRow['product_department_id']);
					} else {
						$rateWeight = $totalCartWeight;
					}
					$rateSet = executeQuery("select * from shipping_rates where shipping_charge_id = ? and minimum_weight <= ? and (maximum_weight is null or maximum_weight >= ?)",
						$chargeRow['shipping_charge_id'], $rateWeight, $rateWeight);
					$shippingCalculationLog .= "Found " . $rateSet['row_count'] . " rates for this shipping charge\n";
					if ($rateSet['row_count'] == 0) {
						$rate = $itemCharge + $categoryCharge + $departmentCharge + $typeCharge + $distributorCharge;
						if ($rate < $chargeRow['minimum_charge'] && !empty($shippingProducts)) {
							$shippingCalculationLog .= "Minimum charge of " . $chargeRow['minimum_charge'] . "used\n";
							$rate = $chargeRow['minimum_charge'];
						}
						if ($rate < $chargeRow['minimum_charge'] && !empty($shippingProducts)) {
							$shippingCalculationLog .= "Minimum charge of " . $chargeRow['minimum_charge'] . "used\n";
							$rate = $chargeRow['minimum_charge'];
						}
						if (!empty($thisShippingProducts)) {
							$perItemCharge = ($totalItems > 0 ? $rate / $totalItems : 0);
							if ($chargeRow['maximum_amount'] > 0 && $perItemCharge > $chargeRow['maximum_amount']) {
								$rate = $totalItems * $chargeRow['maximum_amount'];
								$shippingCalculationLog .= "Maximum charge per item of " . $chargeRow['maximum_amount'] . " used: " . $rate . "\n";
							}
						}
						$allRates[] = $rate;
						if ($perLocation) {
							$shippingCalculationLog .= "Rate for location '" . getFieldFromId("description", "locations", "location_id", $thisLocationId) . ": " . number_format($rate, 2) . "\n";
						}
					} else {
						if ($rateRow = getNextRow($rateSet)) {
							$rate = $rateRow['flat_rate'];
							$shippingCalculationLog .= "Added flat rate of " . number_format($rate, 2) . "\n";
							if ($rateRow['additional_pound_charge'] > 0) {
								$poundCharge = ceil($rateWeight - $rateRow['minimum_weight']) * $rateRow['additional_pound_charge'];
								$shippingCalculationLog .= "Added additional weight charge of " . number_format($poundCharge, 2) . "\n";
								$rate += $poundCharge;
							}
							if (!empty($rateRow['maximum_dimension']) && !empty($rateRow['over_dimension_charge'])) {
								foreach ($this->iShoppingCartItems as $index => $thisItem) {
									if (in_array($thisItem['product_id'], $removedProductIds)) {
										continue;
									}
									$overDimension = false;
									if (!empty($productDataRows[$thisItem['product_id']]['width']) && $productDataRows[$thisItem['product_id']]['width'] > $rateRow['maximum_dimension']) {
										$overDimension = true;
									}
									if (!empty($productDataRows[$thisItem['product_id']]['length']) && $productDataRows[$thisItem['product_id']]['length'] > $rateRow['maximum_dimension']) {
										$overDimension = true;
									}
									if (!empty($productDataRows[$thisItem['product_id']]['height']) && $productDataRows[$thisItem['product_id']]['height'] > $rateRow['maximum_dimension']) {
										$overDimension = true;
									}
									if ($overDimension) {
										$overDimensionCharge = ($rateRow['over_dimension_charge'] * $thisItem['quantity']);
										$rate += $overDimensionCharge;
										$shippingCalculationLog .= "Added over dimension charge of " . number_format($overDimensionCharge, 2) . " for product ID " . $thisItem['product_id'] . "\n";
									}
								}
							}
							$rate += $itemCharge + $categoryCharge + $departmentCharge + $typeCharge + $distributorCharge;
							if ($rate < $chargeRow['minimum_charge'] && !empty($shippingProducts)) {
								$shippingCalculationLog .= "Minimum charge of " . $chargeRow['minimum_charge'] . " used\n";
								$rate = $chargeRow['minimum_charge'];
							}
							if (!empty($thisShippingProducts)) {
								$perItemCharge = ($totalItems > 0 ? $rate / $totalItems : 0);
								if ($chargeRow['maximum_amount'] > 0 && $perItemCharge > $chargeRow['maximum_amount']) {
									$rate = $totalItems * $chargeRow['maximum_amount'];
									$shippingCalculationLog .= "Maximum charge per item of " . $chargeRow['maximum_amount'] . " used: " . $rate . "\n";
								}
							}
							$allRates[] = $rate;
							if ($perLocation) {
								$shippingCalculationLog .= "Rate for location '" . getFieldFromId("description", "locations", "location_id", $thisLocationId) . ": " . number_format($rate, 2) . "\n\n";
							}
						}
					}
				}
				$rate = array_sum($allRates);
				$rate += $fixedCharges;
				if ($rate < $chargeRow['minimum_charge'] && !empty($shippingProducts)) {
					$shippingCalculationLog .= "Minimum charge of " . $chargeRow['minimum_charge'] . " used\n";
					$rate = $chargeRow['minimum_charge'];
				}
				if (count($allShippingProducts) > 0) {
					$perItemCharge = ($totalItems > 0 ? $rate / $totalItems : 0);
					if ($chargeRow['maximum_amount'] > 0 && $perItemCharge > $chargeRow['maximum_amount']) {
						$rate = $totalItems * $chargeRow['maximum_amount'];
						$shippingCalculationLog .= "Maximum charge per item of " . $chargeRow['maximum_amount'] . " used: " . $rate . "\n";
					}
				}
				if (!array_key_exists($row['shipping_method_id'], $shippingRates) || $rate < $shippingRates[$row['shipping_method_id']]['rate']) {
					$shippingCalculationLog .= "Rate for shipping method '" . $row['description'] . ": " . number_format($rate, 2) . "\n\n";
					$shippingRates[$row['shipping_method_id']] = array("description" => $row['description'], "rate" => $rate, "pickup" => $row['pickup'], "shipping_method_code" => $row['shipping_method_code']);
				}
			}
		}
		$hasPromotion = $this->isPromotionValid();
		if ($hasPromotion) {
			$resultSet = executeQuery("select * from promotion_rewards_shipping_charges where promotion_id = ?", $this->iPromotionId);
			while ($row = getNextRow($resultSet)) {
				if (!empty($row['only_reward_products'])) {
					continue;
				}
				if (array_key_exists($row['shipping_method_id'], $shippingRates) && $row['amount'] < $shippingRates[$row['shipping_method_id']]['rate']) {
					$shippingRates[$row['shipping_method_id']]['rate'] = $row['amount'];
					$shippingCalculationLog .= "Promotion rate for shipping method '" . getFieldFromId("description", "shipping_methods", "shipping_method_id", $row['shipping_method_id']) . "': " . number_format($row['amount'], 2) . "\n";
				}
			}
		}
		foreach ($shippingRates as $shippingMethodId => $shippingInfo) {
			if ($pickupOnly && empty($shippingInfo['pickup'])) {
				$shippingCalculationLog .= "Removed non-pickup shipping method " . $shippingInfo['description'] . "\n";
				continue;
			}
			$returnArray[] = array("shipping_method_id" => $shippingMethodId, "shipping_charge" => $shippingInfo['rate'], "description" => $shippingInfo['description'], "shipping_method_code" => $shippingInfo['shipping_method_code'], "pickup" => $shippingInfo['pickup']);
		}
		$shippingCalculationLog .= "Return Array: " . jsonEncode($returnArray) . "\n\n";
		$this->iShippingCalculationLog = $shippingCalculationLog;
		return $returnArray;
	}

	/**
	 * getCartShipWeight - gets the total shipping weight of all itemsInCart
	 * @param
	 *    none
	 * @return
	 *    shipping weight of products in cart
	 */
	function getCartShipWeight($removedProductIds = array(), $onlyTheseProducts = false, $productDepartmentId = false) {
		if ($onlyTheseProducts) {
			$productIds = $removedProductIds;
		} else {
			$productIds = array();
			foreach ($this->iShoppingCartItems as $shoppingCartItem) {
				$productId = $shoppingCartItem['product_id'];
				if (!in_array($productId, $removedProductIds)) {
					$productIds[] = array("product_id" => $productId, "quantity" => $shoppingCartItem['quantity']);
				}
			}
		}
		$totalWeight = 0;
		foreach ($productIds as $thisProductInfo) {
			if (!empty($productDepartmentId) && !ProductCatalog::productIsInDepartment($thisProductInfo['product_id'], $productDepartmentId)) {
				continue;
			}
			$shipWeight = getFieldFromId("weight", "product_data", "product_id", $thisProductInfo['product_id']);
			if (strlen($shipWeight) > 0) {
				$totalWeight += ($shipWeight * $thisProductInfo['quantity']);
			}
		}
		return $totalWeight;
	}

	function getEstimatedTax($parameters = array()) {
		if (!is_array($parameters)) {
			$parameters = array();
		}
		$parameters['shopping_cart_items'] = $this->getShoppingCartItems(array("reset_sale_price" => empty($GLOBALS['gUserRow']['administrator_flag'])));
		$parameters['promotion_id'] = $this->iPromotionId;
		return self::estimateTax($parameters);
	}

	function getOrderUpsellProducts() {
		$orderUpsellProductTypeId = getCachedData("order_upsell_product_type_id", "");
		if ($orderUpsellProductTypeId === false) {
			$orderUpsellProductTypeId = getFieldFromId("product_type_id", "product_types", "product_type_code", "order_upsell_product");
			if (empty($orderUpsellProductTypeId)) {
				$orderUpsellProductTypeId = 0;
			}
			setCachedData("order_upsell_product_type_id", "", $orderUpsellProductTypeId, 168);
		}
		if (empty($orderUpsellProductTypeId)) {
			return array();
		}
		$orderUpsellProducts = array();
		$upgradeProductSet = executeQuery("select product_id from products where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and product_type_id = ?", $GLOBALS['gClientId'], $orderUpsellProductTypeId);
		$productCatalog = new ProductCatalog();
		while ($upgradeProductRow = getNextRow($upgradeProductSet)) {
			$inventoryCounts = $productCatalog->getInventoryCounts(true, $upgradeProductRow['product_id']);
			if ($inventoryCounts[$upgradeProductRow['product_id']] <= 0) {
				continue;
			}
			$requireAfterXDays = CustomField::getCustomFieldData($upgradeProductRow['product_id'], "REQUIRE_AFTER_X_DAYS", "PRODUCTS");
			$required = false;
			if (strlen($requireAfterXDays) > 0 && is_numeric($requireAfterXDays)) {
				$contactId = $this->getContact() ?: $GLOBALS['gUserRow']['contact_id'];
				if (!empty($contactId) && $requireAfterXDays > 0) {
					$orderItemId = getFieldFromId("order_item_id", "order_items", "product_id", $upgradeProductRow['product_id'],
						"deleted = 0 and order_id in (select order_id from orders where contact_id = ? and deleted = 0 and date(order_time) >= date_sub(current_date, interval " . $requireAfterXDays . " day))", $contactId);
					if (!empty($orderItemId)) {
						$this->addItem(array("product_id" => $upgradeProductRow['product_id'], "quantity" => 0, "set_quantity" => true, "allow_order_upsell_products" => true));
						continue;
					}
				}
				$required = true;
			}
			$productRow = ProductCatalog::getCachedProductRow($upgradeProductRow['product_id']);
			$productRow['product_category_group_ids'] = array();
			$productRow['product_department_ids'] = array();
			if (!empty($productRow['product_category_ids'])) {
				$resultSet = executeQuery("select product_category_group_id from product_category_group_links where product_category_id in (" . implode(",", $productRow['product_category_ids']) . ")");
				while ($row = getNextRow($resultSet)) {
					$productRow['product_category_group_ids'][$row['product_category_group_id']] = $row['product_category_group_id'];
				}
				$resultSet = executeQuery("select product_department_id from product_category_departments where product_category_id in (" . implode(",", $productRow['product_category_ids']) . ")");
				while ($row = getNextRow($resultSet)) {
					$productRow['product_department_ids'][$row['product_department_id']] = $row['product_department_id'];
				}
				if (!empty($productRow['product_category_group_ids'])) {
					$resultSet = executeQuery("select product_department_id from product_category_group_departments where product_category_group_id in (" . implode(",", $productRow['product_category_group_ids']) . ")");
					while ($row = getNextRow($resultSet)) {
						$productRow['product_department_ids'][$row['product_department_id']] = $row['product_department_id'];
					}
				}
			}
			$productAppliesToCart = false;
			$allowMultipleUpgrades = CustomField::getCustomFieldData($upgradeProductRow['product_id'], "ALLOW_MULTIPLE_UPGRADES", "PRODUCTS");
			$percentagePrice = CustomField::getCustomFieldData($upgradeProductRow['product_id'], "PERCENTAGE_PRICE", "PRODUCTS");
			$applicationTotal = 0;
			$applicationQuantity = 0;
			$orderUpsellProductIds = array();
			foreach ($this->iShoppingCartItems as $shoppingCartItem) {
				$shoppingCartItemProductRow = ProductCatalog::getCachedProductRow($shoppingCartItem['product_id']);
				if ($shoppingCartItemProductRow['product_type_id'] == $orderUpsellProductTypeId) {
					$orderUpsellProductIds[] = $shoppingCartItem['product_id'];
				}
			}
			foreach ($this->iShoppingCartItems as $shoppingCartItem) {
				$shoppingCartItemProductRow = ProductCatalog::getCachedProductRow($shoppingCartItem['product_id']);
				if ($shoppingCartItemProductRow['product_type_id'] == $orderUpsellProductTypeId) {
					continue;
				}
				$sameTaxonomy = true;
				foreach ($productRow['product_tag_ids'] as $productTagId) {
					if (!ProductCatalog::productIsTagged($shoppingCartItemProductRow['product_id'], $productTagId)) {
						$sameTaxonomy = false;
						break;
					}
				}
				foreach ($productRow['product_category_ids'] as $productCategoryId) {
					if (!ProductCatalog::productIsInCategory($shoppingCartItemProductRow['product_id'], $productCategoryId)) {
						$sameTaxonomy = false;
						break;
					}
				}
				foreach ($productRow['product_category_group_ids'] as $productCategoryGroupId) {
					if (!ProductCatalog::productIsInCategoryGroup($shoppingCartItemProductRow['product_id'], $productCategoryGroupId)) {
						$sameTaxonomy = false;
						break;
					}
				}
				foreach ($productRow['product_department_ids'] as $productDepartmentId) {
					if (!ProductCatalog::productIsInDepartment($shoppingCartItemProductRow['product_id'], $productDepartmentId)) {
						$sameTaxonomy = false;
						break;
					}
				}
				if ($sameTaxonomy) {
					$productAppliesToCart = true;
					$applicationQuantity += $shoppingCartItem['quantity'];
					$applicationTotal += ($shoppingCartItem['quantity'] * $shoppingCartItem['sale_price']);
				}
			}
			if (function_exists("_localDoesOrderUpsellProductApply")) {
				$returnValue = _localDoesOrderUpsellProductApply($upgradeProductRow['product_id']);
				if (is_array($returnValue)) {
					$productAppliesToCart = $returnValue['applies_to_cart'];
					$required = $returnValue['required'];
					if ($required) {
						$productAppliesToCart = true;
					}
				}
			}
			if (!$productAppliesToCart) {
				$this->addItem(array("product_id" => $upgradeProductRow['product_id'], "quantity" => 0, "set_quantity" => true, "allow_order_upsell_products" => true));
				continue;
			}
			if (empty($percentagePrice)) {
				$productCatalog = new ProductCatalog();
				$salePriceInfo = $productCatalog->getProductSalePrice($productRow['product_id'], array("product_information" => $productRow));
				$salePrice = $salePriceInfo['sale_price'];
			} else {
				$salePrice = round(($applicationTotal * $percentagePrice / 100) / ($allowMultipleUpgrades ? $applicationQuantity : 1), 2);
			}
			if ($salePrice === false || !is_numeric($salePrice)) {
				continue;
			}
			$description = CustomField::getCustomFieldData($upgradeProductRow['product_id'], "ORDER_UPSELL_DESCRIPTION", "PRODUCTS") ?: $productRow['description'];
			$orderUpsellProducts[$upgradeProductRow['product_id']] = array("product_id" => $upgradeProductRow['product_id'], "description" => $description, "quantity" => ($allowMultipleUpgrades ? $applicationQuantity : 1),
				"sale_price" => number_format($salePrice, 2, ".", ""),
				"total_cost" => number_format((($allowMultipleUpgrades ? $applicationQuantity : 1) * $salePrice), 2, ".", ""),
				"required" => $required, "checked" => ($required ? "checked" : ""));
			if ($required || in_array($upgradeProductRow['product_id'], $orderUpsellProductIds)) {
				$this->addItem(array("product_id" => $upgradeProductRow['product_id'], "quantity" => ($allowMultipleUpgrades ? $applicationQuantity : 1), "set_quantity" => true, "allow_order_upsell_products" => true));
			}
		}
		return $orderUpsellProducts;
	}

	/**
	 * getShoppingCartItems - Return an array of the items in the shopping cart. The array will contain one row for each product id,
	 * along with the description, quantity, list price, discount rate and weight of that item in the shopping cart
	 * @param
	 *    none
	 * @return
	 *    array of items in the shopping cart
	 */
	function getShoppingCartItems($parameters = array()) {

		$cartChanges = false;
		$cacheKey = md5(jsonEncode($parameters));
		if (array_key_exists($cacheKey, $this->iShoppingCartItemCache)) {
			return $this->iShoppingCartItemCache[$cacheKey];
		}

		$orderUpsellProductTypeId = getCachedData("order_upsell_product_type_id", "");
		if ($orderUpsellProductTypeId === false) {
			$orderUpsellProductTypeId = getFieldFromId("product_type_id", "product_types", "product_type_code", "order_upsell_product");
			if (empty($orderUpsellProductTypeId)) {
				$orderUpsellProductTypeId = 0;
			}
			setCachedData("order_upsell_product_type_id", "", $orderUpsellProductTypeId, 168);
		}

		$usesPromotion = $this->isPromotionValid();
		$returnArray = array();

# if parameter value is set, recalculate all sale prices.

		$productCatalog = new ProductCatalog();
		$productIds = array();
		$userGroupIds = array();
		if ($GLOBALS['gLoggedIn']) {
			$groupSet = executeQuery("select * from user_group_members where user_id = ?", $GLOBALS['gUserId']);
			while ($groupRow = getNextRow($groupSet)) {
				$userGroupIds[] = $groupRow['user_group_id'];
			}
		}
		$productRows = array();
		foreach ($this->iShoppingCartItems as $index => $shoppingCartItem) {
			$productRows[$shoppingCartItem['product_id']] = array();
		}
		if (!empty($productRows)) {
			$resultSet = executeQuery("select *,(select group_concat(product_category_id) from product_category_links where product_id = products.product_id order by sequence_number) as product_category_ids from products left outer join product_data using (product_id) where products.product_id in (" .
				implode(",", array_keys($productRows)) . ")");
			while ($row = getNextRow($resultSet)) {
				$productRows[$row['product_id']] = $row;
			}
		}
		foreach ($productRows as $productId => $productRow) {
			if (empty($productRow)) {
				unset($productRows[$productId]);
				continue;
			}
			if (!empty($productRow['user_group_id'])) {
				if (!in_array($productRow['user_group_id'], $userGroupIds)) {
					$this->removeProduct($shoppingCartItem['product_id']);
					$cartChanges = true;
					unset($productRows[$productId]);
				}
			}
		}
		foreach ($this->iShoppingCartItems as $index => $shoppingCartItem) {
			$productRow = $productRows[$shoppingCartItem['product_id']];
			$shoppingCartItem['order_upsell_product'] = (!empty($orderUpsellProductTypeId) && ($productRow['product_type_id'] == $orderUpsellProductTypeId));
			if ($parameters['reset_sale_price']) {
				$orderUpsellProducts = $this->getOrderUpsellProducts();
				if (empty($productRow['custom_product'])) {
					if (array_key_exists($shoppingCartItem['product_id'], $orderUpsellProducts)) {
						$salePrice = $orderUpsellProducts[$shoppingCartItem['product_id']]['sale_price'];
					} else {
						productCatalog::calculateProductCost($shoppingCartItem['product_id'], "Product Added to Shopping Cart");
						$salePriceInfo = $productCatalog->getProductSalePrice($shoppingCartItem['product_id'], array("product_information" => $productRow, "quantity" => $shoppingCartItem['quantity'], "shopping_cart_id" => $this->iShoppingCartId, "no_cache" => true, "single_product" => true));
						$shoppingCartItem['price_calculation'] = $productCatalog->getPriceCalculationLog();
						$salePrice = $salePriceInfo['sale_price'];
					}
					$this->iShoppingCartItems[$index]['sale_price'] = $shoppingCartItem['sale_price'] = $salePrice;
					if ($shoppingCartItem['sale_price'] === false) {
						$this->removeProduct($shoppingCartItem['product_id']);
						unset($productRows[$shoppingCartItem['product_id']]);
						$cartChanges = true;
					}
				}
			}
			$productIds[] = $shoppingCartItem['product_id'];
			$shoppingCartItem['product_tag_ids'] = "";
			if ($parameters['include_upc_code']) {
				$shoppingCartItem['upc_code'] = getFieldFromId("upc_code", "product_data", "product_id", $shoppingCartItem['product_id']);
			}
			$returnArray[$shoppingCartItem['shopping_cart_item_id']] = $shoppingCartItem;
		}
		if ($cartChanges) {
			$this->writeShoppingCart();
		}
		if (!empty($productIds)) {
			$resultSet = executeQuery("select * from product_tag_links where product_id in (" . implode(",", $productIds) . ")");
			while ($row = getNextRow($resultSet)) {
				foreach ($returnArray as $index => $thisItem) {
					if ($thisItem['product_id'] == $row['product_id']) {
						$returnArray[$index]['product_tag_ids'] .= (empty($returnArray[$index]['product_tag_ids']) ? "" : ",") . $row['product_tag_id'];
					}
				}
			}
		}

		if (!$usesPromotion) {
			$this->iShoppingCartItemCache[$cacheKey] = $returnArray;
			return $returnArray;
		}

		usort($returnArray, array($this, "sortCartItems"));

# calculate promotional prices

# get Excluded products, category groups and departments

		$promotionRow = ShoppingCart::getCachedPromotionRow($this->iPromotionId);
		$limitEvents = (!empty($promotionRow['event_start_date']) || !empty($promotionRow['event_end_date']));

		$excludedProductIds = array();
		$excludedProductDepartmentIds = array();
		$excludedProductCategoryGroupIds = array();
		$resultSet = executeQuery("select product_id from promotion_rewards_excluded_products where promotion_id = ? union " .
			"select product_id from products where product_manufacturer_id is not null and product_manufacturer_id in (select product_manufacturer_id from promotion_rewards_excluded_product_manufacturers where promotion_id = ?) union " .
			"select product_id from products where product_type_id is not null and product_type_id in (select product_type_id from promotion_rewards_excluded_product_types where promotion_id = ?) union " .
			"select product_id from promotion_set_products where promotion_set_id in (select promotion_set_id from promotion_rewards_excluded_sets where promotion_id = ?) union " .
			"select product_id from product_category_links where product_category_id in (select product_category_id from promotion_rewards_excluded_product_categories where promotion_id = ?) union " .
			"select product_id from product_tag_links where (start_date is null or start_date <= current_date) and (expiration_date is null or expiration_date >= current_date) and " .
			"product_tag_id in (select product_tag_id from promotion_rewards_excluded_product_tags where promotion_id = ?)",
			$this->iPromotionId, $this->iPromotionId, $this->iPromotionId, $this->iPromotionId, $this->iPromotionId, $this->iPromotionId);
		while ($row = getNextRow($resultSet)) {
			$excludedProductIds[$row['product_id']] = $row['product_id'];
		}
		$resultSet = executeQuery("select product_department_id from promotion_rewards_excluded_product_departments where promotion_id = ?", $this->iPromotionId);
		while ($row = getNextRow($resultSet)) {
			$excludedProductDepartmentIds[] = $row['product_department_id'];
		}
		$resultSet = executeQuery("select product_category_group_id from promotion_rewards_excluded_product_category_groups where promotion_id = ?", $this->iPromotionId);
		while ($row = getNextRow($resultSet)) {
			$excludedProductCategoryGroupIds[] = $row['product_category_group_id'];
		}

		if ($limitEvents) {
			$resultSet = executeQuery("select product_id from events where product_id is not null and (" . (empty($promotionRow['event_start_date']) ? "" : "start_date < " . makeDateParameter($promotionRow['event_start_date'])) .
				(empty($promotionRow['event_end_date']) ? "" : (empty($promotionRow['event_start_date']) ? "" : " or ") . "start_date > " . makeDateParameter($promotionRow['event_end_date'])) . ")");
			while ($row = getNextRow($resultSet)) {
				$excludedProductIds[$row['product_id']] = $row['product_id'];
			}
		}

# Calculate prices for individual products

		$resultSet = executeQuery("select * from promotion_rewards_products where promotion_id = ?", $this->iPromotionId);
		while ($row = getNextRow($resultSet)) {
			$promotionArray = array();
			$promotionQuantity = (strlen($row['maximum_quantity']) == 0 ? PHP_INT_MAX : $row['maximum_quantity']);
			$promotionAmount = (strlen($row['maximum_amount']) == 0 ? PHP_INT_MAX : $row['maximum_amount']);
			foreach ($returnArray as $index => $thisItem) {
				if ($promotionQuantity <= 0 || $promotionAmount <= 0) {
					break;
				}
				if ($thisItem['product_id'] != $row['product_id']) {
					continue;
				}
				if (empty($row['apply_to_requirements'])) {
					$thisItem['quantity'] -= $thisItem['promotion_requirements'];
				}
				if ($thisItem['quantity'] <= 0) {
					continue;
				}
				if ($thisItem['sale_price'] > $promotionAmount) {
					continue;
				}
				if (array_key_exists($thisItem['product_id'], $excludedProductIds)) {
					continue;
				}
				$excludeProduct = false;
				foreach ($excludedProductDepartmentIds as $departmentId) {
					if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				foreach ($excludedProductCategoryGroupIds as $categoryGroupId) {
					if (ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $categoryGroupId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				$promotionalPrice = $thisItem['sale_price'];
				if (strlen($row['amount']) > 0 && $row['amount'] >= 0 && $row['amount'] < $promotionalPrice) {
					$promotionalPrice = $row['amount'];
				}
				if ($row['discount_percent'] > 0) {
					$thisPromotionalPrice = round($thisItem['sale_price'] * ((100 - $row['discount_percent']) / 100), 2);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				if ($row['discount_amount'] > 0) {
					$thisPromotionalPrice = max(0, $thisItem['sale_price'] - $row['discount_amount']);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				$currentPrice = (array_key_exists("promotional_price", $thisItem) ? $thisItem['promotional_price'] : $thisItem['sale_price']);
				if ($promotionalPrice < $currentPrice) {
					$usedQuantity = min($promotionQuantity, $thisItem['quantity'], ($promotionalPrice <= 0 ? PHP_INT_MAX : ceil($promotionAmount / $promotionalPrice)));
					$thisItem['quantity'] = $usedQuantity;
					$thisItem['promotional_price'] = $promotionalPrice;
					$returnArray[$index]['quantity'] -= $usedQuantity;
					if ($thisItem['promotion_requirements'] > 0) {
						$thisItem['promotion_requirements'] = min($thisItem['promotion_requirements'], $usedQuantity);
						$returnArray[$index]['promotion_requirements'] -= $thisItem['promotion_requirements'];
					}
					$promotionQuantity -= $usedQuantity;
					$promotionAmount -= ($usedQuantity * $promotionalPrice);
					$promotionArray[] = $thisItem;
				}
			}
			if ($row['add_to_cart'] && $promotionQuantity > 0) {
				$foundProduct = false;
				foreach ($this->iShoppingCartItems as $index => $thisItem) {
					if ($thisItem['product_id'] == $row['product_id']) {
						$foundProduct = true;
					}
				}
				if (!$foundProduct) {
					$promotionQuantity = (strlen($row['maximum_quantity']) == 0 ? 1 : $promotionQuantity);
					# add item to cart so that shipping is calculated correctly
					$this->addItem(array("product_id" => $row['product_id'], "quantity" => $promotionQuantity, "set_quantity" => true));
					$salePriceInfo = $productCatalog->getProductSalePrice($row['product_id'], array("product_information" => ProductCatalog::getCachedProductRow($row['product_id']), "quantity" => $promotionQuantity));
					$salePrice = $salePriceInfo['sale_price'];
					$priceCalculation = $salePriceInfo['price_calculation'];
					$promotionalPrice = $salePrice;
					if (strlen($row['amount']) > 0 && $row['amount'] >= 0 && $row['amount'] < $promotionalPrice) {
						$promotionalPrice = $row['amount'];
					}
					if ($row['discount_percent'] > 0) {
						$thisPromotionalPrice = round($salePrice * ((100 - $row['discount_percent']) / 100), 2);
						if ($thisPromotionalPrice < $promotionalPrice) {
							$promotionalPrice = $thisPromotionalPrice;
						}
					}
					if ($row['discount_amount'] > 0) {
						$thisPromotionalPrice = max(0, $salePrice - $row['discount_amount']);
						if ($thisPromotionalPrice < $promotionalPrice) {
							$promotionalPrice = $thisPromotionalPrice;
						}
					}
				}
			}
			$returnArray = $this->mergeItemArrays($returnArray, $promotionArray);
			usort($returnArray, array($this, "sortCartItems"));
		}

# Calculate sale price for product departments

		$resultSet = executeQuery("select * from promotion_rewards_product_departments where promotion_id = ?", $this->iPromotionId);
		while ($row = getNextRow($resultSet)) {
			$promotionArray = array();
			$promotionQuantity = (strlen($row['maximum_quantity']) == 0 ? PHP_INT_MAX : $row['maximum_quantity']);
			$promotionAmount = (strlen($row['maximum_amount']) == 0 ? PHP_INT_MAX : $row['maximum_amount']);
			foreach ($returnArray as $index => $thisItem) {
				if ($promotionQuantity <= 0 || $promotionAmount <= 0) {
					break;
				}
				if (empty($row['apply_to_requirements'])) {
					$thisItem['quantity'] -= $thisItem['promotion_requirements'];
				}
				if ($thisItem['quantity'] <= 0) {
					continue;
				}
				if ($thisItem['sale_price'] > $promotionAmount) {
					continue;
				}
				if (array_key_exists($thisItem['product_id'], $excludedProductIds)) {
					continue;
				}
				if (!ProductCatalog::productIsInDepartment($thisItem['product_id'], $row['product_department_id'])) {
					continue;
				}
				$excludeProduct = false;
				foreach ($excludedProductDepartmentIds as $departmentId) {
					if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				foreach ($excludedProductCategoryGroupIds as $categoryGroupId) {
					if (ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $categoryGroupId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}

				$promotionalPrice = $thisItem['sale_price'];
				if ($row['discount_percent'] > 0) {
					$thisPromotionalPrice = round($thisItem['sale_price'] * ((100 - $row['discount_percent']) / 100), 2);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				if ($row['discount_amount'] > 0) {
					$thisPromotionalPrice = max(0, $thisItem['sale_price'] - $row['discount_amount']);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				$currentPrice = (array_key_exists("promotional_price", $thisItem) ? $thisItem['promotional_price'] : $thisItem['sale_price']);
				if ($promotionalPrice < $currentPrice) {
					$usedQuantity = min($promotionQuantity, $thisItem['quantity'],
						($promotionalPrice <= 0 ? PHP_INT_MAX : ceil($promotionAmount / $promotionalPrice)));
					$thisItem['quantity'] = $usedQuantity;
					$thisItem['promotional_price'] = $promotionalPrice;
					$returnArray[$index]['quantity'] -= $usedQuantity;
					if ($thisItem['promotion_requirements'] > 0) {
						$thisItem['promotion_requirements'] = min($thisItem['promotion_requirements'], $usedQuantity);
						$returnArray[$index]['promotion_requirements'] -= $thisItem['promotion_requirements'];
					}
					$promotionQuantity -= $usedQuantity;
					$promotionAmount -= ($usedQuantity * $promotionalPrice);
					$promotionArray[] = $thisItem;
				}
			}
			$returnArray = $this->mergeItemArrays($returnArray, $promotionArray);
			usort($returnArray, array($this, "sortCartItems"));
		}

# Calculate sale price for product categories

		$resultSet = executeQuery("select * from promotion_rewards_product_category_groups where promotion_id = ?", $this->iPromotionId);
		while ($row = getNextRow($resultSet)) {
			$promotionArray = array();
			$promotionQuantity = (strlen($row['maximum_quantity']) == 0 ? PHP_INT_MAX : $row['maximum_quantity']);
			$promotionAmount = (strlen($row['maximum_amount']) == 0 ? PHP_INT_MAX : $row['maximum_amount']);
			foreach ($returnArray as $index => $thisItem) {
				if ($promotionQuantity <= 0 || $promotionAmount <= 0) {
					break;
				}
				if (empty($row['apply_to_requirements'])) {
					$thisItem['quantity'] -= $thisItem['promotion_requirements'];
				}
				if ($thisItem['quantity'] <= 0) {
					continue;
				}
				if ($thisItem['sale_price'] > $promotionAmount) {
					continue;
				}
				if (array_key_exists($thisItem['product_id'], $excludedProductIds)) {
					continue;
				}
				if (!ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $row['product_category_group_id'])) {
					continue;
				}
				$excludeProduct = false;
				foreach ($excludedProductDepartmentIds as $departmentId) {
					if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				foreach ($excludedProductCategoryGroupIds as $categoryGroupId) {
					if (ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $categoryGroupId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}

				$promotionalPrice = $thisItem['sale_price'];
				if ($row['discount_percent'] > 0) {
					$thisPromotionalPrice = round($thisItem['sale_price'] * ((100 - $row['discount_percent']) / 100), 2);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				if ($row['discount_amount'] > 0) {
					$thisPromotionalPrice = max(0, $thisItem['sale_price'] - $row['discount_amount']);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				$currentPrice = (array_key_exists("promotional_price", $thisItem) ? $thisItem['promotional_price'] : $thisItem['sale_price']);
				if ($promotionalPrice < $currentPrice) {
					$usedQuantity = min($promotionQuantity, $thisItem['quantity'],
						($promotionalPrice <= 0 ? PHP_INT_MAX : ceil($promotionAmount / $promotionalPrice)));
					$thisItem['quantity'] = $usedQuantity;
					$thisItem['promotional_price'] = $promotionalPrice;
					$returnArray[$index]['quantity'] -= $usedQuantity;
					if ($thisItem['promotion_requirements'] > 0) {
						$thisItem['promotion_requirements'] = min($thisItem['promotion_requirements'], $usedQuantity);
						$returnArray[$index]['promotion_requirements'] -= $thisItem['promotion_requirements'];
					}
					$promotionQuantity -= $usedQuantity;
					$promotionAmount -= ($usedQuantity * $promotionalPrice);
					$promotionArray[] = $thisItem;
				}
			}
			$returnArray = $this->mergeItemArrays($returnArray, $promotionArray);
			usort($returnArray, array($this, "sortCartItems"));
		}

# Calculate sale price for product categories

		$resultSet = executeQuery("select * from promotion_rewards_product_categories where promotion_id = ?", $this->iPromotionId);
		while ($row = getNextRow($resultSet)) {
			$promotionArray = array();
			$promotionQuantity = (strlen($row['maximum_quantity']) == 0 ? PHP_INT_MAX : $row['maximum_quantity']);
			$promotionAmount = (strlen($row['maximum_amount']) == 0 ? PHP_INT_MAX : $row['maximum_amount']);
			foreach ($returnArray as $index => $thisItem) {
				if ($promotionQuantity <= 0 || $promotionAmount <= 0) {
					break;
				}
				if (empty($row['apply_to_requirements'])) {
					$thisItem['quantity'] -= $thisItem['promotion_requirements'];
				}
				if ($thisItem['quantity'] <= 0) {
					continue;
				}
				if ($thisItem['sale_price'] > $promotionAmount) {
					continue;
				}
				if (array_key_exists($thisItem['product_id'], $excludedProductIds)) {
					continue;
				}
				if (!ProductCatalog::productIsInCategory($thisItem['product_id'], $row['product_category_id'])) {
					continue;
				}
				$excludeProduct = false;
				foreach ($excludedProductDepartmentIds as $departmentId) {
					if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				foreach ($excludedProductCategoryGroupIds as $categoryGroupId) {
					if (ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $categoryGroupId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}

				$promotionalPrice = $thisItem['sale_price'];
				if ($row['discount_percent'] > 0) {
					$thisPromotionalPrice = round($thisItem['sale_price'] * ((100 - $row['discount_percent']) / 100), 2);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				if ($row['discount_amount'] > 0) {
					$thisPromotionalPrice = max(0, $thisItem['sale_price'] - $row['discount_amount']);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				$currentPrice = (array_key_exists("promotional_price", $thisItem) ? $thisItem['promotional_price'] : $thisItem['sale_price']);
				if ($promotionalPrice < $currentPrice) {
					$usedQuantity = min($promotionQuantity, $thisItem['quantity'],
						($promotionalPrice <= 0 ? PHP_INT_MAX : ceil($promotionAmount / $promotionalPrice)));
					$thisItem['quantity'] = $usedQuantity;
					$thisItem['promotional_price'] = $promotionalPrice;
					$returnArray[$index]['quantity'] -= $usedQuantity;
					if ($thisItem['promotion_requirements'] > 0) {
						$thisItem['promotion_requirements'] = min($thisItem['promotion_requirements'], $usedQuantity);
						$returnArray[$index]['promotion_requirements'] -= $thisItem['promotion_requirements'];
					}
					$promotionQuantity -= $usedQuantity;
					$promotionAmount -= ($usedQuantity * $promotionalPrice);
					$promotionArray[] = $thisItem;
				}
			}
			$returnArray = $this->mergeItemArrays($returnArray, $promotionArray);
			usort($returnArray, array($this, "sortCartItems"));
		}

# Calculate sale price for product tags

		$resultSet = executeQuery("select * from promotion_rewards_product_tags where promotion_id = ?", $this->iPromotionId);
		while ($row = getNextRow($resultSet)) {
			$promotionArray = array();
			$promotionQuantity = (strlen($row['maximum_quantity']) == 0 ? PHP_INT_MAX : $row['maximum_quantity']);
			$promotionAmount = (strlen($row['maximum_amount']) == 0 ? PHP_INT_MAX : $row['maximum_amount']);
			foreach ($returnArray as $index => $thisItem) {
				if ($promotionQuantity <= 0 || $promotionAmount <= 0) {
					break;
				}
				if (empty($row['apply_to_requirements'])) {
					$thisItem['quantity'] -= $thisItem['promotion_requirements'];
				}
				if ($thisItem['quantity'] <= 0) {
					continue;
				}
				if ($thisItem['sale_price'] > $promotionAmount) {
					continue;
				}
				if (array_key_exists($thisItem['product_id'], $excludedProductIds)) {
					continue;
				}
				if (!ProductCatalog::productIsTagged($thisItem['product_id'], $row['product_tag_id'])) {
					continue;
				}
				$excludeProduct = false;
				foreach ($excludedProductDepartmentIds as $departmentId) {
					if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				foreach ($excludedProductCategoryGroupIds as $categoryGroupId) {
					if (ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $categoryGroupId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}

				$promotionalPrice = $thisItem['sale_price'];
				if ($row['discount_percent'] > 0) {
					$thisPromotionalPrice = round($thisItem['sale_price'] * ((100 - $row['discount_percent']) / 100), 2);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				if ($row['discount_amount'] > 0) {
					$thisPromotionalPrice = max(0, $thisItem['sale_price'] - $row['discount_amount']);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				$currentPrice = (array_key_exists("promotional_price", $thisItem) ? $thisItem['promotional_price'] : $thisItem['sale_price']);
				if ($promotionalPrice < $currentPrice) {
					$usedQuantity = min($promotionQuantity, $thisItem['quantity'],
						($promotionalPrice <= 0 ? PHP_INT_MAX : ceil($promotionAmount / $promotionalPrice)));
					$thisItem['quantity'] = $usedQuantity;
					$thisItem['promotional_price'] = $promotionalPrice;
					$returnArray[$index]['quantity'] -= $usedQuantity;
					if ($thisItem['promotion_requirements'] > 0) {
						$thisItem['promotion_requirements'] = min($thisItem['promotion_requirements'], $usedQuantity);
						$returnArray[$index]['promotion_requirements'] -= $thisItem['promotion_requirements'];
					}
					$promotionQuantity -= $usedQuantity;
					$promotionAmount -= ($usedQuantity * $promotionalPrice);
					$promotionArray[] = $thisItem;
				}
			}
			$returnArray = $this->mergeItemArrays($returnArray, $promotionArray);
			usort($returnArray, array($this, "sortCartItems"));
		}

# Calculate sale price for product manufacturers

		$resultSet = executeQuery("select * from promotion_rewards_product_manufacturers where promotion_id = ?", $this->iPromotionId);
		while ($row = getNextRow($resultSet)) {
			$promotionArray = array();
			$promotionQuantity = (strlen($row['maximum_quantity']) == 0 ? PHP_INT_MAX : $row['maximum_quantity']);
			$promotionAmount = (strlen($row['maximum_amount']) == 0 ? PHP_INT_MAX : $row['maximum_amount']);
			foreach ($returnArray as $index => $thisItem) {
				if ($promotionQuantity <= 0 || $promotionAmount <= 0) {
					break;
				}
				if (empty($row['apply_to_requirements'])) {
					$thisItem['quantity'] -= $thisItem['promotion_requirements'];
				}
				if ($thisItem['quantity'] <= 0) {
					continue;
				}
				if ($thisItem['sale_price'] > $promotionAmount) {
					continue;
				}
				if (array_key_exists($thisItem['product_id'], $excludedProductIds)) {
					continue;
				}
				$productManufacturerId = getFieldFromId("product_manufacturer_id", "products", "product_id", $thisItem['product_id']);
				if ($row['product_manufacturer_id'] != $productManufacturerId) {
					continue;
				}
				$excludeProduct = false;
				foreach ($excludedProductDepartmentIds as $departmentId) {
					if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				foreach ($excludedProductCategoryGroupIds as $categoryGroupId) {
					if (ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $categoryGroupId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}

				$promotionalPrice = $thisItem['sale_price'];
				if ($row['discount_percent'] > 0) {
					$thisPromotionalPrice = round($thisItem['sale_price'] * ((100 - $row['discount_percent']) / 100), 2);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				if ($row['discount_amount'] > 0) {
					$thisPromotionalPrice = max(0, $thisItem['sale_price'] - $row['discount_amount']);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				$currentPrice = (array_key_exists("promotional_price", $thisItem) ? $thisItem['promotional_price'] : $thisItem['sale_price']);
				if ($promotionalPrice < $currentPrice) {
					$usedQuantity = min($promotionQuantity, $thisItem['quantity'],
						($promotionalPrice <= 0 ? PHP_INT_MAX : ceil($promotionAmount / $promotionalPrice)));
					$thisItem['quantity'] = $usedQuantity;
					$thisItem['promotional_price'] = $promotionalPrice;
					$returnArray[$index]['quantity'] -= $usedQuantity;
					if ($thisItem['promotion_requirements'] > 0) {
						$thisItem['promotion_requirements'] = min($thisItem['promotion_requirements'], $usedQuantity);
						$returnArray[$index]['promotion_requirements'] -= $thisItem['promotion_requirements'];
					}
					$promotionQuantity -= $usedQuantity;
					$promotionAmount -= ($usedQuantity * $promotionalPrice);
					$promotionArray[] = $thisItem;
				}
			}
			$returnArray = $this->mergeItemArrays($returnArray, $promotionArray);
			usort($returnArray, array($this, "sortCartItems"));
		}

# Calculate sale price for product types

		$resultSet = executeQuery("select * from promotion_rewards_product_types where promotion_id = ?", $this->iPromotionId);
		while ($row = getNextRow($resultSet)) {
			$promotionArray = array();
			$promotionQuantity = (strlen($row['maximum_quantity']) == 0 ? PHP_INT_MAX : $row['maximum_quantity']);
			$promotionAmount = (strlen($row['maximum_amount']) == 0 ? PHP_INT_MAX : $row['maximum_amount']);
			foreach ($returnArray as $index => $thisItem) {
				if ($promotionQuantity <= 0 || $promotionAmount <= 0) {
					break;
				}
				if (empty($row['apply_to_requirements'])) {
					$thisItem['quantity'] -= $thisItem['promotion_requirements'];
				}
				if ($thisItem['quantity'] <= 0) {
					continue;
				}
				if ($thisItem['sale_price'] > $promotionAmount) {
					continue;
				}
				if (array_key_exists($thisItem['product_id'], $excludedProductIds)) {
					continue;
				}
				$productTypeId = getFieldFromId("product_type_id", "products", "product_id", $thisItem['product_id']);
				if ($row['product_type_id'] != $productTypeId) {
					continue;
				}
				$excludeProduct = false;
				foreach ($excludedProductDepartmentIds as $departmentId) {
					if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				foreach ($excludedProductCategoryGroupIds as $categoryGroupId) {
					if (ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $categoryGroupId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}

				$promotionalPrice = $thisItem['sale_price'];
				if ($row['discount_percent'] > 0) {
					$thisPromotionalPrice = round($thisItem['sale_price'] * ((100 - $row['discount_percent']) / 100), 2);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				if ($row['discount_amount'] > 0) {
					$thisPromotionalPrice = max(0, $thisItem['sale_price'] - $row['discount_amount']);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				$currentPrice = (array_key_exists("promotional_price", $thisItem) ? $thisItem['promotional_price'] : $thisItem['sale_price']);
				if ($promotionalPrice < $currentPrice) {
					$usedQuantity = min($promotionQuantity, $thisItem['quantity'],
						($promotionalPrice <= 0 ? PHP_INT_MAX : ceil($promotionAmount / $promotionalPrice)));
					$thisItem['quantity'] = $usedQuantity;
					$thisItem['promotional_price'] = $promotionalPrice;
					$returnArray[$index]['quantity'] -= $usedQuantity;
					if ($thisItem['promotion_requirements'] > 0) {
						$thisItem['promotion_requirements'] = min($thisItem['promotion_requirements'], $usedQuantity);
						$returnArray[$index]['promotion_requirements'] -= $thisItem['promotion_requirements'];
					}
					$promotionQuantity -= $usedQuantity;
					$promotionAmount -= ($usedQuantity * $promotionalPrice);
					$promotionArray[] = $thisItem;
				}
			}
			$returnArray = $this->mergeItemArrays($returnArray, $promotionArray);
			usort($returnArray, array($this, "sortCartItems"));
		}

# Calculate sale price for promotion sets

		$resultSet = executeQuery("select * from promotion_rewards_sets where promotion_id = ?", $this->iPromotionId);
		while ($row = getNextRow($resultSet)) {
			$promotionArray = array();
			$promotionQuantity = (strlen($row['maximum_quantity']) == 0 ? PHP_INT_MAX : $row['maximum_quantity']);
			$promotionAmount = (strlen($row['maximum_amount']) == 0 ? PHP_INT_MAX : $row['maximum_amount']);
			foreach ($returnArray as $index => $thisItem) {
				if ($promotionQuantity <= 0 || $promotionAmount <= 0) {
					break;
				}
				if (empty($row['apply_to_requirements'])) {
					$thisItem['quantity'] -= $thisItem['promotion_requirements'];
				}
				if ($thisItem['quantity'] <= 0) {
					continue;
				}
				if ($thisItem['sale_price'] > $promotionAmount) {
					continue;
				}
				if (array_key_exists($thisItem['product_id'], $excludedProductIds)) {
					continue;
				}
				$promotionSetProductId = getFieldFromId("promotion_set_product_id", "promotion_set_products", "product_id", $thisItem['product_id'], "promotion_set_id = ?", $row['promotion_set_id']);
				if (empty($promotionSetProductId)) {
					continue;
				}
				$excludeProduct = false;
				foreach ($excludedProductDepartmentIds as $departmentId) {
					if (ProductCatalog::productIsInDepartment($thisItem['product_id'], $departmentId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}
				foreach ($excludedProductCategoryGroupIds as $categoryGroupId) {
					if (ProductCatalog::productIsInCategoryGroup($thisItem['product_id'], $categoryGroupId)) {
						$excludeProduct = true;
						break;
					}
				}
				if ($excludeProduct) {
					continue;
				}

				$promotionalPrice = $thisItem['sale_price'];
				if ($row['discount_percent'] > 0) {
					$thisPromotionalPrice = round($thisItem['sale_price'] * ((100 - $row['discount_percent']) / 100), 2);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				if ($row['discount_amount'] > 0) {
					$thisPromotionalPrice = max(0, $thisItem['sale_price'] - $row['discount_amount']);
					if ($thisPromotionalPrice < $promotionalPrice) {
						$promotionalPrice = $thisPromotionalPrice;
					}
				}
				$currentPrice = (array_key_exists("promotional_price", $thisItem) ? $thisItem['promotional_price'] : $thisItem['sale_price']);
				if ($promotionalPrice < $currentPrice) {
					$usedQuantity = min($promotionQuantity, $thisItem['quantity'],
						($promotionalPrice <= 0 ? PHP_INT_MAX : ceil($promotionAmount / $promotionalPrice)));
					$thisItem['quantity'] = $usedQuantity;
					$thisItem['promotional_price'] = $promotionalPrice;
					$returnArray[$index]['quantity'] -= $usedQuantity;
					if ($thisItem['promotion_requirements'] > 0) {
						$thisItem['promotion_requirements'] = min($thisItem['promotion_requirements'], $usedQuantity);
						$returnArray[$index]['promotion_requirements'] -= $thisItem['promotion_requirements'];
					}
					$promotionQuantity -= $usedQuantity;
					$promotionAmount -= ($usedQuantity * $promotionalPrice);
					$promotionArray[] = $thisItem;
				}
			}
			$returnArray = $this->mergeItemArrays($returnArray, $promotionArray);
			usort($returnArray, array($this, "sortCartItems"));
		}

		foreach ($returnArray as $index => $shoppingCartItem) {
			$returnArray[$index]['original_sale_price'] = "";
			if (array_key_exists("promotional_price", $shoppingCartItem)) {
				$returnArray[$index]['original_sale_price'] = $shoppingCartItem['sale_price'];
				foreach ($returnArray[$index]['product_addons'] as $addon) {
					$returnArray[$index]['original_sale_price'] += $addon['sale_price'];
				}
				$returnArray[$index]['sale_price'] = $shoppingCartItem['promotional_price'];
			}
			$returnArray[$index]['product_addons'] = array();
			$resultSet = executeQuery("select *, shopping_cart_item_addons.sale_price item_price from shopping_cart_item_addons join product_addons using (product_addon_id) where shopping_cart_item_id = ? and inactive = 0" . ($GLOBALS['gUserRow']['administrator_flag'] ? "" : " and internal_use_only = 0"), $shoppingCartItem['shopping_cart_item_id']);
			while ($row = getNextRow($resultSet)) {
				$row['sale_price'] = $row['item_price'];
				if (!empty($row['inventory_product_id'])) {
					$productCatalog = new ProductCatalog();
					$addonInventoryCounts = $productCatalog->getInventoryCounts(true, $row['inventory_product_id']);
					if (empty($addonInventoryCounts[$row['inventory_product_id']]) || $addonInventoryCounts[$row['inventory_product_id']] < 0) {
						executeQuery("delete from shopping_cart_item_addons where shopping_cart_item_addon_id = ?", $row['shopping_cart_item_addon_id']);
						continue;
					}
				}
				$returnArray[$index]['product_addons'][] = $row;
			}
		}
		$this->iShoppingCartItemCache[$cacheKey] = $returnArray;
		return $returnArray;
	}

	/**
	 * removeProduct - remove this product from the shopping cart
	 * @param
	 *    product ID
	 * @return
	 *    none
	 */
	function removeProduct($productId) {
		foreach ($this->iShoppingCartItems as $index => $thisItem) {
			if ($thisItem['product_id'] != $productId) {
				continue;
			}
			$this->iShoppingCartItems[$index]['quantity'] = 0;
		}
		$this->iShoppingCart['last_activity'] = date("Y-m-d H:i:s");
		return $this->writeShoppingCart();
	}

	private function loadShoppingCart($shoppingCartId) {
		$resultSet = executeQuery("select * from shopping_carts where shopping_cart_id = ?", $shoppingCartId);
		if ($row = getNextRow($resultSet)) {
			$this->iShoppingCart = $row;
			$this->iPromotionId = $row['promotion_id'];
			$this->iShoppingCartItems = array();
			$this->iShoppingCartId = $row['shopping_cart_id'];
			$this->cleanShoppingCart($this->iShoppingCartId);

			$productAddons = array();
			$addonSet = executeQuery("select * from product_addons join shopping_cart_item_addons using (product_addon_id) where " .
				"shopping_cart_item_id in (select shopping_cart_item_id from shopping_cart_items where shopping_cart_id = ?) and " .
				"inactive = 0" . ($GLOBALS['gUserRow']['administrator_flag'] ? "" : " and internal_use_only = 0") . " order by sort_order", $row['shopping_cart_id']);
			while ($addonRow = getNextRow($addonSet)) {
				if (!empty($addonRow['inventory_product_id'])) {
					$productCatalog = new ProductCatalog();
					$addonInventoryCounts = $productCatalog->getInventoryCounts(true, $addonRow['inventory_product_id']);
					if (empty($addonInventoryCounts[$addonRow['inventory_product_id']]) || $addonInventoryCounts[$addonRow['inventory_product_id']] < 0) {
						executeQuery("delete from shopping_cart_item_addons where shopping_cart_item_addon_id = ?", $addonRow['shopping_cart_item_addon_id']);
						continue;
					}
				}
				if (!array_key_exists($addonRow['shopping_cart_item_id'], $productAddons)) {
					$productAddons[$addonRow['shopping_cart_item_id']] = array();
				}
				$productAddons[$addonRow['shopping_cart_item_id']][$addonRow['product_addon_id']] = $addonRow;
			}
			if ($this->iAlwaysAddProducts === false) {
				$this->iAlwaysAddProducts = array();
				$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "ALWAYS_ADD", "inactive = 0 and " .
					"custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'PRODUCTS')");
				$alwaysAddSet = executeQuery("select primary_identifier from custom_field_data where custom_field_id = ? and text_data = '1'", $customFieldId);
				while ($alwaysAddRow = getNextRow($alwaysAddSet)) {
					$this->iAlwaysAddProducts[$alwaysAddRow['primary_identifier']] = $alwaysAddRow['primary_identifier'];
				}
				freeResult($alwaysAddSet);
			}

			$resultSet = executeQuery("select * from shopping_cart_items where shopping_cart_id = ? order by shopping_cart_item_id", $shoppingCartId);
			while ($row = getNextRow($resultSet)) {
				foreach ($this->iShoppingCartItems as $index => $cartItem) {
					if ($row['product_id'] == $cartItem['product_id'] && !array_key_exists($cartItem['shopping_cart_item_id'], $productAddons) && !array_key_exists($row['shopping_cart_item_id'], $productAddons) && !array_key_exists($row['product_id'], $this->iAlwaysAddProducts)) {
						$this->iShoppingCartItems[$index]['quantity'] += $row['quantity'];
						executeQuery("update shopping_cart_items set quantity = ? where shopping_cart_item_id = ?", $this->iShoppingCartItems[$index]['quantity'], $this->iShoppingCartItems[$index]['shopping_cart_item_id']);
						executeQuery("delete from shopping_cart_items where shopping_cart_item_id = ?", $row['shopping_cart_item_id']);
						continue 2;
					}
				}
				$row['product_addons'] = (array_key_exists($row['shopping_cart_item_id'], $productAddons) ? $productAddons[$row['shopping_cart_item_id']] : array());
				$row['addon_count'] = count($row['product_addons']);
				$this->iShoppingCartItems[] = $row;
			}
			if (!empty($this->iPromotionId) && !$this->isPromotionValid()) {
				$this->iPromotionId = "";
				executeQuery("update shopping_carts set promotion_id = null where shopping_cart_id = ?", $shoppingCartId);
			}
			if (empty($this->iPromotionId)) {
				$automaticPromotions = getCachedData("automatic_promotions", "");
				if (!is_array($automaticPromotions)) {
					$automaticPromotions = array();
					$resultSet = executeQuery("select * from promotions where start_date <= current_date and apply_automatically = 1 and " .
						"(expiration_date is null or expiration_date >= current_date) and inactive = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
					while ($row = getNextRow($resultSet)) {
						$automaticPromotions[] = $row;
					}
					setCachedData("automatic_promotions", "", $automaticPromotions);
				}
				foreach ($automaticPromotions as $row) {
					$this->iPromotionId = $this->iShoppingCart['promotion_id'] = $row['promotion_id'];
					if ($this->isPromotionValid()) {
						executeQuery("update shopping_carts set promotion_id = ? where shopping_cart_id = ?", $this->iPromotionId, $shoppingCartId);
						break;
					} else {
						$this->iPromotionId = $this->iShoppingCart['promotion_id'] = "";
					}
				}
			}
		} else {
			$this->iShoppingCart = array();
			$this->iShoppingCartItems = array();
			$this->iShoppingCartId = "";
		}
		return $this->iShoppingCart;
	}

	/**
	 * Clear items that should not be accessible to the user. Allow for a local version of this function in case the developer wants
	 * to override the default behavior. The localized version should return true if it has completed cleaning the shopping cart and not further
	 * cleaning needs to be done.
	 *
	 * @param $shoppingCartId
	 */
	private function cleanShoppingCart($shoppingCartId) {
		if (function_exists("_localCleanShoppingCart")) {
			if (_localCleanShoppingCart($shoppingCartId)) {
				return;
			}
		}

		$userGroupIds = array();
		if ($GLOBALS['gLoggedIn']) {
			$resultSet = executeQuery("select * from user_group_members where user_id = ?", $GLOBALS['gUserId']);
			while ($row = getNextRow($resultSet)) {
				$userGroupIds[] = $row['user_group_id'];
			}
		}

		$shoppingCartItemIds = array();
		# inactive products
		$resultSet = executeQuery("select * from shopping_cart_items where shopping_cart_id = ? and product_id in (" .
			# inactive products
			"select product_id from products where inactive = 1" .
			# internal use only products
			($GLOBALS['gInternalConnection'] ? "" : " or internal_use_only = 1") .
			# product is expired
			" or expiration_date < current_date" .
			# manufacturers set to cannot sell
			" or product_manufacturer_id in (select product_manufacturer_id from product_manufacturers where cannot_sell = 1)" .
			# manufacturers set to require user
			($GLOBALS['gLoggedIn'] ? "" : " or product_manufacturer_id in (select product_manufacturer_id from product_manufacturers where requires_user = 1)") .
			# manufacturers set to be restricted for user type
			(empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or product_manufacturer_id in (select product_manufacturer_id from user_type_product_manufacturer_restrictions where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") .
			# product tags set to be restricted for user type
			(empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or product_id in (select product_id from product_tag_links where product_tag_id in (select product_tag_id from user_type_product_tag_restrictions where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . "))") .
			# categories set to cannot sell or inactive or internal use only
			" or product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from product_categories where cannot_sell = 1 or product_category_code in ('INACTIVE','INTERNAL_USE_ONLY','DISCONTINUED')))" .
			# product tags set to cannot sell
			" or product_id in (select product_id from product_tag_links where product_tag_id in (select product_tag_id from product_tags where cannot_sell = 1))" .
			# product tag set to require user
			($GLOBALS['gLoggedIn'] ? "" : " or product_id in (select product_id from product_tag_links where product_tag_id in (select product_tag_id from product_tags where requires_user = 1))") .
			# products that can only be purchased by user group defined in the product
			" or (user_group_id is not null" . (empty($userGroupIds) ? "" : " and user_group_id not in (" . implode(",", $userGroupIds) . ")") . ")" .
			" or product_id in (select product_id from product_category_links where product_category_id in (select product_category_id from product_categories where (user_group_id is not null" . (empty($userGroupIds) ? "" : " and user_group_id not in (" . implode(",", $userGroupIds) . ")") . ")))" .
			")", $shoppingCartId);
		while ($row = getNextRow($resultSet)) {
			$productCategoryLinkId = getFieldFromId("product_category_link_id", "product_category_links", "product_id", $row['product_id'],
				"product_category_id in (select product_category_id from product_categories where product_category_code = 'ALLOW_IN_CART')");
			if (empty($productCategoryLinkId)) {
				$shoppingCartItemIds[] = $row['shopping_cart_item_id'];
			}
		}
		# shopping cart items with an addon that requires a form but no form attached
		$resultSet = executeQuery("select * from shopping_cart_items join shopping_cart_item_addons using (shopping_cart_item_id) where shopping_cart_id = ?" .
			" and content is null and product_addon_id in (select product_addon_id from product_addons where form_definition_id is not null)", $shoppingCartId);
		while ($row = getNextRow($resultSet)) {
			$shoppingCartItemIds[] = $row['shopping_cart_item_id'];
		}

		if (!empty($shoppingCartItemIds)) {
			executeQuery("delete from shopping_cart_item_addons where shopping_cart_item_id in (" . implode(",", $shoppingCartItemIds) . ")");
			executeQuery("delete from shopping_cart_items where shopping_cart_item_id in (" . implode(",", $shoppingCartItemIds) . ")");
		}
		$inactiveProductAddons = getCachedData("inactive_product_addons", "inactive_product_addons");
		if (!is_array($inactiveProductAddons)) {
			$inactiveProductAddons = array();
			$resultSet = executeQuery("select product_addon_id from product_addons where inactive = 1 and product_id in (select product_id from products where client_id = ?)", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				$inactiveProductAddons[] = $row['product_addon_id'];
			}
			setCachedData("inactive_product_addons", "inactive_product_addons", $inactiveProductAddons);
		}
		if (!empty($inactiveProductAddons)) {
			if (count($inactiveProductAddons) < 1000) {
				executeQuery("delete from shopping_cart_item_addons where product_addon_id in (" . implode(",", $inactiveProductAddons) . ") and shopping_cart_item_id in (select shopping_cart_item_id from shopping_cart_items where shopping_cart_id = ?)", $shoppingCartId);
			} else {
				executeQuery("delete from shopping_cart_item_addons where product_addon_id in (select product_addon_id from product_addons where inactive = 1) and shopping_cart_item_id in (select shopping_cart_item_id from shopping_cart_items where shopping_cart_id = ?)", $shoppingCartId);
			}
		}
	}

	private function writeShoppingCart() {
		$shoppingCartDataSource = new DataSource("shopping_carts");
		$shoppingCartDataSource->disableTransactions();
		$shoppingCartDataSource->setSaveOnlyPresent(true);
		$shoppingCartItemDataSource = new DataSource("shopping_cart_items");
		$shoppingCartItemDataSource->disableTransactions();
		$shoppingCartItemDataSource->setSaveOnlyPresent(true);
		$this->iShoppingCart['contact_id'] = ($GLOBALS['gLoggedIn'] && empty($this->iShoppingCart['contact_id']) ? $GLOBALS['gUserRow']['contact_id'] : $this->iShoppingCart['contact_id']);
		$oldShoppingCart = getRowFromId("shopping_carts", "shopping_cart_id", $this->iShoppingCartId);
		$updateFieldArray = array("description", "shopping_cart_code", "abandon_email_sent", "user_id", "contact_id", "notes", "purchase_order_number", "promotion_id", "promotion_code", "start_time", "last_activity");
		$nameValues = array();
		foreach ($updateFieldArray as $fieldName) {
			if ($this->iShoppingCart[$fieldName] != $oldShoppingCart[$fieldName]) {
				$nameValues[$fieldName] = $this->iShoppingCart[$fieldName];
			}
		}
		if ($oldShoppingCart['promotion_id'] != $this->iPromotionId) {
			$nameValues['promotion_id'] = $this->iPromotionId;
			$this->iShoppingCart['promotion_id'] = $this->iPromotionId;
		}
		if (empty($this->iShoppingCartId) && !empty($nameValues['contact_id'])) {
			$this->iShoppingCartId = getFieldFromId("shopping_cart_id", "shopping_carts", "contact_id", $nameValues['contact_id'], "shopping_cart_code <=> ?", $nameValues['shopping_cart_code']);
		}
		if (empty($this->iShoppingCartId)) {
			$nameValues['last_activity'] = date("Y-m-d H:i:s");
			$nameValues['date_created'] = date("Y-m-d");
		}
		$nameValues['client_id'] = $GLOBALS['gClientId'];

		$resultId = $shoppingCartDataSource->saveRecord(array("name_values" => $nameValues, "primary_id" => $this->iShoppingCartId, "no_change_log" => true));
		if (!$resultId) {
			$this->iErrorMessage = $shoppingCartDataSource->getErrorMessage() . ":" . json_encode($nameValues);
			return false;
		}
		$this->iShoppingCartId = $resultId;
		if (!$GLOBALS['gLoggedIn']) {
			setCoreCookie("shopping_cart_id", $this->iShoppingCartId, (24 * 365));
			$_COOKIE['shopping_cart_id'] = $this->iShoppingCartId;
		}
		foreach ($this->iShoppingCartItems as $index => $thisItem) {
			if (empty($thisItem['product_id'])) {
				unset($this->iShoppingCartItems[$index]);
				continue;
			}
			if (empty($thisItem['shopping_cart_item_id'])) {
				if ($thisItem['quantity'] > 0) {
					if (empty($thisItem['time_submitted'])) {
						$thisItem['time_submitted'] = date("Y-m-d H:i:s");
					}
					$resultId = $shoppingCartItemDataSource->saveRecord(array("name_values" => array("shopping_cart_id" => $this->iShoppingCartId,
						"product_id" => $thisItem['product_id'], "location_id" => $thisItem['location_id'], "serial_number" => $thisItem['serial_number'],
						"time_submitted" => $thisItem['time_submitted'], "quantity" => $thisItem['quantity'], "sale_price" => $thisItem['sale_price']), "primary_id" => "", "no_change_log" => true));
					if (!$resultId) {
						$this->iErrorMessage = $shoppingCartItemDataSource->getErrorMessage();
						unset($this->iShoppingCartItems[$index]);
					} else {
						$this->iShoppingCartItems[$index]['shopping_cart_item_id'] = $resultId;
					}
					if (is_array($thisItem['product_addons'])) {
						foreach ($thisItem['product_addons'] as $thisAddon) {
							$productAddonRow = getRowFromId("product_addons", "product_addon_id", $thisAddon['product_addon_id'], "product_id = ?", $thisItem['product_id'], "inactive = 0" . ($GLOBALS['gUserRow']['administrator_flag'] ? "" : " and internal_use_only = 0"));
							if (empty($productAddonRow)) {
								continue;
							}
							$quantity = min($productAddonRow['maximum_quantity'], $thisAddon['quantity']);
							if (empty($quantity) || !is_numeric($quantity) || $quantity <= 0) {
								$quantity = 1;
							}
							if (!empty($thisAddon['content'])) {
								$content = json_decode($thisAddon['content'], true);
								if (is_array($content) && array_key_exists("sale_price", $content)) {
									$productAddonRow['sale_price'] = $content['sale_price'] ?: 0.0;
								}

							}
							executeQuery("insert into shopping_cart_item_addons (shopping_cart_item_id,product_addon_id,quantity,sale_price,content) values (?,?,?,?,?)", $resultId, $productAddonRow['product_addon_id'], $quantity, $productAddonRow['sale_price'], $thisAddon['content']);
						}
					}
				} else {
					unset($this->iShoppingCartItems[$index]);
				}
			} else {
				executeQuery("delete from shopping_cart_item_addons where shopping_cart_item_id = ?", $thisItem['shopping_cart_item_id']);
				if ($thisItem['quantity'] > 0) {
					$shoppingCartItemDataSource->saveRecord(array("name_values" => array("shopping_cart_id" => $this->iShoppingCartId, "product_id" => $thisItem['product_id'],
						"location_id" => $thisItem['location_id'], "serial_number" => $thisItem['serial_number'], "quantity" => $thisItem['quantity'], "sale_price" => $thisItem['sale_price']), "primary_id" => $thisItem['shopping_cart_item_id'], "no_change_log" => true));
					if (is_array($thisItem['product_addons'])) {
						foreach ($thisItem['product_addons'] as $thisAddon) {
							$productAddonRow = getRowFromId("product_addons", "product_addon_id", $thisAddon['product_addon_id'], "product_id = ? and inactive = 0" . ($GLOBALS['gUserRow']['administrator_flag'] ? "" : " and internal_use_only = 0"), $thisItem['product_id']);
							if (empty($productAddonRow)) {
								continue;
							}
							$quantity = min($productAddonRow['maximum_quantity'], $thisAddon['quantity']);
							if (empty($quantity) || !is_numeric($quantity) || $quantity <= 0) {
								$quantity = 1;
							}
							if (!empty($thisAddon['content'])) {
								$content = json_decode($thisAddon['content'], true);
								if (is_array($content) && array_key_exists("sale_price", $content)) {
									$productAddonRow['sale_price'] = $content['sale_price'] ?: 0.0;
								}
							}
							executeQuery("insert into shopping_cart_item_addons (shopping_cart_item_id,product_addon_id,quantity,sale_price,content) values (?,?,?,?,?)", $thisItem['shopping_cart_item_id'], $productAddonRow['product_addon_id'], $quantity, $productAddonRow['sale_price'], $thisAddon['content']);
						}
					}
				} else {
					$shoppingCartItemDataSource->deleteRecord(array("primary_id" => $thisItem['shopping_cart_item_id']));
					unset($this->iShoppingCartItems[$index]);
				}
			}
		}
		return true;
	}

	private function mergeItemArrays($firstArray, $secondArray) {
		$compareFields = array("product_id", "location_id", "serial_number", "sale_price", "promotional_price");
		$finalArray = array();
		foreach (array_merge($firstArray, $secondArray) as $thisItem) {
			if ($thisItem['quantity'] <= 0) {
				continue;
			}
			$foundItem = false;
			foreach ($finalArray as $index => $thatItem) {
				$sameItem = true;
				foreach ($compareFields as $fieldName) {
					if ($thatItem[$fieldName] != $thisItem[$fieldName]) {
						$sameItem = false;
						break;
					}
					if (array_key_exists($fieldName, $thatItem) && !array_key_exists($fieldName, $thisItem)) {
						$sameItem = false;
						break;
					}
					if (!array_key_exists($fieldName, $thatItem) && array_key_exists($fieldName, $thisItem)) {
						$sameItem = false;
						break;
					}
				}
				if (!$sameItem) {
					continue;
				}
				$foundItem = true;
				$finalArray[$index]['quantity'] = $thisItem['quantity'];
			}
			if (!$foundItem) {
				$finalArray[] = $thisItem;
			}
		}
		return $finalArray;
	}
}
