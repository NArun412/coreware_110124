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

/* text instructions
<p>The My Account page has Page text chunks that can be used. The code is important and must match the code listed here. The description is just informational and can contain anything. The value is what is used by the page.</p>
<ul>
    <li><strong>USER_CAN_DELETE_PAYMENT_METHOD_IN_USE</strong> - If this exists and is not "false," the "delete payment method" will be shown even if recurring payments exist for a payment method.</li>
    <li><strong>PHONE_REQUIRED</strong> - If this exists and is not "false," the Primary phone field will be required.</li>
    <li><strong>ADDITIONAL_CONTACT_FIELDS</strong> - A comma-separated list of additional fields from the contacts table to show on the page.  The field names must exist and must match exactly.</li>
    <li><strong>RETAIL_STORE_TERMS_CONDITIONS</strong> - Terms and Conditions text from checkout.  If the text chunk does not exist a fragment with the same name will be used.</li>
    <li><strong>EDUCATION_LINK</strong> - If the Education module is in use, put the base URL for a user to access their courses here.</li>
    <li><strong>VERIFY_BANK_ACCOUNT_NUMBER</strong> - If this exists and is not "false," the user will have to enter their bank account number twice to verify it is correct.</li>
</ul>
*/

$GLOBALS['gPageCode'] = "RETAILSTOREMYACCOUNT";
$GLOBALS['gCacheProhibited'] = true;
$GLOBALS['gSetRequiredFields'] = true;
$GLOBALS['gForceSSL'] = true;
require_once "shared/startup.inc";

class MyAccountPage extends Page {

    private $iCancelEventMessage = "";
    private $iCancelReservationMessage = "";
    private $iMakeAccountInactiveMessage = "";
	function setup() {
		if ($GLOBALS['gLoggedIn'] && function_exists("_localServerImportInvoices")) {
			_localServerImportInvoices($GLOBALS['gUserRow']['contact_id']);
		}
        $this->iCancelEventMessage = $this->getFragment("MY_ACCOUNT_CANCEL_EVENT_MESSAGE")
            ?: "Canceling an event registration cannot be undone. Your registration will be removed and, if a registration fee was paid, a gift card will be issued in the amount of the fees. Are you sure you want to cancel your registration?";
        $this->iCancelReservationMessage = $this->getFragment("MY_ACCOUNT_CANCEL_RESERVATION_MESSAGE")
            ?: "Canceling a reservation cannot be undone. Your reservation will be removed and, if a reservation fee was paid, a gift card will be issued in the amount of the fees. Are you sure you want to cancel your reservation?";
        $this->iMakeAccountInactiveMessage = $this->getFragment("MY_ACCOUNT_REMOVE_PAYMENT_METHOD_MESSAGE")
            ?: "Removing a payment account cannot be undone. Any recurring payments using that account will be ended. Are you sure you want to remove this payment account?";

	}

	function executePageUrlActions() {
		$returnArray = array();
		switch ($_GET['url_action']) {
			case "print_contact_identifier":
				$contactIdentifierTypeRow = getRowFromId("contact_identifier_types", "contact_identifier_type_id", $_GET['contact_identifier_type_id']);
				$fragmentContent = getFragmentFromId($contactIdentifierTypeRow['fragment_id']);
				$filename = strtolower($contactIdentifierTypeRow['contact_identifier_type_code']) . ".pdf";
				$substitutions = $GLOBALS['gUserRow'];
				$substitutions['description'] = $contactIdentifierTypeRow['description'];
				$substitutions['identifier_value'] = getFieldFromId("identifier_value", "contact_identifiers", "contact_id", $GLOBALS['gUserRow']['contact_id'], "contact_identifier_type_id = ?", $contactIdentifierTypeRow['contact_identifier_type_id']);
				outputPDF($fragmentContent, array("output_filename" => $filename, "substitutions" => $substitutions));
				exit;
			case "check_email_address":
				if (!empty($_GET['email_address'])) {
					if (!$GLOBALS['gLoggedIn']) {
						$existingContactId = getFieldFromId("contact_id", "contacts", "email_address", $_GET['email_address'],
							"contact_id in (select contact_id from users)");
						if (!empty($existingContactId)) {
							$returnArray['error_email_address_message'] = "A User already exists with this email address. Please log in.";
						}
					} else {
						$existingContactId = getFieldFromId("contact_id", "contacts", "email_address", $_GET['email_address'],
							"contact_id in (select contact_id from users where user_id <> ?)", $GLOBALS['gUserId']);
						if (!empty($existingContactId)) {
							$returnArray['error_email_address_message'] = "This email address is associated with another user.";
						}
					}
				}
				ajaxResponse($returnArray);
				break;
			case "make_account_inactive":
				$accountId = getFieldFromId("account_id", "accounts", "account_id", $_GET['account_id'], "contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
				if (empty($accountId)) {
					$returnArray['error_message'] = "Unable to remove account";
					ajaxResponse($returnArray);
					break;
				}
				$userCanDelete = $this->getPageTextChunk("USER_CAN_DELETE_PAYMENT_METHOD_IN_USE");
				if (!$userCanDelete) {
					$recurringPaymentId = getFieldFromId("recurring_payment_id", "recurring_payments", "account_id", $accountId);
					if (!empty($recurringPaymentId)) {
						$returnArray['error_message'] = "Unable to remove account because it is used in a recurring payment.";
						ajaxResponse($returnArray);
						break;
					}
				}
				$accountRow = getRowFromId("accounts", "account_id", $accountId);
				$merchantAccountId = eCommerce::getAccountMerchantAccount($accountRow['account_id']);
				$accountsDataSource = new DataSource("accounts");
				$accountsDataSource->setSaveOnlyPresent(true);
				$accountsDataSource->saveRecord(array("name_values" => array("inactive" => "1", "account_token" => "", "merchant_identifier" => "", "merchant_account_id" => ""), "primary_id" => $accountId));

				$merchantIdentifier = $accountRow['merchant_identifier'];
				if (empty($merchantIdentifier)) {
					$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $GLOBALS['gUserRow']['contact_id'], "merchant_account_id = ?", $merchantAccountId);
				}
				if (!empty($accountRow['account_token']) && !empty($merchantIdentifier)) {
					$eCommerce = eCommerce::getEcommerceInstance($merchantAccountId);
					if ($eCommerce) {
						$eCommerce->deleteCustomerPaymentProfile(array("merchant_identifier" => $merchantIdentifier,
							"account_token" => $accountRow['account_token']));
					}
				}
				$resultSet = executeQuery("select * from recurring_payments where account_id = ?", $accountId);
				$recurringPaymentsTable = new DataTable("recurring_payments");
				$recurringPaymentsTable->setSaveOnlyPresent(true);
				$contactSubscriptionsTable = new DataTable("contact_subscriptions");
				$contactSubscriptionsTable->setSaveOnlyPresent(true);
				while ($row = getNextRow($resultSet)) {
					$contactSubscriptionRow = getRowFromId("contact_subscriptions", "contact_subscription_id", $row['contact_subscription_id']);

					# Don't make the contact subscription inactive. By making the recurring payment inactive, the subscription will expire.

					if ($contactSubscriptionRow) {
						$subscriptionName = "Subscription '" . getFieldFromId("description", "subscriptions", "subscription_id", $contactSubscriptionRow['subscription_id']) . "'";
						sendEmail(array("subject" => $subscriptionName . " Cancelled", "body" => $subscriptionName . " cancelled by " . getDisplayName($GLOBALS['gUserRow']['contact_id'])
							. " (Contact ID " . $GLOBALS['gUserRow']['contact_id'] . ").\n\nReason: Cancelled because user deleted payment method.", "notification_code" => "SUBSCRIPTIONS"));
					}
				}
				updateUserSubscriptions($GLOBALS['gUserRow']['contact_id']);
				ajaxResponse($returnArray);
				break;
			case "save_payment_method":
				$_POST['payment_method_type_code'] = getFieldFromId("payment_method_type_code", "payment_method_types",
					"payment_method_type_id", getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id",
						$_POST['payment_method_id']));
				$isBankAccount = ($_POST['payment_method_type_code'] == "BANK_ACCOUNT");
				$forceSameAddress = getPreference("FORCE_SAME_BILLING_SHIPPING") && empty(CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "ALLOW_DIFFERENT_SHIPPING_ADDRESS"));
				if ($forceSameAddress || !empty($_POST['same_address'])) {
					$fields = array("address_1", "city", "state", "postal_code", "country_id");
					if ($forceSameAddress) {
						$fields[] = "first_name";
						$fields[] = "last_name";
					}
					foreach ($fields as $fieldName) {
						$_POST['billing_' . $fieldName] = $GLOBALS['gUserRow'][$fieldName];
					}
				}
				$requiredFields = array(
					"billing_first_name" => array(),
					"billing_last_name" => array(),
					"billing_address_1" => array(),
					"billing_city" => array(),
					"billing_state" => array("billing_country_id" => "1000"),
					"billing_postal_code" => array("billing_country_id" => "1000"),
					"billing_country_id" => array(),
					"payment_method_id" => array(),
					"account_number" => array("payment_method_type_code" => "CREDIT_CARD"),
					"expiration_month" => array("payment_method_type_code" => "CREDIT_CARD"),
					"expiration_year" => array("payment_method_type_code" => "CREDIT_CARD"),
					"card_code" => array("payment_method_type_code" => "CREDIT_CARD"),
					"routing_number" => array("payment_method_type_code" => "BANK_ACCOUNT"),
					"bank_account_number" => array("payment_method_type_code" => "BANK_ACCOUNT"));
				$missingFields = "";
				foreach ($requiredFields as $fieldName => $fieldInformation) {
					foreach ($fieldInformation as $checkFieldName => $checkValue) {
						if ($_POST[$checkFieldName] != $checkValue) {
							continue 2;
						}
					}
					if (empty($_POST[$fieldName])) {
						$missingFields .= (empty($missingFields) ? "" : ", ") . $fieldName;
					}
				}
				if (!empty($missingFields)) {
					$returnArray['error_message'] = "Required information is missing: " . $missingFields;
					ajaxResponse($returnArray);
					break;
				}
                $addressBlacklistId = getFieldFromId("address_blacklist_id", "address_blacklist", "postal_code", $_POST['billing_postal_code'], "city = ? and instr(?,address_1) > 0",
                    $_POST['billing_city'], $_POST['billing_address_1']);
                $addressBlacklistId = $addressBlacklistId ?: getFieldFromId("address_blacklist_id", "address_blacklist", "postal_code", $GLOBALS['gUserRow']['postal_code'], "city = ? and instr(?,address_1) > 0",
                    $GLOBALS['gUserRow']['city'], $GLOBALS['gUserRow']['address_1']);
                if (!empty($addressBlacklistId)) {
                    sleep(30);
                    $returnArray['error_message'] = "Charge failed: Transaction declined (8639)";
                    $cleanFields = ['account_number', 'expiration_month','expiration_year','card_code','routing_number','bank_account_number'];
                    addProgramLog("Save Payment method rejected because contact address matched blacklist.\n\n" . jsonEncode(array_diff_key($_POST, array_flip($cleanFields))));
                    ajaxResponse($returnArray);
                }
                $_POST['account_number'] = str_replace(" ", "", $_POST['account_number']);
				$_POST['account_number'] = str_replace("-", "", $_POST['account_number']);
				$_POST['bank_account_number'] = str_replace(" ", "", $_POST['bank_account_number']);
				$_POST['bank_account_number'] = str_replace("-", "", $_POST['bank_account_number']);
				$contactId = $GLOBALS['gUserRow']['contact_id'];
				$contactRow = $GLOBALS['gUserRow'];

				$eCommerce = eCommerce::getEcommerceInstance();
				$achMerchantAccount = getFieldFromId("merchant_account_id", "merchant_accounts", "merchant_account_code", "ACH", "inactive = 0");
				if (!empty($achMerchantAccount)) {
					$achECommerce = eCommerce::getEcommerceInstance($achMerchantAccount);
				}
				$useECommerce = ($achMerchantAccount && $isBankAccount ? $achECommerce : $eCommerce);

				if (!$useECommerce || empty($useECommerce)) {
					$returnArray['error_message'] = "Unable to connect to Merchant Services account. Please contact customer service.";
					ajaxResponse($returnArray);
					break;
				}
				if (!$useECommerce->hasCustomerDatabase()) {
					$returnArray['error_message'] = "Merchant Services account does not support saving payment methods. Please contact customer service.";
					ajaxResponse($returnArray);
					break;
				}

				$merchantIdentifier = getFieldFromId("merchant_identifier", "merchant_profiles", "contact_id", $contactId, "merchant_account_id = ?", $GLOBALS['gMerchantAccountId']);
				if (empty($merchantIdentifier)) {
					if (function_exists("_localMyAccountCustomPaymentFields")) {
						$additionalFields = _localInvoicePaymentsCustomPaymentFields($contactId);
						if (is_array($additionalFields)) {
							$contactRow = array_merge($contactRow, $additionalFields);
						}
					}
					$success = $useECommerce->createCustomerProfile($contactRow);
					$response = $useECommerce->getResponse();
					if ($success) {
						$merchantIdentifier = $response['merchant_identifier'];
					}
				}
				if (empty($merchantIdentifier)) {
					$returnArray['error_message'] = "Unable to create the Payment Method. Please contact customer service. #683";
					ajaxResponse($returnArray);
					break;
				}

