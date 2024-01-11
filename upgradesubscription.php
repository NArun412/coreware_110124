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

$GLOBALS['gPageCode'] = "UPGRADESUBSCRIPTION";
require_once "shared/startup.inc";

class UpgradeSubscriptionPage extends Page {

	var $iContactIds = array();

	function setup() {
		if (function_exists("getContactIdList")) {
			$this->iContactIds = getContactIdList();
		}
		if (empty($this->iContactIds) && !empty($_GET['contact_id']) && $GLOBALS['gUserRow']['administrator_flag']) {
			$this->iContactIds = array($_GET['contact_id']);
		}
		if (empty($this->iContactIds)) {
			$this->iContactIds = array($GLOBALS['gUserRow']['contact_id']);
		}
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "purchase_upgrade":
				$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id']);
				if (empty($contactId) || !in_array($contactId, $this->iContactIds)) {
					$returnArray['error_message'] = "Unable to find subscription to upgrade.";
					ajaxResponse($returnArray);
					break;
				}
				$productId = getFieldFromId("product_id", "products", "product_id", $_POST['product_id'],
					"product_type_id is not null and product_type_id in (select product_type_id from product_types where product_type_code = 'UPGRADE_SUBSCRIPTION')");
				if (empty($productId)) {
					$returnArray['error_message'] = "Invalid upgrade product";
					ajaxResponse($returnArray);
					break;
				}
				$productCatalog = new ProductCatalog();
				$productRow = ProductCatalog::getCachedProductRow($productId);
				$fromSubscriptionCode = CustomField::getCustomFieldData($productRow['product_id'], "FROM_SUBSCRIPTION_CODE", "PRODUCTS");
				$fromSubscriptionId = getFieldFromId("subscription_id", "subscriptions", "subscription_code", $fromSubscriptionCode, "inactive = 0 and internal_use_only = 0");
				if (empty($fromSubscriptionId)) {
					$returnArray['error_message'] = "Unable to find a subscription to upgrade.";
					ajaxResponse($returnArray);
					break;
				}
				$contactSubscriptionId = getFieldFromId("contact_subscription_id", "contact_subscriptions", "subscription_id", $fromSubscriptionId,
					"contact_id = ? and inactive = 0 and customer_paused = 0 and start_date <= current_date and (expiration_date is null or expiration_date > current_date)", $contactId);
				if (empty($contactSubscriptionId)) {
					$returnArray['error_message'] = "Unable to find a subscription to upgrade.";
					ajaxResponse($returnArray);
					break;
				}
				$productRow['from_subscription_id'] = $fromSubscriptionId;
				$toSubscriptionCode = CustomField::getCustomFieldData($productRow['product_id'], "TO_SUBSCRIPTION_CODE", "PRODUCTS");
				$toSubscriptionId = getFieldFromId("subscription_id", "subscriptions", "subscription_code", $toSubscriptionCode, "inactive = 0 and internal_use_only = 0");
				if (empty($toSubscriptionId)) {
					$returnArray['error_message'] = "Unable to upgrade subscription. Please contact customer support.";
					ajaxResponse($returnArray);
					break;
				}
				$productRow['to_subscription_id'] = $toSubscriptionId;
				$salePriceInfo = $productCatalog->getProductSalePrice($productRow['product_id'], array("product_information" => $productRow));
				$productRow['sale_price'] = $salePriceInfo['sale_price'];
				if ($productRow['sale_price'] === false || strlen($productRow['sale_price']) == 0) {
					$returnArray['error_message'] = "Unable to upgrade subscription. Please contact customer support.";
					ajaxResponse($returnArray);
					break;
				}
				$recurringPaymentRow = getRowFromId("recurring_payments", "contact_subscription_id", $contactSubscriptionId);
				if (!empty($recurringPaymentRow)) {
					$subscriptionProductId = getFieldFromId("subscription_product_id", "subscription_products", "subscription_id", $toSubscriptionId,
						"recurring_payment_type_id = ?", $recurringPaymentRow['recurring_payment_type_id']);
					if (empty($subscriptionProductId)) {
						$returnArray['error_message'] = "Unable to upgrade subscription. Please contact customer support.";
						ajaxResponse($returnArray);
						break;
					}
				}

				$GLOBALS['gPrimaryDatabase']->startTransaction();

				if (!empty($productRow['sale_price'])) {
					$orderObject = new Order();
					$totalAmount = $productRow['sale_price'];
					$orderObject->setCustomerContact($contactId);
					$orderObject->addOrderItem(array("product_id" => $productRow['product_id'], "quantity" => 1, "sale_price" => $productRow['sale_price']));

					$taxCharge = $orderObject->getTax();
					if (empty($taxCharge)) {
						$taxCharge = 0;
					}
					$totalAmount += $taxCharge;
					$orderObject->setOrderField("tax_charge", $taxCharge);
					if (!$orderObject->generateOrder()) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = "Unable to create upgrade order";
						ajaxResponse($returnArray);
						break;
					}
					$orderId = $orderObject->getOrderId();
					if (empty($orderId)) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = "Unable to create upgrade order";
						ajaxResponse($returnArray);
						break;
					}

