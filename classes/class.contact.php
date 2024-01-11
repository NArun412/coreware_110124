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

class Contact {
	static private $iContacts = array();
	static private $iUserContacts = array();
	static private $iCountryNames = array();
    static private $iContactLimit = 10000;

	public static function getCountryNames() {
		if (empty(self::$iCountryNames)) {
			$resultSet = executeQuery("select * from countries");
			while ($row = getNextRow($resultSet)) {
				self::$iCountryNames[$row['country_id']] = $row['country_name'];
			}
		}
	}

	public static function getContact($contactId, $forceReload=false) {
		if (empty($contactId) || !is_numeric($contactId)) {
			return array();
		}
		self::getCountryNames();
		if ($forceReload || !array_key_exists($contactId, self::$iContacts)) {
            $method = ($forceReload ? "executeQuery" : "executeReadQuery");
			$resultSet = $method("select *,users.date_created as user_date_created,users.notes as user_notes,contacts.date_created as contact_date_created,contacts.notes as contact_notes," .
				"(select group_concat(user_group_id) from user_group_members where user_id = users.user_id) as user_group_ids," .
				"(select group_concat(category_id) from contact_categories where contact_id = contacts.contact_id) as category_ids," .
				"(select group_concat(concat_ws('||',phone_number,description) separator '||||') from phone_numbers where contact_id = contacts.contact_id) as phone_numbers from " .
				"contacts left outer join users using (contact_id) where contacts.contact_id = ?", $contactId);
			if ($row = getNextRow($resultSet)) {
				$row['country_name'] = self::$iCountryNames[$row['country_id']];
				$row['user_group_ids'] = array_filter(explode(",", $row['user_group_ids']));
				$row['category_ids'] = array_filter(explode(",", $row['category_ids']));
				$phoneNumbers = array();
				if (!empty($row['phone_numbers'])) {
					foreach (explode("||||", $row['phone_numbers']) as $thisPhoneNumber) {
						$parts = explode("||", $thisPhoneNumber);
						$phoneNumber = $parts[0];
						$description = $parts[1];
						$phoneNumbers[] = array("phone_number" => $phoneNumber, "description" => $description);
					}
				}
				$row['phone_numbers'] = $phoneNumbers;
				self::$iContacts[$contactId] = $row;
				if (!empty($row['user_id'])) {
					self::$iUserContacts[$row['user_id']] = $contactId;
				}
			} else {
				self::$iContacts[$contactId] = array();
			}
            // Limit the number of contacts in memory
            if(count(self::$iContacts) > self::$iContactLimit) {
                array_shift(self::$iContacts);
            }
		}
		$contactRow = self::$iContacts[$contactId];
		$contactRow['notes'] = $contactRow['contact_notes'];
		$contactRow['date_created'] = $contactRow['contact_date_created'];
		return $contactRow;
	}

	public static function getUser($userId, $forceReload=false) {
		if (empty($userId) || !is_numeric($userId)) {
			return array();
		}
		self::getCountryNames();
		if ($forceReload || !array_key_exists($userId, self::$iUserContacts)) {
			$resultSet = executeReadQuery("select *,users.date_created as user_date_created,users.notes as user_notes,contacts.date_created as contact_date_created,contacts.notes as contact_notes," .
				"(select group_concat(user_group_id) from user_group_members where user_id = users.user_id) as user_group_ids," .
				"(select group_concat(category_id) from contact_categories where contact_id = contacts.contact_id) as category_ids," .
				"(select group_concat(concat_ws('||',phone_number,description) separator '||||') from phone_numbers where contact_id = contacts.contact_id) as phone_numbers from " .
				"contacts left outer join users using (contact_id) where users.user_id = ?", $userId);
			if ($row = getNextRow($resultSet)) {
				$row['country_name'] = self::$iCountryNames[$row['country_id']];
				$row['user_group_ids'] = array_filter(explode(",", $row['user_group_ids']));
				$row['category_ids'] = array_filter(explode(",", $row['category_ids']));
				$phoneNumbers = array();
				if (!empty($row['phone_numbers'])) {
					foreach (explode("||||", $row['phone_numbers']) as $thisPhoneNumber) {
						$parts = explode("||", $thisPhoneNumber);
						$phoneNumber = $parts[0];
						$description = $parts[1];
						$phoneNumbers[] = array("phone_number" => $phoneNumber, "description" => $description);
					}
				}
				$row['phone_numbers'] = $phoneNumbers;
				self::$iContacts[$row['contact_id']] = $row;
				self::$iUserContacts[$row['user_id']] = $row['contact_id'];
			} else {
				self::$iUserContacts[$userId] = false;
			}
		}
		$contactId = self::$iUserContacts[$userId];
		$userRow = (empty($contactId) ? array() : self::$iContacts[$contactId]);
		$userRow['notes'] = $userRow['user_notes'];
		$userRow['date_created'] = $userRow['user_date_created'];
		return $userRow;
	}

