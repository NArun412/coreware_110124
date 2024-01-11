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

class GoogleOidc extends OidcProvider {

    protected  $iAccessToken;
    protected  $iIdServer;
    protected $iIdServerBackup;
    protected $iCallbackBackup;


    public function __construct($loginProviderCredentialId) {
        parent::__construct($loginProviderCredentialId);
        $this->iIdServer = getPreference("GOOGLE_LOGIN_ID_SERVER");
        $this->iIdServerBackup = getPreference("GOOGLE_LOGIN_ID_SERVER_BACKUP");
        $this->iDiscoveryUrl = "https://accounts.google.com/.well-known/openid-configuration";
        $this->iCallback = ($this->iIdServer ?: "https://" . $_SERVER['HTTP_HOST']) . "/" . (ltrim(getPreference("GOOGLE_OIDC_CALLBACK"),"/ ") ?: "callback");
        $this->iCallbackBackup = ($this->iIdServerBackup ?: "https://" . $_SERVER['HTTP_HOST']) . "/" . (ltrim(getPreference("GOOGLE_OIDC_CALLBACK"),"/ ") ?: "callback");
    }

    function getLoginText() {
        return getPreference("GOOGLE_LOGIN_TEXT") ?: "Login with Google";
    }

    function getLoginFragment() {
        return getFragment("GOOGLE_LOGIN_FRAGMENT") ?: parent::getLoginFragment();
    }

    private function getStateKey($domainName = "", $omitDomain = false) {
        if($omitDomain) {
            return "google_oidc_state_" . $_SERVER['REMOTE_ADDR'];
        }
        $domainName = $domainName ?: $_SERVER['HTTP_HOST'];
        return sprintf("google_oidc_state_%s_%s", $domainName, $_SERVER['REMOTE_ADDR']);
    }

    function getLoginUrl() {
        $securityToken = bin2hex(random_bytes(128/8));
        $fingerprintHash = $this->getBrowserFingerprint();
        $state = http_build_query(["security_token"=>$securityToken,"credential_id"=>$this->iLoginProviderCredentialsRow['login_provider_credential_id'],
            "domain_name"=>$_SERVER['HTTP_HOST'],"referrer"=>$_GET['referrer'],"url"=>$_GET['url'],"fingerprint"=>$fingerprintHash]);
        $stateKey = $this->getStateKey();
        if(getFieldFromId("random_data_chunk_code", "random_data_chunks", "random_data_chunk_code", $stateKey)) {
            executeQuery("update random_data_chunks set text_data = ? where random_data_chunk_code = ?",$state, $stateKey);
        } else {
            executeQuery("insert ignore into random_data_chunks (random_data_chunk_code,text_data) values (?,?)", $stateKey, $state);
        }
        $callbackUrl = $this->iCallback;
        if(!empty($this->iIdServer) && $_SERVER['HTTP_HOST'] != $this->iIdServer) {
            $saveStateResult = $this->saveStateDataToIdServer($stateKey, $state);
            if(!$saveStateResult && !empty($this->iIdServerBackup) && $_SERVER['HTTP_HOST'] != $this->iIdServerBackup) {
                $this->saveStateDataToIdServer($stateKey, $state,true);
                $callbackUrl = $this->iCallbackBackup;
            }
        }
        $loginUrl = sprintf("%s?client_id=%s&response_type=code&scope=%s&redirect_uri=%s&state=%s&nonce=%s",
            $this->iLoginUrl,
            $this->iClientId,
            urlencode("openid profile email"),
            $callbackUrl,
            urlencode($state),
            getRandomString(24));
        return $loginUrl;
    }

