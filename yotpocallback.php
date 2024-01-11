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

$GLOBALS['gPageCode'] = "YOTPOCALLBACK";
require_once "shared/startup.inc";

class YotpoCallbackPage extends Page {

	function mainContent() {
		switch ($_GET['action']) {
			case 'products':
				// Yotpo call back does not include any POST data, and the IP may change.
				// The App key will be included in the callback URL to verify that the callback is from Yotpo
				$yotpoAppKey = getPreference('YOTPO_APP_KEY');
				$yotpoSecretKey = getPreference('YOTPO_SECRET_KEY');

				if (!empty($yotpoAppKey) && !empty($yotpoSecretKey)) {
					$programLogId = addProgramLog("Yotpo products callback received.\n\n" . jsonEncode($_POST));
					if ($_GET['key'] == $yotpoAppKey) {
						$yotpo = new Yotpo($yotpoAppKey, $yotpoSecretKey);
						$count = $yotpo->massCreateProducts();
						$err = $yotpo->getErrorMessage();
						if ($count > 0) {
							$result = $count . " Products sent to Yotpo.";
						} elseif (!empty($err)) {
							$result = "An error occurred sending products to Yotpo: " . $err;
						} else {
							$result = "No more products to send to Yotpo.";
						}
					} else {
						$result = "Key provided '" . htmlText($_GET['key']) . "' does not match Yotpo App Key.";
					}
					addProgramLog("\n\n" . $result, $programLogId);
				} else {
					$result = 'This page can only be called via Yotpo.';
				}
				break;
			case 'loyalty':
				$yotpoLoyaltyApiKey = getPreference("YOTPO_LOYALTY_API_KEY");
				$yotpoLoyaltyGuid = getPreference("YOTPO_LOYALTY_GUID");

				if (!empty($yotpoLoyaltyApiKey) && !empty($yotpoLoyaltyGuid)) {
					$headerInput = file_get_contents("php://input");
					$programLogId = addProgramLog("Yotpo Loyalty callback received."
						. "\n\ninput: " . $headerInput);
					$yotpoLoyalty = new YotpoLoyalty($yotpoLoyaltyApiKey, $yotpoLoyaltyGuid);
					if (empty($_POST) && !empty($headerInput)) {
                        if (substr($headerInput, 0, 1) == "[" || substr($headerInput, 0, 1) == "{") {
                            $_POST = json_decode($headerInput, true);
                            if (json_last_error() != JSON_ERROR_NONE) {
                                $result = "JSON format error: " . $headerInput;
                                break;
                            }
                        } else {
                            parse_str($headerInput, $_POST);
                        }
                    }
					if (array_key_exists("json_post_parameters", $_POST)) {
						try {
							$postParameters = json_decode($_POST['json_post_parameters'], true);
							$_POST = array_merge($_POST, $postParameters);
							unset($_POST['json_post_parameters']);
						} catch (Exception $e) {
						}
					}
					switch($_POST['topic']) {
                        case "swell/redemption_code/below_threshold":
                            $count = $yotpoLoyalty->uploadCoupons($_POST);
                            $err = $yotpoLoyalty->getErrorMessage();
                            if (!empty($err)) {
                                $result = "An error occurred sending coupons to Yotpo Loyalty: " . $err;
                            } else {
                                $result = $count . " coupons sent to Yotpo Loyalty.";
                            }
                            break;
                        default:
                            $result = "Webhook event '" . htmlText($_POST['topic']) . "' has no corresponding action.";
                            break;
                    }
					addProgramLog("\n\n" . $result, $programLogId);
				} else {
					$result = 'This page can only be called via Yotpo.';
				}
				break;
			default:
				$result = 'This page can only be called via Yotpo.';
				break;
		}

		echo '<p>' . $result . '</p>';
	}
}

$pageObject = new YotpoCallbackPage();
$pageObject->displayPage();
