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

class FFL {

	private $iFederalFirearmsLicenseeRow = array();
	private $iCentralizedStorage = false;

	public function __construct($parameters) {
		if (!is_array($parameters)) {
			$parameters = array("federal_firearms_licensee_id" => $parameters);
		}
		if (getPreference("CENTRALIZED_FFL_STORAGE")) {
			$this->iCentralizedStorage = true;
		}
		$fflSet = false;
		if (!empty($parameters['federal_firearms_licensee_id'])) {
			$fflSet = executeQuery("select *,(select filename from files where file_id = federal_firearms_licensees.file_id) filename,(select filename from files where " .
				"file_id = federal_firearms_licensees.sot_file_id) sot_filename from federal_firearms_licensees join contacts using (contact_id) where federal_firearms_licensee_id = ? and federal_firearms_licensees.client_id = ?" .
				(empty($parameters['only_if_valid']) ? "" : " and contacts.deleted = 0 and inactive = 0 and expiration_date is not null and expiration_date > date_sub(current_date, interval 45 day)"),
				$parameters['federal_firearms_licensee_id'], ($this->iCentralizedStorage ? $GLOBALS['gDefaultClientId'] : $GLOBALS['gClientId']));
		} else if (!empty($parameters['license_number'])) {
			$licenseNumber = $parameters['license_number'];
			if (strlen($licenseNumber) == 15) {
				$licenseNumber = substr($licenseNumber, 0, 1) . "-" . substr($licenseNumber, 1, 2) . "-" . substr($licenseNumber, 3, 3) . "-" . substr($licenseNumber, 6, 2) . "-" . substr($licenseNumber, 8, 2) . "-" . substr($licenseNumber, 10, 5);
			}
			$fflSet = executeQuery("select *,(select filename from files where file_id = federal_firearms_licensees.file_id) filename,(select filename from files where " .
				"file_id = federal_firearms_licensees.sot_file_id) sot_filename from federal_firearms_licensees join contacts using (contact_id) where license_number = ? and federal_firearms_licensees.client_id = ?" .
				(empty($parameters['only_if_valid']) ? "" : " and contacts.deleted = 0 and inactive = 0 and expiration_date is not null and expiration_date > date_sub(current_date, interval 45 day)"),
				strtoupper($licenseNumber), ($this->iCentralizedStorage ? $GLOBALS['gDefaultClientId'] : $GLOBALS['gClientId']));
		} else if (!empty($parameters['license_lookup'])) {
			$licenseLookup = $parameters['license_lookup'];
			if (strlen($licenseLookup) == 8) {
				$licenseLookup = substr($licenseLookup, 0, 1) . "-" . substr($licenseLookup, 1, 2) . "-" . substr($licenseLookup, 3, 5);
			}
			$fflSet = executeQuery("select *,(select filename from files where file_id = federal_firearms_licensees.file_id) filename,(select filename from files where " .
				"file_id = federal_firearms_licensees.sot_file_id) sot_filename from federal_firearms_licensees join contacts using (contact_id) where license_lookup = ? and federal_firearms_licensees.client_id = ?" .
				(empty($parameters['only_if_valid']) ? "" : " and contacts.deleted = 0 and inactive = 0 and expiration_date is not null and expiration_date > date_sub(current_date, interval 45 day)"),
				$licenseLookup, ($this->iCentralizedStorage ? $GLOBALS['gDefaultClientId'] : $GLOBALS['gClientId']));
		}
		if ($fflSet) {
			if ($this->iFederalFirearmsLicenseeRow = getNextRow($fflSet)) {
				if (!$parameters['only_preferred_address'] || $this->iFederalFirearmsLicenseeRow['mailing_address_preferred']) {
					$mailingAddressRow = getCachedData("ffl_mailing_address", $this->iFederalFirearmsLicenseeRow['federal_firearms_licensee_id']);
					if (!is_array($mailingAddressRow)) {
						$mailingAddressRow = getRowFromId("addresses", "contact_id", $this->iFederalFirearmsLicenseeRow['contact_id'], "address_label = 'Mailing'");
						setCachedData("ffl_mailing_address", $this->iFederalFirearmsLicenseeRow['federal_firearms_licensee_id'], $mailingAddressRow, 168);
					}
					if ($parameters['only_preferred_address']) {
						if (!empty($mailingAddressRow['address_1']) && !empty($mailingAddressRow['city'])) {
							$addressFields = array("address_1", "address_2", "city", "state", "postal_code", "country_id");
							foreach ($addressFields as $fieldName) {
								$this->iFederalFirearmsLicenseeRow[$fieldName] = $mailingAddressRow[$fieldName];
							}
						}
					} else {
						$this->iFederalFirearmsLicenseeRow['mailing_address_1'] = $mailingAddressRow['address_1'];
						$this->iFederalFirearmsLicenseeRow['mailing_address_2'] = $mailingAddressRow['address_2'];
						$this->iFederalFirearmsLicenseeRow['mailing_city'] = $mailingAddressRow['city'];
						$this->iFederalFirearmsLicenseeRow['mailing_state'] = $mailingAddressRow['state'];
						$this->iFederalFirearmsLicenseeRow['mailing_postal_code'] = $mailingAddressRow['postal_code'];
						$this->iFederalFirearmsLicenseeRow['mailing_country_id'] = $mailingAddressRow['country_id'];
					}
				}
				$this->iFederalFirearmsLicenseeRow['phone_number'] = Contact::getContactPhoneNumber($this->iFederalFirearmsLicenseeRow['contact_id'], 'Store', true);
				if ($this->iCentralizedStorage) {
					$detailsRow = getRowFromId("federal_firearms_licensee_details", "federal_firearms_licensee_id", $this->iFederalFirearmsLicenseeRow['federal_firearms_licensee_id']);
					foreach ($detailsRow as $fieldName => $fieldData) {
						if (!empty($fieldData)) {
							$this->iFederalFirearmsLicenseeRow[$fieldName] = $fieldData;
						}
					}
					if (!empty($detailsRow['inactive'])) {
						$this->iFederalFirearmsLicenseeRow = array();
					}
				}
			}
		}
	}

