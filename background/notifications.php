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
		$this->iProcessCode = "notifications";
	}

	function process() {

		# force update of order status where resend days has value

		$resultSet = executeQuery("select * from order_status where resend_days > 0 order by client_id");
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			$orderSet = executeQuery("select order_id from orders where client_id = ? and order_status_id = ? and date_completed is null and deleted = 0 and order_id not in (select order_id from order_status_changes where order_status_id = ? and " .
				"time_changed > date_sub(current_date,interval " . $row['resend_days'] . " day))", $GLOBALS['gClientId'], $row['order_status_id'], $row['order_status_id']);
			while ($orderRow = getNextRow($orderSet)) {
				Order::updateOrderStatus($orderRow['order_id'], $row['order_status_id'], true);
			}
		}

# Send emails for events that are completed

		$emailCount = 0;
		$resultSet = executeQuery("select * from event_types where ended_email_id is not null or event_type_id in (select event_type_id from event_type_location_emails where ended_email_id is not null) order by client_id");
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);

			$customFieldTypeId = getFieldFromId("custom_field_type_id", "custom_field_types", "custom_field_type_code", "EVENTS");
			$customFieldId = getFieldFromId("custom_field_id", "custom_fields", "custom_field_code", "EVENT_ENDED_EMAIL_SENT", "custom_field_type_id = ?", $customFieldTypeId);
			if (empty($customFieldId)) {
				$insertSet = executeQuery("insert into custom_fields (client_id,custom_field_code,description,custom_field_type_id,form_label) values (?,?,?,?,?)",
					$GLOBALS['gClientId'], "EVENT_ENDED_EMAIL_SENT", "Event Ended Email Sent", $customFieldTypeId, "Event Ended Email Sent");
				$customFieldId = $insertSet['insert_id'];
				executeQuery("insert into custom_field_controls (custom_field_id,control_name,control_value) values (?,'data_type','date')", $customFieldId);
			}

			$eventEndedEmailDays = getPreference("EVENT_ENDED_EMAIL_DAYS");
			if (!is_numeric($eventEndedEmailDays) || $eventEndedEmailDays < 1) {
				$eventEndedEmailDays = 7;
			}
			$eventSet = executeQuery("select * from events where event_type_id = ? and event_id not in (select primary_identifier from custom_field_data where " .
				"custom_field_id in (select custom_field_id from custom_fields where custom_field_code = 'EVENT_ENDED_EMAIL_SENT' and custom_field_type_id in (select custom_field_type_id from custom_field_types where " .
				"custom_field_type_code = 'EVENTS')) and date_data is not null) and coalesce(end_date,start_date) < DATE_SUB(current_date ,INTERVAL " . $eventEndedEmailDays . " day)", $row['event_type_id']);
			while ($eventRow = getNextRow($eventSet)) {
				$contactArray = array();
				$registrantSet = executeQuery("select * from event_registrants join contacts using (contact_id) where email_address is not null and event_id = ?", $eventRow['event_id']);
				while ($registrantRow = getNextRow($registrantSet)) {
					$contactArray[] = $registrantRow;
				}
				foreach ($contactArray as $thisContact) {
					$substitutions = Events::getEventRegistrationSubstitutions($eventRow, $thisContact['contact_id']);
					$substitutions = array_merge($substitutions, $thisContact);

					$emailId = $row['ended_email_id'];
					$locationEmailId = getFieldFromId("ended_email_id", "event_type_location_emails", "event_type_id", $eventRow['event_type_id'], "location_id = ?", $eventRow['location_id']);
					if (!empty($locationEmailId)) {
						$emailId = $locationEmailId;
					}

					$result = sendEmail(array("email_id" => $emailId, "email_address" => $thisContact['email_address'], "substitutions" => $substitutions, "contact_id" => $thisContact['contact_id']));
					$emailCount++;
				}
				CustomField::setCustomFieldData($eventRow['event_id'], "EVENT_ENDED_EMAIL_SENT", date("m/d/Y"), "EVENTS");
			}
		}
		$this->addResult($emailCount . " after event emails sent");