					if (!empty($totalAmount)) {
						$_POST['payment_method_type_code'] = getFieldFromId("payment_method_type_code", "payment_method_types",
							"payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id",
								$_POST['payment_method_id']));
						$isBankAccount = ($_POST['payment_method_type_code'] == "BANK_ACCOUNT");

						$merchantAccountId = $GLOBALS['gMerchantAccountId'];
						$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
						if (!$eCommerce) {
							$this->iDatabase->rollbackTransaction();
							$returnArray['error_message'] = "Unable to connect to Merchant Services. Please contact customer service. #637";
							ajaxResponse($returnArray);
							break;
						}

						# If the user is logged in, get or create a customer profile

						$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $contactId, "merchant_account_id = ?", $merchantAccountId);
						if (empty($merchantIdentifier) && $eCommerce->hasCustomerDatabase()) {
							$success = $eCommerce->createCustomerProfile(array("contact_id" => $contactId, "first_name" => $_POST['billing_first_name'],
								"last_name" => $_POST['last_name'], "address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'],
								"state" => $_POST['billing_state'], "postal_code" => $_POST['billing_postal_code'], "email_address" => $_POST['email_address']));
							$response = $eCommerce->getResponse();
							if ($success) {
								$merchantIdentifier = $response['merchant_identifier'];
							}
						}

						if (empty($merchantIdentifier) && !empty($_POST['account_id'])) {
							$returnArray['error_message'] = "There is a problem using an existing payment method. Please create a new one. #128";
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}

						$accountToken = false;
						$customerPaymentProfileId = false;
						if (empty($_POST['account_id'])) {
							$accountLabel = $_POST['account_label'];
							if (empty($accountLabel)) {
								$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']) . " - " . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4);
							}

							$accountAddressId = "";
							if (!$GLOBALS['gLoggedIn'] || ($_POST['billing_address_1'] != $GLOBALS['gUserRow']['address_1'] || $_POST['billing_city'] != $GLOBALS['gUserRow']['city'] ||
									$_POST['postal_code'] != $GLOBALS['gUserRow']['postal_code'])) {
								if (empty($_POST['billing_country_id'])) {
									$_POST['billing_country_id'] = "1000";
								}
								$accountAddressId = getFieldFromId("address_id", "addresses", "contact_id", $contactId, "address_1 <=> ? and address_2 <=> ? and city <=> ? and state <=> ? and postal_code <=> ? and country_id = ?",
									$_POST['billing_address_1'], $_POST['billing_address_2'], $_POST['billing_city'], $_POST['billing_state'], $_POST['billing_postal_code'], $_POST['billing_country_id']);
								if (empty($accountAddressId)) {
									$insertSet = executeQuery("insert into addresses (contact_id,address_label,address_1,address_2,city,state,postal_code,country_id) values (?,?,?,?,?, ?,?,?)",
										$contactId, "Billing Address", $_POST['billing_address_1'], $_POST['billing_address_2'], $_POST['billing_city'],
										$_POST['billing_state'], $_POST['billing_postal_code'], $_POST['billing_country_id']);
									$accountAddressId = $insertSet['insert_id'];
								}
							}

							$fullName = $_POST['billing_first_name'] . " " . $_POST['billing_last_name'] . (empty($_POST['billing_business_name']) ? "" : ", " . $_POST['billing_business_name']);
							$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name,address_id," .
								"account_number,expiration_date,merchant_account_id,inactive) values (?,?,?,?,?, ?,?,?,?)", $contactId, $accountLabel, $_POST['payment_method_id'],
								$fullName, $accountAddressId, "XXXX-" . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4),
								(empty($_POST['expiration_year']) ? "" : date("Y-m-d", strtotime($_POST['expiration_year'] . "-" . $_POST['expiration_month'] . "-01"))), $merchantAccountId, ($_POST['save_account'] ? 0 : 1));
							if (!empty($resultSet['sql_error'])) {
								$this->iDatabase->rollbackTransaction();
								$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
								ajaxResponse($returnArray);
								break;
							}
							$accountId = $resultSet['insert_id'];

							$paymentArray = array("contact_id" => $contactId, "account_id" => $accountId, "merchant_identifier" => $merchantIdentifier,
								"first_name" => (empty($_POST['billing_first_name']) ? $_POST['first_name'] : $_POST['billing_first_name']),
								"last_name" => (empty($_POST['billing_last_name']) ? $_POST['last_name'] : $_POST['billing_last_name']),
								"business_name" => (empty($_POST['billing_business_name']) ? $_POST['business_name'] : $_POST['billing_business_name']),
								"address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
								"postal_code" => (empty($_POST['billing_postal_code']) ? $_POST['postal_code'] : $_POST['billing_postal_code']),
								"country_id" => (empty($_POST['billing_country_id']) ? $_POST['country_id'] : $_POST['billing_country_id']));
							if ($isBankAccount) {
								$paymentArray['bank_routing_number'] = $_POST['routing_number'];
								$paymentArray['bank_account_number'] = $_POST['bank_account_number'];
								$paymentArray['bank_account_type'] = str_replace(" ", "", lcfirst(ucwords(strtolower(str_replace("_", " ", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id']))))));
							} else {
								$paymentArray['card_number'] = $_POST['account_number'];
								$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
								$paymentArray['card_code'] = $_POST['cvv_code'];
							}
							$success = $eCommerce->createCustomerPaymentProfile($paymentArray);
							$response = $eCommerce->getResponse();
							if ($success) {
								$customerPaymentProfileId = $accountToken = $response['account_token'];
							}
						} else {
							$accountId = getFieldFromId("account_id", "accounts", "account_id", $_POST['account_id'], "contact_id = ?", $contactId);
							$accountToken = getFieldFromId("account_token", "accounts", "account_id", $accountId, "contact_id = ?", $contactId);
							$_POST['payment_method_id'] = getFieldFromId("payment_method_id", "accounts", "account_id", $accountId);
						}

						if (empty($accountToken)) {
							$returnArray['error_message'] = "There is a problem creating a payment. Please contact customer service.";
							$this->iDatabase->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						$accountMerchantIdentifier = getFieldFromId("merchant_identifier", "accounts", "account_id", $accountId);
						if (empty($accountMerchantIdentifier)) {
							$accountMerchantIdentifier = $merchantIdentifier;
						}

						$addressId = getFieldFromId("address_id", "accounts", "account_id", $accountId);
						$success = $eCommerce->createCustomerProfileTransactionRequest(array("amount" => $totalAmount, "order_number" => $orderId, "address_id" => $addressId,
							"merchant_identifier" => $accountMerchantIdentifier, "account_token" => $accountToken));
						$response = $eCommerce->getResponse();
						if ($success) {
							$orderObject->createOrderPayment($totalAmount, array("payment_method_id" => $_POST['payment_method_id'], "account_id" => $accountId,
								"authorization_code" => $response['authorization_code'], "transaction_identifier" => $response['transaction_id']));
						} else {
							if (!empty($customerPaymentProfileId)) {
								$eCommerce->deleteCustomerPaymentProfile(array("merchant_identifier" => $accountMerchantIdentifier, "account_token" => $customerPaymentProfileId));
							}
							$this->iDatabase->rollbackTransaction();
							$returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
							$eCommerce->writeLog($accountToken, $response['response_reason_text'], true);
							ajaxResponse($returnArray);
							break;
						}
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				Order::processOrderItems($orderId);
				Order::processOrderAutomation($orderId);
				coreSTORE::orderNotification($orderId, "order_created");
				Order::notifyCRM($orderId);

				$substitutions = array("from_subscription" => getFieldFromId("description", "subscriptions", "subscription_id", $fromSubscriptionId), "order_id" => $orderId,
					"to_subscription" => getFieldFromId("description", "subscriptions", "subscription_id", $toSubscriptionId), "sale_price" => $productRow['sale_price']);
				$response = CustomField::getCustomFieldData($productId, "SUBSCRIPTION_UPGRADE_RESPONSE", "PRODUCTS");
				if (empty($response)) {
					$response = $this->getPageTextChunk("SUBSCRIPTION_UPGRADE_RESPONSE");
				}
				if (empty($response)) {
					$response = $this->getFragment("SUBSCRIPTION_UPGRADE_RESPONSE");
				}
				if (empty($response)) {
					$response = "<p>Your subscription has been successfully upgraded from %from_subscription% to %to_subscription%. You have been charged $%sale_price% on order ID %order_id%.</p>";
				}
				$returnArray['response'] = PlaceHolders::massageContent($response, $substitutions);
				ajaxResponse($returnArray);
				break;
		}
	}

	function mainContent() {
		$productArray = array();
		$productCatalog = new ProductCatalog();
		$resultSet = executeQuery("select * from products left outer join product_data using (product_id) where inactive = 0 and product_type_id in (select product_type_id from product_types where product_type_code = 'UPGRADE_SUBSCRIPTION') and products.client_id = ?", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$fromSubscriptionCode = CustomField::getCustomFieldData($row['product_id'], "FROM_SUBSCRIPTION_CODE", "PRODUCTS");
			$fromSubscriptionId = getFieldFromId("subscription_id", "subscriptions", "subscription_code", $fromSubscriptionCode, "inactive = 0 and internal_use_only = 0");
			if (empty($fromSubscriptionId)) {
				continue;
			}
			$row['contact_ids'] = array();
			$contactSubscriptionIds = array();
			foreach ($this->iContactIds as $contactId) {
				$contactSubscriptionId = getFieldFromId("contact_subscription_id", "contact_subscriptions", "subscription_id", $fromSubscriptionId,
					"contact_id = ? and inactive = 0 and customer_paused = 0 and start_date <= current_date and (expiration_date is null or expiration_date > current_date)", $contactId);
				if (!empty($contactSubscriptionId)) {
					$contactSubscriptionIds[] = $contactSubscriptionId;
					$row['contact_ids'][] = $contactId;
				}
			}
			if (empty($contactSubscriptionIds)) {
				continue;
			}
			$row['from_subscription_id'] = $fromSubscriptionId;
			$toSubscriptionCode = CustomField::getCustomFieldData($row['product_id'], "TO_SUBSCRIPTION_CODE", "PRODUCTS");
			$toSubscriptionId = getFieldFromId("subscription_id", "subscriptions", "subscription_code", $toSubscriptionCode, "inactive = 0 and internal_use_only = 0");
			if (empty($toSubscriptionId)) {
				continue;
			}
            $countSet = executeQuery("select count(distinct product_id) from subscription_products where subscription_id = ?",$toSubscriptionId);
            if ($countRow = getNextRow($countSet)) {
                if ($countRow['count(distinct product_id)'] > 1) {
	                $newRenewalProductCode = CustomField::getCustomFieldData($row['product_id'], "NEW_RENEWAL_PRODUCT_CODE", "PRODUCTS");
                    $newRenewalProductId = getFieldFromId("product_id","products","product_code",$newRenewalProductCode,"inactive = 0");
                    if (empty($newRenewalProductId)) {
                        continue;
                    }
                }
            }
			$row['to_subscription_id'] = $toSubscriptionId;
			$salePriceInfo = $productCatalog->getProductSalePrice($row['product_id'], array("product_information" => $row));
			$row['sale_price'] = $salePriceInfo['sale_price'];
			if ($row['sale_price'] === false || strlen($row['sale_price']) == 0) {
				continue;
			}
			foreach ($contactSubscriptionIds as $contactSubscriptionId) {
				$recurringPaymentRow = getRowFromId("recurring_payments", "contact_subscription_id", $contactSubscriptionId);
				if (!empty($recurringPaymentRow)) {
					$subscriptionProductId = getFieldFromId("product_id", "subscription_products", "subscription_id", $toSubscriptionId,
						"recurring_payment_type_id = ?", $recurringPaymentRow['recurring_payment_type_id']);
					if (empty($subscriptionProductId)) {
						continue 2;
					}
				}
			}
			$productArray[] = $row;
		}
		if (empty($productArray)) {
			?>
            <p class='highlighted-text'>No upgrade products available.</p>
			<?php
			return true;
		}
		$merchantAccountId = $GLOBALS['gMerchantAccountId'];
		$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
		?>
        <div id="upgrade_wrapper">
            <form id="_edit_form">
				<?php
				if (count($this->iContactIds) == 1) {
					?>
                    <input type='hidden' id='contact_id' name='contact_id' value='<?= $this->iContactIds[0] ?>'>
					<?php
					if ($this->iContactIds[0] != $GLOBALS['gUserRow']['contact_id'] && !empty($GLOBALS['gUserRow']['administrator_flag'])) {
						?>
                        <h3>This upgrade is for <?= getDisplayName($this->iContactIds[0]) ?></h3>
						<?php
					}
				} else {
					?>
                    <div class='form-line' id="_contact_id_row">
                        <label>This upgrade is for</label>
                        <select id='contact_id' name='contact_id' class='validate[required]'>
                            <option value=''>[Select]</option>
							<?php
							foreach ($this->iContactIds as $contactId) {
								?>
                                <option value='<?= $contactId ?>'><?= htmlText(getDisplayName($contactId)) ?></option>
								<?php
							}
							?>
                        </select>
                    </div>
					<?php
				}
				?>
                <h2 class="needs-upgrade-product <?= count($this->iContactIds) == 1 && !$GLOBALS['gUserRow']['administrator_flag'] ? "" : "hidden" ?>">Choose your upgrade</h2>
                <p id="no_upgrade_products_message" class="hidden highlighted-text">No upgrade products available.</p>
				<?php
				foreach ($productArray as $productRow) {
					?>
                    <div class="upgrade-product <?= count($this->iContactIds) == 1 ? "" : "hidden" ?>" data-contact_ids="<?= implode(",", $productRow['contact_ids']) ?>">
                        <input type='radio' class='validate[required]' data-sale_price='<?= $productRow['sale_price'] ?>' name='product_id' id='product_id_<?= $productRow['product_id'] ?>' value='<?= $productRow['product_id'] ?>'>
                        <label class='checkbox-label' for='product_id_<?= $productRow['product_id'] ?>'><?= htmlText($productRow['description'] . " (" . number_format($productRow['sale_price'], 2) . ")") ?></label>
                    </div>
					<?php
				}
				?>

				<?php if ($GLOBALS['gUserRow']['administrator_flag'] && count($this->iContactIds) == 1 && $this->iContactIds[0] != $GLOBALS['gUserRow']['contact_id']) { ?>
                    <div class='form-line'>
                        <label>Amount to be billed</label>
                        <input tabindex="10" data-decimal-places="2" class="align-right validate[custom[number]]" type="text" value="" size="12" name="billing_amount" id="billing_amount">
                    </div>
				<?php } else { ?>
                    <h3 id='billing_amount_wrapper' class='hidden'>Amount to be billed: <span id='billing_amount'></span></h3>
				<?php } ?>

                <div id="payment_wrapper">
                    <div id="_billing_info_section" class="hidden">
                        <h2>Billing Information</h2>

						<?php
						if (count($this->iContactIds) == 1 && $this->iContactIds[0] == $GLOBALS['gUserRow']['contact_id']) {
							$resultSet = executeQuery("select * from accounts where contact_id = ? and inactive = 0 and account_token is not null", $GLOBALS['gUserRow']['contact_id']);
							if ($resultSet['row_count'] == 0 || empty($eCommerce) || !$eCommerce->hasCustomerDatabase()) {
								?>
                                <input type="hidden" id="account_id" name="account_id" value="">
								<?php
							} else {
								?>
                                <div class="form-line" id="_account_id_row">
                                    <label for="account_id" class="">Select Account</label>
                                    <select tabindex="10" id="account_id" name="account_id">
                                        <option value="">[New Account]</option>
										<?php
										while ($row = getNextRow($resultSet)) {
											$merchantAccountId = eCommerce::getAccountMerchantAccount($row['account_id']);
											?>
                                            <option data-merchant_account_id="<?= $merchantAccountId ?>" value="<?= $row['account_id'] ?>"><?= htmlText((empty($row['account_label']) ? $row['account_number'] : $row['account_label'])) ?></option>
											<?php
										}
										?>
                                    </select>
                                    <div class='clear-div'></div>
                                </div>
								<?php
							}
						} else {
							?>
                            <input type="hidden" id="account_id" name="account_id" value="">
							<?php
						}
						?>

                        <div id="_new_account">

                            <div id="_billing_address">

                                <div class="form-line" id="_billing_first_name_row">
                                    <label for="billing_first_name" class="required-label">First Name</label>
                                    <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="25" maxlength="25" id="billing_first_name" name="billing_first_name" placeholder="First Name" value="<?= htmlText($GLOBALS['gUserRow']['first_name']) ?>">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_billing_last_name_row">
                                    <label for="billing_last_name" class="required-label">Last Name</label>
                                    <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="35" id="billing_last_name" name="billing_last_name" placeholder="Last Name" value="<?= htmlText($GLOBALS['gUserRow']['last_name']) ?>">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_billing_business_name_row">
                                    <label for="billing_business_name">Business Name</label>
                                    <input tabindex="10" type="text" class="" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="35" id="billing_business_name" name="billing_business_name" placeholder="Business Name" value="<?= htmlText($GLOBALS['gUserRow']['business_name']) ?>">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_billing_address_1_row">
                                    <label for="billing_address_1" class="required-label">Street</label>
                                    <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="60" id="billing_address_1" name="billing_address_1" placeholder="Address" value="<?= htmlText($GLOBALS['gUserRow']['address_1']) ?>">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_billing_address_2_row">
                                    <label for="billing_address_2" class=""></label>
                                    <input tabindex="10" type="text" class="" size="30" maxlength="60" id="billing_address_2" name="billing_address_2" value="<?= htmlText($GLOBALS['gUserRow']['address_2']) ?>">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_billing_city_row">
                                    <label for="billing_city" class="required-label">City</label>
                                    <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="60" id="billing_city" name="billing_city" placeholder="City" value="<?= htmlText($GLOBALS['gUserRow']['city']) ?>">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_billing_state_row">
                                    <label for="billing_state" class="">State</label>
                                    <input tabindex="10" type="text" class="validate[required]" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000" size="10" maxlength="30" id="billing_state" name="billing_state" placeholder="State" value="<?= htmlText($GLOBALS['gUserRow']['state']) ?>">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_billing_state_select_row">
                                    <label for="billing_state_select" class="">State</label>
                                    <select tabindex="10" id="billing_state_select" name="billing_state_select" class="validate[required]" data-conditional-required="$('#billing_country_id').val() == 1000">
                                        <option value="">[Select]</option>
										<?php
										foreach (getStateArray() as $stateCode => $state) {
											?>
                                            <option <?= ($stateCode == $GLOBALS['gUserRow']['state'] ? "selected" : "") ?> value="<?= $stateCode ?>"><?= htmlText($state) ?></option>
											<?php
										}
										?>
                                    </select>
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_billing_postal_code_row">
                                    <label for="billing_postal_code" class="">Postal Code</label>
                                    <input tabindex="10" type="text" class="validate[required]" size="10" maxlength="10" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000" id="billing_postal_code" name="billing_postal_code" placeholder="Postal Code" value="<?= htmlText($GLOBALS['gUserRow']['postal_code']) ?>">
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_billing_country_id_row">
                                    <label for="billing_country_id" class="">Country</label>
                                    <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="billing_country_id" name="billing_country_id">
										<?php
										foreach (getCountryArray(true) as $countryId => $countryName) {
											?>
                                            <option <?= ($countryId == $GLOBALS['gUserRow']['country_id'] ? "selected" : "") ?> value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
											<?php
										}
										?>
                                    </select>
                                    <div class='clear-div'></div>
                                </div>
                            </div> <!-- billing_address -->

                            <div id="payment_information">
                                <div class="form-line" id="_payment_method_id_row">
                                    <label for="payment_method_id" class="">Payment Method</label>
                                    <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="payment_method_id" name="payment_method_id">
                                        <option value="">[Select]</option>
										<?php
										$paymentLogos = array();
										$resultSet = executeQuery("select *,(select payment_method_types.payment_method_type_code from payment_method_types where " .
											"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where inactive = 0 and internal_use_only = 0 " .
											"and client_id = ? and payment_method_type_id in (select payment_method_type_id from payment_method_types where inactive = 0 and internal_use_only = 0 and " .
											"client_id = ? and payment_method_type_code in ('CREDIT_CARD','BANK_ACCOUNT')) order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
										while ($row = getNextRow($resultSet)) {
											if (empty($row['image_id'])) {
												$row['image_id'] = getFieldFromId("image_id", "payment_methods", "payment_method_code", $row['payment_method_code'], "client_id = ?", $GLOBALS['gDefaultClientId']);
											}
											if (!empty($row['image_id'])) {
												$paymentLogos[$row['payment_method_id']] = $row['image_id'];
											}
											?>
                                            <option value="<?= $row['payment_method_id'] ?>" data-payment_method_type_code="<?= strtolower($row['payment_method_type_code']) ?>"><?= htmlText($row['description']) ?></option>
											<?php
										}
										?>
                                    </select>
                                    <div class='clear-div'></div>
                                </div>

                                <div class="payment-method-fields" id="payment_method_credit_card">
                                    <div class="form-line" id="_account_number_row">
                                        <label for="account_number" class="">Card Number</label>
                                        <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="account_number" name="account_number" placeholder="Account Number" value="">
                                        <div id="payment_logos">
											<?php
											foreach ($paymentLogos as $paymentMethodId => $imageId) {
												?>
                                                <img alt="Payment Logo" id="payment_method_logo_<?= strtolower($paymentMethodId) ?>" class="payment-method-logo" src="<?= getImageFilename($imageId, array("use_cdn" => true)) ?>">
												<?php
											}
											?>
                                        </div>
                                        <div class='clear-div'></div>
                                    </div>

                                    <div class="form-line" id="_expiration_month_row">
                                        <label for="expiration_month" class="">Expiration Date</label>
                                        <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="expiration_month" name="expiration_month">
                                            <option value="">[Month]</option>
											<?php
											for ($x = 1; $x <= 12; $x++) {
												?>
                                                <option value="<?= $x ?>"><?= $x . " - " . date("F", strtotime($x . "/01/2000")) ?></option>
												<?php
											}
											?>
                                        </select>
                                        <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="expiration_year" name="expiration_year">
                                            <option value="">[Year]</option>
											<?php
											for ($x = 0; $x < 12; $x++) {
												$year = date("Y") + $x;
												?>
                                                <option value="<?= $year ?>"><?= $year ?></option>
												<?php
											}
											?>
                                        </select>
                                        <div class='clear-div'></div>
                                    </div>

                                    <div class="form-line" id="_cvv_code_row">
                                        <label for="cvv_code" class="">Security Code</label>
                                        <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="5" maxlength="4" id="cvv_code" name="cvv_code" placeholder="CVV Code" value="">
                                        <a href="https://www.cvvnumber.com/cvv.html" target="_blank"><img id="cvv_image" src="/images/cvv_code.gif" alt="CVV Code"></a>
                                        <div class='clear-div'></div>
                                    </div>
                                </div> <!-- payment_method_credit_card -->

                                <div class="payment-method-fields" id="payment_method_bank_account">
                                    <div class="form-line" id="_routing_number_row">
                                        <label for="routing_number" class="">Bank Routing Number</label>
                                        <input tabindex="10" type="text" class="validate[required,custom[routingNumber]]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="routing_number" name="routing_number" placeholder="Routing Number" value="">
                                        <div class='clear-div'></div>
                                    </div>

                                    <div class="form-line" id="_bank_account_number_row">
                                        <label for="bank_account_number" class="">Account Number</label>
                                        <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="bank_account_number" name="bank_account_number" placeholder="Bank Account Number" value="">
                                        <div class='clear-div'></div>
                                    </div>
                                </div> <!-- payment_method_bank_account -->

                                <div class="form-line" id="_account_label_row">
                                    <label for="account_label" class="">Account Nickname</label>
                                    <span class="help-label">for future reference</span>
                                    <input tabindex="10" type="text" class="validate[required]" size="20" maxlength="30" id="account_label" name="account_label" placeholder="Account Label" value="">
                                    <div class='clear-div'></div>
                                </div>

                            </div> <!-- payment_information -->
                        </div> <!-- new_account -->
                    </div> <!-- billing_info_section -->

                </div> <!-- payment_wrapper -->

            </form>
            <p id="purchase_upgrade_wrapper" class="needs-upgrade-product <?= count($this->iContactIds) == 1 ? "" : "hidden" ?>">
                <button tabindex='10' id='purchase_upgrade'>Upgrade Subscription</button>
            </p>
        </div>
		<?php
	}

	function onLoadJavascript() {
		?>
        <script>
            $(document).on("change", "#contact_id", function () {
                const contactId = $(this).val();
                $(".upgrade-product, .needs-upgrade-product").addClass("hidden");

                if (!empty(contactId)) {
                    $("[data-contact_ids]").each(function () {
                        if ($(this).attr("data-contact_ids").split(",").includes(contactId)) {
                            $(this).removeClass("hidden");
                        }
                    });
                }
                if ($("[data-contact_ids]:not(.hidden)").length) {
                    $(".needs-upgrade-product").removeClass("hidden");
                    $("#no_upgrade_products_message").addClass("hidden");
                } else {
                    $("#no_upgrade_products_message").removeClass("hidden");
                }
                return false;
            });
            $(document).on("click", "#purchase_upgrade", function () {
                if ($("#_edit_form").validationEngine("validate")) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?url_action=purchase_upgrade", $("#_edit_form").serialize(), function (returnArray) {
                        if ("response" in returnArray) {
                            $("#upgrade_wrapper").html(returnArray['response']);
                        }
                    });
                }
            });
            $(document).on("click", "input[type=radio][name=product_id]", function () {
                    const salePrice = parseFloat($("input[type=radio][name=product_id]:checked").data("sale_price"));
                    if ($("#billing_amount").is("span")) {
                        $("#billing_amount").html(RoundFixed(salePrice, 2));
                        $("#billing_amount_wrapper").removeClass("hidden");
                        if (salePrice > 0) {
                            $("#_billing_info_section").removeClass("hidden");
                        } else {
                            $("#_billing_info_section").addClass("hidden");
                        }
                    } else {
                        $("#billing_amount").val(RoundFixed(salePrice, 2));
                    }
                }
            )
            ;
            $("#payment_method_id").change(function (event) {
                $(".payment-method-logo").removeClass("selected");
                $("#payment_method_logo_" + $(this).val()).addClass("selected");
                return false;
            });
            $("#billing_country_id").change(function () {
                if ($(this).val() === "1000") {
                    $("#_billing_state_row").hide();
                    $("#_billing_state_select_row").show();
                } else {
                    $("#_billing_state_row").show();
                    $("#_billing_state_select_row").hide();
                }
            });
            $("#billing_state_select").change(function () {
                $("#billing_state").val($(this).val());
            });
            $("#payment_method_id").change(function () {
                $(".payment-method-fields").hide();
                if (!empty($(this).val())) {
                    const paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");
                    $("#payment_method_" + paymentMethodTypeCode.toLowerCase()).show();
                }
            });
            $("#account_id").change(function () {
                if (!empty($(this).val())) {
                    $("#_new_account").hide();
                } else {
                    $("#_new_account").show();
                }
            });
            setTimeout(function () {
                $("#account_id").trigger("change");
                $("#country_id").trigger("change");
                $("#payment_method_id").trigger("change");
            }, 1000);
        </script>
		<?php
	}

	function internalCSS() {
		?>
        <style>
            select {
                min-width: 0;
            }

            #billing_amount_wrapper {
                padding: 40px 0;
            }

            #purchase_upgrade_wrapper {
                padding: 20px 0;
            }

            #new_payment_method_section {
                display: none;
                margin: 20px auto;
                border: 1px solid rgb(150, 150, 150);
                padding: 10px;
                width: 600px;
            }

            #new_payment_method_section h2 {
                text-align: center;
                margin-bottom: 10px;
            }

            .add-payment-method {
                background-color: rgb(200, 200, 200);
                text-align: center;
                cursor: pointer;
                font-weight: bold;
            }

            .add-payment-method:hover {
                background-color: rgb(220, 220, 220);
                color: rgb(0, 0, 100);
            }

            .payment-method-fields {
                display: none;
            }

            #cvv_image {
                position: relative;
                top: 0;
                height: 26px;
            }

            #payment_logos {
                margin-top: 5px;
            }

            .payment-method-logo {
                max-height: 64px;
                opacity: .2;
                margin-right: 20px;
            }

            .payment-method-logo.selected {
                opacity: 1;
            }
        </style>
		<?php
	}

}

$pageObject = new UpgradeSubscriptionPage();
$pageObject->displayPage();
