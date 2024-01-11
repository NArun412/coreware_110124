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

$GLOBALS['gPageCode'] = "BACKGROUNDPROCESS";
$runEnvironment = php_sapi_name();
if ($runEnvironment == "cli") {
	require_once "shared/startup.inc";
} else {
	require_once "../shared/startup.inc";
}

if (!$GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine']) {
	echo "ERROR: For security purposes, this program cannot be run from a browser - " . php_sapi_name() . ".\n";
	exit;
}

class ThisBackgroundProcess extends BackgroundProcess {
	function setProcessCode() {
		$this->iProcessCode = "recurring_payments";
	}

	function process() {
		$recurringResults = array();
		$recurringCount = 0;
		$inactivePausedCount = 0;
		$errorCount = 0;
		$recurringSet = executeQuery("select *,(select client_id from contacts where contact_id = recurring_payments.contact_id) client_id " .
			"from recurring_payments where" . ($GLOBALS['gUserRow']['superuser_flag'] && !$GLOBALS['gCommandLine'] ? " contact_id in (select contact_id from contacts where client_id = " . $GLOBALS['gClientId'] . ") and" : "") .
			" requires_attention = 0 and customer_paused = 0 and (end_date > current_date or end_date is null) and " .
			"(start_date is null or start_date <= current_date) and next_billing_date <= current_date and contact_id in (select contact_id from contacts where client_id in (select client_id from clients where inactive = 0)) order by client_id");
		$this->addResult($recurringSet['row_count'] . " recurring payments found to process");
		while ($recurringRow = getNextRow($recurringSet)) {
			$contactSubscriptionRow = array();
			if (!empty($recurringRow['contact_subscription_id'])) {
				$contactSubscriptionRow = getRowFromId("contact_subscriptions", "contact_subscription_id", $recurringRow['contact_subscription_id'], "inactive = 0");
				if (empty($contactSubscriptionRow)) {
					$inactivePausedCount++;
					continue;
				}
			}
			changeClient($recurringRow['client_id']);

			if (empty($recurringRow['contact_subscription_id'])) {
				$subscriptionRow = array();
			} else {
				$subscriptionRow = getRowFromId("subscriptions", "subscription_id", $contactSubscriptionRow['subscription_id']);
			}
			if (!empty($contactSubscriptionRow['customer_paused']) && !empty($subscriptionRow['maximum_pause_days'])) {
				$forceUnpause = empty($contactSubscriptionRow['date_paused']);
				if (!$forceUnpause) {
					$endPauseDate = date("Y-m-d", strtotime($contactSubscriptionRow['date_paused']) + ($subscriptionRow['maximum_pause_days'] * 24 * 60 * 60));
					$forceUnpause = (date("Y-m-d") >= $endPauseDate);
				}
				if ($forceUnpause) {
					executeQuery("update contact_subscriptions set customer_paused = 0 where contact_subscription_id = ?", $contactSubscriptionRow['contact_subscription_id']);
					addProgramLog("Subscription unpaused due to maximum pause days exceeded. contact_subscription_id = " . $contactSubscriptionRow['contact_subscription_id']);
					$contactSubscriptionRow['customer_paused'] = 0;
				}
			}
			$recurringPaymentErrorEmailId = getFieldFromId("email_id", "emails", "email_code", "RECURRING_PAYMENT_ERROR_EMAIL", "inactive = 0");
			$recurringPaymentErrorNoUserEmailId = getFieldFromId("email_id", "emails", "email_code", "RECURRING_PAYMENT_ERROR_NO_USER_EMAIL", "inactive = 0");
			if (!array_key_exists($GLOBALS['gClientId'], $recurringResults)) {
				$recurringResults[$GLOBALS['gClientId']] = array();
			}
			eCommerce::getClientMerchantAccountIds();
			$accountSet = executeQuery("select * from contacts join accounts using (contact_id) where accounts.account_id = ? and " .
				"inactive = 0 and account_token is not null", $recurringRow['account_id']);
			if (!$accountRow = getNextRow($accountSet)) {
				$recurringResults[$GLOBALS['gClientId']][] = "Unable to get valid account for recurring payment from " . getDisplayName($recurringRow['contact_id']) . " for recurring payment ID " . $recurringRow['recurring_payment_id'];
				if (empty($subscriptionRow['maximum_retries'])) {
					executeQuery("update recurring_payments set requires_attention = 1,error_message = ?,last_attempted = now() where recurring_payment_id = ?",
						date("m/d/Y h:i:s a T") . ": Unable to get valid account for recurring payment", $recurringRow['recurring_payment_id']);
				} else {
					if ($recurringRow['retry_count'] >= $subscriptionRow['maximum_retries']) {
						executeQuery("update recurring_payments set end_date = current_date,error_message = ?,last_attempted = now() where recurring_payment_id = ?",
							date("m/d/Y h:i:s a T") . ": Unable to get valid account for recurring payment", $recurringRow['recurring_payment_id']);
					} else {
						executeQuery("update recurring_payments set error_message = ?,last_attempted = now(),retry_count = retry_count + 1 where recurring_payment_id = ?",
							date("m/d/Y h:i:s a T") . ": Unable to get valid account for recurring payment", $recurringRow['recurring_payment_id']);
					}
				}
				$errorCount++;
				continue;
			}
			$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($accountRow['account_id']);
			$eCommerce = eCommerce::getEcommerceInstance($accountMerchantAccountId);
			if (!$eCommerce) {
				$recurringResults[$GLOBALS['gClientId']][] = "Unable to get Merchant Account for client.";
				$errorCount++;
				continue;
			} else if (!$eCommerce->hasCustomerDatabase()) {
				$recurringResults[$GLOBALS['gClientId']][] = "No customer database for merchant account.";
				$errorCount++;
				continue;
			}
			if (empty($accountRow['merchant_identifier'])) {
				$accountRow['merchant_identifier'] = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $accountRow['contact_id'], "merchant_account_id = ?", $accountMerchantAccountId);
			}
			if (empty($accountRow['merchant_identifier']) && $eCommerce->requiresCustomerToken()) {
				$recurringResults[$GLOBALS['gClientId']][] = "Contact Profile ID for " . getDisplayName($recurringRow['contact_id']) . " is missing";
				if (empty($subscriptionRow['maximum_retries'])) {
					executeQuery("update recurring_payments set requires_attention = 1,error_message = ?,last_attempted = now() where recurring_payment_id = ?",
						date("m/d/Y h:i:s a T") . ": Contact Profile ID for " . getDisplayName($recurringRow['contact_id']) . " is missing", $recurringRow['recurring_payment_id']);
				} else {
					if ($recurringRow['retry_count'] >= $subscriptionRow['maximum_retries']) {
						executeQuery("update recurring_payments set end_date = current_date,error_message = ?,last_attempted = now() where recurring_payment_id = ?",
							date("m/d/Y h:i:s a T") . ": Contact Profile ID for " . getDisplayName($recurringRow['contact_id']) . " is missing", $recurringRow['recurring_payment_id']);
					} else {
						executeQuery("update recurring_payments set error_message = ?,last_attempted = now(),retry_count = retry_count + 1 where recurring_payment_id = ?",
							date("m/d/Y h:i:s a T") . ": Contact Profile ID for " . getDisplayName($recurringRow['contact_id']) . " is missing", $recurringRow['recurring_payment_id']);
					}
				}
				$errorCount++;
				continue;
			}
			$GLOBALS['gPrimaryDatabase']->startTransaction();

			$orderObject = new Order();
			$orderObject->setCustomerContact($accountRow['contact_id']);
			$orderObject->setOrderField("payment_method_id", $accountRow['payment_method_id']);
			$orderObject->setOrderField("shipping_method_id", $recurringRow['shipping_method_id']);
			$orderObject->setOrderField("recurring_payment_id", $recurringRow['recurring_payment_id']);

			$shoppingCart = ShoppingCart::getShoppingCartForContact($recurringRow['contact_id'], "RECURRING");
			if (!$shoppingCart) {
				continue;
			}
			$shoppingCart->removeAllItems();
			$productAddonArray = array();
			$allVirtual = true;

			$subscriptionRenewalProductId = false;
			$resultSet = executeQuery("select * from recurring_payment_order_items where recurring_payment_id = ? and quantity > 0 and product_id in (select product_id from products where inactive = 0)", $recurringRow['recurring_payment_id']);
			while ($row = getNextRow($resultSet)) {
				$virtualProduct = getFieldFromId("virtual_product", "products", "product_id", $row["product_id"]);
				$allVirtual = $allVirtual && ($virtualProduct == 1);
				$productAddons = array();
				$addonSet = executeQuery("select * from recurring_payment_order_item_addons where recurring_payment_order_item_id = ?", $row['recurring_payment_order_item_id']);
				while ($addonRow = getNextRow($addonSet)) {
					$productAddons[] = $addonRow;
				}
				if (!array_key_exists($row['product_id'], $productAddonArray) && !empty($productAddons)) {
					$productAddonArray[$row['product_id']] = $productAddons;
				}
				$shoppingCart->addItem(array("product_id" => $row['product_id'], "quantity" => $row['quantity'], "sale_price" => $row['sale_price'], "set_quantity" => true));
				if (empty($subscriptionRow) && !$subscriptionRenewalProductId) {
					$subscriptionProductId = getFieldFromId("subscription_product_id", "subscription_products", "product_id", $row['product_id']);
					if (!empty($subscriptionProductId)) {
						$subscriptionRenewalProductId = $subscriptionProductId;
					}
				}
			}
			if (!empty($subscriptionRenewalProductId) && empty($subscriptionRow)) {
				$subscriptionId = getFieldFromId("subscription_id", "subscription_products", "product_id", $subscriptionRenewalProductId);
				$resultSet = executeQuery("select * from contact_subscriptions where contact_id = ? and subscription_id = ? and inactive = 0 and contact_subscription_id not in (select contact_subscription_id from recurring_payments where contact_subscription_id is not null)", $recurringRow['contact_id'], $subscriptionId);
				if ($resultSet['row_count'] == 1) {
					$subscriptionRow = getRowFromId("subscriptions", "subscription_id", $subscriptionId);
					$contactSubscriptionRow = getNextRow($resultSet);
					executeQuery("update recurring_payments set contact_subscription_id = ? where recurring_payment_id = ?", $contactSubscriptionRow['contact_subscription_id'], $recurringRow['recurring_payment_id']);
				}
			}
			if (!empty($subscriptionRenewalProductId) && empty($subscriptionRow)) {
				$recurringResults[$GLOBALS['gClientId']][] = getDisplayName($recurringRow['contact_id']) . ": Unable to create order for recurring payment because the order is for a subscription but notz subscription is attached";
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				executeQuery("update recurring_payments set requires_attention = 1,error_message = ?,last_attempted = now() where recurring_payment_id = ?",
					date("m/d/Y h:i:s a T") . ": Unable to create order for recurring payment because the order is for a subscription but not subscription is attached", $recurringRow['recurring_payment_id']);
				$errorCount++;
				continue;
			}
			if (!empty($contactSubscriptionRow['customer_paused'])) {
				$shoppingCart->removeAllItems();
				$productAddonArray = array();
				$allVirtual = true;
				if (empty($subscriptionRow['product_id'])) {
					continue;
				} else {
					$productCatalog = new ProductCatalog();
					$salePriceInfo = $productCatalog->getProductSalePrice($subscriptionRow['product_id']);
					$salePrice = $salePriceInfo['sale_price'];
					$shoppingCart->addItem(array("product_id" => $subscriptionRow['product_id'], "quantity" => 1, "sale_price" => $salePrice, "set_quantity" => true));
				}
			} else {
				if (!empty($recurringRow['promotion_id'])) {
					$shoppingCart->applyPromotionId($recurringRow['promotion_id']);
					// apply from shopping cart to make sure promotion is valid
					$orderObject->setPromotionId($shoppingCart->getPromotionId());
				}
			}

			$amount = 0;
			$quantity = 0;
			$shoppingCartItems = $shoppingCart->getShoppingCartItems();
			foreach ($shoppingCartItems as $thisItem) {
				if ($thisItem['quantity'] > 0) {
					$orderObject->addOrderItem(array("product_id" => $thisItem['product_id'], "quantity" => $thisItem['quantity'], "sale_price" => $thisItem['sale_price'], "product_addons" => $productAddonArray[$thisItem['product_id']]));
					$quantity += $thisItem['quantity'];
					$amount += ($thisItem['quantity'] * $thisItem['sale_price']);
				}
			}

			if ($quantity == 0) {
				$recurringResults[$GLOBALS['gClientId']][] = getDisplayName($recurringRow['contact_id']) . ": Unable to create order for recurring payment because there are no products in the order";
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				if (empty($subscriptionRow['maximum_retries'])) {
					executeQuery("update recurring_payments set requires_attention = 1,error_message = ?,last_attempted = now() where recurring_payment_id = ?",
						date("m/d/Y h:i:s a T") . ": Unable to create order for recurring payment because there are no products in the order", $recurringRow['recurring_payment_id']);
				} else {
					if ($recurringRow['retry_count'] >= $subscriptionRow['maximum_retries']) {
						executeQuery("update recurring_payments set end_date = current_date,error_message = ?,last_attempted = now() where recurring_payment_id = ?",
							date("m/d/Y h:i:s a T") . ": Unable to create order for recurring payment because there are no products in the order", $recurringRow['recurring_payment_id']);
					} else {
						executeQuery("update recurring_payments set error_message = ?,last_attempted = now(),retry_count = retry_count + 1 where recurring_payment_id = ?",
							date("m/d/Y h:i:s a T") . ": Unable to create order for recurring payment because there are no products in the order", $recurringRow['recurring_payment_id']);
					}
				}
				$errorCount++;
				continue;
			}
			$sourceId = $recurringRow['source_id'];
			if ($allVirtual) {
				$defaultLocationId = CustomField::getCustomFieldData($accountRow['contact_id'], "DEFAULT_LOCATION_ID");
				$locationContactId = getFieldFromId("contact_id", "locations", "location_id", $defaultLocationId);
				$locationContactId = $locationContactId ?: $GLOBALS['gClientRow']['contact_id'];
				$taxCharge = $orderObject->getTax($locationContactId);
				if (empty($sourceId)) {
					$locationCode = getFieldFromId("location_code", "locations", "location_id", $defaultLocationId);
					$sourceId = getFieldFromId("source_id", "sources", "source_code", $locationCode);
				}
			} else {
				$taxCharge = $orderObject->getTax();
			}
			if (!empty($sourceId)) {
				$orderObject->setOrderField("source_id", $sourceId);
			}
			$orderObject->setOrderField("tax_charge", $taxCharge);

			$paymentMethodRow = getRowFromId("payment_methods", "payment_method_id", $accountRow['payment_method_id']);
			if (empty($paymentMethodRow['flat_rate']) || $paymentMethodRow['flat_rate'] == 0) {
				$paymentMethodRow['flat_rate'] = 0;
			}
			if (empty($paymentMethodRow['fee_percent']) || $paymentMethodRow['fee_percent'] == 0) {
				$paymentMethodRow['fee_percent'] = 0;
			}
			$handlingCharge = round($paymentMethodRow['flat_rate'] + ($amount * $paymentMethodRow['fee_percent'] / 100), 2);
			$orderObject->setOrderField("handling_charge", $handlingCharge);

			$shippingCharge = 0;
			if ($shoppingCart) {
				$state = $accountRow['state'];
				$postalCode = $accountRow['postal_code'];
				$countryId = $accountRow['country_id'];

				if (!empty($recurringRow['shipping_method_id'])) {
					$shippingMethodRow = getRowFromId("shipping_methods", "shipping_method_id", $recurringRow['shipping_method_id']);
					if ($shippingMethodRow['pickup'] && !empty($shippingMethodRow['location_id'])) {
						$contactId = getFieldFromId("contact_id", "locations", "location_id", $shippingMethodRow['location_id']);
						if (!empty($contactId)) {
							$contactRow = Contact::getContact($contactId);
							$state = $contactRow['state'];
							$postalCode = $contactRow['postal_code'];
							$countryId = $contactRow['country_id'];
						}
					}
				}

				$shippingOptions = $shoppingCart->getShippingOptions($countryId, $state, $postalCode);
				if ($shippingOptions !== false) {
					foreach ($shippingOptions as $thisShippingMethod) {
						if ($thisShippingMethod['shipping_method_id'] == $recurringRow['shipping_method_id']) {
							$shippingCharge = $thisShippingMethod['shipping_charge'];
						}
					}
				}
			}
			$orderObject->setOrderField("shipping_charge", $shippingCharge);

			$amount += $taxCharge;
			$amount += $handlingCharge;
			$amount += $shippingCharge;

			if (!$orderObject->generateOrder()) {
				$recurringResults[$GLOBALS['gClientId']][] = getDisplayName($recurringRow['contact_id']) . ": Unable to create order: " . $orderObject->getErrorMessage();
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				if (empty($subscriptionRow['maximum_retries'])) {
					executeQuery("update recurring_payments set requires_attention = 1,error_message = ?,last_attempted = now() where recurring_payment_id = ?",
						date("m/d/Y h:i:s a T") . ": Unable to create order: " . $orderObject->getErrorMessage(), $recurringRow['recurring_payment_id']);
				} else {
					if ($recurringRow['retry_count'] >= $subscriptionRow['maximum_retries']) {
						executeQuery("update recurring_payments set end_date = current_date,error_message = ?,last_attempted = now() where recurring_payment_id = ?",
							date("m/d/Y h:i:s a T") . ": Unable to create order: " . $orderObject->getErrorMessage(), $recurringRow['recurring_payment_id']);
					} else {
						executeQuery("update recurring_payments set error_message = ?,last_attempted = now(),retry_count = retry_count + 1 where recurring_payment_id = ?",
							date("m/d/Y h:i:s a T") . ": Unable to create order: " . $orderObject->getErrorMessage(), $recurringRow['recurring_payment_id']);
					}
				}
				$errorCount++;
				continue;
			}
			$orderId = $orderObject->getOrderId();
			if ($amount > 0) {
				$contactPayment = new ContactPayment($accountRow['contact_id'], $eCommerce);
				if (!$contactPayment->setAccount($accountRow['account_id'])) {
					$recurringResults[$GLOBALS['gClientId']][] = getDisplayName($recurringRow['contact_id']) . ": Invalid account for recurring payment";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					if (empty($subscriptionRow['maximum_retries'])) {
						executeQuery("update recurring_payments set requires_attention = 1,error_message = ?,last_attempted = now() where recurring_payment_id = ?",
							date("m/d/Y h:i:s a T") . ": Invalid account for recurring payment", $recurringRow['recurring_payment_id']);
					} else {
						if ($recurringRow['retry_count'] >= $subscriptionRow['maximum_retries']) {
							executeQuery("update recurring_payments set end_date = current_date,error_message = ?,last_attempted = now() where recurring_payment_id = ?",
								date("m/d/Y h:i:s a T") . ": Invalid account for recurring payment", $recurringRow['recurring_payment_id']);
						} else {
							executeQuery("update recurring_payments set error_message = ?,last_attempted = now(),retry_count = retry_count + 1 where recurring_payment_id = ?",
								date("m/d/Y h:i:s a T") . ": Invalid account for recurring payment", $recurringRow['recurring_payment_id']);
						}
					}
					$errorCount++;
					continue;
				}

				$parameters = array();
				$parameters['order_object'] = $orderObject;
				$parameters['amount'] = $amount;
				$parameters['tax_charge'] = $taxCharge;
				$parameters['handling_charge'] = $handlingCharge;
				$parameters['shipping_charge'] = $shippingCharge;
				$parameters['payment_method_id'] = $paymentMethodRow['payment_method_id'];
				$returnArray = $contactPayment->authorizeCharge($parameters);
				if (!$returnArray) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$recurringResults[$GLOBALS['gClientId']][] = getDisplayName($recurringRow['contact_id']) . ": Unable to create charge: " . $contactPayment->getErrorMessage();

					# send email to customer
					$substitutions = array_merge($accountRow, $recurringRow);
					$substitutions['error_message'] = date("m/d/Y h:i:s a T") . ": " . $contactPayment->getErrorMessage();
					$substitutions['subscription'] = $subscriptionRow['description'];
					$accountUserId = Contact::getContactUserId($accountRow['contact_id']);
					$usingRecurringPaymentErrorEmailId = (empty($accountUserId) && !empty($recurringPaymentErrorNoUserEmailId) ? $recurringPaymentErrorNoUserEmailId : $recurringPaymentErrorEmailId);
					if (!empty($usingRecurringPaymentErrorEmailId)) {
						sendEmail(array("email_id" => $usingRecurringPaymentErrorEmailId, "substitutions" => $substitutions, "email_address" => $accountRow['email_address']));
					}
					ContactPayment::notifyCRM($accountRow['contact_id']);

					if (empty($subscriptionRow['maximum_retries'])) {
						executeQuery("update recurring_payments set requires_attention = 1,error_message = ?,last_attempted = now() where recurring_payment_id = ?",
							date("m/d/Y h:i:s a T") . ": Unable to create charge: " . $contactPayment->getErrorMessage(), $recurringRow['recurring_payment_id']);
					} else {
						if ($recurringRow['retry_count'] >= $subscriptionRow['maximum_retries']) {
							executeQuery("update recurring_payments set end_date = current_date,error_message = ?,last_attempted = now() where recurring_payment_id = ?",
								date("m/d/Y h:i:s a T") . ": Unable to create charge: " . $contactPayment->getErrorMessage(), $recurringRow['recurring_payment_id']);
						} else {
							executeQuery("update recurring_payments set error_message = ?,last_attempted = now(),retry_count = retry_count + 1 where recurring_payment_id = ?",
								date("m/d/Y h:i:s a T") . ": Unable to create charge: " . $contactPayment->getErrorMessage(), $recurringRow['recurring_payment_id']);
						}
					}
					$errorCount++;
					continue;
				}
			}

