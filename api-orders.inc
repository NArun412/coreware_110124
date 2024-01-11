<?php

# Ordering API Methods

switch ($_POST['action']) {
	case "get_default_merchant_account":
		$resultSet = executeQuery("select * from merchant_accounts join merchant_services using (merchant_service_id) where client_id = ? and merchant_account_code = 'DEFAULT'", $GLOBALS['gClientId']);
		if ($row = getNextRow($resultSet)) {
			$returnArray['merchant_account'] = $row;
		}
		break;

	case "get_payment_accounts":
		$accounts = array();
		// intentionally including inactive accounts that would be saved because of RETAIL_STORE_ALWAYS_TOKENIZE_PAYMENT_METHOD
		$resultSet = executeQuery("select * from accounts where contact_id = ?", $_POST['contact_id']);
		while ($row = getNextRow($resultSet)) {
			$accounts[] = $row;
		}
		$returnArray['accounts'] = $accounts;
		break;

	case "create_payment_account":
		$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_id", $_POST['payment_method_id']);
		if (empty($paymentMethodId)) {
			$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", makeCode($_POST['payment_method_code']));
		}
		if (empty($paymentMethodId)) {
			$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", "CREDIT_CARD");
			if (empty($paymentMethodId)) {
				$insertSet = executeQuery("insert into payment_methods (client_id,payment_method_code,description,payment_method_type_id,internal_use_only) values (?,?,?,?,1)",
					$GLOBALS['gClientId'], "CREDIT_CARD", "Credit Card", getFieldFromId("payment_method_type_id", "payment_method_types", "payment_method_type_code", "CREDIT_CARD"));
				$paymentMethodId = $insertSet['insert_id'];
			}
		}
		if (empty($paymentMethodId)) {
			$returnArray['error_message'] = "Invalid Payment Method";
			break;
		}
		$paymentMethodTypeId = getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id", $paymentMethodId);
		$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", $paymentMethodTypeId);
		$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id']);
		if (empty($contactId)) {
			$returnArray['error_message'] = "Contact not found";
			break;
		}
		$contactRow = Contact::getContact($contactId);
		if (empty($_POST['country_id']) && !empty($_POST['country_code'])) {
			$_POST['country_id'] = getFieldFromId("country_id", "countries", "country_code", $_POST['country_code']);
		}
		if (empty($_POST['country_id'])) {
			$_POST['country_id'] = 1000;
		}

		$addressId = $_POST['address_id'];
		$addressRow = false;
		if (empty($addressId)) {
			if (!empty($_POST['address_1']) || !empty($_POST['postal_code'])) {
				$addressId = getFieldFromId("address_id", "addresses", "contact_id", $contactId, "address_1 is not null and " .
					"city is not null and address_label <=> ? and address_1 <=> ? and address_2 <=> ? and city <=> ? and state <=> ? and postal_code <=> ? and country_id = ?",
					$_POST['address_label'], $_POST['address_1'], $_POST['address_2'], $_POST['city'], $_POST['state'], $_POST['postal_code'], $_POST['country_id']);
				if (empty($addressId)) {
					$insertSet = executeQuery("insert into addresses (contact_id,address_label,address_1,address_2,city,state,postal_code,country_id) values (?,?,?,?,?, ?,?,?)",
						$contactId, (empty($_POST['address_label']) ? "Billing Address" : $_POST['address_label']), $_POST['address_1'], $_POST['address_2'], $_POST['city'], $_POST['state'], $_POST['postal_code'], $_POST['country_id']);
					if (!empty($insertSet['sql_error'])) {
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						$returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
						ajaxResponse($returnArray);
						break;
					}
					$addressId = $insertSet['insert_id'];
				}
				$addressRow = getRowFromId("addresses", "address_id", $addressId);
			}
		} else {
			$addressRow = getRowFromId("addresses", "address_id", $_POST['address_id']);
			if (empty($addressRow)) {
				$returnArray['error_message'] = "Address not found";
				break;
			}
		}
		if (empty($addressRow)) {
			$addressRow = $contactRow;
		}

		$merchantAccountId = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_id", $_POST['merchant_account_id'],
			"client_id = ?", $GLOBALS['gClientId']);
		if (empty($merchantAccountId) && !empty($_POST['merchant_account_id'])) {
			$returnArray['error_message'] = "Invalid merchant_account_id";
			break;
		}
		if (empty($merchantAccountId) && !empty($_POST['source_code'])) {
			$merchantAccountId = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", $_POST['source_code'],
				"client_id = ?", $GLOBALS['gClientId']);
		}
		if (empty($merchantAccountId)) {
			$merchantAccountId = $GLOBALS['gMerchantAccountId'];
		}
		$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
		if (empty($eCommerce) || !$eCommerce->hasCustomerDatabase()) {
			$returnArray['error_message'] = "Unable to get merchant account";
			break;
		}

		if (empty($_POST['expiration_date']) && !empty($_POST['expiration_month'])) {
			$_POST['expiration_date'] = date("Y-m-d", strtotime($_POST['expiration_month'] . "/01/" . $_POST['expiration_year']));
		}

		$isBankAccount = ($paymentMethodTypeCode == "BANK_ACCOUNT");
		$last4digits = substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4);
		if (empty($last4digits) && !empty($_POST['merchant_identifier']) && !empty($_POST['account_token'])) {
			if ($eCommerce->getCustomerPaymentProfile($_POST)) {
				$response = $eCommerce->getResponse();
				$numberField = $isBankAccount ? "account_number" : "card_number";
				if (array_key_exists($numberField, $response) && !empty($response[$numberField])) {
					$last4digits = substr($response[$numberField], -4);
				}
			}
		}
		$last4digits = $last4digits ?: substr($_POST['account_token'], -4);

		$accountLabel = trim($_POST['account_label']);
		if (empty($accountLabel)) {
			$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $paymentMethodId) . " - " . $last4digits;
		}
		$fullName = $contactRow['first_name'] . " " . $contactRow['last_name'] . (empty($contactRow['business_name']) ? "" : ", " . $contactRow['business_name']);

		if (!empty($_POST['account_token']) && (empty($_POST['merchant_identifier']) && $eCommerce->requiresCustomerToken())) {
			$returnArray['error_message'] = "merchant_identifier is required if account_token is supplied";
			break;
		}

		$GLOBALS['gPrimaryDatabase']->startTransaction();

		$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name,address_id," .
			"account_number,expiration_date,merchant_account_id,merchant_identifier,account_token) values (?,?,?,?,?, ?,?,?,?,?)", $contactId, $accountLabel, $paymentMethodId,
			$fullName, $addressId, "XXXX-" . $last4digits, (empty($_POST['expiration_date']) ? "" : date("Y-m-d", strtotime($_POST['expiration_date']))),
			$merchantAccountId, $_POST['merchant_identifier'], $_POST['account_token']);
		if (!empty($resultSet['sql_error'])) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
			break;
		}
		$accountId = $resultSet['insert_id'];
		$returnArray['account_id'] = $accountId;

		if (!empty($_POST['tokenize_account'])) {
			$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $contactId, "merchant_account_id = ?", $merchantAccountId);
			if (empty($merchantIdentifier)) {
				$success = $eCommerce->createCustomerProfile(array("contact_id" => $contactId, "first_name" => $contactRow['first_name'],
					"last_name" => $contactRow['last_name'], "business_name" => $contactRow['business_name'], "address_1" => $addressRow['address_1'], "city" => $addressRow['city'],
					"state" => $addressRow['state'], "postal_code" => $addressRow['postal_code'], "email_address" => $contactRow['email_address']));
				$response = $eCommerce->getResponse();
				if ($success) {
					$merchantIdentifier = $response['merchant_identifier'];
				} else {
					$returnArray['error_message'] = "Unable to create the merchant profile for contact ID " . $contactId;
					break;
				}
			}

			if (!in_array($paymentMethodTypeCode, array("CREDIT_CARD", "BANK_ACCOUNT"))) {
				$returnArray['error_message'] = "Can only tokenize credit cards & bank accounts";
				break;
			}

			$paymentArray = array("contact_id" => $contactId, "account_id" => $accountId, "merchant_identifier" => $merchantIdentifier,
				"first_name" => $contactRow['first_name'], "last_name" => $contactRow['last_name'], "business_name" => $contactRow['business_name'],
				"address_1" => $addressRow['address_1'], "city" => $addressRow['city'], "state" => $addressRow['state'],
				"postal_code" => $addressRow['postal_code'], "country_id" => $addressRow['country_id']);
			if ($isBankAccount) {
				$paymentArray['bank_routing_number'] = $_POST['routing_number'];
				$paymentArray['bank_account_number'] = $_POST['bank_account_number'];
				$paymentArray['bank_account_type'] = str_replace(" ", "", lcfirst(ucwords(strtolower(str_replace("_", " ", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id']))))));
			} else {
				$paymentArray['card_number'] = $_POST['account_number'];
				$paymentArray['expiration_date'] = date("m/d/Y", strtotime($_POST['expiration_date']));
				$paymentArray['card_code'] = $_POST['cvv_code'];
			}
			$success = $eCommerce->createCustomerPaymentProfile($paymentArray);
			$response = $eCommerce->getResponse();
			if ($success) {
				$returnArray['account_token'] = $accountToken = $response['account_token'];
				$returnArray['merchant_identifier'] = $merchantIdentifier;
			} else {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				$returnArray['error_message'] = "Unable to create payment account.";
				break;
			}
		}
		$GLOBALS['gPrimaryDatabase']->commitTransaction();
		break;

	case "get_contact_addresses":
		$addresses = array();
		$resultSet = executeQuery("select * from addresses where contact_id = ?", $_POST['contact_id']);
		while ($row = getNextRow($resultSet)) {
			$addresses[] = $row;
		}
		$returnArray['addresses'] = $addresses;
		break;

	case "get_tax_rate":
		$returnArray['tax_rate'] = Order::getTaxRate($_POST['location_id']);
		break;

	case "get_order_methods":
		$orderMethods = array();
		$resultSet = executeReadQuery("select * from order_methods where client_id = ? order by sort_order,description");
		while ($row = getNextRow($resultSet)) {
			$orderMethods[$row['order_method_id']] = $row['description'];
		}
		$returnArray['order_methods'] = $orderMethods;
		break;

	case "get_payment_methods":
		$paymentMethods = array();
		$resultSet = executeReadQuery("select * from payment_methods where client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
		while ($row = getNextRow($resultSet)) {
			$row['payment_method_type_code'] = getReadFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", $row['payment_method_type_id']);
			$paymentMethods[$row['payment_method_id']] = $row;
		}
		$returnArray['payment_methods'] = $paymentMethods;
		break;

    case "create_promotion":
        $discountAmount = floatval($_POST['discount_amount']);
        $discountPercent = floatval($_POST['discount_percent']);
        if(empty($discountPercent) && empty($discountAmount)) {
            $returnArray['error_message'] = "Discount amount or Discount percent is required";
            break;
        }
        $promotionCode = makeCode($_POST['promotion_code'], ['allow_dash'=>true]);
        $existingPromotionId = getFieldFromId("promotion_id", "promotions", "promotion_code", $promotionCode);
        if(!empty($existingPromotionId)) {
            $returnArray['error_message'] = "Promotion code already exists";
            break;
        }
        $_POST['promotion_code'] = $promotionCode ?: getRandomString(20, ['uppercase'=>true]);
        if(!empty($_POST['expiration_date'])) {
            $_POST['expiration_date'] = date("Y-m-d", strtotime($_POST['expiration_date']));
        } elseif(!empty($_POST['expiration_days']) && is_numeric($_POST['expiration_days'])) {
            $_POST['expiration_date'] = date ("Y-m-d", strtotime("+{$_POST['expiration_days']} days"));
        }
        $_POST['start_date'] = $_POST['start_date'] ?: date("Y-m-d");
        $userId = null;
        if(!empty($_POST['email_address'])) {
            $emailCount = getFieldFromId("count(*)", "contacts", "email_address", $_POST['email_address'], "client_id = ?", $GLOBALS['gClientId']);
            if($emailCount == 1) {
                $userId = getFieldFromId("user_id", "users", "contact_id", getFieldFromId("contact_id", "contacts", "email_address", $_POST['email_address']));
                if(empty($userId)) {
                    $returnArray['error_message'] = "User with email address {$_POST['email_address']} not found";
                    break;
                }
                $_POST['user_id'] = $userId;
            } else {
                $returnArray['error_message'] = "Email Address can not be matched to a single user.";
                break;
            }
        }
        $_POST['description'] = $_POST['description'] ?: "API generated promotion" . (empty($userId) ? "" : " for " . Contact::getUserDisplayName($userId));
        $_POST['client_id'] = $GLOBALS['gClientId'];
        $validFields = array("client_id", "promotion_code", "description", "discount_amount", "discount_percent", "minimum_amount", "start_date", "expiration_date", "maximum_usages", "maximum_per_email", "user_id");
        $nameValues = array_intersect_key($_POST, array_flip($validFields));
        $promotionsTable = new DataTable("promotions");
        $promotionsTable->setSaveOnlyPresent(true);
        if(!$promotionsTable->saveRecord(array("name_values" => $nameValues))) {
            $returnArray['error_message'] = $promotionsTable->getErrorMessage();
            break;
        }
        if($_POST['return_raw_values']) {
            echo $nameValues['promotion_code'];
            exit;
        }
        $returnArray['promotion_code'] = $nameValues['promotion_code'];
        break;

    case "create_one_time_use_promotion_code":
        $promotionCode = makeCode($_POST['promotion_code'], ['allow_dash'=>true]);
        $existingPromotionId = getFieldFromId("promotion_id", "promotions", "promotion_code", $promotionCode);
        if(empty($existingPromotionId)) {
            $returnArray['error_message'] = "Promotion with code $promotionCode not found.";
            break;
        }
        do {
            $oneTimeUseCode = getRandomString(20, ['uppercase'=>true]);
            $existingCodeId = getFieldFromId("promotion_id", "promotions", "promotion_code", $oneTimeUseCode);
            $existingCodeId = $existingCodeId ?: getFieldFromId("promotion_id", "one_time_use_promotion_codes", "promotion_code", $oneTimeUseCode);
        } while(!empty($existingCodeId));

        $resultSet = executeQuery("insert into one_time_use_promotion_codes (client_id, promotion_id,promotion_code) values (?,?,?)", $GLOBALS['gClientId'], $existingPromotionId, $oneTimeUseCode);
        if(!empty($resultSet['sql_error'])) {
            $returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
            break;
        }
        $returnArray['promotion_code'] = $oneTimeUseCode;
        break;

    case "update_order_status":
		if (!empty($_POST['purchase_order_number'])) {
			$resultSet = executeQuery("select * from orders where purchase_order_number = ? and client_id = ?", $_POST['purchase_order_number'], $GLOBALS['gClientId']);
		} else {
			if (!empty($_POST['order_number'])) {
				$resultSet = executeQuery("select * from orders where order_number = ? and client_id = ?", $_POST['order_number'], $GLOBALS['gClientId']);
			} else {
				if (!empty($_POST['order_id'])) {
					$resultSet = executeQuery("select * from orders where order_id = ? and client_id = ?", $_POST['order_id'], $GLOBALS['gClientId']);
				}
			}
		}
		if ($row = getNextRow($resultSet)) {
			$orderId = $row['order_id'];
		} else {
			$returnArray['error_message'] = "Order not found";
			break;
		}
		$orderStatusId = getFieldFromId('order_status_id', 'order_status', 'order_status_code', $_POST['order_status_code']);
        if (empty($orderStatusId)) {
            $orderStatusId = getFieldFromId('order_status_id', 'order_status', 'description', $_POST['order_status_code']);
        }
        if (empty($orderStatusId)) {
			$returnArray['error_message'] = "Invalid Order Status Code";
			break;
		}
		Order::updateOrderStatus($orderId, $orderStatusId);
		if (!empty($_POST['external_identifier'])) {
			$dataTable = new DataTable("orders");
			$dataTable->setSaveOnlyPresent(true);
			$dataTable->saveRecord(array("name_values" => array("external_identifier" => $_POST['external_identifier']), "primary_id" => $orderId));
		}
		break;

	case "get_order_ids":
		$whereStatement = "client_id = ? and deleted = 0";
		$parameters = array($GLOBALS['gClientId']);
		if (!empty($_POST['minimum_order_id'])) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "order_id >= ?";
			$parameters[] = $_POST['minimum_order_id'];
		}
		if (!empty($_POST['maximum_order_id'])) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "order_id <= ?";
			$parameters[] = $_POST['maximum_order_id'];
		}
		if (!empty($_POST['order_status_id'])) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "order_status_id = ?";
			$parameters[] = $_POST['order_status_id'];
		}
		if (!empty($_POST['order_status_code'])) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "order_status_id in(select order_status_id from order_status where order_status_code = ?)";
			$parameters[] = $_POST['order_status_code'];
		}
		$shippingMethodId = getFieldFromId("shipping_method_id", "shipping_methods", "shipping_method_id", $_POST['shipping_method_id']);
		if (empty($shippingMethodId) && !empty($_POST['shipping_method_code'])) {
			$shippingMethodId = getFieldFromId("shipping_method_id", "shipping_methods", "shipping_method_code", $_POST['shipping_method_code']);
		}
		if (!empty($shippingMethodId)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "shipping_method_id = ?";
			$parameters[] = $shippingMethodId;
		} else {
			$locationId = $_POST['location_id'];
			if (empty($locationId) && !empty($_POST['location_code'])) {
				$locationId = getFieldFromId("location_id", "locations", "location_code", makeCode($_POST['location_code']));
			}
			if (!empty($locationId)) {
				$shippingMethodId = getFieldFromId("shipping_method_id", "shipping_methods", "location_id", $locationId);
			}
			if (!empty($shippingMethodId)) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . "shipping_method_id = ?";
				$parameters[] = $shippingMethodId;
			}
		}
		if (!empty($_POST['start_date'])) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "order_time >= ?";
			$parameters[] = date("Y-m-d 00:00:00", strtotime($_POST['start_date']));
		}
		if (!empty($_POST['end_date'])) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "order_time <= ?";
			$parameters[] = date("Y-m-d 23:59:59", strtotime($_POST['end_date']));
		}
		if (!empty($_POST['date_completed'])) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "date_completed = ?";
			$parameters[] = date("Y-m-d", strtotime($_POST['date_completed']));
		}
		if (!empty($_POST['minimum_order_amount']) && $_POST['minimum_order_amount'] > 0) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "order_id in(select order_id from(select order_id,sum(quantity * sale_price) as order_total from order_items group by order_id having order_total >= ?) as order_cart_totals)";
			$parameters[] = $_POST['minimum_order_amount'];
		}
		if (!empty($_POST['maximum_order_amount']) && $_POST['maximum_order_amount'] > 0) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "order_id in(select order_id from(select order_id,sum(quantity * sale_price) as order_total from order_items group by order_id having order_total >= ?) as order_cart_totals)";
			$parameters[] = $_POST['maximum_order_amount'];
		}
		$productId = getFieldFromId("product_id", "products", "product_id", $_POST['product_id']);
		if (empty($productId)) {
			$productId = getFieldFromId("product_id", "product_data", "upc_code", $_POST['upc_code']);
		}
		if (!empty($productId)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "order_id in(select order_id from order_items where deleted = 0 and product_id = ?)";
			$parameters[] = $productId;
		}
		$sourceId = getFieldFromId("source_id", "sources", "source_id", $_POST['source_id']);
		if (empty($sourceId)) {
			$sourceId = getFieldFromId("source_id", "sources", "source_code", $_POST['source_code']);
		}
		if (!empty($sourceId)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "source_id = ?";
			$parameters[] = $sourceId;
		}
		if (!empty($_POST['state'])) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "((address_id is not null and address_id in(select address_id from addresses where state = ?)) or " .
				"(address_id is null and contact_id in(select contact_id from contacts where state = ?)))";
			$parameters[] = $_POST['state'];
			$parameters[] = $_POST['state'];
		}
		$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_id", $_POST['payment_method_id']);
		if (empty($paymentMethodId)) {
			$paymentMethodId = getFieldFromId("payment_method_id", "payment_methods", "payment_method_code", $_POST['payment_method_code']);
		}
		if (!empty($paymentMethodId)) {
			$whereStatement .= (empty($whereStatement) ? "" : " and ") . "order_id in(select order_id from order_payments where payment_method_id in ?)";
			$parameters[] = $paymentMethodId;
		}
		$orderIds = array();
		$resultSet = executeQuery("select order_id from orders where " . $whereStatement, $parameters);
		while ($row = getNextRow($resultSet)) {
			$orderIds[] = $row['order_id'];
		}
		$returnArray['order_ids'] = $orderIds;
		break;

	case "get_order":
		$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id']);
		if (empty($orderId)) {
			$returnArray['error_message'] = "Order not found";
			break;
		}
		$resultSet = executeQuery("select * from contacts join orders using(contact_id) where orders . order_id = ?", $orderId);
		if ($row = getNextRow($resultSet)) {
			if (!empty($row['address_id'])) {
				$addressRow = getRowFromId("addresses", "address_id", $row['address_id']);
				$row = array_merge($row, array_filter($addressRow));
			}
			$row['pickup'] = getFieldFromId("pickup", "shipping_methods", "shipping_method_id", $row['shipping_method_id']);
			$row['shipping_method'] = getFieldFromId("description", "shipping_methods", "shipping_method_id", $row['shipping_method_id']);
			$row['pickup_location_code'] = getFieldFromId("location_code", "locations", "location_id", getFieldFromId("location_id", "shipping_methods", "shipping_method_id", $row['shipping_method_id']));

			$identifierSet = executeQuery("select * from contact_identifiers join contact_identifier_types using(contact_identifier_type_id) where contact_id = ?", $row['contact_id']);
			$row['contact_identifier_count'] = $identifierSet['row_count'];
			while ($identifierRow = getNextRow($identifierSet)) {
				$row['contact_identifier_' . strtolower($identifierRow['contact_identifier_type_code'])] = $identifierRow['identifier_value'];
			}
			$row['ffl_dealer'] = array();
			if (empty($row['pickup']) && !empty($row['federal_firearms_licensee_id'])) {
				$fflRow = (new FFL($row['federal_firearms_licensee_id']))->getFFLRow();
				if ($fflRow) {
					$row['ffl_dealer'] = $fflRow;
				}
			}
			$row['order_status_code'] = getFieldFromId("order_status_code", "order_status", "order_status_id", $row['order_status_id']);
			$row['order_status_description'] = getFieldFromId("description", "order_status", "order_status_id", $row['order_status_id']);
			$row['source_code'] = getFieldFromId("source_code", "sources", "source_id", $row['source_id']);
			$row['source_description'] = getFieldFromId("description", "sources", "source_id", $row['source_id']);

			$returnArray['orders_row'] = $row;
		}
		$returnArray['order_items'] = array();
		$orderItemsTotal = 0;
		$resultSet = executeQuery("select * from products join order_items using (product_id) left outer join product_data using(product_id) where order_items . order_id = ?", $orderId);
		while ($row = getNextRow($resultSet)) {
			$eventId = getFieldFromId("event_id", "events", "product_id", $row['product_id']);
			if (!empty($eventId)) {
				$row['event_id'] = $eventId;
				$row['event_type_id'] = getFieldFromId("event_type_id", "events", "event_id", $eventId);
			}
			$orderItemsTotal += ($row['sale_price'] * $row['quantity']);
			$serialNumbers = array();
			$subSet = executeQuery("select serial_number from order_item_serial_numbers where order_item_id = ?", $row['order_item_id']);
			while ($subRow = getNextRow($subSet)) {
				$serialNumbers[] = $subRow['serial_number'];
			}
			$row['serial_number'] = implode(",", $serialNumbers);
			$row['serial_numbers'] = $serialNumbers;
			if (empty($returnArray['orders_row']['pickup']) && empty($returnArray['orders_row']['pickup_location_code'])) {
				$locationId = getFieldFromId("location_id", "events", "product_id", $row['product_id']);
				if (!empty($locationId)) {
					$returnArray['orders_row']['pickup'] = "1";
					$returnArray['orders_row']['pickup_location_code'] = getFieldFromId("location_code", "locations", "location_id", $locationId);
				}
			}
			$row['product_addons'] = array();
			$addonSet = executeQuery("select * from product_addons join order_item_addons using (product_addon_id) where order_item_id = ?", $row['order_item_id']);
			while ($addonRow = getNextRow($addonSet)) {
				$row['product_addons'][] = $addonRow;
			}
			$locationSet = executeQuery("select * from locations where client_id = ? and inactive = 0 and (product_distributor_id is null or primary_location = 1) and " .
				"location_id in (select location_id from product_inventories where product_id = ? and quantity > 0) order by location_id", $GLOBALS['gClientId'], $row['product_id']);
			$productInventories = array();
			while ($locationRow = getNextRow($locationSet)) {
				$productInventoryRow = getRowFromId("product_inventories", "product_id", $row['product_id'], "location_id = ?", $locationRow['location_id']);
				if (!empty($productInventoryRow['quantity']) && $productInventoryRow['quantity'] > 0) {
					$productInventories[] = array("location_code" => $locationRow['location_code'], "quantity" => $productInventoryRow['quantity']);
				}
			}
			$row['product_inventories'] = $productInventories;
			$returnArray['order_items'][] = $row;
		}
		$returnArray['order_items_total'] = $orderItemsTotal;
		$returnArray['order_total'] = $orderItemsTotal + $returnArray['orders_row']['shipping_charge'] + $returnArray['orders_row']['tax_charge'] + $returnArray['orders_row']['handling_charge'];

		$returnArray['order_shipments'] = array();
		$resultSet = executeQuery("select * from order_shipments join locations using(location_id) where order_shipments.order_id = ?", $orderId);
		while ($row = getNextRow($resultSet)) {
			$row['order_shipment_items'] = array();
			$itemSet = executeQuery("select *,(select upc_code from product_data where product_id = (select product_id from order_items where " .
				"order_item_id = order_shipment_items.order_item_id)) upc_code from order_shipment_items where order_shipment_id = ?", $row['order_shipment_id']);
			while ($itemRow = getNextRow($itemSet)) {
				$row['order_shipment_items'][] = $itemRow;
			}
			$returnArray['order_shipments'][] = $row;
		}

		$returnArray['order_files'] = array();
		$resultSet = executeQuery("select * from order_files where order_id = ?", $orderId);
		while ($row = getNextRow($resultSet)) {
			$returnArray['order_files'][] = $row;
		}

		$returnArray['order_payments'] = array();
        $resultSet = executeQuery("select order_payments.*,payment_methods.description as payment_method, payment_methods.payment_method_code, payment_methods.payment_method_type_id from order_payments join payment_methods using (payment_method_id) where order_id = ?", $orderId);
		while ($row = getNextRow($resultSet)) {
			if (empty($row['payment_date'])) {
				$row['payment_date'] = date("Y-m-d", strtotime($row['payment_time']));
			}
            $row['payment_method_type_code'] = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", $row['payment_method_type_id']);
			$returnArray['order_payments'][] = $row;
		}

		$returnArray['gift_cards'] = array();
		$resultSet = executeQuery("select * from gift_card_log join gift_cards using (gift_card_id) where order_id = ?", $orderId);
		while ($row = getNextRow($resultSet)) {
			$returnArray['gift_cards'][] = $row;
		}
		$returnArray['loyalty_program_points'] = array();
		$resultSet = executeQuery("select loyalty_program_point_log.*,loyalty_program_points.user_id,loyalty_program_points.loyalty_program_id " .
			"from loyalty_program_point_log join loyalty_program_points using (loyalty_program_point_id) where order_id = ?", $orderId);
		while ($row = getNextRow($resultSet)) {
			$returnArray['loyalty_program_points'][] = $row;
		}
		$returnArray['order_notes'] = array();
		$resultSet = executeQuery("select * from order_notes where order_id = ?", $orderId);
		while ($row = getNextRow($resultSet)) {
			$returnArray['order_notes'][] = $row;
		}
		break;

	case "set_order_item_serial_number":
		$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id']);
		if (empty($orderId)) {
			$returnArray['error_message'] = "Invalid Order ID";
			break;
		}
		$productId = getFieldFromId("product_id", "product_data", "upc_code", $_POST['upc_code']);
		if (empty($productId) || empty($_POST['upc_code'])) {
			$returnArray['error_message'] = "Invalid Product";
			break;
		}
		$orderItemId = getFieldFromId("order_item_id", "order_items", "order_id", $orderId, "product_id = ? and deleted = 0", $productId);
		if (empty($productId)) {
			$returnArray['error_message'] = "Product not in this order";
			break;
		}
		$orderItemSerialNumberId = getFieldFromId("order_item_serial_number_id", "order_item_serial_numbers", "order_item_id", $orderItemId,
			"serial_number = ?", $_POST['serial_number']);
		if (empty($orderItemSerialNumberId)) {
			$dataTable = new DataTable("order_item_serial_numbers");
			$dataTable->setSaveOnlyPresent(true);
			if (!$dataTable->saveRecord(array("name_values" => array("serial_number" => $_POST['serial_number'], "order_item_id" => $orderItemId), "primary_id" => $orderItemSerialNumberId))) {
				$returnArray['error_message'] = $dataTable->getErrorMessage();
			}
		}
		break;

	case "get_loyalty_programs":
		$resultSet = executeQuery("select * from loyalty_programs where client_id = ?", $GLOBALS['gClientId']);
		$loyaltyPrograms = array();
		while ($row = getNextRow($resultSet)) {
			$loyaltyPrograms[] = $row;
		}
		$returnArray['loyalty_programs'] = $loyaltyPrograms;
		break;

	case "get_loyalty_point_balance":
	case "adjust_loyalty_point_balance":
		$userId = getFieldFromId("user_id", "users", "user_id", $_POST['user_id']);
		if (empty($userId) && !empty($_POST['contact_id'])) {
			$userId = getFieldFromId("user_id", "users", "contact_id", $_POST['contact_id']);
		}
		if (empty($userId)) {
			$returnArray['error_message'] = "Invalid User";
			break;
		}
		$loyaltyProgramId = getFieldFromId("loyalty_program_id", "loyalty_programs", "loyalty_program_code", (empty($_POST['loyalty_program_code']) ? "LOYALTY" : ""));
		if (empty($loyaltyProgramId)) {
			$returnArray['error_message'] = "No Loyalty Program found";
			break;
		}
		$loyaltyProgramPointId = getFieldFromId("loyalty_program_point_id", "loyalty_program_points", "user_id", $userId, "loyalty_program_id = ?", $loyaltyProgramId);
		if (empty($loyaltyProgramPointId)) {
			$resultSet = executeQuery("insert into loyalty_program_points(loyalty_program_id, user_id, point_value) values(?,?,0)", $loyaltyProgramId, $userId);
			$loyaltyProgramPointId = $resultSet['insert_id'];
		}
		$pointValue = getFieldFromId("point_value", "loyalty_program_points", "loyalty_program_point_id", $loyaltyProgramPointId);
		if (empty($pointValue)) {
			$pointValue = 0;
		}
		if (empty($_POST['point_value']) || $_POST['action'] == "get_loyalty_point_balance") {
			$returnArray['point_value'] = $pointValue;
			break;
		}
		if (!empty($_POST['set_point_value'])) {
			$pointValue = $_POST['point_value'];
		} else {
			$pointValue = $pointValue + $_POST['point_value'];
		}
		$loyaltyProgramPoints = new DataTable("loyalty_program_points");
		$loyaltyProgramPointId = $loyaltyProgramPoints->saveRecord(array("name_values" => array("point_value" => $pointValue), "primary_id" => $loyaltyProgramPointId));
		if (!$loyaltyProgramPointId) {
			$returnArray['error_message'] = $loyaltyProgramPoints->getErrorMessage();
			break;
		}
		executeQuery("insert into loyalty_program_point_log(loyalty_program_point_id, log_time, point_value, notes) values(?,now(),?,?)",
			$loyaltyProgramPointId, $_POST['point_value'], (!empty($_POST['set_point_value']) ? "API Set Points Value" : ($_POST['point_value'] < 0 ? "API Reduce Points" : "API Increase Points")));
		$returnArray['point_value'] = $pointValue;
		break;

	case "create_distributor_order":
		$userId = $_POST['user_id'];
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
		if (empty($userId)) {
			$returnArray['error_message'] = "User login account on Developer account is required";
			break;
		}
		$locationId = getFieldFromId("location_id", "locations", "location_id", $_POST['location_id'], "inactive = 0 and product_distributor_id is not null");
		if (empty($locationId)) {
			$returnArray['error_message'] = "Invalid Location";
			break;
		}
		$productArray = array();
		foreach ($_POST as $fieldName => $fieldData) {
			if (substr($fieldName, 0, strlen("product_id_")) == "product_id_") {
				$fieldNumber = substr($fieldName, strlen("product_id_"));
				$productId = $fieldData;
				$quantity = (int)$_POST['quantity_' . $fieldNumber];
				if (empty($productId) || empty($quantity)) {
					continue;
				}
				$productArray[] = array("product_id" => $productId, "quantity" => $quantity, "upc_code" => "");
			}
			if (substr($fieldName, 0, strlen("upc_code_")) == "upc_code_") {
				$fieldNumber = substr($fieldName, strlen("upc_code_"));
				$upcCode = $fieldData;
				$productId = getFieldFromId("product_id", "product_data", "upc_code", ProductCatalog::makeValidUPC($upcCode));
				$quantity = (int)$_POST['quantity_' . $fieldNumber];
				if (empty($productId) || empty($quantity)) {
					continue;
				}
				$productArray[] = array("product_id" => $productId, "quantity" => $quantity, "upc_code" => "");
			}
		}
		if (is_array($_POST['products'])) {
			foreach ($_POST['products'] as $thisProduct) {
				if (empty($thisProduct['quantity']) || !is_numeric($thisProduct['quantity'])) {
					continue;
				}
				if (empty($thisProduct['product_id']) && empty($thisProduct['upc_code'])) {
					continue;
				}
				$productId = "";
				if (array_key_exists("product_id", $thisProduct)) {
					$productId = $thisProduct['product_id'];
				}
				if (empty($productId) && array_key_exists("upc_code", $thisProduct)) {
					$productId = getFieldFromId("product_id", "product_data", "upc_code", ProductCatalog::makeValidUPC($thisProduct['upc_code']));
				}
				$productArray[] = array("product_id" => $productId, "quantity" => $thisProduct['quantity'], "upc_code" => ProductCatalog::makeValidUPC($thisProduct['upc_code']), "notes" => $thisProduct['notes']);
			}
		}
		$productErrorMessage = "";
		$productErrors = array();
		$productCatalog = new ProductCatalog();
		$productCatalog->setLocations($locationId);
		foreach ($productArray as $index => $thisProduct) {
			$productId = getFieldFromId("product_id", "products", "product_id", $thisProduct['product_id'], "inactive = 0");
			if (empty($productId)) {
				$productErrorMessage .= (empty($productErrorMessage) ? "" : ", ") . "product '" . (empty($thisProduct['product_id']) ? $thisProduct['upc_code'] : $thisProduct['product_id']) . "' does not exist";
				$thisProduct['error_message'] = "Invalid Product";
				$productErrors[] = $thisProduct;
				unset($productArray[$index]);
				continue;
			}
			$quantityOnHandArray = $productCatalog->getInventoryCounts(true, $productId);
			$quantityOnHand = $quantityOnHandArray[$productId];
			if ($quantityOnHand < $thisProduct['quantity']) {
				$productErrorMessage .= (empty($productErrorMessage) ? "" : ", ") . "product '" . (empty($thisProduct['product_id']) ? $thisProduct['upc_code'] : $thisProduct['product_id']) . "' has insufficient inventory: " . $thisProduct['quantity'] . " needed, " . $quantityOnHand . " found";
				$thisProduct['error_message'] = "Insufficent Quantity Onhand";
				$productErrors[] = $thisProduct;
				unset($productArray[$index]);
				continue;
			}
		}
		if (!empty($productErrorMessage)) {
			$returnArray['product_error_message'] = $productErrorMessage;
			$returnArray['product_errors'] = $productErrors;
			if (!empty($_POST['all_or_nothing'])) {
				$returnArray['error_message'] = $productErrorMessage;
				break;
			}
		}
		$locationDescription = getFieldFromId("description", "locations", "location_id", $locationId);
		$productDistributor = ProductDistributor::getProductDistributorInstance($locationId);
		if (empty($productDistributor)) {
			$returnArray['error_message'] = "Unable to create distributor: " . $locationId;
			break;
		}

		if (empty($productArray)) {
			$returnArray['error_message'] = "No products found to order";
			break;
		}
		$returnValue = $productDistributor->placeDistributorOrder($productArray, array("notes" => $_POST['notes'], "user_id"=>$userId));

		if ($returnValue === false) {
			$returnArray['error_message'] = "Order for " . $locationDescription . " was unable to be placed: " . $productDistributor->getErrorMessage();
			break;
		} else {
			$distributorOrderId = $returnValue['dealer']['distributor_order_id'];
			if (!empty($distributorOrderId)) {
				foreach ($productArray as $thisProduct) {
					if (array_key_exists("product_ids", $returnValue['dealer']) && !in_array($thisProduct['product_id'], $returnValue['dealer']['product_ids'])) {
						continue;
					}
					if (!empty($thisProduct['distributor_order_product_id'])) {
						if (is_array($returnValue['failed_items']) && in_array($thisProduct['product_id'], $returnValue['failed_items'])) {
							continue;
						}
						$orderItemId = getFieldFromId("order_item_id", "distributor_order_products", "distributor_order_product_id", $thisProduct['distributor_order_product_id']);
						executeQuery("delete from distributor_order_products where client_id = ? and user_id = ? and distributor_order_product_id = ?",
							$GLOBALS['gClientId'], $userId, $thisProduct['distributor_order_product_id']);
						if (!empty($orderItemId)) {
							executeQuery("insert ignore into distributor_order_item_links(distributor_order_id, order_item_id) values(?,?)", $distributorOrderId, $orderItemId);
						}
					}
				}
			}

			$distributorOrderId = $returnValue['class_3']['distributor_order_id'];
			if (!empty($distributorOrderId)) {
				foreach ($productArray as $thisProduct) {
					if (array_key_exists("product_ids", $returnValue['class_3']) && !in_array($thisProduct['product_id'], $returnValue['dealer']['product_ids'])) {
						continue;
					}
					if (!empty($thisProduct['distributor_order_product_id'])) {
						if (is_array($returnValue['failed_items']) && in_array($thisProduct['product_id'], $returnValue['failed_items'])) {
							continue;
						}
						$orderItemId = getFieldFromId("order_item_id", "distributor_order_products", "distributor_order_product_id", $thisProduct['distributor_order_product_id']);
						executeQuery("delete from distributor_order_products where client_id = ? and user_id = ? and distributor_order_product_id = ?",
							$GLOBALS['gClientId'], $userId, $thisProduct['distributor_order_product_id']);
						if (!empty($orderItemId)) {
							executeQuery("insert ignore into distributor_order_item_links(distributor_order_id, order_item_id) values(?,?)", $distributorOrderId, $orderItemId);
						}
					}
				}
			}

			if (array_key_exists("failed_items", $returnValue)) {
				$returnArray['failed_items'] = $returnValue['failed_items'];
			}
			$returnArray['location_id'] = $locationId;
			if (array_key_exists("dealer", $returnValue)) {
				$returnArray['dealer'] = $returnValue['dealer'];
			}
			if (array_key_exists("class_3", $returnValue)) {
				$returnArray['class_3'] = $returnValue['class_3'];
			}
		}
		break;

	case "create_order":
		if (empty($_POST['order_type'])) {
			$_POST['order_type'] = "dealer";
		}
		$createOrder = true;
	case "place_order":
		if (empty($_POST['country_id'])) {
			if (!empty($_POST['country_code'])) {
				$_POST['country_id'] = getFieldFromId("country_id", "countries", "country_code", $_POST['country_code']);
			}
			if (empty($_POST['country_id'])) {
				$_POST['country_id'] = 1000;
			}
		}

		if (!empty($_POST['contact_id'])) {
			$contactId = getFieldFromId("contact_id", "contacts", "contact_id", $_POST['contact_id'], "deleted = 0");
		} else {
			$contactId = $GLOBALS['gUserRow']['contact_id'];
		}
		if (empty($contactId)) {
			$returnArray['error_message'] = "Invalid contact ID: " . $_POST['contact_id'];
			break;
		}
		if ($createOrder) {
			$contactRow = Contact::getContact($contactId);
			if (empty($_POST['address_1']) || empty($_POST['city']) || empty($_POST['state']) || empty($_POST['postal_code'])) {
				$_POST['address_1'] = $contactRow['address_1'];
				$_POST['address_2'] = $contactRow['address_2'];
				$_POST['city'] = $contactRow['city'];
				$_POST['state'] = $contactRow['state'];
				$_POST['postal_code'] = $contactRow['postal_code'];
				$_POST['country_id'] = $contactRow['country_id'];
			}
			if (empty($_POST['full_name'])) {
				$_POST['full_name'] = getDisplayName($contactId);
			}
		}

		$shoppingCartCode = strtoupper(getRandomString(20));
		$shoppingCart = ShoppingCart::getShoppingCart($shoppingCartCode);
		$productCatalog = new ProductCatalog();

		/*
		 * Three types of orders
		 *      - to an FFL dealer
		 *          - No Shipping Address
		 *      - back to the ordering dealer
		 *      - to the customer
		 */

		if (empty($_POST['order_type'])) {
			$_POST['order_type'] = "dealer";
		}
		if (!in_array($_POST['order_type'], array("dealer", "ffl", "customer"))) {
			$returnArray['error_message'] = "Invalid order type: only 'dealer', 'ffl', or 'customer' allowed";
			break;
		}
		if ($_POST['order_type'] != "dealer" && !$createOrder) {
			$noDropShipOrders = getPreference("NO_DROP_SHIP_ORDERS");
			if (!empty($noDropShipOrders)) {
				$returnArray['error_message'] = "Order Drop Ship not available . ";
			}
		}

		/*
		 * order_items
		 *      product_id
		 *      product_code
		 *      quantity
		 */
		$itemLineNumber = 0;
		foreach ($_POST['order_items'] as $thisOrderItem) {
			if (!is_array($thisOrderItem)) {
				$GLOBALS['gPrimaryDatabase']->logError("API place_order: non-array passed as order_item: " . $thisOrderItem);
				continue;
			}
			$itemLineNumber++;
			if (empty($thisOrderItem['product_id']) && !empty($thisOrderItem['product_code'])) {
				$thisOrderItem['product_id'] = getFieldFromId("product_id", "products", "product_code", $thisOrderItem['product_code']);
			}
			$productId = getFieldFromId("product_id", "products", "product_id", $thisOrderItem['product_id'], "inactive = 0" . ($createOrder ? "" : " and internal_use_only = 0"));
			if (empty($productId)) {
				if (empty($thisOrderItem['product_code']) && empty($thisOrderItem['product_id'])) {
					$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ", ") . "Order Item Line #" . $itemLineNumber . " - no product specified";
				} else {
					$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ", ") . "Order Item Line #" . $itemLineNumber . " - Product " . (empty($thisOrderItem['product_id']) ? $thisOrderItem['product_code'] : $thisOrderItem['product_id']) . " does not exist";
				}
			} else {
				if (empty($thisOrderItem['quantity']) || !is_numeric($thisOrderItem['quantity']) || $thisOrderItem['quantity'] < 0) {
					$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ", ") . "Order Item Line #" . $itemLineNumber . " - Invalid or missing quantity";
					continue;
				}
				$productRow = ProductCatalog::getCachedProductRow($productId);

				$inventoryCounts = $productCatalog->getInventoryCounts(true, $productId);
				$inventoryQuantity = $inventoryCounts[$productId];
				if (empty($inventoryQuantity)) {
					$inventoryQuantity = 0;
				}
				if ($inventoryQuantity < $thisOrderItem['quantity']) {
					$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ", ") . "Order Item Line #" . $itemLineNumber . ", product ID " . $productId . " - Quantity unavailable for this product";
					continue;
				}
				if (!empty($productRow['cart_maximum']) && $thisOrderItem['quantity'] > $productRow['cart_maximum']) {
					$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ", ") . "Order Item Line #" . $itemLineNumber . ", product ID " . $productId . " - Order maximum is " . $productRow['cart_maximum'];
					continue;
				}
				if (!empty($productRow['cart_minimum']) && $thisOrderItem['quantity'] < $productRow['cart_minimum']) {
					$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ", ") . "Order Item Line #" . $itemLineNumber . ", product ID " . $productId . " - Minimum order quantity is " . $productRow['cart_minimum'];
					continue;
				}
				if (!empty($productRow['order_maximum'])) {
					$orderQuantity = 0;
					$resultSet = executeQuery("select sum(quantity) from order_items where product_id = ? and deleted = 0 and order_id in (select order_id from orders where contact_id = ? and deleted = 0)", $productId, $contactId);
					if ($row = getNextRow($resultSet)) {
						$orderQuantity = $row['sum(quantity)'];
						if (empty($orderQuantity)) {
							$orderQuantity = 0;
						}
					}
					$orderMaximum = $productRow['order_maximum'] - $orderQuantity;
					if ($thisOrderItem['quantity'] > $orderMaximum) {
						$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ", ") . "Order Item Line #" . $itemLineNumber . " - Order quantity limited to " . $orderMaximum;
						continue;
					}
				}

				# make sure inventory is more than order quantity and more than maximum allowed to order, either in cart or lifetime
				# possible errors:
				# maximum order quantity exceeded
				# no quantity specified
				$shoppingCartItemParameters = array("product_id" => $productId, "quantity" => $thisOrderItem['quantity']);
				if (!empty($thisOrderItem['sale_price'])) {
					$shoppingCartItemParameters['sale_price'] = $thisOrderItem['sale_price'];
				}
				if (!$shoppingCart->addItem($shoppingCartItemParameters)) {
					$returnArray['error_message'] .= (empty($returnArray['error_message']) ? "" : ", ") . "Order Item Line #" . $itemLineNumber . " - Unable to add product to shopping cart: " . $shoppingCart->getErrorMessage();
				}
			}
		}
		if (!empty($returnArray['error_message'])) {
			break;
		}
		if (function_exists("_localServerPreprocessOrder")) {
			$functionName = "_localServerPreprocessOrder";
			$returnValue = $functionName($shoppingCart);
			if ($returnValue !== true) {
				$returnArray['error_message'] = $returnValue;
				break;
			}
		}

		if (!$GLOBALS['gLoggedIn'] && $shoppingCart->requiresUser()) {
			$returnArray['error_message'] = "Login is required to place this order.";
			break;
		}

		if (!empty($_POST['promotion_code'])) {
			if (!$shoppingCart->applyPromotionCode($_POST['promotion_code'])) {
				$returnArray['error_message'] = "Invalid Promotion Code";
				break;
			}
		}
		$cartTotalQuantity = $shoppingCart->getShoppingCartItemsCount();
		$shoppingCartItems = $shoppingCart->getShoppingCartItems();

		if ($cartTotalQuantity <= 0) {
			$returnArray['error_message'] = "No items ordered";
			break;
		}

		$productIds = array();
		$shippingRequired = false;
		$somePickupProducts = false;
		$fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED");
		$class3ProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "CLASS_3");
		$fflRequired = false;
		$class3Required = false;
		foreach ($shoppingCartItems as $index => $thisItem) {
			$productRow = ProductCatalog::getCachedProductRow($thisItem['product_id']);
			if (!empty($productRow['no_online_order'])) {
				$returnArray['error_message'] = "In-store purchase only products cannot be ordered online";
				break 2;
			}
			if ($fflRequiredProductTagId) {
				$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productRow['product_id'], "product_tag_id = ?", $fflRequiredProductTagId);
				if (!empty($productTagLinkId)) {
					$fflRequired = true;
				}
			}
			if ($class3ProductTagId) {
				$productTagLinkId = getFieldFromId("product_tag_link_id", "product_tag_links", "product_id", $productRow['product_id'], "product_tag_id = ?", $class3ProductTagId);
				if (!empty($productTagLinkId)) {
					$class3Required = true;
				}
			}
			$shoppingCartItems[$index]['product_row'] = $productRow;
			$pickupOnly = CustomField::getCustomFieldData($thisItem['product_id'], "PICKUP_ONLY", "PRODUCTS");
			if (!empty($pickupOnly)) {
				$somePickupProducts = true;
			}
			if (empty($productRow['virtual_product']) && empty($pickupOnly)) {
				$shippingRequired = true;
			}

			$productIds[] = $thisItem['product_id'];
		}

		if (!$createOrder && ($fflRequired || $class3Required)) {
			$licenseNumber = CustomField::getCustomFieldData($contactId, "FFL_NUMBER");
			$federalFirearmsLicenseeId = (new FFL(array("license_number" => $licenseNumber, "only_if_valid" => true)))->getFieldData("federal_firearms_licensee_id");
			if (empty($federalFirearmsLicenseeId)) {
				$returnArray['error_message'] = "Dealer FFL not on file. Contact your sales rep.";
				break;
			}
		}

		$federalFirearmsLicenseeId = "";
		switch ($_POST['order_type']) {
			case "ffl":
				$federalFirearmsLicenseeId = (new FFL($_POST))->getFieldData("federal_firearms_licensee_id");
				if (empty($federalFirearmsLicenseeId)) {
					$returnArray['error_message'] = "Invalid FFL License Number";
					break 2;
				}
				break;
			case "customer":
				if (!$createOrder && ($fflRequired || $class3Required)) {
					$returnArray['error_message'] = "FFL and Class 3 products cannot be shipped to customer";
					break 2;
				}
				break;
		}

		$orderStatusId = "";
		if ($class3Required) {
			$sotFileNeededOrderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", "SOT_FILE_NEEDED");
			if (empty($sotFileNeededOrderStatusId)) {
				$insertSet = executeQuery("insert into order_status (client_id,order_status_code,description,display_color,internal_use_only) values (?,'SOT_FILE_NEEDED','SOT File Needed','#FF0000',1)", $GLOBALS['gClientId']);
				$sotFileNeededOrderStatusId = $insertSet['insert_id'];
			}
			$sotFileId = (new FFL($federalFirearmsLicenseeId))->getFieldData("sot_file_id");
			if (empty($sotFileId) && empty(getPreference("CENTRALIZED_FFL_STORAGE"))) {
				if (!empty($_POST['sot_file_id'])) {
					$fileContents = file_get_contents("https://" . $_POST['api_domain_name'] . "/download.php?id=" . $_POST['sot_file_id'] . "&connection_key=" . $_POST['connection_key']);
				} else if (!empty($_POST['sot_file_url'])) {
					$fileContents = file_get_contents($_POST['sot_file_url']);
				}
				if (!empty($fileContents)) {
					$fileId = createFile(array("filename" => "SOT License File", "file_content" => $fileContents));
					if (!empty($fileId)) {
						executeQuery("update federal_firearms_licensees set sot_file_id = ? where federal_firearms_licensee_id = ?", $fileId, $federalFirearmsLicenseeId);
						$sotFileId = $fileId;
					}
				}
			}
			if (empty($sotFileId)) {
				$orderStatusId = $sotFileNeededOrderStatusId;
			}
		} else if ($fflRequired) {
			$fflFileNeededOrderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", "FFL_FILE_NEEDED");
			if (empty($fflFileNeededOrderStatusId)) {
				$insertSet = executeQuery("insert into order_status (client_id,order_status_code,description,display_color,internal_use_only) values (?,'FFL_FILE_NEEDED','FFL File Needed','#FF0000',1)", $GLOBALS['gClientId']);
				$fflFileNeededOrderStatusId = $insertSet['insert_id'];
			}
			$fflFileId = (new FFL($federalFirearmsLicenseeId))->getFieldData("file_id");
			if (empty($fflFileId) && empty(getPreference("CENTRALIZED_FFL_STORAGE"))) {
				if (!empty($_POST['file_id'])) {
					$fileContents = file_get_contents("https://" . $_POST['api_domain_name'] . "/download.php?id=" . $_POST['file_id'] . "&connection_key=" . $_POST['connection_key']);
				} else if (!empty($_POST['file_url'])) {
					$fileContents = file_get_contents($_POST['file_url']);
				}
				if (!empty($fileContents)) {
					$fileId = createFile(array("filename" => "FFL License File", "file_content" => $fileContents));
					if (!empty($fileId)) {
						executeQuery("update federal_firearms_licensees set file_id = ? where federal_firearms_licensee_id = ?", $fileId, $federalFirearmsLicenseeId);
						$fflFileId = $fileId;
					}
				}
			}
			if (empty($fflFileId)) {
				$orderStatusId = $fflFileNeededOrderStatusId;
			}
		}

		if ($shippingRequired) {
			if (empty($_POST['shipping_method_code'])) {
				$_POST['shipping_method_code'] = "standard";
			}
			$shippingMethodId = getFieldFromId("shipping_method_id", "shipping_methods", "shipping_method_code", $_POST['shipping_method_code']);
			if (empty($shippingMethodId)) {
				$returnArray['error_message'] = "Invalid shipping method";
				break;
			}

			$shippingMethodPickup = getFieldFromId("pickup", "shipping_methods", "shipping_method_id", $shippingMethodId);
			if (!empty($shippingMethodPickup)) {
				$shippingRequired = false;
			}
		}

		$addressId = "";

		switch ($_POST['order_type']) {
			case "customer":
				if (!$shippingRequired) {
					break;
				}
				if (empty($_POST['full_name']) || empty($_POST['address_1']) || empty($_POST['city']) || empty($_POST['state']) || empty($_POST['postal_code'])) {
					$returnArray['error_message'] = "Customer name and address is required";
					break 2;
				}
				$addressId = getFieldFromId("address_id", "addresses", "contact_id", $contactId, "address_1 <=> ? and address_2 <=> ? and city <=> ? and state <=> ? and postal_code <=> ? and country_id = ?",
					$_POST['address_1'], $_POST['address_2'], $_POST['city'], $_POST['state'], $_POST['postal_code'], $_POST['country_id']);
				if (empty($addressId)) {
					$insertSet = executeQuery("insert into addresses (contact_id,address_label,address_1,address_2,city,state,postal_code,country_id,phone_number,email_address) values (?,?,?,?,?, ?,?,?,?,?)",
						$contactId, "Customer Address", $_POST['address_1'], $_POST['address_2'], $_POST['city'], $_POST['state'], $_POST['postal_code'], $_POST['country_id'], $_POST['phone_number'], $_POST['email_address']);
					if (!empty($insertSet['sql_error'])) {
						$returnArray['error_message'] = getSystemMessage("basic", $insertSet['sql_error']);
						break 2;
					}
					$addressId = $insertSet['insert_id'];
				} else {
					$addressDataTable = new DataTable("addresses");
					$addressDataTable->saveRecord(array("name_values" => array("phone_number" => $_POST['phone_number'], "email_address" => $_POST['email_address']), "primary_id" => $addressId));
				}
				break;
			case "ffl":
				if (empty($_POST['full_name']) || empty($_POST['phone_number'])) {
					$returnArray['error_message'] = "Customer name and phone number is required";
					break 2;
				}
				break;
		}

		$fflRow = (new FFL(array("federal_firearms_licensee_id"=>$federalFirearmsLicenseeId,"only_preferred_address"=>true)))->getFFLRow();

		$shippingCharge = false;

		$orderName = "";
		$attentionLine = "";
		switch ($_POST['order_type']) {
			case "dealer":
				if ($createOrder) {
					$shippingCountryId = $contactRow['country_id'];
					$shippingState = $contactRow['state'];
					$shippingCity = $contactRow['city'];
					$shippingPostalCode = $contactRow['postal_code'];
					$orderName = getDisplayName($contactId);
					$attentionLine = "";
				} else {
					$shippingCountryId = $GLOBALS['gUserRow']['country_id'];
					$shippingState = $GLOBALS['gUserRow']['state'];
					$shippingCity = $GLOBALS['gUserRow']['city'];
					$shippingPostalCode = $GLOBALS['gUserRow']['postal_code'];
					$orderName = getUserDisplayName($GLOBALS['gUserId']);
					$attentionLine = $_POST['full_name'];
				}
				break;
			case "ffl":
				$shippingCountryId = $fflRow['country_id'];
				$shippingState = $fflRow['state'];
				$shippingCity = $fflRow['city'];
				$shippingPostalCode = $fflRow['postal_code'];
				$orderName = (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']);
				$attentionLine = EasyPostIntegration::formatAttentionLine($_POST['full_name'], $_POST['phone_number']);
				break;
			case "customer":
				$shippingCountryId = $_POST['country_id'];
				$shippingState = $_POST['state'];
				$shippingCity = $_POST['city'];
				$shippingPostalCode = $_POST['postal_code'];
				$orderName = $_POST['full_name'];
				if (empty($orderName)) {
					$orderName = getDisplayName($contactId);
				}
				break;
		}

		if ($shippingRequired) {
			$shippingMethods = $shoppingCart->getShippingOptions($shippingCountryId, $shippingState, $shippingPostalCode);

			if ($shippingMethods === false) {
				$returnArray['error_message'] = $shoppingCart->getErrorMessage();
				break;
			} else {
				foreach ($shippingMethods as $thisShippingMethod) {
					if ($thisShippingMethod['shipping_method_id'] == $shippingMethodId) {
						$shippingCharge = $thisShippingMethod['shipping_charge'];
					}
				}
				if ($shippingCharge === false) {
					$returnArray['error_message'] = "No matching shipping method found for " . $_POST['state'] . ", " . $_POST['postal_code'];
					break;
				}
			}
		} else {
			$shippingCharge = 0;
		}
		if (!$shippingRequired) {
			$addressId = "";
		}
		if ($createOrder) {
			$taxCharge = array_key_exists("tax_charge", $_POST) ? $_POST['tax_charge'] : 0;
		} else {
			$taxCharge = $shoppingCart->getEstimatedTax(array("country_id"=> $shippingCountryId, "city"=> $shippingCity, "state"=> $shippingState, "postal_code"=> $shippingPostalCode));
		}
		$cartTotal = 0;
		foreach ($shoppingCartItems as $index => $thisItem) {
			$cartTotal += round($thisItem['quantity'] * $thisItem['sale_price'], 2);
		}
		$handlingCharge = 0;

		$orderTotal = round($cartTotal + $taxCharge + $shippingCharge + $handlingCharge, 2);
		$discounts = $shoppingCart->getCartDiscount();
		$discountAmount = ($discounts['discount_amount'] * 1);
		$discountPercent = ($discounts['discount_percent'] * 1);
		if ($discountAmount <= 0 && $discountPercent > 0) {
			$discountAmount = round($cartTotal * ($discountPercent / 100), 2);
		}
		if ($discountAmount < 0) {
			$discountAmount = 0;
		}
		$orderTotal = $orderTotal - $discountAmount;

		$logEntry = "Order placed by contact ID " . $contactId . ":\n\n";
		foreach ($shoppingCartItems as $index => $thisItem) {
			$productRow = ProductCatalog::getCachedProductRow($thisItem['product_id']);
			$productDataRow = getRowFromId("product_data", "product_id", $thisItem['product_id']);
			$logEntry .= $productRow['product_code'] . "|" . $productRow['description'] . "|" . $productDataRow['upc_code'] . "|" . $thisItem['quantity'] . "|Inventory: " . $inventoryCounts[$thisItem['product_id']] . "\n";
			$logEntry .= "Price calculation: " . $thisItem['price_calculation'] . "\n\n";
		}

		$logArray = array();
		$excludeFields = array("account_number", "expiration_", "cvv", "routing", "bank_account");
		foreach ($_POST as $fieldName => $fieldData) {
			$includeField = true;
			foreach ($excludeFields as $thisFieldName) {
				if (substr($fieldName, 0, strlen($thisFieldName)) == $thisFieldName) {
					$includeField = false;
					break;
				}
			}
			if ($includeField) {
				$logArray[$fieldName] = $fieldData;
			}
		}
		$logEntry .= jsonEncode($logArray) . "\n";
		$programLogId = addProgramLog($logEntry);

		$orderMethodId = getFieldFromId("order_method_id", "order_methods", "order_method_id", $_POST['order_method_id'], "inactive = 0");
		if (empty($orderMethodId)) {
			$orderMethodId = getFieldFromId("order_method_id", "order_methods", "order_method_code", $_POST['order_method_code'], "inactive = 0");
		}
		if (empty($orderMethodId)) {
			$orderMethodId = getFieldFromId("order_method_id", "order_methods", "order_method_code", "API", "inactive = 0");
		}

		$createableSources = array("API" => "API", "CORESTORE" => "coreSTORE");
		$GLOBALS['gAPISourceCode'] = $sourceCode = strtoupper($_POST['source_code']) ?: "API";
		$sourceId = getFieldFromId("source_id", "sources", "source_code", $sourceCode);
		if (empty($sourceId) && array_key_exists($sourceCode, $createableSources)) {
			$insertSet = executeQuery("insert into sources (client_id,source_code,description,internal_use_only) values (?,?,?,1)",
				$GLOBALS['gClientId'], $sourceCode, $createableSources[$sourceCode]);
			$sourceId = $insertSet['insert_id'];
		}

		$GLOBALS['gPrimaryDatabase']->startTransaction();
		$orderObject = new Order();
		$orderObject->populateFromShoppingCart($shoppingCart);
		$orderObject->setOrderField("contact_id", $contactId);
		$orderObject->setOrderField("order_method_id", $orderMethodId);
		$orderObject->setOrderField("tax_charge", $taxCharge);
		$orderObject->setOrderField("source_id", $sourceId);
		$orderObject->setOrderField("shipping_charge", $shippingCharge);
		$orderObject->setOrderField("handling_charge", $handlingCharge);
		$orderObject->setOrderField("order_discount", $discountAmount);
		$orderObject->setOrderField("purchase_order_number", $_POST['purchase_order_number']);
		$orderObject->setOrderField("federal_firearms_licensee_id", $federalFirearmsLicenseeId);
		$orderObject->setOrderField("full_name", $orderName);
		$orderObject->setOrderField("attention_line", $attentionLine);
		$orderObject->setOrderField("shipping_method_id", ($shippingMethodId <= 0 ? "" : $shippingMethodId));
		$orderObject->setOrderField("business_address", ($_POST['order_type'] == "customer" ? false : true));
		$orderObject->setOrderField("address_id", $addressId);
		$orderObject->setOrderField("phone_number", $_POST['phone_number']);
		$orderObject->setOrderField("date_completed", $_POST['date_completed']);

		if (!$orderObject->generateOrder()) {
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			addProgramLog("\n\nOrder Unable to be completed (3493): " . $orderObject->getErrorMessage(), $programLogId);
			$returnArray['error_message'] = $orderObject->getErrorMessage();
			break;
		}

		$orderId = $orderObject->getOrderId();
		if (!empty($_POST['order_notes_content'])) {
			executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $GLOBALS['gUserId'], $_POST['order_notes_content']);
		}
		if ($_POST['order_type'] == "ffl") {
			executeQuery("insert into order_notes (order_id,user_id,time_submitted,content,public_access) values (?,?,now(),?,1)", $orderId, $GLOBALS['gUserId'], "Customer: " . $_POST['full_name'] . ", " . $_POST['phone_number']);
		}
		$orderPackNotes = $orderObject->getPackNotes();
		if (!empty($orderPackNotes)) {
			$orderNoteUserId = $GLOBALS['gUserId'];
			if (empty($orderNoteUserId)) {
				$orderNoteUserId = getFieldFromId("user_id", "users", "full_client_access", "1", "superuser_flag = 0");
			}
			executeQuery("insert into order_notes (order_id,user_id,time_submitted,content) values (?,?,now(),?)", $orderId, $orderNoteUserId, $orderPackNotes);
		}

		if (empty($developerRow['test_account']) && function_exists("_localServerFinalOrderProcessing")) {
			$functionName = "_localServerFinalOrderProcessing";
			$response = $functionName($orderId);
			if ($response !== true) {
				$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
				addProgramLog("\n\nOrder Unable to be completed (6949): " . $orderObject->getErrorMessage(), $programLogId);
				$returnArray['error_message'] = $returnArray['error_message'] = (is_array($response) && !empty($response['error_message']) ? $response['error_message'] : "Unable to complete order");
				break;
			}
		}

		if ($_POST['action'] == "place_order") {
			$paymentMethodCode = getPreference("API_PAYMENT_METHOD_CODE");
			$paymentMethodRow = getRowFromId("payment_methods", "payment_method_code", $paymentMethodCode, "inactive = 0");
			$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", $paymentMethodRow['payment_method_type_id']);
		} else {
			$paymentMethodRow = getRowFromId("payment_methods", "payment_method_id", $_POST['payment_method_id'], "inactive = 0");
			if (empty($paymentMethodRow)) {
				$paymentMethodRow = getRowFromId("payment_methods", "payment_method_code", $_POST['payment_method_code'], "inactive = 0");
			}
			if (empty($paymentMethodRow) && !empty($_POST['account_id'])) {
				$paymentMethodRow = getRowFromId("payment_methods", "payment_method_id", getFieldFromId("payment_method_id", "accounts", "account_id", $_POST['account_id']));
			}
			$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", $paymentMethodRow['payment_method_type_id']);
		}

		switch ($paymentMethodTypeCode) {
			case "INVOICE":
			case "LAYAWAY":
				$invoiceDays = getPreference("LAYAWAY_INVOICE_DAYS");
				if (!empty($invoiceDays)) {
					$dateDue = date("Y-m-d", strtotime("+" . $invoiceDays . " days"));
				}
				$invoiceId = getFieldFromId("invoice_id", "invoices", "invoice_number", $orderId, "inactive = 0");
				if (!empty($invoiceId)) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to create invoice for order. Invoice number already exists.";
					addProgramLog("\n\nUnable to create invoice for order. Invoice number already exists.", $programLogId);
					break 2;
				}
				$invoiceTypeId = getFieldFromId("invoice_type_id", "invoice_types", "invoice_type_code", $paymentMethodTypeCode);
				if (empty($invoiceTypeId) && $paymentMethodTypeCode == "LAYAWAY") {
					$insertSet = executeQuery("insert into invoice_types (client_id,invoice_type_code,description) values (?,?,?)", $GLOBALS['gClientId'], 'LAYAWAY', 'Layaway Invoice');
					$invoiceTypeId = $insertSet['insert_id'];
				}
				$insertSet = executeQuery("insert into invoices (client_id,invoice_number,contact_id,invoice_type_id,invoice_date,date_due) values (?,?,?,?,current_date,?)",
					$GLOBALS['gClientId'], $orderId, $contactId, $invoiceTypeId, $dateDue);
				if (!empty($insertSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to create invoice for order.";
					addProgramLog("\n\nOrder ID " . $orderId . " unable to be completed: " . "Failed to create Order Invoice: " . $insertSet['sql_error'], $programLogId);
					break 2;
				}
				$invoiceId = $insertSet['insert_id'];
				$insertSet = executeQuery("insert into invoice_details (invoice_id,detail_date,description,amount,unit_price) values (?,current_date,?,1,?)",
					$invoiceId, "Order #" . $orderId, $orderTotal);
				if (!empty($insertSet['sql_error'])) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to create invoice for order.";
					addProgramLog("\n\nOrder ID " . $orderId . " unable to be completed: " . "Failed to create Order Invoice: " . $insertSet['sql_error'], $programLogId);
					break 2;
				}
				if (!$orderObject->createOrderPayment($cartTotal, array("payment_method_id" => $paymentMethodRow['payment_method_id'], "invoice_id" => $invoiceId,
					"shipping_charge" => $shippingCharge, "tax_charge" => $taxCharge, "handling_charge" => $handlingCharge))) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to create payment for order.";
					addProgramLog("\n\nOrder ID " . $orderId . " unable to be completed: " . "Failed to create Order Payment: " . $orderObject->getErrorMessage(), $programLogId);
					break 2;
				}
				break;
			default:
				if (empty($paymentMethodRow['payment_method_id'])) {
					break;
				}
				if (!$orderObject->createOrderPayment($cartTotal, array("payment_method_id" => $paymentMethodRow['payment_method_id'], "account_id" => $_POST['account_id'],
					"shipping_charge" => $shippingCharge, "tax_charge" => $taxCharge, "handling_charge" => $handlingCharge))) {
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					$returnArray['error_message'] = "Unable to create payment for order.";
					addProgramLog("\n\nOrder ID " . $orderId . " unable to be completed: " . "Failed to create Order Payment: " . $orderObject->getErrorMessage(), $programLogId);
					break 2;
				}
				break;
		}

		if (empty($developerRow['test_account'])) {
			$commitSet = $GLOBALS['gPrimaryDatabase']->commitTransaction();
			if (!$commitSet || !empty($commitSet['sql_error'])) {
				$returnArray['error_message'] = "Unable to create order.";
				addProgramLog("\n\nOrder ID " . $orderId . " unable to be completed: Commit failure: " . $commitSet['sql_error'], $programLogId);
				break;
			}
			addProgramLog("\n\nOrder Completed, ID " . $orderId, $programLogId);
		} else {
			$originalLogEntry = getFieldFromId("log_entry", "program_log", "program_log_id", $programLogId);
			$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
			addProgramLog($originalLogEntry . "\n\nTEST Order Completed, ID " . $orderId);
			break;
		}
		addProgramLog("\n\nOrder processing for order ID " . $orderId, $programLogId);

		Order::processOrderItems($orderId);
		Order::processOrderAutomation($orderId);
		if ($_POST['send_receipt']) {
			Order::sendReceipt($orderId);
		}
		if (function_exists("_localServerProcessOrder")) {
			$functionName = "_localServerProcessOrder";
			$functionName($orderId);
		}
		Order::notifyCRM($orderId);
		if ($sourceCode != "CORESTORE" && $_POST['calling_app_code'] != "CORESTORE") {
			coreSTORE::orderNotification($orderId, "order_created");
		}
		Order::reportOrderToTaxjar($orderId);
		$returnArray['order_id'] = $orderId;

		$shoppingCart->removeAllItems();
		$shoppingCart->removeMapOverrides();

		$contactRow = getRowFromId('contacts', 'contact_id', $contactId);
		$shippingAddress = (empty($addressId) ? $contactRow : array_merge($contactRow, getRowFromId("addresses", "address_id", $addressId)));
		$substitutions = $shippingAddress;
		$substitutions['shipping_address_block'] = $shippingAddress['address_1'];
		if (!empty($shippingAddress['address_2'])) {
			$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . $shippingAddress['address_2'];
		}
		$shippingCityLine = $shippingAddress['city'] . (empty($shippingAddress['city']) || empty($shippingAddress['state']) ? "" : ", ") . $shippingAddress['state'];
		if (!empty($shippingAddress['postal_code'])) {
			$shippingCityLine .= (empty($shippingCityLine) ? "" : " ") . $shippingAddress['postal_code'];
		}
		if (!empty($shippingCityLine)) {
			$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . $shippingCityLine;
		}
		if (!empty($shippingAddress['country_id']) && $shippingAddress['country_id'] != 1000) {
			$substitutions['shipping_address_block'] .= (empty($substitutions['shipping_address_block']) ? "" : "<br>") . getFieldFromId("country_name", "countries", "country_id", $shippingAddress['country_id']);
		}
		$substitutions = array_merge($substitutions, $orderObject->getOrderRow());
		$substitutions['order_id'] = $orderId;
		$substitutions['order_total'] = number_format($orderTotal, 2, ".", ",");
		$substitutions['tax_charge'] = number_format($taxCharge, 2, ".", ",");
		$substitutions['shipping_charge'] = number_format($shippingCharge, 2, ".", ",");
		$substitutions['handling_charge'] = number_format($handlingCharge, 2, ".", ",");
		$substitutions['shipping_method'] = getFieldFromId("description", "shipping_methods", "shipping_method_id", $shippingMethodId);
		$substitutions['cart_total'] = number_format($cartTotal, 2, ".", ",");
		$substitutions['cart_total_quantity'] = $cartTotalQuantity;
		$substitutions['order_discount'] = $discountAmount;
		$substitutions['donation_amount'] = "";
		$substitutions['designation_code'] = "";
		$substitutions['designation_description'] = "";
		$substitutions['order_date'] = date("m/d/Y");
		$substitutions['ffl_name'] = (empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']);
		$substitutions['ffl_phone_number'] = $fflRow['phone_number'];
		$substitutions['ffl_license_number'] = $fflRow['license_number'];
		$substitutions['ffl_license_number_masked'] = maskString($fflRow['license_number'], "#-##-XXX-XX-XX-#####");
		$substitutions['ffl_address'] = $fflRow['address_1'] . ", " . (empty($fflRow['address_2']) ? "" : $fflRow['address_2'] . ", ") . $fflRow['city'] . ", " . $fflRow['state'] . " " . $fflRow['postal_code'];

# Create the notifications

		$substitutions['domain_name'] = getDomainName();
		$substitutions = array_merge(Order::getOrderItemsSubstitutions($orderId), $substitutions);

		if ($_POST['send_confirmation']) {
			$emailId = getFieldFromId("email_id", "shipping_methods", "shipping_method_id", $shippingMethodId);
			if (empty($emailId)) {
				$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_ORDER_CONFIRMATION",  "inactive = 0");
			}

			$emailAddresses = (empty($_POST['email_address']) ? $contactRow['email_address'] : $_POST['email_address']);
			if (!empty($emailAddresses)) {
				$bccEmailAddresses = array();
				$copyFFLDealer = getPreference("COPY_FFL_DEALER_CONFIRMATION");

				if ($copyFFLDealer && !empty($federalFirearmsLicenseeId)) {
					$bccEmailAddresses[] = $fflRow['email_address'];
				}
				$emailResult = sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_addresses" => $emailAddresses, "bcc_addresses" => $bccEmailAddresses));
			}
		}

		$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_ORDER_NOTIFICATION",  "inactive = 0");
		if (!empty($emailId)) {
			$emailAddresses = getNotificationEmails("RETAIL_STORE_ORDER_NOTIFICATION");
			$pickup = getReadFieldFromId("pickup", "shipping_methods", "shipping_method_id", $shippingMethodId);
			if (!$pickup || Order::hasPhysicalProducts($orderId)) {
				$resultSet = executeQuery("select email_address from shipping_method_notifications where shipping_method_id = ?", $shippingMethodId);
				while ($row = getNextRow($resultSet)) {
					$emailAddresses[] = $row['email_address'];
				}
			}
			$emailResult = sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_addresses" => $emailAddresses));

			if (Order::hasPhysicalProducts($orderId)) {
				$emailAddresses = getNotificationEmails("RETAIL_STORE_PHYSICAL_PRODUCT_ORDER");
				$emailResult = sendEmail(array("email_id" => $emailId, "substitutions" => $substitutions, "email_addresses" => $emailAddresses));
			}
		}

		executeQuery("delete from product_map_overrides where shopping_cart_id = ?", $shoppingCart->getShoppingCartId());
		executeQuery("delete from shopping_cart_item_addons where shopping_cart_item_id in (select shopping_cart_item_id from shopping_cart_items where shopping_cart_id = ?)", $shoppingCart->getShoppingCartId());
		executeQuery("delete from shopping_cart_items where shopping_cart_id = ?", $shoppingCart->getShoppingCartId());
		executeQuery("delete from shopping_carts where shopping_cart_id = ?", $shoppingCart->getShoppingCartId());

		if (empty($_POST['create_user'])) {
			break;
		}
		$userId = Contact::getContactUserId($contactId);
		if (!empty($userId)) {
			updateUserSubscriptions($contactId);
			$returnArray['info_message'] = "User already exists";
			break;
		}
		$userName = $_POST['user_name'];
		if (empty($userName)) {
			if (empty($_POST['email_address'])) {
				$_POST['email_address'] = getFieldFromId("email_address", "contacts", "contact_id", $contactId);
			}
			$userName = $_POST['email_address'];
		}
		if (empty($userName)) {
			$returnArray['info_message'] = "No user name";
			break;
		}
		$userName = strtolower($userName);
		$userId = getFieldFromId("user_id", "users", "user_name", $userName);
		if (!empty($userId)) {
			$returnArray['info_message'] = getSystemMessage("user_name_taken", "Username already in use");
			break;
		}

		$passwordSalt = getRandomString(64);
		$password = getRandomString(12);
		$resultSet = executeQuery("insert into users (client_id,contact_id,user_name,password_salt,password,date_created,force_password_change,notes) values (?,?,?,?,?,now(),1,'Created by API')",
			$GLOBALS['gClientId'], $contactId, $userName, $passwordSalt, $password);
		if (!empty($resultSet['sql_error'])) {
			$returnArray['info_message'] = "Unable to create user";
			break;
		}
		$userId = $resultSet['insert_id'];
		$resultSet = executeQuery("update users set password = ? where user_id = ?", hash("sha256", $userId . $passwordSalt . $password), $userId);
		updateUserSubscriptions($contactId);

		$emailId = getFieldFromId("email_id", "emails", "email_code", "API_CREATED_USER",  "inactive = 0");
		$body = "<p>Your account has been created. Your user name is '%user_name%'. Your temporary password is '%password%'.</p>";
		$subject = "Your account";
		$substitutions = array("password" => $password, "user_name" => $userName);
		sendEmail(array("email_id" => $emailId, "subject" => $subject, "body" => $body, "email_address" => $_POST['email_address'], "substitutions" => $substitutions, "contact_id" => $contactId, "email_code" => "API_CREATED_USER"));
		break;

	case "get_shipping_methods":
		$resultSet = executeReadQuery("select shipping_method_id,shipping_method_code,description from shipping_methods where inactive = 0 and internal_use_only = 0 and client_id = ?", $GLOBALS['gClientId']);
		$returnArray['shipping_methods'] = array();
		while ($row = getNextRow($resultSet)) {
			$returnArray['shipping_methods'][] = $row;
		}
		break;

    case "create_order_shipment":
        $orderId = getFieldFromId("order_id", "orders", "order_id", $_POST["order_id"]);
        if(empty($orderId)) {
            $returnArray['error_message'] = "order_id is required";
            break;
        }
        $locationId = getFieldFromId("location_id", "locations", "location_id", $_POST["location_id"]);
        $locationId = $locationId ?: getFieldFromId("location_id","locations","location_code", $_POST['location_code']);
        if(empty($locationId)) {
            $returnArray['error_message'] = "location_id or location_code is required";
            break;
        }
        $_POST['location_id'] = $locationId;
        if(empty($_POST['date_shipped']) || empty(strtotime($_POST['date_shipped']))) {
            $returnArray['error_message'] = "date_shipped is required";
            break;
        }
        $orderShipmentItems = array();
        foreach($_POST['order_items'] as $thisItem) {
            $orderItemRow = getRowFromId( "order_items", "order_item_id", $thisItem['order_item_id'], "order_id = ?", $orderId);
            $orderItemRow = $orderItemRow ?: getRowFromId( "order_items", "product_id", $thisItem['product_id'], "order_id = ?", $orderId);
            $orderItemRow = $orderItemRow ?: getRowFromId( "order_items", "product_id",
                getFieldFromId("product_id", "product_data", "upc_code", $thisItem['upc_code']), "order_id = ?", $orderId);
            if(empty($orderItemRow)) {
                $returnArray['error_message'] = "Order Item not found: " . jsonEncode($thisItem);
                break 2;
            }
            if($thisItem['quantity'] > $orderItemRow['quantity']) {
                $returnArray['error_message'] = sprintf("Quantity for order item ID %s (product ID %s) cannot be greater than quantity ordered",
                    $orderItemRow['order_item_id'], $orderItemRow['product_id']);
                break 2;
            }
            $orderShipmentItems[] = array_merge($orderItemRow, $thisItem);
        }
        if(empty($orderShipmentItems)) {
            $returnArray['error_message'] = "At least one item is required in order_items";
            break;
        }
        $shippingCarrierId = getFieldFromId("shipping_carrier_id","shipping_carriers","shipping_carrier_id",$_POST['shipping_carrier_id']);
        $shippingCarrierId = $shippingCarrierId ?: getFieldFromId("shipping_carrier_id","shipping_carriers","shipping_carrier_code",$_POST['shipping_carrier_code']);
        $shippingCarrierId = $shippingCarrierId ?: getFieldFromId("shipping_carrier_id","shipping_carriers","description",$_POST['shipping_carrier_code']);
        if(empty($shippingCarrierId) && !empty($_POST['shipping_carrier_id'])) {
            $returnArray['error_message'] = sprintf("Shipping carrier %s not found", $_POST['shipping_carrier_id']);
            break;
        }
        $_POST['shipping_carrier_id'] = $shippingCarrierId;
        $GLOBALS['gPrimaryDatabase']->startTransaction();
        $orderShipmentFieldNames = ["order_id", "location_id","date_shipped","full_name","shipping_charge","shipping_carrier_id","carrier_description","tracking_identifier",
            "label_url","secondary_shipment","no_notifications","notes","internal_use_only"];
        $nameValues = array_intersect_key($_POST,array_flip($orderShipmentFieldNames));
        $dataTable = new DataTable("order_shipments");
        $orderShipmentId = $dataTable->saveRecord(["name_values"=>$nameValues]);
        if(empty($orderShipmentId)) {
            $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
            $returnArray['error_message'] = $dataTable->getErrorMessage();
            break;
        }
        $dataTable = new DataTable("order_shipment_items");
        foreach($orderShipmentItems as $thisItem) {
            $thisItem['order_shipment_id'] = $orderShipmentId;
            $orderShipmentItemFieldNames = ["order_shipment_id", "order_item_id","quantity","cost"];
            $nameValues = array_intersect_key($thisItem,array_flip($orderShipmentItemFieldNames));
            if(!$dataTable->saveRecord(["name_values"=>$nameValues])) {
                $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                $returnArray['error_message'] = $dataTable->getErrorMessage();
                break 2;
            }
        }
        $GLOBALS['gPrimaryDatabase']->commitTransaction();
        // intentional fall through
    case "get_shipment_data":
		if (!empty($_POST['order_id'])) {
			$resultSet = executeReadQuery("select order_shipment_id,order_id,(select purchase_order_number from orders where order_id = order_shipments.order_id) purchase_order_number,date_shipped," .
				"(select shipping_carrier_code from shipping_carriers where shipping_carrier_id = order_shipments.shipping_carrier_id) shipping_carrier_code," .
				"tracking_identifier from order_shipments where order_id in (select order_id from orders where contact_id = ? and client_id = ?) and order_id = ?", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId'], $_POST['order_id']);
		} else {
			$resultSet = executeReadQuery("select order_shipment_id,order_id,(select purchase_order_number from orders where order_id = order_shipments.order_id) purchase_order_number,date_shipped," .
				"(select shipping_carrier_code from shipping_carriers where shipping_carrier_id = order_shipments.shipping_carrier_id) shipping_carrier_code," .
				"(select description from shipping_carriers where shipping_carrier_id = order_shipments.shipping_carrier_id) shipping_carrier," .
				"tracking_identifier from order_shipments where order_id in (select order_id from orders where contact_id = ? and client_id = ?) and date_shipped >= date_sub(current_date,interval " .
				(empty($_POST['days']) || !is_numeric($_POST['days']) || $_POST['days'] < 1 ? "1" : $_POST['days']) . " day)", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId']);
		}
		$shipmentData = array();
		while ($row = getNextRow($resultSet)) {
			$row['order_shipment_items'] = array();
			$itemSet = executeReadQuery("select order_items.product_id,upc_code,order_shipment_items.quantity from order_shipment_items join order_items using (order_item_id) join product_data using (product_id) where order_shipment_id = ?", $row['order_shipment_id']);
			while ($itemRow = getNextRow($itemSet)) {
				$row['order_shipment_items'][] = $itemRow;
			}
			$shipmentData[] = $row;
		}
		$returnArray['shipment_data'] = $shipmentData;
		break;

	case "set_order_status":
		$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id']);
		if (empty($orderId)) {
			$returnArray['error_message'] = "Invalid Order";
			break;
		}
		$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_id", $_POST['order_status_id']);
		if (empty($orderStatusId)) {
			$orderStatusId = getFieldFromId("order_status_id", "order_status", "order_status_code", $_POST['order_status_code']);
		}
		if (empty($orderStatusId)) {
			$returnArray['error_message'] = "Invalid Status";
		}
		Order::updateOrderStatus($orderId, $orderStatusId);
		break;

	case "mark_order_completed":
		$orderId = getFieldFromId("order_id", "orders", "order_id", $_POST['order_id']);
		if (empty($orderId)) {
			$returnArray['error_message'] = "Invalid Order";
			break;
		}
		Order::markOrderCompleted($orderId, date("Y-m-d"), false);
		break;
}
