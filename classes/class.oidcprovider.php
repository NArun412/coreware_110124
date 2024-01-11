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

class OidcProvider {

    protected $iLogging;
    protected $iErrorMessage;
    protected $iClientId;
    protected $iClientSecret;
    protected $iDiscoveryUrl;
    protected $iLoginUrl;
    protected $iTokenUrl;
    protected $iUserInfoUrl;
    protected $iCallback;
    protected $iLoginProviderCredentialsRow;


    public function __construct($loginProviderCredentialId) {
        $this->iLoginProviderCredentialsRow = getRowFromId("login_provider_credentials", "login_provider_credential_id", $loginProviderCredentialId);
        $this->iClientId = $this->iLoginProviderCredentialsRow['account_login'];
        $this->iClientSecret = $this->iLoginProviderCredentialsRow['account_key'];
        $this->iDiscoveryUrl = $this->iLoginProviderCredentialsRow['link_url'];
        $this->iLogging = !empty(getPreference("LOG_SSO"));
    }

    public function getErrorMessage() {
        return $this->iErrorMessage;
    }

    function hasCredentials() {
        $this->discoverUrls($this->iDiscoveryUrl);
        return !in_array(false,[$this->iClientId, $this->iClientSecret, $this->iLoginUrl, $this->iCallback, $this->iTokenUrl, $this->iUserInfoUrl]);
    }

    function getCredentialId() {
        return $this->iLoginProviderCredentialsRow['login_provider_credential_id'];
    }

    static function getOidcProviders($publicAccessOnly = false) {
        $providers = array();
        $hidePublicOnAdminLogin = getPreference("SSO_HIDE_PUBLIC_ACCESS_PROVIDERS_FOR_ADMIN_LOGIN");
        $providerCredentialSet = executeQuery("select * from login_provider_credentials where client_id = ? and inactive = 0 " .
            ($publicAccessOnly ? "and public_access = 1" : (empty($hidePublicOnAdminLogin) ? "" : "and public_access = 0")) . " order by sort_order", $GLOBALS['gClientId']);
        while($providerCredentialRow = getNextRow($providerCredentialSet)) {
            $oidcProviderClass = getFieldFromId("class_name", "login_providers", "login_provider_id", $providerCredentialRow['login_provider_id']);
            if(!empty($oidcProviderClass) && class_exists($oidcProviderClass)) {
                $oidcProvider = new $oidcProviderClass($providerCredentialRow['login_provider_credential_id']);
                if(method_exists($oidcProvider, "hasCredentials") && $oidcProvider->hasCredentials()) {
                    $providers[] = $oidcProvider;
                }
            }
        }
        if(!empty($_GET['coreware_login']) || $_COOKIE['LAST_LOGIN_PROVIDER'] == "CorewareOidc" || (!$publicAccessOnly && (endsWith($_SERVER['HTTP_HOST'],"coreware.com")
                || endsWith($_SERVER['HTTP_HOST'],"corefire.shop") || endsWith($_SERVER['HTTP_HOST'],"officemadeeasy.com") || $GLOBALS['gLocalExecution']))) {
            $oidcProviderClass = "CorewareOidc";
            if (class_exists($oidcProviderClass)) {
                $oidcProvider = new $oidcProviderClass();
                if (method_exists($oidcProvider, "hasCredentials") && $oidcProvider->hasCredentials()) {
                    $providers[] = $oidcProvider;
                }
            }
        }
        return $providers;
    }

    static function getOidcProviderById($loginProviderCredentialId) {
        if(empty($loginProviderCredentialId)) {
            $oidcProviderClass = "CorewareOidc";
        } else {
            $providerCredentialRow = getRowFromId("login_provider_credentials", "login_provider_credential_id", $loginProviderCredentialId);
            $oidcProviderClass = getFieldFromId("class_name", "login_providers", "login_provider_id", $providerCredentialRow['login_provider_id']);
        }
        if(!empty($oidcProviderClass) && class_exists($oidcProviderClass)) {
            $oidcProvider = new $oidcProviderClass($loginProviderCredentialId);
            if(method_exists($oidcProvider, "hasCredentials") && $oidcProvider->hasCredentials()) {
                return $oidcProvider;
            }
        }
        return false;
    }

    static function getSsoLoginOption() {
        return getPreference('SSO_OPTION');
    }

    function createUsersFromLogin() {
        return $this->iLoginProviderCredentialsRow['allow_create_user'];
    }

    function getLoginText() {
        return "Login with SSO";
    }

    function getLoginFragment() {
        return "<button class='sso-login-button' data-href='%link_url%'>%login_text%</button>";
    }

    function discoverUrls($discoveryUrl) {
        $resultArray = getCachedData("oidc_urls", $discoveryUrl);
        if(empty($resultArray)) {
            $discoveryContent = file_get_contents($discoveryUrl);
            if(!empty($discoveryContent)) {
                $resultArray = json_decode($discoveryContent, true);
            }
            if (empty($resultArray)) {
                $this->iErrorMessage = "Error getting OIDC Urls";
                return false;
            }
            setCachedData("oidc_urls", $discoveryUrl, $resultArray);
        }
        $this->iLoginUrl = $resultArray['authorization_endpoint'];
        $this->iTokenUrl = $resultArray['token_endpoint'];
        $this->iUserInfoUrl = $resultArray['userinfo_endpoint'];
        return true;
    }

    function getLoginUrl() {
        return "/login";
    }

    function checkResponse($parameters, $returnedStateArray = array()) {
        return false;
    }

    protected function decodeJwt($jwt) {
        $parts = explode(".", $jwt);
        if(count($parts) == 3) {
            $payload = json_decode(base64_decode($parts[1]),true);
        }
        return $payload;
    }

    protected function getBrowserFingerprint() {
        $fingerprintFields = ['HTTP_ACCEPT','HTTP_ACCEPT_ENCODING','HTTP_ACCEPT_LANGUAGE','HTTP_SEC_CH_UA','HTTP_SEC_CH_UA_MOBILE','HTTP_SEC_CH_UA_PLATFORM','HTTP_USER_AGENT'];
        $fingerprintData = array_intersect_key($_SERVER,array_flip($fingerprintFields));
        ksort($fingerprintData);
        return md5(jsonEncode($fingerprintData));
    }

    function getIdToken($authorizationCode) {
        return false;
    }

    function processUserInfo($parameters) {
        return false;
    }
}