    function checkResponse($parameters, $returnedStateArray = array()) {
        if(empty($returnedStateArray)) {
            parse_str($parameters['state'], $returnedStateArray);
        }
        $stateKey = $this->getStateKey($returnedStateArray['domain_name']);
        $state = getFieldFromId("text_data", "random_data_chunks", "random_data_chunk_code", $stateKey);
        $logEntry = "checked state key: $stateKey\nfound state: $state";
        if(empty($state)) { // for backward compatibility
            $stateKey = $this->getStateKey("", true);
            $state = getFieldFromId("text_data", "random_data_chunks", "random_data_chunk_code", $stateKey);
            $logEntry .= "\nchecked state key: $stateKey\nfound state: $state";
        }
        $savedStateArray = array();
        parse_str($state, $savedStateArray);
        if($returnedStateArray['security_token'] != $savedStateArray['security_token']) {
            $fingerprintHash = $this->getBrowserFingerprint();
            $state = getFieldFromId("text_data", "random_data_chunks", null, null,
                "text_data like ?", '%fingerprint=' . $fingerprintHash);
            $logEntry .= "\nchecked state by fingerprint: $fingerprintHash\nfound state: $state";
            if (!empty($state)) {
                parse_str($state, $savedStateArray);
            }
        }
        if($returnedStateArray['security_token'] != $savedStateArray['security_token']) {
            if($this->iLogging) {
                $incorrectKey = getFieldFromId("random_data_chunk_code", "random_data_chunks", null, null,
                "text_data like ?", '%' . $returnedStateArray['security_token'] . '%');
                addDebugLog( "checkResponse for IP " . $_SERVER['REMOTE_ADDR'] . " failed:" .
                    "\nreturned state: ". jsonEncode($returnedStateArray) . "\nsaved state: " . jsonEncode($savedStateArray) .
                    (empty($incorrectKey) ? "" : "\nstate found in different key: " . $incorrectKey) . "\n$logEntry");
            }
            return false;
        }
        executeQuery("delete ignore from random_data_chunks where random_data_chunk_code = ?", $stateKey);
        if(!empty($savedStateArray['domain_name']) && $savedStateArray['domain_name'] != $_SERVER['HTTP_HOST']) {
            $otherDomainOnSameServer = getFieldFromId("domain_name", "domain_names", "domain_name", $savedStateArray['domain_name']);
            if(!$otherDomainOnSameServer) { // don't delete the saved state if the original domain is on the same server
                executeQuery("delete ignore from random_data_chunks where random_data_chunk_code = ?", $stateKey);
            }
            return $savedStateArray['domain_name'];
        }
        return true;
    }

    private function saveStateDataToIdServer($stateKey, $stateData, $useBackupServer = false) {
        $curl = curl_init();

        $data = jsonEncode(array(
            'connection_key' => "760C0DCAB2BD193B585EB9734F34B3B6",
            'state_key' => $stateKey,
            'state_data' => $stateData
        ));

        $server = ($useBackupServer && !empty($this->iIdServerBackup)) ? $this->iIdServerBackup : $this->iIdServer;
        if(!startsWith($server, "http")) {
            $server = "https://" . $server;
        }

        curl_setopt_array($curl, array(
            CURLOPT_URL => $server . "/api.php?action=save_oidc_state",
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => $GLOBALS['gCurlTimeout'],
            CURLOPT_TIMEOUT => ($GLOBALS['gCurlTimeout'] * 4),
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json"
            ),
        ));
        if($GLOBALS['gLocalExecution']) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        }

        $result = curl_exec($curl);
        $info = curl_getinfo($curl);
        $err = curl_error($curl);
        if ($this->iLogging) {
            $logEntry = "Google OIDC Save State request: " . $data .
                "\nGoogle OIDC Save State response: " . $result;
            $logEntry .= (empty($err) ? "" : "\nGoogle OIDC Save State Error: " . $err);
            addDebugLog($logEntry);
        }
        $resultArray = json_decode($result,true);

        return empty($err) && $resultArray['result'] == "OK";
    }

    public function processUserInfo($parameters) {
        $idToken = $this->getIdToken($parameters['code']);
        if(!is_array($idToken)) {
            return false;
        }
        $result = $this->getUserInfo();
        $result = array_merge( $idToken, is_array($result) ? $result : array());
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
            'redirect_uri' => $this->iCallback
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
            $logEntry = "Google OIDC Token request: " . http_build_query($data) .
                "\nGoogle OIDC Token response: " . $result;
            $logEntry .= (empty($err) ? "" : "\nGoogle OIDC Token Error: " . $err);
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
            addDebugLog("Google OIDC ID Token: " . jsonEncode($idToken));
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
            $logEntry = "Google OIDC request: " . $this->iUserInfoUrl .
                "\nGoogle OIDC response: " . $result;
            $logEntry .= (empty($err) ? "" : "\nGoogle OIDC Error: " . $err);
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
