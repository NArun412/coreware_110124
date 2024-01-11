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

class GiftCard {
	var $iGiftCardRow = false;
	var $iErrorMessage = false;
	var $iRefundPrefix = false;
	var $iCardPrefix = false;

	public function __construct($parameters = array()) {
		$this->iRefundPrefix = getPreference("REFUND_GIFT_CARD_PREFIX");
		$this->iCardPrefix = getPreference("CARD_GIFT_CARD_PREFIX");
        $userIdWhere = "";
        $pinWhere = "";
        if(empty($GLOBALS['gApiCall'])) {
            if(empty($parameters['user_id'])) {
                $userIdWhere = "and user_id is null";
            } else {
                $userIdWhere = sprintf("and (user_id is null or user_id = %s)", makeNumberParameter($parameters['user_id']));
            }
            if(empty($parameters['gift_card_pin'])) {
                $pinWhere = "and gift_card_pin is null";
            } else {
                $pinWhere = sprintf("and gift_card_pin = %s", makeParameter($parameters['gift_card_pin']));
            }
        }
		if (!empty($parameters['gift_card_id'])) {
			$this->iGiftCardRow = getRowFromId("gift_cards", "gift_card_id", $parameters['gift_card_id'], "inactive = 0 $userIdWhere");
		} else if (!empty($parameters['gift_card_number'])) {
            $this->iGiftCardRow = getRowFromId("gift_cards", "gift_card_number", $parameters['gift_card_number'], "inactive = 0 $userIdWhere $pinWhere");
		} else if (!empty($parameters['user_id'])) {
			if (empty($this->iCardPrefix) || empty($parameters['use_prefix'])) {
				$this->iGiftCardRow = getRowFromId("gift_cards", "user_id", $parameters['user_id'], "inactive = 0");
			} else if ($parameters['use_prefix']) {
				$this->iGiftCardRow = getRowFromId("gift_cards", "user_id", $parameters['user_id'], "inactive = 0 and gift_card_number like ?", $this->iCardPrefix . "%");
			} else if ($parameters['use_refund_prefix']) {
				$this->iGiftCardRow = getRowFromId("gift_cards", "user_id", $parameters['user_id'], "inactive = 0 and gift_card_number like ?", $this->iRefundPrefix . "%");
			}
		}
	}

	public function isValid() {
		return (!empty($this->iGiftCardRow));
	}

	public function getErrorMessage() {
		return $this->iErrorMessage;
	}

	public function adjustBalance($setBalance, $addedAmount, $logDescription = false, $orderId = "") {
		if (empty($this->iGiftCardRow)) {
			$this->iErrorMessage = "No gift card selected";
			return false;
		}
		if ($logDescription === false) {
			$logDescription = ($setBalance ? "Set Balance" : "Adjust Balance");
		}
		if (!is_numeric($addedAmount) || strlen($addedAmount) == 0) {
			$this->iErrorMessage = "Invalid Amount";
			return false;
		}
		if (empty($addedAmount)) {
			$addedAmount = 0;
		}
		if ($setBalance) {
			$newBalance = $addedAmount;
		} else {
			$newBalance = $this->iGiftCardRow['balance'] + $addedAmount;
		}
		$giftCards = new DataTable("gift_cards");
		$giftCards->setSaveOnlyPresent(true);
		if (!$giftCardId = $giftCards->saveRecord(array("name_values" => array("balance" => $newBalance), "primary_id" => $this->iGiftCardRow['gift_card_id']))) {
			$returnArray['error_message'] = "Unable to update balance";
			return false;
		}
		$this->iGiftCardRow['balance'] = $newBalance;
		executeQuery("insert into gift_card_log (gift_card_id, description, log_time, order_id, amount) values(?,?,now(),?,?)",
			$giftCardId, $logDescription, $orderId, $addedAmount);
		coreSTORE::giftCardNotification($this->iGiftCardRow['gift_card_number'], "adjust", ($setBalance ? "Set balance to " : "Adjust balance by ") . number_format($addedAmount, 2), $newBalance);
		return true;
	}

	public function getBalance() {
		return (empty($this->iGiftCardRow) ? false : $this->iGiftCardRow['balance']);
	}

	public function getGiftCardNumber() {
		return $this->iGiftCardRow['gift_card_number'];
	}

	public function createRefundGiftCard($giftCardNumber = false, $description = "Gift Card") {
		return $this->createGiftCard($giftCardNumber, $description, false);
	}

	public function createGiftCard($giftCardNumber = false, $description = "Gift Card", $refund = false) {
		if (empty($giftCardNumber)) {
			do {
				$giftCardNumber = ($refund ? $this->iRefundPrefix : $this->iCardPrefix) . strtoupper(getRandomString(25));
				$giftCardId = getFieldFromId("gift_card_id", "gift_cards", "gift_card_number", $giftCardNumber);
			} while (!empty($giftCardId));
		} else {
            $giftCardId = self::lookupGiftCard($giftCardNumber);
			if (!empty($giftCardId)) {
				$this->iErrorMessage = "Gift card already exists";
				return false;
			}
		}
		$giftCards = new DataTable("gift_cards");
		$giftCards->setSaveOnlyPresent(true);
		$giftCardId = $giftCards->saveRecord(array("name_values" => array("gift_card_number" => makeCode($giftCardNumber, ["allow_dash"=>true]), "description" => $description, "balance" => 0)));
		if (empty($giftCardId)) {
			$this->iErrorMessage = "Unable to create gift card";
			return false;
		}
		$this->iGiftCardRow = getRowFromId("gift_cards", "gift_card_id", $giftCardId);
		return $giftCardId;
	}