				$testOrderId = date("Z") + 60000;
				if (!$isBankAccount) {
					$paymentArray = array("amount" => "1.00", "order_number" => $testOrderId, "description" => "Test Transaction", "authorize_only" => true,
						"first_name" => $_POST['billing_first_name'], "last_name" => $_POST['billing_last_name'], "business_name" => $_POST['billing_business_name'],
						"address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
						"postal_code" => $_POST['billing_postal_code'], "country_id" => $_POST['billing_country_id'],
						"email_address" => $contactRow['email_address'], "contact_id" => $contactId);
					if ($isBankAccount) {
						$paymentArray['bank_routing_number'] = $_POST['routing_number'];
						$paymentArray['bank_account_number'] = $_POST['bank_account_number'];
						$paymentArray['bank_account_type'] = strtolower(str_replace("_", "", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id'])));
					} else {
						$paymentArray['card_number'] = $_POST['account_number'];
						$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
						$paymentArray['card_code'] = $_POST['card_code'];
					}
					if (function_exists("_localMyAccountCustomPaymentFields")) {
						$additionalFields = _localInvoicePaymentsCustomPaymentFields($contactId);
						if (is_array($additionalFields)) {
							$paymentArray = array_merge($paymentArray, $additionalFields);
						}
					}

					$success = $useECommerce->authorizeCharge($paymentArray);
					$response = $useECommerce->getResponse();
					if ($success) {
						$paymentArray['transaction_identifier'] = $response['transaction_id'];
						$useECommerce->voidCharge($paymentArray);
					} else {
						$returnArray['error_message'] = "Authorization failed: " . $response['response_reason_text'];
						ajaxResponse($returnArray);
						break;
					}
				}

				$this->iDatabase->startTransaction();
				$accountLabel = $_POST['account_label'];
				if (empty($accountLabel)) {
					$accountLabel = getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']) . " - " . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4);
				}
				$fullName = $_POST['billing_first_name'] . " " . $_POST['billing_last_name'] . (empty($_POST['billing_business_name']) ? "" : ", " . $_POST['business_name']);
				$accountNumber = "XXXX-" . substr($_POST[($isBankAccount ? "bank_" : "") . "account_number"], -4);
				$resultSet = executeQuery("insert into accounts (contact_id,account_label,payment_method_id,full_name,account_number,expiration_date) values (?,?,?,?,?,?)", $contactId, $accountLabel, $_POST['payment_method_id'],
					$fullName, $accountNumber, date("Y-m-d", strtotime($_POST['expiration_month'] . "/01/" . $_POST['expiration_year'])));
				if (!empty($resultSet['sql_error'])) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = getSystemMessage("basic", $resultSet['sql_error']);
					ajaxResponse($returnArray);
					break;
				}
				$accountId = $resultSet['insert_id'];

				$paymentArray = array("contact_id" => $contactId, "account_id" => $accountId, "merchant_identifier" => $merchantIdentifier,
					"first_name" => $_POST['billing_first_name'], "last_name" => $_POST['billing_last_name'], "business_name" => $_POST['billing_business_name'],
					"address_1" => $_POST['billing_address_1'], "city" => $_POST['billing_city'], "state" => $_POST['billing_state'],
					"postal_code" => $_POST['billing_postal_code'], "country_id" => $_POST['billing_country_id']);
				if ($isBankAccount) {
					$paymentArray['bank_routing_number'] = $_POST['routing_number'];
					$paymentArray['bank_account_number'] = $_POST['bank_account_number'];
					$paymentArray['bank_account_type'] = str_replace(" ", "", lcfirst(ucwords(strtolower(str_replace("_", " ", getFieldFromId("payment_method_code", "payment_methods", "payment_method_id", $_POST['payment_method_id']))))));
				} else {
					$paymentArray['card_number'] = $_POST['account_number'];
					$paymentArray['expiration_date'] = $_POST['expiration_month'] . "/01/" . $_POST['expiration_year'];
					$paymentArray['card_code'] = $_POST['card_code'];
				}
				if (function_exists("_localMyAccountCustomPaymentFields")) {
					$additionalFields = _localInvoicePaymentsCustomPaymentFields($contactId);
					if (is_array($additionalFields)) {
						$paymentArray = array_merge($paymentArray, $additionalFields);
					}
				}

				$success = $useECommerce->createCustomerPaymentProfile($paymentArray);
				if (!$success) {
					$this->iDatabase->rollbackTransaction();
					$returnArray['error_message'] = "Unable to create account. Please contact customer service. #157";
					ajaxResponse($returnArray);
					break;
				}
				$count = 0;
				if (!empty($_POST['set_recurring'])) {
					$resultSet = executeQuery("update recurring_payments set account_id = ?, requires_attention = 0 where (end_date > current_date or end_date is null) and contact_id = ? and account_id is not null and account_id <> ?",
						$accountId, $contactId, $accountId);
					$count = $resultSet['affected_rows'];
					if ($count > 0) {
						$returnArray['info_message'] = "payment account added and updated " . $count . " recurring payment" . ($count == 1 ? "" : "s");
						ContactPayment::notifyCRM($contactId, true);
					}
				}

				$this->iDatabase->commitTransaction();

				$emailAddresses = getNotificationEmails("PAYMENT_METHOD_ADDED");
				if (!empty($emailAddresses)) {
					$subject = "Payment Method added";
					$body = "<p>A payment method was added by " . getDisplayName($GLOBALS['gUserRow']['contact_id']) . ", contact ID " . $GLOBALS['gUserRow']['contact_id'] . "." . (empty($count) ? "" : " " . $count . " recurring payment" . ($count == 1 ? "" : "s") .
							" were updated to use this new payment method.") . "</p>";
					sendEmail(array("body" => $body, "subject" => $subject, "email_addresses" => $emailAddresses));
				}

				addActivityLog("Added new payment method");
				ob_start();
				?>
                <tr data-account_id="<?= $accountId ?>">
                    <td class="account-label"><?= htmlText($accountLabel) ?></td>
                    <td class="account-type"><?= htmlText(getFieldFromId("description", "payment_methods", "payment_method_id", $_POST['payment_method_id']) . " - " . $accountNumber) ?></td>
                    <td></td>
                    <td class="align-center">
                        <button class='make-account-inactive'>Delete</button>
                    </td>
                </tr>
				<?php
				$returnArray['new_payment_method'] = ob_get_clean();

				ajaxResponse($returnArray);

				break;
			case "get_registration_events":
				$giftCardProductId = getFieldFromId("product_id", "products", "product_code", "GIFT_CARD");
				$resultSet = executeQuery("select *,event_registrants.order_id as registrant_order_id from event_registrants join events using (event_id) where event_registrants.contact_id = ? and events.client_id = ? order by start_date", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId']);
				if ($resultSet['row_count'] == 0) {
					ajaxResponse($returnArray);
					break;
				}
                $eventUrlAlias = getFieldFromId("url_alias_type_code", "url_alias_types", "client_id", $GLOBALS['gClientId'],
                "table_id = (select table_id from tables where table_name = 'events')");
				ob_start();
				?>
                <table class='grid-table' id='event_registrations_table'>
                    <tr>
                        <th id='event_type_header'>Event Type</th>
                        <th id='event_header'>Event</th>
                        <th id='event_date_header'>Event Date/Time</th>
                        <th id='event_registered_header'>Registered On</th>
                        <th id='event_status_header'>Status</th>
                        <th id='event_certificate_header'>Certificate</th>
                        <th id='event_button_header'>Action</th>
                    </tr>
					<?php
					while ($row = getNextRow($resultSet)) {
						$eventTypeRow = getRowFromId("event_types", "event_type_id", $row['event_type_id']);
						$cancelChangeContent = "";
						$canCancel = $canChange = (!empty($eventTypeRow));
						if (!empty($row['order_id'])) {
							$orderPromotionId = getFieldFromId("order_promotion_id", "order_promotions", "order_id", $row['order_id']);
							if (!empty($orderPromotionId)) {
								$canChange = false;
							}
						}
						$canUpdate = false;
						if (strlen($eventTypeRow['cancellation_days']) == 0 || $eventTypeRow['cancellation_days'] < 0) {
							$canCancel = false;
						}
						if (strlen($eventTypeRow['change_days']) == 0 || $eventTypeRow['change_days'] < 0) {
							$canChange = false;
						}
						if ($canCancel) {
							$cancellationDate = date("Y-m-d", strtotime($row['start_date'] . " -" . $eventTypeRow['cancellation_days'] . " days"));
							if (date("Y-m-d") >= $cancellationDate || (empty($giftCardProductId) && !empty($row['order_id']))) {
								$canCancel = false;
							}
						}
						if ($canChange) {
							$changeDate = date("Y-m-d", strtotime($row['start_date'] . " -" . $eventTypeRow['change_days'] . " days"));
							if (date("Y-m-d") >= $changeDate) {
								$canChange = false;
							}
						}
						if ($canChange) {
							$customFieldId = getFieldFromId("event_registration_custom_field_id", "event_registration_custom_fields",
								"event_id", $row['event_id']);
							$canUpdate = !empty($customFieldId);
						}
						if ($canChange) {
							$cancelChangeContent .= "<button class='change-event'>Change Date/Time</button>";
						}
						if ($canUpdate) {
							$cancelChangeContent .= "<button class='update-event'>Update Details</button>";
						}
						if ($canCancel) {
							$cancelChangeContent .= "<button class='cancel-event'>Cancel Registration</button>";
						}
						$dateChoiceValue = date("D, M j, Y", strtotime($row['start_date']));
						$hour = "";
						$hourSet = executeQuery("select * from event_facilities where date_needed = ? and event_id = ? order by hour", $row['start_date'], $row['event_id']);
						if ($hourRow = getNextRow($hourSet)) {
							$hour = $hourRow['hour'];
						}
						if (!empty($hour)) {
							$displayTime = Events::getDisplayTime($hour);
							$dateChoiceValue .= " " . $displayTime;
						}
                        $orderLink = "";
                        if(!empty($row['registrant_order_id'])) {
                            $orderLink = sprintf(" (<a href='/my-order-status#order%s'>Order %s</a>)", $row['registrant_order_id'], $row['registrant_order_id']);
                        }
                        $eventLink = "";
                        if(!empty($eventUrlAlias)) {
                            $linkName = getFieldFromId("link_name", "events", "event_id", $row['event_id']);
                            if(!empty($linkName)) {
                                $eventLink = sprintf("/%s/%s", $eventUrlAlias, $linkName);
                            }
                        }
                        if(empty($eventLink)) {
                            $eventDescription = htmlText($row['description']);
                        } else {
                            $eventDescription = sprintf("<a href='%s'>%s</a>", $eventLink, htmlText($row['description']));
                        }
						?>
                        <tr data-event_registrant_id="<?= $row['event_registrant_id'] ?>" data-event_id="<?= $row['event_id'] ?>">
                            <td class='event-type-cell'><?= (empty($row['event_type_id']) ? "N/A" : getFieldFromId("description", "event_types", "event_type_id", $row['event_type_id'])) ?></td>
                            <td class='event-cell'><?= $eventDescription ?></td>
                            <td class='event-date-cell'><?= $dateChoiceValue ?></td>
                            <td class='event-registered-cell'><?= date("D, M j, Y \a\\t g:i a", strtotime($row['registration_time'])) . $orderLink ?></td>
                            <td class='event-status-cell'><?= htmlText(getFieldFromId("description", "event_attendance_statuses", "event_attendance_status_id", $row['event_attendance_status_id'])) ?></td>
                            <td class='event-file-cell'><?= (empty($row['file_id']) ? "" : "<a href='/download.php?force_download=true&id=" . $row['file_id'] . "'><span class='fad fa-download'></span></a>") ?></td>
                            <td class='event-button-cell'><?= $cancelChangeContent ?></td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
				$returnArray['event_registrations_table'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "get_reservation_events":
				$giftCardProductId = getFieldFromId("product_id", "products", "product_code", "GIFT_CARD");
				$resultSet = executeQuery("select * from events where contact_id = ? and client_id = ? and event_id not in (select event_id from event_facility_recurrences) and start_date >= current_date and " .
					"(select count(distinct facility_id) from event_facilities where event_id = events.event_id) = 1 and event_id not in (select event_id from event_registrants) order by start_date", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId']);
				if ($resultSet['row_count'] == 0) {
					$returnArray['event_reservations_table'] = "<p>No Reservations Found</p>";
					ajaxResponse($returnArray);
					break;
				}
				$reservationIntroContent = getFragment("MY_ACCOUNT_RESERVATIONS");
				if (empty($reservationIntroContent)) {
					$reservationIntroContent = "<p>A gift card will be issued for canceled reservations which were paid.</p>";
				}
				ob_start();
				?>
				<?= makeHtml($reservationIntroContent) ?>
                <table class='grid-table' id='event_registrations_table'>
                    <tr>
                        <th id='reservations_event_type_header'>Event Type</th>
                        <th id='reservations_event_header'>Event</th>
                        <th id='reservations_facility_header'>Facility</th>
                        <th id='reservations_event_date_header'>Reservation Date/Time</th>
                        <th id='reservations_order_status'></th>
                        <th id='reservations_event_button_header'></th>
                    </tr>
					<?php
					while ($row = getNextRow($resultSet)) {
						$eventTypeRow = getRowFromId("event_types", "event_type_id", $row['event_type_id']);
						$hourSet = executeQuery("select * from event_facilities where event_id = ?", $row['event_id']);
						$facilityDescription = "";
						if ($hourRow = getNextRow($hourSet)) {
							$dateValue = date("m/d/Y", strtotime($hourRow['date_needed']));
							$hour = $hourRow['hour'];
							$facilityDescription = getFieldFromId("description", "facilities", "facility_id", $hourRow['facility_id']);
						} else {
							continue;
						}
						$workingHour = floor($hour);
						$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
						$displayMinutes = ($hour - $workingHour) * 60;
						$displayAmpm = ($hour == 0 ? "midnight" : ($hour == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
						$displayTime = $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm;
						$dateValue .= " " . $displayTime;
						$orderId = getFieldFromId("order_id", "orders", "order_id", $row['order_id'], "order_id in (select order_id from order_payments where amount > 0)");
						?>
                        <tr data-event_id="<?= $row['event_id'] ?>">
                            <td class='event-type-cell'><?= (empty($row['event_type_id']) ? "N/A" : getFieldFromId("description", "event_types", "event_type_id", $row['event_type_id'])) ?></td>
                            <td class='event-cell'><?= htmlText($row['description']) ?></td>
                            <td class='facility-cell'><?= htmlText($facilityDescription) ?></td>
                            <td class='event-date-cell'><?= $dateValue ?></td>
                            <td class='event-order-cell'><?= (empty($order) ? "" : "Paid") ?></td>
                            <td class='event-button-cell'>
                                <button class='cancel-reservation'>Cancel</button>
                            </td>
                        </tr>
						<?php
					}
					?>
                </table>
				<?php
				$returnArray['event_reservations_table'] = ob_get_clean();
				ajaxResponse($returnArray);
				break;
			case "get_event_options":
				$eventId = getFieldFromId("event_id", "events", "event_id", $_GET['event_id'], "start_date >= current_date");
				$eventRegistrantId = getFieldFromId("event_registrant_id", "event_registrants", "event_registrant_id", $_GET['event_registrant_id'], "contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
				$locationId = getFieldFromId("location_id", "locations", "location_id", $_GET['location_id']);
				if (empty($eventId) || empty($eventRegistrantId)) {
					$returnArray['error_message'] = "Event Not Found";
					ajaxResponse($returnArray);
					break;
				}
				$eventRow = getRowFromId("events", "event_id", $eventId);
				$eventTypeRow = getRowFromId("event_types", "event_type_id", $eventRow['event_type_id']);
				$canChange = (!empty($eventTypeRow));
				if (strlen($eventTypeRow['change_days']) == 0 || $eventTypeRow['change_days'] < 0) {
					$canChange = false;
				}
				if ($canChange) {
					$changeDate = date("Y-m-d", strtotime($eventRow['start_date'] . " -" . $eventTypeRow['change_days'] . " days"));
					if (date("Y-m-d") >= $changeDate) {
						$canChange = false;
					}
				}
				if (!$canChange) {
					$returnArray['error_message'] = "This event cannot be changed. #8642";
					ajaxResponse($returnArray);
					break;
				}
				$locations = array();
				$events = array();
				$resultSet = executeQuery("select * from events where event_type_id = ? and event_id <> ? and start_date >= current_date and inactive = 0" .
					($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and client_id = ? order by start_date,description", $eventTypeRow['event_type_id'], $eventId, $GLOBALS['gClientId']);
				if ($resultSet['row_count'] == 0) {
					$returnArray['error_message'] = "This event cannot be changed. #1838";
					ajaxResponse($returnArray);
					break;
				}
				while ($row = getNextRow($resultSet)) {
					$attendeeCounts = Events::getAttendeeCounts($row['event_id']);
					if ($attendeeCounts['registrants'] >= $attendeeCounts['attendees']) {
						continue;
					}
					if (!empty($locationId) && $row['location_id'] != $locationId) {
						continue;
					}

					$dateChoiceValue = date("D, M j, Y", strtotime($row['start_date']));
					$hour = "";
					$hourSet = executeQuery("select * from event_facilities where date_needed = ? and event_id = ? order by hour", $row['start_date'], $row['event_id']);
					if ($hourRow = getNextRow($hourSet)) {
						$hour = $hourRow['hour'];
					}
					if (!empty($hour)) {
						$workingHour = floor($hour);
						$displayHour = ($workingHour == 0 ? "12" : ($workingHour > 12 ? $workingHour - 12 : $workingHour));
						$displayMinutes = ($hour - $workingHour) * 60;
						$displayAmpm = ($hour == 0 ? "midnight" : ($hour == 12 ? "noon" : ($workingHour < 12 ? "am" : "pm")));
						$displayTime = $displayHour . ":" . str_pad($displayMinutes, 2, "0", STR_PAD_LEFT) . " " . $displayAmpm;
						$dateChoiceValue .= " " . $displayTime;
					}

					$events[] = array("key_value" => $row['event_id'], "description" => $row['description'] . ", " . $dateChoiceValue);
					if (!empty($row['location_id']) && !in_array($row['location_id'], $locations)) {
						$locations[] = $row['location_id'];
					}
				}
				if (!empty($locationId) || count($events) < 30 || count($locations) <= 1) {
					$returnArray['events'] = $events;
				} else {
					$resultSet = executeQuery("select * from locations where inactive = 0 and client_id = ? and location_id in (" . implode(",", $locations) . ") order by sort_order,description", $GLOBALS['gClientId']);
					$locations = array();
					while ($row = getNextRow($resultSet)) {
						$locations[] = array("key_value" => $row['location_id'], "description" => $row['description']);
					}
					$returnArray['locations'] = $locations;
				}
				ajaxResponse($returnArray);
				break;
			case "cancel_reservation":
				$eventId = getFieldFromId("event_id", "events", "event_id", $_GET['event_id'], "event_id not in (select event_id from event_facility_recurrences) and " .
					"start_date >= current_date and (select count(distinct facility_id) from event_facilities where event_id = events.event_id) = 1 and event_id not in (select event_id from event_registrants)");
				if (empty($eventId)) {
					$returnArray['error_message'] = "Reservation cannot be removed. Contact customer service.";
					ajaxResponse($returnArray);
					break;
				}
				if (function_exists("_localCancelReservation")) {
					$returnArray = _localCancelReservation(array("event_id" => $eventId));
					ajaxResponse($returnArray);
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$eventRow = getRowFromId("events", "event_id", $eventId);
				$eventTypeRow = getRowFromId("event_types", "event_type_id", $eventRow['event_type_id']);

				$cancelledOrderItems = array();
				$refundAmount = 0;
				$giftCardProductId = getFieldFromId("product_id", "products", "product_code", "GIFT_CARD");
				if (!empty($eventTypeRow['product_id']) && !empty($eventRow['order_id'])) {
					$orderItemId = getFieldFromId("order_item_id", "order_items", "order_id", $eventRow['order_id'], "product_id = ? and deleted = 0", $eventTypeRow['product_id']);
					if (empty($orderItemId)) {
						$returnArray['error_message'] = "This reservation is already cancelled";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$orderItemDataTable = new DataTable("order_items");
					$orderItemDataTable->setSaveOnlyPresent(true);
					if (!$orderItemDataTable->saveRecord(array("name_values" => array("deleted" => 1), "primary_id" => $orderItemId))) {
						$returnArray['error_message'] = "Unable to cancel reservation. Please contact customer service. #8752";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$cancelledOrderItems[] = $orderItemId;
					$orderItemRow = getRowFromId("order_items", "product_id", $eventTypeRow['product_id'], "order_id = ?", $eventRow['order_id']);
					$refundAmount = false;
					if (function_exists("calculateReservationRefundAmount")) {
						$refundAmount = calculateReservationRefundAmount($eventRow, $orderItemRow);
					}
					if ($refundAmount === false) {
						$refundAmount = ($orderItemRow['sale_price'] * $orderItemRow['quantity']) + $orderItemRow['tax_charge'];
					}
				}
				if ($refundAmount > 0 && empty($giftCardProductId)) {
					$returnArray['error_message'] = "Unable to cancel reservation. Please contact customer service. #6843";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}

				#delete from event_facilities, event_images, and events
				executeQuery("delete from event_facilities where event_id = ?", $eventRow['event_id']);
				executeQuery("delete from event_images where event_id = ?", $eventRow['event_id']);
				$deleteSet = executeQuery("delete from events where event_id = ?", $eventRow['event_id']);
				if (!empty($deleteSet['sql_error'])) {
					$returnArray['error_message'] = "Unable to cancel reservation. Please contact customer service. #6938";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}

				if ($refundAmount > 0) {
					$giftCard = new GiftCard(array("user_id" => $GLOBALS['gUserId'], "use_refund_prefix" => true));
					if (!$giftCard->isValid()) {
						$giftCard = new GiftCard();
						$giftCardId = $giftCard->createRefundGiftCard(false, "Gift card for cancelled reservation, Order ID " . $eventRow['order_id']);
						if (empty($giftCardId)) {
							$returnArray['error_message'] = "Unable to cancel event reservation. Please contact customer service. #5982";
							break;
						}
						if (!$giftCard->adjustBalance(true, $refundAmount, "Reservation cancelled", $eventRow['order_id'])) {
							$returnArray['error_message'] = "Unable to cancel event reservation. Please contact customer service. #4636";
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					} else if (!$giftCard->adjustBalance(false, $refundAmount, "Reservation cancelled", $eventRow['order_id'])) {
						$returnArray['error_message'] = "Unable to cancel event reservation. Please contact customer service. #5671";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
					$giftCardNumber = $giftCard->getGiftCardNumber();
					executeQuery("insert into order_notes (order_id,user_id,content) values (?,?,?)", $eventRow['order_id'], $GLOBALS['gUserId'], "Gift card '" . $giftCardNumber . "' issued for canceled reservation");
					executeQuery("insert into order_items (order_id,product_id,description,quantity,sale_price) values (?,?,?,1,?)", $eventRow['order_id'], $giftCardProductId, 'Gift Card - ' . $giftCardNumber, $refundAmount);

					if (!empty($cancelledOrderItems)) {
						executeQuery("update order_items set deleted = 1 where order_id = ? and order_item_id in (" . implode(",", $cancelledOrderItems) . ")", $eventRow['order_id']);
					}

					$emailId = getFieldFromId("email_id", "emails", "email_code", "REFUND_GIFT_CARD", "inactive = 0");
					$substitutions = $GLOBALS['gUserRow'];
					$substitutions['order_id'] = $eventRow['order_id'];
					$substitutions['amount'] = number_format($refundAmount, 2, ".", ",");
					$substitutions['description'] = "Gift Card for Canceled Reservation";
					$substitutions['product_code'] = "GIFT_CARD";
					$substitutions['gift_card_number'] = $giftCardNumber;
					$substitutions['gift_message'] = "";
					$subject = "Gift Card for canceled reservation";
					$body = "Your gift card number is %gift_card_number%, to which %amount% was added.";
					$copyEmailAddresses = getNotificationEmails("EVENT_REGISTRATION_CANCELLATION");
					sendEmail(array("email_id" => $emailId, "contact_id" => $GLOBALS['gUserRow']['contact_id'], "subject" => $subject, "body" => $body, "substitutions" => $substitutions, "email_addresses" => $GLOBALS['gUserRow']['email_address'], "cc_email_addresses" => $copyEmailAddresses));
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				$returnArray['info_message'] = "Your event reservation has been canceled.";

				ajaxResponse($returnArray);

				break;
			case "cancel_registration":
				$response = "";
				$eventId = getFieldFromId("event_id", "events", "event_id", $_GET['event_id'], "start_date >= current_date");
				$eventRegistrantId = getFieldFromId("event_registrant_id", "event_registrants", "event_registrant_id", $_GET['event_registrant_id'], "contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
				if (empty($eventId) || empty($eventRegistrantId)) {
					$returnArray['error_message'] = "Event Not Found";
					ajaxResponse($returnArray);
					exit;
				}
				if (function_exists("_localCancelRegistration")) {
					$returnArray = _localCancelRegistration(array("event_id" => $eventId, "event_registrant_id" => $eventRegistrantId));
					ajaxResponse($returnArray);
				}
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				$eventRegistrantRow = getRowFromId("event_registrants", "event_registrant_id", $eventRegistrantId);
				$eventRow = getRowFromId("events", "event_id", $eventId);
				$orderId = $eventRegistrantRow['order_id'];

				$refundAmount = 0;
				$giftCardProductId = getFieldFromId("product_id", "products", "product_code", "GIFT_CARD");
				if (!empty($eventRegistrantRow['order_id']) && !empty($eventRow['product_id'])) {
					// also reopen registration if cancellation brings class below attendee limit (line 451)
					$orderItemId = getFieldFromId("order_item_id", "order_items", "order_id", $eventRegistrantRow['order_id'], "product_id = ? and deleted = 0", $eventRow['product_id']);
					if (empty($orderItemId)) {
						$returnArray['error_message'] = "This registration is already cancelled";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						exit;
					}
					$orderItemDataTable = new DataTable("order_items");
					$orderItemDataTable->setSaveOnlyPresent(true);
					if (!$orderItemDataTable->saveRecord(array("name_values" => array("deleted" => 1), "primary_id" => $orderItemId))) {
						$returnArray['error_message'] = "Unable to cancel event registration. Please contact customer service. #9591";
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						exit;
					}
					$orderItemRow = getRowFromId("order_items", "product_id", $eventRow['product_id'], "order_id = ?", $eventRegistrantRow['order_id']);
					$refundAmount = false;
					if (function_exists("calculateRegistrationRefundAmount")) {
						$refundAmount = calculateRegistrationRefundAmount($eventRow, $orderItemRow);
					}
					if ($refundAmount === false) {
						$refundAmount = ($orderItemRow['sale_price'] * $orderItemRow['quantity']) + $orderItemRow['tax_charge'];
					}
				}
				$forceRefundNoGiftCard = getPreference("FORCE_REFUND_FOR_CLASS_CANCELLATION");
				if ($refundAmount > 0 && empty($giftCardProductId) && empty($forceRefundNoGiftCard)) {
					$returnArray['error_message'] = "Unable to cancel event registration. Please contact customer service. #1035";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					exit;
				}

				$eventRegistrantTable = new DataTable("event_registrants");
				if (!$eventRegistrantTable->deleteRecord(array("primary_id" => $eventRegistrantId))) {
					$returnArray['error_message'] = "Unable to cancel event registration. Please contact customer service. #9216";
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					exit;
				}
				if (!empty($eventRow['product_id'])) {
					$attendeeCounts = Events::getAttendeeCounts($eventRow['event_id']);
					if ($attendeeCounts['registrants'] >= $attendeeCounts['attendees']) {
						executeQuery("update products set non_inventory_item = 0 where product_id = ?", $eventRow['product_id']);
						executeQuery("update product_inventories set quantity = 0 where product_id = ?", $eventRow['product_id']);
					} else {
						executeQuery("update products set non_inventory_item = 1 where product_id = ?", $eventRow['product_id']);
					}
				}
				if ($refundAmount > 0) {
					if (empty($forceRefundNoGiftCard)) {
						$giftCard = new GiftCard(array("user_id" => $GLOBALS['gUserId'], "use_refund_prefix" => true));
						if (!$giftCard->isValid()) {
							$giftCard = new GiftCard();
							$giftCardId = $giftCard->createRefundGiftCard(false, "Gift card for cancelled event, Order ID " . $eventRegistrantRow['order_id']);
							if (empty($giftCardId)) {
								$returnArray['error_message'] = "Unable to cancel event registration. Please contact customer service. #1875";
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								ajaxResponse($returnArray);
								exit;
							}
							if (!$giftCard->adjustBalance(true, $refundAmount, "Reservation cancelled", $eventRegistrantRow['order_id'])) {
								$returnArray['error_message'] = "Unable to cancel event registration. Please contact customer service. #1875";
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								ajaxResponse($returnArray);
								exit;
							}
						} else if (!$giftCard->adjustBalance(false, $refundAmount, "Reservation cancelled", $eventRegistrantRow['order_id'])) {
							$returnArray['error_message'] = "Unable to cancel event registration. Please contact customer service. #1875";
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							exit;
						}
						$giftCardNumber = $giftCard->getGiftCardNumber();
						executeQuery("insert into order_notes (order_id,user_id,content) values (?,?,?)", $eventRegistrantRow['order_id'], $GLOBALS['gUserId'], "Gift card '" . $giftCardNumber . "' issued for canceled event");
						executeQuery("insert into order_items (order_id,product_id,description,quantity,sale_price) values (?,?,?,1,?)", $eventRegistrantRow['order_id'], $giftCardProductId, 'Gift Card - ' . $giftCardNumber, $refundAmount);

						$emailId = getFieldFromId("email_id", "emails", "email_code", "REFUND_GIFT_CARD", "inactive = 0");
						$substitutions = $GLOBALS['gUserRow'];
						$substitutions['order_id'] = $eventRegistrantRow['order_id'];
						$substitutions['amount'] = number_format($refundAmount, 2, ".", ",");
						$substitutions['description'] = "Gift Card for Canceled Event";
						$substitutions['product_code'] = "GIFT_CARD";
						$substitutions['gift_card_number'] = $giftCardNumber;
						$substitutions['gift_message'] = "";
						$subject = "Gift Card for canceled event";
						$body = "Your gift card number is %gift_card_number%, to which %amount% was added.";
						$copyEmailAddresses = getNotificationEmails("EVENT_REGISTRATION_CANCELLATION");
						sendEmail(array("email_id" => $emailId, "subject" => $subject, "contact_id" => $GLOBALS['gUserRow']['contact_id'], "body" => $body, "substitutions" => $substitutions, "email_addresses" => $GLOBALS['gUserRow']['email_address'], "cc_email_addresses" => $copyEmailAddresses));
					} else {

						# Refund the order to the original payment method

						Ecommerce::getClientMerchantAccountIds();
						$returnOrderItemIds = array($orderItemId);

						$resultSet = executeQuery("select * from order_payments join accounts using (account_id) where order_id = ? and amount > 0 and not_captured = 0 and deleted = 0", $orderId);
						if ($resultSet['row_count'] != 1) {
							$returnArray['error_message'] = "Unable to cancel event registration. Please contact customer service. #69382";
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							exit;
						}
						$orderPaymentRow = getNextRow($resultSet);
						$refundPaymentRow = $orderPaymentRow;

						$totalPaid = $orderPaymentRow['amount'] + $orderPaymentRow['tax_charge'] + $orderPaymentRow['shipping_charge'] + $orderPaymentRow['handling_charge'];

						if ($totalPaid < $refundAmount) {
							$returnArray['error_message'] = "Unable to cancel event registration. Please contact customer service. #6421";
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							exit;
						}
						$percentage = $refundAmount / $totalPaid;

						$refundPaymentRow['shipping_charge'] = round($orderPaymentRow['shipping_charge'] * $percentage);
						$refundPaymentRow['tax_charge'] = round($orderPaymentRow['tax_charge'] * $percentage);
						$refundPaymentRow['handling_charge'] = round($orderPaymentRow['handling_charge'] * $percentage);
						$refundPaymentRow['amount'] = $refundAmount - $refundPaymentRow['shipping_charge'] - $refundPaymentRow['tax_charge'] - $refundPaymentRow['handling_charge'];
						$refundPaymentRow['payment_time'] = date("Y-m-d H:i:s");
						$refundPaymentRow['notes'] = "Refund processed on My Account page";
						$orderPaymentDataTable = new DataTable("order_payments");
						if (!$orderPaymentId = $orderPaymentDataTable->saveRecord(array("name_values" => $refundPaymentRow, "primary_id" => ""))) {
							$returnArray['error_message'] = "Unable to process refund";
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							exit;
						}
						$eCommerce = eCommerce::getEcommerceInstance($orderPaymentRow['merchant_account_id']);
						if (!$eCommerce) {
							$returnArray['error_message'] = "Unable to connect to Merchant Gateway for refund for account " . $orderPaymentRow['account_number'] . " for " . number_format($refundAmount, 2, ".", ",") . ". (5922)";
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
                            exit;
						}
						$refundSuccess = false;
						if ($totalPaid == $refundAmount) {
							$refundSuccess = $eCommerce->voidCharge(array("transaction_identifier" => $orderPaymentRow['transaction_identifier'], "card_number"=>substr($orderPaymentRow['account_number'],-4)));
						}
						if (!$refundSuccess) {
							$refundSuccess = $eCommerce->refundCharge(array("transaction_identifier" => $orderPaymentRow['transaction_identifier'], "amount" => $refundAmount, "card_number" => substr($orderPaymentRow['account_number'], -4)));
						}
						$gatewayResponse = $eCommerce->getResponse();
						if (!$refundSuccess) {
							$returnArray['error_message'] = getFragment("MY_ACCOUNT_REFUND_ERROR_MESSAGE",array("amount"=>$refundAmount, "account_number"=>$orderPaymentRow['account_number']));
							if (empty($returnArray['error_message'])) {
								$returnArray['error_message'] = ($GLOBALS['gUserRow']['superuser_flag'] ? jsonEncode($gatewayResponse) : $gatewayResponse['response_reason_text']) . " Refund not successful for account " . $orderPaymentRow['account_number'] . " for " . number_format($refundAmount, 2, ".", ",") . ". (9421)";
							}
                            $GLOBALS['gPrimaryDatabase']->rollbackTransaction();
                            ajaxResponse($returnArray);
                            exit;
						}
						$response = "Refund for account " . $orderPaymentRow['account_number'] . " for " . number_format($refundAmount, 2, ".", ",") . " successfully processed.";

						$taxjarApiToken = getPreference("taxjar_api_token");
						$taxjarApiReporting = getPreference("taxjar_api_reporting");
						if (!empty($taxjarApiToken) && !empty($taxjarApiReporting)) {
							Order::reportRefundToTaxjar($orderId, $returnOrderItemIds, $refundAmount);
						}
						coreSTORE::orderNotification($orderId, "refund_issued");
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();
				Events::notifyCRM($eventId);
				$returnArray['info_message'] = "Your event registration has been canceled." . (empty($response) ? "" : " " . $response);

				ajaxResponse($returnArray);

				exit;
			case "change_registration":
				$eventId = getFieldFromId("event_id", "events", "event_id", $_POST['event_id'], "start_date >= current_date");
				$eventRegistrantId = getFieldFromId("event_registrant_id", "event_registrants", "event_registrant_id", $_POST['event_registrant_id'], "contact_id = ?", $GLOBALS['gUserRow']['contact_id']);
				if (empty($eventId) || empty($eventRegistrantId)) {
					$returnArray['error_message'] = "Event Not Found";
					ajaxResponse($returnArray);
					break;
				}
				$eventRow = getRowFromId("events", "event_id", $eventId);
				$eventTypeRow = getRowFromId("event_types", "event_type_id", $eventRow['event_type_id']);
				$canChange = (!empty($eventTypeRow));
				if (strlen($eventTypeRow['change_days']) == 0 || $eventTypeRow['change_days'] < 0) {
					$canChange = false;
				}
				if ($canChange) {
					$changeDate = date("Y-m-d", strtotime($eventRow['start_date'] . " -" . $eventTypeRow['change_days'] . " days"));
					if (date("Y-m-d") >= $changeDate) {
						$canChange = false;
					}
				}
				if (!$canChange) {
					$returnArray['error_message'] = "This event cannot be changed. #1133";
					ajaxResponse($returnArray);
					break;
				}
				$resultSet = executeQuery("select * from events where event_type_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and event_id = ? and start_date >= current_date and " .
					"client_id = ? order by start_date,description", $eventTypeRow['event_type_id'], $_POST['new_event_id'], $GLOBALS['gClientId']);
				if ($resultSet['row_count'] == 0) {
					$returnArray['error_message'] = "This event cannot be changed. #5842";
					ajaxResponse($returnArray);
					break;
				}
				if ($row = getNextRow($resultSet)) {
					$attendeeCounts = Events::getAttendeeCounts($row['event_id']);
					if ($attendeeCounts['registrants'] >= $attendeeCounts['attendees']) {
						$returnArray['error_message'] = "This event cannot be changed. #5842";
						ajaxResponse($returnArray);
						break;
					}
					executeQuery("update event_registrants set event_id = ? where event_registrant_id = ?", $_POST['new_event_id'], $eventRegistrantId);
				}
				Events::notifyCRM($eventId);
				Events::notifyCRM($_POST['new_event_id']);
				ajaxResponse($returnArray);
				break;
			case "update_account":
				$requiredFields = array(
					"first_name" => array(),
					"last_name" => array(),
					"address_1" => array(),
					"city" => array(),
					"country_id" => array(),
					"email_address" => array(),
					"state" => array("country_id" => "1000"),
					"postal_code" => array("country_id" => "1000"),
					"user_name" => array("create_account" => "1"));
				$missingFields = "";
				foreach ($requiredFields as $fieldName => $fieldInformation) {
					foreach ($fieldInformation as $checkFieldName => $checkValue) {
						if ($_POST[$checkFieldName] != $checkValue) {
							continue 2;
						}
					}
					if (empty($_POST[$fieldName])) {
						$missingFields .= (empty($missingFields) ? "" : ", ") . $fieldName;
					}
				}
				if (!empty($missingFields)) {
					$returnArray['error_message'] = "Required information is missing: " . $missingFields;
					ajaxResponse($returnArray);
					break;
				}
				$returnArray['create_account'] = $_POST['create_account'];
				$GLOBALS['gPrimaryDatabase']->startTransaction();
				if (!empty($_POST['create_account'])) {
					if (!empty($_POST['new_password']) && !isPCIPassword($_POST['new_password'])) {
						$minimumPasswordLength = getPreference("minimum_password_length");
						if (empty($minimumPasswordLength)) {
							$minimumPasswordLength = 10;
						}
						if (getPreference("PCI_COMPLIANCE")) {
							$noPasswordRequirements = false;
						} else {
							$noPasswordRequirements = getPreference("no_password_requirements");
						}
						$returnArray['error_message'] = getSystemMessage("password_minimum_standards", "Password does not meet minimum standards. Must be at least " . $minimumPasswordLength .
							" characters long" . ($noPasswordRequirements ? "" : " and include an upper and lowercase letter and a number"));
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}
				if (!$GLOBALS['gLoggedIn'] && !empty($_POST['new_password'])) {
					if (getPreference("PCI_COMPLIANCE")) {
						executeQuery("delete from user_passwords where time_changed < date_sub(current_date,interval 2 year)");
						$resultSet = executeQuery("select * from user_passwords where user_id = ?", $GLOBALS['gUserId']);
						while ($row = getNextRow($resultSet)) {
							$thisPassword = hash("sha256", $GLOBALS['gUserId'] . $row['password_salt'] . $_POST['new_password']);
							if ($thisPassword == $row['new_password']) {
								$returnArray['error_message'] = getSystemMessage("recent_password", "You cannot reuse a recent password.");
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
						}
					}
				}
				$contactId = $GLOBALS['gUserRow']['contact_id'];
				$contactTable = new DataTable("contacts");
				$contactTable->setSaveOnlyPresent(true);
				if (empty($contactId)) {
					$resultSet = executeQuery("select * from contacts where client_id = ? and email_address = ? and contact_id not in (select contact_id from accounts) and " .
						"contact_id not in (select contact_id from donations) and contact_id not in (select contact_id from orders) and contact_id not in (select contact_id from users)", $GLOBALS['gClientId'], $_POST['email_address']);
					if ($row = getNextRow($resultSet)) {
						$contactId = $row['contact_id'];
					}
					$_POST['date_created'] = date("Y-m-d");
					if (empty($_POST['source_id'])) {
						$_POST['source_id'] = getFieldFromId("source_id", "sources", "source_id", $_COOKIE['source_id'], "inactive = 0");
						if (empty($_POST['source_id'])) {
							$_POST['source_id'] = getSourceFromReferer($_SERVER['HTTP_REFERER']);
						}
					}
					if (empty($_POST['contact_type_id'])) {
						$_POST['contact_type_id'] = getFieldFromId("contact_type_id", "contact_types", "contact_type_code", $_POST['contact_type_code'], "inactive = 0");
					}
				} else {
					unset($_POST['source_id']);
					unset($_POST['contact_type_id']);
				}
				if (!$contactId = $contactTable->saveRecord(array("name_values" => $_POST, "primary_id" => $contactId))) {
					$returnArray['error_message'] = getSystemMessage("basic", $contactTable->getErrorMessage()) . ":" . $contactTable->getErrorMessage();
					$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
					ajaxResponse($returnArray);
					break;
				}
				$phoneUpdated = false;
				if (!empty($_POST["phone_number"])) {
					$resultSet = executeQuery("select * from phone_numbers where contact_id = ? and description = 'Primary'", $contactId);
					if ($row = getNextRow($resultSet)) {
						if ($_POST["phone_number"] != $row['phone_number']) {
							executeQuery("update phone_numbers set phone_number = ? where phone_number_id = ?",
								$_POST["phone_number"], $row['phone_number_id']);
							$phoneUpdated = true;
						}
					} else {
						executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,'Primary')",
							$contactId, $_POST["phone_number"]);
						$phoneUpdated = true;
					}
				} else {
					$resultSet = executeQuery("delete from phone_numbers where description = 'Primary' and contact_id = ?", $contactId);
					if ($resultSet['affected_rows'] > 0) {
						$phoneUpdated = true;
					}
				}
				if (!empty($_POST["cell_phone_number"])) {
					$resultSet = executeQuery("select * from phone_numbers where contact_id = ? and description = 'cell'", $contactId);
					if ($row = getNextRow($resultSet)) {
						if ($_POST["cell_phone_number"] != $row['phone_number']) {
							executeQuery("update phone_numbers set phone_number = ? where phone_number_id = ?",
								$_POST["cell_phone_number"], $row['phone_number_id']);
							$phoneUpdated = true;
						}
					} else {
						executeQuery("insert into phone_numbers (contact_id,phone_number,description) values (?,?,'cell')",
							$contactId, $_POST["cell_phone_number"]);
						$phoneUpdated = true;
					}
					$customFieldId = CustomField::getCustomFieldIdFromCode("RECEIVE_SMS");
					if (empty($customFieldId)) {
						$customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "CONTACTS");
						$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
							$GLOBALS['gClientId'], "RECEIVE_SMS", "Receive Text Notifications", $customFieldTypeId, "Receive Text Notifications");
						$customFieldId = $insertSet['insert_id'];
						executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,?,?)", $customFieldId, "data_type", "tinyint");
					}
					CustomField::setCustomFieldData($contactId, "RECEIVE_SMS", 'true');
				} else {
					$resultSet = executeQuery("delete from phone_numbers where description = 'cell' and contact_id = ?", $contactId);
					if ($resultSet['affected_rows'] > 0) {
						$phoneUpdated = true;
					}
				}
				if ($contactTable->getColumnsChanged() > 0 || $phoneUpdated) {
					addActivityLog("Updated Contact Info");
				}

				if (!empty($_POST['create_account']) || $GLOBALS['gLoggedIn']) {
					$userTable = new DataTable("users");
					$userTable->setSaveOnlyPresent(true);
					$saveData = array();

					if ($_POST['user_name'] != $GLOBALS['gUserRow']['user_name'] && !empty($_POST['user_name'])) {
						$checkUserId = getFieldFromId("user_id", "users", "user_name", strtolower($_POST['user_name']), "user_id <> ? and (client_id = ? or superuser_flag = 1)", $GLOBALS['gUserId'], $GLOBALS['gClientId']);
						if (!empty($checkUserId)) {
							$returnArray['error_message'] = "User name is already taken. Please choose another.";
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						$saveData['user_name'] = $_POST['user_name'];
					}

					if (!empty($_POST['email_address']) && !$GLOBALS['gLoggedIn']) {
						$existingContactId = getFieldFromId("contact_id", "contacts", "email_address", $_POST['email_address'], "contact_id in (select contact_id from users)");
						if (!empty($existingContactId)) {
							$returnArray['error_message'] = "A User already exists with this email address. Please log in.";
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}

					if (!empty($_POST['new_password'])) {
						$currentPassword = hash("sha256", $GLOBALS['gUserId'] . $GLOBALS['gUserRow']['password_salt'] . $_POST['current_password']);
						if ($GLOBALS['gLoggedIn'] && $currentPassword != $GLOBALS['gUserRow']['password']) {
							$returnArray['error_message'] = "Password cannot be reset because current password is not correct.";
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						} else {
							$saveData['password_salt'] = getRandomString(64);
							$saveData['password'] = hash("sha256", $GLOBALS['gUserId'] . $saveData['password_salt'] . $_POST['new_password']);
						}
					}

					if (array_key_exists("security_question_id", $_POST)) {
						$saveData['security_question_id'] = $_POST['security_question_id'];
					}
					if (array_key_exists("answer_text", $_POST)) {
						$saveData['answer_text'] = $_POST['answer_text'];
					}
					if (array_key_exists("secondary_security_question_id", $_POST)) {
						$saveData['secondary_security_question_id'] = $_POST['secondary_security_question_id'];
					}
					if (array_key_exists("secondary_answer_text", $_POST)) {
						$saveData['secondary_answer_text'] = $_POST['secondary_answer_text'];
					}
					$confirmUserAccount = getPreference("CONFIRM_USER_ACCOUNT");
					if (!empty($confirmUserAccount) && empty($GLOBALS['gUserId'])) {
						$randomCode = getRandomString(6, "23456789ABCDEFGHJKLMNPQRSTUVWXYZ");
						$saveData['verification_code'] = $randomCode;
						$saveData['locked'] = "1";
					}
					if (!empty($saveData)) {
						if (!$GLOBALS['gLoggedIn']) {
							$saveData['contact_id'] = $contactId;
							$saveData['date_created'] = date("Y-m-d");
						}
						if (!$userId = $userTable->saveRecord(array("name_values" => $saveData, "primary_id" => $GLOBALS['gUserId']))) {
							$returnArray['error_message'] = getSystemMessage("basic", $userTable->getErrorMessage());
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
						if (!empty($confirmUserAccount) && !empty($saveData['locked'])) {
							$confirmLink = "https://" . $_SERVER['HTTP_HOST'] . "/confirmuseraccount.php?user_id=" . $userId . "&hash=" . $randomCode;
							sendEmail(array("email_address" => $_POST['email_address'], "send_immediately" => true, "email_code" => "ACCOUNT_CONFIRMATION", "substitutions" => array("confirmation_link" => $confirmLink), "subject" => "Confirm Email Address", "body" => "<p>Click <a href='" . $confirmLink . "'>here</a> to confirm your email address and complete the creation of your user account.</p>"));
							logout();
						}
						if (array_key_exists("password", $saveData) && $GLOBALS['gLoggedIn']) {
							executeQuery("insert into user_passwords (user_id,password_salt,password,time_changed) values (?,?,?,now())", $GLOBALS['gUserId'], $saveData['password_salt'], $saveData['password']);
							executeQuery("update users set last_password_change = now() where user_id = ?", $GLOBALS['gUserId']);
							addActivityLog("Reset Password");
						} else if (!$GLOBALS['gLoggedIn']) {
							$currentPassword = hash("sha256", $userId . $saveData['password_salt'] . $_POST['new_password']);
							executeQuery("update users set password = ? where user_id = ?", $currentPassword, $userId);
						}
					}
					if ($userTable->getColumnsChanged() > 0) {
						addActivityLog("Updated User Information");
					}
				}
				foreach ($_POST as $fieldName => $fieldData) {
					if (substr($fieldName, 0, strlen("mailing_list_id_")) == "mailing_list_id_") {
						$mailingListId = getFieldFromId("mailing_list_id", "mailing_lists", "mailing_list_id", substr($fieldName, strlen("mailing_list_id_")));
						if (!empty($mailingListId)) {
							$mailingListRow = getRowFromId("contact_mailing_lists", "mailing_list_id", $mailingListId, "contact_id = ?", $contactId);
							if (!empty($mailingListRow)) {
								if (!empty($fieldData)) {
									if (!empty($mailingListRow['date_opted_out'])) {
										$contactMailingListSource = new DataSource("contact_mailing_lists");
										$contactMailingListSource->saveRecord(array("name_values" => array("date_opted_in" => date("Y-m-d"), "ip_address" => $_SERVER['REMOTE_ADDR'], "date_opted_out" => ""), "primary_id" => $mailingListRow['contact_mailing_list_id']));
										addActivityLog("Opted in to mailing list '" . getFieldFromId("description", "mailing_lists", "mailing_list_id", $mailingListId) . "'");
									}
								} else {
									if (empty($mailingListRow['date_opted_out'])) {
										$contactMailingListSource = new DataSource("contact_mailing_lists");
										$contactMailingListSource->saveRecord(array("name_values" => array("date_opted_out" => date("Y-m-d")), "primary_id" => $mailingListRow['contact_mailing_list_id']));
										executeQuery("update contact_mailing_lists set date_opted_out = now() where contact_mailing_list_id = ?",
											$mailingListRow['contact_mailing_list_id']);
										addActivityLog("Opted out of mailing list '" . getFieldFromId("description", "mailing_lists", "mailing_list_id", $mailingListId) . "'");
									}
								}
							} else {
								if (!empty($fieldData)) {
									$contactMailingListSource = new DataSource("contact_mailing_lists");
									$contactMailingListSource->saveRecord(array("name_values" => array("date_opted_in" => date("Y-m-d"), "ip_address" => $_SERVER['REMOTE_ADDR'], "contact_id" => $contactId, "mailing_list_id" => $mailingListId)));
									addActivityLog("Opted in to mailing list '" . getFieldFromId("description", "mailing_lists", "mailing_list_id", $mailingListId) . "'");
								}
							}
						}
					}
				}
				if (!empty($_POST['federal_firearms_licensee_id'])) {
					CustomField::setCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_FFL_DEALER", $_POST['federal_firearms_licensee_id']);
				}
				$customFields = CustomField::getCustomFields("contacts", "MY_ACCOUNT");
				foreach ($customFields as $thisCustomField) {
					$customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
					if (!$customField->saveData(array_merge($_POST, array("primary_id" => $contactId)))) {
						$returnArray['error_message'] = $customField->getErrorMessage();
						$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
						ajaxResponse($returnArray);
						break;
					}
				}

				$contactIdentifierDataTable = new DataTable("contact_identifiers");
				$contactIdentifierDataTable->setSaveOnlyPresent(true);
				$resultSet = executeQuery("select * from contact_identifier_types where client_id = ? and inactive = 0 and internal_use_only = 0 and user_editable = 1", $GLOBALS['gClientId']);
				while ($row = getNextRow($resultSet)) {
					$columnName = "contact_identifier_type_" . strtolower($row['contact_identifier_type_code']);
					if (array_key_exists($columnName, $_POST)) {
						if (empty($_POST[$columnName])) {
							if ($row['required']) {
								$returnArray['error_message'] = $row['description'] . " is required";
								$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
								ajaxResponse($returnArray);
								break;
							}
							continue;
						}
						$contactIdentifierId = getFieldFromId("contact_identifier_id", "contact_identifiers", "contact_id", $contactId, "contact_identifier_type_id = ?", $row['contact_identifier_type_id']);
						if (!$contactIdentifierDataTable->saveRecord(array("name_values" => array("identifier_value" => $_POST[$columnName], "contact_id" => $contactId, "contact_identifier_type_id" => $row['contact_identifier_type_id']), "primary_id" => $contactIdentifierId))) {
							$returnArray['error_message'] = $contactIdentifierDataTable->getErrorMessage();
							$GLOBALS['gPrimaryDatabase']->rollbackTransaction();
							ajaxResponse($returnArray);
							break;
						}
					}
				}

				$GLOBALS['gPrimaryDatabase']->commitTransaction();

				$zaiusApiKey = getPreference("ZAIUS_API_KEY");
				if (!empty($zaiusApiKey)) {
					$contactRow = Contact::getContact($contactId);
					$phoneNumber = Contact::getContactPhoneNumber($contactId);
					$customer = array(array("attributes" => array(
						"coreware_contact_id" => strval($contactRow['contact_id']),
						"first_name" => $contactRow['first_name'],
						"last_name" => $contactRow['last_name'],
						"email" => $contactRow['email_address'],
						"street1" => $contactRow['address_1'],
						"street2" => $contactRow['address_2'],
						"city" => $contactRow['city'],
						"state" => $contactRow['state'],
						"zip" => $contactRow['postal_code'],
						"country" => getFieldFromId("country_name", "countries", "country_id", $contactRow['country_id']),
						"phone" => $phoneNumber
					)));
					$zaiusObject = new Zaius($zaiusApiKey);
					$result = $zaiusObject->postApi("profiles", $customer);
					if (!$result) {
						addProgramLog("Zaius Error: " . $zaiusObject->getErrorMessage());
					}
				}

				$returnArray['info_message'] = ($GLOBALS['gLoggedIn'] ? "Changes saved" : "Account Created");
				if (!empty($userId)) {
					$emailId = getFieldFromId("email_id", "emails", "email_code", "NEW_ACCOUNT", "inactive = 0");
					if (!empty($emailId)) {
						$substitutions = $_POST;
						unset($substitutions['new_password']);
						unset($substitutions['password_again']);
						sendEmail(array("email_id" => $emailId, "contact_id" => $contactId, "substitutions" => $substitutions, "email_address" => $_POST['email_address']));
					}
					if (empty($confirmUserAccount) || !is_array($saveData) || empty($saveData['locked'])) {
						login($userId);
					}
				}
				if (!empty($confirmUserAccount)) {
					logout();
					$returnArray['info_message'] = "Please check your email and confirm your user account before you attempt to log in.";
				}
				ajaxResponse($returnArray);
				break;
		}
	}

	function javascript() {
		?>
        <script>
            let fflDealers = [];

            function getEventRegistrations() {
                $("#my_event_registrations_wrapper").html("");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_registration_events", function (returnArray) {
                    if ("event_registrations_table" in returnArray) {
                        $("#my_event_registrations_wrapper").html(returnArray['event_registrations_table']);
                    }
                });
            }

            function getEventReservations() {
                $("#my_event_reservations_wrapper").html("");
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_reservation_events", function (returnArray) {
                    if ("event_reservations_table" in returnArray) {
                        $("#my_event_reservations_wrapper").html(returnArray['event_reservations_table']);
                    }
                });
            }
        </script>
		<?php
	}

    function headerIncludes() {
        ?>
        <script src="/js/jquery.dirty.js"></script>
        <?php
    }

    function onLoadJavascript() {
		?>
        <script>
            $("#_edit_form").dirty({preventLeaving: true, ignoreFields: "ffl_radius"});
            $(document).on("click", ".print-contact-identifier", function () {
                const contactIdentifierTypeId = $(this).data("contact_identifier_type_id");
                if (empty(contactIdentifierTypeId)) {
                    return false;
                }
                document.location = "<?= $GLOBALS['gLinkUrl'] ?>?url_action=print_contact_identifier&contact_identifier_type_id=" + contactIdentifierTypeId;
                return false;
            });
            $("#view_terms_conditions").click(function () {
                $('#_terms_conditions_dialog').dialog({
                    closeOnEscape: true,
                    draggable: true,
                    modal: true,
                    resizable: true,
                    position: { my: "center top", at: "center top+5%", of: window, collision: "none" },
                    width: 1200,
                    title: 'Terms and Conditions',
                    buttons: {
                        Close: function (event) {
                            $("#_terms_conditions_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", "#same_address", function () {
                if ($(this).prop("checked")) {
                    $("#_billing_address").addClass("hidden");
                    $("#_billing_address").find("input,select").val("");
                } else {
                    $("#_billing_address").removeClass("hidden");
                }
            });
            $("#payment_method_id").change(function () {
                $(".payment-method-fields").hide();
                if (!empty($(this).val())) {
                    const paymentMethodTypeCode = $(this).find("option:selected").data("payment_method_type_code");
                    $("#payment_method_" + paymentMethodTypeCode.toLowerCase()).show();
                }
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
            $(document).on("click", "#add_payment_method", function () {
                $('#_new_payment_method_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 800,
                    title: 'New Payment Method',
                    buttons: {
                        Save: function (event) {
                            if ($("#_new_payment_method_form").validationEngine("validate")) {
                                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=save_payment_method", $("#_new_payment_method_form").serialize(), function (returnArray) {
                                    if (!("error_message" in returnArray)) {
                                        if ("new_payment_method" in returnArray) {
                                            $("#accounts_table").find("#add_payment_method_row").before(returnArray['new_payment_method']);
                                        }
                                        $("#_new_payment_method_form").clearForm();
                                        $("#_new_payment_method_dialog").dialog('close');
                                    }
                                });
                            }
                        },
                        Cancel: function (event) {
                            $("#_new_payment_method_form").clearForm();
                            $("#_new_payment_method_dialog").dialog('close');
                        }
                    }
                });
            });
            $(document).on("click", ".make-account-inactive", function () {
                const accountRow = $(this).closest("tr");
                const accountId = $(this).closest("tr").data("account_id");
                $("#_make_account_inactive_description").html($(this).closest("tr").find(".account-description").html());
                $('#_make_account_inactive_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Make Account Inactive',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=make_account_inactive&account_id=" + accountId, function (returnArray) {
                                if (!("error_message" in returnArray)) {
                                    accountRow.remove();
                                }
                            });
                            $("#_make_account_inactive_dialog").dialog('close');
                        },
                        Cancel: function (event) {
                            $("#_make_account_inactive_dialog").dialog('close');
                        }
                    }
                });
            });
            $(document).on("change", "#location_id", function () {
                $("#new_event_id").find("option[value!='']").remove();
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_event_options&event_id=" + $("#event_id").val() + "&event_registrant_id=" + $("#event_registrant_id").val() + "&location_id=" + $(this).val(), function (returnArray) {
                        if (!("error_message" in returnArray)) {
                            if ("events" in returnArray) {
                                $("#new_event_id").find("option[value!='']").remove();
                                for (let i in returnArray['events']) {
                                    let thisOption = $("<option></option>").attr("value", returnArray['events'][i]['key_value']).text(returnArray['events'][i]['description']);
                                    $("#new_event_id").append(thisOption);
                                }
                            }
                        }
                    });
                }
            });
            $(document).on("click", ".change-event", function () {
                $(this).addClass("hidden");
                $("#event_id").val($(this).closest("tr").data("event_id"));
                $("#event_registrant_id").val($(this).closest("tr").data("event_registrant_id"));
                loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=get_event_options&event_id=" + $(this).closest("tr").data("event_id") + "&event_registrant_id=" + $(this).closest("tr").data("event_registrant_id"), function (returnArray) {
                    $(".change-event").removeClass("hidden");
                    if (!("error_message" in returnArray)) {
                        if ("locations" in returnArray) {
                            $("#location_id").find("option[value!='']").remove();
                            $("#_location_id_row").removeClass("hidden");
                            for (let i in returnArray['locations']) {
                                let thisOption = $("<option></option>").attr("value", returnArray['locations'][i]['key_value']).text(returnArray['locations'][i]['description']);
                                $("#location_id").append(thisOption);
                            }
                        } else {
                            $("#_location_id_row").addClass("hidden");
                        }
                        if ("events" in returnArray) {
                            $("#new_event_id").find("option[value!='']").remove();
                            for (const i in returnArray['events']) {
                                let thisOption = $("<option></option>").attr("value", returnArray['events'][i]['key_value']).text(returnArray['events'][i]['description']);
                                $("#new_event_id").append(thisOption);
                            }
                        } else {
                            $("#new_event_id").find("option[value!='']").remove();
                        }
                        const $cellElement = $(this).closest("td");
                        $('#_change_event_dialog').dialog({
                            closeOnEscape: true,
                            draggable: false,
                            modal: true,
                            resizable: false,
                            position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                            width: 600,
                            title: 'Change Event',
                            buttons: {
                                Change: function (event) {
                                    if ($("#change_event_form").validationEngine("validate")) {
                                        loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=change_registration", $("#change_event_form").serialize(), function (returnArray) {
                                            if (!("error_message" in returnArray)) {
                                                getEventRegistrations();
                                            }
                                        });
                                        $("#_change_event_dialog").dialog('close');
                                    }
                                },
                                Cancel: function (event) {
                                    $("#_change_event_dialog").dialog('close');
                                }
                            }
                        });
                    }
                });
                return false;
            });
            $(document).on("click", ".cancel-reservation", function () {
                const $cellElement = $(this).closest("td");
                $('#_cancel_reservation_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Cancel Reservation',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=cancel_reservation&event_id=" + $cellElement.closest("tr").data("event_id"), function (returnArray) {
                                if (!("error_message" in returnArray)) {
                                    getEventReservations();
                                    $("#_cancel_reservation_dialog").dialog('close');
                                }
                            });
                        },
                        No: function (event) {
                            $("#_cancel_reservation_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", ".cancel-event", function () {
                const $cellElement = $(this).closest("td");
                $('#_cancel_event_dialog').dialog({
                    closeOnEscape: true,
                    draggable: false,
                    modal: true,
                    resizable: false,
                    position: { my: "center top", at: "center top+100px", of: window, collision: "none" },
                    width: 600,
                    title: 'Cancel Registration',
                    buttons: {
                        Yes: function (event) {
                            loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=cancel_registration&event_id=" + $cellElement.closest("tr").data("event_id") + "&event_registrant_id=" + $cellElement.closest("tr").data("event_registrant_id"), function (returnArray) {
                                if (!("error_message" in returnArray)) {
                                    getEventRegistrations();
                                }
                                $("#_cancel_event_dialog").dialog('close');
                            });
                        },
                        No: function (event) {
                            $("#_cancel_event_dialog").dialog('close');
                        }
                    }
                });
                return false;
            });
            $(document).on("click", ".update-event", function () {
                const $cellElement = $(this).closest("td");
                document.location = "/eventregistrationupdate.php?event_id=" + $cellElement.closest("tr").data("event_id") + "&event_registrant_id=" + $cellElement.closest("tr").data("event_registrant_id");
            });
            $(document).on("click", "#my_account_link", function () {
                $("#my_account_wrapper").removeClass("hidden");
                $("#my_payment_methods_wrapper").addClass("hidden");
                $("#my_event_registrations_wrapper").addClass("hidden");
                $("#my_event_reservations_wrapper").addClass("hidden");
                $("#my_courses_wrapper").addClass("hidden");
                return false;
            });
            $(document).on("click", "#my_event_registrations_link", function () {
                $("#my_event_registrations_wrapper").removeClass("hidden");
                $("#my_account_wrapper").addClass("hidden");
                $("#my_payment_methods_wrapper").addClass("hidden");
                $("#my_event_reservations_wrapper").addClass("hidden");
                $("#my_courses_wrapper").addClass("hidden");
                return false;
            });
            $(document).on("click", "#my_event_reservations_link", function () {
                $("#my_event_reservations_wrapper").removeClass("hidden");
                $("#my_account_wrapper").addClass("hidden");
                $("#my_payment_methods_wrapper").addClass("hidden");
                $("#my_event_registrations_wrapper").addClass("hidden");
                $("#my_courses_wrapper").addClass("hidden");
                return false;
            });
            $(document).on("click", "#payment_methods_link", function () {
                $("#my_payment_methods_wrapper").removeClass("hidden");
                $("#my_account_wrapper").addClass("hidden");
                $("#my_event_registrations_wrapper").addClass("hidden");
                $("#my_event_reservations_wrapper").addClass("hidden");
                $("#my_courses_wrapper").addClass("hidden");
                return false;
            });
            $(document).on("click", "#courses_link", function () {
                $("#my_courses_wrapper").removeClass("hidden");
                $("#my_payment_methods_wrapper").addClass("hidden");
                $("#my_account_wrapper").addClass("hidden");
                $("#my_event_registrations_wrapper").addClass("hidden");
                $("#my_event_reservations_wrapper").addClass("hidden");
                return false;
            });
            $(document).on("click", "#create_account", function () {
                if ($(this).prop("checked")) {
                    $("#create_account_wrapper").removeClass("hidden");
                } else {
                    $("#create_account_wrapper").addClass("hidden");
                }
            });
            $(document).on("click", ".save-changes", function () {
                if ($("#_edit_form").validationEngine("validate")) {
                    $(".save-changes").hide();
                    $("body").addClass("waiting-for-ajax");
                    $("#_edit_form").attr("action", "<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=update_account").attr("method", "POST").attr("target", "post_iframe").submit();
                    $("#_post_iframe").off("load");
                    $("#_post_iframe").on("load", function () {
                        $("body").removeClass("waiting-for-ajax").removeClass("no-waiting-for-ajax");
                        var returnText = $(this).contents().find("body").html();
                        const returnArray = processReturn(returnText);
                        if (returnArray === false) {
                            return;
                        }
                        if (!("error_message" in returnArray)) {
                            $("body").data("just_saved", "true");
                            if (typeof afterSaveChanges == "function") {
                                afterSaveChanges(!empty(returnArray['create_account']));
                            }
                            setTimeout(function () {
								<?php
								$transferLink = $_GET['referrer'];
								if (empty($transferLink)) {
									$transferLink = "/";
								}
								?>
                                goToLink("<?= $transferLink ?>");
                            }, 2000);
                        } else {
                            $(".save-changes").show();
                        }
                    });
                }
                return false;
            });
            $(document).on("blur", "#user_name", function () {
                $("#_user_name_message").removeClass("info-message").removeClass("error-message").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("/checkusername.php?ajax=true&user_name=" + $(this).val() + "&user_id=<?= $GLOBALS['gUserId'] ?>", function (returnArray) {
                        $("#_user_name_message").removeClass("info-message").removeClass("error-message");
                        if ("info_user_name_message" in returnArray) {
                            $("#_user_name_message").html(returnArray['info_user_name_message']).addClass("info-message");
                        }
                        if ("error_user_name_message" in returnArray) {
                            $("#_user_name_message").html(returnArray['error_user_name_message']).addClass("error-message");
                            $("#user_name").val("");
                            $("#user_name").focus();
                            setTimeout(function () {
                                $("#_edit_form").validationEngine("hideAll");
                            }, 10);
                        }
                    });
                } else {
                    $("#_user_name_message").val("");
                }
            });
            $(document).on("blur", "#email_address", function () {
                $("#_email_address_message").removeClass("info-message").removeClass("error-message").html("");
                if (!empty($(this).val())) {
                    loadAjaxRequest("<?= $GLOBALS['gLinkUrl'] ?>?ajax=true&url_action=check_email_address&email_address=" + $(this).val(), function (returnArray) {
                        $("#_email_address_message").removeClass("info-message").removeClass("error-message");
                        if ("error_email_address_message" in returnArray) {
                            $("#_email_address_message").html(returnArray['error_email_address_message']).addClass("error-message");
                            $("#email_address").focus();
                            setTimeout(function () {
                                $("#_edit_form").validationEngine("hideAll");
                            }, 10);
                        }
                    });
                } else {
                    $("#_user_name_message").val("");
                }
            });
            $(document).on("change", "#ffl_radius", function () {
                getFFLDealers();
            });
            $(document).on("click", ".ffl-dealer", function () {
                const fflId = $(this).data("federal_firearms_licensee_id");
                $("#federal_firearms_licensee_id").val(fflId).trigger("change");
                $("#selected_ffl_dealer").html(fflDealers[fflId]);
                $("#ffl_dealer_not_found").prop("checked", false);
            });
            $("#ffl_dealer_filter").keyup(function (event) {
                const textFilter = $(this).val().toLowerCase();
                if (empty(textFilter)) {
                    $("ul#ffl_dealers li").removeClass("hidden");
                } else {
                    $("ul#ffl_dealers li").each(function () {
                        const description = $(this).html().toLowerCase();
                        if (description.indexOf(textFilter) >= 0) {
                            $(this).removeClass("hidden");
                        } else {
                            $(this).addClass("hidden");
                        }
                    });
                }
            });
            $("#country_id").change(function () {
                if ($("#country_id").val() === "1000") {
                    $("#_state_row").addClass("hidden");
                    $("#_state_select_row").removeClass("hidden");
                } else {
                    $("#_state_row").removeClass("hidden");
                    $("#_state_select_row").addClass("hidden");
                }
            }).trigger("change");
            $("#state_select").change(function () {
                $("#state").val($(this).val());
            });
            $("#postal_code").change(function () {
                getFFLDealers();
            });
            if ($("#ffl_dealers_wrapper").length > 0 && !empty($("#postal_code").val())) {
                getFFLDealers();
            }
            getEventRegistrations();
            getEventReservations();
        </script>
		<?php
	}

	function mainContent() {
		$capitalizedFields = array();
		if (getPreference("USE_FIELD_CAPITALIZATION")) {
			$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
			while ($row = getNextRow($resultSet)) {
				$capitalizedFields[] = $row['column_name'];
			}
		}
		$phoneNumber = $otherPhoneNumber = $cellPhoneNumber = false;
		foreach ($GLOBALS['gUserRow']['phone_numbers'] as $thisPhone) {
			if ($thisPhone['description'] == "Primary" && empty($phoneNumber)) {
				$phoneNumber = $thisPhone['phone_number'];
			} else if (!in_array($thisPhone, array("cell", "mobile", "text")) && empty($otherPhoneNumber)) {
				$otherPhoneNumber = $thisPhone['phone_number'];
			} else if (in_array($thisPhone, array("cell", "mobile", "text")) && empty($cellPhoneNumber)) {
				$cellPhoneNumber = $thisPhone['phone_number'];
			}
		}
		if (empty($phoneNumber)) {
			$phoneNumber = $otherPhoneNumber;
		}
		$contactSubscriptionId = getFieldFromId("contact_subscription_id", "contact_subscriptions", "contact_id", $GLOBALS['gUserRow']['contact_id'], "inactive = 0 and subscription_id in (select subscription_id from subscriptions where inactive = 0 and internal_use_only = 0)");
		$eventRegistrations = 0;
		$eventReservations = 0;

		$invoices = 0;
		if ($GLOBALS['gLoggedIn']) {
			$resultSet = executeQuery("select count(*) from event_registrants where contact_id = ? and event_id in (select event_id from events where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . ")", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				$eventRegistrations = $row['count(*)'];
			}
			$resultSet = executeQuery("select count(*) from events where contact_id = ? and client_id = ? and event_id not in (select event_id from event_facility_recurrences) and start_date >= current_date and " .
				"(select count(distinct facility_id) from event_facilities where event_id = events.event_id) = 1 and event_id not in (select event_id from event_registrants)", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				$eventReservations = $row['count(*)'];
			}
			$resultSet = executeQuery("select count(*) from invoices where contact_id = ? and client_id = ? and internal_use_only = 0 and inactive = 0", $GLOBALS['gUserRow']['contact_id'], $GLOBALS['gClientId']);
			if ($row = getNextRow($resultSet)) {
				$invoices = $row['count(*)'];
			}
		}
		$urlPage = $_GET['url_page'] ?: "";
		$classEventRegistrations = "hidden";
		$classEventReservations = "hidden";
		$classPaymentMethods = "hidden";
		$classCourses = "hidden";
		$classMyAccount = "hidden";
		switch ($urlPage) {
			case "event_registrations":
				$classEventRegistrations = "";
				break;
			case "event_reservations":
				$classEventReservations = "";
				break;
			case "payment_methods":
				$classPaymentMethods = "";
				break;
			case "courses":
				$classCourses = "";
				break;
			default:
				$classMyAccount = "";
				break;
		}
		$resultSet = executeQuery("select * from courses where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and (product_id is not null or course_id in (select course_id from course_attendances where user_id = ?))", $GLOBALS['gClientId'], $GLOBALS['gUserId']);
		$courseCount = $resultSet['row_count'];
		?>

        <h1><span class="user-logged-in">My Account</span><span class="user-not-logged-in">Create an Account</span></h1>
        <p class='error-message align-center'></p>
		<?= $this->iPageData['content'] ?>

        <div id="_content_wrapper">
			<?php if ($GLOBALS['gLoggedIn']) { ?>
                <div id="button_wrapper">
                    <a href="#" id="my_account_link">My Account</a>
					<?php if ($eventRegistrations) { ?>
                        <a href="#" id="my_event_registrations_link">My Event Registrations</a>
					<?php } ?>
					<?php if ($eventReservations) { ?>
                        <a href="#" id="my_event_reservations_link">My Reservations</a>
					<?php } ?>
					<?php if (!empty($contactSubscriptionId)) { ?>
                        <a href='/customer-subscription-manager' id='manage_subscription_link'>Manage subscriptions</a>
					<?php } ?>
                    <a href="/my-order-status" id='order_history_link'>Order History</a>
					<?php if ($invoices) { ?>
                        <a href="/invoice-payments" id='invoice_payments_link'>Pay Open Invoices</a>
                        <a href="/invoice-history" id='invoice_history_link'>Invoice History</a>
					<?php } ?>
                    <a href="#" id='payment_methods_link'>Payment Methods</a>
					<?php if ($courseCount > 0) { ?>
                        <a href="#" id='courses_link'>Education Courses</a>
					<?php } ?>
                    <a href='#' class='hidden' id='wishlist_link'>Wish List</a>
                    <a href='#' class='hidden' id='recently_viewed_products_link'>Recently Viewed Products</a>
                    <a href='#' class='hidden' id='merchant_account_link'>Merchant Accounts</a>
                    <a href='#' class='hidden' id='product_reviews_link'>My Product Reviews</a>
                </div>
			<?php } ?>

            <div id="_forms_wrapper">
                <div id="my_account_wrapper" class="<?= $classMyAccount ?>">
                    <h2>Contact Information</h2>
                    <p id="my_account_error_message" class="error-message"></p>
                    <form id="_edit_form" enctype='multipart/form-data' method='post'>
						<?php
						echo createFormLineControl("contacts", "first_name", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['first_name']));
						echo createFormLineControl("contacts", "middle_name", array("not_null" => false, "initial_value" => $GLOBALS['gUserRow']['middle_name']));
						echo createFormLineControl("contacts", "last_name", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['last_name']));
						echo createFormLineControl("contacts", "business_name", array("not_null" => false, "initial_value" => $GLOBALS['gUserRow']['business_name']));
						?>
                        <h3>Primary Address</h3>
						<?php
						echo createFormLineControl("contacts", "address_1", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['address_1'], "classes" => "autocomplete-address"));
						echo createFormLineControl("contacts", "address_2", array("not_null" => false, "initial_value" => $GLOBALS['gUserRow']['address_2']));
						echo createFormLineControl("contacts", "city", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['city']));
						echo createFormLineControl("contacts", "state", array("form_label" => "State/Province", "not_null" => false, "initial_value" => $GLOBALS['gUserRow']['state']));
						?>
                        <div class="form-line" id="_state_select_row">
                            <label for="state_select" class="required-label">State</label>
                            <select tabindex="10" id="state_select" name="state_select" class="validate[required]">
                                <option value="">[Select]</option>
								<?php
								foreach (getStateArray() as $stateCode => $state) {
									?>
                                    <option value="<?= $stateCode ?>" <?= ($stateCode == $GLOBALS['gUserRow']['state'] ? " selected" : "") ?>><?= htmlText($state) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='clear-div'></div>
                        </div>
						<?php
						$pageControls = DataSource::returnPageControls();
						if (array_key_exists("country_id", $pageControls) && array_key_exists("initial_value", $pageControls['country_id'])) {
							$initialCountryId = $pageControls['country_id']['initial_value'];
						} else {
							$initialCountryId = 1000;
						}
						echo createFormLineControl("contacts", "postal_code", array("no_required_label" => true, "not_null" => true, "data-conditional-required" => "$(\"#country_id\").val() < 1002", "initial_value" => $GLOBALS['gUserRow']['postal_code']));
						echo createFormLineControl("contacts", "country_id", array("not_null" => true, "initial_value" => (empty($GLOBALS['gUserRow']['country_id']) ? $initialCountryId : $GLOBALS['gUserRow']['country_id'])));
						echo createFormLineControl("contacts", "email_address", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['email_address']));
						?><p id="_email_address_message"></p><?php
						if (!$GLOBALS['gLoggedIn']) {
							echo createFormLineControl("contacts", "email_address", array("column_name" => "email_address_again", "form_label" => "Re-enter Email Address", "validation_classes" => "equals[email_address]", "not_null" => true, "initial_value" => $GLOBALS['gUserRow']['email_address']));
						}
						echo createFormLineControl("contacts", "birthdate", array("not_null" => false, "initial_value" => (empty($GLOBALS['gUserRow']['birthdate']) ? "" : date("m/d/Y", strtotime($GLOBALS['gUserRow']['birthdate'])))));
						echo createFormLineControl("contacts", "image_id", array("column_name" => "image_id", "form_label" => "Profile Picture", "data_type" => "image_input", "subtype" => "image", "not_null" => false, "initial_value" => $GLOBALS['gUserRow']['image_id']));
						echo createFormLineControl("phone_numbers", "phone_number", array("form_label" => "Primary Phone", "not_null" => (!empty($this->getPageTextChunk("phone_required"))), "initial_value" => $phoneNumber));
						echo createFormLineControl("phone_numbers", "phone_number", array("column_name" => "cell_phone_number", "form_label" => "Cell Phone", "help_label" => "For receiving text notifications", "not_null" => false, "initial_value" => $cellPhoneNumber));
						$smsConsentText = $this->getFragment("SMS_CONSENT_TEXT");
						if (!empty($smsConsentText)) {
							echo makeHtml($smsConsentText);
						}

