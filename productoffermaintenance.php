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

$GLOBALS['gPageCode'] = "PRODUCTOFFERMAINT";
require_once "shared/startup.inc";

class ProductOfferMaintenancePage extends Page {
    function setup() {
        $this->iTemplateObject->getTableEditorObject()->setDisabledFunctions(array("add"));
        $filters['hide_completed'] = array("form_label" => "Hide Completed", "where" => "date_completed is null", "data_type" => "tinyint", "set_default" => true);
        $this->iTemplateObject->getTableEditorObject()->addFilters($filters);
    }

    function onLoadJavascript() {
        ?>
        <script>
            $("#offer_action").change(function() {
                if ($(this).val() == "counter") {
                    $("#_counter_amount_row").removeClass("hidden");
                } else {
                    $("#_counter_amount_row").addClass("hidden");
                }
            })
        </script>
        <?php
    }

    function javascript() {
        ?>
        <script>
            function afterGetRecord(returnArray) {
                $("#_counter_amount_row").addClass("hidden");
            }
        </script>
        <?php
    }

    function massageDataSource() {
        $this->iDataSource->addColumnControl("product_id", "readonly", true);
        $this->iDataSource->addColumnControl("user_id", "readonly", true);
        $this->iDataSource->addColumnControl("user_id", "data_type", "user_picker");
        $this->iDataSource->addColumnControl("time_submitted", "readonly", true);
        $this->iDataSource->addColumnControl("amount", "readonly", true);
        $this->iDataSource->addColumnControl("offer_action_instructions", "data_type", "literal");
        $this->iDataSource->addColumnControl("offer_action_instructions", "initial_value", "<p>Accepting an offer will create a one-time use promotion code for this product for the offer amount and send the email 'ACCEPT_PRODUCT_OFFER' to the customer. If that email doesn't exist, a standard email will be sent.</p>" .
            "<p>Rejecting an offer will result in the email 'REJECT_PRODUCT_OFFER' (or a standard email) being sent to the customer telling them their offer was not accepted.</p>" .
            "<p>Counter offer will create a one-time use promotion code for the counter offer amount and the email 'COUNTER_PRODUCT_OFFER' being sent to the customer telling them their offer was not accepted, but you have included a promotion code for the counter offer. Again, if that email doesn't exist a standard email will be sent.</p>");
        $this->iDataSource->addColumnControl("offer_action", "data_type", "select");
        $this->iDataSource->addColumnControl("offer_action", "choices", array("accept" => "Accept Offer", "reject" => "Reject Offer","counter"=>"Make Counter Offer"));
        $this->iDataSource->addColumnControl("offer_action", "form_label", "Action");
        $this->iDataSource->addColumnControl("offer_action", "empty_text", "[None]");

        $this->iDataSource->addColumnControl("base_cost", "form_label", "Product Cost");
        $this->iDataSource->addColumnControl("base_cost", "data_type", "decimal");
        $this->iDataSource->addColumnControl("base_cost", "decimal_places", "2");
        $this->iDataSource->addColumnControl("base_cost", "readonly", true);

        $this->iDataSource->addColumnControl("counter_amount", "form_label", "Counter Offer Amount");
        $this->iDataSource->addColumnControl("counter_amount", "data_type", "decimal");
        $this->iDataSource->addColumnControl("counter_amount", "decimal_places", "2");
        $this->iDataSource->addColumnControl("counter_amount", "not_null", true);
    }

    function afterGetRecord(&$returnArray) {
        $returnArray['accept_offer'] = array("data_value" => "", "crc_value" => getCrcValue(""));
        $baseCost = getFieldFromId("base_cost","products","product_id",$returnArray['product_id']['data_value']);
        $returnArray['base_cost'] = array("data_value" => $baseCost,"crc_value" => getCrcValue($baseCost));
    }