			$recurringTypeRow = getRowFromId("recurring_payment_types", "recurring_payment_type_id", $recurringRow['recurring_payment_type_id']);
			$validUnits = array("day", "week", "month");
			$intervalUnit = $recurringTypeRow['interval_unit'];
			if (empty($intervalUnit) || !in_array($intervalUnit, $validUnits)) {
				$intervalUnit = "month";
			}
			$unitsBetween = $recurringTypeRow['units_between'];
			if (empty($unitsBetween) || $unitsBetween < 0) {
				$unitsBetween = 1;
			}
			try {
				$nextBillingDate = new DateTime($recurringRow['next_billing_date']);
			} catch (Exception $e) {
				$nextBillingDate = new DateTime();
			}
			$intervalString = $unitsBetween . " " . $intervalUnit . ($unitsBetween == 1 ? "" : "s");
			date_add($nextBillingDate, date_interval_create_from_date_string($intervalString));
			if (!empty($subscriptionRow['ignore_skipped'])) {
				while (date_format($nextBillingDate, "Y-m-d") < date("Y-m-d")) {
					date_add($nextBillingDate, date_interval_create_from_date_string($intervalString));
				}
			}
			executeQuery("update recurring_payments set next_billing_date = ? where recurring_payment_id = ?", date_format($nextBillingDate, "Y-m-d"), $recurringRow['recurring_payment_id']);

