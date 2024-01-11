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

$GLOBALS['gPageCode'] = "TESTECOMMERCE";
require_once "shared/startup.inc";

class TestEcommercePage extends Page {

	function executePageUrlActions() {
		$returnArray = array();
		if (!empty($_GET['url_action'])) {
			if (!empty($_POST['merchant_account_id'])) {
				$developmentAccountId = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "DEVELOPMENT");
				if (!empty($developmentAccountId) && $developmentAccountId != $_POST['merchant_account_id']) {
					$developmentAccountDescription = getFieldFromId("description", "merchant_accounts", "merchant_account_code", "DEVELOPMENT");
					$developmentAccountCode = makeCode($developmentAccountDescription);
					$i = 0;
					while (getFieldFromId("merchant_account_code", "merchant_accounts", "merchant_account_code", $developmentAccountCode)) {
						$developmentAccountCode = makeCode($developmentAccountDescription) . '_' . ++$i;
					}
					executeQuery("update merchant_accounts set merchant_account_code = ? where merchant_account_id = ?",
						$developmentAccountCode, $developmentAccountId);
					executeQuery("update merchant_accounts set merchant_account_code = 'DEVELOPMENT' where merchant_account_id = ?", $_POST['merchant_account_id']);
				}
			}
			$merchantAccountId = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "DEVELOPMENT");
			if (empty($merchantAccountId) && empty($GLOBALS['gUserRow']['superuser_flag'])) {
				$returnArray['error_message'] = "No Development Merchant Account";
				ajaxResponse($returnArray);
			}
			if (empty($merchantAccountId)) {
				$merchantAccountId = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "DEFAULT");
				if (empty($merchantAccountId)) {
					$returnArray['error_message'] = "No Merchant Account";
					ajaxResponse($returnArray);
				}
			}
			$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
			if (!$eCommerce) {
				$returnArray['error_message'] = "Unable to connect to Merchant Services account. Please contact customer service.";
				ajaxResponse($returnArray);
			}
			$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $GLOBALS['gUserRow']['contact_id'], "merchant_account_id = ?", $merchantAccountId);
		}

		$contactRow = $GLOBALS['gUserRow'];
		$contactArray = array("first_name" => $contactRow['first_name'],
			"last_name" => $contactRow['last_name'],
			"business_name" => $contactRow['business_name'],
			"address_1" => $contactRow['address_1'],
			"city" => $contactRow['city'],
			"state" => $contactRow['state'],
			"postal_code" => $contactRow['postal_code'],
			"country_id" => $contactRow['country_id'],
			"email_address" => $contactRow['email_address'],
			"contact_id" => $contactRow['contact_id']);
        $addressId = getFieldFromId("address_id", "addresses", "contact_id", $contactRow['contact_id']);
        if(!empty($addressId)) {
            $contactArray['address_id'] = $addressId;
        }

		if ($_POST['payment_type'] == "bank_account") {
            // Test accounts
            // TNBC: 490000018 / 24413815
			$paymentArray['bank_routing_number'] = "490000018"; //"071923284"; //"063100277"; //"102000076";
			$paymentArray['bank_account_number'] = "24413815"; //"4100"; //"1234567890"; //"5085025857";
			$paymentArray['bank_account_type'] = "checking";
		} else {
			$paymentArray['card_number'] = empty($_POST['card_number']) ? "6011000993026909" : $_POST['card_number']; // "6011008798004294";
			$paymentArray['expiration_date'] = empty($_POST['expiration_date']) ? "12/01/2020" : $_POST['expiration_date']; //"12/01/2020";
			$paymentArray['card_code'] = empty($_POST['card_code']) ? "996" : $_POST['card_code']; //"996";
		}
		switch ($_GET['url_action']) {
			case "test_connection":
				$success = $eCommerce->testConnection();
				$response = $eCommerce->getResponse();
				$returnArray['response'] = "Success: " . ($success ? "<span style=\"color:green;\">yes</span>" : "<span style=\"color:red;\">no</span>") . "<br/>" . $this->fixResponse($response);
				ajaxResponse($returnArray);
				break;
            case "thomson_reuters":
                if(file_exists(__DIR__ . '/local/class.thomsonreuters.php')) {
                    require_once __DIR__ . '/local/class.thomsonreuters.php';
                    $toAddress = array('postal_code' => '29456',
                        'state' => 'SC',
                        'city' => 'Ladson',
                        'address_1' => '402 Erskine street',
                        'country' => "US");
                    $client = new ThomsonReuters('airparkuat_avs', '6osp8GeTAb');
                    $toAddress = $client->validateAddress($toAddress);
                    $client = new ThomsonReuters('airparkholdings_ws', 'SIe3lafR');
                }
			case "taxjar":
                if(!isset($client)) {
                    $toAddress = array('postal_code' => '30324',
                        'state' => 'GA',
                        'city' => 'Atlanta',
                        'address_1' => '761 Miami Cir NE',
                        'country' => "US");
                    require_once __DIR__ . '/taxjar/vendor/autoload.php';
                    $client = TaxJar\Client::withApiKey("df9c16d1bf9329bf47f71856e5370602");
                    $client->setApiConfig('headers', ['x-api-version' => '2022-01-24']);
                }
                try {
                    $tax = $client->taxForOrder([
                        'from_country' => 'US',
                        'from_zip' => '29483',
                        'from_state' => 'SC',
                        'from_city' => 'Summerville',
                        'from_street' => '231 Deming Way',
                        'to_country' => $toAddress['country'],
                        'to_zip' => $toAddress['postal_code'],
                        'to_state' => $toAddress['state'],
                        'to_city' => $toAddress['city'],
                        'to_street' => $toAddress['address_1'],
                        'shipping' => 15.99,
                        'plugin' => 'coreware',
                        'line_items' => [
                            [
                                'id' => 2943,
                                'quantity' => 1,
                                'unit_price' => 384.22
                            ],
                            [
                                'id' => 2928,
                                'quantity' => 1,
                                'unit_price' => 48.33
                            ]
                        ]
                    ]);
                } catch (Exception $e) {
                    $tax = array('error_message'=>$e->getMessage());
                }
				$returnArray['response'] = jsonEncode($tax, JSON_PRETTY_PRINT);
				$returnArray['debug'] = true;
				ajaxResponse($returnArray);
				break;
			case "authorize_capture":
				$amount = $_POST['amount'] > 0 ? floatval($_POST['amount']) : 20.00;

				$productIds = array();
				$resultSet = executeQuery("select * from products where inactive = 0 order by rand() limit 2");
				while ($row = getNextRow($resultSet)) {
					$productIds[] = $row['product_id'];
				}
				$paymentArray = array_merge($contactArray, $paymentArray, array("amount" => $amount, "order_number" => intval(getRandomString(6, "123456789")),
                    "description" => "Product Order","order_items"=>array(array("product_id"=>$productIds[0],"description"=>"Test product 1","sale_price"=>10, "quantity"=>1),
                        array("product_id"=>$productIds[1],"description"=>"Test Product 2", "sale_price"=>5, "quantity"=>2))));
				$success = $eCommerce->authorizeCharge($paymentArray);
				$response = $eCommerce->getResponse();
				$returnArray['response'] = "Success: " . ($success ? "<span style=\"color:green;\">yes</span>" : "<span style=\"color:red;\">no</span>") . "<br>" . $this->fixResponse($response);
				if ($success) {
					$returnArray['transaction_identifier'] = $response['transaction_id'];
					$returnArray['authorization_code'] = $response['authorization_code'];
				} else {
					$returnArray['error_message'] = $eCommerce->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "void":
				$paymentArray = array("transaction_identifier" => $_POST['transaction_identifier']);
				$success = $eCommerce->voidCharge($paymentArray);
				$response = $eCommerce->getResponse();
				$returnArray['response'] = "Success: " . ($success ? "<span style=\"color:green;\">yes</span>" : "<span style=\"color:red;\">no</span>") . "<br>" . $this->fixResponse($response);
				if (!$success) {
					$returnArray['error_message'] = $eCommerce->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "refund":
				$amount = $_POST['amount'];
				if ($_POST['payment_type'] == "bank_account") {
					$accountToken = getFieldFromId("account_token", "accounts", "contact_id", $contactArray['contact_id'],
						"payment_method_id = (select payment_method_id from payment_methods where payment_method_code = 'ECHECK')");
					$paymentArray = array("amount" => $amount, "transaction_identifier" => $_POST['transaction_identifier'], 'account_token' => $accountToken);
				} else {
					$paymentArray = array("amount" => $amount, "transaction_identifier" => $_POST['transaction_identifier']);
				}
				$paymentArray['card_number'] = substr($_POST['card_number'],-4);
				$success = $eCommerce->refundCharge($paymentArray);
				$response = $eCommerce->getResponse();
				$returnArray['response'] = "Success: " . ($success ? "<span style=\"color:green;\">yes</span>" : "<span style=\"color:red;\">no</span>") . "<br>" . $this->fixResponse($response);
				if (!$success) {
					$returnArray['error_message'] = $eCommerce->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "authorize_only":
				$amount = $_POST['amount'] > 0 ? floatval($_POST['amount']) : 20.00;
				$paymentArray = array_merge($contactArray, $paymentArray, array("amount" => $amount, "order_number" => intval(getRandomString(6, "123456789")), "description" => "Product Order", "authorize_only" => true));
				$success = $eCommerce->authorizeCharge($paymentArray);
				$response = $eCommerce->getResponse();
				$returnArray['response'] = "Success: " . ($success ? "<span style=\"color:green;\">yes</span>" : "<span style=\"color:red;\">no</span>") . "<br>" . $this->fixResponse($response);
				if ($success) {
					$returnArray['transaction_identifier'] = $response['transaction_id'];
					$returnArray['authorization_code'] = $response['authorization_code'];
				} else {
					$returnArray['error_message'] = $eCommerce->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "capture":
				$paymentArray = array("transaction_identifier" => $_POST['transaction_identifier'], "authorization_code" => $_POST['authorization_code']);
				$success = $eCommerce->captureCharge($paymentArray);
				$response = $eCommerce->getResponse();
				$returnArray['response'] = "Success: " . ($success ? "<span style=\"color:green;\">yes</span>" : "<span style=\"color:red;\">no</span>") . "<br>" . $this->fixResponse($response);
				if ($success) {
					$returnArray['transaction_identifier'] = $response['transaction_id'];
					$returnArray['authorization_code'] = $response['authorization_code'];
				} else {
					$returnArray['error_message'] = $eCommerce->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "create_customer_profile":
				$paymentArray = $contactArray;
				$success = $eCommerce->createCustomerProfile($paymentArray);

				$response = $eCommerce->getResponse();

				$returnArray['response'] = "Success: " . ($success ? "<span style=\"color:green;\">yes</span>" : "<span style=\"color:red;\">no</span>") . "<br>" . $this->fixResponse($response);
				if ($success) {
					$returnArray['merchant_profile_identifier'] = $response['merchant_identifier'];
				} else {
					$returnArray['error_message'] = $eCommerce->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "get_customer_profile":
				$paymentArray = array_merge($contactArray, array("merchant_identifier" => $_POST['merchant_profile_identifier']));
				$success = $eCommerce->getCustomerProfile($paymentArray);

				$response = $eCommerce->getResponse();

				$returnArray['response'] = "Success: " . ($success ? "<span style=\"color:green;\">yes</span>" : "<span style=\"color:red;\">no</span>") . "<br>" . $this->fixResponse($response);
				if ($success) {
					$returnArray['merchant_profile_identifier'] = $response['merchant_identifier'];
				} else {
					$returnArray['error_message'] = $eCommerce->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "create_customer_payment_profile":
				$paymentArray = array_merge($contactArray, $paymentArray, array("merchant_identifier" => $merchantIdentifier));
				if ($_POST['payment_type'] == "bank_account") {
					$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name," .
						"account_number) values (?,?,?,?,?)", $GLOBALS['gUserRow']['contact_id'], "Saved Checking account", getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", "eCheck"),
						getUserDisplayName(), "XXXXXX-test");
					$paymentArray['account_id'] = $resultSet['insert_id'];
				} else {
					$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name," .
						"account_number,expiration_date) values (?,?,?,?,?,?)", $GLOBALS['gUserRow']['contact_id'], "Saved Card", getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", "DISCOVER"),
						getUserDisplayName(), "XXXXXXXXXXXX-test", "2022-03-01");
					$paymentArray['account_id'] = $resultSet['insert_id'];
				}
				$success = $eCommerce->createCustomerPaymentProfile($paymentArray);

				$response = $eCommerce->getResponse();

				$returnArray['response'] = "Success: " . ($success ? "<span style=\"color:green;\">yes</span>" : "<span style=\"color:red;\">no</span>") . "<br>" . jsonEncode($paymentArray) . "<br>" . $this->fixResponse($response) . "<br>" . $this->fixResponse($eCommerce->iOptions);
				if ($success) {
					$returnArray['merchant_profile_identifier'] = $response['merchant_identifier'];
					$returnArray['account_token'] = $response['account_token'];
				} else {
					$returnArray['response'] .= "<br>" . $eCommerce->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "get_customer_payment_profile":
				$paymentArray = array("merchant_identifier" => $_POST['merchant_profile_identifier'], "account_token" => $_POST['account_token']);
				$success = $eCommerce->getCustomerPaymentProfile($paymentArray);

				$response = $eCommerce->getResponse();

				$returnArray['response'] = "Success: " . ($success ? "<span style=\"color:green;\">yes</span>" : "<span style=\"color:red;\">no</span>") . "<br>" . $this->fixResponse($response) . "<br>" . $this->fixResponse($eCommerce->iOptions);
				if (!$success) {
					$returnArray['error_message'] = $eCommerce->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "update_customer_payment_profile":
				$paymentArray = array_merge($contactArray, $paymentArray, array("merchant_identifier" => $merchantIdentifier));
//                if ($_POST['payment_type'] == "bank_account") {
//                    $paymentArray['bank_routing_number'] = "071923284";//"063100277"; //"102000076";
//                    $paymentArray['bank_account_number'] = "4100";//"1234567890"; //"5085025857";
//                    $paymentArray['bank_account_type'] = "checking";
//                } else {
//                    $paymentArray['card_number'] = "6011008798004294";
//                    $paymentArray['expiration_date'] = "03/01/2025";
//                    $paymentArray['card_code'] = "248";
//                }
				if (empty($_POST['account_token'])) {
					$returnArray['error_message'] = "Missing account token";
				} else {
					$paymentArray['account_token'] = $_POST['account_token'];
				}
				executeQuery("update accounts set account_label = ?, account_number = ?, expiration_date = ?" .
					" where account_token = ? and contact_id = ?", "Saved Card", maskString($paymentArray['card_number'], "XXXXXXXXXXXX-####"),
					date_format(date_create($paymentArray['expiration_date']), 'Y-m-d'), $paymentArray['account_token'], $GLOBALS['gUserRow']['contact_id']);
				$success = $eCommerce->updateCustomerPaymentProfile($paymentArray);

				$response = $eCommerce->getResponse();

				$returnArray['response'] = "Success: " . ($success ? "<span style=\"color:green;\">yes</span>" : "<span style=\"color:red;\">no</span>") . "<br>" . jsonEncode($paymentArray) . "<br>" . $this->fixResponse($response) . "<br>" . $this->fixResponse($eCommerce->iOptions);
				if ($success) {
					$returnArray['merchant_profile_identifier'] = $response['merchant_identifier'];
					$returnArray['account_token'] = $response['account_token'];
				} else {
					$returnArray['response'] .= "<br>" . $eCommerce->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "delete_customer_payment_profile":
				$paymentArray = array("merchant_identifier" => $_POST['merchant_profile_identifier'], "account_token" => $_POST['account_token']);
				$success = $eCommerce->deleteCustomerPaymentProfile($paymentArray);

				$response = $eCommerce->getResponse();

				$returnArray['response'] = "Success: " . ($success ? "<span style=\"color:green;\">yes</span>" : "<span style=\"color:red;\">no</span>") . "<br>" . $this->fixResponse($response) . "<br>" . $this->fixResponse($eCommerce->iOptions);
				if (!$success) {
					$returnArray['error_message'] = $eCommerce->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "delete_customer_profile":
				$paymentArray = array("merchant_identifier" => $_POST['merchant_profile_identifier'], "account_token" => $_POST['account_token']);
				$success = $eCommerce->deleteCustomerProfile($paymentArray);

				$response = $eCommerce->getResponse();

				$returnArray['response'] = "Success: " . ($success ? "<span style=\"color:green;\">yes</span>" : "<span style=\"color:red;\">no</span>") . "<br>" . $this->fixResponse($response) . "<br>" . $this->fixResponse($eCommerce->iOptions);
				if (!$success) {
					$returnArray['error_message'] = $eCommerce->getErrorMessage();
				}
				ajaxResponse($returnArray);
				break;
			case "create_customer_profile_transaction_request_authorize":
				$authorizeOnly = true;
			case "create_customer_profile_transaction_request_capture":
                $paymentArray = array_merge($contactArray, $paymentArray, array("amount" => $_POST['amount'], "order_number" => intval(getRandomString(6, "123456789")),
                    "merchant_identifier" => $_POST['merchant_profile_identifier'], "account_token" => $_POST['account_token'], "authorize_only" => $authorizeOnly,
                    "description" => "Product Order","order_items"=>array(array("product_id"=>1,"description"=>"Test product 1","sale_price"=>10, "quantity"=>1),
                        array("product_id"=>2,"description"=>"Test Product 2", "sale_price"=>5, "quantity"=>2))));
				$success = $eCommerce->createCustomerProfileTransactionRequest($paymentArray);

                $response = $eCommerce->getResponse();

				$returnArray['response'] = "Success: " . ($success ? "<span style=\"color:green;\">yes</span>" : "<span style=\"color:red;\">no</span>") . "<br>" . $this->fixResponse($response) . "<br>" . $this->fixResponse($eCommerce->iOptions);
				if (!$success) {
					$returnArray['error_message'] = $eCommerce->getErrorMessage();
				} else {
					$returnArray['transaction_identifier'] = $response['transaction_id'];
					$returnArray['authorization_code'] = $response['authorization_code'];
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function fixResponse($response) {
        if(!is_array($response['raw_response'])) {
            $response['raw_response'] = json_decode($response['raw_response']);
        }
		return str_replace("\u003E", " ] ", str_replace("\u003C", " [ ", str_replace("\u0022", '"', str_replace("\/", "/", str_replace(",", ", ", json_encode($response, JSON_PRETTY_PRINT))))));
	}

	function mainContent() {
		?>
        <p class='error-message' id="error_message"></p>
        <table style="width:100%">
            <tr>
                <td style="vertical-align: top">
                    <form id="_edit_form">

                        <div class='form-line'>
                            <label>Merchant Account</label>
                            <select id="merchant_account_id" name="merchant_account_id">
								<?php
								$merchantResults = executeQuery("select * from merchant_accounts where client_id = ? and inactive = 0", $GLOBALS['gClientId']);
								while ($merchantRow = getNextRow($merchantResults)) {
									$selected = $merchantRow['merchant_account_code'] == 'DEVELOPMENT' ? "selected" : "";
									echo '<option ' . $selected . ' value="' . $merchantRow['merchant_account_id'] . '">' . $merchantRow['description'] . "</option>";
								}
								?>
                            </select>
                        </div>

                        <div class='form-line'>
                            <button class="test-button" id="test_connection">Test Connection</button>
                        </div>

                        <div class='form-line'>
                            <label>Amount</label>
                            <input type='text' id="amount" name="amount" value="20">
                        </div>

                        <div class='form-line'>
                            <label>Payment Type</label>
                            <input type="radio" id="payment_type_credit_card" checked name="payment_type" value='credit_card'><label class='checkbox-label' for='payment_type_credit_card'>Credit Card</label><br>
                            <input type="radio" id="payment_type_bank_account" name="payment_type" value='bank_account'><label class='checkbox-label' for='payment_type_bank_account'>Bank Account</label>
                        </div>

                        <div class='form-line'>
                            <label>Test card number</label>
                            <input type='text' id="card_number" name="card_number" value="6011000993026909">
                        </div>
                        <div class='form-line'>
                            <label>Test expiration date</label>
                            <input type='text' id="expiration_date" name="expiration_date" value="12/1/<?= date("Y", strtotime("+1 year")) ?>">
                        </div>
                        <div class='form-line'>
                            <label>Test CSC</label>
                            <input type='text' id="card_code" name="card_code" value="996">
                        </div>


                        <div class='form-line'>
                            <button class="test-button" id="authorize_capture">Authorize & Capture</button>
                        </div>

                        <div class='form-line'>
                            <label>Transaction ID</label>
                            <input type='text' id="transaction_identifier" name="transaction_identifier" value="">
                        </div>

                        <div class='form-line'>
                            <label>Authorization Code</label>
                            <input type='text' id="authorization_code" name="authorization_code" value="" readonly="readonly">
                        </div>

                        <div class='form-line'>
                            <button class="test-button" id="void">Void</button>
                        </div>

                        <div class='form-line'>
                            <button class="test-button" id="refund">Refund</button>
                        </div>

                        <div class='form-line'>
                            <button class="test-button" id="authorize_only">Authorize Only</button>
                        </div>

                        <div class='form-line'>
                            <button class="test-button" id="capture">Capture</button>
                        </div>

                        <div class='form-line'>
                            <button class="test-button" id="create_customer_profile">Create Customer Profile</button>
                        </div>

                        <div class='form-line'>
                            <label>Merchant Profile ID</label>
                            <input type='text' id="merchant_profile_identifier" name="merchant_profile_identifier" value="">
                        </div>

                        <div class='form-line'>
                            <button class="test-button" id="get_customer_profile">Get Customer Profile</button>
                        </div>

                        <div class='form-line'>
                            <button class="test-button" id="create_customer_payment_profile">Create Customer Payment Profile</button>
                        </div>

                        <div class='form-line'>
                            <label>Account Token</label>
                            <input type='text' id="account_token" name="account_token" value="">
                        </div>

                        <div class='form-line'>
                            <button class="test-button" id="get_customer_payment_profile">Get Customer Payment Profile</button>
                        </div>

                        <div class='form-line'>
                            <button class="test-button" id="update_customer_payment_profile">Update Customer Payment Profile</button>
                        </div>

                        <div class='form-line'>
                            <button class="test-button" id="create_customer_profile_transaction_request_capture">Create Customer Transaction w/Capture</button>
                        </div>

                        <div class='form-line'>
                            <button class="test-button" id="create_customer_profile_transaction_request_authorize">Create Customer Transaction Authorize Only</button>
                        </div>

                        <div class='form-line'>
                            <button class="test-button" id="delete_customer_payment_profile">Delete Customer Payment Profile</button>
                        </div>

                        <div class='form-line'>
                            <button class="test-button" id="delete_customer_profile">Delete Customer Profile</button>
                        </div>

                        <div class='form-line'>
                            <button class="test-button" id="taxjar">TaxJar</button>
                        </div>

                        <?php if(file_exists(__DIR__ . '/local/class.thomsonreuters.php')) { ?>
                        <div class='form-line'>
                            <button class="test-button" id="thomson_reuters">Thomson-Reuters</button>
                        </div>
                        <?php } ?>
                    </form>
                </td>
                <td style="vertical-align: top">
                    <div id="response" style="white-space:pre;"></div>
                </td>
            </tr>
        </table>
		<?php
		# authorize & capture Charge
		# void Charge
		# refund Charge
		# authorize only charge
		# capture only charge
		# create customer profile
		# get Customer Profile
		# create customer payment profile
		# get customer payment profile
		# update customer payment profile
		# create customer profile transaction request (authorize and capture)
		# create customer profile transaction request (authorize only)
		# delete customer payment profile
		# delete customer profile

		return true;
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("click", ".test-button", function () {
                $("#response").html("");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=" + $(this).attr("id"), $("#_edit_form").serialize(), function(returnArray) {
                    for (const i in returnArray) {
                        if ($("#" + i).length > 0) {
                            if ($("#" + i).is("input")) {
                                $("#" + i).val(returnArray[i]);
                            } else {
                                $("#" + i).html(returnArray[i]);
                            }
                        }
                    }
                });
                return false;
            });
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            #response {
                margin-bottom: 40px;
            }
        </style>
		<?php
	}
}

$pageObject = new TestEcommercePage();
$pageObject->displayPage();
