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

class ForgeRockOidc extends OidcProvider {

    private $iAccessToken;

    public function __construct($loginProviderCredentialId) {
        parent::__construct($loginProviderCredentialId);
        $this->iCallback = ltrim(getPreference("FORGE_ROCK_LOGIN_CALLBACK"),"/ ") ?: "callback";
    }

    function getLoginText() {
        return getPreference("FORGE_ROCK_LOGIN_TEXT") ?: "Login with ForgeRock";
    }

    function getLoginFragment() {
        return getFragment("FORGE_ROCK_LOGIN_FRAGMENT") ?: parent::getLoginFragment();
    }

    function getLoginUrl() {
        $securityToken = bin2hex(random_bytes(128/8));
        $state = http_build_query(["security_token"=>$securityToken,"credential_id"=>$this->iLoginProviderCredentialsRow['login_provider_credential_id'],
            "domain_name"=>$_SERVER['HTTP_HOST'],"referrer"=>$_GET['referrer'],"url"=>$_GET['url']]);
        $stateKey = "forgerock_oidc_state_" . $_SERVER['REMOTE_ADDR'];
        if(getFieldFromId("random_data_chunk_code", "random_data_chunks", "random_data_chunk_code", $stateKey)) {
            executeQuery("update random_data_chunks set text_data = ? where random_data_chunk_code = ?",$state, $stateKey);
        } else {
            executeQuery("insert ignore into random_data_chunks (random_data_chunk_code,text_data) values (?,?)", $stateKey, $state);
        }
        $loginUrl = sprintf("%s?client_id=%s&response_type=code&scope=%s&redirect_uri=%s&state=%s",
            $this->iLoginUrl,
            $this->iClientId,
            urlencode("openid profile email"),
            "https://" . $_SERVER['HTTP_HOST'] . "/" . $this->iCallback,
            urlencode($state));
        $additionalParameters = getPreference("FORGE_ROCK_ADDITIONAL_QUERY_PARAMETERS");
        if(stristr($additionalParameters,"=") !== false && stristr($additionalParameters, "?") === false) {
            $loginUrl = $loginUrl . "&" . ltrim($additionalParameters,"&");
        }
        return $loginUrl;
    }

    function checkResponse($parameters, $returnedStateArray = array()) {
        $stateKey = "forgerock_oidc_state_" . $_SERVER['REMOTE_ADDR'];
        $state = getFieldFromId("text_data", "random_data_chunks", "random_data_chunk_code", $stateKey);
        $savedStateArray = array();
        parse_str($state, $savedStateArray);
        if(empty($returnedStateArray)) {
            parse_str($parameters['state'], $returnedStateArray);
        }
        if($returnedStateArray['security_token'] != $savedStateArray['security_token']) {
            if($this->iLogging) {
                addDebugLog("checkResponse for IP " . $_SERVER['REMOTE_ADDR'] . " failed:\nreturned state: "
                    . jsonEncode($returnedStateArray) . "\nsaved state: " . jsonEncode($savedStateArray));
            }
            return false;
        }
        executeQuery("delete ignore from random_data_chunks where random_data_chunk_code = ?", $stateKey);
        return true;
    }

    public function processUserInfo($parameters) {
        $idToken = $this->getIdToken($parameters['code']);
        if(!is_array($idToken)) {
            return false;
        }
        $result = $this->getUserInfo();
        $result = array_merge($idToken, is_array($result) ? $result : array());
        $result['user_name'] = $result['email'];
        return $result;
    }

    function getIdToken($authorizationCode) {
        $curl = curl_init();

        $data = array(
            'grant_type' => "authorization_code",
            'code' => $authorizationCode,
            'client_id' => $this->iClientId,
            'client_secret' => $this->iClientSecret,
            'redirect_uri' => "https://" . $_SERVER['HTTP_HOST'] . "/" . $this->iCallback
        );

        $headers = array(
            'Content-Type: application/x-www-form-urlencoded'
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->iTokenUrl,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
            CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
        ));

        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        $err = curl_error($curl);
        if ($this->iLogging) {
            $logEntry = "ForgeRock Token request: " . http_build_query($data) .
                "\nForgeRock Token response: " . $result;
            $logEntry .= (empty($err) ? "" : "\nForgeRock Token Error: " . $err);
            addDebugLog($logEntry);
        }
        if ($result === false || !in_array($info['http_code'], [200,201,202])) {
            $this->iErrorMessage = ($err ?: "Error getting access token") . ":" . $result;
            return false;
        }
        curl_close($curl);
        $resultArray = json_decode($result, true);
        if (!empty($resultArray['access_token'])) {
            $this->iAccessToken = $resultArray['access_token'];
        } else {
            $this->iErrorMessage = "Error getting access token";
        }
        $idToken =$this->decodeJwt($resultArray['id_token']);
        if($this->iLogging) {
            addDebugLog("ForgeRock ID Token: " . jsonEncode($idToken));
        }
        return $idToken;
    }

    public function getUserInfo() {
        if(empty($this->iAccessToken)) {
            $this->iErrorMessage = "Access Token required to get user info";
            return false;
        }
        $curl = curl_init();

        $headers = array(
            'Authorization: Bearer ' . $this->iAccessToken
        );

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->iUserInfoUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
            CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4)
        ));

        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        $err = curl_error($curl);
        if ($this->iLogging) {
            $logEntry = "ForgeRock request: " . $this->iUserInfoUrl .
                "\nForgeRock response: " . $result;
            $logEntry .= (empty($err) ? "" : "\nForgeRock Token Error: " . $err);
            addDebugLog($logEntry);
        }
        if ($result === false || !in_array($info['http_code'], [200,201,202])) {
            $this->iErrorMessage = ($err ?: "Error getting access token") . ":" . $result;
            return false;
        }
        curl_close($curl);
        $resultArray = json_decode($result, true);
        if(empty($resultArray)) {
            $this->iErrorMessage = "Error getting user info";
            return false;
        }
        return $resultArray;

    }
}