	public static function getContactFromUserId($userId) {
		if (!array_key_exists($userId, self::$iUserContacts)) {
			self::getUser($userId);
		}
		return self::getContact(self::$iUserContacts[$userId]);
	}

	public static function getUserFromContactId($contactId) {
		if (!array_key_exists($contactId, self::$iContacts)) {
			self::getContact($contactId);
		}
		return self::getUser(self::$iContacts[$contactId]['user_id']);
	}

	public static function getContactUserId($contactId) {
		if (!array_key_exists($contactId, self::$iContacts)) {
			self::getContact($contactId);
		}
		return self::$iContacts[$contactId]['user_id'];
	}

	public static function getUserContactId($userId) {
		if (!array_key_exists($userId, self::$iUserContacts)) {
			self::getUser($userId);
		}
		return self::$iUserContacts[$userId];
	}

	public static function getDisplayName($contactId, $parameters = array()) {
		if ((empty($contactId) || $contactId == $GLOBALS['gUserRow']['contact_id']) && !empty($GLOBALS['gUserRow']['display_name'])) {
			return $GLOBALS['gUserRow']['display_name'];
		}
		$displayName = "";
		if (empty($contactId) || !is_numeric($contactId)) {
			return $displayName;
		}
		if (array_key_exists("contact_row",$parameters)) {
			$row = $parameters['contact_row'];
		} else {
			if (!array_key_exists($contactId, self::$iContacts)) {
				self::getContact($contactId);
			}
			$row = self::$iContacts[$contactId];
		}
		if ($parameters['use_company'] && !empty($row['business_name'])) {
			$displayName = $row['business_name'];
		} else {
			if ($parameters['include_title']) {
				$displayName = $row['title'];
			}
			$useFirstName = (empty($row['preferred_first_name']) || $parameters['ignore_preferred_first_name'] ? $row['first_name'] : $row['preferred_first_name']);
			if (!empty($displayName) && !empty($useFirstName)) {
				$displayName .= " ";
			}
			$displayName .= $useFirstName;
			if (!empty($displayName) && !empty($row['middle_name'])) {
				$displayName .= " ";
			}
			$displayName .= $row['middle_name'];
			if (!empty($displayName) && !empty($row['last_name'])) {
				$displayName .= " ";
			}
			$displayName .= $row['last_name'];
			if (!empty($displayName) && !empty($row['suffix'])) {
				$displayName .= ", ";
			}
			$displayName .= $row['suffix'];
			if (!empty($row['business_name']) && !empty($displayName) && $parameters['include_company']) {
				if ($parameters['prepend_company']) {
					$displayName = $row['business_name'] . ", " . $displayName;
				} else {
					$displayName .= ", " . $row['business_name'];
				}
			}
			if (!$parameters['dont_use_company'] && empty($displayName) && !empty($row['business_name'])) {
				$displayName = $row['business_name'];
			}
			if (empty($displayName) && !empty($row['alternate_name'])) {
				$displayName = $row['alternate_name'];
			}
		}
		if (empty($displayName)) {
			$displayName = $row['email_address'];
		}
		return $displayName;
	}

