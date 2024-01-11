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

$GLOBALS['gPageCode'] = "EVENTREGISTRATION";
require_once "shared/startup.inc";

class EventRegistrationPage extends Page {

	var $iEventId = "";

	function setup() {
		if (empty($_GET['ajax']) && (empty($_GET['type']) || !empty($_GET['id']))) {
			$this->iEventId = getFieldFromId("event_id", "events", "event_id", $_GET['id'], "(end_date is null or end_date >= current_date)");
			if (empty($this->iEventId)) {
				header("Location: /");
				exit;
			}
		}
		if (!empty($_GET['type']) && empty($this->iEventId)) {
			$resultSet = executeQuery("select * from events where tentative = 0 and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? and event_type_id in " .
				"(select event_type_id from event_types where event_type_code = ?) and start_date >= current_date order by start_date,description", $GLOBALS['gClientId'], strtoupper($_GET['type']));
			if ($resultSet['row_count'] == 1) {
				if ($row = getNextRow($resultSet)) {
					$this->iEventId = $row['event_id'];
				}
			}
		}
	}

	function headerIncludes() {
		?>
        <script src="<?= autoVersion('/js/jsignature/jSignature.js') ?>"></script>
        <script src="<?= autoVersion('/js/jsignature/jSignature.CompressorSVG.js') ?>"></script>
        <script src="<?= autoVersion('/js/jsignature/jSignature.UndoButton.js') ?>"></script>
        <script src="<?= autoVersion('/js/jsignature/signhere/jSignature.SignHere.js') ?>"></script>
		<?php
	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "get_product_price":
				$productId = getFieldFromId("product_id", "products", "product_id", $_GET['product_id']);
				if (empty($productId)) {
					ajaxResponse($returnArray);
					break;
				}
				$quantity = $_GET['quantity'];
				if (empty($quantity) || !is_numeric($quantity) || $quantity <= 0) {
					$quantity = 1;
				}
				$salePrice = getFieldFromId("price", "event_registration_products", "event_id", $_GET['event_id'], "product_id = ?", $productId);
				if (strlen($salePrice) > 0) {
					$returnArray['sale_price'] = $salePrice;
					ajaxResponse($returnArray);
					break;
				}
				$productCatalog = new ProductCatalog();
				$salePriceInfo = $productCatalog->getProductSalePrice($productId, array("quantity" => $quantity, "no_cache" => true, "no_stored_prices" => true));
				$salePrice = $salePriceInfo['sale_price'];
				$returnArray['sale_price'] = $salePrice;
				ajaxResponse($returnArray);
				break;
			case "register":
				$eventId = getFieldFromId("event_id", "events", "event_id", $_POST['event_id'], "(end_date is null or end_date >= current_date)");
				if (empty($eventId)) {
					$returnArray['error_message'] = "Invalid Event";
					ajaxResponse($returnArray);
					break;
				}
				$eventRow = getRowFromId("events", "event_id", $eventId);
				$productCatalog = new ProductCatalog();

				$productIdList = array();
				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("product_id_")) == "product_id_") {
						$rowNumber = substr($fieldName, strlen("product_id_"));
						$productId = getFieldFromId("product_id", "products", "product_id", $fieldData, "inactive = 0");
						if (!empty($productId)) {
							$productQuantity = $_POST['product_quantity_' . $rowNumber];
							if (empty($productQuantity) || !is_numeric($productQuantity) || $productQuantity <= 0) {
								$productQuantity = 0;
							}
							if ($productId == $eventRow['product_id']) {
								$productQuantity = 1;
							}
							$productIdList[$productId] = array("product_id" => $productId, "quantity" => $productQuantity);
							$salePriceInfo = $productCatalog->getProductSalePrice($productId, array("quantity" => (empty($productQuantity) ? 1 : $productQuantity), "no_cache" => true, "no_stored_prices" => true));
							$salePrice = $salePriceInfo['sale_price'];
							$productIdList[$productId]['sale_price'] = $salePrice;
							$productIdList[$productId]['maximum_quantity'] = "";
						}
					}
				}

				if (!empty($eventRow['product_id'])) {
					$productId = $eventRow['product_id'];
					if (!array_key_exists($productId, $productIdList)) {
						$productIdList[$productId] = array("product_id" => $productId, "quantity" => 1);
						$salePriceInfo = $productCatalog->getProductSalePrice($productId, array("no_cache" => true, "no_stored_prices" => true));
						$salePrice = $salePriceInfo['sale_price'];
						$productIdList[$productId]['sale_price'] = $salePrice;
						$productIdList[$productId]['maximum_quantity'] = "";
					}
				}

				$resultSet = executeQuery("select * from event_registration_products where (product_id is not null or product_group_id is not null) and required = 1 and event_id = ?", $eventId);
				while ($row = getNextRow($resultSet)) {
					if (!empty($row['product_group_id'])) {
						$productFound = false;
						$productSet = executeQuery("select product_id from product_group_variants where product_group_id = ?", $row['product_group_id']);
						while ($productRow = getNextRow($productSet)) {
							if (array_key_exists($productRow['product_id'], $productIdList) && $productIdList[$productRow['product_id']['quantity']] > 0) {
								$productFound = true;
								break;
							}
						}
						if (!$productFound) {
							$returnArray['error_message'] = "Required products are not included in the registration";
							ajaxResponse($returnArray);
							break;
						}
					} else {
						if (!array_key_exists($row['product_id'], $productIdList) || $productIdList[$row['product_id']]['quantity'] == 0) {
							$returnArray['error_message'] = "Required products are not included in the registration";
							ajaxResponse($returnArray);
							break;
						}
					}
				}
				$resultSet = executeQuery("select * from event_registration_products where (product_id is not null or product_group_id is not null) and event_id = ?", $eventId);
				while ($row = getNextRow($resultSet)) {
					if (!empty($row['product_group_id'])) {
						$productSet = executeQuery("select product_id from product_group_variants where product_group_id = ?", $row['product_group_id']);
						while ($productRow = getNextRow($productSet)) {
							if (!array_key_exists($productRow['product_id'], $productIdList)) {
								continue;
							}
							if (strlen($row['price']) > 0) {
								$salePrice = $row['price'];
								$productIdList[$productRow['product_id']]['sale_price'] = $salePrice;
								$productIdList[$productRow['product_id']]['maximum_quantity'] = $row['maximum_quantity'];
							}
							break;
						}
					} else {
						if (!array_key_exists($row['product_id'], $productIdList)) {
							continue;
						}
						if (strlen($row['price']) > 0) {
							$salePrice = $row['price'];
							$productIdList[$row['product_id']]['sale_price'] = $salePrice;
							$productIdList[$row['product_id']]['maximum_quantity'] = $row['maximum_quantity'];
						}
					}
				}

				$totalCost = 0;
				foreach ($productIdList as $productId => $productInfo) {
					if ($productInfo['quantity'] <= 0) {
						continue;
					}
					if (!array_key_exists("sale_price", $productInfo)) {
						$returnArray['error_message'] = "Invalid products included in registration: " . jsonEncode($productIdList);
						ajaxResponse($returnArray);
						break;
					}
					$totalCost += $productInfo['sale_price'] * $productInfo['quantity'];
					if (!empty($productInfo['maximum_quantity'])) {
						$countSet = executeQuery("select sum(quantity) from order_items where product_id = ? and deleted = 0 and " .
							"order_id in (select order_id from orders where deleted = 0) and order_id in (select order_id from event_registrants " .
							"where event_id = ?)", $productId, $eventRow['event_id']);
						$usedCount = 0;
						if ($countRow = getNextRow($countSet)) {
							if (!empty($countRow['sum(quantity)'])) {
								$usedCount = $countRow['sum(quantity)'];
							}
						}
						if (($usedCount + $productInfo['quantity']) >= $productInfo['maximum_quantity']) {
							$returnArray['error_message'] = "One or more of the options are no longer available. Refresh the page and start over.";
							ajaxResponse($returnArray);
							break;
						}
					}
				}
				if (($totalCost - $_POST['total_cost']) != 0) {
					$returnArray['error_message'] = "Invalid event registration fee total" . ($GLOBALS['gUserRow']['superuser_flag'] ? " - " . $totalCost . ":" . $_POST['total_cost'] : "");
					ajaxResponse($returnArray);
					break;
				}

				if (empty($_POST['email_address'])) {
					$returnArray['error_message'] = "Missing contact information";
					ajaxResponse($returnArray);
					break;
				}
				$registrants = array("");
				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("additional_registration_")) == "additional_registration_") {
						if (is_numeric($fieldData)) {
							$registrants[] = $fieldData;
						}
					}
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();

				$contactId = "";
				$registrantContactIds = array();
				foreach ($registrants as $registrantRowNumber) {
					$attendeeCounts = Events::getAttendeeCounts($eventId);
					if ($attendeeCounts['registrants'] >= $attendeeCounts['attendees']) {
						$returnArray['error_message'] = "Sorry, this event is full.";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					if ($GLOBALS['gLoggedIn'] && empty($registrantRowNumber) && empty($_POST['for_whom'])) {
						$contactId = $GLOBALS['gUserRow']['contact_id'];
                        $nameValues = array_filter(array_intersect_key($_POST,array_flip(['address_1','city','state','postal_code'])));
                        if(!empty($nameValues)) {
                            $nameValues['country_id'] = $_POST['country_id'];
                            $contactDataTable = new DataTable("contacts");
                            $contactDataTable->setSaveOnlyPresent(true);
                            $contactDataTable->saveRecord(array("primary_id"=>$contactId, "name_values" => $nameValues));
                        }
					} else {
                        $rowNumberSuffix = (empty($registrantRowNumber) ? "" : "_" . $registrantRowNumber);
						$resultSet = executeQuery("select * from contacts where first_name = ? and last_name = ? and email_address = ? and client_id = ?" . (empty($registrantRowNumber) ? "" : " and company_id = " . makeNumberParameter($GLOBALS['gUserRow']['company_id'])),
							$_POST['first_name' . $rowNumberSuffix], $_POST['last_name' . $rowNumberSuffix],
							$_POST['email_address' . $rowNumberSuffix], $GLOBALS['gClientId']);
						if ($row = getNextRow($resultSet)) {
							$contactId = $row['contact_id'];
                            $nameValues = array_filter(array_intersect_key($_POST, array_flip(['address_1' . $rowNumberSuffix,
                                'city' . $rowNumberSuffix,
                                'state' . $rowNumberSuffix,
                                'postal_code' . $rowNumberSuffix])));
                            if(!empty($nameValues)) {
                                $nameValues['country_id'] = $_POST['country_id'];
                                $contactDataTable = new DataTable("contacts");
                                $contactDataTable->setSaveOnlyPresent(true);
                                $contactDataTable->saveRecord(array("primary_id"=>$contactId, "name_values" => $nameValues));
                            }
						} else {
							$contactDataTable = new DataTable("contacts");
							$contactId = $contactDataTable->saveRecord(array("name_values" => array("first_name" => $_POST['first_name' . $rowNumberSuffix],
								"last_name" => $_POST['last_name' . $rowNumberSuffix],
								"address_1" => $_POST['address_1' . $rowNumberSuffix],
								"city" => $_POST['city' . $rowNumberSuffix],
								"state" => $_POST['state' . $rowNumberSuffix],
								"postal_code" => $_POST['postal_code' . $rowNumberSuffix],
                                "country_id" => $_POST['country_id'],
								"email_address" => $_POST['email_address' . $rowNumberSuffix],
								"company_id" => $GLOBALS['gUserRow']['company_id'])));
						}
						if (!empty($_POST['phone_number' . $rowNumberSuffix])) {
							$phoneNumberId = getFieldFromId("phone_number_id", "phone_numbers", "contact_id", $contactId, "phone_number = ?", $_POST['phone_number' . $rowNumberSuffix]);
							if (empty($phoneNumberId)) {
								executeQuery("insert into phone_numbers (contact_id,phone_number) values (?,?)", $contactId, $_POST['phone_number' . $rowNumberSuffix]);
							}
						}
					}
					if (empty($contactId)) {
						$returnArray['error_message'] = "Missing contact information";
						ajaxResponse($returnArray);
						break;
					}
					$eventRegistrantId = getFieldFromId("event_registrant_id", "event_registrants", "event_id", $eventId, "contact_id = ?", $contactId);
					if (!empty($eventRegistrantId)) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = (empty($_POST['for_whom']) ? "You are" : "This person is") . " already registered for this event";
						ajaxResponse($returnArray);
						break;
					}
					$resultSet = executeQuery("insert into event_registrants (event_id,contact_id,registration_time) values (?,?,now())", $eventId, $contactId);
					if (!empty($resultSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
						ajaxResponse($returnArray);
						break;
					}
					$eventRegistrantId = $resultSet['insert_id'];
					$registrantContactIds[$contactId] = $eventRegistrantId;

					if (!empty($_POST['for_whom']) && $GLOBALS['gLoggedIn'] && empty($registrantRowNumber)) {
						$contactId = $GLOBALS['gUserRow']['contact_id'];
					}

					if (!empty($eventRow['product_id'])) {
						$attendeeCount = 0;
						$resultSet = executeQuery("select count(*) from event_registrants where event_id = ?", $eventRow['event_id']);
						if ($row = getNextRow($resultSet)) {
							$attendeeCount = $row['count(*)'];
						}
						if ($attendeeCount >= $eventRow['attendees']) {
							executeQuery("update products set non_inventory_item = 0 where product_id = ?", $eventRow['product_id']);
							executeQuery("update product_inventories set quantity = 0 where product_id = ?", $eventRow['product_id']);
						} else {
							executeQuery("update products set non_inventory_item = 1 where product_id = ?", $eventRow['product_id']);
						}
					}

					$resultSet = executeQuery("select * from event_registration_custom_fields where event_id = ?", $eventId);
					while ($row = getNextRow($resultSet)) {
						$customField = CustomField::getCustomField($row['custom_field_id'], "custom_field_id_" . $row['custom_field_id'] . (empty($registrantRowNumber) ? "" : "_" . $registrantRowNumber));
						if (!$customField->saveData(array_merge(array("primary_id" => $eventRegistrantId), $_POST))) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = $customField->getErrorMessage();
							ajaxResponse($returnArray);
							break;
						}
					}

					$orderId = false;
					if (!empty($productIdList)) {
						$orderObject = new Order();
						$orderObject->setCustomerContact($contactId);
						$totalAmount = 0;
						foreach ($productIdList as $productInfo) {
							if ($productInfo['quantity'] <= 0) {
								continue;
							}
							$orderObject->addOrderItem(array("product_id" => $productInfo['product_id'], "quantity" => $productInfo['quantity'], "sale_price" => $productInfo['sale_price']));
							$totalAmount += ($productInfo['quantity'] * $productInfo['sale_price']);
						}
						$taxCharge = $orderObject->getTax();
						if (empty($taxCharge)) {
							$taxCharge = 0;
						}
						$totalAmount += $taxCharge;
						$orderObject->setOrderField("tax_charge", $taxCharge);
						if (!$orderObject->generateOrder()) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = "Unable to create event registration";
							ajaxResponse($returnArray);
							break;
						}
						$orderId = $orderObject->getOrderId();
						if (empty($orderId)) {
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							$returnArray['error_message'] = "Unable to create event registration";
							ajaxResponse($returnArray);
							break;
						}
						executeQuery("update event_registrants set order_id = ? where event_registrant_id = ?", $orderId, $eventRegistrantId);

                        $logEntry = "Order placed by contact ID " . $contactId . ":\n\n";
                        foreach ($productIdList as $thisItem) {
                            $productRow = ProductCatalog::getCachedProductRow($thisItem['product_id']);
                            $productDataRow = getRowFromId("product_data", "product_id", $thisItem['product_id']);
                            $logEntry .= $productRow['product_code'] . " | " . $productRow['description'] . " | " . $productDataRow['upc_code'] . " | " . $thisItem['quantity'] . "\n";
                        }
                        $logEntry .= "\n" . jsonEncode(array_diff_key($_POST,array("account_number"=>1, "cvv_code"=>1, "routing_number"=>1, "bank_account_number"=>1, "password"=>1))) . "\n";
                        $programLogId = addProgramLog($logEntry);

                        if (!empty($totalAmount)) {
							$_POST['payment_method_type_code'] = getFieldFromId("payment_method_type_code", "payment_method_types",
								"payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id",
									$_POST['payment_method_id']));
							$isBankAccount = ($_POST['payment_method_type_code'] == "BANK_ACCOUNT");

							if ($_POST['payment_method_type_code'] == "GIFT_CARD") {
								$giftCard = new GiftCard(array("gift_card_number" => $_POST['gift_card_number'], "user_id" => $GLOBALS['gUserId']));
								if (!$giftCard->isValid()) {
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									$returnArray['error_message'] = "Gift Card doesn't exist";
									ajaxResponse($returnArray);
									break;
								}
								$balance = $giftCard->getBalance();
								if ($balance < $totalAmount) {
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									$returnArray['error_message'] = "Not enough on the gift card to pay for this registration";
									ajaxResponse($returnArray);
									break;
								}
								if (!$giftCard->adjustBalance(false, (-1 * $totalAmount), "Usage for order", $orderId)) {
									$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
									$returnArray['error_message'] = "Unable to process the gift card transaction";
									ajaxResponse($returnArray);
									break;
								}
                                $orderObject->createOrderPayment($totalAmount, array("payment_method_id" => $_POST['payment_method_id']));
                            } else if ($_POST['payment_method_type_code'] == "CHARGE_ACCOUNT") {
								$orderObject->createOrderPayment($totalAmount, array("payment_method_id" => $_POST['payment_method_id']));
							} else {
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
									$success = $eCommerce->createCustomerProfile(array("contact_id" => $contactId, "first_name" => $_POST['first_name' . (empty($registrantRowNumber) ? "" : "_" . $registrantRowNumber)],
										"last_name" => $_POST['last_name' . (empty($registrantRowNumber) ? "" : "_" . $registrantRowNumber)], "address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'],
										"state" => $_POST['billing_state'], "postal_code" => $_POST['billing_postal_code'], "email_address" => $_POST['email_address' . (empty($registrantRowNumber) ? "" : "_" . $registrantRowNumber)]));
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

								# if new account, create it

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
								} else {
									$accountId = getFieldFromId("account_id", "accounts", "account_id", $_POST['account_id'], "contact_id = ?", $contactId);
									$_POST['payment_method_id'] = getFieldFromId("payment_method_id", "accounts", "account_id", $accountId);
								}
								$accountToken = getFieldFromId("account_token", "accounts", "account_id", $accountId, "contact_id = ?", $contactId);
								$accountMerchantIdentifier = getFieldFromId("merchant_identifier", "accounts", "account_id", $accountId);
								if (empty($accountToken) && !empty($_POST['account_id'])) {
									$returnArray['error_message'] = "There is a problem using an existing payment method. Please create a new one. #953";
									$this->iDatabase->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}

								$accountMerchantAccountId = eCommerce::getAccountMerchantAccount($accountId);
								if ($accountMerchantAccountId != $merchantAccountId) {
									$returnArray['error_message'] = "There is a problem with this account. #584";
									$this->iDatabase->rollbackTransaction();
									ajaxResponse($returnArray);
									break;
								}

								# if the user is asking to save account, store in merchant gateway

								if ($_POST['save_account'] && empty($accountToken) && !empty($eCommerce) && $eCommerce->hasCustomerDatabase()) {
									$paymentArray = array("contact_id" => $contactId, "account_id" => $accountId, "merchant_identifier" => $merchantIdentifier,
										"first_name" => (empty($_POST['billing_first_name']) ? $_POST['first_name' . (empty($registrantRowNumber) ? "" : "_" . $registrantRowNumber)] : $_POST['billing_first_name']),
										"last_name" => (empty($_POST['billing_last_name']) ? $_POST['last_name' . (empty($registrantRowNumber) ? "" : "_" . $registrantRowNumber)] : $_POST['billing_last_name']),
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
								}

								# If creating the account didn't work, exit with error.

								if (empty($accountToken) && empty($_POST['account_number']) && empty($_POST['bank_account_number'])) {
									$this->iDatabase->rollbackTransaction();
									$returnArray['error_message'] = "Unable to charge account. Please contact customer service. #532";
									ajaxResponse($returnArray);
									break;
								}

								# charge the card.

								if (empty($accountToken)) {
									$paymentArray = array("amount" => $totalAmount, "order_number" => $orderId, "description" => "Registration for " . $eventRow['description'],
										"first_name" => (empty($_POST['billing_first_name']) ? $_POST['first_name' . (empty($registrantRowNumber) ? "" : "_" . $registrantRowNumber)] : $_POST['billing_first_name']),
										"last_name" => (empty($_POST['billing_last_name']) ? $_POST['last_name' . (empty($registrantRowNumber) ? "" : "_" . $registrantRowNumber)] : $_POST['billing_last_name']),
										"business_name" => (empty($_POST['billing_business_name']) ? $_POST['business_name'] : $_POST['billing_business_name']),
										"address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
										"postal_code" => (empty($_POST['billing_postal_code']) ? $_POST['postal_code'] : $_POST['billing_postal_code']),
										"country_id" => (empty($_POST['billing_country_id']) ? $_POST['country_id'] : $_POST['billing_country_id']),
										"email_address" => $_POST['email_address' . (empty($registrantRowNumber) ? "" : "_" . $registrantRowNumber)], "contact_id" => $contactId);
									if ($isBankAccount) {
										$paymentArray['bank_routing_number'] = $_POST['routing_number'];
										$paymentArray['bank_account_number'] = $_POST['bank_account_number'];
										$paymentArray['bank_account_type'] = strtolower(str_replace("_", "", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id'])));
									} else {
										$paymentArray['card_number'] = $_POST['account_number'];
										$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
										$paymentArray['card_code'] = $_POST['cvv_code'];
									}
									$success = $eCommerce->authorizeCharge($paymentArray);
									$response = $eCommerce->getResponse();
									if ($success) {
										$orderObject->createOrderPayment($totalAmount, array("payment_method_id" => $_POST['payment_method_id'], "account_id" => $accountId,
											"authorization_code" => $response['authorization_code'], "transaction_identifier" => $response['transaction_id']));
									} else {
										$this->iDatabase->rollbackTransaction();
										$returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
										$eCommerce->writeLog(($isBankAccount ? $paymentArray['bank_account_number'] : $paymentArray['card_number']), $response['response_reason_text'], true);
										ajaxResponse($returnArray);
										break;
									}
								} else if (!empty($eCommerce) && $eCommerce->hasCustomerDatabase()) {
									$addressId = getFieldFromId("address_id", "accounts", "account_id", $accountId);
									$success = $eCommerce->createCustomerProfileTransactionRequest(array("amount" => $totalAmount, "order_number" => $orderId, "address_id" => $addressId,
										"merchant_identifier" => (empty($accountMerchantIdentifier) ? $merchantIdentifier : $accountMerchantIdentifier), "account_token" => $accountToken));
									$response = $eCommerce->getResponse();
									if ($success) {
										$orderObject->createOrderPayment($totalAmount, array("payment_method_id" => $_POST['payment_method_id'], "account_id" => $accountId,
											"authorization_code" => $response['authorization_code'], "transaction_identifier" => $response['transaction_id']));
									} else {
										if (!empty($customerPaymentProfileId)) {
											$eCommerce->deleteCustomerPaymentProfile(array("merchant_identifier" => $merchantIdentifier, "account_token" => $customerPaymentProfileId));
										}
										$this->iDatabase->rollbackTransaction();
										$returnArray['error_message'] = "Charge failed: " . $response['response_reason_text'];
										$eCommerce->writeLog($accountToken, $response['response_reason_text'], true);
										ajaxResponse($returnArray);
										break;
									}
								}
							}
                            addProgramLog("\nOrder Completed, ID " . $orderId, $programLogId);
                        }
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				if (!empty($orderId)) {
					Order::processOrderItems($orderId, array("event_registration_done" => true));
					Order::processOrderAutomation($orderId);
					coreSTORE::orderNotification($orderId, "order_created");
					Order::notifyCRM($orderId);
				}

				$substitutions = Events::getEventRegistrationSubstitutions($eventRow, $contactId);
				if (!empty($registrantContactIds)) {
					Events::sendEventNotifications($eventRow['event_id'], $registrantContactIds);
				}

				if (empty($eventRow['email_id'])) {
					$eventRow['email_id'] = getFieldFromId("email_id", "event_type_location_emails", "event_type_id", $eventRow['event_type_id'], "location_id = ?", $eventRow['location_id']);
					if (empty($eventRow['email_id'])) {
						$eventRow['email_id'] = getFieldFromId("email_id", "event_types", "event_type_id", $eventRow['event_type_id']);
					}
				}
				if (!empty($eventRow['email_id'])) {
					sendEmail(array("email_id" => $eventRow['email_id'], "email_address" => $substitutions['email_address'], "substitutions" => $substitutions, "contact_id" => $contactId));
				}
				$returnArray['response'] = (empty($eventRow['response_content']) ? $this->getFragment("REGISTER_RESPONSE") : $eventRow['response_content']);
				$returnArray['response'] = PlaceHolders::massageContent($returnArray['response'], $substitutions);
				ajaxResponse($returnArray);
				break;
		}
	}

	function onLoadJavascript() {
		?>
        <script>
			<?php if ($GLOBALS['gLoggedIn']) { ?>
            $("input:radio[name ='for_whom']").change(function () {
                const forWhom = $("input:radio[name ='for_whom']:checked").val();
                if (empty(forWhom)) {
                    $(".contact-info").each(function () {
                        $(this).find("input").prop("readonly", true).val($(this).find("input").data("user_value"));
                    });
                } else {
                    $(".contact-info").each(function () {
                        $(this).find("input").prop("readonly", false).val("");
                    });
                    $("#first_name").focus();
                }
            });
			<?php } ?>
            $(document).on("change", "#gift_card_number", function () {
                var thisElement = $(this);
                loadAjaxRequest("/retail-store-controller?ajax=true&url_action=check_gift_card&gift_card_number=" + encodeURIComponent($(this).val()), function (returnArray) {
                    if ("gift_card_information" in returnArray) {
                        $(".gift-card-information").addClass("info-message").html(returnArray['gift_card_information']);
                    }
                    if ("gift_card_error" in returnArray) {
                        $(".gift-card-information").addClass("error-message").html(returnArray['gift_card_error']);
                    }
                });
            });

            $("#account_id").data("swipe_string", "");
            $("#payment_method_id").data("swipe_string", "");

            $(document).on("click", "#add_another", function () {
                let fieldHtml = $("#fields_wrapper_template").html();
                let rowNumber = $("#fields_wrapper").find(".registration-row_number").last().val();
                if (empty(rowNumber)) {
                    rowNumber = 1;
                } else {
                    rowNumber++;
                }
                fieldHtml = fieldHtml.replace(new RegExp("%row_number%", 'g'), rowNumber);
                $("#fields_wrapper").append(fieldHtml);
                $("#first_name_" + rowNumber).focus();
                return false;
            });
            $(document).on("click", ".close-registration", function () {
                $(this).closest(".additional-registration-wrapper").remove();
            });

            $("#account_id,#payment_method_id").keypress(function (event) {
                const thisChar = String.fromCharCode(event.which);
                if (!empty($(this).data("swipe_string"))) {
                    if (event.which === 13) {
                        processMagneticData($(this).data("swipe_string"));
                        $(this).data("swipe_string", "");
                    } else {
                        $(this).data("swipe_string", $(this).data("swipe_string") + thisChar);
                    }
                    return false;
                } else {
                    if (thisChar === "%") {
                        $(this).data("swipe_string", "%");
                        setTimeout(function () {
                            if ($(this).data('swipe_string') === "%") {
                                $(this).data('swipe_string', "");
                            }
                        }, 3000);
                        return false;
                    } else {
                        return true;
                    }
                }
            });
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
            }).trigger("change");
            $("#billing_state_select").change(function () {
                $("#billing_state").val($(this).val());
            });
            $("#country_id").change(function () {
                if ($(this).val() === "1000") {
                    $("#_state_row").hide();
                    $("#_state_select_row").show();
                } else {
                    $("#_state_row").show();
                    $("#_state_select_row").hide();
                }
            }).trigger("change");
            $("#state_select").change(function () {
                $("#state").val($(this).val());
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
            }).trigger("change");
            $(".signature-palette").jSignature({ 'UndoButton': true, "height": 140 });
            $("#register_button").click(function () {
                if ($("#_edit_form").validationEngine('validate')) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=register", $("#_edit_form").serialize(), function (returnArray) {
                        if ("response" in returnArray) {
                            $("#register_fields").html(returnArray['response']);
                        }
                    });
                }
                return false;
            });
            $("#first_name").focus();
            $(".product-checkbox").click(function () {
                if ($(this).prop("checked")) {
                    $(this).closest("tr").find(".product-price").addClass("selected");
                } else {
                    $(this).closest("tr").find(".product-price").removeClass("selected");
                }
                calculatePrice();
            });
            $(".product-selector").change(function () {
                if (empty($(this).val())) {
                    $(this).closest("tr").find('.product-price').html("0.00").removeClass("selected");
                } else {
                    $(this).closest("tr").find('.product-price').html($(this).find("option:selected").data("price")).addClass("selected");
                }
                calculatePrice();
            });
            $(document).on("change", ".product-option-quantity", function () {
                const $productQuantity = $(this);
                const productId = $(this).closest(".product-row").find(".product-id").val();
                if (!empty($(this).val())) {
                    $(this).closest("tr").find(".product-price").addClass("selected");
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_product_price&quantity=" + $(this).val() + "&product_id=" + productId + "&event_id=" + $("#event_id").val(), function (returnArray) {
                        if ("sale_price" in returnArray) {
                            $productQuantity.closest(".product-row").find(".product-price").data("price", returnArray['sale_price']).html(RoundFixed(returnArray['sale_price'], 2));
                        }
                        calculatePrice();
                    });
                } else {
                    $(this).closest("tr").find(".product-price").removeClass("selected");
                    calculatePrice();
                }
            });
            $("#event_id").change(function () {
                if (!empty($(this).val())) {
                    document.location = "<?= $GLOBALS['gLinkUrl'] ?>?id=" + $(this).val();
                }
            });
            calculatePrice();
        </script>
		<?php
	}

	function javascript() {
		?>
        <script>
            function calculatePrice() {
                let totalCost = 0;
                $(".product-price.selected").each(function () {
                    let thisCost = parseFloat($(this).html().replace(/,/g, ""));
                    let quantity = 1;
                    if ($(this).closest(".product-row").find(".product-option-quantity").length > 0) {
                        quantity = $(this).closest(".product-row").find(".product-option-quantity").val();
                        if (empty(quantity)) {
                            quantity = 0;
                        }
                        thisCost = thisCost * quantity;
                    }
                    totalCost += thisCost;
                });
                $("#total_cost").val(totalCost);
                $("#total_cost_display").html(RoundFixed(totalCost, 2));
                if (totalCost > 0) {
                    $("#_billing_info_section").removeClass("hidden");
                } else {
                    $("#_billing_info_section").addClass("hidden");
                }
            }
        </script>
		<?php
	}

	function mainContent() {
		$merchantAccountId = $GLOBALS['gMerchantAccountId'];
		$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
		$capitalizedFields = array();
		if (getPreference("USE_FIELD_CAPITALIZATION")) {
			$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
			while ($row = getNextRow($resultSet)) {
				$capitalizedFields[] = $row['column_name'];
			}
		}
		echo $this->getPageData("content");
		?>
		<?php if ($GLOBALS['gLoggedIn']) { ?>
            <p><a href="/logout.php?url=<?= urlencode($_SERVER['REQUEST_URI']) ?>">If you are not <?= getUserDisplayName() ?>, click here</a></p>
		<?php } ?>
		<?php
		if (!empty($_GET['type']) && empty($this->iEventId)) {
			?>
            <div id="event_type_wrapper">
                <div class="form-line">
                    <label>Choose Event</label>
					<?php
					$resultSet = executeQuery("select * from events where tentative = 0 and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? and event_type_id in " .
						"(select event_type_id from event_types where event_type_code = ?) and start_date >= current_date order by start_date,description", $GLOBALS['gClientId'], strtoupper($_GET['type']));
					if ($resultSet['row_count'] > 0) {
						?>
                        <select id="event_id" name="event_id">
                            <option value="">[Select]</option>
							<?php
							while ($row = getNextRow($resultSet)) {
								?>
                                <option value="<?= $row['event_id'] ?>"><?= date("l, F j, Y", strtotime($row['start_date'])) . " - " . htmlText($row['description']) ?></option>
								<?php
							}
							?>
                        </select>
					<?php } else { ?>
                        <p>No Events Found</p>
					<?php } ?>
                </div>
            </div>
			<?php
			echo $this->getPageData("after_form_content");
			return true;
		}
		$eventRow = getRowFromId("events", "event_id", $this->iEventId);
		if (!empty($eventRow['attendees'])) {
			$resultSet = executeQuery("select count(*) from event_registrants where event_id = ?", $this->iEventId);
			$row = getNextRow($resultSet);
			$registrantCount = $row['count(*)'];
			if ($registrantCount > $eventRow['attendees']) {
				?>
                <p id="event_full">This event is full.</p>
				<?php
				echo $this->getPageData("after_form_content");
				return true;
			}
		}
		$firstName = $GLOBALS['gUserRow']['first_name'];
		$lastName = $GLOBALS['gUserRow']['last_name'];
		$emailAddress = $GLOBALS['gUserRow']['email_address'];
		$phoneNumber = Contact::getContactPhoneNumber($GLOBALS['gUserRow']['contact_id']);

		$eventProducts = array();
		if (!empty($eventRow['product_id'])) {
			$eventProducts[] = array("event_id" => $eventRow['event_id'], "product_id" => $eventRow['product_id'], "start_date" => "", "end_date" => "", "required" => 1, "price" => "");
		}
		$resultSet = executeQuery("select * from event_registration_products where (start_date is null or start_date <= current_date) and (end_date is null or end_date >= current_date) and " .
			"(product_id is not null or product_group_id is not null) and event_id = ?", $eventRow['event_id']);
		while ($row = getNextRow($resultSet)) {
			$eventProducts[] = $row;
		}

		?>
        <div id="register_form_wrapper">

            <form id="_edit_form">
                <input type="hidden" id="event_id" name="event_id" value="<?= $this->iEventId ?>">

                <div id="register_fields">

                    <h1><?= htmlText($eventRow['description']) ?></h1>
                    <p><?= date("m/d/Y", strtotime($eventRow['start_date'])) ?></p>
					<?php if (!empty($eventRow['detailed_description'])) { ?>
                        <p><?= makeHtml($eventRow['detailed_description']) ?></p>
					<?php } ?>

                    <hr>

                    <div id="fields_wrapper">
						<?php if ($GLOBALS['gLoggedIn']) { ?>
							<?php if (count($eventProducts) > 0 || empty($GLOBALS['gUserRow']['company_id'])) { ?>
                                <div class='form-line' id='_for_whom_row'>
                                    <label>Who is this registration for?</label>
                                    <input type='radio' name='for_whom' id='for_whom_myself' checked value=''><label class='checkbox-label' for='for_whom_myself'>Myself</label><br>
                                    <input type='radio' name='for_whom' id='for_whom_someone_else' value='other'><label class='checkbox-label' for='for_whom_someone_else'>Someone Else</label>
                                </div>
							<?php } ?>
						<?php } ?>

                        <h2>Registrant Information</h2>
						<?= createFormControl("contacts", "first_name", array("not_null" => true, "placeholder" => "First Name", "readonly" => $GLOBALS['gLoggedIn'], "initial_value" => $firstName, "data-user_value" => $firstName, "form_line_classes" => "contact-info", "force_validation_classes" => true)) ?>
						<?= createFormControl("contacts", "last_name", array("not_null" => true, "placeholder" => "Last Name", "readonly" => $GLOBALS['gLoggedIn'], "initial_value" => $lastName, "data-user_value" => $lastName, "form_line_classes" => "contact-info", "force_validation_classes" => true)) ?>
						<?php
						if ($this->getPageTextChunk("collect_address") && (!$GLOBALS['gLoggedIn'] || empty($GLOBALS['gUserRow']['address_1']) )) {
							echo createFormControl("contacts", "address_1", array("not_null" => true, "placeholder" => "Address", "initial_value" => $GLOBALS['gUserRow']['address_1'], "form_line_classes" => "contact-info"));
							echo createFormControl("contacts", "city", array("not_null" => true, "placeholder" => "City","initial_value" => $GLOBALS['gUserRow']['city'], "form_line_classes" => "contact-info"));
                            ?>
                            <div class="form-line" id="_state_select_row">
                                <label for="state_select" class="">State</label>
                                <select tabindex="10" id="state_select" name="state_select" class="validate[required]" data-conditional-required="$('#country_id').val() == 1000">
                                    <option value="">[Select]</option>
                                    <?php
                                    foreach (getStateArray() as $stateCode => $state) {
                                        ?>
                                        <option value="<?= $stateCode ?>" <?= $stateCode == $GLOBALS['gUserRow']['state'] ? "selected" : "" ?>><?= htmlText($state) ?></option>
                                        <?php
                                    }
                                    ?>
                                </select>
                                <div class='clear-div'></div>
                            </div>
                            <?php
                            echo createFormControl("contacts", "state", array("not_null" => true, "placeholder" => "State","initial_value" => $GLOBALS['gUserRow']['state'],"form_line_classes" => "contact-info"));
							echo createFormControl("contacts", "postal_code", array("not_null" => true, "placeholder" => "Postal Code", "initial_value" => $GLOBALS['gUserRow']['postal_code'], "form_line_classes" => "contact-info"));
                            echo createFormControl("contacts", "country_id", array("not_null" => true, "initial_value" => "1000"));
						}
						?>
						<?= createFormControl("contacts", "email_address", array("not_null" => true, "placeholder" => "Email", "readonly" => $GLOBALS['gLoggedIn'], "initial_value" => $emailAddress, "data-user_value" => $emailAddress, "form_line_classes" => "contact-info", "force_validation_classes" => true)) ?>
						<?= createFormControl("phone_numbers", "phone_number", array("not_null" => false, "placeholder" => "Phone", "data_format" => "phone", "readonly" => $GLOBALS['gLoggedIn'], "initial_value" => $phoneNumber, "data-user_value" => $phoneNumber, "form_line_classes" => "contact-info", "force_validation_classes" => true)) ?>

						<?php
						$customFields = CustomField::getCustomFields("event_registrations");
						$resultSet = executeQuery("select * from event_registration_custom_fields where event_id = ? order by sequence_number", $this->iEventId);
						while ($row = getNextRow($resultSet)) {
							if (!array_key_exists($row['custom_field_id'], $customFields)) {
								continue;
							}
							$customField = CustomField::getCustomField($row['custom_field_id']);
							echo $customField->getControl();
						}
						?>
                    </div>

					<?php
					if (count($eventProducts) == 0 && !empty($GLOBALS['gUserRow']['company_id'])) {
						$companyContactRow = Contact::getContact(getFieldFromId("contact_id", "companies", "company_id", $GLOBALS['gUserRow']['company_id']));
						?>
                        <p>
                            <button tabindex="10" id="add_another">Add another person<br>from <?= $companyContactRow['business_name'] ?></button>
                        </p>

                        <div id="fields_wrapper_template" class='hidden'>
                            <div class='additional-registration-wrapper'>
                                <span class='close-registration fas fa-times'></span>
                                <h3>Additional Registration</h3>
                                <input class='registration-row_number' type='hidden' id='additional_registration_%row_number%' name='additional_registration_%row_number%' value='%row_number%'>
								<?= createFormControl("contacts", "first_name", array("column_name" => "first_name_%row_number%", "not_null" => true, "placeholder" => "First Name")) ?>
								<?= createFormControl("contacts", "last_name", array("column_name" => "last_name_%row_number%", "not_null" => true, "placeholder" => "Last Name")) ?>
								<?= createFormControl("contacts", "email_address", array("column_name" => "email_address_%row_number%", "not_null" => true, "placeholder" => "Email")) ?>
								<?php
								$customFields = CustomField::getCustomFields("event_registrations");
								$resultSet = executeQuery("select * from event_registration_custom_fields where event_id = ? order by sequence_number", $this->iEventId);
								while ($row = getNextRow($resultSet)) {
									if (!array_key_exists($row['custom_field_id'], $customFields)) {
										continue;
									}
									$customField = CustomField::getCustomField($row['custom_field_id'], "custom_field_id_" . $row['custom_field_id'] . "_%row_number%");
									echo $customField->getControl();
								}
								?>
                            </div>
                        </div>
						<?php
					}

					?>
					<?php if (count($eventProducts) > 0) { ?>
                        <div id="payment_wrapper">
                            <h2>Event Options</h2>
                            <table class="grid-table" id="products_table">
                                <tr>
                                    <th>Description</th>
                                    <th>Details</th>
                                    <th>Select/Qty</th>
                                    <th>Price</th>
                                </tr>
								<?php
								$rowNumber = 0;
								$productCatalog = new ProductCatalog();
								$zaiusUseUpc = getPreference("ZAIUS_USE_UPC");
								$validPaymentMethods = array();
								foreach ($eventProducts as $row) {
									$productUnavailable = false;
									$maximumOrderQuantity = "";
									if (!empty($row['maximum_quantity'])) {
										$countSet = executeQuery("select sum(quantity) from order_items where " .
											(!empty($row['product_group_id']) ? "product_id in (select product_id from product_group_variants where product_group_id = " . $row['product_group_id'] . ")" : "product_id = " . $row['product_id']) .
											" and order_id in (select order_id from event_registrants where event_id = ?)", $eventRow['event_id']);
										$usedCount = 0;
										if ($countRow = getNextRow($countSet)) {
											if (!empty($countRow['sum(quantity)'])) {
												$usedCount = $countRow['sum(quantity)'];
											}
										}
										if ($usedCount >= $row['maximum_quantity']) {
											$productUnavailable = true;
										}
										$maximumOrderQuantity = $row['maximum_quantity'] - $usedCount;
									}
									$rowNumber++;
									$productArray = array();
									$description = "";
									$detailedDescription = "";
									$useProductGroup = true;
									if (!empty($row['product_group_id'])) {
										$useProductGroup = true;
										$description = getFieldFromId("description", "product_groups", "product_group_id", $row['product_group_id']);
										$detailedDescription = getFieldFromId("detailed_description", "product_groups", "product_group_id", $row['product_group_id']);
										$productSet = executeQuery("select * from product_group_variants join products using (product_id) where inactive = 0 and product_group_id = ? order by sort_order,description", $row['product_group_id']);
										while ($productRow = getNextRow($productSet)) {
											$productArray[] = $productRow;
										}
										$maximumOrderQuantity = 1;
									} else if (!empty($row['product_id'])) {
										$useProductGroup = false;
										$thisProductRow = ProductCatalog::getCachedProductRow($row['product_id'], array("inactive" => "0"));
										$productArray[] = $thisProductRow;
										$description = $productArray[0]['description'];
										$detailedDescription = $productArray[0]['detailed_description'];
										if (empty($thisProductRow['cart_maximum'])) {
											$maximumOrderQuantity = 1;
										} else {
											$maximumOrderQuantity = (empty($maximumOrderQuantity) ? $thisProductRow['cart_maximum'] : min($maximumOrderQuantity, $thisProductRow['cart_maximum']));
										}
									}
									if (empty($productArray)) {
										continue;
									}
									foreach ($productArray as $index => $thisProduct) {
										if (strlen($row['price']) == 0) {
											$salePriceInfo = $productCatalog->getProductSalePrice($thisProduct['product_id'], array("product_information" => $thisProduct, "no_stored_prices" => true));
											$productArray[$index]['sale_price'] = $salePriceInfo['sale_price'];
										} else {
											$productArray[$index]['sale_price'] = $row['price'];
										}
									}
									if ($productUnavailable) {
										$row['required'] = false;
									}
									?>
                                    <tr class='product-row' data-row_number="<?= $rowNumber ?>">
                                        <td class="product-description">
											<?php if (!$useProductGroup) { ?>
                                                <input class='product-id' type="hidden" id="product_id_<?= $rowNumber ?>" name="product_id_<?= $rowNumber ?>" value="<?= $productArray[0]['product_id'] ?>"><?= htmlText($description) ?>
											<?php } ?>
                                        </td>
                                        <td class="product-detailed-description"><?= ($eventRow['product_id'] == $row['product_id'] ? "" : makeHtml($detailedDescription)) ?></td>
										<?php
										if ($useProductGroup) {
											?>
                                            <td class="align-center"><select id="product_id_<?= $rowNumber ?>" name="product_id_<?= $rowNumber ?>" class="product-selector <?= (empty($row['required']) ? "" : "validate[required]") ?>">
                                                    <option value="">[<?= ($productUnavailable ? "Not Available" : "Select") ?>]</option>
													<?php
													if (!$productUnavailable) {
														foreach ($productArray as $thisProduct) {
															?>
                                                            <option data-price="<?= number_format($thisProduct['sale_price'], 2, ".", ",") ?>" value="<?= $thisProduct['product_id'] ?>"><?= htmlText($thisProduct['description']) ?></option>
															<?php
														}
													}
													?>
                                                </select></td>
											<?php
										} else {
											if ($productUnavailable || $maximumOrderQuantity <= 0) {
												?>
                                                <td class="align-center">Not Available <?= $productUnavailable . ":" . $maximumOrderQuantity ?></td>
												<?php
											} else {
												if ($maximumOrderQuantity > 1) {
													?>
                                                    <td class="align-center"><input tabindex="10" type="text" size="4" class="product-option-quantity align-right validate[required,custom[integer],max[<?= $maximumOrderQuantity ?>],min[<?= (empty($row['required']) ? "0" : "1") ?>]]" value="" id="product_quantity_<?= $rowNumber ?>" name="product_quantity_<?= $rowNumber ?>"></td>
													<?php
												} else {
													?>
                                                    <td class="align-center"><?php if ($row['required']) { ?><input type='hidden' value='1' name='product_quantity_<?= $rowNumber ?>' id='product_quantity_<?= $rowNumber ?>'><?php } ?><input tabindex="10" type="checkbox" class="product-checkbox" value="1" <?php if (empty($row['required'])) { ?>id="product_quantity_<?= $rowNumber ?>" name="product_quantity_<?= $rowNumber ?>"<?php } ?><?= (empty($row['required']) ? "" : " checked disabled='disabled'") ?>></td>
													<?php
												}
											}
										}
										?>
                                        <td class="align-right product-price<?= (empty($row['required']) ? "" : " selected") ?>" data-price="<?= number_format($productArray[0]['sale_price'], 2, ".", ",") ?>"><?= ($useProductGroup ? "0.00" : number_format($productArray[0]['sale_price'], 2, ".", ",")) ?></td>
                                    </tr>
									<?php
									//  add code for sendAnalyticsEvent here
//                                    $zaiusKey = (!empty($zaiusUseUpc) && !empty($productArray[0]['upc_code'])) ? $productArray[0]['upc_code'] : $productArray[0]['product_id'];
//                                    $category = $this->iProductRow['primary_category'];
									$paymentMethodSet = executeQuery("select product_id,group_concat(payment_method_id) from product_payment_methods where product_id = ? group by product_id", $row['product_id']);
									if ($paymentMethodRow = getNextRow($paymentMethodSet)) {
										$paymentMethods = array_filter(explode(",", $paymentMethodRow['group_concat(payment_method_id)']));
										if (!empty($paymentMethods)) {
											if (empty($validPaymentMethods)) {
												$validPaymentMethods = $paymentMethods;
											} else {
												$validPaymentMethods = array_intersect($validPaymentMethods, $paymentMethods);
											}
										}
									}
								}
								?>
                                <tr>
                                    <td colspan="3" class="highlighted-text" id="total_cost_label">Total<input type="hidden" id="total_cost" name="total_cost" value=""></td>
                                    <td class="align-right" id="total_cost_display"></td>
                                </tr>
                            </table>

                            <div id="_billing_info_section" class="hidden">
                                <h2>Billing Information</h2>

								<?php
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
								<?php } ?>

                                <div id="_new_account">

                                    <div id="_billing_address">

                                        <div class="form-line" id="_billing_first_name_row">
                                            <label for="billing_first_name" class="required-label">First Name</label>
                                            <input tabindex="10" type="text" class="validate[required]<?= (in_array("first_name", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="25" maxlength="25" id="billing_first_name" name="billing_first_name" placeholder="First Name" value="<?= htmlText($GLOBALS['gUserRow']['first_name']) ?>">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_last_name_row">
                                            <label for="billing_last_name" class="required-label">Last Name</label>
                                            <input tabindex="10" type="text" class="validate[required]<?= (in_array("last_name", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="35" id="billing_last_name" name="billing_last_name" placeholder="Last Name" value="<?= htmlText($GLOBALS['gUserRow']['last_name']) ?>">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_business_name_row">
                                            <label for="billing_business_name">Business Name</label>
                                            <input tabindex="10" type="text" class="<?= (in_array("business_name", $capitalizedFields) ? "validate[] capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="35" id="billing_business_name" name="billing_business_name" placeholder="Business Name" value="<?= htmlText($GLOBALS['gUserRow']['business_name']) ?>">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_address_1_row">
                                            <label for="billing_address_1" class="required-label">Street</label>
                                            <input tabindex="10" type="text" class="validate[required]<?= (in_array("address_1", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="60" id="billing_address_1" name="billing_address_1" placeholder="Address" value="">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_address_2_row">
                                            <label for="billing_address_2" class=""></label>
                                            <input tabindex="10" type="text" class="<?= (in_array("address_2", $capitalizedFields) ? "validate[] capitalize" : "") ?>" size="30" maxlength="60" id="billing_address_2" name="billing_address_2" value="">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_city_row">
                                            <label for="billing_city" class="required-label">City</label>
                                            <input tabindex="10" type="text" class="validate[required]<?= (in_array("city", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="60" id="billing_city" name="billing_city" placeholder="City" value="">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_state_row">
                                            <label for="billing_state" class="">State</label>
                                            <input tabindex="10" type="text" class="validate[required]<?= (in_array("state", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000" size="10" maxlength="30" id="billing_state" name="billing_state" placeholder="State" value="">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_state_select_row">
                                            <label for="billing_state_select" class="">State</label>
                                            <select tabindex="10" id="billing_state_select" name="billing_state_select" class="validate[required]" data-conditional-required="$('#billing_country_id').val() == 1000">
                                                <option value="">[Select]</option>
												<?php
												foreach (getStateArray() as $stateCode => $state) {
													?>
                                                    <option value="<?= $stateCode ?>"><?= htmlText($state) ?></option>
													<?php
												}
												?>
                                            </select>
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_postal_code_row">
                                            <label for="billing_postal_code" class="">Postal Code</label>
                                            <input tabindex="10" type="text" class="validate[required]" size="10" maxlength="10" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000" id="billing_postal_code" name="billing_postal_code" placeholder="Postal Code" value="">
                                            <div class='clear-div'></div>
                                        </div>

                                        <div class="form-line" id="_billing_country_id_row">
                                            <label for="billing_country_id" class="">Country</label>
                                            <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="billing_country_id" name="billing_country_id">
												<?php
												foreach (getCountryArray(true) as $countryId => $countryName) {
													?>
                                                    <option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
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
													"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where " .
													($GLOBALS['gLoggedIn'] ? "" : "requires_user = 0 and ") .
													(empty($validPaymentMethods) ? "" : "payment_method_id in (" . implode(",", $validPaymentMethods) . ") and ") .
													"(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
													(empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") . ") and " .
													"inactive = 0 and internal_use_only = 0 and client_id = ? and (payment_method_type_id is null or payment_method_type_id in " .
													"(select payment_method_type_id from payment_method_types where inactive = 0 and internal_use_only = 0 and " .
													"client_id = ?)) order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gClientId']);
												while ($row = getNextRow($resultSet)) {
													if (empty($row['image_id'])) {
														$paymentMethodRow = getRowFromId("payment_methods", "payment_method_code", $row['payment_method_code'], "client_id = ?", $GLOBALS['gDefaultClientId']);
														$row['image_id'] = $paymentMethodRow['image_id'];
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

                                        <div class="payment-method-fields" id="payment_method_gift_card">
                                            <div class="form-line" id="_gift_card_number_row">
                                                <label for="gift_card_number" class="">Card Number</label>
                                                <input tabindex="10" type="text" class="validate[required]"
                                                       data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && !$('#payment_method_gift_card').hasClass('hidden')"
                                                       size="20" maxlength="30" id="gift_card_number"
                                                       name="gift_card_number" placeholder="Card Number" value="">
                                                <div class='clear-div'></div>
                                            </div>
                                            <p class="gift-card-information"></p>
                                        </div> <!-- payment_method_gift_card -->

										<?php if ($GLOBALS['gLoggedIn'] && !empty($eCommerce) && $eCommerce->hasCustomerDatabase()) { ?>
                                            <div class="form-line checkbox-input" id="_save_account_row">
                                                <label class=""></label>
                                                <input tabindex="10" type="checkbox" id="save_account" name="save_account" value="1"><label class="checkbox-label" for="save_account">Save Account</label>
                                                <div class='clear-div'></div>
                                            </div>

                                            <div class="form-line" id="_account_label_row">
                                                <label for="account_label" class="">Account Nickname</label>
                                                <span class="help-label">for future reference, if saved</span>
                                                <input tabindex="10" type="text" class="" size="20" maxlength="30" id="account_label" name="account_label" placeholder="Account Label" value="">
                                                <div class='clear-div'></div>
                                            </div>
										<?php } ?>

                                    </div> <!-- payment_information -->
                                </div> <!-- new_account -->
                            </div> <!-- billing_info_section -->

                        </div> <!-- payment_wrapper -->
					<?php } ?>

                    <p class="error-message" id="error_message"></p>
                    <p>
                        <button tabindex="10" id="register_button">Register</button>
                    </p>
                </div> <!-- register_fields -->

            </form>
        </div> <!-- register_form_wrapper -->

		<?php
		echo $this->getPageData("after_form_content");
		return true;
	}

	function internalCSS() {
		?>
        <style>
            .additional-registration-wrapper {
                position: relative;
                display: table;
                padding: 20px;
                border: 1px solid rgb(200, 200, 200);
                margin-bottom: 20px;

            .close-registration {
                position: absolute;
                top: 10px;
                right: 10px;
                font-size: 1.5rem;
                cursor: pointer;
            }

            }
            #products_table {
                max-width: 100%;
                margin-bottom: 40px;
            }

            #products_table td {
                height: 40px;
                vertical-align: middle;
                padding: 0 20px;
            }

            .product-price {
                opacity: .25;
            }

            .product-price.selected {
                opacity: 1;
            }

            .signature-palette-parent {
                color: rgb(10, 30, 150);
                background-color: rgb(180, 180, 180);
                padding: 20px;
                width: 600px;
                max-width: 100%;
                height: 180px;
                position: relative;
            }

            .signature-palette {
                border: 2px dotted black;
                background-color: rgb(220, 220, 220);
                height: 100%;
                width: 100%;
                position: relative;
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

            #_bank_name_row {
                display: none;
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

	function jqueryTemplates() {
		$customFields = CustomField::getCustomFields("event_registrations");
		$resultSet = executeQuery("select * from event_registration_custom_fields where event_id = ? order by sequence_number", $this->iEventId);
		while ($row = getNextRow($resultSet)) {
			if (!array_key_exists($row['custom_field_id'], $customFields)) {
				continue;
			}
			$customField = CustomField::getCustomField($row['custom_field_id']);
			echo $customField->getTemplate();
		}
	}
}

$pageObject = new EventRegistrationPage("event_registrants");
$pageObject->displayPage();
