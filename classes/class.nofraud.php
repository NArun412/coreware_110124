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

class NoFraud {

    var $iErrorMessage = "";
    var $iTestUrl = "https://apitest.nofraud.com";
    var $iLiveUrl = "https://api.nofraud.com";
	var $iUseUrl = "";
	var $iAccessToken = false;
    var $iLogging = false;
    var $iAvsResponses = array(
        "Y"=>array("Address: Match & 5 Digit Zip: Match"),
        "Z"=>array("Address: No Match & 5 Digit Zip: Match"),
        "A"=>array("Address: Match & 5 Digit Zip: No Match"),
        "N"=>array("Address: No Match & 5 Digit Zip: No Match"));
    var $iCvvResponses = array(
        "M"=>array("Match"),
        "N"=>array("No Match"),
        "P"=>array("Not Processed"),
        "U"=>array("Issuer Not Certified"),
        "X"=>array("No response from association"));

	function __construct($token) {

		if ($GLOBALS['gDevelopmentServer']) {
            $this->iUseUrl = $this->iTestUrl;
		} else {
            $this->iUseUrl = $this->iLiveUrl;
		}
        $this->iLogging = !empty(getPreference("LOG_NOFRAUD"));

        $this->iAccessToken = $token;
	}

    public function getErrorMessage() {
        return $this->iErrorMessage;
    }

    public function postApi($apiMethod,$data, $verb = "POST") {
        if (!is_array($data)) {
            $data = array($data);
        }
        $data['nfToken'] = $this->iAccessToken;
        $jsonData = jsonEncode($data);
        $cleanData = $data;
        unset($cleanData['payment']['creditCard']['cardNumber']);
        unset($cleanData['payment']['creditCard']['cardCode']);
        $cleanJsonData = jsonEncode($cleanData);

        $curl = curl_init();

        $headers = array(
            'Content-Type: application/json',
            'Accept: application/json,*/*'
        );

        $url = $this->iUseUrl . (empty($apiMethod) ? "" : "/" . $apiMethod);
        curl_setopt_array($curl, array(
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $verb,
            CURLOPT_POSTFIELDS     => $jsonData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
            CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
        ));

        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        $err = curl_error($curl);
        if($this->iLogging) {
            addDebugLog("NoFraud request: " . $url
                . "\nNoFraud Data: " . (strlen($cleanJsonData) > 1000 ? substr($cleanJsonData,0,1000) . "..." : $cleanJsonData)
                . "\nNoFraud Result: " . $result
                . (empty($err) ? "" : "\nNoFraud Error: " . $err)
                . "\nNoFraud HTTP Status: " . $info['http_code']);
        }

        if (($result === false && $info['http_code'] != 204) || (!in_array($info['http_code'], array(200,201,202,204)))) {
            if(!empty($result['Errors'])) {
                $this->iErrorMessage = $result['Errors'][0];
            } else {
                $this->iErrorMessage = $err . "," . $result . "," . jsonEncode($info) . "," . $cleanJsonData;
            }
            return false;
        }
        curl_close($curl);
        return json_decode($result,true);
    }

    private function getApi($apiMethod,$data = array()) {
        if (!is_array($data)) {
            $data = array($data);
        }
        $queryParams = http_build_query($data);

        $curl = curl_init();

        $headers = array(
            'Accept: */*'
        );

        $url = $this->iUseUrl . (empty($apiMethod) ? "" : "/" . $apiMethod . (empty($queryParams) ? "" : "?" . $queryParams));
        curl_setopt_array($curl, array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
            CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
        ));

        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        $err = curl_error($curl);
        if($this->iLogging) {
            addDebugLog("NoFraud request: " . $url
                . "\nNoFraud Result: " . $result
                . (empty($err) ? "" : "\nNoFraud Error: " . $err)
                . "\nNoFraud HTTP Status: " . $info['http_code']);
        }

        if (($result === false && $info['http_code'] != 204) || (!in_array($info['http_code'], array(200,201,202,204)))) {
            if(!empty($result['Errors'])) {
                $this->iErrorMessage = $result['Errors'][0];
            } else {
                $this->iErrorMessage = $err . "," . $result . "," . jsonEncode($info) . "," . $queryParams;
            }
            return false;
        }

