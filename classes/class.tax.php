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

class Tax {

    public static function estimateTax($parameters) {
        $productItems = !empty($parameters['shopping_cart_items']) ? $parameters['shopping_cart_items'] : $parameters['product_items'];
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
            $result = _localEstimateTax($productItems, $promotionId, $countryId, $city, $state, $postalCode, $address1, $shippingCharge);
            if ($result !== false) {
                return $result;
            }
        }

        $cartTotal = 0;
        foreach ($productItems as $thisItem) {
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
                foreach ($productItems as $thisItem) {
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
        foreach ($productItems as $index => $thisItem) {
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
}