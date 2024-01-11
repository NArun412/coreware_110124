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


class CorewareOidc extends GoogleOidc {

    public function __construct($loginProviderCredentialId = 0) {
        parent::__construct($loginProviderCredentialId);
        $this->iDiscoveryUrl = "https://accounts.google.com/.well-known/openid-configuration";
        $this->iClientId = getPreference("COREWARE_OIDC_CLIENT_ID");
        $this->iClientSecret = getPreference("COREWARE_OIDC_CLIENT_SECRET");
        if($GLOBALS['gLocalExecution']) {
            $this->iIdServer = "https://dev-id.coreware.net";
            $this->iCallback = "https://dev-id.coreware.net/callback";
        } else {
            $this->iIdServer = "https://id.coreware.com";
            $this->iCallback = "https://id.coreware.com/callback";
            $this->iIdServerBackup = "https://id2.coreware.com";
            $this->iCallbackBackup = "https://id2.coreware.com/callback";
        }
    }

    function getLoginText() {
        return "Coreware staff login";
    }
    function getLoginFragment() {
        $fragmentContent = getFragment("COREWARE_LOGIN_FRAGMENT");
        if(empty($fragmentContent)) {
            $fragmentContent = getFieldFromId("content", "fragments", "fragment_code", "COREWARE_LOGIN_FRAGMENT",
                "client_id = ?", $GLOBALS['gDefaultClientId']);
            if(!empty($fragmentContent)) {
                $fragmentContent = PlaceHolders::massageContent($fragmentContent);
            }
        }
        return $fragmentContent ?: "<button class='sso-login-button' data-href='%link_url%'>%login_text%</button>";
    }

    function getCredentialId() {
        return 0;
    }

    function processUserInfo($parameters) {
        $result = parent::processUserInfo($parameters);
        if(!is_array($result)) {
            return false;
        }
        $result['user_name'] = strtolower($result['given_name'] . "." . $result['family_name']);
        return $result;
    }

}
