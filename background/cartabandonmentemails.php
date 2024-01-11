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
		$this->iProcessCode = "cart_abandonment_emails";
	}

	function process() {
		$emailIds = array();
		$resultSet = executeQuery("select * from emails where email_code = 'CART_ABANDONED' and inactive = 0");
		while ($row = getNextRow($resultSet)) {
			$emailIds[$row['client_id']] = $row['email_id'];
		}
		$this->addResult(count($emailIds) . " cart abandonment emails found");

		$resultSet = executeQuery("select * from shopping_carts where shopping_cart_id in (select shopping_cart_id from shopping_cart_items) and start_time is not null and " .
			"abandon_email_sent = 0 and start_time < date_sub(now(),INTERVAL 2 hour) and start_time > date_sub(now(), interval 6 hour) order by client_id");
		$this->addResult($resultSet['row_count'] . " abandoned shopping carts found");
		$sendCount = 0;
        $clientSendMax = intval(getPreference("ABANDONED_CART_EMAILS_MAXIMUM"));
        $clientSendMax = $clientSendMax > 1 ? $clientSendMax : 100;
        $clientSendCount = 0;
        $clientOverageCount = 0;
		$invalidCount = 0;
		$errorCount = 0;
		$saveClientId = "";
		$inventoryCounts = array();
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			if ($GLOBALS['gClientId'] != $saveClientId) {
                if($clientOverageCount > 0) {
                    sendErrorLogEmail(["subject"=>"Large number of abandoned cart emails to be sent", "body"=>"The maximum number of abandoned cart emails to be sent was reached for client " . $GLOBALS['gClientRow']['client_code'] . "\n\n"
                        . $clientSendCount . " abandoned cart emails were sent. " . $clientOverageCount . " additional emails would have been sent."]);
                }
				$this->addResult("Sending emails for " . $GLOBALS['gClientRow']['client_code']);
				$saveClientId = $GLOBALS['gClientId'];
				$productCatalog = new ProductCatalog();
				$inventoryCounts = $productCatalog->getInventoryCounts(true, array(), true);
                $clientSendCount = 0;
                $clientOverageCount = 0;
			}
			if (empty($row['contact_id'])) {
				$row['contact_id'] = Contact::getUserContactId($row['user_id']);
			}
			$contactRow = Contact::getContact($row['contact_id']);
            $addressBlacklistId = getFieldFromId("address_blacklist_id", "address_blacklist", "postal_code", $contactRow['postal_code'], "city = ? and instr(?,address_1) > 0",
                $contactRow['city'], $contactRow['address_1']);
            if(!empty($addressBlacklistId)) {
                executeQuery("delete from shopping_cart_items where shopping_cart_id in (select shopping_cart_id from shopping_carts where contact_id = ?)", $row['contact_id']);
                executeQuery("delete from shopping_carts where contact_id = ?", $row['contact_id']);
                continue;
            }
			$shoppingCartItems = array();
			$inStock = false;
			$productSet = executeQuery("select *,products.description as product_description from products join shopping_cart_items using (product_id) left join product_data using (product_id) where shopping_cart_id = ?", $row['shopping_cart_id']);
			while ($productRow = getNextRow($productSet)) {
				$shoppingCartItems[] = $productRow;
				if ($inventoryCounts[$productRow['product_id']] > 0) {
					$inStock = true;
				}
			}
			if (!$inStock) {
				continue;
			}
            if($clientSendCount >= $clientSendMax) {
                $clientOverageCount++;
                continue;
            }
			$substitutions = $contactRow;

			$substitutions['domain_name'] = $domainName = getDomainName();
			$orderItems = "";
			$orderItemsTable = "<table id='order_items_table'><tr><th class='product-code-header'>Product Code</th><th class='upc-code-header'>UPC</th>" .
				"<th class='description-header'>Description</th><th class='quantity-header'>Quantity</th>" .
				"<th class='price-header'>Price</th><th class='extended-header'>Extended</th></tr>";
			foreach ($shoppingCartItems as $productRow) {
				if (empty($productRow['description'])) {
					$productRow['description'] = $productRow['product_description'];
				}
				$productUpcLink = (empty($productRow['upc_code']) ? "" : "<a href='" . $domainName . (empty($productRow['link_name']) ? "/product-details?id=" . $productRow['product_id'] : "/product/" . $productRow['link_name']) . "'>" . $productRow['upc_code'] . "</a>");
                $productImage = ProductCatalog::getProductImage($productRow['product_id'], array("image_type" => "thumbnail", "alternate_image_type" => "small"));
				$orderItems .= "<div class='order-item-line'>" .
                        "<span class='product-code'>" . $productRow['product_code'] . "</span>" .
                        "<span class='upc-code'>" . $productUpcLink . "</span>" .
                        "<span class='product-description'>" . $productRow['description'] . "</span>" .
                        "<span class='product-image'>" . (empty($productImage) ? "" : "<img src='" . $productImage . "'>") . "</span>" .
                        "<span class='product-quantity'>" . $productRow['quantity'] . "</span>" .
                        "<span class='product-price'>$" . number_format($productRow['sale_price'], 2) . "</span>" .
                        "<span class='product-extended'>$" . number_format(($productRow['quantity'] * $productRow['sale_price']), 2) . "</span>" .
					"</div>";
				$orderItemsTable .= "<tr class='order-item-row'>" .
                        "<td class='product-code'>" . $productRow['product_code'] . "</td>" .
                        "<td class='upc-code'>" . $productUpcLink . "</td>" .
                        "<td class='product-description' colspan='4'>" . $productRow['description'] . "</td>" .
                    "</tr>" .
					"<tr>" .
                        "<td class='product-image' colspan='3'>" . (empty($productImage) ? "" : "<img src='" . $productImage . "'>") . "</td>" .
                        "<td class='align-right product-quantity'>" . $productRow['quantity'] . "</td>" .
					    "<td class='align-right product-price'>$" . number_format($productRow['sale_price'], 2) . "</td>" .
					    "<td class='align-right product-extended'>$" . number_format(($productRow['quantity'] * $productRow['sale_price']), 2) . "</td>" .
                    "</tr>";
			}
			$orderItemsTable .= "</table>";
			$substitutions['order_items'] = $orderItems;
			$substitutions['order_items_table'] = $orderItemsTable;
			$notifyCrmResult = ShoppingCart::notifyCRM($row['shopping_cart_id'], $substitutions);
			if (!empty($notifyCrmResult)) {
				$this->addResult($notifyCrmResult);
			}
			if (empty($emailIds[$row['client_id']])) {
				continue;
			}
			if (empty($contactRow['email_address'])) {
				continue;
			}
			if (!empty(CustomField::getCustomFieldData($row['contact_id'], "NO_CART_ABANDONED_EMAILS", "CONTACTS", true))) {
				continue;
			}
			$result = sendEmail(array("email_id" => $emailIds[$row['client_id']], "email_address" => $contactRow['email_address'], "send_immediately" => true, "substitutions" => $substitutions));
			if ($result === true) {
				executeQuery("update shopping_carts set abandon_email_sent = 1 where shopping_cart_id = ?", $row['shopping_cart_id']);
				$sendCount++;
                $clientSendCount++;
			} elseif ($result == "No Email Address included") {
				$this->addResult("Skipping invalid email address " . $contactRow['email_address']);
				executeQuery("update shopping_carts set abandon_email_sent = 1 where shopping_cart_id = ?", $row['shopping_cart_id']);
				$invalidCount++;
			} else {
				$this->addResult("Unable to send email to " . $contactRow['email_address'] . ": " . $result);
				$errorCount++;
			}
		}
		$this->addResult($sendCount . " Emails sent.");
		$this->addResult($invalidCount . " Invalid email addresses skipped.");
		$this->addResult($errorCount . " errors occurred.");
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
