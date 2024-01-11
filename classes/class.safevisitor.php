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

const SAFEVISITOR_URL = 'https://api.safevisitor.io';

class SafeVisitor {
	private $iApiUrl;
	private $iErrorMessage;
	private $iAccessToken = "";
    private $iCredentials = array();
    private $iLogging;

	public function __construct($username, $password) {
		$this->iApiUrl = SAFEVISITOR_URL . '/v1';
        $this->iCredentials['un'] = $username;
        $this->iCredentials['pw'] = $password;
        $this->iLogging = !empty(getPreference("LOG_SAFEVISITOR"));
		$tokenExpiration = getPreference('SAFEVISITOR_TOKEN_EXPIRES');
        if(strtotime($tokenExpiration) < time()) {
            $this->getAccessToken();
        } else {
            $this->iAccessToken = getPreference('SAFEVISITOR_ACCESS_TOKEN');
        }
    }

	public function getErrorMessage() {
		return $this->iErrorMessage;
	}

    private function getAccessToken() {
        $curl = curl_init();

       $data = array(
            'username' => $this->iCredentials['un'],
            'password' => $this->iCredentials['pw']
        );
       $jsonData = jsonEncode($data);

        $headers = array(
            'Content-Type: application/json'
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL            => $this->iApiUrl . "/auth",
            CURLOPT_CUSTOMREQUEST  => "POST",
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
            $jsonData = str_replace($this->iCredentials['pw'],"*****", $jsonData);
            addDebugLog("SafeVisitor token request: " . $this->iApiUrl . "/auth"
                . "\nSafeVisitor Data: " . getFirstPart($result,500)
                . "\nSafeVisitor Result: " . $result
                . (empty($err) ? "" : "\nSafeVisitor Error: " . $err)
                . "\nSafeVisitor HTTP Status: " . $info['http_code']);
        }
        if ($result === false || ($info['http_code'] != 200 && $info['http_code'] != 202) && $info['http_code'] != 201) {
            if(!empty($result) && stristr($result,"password is incorrect") !== false) {
                sendCredentialsError(["integration_name"=>"SafeVisitor","error_message"=>$result]);
            }
            return $err . ":" . jsonEncode($result) . ":" . jsonEncode($info);
        }
        curl_close($curl);
        $this->setAccessToken(json_decode($result, true));
        return true;
    }

