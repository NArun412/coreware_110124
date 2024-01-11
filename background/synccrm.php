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
		$this->iProcessCode = "sync_crm";
	}

    private $iDebugLogging = false;
    private $iLastMemory = 0;
    private $iLastLogTime = 0;

    function addResult($resultLine = "", $debugOnly = false) {
        $timeNow = getMilliseconds();
        $resultLine .= " (" . getTimeElapsed($this->iLastLogTime,$timeNow) . ")";
        $this->iLastLogTime = $timeNow;
        if ($GLOBALS['gDevelopmentServer'] || $this->iDebugLogging) {
            $currentMemory = memory_get_usage() / 1000;
            $memoryChange = $currentMemory - $this->iLastMemory;
            $this->iLastMemory = $currentMemory;
            addDebugLog($resultLine . " Memory Used: " . number_format($currentMemory, 0, "", ",")
                . " KB Change: " . number_format($memoryChange, 0, "", ",") . " KB");
            if($debugOnly) {
                return;
            }
        }
        parent::addResult($resultLine);
    }

    function process() {
        $this->iLastLogTime = getMilliseconds();
        $this->iDebugLogging = !empty(getPreference("LOG_SYNC_CRM"));
		$clientSet = executeReadQuery("select * from contacts join clients using (contact_id) where inactive = 0");
		while ($clientRow = getNextRow($clientSet)) {
            changeClient($clientRow['client_id']);

            // Sync MailChimp contacts
            $mailChimpAPIKey = getPreference("MAILCHIMP_API_KEY");
            $mailChimpListId = getPreference("MAILCHIMP_LIST_ID");
            if (!empty($mailChimpAPIKey) && !empty($mailChimpListId)) {
                $mailChimpSync = new MailChimpSync($mailChimpAPIKey, $mailChimpListId);
                $result = $mailChimpSync->syncContacts();
                if (!$result) {
                    $this->addResult("MailChimp Sync failed for client " . $clientRow['client_code'] . ". Error message: " . $mailChimpSync->getErrorMessage());
                    continue;
                }
                $this->addResult("MailChimp Sync successful for client " . $clientRow['client_code']);
                $this->addResult($result['skip_count'] . " contacts skipped because of duplicate email addresses.");
                $this->addResult($result['delete_count'] . " MailChimp members deleted.");
                $this->addResult($result['error_count'] . " errors occurred because of duplicate email addresses.");
                $this->addResult($result['update_count'] . " MailChimp members updated.");
                $this->addResult($result['add_count'] . " MailChimp members added.");
            }
            // Sync Listrak contacts
            $listrakClientId = getPreference('LISTRAK_CLIENT_ID');
            $listrakClientSecret = getPreference('LISTRAK_CLIENT_SECRET');
            if(!empty($listrakClientId)) {
                $listrak = new Listrak($listrakClientId, $listrakClientSecret);
                $whichContactsString = getPreference("LISTRAK_SYNC_WHICH_CONTACTS");
                $whichContactsParts = explode(" ", $whichContactsString);
                $whichContacts = array_shift($whichContactsParts);
                switch ($whichContacts) {
                    case "ALL":
                        $whereStatement = "";
                        $whichText = "All contacts";
                        break;
                    case "ORDERS":
                        $whereStatement = " and contact_id in (select contact_id from orders)";
                        $whichText = "Contacts with orders";
                        break;
                    case "SUBSCRIBED":
                        $whereStatement = " and contact_id in (select contact_id from contact_mailing_lists)";
                        $whichText = "Contacts subscribed to a mailing list";
                        break;
                    case "ORDERS_SUBSCRIBED":
                        $whereStatement = " and contact_id in (select contact_id from orders union select contact_id from contact_mailing_lists)";
                        $whichText = "Contacts with orders or subscribed to a mailing list";
                        break;
                    default:
                        $whichContacts = ""; // make sure unexpected values are treated as none
                        $whichText = "No contacts";
                        break;
                }
                $count = 0;
                if (!empty($whichContacts)) {
                    $contactResult = executeQuery("select *, exists(select * from orders where orders.contact_id = contacts.contact_id) has_order, " .
                        "exists(select * from contact_mailing_lists where contact_mailing_lists.contact_id = contacts.contact_id) subscribed " .
                        "from contacts where client_id = ? and deleted = 0" . $whereStatement, $GLOBALS['gClientId']);
                    $last = $contactResult['row_count'];
                    while($contactRow = getNextRow($contactResult)) {
                        $listrak->updateContact($contactRow['contact_id'], $contactRow, $contactRow['has_order'] || $contactRow['subscribed'], ++$count == $last);
                        if($this->iDebugLogging && $count % 10000 == 0) {
                            $this->addResult("$count contacts synced with Listrak: $whichText", true);
                        }
                    }
                }
                $this->addResult($count . " contacts synced with Listrak: " . $whichText);
                if(!empty(getPreference("LISTRAK_SYNC_ORDER_HISTORY"))) {
                    $orderResult = executeQuery("select * from orders where client_id = ? and deleted = 0", $GLOBALS['gClientId']);
                    $lastOrder = $orderResult['row_count'];
                    $orderCount = 0;
                    while($orderRow = getNextRow($orderResult)) {
                        if(!$listrak->logOrder($orderRow['order_id'], $orderRow, ++$orderCount==$lastOrder)) {
                            $this->addResult("Error syncing orders with Listrak: ". $listrak->getErrorMessage());
                        }
                    }
                    $this->addResult($orderCount . " historical orders synced with Listrak.");
                    executeQuery("delete from client_preferences where client_id = ? and preference_id = (select preference_id from preferences where preference_code = 'LISTRAK_SYNC_ORDER_HISTORY')",
                        $GLOBALS['gClientId']);
                }
            }
            // Sync ActiveCampaign contacts
            $activeCampaignApiKey = getPreference("ACTIVECAMPAIGN_API_KEY");
            $activeCampaignTestMode = getPreference("ACTIVECAMPAIGN_TEST");
            if (!empty($activeCampaignApiKey)) {
                $activeCampaign = new ActiveCampaign($activeCampaignApiKey, $activeCampaignTestMode);
                $result = $activeCampaign->syncContacts();
                if (!$result) {
                    $this->addResult("ActiveCampaign Sync failed for client " . $clientRow['client_code'] . ". Error message: " . $activeCampaign->getErrorMessage());
                    continue;
                }
                $this->addResult("ActiveCampaign Sync successful for client " . $clientRow['client_code']);
                $this->addResult($result['skip_count'] . " contacts skipped because of duplicate email addresses.");
                $this->addResult($result['delete_count'] . " ActiveCampaign members deleted.");
                $this->addResult($result['error_count'] . " errors occurred because of duplicate email addresses.");
                $this->addResult($result['update_count'] . " ActiveCampaign members updated.");
                $this->addResult($result['add_count'] . " ActiveCampaign members added.");
            }
			// Sync HighLevel contacts
			$highLevelAccessToken = getPreference(makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_ACCESS_TOKEN");
			$highLevelLocationId = getPreference(makeCode(HighLevel::HIGHLEVEL_DISPLAY_NAME) . "_LOCATION_ID");
			if (!empty($highLevelAccessToken) && !empty($highLevelLocationId)) {
				$highLevel = new HighLevel($highLevelAccessToken, $highLevelLocationId);
				$result = $highLevel->syncContacts();
				if (!$result) {
					$this->addResult(HighLevel::HIGHLEVEL_DISPLAY_NAME . " Sync failed for client " . $clientRow['client_code'] . ". Error message: " . $highLevel->getErrorMessage());
					continue;
				}
				$this->addResult(HighLevel::HIGHLEVEL_DISPLAY_NAME . " Sync successful for client " . $clientRow['client_code']);
				$this->addResult($result['add_count'] . " " . HighLevel::HIGHLEVEL_DISPLAY_NAME . " new contacts sent.");
				$this->addResult($result['update_count'] . " " . HighLevel::HIGHLEVEL_DISPLAY_NAME . " contacts updated.");
				$this->addResult($result['error_count'] . " errors occurred.");
			}
            // Sync InfusionSoft contacts
            $infusionSoftToken = getPreference("INFUSIONSOFT_ACCESS_TOKEN");
            if (!empty($infusionSoftToken)) {
                $updateCount = 0;
                $errorCount = 0;
                $expirationDateCount = 0;
                $infusionSoft = new InfusionSoft($infusionSoftToken);
                $subscriptionSet = executeReadQuery("select * from subscriptions where client_id = ?", $GLOBALS['gClientId']);
                $subscriptionArray = array();
                while($subscriptionRow = getNextRow($subscriptionSet)) {
                    $subscriptionArray[$subscriptionRow['subscription_id']] = $subscriptionRow;
                }
                freeResult($subscriptionSet);
                $contactSet = executeReadQuery("select *, (select description from mailing_lists join contact_mailing_lists using (mailing_list_id) where contacts.contact_id = contact_mailing_lists.contact_id limit 1) as mailing_list,"
                    . " (select identifier_value from contact_identifiers join contact_identifier_types using (contact_identifier_type_id) where contacts.contact_id = contact_identifiers.contact_id and contact_identifier_types.contact_identifier_type_code = 'INFUSIONSOFT_CONTACT_ID') as infusionsoft_contact_id"
                    . " from contacts where client_id = ?", $GLOBALS['gClientId']);
                while($row = getNextRow($contactSet)) {
                    if(empty($row['mailing_list']) && empty($row['infusionsoft_contact_id'])) {
                        continue;
                    }
                    if(!empty($row['mailing_list'])) {
                        $optInReason = "Subscribed to " . $row['mailing_list'];
                    } else {
                        $optInReason = "";
                    }
                    $infusionSoftContactId = $infusionSoft->updateContact($row['contact_id'], $optInReason);
                    if(!empty($infusionSoftContactId)) {
                        $updateCount++;
                    } else {
                        $errorCount++;
                        continue;
                    }
                    $contactSubscriptionSet = executeReadQuery("select * from contact_subscriptions where contact_id = ?", $row['contact_id']);
                    while($contactSubscriptionRow = getNextRow($contactSubscriptionSet)) {
                        $subscriptionName = $subscriptionArray[$contactSubscriptionRow['subscription_id']]['description'] . " expiration";
                        $expirationDate = $contactSubscriptionRow['expiration_date'];
                        $infusionSoft->addCustomFieldToContact($infusionSoftContactId, $subscriptionName, date_format(date_create($expirationDate), "c"), "DateTime");
                        if(empty($infusionSoft->getErrorMessage())) {
                            $expirationDateCount++;
                        } else {
                            $errorCount++;
                        }
                    }
                }
                $this->addResult("InfusionSoft Sync complete for client " . $clientRow['client_code']);
                $this->addResult($updateCount . " contacts updated in InfusionSoft.");
                $this->addResult($expirationDateCount . " subscription expiration dates updated in InfusionSoft.");
                $this->addResult($errorCount . " InfusionSoft errors occurred.");

                // sync updated event registrations with infusionsoft
                $lastStartTime = $this->iBackgroundProcessRow['last_start_time'];
                $changeSet = executeReadQuery("select distinct primary_identifier from change_log where table_name = 'events' and time_changed >= ? and client_id = ?",
                    $lastStartTime, $GLOBALS['gClientId']);
                $eventIds = array();
                while($row = getNextRow($changeSet)) {
                    $eventIds[] = $row['primary_identifier'];
                }
                $addCount = 0;
                $deleteCount = 0;
	            foreach($eventIds as $eventId) {
                    $resultArray = $infusionSoft->updateEventRegistrants($eventId);
                    $addCount += $resultArray['add_count'];
                    $deleteCount += $resultArray['delete_count'];
                }
                $this->addResult("InfusionSoft event registrations updated for client " . $clientRow['client_code']);
                $this->addResult($addCount . " new registrations added to events in InfusionSoft.");
                $this->addResult($deleteCount . " cancelled or changed registrations removed from events in InfusionSoft.");
            }
        }
	}
}

$backgroundProcess = new ThisBackgroundProcess();
$backgroundProcess->startProcess();
