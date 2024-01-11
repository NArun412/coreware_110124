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

class MailChimpSync {

    private $iMailChimpAPIKey;
    private $iMailChimpListId;
    private $iErrorMessage;
    private $iPreferences;

    function __construct($mailChimpAPIKey, $mailChimpListId) {
        $this->iMailChimpAPIKey = $mailChimpAPIKey;
        $this->iMailChimpListId = $mailChimpListId;
        $this->iPreferences = array(
            'donation_days' => false,
            'product_days' => false,
            'include_all' => false,
            'sync_deletes' => false
        );
    }

    function syncContacts($valuesArray = array()) {
        $mailChimpAPIKey = $this->iMailChimpAPIKey;
        $mailChimpListId = $this->iMailChimpListId;
        if (empty($mailChimpAPIKey) || empty($mailChimpListId)) {
            $this->iErrorMessage = "API Key or List ID are not setup. Do this in Client Preferences.";
            return false;
        }
        $this->iPreferences['donation_days'] = array_key_exists('donation_days', $valuesArray) ? $valuesArray['donation_days'] : $this->iPreferences['donation_days'];
        $this->iPreferences['product_days'] = array_key_exists('product_days', $valuesArray) ? $valuesArray['product_days'] : $this->iPreferences['product_days'];
        $this->iPreferences['include_all'] = array_key_exists('include_all', $valuesArray) ? $valuesArray['include_all'] : $this->iPreferences['include_all'];
        $this->iPreferences['sync_deletes'] = array_key_exists('sync_deletes', $valuesArray) ? $valuesArray['sync_deletes'] : $this->iPreferences['sync_deletes'];

        $contactsArray = array();
        try {
            $mailChimp = new MailChimp($mailChimpAPIKey);
        } catch (Exception $e) {
            $this->iErrorMessage = "Error connecting to MailChimp: " . $e->getMessage();
            return false;
        }

        $validCategories = array("mailing_lists", "categories", "designations", "designation_groups", "forms", "user_groups", "products", "product_categories");
        $mailChimpGroups = array();
        $result = $mailChimp->get("/lists/" . $mailChimpListId . "/interest-categories");
        $groupDisplay = array();

        if (is_array($result) && is_array($result['categories'])) {
	        foreach ($result['categories'] as $categoryInfo) {
		        $title = str_replace(" ", "_", strtolower($categoryInfo['title']));
		        if (in_array($title, $validCategories)) {
			        $categoryResult = $mailChimp->get("/lists/" . $mailChimpListId . "/interest-categories/" . $categoryInfo['id'] . "/interests");
			        foreach ($categoryResult['interests'] as $thisInterest) {
				        $corewareId = "";
				        switch ($title) {
					        case "mailing_lists":
						        $corewareId = getFieldFromId("mailing_list_id", "mailing_lists", "description", $thisInterest['name']);
						        if (!empty($corewareId)) {
							        $groupDisplay[] = array($categoryInfo['title'] . "->" . $thisInterest['name'], "Mailing List '" . $thisInterest['name'] . "'");
						        }
						        break;
					        case "categories":
						        $corewareId = getFieldFromId("category_id", "categories", "description", $thisInterest['name']);
						        if (!empty($corewareId)) {
							        $groupDisplay[] = array($categoryInfo['title'] . "->" . $thisInterest['name'], "Contact Category '" . $thisInterest['name'] . "'");
						        }
						        break;
					        case "designations":
						        $corewareId = getFieldFromId("designation_id", "designations", "description", $thisInterest['name']);
						        if (!empty($corewareId)) {
							        $groupDisplay[] = array($categoryInfo['title'] . "->" . $thisInterest['name'], "Donors who gave toward '" . $thisInterest['name'] . "'");
						        }
						        break;
					        case "designation_groups":
						        $corewareId = getFieldFromId("designation_group_id", "designation_groups", "description", $thisInterest['name']);
						        if (!empty($corewareId)) {
							        $groupDisplay[] = array($categoryInfo['title'] . "->" . $thisInterest['name'], "Donors who gave toward designations in the designation group '" . $thisInterest['name'] . "'");
						        }
						        break;
					        case "forms":
						        $corewareId = getFieldFromId("form_definition_id", "form_definitions", "description", $thisInterest['name']);
						        if (!empty($corewareId)) {
							        $groupDisplay[] = array($categoryInfo['title'] . "->" . $thisInterest['name'], "Contacts who filled out the form '" . $thisInterest['name'] . "'");
						        }
						        break;
					        case "user_groups":
						        $corewareId = getFieldFromId("user_group_id", "user_groups", "description", $thisInterest['name']);
						        if (!empty($corewareId)) {
							        $groupDisplay[] = array($categoryInfo['title'] . "->" . $thisInterest['name'], "Users in the User Group '" . $thisInterest['name'] . "'");
						        }
						        break;
					        case "products":
						        $corewareId = getFieldFromId("product_id", "products", "description", $thisInterest['name']);
						        if (!empty($corewareId)) {
							        $groupDisplay[] = array($categoryInfo['title'] . "->" . $thisInterest['name'], "Contacts who purchased '" . $thisInterest['name'] . "'");
						        }
						        break;
					        case "product_categories":
						        $corewareId = getFieldFromId("product_category_id", "product_categories", "description", $thisInterest['name']);
						        if (!empty($corewareId)) {
							        $groupDisplay[] = array($categoryInfo['title'] . "->" . $thisInterest['name'], "Contacts who purchase products in product category '" . $thisInterest['name'] . "'");
						        }
						        break;
				        }
				        if (!empty($corewareId)) {
					        $mailChimpGroups[] = array("category" => $title, "id" => $thisInterest['id'], "coreware_id" => $corewareId);
				        }
			        }
		        }
	        }
        }

        $skipCount = 0;
        $deleteCount = 0;
        $errorCount = 0;
        $updateCount = 0;
        $addCount = 0;
        $resultSet = executeQuery("select contact_id,first_name,last_name,email_address,mailchimp_identifier,(select group_concat(mailing_list_id) from contact_mailing_lists where contact_id = contacts.contact_id and date_opted_out is null) mailing_list_ids," .
	        "(select group_concat(contact_category_id) from contact_categories where contact_id = contacts.contact_id) as category_ids " .
	        "from contacts where deleted = 0 and client_id = ? and (email_address is not null or mailchimp_identifier is not null) order by mailchimp_identifier desc", $GLOBALS['gClientId']);
        while ($row = getNextRow($resultSet)) {
            if (empty($row['mailchimp_identifier'])) {
                $mailChimpIdentifier = $mailChimp->subscriberHash($row['email_address']);
            } else {
                $mailChimpIdentifier = $row['mailchimp_identifier'];
            }
            if (array_key_exists($mailChimpIdentifier, $contactsArray)) {
                $skipCount++;
                continue;
            }
			$contactMailingListIds = explode(",",$row['mailing_list_ids']);
			$contactCategoryIds = explode(",",$row['category_ids']);
            $interestCount = 0;
            $existingInterests = array();
            foreach ($mailChimpGroups as $groupInfo) {
                switch ($groupInfo['category']) {
                    case "mailing_lists":
                        $corewareId = in_array($groupInfo['coreware_id'],$contactMailingListIds);
                        break;
                    case "categories":
                        $corewareId = in_array($groupInfo['coreware_id'],$contactCategoryIds);
                        break;
                    case "designations":
                        $corewareId = getFieldFromId("donation_id", "donations", "designation_id", $groupInfo['coreware_id'], "contact_id = ? and associated_donation_id is null" .
                            (empty($this->iPreferences['donation_days']) || !is_numeric($this->iPreferences['donation_days']) ? "" : " and donation_date > date_sub(now(),interval " .
                                $this->iPreferences['donation_days'] . " day)"), $row['contact_id']);
                        break;
                    case "designation_groups":
                        $corewareId = getFieldFromId("donation_id", "donations", "contact_id", $row['contact_id'],
                            "designation_id in (select designation_id from designation_group_links where designation_group_id = ?) and associated_donation_id is null" .
                            (empty($this->iPreferences['donation_days']) || !is_numeric($this->iPreferences['donation_days']) ? "" : " and donation_date > date_sub(now(),interval " .
                                $this->iPreferences['donation_days'] . " day)"), $groupInfo['coreware_id']);
                        break;
                    case "forms":
                        $corewareId = getFieldFromId("form_id", "forms", "form_definition_id", $groupInfo['coreware_id'], "contact_id = ?", $row['contact_id']);
                        break;
                    case "user_groups":
                        $corewareId = getFieldFromId("user_group_member_id", "user_group_members", "user_group_id", $groupInfo['coreware_id'], "user_id in (select user_id from users where contact_id = ?)", $row['contact_id']);
                        break;
                    case "products":
                        $corewareId = getFieldFromId("order_item_id", "order_items", "product_id", $groupInfo['coreware_id'], "order_id in (select order_id from orders where " .
                            (empty($this->iPreferences['product_days']) || !is_numeric($this->iPreferences['product_days']) ? "" : "order_time > date_sub(now(), interval " .
                                $this->iPreferences['product_days'] . " day) and ") . "contact_id = ?)", $row['contact_id']);
                        break;
                    case "product_categories":
                        $corewareId = getFieldFromId("order_id", "orders", "contact_id", $row['contact_id'],
                            (empty($this->iPreferences['product_days']) || !is_numeric($this->iPreferences['product_days']) ? "" : "order_time > date_sub(now(), interval " .
                                $this->iPreferences['product_days'] . " day) and ") . "order_id in (select order_id from order_items where product_id in (select product_id from product_category_links where product_category_id = ?))",
                            $groupInfo['coreware_id']);
                        break;
                }
                $existingInterests[$groupInfo['id']] = (empty($corewareId) ? false : true);
                if (!empty($corewareId)) {
                    $interestCount++;
                }
            }
            $row['existing_interests'] = $existingInterests;
            if ($interestCount > 0 || $this->iPreferences['include_all'] == "1") {
                $contactsArray[(empty($row['email_address']) ? "DELETE-" : "") . $mailChimpIdentifier] = $row;
            } else if (!empty($row['mailchimp_identifier'])) {
                executeQuery("update contacts set mailchimp_identifier = null where contact_id = ?", $row['contact_id']);
            }
        }
        $mailChimpList = $mailChimp->get("lists/" . $mailChimpListId);

        $mailChimpMembers = $mailChimp->get("lists/" . $mailChimpListId . "/members", array("count" => $mailChimpList['stats']['member_count']), 120);
        if (!$mailChimp->success()) {
            $this->iErrorMessage = $mailChimp->getLastError();
            return false;
        }
        $operationId = 0;
        $batch = $mailChimp->newBatch();
        $mailChimpIdentifiers = array();
        foreach ($mailChimpMembers['members'] as $thisMember) {
            $thisContact = $contactsArray[$thisMember['id']];
            if (empty($thisContact) || empty($thisContact['email_address'])) {
                $operationId++;
                if ($operationId % 1000 == 0) {
                    $batch->execute();
                    $batch = $mailChimp->newBatch();
                }
                if (!empty($this->iPreferences['sync_deletes'])) {
                    $batch->delete("op" . $operationId, "lists/" . $mailChimpListId . "/members/" . $thisMember['id']);
                    $deleteCount++;
                    if (!empty($thisContact['mailchimp_identifier'])) {
                        executeQuery("update contacts set mailchimp_identifier = null where contact_id = ?", $thisContact['contact_id']);
                    }
                }
                continue;
            }
            $mailChimpIdentifiers[$thisMember['id']] = true;

            $mailChimpInterests = array();
            $existingInterests = $thisContact['existing_interests'];
            foreach ($mailChimpGroups as $groupInfo) {
                $mailChimpInterests[$groupInfo['id']] = ($thisMember['interests'][$groupInfo['id']] ? true : false);
            }
            if (strtolower($thisMember['email_address']) != strtolower($thisContact['email_address'])) {
                $newHashKey = $mailChimp->subscriberHash($thisContact['email_address']);
                if (array_key_exists($newHashKey, $contactsArray)) {
                    $errorCount++;
                } else {
                    $operationId++;
                    if ($operationId % 1000 == 0) {
                        $batch->execute();
                        $batch = $mailChimp->newBatch();
                    }
                    $updateArray = array("email_address" => strtolower($thisContact['email_address']), "merge_fields" => array("FNAME" => $thisContact['first_name'], "LNAME" => $thisContact['last_name']), "interests" => $existingInterests);
                    $batch->patch("op" . $operationId, "lists/" . $mailChimpListId . "/members/" . $thisMember['id'], $updateArray);
                    executeQuery("update contacts set mailchimp_identifier = ? where contact_id = ?", $newHashKey, $thisContact['contact_id']);
                    $updateCount++;
                }
            } else if ($thisMember['merge_fields']['FNAME'] != $thisContact['first_name'] || $thisMember['merge_fields']['LNAME'] != $thisContact['last_name'] || jsonEncode($mailChimpInterests) != jsonEncode($existingInterests)) {
                $operationId++;
                if ($operationId % 1000 == 0) {
                    $batch->execute();
                    $batch = $mailChimp->newBatch();
                }
                $updateArray = array("email_address" => strtolower($thisContact['email_address']), "merge_fields" => array("FNAME" => $thisContact['first_name'], "LNAME" => $thisContact['last_name']), "interests" => $existingInterests);
                $batch->patch("op" . $operationId, "lists/" . $mailChimpListId . "/members/" . $thisMember['id'], $updateArray);
                $updateCount++;
            }
        }
        foreach ($contactsArray as $mailChimpIdentifier => $thisContact) {
            if (empty($thisContact['email_address'])) {
                continue;
            }
            if (empty($thisContact['mailchimp_identifier']) || !array_key_exists($thisContact['mailchimp_identifier'], $mailChimpIdentifiers)) {
                $newHashKey = $mailChimp->subscriberHash($thisContact['email_address']);
                $operationId++;
                if ($operationId % 1000 == 0) {
                    $batch->execute();
                    $batch = $mailChimp->newBatch();
                }
                $updateArray = array("email_address" => strtolower($thisContact['email_address']), "status" => "subscribed", "merge_fields" => array("FNAME" => $thisContact['first_name'], "LNAME" => $thisContact['last_name']), "interests" => $thisContact['existing_interests']);
                $batch->put("op" . $operationId, "lists/" . $mailChimpListId . "/members/" . $newHashKey, $updateArray);
                executeQuery("update contacts set mailchimp_identifier = ? where contact_id = ?", $newHashKey, $thisContact['contact_id']);
                $addCount++;
            }
        }
        $batch->execute();

        $returnArray['groups'] = $groupDisplay;
        $returnArray['skip_count'] = $skipCount;
        $returnArray['delete_count'] = $deleteCount;
        $returnArray['error_count'] = $errorCount;
        $returnArray['update_count'] = $updateCount;
        $returnArray['add_count'] = $addCount;
        return $returnArray;
    }

    /**
     * @return mixed
     */
    public function getErrorMessage()
    {
        return $this->iErrorMessage;
    }
}