    function internalCSS() {
        ?>
        <style>
            #_offer_action_instructions_row ul {
                list-style: disc;
                margin: 20px 0 20px 40px;
            }
        </style>
        <?php
    }

    function afterSaveChanges($nameValues) {
        $resultSet = executeQuery("select * from product_offers join users using (user_id) join contacts using (contact_id) where product_offer_id = ?", $nameValues['primary_id']);
        if (!$offerRow = getNextRow($resultSet)) {
	        return false;
        }
        $substitutions = $offerRow;
        $substitutions['time_submitted'] = date("m/d/Y g:i a", strtotime($substitutions['time_submitted']));
        $substitutions['full_name'] = getDisplayName($substitutions['contact_id']);
        $substitutions['product_description'] = getFieldFromId("description", "products", "product_id", $substitutions['product_id']);

        if ($nameValues['offer_action'] == "accept") {
            $promotionCode = $substitutions['promotion_code'] = strtoupper(getRandomString(24));
            $resultSet = executeQuery("insert into promotions (client_id,promotion_code,description,start_date,expiration_date,requires_user,user_id,maximum_usages) values (?,?,?,current_date,date_add(current_date,interval 14 day),1, ?,1)",
                $GLOBALS['gClientId'], $promotionCode, "Product Offer Accepted", $offerRow['user_id']);
            $promotionId = $resultSet['insert_id'];
            executeQuery("insert into promotion_rewards_products (promotion_id,product_id,maximum_quantity,amount) values (?,?,1,?)",
                $promotionId, $offerRow['product_id'], $offerRow['amount']);
            $emailParameters = array("email_address" => $substitutions['email_address'], "substitutions" => $substitutions);
            $emailId = getFieldFromId("email_id", "emails", "email_code", "ACCEPT_PRODUCT_OFFER",  "inactive = 0");
            if (empty($emailId)) {
                $emailParameters['subject'] = "Your offer has been accepted";
                $emailParameters['body'] = "<p>The offer you made on '%product_description%' in the amount of $%amount% has been accepted! To complete the purchase, log in to the online store and " .
                    "add the product to your shopping cart. In the checkout process, use the promotion code %promotion_code% to reduce the price to your offer amount.</p><p>Thank you for your business!</p>";
            } else {
                $emailParameters['email_id'] = $emailId;
            }
            $emailParameters['contact_id'] = $offerRow['contact_id'];
            sendEmail($emailParameters);
            $substitutions['notes'] = $substitutions['notes'] . (empty($substitutions['notes']) ? "" : "\n") . "Offer accepted by " . getUserDisplayName() . " on " . date("m/d/Y g:i a");
            if (empty($substitutions['date_completed'])) {
                $substitutions['date_completed'] = date("Y-m-d");
            }
        } else if ($nameValues['offer_action'] == "reject") {
            $emailParameters = array("email_address" => $substitutions['email_address'], "substitutions" => $substitutions);
            $emailId = getFieldFromId("email_id", "emails", "email_code", "REJECT_PRODUCT_OFFER",  "inactive = 0");
            if (empty($emailId)) {
                $emailParameters['subject'] = "Your offer has been declined";
                $emailParameters['body'] = "<p>The offer you made on '%product_description%' has been declined.</p><p>Thank you for your interest!</p>";
            } else {
                $emailParameters['email_id'] = $emailId;
            }
            $emailParameters['contact_id'] = $offerRow['contact_id'];
            sendEmail($emailParameters);
            $substitutions['notes'] = $substitutions['notes'] . (empty($substitutions['notes']) ? "" : "\n") . "Offer rejected by " . getUserDisplayName() . " on " . date("m/d/Y g:i a");
            if (empty($substitutions['date_completed'])) {
                $substitutions['date_completed'] = date("Y-m-d");
            }
        } else if ($nameValues['offer_action'] == "counter") {
            if (empty($nameValues['counter_amount'])) {
                return "No counter amount submitted";
            }
            $substitutions['submitted_amount'] = $substitutions['amount'];
            $substitutions['amount'] = $nameValues['counter_amount'];
            $promotionCode = $substitutions['promotion_code'] = strtoupper(getRandomString(24));
            $resultSet = executeQuery("insert into promotions (client_id,promotion_code,description,start_date,requires_user,user_id,maximum_usages) values (?,?,?,current_date,1, ?,1)",
                $GLOBALS['gClientId'], $promotionCode, "Product Counter Offer Proposed", $substitutions['user_id']);
            $promotionId = $resultSet['insert_id'];
            executeQuery("insert into promotion_rewards_products (promotion_id,product_id,maximum_quantity,amount) values (?,?,1,?)",
                $promotionId, $offerRow['product_id'], $substitutions['amount']);
            $emailParameters = array("email_address" => $substitutions['email_address'], "substitutions" => $substitutions);
            $emailId = getFieldFromId("email_id", "emails", "email_code", "COUNTER_PRODUCT_OFFER",  "inactive = 0");
            if (empty($emailId)) {
                $emailParameters['subject'] = "We've made a counter offer...";
                $emailParameters['body'] = "<p>The offer you made on '%product_description%' in the amount of $%submitted_amount% is a bit too low! However, we would like to propose a counter offer in the amount of $%amount%. " .
                    "To accept this offer and complete the purchase, log in to the online store and add the product to your shopping cart. In the checkout process, use the promotion code %promotion_code% to reduce the price to our counter offer amount.</p><p>Thank you for your business!</p>";
            } else {
                $emailParameters['email_id'] = $emailId;
            }
            $emailParameters['contact_id'] = $offerRow['contact_id'];
            sendEmail($emailParameters);
            $substitutions['notes'] = $substitutions['notes'] . (empty($substitutions['notes']) ? "" : "\n") . "Counter offer of " . number_format($nameValues['counter_amount'],2,".",",") . " made by " . getUserDisplayName() . " on " . date("m/d/Y g:i a");
            if (empty($substitutions['date_completed'])) {
                $substitutions['date_completed'] = date("Y-m-d");
            }
        }
        executeQuery("update product_offers set date_completed = ?,notes = ? where product_offer_id = ?",$substitutions['date_completed'],$substitutions['notes'],$substitutions['product_offer_id']);
        return true;
    }
}

$pageObject = new ProductOfferMaintenancePage("product_offers");
$pageObject->displayPage();