	public static function getUserDisplayName($userId, $parameters = array()) {
		if (is_array($userId)) {
			$parameters = $userId;
			$userId = $GLOBALS['gUserId'];
		}
		if (empty($userId)) {
			$userId = $GLOBALS['gUserId'];
		}
		if ($userId == $GLOBALS['gUserId']) {
			return self::getDisplayName($GLOBALS['gUserRow']['contact_id'], $parameters);
		}
		if (empty($userId) || !is_numeric($userId)) {
			return "";
		}
		if (!array_key_exists("include_company", $parameters)) {
			$parameters['include_company'] = true;
		}
		if (!array_key_exists($userId, self::$iUserContacts)) {
			self::getUser($userId);
		}
		$contactId = self::$iUserContacts[$userId];
		if (empty($contactId)) {
			return "";
		}
		$displayName = getDisplayName($contactId, $parameters);
		if (empty($displayName)) {
			$displayName = self::$iContacts[$contactId]['user_name'];
		}
		return $displayName;
	}

	public static function getAddressBlock($contactRow, $lineEnding = "<br>", $parameters = array()) {
		self::getCountryNames();
		$address = $contactRow['address_1'];
		if (!empty($contactRow['address_2'])) {
			$address .= (empty($address) ? "" : $lineEnding) . $contactRow['address_2'];
		}
		$city = $contactRow['city'];
		if (!empty($contactRow['state'])) {
			$city .= (empty($city) ? "" : ", ") . $contactRow['state'];
		}
		if (!empty($contactRow['postal_code'])) {
			$city .= (empty($city) ? "" : " ") . $contactRow['postal_code'];
		}
		if (!empty($city)) {
			$address .= (empty($address) ? "" : $lineEnding) . $city;
		}
		if ($contactRow['country_id'] != 1000) {
			$address .= (empty($address) ? "" : $lineEnding) . self::$iCountryNames[$contactRow['country_id']];
		}
		if (!empty($parameters['include_email'])) {
			$address .= (empty($address) ? "" : $lineEnding) . $contactRow['email_address'];
		}
		if (!empty($parameters['include_phone'])) {
			$address .= (empty($address) ? "" : $lineEnding) . self::getContactPhoneNumber($contactRow['contact_id']);
		}
		return $address;
	}

	public static function getUserContactField($userId, $fieldName) {
		$contactRow = self::getContactFromUserId($userId);
		return $contactRow[$fieldName];
	}

	public static function getContactField($contactId, $fieldName) {
		$contactRow = self::getContact($contactId);
		return $contactRow[$fieldName];
	}

	public static function getMultipleContactFields($contactId, $fieldNames) {
		if (!is_array($fieldNames)) {
			$fieldNames = array($fieldNames);
		}
		$contactRow = self::getContact($contactId);
		$returnArray = array();
		foreach ($fieldNames as $fieldName) {
			$returnArray[$fieldName] = $contactRow[$fieldName];
		}
		return $returnArray;
	}

	public static function getContactPhoneNumber($contactId, $descriptions = array("Primary"), $useAny = true) {
		if (!is_array($descriptions)) {
			$descriptions = array($descriptions);
		}
		if ($contactId == $GLOBALS['gUserRow']['contact_id']) {
			$contactRow = $GLOBALS['gUserRow'];
		} else {
			$contactRow = self::getContact($contactId);
		}
		$phoneNumber = "";
		foreach ($descriptions as $thisDescription) {
			foreach ($contactRow['phone_numbers'] as $thisPhoneNumber) {
				if ($thisPhoneNumber['description'] == $thisDescription) {
					$phoneNumber = $thisPhoneNumber['phone_number'];
					break;
				}
			}
		}
		// If requested number not found, look for "Primary"
		if ($useAny && empty($phoneNumber) && !in_array("Primary", $descriptions)) {
			foreach ($contactRow['phone_numbers'] as $thisPhoneNumber) {
				if ($thisPhoneNumber['description'] == "Primary") {
					$phoneNumber = $thisPhoneNumber['phone_number'];
					break;
				}
			}
		}
		// If "Primary" not found, pull first number available other than "Fax" (coalesce to pull value if description is NULL)
		if ($useAny && empty($phoneNumber)) {
			foreach ($contactRow['phone_numbers'] as $thisPhoneNumber) {
				if ($thisPhoneNumber['description'] != "fax") {
					$phoneNumber = $thisPhoneNumber['phone_number'];
					break;
				}
			}
		}
		return $phoneNumber;
	}
}
