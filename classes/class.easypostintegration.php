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
use EasyPost\Address;
use EasyPost\CarrierAccount;
use EasyPost\EasyPost;
use EasyPost\Parcel;
use EasyPost\Shipment;
use EasyPost\CustomsItem;
use EasyPost\CustomsInfo;

class EasyPostIntegration {

    private static $iCustomsStates = array('AA','AE','AP','AS','FM','GU','MH','MP','PW');

    public static function sortRates($a, $b) {
        if ($a['rate'] == $b['rate']) {
            return 0;
        }
        return ($a['rate'] > $b['rate']) ? 1 : -1;
    }

    public static function getRecentlyUsedDimensions() {
        $preferences = Page::getPagePreferences("EASY_POST_DIALOG");
        if(!empty($preferences['recently_used_dimensions'])) {
            return explode(",", $preferences['recently_used_dimensions']);
        } else {
            return array();
        }
    }

    public static function setRecentlyUsedDimensions($height, $width, $length) {
        $preferences = Page::getPagePreferences("EASY_POST_DIALOG");
        $recentlyUsedArray = array();
        if(!empty($preferences['recently_used_dimensions'])) {
            $recentlyUsedArray = explode(",", $preferences['recently_used_dimensions']);
        }
        $dimensions = $height . "x" . $width . "x" . $length;
        $index = array_search($dimensions, $recentlyUsedArray);
        if($index !== false) {
            unset($recentlyUsedArray[$index]);
        }
        array_unshift($recentlyUsedArray, $dimensions);
        while(count($recentlyUsedArray) > 10) {
            array_pop($recentlyUsedArray);
        }
        $preferences['recently_used_dimensions'] = implode(",", $recentlyUsedArray);
        Page::setPagePreferences($preferences, "EASY_POST_DIALOG");
    }

    public static function getCustomsItemsControl($page) {
        $customsItemsColumn = new DataColumn("customs_items");
        $customsItemsColumn->setControlValue("data_type", "custom");
        $customsItemsColumn->setControlValue("control_class", "EditableList");
        $customsItemsControl = new EditableList($customsItemsColumn, $page);
        $columns = array("description"=>array("data_type"=>"varchar", "form_label"=>"Description"),
            "quantity"=>array("data_type"=>"int", "form_label"=>"Quantity", "maximum_length"=>"3"),
            "value"=>array("data_type"=>"decimal", "form_label"=>"Value", "maximum_length"=>"6"),
            "weight"=>array("data_type"=>"decimal", "form_label"=>"Weight in Oz", "maximum_length"=>"4"),
            "hs_tariff_number"=>array("data_type"=>"varchar", "form_label"=>"HS Tariff Number", "not_null"=>true, "maximum_length"=>"11"),
            "origin_country"=>array("data_type"=>"varchar", "form_label"=>"Country of Origin", "maximum_length"=>"2"));
        $columnList = array();
        foreach($columns as $columnName=>$thisColumn) {
            $dataColumn = new DataColumn($columnName);
            foreach($thisColumn as $controlName=>$controlValue) {
                $dataColumn->setControlValue($controlName, $controlValue);
            }
            $columnList[$columnName] = $dataColumn;
        }
        $customsItemsControl->setColumnList($columnList);
        return $customsItemsControl;
    }

    public static function getCustomsItems($orderShipmentId) {
        $customsItems = array();
        $itemSet = executeQuery("select * from order_shipment_items join order_items using (order_item_id) join product_data using (product_id) where order_shipment_id = ?", $orderShipmentId);
        while ($itemRow = getNextRow($itemSet)) {
            $weightOz = $itemRow['weight'] * 16;
            $rowValues = array();
            $rowValues['description'] = array("data_value" => $itemRow['description'], "crc_value" => getCrcValue($itemRow['description']));
            $rowValues['quantity'] = array("data_value" => $itemRow['quantity'], "crc_value" => getCrcValue($itemRow['quantity']));
            $rowValues['value'] = array("data_value" => $itemRow['sale_price'], "crc_value" => getCrcValue($itemRow['sale_price']));
            $rowValues['weight'] = array("data_value" => $weightOz, "crc_value" => getCrcValue($weightOz));
            $rowValues['origin_country'] = array("data_value" => "US", "crc_value" => getCrcValue("US"));
            $customsItems[] = $rowValues;
        }
        freeResult($itemSet);
        return $customsItems;
    }

