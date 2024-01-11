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

const YOTPO_URL = 'https://api.yotpo.com';

class Yotpo {
	private $iApiUrl;
	private $iErrorMessage;
	private $iAccessToken = "";
	private $iClientId = "";
	private $iClientSecret = "";
    private $iLogging = false;
    private $iLogLength;

	public function __construct($clientId, $clientSecret) {
		$this->iApiUrl = YOTPO_URL;
		$this->iClientId = $clientId;
		$this->iClientSecret = $clientSecret;
        $this->iAccessToken = getPreference('YOTPO_ACCESS_TOKEN');
		$tokenExpiration = getPreference('YOTPO_TOKEN_EXPIRES');
        $this->iLogging = !empty(getPreference('LOG_YOTPO'));
        $this->iLogLength = getPreference("YOTPO_LOG_LENGTH");
        $this->iLogLength = is_numeric($this->iLogLength) ? $this->iLogLength : 500;
        if(strtotime($tokenExpiration) < time()) {
            $this->iAccessToken = self::getAccessToken($this->iClientId, $this->iClientSecret);
        }
    }

	public function getErrorMessage() {
		return $this->iErrorMessage;
	}

    public static function getAccessToken($clientId, $clientSecret) {
        $curl = curl_init();

        $data = array(
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type'=>'client_credentials'
        );

        $headers = array(
            'Content-Type: application/json'
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL            => YOTPO_URL . "/oauth/token",
            CURLOPT_CUSTOMREQUEST  => "POST",
            CURLOPT_POSTFIELDS     => jsonEncode($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
            CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
        ));

        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        // todo: confirm error and success codes

        if ($result === false || ($info['http_code'] != 200 && $info['http_code'] != 202) && $info['http_code'] != 201) {
            $err = curl_error($curl) . ":" . jsonEncode($result) . ":" . jsonEncode($info);
            return $err;
        }
        curl_close($curl);
        return self::setAccessToken(json_decode($result, true));
    }

    public static function setAccessToken($tokenResult) {
	    $tokenExpiration = date_add(date_create(), date_interval_create_from_date_string('14 days'));
	    $preferenceArray = array(
	        array('code'=>'YOTPO_ACCESS_TOKEN', 'description'=>'Yotpo Access Token', 'data_type'=>'varchar', 'value'=> $tokenResult['access_token']),
            array('code'=>'YOTPO_TOKEN_EXPIRES', 'description'=>'Yotpo Token Expires', 'data_type'=>'date', 'value'=> $tokenExpiration->format('c'))
        );

	    foreach($preferenceArray as $thisPreference) {
            $preferenceId = getFieldFromId("preference_id", "preferences", "preference_code", $thisPreference['code']);
            if(empty($preferenceId)) {
                $result = executeQuery("insert into preferences (preference_code,description, data_type, client_setable) values (?,?,?, 1)",
                    $thisPreference['code'], $thisPreference['description'], $thisPreference['data_type']);
                $preferenceId = $result['insert_id'];
            }
            executeQuery("delete from client_preferences where client_id = ? and preference_id = ?", $GLOBALS['gClientId'], $preferenceId);
            executeQuery("insert into client_preferences (client_id,preference_id,preference_value) values (?,?,?)", $GLOBALS['gClientId'], $preferenceId, $thisPreference['value']);
        }
        return $tokenResult['access_token'];
    }

	public function postApi($apiMethod,$data, $verb = "POST", $refresh = false) {
	    if($refresh) {
            $this->iAccessToken = self::getAccessToken($this->iClientId, $this->iClientSecret);
        }

		if (!is_array($data)) {
			$data = array($data);
		}
		$jsonData = json_encode($data);

		$curl = curl_init();

		$headers = array(
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->iAccessToken,
			'Accept: application/json,*/*'
		);

		curl_setopt_array($curl, array(
			CURLOPT_URL            => YOTPO_URL . "/" . $apiMethod,
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
        curl_close($curl);
        $dataExcerpt = strlen($jsonData) < 500 ? $jsonData : substr($jsonData,0,500) . "...";
        if(!$refresh && $info['http_code'] == 401) {
            return $this->postApi($apiMethod, $data, $verb, true);
        }
        if($this->iLogging) {
            addDebugLog("Yotpo request: " . $this->iApiUrl . "/" . $apiMethod
                . "\nYotpo Data: " . (strlen($jsonData) > $this->iLogLength ? substr($jsonData,0,$this->iLogLength) . "..." : $jsonData)
                . "\nYotpo Result: " . $result
                . (empty($err) ? "" : "\nYotpo Error: " . $err)
                . "\nYotpo HTTP Status: " . $info['http_code']);
        }
        if ($result === false && $info['http_code'] != 204) {
            $this->iErrorMessage = $err . ":" . jsonEncode($info) . ":" . $dataExcerpt;
            return false;
        } elseif (!in_array($info['http_code'], array(200,201,202,204))) {
            $this->iErrorMessage = $result . ":" . $err . ":" . jsonEncode($info) . ":" . $dataExcerpt;
            return false;
        }
		return json_decode($result,true);
	}

    private function getApi($apiMethod,$data = array(), $refresh = false) {
	    if($refresh) {
            $this->iAccessToken = self::getAccessToken($this->iClientId, $this->iClientSecret);
        }

        if (!is_array($data)) {
            $data = array($data);
        }
        $queryParams = http_build_query($data);

        $curl = curl_init();

        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->iAccessToken,
            'Accept: application/json,*/*'
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL            => YOTPO_URL . "/" . $apiMethod . (empty($queryParams) ? "?" . $queryParams : ""),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
            CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
        ));

        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if(!$refresh && $info['http_code'] == 401) {
            return $this->getApi($apiMethod, $data,true);
        }
        if($this->iLogging) {
            addDebugLog("Yotpo request: " . $this->iApiUrl . "/" . $apiMethod
                . "\nYotpo Result: " . $result
                . (empty($err) ? "" : "\nYotpo Error: " . $err)
                . "\nYotpo HTTP Status: " . $info['http_code']);
        }
        if ($result === false && $info['http_code'] != 204) {
            $this->iErrorMessage = $err . ":" . jsonEncode($info);
            return false;
        } elseif (!in_array($info['http_code'], array(200,201,202,204))) {
            $this->iErrorMessage = $result . ":" . $err . ":" . jsonEncode($info);
            return false;
        }
        return json_decode($result,true);
    }