	public function issueGiftCards($orderItemId) {
		$returnArray = array();
		$orderItemRow = getRowFromId("order_items", "order_item_id", $orderItemId);
		if (empty($orderItemRow)) {
			$returnArray['error_message'] = "Invalid Order Item";
			return $returnArray;
		}
		$giftCardId = getFieldFromId("gift_card_id", "gift_cards", "order_item_id", $orderItemRow['order_item_id']);
		if (!empty($giftCardId)) {
			$returnArray['error_message'] = "Gift card already issued";
			return $returnArray;
		}

		$orderRow = getRowFromId("orders", "order_id", $orderItemRow['order_id']);
		$contactRow = Contact::getContact($orderRow['contact_id']);
		$productRow = ProductCatalog::getCachedProductRow($orderItemRow['product_id']);

		$productCatalog = new ProductCatalog();
		$cardNumber = $orderItemRow['quantity'];
		$giftCardValue = $productCatalog->getProductSalePrice($orderItemRow['product_id']);
		$giftCardValue = $giftCardValue['sale_price'] ?: $orderItemRow['sale_price'];

		$newDescription = "";
		while ($cardNumber > 0) {
			do {
				$giftCardNumber = $this->iCardPrefix . strtoupper(getRandomString(25));
				$giftCardId = getFieldFromId("gift_card_id", "gift_cards", "gift_card_number", $giftCardNumber);
			} while (!empty($giftCardId));
			$insertSet = executeQuery("insert into gift_cards (client_id, gift_card_number, description, balance,order_item_id) values (?,?,?,?,?)",
				$GLOBALS['gClientId'], $giftCardNumber, "Gift Card for Order #" . $orderRow['order_id'], $giftCardValue, $orderItemRow['order_item_id']);
			if (!empty($insertSet['sql_error'])) {
				break;
			} else {
				executeQuery("insert into gift_card_log (gift_card_id, description, log_time, order_id,amount) values (?,?,now(),?,?)",
					$insertSet['insert_id'], "Gift card ordered", $orderRow['order_id'], $giftCardValue);
			}
			$substitutions = array();
			$substitutions['amount'] = $giftCardValue;
			$substitutions['balance'] = $giftCardValue;
			$substitutions['description'] = $productRow['description'];
			$substitutions['product_code'] = $productRow['product_code'];
			$substitutions['gift_card_number'] = $giftCardNumber;

			$customFieldSet = executeQuery("select * from custom_fields where client_id = ? and custom_field_type_id = (select custom_field_type_id from custom_field_types where custom_field_type_code = 'ORDER_ITEMS')", $GLOBALS['gClientId']);
			while ($customFieldRow = getNextRow($customFieldSet)) {
				$substitutions[strtolower($customFieldRow['custom_field_code'])] = CustomField::getCustomFieldData($orderItemRow['order_item_id'], $customFieldRow['custom_field_code'], "ORDER_ITEMS");
			}
			$substitutions['from_name'] = $orderRow['full_name'];
			$substitutions['from_email_address'] = $contactRow['email_address'];

			$emailId = "";
			if (!empty($substitutions['recipient_email_address'])) {
				$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_GIFT_CARD_GIVEN",  "inactive = 0");
				$emailAddress = $substitutions['recipient_email_address'];
			} else {
				$emailAddress = $contactRow['email_address'];
			}
			if (empty($emailId)) {
				$emailId = getFieldFromId("email_id", "emails", "email_code", "RETAIL_STORE_GIFT_CARD",  "inactive = 0");
			}
			$newDescription .= (empty($newDescription) ? $orderItemRow['description'] . " - " : ", ") . $giftCardNumber;
			$subject = "Gift Card";
			$body = "Your gift card number is %gift_card_number%, for the amount of %amount%.";
			sendEmail(array("email_id" => $emailId, "subject" => $subject, "body" => $body, "substitutions" => $substitutions, "email_addresses" => $emailAddress, "contact_id" => $contactRow['contact_id']));
			$cardNumber--;
			coreSTORE::giftCardNotification($giftCardNumber, "purchased", "Purchase of gift card for " . number_format($giftCardValue, 2), $giftCardValue);
		}
		if (!empty($newDescription)) {
			$returnArray['product_description'] = $newDescription;
			executeQuery("update order_items set description = ? where order_item_id = ?", $newDescription, $orderItemRow['order_item_id']);
		}
		return $returnArray;
	}

    public static function lookupGiftCard($giftCardNumber) {
        // check both the raw and makeCode versions for duplicates
        $giftCardId = getFieldFromId("gift_card_id", "gift_cards", "client_id", $GLOBALS['gClientId'],
            "(gift_card_number = ? or gift_card_number = ?)", $giftCardNumber, makeCode($giftCardNumber, ["allow_dash"=>true]));
        return $giftCardId;
    }
}