	function getFFLRow() {
		return $this->iFederalFirearmsLicenseeRow;
	}

	function getFieldData($fieldName) {
		return $this->iFederalFirearmsLicenseeRow[$fieldName];
	}

	public static function fflFileIdExists($fileId) {
		$fflFileId = getFieldFromId("file_id", "federal_firearms_licensees", "file_id", $fileId, "client_id = ?", (getPreference("CENTRALIZED_FFL_STORAGE") ? $GLOBALS['gDefaultClientId'] : $GLOBALS['gClientId']));
		if (!$fflFileId) {
			$fflFileId = getFieldFromId("file_id", "federal_firearms_licensees", "sot_file_id", $fileId, "client_id = ?", (getPreference("CENTRALIZED_FFL_STORAGE") ? $GLOBALS['gDefaultClientId'] : $GLOBALS['gClientId']));
		}
		return (!empty($fflFileId));
	}

	public static function getFFLRecords($searchParameters = array(), $whereExpressions = array(), $consolidated = true) {
		$fflArray = array();
		$fieldNames = array();
		$whereStatement = "";
		$centralizedStorage = getPreference("CENTRALIZED_FFL_STORAGE");
		$parameters = array($centralizedStorage ? $GLOBALS['gDefaultClientId'] : $GLOBALS['gClientId']);
		$federalFirearmsLicenseesTable = new DataTable("federal_firearms_licensees");
		$contactsTable = new DataTable("contacts");
		foreach ($searchParameters as $fieldName => $fieldData) {
			if (!$federalFirearmsLicenseesTable->columnExists($fieldName) && !$contactsTable->columnExists($fieldName)) {
				continue;
			}
			if (!empty($fieldData)) {
				$whereStatement .= (empty($whereStatement) ? "" : " or ") . $fieldName . " like ?";
				$parameters[] = $fieldData;
			}
		}
		if (!empty($whereExpressions)) {
			if (!empty($whereStatement)) {
				$whereStatement = "(" . $whereStatement . ")";
			}
			foreach ($whereExpressions as $thisWhereExpression) {
				$whereStatement .= (empty($whereStatement) ? "" : " and ") . $thisWhereExpression;
			}
		}
		$resultSet = executeQuery("select *,(select filename from files where file_id = federal_firearms_licensees.file_id) filename,(select filename from files where " .
			"file_id = federal_firearms_licensees.sot_file_id) sot_filename,(select group_concat(product_id) from ffl_product_restrictions where federal_firearms_licensee_id = federal_firearms_licensees.federal_firearms_licensee_id) as restricted_product_ids," .
			"(select group_concat(product_category_id) from ffl_category_restrictions where federal_firearms_licensee_id = federal_firearms_licensees.federal_firearms_licensee_id) as restricted_product_category_ids," .
			"(select group_concat(product_manufacturer_id) from ffl_manufacturer_restrictions where federal_firearms_licensee_id = federal_firearms_licensees.federal_firearms_licensee_id) as restricted_product_manufacturer_ids from " .
			"federal_firearms_licensees join contacts using (contact_id) where contacts.deleted = 0 and federal_firearms_licensees.inactive = 0 and federal_firearms_licensees.client_id = ?" .
			(empty($whereStatement) ? "" : " and (" . $whereStatement . ")"), $parameters);
		$federalFirearmsLicenseeIds = array();
		while ($row = getNextRow($resultSet)) {
			$federalFirearmsLicenseeIds[] = $row['federal_firearms_licensee_id'];
			if (empty($row['business_name'])) {
				$row['business_name'] = $row['licensee_name'];
			}
			if (empty($row['latitude']) || empty($row['longitude'])) {
				$row['latitude'] = getFieldFromId("latitude", "postal_codes", "postal_code", $row['postal_code'], "country_id = ?", $row['country_id']);
				$row['longitude'] = getFieldFromId("longitude", "postal_codes", "postal_code", $row['postal_code'], "country_id = ?", $row['country_id']);
			}
			$mailingAddressRow = getCachedData("ffl_mailing_address", $row['federal_firearms_licensee_id']);
			if (!is_array($mailingAddressRow)) {
				$mailingAddressRow = getRowFromId("addresses", "contact_id", $row['contact_id'], "address_label = 'Mailing'");
				setCachedData("ffl_mailing_address", $row['federal_firearms_licensee_id'], $mailingAddressRow, 168);
			}
			$row['mailing_address_1'] = $mailingAddressRow['address_1'];
			$row['mailing_address_2'] = $mailingAddressRow['address_2'];
			$row['mailing_city'] = $mailingAddressRow['city'];
			$row['mailing_state'] = $mailingAddressRow['state'];
			$row['mailing_postal_code'] = $mailingAddressRow['postal_code'];
			$row['mailing_country_id'] = $mailingAddressRow['country_id'];
			$row['phone_number'] = Contact::getContactPhoneNumber($row['contact_id'], 'Store', true);
			$fflArray[$row['federal_firearms_licensee_id']] = $row;
		}
		if ($centralizedStorage && !empty($federalFirearmsLicenseeIds)) {
			$resultSet = executeQuery("select * from federal_firearms_licensee_details where client_id = ? and federal_firearms_licensee_id in (" . implode(",", $federalFirearmsLicenseeIds) . ")", $GLOBALS['gClientId']);
			while ($row = getNextRow($resultSet)) {
				if (array_key_exists($row['federal_firearms_licensee_id'], $fflArray)) {
					foreach ($row as $fieldName => $fieldData) {
						if (strlen($fieldData) > 0) {
							$fflArray[$row['federal_firearms_licensee_id']][$fieldName] = $fieldData;
						}
					}
					if (!empty($fflArray[$row['federal_firearms_licensee_id']]['inactive'])) {
						unset($fflArray[$row['federal_firearms_licensee_id']]);
					}
				}
			}
		}
		if ($consolidated) {
			return $fflArray;
		}
		$fflRecords = array();
		foreach ($fflArray as $row) {
			if (empty($fieldNames)) {
				$fieldNames = array_keys($row);
			}
			$fflRecords[] = array_values($row);
		}
		return array("ffl_array" => $fflRecords, "field_names" => $fieldNames);
	}