						$additionalContactFields = explode(",", $this->getPageTextChunk("ADDITIONAL_CONTACT_FIELDS"));
						$contactTable = new DataTable("contacts");
						foreach ($additionalContactFields as $contactField) {
							if ($contactTable->columnExists($contactField)) {
								echo createFormLineControl("contacts", $contactField, array("not_null" => false, "initial_value" => $GLOBALS['gUserRow'][$contactField]));
							}
						}

						$resultSet = executeQuery("select * from contact_identifier_types where client_id = ? and inactive = 0 and internal_use_only = 0", $GLOBALS['gClientId']);
						while ($row = getNextRow($resultSet)) {
							$identifierValue = getFieldFromId("identifier_value", "contact_identifiers", "contact_id", $GLOBALS['gUserRow']['contact_id'], "contact_identifier_type_id = ?", $row['contact_identifier_type_id']);
							if (empty($identifierValue) && empty($row['user_editable'])) {
								continue;
							}
							?>
                            <div class='form-line' id="_contact_identifier_type_<?= strtolower($row['contact_identifier_type_code']) ?>_row">
                                <label <?= (empty($row['required']) ? "" : "class='required-label'") ?>><?= $row['description'] ?></label>
                                <input <?= (empty($row['user_editable']) ? "readonly='readonly'" : "") ?> class='uppercase <?= (empty($row['required']) ? "" : "validate[required]") ?>' type="text" id="contact_identifier_type_<?= strtolower($row['contact_identifier_type_code']) ?>" name="contact_identifier_type_<?= strtolower($row['contact_identifier_type_code']) ?>" value="<?= $identifierValue ?>">
								<?php if (!empty($row['fragment_id']) && !empty($identifierValue)) { ?>
                                    <button class='print-contact-identifier' data-contact_identifier_type_id='<?= $row['contact_identifier_type_id'] ?>'>Print</button>
								<?php } ?>
                                <div class='clear-div'></div>
                            </div>
							<?php
						}
                        $hideSections = getPreference("MY_ACCOUNT_HIDE_SECTIONS");
						if ($GLOBALS['gLoggedIn']) {
                            if(stristr($hideSections,"files") === false) {
                                $resultSet = executeQuery("select contact_files.description,contact_files.file_id from contact_files join files using (file_id) where contact_files.contact_id = ? order by date_uploaded desc", $GLOBALS['gUserRow']['contact_id']);
                                if ($resultSet['row_count'] > 0) {
                                    ?>
                                    <h3><?= getLanguageText("Files") ?></h3>
                                    <?php
                                    while ($row = getNextRow($resultSet)) {
                                        ?>
                                        <p><a href='/download.php?id=<?= $row['file_id'] ?>'>Download
                                                '<?= $row['description'] ?>'</a></p>
                                        <?php
                                    }
                                }
                            }

                            if(stristr($hideSections,"attendances") === false) {
                                $resultSet = executeQuery("select * from event_types join contact_event_types using (event_type_id) where contact_event_types.contact_id = ? and contact_event_types.file_id is not null order by date_completed desc", $GLOBALS['gUserRow']['contact_id']);
                                if ($resultSet['row_count'] > 0) {
                                    ?>
                                    <h3><?= getLanguageText("Class Attendances") ?></h3>
                                    <?php
                                    while ($row = getNextRow($resultSet)) {
                                        ?>
                                        <p><a href='/download.php?id=<?= $row['file_id'] ?>'>Download certificate for '<?= $row['description'] ?>' completed on <?= date("m/d/Y", strtotime($row['date_completed'])) ?></a></p>
                                        <?php
                                    }
                                }
                            }

                            if(stristr($hideSections,"certifications") === false) {
                                $certificationTypes = array();
                                $resultSet = executeQuery("select * from contact_certifications join certification_types using (certification_type_id) where contact_certifications.contact_id = ? order by date_issued desc", $GLOBALS['gUserRow']['contact_id']);
                                while ($row = getNextRow($resultSet)) {
                                    if (!array_key_exists($row['certification_type_id'], $certificationTypes)) {
                                        $certificationTypes[$row['certification_type_id']] = $row;
                                    }
                                }
                                if (!empty($certificationTypes)) {
                                    ?>
                                    <h3><?= getLanguageText("Certifications") ?></h3>
                                    <?php
                                    foreach ($certificationTypes as $thisCertification) {
                                        ?>
                                        <p>Certification '<?= htmlText($thisCertification['description']) ?>', issued on <?= date("m/d/Y", strtotime($thisCertification['date_issued'])) ?><?= (empty($thisCertification['expiration_date']) ? "" : ", expires on " . date("m/d/Y", strtotime($thisCertification['expiration_date']))) ?></p>
                                        <?php
                                    }
                                }
                            }
                        }