    private function setAccessToken($tokenResult) {
        if(!empty($tokenResult['expiresIn']) && is_numeric($tokenResult['expiresIn'])) {
            $expiresInSeconds = $tokenResult['expiresIn'];
        } else {
            $expiresInSeconds = 900;
        }
        $tokenExpiration = date("c", time() + $expiresInSeconds);
	    $preferenceArray = array(
	        array('code'=>'SAFEVISITOR_ACCESS_TOKEN', 'description'=>'SafeVisitor Access Token', 'data_type'=>'varchar', 'value'=> $tokenResult['accessToken']),
            array('code'=>'SAFEVISITOR_TOKEN_EXPIRES', 'description'=>'SafeVisitor Token Expires', 'data_type'=>'date', 'value'=> $tokenExpiration)
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
        $this->iAccessToken = $tokenResult['accessToken'];
    }

	public function postApi($apiMethod,$data, $verb = "POST", $refresh = false) {
	    if($refresh) {
	        $this->getAccessToken();
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
			CURLOPT_URL            => $this->iApiUrl . "/" . $apiMethod,
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
        if(!$refresh && $info['http_code'] == 401) {
            return $this->postApi($apiMethod, $data, $verb, true);
        }
        if($this->iLogging) {
            addDebugLog("SafeVisitor request: " . $this->iApiUrl . "/" . $apiMethod
                . "\nSafeVisitor Data: " . getFirstPart($jsonData,500)
                . "\nSafeVisitor Result: " . $result
                . (empty($err) ? "" : "\nSafeVisitor Error: " . $err)
                . "\nSafeVisitor HTTP Status: " . $info['http_code']);
        }

        if (($result === false && $info['http_code'] != 204) || (!in_array($info['http_code'], array(200,201,202,204)))) {
			$this->iErrorMessage = $err . ":" . jsonEncode($info) . ":" . $result . ":" . $jsonData;
			return false;
		}
		curl_close($curl);
		return json_decode($result,true);
	}

    private function getApi($apiMethod,$data = array(), $refresh = false) {
	    if($refresh) {
	        $this->getAccessToken();
        }

        if (!is_array($data)) {
            $data = array($data);
        }
        $queryParams = http_build_query($data);

        $curl = curl_init();

        $headers = array(
            'Authorization: Bearer ' . $this->iAccessToken,
            'Accept: */*'
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL            => $this->iApiUrl . "/" . $apiMethod . (empty($queryParams) ? "" : "?" . $queryParams),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
            CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
        ));

        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        $err = curl_error($curl);
        if(!$refresh && $info['http_code'] == 401) {
            return $this->getApi($apiMethod, $data, true);
        }
        if($this->iLogging) {
            addDebugLog("SafeVisitor request: " . $this->iApiUrl . "/" . $apiMethod . (empty($queryParams) ? "" : "?" . $queryParams)
                . "\nSafeVisitor Result: " . $result
                . (empty($err) ? "" : "\nSafeVisitor Error: " . $err)
                . "\nSafeVisitor HTTP Status: " . $info['http_code']);
        }

        if (($result === false && $info['http_code'] != 204) || (!in_array($info['http_code'], array(200,201,202,204)))) {
            $this->iErrorMessage = $err . ":" . jsonEncode($info) . ":" . $result . ":" . $queryParams;
            return false;
        }

        curl_close($curl);
        return json_decode($result,true);
    }

    public function checkStatus($contactIdentifiers) {
        if(!is_array($contactIdentifiers)) {
            $contactIdentifiers = array($contactIdentifiers);
        }
        $result = $this->postApi("statusbyproxy", array("ids" => $contactIdentifiers));
        $result = is_array($result) ? $result : array();
        // sort by submissionDate desc
        usort($result, function($a, $b) {
            return ((empty($a['submissionDate']) || empty($b['submissionDate'])) ? 0 : (strtotime($b['submissionDate']) <=> strtotime($a['submissionDate'])));
        });

        $returnArray = array();
        foreach($contactIdentifiers as $thisContactIdentifier) {
            $foundResult = "";
            foreach($result as $thisResult) {
                // response columns: id	proxyId	expirationDate	submissionDate	status
                if(is_array($thisResult) && $thisResult['proxyId'] == $thisContactIdentifier) {
                    if(strtolower($thisResult['status']) == "submitted" && strtotime($thisResult['submissionDate']) < strtotime("-1 month")) {
                        // submitted background check still not processed after 1 month: treat as expired. Should never happen in production.
                        $thisResult['expirationDate'] = $thisResult['expirationDate'] ?: date("Y-m-d", strtotime($thisResult['submissionDate'] . " +1 month"));
                    }
                    $foundResult = array("status"=>$thisResult['status'], "expiration_date"=>$thisResult['expirationDate'], "submission_date"=>$thisResult['submissionDate']);
                    break;
                }
            }
            $returnArray[$thisContactIdentifier] = $foundResult;
        }
        return $returnArray;
    }

    public function getGroups() {
        $returnArray = array();
        $result = $this->getApi("groups");
        if(empty($result)) {
            return false;
        }
        foreach($result as $thisResult) {
            if($thisResult['active']) {
                $returnArray[] = $thisResult;
            }
        }
        return $returnArray;
    }

	public static function backgroundCheckGroupChoices($publicOnly = true) {
		$safeVisitorConfig = Page::getClientPagePreferences("SAFEVISITOR");
		$returnArray = array();
		foreach ($safeVisitorConfig['groups'] as $thisGroup) {
            if(!$publicOnly || $thisGroup['public_access']) {
                $thisGroup['key_value'] = $thisGroup['group_name'];
                $returnArray[] = $thisGroup;
            }
		}
		return $returnArray;
	}

	public static function getApplicationUrl($applicationUrl, $proxyId) {
        if(!empty($applicationUrl)) {
            return trim($applicationUrl, "/") . "?externalid=" . base64_encode($proxyId);
        } else {
            return "";
        }
    }


}