# Send reminder emails before event happens

		$emailCount = 0;
		$resultSet = executeQuery("select * from event_types where reminder_email_id is not null order by client_id");
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);

			$eventReminderEmailDays = getPreference("EVENT_REMINDER_DAYS");
			if (empty($eventReminderEmailDays) || !is_numeric($eventReminderEmailDays) || $eventReminderEmailDays <= 0) {
				$eventReminderEmailDays = 3;
			}
			$eventSet = executeQuery("select * from events where event_type_id = ? and current_date between DATE_SUB(start_date ,INTERVAL " . $eventReminderEmailDays . " day) and start_date", $row['event_type_id']);
			while ($eventRow = getNextRow($eventSet)) {
				$contactArray = array();
				$registrantSet = executeQuery("select * from event_registrants join contacts using (contact_id) where email_address is not null and event_id = ?", $eventRow['event_id']);
				while ($registrantRow = getNextRow($registrantSet)) {
					$contactArray[] = $registrantRow;
				}
				foreach ($contactArray as $thisContact) {
					$emailLogId = getFieldFromId("email_log_id", "email_log", "email_id", $row['reminder_email_id'], "contact_id = ?", $thisContact['contact_id']);
					if (!empty($emailLogId)) {
						continue;
					}
					$substitutions = Events::getEventRegistrationSubstitutions($eventRow, $thisContact['contact_id']);
					$substitutions = array_merge($substitutions, $thisContact);

					$emailId = $row['reminder_email_id'];
					$result = sendEmail(array("email_id" => $emailId, "email_address" => $thisContact['email_address'], "substitutions" => $substitutions, "contact_id" => $thisContact['contact_id'], "send_immediately" => true));
					$emailCount++;
				}
			}
		}
		$this->addResult($emailCount . " reminder event emails sent");