        curl_close($curl);
        return json_decode($result,true);
    }

    public function orderRequiresFraudCheck($orderId) {
        $orderRequiresFraudCheck = false;
        $checkFFLOrders = !empty(getPreference("NOFRAUD_CHECK_FFL_ORDERS"));
        $orderItemsResult = executeQuery("select * from order_items where order_id = ?", $orderId);
        while($orderItemsRow = getNextRow($orderItemsResult)) {
            $skipProduct = CustomField::getCustomFieldData($orderItemsRow['product_id'], "NEVER_SEND_TO_NOFRAUD", "PRODUCTS");
            $subscriptionOrEventProduct = getFieldFromId("product_type_id", "products", "product_id", $orderItemsRow['product_id'],
                "product_type_id in (select product_type_id from product_types where product_type_code in ('SUBSCRIPTION_STARTUP','SUBSCRIPTION_RENEWAL','EVENT_REGISTRATION'))");
            $productRequiresFFL = getFieldFromId("product_tag_id", "product_tag_links", "product_id", $orderItemsRow['product_id'],
                "product_tag_id in (select product_tag_id from product_tags where product_tag_code = 'FFL_REQUIRED')");
            if(!$skipProduct && !$subscriptionOrEventProduct && ($checkFFLOrders || !$productRequiresFFL)) {
                $orderRequiresFraudCheck = true;
            }
        }
        return $orderRequiresFraudCheck;
    }

    public function getDecision($orderId, $eCommerceResponses = array()) {
        if(!$this->orderRequiresFraudCheck($orderId)) {
            $this->iErrorMessage = "No products in order that require fraud checks.";
            return false;
        }
        $orderRow = getRowFromId("orders", "order_id", $orderId);
        $contactId = $orderRow['contact_id'];
        $contactResult = executeQuery("select *,(select order_time from orders where contact_id = contacts.contact_id and order_id <> ? order by order_time desc limit 1) as last_order, " .
            "(select sum(sale_price) from order_items where order_id in (select order_id from orders where contact_id = contacts.contact_id and orders.order_id <> ?)) as product_total, " .
            "(select sum(shipping_charge + handling_charge + tax_charge) from orders where contact_id = contacts.contact_id and orders.order_id <> ?) as fees_total " .
            "from contacts where contact_id = ?", $orderId, $orderId, $orderId, $contactId);
        $contactRow = getNextRow($contactResult);
        $orderItemsResult = executeQuery("select * from order_items where order_id = ?", $orderId);
        $orderItems = array();
        while($orderItemsRow = getNextRow($orderItemsResult)) {
            $orderItems[] = array(
                "sku"=>getFieldFromId("upc_code", "product_data", "product_id", $orderItemsRow['product_id']) ?:
                    getFieldFromId("product_code", "products", "product_id", $orderItemsRow['product_id']),
                "name" => $orderItemsRow['description'],
                "price" => $orderItemsRow['sale_price'],
                "quantity" => $orderItemsRow['quantity']
            );
        }
        $customer = array("email"=> $contactRow['email_address'],
            "id"=>$contactId,
            "joinedOn"=>date('m/d/Y', strtotime($contactRow['date_created']))
            );
        if(!empty($contactRow['last_order'])) {
            $customer['lastPurchaseDate'] = date('m/d/Y', strtotime($contactRow['last_order']));
        }
        if(!empty($contactRow['product_total'])) {
            $customer['totalPurchaseValue'] = $contactRow['product_total'] + $contactRow['fees_total'];
        }
        if (empty($orderRow['address_id'])) {
            $shippingAddressRow = $contactRow;
        } else {
            $shippingAddressRow = getRowFromId("addresses", "address_id", $orderRow['address_id']);
            $shippingAddressRow = array_merge($contactRow, array_filter($shippingAddressRow));
        }
        $shipTo = array("firstName"=> $shippingAddressRow['first_name'],
            "lastName"=> $shippingAddressRow['last_name'],
            "address"=> $shippingAddressRow['address_1'],
            "city"=> $shippingAddressRow['city'],
            "state"=> $shippingAddressRow['state'],
            "zip"=> $shippingAddressRow['postal_code'],
            "country"=> getFieldFromId("country_code", "countries", "country_id", $shippingAddressRow['country_id'])
        );

        Ecommerce::getClientMerchantAccountIds();
        $orderPaymentsResult = executeQuery("select * from order_payments where order_id = ?", $orderId);
        $resultArray = array();
        while($orderPaymentsRow = getNextRow($orderPaymentsResult)) {
            $paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id",
                getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id",  $orderPaymentsRow['payment_method_id']));
            if(!in_array($paymentMethodTypeCode, array('CREDIT_CARD', 'BANK_ACCOUNT'))) {
                continue;
            }
            $accountRow = getRowFromId("accounts", "account_id", $orderPaymentsRow['account_id']);
            if(empty($accountRow['address_id'])) {
                $billingAddressRow = $contactRow;
            } else {
                $billingAddressRow = getRowFromId("addresses", "address_id", $accountRow['address_id']);
                $billingAddressRow = array_merge(Contact::getContact($billingAddressRow['contact_id']), array_filter($billingAddressRow));
            }
            $eCommerceResponse = array();
            foreach($eCommerceResponses as $thisResponse) {
                if($orderPaymentsRow['transaction_identifier'] == $thisResponse['transaction_id']) {
                    $eCommerceResponse = $thisResponse;
                    break;
                }
            }
            $billTo = array("firstName"=> $billingAddressRow['first_name'],
                "lastName"=> $billingAddressRow['last_name'],
                "address"=> $billingAddressRow['address_1'],
                "city"=> $billingAddressRow['city'],
                "state"=> $billingAddressRow['state'],
                "zip"=> $billingAddressRow['postal_code'],
                "country"=> getFieldFromId("country_code", "countries", "country_id", $billingAddressRow['country_id']),
                "phoneNumber"=> str_replace(" ", "", str_replace("-", "", str_replace("(", "", str_replace(")", "", $orderRow['phone_number']))))
            );
            $gatewayName = getFieldFromId("description", "merchant_services", "merchant_service_id",
                getFieldFromId("merchant_service_id", "merchant_accounts", "merchant_account_id",
                    $accountRow['merchant_account_id'] ?: $GLOBALS['gDefaultMerchantAccountId']));
            if($paymentMethodTypeCode == "CREDIT_CARD") {
                $payment = array("creditCard"=>array(
                    "cardType" => getFieldFromId("description", "payment_methods", "payment_method_id", $orderPaymentsRow['payment_method_id'])
                ));

                if(is_numeric(substr($accountRow['account_number'],-4))) {
                    $payment['creditCard']['last4'] = substr($accountRow['account_number'],-4);
                }
                if(!empty($eCommerceResponse['card_number'])) {
                    $payment['creditCard']['cardNumber'] = $eCommerceResponse['card_number'];
                    $payment['creditCard']['bin'] = substr($eCommerceResponse['card_number'],0,6);
                }
                if(!empty($eCommerceResponse['expiration_date']) and strtotime($eCommerceResponse['expiration_date']) > 0) {
                    $payment['creditCard']['expirationDate'] = date("my", strtotime($eCommerceResponse['expiration_date']));
                }
                if(!empty($eCommerceResponse['card_code'])) {
                    $payment['creditCard']['cardCode'] = $eCommerceResponse['card_code'];
                }
            } else {
                $payment = array("method" => $gatewayName, "creditCard" => array("cardType" => "ALT"));
            }
            if(empty(array_filter($payment))) {
                continue;
            }
            $shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $orderRow['shipping_method_id']);
            if($shippingMethodRow['pickup']) {
                if(!empty($shippingMethodRow['location_id'])) {
                    $shippingMethodName = "In-store pickup at " . getFieldFromId("description", "locations", "location_id", $shippingMethodRow['location_id']);
                } else {
                    $shippingMethodName = "In-store pickup";
                }
            } else {
                $shippingMethodName = $shippingMethodRow['description'];
            }
            $avsResponse = "U";
            if(strlen($eCommerceResponse['avs_response']) == 1) {
                $avsResponse = $eCommerceResponse['avs_response'];
            } else {
                foreach($this->iAvsResponses as $code => $responseArray) {
                    if(in_array($eCommerceResponse['avs_response'], $responseArray)) {
                        $avsResponse = $code;
                        break;
                    }
                }
            }
            $cvvResponse = "Y";
            if(strlen($eCommerceResponse['card_code_response']) == 1) {
                $cvvResponse = $eCommerceResponse['card_code_response'];
            } else {
                foreach($this->iCvvResponses as $code => $responseArray) {
                    if(in_array($eCommerceResponse['card_code_response'], $responseArray)) {
                        $cvvResponse = $code;
                        break;
                    }
                }
            }

            $data = array("customer" => $customer,
                "billTo" => $billTo,
                "payment" => $payment,
                "order" => array("invoiceNumber"=>$orderId),
                "gatewayName" => $gatewayName,
                "discountPrice" => $orderRow['order_discount'],
                "amount" => strval($orderPaymentsRow['amount'] + $orderPaymentsRow['shipping_charge'] + $orderPaymentsRow['tax_charge'] + $orderPaymentsRow['handling_charge']),
                "lineItems" => $orderItems,
                "customerIP" => $orderRow['ip_address'],
                "shippingMethod" => $shippingMethodName,
                "shippingAmount" => $orderPaymentsRow['shipping_charge'],
                "avsResultCode" => $avsResponse,
                "cvvResultCode" => $cvvResponse
            );
            // Only send shipTo if order is shipped (no pickup or virtual products)
            if(!empty($shippingMethodRow) && empty($shippingMethodRow['pickup'])) {
                $data["shipTo"] = $shipTo;
            }

            $result = $this->postApi("", $data);
            if(empty($result)) {
                $this->iErrorMessage = $this->iErrorMessage ?: "An error occurred accessing the API";
                return false;
            }
            $resultArray[] = $result;
        }
        if(empty($resultArray)) {
            $this->iErrorMessage = $this->iErrorMessage ?: "No payments that are able to be verified by NoFraud";
            return false;
        }

        return $this->parseResults($resultArray);
    }

    public function updateDecision($orderId) {
        $noFraudResult = CustomField::getCustomFieldData($orderId,"NOFRAUD_RESULT", "ORDERS");
        $noFraudResult = json_decode($noFraudResult,true);
        $resultArray = array();
        foreach($noFraudResult['results'] as $thisResult) {
            $result = $this->getApi("status_by_url/" . $this->iAccessToken . "/" . $thisResult['id'] );
            if(empty($result)) {
                $this->iErrorMessage = $this->iErrorMessage ?: "An error occurred accessing the API";
                return false;
            }
            $resultArray[] = $result;
        }

        return $this->parseResults($resultArray);
    }

    public static function isSetup() {
        $orderCustomFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "ORDERS");
        $noFraudResultCustomField = CustomField::getCustomFieldByCode("NOFRAUD_RESULT", "ORDERS");
        $analyticsCode = getFieldFromId("content", "analytics_code_chunks", "analytics_code_chunk_id",
            getFieldFromId("analytics_code_chunk_id", "templates", "template_id",
                getFieldFromId("template_id", "pages", "link_name", "home", "client_id = ?", $GLOBALS['gClientId'])));
        if (empty($orderCustomFieldTypeId) || empty($noFraudResultCustomField) || stristr($analyticsCode, "https://services.nofraud.com/js") === false) {
            return false;
        }
        return true;
    }

    public static function setup() {
        $orderCustomFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "ORDERS");
        if (empty($orderCustomFieldTypeId)) {
            $resultSet = executeQuery("insert into custom_field_types (custom_field_type_code, description) values ('ORDERS','Orders')");
            if (!empty($resultSet['sql_error'])) {
                $returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : "<br>") . $resultSet['sql_error'];
            }
            $orderCustomFieldTypeId = $resultSet['insert_id'];
        }
        $noFraudResultCustomField = CustomField::getCustomFieldByCode("NOFRAUD_RESULT", "ORDERS");
        if (empty($noFraudResultCustomField)) {
            $resultSet = executeQuery("insert into custom_fields (client_id, custom_field_code, description, custom_field_type_id, form_label) " .
                "values (?,'NOFRAUD_RESULT', 'NoFraud Result',?, 'NoFraud Result')", $GLOBALS['gClientId'], $orderCustomFieldTypeId);
            if (!empty($resultSet['sql_error'])) {
                $returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : "<br>") . $resultSet['sql_error'];
            } else {
                $noFraudResultCustomFieldId = $resultSet['insert_id'];
                executeQuery("insert into custom_field_controls (custom_field_id, control_name, control_value) " .
                    "values (?,'data_type','text')", $noFraudResultCustomFieldId);
            }
        }
        $analyticsCodeRow = getRowFromId("analytics_code_chunks", "analytics_code_chunk_id",
            getFieldFromId("analytics_code_chunk_id", "templates", "template_id",
                getFieldFromId("template_id", "pages", "link_name", "home", "client_id = ?", $GLOBALS['gClientId'])));
        if (stristr($analyticsCodeRow['content'], "https://services.nofraud.com/js") === false) {
            $customerNumber = getPreference("NOFRAUD_CUSTOMER_NUMBER");
            if (empty($customerNumber)) {
                $returnArray['error_message'] .= "Customer Number must be saved before installing analytics";
            } elseif(!is_numeric($customerNumber)) {
                $returnArray['error_message'] .= "Customer Number must be numeric only (do not include the full JavaScript snippet)";
            } else {
                $content = $analyticsCodeRow['content'] . "\n\n<script type='text/javascript' src='https://services.nofraud.com/js/" . $customerNumber . "/customer_code.js'></script>";
                $resultSet = executeQuery("update analytics_code_chunks set content = ? where analytics_code_chunk_id = ?", $content,
                    $analyticsCodeRow['analytics_code_chunk_id']);
                if (!empty($resultSet['sql_error'])) {
                    $returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : "<br>") . $resultSet['sql_error'];
                }
            }
        }
        return $returnArray;
    }

    private function parseResults($resultArray) {
        $counts = array();
        $total = 0;
        foreach($resultArray as $thisResult) {
            $counts[$thisResult['decision']]++;
            $total++;
        }
        if($counts['fail'] > 0) {
            $decision = "fail";
        } elseif($counts['review'] > 0) {
            $decision = "review";
        } else {
            $decision = "pass";
        }
        return array("decision"=>$decision,"results"=>$resultArray);
    }

}