                        $usesSso = ($GLOBALS["gLoggedIn"] && startsWith($GLOBALS['gUserRow']['password'], "SSO_"));
                        if(!$usesSso || empty(getPreference("MY_ACCOUNT_HIDE_ACCOUNT_INFO_FOR_SSO"))) {
                            if (!$GLOBALS['gLoggedIn']) {
                                if (!empty($_GET['account_optional'])) { ?>
                                    <div class="form-line" id="_create_account_row">
                                        <input tabindex="10" type="checkbox" id="create_account" name="create_account" value="1"><label class="checkbox-label" for="create_account">Create Account</label>
                                        <div class='clear-div'></div>
                                    </div>
                                    <div id="create_account_wrapper" class="hidden">
                                <?php } else { ?>
                                    <input type="hidden" id="create_account" name="create_account" value="1">
                                <?php }
                            } else { ?>
                                    <h2>Account Information</h2>
                            <?php }
                            ?>
                            <div class="form-line" id="_user_name_row">
                                <label for="user_name" class="required-label">User Name</label>
                                <?php if (!$GLOBALS['gLoggedIn']) { ?>
                                    <span class="help-label">We suggest you use your email address</span>
                                <?php } ?>
                                <input tabindex="10" type="text" autocomplete="chrome-off" autocomplete="off" class="code-value allow-dash lowercase validate[required]" size="40" maxlength="40" id="user_name" name="user_name" value="<?= $GLOBALS['gUserRow']['user_name'] ?>" <?= $usesSso ? "disabled" : "" ?>>
                                <p id="_user_name_message"></p>
                                <div class='clear-div'></div>
                            </div>
                            <?php if ($usesSso) { ?>
                                <p>Your login is handled by Single Sign-On (SSO).</p>
                            <?php } else { ?>
                                <div class="form-line user-logged-in" id="current_password_row">
                                    <label for="current_password">Current Password</label>
                                    <input tabindex="10" autocomplete="chrome-off" autocomplete="off" class="validate[required]" data-conditional-required="!empty($('#new_password').val())" type="password" size="40" maxlength="40" id="current_password" name="current_password" value=""><span class='fad fa-eye show-password'></span>
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_password_row">
                                    <label for="password" class="<?= ($GLOBALS['gLoggedIn'] ? "" : "required-label") ?>"><span class='user-logged-in'>New </span>Password</label>
                                    <?php
                                    $helpLabel = getFieldFromId("control_value", "page_controls", "page_id", $GLOBALS['gPageRow']['page_id'], "column_name = 'new_password' and control_name = 'help_label'");
                                    ?>
                                    <span class='help-label'><?= $helpLabel ?></span>
                                    <?php
                                    $minimumPasswordLength = getPreference("minimum_password_length");
                                    if (empty($minimumPasswordLength)) {
                                        $minimumPasswordLength = 10;
                                    }
                                    if (getPreference("PCI_COMPLIANCE")) {
                                        $noPasswordRequirements = false;
                                    } else {
                                        $noPasswordRequirements = getPreference("no_password_requirements");
                                    }
                                    ?>
                                    <input tabindex="10" autocomplete="chrome-off" autocomplete="off" class="<?= ($noPasswordRequirements ? "no-password-requirements " : "") ?>validate[custom[pciPassword],minSize[<?= $minimumPasswordLength ?>]<?= ($GLOBALS['gLoggedIn'] ? "" : ",required") ?>] password-strength" type="password" size="40" maxlength="40" id="new_password" name="new_password" value=""><span class='fad fa-eye show-password'></span>
                                    <div class='strength-bar-div hidden' id='new_password_strength_bar_div'>
                                        <p class='strength-bar-label' id='new_password_strength_bar_label'></p>
                                        <div class='strength-bar' id='new_password_strength_bar'></div>
                                    </div>
                                    <div class='clear-div'></div>
                                </div>

                                <div class="form-line" id="_password_again_row">
                                    <label for="password_again" class="<?= ($GLOBALS['gLoggedIn'] ? "" : "required-label") ?>">Re-enter <span class='user-logged-in'>New </span>Password</label>
                                    <input tabindex="10" autocomplete="chrome-off" autocomplete="off" type="password" class="validate[equals[new_password]]" size="40" maxlength="40" id="password_again" name="password_again" value=""><span class='fad fa-eye show-password'></span>
                                    <div class='clear-div'></div>
                                </div>

                                <?php if (getPreference("PCI_COMPLIANCE")) { ?>
                                    <p>For security reasons, we need you to select and answer a couple questions. If you ever forget your password, you'll need to be able to answer the security questions you select.</p>
                                    <?php
                                    echo createFormLineControl("users", "security_question_id", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['security_question_id']));
                                    echo createFormLineControl("users", "answer_text", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['answer_text']));
                                    echo createFormLineControl("users", "secondary_security_question_id", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['secondary_security_question_id']));
                                    echo createFormLineControl("users", "secondary_answer_text", array("not_null" => true, "initial_value" => $GLOBALS['gUserRow']['secondary_answer_text']));
                                }
                            }
                            if (!$GLOBALS['gLoggedIn'] && !empty($_GET['account_optional'])) { ?>
                            </div>
					        <?php }
                        }
                        if(stristr($hideSections,"mailing lists") === false) {
                            $resultSet = executeQuery("select * from mailing_lists where inactive = 0 and internal_use_only = 0 and client_id = ? order by sort_order,description", $GLOBALS['gClientId']);
                            if ($resultSet['row_count'] > 0) {
                                ?>
                                <h2>Opt-In Mailing Lists</h2>

                                <?php
                                while ($row = getNextRow($resultSet)) {
                                    $optedIn = getFieldFromId("contact_mailing_list_id", "contact_mailing_lists", "contact_id", $GLOBALS['gUserRow']['contact_id'],
                                        "mailing_list_id = ? and date_opted_out is null", $row['mailing_list_id']);
                                    ?>
                                    <div class="form-line" id="_mailing_list_id_<?= $row['mailing_list_id'] ?>_row">
                                        <label></label>
                                        <input type="checkbox" id="mailing_list_id_<?= $row['mailing_list_id'] ?>" name="mailing_list_id_<?= $row['mailing_list_id'] ?>" value="1" <?= (empty($optedIn) ? "" : "checked='checked'") ?>><label for="mailing_list_id_<?= $row['mailing_list_id'] ?>" class="checkbox-label"><?= htmlText($row['description']) ?></label>
                                        <div class='clear-div'></div>
                                    </div>
                                    <?php
                                }
                            }
                        }

						# Misc Info (including FFL)
                        if(function_exists("_localMyAccountGenerateMiscInformation")) {
                            $miscInfoSectionContent = _localMyAccountGenerateMiscInformation();
                        }
                        if(!empty($miscInfoSectionContent)) {
                            echo $miscInfoSectionContent;
                        } else {
                            $fflCustomFieldId = CustomField::getCustomFieldIdFromCode("DEFAULT_FFL_DEALER");
                            $fflRequiredProductTagId = getFieldFromId("product_tag_id", "product_tags", "product_tag_code", "FFL_REQUIRED", "inactive = 0 and cannot_sell = 0");
                            $customFields = CustomField::getCustomFields("contacts", "MY_ACCOUNT");
                            if (count($customFields) > 0 || (!empty($fflCustomFieldId) && !empty($fflRequiredProductTagId))) {
                                ?>
                                <div id='miscellaneous_information'>
                                    <h2>Miscellaneous Information</h2>
                                    <?php

                                    if (!empty($fflCustomFieldId) && !empty($fflRequiredProductTagId)) {
                                        $federalFirearmsLicenseeId = CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_FFL_DEALER");
                                        $displayName = "";
                                        if (!empty($federalFirearmsLicenseeId)) {
                                            $fflRow = (new FFL($federalFirearmsLicenseeId))->getFFLRow();
                                            if (!empty($fflRow)) {
                                                $displayName = $fflRow['licensee_name'] . (empty($fflRow['business_name']) || $fflRow['business_name'] == $fflRow['licensee_name'] ? "" : ", " . $fflRow['business_name']) . ", " .
                                                    $fflRow['address_1'] . ", " . $fflRow['city'];
                                            }
                                        }
                                        if (empty($displayName) && !empty($federalFirearmsLicenseeId)) {
                                            $federalFirearmsLicenseeId = "";
                                            CustomField::setCustomFieldData($GLOBALS['gUserRow']['contact_id'], "DEFAULT_FFL_DEALER", "");
                                        }
                                        ?>
                                        <div id="ffl_dealer_wrapper">
                                            <h3><?= getLanguageText("FFL Dealer") ?></h3>
                                            <p>Changing your default FFL does NOT affect existing orders. To change the FFL on an existing order, contact customer service.</p>
                                            <input type="hidden" id="federal_firearms_licensee_id" name="federal_firearms_licensee_id" class="show-next-section" value="<?= $federalFirearmsLicenseeId ?>">
                                            <p><?= getLanguageText("Your Default FFL Dealer") ?>: <span id="selected_ffl_dealer"><?= (empty($displayName) ? getLanguageText("No default selected") : $displayName) ?></span></p>
                                            <p id="ffl_dealer_count_paragraph"><span id="ffl_dealer_count"></span> <?= getLanguageText("Dealers found within") ?> <select id="ffl_radius">
                                                    <option value="25">25</option>
                                                    <option value="50" selected>50</option>
                                                    <option value="100">100</option>
                                                </select> <?= getLanguageText("miles. Choose one below") ?>.
                                            </p>
                                            <input tabindex="10" type="text" placeholder="<?= getLanguageText("Search/Filter Dealers") ?>" id="ffl_dealer_filter">
                                            <div id="ffl_dealers_wrapper">
                                                <ul id="ffl_dealers">
                                                </ul>
                                            </div>
                                        </div>
                                        <?php
                                    }

                                    foreach ($customFields as $thisCustomField) {
                                        $customField = CustomField::getCustomField($thisCustomField['custom_field_id']);
                                        if ($customField) {
                                            echo $customField->getControl(array("primary_id" => $GLOBALS['gUserRow']['contact_id']));
                                        }
                                    }
                                    ?>
                                </div>
                                <?php
                            }
                        }

						if (!$GLOBALS['gLoggedIn']) {
							$sectionText = $this->getPageTextChunk("retail_store_terms_conditions");
							if (empty($sectionText)) {
								$sectionText = $this->getFragment("retail_store_terms_conditions");
							}
							if (!empty($sectionText)) {
								?>
                                <div id="terms_and_conditions_section">
                                    <h3>Terms & Conditions</h3>
                                    <div class="form-line" id="_terms_conditions_row">
                                        <input type="checkbox" id="terms_conditions" name="terms_conditions" class="validate[required]" value="1" <?= ($GLOBALS['gInternalConnection'] ? " checked" : "") ?>><label for="terms_conditions" class="checkbox-label">I agree to the Terms and Conditions.</label> <a href='#' id="view_terms_conditions" class="clickable">Click here to view store Terms and Conditions.</a>
                                        <div class='clear-div'></div>
                                    </div>
                                </div>
								<?php
								echo "<div class='dialog-box' id='_terms_conditions_dialog'><div id='_terms_conditions_wrapper'>" . makeHtml($sectionText) . "</div></div>";
							}
						}
						?>
                    </form>
                    <div class="save-changes-wrapper">
                        <p id="button_error_message" class="error-message"></p>
                        <p>
                            <button class='save-changes'><?= getLanguageText("Save Changes") ?></button>
                        </p>
                    </div>
                </div>
                <div id="my_event_registrations_wrapper" class="<?= $classEventRegistrations ?>">
            </div>
            <div id="my_event_reservations_wrapper" class="<?= $classEventReservations ?>">
            </div>
            <div id="my_payment_methods_wrapper" class="<?= $classPaymentMethods ?>">
				<?php
				$accountsArray = array();
				$resultSet = executeQuery("select * from accounts where account_token is not null and inactive = 0 and contact_id = ? order by account_label", $GLOBALS['gUserRow']['contact_id']);
				while ($row = getNextRow($resultSet)) {
					$paymentMethodTypeId = getFieldFromId("payment_method_type_id", "payment_methods", "payment_method_id", $row['payment_method_id']);
					$paymentMethodTypeCode = getFieldFromId("payment_method_type_code", "payment_method_types", "payment_method_type_id", $paymentMethodTypeId);
					$row['payment_method_type_id'] = $paymentMethodTypeId;
					$row['payment_method_type_code'] = $paymentMethodTypeCode;
					$row['recurring_payment_id'] = getFieldFromId("recurring_payment_id", "recurring_payments", "account_id", $row['account_id'], "(end_date is null or end_date > current_date)");
					$accountsArray[] = $row;
				}
				$userCanDelete = $this->getPageTextChunk("USER_CAN_DELETE_PAYMENT_METHOD_IN_USE");
				$inactiveMessage = (!$userCanDelete ? "To make a payment method inactive, all recurring payments using that payment method must first be cancelled."
					: "Making a payment method inactive will also end any recurring payments using that payment method.");
				?>
                <h2>Payment Methods</h2>
                <p><?= $inactiveMessage ?></p>
                <table id="accounts_table" class='grid-table'>
                    <tr>
                        <th>Label</th>
                        <th>Payment Method</th>
                        <th>Expiration</th>
                        <th></th>
                        <th></th>
                    </tr>
					<?php
					foreach ($accountsArray as $accountsRow) {
						if ($accountsRow['inactive'] == 0 && !empty($accountsRow['account_token'])) {
							$notes = (empty($accountsRow['recurring_payment_id']) ? "" : "Used in Recurring Payment");
							if (!empty($accountsRow['expiration_date'])) {
								if (time() > strtotime($accountsRow['expiration_date'])) {
									$notes .= (empty($notes) ? "" : "; ") . "EXPIRED";
								} elseif (time() > strtotime($accountsRow['expiration_date'] . " - 30 days")) {
									$notes .= (empty($notes) ? "" : "; ") . "Expiring soon";
								}
							}
							?>
                            <tr data-account_id="<?= $accountsRow['account_id'] ?>">
                                <td class="account-label"><?= htmlText(empty($accountsRow['account_label']) ? $accountsRow['account_number'] : $accountsRow['account_label']) ?></td>
                                <td class="account-type"><?= htmlText(getFieldFromId("description", "payment_methods", "payment_method_id", $accountsRow['payment_method_id']) . " - " . $accountsRow['account_number']) ?></td>
                                <td class="account-expiration"><?= htmlText(empty($accountsRow['expiration_date']) ? "" : date("m/y", strtotime($accountsRow['expiration_date']))) ?></td>
                                <td><?= $notes ?></td>
                                <td class="align-center">
									<?php if (empty($accountsRow['recurring_payment_id']) || empty($userCannotDelete)) { ?>
                                        <button class='make-account-inactive'>Delete</button>
									<?php } ?>
                                </td>
                            </tr>
							<?php
						}
					}
					?>
                    <tr id="add_payment_method_row">
                        <td id='add_payment_method' colspan="5">Click here to add a new payment method</td>
                    </tr>
                </table>
            </div>
            <div id="my_courses_wrapper" class="<?= $classPaymentMethods ?>">
				<?php
				$resultSet = executeQuery("select * from courses where client_id = ? and inactive = 0" . ($GLOBALS['gInternalConnection'] ? "" : " and internal_use_only = 0") . " and (product_id is not null or course_id in (select course_id from course_attendances where user_id = ?)) order by sort_order,description", $GLOBALS['gClientId'], $GLOBALS['gUserId']);
				?>
                <table class='grid-table' id='courses_table'>
                    <tr>
                        <th>Course</th>
                        <th>Started</th>
                        <th>Completed</th>
                    </tr>
					<?php
					$educationLink = $this->getPageTextChunk("EDUCATION_LINK");
					while ($row = getNextRow($resultSet)) {
						if (!Education::canAccessCourse($row['course_id'])) {
							continue;
						}
						$attendanceSet = executeQuery("select min(start_date) as start_date,max(date_completed) as date_completed from course_attendances where course_id = ? and user_id = ?", $row['course_id'], $GLOBALS['gUserId']);
						if (!$attendanceRow = getNextRow($attendanceSet)) {
							$attendanceRow = array();
						}
						?>
                        <tr>
                            <td><?= (empty($educationLink) ? "" : "<a href='" . $educationLink . "?id=" . $row['course_id'] . "'>") ?><?= htmlText($row['description']) ?><?= (empty($educationLink) ? "" : "</a>") ?></td>
                            <td><?= (empty($attendanceRow['start_date']) ? "" : date("m/d/Y", strtotime($attendanceRow['start_date']))) ?></td>
                            <td><?= (empty($attendanceRow['date_completed']) ? "" : date("m/d/Y", strtotime($attendanceRow['date_completed']))) ?></td>
                        </tr>
						<?php
					}
					?>
                </table>
            </div>
        </div>
        </div>
		<?php
		echo $this->iPageData['after_form_content'];
		return true;
	}

	function internalCSS() {
		?>
        <style>
            #_content_wrapper {
                display: flex;
                align-items: flex-start;
                justify-content: center;
                margin: auto;
                max-width: 1024px;
                position: relative;
                width: 100%;
            }

            #button_wrapper {
                flex: 0 0 300px;
                padding: 0 0 20px;
                display: flex;
                align-items: stretch;
                flex-direction: column;
            }

            #button_wrapper a {
                display: block;
                padding-bottom: 10px;
                margin: 5px;
                flex: 0 0 auto;
            }

            #_terms_conditions_wrapper {
                max-height: 80vh;
                height: 800px;
                overflow: scroll;
            }

            #_expiration_month_row select {
                width: 200px;
                display: inline-block;
                margin-right: 20px;
            }

            .payment-method-fields {
                display: none;
            }

            #cvv_image {
                position: absolute;
                top: 0;
                height: 26px;
            }

            .strength-bar-div {
                height: 24px;
                width: 200px;
                margin: 10px 0 0;
                display: block;
                top: 5px;
            }

            p.strength-bar-label {
                font-size: .6rem;
                margin: 0;
                padding: 0;
            }

            .strength-bar {
                font-size: 1px;
                height: 8px;
                width: 10px;
            }

            #add_payment_method {
                background-color: #c8c8c8;
                text-align: center;
                cursor: pointer;
                font-weight: bold;
            }

            #add_payment_method:hover {
                background-color: #dcdcdc;
                color: #000064;
            }

            #ffl_dealers_wrapper {
                max-width: 600px;
                overflow: auto;
                height: auto;
            }

            #ffl_dealers li {
                padding: 5px 10px;
                cursor: pointer;
                background-color: #dcdcdc;
                border-bottom: 1px solid #c8c8c8;
                line-height: 1.2;
            }

            #ffl_dealers li:hover {
                background-color: #b4bec8;
            }

            #ffl_dealers li.preferred {
                font-weight: 900;
            }

            #ffl_dealers li.have-license {
                background-color: #b4e6b4;
            }

            #selected_ffl_dealer {
                font-weight: 900;
                font-size: 1.4rem;
            }

            #ffl_dealer_filter {
                display: block;
                font-size: 1.2rem;
                padding: 5px;
                border-radius: 5px;
                width: 100%;
                max-width: 400px;
                margin-bottom: 5px;
                margin-top: 10px;
            }

            .save-changes-wrapper {
                margin-top: 40px;
                text-align: center;
                margin-bottom: 25px;
            }

            #_forms_wrapper {
                flex: 0 0 auto;
            }

            #my_account_wrapper, #my_payment_methods_wrapper, #my_event_registrations_wrapper, #my_courses_wrapper {
                width: 100%;
                max-width: 600px;
                background: #fff;
                color: #000;
                padding: 15px;
            }

            #my_account_wrapper > h2 {
                text-align: left;
            }

            #_edit_form {
                padding: 0;
            }

            #_edit_form h3, #_edit_form h2 {
                margin-top: 30px;
                line-height: 1;
            }

            #ffl_dealer_wrapper {
                height: auto;
                padding: 10px;
                border: 1px solid #aaa;
                border-radius: 2px;
            }

            #ffl_dealer_wrapper > h3 {
                margin: 0 0 15px;
                padding: 0;
            }

            #ffl_dealer_wrapper > p {
                margin-bottom: 10px;
                line-height: 1;
            }

            #ffl_dealer_filter, #ffl_radius {
                border-radius: 2px;
                border: 1px solid #bdbdbd;
                font-size: 0.9rem;
                color: #000;
            }

            #ffl_dealer_filter::placeholder {
                color: #000;
            }

            #ffl_radius {
                padding: 2px 5px;
            }

            #ffl_dealers {
                list-style: none;
                margin-left: 0;
                max-height: 500px;
                overflow: auto;
            }

            #ffl_dealers > li {
                margin-bottom: 1px;
                background: #ddd;
                border: none;
            }

            #ffl_dealers > li:nth-of-type(even) {
                background: none;
                border: 1px solid #ddd;
            }

            .ffl-choice {
                cursor: pointer;
            }

            .ffl-choice p {
                margin: 0;
            }

            #ffl_dealers {
                margin: 0;
                list-style: none;
            }

            span.help-label {
                display: block;
            }

            @media (max-width: 1023px) {
                #_content_wrapper {
                    flex-direction: column;
                    align-items: center;
                }

                #button_wrapper {
                    flex-direction: row;
                    position: relative;
                    flex-wrap: wrap;
                    justify-content: center;
                    flex: 0 0 auto;
                }
            }

            @media (max-width: 600px) {
                .grid-table td {
                    padding: 3px;
                    min-width: 10px;
                }

                .grid-table th {
                    padding: 5px;
                }
            }

            .show-password {
                margin-left: 20px;
                cursor: pointer;
            }
        </style>
		<?php
	}

	function hiddenElements() {
		$capitalizedFields = array();
		$forceSameAddress = getPreference("FORCE_SAME_BILLING_SHIPPING") && empty(CustomField::getCustomFieldData($GLOBALS['gUserRow']['contact_id'], "ALLOW_DIFFERENT_SHIPPING_ADDRESS"));
		if (getPreference("USE_FIELD_CAPITALIZATION")) {
			$resultSet = executeQuery("select column_name from column_definitions where letter_case = 'C'");
			while ($row = getNextRow($resultSet)) {
				$capitalizedFields[] = $row['column_name'];
			}
		}
		?>
        <iframe id="_post_iframe" name="post_iframe"></iframe>

        <div id="_cancel_event_dialog" class="dialog-box">
            <p><?=$this->iCancelEventMessage?></p>
        </div>

        <div id="_cancel_reservation_dialog" class="dialog-box">
            <p><?=$this->iCancelReservationMessage?></p>
        </div>

        <div id="_make_account_inactive_dialog" class="dialog-box">
            <p><?=$this->iMakeAccountInactiveMessage?></p>
            <p id="make_account_inactive_description"></p>
        </div>

        <div id="_change_event_dialog" class="dialog-box">
            <form id="change_event_form">
                <input type="hidden" id="event_id" name="event_id" value="">
                <input type="hidden" id="event_registrant_id" name="event_registrant_id" value="">
                <div class="form-line" id="_location_id_row">
                    <label>Location</label>
                    <select id="location_id" name="location_id">
                        <option value="">[Select Location]</option>
                    </select>
                </div>

                <div class="form-line" id="_new_event_id_row">
                    <label>Event</label>
                    <select id="new_event_id" name="new_event_id" class='validate[required]'>
                        <option value="">[Select Event]</option>
                    </select>
                </div>
            </form>
        </div>

        <div id="_new_payment_method_dialog" class='dialog-box'>
            <div id="_new_payment_method">
                <form id="_new_payment_method_form">
                    <p class="error-message">Be sure to make payment methods that are no longer needed inactive.</p>
					<?= $forceSameAddress ? "<p>Billing information must match your contact information on file</p>" : "" ?>
                    <h2>Billing Information</h2>
                    <div class="form-line" id="_billing_first_name_row">
                        <label for="billing_first_name" class="required-label">First Name</label>
                        <input tabindex="10" type="text" <?= ($forceSameAddress ? 'readonly="true"' : "") ?> class="validate[required]<?= (in_array("first_name", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="25" maxlength="25" id="billing_first_name" name="billing_first_name" placeholder="First Name" value="<?= htmlText($GLOBALS['gUserRow']['first_name']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_billing_last_name_row">
                        <label for="billing_last_name" class="required-label">Last Name</label>
                        <input tabindex="10" type="text" <?= ($forceSameAddress ? 'readonly="true"' : "") ?> class="validate[required]<?= (in_array("last_name", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="35" id="billing_last_name" name="billing_last_name" placeholder="Last Name" value="<?= htmlText($GLOBALS['gUserRow']['last_name']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_billing_business_name_row">
                        <label for="billing_business_name">Business Name</label>
                        <input tabindex="10" type="text" <?= ($forceSameAddress ? 'readonly="true"' : "") ?> class="<?= (in_array("business_name", $capitalizedFields) ? "validate[] capitalize" : "") ?>" size="30" maxlength="35" id="billing_business_name" name="billing_business_name" placeholder="Business Name" value="<?= htmlText($GLOBALS['gUserRow']['business_name']) ?>">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line checkbox-input" id="_same_address_row">
                        <label class=""></label>
                        <input tabindex="10" type="checkbox" id="same_address" name="same_address" checked="checked" <?= ($forceSameAddress ? 'readonly="true"' : "") ?> value="1"><label class="checkbox-label" for="same_address">Billing address is same as primary address</label>
                        <div class='clear-div'></div>
                    </div>

                    <div id="_billing_address" class="hidden">
                        <div class="form-line" id="_billing_address_1_row">
                            <label for="billing_address_1" class="required-label">Address</label>
                            <input tabindex="10" type="text" autocomplete='chrome-off' autocomplete='off' data-prefix="billing_" class="autocomplete-address validate[required]<?= (in_array("address_1", $capitalizedFields) ? " capitalize" : "") ?>" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="30" maxlength="60" id="billing_address_1" name="billing_address_1" placeholder="Address" value="">
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
                            <input tabindex="10" type="text" class="validate[required] uppercase" size="10" maxlength="10" data-conditional-required="($('#account_id').length == 0 || $('#account_id').val() == '') && $('#billing_country_id').val() == 1000" id="billing_postal_code" name="billing_postal_code" placeholder="Postal Code" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_billing_country_id_row">
                            <label for="billing_country_id" class="">Country</label>
                            <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="billing_country_id" name="billing_country_id">
								<?php
								foreach (getCountryArray() as $countryId => $countryName) {
									?>
                                    <option value="<?= $countryId ?>"><?= htmlText($countryName) ?></option>
									<?php
								}
								?>
                            </select>
                            <div class='clear-div'></div>
                        </div>
                    </div>

                    <div class="form-line" id="_payment_method_id_row">
                        <label for="payment_method_id" class="">Payment Method</label>
                        <select tabindex="10" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" id="payment_method_id" name="payment_method_id">
                            <option value="">[Select]</option>
							<?php
							$resultSet = executeQuery("select *,(select payment_method_type_code from payment_method_types where " .
								"payment_method_type_id = payment_methods.payment_method_type_id) payment_method_type_code from payment_methods where " .
								"(payment_method_id not in (select payment_method_id from payment_method_user_types) " .
								(empty($GLOBALS['gUserRow']['user_type_id']) ? "" : " or payment_method_id in (select payment_method_id from payment_method_user_types where user_type_id = " . $GLOBALS['gUserRow']['user_type_id'] . ")") . ") and " .
								"inactive = 0 and internal_use_only = 0 and client_id = ? and payment_method_type_id in " .
								"(select payment_method_type_id from payment_method_types where inactive = 0 and internal_use_only = 0 and payment_method_type_code in ('CREDIT_CARD','BANK_ACCOUNT')) " .
								"order by sort_order,description", $GLOBALS['gClientId']);
							while ($row = getNextRow($resultSet)) {
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

                        <div class="form-line" id="_card_code_row">
                            <label for="card_code" class="">Security Code</label>
                            <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="5" maxlength="4" id="card_code" name="card_code" placeholder="CVV Code" value="">
                            <a href="https://www.cvvnumber.com/cvv.html" target="_blank"><img alt="cvv image" id="cvv_image" src="/images/cvv_code.gif"></a>
                            <div class='clear-div'></div>
                        </div>
                    </div> <!-- payment_method_credit_card -->

                    <div class="payment-method-fields" id="payment_method_bank_account">
                        <div class="form-line" id="_routing_number_row">
                            <label for="routing_number" class="">Bank Routing Number</label>
                            <input tabindex="10" type="text" class="validate[required,custom[routingNumber]]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="9" id="routing_number" name="routing_number" placeholder="Routing Number" value="">
                            <div class='clear-div'></div>
                        </div>

                        <div class="form-line" id="_bank_account_number_row">
                            <label for="bank_account_number" class="">Account Number</label>
                            <input tabindex="10" type="text" class="validate[required]" data-conditional-required="$('#account_id').length == 0 || $('#account_id').val() == ''" size="20" maxlength="20" id="bank_account_number" name="bank_account_number" placeholder="Bank Account Number" value="">
                            <div class='clear-div'></div>
                        </div>
						<?php if (!empty($this->getPageTextChunk("VERIFY_BANK_ACCOUNT_NUMBER"))) { ?>
                            <div class="form-line" id="_bank_account_number_again_row">
                                <label for="bank_account_number_again" class="">Re-enter Account Number</label>
                                <input tabindex="10" autocomplete="chrome-off" autocomplete="off" type="text" class="validate[equals[bank_account_number]]" size="20" maxlength="20" id="bank_account_number_again" name="bank_account_number_again" placeholder="Repeat Bank Account Number" value="">
                                <div class='clear-div'></div>
                            </div>
						<?php } ?>

                    </div> <!-- payment_method_bank_account -->

                    <div class="form-line" id="_account_label_row">
                        <label for="account_label" class="">Account Nickname</label>
                        <span class="help-label">for future reference</span>
                        <input tabindex="10" type="text" class="" size="20" maxlength="30" id="account_label" name="account_label" placeholder="Account Label" value="">
                        <div class='clear-div'></div>
                    </div>

                    <div class="form-line" id="_set_recurring_row">
                        <label for="set_recurring" class=""></label>
                        <input tabindex="10" type="checkbox" checked="checked" class="" id="set_recurring" name="set_recurring" value="1"><label class="checkbox-label" for="set_recurring">Use this new payment method on all active recurring payments</label>
                        <div class='clear-div'></div>
                    </div>

                </form>
            </div> <!-- new_payment_method -->
        </div> <!-- new_payment_method_section -->
		<?php
	}
}

$pageObject = new MyAccountPage();
$pageObject->displayPage();