#Send email notifications about page updates that are required.

		$emailCount = 0;
		$resultSet = executeQuery("select * from page_notifications where notification_date <= now()");
		while ($row = getNextRow($resultSet)) {
			changeClient(getFieldFromId("client_id", "pages", "page_id", $row['page_id']));
			$body = "Page '" . getFieldFromId("description", "pages", "page_id", $row['page_id'], "client_id is not null") . "' requires attention: <br/><br/>\n\n" . $row['description'] . "<br/>\n";
			sendEmail(array("body" => $body, "subject" => "Page Notification", "email_addresses" => $row['email_address'], "send_immediately" => true));
			if ($row['months_between'] > 0) {
				executeQuery("update page_notifications set notification_date = date_add(notification_date, interval " . $row['months_between'] . " month) where page_notification_id = ?", $row['page_notification_id']);
			} else {
				executeQuery("delete from page_notifications where page_notification_id = ?", $row['page_notification_id']);
			}
			$emailCount++;
		}
		$this->addResult($emailCount . " page notification emails sent");

		$emailCount = 0;
		$saveClientId = "";
		$saveUserId = "";
		$emailAddresses = array();
		$userNotificationDays = array();
		$resultSet = executeQuery("select * from contacts where responsible_user_id is not null order by client_id,responsible_user_id");
		while ($row = getNextRow($resultSet)) {
			if ($saveClientId != $row['client_id'] || $saveUserId != $row['responsible_user_id']) {
				clearGlobals();
				$GLOBALS['gClientId'] = $saveClientId = $row['client_id'];
				$GLOBALS['gUserId'] = $saveUserId = $row['responsible_user_id'];
			}
			if (array_key_exists($row['responsible_user_id'], $emailAddresses)) {
				$emailAddress = $emailAddresses[$row['responsible_user_id']];
			} else {
				$emailAddress = Contact::getUserContactField($row['responsible_user_id'], "email_address");
				$emailAddresses[$row['responsible_user_id']] = $emailAddress;
			}
			if (empty($emailAddress)) {
				continue;
			}
			if (array_key_exists($row['responsible_user_id'], $userNotificationDays)) {
				$notificationDays = $userNotificationDays[$row['responsible_user_id']];
			} else {
				$notificationDays = getPreference("CONTACT_TOUCHPOINT_NOTIFICATION");
				$userNotificationDays[$row['responsible_user_id']] = $notificationDays;
			}
			if (empty($notificationDays) || !is_numeric($notificationDays)) {
				continue;
			}
			$cutoffDate = date("Y-m-d", strtotime("- " . $notificationDays . " days"));
			$taskId = getFieldFromId("task_id", "tasks", "contact_id", $row['contact_id'], "date_completed >= ?", $cutoffDate);
			if (!empty($taskId)) {
				continue;
			}
			$body = "Contact '" . getDisplayName($row['contact_id']) . "', contact ID " . $row['contact_id'] . " has not had a touchpoint added in over " . $notificationDays . " days";
			sendEmail(array("body" => $body, "subject" => "Contact requires touchpoint", "email_addresses" => $emailAddress, "send_immediately" => true));
			$emailCount++;
		}
		$this->addResult($emailCount . " Contact touchpoint emails sent");

		$resultSet = executeQuery("select * from recurring_donations join contacts using (contact_id) join accounts using (account_id) where requires_attention = 0 and (end_date > current_date or end_date is null) and " .
			"contacts.client_id in (select client_id from clients where inactive = 0) and (start_date is null or start_date <= current_date) and recurring_donations.account_id is not null and email_address is not null and " .
			"expiration_date is not null and expiration_date < date_add(current_date,interval 1 month)");
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			$emailId = getFieldFromId("email_id", "emails", "email_code", "recurring_donation_account_expiring",  "inactive = 0");
			if (empty($emailId)) {
				continue;
			}

			$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", "EMAIL_SENT");
			if (empty($taskTypeId)) {
				$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", "TOUCHPOINT");
			}
			if (empty($taskTypeId)) {
				$taskAttributeId = getFieldFromId("task_attribute_id", "task_attributes", "task_attribute_code", "CONTACT_TASK");
				if (empty($taskAttributeId)) {
					continue;
				}
				$resultSet = executeQuery("insert into task_types (client_id,task_type_code,description) values (?,'EMAIL_SENT','Email Sent')", $GLOBALS['gClientId']);
				$taskTypeId = $resultSet['insert_id'];
				executeQuery("insert into task_type_attributes (task_type_id,task_attribute_id) values (?,?)", $taskTypeId, $taskAttributeId);
			}
			if (empty($taskTypeId)) {
				continue;
			}
			$taskId = getFieldFromId("task_id", "tasks", "task_type_id", $taskTypeId, "contact_id = ? and date_completed > date_sub(current_date,interval 2 month)", $row['contact_id']);
			if (!empty($taskId)) {
				continue;
			}
			$substitutions = $row;
			$substitutions['designation_description'] = getFieldFromId("description", "designations", "designation_id", $row['designation_id']);
			$substitutions['designation_code'] = getFieldFromId("designation_code", "designations", "designation_id", $row['designation_id']);
			$substitutions['full_name'] = getDisplayName($row['contact_id']);

			$result = sendEmail(array("email_id" => $emailId, "email_address" => $row['email_address'], "substitutions" => $substitutions, "contact_id" => $row['contact_id']));
			if ($result) {
				executeQuery("insert into tasks (client_id,contact_id,description,detailed_description,date_completed,task_type_id,simple_contact_task) values " .
					"(?,?,?,?,now(),?,1)", $row['client_id'], $row['contact_id'], 'Notification about expiring account', 'Notified the donor that the payment method used for their recurring donation is expiring', $taskTypeId);
			}
		}

		# Recurring payment account expiration date is approaching

		$resultSet = executeQuery("select * from recurring_payments join contacts using (contact_id) join accounts using (account_id) where requires_attention = 0 and (end_date > current_date or end_date is null) and " .
			"contacts.client_id in (select client_id from clients where inactive = 0) and (start_date is null or start_date <= current_date) and email_address is not null and " .
			"expiration_date is not null and expiration_date < date_add(current_date,interval 1 month)");
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			$emailId = getFieldFromId("email_id", "emails", "email_code", "recurring_payment_account_expiring",  "inactive = 0");
			if (empty($emailId)) {
				continue;
			}

			$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", "EMAIL_SENT");
			if (empty($taskTypeId)) {
				$taskTypeId = getFieldFromId("task_type_id", "task_types", "task_type_code", "TOUCHPOINT");
			}
			if (empty($taskTypeId)) {
				$taskAttributeId = getFieldFromId("task_attribute_id", "task_attributes", "task_attribute_code", "CONTACT_TASK");
				if (empty($taskAttributeId)) {
					continue;
				}
				$resultSet = executeQuery("insert into task_types (client_id,task_type_code,description) values (?,'EMAIL_SENT','Email Sent')", $GLOBALS['gClientId']);
				$taskTypeId = $resultSet['insert_id'];
				executeQuery("insert into task_type_attributes (task_type_id,task_attribute_id) values (?,?)", $taskTypeId, $taskAttributeId);
			}
			if (empty($taskTypeId)) {
				continue;
			}
			$taskId = getFieldFromId("task_id", "tasks", "task_type_id", $taskTypeId, "contact_id = ? and date_completed > date_sub(current_date,interval 2 month)", $row['contact_id']);
			if (!empty($taskId)) {
				continue;
			}
			$substitutions = $row;
			$substitutions['full_name'] = getDisplayName($row['contact_id']);

			$result = sendEmail(array("email_id" => $emailId, "email_address" => $row['email_address'], "substitutions" => $substitutions, "contact_id" => $row['contact_id']));
			if ($result) {
				executeQuery("insert into tasks (client_id,contact_id,description,detailed_description,date_completed,task_type_id,simple_contact_task) values " .
					"(?,?,?,?,now(),?,1)", $GLOBALS['gClientId'], $row['contact_id'], 'Notification about expiring account', 'Notified the customer that the payment method used for their recurring payment is expiring', $taskTypeId);
			}
		}

		# Recurring payment is approaching for subscription

		$resultSet = executeQuery("select *,(select sum(quantity * sale_price) from recurring_payment_order_items where recurring_payment_id = recurring_payments.recurring_payment_id) as amount, " .
			"(select account_number from accounts where account_id = recurring_payments.account_id) as account_number from recurring_payments " .
			"join contact_subscriptions using (contact_subscription_id) join subscriptions using (subscription_id) join contacts on (contacts.contact_id = recurring_payments.contact_id) where " .
			"notify_days is not null and notify_days > 0 and email_address is not null and (end_date is null or end_date > next_billing_date) and next_billing_date = date_add(current_date,interval notify_days day) order by contacts.client_id");
		$emailCount = 0;
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			$emailId = getFieldFromId("email_id", "emails", "email_code", "recurring_payment_approaching",  "inactive = 0");
			if (empty($emailId)) {
				continue;
			}
			$emailLogId = getFieldFromId("email_log_id", "email_log", "email_id", $emailId, "contact_id = ? and time_submitted > date_sub(current_date,interval 5 day)", $row['contact_id']);
			if (!empty($emailLogId)) {
				continue;
			}

			$substitutions = $row;
			$substitutions['full_name'] = getDisplayName($row['contact_id']);
			$substitutions['next_billing_date'] = date("m/d/Y", strtotime($row['next_billing_date']));
			$substitutions['amount'] = number_format($row['amount'], 2, ".", ",");

			sendEmail(array("email_id" => $emailId, "email_address" => $row['email_address'], "substitutions" => $substitutions, "contact_id" => $row['contact_id']));
			$emailCount++;
		}
		$this->addResult($emailCount . " emails sent about approaching recurring payment");

		# Subscription expiring without recurring payment

		$resultSet = executeQuery("select * from contact_subscriptions join subscriptions using (subscription_id) join contacts on (contacts.contact_id = contact_subscriptions.contact_id) where " .
			"contact_subscription_id not in (select contact_subscription_id from recurring_payments) and notify_days is not null and notify_days > 0 and email_address is not null and " .
			"expiration_date is not null and expiration_date = date_add(current_date,interval notify_days day) order by contacts.client_id");
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			$emailId = getFieldFromId("email_id", "emails", "email_code", "subscription_expiring",  "inactive = 0");
			if (empty($emailId)) {
				continue;
			}
			$emailLogId = getFieldFromId("email_log_id", "email_log", "email_id", $emailId, "contact_id = ? and time_submitted > date_sub(current_date,interval 5 day)", $row['contact_id']);
			if (!empty($emailLogId)) {
				continue;
			}

			$substitutions = $row;
			$substitutions['full_name'] = getDisplayName($row['contact_id']);
			$substitutions['expiration_date'] = date("m/d/Y", strtotime($row['expiration_date']));

			sendEmail(array("email_id" => $emailId, "email_address" => $row['email_address'], "substitutions" => $substitutions, "contact_id" => $row['contact_id']));
			$emailCount++;
		}
		$this->addResult($emailCount . " emails sent about expiring subscription without recurring payment");

		# Send Email reminder for invoices

		$emailCount = 0;
		$resultSet = executeQuery("select *,(select sum(amount * unit_price) from invoice_details where invoice_id = invoices.invoice_id) total_amount," .
			"(select sum(amount) from invoice_payments where invoice_id = invoices.invoice_id) total_payments, " .
			"(select max(payment_date) from invoice_payments where invoice_id = invoices.invoice_id) last_payment_date " .
			" from invoices join invoice_types using (invoice_type_id) where invoices.internal_use_only = 0 and invoices.inactive = 0 and " .
			"date_completed is null and email_id is not null and days_after is not null and days_after > 0 order by invoices.client_id");
		while ($row = getNextRow($resultSet)) {
			if (empty($row['last_payment_date'])) { // handle null last_payment_date
				$row['last_payment_date'] = $row['invoice_date'];
			}
			if (strtotime($row['last_payment_date']) > (time() - $row['days_after'] * 86400)) { // not time for next reminder yet
				continue;
			}
			$invoiceReminderLogId = getFieldFromId("invoice_reminder_log_id", "invoice_reminder_log", "invoice_id", $row['invoice_id'],
				"log_time > ?", $row['last_payment_date']);
			if (!empty($invoiceReminderLogId)) { // reminder already sent
				continue;
			}
			changeClient($row['client_id']);

			$substitutions = array_merge($row, Contact::getContact($row['contact_id']));
			if (empty($substitutions['email_address'])) {
				continue;
			}
			$substitutions['full_name'] = getDisplayName($row['contact_id']);
			$substitutions['invoice_date'] = date("m/d/Y", strtotime($row['invoice_date']));
			$substitutions['date_due'] = date("m/d/Y", strtotime($row['date_due']));
			$substitutions['total_amount'] = number_format($substitutions['total_amount'], 2);
			$substitutions['total_payments'] = number_format($substitutions['total_payments'], 2);
			$substitutions['balance_due'] = number_format($substitutions['total_amount'] - $substitutions['total_payments'], 2);
			if ($substitutions['balance_due'] <= 0) {
				continue;
			}
			$substitutions['invoice_link'] = '';
			$linkName = getFieldFromId("link_name", "pages", "script_filename", "invoicepayments.php", "inactive = 0");
			$hashCode = getFieldFromId("hash_code", "contacts", "contact_id", $row['contact_id']);
			if (empty($hashCode)) {
				$hashCode = getRandomString();
				executeQuery("update contacts set hash_code = ? where contact_id = ?", $hashCode, $row['contact_id']);
			}
			if (!empty($hashCode) && !empty($linkName)) {
				$domainName = getDomainName();
				$substitutions['invoice_link'] = $domainName . "/" . $linkName . "?code=" . $hashCode;
			}
			sendEmail(array("email_id" => $row['email_id'], "email_address" => $substitutions['email_address'], "substitutions" => $substitutions, "contact_id" => $row['contact_id']));
			$emailCount++;

			executeQuery("insert into invoice_reminder_log (invoice_id, log_time) values (?,current_date)", $row['invoice_id']);
		}
		$this->addResult($emailCount . " invoice payment reminder emails sent");

		# Send Email for expired form

		$resultSet = executeQuery("select * from form_definitions where expiration_days is not null and expiration_days > 0 and expiration_email_id is not null order by client_id");
		while ($row = getNextRow($resultSet)) {
			changeClient($row['client_id']);
			$formSet = executeQuery("select * from forms where date_add(date_created,interval " . $row['expiration_days'] . " day) between " .
				"current_date and date_add(current_date,interval 7 day) and form_definition_id = ?", $row['form_definition_id']);
			while ($formRow = getNextRow($formSet)) {
				$emailLogId = getFieldFromId("email_log_id", "email_log", "email_id", $row['expiration_email_id'],
					"contact_id = ? and time_submitted > date_sub(current_date,interval 10 day)", $formRow['contact_id']);
				if (!empty($emailLogId)) {
					continue;
				}
				$substitutions = array_merge($row, Contact::getContact($formRow['contact_id']));
				if (empty($substitutions['email_address'])) {
					continue;
				}
				$substitutions['full_name'] = getDisplayName($row['contact_id']);
				sendEmail(array("email_id" => $row['expiration_email_id'], "email_address" => $substitutions['email_address'],
					"substitutions" => $substitutions, "contact_id" => $formRow['contact_id']));
			}
		}

        # Send server-specific emails if any
        if (function_exists("_localServerSendNotifications")) {
            $results = _localServerSendNotifications();
            foreach($results as $thisResult) {
                $this->addResult($thisResult);
            }
        }

		$resultSet = executeQuery("select client_id,primary_identifier,count(*) from change_log where table_name = 'products' and column_name = 'base_cost' and time_changed > date_sub(current_time,interval 2 day) group by client_id,primary_identifier having count(*) > 10");
		$errorLog = "";
		if ($row = getNextRow($resultSet)) {
			$errorLog .= "Excessive updating of product cost for product ID " . $row['primary_identifier'] . " on client " . getFieldFromId("client_code", "clients", "client_id", $row['client_id']) . "\n";
		}
		if (!empty($errorLog)) {
			$GLOBALS['gPrimaryDatabase']->logError($errorLog);
		}
    }
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