    public static function formatAttentionLine($name, $phoneNumber) {
        $attentionLine = "Customer: " . $name . ", " . $phoneNumber;
        if(strlen($attentionLine) > 35) {
            $attentionLine = "Cust: " . preg_replace("/[\(\)]/","", $phoneNumber) . "," . $name;
        }
        return $attentionLine;
    }

    public static function getLabelRates($easyPostApiKey, $inputArray) {
        $inputArray['from_country_id'] = intval($inputArray['from_country_id']) ?: 1000;
        $inputArray['to_country_id'] = intval($inputArray['to_country_id']) ?: 1000;
        $inputHash = md5(jsonEncode($inputArray));
        $cachedResult = getCachedData("easy_post_label_rates", $inputHash);
        if(!empty($cachedResult)) {
            return $cachedResult;
        }
        EasyPost::setApiKey($easyPostApiKey);
        try { // carrier accounts only available with a production API key
            $carrierAccountList = CarrierAccount::all();
            $carrierAccounts = array();
            foreach($carrierAccountList as $account) {
                $carrierAccounts[$account->readable][$account->id] = $account->description;
            }
        } catch(Exception $e) {
            $carrierAccounts = array();
        }

        try {

            $fromAddress = Address::create(array(
                'company' => $inputArray['from_full_name'],
                'street1' => $inputArray['from_address_1'],
                'street2' => $inputArray['from_address_2'],
                'city' => $inputArray['from_city'],
                'state' => $inputArray['from_state'],
                'zip' => $inputArray['from_postal_code'],
                'country' => getFieldFromId("country_code", "countries", "country_id", $inputArray['from_country_id']),
                'phone' => $inputArray['from_phone_number']
            ));
            $toAddressArray = array(
                'company' => $inputArray['to_full_name'],
                'name' => $inputArray['to_attention_line'],
                'street1' => $inputArray['to_address_1'],
                'street2' => $inputArray['to_address_2'],
                'city' => $inputArray['to_city'],
                'state' => $inputArray['to_state'],
                'zip' => $inputArray['to_postal_code'],
                'country' => getFieldFromId("country_code", "countries", "country_id", $inputArray['to_country_id']),
                'phone' => $inputArray['to_phone_number']);
            if (!empty($inputArray['residential_address'])) {
                $toAddressArray['residential'] = true;
            }
            $toAddress = Address::create($toAddressArray);
            if (empty($inputArray['letter_package'])) {
                $parcel = Parcel::create(array(
                    "length" => $inputArray['length'],
                    "width" => $inputArray['width'],
                    "height" => $inputArray['height'],
                    "weight" => ceil($inputArray['weight'] * ($inputArray['weight_unit'] == "ounce" ? 1 : 16))
                ));
            } else {
                $parcel = Parcel::create(array(
                    "predefined_package" => "Letter",
                    "weight" => ceil($inputArray['weight'] * ($inputArray['weight_unit'] == "ounce" ? 1 : 16))
                ));
                $inputArray['signature_required'] = "";
                $inputArray['adult_signature_required'] = "";
            }
            $shipmentOptions = array('delivery_confirmation' => (empty($inputArray['signature_required']) && empty($inputArray['adult_signature_required']) ? "NO_SIGNATURE" : (empty($inputArray['adult_signature_required']) ? $inputArray['signature_required'] : $inputArray['adult_signature_required'])));
            if (!empty($inputArray['hazmat_indicator'])) {
                $shipmentOptions['hazmat'] = $inputArray['hazmat_indicator'];
            }
            if (!empty($inputArray['include_media'])) {
                $shipmentOptions['special_rates_eligibility'] = "USPS.MEDIAMAIL";
            }
            if (!empty($inputArray['label_date'])) {
	            $shipmentOptions['label_date'] = date("Y-m-d",strtotime($inputArray['label_date']));
            }
			$shipmentOptions['invoice_number'] = $inputArray['order_id'];

            if(!empty($inputArray['insurance_amount'] && !empty($inputArray['use_carrier_insurance']))) {
                $shipmentOptions['carrier_insurance_amount'] = $inputArray['insurance_amount'];
            }

            $customsInfo = null;
            if (in_array(strtoupper($inputArray['to_state']), self::$iCustomsStates) || $inputArray['to_country_id'] != 1000) {
                if (!array_key_exists("customs_items_description-1", $inputArray)) {
                    $returnArray['error_message'] = "Customs Declaration required for shipment to " . $inputArray['to_state'];
                    return $returnArray;
                }
                $customsItems = array();
                $index = 1;
                while (array_key_exists("customs_items_description-" . $index, $inputArray)) {
                    $customsItems[] = CustomsItem::create(array(
                        "description" => $inputArray['customs_items_description-' . $index],
                        "quantity" => $inputArray['customs_items_quantity-' . $index],
                        "value" => $inputArray['customs_items_value-' . $index],
                        "weight" => $inputArray['customs_items_weight-' . $index],
                        "hs_tariff_number" => $inputArray['customs_items_hs_tariff_number-' . $index],
                        "origin_country" => $inputArray['customs_items_origin_country-' . $index]
                    ));
                    $index++;
                }
                $customsInfo = CustomsInfo::create(array(
                    "contents_explanation" => $inputArray['customs_contents_explanation'],
                    "contents_type" => $inputArray['customs_contents_type'],
                    "customs_certify" => $inputArray['customs_contents_certify'],
                    "customs_signer" => $inputArray['customs_signer'],
                    "eel_pfc" => ($inputArray['customs_eel_pfc_other'] ?: $inputArray['customs_eel_pfc']),
                    "restriction_comments" => $inputArray['customs_restriction_comments'],
                    "restriction_type" => $inputArray['customs_restriction_type'],
                    "customs_items" => $customsItems
                ));
            }
            if (!empty($customsInfo)) {
                $shipment = Shipment::create(array(
                    "to_address" => $toAddress,
                    "from_address" => $fromAddress,
                    "parcel" => $parcel,
                    "customs_info" => $customsInfo,
	                "reference" => $inputArray['order_id'],
                    "options" => $shipmentOptions
                ));
            } else {
                $shipment = Shipment::create(array(
                    "to_address" => $toAddress,
                    "from_address" => $fromAddress,
                    "parcel" => $parcel,
	                "reference" => $inputArray['order_id'],
                    "options" => $shipmentOptions
                ));
            }

            $returnArray['insurance_charge'] = 0;
            if (!empty($inputArray['insurance_amount']) && $inputArray['insurance_amount'] > 0) {
                if(!empty($inputArray['use_carrier_insurance'])) {
                    $returnArray['insurance_charge'] = "Included in rates";
                } else {
                    $insuranceRate = getPreference("EASY_POST_INSURANCE_PERCENT") ?: 1;
                    $returnArray['insurance_charge'] = number_format(max(1, round($inputArray['insurance_amount'] * ($insuranceRate / 100), 2)), 2);
                }
            }
            $predefinedParcels = array();
            $predefinedParcels['FEDEX'] = array("FedExEnvelope", "FedExBox", "FedExPak", "FedExTube", "FedEx10kgBox", "FedEx25kgBox", "FedExSmallBox", "FedExMediumBox", "FedExLargeBox", "FedExExtraLargeBox");
            $predefinedParcels['USPS'] = array("FlatRateEnvelope", "FlatRateLegalEnvelope", "FlatRatePaddedEnvelope", "FlatRateGiftCardEnvelope", "FlatRateWindowEnvelope", "FlatRateCardboardEnvelope", "SmallFlatRateEnvelope", "Parcel", "SoftPack", "SmallFlatRateBox", "MediumFlatRateBox", "LargeFlatRateBox", "LargeFlatRateBoxAPOFPO", "FlatTubTrayBox", "EMMTrayBox", "FullTrayBox", "HalfTrayBox", "PMODSack");
            $predefinedParcels['UPS'] = array("UPSLetter", "UPSExpressBox", "UPS25kgBox", "UPS10kgBox", "Tube", "Pak", "SmallExpressBox", "MediumExpressBox", "LargeExpressBox");

            $foundCarriers = array();
            $returnArray['rates'] = array();
            foreach ($shipment->rates as $rate) {
                $rateDescription = $rate->carrier . ", " . $rate->service;
                if (!empty($carrierAccounts[$rate->carrier]) && count($carrierAccounts[$rate->carrier]) > 1) {
                    $rateDescription .= " (" . $carrierAccounts[$rate->carrier][$rate->carrier_account_id] . ")";
                }
                $returnArray['rates'][] = array("description" => $rateDescription, "carrier"=>$rate->carrier, "rate" => $rate->rate, "id" => $rate->id, "rate_shipment_id" => $rate->shipment_id);
                if (!in_array($rate->carrier, $foundCarriers)) {
                    $foundCarriers[] = strtoupper($rate->carrier);
                }
            }
            $predefinedPackages = explode(",", getPreference("ALWAYS_SHOW_PREDEFINED"));
            foreach ($predefinedPackages as $index => $predefinedPackage) {
                if (empty($predefinedPackage)) {
                    unset($predefinedPackages[$index]);
                }
            }
            if (empty($inputArray['letter_package']) && (!empty($inputArray['predefined_package']) || !empty($predefinedPackages))) {
                foreach ($predefinedParcels as $carrier => $predefinedParcelCodes) {
	                $shipmentOptions = array('delivery_confirmation' => (empty($inputArray['signature_required']) && empty($inputArray['adult_signature_required']) ? "NO_SIGNATURE" : (empty($inputArray['adult_signature_required']) ? $inputArray['signature_required'] : $inputArray['adult_signature_required'])));
	                if (!empty($inputArray['hazmat_indicator'])) {
		                $shipmentOptions['hazmat'] = $inputArray['hazmat_indicator'];
	                }
	                if (!empty($inputArray['label_date'])) {
		                $shipmentOptions['label_date'] = date("Y-m-d",strtotime($inputArray['label_date']));
	                }
	                $shipmentOptions['invoice_number'] = $inputArray['order_id'];

                    if(!empty($inputArray['insurance_amount'] && !empty($inputArray['use_carrier_insurance']))) {
                        $shipmentOptions['carrier_insurance_amount'] = $inputArray['insurance_amount'];
                    }

                    if (!in_array($carrier, $foundCarriers)) {
                        continue;
                    }
                    foreach ($predefinedParcelCodes as $thisPredefinedParcelCode) {
                        if ($inputArray['predefined_package'] != "ALL" && $inputArray['predefined_package'] != $thisPredefinedParcelCode && !in_array($thisPredefinedParcelCode, $predefinedPackages)) {
                            continue;
                        }
                        $parcel = Parcel::create(array(
                            "length" => $inputArray['length'],
                            "width" => $inputArray['width'],
                            "height" => $inputArray['height'],
                            "weight" => ceil($inputArray['weight'] * ($inputArray['weight_unit'] == "ounce" ? 1 : 16)),
                            "predefined_package" => $thisPredefinedParcelCode
                        ));
                        $shipment = null;
                        if (!empty($customsInfo)) {
                            $shipment = Shipment::create(array(
                                "to_address" => $toAddress,
                                "from_address" => $fromAddress,
                                "parcel" => $parcel,
                                "customs_info" => $customsInfo,
	                            "reference" => $inputArray['order_id'],
                                "options" => $shipmentOptions
                            ));
                        } else {
                            $shipment = Shipment::create(array(
                                "to_address" => $toAddress,
                                "from_address" => $fromAddress,
                                "parcel" => $parcel,
	                            "reference" => $inputArray['order_id'],
                                "options" => $shipmentOptions
                            ));
                        }
                        foreach ($shipment->rates as $rate) {
                            $rateDescription = $rate->carrier . ", " . $rate->service . ", " . $thisPredefinedParcelCode;
                            if (!empty($carrierAccounts[$rate->carrier]) && count($carrierAccounts[$rate->carrier]) > 1) {
                                $rateDescription .= " (" . $carrierAccounts[$rate->carrier][$rate->carrier_account_id] . ")";
                            }
                            $returnArray['rates'][] = array("description" => $rateDescription, "carrier"=>$rate->carrier, "rate" => $rate->rate, "id" => $rate->id, "rate_shipment_id" => $rate->shipment_id);
                        }
                    }
                }
            }

            usort($returnArray['rates'], array('EasyPostIntegration', "sortRates"));
        } catch(Exception $e) {
            $returnArray['error_message'] = $e->getMessage();
        }
        setCachedData("easy_post_label_rates", $inputHash,$returnArray,1);

        return $returnArray;
    }