	public static function getFFLBlock($fflRow) {
		$canEdit = canAccessPage("FEDERALFIREARMSLICENSEMAINT") && !getPreference("CENTRALIZED_FFL_STORAGE");
		if (empty($fflRow)) {
			$selectedFFL = "None Selected";
		} else {
			if (!empty($fflRow['license_lookup'])) {
				$fflLookupParts = explode("-", $fflRow['license_lookup']);
				$ezCheckLink = sprintf("<a target='_blank' href='https://fflezcheck.atf.gov/FFLEzCheck/fflSearch?licsRegn=%s&licsDis=%s&licsSeq=%s'>EZCheck Lookup</a>",
					$fflLookupParts[0], $fflLookupParts[1], $fflLookupParts[2]);
			} else {
				$ezCheckLink = "";
			}
			if (strtotime($fflRow['expiration_date']) < time() || strtotime(getFflExpirationDate($fflRow['license_number'])) < time()) {
				$isExpired = true;
			} else {
				$isExpired = false;
			}
			$notes = $fflRow['notes'];
			$notesLength = 30;
			if (strlen($notes) > $notesLength && $canEdit) {
				$notes = "<a target='_blank' href='/federal-firearms-licenses?clear_filter=true&primary_id_only=true&url_page=show&primary_id=" . $fflRow['federal_firearms_licensee_id'] . "'>" . substr($notes, 0, $notesLength) . "..." . "</a>";
			}
			$selectedFFL = $fflRow['license_number'] . "<br>" .
				(empty($fflRow['business_name']) ? $fflRow['licensee_name'] : $fflRow['business_name']) . "<br>" .
				(empty($fflRow['mailing_address_preferred']) ? $fflRow['address_1'] . "<br>" . $fflRow['city'] . ", " . $fflRow['state'] . " " . $fflRow['postal_code'] . "<br>"
					: $fflRow['mailing_address_1'] . "<br>" . (empty($fflRow['mailing_address_2']) ? "" : $fflRow['mailing_address_2'] . "<br>") . $fflRow['mailing_city'] . ", " . $fflRow['mailing_state'] . " " . $fflRow['mailing_postal_code'] . "<br>") .
				(empty($fflRow['email_address']) ? "" : $fflRow['email_address'] . "<br>") .
				(empty($fflRow['phone_number']) ? "" : $fflRow['phone_number'] . "<br>") .
				(empty($notes) ? "" : "Notes: " . $notes . "<br>") .
				"<br>" . $ezCheckLink . "<br>" .
				(canAccessPageCode("FEDERALFIREARMSLICENSEMAINT") ? "<a target='_blank' href='/federal-firearms-licenses?clear_filter=true&primary_id_only=true&url_page=show&primary_id=" . $fflRow['federal_firearms_licensee_id'] . "'>Edit FFL Record</a><br>" : "") .
				(empty($fflRow['file_id']) ? "No license" . ($canEdit ? " <span class='upload-license fas fa-upload'></span>" : "") : "<a target='_blank' href='/download.php?id=" . $fflRow['file_id'] . "'>View License</a>") . "<br>" .
				(empty($fflRow['sot_file_id']) ? "No SOT Document" . ($canEdit ? " <span class='upload-license fas fa-upload'></span>" : "") : "<a target='_blank' href='/download.php?id=" . $fflRow['sot_file_id'] . "'>View SOT</a>") .
				(empty($isExpired) ? "" : "<br><span class='red-text'>LICENSE IS EXPIRED</span>");
		}
		return $selectedFFL;
	}
}