    public function logOrder($orderId) {
        $orderRow = getRowFromId("orders", "order_id", $orderId);
        $contactRow = Contact::getContact($orderRow['contact_id']);
        $phoneNumber = getContactPhoneNumber($contactRow['contact_id']);

        $orderItems = array();
        $resultSet = executeQuery("select * from order_items join products using (product_id) join product_data using (product_id) where order_id = ?", $orderId);
        $domainName = getDomainName();

        while ($row = getNextRow($resultSet)) {
            $row['sale_price'] = round($row['sale_price'],2);
            $row['upc_code'] = str_replace(" ", "_", $row['upc_code']);
            $sendId = (empty($row['upc_code']) ? $row['product_code'] : $row['upc_code']);
            $sku = str_replace(" ", "_", ($row['manufacturer_sku'] ?: $row['product_code']));
            $orderItems[$sendId] = array(
                "name" => $row['description'],
                "url" => $domainName . "/product/" . $row['link_name'],
                "description" => $row['detailed_description'],
                "currency" => 'USD',
                "price" => $row['sale_price'],
                "coupon_used" => array(getFieldFromId("promotion_code", "promotions", "promotion_id",
                    getFieldFromId("promotion_id", "order_promotions", "order_id", $orderId))),
                "specs" => array(
                    "upc" => $row['upc_code'],
                    "brand" => getFieldFromId('description', 'product_manufacturers', 'product_manufacturer_id', $row['product_manufacturer_id']),
                    "external_sku" => $sku
                ));
        }
        freeResult($resultSet);

        $order = array(
            "platform" => "general",
            "utoken" => $this->iAccessToken,
            "app_key" => $this->iClientId,
            "email" => $contactRow['email_address'],
            "customer_name" => getDisplayName($contactRow['contact_id']),
            "order_id" => $orderId,
            "order_date" => date_format(date_create($orderRow['order_time']), "c"),
            "currency_iso" => "USD",
            "products" => $orderItems,
            "customer" => array(
                "country" => getFieldFromId("country_code", "countries", "country_id", $contactRow['country_id']),
                "state" => $contactRow['state'],
                "address" => getAddressBlock($contactRow, ", "),
                "phone" => $phoneNumber
            ));

        $result = $this->postApi("apps/" . $this->iClientId . "/purchases", $order);
        if (!$result) {
            addProgramLog("Yotpo Error on Order: " . $this->getErrorMessage());
        } else {
            addProgramLog("Yotpo Results on Order: " . jsonEncode($result) . ":" . jsonEncode($order));
        }
    }

    public function massCreateProducts() {
        $domainName = getDomainName();
        $updateFolder = $GLOBALS['gDocumentRoot'] . "/cache/" . $GLOBALS['gClientRow']['client_code'] . "-yotpo";
	    $filename = "";
        if ($handle = opendir($updateFolder)) {
            while (false !== ($filename = readdir($handle))) {
                if ($filename != "." && $filename != "..") {
                    break;
                }
            }
            closedir($handle);
        }
        if(!empty($filename)) {
            $productArray = json_decode(file_get_contents($updateFolder . "/" . $filename), true);
            if(!empty($productArray)) {
                $sendArray = array("utoken"=>$this->iAccessToken, "result_callback_url"=>$domainName . "/yotpo-callback?action=products&key=" . $this->iClientId, "products"=>$productArray);
                $returnEmailArray = getNotificationEmails('YOTPO_RESULTS');
                if(!empty($returnEmailArray)) {
                    $sendArray['return_email_address'] = $returnEmailArray[0];
                }
                $this->postApi("apps/" . $this->iClientId . "/products/mass_create", $sendArray);
                if(empty($this->iErrorMessage)) {
                    if($this->iLogging) {
                        addDebugLog("Yotpo Products update: " . $filename . " processed.");
                    }
                    unlink($updateFolder . "/" . $filename);
                    return count($productArray);
                }
            }
        }
        return 0;
    }

}