    public static function createLabel($easyPostApiKey, $inputArray, $productIds = array()) {
        try {
            EasyPost::setApiKey($easyPostApiKey);

            $shipment = Shipment::retrieve($inputArray['rate_shipment_id']);

            foreach ($shipment->rates as $rate) {
                if ($inputArray['postage_rate_id'] == $rate->id) {
                    $shippingCarrierCode = $rate->carrier;
                    break;
                }
            }
            $shippingCarrierId = getFieldFromId("shipping_carrier_id", "shipping_carriers", "shipping_carrier_code", makeCode($shippingCarrierCode));
            if (!empty($shippingCarrierId) && !empty($productIds)) {
                $invalidProductIds = array();
                foreach ($productIds as $productId) {
                    $productShippingMethodId = getFieldFromId("product_shipping_carrier_id", "product_shipping_carriers",
                        "product_id", $productId, "shipping_carrier_id = ?", $shippingCarrierId);
                    if (!empty($productShippingMethodId)) {
                        $invalidProductIds[] = $productId;
                        continue;
                    }
                    $categorySet = executeQuery("select * from product_categories where client_id = ? and product_category_id in (select product_category_id from product_category_shipping_carriers where " .
                        "shipping_carrier_id = ?)", $GLOBALS['gClientId'], $shippingCarrierId);
                    while ($categoryRow = getNextRow($categorySet)) {
                        if (ProductCatalog::productIsInCategory($productId, $categoryRow['product_category_id'])) {
                            $invalidProductIds[] = $productId;
                            break;
                        }
                    }
                }
                if (!empty($invalidProductIds)) {
                    $returnArray['error_message'] = "The following products cannot be shipped by this carrier:";
                    foreach ($invalidProductIds as $productId) {
                        $returnArray['error_message'] .= "<br>" . htmlText(getFieldFromId("description", "products", "product_id", $productId));
                    }
                    return $returnArray;
                }
            }

            try {
                $shipmentArray = array('rate' => array('id' => $inputArray['postage_rate_id']));
                if (!empty($inputArray['insurance_amount']) && empty($inputArray['use_carrier_insurance'])) {
                    $shipmentArray['insurance'] = $inputArray['insurance_amount'];
                }
                $shipment->buy($shipmentArray);
	            removeCachedData("easy_post_label_rates", "*");
            } catch (\EasyPost\Error $e) {
                $returnArray['error_message'] = "EasyPost error: " . $e->ecode;
                ob_start();
                var_dump($shipment);
                $GLOBALS['gPrimaryDatabase']->logError($e->ecode . "\n" . $inputArray['postage_rate_id'] . "\n" . ob_get_clean());
                return $returnArray;
            }

            $returnArray['shipping_charge'] = number_format($shipment->selected_rate->rate, 2, ".", ",");
            $returnArray['label_url'] = $shipment->postage_label->label_url;
            $returnArray['tracking_identifier'] = $shipment->tracking_code;
            $returnArray['full_name'] = $inputArray['to_full_name'];
            $returnArray['carrier_description'] = $shipment->selected_rate->carrier . ", " . $shipment->selected_rate->service;
            $returnArray['shipping_carrier_id'] = $shippingCarrierId;
        } catch(Exception $e) {
            $returnArray['error_message'] = $e->getMessage();
        }

    return $returnArray;
    }

    public static function createTracker($easyPostApiKey, $trackingIdentifier, $shippingCarrierCode) {
        try {
            // try with carrier first, auto-detect if unsuccessful
            $result = \EasyPost\Tracker::create(['tracking_code' => $trackingIdentifier, 'carrier' => $shippingCarrierCode], $easyPostApiKey);
            if (!empty($result->error)) {
                \EasyPost\Tracker::create(['tracking_code' => $trackingIdentifier], $easyPostApiKey);
            }
            return true;
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}