			$GLOBALS['gPrimaryDatabase']->commitTransaction();
			Order::processOrderItems($orderId);
			Order::processOrderAutomation($orderId);
			if (function_exists("_localServerProcessOrder")) {
				_localServerProcessOrder($orderId);
			}

			Order::notifyCRM($orderId);
			coreSTORE::orderNotification($orderId, "order_created");
			Order::reportOrderToTaxjar($orderId);

			$recurringCount++;
			sleep(5);
		}
		$this->addResult($inactivePausedCount . " recurring payments that are paused or inactive skipped");
		$this->addResult($recurringCount . " recurring payments created");
		$this->addResult($errorCount . " errors encountered");

		$GLOBALS['gChangeLogNotes'] = "Updating User Subscriptions from Create Contact User";
		updateUserSubscriptions();
		$GLOBALS['gChangeLogNotes'] = "";

		$clientSet = executeQuery("select * from clients");
		while ($clientRow = getNextRow($clientSet)) {
			$logEntries = $recurringResults[$clientRow['client_id']];
			if (empty($logEntries)) {
				continue;
			}
			sort($logEntries);
			$logEntry = implode("\n", $logEntries);
			$htmlLogEntry = "Recurring Payments processed<br>\n<br>\n" . implode("<br>\n", $logEntries) . "<br>\n<br>\n";
			$this->addResult($logEntry);
			$GLOBALS['gClientId'] = $clientRow['client_id'];
			executeQuery("insert into program_log (client_id,program_name,log_entry) values (?,'Recurring Payments',?)", $GLOBALS['gClientId'], $logEntry);
			sendEmail(array("subject" => "Recurring Payments", "body" => $htmlLogEntry, "notification_code" => array("RECURRING_PAYMENTS", "DONATIONS")));
		}
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
