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

class Shipping {

    public static function getDealerShippingCharge($parameters = array()) {
        $quantity = 1; // Calculate the shipping charge based on single item
        $shippingMethod = getRowFromId("shipping_methods", "shipping_method_id", $parameters['shipping_method_id']);
        $shippingCalculationLog = "";
        $pickupOnly = false;
        $productId = $parameters['product_id'];
        $contactRow = $GLOBALS['gUserRow'];
        $virtualProduct = getFieldFromId("virtual_product", "products", "product_id", $productId);
        if ($virtualProduct) {
            return array("shipping_charge" => "0.00", "description" => "None Required");
        }
        $orderTotal = $parameters['sale_price'] * $quantity;

        $fixedCharges = 0;
        $productIds = array();
        $productDataRow = getRowFromId("product_data", "product_id", $productId);
        $shippingProducts = array();

        $shippingCalculationLog = "Shipping " . $quantity . " of product ID " . $quantity . "\n";

        $shippingPrice = getFieldFromId("price", "product_prices", "product_id", $productId, "product_price_type_id = " .
            "(select product_price_type_id from product_price_types where product_price_type_code = 'SHIPPING_CHARGE' and (start_date is null or start_date <= current_date) and " .
            "(end_date is null or end_date >= current_date) and client_id = ?)" .
            (empty($contactRow['user_type_id']) ? " and user_type_id is null" : " and (user_type_id is null or user_type_id = " . $contactRow['user_type_id'] . ")"), $GLOBALS['gClientId']);
        if (strlen($shippingPrice) > 0) {
            if ($quantity > 1) {
                $additionalShippingPrice = getFieldFromId("price", "product_prices", "product_id", $productId, "product_price_type_id = " .
                    "(select product_price_type_id from product_price_types where product_price_type_code = 'ADDITIONAL_SHIPPING_CHARGE' and (start_date is null or start_date <= current_date) and " .
                    "(end_date is null or end_date >= current_date) and client_id = ?)" .
                    (empty($contactRow['user_type_id']) ? " and user_type_id is null" : " and (user_type_id is null or user_type_id = " . $contactRow['user_type_id'] . ")"), $GLOBALS['gClientId']);
                if (strlen($additionalShippingPrice) == 0) {
                    $additionalShippingPrice = $shippingPrice;
                }
            } else {
                $additionalShippingPrice = $shippingPrice;
            }
            $fixedCharges = $shippingPrice + ($additionalShippingPrice * ($quantity - 1));
            $shippingCalculationLog .= "Flat rate of " . number_format($fixedCharges, 2) . " for product ID " . $productId . "\n";
            return array("shipping_charge" => $fixedCharges, "description" => "Shipping Charge on Product Level", "shipping_calculation_log" => $shippingCalculationLog);
        }
        $shippingPrice = getFieldFromId("shipping_charge", "product_manufacturers", "product_manufacturer_id", getFieldFromId("product_manufacturer_id", "products", "product_id", $productId));
        if (strlen($shippingPrice) > 0) {
            $fixedCharges = ($shippingPrice * $quantity);
            $shippingCalculationLog .= "Flat rate of " . number_format($shippingPrice, 2) . " for each of product ID " . $productId . " from manufacturer\n";
            $shippingCalculationLog .= "Total flat rate charges of " . number_format($fixedCharges, 2) . "\n\n";
            return array("shipping_charge" => $fixedCharges, "description" => "Shipping Charge based on Product Manufacturer", "shipping_calculation_log" => $shippingCalculationLog);
        }

        $totalWeight = 0;
        $shipWeight = getFieldFromId("weight", "product_data", "product_id", $productId);
        if (strlen($shipWeight) > 0) {
            $totalWeight = ($shipWeight * $quantity);
        }
        $totalWeight = round($totalWeight, 2);
        $shippingCalculationLog .= "Total weight of cart is " . number_format($totalWeight, 2) . "\n\n";
        $shippingRate = array();

        $shippingCalculationLog .= "Calculating shipping method '" . $shippingMethod['shipping_method_id'] . "'\n";

        // Get shipping based on shipping charge + shipping method code
        $shippingPrice = getFieldFromId("price", "product_prices", "product_id", $productId, "product_price_type_id = " .
            "(select product_price_type_id from product_price_types where product_price_type_code = 'SHIPPING_CHARGE_" . $shippingMethod['shipping_method_code'] .
            "' and (start_date is null or start_date <= current_date) and (end_date is null or end_date >= current_date) and client_id = ?)" .
            (empty($contactRow['user_type_id']) ? " and user_type_id is null" : " and (user_type_id is null or user_type_id = " . $contactRow['user_type_id'] . ")"), $GLOBALS['gClientId']);
        if (strlen($shippingPrice) > 0) {
            if ($quantity > 1) {
                $additionalShippingPrice = getFieldFromId("price", "product_prices", "product_id", $productId, "product_price_type_id = " .
                    "(select product_price_type_id from product_price_types where product_price_type_code = 'ADDITIONAL_SHIPPING_CHARGE' and (start_date is null or start_date <= current_date) and " .
                    "(end_date is null or end_date >= current_date) and client_id = ?)" .
                    (empty($contactRow['user_type_id']) ? " and user_type_id is null" : " and (user_type_id is null or user_type_id = " . $contactRow['user_type_id'] . ")"), $GLOBALS['gClientId']);
                if (strlen($additionalShippingPrice) == 0) {
                    $additionalShippingPrice = $shippingPrice;
                }
            } else {
                $additionalShippingPrice = $shippingPrice;
            }
            $fixedCharges = $shippingPrice + ($additionalShippingPrice * ($quantity - 1));
            $shippingCalculationLog .= "Flat rate of " . number_format($fixedCharges, 2) . " for product ID " . $productId .
                " for shipping method '" . $shippingMethod['description'] . "'\n";
            return array("shipping_charge" => $fixedCharges, "description" => "Shipping Charge based on Product and Shipping Method", "shipping_calculation_log" => $shippingCalculationLog);
        }

        $canUse = true;
        $prohibitedShippingMethod = getRowFromId("product_shipping_methods", "product_id", $productId, "shipping_method_id = ?", $shippingMethod['shipping_method_id']);
        if (!empty($prohibitedShippingMethod)) {
            $shippingCalculationLog .= "Product ID " . $productId . " cannot use this shipping method.\n";
            $canUse = false;
        }

        $prohibitedShippingMethodCategories = array();
        $categorySet = executeQuery("select * from product_categories where client_id = ? and product_category_id in (select product_category_id from product_category_shipping_methods where " .
            "shipping_method_id = ?)", $GLOBALS['gClientId'], $shippingMethod['shipping_method_id']);
        while ($categoryRow = getNextRow($categorySet)) {
            $prohibitedShippingMethodCategories[] = $categoryRow;
        }
        foreach ($prohibitedShippingMethodCategories as $categoryRow) {
            if (ProductCatalog::productIsInCategory($productId, $categoryRow['product_category_id'])) {
                $shippingCalculationLog .= "Product ID " . $productId . " is in product category '" . $categoryRow['description'] . "', which cannot use this shipping method.\n";
                $canUse = false;
            }
        }

        if (!$canUse) {
            $shippingCalculationLog .= "Can't use shipping method '" . $shippingMethod['description'] . "'\n";
            return array("shipping_charge" => null, "description" => "Shipping Method" . $shippingMethod['description'] . " not available", "shipping_calculation_log" => $shippingCalculationLog);
        }


        $chargeSet = executeQuery("select * from shipping_charges where shipping_method_id = ?", $shippingMethod['shipping_method_id']);
        $shippingCalculationLog .= $chargeSet['row_count'] . " charges found for shipping method '" . $shippingMethod['description'] . "'\n";
        while ($chargeRow = getNextRow($chargeSet)) {
            if ((!empty($chargeRow['minimum_amount']) && $orderTotal < $chargeRow['minimum_amount']) || (!empty($chargeRow['maximum_order_amount']) && $orderTotal > $chargeRow['maximum_order_amount'])) {
                $shippingCalculationLog .= "Charge '" . $chargeRow['description'] . "' cannot be used because order total does not meet minimum or maximum amount requirements.\n";
                continue;
            }
            $useCharge = false;
            $locationSet = executeQuery("select * from shipping_locations where shipping_charge_id = ? order by exclude_location,sequence_number",
                $chargeRow['shipping_charge_id']);
            while ($locationRow = getNextRow($locationSet)) {
                if (($contactRow['country_id'] == $locationRow['country_id'] || empty($locationRow['country_id'])) &&
                    ($contactRow['state'] == $locationRow['state'] || empty($locationRow['state'])) &&
                    ($contactRow['postal_code'] == $locationRow['postal_code'] || empty($locationRow['postal_code']))) {
                    $useCharge = ($locationRow['exclude_location'] ? false : true);
                }
            }
            if (!$useCharge) {
                $shippingCalculationLog .= "Charge '" . $chargeRow['description'] . "' cannot be used because of the location\n";
                continue;
            }
            $shippingCalculationLog .= "Calculating charges for Charge '" . $chargeRow['description'] . "'\n";

# get shipping surcharge for distributors

            $distributorCharge = 0;
            $productInventoryId = getFieldFromId("product_inventory_id", "product_inventories", "product_id", $productId, "quantity > 0 and location_id in (select location_id from locations " .
                "where inactive = 0 and (product_distributor_id is null or product_distributor_id not in (select product_distributor_id from product_distributor_shipping_charges where shipping_charge_id = ?)))",
                $chargeRow['shipping_charge_id']);
            if (empty($productInventoryId)) {
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
                    $distributorCharge = $distributorRow['flat_rate'] + (($quantity - 1) * $distributorRow['additional_item_charge']);
                    $shippingCalculationLog .= "Additional Shipping charge for distributor " . getFieldFromId("description", "product_distributors", "product_distributor_id", $distributorRow['product_distributor_id']) .
                        (empty($productDepartmentId) ? "" : " in department " . getFieldFromId("description", "product_departments", "product_department_id", $productDepartmentId)) . " of " . $distributorCharge . "\n";
                    break;
                }
            }

# get additional charges for product types
            $productTypeChargeSet = executeQuery("select * from shipping_charge_product_types join product_types using (product_type_id) join products using (product_type_id) where " .
                "shipping_charge_id = ? and product_id = ? and (flat_rate <> 0 or additional_item_charge <> 0) and product_types.inactive = 0", $chargeRow['shipping_charge_id'], $productId);
            $productTypeChargeRow = getNextRow($productTypeChargeSet);
            $productTypeCharge = !empty($productTypeChargeRow) ? $productTypeChargeRow['flat_rate'] : 0;

# get additional charges for categories
            $shippingCalculationLog .= "Checking categories for product ID " . $productId . "\n";
            $categoryCharge = 0;
            $saveProductCategoryId = "";
            $saveCharge = 0;
            $categoryDescription = "";
            $categorySet = executeQuery("select * from shipping_charge_product_categories join product_categories using (product_category_id) join product_category_links using (product_category_id) where " .
                "shipping_charge_id = ? and (flat_rate <> 0 or additional_item_charge <> 0) and inactive = 0 and product_category_links.product_id = ? order by sequence_number", $chargeRow['shipping_charge_id'], $productId);
            while ($categoryRow = getNextRow($categorySet)) {
                if ($categoryRow['flat_rate'] > $saveCharge || empty($saveProductCategoryId)) {
                    $saveProductCategoryId = $categoryRow['product_category_id'];
                    $saveCharge = $categoryRow['flat_rate'];
                    $categoryDescription = $categoryRow['description'];
                }
            }
            if (!empty($saveProductCategoryId)) {
                $categoryCharge = $saveCharge;
                $shippingCalculationLog .= "Additional charge of " . number_format($saveCharge, 2) . " for category '" . $categoryDescription . "' for product ID " . $productId . "\n";
            }

# get Additional charges for departments
            $departmentCharge = 0;
            $productDepartmentCharges = array();
            $departmentSet = executeQuery("select * from shipping_charge_product_departments join product_departments using (product_department_id) " .
                "where shipping_charge_id = ? and (flat_rate <> 0 or additional_item_charge <> 0) and inactive = 0", $chargeRow['shipping_charge_id']);
            while ($departmentRow = getNextRow($departmentSet)) {
                if (!ProductCatalog::productIsInDepartment($productId, $departmentRow['product_department_id'])) {
                    continue;
                }
                $shippingCalculationLog .= "Product ID " . $productId . " is in department " . $departmentRow['description'] . "\n";
                if (!array_key_exists($departmentRow['product_department_id'], $productDepartmentCharges)) {
                    $productDepartmentCharges[$departmentRow['product_department_id']] = array("product_department_id" => $departmentRow['product_department_id'], "description" => "Department: " . $departmentRow['description'],
                        "first_used" => false, "flat_rate" => $departmentRow['flat_rate'], "additional_item_charge" => $departmentRow['additional_item_charge'], "product_ids" => array());
                }
                $productDepartmentCharges[$departmentRow['product_department_id']]['product_ids'][] = $productId;
            }
            $saveProductDepartmentId = "";
            $saveCharge = 0;
            foreach ($productDepartmentCharges as $productDepartmentId => $thisChargeInfo) {
                if (!array_key_exists("product_ids", $thisChargeInfo) || !is_array($thisChargeInfo['product_ids']) || !in_array($productId, $thisChargeInfo['product_ids'])) {
                    continue;
                }
                if ($thisChargeInfo['flat_rate'] > $saveCharge || empty($saveProductDepartmentId)) {
                    $saveProductDepartmentId = $productDepartmentId;
                    $saveCharge = $thisChargeInfo['flat_rate'];
                }
                if (!empty($saveProductDepartmentId)) {
                    $departmentCharge = $saveCharge;
                    $shippingCalculationLog .= "Additional charge of " . number_format($saveCharge, 2) . " for department '" . $productDepartmentCharges[$saveProductDepartmentId]['description'] . "' for product ID " . $productId . "\n";
                }
            }

            $shippingCalculationLog .= "Found matching shipping location\n";
            $itemCharge = $chargeRow['flat_rate'];
            $shippingCalculationLog .= "Per item charge: " . number_format($itemCharge, 2) . "\n";
            if ($chargeRow['percentage'] > 0) {
                $percentageCharge = round($orderTotal * ($chargeRow['percentage'] / 100), 2);
                $shippingCalculationLog .= "Percentage of order charge: " . number_format($percentageCharge, 2) . "\n";
                $itemCharge += $percentageCharge;
            }
            $shipWeight = getFieldFromId("weight", "product_data", "product_id", $productId);
            $rateSet = executeQuery("select * from shipping_rates where shipping_charge_id = ? and minimum_weight <= ? and (maximum_weight is null or maximum_weight >= ?)",
                $chargeRow['shipping_charge_id'], $shipWeight, $shipWeight);
            $shippingCalculationLog .= "Found " . $rateSet['row_count'] . " rates for this shipping charge\n";

            $rate = 0;
            if ($rateSet['row_count'] == 0) {
                $rate = $itemCharge + $categoryCharge + $departmentCharge + $productTypeCharge + $distributorCharge;
                if ($rate < $chargeRow['minimum_charge']) {
                    $shippingCalculationLog .= "Minimum charge of " . $chargeRow['minimum_charge'] . "used\n";
                    $rate = $chargeRow['minimum_charge'];
                }
                if ($chargeRow['maximum_amount'] > 0 && $rate > $chargeRow['maximum_amount']) {
                    $rate = $chargeRow['maximum_amount'];
                    $shippingCalculationLog .= "Maximum charge per item of " . $chargeRow['maximum_amount'] . " used: " . $rate . "\n";
                }
            } else {
                if ($rateRow = getNextRow($rateSet)) {
                    $rate = $rateRow['flat_rate'];
                    $shippingCalculationLog .= "Added flat rate of " . number_format($rate, 2) . "\n";
                    if ($rateRow['additional_pound_charge'] > 0) {
                        $poundCharge = ceil($shipWeight - $rateRow['minimum_weight']) * $rateRow['additional_pound_charge'];
                        $shippingCalculationLog .= "Added additional weight charge of " . number_format($poundCharge, 2) . "\n";
                        $rate += $poundCharge;
                    }
                    if (!empty($rateRow['maximum_dimension']) && !empty($rateRow['over_dimension_charge'])) {
                        $overDimension = false;
                        if (!empty($productDataRow['width']) && $productDataRow['width'] > $rateRow['maximum_dimension']) {
                            $overDimension = true;
                        }
                        if (!empty($productDataRow['length']) && $productDataRow['length'] > $rateRow['maximum_dimension']) {
                            $overDimension = true;
                        }
                        if (!empty($productDataRow['height']) && $productDataRow['height'] > $rateRow['maximum_dimension']) {
                            $overDimension = true;
                        }
                        if ($overDimension) {
                            $overDimensionCharge = $rateRow['over_dimension_charge'];
                            $rate += $overDimensionCharge;
                            $shippingCalculationLog .= "Added over dimension charge of " . number_format($overDimensionCharge, 2) . " for product ID " . $productId . "\n";
                        }
                    }
                    $rate += $itemCharge + $categoryCharge + $departmentCharge + $productTypeCharge + $distributorCharge;
                    if ($rate < $chargeRow['minimum_charge'] && !empty($shippingProducts)) {
                        $shippingCalculationLog .= "Minimum charge of " . $chargeRow['minimum_charge'] . " used\n";
                        $rate = $chargeRow['minimum_charge'];
                    }
                    if ($chargeRow['maximum_amount'] > 0 && $rate > $chargeRow['maximum_amount']) {
                        $rate = $chargeRow['maximum_amount'];
                        $shippingCalculationLog .= "Maximum charge per item of " . $chargeRow['maximum_amount'] . " used: " . $rate . "\n";
                    }
                }
            }
            $rate += $fixedCharges;
            if ($rate < $chargeRow['minimum_charge'] && !empty($shippingProducts)) {
                $shippingCalculationLog .= "Minimum charge of " . $chargeRow['minimum_charge'] . " used\n";
                $rate = $chargeRow['minimum_charge'];
            }
            if ($chargeRow['maximum_amount'] > 0 && $rate > $chargeRow['maximum_amount']) {
                $rate = $chargeRow['maximum_amount'];
                $shippingCalculationLog .= "Maximum charge per item of " . $chargeRow['maximum_amount'] . " used: " . $rate . "\n";
            }
            if (empty($shippingRate) || $rate < $shippingRate['rate']) {
                $shippingCalculationLog .= "Rate for shipping method '" . $shippingMethod['description'] . ": " . number_format($rate, 2) . "\n\n";
                $shippingRate = array("description" => $shippingMethod['description'], "rate" => $rate, "shipping_method_code" => $shippingMethod['shipping_method_code']);
            }
        }

        return array("shipping_charge" => $shippingRate, "description" => "Shipping Charge", "shipping_calculation_log" => $shippingCalculationLog);
    }
}